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

Setiap diagram anak di bawah meledakkan satu proses induk di Diagram 0 dengan menjaga **keseimbangan vertikal (*vertical balancing*)**.

### 6.1 Diagram 2: Kelola Data Master Akademik & Referensi
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

### 6.2 Diagram 6: Proses Kuesioner Tracer Alumni
Meledakkan **Proses 6.0** yang menangani kuesioner universitas, kuesioner prodi, penguncian status karier F8, serta pembentukan token atasan.

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

### 6.3 Diagram 7: Proses Evaluasi Kinerja Pengguna Lulusan (Atasan)
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

---

## 7. Matriks Keseimbangan Vertikal (*Vertical Balancing Matrix*)

Sesuai aturan Kendall & Kendall (hlm. 230), tabel berikut membuktikan bahwa seluruh aliran data pada child diagram konsisten 100% dengan proses induknya di Diagram 0:

| Proses Induk (Diagram 0) | Aliran Data Masuk (*Input Interface Flow*) | Aliran Data Keluar (*Output Interface Flow*) | Anak Proses Penanggung Jawab | Status Seimbang |
| :--- | :--- | :--- | :--- | :---: |
| **2.0 Kelola Data Master Akademik & Referensi** | • `Data Mahasiswa Lulus & Yudisium` (dari `Sistem Akademik`)<br/>• `Data Referensi Fakultas, Prodi, Wilayah & UMP` (dari `Sistem Referensi`) | • `Rekaman Mahasiswa Lulus & Yudisium` (ke `D2`)<br/>• `Data Master Wilayah, Organisasi & UMP` (ke `D9`)<br/>• `Catatan Audit Pembaruan Master` (ke `D10`) | • Proses 2.1 & 2.2<br/>• Proses 2.3 & 2.4 | **Seimbang (*Balanced*)** |
| **6.0 Proses Kuesioner Tracer Alumni** | • `Pilihan & Isian Jawaban Kuesioner` (dari `Alumni`)<br/>• `Daftar Pertanyaan Wajib & Aturan Percabangan` (dari `D5`)<br/>• `Status Karier Alumni (F8 Sync)` (dari `D3`) | • `Respon Jawaban Terpadu (responden: alumni)` (ke `D6`)<br/>• `Status Penyelesaian Kuesioner (100% Selesai)` (ke `Alumni`)<br/>• `Inisialisasi Sesi Evaluasi Atasan` (ke `D7`) | • Proses 6.1<br/>• Proses 6.2<br/>• Proses 6.3<br/>• Proses 6.4 | **Seimbang (*Balanced*)** |
| **7.0 Proses Evaluasi Kinerja Pengguna Lulusan** | • `Sesi Token Aktif & Identitas Sasaran` (dari `D7`)<br/>• `Butir Penilaian Kinerja (Target: atasan)` (dari `D5`)<br/>• `Jawaban Aspek Kompetensi Alumni` (dari `Atasan`) | • `Formulir Evaluasi Berbasis Token` (ke `Atasan`)<br/>• `Respon Jawaban Terpadu (responden: atasan)` (ke `D6`)<br/>• `Pembaruan Status Penyerahan Evaluasi` (ke `D7`)<br/>• `Konfirmasi Penyerahan Evaluasi` (ke `Atasan`) | • Proses 7.1<br/>• Proses 7.2<br/>• Proses 7.3<br/>• Proses 7.4 | **Seimbang (*Balanced*)** |

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
