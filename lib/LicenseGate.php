<?php
/**
 * LicenseGate — menghubungkan Mini POS dengan Central SaaS LMD.
 *
 * - Aktivasi: link dari Showcase (?tenant=…&lic=…) divalidasi ke LMD, lalu license key disimpan
 *   di tabel tenants milik Mini POS. Setelah itu perangkat lain cukup membuka ?tenant=….
 * - Setiap request: lisensi dicek ke LMD (POST licenses/validate), hasilnya di-cache di sesi.
 * - Jika LMD tidak bisa dihubungi: tetap jalan selama masa tenggang sejak validasi sukses terakhir.
 */
class LicenseGate {
    const TENANT_PATTERN = '/^[a-z0-9](?:[a-z0-9.-]{1,253}[a-z0-9])$/';

    private $db;
    private $config;

    public function __construct(PDO $db, array $config) {
        $this->db = $db;
        $this->config = $config;
    }

    /**
     * Menentukan tenant aktif dari URL/sesi; mengaktifkan tenant baru bila URL membawa license key.
     */
    public function enter() {
        $requested = strtolower(trim((string) ($_GET['tenant'] ?? '')));
        $licenseKey = trim((string) ($_GET['lic'] ?? ''));

        if ($requested === '') {
            $requested = $_SESSION['tenant'] ?? '';
        }
        if ($requested === '' || !preg_match(self::TENANT_PATTERN, $requested)) {
            redirect(showcase_url('unauthorized_direct_access'));
        }

        // Pindah tenant di browser yang sama: sesi login tenant sebelumnya diakhiri
        if (($_SESSION['tenant'] ?? null) !== $requested) {
            logout_user();
            unset($_SESSION['license_cache']);
            $_SESSION['tenant'] = $requested;
        }

        $tenant = $this->findTenant($requested);

        if ($licenseKey !== '' && (!$tenant || !hash_equals($tenant['license_key'], $licenseKey))) {
            $result = $this->validateRemote($requested, $licenseKey);
            if (!$result['valid']) {
                $this->deny($result);
            }
            $tenant = $this->saveTenant($requested, $licenseKey, $result['data']);
            $this->cache($tenant);
        }

        if (!$tenant) {
            redirect(showcase_url('tenant_not_activated'));
        }

        // License key tidak dibiarkan di address bar / riwayat browser
        if ($licenseKey !== '') {
            redirect(strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query(['tenant' => $requested]));
        }
        return $tenant;
    }

    /**
     * Memastikan lisensi tenant masih valid. Mengembalikan info lisensi untuk ditampilkan.
     */
    public function check(array $tenant) {
        $cached = $_SESSION['license_cache'] ?? null;
        if ($cached && $cached['tenant_id'] === (int) $tenant['id'] && time() - $cached['checked_at'] < $this->config['license_cache_ttl']) {
            return $cached['license'];
        }

        $result = $this->validateRemote($tenant['domain'], $tenant['license_key']);
        if ($result['valid']) {
            $tenant = $this->saveTenant($tenant['domain'], $tenant['license_key'], $result['data']);
            return $this->cache($tenant);
        }

        if ($result['unreachable'] && $this->withinOfflineGrace($tenant)) {
            return $this->licenseFromTenant($tenant, true);
        }
        $this->deny($result);
    }

