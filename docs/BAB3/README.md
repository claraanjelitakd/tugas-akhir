# BAB 3 — Analisis dan Perancangan Sistem Tracer Study UKDW

> Semua diagram dalam dokumen ini menggunakan sintaks **Mermaid** dan dapat dirender langsung di GitHub.

## Daftar Isi

| File | Konten |
|------|--------|
| [activity-diagram.md](activity-diagram.md) | Activity Diagram (UC01–UC17) — 17 use case |
| [c4-diagram.md](c4-diagram.md) | C4 Diagram Level 1 (System Context) & Level 2 (Container) |
| [deskripsi-usecase.md](deskripsi-usecase.md) | Deskripsi Use Case — format Sundaramoorthy (2022) |
| [kamus-data.md](kamus-data.md) | Kamus Data / Data Dictionary — seluruh tabel database |
| [sequence-diagram.md](sequence-diagram.md) | Sequence Diagram — UC-07 Verifikasi Perusahaan, UC-08 Sinkronisasi LinkedIn |

## Peta Use Case

| ID | Nama | Aktor |
|----|------|-------|
| UC-01 | Login & Lupa Kata Sandi | Semua pengguna |
| UC-02 | Mengisi Profil Alumni | Alumni |
| UC-03 | Mengisi Kuesioner Universitas | Alumni |
| UC-04 | Mengisi Kuesioner Prodi | Alumni |
| UC-05 | Evaluasi Kinerja Alumni | Pengguna Lulusan (Atasan) |
| UC-06 | Mengelola Data Alumni | Admin |
| UC-07 | Verifikasi & Rekomendasi Perusahaan | Admin |
| UC-08 | Sinkronisasi LinkedIn (Apify) | Admin / Superadmin |
| UC-09 | Mengelola Kuesioner Universitas | Superadmin |
| UC-10 | Mengelola Kuesioner Prodi | Superadmin / Admin Prodi |
| UC-11 | Mengelola Kuesioner Pengguna Lulusan | Superadmin |
| UC-12 | Melihat Laporan dan Analisis | Admin / Biro |
| UC-13 | Ekspor Data Alumni | Admin / Biro |
| UC-14 | Menghubungi Alumni | Admin |
| UC-15 | Kelola Master UMP & Wilayah | Superadmin |
| UC-16 | Manajemen Akun Pengguna | Superadmin |
| UC-17 | Mengelola Master Pengguna Lulusan | Superadmin |
| UC-18 | Log Aktivitas Sistem | Superadmin |

## Stack Teknologi

| Layer | Teknologi |
|-------|-----------|
| Backend | Laravel 11 / PHP 8.3 |
| Frontend | Vue 3 + Inertia.js |
| Database | MySQL |
| Queue | Laravel Queue + Supervisor |
| Scraping | Apify API (LinkedIn) |
| Email | SMTP / TLS |
