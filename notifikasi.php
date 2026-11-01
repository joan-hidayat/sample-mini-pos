<?php
/**
 * Kotak surat notifikasi akun toko (khusus admin, T-43). Kanal 'mailbox': notifikasi disimpan di sini, tidak dikirim keluar.
 */
require_once __DIR__ . '/lib/bootstrap.php';

[$tenant, $license] = boot_tenant();
$user = require_login($tenant, $license);
if ($user['role'] !== 'admin') {
    flash('error', 'Halaman Notifikasi hanya untuk admin.');
    redirect(page_url('index.php'));
}

$stmt = db()->prepare("SELECT * FROM notifications WHERE tenant_id = :tenant_id ORDER BY id DESC LIMIT 100");
$stmt->execute(['tenant_id' => (int) $tenant['id']]);

render('notifikasi', ['tenant' => $tenant, 'license' => $license, 'user' => $user, 'items' => $stmt->fetchAll()]);
