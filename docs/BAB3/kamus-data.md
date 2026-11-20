# Kamus Data (Data Dictionary) — Tracer Study UKDW

> Dihasilkan dari schema migration Laravel aktif.
> Semua PK bertipe `bigint unsigned AUTO_INCREMENT` kecuali yang disebutkan lain.
> **FK** = Foreign Key, **UK** = Unique Key, **AK** = Alternate Key (natural identifier), **PK** = Primary Key, **AI** = Auto Increment

---

## Modul 1: Autentikasi, Pengguna, dan Audit Log

### Tabel: `users`

> Tabel pusat akun pengguna. Siapapun yang bisa login ke sistem — mulai dari alumni yang mengisi kuesioner, admin prodi, admin fakultas, sampai superadmin — datanya ada di sini. Kolom `role` menentukan apa yang boleh dilakukan pengguna tersebut di sistem.

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | ID unik akun pengguna |
| `name` | varchar(100) | NULL | Nama pengguna / alumni |
| `username` | varchar(50) | AK, NOT NULL | Username login (NIM untuk alumni, identitas untuk staf) |
| `email` | varchar(100) | NULL | Alamat email akun login |
| `role` | varchar(30) | NOT NULL, default 'alumni' | Peran: `superadmin`, `admin_biro3`, `admin_fakultas`, `admin_prodi`, `alumni` |
| `kode_prodi` | char(2) | FK → `ref_prodi.kode_prodi`, NULL | Relasi ke prodi (admin prodi atau alumni) |
| `kode_fakultas` | char(2) | FK → `ref_fakultas.kode_fakultas`, NULL | Relasi ke fakultas (admin fakultas) |
| `must_change_password` | tinyint(1) | NOT NULL, default 0 | Penanda kewajiban ganti sandi saat pertama login |
| `password` | varchar(255) | NOT NULL | Hash kata sandi akun |
| `remember_token` | varchar(100) | NULL | Token sesi remember-me |
| `created_at`, `updated_at` | timestamp | NULL | Timestamp otomatis Laravel |

---

### Tabel: `log_activities`

> Setiap kali ada aksi penting di sistem (edit data, verifikasi perusahaan, hapus akun, dll.), rekamnya masuk ke sini. Berguna untuk audit trail — kalau ada yang berubah, bisa dilihat siapa yang melakukannya, kapan, dan datanya sebelum/sesudahnya seperti apa.

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | ID unik rekam jejak aktivitas |
| `user_id` | bigint unsigned | FK → `users.id` ON DELETE SET NULL, NULL | Pengguna pelaku aktivitas |
| `action` | varchar(100) | NOT NULL | Kode jenis aktivitas (contoh: `GANTI_PERUSAHAAN_MASTER`) |
| `model_type` | varchar(100) | NULL | Nama kelas model target aktivitas |
| `model_id` | varchar(100) | NULL | ID record model target aktivitas |
| `description` | text | NOT NULL | Uraian naratif lengkap aktivitas |
| `old_values` | json | NULL | Nilai atribut sebelum perubahan |
| `new_values` | json | NULL | Nilai atribut sesudah perubahan |
| `ip_address` | varchar(45) | NULL | Alamat IP perangkat pengakses |
| `user_agent` | text | NULL | User agent browser/aplikasi |
| `created_at`, `updated_at` | timestamp | NULL | Timestamp otomatis Laravel |

---

## Modul 2: Master Akademik, Wilayah, dan Standar UMP

### Tabel: `ref_fakultas`

> Data master fakultas-fakultas di UKDW. Tabel referensi statis — isinya tidak sering berubah, cukup diisi sekali saat setup. Menggunakan `kode_fakultas` sebagai Primary Key alami. Digunakan untuk mengelompokkan prodi dan membatasi akses admin fakultas.

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `kode_fakultas` | char(2) | PK | Kode resmi fakultas (contoh: '7', '1') |
| `nama_fakultas` | varchar(100) | NOT NULL | Nama resmi fakultas |
| `singkatan` | varchar(20) | NULL | Singkatan nama fakultas (contoh: FTI, FB) |
| `created_at`, `updated_at` | timestamp | NULL | Timestamp otomatis Laravel |

