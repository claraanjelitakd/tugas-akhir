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

    Rel(alumni, tracer, "Mengisi profil & kuesioner", "HTTPS")
    Rel(atasan, tracer, "Mengisi evaluasi kinerja alumni", "HTTPS")
    Rel(adminProdi, tracer, "Mengelola alumni, kuesioner prodi, laporan", "HTTPS")
    Rel(adminFakultas, tracer, "Memantau laporan lintas prodi dalam fakultas", "HTTPS")
    Rel(adminBiro, tracer, "Mengakses laporan & ekspor data", "HTTPS")
    Rel(superadmin, tracer, "Mengelola akun, kuesioner, master data, konfigurasi", "HTTPS")
    Rel(tracer, apify, "Request scraping", "REST API")
    Rel(apify, tracer, "Hasil profil LinkedIn", "Webhook")
    Rel(tracer, smtp, "Kirim email notifikasi", "SMTP/TLS")
```

---

## Level 2 — Container

Menunjukkan container teknis di dalam sistem Tracer Study UKDW.

```mermaid
C4Container
    title Container Diagram — Tracer Study UKDW

    Person(alumni, "Alumni", "Lulusan UKDW.")
    Person(atasan, "Pengguna Lulusan", "Atasan / pemberi kerja alumni.")
    Person(adminProdi, "Admin Prodi", "Staf program studi.")
    Person(adminFakultas, "Admin Fakultas", "Staf fakultas.")
    Person(adminBiro, "Admin Biro", "Staf biro institusional.")
    Person(superadmin, "Superadmin", "Administrator sistem ITC UKDW.")

    System_Ext(apify, "Apify API", "Scraping profil LinkedIn.")
    System_Ext(smtp, "Email Service - SMTP", "Pengiriman email notifikasi.")

    Container_Boundary(tracer, "Tracer Study UKDW") {
        Container(spa, "Single Page App", "Vue 3, Inertia.js", "Antarmuka pengguna yang berjalan di browser. Merender halaman dan mengirim request ke Web Application melalui Inertia Protocol.")
        Container(webapp, "Web Application", "Laravel 11, PHP 8.3", "Menangani routing, autentikasi, logika bisnis, dan menghasilkan respons Inertia untuk SPA.")
        ContainerDb(db, "Database", "MySQL", "Menyimpan data alumni, kuesioner, perusahaan, jawaban, wilayah, UMP, dan log aktivitas.")
        Container(queue, "Queue Worker", "Laravel Queue, Supervisor", "Memproses job asinkron: pengiriman email massal dan sinkronisasi data LinkedIn dari Apify.")
        Container(storage, "File Storage", "Laravel Storage", "Menyimpan file ekspor Excel dan foto profil alumni.")
    }

    Rel(alumni, spa, "Mengisi profil & kuesioner", "HTTPS")
    Rel(atasan, spa, "Mengisi evaluasi kinerja", "HTTPS")
    Rel(adminProdi, spa, "Mengelola alumni & kuesioner prodi", "HTTPS")
    Rel(adminFakultas, spa, "Memantau laporan fakultas", "HTTPS")
    Rel(adminBiro, spa, "Mengakses laporan & ekspor", "HTTPS")
    Rel(superadmin, spa, "Mengelola akun, master data, konfigurasi", "HTTPS")

    Rel(spa, webapp, "Mengirim request", "Inertia Protocol / JSON")
    Rel(webapp, spa, "Mengembalikan respons", "Inertia Response")
    Rel(webapp, db, "Membaca & menulis data", "Eloquent ORM / SQL")
    Rel(webapp, queue, "Mendispatch job async", "Laravel Queue")
    Rel(webapp, storage, "Menyimpan & membaca file", "Laravel Storage API")

    Rel(queue, smtp, "Mengirim email", "SMTP/TLS")
    Rel(queue, apify, "Request scraping LinkedIn", "REST API")
    Rel(apify, webapp, "Mengembalikan data profil", "Webhook / REST API")
```
