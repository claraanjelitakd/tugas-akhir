# Wishlist & Rencana Pengembangan Sistem Tracer Study

Dokumen ini mencatat daftar kebutuhan fitur baru, penyempurnaan modul, serta agenda optimasi teknis yang diajukan untuk iterasi pengembangan berikutnya.

---

## 1. Modul Master Wilayah (`/superadmin/master-wilayah`)

* **Kebutuhan**: Penambahan Manajemen Master Negara.
* **Deskripsi**:
  * Saat ini modul Master Wilayah baru mencakup Provinsi, Kabupaten/Kota, dan ketetapan Upah Minimum Provinsi (UMP).
  * Diperlukan penambahan tab atau submodul **Master Negara** (memanfaatkan tabel referensi negara kanonik `ref_negara`) yang dilengkapi fitur pencarian, penambahan, edit nama negara, dan status aktif.
* **Tujuan**:
  * Mendukung pencatatan dan normalisasi lokasi kerja serta studi lanjut alumni yang berada di luar negeri (perusahaan multinasional dan perguruan tinggi internasional) agar terintegrasi dengan filter analitik.

---

## 2. Modul Verifikasi & Kelola Perusahaan (`/superadmin/perusahaan`)

* **Kebutuhan**: Fitur Hapus Perusahaan (Delete Action).
* **Deskripsi**:
  * Saat ini antarmuka verifikasi perusahaan baru menyediakan aksi Verifikasi (Approve), Tolak (Reject), Edit Data, dan Gabung/Konsolidasi (Merge).
  * Belum tersedia tombol aksi **Hapus** untuk menghapus entitas perusahaan yang tidak valid, duplikat ekstrem, atau hasil input uji coba.
* **Pertimbangan Teknis & Integritas Data**:
  * Pengecekan relasi dependensi: Jika perusahaan sudah terhubung dengan data alumni (`biodata.perusahaan_id`), sistem harus mencegah penghapusan langsung dan memberikan notifikasi jumlah alumni yang terdampak.
  * Opsi penanganan: Menerapkan soft delete (`deleted_at`) atau memindahkan relasi alumni ke perusahaan lain sebelum dihapus permanen.

---

## 3. Konfigurasi Fleksibel Target Response Rate di Master Data

* **Kebutuhan**: Pengaturan target persentase response rate yang dapat disesuaikan per periode/tahun.
* **Deskripsi**:
  * Angka target capaian response rate pada Dashboard Analitik Indikator 01 saat ini ditetapkan pada standar 60,0% (merujuk pada standar Kemendikbudristek dan IKU 1).
  * Diperlukan pengaturan fleksibel pada menu pengaturan/master data Superadmin agar target tahunan dapat diubah sesuai rencana strategis universitas (misal: dinaikkan menjadi 70% atau 75%).
* **Dampak**:
  * Garis target merah pada grafik *Ranking Response Rate*, badge capaian mutu (*Sangat Baik*, *Memenuhi Target*, *Perlu Peningkatan*), serta formula perhitungan gap capaian akan otomatis membaca nilai target dinamis dari database.

---

## 4. Analisis & Rencana Optimasi Performa (Mengatasi Loading Lambat)

* **Masalah**: Waktu muat (loading time) terasa lama pada beberapa halaman Superadmin.
* **Faktor Penyebab Teridentifikasi**:
  1. **Algoritma Rekomendasi Kemiripan di Halaman Perusahaan**:
     * Pada `VerifikasiPerusahaanSuperAdminController@index`, fungsi `getSimilarRecommendations()` dijalankan secara *in-loop* untuk seluruh baris data dalam paginasi. Algoritma pembanding teks (Levenshtein, token overlap, dan geographic matching) membebani CPU server saat memuat tabel.
     * *Rencana Optimasi*: Mengubah rekomendasi kemiripan menjadi pemanggilan asinkron (AJAX) yang hanya dipicu saat pengguna membuka modal peninjauan/verifikasi, bukan saat me-render seluruh tabel.
  2. **Muat Master Dropdown Berukuran Besar Sekaligus**:
     * Memuat seluruh daftar kabupaten di Indonesia (514+ baris) secara utuh ke dalam props Inertia pada setiap request halaman perusahaan.
     * *Rencana Optimasi*: Menggunakan cascading dropdown dinamis yang hanya mengambil kabupaten berdasarkan provinsi terpilih.
  3. **Multi-Instance Server Lokal PHP CLI**:
     * Terdapat lebih dari satu proses `php artisan serve` yang berjalan bersamaan pada lingkungan lokal. Server bawaan PHP bersifat *single-threaded*, sehingga request yang masuk secara paralel (Inertia payload, static asset, dan prefetch) harus mengantre.
     * *Rencana Optimasi*: Menjaga satu instance web server aktif dan mematikan instance ganda yang saling berebut port/koneksi.
  4. **Indexing Database View**:
     * Menambahkan composite index pada tabel referensi transaksional agar join database view analitik merespons di bawah 50ms secara konsisten.

---

## 5. Tata Kelola Status Kuesioner Induk (Aktif vs Nonaktif) & Visibilitas Alumni

* **Latar Belakang & Isu Saat Ini**:
  * Pada modul Superadmin (`/superadmin/pertanyaan` dan `/superadmin/section`), terdapat kartu info di header dengan label **"Instrumen Kuesioner Induk Aktif"** yang menampilkan kuesioner `[2021] Tracer Study UKDW 2021` meskipun di database status kuesioner tersebut berstatus **Nonaktif** (`is_active = false`).
  * **Penyebab Teknis di Backend**:
    * Di controller (`DaftarPertanyaanController.php`), terdapat logika fallback:
      ```php
      $activeKuesioner = $kuesioners->firstWhere('is_active', true) ?? $kuesioners->first();
      ```
    * Jika tidak ada kuesioner yang berstatus aktif sama sekali, sistem mengambil kuesioner pertama sebagai pengganti sementara untuk pratinjau struktur butir, namun teks header tetap menyatakan *"Instrumen Kuesioner Induk Aktif"*.
* **Tindakan & Perilaku Sistem yang Diharapkan**:
  1. **Di Sisi Responden Alumni**:
     * **Jika Kuesioner Berstatus Nonaktif**: Alumni **TIDAK BISA** melihat dan mengisi kuesioner tersebut. Sistem harus menyajikan halaman informasi ramah pengguna bahwa *"Periode pengisian tracer study sedang ditutup / belum dibuka"*.
     * **Hanya Kuesioner Berstatus Aktif (`is_active = true`)** yang boleh ditampilkan dan dapat diisi oleh alumni.
  2. **Di Sisi Admin & Superadmin**:
     * **Koreksi Label Header**: Jika kuesioner yang sedang ditampilkan berstatus nonaktif (karena hasil fallback), label tidak boleh menyatakan *"Kuesioner Induk Aktif"*, melainkan harus secara jujur menampilkan badge peringatan: **"Status: Nonaktif (Draft/Arsip)"**.
     * **Tombol Cepat Aktivasi**: Menyediakan tombol pintas di header **"Aktifkan Kuesioner Ini"** dengan konfirmasi SweetAlert sehingga Superadmin dapat langsung mengaktifkan kuesioner tahun berjalan yang diinginkan untuk dibuka kepada seluruh alumni.
     * **Aturan Single Active Questionnaire**: Memastikan saat satu kuesioner diaktifkan, kuesioner induk dari tahun/periode lain secara otomatis diubah menjadi nonaktif agar alumni tidak menghadapi kuesioner ganda.
