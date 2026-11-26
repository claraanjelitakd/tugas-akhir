# BAB 4: PEMETAAN KONFIGURASI ARSITEKTUR PERANGKAT LUNAK
## Sistem Informasi Tracer Study dan Analitik Mutu Lulusan UKDW

Dokumen ini menyajikan konfigurasi teknis menyeluruh yang menghubungkan setiap fitur fungsional sistem pada seluruh lapisan arsitektur: **Route & Middleware**, **Controller**, **Service Layer (Logika Bisnis)**, **Database Layer (Model, Tabel, dan View)**, serta **Frontend Layer (Halaman Inertia dan Komponen Vue)**.

Dokumen ini dirancang sebagai rujukan resmi penyusunan Subbab **4.1 (Konfigurasi Arsitektur Perangkat Lunak)** pada naskah Skripsi/Tugas Akhir.

---

## 1. Narasi Pengantar untuk Bab 4 Skripsi

> *Berikut adalah draf kalimat pengantar yang dapat langsung disalin ke awal subbab Bab 4:*
>
> "Implementasi sistem *Tracer Study* Universitas Kristen Duta Wacana (UKDW) dibangun menggunakan pola arsitektur *Model-View-Controller* (MVC) modern yang diperluas dengan *Service Layer Pattern* dan diintegrasikan melalui *Inertia.js Monolith Adapter*. Pemisahan tanggung jawab antar-lapisan diterapkan secara disiplin agar alur pengolahan data terstruktur, mudah diuji, dan aman:
> 1. **Lapisan Rute & Otorisasi (*Route & Middleware Layer*)**: Menjamin bahwa setiap permintaan HTTP diverifikasi terlebih dahulu oleh middleware otentikasi (`auth`), pengecekan sandi awal (`must_change_password`), dan kontrol akses multi-peran (`role`).
> 2. **Lapisan Pengontrol (*Controller Layer*)**: Bertindak sebagai orkestrator yang menerima masukan pengguna (*request*), memvalidasi format data, memanggil logika bisnis domain, dan menentukan respons (*Inertia view*, respons JSON reaktif, atau aliran berkas *streamed download*).
> 3. **Lapisan Layanan Domain (*Service Layer*)**: Memusatkan seluruh aturan bisnis independen, seperti formula kalkulasi 18 grafik universitas, algoritma pencocokan kemiripan nama perusahaan, integrasi penarikan profil LinkedIn Apify, evaluasi kelengkapan kuesioner, hingga pembentukan berkas ekspor multi-sheet.
> 4. **Lapisan Basis Data (*Database Layer*)**: Menyimpan data relasional kanonik melalui *Eloquent ORM*, serta menyediakan *Database Views (Read-Model)* khusus yang mengoptimalkan kueri analitik dan pelaporan tanpa membebani tabel transaksi utama.
> 5. **Lapisan Antarmuka (*Frontend Layer*)**: Menggunakan *Vue 3* yang diorganisasikan secara modular ke dalam halaman *Inertia Page* dan komponen visual (formulir ber-stepper, panel grafik *Apache ECharts*, peta wilayah geospasial, dialog modal, dan tabel *drill-down* data inline).
>
> Pemetaan konfigurasi terpadu antar-lapisan untuk setiap aktivitas dan modul sistem disajikan pada tabel-tabel berikut."

---

## 2. Tabel Pemetaan Konfigurasi Lintas Lapisan

### Tabel 4.1 Konfigurasi Modul Autentikasi dan Manajemen Akun Pengguna

