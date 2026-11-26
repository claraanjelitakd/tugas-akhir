# BAB 4: IMPLEMENTASI DAN EVALUASI SISTEM
## Sistem Informasi Tracer Study dan Analitik Mutu Lulusan UKDW

---

## 4.1 Lingkungan Implementasi

Implementasi sistem *Tracer Study* Universitas Kristen Duta Wacana (UKDW) dilaksanakan dengan spesifikasi lingkungan perangkat keras (*hardware*), perangkat lunak (*software*), kerangka kerja (*framework*), serta layanan antarmuka pemrograman aplikasi (*third-party API*) aktual yang dirangkum pada tabel-tabel berikut:

### 4.1.1 Lingkungan Perangkat Keras (*Hardware Environment*)
Pengembangan dan pengujian sistem dijalankan pada komputer pengembang (*development workstation*) dengan rincian spesifikasi perangkat keras pada Tabel 4.1.

#### Tabel 4.1 Spesifikasi Lingkungan Perangkat Keras (*Hardware Environment*)
| No | Komponen Perangkat Keras | Spesifikasi Perangkat | Fungsi & Peran |
|:---:|---|---|---|
| 1 | **Unit Pemroses Sentral (CPU)** | AMD Ryzen 5 / Intel Core i5 (6 Cores / 12 Threads, *Clock Speed* hingga 4.2 GHz) | Eksekusi pemrosesan server backend, kompilasi kode program, dan komputasi kueri basis data. |
| 2 | **Memori Akses Acak (RAM)** | 16 GB DDR4 Dual-Channel (Kecepatan Bus 3200 MHz) | Menjalankan *local server*, *Vite dev server*, DBMS MySQL, dan *background queue worker* secara simultan. |
| 3 | **Media Penyimpanan (Storage)** | Solid State Drive (SSD) NVMe M.2 512 GB (Kecepatan baca/tulis 3500/3000 MB/s) | Penyimpanan berkas proyek, repositori basis data lokal, dependensi pustaka, dan aset statis sistem. |
| 4 | **Perangkat Masukan & Tampilan** | Layar Monitor FHD Resolusi 1920x1080 piksel, Papan Ketik, dan Tetikus (*Mouse*) | Antarmuka visual pengembang untuk pengodean, penataan tata letak responsif antarmuka, dan pengujian interaksi. |

---

### 4.1.2 Lingkungan Perangkat Lunak (*Software Environment*) & Pustaka Dependensi
Lingkungan perangkat lunak pendukung pengembangan mencakup sistem operasi, bahasa pemrograman, kerangka kerja, manajer paket, dan pustaka dependensi yang dirinci pada Tabel 4.2.

#### Tabel 4.2 Spesifikasi Lingkungan Perangkat Lunak (*Software Environment*) & Pustaka Dependensi
| No | Komponen Perangkat Lunak | Versi / Paket Pustaka | Peran & Fungsi dalam Sistem |
|:---:|---|---|---|
| 1 | **Sistem Operasi** | Microsoft Windows 11 Pro 64-bit | Lingkungan dasar eksekusi pengujian dan pengembangan sistem. |
| 2 | **Bahasa Pemrograman Backend** | PHP versi 8.3 | Pemrosesan logika bisnis, validasi data masukan, dan pengolahan kueri basis data. |
| 3 | **Ekstensi PHP Aktif** | `pdo_mysql`, `sqlite3`, `curl`, `mbstring`, `openssl`, `gd`, `bcmath` | Konektivitas database, komunikasi HTTP ke API eksternal, kriptografi keamanan, dan kalkulasi presisi numerik. |
| 4 | **Kerangka Kerja Backend** | Laravel Framework versi 11/13 (`laravel/framework: ^13.17`) | Kerangka kerja utama arsitektur MVC, ORM Eloquent, routing, otentikasi, dan sistem antrean. |
| 5 | **Adaptor Monolitik Modern** | Inertia.js Laravel Adapter (`inertiajs/inertia-laravel: ^3.3`) | Menghubungkan routing controller Laravel dengan komponen halaman Vue tanpa membangun REST API terpisah. |
| 6 | **Bahasa & Kerangka Frontend** | JavaScript (ES2023) / Vue 3 Composition API (`vue: ^3.5.42`) | Pembangunan antarmuka pengguna berbasis komponen reaktif, dinamis, dan terstruktur. |
| 7 | **Adaptor Frontend Client** | Inertia.js Vue 3 Client Adapter (`@inertiajs/vue3: ^3.7.0`) | Manajemen navigasi halaman SPA (*Single Page Application*), penanganan form, dan pemeliharaan state. |
| 8 | **Pustaka Visualisasi Data** | Apache ECharts (`echarts: ^6.1.0`) | Render diagram analitik grafik universitas, prodi, dan atasan (batang, donat, radar, boxplot, scatter). |
| 9 | **Kerangka Kerja Antarmuka (CSS)**| Tailwind CSS versi 4 (`tailwindcss: ^4.3.3`, `@tailwindcss/vite: ^4.0.0`) | Penataan desain tata letak antarmuka modern yang bersih, konsisten, dan responsif. |
| 10 | **Sistem Manajemen Basis Data (DBMS)**| MySQL versi 8.0 Community Server & SQLite versi 3 | MySQL untuk basis data operasional/lokal; SQLite untuk eksekusi pengujian otomatis terisolasi (*in-memory*). |
| 11 | **Alat Kompilasi Aset (*Build Tool*)**| Vite versi 8 (`vite: ^8.0.0`, `@vitejs/plugin-vue: ^6.0.8`, `laravel-vite-plugin: ^3.1`) | Kompilator aset JavaScript, CSS, dan Hot Module Replacement (HMR) saat pengembangan. |
| 12 | **Manajer Dependensi** | Composer versi 2.7+ (PHP) & Node.js versi 20+ / NPM versi 10+ (JS) | Manajemen unduhan paket pustaka dan integrasi modul pihak ketiga. |
| 13 | **Alat Uji & Kualitas Kode** | PHPUnit versi 12 (`phpunit: ^12.5.12`) & Laravel Pint versi 1.27 (`laravel/pint: ^1.27`) | Pengujian fitur otomatis (*automated feature testing*) dan pemformatan standar kode program PSR-12. |

---

### 4.1.3 Layanan Eksternal & Pihak Ketiga (*Third-Party Services*)
Integrasi layanan eksternal yang dimanfaatkan untuk mendukung fungsionalitas otomasi sistem disajikan pada Tabel 4.3.

#### Tabel 4.3 Spesifikasi Layanan Eksternal dan Pihak Ketiga (*Third-Party Services*)
| No | Nama Layanan / API | Penyedia / Protokol | Fungsi & Integrasi Sistem |
|:---:|---|---|---|
| 1 | **Apify REST API** | Apify Cloud (Actor: `harvestapi/linkedin-profile-scraper`) | Penarikan data profil publik LinkedIn alumni (riwayat perusahaan, posisi kerja, wilayah) menggunakan otentikasi *Bearer Token*. |
| 2 | **Protokol Komunikasi Email** | SMTP (*Simple Mail Transfer Protocol*), TLS Port 587 | Pengiriman otomatis tautan token evaluasi atasan, tautan pemulihan kata sandi, dan notifikasi pengingat kuesioner (*blast email reminder*). |

