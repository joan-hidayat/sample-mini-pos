<?php
/**
 * API kasir untuk app.js (JSON, sesi login yang sama dengan halaman).
 *   POST api.php?tenant=…&action=checkout   body: {"items":[{"id":1,"qty":2}]}   header: X-CSRF-Token
 * Modul KASIR wajib ada di lisensi; stok dikurangi & dicek hanya bila ada modul STOK (T-28).
 */
require_once __DIR__ . '/lib/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function json_response($status, array $body) {
    http_response_code($status);
    echo json_encode($body);
    exit;
}

// boot_tenant() menghentikan request (redirect / halaman error) bila lisensi tidak valid
[$tenant, $license] = boot_tenant();
$user = current_user($tenant);
if (!$user) {
    json_response(401, ['ok' => false, 'message' => 'Silakan masuk kembali.']);
}
if (!has_module($license, 'KASIR')) {
    json_response(403, ['ok' => false, 'message' => 'Modul Kasir tidak termasuk paket lisensi toko ini.']);
}
if (($_GET['action'] ?? '') !== 'checkout' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(404, ['ok' => false, 'message' => 'Endpoint tidak dikenal.']);
}
$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!is_string($csrf) || !hash_equals(csrf_token(), $csrf)) {
    json_response(419, ['ok' => false, 'message' => 'Sesi kedaluwarsa. Muat ulang halaman.']);
}

$body = json_decode(file_get_contents('php://input'), true);
try {
    $sale = (new SaleRepository(db()))->checkout((int) $tenant['id'], (int) $user['id'], (array) ($body['items'] ?? []), has_module($license, 'STOK'));
    json_response(200, ['ok' => true, 'sale' => $sale]);
} catch (InvalidArgumentException $e) {
    json_response(422, ['ok' => false, 'message' => $e->getMessage()]);
}