| Fitur / Aktivitas | Aktor / Role | Lapisan Rute & Controller Backend | Lapisan Layanan Bisnis (*Service Layer*) | Lapisan Basis Data (Model, Tabel & View) | Lapisan Antarmuka (*Frontend Vue*) |
|---|---|---|---|---|---|
| **Login dan Logout** | Semua Role (Alumni, Admin, SuperAdmin) | • Route: `/login`, `/logout`<br>• Middleware: `guest` (login), `auth` (logout)<br>• Controller: `LoginController`<br>• Method: `tampilkanHalamanLogin()`, `prosesLogin()`, `prosesLogout()` | Logika autentikasi multi-guard, evaluasi status `must_change_password`, dan pengalihan ke dashboard sesuai peran pengguna | • Model: `User`<br>• Tabel: `users`, `sessions` | • Halaman: `resources/js/Pages/Otentikasi/Login.vue`<br>• Komponen: `AuthBackdrop.vue` |
| **Ganti Kata Sandi Wajib** | Pengguna Pertama Kali Login | • Route: `/ubah-kata-sandi`<br>• Middleware: `auth`<br>• Controller: `UbahKataSandiController`<br>• Method: `tampilkanUbahKataSandi()`, `prosesUbahKataSandi()` | Validasi keamanan kata sandi minimal 12 karakter dan pembaruan status `must_change_password = false` | • Model: `User`<br>• Tabel: `users` | • Halaman: `resources/js/Pages/Otentikasi/UbahKataSandi.vue`<br>• Komponen: `AuthBackdrop.vue` |
| **Pemulihan Kata Sandi (Lupa Password)** | Semua Pengguna (Publik) | • Route: `/lupa-kata-sandi`, `/reset-kata-sandi/{token}`<br>• Middleware: `guest`<br>• Controller: `LupaKataSandiController`<br>• Method: `prosesKirimLinkReset()`, `prosesResetKataSandi()` | `PasswordResetNotificationService`<br>(Pembangkitan token unik berbatas waktu 60 menit dan pengiriman email pemulihan) | • Model: `User`<br>• Tabel: `password_reset_tokens`, `users` | • Halaman: `resources/js/Pages/Otentikasi/ForgotPassword.vue`, `ResetPassword.vue` |
| **Manajemen Akun Multi-Role** | SuperAdmin | • Route: `/superadmin/manajemen-akun`<br>• Middleware: `auth`, `role:superadmin`<br>• Controller: `ManajemenAkunController`<br>• Method: `index()`, `update()`, `sendPasswordResetLink()` | Pengelolaan hak akses peran, aktivasi akun pengelola fakultas/prodi, dan reset manual kata sandi | • Model: `User`, `Prodi`, `RefFakultas`<br>• Tabel: `users`, `prodi`, `ref_fakultas` | • Halaman: `resources/js/Pages/SuperAdmin/ManajemenAkun/Index.vue`<br>• Komponen: Modal Tambah Pengguna, Modal Edit Akun |
| **Sinkronisasi Akun Alumni Massal** | SuperAdmin | • Route: `/superadmin/manajemen-akun/sync-alumni`<br>• Middleware: `auth`, `role:superadmin`<br>• Controller: `ManajemenAkunController`<br>• Method: `syncAlumniAccounts()` | Pembuatan akun alumni otomatis secara massal berbasis data kelulusan akademik yudisium | • Model: `User`, `DataAkademik`, `Biodata`<br>• Tabel: `users`, `data_akademik`, `biodata`, `account_sync_settings` | • Halaman: `SuperAdmin/ManajemenAkun/Index.vue`<br>• Komponen: Tombol Aksi "Sinkronisasi Massal", Badge Status Sinkronisasi |

---

### Tabel 4.2 Konfigurasi Modul Portal Alumni dan Pengisian Instrumen

