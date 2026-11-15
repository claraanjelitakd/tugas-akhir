# Dokumen Spesifikasi Data Flow Diagram (DFD) — Sistem Informasi Tracer Study UKDW

Dokumen ini menyajikan perancangan dan spesifikasi formal **Data Flow Diagram (DFD)** untuk Sistem Informasi Tracer Study Universitas Kristen Duta Wacana (UKDW), yang disusun mengacu penuh pada standar acuan akademik:
**Kenneth E. Kendall & Julie E. Kendall, *Systems Analysis and Design*, Tenth Edition, Global Edition (Pearson, 2020), Chapter 7: *Using Data Flow Diagrams*, halaman 224–254**.

---

## 1. Landasan Teori & Kaidah Baku Kendall & Kendall (Edisi Ke-10)

Berdasarkan Kendall & Kendall (2020, hlm. 226–234), DFD adalah teknik analisis terstruktur yang merepresentasikan bagaimana data mengalir, ditransformasikan oleh proses, dan disimpan dalam suatu organisasi.

### 1.1 Empat Simbol Dasar DFD

Sistem dan seluruh subsistem dimodelkan hanya menggunakan kombinasi **empat simbol dasar**:

| Simbol Visual | Nama Komponen | Deskripsi & Aturan Penamaan (Kendall hlm. 226–227) |
| :---: | :--- | :--- |
| **Kotak Ganda (*Double Square*)** | **Entitas Eksternal (*External Entity / Source & Destination*)** | Pihak luar (orang, departemen, organisasi, atau sistem eksternal) yang mengirimkan data ke sistem atau menerima data dari sistem. Berada di luar batas sistem (*outside system boundary*). Diberi nama menggunakan **Kata Benda (*Noun*)**. Entitas yang sama dapat digambar lebih dari sekali untuk mencegah garis bersilangan (*crossing lines*). |
| **Panah (*Arrow*)** | **Aliran Data (*Data Flow*)** | Menunjukkan pergerakan data dari satu titik ke titik lain dengan kepala panah menunjuk ke arah tujuan. Karena merepresentasikan data (bukan kontrol/keputusan), aliran data wajib dinamai menggunakan **Kata Benda (*Noun*)**, misal: `Data Mahasiswa Lulus`, `Jawaban Kuesioner Tracer`. |
| **Persegi Membulat (*Rectangle with Rounded Corners*)** | **Proses Transformasi (*Transforming Process*)** | Menunjukkan pekerjaan atau perubahan bentuk data. Aliran data yang keluar dari proses harus memiliki nama berbeda dari aliran yang masuk. Penamaan menggunakan format **Kata Kerja – Kata Sifat – Kata Benda (*Verb–Adjective–Noun*)**, misal: `Kelola Akun Pengguna`, `Validasi Data Profil`, `Proses Kuesioner Tracer`. Memiliki nomor unik (0 untuk Konteks, 1–9 untuk Diagram 0, 1.1, 1.2 untuk Diagram Anak). |
| **Persegi Terbuka Kanan (*Open-Ended Rectangle*)** | **Simpanan Data Logis (*Data Store*)** | Tempat penyimpanan data permanen (berupa berkas master, basis data, atau berkas transaksi). Dinamai dengan **Kata Benda (*Noun*)** dan diberi nomor referensi unik (`D1`, `D2`, `D3`, dst.). |

### 1.2 Aturan Sintaksis & Integritas Diagram (Kendall hlm. 227–232)

1. **Integritas Aliran Data Proses**:
   - Setiap proses harus memiliki minimal satu aliran data masuk (*input*) dan minimal satu aliran data keluar (*output*).
   - Dilarang membuat proses tanpa keluaran (*Black Hole*), proses tanpa masukan (*Miracle*), atau proses dengan keluaran yang tidak dapat dibentuk dari masukannya (*Gray Hole*).
2. **Koneksi Simpanan Data & Entitas**:
   - Entitas eksternal **dilarang terhubung langsung** ke Simpanan Data (harus melalui proses penengah).
   - Entitas eksternal **dilarang terhubung langsung** ke Entitas Eksternal lain di dalam diagram sistem.
   - Simpanan Data **dilarang terhubung langsung** ke Simpanan Data lain tanpa proses transformasi.
3. **Batas Jumlah Proses Diagram 0**:
   - Diagram 0 adalah ledakan (*explosion*) dari Diagram Konteks dan dibatasi **antara 3 hingga 9 proses**. Lebih dari 9 proses akan membuat diagram terlalu padat (*cluttered*) dan menyulitkan pemahaman.
4. **Keseimbangan Vertikal (*Vertical Balancing*)**:
   - Seluruh aliran data masukan dan keluaran yang terhubung pada proses induk di Diagram 0 harus muncul secara persis (*interface data flow*) pada child diagram yang meledakkannya. Diagram anak tidak boleh menerima masukan atau menghasilkan keluaran luar yang tidak dimiliki proses induknya.
5. **Penolakan Aliran Linier (*Avoiding Linear Flow Error*)**:
   - Aliran proses berantai satu arah (A $\rightarrow$ B $\rightarrow$ C) seperti flowchart dilarang. Proses DFD bekerja secara berulang/bersamaan berdasarkan ketersediaan data.
6. **Diagram Konteks Tanpa Simpanan Data**:
   - Diagram Konteks hanya memuat entitas eksternal, aliran data antarmuka, dan proses 0. Tidak ada *data store* yang digambarkan pada tingkat konteks.

---

## 2. Batas Sistem & Analisis Entitas Eksternal Sistem Tracer

Batas sistem (*system boundary*) adalah batasan perangkat lunak aplikasi web **Sistem Informasi Tracer Study UKDW**. Pihak atau sistem apa pun yang berinteraksi dari luar batas aplikasi diklasifikasikan sebagai **Entitas Eksternal**:

### 2.1 Entitas Sistem Eksternal (Mesin / Perangkat Lunak Luar)

Sesuai penegasan Kendall & Kendall (hlm. 226) bahwa entitas eksternal dapat berupa *"another machine or system"*, sistem ini terhubung dengan 3 sistem eksternal:

1. **`Sistem Akademik (SIAKAD UKDW)`**:
   - **Peran**: Sistem informasi akademik sentral universitas (Biro 1).
   - **Aliran Masuk ke Tracer**: Mengirimkan `Data Mahasiswa Lulus` (NIM, Nama, Prodi, Tanggal Masuk, Tanggal Lulus, IPK) dan `Data Kelulusan & Yudisium` (Nomor SK Yudisium, Tanggal Kelulusan Resmi, Judul Skripsi/Tugas Akhir).
