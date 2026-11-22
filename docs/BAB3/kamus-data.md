# Kamus Data (Data Dictionary) — Tracer Study UKDW

Kamus data ini mendokumentasikan spesifikasi struktur basis data aktif sistem Tracer Study UKDW yang dibangun menggunakan kerangka kerja Laravel. Setiap tabel dijelaskan berdasarkan fungsi modul, struktur atribut, tipe data, batasan integritas (*constraints*), serta deskripsi operasional kolom.

Keterangan singkatan batasan integritas (*constraints*):
- **PK**: *Primary Key* (Kunci Utama)
- **AI**: *Auto Increment* (Penomoran Otomatis)
- **FK**: *Foreign Key* (Kunci Tamu / Kunci Asing)
- **UK**: *Unique Key* (Kunci Unik)
- **AK**: *Alternate Key* (Kunci Alternatif / *Natural Identifier*)

---

## 3.1 Modul 1: Autentikasi, Pengguna, dan Audit Log

Modul ini bertanggung jawab dalam mengelola otentikasi identitas pengguna, hak akses berbasis peran (*Role-Based Access Control* / RBAC), serta pencatatan jejak audit (*audit trail*) atas setiap aktivitas modifikasi data dalam sistem.

### 3.1.1 Tabel `users`

Tabel `users` digunakan untuk menyimpan kredensial akun autentikasi seluruh entitas pengguna yang memiliki hak akses login ke dalam sistem, mencakup peran *superadmin*, *admin_biro3*, *admin_fakultas*, *admin_prodi*, dan *alumni*. Atribut `role` berfungsi mengontrol batas otorisasi pengguna, sedangkan `kode_prodi` dan `kode_fakultas` menetapkan ruang lingkup data yang dapat diakses. Rincian struktur kolom dan batasan integritas tabel `users` disajikan pada Tabel 3.1.

**Tabel 3.1 Struktur Data Tabel `users`**

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | Pengidentifikasi unik baris data akun pengguna |
| `name` | varchar(100) | NULL | Nama lengkap pengguna atau alumni |
| `username` | varchar(50) | AK, NOT NULL | Kredensial masuk sistem (NIM bagi alumni atau username khusus staf/admin) |
| `email` | varchar(100) | NULL | Alamat surat elektronik terdaftar untuk komunikasi akun |
| `role` | varchar(30) | NOT NULL, default 'alumni' | Klasifikasi peran: `superadmin`, `admin_biro3`, `admin_fakultas`, `admin_prodi`, `alumni` |
| `kode_prodi` | char(2) | FK → `ref_prodi.kode_prodi`, NULL | Program studi asal pengguna (khusus peran admin prodi dan alumni) |
| `kode_fakultas` | char(2) | FK → `ref_fakultas.kode_fakultas`, NULL | Fakultas yang menaungi pengguna (khusus peran admin fakultas) |
| `must_change_password` | tinyint(1) | NOT NULL, default 0 | Indikator kewajiban pembaruan kata sandi saat pengguna pertama kali masuk |
| `password` | varchar(255) | NOT NULL | Hasil enkripsi *hashing* kata sandi akun |
| `remember_token` | varchar(100) | NULL | Token verifikasi sesi login persisten (*remember-me*) |
| `created_at`, `updated_at` | timestamp | NULL | Waktu pembuatan dan pembaruan terakhir rekaman data |

---

### 3.1.2 Tabel `log_activities`

Tabel `log_activities` berfungsi mencatat riwayat audit (*audit log*) atas tindakan operasional penting yang dilakukan oleh pengguna, meliputi operasi perubahan data master, persetujuan integrasi data, hingga penghapusan akun. Tabel ini menyimpan status nilai data sebelum dan sesudah modifikasi dalam format JSON guna mendukung transparansi dan akuntabilitas sistem. Struktur atribut tabel `log_activities` disajikan pada Tabel 3.2.

**Tabel 3.2 Struktur Data Tabel `log_activities`**

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | Pengidentifikasi unik rekam jejak aktivitas audit |
| `user_id` | bigint unsigned | FK → `users.id` ON DELETE SET NULL, NULL | Pengguna yang melakukan aktivitas terkait |
| `action` | varchar(100) | NOT NULL | Kode pengenal klasifikasi aktivitas sistem |
| `model_type` | varchar(100) | NULL | Nama kelas model data yang menjadi target aktivitas |
| `model_id` | varchar(100) | NULL | Kunci identifikasi baris model yang menjadi target operasi |
| `description` | text | NOT NULL | Narasi deskripsi operasional tindakan yang dilakukan |
| `old_values` | json | NULL | Snapshot nilai atribut sebelum dilakukannya perubahan data |
| `new_values` | json | NULL | Snapshot nilai atribut sesudah dilakukannya perubahan data |
| `ip_address` | varchar(45) | NULL | Alamat Protokol Internet (IP) dari perangkat pengakses |
| `user_agent` | text | NULL | Informasi peramban web dan sistem operasi yang digunakan |
| `created_at`, `updated_at` | timestamp | NULL | Waktu pembuatan dan pembaruan rekaman jejak audit |

---