| Fitur / Aktivitas | Aktor / Role | Lapisan Rute & Controller Backend | Lapisan Layanan Bisnis (*Service Layer*) | Lapisan Basis Data (Model, Tabel & View) | Lapisan Antarmuka (*Frontend Vue*) |
|---|---|---|---|---|---|
| **Dashboard Alumni** | Alumni | • Route: `/alumni/dashboard`<br>• Middleware: `auth`, `role:alumni`, `must_change_password`<br>• Controller: `DashboardController` (Alumni)<br>• Method: `tampilkanDashboard()` | `KelengkapanTracerService`<br>(Audit persentase kelengkapan data diri dan status kuesioner) | • Model: `Biodata`, `AlumniAuditRekap`<br>• View: `v_alumni_audit_rekap`, `v_alumni_tracer_prodi_status` | • Halaman: `resources/js/Pages/Alumni/Dashboard.vue`<br>• Komponen: Banner Sambutan, Kartu Status Kuesioner, Stepper Kelengkapan |
| **Pengisian Profil Alumni** | Alumni | • Route: `/alumni/profil`<br>• Middleware: `auth`, `role:alumni`<br>• Controller: `ProfilController`, `SimpanProfilController`<br>• Method: `tampilkanHalamanProfil()`, `simpanPerubahanProfil()` | `AdminAlumniProfileService`<br>`KuesionerSyncService`<br>(Penyusunan opsi wilayah hierarkis, normalisasi profil, dan sinkronisasi jawaban profil) | • Model: `Biodata`, `DataAkademik`, `Yudisium`, `Perusahaan`, `Atasan`, `Propinsi`, `Kabupaten`<br>• Tabel: `biodata`, `data_akademik`, `yudisium`, `perusahaan`, `atasan`<br>• View: `v_alumni_kuesioner_autofill` | • Halaman: `resources/js/Pages/Alumni/Profil/Index.vue`<br>• Komponen: `FormPribadi.vue`, `FormAkademik.vue`, `FormKarier.vue`, `Stepper.vue` |
| **Pengajuan Perusahaan Baru** | Alumni | • Route: `/alumni/company`<br>• Middleware: `auth`, `role:alumni`<br>• Controller: `SimpanProfilController`<br>• Method: `tambahPerusahaanBaru()` | Pendaftaran entitas instansi baru dengan status awal *Menunggu Verifikasi* dan penautan ke profil alumni | • Model: `Perusahaan`, `Biodata`<br>• Tabel: `perusahaan`, `biodata` | • Komponen: Modal Form "Tambah Instansi Baru" (terintegrasi di `Alumni/Profil/Index.vue`) |
| **Pengisian Kuesioner Universitas** | Alumni | • Route: `/alumni/kuesioner`<br>• Middleware: `auth`, `role:alumni`<br>• Controller: `KuesionerController`, `SimpanJawabanController`<br>• Method: `tampilkanKuesioner()`, `simpanJawabanKuesioner()` | `KelengkapanTracerService`<br>`KuesionerSyncService`<br>`F3AnswerParser`<br>(Penerapan branching logic status pekerjaan, penentuan soal wajib dinamis, dan flattening jawaban JSON) | • Model: `KuesionerSection`, `KuesionerPertanyaan`, `KuesionerJawaban`, `QuestionMapping`<br>• Tabel: `kuesioner_*`<br>• View: `v_alumni_tracer_univ_status` | • Halaman: `resources/js/Pages/Alumni/Kuesioner.vue`<br>• Komponen: `KartuPertanyaan.vue`, `TabelF2.vue`, `TabelF17.vue`, `Stepper.vue`, `Navigasi.vue`, `Banner.vue` |
| **Pengisian Kuesioner Program Studi** | Alumni | • Route: `/alumni/kuesioner-prodi`<br>• Middleware: `auth`, `role:alumni`<br>• Controller: `KuesionerProdiController`<br>• Method: `tampilkanKuesionerProdi()`, `simpanJawaban()` | `KuesionerSyncService`<br>(Pemuatan instrumen penempatan prodi dan evaluasi matriks) | • Model: `KuesionerSection`, `KuesionerSectionPertanyaan`, `KuesionerJawaban`, `Prodi`<br>• View: `v_alumni_tracer_prodi_status` | • Halaman: `resources/js/Pages/Alumni/KuesionerProdi.vue`<br>• Komponen: `KartuPertanyaanProdi.vue`, `TabelMatriksProdi.vue`, `Stepper.vue`, `Navigasi.vue` |

---

### Tabel 4.3 Konfigurasi Modul Evaluasi Pengguna Lulusan (Atasan)

| Fitur / Aktivitas | Aktor / Role | Lapisan Rute & Controller Backend | Lapisan Layanan Bisnis (*Service Layer*) | Lapisan Basis Data (Model, Tabel & View) | Lapisan Antarmuka (*Frontend Vue*) |
|---|---|---|---|---|---|
| **Survei Evaluasi Kinerja (Publik)** | Pengguna Lulusan (Atasan) | • Route: `/evaluasi-atasan/{token}`<br>• Middleware: *Tanpa Login (Akses Token Publik)*<br>• Controller: `EvaluasiAtasanController`<br>• Method: `show()`, `store()` | `AdminAlumniProfileService`<br>`KuesionerSyncService`<br>(Validasi token privat 64 karakter, pencegahan submit berulang, dan penyimpanan respon atasan) | • Model: `EvaluasiAtasan`, `Atasan`, `Biodata`, `Perusahaan`, `KuesionerJawaban`<br>• Tabel: `evaluasi_atasan`, `atasan`, `kuesioner_jawaban` | • Halaman: `resources/js/Pages/Public/EvaluasiAtasanForm.vue`<br>• Komponen Fallback: `Public/EvaluasiAtasanNotFound.vue` |
| **Direktori Master Atasan** | SuperAdmin, Biro 3, Fakultas, Prodi | • Route: `/superadmin/atasan`, `/biro3/atasan`, dll.<br>• Middleware: `auth`, `role:...`<br>• Controller: `DaftarAtasanController`<br>• Method: `index()`, `store()`, `update()`, `exportExcel()` | `AtasanDirectoryQueryService`<br>(Penerapan pembatasan scope prodi/fakultas/universitas dan pembentukan ringkasan statistik evaluator) | • Model: `Atasan`, `Biodata`, `Perusahaan`, `User`, `Prodi`<br>• Tabel: `atasan`, `biodata`, `perusahaan` | • Halaman: `resources/js/Pages/Shared/DataAtasan/Index.vue`<br>• Komponen: Tabel Direktori Atasan, Modal Edit Kontak Atasan |
| **Kelola Instrumen Evaluasi Atasan** | SuperAdmin | • Route: `/superadmin/evaluasi-atasan`<br>• Middleware: `auth`, `role:superadmin`<br>• Controller: `KelolaEvaluasiAtasanController`<br>• Method: CRUD Section, Pertanyaan, Opsi | `QuestionDefaultOptionsService`<br>(Penyusunan indikator 7 aspek kompetensi dan skala Likert kepuasan atasan) | • Model: `KuesionerSection`, `KuesionerPertanyaan`, `KuesionerOpsi`<br>• Tabel: `kuesioner_section`, `kuesioner_pertanyaan`, `kuesioner_opsi` | • Halaman: `resources/js/Pages/SuperAdmin/EvaluasiAtasan/Index.vue`<br>• Komponen: Form Tambah Aspek, Modal Bank Soal |

