# Katalog & Deskripsi Komprehensif Tangkapan Layar Sistem (Bab 4 Skripsi)
## Diselaraskan dengan Use Case Diagram (UC-01 s.d. UC-23) Tracer Study UKDW

Dokumen ini memuat deskripsi mendalam, analisis fungsional, dan penjelasan visual untuk setiap tangkapan layar (*screenshot*) yang digunakan pada Bab 4 (Implementasi dan Pengujian Sistem). Setiap bagian menguraikan fungsi layar, komponen filter, indikator yang ditampilkan, alur interaksi pengguna (*stepper/modal*), serta kontrak datanya.

Seluruh berkas gambar beresolusi tinggi (*HiDPI*) tersimpan di direktori: `docs/screenshots/`

---

## Bagian 1: Antarmuka Alur Pengguna Alumni (UC-01 s.d. UC-07)

### Gambar 4.1: Halaman Masuk Sesi (Login Akun Pengguna)
* **Use Case Terkait:** `UC-01 (Login)`
* **Nama Berkas:** `01_alumni_login.png`
* **Path:** `docs/screenshots/01_alumni_login.png`
* **Penjelasan Fungsional & Komponen Antarmuka:**
  * **Tujuan:** Gerbang pintu masuk autentikasi tunggal (*single entry point*) bagi seluruh pengguna sistem, baik alumni maupun administrator lintas jenjang.
  * **Komponen Antarmuka:**
    * *Field Identifikasi:* Mendukung input berupa alamat surel terdaftar maupun Nomor Induk Mahasiswa (NIM).
    * *Field Keamanan:* Input kata sandi dengan toggle visibilitas sandi (*show/hide password*).
    * *Aksi & Navigasi:* Tombol "Masuk Sesi", opsi "Ingat Saya" (*remember me* via token terenkripsi), dan tautan bantuan "Lupa Kata Sandi".
    * *Estetika Visual:* Menampilkan identitas resmi Pusat Karir dan Alumni (PKA) UKDW, logo institusi, serta latar belakang fotografi wisuda resmi UKDW yang diperhalus dengan filter *backdrop-blur* agar tipografi formulir tetap terbaca kontras.

### Gambar 4.2: Halaman Mengubah Kata Sandi Wajib (*Must Change Password*)
* **Use Case Terkait:** `UC-02 (Mengubah Kata Sandi)`
* **Nama Berkas:** `02_alumni_must_change_password.png`
* **Path:** `docs/screenshots/02_alumni_must_change_password.png`
* **Penjelasan Fungsional & Komponen Antarmuka:**
  * **Tujuan:** Melindungi keamanan privasi data alumni dengan memaksa pergantian kata sandi ketika alumni pertama kali login menggunakan kredensial bawaan (*default password* berbasis tanggal lahir/NIM).
  * **Komponen Antarmuka:**
    * *Validasi Sandi Baru:* Input kata sandi baru dan konfirmasi kata sandi dengan indikator kekuatan sandi (*password strength meter*) yang mengevaluasi kombinasi karakter, angka, dan panjang minimum.
    * *Keamanan Sesi:* Dilengkapi pesan peringatan formal bahwa akun belum dapat mengakses instrumen survei sebelum kata sandi diperbarui.

### Gambar 4.3: Halaman Memulihkan Kata Sandi (*Forgot Password*)
* **Use Case Terkait:** `UC-03 (Memulihkan Kata Sandi)`
* **Nama Berkas:** `03_alumni_lupa_password.png`
* **Path:** `docs/screenshots/03_alumni_lupa_password.png`
* **Penjelasan Fungsional & Komponen Antarmuka:**
  * **Tujuan:** Menyediakan mekanisme pemulihan mandiri jika alumni lupa kata sandi tanpa harus menghubungi admin biro secara manual.
  * **Komponen Antarmuka:**
    * *Input Surel:* Input alamat surel yang terhubung dengan akun alumni.
    * *Keamanan Token:* Tombol "Kirim Tautan Pemulihan" yang memicu pengiriman email resmi berisikan token unik bersandi acak (*crypto-secure token*) dengan batas kedaluwarsa 60 menit dan proteksi pembatasan frekuensi klik (*rate-limiting throttle: 5,1*).

