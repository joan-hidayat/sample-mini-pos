<?php
/**
 * Akun pengguna Mini POS per tenant. Kuota akun aktif dibatasi max_users dari lisensi LMD.
 */
class UserRepository {
    const ROLES = ['admin' => 'Admin', 'kasir' => 'Kasir'];

    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function countAll($tenantId) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE tenant_id = :tenant_id");
        $stmt->execute(['tenant_id' => $tenantId]);
        return (int) $stmt->fetchColumn();
    }

    public function countActive($tenantId) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE tenant_id = :tenant_id AND status = 'active'");
        $stmt->execute(['tenant_id' => $tenantId]);
        return (int) $stmt->fetchColumn();
    }

    public function listByTenant($tenantId) {
        $stmt = $this->db->prepare("
            SELECT id, name, email, role, status, last_login_at, created_at
            FROM users WHERE tenant_id = :tenant_id
            ORDER BY role = 'admin' DESC, status = 'active' DESC, name ASC
        ");
        $stmt->execute(['tenant_id' => $tenantId]);
        return $stmt->fetchAll();
    }

    public function findById($id, $tenantId) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = :id AND tenant_id = :tenant_id");
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        return $stmt->fetch() ?: null;
    }

    public function findByEmail($email, $tenantId) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = :email AND tenant_id = :tenant_id");
        $stmt->execute(['email' => strtolower(trim($email)), 'tenant_id' => $tenantId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Membuat akun. Melempar InvalidArgumentException untuk input tidak valid / kuota penuh.
     */
    public function create($tenantId, $name, $email, $password, $role, $maxUsers) {
        $name = trim($name);
        $email = strtolower(trim($email));
        $this->assertValid($name, $email, $password, $role);

        $this->db->beginTransaction();
        try {
            // Kunci baris tenant agar dua pembuatan akun bersamaan tidak melewati kuota
            $this->db->prepare("SELECT id FROM tenants WHERE id = :id FOR UPDATE")->execute(['id' => $tenantId]);
            if ($this->countActive($tenantId) >= $maxUsers) {
                throw new InvalidArgumentException("Kuota lisensi penuh: maksimal {$maxUsers} user aktif. Nonaktifkan user lain atau upgrade paket di Central SaaS.");
            }
            if ($this->findByEmail($email, $tenantId)) {
                throw new InvalidArgumentException("Email {$email} sudah terdaftar.");
            }
            $this->db->prepare("
                INSERT INTO users (tenant_id, name, email, password_hash, role, status)
                VALUES (:tenant_id, :name, :email, :password_hash, :role, 'active')
            ")->execute([
                'tenant_id' => $tenantId,
                'name' => $name,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $role,
            ]);
            $id = (int) $this->db->lastInsertId();
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
        return $this->findById($id, $tenantId);
    }

    public function setStatus($id, $tenantId, $status, $maxUsers) {
        if ($status === 'active' && $this->countActive($tenantId) >= $maxUsers) {
            throw new InvalidArgumentException("Kuota lisensi penuh: maksimal {$maxUsers} user aktif.");
        }
        $this->db->prepare("UPDATE users SET status = :status, failed_attempts = 0, locked_until = NULL WHERE id = :id AND tenant_id = :tenant_id")
            ->execute(['status' => $status, 'id' => $id, 'tenant_id' => $tenantId]);
    }

    /**
     * Daftar admin aktif (untuk halaman reset password admin dari LMD).
     */
    public function listActiveAdmins($tenantId) {
        $stmt = $this->db->prepare("SELECT id, name, email FROM users WHERE tenant_id = :tenant_id AND role = 'admin' AND status = 'active' ORDER BY name");
        $stmt->execute(['tenant_id' => $tenantId]);
        return $stmt->fetchAll();
    }

    /**
     * Validasi password tanpa menyimpan (dipakai sebelum token reset dihanguskan).
     */
    public function checkPassword($password) {
        $this->assertPassword($password);
    }

    public function resetPassword($id, $tenantId, $password) {
        $this->assertPassword($password);
        $this->db->prepare("UPDATE users SET password_hash = :hash, failed_attempts = 0, locked_until = NULL WHERE id = :id AND tenant_id = :tenant_id")
            ->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $id, 'tenant_id' => $tenantId]);
    }

    /**
     * Verifikasi login dengan penguncian sementara setelah percobaan gagal berulang.
     * Mengembalikan [user|null, pesan error|null].
     */
    public function attemptLogin($email, $password, $tenantId, $maxAttempts, $lockMinutes) {
        $user = $this->findByEmail($email, $tenantId);
        $genericError = 'Email atau password salah.';

        if (!$user) {
            // Tetap jalankan hash agar waktu respons tidak membedakan email terdaftar/tidak
            static $dummyHash = null;
            $dummyHash = $dummyHash ?: password_hash('dummy-password', PASSWORD_DEFAULT);
            password_verify($password, $dummyHash);
            return [null, $genericError];
        }
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            $minutes = (int) ceil((strtotime($user['locked_until']) - time()) / 60);
            return [null, "Akun dikunci sementara karena terlalu banyak percobaan gagal. Coba lagi dalam {$minutes} menit."];
        }
        if (!password_verify($password, $user['password_hash'])) {
            $attempts = (int) $user['failed_attempts'] + 1;
            $lockedUntil = $attempts >= $maxAttempts ? date('Y-m-d H:i:s', time() + $lockMinutes * 60) : null;
            $this->db->prepare("UPDATE users SET failed_attempts = :attempts, locked_until = :locked WHERE id = :id")
                ->execute(['attempts' => $lockedUntil ? 0 : $attempts, 'locked' => $lockedUntil, 'id' => $user['id']]);
            return [null, $genericError];
        }
        if ($user['status'] !== 'active') {
            return [null, 'Akun ini dinonaktifkan. Hubungi admin toko.'];
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $this->db->prepare("UPDATE users SET password_hash = :hash WHERE id = :id")
                ->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $user['id']]);
        }
        $this->db->prepare("UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = :id")
            ->execute(['id' => $user['id']]);
        return [$user, null];
    }

    private function assertValid($name, $email, $password, $role) {
        if ($name === '' || mb_strlen($name) > 150) {
            throw new InvalidArgumentException('Nama wajib diisi (maksimal 150 karakter).');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Format email tidak valid.');
        }
        if (!isset(self::ROLES[$role])) {
            throw new InvalidArgumentException('Peran tidak dikenal.');
        }
        $this->assertPassword($password);
    }

    private function assertPassword($password) {
        $min = (int) app_config('password_min_length');
        if (strlen($password) < $min) {
            throw new InvalidArgumentException("Password minimal {$min} karakter.");
        }
        if (strlen($password) > 72) {
            throw new InvalidArgumentException('Password maksimal 72 karakter.');
        }
    }
}