## 3.2 Modul 2: Master Akademik, Wilayah, dan Standar UMP

Modul ini menyimpan data referensi pokok yang digunakan sebagai acuan validasi integritas di seluruh sistem, meliputi struktur organisasi universitas (fakultas dan program studi), populasi mahasiswa resmi, riwayat kelulusan yudisium, standar wilayah administratif, serta indikator ambang Upah Minimum Provinsi (UMP).

### 3.2.1 Tabel `ref_fakultas`

Tabel `ref_fakultas` menyimpan data master fakultas di lingkungan Universitas Kristen Duta Wacana (UKDW). Tabel ini bersifat statis dan menerapkan `kode_fakultas` sebagai *natural primary key* tanpa menggunakan kunci pengganti (*surrogate key*). Entitas ini digunakan untuk mengelompokkan program studi dan mendefinisikan batasan cakupan administratif admin fakultas. Struktur data tabel `ref_fakultas` disajikan pada Tabel 3.3.

**Tabel 3.3 Struktur Data Tabel `ref_fakultas`**

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `kode_fakultas` | char(2) | PK | Kode resmi identitas fakultas (contoh: '7', '1') |
| `nama_fakultas` | varchar(100) | NOT NULL | Nama resmi fakultas di universitas |
| `singkatan` | varchar(20) | NULL | Singkatan nama fakultas (contoh: FTI, FB, FK) |
| `created_at`, `updated_at` | timestamp | NULL | Waktu pencatatan dan pembaruan rekaman data |

---

### 3.2.2 Tabel `ref_prodi`

Tabel `ref_prodi` menyimpan data master program studi yang bernaung di bawah masing-masing fakultas. Tabel ini menerapkan `kode_prodi` sebagai *primary key* alami yang berelasi dengan tabel `ref_fakultas`. Atribut `jenjang` memegang peranan krusial dalam algoritma penyaringan data analitik Indikator Kinerja Utama (khususnya IKU 1 dan IKU 2 jenjang sarjana S1). Struktur atribut tabel `ref_prodi` disajikan pada Tabel 3.4.

**Tabel 3.4 Struktur Data Tabel `ref_prodi`**

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `kode_prodi` | char(2) | PK | Kode resmi program studi (contoh: '71', '72') |
| `nama_prodi` | varchar(100) | NOT NULL | Nama resmi program studi |
| `jenjang` | varchar(10) | NOT NULL, default 'S1' | Jenjang strata pendidikan program studi |
| `kode_fakultas` | char(2) | FK → `ref_fakultas.kode_fakultas`, NULL | Fakultas penaung program studi terkait |
| `created_at`, `updated_at` | timestamp | NULL | Waktu pencatatan dan pembaruan rekaman data |

---

### 3.2.3 Tabel `data_akademik`

Tabel `data_akademik` merupakan sumber kebenaran (*single source of truth*) data kemahasiswaan yang diintegrasikan langsung dari Pangkalan Data Pendidikan Tinggi / Sistem Informasi Akademik universitas. Tabel ini menggunakan Nomor Induk Mahasiswa (`nim`) sebagai *primary key* alami tunggal. Atribut di dalamnya memuat data historis studi, capaian akademik kelulusan, dan status kemahasiswaan resmi. Rincian struktur data tabel `data_akademik` disajikan pada Tabel 3.5.

**Tabel 3.5 Struktur Data Tabel `data_akademik`**

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `nim` | char(8) | PK | Nomor Induk Mahasiswa 8 karakter sebagai pengidentifikasi unik |
| `nama` | varchar(100) | NULL | Nama lengkap mahasiswa sesuai dokumen akademik resmi |
| `email_pribadi` | varchar(100) | NULL | Alamat surat elektronik pribadi dari basis data akademik |
| `kode_prodi` | char(2) | FK → `ref_prodi.kode_prodi`, NULL | Program studi tempat mahasiswa menempuh pendidikan |
| `angkatan_masuk` | year | NULL | Tahun kalender pertama kali mahasiswa terdaftar |
| `tahun_akademik_masuk` | varchar(30) | NULL | Format semester tahun akademik masuk perkuliahan |
| `tanggal_lahir` | date | NULL | Tanggal kelahiran mahasiswa |
| `jenis_kelamin` | enum | NULL | Klasifikasi jenis kelamin: Laki-laki, Perempuan |
| `warga_negara` | varchar(10) | NOT NULL, default 'WNI' | Status kewarganegaraan mahasiswa |
| `status_mahasiswa` | enum | NOT NULL, default 'Lulus' | Status registrasi akademik (Lulus, Aktif, Cuti, dsb.) |
| `tahun_akademik_lulus` | varchar(30) | NULL | Semester dan tahun akademik penetapan kelulusan |
| `tahun_lulus` | year | NULL | Tahun kalender penetapan kelulusan resmi |
| `tanggal_lulus` | date | NULL | Tanggal resmi penetapan status kelulusan |
| `ip_kumulatif` | decimal(3,2) | NULL | Indeks Prestasi Kumulatif (IPK) akhir kelulusan |
| `total_sks` | smallint | NULL | Akumulasi beban Satuan Kredit Semester yang telah diselesaikan |
| `total_angka_kualitas` | decimal(8,2) | NULL | Nilai mutu prestasi akademik kumulatif |
| `created_at`, `updated_at` | timestamp | NULL | Waktu pencatatan dan pembaruan data akademik |