### Gambar 4.4: Halaman Dashboard Utama Portal Alumni
* **Use Case Terkait:** `Portal Alumni Utama`
* **Nama Berkas:** `04_alumni_dashboard.png`
* **Path:** `docs/screenshots/04_alumni_dashboard.png`
* **Penjelasan Fungsional & Komponen Antarmuka:**
  * **Tujuan:** Menjadi pusat kendali (*command center*) bagi alumni untuk memantau status penyelesaian tracer study mereka.
  * **Komponen Antarmuka:**
    * *Navbar Terpadu:* Header yang memuat logo UKDW, tautan navigasi tab utama (Dashboard, Profil, Kuesioner Universitas, Kuesioner Prodi), serta *avatar pill* di pojok kanan yang menampilkan foto profil lokal resmi beresolusi tinggi dengan dropdown info NIM dan aksi Logout.
    * *Tiga Kartu Tahapan Progres:*
      1. **Tahap 1 (Profil & Riwayat Karier):** Menampilkan persentase kelengkapan data diri dan karier (contoh: 100% - 25 dari 25 data terisi) dengan tombol "Perbarui Profil".
      2. **Tahap 2 (Kuesioner Universitas):** Menampilkan progres keterisian butir wajib survei Dikti (contoh: 100% - 19 butir terjawab) dengan tombol "Lihat Kuesioner".
      3. **Tahap 3 (Kuesioner Program Studi):** Menampilkan progres pengisian survei evaluasi kurikulum prodi (contoh: 93% - 41 dari 44 butir terjawab) dengan tombol "Lanjutkan Kuesioner Prodi".
    * *Desain Visual:* Latar belakang foto wisuda berbalut overlay lembut dengan kartu berbingkai hijau UKDW `#0D542B` yang bersih dan bebas dari emoticon/dekorasi berlebih.

### Gambar 4.5: Halaman Kelola Profil & Riwayat Pekerjaan Alumni
* **Use Case Terkait:** `UC-04 (Mengelola Profile Alumni) & UC-05 (Menambah Perusahaan)`
* **Nama Berkas:** `05_alumni_profile.png`
* **Path:** `docs/screenshots/05_alumni_profile.png`
* **Penjelasan Fungsional & Komponen Antarmuka:**
  * **Tujuan:** Tempat alumni memverifikasi data akademik master dan mencatat riwayat karier serta atasan langsung mereka.
  * **Struktur Bagian Formulir (*Accordion/Card*):**
    * *1. Data Akademik Master (Read-Only):* Menampilkan nama lengkap, NIM, jenis kelamin, fakultas, program studi, tahun masuk, dan tanggal yudisium yang terkunci dari database akademik universitas demi menjaga integritas data.
    * *2. Kontak Pribadi & Domisili:* Input nomor telepon WhatsApp aktif, surel pribadi korespondensi, alamat domisili saat ini lengkap dengan pemilihan berjenjang Negara, Provinsi, Kabupaten/Kota, Kecamatan, dan Kode Pos.
    * *3. Profil Profesional Media Sosial:* Input URL akun media sosial, khususnya URL profil LinkedIn alumni yang digunakan sistem sebagai jangkar penelusuran scraping riwayat kerja.
    * *4. Status Pekerjaan & Riwayat Karier:* Pilihan status terkini (Bekerja, Wirausaha, Melanjutkan Studi, Belum Bekerja). Jika bekerja, menampilkan pemilihan autocomplete nama kantor/perusahaan dari Master Perusahaan, posisi jabatan, divisi, kategori instansi (F11 Dikti), dan perkiraan pendapatan awal.
    * *5. Usulan Perusahaan Baru (UC-05):* Tombol "Tambah Usulan Perusahaan" yang memunculkan modal khusus jika kantor alumni belum terdaftar di database institusi.
    * *6. Kontak Atasan Langsung:* Input nama atasan langsung, jabatan atasan, dan surel resmi atasan yang nantinya menjadi target pengiriman undangan survei kepuasan pengguna lulusan.

### Gambar 4.6: Formulir Kuesioner Tracer Study Nasional Tingkat Universitas
* **Use Case Terkait:** `UC-06 (Mengisi Kuesioner Universitas)`
* **Nama Berkas:** `06_alumni_kuesioner_univ.png`
* **Path:** `docs/screenshots/06_alumni_kuesioner_univ.png`
* **Penjelasan Fungsional & Komponen Antarmuka:**
  * **Tujuan:** Pengumpulan data penelusuran alumni berbasis instrumen standar nasional Kementerian Pendidikan Tinggi (Kemendikbudristek/Dikti).
  * **Komponen & Alur Stepper:**
    * *Stepper Navigasi:* Menampilkan seksi-seksi pertanyaan bertahap (Fasilitas Pembelajaran, Pengalaman Kuliah, Saluran Pencarian Kerja, Masa Tunggu, Keselarasan Bidang Kerja, dan Tingkat Pendidikan).
    * *Logika Percabangan Dinamis (*Branching Logic*):* Menampilkan pertanyaan tindak lanjut secara otomatis sesuai pilihan alumni. Contoh: Jika memilih "Bekerja sebelum lulus", pertanyaan masa tunggu pencarian kerja pasca-wisuda otomatis dilewati (*skip*).
    * *Indikator Validasi:* Menampilkan tanda wajib isi (*asterisk merah*) dan pesan konfirmasi otomatis sebelum lembar jawaban disimpan secara permanen ke tabel `kuesioner_jawaban`.