---

### Tabel 4.4 Konfigurasi Modul Manajemen Alumni, Komunikasi, dan Verifikasi

| Fitur / Aktivitas | Aktor / Role | Lapisan Rute & Controller Backend | Lapisan Layanan Bisnis (*Service Layer*) | Lapisan Basis Data (Model, Tabel & View) | Lapisan Antarmuka (*Frontend Vue*) |
|---|---|---|---|---|---|
| **Direktori Data Alumni** | SuperAdmin, Biro 3, Fakultas, Prodi | • Route: `/superadmin/alumni`, `/prodi/alumni`, dll.<br>• Middleware: `auth`, `role:...`<br>• Controller: `DaftarAlumniController` (per role)<br>• Method: `index()` | `AlumniDirectoryQueryService`<br>`KelengkapanTracerService`<br>(Filter multi-kriteria: tahun, status kuesioner, status profil, pencarian cepat) | • Model: `Biodata`, `User`, `DataAkademik`, `AlumniAuditRekap`<br>• View: `v_alumni_audit_rekap`, `v_alumni_profile_summary`<br>• Tabel: `biodata`, `users` | • Halaman: `resources/js/Pages/SuperAdmin/Alumni/Index.vue` (dan wrapper per role)<br>• Komponen: `AlumniDirectoryShared.vue`, Modal Filter |
| **Lembar Detail Alumni Terpadu** | SuperAdmin, Biro 3, Fakultas, Prodi | • Route: `/superadmin/alumni/{id}`, dll.<br>• Middleware: `auth`, `role:...`<br>• Controller: `DetailAlumniController` (per role)<br>• Method: `show()`, `updateProfile()` | `UnifiedDetailAlumniService`<br>`AdminAlumniProfileService`<br>(Penyusunan data terpadu lintas tab profil, kuesioner univ, prodi, atasan, dan riwayat LinkedIn) | • Model: `Biodata`, `DataAkademik`, `Perusahaan`, `Atasan`, `KuesionerJawaban`<br>• View: `v_alumni_tracer_univ_status`, `v_alumni_tracer_prodi_status` | • Halaman: `resources/js/Pages/SuperAdmin/Alumni/Show.vue`<br>• Komponen: `AlumniDetailShared.vue`, Tab Profil, Tab Kuesioner Univ, Tab Kuesioner Prodi, Tab Atasan |
| **Komunikasi Alumni (Blast Email & WA)** | SuperAdmin, Biro 3, Fakultas, Prodi | • Route: `.../alumni/blast-email`, `.../send-email`<br>• Middleware: `auth`, `role:...`<br>• Controller: `DaftarAlumniController` (per role)<br>• Method: `blastEmail()`, `sendReminderEmail()` | `SendBlastReminderEmailJob`<br>(Antrean pengiriman email massal dan penyusunan templat tautan WhatsApp Click-to-Chat dinamis) | • Model: `Biodata`, `User`, `LogActivity`<br>• Tabel: `biodata`, `users`, `log_activities` | • Komponen: Modal "Blast Email Reminder", Tombol WhatsApp (di dalam `AlumniDirectoryShared.vue`) |
| **Verifikasi & Rekomendasi Perusahaan** | SuperAdmin, Fakultas, Prodi | • Route: `/superadmin/perusahaan`, dll.<br>• Middleware: `auth`, `role:...`<br>• Controller: `VerifikasiPerusahaanController` (per role)<br>• Method: `index()`, `verify()`, `replace()`, `recommendations()` | `PerusahaanVerificationService`<br>(Prapemrosesan token nama, pencocokan akronim, perbandingan frasa, Levenshtein distance, skor kemiripan, dan transaksi pengalihan relasi) | • Model: `Perusahaan`, `Biodata`, `LogActivity`<br>• Tabel: `perusahaan`, `biodata`, `log_activities` | • Halaman: `resources/js/Pages/SuperAdmin/Perusahaan/Index.vue`<br>• Komponen: Tab Menunggu Verifikasi, Tab Terverifikasi, Modal Rekomendasi Kemiripan Nama |
| **Sinkronisasi LinkedIn (Apify Scraper)** | SuperAdmin, Fakultas, Prodi | • Route: `/superadmin/linkedin`, dll.<br>• Middleware: `auth`, `role:...`<br>• Controller: `LinkedInSyncController`<br>• Method: `syncSingle()`, `showResult()`, `approveResult()`, `rejectResult()` | `LinkedInProfileService`<br>`ApifyLinkedInProvider`<br>`LinkedInProfileMapper`<br>`ScrapeLinkedInProfileJob`<br>(Eksekusi scraping asinkron latar belakang, staging JSON, seleksi posisi aktif, dan plotting) | • Model: `LinkedinSyncResult`, `AlumniLinkedinPlot`, `Biodata`, `Perusahaan`<br>• Tabel: `linkedin_sync_results`, `biodata`<br>• View: `v_alumni_linkedin_plot` | • Halaman: `resources/js/Pages/SuperAdmin/LinkedIn/Index.vue`<br>• Komponen: Modal Review Hasil Staging LinkedIn, Modal Plot Ulang |
| **Ekspor Berkas Excel Multi-Sheet** | SuperAdmin, Biro 3, Fakultas, Prodi | • Route: `.../alumni/{id}/export-excel`<br>• Middleware: `auth`, `role:...`<br>• Controller: `DetailAlumniController`<br>• Method: `exportExcel()` | `AlumniTracerExcelExporter`<br>(Streamed response berkas Excel HTML berformat warna UKDW Green dengan proteksi teks numerik) | • Model: `Biodata`, `KuesionerJawaban`, `EvaluasiAtasan`<br>• View: `v_alumni_audit_rekap` | • Komponen: Tombol "Ekspor Excel" pada direktori dan detail alumni |