---

### 3.2.4 Tabel `yudisium`

Tabel `yudisium` menyimpan informasi kelulusan yudisium dan publikasi karya ilmiah tugas akhir/skripsi mahasiswa. Tabel ini berelasi secara *one-to-one* dengan tabel `data_akademik` menggunakan `nim` sebagai *primary key* sekaligus *foreign key*. Struktur atribut tabel `yudisium` disajikan pada Tabel 3.6.

**Tabel 3.6 Struktur Data Tabel `yudisium`**

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `nim` | char(8) | PK, FK → `data_akademik.nim` | NIM mahasiswa yang telah menempuh sidang yudisium |
| `judul_ta` | text | NULL | Judul skripsi atau tugas akhir dalam Bahasa Indonesia |
| `judul_ta_inggris` | text | NULL | Terjemahan judul tugas akhir dalam Bahasa Inggris |
| `url_publikasi` | varchar(200) | NULL | Tautan repositori karya ilmiah atau publikasi tugas akhir |
| `jenis_publikasi` | varchar(100) | NULL | Kategori luaran publikasi ilmiah kelulusan |
| `status_publikasi` | varchar(50) | NULL | Status penelaahan artikel karya ilmiah (Published, Submitted, dsb.) |
| `keterangan_hasil_yudisium` | varchar(60) | NULL | Predikat kelulusan akademik (misal: Lulus dengan Pujian) |
| `status_lulus` | enum | NOT NULL, default 'Belum' | Penetapan status kelulusan yudisium: Belum, Proses, Lulus, Tidak Lulus |
| `created_at`, `updated_at` | timestamp | NULL | Waktu pencatatan dan pembaruan penetapan yudisium |

---

### 3.2.5 Tabel `propinsi`

Tabel `propinsi` merupakan tabel referensi wilayah tingkat provinsi di Indonesia berdasarkan kode resmi Badan Pusat Statistik (BPS) dan Kementerian Dalam Negeri. Kolom `kode_provinsi` diterapkan sebagai *natural primary key* untuk mencegah inkonsistensi penamaan daerah pada formulir domisili dan profil perusahaan. Struktur tabel `propinsi` disajikan pada Tabel 3.7.

**Tabel 3.7 Struktur Data Tabel `propinsi`**

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `kode_provinsi` | varchar(10) | PK | Kode numerik wilayah provinsi standar BPS/Kemendagri |
| `nama_provinsi` | varchar(60) | NOT NULL | Nama resmi wilayah provinsi |
| `created_at`, `updated_at` | timestamp | NULL | Waktu sinkronisasi dan pencatatan data wilayah |

---

### 3.2.6 Tabel `kabupaten`

Tabel `kabupaten` memuat daftar referensi wilayah administratif kabupaten dan kota di Indonesia yang terikat pada entitas induk tabel `propinsi`. Data ini digunakan untuk memvalidasi sebaran geografis domisili alumni dan lokasi kerja instansi. Rincian struktur kolom tabel `kabupaten` disajikan pada Tabel 3.8.

**Tabel 3.8 Struktur Data Tabel `kabupaten`**

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `kode_kabupaten` | varchar(10) | PK | Kode numerik wilayah kabupaten/kota standar BPS |
| `nama_kabupaten` | varchar(60) | NOT NULL | Nama resmi entitas kabupaten atau kota |
| `kode_provinsi` | varchar(10) | FK → `propinsi.kode_provinsi`, NOT NULL | Kode provinsi yang menaungi kabupaten/kota |
| `created_at`, `updated_at` | timestamp | NULL | Waktu sinkronisasi data kabupaten |

---

### 3.2.7 Tabel `ref_negara`

Tabel `ref_negara` berisi referensi master negara internasional berbasis standar ISO 3166-1. Tabel ini digunakan untuk mendukung pelacakan tempat bekerja alumni yang berkarir di luar negeri maupun perusahaan multinasional. Struktur kolom tabel `ref_negara` disajikan pada Tabel 3.9.

**Tabel 3.9 Struktur Data Tabel `ref_negara`**

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `kode_negara` | varchar(2) | PK | Kode unik identitas negara dua huruf (ISO-3166-1 alpha-2) |
| `kode_iso3` | varchar(3) | AK, NULL | Kode pengenal negara tiga huruf (ISO-3166-1 alpha-3) |
| `nama_negara` | varchar(80) | NOT NULL | Nama umum negara dalam Bahasa Indonesia |
| `nama_resmi` | varchar(120) | NULL | Nomenklatur resmi kenegaraan lengkap |
| `benua` | varchar(50) | NULL | Wilayah benua kedudukan negara |
| `ibu_kota` | varchar(80) | NULL | Nama ibu kota negara bersangkutan |
| `created_at`, `updated_at` | timestamp | NULL | Waktu pencatatan master negara |

---