### Gambar 4.7: Formulir Kuesioner Khusus Program Studi
* **Use Case Terkait:** `UC-07 (Mengisi Kuesioner Program Studi)`
* **Nama Berkas:** `07_alumni_kuesioner_prodi.png`
* **Path:** `docs/screenshots/07_alumni_kuesioner_prodi.png`
* **Penjelasan Fungsional & Komponen Antarmuka:**
  * **Tujuan:** Instrumen survei mandiri tingkat program studi untuk keperluan akreditasi LAM-INFOKOM, BAN-PT, maupun akreditasi internasional.
  * **Komponen Antarmuka:**
    * *Materi Evaluasi:* Matriks butir evaluasi relevansi kurikulum, kontribusi mata kuliah wajib keilmuan, kepuasan terhadap bimbingan skripsi/tugas akhir, sarana perangkat keras laboratorium, dan kecepatan akses internet kampus.
    * *Skala Penilaian:* Menggunakan format pilihan radio terstruktur (Sangat Kurang, Kurang, Cukup, Baik, Sangat Baik) dan kolom saran terbuka perbaikan kurikulum prodi.

---

## Bagian 2: Antarmuka Alur Pengguna Lulusan / Atasan Langsung (UC-08)

### Gambar 4.8: Formulir Evaluasi Langkah 1 — Karakteristik Perusahaan & Sebaran Alumni
* **Use Case Terkait:** `UC-08 (Evaluasi Kinerja Alumni - Langkah 1)`
* **Nama Berkas:** `08_atasan_langkah1_karakteristik.png`
* **Path:** `docs/screenshots/08_atasan_langkah1_karakteristik.png`
* **Penjelasan Fungsional & Komponen Antarmuka:**
  * **Tujuan:** Mengetahui profil organisasi mitra kerja serta keterlibatan alumni UKDW di lingkungan industri tersebut.
  * **Komponen Antarmuka:**
    * *Akses Publik Bertoken:* Dibuka secara publik melalui URL bertoken acak tanpa mewajibkan login akun, menjamin kemudahan akses bagi atasan/HRD korporasi.
    * *Banner Sapaan Formal:* Menyapa nama atasan penilai secara hormat dan merangkum nama alumni yang dinilai, prodi, fakultas, serta NIM.
    * *Tiga Butir Karakteristik Organisasi:*
      1. Jumlah Pegawai Keseluruhan di Perusahaan (Pilihan: < 20 orang, 20-100 orang, > 100 orang).
      2. Jumlah Alumni UKDW yang Bekerja di Instansi tersebut.
      3. Standar Gaji Pertama per bulan yang diberikan instansi kepada lulusan UKDW.
    * *Navigasi:* Tombol "Lanjut ke Penilaian Kinerja" yang memvalidasi keterisian data sebelum melangkah ke lembar skala Likert.

### Gambar 4.9: Formulir Evaluasi Langkah 2 — Penilaian Kinerja Lulusan (Matriks Skala Likert)
* **Use Case Terkait:** `UC-08 (Evaluasi Kinerja Alumni - Langkah 2)`
* **Nama Berkas:** `09_atasan_langkah2_skala_likert.png`
* **Path:** `docs/screenshots/09_atasan_langkah2_skala_likert.png`
* **Penjelasan Fungsional & Komponen Antarmuka:**
  * **Tujuan:** Mengukur secara obyektif kesiapan dan kompetensi kerja alumni UKDW di dunia industri.
  * **Komponen Antarmuka:**
    * *1. Penilaian Kesiapan Kerja (Adaptasi):* Skala pilihan 4 tingkat (Tidak Siap, Kurang Siap, Siap, Sangat Siap) untuk mengukur kecepatan adaptasi alumni di awal masa kerja.
    * *2. Matriks Skala Likert 7 Aspek Kinerja Lulusan:* Tabel penilaian komparatif dengan kolom skala 1 s.d. 5 (Sangat Kurang, Kurang, Cukup, Baik, Sangat Baik) untuk 7 kompetensi inti:
      1. Etika dan Moral Kerja.
      2. Keahlian Berdasarkan Bidang Ilmu (*Hard Skills*).
      3. Kemampuan Bahasa Asing / Bahasa Inggris.
      4. Penggunaan Teknologi Informasi.
      5. Kemampuan Komunikasi Interpersonal.
      6. Kerja Sama dalam Tim (*Teamwork*).
      7. Pengembangan Diri dan Kemauan Belajar.
    * *Aksi Akhir:* Kolom catatan/masukan kualitatif untuk universitas dan tombol "Kirim Evaluasi Kinerja".

