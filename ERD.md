# Entity Relationship Diagram (ERD) - Tracer Study UKDW (SERU)

Dokumentasi lengkap skema basis data, kamus data (*data dictionary*), relasi entitas, dan diagram hubungan (*Entity Relationship Diagram*) untuk sistem informasi **SERU (Sistem Ekosistem Rekam Jejak Alumni) - Universitas Kristen Duta Wacana**.

> Revisi 22 Oktober 2026: penyatuan tampilan admin tidak mengubah migrasi, tabel, kolom, foreign key, atau relasi ERD. Modul perusahaan tetap memakai relasi `perusahaan.created_by_prodi_id`, `perusahaan.created_by_user_id`, dan `biodata.perusahaan_id` untuk menentukan cakupan data Prodi/Fakultas pada lapisan aplikasi.

---

## 1. Diagram ERD (Mermaid Visual)

```mermaid
erDiagram
    %% ==========================================
    %% 1. AUTENTIKASI, PENGGUNA & AUDIT TRAIL
    %% ==========================================
    users ||--o| biodata : "memiliki profil (user_id)"
    users ||--o{ perusahaan : "mengajukan perusahaan (created_by_user_id)"
    prodi ||--o{ users : "menaungi admin prodi (prodi_id)"
    prodi ||--o{ perusahaan : "prodi pengaju (created_by_prodi_id)"
    ref_fakultas ||--o{ users : "menaungi admin fakultas (fakultas_id)"
    users ||--o{ log_activities : "melakukan aktivitas (user_id)"

    users {
        bigint id PK
        string name
        string email UK
        string username UK "NIM / NIK / Kode Akun"
        string role "superadmin, admin_biro3, admin_fakultas, admin_prodi, alumni"
        bigint prodi_id FK "nullable"
        bigint fakultas_id FK "nullable"
        string password
        boolean must_change_password
        timestamp created_at
        timestamp updated_at
    }

    log_activities {
        bigint id PK
        bigint user_id FK "nullable, references users.id"
        string action "100, e.g. VERIFIKASI_PERUSAHAAN, RESET_PASSWORD_DOB, UPDATE_USER"
        string model_type "nullable"
        unsigned_bigint model_id "nullable"
        text description
        json old_values "nullable"
        json new_values "nullable"
        string ip_address "45, nullable"
        text user_agent "nullable"
        timestamp created_at
        timestamp updated_at
    }

    %% ==========================================
    %% 2. MASTER DATA, FAKULTAS & WILAYAH
    %% ==========================================
    ref_fakultas ||--o{ prodi : "menaungi (fakultas_id)"
    propinsi ||--o{ kabupaten : "memiliki (propinsi_id)"
    propinsi ||--o| ump : "memiliki standar UMP (kode_provinsi)"
    propinsi ||--o{ perusahaan : "lokasi provinsi (propinsi_id)"
    kabupaten ||--o{ perusahaan : "lokasi kabupaten (kabupaten_id)"
    ref_negara ||--o{ perusahaan : "lokasi internasional (negara)"
    prodi ||--o{ biodata : "memiliki alumni (prodi_id)"
    propinsi ||--o{ biodata : "domisili alumni (propinsi_id)"
    kabupaten ||--o{ biodata : "domisili alumni (kabupaten_id)"
    propinsi ||--o{ data_akademik : "provinsi domisili akademik (propinsi_id)"
    kabupaten ||--o{ data_akademik : "kabupaten domisili akademik (kabupaten_id)"

    ref_fakultas {
        bigint id PK
        string kode_fakultas UK "1, 2, 3, 4, 6, 7, 8"
        string nama_fakultas
        timestamp created_at
        timestamp updated_at
    }

    ref_negara {
        bigint id PK
        string nama_negara UK
        string ibu_kota "nullable"
        string kode_iso2 UK "char(2)"
        string kode_iso3 "nullable, char(3)"
        string nama_resmi "nullable"
        string benua "nullable"
        timestamp created_at
        timestamp updated_at
    }

    propinsi {
        bigint id PK
        string kode_provinsi UK
        string nama_provinsi
        timestamp created_at
        timestamp updated_at
    }

    kabupaten {
        bigint id PK
        bigint propinsi_id FK
        string nama_kabupaten
        timestamp created_at
        timestamp updated_at
    }

    prodi {
        bigint id PK
        bigint fakultas_id FK "nullable, references ref_fakultas.id"
        string kode_prodi UK
        string nama_prodi
        string jenjang "nullable, e.g. S1, S2, Profesi"
        timestamp created_at
        timestamp updated_at
    }

    ump {
        bigint id PK
        string kode_provinsi FK "references propinsi.kode_provinsi"
        int tahun "2026"
        decimal besaran "12,2"
        string catatan "nullable"
        timestamp created_at
        timestamp updated_at
    }

    %% ==========================================
    %% 3. ENTITAS AKADEMIK, YUDISIUM & PROFIL BIODATA
    %% ==========================================
    data_akademik ||--|| biodata : "relasi identitas tunggal bebas duplikasi (nim)"
    data_akademik ||--o| yudisium : "kelulusan & tugas akhir (nim)"
    biodata ||--o| yudisium : "referensi yudisium (yudisium_id)"
    biodata ||--o| data_orang_tua : "kontak wali (orang_tua_id/nim)"
    perusahaan ||--o{ biodata : "tempat bekerja (perusahaan_id)"
    atasan ||--o{ biodata : "atasan langsung (atasan_id)"
    biodata ||--o{ evaluasi_atasan : "dievaluasi atasan (biodata_id)"
    perusahaan ||--o{ evaluasi_atasan : "tempat alumni dinilai (perusahaan_id)"
    atasan ||--o{ evaluasi_atasan : "penilai survey (atasan_id)"
    biodata ||--o{ linkedin_sync_results : "riwayat staging scraping linkedin (biodata_id)"
    users ||--o{ linkedin_sync_results : "direview oleh superadmin (reviewed_by)"

    data_akademik {
        bigint id PK
        char nim UK "8, Primary Identifier Akademik UKDW"
        string nama "100, Master Nama Lengkap Mahasiswa"
        year angkatan_masuk "4, nullable (Tahun Masuk)"
        string tempat_lahir "60, nullable"
        date tanggal_lahir "nullable"
        enum agama "Islam, Kristen Protestan, Katolik, Hindu, Buddha, Konghucu, Lainnya"
        enum jenis_kelamin "Laki-laki, Perempuan"
        string golongan_darah "5, nullable"
        string warga_negara "10, default WNI"
        string nomor_telepon "20, nullable"
        string email_pribadi "100, nullable"
        string email_students "100, nullable (Email resmi kampus UKDW)"
        text alamat_saat_ini "nullable (Alamat domisili saat kuliah / KTP)"
        string kelurahan "50, nullable"
        string kecamatan "50, nullable"
        bigint kabupaten_id FK "nullable, references kabupaten.id"
        bigint propinsi_id FK "nullable, references propinsi.id"
        char kode_pos "5, nullable (Kode pos asal KTP 5 digit)"
        char nik "16, nullable (16 digit NIK KTP)"
        char no_kk "16, nullable (16 digit No KK)"
        string nisn "15, nullable"
        string no_bpjs "20, nullable"
        string npwp "25, nullable"
        string asal_sekolah "100, nullable"
        text alamat_asal_sekolah "nullable"
        string kota_kabupaten_asal_sekolah "60, nullable"
        string provinsi_asal_sekolah "60, nullable"
        string jurusan_asal_sekolah "50, nullable"
        enum status_mahasiswa "Lulus, Aktif, Cuti, Drop Out, Lainnya (default Lulus)"
        string tahun_akademik_lulus "30, nullable, misal: Gasal 2024/2025"
        year tahun_lulus "nullable, e.g. 2021-2026"
        date tanggal_lulus "nullable, tanggal SK yudisium"
        decimal ip_kumulatif "3,2, nullable"
        smallint total_sks "nullable"
        decimal total_angka_kualitas "8,2, nullable"
        timestamp created_at
        timestamp updated_at
    }

    yudisium {
        bigint id PK
        char nim UK "8, FK references data_akademik.nim"
        string dosen_pembimbing_1 "100, nullable"
        string dosen_pembimbing_2 "100, nullable"
        string dosen_penguji_1 "100, nullable"
        string dosen_penguji_2 "100, nullable"
        text judul_ta "nullable (Skripsi/Tesis/TA)"
        text judul_ta_inggris "nullable"
        string url_publikasi "200, nullable (Link repositori/jurnal ilmiah)"
        string jenis_publikasi "100, nullable"
        string status_publikasi "50, nullable"
        string keterangan_hasil_yudisium "60, nullable"
        enum status_lulus "Belum, Proses, Lulus, Tidak Lulus"
        timestamp created_at
        timestamp updated_at
    }

    biodata {
        bigint id PK
        bigint user_id FK "references users.id"
        char nim UK "8, FK references data_akademik.nim"
        bigint orang_tua_id FK "nullable, references data_orang_tua.id"
        bigint yudisium_id FK "nullable, references yudisium.id"
        bigint prodi_id FK "nullable, references prodi.id"
        string foto "200, nullable (Path file di /uploads/profile)"
        string nomor_telepon "20, nullable"
        string email_pribadi "100, nullable (Korespondensi aktif alumni)"
        text alamat_saat_ini "nullable (Alamat domisili tempat tinggal saat ini)"
        bigint kabupaten_id FK "nullable, references kabupaten.id"
        bigint propinsi_id FK "nullable, references propinsi.id"
        char kode_pos "5, nullable (Kode pos tempat tinggal alumni saat ini)"
        char nik "16, nullable (16 digit NIK KTP alumni)"
        string npwp "25, nullable (Nomor Pokok Wajib Pajak alumni)"
        string instagram_url "150, nullable"
        string facebook_url "150, nullable"
        string linkedin_url "150, nullable"
        string linkedin_username "100, nullable"
        text skills "nullable (Daftar keahlian spesifik alumni)"
        text experience "nullable (Pengalaman kerja / riwayat karier)"
        bigint perusahaan_id FK "nullable, references perusahaan.id"
        bigint atasan_id FK "nullable, references atasan.id"
        string kategori_pekerjaan "50, nullable (Bekerja, Wiraswasta, Melanjutkan Pendidikan)"
        string posisi_jabatan "100, nullable"
        string posisi_wiraswasta "100, nullable"
        string pendidikan_tingkat "30, nullable (Profesi, S2, S3)"
        string perguruan_tinggi "100, nullable"
        string pendidikan_prodi "100, nullable"
        decimal gaji "15,2, nullable (Take Home Pay)"
        string jenis_pekerjaan "100, nullable"
        timestamp created_at
        timestamp updated_at
    }

    linkedin_sync_results {
        bigint id PK
        bigint biodata_id FK "references biodata.id"
        string status "pending, approved, rejected"
        text linkedin_url
        string linkedin_username "nullable"
        json scraped_data "nullable (JSON mentah scraping Apify)"
        timestamp scraped_at "nullable"
        bigint reviewed_by FK "nullable, references users.id"
        timestamp reviewed_at "nullable"
        text review_notes "nullable"
        timestamp created_at
        timestamp updated_at
    }

    data_orang_tua {
        bigint id PK
        char nim UK "8, FK references data_akademik.nim"
        string nama_orang_tua "100, nullable"
        string pekerjaan "100, nullable"
        text alamat "nullable"
        string kota "60, nullable"
        bigint kabupaten_id FK "nullable, references kabupaten.id"
        bigint propinsi_id FK "nullable, references propinsi.id"
        char kode_pos "5, nullable (Kode pos domisili orang tua 5 digit)"
        string nomor_telepon "20, nullable"
        timestamp created_at
        timestamp updated_at
    }

    perusahaan {
        bigint id PK
        string nama_perusahaan "150, Nama resmi instansi / perusahaan"
        bigint propinsi_id FK "nullable, references propinsi.id"
        bigint kabupaten_id FK "nullable, references kabupaten.id"
        text alamat "nullable"
        string homepage "150, nullable"
        string no_telp_fax "30, nullable"
        string kode_pos "10, nullable (Kode pos kantor domestik/internasional)"
        string sektor "100, nullable"
        enum skala "Lokal, Regional/Lokal, Nasional, Internasional (default Nasional)"
        string bentuk_perusahaan "60, nullable (BUMN, Perusahaan Terbatas, Koperasi, CV, Firma)"
        string jumlah_pegawai "60, nullable (< 50, 51-100, 101-150, 151-300, 301-500, > 500 Orang)"
        enum status_verifikasi "Menunggu Verifikasi, Terverifikasi, Ditolak"
        enum jenis_lokasi "Dalam Negeri, Luar Negeri"
        string negara "100, default Indonesia"
        string jenis_perusahaan "100, nullable (Instansi pemerintah, BUMN, Swasta, Wiraswasta, dll)"
        text jenis_perusahaan_lainnya "nullable"
        bigint created_by_user_id FK "nullable, references users.id"
        bigint created_by_prodi_id FK "nullable, index prodi pengaju"
        timestamp created_at
        timestamp updated_at
    }

    atasan {
        bigint id PK
        string nama
        string email "nullable"
        string telepon "nullable"
        timestamp created_at
        timestamp updated_at
    }

    %% ==========================================
    %% 4. KUESIONER TRACER STUDY UNIVERSITAS
    %% ==========================================
    kuesioner ||--o{ kelompok_pertanyaan : "memiliki seksi (kuesioner_id)"
    kelompok_pertanyaan ||--o{ ref_subpertanyaan2021 : "memuat pertanyaan (kelompok_pertanyaan_id)"
    ref_subpertanyaan2021 ||--o{ ref_subpertanyaan_detil : "memiliki opsi (pertanyaan_id)"
    biodata ||--o{ tracer : "menjawab (biodata_id)"
    ref_subpertanyaan2021 ||--o{ tracer : "direferensikan (question_id)"

    kuesioner {
        bigint id PK
        string title
        text description "nullable"
        int year "2026"
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }

    kelompok_pertanyaan {
        bigint id PK
        bigint kuesioner_id FK
        string kode_kelompok "nullable, e.g. 1..9"
        string title
        text description "nullable"
        int order
        timestamp created_at
        timestamp updated_at
    }

    ref_subpertanyaan2021 {
        bigint id PK
        char kelompok "3, e.g. BIO, F1, F2"
        bigint kelompok_pertanyaan_id FK
        string kode_pertanyaan UK "20"
        text subpertanyaan
        string type "50, single_choice, multiple_choice, text, number, rating_5, multiple_number, radio_input, matrix, dll"
        string keterangan "100, nullable"
        tinyint wajib "1=wajib, 0=opsional"
        string tampil_di "100, nullable, profil vs kuesioner"
        int order
        timestamp created_at
        timestamp updated_at
    }

    ref_subpertanyaan_detil {
        bigint id PK
        bigint pertanyaan_id FK "references ref_subpertanyaan2021.id"
        string kode_pertanyaan "nullable"
        string kode_opsi "nullable"
        text option_text
        string jump_to "nullable, kode pertanyaan tujuan branching"
        int order
        timestamp created_at
        timestamp updated_at
    }

    tracer {
        bigint id PK
        bigint biodata_id FK "references biodata.id"
        bigint question_id FK "references ref_subpertanyaan2021.id"
        string nim
        string kelompok "char(3), misal: BIO, F1, F2, F17"
        string kode_pertanyaan "varchar(50)"
        text subpertanyaan "nullable"
        text answer "nullable"
        json answer_json "nullable"
        string keterangan "nullable"
        string tahun_lulus "nullable"
        timestamp created_at
        timestamp updated_at
    }

    %% ==========================================
    %% 5. KUESIONER KHUSUS PROGRAM STUDI
    %% ==========================================
    prodi ||--o{ prodi_question_section : "mengelola seksi (prodi_id)"
    prodi_question_section ||--o{ prodi_question : "memuat pertanyaan (prodi_question_section_id)"
    prodi_question ||--o{ prodi_question_option : "memiliki opsi (prodi_question_id)"
    biodata ||--o{ prodi_response : "menjawab kuesioner prodi (biodata_id)"
    prodi_question ||--o{ prodi_response : "jawaban butir (prodi_question_id)"

    prodi_question_section {
        bigint id PK
        bigint prodi_id FK "references prodi.id"
        string title
        text description "nullable"
        int order
        timestamp created_at
        timestamp updated_at
    }

    prodi_question {
        bigint id PK
        bigint prodi_id FK "references prodi.id"
        bigint prodi_question_section_id FK
        string code "varchar(50)"
        text question_text
        string type "single_choice, multiple_choice, text, number, rating_5, radio_input"
        boolean is_required
        int order
        timestamp created_at
        timestamp updated_at
    }

    prodi_question_option {
        bigint id PK
        bigint prodi_question_id FK
        string code "nullable"
        text option_text
        string jump_to "nullable"
        int order
        timestamp created_at
        timestamp updated_at
    }

    prodi_response {
        bigint id PK
        bigint biodata_id FK "references biodata.id"
        bigint prodi_question_id FK "references prodi_question.id"
        text answer_text "nullable"
        json answer_json "nullable"
        timestamp created_at
        timestamp updated_at
    }

    %% ==========================================
    %% 6. EVALUASI ATASAN / PENGGUNA LULUSAN
    %% ==========================================
    evaluasi_atasan ||--o{ respon_evaluasi_atasan : "memiliki jawaban (evaluasi_atasan_id)"
    pertanyaan_evaluasi_atasan ||--o{ respon_evaluasi_atasan : "direferensikan (pertanyaan_id)"

    evaluasi_atasan {
        bigint id PK
        bigint biodata_id FK "references biodata.id"
        bigint perusahaan_id FK "nullable, references perusahaan.id"
        bigint atasan_id FK "nullable, references atasan.id"
        string token UK "64, token survei publik tanpa login"
        boolean is_submitted "default false"
        timestamp submitted_at "nullable"
        string jumlah_alumni_ukdw "nullable (< 5, 6-10, 11-20, > 21 Orang)"
        string standar_gaji_pertama "nullable (< 1jt, 1-1.5jt, ..., > 5jt)"
        timestamp created_at
        timestamp updated_at
    }

    pertanyaan_evaluasi_atasan {
        bigint id PK
        string kategori "50, default Kinerja"
        string kode UK "50"
        string aspek
        text deskripsi "nullable"
        string tipe "50, likert_5, pilihan_ganda, text, textarea"
        json pilihan_jawaban "nullable, untuk opsi pilihan_ganda"
        int order
        boolean is_active "default true"
        timestamp created_at
        timestamp updated_at
    }

    respon_evaluasi_atasan {
        bigint id PK
        bigint evaluasi_atasan_id FK "references evaluasi_atasan.id"
        bigint pertanyaan_id FK "references pertanyaan_evaluasi_atasan.id"
        string nilai "100, jawaban sesuai tipe instrumen"
        text catatan "nullable"
        timestamp created_at
        timestamp updated_at
    }
```

