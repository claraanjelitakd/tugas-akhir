# Kamus Data (Data Dictionary) — Tracer Study UKDW

> Dihasilkan dari schema migration Laravel aktif.
> Semua PK bertipe `bigint unsigned AUTO_INCREMENT` kecuali yang disebutkan lain.
> **FK** = Foreign Key, **UK** = Unique Key, **AK** = Alternate Key (natural identifier), **PK** = Primary Key, **AI** = Auto Increment

---

## Modul 1: Autentikasi, Pengguna, dan Audit Log

### Tabel: `users`

> Menyimpan akun seluruh pengguna sistem (alumni dan administrator).

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | ID unik akun pengguna |
| `name` | varchar(100) | NULL | Nama pengguna / alumni |
| `username` | varchar(50) | AK, NOT NULL | Username login (NIM untuk alumni, identitas untuk staf) |
| `email` | varchar(100) | NULL | Alamat email akun login |
| `role` | varchar(30) | NOT NULL, default 'alumni' | Peran: `superadmin`, `admin_biro3`, `admin_fakultas`, `admin_prodi`, `alumni` |
| `prodi_id` | bigint unsigned | FK → `prodi.id`, NULL | Relasi ke prodi (admin prodi atau alumni) |
| `fakultas_id` | bigint unsigned | FK → `ref_fakultas.id`, NULL | Relasi ke fakultas (admin fakultas) |
| `must_change_password` | tinyint(1) | NOT NULL, default 0 | Penanda kewajiban ganti sandi saat pertama login |
| `password` | varchar(255) | NOT NULL | Hash kata sandi akun |
| `remember_token` | varchar(100) | NULL | Token sesi remember-me |
| `created_at`, `updated_at` | timestamp | NULL | Timestamp otomatis Laravel |

---

### Tabel: `log_activities`

> Menyimpan jejak audit aktivitas pengguna dalam sistem.

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

> Menyimpan data referensi fakultas di UKDW.

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | ID unik master fakultas |
| `kode_fakultas` | varchar(10) | AK, NOT NULL | Kode resmi fakultas (contoh: 7, 1) |
| `nama_fakultas` | varchar(100) | NOT NULL | Nama resmi fakultas |
| `singkatan` | varchar(20) | NULL | Singkatan nama fakultas (contoh: FTI, FB) |
| `created_at`, `updated_at` | timestamp | NULL | Timestamp otomatis Laravel |

---

### Tabel: `prodi`

> Menyimpan data program studi UKDW.

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | ID unik master program studi |
| `kode_prodi` | varchar(10) | AK, NOT NULL | Kode resmi program studi (contoh: 71, 72) |
| `nama_prodi` | varchar(100) | NOT NULL | Nama resmi program studi |
| `jenjang` | varchar(10) | NOT NULL, default 'S1' | Jenjang pendidikan untuk perhitungan IKU |
| `fakultas_id` | bigint unsigned | FK → `ref_fakultas.id`, NOT NULL | Fakultas yang menaungi program studi |
| `created_at`, `updated_at` | timestamp | NULL | Timestamp otomatis Laravel |

---

### Tabel: `data_akademik`

> Menyimpan data identitas akademik mahasiswa sebagai sumber resmi.

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | ID unik rekam data akademik |
| `nim` | char(8) | AK, NOT NULL | Nomor Induk Mahasiswa (8 karakter) |
| `nama` | varchar(100) | NULL | Nama lengkap mahasiswa |
| `email_pribadi` | varchar(100) | NULL | Email pribadi dari sumber akademik |
| `prodi_id` | bigint unsigned | FK → `prodi.id`, NULL | Program studi akademik mahasiswa |
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

> Menyimpan data kelulusan, judul tugas akhir, dan publikasi ilmiah mahasiswa.

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | ID unik rekam kelulusan yudisium |
| `nim` | char(8) | AK, FK → `data_akademik.nim`, NOT NULL | NIM mahasiswa |
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

> Menyimpan data referensi provinsi (kode BPS/Kemendagri).

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `kode_provinsi` | varchar(10) | PK | Kode BPS/Kemendagri provinsi (contoh: 34) |
| `nama_provinsi` | varchar(60) | NOT NULL | Nama resmi provinsi (contoh: DI Yogyakarta) |
| `created_at`, `updated_at` | timestamp | NULL | Timestamp otomatis Laravel |