---

### Tabel: `ref_prodi`

> Data master program studi, dipasangkan dengan fakultasnya melalui `kode_fakultas`. Menggunakan `kode_prodi` sebagai Primary Key alami. Kolom `jenjang` penting untuk perhitungan IKU (Indikator Kinerja Utama) yang membedakan program S1, S2, dan lainnya.

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `kode_prodi` | char(2) | PK | Kode resmi program studi (contoh: '71', '72') |
| `nama_prodi` | varchar(100) | NOT NULL | Nama resmi program studi |
| `jenjang` | varchar(10) | NOT NULL, default 'S1' | Jenjang pendidikan untuk perhitungan IKU |
| `kode_fakultas` | char(2) | FK → `ref_fakultas.kode_fakultas`, NULL | Fakultas yang menaungi program studi |
| `created_at`, `updated_at` | timestamp | NULL | Timestamp otomatis Laravel |

---

### Tabel: `data_akademik`

> Data akademik mahasiswa yang diimpor dari Sistem Informasi Akademik (SIA) UKDW — bukan input manual pengguna. NIM menjadi Primary Key alami (natural key) tanpa kolom surrogate id. Tabel ini adalah "sumber kebenaran" identitas akademik.

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `nim` | char(8) | PK | Nomor Induk Mahasiswa (8 karakter) |
| `nama` | varchar(100) | NULL | Nama lengkap mahasiswa |
| `email_pribadi` | varchar(100) | NULL | Email pribadi dari sumber akademik |
| `kode_prodi` | char(2) | FK → `ref_prodi.kode_prodi`, NULL | Program studi akademik mahasiswa |
| `angkatan_masuk` | year | NULL | Tahun angkatan masuk perkuliahan |
| `tahun_akademik_masuk` | varchar(30) | NULL | Periode masuk (contoh: 2023/2024 Semester Ganjil) |
| `tanggal_lahir` | date | NULL | Tanggal lahir |
| `jenis_kelamin` | enum | NULL | Jenis kelamin: Laki-laki, Perempuan |
| `warga_negara` | varchar(10) | NOT NULL, default 'WNI' | Kewarganegaraan (WNI/WNA) |
| `status_mahasiswa` | enum | NOT NULL, default 'Lulus' | Status akademik: Lulus, Aktif, Cuti, Drop Out, dsb. |
| `tahun_akademik_lulus` | varchar(30) | NULL | Periode lulus (contoh: Gasal 2024/2025) |
| `tahun_lulus` | year | NULL | Tahun kelulusan resmi |
| `tanggal_lulus` | date | NULL | Tanggal resmi dinyatakan lulus |
| `ip_kumulatif` | decimal(3,2) | NULL | Indeks Prestasi Kumulatif (IPK) |
| `total_sks` | smallint | NULL | Total SKS kelulusan |
| `total_angka_kualitas` | decimal(8,2) | NULL | Total angka kualitas kelulusan |
| `created_at`, `updated_at` | timestamp | NULL | Timestamp otomatis Laravel |

---

### Tabel: `yudisium`

> Data kelulusan resmi mahasiswa yang juga berasal dari SIA. Menyimpan detail tugas akhir, publikasi ilmiah, dan predikat kelulusan. Menggunakan `nim` sebagai Primary Key dan Foreign Key ke `data_akademik.nim` — tanpa surrogate id.

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `nim` | char(8) | PK, FK → `data_akademik.nim` | NIM mahasiswa |
| `judul_ta` | text | NULL | Judul Skripsi/Thesis/Disertasi |
| `judul_ta_inggris` | text | NULL | Judul tugas akhir dalam Bahasa Inggris |
| `url_publikasi` | varchar(200) | NULL | Tautan repositori/jurnal publikasi |
| `jenis_publikasi` | varchar(100) | NULL | Kategori media publikasi ilmiah |
| `status_publikasi` | varchar(50) | NULL | Status: Published, Submitted, In Review |
| `keterangan_hasil_yudisium` | varchar(60) | NULL | Predikat yudisium: Lulus dengan Pujian, dsb. |
| `status_lulus` | enum | NOT NULL, default 'Belum' | Status kelulusan: Belum, Proses, Lulus, Tidak Lulus |
| `created_at`, `updated_at` | timestamp | NULL | Timestamp otomatis Laravel |