---

## 2. Kelompok Entitas & Kamus Data (*Data Dictionary*)

### A. Modul Akun, Autentikasi & Audit Trail
1. **`users`**:
   - Menyimpan kredensial autentikasi pengguna.
   - Kolom: `id`, `name`, `email` (UK), `username` (UK), `password`, `role` (`superadmin`, `admin_biro3`, `admin_fakultas`, `admin_prodi`, `alumni`), `must_change_password` (boolean), `prodi_id` (FK nullable), `fakultas_id` (FK nullable).
   - Fitur Manajemen Akun: Reset password instan ke format tanggal lahir alumni (`DDMMYYYY`) dan konfirmasi langsung ke `email` pribadi yang terdaftar.
2. **`log_activities`**:
   - Menyimpan seluruh rekaman audit trail (*audit logging*) sistem secara komprehensif.
   - Kolom: `id`, `user_id` (FK ke `users`, nullable), `action` (e.g. `ACC_VERIFIKASI_PERUSAHAAN`, `GANTI_PERUSAHAAN`, `REJECT_PERUSAHAAN`, `RESET_PASSWORD_DOB`, `UPDATE_USER`, `UPDATE_PROFIL_ALUMNI`), `model_type`, `model_id`, `description`, `old_values` (JSON), `new_values` (JSON), `ip_address`, `user_agent`.

