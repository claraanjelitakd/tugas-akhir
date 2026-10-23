# Update Log Tracer Study

> Arsip perubahan historis. Keterangan fitur/angka pengujian berlaku pada waktu pencatatan, bukan jaminan kondisi terkini. Rujukan revisi 21 Oktober 2026: [DFD](dfd.md) dan [user flow](user-flow.md).

## 22 Oktober 2026

- **Dashboard Superadmin – metrik partisipasi dan kelengkapan**: menggunakan satu agregasi dari v_alumni_audit_rekap untuk response rate, kelengkapan profil, kelengkapan kuesioner universitas, dan gabungan keduanya. Kuesioner prodi sengaja tidak masuk ke ringkasan kelengkapan universitas.
- **Klasifikasi response rate**: menetapkan tiga status visual yang dapat diaudit, yaitu merah (<50%), kuning (50–74,9%), dan hijau (>=75%). Response rate mengukur alumni yang telah mulai menjawab, bukan bukti bahwa seluruh pertanyaan wajib sudah selesai.
- **UI Dashboard Superadmin**: menambahkan panel kelengkapan dengan jumlah dan persentase alumni lengkap, serta menyederhanakan aksen tampilan menjadi hijau tua dan kuning tua tanpa gradasi. Status response rate tetap memakai merah sebagai tanda perlu perhatian.
- Menambahkan feature test dashboard untuk kontrak metrik kelengkapan dan ketiga ambang response rate.

- **Kebijakan privasi profil alumni**: menyembunyikan NIK, NPWP, KK, NISN, BPJS, email mahasiswa, data pembimbing/penguji, publikasi, dan tab data orang tua dari halaman self-service tanpa menghapus kolom atau data lama. Judul tugas akhir dan tanggal kelulusan tetap ditampilkan sebagai referensi akademik non-sensitif.
- **Konfigurasi Vite**: mengganti penggunaan `__dirname` dengan `import.meta.dirname` pada alias agar warning `configLoader: 'native'` tidak muncul pada Vite terbaru.
- **Evaluasi Atasan**: memperbaiki pemetaan nilai jawaban (`nilai`), field kontak/perusahaan, status selesai, serta menghapus token akses dari payload halaman detail admin.
- **Sinkronisasi Take Home Pay**: menjadikan `biodata.gaji` sebagai sumber tunggal F505 dan menimpa jawaban tracer F505 yang masih menyimpan nominal lama. Ditegaskan bahwa `tracer.question_id` sudah merupakan foreign key ID subpertanyaan, sehingga tidak dibuat kolom ID duplikat.
- Membatasi props Inertia profil alumni ke konteks minimum yang dibutuhkan frontend dan menjaga field sensitif tetap utuh saat simpan profil parsial.
- Menambahkan migrasi `document_sensitive_profile_data_policy` untuk memberi komentar alasan retensi/minimisasi pada tabel dan kolom sensitif di MySQL.

- Membuat branch refactor/data-quality-structure untuk perbaikan kualitas data bertahap.
- Menormalisasi proyeksi jawaban tracer: record lama yang hanya memiliki answer di-backfill ke answer_json tanpa menghapus teks asli, dan model Tracer melengkapi pasangan representasi saat penyimpanan.
- Menambahkan response negotiation pada aksi approve, plot ulang, dan reject LinkedIn agar request Inertia menerima redirect yang valid, sedangkan fetch API tetap menerima JSON.
- Menampilkan seluruh field master perusahaan pada modal peninjauan dan menambahkan tombol informasi untuk melihat detail setiap rekomendasi master.
- Memperbarui ERD, use case, arsitektur, dan alur pengguna untuk kontrak jawaban tracer serta detail master perusahaan.
- Menyatukan sumber sidebar dan header dashboard untuk seluruh role admin melalui `resources/js/components/AdminSidebar.vue` dan `resources/js/components/AdminPageHeader.vue`.
- Menjadikan `SuperAdmin/Perusahaan/Index.vue` sebagai tampilan bersama Master Data Perusahaan bagi Superadmin, Fakultas, dan Prodi. Route, controller, dan batas data tetap dipisahkan berdasarkan role.
- Memastikan controller Fakultas dan Prodi memakai lingkup query yang sama untuk daftar perusahaan dan aksi verifikasi.
- Memperbarui dokumentasi arsitektur, use case, user flow, ERD/DFD, verifikasi perusahaan, dan LinkedIn. Tidak ada perubahan skema database atau provider LinkedIn.

