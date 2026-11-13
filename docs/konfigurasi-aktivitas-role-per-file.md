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

## 3A. Tabel konfigurasi utama seluruh aktivitas (format spreadsheet)

Bagian ini adalah format utama untuk seluruh aktivitas aplikasi, bukan hanya
untuk direktori atau halaman Vue tertentu. Setiap baris mewakili satu aktivitas
bisnis dan menghubungkan semua layer sekaligus: role, controller, method,
service, Vue, model, tabel, database view, migration, dan query.

Bagian 4 sampai akhir tetap dipertahankan sebagai lampiran penjelasan per file.
Artinya, tabel pada bagian ini adalah peta lintas layer, sedangkan lampiran
dipakai jika perlu membaca isi file atau method secara lebih rinci.

Jika satu service dipakai banyak controller, semua controller pemakainya ditulis
pada kolom Service agar hubungan tersebut langsung terlihat.

Kolom yang dipakai:

- Controller: file dan method yang menerima request.
- Service / isi logika: service yang dipanggil atau keterangan jika controller
  masih melakukan query langsung.
- Vue: halaman dan komponen yang dipakai user.
- Database: model, tabel, view, dan migration. View database ditulis eksplisit,
  bukan hanya model.
- Query utama: tujuan query, filter, scope, dan agregasi.