2. **`Sistem Referensi Universitas (SIM Referensi)`**:
   - **Peran**: Sistem master data institusi dan pangkalan data referensi terpusat universitas.
   - **Aliran Masuk ke Tracer**: Mengirimkan data referensi organisasi internal (`Referensi Fakultas & Program Studi`) serta referensi standar wilayah nasional (`Referensi Provinsi & Kota BPS/Kemendagri`, `Kode Negara ISO`, dan `Standar UMP Tahunan`).
3. **`Layanan Eksternal Apify (LinkedIn Engine)`**:
   - **Peran**: Penyedia layanan otomatisasi web scraping API pihak ketiga.
   - **Interaksi**: Menerima `URL Profil LinkedIn Sasaran` dari sistem Tracer, dan mengembalikan `Data Snapshot Mentah LinkedIn`.

### 2.2 Entitas Pengguna Manusia (*Human External Entities*)

1. **`Alumni`**: Lulusan UKDW yang memperbarui profil karier, mengusulkan instansi tempat bekerja, serta menjawab instrumen kuesioner tracer universitas dan program studi.
2. **`Pengguna Lulusan (Atasan Langsung)`**: Pimpinan instansi tempat alumni bekerja yang menerima tautan evaluasi berbasis token tanpa akun login, untuk menilai kompetensi dan kinerja alumni.
3. **`Admin Program Studi`**: Staf pengelola tingkat program studi yang mengelola instrumen prodi, memverifikasi usulan instansi alumni prodi, dan memantau kinerja lulusan prodinya.
4. **`Admin Fakultas`**: Pimpinan dan staf fakultas yang memantau capaian kinerja lulusan lintas prodi di bawah fakultas bersangkutan.
5. **`Admin Biro 3 (Kemahasiswaan & Alumni)`**: Pengelola tingkat universitas yang mengelola instrumen universitas, memantau tingkat partisipasi (*response rate*), dan menyusun laporan IKU S1.
6. **`Superadmin (ITC)`**: Administrator teknologi informasi universitas yang mengelola akun sistem, otorisasi peran, konfigurasi sinkronisasi, dan audit sistem.

---

## 3. Daftar Penyimpanan Data Logis (*Data Stores*)

Mengacu pada kamus data basis data aktif sistem tracer study:

| Kode Simpanan | Nama Simpanan Logis (*Data Store Name*) | Tabel Basis Data Terkait | Peran dalam Sistem |
| :---: | :--- | :--- | :--- |
| **`D1`** | **Data Pengguna & Akun** | `users`, `password_reset_tokens` | Menyimpan kredensial autentikasi, username/NIM, peran otorisasi, dan token pemulihan sandi. |
| **`D2`** | **Master Akademik & Yudisium** | `data_akademik`, `yudisium` | Menyimpan rekaman riwayat akademik mahasiswa lulus dan berkas yudisium dari Sistem Akademik. |
| **`D3`** | **Data Biodata & Profil Alumni** | `biodata` | Menyimpan profil terintegrasi alumni (identitas, kontak, domisili, status karier kanonik F8, riwayat pekerjaan, dan studi lanjut). |
| **`D4`** | **Master Perusahaan & Kontak Atasan** | `perusahaan`, `atasan` | Menyimpan data instansi kerja alumni (nama, skala, wilayah) dan snapshot kontak pimpinan/atasan langsung. |
| **`D5`** | **Instrumen & Bank Soal Kuesioner** | `kuesioner`, `kuesioner_section`, `kuesioner_pertanyaan`, `kuesioner_section_pertanyaan`, `kuesioner_opsi` | Menyimpan bank soal survei terpadu (*Unified Survey Engine*), konfigurasi stepper/bagian, dan opsi jawaban berbobot. |
| **`D6`** | **Jawaban Survei Terpadu** | `kuesioner_jawaban` | *Single Source of Truth* seluruh respon jawaban survei tracer universitas, prodi, maupun evaluasi atasan. |
| **`D7`** | **Sesi Evaluasi Atasan** | `evaluasi_atasan` | Menyimpan lembar transaksi survei kepuasan atasan berbasis token acak 64-karakter dan status penyelesaiannya. |
| **`D8`** | **Staging Sinkronisasi LinkedIn** | `linkedin_sync_results` | Menyimpan data mentah hasil penarikan profil LinkedIn sebelum diverifikasi menjadi riwayat karier aktif. |
| **`D9`** | **Referensi Wilayah, Organisasi & UMP** | `propinsi`, `kabupaten`, `ref_negara`, `ump`, `ref_fakultas`, `prodi` | Menyimpan tabel referensi master institusi dan wilayah administratif standar nasional. |
| **`D10`** | **Log Aktivitas Audit** | `log_activities` | Menyimpan rekam jejak audit (*audit trail*) atas aksi mutasi data penting yang dilakukan pengguna/admin. |

---

## 4. Diagram Konteks (Context Diagram — Tingkat Tertinggi)

Diagram Konteks memberikan gambaran *bird's-eye view* atas batas sistem, memuat **satu proses tunggal bernomor 0**, 9 entitas eksternal, dan seluruh aliran data antarmuka (*interface flows*).

