<?php
/**
 * Stok produk (modul STOK, khusus admin). Dengan modul ini kasir mengurangi stok & menolak penjualan melebihi stok.
 */
require_once __DIR__ . '/lib/bootstrap.php';

[$tenant, $license] = boot_tenant();
$user = require_login($tenant, $license);
require_module($tenant, $license, $user, 'STOK');

$repo = new ProductRepository(db());
$tenantId = (int) $tenant['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $product = $repo->updateStock((int) ($_POST['product_id'] ?? 0), $tenantId, $_POST['stock'] ?? '', $_POST['min_stock'] ?? '');
        flash('success', "Stok {$product['name']} disimpan: {$product['stock']}.");
    } catch (InvalidArgumentException $e) {
        flash('error', $e->getMessage());
    }
    redirect(page_url('stok.php'));
}

render('stok', ['tenant' => $tenant, 'license' => $license, 'user' => $user, 'products' => $repo->listActive($tenantId)]);