---

### Tabel: `propinsi`

> Tabel referensi provinsi di Indonesia berdasarkan kode BPS/Kemendagri. PK-nya langsung pakai kode provinsi (varchar), bukan surrogate id — karena kode BPS adalah standar nasional yang stabil dan langsung digunakan sebagai acuan di berbagai tabel tanpa perlu konversi.

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `kode_provinsi` | varchar(10) | PK | Kode BPS/Kemendagri provinsi (contoh: 34) |
| `nama_provinsi` | varchar(60) | NOT NULL | Nama resmi provinsi (contoh: DI Yogyakarta) |
| `created_at`, `updated_at` | timestamp | NULL | Timestamp otomatis Laravel |

---

### Tabel: `kabupaten`

> Tabel referensi kabupaten/kota, berelasi ke provinsi induknya. Strukturnya sama dengan `propinsi` — natural key sebagai PK langsung. Digunakan untuk melengkapi data domisili alumni dan lokasi perusahaan.

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `kode_kabupaten` | varchar(10) | PK | Kode BPS/Kemendagri kabupaten (contoh: 34.04) |
| `nama_kabupaten` | varchar(60) | NOT NULL | Nama resmi kabupaten/kota |
| `kode_provinsi` | varchar(10) | FK → `propinsi.kode_provinsi`, NOT NULL | Provinsi induk |
| `created_at`, `updated_at` | timestamp | NULL | Timestamp otomatis Laravel |

---

### Tabel: `ref_negara`

> Tabel referensi negara berdasarkan standar ISO-3166-1. Digunakan untuk menandai lokasi perusahaan multinasional tempat alumni bekerja. `kode_negara` (alpha-2) adalah PK, sedangkan `kode_iso3` (alpha-3) menjadi alternate key — keduanya diterbitkan oleh lembaga yang sama dan bersifat stabil.

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `kode_negara` | varchar(2) | PK | Kode negara ISO-3166-1 alpha-2 (contoh: ID, SG) |
| `kode_iso3` | varchar(3) | AK, NULL | Kode 3 huruf ISO-3166-1 alpha-3 (contoh: IDN, SGP) |
| `nama_negara` | varchar(80) | NOT NULL | Nama umum negara dalam Bahasa Indonesia |
| `nama_resmi` | varchar(120) | NULL | Nama resmi kenegaraan lengkap |
| `benua` | varchar(50) | NULL | Benua lokasi negara |
| `ibu_kota` | varchar(80) | NULL | Nama ibu kota negara |
| `created_at`, `updated_at` | timestamp | NULL | Timestamp otomatis Laravel |

---

### Tabel: `ump`

> Data Upah Minimum Provinsi (UMP) per tahun. Digunakan untuk menghitung apakah gaji alumni sudah di atas atau di bawah UMP — salah satu indikator dalam laporan IKU 2. Composite AK: satu provinsi hanya punya satu nilai UMP per tahun.

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | ID unik referensi UMP |
| `kode_provinsi` | varchar(10) | FK → `propinsi.kode_provinsi`, NOT NULL | Provinsi referensi UMP |
| `tahun` | year | NOT NULL, default 2026 | Tahun penetapan UMP — AK bersama `kode_provinsi` |
| `besaran` | decimal(15,2) | NULL | Nominal besaran rupiah UMP |
| `catatan` | varchar(150) | NULL | Dasar hukum SK Gubernur penetapan UMP |
| `created_at`, `updated_at` | timestamp | NULL | Timestamp otomatis Laravel |

