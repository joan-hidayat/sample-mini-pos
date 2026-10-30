<?php
/**
 * Bootstrap Mini POS: konfigurasi, sesi, koneksi database, dan helper umum.
 */
require_once __DIR__ . '/LicenseGate.php';
require_once __DIR__ . '/UserRepository.php';

const MINIPOS_ROOT = __DIR__ . '/..';

function app_config($key = null) {
    static $config = null;
    if ($config === null) {
        $config = require MINIPOS_ROOT . '/config.php';
    }
    return $key === null ? $config : ($config[$key] ?? null);
}

/**
 * Identitas aplikasi untuk LMD (lmd-client.php). URL API otomatis memakai host yang sama bila tidak diisi.
 */
function lmd_client($key) {
    static $client = null;
    if ($client === null) {
        $client = require MINIPOS_ROOT . '/lmd-client.php';
        $client['lmd_api_url'] = rtrim($client['lmd_api_url'] ?: base_host_url() . '/License-Management-Dashboard/api/index.php', '/');
    }
    return $client[$key] ?? null;
}

function is_https() {
    return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}

function base_host_url() {
    return (is_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost:8080');
}

function showcase_url($notice = null) {
    $url = app_config('showcase_url') ?: base_host_url() . '/software-showcase/';
    return $notice ? $url . '?notice=' . urlencode($notice) : $url;
}

function start_app_session() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('MINIPOSSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * Koneksi ke database Mini POS. Database & tabel dibuat otomatis jika belum ada.
 */
function db() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $cfg = app_config('db');
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO("mysql:host={$cfg['host']};charset=utf8mb4", $cfg['user'], $cfg['pass'], $options);

    $dbName = str_replace('`', '', $cfg['name']);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$dbName}`");

    if (!$pdo->query("SHOW TABLES LIKE 'users'")->fetch()) {
        $schema = preg_replace('/^--.*$/m', '', file_get_contents(MINIPOS_ROOT . '/database/schema.sql'));
        foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
            $pdo->exec($statement);
        }
    }
    // Database lama: tambahkan kolom yang muncul setelah versi pertama
    if (!$pdo->query("SHOW COLUMNS FROM tenants LIKE 'instance_secret'")->fetch()) {
        $pdo->exec("ALTER TABLE tenants ADD COLUMN instance_secret CHAR(64) NULL AFTER license_key");
    }
    return $pdo;
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/**
 * URL halaman Mini POS untuk tenant aktif, misal page_url('login.php').
 */
function page_url($page, array $params = []) {
    $tenant = $_SESSION['tenant'] ?? null;
    $query = $tenant ? array_merge(['tenant' => $tenant], $params) : $params;
    return $page . ($query ? '?' . http_build_query($query) : '');
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf() {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        render('error', ['title' => 'Sesi Kedaluwarsa', 'message' => 'Formulir sudah tidak berlaku. Muat ulang halaman lalu coba lagi.']);
        exit;
    }
}

function flash($type = null, $message = null) {
    if ($type !== null) {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
        return null;
    }
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * Render view dari folder views/ dengan variabel yang diberikan.
 */
function render($view, array $vars = []) {
    extract($vars, EXTR_SKIP);
    require MINIPOS_ROOT . "/views/{$view}.php";
}

/**
 * Pengguna yang sedang login untuk tenant aktif (null jika belum login / akun dinonaktifkan).
 */
function current_user(array $tenant) {
    if (empty($_SESSION['user_id']) || ($_SESSION['user_tenant_id'] ?? null) !== (int) $tenant['id']) {
        return null;
    }
    $user = (new UserRepository(db()))->findById((int) $_SESSION['user_id'], (int) $tenant['id']);
    if (!$user || $user['status'] !== 'active') {
        logout_user();
        return null;
    }
    return $user;
}

function login_user(array $user) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_tenant_id'] = (int) $user['tenant_id'];
}

function logout_user() {
    unset($_SESSION['user_id'], $_SESSION['user_tenant_id']);
    session_regenerate_id(true);
}

/**
 * Titik masuk standar setiap halaman: sesi + tenant + lisensi valid.
 * Mengembalikan [tenant, license].
 */
function boot_tenant() {
    start_app_session();
    $gate = new LicenseGate(db(), app_config());
    $tenant = $gate->enter();
    $license = $gate->check($tenant);
    return [$tenant, $license];
}

function require_login(array $tenant, array $license) {
    $user = current_user($tenant);
    if (!$user) {
        redirect(page_url('login.php'));
    }
    return $user;
}