---

## Bagian 3: Antarmuka Alur Administrator (UC-09 s.d. UC-23)

### Gambar 4.10: Dashboard Eksekutif Administrator & Filter Multidimensi
* **Use Case Terkait:** `Dashboard Super Administrator`
* **Nama Berkas:** `10_admin_dashboard.png`
* **Path:** `docs/screenshots/10_admin_dashboard.png`
* **Penjelasan Fungsional & Komponen Antarmuka:**
  * **Tujuan:** Memberikan tinjauan menyeluruh (*high-level executive overview*) performa tracer study dan status penelusuran lulusan.
  * **Komponen Antarmuka:**
    * *Panel Filter Multidimensi:* Filter dinamis berbasis Tahun Kelulusan (Tahun +1, +2), Rentang Periode Yudisium, Fakultas, dan Program Studi dengan tombol *Reset Filter*.
    * *Kartu Statistik Ringkasan:*
      * Total Populasi Alumni Kohort.
      * Jumlah Responden yang Telah Mengisi Survei beserta persentase Response Rate.
      * Jumlah Responden yang Telah Bekerja, Wirausaha, dan Melanjutkan Studi.
      * Rata-rata Skor Kinerja Lulusan dari Atasan.
    * *Grafik Cepat Beranda:* Diagram batang sebaran status kelulusan dan linimasa partisipasi pengisian survei mingguan.

### Gambar 4.11: Master Data Alumni & Kolom Aksi Sinkronisasi LinkedIn
* **Use Case Terkait:** `UC-15 (Mengelola Data Alumni)`
* **Nama Berkas:** `11_admin_master_alumni.png`
* **Path:** `docs/screenshots/11_admin_master_alumni.png`
* **Penjelasan Fungsional & Komponen Antarmuka:**
  * **Tujuan:** Pengelolaan menyeluruh direktori lulusan, penelusuran status pengisian kuesioner, dan pengoperasian sinkronisasi scraping data LinkedIn.
  * **Fitur Filter & Pencarian:**
    * Filter Tahun Lulus, Status Kuesioner (Lengkap, Sebagian, Belum Mengisi), Status Kerja, Fakultas, dan Program Studi.
    * Kotak pencarian langsung (*instant search*) berdasarkan NIM atau Nama alumni.
  * **Tabel Direktori Alumni:**
    * Menampilkan kolom NIM, Nama Lengkap, Program Studi, Tahun Wisuda, Pekerjaan & Kantor Terkini, Status Pengisian Kuesioner, serta Lencana Status LinkedIn.
    * *Kolom Aksi Terpadu:*
      1. Tombol *Detail Profil*: Membuka audit profil terpadu 5 tab alumni.
      2. Tombol *Sinkronisasi LinkedIn*: Memicu penarikan data profil scraping LinkedIn secara instan melalui Apify API.
      3. Tombol *Kirim Email*: Mengirim surel notifikasi personal pengingat tracer.

### Gambar 4.12: Detail Profil Alumni — Tab Riwayat & Komparasi Diff Sinkronisasi LinkedIn
* **Use Case Terkait:** `UC-17 (Sinkronisasi LinkedIn) & UC-15 (Detail Alumni)`
* **Nama Berkas:** `12_admin_detail_alumni_linkedin.png`
* **Path:** `docs/screenshots/12_admin_detail_alumni_linkedin.png`
* **Penjelasan Fungsional & Komponen Antarmuka:**
  * **Tujuan:** Menampilkan audit mendalam pada salah satu profil alumni terpilih serta memvalidasi hasil scraping LinkedIn.
  * **Struktur 5 Tab Audit Terpadu:**
    * *Tab 1 (Data Diri & Akademik):* Profil lengkap, status yudisium, IPK, kontak, dan alamat domisili.
    * *Tab 2 (Kuesioner Universitas):* Rincian jawaban butir kuesioner Dikti.
    * *Tab 3 (Kuesioner Program Studi):* Rincian jawaban survei kurikulum prodi.
    * *Tab 4 (Evaluasi Pengguna Lulusan):* Hasil skor penilaian dan masukan dari atasan langsung.
    * *Tab 5 (Sinkronisasi LinkedIn - Aktif di Gambar):*
      * Menampilkan tabel komparasi dua kolom (*Old Values vs LinkedIn Scraped Values*) untuk Nama Kantor, Jabatan, Tanggal Mulai Bekerja, dan Ringkasan Pengalaman.
      * Dilengkapi lencana kesesuaian kemiripan instansi (*similarity match percentage*) serta tombol persetujuan "Approve & Perbarui Profil" atau "Tolak Sinkronisasi".