---

### Tabel: `kabupaten`

> Menyimpan data referensi kabupaten/kota (kode BPS/Kemendagri).

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `kode_kabupaten` | varchar(10) | PK | Kode BPS/Kemendagri kabupaten (contoh: 34.04) |
| `nama_kabupaten` | varchar(60) | NOT NULL | Nama resmi kabupaten/kota |
| `kode_provinsi` | varchar(10) | FK → `propinsi.kode_provinsi`, NOT NULL | Provinsi induk |
| `created_at`, `updated_at` | timestamp | NULL | Timestamp otomatis Laravel |

---

### Tabel: `ref_negara`

> Menyimpan data referensi negara (kode ISO-3166-1).

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `kode_negara` | varchar(2) | PK | Kode negara ISO-3166-1 alpha-2 (contoh: ID, SG) |
| `kode_iso3` | varchar(3) | NULL | Kode 3 huruf ISO-3166-1 alpha-3 (contoh: IDN) |
| `kode_iso3` | varchar(3) | AK, NULL | Kode 3 huruf ISO-3166-1 alpha-3 (contoh: IDN) |
| `nama_negara` | varchar(80) | NOT NULL | Nama umum negara dalam Bahasa Indonesia |
| `nama_resmi` | varchar(120) | NULL | Nama resmi kenegaraan lengkap |
| `benua` | varchar(50) | NULL | Benua lokasi negara |
| `ibu_kota` | varchar(80) | NULL | Nama ibu kota negara |
| `created_at`, `updated_at` | timestamp | NULL | Timestamp otomatis Laravel |

---

### Tabel: `ump`

> Menyimpan data Upah Minimum Provinsi per tahun untuk perhitungan IKU 2.

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

> Menyimpan master data perusahaan/instansi tempat alumni bekerja.

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
| `created_by_prodi_id` | bigint unsigned | FK → `prodi.id`, NULL | Prodi asal pengusul perusahaan |
| `kode_provinsi` | varchar(10) | FK → `propinsi.kode_provinsi`, NULL | Provinsi lokasi perusahaan |
| `kode_kabupaten` | varchar(10) | FK → `kabupaten.kode_kabupaten`, NULL | Kabupaten lokasi perusahaan |
| `created_at`, `updated_at` | timestamp | NULL | Timestamp otomatis Laravel |

---

### Tabel: `atasan`

> Menyimpan data pengguna lulusan (atasan langsung alumni) yang memberikan evaluasi kinerja.

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | ID unik data atasan |
| `nama` | varchar(100) | NOT NULL | Nama lengkap atasan penilai |
| `email` | varchar(100) | NULL | Alamat email resmi atasan |
| `telepon` | varchar(20) | NULL | Nomor telepon/WhatsApp atasan |
| `perusahaan_id` | bigint unsigned | FK → `perusahaan.id`, NULL | Instansi atasan |
| `created_at`, `updated_at` | timestamp | NULL | Timestamp otomatis Laravel |

---

### Tabel: `biodata`

> Tabel sentral profil alumni mencakup kontak, domisili, karier, dan data studi lanjut.

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | ID unik profil biodata alumni |
| `nim` | char(8) | AK, FK → `data_akademik.nim`, NOT NULL | NIM alumni |
| `user_id` | bigint unsigned | FK → `users.id` ON DELETE CASCADE, NULL | Akun pengguna alumni |
| `yudisium_id` | bigint unsigned | FK → `yudisium.id`, NULL | Data yudisium terkait |
| `prodi_id` | bigint unsigned | FK → `prodi.id`, NULL | Program studi alumni |
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

