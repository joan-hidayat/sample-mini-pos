<?php
/**
 * Kelola produk toko (khusus admin, bagian dari modul KASIR): tambah, ubah, aktif/nonaktifkan (T-30).
 */
require_once __DIR__ . '/lib/bootstrap.php';

[$tenant, $license] = boot_tenant();
$user = require_login($tenant, $license);
if ($user['role'] !== 'admin') {
    flash('error', 'Halaman Kelola Produk hanya untuk admin.');
    redirect(page_url('index.php'));
}
require_module($tenant, $license, $user, 'KASIR');

$repo = new ProductRepository(db());
$tenantId = (int) $tenant['id'];
$trackStock = has_module($license, 'STOK');
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $productId = (int) ($_POST['product_id'] ?? 0);
    try {
        if ($action === 'create') {
            $old = array_intersect_key($_POST, array_flip(['name', 'category', 'emoji', 'price', 'stock']));
            $product = $repo->create($tenantId, $_POST, $trackStock ? ($_POST['stock'] ?? 0) : 0);
            flash('success', "Produk {$product['name']} ditambahkan.");
        } elseif ($action === 'update') {
            $product = $repo->update($productId, $tenantId, $_POST);
            flash('success', "Produk {$product['name']} disimpan.");
        } elseif ($action === 'deactivate' || $action === 'activate') {
            $product = $repo->setStatus($productId, $tenantId, $action === 'activate' ? 'active' : 'inactive');
            flash('success', "Produk {$product['name']} " . ($action === 'activate' ? 'diaktifkan kembali.' : 'dinonaktifkan (tidak tampil di kasir).'));
        } else {
            throw new InvalidArgumentException('Aksi tidak dikenal.');
        }
        redirect(page_url('produk.php'));
    } catch (InvalidArgumentException $e) {
        flash('error', $e->getMessage());
        if ($action !== 'create') {
            redirect(page_url('produk.php'));
        }
    }
}

render('produk', [
    'tenant' => $tenant,
    'license' => $license,
    'user' => $user,
    'products' => $repo->listAll($tenantId),
    'trackStock' => $trackStock,
    'old' => $old,
]);
