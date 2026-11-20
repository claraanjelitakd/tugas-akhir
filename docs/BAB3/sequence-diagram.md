# Sequence Diagram — Tracer Study UKDW

> Diagram interaksi antar objek untuk use case utama yang melibatkan sistem eksternal.
> Menggunakan notasi Mermaid `sequenceDiagram` — dapat dirender langsung di GitHub.

---

## UC-08 — Sinkronisasi LinkedIn via Apify

Proses pengambilan data profil karier alumni dari LinkedIn menggunakan layanan scraping Apify.
Alur dibagi dua fase: **Trigger & Scraping** dan **Review & Merge**.

```mermaid
sequenceDiagram
    actor Admin as Admin / Superadmin
    participant UI as SPA (Vue 3)
    participant App as Web App (Laravel)
    participant DB as Database (MySQL)
    participant Queue as Queue Worker
    participant Apify as Apify API

    Note over Admin,Apify: Fase 1 — Trigger Sinkronisasi

    Admin->>UI: Pilih alumni & klik "Sinkronisasi LinkedIn"
    UI->>App: POST /admin/linkedin-sync/{biodata_id}
    App->>DB: Ambil linkedin_url dari tabel biodata
    DB-->>App: linkedin_url alumni

    App->>Queue: Dispatch job SyncLinkedInJob(biodata_id, linkedin_url)
    App-->>UI: 202 Accepted — job terdaftarkan
    UI-->>Admin: Tampil notifikasi "Proses sinkronisasi dimulai"

    Note over Queue,Apify: Fase 2 — Scraping (Async)

    Queue->>Apify: POST /v2/acts/run (input: linkedin_url)
    Apify-->>Queue: 201 Created — runId

    Queue->>Apify: GET /v2/acts/runs/{runId} (polling status)
    Apify-->>Queue: status: RUNNING

    Queue->>Apify: GET /v2/acts/runs/{runId} (polling selesai)
    Apify-->>Queue: status: SUCCEEDED + dataset hasil

    Queue->>Apify: GET /v2/datasets/{datasetId}/items
    Apify-->>Queue: JSON profil LinkedIn (nama, posisi, perusahaan, skills, dsb.)

    Queue->>DB: INSERT linkedin_sync_results (biodata_id, scraped_data, status='pending')
    Queue-->>Admin: [notifikasi UI] "Hasil scraping siap direview"

    Note over Admin,DB: Fase 3 — Review & Merge

    Admin->>UI: Buka halaman review sinkronisasi
    UI->>App: GET /admin/linkedin-sync/{id}/review
    App->>DB: SELECT linkedin_sync_results WHERE id = ? AND status = 'pending'
    DB-->>App: Data scraping pending
    App-->>UI: Tampilkan perbandingan data lama vs. hasil scraping

    alt Admin menyetujui
        Admin->>UI: Klik "Setujui & Merge"
        UI->>App: PATCH /admin/linkedin-sync/{id}/approve
        App->>DB: UPDATE biodata SET skills, experience, posisi_jabatan, ... (dari scraped_data)
        App->>DB: UPDATE linkedin_sync_results SET status='approved', reviewed_by, reviewed_at
        App-->>UI: 200 OK
        UI-->>Admin: Tampil "Data profil berhasil diperbarui"
    else Admin menolak
        Admin->>UI: Klik "Tolak"
        UI->>App: PATCH /admin/linkedin-sync/{id}/reject
        App->>DB: UPDATE linkedin_sync_results SET status='rejected', reviewed_by, reviewed_at
        App-->>UI: 200 OK
        UI-->>Admin: Tampil "Hasil scraping ditolak"
    end
```

---

## UC-07 — Verifikasi & Rekomendasi Perusahaan

Proses alumni mengusulkan perusahaan baru dan admin memverifikasi kelayakannya sebelum masuk master data.

```mermaid
sequenceDiagram
    actor Alumni as Alumni
    actor Admin as Admin / Superadmin
    participant UI as SPA (Vue 3)
    participant App as Web App (Laravel)
    participant DB as Database (MySQL)

    Note over Alumni,DB: Fase 1 — Alumni Input Perusahaan Baru

    Alumni->>UI: Isi form profil (tab Pekerjaan)
    UI->>App: GET /api/perusahaan/search?q=nama_perusahaan
    App->>DB: SELECT * FROM perusahaan WHERE nama LIKE ?
    DB-->>App: Daftar perusahaan yang sudah terverifikasi
    App-->>UI: Hasil pencarian perusahaan

    alt Perusahaan sudah ada
        Alumni->>UI: Pilih perusahaan dari daftar
        UI->>App: PATCH /alumni/profil (perusahaan_id yang sudah ada)
        App->>DB: UPDATE biodata SET perusahaan_id = ?
        App-->>UI: 200 OK — perusahaan terhubung
    else Perusahaan belum ada
        Alumni->>UI: Klik "Tambah Perusahaan Baru" & isi form
        UI->>App: POST /alumni/perusahaan (nama, alamat, sektor, skala, dsb.)
        App->>DB: INSERT INTO perusahaan (..., status_verifikasi='Menunggu Verifikasi', created_by_prodi_id)
        DB-->>App: perusahaan_id baru
        App->>DB: UPDATE biodata SET perusahaan_id = ? (perusahaan baru)
        App-->>UI: 201 Created
        UI-->>Alumni: Tampil "Perusahaan diusulkan, menunggu verifikasi admin"
    end

    Note over Admin,DB: Fase 2 — Admin Review & Verifikasi

    Admin->>UI: Buka halaman Verifikasi Perusahaan
    UI->>App: GET /admin/perusahaan?status=Menunggu+Verifikasi
    App->>DB: SELECT * FROM perusahaan WHERE status_verifikasi = 'Menunggu Verifikasi'
    DB-->>App: Daftar perusahaan pending
    App-->>UI: Tampilkan tabel perusahaan pending

    Admin->>UI: Klik detail salah satu perusahaan
    UI->>App: GET /admin/perusahaan/{id}
    App->>DB: SELECT perusahaan + alumni yang mengusulkan
    DB-->>App: Detail perusahaan & konteks pengusul
    App-->>UI: Tampilkan detail lengkap

    alt Admin memverifikasi
        Admin->>UI: Klik "Verifikasi"
        UI->>App: PATCH /admin/perusahaan/{id}/verifikasi
        App->>DB: UPDATE perusahaan SET status_verifikasi='Terverifikasi'
        App->>DB: INSERT log_activities (action='VERIFIKASI_PERUSAHAAN', ...)
        App-->>UI: 200 OK
        UI-->>Admin: Tampil "Perusahaan berhasil diverifikasi"
    else Admin menolak
        Admin->>UI: Klik "Tolak" + isi alasan
        UI->>App: PATCH /admin/perusahaan/{id}/tolak
        App->>DB: UPDATE perusahaan SET status_verifikasi='Ditolak'
        App->>DB: INSERT log_activities (action='TOLAK_PERUSAHAAN', ...)
        App-->>UI: 200 OK
        UI-->>Admin: Tampil "Perusahaan ditolak"

        Note over Alumni: Alumni perlu input ulang perusahaan
    end
```