    private function validateRemote($domain, $licenseKey) {
        $apiUrl = $this->config['lmd_api_url'] ?: base_host_url() . '/License-Management-Dashboard/api/index.php';
        $payload = json_encode([
            'license_key' => $licenseKey,
            // Konvensi instance key Mini POS, sama dengan yang dibuat LMD saat order dari Showcase
            'instance_key' => 'INST-STORE-' . substr($licenseKey, -8),
            'domain' => $domain,
        ]);

        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
            'content' => $payload,
            'timeout' => 5,
            'ignore_errors' => true,
        ]]);
        $raw = @file_get_contents(rtrim($apiUrl, '/') . '/licenses/validate', false, $context);
        $json = $raw !== false ? json_decode($raw, true) : null;

        if (!is_array($json)) {
            return ['valid' => false, 'unreachable' => true, 'message' => 'Server lisensi Central SaaS tidak dapat dihubungi.'];
        }
        $data = $json['data'] ?? [];
        return [
            'valid' => !empty($data['valid']),
            'unreachable' => false,
            'message' => $json['message'] ?? 'Lisensi tidak valid.',
            'data' => $data,
        ];
    }

    private function findTenant($domain) {
        $stmt = $this->db->prepare("SELECT * FROM tenants WHERE domain = :domain");
        $stmt->execute(['domain' => $domain]);
        return $stmt->fetch() ?: null;
    }

    private function saveTenant($domain, $licenseKey, array $data) {
        $stmt = $this->db->prepare("
            INSERT INTO tenants (domain, license_key, store_name, licensee_name, licensee_email, app_name, max_users, modules, expires_at, last_validated_at)
            VALUES (:domain, :license_key, :store_name, :licensee_name, :licensee_email, :app_name, :max_users, :modules, :expires_at, NOW())
            ON DUPLICATE KEY UPDATE license_key = VALUES(license_key), store_name = VALUES(store_name),
                licensee_name = VALUES(licensee_name), licensee_email = VALUES(licensee_email), app_name = VALUES(app_name),
                max_users = VALUES(max_users), modules = VALUES(modules), expires_at = VALUES(expires_at), last_validated_at = NOW()
        ");
        $stmt->execute([
            'domain' => $domain,
            'license_key' => $licenseKey,
            'store_name' => $data['instance_name'] ?? null,
            'licensee_name' => $data['licensee']['name'] ?? null,
            'licensee_email' => isset($data['licensee']['email']) ? strtolower($data['licensee']['email']) : null,
            'app_name' => $data['application'] ?? null,
            'max_users' => max(1, (int) ($data['max_users'] ?? 1)),
            'modules' => json_encode($data['modules'] ?? []),
            'expires_at' => $data['expires_at'] ?? null,
        ]);
        return $this->findTenant($domain);
    }

    private function cache(array $tenant) {
        $license = $this->licenseFromTenant($tenant, false);
        $_SESSION['license_cache'] = ['tenant_id' => (int) $tenant['id'], 'checked_at' => time(), 'license' => $license];
        return $license;
    }

    private function licenseFromTenant(array $tenant, $offline) {
        return [
            'license_key' => $tenant['license_key'],
            'store_name' => $tenant['store_name'] ?: strtoupper(explode('.', $tenant['domain'])[0]),
            'licensee_name' => $tenant['licensee_name'],
            'licensee_email' => $tenant['licensee_email'],
            'app_name' => $tenant['app_name'],
            'max_users' => (int) $tenant['max_users'],
            'modules' => json_decode($tenant['modules'] ?? '[]', true) ?: [],
            'expires_at' => $tenant['expires_at'],
            'offline' => $offline,
        ];
    }

    private function withinOfflineGrace(array $tenant) {
        if (empty($tenant['last_validated_at'])) {
            return false;
        }
        $graceUntil = strtotime($tenant['last_validated_at']) + $this->config['offline_grace_hours'] * 3600;
        $notExpired = empty($tenant['expires_at']) || strtotime($tenant['expires_at']) > time();
        return $notExpired && time() < $graceUntil;
    }

    private function deny(array $result) {
        unset($_SESSION['license_cache']);
        logout_user();
        if ($result['unreachable']) {
            http_response_code(503);
            render('error', [
                'title' => 'Server Lisensi Tidak Terjangkau',
                'message' => 'Mini POS tidak dapat menghubungi Central SaaS untuk memverifikasi lisensi. Periksa koneksi internet server, lalu muat ulang halaman.',
            ]);
            exit;
        }
        // Lisensi sah tetapi instalasi ini ditolak (T-02): jelaskan di tempat, jangan lempar ke Showcase
        $installationErrors = [
            'domain_mismatch' => 'Lisensi toko ini terkunci ke domain lain. Buka aplikasi dari alamat toko yang terdaftar, atau hubungi penyedia layanan untuk memindahkan lisensi.',
            'instance_limit_reached' => 'Kuota instalasi paket Anda sudah penuh. Minta admin Central SaaS memblokir instalasi yang tidak dipakai, atau upgrade paket untuk menambah lokasi.',
            'instance_blocked' => 'Instalasi di lokasi ini telah diblokir oleh admin Central SaaS. Hubungi penyedia layanan untuk mengaktifkannya kembali.',
            'instance_key_conflict' => 'Identitas instalasi ini sudah terdaftar pada lisensi lain. Hubungi penyedia layanan.',
        ];
        $reason = $result['data']['reason'] ?? null;
        if (isset($installationErrors[$reason])) {
            http_response_code(403);
            render('error', ['title' => 'Instalasi Tidak Diizinkan', 'message' => $installationErrors[$reason]]);
            exit;
        }
        redirect(showcase_url('invalid_license'));
    }
}
