# CLAUDE.md

Repo ini bagian dari ekosistem Central SaaS LMD (`joan-hidayat/lmd`, `software-showcase`, `sample-mini-pos`).
Proyek masih tahap perancangan & pengembangan: password, credential, dan URL lokal sengaja hardcode.

## Aturan dokumentasi (wajib)
Setiap perubahan kode dan setiap analisis/temuan di repo ini dicatat di repo **lmd**:
- `docs/LOG_EVALUASI.md` — entri baru di paling atas **Riwayat** + perbarui tabel **Daftar Temuan**.
- `docs/EKOSISTEM_CARA_KERJA.md` — perbarui jika alur, endpoint, atau status implementasi berubah.

Jika repo lmd tidak ada di sesi, tambahkan dulu (add_repo `joan-hidayat/lmd`) sebelum menutup pekerjaan.

## Standar integrasi (wajib)
Integrasi LMD ↔ Showcase ↔ aplikasi client mengikuti `docs/STANDAR_INTEGRASI.md` di repo **lmd** (v1): satu `app_code` yang sama di registry LMD, `lmdAppCode` produk Showcase, dan `lmd-client.php` aplikasi client. Aplikasi baru wajib melewati checklist di bagian 6 dokumen itu.