---

## Modul 3: Profil Alumni, Perusahaan, Atasan, dan LinkedIn Sync

### Tabel: `perusahaan`

> Master data perusahaan/instansi tempat alumni bekerja. Perusahaan bisa diusulkan oleh alumni sendiri maupun admin prodi. Sebelum dipakai secara resmi, perusahaan perlu diverifikasi admin — statusnya dicatat di `status_verifikasi`. Mendukung perusahaan dalam negeri maupun luar negeri.

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | ID unik master perusahaan |
| `nama_perusahaan` | varchar(150) | NOT NULL | Nama resmi perusahaan/lembaga/institusi |
| `alamat` | text | NULL | Alamat lengkap kantor |
| `homepage` | varchar(150) | NULL | Website/URL homepage resmi |
| `no_telp_fax` | varchar(30) | NULL | Nomor telepon atau fax resmi |
| `kode_pos` | varchar(10) | NULL | Kode pos kantor |
| `sektor` | varchar(100) | NULL | Sektor bidang industri/usaha |
| `skala` | enum | NOT NULL, default 'Nasional' | Skala usaha: Lokal, Regional/Lokal, Nasional, Internasional |
| `bentuk_perusahaan` | varchar(60) | NULL | Bentuk instansi: BUMN, PT, Koperasi, CV, Firma |
| `jumlah_pegawai` | varchar(60) | NULL | Rentang jumlah pegawai |
| `status_verifikasi` | enum | NOT NULL, default 'Menunggu Verifikasi' | Status: Menunggu Verifikasi, Terverifikasi, Ditolak |
| `jenis_lokasi` | enum | NOT NULL, default 'Dalam Negeri' | Lokasi: Dalam Negeri, Luar Negeri |
| `kode_negara` | varchar(2) | FK → `ref_negara.kode_negara`, NOT NULL, default 'ID' | Kode ISO negara kedudukan |
| `jenis_perusahaan` | varchar(100) | NULL | Kategori Dikti F11 |
| `jenis_perusahaan_lainnya` | text | NULL | Keterangan jenis instansi jika opsi lainnya |
| `created_by_user_id` | bigint unsigned | FK → `users.id`, NULL | Pengguna yang mengusulkan data perusahaan |
| `created_by_kode_prodi` | char(2) | FK → `ref_prodi.kode_prodi`, NULL | Prodi asal pengusul perusahaan |
| `kode_provinsi` | varchar(10) | FK → `propinsi.kode_provinsi`, NULL | Provinsi lokasi perusahaan |
| `kode_kabupaten` | varchar(10) | FK → `kabupaten.kode_kabupaten`, NULL | Kabupaten lokasi perusahaan |
| `created_at`, `updated_at` | timestamp | NULL | Timestamp otomatis Laravel |

---

### Tabel: `atasan`

> Data pengguna lulusan (atasan langsung alumni) yang akan mengisi formulir evaluasi kinerja. Atasan tidak perlu mendaftar akun — mereka mengakses formulir via token khusus yang dikirim lewat email. Kolom `user_id` opsional, diisi kalau atasan kebetulan juga terdaftar sebagai pengguna sistem.

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | ID unik data atasan |
| `nama` | varchar(100) | NOT NULL | Nama lengkap atasan penilai |
| `email` | varchar(100) | NULL | Alamat email resmi atasan |
| `telepon` | varchar(20) | NULL | Nomor telepon/WhatsApp atasan |
| `perusahaan_id` | bigint unsigned | FK → `perusahaan.id`, NULL | Instansi atasan |
| `user_id` | bigint unsigned | FK → `users.id`, NULL | Akun pengguna atasan (jika terdaftar di sistem) |
| `created_at`, `updated_at` | timestamp | NULL | Timestamp otomatis Laravel |

---