---

### B. Modul Wilayah & Master Institusi
1. **`prodi`**: Master program studi di lingkungan UKDW (Sistem Informasi, Informatika, Arsitektur, Desain Produk, Manajemen, Akuntansi, Biologi, Kedokteran, Teologi, dll.).
2. **`ref_fakultas`**: Master fakultas di UKDW yang menaungi program studi terkait.
3. **`ref_negara`**: Master data 194 negara resmi dunia (kode ISO 2 unik, nama negara unik, ibu kota, benua) untuk lokasi perusahaan internasional dan kewarganegaraan alumni. Disinkronkan dari berkas [`data/daftar_negara_dunia.csv`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/data/daftar_negara_dunia.csv).
4. **`propinsi`** & **`kabupaten`**: Master data wilayah administratif Republik Indonesia (38 Provinsi dan 514 Kabupaten/Kota). Disinkronkan dari berkas [`data/provinsi.csv`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/data/provinsi.csv) dan [`data/kabupaten_kota.csv`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/data/kabupaten_kota.csv).
5. **`ump`**: Data Upah Minimum Provinsi (UMP) tahun 2026 terhubung via `kode_provinsi` untuk evaluasi kesesuaian gaji standar kelayakan hidup.

---

### C. Modul Data Akademik, Kelulusan & Profil Biodata Alumni
1. **`data_akademik`** (*Single Source of Truth* Master Identitas & Akademik):
   - Menyimpan seluruh data resmi mahasiswa/alumni yang bersumber dari Pangkalan Data Akademik universitas:
     - Kunci Utama: `id`, `nim` (`CHAR(8)` Unique, format resmi UKDW).
     - Identitas Kependudukan & Pribadi: `nama`, `angkatan_masuk` (`YEAR(4)`), `tempat_lahir`, `tanggal_lahir`, `agama` (`ENUM('Islam', 'Kristen Protestan', 'Katolik', 'Hindu', 'Buddha', 'Konghucu', 'Lainnya')`), `jenis_kelamin` (`ENUM('Laki-laki', 'Perempuan')`), `golongan_darah` (`VARCHAR(5)`), `warga_negara` (`VARCHAR(10)`, default 'WNI'), `nik` (`CHAR(16)`), `no_kk` (`CHAR(16)`), `nisn` (`VARCHAR(15)`), `no_bpjs` (`VARCHAR(20)`), `npwp` (`VARCHAR(25)`).
     - Riwayat Pendidikan Menengah: `asal_sekolah`, `alamat_asal_sekolah`, `kota_kabupaten_asal_sekolah`, `provinsi_asal_sekolah`, `jurusan_asal_sekolah`.
     - Kontak & Alamat Asal KTP: `nomor_telepon`, `email_students` (email kampus `@students.ukdw.ac.id`), `email_pribadi`, `alamat_saat_ini`, `kelurahan`, `kecamatan`, `kabupaten_id`, `propinsi_id`, `kode_pos` (`CHAR(5)`).
     - Parameter Kelulusan: `status_mahasiswa` (`ENUM('Lulus', 'Aktif', 'Cuti', 'Drop Out', 'Lainnya')`, default 'Lulus'), `tahun_akademik_lulus`, `tahun_lulus` (`YEAR(4)`), `tanggal_lulus`, `ip_kumulatif`, `total_sks`, `total_angka_kualitas`.
   - **Karakteristik**: Seluruh field identitas kependudukan & akademik dikunci permanen (*read-only/disabled*) di halaman profil untuk semua role (Alumni, SuperAdmin, Fakultas, Prodi, Biro 3).