---

## 4.2 Konfigurasi Sistem

### 4.2.1 Konfigurasi Berkas Lingkungan (*Environment Variables*)
Sistem mengisolasi seluruh parameter konfigurasi sensitif (kredensial basis data, kunci enkripsi, dan token API) ke dalam berkas lingkungan `.env`:

```env
APP_NAME="Tracer Study UKDW"
APP_ENV=local
APP_KEY=base64:XyZ...GeneratedApplicationKey...=
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tracer
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tracer.study@staff.ukdw.ac.id
MAIL_PASSWORD=secret-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="tracer.study@staff.ukdw.ac.id"
MAIL_FROM_NAME="Tracer Study UKDW"

# Integrasi LinkedIn Apify Scraper
LINKEDIN_PROVIDER=apify
APIFY_API_TOKEN=apify_api_LiveTokenSecret...
APIFY_ACTOR_ID=harvestapi/linkedin-profile-scraper
APIFY_TIMEOUT_SECONDS=180
```

### 4.2.2 Konfigurasi Antrean Latar Belakang (*Background Queue Worker*)
Operasi I/O berat seperti scraping LinkedIn dan pengiriman email blast massal didelegasikan ke *database queue* agar request web tetap responsif:
```bash
php artisan queue:work --queue=default,scraping,emails --tries=3 --timeout=180
```

---

### 4.2.3 Konfigurasi Parameter Bisnis dan Matriks Indikator Kinerja

Selain konfigurasi infrastruktur dasar pada berkas `.env`, sistem memisahkan aturan bisnis, formula indikator mutu, dan batas ambang komputasi ke dalam berkas konfigurasi khusus (*dedicated configuration files*) dan konstanta terpusat agar mudah diaudit tanpa harus mengubah kode logika utama (*business logic decoupling*):

#### 1. Konfigurasi Formula dan Matriks Bobot IKU S1 (`config/iku.php`)
Sistem mengimplementasikan Keputusan Menteri Pendidikan, Kebudayaan, Riset, dan Teknologi terkait Indikator Kinerja Utama Perguruan Tinggi (IKU 1: Lulusan Mendapat Pekerjaan yang Layak). Parameter dan matriks bobot luaran karier lulusan diisolasi pada berkas `config/iku.php`:

```php
return [
    's1' => [
        'aee_ideal'                  => 25.0,  // Target ideal Angka Efisiensi Edukasi (AEE)
        'aee_status_target'          => 60.0,  // Ambang batas persentase kelulusan tepat waktu
        'ump_multiplier'             => 1.2,   // Pengali ambang batas upah layak (1,2 x UMP Provinsi)
        'slovin_error'               => 0.023, // Margin of error (2,3%) rumus Slovin target responden
        'weight_confirmation_status' => 'needs_biro3_confirmation',
        'weights' => [
            'study_continuation'                  => 0.6, // Lanjut studi S2/Profesi
            'before_graduation_above_ump'         => 1.0, // Kerja sebelum lulus (>= 1,2 UMP)
            'before_graduation_below_ump'         => 0.6, // Kerja sebelum lulus (< 1,2 UMP)
            'employee_after_under_6_above_ump'    => 1.0, // Karyawan <= 6 bln (>= 1,2 UMP)
            'employee_after_6_under_12_above_ump' => 0.8, // Karyawan 6-12 bln (>= 1,2 UMP)
            'employee_after_under_12_below_ump'   => 0.6, // Karyawan <= 12 bln (< 1,2 UMP)
            'founder_after_under_6_above_ump'     => 1.2, // Wirausaha <= 6 bln (>= 1,2 UMP)
            'founder_after_6_plus_above_ump'      => 1.0, // Wirausaha > 6 bln (>= 1,2 UMP)
            'founder_after_under_6_below_ump'     => 0.8, // Wirausaha <= 6 bln (< 1,2 UMP)
            'founder_after_6_plus_below_ump'      => 0.6, // Wirausaha > 6 bln (< 1,2 UMP)
            'freelancer_after_under_6_above_ump'  => 0.5, // Freelance <= 6 bln (>= 1,2 UMP)
            'freelancer_after_6_plus_above_ump'   => 0.4, // Freelance > 6 bln (>= 1,2 UMP)
            'freelancer_after_under_6_below_ump'  => 0.3, // Freelance <= 6 bln (< 1,2 UMP)
            'freelancer_after_6_plus_below_ump'   => 0.2, // Freelance > 6 bln (< 1,2 UMP)
        ],
    ],
];
```

#### 2. Konfigurasi Driver Sinkronisasi Profil LinkedIn (`config/linkedin.php` & `config/services.php`)
Sistem menyediakan mekanisme *failover* dan pengalihan driver profil profesional antara penarikan live via Apify Cloud Actor dan pengujian terisolasi berbasis *fixture snapshot* JSON offline:

```php
return [
    'driver'       => env('LINKEDIN_DRIVER', 'mock'), // Opsi: 'mock', 'apify', 'mock_apify'
    'mock_path'    => env('LINKEDIN_MOCK_PATH', storage_path('app/mock/apify')),
    'api_base_url' => env('LINKEDIN_API_BASE_URL'),
    'api_key'      => env('LINKEDIN_API_KEY'),
    'actor_id'     => env('APIFY_LINKEDIN_ACTOR_ID', 'harvestapi/linkedin-profile-scraper'),
    'timeout'      => 180, // Batas maksimal eksekusi request scraper (3 menit)
];
```

#### 3. Matriks Ambang Skor Verifikasi Perusahaan (`PerusahaanVerificationService`)
Untuk menjaga kebersihan data master instansi alumni dari input ganda, parameter ambang batas (*threshold*) kemiripan nama diatur dengan bobot bertingkat:
* **Tingkat 1 (Nama Bersih Identik):** Skor `95` (Pembersihan sufiks badan usaha seperti PT, CV, Yayasan, Tbk).
* **Tingkat 2 (Akronim/Inisial Sama):** Skor `88` (Pencocokan inisial huruf kapital nama instansi).
* **Tingkat 3 (Substring Terkandung):** Skor `75` hingga `90` (Berdasarkan rasio panjang frasa yang cocok).
* **Tingkat 4 (Jarak Levenshtein):** Skor `78` (Toleransi kesalahan ketik/typo $\le 2$ karakter edit).
* **Batas Ambang Rekomendasi:** Skor $\ge 75$ ditampilkan sebagai kandidat rekomendasi verifikasi ke admin.

#### 4. Standar 7 Aspek Kompetensi Pengguna Lulusan (Evaluasi Atasan)
Instrumen survei kinerja atasan dibakukan sesuai dengan 7 aspek kompetensi standar Kemendikbudristek pada skala Likert 1–5:
1. Etika dan Moral (Integritas)
2. Keahlian Berdasarkan Bidang Ilmu (Profesionalisme)
3. Kemampuan Bahasa Inggris
4. Penggunaan Teknologi Informasi
5. Kemampuan Komunikasi
6. Kerja Sama Tim (*Teamwork*)
7. Pengembangan Diri (*Lifelong Learning*)