### Tabel: `biodata`

> Tabel inti profil tracer study alumni. Di sinilah semua informasi karier alumni tersimpan: tempat kerja, jabatan, gaji, domisili, media sosial, sampai riwayat studi lanjut. Alumni mengisi sendiri data ini setelah login. Tabel ini menjadi penghubung antara akun (`users`), data akademik (`data_akademik`), dan seluruh informasi tracer lainnya.

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | ID unik profil biodata alumni |
| `nim` | char(8) | AK, FK → `data_akademik.nim`, NOT NULL | NIM alumni |
| `user_id` | bigint unsigned | FK → `users.id` ON DELETE CASCADE, NULL | Akun pengguna alumni |
| `kode_prodi` | char(2) | FK → `ref_prodi.kode_prodi`, NULL | Program studi alumni |
| `foto` | text | NULL | Path berkas foto profil |
| `nomor_telepon` | varchar(20) | NULL | Nomor telepon/WhatsApp aktif |
| `email_pribadi` | varchar(100) | NULL | Alamat email aktif alumni |
| `alamat_saat_ini` | text | NULL | Alamat domisili saat ini |
| `kelurahan` | varchar(50) | NULL | Kelurahan/desa domisili |
| `kecamatan` | varchar(50) | NULL | Kecamatan domisili |
| `kode_pos` | char(5) | NULL | Kode pos tempat tinggal |
| `kode_provinsi` | varchar(10) | FK → `propinsi.kode_provinsi`, NULL | Provinsi domisili |
| `kode_kabupaten` | varchar(10) | FK → `kabupaten.kode_kabupaten`, NULL | Kabupaten domisili |
| `instagram_url` | varchar(150) | NULL | Tautan profil Instagram |
| `facebook_url` | varchar(150) | NULL | Tautan profil Facebook |
| `linkedin_url` | varchar(150) | NULL | Tautan profil LinkedIn |
| `linkedin_username` | varchar(100) | NULL | Username akun LinkedIn |
| `skills` | text | NULL | Daftar keahlian/skills alumni |
| `experience` | text | NULL | Riwayat pengalaman kerja |
| `kategori_pekerjaan` | varchar(50) | NULL | Kategori karier: Bekerja, Wiraswasta, Melanjutkan Pendidikan, dsb. |
| `posisi_jabatan` | varchar(100) | NULL | Jabatan atau posisi pekerjaan |
| `bidang_pekerjaan` | varchar(150) | NULL | Bidang/rumpun pekerjaan |
| `divisi` | varchar(150) | NULL | Divisi atau unit kerja |
| `posisi_wiraswasta` | varchar(100) | NULL | Jenis usaha atau posisi wirausaha |
| `perusahaan_id` | bigint unsigned | FK → `perusahaan.id`, NULL | Perusahaan tempat bekerja |
| `atasan_id` | bigint unsigned | FK → `atasan.id`, NULL | Data atasan terkait |
| `pendidikan_tingkat` | varchar(30) | NULL | Jenjang studi lanjut: S2, S3, Profesi |
| `perguruan_tinggi` | varchar(100) | NULL | Nama perguruan tinggi studi lanjut |
| `pendidikan_prodi` | varchar(100) | NULL | Program studi pada jenjang studi lanjut |
| `gaji` | decimal(15,2) | NULL | Nominal gaji/penghasilan pertama per bulan |
| `jenis_pekerjaan` | varchar(100) | NULL | Jenis atau klasifikasi bidang pekerjaan |
| `created_at`, `updated_at` | timestamp | NULL | Timestamp otomatis Laravel |

---

### Tabel: `linkedin_sync_results`