### 3.2.8 Tabel `ump`

Tabel `ump` menyimpan data besaran Upah Minimum Provinsi (UMP) per tahun penetapan Surat Keputusan Gubernur di seluruh wilayah Indonesia. Nilai UMP digunakan sebagai parameter pembanding penghasilan alumni pada indikator IKU 2 Kemendikbudristek (ambang batas $1{,}2 \times \text{UMP}$). Pasangan kolom `kode_provinsi` dan `tahun` membentuk batasan unik *composite alternate key*. Struktur kolom tabel `ump` disajikan pada Tabel 3.10.

**Tabel 3.10 Struktur Data Tabel `ump`**

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | Pengidentifikasi unik referensi baris data UMP |
| `kode_provinsi` | varchar(10) | FK → `propinsi.kode_provinsi`, NOT NULL | Kode wilayah provinsi rujukan ketetapan UMP |
| `tahun` | year | NOT NULL, default 2026 | Tahun kalender masa berlaku besaran ketetapan UMP |
| `besaran` | decimal(15,2) | NULL | Nominal besaran rupiah UMP yang berlaku |
| `catatan` | varchar(150) | NULL | Nomor dasar hukum dan keterangan ketetapan regulasi |
| `created_at`, `updated_at` | timestamp | NULL | Waktu pembaruan data ketetapan UMP |

---

## 3.3 Modul 3: Profil Alumni, Perusahaan, Atasan, dan LinkedIn Sync

Modul ini mengelola siklus data profil karir mandiri alumni, verifikasi kemitraan industri tempat bekerja, kontak evaluasi atasan langsung, serta pencatatan staging integrasi profil profesional dari jaringan LinkedIn.

### 3.3.1 Tabel `perusahaan`

Tabel `perusahaan` merupakan basis data master instansi atau perusahaan tempat alumni bekerja atau berwirausaha. Perusahaan dapat diusulkan oleh alumni maupun pengelola program studi dengan status verifikasi bertahap (`status_verifikasi`) sebelum dipromosikan menjadi data terverifikasi. Struktur kolom dan batasan integritas tabel `perusahaan` disajikan pada Tabel 3.11.

**Tabel 3.11 Struktur Data Tabel `perusahaan`**

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | Pengidentifikasi unik master entitas perusahaan |
| `nama_perusahaan` | varchar(150) | NOT NULL | Nomenklatur resmi nama lembaga atau instansi kerja |
| `alamat` | text | NULL | Alamat kantor operasional perusahaan |
| `homepage` | varchar(150) | NULL | Tautan laman resmi (*website*) perusahaan |
| `no_telp_fax` | varchar(30) | NULL | Nomor saluran komunikasi resmi perusahaan |
| `kode_pos` | varchar(10) | NULL | Kode pos domisili instansi kerja |
| `sektor` | varchar(100) | NULL | Bidang industri usaha perusahaan |
| `skala` | enum | NOT NULL, default 'Nasional' | Klasifikasi skala: Lokal, Regional/Lokal, Nasional, Internasional |
| `bentuk_perusahaan` | varchar(60) | NULL | Badan hukum instansi (BUMN, PT, CV, Yayasan, dsb.) |
| `jumlah_pegawai` | varchar(60) | NULL | Rentang perkiraan jumlah tenaga kerja instansi |
| `status_verifikasi` | enum | NOT NULL, default 'Menunggu Verifikasi' | Status audit: Menunggu Verifikasi, Terverifikasi, Ditolak |
| `jenis_lokasi` | enum | NOT NULL, default 'Dalam Negeri' | Wilayah letak operasional instansi |
| `kode_negara` | varchar(2) | FK → `ref_negara.kode_negara`, NOT NULL, default 'ID' | Kode negara letak kedudukan kantor |
| `jenis_perusahaan` | varchar(100) | NULL | Kategori instansi berdasarkan instrumen standar Dikti F11 |
| `jenis_perusahaan_lainnya` | text | NULL | Uraian khusus jika kategori instansi bernilai pilihan lainnya |
| `created_by_user_id` | bigint unsigned | FK → `users.id`, NULL | Pengguna pembuat atau pengusul entitas perusahaan |
| `created_by_kode_prodi` | char(2) | FK → `ref_prodi.kode_prodi`, NULL | Program studi asal pengusul data perusahaan |
| `kode_provinsi` | varchar(10) | FK → `propinsi.kode_provinsi`, NULL | Wilayah provinsi kedudukan instansi kerja |
| `kode_kabupaten` | varchar(10) | FK → `kabupaten.kode_kabupaten`, NULL | Wilayah kabupaten/kota kedudukan instansi kerja |
| `created_at`, `updated_at` | timestamp | NULL | Waktu pencatatan dan pembaruan data perusahaan |

---

### 3.3.2 Tabel `atasan`

Tabel `atasan` menyimpan informasi kontak pengguna lulusan (atasan langsung alumni di tempat kerja) yang ditugaskan mengisi survei kepuasan mitra industri. Entitas atasan dapat mengakses formulir evaluasi melalui tautan token publik tanpa kewajiban membuat akun login. Rincian atribut tabel `atasan` disajikan pada Tabel 3.12.

