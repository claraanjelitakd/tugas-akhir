# Entity Relationship Diagram (ERD) — Tracer Study UKDW

> Notasi: Crow's Foot (Mermaid `erDiagram`).
> **PK** = Primary Key · **AK** = Alternate Key · **FK** = Foreign Key · **UK** = Unique Key

---

```mermaid
erDiagram

    %% ── MODUL 1: PENGGUNA & AUDIT ──────────────────────────────────

    users {
        bigint id PK
        varchar username "AK"
        varchar email
        varchar role
        bigint prodi_id FK
        bigint fakultas_id FK
        varchar password
    }

    log_activities {
        bigint id PK
        bigint user_id FK
        varchar action
        varchar model_type
        varchar model_id
        text description
    }

    %% ── MODUL 2: MASTER AKADEMIK & WILAYAH ─────────────────────────

    ref_fakultas {
        bigint id PK
        varchar kode_fakultas "AK"
        varchar nama_fakultas
        varchar singkatan
    }

    prodi {
        bigint id PK
        varchar kode_prodi "AK"
        varchar nama_prodi
        varchar jenjang
        bigint fakultas_id FK
    }

    data_akademik {
        bigint id PK
        char nim "AK"
        varchar nama
        bigint prodi_id FK
        year angkatan_masuk
        enum status_mahasiswa
        year tahun_lulus
        decimal ip_kumulatif
    }

    yudisium {
        bigint id PK
        char nim "AK,FK"
        text judul_ta
        enum status_lulus
    }

    propinsi {
        varchar kode_provinsi PK
        varchar nama_provinsi
    }

    kabupaten {
        varchar kode_kabupaten PK
        varchar nama_kabupaten
        varchar kode_provinsi FK
    }

    ref_negara {
        varchar kode_negara PK
        varchar nama_negara "AK"
        varchar kode_iso3
        varchar benua
    }

    ump {
        bigint id PK
        varchar kode_provinsi "AK,FK"
        year tahun "AK"
        decimal besaran
    }

    %% ── MODUL 3: PROFIL ALUMNI ──────────────────────────────────────

    perusahaan {
        bigint id PK
        varchar nama_perusahaan
        varchar sektor
        enum skala
        enum status_verifikasi
        bigint created_by_prodi_id FK
        varchar kode_provinsi FK
        varchar kode_kabupaten FK
        varchar kode_negara FK
    }

    atasan {
        bigint id PK
        varchar nama
        varchar email
        bigint perusahaan_id FK
    }

    biodata {
        bigint id PK
        char nim "AK,FK"
        bigint user_id FK
        bigint yudisium_id FK
        bigint prodi_id FK
        bigint perusahaan_id FK
        bigint atasan_id FK
        varchar kode_provinsi FK
        varchar kode_kabupaten FK
        varchar kategori_pekerjaan
        decimal gaji
    }

    linkedin_sync_results {
        bigint id PK
        bigint biodata_id FK
        varchar linkedin_url
        json scraped_data
        enum status
        bigint reviewed_by FK
    }

    %% ── MODUL 4: KUESIONER ──────────────────────────────────────────

    kuesioner {
        bigint id PK
        varchar title
        year year
        tinyint is_active
    }

    kuesioner_section {
        bigint id PK
        varchar kode_section "AK"
        varchar target
        bigint prodi_id FK
        varchar judul
        smallint order
    }

    kuesioner_pertanyaan {
        bigint id PK
        varchar kode_pertanyaan "AK"
        text teks_pertanyaan
        varchar tipe
        bigint pemilik_prodi_id FK
        tinyint is_reusable
    }

    kuesioner_section_pertanyaan {
        bigint id PK
        bigint section_id "AK,FK"
        bigint pertanyaan_id "AK,FK"
        smallint order
        tinyint wajib
    }

    kuesioner_opsi {
        bigint id PK
        bigint pertanyaan_id FK
        varchar kode_opsi "AK bersama pertanyaan_id"
        text label
        smallint nilai_skor
        smallint order
    }

    kuesioner_jawaban {
        bigint id PK
        varchar responden_type
        bigint biodata_id FK
        bigint evaluasi_atasan_id FK
        bigint section_id FK
        bigint pertanyaan_id FK
        bigint opsi_id FK
        text jawaban_teks
    }

    %% ── MODUL 5: EVALUASI ATASAN ────────────────────────────────────

    evaluasi_atasan {
        bigint id PK
        bigint biodata_id FK
        bigint atasan_id FK
        bigint perusahaan_id FK
        varchar token UK
        tinyint is_submitted
    }

    %% ── MODUL TAMBAHAN ──────────────────────────────────────────────

    account_sync_settings {
        bigint id PK
        boolean is_open
        bigint updated_by FK
    }

    %% ── RELASI ──────────────────────────────────────────────────────

    ref_fakultas ||--|{ prodi : "memiliki"
    ref_fakultas ||--o{ users : "admin_fakultas"
    prodi ||--o{ users : "admin_prodi"
    prodi ||--o{ data_akademik : "studi di"
    prodi ||--o{ perusahaan : "mengusulkan"
    prodi ||--o{ kuesioner_section : "memiliki section"
    prodi ||--o{ kuesioner_pertanyaan : "memiliki pertanyaan"

    data_akademik ||--o| yudisium : "kelulusan"
    data_akademik ||--o| biodata : "profil"

    yudisium ||--o| biodata : "referensi"
    users ||--o| biodata : "akun"
    users ||--o{ log_activities : "mencatat"
    users ||--o{ linkedin_sync_results : "mereview"
    users ||--o| account_sync_settings : "mengatur"

    propinsi ||--|{ kabupaten : "membawahi"
    propinsi ||--o{ perusahaan : "lokasi"
    propinsi ||--o{ biodata : "domisili"
    propinsi ||--o{ ump : "standar UMP"
    kabupaten ||--o{ perusahaan : "lokasi"
    kabupaten ||--o{ biodata : "domisili"
    ref_negara ||--o{ perusahaan : "negara"

    perusahaan ||--o{ atasan : "memiliki"
    perusahaan ||--o{ biodata : "tempat kerja"
    perusahaan ||--o{ evaluasi_atasan : "konteks"

    atasan ||--o{ evaluasi_atasan : "menilai"
    biodata ||--o{ linkedin_sync_results : "sinkronisasi"
    biodata ||--o{ kuesioner_jawaban : "menjawab"
    biodata ||--o{ evaluasi_atasan : "dinilai"

    kuesioner_section ||--o{ kuesioner_section_pertanyaan : "memuat"
    kuesioner_pertanyaan ||--o{ kuesioner_section_pertanyaan : "ditempatkan"
    kuesioner_pertanyaan ||--o{ kuesioner_opsi : "memiliki opsi"
    kuesioner_pertanyaan ||--o{ kuesioner_jawaban : "dijawab"
    kuesioner_section ||--o{ kuesioner_jawaban : "bagian dari"
    kuesioner_opsi ||--o{ kuesioner_jawaban : "dipilih"
    evaluasi_atasan ||--o{ kuesioner_jawaban : "menjawab"
```
