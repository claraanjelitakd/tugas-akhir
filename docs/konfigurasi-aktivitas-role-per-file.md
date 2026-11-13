# Konfigurasi Aktivitas, Role, dan Pemetaan File Tracer Study

Dokumen ini adalah peta teknis aplikasi berdasarkan source code aktif. Setiap
aktivitas ditelusuri dari route, middleware role, controller, service/query,
model atau database view, sampai halaman dan komponen Vue.

Dokumen ini sengaja menjelaskan file yang dipakai dan sumber datanya. Ia bukan
sekadar daftar route, karena banyak route hanya merupakan variasi scope untuk
aktivitas yang sama.

## 1. Sumber kebenaran dan cara membaca

Sumber utama dokumen ini adalah:

- routes/web.php untuk route dan middleware role.
- app/Http/Controllers untuk orkestrasi request dan response.
- app/Services untuk query domain, normalisasi, agregasi, ekspor, dan integrasi.
- app/Models untuk relasi Eloquent dan accessor data.
- database/migrations untuk tabel dan view runtime.
- resources/js/Pages untuk halaman Inertia.
- resources/js/components untuk komponen yang dipakai lintas halaman.

Dokumentasi lama seperti documentation_arsitektur_file.txt masih memuat
beberapa nama file legacy. Jika ada perbedaan, source code aktif di atas yang
menjadi acuan.

### Role aplikasi

| Nama role | Scope data utama |
|---|---|
| alumni | Profil dan jawaban milik alumni sendiri. |
| admin_prodi | Data alumni, perusahaan, kuesioner, LinkedIn, dan analitik dalam program studi akun. |
| admin_fakultas | Data dalam seluruh program studi fakultas akun. |
| admin_biro3 | Monitoring tingkat universitas, komunikasi alumni, dan pembacaan data; tidak memiliki endpoint sinkronisasi LinkedIn/review sendiri. |
| superadmin | Scope universitas dan seluruh konfigurasi sistem. |
| Publik/evaluator | Mengisi evaluasi atasan melalui token tanpa login. |

### Pola alur umum

    Route + role middleware
        -> Controller
            -> Service / query / model langsung
                -> tabel kanonik atau database view
            -> Inertia render atau JSON / file download
        -> Vue Page
            -> komponen presentasi, chart, form, tabel, atau drill-down

## 2. Matriks aktivitas dan role

| Aktivitas | Alumni | Admin Prodi | Admin Fakultas | Admin Biro 3 | Superadmin | Publik |
|---|---:|---:|---:|---:|---:|---:|
| Login, logout, lupa/reset password | Ya | Ya | Ya | Ya | Ya | - |
| Ganti password wajib | Ya | Ya | Ya | Ya | Ya | - |
| Dashboard | Ya | Ya | Ya | Ya | Ya | - |
| Profil alumni | Penuh | - | - | - | - | - |
| Kuesioner umum tracer | Penuh | - | - | - | - | - |
| Kuesioner khusus prodi | Penuh | - | - | - | - | - |
| Usul/tambah perusahaan | Ya | Ya | Ya | Ya | Ya | - |
| Kirim undangan evaluasi atasan | Ya | Ya | Ya | Ya | Ya | - |
| Direktori dan detail alumni | - | Scope prodi | Scope fakultas | Baca universitas | Penuh | - |
| Export data alumni | - | Detail Excel | Detail Excel | Excel/ZIP | Excel/ZIP | - |
| Data atasan | - | Baca | Baca | Baca | CRUD | - |
| Verifikasi perusahaan | - | Scope prodi | Scope fakultas | - | Semua | - |
| Kelola kuesioner prodi | - | Scope prodi | Scope fakultas | - | Semua prodi | - |
| Kelola kuesioner universitas | - | - | - | - | Ya | - |
| Isi evaluasi atasan | - | - | - | - | - | Ya, memakai token |
| Kelola instrumen evaluasi atasan | - | - | - | - | Ya | - |
| Analitik tracer universitas | - | Scope prodi | Scope fakultas | Universitas | Universitas | - |
| Analitik kuesioner prodi | - | Scope prodi | Scope fakultas | Scope sesuai route | Universitas | - |
| Analitik evaluasi atasan | - | Scope prodi | Scope fakultas | Universitas | Universitas | - |
| Sinkronisasi LinkedIn | - | Scope prodi | Scope fakultas | Baca trace | Semua | - |
| Master wilayah, negara, UMP | - | - | - | - | Ya | - |
| Manajemen akun | - | - | - | - | Ya | - |
| Log aktivitas | - | - | - | - | Ya | - |

Catatan: route POST /alumni/company, POST /alumni/company/request-update, dan
POST /alumni/kirim-email-atasan mengizinkan lima role aplikasi. Endpoint
memakai controller alumni karena kontrak payload dan proses bisnisnya sama.

## 3. Route dan middleware

Semua route terautentikasi berada di dalam auth. Sebagian besar operasi
aplikasi juga berada di dalam must_change_password.