**Tabel 3.12 Struktur Data Tabel `atasan`**

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | Pengidentifikasi unik rekaman atasan penilai |
| `nama` | varchar(100) | NOT NULL | Nama lengkap pejabat atau pimpinan penilai kinerja alumni |
| `email` | varchar(100) | NULL | Surat elektronik resmi atasan untuk pengiriman instrumen evaluasi |
| `telepon` | varchar(20) | NULL | Nomor kontak telepon/WhatsApp atasan |
| `perusahaan_id` | bigint unsigned | FK → `perusahaan.id`, NULL | Instansi kerja tempat atasan bertugas |
| `user_id` | bigint unsigned | FK → `users.id`, NULL | Akun pengguna jika atasan terdaftar di sistem |
| `created_at`, `updated_at` | timestamp | NULL | Waktu pencatatan data atasan |

---

### 3.3.3 Tabel `biodata`

Tabel `biodata` merupakan tabel inti yang menyimpan atribut dinamis profil alumni, riwayat pekerjaan, klasifikasi karir (bekerja, wirausaha, studi lanjut, mencari kerja), besaran gaji, keahlian (*skills*), tautan jejaring sosial, serta relasi ke instansi kerja dan atasan. Tabel ini terikat langsung ke `data_akademik` melalui atribut `nim`. Rincian struktur data tabel `biodata` disajikan pada Tabel 3.13.

**Tabel 3.13 Struktur Data Tabel `biodata`**

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | Pengidentifikasi unik baris profil alumni |
| `nim` | char(8) | AK, FK → `data_akademik.nim`, NOT NULL | NIM alumni yang bersesuaian dengan master akademik |
| `user_id` | bigint unsigned | FK → `users.id` ON DELETE CASCADE, NULL | Akun autentikasi pengguna alumni bersangkutan |
| `kode_prodi` | char(2) | FK → `ref_prodi.kode_prodi`, NULL | Program studi kelulusan alumni |
| `foto` | text | NULL | Lokasi berkas foto profil yang diunggah |
| `nomor_telepon` | varchar(20) | NULL | Nomor kontak seluler aktif alumni |
| `email_pribadi` | varchar(100) | NULL | Surat elektronik aktif alumni saat ini |
| `alamat_saat_ini` | text | NULL | Alamat tempat tinggal domisili saat ini |
| `kelurahan` | varchar(50) | NULL | Wilayah kelurahan/desa domisili |
| `kecamatan` | varchar(50) | NULL | Wilayah kecamatan domisili |
| `kode_pos` | char(5) | NULL | Kode pos wilayah tempat tinggal |
| `kode_provinsi` | varchar(10) | FK → `propinsi.kode_provinsi`, NULL | Provinsi domisili alumni |
| `kode_kabupaten` | varchar(10) | FK → `kabupaten.kode_kabupaten`, NULL | Kabupaten/kota domisili alumni |
| `instagram_url` | varchar(150) | NULL | Tautan profil media sosial Instagram |
| `facebook_url` | varchar(150) | NULL | Tautan profil media sosial Facebook |
| `linkedin_url` | varchar(150) | NULL | Tautan halaman profil jejaring LinkedIn |
| `linkedin_username` | varchar(100) | NULL | Nama pengguna (*username*) profil LinkedIn |
| `skills` | text | NULL | Ringkasan keahlian kompetensi profesional |
| `experience` | text | NULL | Narasi rekam jejak pengalaman kerja alumni |
| `kategori_pekerjaan` | varchar(50) | NULL | Status karir: Bekerja, Wiraswasta, Studi Lanjut, Mencari Kerja |
| `posisi_jabatan` | varchar(100) | NULL | Sebutan jabatan atau profesi pada tempat kerja |
| `bidang_pekerjaan` | varchar(150) | NULL | Klasifikasi bidang atau sektor pekerjaan alumni |
| `divisi` | varchar(150) | NULL | Unit kerja atau departemen penempatan alumni |
| `posisi_wiraswasta` | varchar(100) | NULL | Peran wirausaha (Owner, Co-Founder, Freelancer, dsb.) |
| `perusahaan_id` | bigint unsigned | FK → `perusahaan.id`, NULL | Instansi tempat alumni bekerja |
| `atasan_id` | bigint unsigned | FK → `atasan.id`, NULL | Pimpinan atau atasan langsung alumni |
| `pendidikan_tingkat` | varchar(30) | NULL | Jenjang studi lanjut (S2, S3, Spesialis) |
| `perguruan_tinggi` | varchar(100) | NULL | Nama institusi pendidikan tempat studi lanjut |
| `pendidikan_prodi` | varchar(100) | NULL | Program studi yang ditempuh pada studi lanjut |
| `gaji` | decimal(15,2) | NULL | Nominal pendapatan pertama per bulan dalam rupiah |
| `jenis_pekerjaan` | varchar(100) | NULL | Pengelompokan klasifikasi profesi pekerjaan |
| `created_at`, `updated_at` | timestamp | NULL | Waktu pembuatan dan pembaruan profil biodata |

---