## 15 Oktober 2026
- **Refactoring & Pembersihan Antarmuka Dashboard Alumni & Navbar**:
  - Menyederhanakan antarmuka [`Alumni/Dashboard.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Dashboard.vue) dengan menghapus badge decorative titik kuning portal, kartu ringkasan status kelengkapan 4 tahap di banner, banner langkah selanjutnya, dan kartu tahap 4 survei atasan.
  - Dashboard alumni kini secara bersih menampilkan ringkasan profil dan 3 kartu navigasi inti: Tahap 1 (Profil & Biodata), Tahap 2 (Kuesioner Universitas), dan Tahap 3 (Kuesioner Prodi).
  - Mengatur navigasi navbar [`Alumni/Components/Navbar.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Components/Navbar.vue): menu link tengah disembunyikan saat di dashboard karena navigasi sudah tersedia langsung di kartu utama halaman.
  - Mengubah tombol keluar menjadi ikon logout mandiri bernuansa merah (`text-rose-600`) tanpa tulisan "Keluar".
- **Pemisahan Pengiriman Email Undangan Kuesioner Evaluasi Atasan (On-Demand)**:
  - Mengeliminasi pengiriman email atasan otomatis saat menyimpan profil pada [`SimpanProfilController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/Alumni/Profil/SimpanProfilController.php) guna mencegah terkirimnya email saat terdapat kekeliruan data/email atasan.
  - Menyediakan rute endpoint baru `POST /alumni/kirim-email-atasan` (`alumni.kirim-email-atasan`) pada [`routes/web.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/routes/web.php).
  - Menyediakan tombol manual terdedikasi **"Kirim Email ke Atasan"** dengan konfirmasi SweetAlert dan status pengiriman pada Card 3 Formulir Karier Alumni ([`FormKarier.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/AlumniProfil/FormKarier.vue) & [`Index.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Profil/Index.vue)).
