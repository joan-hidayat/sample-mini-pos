<?php
/**
 * MINI POS EXPRESS — Halaman kasir.
 *
 * Alur akses:
 * 1. Link aktivasi dari Showcase (?tenant=…&lic=…) → lisensi divalidasi ke LMD dan disimpan (lib/LicenseGate.php).
 * 2. Tenant belum punya user → setup.php (buat akun admin pertama).
 * 3. Belum login → login.php. Perangkat lain cukup membuka ?tenant=… lalu login.
 */
require_once __DIR__ . '/lib/bootstrap.php';

// Link reset password admin dari LMD (T-21) masuk lewat alamat aplikasi: teruskan ke halaman reset
if (!empty($_GET['reset_token'])) {
    redirect('reset.php?' . http_build_query(['tenant' => $_GET['tenant'] ?? '', 'reset_token' => $_GET['reset_token']]));
}

[$tenant, $license] = boot_tenant();

if ((new UserRepository(db()))->countAll((int) $tenant['id']) === 0) {
    redirect(page_url('setup.php'));
}
$user = require_login($tenant, $license);
require_module($tenant, $license, $user, 'KASIR');

render('pos', [
    'tenant' => $tenant,
    'license' => $license,
    'user' => $user,
    'products' => (new ProductRepository(db()))->listActive((int) $tenant['id']),
]);