### Gambar 4.13: Master Data Perusahaan Industri
* **Use Case Terkait:** `UC-18 (Master Data Perusahaan) & UC-19 (Verifikasi Perusahaan)`
* **Nama Berkas:** `13_admin_master_perusahaan.png`
* **Path:** `docs/screenshots/13_admin_master_perusahaan.png`
* **Penjelasan Fungsional & Komponen Antarmuka:**
  * **Tujuan:** Menjaga kebersihan dan standardisasi entitas industri mitra tempat alumni berkarier.
  * **Komponen Antarmuka:**
    * *Tabel Entitas Instansi:* Memuat Nama Perusahaan Resmi, Sektor Kategori (F11), Alamat Kantor Pusat, Domisili Wilayah (Provinsi/Kabupaten), Skala Usaha, Jumlah Alumni yang Bekerja, dan Status Verifikasi (Terverifikasi / Usulan Baru).
    * *Fitur Verifikasi & Rekomendasi Similarity (UC-19):* Tombol aksi "Tinjau Rekomendasi Kemiripan" pada baris perusahaan yang memunculkan rekomendasi pencocokan nama otomatis guna mengonsolidasi entri duplikat (misal: "PT Gojek Indonesia" dengan "Gojek Tokopedia").

### Gambar 4.14: Master Data Pengguna Lulusan / Atasan Langsung
* **Use Case Terkait:** `UC-20 (Master Data Pengguna Lulusan)`
* **Nama Berkas:** `14_admin_master_atasan.png`
* **Path:** `docs/screenshots/14_admin_master_atasan.png`
* **Penjelasan Fungsional & Komponen Antarmuka:**
  * **Tujuan:** Mengelola daftar kontak supervisor yang berhak mengevaluasi kinerja lulusan UKDW.
  * **Komponen Antarmuka:**
    * *Tabel Direktori Atasan:* Menampilkan Nama Lengkap Atasan, Jabatan Struktural, Nama Perusahaan Tempat Bekerja, Alamat Email Resmi, Nomor Telepon/WhatsApp, Nama Alumni yang Dibimbing, Status Pengiriman Surel Undangan Evaluasi, dan Status Pengisian Survei (Belum Mengisi / Sudah Submit).
    * *Aksi Admin:* Tombol salin tautan evaluasi personal (*copy unique token URL*) dan tombol kirim ulang surel undangan evaluasi (*resend invitation email*).

### Gambar 4.15: Master Data Wilayah Administrasi (Provinsi & Kabupaten/Kota)
* **Use Case Terkait:** `UC-10 (Mengelola Master Wilayah)`
* **Nama Berkas:** `15_admin_master_wilayah.png`
* **Path:** `docs/screenshots/15_admin_master_wilayah.png`
* **Penjelasan Fungsional & Komponen Antarmuka:**
  * **Tujuan:** Mengelola hierarki pembagian wilayah administratif nasional dan internasional untuk standardisasi isian kuesioner.
  * **Komponen Antarmuka:**
    * *Sistem Tab Navigasi Wilayah:* Tab Provinsi, Tab Kabupaten/Kota, Tab Negara Internasional, dan Tab UMP.
    * *Tabel Provinsi (Aktif di Gambar):* Memuat Kode BPS Provinsi, Nama Provinsi Resmi, Jumlah Kabupaten/Kota di dalamnya, serta tombol tambah/sunting/hapus wilayah.

### Gambar 4.16: Master Standar Upah Minimum Provinsi (UMP)
* **Use Case Terkait:** `UC-11 (Mengelola Master UMP)`
* **Nama Berkas:** `16_admin_master_ump.png`
* **Path:** `docs/screenshots/16_admin_master_ump.png`
* **Penjelasan Fungsional & Komponen Antarmuka:**
  * **Tujuan:** Menjadi landasan tolok ukur perhitungan ambang batas kelayakan pendapatan lulusan (1,2x UMP) pada formula Indikator Kinerja Utama 1 (IKU-1) S1 Kemendikbudristek No. 210/M/2023.
  * **Komponen Antarmuka:**
    * *Kartu Metrik UMP:* Rata-rata UMP Nasional tahun berjalan, UMP Tertinggi (Provinsi DKI Jakarta), UMP Terendah (Provinsi Jawa Tengah), dan Jumlah Provinsi yang Telah Ditetapkan.
    * *Tabel Besaran UMP:* Menampilkan Nama Provinsi, Tahun Penetapan, Nilai UMP Resmi (Rp), Nilai Ambang Batas 1,2x UMP (Rp), serta aksi edit besaran dan fitur "Salin Data UMP Antar-Tahun" untuk kemudahan pembaharuan tahunan.

