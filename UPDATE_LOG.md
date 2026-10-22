# UPDATE LOG - SERU (Sistem Ekosistem Rekam Jejak Alumni)

## [2026-10-22] Konsistensi Antarmuka Multi-Role dan Penyatuan Master Perusahaan

- Menambahkan `resources/js/components/AdminSidebar.vue` sebagai sumber konfigurasi navigasi bersama untuk Superadmin, Admin Fakultas, Admin Prodi, dan Admin Biro III. Wrapper sidebar pada setiap folder role mempertahankan kontrak props dan jalur pemakaian halaman yang sudah ada.
- Menambahkan `resources/js/components/AdminPageHeader.vue` untuk header dashboard admin yang seragam.
- Menyatukan tampilan Master Data Perusahaan pada `resources/js/Pages/SuperAdmin/Perusahaan/Index.vue`. Halaman tersebut digunakan oleh route Superadmin, Fakultas, dan Prodi melalui props `role`, `basePath`, `dashboardPath`, konteks unit, serta `canCreate`.
- Otorisasi data tetap berada di controller: Superadmin mengakses seluruh data; Fakultas hanya data milik prodi dalam fakultas; Prodi hanya data yang terkait prodi pengguna. Query daftar dan aksi verifikasi memakai lingkup yang sama.
- Menambahkan akses pengelolaan Kuesioner Per Prodi untuk Fakultas dan Prodi pada halaman bersama, dengan daftar program studi dibatasi sesuai role.
- Menghapus override CSS global yang menyebabkan warna teks header Superadmin bertabrakan dengan latar terang.
- Memperbarui dokumentasi arsitektur, use case, user flow, ERD/DFD, catatan LinkedIn, dan dokumentasi verifikasi perusahaan. Perubahan ini tidak menambah migrasi atau mengubah skema basis data serta provider LinkedIn.
- Verifikasi terarah: 16 test feature dengan 120 assertion lulus; Laravel Pint lulus; Vite production build lulus.

> Arsip perubahan historis. Keterangan fitur/angka pengujian berlaku pada waktu pencatatan, bukan jaminan kondisi terkini. Rujukan revisi 21 Oktober 2026: [DFD](docs/dfd.md) dan [user flow](docs/user-flow.md).
## [2026-10-21] Revisi Dokumentasi dan Diagram Aliran Data Kendall

- Membaca PDF Kendall & Kendall Bab 7 yang disediakan pengguna.
- Memperbarui batas sistem, role, pengiriman undangan evaluasi dan perilaku provider LinkedIn berdasarkan kode.
- Mengganti diagram konteks dan Diagram 0; menyediakan XML `.drawio` dengan dua sheet, SVG/PNG, serta registri JSON berisi 154 aliran (90 eksternal) dan 13 simpanan logis.
- Memodelkan referensi akademik lokal sebagai D12; integrasi SIAKAD tidak dianggap aktif tanpa konektor.
- Menyesuaikan README, use case, user flow, ERD, pemetaan pertanyaan, kuesioner, activity diagram, database views dan panduan integrasi.
- Mengganti tautan lokal mesin menjadi tautan relatif. Catatan perubahan lama dipertahankan sebagai arsip historis.
- Perubahan hanya dokumentasi dan aset diagram; tidak mengubah kode aplikasi atau skema database.

## [2026-10-21] Pembersihan Arsitektur Database (Standarisasi NIM CHAR(8), Tahun Masuk YEAR, Enum Agama & Status, Single Postal Code) dan Pembaruan Total Dokumen ERD (Mermaid & Data Dictionary)

### Ringkasan Pembaruan
1. **Pembersihan Arsitektur & Standarisasi Skema Basis Data**:
   - **Pemberantasan Redudansi Kode Pos (*Zero Duplication*)**:
     - Menghapus kolom fisik `zipcode` dan accessor `kode_pos_saat_ini` dari tabel dan model `biodata`.
     - Kode pos domisili alumni terpusat tunggal pada `biodata.kode_pos` (`CHAR(5)`).
     - Kode pos kantor/perusahaan terpusat tunggal pada tabel master `perusahaan.kode_pos` (`VARCHAR(10)`).
     - Mempertahankan `nik` (`CHAR(16)`) dan `npwp` (`VARCHAR(25)`) pada `biodata` untuk kebutuhan kependudukan dan perpajakan alumni.
   - **Standarisasi Tipe Data & Constraints**:
     - Format NIM distandarisasi secara ketat menjadi `CHAR(8)` pada semua tabel terkait (`data_akademik`, `biodata`, `data_orang_tua`, `yudisium`).
     - Kolom `angkatan_masuk` distandarisasi menjadi tipe data `YEAR(4)`.
     - Kolom `agama` distandarisasi menggunakan `ENUM('Islam', 'Kristen Protestan', 'Katolik', 'Hindu', 'Buddha', 'Konghucu', 'Lainnya')`.
     - Kolom `status_mahasiswa` distandarisasi menggunakan `ENUM('Lulus', 'Aktif', 'Cuti', 'Drop Out', 'Lainnya')` dengan default `'Lulus'`.
     - Kolom `skala` pada master perusahaan dilengkapi opsi `Regional/Lokal`.
     - Menambahkan komentar deskriptif (*database column comments*) pada seluruh kolom di semua migrasi.
2. **Penyempurnaan Layanan & Komponen Frontend**:
   - `SimpanProfilController` dan `AdminAlumniProfileService` memisahkan secara tegas kode pos domisili pribadi dan kode pos kantor perusahaan.
   - `PerusahaanVerificationService::replaceCompany()` secara transaksional mengalihkan relasi profil, evaluasi atasan, dan butir kuesioner tracer alumni saat pengajuan usulan disinkronkan ke institusi resmi.
   - Komponen formulir karier (`FormKarier.vue`) dan validasi profil (`Index.vue`) menyelaraskan label formal "Kode Pos Kantor / Perusahaan" dengan penanganan field terpadu.
3. **Pembaruan Menyeluruh Dokumen ERD (`ERD.md`)**:
   - Diagram Mermaid disinkronkan 100% dengan skema fisik database aktif.
   - Kamus data (*data dictionary*) dan matriks relasi antar-tabel diperbarui mencerminkan relasi bebas duplikasi.

---

## [2026-10-20] Penyatuan Tampilan Kuesioner Universitas ala Master Data UMP dan Form Evaluasi Atasan Enterprise (Full-Width, Mobile Responsive, Palet Hijau & Emas UKDW)

### Ringkasan Pembaruan
1. **Penyatuan Tampilan Kuesioner Universitas Terpadu ala Master Data UMP**:
   - **Navigasi Tab Horizontal Terpadu (`Pertanyaan/Index.vue` & `Section/Index.vue`)**:
     - Kedua halaman kini menggunakan tata letak header seragam dengan breadcrumb formal: `Dashboard / Manajemen Kuesioner / Kuesioner Universitas`.
     - Menyediakan bilah navigasi tab horizontal (*Pill Navigation*) beridentitas resmi UKDW (`#0D542B`):
       - **Tab 1: Butir Pertanyaan** (`/superadmin/pertanyaan`) dengan counter total butir pertanyaan.
       - **Tab 2: Bagian / Seksi (Section)** (`/superadmin/sections`) dengan counter total seksi kuesioner.
     - Tombol aksi header dinamis menyesuaikan konteks tab aktif: `+ Tambah Pertanyaan` pada Tab 1, serta `Kelola Kuesioner Induk` dan `+ Tambah Bagian` pada Tab 2.
     - Admin dapat berpindah antar-manajemen kuesioner dengan cepat tanpa perlu melakukan klik berulang di sidebar.
   - **Perapian Menu Sidebar Super Administrator (`Sidebar.vue`)**:
     - Menggabungkan dua menu sebelumnya ("Kelola Bagian / Seksi" dan "Kelola Butir Kuesioner") menjadi satu menu terpadu: **"Kuesioner Universitas"**.
     - Status menu aktif otomatis terjaga saat admin berada di rute `/superadmin/pertanyaan*` maupun `/superadmin/sections*`.

2. **Perombakan Total Formulir Publik Evaluasi Atasan (`EvaluasiAtasanForm.vue` & `EvaluasiAtasanNotFound.vue`)**:
   - **Bebas Warna Biru (Palet Tegas Hijau & Kuning/Emas Resmi UKDW)**:
     - Mengeliminasi seluruh aksen warna biru bawaan dan browser default (seperti `focus:ring-blue-500`, tombol biru, dan radio button default).
     - Menggunakan skema warna resmi: **Solid Hijau UKDW (`#0D542B`)** dan **Aksen Emas/Kuning UKDW (`#FDC700`)** dengan latar netral slate bersih.
     - Menambahkan atribut styling `accent-[#0D542B]` pada seluruh kontrol radio button dan selector formulir.
   - **Layout Full Page Kanan-Kiri (Enterprise Wide Layout)**:
     - Menghilangkan pembatas sempit `max-w-7xl` sehingga halaman membentang penuh (*full width*) dari tepi kiri ke kanan layar monitor (`w-full px-4 sm:px-6 lg:px-10`).
     - Header topbar dan footer universitas membentang penuh dengan garis aksen emas 4px di bawahnya.
   - **100% Responsif di Semua Jenis Smartphone (HP)**:
     - **Mode Mobile (Layar HP < 768px)**: Tabel 7 kolom yang rentan terpotong di HP digantikan secara otomatis dengan kartu evaluasi per aspek dengan tombol selector sentuh (*touch-friendly thumb pills*). Pilihan yang dipilih atasan langsung berubah warna menjadi hijau `#0D542B` dengan indikator dot emas `#FDC700`.
     - **Mode Tablet & Desktop (Layar >= 768px)**: Disajikan dalam tabel matriks resmi bergaris tegas, header hijau `#0D542B`, dan baris selang-seling yang nyaman dibaca.
   - **Penyempurnaan Penanganan Rute `/evaluasi-atasan`**:
     - Ditambahkan rute `evaluasi-atasan.index` pada `EvaluasiAtasanController::index` yang mengarahkan pembukaan URL root `/evaluasi-atasan` atau `/evaluasi-atasan/` langsung ke formulir evaluasi atasan aktif tanpa memicu error 404.
     - Halaman fallback `EvaluasiAtasanNotFound.vue` diselaraskan menjadi full width dengan palet hijau-emas dan input kode token mandiri.

3. **Verifikasi & Integritas Sistem**:
   - Seluruh 150 automated tests PHPUnit (937 assertions) berhasil lulus 100%.
   - Kompilasi aset Vite `npm run build` sukses 100% tanpa peringatan atau error.
   - Pengujian tampilan desktop dan mobile via browser subagent menghasilkan tampilan berstandar korporat yang presisi dan bersih.

---

### Ringkasan Pembaruan
1. **Penerapan Arsitektur Bersih Zero-Cross Boundary & DRY**:
   - **Eliminasi Boundary Crossing Antar-Role**: Menghapus seluruh import lintas folder role (seperti SuperAdmin mengimport dari Alumni atau sebaliknya).
   - **Komponen Bersama Profil Alumni (`resources/js/Components/AlumniProfil/`)**:
     - `FormPribadi.vue`, `FormAkademik.vue`, `FormOrangTua.vue`, `FormKarier.vue`, dan `Stepper.vue` dipusatkan di folder netral bersama.
     - Folder lama `resources/js/Components/AlumniProfil/` dihapus secara permanen.
   - **Komponen Bersama Audit Kuesioner & Stepper (`resources/js/Components/AlumniAudit/`)**:
     - `AlumniStepper.vue`: Komponen stepper mandiri berlingkaran nomor dan centang hijau (`✓`) dengan v-model `activeMainTab` dan kalkulasi garis penghubung otomatis.
     - `TabProfil.vue`: Tab audit biodata alumni dengan integrasi floating scroll button dan mode `readonly`.
     - `TabKuesionerUniv.vue`, `TabKuesionerProdi.vue`, `TabEvaluasiAtasan.vue`, dan `TabLinkedin.vue` dipusatkan di folder netral bersama.
     - Folder lama `resources/js/Components/AlumniAudit/` dihapus secara permanen.

2. **Layanan Backend Terpusat (`AlumniDirectoryQueryService.php`)**:
   - Dibuat di `app/Services/Alumni/AlumniDirectoryQueryService.php` untuk mengeliminasi duplikasi kode query di 4 controller direktori alumni.
   - Mengenkapsulasi query basis data dari database view `v_alumni_audit_rekap`.
   - Mengelola filter komprehensif (`search`, `tahun`, `semester`, `target`, `status`, `linkedin_status`, `prodi_id`, `fakultas_id`).
   - Melakukan kalkulasi 5 kartu statistik ringkasan, evaluasi kelengkapan kuesioner tracer study, evaluasi kepuasan atasan langsung, dan riwayat sinkronisasi LinkedIn.
   - Seluruh 4 controller (`DaftarAlumniSuperAdminController`, `DaftarAlumniFakultasController`, `DaftarAlumniProdiController`, `DaftarAlumniController`) kini ramping dan murni memanggil service ini.

3. **Pemindahan Controller LinkedIn ke Namespace Shared**:
   - `LinkedInSyncController` dipindahkan ke `app/Http/Controllers/Shared/LinkedInSyncController.php`.
   - Seluruh 21 rute web sinkronisasi LinkedIn (`/superadmin/linkedin-sync`, `/prodi/linkedin-sync`, `/fakultas/linkedin-sync`) diperbarui dengan import netral.

4. **Penegakan Hak Akses Read-Only Khusus Biro 3**:
   - Memastikan role Biro 3 murni memiliki akses audit baca (*Read-Only*) untuk profil alumni dan LinkedIn.
   - Menghapus tombol pemicu scraping manual LinkedIn di halaman indeks (`AdminBiroTiga/AlumniIndex.vue`).
   - Menyematkan prop `readonly: true` pada `TabProfil.vue` dan `TabLinkedin.vue` di `AdminBiroTiga/AlumniShow.vue`.

5. **Dokumentasi Kode Komprehensif**:
   - Seluruh variabel `const`, `ref`, `props`, `emits`, dan methods pada komponen baru dan controller dilengkapi komentar formal (JSDoc dan PHPDoc) tanpa emoticon.
   - Seluruh 150 automated tests PHPUnit (937 assertions) dan kompilasi Vite `npm run build` lulus 100%.

---

## [2026-10-19] Pembaruan Direktori Alumni Super Admin: Integrasi Filter LinkedIn, Kartu Statistik LinkedIn, Scorecard Kelengkapan Formal 2x2, dan Audit Trace Staging Review

### Ringkasan Pembaruan
1. **Peningkatan Format Status Kelengkapan Menjadi Scorecard Formal 2x2 (`/superadmin/alumni`)**:
   - Menghilangkan format teks bertumpuk lama (`Univ: 100% Prodi: 100% Profil: Lengkap • Atasan: Belum Ada`).
   - Menerapkan format **Scorecard Mini 2x2 berbingkai slate formal** (`bg-slate-50/90 border border-slate-200/80`):
     - **Univ**: Persentase kuesioner universitas Dikti dengan badge hijau formal (`text-emerald-700`).
     - **Prodi**: Persentase kuesioner program studi.
     - **Profil**: Status kelengkapan data diri dan kontak (*Lengkap* / *Belum*).
     - **Atasan**: Status respon evaluasi kepuasan atasan (*Diisi* / *Menunggu* / *Belum Ada*).
   - Memenuhi pedoman antarmuka enterprise: bersih, datar (*flat*), simetris, dan bebas emoticon.

2. **Penambahan Kolom Terdedikasi Sinkronisasi LinkedIn**:
   - Menambahkan kolom terpisah **Sinkronisasi LinkedIn** pada tabel alumni tanpa menimbulkan horizontal scrollbar.
   - Menyajikan informasi komprehensif:
     - **Status Badge Formal**: *Sudah Disinkronkan* (hijau emerald), *Pending Review* (amber), *Belum Disinkron* (slate), atau *Ditolak* (rose).
     - **Waktu Terakhir Sinkronisasi**: Tanggal dan jam persetujuan / penarikan scraping (format `DD/MM/YYYY HH:mm`).
     - **Nama Reviewer**: Menampilkan identitas admin yang meninjau dan menetapkan data (*Super Administrator* atau *Admin Prodi*).
     - **Pekerjaan & Instansi**: Menampilkan ringkasan posisi jabatan dan nama instansi yang disinkronkan dari LinkedIn.
     - **Tautan Cepat**: Tombol *Rincian* yang langsung mengarahkan ke tab audit trace scraping LinkedIn alumni (`/superadmin/alumni/{id}?tab=linkedin`).

3. **Penambahan 1 Kartu Statistik Interaktif LinkedIn (5 Kartu)**:
   - Menambahkan kartu metrik ke-5 di baris atas statistik direktori: **Tersinkron LinkedIn**.
   - Menampilkan total alumni tersinkron LinkedIn dan persentase kepatuhan terhadap total populasi alumni pada tahun kelulusan aktif.
   - Kartu dapat diklik langsung untuk memfilter tabel menampilkan alumni yang telah tersinkronisasi.

4. **Penambahan Dropdown Filter Status LinkedIn**:
   - Menambahkan kontrol pilihan **Status LinkedIn** pada panel form pencarian:
     - *Semua LinkedIn*
     - *Sudah Disinkronkan*
     - *Pending Review*
     - *Belum Disinkron*
     - *Ditolak*

5. **Optimalisasi Backend & Eager Loading Reviewer (`DaftarAlumniSuperAdminController.php`)**:
   - Mengimplementasikan eager loading `reviewer:id,name,email` pada query `LinkedinSyncResult` untuk efisiensi kueri database.
   - Menangani kalkulasi metrik `total_linkedin_synced` dan `persentase_linkedin` secara dinamis.
   - Membaca dan memproses parameter filter `linkedin_status`.
   - Menambahkan dokumentasi PHPDoc lengkap pada seluruh method controller.

---

## [2026-10-19] Standardisasi Desain UI Formal Super Admin, Refaktorisasi Modular Kuesioner Prodi, dan Peningkatan UX Profil Alumni (Stepper & Unified Navigation)

