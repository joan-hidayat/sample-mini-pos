<?php
/**
 * Manifest integrasi untuk Central SaaS LMD — Standar Integrasi v1.2 bagian 4.4 (T-34).
 *
 * Admin LMD cukup memasukkan alamat aplikasi ini di Applications & Modules → "Impor dari Manifest";
 * LMD membuat aplikasi + modul dengan kode yang PASTI sama dengan yang dikenali aplikasi.
 * Sumber data: lmd-client.php (identitas) dan APP_MODULES di lib/bootstrap.php (modul).
 * Tidak berisi rahasia; tidak membuka sesi atau database.
 */
require_once __DIR__ . '/lib/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

$modules = [];
$order = 0;
foreach (APP_MODULES as $code => $module) {
    $modules[] = [
        'code' => $code,
        'name' => $module['label'],
        'description' => $module['description'] ?? null,
        'module_type' => 'standard',
        'sort_order' => ++$order,
        'available' => $module['ready'],
    ];
}

// Launch URL mengikuti folder tempat aplikasi dipasang (mis. /mini-pos/)
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/mini-pos/lmd-manifest.php')), '/') . '/';

echo json_encode([
    'manifest_version' => 1,
    'standard' => 'lmd-integration/1.2',
    'app_code' => lmd_client('app_code'),
    'name' => lmd_client('app_name'),
    'description' => lmd_client('app_description'),
    'type' => 'hosted',
    'launch_url' => $base . '?tenant={tenant}&lic={license_key}',
    'require_signature' => true,
    'modules' => $modules,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