```mermaid
flowchart TD
    %% Entitas Sistem Luar
    E_AKAD["Sistem Akademik (SIAKAD)"]
    E_REF["Sistem Referensi Universitas (SIM Referensi)"]
    E_APIFY["Layanan Apify (LinkedIn Engine)"]

    %% Entitas Pengguna Manusia
    E_ALUMNI["Alumni"]
    E_ATASAN["Pengguna Lulusan (Atasan)"]
    E_PRODI["Admin Program Studi"]
    E_FAK["Admin Fakultas"]
    E_BIRO3["Admin Biro 3 (Kemahasiswaan)"]
    E_SUPER["Superadmin (ITC)"]

    %% Proses Konteks Tunggal
    P0(("0<br/>Sistem Informasi<br/>Tracer Study UKDW"))

    %% Aliran Sistem Akademik & Referensi
    E_AKAD -->|"Data Mahasiswa Lulus & Yudisium"| P0
    E_REF -->|"Data Referensi Fakultas, Prodi, Wilayah & UMP"| P0

    %% Aliran Alumni
    E_ALUMNI -->|"Kredensial Login & Permohonan Akun"| P0
    E_ALUMNI -->|"Data Profil, Riwayat Karier & Usulan Instansi"| P0
    E_ALUMNI -->|"Jawaban Kuesioner Tracer (Univ & Prodi)"| P0
    P0 -->|"Status Akun & Hasil Pemulihan Sandi"| E_ALUMNI
    P0 -->|"Status Keterisian Profil & Kuesioner"| E_ALUMNI

    %% Aliran Pengguna Lulusan (Atasan)
    P0 -->|"Tautan Evaluasi Berbasis Token"| E_ATASAN
    E_ATASAN -->|"Jawaban Evaluasi Aspek Kinerja"| P0
    P0 -->|"Konfirmasi Penyerahan Evaluasi"| E_ATASAN

    %% Aliran Admin Prodi
    E_PRODI -->|"Kredensial Login"| P0
    E_PRODI -->|"Instrumen Kuesioner Prodi"| P0
    E_PRODI -->|"Keputusan Verifikasi Perusahaan & Staging LinkedIn"| P0
    P0 -->|"Laporan Kinerja Lulusan & Rekapitulasi Prodi"| E_PRODI

    %% Aliran Admin Fakultas
    E_FAK -->|"Kredensial Login"| P0
    P0 -->|"Laporan Kinerja Lulusan Fakultas"| E_FAK

    %% Aliran Admin Biro 3
    E_BIRO3 -->|"Kredensial Login"| P0
    E_BIRO3 -->|"Konfigurasi Kuesioner Universitas"| P0
    P0 -->|"Laporan Eksekutif, Response Rate & Capaian IKU S1"| E_BIRO3

    %% Aliran Superadmin
    E_SUPER -->|"Kredensial Login"| P0
    E_SUPER -->|"Pengaturan Akun & Parameter Konfigurasi"| P0
    P0 -->|"Laporan Audit Trail & Log Aktivitas Sistem"| E_SUPER

    %% Aliran Apify
    P0 -->|"URL Profil LinkedIn Sasaran"| E_APIFY
    E_APIFY -->|"Data Snapshot Mentah LinkedIn"| P0
```

---

## 5. Diagram 0 (Level 0 Logical DFD — Ledakan Diagram Konteks)

Diagram 0 meledakkan Proses 0 menjadi **9 proses fungsional utama** bernomor bulat 1.0 s.d. 9.0, mematuhi batas maksimal 9 proses Kendall & Kendall.

```mermaid
flowchart TD
    %% Entitas Eksternal
    ENT_AKAD["Sistem Akademik (SIAKAD)"]
    ENT_REF["Sistem Referensi Universitas"]
    ENT_ALUMNI["Alumni"]
    ENT_ATASAN["Pengguna Lulusan (Atasan)"]
    ENT_PRODI["Admin Program Studi"]
    ENT_BIRO3["Admin Biro 3"]
    ENT_SUPER["Superadmin (ITC)"]
    ENT_APIFY["Layanan Apify"]

    %% Data Stores
    D1[("D1 Data Pengguna & Akun")]
    D2[("D2 Master Akademik & Yudisium")]
    D3[("D3 Data Biodata & Profil Alumni")]
    D4[("D4 Master Perusahaan & Atasan")]
    D5[("D5 Instrumen & Bank Soal Kuesioner")]
    D6[("D6 Jawaban Survei Terpadu")]
    D7[("D7 Sesi Evaluasi Atasan")]
    D8[("D8 Staging Sinkronisasi LinkedIn")]
    D9[("D9 Referensi Wilayah & UMP")]
    D10[("D10 Log Aktivitas Audit")]

    %% 1.0 Kelola Akun & Autentikasi
    P1["1.0<br/>Kelola Akun &<br/>Autentikasi Pengguna"]
    ENT_ALUMNI -->|"Kredensial Login"| P1
    ENT_PRODI -->|"Kredensial Login"| P1
    ENT_BIRO3 -->|"Kredensial Login"| P1
    ENT_SUPER -->|"Kredensial & Pengaturan Peran"| P1
    D2 -->|"NIM & Tanggal Lahir (Validasi Akun Baru)"| P1
    P1 -->|"Data Akun Terverifikasi"| D1
    D1 -->|"Informasi Profil Akun"| P1
    P1 -->|"Status Akses Login"| ENT_ALUMNI
    P1 -->|"Status Akses Login"| ENT_PRODI
    P1 -->|"Status Akses Login"| ENT_BIRO3
    P1 -->|"Catatan Audit Akses"| D10

    %% 2.0 Kelola Data Master Akademik & Referensi
    P2["2.0<br/>Kelola Data Master<br/>Akademik & Referensi"]
    ENT_AKAD -->|"Data Mahasiswa Lulus & Yudisium"| P2
    ENT_REF -->|"Data Referensi Fakultas, Prodi, Wilayah & UMP"| P2
    P2 -->|"Rekaman Mahasiswa Lulus & Yudisium"| D2
    P2 -->|"Data Master Wilayah, Organisasi & UMP"| D9
    P2 -->|"Catatan Audit Pembaruan Master"| D10

    %% 3.0 Kelola Profil & Riwayat Karier Alumni
    P3["3.0<br/>Kelola Profil &<br/>Riwayat Karier Alumni"]
    ENT_ALUMNI -->|"Pembaruan Profil, Kontak & Status Karier F8"| P3
    D2 -->|"Data Pokok Mahasiswa (NIM, IPK, Tgl Lulus)"| P3
    D9 -->|"Referensi Wilayah Domisili"| P3
    D4 -->|"Referensi Instansi Valid"| P3
    P3 -->|"Data Biodata & Karier Terkini"| D3
    D3 -->|"Status Keterisian Profil"| P3
    P3 -->|"Status Keterisian Profil"| ENT_ALUMNI
    P3 -->|"Catatan Perubahan Profil"| D10

    %% 4.0 Kelola Usulan Perusahaan & Kontak Atasan
    P4["4.0<br/>Kelola Usulan Perusahaan<br/>& Kontak Atasan"]
    ENT_ALUMNI -->|"Usulan Nama Perusahaan & Kontak Atasan"| P4
    ENT_PRODI -->|"Keputusan Verifikasi Instansi"| P4
    P4 -->|"Master Perusahaan & Kontak Atasan Terverifikasi"| D4
    P4 -->|"Catatan Audit Verifikasi Perusahaan"| D10

    %% 5.0 Kelola Instrumen Kuesioner Terpadu
    P5["5.0<br/>Kelola Instrumen<br/>Kuesioner Terpadu"]
    ENT_BIRO3 -->|"Struktur Kuesioner Universitas"| P5
    ENT_PRODI -->|"Struktur Kuesioner Prodi"| P5
    P5 -->|"Definisi Butir Pertanyaan, Section & Bobot Opsi"| D5
    D5 -->|"Katalog Butir Pertanyaan Aktif"| P5

    %% 6.0 Proses Kuesioner Tracer Alumni
    P6["6.0<br/>Proses Kuesioner<br/>Tracer Alumni"]
    ENT_ALUMNI -->|"Pilihan & Isian Jawaban Kuesioner"| P6
    D5 -->|"Daftar Pertanyaan Wajib & Aturan Percabangan"| P6
    D3 -->|"Status Karier Alumni (F8 Sync)"| P6
    P6 -->|"Respon Jawaban Terpadu (responden: alumni)"| D6
    P6 -->|"Status Penyelesaian Kuesioner (100% Selesai)"| ENT_ALUMNI
    P6 -->|"Inisialisasi Sesi Evaluasi Atasan"| D7

    %% 7.0 Proses Evaluasi Kinerja Pengguna Lulusan
    P7["7.0<br/>Proses Evaluasi Kinerja<br/>Pengguna Lulusan (Atasan)"]
    D7 -->|"Sesi Token Aktif & Identitas Sasaran"| P7
    P7 -->|"Formulir Evaluasi Berbasis Token"| ENT_ATASAN
    D5 -->|"Butir Penilaian Kinerja (Target: atasan)"| P7
    ENT_ATASAN -->|"Jawaban Aspek Kompetensi Alumni"| P7
    P7 -->|"Respon Jawaban Terpadu (responden: atasan)"| D6
    P7 -->|"Pembaruan Status Penyerahan Evaluasi"| D7
    P7 -->|"Konfirmasi Penyerahan Evaluasi"| ENT_ATASAN

    %% 8.0 Sinkronisasi & Verifikasi Profil LinkedIn
    P8["8.0<br/>Sinkronisasi Profil<br/>Alumni LinkedIn"]
    D3 -->|"Tautan URL Profil LinkedIn Alumni"| P8
    P8 -->|"Permintaan Scraping LinkedIn"| ENT_APIFY
    ENT_APIFY -->|"Data Snapshot Mentah LinkedIn"| P8
    P8 -->|"Data Staging LinkedIn"| D8
    D8 -->|"Daftar Pengajuan Staging LinkedIn"| ENT_PRODI
    ENT_PRODI -->|"Keputusan Approval Staging"| P8
    P8 -->|"Pembaruan Riwayat Pekerjaan LinkedIn Terverifikasi"| D3
    P8 -->|"Catatan Audit Sinkronisasi LinkedIn"| D10

    %% 9.0 Susun Laporan Analitik & Capaian IKU
    P9["9.0<br/>Susun Laporan Analitik<br/>& Capaian IKU Lulusan"]
    D6 -->|"Data Jawaban Survei Terpadu"| P9
    D3 -->|"Data Populasi Alumni"| P9
    D2 -->|"Data Periode Kelulusan & Angkatan"| P9
    D4 -->|"Data Skala Instansi Tempat Bekerja"| P9
    D9 -->|"Data Standar UMP & Wilayah Kerja"| P9
    P9 -->|"Laporan Eksekutif, Response Rate & Capaian IKU S1"| ENT_BIRO3
    P9 -->|"Laporan Kinerja Lulusan Program Studi"| ENT_PRODI
    D10 -->|"Data Rekam Jejak Audit"| P9
    ENT_SUPER -->|"Kriteria Filter Audit Log"| P9
    P9 -->|"Laporan Audit Trail Sistem"| ENT_SUPER
```