| Kelompok route | Middleware | Controller utama |
|---|---|---|
| /, /evaluasi-atasan/{token} | Publik atau throttle | Tamu/BerandaController, EvaluasiAtasanController |
| /login, /forgot-password, /reset-password | guest | Controller otentikasi |
| /change-password, /logout | auth | Controller otentikasi |
| /alumni/* | role:alumni | Controller di app/Http/Controllers/Alumni |
| /prodi/* | role:admin_prodi | Controller Prodi atau Shared |
| /fakultas/* | role:admin_fakultas | Controller Fakultas atau Shared |
| /biro3/* | role:admin_biro3 | Controller Biro 3 atau controller analitik Shared |
| /superadmin/* | role:superadmin | Controller SuperAdmin atau Shared |

Route analitik, LinkedIn, data atasan, dan pengaturan kuesioner menggunakan
controller yang sama untuk beberapa role, tetapi prefix route menentukan scope
dan controller membaca user aktif untuk membatasi query.

## 4. Backend: controller per file

Controller adalah pengatur alur request. Query atau perhitungan lintas role
dipindahkan ke service, tetapi beberapa halaman konfigurasi Superadmin masih
melakukan query Eloquent atau Query Builder langsung di controller.

### 4.1 Tamu, publik, dan autentikasi

| File | Method penting | Tugas dan sumber data |
|---|---|---|
| app/Http/Controllers/Tamu/BerandaController.php | tampilkanBeranda | Menyusun landing publik, statistik ringkas alumni/perusahaan/prodi. Membaca Biodata, Perusahaan, dan Prodi. |
| app/Http/Controllers/EvaluasiAtasanController.php | index, show, store | Membuka halaman evaluasi, memvalidasi token, mengambil alumni/atasan/perusahaan dan pertanyaan aktif, lalu menyimpan evaluasi publik. Memakai AdminAlumniProfileService dan KuesionerSyncService. |
| app/Http/Controllers/Otentikasi/LoginController.php | tampilkanHalamanLogin, prosesLogin, prosesLogout | Login multi-role, redirect dashboard, penanganan password bawaan, dan logout. Membaca User serta DataAkademik. |
| app/Http/Controllers/Otentikasi/LupaKataSandiController.php | tampilkanHalamanLupaKataSandi, prosesKirimLinkReset, tampilkanHalamanResetKataSandi, prosesResetKataSandi | Membuat token reset, memvalidasi email/token, memperbarui password, dan mengirim email melalui PasswordResetNotificationService. |
| app/Http/Controllers/Otentikasi/UbahKataSandiController.php | tampilkanUbahKataSandi, prosesUbahKataSandi | Form dan penyimpanan ganti password wajib pada tabel users. |

### 4.2 Alumni

| File | Method penting | Tugas dan sumber data |
|---|---|---|
| app/Http/Controllers/Alumni/Dashboard/DashboardController.php | tampilkanDashboard | Mengambil biodata user aktif, menghitung persentase profil/kuesioner, membaca status prodi dari v_alumni_tracer_prodi_status, dan status evaluasi atasan. |
| app/Http/Controllers/Alumni/Profil/ProfilController.php | tampilkanHalamanProfil | Menyusun form profil, referensi wilayah/negara/perusahaan, pertanyaan profil dinamis, serta jawaban tersimpan. |
| app/Http/Controllers/Alumni/Profil/SimpanProfilController.php | simpanPerubahanProfil, kirimEmailAtasan, tambahPerusahaanBaru, usulkanPembaruanPerusahaan | Menyimpan profil, karier, atasan, perusahaan, undangan evaluasi, perusahaan baru, dan usulan perubahan perusahaan. |
| app/Http/Controllers/Alumni/Kuesioner/KuesionerController.php | tampilkanKuesioner | Mengambil kuesioner umum aktif, section, pertanyaan, opsi, jawaban, dan data autofill. |
| app/Http/Controllers/Alumni/Kuesioner/SimpanJawabanController.php | store, simpanJawabanKuesioner | Memvalidasi dan menyimpan jawaban kuesioner umum, memperbarui field profil/perusahaan/atasan yang dikelola pertanyaan, lalu menjalankan sinkronisasi profil. |
| app/Http/Controllers/Alumni/Kuesioner/KuesionerProdiController.php | tampilkanKuesionerProdi, simpanJawaban | Mengambil section dan placement pertanyaan prodi, memproses jawaban termasuk matriks, lalu menyimpan jawaban prodi. |

### 4.3 Controller Shared

| File | Method penting | Tugas dan sumber data |
|---|---|---|
| app/Http/Controllers/Shared/DataAtasan/DaftarAtasanController.php | index, store, update | Direktori atasan dengan role scope melalui AtasanDirectoryQueryService. store dan update hanya route Superadmin. |
| app/Http/Controllers/Shared/Kuesioner/KelolaKuesionerProdiController.php | index, storeSection, updateSection, destroySection, reorderSection, storeQuestion, updateQuestion, destroyQuestion, reorderQuestion, toggleActive, togglePlacementActive, storeOption, updateOption, destroyOption, attachFromBank | CRUD konfigurasi kuesioner prodi. Memilih prodi berdasarkan role, query parameter, dan scope user. |
| app/Http/Controllers/Shared/LinkedInSyncController.php | index, updateUrl, syncSingle, syncBatch, showResult, approveResult, plotAgain, rejectResult, history | Staging hasil scraping, review, approval/reject, plot ulang, histori, dan batch sync. |

### 4.4 Admin Prodi, Fakultas, dan Biro 3

| File | Method penting | Tugas dan scope |
|---|---|---|
| app/Http/Controllers/AdminProdi/Dashboard/DashboardController.php | tampilkanDashboard | Memanggil RoleDashboardDataService dengan scope prodi user. |
| app/Http/Controllers/AdminProdi/KelolaPertanyaan/KelolaSectionProdiController.php | index, store, update, destroy, reorder | Halaman/CRUD section kuesioner prodi sederhana pada route /prodi/sections. Berbeda dari controller Shared karena alurnya hanya mengelola section dalam konteks prodi user. |
| app/Http/Controllers/AdminFakultas/Dashboard/DashboardController.php | tampilkanDashboard | Memvalidasi fakultas user dan memanggil RoleDashboardDataService dengan scope fakultas. |
| app/Http/Controllers/AdminBiroTiga/Dashboard/DashboardController.php | tampilkanDashboard | Memanggil RoleDashboardDataService dengan scope universitas. |
| app/Http/Controllers/AdminProdi/KelolaAlumni/DaftarAlumniProdiController.php | index, show, updateProfile, exportExcel, sendReminderEmail | Direktori dan detail alumni scope prodi, audit tab, update profil, ekspor, dan pengingat. |
| app/Http/Controllers/AdminFakultas/KelolaAlumni/DaftarAlumniFakultasController.php | index, sendReminderEmail | Direktori alumni scope fakultas dan pengingat. |
| app/Http/Controllers/AdminFakultas/KelolaAlumni/DetailAlumniFakultasController.php | show, updateProfile, exportExcel | Detail, audit tab, update profil, dan export Excel scope fakultas. |
| app/Http/Controllers/AdminBiroTiga/KelolaAlumni/DaftarAlumniController.php | tampilkanDaftarAlumni, exportZip, blastEmail, sendReminderEmail | Direktori universitas, export ZIP, blast email, dan pengingat individual. |
| app/Http/Controllers/AdminBiroTiga/KelolaAlumni/DetailAlumniController.php | tampilkanDetailAlumni, updateProfile, exportExcel | Detail audit dan export. updateProfile menolak perubahan karena Biro 3 hanya baca untuk area tersebut. |
| app/Http/Controllers/AdminProdi/VerifikasiPerusahaan/VerifikasiPerusahaanProdiController.php | index, verify, replace, updateAndVerify, reject, searchVerified, recommendations, destroy | Verifikasi dan konsolidasi perusahaan scope prodi; algoritma bisnis didelegasikan ke PerusahaanVerificationService. |
| app/Http/Controllers/AdminFakultas/VerifikasiPerusahaan/VerifikasiPerusahaanFakultasController.php | index, verify, replace, updateAndVerify, reject, searchVerified, recommendations, destroy | Verifikasi perusahaan scope seluruh prodi dalam fakultas. |

### 4.5 Superadmin

| File | Method penting | Tugas dan sumber data |
|---|---|---|
| app/Http/Controllers/SuperAdmin/Dashboard/DashboardController.php | tampilkanDashboard | Dashboard universitas dengan query langsung ke v_alumni_audit_rekap, Biodata, Prodi, Perusahaan, LogActivity, dan kuesioner. |
| app/Http/Controllers/SuperAdmin/KelolaAlumni/DaftarAlumniSuperAdminController.php | index, exportZip, sendReminderEmail | Direktori alumni lintas universitas, ZIP, dan pengingat. |
| app/Http/Controllers/SuperAdmin/KelolaAlumni/DetailAlumniSuperAdminController.php | show, buildLinkedinTraceMapping, updateProfile, exportExcel | Detail audit lima tab, update profil, mapping trace LinkedIn, dan Excel. |
| app/Http/Controllers/SuperAdmin/Analytics/TracerAnalyticsController.php | index, data, exportResponseRate | Halaman/JSON analitik tracer universitas dan export response rate. |
| app/Http/Controllers/SuperAdmin/Analytics/ProdiAnalyticsController.php | index, data | Halaman/JSON analitik kuesioner prodi. Admin Prodi dipaksa ke prodi user. |
| app/Http/Controllers/SuperAdmin/Analytics/AtasanAnalyticsController.php | index, data | Halaman/JSON analitik evaluasi atasan. |
| app/Http/Controllers/SuperAdmin/KelolaPertanyaan/DaftarPertanyaanController.php | index | Menyusun bank pertanyaan, section, opsi, placement, dan status kelengkapan. |
| app/Http/Controllers/SuperAdmin/KelolaPertanyaan/SimpanPertanyaanController.php | store, update, destroy, toggleActive, toggleVisibility, reorder, attachFromBank | Operasi pertanyaan kuesioner universitas dan bank soal. |
| app/Http/Controllers/SuperAdmin/KelolaPertanyaan/KelolaOpsiController.php | storeOption, updateOption, destroyOption | CRUD opsi jawaban pertanyaan universitas. |
| app/Http/Controllers/SuperAdmin/KelolaSection/KelolaSectionController.php | index, store, update, destroy, reorder | CRUD dan pengurutan section kuesioner universitas. |
| app/Http/Controllers/SuperAdmin/KelolaSection/KelolaKuesionerController.php | store, update, toggleActive, destroy | CRUD instrumen induk kuesioner. |
| app/Http/Controllers/SuperAdmin/KelolaEvaluasiAtasanController.php | index, CRUD section/pertanyaan/opsi, toggleQuestion, reorderQuestions, attachFromBank | Konfigurasi instrumen evaluasi atasan. |
| app/Http/Controllers/SuperAdmin/Perusahaan/VerifikasiPerusahaanSuperAdminController.php | index, store, verify, replace, updateAndVerify, reject, searchVerified, recommendations, destroy | Master perusahaan lintas universitas; implementasi bisnis ada di PerusahaanVerificationService. |
| app/Http/Controllers/SuperAdmin/MasterWilayah/MasterWilayahController.php | CRUD provinsi, kabupaten, negara, UMP, getKabupatenByProvinsi | Master wilayah dan UMP; query langsung memakai model/DB. |
| app/Http/Controllers/SuperAdmin/ManajemenAkunController.php | index, updateSyncAvailability, update, sendPasswordResetLink, syncAlumniAccounts | Manajemen akun, reset password, sinkronisasi akun alumni, dan buka/tutup sinkronisasi. |
| app/Http/Controllers/SuperAdmin/LogAktivitasController.php | index | Filter dan pagination audit trail log_activities. |
| app/Http/Controllers/Controller.php | Tidak ada alur bisnis | Base controller Laravel. |

## 5. Service dan helper per file

### 5.1 Alumni, dashboard, query, dan ekspor

| File | Isi utama | Sumber data / pemakai |
|---|---|---|
| app/Services/Alumni/AdminAlumniProfileService.php | buildFormData, getRefOptions, getProfileQuestionConfigs, updateProfile. Membentuk kontrak form profil dan menyimpan field kanonik. | Biodata, DataAkademik, Yudisium, Perusahaan, Atasan, wilayah, negara, pertanyaan profil. |
| app/Services/Alumni/AlumniDirectoryQueryService.php | getDirectoryData; filter tahun, semester, target, status, prodi, LinkedIn, wilayah, skills, bidang, unit, head office. | v_alumni_audit_rekap, v_alumni_profile_summary, Biodata, DataAkademik, Prodi, Perusahaan, wilayah. |
| app/Services/Alumni/LinkedinTraceHelper.php | Mapping nilai hasil scraping terhadap kolom aktif alumni, status kecocokan, dan histori. | Biodata, LinkedinSyncResult, v_alumni_linkedin_plot. |
| app/Services/Dashboard/RoleDashboardDataService.php | build; scope universitas/fakultas/prodi, response rate, completion, perusahaan, prodi, alumni terbaru, dan section. | v_alumni_audit_rekap, Prodi, Perusahaan, LogActivity, kuesioner. |
| app/Services/Export/AlumniTracerExcelExporter.php | download dan generateExcelContent; membuat workbook profil, tracer universitas, kuesioner prodi, evaluasi atasan. | Detail alumni dan relasi kuesioner/evaluasi/LinkedIn. |
| app/Services/Export/ResponseRateExcelExporter.php | Membuat SpreadsheetML response rate berdasarkan tahun, prodi, dan status. | v_alumni_audit_rekap dan data direktori. |

### 5.2 Kuesioner dan sinkronisasi profil

| File | Isi utama | Sumber data / pemakai |
|---|---|---|
| app/Services/Kuesioner/F3AnswerParser.php | parse dan extractMonths; menormalisasi jawaban masa tunggu menjadi status dan angka bulan. | kuesioner_jawaban.jawaban_teks atau jawaban_json. |
| app/Services/Kuesioner/KelengkapanTracerService.php | Daftar kode wajib, evaluasi profil, evaluasi kuesioner wajib, dan evaluasi total. | Biodata, KuesionerPertanyaan, KuesionerJawaban, view status tracer. |
| app/Services/Kuesioner/KuesionerSyncService.php | Pertanyaan profil dinamis, jawaban dinamis, syncProfileResponses, syncProdiResponses, resolusi field identitas. | Menjembatani kuesioner_jawaban dengan biodata, data_akademik, perusahaan, dan atasan. |
| app/Services/Kuesioner/QuestionDefaultOptionsService.php | Membuat opsi awal berdasarkan tipe pertanyaan. | KuesionerPertanyaan dan KuesionerOpsi. |
| app/Services/Kuesioner/UnifiedDetailAlumniService.php | Mengambil section dan jawaban kuesioner universitas/prodi serta pertanyaan evaluasi atasan untuk tab audit. | KuesionerSection, KuesionerPertanyaan, KuesionerJawaban, EvaluasiAtasan. |

### 5.3 Analitik

| File | Isi utama | Sumber data |
|---|---|---|
| app/Services/Analytics/TracerAnalyticsService.php | Payload chart universitas, filter, records drill-down, summary, availability, metodologi, response rate, employment, wait time, salary vs UMP, alignment, map, dan chart tracer. | v_analytics_*, v_alumni_audit_rekap, kuesioner_jawaban, ump, perusahaan, prodi. |
| app/Services/Analytics/ProdiAnalyticsService.php | Payload satu section prodi, formatter Likert/choice/text, pivot, statistik, records, insight, median, dan label jawaban. | KuesionerSection, KuesionerPertanyaan, KuesionerSectionPertanyaan, KuesionerJawaban, Prodi, DataAkademik. |
| app/Services/Analytics/AtasanAnalyticsService.php | Distribusi pertanyaan, skor Likert, kesiapan kerja, ringkasan prodi, dan records evaluasi atasan. | EvaluasiAtasan, KuesionerJawaban, KuesionerPertanyaan, Biodata, Prodi. |
| app/Services/Analytics/IkuS1AnalyticsService.php | AEE, outcome karier berbobot, masa tunggu, gaji terhadap UMP, klasifikasi role karier, dan status kontribusi. | v_iku_s1_aee, v_iku_s1_lulusan, Ump, KuesionerJawaban, akademik/profil. |

### 5.4 LinkedIn

| File | Isi utama | Sumber / pemakai |
|---|---|---|
| app/Services/LinkedIn/Contracts/LinkedInProfileProvider.php | Kontrak findByUsername dan findByUrl. | Provider API, mock, dan Apify. |
| app/Services/LinkedIn/LinkedInProfileService.php | syncBiodata dan syncBatch; mengambil profil dari provider dan menyimpan staging. | Biodata, LinkedinSyncResult, provider aktif. |
| app/Services/LinkedIn/Mappers/LinkedInProfileMapper.php | Menentukan posisi terbaru, mencari/membuat perusahaan, memetakan profil ke field biodata, dan payload approval. | LinkedInProfile, Biodata, PerusahaanVerificationService, Perusahaan. |
| app/Services/LinkedIn/DTOs/LinkedInProfile.php | DTO profil ter-normalisasi: nama, URL, headline, lokasi, perusahaan, posisi, pendidikan, skills, experience, foto. | Provider dan mapper. |
| app/Services/LinkedIn/DTOs/LinkedInPosition.php | DTO posisi kerja, status current, tanggal mulai/selesai, serialisasi. | LinkedInProfile. |
| app/Services/LinkedIn/Providers/ApifyLinkedInProvider.php | Request Apify, validasi URL, normalisasi response, tanggal, foto, dan field nested. | Apify API; hasil masuk ke LinkedinSyncResult. |
| app/Services/LinkedIn/Providers/ApiLinkedInProvider.php | Provider API, validasi konfigurasi, request username/URL, dan normalisasi response. | Provider alternatif dari konfigurasi. |
| app/Services/LinkedIn/Providers/MockApifyLinkedInProvider.php | Pengganti Apify untuk lokal; membaca fixture dan meniru payload scraper. | storage/app/mock/apify. |
| app/Services/LinkedIn/Providers/MockLinkedInProvider.php | Provider simulasi lokal berbasis username/biodata. | Pengujian tanpa request eksternal. |
| app/Services/LinkedIn/Exceptions/LinkedInProfileNotFoundException.php | Exception profil tidak ditemukan. | Ditangani controller sync. |
| app/Services/LinkedIn/Exceptions/LinkedInSyncException.php | Exception umum proses sync. | Ditangani controller sync. |

### 5.5 Perusahaan, atasan, dan otentikasi

| File | Isi utama | Sumber / pemakai |
|---|---|---|
| app/Services/Perusahaan/PerusahaanVerificationService.php | Pending query scope, jumlah pending, pencarian master, rekomendasi, verify, auto-replace, update-and-verify, reject, dan delete flow. | Perusahaan, Biodata, Prodi, LogActivity, wilayah. Cleaning memakai cleanCompanyName, cleanString, getTokens, makeAcronym, getAcronyms. |
| app/Services/Atasan/AtasanDirectoryQueryService.php | Query direktori atasan dengan role scope, pencarian, relasi perusahaan/alumni, dan statistik. | Atasan, Biodata, Perusahaan, User, Prodi, RefFakultas. |
| app/Services/Otentikasi/PasswordResetNotificationService.php | Membuat token reset, menyimpan token, dan mengirim ResetPasswordMail. | User, password_reset_tokens, mailer. |



## 6. Halaman Vue/Inertia per file

Controller mengirim props melalui Inertia. Halaman Vue menerima props tersebut,
mengelola state filter/form lokal, lalu memanggil route Inertia atau endpoint JSON
untuk perubahan data tanpa membuat query database di browser.

### 6.1 Halaman autentikasi dan publik

| File | Pemakai | Isi dan sumber data |
|---|---|---|
| resources/js/Pages/Tamu/Beranda.vue | Tamu/publik | Landing page; memakai komponen landing untuk statistik dan informasi publik dari BerandaController. |
| resources/js/Pages/Public/EvaluasiAtasanForm.vue | Evaluator publik dengan token | Form evaluasi atasan; props token, alumni, atasan, perusahaan, pertanyaan, opsi referensi; POST ke evaluasi-atasan/{token}. |
| resources/js/Pages/Public/EvaluasiAtasanNotFound.vue | Evaluator publik | Empty/error state saat token tidak valid, kedaluwarsa, atau evaluasi sudah tidak tersedia. |
| resources/js/Pages/Otentikasi/Login.vue | Semua user | Form login POST /login; redirect diputuskan LoginController. |
| resources/js/Pages/Otentikasi/ForgotPassword.vue | Guest | Form email reset POST /forgot-password. |
| resources/js/Pages/Otentikasi/ResetPassword.vue | Guest dengan token | Form password baru POST /reset-password. |
| resources/js/Pages/Otentikasi/UbahKataSandi.vue | Semua user yang wajib mengganti password | Form perubahan password POST /change-password. |

### 6.2 Halaman alumni

| File | Pemakai | Isi dan sumber data |
|---|---|---|
| resources/js/Pages/Alumni/Dashboard.vue | Alumni | Ringkasan kelengkapan profil, kuesioner universitas/prodi, evaluasi atasan, perusahaan, dan tautan undangan. Props dihitung DashboardController dan KelengkapanTracerService. |
| resources/js/Pages/Alumni/Profil/Index.vue | Alumni | Halaman form profil bertahap; menerima form, referensi, perusahaan, pertanyaan profil, jawaban dinamis, dan data atasan. POST ke profile, company, request-update, dan kirim-email-atasan. |
| resources/js/Pages/Alumni/Kuesioner.vue | Alumni | Form kuesioner umum; menerima sections, questions, answers, options, progress, autofill, dan skip logic. POST jawaban ke alumni/kuesioner. |
| resources/js/Pages/Alumni/KuesionerProdi.vue | Alumni | Form kuesioner prodi; menerima section, placement, pertanyaan matriks, jawaban, dan progress. POST ke alumni/kuesioner-prodi. |
| resources/js/Pages/Alumni/Components/Navbar.vue | Alumni | Navigasi alumni, user aktif, dan logout. |
| resources/js/Pages/Alumni/Components/AlumniDirectoryShared.vue | Dipakai wrapper direktori admin | Komponen besar untuk filter direktori, pagination, kontak alumni, export, LinkedIn sync/review/history, dan modal. Props berasal dari query service direktori dan prefix role. |
| resources/js/Pages/Alumni/Components/AlumniDetailShared.vue | Dipakai halaman detail admin | Shell detail alumni dengan tab Profil, Kuesioner Universitas, Kuesioner Prodi, Evaluasi Atasan, dan LinkedIn. Query string tab menjaga konteks saat navigasi. |
| resources/js/Pages/Alumni/Components/Kuesioner/Banner.vue | Kuesioner umum | Header konteks, progress, dan informasi instrumen. |
| resources/js/Pages/Alumni/Components/Kuesioner/KartuPertanyaan.vue | Kuesioner umum | Menampilkan satu pertanyaan, tipe input, opsi, validasi, dan jawaban. |
| resources/js/Pages/Alumni/Components/Kuesioner/Navigasi.vue | Kuesioner umum | Navigasi pertanyaan/section, next/previous, dan submit. |
| resources/js/Pages/Alumni/Components/Kuesioner/Stepper.vue | Kuesioner umum | Indikator posisi section/pertanyaan dan status selesai. |
| resources/js/Pages/Alumni/Components/Kuesioner/TabelF17.vue | Kuesioner umum | Input matriks/gap kompetensi F17; jawaban disimpan sebagai struktur JSON. |
| resources/js/Pages/Alumni/Components/Kuesioner/TabelF2.vue | Kuesioner umum | Input tabel/matriks F2; payload mengikuti kontrak jawaban kuesioner. |
| resources/js/Pages/Alumni/Components/KuesionerProdi/Banner.vue | Kuesioner prodi | Header dan progress instrumen prodi. |
| resources/js/Pages/Alumni/Components/KuesionerProdi/KartuPertanyaanProdi.vue | Kuesioner prodi | Render pertanyaan prodi berdasarkan tipe, opsi, placement, dan jawaban. |
| resources/js/Pages/Alumni/Components/KuesionerProdi/Navigasi.vue | Kuesioner prodi | Navigasi section/pertanyaan prodi. |
| resources/js/Pages/Alumni/Components/KuesionerProdi/Stepper.vue | Kuesioner prodi | Status progres section prodi. |
| resources/js/Pages/Alumni/Components/KuesionerProdi/TabelMatriksProdi.vue | Kuesioner prodi | Input pertanyaan matriks; mengirim jawaban terstruktur. |

### 6.3 Dashboard dan direktori admin

| File | Pemakai | Isi dan sumber data |
|---|---|---|
| resources/js/Pages/AdminProdi/Dashboard.vue | Admin Prodi | Wrapper dashboard; meneruskan props scope prodi ke RoleDashboard. |
| resources/js/Pages/AdminFakultas/Dashboard.vue | Admin Fakultas | Wrapper dashboard; meneruskan props scope fakultas. |
| resources/js/Pages/AdminBiroTiga/Dashboard.vue | Admin Biro 3 | Wrapper dashboard; meneruskan props scope universitas. |
| resources/js/Pages/SuperAdmin/Dashboard.vue | Superadmin | Dashboard universitas dengan props lebih kaya dari controller Superadmin; filter tahun memakai router.get. |
| resources/js/Pages/AdminProdi/Alumni/Index.vue | Admin Prodi | Wrapper direktori; mengirim role, prefix, data pagination, filters, options, dan daftar alumni ke AlumniDirectoryShared. |
| resources/js/Pages/AdminProdi/Alumni/Show.vue | Admin Prodi | Wrapper detail alumni; mengirim formData, audit, pertanyaan, relasi, dan endpoint update ke AlumniDetailShared. |
| resources/js/Pages/AdminFakultas/Alumni/Index.vue | Admin Fakultas | Wrapper direktori scope fakultas. |
| resources/js/Pages/AdminFakultas/Alumni/Show.vue | Admin Fakultas | Wrapper detail scope fakultas. |
| resources/js/Pages/AdminBiroTiga/AlumniIndex.vue | Admin Biro 3 | Wrapper direktori universitas dengan export ZIP dan komunikasi alumni. |
| resources/js/Pages/AdminBiroTiga/AlumniShow.vue | Admin Biro 3 | Wrapper detail baca dan audit. |
| resources/js/Pages/SuperAdmin/Alumni/Index.vue | Superadmin | Wrapper direktori lintas universitas dengan export ZIP dan action administratif. |
| resources/js/Pages/SuperAdmin/Alumni/Show.vue | Superadmin | Wrapper detail penuh dan update profil. |
| resources/js/Pages/AdminProdi/Section/Index.vue | Admin Prodi | Halaman konfigurasi section prodi lama/khusus; CRUD section dan reorder melalui /prodi/sections. |
| resources/js/Pages/AdminProdi/Components/Sidebar.vue | Admin Prodi | Wrapper sidebar yang meneruskan role, user, dan konteks prodi. |
| resources/js/Pages/AdminFakultas/Components/Sidebar.vue | Admin Fakultas | Wrapper sidebar konteks fakultas. |
| resources/js/Pages/AdminBiroTiga/Components/Sidebar.vue | Admin Biro 3 | Wrapper sidebar konteks Biro 3. |
| resources/js/Pages/SuperAdmin/Components/Sidebar.vue | Superadmin | Wrapper sidebar Superadmin. |

### 6.4 Halaman konfigurasi dan administrasi

| File | Pemakai | Isi dan sumber data |
|---|---|---|
| resources/js/Pages/Shared/DataAtasan/Index.vue | Admin Prodi/Fakultas/Biro 3 baca; Superadmin CRUD | Tabel atasan, filter, pagination, form create/update untuk route Superadmin. |
| resources/js/Pages/Shared/Kuesioner/ProdiKuesionerIndex.vue | Admin Prodi, Fakultas, Superadmin | CRUD section, placement pertanyaan, opsi, bank soal, reorder, toggle active; basePath menentukan role dan scope. |
| resources/js/Pages/SuperAdmin/Pertanyaan/Index.vue | Superadmin | Bank pertanyaan universitas, opsi, active/visibility, reorder, attachment ke section. |
| resources/js/Pages/SuperAdmin/Section/Index.vue | Superadmin | Konfigurasi section dan instrumen kuesioner universitas. |
| resources/js/Pages/SuperAdmin/EvaluasiAtasan/Index.vue | Superadmin | Konfigurasi section, pertanyaan, opsi, reorder, attach bank soal evaluasi atasan. |
| resources/js/Pages/SuperAdmin/Perusahaan/Index.vue | Superadmin, Admin Fakultas, Admin Prodi | Vue bersama master perusahaan; basePath, role, canCreate, dan scope dikirim controller. Memuat rekomendasi lewat endpoint JSON, lalu verify, replace, edit, reject, delete. |
| resources/js/Pages/SuperAdmin/MasterWilayah/Index.vue | Superadmin | Tab provinsi, kabupaten, negara, dan UMP; cascade kabupaten menggunakan API wilayah. |
| resources/js/Pages/SuperAdmin/ManajemenAkun/Index.vue | Superadmin | Daftar akun, filter role, edit akun, reset password, kontrol account sync, dan sinkronisasi alumni. |
| resources/js/Pages/SuperAdmin/Logs/Index.vue | Superadmin | Tabel audit log, filter, pagination, dan reload Inertia. |
| resources/js/Pages/SuperAdmin/LinkedIn/Index.vue | Superadmin | Halaman khusus sync LinkedIn, batch/single, URL, review hasil, approve/reject, plot ulang, dan histori. |
| resources/js/Pages/Tamu/Beranda.vue | Publik | Shell landing yang memuat komponen landing dan data publik dari BerandaController. |

## 7. Grafik dan komponen analitik Vue

Backend tidak mengirim gambar grafik. Backend menghitung payload JSON,
sedangkan Vue dan ECharts/MapLibre/word cloud merender visualisasi. Polanya:

    TracerAnalyticsController
        -> TracerAnalyticsService
            -> v_analytics_* / v_iku_s1_* / tabel jawaban
        -> GrafikUniv.vue
            -> AnalyticsChartsPanel.vue
                -> category chart
                    -> AnalyticsEChart / AnalyticsMap / AnalyticsTabbedContainer

### 7.1 Halaman grafik

| File | Pemakai | Isi, endpoint, dan query |
|---|---|---|
| resources/js/Pages/SuperAdmin/Analytics/GrafikUniv.vue | Admin Prodi/Fakultas/Biro 3/Superadmin melalui prefix route | Halaman induk analitik universitas. Menerima props analytics dari TracerAnalyticsController, menyimpan filter tahun/UMP/prodi/chart, mengambil JSON dari {prefix}/analitik/data, menampilkan KPI, insight, metodologi, chart, tabel drill-down, pencarian, sort, pagination, dan detail alumni. |
| resources/js/Pages/SuperAdmin/Analytics/GrafikProdi.vue | Admin Prodi/Fakultas/Biro 3/Superadmin | Halaman analitik kuesioner prodi. Menerima payload ProdiAnalyticsService, fetch ke {prefix}/analitik-prodi/data, memilih prodi/section/tahun, menampilkan chart, pivot, records, kontak alumni, dan drill-down. |
| resources/js/Pages/SuperAdmin/Analytics/GrafikAtasan.vue | Admin Prodi/Fakultas/Biro 3/Superadmin | Halaman analitik evaluasi atasan. Fetch ke {prefix}/analitik-atasan/data, menampilkan rata-rata aspek, distribusi aspek, kesiapan kerja, perbandingan prodi, dan records. |

### 7.2 Komponen fondasi grafik universitas

| File | Isi dan kontrak data |
|---|---|
| resources/js/components/analytics/AnalyticsChartsPanel.vue | Hub pemilih chart universitas. Lazy-load 21 komponen kategori, meneruskan chart payload, selectedChart, chart options, dan event select/province/UMP/data/methodology. Menjaga state cross-filter dan mode heatmap count/percentage. |
| resources/js/components/analytics/AnalyticsEChart.vue | Wrapper Apache ECharts. Menerima option, height, loading, dan label; menginisialisasi chart, resize, legend/zoom/hover event, reset, serta download PNG. |
| resources/js/components/analytics/AnalyticsMap.vue | Peta MapLibre untuk provinsi dan cluster lokasi kerja. Membaca GeoJSON public/geojson/indonesia-provinsi.json, menggabungkan region payload backend, menampilkan choropleth/cluster, dan emit province-selected. |
| resources/js/components/analytics/AnalyticsTabbedContainer.vue | Layout reusable untuk chart, tabel, ringkasan, tab data, empty state, dan drill-down inline. |
| resources/js/components/analytics/AnalyticsChartCard.vue | Kartu pembungkus judul, subtitle, summary, chart, dan state kosong/loading. |
| resources/js/components/analytics/AnalyticsWordCloud.vue | Merender kata/feedback dengan ukuran berbasis frekuensi; data berasal dari payload teks Service analitik. |
| resources/js/components/analytics/analyticsChartOptions.js | Kamus chart key, normalisasi option ECharts, palette, formatter, opsi bar/line/pie/heatmap/sankey/treemap, dan option khusus chart IKU. Tidak membaca database. |

### 7.3 Grafik universitas per file

Semua file di bawah menerima chart payload dari TracerAnalyticsService.
Nama tabel/view di bawah adalah sumber backend, bukan query langsung dari Vue.

| File | Grafik dan sumber data backend |
|---|---|
| components/analytics/categories/01_ResponseRate.vue | Response rate per prodi/status; data dari v_analytics_response_rate dan v_alumni_audit_rekap. Menampilkan bar/stacked chart, target, record responded/not responded, dan emit drill-down. |
| components/analytics/categories/02_LearningMethods.vue | Metode pembelajaran; kode pertanyaan F21-F27 dari v_analytics_tracer_answers. Menampilkan distribusi dan detail jawaban. |
| components/analytics/categories/03_JobSearchStart.vue | Waktu mulai mencari pekerjaan; F3/F502 yang sudah dipetakan service dan F3AnswerParser. |
| components/analytics/categories/04_JobSearchMethods.vue | Metode pencarian pekerjaan; jawaban F4 dari read-model tracer answers. |
| components/analytics/categories/05_WaitTime.vue | Waktu tunggu mendapat pekerjaan; data wait-time ter-normalisasi dari F502/F504/F506 dan service. |
| components/analytics/categories/06_ApplicationPipeline.vue | Pipeline lamaran; relasi F6/F7/F7A dan conversion/pipeline stats dari service. |
| components/analytics/categories/07_EmploymentStatus.vue | Status pekerjaan; F8 dan v_analytics_employment_status. |
| components/analytics/categories/08_JobSearchActivity.vue | Keaktifan mencari pekerjaan; F10. |
| components/analytics/categories/09_WorkplaceType.vue | Jenis tempat kerja; F11, perusahaan, dan records detail. |
| components/analytics/categories/10_FurtherStudy.vue | Studi lanjut; F12/F18b-F18d serta data pendidikan lanjut yang dipetakan service. |
| components/analytics/categories/11_SalaryVsUmp.vue | Gaji terhadap UMP; v_analytics_salary_ump, Ump, provinsi perusahaan, filter tahun UMP, dan selection province. |
| components/analytics/categories/12_FieldAlignment.vue | Keselarasan bidang studi dengan pekerjaan; F14 dan v_analytics_alignment. |
| components/analytics/categories/13_EducationAlignment.vue | Keselarasan tingkat pendidikan; F15 dan v_analytics_alignment. |
| components/analytics/categories/15_MismatchReasons.vue | Alasan ketidaksesuaian pekerjaan; F16. |
| components/analytics/categories/16_CompetencyGap.vue | Gap kompetensi; pasangan F17a/F17b, line/heatmap, dan detail jawaban. |
| components/analytics/categories/17_ProfileCompletion.vue | Kelengkapan profil; v_analytics_profile_completion dan field kontrak profil aktif. |
| components/analytics/categories/18_WorkLocation.vue | Wilayah kerja; v_analytics_work_location, map MapLibre, marker/cluster, dan province drill-down. |
| components/analytics/categories/19_IkuS1Aee.vue | AEE/kelulusan tepat waktu; v_iku_s1_aee dan payload IkuS1AnalyticsService. |
| components/analytics/categories/20_IkuS1Outcomes.vue | Outcome karier IKU S1; v_iku_s1_lulusan, klasifikasi karier, masa tunggu, gaji, dan studi lanjut. |
| components/analytics/categories/21_IkuS1UmpWeighted.vue | Outcome berbobot UMP; v_iku_s1_lulusan, Ump, bobot kategori, masa tunggu, dan status kontribusi. |

### 7.4 Grafik program studi per file

ProdiAnalyticsChartsPanel memilih file berdasarkan section.order. Jika tipe
section adalah choice, panel memakai ChoiceQuestionsChart. Untuk Likert,
kategori memakai buildLikertOption dari prodiChartOptions.js. Data record
tetap berasal dari KuesionerJawaban dan pertanyaan/section aktif.

| File | Grafik dan sumber data backend |
|---|---|
| components/prodi-analytics/ProdiAnalyticsChartsPanel.vue | Hub dinamis 9 section prodi; memilih komponen berdasarkan section order/type dan meneruskan items. |
| components/prodi-analytics/ChoiceQuestionsChart.vue | Chart pertanyaan pilihan; membaca categories/series/records dari payload choice. |
| components/prodi-analytics/ProdiPivotTable.vue | Pivot/tabel silang pertanyaan-responden; bukan sumber data baru, hanya representasi payload service. |
| components/prodi-analytics/prodiChartOptions.js | Builder option choice/Likert, tinggi chart, label skor, warna, dan formatter. |
| components/prodi-analytics/categories/01_InterestAndContribution.vue | Peminatan dan kontribusi; choice chart, word cloud, context selection, dan records. |
| components/prodi-analytics/categories/02_LearningExperience.vue | Pengalaman pembelajaran; Likert chart per pertanyaan. |
| components/prodi-analytics/categories/03_StudyProgramValues.vue | Nilai yang dirasakan dari program studi; Likert chart. |
| components/prodi-analytics/categories/04_AvailableFacilities.vue | Ketersediaan fasilitas; Likert chart. |
| components/prodi-analytics/categories/05_LecturersAndStaff.vue | Dosen dan tenaga kependidikan; Likert chart. |
| components/prodi-analytics/categories/06_CoursesOffered.vue | Mata kuliah yang ditawarkan; Likert chart. |
| components/prodi-analytics/categories/07_OverallSatisfaction.vue | Kepuasan keseluruhan; Likert chart dan detail records. |
| components/prodi-analytics/categories/08_StudyProgramRecommendation.vue | Rekomendasi program studi; Likert chart. |
| components/prodi-analytics/categories/09_HopesAndFeedback.vue | Harapan dan saran; word cloud serta daftar respons teks dengan tab/filter. |

### 7.5 Grafik evaluasi atasan

Grafik evaluasi atasan tidak memakai category file terpisah. Option ECharts
dibentuk langsung oleh GrafikAtasan.vue dari question_stats, readiness,
prodi_summary, dan records yang dikirim AtasanAnalyticsService.

| Bagian di GrafikAtasan.vue | Sumber dan fungsi |
|---|---|
| scoreOption | Rata-rata skor aspek kinerja Likert 1-5 dari question_stats. |
| aspectDetailOption | Distribusi pilihan untuk aspek yang dipilih. |
| readinessOption | Distribusi tingkat kesiapan kerja dari payload readiness. |
| prodiOption | Perbandingan rata-rata skor antar prodi dari prodi_summary. |
| records table | Detail evaluator/alumni/atasan yang menjadi pembentuk agregat. |
| filter dan fetch | tahun/prodi ke endpoint analitik atasan sesuai prefix role. |

## 8. Komponen profil dan audit

### 8.1 Komponen form profil alumni

| File | Isi |
|---|---|
| resources/js/components/AlumniProfil/Stepper.vue | Navigasi tab form profil dan emit select-tab. |
| resources/js/components/AlumniProfil/FormPribadi.vue | Identitas, kontak, alamat, kewarganegaraan, pertanyaan profil, validasi email/telepon/URL, wilayah bertingkat, dan sinkronisasi field custom. |
| resources/js/components/AlumniProfil/FormAkademik.vue | Data akademik/yudisium yang ditampilkan dari formData; sebagian besar bersifat referensi atau read-only. |
| resources/js/components/AlumniProfil/FormKarier.vue | Kategori pekerjaan, posisi, perusahaan, bidang, divisi, gaji, pendidikan lanjut, atasan, dan pengiriman undangan evaluasi. Terdapat normalisasi gaji dan sinkronisasi alias field company/perusahaan. |

Props utama FormPribadi/FormKarier berasal dari
AdminAlumniProfileService::buildFormData(), getRefOptions(), dan data
perusahaan/atasan dari controller. Penyimpanan akhirnya masuk ke Biodata,
Perusahaan, Atasan, User, dan KuesionerJawaban melalui controller.

### 8.2 Komponen audit detail alumni

| File | Isi dan sumber |
|---|---|
| resources/js/components/AlumniAudit/AlumniStepper.vue | Navigasi tab audit profil, kuesioner, evaluasi atasan, dan LinkedIn. |
| resources/js/components/AlumniAudit/TabProfil.vue | Menampilkan FormPribadi/FormAkademik/FormKarier dalam konteks audit admin. |
| resources/js/components/AlumniAudit/TabKuesionerUniv.vue | Menampilkan section/pertanyaan/jawaban kuesioner universitas dari UnifiedDetailAlumniService. |
| resources/js/components/AlumniAudit/TabKuesionerProdi.vue | Menampilkan section/placement/jawaban prodi dari UnifiedDetailAlumniService. |
| resources/js/components/AlumniAudit/TabEvaluasiAtasan.vue | Menampilkan token/status/pertanyaan/jawaban evaluasi atasan dan relasi atasan. |
| resources/js/components/AlumniAudit/TabLinkedin.vue | Menampilkan URL, status sync, preview scraped, mapping field, histori, dan status approval dari LinkedinTraceHelper serta v_alumni_linkedin_plot. |

### 8.3 Komponen administrasi umum

| File | Isi |
|---|---|
| resources/js/components/AdminPageHeader.vue | Header halaman admin, breadcrumb, judul, subtitle, dan action slot. |
| resources/js/components/AdminSidebar.vue | Menu navigasi berdasarkan role, scope, dan route aktif. |
| resources/js/components/RoleDashboard.vue | Dashboard reusable; menerima role, konfigurasi prefix, statistik, completion metric, prodi summary, company summary, recent alumni, dan sections. |
| resources/js/components/Otentikasi/AuthBackdrop.vue | Background/presentasi visual yang dipakai halaman autentikasi. Tidak mengambil data database. |
| resources/js/components/PaginationControls.vue | Pagination reusable untuk direktori, analytics records, logs, dan tabel lain. |
| resources/js/components/form/searchable-select.vue | Select searchable reusable untuk prodi/wilayah/master. |
| resources/js/components/ui/pigo-loader.vue | Loading indicator presentasi. |

### 8.4 Komponen landing

| File | Isi dan sumber |
|---|---|
| resources/js/components/landing/navbar.vue | Navigasi landing dan link login/section. |
| resources/js/components/landing/hero-section.vue | Hero dan call-to-action publik. |
| resources/js/components/landing/stats-transformation-section.vue | Statistik ringkas dari props landing. |
| resources/js/components/landing/alumni-map-section.vue | Peta persebaran alumni publik; mengambil GeoJSON provinsi/kabupaten dan payload lokasi. |
| resources/js/components/landing/berkas-section.vue | Informasi berkas/dokumen publik. |
| resources/js/components/landing/blog-section.vue | Section informasi/blog statis. |
| resources/js/components/landing/tentang-section.vue | Penjelasan aplikasi/institusi. |
| resources/js/components/landing/user-guide-section.vue | Panduan penggunaan publik. |
| resources/js/components/landing/footer.vue | Footer dan tautan penutup. |

## 9. Model Eloquent dan database view model

### 9.1 Model tabel kanonik

| Model | Tabel | Isi dan relasi utama |
|---|---|---|
| app/Models/User.php | users | Akun, role, prodi/fakultas, biodata, accessor nama/email, password. |
| app/Models/Biodata.php | biodata | Profil aktif alumni, kontak, domisili, karier, perusahaan_id, atasan_id, LinkedIn, relasi akademik/kuesioner/evaluasi. |
| app/Models/DataAkademik.php | data_akademik | NIM, identitas akademik, tahun lulus, IPK, relasi biodata/yudisium. |
| app/Models/Yudisium.php | yudisium | Status lulus, proses yudisium, tanggal/tahun lulus, relasi akademik/biodata. |
| app/Models/Prodi.php | prodi | Program studi dan fakultas, relasi alumni serta section kuesioner prodi. |
| app/Models/RefFakultas.php | ref_fakultas | Master fakultas dan relasi prodi/user. |
| app/Models/Perusahaan.php | perusahaan | Master/pengajuan perusahaan, status verifikasi, pembuat, wilayah, alumni, atasan, audit verifikasi. |
| app/Models/Atasan.php | atasan | Nama, email, telepon, perusahaan, user/alumni, evaluasi atasan. |
| app/Models/Propinsi.php | propinsi | Kode/nama provinsi, relasi kabupaten/perusahaan/UMP. |
| app/Models/Kabupaten.php | kabupaten | Kode/nama kabupaten/kota dan relasi provinsi/perusahaan. |
| app/Models/RefNegara.php | ref_negara | Master negara untuk lokasi perusahaan/alumni. |
| app/Models/Ump.php | ump | UMP per provinsi dan tahun; dipakai salary vs UMP dan IKU. |
| app/Models/LogActivity.php | log_activities | Audit actor, action, description, IP, user, timestamp. |
| app/Models/EvaluasiAtasan.php | evaluasi_atasan | Token, status submit, alumni, perusahaan, atasan, dan respons evaluasi. |
| app/Models/LinkedinSyncResult.php | linkedin_sync_results | Staging scraped_data JSON, URL/username, status pending/approved/rejected, reviewer, timestamp, preview/accessor. |
| app/Models/Kuesioner.php | kuesioner | Instrumen induk, active flag, relasi sections. |
| app/Models/KuesionerSection.php | kuesioner_section | Section universitas/prodi/evaluasi, owner prodi, order/active, relasi placement/jawaban. |
| app/Models/KuesionerPertanyaan.php | kuesioner_pertanyaan | Bank pertanyaan, tipe, kode, label, active/visibility, owner prodi, opsi, placement, jawaban. |
| app/Models/KuesionerSectionPertanyaan.php | kuesioner_section_pertanyaan | Placement pertanyaan ke section, order, active. |
| app/Models/KuesionerOpsi.php | kuesioner_opsi | Opsi jawaban, label, skor, relasi pertanyaan dan jawaban. |
| app/Models/KuesionerJawaban.php | kuesioner_jawaban | Jawaban teks/JSON, biodata/evaluasi, pertanyaan, section, opsi, nilai/catatan accessor. |
| app/Models/QuestionMapping.php | v_question_mappings | Mapping kode pertanyaan ke tabel/kolom kanonik; read-only. |

### 9.2 Model yang membaca database view

| Model | View | Pemakai |
|---|---|---|
| app/Models/AlumniAuditRekap.php | v_alumni_audit_rekap | Dashboard dan direktori; scope selesai/belum/prodi/fakultas. |
| app/Models/AlumniKuesionerAutofill.php | v_alumni_kuesioner_autofill | Autofill kuesioner dari profil dan data akademik. |
| app/Models/AlumniLinkedinPlot.php | v_alumni_linkedin_plot | Status staging, nama cocok/tidak, plot, reviewer, dan histori ringkas. |
| app/Models/AlumniProfileSummary.php | v_alumni_profile_summary | Ringkasan profil, akademik, yudisium, karier, perusahaan, dan atasan. |
| app/Models/AlumniTracerProdiStatus.php | v_alumni_tracer_prodi_status | Jumlah dan progres pertanyaan prodi. |
| app/Models/AlumniTracerUnivStatus.php | v_alumni_tracer_univ_status | Jumlah dan progres pertanyaan wajib universitas. |

Database view tidak menjadi tempat write data bisnis. Write tetap dilakukan ke
tabel kanonik, lalu view membaca ulang hasilnya.


## 10. Migration dan tabel/view per file

### 10.1 Migration tabel framework

| Migration | Object | Dipakai untuk |
|---|---|---|
| 2026_09_01_000000_create_users_table.php | users | Akun, role, password, scope prodi/fakultas. |
| 2026_09_01_000001_create_cache_table.php | cache | Cache Laravel. |
| 2026_09_01_000002_create_cache_locks_table.php | cache_locks | Lock cache Laravel. |
| 2026_09_01_000003_create_jobs_table.php | jobs | Queue jobs. |
| 2026_09_01_000004_create_job_batches_table.php | job_batches | Batch queue. |
| 2026_09_01_000005_create_failed_jobs_table.php | failed_jobs | Job gagal. |
| 2026_09_01_000006_create_password_reset_tokens_table.php | password_reset_tokens | Token lupa password. |
| 2026_09_01_000007_create_sessions_table.php | sessions | Session login. |

### 10.2 Migration tabel bisnis

| Migration | Tabel | Isi utama |
|---|---|---|
| 2026_09_01_000008_create_propinsi_table.php | propinsi | Master provinsi. |
| 2026_09_01_000009_create_ref_fakultas_table.php | ref_fakultas | Master fakultas. |
| 2026_09_01_000010_create_ref_negara_table.php | ref_negara | Master negara. |
| 2026_09_01_000011_create_data_akademik_table.php | data_akademik | Sumber identitas akademik, NIM, tahun lulus, IPK. |
| 2026_09_01_000012_create_prodi_table.php | prodi | Master program studi dan fakultas. |
| 2026_09_01_000013_create_kabupaten_table.php | kabupaten | Master kabupaten/kota dan provinsi. |
| 2026_09_01_000014_create_perusahaan_table.php | perusahaan | Master dan pengajuan perusahaan, status verifikasi, wilayah, creator. |
| 2026_09_01_000015_create_atasan_table.php | atasan | Atasan alumni, kontak, perusahaan, dan relasi evaluasi. |
| 2026_09_01_000016_create_yudisium_table.php | yudisium | Status dan tanggal kelulusan. |
| 2026_09_01_000017_create_biodata_table.php | biodata | Profil aktif alumni dan relasi bisnis. |
| 2026_09_01_000018_create_log_activities_table.php | log_activities | Audit trail. |
| 2026_09_01_000019_create_kuesioner_table.php | kuesioner | Instrumen induk. |
| 2026_09_01_000024_create_ump_table.php | ump | UMP per provinsi/tahun. |
| 2026_09_01_000030_create_evaluasi_atasan_table.php | evaluasi_atasan | Token dan status evaluasi atasan. |
| 2026_09_01_000032_create_linkedin_sync_results_table.php | linkedin_sync_results | Staging hasil scraping LinkedIn. |
| 2026_09_01_000033_create_kuesioner_section_table.php | kuesioner_section | Section instrumen. |
| 2026_09_01_000034_create_kuesioner_pertanyaan_table.php | kuesioner_pertanyaan | Bank pertanyaan. |
| 2026_09_01_000035_create_kuesioner_section_pertanyaan_table.php | kuesioner_section_pertanyaan | Placement pertanyaan ke section. |
| 2026_09_01_000036_create_kuesioner_opsi_table.php | kuesioner_opsi | Opsi jawaban dan skor. |
| 2026_09_01_000037_create_kuesioner_jawaban_table.php | kuesioner_jawaban | Jawaban alumni/evaluator. |
| 2026_11_11_231728_add_label_pertanyaan_to_kuesioner_pertanyaan_table.php | kuesioner_pertanyaan | Label tambahan untuk pertanyaan. |
| 2026_11_13_122710_create_account_sync_settings_table.php | account_sync_settings | Flag buka/tutup sinkronisasi akun alumni dan updated_by. Saat ini dibaca langsung oleh ManajemenAkunController, belum ada model AccountSyncSetting. |

### 10.3 Migration database view operasional

| Migration | View | Fungsi |
|---|---|---|
| 2026_09_02_000000_create_v_question_mappings_view.php | v_question_mappings | Mapping kode pertanyaan ke tabel/kolom kanonik. |
| 2026_09_02_000001_create_v_alumni_kuesioner_autofill_view.php | v_alumni_kuesioner_autofill | Nilai autofill profil/akademik untuk kuesioner. |
| 2026_09_02_000002_create_v_alumni_linkedin_plot_view.php | v_alumni_linkedin_plot | Status staging, kecocokan nama, status plot, dan reviewer LinkedIn. |
| 2026_09_02_000003_create_v_alumni_profile_summary_view.php | v_alumni_profile_summary | Ringkasan profil untuk direktori/detail. |
| 2026_09_02_000004_create_v_alumni_tracer_prodi_status_view.php | v_alumni_tracer_prodi_status | Status progres kuesioner prodi. |
| 2026_09_02_000005_create_v_alumni_tracer_univ_status_view.php | v_alumni_tracer_univ_status | Status progres kuesioner universitas. |
| 2026_09_02_000006_create_v_alumni_audit_rekap_view.php | v_alumni_audit_rekap | Rekap profil, Univ, Prodi, Atasan, LinkedIn untuk dashboard/direktori. |
| 2026_11_04_000006_create_v_kuesioner_rekap_jawaban_view.php | v_kuesioner_rekap_jawaban | Rekap jawaban kompatibilitas/audit; statusnya legacy dan bukan sumber utama analitik baru. |

### 10.4 Migration database view analitik

| Migration | View | Service |
|---|---|---|
| 2026_10_25_000001_create_v_analytics_population_view.php | v_analytics_population | TracerAnalyticsService |
| 2026_10_25_000002_create_v_analytics_response_rate_view.php | v_analytics_response_rate | TracerAnalyticsService |
| 2026_10_25_000003_create_v_analytics_profile_completion_view.php | v_analytics_profile_completion | TracerAnalyticsService |
| 2026_10_25_000004_create_v_analytics_wait_time_view.php | v_analytics_wait_time | TracerAnalyticsService |
| 2026_10_25_000005_create_v_analytics_salary_ump_view.php | v_analytics_salary_ump | TracerAnalyticsService |
| 2026_10_25_000006_create_v_analytics_work_location_view.php | v_analytics_work_location | TracerAnalyticsService |
| 2026_10_25_000007_create_v_analytics_employment_status_view.php | v_analytics_employment_status | TracerAnalyticsService |
| 2026_10_25_000008_create_v_analytics_alignment_view.php | v_analytics_alignment | TracerAnalyticsService |
| 2026_10_25_000010_create_v_analytics_tracer_answers_view.php | v_analytics_tracer_answers | TracerAnalyticsService |
| 2026_10_25_000011_create_v_analytics_learning_methods_view.php | v_analytics_learning_methods | Alias/kompatibilitas; service baru memfilter tracer answers. |
| 2026_10_25_000012_create_v_analytics_job_search_start_view.php | v_analytics_job_search_start | Alias F3; service baru memfilter tracer answers. |
| 2026_10_25_000013_create_v_analytics_job_search_methods_view.php | v_analytics_job_search_methods | Alias F4; service baru memfilter tracer answers. |
| 2026_10_25_000014_create_v_analytics_application_pipeline_view.php | v_analytics_application_pipeline | Alias F6/F7/F7A; service baru memfilter tracer answers. |
| 2026_10_25_000015_create_v_analytics_job_search_activity_view.php | v_analytics_job_search_activity | Alias F10; service baru memfilter tracer answers. |
| 2026_10_25_000016_create_v_analytics_workplace_type_view.php | v_analytics_workplace_type | Alias F11; service baru memfilter tracer answers. |
| 2026_10_25_000017_create_v_analytics_further_study_view.php | v_analytics_further_study | Alias F12/F18b-F18d; service baru memfilter tracer answers. |
| 2026_10_25_000018_create_v_analytics_mismatch_reasons_view.php | v_analytics_mismatch_reasons | Alias F16; service baru memfilter tracer answers. |
| 2026_10_25_000019_create_v_analytics_competency_gap_view.php | v_analytics_competency_gap | Alias F17a/F17b; service baru memfilter tracer answers. |
| 2026_11_09_002941_create_v_iku_s1_lulusan_view.php | v_iku_s1_lulusan | IkuS1AnalyticsService dan chart IKU S1. |
| 2026_11_09_002942_create_v_iku_s1_aee_view.php | v_iku_s1_aee | IkuS1AnalyticsService dan chart AEE. |

## 11. Query dan sumber data per aktivitas

### 11.1 Direktori alumni

Alur query:

    AlumniDirectoryQueryService::getDirectoryData()
        -> mulai dari read-model alumni/detail
        -> filter tahun lulus dan scope prodi/fakultas/universitas
        -> filter status profile/univ/prodi/atasan
        -> filter wilayah, skills, bidang, unit, head office, LinkedIn
        -> eager load relasi untuk tabel
        -> format data untuk AlumniDirectoryShared.vue

Sumber kanonik profil tetap Biodata dan relasinya. View seperti
v_alumni_audit_rekap dan v_alumni_profile_summary hanya read-model/rekap,
bukan tabel baru untuk write.

### 11.2 Rekomendasi perusahaan

Alur query:

    Perusahaan Index Vue
        -> GET {prefix}/perusahaan/{id}/recommendations
        -> controller mengambil perusahaan pending
        -> PerusahaanVerificationService::getSimilarRecommendations()
        -> normalisasi nama
        -> token dan akronim
        -> pencocokan nama bertingkat
        -> bonus kesamaan kabupaten/provinsi
        -> response JSON ke modal Vue

Tidak ada tabel atau model Recommendation. Hasil rekomendasi dihitung dari
perusahaan yang sudah ada. Aksi replace mengubah referensi Biodata dari
perusahaan pengajuan ke master terverifikasi, lalu menulis LogActivity.

### 11.3 Grafik universitas

Alur query:

    GrafikUniv.vue
        -> GET {prefix}/analitik/data?grafik=...&tahun=...&tahun_ump=...&prodi_id=...
        -> TracerAnalyticsController::data()
        -> TracerAnalyticsService::buildUniversityChart()
        -> view analytics / tabel jawaban / UMP / perusahaan
        -> chart payload + records + methodology
        -> AnalyticsChartsPanel + category Vue

Vue tidak menentukan denominator response rate, status pekerjaan, status UMP,
bobot IKU, median, atau klasifikasi outcome. Semua itu dihitung service.

### 11.4 Grafik program studi

Alur query:

    GrafikProdi.vue
        -> GET {prefix}/analitik-prodi/data
        -> ProdiAnalyticsController::data()
        -> ProdiAnalyticsService::buildProdiChart()
        -> section aktif + placement + pertanyaan + jawaban
        -> visualizations, pivot, records, summary
        -> ProdiAnalyticsChartsPanel / ProdiPivotTable

Tipe pertanyaan menentukan formatter: Likert, choice, atau text. Jawaban teks
tidak dipaksa menjadi skor.

### 11.5 Grafik evaluasi atasan

Alur query:

    GrafikAtasan.vue
        -> GET {prefix}/analitik-atasan/data
        -> AtasanAnalyticsController::data()
        -> AtasanAnalyticsService::build()
        -> EvaluasiAtasan + KuesionerJawaban + Pertanyaan + Prodi
        -> question_stats, readiness, prodi_summary, records
        -> ECharts dan tabel detail

### 11.6 LinkedIn

Alur staging:

    URL biodata
        -> LinkedInProfileService/provider
        -> LinkedinSyncResult.scraped_data
        -> review admin
        -> approve/reject/plot again
        -> Biodata/Perusahaan/pendidikan lanjut diperbarui bila dipilih
        -> LogActivity + backup snapshot

Biro 3 dapat membaca trace pada detail alumni, tetapi route sync dan review
aktif hanya tersedia untuk Admin Prodi, Admin Fakultas, dan Superadmin.

## 12. Mail, provider, dan sumber data non-tabel

| File | Fungsi |
|---|---|
| app/Mail/PengingatKuesionerAlumniMail.php | Pengingat alumni dari direktori admin. |
| app/Mail/UndanganEvaluasiAtasanMail.php | Link evaluasi atasan bertoken. |
| app/Mail/KonfirmasiEvaluasiAtasanTerkirimMail.php | Konfirmasi undangan berhasil dikirim. |
| app/Mail/ResetPasswordMail.php | Link reset password. |
| storage/app/mock/apify | Fixture provider Apify lokal. |
| storage/app/backup/linkedin_sync_results_backup.json | Snapshot hasil sync LinkedIn untuk preservasi data staging. |
| public/geojson/indonesia-provinsi.json | Geometri peta analitik/lokasi. |
| public/geojson/indonesia-kabupaten.json | Geometri pendukung peta landing/wilayah. |
| database/seeders/data/alumni_532_real_cohort.json | Fixture cohort alumni. |
| database/seeders/data/unified_kuesioner_instruments.json | Fixture instrumen kuesioner. |

## 13. Daftar file Vue yang tidak boleh terlewat saat mengubah fitur

| Area | File |
|---|---|
| Chart universitas | components/analytics/AnalyticsChartsPanel.vue, AnalyticsEChart.vue, AnalyticsMap.vue, AnalyticsTabbedContainer.vue, AnalyticsWordCloud.vue, AnalyticsChartCard.vue, analyticsChartOptions.js, categories/01_ResponseRate.vue sampai 21_IkuS1UmpWeighted.vue |
| Chart prodi | components/prodi-analytics/ProdiAnalyticsChartsPanel.vue, ChoiceQuestionsChart.vue, ProdiPivotTable.vue, prodiChartOptions.js, categories/01_InterestAndContribution.vue sampai 09_HopesAndFeedback.vue |
| Audit alumni | components/AlumniAudit/AlumniStepper.vue, TabProfil.vue, TabKuesionerUniv.vue, TabKuesionerProdi.vue, TabEvaluasiAtasan.vue, TabLinkedin.vue |
| Form profil | components/AlumniProfil/Stepper.vue, FormPribadi.vue, FormAkademik.vue, FormKarier.vue |
| Direktori/detail | Pages/Alumni/Components/AlumniDirectoryShared.vue, AlumniDetailShared.vue, seluruh wrapper Index.vue/Show.vue role admin |
| Konfigurasi | Pages/Shared/Kuesioner/ProdiKuesionerIndex.vue, SuperAdmin/Pertanyaan/Index.vue, Section/Index.vue, EvaluasiAtasan/Index.vue, Perusahaan/Index.vue |
| Infrastruktur UI | AdminSidebar.vue, AdminPageHeader.vue, RoleDashboard.vue, PaginationControls.vue, searchable-select.vue, pigo-loader.vue |

## 14. Catatan arsitektur penting

1. Controller analitik berada di namespace SuperAdmin, tetapi route dan halaman
   dipakai juga oleh Admin Prodi, Admin Fakultas, dan Biro 3. Namespace tidak
   selalu berarti role pemakai tunggal.
2. Perusahaan punya tiga controller role tetapi satu service domain dan satu
   halaman Vue bersama. Perbedaan utama ada pada query scope dan middleware.
3. Profil admin punya wrapper Vue per role tetapi komponen detail/direktori
   dibagi lewat AlumniDirectoryShared dan AlumniDetailShared.
4. Grafik memiliki satu halaman induk per jenis analitik dan banyak komponen
   kategori. Perubahan rumus dilakukan di service; perubahan tampilan chart
   dilakukan di kategori Vue atau option builder.
5. Database view adalah read-model. Jangan menyimpan perubahan bisnis ke view.
6. account_sync_settings belum memiliki Model Eloquent; aksesnya masih langsung
   melalui DB facade di ManajemenAkunController.
7. Admin Biro 3 memiliki route update profile pada detail, tetapi controller
   secara eksplisit menolak perubahan. Dokumentasi dan UI harus mempertahankan
   status read-only tersebut.
8. v_analytics_* lama untuk kategori spesifik masih ada sebagai alias/kompatibilitas,
   tetapi service aktif mengutamakan v_analytics_tracer_answers untuk banyak
   indikator.

## 15. Checklist perubahan fitur

Saat mengubah suatu aktivitas, telusuri minimal:

- route dan role middleware;
- controller method dan props Inertia/JSON;
- service/query dan method yang dipanggil;
- model serta relasi;
- tabel kanonik dan view yang menjadi sumber;
- migration pembentuk tabel/view;
- halaman Vue;
- komponen Vue yang menerima props tersebut;
- chart option/category bila analitik;
- audit log, mail, provider, atau backup bila ada efek samping;
- test terdekat dan kontrak data yang terdampak.

Dokumen terkait yang perlu dibaca bersama dokumen ini adalah
docs/panduan-analitik.md, docs/database-views.md,
docs/kuesioner-data-contract.md, docs/verifikasi-perusahaan-similarity.md,
dan docs/linkedin-mcp-integration.md.



## 16. Coverage file saat dokumen ini dibuat

Pemetaan ini mencakup inventory berikut dari repository:

| Area | Jumlah file |
|---|---:|
| app/Http/Controllers | 42 |
| app/Services | 29 |
| app/Models | 28 |
| resources/js/Pages | 55 |
| resources/js/components | 66 |
| database/migrations | 58 |

Untuk menghindari pengulangan path yang terlalu panjang, tabel kategori chart
menulis folder relatif components/analytics dan components/prodi-analytics.
Path lengkapnya adalah resources/js/components/analytics dan
resources/js/components/prodi-analytics.

## 17. Ringkasan file yang dipakai bersama

| File/folder | Dipakai oleh |
|---|---|
| app/Services/Alumni/AlumniDirectoryQueryService.php | Direktori Admin Prodi, Fakultas, Biro 3, dan Superadmin. |
| app/Services/Alumni/AdminAlumniProfileService.php | Profil Alumni, detail alumni Prodi/Fakultas/Superadmin/Biro 3, evaluator publik. |
| app/Services/Kuesioner/KuesionerSyncService.php | Profil, kuesioner umum, kuesioner prodi, evaluator publik, detail admin. |
| app/Services/Perusahaan/PerusahaanVerificationService.php | Verifikasi perusahaan Prodi/Fakultas/Superadmin dan mapping LinkedIn. |
| app/Services/Analytics/TracerAnalyticsService.php | Route analitik Biro 3, Prodi, Fakultas, dan Superadmin. |
| app/Services/Analytics/ProdiAnalyticsService.php | Analitik Prodi/Fakultas/Biro 3/Superadmin dengan pembatasan scope. |
| app/Services/Analytics/AtasanAnalyticsService.php | Analitik evaluasi atasan seluruh role admin. |
| app/Http/Controllers/Shared/LinkedInSyncController.php | Route LinkedIn Prodi, Fakultas, dan Superadmin. |
| resources/js/Pages/SuperAdmin/Perusahaan/Index.vue | UI verifikasi perusahaan Prodi, Fakultas, dan Superadmin. |
| resources/js/Pages/Alumni/Components/AlumniDirectoryShared.vue | Wrapper direktori alumni semua role admin. |
| resources/js/Pages/Alumni/Components/AlumniDetailShared.vue | Wrapper detail alumni semua role admin. |
| resources/js/components/analytics/* | Semua grafik tracer universitas dan IKU S1. |
| resources/js/components/prodi-analytics/* | Semua grafik kuesioner program studi. |

## 18. Checklist ketika menambah atau mengubah fitur

Saat mengubah satu aktivitas, telusuri minimal:

- route dan role middleware;
- controller method dan props Inertia/JSON;
- service/query dan method yang dipanggil;
- model serta relasi;
- tabel kanonik dan view yang menjadi sumber;
- migration pembentuk tabel/view;
- halaman Vue;
- komponen Vue yang menerima props tersebut;
- chart option/category bila analitik;
- audit log, mail, provider, atau backup bila ada efek samping;
- test terdekat dan kontrak data yang terdampak.

Dokumen terkait yang perlu dibaca bersama dokumen ini:

- docs/panduan-analitik.md
- docs/database-views.md
- docs/kuesioner-data-contract.md
- docs/verifikasi-perusahaan-similarity.md
- docs/linkedin-mcp-integration.md