- **Penyelarasan Opsi Default Otomatis Instrumen Kuesioner (Prodi & Universitas)**:
  - Pembuatan service terpusat [`app/Services/Kuesioner/QuestionDefaultOptionsService.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Services/Kuesioner/QuestionDefaultOptionsService.php) untuk mengenerate opsi pilihan secara otomatis saat pertanyaan baru dibuat oleh Admin Prodi (`SimpanPertanyaanProdiController`), Super Admin Prodi (`KelolaKuesionerProdiSuperAdminController`), maupun Super Admin Universitas (`SimpanPertanyaanController`).
  - Pemetaan opsi default terstandarisasi:
    - `radio_text` (Pilihan Ganda + Lainnya): 2 opsi default (`Pilihan 1`, `Pilihan 2`) + 1 opsi kustom isian teks mandiri (`Lainnya (Tuliskan)`).
    - `rating_5` (Skala Rating 1-5): 5 opsi skala terurut (`1 - Sangat Rendah`, `2 - Rendah`, `3 - Cukup`, `4 - Tinggi`, `5 - Sangat Tinggi`). Opsi ini tetap dapat disesuaikan bunyinya oleh admin/superadmin via modal Kelola Opsi.
    - `radio_input` (Radio + Angka Bulan): 1 opsi pilihan numerik (`Kira-kira ... bulan`) + 1 opsi penolakan tanpa isian (`Tidak`).
    - `single_choice`, `multiple_choice`, `dropdown`, `searchable_select`: Otomatis 2 opsi default (`Pilihan 1`, `Pilihan 2`).
- **Pembersihan Tipe Pertanyaan Redundan & Membingungkan**:
  - Menghilangkan pilihan duplikat pada modal tambah pertanyaan Super Admin ([`SuperAdmin/Pertanyaan/Index.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/SuperAdmin/Pertanyaan/Index.vue)) dan Prodi ([`AdminProdi/Pertanyaan/Index.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/AdminProdi/Pertanyaan/Index.vue)): duplikasi `radio` vs `single_choice`, serta `checkbox` vs `multiple_choice`.
  - Mengeliminasi tipe matriks skala yang sudah tidak relevan/usang (`matrix`, `matrix_dual`, `multiple_textbox`) dari antarmuka dropdown penambahan soal.
  - Memperjelas penamaan label tipe `multiple_number` menjadi **"Rincian Nominal / Gaji (Take Home Pay)"**.
- **Perbaikan Rendering Frontend Kuesioner Prodi Alumni ([`KuesionerProdi.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/KuesionerProdi.vue))**:
  - Menambahkan template kartu interaktif untuk tipe `radio_text` (dengan input isian dinamis saat opsi "Lainnya" dipilih), `radio_input` (dengan kolom input angka dan satuan bulan), `dropdown`, `searchable_select`, `textarea`, dan `time`.
  - Membersihkan deklarasi duplikat pada helper `isCheckboxChecked` dan `toggleCheckboxOption` sehingga kompilasi Vite SFC berjalan bersih tanpa error.
  - Memperbaiki ekspresi reguler stripping label pada matriks skala `rating_5` (`replace(/^\d+\s*[-=:]\s*/, '')`) agar teks tidak menduplikasi angka indeks skor (contoh: `1` tidak lagi menjadi `1 - Sangat Rendah`).
- **Perbaikan Otorisasi & Penjagaan Profil Tambah Perusahaan Multi-Role**:
  - Memperluas middleware otorisasi rute `POST /alumni/company` pada [`routes/web.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/routes/web.php) dari semula khusus `role:alumni` menjadi multi-role: `role:alumni,admin_prodi,admin_fakultas,admin_biro3,superadmin`.
  - Menambahkan pengondisian peran pada [`SimpanProfilController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/Alumni/Profil/SimpanProfilController.php) agar asosiasi `biodata->update(['perusahaan_id' => ...])` hanya dijalankan untuk alumni, mencegah terjadinya manipulasi relasi profil ketika admin menambah perusahaan dari modal verifikasi/detail.
- **Automated Feature Testing & Standar Kode**:
  - Pembaruan unit & feature test suites: `tests/Feature/AdminProdiKuesionerTest.php`, `tests/Feature/SuperAdminKelolaPertanyaanTest.php`, `tests/Feature/BiroTigaKelolaPertanyaanTest.php`, dan `tests/Feature/VerifikasiPerusahaanTest.php` (seluruh tes lulus 100%).
  - Lolos pemformatan kode Laravel Pint (`vendor/bin/pint --dirty --format agent`).
  - Vite production bundle dikompilasi sukses tanpa peringatan galat.

## 11 Oktober 2026
- **Penyelarasan Tampilan & Navigasi Stepper Detail Alumni Multi-Role (Super Admin, Fakultas, Prodi, Biro 3)**:
  - Mengganti navigasi tab lama pada `SuperAdmin/Alumni/Show.vue`, `AdminProdi/Alumni/Show.vue`, `AdminFakultas/Alumni/Show.vue`, dan `AdminBiroTiga/AlumniShow.vue` dengan komponen Stepper horizontal terpadu sesuai standar pengisian kuesioner universitas alumni (`Stepper.vue`).
  - Palet warna identitas UKDW: Tahapan aktif emas (`#FFD700`) dan teks hijau (`#005B3C`), tahapan lengkap hijau almamater (`#005B3C`) dengan centang putih (`✓`), tahapan belum lengkap abu-abu netral dengan angka urut tahapan, badge mini centang di atas bulatan aktif jika 100% lengkap, serta garis penghubung hijau/abu-abu.
  - Menghapus Card Ringkasan Status Audit berulang (3 kolom progress bar) dan tombol `&larr; Kembali ke Daftar` di header agar tampilan lebih ringkas, elegan, dan lega. Navigasi kembali tetap mudah diakses via Breadcrumbs.
  - Menyatukan data profil alumni pada Biro 3 dalam format 1 tabel kontinu terpadu per seksi tanpa sub-tab bertingkat berulang.