2. **`yudisium`**:
   - Menyimpan data penyelesaian tugas akhir dan kelulusan yudisium:
     - `nim` (`CHAR(8)` Unique FK ke `data_akademik.nim`).
     - Dosen Pembimbing (`dosen_pembimbing_1`, `dosen_pembimbing_2`) & Dosen Penguji (`dosen_penguji_1`, `dosen_penguji_2`).
     - Judul Tugas Akhir (`judul_ta`, `judul_ta_inggris`).
     - Publikasi Ilmiah (`url_publikasi`, `jenis_publikasi`, `status_publikasi`).
     - Hasil & Status Yudisium: `keterangan_hasil_yudisium`, `status_lulus` (`Belum`, `Proses`, `Lulus`, `Tidak Lulus`).
   - **Otomasi Siklus Kelulusan**: Ketika `status_lulus` bernilai `'Lulus'`, sistem (via Model Event `Yudisium::saved`) otomatis:
     - Mengubah status mahasiswa di `data_akademik` menjadi `'Lulus'`.
     - Mengisi tanggal, tahun, dan periode kelulusan di `data_akademik`.
     - Membuatkan akun `users` baru (username: NIM, role: alumni, password awal: tgl lahir `DDMMYYYY`) jika belum ada.
     - Menginisialisasi kerangka record `biodata` (skeleton profile) dengan data identitas, prodi, dan ortu terisi otomatis dari data akademik, sementara kolom karier (`perusahaan_id`, `posisi_jabatan`, `gaji`, dll.) dibiarkan kosong/null untuk diisi alumni atau disinkronkan melalui LinkedIn.