---

### Tabel 4.5 Konfigurasi Modul Instrumen Kuesioner, Master Wilayah, dan Audit

| Fitur / Aktivitas | Aktor / Role | Lapisan Rute & Controller Backend | Lapisan Layanan Bisnis (*Service Layer*) | Lapisan Basis Data (Model, Tabel & View) | Lapisan Antarmuka (*Frontend Vue*) |
|---|---|---|---|---|---|
| **Kelola Kuesioner Universitas** | SuperAdmin | • Route: `/superadmin/pertanyaan`, `/sections`<br>• Middleware: `auth`, `role:superadmin`<br>• Controller: `DaftarPertanyaanController`, `KelolaSectionController`<br>• Method: CRUD Section, Pertanyaan, Opsi | `QuestionDefaultOptionsService`<br>(Penyusunan bank soal Dikti, konfigurasi tipe jawaban, visibilitas, urutan, dan branching) | • Model: `KuesionerSection`, `KuesionerPertanyaan`, `KuesionerOpsi`<br>• Tabel: `kuesioner_*`<br>• View: `v_question_mappings` | • Halaman: `resources/js/Pages/SuperAdmin/Pertanyaan/Index.vue`, `SuperAdmin/Section/Index.vue`<br>• Komponen: Modal Bank Soal, Form Opsi Jawaban |
| **Kelola Kuesioner Program Studi** | SuperAdmin, Fakultas, Prodi | • Route: `/superadmin/kelola-kuesioner-prodi`, dll.<br>• Middleware: `auth`, `role:...`<br>• Controller: `KelolaKuesionerProdiController`<br>• Method: CRUD Section, Placement Pertanyaan | `QuestionDefaultOptionsService`<br>(Pengelolaan bank soal prodi dan penempatan butir pertanyaan spesifik capaian lulusan prodi) | • Model: `KuesionerSection`, `KuesionerSectionPertanyaan`, `KuesionerPertanyaan`, `Prodi`<br>• Tabel: `kuesioner_*` | • Halaman: `resources/js/Pages/Shared/Kuesioner/ProdiKuesionerIndex.vue`<br>• Komponen: Form Pertanyaan Prodi, Modal Bank Soal Prodi |
| **Master Wilayah & Ketetapan UMP** | SuperAdmin | • Route: `/superadmin/master-wilayah`<br>• Middleware: `auth`, `role:superadmin`<br>• Controller: `MasterWilayahController`<br>• Method: CRUD Wilayah, `storeOrUpdateUmp()`, `copyYearUmp()` | Pembaruan data geospasial BPS/ISO dan duplikasi penetapan nominal standar UMP 38 provinsi per tahun | • Model: `Propinsi`, `Kabupaten`, `RefNegara`, `Ump`<br>• Tabel: `propinsi`, `kabupaten`, `ref_negara`, `ump` | • Halaman: `resources/js/Pages/SuperAdmin/MasterWilayah/Index.vue`<br>• Komponen: Tab Provinsi, Tab Kabupaten, Tab Negara, Tab UMP |
| **Log Aktivitas (Audit Trail)** | SuperAdmin | • Route: `/superadmin/logs`<br>• Middleware: `auth`, `role:superadmin`<br>• Controller: `LogAktivitasController`<br>• Method: `index()` | Pembacaan kronologis riwayat audit operasional sistem dari seluruh modul bisnis | • Model: `LogActivity`, `User`<br>• Tabel: `log_activities`, `users` | • Halaman: `resources/js/Pages/SuperAdmin/Logs/Index.vue`<br>• Komponen: Tabel Riwayat Aktivitas, Modal Detail Perubahan Data (*Old vs New Values*) |

