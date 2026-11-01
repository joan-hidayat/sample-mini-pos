<?php
/**
 * Info langganan toko (khusus admin, T-42): no. pesanan, paket, modul, kuota, masa aktif, link kuitansi.
 * Data dari respons validate LMD (disimpan di tenants.subscription), jadi tetap tampil saat mode tenggang.
 */
require_once __DIR__ . '/lib/bootstrap.php';

[$tenant, $license] = boot_tenant();
$user = require_login($tenant, $license);
if ($user['role'] !== 'admin') {
    flash('error', 'Halaman Langganan hanya untuk admin.');
    redirect(page_url('index.php'));
}

render('langganan', [
    'tenant' => $tenant,
    'license' => $license,
    'user' => $user,
    'activeUsers' => (new UserRepository(db()))->countActive((int) $tenant['id']),
]);
