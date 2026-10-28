# Changelog Tracer Study

## [2026-10-28] Penyelarasan Visualisasi Grafik Universitas & Harmoni Indikator Laporan Resmi

- **Indikator 16 (Kompetensi Saat Lulus, Diperlukan Kerja, dan Gap Selisih)**:
  - Mengimplementasikan *Dual Y-Axis* (Sumbu Ganda): Sumbu Y Kiri (0 - 5) untuk Skor Likert batang bekal kampus vs kebutuhan dunia kerja, dan Sumbu Y Kanan (-3 s/d +3) khusus untuk garis gap selisih surplus/defisit (`z: 10`, ketebalan 3px, penanda titik bulat).
  - Menerapkan palet warna institusi yang harmonis dan seimbang: Batang Saat Lulus menggunakan Hijau UKDW (`#0D542B`), Batang Diperlukan Kerja menggunakan Slate Netral Lembut (`#94A3B8`), dan Garis Gap Selisih menggunakan Slate Tegas (`#334155`) dengan garis acuan putus-putus Impas (0) pada `#CBD5E1`. Menghilangkan warna oranye yang silau/bentrok.
  - Analisis naratif Gambar 4.17 dinamis membaca 7 dimensi kompetensi aktual (menyebutkan gap surplus tertinggi, defisit terdalam, dan urutan lengkap).
- **Indikator 13 (Keselarasan Tingkat Pendidikan / Vertical Match)**:
  - Mengubah grafik perbandingan keselarasan vertikal per program studi menjadi Diagram Line murni (`#0D542B`) sesuai format Gambar 4.14 laporan resmi.
  - Memindahkan analisis naratif Gambar 4.14 ke Indikator 13 dan mengonfigurasikannya secara dinamis membaca data aktual prodi (menyebutkan prodi keselarasan tertinggi, terendah, dan rincian urutan seluruh prodi).
- **Indikator 12 (Hubungan Bidang Studi / Horizontal Match)**:
  - Memperbarui analisis naratif Gambar 4.12 menjadi data-driven dinamis membaca data aktual prodi tanpa teks statis hardcoded.
- **Indikator 11 (Statistik Pendapatan per Bulan & UMP)**:
  - Menghapus peta spasial MapLibre dan mengganti View ke-3 menjadi tab "Status per Provinsi" dengan tabel analitik dan chart horizontal yang memuat jumlah alumni, kelayakan ambang 1,2× UMP, persentase kelayakan, dan median gaji per provinsi penempatan.
  - Menyajikan grafik pendapatan per prodi sebagai Line Chart 3 parameter: Kuartil 1 (Q1), Median (Q2 di-highlight hijau primer UKDW), dan Kuartil 3 (Q3) untuk membandingkan dispersi interkuartil.
- **Indikator 10 (Studi Lanjut dan Sumber Biaya)**:
  - Memasukkan seluruh pilihan master kuesioner resmi instrumen ke dalam visualisasi dan tabel (5 jenjang: S1, S2, S3, Profesi, Spesialis; dan 5 skema pembiayaan F12: Biaya Sendiri/Keluarga, Beasiswa Pemerintah, Beasiswa Swasta/Perusahaan, Beasiswa Luar Negeri, Lainnya) dengan sorting descending berdasarkan jumlah alumni terbanyak.
- **Indikator 06 & Indikator 02 (KPI Cards & Format Bilangan Bulat INT)**:
  - Header KPI cards pada pipeline lamaran (F6, F7, F7A) diselaraskan menampilkan median valid tanpa tanda strip `-`.
  - Pada Indikator 02 (Metode Pembelajaran): Menghapus seluruh angka pecahan desimal dari tabel utama, ringkasan kartu, header drill-down, dan tabel rincian prodi; seluruh skor disajikan dalam bilangan bulat INT (skala 1 s/d 5) serta label kategori kuesioner.
- **Pemberantasan Tumpang Tindih Sumbu X Kategori Program Studi**:
  - Menerapkan rotasi 30 derajat (`rotate: 30`) dan pelebaran margin canvas grid bawah (`bottom: 80, containLabel: true`) pada seluruh diagram garis perbandingan program studi (`11_SalaryVsUmp.vue`, `13_EducationAlignment.vue`, `12_FieldAlignment.vue`, `06_ApplicationPipeline.vue`, `05_WaitTime.vue`) sehingga nama prodi panjang (seperti *Pendidikan Bahasa Inggris*, *Teknologi Pangan*, dll.) tidak lagi bertabrakan (*nabrak*).
- **Komponen Wadah Visualisasi Terpadu (`AnalyticsTabbedContainer.vue`)**:
  - Menyatukan beberapa visualisasi ke dalam satu kontainer kokoh dengan tab switcher, seamless inline drill-down tanpa perpindahan halaman/tab terpisah ("Don't Make Me Think"), kartu ringkasan alami "Kilas Indikator", dan seleksi dua arah data chart.

## [2026-10-22] Konsistensi Antarmuka Multi-Role

- Menambahkan komponen navigasi dan header admin bersama agar Superadmin, Fakultas, Prodi, dan Biro III menggunakan pola antarmuka yang konsisten.
- Menyatukan halaman Master Data Perusahaan lintas Superadmin, Fakultas, dan Prodi tanpa memperluas wewenang data: perbedaan hanya pada route dasar, pilihan filter, dan query scope backend.
- Menambahkan dokumentasi arsitektur terpusat di [ARCHITECTURE.md](ARCHITECTURE.md).

> Arsip perubahan historis. Keterangan fitur/angka pengujian berlaku pada waktu pencatatan, bukan jaminan kondisi terkini. Rujukan revisi 21 Oktober 2026: [DFD](dfd.md) dan [user flow](user-flow.md).
Semua perubahan besar pada sistem dicatat dalam dokumen ini.

## [2026-10-17]
- **Dokumentasi Lengkap & Kueri DDL Seluruh Database Views ([`database-views.md`](database-views.md) & [`ERD.md`](../ERD.md))**:
  - Menyusun dokumen panduan teknis terpusat [`docs/database-views.md`](database-views.md) yang merangkum latar belakang arsitektur (eliminasi query N+1), diagram hierarki ketergantungan view (Mermaid), kamus data per view, dan skrip DDL SQL lengkap (*ready-to-run*) untuk seluruh 7 database views:
    1. `v_alumni_profile_summary`: Rekapitulasi profil lengkap 4 sub-tab (Pribadi, Akademik, Orang Tua, Karier).
    2. `v_alumni_tracer_univ_status`: Agregasi kelengkapan kuesioner universitas dengan *skip logic* status F8 dan F502/F506.
    3. `v_alumni_tracer_prodi_status`: Agregasi butir kuesioner mandiri program studi per alumni.
    4. `v_alumni_audit_rekap`: Master audit 89 kolom untuk dashboard & audit direktori.
    5. `v_alumni_kuesioner_autofill`: Pemetaan atribut profil ke kode butir kuesioner (F1, F2A-F2H, F5B-F5D, F510, F8, dll).
    6. `v_question_mappings`: Kamus relasi butir kuesioner dengan tabel sumber basis data.
    7. `v_alumni_linkedin_plot`: Pemetaan data scraping LinkedIn ke biodata alumni, verifikasi kesesuaian nama (eksak/parsial), dan deteksi kesamaan perusahaan/posisi.
  - Menyediakan *All-in-One Master SQL Runner Script* untuk mengeksekusi dan membuat ulang seluruh 7 view sesuai urutan dependensinya.