---

### Tabel 4.6 Konfigurasi Lengkap Komponen Frontend: 18 Grafik Universitas (GU)

* **Halaman Utama (Inertia View)**: `resources/js/Pages/Shared/Analytics/GrafikUniv.vue`
* **Controller**: `TracerAnalyticsController.php` (Method: `index()`, `data()`, `exportResponseRate()`)
* **Service Backend**: `TracerAnalyticsService.php` dan `IkuS1AnalyticsService.php`
* **Komponen Mesin Bersama**:
  * `AnalyticsChartsPanel.vue` (Komponen pemilih/dispatcher kategori grafik aktif)
  * `AnalyticsEChart.vue` (Komponen pembungkus visualisasi Apache ECharts)
  * `AnalyticsTabbedContainer.vue` (Wadah tab grafik di atas dan tabel rincian data alumni di bawah)
  * `analyticsChartOptions.js` (Definisi palet warna institusi UKDW `#0D542B` dan opsi chart)

| Kunci Indikator (`key`) | Nama Indikator Grafik | Berkas Komponen Vue Kategori (`resources/js/components/analytics/categories/`) | View / Sumber Data Database | Bentuk Visualisasi Diagram ECharts & Data Rincian |
|---|---|---|---|---|
| `wait_time` | Waktu Memperoleh Pekerjaan | **`05_WaitTime.vue`** | View: `v_analytics_wait_time`, F502, F504, F506 | Diagram Batang Perbandingan Median Kohort & Statistik Boxplot Kuartil per-Prodi + Tabel Rincian Alumni |
| `salary_vs_ump` | Gaji vs 1,2× UMP Provinsi | **`11_SalaryVsUmp.vue`** | View: `v_analytics_salary_ump`, `biodata.gaji`, `ump.besaran` | Stacked Bar Komposisi Memenuhi vs Di Bawah UMP & Median Gaji per-Prodi + Tabel 10 Gaji Tertinggi |
| `employment_status` | Status Pekerjaan dan IKU 1 | **`07_EmploymentStatus.vue`** | View: `v_analytics_employment_status`, F8 | Donut Chart & Diagram Batang Distribusi Bekerja, Wirausaha, Studi Lanjut, Mencari Kerja |
| `field_alignment` | Hubungan Bidang Studi dgn Kerja | **`12_FieldAlignment.vue`** | View: `v_analytics_alignment`, F14 | Horizontal Bar 5 Skala Keeratan Hubungan Ilmu (Sangat Erat s/d Tidak Sama Sekali) |
| `education_alignment`| Keselarasan Tingkat Pendidikan | **`13_EducationAlignment.vue`** | View: `v_analytics_alignment`, F15 | Diagram Batang Kesesuaian Jenjang Pendidikan (Setingkat Lebih Tinggi, Sama, Lebih Rendah) |
| `response_rate` | Response Rate Universitas | **`01_ResponseRate.vue`** | View: `v_analytics_response_rate`, `v_analytics_population` | Diagram Batang Capaian Target Responden per-Prodi & Indikator Partisipasi Kampus |
| `iku_s1_aee` | AEE S1: Kelulusan Tepat Waktu | **`19_IkuS1Aee.vue`** | View: `v_iku_s1_aee`, `v_iku_s1_lulusan` | Stacked Bar Horizontal Rasio Kelulusan Maksimal 4 Tahun per Program Studi |
| `iku_s1_outcomes` | IKU S1: Capaian Luaran Karier | **`20_IkuS1Outcomes.vue`** | Formula IKU 1, View Akademik & Tracer | Diagram Batang Berbobot Capaian Masa Tunggu, Ambang UMP, dan Posisi Usaha Lulusan |
| `iku_s1_ump_weighted`| IKU S1: Rincian Bobot UMP | **`21_IkuS1UmpWeighted.vue`** | Formula IKU 1, Master UMP Provinsi | Diagram Batang Status UMP per Kategori Karier & Tabel Formula Individu Responden |
| `learning_methods` | Penekanan Metode Pembelajaran | **`02_LearningMethods.vue`** | F21–F27, View: `v_analytics_tracer_answers` | **Diagram Radar (*Radar Chart*)** dan Matriks Likert Metode Perkuliahan Program Studi |
| `job_search_start` | Waktu Mulai Mencari Kerja | **`03_JobSearchStart.vue`** | F3, View: `v_analytics_tracer_answers` | Stacked Bar Distribusi Waktu Pencarian (Sebelum Lulus vs Setelah Lulus) |
| `job_search_methods` | Cara Mencari Pekerjaan | **`04_JobSearchMethods.vue`** | F4 (Multi-pilihan), View Tracer Answers | Diagram Batang Horizontal Proporsi Saluran Rekrutmen (Koneksi, Job Fair, Daring) |
| `application_pipeline`| Pipeline Rekrutmen Kerja | **`06_ApplicationPipeline.vue`** | F6, F7, F7A, View Tracer Answers | **Scatter Plot Korelasi Nilai IPK** & Diagram Batang Konversi Lamaran, Respons, Wawancara |
| `job_search_activity`| Aktivitas Mencari Kerja | **`08_JobSearchActivity.vue`** | F10, View Tracer Answers | Diagram Donat Status Keaktifan Mencari Kerja dalam 4 Minggu Terakhir |
| `workplace_type` | Jenis Tempat Kerja | **`09_WorkplaceType.vue`** | F11, View Tracer Answers | Diagram Donat Komposisi Instansi Pemerintah, BUMN, Swasta, Non-Profit, Wirausaha |
| `further_study` | Studi Lanjut & Sumber Biaya | **`10_FurtherStudy.vue`** | F18, View Tracer Answers | Diagram Batang Proporsi Sumber Pembiayaan Studi Lanjut (Beasiswa vs Biaya Sendiri) |
| `mismatch_reasons` | Alasan Ketidaksesuaian Kerja | **`15_MismatchReasons.vue`** | F16 (Multi-pilihan), View Tracer Answers | Diagram Batang Horizontal Frekuensi Faktor Penyebab Pekerjaan Tidak Linear |
| `competency_gap` | Gap Kompetensi Lulusan | **`16_CompetencyGap.vue`** | F17a1–F17b7, View Tracer Answers | **Diagram Batang Selisih Gap & Diagram Radar** 7 Aspek Kompetensi Dikti |
| `profile_completion` | Kelengkapan Profil Alumni | **`17_ProfileCompletion.vue`** | View: `v_analytics_profile_completion` | Diagram Donat Proporsi Alumni dengan Status Profil Lengkap vs Belum Lengkap |
| `work_location` | Sebaran Wilayah Kerja | **`18_WorkLocation.vue`** | View: `v_analytics_work_location`, `AnalyticsMap.vue` | **Peta Geospasial Interaktif** Sebaran Provinsi Penempatan Kerja Alumni |