---

### 4.2.4 Pemetaan Konfigurasi Arsitektur Perangkat Lunak Lintas Lapisan

Tabel konfigurasi dipetakan ke dalam 4 kolom standar: **Fitur & Role**, **Route & Controller**, **Service**, dan **Frontend** tanpa menggunakan simbol bullet sehingga pas pada margin kertas A4 Portrait.

#### Tabel 4.4 Konfigurasi Modul Autentikasi dan Manajemen Akun Pengguna
| Fitur & Role | Route & Controller | Service | Frontend |
|---|---|---|---|
| **Login dan Logout**<br>(Semua Role) | `/login`, `/logout`<br>`LoginController` | `AuthGuardManager`<br>(Otentikasi multi-guard & redirect role) | `Otentikasi/Login.vue`<br>`AuthBackdrop.vue` |
| **Ganti Kata Sandi Wajib**<br>(Pengguna Baru) | `/ubah-kata-sandi`<br>`UbahKataSandiController` | `SecurityCredentialService`<br>(Validasi sandi min 12 karakter) | `Otentikasi/UbahKataSandi.vue`<br>`AuthBackdrop.vue` |
| **Pemulihan Kata Sandi**<br>(Semua Pengguna Publik) | `/lupa-kata-sandi`, `/reset-kata-sandi`<br>`LupaKataSandiController` | `PasswordResetNotificationService`<br>(Pembangkitan token 60 menit) | `Otentikasi/ForgotPassword.vue`<br>`ResetPassword.vue` |
| **Manajemen Akun Multi-Role**<br>(SuperAdmin) | `/superadmin/manajemen-akun`<br>`ManajemenAkunController` | `AccountManagementService`<br>(Otorisasi role pengelola prodi/fakultas) | `SuperAdmin/ManajemenAkun/Index.vue`<br>Modal Tambah/Edit Pengguna |
| **Sinkronisasi Akun Alumni**<br>(SuperAdmin) | `/superadmin/manajemen-akun/sync-alumni`<br>`ManajemenAkunController` | `AlumniAccountSyncService`<br>(Batch provisioning dari yudisium) | `SuperAdmin/ManajemenAkun/Index.vue`<br>Tombol Sinkronisasi Massal |

---

#### Tabel 4.5 Konfigurasi Modul Portal Alumni dan Pengisian Instrumen
| Fitur & Role | Route & Controller | Service | Frontend |
|---|---|---|---|
| **Dashboard Alumni**<br>(Alumni) | `/alumni/dashboard`<br>`DashboardController` | `KelengkapanTracerService`<br>(Audit progres data diri & kuesioner) | `Alumni/Dashboard.vue`<br>Banner Status & Stepper |
| **Pengisian Profil Alumni**<br>(Alumni) | `/alumni/profil`<br>`ProfilController`, `SimpanProfilController` | `AdminAlumniProfileService`<br>`KuesionerSyncService` | `Alumni/Profil/Index.vue`<br>`FormPribadi`, `FormKarier` |
| **Pengajuan Perusahaan Baru**<br>(Alumni) | `/alumni/company`<br>`SimpanProfilController` | `PerusahaanVerificationService`<br>(Pendaftaran instansi draft) | `Alumni/Profil/Index.vue`<br>Modal Tambah Instansi |
| **Pengisian Kuesioner Universitas**<br>(Alumni) | `/alumni/kuesioner`<br>`KuesionerController`, `SimpanJawabanController` | `KelengkapanTracerService`<br>`F3AnswerParser` (branching kerja F8) | `Alumni/Kuesioner.vue`<br>`KartuPertanyaan`, `TabelF17` |
| **Pengisian Kuesioner Program Studi**<br>(Alumni) | `/alumni/kuesioner-prodi`<br>`KuesionerProdiController` | `KuesionerSyncService`<br>(Pemuatan instrumen evaluasi prodi) | `Alumni/KuesionerProdi.vue`<br>`KartuPertanyaanProdi` |

---

#### Tabel 4.6 Konfigurasi Modul Evaluasi Pengguna Lulusan (Atasan)
| Fitur & Role | Route & Controller | Service | Frontend |
|---|---|---|---|
| **Survei Kinerja Lulusan**<br>(Atasan / Publik) | `/evaluasi-atasan/{token}`<br>`EvaluasiAtasanController` | `AdminAlumniProfileService`<br>`KuesionerSyncService` (validasi token) | `Public/EvaluasiAtasanForm.vue`<br>`EvaluasiAtasanNotFound.vue` |
| **Direktori Master Atasan**<br>(SuperAdmin, Biro 3, Fakultas, Prodi) | `.../atasan`<br>`DaftarAtasanController` | `AtasanDirectoryQueryService`<br>(Filter cakupan scope role) | `Shared/DataAtasan/Index.vue`<br>Tabel Direktori Atasan |
| **Kelola Instrumen Atasan**<br>(SuperAdmin) | `/superadmin/evaluasi-atasan`<br>`KelolaEvaluasiAtasanController` | `QuestionDefaultOptionsService`<br>(7 aspek kompetensi Dikti) | `SuperAdmin/EvaluasiAtasan/Index.vue`<br>Form Aspek Kompetensi |

---

#### Tabel 4.7 Konfigurasi Modul Manajemen Alumni, Komunikasi, dan Verifikasi
| Fitur & Role | Route & Controller | Service | Frontend |
|---|---|---|---|
| **Direktori Data Alumni**<br>(SuperAdmin, Biro 3, Fakultas, Prodi) | `.../alumni`<br>`DaftarAlumniController` | `AlumniDirectoryQueryService`<br>`KelengkapanTracerService` | `SuperAdmin/Alumni/Index.vue`<br>`AlumniDirectoryShared.vue` |
| **Lembar Detail Alumni Terpadu**<br>(SuperAdmin, Biro 3, Fakultas, Prodi) | `.../alumni/{id}`<br>`DetailAlumniController` | `UnifiedDetailAlumniService`<br>`AdminAlumniProfileService` | `SuperAdmin/Alumni/Show.vue`<br>`AlumniDetailShared.vue` |
| **Komunikasi Email & WhatsApp**<br>(SuperAdmin, Biro 3, Fakultas, Prodi) | `.../blast-email`, `.../send-email`<br>`DaftarAlumniController` | `SendBlastReminderEmailJob`<br>(Queue worker & link WhatsApp) | Modal Blast Email Reminder<br>Tombol WhatsApp Otomatis |
| **Verifikasi & Rekomendasi PT**<br>(SuperAdmin, Fakultas, Prodi) | `.../perusahaan`<br>`VerifikasiPerusahaanController` | `PerusahaanVerificationService`<br>(Multi-pass similarity matching) | `SuperAdmin/Perusahaan/Index.vue`<br>Modal Rekomendasi Kemiripan |
| **Sinkronisasi LinkedIn Apify**<br>(SuperAdmin, Fakultas, Prodi) | `.../linkedin`<br>`LinkedInSyncController` | `LinkedInProfileService`<br>`ApifyLinkedInProvider` | `SuperAdmin/LinkedIn/Index.vue`<br>Modal Review Staging Hasil |
| **Ekspor Berkas Excel Multi-Sheet**<br>(SuperAdmin, Biro 3, Fakultas, Prodi) | `.../alumni/{id}/export-excel`<br>`DetailAlumniController` | `AlumniTracerExcelExporter`<br>(Streamed HTML format UKDW Green) | Tombol Aksi Ekspor Excel<br>(Direktori & Detail Alumni) |

