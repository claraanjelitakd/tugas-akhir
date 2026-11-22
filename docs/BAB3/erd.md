# Entity Relationship Diagram (ERD) — Tracer Study UKDW

> Notasi: Crow's Foot (Mermaid `erDiagram`).
> **PK** = Primary Key · **AK** = Alternate Key · **FK** = Foreign Key · **UK** = Unique Key
>
> Seluruh instrumen kuesioner (Universitas, Program Studi, dan Evaluasi Pengguna Lulusan/Atasan) terhubung secara terpadu (*Unified Survey Engine*), dengan relasi langsung periode kuesioner ke respon jawaban.

---

```mermaid
erDiagram

    %% ── MODUL 1: PENGGUNA & AUDIT ──────────────────────────────────

    users {
        bigint id PK
        varchar name
        varchar username "AK"
        varchar email
        varchar role
        char kode_prodi FK
        char kode_fakultas FK
        tinyint must_change_password
        varchar password
        varchar remember_token
    }

    log_activities {
        bigint id PK
        bigint user_id FK
        varchar action
        varchar model_type
        varchar model_id
        text description
        json old_values
        json new_values
        varchar ip_address
        text user_agent
    }

    %% ── MODUL 2: MASTER AKADEMIK & WILAYAH ─────────────────────────

    ref_fakultas {
        char kode_fakultas PK
        varchar nama_fakultas
        varchar singkatan
    }

    ref_prodi {
        char kode_prodi PK
        varchar nama_prodi
        varchar jenjang
        char kode_fakultas FK
    }

    data_akademik {
        char nim PK
        varchar nama
        varchar email_pribadi
        char kode_prodi FK
        year angkatan_masuk
        varchar tahun_akademik_masuk
        date tanggal_lahir
        enum jenis_kelamin
        varchar warga_negara
        enum status_mahasiswa
        varchar tahun_akademik_lulus
        year tahun_lulus
        date tanggal_lulus
        decimal ip_kumulatif
        smallint total_sks
        decimal total_angka_kualitas
    }

    yudisium {
        char nim "PK,FK"
        text judul_ta
        text judul_ta_inggris
        varchar url_publikasi
        varchar jenis_publikasi
        varchar status_publikasi
        varchar keterangan_hasil_yudisium
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
        varchar kode_iso3 "AK"
        varchar nama_negara
        varchar nama_resmi
        varchar benua
        varchar ibu_kota
    }

    ump {
        bigint id PK
        varchar kode_provinsi "AK,FK"
        year tahun "AK"
        decimal besaran
        varchar catatan
    }

    %% ── MODUL 3: PROFIL ALUMNI ──────────────────────────────────────

    perusahaan {
        bigint id PK
        varchar nama_perusahaan
        varchar kode_provinsi FK
        varchar kode_kabupaten FK
        text alamat
        varchar no_telp_fax
        varchar kode_pos
        varchar homepage
        varchar sektor
        enum skala
        varchar bentuk_perusahaan
        varchar jumlah_pegawai
        enum status_verifikasi
        enum jenis_lokasi
        varchar kode_negara FK
        varchar jenis_perusahaan
        varchar jenis_perusahaan_lainnya
        bigint created_by_user_id FK
        char created_by_kode_prodi FK
    }

    atasan {
        bigint id PK
        varchar nama
        varchar email
        varchar telepon
        bigint perusahaan_id FK
        bigint user_id FK
    }

    biodata {
        bigint id PK
        char nim "AK,FK"
        bigint user_id FK
        char kode_prodi FK
        bigint perusahaan_id FK
        bigint atasan_id FK
        varchar kode_provinsi FK
        varchar kode_kabupaten FK
        varchar foto
        varchar nomor_telepon
        varchar email_pribadi
        text alamat_saat_ini
        varchar kelurahan
        varchar kecamatan
        varchar kode_pos
        varchar instagram_url
        varchar facebook_url
        varchar linkedin_url
        varchar linkedin_username
        text skills
        text experience
        varchar kategori_pekerjaan
        varchar posisi_jabatan
        varchar bidang_pekerjaan
        varchar divisi
        varchar posisi_wiraswasta
        decimal gaji
        varchar jenis_pekerjaan
        varchar pendidikan_tingkat
        varchar perguruan_tinggi
        varchar pendidikan_prodi
    }

    linkedin_sync_results {
        bigint id PK
        bigint biodata_id FK
        varchar linkedin_url
        varchar linkedin_username
        json scraped_data
        enum status
        bigint user_id FK "→ users.id"
        timestamp reviewed_at
        timestamp scraped_at
    }

    %% ── MODUL 4: KUESIONER ──────────────────────────────────────────

    kuesioner {
        bigint id PK
        varchar title
        text description
        year year
        tinyint is_active
        decimal target_response_rate
    }

    kuesioner_section {
        bigint id PK
        bigint kuesioner_id FK
        varchar kode_section "AK"
        varchar target
        char kode_prodi FK
        varchar judul
        text deskripsi
        smallint order
        tinyint is_active
    }

    kuesioner_pertanyaan {
        bigint id PK
        varchar kode_pertanyaan "AK"
        varchar label_pertanyaan
        text teks_pertanyaan
        varchar tipe
        char pemilik_kode_prodi FK
        tinyint is_reusable
        varchar keterangan
        tinyint is_active
    }

    kuesioner_section_pertanyaan {
        bigint id PK
        bigint section_id "AK,FK"
        bigint pertanyaan_id "AK,FK"
        smallint order
        tinyint wajib
        tinyint is_active
        varchar tampil_di
        tinyint profil_step
    }

    kuesioner_opsi {
        bigint id PK
        bigint pertanyaan_id FK
        varchar kode_opsi "AK bersama pertanyaan_id"
        text label
        varchar label_singkat
        smallint nilai_skor
        varchar lompat_ke_pertanyaan_id
        smallint order
    }

    kuesioner_jawaban {
        bigint id PK
        bigint kuesioner_id FK
        varchar responden_type
        bigint biodata_id FK
        bigint evaluasi_atasan_id FK
        bigint section_id FK
        bigint pertanyaan_id FK
        varchar kode_pertanyaan
        bigint opsi_id FK
        text kode_opsi
        text jawaban_teks
        json jawaban_json
    }

    %% ── MODUL 5: EVALUASI ATASAN ────────────────────────────────────

    evaluasi_atasan {
        bigint id PK
        bigint biodata_id FK
        bigint atasan_id FK
        bigint perusahaan_id FK
        varchar token UK
        tinyint is_submitted
        timestamp submitted_at
    }

    %% ── MODUL TAMBAHAN ──────────────────────────────────────────────

    account_sync_settings {
        bigint id PK
        boolean is_open
        bigint user_id FK "→ users.id"
    }

    %% ── RELASI ──────────────────────────────────────────────────────

    ref_fakultas ||--|{ ref_prodi : "memiliki"
    ref_fakultas ||--o{ users : "admin_fakultas"
    ref_prodi ||--o{ users : "admin_prodi"
    ref_prodi ||--o{ data_akademik : "studi di"
    ref_prodi ||--o{ perusahaan : "mengusulkan"
    ref_prodi ||--o{ kuesioner_section : "memiliki section"
    ref_prodi ||--o{ kuesioner_pertanyaan : "memiliki pertanyaan"
    ref_prodi ||--o{ biodata : "program studi"

    data_akademik ||--o| yudisium : "kelulusan (nim)"
    data_akademik ||--o| biodata : "profil (nim)"

    kuesioner ||--o{ kuesioner_jawaban : "periode kuesioner"
    users ||--o| biodata : "akun"
    users ||--o{ log_activities : "mencatat"
    users ||--o{ linkedin_sync_results : "mereview"
    users ||--o| account_sync_settings : "memperbarui"
    users ||--o{ atasan : "akun atasan"
    users ||--o{ perusahaan : "mengusulkan"

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
    kuesioner ||--o{ kuesioner_section : "memiliki section"
    kuesioner ||--o{ kuesioner_jawaban : "periode instrumen"
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