---

### Tabel 4.7 Konfigurasi Komponen Frontend: Grafik Program Studi (GP)

* **Halaman Utama (Inertia View)**: `resources/js/Pages/Shared/Analytics/GrafikProdi.vue`
* **Controller**: `ProdiAnalyticsController.php` (Method: `index()`, `data()`)
* **Service Backend**: `ProdiAnalyticsService.php`
* **Komponen Mesin Bersama**:
  * `ProdiAnalyticsChartsPanel.vue` (Komponen dispatcher kategori section prodi aktif)
  * `ProdiPivotTable.vue` (Tabel pivot tabulasi silang jawaban kuesioner)
  * `ChoiceQuestionsChart.vue` (Komponen diagram batang pertanyaan pilihan berganda)
  * `prodiChartOptions.js` (Pengaturan opsi tema grafik prodi)

| Bagian Section Kuesioner Prodi | Berkas Komponen Vue (`resources/js/components/prodi-analytics/categories/`) | Sumber Data Basis Data | Tampilan Visual & Fungsi Analitik |
|---|---|---|---|
| Section 1: Minat & Kontribusi | **`01_InterestAndContribution.vue`** | Tabel: `kuesioner_jawaban`, `kuesioner_pertanyaan` (prodi) | Diagram Likert distribusi minat mahasiswa dan kontribusi mata kuliah terhadap bidang ilmu |
| Section 2: Pengalaman Belajar | **`02_LearningExperience.vue`** | Kuesioner Jawaban Placement Prodi | Diagram evaluasi metode pembelajaran aktif dan atmosfer akademik program studi |
| Section 3: Nilai-Nilai Prodi | **`03_StudyProgramValues.vue`** | Kuesioner Jawaban Placement Prodi | Diagram penilaian penanaman etika profesi, integritas, dan nilai khas fakultas |
| Section 4: Sarana & Fasilitas | **`04_AvailableFacilities.vue`** | Kuesioner Jawaban Placement Prodi | Diagram tingkat kepuasan fasilitas laboratorium, ruang kelas praktikum, dan pustaka |
| Section 5: Dosen & Tenaga Kerja | **`05_LecturersAndStaff.vue`** | Kuesioner Jawaban Placement Prodi | Diagram kepuasan bimbingan dosen tugas akhir dan layanan administrasi program studi |
| Section 6: Kurikulum & Silabus | **`06_CoursesOffered.vue`** | Kuesioner Jawaban Placement Prodi | Diagram relevansi silabus mata kuliah pilihan terhadap tren teknologi industri kerja |
| Section 7: Kepuasan Keseluruhan| **`07_OverallSatisfaction.vue`** | Kuesioner Jawaban Placement Prodi | Diagram akumulasi kepuasan alumni dan persentase *satisfaction rate* (skor 4 & 5) |
| Section 8: Rekomendasi Prodi | **`08_StudyProgramRecommendation.vue`**| Kuesioner Jawaban Placement Prodi | Diagram proporsi alumni yang merekomendasikan program studi kepada masyarakat luas |
| Section 9: Harapan & Saran | **`09_HopesAndFeedback.vue`** | Kuesioner Jawaban & `AnalyticsWordCloud.vue` | Daftar ringkasan umpan balik kualitatif serta visualisasi awan kata (*word cloud*) |