---

#### Tabel 4.8 Konfigurasi Modul Pengelolaan Master Instrumen, Wilayah, dan Audit Trail
| Fitur & Role | Route & Controller | Service | Frontend |
|---|---|---|---|
| **Kelola Kuesioner Universitas**<br>(SuperAdmin) | `/superadmin/pertanyaan`, `/sections`<br>`DaftarPertanyaanController` | `QuestionDefaultOptionsService`<br>(Manajemen bank soal Dikti) | `SuperAdmin/Pertanyaan/Index.vue`<br>Modal Bank Soal Dikti |
| **Kelola Kuesioner Program Studi**<br>(SuperAdmin, Fakultas, Prodi) | `.../kelola-kuesioner-prodi`<br>`KelolaKuesionerProdiController` | `QuestionDefaultOptionsService`<br>(Penempatan butir capaian prodi) | `Shared/Kuesioner/ProdiKuesionerIndex.vue`<br>Form Pertanyaan Prodi |
| **Master Wilayah & Standar UMP**<br>(SuperAdmin) | `/superadmin/master-wilayah`<br>`MasterWilayahController` | `MasterWilayahService`<br>(Data BPS/ISO & penetapan UMP) | `SuperAdmin/MasterWilayah/Index.vue`<br>Tab Wilayah & Tab UMP |
| **Log Aktivitas (Audit Trail)**<br>(SuperAdmin) | `/superadmin/logs`<br>`LogAktivitasController` | `AuditTrailService`<br>(Pencatatan riwayat perubahan data) | `SuperAdmin/Logs/Index.vue`<br>Modal Riwayat Perubahan |

---

#### Tabel 4.9 Konfigurasi Modul Analitik Grafik (GU, GP, GA)
| Fitur & Role | Route & Controller | Service | Frontend |
|---|---|---|---|
| **Visualisasi 18 Grafik Universitas (GU)**<br>(SuperAdmin, Biro 3, Fakultas, Prodi) | `.../grafik-univ`, `.../grafik-univ/data`<br>`TracerAnalyticsController` | `TracerAnalyticsService`<br>`IkuS1AnalyticsService` (18 indikator Dikti & IKU) | `Shared/Analytics/GrafikUniv.vue`<br>`AnalyticsChartsPanel`, `AnalyticsEChart` |
| **Ekspor Response Rate & Data GU**<br>(SuperAdmin, Biro 3, Fakultas, Prodi) | `.../grafik-univ/export-response-rate`<br>`TracerAnalyticsController` | `TracerAnalyticsService`<br>(Ekspor rekap partisipasi responden) | Tombol "Ekspor Data" di `GrafikUniv.vue` |
| **Visualisasi Grafik Program Studi (GP)**<br>(SuperAdmin, Fakultas, Prodi) | `.../grafik-prodi`, `.../grafik-prodi/data`<br>`ProdiAnalyticsController` | `ProdiAnalyticsService`<br>(Kalkulasi 9 section Likert kurikulum & fasilitas) | `Shared/Analytics/GrafikProdi.vue`<br>`ProdiAnalyticsChartsPanel`, `ProdiPivotTable` |
| **Visualisasi Grafik Evaluasi Atasan (GA)**<br>(SuperAdmin, Biro 3, Fakultas, Prodi) | `.../grafik-atasan`, `.../grafik-atasan/data`<br>`AtasanAnalyticsController` | `AtasanAnalyticsService`<br>(Agregasi skor 7 aspek kompetensi Dikti) | `Shared/Analytics/GrafikAtasan.vue`<br>`AnalyticsEChart.vue`, Kartu Metrik |

---

#### Tabel 4.10 Pemetaan Rincian 18 Indikator Grafik Universitas (GU) dan Komponen Frontend
| Kunci Indikator (`key`) | Nama Indikator Grafik | Komponen Vue Kategori | View Database & Bentuk Visualisasi ECharts |
|---|---|---|---|
| `wait_time` | Waktu Memperoleh Pekerjaan | `05_WaitTime.vue` | View: `v_analytics_wait_time`; Diagram Batang Median & Boxplot Kuartil Prodi |
| `salary_vs_ump` | Gaji vs 1,2× UMP Provinsi | `11_SalaryVsUmp.vue` | View: `v_analytics_salary_ump`; Stacked Bar Memenuhi UMP & Tabel 10 Gaji Tertinggi |
| `employment_status` | Status Pekerjaan dan IKU 1 | `07_EmploymentStatus.vue` | View: `v_analytics_employment_status`; Donut Chart & Bar Distribusi Status Kerja |
| `field_alignment` | Keselarasan Bidang Studi | `12_FieldAlignment.vue` | View: `v_analytics_alignment`; Horizontal Bar 5 Skala Keeratan Hubungan Ilmu |
| `education_alignment`| Keselarasan Tingkat Pendidikan | `13_EducationAlignment.vue` | View: `v_analytics_alignment`; Bar Kesesuaian Jenjang (Lebih Tinggi, Sama, Rendah) |
| `response_rate` | Response Rate Universitas | `01_ResponseRate.vue` | View: `v_analytics_response_rate`; Bar Capaian Target Responden per-Prodi |
| `iku_s1_aee` | AEE S1: Kelulusan Tepat Waktu | `19_IkuS1Aee.vue` | View: `v_iku_s1_aee`; Stacked Bar Rasio Kelulusan Maksimal 4 Tahun per-Prodi |
| `iku_s1_outcomes` | IKU S1: Capaian Luaran Karier | `20_IkuS1Outcomes.vue` | Formula IKU 1; Bar Berbobot Masa Tunggu, Ambang UMP, dan Luaran Usaha |
| `iku_s1_ump_weighted`| IKU S1: Rincian Bobot UMP | `21_IkuS1UmpWeighted.vue` | Formula IKU 1; Bar Bobot Capaian Gaji terhadap Standar UMP Provinsi |
| `learning_methods` | Penekanan Metode Pembelajaran | `02_LearningMethods.vue` | View: `v_analytics_tracer_answers`; Radar Chart & Matriks Likert Metode Perkuliahan |
| `job_search_start` | Waktu Mulai Mencari Kerja | `03_JobSearchStart.vue` | View: `v_analytics_tracer_answers`; Stacked Bar Waktu Pencarian (Sebelum vs Setelah) |
| `job_search_methods` | Cara Mencari Pekerjaan | `04_JobSearchMethods.vue` | View: `v_analytics_tracer_answers`; Horizontal Bar Proporsi Saluran Rekrutmen |
| `application_pipeline`| Pipeline Rekrutmen Kerja | `06_ApplicationPipeline.vue` | View: `v_analytics_tracer_answers`; Scatter Plot Korelasi IPK & Konversi Lamaran |
| `job_search_activity`| Aktivitas Mencari Kerja | `08_JobSearchActivity.vue` | View: `v_analytics_tracer_answers`; Donut Chart Keaktifan Melamar 4 Minggu Terakhir |
| `workplace_type` | Jenis Tempat Kerja | `09_WorkplaceType.vue` | View: `v_analytics_tracer_answers`; Donut Chart Komposisi Instansi (BUMN, Swasta, dll) |
| `further_study` | Studi Lanjut & Sumber Biaya | `10_FurtherStudy.vue` | View: `v_analytics_tracer_answers`; Bar Proporsi Sumber Pembiayaan (Beasiswa/Sendiri) |
| `mismatch_reasons` | Alasan Ketidaksesuaian Kerja | `15_MismatchReasons.vue` | View: `v_analytics_tracer_answers`; Horizontal Bar Faktor Penyebab Kerja Tidak Linear |
| `competency_gap` | Gap Kompetensi Lulusan | `16_CompetencyGap.vue` | View: `v_analytics_tracer_answers`; Bar Selisih Gap & Radar Chart 7 Aspek Dikti |
| `profile_completion` | Kelengkapan Profil Alumni | `17_ProfileCompletion.vue` | View: `v_analytics_profile_completion`; Donut Chart Proporsi Profil Lengkap vs Belum |
| `work_location` | Sebaran Wilayah Kerja | `18_WorkLocation.vue` | View: `v_analytics_work_location`; Peta Geospasial Interaktif Sebaran Provinsi |