- **Standardisasi Kolom Status & Aksi Direktori Mahasiswa (Index)**:
  - Kolom status terpadu 3-in-1 (Kelengkapan Profil, Kuesioner Universitas, Evaluasi Atasan) dengan persentase dan label status ringkas pada seluruh role (`SuperAdmin`, `AdminBiroTiga`, `AdminFakultas`, `AdminProdi`).
  - Kolom aksi baris terpadu: Tombol Detail (`👁️`), Hubungi via WhatsApp (`💬`) dengan template resmi, tautan portal & panduan login, Kirim Email Pengingat Kuesioner Langsung (`✉️`), serta Sinkronisasi LinkedIn Direct & Riwayat Scraping (`🔗` / `H`).
- **Mailable & Fitur Kirim Email Pengingat Kuesioner Alumni**:
  - Pembuatan Mailable `App\Mail\PengingatKuesionerAlumniMail` dan template email Blade responsif `resources/views/emails/pengingat_kuesioner_alumni.blade.php`.
  - Integrasi endpoint kirim email langsung per alumni di semua controller direktori alumni dengan pop-up notifikasi SweetAlert2.
- **Penyelarasan Filter Tahun Kelulusan & Konsistensi Dashboard**:
  - Menyamakan kalkulasi dan filter tahun kelulusan mahasiswa/alumni antara dashboard rekapitulasi, direktori alumni, dan service kelengkapan tracer study di seluruh tingkatan peran.
- **Automated Feature Testing & Standar Kode**:
  - Penambahan feature test suite `tests/Feature/BiroTigaDaftarAlumniTest.php` dan pembaruan `tests/Feature/SuperAdminDaftarAlumniTest.php` (seluruh pengujian lulus 100%).
  - Lolos pemformatan kode Laravel Pint (`vendor/bin/pint --dirty --format agent`).
  - Vite production bundle dikompilasi sukses tanpa peringatan galat.

