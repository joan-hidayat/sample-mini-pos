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
            // License key baru untuk tenant ini: secret lama (milik lisensi lain) tidak dipakai
            $result = $this->validateRemote($requested, $licenseKey, null);
            if (!$result['valid']) {
                $this->deny($result);
            }
            $tenant = $this->saveTenant($requested, $licenseKey, $result['data'], true);
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

        $result = $this->validateRemote($tenant['domain'], $tenant['license_key'], $tenant['instance_secret'] ?? null);
        if ($result['valid']) {
            $tenant = $this->saveTenant($tenant['domain'], $tenant['license_key'], $result['data']);
            return $this->cache($tenant);
        }

        if ($result['unreachable'] && $this->withinOfflineGrace($tenant)) {
            return $this->licenseFromTenant($tenant, true);
        }
        $this->deny($result);
    }

    /**
     * $secret: instance secret dari LMD (T-22). Jika ada, request ditandatangani
     * HMAC-SHA256(secret, timestamp + "\n" + body) lewat header X-LMD-Timestamp & X-LMD-Signature.
     */
    private function validateRemote($domain, $licenseKey, $secret) {
        $payload = json_encode([
            'license_key' => $licenseKey,
            // Konvensi instance key (lmd-client.php), sama dengan yang dibuat LMD saat order dari Showcase
            'instance_key' => lmd_client('instance_key_prefix') . substr($licenseKey, -8),
            'domain' => $domain,
        ]);

        $headers = [];
        if ($secret) {
            $timestamp = (string) time();
            $headers = [
                'X-LMD-Timestamp: ' . $timestamp,
                'X-LMD-Signature: ' . hash_hmac('sha256', $timestamp . "\n" . $payload, $secret),
            ];
        }
        $json = $this->postLmd('/licenses/validate', $payload, $headers);

        if (!is_array($json)) {
            return ['valid' => false, 'unreachable' => true, 'message' => 'Server lisensi Central SaaS tidak dapat dihubungi.'];
        }
        $data = $json['data'] ?? [];

        // Standar Integrasi v1: lisensi aktif milik aplikasi lain tetap ditolak
        if (!empty($data['valid']) && ($data['app_code'] ?? null) !== lmd_client('app_code')) {
            $data = ['valid' => false, 'reason' => 'app_mismatch', 'app_code' => $data['app_code'] ?? null, 'application' => $data['application'] ?? null];
        }
        return [
            'valid' => !empty($data['valid']),
            'unreachable' => false,
            'message' => $json['message'] ?? 'Lisensi tidak valid.',
            'data' => $data,
        ];
    }

    /**
     * Memeriksa ($consume = false) atau menghanguskan ($consume = true) token reset password admin dari LMD (T-21).
     * Mengembalikan ['valid' => bool, 'reason' => ...]; reason 'unreachable' jika LMD tidak bisa dihubungi.
     */
    public function checkResetToken(array $tenant, $token, $consume) {
        $json = $this->postLmd('/licenses/reset_token', json_encode([
            'license_key' => $tenant['license_key'],
            'token' => $token,
            'consume' => (bool) $consume,
        ]));
        if (!is_array($json)) {
            return ['valid' => false, 'reason' => 'unreachable'];
        }
        $data = $json['data'] ?? [];
        return ['valid' => !empty($data['valid']), 'reason' => $data['reason'] ?? null];
    }

    private function postLmd($path, $payload, array $headers = []) {
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => implode("\r\n", array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers)) . "\r\n",
            'content' => $payload,
            'timeout' => 5,
            'ignore_errors' => true,
        ]]);
        $raw = @file_get_contents(lmd_client('lmd_api_url') . $path, false, $context);
        return $raw !== false ? json_decode($raw, true) : null;
    }

    private function findTenant($domain) {
        $stmt = $this->db->prepare("SELECT * FROM tenants WHERE domain = :domain");
        $stmt->execute(['domain' => $domain]);
        return $stmt->fetch() ?: null;
    }

    private function saveTenant($domain, $licenseKey, array $data, $newLicense = false) {
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
        // Secret hanya dikirim LMD sekali (validasi pertama); simpan, atau buang bila license key berganti
        if (!empty($data['instance_secret']) || $newLicense) {
            $this->db->prepare("UPDATE tenants SET instance_secret = :secret WHERE domain = :domain")
                ->execute(['secret' => $data['instance_secret'] ?? null, 'domain' => $domain]);
        }
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
            'signature_required' => 'Instalasi ini belum memiliki kunci rahasia dari Central SaaS (mis. database aplikasi dipulihkan atau dipindah). Minta penyedia layanan menekan "Reset secret" pada instalasi toko ini, lalu muat ulang halaman.',
            'signature_invalid' => 'Kunci rahasia instalasi ini tidak cocok dengan Central SaaS. Minta penyedia layanan menekan "Reset secret" pada instalasi toko ini, lalu muat ulang halaman.',
            'signature_expired' => 'Jam server aplikasi ini berbeda lebih dari 5 menit dengan Central SaaS. Periksa pengaturan jam & zona waktu server, lalu muat ulang halaman.',
            'app_mismatch' => 'License key ini terdaftar untuk aplikasi ' . ($result['data']['application'] ?? 'lain')
                . ', bukan Mini POS Express. Pesan paket Mini POS di Software Showcase untuk mendapatkan license key yang sesuai.',
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