---

#### Tabel 4.11 Pemetaan Section Kuesioner Program Studi (GP) dan Komponen Frontend
| Bagian Section Kuesioner Prodi | Komponen Vue Kategori | Tampilan Visual & Fungsi Analitik |
|---|---|---|
| Section 1: Minat & Kontribusi | `01_InterestAndContribution.vue` | Diagram Likert distribusi minat mahasiswa dan kontribusi mata kuliah |
| Section 2: Pengalaman Belajar | `02_LearningExperience.vue` | Diagram evaluasi metode pembelajaran aktif dan atmosfer akademik prodi |
| Section 3: Nilai-Nilai Prodi | `03_StudyProgramValues.vue` | Diagram penilaian penanaman etika profesi, integritas, dan nilai fakultas |
| Section 4: Sarana & Fasilitas | `04_AvailableFacilities.vue` | Diagram tingkat kepuasan fasilitas laboratorium, ruang kelas, dan pustaka |
| Section 5: Dosen & Tenaga Kerja | `05_LecturersAndStaff.vue` | Diagram kepuasan bimbingan dosen tugas akhir dan layanan staf FTI |
| Section 6: Kurikulum & Silabus | `06_CoursesOffered.vue` | Diagram relevansi silabus mata kuliah pilihan terhadap tren teknologi |
| Section 7: Kepuasan Keseluruhan| `07_OverallSatisfaction.vue` | Diagram kepuasan total dan persentase satisfaction rate (skor 4 & 5) |
| Section 8: Rekomendasi Prodi | `08_StudyProgramRecommendation.vue` | Diagram proporsi alumni yang merekomendasikan program studi ke relasi |
| Section 9: Harapan & Saran | `09_HopesAndFeedback.vue` | Daftar ringkasan umpan balik kualitatif serta visualisasi Word Cloud |

---

#### Tabel 4.12 Pemetaan Aspek Kuesioner Evaluasi Atasan (GA) dan Komponen Frontend
| Bagian Visualisasi Analitik Atasan | Komponen Frontend Terintegrasi | Fungsi & Nilai Evaluasi Kinerja |
|---|---|---|
| **Ringkasan Response Rate Atasan** | Kartu Metrik Ringkasan di `GrafikAtasan.vue` | Jumlah atasan dihubungi, survei terisi, dan persentase respon evaluator |
| **Diagram Skor 7 Aspek Kompetensi** | `AnalyticsEChart.vue` di `GrafikAtasan.vue` | Diagram batang rata-rata skor Likert 7 aspek kompetensi standar Dikti |
| **Diagram Tingkat Kesiapan Kerja** | Diagram Donat di `GrafikAtasan.vue` | Visualisasi persentase kesiapan kerja alumni saat memasuki dunia kerja |
| **Matriks Performa Antar-Prodi** | Tabel Komparasi Nilai di `GrafikAtasan.vue` | Tabel tabulasi nilai rata-rata kinerja lulusan per program studi asal |

---

## 4.3 Implementasi Sistem

Subbab ini menyajikan implementasi perangkat lunak sesuai desain sistem yang telah dibuat, yang dituangkan ke dalam modul-modul program berbasis potongan kode (*coding snippets*) prinsip utama yang menunjukkan mekanisme kerja setiap modul secara ringkas dan esensial.

### 4.3.1 Modul Autentikasi dan Otorisasi Multi-Peran
*Berkas: `app/Http/Middleware/RoleMiddleware.php`*

Modul ini bertanggung jawab mengamankan rute sistem agar hanya dapat diakses oleh peran (*role*) yang berhak (SuperAdmin, Admin Biro 3, Admin Fakultas, Admin Prodi, dan Alumni).

#### Potongan Kode Prinsip Utama:
```php
public function handle(Request $request, Closure $next, ...$roles): Response
{
    if (! Auth::check()) {
        return redirect('/login');
    }

    $user = Auth::user();

    // Memeriksa kesesuaian peran pengguna terhadap daftar peran yang diizinkan pada rute
    if (! in_array($user->role, $roles, true)) {
        return abort(403, 'Anda tidak memiliki akses ke halaman ini.');
    }

    return $next($request);
}
```

#### Penjelasan:
Potongan kode di atas merupakan inti dari perlindungan otorisasi sistem. Middleware mengevaluasi atribut `role` pengguna yang terotentikasi terhadap daftar parameter peran yang didefinisikan pada deklarasi rute Laravel. Apabila peran pengguna tidak tercantum dalam daftar izin, eksekusi permintaan langsung dihentikan dengan kode status HTTP `403 Forbidden`, mencegah eskalasi hak akses antar-peran.

---

### 4.3.2 Modul Pengisian Instrumen Kuesioner dan Logika Percabangan
*Berkas: `app/Services/Kuesioner/KelengkapanTracerService.php`*

Modul ini mengevaluasi status kelengkapan kuesioner alumni secara dinamis berdasarkan percabangan (*branching logic*) status pekerjaan pada pertanyaan `F8`.