---

### Tabel 4.8 Konfigurasi Komponen Frontend: Grafik Evaluasi Atasan (GA)

* **Halaman Utama (Inertia View)**: `resources/js/Pages/Shared/Analytics/GrafikAtasan.vue`
* **Controller**: `AtasanAnalyticsController.php` (Method: `index()`, `data()`)
* **Service Backend**: `AtasanAnalyticsService.php`

| Bagian Visualisasi Analitik Atasan | Komponen Frontend Terintegrasi | Sumber Data Basis Data | Fungsi & Nilai Evaluasi Kinerja |
|---|---|---|---|
| **Ringkasan Response Rate Atasan** | Kartu Metrik di `GrafikAtasan.vue` | Tabel: `evaluasi_atasan` (`is_submitted = true`) | Menampilkan jumlah atasan yang dihubungi, jumlah survei terisi, dan persentase respon |
| **Diagram Skor 7 Aspek Kompetensi** | `AnalyticsEChart.vue` di `GrafikAtasan.vue` | Pertanyaan Atasan, Jawaban Evaluasi | Diagram batang rata-rata skor Likert aspek Etika, Keahlian Bidang, Bahasa Inggris, TI, Komunikasi, Kerja Tim, dan Pengembangan Diri |
| **Diagram Tingkat Kesiapan Kerja** | Diagram Donat di `GrafikAtasan.vue` | Butir Soal `tingkat_kesiapan_kerja` | Visualisasi persentase kesiapan kerja alumni saat pertama kali memasuki instansi kerja |
| **Matriks Performa Antar-Prodi** | Tabel Komparasi di `GrafikAtasan.vue` | Agregasi Jawaban Evaluasi per-Prodi | Tabel tabulasi nilai rata-rata performa lulusan yang dikelompokkan berdasarkan program studi asal alumni |
