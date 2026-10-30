<?php
/**
 * Identitas aplikasi untuk Central SaaS LMD — Standar Integrasi v1.
 * Spesifikasi lengkap: docs/STANDAR_INTEGRASI.md di repo LMD.
 *
 * Override per server (tidak di-commit): buat lmd-client.local.php yang me-return array key yang ingin diganti.
 */
$client = [
    // Harus sama dengan kode aplikasi di LMD (menu Applications & Modules).
    // License key dengan app_code lain ditolak, walaupun lisensinya aktif.
    'app_code' => 'MINIPOS',

    // null = otomatis memakai host yang sama: {scheme}://{host}/License-Management-Dashboard/api/index.php
    'lmd_api_url' => null,

    // instance_key = prefix + 8 karakter terakhir license key. Harus sama dengan yang dibuat LMD saat order.
    'instance_key_prefix' => 'INST-STORE-',

    // Instance secret untuk tanda tangan request (Standar v1.1 bagian 4.3) TIDAK disimpan di sini:
    // secret berbeda per toko dan disimpan di tabel tenants (database mini_pos).
];

if (file_exists(__DIR__ . '/lmd-client.local.php')) {
    $client = array_replace($client, require __DIR__ . '/lmd-client.local.php');
}

return $client;