#### Potongan Kode Prinsip Utama:
```php
$statusKerja = $jawaban['F8'] ?? null;
$mandatoryCodes = ['F8', 'F3', 'F17a1', 'F17b1', 'F21', 'F27'];

// Percabangan status Bekerja (F8=1) atau Wiraswasta (F8=3)
if (in_array((string) $statusKerja, ['1', '3'], true)) {
    $mandatoryCodes = array_merge($mandatoryCodes, ['F504', 'F14', 'F15']);
} 
// Percabangan status Melanjutkan Pendidikan (F8=4)
elseif ((string) $statusKerja === '4') {
    $mandatoryCodes = array_merge($mandatoryCodes, ['F18b', 'F18c', 'F12']);
}

$unfilled = array_filter($mandatoryCodes, fn ($code) => empty($jawaban[$code]));
$isComplete = empty($unfilled);
```

#### Penjelasan:
Potongan kode di atas mengimplementasikan aturan bisnis di mana instrumen kuesioner beradaptasi dengan kondisi riil alumni. Jika responden bekerja, sistem mewajibkan pengisian masa tunggu (`F504`) dan keselarasan studi (`F14`, `F15`). Sebaliknya, jika responden melanjutkan studi, instrumen mengalihkan kewajiban pengisian ke butir perguruan tinggi (`F18b`) dan beasiswa (`F12`), sehingga kuesioner dinilai lengkap secara adil dan valid.

---

### 4.3.3 Modul Rekomendasi dan Verifikasi Perusahaan
*Berkas: `app/Services/Perusahaan/PerusahaanVerificationService.php`*

Modul ini menyelesaikan permasalahan redundansi data master instansi kerja akibat variasi input alumni dengan menerapkan pencocokan berjenjang (*multi-pass similarity matching*).

#### Potongan Kode Prinsip Utama:
```php
// Level 1: Kesamaan identik nama bersih tanpa singkatan badan usaha (Skor: 95)
if ($cleanPending !== '' && $cleanPending === $cleanVerified) {
    $nameScore = 95;
    $reasons[] = 'Nama Identik (Tanpa PT/CV)';
}
// Level 2: Pencocokan akronim / inisial singkatan nama (Skor: 88)
elseif (
    (! empty($verifiedAcronyms) && in_array(strtolower($cleanPending), $verifiedAcronyms, true)) ||
    (! empty($pendingAcronyms) && in_array(strtolower($cleanVerified), $pendingAcronyms, true))
) {
    $nameScore = 88;
    $reasons[] = 'Akronim / Singkatan Cocok';
}
// Level 3: Substring frasa terkandung dengan kalkulasi rasio panjang karakter (Skor: 75-90)
elseif ($cleanPending !== '' && str_contains($cleanVerified, $cleanPending)) {
    $ratio = strlen($cleanPending) / strlen($cleanVerified);
    $nameScore = 75 + (int) ($ratio * 15);
    $reasons[] = 'Nama Mengandung Frasa Yang Sama';
}
// Level 4: Jarak Levenshtein untuk mendeteksi kesalahan ketik/typo (Skor: 78)
elseif (levenshtein($cleanPending, $cleanVerified) <= 2) {
    $nameScore = 78;
    $reasons[] = 'Nama Sangat Mirip (Toleransi Typo)';
}
```

#### Penjelasan:
Logika di atas menyaring nama perusahaan baru yang diajukan alumni terhadap ribuan master terverifikasi. Melalui 4 lapisan filter (nama bersih, akronim inisial, frasa bagian, dan jarak Levenshtein maksimal 2 edit karakter), sistem mampu menyajikan rekomendasi instansi yang relevan dengan skor persentase keyakinan, sehingga admin cukup melakukan *one-click approval/replace* tanpa memasukkan data duplikat.

---

### 4.3.4 Modul Integrasi dan Staging Riwayat LinkedIn Apify
*Berkas: `app/Services/LinkedIn/Providers/ApifyLinkedInProvider.php` & `app/Jobs/ScrapeLinkedInProfileJob.php`*

Modul ini mengotomatiskan pelacakan karier alumni melalui scraping profil LinkedIn publik menggunakan antrean latar belakang (*background job queue*) dan tabel perantara (*staging area*).

#### Potongan Kode Prinsip Utama:
```php
// 1. Pemanggilan API Apify dengan proteksi Bearer Token pada HTTP Header
$response = Http::withToken($this->apiToken)
    ->timeout(180)
    ->post(self::APIFY_BASE_URL . "/{$actorPath}/run-sync-get-dataset-items", [
        'queries' => [$profileUrl],
    ]);

// 2. Isolasi hasil ke tabel staging berstatus 'pending' untuk verifikasi admin
$syncRecord = LinkedinSyncResult::create([
    'biodata_id' => $this->biodata->id,
    'status'     => 'processing',
]);
$dto = $linkedInService->fetchProfile($this->biodata->linkedin_url);
$syncRecord->update([
    'status'       => 'pending',
    'scraped_data' => $dto->toArray(),
]);
```

#### Penjelasan:
Potongan kode ini menjamin dua prinsip rekayasa: keamanan dan integritas. Token API Apify tidak pernah diekspos di frontend melainkan disuntikkan langsung ke header HTTP backend. Hasil ekstraksi JSON posisi pekerjaan aktif tidak langsung menimpa profil primer, melainkan diisolasi pada tabel `linkedin_sync_results` berstatus `pending` agar admin dapat meninjau kebenaran data terlebih dahulu.

---

### 4.3.5 Modul Mesin Analitik Grafik Universitas (GU) dan Masa Tunggu
*Berkas: `app/Services/Analytics/TracerAnalyticsService.php`*

Modul ini memusatkan formula kalkulasi 18 grafik tingkat universitas, salah satunya adalah evaluasi waktu tunggu lulusan terhadap nilai tengah (*median*) populasi kelulusan.

#### Potongan Kode Prinsip Utama:
```php
$median = $this->median($values);

// Evaluasi perbandingan masa tunggu individu terhadap median kohort
$records = $records->map(function (array $record) use ($median): array {
    $record['comparison'] = $median === null
        ? 'Belum dapat dibandingkan'
        : (abs($record['months'] - $median) <= 0.05
            ? 'Sama dengan median'
            : ($record['months'] < $median ? 'Lebih cepat' : 'Lebih lama'));

    return $record;
});

// Agregasi sebaran kuartil (Q1, Median, Q3) per program studi
$prodiStats = $records->groupBy('nama_prodi')->map(function ($prodiRecords, $prodi) {
    $vals = $prodiRecords->pluck('months')->all();
    return [
        'prodi'  => $prodi,
        'q1'     => $this->percentile($vals, 25),
        'median' => $this->percentile($vals, 50),
        'q3'     => $this->percentile($vals, 75),
    ];
});
```

#### Penjelasan:
Potongan kode di atas menunjukkan prinsip kalkulasi matematis objektif: menentukan nilai median masa tunggu kohort, lalu mengelompokkan alumni ke dalam kategori "Lebih cepat", "Sama dengan median", atau "Lebih lama". Agregasi persentil kuartil (Q1, Median/Q2, Q3) disiapkan di tingkat backend agar siap dirender oleh komponen visualisasi Apache ECharts di frontend tanpa membebani browser pengguna.