> Menyimpan hasil scraping profil LinkedIn alumni yang dilakukan melalui Apify API. Data yang baru di-scrape statusnya `pending` — admin perlu mereview dan menyetujuinya sebelum data diperbarui ke `biodata`. Ini mencegah data LinkedIn yang salah atau tidak relevan langsung masuk ke profil alumni.

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | ID unik hasil sinkronisasi LinkedIn |
| `biodata_id` | bigint unsigned | FK → `biodata.id`, NOT NULL | Alumni pemilik profil LinkedIn |
| `linkedin_url` | varchar(150) | NOT NULL | URL profil LinkedIn yang disinkronkan |
| `linkedin_username` | varchar(100) | NULL | Username akun LinkedIn |
| `scraped_data` | json | NOT NULL | Payload data mentah hasil scraping (JSON) |
| `status` | enum | NOT NULL, default 'pending' | Status: `pending`, `approved`, `rejected` |
| `user_id` | bigint unsigned | FK → `users.id`, NULL | Administrator yang melakukan peninjauan |
| `reviewed_at` | timestamp | NULL | Waktu persetujuan peninjauan |
| `scraped_at` | timestamp | NULL | Waktu pengambilan data scraping |
| `created_at`, `updated_at` | timestamp | NULL | Timestamp otomatis Laravel |

---

## Modul 4: Engine Kuesioner Terpadu (Unified Survey Engine)

### Tabel: `kuesioner`

> Tabel induk yang merepresentasikan satu periode Tracer Study (contoh: "Tracer Study 2026"). `is_active` menentukan apakah alumni sekarang bisa mengisi kuesioner atau tidak — hanya satu yang aktif di satu waktu. Berelasi langsung ke `kuesioner_jawaban.kuesioner_id`.

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | ID unik instrumen kuesioner |
| `title` | varchar(150) | NOT NULL | Judul instrumen (contoh: Tracer Study 2026) |
| `description` | text | NULL | Deskripsi penjelasan instrumen |
| `year` | year | NOT NULL | Tahun periode pelaksanaan Tracer Study |
| `is_active` | tinyint(1) | NOT NULL, default 1 | Status keaktifan kuesioner bagi responden |
| `target_response_rate` | decimal(5,2) | NOT NULL, default 60.00 | Target capaian response rate (%) |
| `created_at`, `updated_at` | timestamp | NULL | Timestamp otomatis Laravel |

---

### Tabel: `kuesioner_section`

> Instrumen kuesioner dibagi ke dalam section-section (bagian). Ada section untuk alumni universitas, section khusus per prodi, dan section untuk atasan — dibedakan via kolom `target`. Pemisahan ini memungkinkan setiap prodi punya pertanyaan spesifik mereka sendiri, di luar pertanyaan umum universitas.

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | ID unik bagian kuesioner |
| `kuesioner_id` | bigint unsigned | FK → `kuesioner.id` ON DELETE SET NULL, NULL | ID instrumen kuesioner induk periode tracer |
| `kode_section` | varchar(50) | AK, NOT NULL | Kode unik bagian (contoh: SEC_UNIV_01, SEC_SI_01) |
| `target` | varchar(20) | NOT NULL, default 'univ' | Target: `univ`, `prodi`, `atasan` |
| `kode_prodi` | char(2) | FK → `ref_prodi.kode_prodi` ON DELETE SET NULL, NULL | Kode prodi jika section khusus satu prodi |
| `judul` | varchar(200) | NOT NULL | Judul atau nama bagian kuesioner |
| `deskripsi` | text | NULL | Deskripsi atau petunjuk pengisian |
| `order` | smallint | NOT NULL, default 1 | Nomor urut tampil bagian |
| `is_active` | tinyint(1) | NOT NULL, default 1 | Status keaktifan bagian |
| `created_at`, `updated_at` | timestamp | NULL | Timestamp otomatis Laravel |

---

### Tabel: `kuesioner_pertanyaan`