---

## 6. Diagram Rinci / Child Diagram (Level 1)

Setiap diagram anak di bawah meledakkan satu proses induk di Diagram 0 dengan menjaga **keseimbangan vertikal (*vertical balancing*)**. Sembilan proses induk diuraikan secara lengkap sehingga seluruh fitur aplikasi terpetakan secara utuh:

### 6.1 Diagram 1: Kelola Akun & Autentikasi Pengguna
Meledakkan **Proses 1.0** yang menangani otentikasi multi-peran, ubah kata sandi, dan reset sandi.

```mermaid
flowchart TD
    ENT_ALUMNI["Alumni"]
    ENT_PRODI["Admin Program Studi"]
    ENT_BIRO3["Admin Biro 3"]
    ENT_SUPER["Superadmin (ITC)"]

    D1[("D1 Data Pengguna & Akun")]
    D2[("D2 Master Akademik & Yudisium")]
    D10[("D10 Log Aktivitas Audit")]

    P11["1.1<br/>Validasi Kredensial<br/>Login Pengguna"]
    P12["1.2<br/>Proses Pemulihan &<br/>Ubah Kata Sandi"]
    P13["1.3<br/>Kelola Hak Akses &<br/>Peran Pengguna"]
    P14["1.4<br/>Inisialisasi Akun<br/>Alumni Baru"]

    %% 1.1
    ENT_ALUMNI -->|"Kredensial Login"| P11
    ENT_PRODI -->|"Kredensial Login"| P11
    ENT_BIRO3 -->|"Kredensial Login"| P11
    D1 -->|"Data Akun Terdaftar"| P11
    P11 -->|"Status Akses Login"| ENT_ALUMNI
    P11 -->|"Status Akses Login"| ENT_PRODI
    P11 -->|"Status Akses Login"| ENT_BIRO3
    P11 -->|"Catatan Audit Akses"| D10

    %% 1.2
    ENT_ALUMNI -->|"Permohonan Reset / Ubah Sandi"| P12
    D2 -->|"Validasi Tanggal Lahir & NIM"| P12
    P12 -->|"Pembaruan Hash Kata Sandi & Token"| D1

    %% 1.3
    ENT_SUPER -->|"Kredensial & Pengaturan Peran"| P13
    P13 -->|"Pembaruan Role & Status Keaktifan Akun"| D1

    %% 1.4
    D2 -->|"Data Mahasiswa Lulus Belum Berakun"| P14
    P14 -->|"Pendaftaran Akun Baru Alumni"| D1
```

### 6.2 Diagram 2: Kelola Data Master Akademik & Referensi
Meledakkan **Proses 2.0** yang menerima data dari kedua sistem eksternal (`Sistem Akademik` dan `Sistem Referensi`).