### Gambar 4.17: Mengelola Kuesioner Universitas (Bank Pertanyaan & Section)
* **Use Case Terkait:** `UC-12 (Mengelola Kuesioner Universitas)`
* **Nama Berkas:** `17_admin_manajemen_pertanyaan_univ.png`
* **Path:** `docs/screenshots/17_admin_manajemen_pertanyaan_univ.png`
* **Penjelasan Fungsional & Komponen Antarmuka:**
  * **Tujuan:** Mengonfigurasi butir-butir instrumen survei tracer study nasional universitas secara dinamis tanpa mengubah baris kode program.
  * **Komponen Antarmuka:**
    * *Struktur Seksi (*Section Management*):* Pengelompokan butir pertanyaan berdasarkan seksi tahapan kuesioner dengan fitur seret dan lepas (*drag-and-drop reorder*).
    * *Manajemen Butir Soal:* Menampilkan Kode Pertanyaan (F1, F2, F5, dsb.), Label Teks Pertanyaan, Tipe Input (Pilihan Ganda Radio, Kotak Centang Checkbox, Angka Numerik, Input Teks), Saklar Visibilitas Aktif/Nonaktif, dan Tombol Kelola Opsi Respon.

### Gambar 4.18: Mengelola Kuesioner Pengguna Lulusan / Atasan
* **Use Case Terkait:** `UC-13 (Mengelola Kuesioner Pengguna Lulusan)`
* **Nama Berkas:** `18_admin_manajemen_evaluasi_atasan.png`
* **Path:** `docs/screenshots/18_admin_manajemen_evaluasi_atasan.png`
* **Penjelasan Fungsional & Komponen Antarmuka:**
  * **Tujuan:** Mengelola butir-butir pertanyaan instrumen penilaian atasan, mencakup butir karakteristik organisasi dan 7 dimensi kompetensi kinerja alumni.
  * **Komponen Antarmuka:**
    * Konfigurasi teks aspek kompetensi, deskripsi indikator kinerja, pengurutan urutan tampil butir pada tabel Likert, dan penentuan skala baku (1 s.d. 5).

### Gambar 4.19: Mengelola Kuesioner Program Studi
* **Use Case Terkait:** `UC-14 (Mengelola Kuesioner Program Studi)`
* **Nama Berkas:** `19_admin_manajemen_kuesioner_prodi.png`
* **Path:** `docs/screenshots/19_admin_manajemen_kuesioner_prodi.png`
* **Penjelasan Fungsional & Komponen Antarmuka:**
  * **Tujuan:** Memberikan otonomi kepada masing-masing program studi untuk menyusun butir evaluasi internal sesuai kurikulum prodi.
  * **Komponen Antarmuka:**
    * Pemilihan Program Studi aktif, penambahan butir pertanyaan spesifik mata kuliah keahlian prodi, dan pengaturan opsi jawaban kustom.

### Gambar 4.20: Manajemen Akun Pengguna & Hak Akses Role
* **Use Case Terkait:** `UC-09 (Manajemen Akun)`
* **Nama Berkas:** `20_admin_manajemen_akun.png`
* **Path:** `docs/screenshots/20_admin_manajemen_akun.png`
* **Penjelasan Fungsional & Komponen Antarmuka:**
  * **Tujuan:** Mengelola kredensial dan hak akses berbasis peran (*Role-Based Access Control / RBAC*).
  * **Komponen Antarmuka:**
    * *Tabel Akun Pengguna:* Daftar akun administrator yang memuat Nama Pengguna, Alamat Surel, Penugasan Peran (*Super Admin, Admin Biro 3, Admin Fakultas, Admin Prodi*), Fakultas/Prodi yang Dikelola, dan Status Akun.
    * *Aksi Manajemen:* Tombol tambah admin baru, ubah penetapan hak akses role, serta aksi pemulihan/reset kata sandi akun administrator.

---

### Tinjauan Khusus Laporan Analitik & Visualisasi Grafik (UC-21 & UC-22)