- **Implementasi Database View Hasil Scraping LinkedIn ([`v_alumni_linkedin_plot`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/database/migrations/2026_10_17_000000_create_v_alumni_linkedin_plot_view.php) & [`AlumniLinkedinPlot.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Models/AlumniLinkedinPlot.php))**:
  - Membuat Database View `v_alumni_linkedin_plot` dan model Eloquent [`AlumniLinkedinPlot`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Models/AlumniLinkedinPlot.php) yang memetakan data staging LinkedIn (`linkedin_sync_results`) berdampingan dengan profil aktual `biodata`.
  - Dilengkapi logika verifikasi kesesuaian nama (`is_nama_sesuai`, `keterangan_nama`), deteksi plot ke biodata (`is_terplot_ke_biodata`), dan status plotting (`status_plot_label`).
  - Dilengkapi query scopes: `scopeNamaSesuai()`, `scopeTerplot()`, dan `scopePendingReview()`.
- **Refactoring Dashboard Controllers & Export Excel Menggunakan Database Views**:
  - Merefaktor [`DashboardController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/AdminProdi/Dashboard/DashboardController.php) (Admin Prodi), [`DashboardController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/SuperAdmin/Dashboard/DashboardController.php) (Super Admin), dan [`DashboardController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/AdminBiroTiga/Dashboard/DashboardController.php) (Biro 3) dari perulangan PHP N+1 menjadi kueri agregasi cepat berbasis `v_alumni_audit_rekap`.
  - Merefaktor [`AlumniTracerExcelExporter.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Services/Export/AlumniTracerExcelExporter.php) untuk menggunakan `v_alumni_profile_summary` sebagai sumber data profil primer.
- **Verifikasi Pengujian Otomatis (Automated Tests)**:
  - Seluruh pengujian fitur lulus 100%: [`AlumniLinkedinPlotViewTest.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/tests/Feature/AlumniLinkedinPlotViewTest.php) (6 lulus, 31 assertions), `AdminProdiDashboardTest.php` (2 lulus, 19 assertions), `SuperAdminDashboardTest.php` (2 lulus, 18 assertions), `BiroTigaDashboardTest.php` (3 lulus, 14 assertions), dan 47 tes LinkedIn suite lulus (165 assertions).

## [2026-10-15]
- **Transparansi Hasil Sinkronisasi LinkedIn & Manajemen Keahlian (Skills) ([`Index.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/SuperAdmin/LinkedIn/Index.vue), [`LinkedinSyncResult.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Models/LinkedinSyncResult.php), [`LinkedInSyncController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/Shared/LinkedInSyncController.php))**:
  - Memperluas modal peninjauan hasil sinkronisasi LinkedIn (`max-w-5xl`) dengan tab switcher:
    - **Ringkasan Terstruktur**: Menampilkan rincian riwayat pendidikan lengkap (dengan deteksi almamater UKDW), pengalaman kerja komprehensif, tautan langsung pembuktian ke profil asli LinkedIn, statistik koneksi/pengikut, ringkasan diri (*About*), dan riwayat sukarelawan (*volunteering*).
    - **{ } JSON Mentah**: Menampilkan seluruh respons payload mentah Apify Dataset dalam penampil teks kode gelap (*syntax highlight*) dengan tombol salin cepat ke *clipboard*.
  - Menambahkan kolom input **Keahlian (Skills)** pada form penyesuaian usulan sebelum data disetujui (*Approve*), dilengkapi tombol *Quick Add Pills* dan tombol *Gunakan Semua* dari keahlian hasil scraping LinkedIn.
  - Memperbarui `approveResult` pada controller agar menyimpan keahlian kustom langsung ke kolom `biodata.skills` dan `biodata.expert`, serta pengalaman kerja ke `biodata.experience` dan `biodata.minat`.
  - Memastikan integritas data murni: jika LinkedIn tidak menyediakan data keahlian (misal disembunyikan oleh setelan privasi profil alumni untuk guest), keahlian dibiarkan kosong apa adanya murni dari hasil scraping tanpa manipulasi tebakan sistem.
  - Menambahkan feature test komprehensif `test_24_approve_with_custom_skills_saves_to_alumni_profile` pada [`ApifyLinkedInProviderTest.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/tests/Feature/ApifyLinkedInProviderTest.php) dengan seluruh 41 tes LinkedIn lulus (*100% Passed, 134 assertions*).
- **Perbaikan & Penyederhanaan Antarmuka Dashboard Alumni & Navbar ([`Dashboard.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Dashboard.vue) & [`Navbar.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Components/Navbar.vue))**:
  - Menghapus badge decorative dengan titik kuning (`Portal Resmi Alumni Tracer Study UKDW`) dan kartu ringkasan progres kelengkapan 4 tahap dari header banner solid hijau UKDW agar tampilan lebih bersih, minimalis, dan elegan.
  - Menghapus kartu banner notifikasi aksi cepat (*Langkah Selanjutnya*) dan kartu survei atasan (*Tahap 4 Evaluasi Pengguna Lulusan*) dari dashboard.
  - Merapikan dashboard alumni agar langsung berfokus pada 3 kartu utama: **Tahap 1: Profil & Biodata**, **Tahap 2: Kuesioner Universitas**, dan **Tahap 3: Kuesioner Prodi**.
  - Mengatur navigasi navbar: menu navigasi tautan tengah disembunyikan saat pengguna berada di `/alumni/dashboard` karena navigasi sudah langsung dapat diakses lewat kartu-kartu di dalam halaman.
  - Mengubah tombol keluar pada navbar menjadi ikon logout mandiri bernuansa merah (`text-rose-600`) tanpa teks *"Keluar"*, lengkap dengan dialog konfirmasi SweetAlert.
- **Pemisahan Pengiriman Email Kuesioner Evaluasi Atasan (Manual On-Demand)**:
  - Memisahkan pengiriman email ke atasan dari proses simpan profil di [`SimpanProfilController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/Alumni/Profil/SimpanProfilController.php) untuk mencegah email terkirim otomatis saat masih ada kesalahan ketik data atasan.
  - Menambahkan endpoint rute khusus `POST /alumni/kirim-email-atasan` (`alumni.kirim-email-atasan`) pada [`routes/web.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/routes/web.php).
  - Menambahkan panel aksi khusus dan tombol **"Kirim Email ke Atasan"** dengan konfirmasi SweetAlert serta status penandaan pada Card 3 Formulir Karier Alumni ([`FormKarier.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Components/AlumniProfil/FormKarier.vue) & [`Index.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/Profil/Index.vue)).
- **Standardisasi & Otomasi Pembuatan Opsi Default Butir Pertanyaan (Prodi & Universitas)**:
  - Mengembangkan service terpusat [`app/Services/Kuesioner/QuestionDefaultOptionsService.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Services/Kuesioner/QuestionDefaultOptionsService.php) untuk mengotomasi penambahan pilihan opsi awal saat butir pertanyaan baru dibuat:
    - `radio_text` (Pilihan Ganda + Lainnya): 2 opsi baku (`Pilihan 1`, `Pilihan 2`) + 1 opsi bertrik khusus input teks bebas (`Lainnya (Tuliskan)`).
    - `rating_5` (Skala Rating 1-5): 5 opsi skala terurut (`1 - Sangat Rendah`, `2 - Rendah`, `3 - Cukup`, `4 - Tinggi`, `5 - Sangat Tinggi`). Admin dan Super Admin dapat mengkustomisasi kalimat/skor via modal kelola opsi.
    - `radio_input` (Radio + Angka Bulan): 1 opsi berkolom numerik bertitik (`Kira-kira ... bulan`) + 1 opsi non-aktif/penolakan (`Tidak`).
    - `single_choice`, `multiple_choice`, `dropdown`, `searchable_select`: 2 opsi pilihan default (`Pilihan 1`, `Pilihan 2`).
  - Terintegrasi langsung pada controller:
    - [`SimpanPertanyaanProdiController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/AdminProdi/KelolaPertanyaan/SimpanPertanyaanProdiController.php) (Admin Prodi)
    - [`KelolaKuesionerProdiSuperAdminController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/SuperAdmin/KelolaPertanyaan/KelolaKuesionerProdiSuperAdminController.php) (Super Admin Prodi)
    - [`SimpanPertanyaanController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/SuperAdmin/KelolaPertanyaan/SimpanPertanyaanController.php) (Super Admin Universitas)
- **Pembersihan & Eliminasi Tipe Pertanyaan Redundan**:
  - Menghilangkan entri bertingkat/duplikat pada antarmuka dropdown modal tambah/edit pertanyaan di Super Admin ([`Index.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/SuperAdmin/Pertanyaan/Index.vue)) dan Prodi ([`Index.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/AdminProdi/Pertanyaan/Index.vue)).
  - Menyembunyikan tipe matriks skala yang sudah tidak relevan/usang (`matrix`, `matrix_dual`, `multiple_textbox`).
  - Memperjelas penamaan label tipe `multiple_number` menjadi **"Rincian Nominal / Gaji (Take Home Pay)"**.
- **Refactoring & Pemulihan Frontend Kuesioner Prodi ([`KuesionerProdi.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Alumni/KuesionerProdi.vue))**:
  - Menambahkan template kartu interaktif responsif untuk tipe `radio_text` (dilengkapi input teks isian langsung saat opsi 'Lainnya' dipilih), `radio_input` (dengan kolom angka dan label 'Bulan'), `dropdown`, `searchable_select`, `textarea`, dan `time`.
  - Mengeliminasi duplikasi deklarasi fungsi helper `isCheckboxChecked` dan `toggleCheckboxOption` yang memicu kegagalan build Vite SFC.
  - Memperbaiki regex pemotongan prefix skor pada header matriks skala 1-5 (`replace(/^\d+\s*[-=:]\s*/, '')`), sehingga teks opsi seperti `1 - Sangat Rendah` tidak lagi menduplikasi angka indeks `1`.
- **Perluasan Otorisasi Penambahan Perusahaan Multi-Role (`SimpanProfilController.php` & `routes/web.php`)**:
  - Memperluas middleware otorisasi rute `POST /alumni/company` pada [`routes/web.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/routes/web.php) menjadi `role:alumni,admin_prodi,admin_fakultas,admin_biro3,superadmin`.
  - Memperbaiki pengondisian mutasi relasi `biodata->update(['perusahaan_id' => ...])` pada [`SimpanProfilController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/Alumni/Profil/SimpanProfilController.php) agar khusus dijalankan bagi akun alumni, menjaga integritas relasi profil alumni ketika staf/admin menambah instansi perusahaan dari antarmuka verifikasi.
- **Automated Feature Testing & Standar Mutu**:
  - Pengujian fitur lengkap lulus 100%: `tests/Feature/AdminProdiKuesionerTest.php`, `tests/Feature/SuperAdminKelolaPertanyaanTest.php`, `tests/Feature/BiroTigaKelolaPertanyaanTest.php`, dan `tests/Feature/VerifikasiPerusahaanTest.php`.
  - Pemformatan kode PSR-12/Laravel Pint berhasil (`vendor/bin/pint --dirty --format agent`).
  - Kompilasi Vite production bundle (`npm run build`) berjalan bersih tanpa galat.

## [2026-10-11]
- **Standardisasi Desain Stepper Horizontal Kuesioner Alumni pada Detail Alumni Multi-Role**:
  - Mengimplementasikan komponen Stepper horizontal terpadu (mengadopsi estetika kuesioner universitas alumni `Stepper.vue`) pada seluruh antarmuka detail alumni:
    - `SuperAdmin/Alumni/Show.vue`
    - `AdminProdi/Alumni/Show.vue`
    - `AdminFakultas/Alumni/Show.vue`
    - `AdminBiroTiga/AlumniShow.vue`
  - Aksen visual resmi UKDW: Lingkaran aktif berwarna emas UKDW (`#FFD700`) teks hijau botol (`#005B3C`), lingkaran tuntas berwarna hijau tua almamater (`#005B3C`) dengan tanda centang putih bersih (`✓`), lingkaran belum selesai abu-abu bersih (`bg-gray-100 text-gray-400`) dengan nomor tahapan, mini badge centang di kanan atas lingkaran aktif saat 100% lengkap, serta baris garis penghubung hijau/abu-abu.
  - Menghapus Card Ringkasan Status Audit redundan (3 kolom progress bar) dan tombol `&larr; Kembali ke Daftar` di header untuk memperluas ruang pandang data inti dan menjaga konsistensi alur pengguna. Navigasi kembali terpusat melalui tautan Breadcrumbs.
  - Menyatukan data profil alumni pada Biro 3 ke dalam 1 tabel kontinu terpadu per seksi dengan filter seksi instan, mengeliminasi sub-tab bersarang ganda.
- **Penyelarasan Kolom Status & Aksi Terpadu Direktori Mahasiswa (Index)**:
  - Menyederhanakan kolom status menjadi format terpadu 3-in-1 (Profil, Kuesioner Universitas, Evaluasi Atasan) yang menampilkan persentase kelengkapan dan badge status jelas tanpa memakan ruang berlebih.
  - Menyediakan toolbar aksi cepat di setiap baris data mahasiswa: Tombol Detail (`👁️`), Hubungi via WhatsApp (`💬`) dilengkapi template pesan dinamis, URL kuesioner & cara login alumni, Kirim Email Pengingat Flash (`✉️`), serta Sinkronisasi LinkedIn Direct & Modal Riwayat Scraping (`🔗` / `H`).
- **Mailable & Fitur Kirim Email Pengingat Kuesioner Alumni**:
  - Menambahkan kelas Mailable `App\Mail\PengingatKuesionerAlumniMail` dan template email Blade responsif `resources/views/emails/pengingat_kuesioner_alumni.blade.php`.
  - Integrasi endpoint kirim email langsung per alumni di semua controller direktori alumni dengan pop-up notifikasi SweetAlert2.
- **Penyelarasan Algoritma Tahun Kelulusan & Filter Dashboard/Direktori**:
  - Menyamakan kalkulasi dan filter tahun kelulusan mahasiswa/alumni antara dashboard rekapitulasi, direktori alumni, dan service kelengkapan tracer study di seluruh tingkatan peran (Super Admin, Biro 3, Fakultas, Prodi).
- **Pengujian & Kualitas Kode**:
  - Menambahkan automated feature tests `tests/Feature/BiroTigaDaftarAlumniTest.php` dan memperbarui `tests/Feature/SuperAdminDaftarAlumniTest.php`. Seluruh tes lulus (100% passed).
  - Standarisasi format kode Laravel Pint (`vendor/bin/pint --dirty --format agent`).
  - Kompilasi Vite production bundle lulus bersih tanpa galat.

## [2026-10-09]
- **Penyelarasan Desain & Antarmuka Direktori Alumni Multi-Role**:
  - Menghapus kolom Status yang tidak relevan/redundan pada tabel alumni di Super Admin, Admin Fakultas, dan Admin Prodi sehingga visual tabel lebih lega, rapi, dan konsisten.
  - Menyamakan struktur halaman Detail Alumni (`Show.vue`) pada Admin Fakultas dan Admin Prodi menjadi 5 tab komprehensif identik dengan Super Admin: Tab 1 (Kuesioner Universitas), Tab 2 (Kuesioner Prodi), Tab 3 (Data Profil Lengkap), Tab 4 (Hasil Scraping & Trace LinkedIn), dan Tab 5 (Evaluasi Pengguna Lulusan / Atasan).
  - Melakukan ekstraksi service helper bersama [`app/Services/Alumni/LinkedinTraceHelper.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Services/Alumni/LinkedinTraceHelper.php) untuk menyatukan logika perbandingan field database vs hasil scraping, pemetaan atribut, dan audit trail histori sinkronisasi LinkedIn di semua controller stakeholder.
- **Perbaikan Bug Tata Letak Layar Alumni Terpotong (Sidebar Padding Fix)**:
  - Memperbaiki padding responsif `lg:pl-72` pada elemen `<main>` di [`resources/js/Pages/AdminProdi/Alumni/Index.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/AdminProdi/Alumni/Index.vue) dan [`resources/js/Pages/AdminFakultas/Alumni/Index.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/AdminFakultas/Alumni/Index.vue).
  - Mengatasi masalah konten yang tertimpa oleh fixed sidebar `w-72`, sehingga judul direktori, 4 kartu metrik statistik alumni, foto avatar, nama lengkap, dan data tabel dapat diakses dengan jelas dan proporsional di semua resolusi layar desktop.
- **Penyempurnaan Pipeline Normalisasi Teks & Algoritma Rekomendasi Kecocokan Perusahaan (`PerusahaanVerificationService.php`)**:
  - Penambahan pembersihan string tanda kurung `(...)` (seperti `(Kantor Pusat)`, `(Persero)`) pada fungsi `cleanCompanyName()` untuk memastikan pencocokan nama berfokus pada entitas pokok badan usaha.
  - Perluasan daftar stop words unit kantor, cabang, dan wilayah (`'kantor', 'pusat', 'cabang', 'branch', 'subcabang', 'kcu', 'kc', 'kcp', 'unit', 'witel', 'regional', 'wilayah', 'area', 'divisi', 'division', 'office', 'hq', 'head', 'representative'`).
  - Pembuatan akronim natural otomatis: Mengakomodasi nama panjang berformat standar seperti `PT Bank Central Asia Tbk` $\rightarrow$ `bca`, cocok dengan `PT BCA INDONESIA` pada Level 2 (Skor 88%).
  - Penambahan Level 3B (Compound Token Acronym Intersect): Mendeteksi akronim parsial pada nama majemuk seperti `BCA Digital` $\leftrightarrow$ `Bank Central Asia` (Skor 70-80%).
  - Ambang batas Token Overlap Ratio disesuaikan menjadi minimal 33% (sepertiga bagian nama) untuk menangani variasi nama dengan panjang token berbeda secara fleksibel dan proporsional.
  - Filter Klasifikasi Sektor Industri Generik Tunggal: Menambahkan `$genericIndustryWords` (`'bank', 'universitas', 'univ', 'institut', 'sekolah', 'rs', 'hospital', 'hotel', 'studio', 'restoran', 'resto', 'cafe', 'toko', 'media', 'lab', 'laboratorium'`) agar entitas seperti `Bank Mandiri` tidak lagi merekomendasikan seluruh bank lain di Indonesia hanya karena kata "Bank", namun tetap merekomendasikan `CV Theresia Inovasi Mandiri` (58%).
- **Penerapan Kebijakan Ketat Larangan Git Push Otomatis (`.agents/rules/git-push-policy.md` & `AGENTS.md`)**:
  - Menyusun aturan tetap dalam proyek bahwa agen AI dilarang keras melakukan `git push` mandiri tanpa instruksi eksplisit pengguna pada sesi pesan tersebut. Semua hasil pekerjaan wajib diuji dan dilaporkan secara lokal terlebih dahulu.
- **Automated Testing & Kualitas Kode**:
  - 124 feature & unit tests lulus (100% passed, 747 assertions).
  - Pint formatting lolos standar PSR-12/Laravel Pint.
  - Vite build bundle sukses tanpa galat.

## [2026-10-08]
- **Deep Linking Terarah Detail Alumni ke LinkedIn Sync**: Menghubungkan banner *Audit & Trace Hasil Scraping LinkedIn* pada Detail Alumni (`/superadmin/alumni/{id}`) langsung ke `/superadmin/linkedin-sync?alumni_id={id}&search={nim}` dengan auto-select tahun lulus alumni, filter pencarian otomatis, highlight cincin hijau emerald ("Profil Dipilih"), dan auto-scroll viewport ke baris profil alumni.
- **Tombol Aksi Cepat Ikon Mata di LinkedIn Sync**: Menyematkan tombol aksi ikon mata pada setiap baris alumni di tabel LinkedIn Sync (`/superadmin/linkedin-sync`) untuk navigasi instan ke halaman Detail Alumni lengkap (`/superadmin/alumni/{id}`).
- **Standarisasi Kolom `skills` & `experience`**: Melakukan migrasi penggantian nama kolom `expert` menjadi `skills` dan `minat` menjadi `experience` pada tabel `biodata`, serta mereplikasi pembaruan pada view database `v_alumni_profile_summary` dan `v_alumni_audit_rekap`.
- **Ekspor Excel & ZIP per Prodi**: Penyempurnaan ekspor data profil dan lembar evaluasi atasan ke format spreadsheet Excel per prodi pada filter tahun terbaru.
- **Automated Testing & Code Quality**: 123 automated feature & unit tests lulus (745 assertions), linting Pint agent lulus, dan Vite build sukses tanpa kendala.

## [2026-10-06]
- **Integrasi Apify LinkedIn Provider & Multi-Provider Architecture**: Menambahkan driver `ApifyLinkedInProvider` untuk integrasi web scraping pihak ketiga menggunakan Actor `data_forge_org~linkedin-scraper` via autentikasi aman Bearer token (`APIFY_API_TOKEN`). Mendukung pemilihan driver via `LINKEDIN_PROVIDER=official`, `LINKEDIN_PROVIDER=apify`, atau `LINKEDIN_DRIVER=mock` di `.env`.
- **Tabel Staging & Histori Audit (`linkedin_sync_results`)**: Hasil scraping diisolasi ke tabel staging `linkedin_sync_results` berstatus `pending` lengkap dengan payload mentah JSON (`raw_response`), parameter input (`input_params`), perbandingan data (`mapped_data`), serta jejak auditor (`reviewed_by`, `reviewed_at`).
- **Alur Review & Persetujuan SuperAdmin (Approval Workflow)**: Antarmuka review di SuperAdmin untuk membandingkan data scraping vs data existing dengan aksi Approve (menerapkan jabatan dan mendaftarkan perusahaan dengan status `Menunggu Verifikasi`) dan Reject (menolak hasil tanpa mengubah data utama).
- **Download Permanen Foto Profil LinkedIn ke Disk Lokal**: Mengunduh foto profil LinkedIn secara fisik ke `public/uploads/profile/profile_{nim}_{timestamp}.jpg` agar tidak bergantung pada CDN LinkedIn sementara yang memiliki expiry token (`?e=...`).
- **Form Foto Profil & Unggah Mandiri di Biodata Alumni**: Menambahkan kartu preview avatar foto profil interaktif pada `FormPribadi.vue` dan handler upload pada `SimpanProfilController.php` dengan badge sumber data (*LinkedIn Sync* / *Unggah Mandiri*).
- **Tab 4 Detail Alumni: Tabel Audit & Trace Pemetaan Scraping (`Show.vue`)**: Menyediakan tab khusus *"Hasil Scraping & Trace LinkedIn"* pada halaman Detail Alumni SuperAdmin (`/superadmin/alumni/{id}`) yang memuat pencarian instan, JSON viewer interaktif, riwayat sinkronisasi, dan tabel perbandingan atribut scraping vs target kolom database vs nilai riil terkini.
- **Sinkronisasi Otomatis Status Karier & Opsi Jabatan Kustom (`FormKarier.vue`)**: Normalisasi `kategori_pekerjaan` menjadi `'Pekerja'` agar kartu status kerja langsung terpilih otomatis, serta menambahkan penanganan dinamis jabatan kustom yang belum ada di referensi baku kampus dengan toggle switch `+ Tulis Jabatan Kustom`.
- **Perbaikan Rute URL Aliasing `/superadmin/linkedin`**: Menambahkan alias rute `/superadmin/linkedin` ke `LinkedInSyncController@index` guna mengatasi error 404 ketika pengguna mengakses tautan pendek menu LinkedIn.
- **Automated Testing**: Menambahkan 23 skenario feature test di `tests/Feature/ApifyLinkedInProviderTest.php` dan 17 skenario di `tests/Feature/SuperAdminLinkedInSyncTest.php` (100% passed).

## [2026-10-03]
- **Fleksibilitas Aksi & Auto Replace Master Perusahaan**: Membuka akses aksi verifikasi (*Verify*, *Edit*, *Auto Replace*, *Reject*) pada data perusahaan berstatus **Terverifikasi** di SuperAdmin, Admin Prodi, dan Admin Fakultas, serta menghapus batasan `->where('status_verifikasi', 'Menunggu Verifikasi')` di controller untuk mencegah error 404.
- **Penyempurnaan Modal Edit Akun & Pre-select Fakultas**: Memperbaiki komputasi `currentFakultasId` pada modal **Sunting Data Akun Pengguna** (`SuperAdmin/ManajemenAkun/Index.vue`) agar Fakultas alumni/admin terisi otomatis secara akurat, serta menambahkan listener penyesuaian otomatis saat Program Studi diubah.
- **Fitur Sinkronisasi LinkedIn (Driver-Based Switch via .ENV)**: Halaman Sinkronisasi LinkedIn SuperAdmin dengan 5 kartu metrik analitik, aksi sinkronisasi individual & massal per angkatan, dan abstraksi `LINKEDIN_DRIVER` (`mock` / `api`) via `AppServiceProvider`.
- **Sentralisasi Data CSV (/data) & Seeder Wilayah/Negara**: Memindahkan berkas master CSV ke `/data`, menyempurnakan `RefNegaraSeeder.php` dan `WilayahSeeder.php` dengan stream `fgetcsv` & proteksi *unique constraint*.

## [2026-09-30]
- **Fitur Otomatis Reset Password via Gmail / SMTP**: Integrasi pengiriman email pemulihan kata sandi berbasis SMTP Google App Passwords dengan Mailable `ResetPasswordMail.php`, template HTML Blade `reset_password.blade.php`, Controller `LupaKataSandiController.php`, form modal interaktif di `Login.vue`, dan halaman Inertia Vue `ResetPassword.vue`.
- **Pengutamaan Prioritas Email Pribadi Biodata**: Mengubah logika accessor `getEmailAttribute()` pada model `User.php` dan `Biodata.php` agar mengutamakan `email_pribadi` pada tabel `biodata` (email aktif alumni terkini).
- **Keseragaman Tampilan Master Data Perusahaan**: Menyeragamkan halaman Master Data & Verifikasi Perusahaan di 3 tingkatan peran (SuperAdmin, Admin Fakultas, dan Admin Prodi) dengan tab navigasi filter status (*Semua Perusahaan*, *Menunggu Verifikasi*, *Master Terverifikasi*) serta fitur rekomendasi kecocokan nama otomatis (*fuzzy matching & token overlap*).
- **Tabel Log Aktivitas & Audit Trail SuperAdmin**: Menambahkan rute `/superadmin/logs`, Controller `LogAktivitasController.php`, dan komponen Vue `SuperAdmin/Logs/Index.vue` lengkap dengan widget statistik, pencarian realtime, filter jenis aksi, dan modal detail JSON (*old values* & *new values*).
- **Migrasi Kolom `created_by_user_id` & `created_by_prodi_id`**: Menambahkan migrasi `2026_09_30_125458_add_created_by_user_id_to_perusahaan_table.php` untuk menjamin integritas tabel `perusahaan`.

## [2026-09-28]
- **Penyesuaian Warna Per Section Landing Page**: Background section Sambutan WR III diatur hijau botol UKDW pekat (`bg-gradient-to-b from-[#00381A] via-[#004D25] to-[#003318]`) dan background section Alur Partisipasi diatur kuning cerah UKDW (`bg-gradient-to-b from-[#FFC700] via-[#FBBF24] to-[#F59E0B]`) berpadu kartu kontras tinggi mutiara.
- **Eliminasi Total Warna Biru di Landing Page**: Menghilangkan seluruh warna dan aksen biru/cyan di komponen landing page, digantikan dengan hijau UKDW (`#004D25`) dan emas (`#FFC700`).
- **Navbar Tema iOS Glossy Bening**: Navbar secara dinamis mendeteksi scroll layar; saat di-scroll otomatis bertransisi menjadi frosted glass glossy bening khas iOS (`backdrop-blur-2xl backdrop-saturate-200 border-b border-white/20 shadow-lg`).
- **Desain Otentikasi Non-Tumpul (Eliminasi AI Rounded Look)**: Mengubah bentuk kartu, form input, tombol, dan modal di `Login.vue` dan `UbahKataSandi.vue` dari rounded bulat tumpul (`rounded-[2.5rem]`, `rounded-full`) menjadi sudut tegas enterprise (`rounded-xl`, `rounded-lg`, `rounded-md`).
- **Autentikasi Alumni Password Tanggal Lahir & Siklus Ubah Sandi**: Menghubungkan proses login alumni di `LoginController.php` dengan kata sandi bawaan tanggal lahir format `ddmmyyyy` (`15082001`). Alumni dengan password default wajib mengubah sandi di `/change-password`, dan setelah berhasil diubah, login berikutnya langsung menuju dashboard seperti biasa.
- **Migrasi Kolom Foto Alumni (`add_foto_to_biodata_table`)**: Menambahkan kolom `foto` (nullable string) pada tabel `biodata` yang dialokasikan untuk path foto profil alumni (`/uploads/profile`) serta memperbarui model `Biodata.php`.
- **Eksplisit Relasi Yudisium & Skripsi (`Biodata.php`)**: Memperbarui relasi `yudisium()` pada model `Biodata` ke `belongsTo(Yudisium::class, 'yudisium_id')` untuk menghubungkan data kelulusan, judul tugas akhir (`judul_ta`), URL repositori karya ilmiah (`url_publikasi`), dan jenis publikasi (`jenis_publikasi`).
- **Agregasi Geografis & Master Wilayah Beranda (`BerandaController.php`)**: Menghitung agregasi spasial `alumniWilayah` (sebaran domisili, sebaran kantor/perusahaan, dan ringkasan per provinsi berisi daftar nama alumni serta daftar instansi untuk tooltip hover peta) dan menyediakan referensi `allProvinsi` serta `allKabupaten`.
- **Modularisasi Komponen Landing Page (`resources/js/components/landing/`)**: Memecah halaman landing page publik menjadi 10 komponen terisolasi (`hero-section.vue`, `alumni-map-section.vue`, `testimonial-slider-section.vue`, `stats-transformation-section.vue`, `sektor-alumni-section.vue`, `career-pillars-section.vue`, `user-guide-section.vue`, `berkas-section.vue`, `blog-section.vue`, `tentang-section.vue`).
- **Peta Interaktif 2 Kolom & SweetAlert2 Detail**: Peta Leaflet GeoJSON 38 provinsi di sisi kiri dan daftar alumni grid 2x2 di sisi kanan dengan filter multi-dimensi, hover tooltip informatif, serta dialog SweetAlert2 lengkap dengan foto profil, judul skripsi, dan link publikasi repositori.
- **Etalase Karya & Tugas Akhir Alumni**: Menggantikan slider testimoni dengan etalase karya ilmiah dan skripsi alumni UKDW dengan tautan langsung repositori kampus.
- **Standardisasi Maskot Pigo & Aset Gambar**: Menempatkan aset resmi di `public/uploads/pigo/`, membuat loader `pigo-loader.vue`, dan menyelaraskan logo resmi UKDW pada seluruh navbar.

## [2026-09-26]
- **Eliminasi Total Caption Video & Masking Bawah (`hero-section.vue`)**: Memotong area bawah YouTube video canvas (`-translate-y-[36%] scale-115`) dan menambahkan masking gradasi gelap bawah (`h-44`) untuk memastikan tidak ada takarir/caption YouTube yang tampak di layar.
- **Tata Letak 2 Kolom Sejajar (Filter Atas, Peta Kiri & Alumni Kanan) (`alumni-map-section.vue`)**: Menerapkan filter bar atas (Cari nama, jabatan, perusahaan, kode pos... | Dropdown Semua Kab/Kota | Tombol Lihat Semua), lalu di bawahnya dibagi 2 kolom: Kiri Peta OpenStreetMap Leaflet dan Kanan Daftar Alumni dalam grid 2 baris x 2 kolom (4 alumni per halaman) dengan paginasi ringkas.
- **Hover Peta Super Informatif (Menampilkan Nama PT & Nama Alumni)**: Tooltip Leaflet saat hover ke provinsi menampilkan data riil: Nama Provinsi, Total Alumni, Daftar Nama Perusahaan/Mitra, dan Daftar Nama Alumni di provinsi tersebut.
- **Kartu Alumni Langsung Nilai Tanpa Label Repetitif**: Menghilangkan label "Jabatan" dan "Perusahaan/Instansi", langsung menampilkan nama alumni, jabatan, perusahaan, kota wilayah, serta judul TA ringkas.
- **Detail Berbasis SweetAlert2**: Tombol Detail membuka pop-up SweetAlert2 lengkap dengan NIM, prodi, tahun lulus, alamat lengkap, kode pos/zipcode, judul TA & link publikasi repositori aktif, studi lanjut, medsos, serta tombol *"Sorot di Peta"*.
- **Pembersihan Elemen**: Menghapus badge & tulisan "Alumni Terverifikasi UKDW", menghapus opsi toggle "Domisili", dan menghapus tombol pilihan cepat.
- **Penggantian Section "Satisfied Alumni Speaks" Menjadi "Karya & Tugas Akhir Alumni UKDW" (`testimonial-slider-section.vue`)**: Mengganti slider testimoni dengan etalase riset dan tugas akhir alumni UKDW lengkap dengan judul skripsi, nama penulis, instansi karier, dan tautan repositori karya ilmiah.
- **Peta Sebaran Alumni Interaktif (Leaflet.js + OpenStreetMap + GeoJSON 38 Provinsi)**: Membangun modul peta interaktif berbasis OpenStreetMap dan GeoJSON 38 provinsi serta 518 kabupaten/kota (titik awal `[-2.55, 118.02]`, zoom 5). Dilengkapi visualisasi choropleth hijau-kuning, fitur klik polygon untuk zoom-in ke level kabupaten/kota, panel rincian wilayah, serta tombol reset ke peta nasional.
- **Kombinasi 2 Warna Utama (Hijau Hutan & Kuning Cerah)**: Menyelaraskan seluruh elemen visual antarmuka publik dengan kombinasi warna hijau pekat UKDW (`#0D542B` / `#00381e`), aksen kuning emas (`#FACC15` / `#EAB308`), dan badge soft mint `• Alumni Terverifikasi` (`bg-[#ECFDF5] border-[#A7F3D0] text-[#065F46]`).
- **Penyajian Data Dinamis & Riil Database**: Mengganti seluruh konten statis/dummy landing page dengan data riil dari database melalui `BerandaController`: statistik 120+ alumni, 32+ mitra perusahaan, 88+ responden, 12 prodi terintegrasi, 94% serapan kerja, dan testimonial riil dengan nama, prodi, jabatan, dan instansi alumni asli.
- **Migrasi & Standardisasi Aset Maskot Pigo & Logo UKDW**: Memindahkan seluruh aset gambar resmi ke `public/uploads/pigo/` (`loading-pigo.png`, `pigo-laptop.png`, `pigo-update-data.png`, `hallo-alumni.png`, `anak-it.png`, `pigo-dokter.png`) dan `public/uploads/logo/logo-ukdw.png`. Mengintegrasikan maskot pada `pigo-loader.vue`, `Login.vue`, `UbahKataSandi.vue`, serta logo resmi di seluruh bilah navigasi stakeholder.
- **Redesain Navigasi Kotak & Tipis (Flat Admin Style)**: Menghapus gradasi dan kotak ikon berlapis pada sidebar, mengubah bentuk menu menjadi kotak ramping (`rounded-lg`, `px-3 py-2`), serta meratakan header dashboard seluruh stakeholder (Prodi, Fakultas, SuperAdmin, Biro 3) menjadi header putih datar tanpa banner hijau raksasa agar terkunci stabil saat layar diperkecil.
- **SweetAlert2 Edit & Approval Perusahaan**: Mengubah fungsi sunting perusahaan menjadi pop-up dialog interaktif SweetAlert2 dengan filter dinamis provinsi/kabupaten dan validasi preConfirm di Admin Prodi & Admin Fakultas. Menghapus floating modal kustom lama.
- **Penyelarasan Warna & Bentuk Badges Status**: Mendesain ulang badge status "Menunggu Verifikasi" (warna krem #FFFBEB, border kuning #FDE68A, dot oranye #F59E0B) dan "Master Terverifikasi" (warna mint #ECFDF5, border #A7F3D0) dengan sudut rounded-2xl sesuai desain referensi.
- **Apple Glossy Sidebars di Semua Role Non-Alumni**: Menerapkan estetika frosted glass (backdrop-blur-2xl, bg-white/85), glossy gradient active pills (#0D542B ke #157a41 dengan pantulan kilau halus), dan fixed pinned viewport (h-screen overflow-hidden) pada Admin Prodi, Admin Fakultas, Super Admin, dan Admin Biro 3.
- **Layout Responsif "Stay on Resize"**: Memastikan layout desktop fixed sidebar (lg:pl-72, min-w-0, overflow-x-auto) konsisten di semua stakeholder sehingga layar tetap stabil dan rapi saat dikecilkan/dizoom.
- **Koreksi Typo & Kolom Relasi ERD**: Menambahkan `created_by` dan `created_prodi_id` pada tabel `perusahaan`, memperbarui diagram relasi Mermaid serta tabel matriks relasi, dan melengkapi kamus data `users` dengan `admin_fakultas` dan `fakultas_id`.

## [2026-09-21]
- **Perbaikan Akses Modal Tambah & Edit Pertanyaan**: Memperbaiki variabel `initialKeterangan` yang belum terdefinisi pada fungsi `openQuestionModal()` di `SuperAdmin/Pertanyaan/Index.vue` serta mengisi form value secara aman via hook `didOpen` SweetAlert2 agar tombol "Tambah Pertanyaan" dan tombol pensil "Edit" dapat diakses normal.
- **Validasi Backend Simpan Pertanyaan**: Menambahkan tipe `radio`, `checkbox`, dan field `keterangan` (batasan nilai/constraint) ke dalam daftar aturan validasi `store` dan `update` di `SimpanPertanyaanController.php`.
- **Eliminasi Duplikasi Tombol Tambah Opsi**: Menghapus duplikasi tombol tambah opsi pada baris butir pertanyaan, kini hanya menggunakan satu tombol yaitu ikon `+` warna hijau di kolom Aksi.
- **Restriksi Opsi Berdasarkan Tipe Pertanyaan**: Membatasi tombol tambah opsi dan kartu opsi hanya untuk 9 tipe yang mendukung opsi (`radio`, `radio_input`, `radio_text`, `multiple_choice`, `rating_5`, `multiple_number`, `matrix`, `matrix_dual`, `multiple_textbox`), serta menghapus tipe yang tidak terpakai/duplikat.
- **Cascading Jump Logic (Alur Lompatan 2-Langkah)**: Mengubah dropdown alur lompatan (*jump logic*) yang sebelumnya menampilkan daftar flat 70+ soal menjadi dropdown bertingkat: Langkah 1 memilih Bagian/Section target, kemudian Langkah 2 memilih butir pertanyaan target di dalam bagian tersebut untuk navigasi yang ringkas dan cepat pada Super Admin dan Biro 3.
- **Penyederhanaan Seeder Gaji / Take Home Pay (F505)**: Memastikan `F505` hanya memiliki 1 opsi jawaban tunggal (`F5051` - *Rata-rata Pendapatan per Bulan (Take Home Pay)*) dan membersihkan data opsi `F5052` serta `F5053` dari database dan seeder.
- **Batasan Nilai / Constraint**: Menambahkan dukungan kolom batasan nilai / constraint isian pada form pertanyaan (contoh: batasan minimal Rp 1.000 kelipatan ribuan untuk gaji) yang ditampilkan langsung di bawah kalimat pertanyaan.
- **Aksen Kuning Khusus Header Kuesioner**: Memberikan penanda warna kuning lembut (`bg-[#FEF9C3]`), aksen border kuning (`border-l-[#EAB308]`), dan badge kuning *Header Kuesioner* untuk butir pertanyaan bertipe `header`.
- **Redesign Tabel Kelola Section & Pertanyaan (Super Admin)**: Mengubah antarmuka Kelola Bagian Kuesioner (`/superadmin/sections`) dan Kelola Butir Pertanyaan (`/superadmin/pertanyaan`) menjadi tampilan data tabel modern, minimalis, dan elegan dengan tombol aksi berbasis ikon SVG bersih (Detail Mata, Edit Pensil, Kelola Opsi, Hapus Sampah, dan Reorder Naik/Turun).
- **Default Tampilan All & Filter Dropdown Section**: Menampilkan semua pertanyaan (**ALL**) secara awal dengan menu tarik-turun (*dropdown filter*) untuk memilih bagian kuesioner tertentu, filter lokasi tampil, serta pencarian teks terpadu.
- **Konfigurasi Lokasi Tampil Butir Pertanyaan (`tampil_di`)**: Menambahkan kolom `tampil_di` (`kuesioner`, `profile`, `both`) pada tabel `ref_subpertanyaan2021` dan form Super Admin sehingga pengaturan kemunculan pertanyaan pada kuesioner universitas atau halaman profil alumni dapat diatur secara dinamis dari database tanpa hardcode.
- **Pembersihan Sidebar Super Admin**: Menghapus tautan jalan pintas publik (*Lihat Beranda Publik*) dari sidebar SuperAdmin agar navigasi lebih terfokus.


- **Standarisasi Ekspor Excel (.xls) Terformat dengan Proteksi Teks NIK/NPWP/NIM**:
  - Mengembalikan format unduhan ke file spreadsheet **Excel (.xls)** asli dengan styling penuh (Header UKDW Green `#0D542B`, blok Identitas Alumni, batas tabel, dan badge warna status).
  - Menerapkan format teks eksplisit Microsoft Excel (`mso-number-format:'\@'`) pada kolom respon, NIK, NPWP, NIM, nomor telepon, dan kode pertanyaan agar terhindar dari notasi ilmiah eksponensial (seperti `3,40401E+15`).
  - Memperbaiki pembacaan NIK dan NPWP melalui fallback `COALESCE(b.nik, da.nik)` dan `COALESCE(b.npwp, da.npwp)` pada kuesioner autofill dan profil tracer.
- **Database Views Terpisah & Modular (Solusi N+1 & Query Cepat)**:
  - Membuat 5 Database Views terpisah per fungsi: `v_alumni_profile_summary`, `v_alumni_tracer_univ_status`, `v_alumni_tracer_prodi_status`, `v_alumni_audit_rekap`, `v_alumni_tracer_export`.
  - Direktori alumni di seluruh stakeholder kini mengeksekusi single query ke view tanpa loop PHP lambat.
- **Admin Biro 3**:
  - Mengganti navbar dengan Sidebar Resmi UKDW (`AdminBiroTiga/Components/Sidebar.vue`).
  - Direktori DataTables terpadu dan Tampilan Detail 3-Tab dengan sinkronisasi LinkedIn dan ekspor Excel/CSV.
- **Admin Prodi**:
  - Mengganti navbar dengan Sidebar Resmi UKDW (`AdminProdi/Components/Sidebar.vue`).
  - Direktori dan Detail Alumni 3-Tab dengan pembatasan khusus prodi login.
- **Admin Fakultas (Stakeholder Baru)**:
  - Menambahkan `fakultas_id` pada tabel `users` dan seeder untuk 7 fakultas UKDW.
  - Modul lengkap Fakultas: Sidebar, Dashboard, Direktori Alumni (dengan dropdown filter prodi dalam fakultas), dan Detail Alumni 3-Tab.
- **Pemisahan File per Stakeholder**:
  - Seluruh controller dan file Vue dipisah per folder aktor untuk kemudahan perawatan (*maintainability*).

**Modul Super Admin (/superadmin/alumni/{id}):**
- **Tata Letak Statis & Stabil**: Menghilangkan *negative margin* (`-mt-10`) dan tumpang tindih kontainer untuk menghilangkan pergeseran layar (*pull/drag elastic bouncing*).
- **Konsistensi Form Profil**: Menghapus pembungkus kartu ganda sehingga form data pribadi, akademik, orang tua, dan karier memiliki lebar dan padding 100% konsisten dan simetris.
- **Filter Dropdown Seksi Langsung Nama**: Mengganti deretan tombol seksi horizontal dengan dropdown `<select>` yang menampilkan langsung nama bagian/seksi pada Kuesioner Universitas maupun Program Studi.
- **Standar Data Table Bersih**: Menghilangkan warna mencolok berlebihan pada tabel, menggantinya dengan gaya tabel data standar (header abu-abu terang, baris data putih bergaris tipis, badge status merah/hijau minimalis, dan header pemisah kuning lembut UKDW).

## [2026-09-20] - Redesain Audit Detail Alumni Super Admin (DataTables Excel-Style View, Urutan Tab Baru, Header Kuning & Highlight Merah)
**Modul Super Admin (/superadmin/alumni/{id}):**
- Urutan Tab Baru: 1. Detail Profile (default), 2. Kuesioner Universitas, 3. Kuesioner Program Studi: [Nama Prodi].
- DataTables View ala Excel: Tabel data dengan Header Section/Question Kuning UKDW `#FDC700`, baris belum dijawab berlatar merah lembut (`bg-rose-50 border-l-4 border-l-rose-500`), dan baris terjawab hijau/bersih.
- Fitur pencarian cepat (*Quick Search*) per tab kuesioner.
- Format Download Excel: Menggunakan CSV UTF-8 BOM murni tanpa dialog peringatan mismatch pada Microsoft Excel.
**Profil Alumni UI/UX & Validasi:**
- Standardisasi Take Home Pay / Gaji: Pola computed getter/setter (Single Source of Truth), peniadaan pengali `* 1000` tersembunyi di semua layer, dan validasi minimal ribuan (>= Rp 1.000) dengan border merah serta notifikasi modal.
- Tab Navigasi Minimalis: Menghilangkan teks "X belum" dan karakter ASCII mentah, digantikan badge lingkaran hijau (lengkap) dan dot status rose/amber halus (belum lengkap).
- Top Notification Banner: Memindahkan peringatan "Ada Perubahan Belum Disimpan" ke banner atas mengambang dengan tombol aksi cepat "Simpan Sekarang".
- Color-coded field states (merah untuk belum terisi, hijau untuk valid) dan pembersihan seluruh emoticon/emoji.
- Filter lokasi perusahaan bertingkat (Negara / Provinsi & Kabupaten) sebelum memilih perusahaan, dengan fallback *"Data perusahaan tidak ditemukan"* dan penambahan perusahaan baru.
- Redesain profesional & eksekutif: Menghapus banner pill merah/pink di atas formulir profil, digantikan dengan indikator langsung pada kolom (*field-level indicators*) berupa border rose halus untuk kolom wajib yang kosong dan border emerald checklist untuk kolom valid.
- Validasi NPWP sesuai UU HPP & PMK No. 112/PMK.03/2022: Dibatasi 15 atau 16 digit angka (integrasi NIK sebagai NPWP Orang Pribadi), tombol cepat "⚡ Gunakan NIK (16 Digit)", dan evaluasi kelengkapan tab secara ketat.
- Pemilihan peran eksklusif (*single choice*): Kategori `Pekerja / Karyawan`, `Wirausaha / Founder`, dan `Melanjutkan Pendidikan` saling mereset atribut peran lain ke `null` saat berpindah.
- Validasi kontak: Email wajib mengandung `@` dan domain valid, nomor telepon 10–15 digit, dan standarisasi URL LinkedIn, Instagram, Facebook.

**Kuesioner Prodi:**
- Menyamakan tombol Lanjut/Kembali (FAB melayang desktop & sticky bottom mobile) dan lebar kontainer (`max-w-6xl xl:max-w-7xl`) dengan Kuesioner Universitas.
- Mengoreksi 4 butir pertanyaan Prodi SI menjadi header deskripsi section.

## [2026-09-20] - Navigasi Sidebar Terpadu Super Admin
**UI/UX & Navigasi:**
- Mengganti navigasi atas (*top navbar*) pada modul Super Admin dengan **Sidebar Terpadu** (`Sidebar.vue`) tetap di sisi kiri layar (`w-72`).
- Menyediakan navigasi menu terstruktur (Dashboard, Kelola Kuesioner, Kelola Section, Data Alumni, dan Pintasan Publik).
- Desain responsif dengan *slide-over drawer* dan *backdrop* khusus perangkat mobile/tablet.
- Menghubungkan seluruh halaman Super Admin (`Dashboard.vue`, `Pertanyaan/Index.vue`, `Section/Index.vue`, `Alumni/Index.vue`, `Alumni/Show.vue`).
- Menghapus komponen `Navbar.vue` lama di modul Super Admin.

## [2026-09-20] - Seeding Perusahaan 15 Kolom, Sinkronisasi Studi Lanjut F18, UI F17 Skala Langsung, & Jump Logic Database-Driven
**Database & Seeding:**
- Mengisi 15 kolom lengkap pada `database/seeders/PerusahaanSeeder.php` termasuk provinsi_id, kabupaten_id, kota, provinsi, negara, jenis_lokasi, jenis_perusahaan, skala, kode_pos, dan status_verifikasi.
- Memperbarui database view `v_alumni_kuesioner_autofill` untuk mendukung pemetaan `BIO_PENDIDIKAN_TINGKAT`.

**Studi Lanjut (F18):**
- Sinkronisasi otomatis data profil studi lanjut (`pendidikan_tingkat`, `perguruan_tinggi`, `pendidikan_prodi`) ke butir kuesioner `F18b` dan `F18c` via `KuesionerSyncService` & `KuesionerController`.
- Penataan rapi kartu `F18` di `Kuesioner.vue` (`F18a` single card, `F18b` & `F18c` 2-column grid, `F18d` input date).

**Frontend & Kuesioner UI:**
- Mengubah format skala kompetensi `TabelF17.vue` menjadi `Sangat Rendah 1 2 3 4 5 Sangat Tinggi` dengan palet 2-warna UKDW (Hijau `#005B3C` & Kuning `#FDC700`).
- Konsistensi tombol stepper (+/-) pada `KartuPertanyaan.vue` dan pembersihan teks rating.
- Logika percabangan kuesioner (`jump_to`) kini 100% didorong oleh relasi basis data tanpa hardcoded ID di frontend.

**Dokumentasi:**
- Memperbarui `ERD.md`, `DAFTAR_PERTANYAAN_MAPPING.md`, `UPDATE_LOG.md`, dan `docs/changelog.md`.

## [2026-09-04] - Restrukturisasi Arsitektur & Pembersihan Logika
**Arsitektur Kode & Controller:**
- Menghapus semua fungsi `Closure` di `routes/web.php` untuk memisahkan *Routing* secara murni.
- Membuat struktur folder Controller bersarang (Nested) per Aktor/User, lalu dipecah per-Fungsi.
- Menerapkan penamaan `UpperCamelCase` dan **Bahasa Indonesia** untuk semua folder, class, dan fungsi di *Controller*.
- Memisahkan Controller Monolitik (`AuthController`, `AlumniProfileController`, `QuestionnaireController`, `Biro3\AlumniController`) menjadi Controller berprinsip *Single Responsibility*.

**Daftar Controller Baru (Bahasa Indonesia):**
- `App\Http\Controllers\Tamu\BerandaController`
- `App\Http\Controllers\Otentikasi\LoginController`
- `App\Http\Controllers\Otentikasi\UbahKataSandiController`
- `App\Http\Controllers\Alumni\Dashboard\DashboardController`
- `App\Http\Controllers\Alumni\Profil\ProfilController`
- `App\Http\Controllers\Alumni\Kuesioner\KuesionerController`
- `App\Http\Controllers\Alumni\Kuesioner\SimpanJawabanController`
- `App\Http\Controllers\AdminBiroTiga\Dashboard\DashboardController`
- `App\Http\Controllers\AdminBiroTiga\KelolaAlumni\DaftarAlumniController`
- `App\Http\Controllers\AdminBiroTiga\KelolaAlumni\DetailAlumniController`
- `App\Http\Controllers\AdminBiroTiga\KelolaAlumni\SinkronisasiLinkedinController`
- `App\Http\Controllers\AdminProdi\Dashboard\DashboardController`
- `App\Http\Controllers\SuperAdmin\Dashboard\DashboardController`

**Frontend (Vue):**
- Membersihkan `resources/js/Pages/alumni/kuesioner/index.vue` dari logika JavaScript yang rumit (GSAP, Jump Logic) untuk menaati aturan "Zero Logic Frontend". Data disiapkan 100% matang dari Backend.
- Membersihkan `resources/js/Pages/alumni/profile/index.vue` dari logika animasi GSAP.
- Menambahkan komentar dokumentasi di *Frontend* yang mengindikasikan larangan penulisan `const` dan fungsi JS secara ekstensif.

**Dokumentasi:**
- Semua *Controller* baru dilengkapi dengan DocBlocks (Komentar terstruktur) dalam bahasa Indonesia untuk memudahkan pemeliharaan kode.
- File `docs/changelog.md` ini dibuat untuk melacak pembaruan di masa mendatang.
