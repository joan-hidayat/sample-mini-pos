<?php
/**
 * Laporan penjualan (modul LAPORAN, khusus admin).
 */
require_once __DIR__ . '/lib/bootstrap.php';

[$tenant, $license] = boot_tenant();
$user = require_login($tenant, $license);
require_module($tenant, $license, $user, 'LAPORAN');

$today = date('Y-m-d');
$validDate = function ($value) {
    $date = DateTime::createFromFormat('!Y-m-d', (string) $value);
    return $date && $date->format('Y-m-d') === $value ? $value : null;
};
$from = $validDate($_GET['from'] ?? '') ?? $today;
$to = $validDate($_GET['to'] ?? '') ?? $today;
if ($from > $to) {
    [$from, $to] = [$to, $from];
}
// Rentang dibatasi 1 tahun agar query tetap ringan
if (strtotime($to) - strtotime($from) > 366 * 86400) {
    $from = date('Y-m-d', strtotime($to . ' -366 days'));
}

render('laporan', [
    'tenant' => $tenant,
    'license' => $license,
    'user' => $user,
    'from' => $from,
    'to' => $to,
    'report' => (new SaleRepository(db()))->report((int) $tenant['id'], $from, $to),
]);
