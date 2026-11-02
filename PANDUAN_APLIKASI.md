# Tracer Study UKDW (SERU - Sistem Ekosistem Rekam Jejak Alumni)

Sistem Informasi Tracer Study Alumni Universitas Kristen Duta Wacana (UKDW). Dibangun menggunakan **Laravel 13** (Backend), **Vue 3** & **Inertia.js 3** (Frontend SPA), serta **Tailwind CSS**.

> **Revisi dokumentasi 21 Oktober 2026.** Rujukan alur dan batas sistem: [DFD](docs/dfd.md), [user flow](docs/user-flow.md), dan [use case](USE_CASE.md). Lima role akun dibedakan dari publik serta atasan bertoken. Data akademik tersedia pada tabel lokal; integrasi langsung SIAKAD belum diasumsikan.

> **Revisi 22 Oktober 2026.** Pola antarmuka admin menggunakan komponen bersama untuk sidebar dan header; Master Data Perusahaan memakai satu halaman lintas role dengan route, filter, dan data scope yang berbeda. Detailnya tersedia pada [arsitektur admin](docs/ARCHITECTURE.md).

---

## Daftar Isi
1. [Instalasi & Menjalankan Aplikasi](#instalasi--menjalankan-aplikasi)
2. [Peta Struktur File & Direktori Proyek](#peta-struktur-file--direktori-proyek)
3. [Entity Relationship Diagram (ERD) & Skema Database](#entity-relationship-diagram-erd--skema-database)
4. [Arsitektur Kuesioner & Alur Data](#arsitektur-kuesioner--alur-data)
   - [A. Kuesioner Utama Universitas](#a-kuesioner-utama-universitas)
   - [B. Kuesioner Khusus Program Studi](#b-kuesioner-khusus-program-studi)
   - [C. Entitas Biodata & Sinkronisasi Otomatis Data Profil](#c-entitas-biodata--sinkronisasi-otomatis-data-profil)
5. [Fitur Sinkronisasi LinkedIn (Driver-Based Switch via .ENV)](#fitur-sinkronisasi-linkedin-driver-based-switch-via-env)
6. [Modularisasi Komponen Vue Alumni](#modularisasi-komponen-vue-alumni)
7. [Manajemen Modul & Peran Pengguna (Roles)](#manajemen-modul--peran-pengguna-roles)
8. [Panduan Pencarian Cepat Kode (Quick Navigation)](#panduan-pencarian-cepat-kode-quick-navigation)

---

## Instalasi & Menjalankan Aplikasi

1. Clone repository
2. Jalankan `composer install`
3. Jalankan `npm install`
4. Copy `.env.example` ke `.env`
5. Generate app key: `php artisan key:generate`
6. Jalankan migrasi dan seeder: `php artisan migrate:fresh --seed`
7. Jalankan local development server:
   ```bash
   # Terminal 1: Backend Laravel
   php artisan serve

   # Terminal 2: Frontend Vite
   npm run dev
   ```

---

## Peta Struktur File & Direktori Proyek

Berikut adalah peta struktur seluruh file dan direktori utama proyek SERU Tracer Study UKDW untuk memudahkan pencarian file, fungsi, dan komponen:

```text
tracerstudy/
├── app/                                            # [BACKEND] Inti Logika Aplikasi (Laravel)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AdminProdi/                         # Modul Administrator Program Studi (role: admin_prodi)
│   │   │   │   ├── Dashboard/
│   │   │   │   │   └── DashboardController.php     # Menghitung KPI, statistik respon, & aktivitas alumni prodi
│   │   │   │   ├── KelolaAlumni/
│   │   │   │   │   └── DaftarAlumniProdiController.php # Menampilkan direktori & detail audit alumni prodi
│   │   │   │   └── KelolaPertanyaan/
│   │   │   │       ├── DaftarPertanyaanProdiController.php # Menampilkan & menyaring butir pertanyaan prodi
│   │   │   │       ├── KelolaOpsiProdiController.php       # Tambah, perbarui, & hapus opsi pilihan jawaban
│   │   │   │       ├── KelolaSectionProdiController.php    # CRUD seksi kuesioner prodi & fitur ubah urutan
│   │   │   │       └── SimpanPertanyaanProdiController.php  # Simpan, edit, & hapus butir pertanyaan prodi
│   │   │   ├── Alumni/                             # Modul Alumni (role: alumni)
│   │   │   │   ├── Dashboard/
│   │   │   │   │   └── DashboardController.php     # Progres kuesioner universitas, prodi, & kelengkapan profil
│   │   │   │   ├── Kuesioner/
│   │   │   │   │   ├── KuesionerController.php       # Menampilkan kuesioner tracer study universitas (F1-F22)
│   │   │   │   │   ├── KuesionerProdiController.php  # Menampilkan & menyimpan kuesioner khusus program studi
│   │   │   │   │   └── SimpanJawabanController.php   # Menyimpan jawaban kuesioner tracer study universitas
│   │   │   │   └── Profil/
│   │   │   │       ├── ProfilController.php        # Menampilkan halaman kelola biodata & riwayat alumni
│   │   │   │       └── SimpanProfilController.php  # Simpan pembaruan data kontak, domisili, dan karier
│   │   │   ├── SuperAdmin/                         # Modul Super Admin (role: superadmin)
│   │   │   │   ├── Dashboard/
│   │   │   │   │   └── DashboardController.php     # Metrik universitas, chart statistik, & monitoring sistem
│   │   │   │   ├── KelolaAlumni/
│   │   │   │   │   ├── DaftarAlumniSuperAdminController.php # Direktori seluruh alumni lintas fakultas/prodi
│   │   │   │   │   └── DetailAlumniSuperAdminController.php # Audit lengkap respon kuesioner univ, prodi, & profil
│   │   │   │   ├── KelolaPertanyaan/
│   │   │   │   │   ├── DaftarPertanyaanController.php  # Kelola butir instrumen kuesioner universitas
│   │   │   │   │   ├── KelolaOpsiController.php        # Kelola opsi jawaban & konfigurasi alur jump_to
│   │   │   │   │   ├── KelolaKuesionerProdiSuperAdminController.php # Pengaturan kuesioner prodi (Pilih prodi -> section & pertanyaan)
│   │   │   │   │   └── SimpanPertanyaanController.php  # Simpan & perbarui butir instrumen universitas
│   │   │   │   ├── KelolaSection/
│   │   │   │   │   └── KelolaSectionController.php     # CRUD seksi/bagian kuesioner universitas
│   │   │   │   ├── Perusahaan/
│   │   │   │   │   └── VerifikasiPerusahaanSuperAdminController.php # ACC/Verifikasi perusahaan alumni (All & Per Prodi)
│   │   │   │   ├── LinkedIn/
│   │   │   │   │   └── LinkedInSyncController.php      # Orkestrasi sinkronisasi LinkedIn single & batch superadmin
│   │   │   │   ├── Logs/
│   │   │   │   │   └── LogAktivitasController.php      # Audit log aktivitas sistem
│   │   │   │   ├── Analytics/
│   │   │   │   │   └── TracerAnalyticsController.php   # Controller Business Intelligence & Grafik Universitas (/superadmin/analitik)
│   │   │   │   └── ManajemenAkunController.php     # Manajemen akun pengguna & pemulihan password email terdaftar (pribadi)
│   │   │   ├── AdminBiroTiga/                      # Modul Admin Biro III (role: admin_biro3)
│   │   │   │   ├── Dashboard/
│   │   │   │   │   └── DashboardController.php     # Monitoring responden per semester/tahun kelulusan
│   │   │   │   └── KelolaAlumni/
│   │   │   │       ├── DaftarAlumniController.php  # Direktori alumni tersaring yudisium 'Lulus'
│   │   │   │       ├── DetailAlumniController.php  # Detail profil alumni & rekam jejak karier
│   │   │   │       └── SinkronisasiLinkedinController.php # Sinkronisasi profil LinkedIn via LinkedInProfileService resmi
│   │   │   ├── Otentikasi/                         # Modul Autentikasi Pengguna
│   │   │   │   ├── LoginController.php             # Login multi-role (Superadmin, Biro 3, Admin Prodi, Alumni)
│   │   │   │   └── UbahKataSandiController.php     # Wajib ganti sandi awal bagi alumni baru
│   │   │   └── Tamu/                               # Modul Pengunjung Publik
│   │   │       └── BerandaController.php           # Landing page publik, statistik capaian, & peta alumni
│   │   └── Middleware/
│   │       ├── CheckMustChangePassword.php         # Interseptor jika user wajib memperbarui password awal
│   │       ├── HandleInertiaRequests.php           # Shared data Inertia (auth user, flash session, navigasi)
│   │       └── RoleMiddleware.php                  # Pembatas hak akses berdasarkan role pengguna
│   ├── Models/                                     # Definisi Model Eloquent & Relasi Database (Nama Tunggal/Singular)
│   │   ├── User.php                                # Akun pengguna, role, relasi ke biodata, prodi_id, & fakultas_id
│   │   ├── Biodata.php                             # Entitas profil biodata alumni (tabel `biodata`, 29 atribut termasuk `foto`), relasi akademik, yudisium_id, dll.
│   │   ├── DataAkademik.php                        # Pangkalan data akademik (tabel `data_akademik`: NIM, IPK, tahun lulus, asal sekolah)
│   │   ├── Yudisium.php                            # Data kelulusan, skripsi/TA, & publikasi ilmiah (tabel `yudisium`: judul_ta, url_publikasi, jenis_publikasi)
│   │   ├── Prodi.php                               # Data master program studi UKDW (tabel `prodi`)
│   │   ├── RefFakultas.php                         # Data master fakultas UKDW (tabel `ref_fakultas`)
│   │   ├── RefNegara.php                           # Data master 194 negara dunia & kode ISO 2 (tabel `ref_negara`)
│   │   ├── Perusahaan.php                          # Profil perusahaan tempat alumni bekerja (tabel `perusahaan`, status_verifikasi, created_by)
│   │   ├── Atasan.php                              # Data atasan langsung alumni di perusahaan (tabel `atasan`)
│   │   ├── Propinsi.php                            # Master data wilayah provinsi Indonesia (tabel `propinsi`)
│   │   ├── Kabupaten.php                           # Master data kabupaten/kota Indonesia (tabel `kabupaten`)
│   │   ├── Ump.php                                 # Data referensi Upah Minimum Provinsi (tabel `ump`)
│   │   ├── Kuesioner.php                           # Header kuesioner tracer study universitas (tabel `kuesioner`)
│   │   ├── KelompokPertanyaan.php                  # Bagian/seksi kuesioner universitas (tabel `kelompok_pertanyaan`)
│   │   ├── RefSubpertanyaan2021.php                # Butir pertanyaan kuesioner universitas 2021 (tabel `ref_subpertanyaan2021`, tampil_di)
│   │   ├── RefSubpertanyaanDetil.php               # Opsi jawaban & nilai jump_to kuesioner univ (tabel `ref_subpertanyaan_detil`)
│   │   ├── Tracer.php                              # Jawaban kuesioner tracer study alumni (tabel `tracer`)
│   │   ├── QuestionMapping.php                     # Pemetaan profil biodata via tabel `question_mappings` & VIEW `v_question_mappings`
│   │   ├── AlumniAuditRekap.php                    # Model Eloquent untuk Database View `v_alumni_audit_rekap`
│   │   ├── AlumniProfileSummary.php                # Model Eloquent untuk Database View `v_alumni_profile_summary`
│   │   ├── AlumniTracerUnivStatus.php              # Model Eloquent untuk Database View `v_alumni_tracer_univ_status`
│   │   ├── AlumniTracerProdiStatus.php             # Model Eloquent untuk Database View `v_alumni_tracer_prodi_status`
│   │   ├── AlumniKuesionerAutofill.php             # Model Eloquent untuk Database View `v_alumni_kuesioner_autofill`
│   │   ├── ProdiQuestionSection.php                # Bagian/seksi kuesioner khusus program studi (tabel `prodi_question_section`)
│   │   ├── ProdiQuestion.php                       # Butir pertanyaan kuesioner khusus program studi (tabel `prodi_question`)
│   │   ├── ProdiQuestionOption.php                 # Opsi jawaban kuesioner khusus program studi (tabel `prodi_question_option`)
│   │   └── ProdiResponse.php                       # Jawaban alumni untuk kuesioner khusus program studi (tabel `prodi_response`)
│   ├── Providers/
│   │   └── AppServiceProvider.php                  # Konfigurasi layanan global & Driver Resolver LinkedIn via .ENV
│   └── Services/                                   # Domain Services & Logika Bisnis Terpusat
│       ├── Analytics/
│       │   └── TracerAnalyticsService.php          # Kalkulasi metrik agregat 16+2 indikator universitas dari database view
│       ├── Dashboard/
│       │   └── RoleDashboardDataService.php        # Penyedia data rekap audit terpadu untuk dashboard multi-role
│       ├── Alumni/
│       │   └── AdminAlumniProfileService.php       # Service terpusat penyimpanan & pembacaan profil alumni oleh admin
│       ├── Export/
│       │   └── AlumniTracerExcelExporter.php       # Generator ekspor Excel (.xls) per alumni
│       ├── Kuesioner/
│       │   ├── KelengkapanTracerService.php        # Audit skor kelengkapan kuesioner universitas & profil
│       │   └── KuesionerSyncService.php            # Auto-sync data profil ke tracer & prodi_response
│       └── LinkedIn/                               # Modul Sinkronisasi LinkedIn (Driver-Based Resolution)
│           ├── Contracts/
│           │   └── LinkedInProfileProvider.php     # Interface kontrak provider profil LinkedIn
│           ├── DTOs/
│           │   ├── LinkedInEducation.php           # Data Transfer Object riwayat pendidikan
│           │   ├── LinkedInExperience.php          # Data Transfer Object riwayat pengalaman kerja
│           │   └── LinkedInProfile.php             # DTO agregat profil profesional LinkedIn
│           ├── Exceptions/
│           │   └── LinkedInProfileNotFoundException.php # Exception saat profil tidak ditemukan
│           ├── Mappers/
│           │   └── LinkedInProfileMapper.php       # Mapping DTO ke model Biodata & Perusahaan
│           ├── Providers/
│           │   ├── ApiLinkedInProvider.php         # Driver API resmi LinkedIn (Future-ready)
│           │   └── MockLinkedInProvider.php        # Driver Mock data lokal untuk testing & dev
│           └── LinkedInProfileService.php          # Service orkestrasi sinkronisasi, transaksi DB, & audit trail
├── data/                                           # Berkas Referensi Master Data CSV
│   ├── daftar_negara_dunia.csv                     # Master 194 negara dunia & kode ISO 2
│   ├── provinsi.csv                                # Master 38 provinsi di Indonesia
│   └── kabupaten_kota.csv                          # Master 514 kabupaten/kota di Indonesia
├── database/                                       # Skema & Data Awal Database
│   ├── migrations/                                 # Satu migration untuk setiap tabel dan view aktif
│   └── seeders/                                    # Data benih (Seeder - 19 kelas berurutan)
│       ├── DatabaseSeeder.php                      # Seeder master yang memanggil seluruh seeder
│       ├── RefFakultasSeeder.php                   # Seeder 7 fakultas UKDW
│       ├── RefNegaraSeeder.php                     # Seeder master 194 negara dunia dari data/daftar_negara_dunia.csv
│       ├── WilayahSeeder.php                       # Seeder 38 Provinsi & 514 Kabupaten/Kota dari data/*.csv
│       ├── UmpSeeder.php                           # Seeder data standar UMP 38 provinsi 2026
│       ├── UserSeeder.php                          # Akun demo (superadmin, biro3, admin prodi, alumni)
│       ├── PerusahaanSeeder.php                    # Seeder master instansi/perusahaan
│       ├── DataAkademikSeeder.php                  # Seeder data akademik & asal sekolah
│       ├── BiodataSeeder.php                       # Seeder 28 field profil biodata & karier
│       ├── YudisiumSeeder.php                      # Seeder data kelulusan & tugas akhir
│       ├── KuesionerSeeder.php                     # Seeder kuesioner 2021
│       ├── KelompokPertanyaanSeeder.php            # Seeder 10 kelompok pertanyaan
│       ├── RefSubpertanyaan2021Seeder.php          # Seeder 68 butir subpertanyaan 2021
│       ├── RefSubpertanyaanDetilSeeder.php         # Seeder 185 opsi jawaban & jump logic
│       ├── QuestionMappingSeeder.php               # Seeder sinkronisasi data profil
│       ├── ProdiQuestionnaireSeeder.php            # Seeder instrumen Prodi SI & Filsafat
│       ├── JoshuaAndreanSeeder.php                 # Seeder alumni uji coba khusus
│       ├── AlumniSimulationSeeder.php              # Simulasi alumni lintas 12 prodi
│       └── Lulusan2026Seeder.php                   # 10 alumni lulusan 2026 (profil karier kosong / skeleton)
├── public/                                         # Public Assets Root
│   ├── uploads/
│   │   ├── pigo/                                   # Aset resmi varian maskot Pigo
│   │   ├── profile/                                # Direktori penyimpanan berkas foto profil alumni
│   │   └── logo/                                   # Logo resmi Universitas Kristen Duta Wacana
│   └── geojson/                                    # Data batas poligon GeoJSON 38 provinsi dan kabupaten/kota Indonesia
├── resources/                                      # [FRONTEND] Antarmuka Pengguna (Vue 3, Inertia & Asset)
│   ├── js/
│   │   ├── app.js                                  # Bootstrapper Vue 3, Inertia SPA, & konfigurasi progress bar
│   │   ├── components/                             # Komponen Reusable Global
│   │   │   ├── RoleDashboard.vue                   # Template dashboard terpadu untuk Admin Prodi, Fakultas, dan Biro 3
│   │   │   ├── form/searchable-select.vue          # Dropdown pencarian dinamis (propinsi, kabupaten, prodi)
│   │   │   ├── landing/                            # Komponen modular landing page (Hero, Map 2-Kolom, Showcase Karya Alumni, Stats, dll)
│   │   │   ├── ui/                                 # Komponen UI global (pigo-loader.vue, dll)
│   │   │   └── analytics/                          # Modul visualisasi analitik & Business Intelligence
│   │   │       ├── AnalyticsChartsPanel.vue        # Index Hub router dinamis pemanggil 18 indikator kategori
│   │   │       ├── AnalyticsChartCard.vue          # Kartu visualisasi terintegrasi mode [ Grafik | Data ]
│   │   │       ├── AnalyticsEChart.vue             # Wrapper Apache ECharts reaktif
│   │   │       ├── AnalyticsMap.vue                # Peta choropleth wilayah kerja MapLibre GL
│   │   │       ├── analyticsChartOptions.js        # Utilitas opsi chart, palet brand, & kalkulasi statistik
│   │   │       └── categories/                     # 18 Komponen kategori analitik mandiri (01 s.d. 18)
│   │   └── Pages/                                  # Halaman Tampilan Inertia.js (View Routes)
│   │       ├── AdminProdi/                         # Antarmuka Admin Program Studi
│   │       ├── AdminFakultas/                      # Antarmuka Admin Fakultas
│   │       ├── Alumni/                             # Antarmuka Alumni
│   │       ├── SuperAdmin/                         # Antarmuka Super Admin Universitas
│   │       │   └── Analytics/
│   │       │       └── GrafikUniv.vue              # Halaman Business Intelligence Grafik Universitas (/superadmin/analitik)
│   │       ├── AdminBiroTiga/                      # Antarmuka Admin Biro III (Kemahasiswaan & Alumni)
│   │       ├── Otentikasi/                         # Antarmuka Login & Keamanan
│   │       └── Tamu/                               # Landing page utama publik
│   └── views/
│       └── app.blade.php                           # Template dasar HTML & container root Inertia SPA
└── routes/                                         # [ROUTING] Konfigurasi Rute Aplikasi
    ├── web.php                                     # Seluruh endpoint URL web aplikasi (Tamu, Auth, Alumni, Prodi, Fakultas, Superadmin, Biro 3)
    └── console.php                                 # Perintah konsol Artisan terjadwal
```

---

## Entity Relationship Diagram (ERD) & Skema Database

Dokumentasi lengkap diagram relasi antar tabel (ERD Mermaid), kamus data (*data dictionary*), dan spesifikasi kolom tersedia secara terdedikasi di:
👉 **[`ERD.md`](ERD.md)**

Ringkasan relasi utama & prinsip anti-duplikasi data:
- **`ref_fakultas` (1:N) $\rightarrow$ `prodi`**: Master data 7 fakultas UKDW terhubung ke program studi.
- **`prodi` (1:N) $\rightarrow$ `biodata`**: Program studi alumni UKDW.
- **`users` (1:1) $\rightarrow$ `biodata`**: Akun pengguna login. Email akun menjadi fallback autentikasi, sedangkan email pribadi aktif disimpan di `biodata`.
- **`data_akademik` (1:1) $\rightarrow$ `biodata`**: Pangkalan data master identitas dan akademik tunggal terhubung via `nim`. Kontak dan domisili aktif tidak disimpan di tabel ini.
- **`yudisium` (1:1) $\rightarrow$ `biodata`**: Data kelulusan resmi (`status_lulus`: 'Belum', 'Proses', 'Lulus', 'Tidak Lulus'), judul tugas akhir (`judul_ta`), dan link publikasi karya ilmiah (`url_publikasi`). Saat status berubah menjadi 'Lulus', sistem secara otomatis memperbarui `status_mahasiswa = 'L'`, `tanggal_lulus`, dan `tahun_lulus` pada `data_akademik`.
- **`log_activities`**: Rekam jejak audit trail transparan untuk setiap perubahan status verifikasi/ACC perusahaan, pergantian master (auto replace), penolakan pengajuan, reset password, dan pembaruan akun.
- **`biodata` (1:N) $\rightarrow$ `tracer`**: Respon pengisian kuesioner tracer study tingkat universitas.
- **`biodata` (1:N) $\rightarrow$ `prodi_response`**: Respon pengisian kuesioner evaluasi program studi.
- **`prodi` (1:N) $\rightarrow$ `prodi_question_section` $\rightarrow$ `prodi_question`**: Manajemen kuesioner mandiri prodi.
- **`propinsi` (1:N) $\rightarrow$ `kabupaten`**: Master data batas wilayah administratif Republik Indonesia.
- **`propinsi` (1:1) $\rightarrow$ `ump`**: Standar Upah Minimum Provinsi tahun 2026.
- **`perusahaan` (1:N) $\rightarrow$ `biodata`**: Master data instansi tempat bekerja alumni terverifikasi.

---

## Arsitektur Kuesioner & Alur Data

### A. Kuesioner Utama Universitas (Standar Tracer Study 2021)
- **Database**: `kuesioner` $\rightarrow$ `kelompok_pertanyaan` $\rightarrow$ `ref_subpertanyaan2021` $\rightarrow$ `ref_subpertanyaan_detil` $\rightarrow$ `tracer`.
- **Tabel Tracer**: Menyimpan jawaban alumni dengan kolom: `id`, `biodata_id`, `question_id`, `nim`, `kelompok` (char 3: 'BIO', 'F1', 'F2', 'F17', dst), `kode_pertanyaan`, `subpertanyaan`, `answer`, `answer_json`, `keterangan`, `tahun_lulus`.
- **Pemisahan Biodata**:
  - `BIO_TEMPAT_LAHIR`: Ditarik otomatis dari `data_akademik.tempat_lahir`.
  - `BIO_TANGGAL_LAHIR`: Ditarik otomatis dari `data_akademik.tanggal_lahir`.
  - Terhubung via database VIEW `v_question_mappings` dan tabel `question_mappings`.
- **Auto-Pull Profil Pekerjaan**:
  - `F2E` & `F5B` (Nama Perusahaan), `F2E1`..`F2E3` (Atasan: Nama, HP, Email), `F2F` & `F510` (Alamat Perusahaan), `F2G` & `F5C` (Posisi/Jabatan), `F2H` & `F5D` (Skala Perusahaan) ditarik otomatis dari profil biodata tanpa perlu diinput ulang.
- **Komponen Matriks & Rincian Gaji (multiple_number)**:
  - `TabelF2.vue`: Matriks 7 baris x 5 skala penilaian untuk metode pembelajaran (`F21` s.d. `F27`).
  - `TabelF17.vue`: Dual-matrix komparasi 7 baris x 5 skala penilaian untuk kompetensi (`F17a1`..`a7` vs `F17b1`..`b7`).
  - `F505` (`multiple_number`): Rincian Take Home Pay (Pekerjaan Utama, Lembur & Tips, Pekerjaan Lainnya) dengan input ribuan rupiah (`.000`), preview nominal Rupiah, dan kalkulasi total otomatis.
- **Urutan 10 Seksi Kuesioner Tracer Study**:
  1. Seksi 1: Identitas & Biodata Mahasiswa (Dikelola terpusat di form Profil)
  2. Seksi 2: Status Pekerjaan Saat Ini (`F8`, `F10`)
  3. Seksi 3: Waktu Mulai & Cara Mencari Pekerjaan (`F3`, `F4`)
  4. Seksi 4: Mendapatkan Pekerjaan & Data Pekerjaan (`F504`, `F502`, `F505`, `F505A`, `F506`)
  5. Seksi 5: Riwayat Lamaran Pekerjaan (`F6`, `F7`, `F7A`)
  6. Seksi 6: Studi Lanjut (`F18`, `F18a`, `F18b`, `F18c`, `F18d`)
  7. Seksi 7: Pembiayaan Kuliah (`F12`)
  8. Seksi 8: Keselarasan & Relevansi Pekerjaan (`F14`, `F15`, `F16`)
  9. Seksi 9: Evaluasi Kompetensi Lulusan (`F17`, `F17a1..a7`, `F17b1..b7`)
  10. Seksi 10: Penekanan Metode Pembelajaran (`F2`, `F21` s.d. `F27`)

- **Ekspor Data Jawaban ke Format Microsoft Excel (.xls)**:
  - Tersedia di detail alumni Super Admin (`/superadmin/alumni/{id}/export-excel`).
  - Menghasilkan dokumen Spreadsheet XML terstruktur lengkap dengan header metadata alumni (NIM, Nama, Prodi, Fakultas, Tahun Lulus), pewarnaan identitas hijau resmi UKDW `#005B3C`, indikator status jawaban, dan mencakup instrumen kuesioner universitas serta prodi.

- **Proteksi Data Perusahaan Terverifikasi**:
  - Jika perusahaan berstatus `Terverifikasi`, detail institusi (alamat, skala, jenis perusahaan, zipcode) terkunci di sisi alumni untuk menjaga integritas master data, kecuali jika alumni berposisi sebagai Owner / Founder / Wiraswasta atau perusahaan baru ditambahkan.

### B. Kuesioner Khusus Program Studi
- **Database**: Terpisah secara independen agar setiap program studi dapat mengelola instrumen evaluasinya sendiri:
  1. `prodi_question_section`: Bagian/seksi kuesioner berbasis `prodi_id`.
  2. `prodi_question`: Butir pertanyaan khusus prodi dengan tipe input dinamis.
  3. `prodi_question_option`: Pilihan opsi jawaban butir prodi.
  4. `prodi_response`: Jawaban tersimpan milik alumni untuk kuesioner program studinya (`biodata_id`, `prodi_question_id`, `answer_text`, `answer_json`).
- Telah diisi instrumen lengkap untuk **Program Studi Sistem Informasi** (9 Bagian, 52 Pertanyaan) dan **Filsafat Keilahian**.

### C. Entitas Biodata & Sinkronisasi Otomatis Data Profil
- Entitas profil alumni menggunakan model `App\Models\Biodata` pada tabel `biodata` yang memuat 28 field identitas, domisili, kontak, dokumen kependudukan, karier, dan media sosial.
- Pertanyaan identitas alumni pada Kuesioner Prodi:
  - **`PSI-1-01` (Nama)** $\rightarrow$ Diambil otomatis dari `biodata.nama` (fallback `data_akademik.nama` / `users.name`).
  - **`PSI-1-02` (NIM)** $\rightarrow$ Diambil otomatis dari `biodata.nim` (atau `data_akademik.nim`).
  - **`PSI-1-03` (Tahun Kelulusan)** $\rightarrow$ Diambil otomatis dari `biodata.tahun_lulus` / `yudisium.tahun_lulus` / `data_akademik.tahun_akademik_lulus`.
- Ditangani secara terpusat oleh [`KuesionerSyncService::syncProdiResponses($biodata)`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Services/Kuesioner/KuesionerSyncService.php).

---

## 5. Fitur Sinkronisasi LinkedIn (Multi-Provider: Mock, Official, & Apify)

Fitur ini memungkinkan Superadmin menyinkronkan data profil profesional alumni (jabatan, institusi perusahaan tempat bekerja, dan foto profil fisik) berbasis abstraksi driver yang dapat dipilih murni melalui konfigurasi `.env` tanpa mengubah kode controller, service bisnis, maupun UI.

### A. Alur Arsitektur Multi-Provider
```text
Shared/LinkedInSyncController
  |
  +-- LINKEDIN_PROVIDER=apify
  |     URL biodata.linkedin_url -> ApifyLinkedInProvider
  |     -> linkedin_sync_results (pending)
  |     -> review admin berwenang -> approve / reject
  |     -> mapping biodata/perusahaan hanya saat approve
  |     Batch ditolak (HTTP 400).
  |
  +-- LINKEDIN_PROVIDER=official
        LINKEDIN_DRIVER=mock -> MockLinkedInProvider
        LINKEDIN_DRIVER=api  -> ApiLinkedInProvider
        -> LinkedInProfileService -> pemetaan profil
```

Nama driver `official`/`api` adalah konfigurasi adapter aplikasi; bukan bukti kemitraan atau akses API produksi sudah tersedia. Lihat [panduan integrasi](docs/linkedin-mcp-integration.md).

### B. Konfigurasi Environment (`.env`)
```env
# 1. Mode Mock (Default Pengujian Lokal Tanpa Jaringan)
LINKEDIN_DRIVER=mock
LINKEDIN_MOCK_PATH=storage/app/mock/linkedin

# 2. Mode Apify Scraper (Scraping Profil LinkedIn Live via Actor Apify)
# LINKEDIN_PROVIDER=apify
# APIFY_API_TOKEN=apify_api_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
# APIFY_LINKEDIN_ACTOR_ID=data_forge_org~linkedin-scraper

# 3. Mode Official LinkedIn API (Server-to-Server Kemitraan Resmi)
# LINKEDIN_PROVIDER=official
# LINKEDIN_API_BASE_URL=https://api.linkedin.com/v2
# LINKEDIN_API_KEY=rahasia_api_key_server_to_server
```

### C. Alur Staging & Review Persetujuan (Approval Workflow)
Alur berikut khusus provider **Apify**. Driver mock/API diproses melalui `LinkedInProfileService` dan tidak boleh diasumsikan selalu melewati review yang sama. Peninjauan dilakukan Superadmin, Admin Prodi, atau Admin Fakultas sesuai lingkup.

1. **Isolasi Staging (`linkedin_sync_results`)**: Data hasil scraping pihak ketiga tidak langsung mengubah master data alumni. Hasil scraping disimpan ke tabel staging dengan status `pending` beserta payload mentah JSON.
2. **Review Perbandingan**: SuperAdmin dapat meninjau perbandingan data scraping vs data existing di halaman `/superadmin/linkedin-sync` sebelum memutuskan untuk menyetujui (*Approve*) atau menolak (*Reject*).
3. **Persetujuan (*Approve*)**:
   - Memperbarui `posisi_jabatan`, `kategori_pekerjaan = 'Pekerja'`, dan `foto`.
   - Mendaftarkan perusahaan ke tabel `perusahaan` jika belum ada dengan `status_verifikasi = 'Menunggu Verifikasi'`.
   - Mengubah status staging menjadi `approved` beserta user reviewer dan waktu review.
4. **Penolakan (*Reject*)**: Menandai hasil staging sebagai `rejected` tanpa menyentuh data profil master alumni.

### D. Download Foto Profil Permanen ke Server
- URL foto profil dari CDN LinkedIn memiliki token kedaluwarsa (`?e=...`) dan pembatasan hotlinking.
- Sistem mengunduh file foto secara fisik dan menyimpannya secara permanen ke direktori `public/uploads/profile/profile_{nim}_{timestamp}.jpg`.
- Alumni juga dapat mengunggah file foto mandiri (JPG/PNG max 2MB) melalui halaman Biodata Pribadi.

### E. Tab LinkedIn Detail Alumni: Tabel Audit & Trace Pemetaan
- Pada halaman Detail Alumni SuperAdmin (`/superadmin/alumni/{id}`), tersedia **Tab LinkedIn: Hasil Scraping & Trace LinkedIn**.
- Menampilkan tabel komparasi detail per atribut: *Key Atribut Scraping*, *Deskripsi Field*, *Nilai Mentah Apify*, *Target Kolom Database*, *Nilai Aktual di Database*, dan *Status Audit*.
- Dilengkapi pencarian teks real-time dan JSON Viewer interaktif dengan tombol *Copy Raw JSON*.

### F. Integritas Data & Kebijakan Verifikasi Perusahaan
- **Posisi Aktif (Current Position)**: Dipilih dengan prioritas posisi `endMonthYear == null`, kemudian tanggal mulai (`startMonthYear`) terbaru.
- **Kebijakan Verifikasi Mutlak**: Setiap institusi perusahaan baru yang dibuat dari sinkronisasi LinkedIn SELALU disetel ke `status_verifikasi = 'Menunggu Verifikasi'`, meskipun data alamat, provinsi, dan negara terisi lengkap.
  - **Proteksi Data Otoritatif**: Field otoritatif alumni (NIM, email pribadi, nomor telepon, alamat domisili, dan riwayat akademik) tidak pernah ditimpa oleh data LinkedIn.
- **Audit Trail**: Seluruh aktivitas sinkronisasi dicatat ke tabel `log_activities` melalui `LogActivity::record()` dengan aksi `'linkedin_sync'`.

### G. Navigasi Dua Arah & Deep Linking Terarah (Detail Alumni ⇄ LinkedIn Sync)
- **Deep Linking dari Detail Alumni (`/superadmin/alumni/{id}`)**:
  - Pada banner "Audit & Trace Hasil Scraping LinkedIn", klik pada logo LinkedIn maupun tombol "Buka Menu LinkedIn Sync &rarr;" langsung mengarahkan ke `/superadmin/linkedin-sync?alumni_id={id}&search={nim}`.
  - `LinkedInSyncController` secara otomatis mendeteksi tahun kelulusan target alumni sehingga data alumni pasti termuat dalam hasil kueri tanpa terhalang filter tahun default.
  - Komponen Vue mengaktifkan pencarian otomatis, memberikan sorotan visual cincin hijau emerald (*ring highlight* dengan badge "Profil Dipilih"), dan melakukan *auto-scroll* mulus ke baris alumni terkait.
- **Tombol Aksi Cepat Ikon Mata di LinkedIn Sync**:
  - Pada setiap baris data tabel alumni di modul LinkedIn Sync (`/superadmin/linkedin-sync`), tersedia tombol aksi dengan ikon mata (*Lihat Detail Alumni*).
  - Mengarahkan Super Admin langsung ke halaman Detail Alumni lengkap (`/superadmin/alumni/{id}`) untuk audit lembar jawaban kuesioner universitas, kuesioner prodi, kuesioner atasan, dan tab audit trace LinkedIn.
- **Standarisasi Kolom `skills` & `experience`**:
  - Tabel `biodata` menggunakan kolom terstandarisasi `skills` (daftar keahlian teknis) dan `experience` (riwayat pengalaman kerja), menggantikan penamaan lama (`expert` dan `minat`), terintegrasi dengan view database `v_alumni_profile_summary` dan `v_alumni_audit_rekap`.

---

## 6. Modularisasi Komponen Vue Alumni

Komponen pengisian kuesioner dipecah secara modular untuk memudahkan pemeliharaan kode:

| Nama Komponen | Lokasi File | Peran & Tanggung Jawab |
|---|---|---|
| **Kuesioner (Induk)** | `Pages/Alumni/Kuesioner.vue` | Mengorkestrasi state form Inertia, alur *jump logic*, validasi per seksi, dan persistensi sesi `localStorage`. |
| **Navbar** | `Pages/Alumni/Components/Kuesioner/Navbar.vue` | Header atas dengan logo resmi UKDW dan navigasi tombol Kembali ke Dashboard. |
| **Stepper** | `Pages/Alumni/Components/Kuesioner/Stepper.vue` | Navigasi tahapan bulatan angka 1 s/d N, judul seksi, indikator centang selesai (`✓`), dan *auto-scroll*. |
| **Banner** | `Pages/Alumni/Components/Kuesioner/Banner.vue` | Banner hijau judul seksi aktif (*"Bagian X dari Y"*) dan petunjuk pengisian. |
| **TabelF17** | `Pages/Alumni/Components/Kuesioner/TabelF17.vue` | Tabel komparasi dua sisi instrumen F17 (*Kemampuan Diri* vs *Kontribusi Kampus*), badge komparasi otomatis, dan counter progres aspek. |
| **KartuPertanyaan** | `Pages/Alumni/Components/Kuesioner/KartuPertanyaan.vue` | Renderer butir pertanyaan beserta seluruh variasi template input (`text`, `textarea`, `number`, `single_choice`/`radio`, `radio_input`, `radio_text`, `multiple_choice`/`checkbox`, `dropdown`, `searchable_select`, `rating_5`, `multiple_number`, `date`, `time`, `file`, layout 2 kolom). |
| **Navigasi** | `Pages/Alumni/Components/Kuesioner/Navigasi.vue` | Tombol navigasi desktop melayang (`< Kembali` dan `> Lanjut/Selesai`) serta *fixed bottom bar* di smartphone/mobile. |

---

## Log Pembaruan (Changelog)

- **v2.6 (03 Oktober 2026 - Fitur Sinkronisasi LinkedIn SuperAdmin & Refaktor Driver-Based via .ENV)**:
  - **Superadmin Sinkronisasi LinkedIn**: Memfilter alumni berdasarkan Tahun Kelulusan, 5 metrik KPI analitik (Total, Dengan LinkedIn, Berhasil, Gagal, Dilewati), sinkronisasi individual asinkron, dan sinkronisasi massal per angkatan.
  - **Arsitektur Driver-Based Provider**: Penyedia data dipisahkan menggunakan interface `LinkedInProfileProvider` dengan switch terpusat di `.env` (`LINKEDIN_DRIVER=mock` atau `LINKEDIN_DRIVER=api`).
  - **Kesiapan Server-to-Server API**: Dibuat `ApiLinkedInProvider` yang menginjeksi API Key dan Base URL dari konfigurasi server, menormalisasi respon HTTP ke `LinkedInProfile` DTO, dan menangani error status tanpa membocorkan kredensial.
  - **Dukungan Mock JSON Terstruktur**: `MockLinkedInProvider` membaca `storage/app/mock/linkedin/{username}.json` dengan fallback dinamis berbasis biodata lokal.
  - **Kebijakan Verifikasi Mutlak**: Setiap perusahaan baru yang dibuat melalui sinkronisasi LinkedIn selalu berstatus `status_verifikasi = 'Menunggu Verifikasi'` meskipun data lengkap.
  - **Perlindungan Data Otoritatif & Audit Trail**: NIM, telepon, email pribadi, dan data akademik tidak pernah ditimpa. Jejak sinkronisasi dicatat ke tabel `log_activities`.

- **v2.5 (28 September 2026)**:
  - **Schema Foto Profil**: Kolom `foto` pada tabel `biodata` tercatat di `database/migrations/2026_09_01_000017_create_biodata_table.php` dan terhubung ke direktori `public/uploads/profile/`.
  - **Refaktor Relasi Yudisium & Skripsi**: Menghubungkan relasi `yudisium()` pada model `Biodata.php` secara eksplisit untuk membaca judul tugas akhir (`judul_ta`), link publikasi karya ilmiah (`url_publikasi`), dan jenis publikasi.
  - **Agregasi Spasial Beranda (`BerandaController.php`)**: Menghitung sebaran wilayah domisili dan karier alumni serta ringkasan per provinsi berisi daftar nama alumni dan daftar nama instansi/perusahaan untuk hover tooltip peta interaktif.
  - **Modularisasi Komponen Landing Page (`resources/js/components/landing/`)**: Memecah halaman landing page menjadi 10 komponen terisolasi (`hero-section.vue`, `alumni-map-section.vue`, `testimonial-slider-section.vue`, `stats-transformation-section.vue`, `sektor-alumni-section.vue`, `career-pillars-section.vue`, `user-guide-section.vue`, `berkas-section.vue`, `blog-section.vue`, `tentang-section.vue`).
  - **Peta Interaktif 2-Kolom & SweetAlert2 Detail**: Peta Leaflet GeoJSON 38 provinsi di sisi kiri dan daftar alumni 2x2 di sisi kanan dengan filter multi-dimensi, hover tooltip informatif, dan dialog pop-up SweetAlert2 lengkap dengan foto profil, judul skripsi, link publikasi repositori kampus, serta tombol sorot peta.
  - **Etalase Karya & Tugas Akhir**: Menampilkan karya ilmiah dan skripsi unggulan alumni UKDW terhubung langsung dengan repositori kampus.
  - **Standardisasi Maskot Pigo & Aset Visual Branding**: Memusatkan aset maskot resmi di `public/uploads/pigo/`, menambahkan global loader `pigo-loader.vue`, dan menyelaraskan logo resmi UKDW pada seluruh bilah navigasi stakeholder.

- **v2.4**:
  - Menghilangkan seluruh `jump_to` pada seeder (`RefSubpertanyaanDetilSeeder`) agar semua butir kuesioner dapat ditinjau dan diisi secara utuh.
  - Menyusun ulang 10 seksi kuesioner sesuai alur logis baru: `F8` &rarr; `F3` &rarr; `F10` &rarr; `F4` &rarr; `F504` &rarr; `F6` &rarr; `F7` &rarr; `F7A` &rarr; `F18` &rarr; `F12` &rarr; `F14` &rarr; `F15` &rarr; `F17` &rarr; `F2` &rarr; `F16`.
  - Mengimplementasikan proteksi data perusahaan terverifikasi di form karier alumni.
  - Memperbaiki prefill nominal Take Home Pay (`F505`) dan rekomendasi kesesuaian UMR (`F505A`).
  - Mengubah format ekspor data jawaban di Super Admin dari CSV menjadi Spreadsheet Microsoft Excel (`.xls`) dengan styling hijau UKDW dan metadata lengkap.

---

## Manajemen Modul & Peran Pengguna (Roles)

1. **`superadmin`**:
   - Dashboard analitik seluruh universitas.
   - Kelola Pertanyaan Kuesioner Universitas (`/superadmin/pertanyaan`).
   - Kelola Section Kuesioner Universitas (`/superadmin/sections`).
   - Direktori Seluruh Alumni & Audit Detail Hasil Kuesioner (Kuesioner Univ, Kuesioner Prodi, Profil Mahasiswa) (`/superadmin/alumni/{id}`).
   - Unduh Rekap Laporan Jawaban Alumni dalam format Microsoft Excel (`.xls`).
2. **`admin_biro3`**:
   - Dashboard analitik responden kelulusan per periode.
   - Kelola Direktori Alumni tersaring yudisium 'Lulus' berbasis Database View cepat (`/biro3/alumni`).
   - Audit baca Profil Alumni & riwayat hasil LinkedIn; tanpa aksi sinkronisasi (`/biro3/alumni/{id}`).
3. **`admin_fakultas`**:
   - Terikat pada `users.fakultas_id`.
   - Dashboard KPI khusus seluruh prodi dalam fakultas (`/fakultas/dashboard`).
   - Direktori Alumni Fakultas dengan dropdown filter prodi internal (`/fakultas/alumni`).
   - Audit profil, jawaban universitas/prodi, evaluasi atasan, dan riwayat LinkedIn alumni fakultas (`/fakultas/alumni/{id}`).
4. **`admin_prodi`**:
   - Terikat pada `users.prodi_id`.
   - Dashboard KPI khusus program studi (`/prodi/dashboard`).
   - Kelola Butir Pertanyaan Kuesioner Program Studi (`/prodi/pertanyaan`).
   - Kelola Bagian (Section) Kuesioner Program Studi (`/prodi/sections`).
   - Direktori Mahasiswa & Alumni khusus prodi (`/prodi/alumni`).
   - Audit Detail Jawaban & Profil Alumni Program Studi (`/prodi/alumni/{id}`).
5. **`alumni`**:
   - Dashboard kelengkapan data & progres kuesioner (`/alumni/dashboard`).
   - Pengisian Kuesioner Tracer Study Universitas (`/alumni/kuesioner`).
   - Pengisian Kuesioner Khusus Program Studi (`/alumni/kuesioner-prodi`).
   - Pembaruan kontak/domisi dinamis dan karier/perusahaan; master akademik ditampilkan sebagai referensi (`/alumni/profile`).

---

## Dokumen Pemetaan Pertanyaan & Arsitektur Form
Untuk referensi lengkap seluruh butir pertanyaan Tracer Study Dikti (`F1` s/d `F18`, `F24A`, `F24B`), letak komponen Vue, kolom basis data, dan aturan sinkronisasi:
👉 **[DAFTAR_PERTANYAAN_MAPPING.md](DAFTAR_PERTANYAAN_MAPPING.md)**

---

## Panduan Pencarian Cepat Kode (Quick Navigation)

- Ingin mengubah tampilan navigasi utama alumni (Dashboard, Profil, Kuesioner Univ & Prodi)? Buka [`resources/js/Pages/Alumni/Components/Navbar.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Components/Navbar.vue).
- Ingin melihat peta lengkap kode pertanyaan Dikti & letak Vue formnya? Buka [`DAFTAR_PERTANYAAN_MAPPING.md`](DAFTAR_PERTANYAAN_MAPPING.md).
- Ingin mengubah form profil dan karier alumni? Buka [`resources/js/Components/AlumniProfil/FormKarier.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/AlumniProfil/FormKarier.vue).
- Ingin mengubah tampilan/input kuesioner tracer alumni? Buka [`resources/js/Pages/Alumni/Components/Kuesioner/KartuPertanyaan.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Components/Kuesioner/KartuPertanyaan.vue).
- Ingin mengubah tabel perbandingan F17? Buka [`resources/js/Pages/Alumni/Components/Kuesioner/TabelF17.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Components/Kuesioner/TabelF17.vue).
- Ingin melihat logika sinkronisasi data profil vs kuesioner? Buka [`app/Services/Kuesioner/KuesionerSyncService.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Services/Kuesioner/KuesionerSyncService.php).
- Ingin melihat controller kuesioner prodi alumni? Buka [`app/Http/Controllers/Alumni/Kuesioner/KuesionerProdiController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/Alumni/Kuesioner/KuesionerProdiController.php).
- Ingin mengedit tampilan Admin Prodi? Buka folder `resources/js/Pages/AdminProdi/`.
- Ingin mengedit seeder kuesioner program studi? Buka [`database/seeders/ProdiQuestionnaireSeeder.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/database/seeders/ProdiQuestionnaireSeeder.php).


## Panduan dokumentasi dan diagram

| Dokumen | Kegunaan |
| --- | --- |
| [DFD](docs/dfd.md) | Batas sistem, teori Kendall, diagram konteks, Diagram 0, kamus dan registri aliran. |
| [File draw.io](docs/tracer-study-dfd.drawio) | Dua sheet diagram yang dapat diedit dan diimpor ke diagrams.net. |
| [Use case](USE_CASE.md) | Aktor, kebutuhan fungsional dan lingkup akses. |
| [User flow](docs/user-flow.md) | Langkah penggunaan tiap role. |
| [ERD](ERD.md) | Entitas, relasi dan kamus data. |
| [Database views](docs/database-views.md) | Query view dan pemetaan konsumsinya. |
| [Kuesioner](KUESIONER.md) | Struktur bagian dan aturan percabangan. |
| [Pemetaan pertanyaan](DAFTAR_PERTANYAAN_MAPPING.md) | Hubungan kode pertanyaan, profil dan komponen. |
| [Activity diagram](docs/activitydiagram.md) | Alur aktivitas pengguna dan sistem; berbeda dari DFD. |
| [Integrasi LinkedIn](docs/linkedin-mcp-integration.md) | Provider, staging dan peninjauan. |
| [Verifikasi perusahaan](docs/verifikasi-perusahaan-similarity.md) | Normalisasi nama, pencocokan dan konsolidasi. |

Versi PHP package yang diperiksa: Laravel **13.30.0**, adapter Inertia **3.3.1**, PHPUnit **12.5.34**. Persyaratan versi di `composer.json`/`package.json` bukan daftar versi terpasang; gunakan lockfile atau `composer show --direct` untuk memastikannya.

Email evaluasi atasan dikirim dengan aksi terpisah melalui `POST /alumni/kirim-email-atasan`, bukan otomatis setiap profil disimpan. Token dibuat sepanjang 40 karakter dan disimpan pada kolom berkapasitas 64 karakter. Biro III berfokus pada analisis/komunikasi; rute bersama masih mendukung pencatatan perusahaan dan permintaan email evaluasi. Catatan perubahan adalah rekaman historis, bukan spesifikasi perilaku terkini.
