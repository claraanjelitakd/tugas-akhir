# C4 Diagram — Tracer Study UKDW

> Referensi: [C4 Model](https://c4model.com) — Simon Brown

---

## Level 1 — System Context

Menunjukkan posisi Sistem Tracer Study UKDW di antara penggunanya dan sistem eksternal.

```mermaid
C4Context
  title System Context - Tracer Study UKDW

  Person(alumni, "Alumni", "Lulusan UKDW yang mengisi profil dan kuesioner tracer study")
  Person(atasan, "Pengguna Lulusan (Atasan)", "Pemberi kerja yang mengisi kuesioner evaluasi kinerja alumni")
  Person(admin, "Admin Prodi / Fakultas / Biro", "Staf yang mengelola data alumni dan melihat laporan")
  Person(superadmin, "Superadmin (ITC)", "Mengelola akun, kuesioner, master data, dan sinkronisasi")

  System(tracerStudy, "Tracer Study UKDW", "Aplikasi web untuk pelacakan karier alumni, pengisian kuesioner, dan analisis data lulusan UKDW")

  System_Ext(apify, "Apify API", "Platform scraping LinkedIn untuk data karier alumni")
  System_Ext(smtp, "Email Service (SMTP)", "Layanan pengiriman email notifikasi dan undangan kuesioner")

  Rel(alumni, tracerStudy, "Mengisi profil dan kuesioner", "HTTPS")
  Rel(atasan, tracerStudy, "Mengisi evaluasi kinerja alumni", "HTTPS")
  Rel(admin, tracerStudy, "Mengelola data alumni dan laporan", "HTTPS")
  Rel(superadmin, tracerStudy, "Mengelola akun, kuesioner, dan master data", "HTTPS")

  Rel(tracerStudy, apify, "Meminta sinkronisasi data LinkedIn", "REST API")
  Rel(apify, tracerStudy, "Mengembalikan data profil LinkedIn", "Webhook")
  Rel(tracerStudy, smtp, "Mengirim email notifikasi dan undangan", "SMTP/TLS")
```

---

## Level 2 — Container

Menunjukkan container teknis di dalam sistem Tracer Study UKDW.

```mermaid
C4Container
  title Container Diagram - Tracer Study UKDW

  Person(alumni, "Alumni", "Lulusan UKDW")
  Person(atasan, "Pengguna Lulusan", "Atasan / pemberi kerja")
  Person(admin, "Admin / Superadmin", "Staf dan ITC UKDW")

  System_Boundary(tracerStudy, "Tracer Study UKDW") {
    Container(webApp, "Web Application", "Laravel 11 / PHP 8.3", "Routing, autentikasi, logika bisnis, Inertia response")
    Container(spa, "Single Page App", "Vue 3 + Inertia.js", "Antarmuka pengguna berbasis komponen Vue di browser")
    ContainerDb(db, "Database", "MySQL", "Alumni, kuesioner, perusahaan, jawaban, wilayah, UMP, log")
    Container(queue, "Queue Worker", "Laravel Queue + Supervisor", "Job async: email massal, sinkronisasi Apify")
    Container(storage, "File Storage", "Laravel Storage", "File ekspor Excel dan foto profil alumni")
  }

  System_Ext(apify, "Apify API", "Scraping LinkedIn")
  System_Ext(smtp, "Email Service (SMTP)", "Pengiriman email")

  Rel(alumni, spa, "Akses via browser", "HTTPS")
  Rel(atasan, spa, "Akses via browser", "HTTPS")
  Rel(admin, spa, "Akses via browser", "HTTPS")

  Rel(spa, webApp, "Request & form submission", "HTTP/JSON — Inertia Protocol")
  Rel(webApp, db, "Read/Write data", "SQL / Eloquent ORM")
  Rel(webApp, queue, "Dispatch job async", "Laravel Queue")
  Rel(webApp, storage, "Simpan & baca file", "Laravel Filesystem")
  Rel(queue, smtp, "Kirim email massal", "SMTP/TLS")
  Rel(queue, apify, "Minta sinkronisasi LinkedIn", "REST API")
  Rel(apify, webApp, "Kirim hasil scraping", "Webhook / REST API")
  Rel(webApp, spa, "Inertia response", "HTTP/JSON")
```