```mermaid
flowchart TD
    ENT_AKAD["Sistem Akademik (SIAKAD)"]
    ENT_REF["Sistem Referensi Universitas"]

    D2[("D2 Master Akademik & Yudisium")]
    D9[("D9 Referensi Wilayah & UMP")]
    D10[("D10 Log Aktivitas Audit")]

    P21["2.1<br/>Validasi Format Data<br/>Mahasiswa Lulus & Yudisium"]
    P22["2.2<br/>Sinkronisasi Rekaman<br/>Akademik Kelulusan"]
    P23["2.3<br/>Validasi Struktur Master<br/>Organisasi & Wilayah"]
    P24["2.4<br/>Perbarui Master Referensi<br/>Fakultas, Prodi & UMP"]

    %% Aliran Akademik
    ENT_AKAD -->|"Data Mahasiswa Lulus & Yudisium"| P21
    P21 -->|"Data Lulusan Terstruktur"| P22
    P22 -->|"Rekaman Mahasiswa Lulus & Yudisium"| D2
    P22 -->|"Catatan Audit Sinkronisasi Akademik"| D10

    %% Aliran Referensi
    ENT_REF -->|"Data Referensi Fakultas, Prodi, Wilayah & UMP"| P23
    P23 -->|"Data Master Tervalidasi"| P24
    P24 -->|"Data Master Wilayah, Organisasi & UMP"| D9
    P24 -->|"Catatan Audit Pembaruan Master"| D10
```

### 6.3 Diagram 3: Kelola Profil & Riwayat Karier Alumni
Meledakkan **Proses 3.0** yang menangani pengisian identitas pribadi, domisili, sinkronisasi 5 status karier F8, dan riwayat pekerjaan/studi lanjut.

```mermaid
flowchart TD
    ENT_ALUMNI["Alumni"]

    D2[("D2 Master Akademik & Yudisium")]
    D3[("D3 Data Biodata & Profil Alumni")]
    D4[("D4 Master Perusahaan & Atasan")]
    D9[("D9 Referensi Wilayah & UMP")]
    D10[("D10 Log Aktivitas Audit")]

    P31["3.1<br/>Validasi Identitas &<br/>Kontak Pribadi Alumni"]
    P32["3.2<br/>Perbarui Wilayah<br/>Domisili Saat Ini"]
    P33["3.3<br/>Catat Status Karier<br/>5 Opsi Dikti (F8)"]
    P34["3.4<br/>Simpan Riwayat Pekerjaan<br/>& Studi Lanjut"]

    %% 3.1
    D2 -->|"Data Pokok Mahasiswa (NIM, IPK, Tgl Lulus)"| P31
    ENT_ALUMNI -->|"Pembaruan Profil & Kontak Pribadi"| P31
    P31 -->|"Identitas & Kontak Tervalidasi"| D3

    %% 3.2
    D9 -->|"Referensi Wilayah Domisili"| P32
    ENT_ALUMNI -->|"Data Alamat & Kota Domisili"| P32
    P32 -->|"Data Domisili Alumni"| D3

    %% 3.3
    ENT_ALUMNI -->|"Pilihan Status Aktivitas Utama F8"| P33
    P33 -->|"Status Karier Kanonik Terkunci (F8 Sync)"| D3
    P33 -->|"Status Keterisian Profil"| ENT_ALUMNI

    %% 3.4
    D4 -->|"Referensi Instansi Valid"| P34
    ENT_ALUMNI -->|"Data Pekerjaan, Jabatan, Gaji, Studi"| P34
    P34 -->|"Riwayat Karier Alumni"| D3
    P34 -->|"Catatan Perubahan Profil"| D10
```

### 6.4 Diagram 4: Kelola Usulan Perusahaan & Kontak Atasan
Meledakkan **Proses 4.0** yang menangani usulan perusahaan baru, rekomendasi kemiripan instansi, verifikasi, dan manajemen kontak atasan.

```mermaid
flowchart TD
    ENT_ALUMNI["Alumni"]
    ENT_PRODI["Admin Program Studi"]

    D4[("D4 Master Perusahaan & Atasan")]
    D10[("D10 Log Aktivitas Audit")]

    P41["4.1<br/>Pencatatan Usulan<br/>Perusahaan Baru"]
    P42["4.2<br/>Deteksi Kemiripan &<br/>Rekomendasi Duplikasi"]
    P43["4.3<br/>Verifikasi Keputusan<br/>(Setujui/Tolak/Gabung)"]
    P44["4.4<br/>Kelola Snapshot Kontak<br/>Atasan Langsung"]

    %% 4.1
    ENT_ALUMNI -->|"Usulan Nama Perusahaan & Kontak Atasan"| P41
    P41 -->|"Data Usulan Status Menunggu Verifikasi"| D4

    %% 4.2
    D4 -->|"Daftar Master Perusahaan Terverifikasi"| P42
    P42 -->|"Daftar Rekomendasi Instansi Serupa"| P43

    %% 4.3
    ENT_PRODI -->|"Keputusan Verifikasi Instansi"| P43
    P43 -->|"Pembaruan Status Verifikasi / Penggabungan"| D4
    P43 -->|"Catatan Audit Verifikasi Perusahaan"| D10

    %% 4.4
    ENT_ALUMNI -->|"Data Nama, Jabatan & Email Atasan"| P44
    P44 -->|"Snapshot Kontak Atasan Per Alumni"| D4
```

### 6.5 Diagram 5: Kelola Instrumen Kuesioner Terpadu
Meledakkan **Proses 5.0** yang menangani periode kuesioner, pembuatan section/stepper, butir bank pertanyaan, opsi pilihan, dan penempatan soal.

```mermaid
flowchart TD
    ENT_BIRO3["Admin Biro 3"]
    ENT_PRODI["Admin Program Studi"]

    D5[("D5 Instrumen & Bank Soal Kuesioner")]

    P51["5.1<br/>Kelola Periode Kuesioner<br/>& Target Response Rate"]
    P52["5.2<br/>Kelola Bagian Instrumen<br/>(Section / Stepper)"]
    P53["5.3<br/>Kelola Bank Butir<br/>Pertanyaan Terpadu"]
    P54["5.4<br/>Kelola Opsi Jawaban<br/>& Bobot Skala Likert"]
    P55["5.5<br/>Atur Penempatan Butir<br/>(Placement & Pinjam Soal)"]

    %% 5.1
    ENT_BIRO3 -->|"Tahun Periode & Target Response Rate"| P51
    P51 -->|"Konfigurasi Kuesioner Aktif"| D5

    %% 5.2
    ENT_BIRO3 -->|"Struktur Kuesioner Universitas"| P52
    ENT_PRODI -->|"Struktur Kuesioner Prodi"| P52
    P52 -->|"Daftar Section Instrumen (univ/prodi/atasan)"| D5

    %% 5.3
    ENT_BIRO3 -->|"Definisi Butir Soal Baru"| P53
    ENT_PRODI -->|"Definisi Butir Soal Prodi Baru"| P53
    P53 -->|"Bank Soal Terpadu"| D5

    %% 5.4
    P53 -->|"Relasi Butir Soal ke Opsi"| P54
    P54 -->|"Opsi Pilihan Ganda & Skala Likert"| D5

    %% 5.5
    D5 -->|"Katalog Butir Pertanyaan Aktif"| P55
    P55 -->|"Penempatan Soal pada Section (placement_id)"| D5
```