---

### 4.3.6 Modul Kalkulasi Formula dan Pembobotan Luaran IKU 1 S1
*Berkas: `config/iku.php` & `app/Services/Analytics/IkuS1AnalyticsService.php`*

Modul ini menghitung capaian Indikator Kinerja Utama (IKU 1: Lulusan Mendapat Pekerjaan yang Layak) dengan memadukan data masa tunggu, status wirausaha/karyawan, serta perbandingan gaji terhadap ambang batas 1,2× UMP Provinsi.

#### Potongan Kode Prinsip Utama:
```php
// 1. Evaluasi ambang batas upah layak (1,2 x UMP Provinsi)
$threshold = $ump ? $ump * (float) config('iku.s1.ump_multiplier', 1.2) : null;
$salaryAboveThreshold = ($salary !== null && $threshold !== null) ? $salary > $threshold : null;

// 2. Pembobotan IKU 1 berdasarkan kategori karier, masa tunggu, dan gaji
$weights = config('iku.s1.weights', []);
if ($category === 'Melanjutkan Pendidikan') {
    $weight = (float) ($weights['study_continuation'] ?? 0.6);
} elseif ($category === 'Founder / Co-Founder') {
    $weight = ($months < 6 && $salaryAboveThreshold)
        ? (float) ($weights['founder_after_under_6_above_ump'] ?? 1.2)
        : (float) ($weights['founder_after_under_6_below_ump'] ?? 0.8);
} else { // Kategori Karyawan
    $weight = ($months < 6 && $salaryAboveThreshold)
        ? (float) ($weights['employee_after_under_6_above_ump'] ?? 1.0)
        : (($salaryAboveThreshold) ? (float) ($weights['employee_after_6_under_12_above_ump'] ?? 0.8) : 0.6);
}
```

#### Penjelasan:
Potongan kode di atas mengimplementasikan mesin komputasi IKU 1 yang dinamis dan terstandarisasi. Logika membaca faktor pengali ambang batas upah (`ump_multiplier` sebesar 1,2) dan matriks bobot luaran dari berkas konfigurasi terpusat `config/iku.php`. Alumni yang mendirikan usaha mandiri (wirausaha) dengan penghasilan di atas 1,2× UMP dalam waktu kurang dari 6 bulan memperoleh bobot tertinggi sebesar 1,2 poin, sedangkan karyawan dengan kriteria serupa memperoleh bobot 1,0 poin sesuai standar mutu Kemendikbudristek.

---

### 4.3.7 Modul Analitik Kuesioner Program Studi (GP) dan Tabulasi Likert
*Berkas: `app/Services/Analytics/ProdiAnalyticsService.php`*

Modul ini mengolah respon instrumen prodi (skala Likert 1–5) dari 9 kategori evaluasi kurikulum, dosen, dan fasilitas ke dalam bentuk pivot distribusi persentase.

#### Potongan Kode Prinsip Utama:
```php
// Menghitung frekuensi kemunculan tiap skor skala Likert (1 s.d. 5)
$counts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
foreach ($answers as $val) {
    if (isset($counts[$val])) {
        $counts[$val]++;
    }
}

// Menghitung skor rata-rata (mean) dan tingkat kepuasan (satisfaction rate skor 4 & 5)
$totalRespondents = array_sum($counts);
$mean = $totalRespondents > 0
    ? round(array_sum(array_map(fn ($k, $v) => $k * $v, array_keys($counts), $counts)) / $totalRespondents, 2)
    : 0.0;
$satisfactionRate = $totalRespondents > 0
    ? round((($counts[4] + $counts[5]) / $totalRespondents) * 100, 1)
    : 0.0;
```

#### Penjelasan:
Potongan kode ini menghitung metrik kepuasan lulusan terhadap program studi. Selain nilai rerata (*mean*), sistem mengkalkulasi *satisfaction rate* yang mengukur akumulasi persentase alumni yang menyatakan "Puas" (skor 4) dan "Sangat Puas" (skor 5), menghasilkan data kuantitatif yang siap disajikan pada tabel pivot dan diagram batang prodi.

---

### 4.3.8 Modul Evaluasi Pengguna Lulusan (Atasan) Berbasis Token Privat
*Berkas: `app/Http/Controllers/EvaluasiAtasanController.php`*

Modul ini memfasilitasi pengisian instrumen penilaian kinerja lulusan oleh atasan perusahaan secara langsung tanpa kewajiban login akun, menggunakan token acak berkeamanan tinggi.

#### Potongan Kode Prinsip Utama:
```php
public function show(string $token): Response
{
    // Verifikasi keberadaan token unik dan relasi alumni serta perusahaan
    $evaluasi = EvaluasiAtasan::where('token', $token)
        ->with(['biodata.prodi', 'perusahaan', 'atasan'])
        ->first();

    if (! $evaluasi) {
        return Inertia::render('Public/EvaluasiAtasanNotFound', [
            'message' => 'Tautan formulir evaluasi tidak ditemukan atau kode token tidak valid.',
        ]);
    }

    if ($evaluasi->is_submitted) {
        return Inertia::render('Public/EvaluasiAtasanNotFound', [
            'message' => 'Survei evaluasi ini telah berhasil diisi sebelumnya. Terima kasih.',
        ]);
    }

    return Inertia::render('Public/EvaluasiAtasanForm', ['evaluasi' => $evaluasi]);
}
```

#### Penjelasan:
Potongan kode di atas mengontrol akses publik form atasan. Sistem mencocokkan parameter token unik 64 karakter dari URL email. Jika token tidak ditemukan atau evaluasi telah berstatus terisi (`is_submitted = true`), sistem mengembalikan tampilan pemberitahuan yang aman dan mencegah manipulasi pengisian ulang respon atasan.

---

### 4.3.9 Modul Sinkronisasi Massal Akun Alumni dari Data Akademik
*Berkas: `app/Http/Controllers/SuperAdmin/ManajemenAkunController.php`*

Modul ini mengotomatiskan pembuatan akun alumni secara massal (*batch provisioning*) dari data mahasiswa berstatus lulus pada tabel `data_akademik` tanpa perlu input manual satu per satu.

#### Potongan Kode Prinsip Utama:
```php
DB::transaction(function () use ($unsyncedRecords, &$syncedCount) {
    foreach ($unsyncedRecords as $record) {
        $nim = trim((string) $record->nim);
        $parsed = Biodata::parseNim($nim);
        $prodi = $parsed ? Prodi::where('kode_prodi', $parsed['kode_prodi'])->first() : null;

        // Password default dibangkitkan dari tanggal lahir (format: ddmmyyyy)
        $plainPassword = $record->tanggal_lahir 
            ? Carbon::parse($record->tanggal_lahir)->format('dmY') 
            : 'password123';

        $user = User::firstOrCreate(
            ['username' => $nim],
            [
                'name'     => $record->nama ?: 'Alumni UKDW',
                'email'    => $record->email_pribadi ?: ($nim . '@students.ukdw.ac.id'),
                'password' => Hash::make($plainPassword),
                'role'     => 'alumni',
            ]
        );
        $syncedCount++;
    }
});
```