## 09 Oktober 2026
- **Penyelarasan Desain & Fitur Alumni Multi-Role (Super Admin, Fakultas, Prodi)**:
  - Mengeliminasi kolom Status yang redundan pada tabel direktori alumni di Super Admin, Admin Fakultas, dan Admin Prodi untuk tampilan yang lebih bersih, konsisten, dan fokus pada data inti lulusan.
  - Menyeragamkan halaman Detail Alumni (`Show.vue`) pada Fakultas dan Prodi menjadi 5 tab komprehensif identik dengan Super Admin: Tab 1 (Kuesioner Universitas), Tab 2 (Kuesioner Prodi), Tab 3 (Data Profil Lengkap), Tab 4 (Hasil Scraping & Trace LinkedIn), dan Tab 5 (Evaluasi Pengguna Lulusan / Atasan).
  - Ekstraksi helper bersama [`app/Services/Alumni/LinkedinTraceHelper.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Services/Alumni/LinkedinTraceHelper.php) untuk mengisolasi logika penyusunan audit trace, perbandingan field, dan histori sinkronisasi LinkedIn lintas controller.
- **Perbaikan Tata Letak Tampilan Alumni Terpotong (Sidebar Layout Fix)**:
  - Memperbaiki padding responsif `lg:pl-72` pada kontainer `<main>` di [`resources/js/Pages/AdminProdi/Alumni/Index.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/AdminProdi/Alumni/Index.vue) dan [`resources/js/Pages/AdminFakultas/Alumni/Index.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/AdminFakultas/Alumni/Index.vue).
  - Memastikan konten judul halaman, 4 kartu ringkasan metrik statistik alumni, foto avatar, nama, dan tabel tidak lagi tertutup atau terpotong oleh fixed sidebar `w-72`.
- **Penyempurnaan Algoritma Rekomendasi Kecocokan Perusahaan (`PerusahaanVerificationService.php`)**:
  - Pembersihan teks mendalam (*Text Preprocessing*): Memotong teks keterangan cabang/kantor di dalam tanda kurung `(...)` (seperti `(Kantor Pusat)`, `(Persero)`), serta memperluas daftar stop words unit/cabang administratif (`kantor`, `pusat`, `cabang`, `kcu`, `witel`, `divisi`, `office`, `hq`, dll).
  - Deteksi Akronim Natural: Menghubungkan nama panjang seperti `PT Bank Central Asia Tbk` $\rightarrow$ `bca`, cocok dengan `PT BCA INDONESIA` (skor 88%).
  - Level 3B (Compound Token Acronym Intersect): Mendeteksi kecocokan parsial akronim pada nama majemuk entitas anak/afiliasi seperti `BCA Digital` $\leftrightarrow$ `Bank Central Asia` (skor 75%).
  - Penyesuaian Ambang Batas Token Overlap (33%): Menetapkan rasio irisan kata minimal $\ge 33\%$ (sepertiga bagian nama) untuk menangani variasi nama dengan panjang token berbeda secara adil.
  - Pengecualian Kata Sektor Industri Generik Tunggal: Menambahkan `$genericIndustryWords` (`bank`, `universitas`, `rs`, `hotel`, `studio`, `cafe`, dll) sehingga entitas seperti `Bank Mandiri` tidak lagi merekomendasikan seluruh bank lain di Indonesia, namun tetap merekomendasikan entitas spesifik seperti `CV Theresia Inovasi Mandiri` (58%).
- **Penetapan Aturan Keamanan & Kontrol Versi (`.agents/rules/git-push-policy.md` & `AGENTS.md`)**:
  - Menetapkan kebijakan ketat pelarangan eksekusi `git push` otomatis tanpa persetujuan eksplisit dari pengguna guna menjamin seluruh perubahan terverifikasi dan aman sebelum dipublikasikan ke remote repository.

## 06 Oktober 2026
- **Integrasi Provider Pihak Ketiga Apify LinkedIn**: Menambahkan `ApifyLinkedInProvider` memanfaatkan actor Apify `data_forge_org~linkedin-scraper` via autentikasi aman Bearer token (`APIFY_API_TOKEN`) tanpa mengekspos token pada query string URL.
- **Tabel Staging & Histori Audit (`linkedin_sync_results`)**: Data hasil scraping LinkedIn masuk ke tabel staging terisolasi (`linkedin_sync_results`) berstatus `pending` sehingga data master alumni tidak langsung terpengaruh sebelum direview.
- **Alur Review & Persetujuan SuperAdmin (Approval Workflow)**: Antarmuka review perbandingan data hasil scraping vs data utama alumni eksisting dengan tombol aksi Approve (menerapkan jabatan & institusi perusahaan baru berstatus 'Menunggu Verifikasi') dan Reject (menolak hasil scraping tanpa mengubah data utama).
- **Download Permanen Foto Profil LinkedIn**: Mengunduh foto profil LinkedIn secara fisik ke storage server lokal (`public/uploads/profile/profile_{nim}_{timestamp}.jpg`) untuk mengatasi URL CDN LinkedIn bertoken sementara (`?e=...`) yang cepat kedaluwarsa.
- **Kartu Foto Profil & Unggah Mandiri (`FormPribadi.vue` & `SimpanProfilController.php`)**: Menambahkan preview foto profil interaktif pada halaman Biodata Alumni dengan badge status sumber data (Sinkronisasi LinkedIn / Unggah Mandiri) serta dukungan unggah file foto fisik mandiri.
- **Tab 4 Detail Alumni: Tabel Audit & Trace Pemetaan Scraping (`Show.vue`)**: Menyediakan tab khusus *"Hasil Scraping & Trace LinkedIn"* pada halaman Detail Alumni SuperAdmin (`/superadmin/alumni/{id}`), menampilkan:
  1. Ringkasan kartu status, URL LinkedIn, tanggal scraping, dan reviewer.
  2. Filter pencarian teks instan secara real-time pada atribut scraping.
  3. JSON Viewer interaktif dengan penyorotan sintaks dan tombol salin raw JSON.
  4. Tabel audit perbandingan komprehensif: Key Atribut Scraping $\rightarrow$ Deskripsi Field $\rightarrow$ Nilai Mentah Apify $\rightarrow$ Target Kolom Basis Data $\rightarrow$ Nilai Riil Terkini di Database $\rightarrow$ Status Audit (Cocok, Tersinkronisasi, Menunggu Verifikasi, Belum Dipetakan).
  5. Riwayat historis sinkronisasi alumni terdahulu.
- **Sinkronisasi Otomatis Status Pekerjaan & Jabatan Kustom (`FormKarier.vue`)**:
  - Memperbaiki pemetaan `kategori_pekerjaan` alumni dari hasil sinkronisasi LinkedIn menjadi `'Pekerja'` sehingga kartu radio status pekerjaan langsung terpilih secara otomatis.
  - Menambahkan dukungan jabatan kustom dinamis: jabatan dari LinkedIn yang belum ada di daftar referensi kampus otomatis dimasukkan ke opsi pilihan dan ditampilkan dengan toggle switch `+ Tulis Jabatan Kustom`.
- **Perbaikan Rute URL Aliasing `/superadmin/linkedin`**: Mendaftarkan route alias untuk `/superadmin/linkedin` menuju `LinkedInSyncController@index` guna mengatasi error 404 ketika pengguna mengakses URL pendek tersebut.
- **Automated Feature Testing**: Menambahkan pengujian komprehensif `tests/Feature/ApifyLinkedInProviderTest.php` (23 skenario) dan memperbarui `tests/Feature/SuperAdminLinkedInSyncTest.php` (17 skenario). Seluruh test berhasil (100% passed).


## 03 Oktober 2026
- **Verifikasi & Approval Perusahaan**: Membuka akses aksi verifikasi (*Verify*, *Edit*, *Auto Replace*, *Reject*) pada data perusahaan berstatus **Terverifikasi** di SuperAdmin, Admin Prodi, dan Admin Fakultas, serta menghapus `->where('status_verifikasi', 'Menunggu Verifikasi')` di controller untuk menghilangkan error 404 saat melakukan *Auto Replace*.
- **SuperAdmin Manajemen Akun**: Memperbaiki komputasi `currentFakultasId` pada modal **Sunting Data Akun Pengguna** (`SuperAdmin/ManajemenAkun/Index.vue`) agar Fakultas alumni/admin terisi otomatis secara akurat, serta menambahkan listener penyesuaian otomatis saat Program Studi diubah.
- **Fitur LinkedIn Sync**: Halaman Sinkronisasi LinkedIn SuperAdmin dengan 5 kartu metrik analitik, aksi sinkronisasi individual & massal per angkatan, dan abstraksi `LINKEDIN_DRIVER` (`mock` / `api`) via `AppServiceProvider`.
- **Master Data CSV**: Memindahkan berkas master CSV ke `/data`, menyempurnakan `RefNegaraSeeder.php` dan `WilayahSeeder.php` dengan stream `fgetcsv` & proteksi *unique constraint*.

## 28 September 2026
- **Database (Kolom Foto Alumni)**: Menambahkan kolom `foto` (nullable string) pada tabel `biodata` (`2026_09_28_054830_add_foto_to_biodata_table.php`) untuk direktori `/uploads/profile`.
- **Backend & Model**: Memperbarui `$fillable` pada `Biodata.php` dan memperjelas relasi `yudisium()` ke `belongsTo(Yudisium::class, 'yudisium_id')` untuk mengakses judul TA, URL publikasi, dan jenis publikasi.
- **Backend (BerandaController)**: Eager loading relasi `yudisium` dan atribut `foto`, agregasi spasial `alumniWilayah` untuk Leaflet tooltip hover (nama PT dan nama alumni), serta query referensi `allProvinsi` dan `allKabupaten`.
- **Frontend (Modular Landing Components)**: Menata 10 komponen beranda di `resources/js/components/landing/` (`hero-section.vue`, `alumni-map-section.vue`, `testimonial-slider-section.vue`, `stats-transformation-section.vue`, `sektor-alumni-section.vue`, `career-pillars-section.vue`, `user-guide-section.vue`, `berkas-section.vue`, `blog-section.vue`, `tentang-section.vue`).
- **Frontend (SweetAlert2 Detail Alumni & Leaflet Map)**: Pop-up detail lengkap berbasis SweetAlert2 yang memuat identitas, foto, institusi, skripsi & tautan publikasi repositori, serta tombol sorot peta Leaflet 38 provinsi.

## 21 September 2026
- **Fix (Tombol Tambah & Edit Pertanyaan)**: Memperbaiki referensi variabel `initialKeterangan` pada modal SweetAlert2 di `SuperAdmin/Pertanyaan/Index.vue` serta mengisi form value secara aman via `didOpen` agar tombol **Tambah Pertanyaan** dan tombol pensil **Edit** dapat dibuka dengan normal tanpa error JavaScript.
- **Backend (Validasi Tipe & Keterangan)**: Menambahkan tipe `radio`, `checkbox`, dan field `keterangan` (batasan nilai/constraint) ke dalam validasi `SimpanPertanyaanController.php` pada method `store` dan `update`.
- **UI/UX (Eliminasi Duplikasi Tombol Tambah Opsi)**: Menghapus kontainer duplikat tombol tambah opsi pada baris pertanyaan, memusatkan penambahan opsi pada 1 tombol saja yaitu ikon `+` di kolom Aksi.
- **Feature (Restriksi Opsi Berdasarkan Tipe Soal)**: Membatasi tombol tambah opsi dan kartu opsi hanya untuk 9 tipe yang mendukung opsi (`radio`, `radio_input`, `radio_text`, `multiple_choice`, `rating_5`, `multiple_number`, `matrix`, `matrix_dual`, `multiple_textbox`), serta membersihkan tipe duplikat/tidak terpakai.
- **UI/UX (Cascading Jump Logic)**: Mengubah dropdown alur lompatan (*jump logic*) menjadi 2 langkah bertingkat (Langkah 1: Pilih Section, Langkah 2: Pilih Pertanyaan Target) pada Super Admin dan Admin Biro 3 sehingga tidak menampilkan daftar panjang 70+ soal sekaligus.
- **Database & Seeding (Gaji F505)**: Menyederhanakan opsi pendapatan per bulan (*Take Home Pay* / F505) menjadi 1 opsi tunggal (`F5051`), membersihkan opsi lama `F5052` dan `F5053`, serta menambahkan catatan batasan/constraint minimal Rp 1.000 (kelipatan ribuan).
- **UI/UX (Kuning Khusus Header)**: Menandai butir kuesioner tipe `header` dengan warna kuning lembut (`bg-[#FEF9C3]`), aksen border kuning (`border-l-[#EAB308]`), dan badge kuning *Header Kuesioner*.
- **UI/UX (Super Admin Table Redesign)**: Mengubah tampilan **Kelola Section** dan **Kelola Pertanyaan** menjadi format data table modern, bersih, minimalis, dan elegan dengan tombol aksi berbasis ikon SVG bersih (Detail/Mata, Edit/Pensil, Kelola Opsi, Hapus/Sampah, Reorder Naik/Turun).
- **Feature (Dropdown Filter Section & Target)**: Menampilkan seluruh soal (**ALL**) secara *default* dengan dropdown filter section, filter lokasi tampil, serta pencarian teks terintegrasi.
- **Database & Architecture (`tampil_di`)**: Menambahkan kolom `tampil_di` (`kuesioner`, `profile`, `both`) pada tabel `ref_subpertanyaan2021` agar visibilitas butir soal antara kuesioner universitas dan halaman profil alumni dapat dikonfigurasi dinamis langsung dari Super Admin.
- **UI/UX (Super Admin Sidebar)**: Membersihkan pintasan publik (*Lihat Beranda Publik*) dari sidebar SuperAdmin untuk navigasi yang lebih terfokus dan rapi.
- **Testing**: Penambahan automated feature tests `SuperAdminKelolaPertanyaanTest` (59 tests passed).

## 20 September 2026
- **UI/UX (Super Admin Sidebar)**: Mengubah sistem navigasi modul Super Admin dari navigasi atas (*top bar*) menjadi navigasi **Sidebar tetap** (`Sidebar.vue`) di sisi kiri (`w-72`) dengan drawer mobile dan tautan menu terpusat.
- **Database & Seeding**: Seeding lengkap 15 atribut tabel `perusahaan` (`PerusahaanSeeder.php`), foreign key provinsi/kabupaten, dan perbaikan view `v_alumni_kuesioner_autofill` untuk `BIO_PENDIDIKAN_TINGKAT`.
- **Feature (Studi Lanjut F18)**: Sinkronisasi 2 arah profil studi lanjut (`pendidikan_tingkat`, `perguruan_tinggi`, `pendidikan_prodi`) ke kuesioner `F18b` dan `F18c` (`KuesionerSyncService` & `KuesionerController`).
- **UI/UX (Kuesioner)**: Tampilan kartu `F18` ditata bersih (`F18b` & `F18c` 2 kolom, `F18d` input type date).
- **UI/UX (Tabel F17)**: Format skala langsung `Sangat Rendah 1 2 3 4 5 Sangat Tinggi` dengan palet 2-warna UKDW (`#005B3C` dan `#FDC700`).
- **Logic**: Percabangan kuesioner (`jump_to`) kini database-driven tanpa hardcoded question ID di client.
- **Documentation & ERD**: Pembaruan menyeluruh pada `ERD.md`, `DAFTAR_PERTANYAAN_MAPPING.md`, dan `UPDATE_LOG.md`.

## 4 September 2026
- **Fix**: Menambahkan relasi user pada eager loading Alumni::with() di DaftarAlumniController dan DetailAlumniController untuk mencegah error render Vue.
- **Feature**: Mendesain ulang Login.vue menggunakan referensi SIMASTER UGM (split screen, bg overlay, username/NIM).
- **Feature**: Mengubah indikator loading di Login.vue dari spinner tombol menjadi Popup Alert Vue yang interaktif.
- **UI/UX**: Mengubah tampilan global \pigo-loader.vue\ menjadi popup modern (glassmorphism) yang seragam dengan halaman login.
- **Bug Fix**: Memperbaiki navbar di halaman *Landing Page* (Tamu) yang menyebabkan *stuck* karena melempar *user* kembali ke \/login\. Tombol kini berubah cerdas menjadi 'Dashboard' dan mengarah ke _dashboard_ masing-masing role.
- **Keamanan**: Menambahkan konfigurasi \SESSION_EXPIRE_ON_CLOSE=true\ di \.env\ agar sesi/kuki otomatis dihapus saat browser ditutup (menyelesaikan masalah nyangkut login otomatis).
