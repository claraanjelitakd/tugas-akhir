# Wishlist & Rencana Pengembangan Sistem Tracer Study

Dokumen ini mencatat daftar kebutuhan fitur baru, penyempurnaan modul, serta agenda optimasi teknis yang diajukan untuk iterasi pengembangan berikutnya.

---

## 1. Modul Master Wilayah (`/superadmin/master-wilayah`)

* **Status**: Selesai Diimplementasikan (Closed / Resolved).
* **Kebutuhan**: Penambahan Manajemen Master Negara.
* **Solusi yang Telah Diterapkan**:
  * Penambahan tab **Master Negara** pada antarmuka `/superadmin/master-wilayah` dengan badge total data negara dunia (`stats.total_negara`).
  * Penambahan kartu ringkasan statistik Master Negara pada header analitik wilayah.
  * Fitur pencarian instan berdasarkan nama negara, kode ISO-2, kode ISO-3, ibu kota, atau wilayah benua.
  * Filter dropdown berdasarkan wilayah benua (Asia, Eropa, Afrika, Amerika Utara, Amerika Selatan, Oseania, Antartika).
  * Modal formulir reaktif Vue (`<Teleport to="body">`) untuk Tambah Negara Baru dan Sunting Negara (ISO-2, ISO-3, nama umum, nama resmi, benua, ibu kota).
  * Aksi Hapus Negara yang dilengkapi verifikasi dependensi ke tabel perusahaan (mencegah penghapusan jika negara tercatat pada instansi/perusahaan aktif).
  * Sinkronisasi otomatis: Jika nama negara disunting, seluruh relasi nama negara pada tabel instansi perusahaan ikut diperbarui secara konsisten.

---

## 2. Modul Verifikasi & Kelola Perusahaan (`/superadmin/perusahaan`)

* **Status**: Selesai Diimplementasikan (Closed / Resolved).
* **Kebutuhan**: Fitur Hapus Perusahaan (Delete Action).
* **Solusi yang Telah Diterapkan**:
  * Ditambahkan tombol aksi **Hapus (Ikon Sampah)** pada setiap baris tabel perusahaan di modul Superadmin, Fakultas, dan Program Studi.
  * **Proteksi Integritas Relasi Alumni & Evaluasi Atasan**:
    * Sistem memverifikasi dependensi ke tabel `biodata` (`perusahaan_id`) dan `evaluasi_atasan`.
    * Jika perusahaan masih terhubung dengan data alumni aktif, penghapusan langsung **dicegah** dengan pesan peringatan interaktif yang mengarahkan admin untuk menggunakan fitur *"Sinkronkan / Ganti dengan Master"* terlebih dahulu.
    * Jika perusahaan tidak memiliki relasi alumni atau evaluasi atasan (misal: entitas usulan duplikat, uji coba, atau spam tanpa responden), perusahaan dapat dihapus permanen secara aman.
  * Pencatatan audit trail otomatis ke tabel `log_activities` saat penghapusan instansi perusahaan berhasil dieksekusi.

---

## 3. Konfigurasi Fleksibel Target Response Rate di Master Data