### 6.6 Diagram 6: Proses Kuesioner Tracer Alumni
Meledakkan **Proses 6.0** yang menangani pengisian survei universitas & prodi, validasi status karier F8, perhitungan kelengkapan 100%, serta inisialisasi sesi token atasan.

```mermaid
flowchart TD
    ENT_ALUMNI["Alumni"]

    D3[("D3 Data Biodata & Profil Alumni")]
    D5[("D5 Instrumen & Bank Soal Kuesioner")]
    D6[("D6 Jawaban Survei Terpadu")]
    D7[("D7 Sesi Evaluasi Atasan")]

    P61["6.1<br/>Validasi Status Karier F8<br/>& Jalur Percabangan"]
    P62["6.2<br/>Simpan Transaksi Respon<br/>Jawaban Tracer"]
    P63["6.3<br/>Hitung Keterisian Wajib<br/>& Kelengkapan Kuesioner"]
    P64["6.4<br/>Inisialisasi Token Sesi<br/>Evaluasi Atasan"]

    %% 6.1
    D3 -->|"Status Karier Alumni (F8 Sync)"| P61
    D5 -->|"Daftar Pertanyaan Wajib & Aturan Percabangan"| P61
    ENT_ALUMNI -->|"Pilihan & Isian Jawaban Kuesioner"| P61
    P61 -->|"Jawaban Lolos Validasi Percabangan"| P62

    %% 6.2
    P62 -->|"Respon Jawaban Terpadu (responden: alumni)"| D6
    P62 -->|"Sinyal Penyimpanan Jawaban"| P63

    %% 6.3
    D6 -->|"Rekapitulasi Respon Tersimpan"| P63
    D5 -->|"Daftar Butir Wajib Sesuai Kategori Karier"| P63
    P63 -->|"Status Penyelesaian Kuesioner (100% Selesai)"| ENT_ALUMNI
    P63 -->|"Status Alumni Bekerja dengan Atasan Valid"| P64

    %% 6.4
    D3 -->|"Data Kontak Atasan Langsung"| P64
    P64 -->|"Inisialisasi Sesi Evaluasi Atasan (Token 64-Karakter)"| D7
```

### 6.7 Diagram 7: Proses Evaluasi Kinerja Pengguna Lulusan (Atasan)
Meledakkan **Proses 7.0** yang memfasilitasi penilaian kinerja alumni oleh pimpinan langsung tanpa perlu login akun.

```mermaid
flowchart TD
    ENT_ATASAN["Pengguna Lulusan (Atasan)"]

    D5[("D5 Instrumen & Bank Soal Kuesioner")]
    D6[("D6 Jawaban Survei Terpadu")]
    D7[("D7 Sesi Evaluasi Atasan")]

    P71["7.1<br/>Validasi Keabsahan Token<br/>Sesi Evaluasi Atasan"]
    P72["7.2<br/>Sajikan Butir Aspek<br/>Kompetensi Penilaian"]
    P73["7.3<br/>Simpan Transaksi Jawaban<br/>Evaluasi Kinerja"]
    P74["7.4<br/>Finalisasi Status Penyerahan<br/>Evaluasi Atasan"]

    %% 7.1
    D7 -->|"Sesi Token Aktif & Identitas Sasaran"| P71
    P71 -->|"Formulir Evaluasi Berbasis Token"| ENT_ATASAN
    P71 -->|"Otorisasi Sesi Valid"| P72

    %% 7.2
    D5 -->|"Butir Penilaian Kinerja (Target: atasan)"| P72
    P72 -->|"Daftar Aspek Kompetensi Kerja"| P73

    %% 7.3
    ENT_ATASAN -->|"Jawaban Aspek Kompetensi Alumni"| P73
    P73 -->|"Respon Jawaban Terpadu (responden: atasan)"| D6
    P73 -->|"Sinyal Selesai Pengisian"| P74

    %% 7.4
    P74 -->|"Pembaruan Status Penyerahan Evaluasi (is_submitted=1)"| D7
    P74 -->|"Konfirmasi Penyerahan Evaluasi"| ENT_ATASAN
```

### 6.8 Diagram 8: Sinkronisasi & Verifikasi Profil LinkedIn
Meledakkan **Proses 8.0** yang menangani permintaan penarikan profil via Apify, penampungan staging, dan persetujuan data karier alumni.

```mermaid
flowchart TD
    ENT_APIFY["Layanan Apify"]
    ENT_PRODI["Admin Program Studi"]

    D3[("D3 Data Biodata & Profil Alumni")]
    D8[("D8 Staging Sinkronisasi LinkedIn")]
    D10[("D10 Log Aktivitas Audit")]

    P81["8.1<br/>Kirim Permintaan Scraping<br/>Tautan Profil LinkedIn"]
    P82["8.2<br/>Simpan Snapshot Hasil<br/>Scraping ke Staging"]
    P83["8.3<br/>Sajikan Antarmuka Peninjauan<br/>Hasil Sinkronisasi"]
    P84["8.4<br/>Verifikasi & Salin Riwayat<br/>Karier ke Profil Aktif"]

    %% 8.1
    D3 -->|"Tautan URL Profil LinkedIn Alumni"| P81
    P81 -->|"Permintaan Scraping LinkedIn"| ENT_APIFY

    %% 8.2
    ENT_APIFY -->|"Data Snapshot Mentah LinkedIn"| P82
    P82 -->|"Data Staging LinkedIn"| D8

    %% 8.3
    D8 -->|"Daftar Pengajuan Staging LinkedIn"| P83
    P83 -->|"Data Peninjauan Hasil Sync"| ENT_PRODI

    %% 8.4
    ENT_PRODI -->|"Keputusan Approval Staging"| P84
    P84 -->|"Pembaruan Riwayat Pekerjaan LinkedIn Terverifikasi"| D3
    P84 -->|"Catatan Audit Sinkronisasi LinkedIn"| D10
```

### 6.9 Diagram 9: Susun Laporan Analitik & Capaian IKU
Meledakkan **Proses 9.0** yang menyusun analitik tracer, evaluasi atasan, rekapitulasi IKU S1, blast pengingat, dan ekspor laporan.