3. **`biodata`** (Profil Dinamis & Pelacak Tracer Karier):
   - Menyimpan profil aktif alumni, kontak pribadi, domisili terkini, media sosial, dan data karier tanpa duplikasi data master akademik:
     - Relasi: `user_id` (1:1 ke `users`), `nim` (`CHAR(8)` 1:1 ke `data_akademik`), `prodi_id`, `orang_tua_id`, `yudisium_id`, `perusahaan_id`, `atasan_id`.
     - Foto: `foto` (Path foto di `/uploads/profile`).
     - Kontak Pribadi: `email_pribadi` (digunakan untuk login dan korespondensi aktif), `nomor_telepon`.
     - Domisili Tempat Tinggal Terkini: `alamat_saat_ini`, `kabupaten_id`, `propinsi_id`, `kode_pos` (`CHAR(5)` satu-satunya kode pos domisili alumni).
     - Identitas Kependudukan & Pajak Alumni: `nik` (`CHAR(16)`), `npwp` (`VARCHAR(25)`).
     - Media Sosial & Portofolio Profesional: `instagram_url`, `facebook_url`, `linkedin_url`, `linkedin_username`, `skills`, `experience`.
     - Data Karier & Pelacak Tracer: `perusahaan_id`, `atasan_id`, `kategori_pekerjaan` (`Bekerja (Full Time)`, `Wiraswasta`, `Melanjutkan Pendidikan`, dll.), `posisi_jabatan`, `posisi_wiraswasta`, `pendidikan_tingkat`, `perguruan_tinggi`, `pendidikan_prodi`, `gaji`, `jenis_pekerjaan`.
     - **Prinsip Bebas Duplikasi Kode Pos**: Kolom fisik `zipcode` dan `kode_pos_saat_ini` tidak ada di tabel `biodata`. Kode pos lokasi kerja alumni diperoleh langsung secara relasional dari master `perusahaan.kode_pos`.