* **Status**: Selesai Diimplementasikan (Closed / Resolved).
* **Kebutuhan**: Pengaturan target persentase response rate yang dapat disesuaikan per periode/tahun.
* **Solusi yang Telah Diterapkan**:
  * **Database & Model**:
    * Menambahkan kolom `target_response_rate` (DECIMAL 5,2 NOT NULL DEFAULT 60.00) pada tabel `kuesioner` dan memperbarui model [Kuesioner.php](file:///c:/study/tracer-ta/app/Models/Kuesioner.php) serta migration aktif [2026_09_01_000019_create_kuesioner_table.php](file:///c:/study/tracer-ta/database/migrations/2026_09_01_000019_create_kuesioner_table.php).
    * Menyempurnakan validasi dan penyimpanan pada [KelolaKuesionerController.php](file:///c:/study/tracer-ta/app/Http/Controllers/SuperAdmin/KelolaSection/KelolaKuesionerController.php) (`min:1|max:100`).
  * **Antarmuka Pengelolaan Kuesioner Induk**:
    * Pada [Pertanyaan/Index.vue](file:///c:/study/tracer-ta/resources/js/Pages/SuperAdmin/Pertanyaan/Index.vue): Menambahkan kolom *"Target Response Rate"* pada tabel Tab 4 dan input numerik berskala desimal pada modal Tambah/Sunting Kuesioner Induk.
    * Pada [Section/Index.vue](file:///c:/study/tracer-ta/resources/js/Pages/SuperAdmin/Section/Index.vue): Menampilkan target pada kartu daftar kuesioner induk serta input target pada modal form kuesioner.
  * **Dinamisasi Backend & Visualisasi Analitik (Indikator 01)**:
    * Pada [TracerAnalyticsService.php](file:///c:/study/tracer-ta/app/Services/Analytics/TracerAnalyticsService.php): Metode `resolveTargetResponseRate()` otomatis membaca target dari kuesioner periode aktif/tahun terpilih (fallback ke 60,0%). Formula perhitungan `target_gap` dan penentuan kriteria capaian (*Sangat Baik* $\ge \text{target} + 10\%$, *Memenuhi Target* $\ge \text{target}$, *Perlu Peningkatan* $< \text{target}$) kini terikat pada target dinamis.
    * Pada [01_ResponseRate.vue](file:///c:/study/tracer-ta/resources/js/components/analytics/categories/01_ResponseRate.vue): Garis putus-putus merah `markLine`, pewarnaan batang, label switcher tab (*Ranking & Target X%*), serta tooltip interaktif otomatis terkalibrasi dinamis dengan nilai target dari server.
    * Pada [GrafikUniv.vue](file:///c:/study/tracer-ta/resources/js/Pages/SuperAdmin/Analytics/GrafikUniv.vue): Kartu ringkasan KPI *Response Rate* langsung menampilkan nilai target dan gap capaian secara terperinci.
  * **Pengujian Otomatis**:
    * Menambahkan unit/feature test `test_response_rate_uses_configured_target_from_kuesioner` pada [SuperAdminAnalyticsTest.php](file:///c:/study/tracer-ta/tests/Feature/SuperAdminAnalyticsTest.php) dan seluruh 13 pengujian analitik terverifikasi berhasil (100% pass).

---

## 4. Analisis & Rencana Optimasi Performa (Mengatasi Loading Lambat)

* **Status**: Selesai Diimplementasikan (Closed / Resolved).
* **Masalah**: Waktu muat (loading time) terasa lambat pada halaman verifikasi instansi/perusahaan dan query relasi referensi analitik.
* **Solusi & Optimasi yang Telah Diterapkan**:
  1. **Asinkronisasi Algoritma Rekomendasi Kemiripan di Halaman Perusahaan**:
     * Menghapus loop kalkulasi berat `getSimilarRecommendations()` dari method `index()` pada [VerifikasiPerusahaanSuperAdminController.php](file:///c:/study/tracer-ta/app/Http/Controllers/SuperAdmin/Perusahaan/VerifikasiPerusahaanSuperAdminController.php), [VerifikasiPerusahaanFakultasController.php](file:///c:/study/tracer-ta/app/Http/Controllers/AdminFakultas/VerifikasiPerusahaan/VerifikasiPerusahaanFakultasController.php), dan [VerifikasiPerusahaanProdiController.php](file:///c:/study/tracer-ta/app/Http/Controllers/AdminProdi/VerifikasiPerusahaan/VerifikasiPerusahaanProdiController.php).
     * Mendaftarkan endpoint API AJAX asinkron:
       * `GET /superadmin/perusahaan/{id}/recommendations`
       * `GET /fakultas/perusahaan/{id}/recommendations`
       * `GET /prodi/perusahaan/{id}/recommendations`
     * Pada [Index.vue](file:///c:/study/tracer-ta/resources/js/Pages/SuperAdmin/Perusahaan/Index.vue):
       * Saat tabel pertama kali dimuat, server langsung merespons secara instan dalam hitungan milidetik tanpa terbebani Levenshtein distance ribuan karakter.
       * Pada modal peninjauan usulan (`openReviewModal`), data rekomendasi diambil secara asinkron di latar belakang dengan indikator pemuatan animasi formal tema UKDW `#0D542B`.
       * Pada kolom tabel "Rekomendasi Master", admin dapat memeriksa kemiripan per baris secara *on-demand* via tombol *"Cek Kemiripan"*. Hasil yang telah diambil di-*cache* di memori browser sehingga tidak memicu request berulang.
  2. **Cascading Dropdown Dinamis Kabupaten per Provinsi**:
     * Menghentikan pengiriman muatan 514 baris seluruh kabupaten di Indonesia pada props Inertia halaman perusahaan (`'kabupatens' => []`).
     * Mendaftarkan endpoint helper wilayah: `GET /api/wilayah/kabupaten/{propinsiId}` pada [MasterWilayahController.php](file:///c:/study/tracer-ta/app/Http/Controllers/SuperAdmin/MasterWilayah/MasterWilayahController.php) dengan proteksi cache memori 24 jam (`Cache::remember`).
     * Cache otomatis dibersihkan saat admin melakukan tambah, sunting, atau hapus kabupaten pada modul Master Wilayah.
     * Pada modal Tambah dan Sunting instansi [Index.vue](file:///c:/study/tracer-ta/resources/js/Pages/SuperAdmin/Perusahaan/Index.vue), kabupaten diambil secara dinamis hanya saat provinsi dipilih atau saat modal sunting dibuka.
  3. **Indexing Database Transaksional & Relasi Referensi**:
     * Menambahkan composite index `data_akademik_tahun_lulus_index` pada [2026_09_01_000011_create_data_akademik_table.php](file:///c:/study/tracer-ta/database/migrations/2026_09_01_000011_create_data_akademik_table.php) (SQLite & MySQL).
     * Menambahkan composite index `perusahaan_status_verifikasi_index` pada [2026_09_01_000014_create_perusahaan_table.php](file:///c:/study/tracer-ta/database/migrations/2026_09_01_000014_create_perusahaan_table.php) (SQLite & MySQL) serta menerapkan index pada database runtime aktif untuk mempercepat kalkulasi KPI dan paginasi status verifikasi instansi.
  4. **Multi-Instance Server Lokal PHP CLI**:
     * Menemukan dan mendokumentasikan anomali dua proses server lokal `php artisan serve` yang berjalan berbarengan, dengan instruksi pemeliharaan agar request lokal tidak terhambat *single-threaded bottleneck*.
  5. **Pengujian Otomatis & Jaminan Kualitas**:
     * Menambahkan test `test_async_recommendations_endpoint_returns_json` dan `test_cascading_kabupaten_endpoint_returns_json` pada [VerifikasiPerusahaanTest.php](file:///c:/study/tracer-ta/tests/Feature/VerifikasiPerusahaanTest.php). Seluruh 11 pengujian verifikasi perusahaan lulus 100%.

---

## 5. Tata Kelola Status Kuesioner Induk (Aktif vs Nonaktif) & Visibilitas Alumni

* **Status**: Selesai Diimplementasikan (Closed / Resolved).
* **Latar Belakang Permasalahan**:
  * Pada modul Superadmin (`/superadmin/pertanyaan` dan `/superadmin/section`), terdapat kartu info di header dengan label kaku **"Instrumen Kuesioner Induk Aktif"** yang memuat kuesioner fallback `[2021] Tracer Study UKDW 2021` meskipun kuesioner tersebut berstatus **Nonaktif** (`is_active = false`).
  * Hal tersebut menimbulkan kebingungan karena status aslinya nonaktif, namun judul antarmuka menyebutnya aktif.
* **Solusi yang Telah Diterapkan**:
  1. **Sisi Super Admin (`SuperAdmin/Pertanyaan/Index.vue` & `SuperAdmin/Section/Index.vue`)**:
     * Label header berubah dinamis: Menampilkan *"Instrumen Kuesioner Induk Aktif"* jika aktif, atau *"Pratinjau Kuesioner Induk (Status Nonaktif)"* jika nonaktif.
     * Ditambahkan badge status visual: Badge hijau **Aktif** vs badge abu-abu **Nonaktif (Draft / Arsip)**.
     * Tombol aksi langsung di header: Tersedia tombol *"Aktifkan Kuesioner Ini"* (jika nonaktif) dan tombol *"Nonaktifkan"* (jika aktif).
  2. **Sisi Responden Alumni (`Alumni/Kuesioner/KuesionerController.php`)**:
     * Verifikasi gerbang akses: Sistem hanya menampilkan kuesioner berstatus `is_active = true`.
     * Jika tidak ada kuesioner aktif, alumni dicegah mengisi dan disajikan pesan formal: *"Tidak ada kuesioner aktif saat ini."*
  3. **Integritas Aturan Single Active (`KelolaKuesionerController@toggleActive`)**:
     * Ketika kuesioner periode tertentu diaktifkan, seluruh kuesioner periode lain otomatis diubah menjadi nonaktif oleh backend.

---

## 6. Rencana Otomasi Periode & Kloning Kuesioner Induk

* **Kebutuhan**: Penjadwalan Periode Pengisian Otomatis dan Fitur Kloning Soal Antartahun.
* **Deskripsi**:
  1. **Penjadwalan Otomatis (Start Date & End Date)**:
     * Menambahkan atribut `start_date` dan `end_date` pada tabel kuesioner.
     * Kuesioner akan otomatis terbuka dan tertutup bagi alumni sesuai rentang tanggal yang ditetapkan tanpa memerlukan pengubahan status secara manual.
  2. **Fitur Kloning Kuesioner (Duplicate Questionnaire)**:
     * Menyediakan tombol aksi satu klik untuk menyalin seluruh struktur kuesioner induk (bagian seksi, butir subpertanyaan, opsi pilihan, bobot, dan logika percabangan jump-to) dari tahun sebelumnya ke periode kuesioner baru.
     * Mempercepat persiapan kuesioner tahunan baru tanpa perlu menginput ulang pertanyaan dari awal.
  3. **Integrasi Broadcast / Pengingat Pengisian**:
     * Mengirim notifikasi email otomatis ke akun alumni terdaftar ketika periode kuesioner baru resmi dibuka oleh Superadmin.