> Bank soal butir pertanyaan kuesioner yang bersifat reusable. Satu pertanyaan bisa dipakai di banyak section — misalnya pertanyaan standar DIKTI yang digunakan bersama oleh semua prodi. Prodi juga bisa membuat pertanyaan sendiri, ditandai dengan mengisi `pemilik_prodi_id`.

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | ID unik butir pertanyaan |
| `kode_pertanyaan` | varchar(50) | AK, NOT NULL | Kode unik butir (contoh: F5B, PSI-01, ATASAN-01) |
| `label_pertanyaan` | varchar(255) | NULL | Label ringkas untuk grafik dan analitik |
| `teks_pertanyaan` | text | NOT NULL | Teks redaksi pertanyaan survei |
| `tipe` | varchar(50) | NOT NULL, default 'single_choice' | Tipe input: `single_choice`, `multiple_choice`, `text`, `number`, `likert_5` |
| `pemilik_kode_prodi` | char(2) | FK → `ref_prodi.kode_prodi`, NULL | Kode prodi pembuat butir (NULL = milik universitas) |
| `is_reusable` | tinyint(1) | NOT NULL, default 1 | Status apakah butir dapat dipinjam prodi lain |
| `keterangan` | varchar(255) | NULL | Keterangan operasional pertanyaan |
| `is_active` | tinyint(1) | NOT NULL, default 1 | Status keaktifan butir |
| `created_at`, `updated_at` | timestamp | NULL | Timestamp otomatis Laravel |

---

### Tabel: `kuesioner_section_pertanyaan`

> Tabel junction yang menghubungkan section dengan pertanyaan (many-to-many). Di sini juga tersimpan logika branching — kolom `tampil_di` menentukan apakah pertanyaan ditampilkan ke semua responden atau hanya pada kondisi tertentu (contoh: hanya tampil kalau alumni memilih "Bekerja").

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | ID unik relasi penempatan pertanyaan ke bagian |
| `section_id` | bigint unsigned | FK → `kuesioner_section.id`, NOT NULL — AK bersama `pertanyaan_id` | ID bagian kuesioner |
| `pertanyaan_id` | bigint unsigned | FK → `kuesioner_pertanyaan.id`, NOT NULL — AK bersama `section_id` | ID butir pertanyaan |
| `order` | smallint | NOT NULL, default 1 | Nomor urut tampil pertanyaan di dalam section |
| `wajib` | tinyint(1) | NOT NULL, default 1 | Kewajiban pengisian (1 = wajib, 0 = opsional) |
| `is_active` | tinyint(1) | NOT NULL, default 1 | Status tampil pertanyaan pada section ini |
| `tampil_di` | varchar(100) | NULL | Logika percabangan/kondisi penampil (branching logic) |
| `created_at`, `updated_at` | timestamp | NULL | Timestamp otomatis Laravel |

---

### Tabel: `kuesioner_opsi`

> Pilihan jawaban untuk pertanyaan bertipe pilihan ganda atau skala Likert. `lompat_ke_pertanyaan_id` mendukung logika skip — misalnya "kalau memilih opsi ini, langsung lompat ke pertanyaan X" — tanpa harus ke pertanyaan berikutnya secara berurutan.

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | ID unik pilihan jawaban |
| `pertanyaan_id` | bigint unsigned | FK → `kuesioner_pertanyaan.id`, NOT NULL | ID butir pertanyaan pemilik opsi |
| `kode_opsi` | varchar(50) | AK bersama `pertanyaan_id`, NULL | Kode identifikasi opsi (contoh: F501, F502) |
| `label` | text | NOT NULL | Teks label pilihan yang terbaca responden |
| `label_singkat` | varchar(255) | NULL | Label ringkas untuk visualisasi grafik |
| `nilai_skor` | smallint | NULL | Nilai bobot numerik/skala Likert (contoh: 1–5) |
| `lompat_ke_pertanyaan_id` | varchar(50) | NULL | Kode pertanyaan target untuk percabangan jawaban |
| `order` | smallint | NOT NULL, default 1 | Nomor urut tampil pilihan jawaban |
| `created_at`, `updated_at` | timestamp | NULL | Timestamp otomatis Laravel |

---

### Tabel: `kuesioner_jawaban`