4. **`data_orang_tua`**: Kontak dan domisili orang tua/wali alumni (`nim` `CHAR(8)` FK ke `data_akademik.nim`, `nama_orang_tua`, `pekerjaan`, `nomor_telepon`, `alamat`, `kota`, `kabupaten_id`, `propinsi_id`, `kode_pos` `CHAR(5)`).
5. **`perusahaan`** & **`atasan`**:
   - Master data entitas institusi/perusahaan dan atasan alumni.
   - Kolom perusahaan: `nama_perusahaan`, `alamat`, `kode_pos` (`VARCHAR(10)` mendukung kode pos domestik 5 digit maupun internasional), `homepage`, `no_telp_fax`, `sektor`, `skala` (`Lokal`, `Regional/Lokal`, `Nasional`, `Internasional`), `bentuk_perusahaan` (`BUMN`, `Perusahaan Terbatas`, `Koperasi`, `CV`, `Firma`), `jumlah_pegawai` (`< 50 Orang`, `51 - 100 Orang`, `101 – 150 Orang`, `151 – 300 Orang`, `301 – 500 Orang`, `> 500 Orang`), `status_verifikasi` (`Menunggu Verifikasi`, `Terverifikasi`, `Ditolak`), `jenis_lokasi` (`Dalam Negeri`, `Luar Negeri`), `negara` (default 'Indonesia'), `jenis_perusahaan`, `jenis_perusahaan_lainnya`, `created_by_user_id`, `created_by_prodi_id`.
   - Kolom atasan: `nama`, `email`, `telepon`.
   - Menjadi *Single Source of Truth* informasi profil institusi tempat alumni berkarier sehingga tidak terjadi redundansi kolom pada instrumen evaluasi maupun profil alumni.

---

### D. Modul Kuesioner Tracer Study Universitas (Standar 2021)
1. **`kuesioner`**: Master paket kuesioner tracer study tingkat universitas yang aktif (`title`, `year`, `is_active`).
2. **`kelompok_pertanyaan`**: Seksi instrumen kuesioner universitas (`kuesioner_id`, `kode_kelompok`, `title`, `order`).
3. **`ref_subpertanyaan2021`**:
   - Butir pertanyaan kuesioner universitas (`kode_pertanyaan`, `subpertanyaan`, `type`, `wajib`, `order`).
   - Kolom `tampil_di`:
     - `'profil'`: Ditampilkan pada halaman Profil Alumni (Seksi 1 / Identitas & Biodata Mahasiswa).
     - `'kuesioner'`: Ditampilkan pada alur Kuesioner Universitas (Seksi 2 s.d. 9).
4. **`ref_subpertanyaan_detil`**: Opsi pilihan jawaban butir pertanyaan universitas dengan kolom `jump_to` untuk alur percabangan (*branching/skip logic*).
5. **`tracer`**: Tabel jawaban kuesioner universitas per alumni (`biodata_id`, `question_id`, `nim`, `kode_pertanyaan`, `answer`, `answer_json`, `keterangan`, `tahun_lulus`).

---

### E. Modul Kuesioner Khusus Program Studi
1. **`prodi_question_section`**: Seksi pertanyaan kuesioner yang dikelola mandiri oleh masing-masing Program Studi (`prodi_id`, `title`, `order`).
2. **`prodi_question`**: Butir pertanyaan kuesioner prodi (`code`, `question_text`, `type`, `is_required`, `order`).
3. **`prodi_question_option`**: Opsi jawaban butir pertanyaan prodi (`code`, `option_text`, `jump_to`, `order`).
4. **`prodi_response`**: Tabel jawaban kuesioner prodi alumni (`biodata_id`, `prodi_question_id`, `answer_text`, `answer_json`).

---

### F. Database Views Terkonsolidasi
*(Dokumentasi kueri DDL lengkap dan kamus data tersedia di [`docs/database-views.md`](docs/database-views.md))*

1. **`v_alumni_audit_rekap`** (Model: [`AlumniAuditRekap`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Models/AlumniAuditRekap.php)): View agregat utama 89 kolom yang menyatukan profil biodata, status kuesioner universitas, dan status kuesioner prodi untuk direktori alumni tanpa masalah query N+1.
2. **`v_alumni_profile_summary`** (Model: [`AlumniProfileSummary`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Models/AlumniProfileSummary.php)): View ringkasan profil lengkap 4 sub-tab (Pribadi, Akademik, Orang Tua, Karier).
3. **`v_alumni_tracer_univ_status`** (Model: [`AlumniTracerUnivStatus`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Models/AlumniTracerUnivStatus.php)): Menghitung persentase dan progres kelengkapan kuesioner universitas (otomatis mengecualikan butir identitas Seksi 1).
4. **`v_alumni_tracer_prodi_status`** (Model: [`AlumniTracerProdiStatus`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Models/AlumniTracerProdiStatus.php)): Menghitung status pengisian kuesioner mandiri program studi.
5. **`v_alumni_kuesioner_autofill`** (Model: [`AlumniKuesionerAutofill`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Models/AlumniKuesionerAutofill.php)): Pemetaan data profil alumni ke kode pertanyaan kuesioner untuk pengisian otomatis.
6. **`v_question_mappings`** (Model: [`QuestionMapping`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Models/QuestionMapping.php)): Tabel kamus relasional yang memetakan kode pertanyaan kuesioner dengan tabel dan kolom sumber basis data.
7. **`v_alumni_linkedin_plot`** (Model: [`AlumniLinkedinPlot`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Models/AlumniLinkedinPlot.php)): View perbandingan data scraping LinkedIn (`linkedin_sync_results`) berdampingan dengan profil `biodata`, dilengkapi verifikasi kecocokan nama alumni (`is_nama_sesuai`), deteksi kesamaan data perusahaan/posisi, dan status plot ke sistem (`status_plot_label`).