| Nama Aktivitas | Role/pemakai | Controller | Penjelasan Controller | Method + penjelasan | Service / isi logika | Penjelasan Service dan controller pemakai | Method service + penjelasan | Vue | Penjelasan Vue | Database (Model + tabel/view + migration) | Penjelasan Database | Query utama |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Login, logout, lupa/reset password | Semua role; reset dimulai guest | LoginController.php; LupaKataSandiController.php; UbahKataSandiController.php | Mengatur autentikasi, redirect role, token reset, dan password wajib. | LoginController: tampilkanHalamanLogin, prosesLogin, prosesLogout. LupaKataSandiController: prosesKirimLinkReset, tampilkanHalamanResetKataSandi, prosesResetKataSandi. UbahKataSandiController: tampilkanUbahKataSandi, prosesUbahKataSandi. | PasswordResetNotificationService.php; LoginController dan UbahKataSandiController query langsung. | Service hanya menangani notifikasi/reset token. Login dan ganti password membaca User langsung. | PasswordResetNotificationService::send membuat token, menyimpan token, dan mengirim mail. | Login.vue; ForgotPassword.vue; ResetPassword.vue; UbahKataSandi.vue; AuthBackdrop.vue. | Form login/reset/password dan pesan validasi/flash. | Model User; tabel users, password_reset_tokens, sessions; migration create_users_table, create_password_reset_tokens_table, create_sessions_table. | Menyimpan akun, role, password, token reset, dan session. | Cari user berdasarkan email/login; validasi token reset; update password dan must_change_password. |
| Dashboard | Alumni, Admin Prodi, Admin Fakultas, Biro 3, Superadmin | DashboardController pada folder Alumni, AdminProdi, AdminFakultas, AdminBiroTiga, SuperAdmin. | Membangun props statistik dan memilih scope role. | Alumni: tampilkanDashboard. Admin: tampilkanDashboard dengan scope prodi/fakultas/universitas. Superadmin: tampilkanDashboard dengan query agregat langsung. | RoleDashboardDataService.php dipakai AdminProdi, AdminFakultas, AdminBiroTiga. KelengkapanTracerService dipakai dashboard Alumni. Superadmin query langsung. | Menghasilkan response rate, completion, ringkasan perusahaan, alumni terbaru, prodi, section, dan log terbaru. | RoleDashboardDataService::build; KelengkapanTracerService::evaluasiKelengkapanTotal. | Alumni/Dashboard.vue; AdminProdi/Dashboard.vue; AdminFakultas/Dashboard.vue; AdminBiroTiga/Dashboard.vue; SuperAdmin/Dashboard.vue; RoleDashboard.vue. | Menampilkan KPI, progress, ringkasan, tabel alumni, perusahaan, dan aktivitas sesuai role. | Model AlumniAuditRekap, Biodata, Prodi, Perusahaan, LogActivity; view v_alumni_audit_rekap; migration create_v_alumni_audit_rekap_view; tabel biodata, prodi, perusahaan, log_activities. | v_alumni_audit_rekap adalah read-model status Profil/Univ/Prodi/Atasan dan bukan tabel write. | Filter tahun dan scope prodi/fakultas; hitung population, responden, completion, pending company, LinkedIn, dan recent records. |
| Profil alumni | Alumni | Alumni/Profil/ProfilController.php; Alumni/Profil/SimpanProfilController.php | Mengambil kontrak form dan menyimpan profil/karier/atasan. | ProfilController::tampilkanHalamanProfil. SimpanProfilController::simpanPerubahanProfil. | AdminAlumniProfileService.php dan KuesionerSyncService.php dipakai ProfilController, SimpanProfilController, EvaluasiAtasanController, serta detail alumni admin. | Membentuk form normalisasi, referensi, pertanyaan profil dinamis, update field kanonik, dan sinkronisasi jawaban profil. | buildFormData; getRefOptions; getProfileQuestionConfigs; updateProfile; syncProfileResponses; syncDynamicProfileResponses. | Alumni/Profil/Index.vue; AlumniProfil/FormPribadi.vue; FormAkademik.vue; FormKarier.vue; Stepper.vue. | Form dibagi tab pribadi, akademik, dan karier; menangani wilayah bertingkat, pekerjaan, perusahaan, atasan, gaji, dan studi lanjut. | Model Biodata, DataAkademik, Yudisium, User, Perusahaan, Atasan, Propinsi, Kabupaten, RefNegara; tabel biodata, data_akademik, yudisium, users, perusahaan, atasan, propinsi, kabupaten, ref_negara; migration pembentuk masing-masing tabel; view v_alumni_kuesioner_autofill untuk autofill. | Biodata adalah sumber profil aktif. Data akademik/yudisium menjadi referensi akademik. View autofill hanya sumber baca awal. | Load user->biodata dan relasi; ambil opsi wilayah/negara/perusahaan; update biodata, user, perusahaan, atasan; sinkronkan jawaban profil. |
| Kuesioner umum tracer | Alumni | Alumni/Kuesioner/KuesionerController.php; SimpanJawabanController.php | Menampilkan instrumen aktif dan memproses jawaban umum. | KuesionerController::tampilkanKuesioner. SimpanJawabanController::simpanJawabanKuesioner. | KuesionerSyncService, KelengkapanTracerService, F3AnswerParser; SimpanJawabanController juga meng-update model bisnis berdasarkan QuestionMapping. | Menentukan pertanyaan wajib, skip logic, autofill, parsing F3/matriks, dan sinkronisasi field profil/perusahaan/atasan. | getDynamicProfileQuestions; syncProfileResponses; evaluasiKuesionerWajib; parse. | Alumni/Kuesioner.vue; KartuPertanyaan.vue; Navigasi.vue; Stepper.vue; Banner.vue; TabelF2.vue; TabelF17.vue. | Render tipe pertanyaan, pilihan, matriks, progress, skip logic, dan submit jawaban. | Model Kuesioner, KuesionerSection, KuesionerPertanyaan, KuesionerOpsi, KuesionerJawaban, QuestionMapping; tabel kuesioner, kuesioner_section, kuesioner_pertanyaan, kuesioner_opsi, kuesioner_jawaban; view v_question_mappings, v_alumni_kuesioner_autofill, v_alumni_tracer_univ_status; migration create_*_kuesioner dan create_v_* terkait. | Pertanyaan/opsi/jawaban adalah tabel kanonik. View mapping dan autofill membantu membaca metadata, sedangkan status view menghitung progres. | Ambil instrumen/section/placement aktif; join jawaban berdasarkan biodata dan pertanyaan; simpan jawaban teks/JSON; pilih opsi/skor; hitung mandatory denominator. |
| Kuesioner khusus program studi | Alumni | Alumni/Kuesioner/KuesionerProdiController.php | Memuat placement kuesioner prodi sesuai prodi alumni dan menyimpan jawaban. | tampilkanKuesionerProdi; simpanJawaban. | KuesionerSyncService dipakai untuk syncProdiResponses dan resolusi field akademik. | Menghubungkan jawaban prodi dengan profil/status prodi tanpa mencampur jawaban universitas. | syncProdiResponses; resolveAcademicIdentityField. | Alumni/KuesionerProdi.vue; KartuPertanyaanProdi.vue; Navigasi.vue; Stepper.vue; Banner.vue; TabelMatriksProdi.vue. | Form section/placement prodi, tipe pertanyaan, matriks, progress, dan submit. | Model KuesionerSection, KuesionerPertanyaan, KuesionerSectionPertanyaan, KuesionerJawaban, Prodi; view v_alumni_tracer_prodi_status; migration kuesioner_section, kuesioner_pertanyaan, kuesioner_section_pertanyaan, kuesioner_jawaban, dan status view. | Placement menentukan pertanyaan yang berlaku untuk prodi. View status menghitung jumlah dan jawaban aktif. | Filter section berdasarkan prodi_id, placement active, biodata_id, dan responden_type; simpan jawaban dan hitung status. |
| Tambah/usul perusahaan dan undangan evaluasi | Semua lima role aplikasi | Alumni/Profil/SimpanProfilController.php | Endpoint bersama untuk membuat perusahaan baru, mengusulkan perubahan perusahaan, dan mengirim undangan atasan. | tambahPerusahaanBaru; usulkanPembaruanPerusahaan; kirimEmailAtasan. | Tidak memakai service perusahaan khusus untuk write awal; controller melakukan validasi/update langsung dan memanggil KuesionerSyncService. Mail memakai UndanganEvaluasiAtasanMail dan KonfirmasiEvaluasiAtasanTerkirimMail. | Perusahaan baru berstatus menunggu verifikasi; data atasan dan token evaluasi dihubungkan ke biodata. | KuesionerSyncService::syncProfileResponses; mail send. | Modal pada Alumni/Profil/Index.vue dan FormKarier.vue; detail admin dapat menampilkan data hasilnya. | User mengisi nama perusahaan, data lokasi, atasan, dan email undangan. | Model Perusahaan, Biodata, Atasan, EvaluasiAtasan, LogActivity; tabel perusahaan, biodata, atasan, evaluasi_atasan, log_activities; migration create_perusahaan_table, create_biodata_table, create_atasan_table, create_evaluasi_atasan_table, create_log_activities_table. | Tidak ada tabel usulan terpisah; status pengajuan disimpan pada perusahaan.status_verifikasi. | Insert/update perusahaan; hubungkan biodata.perusahaan_id; insert/update atasan; buat token evaluasi; catat log dan kirim email. |
| Direktori, detail, audit, dan export alumni | Admin Prodi, Fakultas, Biro 3, Superadmin | DaftarAlumniProdiController.php; DaftarAlumniFakultasController.php; AdminBiroTiga/KelolaAlumni/DaftarAlumniController.php; DaftarAlumniSuperAdminController.php; controller DetailAlumni masing-masing role. | Memilih scope, menyusun detail audit, mengirim pengingat, dan mengunduh Excel/ZIP. | index/tampilkanDaftarAlumni; show/tampilkanDetailAlumni; updateProfile; exportExcel; exportZip; sendReminderEmail; blastEmail. | AlumniDirectoryQueryService, AdminAlumniProfileService, UnifiedDetailAlumniService, KelengkapanTracerService, KuesionerSyncService, LinkedinTraceHelper, AlumniTracerExcelExporter. Dipakai oleh controller direktori/detail empat role. | Query direktori dan detail disentralisasi; controller hanya menentukan scope, response, email, dan file download. | getDirectoryData; buildFormData; getUnivSectionsWithAnswers; getProdiSectionsWithAnswers; evaluasiKelengkapanTotal; download; generateExcelContent. | Wrapper role Index/Show; AlumniDirectoryShared.vue; AlumniDetailShared.vue; AlumniAudit/*; AlumniProfil/*. | Direktori memiliki filter/pagination/komunikasi/LinkedIn; detail memiliki tab Profil, Univ, Prodi, Atasan, LinkedIn. | Model AlumniAuditRekap, AlumniProfileSummary, AlumniTracerUnivStatus, AlumniTracerProdiStatus, AlumniLinkedinPlot, Biodata, User, DataAkademik, Yudisium, Perusahaan, Atasan, EvaluasiAtasan, LinkedinSyncResult; view v_alumni_audit_rekap, v_alumni_profile_summary, v_alumni_tracer_univ_status, v_alumni_tracer_prodi_status, v_alumni_linkedin_plot; migration create_v_* dan tabel kanonik. | View read-model mengurangi duplikasi query status. Write profil hanya role yang diizinkan; Biro 3 menolak update profile. | Filter server-side berdasarkan tahun, scope, status, wilayah, skill, bidang, unit, head office, LinkedIn; detail eager-load relasi dan section/jawaban; export menggabungkan beberapa sumber. |
| Direktori data atasan | Admin Prodi/Fakultas/Biro 3 baca; Superadmin CRUD | Shared/DataAtasan/DaftarAtasanController.php | Menampilkan data atasan sesuai role; hanya Superadmin memiliki route store/update. | index; store; update. | AtasanDirectoryQueryService dipakai index. store/update memakai model langsung. | Scope query berdasarkan role, pencarian, relasi alumni/perusahaan, dan statistik atasan. | getDirectoryData; buildQuery; applyRoleScope; formatRecord; buildStats. | Shared/DataAtasan/Index.vue. | Tabel atasan, filter, pagination, dan form CRUD Superadmin. | Model Atasan, Biodata, Perusahaan, User, Prodi, RefFakultas; tabel atasan, biodata, perusahaan, users, prodi, ref_fakultas; migration create_atasan_table dan migration tabel relasi. | Data atasan terhubung ke banyak biodata dan evaluasi. | Query search nama/email/perusahaan; apply scope prodi/fakultas/universitas; eager-load relasi; aggregate jumlah alumni/evaluasi. |
| Verifikasi perusahaan dan rekomendasi | Admin Prodi, Admin Fakultas, Superadmin | AdminProdi/VerifikasiPerusahaanProdiController.php; AdminFakultas/VerifikasiPerusahaanFakultasController.php; SuperAdmin/Perusahaan/VerifikasiPerusahaanSuperAdminController.php | Controller terpisah menjaga middleware dan scope, tetapi proses bisnis sama. | index; verify; replace; updateAndVerify; reject; searchVerified; recommendations; destroy; Superadmin juga store. | PerusahaanVerificationService.php dipakai ketiga controller dan LinkedInProfileMapper. | Cleaning nama, token, akronim, pencocokan bertingkat, rekomendasi geografis, verify, auto-replace, update, reject. | getPendingForProdi; getPendingForFakultas; getSimilarRecommendations; searchVerified; replaceCompany; verifyDirectly; updateAndVerify; rejectCompany; cleanCompanyName; cleanString; getTokens; makeAcronym; getAcronyms. | SuperAdmin/Perusahaan/Index.vue dipakai bersama dengan basePath berbeda. | Fetch rekomendasi JSON, modal detail, verify direct, replace, edit/verify, reject, delete, search master. | Model Perusahaan, Biodata, Prodi, Propinsi, Kabupaten, RefNegara, LogActivity; tabel perusahaan, biodata, prodi, wilayah, log_activities; migration create_perusahaan_table dan migration relasi; tidak ada view khusus rekomendasi. | Rekomendasi dihitung runtime dari data perusahaan; tidak ada Recommendation model/table. Replace mengubah foreign key biodata.perusahaan_id dan mencatat log. | Query pending dengan scope prodi/fakultas; normalisasi nama; similarity matching; bonus kabupaten/provinsi; transaction update references. |
| Kelola kuesioner prodi | Admin Prodi, Fakultas, Superadmin | Shared/Kuesioner/KelolaKuesionerProdiController.php; AdminProdi/KelolaPertanyaan/KelolaSectionProdiController.php untuk section sederhana. | Controller Shared mengelola full instrument; controller Prodi lama mengelola section route /prodi/sections. | Shared: index, CRUD section/question/option, reorder, toggle active, attachFromBank. Prodi khusus: index, store, update, destroy, reorder. | QuestionDefaultOptionsService dipakai Shared controller; section sederhana melakukan Eloquent langsung. | Membuat opsi default dan mengelola bank pertanyaan/placement antar prodi. | generateForUnifiedQuestion; query placement/section; update order/is_active; attach bank. | Shared/Kuesioner/ProdiKuesionerIndex.vue; AdminProdi/Section/Index.vue. | CRUD section, pertanyaan, opsi, placement, bank soal, reorder, toggle. | Model KuesionerSection, KuesionerPertanyaan, KuesionerSectionPertanyaan, KuesionerOpsi, Prodi, LogActivity; tabel kuesioner_section, kuesioner_pertanyaan, kuesioner_section_pertanyaan, kuesioner_opsi, prodi, log_activities; migration create_* kuesioner. | Satu bank pertanyaan dapat ditempatkan ke banyak section/prodi melalui placement. | Pilih prodi berdasarkan user/query; query section/placement/question; transaction reorder; attach question dan default options. |
| Kelola kuesioner universitas | Superadmin | SuperAdmin/KelolaPertanyaan/DaftarPertanyaanController.php; SimpanPertanyaanController.php; KelolaOpsiController.php; KelolaSection/KelolaSectionController.php; KelolaKuesionerController.php | Memecah konfigurasi instrumen, section, pertanyaan, opsi, active/visibility, dan bank soal. | index; store/update/destroy; toggleActive/toggleVisibility; reorder; attachFromBank; storeOption/updateOption/destroyOption; CRUD section/kuesioner. | QuestionDefaultOptionsService dipakai SimpanPertanyaanController; controller lain query/update model langsung. | Mengelola master instrumen yang dibaca Alumni/KuesionerController dan analitik. | generateForUnifiedQuestion; CRUD dan reorder model kuesioner. | SuperAdmin/Pertanyaan/Index.vue; SuperAdmin/Section/Index.vue. | Form master pertanyaan, opsi, section, bank soal, status active/visibility. | Model Kuesioner, KuesionerSection, KuesionerPertanyaan, KuesionerSectionPertanyaan, KuesionerOpsi, LogActivity; tabel kuesioner_*; view v_question_mappings; migration create_kuesioner_table, create_kuesioner_section_table, create_kuesioner_pertanyaan_table, create_kuesioner_section_pertanyaan_table, create_kuesioner_opsi_table, add_label_pertanyaan. | Data konfigurasi menjadi sumber form kuesioner umum dan mapping analitik. | Query instrumen/section/placement; update order, active, visibility; create option berdasarkan tipe; attach bank question. |
| Isi evaluasi atasan publik | Publik/evaluator memakai token | EvaluasiAtasanController.php | Menyajikan form token, memvalidasi evaluasi, dan menyimpan respons. | index; show(token); store(token). | AdminAlumniProfileService untuk referensi; KuesionerSyncService untuk sinkronisasi profil; tidak ada Evaluation service khusus. | Menyimpan data atasan/perusahaan/profil yang dikonfirmasi evaluator dan jawaban evaluasi. | getRefOptions; syncProfileResponses. | Public/EvaluasiAtasanForm.vue; EvaluasiAtasanNotFound.vue. | Form publik tanpa login, validasi token, pertanyaan aktif, dan submit. | Model EvaluasiAtasan, Atasan, Biodata, Perusahaan, KuesionerPertanyaan, KuesionerJawaban; tabel evaluasi_atasan, atasan, biodata, perusahaan, kuesioner_*; migration create_evaluasi_atasan_table dan migration kuesioner. | Token/status disimpan pada evaluasi_atasan; jawaban terhubung ke evaluasi dan pertanyaan. | Cari evaluasi berdasarkan token; lock record; load active questions/options; transaction update profile/atasan/company dan insert jawaban. |
| Kelola instrumen evaluasi atasan | Superadmin | SuperAdmin/KelolaEvaluasiAtasanController.php | CRUD section, pertanyaan, opsi, reorder, active, dan attach bank untuk evaluasi atasan. | index; store/update/destroy section; reorderSections; store/update/destroy/toggleQuestion/reorderQuestions; store/update/destroy options; attachFromBank. | QuestionDefaultOptionsService dipakai untuk opsi default. | Instrumen memakai struktur pertanyaan yang sama agar form publik dan analitik konsisten. | generateForUnifiedQuestion; CRUD kuesioner section/question/option. | SuperAdmin/EvaluasiAtasan/Index.vue. | UI konfigurasi section, pertanyaan, opsi, status, urutan, bank soal. | Model KuesionerSection, KuesionerPertanyaan, KuesionerSectionPertanyaan, KuesionerOpsi, EvaluasiAtasan, LogActivity; tabel kuesioner_*, evaluasi_atasan, log_activities; migration terkait. | Evaluasi atasan memakai tabel instrumen bersama, dibedakan oleh konteks/responden_type dan relasi evaluasi. | Query section/placement; CRUD dan reorder; toggle active; attach bank; log perubahan. |
| Analitik tracer universitas dan IKU S1 | Admin Prodi/Fakultas/Biro 3/Superadmin | SuperAdmin/Analytics/TracerAnalyticsController.php | Menyajikan halaman, JSON chart, dan export response rate untuk semua prefix admin. | index; data; exportResponseRate. | TracerAnalyticsService.php dipakai index/data; ResponseRateExcelExporter dipakai export. IkuS1AnalyticsService dipakai dari TracerAnalyticsService. | Menghasilkan semua angka, label, denominator, records, insight, methodology, map, dan IKU. | build; buildUniversityChart; resolveTargetResponseRate; IkuS1AnalyticsService::buildAeeChart/buildOutcomeChart/buildUmpWeightedChart. | SuperAdmin/Analytics/GrafikUniv.vue; AnalyticsChartsPanel; 21 category chart; AnalyticsEChart; AnalyticsMap; AnalyticsTabbedContainer; AnalyticsWordCloud. | Filter chart/tahun/UMP/prodi, KPI, chart, map, drill-down, table, methodology, export. | Model/view: AlumniAuditRekap, Ump, Prodi, Biodata, KuesionerJawaban; view v_analytics_population, v_analytics_response_rate, v_analytics_profile_completion, v_analytics_wait_time, v_analytics_salary_ump, v_analytics_work_location, v_analytics_employment_status, v_analytics_alignment, v_analytics_tracer_answers, v_iku_s1_aee, v_iku_s1_lulusan; migration create_v_analytics_* dan create_v_iku_s1_*. | Read-model analitik memisahkan populasi, jawaban, profile completion, wait time, salary/UMP, lokasi, status, alignment, dan IKU. | Filter tahun/prodi/UMP/chart; latest answer per alumni/question; aggregate count/percentage/median/percentile; classify outcome; build records. |
| Analitik kuesioner prodi | Admin Prodi/Fakultas/Biro 3/Superadmin | SuperAdmin/Analytics/ProdiAnalyticsController.php | Menyajikan halaman dan JSON analitik prodi; Admin Prodi dipaksa ke prodi user. | index; data. | ProdiAnalyticsService.php. | Memformat section berdasarkan tipe question, membentuk visualizations/pivot/records/insight. | buildProdiChart; getResponses; formatLikertQuestion; formatChoiceQuestion; formatTextQuestion; buildInsights. | SuperAdmin/Analytics/GrafikProdi.vue; ProdiAnalyticsChartsPanel; category 01-09; ChoiceQuestionsChart; ProdiPivotTable; AnalyticsEChart/WordCloud. | Filter prodi/section/tahun, chart per section, pivot, record detail, kontak alumni, drill-down. | Model Prodi, KuesionerSection, KuesionerPertanyaan, KuesionerSectionPertanyaan, KuesionerJawaban, DataAkademik; view v_alumni_tracer_prodi_status dan v_analytics_tracer_answers bila diperlukan; migration kuesioner dan view status. | Data chart berasal dari jawaban placement aktif; frontend tidak menghitung skor/denominator. | Filter prodi/section/year; query active placement; ambil jawaban terbaru; aggregate per option/score; bentuk records/pivot. |
| Analitik evaluasi atasan | Admin Prodi/Fakultas/Biro 3/Superadmin | SuperAdmin/Analytics/AtasanAnalyticsController.php | Menyajikan halaman dan JSON analitik evaluasi atasan. | index; data. | AtasanAnalyticsService.php. | Menghitung skor aspek, distribusi, readiness, summary prodi, dan records. | build; evaluations; questionStats; prodiSummary; records. | SuperAdmin/Analytics/GrafikAtasan.vue; AnalyticsEChart; PaginationControls. | Chart skor rata-rata aspek, detail distribusi aspek, kesiapan kerja, perbandingan prodi, tabel evaluator/alumni. | Model EvaluasiAtasan, KuesionerJawaban, KuesionerPertanyaan, Atasan, Biodata, Prodi; tabel evaluasi_atasan, kuesioner_jawaban, kuesioner_pertanyaan; migration evaluasi/kuesioner. | Pertanyaan Likert dan pilihan dihitung dari respons evaluasi yang submitted. | Filter tahun/prodi; load evaluasi submitted; join response/question; count distribution; average score; group by prodi. |
| Sinkronisasi LinkedIn | Admin Prodi/Fakultas/Superadmin; Biro 3 baca trace | Shared/LinkedInSyncController.php | Mengatur sync, staging, review, approval/reject, plot ulang, histori, dan batch. | index; updateUrl; syncSingle; syncBatch; showResult; approveResult; plotAgain; rejectResult; history. | LinkedInProfileService, provider Apify/API/mock, LinkedInProfileMapper, LinkedinTraceHelper, PerusahaanVerificationService. Controller ini dipakai route Prodi/Fakultas/Superadmin. | Provider mengambil profil; mapper menormalisasi posisi/perusahaan; hasil disimpan staging; approval baru boleh menulis profil. | syncBiodata; syncBatch; findByUrl/findByUsername; determineCurrentPosition; mapToBiodata; resolveCompany; getProfileItem/getPreviewData. | SuperAdmin/LinkedIn/Index.vue; AlumniDirectoryShared LinkedIn modal; TabLinkedin.vue; AnalyticsEChart tidak terlibat. | Input URL, sync single/batch, preview JSON, review field, approve/reject/plot again, histori. | Model LinkedinSyncResult, AlumniLinkedinPlot, Biodata, Perusahaan, User, LogActivity; tabel linkedin_sync_results, biodata, perusahaan, log_activities; view v_alumni_linkedin_plot; migration create_linkedin_sync_results_table dan create_v_alumni_linkedin_plot_view. | scraped_data adalah staging JSON. Approval/reject menjaga histori; view menampilkan status terbaru dan kecocokan. | Validasi URL; provider request/mock; insert result; ambil latest result; approve dengan transaction update biodata/company/studi; catat log dan backup. |
| Master wilayah, negara, dan UMP | Superadmin | SuperAdmin/MasterWilayah/MasterWilayahController.php | CRUD master wilayah dan endpoint cascading kabupaten. | index; store/update/destroyProvinsi; store/update/destroyKabupaten; getKabupatenByProvinsi; storeOrUpdateUmp; destroyUmp; copyYearUmp; store/update/destroyNegara. | Tidak ada service khusus; query Eloquent/DB langsung di controller. | Controller langsung memvalidasi, menyimpan, menghapus, dan mencatat log. | Query model Propinsi/Kabupaten/RefNegara/Ump; DB transaction bila diperlukan. | SuperAdmin/MasterWilayah/Index.vue; searchable-select.vue. | Tab master provinsi, kabupaten, negara, UMP; kabupaten diambil async berdasarkan provinsi. | Model Propinsi, Kabupaten, RefNegara, Ump, Perusahaan; tabel propinsi, kabupaten, ref_negara, ump, perusahaan; migration create_propinsi_table, create_kabupaten_table, create_ref_negara_table, create_ump_table. | Menjadi referensi form profil, perusahaan, salary vs UMP, dan filter wilayah. | Query list/pagination/search; query kabupaten where kode_provinsi; upsert UMP per province/year; copy year. |
| Manajemen akun dan sinkronisasi alumni | Superadmin | SuperAdmin/ManajemenAkunController.php | Daftar/filter/edit akun, reset password, kontrol sinkronisasi, dan pembuatan akun alumni dari data akademik. | index; updateSyncAvailability; update; sendPasswordResetLink; syncAlumniAccounts. | PasswordResetNotificationService dipakai reset link; sinkronisasi lain query DB/model langsung. | Account sync memakai flag account_sync_settings dan mencocokkan DataAkademik/Biodata/User. | PasswordResetNotificationService::send; getAccountSyncSetting; syncAlumniAccounts. | SuperAdmin/ManajemenAkun/Index.vue. | Filter role, edit user, reset password, tombol sinkronisasi, status buka/tutup. | Model User, DataAkademik, Biodata, Prodi, RefFakultas; tabel users, data_akademik, biodata, prodi, ref_fakultas, account_sync_settings; migration create_account_sync_settings_table. | account_sync_settings belum memiliki model Eloquent; dibaca langsung memakai DB facade. | Query user dengan filter role/scope; cari data akademik yang belum punya akun; insert/update users dan biodata; update setting. |
| Log aktivitas/audit trail | Superadmin; ditulis oleh banyak proses | SuperAdmin/LogAktivitasController.php dan LogActivity::record dari controller/service lain | Menampilkan audit, bukan membuat aktivitas bisnis baru. | index; filter action/user/date/search; pagination. | Tidak ada service khusus. LogActivity model menyediakan record helper. | Menyimpan actor, action, description, IP, dan waktu dari verifikasi, akun, LinkedIn, konfigurasi, dan proses penting. | LogActivity::record; query index filter/pagination. | SuperAdmin/Logs/Index.vue. | Filter tabel log dan reload state Inertia. | Model LogActivity; tabel log_activities; migration create_log_activities_table. | Audit trail bersifat append/read; tidak menjadi sumber data analitik utama. | Query latest log with user; where action/user/date/search; paginate. |

### Cara menggunakan master table

Jika ingin mencari semua controller yang memakai satu service, cari nama service
pada kolom Service / isi logika lalu lihat kolom Controller. Contoh:

- PerusahaanVerificationService dipakai tiga controller verifikasi perusahaan dan
  LinkedInProfileMapper.
- AdminAlumniProfileService dipakai ProfilController, SimpanProfilController,
  EvaluasiAtasanController, dan controller detail alumni admin.
- TracerAnalyticsService dipakai TracerAnalyticsController pada route Prodi,
  Fakultas, Biro 3, dan Superadmin.
- KelengkapanTracerService dipakai dashboard Alumni, halaman kuesioner, dan
  halaman detail audit alumni.
- KuesionerSyncService dipakai profil, kuesioner umum, kuesioner prodi,
  evaluator publik, dan detail alumni.



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

Bagian ini sengaja menjadi inventaris Vue saja. Hubungan lengkap halaman ini
dengan controller, service, method, query, model, tabel, view, dan migration
sekarang tersedia di bagian 3A, terutama baris Direktori, detail, audit, dan
export alumni serta baris Dashboard.

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

Rincian format spreadsheet per controller, service, Vue, model, tabel, view,
migration, dan query tersedia pada Lampiran A di bagian akhir dokumen.

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

## Lampiran A. Detail per file untuk analitik grafik

Bagian ini adalah tabel konfigurasi rinci dengan format yang sama seperti contoh spreadsheet. Satu baris mewakili satu file Vue/komponen yang terlibat. Kolom Controller dan Service menunjukkan jalur backend yang memasok payload; komponen anak tidak memiliki query sendiri sehingga ditandai sebagai komponen turunan dari halaman induk. Tanda - berarti tidak ada file atau layer langsung pada baris tersebut.

### 7A.1 Analitik grafik universitas

| Nama Aktivitas | Role/pemakai | Controller | Penjelasan Controller | Method + penjelasan | Service / isi logika | Penjelasan Service | Method service + penjelasan | Vue/View | Penjelasan Vue | Database (Model + tabel) | View database | Migration | Penjelasan Database | Query utama |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Analitik grafik universitas | Superadmin, Admin Prodi, Admin Fakultas, Biro 3 | TracerAnalyticsController.php | Entry point route halaman, JSON data, dan export response rate sesuai prefix role. | index(): render GrafikUniv; data(): kirim payload JSON; exportResponseRate(): download spreadsheet. | TracerAnalyticsService.php; IkuS1AnalyticsService.php | TracerAnalyticsService menyusun seluruh chart universitas; IkuS1AnalyticsService menangani chart IKU S1. | buildUniversityChart(): payload utama; build(): normalisasi filter; buildAeeChart(), buildOutcomeChart(), buildUmpWeightedChart(): payload IKU. | resources/js/Pages/SuperAdmin/Analytics/GrafikUniv.vue | Halaman induk yang menerima analytics, mengelola filter tahun/UMP/prodi/chart, mengambil endpoint data, menampilkan KPI, insight, chart, tabel, pagination, dan drill-down. | Model: AlumniAuditRekap, AlumniTracerUnivStatus, Ump, KuesionerJawaban; tabel: biodata, data_akademik, kuesioner_jawaban, ump | v_analytics_population, v_analytics_response_rate, v_alumni_audit_rekap, v_analytics_tracer_answers, v_iku_s1_aee, v_iku_s1_lulusan | 2026_09_02_000006_create_v_alumni_audit_rekap_view.php; 2026_10_25_000001 sampai 000019 untuk view analitik; 2026_11_09_002941 dan 002942 untuk IKU | Read model menyatukan populasi alumni, status pengisian, jawaban tracer, UMP, dan indikator IKU sebelum dikirim sebagai payload agregat ke Vue. | Query Builder/DB::table ke view analitik; join/filter tahun, prodi, UMP, status submit, dan kode pertanyaan. |
| Analitik grafik universitas | Role yang sama | TracerAnalyticsController.php (alur induk) | Tidak memiliki route sendiri; payload diwariskan dari halaman GrafikUniv. | data() pada halaman induk memasok props; komponen hanya menerima event dan payload. | TracerAnalyticsService.php (alur induk) | Tidak memanggil service langsung; hanya mengolah payload chart yang sudah dihitung backend. | buildUniversityChart() sudah selesai sebelum komponen dirender. | resources/js/components/analytics/AnalyticsChartsPanel.vue | Memilih 21 kategori chart, lazy-load komponen, meneruskan chart payload, dan mengirim event select chart/province/data/methodology. | Model: -; tabel: - pada komponen | - | - | Komponen presentasi; sumber datanya tetap query yang dijalankan service pada baris halaman induk. | Tidak ada query; membaca props dan emit event ke GrafikUniv.vue. |
| Analitik grafik universitas | Role yang sama | TracerAnalyticsController.php (alur induk) | Tidak route langsung; controller hanya memasok payload ke GrafikUniv. | data() memasok option dan data chart melalui payload analytics. | TracerAnalyticsService.php (alur induk) | Tidak query langsung; wrapper hanya menerima option ECharts. | universityChartOptions() menyediakan kamus dan konfigurasi chart. | resources/js/components/analytics/AnalyticsEChart.vue | Wrapper Apache ECharts untuk render option, loading, resize, tooltip, legend, zoom, event klik, reset, dan download PNG. | Model: -; tabel: - | - | - | Tidak menyentuh database; hanya merender data yang sudah diagregasi. | Tidak ada query; input berasal dari props option. |
| Analitik grafik universitas | Role yang sama | TracerAnalyticsController.php (alur induk) | Tidak route langsung; controller memasok region dan lokasi kerja lewat payload. | data() mengirim regions/work locations; komponen menerjemahkannya ke layer peta. | TracerAnalyticsService.php (alur induk) | Menyusun work location berdasarkan provinsi/kota; komponen tidak memanggil service. | workLocationChart(), workLocationRecords(): agregasi lokasi dan record. | resources/js/components/analytics/AnalyticsMap.vue | Merender peta MapLibre, choropleth, marker/cluster, GeoJSON provinsi, serta emit province-selected untuk drill-down. | Model: -; tabel: - pada komponen; sumber backend berasal dari biodata dan jawaban lokasi kerja | -; GeoJSON adalah asset publik, bukan database view | - | Database hanya dibaca service; file Vue juga membaca public/geojson/indonesia-provinsi.json untuk geometri. | Tidak ada query; join data peta dengan payload regions dari props. |
| Analitik grafik universitas | Role yang sama | TracerAnalyticsController.php (alur induk) | Tidak route langsung; controller mengirim data chart, summary, dan records ke halaman induk. | data() memasok payload yang ditampilkan di tab grafik dan data. | TracerAnalyticsService.php (alur induk) | Tidak query langsung; menyediakan data untuk layout chart/data/drill-down. | buildUniversityChart() dan response/data builder terkait. | resources/js/components/analytics/AnalyticsTabbedContainer.vue | Layout reusable untuk tab chart, tabel, summary, empty state, dan drill-down inline tanpa pindah tab. | Model: -; tabel: - | - | - | Hanya mengatur presentasi payload; sumber data tetap database yang dipakai service. | Tidak ada query. |
| Analitik grafik universitas | Role yang sama | TracerAnalyticsController.php (alur induk) | Tidak route langsung; halaman induk telah memuat payload. | data() sebagai sumber payload chart. | - | Komponen kartu tidak memiliki service backend. | - | resources/js/components/analytics/AnalyticsChartCard.vue | Membungkus judul, subtitle, ringkasan, chart slot, loading, dan empty state tiap kartu indikator. | Model: -; tabel: - | - | - | Komponen UI murni. | Tidak ada query. |
| Analitik grafik universitas | Role yang sama | TracerAnalyticsController.php (alur induk) | Tidak route langsung; payload teks sudah dibentuk service. | data() mengirim feedback/word frequencies melalui analytics. | TracerAnalyticsService.php (alur induk) | Menyiapkan jawaban teks yang dinormalisasi; komponen hanya menghitung tampilan frekuensi. | questionChart(), answerText(), answerValues(). | resources/js/components/analytics/AnalyticsWordCloud.vue | Merender kata atau feedback dengan ukuran berdasarkan frekuensi dan menyediakan daftar detail respons. | Model: -; tabel: - | v_analytics_tracer_answers sebagai sumber backend | 2026_10_25_000010_create_v_analytics_tracer_answers_view.php | Tidak membaca database langsung; hanya menerima agregat teks dari service. | Tidak ada query di Vue. |
| Analitik grafik universitas | Role yang sama | TracerAnalyticsController.php (alur induk) | Tidak route langsung; controller/service telah menyiapkan chart key. | data() mengirim key, payload, dan opsi filter. | TracerAnalyticsService.php (alur induk) | Menetapkan chart key, methodology, dan struktur payload; file JS hanya formatter. | universityChartOptions(), definitions(), chartMethodology(). | resources/js/components/analytics/analyticsChartOptions.js | Utility builder untuk option bar/line/pie/heatmap/sankey/treemap, palette, label, formatter, dan option IKU. | Model: -; tabel: - | - | - | Tidak query dan tidak mengetahui tabel; hanya mengubah payload menjadi konfigurasi ECharts. | Tidak ada query. |
| Analitik grafik universitas - response rate | Role yang sama | TracerAnalyticsController.php | Menerima filter dan mengembalikan response rate serta detail status pengisian. | index(), data(): halaman/payload; exportResponseRate(): export response rate. | TracerAnalyticsService.php; ResponseRateExcelExporter.php | Menghitung response rate, target, status responded/not responded, dan mengekspor data. | responseRateChart(), responseRateRows(), responseRateProfileDetails(), resolveTargetResponseRate(); download(). | resources/js/components/analytics/categories/01_ResponseRate.vue | Menampilkan bar/stacked chart, target, status response, tabel detail, dan event drill-down/export. | Model: AlumniAuditRekap, AlumniTracerUnivStatus; tabel: biodata, data_akademik | v_analytics_population, v_analytics_response_rate, v_alumni_audit_rekap | 2026_09_02_000006_create_v_alumni_audit_rekap_view.php; 2026_10_25_000001_create_v_analytics_population_view.php; 000002_create_v_analytics_response_rate_view.php | Mengambil denominator populasi dan status submit per tahun/prodi; target dipakai untuk membandingkan capaian. | Filter tahun/prodi pada v_analytics_response_rate; join metadata prodi; hitung responded / population. |
| Analitik grafik universitas - metode pembelajaran | Role yang sama | TracerAnalyticsController.php | Menyediakan payload kategori pembelajaran dari endpoint analitik universitas. | data(): mengembalikan chart berdasarkan chart key. | TracerAnalyticsService.php | Mengelompokkan jawaban pertanyaan F21-F27 menjadi distribusi metode pembelajaran dan record. | analyticsRows(), learningMethodsChart(), questionChart(), questionRecords(). | resources/js/components/analytics/categories/02_LearningMethods.vue | Merender distribusi metode pembelajaran, label kategori, dan detail jawaban. | Model: -; tabel: kuesioner_jawaban, kuesioner_pertanyaan | v_analytics_tracer_answers | 2026_09_01_000034_create_kuesioner_pertanyaan_table.php; 2026_09_01_000037_create_kuesioner_jawaban_table.php; 2026_10_25_000010_create_v_analytics_tracer_answers_view.php | Jawaban terbaru per alumni dan kode pertanyaan dinormalisasi sebelum dihitung. | Filter chart_key dan kode F21-F27; group by label jawaban dan tahun/prodi. |
| Analitik grafik universitas - awal mencari kerja | Role yang sama | TracerAnalyticsController.php | Mengembalikan chart waktu mulai mencari pekerjaan sesuai filter. | data(): payload chart; index(): render halaman. | TracerAnalyticsService.php | Menormalisasi jawaban F3/F502 dan menyusun distribusi waktu mulai mencari kerja. | jobSearchStartChart(), questionChart(), answerValues(). | resources/js/components/analytics/categories/03_JobSearchStart.vue | Menampilkan distribusi kapan alumni mulai mencari pekerjaan dan record pendukung. | Model: -; tabel: kuesioner_jawaban, kuesioner_pertanyaan | v_analytics_tracer_answers; v_analytics_wait_time sebagai read model terkait | 2026_09_01_000034_create_kuesioner_pertanyaan_table.php; 2026_09_01_000037_create_kuesioner_jawaban_table.php; 2026_10_25_000004_create_v_analytics_wait_time_view.php; 000010_create_v_analytics_tracer_answers_view.php | Parser mengubah kode/jawaban menjadi kategori waktu yang konsisten. | Filter kode pertanyaan F3/F502; group by kategori waktu dan filter tahun/prodi. |
| Analitik grafik universitas - metode mencari kerja | Role yang sama | TracerAnalyticsController.php | Menyediakan payload metode pencarian pekerjaan. | data(): payload chart berdasarkan key 04. | TracerAnalyticsService.php | Menghitung frekuensi metode pencarian kerja dari jawaban tracer. | questionChart(), questionRecords(), analyticsRows(). | resources/js/components/analytics/categories/04_JobSearchMethods.vue | Menampilkan pilihan metode mencari kerja dan detail alumni yang memilihnya. | Model: -; tabel: kuesioner_jawaban, kuesioner_pertanyaan | v_analytics_tracer_answers | 2026_09_01_000034_create_kuesioner_pertanyaan_table.php; 2026_09_01_000037_create_kuesioner_jawaban_table.php; 2026_10_25_000010_create_v_analytics_tracer_answers_view.php | Read model menyimpan jawaban terbaru per alumni/pertanyaan agar agregasi tidak menggandakan respons. | Filter kode pertanyaan metode kerja; group by answer label. |
| Analitik grafik universitas - waktu tunggu | Role yang sama | TracerAnalyticsController.php | Mengembalikan chart dan records waktu tunggu. | data(): payload; index(): render halaman. | TracerAnalyticsService.php | Menggabungkan F502/F504/F506, menghitung bulan/kelompok waktu, median, dan detail. | waitTimeChart(), waitTimeRecords(), waitTime(), waitValues(). | resources/js/components/analytics/categories/05_WaitTime.vue | Menampilkan distribusi waktu tunggu, statistik ringkas, dan record drill-down. | Model: -; tabel: kuesioner_jawaban, kuesioner_pertanyaan, biodata | v_analytics_wait_time; v_analytics_tracer_answers | 2026_09_01_000017_create_biodata_table.php; 2026_09_01_000034_create_kuesioner_pertanyaan_table.php; 2026_09_01_000037_create_kuesioner_jawaban_table.php; 2026_10_25_000004_create_v_analytics_wait_time_view.php; 000010_create_v_analytics_tracer_answers_view.php | View wait time menyediakan nilai yang sudah dinormalisasi agar service dapat menghitung median/distribusi dengan konsisten. | Filter tahun/prodi; ambil jawaban F502/F504/F506; group dan calculate month buckets. |
| Analitik grafik universitas - pipeline lamaran | Role yang sama | TracerAnalyticsController.php | Mengembalikan funnel lamaran dan conversion stats. | data(): payload chart dan records. | TracerAnalyticsService.php | Menghubungkan status F6/F7/F7A, membuat pipeline, conversion, dan statistik IPK. | applicationPipelineChart(), pipelineRelationTable(), conversionIpkStats(). | resources/js/components/analytics/categories/06_ApplicationPipeline.vue | Menampilkan funnel/pipeline lamaran, relasi status, dan detail record. | Model: -; tabel: kuesioner_jawaban, kuesioner_pertanyaan, biodata | v_analytics_tracer_answers | 2026_09_01_000034_create_kuesioner_pertanyaan_table.php; 2026_09_01_000037_create_kuesioner_jawaban_table.php; 2026_10_25_000010_create_v_analytics_tracer_answers_view.php | Jawaban status lamaran dibaca per alumni lalu dihitung sebagai tahap pipeline. | Filter F6/F7/F7A; group by status tahap; hitung conversion antar tahap. |
| Analitik grafik universitas - status pekerjaan | Role yang sama | TracerAnalyticsController.php | Menyediakan status pekerjaan untuk chart universitas. | data(): payload status dan records. | TracerAnalyticsService.php | Menormalisasi F8 dan mengelompokkan employment status. | employmentStatusChart(), employmentStatus(), normalizeEmploymentStatus(). | resources/js/components/analytics/categories/07_EmploymentStatus.vue | Menampilkan komposisi status kerja dan detail responden. | Model: -; tabel: kuesioner_jawaban, kuesioner_pertanyaan, biodata | v_analytics_employment_status; v_analytics_tracer_answers | 2026_09_01_000017_create_biodata_table.php; 2026_09_01_000034_create_kuesioner_pertanyaan_table.php; 2026_09_01_000037_create_kuesioner_jawaban_table.php; 2026_10_25_000007_create_v_analytics_employment_status_view.php; 000010_create_v_analytics_tracer_answers_view.php | View status membantu konsistensi kategori pekerjaan sebelum agregasi. | Filter tahun/prodi; group F8/status; count dan percentage. |
| Analitik grafik universitas - aktivitas mencari kerja | Role yang sama | TracerAnalyticsController.php | Mengembalikan distribusi aktivitas pencarian kerja. | data(): payload chart key 08. | TracerAnalyticsService.php | Mengolah jawaban F10 menjadi distribusi aktivitas. | questionChart(), questionRecords(), analyticsRows(). | resources/js/components/analytics/categories/08_JobSearchActivity.vue | Menampilkan distribusi aktivitas mencari kerja dan record. | Model: -; tabel: kuesioner_jawaban, kuesioner_pertanyaan | v_analytics_tracer_answers | 2026_09_01_000034_create_kuesioner_pertanyaan_table.php; 2026_09_01_000037_create_kuesioner_jawaban_table.php; 2026_10_25_000010_create_v_analytics_tracer_answers_view.php | Jawaban F10 dibaca sebagai pilihan/label dan diagregasi per tahun/prodi. | Filter F10; group by answer label; calculate count/percentage. |
| Analitik grafik universitas - jenis tempat kerja | Role yang sama | TracerAnalyticsController.php | Menyediakan chart jenis tempat kerja dan records perusahaan. | data(): payload chart dan detail; index(): halaman. | TracerAnalyticsService.php | Mengolah F11 dan metadata pekerjaan/perusahaan menjadi kategori tempat kerja. | questionChart(), questionRecords(), analyticsRows(). | resources/js/components/analytics/categories/09_WorkplaceType.vue | Menampilkan distribusi jenis tempat kerja dan record perusahaan/alumni. | Model: -; tabel: kuesioner_jawaban, kuesioner_pertanyaan, perusahaan | v_analytics_tracer_answers; v_analytics_workplace_type sebagai kompatibilitas legacy | 2026_09_01_000034_create_kuesioner_pertanyaan_table.php; 2026_09_01_000037_create_kuesioner_jawaban_table.php; 2026_10_25_000016_create_v_analytics_workplace_type_view.php; 000010_create_v_analytics_tracer_answers_view.php | Jawaban F11 menjadi kategori; metadata perusahaan dipakai untuk detail, bukan sebagai sumber chart terpisah. | Filter F11; group by workplace type; join biodata/perusahaan bila perlu untuk records. |
| Analitik grafik universitas - studi lanjut | Role yang sama | TracerAnalyticsController.php | Mengembalikan data studi lanjut dan detail alumni. | data(): payload chart. | TracerAnalyticsService.php | Mengolah F12/F18b-F18d dan jawaban pendidikan lanjut. | furtherStudyChart(), questionChart(), questionRecords(). | resources/js/components/analytics/categories/10_FurtherStudy.vue | Menampilkan proporsi studi lanjut, detail jenis studi, dan records. | Model: -; tabel: kuesioner_jawaban, kuesioner_pertanyaan, data_akademik | v_analytics_tracer_answers | 2026_09_01_000011_create_data_akademik_table.php; 2026_09_01_000034_create_kuesioner_pertanyaan_table.php; 2026_09_01_000037_create_kuesioner_jawaban_table.php; 2026_10_25_000017_create_v_analytics_further_study_view.php; 000010_create_v_analytics_tracer_answers_view.php | Jawaban status/studi lanjut dinormalisasi dan dikaitkan dengan tahun/prodi alumni. | Filter F12/F18b-F18d; group status/jenis studi; count dan percentage. |
| Analitik grafik universitas - gaji terhadap UMP | Role yang sama | TracerAnalyticsController.php | Menyediakan chart gaji, status terhadap UMP, dan filter tahun UMP. | data(): payload; export tidak mengubah query chart. | TracerAnalyticsService.php | Menggabungkan gaji pekerjaan dengan UMP provinsi dan tahun yang dipilih. | salaryChart(), salaryFromAnalyticsView(), salaryVsUmp(), aggregateSalary(), aggregateUmpStatus(). | resources/js/components/analytics/categories/11_SalaryVsUmp.vue | Menampilkan distribusi gaji vs UMP, status memenuhi UMP, filter provinsi, dan detail. | Model: Ump; tabel: ump, kuesioner_jawaban, biodata, perusahaan | v_analytics_salary_ump; v_analytics_tracer_answers | 2026_09_01_000024_create_ump_table.php; 2026_09_01_000037_create_kuesioner_jawaban_table.php; 2026_10_25_000005_create_v_analytics_salary_ump_view.php; 000010_create_v_analytics_tracer_answers_view.php | UMP dipilih berdasarkan provinsi dan tahun; gaji dinormalisasi untuk klasifikasi di bawah/setara/di atas UMP. | Join salary rows dengan UMP by province/year; group status and province. |
| Analitik grafik universitas - keselarasan bidang studi | Role yang sama | TracerAnalyticsController.php | Mengembalikan chart alignment bidang studi. | data(): payload chart key 12. | TracerAnalyticsService.php | Menormalisasi jawaban F14 dan menghitung kategori keselarasan. | alignmentChart(), normalizeAlignmentAnswer(). | resources/js/components/analytics/categories/12_FieldAlignment.vue | Menampilkan tingkat keselarasan bidang studi dengan pekerjaan dan detail jawaban. | Model: -; tabel: kuesioner_jawaban, kuesioner_pertanyaan | v_analytics_alignment; v_analytics_tracer_answers | 2026_09_01_000034_create_kuesioner_pertanyaan_table.php; 2026_09_01_000037_create_kuesioner_jawaban_table.php; 2026_10_25_000008_create_v_analytics_alignment_view.php; 000010_create_v_analytics_tracer_answers_view.php | Jawaban F14 dipetakan ke label kanonik agar pilihan lama dan baru dapat dibandingkan. | Filter F14; normalize label; group by alignment category. |
| Analitik grafik universitas - keselarasan tingkat pendidikan | Role yang sama | TracerAnalyticsController.php | Mengembalikan chart kesesuaian tingkat pendidikan. | data(): payload chart key 13. | TracerAnalyticsService.php | Mengolah F15 dan alignment read model. | alignmentChart(), questionChart(), normalizeAlignmentAnswer(). | resources/js/components/analytics/categories/13_EducationAlignment.vue | Menampilkan kesesuaian tingkat pendidikan dengan pekerjaan. | Model: -; tabel: kuesioner_jawaban, kuesioner_pertanyaan | v_analytics_alignment; v_analytics_tracer_answers | 2026_09_01_000034_create_kuesioner_pertanyaan_table.php; 2026_09_01_000037_create_kuesioner_jawaban_table.php; 2026_10_25_000008_create_v_analytics_alignment_view.php; 2026_10_25_000010_create_v_analytics_tracer_answers_view.php | Read model alignment menyimpan kategori yang sudah dinormalisasi untuk agregasi. | Filter F15; group by education alignment; count/percentage. |
| Analitik grafik universitas - alasan mismatch | Role yang sama | TracerAnalyticsController.php | Menyediakan alasan ketidaksesuaian pekerjaan. | data(): payload chart key 15. | TracerAnalyticsService.php | Mengelompokkan jawaban F16 dan menjaga detail jawaban teks/pilihan. | questionChart(), questionRecords(), answerValues(). | resources/js/components/analytics/categories/15_MismatchReasons.vue | Menampilkan alasan mismatch dalam chart dan daftar record. | Model: -; tabel: kuesioner_jawaban, kuesioner_pertanyaan | v_analytics_mismatch_reasons; v_analytics_tracer_answers | 2026_09_01_000034_create_kuesioner_pertanyaan_table.php; 2026_09_01_000037_create_kuesioner_jawaban_table.php; 2026_10_25_000018_create_v_analytics_mismatch_reasons_view.php; 000010_create_v_analytics_tracer_answers_view.php | Jawaban F16 dapat berupa multi-choice/teks sehingga service membaca jawaban teks dan JSON. | Filter F16; decode jawaban JSON; explode pilihan; group counts. |
| Analitik grafik universitas - gap kompetensi | Role yang sama | TracerAnalyticsController.php | Mengembalikan chart kompetensi dan gap. | data(): payload chart key 16. | TracerAnalyticsService.php | Menggabungkan pasangan kompetensi yang dibutuhkan dan dikuasai lalu menghitung gap. | competencyGapChart(), answerValues(), questionChart(). | resources/js/components/analytics/categories/16_CompetencyGap.vue | Menampilkan perbandingan kompetensi, gap, heatmap/line, dan detail jawaban. | Model: -; tabel: kuesioner_jawaban, kuesioner_pertanyaan | v_analytics_competency_gap; v_analytics_tracer_answers | 2026_09_01_000034_create_kuesioner_pertanyaan_table.php; 2026_09_01_000037_create_kuesioner_jawaban_table.php; 2026_10_25_000019_create_v_analytics_competency_gap_view.php; 2026_10_25_000010_create_v_analytics_tracer_answers_view.php | Pasangan F17a/F17b dibandingkan pada label kompetensi yang sama. | Filter pair F17a/F17b; normalize score; calculate required minus mastered. |
| Analitik grafik universitas - kelengkapan profil | Role yang sama | TracerAnalyticsController.php | Mengembalikan persentase kelengkapan profil dan detail field. | data(): payload chart key 17. | TracerAnalyticsService.php | Mengambil status field wajib dan membentuk completion summary. | profileCompletionChart(), summary(). | resources/js/components/analytics/categories/17_ProfileCompletion.vue | Menampilkan completion rate, field yang belum lengkap, dan detail alumni. | Model: -; tabel: biodata, data_akademik, kuesioner_jawaban | v_analytics_profile_completion, v_analytics_population | 2026_09_01_000011_create_data_akademik_table.php; 2026_09_01_000017_create_biodata_table.php; 2026_10_25_000001_create_v_analytics_population_view.php; 2026_10_25_000003_create_v_analytics_profile_completion_view.php | View menghitung kelengkapan terhadap field profil kanonik, bukan membaca dari Vue. | Filter tahun/prodi; count complete fields / required fields; group status. |
| Analitik grafik universitas - lokasi kerja | Role yang sama | TracerAnalyticsController.php | Mengembalikan agregasi lokasi dan records untuk peta. | data(): payload map; chart/category key 18. | TracerAnalyticsService.php | Mengelompokkan provinsi/kota lokasi kerja serta menyiapkan marker/records. | workLocationChart(), workLocationRecords(), locationLabel(). | resources/js/components/analytics/categories/18_WorkLocation.vue | Menampilkan map MapLibre, provinsi, marker/cluster, filter, dan drill-down lokasi. | Model: -; tabel: biodata, kuesioner_jawaban, kuesioner_pertanyaan | v_analytics_work_location; v_analytics_tracer_answers | 2026_09_01_000017_create_biodata_table.php; 2026_09_01_000037_create_kuesioner_jawaban_table.php; 2026_10_25_000006_create_v_analytics_work_location_view.php; 000010_create_v_analytics_tracer_answers_view.php | Lokasi kerja dinormalisasi agar label kota/provinsi cocok dengan GeoJSON dan filter wilayah. | Group by province/city; map region code; filter year/prodi. |
| Analitik grafik universitas - IKU S1 AEE | Role yang sama | TracerAnalyticsController.php | Mengirim payload AEE dari endpoint analitik universitas. | data(): payload; index(): halaman. | IkuS1AnalyticsService.php; TracerAnalyticsService.php | Menghitung angka AEE dan status ketercapaian indikator. | buildAeeChart(), aeeStatus(), applyFilters(). | resources/js/components/analytics/categories/19_IkuS1Aee.vue | Menampilkan KPI AEE, target, realisasi, status, dan rincian per prodi/kelompok. | Model: -; tabel: data_akademik, biodata, kuesioner_jawaban, prodi | v_iku_s1_aee | 2026_11_09_002942_create_v_iku_s1_aee_view.php | View IKU menyatukan data lulusan dan status yang diperlukan untuk AEE; service menerapkan filter. | Filter cohort/tahun/prodi; aggregate realized AEE; compare target. |
| Analitik grafik universitas - IKU S1 outcome lulusan | Role yang sama | TracerAnalyticsController.php | Mengirim payload outcome karier IKU S1. | data(): payload chart key 20. | IkuS1AnalyticsService.php; TracerAnalyticsService.php | Mengklasifikasikan outcome bekerja, studi lanjut, atau kategori lain berdasarkan masa tunggu, gaji, dan status studi. | buildOutcomeChart(), classifiedOutcomes(), careerCategory(), resolveWaitMonths(). | resources/js/components/analytics/categories/20_IkuS1Outcomes.vue | Menampilkan komposisi outcome, status karier, masa tunggu, dan detail kategori. | Model: -; tabel: data_akademik, biodata, kuesioner_jawaban, prodi | v_iku_s1_lulusan | 2026_11_09_002941_create_v_iku_s1_lulusan_view.php | View lulusan menjadi basis klasifikasi outcome; query service menambahkan filter tahun/prodi. | Filter outcome source; classify career/study; aggregate percentage and records. |
| Analitik grafik universitas - IKU S1 berbobot UMP | Role yang sama | TracerAnalyticsController.php | Mengirim payload outcome berbobot UMP. | data(): payload chart key 21. | IkuS1AnalyticsService.php; TracerAnalyticsService.php | Memberi bobot outcome berdasarkan kategori, masa tunggu, dan status gaji terhadap UMP. | buildUmpWeightedChart(), umpRows(), resolveWeight(), outcomeContributionStatus(). | resources/js/components/analytics/categories/21_IkuS1UmpWeighted.vue | Menampilkan kontribusi berbobot, status tercapai, bobot kategori, dan breakdown wilayah/prodi. | Model: Ump; tabel: ump, data_akademik, biodata, kuesioner_jawaban | v_iku_s1_lulusan; - untuk bobot UMP langsung dari tabel Ump | 2026_09_01_000024_create_ump_table.php; 2026_11_09_002941_create_v_iku_s1_lulusan_view.php | View menyediakan outcome lulusan; tabel UMP dipakai untuk ambang gaji dan bobot. | Load UMP by year/province; classify outcome; apply weight; calculate contribution percentage. |

### 7A.2 Analitik grafik program studi

| Nama Aktivitas | Role/pemakai | Controller | Penjelasan Controller | Method + penjelasan | Service / isi logika | Penjelasan Service | Method service + penjelasan | Vue/View | Penjelasan Vue | Database (Model + tabel) | View database | Migration | Penjelasan Database | Query utama |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Analitik grafik program studi | Superadmin, Admin Prodi, Admin Fakultas, Biro 3 | ProdiAnalyticsController.php | Entry point halaman dan endpoint JSON analitik kuesioner prodi; admin prodi dibatasi ke prodinya sendiri. | index(): render GrafikProdi; data(): kirim payload; parameter prodi_id, section_id, tahun. | ProdiAnalyticsService.php | Memuat prodi/section/tahun, mengambil respons aktif, membentuk chart per tipe pertanyaan, insight, records, dan pivot. | getAvailableProdis(), getAvailableYears(), buildProdiChart(). | resources/js/Pages/SuperAdmin/Analytics/GrafikProdi.vue | Halaman induk yang mengelola filter prodi/section/tahun, fetch data, chart, pivot table, records, pagination, dan drill-down alumni. | Model: Prodi, KuesionerSection, KuesionerPertanyaan, KuesionerSectionPertanyaan, KuesionerJawaban; tabel: prodi, kuesioner_section, kuesioner_pertanyaan, kuesioner_section_pertanyaan, kuesioner_jawaban, data_akademik | -; halaman ini membaca tabel aktif langsung melalui Eloquent/query builder | 2026_09_01_000011_create_data_akademik_table.php; 000012_create_prodi_table.php; 000033_create_kuesioner_section_table.php; 000034_create_kuesioner_pertanyaan_table.php; 000035_create_kuesioner_section_pertanyaan_table.php; 000037_create_kuesioner_jawaban_table.php | Data prodi memakai konfigurasi section dan pertanyaan aktif; respons disaring berdasarkan prodi dan tahun akademik/alumni. | Load available prodi/years; join section-question; filter active questions and submitted answers; group by question/answer. |
| Analitik grafik program studi | Role yang sama | ProdiAnalyticsController.php (alur induk) | Tidak route langsung; payload diteruskan dari GrafikProdi. | data() memasok sections/items/insights; komponen mengubahnya menjadi visual. | ProdiAnalyticsService.php (alur induk) | Tidak query langsung; menerima payload chart section dan meneruskannya ke kategori. | buildProdiChart() selesai sebelum panel dirender. | resources/js/components/prodi-analytics/ProdiAnalyticsChartsPanel.vue | Memilih komponen berdasarkan section order/type dan meneruskan items, records, insight, serta context filter. | Model: -; tabel: - | - | - | Komponen presentasi turunan dari payload service. | Tidak ada query. |
| Analitik grafik program studi | Role yang sama | ProdiAnalyticsController.php (alur induk) | Tidak route langsung; controller memasok payload pilihan. | data() mengirim categories/series/records ke komponen. | - | Tidak ada service langsung pada chart pilihan. | - | resources/js/components/prodi-analytics/ChoiceQuestionsChart.vue | Merender pertanyaan pilihan, series/categories, persentase, dan detail records. | Model: -; tabel: - | - | - | Hanya presentasi payload pilihan dari ProdiAnalyticsService. | Tidak ada query. |
| Analitik grafik program studi | Role yang sama | ProdiAnalyticsController.php (alur induk) | Tidak route langsung; data sudah diambil oleh endpoint prodi. | data() sebagai sumber items dan records. | - | Tidak ada service langsung; tabel adalah representasi payload. | - | resources/js/components/prodi-analytics/ProdiPivotTable.vue | Menampilkan pivot pertanyaan-responden dan detail jawaban tanpa mengambil data baru. | Model: -; tabel: - | - | - | Tidak mengubah atau membaca database. | Tidak ada query. |
| Analitik grafik program studi | Role yang sama | ProdiAnalyticsController.php (alur induk) | Tidak route langsung; controller/service telah menentukan chart type. | data() mengirim item chart dan metadata. | - | Utility JS tidak memiliki service backend. | - | resources/js/components/prodi-analytics/prodiChartOptions.js | Builder option Likert/choice, label skor, warna, tinggi chart, dan formatter. | Model: -; tabel: - | - | - | Murni formatter/konfigurasi chart. | Tidak ada query. |
| Analitik grafik program studi - peminatan dan kontribusi | Role yang sama | ProdiAnalyticsController.php | Mengembalikan section peminatan/kontribusi dari kuesioner prodi. | data(): payload section dan records. | ProdiAnalyticsService.php | Memformat choice/text question, termasuk jawaban lain dan feedback. | formatChoiceQuestion(), formatTextQuestion(), answerLabels(), otherAnswerDetail(). | resources/js/components/prodi-analytics/categories/01_InterestAndContribution.vue | Menampilkan chart pilihan, word cloud/context selection, dan records. | Model: KuesionerJawaban, KuesionerPertanyaan; tabel: kuesioner_jawaban, kuesioner_pertanyaan, prodi | - | 2026_09_01_000012_create_prodi_table.php; 000034_create_kuesioner_pertanyaan_table.php; 000037_create_kuesioner_jawaban_table.php | Pertanyaan aktif dibaca dari section prodi; pilihan JSON dan jawaban lain dipisahkan untuk label/detail. | Filter prodi/section/tahun; group option labels; attach other text and records. |
| Analitik grafik program studi - pengalaman pembelajaran | Role yang sama | ProdiAnalyticsController.php | Mengirim payload section Likert pengalaman pembelajaran. | data(): payload chart Likert. | ProdiAnalyticsService.php | Menghitung valid count, rata-rata skor, distribusi, dan record per pertanyaan. | formatLikertQuestion(), questionStat(), extractLikertScore(). | resources/js/components/prodi-analytics/categories/02_LearningExperience.vue | Menampilkan chart Likert per pertanyaan dan detail responden. | Model: KuesionerJawaban, KuesionerPertanyaan; tabel: kuesioner_jawaban, kuesioner_pertanyaan, kuesioner_section_pertanyaan | - | 2026_09_01_000034_create_kuesioner_pertanyaan_table.php; 000035_create_kuesioner_section_pertanyaan_table.php; 000037_create_kuesioner_jawaban_table.php | Skor diambil dari jawaban aktif dan dipetakan ke label Likert pertanyaan. | Filter section; parse score; group by question and score; calculate average. |
| Analitik grafik program studi - nilai program studi | Role yang sama | ProdiAnalyticsController.php | Mengirim payload penilaian nilai program studi. | data(): payload section. | ProdiAnalyticsService.php | Menyusun statistik Likert section dan insight. | formatSectionChart(), formatLikertQuestion(), buildInsights(). | resources/js/components/prodi-analytics/categories/03_StudyProgramValues.vue | Menampilkan distribusi nilai yang dirasakan dan detail jawaban. | Model: KuesionerJawaban, KuesionerPertanyaan; tabel: kuesioner_jawaban, kuesioner_pertanyaan, kuesioner_section | - | 2026_09_01_000033_create_kuesioner_section_table.php; 000034_create_kuesioner_pertanyaan_table.php; 000037_create_kuesioner_jawaban_table.php | Section menentukan urutan dan pertanyaan yang masuk ke agregasi. | Filter section/prodi/tahun; group score per question; compute average and insight. |
| Analitik grafik program studi - fasilitas | Role yang sama | ProdiAnalyticsController.php | Mengirim data evaluasi ketersediaan fasilitas. | data(): payload section. | ProdiAnalyticsService.php | Mengubah jawaban fasilitas menjadi statistik Likert dan records. | formatLikertQuestion(), questionStat(), recordPayload(). | resources/js/components/prodi-analytics/categories/04_AvailableFacilities.vue | Menampilkan chart fasilitas, rata-rata, dan detail respons. | Model: KuesionerJawaban, KuesionerPertanyaan; tabel: kuesioner_jawaban, kuesioner_pertanyaan | - | 2026_09_01_000034_create_kuesioner_pertanyaan_table.php; 2026_09_01_000037_create_kuesioner_jawaban_table.php | Sumber adalah jawaban section aktif, tidak ada view analitik khusus. | Filter section; aggregate score/category; build records. |
| Analitik grafik program studi - dosen dan tenaga kependidikan | Role yang sama | ProdiAnalyticsController.php | Mengirim data evaluasi dosen dan tenaga kependidikan. | data(): payload section. | ProdiAnalyticsService.php | Menghitung distribusi skor dan insight per pertanyaan. | formatLikertQuestion(), questionStat(), buildInsights(). | resources/js/components/prodi-analytics/categories/05_LecturersAndStaff.vue | Menampilkan indikator dosen/staf dan detail jawaban. | Model: KuesionerJawaban, KuesionerPertanyaan; tabel: kuesioner_jawaban, kuesioner_pertanyaan | - | 2026_09_01_000034_create_kuesioner_pertanyaan_table.php; 2026_09_01_000037_create_kuesioner_jawaban_table.php | Jawaban Likert dibaca langsung dari tabel respons. | Filter question/section/prodi; parse score; group and average. |
| Analitik grafik program studi - mata kuliah | Role yang sama | ProdiAnalyticsController.php | Mengirim data evaluasi mata kuliah yang ditawarkan. | data(): payload section. | ProdiAnalyticsService.php | Menyusun statistik Likert dan record mata kuliah. | formatLikertQuestion(), questionStat(), recordPayload(). | resources/js/components/prodi-analytics/categories/06_CoursesOffered.vue | Menampilkan chart mata kuliah dan detail respons. | Model: KuesionerJawaban, KuesionerPertanyaan; tabel: kuesioner_jawaban, kuesioner_pertanyaan | - | 2026_09_01_000034_create_kuesioner_pertanyaan_table.php; 2026_09_01_000037_create_kuesioner_jawaban_table.php | Tidak ada view khusus; pertanyaan aktif menjadi sumber indikator. | Filter section; group answers per question; calculate average/distribution. |
| Analitik grafik program studi - kepuasan keseluruhan | Role yang sama | ProdiAnalyticsController.php | Mengirim agregat kepuasan keseluruhan. | data(): payload section dan records. | ProdiAnalyticsService.php | Menghitung skor, distribusi, dan insight kepuasan. | formatLikertQuestion(), questionStat(), buildInsights(). | resources/js/components/prodi-analytics/categories/07_OverallSatisfaction.vue | Menampilkan chart kepuasan, ringkasan, dan detail records. | Model: KuesionerJawaban, KuesionerPertanyaan; tabel: kuesioner_jawaban, kuesioner_pertanyaan | - | 2026_09_01_000034_create_kuesioner_pertanyaan_table.php; 2026_09_01_000037_create_kuesioner_jawaban_table.php | Skor berasal dari jawaban pertanyaan kepuasan aktif pada section prodi. | Filter section/prodi/tahun; average/percentage by score. |
| Analitik grafik program studi - rekomendasi prodi | Role yang sama | ProdiAnalyticsController.php | Mengirim agregat rekomendasi program studi. | data(): payload section. | ProdiAnalyticsService.php | Mengubah skor/choice rekomendasi menjadi chart dan insight. | formatLikertQuestion(), formatChoiceQuestion(), questionStat(). | resources/js/components/prodi-analytics/categories/08_StudyProgramRecommendation.vue | Menampilkan tingkat rekomendasi program studi dan detail pilihan. | Model: KuesionerJawaban, KuesionerPertanyaan; tabel: kuesioner_jawaban, kuesioner_pertanyaan | - | 2026_09_01_000034_create_kuesioner_pertanyaan_table.php; 2026_09_01_000037_create_kuesioner_jawaban_table.php | Tipe pertanyaan menentukan apakah data dirender sebagai Likert atau pilihan. | Filter section; parse score/choice; group and calculate percentage. |
| Analitik grafik program studi - harapan dan feedback | Role yang sama | ProdiAnalyticsController.php | Mengirim jawaban teks dan feedback section. | data(): payload text items dan records. | ProdiAnalyticsService.php | Menormalisasi jawaban teks, membuat insight, dan mempertahankan records. | formatTextQuestion(), rawAnswer(), buildInsights(), recordPayload(). | resources/js/components/prodi-analytics/categories/09_HopesAndFeedback.vue | Menampilkan word cloud, tab/filter feedback, dan daftar respons teks. | Model: KuesionerJawaban, KuesionerPertanyaan; tabel: kuesioner_jawaban, kuesioner_pertanyaan | - | 2026_09_01_000034_create_kuesioner_pertanyaan_table.php; 2026_09_01_000037_create_kuesioner_jawaban_table.php | Jawaban teks berasal dari jawaban_teks; tipe pertanyaan dibaca dari kuesioner_pertanyaan. | Filter section/prodi/tahun; select text answers; normalize frequency and records. |

### 7A.3 Analitik grafik evaluasi atasan

| Nama Aktivitas | Role/pemakai | Controller | Penjelasan Controller | Method + penjelasan | Service / isi logika | Penjelasan Service | Method service + penjelasan | Vue/View | Penjelasan Vue | Database (Model + tabel) | View database | Migration | Penjelasan Database | Query utama |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Analitik grafik evaluasi atasan | Superadmin, Admin Prodi, Admin Fakultas, Biro 3 | AtasanAnalyticsController.php | Entry point halaman dan endpoint JSON evaluasi atasan dengan filter tahun/prodi. | index(): render GrafikAtasan; data(): kirim payload analytics. | AtasanAnalyticsService.php | Mengambil evaluasi atasan yang submitted, statistik pertanyaan, kesiapan kerja, ringkasan prodi, dan records. | build(), evaluations(), questionStats(), prodiSummary(), records(). | resources/js/Pages/SuperAdmin/Analytics/GrafikAtasan.vue | Halaman induk yang mengelola filter tahun/prodi, fetch data, empat visual utama, tabel records, dan pagination. | Model: EvaluasiAtasan, Atasan, KuesionerJawaban, KuesionerPertanyaan, Prodi, Biodata; tabel: evaluasi_atasan, atasan, kuesioner_jawaban, kuesioner_pertanyaan, prodi, biodata | -; service membaca tabel operasional secara langsung | 2026_09_01_000012_create_prodi_table.php; 000015_create_atasan_table.php; 000017_create_biodata_table.php; 000030_create_evaluasi_atasan_table.php; 000034_create_kuesioner_pertanyaan_table.php; 000037_create_kuesioner_jawaban_table.php | Evaluasi atasan menyimpan relasi alumni-atasan dan jawaban; pertanyaan/opsi menentukan skor dan kategori. | Filter tahun/prodi; select submitted evaluations; join respondent/atasan/prodi; aggregate score and answer distribution. |
| Analitik grafik evaluasi atasan | Role yang sama | AtasanAnalyticsController.php (alur induk) | Tidak route langsung; komponen menerima payload dari GrafikAtasan. | data() memasok score_stats, aspect_details, readiness, prodi_summary, dan records. | AtasanAnalyticsService.php (alur induk) | Tidak query langsung; chart child membaca payload yang sudah dihitung. | build() selesai sebelum komponen dirender. | resources/js/components/analytics/AnalyticsEChart.vue | Merender option ECharts untuk rata-rata aspek, distribusi aspek, kesiapan kerja, dan perbandingan prodi. | Model: -; tabel: - | - | - | Wrapper chart murni; tidak memiliki sumber data mandiri. | Tidak ada query. |
| Analitik grafik evaluasi atasan | Role yang sama | AtasanAnalyticsController.php (alur induk) | Tidak route langsung; records dan pagination berasal dari endpoint atasan. | data() memasok records dan metadata pagination. | - | Tidak ada service langsung pada kontrol pagination. | - | resources/js/components/PaginationControls.vue | Mengatur perpindahan halaman records dan emit perubahan halaman ke GrafikAtasan.vue. | Model: -; tabel: - | - | - | Komponen kontrol UI; data records tetap berasal dari service. | Tidak ada query. |

Catatan pembacaan tabel: satu controller dapat dipakai oleh banyak row karena satu endpoint menyediakan payload untuk beberapa file Vue. Yang membedakan tiap row adalah method service, jenis payload, view/tabel sumber, dan fungsi query. Untuk komponen yang hanya merender payload, Controller/Service ditulis sebagai alur induk dan query diberi tanda - agar tidak seolah-olah komponen Vue mengakses database langsung.
## Lampiran B. Detail per file untuk aktivitas non-analitik

Lampiran ini melanjutkan format Lampiran A untuk aktivitas selain grafik. Controller yang dipakai bersama ditulis ulang pada setiap row agar hubungan aktivitas, role, method, service, Vue, dan database dapat dipindahkan ke spreadsheet tanpa menebak-nebak relasinya.

| Nama Aktivitas | Role/pemakai | Controller | Penjelasan Controller | Method + penjelasan | Service / isi logika | Penjelasan Service | Method service + penjelasan | Vue/View | Penjelasan Vue | Database (Model + tabel) | View database | Migration | Penjelasan Database | Query utama |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Login | Semua role, guest | app/Http/Controllers/Otentikasi/LoginController.php | Menampilkan form, memvalidasi kredensial, redirect berdasarkan role, dan logout. | tampilkanHalamanLogin(): render Login; prosesLogin(): autentikasi dan redirect; prosesLogout(): invalidate session. | - | Login membaca User langsung dan tidak memakai service khusus. | - | resources/js/Pages/Otentikasi/Login.vue; resources/js/components/Otentikasi/AuthBackdrop.vue | Form email/password, error validasi, remember session, dan layout autentikasi. | Model User; tabel users, sessions | - | 2026_09_01_000000_create_users_table.php; 000007_create_sessions_table.php | User menyimpan email, password, role, status akun, dan flag password wajib. | Cari user berdasarkan email; verifikasi password; update session dan redirect role. |
| Lupa dan reset password | Semua role yang lupa password | app/Http/Controllers/Otentikasi/LupaKataSandiController.php | Mengirim token reset, menampilkan form reset, dan menyimpan password baru. | tampilkanHalamanLupaKataSandi(); prosesKirimLinkReset(); tampilkanHalamanResetKataSandi(); prosesResetKataSandi(). | app/Services/Otentikasi/PasswordResetNotificationService.php | Membuat token, menyimpan token, dan mengirim notifikasi reset. | send(): persist token dan dispatch mail. | resources/js/Pages/Otentikasi/ForgotPassword.vue; ResetPassword.vue | Form email lupa password dan form password baru berbasis token. | Model User; tabel users, password_reset_tokens | - | 2026_09_01_000000_create_users_table.php; 2026_09_01_000006_create_password_reset_tokens_table.php | Token reset memiliki email, token, dan waktu kedaluwarsa. | Cari user/email; insert atau update token; validasi token dan expiry; update password. |
| Ubah password | User yang sudah login | app/Http/Controllers/Otentikasi/UbahKataSandiController.php | Menampilkan dan memproses perubahan password dari dalam aplikasi. | tampilkanUbahKataSandi(); prosesUbahKataSandi(). | - | Controller memvalidasi password lama dan mengubah User secara langsung. | - | resources/js/Pages/Otentikasi/UbahKataSandi.vue | Form password lama, password baru, konfirmasi, dan flash message. | Model User; tabel users | - | 2026_09_01_000000_create_users_table.php | Kolom password dan must_change_password menjadi sumber status autentikasi. | Validasi auth user; check password lama; update hashed password; clear must_change_password. |
| Dashboard alumni | Alumni | app/Http/Controllers/Alumni/Dashboard/DashboardController.php | Menyusun ringkasan progres profil dan tracer milik alumni aktif. | tampilkanDashboard(). | app/Services/Kuesioner/KelengkapanTracerService.php | Menghitung kelengkapan profil, tracer umum, tracer prodi, dan status evaluasi. | evaluasiKelengkapanTotal(). | resources/js/Pages/Alumni/Dashboard.vue; resources/js/components/RoleDashboard.vue | Menampilkan progress, status kuesioner, profil, perusahaan, dan evaluasi atasan. | Model Biodata, DataAkademik, KuesionerJawaban, EvaluasiAtasan; tabel biodata, data_akademik, kuesioner_jawaban, evaluasi_atasan | v_alumni_audit_rekap, v_alumni_tracer_univ_status, v_alumni_tracer_prodi_status | 2026_09_02_000004 sampai 000006 untuk status/audit view; tabel pembentuk terkait | Read model status menggabungkan progres dari beberapa instrumen tanpa menulis data baru. | Filter biodata user; hitung mandatory answered dan status submit; ambil ringkasan evaluasi. |
| Dashboard Admin Prodi | Admin Prodi | app/Http/Controllers/AdminProdi/Dashboard/DashboardController.php | Mengambil statistik yang dibatasi prodi user. | tampilkanDashboard(Request, RoleDashboardDataService). | app/Services/Dashboard/RoleDashboardDataService.php | Menyusun statistik role admin dengan scope prodi. | build(). | resources/js/Pages/AdminProdi/Dashboard.vue; resources/js/components/RoleDashboard.vue | KPI, response rate, kelengkapan, alumni terbaru, dan aktivitas scope prodi. | Model AlumniAuditRekap, Biodata, Prodi, Perusahaan, LogActivity; tabel biodata, prodi, perusahaan, log_activities | v_alumni_audit_rekap | 2026_09_02_000006_create_v_alumni_audit_rekap_view.php; migration tabel master | Query diberi prodi_id dari user dan tidak boleh melebar ke fakultas/universitas. | Apply scope prodi; aggregate population/response/completion; latest alumni/company/log. |
| Dashboard Admin Fakultas | Admin Fakultas | app/Http/Controllers/AdminFakultas/Dashboard/DashboardController.php | Mengambil statistik seluruh prodi dalam fakultas user. | tampilkanDashboard(Request, RoleDashboardDataService). | app/Services/Dashboard/RoleDashboardDataService.php | Menentukan prodi anggota fakultas dan mengagregasi data. | build(). | resources/js/Pages/AdminFakultas/Dashboard.vue; resources/js/components/RoleDashboard.vue | Dashboard KPI dan tabel dengan scope fakultas. | Model AlumniAuditRekap, Prodi, Biodata, Perusahaan, LogActivity; tabel ref_fakultas, prodi, biodata, perusahaan, log_activities | v_alumni_audit_rekap | 2026_09_02_000006_create_v_alumni_audit_rekap_view.php; 2026_09_01_000009_create_ref_fakultas_table.php | Scope fakultas diturunkan dari relasi prodi.ref_fakultas_id. | Resolve fakultas; whereIn prodi; aggregate status dan recent records. |
| Dashboard Biro 3 | Biro 3 | app/Http/Controllers/AdminBiroTiga/Dashboard/DashboardController.php | Menyediakan ringkasan lintas prodi sesuai hak akses Biro 3. | tampilkanDashboard(Request, RoleDashboardDataService). | app/Services/Dashboard/RoleDashboardDataService.php | Memakai scope organisasi Biro 3 yang lebih luas daripada Admin Prodi. | build(). | resources/js/Pages/AdminBiroTiga/Dashboard.vue; resources/js/components/RoleDashboard.vue | KPI lintas unit, response rate, perusahaan, LinkedIn, dan alumni terbaru. | Model AlumniAuditRekap, Biodata, Prodi, Perusahaan, LogActivity; tabel biodata, prodi, perusahaan, log_activities | v_alumni_audit_rekap | 2026_09_02_000006_create_v_alumni_audit_rekap_view.php | Read model audit menjadi sumber ringkasan lintas prodi. | Apply allowed scope; aggregate status; fetch latest records and pending items. |
| Dashboard Superadmin | Superadmin | app/Http/Controllers/SuperAdmin/Dashboard/DashboardController.php | Menyusun dashboard tanpa pembatasan unit organisasi. | tampilkanDashboard(). | - | Query agregat dilakukan langsung oleh controller. | - | resources/js/Pages/SuperAdmin/Dashboard.vue; resources/js/components/RoleDashboard.vue | Menampilkan seluruh KPI, master data, aktivitas, dan ringkasan sistem. | Model AlumniAuditRekap, Biodata, Prodi, Perusahaan, LogActivity; tabel biodata, prodi, perusahaan, log_activities | v_alumni_audit_rekap | 2026_09_02_000006_create_v_alumni_audit_rekap_view.php | Superadmin membaca seluruh populasi dan seluruh audit. | Aggregate global population/response/completion; latest logs and pending company. |
| Profil alumni - halaman baca | Alumni | app/Http/Controllers/Alumni/Profil/ProfilController.php | Memuat data profil, opsi referensi, pertanyaan dinamis, dan jawaban autofill. | tampilkanHalamanProfil(Request). | app/Services/Alumni/AdminAlumniProfileService.php; app/Services/Kuesioner/KuesionerSyncService.php | Membentuk kontrak form dan metadata jawaban profil. | buildFormData(); getRefOptions(); getProfileQuestionConfigs(); sync profile metadata. | resources/js/Pages/Alumni/Profil/Index.vue; resources/js/components/AlumniProfil/FormPribadi.vue; FormAkademik.vue; FormKarier.vue; Stepper.vue | Form pribadi, akademik, karier, wilayah bertingkat, pekerjaan, perusahaan, atasan, gaji, dan studi lanjut. | Model Biodata, DataAkademik, Yudisium, User, Perusahaan, Atasan, Propinsi, Kabupaten, RefNegara; tabel biodata, data_akademik, yudisium, users, perusahaan, atasan, propinsi, kabupaten, ref_negara | v_alumni_kuesioner_autofill | 2026_09_02_000001_create_v_alumni_kuesioner_autofill_view.php; migration tabel master | Autofill hanya sumber baca; perubahan profil masuk ke tabel kanonik. | Load auth user and relations; load reference options; load question mappings/autofill. |
| Profil alumni - simpan, perusahaan, dan undangan atasan | Alumni | app/Http/Controllers/Alumni/Profil/SimpanProfilController.php | Menyimpan profil, menambah/mengusulkan perusahaan, dan mengirim undangan evaluasi. | simpanPerubahanProfil(); tambahPerusahaanBaru(); usulkanPembaruanPerusahaan(); kirimEmailAtasan(). | app/Services/Alumni/AdminAlumniProfileService.php; app/Services/Kuesioner/KuesionerSyncService.php; mail UndanganEvaluasiAtasanMail | Menormalisasi field profil, sinkron jawaban, dan menghubungkan perusahaan/atasan/evaluasi. | updateProfile(); syncProfileResponses(); syncDynamicProfileResponses(); send mail. | resources/js/Pages/Alumni/Profil/Index.vue; FormPribadi.vue; FormAkademik.vue; FormKarier.vue | Submit form, modal tambah perusahaan, usul perubahan data, dan undangan evaluator. | Model Biodata, DataAkademik, User, Perusahaan, Atasan, EvaluasiAtasan, LogActivity; tabel biodata, data_akademik, users, perusahaan, atasan, evaluasi_atasan, log_activities | - untuk write; v_alumni_kuesioner_autofill hanya dibaca saat form | 2026_09_01_000014_create_perusahaan_table.php; 000015_create_atasan_table.php; 000017_create_biodata_table.php; 000030_create_evaluasi_atasan_table.php; 000018_create_log_activities_table.php | Tidak ada tabel usulan terpisah; status pengajuan tersimpan pada perusahaan.status_verifikasi. | Validate and update profile; insert company; link biodata.perusahaan_id; create/update atasan and evaluation token; record log. |
| Kuesioner umum tracer - tampil | Alumni | app/Http/Controllers/Alumni/Kuesioner/KuesionerController.php | Memuat instrumen universitas, section, placement, opsi, dan jawaban existing. | tampilkanKuesioner(). | app/Services/Kuesioner/KuesionerSyncService.php; app/Services/Kuesioner/KelengkapanTracerService.php | Menentukan pertanyaan aktif/wajib, autofill, skip logic, dan status kelengkapan. | getDynamicProfileQuestions(); evaluasiKuesionerWajib(); syncProfileResponses(). | resources/js/Pages/Alumni/Kuesioner.vue; KartuPertanyaan.vue; Navigasi.vue; Stepper.vue; Banner.vue; TabelF2.vue; TabelF17.vue | Render tipe pilihan, matriks, progress, skip logic, dan data existing. | Model Kuesioner, KuesionerSection, KuesionerPertanyaan, KuesionerOpsi, KuesionerJawaban; tabel kuesioner, kuesioner_section, kuesioner_pertanyaan, kuesioner_opsi, kuesioner_jawaban | v_question_mappings, v_alumni_kuesioner_autofill, v_alumni_tracer_univ_status | 2026_09_01_000019_create_kuesioner_table.php; 000033-000037 tabel kuesioner; 2026_09_02_000000/000001/000005 view | Konfigurasi instrumen dan jawaban adalah sumber kanonik; view hanya membantu mapping/autofill/status. | Load active instrument/section/placement/options; join latest answers by biodata and question; compute progress. |
| Kuesioner umum tracer - simpan jawaban | Alumni | app/Http/Controllers/Alumni/Kuesioner/SimpanJawabanController.php | Memvalidasi dan menyimpan seluruh tipe jawaban, lalu menyinkronkan field bisnis. | store(); simpanJawabanKuesioner(). | app/Services/Kuesioner/KuesionerSyncService.php; KelengkapanTracerService.php; F3AnswerParser.php | Menyimpan jawaban teks/JSON, memetakan pertanyaan ke biodata/perusahaan/atasan, dan menghitung kelengkapan. | syncProfileResponses(); syncDynamicProfileResponses(); evaluasiKuesionerWajib(); parse(). | resources/js/Pages/Alumni/Kuesioner.vue; komponen KartuPertanyaan dan tabel matriks | Submit payload pertanyaan, pilihan, matriks, dan teks. | Model KuesionerJawaban, Biodata, DataAkademik, Perusahaan, Atasan; tabel kuesioner_jawaban, biodata, data_akademik, perusahaan, atasan | - pada write; status view membaca hasilnya | 2026_09_01_000037_create_kuesioner_jawaban_table.php; migration field profile/company terkait | jawaban_teks dipakai text/numeric; jawaban_json dipakai choice/matrix; mapping menjaga field kanonik tetap sinkron. | Upsert answer by respondent/question; parse F3; update mapped business fields; transaction and recalculate mandatory status. |
| Kuesioner prodi - tampil dan simpan | Alumni | app/Http/Controllers/Alumni/Kuesioner/KuesionerProdiController.php | Memuat placement prodi alumni dan menyimpan respons prodi. | tampilkanKuesionerProdi(); simpanJawaban(). | app/Services/Kuesioner/KuesionerSyncService.php | Menyinkronkan jawaban prodi tanpa mencampur status tracer universitas. | syncProdiResponses(); resolveAcademicIdentityField(). | resources/js/Pages/Alumni/KuesionerProdi.vue; KartuPertanyaanProdi.vue; Navigasi.vue; Stepper.vue; Banner.vue; TabelMatriksProdi.vue | Form section prodi, matriks, progress, validasi, dan submit. | Model KuesionerSection, KuesionerPertanyaan, KuesionerSectionPertanyaan, KuesionerOpsi, KuesionerJawaban, Prodi; tabel kuesioner_section, kuesioner_pertanyaan, kuesioner_section_pertanyaan, kuesioner_opsi, kuesioner_jawaban, prodi | v_alumni_tracer_prodi_status | 2026_09_01_000012_create_prodi_table.php; 000033-000037 tabel kuesioner; 2026_09_02_000004_create_v_alumni_tracer_prodi_status_view.php | Placement menentukan pertanyaan prodi; status view membaca jumlah wajib dan submitted answer. | Filter placement by prodi_id and active; upsert answers; calculate prodi completion/status. |
| Usulan perusahaan dan rekomendasi | Alumni, Admin Prodi, Admin Fakultas, Superadmin | app/Http/Controllers/Alumni/Profil/SimpanProfilController.php; app/Http/Controllers/AdminProdi/VerifikasiPerusahaan/VerifikasiPerusahaanProdiController.php; app/Http/Controllers/AdminFakultas/VerifikasiPerusahaan/VerifikasiPerusahaanFakultasController.php; app/Http/Controllers/SuperAdmin/Perusahaan/VerifikasiPerusahaanSuperAdminController.php | Alumni membuat/usul data; admin memverifikasi, mengganti, menolak, dan mencari rekomendasi sesuai scope. | Alumni: tambahPerusahaanBaru(), usulkanPembaruanPerusahaan(); admin: index(), verify(), replace(), updateAndVerify(), reject(), searchVerified(), recommendations(), destroy(); Superadmin juga store(). | app/Services/Perusahaan/PerusahaanVerificationService.php | Membersihkan nama, membuat token/akronim, menghitung kemiripan, memberi rekomendasi lokasi, dan mengubah relasi alumni secara aman. | getPendingForProdi/Fakultas(); getSimilarRecommendations(); searchVerified(); replaceCompany(); verifyDirectly(); updateAndVerify(); rejectCompany(); cleanCompanyName(); getTokens(); makeAcronym(). | resources/js/Pages/SuperAdmin/Perusahaan/Index.vue; modal di Alumni/Profil/Index.vue dan FormKarier.vue | Menampilkan pending company, rekomendasi, detail, verify, replace, edit/verify, reject, delete, dan pencarian master. | Model Perusahaan, Biodata, Prodi, Propinsi, Kabupaten, RefNegara, LogActivity; tabel perusahaan, biodata, prodi, propinsi, kabupaten, ref_negara, log_activities | - | 2026_09_01_000008 sampai 000014 untuk wilayah/perusahaan; 000017 biodata; 000018 log | Tidak ada recommendation table; rekomendasi dihitung runtime dan replace mengubah biodata.perusahaan_id. | Scope pending by role; normalize names; token/acronym similarity; geographic bonus; transaction update references and log. |
| Direktori alumni Prodi | Admin Prodi | app/Http/Controllers/AdminProdi/KelolaAlumni/DaftarAlumniProdiController.php | Menampilkan daftar, detail, edit profil terbatas, export, dan reminder dalam scope prodi. | index(); show(); updateProfile(); exportExcel(); sendReminderEmail(). | app/Services/Alumni/AlumniDirectoryQueryService.php; AdminAlumniProfileService.php; AlumniTracerExcelExporter.php | Query list/detail, form profile, export tracer, dan reminder. | getDirectoryData(); buildFormData(); getUnivSectionsWithAnswers(); getProdiSectionsWithAnswers(); download(). | resources/js/Pages/AdminProdi/Alumni/Index.vue; Show.vue; AlumniDirectoryShared.vue; AlumniDetailShared.vue; components/AlumniAudit/* | Filter/pagination direktori dan tab detail profil, univ, prodi, atasan, LinkedIn. | Model AlumniAuditRekap, Biodata, User, DataAkademik, Yudisium, Perusahaan, Atasan, EvaluasiAtasan; tabel kanonik terkait | v_alumni_audit_rekap, v_alumni_profile_summary, v_alumni_tracer_univ_status, v_alumni_tracer_prodi_status | 2026_09_02_000003 sampai 000006 view alumni; migration tabel kanonik | Read model mengurangi query status; update tetap dibatasi role dan scope. | Filter by prodi/status/tahun/search; eager-load relations; paginate; export selected/detail data. |
| Direktori alumni Fakultas | Admin Fakultas | app/Http/Controllers/AdminFakultas/KelolaAlumni/DaftarAlumniFakultasController.php | Menampilkan alumni seluruh prodi dalam fakultas dan reminder. | index(); sendReminderEmail(). | app/Services/Alumni/AlumniDirectoryQueryService.php | Menerapkan scope fakultas melalui prodi dan mengembalikan data direktori. | getDirectoryData(); applyRoleScope(). | resources/js/Pages/AdminFakultas/Alumni/Index.vue; Show.vue; AlumniDirectoryShared.vue; AlumniDetailShared.vue | Direktori dan detail bersama dengan scope fakultas. | Model AlumniAuditRekap, Prodi, Biodata, User; tabel ref_fakultas, prodi, biodata, users | v_alumni_audit_rekap, v_alumni_profile_summary | 2026_09_01_000009_create_ref_fakultas_table.php; view 2026_09_02_000003/000006 | Relasi prodi ke fakultas menentukan cakupan data. | Resolve faculty; whereIn prodi; filter/paginate; load audit summary. |
| Direktori alumni Biro 3 | Biro 3 | app/Http/Controllers/AdminBiroTiga/KelolaAlumni/DaftarAlumniController.php | Menampilkan direktori, export ZIP, reminder, dan blast email. | tampilkanDaftarAlumni(); exportZip(); sendReminderEmail(); blastEmail(). | AlumniDirectoryQueryService; AlumniTracerExcelExporter; AdminAlumniProfileService | Query daftar, menggabungkan export, dan menyiapkan email massal. | getDirectoryData(); download(); buildFormData(). | resources/js/Pages/AdminBiroTiga/AlumniIndex.vue; AlumniDirectoryShared.vue; AlumniShow.vue; AlumniDetailShared.vue | Direktori lintas scope, aksi email, export, dan detail audit. | Model AlumniAuditRekap, Biodata, User, Prodi, Perusahaan; tabel biodata, users, prodi, perusahaan | v_alumni_audit_rekap, v_alumni_profile_summary | 2026_09_02_000003/000006 dan tabel kanonik | Biro 3 dapat membaca data luas tetapi tidak otomatis boleh mengubah profil alumni. | Filter scope/status; query recipients; generate ZIP/Excel; send reminder/blast. |
| Direktori alumni Superadmin | Superadmin | app/Http/Controllers/SuperAdmin/KelolaAlumni/DaftarAlumniSuperAdminController.php | Direktori global, export ZIP, dan reminder tanpa scope organisasi. | index(); exportZip(); sendReminderEmail(). | AlumniDirectoryQueryService; AlumniTracerExcelExporter | Menyediakan query global dan file export. | getDirectoryData(); download(). | resources/js/Pages/SuperAdmin/Alumni/Index.vue; Show.vue; AlumniDirectoryShared.vue; AlumniDetailShared.vue | Filter global, export, dan detail audit. | Model AlumniAuditRekap, Biodata, User, Prodi, Perusahaan; tabel kanonik terkait | v_alumni_audit_rekap, v_alumni_profile_summary, v_alumni_linkedin_plot | 2026_09_02_000002/000003/000006 dan tabel kanonik | Superadmin dapat melihat seluruh status dan data lintas unit. | Global filter/search; eager-load; paginate; export selected/all records. |
| Detail alumni | Admin Prodi, Fakultas, Biro 3, Superadmin | app/Http/Controllers/AdminProdi/KelolaAlumni/DaftarAlumniProdiController.php; app/Http/Controllers/AdminFakultas/KelolaAlumni/DetailAlumniFakultasController.php; app/Http/Controllers/AdminBiroTiga/KelolaAlumni/DetailAlumniController.php; app/Http/Controllers/SuperAdmin/KelolaAlumni/DetailAlumniSuperAdminController.php | Menyusun detail audit dan aksi yang berbeda sesuai role. | show/tampilkanDetailAlumni(); updateProfile(); exportExcel(); buildLinkedinTraceMapping(). | AdminAlumniProfileService.php; UnifiedDetailAlumniService.php; KuesionerSyncService.php; LinkedinTraceHelper.php | Menghasilkan form profil, section/jawaban, audit, LinkedIn trace, dan export detail. | buildFormData(); getUnivSectionsWithAnswers(); getProdiSectionsWithAnswers(); getEvaluasiAtasanData(); buildLinkedinTraceMapping(); download(). | AdminProdi/Alumni/Show.vue; AdminFakultas/Alumni/Show.vue; AdminBiroTiga/AlumniShow.vue; SuperAdmin/Alumni/Show.vue; components/AlumniAudit/TabProfil.vue; TabKuesionerUniv.vue; TabKuesionerProdi.vue; TabEvaluasiAtasan.vue; TabLinkedin.vue | Tab detail profil, tracer, evaluasi atasan, LinkedIn, audit, dan aksi update/export sesuai permission. | Model AlumniProfileSummary, AlumniAuditRekap, AlumniTracerUnivStatus, AlumniTracerProdiStatus, AlumniLinkedinPlot, Biodata, User, DataAkademik, Yudisium, Perusahaan, Atasan, EvaluasiAtasan, LinkedinSyncResult | v_alumni_profile_summary, v_alumni_audit_rekap, v_alumni_tracer_univ_status, v_alumni_tracer_prodi_status, v_alumni_linkedin_plot | 2026_09_02_000002 sampai 000006; tabel profil/tracer/evaluasi/LinkedIn | View dipakai untuk status ringkas; detail section membaca tabel jawaban dan relasi aktif. | Load by alumni id with permission scope; eager-load relations; query section answers; build audit and LinkedIn timeline. |
| Direktori data atasan | Admin Prodi, Fakultas, Biro 3 baca; Superadmin CRUD | app/Http/Controllers/Shared/DataAtasan/DaftarAtasanController.php | Menampilkan data atasan sesuai role dan menyediakan CRUD Superadmin. | index(); store(); update(). | app/Services/Atasan/AtasanDirectoryQueryService.php | Menyusun query scope, search, relasi alumni/perusahaan, dan statistik atasan. | getDirectoryData(); buildQuery(); applyRoleScope(); formatRecord(); buildStats(). | resources/js/Pages/Shared/DataAtasan/Index.vue | Tabel atasan, filter, pagination, dan form CRUD Superadmin. | Model Atasan, Biodata, Perusahaan, User, Prodi, RefFakultas; tabel atasan, biodata, perusahaan, users, prodi, ref_fakultas | - | 2026_09_01_000009/000012/000015/000017 | Atasan terhubung ke alumni, perusahaan, dan evaluasi. | Search name/email/company; apply role scope; eager-load relations; aggregate alumni/evaluation count. |
| Kelola kuesioner prodi | Admin Prodi, Fakultas, Superadmin | app/Http/Controllers/Shared/Kuesioner/KelolaKuesionerProdiController.php | CRUD section, pertanyaan, opsi, placement, reorder, dan bank soal prodi. | index(); store/update/destroySection(); reorderSection(); toggleActive(); togglePlacementActive(); store/update/destroyQuestion(); reorderQuestion(); store/update/destroyOption(); attachFromBank(). | app/Services/Kuesioner/QuestionDefaultOptionsService.php | Membuat opsi default berdasarkan tipe pertanyaan; controller melakukan CRUD placement. | generateForUnifiedQuestion(); query/update placement and order. | resources/js/Pages/Shared/Kuesioner/ProdiKuesionerIndex.vue | Mengelola section/pertanyaan/opsi per prodi, placement bank soal, urutan, dan active state. | Model KuesionerSection, KuesionerPertanyaan, KuesionerSectionPertanyaan, KuesionerOpsi, Prodi, LogActivity; tabel kuesioner_*, prodi, log_activities | - | 2026_09_01_000012_create_prodi_table.php; 000033-000037_create_kuesioner_*_table.php; 000018_create_log_activities_table.php | Placement menjadi penghubung pertanyaan bank ke section/prodi. | Select prodi; query section/placement/question/option; transaction reorder; attach bank and log. |
| Kelola section prodi lama | Admin Prodi | app/Http/Controllers/AdminProdi/KelolaPertanyaan/KelolaSectionProdiController.php | Endpoint legacy untuk CRUD section prodi pada route lama. | index(); store(); update(); destroy(); reorder(). | - | Controller melakukan query dan update Eloquent langsung. | - | resources/js/Pages/Shared/Kuesioner/ProdiKuesionerIndex.vue | Menjadi UI yang sama atau kompatibel untuk section prodi. | Model KuesionerSection, Prodi, LogActivity; tabel kuesioner_section, prodi, log_activities | - | 2026_09_01_000012_create_prodi_table.php; 000033_create_kuesioner_section_table.php; 000018_create_log_activities_table.php | Route lama tetap menyimpan section pada tabel kanonik yang sama. | Filter section by prodi; CRUD order; record activity log. |
| Kelola kuesioner universitas | Superadmin | app/Http/Controllers/SuperAdmin/KelolaPertanyaan/DaftarPertanyaanController.php; SimpanPertanyaanController.php; KelolaOpsiController.php; SuperAdmin/KelolaSection/KelolaSectionController.php; KelolaKuesionerController.php | Memisahkan halaman bank pertanyaan, write pertanyaan/opsi, section, dan instrumen. | index(); store/update/destroy/toggleActive/reorder/toggleVisibility/attachFromBank(); storeOption/updateOption/destroyOption(); section CRUD/reorder; questionnaire CRUD/toggleActive. | app/Services/Kuesioner/QuestionDefaultOptionsService.php | Menyediakan opsi awal; controller lain memakai model langsung untuk konfigurasi. | generateForUnifiedQuestion(); CRUD/reorder/toggle model. | resources/js/Pages/SuperAdmin/Pertanyaan/Index.vue; Section/Index.vue | Mengatur bank soal, section, opsi, visibility, active, urutan, dan attachment. | Model Kuesioner, KuesionerSection, KuesionerPertanyaan, KuesionerSectionPertanyaan, KuesionerOpsi, LogActivity; tabel kuesioner_*, log_activities | v_question_mappings | 2026_09_01_000019_create_kuesioner_table.php; 000033-000037; 2026_09_02_000000_create_v_question_mappings_view.php | Instrumen aktif dibaca form alumni; placement memetakan pertanyaan ke section. | Query active instrument/section/placement; CRUD options; reorder; attach bank; log changes. |
| Isi evaluasi atasan publik | Publik/evaluator dengan token | app/Http/Controllers/EvaluasiAtasanController.php | Menampilkan form berbasis token dan menyimpan evaluasi atasan. | index(); show(token); store(token, Request). | app/Services/Alumni/AdminAlumniProfileService.php; app/Services/Kuesioner/KuesionerSyncService.php | Menyediakan referensi profil dan sinkronisasi field yang dikonfirmasi evaluator; tidak ada Evaluation service khusus. | getRefOptions(); syncProfileResponses(). | resources/js/Pages/Public/EvaluasiAtasanForm.vue; EvaluasiAtasanNotFound.vue | Form publik, validasi token, pertanyaan aktif, dan status token invalid/submitted. | Model EvaluasiAtasan, Atasan, Biodata, Perusahaan, KuesionerPertanyaan, KuesionerJawaban; tabel evaluasi_atasan, atasan, biodata, perusahaan, kuesioner_* | - | 2026_09_01_000014/000015/000017/000030/000033-000037 | Token dan status evaluasi berada di evaluasi_atasan; jawaban terhubung ke pertanyaan. | Find token; lock evaluation; load active questions/options; update evaluator data and insert answers in transaction. |
| Kelola instrumen evaluasi atasan | Superadmin | app/Http/Controllers/SuperAdmin/KelolaEvaluasiAtasanController.php | CRUD section, pertanyaan, opsi, active, reorder, dan attachment bank untuk evaluasi atasan. | index(); store/update/destroySection(); reorderSections(); store/update/destroyQuestion(); store/update/destroyOption(); toggleQuestion(); destroyQuestion(); reorderQuestions(); attachFromBank(). | app/Services/Kuesioner/QuestionDefaultOptionsService.php | Menghasilkan opsi default dan menjaga struktur instrumen tetap konsisten. | generateForUnifiedQuestion(); CRUD/reorder/toggle placement. | resources/js/Pages/SuperAdmin/EvaluasiAtasan/Index.vue | Konfigurasi section, pertanyaan, opsi, active, urutan, dan bank soal evaluasi. | Model KuesionerSection, KuesionerPertanyaan, KuesionerSectionPertanyaan, KuesionerOpsi, EvaluasiAtasan, LogActivity; tabel kuesioner_*, evaluasi_atasan, log_activities | - | 2026_09_01_000030_create_evaluasi_atasan_table.php; 000033-000037; 000018 | Instrumen memakai tabel pertanyaan bersama dan dibedakan oleh konteks placement/responden. | Query section/placement; CRUD; reorder; toggle active; attach bank; record log. |
| Sinkronisasi LinkedIn | Admin Prodi, Admin Fakultas, Superadmin; Biro 3 baca | app/Http/Controllers/Shared/LinkedInSyncController.php | Mengatur URL, sync single/batch, preview, approval/reject, plot ulang, dan histori. | index(); updateUrl(); syncSingle(); showResult(); approveResult(); plotAgain(); rejectResult(); history(); syncBatch(). | app/Services/LinkedIn/LinkedInProfileService.php; LinkedInProfileMapper.php; provider Apify/API/mock; Alumni/LinkedinTraceHelper.php; PerusahaanVerificationService.php | Provider mengambil data, mapper menormalisasi posisi/perusahaan, staging menyimpan hasil, approval menulis profil. | syncBiodata(); findByUrl/findByUsername(); determineCurrentPosition(); mapToBiodata(); resolveCompany(); getProfileItem(); getPreviewData(). | resources/js/Pages/SuperAdmin/LinkedIn/Index.vue; resources/js/Pages/Alumni/Components/AlumniDetailShared.vue; components/AlumniAudit/TabLinkedin.vue | Input URL, sync single/batch, preview, review, approve/reject, plot ulang, histori, dan status kecocokan. | Model LinkedinSyncResult, AlumniLinkedinPlot, Biodata, Perusahaan, User, LogActivity; tabel linkedin_sync_results, biodata, perusahaan, users, log_activities | v_alumni_linkedin_plot | 2026_09_01_000032_create_linkedin_sync_results_table.php; 2026_09_02_000002_create_v_alumni_linkedin_plot_view.php; tabel biodata/perusahaan/log | scraped_data menjadi staging JSON; approval/reject dan snapshot backup menjaga hasil scraping. | Validate URL; call provider; insert staging result; select latest; approve transaction update profile/company; save history and log. |
| Master wilayah, negara, dan UMP | Superadmin | app/Http/Controllers/SuperAdmin/MasterWilayah/MasterWilayahController.php | CRUD provinsi, kabupaten, negara, UMP, cascading kabupaten, dan copy UMP antar tahun. | index(); store/update/destroyProvinsi(); store/update/destroyKabupaten(); getKabupatenByProvinsi(); storeOrUpdateUmp(); destroyUmp(); copyYearUmp(); store/update/destroyNegara(). | - | Controller memakai validation, model, DB transaction, dan LogActivity langsung. | - | resources/js/Pages/SuperAdmin/MasterWilayah/Index.vue; resources/js/components/searchable-select.vue | Tab provinsi, kabupaten, negara, UMP, search, form, dan cascade kabupaten berdasarkan provinsi. | Model Propinsi, Kabupaten, RefNegara, Ump; tabel propinsi, kabupaten, ref_negara, ump | - | 2026_09_01_000008_create_propinsi_table.php; 000010_create_ref_negara_table.php; 000013_create_kabupaten_table.php; 000024_create_ump_table.php | Master ini dipakai form profil, perusahaan, lokasi kerja, dan kalkulasi salary vs UMP. | List/search/paginate; kabupaten where kode_provinsi; upsert UMP by province/year; copy records to new year. |
| Manajemen akun dan sinkronisasi alumni | Superadmin | app/Http/Controllers/SuperAdmin/ManajemenAkunController.php | Daftar/filter/edit akun, reset password, kontrol sync, dan pembuatan akun alumni. | index(); updateSyncAvailability(); update(); sendPasswordResetLink(); syncAlumniAccounts(). | app/Services/Otentikasi/PasswordResetNotificationService.php; - untuk sync langsung | Service hanya menangani reset notification; pencocokan akun dilakukan controller/model. | send(); getAccountSyncSetting(); syncAlumniAccounts(). | resources/js/Pages/SuperAdmin/ManajemenAkun/Index.vue | Filter role, edit akun, reset password, status buka/tutup sync, dan tombol sinkronisasi. | Model User, DataAkademik, Biodata, Prodi, RefFakultas; tabel users, data_akademik, biodata, prodi, ref_fakultas, account_sync_settings | - | 2026_09_01_000000_create_users_table.php; 000009_ref_fakultas; 000011_data_akademik; 000012_prodi; 2026_11_13_122710_create_account_sync_settings_table.php | account_sync_settings dibaca langsung dengan DB facade dan mengontrol feature flag sync. | Filter users; find academic records without account; insert/update user and biodata; read/update setting; send reset link. |
| Log aktivitas dan audit trail | Superadmin; ditulis proses penting semua role | app/Http/Controllers/SuperAdmin/LogAktivitasController.php | Menampilkan log aktivitas dengan filter dan pagination. | index(Request). | - | Tidak ada service khusus; LogActivity::record dipanggil controller/service lain. | LogActivity::record(); query filter di index(). | resources/js/Pages/SuperAdmin/Logs/Index.vue | Tabel audit, filter action/user/date/search, pagination, dan reload. | Model LogActivity; tabel log_activities | - | 2026_09_01_000018_create_log_activities_table.php | Append-only audit actor, action, description, IP, dan timestamp. | Query latest with user; where action/user/date/search; paginate. |
| Halaman publik beranda | Tamu/publik | app/Http/Controllers/Tamu/BerandaController.php | Menyajikan landing page, statistik, alumni unggulan, sebaran wilayah, dan direktori publik. | tampilkanBeranda(). | - | Controller mengambil data publik langsung dengan Eloquent dan DB query builder; tidak ada service khusus. | - | resources/js/Pages/Tamu/Beranda.vue; resources/js/components/landing/navbar.vue; hero-section.vue; stats-transformation-section.vue; alumni-map-section.vue; blog-section.vue; berkas-section.vue; tentang-section.vue; user-guide-section.vue; footer.vue | Landing page, statistik transformasi, peta domisili/karier, alumni unggulan, informasi, panduan, dan footer. | Model Biodata, Perusahaan, Prodi, Yudisium; tabel biodata, perusahaan, prodi, yudisium, kuesioner_jawaban, propinsi, kabupaten | - | 2026_09_01_000008_create_propinsi_table.php; 000011_create_data_akademik_table.php; 000012_create_prodi_table.php; 000013_create_kabupaten_table.php; 000014_create_perusahaan_table.php; 000016_create_yudisium_table.php; 000017_create_biodata_table.php; 000037_create_kuesioner_jawaban_table.php | Data dikirim sebagai props publik; field sensitif tidak ditampilkan dan query peta hanya mengirim agregat wilayah. | Count biodata/perusahaan/prodi/respondent; hitung persentase karier; ambil 8 alumni unggulan; group domisili dan lokasi karier by provinsi/kabupaten; eager-load direktori alumni. |

Catatan Lampiran B: beberapa row memakai lebih dari satu controller karena satu aktivitas memang memiliki controller berbeda per role. Komponen Vue bersama dicantumkan pada row aktivitas yang memasok payload. Jika komponen hanya presentasi dan tidak membaca endpoint/database, service, model, view, migration, dan query langsung ditulis -.