> Tempat semua jawaban tersimpan, baik dari alumni maupun atasan. Satu baris = satu jawaban untuk satu pertanyaan. Kolom `kode_pertanyaan` dan `kode_opsi` disimpan juga sebagai denormalisasi ringan untuk mempercepat query analitik tanpa harus selalu join ke tabel master pertanyaan dan opsi.

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | ID unik respon jawaban survei |
| `kuesioner_id` | bigint unsigned | FK → `kuesioner.id`, NULL | ID instrumen periode kuesioner terkait |
| `responden_type` | varchar(20) | NOT NULL, default 'alumni' | Tipe responden: `alumni`, `atasan` |
| `biodata_id` | bigint unsigned | FK → `biodata.id`, NULL | ID biodata alumni (jika responden = alumni) |
| `evaluasi_atasan_id` | bigint unsigned | FK → `evaluasi_atasan.id`, NULL | ID evaluasi atasan (jika responden = atasan) |
| `section_id` | bigint unsigned | FK → `kuesioner_section.id`, NOT NULL | ID bagian kuesioner |
| `pertanyaan_id` | bigint unsigned | FK → `kuesioner_pertanyaan.id`, NOT NULL | ID butir pertanyaan yang dijawab |
| `kode_pertanyaan` | varchar(50) | NULL | Kode butir pertanyaan (contoh: F8, PSI-1-02) |
| `opsi_id` | bigint unsigned | FK → `kuesioner_opsi.id`, NULL | ID pilihan jawaban terpilih (jika pilihan ganda) |
| `kode_opsi` | text | NULL | Kode opsi terpilih atau array JSON multi-select |
| `jawaban_teks` | text | NULL | Nilai jawaban teks bebas atau angka |
| `jawaban_json` | json | NULL | Nilai jawaban terstruktur multi-select |
| `created_at`, `updated_at` | timestamp | NULL | Timestamp otomatis Laravel |

---

## Modul 5: Evaluasi Pengguna Lulusan

### Tabel: `evaluasi_atasan`

> Sesi evaluasi kinerja alumni oleh atasan langsungnya. Setiap sesi punya token unik yang dikirim ke email atasan — token inilah yang memungkinkan atasan mengisi formulir tanpa harus punya akun di sistem. Satu alumni bisa punya lebih dari satu sesi evaluasi jika atasan atau perusahaannya berubah.

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | ID unik transaksi evaluasi atasan |
| `biodata_id` | bigint unsigned | FK → `biodata.id`, NOT NULL | Alumni yang dinilai |
| `atasan_id` | bigint unsigned | FK → `atasan.id`, NOT NULL | Atasan penilai |
| `perusahaan_id` | bigint unsigned | FK → `perusahaan.id`, NULL | Instansi penilai |
| `token` | varchar(64) | UK, NOT NULL | Token acak unik untuk akses formulir publik |
| `is_submitted` | tinyint(1) | NOT NULL, default 0 | Status apakah evaluasi sudah disubmit |
| `submitted_at` | timestamp | NULL | Waktu penyerahan respon evaluasi |
| `created_at`, `updated_at` | timestamp | NULL | Timestamp otomatis Laravel |

---

## Modul Tambahan: Pengaturan Sistem

### Tabel: `account_sync_settings`

> Tabel konfigurasi singleton — selalu hanya satu baris (id = 1). Menyimpan status buka/tutup fitur sinkronisasi akun: ketika "buka", alumni baru bisa dibuatkan akun dari data akademik yang masuk. Superadmin yang terakhir mengubah statusnya tercatat di `updated_by`.

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | ID pengaturan (selalu = 1) |
| `is_open` | boolean | NOT NULL, default true | Status buka (`true`) atau tutup (`false`) sinkronisasi akun |
| `user_id` | bigint unsigned | FK → `users.id`, NULL | Superadmin yang terakhir mengubah pengaturan |
| `created_at`, `updated_at` | timestamp | NULL | Timestamp otomatis Laravel |