---

### G. Modul Evaluasi Pengguna Lulusan (Atasan Langsung)
1. **`evaluasi_atasan`** (Model: [`EvaluasiAtasan`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Models/EvaluasiAtasan.php)):
   - Sesi survei evaluasi tingkat kepuasan dan kinerja lulusan UKDW oleh pimpinan/atasan tempat alumni bekerja.
   - Kolom: `id`, `biodata_id` (FK), `perusahaan_id` (FK), `atasan_id` (FK), `token` (VARCHAR(64), unik; controller saat ini menghasilkan 40 karakter), `is_submitted` (boolean), `submitted_at` (datetime), `jumlah_alumni_ukdw` (rentang jumlah lulusan UKDW di perusahaan), `standar_gaji_pertama` (standar gaji awal alumni UKDW per bulan).
   - Diakses publik tanpa perlu autentikasi login via `/evaluasi-atasan/{token}`.
2. **`pertanyaan_evaluasi_atasan`** (Model: [`PertanyaanEvaluasiAtasan`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Models/PertanyaanEvaluasiAtasan.php)):
   - Bank butir instrumen evaluasi kepuasan atasan dinamis.
   - Kolom: `id`, `kategori` (string kategori instrumen), `kode`, `aspek`, `deskripsi`, `tipe` (tipe pertanyaan instrumen), `pilihan_jawaban` (JSON array opsi), `order`, `is_active`.
   - Memuat:
     - 1 butir tingkat kesiapan kerja alumni (`Sangat siap`, `Cukup Siap`, `Kurang siap`, `Tidak siap`).
     - 12 butir aspek kinerja lulusan (Etika, Keahlian Bidang Ilmu, Bhs Inggris, TI, Komunikasi, Kerja Tim, Pengembangan Diri, Kepemimpinan, Etos Kerja, Tanggung Jawab, Adaptasi, Inisiatif).
3. **`respon_evaluasi_atasan`** (Model: [`ResponEvaluasiAtasan`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Models/ResponEvaluasiAtasan.php)):
   - Menyimpan rekaman butir jawaban dari pimpinan/atasan.
   - Kolom: `id`, `evaluasi_atasan_id` (FK), `pertanyaan_id` (FK), `nilai` (VARCHAR(100), jawaban sesuai tipe instrumen), `catatan` (masukan/saran khusus).

---

### H. Modul Integrasi & Sinkronisasi LinkedIn
1. **`linkedin_sync_results`** (Model: [`LinkedinSyncResult`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Models/LinkedinSyncResult.php)):
   - Tabel staging penampung hasil penarikan (*scraping*) data profil profesional alumni via LinkedIn Provider (Apify Actor / Official API).
   - Kolom: `id`, `biodata_id` (FK ke `biodata.id`), `status` (`pending`, `approved`, `rejected`), `linkedin_url`, `linkedin_username`, `scraped_data` (JSON mentah lengkap profil), `scraped_at` (datetime), `reviewed_by` (FK ke `users.id`), `reviewed_at` (datetime), `review_notes` (catatan revisi/penolakan).
   - Menjamin isolasi data mentah scraping sebelum disetujui (*Approve*) oleh Super Admin untuk masuk ke tabel profil `biodata`.

---

## 3. Matriks Relasi Antar Tabel