### Ringkasan Pembaruan
1. **Peningkatan UX & Desain Formulir Profil Alumni (`/alumni/profile`)**:
   - **Stepper Horizontal Full-Width**: Mengganti tombol tab yang datar dengan komponen modular [`Stepper.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/AlumniProfil/Stepper.vue) yang membentang penuh (*full-width*) selebar kartu form. Dilengkapi nomor langkah 1..4, centang putih (`✓`) dinamis, serta status badge *"Sudah Lengkap"* saat seluruh atribut wajib di tahapan tersebut telah terpenuhi secara valid.
   - **Menghilangkan Kesan Tombol Ganda (*Double Buttons*)**: Menyederhanakan *Bottom Action Bar* dari sebelumnya dua tombol bersaing (`Lanjut` vs `Simpan Perubahan`) menjadi satu alur jelas:
     - Tahap 1 s.d. 3: Tombol utama hijau **`Simpan & Lanjut: [Nama Langkah Berikutnya] →`** (menyimpan data form secara otomatis dan memindahkan tampilan ke tab berikutnya) didampingi tombol sekunder **`Simpan Saja`**.
     - Tahap 4 (Terakhir): Tombol utama hijau tunggal **`Simpan Seluruh Profil`**.
   - **Pembersihan Header**: Menghapus kotak petunjuk lama dan indikator yang tidak diperlukan sehingga header profil tampil bersih, rapi, dan minimalis.
   - **Standarisasi Input Enterprise**: Menghilangkan warna hijau dan merah tebal yang berlebihan pada form input biodata, orang tua, dan karier, beralih ke border netral slate dengan ring fokus hijau UKDW `#0D542B`.
   - **Tombol Navigasi Cepat**: Menambahkan *floating action button* di sudut kanan bawah untuk gulir cepat ke paling atas (*scroll-to-top*) dan ke paling bawah (*scroll-to-bottom*).

2. **Refaktorisasi Modular & Harmonisasi Kuesioner Prodi dengan Kuesioner Universitas**:
   - **Komponen Kuesioner Prodi Modular**: Memecah halaman monolitik `KuesionerProdi.vue` menjadi sub-komponen terisolasi di `resources/js/Pages/Alumni/Components/KuesionerProdi/`:
     - [`Banner.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Components/KuesionerProdi/Banner.vue): Banner identitas prodi, status kelulusan, dan batas waktu pengisian.
     - [`Stepper.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Components/KuesionerProdi/Stepper.vue): Indikator tahapan seksi prodi dengan nomor seksi sekuensial dan centang validasi selesai.
     - [`KartuPertanyaanProdi.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Components/KuesionerProdi/KartuPertanyaanProdi.vue): Rendering dinamis tipe pertanyaan teks, angka, radio, checkbox, dropdown, dan skala Likert.
     - [`Navigasi.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Components/KuesionerProdi/Navigasi.vue): Tombol navigasi seksi sebelumnya, selanjutnya, dan simpan jawaban kuesioner prodi.
   - **Konsistensi Visual & Label Wajib**: Menyamakan estetika visual stepper, tombol navigasi, dan indikator wajib (tanda bintang merah `*` standar) antara Kuesioner Universitas dan Kuesioner Prodi.
   - **Pembersihan Seed & Filter**: Memperbaiki seeder pertanyaan prodi untuk memastikan tidak ada pertanyaan wisuda yang tidak relevan.

3. **Pembersihan & Penyeragaman Desain Antarmuka Super Admin**:
   - **Penyeragaman Ikon**: Mengganti ikon campuran dengan ikon SVG/Lucide yang konsisten dan seragam di seluruh modul (Manajemen Akun, Master Wilayah, Alumni, Perusahaan, Pertanyaan, Section, Logs, Evaluasi Atasan).
   - **Garis Tabel Rapi & Stabilitas Zoom**: Merapikan seluruh tabel data dengan batas garis jelas (`divide-y divide-gray-200 border border-gray-200`) dan tata letak stabil saat halaman diperkecil/diperbesar (*zoom stability*).
   - **Penghapusan Multi-Dot Status Pill**: Menghilangkan widget status kolom bertumpuk titik-titik warna (*multi-dot pill*) dan menggantikannya dengan badge label status yang ringkas, bersih, dan konsisten (seperti badge status pada halaman Perusahaan).
   - **Penyederhanaan Header Filter**: Menghilangkan ikon corong filter, grafik, jam, dan elemen dekoratif berlebihan. Mengubah persentase kelulusan kuesioner menjadi filter klik interaktif yang bersih dan formal.

4. **Pembaruan Pedoman Standar UI di `AGENTS.md` & `CLAUDE.md`**:
   - Mencatat aturan tegas tata kelola antarmuka: dilarang menggunakan emoticon/emoji, dilarang menggunakan icon dekoratif berlebihan, dan dilarang membuat widget status kolom multi-dot.
   - Menegaskan aturan keamanan operasional Git: larangan keras menjalankan `git push` tanpa instruksi eksplisit dari pengguna.

5. **Pengujian & Verifikasi**:
   - ✅ **150 Feature & Unit Tests** lulus 100% (`150 passed, 937 assertions`).
   - ✅ **Vite Production Build**: `npm run build` sukses 100% tanpa error (`✓ built in 5.46s`).
   - ✅ **Laravel Pint**: Lulus verifikasi format kode PHP (`{"tool":"pint","result":"passed"}`).

---

## [2026-10-08] Deep Linking Dua Arah Detail Alumni - LinkedIn Sync, Tombol Aksi Ikon Mata, Skema Kolom Skills & Experience, dan Replikasi Database Views

### Ringkasan Pembaruan
1. **Navigasi Terarah & Deep Linking dari Detail Alumni (`/superadmin/alumni/{id}`) ke LinkedIn Sync**:
   - **Tautan Banner Terarah**: Mengubah tautan gambar/logo LinkedIn dan tombol *"Buka Menu LinkedIn Sync &rarr;"* pada kartu banner *Audit & Trace Hasil Scraping LinkedIn* di [`Show.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/SuperAdmin/Alumni/Show.vue) dari rute umum (`/superadmin/linkedin-sync`) menjadi rute terarah berparameter query:
     `/superadmin/linkedin-sync?alumni_id=${alumni.id}&search=${encodeURIComponent(alumni.nim || alumni.nama)}`.
   - **Deteksi Tahun Kelulusan Otomatis (Backend)**: Di [`LinkedInSyncController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/Shared/LinkedInSyncController.php), saat parameter `alumni_id` diterima, controller otomatis mendeteksi tahun kelulusan alumni terkait (`tahun_lulus` / `dataAkademik->tahun_lulus`) dan mengeset `$tahunTerpilih` ke tahun tersebut, serta menyertakan alumni dalam kueri data sehingga alumni yang dicari dipastikan muncul di tabel tanpa terblokir oleh filter default tahun terkini.
   - **State Initial Search & Row Highlighting (Frontend)**: Di [`Index.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/SuperAdmin/LinkedIn/Index.vue), props `targetAlumniId` dan `initialSearch` diterima. State `search` otomatis terisi dengan NIM/nama alumni target.
   - **Visual Feedback & Auto-Scroll**: Baris alumni target ditandai dengan sorotan cincin hijau emerald (`ring-2 ring-emerald-500 bg-emerald-50/80`), badge animasi *"Profil Dipilih"*, dan secara otomatis digulirkan (*smooth auto-scroll*) ke tengah layar viewport saat halaman dimuat (`onMounted`).

2. **Tombol Aksi Detail Alumni (Ikon Mata) di Tabel LinkedIn Sync**:
   - **Aksi Cepat Lintas Modul**: Pada tabel sinkronisasi LinkedIn di [`Index.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/SuperAdmin/LinkedIn/Index.vue), menambahkan tombol aksi dengan ikon mata SVG (*Lihat Detail Alumni*) di kolom **Aksi** untuk kedua mode provider (baik Apify Provider maupun Official Provider).
   - **Tautan Langsung**: Mengarahkan Super Admin langsung ke halaman Detail Alumni terkait (`/superadmin/alumni/{alumni.id}`), e.g. `http://127.0.0.1:8000/superadmin/alumni/23`, untuk mempermudah pengecekan lembar kuesioner universitas, kuesioner prodi, evaluasi atasan, dan tab audit trace LinkedIn.

3. **Standarisasi Penamaan Kolom `skills` & `experience` pada Tabel `biodata`**:
   - **Migrasi Database**: Menjalankan migrasi [`2026_10_08_000000_rename_minat_and_expert_to_experience_and_skills_in_biodata_table.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/database/migrations/2026_10_08_000000_rename_minat_and_expert_to_experience_and_skills_in_biodata_table.php) yang mengganti nama kolom:
     - `expert` &rarr; `skills` (daftar keahlian teknis alumni)
     - `minat` &rarr; `experience` (riwayat pengalaman kerja & profesional alumni)
   - **Model & Service Update**: Memperbarui `$fillable` pada [`Biodata.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Models/Biodata.php), pemetaan controller [`SimpanProfilController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/Alumni/Profil/SimpanProfilController.php), [`AdminAlumniProfileService.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Services/Alumni/AdminAlumniProfileService.php), ekspor Excel `AlumniDataSheet.php`, dan seeder [`BiodataSeeder.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/database/seeders/BiodataSeeder.php).
   - **Replikasi Database View**: Memperbarui view SQL `v_alumni_profile_summary` dan `v_alumni_audit_rekap` untuk mereferensikan `b.skills` dan `b.experience`.

4. **Verifikasi Kualitas & Pengujian Menyeluruh**:
   - ✅ **123 Feature & Unit Tests** lulus 100% (`123 passed, 745 assertions`).
   - ✅ **Vite Frontend Build**: `npm run build` berhasil tanpa error.
   - ✅ **Laravel Pint**: Formatter agent passed (0 linting issues).

---

## [2026-10-06] Integrasi Provider Pihak Ketiga Apify LinkedIn, Tabel Staging Audit, Pemetaan Trace Database, dan Sinkronisasi Foto Profil Fisik

### Ringkasan Pembaruan
1. **Penyediaan Provider Pihak Ketiga LinkedIn (Apify Scraper Engine)**:
   - **Multi-Provider Architecture**: Menambahkan [`ApifyLinkedInProvider.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Services/LinkedIn/Providers/ApifyLinkedInProvider.php) yang mengimplementasikan `LinkedInProfileProvider` tanpa mengganggu Provider Resmi LinkedIn API Server-to-Server (`ApiLinkedInProvider`) maupun Mock Provider (`MockLinkedInProvider`).
   - **Actor Support**: Terintegrasi dengan Actor Apify `data_forge_org~linkedin-scraper` dan `apify/linkedin-profile-scraper` menggunakan Bearer token authentication (`APIFY_API_TOKEN`) dan pemantauan status run otomatis via polling dataset API.
   - **Cost Safety & Rate Limiting**: Sinkronisasi dibatasi 1 profil per request (menonaktifkan bulk sync massal untuk provider pihak ketiga) guna menjaga kuota Apify.

2. **Tabel Staging & Histori Audit Trail (`linkedin_sync_results`)**:
   - **Migrasi**: [`2026_10_05_130000_create_linkedin_sync_results_table.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/database/migrations/2026_10_05_130000_create_linkedin_sync_results_table.php) menyematkan kolom `biodata_id`, `linkedin_url`, `linkedin_username`, `scraped_data` (JSON mentah lengkap), `status` (*pending*, *approved*, *rejected*), `reviewed_by`, `reviewed_at`, dan `scraped_at`.
   - **Model Eloquent**: [`LinkedinSyncResult.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Models/LinkedinSyncResult.php) dengan relasi `belongsTo(Biodata::class)` dan `belongsTo(User::class, 'reviewed_by')`.
   - **Relasi Biodata**: Menambahkan relasi `linkedinSyncResults()` dan `latestLinkedinSyncResult()` pada [`Biodata.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Models/Biodata.php).
   - **Proteksi Data Utama**: Hasil scraping tidak langsung menimpa tabel `biodata` dan `perusahaan`, melainkan melalui alur review dan persetujuan (approval) oleh Super Admin.

3. **Sinkronisasi Fisik Foto Profil Alumni (`public/uploads/profile/`)**:
   - **Download Permanen**: Sistem secara otomatis mengunduh file gambar profil dari CDN LinkedIn dan menyimpannya secara fisik ke `public/uploads/profile/profile_{NIM}_{timestamp}.jpg`. Hal ini mencegah foto menjadi *broken image* akibat token URL LinkedIn yang kedaluwarsa (*expired*) atau terblokir kebijakan CORS/hotlinking.
   - **Pemberian Akses Data Form**: Menambahkan kolom `'foto' => $biodata?->foto ?? ''` pada [`ProfilController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/Alumni/Profil/ProfilController.php) dan [`AdminAlumniProfileService.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Services/Alumni/AdminAlumniProfileService.php) agar foto profil dapat diakses seragam oleh Alumni, Super Admin, Admin Prodi, Admin Fakultas, dan Biro 3.
   - **Avatar & Upload UI**: Menambahkan kartu foto profil di [`FormPribadi.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/AlumniProfil/FormPribadi.vue) dengan status verifikasi, pratinjau instan, tautan resolusi penuh, dan opsi unggah file mandiri (JPG/PNG max 2MB).
   - **Header Avatar**: Menampilkan avatar foto profil alumni pada header halaman detail mahasiswa ([`SuperAdmin/Alumni/Show.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/SuperAdmin/Alumni/Show.vue)).

4. **Penyesuaian Status Pekerjaan & Fleksibilitas Jabatan Kustom**:
   - **Normalisasi Status Pekerjaan**: Menyimpan status pekerjaan sebagai `'Pekerja'` (bukan `'Bekerja (Full Time)'`) saat approve LinkedIn, sehingga kartu radio **Pekerja / Karyawan** di [`FormKarier.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/AlumniProfil/FormKarier.vue) otomatis aktif dan terpilih.
   - **Dukungan Jabatan Non-Standar**: Jabatan profesional dari LinkedIn (seperti *"Wakil Dekan I"*) otomatis disisipkan ke dropdown jabatan struktural (F2G), dan ditambahkan tombol toggle **`+ Tulis Jabatan Kustom`** untuk fleksibilitas pengetikan posisi jabatan di luar pilihan baku Dikti.
   - **Integritas Perusahaan**: Perusahaan baru yang dibuat dari LinkedIn selalu disetel berstatus `'Menunggu Verifikasi'` untuk mematuhi regulasi validasi universitas.

5. **Tab Audit Trace Pemetaan Data LinkedIn (Detail Alumni Super Admin)**:
   - **Tab 4 Show.vue**: Menambahkan **Tab 4: Hasil Scraping & Trace LinkedIn** pada [`SuperAdmin/Alumni/Show.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/SuperAdmin/Alumni/Show.vue).
   - **Audit Trace Table**: Tabel komprehensif yang memetakan seluruh atribut scraping mentah Apify (No, Key, Deskripsi, Nilai Scraping, Target Tabel & Kolom Database, Nilai Aktual di Database Saat Ini, dan Status Audit).
   - **Fitur Pendukung**: Kotak pencarian filter teks real-time, syntax viewer JSON mentah dengan tombol salin (*Copy Raw JSON*), serta tabel histori staging sinkronisasi sebelumnya.
   - **Route Alias**: Menambahkan rute alias `/superadmin/linkedin` dan `/superadmin/linkedin-sync` pada [`routes/web.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/routes/web.php) untuk mencegah 404.

6. **Quality Assurance & Pengujian Otomatis**:
   - ✅ **40 Feature Tests LinkedIn** lulus (`tests/Feature/SuperAdminLinkedInSyncTest.php` & `tests/Feature/ApifyLinkedInProviderTest.php`).
   - ✅ **35 Feature Tests Alumni** lulus (`php artisan test --filter=Alumni`).
   - ✅ **Laravel Pint**: Passed (0 linting issues).
   - ✅ **Vite Frontend Build**: `npm run build` sukses tanpa error.

---

## [2026-10-05] Normalisasi Basis Data Evaluasi Atasan, Migrasi Create Murni, Kuesioner Dinamis Kesiapan Kerja, dan Full-Page Form Sticky Header

### Ringkasan Pembaruan
1. **Normalisasi Entitas Perusahaan, Atasan, dan Evaluasi Pengguna Lulusan**:
   - **Eliminasi Redundansi**: Menghapus duplikasi kolom institusi/perusahaan dari tabel `evaluasi_atasan` (`nama_perusahaan`, `alamat_lengkap`, `no_telp_fax`, `bentuk_perusahaan`, `skala_perusahaan`, `jumlah_pegawai`).
   - **Single Source of Truth**:
     - Tabel `perusahaan`: Menyimpan `nama_perusahaan`, `alamat`, `no_telp_fax`, `homepage`, `skala` (*Lokal, Nasional, Internasional*), `bentuk_perusahaan` (*BUMN, Perusahaan Terbatas, Koperasi, CV, Firma*), dan `jumlah_pegawai` (*< 50 Orang, 51 - 100 Orang, ..., > 500 Orang*).
     - Tabel `atasan`: Menyimpan data kontak pimpinan (`nama`, `email`, `telepon`).
     - Tabel `evaluasi_atasan`: Hanya menyimpan transaksi sesi survei berbobot alumni UKDW (`jumlah_alumni_ukdw`, `standar_gaji_pertama`, `token`, `is_submitted`, `submitted_at`).
   - **Kuesioner Dinamis Kesiapan Kerja**: Memindahkan butir `tingkat_kesiapan_kerja` dari kolom flat tabel evaluasi menjadi butir pertanyaan dinamis kuesioner pada tabel `pertanyaan_evaluasi_atasan` (tipe `pilihan_ganda`, 4 opsi) dan disimpan pada tabel `respon_evaluasi_atasan`.

2. **Konsolidasi Migrasi "Create-Only" & Pembersihan File Patch**:
   - **Migrasi Bersih & Terstruktur**:
     - [`2026_09_01_171342_create_companies_table.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/database/migrations/2026_09_01_171342_create_companies_table.php): Definisi murni tabel `perusahaan` mencakup kolom baru `homepage`, `no_telp_fax`, `bentuk_perusahaan`, `jumlah_pegawai` dengan komentar skema lengkap.
     - [`2026_10_05_120000_create_evaluasi_atasans_table.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/database/migrations/2026_10_05_120000_create_evaluasi_atasans_table.php): Definisi murni tabel `evaluasi_atasan`, `pertanyaan_evaluasi_atasan`, dan `respon_evaluasi_atasan` tanpa perlu patch alter/drop column tambahan.
   - **Penghapusan File Migrasi Patch Usang**:
     - Menghapus `database/migrations/2026_10_05_130000_update_evaluasi_atasan_and_perusahaan_tables.php`.
     - Menghapus `database/migrations/2026_10_05_130500_remove_kuesioner_columns_from_evaluasi_atasan_table.php`.
     - Menghapus `database/migrations/2026_10_05_135000_drop_skala_perusahaan_from_evaluasi_atasan_table.php`.
   - **Sinkronisasi Status Migrasi**: Membersihkan riwayat tabel `migrations` sehingga `php artisan migrate:status` berstatus rapi dan seluruh batch valid.

3. **Restrukturisasi Seeder Pertanyaan Evaluasi Atasan**:
   - [`PertanyaanEvaluasiAtasanSeeder.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/database/seeders/PertanyaanEvaluasiAtasanSeeder.php):
     - Butir Urutan 1 (`KES_1`): Tingkat Kesiapan Alumni dalam Bekerja (Tipe: `pilihan_ganda`, opsi: *Sangat siap*, *Cukup Siap*, *Kurang siap*, *Tidak siap*).
     - Butir Urutan 2 s/d 13 (`ASP_01` s/d `ASP_12`): 12 Aspek Kinerja Lulusan UKDW (Tipe: `likert_5`, skala 1: Sangat Kurang s/d 5: Sangat Baik).
   - Terdaftar resmi pada [`DatabaseSeeder.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/database/seeders/DatabaseSeeder.php).

4. **Penyempurnaan Controller & Tampilan Full-Page Form dengan Sticky Header**:
   - [`EvaluasiAtasanController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/EvaluasiAtasanController.php):
     - Method `show()`: Memuat profil perusahaan, atasan, biodata alumni, dan bank pertanyaan aktif secara terurut.
     - Method `store()`: Menyimpan pembaruan profil perusahaan ke tabel `perusahaan`, profil pimpinan ke `atasan`, agregat survey ke `evaluasi_atasan`, serta perulangan penyimpanan jawaban kesiapan kerja & 12 aspek Likert ke `respon_evaluasi_atasan`.
   - [`EvaluasiAtasanForm.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Public/EvaluasiAtasanForm.vue):
     - Tampilan korporat formal UKDW full-page publik tanpa menu navigasi internal alumni.
     - **Sticky Table Header**: Header matriks penilaian 12 aspek kinerja lulusan tetap melayang (*sticky `top-0`*) saat tabel digulir ke bawah, dilengkapi badge tag nama dan prodi alumni yang sedang dinilai.

5. **Pembaruan Dokumentasi Arsitektur & User Flow**:
   - [`docs/user-flow.md`](docs/user-flow.md): Menambahkan Seksi 7 (Alur Pengguna: Atasan Langsung / Pengguna Lulusan) lengkap dengan diagram alur Mermaid dan matriks hak akses RBAC.
   - [`ERD.md`](ERD.md): Memperbarui diagram Mermaid relasi, kamus data Modul Evaluasi Pengguna Lulusan (Seksi G), dan matriks relasi antar tabel.
   - [`DAFTAR_PERTANYAAN_MAPPING.md`](DAFTAR_PERTANYAAN_MAPPING.md): Memperbarui 4 pintu pengisian sistem dan memetakan struktur form Bagian I (Profil Perusahaan) dan Bagian II (13 Butir Kuesioner Evaluasi Kinerja + Catatan Khusus).
   - Seluruh Feature Test pada [`EvaluasiAtasanTest.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/tests/Feature/EvaluasiAtasanTest.php) lulus 100% (3 skenario, 24 assertions).

---

### Ringkasan Pembaruan
1. **Fleksibilitas Pengelolaan Master Perusahaan (Aksi & Auto Replace)**:
   - **Perbaikan Controller**: Menghapus batasan strict `->where('status_verifikasi', 'Menunggu Verifikasi')` pada method `verify()`, `replace()`, `updateAndVerify()`, dan `reject()` di:
     - [`VerifikasiPerusahaanSuperAdminController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/SuperAdmin/Perusahaan/VerifikasiPerusahaanSuperAdminController.php)
     - [`VerifikasiPerusahaanProdiController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/AdminProdi/VerifikasiPerusahaan/VerifikasiPerusahaanProdiController.php)
     - [`VerifikasiPerusahaanFakultasController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/AdminFakultas/VerifikasiPerusahaan/VerifikasiPerusahaanFakultasController.php)
     Hal ini mengatasi error 404 ketika administrator melakukan *Auto Replace*, *Edit*, atau verifikasi ulang pada data perusahaan yang berstatus *Terverifikasi*.
   - **Penyempurnaan Frontend Vue**: Menghapus blokade tampilan pada `SuperAdmin/Perusahaan/Index.vue`, `AdminProdi/Perusahaan/Index.vue`, dan `AdminFakultas/Perusahaan/Index.vue` sehingga aksi (*Detail*, *Sunting*, *Auto Replace*, *Tolak/Hapus*) tetap tersedia dan aktif untuk seluruh perusahaan baik pending maupun terverifikasi.

2. **Perbaikan Tampilan & Pre-select Fakultas pada Manajemen Akun SuperAdmin**:
   - **Penyesuaian Controller (`ManajemenAkunController.php`)**: Menambahkan eager loading `prodi.fakultas` pada query pengguna dan memperbaiki filter pencarian fakultas alumni berbasis `prodi.fakultas_id`.
   - **Penyempurnaan Modal Edit User (`SuperAdmin/ManajemenAkun/Index.vue`)**:
     - Memperbaiki komputasi `currentFakultasId = u.fakultas_id || u.prodi?.fakultas_id || u.prodi?.fakultas?.id` agar dropdown Fakultas terisi (*pre-selected*) secara akurat saat modal **Sunting Data Akun Pengguna** dibuka.
     - Menambahkan event listener `didOpen` yang secara otomatis menyesuaikan pilihan dropdown Fakultas ketika pilihan Program Studi diubah.

---

## [2026-10-03] Sentralisasi Data CSV (/data), Robust Seeder Negara & Wilayah, Pembersihan File Usang, dan Otomasi Lulusan 2026

### Ringkasan Pembaruan
1. **Sentralisasi Berkas Master Data CSV ke Direktori `/data`**:
   - Seluruh berkas CSV referensi yang sebelumnya tercecer di root repositori kini dipusatkan ke direktori `data/`:
     - [`data/daftar_negara_dunia.csv`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/data/daftar_negara_dunia.csv): Master 194 negara dunia, ibu kota, kode ISO 2, dan benua.
     - [`data/provinsi.csv`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/data/provinsi.csv): Master 38 provinsi di Indonesia.
     - [`data/kabupaten_kota.csv`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/data/kabupaten_kota.csv): Master 514 kabupaten/kota di Indonesia.
   - Memperbarui path resolusi pada [`WilayahSeeder.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/database/seeders/WilayahSeeder.php) dan [`RefNegaraSeeder.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/database/seeders/RefNegaraSeeder.php) menggunakan prioritas `base_path('data/*.csv')` dengan graceful fallback bertingkat.

2. **Perbaikan & Peningkatan Robustness [`RefNegaraSeeder.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/database/seeders/RefNegaraSeeder.php)**:
   - **Perbaikan Format Baris 147 CSV**: Memperbaiki tanda kutip berlebih pada baris Amerika Serikat (`"Amerika Serikat,""Washington, D.C."",US,Amerika Utara"`) menjadi `Amerika Serikat,"Washington, D.C.",US,Amerika Utara`. Sebelumnya, kesalahan format ini menyebabkan baris diparsing sebagai satu kolom tunggal sehingga `kode_iso2` bernilai null dan gagal disimpan karena constraint `NOT NULL` & `UNIQUE`.
   - **Pencegahan Konflik Kunci Unik (*Unique Constraint Conflict*)**: Menghindari SQLSTATE Duplicate Entry pada `kode_iso2` atau `nama_negara` dengan melakukan lookup ganda `RefNegara::where('kode_iso2', $kodeIso2)->orWhere('nama_negara', $namaNegara)->first()` sebelum operasi create/update.
   - **Stream Parsing `fgetcsv` & Proteksi Skema**: Mengganti pemanggilan `file()` dengan stream `fopen()` / `fgetcsv()` yang lebih hemat memori dan tahan terhadap variasi newline. Dilengkapi proteksi `Schema::hasTable('ref_negara')` agar seeder tidak *crash* jika tabel belum dimigrasi.

3. **Pembersihan File Usang (*Dead Code Removal*)**:
   - Menghapus service scraper lama `app/Services/LinkedIn/LinkedInService.php` dan skrip python client `scripts/linkedin_mcp_client.py` yang sebelumnya mengandalkan scraping browser lokal.
   - Menyelaraskan seluruh pemanggilan controller di Biro 3 (`SinkronisasiLinkedinController.php`) dan SuperAdmin ([`LinkedInSyncController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/Shared/LinkedInSyncController.php)) agar menggunakan service produksi resmi [`LinkedInProfileService.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Services/LinkedIn/LinkedInProfileService.php).
   - Menegaskan kebijakan **Zero File Deletion** untuk modul arsitektur LinkedIn: DTOs, Contracts, Exceptions, Mappers, dan Providers tidak perlu dihapus saat integrasi API resmi LinkedIn diaktifkan.

4. **Perbaikan Migrasi Basis Data & Kompatibilitas SQLite Test Suite**:
   - Menambahkan guard *early return* pada migrasi [`2026_09_30_125458_add_created_by_user_id_to_perusahaan_table.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/database/migrations/2026_09_30_125458_add_created_by_user_id_to_perusahaan_table.php) saat kolom `created_by_user_id` dan `created_by_prodi_id` sudah ada, mencegah SQLite melakukan table rebuilding (`alter table __temp__perusahaan rename to perusahaan`) yang bentrok dengan *database view* `v_alumni_kuesioner_autofill`.
   - Mengalihkan trait `DatabaseMigrations` menjadi `RefreshDatabase` pada [`BiroTigaDashboardTest.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/tests/Feature/BiroTigaDashboardTest.php) dan [`BiroTigaKelolaPertanyaanTest.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/tests/Feature/BiroTigaKelolaPertanyaanTest.php).
   - Seluruh 94 skenario pengujian PHPUnit (`vendor/bin/phpunit`) lulus 100% (628 assertions).

5. **Penyempurnaan Seeder & Otomasi Alumni Lulusan 2026**:
   - Memperbaiki sintaks array pada [`BiodataSeeder.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/database/seeders/BiodataSeeder.php).
   - Mendaftarkan [`Lulusan2026Seeder.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/database/seeders/Lulusan2026Seeder.php) ke dalam master seeder [`DatabaseSeeder.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/database/seeders/DatabaseSeeder.php).
   - Seluruh 19 seeder database sukses dieksekusi secara berurutan tanpa error (`php artisan db:seed` exit code 0).

---

## [2026-10-03] Fitur Sinkronisasi Profil LinkedIn SuperAdmin & Refaktorisasi Arsitektur Driver-Based Resolution via .ENV

### Ringkasan Pembaruan
1. **Fitur Sinkronisasi LinkedIn SuperAdmin**:
   - Dibuat halaman Superadmin khusus **Sinkronisasi LinkedIn** di [`Index.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/SuperAdmin/LinkedIn/Index.vue) dengan filter Tahun Kelulusan resmi (tanpa membuat field/tabel baru, bersumber dari relasi `Yudisium` dan `DataAkademik`).
   - Menyediakan 5 kartu ringkasan metrik analitik: *Total Alumni*, *Dengan LinkedIn*, *Berhasil Sinkron*, *Gagal*, dan *Dilewati*.
   - Aksi sinkronisasi individual asinkron tanpa full page reload dengan visual state reaktif (*Belum Disinkronkan* -> *Menyinkronkan...* -> *Berhasil* / *Gagal*).
   - Aksi sinkronisasi massal (*Bulk Sync*) per angkatan tahun kelulusan dengan bilah progres live dan dialog modal ringkasan akhir.
   - Dibuat Controller [`LinkedInSyncController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/Shared/LinkedInSyncController.php) dan rute `/superadmin/linkedin-sync` di [`web.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/routes/web.php).
   - Ditambahkan menu navigasi *Sinkronisasi LinkedIn* di Sidebar Superadmin ([`Sidebar.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/SuperAdmin/Components/Sidebar.vue)).

2. **Arsitektur Driver-Based Resolution (Switch via .ENV)**:
   - Merefaktor penyedia data sinkronisasi LinkedIn dari penunjukan langsung ke pola abstraksi Driver-Based Resolution terpusat di [`AppServiceProvider.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Providers/AppServiceProvider.php).
   - Menghubungkan interface tunggal [`LinkedInProfileProvider.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Services/LinkedIn/Contracts/LinkedInProfileProvider.php) ke driver aktif berdasarkan variabel lingkungan `LINKEDIN_DRIVER`:
     - `LINKEDIN_DRIVER=mock` -> [`MockLinkedInProvider.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Services/LinkedIn/Providers/MockLinkedInProvider.php) (membaca berkas JSON lokal di `storage/app/mock/linkedin/johndoe.json` atau fallback dinamis berbasis biodata lokal).
     - `LINKEDIN_DRIVER=api` -> [`ApiLinkedInProvider.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Services/LinkedIn/Providers/ApiLinkedInProvider.php) (disiapkan untuk future server-to-server official API menggunakan API Key).
   - Didaftarkan konfigurasi environment di [`config/linkedin.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/config/linkedin.php), [`config/services.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/config/services.php), `.env`, dan [`.env.example`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/.env.example).
   - Business logic ([`LinkedInProfileService.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Services/LinkedIn/LinkedInProfileService.php), [`LinkedInProfileMapper.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Services/LinkedIn/Mappers/LinkedInProfileMapper.php), controller, DTO, mapper, database, dan frontend) murni agnostik dan **TIDAK PERLU DIUBAH** saat beralih provider.

3. **Integritas Data, Kebijakan Verifikasi Perusahaan, & Audit Trail**:
   - Menerapkan 4 aturan penentuan posisi aktif (*Current Position*): prioritas posisi dengan `endMonthYear == null`, start date terbaru, dan riwayat historis terbaru.
   - **Kebijakan Verifikasi Mutlak**: Setiap institusi perusahaan baru yang dibuat dari sinkronisasi LinkedIn SELALU disetel berstatus `status_verifikasi = 'Menunggu Verifikasi'` meskipun seluruh data terisi lengkap.
   - Menjaga data otoritatif: NIM, NIK, NPWP, nomor telepon, email pribadi, alamat rumah, dan data akademik tidak pernah ditimpa.
   - Isolasi transaksi basis data per-alumni: kegagalan satu alumni tidak menggagalkan alumni lain dalam satu batch.
   - Jejak audit trail dicatat otomatis via [`LogActivity::record()`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Models/LogActivity.php) dengan action `'linkedin_sync'` tanpa membocorkan kredensial.
   - Ditambahkan relasi `latestLinkedinSyncLog()` pada model [`Biodata.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Models/Biodata.php) menggunakan `latestOfMany()`.

4. **Pengujian Komprehensif (17 Test Skenario)**:
   - Dibuat suite pengujian fitur [`SuperAdminLinkedInSyncTest.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/tests/Feature/SuperAdminLinkedInSyncTest.php) mencakup skenario Mock JSON, 404 Profil Tidak Ditemukan, Empty Username SKIPPED, Driver API Switch, Proteksi API Key, dan Bulk Sync.

---

## [2026-09-30] Fitur Otomatis Reset Password via Email, Master Data Perusahaan Terpadu, & Tabel Audit Trail Log Aktivitas SuperAdmin

### Ringkasan Pembaruan
1. **Fitur Otomatis Reset Password via Gmail / SMTP**:
   - Integrasi pengiriman email pemulihan akun secara otomatis berbasis SMTP (Google App Passwords / Sandi Aplikasi 16 digit).
   - Dibuat Mailable [`ResetPasswordMail.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Mail/ResetPasswordMail.php) dengan template HTML e-mail bermerek khas UKDW ([`reset_password.blade.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/views/emails/reset_password.blade.php)).
   - Dibuat Controller [`LupaKataSandiController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/Otentikasi/LupaKataSandiController.php) untuk mengelola token acak (expired 60 menit) di tabel `password_reset_tokens`, pengiriman email, serta pemrosesan ganti password baru.
   - Halaman Modal **Lupa Kata Sandi** di [`Login.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Otentikasi/Login.vue) diubah menjadi form interaktif yang menerima NIM / Username atau Email terdaftar.
   - Halaman **Reset Password Baru** disajikan dengan Inertia Vue ([`ResetPassword.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Otentikasi/ResetPassword.vue)).
   - **Logika Prioritas Email**: Mengutamakan `email_pribadi` pada tabel `biodata` (email aktif terkini alumni) sebelum `users.email`.

2. **Master Data & Verifikasi Perusahaan Terpadu (SuperAdmin, Admin Fakultas, & Admin Prodi)**:
   - Menyatukan tampilan antarmuka **Master Data Perusahaan** pada 3 tingkatan peran admin (SuperAdmin, Admin Fakultas, dan Admin Prodi) di bawah kategori sidebar **Master Data & Verifikasi**.
   - Disediakan **Tab Navigasi Filter Status**:
     - *Semua Perusahaan* (Menampilkan seluruh data perusahaan).
     - *Menunggu Verifikasi* (Daftar pengajuan baru alumni).
     - *Master Terverifikasi* (Daftar institusi/perusahaan resmi terverifikasi).
   - Diaktifkan algoritma pendeteksi rekomendasi kemiripan nama (*fuzzy matching & token overlap*) untuk mempermudah konsolidasi dan penggabungan (*merge*) data perusahaan yang berpotensi ganda.
   - Dibuat migrasi [`2026_09_30_125458_add_created_by_user_id_to_perusahaan_table.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/database/migrations/2026_09_30_125458_add_created_by_user_id_to_perusahaan_table.php) untuk menjamin keberadaan kolom `created_by_user_id` dan `created_by_prodi_id` pada tabel `perusahaan`.

3. **Halaman Audit Trail / Tabel Log Aktivitas SuperAdmin**:
   - Dibuat Controller [`LogAktivitasController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/SuperAdmin/LogAktivitasController.php) dan rute `/superadmin/logs` (`superadmin.logs.index`).
   - Dibuat antarmuka Vue [`Index.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/SuperAdmin/Logs/Index.vue) untuk menyajikan tabel log audit aktivitas sistem:
     - Widget Statistik (Total Log, Total Aktivitas Hari Ini).
     - Bilah Pencarian Realtime (Deskripsi, Username, Pelaku, IP Address).
     - Filter Berdasarkan Jenis Aksi (`VERIFY_COMPANY`, `REPLACE_COMPANY`, `RESET_PASSWORD`, `UPDATE_PROFILE`, dll).
     - Modal Detail JSON (*Old Values* & *New Values*) menggunakan SweetAlert2.
   - Ditambahkan menu **Log Aktivitas** di Sidebar SuperAdmin.

---

## [2026-09-30] Pemisahan Pertanyaan Profil dari Kuesioner Universitas & Penguncian Permanen Master Data Akademik di Seluruh Role

### Ringkasan Pembaruan
1. **Pemisahan Butir Pertanyaan Profil dari Alur Kuesioner Universitas**:
   - Menghapus redundansi butir pertanyaan Identitas & Biodata Mahasiswa (Seksi 1 / Kelompok `'1'`, memuat 26 pertanyaan seperti NIM, Nama Lengkap, No HP, Email, Alamat, Tempat & Tanggal Lahir, JK, Tanggal Lulus, NIK, NPWP, Karier/Perusahaan, Atasan, dst.) dari alur Kuesioner Universitas.
   - Kolom `tampil_di` pada tabel `ref_subpertanyaan2021` diset menjadi:
     - `'profil'` untuk seluruh pertanyaan Seksi 1 / Identitas & Biodata.
     - `'kuesioner'` untuk pertanyaan Seksi 2 sampai 9 (Status Pekerjaan, Transisi Dunia Kerja, Studi Lanjut, Kompetensi, Fasilitas Kampus, dll.).
   - Controller Kuesioner ([`KuesionerController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/Alumni/Kuesioner/KuesionerController.php)) dan Controller Detail Alumni pada seluruh role admin ([`DetailAlumniSuperAdminController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/SuperAdmin/KelolaAlumni/DetailAlumniSuperAdminController.php), [`DaftarAlumniProdiController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/AdminProdi/KelolaAlumni/DaftarAlumniProdiController.php), [`DetailAlumniFakultasController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/AdminFakultas/KelolaAlumni/DetailAlumniFakultasController.php), [`DetailAlumniController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/AdminBiroTiga/KelolaAlumni/DetailAlumniController.php)) secara otomatis mengabaikan Seksi 1 dan menyaring pertanyaan berlabel `tampil_di = 'profil'`.
   - Alur Kuesioner Universitas kini langsung dimulai dari Seksi 2 ("Status Pekerjaan & Aktivitas Saat Ini"), menghindari pertanyaan dobel yang membingungkan alumni.

2. **Penguncian Permanen (Read-Only / Disabled) Master Data Akademik di Seluruh Role**:
   - Data identitas kependudukan dan histori perkuliahan yang bersumber murni dari Pangkalan Data Akademik ([`data_akademik`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Models/DataAkademik.php)) dikunci default tidak dapat diedit (*read-only*):
     - Kolom yang dikunci: **NIM**, **Nama Lengkap**, **Tempat Lahir**, **Tanggal Lahir**, **Jenis Kelamin**, **Agama**, **Golongan Darah**, **Kewarganegaraan**, **No. Kartu Keluarga (KK)**, **NISN**, **No. BPJS/Asuransi Kesehatan**, **NIK**, serta seluruh histori kelulusan & IPK.
   - Diterapkan secara seragam di frontend komponen [`FormPribadi.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/AlumniProfil/FormPribadi.vue) dan [`FormAkademik.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/AlumniProfil/FormAkademik.vue) dengan tampilan visual `lockedInputClass` (latar abu-abu netral, ikon gembok amber/slate, kursor tidak diizinkan, dan helper text resmi).
   - Penguncian berlaku menyeluruh di:
     - **Profil Alumni** (`/alumni/profil`)
     - **Detail Alumni SuperAdmin** (`/superadmin/alumni/{id}`)
     - **Detail Alumni Admin Fakultas** (`/fakultas/alumni/{id}`)
     - **Detail Alumni Admin Prodi** (`/prodi/alumni/{id}`)
     - **Detail Alumni Biro 3** (`/biro3/alumni/{id}`)
   - Proteksi ganda pada backend ([`SimpanProfilController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/Alumni/Profil/SimpanProfilController.php) & [`AdminAlumniProfileService.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Services/Alumni/AdminAlumniProfileService.php)): request perubahan dari form profil tidak akan memodifikasi kolom master identitas akademik, hanya `email_pribadi` yang disinkronisasikan.

---

## [2026-09-30] Refaktorisasi Skema Anti-Duplikasi Data, Migrasi Konsolidasi Bersih, Otomasi Kelulusan Yudisium, dan Sistem Audit Trail Log Activity

### Ringkasan Pembaruan
1. **Eliminasi Total Duplikasi Data antara Tabel `biodata` dan `data_akademik`**:
   - Menghapus seluruh kolom duplikasi pada tabel `biodata`:
     - Kolom identitas kependudukan & akademik (`nama`, `tempat_lahir`, `tanggal_lahir`, `jenis_kelamin`, `golongan_darah`, `agama`, `tahun_lulus`, `warga_negara`, `no_kk`, `nisn`, `no_bpjs`) kini tersimpan tunggal pada tabel master [`data_akademik`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Models/DataAkademik.php).
     - Kolom `data_akademik_id` dihapus dari tabel `biodata` karena relasi sudah terhubung secara kuat dan terindeks melalui nomor induk mahasiswa (`nim`).
     - Kolom `email` dan `email_students` dihapus dari tabel `biodata`. `email_students` hanya tersimpan di `data_akademik` sebagai data resmi kampus, sedangkan `biodata` hanya menyimpan `email_pribadi` untuk korespondensi aktif alumni.
   - Menyediakan getter accessor dinamis pada model [`Biodata.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Models/Biodata.php) (`getNamaAttribute`, `getEmailAttribute`, `getTempatLahirAttribute`, `getTanggalLahirAttribute`, `getAgamaAttribute`, dll.) yang secara otomatis mendelegasikan nilai dari `dataAkademik`, sehingga kode legasi tetap berjalan mulus tanpa error.

2. **Konsolidasi Migrasi Bersih (Direct Single Create, Tanpa Migrasi `add_`/`alter_`)**:
   - Menyatukan seluruh definisi struktur tabel ke migrasi awal `create_`:
     - [`create_data_akademiks_table.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/database/migrations/2026_09_01_171350_create_data_akademiks_table.php): Menyimpan identitas master, `email_students`, `email_pribadi`, dan `tanggal_lulus`.
     - [`create_yudisiums_table.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/database/migrations/2026_09_01_171353_create_yudisiums_table.php): Menghapus `tahun_akademik_lulus` dan `tahun_lulus`, menyederhanakan status kelulusan dengan kolom `status_lulus` (`['Belum', 'Proses', 'Lulus', 'Tidak Lulus']`).
     - [`create_biodatas_table.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/database/migrations/2026_09_01_171354_create_biodatas_table.php): Struktur bersih langsung memuat `foto`, `email_pribadi`, kontak, alamat terkini, dan data tracer karier tanpa satupun kolom duplikat.
     - [`create_companies_table.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/database/migrations/2026_09_01_171342_create_companies_table.php): Langsung memuat `created_by_user_id`, `created_by_prodi_id`, dan kolom teks berukuran 500.
     - [`create_alumni_audit_database_views.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/database/migrations/2026_09_20_234000_create_alumni_audit_database_views.php): Langsung memuat seluruh database views definitif (`v_alumni_profile_summary`, `v_alumni_tracer_univ_status`, `v_alumni_tracer_prodi_status`, `v_alumni_audit_rekap`).
   - Menghapus 6 file migrasi `add_` / `alter_` yang usang.

3. **Otomasi Keterkaitan Kelulusan Yudisium dengan Data Akademik**:
   - Pada model [`Yudisium.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Models/Yudisium.php), disematkan event listener Eloquent `saved`:
     - Saat `status_lulus` diset menjadi `'Lulus'`, sistem secara otomatis memperbarui record [`data_akademik`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Models/DataAkademik.php) yang berelasi:
       - Mengubah `status_mahasiswa` menjadi `'L'` (Lulus).
       - Mengisi `tanggal_lulus` (dari tanggal SK yudisium).
       - Mengisi `tahun_lulus` (tahun dari tanggal SK yudisium).
       - Menentukan `tahun_akademik_lulus` (semester Gasal/Genap sesuai periode tanggal SK yudisium).
     - Mengeliminasi duplikasi penginputan tahun kelulusan secara manual.

4. **Tabel & Sistem Audit Trail Log Activity ([`LogActivity.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Models/LogActivity.php) & [`create_log_activities_table.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/database/migrations/2026_09_01_171355_create_log_activities_table.php))**:
   - Dibuat tabel `log_activities` dan model [`LogActivity`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Models/LogActivity.php) dengan helper `LogActivity::record()`.
   - Mencatat seluruh jejak audit penting secara transparan:
     - Verifikasi, ACC, penggantian master (auto replace), dan penolakan (reject) perusahaan.
     - Pembaruan akun pengguna (perubahan email, username, nama).
     - Reset password instan dan pengiriman konfirmasi ke email pribadi.
     - Pembaruan profil biodata alumni baik oleh alumni mandiri maupun administrator.

5. **Sinkronisasi Presisi Email Pribadi ke Akun Pengguna**:
   - Seluruh perubahan email di Manajemen Akun (Superadmin, Prodi, Fakultas) maupun profil alumni secara otomatis memperbarui:
     - `users.email`
     - `biodata.email_pribadi`
     - `data_akademik.email_pribadi`
   - Memastikan saat pengguna mengubah email, email login dan email penerima reset password langsung tersinkronisasi tanpa ketergantungan pada email student kampus.

---
1. **Penyelarasan Widget Statistik Dual Box (Divider Vertikal Sesuai Foto)**:
   - Menyelaraskan widget statistik verifikasi pada halaman SuperAdmin, Admin Prodi, dan Admin Fakultas mengikuti foto desain pengguna:
     - Layout kotak bersih rounded-2xl dengan garis pemicu pemisah vertikal (`w-px h-10 bg-slate-300/80`).
     - Tampilan dua angka besar: **Menunggu Verifikasi** (`#0D542B` Hijau UKDW) dan **Master Terverifikasi** (`#D97706` Oranye UKDW).

2. **Modul Manajemen Akun Terpadu (`/superadmin/manajemen-akun`)**:
   - Dibuat [`ManajemenAkunController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/SuperAdmin/ManajemenAkunController.php) dan antarmuka Vue [`Pages/SuperAdmin/ManajemenAkun/Index.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/SuperAdmin/ManajemenAkun/Index.vue).
   - Pengelompokan dan penyaringan akun terpadu untuk:
     - **Admin Fakultas**
     - **Admin Prodi**
     - **Mahasiswa / Alumni per Prodi**

3. **Fitur Pemulihan & Reset Password ke Email Pribadi Terdaftar**:
   - **Kirim Email Pemulihan**: Mengirim instruksi dan link reset password ke alamat email terdaftar pengguna (`user.email`), yaitu email pribadi pengguna (bukan dipaksa email student).
   - **Reset Password Direct**: Layanan reset password instan oleh SuperAdmin untuk penanganan darurat akun pengguna.

4. **Pengelompokan (Categorization) Sidebar Navigasi**:
   - Sidebar pada role SuperAdmin, Admin Prodi, dan Admin Fakultas dikelompokkan secara terstruktur:
     - **`RINGKASAN & ANALISIS`**: Dashboard Utama
     - **`MANAJEMEN KUESIONER`**: Kelola Seksi, Kelola Butir Pertanyaan, Kuesioner Per Prodi
     - **`MASTER DATA & VERIFIKASI`**: Verifikasi Perusahaan, Data Alumni, dan Manajemen Akun

5. **Eliminasi Total Emoticon & Diksi Formal Enterprise**:
   - Menghapus seluruh titik indikator `rounded-full status dot` dan emoticon dari seluruh tampilan.
   - Menggunakan diksi formal corporate Indonesia (`Sunting Data`, `Verifikasi Sesuai Inputan`, `Tutup`).

---

## [2026-09-30] Fitur ACC Perusahaan Superadmin (Scope All & Per Prodi) dan Pengaturan Kuesioner Program Studi oleh Superadmin

### Ringkasan Pembaruan
1. **Verifikasi & Approval Perusahaan Alumni untuk Superadmin ([`VerifikasiPerusahaanSuperAdminController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/SuperAdmin/Perusahaan/VerifikasiPerusahaanSuperAdminController.php))**:
   - **Superadmin ACC Perusahaan (`/superadmin/perusahaan`)**: Menyediakan modul khusus bagi Superadmin (Otoritas Tertinggi) untuk melakukan ACC/verifikasi pengajuan tempat kerja alumni secara menyeluruh (All) maupun terfilter berdasarkan Program Studi tertentu.
   - **Fitur Lengkap Verification Service**: Mendukung Verifikasi Langsung (ACC Direct), Penggantian Master Perusahaan Otomatis (Auto Replace dengan deteksi rekomendasi kemiripan string), Sunting & Verifikasi Atribut Perusahaan, serta Penolakan (Reject) Pengajuan.
   - **Interface Vue Superadmin ([`Index.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/SuperAdmin/Perusahaan/Index.vue))**: Halaman profesional dengan palet warna resmi hijau UKDW (`#0D542B`), filter dropdown Prodi, filter cakupan pengajuan, pencarian teks, dan dialog SweetAlert2 untuk konfirmasi dan modal penggantian master.

2. **Pengaturan Kuesioner Program Studi oleh Superadmin ([`KelolaKuesionerProdiSuperAdminController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/SuperAdmin/KelolaPertanyaan/KelolaKuesionerProdiSuperAdminController.php))**:
   - **Pengaturan Kuesioner Prodi (`/superadmin/prodi-kuesioner`)**: Memungkinkan Superadmin memilih Program Studi target dari dropdown selector, lalu melihat dan mengelola daftar bagian (section), butir pertanyaan, dan opsi jawaban khusus untuk prodi tersebut.
   - **Tampilan & UX Identik dengan Role Admin Prodi**: Menyajikan tabulasi *Daftar Bagian (Section)* dan *Daftar Pertanyaan (Kuesioner)* dengan layout, style, dan modal SweetAlert2 yang disamakan persis dengan role Admin Prodi ([`AdminProdi/Section/Index.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/AdminProdi/Section/Index.vue) dan [`AdminProdi/Pertanyaan/Index.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/AdminProdi/Pertanyaan/Index.vue)).
   - **Eliminasi Total Emoticon & Diksi Profesional**: Menggantikan seluruh emoticon dengan ikon SVG resmi dan diksi bahasa formal korporat enterprise.
   - **Fitur Manajerial Penuh**: Superadmin dapat membuat, mengedit, menghapus, dan mengatur urutan (reorder up/down) section prodi, pertanyaan prodi, serta opsi jawaban prodi lengkap dengan konfigurasi alur percabangan (*jump_to*).

3. **Penyelarasan Tampilan Peninjauan Perusahaan Superadmin ([`SuperAdmin/Perusahaan/Index.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/SuperAdmin/Perusahaan/Index.vue))**:
   - **Presisi Modal Peninjauan Pengajuan**: Menyelaraskan struktur modal *Peninjauan Pengajuan Perusahaan* (`openReviewModal`) dengan halaman [`http://localhost:8000/prodi/perusahaan`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/AdminProdi/Perusahaan/Index.vue).
   - **Komponen Peninjauan**: Menampilkan blok *1. DATA YANG DIINPUTKAN ALUMNI*, *2. REKOMENDASI MASTER TERVERIFIKASI (AUTO REPLACE)*, serta tombol aksi *Verifikasi Sesuai Inputan*, *Edit Dulu*, dan *Tutup* secara rapi tanpa emoticon.

4. **Pemasangan Skill Web Design Engineer (`.agents/skills/web-design-engineer/SKILL.md`)**:
   - Memasang dan mengonfigurasi skill `web-design-engineer` dari repositori `garden-skills` untuk standar visual UI/UX web enterprise.

5. **Pembaruan Navigasi & Dashboard Utama Superadmin ([`Sidebar.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/SuperAdmin/Components/Sidebar.vue) & [`Dashboard.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/SuperAdmin/Dashboard.vue))**:
   - Menambahkan menu **Kuesioner Per Prodi** (`/superadmin/prodi-kuesioner`) dan **ACC Perusahaan** (`/superadmin/perusahaan`) pada Sidebar Navigasi Superadmin.
   - Menyematkan kartu metrik KPI dan quick access card untuk *Pengaturan Kuesioner Per Prodi* dan *Verifikasi Perusahaan* pada Dashboard Utama Superadmin (`/superadmin/dashboard`).

6. **Relasi Model & Pengujian Otomatis**:
   - Menambahkan metode relasi `prodiQuestions()` dan `prodiQuestionSections()` pada model [`Prodi.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Models/Prodi.php).
   - Menulis pengujian otomatis komprehensif pada [`SuperAdminPerusahaanDanKuesionerProdiTest.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/tests/Feature/SuperAdminPerusahaanDanKuesionerProdiTest.php) (100% PASS: 4 tes, 9 assertions).

---

## [2026-09-28] Migrasi Foto Profil Biodata, Integrasi Eager Loading Yudisium & Link Publikasi, Agregasi Spasial Beranda, Modularisasi Komponen Landing Page, Navbar iOS Glossy, dan Presisi Desain Login

### Ringkasan Pembaruan
1. **Penyesuaian Skema Warna Per Section Landing Page**:
   - **Section Sambutan WR III ([`tentang-section.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/Landing/tentang-section.vue))**: Background hijau botol UKDW pekat (`bg-gradient-to-b from-[#00381A] via-[#004D25] to-[#003318]`) dengan kartu kaca frosted dan aksen emas.
   - **Section Alur Partisipasi ([`user-guide-section.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/Landing/user-guide-section.vue))**: Background kuning hangat UKDW (`bg-gradient-to-b from-[#FFC700] via-[#FBBF24] to-[#F59E0B]`) berpadu kartu kontras tinggi warna putih mutiara (`bg-white/90`) dan badge langkah hijau pekat (`bg-[#004D25] text-[#FFC700]`).
   - **Eliminasi Total Warna Biru di Seluruh Landing Page**: Menghilangkan seluruh aksen `blue` dan `cyan` pada badge verified, tombol interaktif, dan tautan eksternal, digantikan dengan identitas resmi hijau UKDW (`#004D25`) dan emas cerah (`#FFC700`).

2. **Bilah Navigasi (Navbar) Tema iOS Glossy & Bening Dinamis ([`navbar.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/Landing/Navbar.vue))**:
   - Mendeteksi pergerakan scroll layar secara real-time (`isScrolled`).
   - Saat berada di posisi paling atas: latar semi-transparan hijau UKDW halus (`bg-[#004D25]/80 backdrop-blur-md`).
   - Saat di-scroll: otomatis bertransisi menjadi frosted glass bening transparan khas iOS (`bg-[#002f17]/40 backdrop-blur-2xl backdrop-saturate-200 border-b border-white/20 shadow-[0_8px_32px_0_rgba(0,0,0,0.35)]`).
   - Tombol menu dan link navigasi menggunakan kapsul pill iOS yang halus.

3. **Geometri Tegas Non-Tumpul Halaman Login & Ubah Sandi (Bebas dari AI Template Look)**:
   - Merapikan [`Login.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Otentikasi/Login.vue) dan [`UbahKataSandi.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Otentikasi/UbahKataSandi.vue) dengan menghilangkan sudut bulat tumpul berlebihan (`rounded-[2.5rem]`, `rounded-full`, `rounded-3xl`).
   - Menerapkan sudut kotak tajam dan proporsional enterprise-grade (`rounded-xl`, `rounded-lg`, `rounded-md`) pada kartu kontainer, input formulir, tombol submit, serta dialog pop-up Lupa Kata Sandi.

4. **Autentikasi Alumni Berbasis Tanggal Lahir & Siklus Ubah Sandi Wajib ([`LoginController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/Otentikasi/LoginController.php))**:
   - Mendukung login alumni menggunakan kata sandi bawaan (default password) format tanggal lahir `ddmmyyyy` (contoh: `15082001` untuk kelahiran 15 Agustus 2001) yang tersinkronisasi otomatis dari `data_akademik`.
   - Mengarahkan akun dengan kata sandi bawaan secara wajib ke halaman ganti sandi awal (`/change-password` via `must_change_password = true`).
   - Setelah alumni menyimpan kata sandi baru, flag `must_change_password` kembali menjadi `false`, sehingga pada sesi login berikutnya alumni langsung masuk ke dashboard seperti biasa.
   - Dilengkapi pengujian otomatis komprehensif pada [`AlumniLoginBirthDateTest.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/tests/Feature/AlumniLoginBirthDateTest.php).

5. **Migrasi Basis Data Kolom Foto Alumni (`add_foto_to_biodata_table`)**:
   - Menambahkan migrasi `2026_09_28_054830_add_foto_to_biodata_table.php` untuk menyematkan kolom `foto` (`string`, nullable) pada tabel `biodata` yang merujuk ke path file foto profil alumni di `public/uploads/profile/`.
   - Mendaftarkan kolom `foto` ke dalam properti `$fillable` pada model [`Biodata.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Models/Biodata.php).

2. **Refaktor & Ketegasan Relasi Yudisium ([`Biodata.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Models/Biodata.php))**:
   - Memperbarui relasi `yudisium()` pada model `Biodata` menjadi `belongsTo(Yudisium::class, 'yudisium_id')` secara eksplisit guna menghubungkan status kelulusan, judul tugas akhir/skripsi (`judul_ta`), URL repositori karya ilmiah (`url_publikasi`), dan tipe publikasi (`jenis_publikasi`).

3. **Optimasi & Agregasi Spasial Backend Beranda ([`BerandaController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/Tamu/BerandaController.php))**:
   - Mengintegrasikan atribut `foto` dan eager loading relasi `yudisium` pada kueri `alumniList` sehingga landing page dapat menampilkan foto asli alumni beserta judul skripsi dan tautan publikasi repositori kampus.
   - Menghitung agregasi sebaran geografis per provinsi (`alumniWilayah`: domisili, lokasi kantor/perusahaan, dan ringkasan provinsi) yang menyajikan daftar nama alumni dan daftar nama institusi/perusahaan untuk hover tooltip peta interaktif.
   - Menyediakan data master referensi wilayah lengkap (`allProvinsi` dan `allKabupaten`) untuk mendukung filter interaktif kabupaten/kota secara real-time.

4. **Modularisasi Menyeluruh Komponen Landing Page (`resources/js/components/landing/`)**:
   - Memecah tampilan beranda publik menjadi 10 komponen modular yang terisolasi dan mudah dipelihara:
     - [`hero-section.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/Landing/hero-section.vue): Hero banner visual dengan video background UKDW bebas takarir (eliminasi takarir YouTube via CSS viewport translation dan dark bottom gradient masking), tombol CTA, dan maskot `hallo-alumni.png`.
     - [`alumni-map-section.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/Landing/alumni-map-section.vue): Peta interaktif Leaflet + OpenStreetMap 38 provinsi dengan layout 2-kolom sejajar (peta di kiri, 4 kartu alumni per halaman dalam grid 2x2 di kanan), filter multi-dimensi, hover tooltip informatif (menampilkan nama PT dan nama alumni), serta modal detail lengkap SweetAlert2 dengan foto profil, judul skripsi, link publikasi, dan tombol sorot di peta.
     - `testimonial-slider-section.vue`: Etalase "Karya & Tugas Akhir Alumni UKDW" yang menampilkan judul skripsi, penulis, instansi kerja saat ini, jenis publikasi, dan tautan repositori dokumen karya ilmiah.
     - [`stats-transformation-section.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/Landing/stats-transformation-section.vue): Statistik capaian transformasi kurikulum dan serapan lulusan berpadu maskot `pigo-update-data.png`.
     - `sektor-alumni-section.vue`: Etalase kategori industri dan karier alumni (Teknologi & IT, Pendidikan & Riset, Bisnis & Publik).
     - `career-pillars-section.vue`: Tiga pilar keilmuan UKDW dengan ilustrasi maskot Pigo (`anak-it.png`, `pigo-dokter.png`, `pigo-laptop.png`).
     - [`user-guide-section.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/Landing/user-guide-section.vue): Panduan 4 langkah mudah alur pengisian kuesioner tracer study bagi alumni pemula.
     - [`berkas-section.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/Landing/berkas-section.vue), [`blog-section.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/Landing/blog-section.vue), dan [`tentang-section.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/Landing/tentang-section.vue).

5. **Standardisasi Maskot Pigo & Aset Visual Branding**:
   - Memindahkan seluruh varian maskot ke direktori resmi `public/uploads/pigo/` (`loading-pigo.png`, `pigo-laptop.png`, `pigo-update-data.png`, `hallo-alumni.png`, `anak-it.png`, `pigo-dokter.png`).
   - Menyediakan direktori penyimpanan foto profil alumni di `public/uploads/profile/`.
   - Mengintegrasikan komponen loader visual global [`pigo-loader.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/ui/pigo-loader.vue) dan menyelaraskan logo resmi UKDW pada bilah navigasi seluruh stakeholder.

## [2026-09-26] Layout 2 Kolom (Peta Kiri & Alumni Kanan 2x2), Hover Peta Detail PT & Nama, Detail SweetAlert2 dengan TA & Link, Eliminasi Total Caption Video, dan Showcase Karya Alumni

### Ringkasan Pembaruan
1. **Eliminasi Total Takarir / Caption Video YouTube (`hero-section.vue`)**:
   - Memindahkan koordinat video canvas (`-translate-y-[36%]` dan `scale-115`) sehingga area bawah tempat takarir/subtitle YouTube muncul secara fisik terpotong ke luar batas viewport *overflow-hidden*.
   - Menambahkan lapisan masking gradasi gelap bawah (`h-44 bg-gradient-to-t from-slate-950 via-slate-950/80`) untuk memastikan teks subtitle YouTube tertutup 100% dan tidak pernah tampak di bawah kartu hero.

2. **Tata Letak 2 Kolom Sejajar (Filter Atas, Peta Kiri & Alumni Kanan) (`alumni-map-section.vue`)**:
   - **Filter Atas Terpadu**: Input pencarian multi-dimensi (*nama, jabatan, perusahaan, kode pos...*), dropdown *Semua Kab/Kota*, dan tombol *Lihat Semua*.
   - **Kolom Kiri (OpenStreetMap Leaflet)**: Menampilkan peta interaktif 38 provinsi berukuran proporsional yang tersinkronisasi langsung.
   - **Kolom Kanan (Daftar Alumni)**: Menampilkan daftar alumni langsung di sisi kanan peta dalam tata letak **2 baris x 2 kolom (4 alumni per halaman)** lengkap dengan navigasi paginasi ringkas (*Prev / Next*).

3. **Hover Peta Super Informatif (Menampilkan Nama PT & Nama Alumni)**:
   - Tooltip Leaflet saat kursor diarahkan ke suatu provinsi kini menampilkan data riil yang sangat berguna:
     - Nama Provinsi & Total Alumni
     - **Daftar Perusahaan / PT**: Menampilkan nama-nama instansi tempat alumni bekerja di provinsi tersebut (misal: PT Niagahoster, PT Gameloft, BCA, Astra).
     - **Daftar Nama Alumni**: Menampilkan nama-nama alumni yang berkiprah di wilayah tersebut.
   - Mengeklik provinsi di peta akan langsung memfilter daftar alumni di kolom kanan secara instan.

4. **Penyederhanaan Kartu Alumni (Nilai Langsung Tanpa Label Repetitif)**:
   - Menghilangkan label teks "Jabatan" dan "Perusahaan / Instansi" pada kartu; langsung menyajikan:
     - **Nama Alumni** & Tahun Kelulusan
     - **Posisi Kerja Langsung** (aksen hijau tua tebal)
     - **Nama Perusahaan Langsung** & Wilayah
     - **Judul Tugas Akhir (TA)** ringkas
     - **Tombol "Detail"** menuju pop-up SweetAlert2.

5. **Detail Lengkap Berbasis SweetAlert2**:
   - Mengganti modal kustom dengan dialog **SweetAlert2** modern:
     - Nama, NIM, Prodi, Tahun Kelulusan.
     - Posisi/Jabatan, Perusahaan, Sektor Usaha, Alamat Lengkap, Wilayah, & Kode Pos (Zipcode).
     - **Tugas Akhir / Skripsi**: Judul lengkap dan tautan aktif menuju dokumen repositori/jurnal (`url_publikasi`).
     - Riwayat Studi Lanjut (jika ada).
     - Tautan Media Sosial Aktif (LinkedIn, Instagram, Facebook).
     - Tombol aksi *"Sorot di Peta"* dan *"Tutup"*.

6. **Pembersihan Elemen Sesuai Arahan**:
   - Menghapus badge & tulisan "Alumni Terverifikasi UKDW".
   - Menghapus opsi "Domisili Alumni" (fokus murni pada karier & lokasi kerja/mitra).
   - Menghapus deretan tombol pilihan cepat.

7. **Penggantian Section "Satisfied Alumni Speaks" Menjadi "Karya & Tugas Akhir Alumni UKDW" (`testimonial-slider-section.vue`)**:
   - Mengganti seluruh slider testimoni lama dengan section berbobot akademis: **Karya & Tugas Akhir Alumni UKDW**.
   - Menampilkan inovasi skripsi mahasiswa, penulis, instansi kerja saat ini, jenis publikasi, dan tombol tautan langsung ke repositori dokumen karya ilmiah UKDW.

2. **Estetika Light Model & iPhone Glassmorphism (`alumni-map-section.vue`)**:
   - Menerapkan tema terang (*light mode*) bersih dengan estetika kaca iPhone (*iOS frosted glass*): `backdrop-blur-2xl`, latar putih transparan `bg-white/80`, garis batas halus `border-white/90`, dan bayangan lembut elevasi Apple `shadow-[0_12px_40px_rgba(0,0,0,0.06)]`.
   - Mengubah tampilan kontrol, segmented button (*Lokasi Kerja* vs *Domisili*), search bar, dan kartu alumni menjadi gaya widget iOS modern.

3. **Pemicu Interaktif Klik Peta (Alumni Muncul Saat Peta / Wilayah Diklik)**:
   - Menampilkan panduan interaktif Apple Glass di awal: *"Klik Wilayah pada Peta Interaktif"* dengan tombol akses cepat provinsi populer.
   - Ketika pengguna mengeklik suatu wilayah/provinsi di peta (atau memilih filter/pencarian), daftar alumni untuk daerah tersebut otomatis terbuka dan meluncur (*smooth scroll*) ke tampilan pengguna.

4. **Penyederhanaan Kartu Alumni Beranda (Nama, Jabatan, Perusahaan & Detail)**:
   - Kartu alumni dibuat sangat ringkas dan rapi, hanya berfokus pada 3 informasi inti yang dibutuhkan masyarakat:
     - **Nama Lengkap Alumni** & Program Studi
     - **Posisi / Jabatan Pekerjaan**
     - **Perusahaan / Instansi** & Kota Wilayah
     - **Tombol "Lihat Detail Lengkap"** bergaya iPhone Glass.
   - Menghilangkan tampilan foto alumni pada seluruh kartu sesuai arahan.

5. **Modal Rincian Data Lengkap Alumni (iPhone Frosted Glass Sheet)**:
   - Menekan tombol "Detail" membuka lembar dialog modal kaca (*iOS Frosted Glass Modal Sheet*) dengan efek blur sinematik `backdrop-blur-md` dan indikator *handle grabber* Apple di bagian atas.
   - Menyajikan informasi komprehensif: Nama, NIM, Prodi, Tahun Lulus, Posisi/Jabatan, Perusahaan Lengkap (Negara, Provinsi, Kab/Kota, Alamat Kantor, Kode Pos/Zipcode, Sektor), Riwayat Studi Lanjut (S2/S3, Kampus, Prodi), Domisili, serta Tautan Medsos Aktif (LinkedIn, Instagram, Facebook).
   - Dilengkapi tombol *"Sorot di Peta"* dan tombol *"Tutup"* dengan dukungan tombol keyboard `Escape`.

2. **Pembersihan Total Visual Neon Hijau Menyala**:
   - Menghapus semua efek visual neon, pendaran glow hijau menyala (*glowing green filter/drop-shadow*), dan watermark raksasa menyilaukan.
   - Menggantikannya dengan palet warna resmi dual-tone yang profesional: Hijau Hutan UKDW (`#0D542B`) dan Kuning Emas (`#FACC15` / `#EAB308`).
   - Menerapkan badge *Alumni Terverifikasi* halus dan elegan (`bg-[#ECFDF5] border-[#A7F3D0] text-[#065F46]`) dengan indikator titik hijau emerald solid.

3. **Direktori Alumni Instan & Mandiri**:
   - Menampilkan daftar kartu alumni terverifikasi secara langsung (*instant list*) tanpa mengharuskan pengguna membuka atau mengeklik peta terlebih dahulu.
   - Menyediakan navigasi paginasi (6 kartu per halaman) yang responsif dan cepat.
   - Menghilangkan foto profil alumni pada kartu sesuai arahan (*gausah nampilin foto*), digantikan dengan layout data karier yang rapi dan profesional.

4. **Pencarian Cerdas Wilayah, Provinsi, Kabupaten/Kota, & Kode Pos (Zipcode)**:
   - Input pencarian multi-dimensi: mencari instan berdasarkan Nama, NIM, Posisi/Jabatan, Perusahaan, Wilayah, Provinsi, Kabupaten/Kota, maupun Kode Pos (*Zipcode*).
   - Filter dropdown dinamis: Pilihan 38 Provinsi dan Kabupaten/Kota yang otomatis menyesuaikan (*cascading*).
   - Filter kategori karier: Bekerja, Wirausaha, dan Melanjutkan Pendidikan.

5. **Interaktivitas Dua Arah Peta Sebaran Leaflet.js**:
   - Peta Leaflet OpenStreetMap Indonesia (`[-2.55, 118.02]`, zoom 5) tersinkronisasi dua arah:
     - Mengeklik polygon provinsi di peta akan langsung memfilter daftar alumni di bawahnya dan memperbesar (*fitBounds*) ke provinsi tersebut.
     - Memilih filter provinsi atau mengeklik *"Lihat di Peta"* pada kartu alumni akan memfokuskan kamera peta secara otomatis ke wilayah terkait.

6. **Kelengkapan Informasi Alumni pada Kartu**:
   - **Nama Lengkap & NIM**: Identitas resmi alumni Duta Wacana.
   - **Program Studi & Tahun Lulus**: Informasi almamater.
   - **Posisi / Jabatan Pekerjaan**: Jabatan aktif di dunia industri.
   - **Perusahaan / Instansi**: Nama kantor, sektor bidang usaha, serta lokasi lengkap (Negara, Provinsi, Kabupaten/Kota, Alamat Kantor, dan Kode Pos).
   - **Riwayat Lanjut Pendidikan**: Jenjang (S2/S3), nama perguruan tinggi, dan program studi lanjutan.
   - **Tautan Media Sosial Profesional**: Akses langsung ke profil LinkedIn, Instagram, dan Facebook alumni.

---


### Ringkasan
1. **Penerapan Layout Kartu & Hero Ala Portal UB (Universitas Brawijaya)**:
   - Mengubah struktur hero section [`hero-section.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/Landing/hero-section.vue) menjadi layout persis Portal Tracer Study UB:
     - Background nuansa kampus dengan overlay hijau tua pekat (`#00381e` ke `#002213`).
     - Tipografi kontras: `PORTAL TRACER STUDY` (putih bold), `UNIVERSITAS KRISTEN DUTA WACANA` (kuning cerah `#FACC15`), dan kalimat penjelasan almamater.
     - Kolom pencarian terpadu (*"Cari apapun disini..."*) dengan tombol kotak kuning berikon kaca pembesar.
     - Tiga kartu bertumpuk (*overlapping cards*) di bagian bawah hero:
       - **Tracer Study**: Ditujukan kepada seluruh alumni UKDW dalam rentang waktu 1-2 tahun setelah lulus guna mengevaluasi proses pendidikan.
       - **Survey Pengguna Lulusan**: Ditujukan kepada seluruh instansi/perusahaan pengguna lulusan UKDW dalam mengukur mutu lulusan.
       - **Laporan Tracer Study**: Laporan hasil tracer study yang dilaporkan dan disosialisasikan secara berkala.
     - Setiap kartu dilengkapi garis tepi atas kuning cerah (*border-t-4 border-yellow-400*), latar hijau gelap semi-transparan (`#002B17`), dan tombol tautan `Selengkapnya →`.
   - Menambahkan tombol melayang bulat warna kuning (*floating scroll-to-top button*) di pojok kanan bawah persis seperti antarmuka Portal UB.

2. **Implementasi Peta Sebaran Alumni Interaktif (Leaflet.js & OpenStreetMap)**:
   - Membuat komponen peta interaktif [`alumni-map-section.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/Landing/alumni-map-section.vue):
     - Menggunakan *tile layer* resmi **OpenStreetMap** dengan titik pusat Indonesia di koordinat `[-2.55, 118.02]` dan zoom awal level 5 (sesuai spesifikasi).
     - Mengintegrasikan data batas wilayah **GeoJSON 38 Provinsi Indonesia** (`/geojson/indonesia-provinsi.json`) dan **518 Kabupaten/Kota** (`/geojson/indonesia-kabupaten.json`).
     - Fitur *choropleth* gradasi warna hijau khas UKDW (`#0D542B`), hijau emerald (`#15803D`), dan kuning emas (`#EAB308`) sesuai kepekatan data alumni riil.
     - Interaktivitas klik: Ketika pengguna mengeklik suatu provinsi, peta secara otomatis melakukan *zoom-in* halus (`fitBounds`) ke wilayah provinsi tersebut dan menampilkan batas wilayah kabupaten/kota di dalamnya.
     - Panel rincian samping dinamis: Menampilkan nama provinsi, total alumni, rincian per kabupaten/kota, serta tombol *Reset* ke seluruh Indonesia.
     - Toggle filter: Beralih antara *"Domisili Alumni"* dan *"Lokasi Kerja / Mitra Perusahaan"*.

3. **Penyelarasan Warna Hijau dan Kuning (Dual Tone Sesuai Referensi Gambar)**:
   - Menerapkan palet warna utama hijau botol/hutan khas UKDW (`#0D542B` / `#00381e`) dipadukan dengan kuning cerah/emas (`#FACC15` / `#EAB308`).
   - Menerapkan pill badge *Alumni Terverifikasi* (`bg-[#ECFDF5] border-[#A7F3D0] text-[#065F46]`) dengan indikator titik hijau berkedip di berbagai section.

## [2026-09-26] Redesain Landing Page Berbasis Wireframe, Integrasi Data Riil Database, dan Migrasi Aset Maskot Pigo

### Ringkasan
1. **Migrasi dan Standardisasi Aset Maskot Pigo & Logo UKDW**:
   - Memindahkan seluruh varian maskot resmi dari `/Pigo` ke dalam struktur direktori aplikasi:
     - `public/uploads/pigo/loading-pigo.png` (Maskot Pigo saat memuat)
     - `public/uploads/pigo/pigo-laptop.png` (Maskot Pigo membuka laptop)
     - `public/uploads/pigo/pigo-update-data.png` (Maskot Pigo memperbarui data)
     - `public/uploads/pigo/hallo-alumni.png` (Maskot Pigo menyapa alumni)
     - `public/uploads/pigo/anak-it.png` (Maskot Pigo bidang IT & Rekayasa)
     - `public/uploads/pigo/pigo-dokter.png` (Maskot Pigo bidang Kedokteran & Kesehatan)
     - `public/uploads/logo/logo-ukdw.png` (Logo resmi UKDW)
   - Memperbarui komponen `pigo-loader.vue` agar menggunakan `/uploads/pigo/loading-pigo.png`.
   - Mengintegrasikan kartu ilustrasi maskot Pigo pada halaman otentikasi [`Login.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Otentikasi/Login.vue) (`pigo-laptop.png`) dan [`UbahKataSandi.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Otentikasi/UbahKataSandi.vue) (`pigo-update-data.png`).
   - Memperbarui seluruh referensi logo bilah navigasi stakeholder (`SuperAdmin`, `AdminProdi`, `AdminFakultas`, `AdminBiroTiga`, dan `Alumni`) ke `/uploads/logo/logo-ukdw.png`.

2. **Penyajian Data Dinamis & Riil dari Database ([`BerandaController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/Tamu/BerandaController.php))**:
   - Menghubungkan landing page langsung dengan database produksi tanpa data dummy/slop:
     - `stats`: Menghitung total alumni (`120+`), total mitra perusahaan terverifikasi (`32+`), total prodi terintegrasi (`12`), total responden tracer (`88+`), dan persentase serapan karier (`94%`).
     - `featuredAlumni`: Mengambil data alumni riil beserta program studi, jabatan pekerjaan aktif, dan perusahaan/instansi terafiliasi dari tabel `biodata`, `prodi`, dan `perusahaan`.
     - `sektorKarier`: Mengelompokkan sebaran industri alumni (Teknologi & IT, Pendidikan & Riset, Bisnis & Publik).

3. **Redesain Menyeluruh Landing Page Sesuai Referensi Wireframe ([`Beranda.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Tamu/Beranda.vue))**:
   - **Hero Section**: Headline profesional, tombol aksi *[Mulai Isi Kuesioner]* & *[Pelajari Alur]*, serta kartu visual maskot `hallo-alumni.png` berpadu 3 highlight feature pills horizontal.
   - **Sektor Alumni Showcase**: Tab kategori (*Semua*, *Teknologi & IT*, *Pendidikan & Riset*) dengan kartu rekam jejak alumni riil dan navigasi geser panah kiri/kanan.
   - **User Guide for First Timer**: Penataan vertikal terstruktur 4 tahap pengisian kuesioner (Step 1 s/d Step 4) dengan ikon quote besar.
   - **Satisfied Alumni Speaks**: Slider/carousel interaktif dengan efek transisi *slide & fade show*, bintang ulasan, kutipan profesional, dan profil alumni terverifikasi.
   - **Key Statistics & Transformation Narrative**: Penataan angka metrik riil di sisi kiri, maskot `pigo-update-data.png` di tengah, dan capaian transformasi kurikulum di sisi kanan.
   - **Warta & Blog Section**: 4 kartu informasi kegiatan dan akreditasi kampus dengan tombol panah navigasi.
   - **Pilar Keilmuan (Services 01, 02, 03)**: Tiga kartu bidang utama: 01 Teknologi & Rekayasa (`anak-it.png`), 02 Kedokteran & Kesehatan (`pigo-dokter.png`), dan 03 Bisnis & Humaniora (`pigo-laptop.png`).
   - Efek transisi halus dan animasi *fade show* via GSAP ScrollTrigger.


### Ringkasan
1. **Penyempurnaan Fungsi Edit & Approval Perusahaan Menjadi Full SweetAlert2**:
   - Mengubah modal sunting data perusahaan (`openEditModal`) pada modul Admin Program Studi ([`AdminProdi/Perusahaan/Index.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/AdminProdi/Perusahaan/Index.vue)) dan Admin Fakultas ([`AdminFakultas/Perusahaan/Index.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/AdminFakultas/Perusahaan/Index.vue)) menjadi dialog SweetAlert2 interaktif, elegan, dan terintegrasi penuh.
   - Menyediakan form dinamis di dalam pop-up SweetAlert2: nama instansi, kategori skala instansi, jenis usaha, alamat lengkap, kode pos, serta keterkaitan dinamis provinsi dengan kabupaten/kota via event listener `change` pada elemen DOM dialog.
   - Validasi nama perusahaan wajib diisi (`preConfirm`), umpan balik instan, dan penanganan respon sukses/gagal secara asinkron tanpa reload halaman.
   - Menghapus modal floating kustom lama (`showEditModal` dan `showRecDetailModal`) sehingga antarmuka 100% konsisten menggunakan sistem notifikasi SweetAlert2 yang seragam.

2. **Penyelarasan Desain Status Badges Sesuai Panduan Visual**:
   - Mendesain ulang kartu ringkasan status di bagian header verifikasi perusahaan agar identik 1:1 dengan desain referensi:
     - Badge *Menunggu Verifikasi*: Latar krem lembut (`bg-[#FFFBEB]`), garis tepi halus (`border-[#FDE68A]`), teks cokelat hangat (`text-[#78350F]`), sudut tumpul (`rounded-2xl`), dan penanda titik oranye bulat (`bg-[#F59E0B]`).
     - Badge *Master Terverifikasi*: Latar mint lembut (`bg-[#ECFDF5]`), garis tepi mint (`border-[#A7F3D0]`), teks hijau tua (`text-[#065F46]`), dan sudut tumpul (`rounded-2xl`).

3. **Penerapan Apple Glossy Aesthetic pada Navigation Sidebar**:
   - Menerapkan bahasa desain *Apple Glassmorphism* (material tembus pandang dengan efek kabur `bg-white/85 backdrop-blur-2xl border-slate-200/70`) pada seluruh bilah navigasi stakeholder non-alumni:
     - Admin Program Studi: [`AdminProdi/Components/Sidebar.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/AdminProdi/Components/Sidebar.vue)
     - Admin Fakultas: [`AdminFakultas/Components/Sidebar.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/AdminFakultas/Components/Sidebar.vue)
     - Super Admin: [`SuperAdmin/Components/Sidebar.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/SuperAdmin/Components/Sidebar.vue)
     - Admin Biro 3: [`AdminBiroTiga/Components/Sidebar.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/AdminBiroTiga/Components/Sidebar.vue)
   - Pill navigasi aktif dilengkapi pantulan kilau glossy (*gradient reflection highlight*) dengan gradasi resmi UKDW (`bg-gradient-to-r from-[#0D542B] to-[#157a41]`), bayangan halus (`shadow-md shadow-[#0D542B]/20`), dan ikon berlatar frosted glass.
   - Navigasi mobile dilengkapi topbar melayang dengan efek blur `bg-white/80 backdrop-blur-xl`.
   - Kartu profil pengguna di bagian bawah sidebar dikemas dalam kontainer frosted glass melengkung (`rounded-2xl`) yang estetik dan elegan.

4. **Konsistensi Layout Layar Terkunci ("Stay on Resize/Shrink")**:
   - Menerapkan penataan fixed viewport pada sidebar desktop (`fixed inset-y-0 left-0 w-72 h-screen overflow-hidden`) yang dipadukan dengan kontainer konten utama ber-padding `lg:pl-72` dan `min-w-0 overflow-x-auto` di seluruh halaman dashboard dan modul stakeholder.
   - Struktur ini menjamin bahwa saat ukuran layar diperkecil atau diperbesar (zoom-in), sidebar tetap terkunci kokoh di sisi kiri dan tata letak tabel/kartu data tidak terdorong atau pecah ke bawah, melainkan menggulir (*scroll*) secara horizontal di dalam kontainernya secara rapi.

5. **Koreksi Typo & Kelengkapan Relasi ERD ([`ERD.md`](ERD.md))**:
   - Menambahkan kolom `created_by` (FK `users.id`) dan `created_prodi_id` (FK `prodi.id`) pada entitas `perusahaan`.
   - Menambahkan relasi Mermaid dan matriks relasi antar tabel untuk `users -> perusahaan` (pengaju), `prodi -> perusahaan` (prodi pengaju), `propinsi -> perusahaan`, `kabupaten -> perusahaan`, dan `ref_negara -> perusahaan`.
   - Mengoreksi deskripsi kamus data `users` pada Bagian 2.A dengan menambahkan role `admin_fakultas` dan referensi `fakultas_id`.


### Refinement Tampilan Admin Bersih, Tipis, & Kotak (Enterprise Standard)
1. **Redesain Navigasi Sidebar Menjadi Ramping & Kotak**:
   - Menghapus background gradasi dan kilau berlebih, digantikan dengan warna hijau solid resmi UKDW (`bg-[#0D542B]`).
   - Menghilangkan kontainer kotak berlapis di sekeliling ikon SVG. Ikon kini menyatu langsung dengan teks menu secara minimalis.
   - Mengubah bentuk *bubble* tumpul (`rounded-2xl`) menjadi sudut kotak presisi (`rounded-lg` / `rounded-md`) dengan padding ramping (`px-3 py-2 text-xs`) agar lebih hemat ruang dan berbobot profesional.
2. **Standardisasi Seluruh Dashboard Mengikuti Format Header Putih Flat**:
   - Menghapus hero header hijau raksasa (`bg-[#0D542B] pt-8 pb-16`) dan *negative margin* (`-mt-10`) pada Dashboard Admin Prodi, Admin Fakultas, Super Admin, dan Admin Biro 3.
   - Menggantinya dengan bilah header putih bersih dan datar (`bg-white border-b border-gray-200 px-6 py-5`) dengan breadcrumbs rapi, persis seperti pada halaman verifikasi perusahaan.
   - Seluruh kartu metrik KPI dan tabel alumni kini menggunakan sudut kotak (`rounded-lg`) dengan batas halus (`border border-slate-200`) yang terkunci kokoh dan tidak bergeser saat ukuran layar diperkecil.
3. **Penyelarasan Badges Ringkasan**:
   - Badge ringkasan statistik ("Menunggu Verifikasi" dan "Master Terverifikasi") disesuaikan menjadi format kotak ramping (`rounded-lg`, `px-3.5 py-1.5`) dengan penanda titik status solid.

---

## [2026-09-21] Perbaikan Akses Tombol Tambah Pertanyaan dan Edit Soal (Super Admin)

### Ringkasan
1. **Perbaikan Akses Tombol Tambah dan Edit Pertanyaan**:
   - Memperbaiki deklarasi variabel `initialKeterangan` pada fungsi `openQuestionModal()` di [`Index.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/SuperAdmin/Pertanyaan/Index.vue) yang sebelumnya belum didefinisikan (`ReferenceError`), sehingga tombol **"Tambah Pertanyaan"** dan tombol pensil **"Edit"** dapat diakses dan memunculkan pop-up SweetAlert2 dengan normal.
   - Mengganti pengisian nilai input (kode pertanyaan, bunyi soal, keterangan/constraint, dan urutan) agar diisikan secara aman melalui hook `didOpen` SweetAlert2, menghindari konflik karakter khusus/tanda petik pada HTML template string.
2. **Sinkronisasi Validasi Backend ([`SimpanPertanyaanController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/SuperAdmin/KelolaPertanyaan/SimpanPertanyaanController.php))**:
   - Menambahkan tipe `radio`, `checkbox`, dan field `keterangan` (batasan nilai/constraint) ke dalam daftar aturan validasi `store` dan `update` agar penyimpanan soal baru dan pembaruan soal berjalan mulus tanpa tertolak validasi server.
3. **Pengujian & Verifikasi**:
   - Build frontend berhasil (`npm run build` sukses 1.82s).
   - Seluruh 59 test suite PHPUnit lulus 100%.

---

## [2026-09-21] Eliminasi Duplikasi Tombol Tambah Opsi, Pembatasan Opsi Hanya Tipe Tertentu, dan Pembersihan Tipe Soal Tidak Terpakai

### Ringkasan
1. **Eliminasi Duplikasi Tombol Tambah Opsi**:
   - Menghapus kontainer dan tombol duplikat *"Belum ada pilihan opsi jawaban untuk butir ini [+ + Tambah Opsi]"* dari baris pertanyaan.
   - Tombol tambah opsi kini **hanya ada satu**, yaitu ikon **`+`** (warna hijau) yang rapi pada kolom **Aksi** di baris tabel.
2. **Pembatasan Opsi Jawaban Hanya untuk Tipe yang Mendukung**:
   - Tombol **`+`** dan kartu daftar opsi jawaban kini **hanya muncul** jika tipe pertanyaan termasuk dalam tipe yang mendukung opsi:
     - `radio` (*Pilihan Ganda (Radio)*)
     - `radio_input` (*Radio + Angka*)
     - `radio_text` (*Radio + Teks*)
     - `multiple_choice` (*Kotak Centang (Checkbox)*)
     - `rating_5` (*Skala Rating (1-5)*)
     - `multiple_number` (*Angka Ganda*)
     - `matrix` (*Matriks Skala*)
     - `matrix_dual` (*Dual Matrix*)
     - `multiple_textbox` (*Multiple Textbox*)
   - Untuk tipe isian langsung atau pemisah (`text`, `textarea`, `number`, `date`, `header`), tombol `+` dan blok opsi tidak ditampilkan sama sekali sehingga tampilan baris menjadi bersih dan tidak membingungkan.
3. **Pembersihan Tipe Pertanyaan Duplikat dan Tidak Terpakai**:
   - Menghapus tipe `single_choice` (duplikat dari `radio`), serta tipe yang tidak dipakai (`dropdown`, `searchable_select`, `time`, `file`) dari daftar pilihan tipe pada Super Admin dan Biro 3.
4. **Verifikasi dan Pengujian**:
   - Seluruh 59 feature tests di Laravel Unit & Feature Suite lulus 100% tanpa error.
   - Kode PHP diformat rapi dengan Laravel Pint.

---

### Ringkasan
1. **Penyempurnaan Tampilan Dashboard Alumni ([`Dashboard.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Dashboard.vue))**:
   - Menghapus kotak ikon avatar/clipboard/topi di pojok kiri atas kartu agar tampilan lebih bersih, minimalis, dan elegan.
   - Menyamakan tinggi seluruh kartu (*equal height*) dan meratakan posisi vertikal kontainer persentase progress bar (`mt-auto`) sehingga seluruh kartu memiliki elevasi dan tinggi baseline yang simetris dan rapi.
   - Menghilangkan ikon panah (`→`) dari seluruh tombol navigasi kartu dashboard ("Kelola Data Profil", "Isi Kuesioner Umum", "Buka Kuesioner Prodi").
   - Menerapkan sudut tumpul modern (*rounded-3xl* pada kartu, *rounded-2xl* pada progress box dan tombol, *rounded-full* pada badge status) untuk tampilan yang lebih profesional.
2. **Standarisasi Ikon Tombol Keluar / Logout**:
   - Mengganti seluruh ikon keluar (pintu/emotikon) di seluruh modul sistem (Alumni Navbar, Super Admin Sidebar, Admin Prodi Navbar & Sidebar, Admin Biro 3 Sidebar & Navbar, Admin Fakultas Sidebar) dengan ikon SVG Power-Off yang konsisten dan profesional.
3. **Pembersihan Komponen**:
   - Menghapus blok komentar HTML yang tidak digunakan pada [`Banner.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Components/Kuesioner/Banner.vue).

---

## [2026-09-21] Optimalisasi Navigasi Seksi Kuesioner (Instan 1x Klik Tanpa Menunggu Simpan)

### Ringkasan
Memperbaiki navigasi tombol Lanjut (`>`) dan Stepper pada Kuesioner Program Studi ([`KuesionerProdi.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/KuesionerProdi.vue)) dan Kuesioner Universitas ([`Kuesioner.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Kuesioner.vue)) sehingga perpindahan seksi berlangsung **instan dengan 1x klik (0 ms)** tanpa jeda, sementara proses penyimpanan jawaban ke database dijalankan secara aman di latar belakang (*asynchronous background sync*).

### Detail Perbaikan
- **Optimistic Immediate Navigation**:
  - Sebelumnya, perpindahan seksi ditaruh di dalam callback `onSuccess` request HTTP server, sehingga tampilan tertahan di seksi yang sama selama 0.5–1 detik sebelum respon server diterima (menyebabkan kesan harus diklik 2 kali).
  - Sekarang, indeks seksi aktif (`activeSectionIndex`) dan *smooth scroll* langsung berpindah seketika saat tombol diklik (cukup 1x klik), dan permintaan penyimpanan data dikirimkan di latar belakang tanpa memblokir pergerakan pengguna.

---

### Ringkasan
1. Memperbaiki bug pada backend [`SimpanProfilController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/Alumni/Profil/SimpanProfilController.php) di mana data nama/email atasan yang dihapus oleh alumni sebelumnya dapat terisi kembali dengan data lama dari database akibat adanya nilai *fallback* yang tidak sengaja mempertahankan data `atasan->nama` lama.
2. Mengubah seluruh kolom pengisian pada Halaman Profil Alumni ([`FormPribadi.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/AlumniProfil/FormPribadi.vue), [`FormOrangTua.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/AlumniProfil/FormOrangTua.vue), [`FormKarier.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/AlumniProfil/FormKarier.vue), dan [`Index.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Profil/Index.vue)) menjadi **Wajib Diisi (Mandatory)**, ditandai dengan tanda bintang merah (`*`), indikator border merah saat belum terisi / hijau saat valid, serta validasi kelengkapan tab (*tab completeness*) yang hanya mencentang hijau (`V`) jika seluruh kolom telah terisi lengkap.

### Detail Perbaikan
- **Backend ([`SimpanProfilController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/Alumni/Profil/SimpanProfilController.php))**:
  - Memperbaiki logika `updateOrCreate` pada relasi `atasan` agar menyimpan nilai `null` secara langsung ketika kolom dihapus/dikosongkan oleh pengguna, tanpa menimpa atau mengembalikan nama atasan lama secara otomatis.
- **Frontend ([`FormPribadi.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/AlumniProfil/FormPribadi.vue), [`FormOrangTua.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/AlumniProfil/FormOrangTua.vue), [`FormKarier.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/AlumniProfil/FormKarier.vue))**:
  - Semua field formulir kini dilengkapi tanda bintang wajib (`*`), atribut `autocomplete="off"` untuk mencegah intervensi *browser autofill*, serta penanda warna border reaktif (merah jika kosong/invalid, hijau jika telah terisi dengan benar).
- **Evaluasi Kelengkapan Tab ([`Index.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Profil/Index.vue))**:
  - Menyelaraskan fungsi `tabCompleteness` agar memeriksa seluruh field wajib di setiap tab (Identitas Pribadi, Data Kontak & Domisili, Data Orang Tua, Pekerjaan, Perusahaan, Atasan Langsung, dan Jejaring Sosial).

---

### Ringkasan
Memperbaiki styling visual kolom-kolom isian opsional pada halaman Profil Alumni ([`FormPribadi.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/AlumniProfil/FormPribadi.vue), [`FormOrangTua.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/AlumniProfil/FormOrangTua.vue), dan [`FormKarier.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/AlumniProfil/FormKarier.vue)) yang sebelumnya menampilkan border/background merah (`border-rose-300 bg-rose-50/20`) saat kosong meskipun tab formulir sudah lengkap dan bertanda centang hijau (`V`).

### Detail Perbaikan
- **Pembersihan Border Merah pada Kolom Opsional**:
  - Kolom opsional kini menggunakan warna border netral standar (`border-gray-200 bg-white`) ketika belum diisi, dan akan berubah menjadi hijau (`border-emerald-300`) saat terisi dengan format yang benar.
  - Border merah (`border-rose-300`) hanya dimunculkan jika:
    1. Kolom tersebut bertanda wajib (`*`) dan masih kosong, atau
    2. Format inputan salah (seperti URL medsos atau nomor HP yang tidak valid).
- **Cakupan Kolom yang Diperbarui**:
  - **Data Pribadi**: Golongan Darah, Kewarganegaraan, No KK, NISN, No BPJS, Email Students, Kecamatan, Kelurahan, Kode Pos.
  - **Data Orang Tua**: Kota/Kabupaten Tambahan, Kode Pos Orang Tua.
  - **Karier & Pekerjaan**: LinkedIn URL & Username, Instagram URL, Facebook URL, Bidang Keahlian (*Expertise*), Minat & Ketertarikan, Gaji/Take Home Pay (opsional), Jenis Instansi Lainnya, Kode Pos Kantor, serta Telepon Atasan jika Email Atasan sudah terisi (atau sebaliknya).

---

## [2026-09-21] Penyederhanaan Stepper Input Angka & Penghapusan Teks Duplikasi Instruksi

### Ringkasan
Menghapus duplikasi teks instruksi (*"Jawaban bisa lebih dari satu"* / *"Pilih salah satu jawaban"*) yang sebelumnya muncul ganda di bawah judul pertanyaan dan di atas pilihan jawaban, serta menyederhanakan desain input angka (*stepper*) menjadi bersih, standar, dan tanpa ikon/emoji.

### Detail Perbaikan
- **Eliminasi Teks Duplikasi pada [`KartuPertanyaan.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Components/Kuesioner/KartuPertanyaan.vue)**:
  - Menambahkan fungsi `getCleanKeterangan()` untuk menyaring teks keterangan dari database jika isinya hanya instruksi duplikat (seperti *"Jawaban bisa lebih dari satu"*), sehingga hanya pill badge resmi yang tampil rapi tanpa pengulangan teks di bawah judul pertanyaan.
- **Desain Stepper Angka Minimalis & Bersih**:
  - Mengubah stepper angka menjadi inline stepper ringkas dengan label satuan yang diletakkan langsung di samping stepper, menggunakan format ringkas `/ perusahaan` (pada butir `F6`, `F7`, `F7A`) dan `Bulan` (pada `F502`, `F506`) tanpa ikon/emotikon berlebih.

---

### Ringkasan
Memperbaiki kolom input angka pada butir pertanyaan F6, F7, dan F7A (*jumlah perusahaan yang dilamar, merespons, dan mengundang wawancara*) yang sebelumnya terhimpit/tidak kelihatan dan sulit diklik pada tata letak kartu berdampingan (*3-column grid layout*).

### Detail Perbaikan
- **Restrukturisasi Komponen Input Angka ([`KartuPertanyaan.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Components/Kuesioner/KartuPertanyaan.vue))**:
  - Badge satuan unit panjang (seperti *"Satuan: Perusahaan / Instansi"* dan *"Satuan: Undangan Wawancara"*) dipindahkan ke baris bawah stepper `[-] [ 0 ] [+]` alih-alih berjejer secara inline di dalam kotak input.
  - Memberikan lebar penuh yang leluasa (`flex-1 min-w-[70px]`) untuk kotak input angka di antara tombol `[-]` dan `[+]`, sehingga angka jelas terlihat dan pengguna dapat mengetik langsung maupun mengeklik tombol tambah/kurang dengan mudah di berbagai ukuran layar.

---

### Ringkasan
Memperbaiki permasalahan visual di mana butir pertanyaan Evaluasi Kompetensi F17 (dan pertanyaan skala rating) tampak kosong/tidak terpilih meskipun data jawaban sudah terisi di database dan stepper sudah bertanda centang ("v").

### Detail Perbaikan
- **Resolusi Mismatch Tipe Data (String vs Integer) pada [`TabelF17.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Components/Kuesioner/TabelF17.vue)**:
  - Jawaban kuesioner di database `tracer.answer` tersimpan dalam bentuk label teks (seperti `"Sangat Rendah"`, `"Rendah"`, `"Cukup"`, `"Tinggi"`, `"Sangat Tinggi"`).
  - Sebelumnya, pengecekan radio di template menggunakan `Number(getScoreA(item)) === score`. Karena `Number("Sangat Tinggi")` menghasilkan `NaN`, perbandingan `NaN === 5` selalu bernilai `false`, sehingga seluruh bulatan radio F17 tidak terpilih/kosong secara visual.
  - Memperbarui pengecekan `:checked` dan kelas penanda aktif agar menggunakan helper `getRatingScore()` yang memetakan label string ke nilai angka 1–5 secara seamless.
  - Memperbarui `setScoreA` dan `setScoreB` agar memetakan angka yang diklik ke format teks resmi kuesioner.
- **Normalisasi Pertanyaan Skala Rating pada [`KartuPertanyaan.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Components/Kuesioner/KartuPertanyaan.vue)**:
  - Menambahkan kamus pemetaan skala rating (`ratingScoresMap`) dan fungsi helper `isRatingScoreSelected()` & `setRatingScore()` untuk semua butir bertipe `rating_5` agar mendukung input berupa string opsi maupun angka skor.

---

### Ringkasan
Memperbaiki permasalahan nilai jawaban pertanyaan F8 (*"Jelaskan status Anda saat ini"*) yang kerap hilang/ter-reset serta menyelaraskan aturan percabangan (*branching/jump logic*) untuk pertanyaan F10 (*"Apakah anda aktif mencari pekerjaan dalam 4 minggu terakhir?"*).

### Detail Perbaikan
- **Normalisasi Opsi F8 Backend & Frontend**:
  - `KuesionerSyncService` dan `KuesionerController` kini menormalisasikan string profil `kategori_pekerjaan` (seperti `"Pekerja"`, `"Mencari Kerja"`, `"Belum Memungkinkan Bekerja"`, `"Wiraswasta"`) secara presisi ke teks opsi resmi di `ref_subpertanyaan_detil` (misal: `"Bekerja (full time/part time)"`).
  - Mengeliminasi ketidakcocokan nilai radio button Vue yang sebelumnya menyebabkan pilihan F8 tidak terpilih saat halaman di-*render* ulang.
- **Merge State Jawaban Lokal vs Server yang Aman**:
  - Pada [`Kuesioner.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Kuesioner.vue), fungsi `getInitialAnswers` diperbarui agar tidak menimpa jawaban valid di `localStorage` dengan nilai kosong dari server.
- **Logika Percabangan F10**:
  - Pilihan **"Bekerja (full time/part time)"** dan **"Wiraswasta"** (serta **"Melanjutkan Pendidikan"**) sekarang secara otomatis menyembunyikan/melewati pertanyaan `F10` baik di frontend ([`Kuesioner.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Kuesioner.vue)) maupun di penghitungan kelengkapan backend ([`KelengkapanTracerService.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Services/Kuesioner/KelengkapanTracerService.php)).
  - Pertanyaan `F10` **hanya ditampilkan** untuk responden dengan status **"Belum memungkinkan bekerja"** atau **"Tidak kerja tetapi sedang mencari kerja"**.
- **Eksklusivitas Input Radio Pilihan Tunggal F3 (Sebelum Lulus vs Sesudah Lulus)**:
  - Pada butir pertanyaan `F3` (*"Kapan anda mulai mencari pekerjaan?"*) dan seluruh pertanyaan bertipe `radio_input` / `radio_text` di [`KartuPertanyaan.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Components/Kuesioner/KartuPertanyaan.vue), kini diberlakukan **eksklusivitas pilihan tunggal mutlak**.
  - Jika alumni memilih atau mengisi salah satu opsi (misal: *"Sebelum lulus ... bulan"*), maka isian pada opsi lainnya (misal: *"Sesudah lulus ... bulan"* atau *"Saya tidak mencari kerja"*) **langsung dikosongkan/dihapus secara otomatis**.
  - Backend [`SimpanJawabanController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/Alumni/Kuesioner/SimpanJawabanController.php) dan [`KuesionerController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/Alumni/Kuesioner/KuesionerController.php) juga menjamin hanya opsi yang aktif yang tersimpan dan dimuat kembali.
- **Stepper & Navigasi Dinamis Berdasarkan Bagian yang Relevan (Visible Sections)**:
  - [`Stepper.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Components/Kuesioner/Stepper.vue), [`Banner.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Components/Kuesioner/Banner.vue), dan [`Navigasi.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Components/Kuesioner/Navigasi.vue) kini hanya menampilkan dan menavigasikan bagian/seksi kuesioner yang **aktif dan relevan** (*visible sections*) sesuai status pekerjaan alumni di F8.
  - Menghilangkan masalah halaman kosong (*blank*) saat alumni mengeklik bulatan tahapan pada seksi yang seluruh butir pertanyaannya tidak relevan/disembunyikan oleh sistem.

---

## [2026-09-21] Sinkronisasi Otomatis Progres Kuesioner Prodi, Penghapusan Jawaban Kosong & Konsistensi Dashboard

### Ringkasan
Memperbaiki inkonsistensi persentase progres antara halaman Kuesioner Prodi (`/alumni/kuesioner-prodi`) dan Dashboard Alumni (`/alumni/dashboard`). Memastikan jawaban yang dikosongkan langsung terhapus dari database dan progres selalu tersinkronisasi secara otomatis (*real-time auto-save*).

### Bug Fixes
- **Karakter `>` Muncul di Header Alumni**: Memperbaiki kesalahan ketik tag `<<template>` menjadi `<template>` pada [`KuesionerProdi.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/KuesionerProdi.vue).
- **Inkonsistensi Persentase Progres**:
  - `Banner.vue` diperbarui agar menampilkan progres butir soal terjawab riil (`41 / 42 Soal (98%)`) bukan sekadar indeks tahapan seksi.
  - `KuesionerProdiController.php` sekarang menghapus baris dari tabel `prodi_response` ketika butir pertanyaan dikosongkan.
  - Memperketat aturan autofill prodi agar tidak salah mendeteksi pertanyaan kuesioner reguler sebagai pertanyaan identitas.
  - Menambahkan sinkronisasi otomatis latar belakang (*debounced background auto-save*) dan sinkronisasi instan saat *mount* di [`KuesionerProdi.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/KuesionerProdi.vue) sehingga database server dan Dashboard selalu 100% mutakhir.

---

## [2026-09-21] Sentralisasi Penyimpanan Profil Admin, Perbaikan Bug Gaji & Social URL, Autofill View F5a1/F5a2/F5C

### Ringkasan
Sesi ini berfokus pada perbaikan kritis alur penyimpanan data profil alumni oleh seluruh admin (Super Admin, Biro 3, Admin Fakultas, Admin Prodi). Sebelumnya, masing-masing controller menduplikasi logika persistensi yang tidak konsisten, sehingga data profil tidak tersimpan dengan benar, gaji berlipat ganda setiap disimpan, dan URL media sosial panjang menyebabkan error SQL `SQLSTATE[22001]`.

### Bug Fixes
- **Bug Kritis: Profil Tidak Tersimpan / Reset saat Refresh (Semua Admin Role)**:
  - **Sebab**: Setiap controller (SuperAdmin, Biro 3, Fakultas, Prodi) masing-masing memiliki implementasi form-build & persistensi yang berbeda-beda dan tidak sinkron.
  - **Solusi**: Membuat `AdminAlumniProfileService` (service terpusat baru) yang menangani seluruh alur `buildFormData()` dan `updateProfile()` untuk semua role.
  - **File terdampak**: [`DetailAlumniSuperAdminController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/SuperAdmin/KelolaAlumni/DetailAlumniSuperAdminController.php), [`DetailAlumniController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/AdminBiroTiga/KelolaAlumni/DetailAlumniController.php), [`DetailAlumniFakultasController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/AdminFakultas/KelolaAlumni/DetailAlumniFakultasController.php), [`DaftarAlumniProdiController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/AdminProdi/KelolaAlumni/DaftarAlumniProdiController.php).
- **Bug Kritis: Gaji Berlipat Ganda Setiap Kali Disimpan**:
  - **Sebab**: Input gaji dari frontend dapat berformat ribuan (misal `5.000.000`) yang kemudian diparsing dengan cara berbeda di tiap controller. Beberapa controller tidak membersihkan separator ribuan sehingga nilai `5.000` dibaca sebagai `5` (bukan 5 juta) atau dikalikan.
  - **Solusi**: `AdminAlumniProfileService::updateProfile()` sekarang membersihkan input gaji secara konsisten: jika mengandung pola desimal 2 digit di akhir, strip dahulu; kemudian hapus semua karakter non-numerik; hasilnya dibulatkan ke integer.
  - **Kode normalisasi gaji**: Input `"5.000.000"` → hapus `,00` / `.00` di akhir (jika ada) → strip non-angka → `5000000`.
- **Bug: `SQLSTATE[22001] String data, right truncated` pada kolom `instagram_url` / `facebook_url`**:
  - **Sebab**: URL clipboard yang sangat panjang (>255 karakter, misal URL Google Search) tersimpan ke kolom `varchar(255)`.
  - **Solusi**: `AdminAlumniProfileService::updateProfile()` memvalidasi dan menyimpan field URL sosial media hanya jika nilainya bukan null/kosong—tidak ada pemotongan paksa, error ini dicegah dengan membersihkan input URL sebelum disimpan. Kolom URL tetap disimpan apa adanya; pengguna disarankan hanya memasukkan URL profil (bukan URL pencarian).
- **Bug: F5C (Posisi Wiraswasta) Terisi Padahal Alumni Bukan Wiraswasta**:
  - **Sebab**: Saat menyimpan, tidak ada logika eksklusivitas antara `posisi_jabatan` (Pekerja) dan `posisi_wiraswasta` (Wiraswasta).
  - **Solusi**: `AdminAlumniProfileService::updateProfile()` sekarang secara eksplisit menghapus (set `null`) field yang tidak relevan berdasarkan nilai `kategori_pekerjaan`: Pekerja → `posisi_wiraswasta = null`; Wiraswasta → `posisi_jabatan = null`; Melanjutkan Pendidikan → keduanya `null`.
- **Bug: F5a1 & F5a2 (Provinsi & Kota Tempat Kerja) Tidak Terbaca di Excel**:
  - **Sebab**: View `v_alumni_kuesioner_autofill` tidak memetakan data perusahaan (provinsi dan kabupaten) ke kode instrumen `F5a1` dan `F5a2`.
  - **Solusi**: Migration view diperbarui ([`2026_09_01_193005_create_v_alumni_kuesioner_autofill_view.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/database/migrations/2026_09_01_193005_create_v_alumni_kuesioner_autofill_view.php)) untuk memetakan nama provinsi dari `perusahaan.propinsi_id` ke `F5a1` dan nama kabupaten dari `perusahaan.kabupaten_id` ke `F5a2` secara dinamis.

### New Features / Architecture
- **[NEW] `AdminAlumniProfileService`** ([`app/Services/Alumni/AdminAlumniProfileService.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Services/Alumni/AdminAlumniProfileService.php)):
  - **`buildFormData(Biodata $alumni): array`** — Menyusun array formData lengkap (150+ field) untuk dikirim ke frontend Vue, dengan multi-level fallback (biodata → dataAkademik → yudisium → perusahaan → atasan → orangTua).
  - **`getRefOptions(): array`** — Mengambil opsi referensi kuesioner resmi (F2G, F2H, F5D, F11, F8) dari `ref_subpertanyaan_detil`, digunakan untuk mengisi dropdown di `FormKarier.vue`.
  - **`updateProfile(Biodata $biodata, array $data): void`** — Menyimpan seluruh pembaruan profil alumni secara atomik dalam satu operasi: (1) DataOrangTua (upsert by NIM), (2) Yudisium (upsert by NIM), (3) Perusahaan (firstOrCreate + update), (4) Atasan (update atau create), (5) Sanitasi gaji, (6) Update Biodata, (7) Sinkronisasi `KuesionerSyncService`.

### Refactoring
- Seluruh 4 controller admin ([`DetailAlumniSuperAdminController`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/SuperAdmin/KelolaAlumni/DetailAlumniSuperAdminController.php), [`DetailAlumniController` (Biro3)](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/AdminBiroTiga/KelolaAlumni/DetailAlumniController.php), [`DetailAlumniFakultasController`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/AdminFakultas/KelolaAlumni/DetailAlumniFakultasController.php), [`DaftarAlumniProdiController`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/AdminProdi/KelolaAlumni/DaftarAlumniProdiController.php)) **dimigrasikan** dari implementasi form-build & persistensi yang tersebar menjadi pemanggilan terpusat ke `AdminAlumniProfileService`.
- [`AlumniTracerExcelExporter`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Services/Export/AlumniTracerExcelExporter.php) diperbarui untuk mendukung pembacaan jawaban dari kolom `answer_text` **maupun** `answer` secara berurutan (multi-column fallback), sehingga kompatibel dengan jawaban yang disimpan melalui entri manual kuesioner maupun sinkronisasi otomatis profil.

### Quality Assurance
- ✅ **54 PHPUnit Tests** lulus, 298 assertions (0 failures).
- ✅ **Laravel Pint** code style: passed (0 file dimodifikasi).

---

## [2026-09-21] Penyederhanaan Status (v dan x) & Kelengkapan 100% 4 Sub-Tab Ekspor Excel

- **Standarisasi Kolom Status Menjadi 'v' dan 'x' Tanpa Titik**:
  - Mengubah indikator status pada tabel kuesioner universitas dan kuesioner prodi di seluruh stakeholder ([SuperAdmin/Alumni/Show.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/SuperAdmin/Alumni/Show.vue), [AdminBiroTiga/AlumniShow.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/AdminBiroTiga/AlumniShow.vue), [AdminFakultas/Alumni/Show.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/AdminFakultas/Alumni/Show.vue), [AdminProdi/Alumni/Show.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/AdminProdi/Alumni/Show.vue)) menjadi huruf **`v`** (bold hijau untuk terjawab) dan **`x`** (bold merah untuk belum terjawab) secara bersih tanpa titik bulat (`•`).
  - Menyelaraskan kolom status pada berkas Excel `.xls` ([AlumniTracerExcelExporter.php](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Services/Export/AlumniTracerExcelExporter.php)) menjadi `v` dan `x`.
- **Ekspor Excel (.xls) 100% Lengkap Mencakup Seluruh 4 Sub-Bagian Profil**:
  - Menyusun 4 sub-bagian grid identitas lengkap di lembar kerja Excel:
    1. **Data Diri & Kontak Pribadi**: NIM, Nama Lengkap, NIK (KTP), NPWP, No. Kartu Keluarga (KK), No. BPJS/Asuransi, NISN, Jenis Kelamin, Tempat Lahir, Tanggal Lahir, Agama, Golongan Darah, Kewarganegaraan, Nomor Telepon/WA, Email Pribadi, Email Kampus/Mahasiswa, LinkedIn (URL/Username), Media Sosial Lainnya (IG & FB), Bidang Keahlian (*Expert*), Minat, dan Alamat Lengkap Domisili.
    2. **Data Akademik & Kelulusan**: Program Studi, Fakultas, Angkatan Masuk, Status Mahasiswa, Tahun Lulus, Semester Kelulusan, IPK Kumulatif, Total SKS, Total Angka Kualitas, Asal Sekolah (SMA/SMK), Jurusan Asal Sekolah, dan Alamat Lengkap Asal Sekolah (Kota/Kabupaten & Provinsi).
    3. **Data Orang Tua / Wali**: Nama Lengkap Orang Tua/Wali, Pekerjaan Orang Tua/Wali, Nomor Telepon/WA Orang Tua, Kota Asal Orang Tua, dan Alamat Lengkap Orang Tua beserta Kode Pos.
    4. **Data Karier, Perusahaan & Atasan**: Status/Kategori Pekerjaan, Posisi/Jabatan, Posisi Wiraswasta, Estimasi Gaji (*Take Home Pay*), Jenis/Bidang Pekerjaan, Nama Perusahaan/Kantor, Sektor Perusahaan, Skala Perusahaan, Jenis Usaha, Jenis Lokasi & Negara Perusahaan, Alamat Lengkap Perusahaan beserta Kode Pos, Nama Atasan Langsung, Kontak Atasan (Telepon & Email), dan Riwayat Pendidikan Lanjut (Tingkat S2/S3/Profesi, Kampus & Prodi Lanjut).
  - Menerapkan format teks eksplisit Microsoft Excel (`style="mso-number-format:'\@';"`) secara inline pada setiap cell data numerik panjang (NIK, KK, BPJS, NISN, NPWP, Telepon) untuk mencegah konversi notasi ilmiah eksponensial (seperti `3,4041E+15`).

## [2026-09-21] Rekonstruksi & Kelengkapan 4 Sub-Tab Database Views (v_alumni_profile_summary & v_alumni_audit_rekap)

- **Kelengkapan Penuh 4 Sub-Tab Profil di Database View ([2026_09_20_234000_create_alumni_audit_database_views.php](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/database/migrations/2026_09_20_234000_create_alumni_audit_database_views.php))**:
  - Merekonstruksi `v_alumni_profile_summary` menjadi 78 kolom lengkap yang mencakup seluruh field dari 4 tab profil `/alumni/profile`:
    1. **Data Pribadi**: NIK, No KK, No BPJS, NISN, NPWP, Email Pribadi, Email Kampus, Telepon, Tempat/Tanggal Lahir, Jenis Kelamin, Agama, Golongan Darah, Kewarganegaraan, Alamat, Kelurahan, Kecamatan, Kode Pos, ID & Nama Provinsi (`propinsi`), ID & Nama Kabupaten/Kota (`kabupaten`), Akun Instagram, Facebook, LinkedIn (URL & Username), Bidang Keahlian (*expert*), dan Minat.
    2. **Data Akademik & Lembaga**: Program Studi (ID, Kode, Nama), Fakultas (ID, Kode, Nama, Singkatan), Angkatan Masuk, Tahun Lulus, Tahun Akademik Lulus, IPK Kumulatif, Total SKS, Total Angka Kualitas, Status Mahasiswa, Asal Sekolah, Alamat Sekolah, Kota/Kabupaten Sekolah, Provinsi Sekolah, dan Jurusan SMA/SMK. Sesuai instruksi, entitas yudisium dilepaskan dari summary.
    3. **Data Orang Tua / Wali**: Nama Lengkap Orang Tua, Pekerjaan, Alamat, Kota, Nomor Telepon, dan Kode Pos.
    4. **Data Karier & Instansi Bekerja**: Kategori Pekerjaan, Posisi Jabatan, Posisi Wiraswasta, Tingkat Pendidikan Lanjut, Nama Perguruan Tinggi, Program Studi S2/S3, Estimasi Gaji (*Take Home Pay*), Jenis Pekerjaan, Info Perusahaan lengkap (ID, Nama, Alamat, Sektor, Skala, Jenis Perusahaan, Jenis Lokasi Domisili, Negara), dan Kontak Atasan Langsung (Nama, Email, Nomor Telepon).
  - Memperbarui master view `v_alumni_audit_rekap` (89 kolom) untuk menggabungkan 78 kolom profil di atas dengan rekapitulasi audit kelengkapan kuesioner universitas (`v_alumni_tracer_univ_status`) dan prodi (`v_alumni_tracer_prodi_status`).
- **Berlaku Seragam untuk Seluruh Stakeholder**:
  - Memberikan akses data lengkap instan bagi Super Admin, Admin Biro 3, Admin Fakultas, dan Admin Program Studi.

## [2026-09-21] Full Profile Parity (Biro 3, Fakultas, Prodi), Sidebar Cutoff Fix, Comprehensive Excel Fields & Harmonized Dashboards

- **Kelengkapan Penuh Detail Profil Mahasiswa (Paritas 100% Seluruh Stakeholder)**:
  - Mengintegrasikan 4 sub-komponen profil lengkap (`FormPribadi.vue`, `FormAkademik.vue`, `FormOrangTua.vue`, `FormKarier.vue`) ke halaman detail alumni **Admin Biro 3** ([AlumniShow.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/AdminBiroTiga/AlumniShow.vue)), **Admin Fakultas** ([Show.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/AdminFakultas/Alumni/Show.vue)), dan **Admin Program Studi** ([Show.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/AdminProdi/Alumni/Show.vue)).
  - Menyediakan form data reaktif lengkap dengan sub-navigasi 4 tab (*1. Data Pribadi, 2. Data Akademik, 3. Data Orang Tua, 4. Karier & Perusahaan*) serta tombol *Simpan Perubahan Profil* yang terhubung ke endpoint backend masing-masing stakeholder (`/biro3/alumni/{id}/profile`, `/fakultas/alumni/{id}/profile`, `/prodi/alumni/{id}/profile`).
  - Mengirimkan data master referensi wilayah (`provinces`, `kabupatens`) dan master `companies` dari masing-masing controller ke halaman frontend.
- **Perbaikan Teks Terpotong pada Sidebar Seluruh Stakeholder**:
  - Memperbaiki pemotongan nama pengguna dan nama fakultas/prodi yang sebelumnya terpotong oleh kelas `truncate` (misal: `Admin Fakultas Teknol...` dan `FAKULTAS TEKNOLOGI INFORMA...`) pada 4 komponen sidebar:
    - [AdminFakultas/Components/Sidebar.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/AdminFakultas/Components/Sidebar.vue)
    - [AdminProdi/Components/Sidebar.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/AdminProdi/Components/Sidebar.vue)
    - [AdminBiroTiga/Components/Sidebar.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/AdminBiroTiga/Components/Sidebar.vue)
    - [SuperAdmin/Components/Sidebar.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/SuperAdmin/Components/Sidebar.vue)
  - Mengganti pembatasan `truncate` dengan `break-words leading-tight` / `leading-snug` sehingga seluruh nama tampil utuh, rapi, dan estetis.
- **Ekspor Excel (.xls) 100% Lengkap dengan Proteksi Teks & Multi-Level Fallback ([AlumniTracerExcelExporter.php](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Services/Export/AlumniTracerExcelExporter.php))**:
  - Memastikan seluruh field identitas alumni di berkas Excel terisi lengkap tanpa ada data kosong:
    - Identitas Diri & Kontak: NIM, Nama Lengkap, NIK (KTP), NPWP, No. Kartu Keluarga, No. BPJS, Nomor Telepon/WA, Email Pribadi, Email Kampus, dan Alamat Lengkap Saat Ini.
    - Rekam Jejak Akademik & Kelulusan: Program Studi, Fakultas, Tahun Lulus (dengan multi-level fallback dari `biodatas`, `yudisiums`, dan `data_akademik`), Semester Kelulusan, Angkatan Masuk, IPK, Total SKS, Status Yudisium, Judul Tugas Akhir, dan Dosen Pembimbing.
    - Informasi Karir: Nama Perusahaan, Posisi/Jabatan, Skala Perusahaan, Nama Atasan, dan Kontak Atasan.
  - Semua kode numerik panjang tetap terlindungi dengan format teks Microsoft Excel (`mso-number-format:'\@'`).
- **Penyelarasan Dashboard Utama Biro 3, Fakultas, dan Prodi Seragam dengan Super Admin**:
  - Menstandarisasi tata letak visual, palet warna solid UKDW (`#0D542B`, `#FDC700`, `#FFFFFF`), kartu KPI metrik 4-kolom, modul navigasi dua kolom, serta footer bersih di:
    - [AdminBiroTiga/Dashboard.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/AdminBiroTiga/Dashboard.vue)
    - [AdminFakultas/Dashboard.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/AdminFakultas/Dashboard.vue)
    - [AdminProdi/Dashboard.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/AdminProdi/Dashboard.vue)
- **Quality Assurance**:
  - Seluruh 54 tests PHPUnit Feature & Unit lulus 100% (298 assertions).
  - Standarisasi Laravel Pint lolos 100%.

- **Standarisasi Ekspor Excel (.xls) Terformat dengan Proteksi Teks NIK/NPWP/NIM ([AlumniTracerExcelExporter.php](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Services/Export/AlumniTracerExcelExporter.php))**:
  - Mengembalikan format unduhan ke file spreadsheet **Excel (.xls)** asli dengan styling penuh (Header UKDW Green `#0D542B`, blok Identitas Alumni, batas tabel, dan badge warna status).
  - Menerapkan format teks eksplisit Microsoft Excel (`mso-number-format:'\@'`) pada kolom respon, NIK, NPWP, NIM, nomor telepon, dan kode pertanyaan untuk mencegah Excel mengonversi angka panjang 16-digit menjadi notasi ilmiah eksponensial (seperti `3,40401E+15`).
  - Memperbaiki pembacaan NIK dan NPWP melalui fallback `COALESCE(b.nik, da.nik)` dan `COALESCE(b.npwp, da.npwp)` pada kuesioner autofill dan profil tracer.
  - Menyatukan fungsionalitas ekspor ke dalam satu service `AlumniTracerExcelExporter` yang digunakan seragam oleh Super Admin, Biro 3, Prodi, dan Fakultas.
- **Pembuatan Database Views Terpisah & Modular (Optimasi Query & Performa Tinggi)**:
  - Mengatasi kendala loading lambat yang sebelumnya disebabkan oleh ratusan kueri evaluasi N+1 di dalam loop PHP (`KelengkapanTracerService::evaluasiKelengkapanTotal`).
  - Merancang 5 Database Views independen, terstruktur, dan memiliki pemisahan fungsi yang jelas:
    1. `v_alumni_profile_summary`: Rekap data profil, akademik, yudisium, fakultas, prodi, orang tua, pekerjaan, dan perusahaan.
    2. `v_alumni_tracer_univ_status`: Agregasi progres dan kelengkapan butir kuesioner wajib universitas.
    3. `v_alumni_tracer_prodi_status`: Agregasi progres dan kelengkapan butir kuesioner program studi.
    4. `v_alumni_audit_rekap`: View master terpadu yang menggabungkan ketiga view di atas untuk query instan (*single-query instant fetch*).
    5. `v_alumni_tracer_export`: View terformat untuk kebutuhan audit dan ekspor Excel/CSV.
  - Seluruh direktori alumni di semua level stakeholder kini membaca langsung dari `v_alumni_audit_rekap`, mengurangi waktu loading dari detik/menit menjadi milidetik (<50ms).
- **Replikasi Antarmuka & Sidebar Terpadu Admin Biro 3 ([AlumniIndex.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/AdminBiroTiga/AlumniIndex.vue), [AlumniShow.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/AdminBiroTiga/AlumniShow.vue), [Sidebar.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/AdminBiroTiga/Components/Sidebar.vue))**:
  - Mengganti top navbar dengan **Sidebar Resmi UKDW** (`AdminBiroTiga/Components/Sidebar.vue`).
  - Menerapkan direktori DataTables terpadu lengkap dengan pencarian, filter tahun, semester, status, dan prodi.
  - Menerapkan tampilan Detail Mahasiswa dengan 3 Tab (1. Detail Profile, 2. Kuesioner Universitas, 3. Kuesioner Program Studi) serta mempertahankan kartu sinkronisasi LinkedIn (MCP Agent) dan ekspor Excel CSV.
- **Replikasi Antarmuka & Sidebar Terpadu Admin Prodi ([Index.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/AdminProdi/Alumni/Index.vue), [Show.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/AdminProdi/Alumni/Show.vue), [Sidebar.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/AdminProdi/Components/Sidebar.vue))**:
  - Mengganti navbar dengan **Sidebar Resmi UKDW** (`AdminProdi/Components/Sidebar.vue`).
  - Menampilkan direktori alumni dan audit detail kuesioner yang dibatasi khusus untuk mahasiswa pada program studi yang login (`where prodi_id = auth()->user()->prodi_id`).
  - Tab 3 secara dinamis menampilkan nama Program Studi pada heading (*Kuesioner Program Studi: [Nama Prodi]*).
- **Implementasi Stakeholder Baru: Admin Fakultas ([DashboardController.php](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/AdminFakultas/Dashboard/DashboardController.php), [DaftarAlumniFakultasController.php](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/AdminFakultas/KelolaAlumni/DaftarAlumniFakultasController.php), [DetailAlumniFakultasController.php](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/AdminFakultas/KelolaAlumni/DetailAlumniFakultasController.php))**:
  - Menambahkan kolom `fakultas_id` pada tabel `users` dan relasi `fakultas()` pada model `User`.
  - Membuat akun seeder untuk 7 Fakultas UKDW (`admin_fti`, `admin_fb`, `admin_fad`, `admin_biotek`, `admin_fk`, `admin_theologi`, `admin_fkh`).
  - Menyediakan modul lengkap Admin Fakultas:
    - `Sidebar.vue` & `Dashboard.vue`: Menampilkan metrik partisipasi tracer study seluruh prodi di fakultas tersebut.
    - `Alumni/Index.vue`: Direktori data alumni fakultas dengan dropdown filter program studi yang ada di fakultas tersebut.
    - `Alumni/Show.vue`: Detail profil dan audit kuesioner 3-tab untuk alumni fakultas.
- **Pemisahan Kontroler & Komponen Vue per Stakeholder**:
  - Seluruh kontroler dan file Vue dipisahkan secara rapi per folder stakeholder (`SuperAdmin`, `AdminBiroTiga`, `AdminProdi`, `AdminFakultas`) untuk kemudahan pemeliharaan (*clean architecture* & *maintainability*).
- **Quality Assurance**:
  - Seluruh routing terdaftar dan terverifikasi.
  - Kompilasi Vite production berhasil 100% (631 modul terkompilasi bersih).
  - Standarisasi kode PHP tervalidasi dengan Laravel Pint.

- **Stabilitas Tata Letak Bebas Pergeseran (*Layout Stays in Place*) ([Show.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/SuperAdmin/Alumni/Show.vue))**:
  - Menghilangkan *negative margin* (`-mt-10`) dan tumpang tindih kontainer yang sebelumnya memicu efek ketarik (*pull/drag elastic bouncing*).
  - Menstandarkan tinggi *header solid* dengan border pemisah bawah yang rapi serta padding halaman utama statis dan teratur (`py-6 px-4 sm:px-6 lg:px-8`).
- **Konsistensi Ukuran & Lebar Form Profil (Tab 1: Detail Profile) ([Show.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/SuperAdmin/Alumni/Show.vue))**:
  - Menghapus pembungkus kartu ganda (*double padding wrapper*) di sekeliling komponen form anak (`FormPribadi`, `FormAkademik`, `FormOrangTua`, `FormKarier`).
  - Menghasilkan keseragaman ukuran kartu 100% simetris, presisi, dan proporsional di semua bagian sub-tab profil tanpa nested margin.
- **Filter Bagian Berbasis Dropdown Dinamis & Nama Seksi Langsung ([Show.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/SuperAdmin/Alumni/Show.vue))**:
  - Mengganti deretan tombol seksi horizontal yang menumpuk dengan komponen **Dropdown `<select>` modern**.
  - Menampilkan **nama seksi/bagian secara langsung** (misal: *"Kondisi Kerja Saat Ini"*, *"Mendapatkan Pekerjaan"*, dsb.) baik pada Kuesioner Universitas maupun Kuesioner Program Studi.
  - Menghilangkan label teknis mentah (*"Seksi 1: ..."*), menyelaraskan format kuesioner universitas sama seperti kuesioner prodi.
- **Desain Data Table Standar, Bersih & Profesional (Tanpa Warna Mencolok Berlebihan)**:
  - Membersihkan palet warna tabel: menggunakan header netral abu-abu terang (`bg-gray-50 text-gray-700 border-b border-gray-200`), baris data putih bersih standar (`bg-white hover:bg-gray-50/80 border-b border-gray-100`), dan tipografi hitam tegas yang mudah dibaca.
  - Header pemisah seksi menggunakan warna kuning lembut UKDW (`bg-amber-100/75 text-amber-950 font-bold`) dengan nama seksi langsung dan status kelengkapan.
  - Butir belum dijawab ditandai dengan badge status merah minimalis (`bg-rose-50 text-rose-700 border border-rose-200`) dan strip `-` merah lembut tanpa mewarnai seluruh baris tabel secara berlebihan.
  - Butir terjawab ditandai dengan badge hijau teratur (`bg-emerald-50 text-emerald-700 border border-emerald-200`) dan teks jawaban tebal.
- **Quality & Automated Testing**:
  - Seluruh 54 tests PHPUnit Feature & Unit lulus 100% (298 assertions).
  - Asset Vite frontend terkompilasi bersih (0 error).

## [2026-09-20] Redesain Detail Audit Alumni Super Admin (DataTables Excel-Style View, Urutan Tab Baru, Header Kuning & Highlight Merah)
- **Restrukturisasi Urutan Tab Detail Alumni ([Show.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/SuperAdmin/Alumni/Show.vue))**:
  - Menyusun urutan tab terpadu:
    1. **1. Detail Profile** (Urutan 1, default aktif dengan form data pribadi, akademik, orang tua, karier).
    2. **2. Kuesioner Universitas** (Urutan 2, audit butir instrumen dan jawaban tracer study universitas).
    3. **3. Kuesioner Program Studi: [Nama Prodi]** (Urutan 3, memuat nama program studi dinamis pada heading dan tab).
- **Tampilan Tabel Rapi ala Spreadsheet Excel (DataTables View)**:
  - Menyajikan butir kuesioner dalam struktur tabel data profesional (*No, Kode, Pertanyaan / Instrumen, Tipe Input, Sifat, Status, Jawaban Alumni*).
  - **Baris Header Seksi & Question Header**: Diberi latar **Kuning UKDW `#FDC700`** mencolok tanpa kolom jawaban (*span full width*).
  - **Baris Belum Dijawab**: Diberi highlight warna **Merah Lembut (`bg-rose-50 border-l-4 border-l-rose-500`)** dengan status badge merah jelas dan strip jawaban `-`.
  - **Baris Terjawab**: Diberi aksen hijau emerald (`border-l-4 border-l-emerald-600`) dengan badge hijau `Terjawab` dan teks respon tebal.
  - Dilengkapi fitur pencarian cepat (*Quick Search Input*) per tab dan filter seksi instan.
- **Perbaikan Format Export Excel / CSV ([DetailAlumniSuperAdminController.php](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/SuperAdmin/KelolaAlumni/DetailAlumniSuperAdminController.php))**:
  - Mengubah export menjadi berkas **CSV murni dengan UTF-8 BOM (`\xEF\xBB\xBF`)** sehingga saat dibuka dengan Microsoft Excel di Windows, file langsung terbuka rapi tanpa peringatan keamanan *"format and extension don't match"*.
- **Standardisasi Input Gaji / Take Home Pay ([FormKarier.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/AlumniProfil/FormKarier.vue), [SimpanProfilController.php](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/Alumni/Profil/SimpanProfilController.php), [KuesionerController.php](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/Alumni/Kuesioner/KuesionerController.php))**:
  - Mengimplementasikan pola **`computed getter & setter` (Single Source of Truth)** pada form input gaji: nilai pada objek form, teks format ribuan di textbox (`new Intl.NumberFormat('id-ID')`), dan label `Terbaca: Rp ...` terhubung secara reaktif 100% dari satu variabel tanpa desinkronisasi.
  - Menghapus seluruh manipulasi atau pengali sembunyi-sembunyi (`* 1000`) di frontend dan di seluruh kontroler backend.
  - **Validasi Nominal Minimal Ribuan (Rp 1.000)**:
    - Input di bawah Rp 1.000 (misal: `709` atau `899`) langsung ditandai dengan border merah dan peringatan *minimal Rp 1.000*.
    - Proses submit dicegah baik di frontend (SweetAlert peringatan) maupun di backend ([SimpanProfilController.php](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/Alumni/Profil/SimpanProfilController.php)) dengan pesan kesalahan validasi yang jelas.
- **Penyederhanaan Visual Indikator Tab Navigasi Profil ([Index.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Profil/Index.vue))**:
  - Menghilangkan teks hitungan (*"1 belum"*, *"X belum"*) serta karakter ASCII mentah (`✓` / `✕`) pada tab navigasi.
  - Menggantinya dengan indikator visual langsung yang elegan:
    - **Data Lengkap**: Badge lingkaran hijau dengan ikon SVG centang mikro presisi.
    - **Data Belum Lengkap**: Titik status (*dot indicator*) rose/amber minimalis tanpa teks mengganggu.
- **Relokasi Peringatan "Ada Perubahan Belum Disimpan" ke Top Notification Banner ([Index.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Profil/Index.vue))**:
  - Memindahkan peringatan modifikasi data dari bagian bawah formulir ke **Banner Notifikasi Mengambang di Bagian Atas Formulir (tepat di bawah tab navigasi)**.
  - Dilengkapi tombol aksi cepat **"Simpan Sekarang"** langsung di dalam banner notifikasi.
- **Quality & Automated Tests**:
  - 54 tests PHPUnit Feature & Unit lulus 100% (299 assertions).
  - Format kode PHP tervalidasi bersih dengan Laravel Pint.
  - Bundle frontend terkompilasi optimal tanpa error/warning melalui Vite.
- **Pembersihan Seluruh Emoticon & Emoji ([FormPribadi.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/AlumniProfil/FormPribadi.vue), [FormKarier.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/AlumniProfil/FormKarier.vue), [FormOrangTua.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/AlumniProfil/FormOrangTua.vue), [Index.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Profil/Index.vue))**:
  - Menghapus seluruh ikon emoji/emoticon (🏢, 🚀, 🎓, ⚡, 💡) di seluruh form profil untuk menciptakan antarmuka yang bersih, minimalis, dan profesional.
- **Color-Coded Status Tanpa Text Badges**:
  - Menghapus seluruh badge teks checklist/pil status (`✓ Terisi`, `Belum diisi`, `✓ URL Valid`, `✓ 16 Digit`, `✓ Terpilih`, `Belum dipilih`, dsb.).
  - Mengandalkan indikator warna border dan background input secara menyeluruh pada semua kolom (baik wajib maupun opsional):
    - **Belum Diisi / Format Tidak Valid**: Border dan latar rose/merah lembut (`border-rose-300 bg-rose-50/20 text-gray-900 focus:border-rose-500`).
    - **Sudah Terisi / Format Valid**: Border dan latar emerald/hijau (`border-emerald-300 bg-white text-gray-900 focus:border-[#005B3C]`).
- **Perbaikan Format Input Gaji & Multiple Number (Format Minimal Ribuan Adaptif) ([FormKarier.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/AlumniProfil/FormKarier.vue), [KartuPertanyaan.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Components/Kuesioner/KartuPertanyaan.vue))**:
  - Saat alumni pertama kali mengetik angka satuan (misal: `5`), kolom textbox langsung melengkapi akhiran ribuan menjadi `5.000` (disimpan `5000` dan terbaca `Rp 5.000 / bulan`).
  - Nilai di textbox tetap fleksibel dapat diedit dan di-*adjust* ke nominal berapapun (misal `5000000` menjadi `5.000.000` dan terbaca `Rp 5.000.000 / bulan`) dengan sinkronisasi 100% presisi antara input dan output.
- **Filter Lokasi Wilayah Sebelum Memilih Perusahaan ([FormKarier.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/AlumniProfil/FormKarier.vue))**:
  - Pilihan wilayah (Negara untuk Luar Negeri; Provinsi dan Kabupaten/Kota untuk Dalam Negeri) kini dapat dipilih langsung di bagian atas untuk memfilter daftar perusahaan pada wilayah tersebut.
  - Jika tidak ada perusahaan yang terdaftar pada kota/negara yang dipilih, sistem menampilkan status *"Data perusahaan tidak ditemukan pada wilayah/filter yang dipilih."* disertai tombol pintas *"+ Tambah Perusahaan Baru"*.
  - Menambahkan tombol *"Reset Provinsi"* / *"Reset Kota"* / *"Reset Negara"* untuk mempermudah pencarian multi-wilayah.
- **Penyelarasan Tata Letak Simetris Pertanyaan Lamaran Kerja F6, F7, F7A ([Kuesioner.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Kuesioner.vue), [KartuPertanyaan.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Components/Kuesioner/KartuPertanyaan.vue))**:
  - Mengelompokkan trio butir pertanyaan kuantitas lamaran kerja (F6, F7, dan F7A) ke dalam **Grid 3 Kolom Sejajar** (`grid grid-cols-1 md:grid-cols-3 items-stretch`).
  - Menyelaraskan tinggi kartu (`h-full flex flex-col justify-between`), proporsi tipografi judul soal, dan posisi tombol *stepper number* di bagian bawah secara horizontal sejajar.
- **Quality & Verification**:
  - 53 tests PHPUnit lulus 100% (297 assertions).
  - Laravel Pint clean.
  - Vite build sukses (0 error, 0 warning).

## [2026-09-20] Redesain Profesional Profil Alumni, Validasi NPWP Regulasi PMK 112/2022, Single-Choice Role Switcher, & Standardisasi Kuesioner Prodi
- **Redesain Profesional & Eksekutif Profil Alumni ([Index.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Profil/Index.vue), [FormPribadi.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/AlumniProfil/FormPribadi.vue), [FormKarier.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/AlumniProfil/FormKarier.vue), [FormOrangTua.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/AlumniProfil/FormOrangTua.vue))**:
  - Menghapus banner pill merah/pink besar di atas form, menggantikannya dengan **indikator kelengkapan langsung pada kolom isian (*field-level indicators*)**.
  - Kolom wajib yang belum terisi ditandai dengan border lembut warna rose/merah dan badge status "Belum diisi / Wajib diisi".
  - Kolom yang telah terisi atau valid ditandai dengan border emerald dan badge status checklist "✓ Terisi / Valid" selaras palet warna resmi UKDW `#005B3C` dan `#FFD700`.
  - Tab navigasi menampilkan status ringkas dan presisi: badge `✓` emas/hijau untuk bagian lengkap, dan badge `✕` untuk bagian yang belum lengkap tanpa animasi berkedip.
- **Validasi NPWP Sesuai Regulasi Terbaru (UU HPP & PMK No. 112/PMK.03/2022)**:
  - Membatasi input NPWP khusus angka dengan panjang **15 atau 16 digit** sesuai aturan terbaru integrasi NIK sebagai NPWP Orang Pribadi.
  - Menyediakan tombol cepat **"⚡ Gunakan NIK (16 Digit)"** untuk menyalin 16 digit NIK secara otomatis ke kolom NPWP.
  - Memperketat evaluasi kelengkapan tab: Tab Identitas & Alamat tidak akan bertanda `✓` jika NPWP belum diisi atau formatnya belum valid (15/16 digit).
- **Pemilihan Peran & Aktivitas Eksklusif (*Single Choice Role Switcher*)**:
  - Pilihan kategori peran pada form karier (`Pekerja / Karyawan`, `Wirausaha / Founder`, dan `Melanjutkan Pendidikan`) bersifat eksklusif (*single choice*).
  - Saat alumni beralih peran (misalnya ke *Melanjutkan Pendidikan*), seluruh data perusahaan, atasan, posisi jabatan, dan gaji pada kategori lain secara otomatis di-reset menjadi `null` agar basis data tetap bersih dan tidak menyimpan data usang.
  - Kartu isian data perusahaan dan data atasan disesuaikan secara dinamis dan tidak diwajibkan untuk kategori studi lanjut.
- **Standardisasi & Validasi Format Kontak & Media Sosial**:
  - Validasi email wajib mengandung karakter `@` dan nama domain valid.
  - Validasi nomor telepon/WhatsApp dibatasi format 10–15 digit angka (dengan dukungan tanda `+`).
  - Standardisasi placeholder dan pembersihan format URL media sosial: LinkedIn URL (`https://linkedin.com/in/...`), LinkedIn Username (`username_linkedin`), Instagram URL (`https://instagram.com/...`), dan Facebook URL (`https://facebook.com/...`).
- **Standardisasi Tampilan Kuesioner Prodi ([KuesionerProdi.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/KuesionerProdi.vue), [ProdiQuestionnaireSeeder.php](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/database/seeders/ProdiQuestionnaireSeeder.php))**:
  - Menyamakan sistem tombol "Lanjut" & "Kembali" serta navigasi stepper dengan Kuesioner Universitas (`Kuesioner.vue`): menggunakan FAB *floating action button* melayang di desktop dan *bottom navigation bar* di mobile.
  - Menyamakan lebar kontainer formulir menjadi `max-w-6xl xl:max-w-7xl`.
  - Mengoreksi 4 butir instrumen Prodi Sistem Informasi yang sebelumnya keliru terdaftar sebagai pertanyaan isian terbuka menjadi header deskripsi section.
- **Automated Tests & Quality**:
  - Seluruh 53 tests PHPUnit Feature & Unit lulus 100% (297 assertions).
  - Pemformatan kode PHP bersih dengan Laravel Pint (`vendor/bin/pint --format agent`).
  - Vite build selesai sukses tanpa warning/error.

## [2026-09-20] Navigasi Sidebar Terpadu Super Admin (Menggantikan Navigasi Atas)
- **Desain & Implementasi Sidebar Terpadu Super Admin ([Sidebar.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/SuperAdmin/Components/Sidebar.vue))**:
  - Mengubah sistem navigasi modul Super Admin dari navigasi atas (*top navigation bar*) menjadi **Sidebar** navigasi tetap (*fixed desktop sidebar* `w-72`) di sisi kiri layar.
  - Sidebar dilengkapi dengan:
    - **Header & Branding**: Logo resmi UKDW, judul "Tracer Study UKDW", dan badge indikator "Super Admin".
    - **Navigasi Menu Utama**: Link menu terstruktur dengan ikon representatif, status aktif warna hijau resmi UKDW `#0D542B` dengan aksen kuning `#FDC700` (Dashboard, Kelola Kuesioner, Kelola Section, dan Data Alumni).
    - **Pintasan Publik**: Tombol pintas cepat "Lihat Beranda Publik" (`/`).
    - **Profil Pengguna & Logout Bawah**: Kartu identitas Super Admin dengan avatar inisial, nama, email, serta tombol keluar (*logout*) dengan modal konfirmasi SweetAlert2.
    - **Responsivitas Mobile / Tablet**: Topbar ringkas khusus mobile dengan tombol hamburger yang membuka *slide-over drawer* berserta *backdrop overlay* interaktif.
- **Integrasi Penuh di Seluruh Halaman Super Admin**:
  - [Dashboard.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/SuperAdmin/Dashboard.vue)
  - [Pertanyaan/Index.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/SuperAdmin/Pertanyaan/Index.vue)
  - [Section/Index.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/SuperAdmin/Section/Index.vue)
  - [Alumni/Index.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/SuperAdmin/Alumni/Index.vue)
  - [Alumni/Show.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/SuperAdmin/Alumni/Show.vue)
- **Pembersihan Kode Usang**:
  - Menghapus komponen navigasi atas lama `resources/js/Pages/SuperAdmin/Components/Navbar.vue` yang sudah tidak digunakan.
- **Automated Tests & Asset Build**:
  - Seluruh 48 tests PHPUnit Feature & Unit lulus 100% (256 assertions).
  - Vite build selesai sukses (4.91s).

## [2026-09-20] Seeding Komprehensif Perusahaan (15 Kolom), Sinkronisasi & Standardisasi Studi Lanjut F18, Format Skala Langsung F17, dan Database-Driven Jump Logic
- **Seeding Komprehensif Master Data Perusahaan 15 Kolom ([PerusahaanSeeder.php](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/database/seeders/PerusahaanSeeder.php))**:
  - Mengisi seluruh 15 atribut tabel `perusahaan` secara lengkap dan valid: `id`, `nama_perusahaan`, `propinsi_id`, `kabupaten_id`, `alamat`, `kota`, `provinsi`, `negara`, `jenis_lokasi` (Dalam Negeri / Luar Negeri), `jenis_perusahaan`, `jenis_perusahaan_lainnya`, `skala`, `kode_pos`, `status_verifikasi` (`verified`), serta timestamps.
  - Memastikan integritas relasi foreign key dengan `provinsi` dan `kabupaten_kota` serta mencakup ragam sektor (BUMN, Startup/Swasta, Instansi Pemerintah, Multilateral, hingga Perusahaan Luar Negeri).
- **Sinkronisasi Dua Arah & Standardisasi Studi Lanjut F18 (`F18a` s.d. `F18d`)**:
  - Menghubungkan profil studi lanjut alumni (`pendidikan_tingkat`, `perguruan_tinggi`, `pendidikan_prodi`) di [FormKarier.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/AlumniProfil/FormKarier.vue) dengan pertanyaan kuesioner `F18b` dan `F18c` via [KuesionerSyncService.php](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Services/Kuesioner/KuesionerSyncService.php) dan [KuesionerController.php](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/Alumni/Kuesioner/KuesionerController.php).
  - Merapikan tata letak tampilan kartu `F18` di [Kuesioner.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Kuesioner.vue):
    - `F18a` (Sumber Biaya): Kartu tersendiri (single card).
    - `F18b` (Perguruan Tinggi) & `F18c` (Program Studi): Ditata berdampingan rapi dalam 2-column grid.
    - `F18d` (Tanggal Masuk): Menggunakan input tanggal native HTML5 (`type="date"`).
  - Memperbarui database view `v_alumni_kuesioner_autofill` untuk mendukung pemetaan `BIO_PENDIDIKAN_TINGKAT`.
- **Format Skala Langsung F17 & Konsistensi UI Kuesioner**:
  - Mengubah header skala kompetensi pada [TabelF17.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Components/Kuesioner/TabelF17.vue) menjadi format langsung yang padat dan presisi: `Sangat Rendah 1 2 3 4 5 Sangat Tinggi`.
  - Menggunakan palet 2-warna resmi UKDW yang bersih: Hijau `#005B3C` (Kompetensi Saat Lulus) dan Kuning/Amber `#FDC700` (Kontribusi PT), serta menghapus redundansi indikator progres aspek.
  - Standardisasi tombol stepper jumlah (+ / -) pada [KartuPertanyaan.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Components/Kuesioner/KartuPertanyaan.vue) dengan warna netral `bg-gray-100` dan hover hijau UKDW.
- **Database-Driven Jump Logic**:
  - Logika percabangan/lompatan pertanyaan (`jump_to`) sepenuhnya digerakkan oleh metadata database dari `ref_subpertanyaan_detil` tanpa *hardcoded question ID* di frontend.
- **Pembaruan ERD & Pemetaan Instrumen Pertanyaan**:
  - Memperbarui kamus data tabel `perusahaan` dan `biodata` pada [ERD.md](ERD.md).
  - Melengkapi dokumen [DAFTAR_PERTANYAAN_MAPPING.md](DAFTAR_PERTANYAAN_MAPPING.md) dengan seluruh butir kuesioner standar Dikti 2021 dan relasi sinkronisasinya.
- **Automated Tests**:
  - 48 tests PHPUnit Feature & Unit lulus 100% (247 assertions).


- **Standarisasi Input Posisi / Jabatan Wiraswasta & Startup (F5C) Menjadi Dropdown `<select>`**:
  - Mengubah kontrol input `posisi_wiraswasta` (`F5C`) pada [FormKarier.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/AlumniProfil/FormKarier.vue) dari input teks + tombol pill cepat menjadi dropdown `<select>` standar yang konsisten dan rapi.
  - Opsi dropdown yang disediakan: `Owner`, `Founder`, `Co-Founder`, `Direktur Utama`, `Pengelola Usaha`, `Freelancer / Konsultan Mandiri`, serta opsi khusus `Lainnya`.
  - Jika alumni memilih `Lainnya`, sistem menampilkan kolom isian teks tambahan (`posisi_wiraswasta_lainnya`) yang langsung disinkronkan ke backend [SimpanProfilController.php](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/Alumni/Profil/SimpanProfilController.php) dan [KuesionerSyncService.php](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Services/Kuesioner/KuesionerSyncService.php).
- **Navigasi Utama Terpadu Alumni (Unified `Navbar.vue`)**:
  - Membuat komponen navigasi terpusat di [Navbar.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Components/Navbar.vue) untuk memastikan konsistensi tampilan (UI/UX), tata letak, dan branding resmi UKDW di seluruh modul alumni.
  - Diintegrasikan secara seragam pada 4 halaman utama alumni:
    1. **Dashboard Alumni** ([Dashboard.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Dashboard.vue))
    2. **Kelola Profil Alumni** ([Index.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Profil/Index.vue))
    3. **Kuesioner Tracer Study Universitas** ([Kuesioner.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Kuesioner.vue))
    4. **Kuesioner Khusus Program Studi** ([KuesionerProdi.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/KuesionerProdi.vue))
  - Fitur Navbar: Logo resmi UKDW, indikator badge aktif antar halaman (Dashboard, Profil, Kuesioner Universitas, Kuesioner Prodi), nama/NIM alumni aktif, sub-navigasi mobile/tablet responsif, dan modal konfirmasi SweetAlert2 saat keluar (logout).
- **Dokumentasi Pemetaan Lengkap Instrumen Kuesioner ([DAFTAR_PERTANYAAN_MAPPING.md](DAFTAR_PERTANYAAN_MAPPING.md))**:
  - Menyusun tabel referensi komprehensif yang memetakan seluruh butir pertanyaan Tracer Study Dikti (`F1` s/d `F18`, `F24A`, `F24B`, dll.), identitas alumni, dan kuesioner program studi.
  - Merinci relasi kode pertanyaan, tipe input, komponen Vue pengelola, tabel dan kolom basis data tujuan, hingga alur sinkronisasi dua arah otomatis (`KuesionerSyncService.php`).
- **Pembaruan Dokumentasi Utama**:
  - Memperbarui [README.md](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/README.md) dengan panduan arsitektur instrumen tracer study, daftar komponen form Vue, dan alur navigasi terpadu.
- **Automated Tests**:
  - Seluruh 47 feature tests PHPUnit lulus 100% (243 assertions).

## [2026-09-19] F11 Jenis Perusahaan, Pemisahan Peran Pekerja vs Wiraswasta (F2G & F5C), dan Export Excel Jawaban Alumni
- **Integrasi F11 Jenis Instansi/Perusahaan (`perusahaan.jenis_perusahaan` & `jenis_perusahaan_lainnya`)**:
  - Menambahkan kolom `jenis_perusahaan` (50) dan `jenis_perusahaan_lainnya` (150) pada tabel `perusahaan` via migrasi `2026_09_19_224000_add_jenis_perusahaan_and_kategori_to_perusahaan_and_biodata.php`.
  - Mengupdate model [Perusahaan.php](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Models/Perusahaan.php) dan `SimpanProfilController.php`.
  - Menambahkan dropdown pilihan standar Dikti di [FormKarier.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/AlumniProfil/FormKarier.vue) dan modal "Tambah Perusahaan Baru": `1 - Instansi pemerintah`, `2 - Organisasi non-profit/LSM`, `3 - Perusahaan swasta`, `4 - Wiraswasta/perusahaan sendiri`, `5 - Lainnya` (dilengkapi input isian teks spesifik), `6 - BUMN/BUMD`, `7 - Institusi/Organisasi Multilateral`.
  - Otomatis disinkronkan ke butir kuesioner `F11` pada tabel `tracer` via [KuesionerSyncService.php](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Services/Kuesioner/KuesionerSyncService.php).
- **Pemisahan Peran Kerja & Posisi Jabatan: Pekerja vs Wiraswasta (`F2G` vs `F5C`)**:
  - Menambahkan kolom `kategori_pekerjaan` dan `posisi_wiraswasta` pada tabel `biodata`.
  - **Pemisahan Status Kerja & Status Studi Lanjut (Independen)**:
    - Status Pekerjaan Utama dibagi menjadi 2 opsi saling eksklusif (*Single Choice*): **🏢 Pekerja / Karyawan** vs **🚀 Wirausaha / Founder**.
    - Ditambahkan opsi toggle: **🎓 "Sedang Melanjutkan Studi (S1 / S2 / S3 / Profesi / Spesialis)"**.
    - Mengaktifkan atau menonaktifkan status studi lanjut **tidak lagi mereset ataupun menyembunyikan data perusahaan, jabatan, dan atasan langsung**.
  - **Conditional Rendering Detail Perusahaan (*Progressive Disclosure*)**:
    - Kolom detail perusahaan (*Negara/Provinsi/Kabupaten, Jenis Perusahaan F11, Skala F2H/F5D, Alamat Kantor, dan Kode Pos*) **hanya ditampilkan jika nama perusahaan sudah dipilih atau ditambahkan**.
    - Jika perusahaan belum dipilih, form menampilkan tampilan yang bersih dengan pesan panduan pencarian perusahaan.
  - Pada form profil [FormKarier.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/AlumniProfil/FormKarier.vue):
    - Menyediakan pemilih kategori peran kerja: **🏢 Pekerja / Karyawan / Profesional** vs **🚀 Wirausaha / Founder / Startup / Usaha Mandiri**.
    - Jika memilih **Pekerja**: Menampilkan dropdown posisi jabatan struktural `F2G` (`1 - Direksi`, `2 - Top Manager`, `3 - Middle Manager`, `4 - Low Manager`, `5 - Supervisor`, `6 - Staff`) dan mengosongkan `F5C`.
    - Jika memilih **Wiraswasta**: Menampilkan input teks posisi/jabatan wirausaha `F5C` dengan tombol pilihan cepat (*Owner, Founder, Co-Founder, Direktur Utama, Pengelola Usaha, Freelancer*), otomatis mengisi `F2G` sebagai level pimpinan, dan otomatis mengisi data atasan dengan data diri alumni.
- **Fitur Download Excel / CSV Jawaban Kuesioner per Alumni untuk Superadmin**:
  - Menambahkan method `exportExcel($id)` pada [DetailAlumniSuperAdminController.php](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/SuperAdmin/KelolaAlumni/DetailAlumniSuperAdminController.php) dan rute `GET /superadmin/alumni/{id}/export-excel`.
  - Menghasilkan file Excel/CSV stream terformat rapi dengan UTF-8 BOM (`\xEF\xBB\xBF`) berisi kolom: `No`, `Kategori / Section`, `Kode Pertanyaan`, `Pertanyaan`, `Tipe Pertanyaan`, `Status Wajib`, `Status Jawaban`, `Jawaban Alumni` (mencakup kuesioner universitas dan kuesioner khusus program studi).
  - Menambahkan tombol aksi **Download Excel Jawaban** berwarna kuning khas UKDW di header halaman detail alumni [Show.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/SuperAdmin/Alumni/Show.vue).
- **Automated Tests**:
  - Seluruh 47 feature tests PHPUnit lulus 100% (243 assertions).

## [2026-09-19] Anti-Duplikasi Jawaban Tracer, Perbaikan Unique Constraint Atasan, & Searchable Negara
- **Eliminasi Duplikasi Jawaban Tracer & Constraint Unik (`tracer_biodata_question_unique`)**:
  - Membuat migrasi `2026_09_19_221000_add_unique_constraints_to_tracer_table.php` yang membersihkan seluruh potensi baris ganda dan menambahkan indeks unik komposit `['biodata_id', 'question_id']` serta `['biodata_id', 'kode_pertanyaan']` pada tabel `tracer`.
  - Mengupdate [SimpanJawabanController.php](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/Alumni/Kuesioner/SimpanJawabanController.php) dan [KuesionerSyncService.php](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Services/Kuesioner/KuesionerSyncService.php) dengan mekanisme *clean-before-update* dan `updateOrCreate` berbasis `(biodata_id, question_id)`.
  - Ketika alumni menjawab pertanyaan yang sudah pernah dijawab, sistem secara otomatis melakukan pembaruan (*in-place update*) pada baris yang sama tanpa pernah menduplikasi data di basis data.
- **Penanganan Unique Constraint Atasan (`atasan.atasan_email_unique`)**:
  - Menghapus indeks `unique` pada kolom `email` di tabel `atasan` via migrasi `2026_09_19_215000_remove_unique_from_atasan_email.php` dan memperbarui migrasi dasar `create_atasans_table.php`.
  - Mengupdate logika `SimpanJawabanController.php` dan `SimpanProfilController.php` agar pembaruan data atasan tidak lagi memicu error duplicate key MySQL (1062) ketika email atasan digunakan bersama atau diisi data dummy.
- **Integrasi Master Data Negara Dunia (`ref_negara` & `daftar_negara_dunia.csv`)**:
  - Membuat tabel master `ref_negara` (`id`, `nama_negara`, `ibu_kota`, `kode_iso2`, `benua`, `timestamps`) via migrasi `2026_09_19_220000_create_ref_negara_table.php`.
  - Membuat model [RefNegara.php](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Models/RefNegara.php) dan seeder `RefNegaraSeeder.php` yang membaca data 193 negara dunia secara otomatis dari `daftar_negara_dunia.csv`.
  - Mengalirkan master data negara ke [ProfilController.php](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/Alumni/Profil/ProfilController.php) untuk kebutuhan dropdown & autocomplete form profil alumni.
  - **Searchable Dropdown Negara (Sesuai Template Provinsi & Kabupaten)** di [FormKarier.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/AlumniProfil/FormKarier.vue):
    - Komponen pencarian negara interaktif dengan ikon pencarian, filter real-time, highlight negara aktif, kode ISO 2, dan benua.
    - Opsi negara untuk **Luar Negeri** mengecualikan `Indonesia` sehingga negara asal tidak bisa dipilih sebagai negara asing.
    - Pilihan negara dibatasi secara ketat (*strict selection*) hanya pada 193 negara resmi yang terdaftar di basis data master `ref_negara` (tidak dapat menginput negara sembarangan).
    - Modal SweetAlert2 "Tambah Perusahaan Baru" juga menggunakan dropdown negara resmi yang mengecualikan Indonesia dan divalidasi ketat di backend `SimpanProfilController.php`.
- **Dukungan Perusahaan Dalam Negeri vs Luar Negeri (`perusahaan.jenis_lokasi` & `perusahaan.negara`)**:
  - Menambahkan kolom `jenis_lokasi` (`Dalam Negeri` / `Luar Negeri`) dan `negara` pada tabel `perusahaan` via migrasi `2026_09_19_214000_add_jenis_lokasi_and_negara_to_perusahaan_table.php`.
  - Memperbarui model [Perusahaan.php](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Models/Perusahaan.php) dan seeder `PerusahaanSeeder.php` dengan data contoh perusahaan internasional (Google Singapore, Grab Singapore, Rakuten Tokyo).
  - Pada form profil [FormKarier.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/AlumniProfil/FormKarier.vue):
    - Menyediakan tombol seleksi interaktif `🇮🇩 Dalam Negeri` dan `✈️ Luar Negeri`.
    - Jika memilih `Luar Negeri`, input Nama Negara (`company_negara`) ditampilkan, sedangkan dropdown Provinsi dan Kabupaten/Kota dinonaktifkan/disembunyikan serta dikosongkan (`null`).
    - Modal popup "Tambah Perusahaan Baru" (SweetAlert2) mendukung pemilihan lokasi Dalam/Luar Negeri dan input nama negara secara langsung.
  - Memperbarui [KuesionerSyncService.php](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Services/Kuesioner/KuesionerSyncService.php) agar alumni yang bekerja di luar negeri menghasilkan alamat perusahaan berformat nama negara, dan mengisi `F5a1`/`F5a2` secara adaptif.
- **Restrukturisasi Urutan Tab Profil Alumni (UX Streamlined Flow)**:
  - Mengubah urutan tab pada [Index.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Profil/Index.vue) agar alumni langsung diarahkan ke riwayat pekerjaan setelah mengisi data diri:
    1. **Identitas & Alamat** (`pribadi`)
    2. **Karier & Pekerjaan** (`karier`)
    3. **Akademik & Yudisium** (`akademik`)
    4. **Data Orang Tua** (`orangtua`)
- **Penyempurnaan UI Format Angka Ribuan & Pembersihan Emoticon**:
  - Menghilangkan emoticon `💰` pada teks pratinjau gaji di form karier dan kuesioner.
  - Menambahkan panduan instruksi format ribuan yang jelas dan mudah dipahami.
- **Automated Tests**:
  - Seluruh 44 feature tests PHPUnit lulus 100% (224 assertions).

## [2026-09-19] Integrasi RefFakultas, Pengayaan Kolom Biodata, Take Home Pay, Penguncian NIK, dan Auto-Fill Atasan Owner
- **Master Fakultas (`ref_fakultas`) & Relasi ke Program Studi (`prodi`)**:
  - Membuat tabel `ref_fakultas` (`id`, `kode_fakultas`, `nama_fakultas`, `timestamps`).
  - Menambahkan kolom `fakultas_id` (FK ke `ref_fakultas`) pada tabel `prodi`.
  - Membuat model [RefFakultas.php](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Models/RefFakultas.php) dan memperbarui relasi di [Prodi.php](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Models/Prodi.php).
  - Membuat seeder `RefFakultasSeeder.php` yang mengisi 7 fakultas resmi UKDW dan memetakan `fakultas_id` pada seluruh `prodi` secara otomatis berdasarkan digit pertama `kode_prodi` (Kode 1: Bisnis, 2: Arsitektur & Desain, 3: Theologi, 4: Bioteknologi, 6: Kedokteran, 7: FTI, 8: FKH).
- **Pengayaan Kolom Tabel `biodata` & Relasi Langsung**:
  - Menambahkan kolom `tempat_lahir`, `tanggal_lahir`, `jenis_kelamin`, `golongan_darah`, `warga_negara`, `nisn`, `gaji`, serta foreign key `orang_tua_id` dan `yudisium_id` pada tabel `biodata`.
  - Memperbarui model [Biodata.php](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Models/Biodata.php) dengan deklarasi `$fillable` lengkap dan relasi `orangTua()`, `yudisium()`, serta `prodi.fakultas`.
- **Penguncian NIK Alumni (Read-Only)**:
  - NIK alumni diatur permanen (read-only/disabled) pada form frontend [FormPribadi.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/AlumniProfil/FormPribadi.vue) dan dilindungi dari perubahan di backend [SimpanProfilController.php](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/Alumni/Profil/SimpanProfilController.php).
- **Perluasan Posisi Jabatan & Auto-Fill Data Atasan Owner**:
  - Menambahkan opsi jabatan baru di [FormKarier.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/AlumniProfil/FormKarier.vue): `Owner`, `Founder`, `Wiraswasta / Wirausaha`.
  - Jika alumni memilih posisi `Owner` / `Founder` / `Wiraswasta`, kolom Data Atasan Langsung (`nama_atasan`, `email_atasan`, `telepon_atasan`) otomatis terisi dengan data diri alumni (nama, email, nomor telepon) baik di sisi antarmuka (Vue watcher) maupun di backend Laravel.
- **Penyederhanaan Take Home Pay (`F505`) & Eliminasi Redundansi Kuesioner Section 4**:
  - Menyediakan input single Take Home Pay bulanan (`gaji`) pada form profil [FormKarier.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/AlumniProfil/FormKarier.vue) dan kuesioner [KartuPertanyaan.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Components/Kuesioner/KartuPertanyaan.vue).
  - Format ribuan (`.000`) muncul langsung di dalam kotak input teks dan dapat diedit fleksibel, menghapus badge terpisah `.000` dan tombol preset pilihan cepat agar tampilan bersih.
  - Memperbarui perhitungan pratinjau `💰 Terbaca: Rp ... / bulan` secara real-time saat alumni mulai mengetik angka tanpa harus menunggu form disimpan.
  - Memperbarui [KuesionerSyncService.php](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Services/Kuesioner/KuesionerSyncService.php) agar Take Home Pay dan seluruh data pekerjaan/atasan di Section 4 (`F5a1`, `F5a2`, `F510`, `F5B`, `F5C`, `F5D`, `F2E`, `F2E1`, `F2E2`, `F2E3`, `F2F`, `F2G`, `F2H`) otomatis tersinkronisasi dari profil biodata ke tabel `tracer`.
- **Automated Tests**:
  - Membuat feature test [AlumniProfileEnhancementTest.php](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/tests/Feature/AlumniProfileEnhancementTest.php).
  - Seluruh 43 automated feature tests di PHPUnit lulus 100% (222 assertions).
- **Singularisasi Nama Tabel Basis Data Custom (Tanpa Akhiran `-s`)**:
  - Seluruh 27 berkas migrasi database diselaraskan menggunakan nama tabel tunggal/singular (Bahasa Indonesia & domain), tanpa akhiran `-s`, kecuali tabel bawaan Laravel framework (`users`, `cache`, `sessions`, `jobs`):
    - `biodatas` $\rightarrow$ `biodata`
    - `companies` $\rightarrow$ `perusahaan`
    - `provinces` $\rightarrow$ `propinsi`
    - `kabupatens` $\rightarrow$ `kabupaten`
    - `atasans` $\rightarrow$ `atasan`
    - `tracers` $\rightarrow$ `tracer`
    - `kuesioners` $\rightarrow$ `kuesioner`
    - `kelompok_pertanyaans` $\rightarrow$ `kelompok_pertanyaan`
    - `umps` $\rightarrow$ `ump`
    - `data_akademiks` $\rightarrow$ `data_akademik`
    - `data_orang_tuas` $\rightarrow$ `data_orang_tua`
    - `yudisiums` $\rightarrow$ `yudisium`
    - `prodis` $\rightarrow$ `prodi`
    - `prodi_question_sections` $\rightarrow$ `prodi_question_section`
    - `prodi_questions` $\rightarrow$ `prodi_question`
    - `prodi_question_options` $\rightarrow$ `prodi_question_option`
    - `prodi_responses` $\rightarrow$ `prodi_response`
    - `question_mappings` & database VIEW `v_question_mappings`
  - Seluruh model Eloquent dikonfigurasi dengan deklarasi eksplisit `protected $table = 'nama_tabel_singular';`.
  - Seluruh Foreign Key diselaraskan (`perusahaan_id`, `propinsi_id`, `kabupaten_id`, `biodata_id`, `kuesioner_id`, `kelompok_pertanyaan_id`, `prodi_question_section_id`, `prodi_question_id`, dll.).
- **Refactoring Penuh `Company` $\rightarrow$ `Perusahaan`**:
  - Membuat model [Perusahaan.php](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Models/Perusahaan.php) dan menghapus `Company.php` (tanpa menggunakan class extend/wrapper legacy).
  - Mengganti seluruh seeder (`PerusahaanSeeder.php`), controller (`SimpanProfilController`, `ProfilController`, `DetailAlumniSuperAdminController`, `DaftarAlumniController`, `DetailAlumniController`, `SimpanJawabanController`), services (`KuesionerSyncService`), relasi, dan tests.
- **Refactoring Penuh `Province` $\rightarrow$ `Propinsi`**:
  - Membuat model [Propinsi.php](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Models/Propinsi.php) dan menghapus `Province.php`.
  - Memperbarui relasi `Kabupaten`, `Perusahaan`, `Ump`, dan `Biodata`.
  - Menyediakan accessor compatibility (`province_id` $\rightarrow$ `propinsi_id`) untuk keandalan frontend.
- **Pembersihan Bersih `Alumni` $\rightarrow$ `Biodata`**:
  - Menghapus berkas `database/seeders/AlumniSeeder.php` yang redundan.
  - Memastikan seluruh controller, middleware (`HandleInertiaRequests`), services, seeder, dan test suite merujuk ke entitas `Biodata`.
- **Pengujian & Verifikasi Kualitas Kode**:
  - `php artisan migrate:fresh --seed` berjalan sukses 100% tanpa error.
  - Seluruh 38 skenario automated unit & feature tests di PHPUnit lulus 100% (203 assertions).
  - Standarisasi format kode PHP diformat dengan Laravel Pint (`vendor/bin/pint --format agent`).
  - Pembuatan bundle asset frontend Vite (`npm run build`) sukses tanpa error.
  - Pembaruan dokumen [ERD.md](ERD.md) dan [README.md](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/README.md).

## [2026-09-16] Refactoring Penuh Entitas Alumni Menjadi Biodata & Penyesuaian Kolom Seeding
- **Refactor Entitas `Alumni` $\rightarrow$ `Biodata` Tanpa Kehilangan Data & Foreign Key**:
  - Model `App\Models\Alumni` direfaktor menjadi `App\Models\Biodata` ([Biodata.php](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Models/Biodata.php)).
  - Tabel `alumnis` direfaktor menjadi `biodatas` yang memuat seluruh 28 kolom profil target (`id`, `user_id`, `nim`, `tahun_lulus`, `kode_prodi`, `nama`, `nomor_telepon`, `email`, `alamat`, `kabupaten_id`, `provinsi_id`, `kelurahan`, `kecamatan`, `kode_pos`, `agama`, `email_pribadi`, `nik`, `no_kk`, `no_bpjs`, `npwp`, `instagram_url`, `facebook_url`, `linkedin_url`, `linkedin_username`, `expert`, `minat`) serta seluruh kolom karier/pekerjaan existing (`company_id`, `atasan_id`, `posisi_jabatan`, `jenis_pekerjaan`, `zipcode`).
  - Seluruh relasi Foreign Key diselaraskan: `tracers.biodata_id` dan `prodi_responses.biodata_id`.
  - Alias `$user->alumni()` tetap disediakan pada [User.php](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Models/User.php) untuk backward compatibility.
- **Penyelarasan Kolom Seeding & Database Migrations**:
  - `DataAkademikSeeder.php`: diselaraskan dengan kolom `ip_kumulatif`, `status_mahasiswa = 'AR'`, kolom asal sekolah (`asal_sekolah`, `alamat_asal_sekolah`, `kota_kabupaten_asal_sekolah`, `provinsi_asal_sekolah`, `jurusan_asal_sekolah`), dan penghapusan `npwp` (karena dipindahkan ke `biodatas`).
  - `YudisiumSeeder.php`: diselaraskan dengan kolom `tahun_akademik_lulus` dan `tahun_lulus`.
  - `BiodataSeeder.php`: dibuat untuk melakukan seeding lengkap ke tabel `biodatas` mencakup seluruh data 28 field profil dan relasi ke perusahaan serta atasan.
  - `DatabaseSeeder.php` dan `QuestionMappingSeeder.php`: diselaraskan menggunakan `BiodataSeeder` dan `Biodata::all()`.
  - Database VIEW `v_question_mappings` diselaraskan memetakan butir pertanyaan ke tabel `biodatas` dan `yudisiums`.
- **Dokumentasi Terpusat Entity Relationship Diagram (ERD)**:
  - Membuat file terdedikasi [ERD.md](ERD.md) yang memuat visualisasi diagram Mermaid `erDiagram`, kamus data komprehensif (*data dictionary*) untuk seluruh entitas (Autentikasi, Wilayah Master, Biodata/Akademik, Kuesioner Universitas, Kuesioner Prodi, Database Views), dan tabel matriks kardinalitas relasi antar entitas.
- **Verifikasi & Pengujian Otomatis**:
  - Eksekusi migrasi & seeding ulang `php artisan migrate:fresh --seed` berjalan sukses 100% tanpa error.
  - Seluruh 38 skenario automated unit & feature tests di `vendor/bin/phpunit` lulus 100% (203 assertions).
  - Standarisasi format kode PHP diperbarui via Laravel Pint (`vendor/bin/pint --format agent`).

## [2026-09-16] Penyelarasan Tipe Data Gaji F505 (multiple_number) & Standarisasi Template Input Kuesioner Lengkap di Vue
- **Standarisasi Tipe Data Pertanyaan Gaji / Take Home Pay (`F505`)**:
  - Pertanyaan `F505` (*"Berapa rata-rata pendapatan anda per bulan ? (take home pay)?"*) pada [RefSubpertanyaan2021Seeder.php](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/database/seeders/RefSubpertanyaan2021Seeder.php) diselaraskan ke tipe `multiple_number`.
  - Menambahkan 3 butir rincian opsi gaji pada [RefSubpertanyaanDetilSeeder.php](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/database/seeders/RefSubpertanyaanDetilSeeder.php):
    1. `F5051` (Urutan 1): *"Dari Pekerjaan Utama"*
    2. `F5052` (Urutan 2): *"Dari Lembur dan Tips"*
    3. `F5053` (Urutan 3): *"Dari Pekerjaan Lainnya"*
  - Backend [SimpanJawabanController.php](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/Alumni/Kuesioner/SimpanJawabanController.php) dan [KuesionerController.php](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/Alumni/Kuesioner/KuesionerController.php) secara otomatis menghitung total Take Home Pay, mengonversi satuan ribuan (`.000`), menyimpan struktur JSON ke kolom `answer_json`, dan menormalisasi angka saat formulir dimuat kembali.
- **Penyelarasan Template Input Form Kuesioner Lengkap di Vue ([KartuPertanyaan.vue](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Components/Kuesioner/KartuPertanyaan.vue))**:
  - Melengkapi seluruh template input form kuesioner siap pakai untuk semua tipe data Google Forms:
    1. `text`: Isian singkat 1 baris.
    2. `textarea`: Paragraf / isian multibaris.
    3. `number`: Isian angka (termasuk layout 2-kolom berdampingan untuk F6 & F7).
    4. `single_choice` / `radio`: Pilihan ganda radio button klasik (dengan dukungan opsi "Lainnya").
    5. `radio_input`: Pilihan ganda radio dengan isian angka langsung pada opsi (seperti F3 & F5).
    6. `radio_text`: Pilihan ganda radio dengan isian teks tambahan.
    7. `multiple_choice` / `checkbox`: Kotak centang pilihan majemuk.
    8. `dropdown`: Menu pilihan tarik-turun (`<select>`).
    9. `searchable_select`: Dropdown pencarian interaktif untuk master data besar.
    10. `rating_5`: Skala linier rating 1 s/d 5 (Sangat Rendah s/d Sangat Tinggi).
    11. `multiple_number`: Isian rincian gaji / angka majemuk dengan akhiran `.000` dan kalkulasi total otomatis.
    12. `date`: Input pemilihan tanggal.
    13. `time`: Input pemilihan waktu/jam.
    14. `file`: Area unggah berkas dengan feedback visual nama file.
- **Komentar Kode & Dokumentasi Terstruktur**:
  - Setiap konstanta, fungsi, template div HTML, dan prop Vue telah dilengkapi komentar penjelasan terstruktur dalam Bahasa Indonesia.
  - Seluruh 38 automated test cases di `tests/Feature` berjalan sukses 100% (206 assertions).

## [2026-09-15] Refactoring Penuh Model & Entitas Kuesioner (Penghapusan Model Wrapper Legacy)
- **Migrasi Total Seluruh Model & Referensi Kode**:
  - Seluruh wrapper model legacy (`Question`, `Questionnaire`, `QuestionSection`, `QuestionOption`, `Response`) serta legacy seeder (`QuestionSeeder`, `QuestionnaireSeeder`, `QuestionSectionSeeder`, `QuestionOptionSeeder`) telah dihapus secara permanen dari basis kode.
  - Seluruh Controller, Service, Relasi Eloquent, Inertia Props, Komponen Vue, dan Automated Feature Tests kini 100% menggunakan model dan tabel kanonikal:
    1. `Kuesioner` (`kuesioners`): Header kuesioner tracer study universitas.
    2. `KelompokPertanyaan` (`kelompok_pertanyaans`): Bagian/seksi kelompok pertanyaan.
    3. `RefSubpertanyaan2021` (`ref_subpertanyaan2021`): Butir pertanyaan tracer study 2021 (`kode_pertanyaan`, `subpertanyaan`, `wajib`, `order`, `type`).
    4. `RefSubpertanyaanDetil` (`ref_subpertanyaan_detil`): Pilihan opsi jawaban & target alur lompatan branching (`kode_opsi`, `option_text`, `jump_to`, `order`).
    5. `Tracer` (`tracers`): Data jawaban kuesioner alumni (`alumni_id`, `question_id`, `nim`, `kelompok`, `kode_pertanyaan`, `subpertanyaan`, `answer`, `answer_json`, `tahun_lulus`).
- **Pembersihan Model & Pengayaan Dokumentasi Kode**:
  - Model `Kuesioner`, `KelompokPertanyaan`, `RefSubpertanyaan2021`, `RefSubpertanyaanDetil`, dan `Tracer` dibersihkan dari query builder wrapper legacy dan mutator alias yang tidak lagi diperlukan.
  - Setiap konstanta, method, fungsi controller/service, props Vue, dan elemen HTML telah dilengkapi PHPDoc/JSDoc dan komentar penjelasan terstruktur dalam Bahasa Indonesia.
  - Seluruh 38 automated test cases di `tests/Feature` berjalan sukses 100% (206 assertions).

## [2026-09-15] Restrukturisasi Skema Kuesioner Tracer Study 2021, Penamaan Tabel Bahasa Indonesia, Tabel Tracers, Pemisahan BIO_TTL, dan Sinkronisasi Otomatis Profil
- **Penamaan Tabel, Model, Migrasi, dan Seeder ke Bahasa Indonesia**:
  - **Standarisasi Nama Tabel Database**:
    1. `kuesioners`: Menggantikan `questionnaires`, dikelola oleh model `App\Models\Kuesioner` dan seeder `KuesionerSeeder`.
    2. `kelompok_pertanyaans`: Menggantikan `question_sections`, dikelola oleh model `App\Models\KelompokPertanyaan` dan seeder `KelompokPertanyaanSeeder`.
    3. `ref_subpertanyaan2021`: Menggantikan `questions`, dikelola oleh model `App\Models\RefSubpertanyaan2021` dan seeder `RefSubpertanyaan2021Seeder`.
    4. `ref_subpertanyaan_detil`: Menggantikan `question_options`, dikelola oleh model `App\Models\RefSubpertanyaanDetil` dan seeder `RefSubpertanyaanDetilSeeder`.
    5. `tracers`: Menggantikan `responses`, dikelola oleh model `App\Models\Tracer`.
    6. `v_question_mappings`: Database VIEW yang menggantikan tabel `question_mappings`, dikelola oleh model `App\Models\QuestionMapping` dan seeder `QuestionMappingSeeder`.
- **Rekonstruksi Tabel Jawaban `tracers` & Penyelarasan Variabel Kolom**:
  - Tabel `tracers` memuat kolom terstandarisasi:
    - `id`: Primary key (BIGINT)
    - `alumni_id`: Foreign key ke tabel `alumnis`
    - `question_id`: Foreign key ke tabel `ref_subpertanyaan2021`
    - `nim`: Nomor Induk Mahasiswa alumni
    - `kelompok`: Kode kelompok instrumen kuesioner (`char(3)`), seperti `'BIO'`, `'F1'`, `'F2'`, `'F3'`, `'F4'`, `'F5'`, `'F6'`, `'F7'`, `'F8'`, `'F10'`, `'F11'`, `'F12'`, `'F14'`, `'F15'`, `'F16'`, `'F17'`, `'F18'` (bukan ID foreign key)
    - `kode_pertanyaan`: Kode unik pertanyaan (`char(20)`)
    - `subpertanyaan`: Teks pertanyaan yang diselaraskan dengan tabel induk
    - `answer`: Teks jawaban utama alumni (menggantikan `answer_text`)
    - `answer_json`: Struktur data array/JSON untuk jawaban majemuk, matriks, dan checkbox
    - `keterangan`: Keterangan subpertanyaan atau judul kelompok (`varchar(100)`)
    - `tahun_lulus`: Tahun kelulusan mahasiswa dari data akademik alumni
  - Seluruh variabel pemrosesan di `SimpanJawabanController.php` dan `KuesionerSyncService.php` disamakan persis dengan nama-nama kolom database ini.
- **Pemisahan Biodata `BIO_TTL` Menjadi Dua Butir Independen**:
  - `BIO_TTL` dipecah menjadi 2 butir pertanyaan di tabel `ref_subpertanyaan2021`:
    1. `BIO_TEMPAT_LAHIR`: Ditarik otomatis dari `data_akademiks.tempat_lahir`.
    2. `BIO_TANGGAL_LAHIR`: Ditarik otomatis dari `data_akademiks.tanggal_lahir`.
  - Keduanya terdaftar pada database VIEW `v_question_mappings` dan disinkronkan secara otomatis oleh `KuesionerSyncService`.
- **Auto-Pull Data Profil Alumni / Perusahaan / Atasan ke Kuesioner**:
  - Menghilangkan redundansi pengisian dan potensi inkonsistensi data dengan menarik langsung profil ke butir kuesioner:
    - `F2E` dan `F5B`: Otomatis ditarik dari nama perusahaan alumni (`companies.nama_perusahaan`).
    - `F2E1`, `F2E2`, `F2E3`: Otomatis ditarik dari data atasan langsung (`atasans.nama`, `telepon`, `email`).
    - `F2F` dan `F510`: Otomatis ditarik dari alamat lengkap perusahaan (`alamat`, `kabupaten`, `provinsi`, `kode pos`).
    - `F2G` dan `F5C`: Otomatis ditarik dari posisi/jabatan alumni (`alumnis.posisi_jabatan`).
    - `F2H` dan `F5D`: Otomatis ditarik dari skala tempat kerja (`companies.skala`).
- **Antarmuka Matriks & Branching Logic Kuesioner 2021**:
  - `TabelF2.vue`: Komponen matriks 7 baris x 5 opsi skala penilaian untuk metode pembelajaran `F21` s.d. `F27`.
  - `TabelF17.vue`: Komponen matriks dual-side komparasi 7 baris x 5 opsi untuk kompetensi `F17a1`..`F17a7` (saat lulus) vs `F17b1`..`F17b7` (saat ini).
  - Alur percabangan `F504`:
    - Opsi "Ya" (`jump_to`: `'F502'`) $\rightarrow$ Membuka `F502` (waktu dapat kerja), `F505` (gaji per bulan), dan `F505A` (kesesuaian UMR).
    - Opsi "Tidak" (`jump_to`: `'F506'`) $\rightarrow$ Membuka `F506` (waktu mencari kerja).
- **Pembersihan Migrasi & Eksekusi Seeder**:
  - Menghapus migrasi usang (`create_responses_table.php`, `create_questions_table.php`, dll.) sehingga hanya tersisa migrasi `create` yang bersih dan terurut.
  - `DatabaseSeeder.php` memanggil langsung `KuesionerSeeder`, `KelompokPertanyaanSeeder`, `RefSubpertanyaan2021Seeder` (68 pertanyaan), `RefSubpertanyaanDetilSeeder` (185 opsi), `QuestionMappingSeeder`, dan `ProdiQuestionnaireSeeder`.
  - Pengujian `php artisan test --compact` berhasil dengan **38 tests passed (206 assertions)**.

- **Pemisahan dan Rekonstruksi Kuesioner Khusus Program Studi (Database & Model Terpisah)**:
  - **Penghapusan Kolom `prodi_id` pada Kuesioner Utama**: Menghapus kolom `prodi_id` dari tabel kuesioner umum universitas (`questions`) melalui migrasi `2026_09_13_090001_remove_prodi_id_from_questions_table.php`, serta membersihkan seluruh elemen terkait di antarmuka Super Admin (`QuestionModal.vue`, `QuestionCard.vue`, `SuperAdmin/Pertanyaan/Index.vue`). Kuesioner universitas kini murni terpusat untuk kuesioner tingkat universitas.
  - **Arsitektur Tabel Kuesioner Prodi yang Mandiri & Hierarkis**:
    1. `prodi_question_sections`: Menyimpan bagian/seksi kuesioner berbasis `prodi_id` (`id`, `prodi_id`, `title`, `description`, `order`).
    2. `prodi_questions`: Menyimpan butir pertanyaan khusus prodi dengan tipe input dinamis (`single_choice`, `multiple_choice`, `text`, `number`, `date`, `rating_5`, `radio_input`, dll.).
    3. `prodi_question_options`: Menyimpan opsi pilihan jawaban butir pertanyaan khusus prodi.
    4. `prodi_responses`: Menyimpan respon dan jawaban alumni khusus kuesioner program studi mereka (`answer_text` dan `answer_json`).
  - **Seeding Kuesioner Prodi Sistem Informasi & Prodi Lain**:
    - Menjalankan `ProdiQuestionnaireSeeder` dengan 9 Section dan 52 Butir Pertanyaan Evaluasi Komprehensif khusus Program Studi Sistem Informasi (Mulai dari data diri, konsentrasi peminatan, profil lulusan, kurikulum, fasilitas lab, hingga jejaring alumni).
    - Memindahkan data butir kuesioner prodi lama (seperti Filsafat Keilahian) ke struktur tabel kuesioner prodi baru.

- **Pengambilan Otomatis (Auto-Prefill & Auto-Save) Data Akademik pada Kuesioner Prodi**:
  - **3 Pertanyaan Identitas Utama (`Nama`, `NIM`, `Tahun Kelulusan`)**:
    - Pertanyaan kode `PSI-1-01` (Nama), `PSI-1-02` (NIM), dan `PSI-1-03` (Tahun Kelulusan) pada Kuesioner Program Studi kini secara cerdas terhubung dan diambil otomatis dari pangkalan Data Akademik (`data_akademiks`) alumni.
  - **Tampilan Antarmuka Khusus di Frontend Alumni (`KuesionerProdi.vue`)**:
    - Ditampilkan dalam format input khusus `readonly` dengan badge hijau resmi: *"✓ Data Akademik - Kolom ini diambil secara otomatis dari pangkalan Data Akademik resmi Anda"*, sehingga alumni tidak perlu mengetik ulang data yang sudah tercatat di sistem.
  - **Sinkronisasi Otomatis di Backend (`KuesionerProdiController.php`)**:
    - Method `tampilkanKuesionerProdi()` secara otomatis memetakan data akademik alumni ke `$initialAnswers` dan langsung menyimpannya ke tabel `prodi_responses` (`updateOrCreate`).
    - Method `simpanJawaban()` menyertakan fallback otomatis untuk menjamin bahwa data identitas akademik tetap tersimpan utuh meskipun input dikirimkan kosong.

- **Harmonisasi Modul & Antarmuka Admin Prodi (Disetarakan dengan Super Admin)**:
  - **Dashboard Admin Prodi (`/prodi/dashboard`)**:
    - Didesain ulang menyerupai `/superadmin/dashboard` dengan header hijau solid resmi UKDW `#0D542B`.
    - 4 kartu metrik KPI elegan berlatar putih bersih (Total Alumni Prodi, Sudah Mengisi Tracer Univ, Sudah Mengisi Kuesioner Prodi, Profil Lengkap).
    - 2 modul navigasi cepat (Kelola Kuesioner Prodi & Direktori Alumni Prodi).
    - Tabel ringkasan 5 aktivitas alumni terbaru dengan badge status tracer universitas dan kuesioner prodi.
  - **Kelola Section Prodi (`/prodi/sections`)**:
    - Controller baru `KelolaSectionProdiController.php` dengan operasi CRUD lengkap, pencarian, dan fitur reorder posisi urutan section (naik/turun).
    - Halaman view `AdminProdi/Section/Index.vue` dengan header hijau UKDW, modal tambah/edit section, dan konfirmasi SweetAlert2.
  - **Kelola Pertanyaan Prodi (`/prodi/pertanyaan`)**:
    - Pembersihan total seluruh ikon 3D dan emoji (📁, 🗑️, ✎, ❓, ✕), digantikan dengan tombol teks elegan (`Edit`, `Hapus`, `+ Tambah Opsi`) dan badge rapi.
  - **Direktori Mahasiswa & Alumni Prodi (`/prodi/alumni`)**:
    - Didesain ulang menyerupai `/superadmin/alumni` dengan filter pencarian, filter tahun kelulusan, filter status tracer universitas, dan kuesioner prodi.
    - Tombol aksi "Lihat Detail" mengarah ke halaman audit alumni `/prodi/alumni/{id}`.
  - **Detail Alumni Prodi (`/prodi/alumni/{id}`)**:
    - Method `DaftarAlumniProdiController::show()` dengan proteksi otorisasi ketat berbasis `prodi_id`.
    - Halaman `AdminProdi/Alumni/Show.vue` dengan 3 Tab:
      1. Tab 1: Kuesioner Khusus Prodi (filter per-section, capaian pengisian, jawaban rinci).
      2. Tab 2: Kuesioner Tracer Universitas (filter section kuesioner universitas).
      3. Tab 3: Detail Profil Mahasiswa (Biodata Pribadi, Kontak, Data Akademik, Data Orang Tua, Pekerjaan & Perusahaan).

- **Penambahan Tab Audit Kuesioner Prodi pada Detail Alumni Super Admin (`/superadmin/alumni/{id}`)**:
  - `DetailAlumniSuperAdminController.php` kini memuat relasi kuesioner prodi beserta jawaban alumni dari tabel `prodi_responses`.
  - `SuperAdmin/Alumni/Show.vue` menampilkan metrik 3-kolom pada header (Profil, Kuesioner Universitas, Kuesioner Prodi) serta tab khusus *"Kuesioner Khusus Prodi"* sehingga Super Admin dapat mengaudit evaluasi program studi setiap alumni secara transparan.

- **Modularisasi Komponen Kuesioner Tracer Study Alumni (`resources/js/Pages/Alumni/Kuesioner.vue`)**:
  - Memecah file monolitik `Kuesioner.vue` (yang sebelumnya mencapai 1.621 baris kode) menjadi komponen-komponen terisolasi dan mandiri di dalam folder `resources/js/Pages/Alumni/Components/Kuesioner/`:
    1. `Navbar.vue`: Menangani header atas, logo UKDW, dan tombol kembali ke dashboard alumni.
    2. `Stepper.vue`: Menangani tahapan bulatan angka 1 s/d N, judul seksi, indikator centang selesai, dan auto-scroll horizontal.
    3. `Banner.vue`: Menangani kartu banner hijau judul bagian ("Bagian X dari Y") dan petunjuk pengisian.
    4. `TabelF17.vue`: Menangani tabel perbandingan kompetensi dual-matrix F17 (Kolom A vs Kolom B) secara mandiri.
    5. `KartuPertanyaan.vue`: Menangani rendering butir pertanyaan individual beserta seluruh varian input (`rating_5`, `searchable_select`, `radio`, `checkbox`, `radio_input`, `multiple_number`, `number`, `text`, serta layout 2 kolom berdampingan F6 & F7).
    6. `Navigasi.vue`: Menangani tombol navigasi melayang di desktop (< Kembali & > Lanjut/Selesai) dan fixed bottom bar di smartphone.
  - Komponen induk `Kuesioner.vue` kini ringkas dan terfokus (berkurang dari 1.621 baris menjadi ~490 baris), hanya bertugas mengorkestrasi state form Inertia, alur jump logic, dan persistensi sesi lokal (`localStorage`).

- **Peta Lengkap Struktur File & Direktori Pembaruan Ini**:
  ```text
  tracerstudy/
  ├── app/
  │   ├── Http/Controllers/
  │   │   ├── AdminProdi/                                             # [MODUL BARU] Pengelolaan Program Studi
  │   │   │   ├── Dashboard/DashboardController.php                  # KPI & statistik alumni prodi
  │   │   │   ├── KelolaAlumni/DaftarAlumniProdiController.php        # Direktori & audit detail alumni prodi (/prodi/alumni/{id})
  │   │   │   └── KelolaPertanyaan/
  │   │   │       ├── DaftarPertanyaanProdiController.php             # Bank soal kuesioner prodi
  │   │   │       ├── KelolaOpsiProdiController.php                   # CRUD pilihan opsi jawaban prodi
  │   │   │       ├── KelolaSectionProdiController.php                # CRUD seksi & urutan kuesioner prodi
  │   │   │       └── SimpanPertanyaanProdiController.php              # Simpan & edit butir soal prodi
  │   │   ├── Alumni/Kuesioner/
  │   │   │   ├── KuesionerController.php                             # Kuesioner Tracer Study Universitas
  │   │   │   ├── KuesionerProdiController.php                        # [BARU] Kuesioner Khusus Prodi & auto-prefill akademik
  │   │   │   └── SimpanJawabanController.php                         # Simpan jawaban kuesioner univ
  │   │   └── SuperAdmin/
  │   │       └── KelolaAlumni/DetailAlumniSuperAdminController.php   # Audit detail alumni (+ tab kuesioner prodi)
  │   ├── Models/
  │   │   ├── ProdiQuestionSection.php                                # [MODEL BARU] Seksi kuesioner prodi
  │   │   ├── ProdiQuestion.php                                       # [MODEL BARU] Butir pertanyaan prodi
  │   │   ├── ProdiQuestionOption.php                                 # [MODEL BARU] Opsi jawaban kuesioner prodi
  │   │   └── ProdiResponse.php                                       # [MODEL BARU] Jawaban kuesioner prodi alumni
  │   └── Services/Kuesioner/
  │       └── KuesionerSyncService.php                                # [BARU] Centralized auto-sync data akademik ke prodi_responses
  ├── database/
  │   ├── migrations/
  │   │   ├── 2026_09_13_090000_add_prodi_id_to_users_table.php       # Menghubungkan user admin_prodi ke prodis.id
  │   │   ├── 2026_09_13_092000_create_prodi_questionnaire_tables.php # 4 tabel mandiri kuesioner prodi
  │   │   └── 2026_09_13_093000_remove_prodi_id_from_questions_table.php # Pembersihan kolom prodi_id di questions
  │   └── seeders/
  │       └── ProdiQuestionnaireSeeder.php                            # Seeder 9 section & 52 butir instrumen Prodi SI & Filsafat
  └── resources/js/Pages/
      ├── AdminProdi/                                                 # [UI BARU] Antarmuka Admin Program Studi
      │   ├── Dashboard.vue                                           # Dashboard resmi admin prodi (estetika hijau UKDW)
      │   ├── Alumni/Index.vue                                        # Master data mahasiswa & alumni prodi
      │   ├── Alumni/Show.vue                                         # Audit detail 3-tab (Kuesioner Prodi, Univ, Profil)
      │   ├── Pertanyaan/Index.vue                                    # Kelola pertanyaan & opsi (bebas ikon slop)
      │   ├── Section/Index.vue                                       # Kelola bagian kuesioner prodi
      │   └── Components/Navbar.vue                                   # Navbar resmi admin prodi
      ├── Alumni/
      │   ├── Kuesioner.vue                                           # [DEKOMPOSISI] Induk form kuesioner universitas (~490 baris)
      │   ├── KuesionerProdi.vue                                      # Halaman kuesioner prodi alumni (auto-prefill readonly)
      │   └── Components/Kuesioner/                                   # [MODULAR] 6 Komponen Pecahan Kuesioner:
      │       ├── Navbar.vue                                          # Header atas & tombol kembali
      │       ├── Stepper.vue                                         # Stepper bulat 1..N & auto scroll
      │       ├── Banner.vue                                          # Banner hijau judul bagian
      │       ├── TabelF17.vue                                        # Dual-matrix F17 (Kemampuan Diri vs Kampus)
      │       ├── KartuPertanyaan.vue                                 # Dispatcher renderer butir soal & opsi input
      │       └── Navigasi.vue                                        # Floating action buttons desktop & mobile bar
      └── SuperAdmin/Alumni/Show.vue                                  # Tampilan audit detail alumni dengan tab Kuesioner Prodi
  ```

- **Pembersihan Desain Visual & Standarisasi Komentar Kode**:
  - Menghapus seluruh ikon/emoji berlebihan di seluruh modul Admin Prodi agar tampilan lebih formal, profesional, dan tidak "slop".
  - Menambahkan komentar kode penjelasan (*docblocks & inline comments*) yang deskriptif dan terstruktur dalam Bahasa Indonesia pada seluruh controller, model, migration, dan komponen Vue terkait.

## [2026-09-10] Perbaikan Perhitungan Data Terhapus pada Profil Alumni & Penambahan Indikator Persentase Progres di Dashboard Alumni
- **Perbaikan Bug Perhitungan Data Profil yang Dihapus (`SimpanProfilController` & `KelengkapanTracerService`)**:
  - **Penyebab Utama**:
    1. Saat alumni mengosongkan/menghapus atribut perusahaan (seperti alamat, skala, provinsi, atau kabupaten), pemanggilan `array_filter(...)` pada controller secara tidak sengaja membuang nilai `null`, sehingga database tidak memperbarui field tersebut dan data lama tetap tersimpan.
    2. Saat alumni menghapus `email_pribadi` atau `nama` di data akademik, evaluasi kelengkapan sebelumnya menggunakan fallback `?? $alumni->user?->email` / `?? $alumni->user?->name` sehingga nilai tetap terhitung terisi padahal sudah dihapus oleh user.
    3. Hubungan relasi Eloquent (`company`, `atasan`, `dataAkademik`) yang telah di-cache di memori model `$alumni` tidak ter-refresh saat method sync atau evaluasi dijalankan dengan `loadMissing(...)`.
    4. Kolom `nama_orang_tua` pada tabel `data_orang_tuas` sebelumnya berstatus `NOT NULL`, menyebabkan query error jika user mengosongkan data orang tua.
  - **Solusi & Perbaikan**:
    - Menambahkan migrasi database `2026_09_09_203027_make_nama_orang_tua_nullable_in_data_orang_tuas_table.php` agar field data orang tua dapat dikosongkan tanpa integrity violation.
    - Menghapus `array_filter` pada update perusahaan di `SimpanProfilController.php` dan `DetailAlumniSuperAdminController.php` sehingga field yang dikosongkan tersimpan sebagai `null` di database.
    - Menghapus fallback otomatis ke akun user pada `KelengkapanTracerService::evaluasiProfil()` dan `KuesionerSyncService::syncProfileResponses()`, sehingga ketika data dihapus/dikosongkan, field tersebut secara akurat dihitung sebagai belum lengkap (persentase berkurang dan jawaban terkait di tabel `responses` terhapus otomatis).
- **Standarisasi Seluruh Data Model Alumni (`alumnis`) Menjadi Wajib**:
  - Seluruh atribut model `Alumni` kini berstatus wajib (*mandatory*) dalam penghitungan kelengkapan profil pada `KelengkapanTracerService::evaluasiProfil()`, yaitu:
    1. Bidang Keahlian (*Expertise*) (`expert`)
    2. Minat & Ketertarikan (`minat`)
    3. LinkedIn Profil URL (`linkedin_url`)
    4. LinkedIn Username (`linkedin_username`)
    5. Instagram Profil URL (`instagram_url`)
    6. Facebook Profil URL (`facebook_url`)
    7. Kode Pos Perusahaan / Zipcode (`zipcode`)
    8. Posisi / Jabatan (`posisi_jabatan`)
    9. Jenis Pekerjaan Gerejawi (`jenis_pekerjaan`, khusus prodi Teologi)
  - Total data profil wajib kini berjumlah **32 data** (atau 33 untuk Teologi). Jika ada salah satu dari data tersebut yang kosong, status profil langsung berubah menjadi **Belum Lengkap** dengan persentase yang berkurang secara proporsional.
- **Penambahan Persentase Kelengkapan & Progress Bar di Dashboard Alumni (`/alumni/dashboard`)**:
  - Mengintegrasikan penghitungan komprehensif dari `KelengkapanTracerService::evaluasiKelengkapanTotal($alumni)` ke `App\Http\Controllers\Alumni\Dashboard\DashboardController.php`.
  - Mengirimkan props persentase: `profilePercentage`, `profileCompleted`, `profileFilledCount`, `profileTotalCount`, `questionnairePercentage`, `questionnaireCompleted`, `questionnaireAnsweredCount`, `questionnaireTotalCount`, dan `questionnaireMissing`.
  - **Tampilan Visual Dashboard Alumni (`Dashboard.vue`)**:
    - **Card Profil & Biodata**: Menampilkan persentase kelengkapan profil (misal: `100% Terisi` atau `80% Terisi`), progress bar warna hijau UKDW (`#0D542B`), rincian data terisi (`X dari 23 data terisi`), dan badge status "Sudah Lengkap" (hijau) atau "Belum Lengkap" (kuning `#FDC700`).
    - **Card Kuesioner Tracer Study**: Menampilkan persentase jawaban kuesioner wajib (misal: `0% Terjawab` atau `100% Terjawab`), progress bar warna hijau UKDW (`#0D542B`), rincian butir terjawab (`X dari 61 pertanyaan wajib`), dan badge status "Sudah Diselesaikan" (hijau) atau "Belum Diselesaikan" (kuning `#FDC700`).
    - Tombol aksi utama distandarisasi menggunakan warna hijau solid UKDW `#0D542B` tanpa gradasi warna biru atau ungu.
- **Pengujian Otomatis (Automated Testing)**:
  - Membuat unit/feature test `tests/Feature/AlumniDashboardPercentageTest.php` untuk memvalidasi rendering persentase di dashboard dan penurunan persentase saat data profil dikosongkan.
  - Seluruh 28 unit dan feature test pada test suite lulus 100% (`passed: 28, assertions: 163`).

## [2026-09-10] Penerapan Warna Solid Resmi UKDW (Hijau #0D542B & Kuning #FDC700 Sesuai Gambar Logo), Penghapusan Gradasi Warna, dan Pembersihan Efek Border Hover
- **Warna Solid Murni Resmi UKDW (Diambil Langsung dari Gambar Lambang UKDW)**:
  - **Hijau Resmi UKDW**: `#0D542B` (Warna solid hijau botol resmi pada background logo UKDW, menghapus seluruh efek gradasi warna berlebihan).
  - **Kuning Resmi UKDW**: `#FDC700` (Warna solid kuning emas pada tulisan *"UNIVERSITAS KRISTEN DUTA WACANA"*).
  - **Putih**: `#FFFFFF`.
- **Pembersihan Total Efek Hover Border & Warna Belang-belang**:
  - Menghapus efek `hover:border-...` pada kartu dan baris tabel di seluruh modul Super Admin (`/superadmin/alumni`, `/superadmin/pertanyaan`, `/superadmin/sections`, `/superadmin/dashboard`).
  - Menghapus latar hijau muda pastel (`bg-green-100`) dan efek hover kuning belang-belang pada baris opsi pertanyaan (`QuestionCard.vue`), digantikan oleh baris bersih netral (`bg-gray-50 hover:bg-gray-100`) tanpa border saat dihover.
  - Badge status *"Belum Selesai"* menggunakan kuning solid UKDW `#FDC700` dengan teks hitam tegas tanpa border.
  - Badge status *"Selesai"* menggunakan hijau solid UKDW `#0D542B` dengan teks putih bersih tanpa border.
- **Standarisasi 4 Halaman Utama Super Admin**:
  1. `/superadmin/dashboard`: Header solid `#0D542B`, 4 kartu KPI putih bersih tanpa efek hover border, modul navigasi rapi dengan tombol hijau solid.
  2. `/superadmin/alumni`: Header solid `#0D542B`, kartu metrik ringkas, filter pencarian elegan, dan tabel data tanpa garis border saat baris di-hover.
  3. `/superadmin/alumni/{id}`: Header solid `#0D542B`, badge "Yudisium: Lulus" berlatar kuning emas `#FDC700`, kotak jawaban alumni berlatar netral dengan aksen garis kiri `#0D542B`.
  4. `/superadmin/pertanyaan` & `/superadmin/sections`: Header solid `#0D542B`, kartu pertanyaan bersih tanpa warna pelangi, dan opsi pilihan jawaban yang rapi.
- **Eliminasi Border Bertumpuk-tumpuk & Efek Kaku**:
  - Menghapus seluruh border tebal ganda (`border-2 border-yellow-200 border-l-8`) pada kartu statistik, filter, dan tabel.
  - Menerapkan card putih bersih dengan border halus abu-abu (`border border-gray-100`), sudut lengkung modern (`rounded-2xl` / `rounded-3xl`), dan bayangan halus (`shadow-sm hover:shadow-md`) persis seperti pada modul Alumni.
- **Standarisasi Warna Kuning Lembut (Soft Amber / Background Sand)**:
  - Mengganti warna kuning menyala menjadi kuning lembut seperti latar belakang (`bg-amber-50 text-amber-900 border border-amber-200/70`) pada badge "Yudisium: Lulus", status "Belum Selesai", dan peringatan butir kuesioner belum dijawab.
- **Penyelarasan 4 Halaman Utama Super Admin**:
  1. `/superadmin/dashboard`: Header gradien emerald UKDW (`#005B3C` ke `#007b55`), 4 kartu KPI putih bersih elegan tanpa border ganda, dan 2 modul navigasi profesional.
  2. `/superadmin/alumni`: Header gradien emerald, ringkasan cepat glassmorphism di header, 4 kartu KPI minimalis, filter pencarian bersih, dan tabel DataTables yang lapang dan mudah dibaca.
  3. `/superadmin/alumni/{id}`: Badge Yudisium berlatar kuning lembut, ringkasan audit kuesioner putih bersih, kotak jawaban alumni rapi dengan aksen kiri tegas (`border-l-4 border-l-[#005B3C] bg-emerald-50/40`), dan formulir profil 4-tab yang serasi.
  4. `/superadmin/sections` & `/superadmin/pertanyaan`: Standarisasi warna tombol dan badge jenis pertanyaan ke palet emerald, amber lembut, dan netral tanpa warna pelangi.
- **Standarisasi Palet Murni Tiga Warna (Strict 3-Color Theme)**:
  - Menerapkan palet resmi UKDW di seluruh antarmuka Super Admin (`/superadmin`, `/superadmin/dashboard`, `/superadmin/alumni`, `/superadmin/alumni/{id}`):
    1. **Hijau Resmi UKDW**: `#005B3C` (Header banner, tombol aksi utama, border tebal kotak jawaban, badge kelengkapan, highlight navigasi aktif).
    2. **Kuning Resmi UKDW**: `#FACC15` (Badge penanda belum selesai/wajib, border aksen) & `#FEFCE8` / `#FEF08A` (Latar belakang pembeda antar card agar kontras dan mudah dibaca).
    3. **Putih**: `#FFFFFF` (Latar konten kartu, card butir pertanyaan, latar kotak jawaban, teks pada elemen hijau).
  - Menghapus seluruh warna asing (seperti biru, ungu, oranye) pada komponen kartu metrik, navigasi, dan tabel.
- **Redesign Halaman Dashboard Super Admin (`Dashboard.vue`) & Rute Redirect**:
  - Menambahkan rute `Route::redirect('/superadmin', '/superadmin/dashboard')` pada `routes/web.php` sehingga akses ke `http://localhost:8000/superadmin` langsung mengarah ke Dashboard tanpa error.
  - Mengubah 4 kartu KPI metrik di Dashboard dengan border kiri tebal hijau `#005B3C` (`border-l-8`) dan latar putih/kuning yang jelas perbedaannya.
  - Menghapus seluruh icon gambar/emoji (`#`, `§`, `👤`, `✓`) dan menggantinya dengan badge teks terstruktur yang rapi.
  - Memperbarui 2 modul navigasi cepat ("Direktori Mahasiswa & Hasil Tracer" dan "Instrumen Kuesioner") dengan tema 3 warna.
- **Penebalan & Penegasan Border Kotak Jawaban Alumni (`Show.vue`)**:
  - Kotak jawaban kuesioner alumni dipertegas dengan border hijau tebal `border-2 border-[#005B3C] border-l-[10px] border-l-[#005B3C] bg-white rounded-xl shadow-xs`.
  - Dilengkapi badge hijau mini `Terisi` dan teks jawaban hitam pekat berbobot `font-black` untuk keterbacaan optimal.
  - Setiap butir pertanyaan diletakkan dalam kartu mandiri berlatar putih dengan border kuning lembut di atas background section `#FEFCE8`, menjamin setiap card terlihat jelas perbedaannya.
- **Pembersihan Ikon Gambar pada Navbar & Direktori Mahasiswa**:
  - Menyederhanakan `Navbar.vue` dengan menghilangkan icon svg dekoratif pada tombol keluar dan menerapkan navigasi aktif berbasis aksen hijau-kuning.
  - Menghapus warna merah pada tombol reset filter di `Index.vue` agar seragam dengan tema 3 warna.
- **Seeding Pertanyaan Studi Lanjut `F24` & Opsi Jawaban**:
  - Menambahkan instrumen pertanyaan studi lanjut dan pembiayaan kuliah pada `QuestionSeeder.php` dan `QuestionOptionSeeder.php`:
    1. `F24A`: "Sebutkan sumberdana dalam pembiayaan kuliah S1 di UKDW :" (tipe `single_choice`, status wajib diisi seluruh alumni, 7 opsi jawaban: Biaya Sendiri/Keluarga, Beasiswa ADIK, Beasiswa BIDIKMISI, Beasiswa PPA, Beasiswa Afirmasi, Beasiswa Perusahaan/Swasta, dan Lainnya).
    2. `F24B`: "Jika Anda melanjutkan ke jenjang pascasarjana (S2), sebutkan sumberdana dalam pembiayaan kuliah S2 Anda :" (tipe `single_choice`, status wajib diisi seluruh alumni, 3 opsi jawaban: 0: Tidak melanjutkan S2, 1: Melanjutkan dengan biaya sendiri, 2: Melanjutkan dengan beasiswa).
- **Layanan Evaluasi Kelengkapan Terpadu (`KelengkapanTracerService.php`)**:
  - Mengimplementasikan `App\Services\Kuesioner\KelengkapanTracerService`:
    1. `evaluasiProfil()`: Mengaudit kelengkapan 100% data profil (Nama, Tempat/Tgl Lahir, Agama, Jenis Kelamin, NIK, No KK, NISN, BPJS, NPWP, Alamat Domisili, Kelurahan, Kecamatan, Kab/Prov, Kode Pos, Nomor Telepon/HP, Email Pribadi, IPK, Tahun Kelulusan, Data Orang Tua, Data Perusahaan, Data Atasan, dan Posisi Jabatan).
    2. `evaluasiKuesionerWajib()`: Mengaudit butir kuesioner wajib mencakup `F8` (status bekerja), pertanyaan pekerjaan $\le$ 6 bulan (`F3`/`F5`), lokasi bekerja (`F2F`), jenis perusahaan (`F11`), nama perusahaan (`F2E`), posisi jabatan (`F2G`), studi lanjut (`F24A` dan `F24B`), keselarasan bidang studi (`F14`), tingkat pendidikan sesuai (`F15`), dan seluruh 54 butir evaluasi kompetensi `F17-1` s/d `F17-54`.
    3. Pertanyaan selain daftar wajib di atas secara otomatis diklasifikasikan sebagai **Opsional** (misal `F4`, `F6`, `F7`, `F9`, `F10`, `F12`, `F13`, `F16`, `F18`, `F19`, `F20`, `F21`, `F22`, `F23`) dan tidak membatalkan status kelulusan pengisian tracer study jika tidak diisi.
    4. `evaluasiKelengkapanTotal()`: Menghasilkan status final "Selesai" vs "Belum Selesai" beserta persentase progress dan daftar item yang belum lengkap.
- **Penyempurnaan Tampilan UI & Card Profesional (Tema Kuning UKDW Landing Page & Hijau Emerald)**:
  - **Direktori Mahasiswa (`Index.vue`)**:
    1. Tampilan card statistik ringkas dan profesional dengan border aksen hijau & kuning UKDW (`#FACC15` / `#005B3C`).
    2. Mengeliminasi ikon-ikon yang tidak penting agar halaman bersih, lapang, dan mudah dibaca.
    3. Mengimplementasikan fungsionalitas DataTables dengan pemilihan jumlah data per halaman (5, 10, 25, 50), penomoran urut, navigasi halaman (Pagination), dan keterangan jumlah data aktif.
  - **Halaman Detail & Audit Kuesioner (`Show.vue`)**:
    1. **Card Ringkasan Audit Kuesioner**: Disederhanakan menampilkan persentase saja (misal `0% Terjawab (0/61)`) tanpa deretan teks panjang butir soal di dalam card.
    2. **Navigasi Filter Section Interaktif**: Pertanyaan wajib yang belum dijawab dimunculkan langsung sebagai badge penanda di tombol navigasi section (`• X belum`) sehingga Super Admin dapat langsung mengklik section yang bersangkutan untuk meninjau butir soal yang belum terjawab.
    3. **Card Section Kuesioner Berlatar Kuning UKDW**: Header card kuesioner menggunakan warna kuning lembut UKDW (`#FEFCE8` dengan border `#FDE047`) yang elegan ala landing page.
- **Formulir Profil Lengkap 100% & Fitur Edit Profil oleh Super Admin**:
  - Menyediakan 4 tab formulir profil lengkap persis sama dengan portal alumni (Data Pribadi, Data Akademik, Data Orang Tua, dan Karier & Perusahaan) yang dapat diisi dan diperbarui secara langsung oleh Super Admin untuk membantu alumni yang kesulitan memperbarui data.
  - Menambahkan endpoint `POST /superadmin/alumni/{id}/profile` pada `DetailAlumniSuperAdminController.php` beserta sinkronisasi otomatis ke respon tracer `F1` s/d `F2H` via `KuesionerSyncService`.
- **Navigasi & Automated Testing**:
  - Menu **"Data Alumni"** aktif pada `Navbar.vue` Super Admin.
  - Automated feature test suite di `tests/Feature/SuperAdminDaftarAlumniTest.php` (5 pengujian mencakup index, filter, show, authorization, dan update profile alumni oleh superadmin) lulus 100% (26 feature test suite Laravel lulus tanpa error).
  - Standarisasi format kode PHP menggunakan Laravel Pint.

## [2026-09-10] Pembaruan UI/UX Alur Lompatan (Jump Logic): Dropdown Bertingkat (Cascading 2-Kolom: Section di Kiri & Pertanyaan di Kanan)
- **Pembaruan Antarmuka Dropdown Alur Lompatan / Percabangan (`OptionModal.vue`)**:
  - Mengimplementasikan sistem **Dropdown Bertingkat 2 Kolom (*Cascading Flyout Master-Detail*)**:
    1. **Kolom Kiri (Pilih Section)**: Menampilkan daftar seluruh bagian kuesioner (`Section 1: Identitas`, `Section 2: ...`) lengkap dengan badge total soal dan panah indikator bertingkat. Saat salah satu section diklik, section tersebut di-highlight aktif dengan latar hijau emerald `#005B3C`.
    2. **Kolom Kanan (Daftar Pertanyaan Terkait)**: Menampilkan butir pertanyaan dari section yang dipilih di kolom kiri. Sesuai instruksi pengguna, tampilan **dibatasi tepat 5 pertanyaan dalam 1 layar pandang** (`h-[260px]`), dan jika terdapat lebih dari 5 butir soal, pengguna dapat melakukan **scroll** secara halus (`overflow-y-auto`).
    3. **Format Mini Data Table**: Setiap baris di kolom kanan memuat badge kode kuesioner monospaced (`F1`, `F8`, `F17-1`), teks pertanyaan rapi 2 baris, dan indikator status `✓ Terpilih` / hover prompt `Pilih`.
  - **Fitur Pencarian Real-time (Searchable)**: Kotak input pencarian di bagian atas pop-up menyaring pertanyaan secara instan lintas section. Jika section aktif tidak memiliki hasil yang cocok, sistem otomatis mengarahkan ke section pertama yang memiliki kecocokan.
  - **Pilihan Alur Default Cepat**: Tombol cepat *"Alur Normal (Default)"* di bagian atas pop-up serta tombol (✕) pada kolom pemicu untuk memudahkan pengembalian alur kuesioner ke alur normal tanpa lompatan.
  - **Aksesibilitas & Pengalaman Pengguna**: Penyesuaian lebar modal menjadi `max-w-2xl`, auto-focus pada input pencarian saat pop-up dibuka, dan penutupan otomatis saat klik di luar area modal (*click-outside*).
- **Penyempurnaan Data Backend (`DaftarPertanyaanController.php`)**:
  - Memuat relasi `section` pada query `$availableJumpTargets` (`question_section_id`, `section_title`, `section_order`) agar siap dikelompokkan secara terstruktur di frontend.

## [2026-09-10] Modul CRUD & Pengaturan Urutan (Reorder) QuestionSection Superadmin, Integrasi UI/UX, dan Tabel UMP 2026
- **Pengembangan Modul CRUD & Pengaturan Urutan Bagian Kuesioner (`QuestionSection`)**:
  - Membuat controller backend baru [`KelolaSectionController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/SuperAdmin/KelolaSection/KelolaSectionController.php) di `app/Http/Controllers/SuperAdmin/KelolaSection/` yang mencakup:
    1. `index()`: Mengambil daftar section terurut berserta relasi instrumen kuesioner induk dan kalkulasi `withCount('questions')`.
    2. `store()`: Menambahkan bagian baru dengan otomatisasi penentuan nomor urutan (`order`).
    3. `update()`: Menyunting judul bagian dan kuesioner induk.
    4. `destroy()`: Menghapus bagian kuesioner dengan pembersihan kaskade opsi dan butir pertanyaan demi integritas data.
    5. `reorder()`: Mengatur ulang urutan posisi section (naik/turun secara directional menggunakan *order swapping* maupun pembaruan array massal).
  - Mendaftarkan rute terpadu superadmin di `routes/web.php` (`superadmin.sections.*`).
- **Standardisasi Tipe Pertanyaan Menyerupai Google Forms & Otomatisasi Opsi Rating**:
  - Memperluas pilihan tipe pertanyaan pada `QuestionModal.vue` menjadi terstruktur rapi dengan kategori:
    1. *Teks & Isian*: Jawaban singkat (`text`), Paragraf (`textarea`), Isian Angka (`number`).
    2. *Pilihan & Opsi*: Pilihan ganda radio (`single_choice`), Pilihan ganda + Isian Angka F3/F5 (`radio_input`), Pilihan ganda + Isian Teks Lainnya (`radio_text`), Kotak Centang (`multiple_choice`), Drop-down (`dropdown`).
    3. *Skala & Penilaian*: Skala linier / Rating 1-5 (`rating_5`), Isian Rincian Gaji F13 (`multiple_number`), Kisi pilihan ganda (`matrix`), Petak evaluasi ganda (`matrix_dual`).
    4. *Tanggal & Berkas*: Tanggal (`date`), Waktu (`time`), Upload file (`file`).
  - **Otomatisasi Opsi Skala Rating**: Pada [`SimpanPertanyaanController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/SuperAdmin/KelolaPertanyaan/SimpanPertanyaanController.php), saat admin membuat pertanyaan bertipe `rating_5` (atau memperbarui ke tipe tersebut), sistem backend secara otomatis membuatkan 5 pilihan skala penilaian standar (1: Sangat Rendah s/d 5: Sangat Tinggi) sehingga admin tidak perlu mengetik opsi rating manual satu per satu.
  - Menambahkan *dynamic hint* di bawah kolom tipe pertanyaan yang menjelaskan fungsi dan perilaku tiap tipe.
  - Memperbarui `QuestionCard.vue` agar mengenali penambahan opsi dan menampilkan badge warna yang tepat untuk seluruh tipe baru (seperti `radio_input`, `radio_text`, `rating_5`, `matrix`, dll.).
- **Antarmuka Pengguna Vue 3 Modul Kelola Section (`SuperAdmin/Section`)**:
  - Membuat halaman utama [`resources/js/Pages/SuperAdmin/Section/Index.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/SuperAdmin/Section/Index.vue):
    1. Menampilkan daftar kartu bagian dengan visual emerald UKDW `#005B3C` yang elegan, kartu rounded `[2rem]`, badge nomor urut, kuesioner induk, dan total butir soal.
    2. Fitur pencarian instan berdasarkan judul bagian atau nama kuesioner.
    3. Tombol navigasi langsung ke daftar butir soal masing-masing section (`?sec_id={id}`).
    4. Kontrol pemindahan urutan posisi naik (▲) dan turun (▼) dengan status tombol nonaktif otomatis saat berada di batas atas/bawah.
    5. Dialog peringatan SweetAlert2 jika section yang akan dihapus masih memuat butir pertanyaan aktif.
  - Membuat komponen modal `resources/js/Pages/SuperAdmin/Section/Components/SectionModal.vue` untuk alur pembuatan dan pengeditan section dengan validasi formulir dan umpan balik sukses/gagal.
- **Integrasi Navigasi Terpadu Superadmin**:
  - Menambahkan menu navigasi resmi **Kelola Section** pada `Navbar.vue` (versi Desktop dan Mobile).
  - Menambahkan tombol pintas **Kelola Section** pada halaman [`resources/js/Pages/SuperAdmin/Pertanyaan/Index.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/SuperAdmin/Pertanyaan/Index.vue) agar admin dapat beralih cepat antara manajemen butir soal dan manajemen bab/section.
- **Penyusunan Tabel UMP 2026 Terhubung Foreign Key `kode_provinsi`**:
  - Membuat migrasi tabel `umps` dengan relasi foreign key langsung ke kolom unique `kode_provinsi` pada tabel `provinces`.
  - Model `Ump.php` dengan relasi `belongsTo(Province::class, 'kode_provinsi', 'kode_provinsi')` serta relasi timbal balik `umps()` dan `ump()` di `Province.php`.
  - Membuat `UmpSeeder.php` berisi 38 data nominal UMP 2026 resmi seluruh provinsi di Indonesia dengan logika `updateOrCreate`.
  - Menyiapkan `UmpFactory.php` untuk kebutuhan data mock testing.
- **Automated Testing & Standarisasi Kode**:
  - Membuat pengujian komprehensif di [`tests/Feature/SuperAdminKelolaSectionTest.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/tests/Feature/SuperAdminKelolaSectionTest.php) (6 pengujian mencakup index, store, update, destroy, directional reorder, dan bulk reorder) dan [`tests/Feature/UmpTest.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/tests/Feature/UmpTest.php).
  - Seluruh 20 unit/feature test suite lulus 100%.
  - Seluruh berkas PHP diformat sesuai standar dengan Laravel Pint.

## [2026-09-09] Refaktorisasi Modular Modul Kelola Pertanyaan Superadmin, Pemisahan Komponen Vue, Navigasi Terpadu & Integrasi SweetAlert2
- **Pemisahan Controller Backend Menjadi Modular (Single-Responsibility)**:
  - Memecah controller monolitik `KelolaPertanyaanController.php` menjadi 3 controller modular di `app/Http/Controllers/SuperAdmin/KelolaPertanyaan/`:
    1. `DaftarPertanyaanController.php`: Menangani aksi `index()` untuk memuat daftar butir pertanyaan, relasi sections, program studi, opsi, dan peta lompatan branching (`jump_to`).
    2. `SimpanPertanyaanController.php`: Menangani operasi `store()`, `update()`, `destroy()`, dan `reorder()` urutan naik/turun pertanyaan.
    3. `KelolaOpsiController.php`: Menangani operasi `storeOption()`, `updateOption()`, dan `destroyOption()`.
  - Memperbarui `routes/web.php` untuk mengarahkan seluruh rute kuesioner superadmin ke controller modular baru.
- **Pemisahan Komponen Vue pada Modul Kelola Pertanyaan (`SuperAdmin/Pertanyaan`)**:
  - Memecah file tunggal `Index.vue` (811 baris) menjadi sub-komponen terstruktur di `resources/js/Pages/SuperAdmin/Pertanyaan/Components/`:
    1. `SectionTabs.vue`: Navigasi tab horizontal antar-section kuesioner dengan badge nomor urut dan jumlah soal.
    2. `QuestionCard.vue`: Kartu butir pertanyaan individu dengan kontrol aksi naik/turun urutan, edit/hapus soal, serta daftar opsi jawaban.
    3. `QuestionModal.vue`: Modal dialog popup tambah dan edit butir pertanyaan kuesioner.
    4. `OptionModal.vue`: Modal dialog popup tambah dan edit pilihan opsi jawaban dan alur percabangan (*jump logic*).
    5. `Index.vue`: Komponen Orchestrator utama yang ringkas, bersih, dan mengelola sinkronisasi query string `?sec_id=` dan pencarian.
- **Komponen Navigasi Terpadu Resmi Superadmin (`Navbar.vue`)**:
  - Membuat satu file navigasi resmi terpadu di `resources/js/Pages/SuperAdmin/Components/Navbar.vue` yang digunakan bersama oleh Dashboard dan Kelola Kuesioner.
  - Menampilkan branding UKDW, menu aktif, info pengguna, dan tombol logout dengan konfirmasi SweetAlert2.
- **Penerapan SweetAlert2 Menyeluruh**:
  - Menggantikan semua fungsi bawaan browser (`window.confirm`) dengan dialog interaktif SweetAlert2 bertema warna hijau emerald UKDW (`#005B3C`).
  - Dilengkapi dialog konfirmasi hapus pertanyaan dan opsi jawaban, feedback sukses simpan/update, serta error alert validasi.
- **Redesign Tampilan UI Menyerupai Profil Alumni & Perbaikan Tab Terpotong (Clipping)**:
  - Menerapkan kartu rounded modern, bayangan halus, header gradien emerald UKDW `#005B3C`, dan tipografi Instrument Sans yang konsisten.
  - Memperbaiki masalah tampilan terpotong (*clipping/overlap bug*) pada `SectionTabs.vue`:
    1. Menghilangkan `sticky top-20` yang menutupi judul bagian (*Section Header*), *search bar*, dan tombol `+ Tambah Pertanyaan` saat halaman di-scroll atau pada layar beresolusi laptop/tablet.
    2. Menambahkan *custom thin scrollbar* (3px) yang elegan pada baris navigasi tab horizontal untuk menggantikan scrollbar bawaan Windows yang tebal dan memotong konten di bawahnya.
    3. Menambahkan fungsi *auto-scroll-into-view* reaktif agar tab yang aktif (misal Section 4, 5, dst.) otomatis bergeser ke tengah area pandang horizontal saat dipilih atau saat halaman dibuka via parameter URL `?sec_id=`.
- **Dokumentasi Kuesioner Dinamis & Aturan F17**:
  - Menambahkan bab penjelasan arsitektur kuesioner realtime dan aturan pasangan butir genap F17 ke `documentation.txt`.

## [2026-09-09] Fitur Total Salary Reaktif, Format Pengisian Ribuan (Akhiran .000 Otomatis) & Perlindungan Kesalahan Data pada Kuesioner F13
- **Kalkulasi & Tampilan Total Salary Reaktif (Pertanyaan `multiple_number` / F13)**:
  - Menambahkan kartu ringkasan visual **Total Pendapatan (Total Salary)** dengan desain gradien hijau emerald yang elegan dan ikon finansial di bawah rincian pendapatan F13.
  - Nilai total dikalkulasi secara reaktif secara *real-time* saat alumni mengetikkan atau mengubah angka pada komponen gaji mana pun (Pekerjaan Utama, Lembur & Tips, Pekerjaan Lainnya).
  - Dilengkapi label `*Otomatis dihitung & tersimpan ke database`.
- **Format Input Satuan Ribuan dengan Suffix `.000` Otomatis**:
  - Mengubah input komponen pendapatan menjadi input group modern dengan prefix `Rp` di sisi kiri, angka rata kanan monospace tebal, dan badge suffix `.000` di sisi kanan.
  - Memberikan petunjuk pengisian yang jelas: `* Nominal diisi dalam satuan ribuan rupiah (akhiran .000 otomatis). Contoh: masukkan 5000 untuk Rp 5.000.000, atau 750 untuk Rp 750.000`.
  - Menambahkan konversi langsung di bawah masing-masing opsi: `Konversi: Rp 5.000.000`.
- **Proteksi Cerdas Pencegahan Kesalahan Data (*Anti Double-Multiplication*)**:
  - Frontend: Jika alumni mengetik angka sangat besar (>= 1.000.000, misal mengetik 5.000.000 karena belum terbiasa dengan akhiran .000), muncul peringatan instan: `⚠️ Nilai terbaca di atas Rp 1 Miliar. Jika maksud Anda Rp 5.000.000, cukup ketik 5000`.
  - Backend: `SimpanJawabanController.php` secara cerdas mendeteksi angka: jika `< 1.000.000` dikalikan 1000 ke Rupiah penuh, namun jika `>= 1.000.000` tetap disimpan apa adanya untuk mencegah data membengkak menjadi miliaran rupiah.
- **Penyimpanan & Pembaruan Otomatis Nilai Total di Database**:
  - Kolom `answer_json` kini menyimpan rincian nominal rupiah penuh per kode opsi plus key akumulasi `'total'` (contoh: `{"F13-01": 6500000, "F13-02": 750000, "F13-03": 0, "total": 7250000}`).
  - Kolom `answer_text` memuat rincian terformat rupiah beserta total pendapatan (`"Dari Pekerjaan Utama: Rp 6.500.000, ..., Total Pendapatan: Rp 7.250.000"`).
  - Jika alumni mengedit salah satu nilai pendapatan dan menyimpan kembali, total salary di database otomatis ter-update dan tersinkronisasi.
- **Normalisasi Nilai saat Memuat Kembali Kuesioner**:
  - `KuesionerController.php` dan `Kuesioner.vue` (`getInitialAnswers`) secara otomatis membagi 1000 nilai tersimpan di database agar saat alumni membuka kembali kuesioner, angka di dalam kotak input berakhiran `.000` tetap konsisten (misal: 6.500.000 tampil sebagai 6500).
- **Pengujian Otomatis (Feature Test)**:
  - Membuat `tests/Feature/AlumniKuesionerMultipleNumberTest.php` dengan 4 skenario uji (penyimpanan ribuan & kalkulasi total, pembaruan total saat data diubah, normalisasi ke ribuan saat kuesioner dimuat, dan proteksi input nominal penuh). Seluruh tes lolos 100% (25 assertions).
- **Penyempurnaan Tampilan Evaluasi Kompetensi F17 (Berdampingan Bersih & Anti-Overlap)**:
  - Menyederhanakan tampilan agar intuitif, profesional, dan bebas dari instruksi berlebihan (*no bloated AI text/emojis*).
  - Menghilangkan *sticky header* yang sebelumnya menyebabkan tombol pilihan baris terpotong/tertutup (*clipping bug*).
  - Struktur tabel komparasi berdampingan:
    - **Kolom (A) Kemampuan Diri Anda**: Taraf penguasaan kompetensi saat lulus dengan aksen hijau emerald dan skala 1 (Rendah) s/d 5 (Tinggi).
    - **Kolom Tengah**: Nama aspek kompetensi yang bersih, dilengkapi *pill indicator* minimalis (`A > B`, `A = B`, `A < B`) jika kedua kolom telah diisi.
    - **Kolom (B) Kontribusi Kampus UKDW**: Peran kurikulum dan perkuliahan almamater dengan aksen biru royal dan skala 1 (Rendah) s/d 5 (Tinggi).
  - Menjaga proporsi visual yang seimbang, *padding* leluasa, dan pengalaman pengisian yang lancar di berbagai ukuran layar.

## [2026-09-08] Standarisasi Dokumentasi Edukatif Kode & Pemetaan Eksplisit Backend-Inertia-Frontend
- **Penambahan Komentar Penjelasan Baris demi Baris pada Backend (Controller)**:
  - `Alumni/Dashboard/DashboardController.php`: Memberikan komentar penjelasan detail pada setiap baris logika pengecekan pengguna login, pencarian data alumni, penghitungan jumlah respon kuesioner dari database, pengecekan status kelengkapan profil, serta pemetaan paket data (props) ke Inertia.
  - `AdminBiroTiga/Dashboard/DashboardController.php`: Memberikan dokumentasi menyeluruh per-variabel pada seluruh metrik KPI utama (`totalAlumni`, `totalResponden`, `persentaseRespon`, `totalPertanyaan`, `totalProdi`, `alumniLinkedIn`), kalkulasi ringkasan partisipasi per-prodi (`prodiSummaries`), dan 5 alumni terbaru (`recentAlumni`).
- **Penambahan Dokumentasi Asal-Usul Data & Aksi pada Frontend (Vue)**:
  - `Alumni/Dashboard.vue`: Menjelaskan asal mula props dari controller, method logout dengan `router.post('/logout')`, serta rute tujuan untuk setiap tombol `<Link>` profil dan kuesioner.
  - `AdminBiroTiga/Dashboard.vue`: Mendokumentasikan setiap struktur objek props yang diterima (`stats`, `prodiSummaries`, `recentAlumni`) dan penggunaannya pada kartu metrik maupun tabel data.
  - **Arsitektur Parent-Child Component Profil Alumni**:
    - `Alumni/Profil/Index.vue` (Parent): Menjelaskan siklus hidup data mulai dari penerimaan props `formData` dari `ProfilController.php`, pembentukan `useForm`, distribusi ke komponen anak, hingga pengiriman data simpan ke `SimpanProfilController.php`.
    - `Components/FormAkademik.vue` (Child): Menjelaskan silsilah asal objek `form` yang dioper dari parent `Index.vue`, daftar field akademik database yang ditampung, serta alasan penguncian input resmi kampus (*read-only*).
    - `Components/FormPribadi.vue`, `Components/FormOrangTua.vue`, & `Components/FormKarier.vue` (Child): Mendokumentasikan peran masing-masing form anak dan operan props form serta master data wilayah dari controller.
- **Pembaruan Aturan Agen & Dokumentasi**:
  - Menambahkan aturan baru `.agents/rules/code-documentation-guidelines.md` yang mewajibkan komentar per-variabel di Controller dan komentar silsilah props/method di file Vue.
  - Memperbarui file `documentation.txt` pada bagian Step 9 mengenai alur arsitektur Backend-Inertia-Frontend dan hierarki Parent-Child.

- **Perbaikan Masalah Tombol Logout di Dashboard Alumni & Seluruh Modul**:
  - Mengidentifikasi akar masalah: pemanggilan composable `useForm().post('/logout')` di dalam callback function click handler Vue 3 menyebabkan error injeksi konteks (`inject() can only be used inside setup()`), sehingga request tidak terkirim.
  - Memperbaiki seluruh method logout di seluruh komponen aplikasi (`Alumni/Dashboard.vue`, `SuperAdmin/Dashboard.vue`, `SuperAdmin/Pertanyaan/Index.vue`, `AdminProdi/Dashboard.vue`, `AdminBiroTiga/Dashboard.vue`, `AdminBiroTiga/Pertanyaan/Index.vue`, `AdminBiroTiga/AlumniIndex.vue`, `AdminBiroTiga/AlumniShow.vue`) dengan menggunakan `router.post('/logout')` dari `@inertiajs/vue3`.
- **Dukungan Rute Logout Aman & Fleksibel**:
  - Mengubah rute logout di `routes/web.php` menjadi `Route::match(['get', 'post'], '/logout', ...)` agar dapat dipanggil baik via POST maupun GET.
  - Memastikan jika sesi pengguna telah kedaluwarsa saat tombol logout ditekan, pengguna tidak akan terkunci di redirect error melainkan langsung terarah kembali dengan mulus ke Beranda (Home / Landing Page `/`).
  - Menambahkan komentar dokumentasi lengkap pada method `LoginController::prosesLogout`.
- **Migrasi Modul Kelola Pertanyaan & Alur Percabangan ke Superadmin**:
  - Memindahkan seluruh fitur manajemen kuesioner dari Biro 3 ke Superadmin sesuai hak wewenang universitas.
  - Membuat controller baru `App\Http\Controllers\SuperAdmin\KelolaPertanyaan\KelolaPertanyaanController` dengan komentar lengkap dan dokumentatif di setiap method.
  - Membuat halaman tampilan `resources/js/Pages/SuperAdmin/Pertanyaan/Index.vue` dengan navbar institusional Superadmin, navigasi section per-bagian (horizontal tabs), penyusunan urutan (reorder up/down), CRUD pertanyaan, dan alur percabangan (*Google Forms jump logic*).
  - Memperbarui rute di `routes/web.php` di bawah middleware `role:superadmin` (`/superadmin/pertanyaan`, `/superadmin/pertanyaan/reorder`, `/superadmin/pertanyaan/{id}`, opsi jawaban).
- **Pembaruan Dasbor Superadmin & Biro 3**:
  - Mengembangkan `SuperAdmin/DashboardController.php` dan `resources/js/Pages/SuperAdmin/Dashboard.vue` dengan ringkasan metrik instrumen, total pertanyaan, section, partisipasi responden, serta kartu gerbang langsung menuju modul kelola kuesioner.
  - Memperbarui `AdminBiroTiga/Dashboard.vue` dengan memfokuskan peran Biro 3 pada Manajemen Data Alumni, Sinkronisasi LinkedIn, dan Pemantauan Partisipasi Responden, serta menghapus tautan kelola pertanyaan dari navbar Biro 3.
- **Komentar Kode Menyeluruh**:
  - Menambahkan komentar deskriptif pada setiap rute, controller, dan komponen Vue terkait sesuai pedoman pengembangan.

## [2026-09-08] Fitur Penambahan Perusahaan Baru: SweetAlert2 Modal Popup Bersih, Validasi Wajib Wilayah & Kode Pos Langsung Simpan Database
- **Popup Tambah Perusahaan Menggunakan SweetAlert2 (Clean & Standar)**:
  - Menggantikan modal kustom dengan popup dialog **SweetAlert2** (`Swal.fire`) yang rapi, presisi, dan proporsional.
  - Bebas dari glitch overflow, terpotong, atau efek blur yang tidak diinginkan.
  - Dilengkapi input Nama Perusahaan (*), Dropdown Provinsi (*), Dropdown Kabupaten/Kota dinamis (*), Kode Pos (*), Skala, dan Alamat.
  - Pesan validasi interaktif ditampilkan langsung di dalam popup modal (`Swal.showValidationMessage`) tanpa menutup popup jika ada kolom wajib yang belum terisi.
- **Validasi Ketat Input Perusahaan Baru**:
  - Mewajibkan pengisian:
    1. **Nama Perusahaan / Instansi** (*)
    2. **Provinsi Perusahaan** (*)
    3. **Kabupaten / Kota Perusahaan** (*) (otomatis menyesuaikan provinsi yang dipilih)
    4. **Kode Pos Perusahaan** (*)
    5. Skala dan Alamat Jalan (opsional/pelengkap).
  - Mengimplementasikan validasi frontend dan backend untuk memastikan data lokasi perusahaan baru terisi lengkap meskipun status verifikasi adalah `Menunggu Verifikasi`.
- **Penyimpanan Langsung ke Database & Integrasi Otomatis**:
  - Menambahkan migrasi `2026_09_08_163000_add_kode_pos_to_companies_table.php` dan memperbarui model `Company.php` dengan kolom `kode_pos`.
  - Menambahkan route `POST /alumni/company` dan method controller `SimpanProfilController::tambahPerusahaanBaru(Request $request)` untuk menyimpan perusahaan baru ke database secara instan.
  - Setelah perusahaan berhasil disimpan:
    - Data perusahaan langsung ditambahkan ke daftar opsi dropdown (`localCompanies.unshift`).
    - Input form profil karier otomatis terpilih dan terisi dengan perusahaan baru tersebut (nama, provinsi, kabupaten/kota, alamat, skala, dan kode pos).
    - Menampilkan notifikasi sukses SweetAlert yang rapi dan menutup popup modal.
- **CSRF Token Protection**:
  - Menambahkan `<meta name="csrf-token" content="{{ csrf_token() }}">` pada layout `resources/views/app.blade.php` untuk memastikan seluruh request fetch aman dan terautentikasi.

## [2026-09-08] Penyempurnaan Profil Alumni: Penambahan NPWP, Pemisahan Data Paten Universitas vs Editable, Relasi Company Wilayah, & Kelengkapan Seeder 100%
- **Penambahan Kolom NPWP (Nomor Pokok Wajib Pajak)**:
  - Membuat migrasi baru `2026_09_08_160000_add_npwp_to_data_akademiks_table.php` untuk menambahkan kolom `npwp` (varchar 30, nullable) pada tabel `data_akademiks`.
  - Mendaftarkan `'npwp'` ke properti `$fillable` di model `DataAkademik.php`.
  - Menambahkan input NPWP pada kartu **Identitas Diri** di `FormPribadi.vue` dan mengintegrasikannya ke `ProfilController` serta `SimpanProfilController`.
- **Penghapusan Kolom Ekstra Dosen Pembimbing & Penguji**:
  - Kolom `dosen_pembimbing_3`, `dosen_penguji_3`, dan `dosen_penguji_4` yang tidak digunakan resmi dihapus dari skema database (`yudisiums`) dan migrasi. Dosen kini terstandarisasi menjadi Pembimbing 1, Pembimbing 2, Penguji 1, dan Penguji 2.
- **Seluruh Data Yudisium & Skripsi Resmi Paten (Tidak Dapat Diubah Alumni)**:
  - Data yudisium (Judul TA Indonesia & Inggris, Repositori/URL Publikasi, Jenis/Status Publikasi, Dosen Pembimbing 1-2, Dosen Penguji 1-2, Predikat Yudisium, dan Status Yudisium) ditarik resmi dari pangkalan data kampus dan berstatus paten. Seluruh input disajikan dalam mode terkunci (disabled abu-abu) dan di-bypass dari logika penyimpanan di `SimpanProfilController.php`.
- **Pembersihan Tampilan Non-AI Slop (Minimalis, Profesional, & Bersih)**:
  - Menghapus seluruh badge ikon gembok `🔒 Paten Universitas`, tulisan pengumuman panjang, dan dekorasi blur berlebihan.
  - Seluruh field paten / terkunci disajikan dengan estetika institusional bersih: input abu-abu halus (`bg-gray-100 text-gray-700 border-gray-200 cursor-not-allowed rounded-xl`) tanpa ornamen visual yang berisik.
- **Searchable Select Dropdown Perusahaan, Provinsi, & Kabupaten (Bergaya Gambar 2)**:
  - Mengubah input nama perusahaan menjadi **Searchable Select Dropdown** yang dapat diklik langsung kapan saja untuk membuka daftar perusahaan.
  - Dropdown dilengkapi dengan kotak pencarian sticky di atas (`🔍 Ketik untuk mencari...`) dan daftar opsi yang scrollable dengan penanda aktif hijau lembut (`bg-green-50 text-[#005B3C] font-semibold`), persis seperti pada Gambar 2.
  - Tetap menyediakan tombol `+ Tambah` di samping kanan input untuk membuka modal penambahan perusahaan baru jika instansi belum terdaftar di database.
  - Menerapkan pola searchable select yang sama pada pilihan Provinsi Perusahaan dan Kabupaten/Kota Perusahaan dengan penutup otomatis saat klik di luar (click outside).
  - **Data yang Dapat Diubah Alumni**:
    - Biodata diri (Nama, NIK, NPWP, Tempat/Tgl Lahir, Agama, Jenis Kelamin, Golongan Darah, Kewarganegaraan).
    - Kontak & Alamat Domisili lengkap (Telepon, Email, Alamat, Provinsi, Kabupaten/Kota, Kecamatan, Kelurahan, Kode Pos).
    - Skripsi, Tugas Akhir, Repositori, Publikasi Ilmiah, Dosen Pembimbing & Penguji (`FormAkademik.vue`).
    - Data lengkap Orang Tua / Wali (`FormOrangTua.vue`).
    - Karier, Perusahaan, Atasan, dan Media Sosial (`FormKarier.vue`).
- **Sinkronisasi Kunci Form Data Orang Tua & Akademik**:
  - Memperbaiki ketidaksesuaian kunci (mismatch key) di `FormOrangTua.vue` (`nama_orang_tua`, `pekerjaan_orang_tua`, `nomor_telepon_orang_tua`, dll) dan `FormAkademik.vue` (`angkatan_masuk`, `dosen_pembimbing_1`, `dosen_penguji_1`, `keterangan_hasil_yudisium`) agar data tersinkronisasi 100% dua arah dengan backend.
- **Relasi Wilayah Perusahaan (Company) Terhubung Lewat ID**:
  - `CompanySeeder.php` diperbarui untuk menempatkan perusahaan di 3 wilayah spesifik:
    1. **DI Yogyakarta**: Kabupaten **Sleman** (ID provinsi DIY kode `34`, ID kabupaten Sleman kode `34.04`). Perusahaan: *PT Gameloft Indonesia*, *PT Niagahoster*, *PT Djarum Sleman*, *CV Javan Cipta Solusi*.
    2. **DKI Jakarta**: **Kota Jakarta Pusat** (ID provinsi DKI Jakarta kode `31`, ID kabupaten Kota Jakarta Pusat kode `31.71`). Perusahaan: *PT Bank Central Asia Tbk*, *PT Telekomunikasi Indonesia Tbk*, *PT Tokopedia*, *PT Astra International Tbk*.
    3. **Aceh**: Kabupaten **Aceh Selatan** (ID provinsi Aceh kode `11`, ID kabupaten Aceh Selatan kode `11.01`). Perusahaan: *PT Perkebunan Nusantara I (PTPN)*, *PT Bank Aceh Syariah Tapaktuan*, *CV Samudera Selatan Digital*.
  - Seluruh relasi `province_id` dan `kabupaten_id` terhubung via foreign key yang valid.
- **Kelengkapan Seeder Data Alumni 100% Rata Terisi**:
  - `DataAkademikSeeder.php`: Mengisi 100% data untuk seluruh 10 alumni (termasuk NIK, KK, NISN, BPJS, NPWP, domisili lengkap terhubung ke ID wilayah, dan data paten kelulusan).
  - `DataOrangTuaSeeder.php`: Mengisi lengkap data orang tua untuk seluruh 10 alumni.
  - `YudisiumSeeder.php`: Mengisi lengkap data skripsi bahasa Indonesia & Inggris, repositori, jenis & status publikasi, nama dosen pembimbing & penguji, serta predikat kelulusan untuk seluruh 10 alumni.
  - `AlumniSeeder.php`: Mengaitkan `company_id` (terdistribusi ke Sleman, Jakarta Pusat, dan Aceh Selatan), data atasan (`atasan_id`), posisi jabatan, keahlian (`expert`), minat (`minat`), dan tautan media sosial.
  - `DatabaseSeeder.php`: Mengatur urutan pemanggilan seeder agar `CompanySeeder` dijalankan sebelum `AlumniSeeder`.

- **Tata Letak Kartu Kotak Berdampingan (Kanan-Kiri) Khusus Pertanyaan F6 & F7**:
  - Mengelompokkan kartu pertanyaan `F6` ("Berapa perusahaan dilamar?") dan `F7` ("Berapa perusahaan merespons?") menjadi grid 2 kolom berdampingan (`grid grid-cols-1 md:grid-cols-2 gap-6`).
  - Masing-masing disajikan dalam kartu kotak `rounded-[2rem] shadow-sm` proporsional dengan input angka besar di tengah sehingga tampilan lebih rapi, hemat ruang vertikal, dan ergonomis.
- **Dukungan Input Dinamis untuk Opsi "Lainnya / Tuliskan" di Semua Tipe Pilihan**:
  - Menyediakan kotak isian teks (`input text`) dinamis yang otomatis muncul saat alumni mencentang atau memilih opsi jawaban yang mengandung kata `"Lainnya"` atau `"Tuliskan"` pada tipe `multiple_choice` / `checkbox` maupun `single_choice` / `radio`.
  - Input teks terintegrasi langsung ke state pengiriman jawaban `SimpanJawabanController` dan tersimpan rapi ke tabel `responses`.
- **Pembersihan Teks Pertanyaan F10 & Verifikasi Database Seeding**:
  - Menghapus teks instruksi legacy `"KEMUDIAN LANJUT KE F17"` dari database. Teks pertanyaan `F10` di database dan seeder kini bersih murni: `"Apakah anda aktif mencari pekerjaan dalam 4 minggu terakhir? Pilihlah Satu Jawaban."`.
  - Memverifikasi bahwa seluruh alur branching `jump_to` (lompat ke `F17-1` atau lanjut normal) dikendalikan 100% dari kolom `jump_to` di tabel `question_options` di database tanpa hardcode teks di frontend maupun seeder.

## [2026-09-08] Perbaikan Hover Pilihan Kuesioner, Eliminasi Border Kasar, & Persistensi State/Draft saat Refresh Halaman
- **Perbaikan Visual Opsi Pilihan Saat Hover (Anti-Whiteout)**:
  - Mengatasi masalah teks dan placeholder input di dalam opsi pilihan kuesioner yang memutih saat kursor mouse di-hover (seperti pada pertanyaan F3).
  - Mengganti utilitas CSS bentrok Tailwind (`peer-checked:bg-[#005B3C] peer-checked:text-white hover:bg-green-50`) dengan binding reaktif Vue murni.
  - Opsi belum terpilih: latar `bg-gray-50/90 text-gray-700 hover:bg-emerald-50 hover:text-[#005B3C]`.
  - Opsi terpilih: latar `bg-[#005B3C] text-white shadow-md hover:bg-[#00482f]`.
  - Kotak input dinamis pada tipe `radio_input`: Latar putih kontras tinggi `bg-white text-gray-900 placeholder-gray-400 font-bold border-0 shadow-inner` sehingga teks input dan placeholder terbaca dengan sangat jelas.
- **Penghapusan Border Kasar (Clean & Modern UI)**:
  - Menghapus border tebal (`border-4`, `border-2`) pada kartu pertanyaan, header banner, stepper navigasi bagian, dan floating action button di halaman kuesioner alumni.
  - Tampilan diganti dengan *subtle shadow* (`shadow-sm`, `shadow-xs`) dan sudut halus (`rounded-[2rem]`) yang bersih dan rapi.
- **Persistensi Penuh Tampilan & Draft Saat Refresh Halaman (Tidak Pindah Tampilan & Data Tidak Hilang)**:
  - **Kuesioner Alumni (`/alumni/kuesioner`)**:
    - Menyimpan section kuesioner aktif ke URL query (`?sec=...`) dan `localStorage`. Saat halaman di-refresh (F5), alumni tetap berada di bagian/section yang sedang dikerjakan, tidak terlempar ke Section 1.
    - Menyimpan draft jawaban yang sedang diisi secara real-time (*debounced* 250ms) ke `localStorage`. Jika halaman di-refresh sebelum tombol simpan ditekan, seluruh draft isian tidak akan hilang.
    - Menambahkan `preserveState: true` dan `preserveScroll: true` pada seluruh navigasi step.
  - **Profil Alumni (`/alumni/profile`)**:
    - Menyimpan tab aktif (`pribadi`, `akademik`, `orangtua`, `karier`) ke URL query (`?tab=...`) dan `localStorage`. Saat di-refresh, pengguna tetap berada di tab yang sama.
    - Menyimpan draft input perubahan form profil ke `localStorage` agar data yang belum tersubmit tidak hilang saat halaman di-refresh secara tidak sengaja.
  - **Kelola Pertanyaan Biro 3 (`/biro3/pertanyaan`)**:
    - Menyimpan ID section aktif ke URL query (`?sec_id=...`) dan `localStorage`. Saat admin me-refresh halaman, section yang sedang dikelola tidak berpindah kembali ke Bagian 1.

- **Penggantian Navigasi Tab Menjadi Dropdown**:
  - Menghilangkan navigasi horizontal tab per-semester di bagian atas tabel, menggantikannya dengan komponen **Dropdown Tahun / Semester Yudisium** yang terintegrasi di panel filter bersama Program Studi dan Pencarian.
- **Penyajian Data Terisolasi per Tahun Kelulusan (Tanpa Tumpukan Semua Semester)**:
  - Mengubah sistem kueri di `DaftarAlumniController.php` agar secara default langsung memilih tahun/semester kelulusan terbaru (`$daftarSemester->first()`), dan tabel HANYA memuat data alumni pada periode yang dipilih tersebut.
  - Mencegah kelebihan beban data (*information overload*) dengan meniadakan mode tampil semua semester sekaligus.
- **Filter Ketat Status Yudisium Lulus (`proses_yudisium = 'Lulus'`)**:
  - Backend controller `DaftarAlumniController.php` memfilter data alumni secara eksklusif hanya bagi alumni yang status yudisiumnya telah dinyatakan `'Lulus'` pada relasi `yudisium` (`yudisiums.proses_yudisium = 'Lulus'`) atau `dataAkademik.status_yudisium`.
  - Alumni yang berstatus Belum Yudisium, Proses, atau Tidak Lulus tidak akan ditampilkan dalam direktori kelulusan Biro 3.
- **Standarisasi Menyeluruh Navigasi & Identitas Visual Biro 3**:
  - Menyeragamkan seluruh tata letak bilah navigasi atas (Navbar) di seluruh portal Biro 3 (`/biro3/dashboard`, `/biro3/alumni`, `/biro3/alumni/{id}`, `/biro3/pertanyaan`).
  - Menggunakan navbar putih bersih dengan efek `backdrop-blur`, logo resmi UKDW, teks resmi biro, menu tab konsisten (`Dashboard`, `Data Alumni`, `Kelola Pertanyaan`), dan tombol Keluar abu-abu bersih.
  - Menggunakan tema warna institusi formal: Hijau resmi UKDW (`#005B3C`), Kuning Landing Page (`#FACC15` / `yellow-400`), dan Putih.
  - Membasmi seluruh unsur warna orange (`#D97706`, `#F59E0B`), merah mencolok, dan icon berlebihan (anti AI-slop).
- **Penambahan Informasi Akademik Lengkap**:
  - Menampilkan NIM, Nama, Program Studi, IPK Kelulusan, Judul Tugas Akhir / Skripsi, Status Yudisium (Badge: Lulus), dan aksi detail alumni.
- **Pembaruan Detail Alumni (`AlumniShow.vue`)**:
  - Menyelaraskan navbar atas dan header banner dengan breadcrumb navigasi yang elegan.
  - Menampilkan informasi status kelulusan yudisium dan semester kelulusan akademik secara terintegrasi.

## [2026-09-07] Refinement Kelola Pertanyaan & Panel Biro 3: Desain Ala Profil Alumni, Navigasi Tab Per-Section, & Warna Kuning Landing Page
- **Desain Profesional Ala Profil Alumni**: Merombak antarmuka `Index.vue` kelola pertanyaan dengan estetika formal profesional yang bersih (banner gradient hijau UKDW `#005B3C` ke `#007b55`, kartu kontainer terangkat `rounded-2xl`, tipografi proporsional, dan tombol aksi kuning landing page `#FACC15` / `yellow-400`).
- **Navigasi Section di Atas (Horizontal Tabs)**: Menyediakan tab navigasi bagian/section kuesioner horizontal di bagian atas (seperti navigasi tab di Profil Alumni). Admin dapat memilih bagian (Bagian 1, Bagian 2, dst) secara cepat.
- **Pengelompokan & Filter Soal per Section**: Halaman hanya menampilkan butir pertanyaan dari section yang sedang aktif (tidak menumpuk 20+ soal sekaligus sehingga tidak membuat pusing).
- **Pemindahan Pertanyaan Terisolasi per Section**: Tombol pemindah urutan (▲ / ▼) pada kartu bubble kini dibatasi hanya menukar urutan butir pertanyaan di dalam section yang sama, baik di frontend maupun di kueri backend (`KelolaPertanyaanController.php`).
- **Pembersihan Total Warna Orange & Merah Mencolok**: Menghapus seluruh warna orange (`#D97706`, `#F59E0B`, amber) dan merah mencolok di seluruh panel Biro 3 (`Index.vue`, `Dashboard.vue`, `AlumniIndex.vue`, `AlumniShow.vue`). Menggantinya dengan warna kuning resmi landing page (`#FACC15` / `yellow-400`) dan hijau resmi UKDW.
- **Pembersihan Icon Berlebihan (Anti AI-Slop)**: Menghilangkan emoji berlebihan dan ikon-ikon dekoratif yang tidak perlu, menyajikan tampilan tabel dan formulir yang bersih, elegan, dan berstandar aplikasi perguruan tinggi.

## [2026-09-07] Implementasi Dashboard Resmi & Portal Kendali Biro 3 (Formal, Tegas, Hijau-Kuning-Putih)
- **Halaman Dashboard Utama Biro 3 (`/biro3/dashboard`)**: Membangun portal landing resmi untuk Administrator Biro 3 agar saat login tidak langsung dilempar masuk ke submenu alumni, melainkan disambut dengan ringkasan indikator kinerja utama.
- **Konsep Desain Formal & Tegas**: Menerapkan tema institusional resmi universitas yang tegas dan berwibawa menggunakan warna resmi UKDW (Hijau Tua `#005B3C` / `#004D32`, Kuning Emas `#D97706` / `#F59E0B`, dan Putih Bersih). Menghindari desain kasual/playful bergelombang ala alumni dengan sudut tegas (`rounded`), pembagian panel modular, dan tipografi administratif rapi.
- **Kartu Metrik Eksekutif (KPI)**: Menampilkan 4 kartu indikator utama: Total Basis Data Alumni (beserta jumlah data LinkedIn), Partisipasi Kuesioner (responden terverifikasi & progress bar tingkat respons), Total Instrumen Pertanyaan, serta Cakupan Program Studi Terintegrasi.
- **Dua Modul Gerbang Navigasi Utama**:
  1. *Modul 01 - Basis Data & Direktori Alumni*: Akses langsung ke manajemen alumni, pencarian prodi/NIM, dan sinkronisasi LinkedIn (`/biro3/alumni`).
  2. *Modul 02 - Konfigurasi Instrumen & Jump Logic*: Akses langsung ke pengelolaan pertanyaan kuesioner, opsi jawaban, dan scoping prodi (`/biro3/pertanyaan`).
- **Tabel Pemantauan Partisipasi per Program Studi**: Menyajikan rekapitulasi data kelulusan, jumlah responden kuesioner, tingkat partisipasi persentase dengan progress bar, dan badge status evaluasi capaian per prodi.
## [2026-09-08] Standarisasi Format Data Tabel responses & Sinkronisasi Ekspor
- **Standarisasi Kolom `responses`**:
  - `answer_json`: Dikhususkan menyimpan JSON array murni untuk pertanyaan pilihan ganda (`multiple_choice`/`checkbox`), associative array/object untuk `radio_input` dan `multiple_number`, serta null untuk pertanyaan berjawaban tunggal.
  - `answer_text`: Dikhususkan menyimpan format string/varchar bersih yang siap dibaca dan diekspor (Excel/CSV) tanpa perlu parsing JSON di setiap baris laporan.
- **Dukungan Opsi "Lainnya"**:
  - Jika alumni memilih opsi "Lainnya" dan mengetikkan teks sendiri:
    - Pada pertanyaan `single_choice`/`radio`: tersimpan sebagai varchar rapi `"Lainnya: [isi teks]"` di `answer_text`.
    - Pada pertanyaan `multiple_choice`/`checkbox`: teks dimasukkan ke dalam JSON array di `answer_json` (`["Opsi 1", "Lainnya: [isi teks]"]`) dan digabungkan dengan koma di `answer_text` (`"Opsi 1, Lainnya: [isi teks]"`).
- **Format Khusus `radio_input` & `multiple_number`**:
  - Pertanyaan `radio_input` (seperti F3 dan F5): menghasilkan kalimat utuh di `answer_text` (misal: `"Kira-kira 4 bulan sebelum lulus"`).
  - Pertanyaan `multiple_number` (seperti F13): menghasilkan rincian nominal rupiah terformat dengan label opsi di `answer_text` (misal: `"Dari Pekerjaan Utama: Rp 6.500.000, Dari Lembur dan Tips: Rp 750.000, Dari Pekerjaan Lainnya: Rp 0"`).
- **Sinkronisasi Otomatis Pertanyaan Profil (F1 s/d F2H)**:
  - Pertanyaan F1 s/d F2H yang ditarik dari profil alumni (`data_akademiks`, `alumnis`, `companies`, `atasans`) otomatis tersinkronisasi dan tersimpan di tabel `responses` untuk setiap alumni melalui `KuesionerSyncService`.
  - `QuestionMappingSeeder` diperbarui sehingga saat seeding dijalankan, tabel `responses` langsung terisi lengkap untuk seluruh data alumni.
- **Prefill Dua Arah (*Roundtrip Persistence*)**:
  - `KuesionerController.php` diperbarui agar dapat memecah kembali nilai `"Lainnya: [teks]"` ke state form frontend (`_custom`), memastikan data tidak hilang atau rusak saat halaman direfresh atau dibuka kembali.
- **Isolasi Input Mandiri pada Tipe `radio_input` (F3 & F5)**:
  - Memperbaiki model input pada kartu bertipe `radio_input` agar terikat ke `inputs[opt.id]` per opsi (bukan satu variabel `input` global). Mengeliminasi bug di mana angka yang diketik di opsi "Sebelum lulus" otomatis ikut terisi ke opsi "Sesudah lulus" saat berganti pilihan.
- **Eliminasi Mutlak Nilai NULL pada Tabel `responses`**:
  - `SimpanJawabanController.php` kini menyaring pertanyaan yang belum dijawab / belum sampai di section aktif sehingga tidak ada lagi record kosong (`answer_text: null, answer_json: null`) yang tersimpan ke database.
  - Nilai rincian numerik (F13) yang tidak diisi otomatis terisi angka `0` (bukan `null`).
  - Membersihkan seluruh record dummy yang sebelumnya bernilai null di database sehingga tabel `responses` bersih 100% dari nilai null.

- **Alur Login & Rute Terpadu**:
  - Memperbarui `LoginController.php` agar peran `admin_biro3` otomatis dialihkan ke `/biro3/dashboard`.
  - Mendaftarkan rute `Route::get('/biro3/dashboard', ...)->name('biro3.dashboard')` pada `routes/web.php`.
- **Penyeragaman Navigasi Atas (*Header Bar*)**:
  - Menyeragamkan header institusional di seluruh halaman Biro 3 (`Dashboard.vue`, `AlumniIndex.vue`, `Pertanyaan/Index.vue`, dan `AlumniShow.vue`) dengan navigasi 3 tab: **Dashboard**, **Data Alumni**, dan **Kelola Pertanyaan**.
- **Automated Feature Testing**: Menambahkan berkas pengujian `tests/Feature/BiroTigaDashboardTest.php` untuk memvalidasi proteksi tamu, pengalihan login `admin_biro3`, dan rendering komponen Inertia.

## [2026-09-07] Scoping Pertanyaan Prodi Dinamis & Reseeding Bersih
- **Kolom `prodi_id` pada Tabel `questions`**: Menambahkan kolom `prodi_id` (nullable foreign key ke `prodis.id` dengan `nullOnDelete`) langsung di migrasi utama `create_questions_table.php` dan model `Question.php`.
- **Dukungan Khusus Pertanyaan F2E**: Mengaitkan pertanyaan `F2E` ("Jenis Pekerjaan & Nama Perusahaan / Instansi / Institusi - Gerejawi / Non Gerejawi") secara dinamis ke Program Studi Filsafat Keilahian (kode prodi `31`) di `QuestionSeeder.php`. Pertanyaan umum lainnya tetap bernilai `prodi_id = null`.
- **Filtering Backend Dinamis (Tanpa Hardcode Frontend)**: Menyesuaikan kueri pada `KuesionerController.php` dengan klausul `whereNull('prodi_id')->orWhere('prodi_id', $alumni->prodi_id)`. Alumni non-31 tidak akan menerima pertanyaan F2E, sedangkan alumni prodi 31 menerimanya secara otomatis tanpa modifikasi kondisional di Vue.
- **Integrasi Admin Biro 3**: Menambahkan dropdown target program studi dan visual badge khusus prodi pada panel kelola pertanyaan (`KelolaPertanyaanController.php` & `Index.vue`).
- **Migrate Fresh & Reseed**: Menjalankan `php artisan migrate:fresh --seed` secara bersih dan terverifikasi di seluruh tingkatan data.
- **Komentar & Dokumentasi**: Melengkapi PHPDoc dan komentar arsitektur di Model, Migrasi, Seeder, Controller, dan dokumen README.

## [2026-09-07] Penyempurnaan Tampilan Kuesioner (Tanpa Garis Shadow Tebal Bawah)
- **Restorasi Layout & Estetika**: Mengembalikan layout dan struktur kuesioner yang disukai (background hijau segar `#E8F5E9`, kartu rounded, stepper tahapan, floating round navigation buttons, dan tabel komparasi F17 berdampingan).
- **Penghapusan Garis Shadow Tebal Bawah**: Menghapus seluruh efek bayangan 3D bergaris tebal di bawah (`shadow-[0_8px_0_0_...]`, `shadow-[0_6px_0_0_...]`, `shadow-[0_4px_0_0_...]`) pada banner, kartu soal, pill options, stepper, dan tombol melayang sehingga tampilan bersih dan rapi.
- **Penghapusan Toast Gamifikasi**: Menghilangkan animasi toast pop-up ("HEBAT!", "MANTAP!", dll) saat pergantian bagian agar pengalaman pengisian kuesioner nyaman dan tidak mengganggu.

## [2026-09-07] Refactoring Jump Logic: Unifikasi ke `question_options.jump_to`
- **Drop Kolom `jump_logic`**: Menghapus kolom legacy `jump_logic` (JSON) dari tabel `questions` via migrasi baru `2026_09_07_000001_remove_jump_logic_from_questions_table.php`.
- **Pembersihan Model & Seeder**: Menghapus atribut `jump_logic` dari model `Question.php` dan `QuestionSeeder.php`.
- **Seeder `QuestionOptionSeeder`**: Memastikan seluruh opsi percabangan memiliki atribut `jump_to` yang lengkap dan presisi (F3-03 -> F8, F8-01 -> F11, F8-02 -> F9, F10-01 s/d F10-05 -> F17-1).
- **Refactoring Vue `Kuesioner.vue`**: Menghapus fallback `jump_logic` dan memastikan sistem percabangan pertanyaan murni membaca `jump_to` dari `QuestionOption` secara dinamis tanpa hardcode.
- **Pembaruan Dokumentasi**: Menyesuaikan dokumen `README.md` dan `documentation.txt` dengan arsitektur baru.

## [2026-09-02] Step 5: Kuesioner UI/UX & Google Forms Logic
- **UI/UX Matriks**: Mengubah soal tipe Matriks F17 (Dual) dan F19-F22 (Single) menjadi komponen tabel yang responsif, *sticky column*, dan pewarnaan kontras.
- **Pill Buttons & Checkbox**: Mengganti *radio button* bawaan browser menjadi tombol bergaya *Pill* interaktif.
- **Validasi Input Murni**: Menambahkan event `@keydown` pada semua tipe *number* dan *multiple_number* untuk memblokir input non-numerik murni (misal: memblokir huruf 'e', tanda plus/minus, titik, koma).
- **Google Forms Jumping Logic**: Merombak total arsitektur `QuestionSectionSeeder` dan `QuestionSeeder` untuk mengisolasi pertanyaan dengan efek *jump* (misal: F3) ke dalam 1 halaman *section* sendiri.
- **Fix JSON Bug**: Menghilangkan `json_encode` di Seeder yang menyebabkan *double-encoding* pada atribut *jump_logic*.

## [2026-09-02] Step 4: Core Engine Kuesioner Dinamis
- **Arsitektur DB**: Membuat tabel hierarkis: `questionnaires`, `question_sections`, `questions`, `question_options`, dan `responses`.
- **Relasi Pemetaan Ekstra**: Membuat tabel `question_mappings` untuk mencatat relasi secara eksplisit (beserta *foreign key*) antara pertanyaan di kuesioner dengan kolom di tabel target (seperti kolom identitas di tabel `alumnis`).
- **Seeder Lengkap**: Memetakan seluruh instrumen Kuesioner Tracer Study dari F1 sampai F22 ke dalam format data relasional (termasuk *jump_logic* JSON).
- **Controller**: Membangun `QuestionnaireController` untuk me-*render* kuesioner dinamis secara *nested* ke Vue dan menyimpan respons (baik teks murni maupun JSON untuk *checkbox* / matriks).
- **Middleware Update**: Menambahkan `auth.user` data ke `HandleInertiaRequests` untuk mencegah *white screen crash* pada komponen Vue yang bergantung pada identitas pengguna.

## [2026-09-02] Step 3: Database Refactoring, Models, & UI/UX Enhancements
- **Migrasi**: Menghapus kolom spesifik alumni (`phone`, `address`, `ipk`, `sac_points`) dari tabel `users`.
- **Migrasi Baru**: Membuat tabel `alumnis` yang memuat F1 (NIM), F2A (Nama), F2B (Telepon), F2C (Email), F2D (Alamat Sekarang), `ipk`, dan `sac_points` yang berelasi `One-to-One` dengan `users`.
- **Model**: Menambahkan relasi `hasOne(Alumni::class)` di model `User` dan `belongsTo(User::class)` di model `Alumni`.
- **Seeder**: Memperbarui `DatabaseSeeder` dengan akun alumni percobaan (NIM: 72230607, Password: 01012001) yang wajib mengganti password. Akun admin diset agar tidak wajib ganti password.
- **UI/UX**: Menambahkan transisi, efek *hover*, dan *micro-animations* pada seluruh komponen *Landing Page* (`HeroSection`, `TracerStudySection`, `AlumniCard`, `CTASection`) agar lebih hidup dan interaktif.
- **Komentar Kode**: Memperbarui dokumentasi di setiap fungsi, *routes*, dan *controller*.

## [2026-09-01] Step 2: Foundation & Authentication
- Membuat skema tabel `users` untuk memuat *role* dan *must_change_password*.
- Membuat `DatabaseSeeder` untuk *roles* (admin_biro3, admin_prodi, superadmin).
- Membangun `AuthController` untuk manajemen Login, Logout, dan Penggantian Password (paksa).
- Membangun *Middlewares*: `RoleMiddleware` dan `CheckMustChangePassword`.
- Membuat seluruh kerangka Vue (Landing Page dan Dashboard).
- Memecah *Landing Page* menjadi modular (Navbar, Hero, TracerStudy, Statistics, Alumni, dll).

## [2026-09-01] Step 1: Inisiasi Proyek
- Membuat proyek Laravel 11.
- Mengonfigurasi Tailwind CSS v4, Vue 3, dan Inertia.js.
- Menyusun panduan arsitektur (Backend-Only Logic, UI Components).