### 3.3.4 Tabel `linkedin_sync_results`

Tabel `linkedin_sync_results` menampung payload data profil alumni yang diperoleh melalui proses penarikan otomatis (*scraping*) menggunakan Apify Actor API. Data hasil scraping berstatus `pending` dan memerlukan persetujuan administrator sebelum disinkronkan ke tabel `biodata`. Struktur atribut tabel `linkedin_sync_results` disajikan pada Tabel 3.14.

**Tabel 3.14 Struktur Data Tabel `linkedin_sync_results`**

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | Pengidentifikasi unik transaksi sinkronisasi LinkedIn |
| `biodata_id` | bigint unsigned | FK → `biodata.id`, NOT NULL | Relasi ke profil alumni pemilik akun |
| `linkedin_url` | varchar(150) | NOT NULL | Alamat URL profil target penarikan data |
| `linkedin_username` | varchar(100) | NULL | Pengenal akun pengguna LinkedIn yang dituju |
| `scraped_data` | json | NOT NULL | Payload mentah data pengalaman dan keahlian format JSON |
| `status` | enum | NOT NULL, default 'pending' | Status verifikasi peninjauan: `pending`, `approved`, `rejected` |
| `user_id` | bigint unsigned | FK → `users.id`, NULL | Pengguna peninjau (*reviewer*) berkas integrasi |
| `reviewed_at` | timestamp | NULL | Waktu persetujuan peninjauan oleh administrator |
| `scraped_at` | timestamp | NULL | Waktu penyelesaian penarikan data oleh scraper |
| `created_at`, `updated_at` | timestamp | NULL | Waktu pembuatan dan pembaruan rekaman sinkronisasi |

---

## 3.4 Modul 4: Engine Kuesioner Terpadu (Unified Survey Engine)

Modul ini menyusun instrumen survei terpadu lintas kelompok sasaran (universitas, program studi, dan evaluasi atasan), mengelola logika percabangan pertanyaan (*branching logic*), serta mencatat respon jawaban kuesioner dari responden.

### 3.4.1 Tabel `kuesioner`

Tabel `kuesioner` berfungsi sebagai entitas master periode pelaksanaan kegiatan Tracer Study universitas. Atribut `is_active` menentukan periode instrumen yang sedang dibuka bagi responden. Rincian struktur kolom dan batasan integritas tabel `kuesioner` disajikan pada Tabel 3.15.

**Tabel 3.15 Struktur Data Tabel `kuesioner`**

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | Pengidentifikasi unik periode kuesioner induk |
| `title` | varchar(150) | NOT NULL | Judul resmi instrumen (contoh: Tracer Study UKDW 2026) |
| `description` | text | NULL | Narasi petunjuk umum instrumen survei |
| `year` | year | NOT NULL | Tahun periode pelaksanaan tracer study |
| `is_active` | tinyint(1) | NOT NULL, default 1 | Status keterbukaan pengisian kuesioner bagi responden |
| `target_response_rate` | decimal(5,2) | NOT NULL, default 60.00 | Target persentase capaian laju pengisian (*response rate*) |
| `created_at`, `updated_at` | timestamp | NULL | Waktu pembuatan dan pembaruan data instrumen kuesioner |

---

### 3.4.2 Tabel `kuesioner_section`

Tabel `kuesioner_section` membagi instrumen survei ke dalam kelompok bagian tematik. Bagian instrumen dibedakan berdasarkan atribut `target` (`univ`, `prodi`, atau `atasan`) dan terhubung ke instrumen induk melalui `kuesioner_id`. Struktur atribut tabel `kuesioner_section` disajikan pada Tabel 3.16.

**Tabel 3.16 Struktur Data Tabel `kuesioner_section`**

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | Pengidentifikasi unik seksi bagian instrumen |
| `kuesioner_id` | bigint unsigned | FK → `kuesioner.id` ON DELETE SET NULL, NULL | Periode kuesioner induk pemilik bagian survei |
| `kode_section` | varchar(50) | AK, NOT NULL | Kode pengenal bagian survei (contoh: SEC_UNIV_01, SEC_SI_01) |
| `target` | varchar(20) | NOT NULL, default 'univ' | Kelompok sasaran pengisian: `univ`, `prodi`, `atasan` |
| `kode_prodi` | char(2) | FK → `ref_prodi.kode_prodi` ON DELETE SET NULL, NULL | Relasi prodi khusus (jika bagian survei bertarget prodi) |
| `judul` | varchar(200) | NOT NULL | Nama atau tajuk bagian instrumen survei |
| `deskripsi` | text | NULL | Petunjuk teknis pengisian bagian instrumen |
| `order` | smallint | NOT NULL, default 1 | Nomor urutan penyajian bagian survei pada antarmuka |
| `is_active` | tinyint(1) | NOT NULL, default 1 | Status keaktifan kelompok seksi kuesioner |
| `created_at`, `updated_at` | timestamp | NULL | Waktu pencatatan dan pembaruan bagian survei |

---

### 3.4.3 Tabel `kuesioner_pertanyaan`