| Entitas Sumber (Parent) | Relasi | Entitas Tujuan (Child) | Foreign Key / Constraint | Deskripsi & Perilaku Relasi |
|---|:---:|---|---|---|
| `users` | 1 : 1 | `biodata` | `biodata.user_id` | Setiap user akun alumni terhubung ke 1 entitas biodata profil. |
| `users` | 1 : N | `log_activities` | `log_activities.user_id` | Jejak audit mencatat user pelaku aksi sistem. |
| `prodi` | 1 : N | `users` | `users.prodi_id` | Admin Program Studi terikat pada 1 program studi. |
| `ref_fakultas` | 1 : N | `users` | `users.fakultas_id` | Admin Fakultas terikat pada 1 fakultas. |
| `prodi` | 1 : N | `biodata` | `biodata.prodi_id` | Alumni terafiliasi dengan program studi kelulusannya. |
| `data_akademik` | 1 : 1 | `biodata` | `biodata.nim = data_akademik.nim` | Relasi identitas akademik master bebas duplikasi data. |
| `data_akademik` | 1 : 1 | `yudisium` | `yudisium.nim = data_akademik.nim` | Relasi data kelulusan, skripsi/TA, dan repositori publikasi (NIM CHAR(8)). |
| `data_akademik` | 1 : 1 | `data_orang_tua` | `data_orang_tua.nim = data_akademik.nim` | Kontak wali dan data orang tua alumni (NIM CHAR(8)). |
| `biodata` | N : 1 | `yudisium` | `biodata.yudisium_id = yudisium.id` | Referensi langsung hasil yudisium pada profil alumni. |
| `biodata` | N : 1 | `data_orang_tua` | `biodata.orang_tua_id = data_orang_tua.id` | Referensi langsung data orang tua/wali pada profil alumni. |
| `users` | 1 : N | `perusahaan` | `perusahaan.created_by_user_id` | Pengguna pengaju pendaftaran institusi/perusahaan baru. |
| `prodi` | 1 : N | `perusahaan` | `perusahaan.created_by_prodi_id` | Program studi pengaju untuk pembatasan verifikasi prodi & fakultas. |
| `propinsi` | 1 : N | `perusahaan` | `perusahaan.propinsi_id` | Provinsi kantor perusahaan di Indonesia. |
| `kabupaten` | 1 : N | `perusahaan` | `perusahaan.kabupaten_id` | Kabupaten/Kota kantor perusahaan di Indonesia. |
| `ref_negara` | 1 : N | `perusahaan` | `perusahaan.negara = ref_negara.nama_negara` | Negara domisili kantor perusahaan internasional. |
| `perusahaan` | 1 : N | `biodata` | `biodata.perusahaan_id` | Tempat instansi/perusahaan alumni bekerja. |
| `atasan` | 1 : N | `biodata` | `biodata.atasan_id` | Atasan langsung alumni di tempat kerja. |
| `biodata` | 1 : N | `evaluasi_atasan` | `evaluasi_atasan.biodata_id` | Sesi survei evaluasi kinerja alumni oleh atasan langsung. |
| `perusahaan` | 1 : N | `evaluasi_atasan` | `evaluasi_atasan.perusahaan_id` | Profil institusi/perusahaan tempat alumni dievaluasi. |
| `atasan` | 1 : N | `evaluasi_atasan` | `evaluasi_atasan.atasan_id` | Atasan penilai survei evaluasi kepuasan pengguna. |
| `evaluasi_atasan` | 1 : N | `respon_evaluasi_atasan` | `respon_evaluasi_atasan.evaluasi_atasan_id` | Rekaman butir jawaban evaluasi atasan per survei. |
| `pertanyaan_evaluasi_atasan` | 1 : N | `respon_evaluasi_atasan` | `respon_evaluasi_atasan.pertanyaan_id` | Referensi butir aspek penilaian/kesiapan kerja. |
| `biodata` | 1 : N | `linkedin_sync_results` | `linkedin_sync_results.biodata_id` | Riwayat staging dan ekstraksi profil LinkedIn per alumni. |
| `users` | 1 : N | `linkedin_sync_results` | `linkedin_sync_results.reviewed_by` | Akun Administrator (Super Admin / Admin Fakultas / Admin Prodi / Admin Biro 3) peninjau dan penyetuju data scraping LinkedIn. |
| `propinsi` | 1 : N | `kabupaten` | `kabupaten.propinsi_id` | Hierarki kewilayahan provinsi ke kabupaten/kota. |
| `propinsi` | 1 : 1 | `ump` | `ump.kode_provinsi` | Standar UMP ketetapan pemerintah provinsi. |
| `kuesioner` | 1 : N | `kelompok_pertanyaan` | `kelompok_pertanyaan.kuesioner_id` | Seksi/bagian dalam instrumen tracer study universitas. |
| `kelompok_pertanyaan` | 1 : N | `ref_subpertanyaan2021` | `ref_subpertanyaan2021.kelompok_pertanyaan_id` | Butir soal per seksi instrumen universitas. |
| `ref_subpertanyaan2021` | 1 : N | `ref_subpertanyaan_detil` | `ref_subpertanyaan_detil.pertanyaan_id` | Opsi pilihan jawaban & branching logic kuesioner. |
| `biodata` | 1 : N | `tracer` | `tracer.biodata_id` | Rekaman jawaban butir kuesioner universitas. |
| `ref_subpertanyaan2021` | 1 : N | `tracer` | `tracer.question_id` | Referensi butir soal kuesioner universitas. |
| `prodi` | 1 : N | `prodi_question_section` | `prodi_question_section.prodi_id` | Seksi instrumen kuesioner mandiri program studi. |
| `prodi_question_section` | 1 : N | `prodi_question` | `prodi_question.prodi_question_section_id` | Butir soal instrumen mandiri program studi. |
| `prodi_question` | 1 : N | `prodi_question_option` | `prodi_question_option.prodi_question_id` | Pilihan opsi kuesioner mandiri program studi. |
| `biodata` | 1 : N | `prodi_response` | `prodi_response.biodata_id` | Rekaman jawaban butir kuesioner mandiri program studi. |
| `prodi_question` | 1 : N | `prodi_response` | `prodi_response.prodi_question_id` | Referensi butir soal kuesioner program studi. |

## Catatan konsistensi model (21 Oktober 2026)

Data akademik, yudisium dan orang tua tersedia dalam tabel lokal. Asal data universitas tidak membuktikan adanya integrasi API SIAKAD. DFD terkini mengelompokkannya sebagai D12; tidak menambah aktor SIAKAD tanpa konektor. View database merupakan proyeksi tabel, bukan penyimpanan bisnis baru.

`evaluasi_atasan.token` menampung sampai 64 karakter, sedangkan controller membuat token 40 karakter. Penyimpanan profil dan pengiriman undangan adalah aksi terpisah. Tidak terdapat field kedaluwarsa token pada migrasi evaluasi, dan handler POST tidak memeriksa penolakan submit ulang berdasarkan `is_submitted`.

Untuk nama kolom dan tipe fisik, migrasi `database/migrations` adalah rujukan implementasi. [DFD](docs/dfd.md) adalah model aliran logis, bukan pengganti ERD.
