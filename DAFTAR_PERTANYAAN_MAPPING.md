# 📋 Pemetaan Lengkap Daftar Pertanyaan & Arsitektur Form Tracer Study UKDW

Dokumen ini berisi peta komprehensif seluruh instrumen pertanyaan Tracer Study (Standar Dikti Kemendikbudristek & Kuesioner Khusus Program Studi), mencakup kode pertanyaan, tipe input, komponen Vue pengelola, tabel database, serta alur sinkronisasi otomatis dua arah antara **Profil Alumni** dan **Kuesioner Tracer Study**.

---

## 📑 Daftar Isi
1. [Struktur Alur Form & Komponen Vue](#1-struktur-alur-form--komponen-vue)
2. [Peta Pertanyaan Inti Standar Dikti (F1 s/d F18, F24)](#2-peta-pertanyaan-inti-standar-dikti-f1-sd-f18-f24)
3. [Peta Data Identitas & Biodata Alumni](#3-peta-data-identitas--biodata-alumni)
4. [Peta Kuesioner Khusus Program Studi](#4-peta-kuesioner-khusus-program-studi)
5. [Mekanisme Sinkronisasi Otomatis 2 Arah (KuesionerSyncService)](#5-mekanisme-sinkronisasi-otomatis-2-arah-kuesionersyncservice)
6. [AdminAlumniProfileService — Persistensi Terpusat oleh Admin](#6-adminalumniprofileservice--persistensi-terpusat-oleh-admin)
7. [Fitur Ekspor Kode Pertanyaan & Jawaban (Superadmin)](#7-fitur-ekspor-kode-pertanyaan--jawaban-superadmin)
8. [Peta Kuesioner Evaluasi Pengguna Lulusan (Atasan Langsung)](#8-peta-kuesioner-evaluasi-pengguna-lulusan-atasan-langsung)

---

## 1. Struktur Alur Form & Komponen Vue

Sistem Tracer Study UKDW membagi pengisian data menjadi 4 pintu utama:

| Pintu Formulir | URL Halaman | Komponen Vue Utama | Sub-Komponen Terkait |
| :--- | :--- | :--- | :--- |
| **Profil Alumni** | `/alumni/profile` | `resources/js/Pages/Alumni/Profil/Index.vue` | `FormPribadi.vue`<br>`FormKarier.vue`<br>`FormAkademik.vue`<br>`FormOrangTua.vue` |
| **Kuesioner Universitas (Dikti)** | `/alumni/kuesioner` | `resources/js/Pages/Alumni/Kuesioner.vue` | `Stepper.vue`<br>`Banner.vue`<br>`TabelF2.vue`<br>`TabelF17.vue`<br>`KartuPertanyaan.vue`<br>`Navigasi.vue` |
| **Kuesioner Khusus Prodi** | `/alumni/kuesioner-prodi` | `resources/js/Pages/Alumni/KuesionerProdi.vue` | Dynamic Form Sections per Prodi |
| **Evaluasi Atasan (Pengguna)** | `/evaluasi-atasan/{token}` | `resources/js/Pages/Public/EvaluasiAtasanForm.vue` | Header Institusi UKDW, Profil Perusahaan, Sticky Matrix Penilaian |
| **Navigasi Utama Terpadu** | *(Global)* | `resources/js/Pages/Alumni/Components/Navbar.vue` | Terintegrasi seragam di semua halaman alumni |

> **Konfigurasi Lokasi Tampil Butir Soal (Super Admin)**:
> Setiap butir pertanyaan pada instrumen kuesioner kini memiliki pengaturan kolom `tampil_di` pada tabel `ref_subpertanyaan2021` yang dapat dikonfigurasi melalui SuperAdmin:
> 1. `kuesioner`: Butir pertanyaan hanya ditampilkan saat alumni mengisi formulir kuesioner Tracer Study.
> 2. `profile`: Butir pertanyaan dikelola khusus pada formulir profil alumni (`/alumni/profile`) dan disinkronkan otomatis tanpa muncul ganda di kuesioner.
> 3. `both`: Butir pertanyaan dapat diakses dan diisi baik melalui formulir kuesioner maupun profil biodata.

> **Catatan untuk Admin**: Seluruh pembaruan profil melalui halaman admin (Super Admin, Admin Fakultas, Admin Prodi) dilayani oleh satu service terpusat: [`AdminAlumniProfileService`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Services/Alumni/AdminAlumniProfileService.php). Lihat [Bagian 6](#6-adminalumniprofileservice--persistensi-terpusat-oleh-admin) untuk detail.

---

## 2. Peta Pertanyaan Inti Standar Dikti (F1 s/d F18, F24)

Berikut adalah rincian setiap butir pertanyaan standar Dikti, peletakan komponen Vue, tipe input, dan tabel basis data tujuan:

| Kode | Pertanyaan / Topik | Tipe Input | Komponen Vue | Tabel & Kolom Database | Keterangan & Aturan Khusus |
| :---: | :--- | :--- | :--- | :--- | :--- |
| **F8** | Status situasi saat ini (Bekerja, Wiraswasta, Melanjutkan Studi, Mencari Kerja, Belum Memungkinkan Bekerja) | `radio` | `FormKarier.vue`<br>`KartuPertanyaan.vue` | `biodata.kategori_pekerjaan`<br>`tracer.answer` (F8) | Menjadi penentu logika percabangan (Jump Logic) database: Melanjutkan studi $\rightarrow$ `F18`, Belum bekerja $\rightarrow$ `F10`, Bekerja $\rightarrow$ `F3`. **Jika diubah via admin, `posisi_jabatan` / `posisi_wiraswasta` diset null secara otomatis oleh `AdminAlumniProfileService`.** |
| **F10** | Apakah aktif mencari pekerjaan dalam 4 minggu terakhir | `radio` | `KartuPertanyaan.vue` | `tracer.answer` (F10) | Pilihan Ya/Tidak. Jika Tidak $\rightarrow$ lompat ke `F3`. |
| **F18** | Pertanyaan Studi Lanjut (Header) | `header` | `KartuPertanyaan.vue` | - | Header instruksi bagi alumni yang melanjutkan pendidikan. |
| **F12** | Sumber biaya studi lanjut / pembiayaan kuliah | `radio` | `KartuPertanyaan.vue` | `tracer.answer` (F12) | 5 Opsi: Biaya Sendiri / Keluarga, Beasiswa Pemerintah, Beasiswa Swasta, Beasiswa Luar Negeri, Lainnya. |
| **F18b** | Perguruan Tinggi studi lanjut | `text` | `FormKarier.vue`<br>`KartuPertanyaan.vue` | `biodata.perguruan_tinggi`<br>`tracer.answer` (F18b) | Sinkron otomatis 2 arah dengan data Profil Alumni (`UGM`, `ITB`, dll.). |
| **F18c** | Program Studi studi lanjut | `text` | `FormKarier.vue`<br>`KartuPertanyaan.vue` | `biodata.pendidikan_prodi`<br>`tracer.answer` (F18c) | Sinkron otomatis 2 arah dengan data Profil Alumni (`Magister Manajemen`, dll.). |
| **F18d** | Tanggal Masuk studi lanjut | `date` | `KartuPertanyaan.vue` | `tracer.answer` (F18d) | Format tanggal standar HTML5 date picker (`YYYY-MM-DD`). |
| **F3** | Kapan mulai mencari pekerjaan (sebelum/setelah lulus) | `radio_input` | `KartuPertanyaan.vue` | `tracer.answer`<br>`tracer.answer_json` | Opsi input bulan sebelum/sesudah lulus. Opsi 3 (Tidak mencari kerja) $\rightarrow$ lompat ke `F504`. |
| **F4** | Bagaimana cara mencari pekerjaan | `multiple_choice` / `checkbox` | `KartuPertanyaan.vue` | `tracer.answer_json` (F4) | Multi-pilihan strategi mencari kerja (iklan, bursa kerja, relasi, dll.). |
| **F504** | Apakah telah mendapatkan pekerjaan ≤ 6 bulan | `radio` | `KartuPertanyaan.vue` | `tracer.answer` (F504) | Opsi "Ya" $\rightarrow$ `jump_to: 'F502'`, Opsi "Tidak" $\rightarrow$ `jump_to: 'F506'`. |
| **F502** | Dalam berapa bulan mendapatkan pekerjaan | `number` | `KartuPertanyaan.vue` | `tracer.answer` (F502) | Satuan unit: Bulan. Dilengkapi stepper angka `+` / `-`. |
| **F505** | Rata-rata pendapatan per bulan (Take Home Pay) | `multiple_number` | `FormKarier.vue`<br>`KartuPertanyaan.vue` | `biodata.gaji`<br>`tracer.answer_json` (F5051) | 1 Opsi tunggal: Rata-rata Pendapatan per Bulan (Take Home Pay). Batasan constraint: Minimal Rp 1.000 (kelipatan ribuan). Format ribuan otomatis. **Input gaji dari admin disanitasi oleh `AdminAlumniProfileService`: separator ribuan dibersihkan, hasil dibulatkan ke integer agar tidak berlipat ganda.** |
| **F505A**| Kesesuaian Gaji dengan UMR | `radio` | `KartuPertanyaan.vue` | `tracer.answer` (F505A) | Pilihan: Sesuai / Tidak Sesuai. `jump_to: 'F6'`. |
| **F506** | Dalam berapa bulan mendapatkan pekerjaan (pencarian > 6 bulan) | `number` | `KartuPertanyaan.vue` | `tracer.answer` (F506) | Satuan unit: Bulan. Dilengkapi stepper angka `+` / `-`. |
| **F5C** | Posisi/jabatan wiraswasta | `select`, `text` | `FormKarier.vue`<br>`KartuPertanyaan.vue` | `biodata.posisi_wiraswasta`<br>`tracer.answer` (F5C) | **Eksklusif**: Hanya diisi jika `biodata.kategori_pekerjaan = 'Wiraswasta'`. Jika alumni bukan wiraswasta, field ini di-`null`-kan otomatis oleh `AdminAlumniProfileService::updateProfile()`. |
| **F5a1** | Provinsi tempat bekerja | `text` | `KartuPertanyaan.vue` | `perusahaan.propinsi_id` → `propinsi.nama_propinsi`<br>`tracer.answer` (F5a1) | Dipetakan otomatis dari relasi `perusahaan.propinsi` melalui view `v_alumni_kuesioner_autofill`. |
| **F5a2** | Kabupaten/Kota tempat bekerja | `text` | `KartuPertanyaan.vue` | `perusahaan.kabupaten_id` → `kabupaten.nama_kabupaten`<br>`tracer.answer` (F5a2) | Dipetakan otomatis dari relasi `perusahaan.kabupaten` melalui view `v_alumni_kuesioner_autofill`. |
| **F6** | Berapa perusahaan/instansi yang sudah dilamar | `number` | `KartuPertanyaan.vue` | `tracer.answer` (F6) | Satuan unit: Perusahaan / Instansi. Grid 2 kolom berpasangan dengan F7. |
| **F7** | Berapa perusahaan/instansi yang merespons lamaran | `number` | `KartuPertanyaan.vue` | `tracer.answer` (F7) | Satuan unit: Perusahaan / Instansi. Grid 2 kolom berpasangan dengan F6. |
| **F7A** | Berapa perusahaan/instansi yang mengundang wawancara | `number` | `KartuPertanyaan.vue` | `tracer.answer` (F7A) | Satuan unit: Undangan Wawancara. |
| **F14** | Seberapa erat hubungan bidang studi dengan pekerjaan | `radio` | `KartuPertanyaan.vue` | `tracer.answer` (F14) | Skala: Sangat Erat, Erat, Cukup Erat, Kurang Erat, Tidak Sama Sekali. |
| **F15** | Tingkat pendidikan apa yang paling tepat untuk pekerjaan Anda | `radio` | `KartuPertanyaan.vue` | `tracer.answer` (F15) | Setingkat Lebih Tinggi, Tingkat yang Sama, Setingkat Lebih Rendah, Tidak Perlu PT. |
| **F16** | Alasan mengambil pekerjaan yang tidak sesuai bidang studi | `multiple_choice` | `KartuPertanyaan.vue` | `tracer.answer_json` (F16) | Pilihan majemuk alasan (prospek karier, minat, lokasi, gaji, dll.). |
| **F17** | Evaluasi Kompetensi: Saat Lulus (A) vs Diperlukan di Dunia Kerja (B) | `matrix_dual` (Tabel Komparasi) | `TabelF17.vue` | `tracer.answer` (F17a1..a7 & F17b1..b7) | 7 Aspek Kompetensi (Etika, Keahlian Bidang Ilmu, Bhs Inggris, IT, Komunikasi, Kerja Tim, Pengembangan Diri) dengan skala langsung 1 s/d 5. |
| **F2** | Penekanan metode pembelajaran selama kuliah (F21 s/d F27) | `matrix` / `table` | `TabelF2.vue` | `tracer.answer` (F21 s/d F27) | 7 Metode (Perkuliahan, Demonstrasi, Riset, Magang, Praktikum, Lapangan, Diskusi) skala 1 s/d 5. |

---

## 3. Peta Data Identitas & Biodata Alumni

Selain pertanyaan kuesioner Dikti, alumni mengisi data identitas terpadu di halaman `/alumni/profile`:

| Tab Profil | Field Data | Tipe Input | Komponen Vue | Tabel Database |
| :--- | :--- | :--- | :--- | :--- |
| **Identitas & Alamat** | Nama, NIM, NIK, NPWP (15/16 Digit UU HPP & PMK 112/2022), No HP, Email | `text`, `email`, `tel` | `FormPribadi.vue` | `biodata`, `users` |
| **Identitas & Alamat** | Alamat Domisili KTP, Provinsi, Kabupaten/Kota, Negara, Kode Pos | `select` Searchable, `text` | `FormPribadi.vue` | `biodata.provinsi_id`, `biodata.kabupaten_id`, `biodata.negara_id` |
| **Karier & Jejaring** | Kategori Peran (Pekerja, Wiraswasta, Melanjutkan Pendidikan) - *Single Choice & Auto-Reset* | `role_cards` | `FormKarier.vue` | `biodata.kategori_pekerjaan` |
| **Karier & Jejaring** | Posisi Jabatan Struktural (`F2G`) / Wiraswasta (`F5C`) | `select`, `text` | `FormKarier.vue` | `biodata.posisi_jabatan`, `biodata.posisi_wiraswasta` |
| **Karier & Jejaring** | Lokasi Perusahaan (DN/LN), Provinsi/Kabupaten atau Master Negara Dunia | `select` Searchable Modal | `FormKarier.vue` | `perusahaan.jenis_lokasi`, `perusahaan.propinsi_id`, `perusahaan.kabupaten_id`, `perusahaan.negara` |
| **Karier & Jejaring** | Take Home Pay (Gaji), Data Atasan Langsung (`F24A`, `F24B`) | `number`, `text`, `email`, `tel` | `FormKarier.vue` | `biodata.gaji`, `atasan` |
| **Akademik & Yudisium**| Program Studi, IPK, Tahun Masuk, Tahun Lulus, Tanggal Yudisium, Judul Skripsi | `text`, `readonly` | `FormAkademik.vue` | `data_akademik`, `prodi`, `yudisium` |
| **Data Orang Tua** | Nama Orang Tua, Pekerjaan, Alamat, No Telepon, Provinsi, Kabupaten | `text`, `tel`, `select` | `FormOrangTua.vue` | `data_orang_tua` |

---

## 4. Peta Kuesioner Khusus Program Studi

Kuesioner program studi dirancang modular dan independen untuk menunjang akreditasi masing-masing jurusan:

- **Halaman Form**: `resources/js/Pages/Alumni/KuesionerProdi.vue`
- **Controller**: `app/Http/Controllers/Alumni/Kuesioner/KuesionerProdiController.php`
- **Tabel Basis Data**:
  - `prodi_questionnaire_sections`: Pengelompokan bagian soal per prodi.
  - `prodi_questionnaire_questions`: Daftar pertanyaan per prodi (kode misal: `PSI-1-01`, `PSI-1-02`, dll.).
  - `prodi_questionnaire_options`: Opsi pilihan ganda untuk pertanyaan bertipe radio/select/checkbox.
  - `prodi_questionnaire_responses`: Menyimpan rekaman jawaban alumni per butir pertanyaan prodi.

---

## 5. Mekanisme Sinkronisasi Otomatis 2 Arah (KuesionerSyncService)

Sistem Tracer Study UKDW dilengkapi dengan **`KuesionerSyncService.php`** yang memastikan alumni tidak perlu mengetik ulang data yang sudah pernah diisi di profil maupun kuesioner.

```mermaid
graph LR
    A[Form Profil Alumni] <-->|KuesionerSyncService| B[Database Relasional<br/>biodata, perusahaan, atasan]
    B <-->|KuesionerSyncService| C[Instrumen Kuesioner Tracer Dikti<br/>tabel tracer]
    D[Admin Form<br/>SuperAdmin / Fakultas / Prodi] -->|AdminAlumniProfileService| B
    B -->|KuesionerSyncService dipanggil otomatis setelah save admin| C
```

### Aturan Sinkronisasi:
1. **Pembaruan Idempoten (Upsert)**:
   Saat alumni mengisi kuesioner (`/alumni/kuesioner`), jawaban disinkronkan dengan `updateOrCreate` berbasis kombinasi `nim` dan `kode_pertanyaan`. Hal ini mencegah terjadinya redudansi dan duplikasi baris data di tabel `tracer`.
2. **Pencegahan Error Atasan Unik**:
   Jika data Atasan Langsung (`nama`, `email`, `telepon`) diperbarui, backend mengecek apakah atasan dengan email tersebut sudah ada di tabel `atasan`. Jika sudah ada, sistem menghubungkan ID atasan ke `biodata.atasan_id` tanpa menabrak constraint unik.
3. **Penyelarasan Posisi Wiraswasta (F5C)**:
   Dropdown `F5C` di form karier langsung dipetakan ke kode instrumen `F5C` di tabel kuesioner. Jika `kategori_pekerjaan` bukan `Wiraswasta`, field `posisi_wiraswasta` di-null-kan secara otomatis oleh `AdminAlumniProfileService` sebelum sinkronisasi dijalankan.
4. **Autofill F5a1 & F5a2 dari View**:
   View database `v_alumni_kuesioner_autofill` menyediakan kolom `F5a1` (nama provinsi perusahaan) dan `F5a2` (nama kabupaten perusahaan) yang di-join dari tabel `propinsi` dan `kabupaten` melalui relasi `perusahaan.propinsi_id` / `perusahaan.kabupaten_id`. `KuesionerSyncService` kemudian memetakan nilai ini ke baris `tracer` dengan kode pertanyaan yang sesuai.

### Source of Truth per Kode Pertanyaan:
| Kode | Sumber Autofill (v_alumni_kuesioner_autofill) | Kolom Tujuan (tracer) |
| :---: | :--- | :--- |
| F1 | `biodata.nim` | `tracer.answer` |
| F5a1 | `propinsi.nama_propinsi` (via `perusahaan.propinsi_id`) | `tracer.answer` |
| F5a2 | `kabupaten.nama_kabupaten` (via `perusahaan.kabupaten_id`) | `tracer.answer` |
| F5C | `biodata.posisi_wiraswasta` (hanya jika kategori = Wiraswasta) | `tracer.answer` |
| F505 | `biodata.gaji` (dibulatkan ke integer) | `tracer.answer` + `answer_json` |
| F8 | `biodata.kategori_pekerjaan` | `tracer.answer` |
| F18b | `biodata.perguruan_tinggi` | `tracer.answer` |
| F18c | `biodata.pendidikan_prodi` | `tracer.answer` |
| F2G | `biodata.posisi_jabatan` | `tracer.answer` |
| F24A | `atasan.nama` | `tracer.answer` |
| F24B | `atasan.email` | `tracer.answer` |

---

## 6. AdminAlumniProfileService — Persistensi Terpusat oleh Admin

**File**: [`app/Services/Alumni/AdminAlumniProfileService.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Services/Alumni/AdminAlumniProfileService.php)

Service ini adalah **Single Source of Truth** untuk seluruh operasi baca dan tulis data profil alumni oleh administrator (Super Admin, Admin Fakultas, Admin Prodi). Sebelumnya, logika ini tersebar di 4 controller yang berbeda dengan implementasi berbeda-beda.

### Method Publik

#### `buildFormData(Biodata $alumni): array`
Menyusun array `formData` lengkap dengan 150+ field untuk dikirim ke frontend Vue. Mendukung multi-level fallback:
```
biodata → dataAkademik → yudisium → perusahaan → atasan → orangTua
```
Field yang dihasilkan mencakup:
- **Identitas Pribadi**: nim, nama, nik, npwp, no_kk, no_bpjs, nisn, agama, jenis_kelamin, golongan_darah, warga_negara, tempat_lahir, tanggal_lahir
- **Kontak & Alamat**: nomor_telepon, email, email_pribadi, alamat, kelurahan, kecamatan, kabupaten_id, propinsi_id, kode_pos
- **Akademik**: prodi_id, program_studi, fakultas, angkatan_masuk, ipk, total_sks, strata, no_ijazah
- **Yudisium**: judul_ta, dosen_pembimbing_1/2, dosen_penguji_1/2, status_yudisium, tahun_lulus, url_publikasi
- **Orang Tua**: nama_orang_tua, pekerjaan_orang_tua, alamat_orang_tua, nomor_telepon_orang_tua
- **Karier**: kategori_pekerjaan, posisi_jabatan, posisi_wiraswasta, gaji, jenis_pekerjaan, pendidikan_tingkat, perguruan_tinggi
- **Perusahaan**: nama_perusahaan, perusahaan_jenis_lokasi, perusahaan_negara, perusahaan_propinsi_id, perusahaan_kabupaten_id, skala, jenis_perusahaan
- **Atasan**: nama_atasan, email_atasan, telepon_atasan
- **Sosial Media**: instagram_url, facebook_url, linkedin_url, linkedin_username

#### `getRefOptions(): array`
Mengambil opsi dropdown referensi dari tabel `ref_subpertanyaan_detil` untuk kode pertanyaan: `F2G`, `F2H`, `F5D`, `F11`, `F8`. Digunakan oleh `FormKarier.vue` untuk mengisi pilihan posisi jabatan, jenis pekerjaan, dll.

#### `updateProfile(Biodata $biodata, array $data): void`
Menyimpan seluruh pembaruan profil secara atomik dalam 7 langkah berurutan:
1. **DataOrangTua** — `updateOrCreate` by NIM
2. **Yudisium** — `updateOrCreate` by NIM
3. **Perusahaan** — `firstOrCreate` by `nama_perusahaan`, lalu update atribut
4. **Atasan** — update jika sudah ada (by `atasan_id`), atau `create` baru
5. **Sanitasi Gaji** — strip separator ribuan + desimal, round ke integer
6. **Biodata** — `update()` dengan eksklusivitas posisi berdasarkan `kategori_pekerjaan`
7. **Sinkronisasi Kuesioner** — panggil `KuesionerSyncService::syncProfileResponses()`

### Aturan Eksklusivitas Posisi
| `kategori_pekerjaan` | `posisi_jabatan` | `posisi_wiraswasta` |
| :--- | :--- | :--- |
| `Pekerja` | ✅ Diisi dari input | `null` (dihapus) |
| `Wiraswasta` | `null` (dihapus) | ✅ Diisi dari input |
| `Melanjutkan Pendidikan` | `null` (dihapus) | `null` (dihapus) |

### Controller yang Menggunakan Service Ini
| Controller | Role | Aksi |
| :--- | :--- | :--- |
| [`DetailAlumniSuperAdminController`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/SuperAdmin/KelolaAlumni/DetailAlumniSuperAdminController.php) | Super Admin | `show()`, `updateProfile()` |
| [`DetailAlumniController`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/AdminBiroTiga/KelolaAlumni/DetailAlumniController.php) | Biro 3 | `tampilkanDetailAlumni()`; `updateProfile()` menolak perubahan |
| [`DetailAlumniFakultasController`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/AdminFakultas/KelolaAlumni/DetailAlumniFakultasController.php) | Admin Fakultas | `show()`, `updateProfile()` |
| [`DaftarAlumniProdiController`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/AdminProdi/KelolaAlumni/DaftarAlumniProdiController.php) | Admin Prodi | `show()`, `updateProfile()` |

---

## 7. Fitur Ekspor Kode Pertanyaan & Jawaban (Superadmin)

Bagi Superadmin dan Admin Program Studi, tersedia modul rekapitulasi data:
- **Format Ekspor**: Microsoft Excel (`.xls`) berformat HTML kaya gaya.
- **Service Backend**: [`AlumniTracerExcelExporter`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Services/Export/AlumniTracerExcelExporter.php) — digunakan seragam oleh seluruh stakeholder admin.
- **Isi Ekspor**:
  - **Blok Identitas Lengkap (4 Sub-Bagian)**:
    1. Data Diri & Kontak Pribadi (NIM, NIK, NPWP, No KK, No BPJS, NISN, Telepon, Email, LinkedIn, dll.)
    2. Data Akademik & Kelulusan (Prodi, Fakultas, IPK, SKS, Tahun Lulus, Asal Sekolah, dll.)
    3. Data Orang Tua / Wali (Nama, Pekerjaan, Alamat, Telepon)
    4. Data Karier, Perusahaan & Atasan (Kategori pekerjaan, Gaji, Perusahaan, Skala, Lokasi, Atasan)
  - **Tabel Kuesioner Universitas**: Semua butir pertanyaan Dikti dengan kolom Kode, Pertanyaan, Tipe, Sifat (Wajib/Opsional), Status (`v`/`x`), dan Jawaban Alumni.
  - **Tabel Kuesioner Prodi**: Butir pertanyaan prodi dengan format sama.
- **Proteksi Format Numerik**: Semua cell data sensitif (NIK, NIM, NPWP, No KK, No BPJS, Telepon) dilindungi dengan `style="mso-number-format:'\\@';"` untuk mencegah Excel mengonversi ke notasi ilmiah eksponensial.
- **Multi-Column Fallback Jawaban**: Eksporter membaca jawaban dari `answer_text` terlebih dahulu, jika kosong fallback ke `answer`, jika keduanya kosong coba `answer_json`.

---

## 8. Peta Kuesioner Evaluasi Pengguna Lulusan (Atasan Langsung)

Kuesioner evaluasi atasan adalah instrumen resmi Universitas Kristen Duta Wacana untuk mengukur tingkat kepuasan mitra industri/pimpinan kerja terhadap kompetensi dan kinerja alumni yang bekerja di institusi mereka.

- **Akses & Autentikasi**: Publik melalui URL token unik tanpa login: `/evaluasi-atasan/{token}`
- **Pemicu Notifikasi**: Terkirim otomatis via email undangan berformat resmi institusi ke email atasan saat alumni menyimpan data profil karier (`FormKarier.vue`).
- **Komponen Vue**: [`resources/js/Pages/Public/EvaluasiAtasanForm.vue`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/resources/js/Pages/Public/EvaluasiAtasanForm.vue) (Full-page layout korporat dengan sticky table header).
- **Controller Backend**: [`app/Http/Controllers/EvaluasiAtasanController.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Http/Controllers/EvaluasiAtasanController.php).

### A. Bagian I: Informasi Profil Perusahaan / Lembaga / Institusi

Bagian ini dinormalisasi ke tabel master `perusahaan`, `atasan`, dan transaksi `evaluasi_atasan`:

| Butir Pertanyaan / Field | Tipe Input | Pilihan / Format | Tabel & Kolom Tujuan |
| :--- | :--- | :--- | :--- |
| **Nama Perusahaan / Lembaga** | `text` | Sesuai nama entitas | `perusahaan.nama_perusahaan` |
| **Alamat Lengkap Perusahaan** | `textarea` | Alamat domisili kantor | `perusahaan.alamat` |
| **Nomor Telepon / Fax** | `tel` / `text` | Nomor resmi instansi | `perusahaan.no_telp_fax` |
| **Homepage / Website** | `url` / `text` | Alamat web resmi | `perusahaan.homepage` |
| **Bentuk Perusahaan / Institusi** | `select` | 1. BUMN<br>2. Perusahaan Terbatas<br>3. Koperasi<br>4. CV<br>5. Firma | `perusahaan.bentuk_perusahaan` |
| **Skala Perusahaan** | `select` | 1. Lokal<br>2. Nasional<br>3. Internasional | `perusahaan.skala` |
| **Jumlah Pegawai Keseluruhan** | `select` | 1. < 50 Orang<br>2. 51 - 100 Orang<br>3. 101 – 150 Orang<br>4. 151 – 300 Orang<br>5. 301 – 500 Orang<br>6. > 500 Orang | `perusahaan.jumlah_pegawai` |
| **Nama Pimpinan / Kontak Person** | `text` | Nama atasan penilai | `atasan.nama` |
| **Email Pimpinan / Kontak Person** | `email` | Email aktif atasan | `atasan.email` |
| **Nomor Telepon Pimpinan** | `tel` | Nomor HP/telepon kerja atasan | `atasan.telepon` |
| **Jumlah Alumni UKDW di Perusahaan** | `select` | 1. < 5 Orang<br>2. 6 - 10 Orang<br>3. 11 – 20 Orang<br>4. > 21 Orang | `evaluasi_atasan.jumlah_alumni_ukdw` |
| **Standar Gaji Pertama Alumni UKDW** | `select` | 1. < 1.000.000<br>2. 1.000.000 – 1.500.000<br>3. 1.500.000 – 2.000.000<br>4. 2.000.000 – 3.000.000<br>5. 3.000.000 – 4.000.000<br>6. > 5.000.000 | `evaluasi_atasan.standar_gaji_pertama` |

---

### B. Bagian II: Informasi Penilaian Kinerja Lulusan (Alumni) UKDW

Instrumen kuesioner dinamis tersimpan pada tabel `pertanyaan_evaluasi_atasan` dan jawaban disimpan pada `respon_evaluasi_atasan`:

| No | Kode Aspek | Topik / Butir Pertanyaan | Tipe Input & Pilihan | Kolom Jawaban Database |
| :---: | :---: | :--- | :--- | :--- |
| **1** | `KES_1` | **Tingkat Kesiapan Alumni dalam Bekerja**: Seberapa siap alumni UKDW saat pertama kali mulai bekerja di perusahaan Anda? | `pilihan_ganda`<br>• Sangat siap<br>• Cukup Siap<br>• Kurang siap<br>• Tidak siap | `respon_evaluasi_atasan.jawaban_teks` |
| **2** | `ASP_01` | **Etika, Moral, dan Integritas**: Kejujuran, kedisiplinan, dan ketaatan terhadap norma etika kerja | `likert_5` (1: Sangat Kurang s/d 5: Sangat Baik) | `respon_evaluasi_atasan.nilai` |
| **3** | `ASP_02` | **Keahlian Berdasarkan Bidang Ilmu**: Penguasaan keilmuan dan kemampuan teknis sesuai program studi kelulusan | `likert_5` (1 s/d 5) | `respon_evaluasi_atasan.nilai` |
| **4** | `ASP_03` | **Kemampuan Berbahasa Inggris**: Kemampuan komunikasi lisan dan tulisan dalam bahasa Inggris kerja | `likert_5` (1 s/d 5) | `respon_evaluasi_atasan.nilai` |
| **5** | `ASP_04` | **Penggunaan Teknologi Informasi**: Keterampilan mengoperasikan perangkat lunak, sistem, dan alat bantu digital | `likert_5` (1 s/d 5) | `respon_evaluasi_atasan.nilai` |
| **6** | `ASP_05` | **Kemampuan Berkomunikasi**: Kejelasan menyampaikan ide, presentasi, dan koordinasi interpersonal | `likert_5` (1 s/d 5) | `respon_evaluasi_atasan.nilai` |
| **7** | `ASP_06` | **Kerja Sama dalam Tim**: Kolaborasi, empati, dan kontribusi aktif dalam kelompok kerja/divisi | `likert_5` (1 s/d 5) | `respon_evaluasi_atasan.nilai` |
| **8** | `ASP_07` | **Pengembangan Diri dan Kemauan Belajar**: Keinginan belajar hal baru dan inisiatif meningkatkan kapabilitas | `likert_5` (1 s/d 5) | `respon_evaluasi_atasan.nilai` |
| **9** | `ASP_08` | **Kepemimpinan (Leadership)**: Kemampuan memimpin proyek, membimbing rekan, dan mengambil keputusan | `likert_5` (1 s/d 5) | `respon_evaluasi_atasan.nilai` |
| **10** | `ASP_09` | **Etos Kerja dan Kedisiplinan**: Ketepatan waktu, dedikasi tinggi, dan konsistensi terhadap target perusahaan | `likert_5` (1 s/d 5) | `respon_evaluasi_atasan.nilai` |
| **11** | `ASP_10` | **Tanggung Jawab dalam Menyelesaikan Tugas**: Komitmen dan ketuntasan atas pekerjaan yang diberikan | `likert_5` (1 s/d 5) | `respon_evaluasi_atasan.nilai` |
| **12** | `ASP_11` | **Kemampuan Beradaptasi dengan Lingkungan Kerja**: Fleksibilitas terhadap budaya perusahaan dan dinamika organisasi | `likert_5` (1 s/d 5) | `respon_evaluasi_atasan.nilai` |
| **13** | `ASP_12` | **Inisiatif dan Kreativitas Kerja**: Daya cipta alternatif solusi inovatif terhadap permasalahan kerja | `likert_5` (1 s/d 5) | `respon_evaluasi_atasan.nilai` |
| **14** | `CAT_01` | **Saran Khusus / Catatan Tambahan**: Masukan konstruktif untuk pengembangan kurikulum dan peningkatan mutu lulusan UKDW | `textarea` (Teks Bebas) | `respon_evaluasi_atasan.catatan` |


Revisi 21 Oktober 2026: komponen formulir bersama berada di `resources/js/components/AlumniProfil`, komponen audit berada di `resources/js/components/AlumniAudit`. Biro III memakai pembacaan profil bersama tetapi tidak menyimpan pembaruan profil. Pengiriman undangan evaluasi terpisah dari simpan profil; token yang dihasilkan 40 karakter. Pemetaan aliran data tersedia pada [DFD](docs/dfd.md).