Tabel `kuesioner_pertanyaan` bertindak sebagai bank soal terpusat yang menyimpan butir pertanyaan survei yang dapat digunakan kembali (*reusable*). Tabel ini mendukung variasi masukan data mulai dari pilihan tunggal, pilihan ganda, skala Likert, teks bebas, hingga angka pendapatan. Struktur data tabel `kuesioner_pertanyaan` disajikan pada Tabel 3.17.

**Tabel 3.17 Struktur Data Tabel `kuesioner_pertanyaan`**

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | Pengidentifikasi unik butir pertanyaan bank soal |
| `kode_pertanyaan` | varchar(50) | AK, NOT NULL | Kode butir pertanyaan baku (contoh: F8, F504, PSI-01) |
| `label_pertanyaan` | varchar(255) | NULL | Label ringkas butir untuk keperluan penyajian grafik analitik |
| `teks_pertanyaan` | text | NOT NULL | Redaksi lengkap kalimat pertanyaan survei |
| `tipe` | varchar(50) | NOT NULL, default 'single_choice' | Tipe masukan input: `single_choice`, `multiple_choice`, `text`, `number`, dsb. |
| `pemilik_kode_prodi` | char(2) | FK → `ref_prodi.kode_prodi`, NULL | Pemilik butir pertanyaan (NULL menandakan milik universitas) |
| `is_reusable` | tinyint(1) | NOT NULL, default 1 | Izin peminjaman butir pertanyaan oleh prodi lain |
| `keterangan` | varchar(255) | NULL | Catatan operasional butir kuesioner |
| `is_active` | tinyint(1) | NOT NULL, default 1 | Status keaktifan butir pertanyaan dalam instrumen |
| `created_at`, `updated_at` | timestamp | NULL | Waktu pembuatan dan pembaruan rekaman butir |

---

### 3.4.4 Tabel `kuesioner_section_pertanyaan`

Tabel `kuesioner_section_pertanyaan` merupakan entitas asosiatif (*junction table*) yang menghubungkan butir pertanyaan dengan seksi bagian instrumen secara *many-to-many*. Tabel ini memuat urutan penomoran butir, indikator wajib isi, dan aturan percabangan (*branching logic*). Struktur kolom tabel `kuesioner_section_pertanyaan` disajikan pada Tabel 3.18.

**Tabel 3.18 Struktur Data Tabel `kuesioner_section_pertanyaan`**

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | Pengidentifikasi unik relasi penempatan pertanyaan pada bagian |
| `section_id` | bigint unsigned | FK → `kuesioner_section.id`, NOT NULL | Bagian seksi instrumen penampung butir |
| `pertanyaan_id` | bigint unsigned | FK → `kuesioner_pertanyaan.id`, NOT NULL | Butir pertanyaan yang disematkan ke dalam bagian |
| `order` | smallint | NOT NULL, default 1 | Urutan penomoran pertanyaan di dalam bagian instrumen |
| `wajib` | tinyint(1) | NOT NULL, default 1 | Status kewajiban pengisian (1 = Wajib, 0 = Opsional) |
| `is_active` | tinyint(1) | NOT NULL, default 1 | Status keaktifan butir pada bagian instrumen terkait |
| `tampil_di` | varchar(100) | NULL | Aturan kondisi penampil (misal: hanya tampil untuk status bekerja) |
| `created_at`, `updated_at` | timestamp | NULL | Waktu penempatan dan pembaruan pertanyaan pada bagian |

---

### 3.4.5 Tabel `kuesioner_opsi`

Tabel `kuesioner_opsi` memuat pilihan jawaban untuk butir pertanyaan bertipe pilihan tunggal, pilihan ganda, dan skala Likert. Atribut `lompat_ke_pertanyaan_id` mendukung alur percabangan cerdas di mana responden diarahkan langsung ke butir target tertentu berdasarkan opsi yang dipilihnya. Struktur tabel `kuesioner_opsi` disajikan pada Tabel 3.19.

**Tabel 3.19 Struktur Data Tabel `kuesioner_opsi`**

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | Pengidentifikasi unik baris pilihan opsi jawaban |
| `pertanyaan_id` | bigint unsigned | FK → `kuesioner_pertanyaan.id`, NOT NULL | Butir pertanyaan induk pemilik pilihan jawaban |
| `kode_opsi` | varchar(50) | NULL | Kode rujukan opsi (contoh: F501, F502) |
| `label` | text | NOT NULL | Teks redaksi pilihan jawaban yang terbaca responden |
| `label_singkat` | varchar(255) | NULL | Nomenklatur ringkas label opsi untuk visualisasi grafik |
| `nilai_skor` | smallint | NULL | Nilai bobot numerik opsi (misal: skala Likert 1 hingga 5) |
| `lompat_ke_pertanyaan_id` | varchar(50) | NULL | Kode pertanyaan tujuan lompatan percabangan survei |
| `order` | smallint | NOT NULL, default 1 | Urutan penampilan pilihan jawaban pada antarmuka |
| `created_at`, `updated_at` | timestamp | NULL | Waktu pencatatan pilihan jawaban |

---

### 3.4.6 Tabel `kuesioner_jawaban`

