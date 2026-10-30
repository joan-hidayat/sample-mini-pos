<?php
/**
 * Heartbeat ke Central SaaS LMD dari server (bukan browser): request ditandatangani instance secret (T-22),
 * jadi browser tidak perlu (dan tidak boleh) memegang secret atau license key.
 */
require_once __DIR__ . '/lib/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
start_app_session();
unset($_SESSION['license_cache']);

// boot_tenant() menghentikan request (redirect / halaman error) bila lisensi tidak valid
[$tenant, $license] = boot_tenant();
if (!current_user($tenant)) {
    http_response_code(401);
    echo json_encode(['valid' => false, 'message' => 'Silakan masuk kembali.']);
    exit;
}
echo json_encode([
    'valid' => true,
    'application' => $license['app_name'],
    'expires_at' => $license['expires_at'],
    'offline' => !empty($license['offline']),
]);