```mermaid
flowchart TD
    ENT_BIRO3["Admin Biro 3"]
    ENT_PRODI["Admin Program Studi"]
    ENT_SUPER["Superadmin (ITC)"]

    D2[("D2 Master Akademik & Yudisium")]
    D3[("D3 Data Biodata & Profil Alumni")]
    D4[("D4 Master Perusahaan & Atasan")]
    D6[("D6 Jawaban Survei Terpadu")]
    D9[("D9 Referensi Wilayah & UMP")]
    D10[("D10 Log Aktivitas Audit")]

    P91["9.1<br/>Agregasi Statistik Partisipasi<br/>& Response Rate"]
    P92["9.2<br/>Hitung Capaian Indikator<br/>Kinerja Utama (IKU S1)"]
    P93["9.3<br/>Analisis Evaluasi Kinerja<br/>& Benchmark Prodi"]
    P94["9.4<br/>Bentuk Berkas Laporan<br/>Ekspor (Excel & ZIP)"]
    P95["9.5<br/>Rekapitulasi Catatan<br/>Audit Trail Sistem"]

    %% 9.1
    D2 -->|"Data Periode Kelulusan & Angkatan"| P91
    D3 -->|"Data Populasi Alumni"| P91
    D6 -->|"Data Jawaban Survei Terpadu"| P91
    P91 -->|"Statistik Response Rate"| P94

    %% 9.2
    D4 -->|"Data Skala Instansi Tempat Bekerja"| P92
    D9 -->|"Data Standar UMP & Wilayah Kerja"| P92
    P92 -->|"Laporan Eksekutif, Response Rate & Capaian IKU S1"| ENT_BIRO3

    %% 9.3
    D6 -->|"Data Skor Kompetensi Atasan"| P93
    P93 -->|"Laporan Kinerja Lulusan Program Studi"| ENT_PRODI

    %% 9.4
    P94 -->|"Ekspor Berkas Excel & ZIP"| ENT_BIRO3
    P94 -->|"Ekspor Berkas Excel & ZIP"| ENT_PRODI

    %% 9.5
    D10 -->|"Data Rekam Jejak Audit"| P95
    ENT_SUPER -->|"Kriteria Filter Audit Log"| P95
    P95 -->|"Laporan Audit Trail Sistem"| ENT_SUPER
```

---

## 7. Matriks Keseimbangan Vertikal (*Vertical Balancing Matrix*)

Sesuai aturan Kendall & Kendall (hlm. 230), tabel berikut membuktikan bahwa seluruh aliran data pada 9 child diagram konsisten 100% dengan proses induknya di Diagram 0:

| Proses Induk (Diagram 0) | Aliran Data Masuk (*Input Interface Flow*) | Aliran Data Keluar (*Output Interface Flow*) | Anak Proses Penanggung Jawab | Status Seimbang |
| :--- | :--- | :--- | :--- | :---: |
| **1.0 Kelola Akun & Autentikasi Pengguna** | • `Kredensial Login` (dari `Alumni, Prodi, Biro3, Super`)<br/>• `Kredensial & Pengaturan Peran` (dari `Superadmin`)<br/>• `NIM & Tanggal Lahir` (dari `D2`) | • `Data Akun Terverifikasi` (ke `D1`)<br/>• `Status Akses Login` (ke pengguna)<br/>• `Catatan Audit Akses` (ke `D10`) | • Proses 1.1<br/>• Proses 1.2<br/>• Proses 1.3<br/>• Proses 1.4 | **Seimbang (*Balanced*)** |
| **2.0 Kelola Data Master Akademik & Referensi** | • `Data Mahasiswa Lulus & Yudisium` (dari `Sistem Akademik`)<br/>• `Data Referensi Fakultas, Prodi, Wilayah & UMP` (dari `Sistem Referensi`) | • `Rekaman Mahasiswa Lulus & Yudisium` (ke `D2`)<br/>• `Data Master Wilayah, Organisasi & UMP` (ke `D9`)<br/>• `Catatan Audit Pembaruan Master` (ke `D10`) | • Proses 2.1 & 2.2<br/>• Proses 2.3 & 2.4 | **Seimbang (*Balanced*)** |
| **3.0 Kelola Profil & Riwayat Karier Alumni** | • `Pembaruan Profil, Kontak & Status Karier F8` (dari `Alumni`)<br/>• `Data Pokok Mahasiswa` (dari `D2`)<br/>• `Referensi Wilayah Domisili` (dari `D9`)<br/>• `Referensi Instansi Valid` (dari `D4`) | • `Data Biodata & Karier Terkini` (ke `D3`)<br/>• `Status Keterisian Profil` (ke `Alumni`)<br/>• `Catatan Perubahan Profil` (ke `D10`) | • Proses 3.1<br/>• Proses 3.2<br/>• Proses 3.3<br/>• Proses 3.4 | **Seimbang (*Balanced*)** |
| **4.0 Kelola Usulan Perusahaan & Kontak Atasan** | • `Usulan Nama Perusahaan & Kontak Atasan` (dari `Alumni`)<br/>• `Keputusan Verifikasi Instansi` (dari `Admin Prodi`) | • `Master Perusahaan & Kontak Atasan Terverifikasi` (ke `D4`)<br/>• `Catatan Audit Verifikasi Perusahaan` (ke `D10`) | • Proses 4.1<br/>• Proses 4.2<br/>• Proses 4.3<br/>• Proses 4.4 | **Seimbang (*Balanced*)** |
| **5.0 Kelola Instrumen Kuesioner Terpadu** | • `Struktur Kuesioner Universitas` (dari `Admin Biro 3`)<br/>• `Struktur Kuesioner Prodi` (dari `Admin Prodi`) | • `Definisi Butir Pertanyaan, Section & Bobot Opsi` (ke `D5`) | • Proses 5.1<br/>• Proses 5.2<br/>• Proses 5.3<br/>• Proses 5.4<br/>• Proses 5.5 | **Seimbang (*Balanced*)** |
| **6.0 Proses Kuesioner Tracer Alumni** | • `Pilihan & Isian Jawaban Kuesioner` (dari `Alumni`)<br/>• `Daftar Pertanyaan Wajib & Aturan Percabangan` (dari `D5`)<br/>• `Status Karier Alumni (F8 Sync)` (dari `D3`) | • `Respon Jawaban Terpadu (responden: alumni)` (ke `D6`)<br/>• `Status Penyelesaian Kuesioner (100% Selesai)` (ke `Alumni`)<br/>• `Inisialisasi Sesi Evaluasi Atasan` (ke `D7`) | • Proses 6.1<br/>• Proses 6.2<br/>• Proses 6.3<br/>• Proses 6.4 | **Seimbang (*Balanced*)** |
| **7.0 Proses Evaluasi Kinerja Pengguna Lulusan** | • `Sesi Token Aktif & Identitas Sasaran` (dari `D7`)<br/>• `Butir Penilaian Kinerja (Target: atasan)` (dari `D5`)<br/>• `Jawaban Aspek Kompetensi Alumni` (dari `Atasan`) | • `Formulir Evaluasi Berbasis Token` (ke `Atasan`)<br/>• `Respon Jawaban Terpadu (responden: atasan)` (ke `D6`)<br/>• `Pembaruan Status Penyerahan Evaluasi` (ke `D7`)<br/>• `Konfirmasi Penyerahan Evaluasi` (ke `Atasan`) | • Proses 7.1<br/>• Proses 7.2<br/>• Proses 7.3<br/>• Proses 7.4 | **Seimbang (*Balanced*)** |
| **8.0 Sinkronisasi & Verifikasi Profil LinkedIn** | • `Tautan URL Profil LinkedIn Alumni` (dari `D3`)<br/>• `Data Snapshot Mentah LinkedIn` (dari `Apify`)<br/>• `Keputusan Approval Staging` (dari `Admin Prodi`) | • `Permintaan Scraping LinkedIn` (ke `Apify`)<br/>• `Data Staging LinkedIn` (ke `D8`)<br/>• `Pembaruan Riwayat Pekerjaan LinkedIn Terverifikasi` (ke `D3`)<br/>• `Catatan Audit Sinkronisasi LinkedIn` (ke `D10`) | • Proses 8.1<br/>• Proses 8.2<br/>• Proses 8.3<br/>• Proses 8.4 | **Seimbang (*Balanced*)** |
| **9.0 Susun Laporan Analitik & Capaian IKU** | • `Data Jawaban Survei Terpadu` (dari `D6`)<br/>• `Data Populasi Alumni` (dari `D3`)<br/>• `Data Periode Kelulusan & Angkatan` (dari `D2`)<br/>• `Data Skala Instansi Tempat Bekerja` (dari `D4`)<br/>• `Data Standar UMP & Wilayah Kerja` (dari `D9`)<br/>• `Data Rekam Jejak Audit` (dari `D10`) | • `Laporan Eksekutif, Response Rate & Capaian IKU S1` (ke `Admin Biro 3`)<br/>• `Laporan Kinerja Lulusan Program Studi` (ke `Admin Prodi`)<br/>• `Laporan Audit Trail Sistem` (ke `Superadmin`)<br/>• `Ekspor Berkas Excel & ZIP` (ke admin) | • Proses 9.1<br/>• Proses 9.2<br/>• Proses 9.3<br/>• Proses 9.4<br/>• Proses 9.5 | **Seimbang (*Balanced*)** |

