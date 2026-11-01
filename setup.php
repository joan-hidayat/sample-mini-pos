<?php
/**
 * Aktivasi pertama: membuat akun admin toko.
 * Hanya tersedia selama tenant belum punya user; email harus sama dengan email pemesan di LMD.
 * Bukti kepemilikan lisensi (T-42): dibuka dari link aktivasi (?lic=…) di sesi ini, ATAU license key diketik di form.
 * Jadi bila perangkat mati di tengah jalan, customer cukup membuka alamat toko di perangkat lain + license key.
 */
require_once __DIR__ . '/lib/bootstrap.php';

[$tenant, $license] = boot_tenant();
$users = new UserRepository(db());

if ($users->countAll((int) $tenant['id']) > 0) {
    redirect(page_url('login.php'));
}

$error = null;
$old = [];
$hasProof = ($_SESSION['license_proof'] ?? null) === $tenant['domain'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $old = ['name' => trim($_POST['name'] ?? ''), 'email' => strtolower(trim($_POST['email'] ?? ''))];
    $password = (string) ($_POST['password'] ?? '');

    $typedKey = strtoupper(trim((string) ($_POST['license_key'] ?? '')));
    if (!$hasProof && !hash_equals($tenant['license_key'], $typedKey)) {
        $error = 'License key tidak cocok. Lihat license key di Software Showcase → Cek Pesanan, atau di layar sukses setelah pembayaran.';
    } elseif (empty($license['licensee_email']) || !hash_equals($license['licensee_email'], $old['email'])) {
        $error = 'Email tidak cocok dengan email pendaftaran lisensi toko ini.';
    } elseif (!hash_equals($password, (string) ($_POST['password_confirm'] ?? ''))) {
        $error = 'Konfirmasi password tidak sama.';
    } else {
        try {
            $admin = $users->create((int) $tenant['id'], $old['name'], $old['email'], $password, 'admin', $license['max_users']);
            Notifier::send($tenant, 'admin_created', $admin['email'], $admin['name']);
            login_user($admin);
            flash('success', 'Akun admin berhasil dibuat. Selamat datang!');
            redirect(page_url('index.php'));
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
        }
    }
}

render('setup', ['license' => $license, 'old' => $old, 'error' => $error, 'hasProof' => $hasProof]);
