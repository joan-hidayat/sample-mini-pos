===============================================================
MINI POS EXPRESS v1.1.0 — PORTABLE CASHIER CLIENT
===============================================================

Aplikasi kasir web dengan login multi-user dan verifikasi lisensi
terintegrasi Central SaaS LMD.

PERSIAPAN (XAMPP)
1. Letakkan folder ini di htdocs/mini-pos (sejajar dengan
   License-Management-Dashboard dan software-showcase).
2. Database "mini_pos" dan tabelnya dibuat otomatis saat aplikasi
   pertama kali dibuka (user MySQL default: root tanpa password).
   Untuk kredensial lain, buat config.local.php — lihat config.php.

AKTIVASI PERTAMA (PEMILIK TOKO)
1. Berlangganan lewat Software Showcase, tunggu pembayaran di-approve.
2. Klik "Buka Aplikasi" — link berisi ?tenant=...&lic=...
3. Buat akun admin memakai email yang dipakai saat berlangganan.
4. Tambah akun kasir di menu "Kelola User" (dibatasi kuota paket).

MEMAKAI DI PERANGKAT LAIN (KASIR)
1. Hubungkan HP / laptop ke Wi-Fi yang sama dengan PC server.
2. Buka browser ke alamat toko, contoh:
   http://192.168.0.116:8080/mini-pos/?tenant=namatoko.pos.local
3. Login dengan akun yang dibuat admin. License key tidak diperlukan.
===============================================================