Tabel `kuesioner_jawaban` menampung seluruh respons jawaban yang diberikan oleh responden (alumni maupun atasan). Data tersimpan dalam bentuk skalar pada `jawaban_teks` dan terstruktur pada `jawaban_json`. Kolom `kuesioner_id` memastikan keterikatan riwayat jawaban pada periode Tracer Study yang bersangkutan. Struktur atribut tabel `kuesioner_jawaban` disajikan pada Tabel 3.20.

**Tabel 3.20 Struktur Data Tabel `kuesioner_jawaban`**

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | Pengidentifikasi unik baris respon jawaban |
| `kuesioner_id` | bigint unsigned | FK → `kuesioner.id`, NULL | Periode kuesioner induk tempat jawaban dicatat |
| `responden_type` | varchar(20) | NOT NULL, default 'alumni' | Entitas pengisi survei: `alumni` atau `atasan` |
| `biodata_id` | bigint unsigned | FK → `biodata.id`, NULL | Profil alumni penjawab (diisi jika responden adalah alumni) |
| `evaluasi_atasan_id` | bigint unsigned | FK → `evaluasi_atasan.id`, NULL | Sesi evaluasi atasan (diisi jika responden adalah atasan) |
| `section_id` | bigint unsigned | FK → `kuesioner_section.id`, NOT NULL | Bagian seksi instrumen tempat pertanyaan berada |
| `pertanyaan_id` | bigint unsigned | FK → `kuesioner_pertanyaan.id`, NOT NULL | Butir pertanyaan yang diberikan respon |
| `kode_pertanyaan` | varchar(50) | NULL | Kode butir pertanyaan (denormalisasi untuk performa analitik) |
| `opsi_id` | bigint unsigned | FK → `kuesioner_opsi.id`, NULL | Pilihan jawaban yang dipilih (pada jenis pertanyaan pilihan) |
| `kode_opsi` | text | NULL | Kode opsi terpilih atau array JSON kode opsi terpilih |
| `jawaban_teks` | text | NULL | Nilai jawaban dalam representasi teks atau angka |
| `jawaban_json` | json | NULL | Nilai jawaban terstruktur multi-opsi atau kustom |
| `created_at`, `updated_at` | timestamp | NULL | Waktu pencatatan dan penyimpanan jawaban |

---

## 3.5 Modul 5: Evaluasi Pengguna Lulusan

Modul ini memfasilitasi penilaian kinerja dan kompetensi alumni oleh pimpinan atau atasan langsung di tempat kerja.

### 3.5.1 Tabel `evaluasi_atasan`

Tabel `evaluasi_atasan` mencatat sesi transaksi evaluasi kinerja alumni oleh atasan. Kolom `token` berfungsi sebagai kunci akses kriptografis unik yang dikirimkan via surat elektronik ke alamat atasan untuk mengisi instrumen evaluasi secara aman tanpa proses otentikasi login. Rincian struktur data tabel `evaluasi_atasan` disajikan pada Tabel 3.21.

**Tabel 3.21 Struktur Data Tabel `evaluasi_atasan`**

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | Pengidentifikasi unik transaksi evaluasi atasan |
| `biodata_id` | bigint unsigned | FK → `biodata.id`, NOT NULL | Profil alumni yang menjadi objek evaluasi kinerja |
| `atasan_id` | bigint unsigned | FK → `atasan.id`, NOT NULL | Atasan langsung yang bertindak sebagai penilai |
| `perusahaan_id` | bigint unsigned | FK → `perusahaan.id`, NULL | Instansi tempat penilaian kinerja dilaksanakan |
| `token` | varchar(64) | UK, NOT NULL | Token akses unik formulir publik evaluasi |
| `is_submitted` | tinyint(1) | NOT NULL, default 0 | Status penyelesaian pengisian instrumen evaluasi |
| `submitted_at` | timestamp | NULL | Waktu penyerahan akhir respons evaluasi atasan |
| `created_at`, `updated_at` | timestamp | NULL | Waktu penerbitan sesi evaluasi kinerja |

---

## 3.6 Modul Konfigurasi Sistem: `account_sync_settings`

Modul ini menyimpan preferensi parameter global operasional aplikasi.

### 3.6.1 Tabel `account_sync_settings`

Tabel `account_sync_settings` merupakan tabel konfigurasi berbasis pola *singleton* yang hanya menyimpan satu baris pengaturan (`id = 1`). Tabel ini mengatur keterbukaan fitur sinkronisasi akun otomatis dari data mahasiswa yudisium menjadi akun login alumni. Struktur kolom tabel `account_sync_settings` disajikan pada Tabel 3.22.

**Tabel 3.22 Struktur Data Tabel `account_sync_settings`**

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | Pengidentifikasi unik baris pengaturan (selalu bernilai 1) |
| `is_open` | boolean | NOT NULL, default true | Status keterbukaan fitur sinkronisasi akun lulusan (`true`/`false`) |
| `user_id` | bigint unsigned | FK → `users.id`, NULL | Administrator yang melakukan pembaruan pengaturan terakhir |
| `created_at`, `updated_at` | timestamp | NULL | Waktu pembaruan konfigurasi sistem |