Sistem Tracer Study UKDW menyediakan **18 Indikator Laporan Analitik Universitas Utama** yang dapat dieksplorasi secara mendalam:
1. `Response Rate (Tingkat Partisipasi Responden)`
2. `IKU-1 S1: Angka Efisiensi Edukasi (AEE / Lulus Tepat Waktu)`
3. `IKU-1 S1: Capaian Bobot Lulusan (Outcomes Bekerja, Wirausaha, Studi Lanjut)`
4. `IKU-1 S1: Pembobotan Gaji terhadap 1,2x UMP`
5. `Metode Pembelajaran & Kontribusi Perkuliahan`
6. `Waktu Mulai Mencari Pekerjaan (Sebelum vs Setelah Lulus)`
7. `Saluran & Metode Pencarian Kerja (F4 Dikti)`
8. `Waktu Tunggu Mendapatkan Pekerjaan Pertama (F5 Dikti)`
9. `Corong Lamaran Pekerjaan (Jumlah Lamaran, Respons, & Wawancara)`
10. `Status Pekerjaan Terkini (Bekerja, Wiraswasta, Studi Lanjut, Belum Bekerja)`
11. `Aktivitas Pencarian Kerja bagi yang Belum Bekerja`
12. `Tipe & Sektor Tempat Kerja (Instansi Pemerintah, BUMN, Swasta, Wirausaha)`
13. `Profil Alumni yang Melanjutkan Studi Lanjut S2/Profesi`
14. `Perbandingan Gaji Pertama terhadap Standar 1,2x UMP`
15. `Keselarasan Horisontal (Kesesuaian Bidang Ilmu dengan Pekerjaan)`
16. `Keselarasan Vertikal (Kesesuaian Tingkat Pendidikan Sarjana)`
17. `Alasan Mengambil Pekerjaan yang Tidak Selaras`
18. `Gap Analysis Kompetensi (Bekal Lulusan vs Tuntutan Dunia Kerja)`

Dua indikator analitik inti yang disyaratkan secara komprehensif pada Bab 4 ditampilkan secara utuh (**Grafik + Kartu Kilas Analisis + Tabel Rincian Responden / Drill-Down**) pada Gambar 4.21 dan Gambar 4.22:

### Gambar 4.21: Laporan Analitik Capaian Bobot IKU S1 & Tabel Analisis Data
* **Use Case Terkait:** `UC-21 (Melihat Laporan - IKU S1 Outcomes)`
* **Nama Berkas:** `21_admin_grafik_iku_s1_dan_analisis.png`
* **Path:** `docs/screenshots/21_admin_grafik_iku_s1_dan_analisis.png`
* **Penjelasan Fungsional & Visualisasi Mendalam:**
  * **Tujuan:** Menyajikan evaluasi capaian Indikator Kinerja Utama 1 (IKU-1) Perguruan Tinggi sesuai Surat Keputusan Mendikbudristek No. 210/M/2023.
  * **Tiga Elemen Utama yang Ditampilkan Utuh di Layar:**
    1. *Horizontal Stacked Bar Chart ECharts:*  
       Menampilkan sebaran alumni pada 4 kategori aktivitas karier: Karyawan, Founder/Wirausaha, Freelancer, dan Melanjutkan Pendidikan. Di dalam batang grafik disajikan proporsi perolehan tier bobot:
       * *Karyawan:* Bobot 1.0 (Tunggu ≤ 6 bln & Gaji ≥ 1,2× UMP), Bobot 0.8 (Tunggu 6–12 bln & Gaji ≥ 1,2× UMP), Bobot 0.6 (Tunggu ≤ 12 bln & Gaji < 1,2× UMP), Bobot 0.0 (Tunggu > 12 bln).
       * *Wirausaha (Founder):* Bobot 1.2, 1.0, 0.8, 0.6.
       * *Melanjutkan Pendidikan:* Bobot 0.6 (Studi lanjut S2 ≤ 12 bln) dan Bobot 0.0.
       * *Freelancer & Lainnya:* Bobot 0.2–0.5 (Proporsional jam & upah).
    2. *Kartu Kilas Indikator (Narasi Humanis ala iOS Summary):*  
       Menyajikan ringkasan persentase kelulusan yang berkarier layak, perbandingan prodi dengan kontribusi tertinggi, serta skor akhir IKU universitas tanpa gaya kalimat artifisial.
    3. *Tabel Analisis Responden Terkait (*Drill-Down Table*):*  
       Tabel interaktif tepat di bawah grafik yang menyajikan daftar nama responden alumni yang membentuk segmen grafik yang diklik (NIM, Nama, Prodi, Kategori Karier, Masa Tunggu, Gaji Riil, Nilai UMP Provinsi Kantor, Poin Bobot yang Disumbangkan, dan Status Memenuhi IKU).

