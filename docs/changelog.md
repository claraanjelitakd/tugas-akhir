# Update Log Tracer Study

> Arsip perubahan historis. Keterangan fitur/angka pengujian berlaku pada waktu pencatatan, bukan jaminan kondisi terkini. Rujukan revisi 21 Oktober 2026: [DFD](dfd.md) dan [user flow](user-flow.md).

## 02 November 2026

- **Penyelarasan ERD dan Kamus Data Skripsi (Relasi Master Perusahaan dan Atasan)**:
  - Mengonfirmasi penguatan relasi langsung antara entitas `perusahaan` dan `atasan` sesuai kaidah perancangan skripsi (1 perusahaan menaungi banyak atasan, `1 : N`).
  - Memperbarui diagram ERD Modul 6 (Evaluasi Pengguna Lulusan) pada [ERD.md](file:///c:/study/tracer-ta/ERD.md) dan [storage/app/tugas-akhir-publication/ERD.md](file:///c:/study/tracer-ta/storage/app/tugas-akhir-publication/ERD.md) dengan menyertakan relasi eksplisit `perusahaan ||--o{ atasan : "menaungi (perusahaan_id)"` serta kotak entitas `atasan` dan `perusahaan`.
  - Memperbarui Kamus Data Tabel `atasan` (Tabel 13) dengan kolom `perusahaan_id` (`bigint unsigned`, `FK`, nullable, referensi ke `perusahaan.id` `ON DELETE SET NULL`) dan `user_id` (`bigint unsigned`, `FK`, nullable, referensi ke `users.id` `ON DELETE SET NULL`).
  - Menandai pembuatan migration database baru (`perusahaan_id` pada tabel `atasan`) sebagai agenda revisi kode berikutnya agar skema runtime aktif saat ini tetap stabil selama penyusunan naskah skripsi.
- **Penambahan Menu & Halaman Grafik Program Studi (Super Admin)**:
  - Menambahkan menu **Grafik Prodi** pada sidebar Super Admin ([AdminSidebar.vue](file:///c:/study/tracer-ta/resources/js/components/AdminSidebar.vue)) di bawah kelompok *Ringkasan & Analisis* berdampingan dengan *Grafik Universitas*.
  - Menyediakan rute web `/superadmin/analitik-prodi` dan endpoint data asinkron `/superadmin/analitik-prodi/data` ([web.php](file:///c:/study/tracer-ta/routes/web.php)).
  - Mengimplementasikan [ProdiAnalyticsController.php](file:///c:/study/tracer-ta/app/Http/Controllers/SuperAdmin/Analytics/ProdiAnalyticsController.php) dan [ProdiAnalyticsService.php](file:///c:/study/tracer-ta/app/Services/Analytics/ProdiAnalyticsService.php) untuk mengagregasi respon alumni pada kuesioner program studi (`prodi_response`, `prodi_question`, `prodi_question_section`, `prodi_question_option`).
  - Mengonfigurasi default pemilih program studi ke **Sistem Informasi** (dengan opsi dropdown untuk beralih ke program studi lain beserta empty state informatif jika prodi lain belum memiliki instrumen soal).
  - Menyajikan visualisasi ECharts interaktif ([GrafikProdi.vue](file:///c:/study/tracer-ta/resources/js/Pages/SuperAdmin/Analytics/GrafikProdi.vue)) untuk 9 seksi instrumen Sistem Informasi (skala Likert 1-5, distribusi peminatan/kontribusi donat, dan umpan balik teks), kartu ringkasan KPI alami (ala iOS Weather), narasi kilas analisis, serta tabel detail respon alumni terintegrasi.
  - Menambahkan pengujian fitur otomatis pada [SuperAdminProdiAnalyticsTest.php](file:///c:/study/tracer-ta/tests/Feature/SuperAdminProdiAnalyticsTest.php) (3 tests passed, 36 assertions).
- **Optimalisasi Filter Wilayah & Tabel Perusahaan (Super Admin)**:
  - Memperjelas relasi program studi pada kolom *Pengaju Usulan* di [SuperAdmin/Perusahaan/Index.vue](file:///c:/study/tracer-ta/resources/js/Pages/SuperAdmin/Perusahaan/Index.vue): saat filter program studi aktif, sistem menampilkan badge jumlah alumni dari prodi tersebut yang bekerja di perusahaan, sehingga pengguna langsung memahami mengapa instansi bersangkutan masuk ke dalam filter meskipun akun pembuat awal (*creator*) berasal dari prodi lain.
  - Memperbaiki penggabungan query filter di [VerifikasiPerusahaanSuperAdminController.php](file:///c:/study/tracer-ta/app/Http/Controllers/SuperAdmin/Perusahaan/VerifikasiPerusahaanSuperAdminController.php) dan [PerusahaanVerificationService.php](file:///c:/study/tracer-ta/app/Services/Perusahaan/PerusahaanVerificationService.php) agar filter wilayah (*negara*, *provinsi*, *kabupaten*) tetap berjalan serentak saat filter program studi sedang aktif.
  - Memperbaiki bug status `(MEMUAT...)` dan kekosongan daftar kabupaten saat memilih provinsi pada filter wilayah dengan:
    1. Mengamankan endpoint `getKabupatenByProvinsi` di [MasterWilayahController.php](file:///c:/study/tracer-ta/app/Http/Controllers/SuperAdmin/MasterWilayah/MasterWilayahController.php) agar menyimpan dan mengembalikan plain array data (mencegah *serialization error* objek incomplete class di cache).
    2. Menambahkan `try ... finally` pada `handlePropinsiChangeFilter` dan validasi `Array.isArray()` di [SuperAdmin/Perusahaan/Index.vue](file:///c:/study/tracer-ta/resources/js/Pages/SuperAdmin/Perusahaan/Index.vue) agar indikator loading selalu berakhir tepat waktu.
    3. Menambahkan watcher otomatis pada `props.kabupatens` untuk sinkronisasi instan saat Inertia merender data halaman.
  - Menyediakan bilah filter bertingkat untuk **Negara**, **Provinsi**, dan **Kabupaten / Kota** di atas tabel perusahaan ([SuperAdmin/Perusahaan/Index.vue](file:///c:/study/tracer-ta/resources/js/Pages/SuperAdmin/Perusahaan/Index.vue) dan [VerifikasiPerusahaanSuperAdminController.php](file:///c:/study/tracer-ta/app/Http/Controllers/SuperAdmin/Perusahaan/VerifikasiPerusahaanSuperAdminController.php)).
  - Mengembalikan struktur tabel perusahaan menjadi format ringkas 7 kolom (*No*, *Nama Institusi / Perusahaan*, *Wilayah & Alamat Kantor*, *Pengaju Usulan*, *Status & Info Verifikasi*, *Rekomendasi Master*, *Aksi*) sehingga pas dalam satu layar tanpa perlu menggeser tabel secara horizontal (*no horizontal scroll*).
  - Rincian wilayah lengkap (Negara, Provinsi, Kabupaten/Kota, Alamat, Kode Pos, Homepage, dll.) tetap dapat ditinjau melalui modal *Peninjauan Usulan Perusahaan Alumni*.
  - Menyelaraskan header kolom **Angkatan** pada modal *Daftar Alumni Bekerja* dan modal *Peninjauan Usulan Perusahaan Alumni* menjadi **Tahun Lulus** (`tahun_lulus`), sehingga sesuai dengan terminologi kohort Tracer Study UKDW.
- **Pemisahan Entitas Wilayah Perusahaan (Negara, Provinsi, Kabupaten/Kota)**:
  - Memisahkan kolom wilayah pada tabel data master & usulan perusahaan di [SuperAdmin/Perusahaan/Index.vue](file:///c:/study/tracer-ta/resources/js/Pages/SuperAdmin/Perusahaan/Index.vue) menjadi **Alamat Kantor**, **Kabupaten / Kota**, **Provinsi**, dan **Negara**.
  - Menerapkan pemisahan wilayah serupa pada tabel drill-down *Rincian Data Terpilih* di [AnalyticsTabbedContainer.vue](file:///c:/study/tracer-ta/resources/js/components/analytics/AnalyticsTabbedContainer.vue) serta query rekaman analitik di [TracerAnalyticsService.php](file:///c:/study/tracer-ta/app/Services/Analytics/TracerAnalyticsService.php).
  - Menerapkan pemisahan atribut wilayah (Negara, Provinsi, Kabupaten/Kota, Kode Pos) pada blok *Profil & Karakteristik Instansi* di [TabEvaluasiAtasan.vue](file:///c:/study/tracer-ta/resources/js/components/AlumniAudit/TabEvaluasiAtasan.vue).
- **Penyempurnaan Tab Evaluasi Atasan pada Detail Alumni Super Admin & Admin Terkait**:
  - Menghapus tombol *Salin Link Kuesioner* dan *Kirim Email ke Atasan* dari antarmuka tab Evaluasi Atasan (`TabEvaluasiAtasan.vue`).
  - Memperkaya data audit `EvaluasiAtasan.php` (`toAdminAuditArray()`) sehingga memuat seluruh atribut yang ada di formulir atasan: profil lengkap perusahaan (bentuk lembaga, skala, jumlah pegawai, no telp/fax, website/homepage, wilayah), identitas atasan penilai, karakteristik keberadaan alumni UKDW (`jumlah_alumni_ukdw`), serta standar gaji pertama (`standar_gaji_pertama`).
  - Menampilkan ringkasan penempatan karier (posisi jabatan, bidang divisi, kategori aktivitas) dan riwayat studi lanjut alumni secara terpadu.
  - Memisahkan butir *Tingkat Kesiapan Kerja* ke dalam kartu indikator hasil penilaian khusus.
  - Memformat nilai pada tabel 12 aspek kompetensi secara dinamis dalam rentang 1 - 5 (dengan keterangan `1 Paling Rendah`, e.g. `5 - Sangat Tinggi`), menghapus hardcode skala 1-4, menghilangkan kolom *Catatan Penilai* dari setiap baris tabel, serta menyajikan teks skor secara polos (tanpa *outline*, *shadow*, dan *border*).
  - Menyatukan seluruh saran terbuka dan umpan balik kualitatif penilai ke dalam satu kotak *Catatan & Masukan Penilai* di bagian paling bawah antarmuka.
- **Penyempurnaan Tampilan Grafik Universitas & Rincian Data Drill-Down**:
  - Mengubah pengaturan default visibilitas panel *Sumber dan Metodologi Perhitungan* (rumus, denominator, view SQL) pada `GrafikUniv.vue` menjadi tertutup/tersembunyi (*collapsed*) secara bawaan, dengan opsi tombol *Tampilkan Rincian* bagi pengguna yang ingin membukanya.
  - Menambahkan kolom **Nama Perusahaan / Usaha** dan **Wilayah / Region** (nama kabupaten, provinsi, atau negara/skala) pada tabel inline *Rincian Data Terpilih* (`AnalyticsTabbedContainer.vue`), serta memperkaya query data rekaman `workplace_type` di `TracerAnalyticsService.php`.
- **Halaman Penutup & Email Tanda Terima Evaluasi Pengguna Lulusan**:
  - Menata ulang halaman konfirmasi terima formulir evaluasi atasan (`EvaluasiAtasanNotFound.vue`) dengan tema flat, responsif full-page, warna institusi UKDW (hijau tua `#0D542B` & kuning `#FDC700`), bebas dari elemen bayangan/border/centang berlebih.
  - Mengirimkan email konfirmasi tanda terima resmi secara otomatis ke email atasan setelah formulir survei evaluasi berhasil dikirim (`KonfirmasiEvaluasiAtasanTerkirimMail.php`).

## 01 November 2026

- **Sinkronisasi Dua Arah Data Perusahaan (Alumni & Evaluasi Atasan)**:
  - Menambahkan field `bentuk_perusahaan`, `no_telp_fax`, dan `homepage` pada form karier alumni (`FormKarier.vue`) serta modal tambah perusahaan baru agar seluruh atribut perusahaan di form evaluasi atasan juga dapat diisi dan diperbarui dari sisi alumni.
  - Membuka sinkronisasi dua arah penuh pada `SimpanProfilController.php` dan `EvaluasiAtasanController.php` terhadap tabel `perusahaan`.
  - Menyamakan casing path import `@/components/AlumniProfil/...` pada `Alumni/Profil/Index.vue`.
- **Refinement Halaman Evaluasi Pengguna Lulusan (`/evaluasi-atasan/{token}`)**:
  - Menambahkan dropdown profil bundar alumni di header kanan atas, banner sapaan formal langsung kepada atasan (tanpa *Bapak/Ibu*), serta ringkasan informasi jabatan, penempatan, dan studi lanjut alumni.
  - Menyusun formulir dalam 3 tahapan stepper (*Profil Perusahaan*, *Karakteristik*, *Penilaian Kinerja*), membalik urutan skala Kesiapan Kerja (*Tidak siap* s/d *Sangat siap*) dan Likert 12 Aspek Kinerja (*Sangat Kurang* s/d *Sangat Tinggi*), serta mendukung pembatalan jawaban (*uncheck*) dengan klik ulang / klik 2x.
- **Penyatuan Desain & Instruksi Default Kuesioner Universitas dan Prodi**:
  - Menyamakan tampilan kartu soal dan opsi pilihan pada `KartuPertanyaanProdi.vue` dengan `KartuPertanyaan.vue` (indikator lingkaran radio button untuk pilihan tunggal, kotak centang untuk pilihan ganda, input teks kustom untuk opsi `Lainnya` / `Other`, serta stepper angka `-`/`+`).
  - Menambahkan badge instruksi default otomatis di setiap tipe pertanyaan pada Kuesioner Universitas dan Kuesioner Prodi.
  - Memperbaiki logika ceklist stepper di `KuesionerProdi.vue` agar hanya menandai bagian selesai ketika seluruh pertanyaan wajib di bagian tersebut sudah dijawab.
- **Penyempurnaan UX Profil Alumni & Analitik**:
  - Mengubah alert perubahan belum disimpan menjadi *floating bottom bar* hijau UKDW, menyederhanakan navbar alumni menjadi avatar bundar ber-dropdown, memperbaiki pengiriman email atasan, menampilkan rincian *Jenis Instansi Lainnya* pada tabel drill-down Indikator 09, dan menghilangkan *double-loading* pada `GrafikUniv.vue`.

## 28 Oktober 2026

- **Penyelarasan Visualisasi Grafik Universitas & Harmoni Indikator Laporan Resmi**:
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

## 25 Oktober 2026

- **Penyederhanaan Indikator 01 & Optimasi Tab Data Responden (Tema 2-Tone Profesional)**:
  - **Fokus 2 Visualisasi Utama Indikator 01**:
    - Mempertahankan hanya 2 grafik inti: *100% Stacked Horizontal Bar Chart* (Sudah Mengisi hijau `#0D542B` vs Belum Mengisi amber `#F59E0B`) dan *Ranking Response Rate & Kriteria Capaian Target 60%*.
    - Menghilangkan grafik *Combo Bar + Line*, kolom kartu alumni sempit 54 halaman di sebelah kanan grafik, tabel ringkasan inline di bawah grafik, dan footer metodologi redundant.
    - Menghapus switcher `[ Jumlah | Persentase ]` dari toolbar analitik.
  - **Tab Data Responden Berformat Excel Terpadu**:
    - Memindahkan rincian data alumni ke Tab Data (`activePanel === 'data'`) dengan struktur kolom resmi layaknya spreadsheet Excel: *No*, *NIM*, *Nama Mahasiswa / Alumni*, *Program Studi*, *Tahun Lulus*, *Status Kuesioner*, *Waktu Respons*, dan *Aksi*.
    - Menyediakan tab filter status kuesioner interaktif: *Semua*, *Sudah Mengisi*, dan *Belum Mengisi* dengan counter badge data aktual.
    - Menambahkan tombol ekspor Excel langsung di tab data: *Download Excel Sesuai Pilihan* dan *Download Excel 3 Sheet (Lengkap)*.
    - Aksi kontak dan detail alumni dibuat berbasis icon-only SVG formal (WhatsApp, Email, dan Detail Profil) yang konsisten dan identik dengan halaman `/superadmin/alumni`.
  - **Optimasi Performa & Kecepatan Muat Superadmin Analitik**:
    - Memangkas debounce timer dari 240ms menjadi 40ms serta mengeksekusi request instan saat user memilih dropdown indikator.
    - Mengubah impor landing page `01_ResponseRate.vue` menjadi static import di index hub guna menghilangkan jeda network waterfall async chunk awal.
    - Menyempurnakan pencarian teks pada tab data sehingga mencakup nama, NIM, prodi, nomor telepon, dan email tanpa lag.


- **Modularisasi Komponen Kategori Analitik (18 Berkas Mandiri)**: Memecah visualisasi analitik menjadi 18 komponen kategori mandiri di `resources/js/components/analytics/categories/` (`01_ResponseRate.vue` sampai `18_WorkLocation.vue`). Masing-masing berkas mengelola data, opsi chart, dan tabel datanya secara terisolasi tanpa bergantung pada wrapper generik. Menghapus berkas legacy `SuperAdmin/Analytics/Index.vue`.
- **Index Hub Dinamis (`AnalyticsChartsPanel.vue`)**: Menggunakan router komponen dinamis berbasis `defineAsyncComponent` untuk me-mount berkas kategori yang aktif dengan *code-splitting lazy loading*, menghemat ukuran unduhan bundle awal.
- **Penyelarasan Visual Laporan Resmi Tracer Study**:
  - Mengadopsi grafik garis perbandingan resmi: Gambar 4.3 (Penekanan metode belajar), Gambar 4.6 (Waktu tunggu per prodi dengan ukuran sampel `n`), Gambar 4.7 (Tahapan lamaran dilamar, merespon, wawancara per prodi), Gambar 4.12 (Pendapatan per bulan per prodi), dan Gambar 4.17 (Dual-line gap kompetensi saat lulus vs dibutuhkan).
  - Mengeliminasi tekstur decal garis-garis (`decal: { show: false }`) untuk tampilan flat yang bersih dan profesional.
  - Menerapkan warna solid brand emerald (`#0D542B`) untuk kategori sejenis guna menghindari kebingungan visual akibat warna pelangi berlebih.
- **Tata Letak Terpadu [Grafik | Data] (`AnalyticsChartCard.vue`)**:
  - Menggantikan 7 tombol toolbar berlebih dengan layout split berdampingan (Grafik di kiri dan Tabel Data terstruktur di kanan).
  - Menyediakan tombol pemilih mode tampilan: `[ Grafik | Data ]` (default), `[ Grafik ]` (penuh), dan `[ Data ]` (tabel penuh), serta tombol unduh PNG.
  - Mendukung interaktivitas dua arah: klik baris tabel langsung memicu filter pada grafik, dan klik grafik langsung menyorot baris tabel terkait.
- **Penyatuan Template Dashboard Admin (`RoleDashboard.vue`)**:
  - Menyatukan antarmuka dashboard Admin Prodi, Admin Fakultas, dan Biro 3 ke dalam satu komponen template bersama yang dikontrol oleh cakupan role (`RoleDashboardDataService`).
- **Dokumentasi Kode Komprehensif**: Menambahkan komentar penjelasan pada setiap deklarasi `const`, `computed`, `ref`, dan fungsi di seluruh file komponen frontend analitik tanpa memakai emotikon/emoji.

- **Redesign Business Intelligence Grafik Universitas**: mengganti visual HTML/SVG manual dengan Apache ECharts 6.1 melalui wrapper reusable yang mengelola resize, cleanup, reduced motion, tooltip, click selection, zoom, transisi, dan export.
- **Interaksi lintas grafik**: menambahkan shared selection, cross-filter, drawer drill-down, reset selection, heatmap jumlah/persentase, serta toolbar reset, PNG, CSV, legend, fullscreen, data, dan metodologi.
- **Peta MapLibre**: mengganti peta analitik Leaflet pada Grafik Universitas dengan MapLibre GL JS 6.15, choropleth provinsi, zoom/pan, popup, layer toggle, dan cluster lokasi berdasarkan wilayah perusahaan.
- **Filter tanpa reload Inertia**: menambahkan endpoint JSON `/superadmin/analitik/data` dan `useHttp` dengan debounce, loading state, error state, serta sinkronisasi URL browser.
- **Cakupan 16 indikator**: menyusun indikator wajib sesuai urutan laporan, memisahkan F15 per prodi dan universitas, serta mempertahankan analitik wilayah dan kelengkapan profil sebagai indikator tambahan.
- **Audit statistik dan performa**: menambahkan metadata tipe data, missing data, dasar persentase, waktu perhitungan, pencarian/sorting/pagination tabel, dan lazy chunk terpisah untuk ECharts serta MapLibre.
- **Optimasi view analitik**: memutus dependensi `v_analytics_population` dari `v_alumni_audit_rekap`, memindahkan agregasi jawaban wajib hanya ke `v_analytics_response_rate`, dan menghapus agregasi `MAX(id)` yang redundant karena tabel `tracer` sudah memiliki unique key. Waktu pembentukan payload sampel turun dari sekitar 4–5 detik menjadi 0,18–0,31 detik pada Laragon lokal.
- **Dimensi studi lanjut**: memperluas `v_analytics_further_study` dari F12 saja menjadi F12, F18b, F18c, dan F18d; service mempertahankan label sumber biaya, perguruan tinggi, program studi, serta tanggal mulai agar nilai antardimensi tidak tercampur.

- **Grafik Universitas terpilah**: `/superadmin/analitik` kini dirender oleh `GrafikUniv.vue` dengan dropdown satu indikator per halaman, filter tahun lulusan, program studi, dan tahun UMP.
- **Dataset sesuai grafik aktif**: `TracerAnalyticsService::buildUniversityChart()` hanya mengambil kode pertanyaan dan detail yang diperlukan oleh indikator aktif.
- **Detail analitik**: panel Analisis menampilkan rumus, denominator, median, klasifikasi, dan nama alumni terkait; panel Data menampilkan tabel individu beserta prodi dan perusahaan.
- **Perbandingan gaji dengan UMP**: aturan tampilan grafik menjadi `gaji >= 1,2 × UMP` provinsi tempat kerja. Record menyimpan ambang, status memenuhi, perusahaan, dan nama alumni.
- **Cakupan indikator**: menambahkan waktu memperoleh pekerjaan, pendapatan, wilayah kerja berbasis perusahaan, status/IKU 1 awal, hubungan bidang studi, keselarasan tingkat pendidikan, response rate, dan kelengkapan profil. Kuesioner prodi tidak dihitung.

- **Analitik Tracer Study tahap pertama**: menambahkan `/superadmin/analitik` dengan filter tahun lulusan, program studi, dan tahun UMP.
- **Definisi statistik laporan**: menambahkan median waktu memperoleh pekerjaan dan gaji, rentang P25–P75 untuk waktu, denominator, jumlah responden, serta data yang belum dapat dibandingkan.
- **Sumber gaji dan lokasi kerja**: menggunakan `biodata.gaji` serta provinsi perusahaan sebagai sumber kanonik; aturan ambang 1,2× UMP dan detail individu dijelaskan pada entri grafik universitas di atas.
- **Dokumentasi analitik**: memperbarui arsitektur dan user flow untuk menjelaskan pola satu indikator aktif per halaman.
- **Interaksi Grafik Universitas**: memisahkan halaman menjadi tab Analisis, Grafik, dan Data; daftar individu per kelompok tidak lagi dirender sebagai satu teks panjang; filter peta dan batang membuka tabel detail.
- **Distribusi demo analitik**: menambahkan `AnalyticsDemoDistributionSeeder` untuk membuat data demo lintas provinsi, gaji di bawah ambang, dan alumni belum bekerja tanpa mengubah alumni non-demo.
- **View analitik terpilah**: menambahkan view database per indikator untuk populasi, response rate, profil, waktu tunggu, gaji-UMP, wilayah kerja, status pekerjaan, dan F14/F15. Service hanya membaca view indikator aktif agar payload dan pemrosesan request lebih kecil.
- **Indikator tracer universitas lanjutan**: menambahkan metode pembelajaran, waktu mulai dan cara mencari kerja, funnel lamaran, aktivitas pencarian, jenis tempat kerja, studi lanjut, alasan ketidaksesuaian, serta gap kompetensi. Jawaban lintas butir dibaca dari `v_analytics_tracer_answers` dan setiap indikator mengirim `visual_plan`, ringkasan, seri, dan tabel individu.
- **Perbaikan sumber grafik nol**: grafik waktu tunggu, F8, F14, dan F15 kembali memakai view khusus sehingga kolom normalisasi tidak hilang saat payload dibentuk.
- **View lanjutan per indikator**: menambahkan sembilan view khusus untuk metode belajar, waktu/cara mencari kerja, pipeline lamaran, aktivitas pencarian, jenis tempat kerja, studi lanjut, alasan ketidaksesuaian, dan gap kompetensi.
- **Kombinasi grafik kontekstual**: menambahkan komponen donut, box plot, batang divergen, radar, treemap, heatmap, histogram, funnel, scatter plot, diagram alir, dumbbell, dan peta interaktif; tab Grafik tidak lagi memakai pola batang yang sama untuk seluruh indikator.
- **Status data kosong**: payload kini membedakan tidak ada populasi, tidak ada jawaban valid, serta pertanyaan yang tidak berlaku atau belum dijawab setelah percabangan.
- **Distribusi jawaban demo**: data demo memiliki variasi F3, F4, F6/F7/F7A, F8, F10-F16, F17, F21-F27, F502/F506, lima provinsi perusahaan, dan gaji di bawah maupun di atas 1,2× UMP. Bulk upsert menjaga seeding tetap cepat dan hanya menyentuh akun demo.

## 23 Oktober 2026

- **Audit dan penyederhanaan skema profil**: menghapus tabel `data_orang_tua`, foreign key `biodata.orang_tua_id`, NIK, No KK, NPWP, serta kolom dosen pembimbing/penguji dari `yudisium`. Data orang tua tidak lagi dibuat oleh seeder dan tidak lagi diekspor ke Excel.
- **Kanon data profil**: menetapkan `alamat_saat_ini`, `skills`, dan `experience` sebagai field tunggal yang digunakan frontend, backend, LinkedIn Sync, kelengkapan, dan exporter. Alias lama `alamat`, `expert`, dan `minat` tidak lagi dikirim atau disimpan.
- **Konsistensi view dan test**: memperbarui view profil, autofill, mapping pertanyaan, audit kelengkapan, seeder, dan feature test agar tidak membaca kolom yang sudah dihapus. Baseline schema dibuat kompatibel dengan SQLite untuk test dan MySQL Laragon untuk runtime.

- **Penyederhanaan Dashboard Superadmin**: merangkum metrik utama menjadi response rate, kelengkapan gabungan, dan populasi lulusan; detail kelengkapan kini dipilih melalui tab interaktif; pintasan modul yang berulang dihapus dari area utama agar fokus dashboard lebih jelas.
- **Penyelarasan visual Dashboard Superadmin**: menggunakan hero hijau UKDW dan pola card responsif dari Direktori Alumni, menghapus label konteks "Otoritas Pusat", serta menata ringkasan menjadi grid card yang nyaman pada layar kecil maupun besar.
- **Penyempurnaan status dashboard**: seluruh indikator response rate memakai aksen hijau atau kuning resmi UKDW `#FDC700`; label ambang status tidak lagi ditampilkan karena warna sudah menjadi penanda visualnya. Antrean ACC perusahaan memakai panel hijau/netral agar tidak tercampur dengan indikator perhatian.
- **Filter tahun Dashboard Superadmin**: menambahkan filter tahun kelulusan dengan default tahun berjalan, diterapkan pada metrik universitas, ringkasan prodi, dan alumni terbaru. Tampilan dashboard disederhanakan menjadi aksen hijau tua dan kuning UKDW tanpa outline dekoratif, kuning muda, atau panah aksi.
- **Skenario data dashboard**: menambahkan `DashboardScenarioSeeder` yang idempotent untuk populasi demo 2024–2026 per prodi, dengan target populasi dan responden yang berbeda, jawaban kuesioner prodi hanya untuk prodi yang memiliki instrumen, serta tidak menimpa NIM yang sudah ada.
- **Status kuesioner prodi**: memperbarui view audit agar prodi tanpa instrumen menghasilkan nilai tidak berlaku (`NULL`) sehingga antarmuka dapat menampilkan `-`, bukan 100%.
- **Dashboard alumni**: persentase kuesioner prodi tanpa butir kini dikirim sebagai `null` dan ditampilkan sebagai `-`.
- **Kelengkapan kuesioner universitas**: memperbaiki view audit yang sebelumnya mengunci jumlah wajib pada 20 butir. Total kini dihitung dari butir wajib aktif, mengikuti percabangan F8 dan slot alternatif F502/F506, sehingga responden lengkap tidak lagi salah terbaca 19/20 dan metrik gabungan tidak jatuh ke 0%.
- **Interaksi dashboard Superadmin**: menghapus banner antrean perusahaan dari area ringkasan dan menjadikan kartu response rate, kelengkapan gabungan, serta populasi lulusan sebagai pintasan ke detail dashboard.

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
- **23 Oktober 2026 — Finalisasi kontrak schema profil**: Menjadikan kontak/domisi aktif hanya berada di `biodata`, melakukan backfill aman dari data legacy, membersihkan migrasi dan seeder, serta memperbarui ERD, arsitektur, DFD, user flow, dan dokumentasi view. Field NIK, NPWP, No. KK, tabel orang tua, pembimbing/penguji, dan alias profil legacy tidak lagi dibuat.
- **24 Oktober 2026 — Penyederhanaan migration**: Merapikan migration historis menjadi satu file untuk setiap 33 tabel aktif dan 7 view aktif. Migration `add`, `refresh`, backfill, dan placeholder tabel orang tua tidak lagi digunakan. Database `tracer_study` dimigrasikan ulang dan diisi ulang melalui seeder.
## 25 Oktober 2026

- Menyamakan dashboard Admin Prodi, Admin Fakultas, dan Biro 3 melalui komponen `RoleDashboard` serta service `RoleDashboardDataService`.
- Menambahkan filter tahun lulusan, kartu response rate, kelengkapan profil dan kuesioner universitas, ringkasan per prodi, serta pratinjau alumni dengan scope backend masing-masing role.
- Menyesuaikan pintasan dashboard agar hanya menampilkan route yang tersedia pada role terkait dan memperbarui fixture test tahun kelulusan.
