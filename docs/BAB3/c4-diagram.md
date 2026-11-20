# C4 Diagram — Tracer Study UKDW

> Referensi: [C4 Model](https://c4model.com) — Simon Brown

---

## Level 1 — System Context

Menunjukkan posisi Sistem Tracer Study UKDW di antara penggunanya dan sistem eksternal.

```mermaid
flowchart TB
    alumni["**Alumni**\n[Person]\nLulusan UKDW yang mengisi data profil karier,\nmenjawab kuesioner tracer study, dan\nmemperbarui informasi pekerjaan secara mandiri."]
    atasan["**Pengguna Lulusan**\n[Person]\nAtasan atau pemberi kerja alumni yang\nmenerima undangan melalui email dan mengisi\nformulir evaluasi kinerja alumni."]
    admin["**Admin / Superadmin**\n[Person]\nStaf prodi dan ITC UKDW yang mengelola data\nalumni, kuesioner, perusahaan, laporan, serta\nkonfigurasi dan akun pengguna sistem."]

    tracer["**Tracer Study UKDW**\n[Software System]\nAplikasi web untuk pelacakan karier alumni,\npengisian kuesioner, sinkronisasi LinkedIn,\ndan analisis data lulusan berbasis prodi."]

    apify["**Apify API**\n[External System]\nLayanan scraping pihak ketiga yang digunakan\nuntuk mengambil data profil LinkedIn alumni\nsecara otomatis melalui REST API."]
    smtp["**Email Service — SMTP**\n[External System]\nServer email eksternal untuk mengirimkan\nnotifikasi dan undangan evaluasi kepada\nalumni maupun pengguna lulusan."]

    alumni  -->|"Mengisi profil & kuesioner [HTTPS]"| tracer
    atasan  -->|"Mengisi evaluasi kinerja alumni [HTTPS]"| tracer
    admin   -->|"Mengelola data, laporan, akun [HTTPS]"| tracer

    tracer  -->|"Request scraping [REST API]"| apify
    apify   -->|"Hasil profil LinkedIn [Webhook]"| tracer
    tracer  -->|"Kirim email notifikasi [SMTP/TLS]"| smtp
```

---

## Level 2 — Container

Menunjukkan container teknis di dalam sistem Tracer Study UKDW.

```mermaid
flowchart TB
    alumni["**Alumni**\n[Person]"]
    atasan["**Pengguna Lulusan**\n[Person]"]
    admin["**Admin / Superadmin**\n[Person]"]

    subgraph boundary["Tracer Study UKDW — System Boundary"]
        spa["**Single Page App**\nVue 3 + Inertia.js\nAntarmuka pengguna di browser"]
        webapp["**Web Application**\nLaravel 11 / PHP 8.3\nRouting, autentikasi, logika bisnis"]
        db[("**Database**\nMySQL\nAlumni, kuesioner, perusahaan,\njawaban, wilayah, UMP, log")]
        queue["**Queue Worker**\nLaravel Queue + Supervisor\nJob async: email massal, sinkronisasi Apify"]
        storage["**File Storage**\nLaravel Storage\nFile ekspor Excel & foto profil"]
    end

    apify["**Apify API**\n[External System]"]
    smtp["**Email Service — SMTP**\n[External System]"]

    alumni -->|"HTTPS"| spa
    atasan -->|"HTTPS"| spa
    admin  -->|"HTTPS"| spa

    spa    -->|"HTTP/JSON — Inertia Protocol"| webapp
    webapp -->|"Inertia response"| spa
    webapp -->|"SQL / Eloquent ORM"| db
    webapp -->|"Dispatch job async"| queue
    webapp -->|"Simpan & baca file"| storage

    queue  -->|"SMTP/TLS"| smtp
    queue  -->|"REST API"| apify
    apify  -->|"Webhook / REST API"| webapp
```