### Gambar 4.22: Laporan Analitik Gap Analysis Kompetensi Lulusan & Analisis Data
* **Use Case Terkait:** `UC-21 (Melihat Laporan - Gap Analysis Kompetensi)`
* **Nama Berkas:** `22_admin_grafik_gap_analysis_dan_analisis.png`
* **Path:** `docs/screenshots/22_admin_grafik_gap_analysis_dan_analisis.png`
* **Penjelasan Fungsional & Visualisasi Mendalam:**
  * **Tujuan:** Menganalisis kesenjangan (*gap*) antara bekal kompetensi nyata yang diperoleh alumni saat lulus (*Performance / Where We Are*) dibandingkan tuntutan kebutuhan dunia kerja profesional (*Potential / Where We Want To Be*).
  * **Komponen Visualisasi yang Ditampilkan Utuh:**
    1. *Diagram Garis Gap Analysis ECharts:*  
       Menampilkan dua garis tren titik koordinat dari Baseline (Titik Masuk Kuliah) menuju Evaluasi Dunia Kerja untuk 7 dimensi kompetensi (Etika, Keahlian Bidang Ilmu, Bahasa Inggris, TI, Komunikasi, Kerja Sama Tim, Pengembangan Diri). Area di antara kedua garis diarsir halus (*shaded polygon*) dengan lencana keterangan nilai defisit/surplus.
    2. *Selektor Dimensi Cepat:*  
       Tombol pill di bagian atas diagram untuk berpindah antar-dimensi secara instan tanpa memuat ulang halaman (*instant visit*).
    3. *Tabel Analisis Skor Rincian Responden:*  
       Tabel rincian di bawah grafik yang membedah nilai rata-rata penilaian atasan langsung vs penilaian diri alumni per program studi.

### Gambar 4.23: Laporan Analitik Evaluasi Kinerja Lulusan oleh Atasan
* **Use Case Terkait:** `UC-21 (Melihat Laporan - Analitik Atasan)`
* **Nama Berkas:** `23_admin_grafik_evaluasi_atasan.png`
* **Path:** `docs/screenshots/23_admin_grafik_evaluasi_atasan.png`
* **Penjelasan Fungsional & Komponen Antarmuka:**
  * **Tujuan:** Menganalisis sebaran penilaian obyektif supervisor industri terhadap performa kerja lulusan.
  * **Komponen Antarmuka:**
    * *Grafik Batang Komposisi:* Distribusi persentase respon skala Likert (Sangat Kurang s.d. Sangat Baik) untuk setiap butir kompetensi.
    * *Tabel Distribusi Skor:* Statistik mean, median, dan persentase kepuasan atasan (kategori Baik dan Sangat Baik).

### Gambar 4.24: Laporan Analitik Evaluasi Pembelajaran Program Studi
* **Use Case Terkait:** `UC-21 (Melihat Laporan - Analitik Prodi)`
* **Nama Berkas:** `24_admin_grafik_prodi.png`
* **Path:** `docs/screenshots/24_admin_grafik_prodi.png`
* **Penjelasan Fungsional & Komponen Antarmuka:**
  * **Tujuan:** Menyajikan umpan balik khusus kurikulum dan fasilitas praktikum bagi ketua program studi.
  * **Komponen Antarmuka:**
    * Grafik radar/batang per prodi mengenai tingkat relevansi mata kuliah, efektivitas tugas akhir, dan kepuasan fasilitas komputer/laboratorium.

### Gambar 4.25: Audit Trail / Log Aktivitas Transaksi Data
* **Use Case Terkait:** `UC-23 (Melihat Log Aktivitas)`
* **Nama Berkas:** `25_admin_audit_activity_logs.png`
* **Path:** `docs/screenshots/25_admin_audit_activity_logs.png`
* **Penjelasan Fungsional & Komponen Antarmuka:**
  * **Tujuan:** Menjamin prinsip transparansi, akuntabilitas, dan keamanan forensik data pada aplikasi Tracer Study UKDW.
  * **Komponen Antarmuka:**
    * *Tabel Rekaman Transaksi:* Mencatat Stempel Waktu (Tanggal & Jam), Nama Aktor/Admin Pengubah, Alamat IP Pengguna, Modul Entitas yang Diubah (Alumni, Perusahaan, Pertanyaan, UMP), dan Tipe Aksi (CREATE, UPDATE, DELETE).
    * *Tampilan Komparasi Nilai Lama vs Baru (*Audit Diff*):* Kolom khusus yang menampilkan secara terstruktur atribut apa saja yang berubah sebelum dan sesudah aksi dilakukan (misal: perubahan status verifikasi perusahaan dari *unverified* menjadi *verified*).
