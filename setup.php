<?php
/**
 * Aktivasi pertama: membuat akun admin toko.
 * Hanya tersedia selama tenant belum punya user; email harus sama dengan email pemesan di LMD.
 */
require_once __DIR__ . '/lib/bootstrap.php';

[$tenant, $license] = boot_tenant();
$users = new UserRepository(db());

if ($users->countAll((int) $tenant['id']) > 0) {
    redirect(page_url('login.php'));
}

$error = null;
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $old = ['name' => trim($_POST['name'] ?? ''), 'email' => strtolower(trim($_POST['email'] ?? ''))];
    $password = (string) ($_POST['password'] ?? '');

    if (empty($license['licensee_email']) || !hash_equals($license['licensee_email'], $old['email'])) {
        $error = 'Email tidak cocok dengan email pendaftaran lisensi toko ini.';
    } elseif (!hash_equals($password, (string) ($_POST['password_confirm'] ?? ''))) {
        $error = 'Konfirmasi password tidak sama.';
    } else {
        try {
            $admin = $users->create((int) $tenant['id'], $old['name'], $old['email'], $password, 'admin', $license['max_users']);
            login_user($admin);
            flash('success', 'Akun admin berhasil dibuat. Selamat datang!');
            redirect(page_url('index.php'));
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
        }
    }
}

render('setup', ['license' => $license, 'old' => $old, 'error' => $error]);
