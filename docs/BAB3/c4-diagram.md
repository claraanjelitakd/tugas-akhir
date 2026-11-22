# C4 Diagram — Tracer Study UKDW

> Referensi: [C4 Model](https://c4model.com) — Simon Brown

---

## Level 1 — System Context

Menunjukkan posisi Sistem Tracer Study UKDW di antara penggunanya dan sistem eksternal.

```mermaid
C4Context
    title System Context — Tracer Study UKDW

    Person(alumni, "Alumni", "Lulusan UKDW yang mengisi data profil karier, menjawab kuesioner tracer study, dan memperbarui informasi pekerjaan secara mandiri.")
    Person(atasan, "Pengguna Lulusan", "Atasan atau pemberi kerja alumni yang menerima undangan melalui email dan mengisi formulir evaluasi kinerja alumni.")
    Person(adminProdi, "Admin Prodi", "Staf program studi yang mengelola data alumni per prodi, memverifikasi perusahaan, mengelola kuesioner prodi, dan melihat laporan.")
    Person(adminFakultas, "Admin Fakultas", "Staf fakultas yang memantau data alumni dan laporan di seluruh program studi dalam satu fakultas.")
    Person(adminBiro, "Admin Biro", "Staf biro (BAKA/ITC) yang mengakses laporan dan ekspor data alumni lintas prodi untuk keperluan pelaporan institusional.")
    Person(superadmin, "Superadmin", "Administrator sistem dari ITC UKDW yang mengelola akun pengguna, kuesioner universitas, master data wilayah & UMP, serta konfigurasi sistem.")

    System(tracer, "Tracer Study UKDW", "Aplikasi web untuk pelacakan karier alumni, pengisian kuesioner, sinkronisasi LinkedIn, dan analisis data lulusan berbasis prodi.")

    System_Ext(apify, "Apify API", "Layanan scraping pihak ketiga untuk mengambil data profil LinkedIn alumni secara otomatis melalui REST API.")
    System_Ext(smtp, "Email Service - SMTP", "Server email eksternal untuk mengirimkan notifikasi dan undangan evaluasi kepada alumni maupun pengguna lulusan.")

    Rel(alumni, tracer, "Mengisi kuesioner tracer, perbarui profil & ajukan perusahaan/atasan", "HTTPS")
    Rel(atasan, tracer, "Mengisi evaluasi kinerja alumni via tautan token publik", "HTTPS")
    Rel(adminProdi, tracer, "Memantau alumni prodi, kelola kuesioner prodi & ekspor data", "HTTPS")
    Rel(adminFakultas, tracer, "Monitoring analitik fakultas & direktori lintas prodi", "HTTPS")
    Rel(adminBiro, tracer, "Monitoring IKU kampus, blast email massal & ekspor laporan", "HTTPS")
    Rel(superadmin, tracer, "Kelola instrumen kuesioner, akun pengguna, master data & konfigurasi", "HTTPS")
    Rel(tracer, apify, "Request scraping profil LinkedIn", "REST API")
    Rel(apify, tracer, "Mengembalikan data JSON profil LinkedIn", "REST API / JSON")
    Rel(tracer, smtp, "Kirim antrean email notifikasi & undangan", "SMTP/TLS")
    Rel(smtp, atasan, "Mengirim email undangan evaluasi ber-token", "SMTP")
    Rel(smtp, alumni, "Mengirim email blast tracer & reset password", "SMTP")
```

---

## Level 2 — Container

Menunjukkan container teknis di dalam sistem Tracer Study UKDW.

```mermaid
C4Container
    title Container Diagram — Tracer Study UKDW

    Person(alumni, "Alumni", "Lulusan UKDW yang mengisi profil karier dan kuesioner tracer study.")
    Person(atasan, "Pengguna Lulusan", "Atasan langsung / pemberi kerja alumni.")
    Person(adminProdi, "Admin Prodi", "Staf program studi yang memantau alumni dan kuesioner prodi.")
    Person(adminFakultas, "Admin Fakultas", "Staf fakultas yang memantau data alumni se-fakultas.")
    Person(adminBiro, "Admin Biro", "Staf biro BAKA / ITC yang memantau IKU dan komunikasi email alumni.")
    Person(superadmin, "Superadmin", "Administrator sistem dari ITC UKDW.")

    System_Ext(apify, "Apify API", "Layanan eksternal scraping profil LinkedIn.")
    System_Ext(smtp, "Email Service - SMTP", "Layanan server pengiriman email notifikasi.")

    Container_Boundary(tracer, "Tracer Study UKDW") {
        Container(spa, "Single Page App", "Vue 3, Inertia.js v3", "Antarmuka pengguna di browser. Merender halaman dan mengirim request interaksi pengguna.")
        Container(webapp, "Web Application", "Laravel 13, PHP 8.3", "Menangani routing, autentikasi, otorisasi role, logika bisnis, dan penyedia data Inertia.")
        ContainerDb(db, "Database", "MySQL 8.4 LTS", "Menyimpan data pengguna, akademik, biodata, kuesioner, evaluasi, wilayah, UMP, dan audit log.")
        Container(queue, "Queue Worker", "Laravel Queue, Supervisor", "Memproses pekerjaan asinkron: blast email massal dan sinkronisasi data LinkedIn dari Apify.")
        Container(storage, "File Storage", "Laravel Storage", "Menyimpan berkas ekspor Excel/ZIP dan foto profil alumni.")
    }

    Rel(alumni, spa, "Mengisi kuesioner tracer, kelola profil & atasan", "HTTPS")
    Rel(atasan, spa, "Mengisi evaluasi via tautan token publik", "HTTPS")
    Rel(adminProdi, spa, "Pantau lulusan, kuesioner prodi & ekspor", "HTTPS")
    Rel(adminFakultas, spa, "Monitoring analitik fakultas & direktori", "HTTPS")
    Rel(adminBiro, spa, "Monitoring IKU, blast email & ekspor data", "HTTPS")
    Rel(superadmin, spa, "Kelola instrumen kuesioner, akun & sinkronisasi", "HTTPS")

    BiRel(spa, webapp, "Request interaksi pengguna & respons data Inertia", "HTTPS / JSON")
    Rel(webapp, db, "Query SQL & transaksi tulis data", "SQL")
    Rel(db, webapp, "Result set data & model Eloquent", "Eloquent ORM")
    Rel(webapp, queue, "Mendispatch job async", "Laravel Queue")
    Rel(webapp, storage, "Simpan berkas ekspor & foto profil", "Laravel Storage API")
    Rel(storage, webapp, "File stream untuk unduhan laporan", "Binary Stream")
    BiRel(queue, db, "Mengambil antrean & simpan hasil staging/log", "SQL")

    Rel(queue, smtp, "Mengirim antrean email notifikasi", "SMTP / TLS")
    Rel(queue, apify, "Kirim URL profil LinkedIn alumni", "REST API / HTTPS")
    Rel(apify, queue, "Mengembalikan data JSON profil alumni", "JSON / HTTPS")

    Rel(smtp, atasan, "Mengirim undangan evaluasi ber-token", "SMTP")
    Rel(smtp, alumni, "Mengirim blast reminder & reset password", "SMTP")
```