> Menyimpan data staging hasil sinkronisasi profil LinkedIn alumni melalui Apify.

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | ID unik hasil sinkronisasi LinkedIn |
| `biodata_id` | bigint unsigned | FK → `biodata.id`, NOT NULL | Alumni pemilik profil LinkedIn |
| `linkedin_url` | varchar(150) | NOT NULL | URL profil LinkedIn yang disinkronkan |
| `linkedin_username` | varchar(100) | NULL | Username akun LinkedIn |
| `scraped_data` | json | NOT NULL | Payload data mentah hasil scraping (JSON) |
| `status` | enum | NOT NULL, default 'pending' | Status: `pending`, `approved`, `rejected` |
| `reviewed_by` | bigint unsigned | FK → `users.id`, NULL | Administrator yang melakukan peninjauan |
| `reviewed_at` | timestamp | NULL | Waktu persetujuan peninjauan |
| `scraped_at` | timestamp | NULL | Waktu pengambilan data scraping |
| `created_at`, `updated_at` | timestamp | NULL | Timestamp otomatis Laravel |

---

## Modul 4: Engine Kuesioner Terpadu (Unified Survey Engine)

### Tabel: `kuesioner`

> Menyimpan instrumen kuesioner induk per periode Tracer Study.

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

> Menyimpan bagian (section) instrumen survei terpadu. Kolom `target` membedakan jenis kuesioner.

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | ID unik bagian kuesioner |
| `kode_section` | varchar(50) | AK, NOT NULL | Kode unik bagian (contoh: SEC_UNIV_01, SEC_SI_01) |
| `target` | varchar(20) | NOT NULL, default 'univ' | Target: `univ`, `prodi`, `atasan` |
| `prodi_id` | bigint unsigned | FK → `prodi.id` ON DELETE SET NULL, NULL | ID prodi jika section khusus satu prodi |
| `judul` | varchar(200) | NOT NULL | Judul atau nama bagian kuesioner |
| `deskripsi` | text | NULL | Deskripsi atau petunjuk pengisian |
| `order` | smallint | NOT NULL, default 1 | Nomor urut tampil bagian |
| `is_active` | tinyint(1) | NOT NULL, default 1 | Status keaktifan bagian |
| `created_at`, `updated_at` | timestamp | NULL | Timestamp otomatis Laravel |

---

### Tabel: `kuesioner_pertanyaan`

> Menyimpan bank soal butir pertanyaan kuesioner.

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | ID unik butir pertanyaan |
| `kode_pertanyaan` | varchar(50) | AK, NOT NULL | Kode unik butir (contoh: F5B, PSI-01, ATASAN-01) |
| `label_pertanyaan` | varchar(255) | NULL | Label ringkas untuk grafik dan analitik |
| `teks_pertanyaan` | text | NOT NULL | Teks redaksi pertanyaan survei |
| `tipe` | varchar(50) | NOT NULL, default 'single_choice' | Tipe input: `single_choice`, `multiple_choice`, `text`, `number`, `likert_5` |
| `pemilik_prodi_id` | bigint unsigned | FK → `prodi.id`, NULL | ID prodi pembuat butir (NULL = milik universitas) |
| `is_reusable` | tinyint(1) | NOT NULL, default 1 | Status apakah butir dapat dipinjam prodi lain |
| `keterangan` | varchar(255) | NULL | Keterangan operasional pertanyaan |
| `is_active` | tinyint(1) | NOT NULL, default 1 | Status keaktifan butir |
| `created_at`, `updated_at` | timestamp | NULL | Timestamp otomatis Laravel |

---

### Tabel: `kuesioner_section_pertanyaan`

> Tabel junction yang menghubungkan section dengan pertanyaan (many-to-many) beserta logika percabangan.

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

> Menyimpan opsi/pilihan jawaban untuk pertanyaan bertipe pilihan.

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

> Menyimpan data jawaban responden (alumni maupun atasan).

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | ID unik respon jawaban survei |
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

> Menyimpan sesi evaluasi kinerja alumni oleh atasan.

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

> Menyimpan pengaturan status buka/tutup sinkronisasi pembuatan akun alumni. Singleton — hanya satu baris (id = 1).

| Kolom | Tipe | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | bigint unsigned | PK, AI | ID pengaturan (selalu = 1) |
| `is_open` | boolean | NOT NULL, default true | Status buka (`true`) atau tutup (`false`) sinkronisasi akun |
| `updated_by` | bigint unsigned | FK → `users.id`, NULL | Superadmin yang terakhir mengubah pengaturan |
| `created_at`, `updated_at` | timestamp | NULL | Timestamp otomatis Laravel |
