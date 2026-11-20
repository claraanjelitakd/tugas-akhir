# C4 Diagram — Tracer Study UKDW

> Referensi: [C4 Model](https://c4model.com) — Simon Brown

---

## Level 1 — System Context

Menunjukkan posisi Sistem Tracer Study UKDW di antara penggunanya dan sistem eksternal.

```mermaid
flowchart TB
    alumni["👤 **Alumni**\nLulusan UKDW"]
    atasan["👤 **Pengguna Lulusan**\nAtasan / pemberi kerja"]
    admin["👤 **Admin / Superadmin**\nStaf & ITC UKDW"]

    tracer["🖥️ **Tracer Study UKDW**\n*(System)*\nAplikasi web pelacakan karier alumni,\npengisian kuesioner, dan analisis data lulusan"]

    apify["⚙️ **Apify API**\n*(External System)*\nScraping data LinkedIn"]
    smtp["⚙️ **Email Service — SMTP**\n*(External System)*\nPengiriman email notifikasi"]

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
    alumni["👤 Alumni"]
    atasan["👤 Pengguna Lulusan"]
    admin["👤 Admin / Superadmin"]

    subgraph boundary["Tracer Study UKDW — System Boundary"]
        spa["**Single Page App**\nVue 3 + Inertia.js\nAntarmuka pengguna di browser"]
        webapp["**Web Application**\nLaravel 11 / PHP 8.3\nRouting, autentikasi, logika bisnis"]
        db[("**Database**\nMySQL\nAlumni, kuesioner, perusahaan,\njawaban, wilayah, UMP, log")]
        queue["**Queue Worker**\nLaravel Queue + Supervisor\nJob async: email massal, sinkronisasi Apify"]
        storage["**File Storage**\nLaravel Storage\nFile ekspor Excel & foto profil"]
    end

    apify["⚙️ **Apify API**\nScraping LinkedIn"]
    smtp["⚙️ **Email Service — SMTP**\nPengiriman email"]

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