#### Penjelasan:
Potongan kode di atas menjamin konsistensi pembuatan akun alumni baru dalam satu transaksi basis data atomik (`DB::transaction`). Sistem memetakan program studi asal secara otomatis melalui parsing prefiks NIM, lalu mengenkripsi kata sandi awal menggunakan tanggal lahir mahasiswa dengan format `ddmmyyyy`. Mekanisme ini mempermudah alumni mengakses portal saat pertama kali lulus tanpa hambatan aktivasi yang rumit.

---

### 4.3.10 Modul Ekspor Berkas Excel Multi-Sheet Berformat Resmi UKDW
*Berkas: `app/Services/Export/AlumniTracerExcelExporter.php`*

Modul ini memfasilitasi pengunduhan data rekapitulasi pelacakan alumni dan evaluasi atasan ke dalam format buku kerja (*workbook*) Excel multi-sheet terstruktur dengan skema warna hijau institusi UKDW.

#### Potongan Kode Prinsip Utama:
```php
public static function download(int|string|Biodata $id): StreamedResponse
{
    $alumni = $id instanceof Biodata ? $id : Biodata::findOrFail($id);
    $filename = "Tracer_Study_{$alumni->nim}_{$alumni->nama}.xls";

    return response()->streamDownload(function () use ($alumni) {
        echo self::generateExcelContent($alumni);
    }, $filename, [
        'Content-Type'        => 'application/vnd.ms-excel; charset=UTF-8',
        'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        'Cache-Control'       => 'max-age=0, no-cache, no-store, must-revalidate',
    ]);
}
```

#### Penjelasan:
Potongan kode di atas memanfaatkan teknik *streamed download* dari Laravel untuk menyalurkan berkas Excel langsung ke koneksi HTTP pengguna tanpa menyimpannya ke media penyimpanan sementara server (*temporary storage*). Berkas yang dihasilkan memuat sheet instrumen kuesioner Dikti dan sheet survei atasan yang ditata secara formal menggunakan styling XML/HTML dengan aksen hijau UKDW (`#0D542B`), sehingga hemat konsumsi memori RAM server.

---

### 4.3.11 Modul Distribusi Email Pengingat Massal Antrean Latar Belakang
*Berkas: `app/Jobs/SendBlastReminderEmailJob.php`*

Modul ini menangani pengiriman pesan email pengingat (*blast reminder email*) kepada ratusan alumni yang belum melengkapi tracer study secara terisolasi pada antrean latar belakang.

#### Potongan Kode Prinsip Utama:
```php
public int $tries = 3;
public int $timeout = 600;

public function handle(): void
{
    if (empty($this->biodataIds)) {
        return;
    }

    // Membagi pengiriman menjadi potongan bertahap (chunk) 25 penerima
    $chunks = array_chunk($this->biodataIds, 25);
    foreach ($chunks as $chunk) {
        $alumniList = Biodata::whereIn('id', $chunk)->with('user')->get();
        foreach ($alumniList as $alumni) {
            Mail::to($alumni->user->email)->send(new TracerReminderMail($alumni));
        }
        usleep(500000); // Jeda toleransi 0,5 detik antar-batch untuk stabilitas SMTP
    }
}
```

#### Penjelasan:
Implementasi *job queue worker* ini mencegah terjadinya waktu tunggu habis (*HTTP gateway timeout*) dan pembatasan frekuensi pengiriman oleh server SMTP (*rate limiting*). Pengiriman email dipartisi ke dalam kelompok kecil (*chunk*) berukuran 25 penerima dengan jeda waktu terkontrol, serta dilengkapi batas percobaan ulang (*retry*) sebanyak 3 kali jika terjadi gangguan jaringan sesaat.

---

### 4.3.12 Modul Direktori Alumni dan Pembatasan Cakupan Multi-Peran
*Berkas: `app/Services/Alumni/AlumniDirectoryQueryService.php`*

Modul ini menyatukan kueri pencarian, filter kaskade, dan pembatasan isolasi data (*zero-cross boundary*) agar setiap admin hanya dapat mengakses direktori alumni sesuai lingkup wewenangnya.

#### Potongan Kode Prinsip Utama:
```php
$profileOptionsQuery = DB::table('data_akademik as da');

// Pembatasan cakupan data berbasis hierarki peran pengguna
if ($scopedProdiId) {
    $profileOptionsQuery->where('da.kode_prodi', $scopedProdiId);
} elseif (! empty($scopedProdiIds)) {
    $profileOptionsQuery->whereIn('da.kode_prodi', $scopedProdiIds);
} elseif ($scopedFakultasId) {
    $profileOptionsQuery->join('ref_prodi as rp', 'rp.kode_prodi', '=', 'da.kode_prodi')
        ->where('rp.kode_fakultas', $scopedFakultasId);
}

$profileOptions = $profileOptionsQuery->get();
```

#### Penjelasan:
Logika kueri di atas menerapkan prinsip isolasi otorisasi data di tingkat pangkalan data (*data layer scoping*). Admin Program Studi secara ketat dibatasi hanya pada kode prodinya sendiri, Admin Fakultas dibatasi pada seluruh prodi di bawah naungan fakultasnya melalui kueri relasi join, sedangkan SuperAdmin dan Biro 3 mendapatkan akses penuh lintas universitas, menghindari terjadinya kebocoran informasi antar-fakultas.

---

### 4.3.13 Modul Parsing Jawaban Multi-Format dan Normalisasi Waktu Tunggu F3
*Berkas: `app/Services/Kuesioner/F3AnswerParser.php`*

Modul ini bertanggung jawab menormalisasi variasi jawaban teks mentah instrumen waktu tunggu `F3` menjadi nilai numerik bulan dan segmen fase pencarian kerja yang kanonik.

#### Potongan Kode Prinsip Utama:
```php
public function parse(?string $answer): array
{
    $rawAnswer = trim((string) $answer);
    $normalized = mb_strtolower($rawAnswer);
    $months = $this->extractMonths($rawAnswer);

    if (str_contains($normalized, 'tidak mencari')) {
        return $this->result($rawAnswer, 'not_searching', 'Tidak mencari kerja', $months);
    }
    if (str_contains($normalized, 'sebelum lulus')) {
        return $this->result($rawAnswer, 'before_graduation', 'Sebelum lulus', $months);
    }

    return $this->result($rawAnswer, 'after_graduation', 'Setelah lulus', $months);
}
```

#### Penjelasan:
Potongan kode ini menyelesaikan tantangan inkonsistensi input data teks masa lalu (seperti format teks `"Sebelum lulus ... bulan: 3"` atau `"Setelah lulus: 2 bulan"`). Parser mengekstraksi nilai desimal bulan secara deterministik dan mengklasifikasikannya ke dalam tiga fase baku: tidak mencari kerja, sebelum lulus, atau setelah lulus. Hal ini memastikan lapisan analitik dan pustaka visualisasi grafik menerima angka yang siap dihitung tanpa memicu galat komputasi.
