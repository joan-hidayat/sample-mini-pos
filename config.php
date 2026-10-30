<?php
/**
 * Konfigurasi Mini POS Express.
 *
 * Nilai default di bawah cocok untuk XAMPP lokal. Untuk server lain, buat file
 * config.local.php (sudah di-ignore git) yang me-return array berisi key yang ingin diganti, misalnya:
 *
 *   <?php return ['db' => ['pass' => 'rahasia']];
 *
 * Pengaturan koneksi ke Central SaaS LMD (kode aplikasi, URL API) ada di lmd-client.php.
 */
$config = [
    // Database milik Mini POS sendiri (bukan database Central SaaS LMD).
    // Database & tabel dibuat otomatis saat aplikasi pertama kali dibuka.
    'db' => [
        'host' => '127.0.0.1',
        'name' => 'mini_pos',
        'user' => 'root',
        'pass' => '',
    ],

    // null = otomatis memakai host yang sama: {scheme}://{host}/software-showcase/
    'showcase_url' => null,

    // Hasil validasi lisensi disimpan di sesi selama N detik sebelum dicek ulang ke LMD
    'license_cache_ttl' => 300,
    // Jika LMD tidak bisa dihubungi, aplikasi tetap jalan selama N jam sejak validasi sukses terakhir
    'offline_grace_hours' => 24,

    // Batas percobaan login gagal per akun sebelum dikunci sementara
    'login_max_attempts' => 5,
    'login_lock_minutes' => 5,
    'password_min_length' => 8,
];

if (file_exists(__DIR__ . '/config.local.php')) {
    $config = array_replace_recursive($config, require __DIR__ . '/config.local.php');
}

return $config;