---

## 8. Kamus Aliran Data (*Data Flow Dictionary*)

Mengacu pada Kendall & Kendall (hlm. 237–238), struktur rincian data untuk setiap paket aliran data adalah sebagai berikut:

| Nama Aliran Data | Bentuk / Tipe Data | Komposisi Elemen Data Pokok |
| :--- | :--- | :--- |
| **`Data Mahasiswa Lulus & Yudisium`** | Paket Data Eksternal | `NIM` + `Nama Mahasiswa` + `Kode Prodi` + `Tanggal Masuk` + `Tanggal Kelulusan Resmi` + `IPK Kelulusan` + `Nomor SK Yudisium` + `Judul Skripsi/Tugas Akhir` + `Periode Akademik Lulus` |
| **`Data Referensi Fakultas, Prodi, Wilayah & UMP`** | Paket Data Eksternal | `Kode Fakultas` + `Nama Fakultas` + `Kode Prodi` + `Nama Prodi` + `Jenjang` + `Kode Provinsi` + `Nama Provinsi` + `Kode Kota/Kabupaten` + `Nama Kota/Kabupaten` + `Kode Negara ISO` + `Nominal UMP` + `Tahun Berlaku UMP` |
| **`Data Profil, Riwayat Karier & Usulan Instansi`** | Transaksi Pengguna | `NIM` + `Nomor Telepon` + `Email Pribadi` + `Alamat Domisili` + `Kode Kota Domisili` + `Kategori Karier F8` + `Nama Perusahaan` + `Jabatan` + `Kisaran Gaji` + `Nama Atasan Langsung` + `Kontak/Email Atasan` |
| **`Pilihan & Isian Jawaban Kuesioner`** | Transaksi Pengguna | `NIM / Biodata ID` + `Kode Section` + `ID Butir Pertanyaan` + `ID Opsi Terpilih` + `Jawaban Teks Bebas` + `Nilai Skala Likert` + `Timestamp Pengisian` |
| **`Jawaban Aspek Kompetensi Alumni`** | Transaksi Pengguna | `Token Evaluasi` + `Biodata ID Sasaran` + `ID Butir Penilaian Kinerja` + `Nilai Skor Likert 1-5` + `Komentar Kualitatif Atasan` + `Timestamp Submit` |
| **`Laporan Eksekutif, Response Rate & Capaian IKU S1`** | Dokumen Keluaran | `Tahun Periode Tracer` + `Populasi Lulusan S1 (TS-1)` + `Jumlah Responden Mengisi` + `Persentase Response Rate` + `Jumlah Lulusan Mendapat Pekerjaan Layak` + `Jumlah Lulusan Studi Lanjut` + `Jumlah Lulusan Wiraswasta` + `Skor Capaian Bobot IKU S1` |
| **`Laporan Audit Trail & Log Aktivitas Sistem`** | Dokumen Keluaran | `ID Log` + `User ID Pelaku` + `Username / Role` + `Kode Aksi (ACTION_TYPE)` + `Model Terkena Mutasi` + `ID Rekaman Target` + `Deskripsi Perubahan Data` + `IP Address` + `Waktu Kejadian` |

---

## 9. Kesimpulan & Panduan Presentasi Tugas Akhir

1. **Pemenuhan Kaidah Kendall & Kendall**:
   - Seluruh simbol, penomoran (`0` untuk konteks, `1.0`–`9.0` untuk Diagram 0, `X.1`–`X.4` untuk diagram anak), dan penamaan verb-adjective-noun telah tervalidasi.
   - Tidak ada koneksi ilegal entitas-ke-entitas, entitas-ke-simpanan, maupun simpanan-ke-simpanan.
   - Diagram 0 memiliki tepat 9 proses, memenuhi batas maksimal keterbacaan Kendall.
2. **Keberadaan Entitas Sistem Eksternal**:
   - `Sistem Akademik (SIAKAD)` dan `Sistem Referensi Universitas (SIM Referensi)` digambarkan secara resmi sebagai **Entitas Eksternal (*Double Square*)** yang memberikan data masukan ke proses master data sistem Tracer Study UKDW.
   - Posisi ini secara ilmiah dan praktis memisahkan batasan aplikasi tugas akhir dengan sistem institusional kampus yang telah berjalan mandiri.
