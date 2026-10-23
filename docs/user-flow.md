# User Flow Sistem Tracer Study per Peran Pengguna

Dokumen ini berisi alur pengguna (*User Flow*) lengkap untuk setiap peran (*user role*) yang terdapat pada Sistem **Tracer Study**.

> Revisi 22 Oktober 2026: alur Master Data Perusahaan untuk Superadmin, Fakultas, dan Prodi memakai tampilan yang sama. Perbedaannya hanya pada batas data dan filter: Superadmin lintas universitas, Fakultas lintas prodi dalam fakultas, dan Prodi terbatas pada prodi sendiri. Tindakan yang tersedia hanya memproses data dalam lingkup tersebut.

---

## Daftar Peran Pengguna (User Roles)

1. [1. User Flow: Alumni / Lulusan](#1-user-flow-alumni--lulusan)
2. [2. User Flow: Admin Program Studi (Admin Prodi)](#2-user-flow-admin-program-studi-admin-prodi)
3. [3. User Flow: Admin Fakultas](#3-user-flow-admin-fakultas)
4. [4. User Flow: Admin Biro III / Kemahasiswaan & Alumni](#4-user-flow-admin-biro-iii--kemahasiswaan--alumni)
5. [5. User Flow: Superadmin](#5-user-flow-superadmin)
6. [6. User Flow: Publik / Tamu (Guest)](#6-user-flow-publik--tamu-guest)
7. [7. User Flow: Atasan Langsung / Pengguna Lulusan](#7-user-flow-atasan-langsung--pengguna-lulusan-public-token-based-evaluation-flow)

---

## 1. User Flow: Alumni / Lulusan

Alumni adalah pengguna yang mengisi data biodata & profil pekerjaan, kuesioner tracer study universitas (Kemdikbudristek), serta kuesioner khusus program studi.

### Diagram Mermaid: Alumni Flow

```mermaid
flowchart TD
    A[Mulai: Akses Halaman Utama / Landing Page] --> B{Sudah Login?}
    B -->|Sudah| G[Dashboard Alumni: /alumni/dashboard]
    B -->|Belum| C[Halaman Login: /login]

    C --> D{Kredensial Login}
    D -->|Username NIM & Password| E[Validasi Akun]
    C -->|Lupa Kata Sandi| F[Form Lupa Kata Sandi: /forgot-password]
    F -->|Kirim Token Email| F1[Reset Password: /reset-password/token]
    F1 --> C

    E -->|Gagal| C
    E -->|Berhasil| H{Wajib Ganti Password? must_change_password}
    H -->|Ya - Login Default Tgl Lahir| I[Halaman Ganti Sandi: /change-password]
    I -->|Password Baru Disimpan| G
    H -->|Tidak| G

    G --> J[Navigasi Menu Utama Alumni]

    J --> K[1. Kelola Profil & Biodata: /alumni/profile]
    K --> K1[Perbarui Kontak, Biodata & Riwayat Pekerjaan]
    K --> K2[Input Data Atasan Langsung - Memicu Token Evaluasi Atasan]
    K --> K3{Perusahaan Terdaftar?}
    K3 -->|Belum Ada| K4[Tambah Perusahaan Baru: /alumni/company]
    K3 -->|Sudah Ada| K5[Pilih dari Master Perusahaan]
    K4 --> K6[Simpan Perubahan Profil]
    K5 --> K6
    K6 --> G

    J --> L[2. Isi Kuesioner Universitas: /alumni/kuesioner]
    L --> L1[Pengisian Pertanyaan Tracer Study Kemdikbudristek]
    L1 --> L2[Simpan Jawaban Kuesioner Universitas]
    L2 --> G

    J --> M[3. Isi Kuesioner Program Studi: /alumni/kuesioner-prodi]
    M --> M1[Pengisian Pertanyaan Khusus Sesuai Prodi Alumni]
    M1 --> M2[Simpan Jawaban Kuesioner Prodi]
    M2 --> G

    G --> N[Pantau Progress di Dashboard]
    N --> O{Keluar Sesi?}
    O -->|Ya - Klik Logout| P[Konfirmasi Logout SweetAlert2]
    P -->|Konfirmasi| Q[Logout: /logout -> Redirect ke Beranda]
```

### Rincian Alur Alumni Sebenarnya (Sesuai Program):
1. **Otentikasi & Keamanan Akun**:
   - **Login Akun**: Alumni masuk melalui `/login` menggunakan **Username (NIM)** dan **Password**.
   - **Password Bawaan (Default Password)**: Alumni yang baru pertama kali masuk dapat menggunakan tanggal lahir dengan format `DDMMYYYY` (contoh: `15082001`).
   - **Wajib Ganti Password (`must_change_password`)**: Pengguna yang masuk menggunakan password bawaan otomatis diwajibkan mengganti kata sandi terlebih dahulu di rute `/change-password` sebelum diperbolehkan mengakses fitur dashboard.
   - **Lupa Password**: Tersedia alur pemulihan kata sandi via email (`/forgot-password` dan `/reset-password/{token}`).
2. **Dashboard Alumni (`/alumni/dashboard`)**:
   - Menyajikan 3 kartu monitoring status kelengkapan data:
     1. **Kelengkapan Profil & Biodata** (persentase & jumlah field terisi).
     2. **Kuesioner Universitas** (persentase & jumlah soal wajib Kemdikbudristek yang sudah dijawab).
     3. **Kuesioner Program Studi** (persentase & jumlah soal khusus prodi yang sudah dijawab).
3. **Pengelolaan Profil & Pekerjaan (`/alumni/profile`)**:
   - Mengubah biodata pribadi, nomor kontak, serta riwayat pekerjaan/posisi saat ini.
   - Mengisi data atasan langsung (nama dan email atasan), yang secara otomatis menerbitkan data `evaluasi_atasan` untuk dinilai oleh pengguna lulusan via tautan ber-token.
   - Mendaftarkan entitas perusahaan baru (`/alumni/company`) apabila tempat kerja belum ada pada data master universitas.
4. **Pengisian Kuesioner Universitas / Tracer Study (`/alumni/kuesioner`)**:
   - Mengisi butir-butir pertanyaan standar tracer study (pendapatan, masa tunggu kerja, relevansi keilmuan, dsb.).
   - Menyimpan jawaban ke sistem (`POST /alumni/kuesioner`).
5. **Pengisian Kuesioner Program Studi (`/alumni/kuesioner-prodi`)**:
   - Mengisi kuesioner evaluasi kurikulum dan fasilitas spesifik yang dikelola oleh Program Studi masing-masing alumni.
   - Menyimpan respon ke database (`POST /alumni/kuesioner-prodi`).
6. **Logout / Keluar**:
   - Alumni dapat keluar dari sistem melalui tombol Logout di Navbar dengan konfirmasi SweetAlert2 (`POST /logout`), yang menghapus sesi dan mengarahkan kembali ke Landing Page.

---

## 2. User Flow: Admin Program Studi (Admin Prodi)

Admin Program Studi bertanggung jawab mengelola kuesioner khusus prodi, memantau tingkat partisipasi alumni prodi, memvalidasi pengajuan perusahaan baru dari alumni, mengelola akun mahasiswa/alumni prodi, dan mengekspor data tracer prodi.

### Diagram Mermaid: Admin Prodi Flow

```mermaid
flowchart TD
    A[Mulai: Login Admin Prodi] --> B[Dashboard Prodi: /prodi/dashboard]
    B --> C{Pilih Menu Operasional}

    C --> D[1. Monitoring Partisipasi Alumni Prodi]
    D --> D1[Lihat Metrik: Total Alumni, Respon Prodi, Respon Univ, % Partisipasi]
    D --> D2[Lihat Tabel 5 Alumni Terbaru & Status Kelengkapan]

    C --> E[2. Direktori & Detail Alumni Prodi: /prodi/alumni]
    E --> E1[Cari & Filter Data Alumni Prodi]
    E --> E2[Lihat Detail Alumni & Respon Kuesioner: /prodi/alumni/id]
    E2 --> E3[Perbarui Profil Alumni jika Diperlukan: POST /profile]
    E1 --> E4[Ekspor Rekap Alumni Prodi ke Excel: /export-excel]

    C --> F[3. Kelola Kuesioner Khusus Prodi]
    F --> F1[Kelola Section / Bagian: /prodi/sections]
    F1 --> F2[Tambah, Edit, Hapus & Atur Urutan Section: /reorder]
    F --> F3[Kelola Pertanyaan Prodi: /prodi/pertanyaan]
    F3 --> F4[Tambah, Edit, Hapus & Reorder Butir Pertanyaan]
    F --> F5[Kelola Pilihan Opsi Jawaban: /prodi/opsi]
    F5 --> F6[Tambah, Edit, Hapus Opsi Jawaban Radio / Checkbox]

    C --> G[4. Verifikasi Pengajuan Perusahaan: /prodi/perusahaan]
    G --> G1[Daftar Pengajuan Perusahaan Baru dari Alumni Prodi]
    G1 --> G2{Tindakan Verifikasi}
    G2 -->|Setujui Baru| G3[Verifikasi Perusahaan: POST /verify]
    G2 -->|Tolak| G4[Tolak Pengajuan: POST /reject]
    G2 -->|Duplikat / Sudah Ada| G5[Ganti & Hubungkan ke Master: POST /replace]
    G2 -->|Koreksi Data| G6[Edit & Verifikasi: PUT /perusahaan/id]

    C --> H[5. Manajemen Akun Mahasiswa / Alumni Prodi: /prodi/manajemen-akun]
    H --> H1[Daftar Akun Alumni Program Studi]
    H1 --> H2[Perbarui Data Akun: PUT /manajemen-akun/id]
    H1 --> H3[Reset Password ke Default Tgl Lahir + Kirim Notifikasi: POST /reset-default-notify]

    D2 --> Z[Selesai / Logout: /logout]
    E4 --> Z
    F6 --> Z
    G3 --> Z
    G4 --> Z
    G5 --> Z
    G6 --> Z
    H3 --> Z
```

### Rincian Alur Admin Prodi Sebenarnya:
1. **Dashboard Prodi (`/prodi/dashboard`)**:
   - Menampilkan metrik: Total Alumni Prodi, Responden Kuesioner Prodi, Responden Kuesioner Universitas, dan Persentase Partisipasi.
   - Tabel ringkasan 5 alumni terbaru beserta status kelengkapan kuesioner universitas dan prodi.
2. **Kelola Alumni Prodi (`/prodi/alumni`)**:
   - Menampilkan direktori alumni khusus program studi yang bersangkutan.
   - Melihat rincian profil biodata dan seluruh riwayat jawaban tracer study alumni (`/prodi/alumni/{id}`).
   - Memperbarui data profil alumni jika terdapat koreksi informasi.
   - Mengunduh rekap data alumni prodi ke format Excel (`/prodi/alumni/{id}/export-excel`).
3. **Instrumen Kuesioner Khusus Prodi**:
   - **Section Kuesioner Prodi (`/prodi/sections`)**: Menambah, mengedit, menghapus, dan mengatur urutan tampilan bagian soal (*reorder*).
   - **Pertanyaan Prodi (`/prodi/pertanyaan`)**: Menambah, mengedit, menghapus, dan mengatur urutan butir pertanyaan prodi. Saat butir baru dibuat, sistem secara otomatis mengenerate opsi pilihan default melalui `QuestionDefaultOptionsService` (misal 2 opsi pilihan ganda, skala 1-5, atau pilihan ganda + kolom isian teks 'Lainnya (Tuliskan)').
   - **Opsi Jawaban Prodi (`/prodi/opsi`)**: Mengelola, menyunting teks, menambah, atau menghapus pilihan opsi respon untuk tipe pertanyaan yang mendukung opsi.
4. **Verifikasi Perusahaan (`/prodi/perusahaan`)**:
   - Memeriksa nama instansi/perusahaan baru yang dimasukkan alumni prodi saat memperbarui profil pekerjaan.
   - Aksi: Setujui perusahaan baru (`verify`), tolak (`reject`), edit lalu verifikasi (`update`), atau ganti/hubungkan ke data perusahaan master yang sudah terverifikasi sebelumnya (`replace`).
5. **Manajemen Akun Alumni Prodi (`/prodi/manajemen-akun`)**:
   - Melihat akun login mahasiswa/alumni pada program studi tersebut.
   - Mengubah username, nama, atau email akun alumni.
   - Mereset password akun alumni ke tanggal lahir bawaan (`DDMMYYYY`) disertai notifikasi email resmi (`reset-default-notify`).

---

## 3. User Flow: Admin Fakultas

Admin Fakultas bertugas memantau kinerja tracer study lintas program studi di lingkungan fakultasnya, memeriksa direktori alumni, memvalidasi pengajuan perusahaan alumni se-fakultas, dan mengelola akun mahasiswa/alumni tingkat fakultas.

### Diagram Mermaid: Admin Fakultas Flow

```mermaid
flowchart TD
    A[Mulai: Login Admin Fakultas] --> B[Dashboard Fakultas: /fakultas/dashboard]
    B --> C{Pilih Menu Operasional}

    C --> D[1. Monitoring Statistik Tingkat Fakultas]
    D --> D1[Lihat Metrik: Total Alumni Fakultas, Total Selesai, % Kelengkapan]
    D --> D2[Tabel Distribusi Partisipasi per Program Studi dalam Fakultas]

    C --> E[2. Direktori Alumni Fakultas: /fakultas/alumni]
    E --> E1[Filter Alumni Berdasarkan Program Studi di Fakultas]
    E --> E2[Lihat Rincian Alumni & Jawaban Kuesioner: /fakultas/alumni/id]
    E2 --> E3[Perbarui Data Profil Alumni jika Perlu: POST /profile]
    E1 --> E4[Ekspor Data Alumni Fakultas ke Excel: /export-excel]

    C --> F[3. Verifikasi Perusahaan Tingkat Fakultas: /fakultas/perusahaan]
    F --> F1[Daftar Usulan Perusahaan dari Seluruh Alumni Fakultas]
    F1 --> F2{Tindakan Verifikasi}
    F2 -->|Setujui Baru| F3[Verifikasi Perusahaan: POST /verify]
    F2 -->|Tolak Usulan| F4[Tolak Perusahaan: POST /reject]
    F2 -->|Sudah Terdaftar| F5[Hubungkan ke Master Terverifikasi: POST /replace]
    F2 -->|Koreksi Data| F6[Edit & Setujui: PUT /perusahaan/id]

    C --> G[4. Manajemen Akun Se-Fakultas: /fakultas/manajemen-akun]
    G --> G1[Daftar Akun Alumni Seluruh Prodi dalam Fakultas]
    G1 --> G2[Perbarui Akun Alumni: PUT /manajemen-akun/id]
    G1 --> G3[Reset Password ke Default Tgl Lahir + Notifikasi: POST /reset-default-notify]

    D2 --> Z[Selesai / Logout: /logout]
    E4 --> Z
    F3 --> Z
    F4 --> Z
    F5 --> Z
    F6 --> Z
    G3 --> Z
```

### Rincian Alur Admin Fakultas Sebenarnya:
1. **Dashboard Fakultas (`/fakultas/dashboard`)**:
   - Menampilkan ringkasan metrik fakultas: Total alumni se-fakultas, total alumni yang telah menyelesaikan seluruh instrumen tracer study, dan persentase kelengkapan fakultas.
   - Tabel komparasi respons dan tingkat partisipasi alumni untuk setiap Program Studi di bawah naungan fakultas.
2. **Direktori & Detail Alumni Fakultas (`/fakultas/alumni`)**:
   - Melihat daftar seluruh alumni dalam lingkup fakultas dengan filter program studi.
   - Meninjau data detail profil dan lembar jawaban tracer study alumni (`/fakultas/alumni/{id}`).
   - Melakukan koreksi/perubahan profil alumni bila diperlukan.
   - Mengekspor data profil dan isian kuesioner alumni ke format Excel (`/export-excel`).
3. **Verifikasi Perusahaan Lingkup Fakultas (`/fakultas/perusahaan`)**:
   - Meninjau pengajuan perusahaan baru yang diinput oleh alumni dari prodi-prodi di fakultas terkait.
   - Fitur approval: Verifikasi (`verify`), tolak (`reject`), edit & setujui (`update`), atau tautkan ke perusahaan terverifikasi (`replace`).
4. **Manajemen Akun Mahasiswa / Alumni Se-Fakultas (`/fakultas/manajemen-akun`)**:
   - Mengelola akun alumni pada semua prodi di fakultas bersangkutan.
   - Mengedit informasi akun serta mereset kata sandi ke tanggal lahir bawaan (`DDMMYYYY`) via tombol *Reset Default & Notify*.

---

## 4. User Flow: Admin Biro III / Kemahasiswaan & Alumni

Biro III memantau data universitas, membaca detail alumni, mengekspor hasil dan mengirim komunikasi pengingat. Halaman detail profil serta LinkedIn bersifat baca. Rute POST pembaruan profil masih ada tetapi handler mengembalikan peringatan tanpa menyimpan; tidak ada rute sinkronisasi LinkedIn Biro III.

```mermaid
flowchart TD
    A[Login Biro III] --> B[Dashboard universitas]
    B --> C[Filter direktori alumni]
    C --> D[Baca profil, jawaban, evaluasi dan riwayat LinkedIn]
    C --> E[Unduh Excel atau ZIP]
    C --> F[Kirim pengingat individual atau massal]
```

Rute utama: `/biro3/dashboard`, `/biro3/alumni`, `/biro3/alumni/{id}`, `/biro3/alumni/{id}/export-excel`, `/biro3/alumni/export-zip`, `/biro3/alumni/blast-email` dan `/biro3/alumni/{id}/send-email`. Hak akses rute bersama juga mencakup pencatatan perusahaan serta permintaan undangan evaluasi; istilah baca saja dibatasi pada mutasi profil dan LinkedIn.


## 5. User Flow: Superadmin

Superadmin memegang kendali penuh atas seluruh modul sistem Tracer Study: instrumen kuesioner universitas, kuesioner khusus prodi, instrumen evaluasi atasan, verifikasi perusahaan tingkat kampus, sinkronisasi massal LinkedIn, manajemen akun seluruh role, dan log audit sistem.

### Diagram Mermaid: Superadmin Flow

```mermaid
flowchart TD
    A[Mulai: Login Superadmin] --> B[Dashboard Superadmin: /superadmin/dashboard]
    B --> C{Pilih Modul Manajemen}

    C --> D[1. Kelola Kuesioner Universitas]
    D --> D1[Kelola Bagian / Section: /superadmin/sections]
    D --> D2[Kelola Paket Kuesioner Induk & Toggle Aktif: /superadmin/kuesioner]
    D --> D3[Kelola Butir Pertanyaan & Branching: /superadmin/pertanyaan]
    D --> D4[Kelola Opsi Jawaban Pertanyaan: /superadmin/pertanyaan/options]

    C --> E[2. Kelola Kuesioner Khusus Prodi: /superadmin/prodi-kuesioner]
    E --> E1[Pilih Program Studi Sasaran]
    E1 --> E2[Kelola Section, Pertanyaan & Opsi Khusus Prodi Tersebut]

    C --> F[3. Kelola Evaluasi Pengguna Lulusan / Atasan: /superadmin/evaluasi-atasan]
    F --> F1[Kelola Butir Pertanyaan Penilaian Kinerja Lulusan]
    F1 --> F2[Tambah, Edit, Toggle Aktif & Reorder Pertanyaan Evaluasi]

    C --> G[4. Direktori Alumni & Ekspor: /superadmin/alumni]
    G --> G1[Audit Data Profil & Respon Seluruh Alumni Kampus]
    G1 --> G2[Perbarui Profil Alumni & Ekspor ke Excel: /export-excel]
    G1 --> G3[Akses Detail Alumni: /superadmin/alumni/id]
    G3 --> G4[Audit Kuesioner Univ, Prodi, Evaluasi Atasan & Trace LinkedIn]
    G4 -->|Klik Logo / Tombol LinkedIn Banner| I

    C --> H[5. Verifikasi Perusahaan Tingkat Pusat: /superadmin/perusahaan]
    H --> H1[Daftar Pengajuan Perusahaan dari Seluruh Alumni]
    H1 --> H2[Tindakan: Setujui /verify, Tolak /reject, Ganti /replace, Edit /update]

    C --> I[6. Sinkronisasi LinkedIn Terpusat: /superadmin/linkedin-sync]
    I --> I1[Auto-Filter Tahun Lulus & Highlight Profil Dipilih via Param alumni_id]
    I --> I2[Sinkronisasi Satuan per Alumni: POST /single]
    I --> I3[Sinkronisasi Batch / Massal Seluruh Alumni: POST /batch]
    I --> I4[Aksi Ikon Mata Detail Alumni: Klik menuju /superadmin/alumni/id]
    I4 -->|Navigasi Cepat Detail| G3

    C --> J[7. Manajemen Akun Terpadu: /superadmin/manajemen-akun]
    J --> J1[Kelola Akun Admin Fakultas, Admin Prodi, dan Mahasiswa]
    J1 --> J2[Perbarui Data Akun: PUT /manajemen-akun/id]
    J1 --> J3[Reset Password ke Default Tgl Lahir + Notifikasi: POST /reset-default-notify]

    C --> K[8. Log Aktivitas & Audit Trail: /superadmin/logs]
    K --> K1[Pantau Log Aktivitas Pengguna, Riwayat Perubahan, & Keamanan]

    D4 --> Z[Selesai / Logout: /logout]
    E2 --> Z
    F2 --> Z
    G2 --> Z
    H2 --> Z
    I3 --> Z
    J3 --> Z
    K1 --> Z
```

### Kontrak metrik Dashboard Superadmin

Response rate ditampilkan dengan tiga status: merah di bawah 50%, kuning 50–74,9%, dan hijau mulai 75%. Panel kelengkapan hanya menilai Profil Alumni, Kuesioner Universitas, dan gabungan keduanya. Kuesioner Prodi tidak memengaruhi angka kelengkapan universitas.

### Rincian Alur Superadmin Sebenarnya:
1. **Dashboard Superadmin (`/superadmin/dashboard`)**:
   - Pusat pemantauan menyeluruh: Statistik pengisian tracer, ringkasan instrumen kuesioner, status approval perusahaan, dan peringatan operasional.
2. **Kelola Kuesioner Universitas (Kemdikbudristek)**:
   - **Section Kuesioner (`/superadmin/sections`)**: CRUD dan reorder bagian-bagian kuesioner.
   - **Kuesioner Induk (`/superadmin/kuesioner`)**: Mengatur instrumen kuesioner dan toggle status aktif/nonaktif.
   - **Pertanyaan & Opsi (`/superadmin/pertanyaan`)**: CRUD pertanyaan, konfigurasi percabangan soal (*branching logic*), pembersihan opsi duplikat, generate opsi default otomatis via `QuestionDefaultOptionsService`, serta kelola opsi pilihan jawaban.
3. **Kelola Kuesioner Prodi Override (`/superadmin/prodi-kuesioner`)**:
   - Memilih prodi mana saja dan mengelola section, pertanyaan, dan opsi kuesioner spesifik prodi tersebut.
4. **Kelola Instrumen Evaluasi Atasan (`/superadmin/evaluasi-atasan`)**:
   - Menambah, mengubah, mengaktifkan/menonaktifkan, dan mengatur urutan butir pertanyaan pada formulir evaluasi pengguna lulusan.
5. **Verifikasi Perusahaan Terpusat (`/superadmin/perusahaan`)**:
   - Memvalidasi seluruh usulan perusahaan baru dari alumni semua fakultas dan program studi.
6. **Direktori Alumni Se-Universitas & Audit Detail (`/superadmin/alumni`)**:
   - Menelusuri seluruh data alumni lintas prodi dan fakultas dengan filter tahun kelulusan terbaru atau spesifik.
   - Mengunduh rekapitulasi data profil dan kuesioner alumni ke berkas Excel (`.xls` / `.zip` per prodi).
   - **Halaman Detail Alumni (`/superadmin/alumni/{id}`)**: Memeriksa tab detail profil lengkap, lembar audit jawaban kuesioner universitas, kuesioner prodi, kuesioner evaluasi atasan, serta tab Audit & Trace Scraping LinkedIn.
   - **Tautan Terarah ke Profil LinkedIn**: Pada banner audit LinkedIn, Superadmin dapat mengklik logo LinkedIn atau tombol "Buka Menu LinkedIn Sync &rarr;". Tautan ini membawa parameter query `alumni_id={id}&search={nim}` yang langsung mengarahkan dan memfokuskan baris alumni tersebut di halaman LinkedIn Sync.
7. **Sinkronisasi LinkedIn Terpusat (`/superadmin/linkedin-sync`)**:
   - **Deteksi Otomatis & Deep Linking**: Jika halaman diakses dari Detail Alumni (`alumni_id`), sistem otomatis memilih tab tahun kelulusan alumni terkait, menerapkan filter teks, memberi sorotan cincin emerald (*pulse badge* "Profil Dipilih"), dan menggulirkan posisi layar (*auto-scroll*) tepat ke baris alumni tersebut.
   - **Aksi Cepat Ikon Mata**: Pada setiap baris data alumni (baik mode Apify maupun Official Provider), tersedia tombol aksi dengan ikon mata (*Lihat Detail Alumni*) untuk membuka halaman `/superadmin/alumni/{id}` secara instan.
   - **Sinkronisasi Satuan & Staging Review**: Menjalankan ekstraksi data LinkedIn via Apify atau API resmi, meninjau perbandingan data staging di modal review, menyetujui (*Approve*) data agar tersimpan ke tabel `biodata` (termasuk foto fisik, posisi jabatan, perusahaan, skills, dan experience), atau menolak (*Reject*).
   - **Sinkronisasi Batch**: Memproses antrean sinkronisasi secara massal untuk mode Official Provider.
8. **Manajemen Akun Terpadu (`/superadmin/manajemen-akun`)**:
   - Mengelola akun seluruh peran: Admin Fakultas, Admin Prodi, dan Akun Alumni.
   - Mereset password akun ke tanggal lahir default serta mengirimkan email notifikasi.
9. **Log Aktivitas & Audit Trail (`/superadmin/logs`)**:
   - Melihat rekam jejak aktivitas (*action logs*) seluruh pengguna untuk keamanan dan audit sistem.

---

## 6. User Flow: Publik / Tamu (Guest)

Pengunjung umum dapat mengakses halaman depan (Landing Page), formulir Evaluasi Pengguna Lulusan (via tautan token khusus), halaman login, serta fitur pemulihan kata sandi tanpa perlu registrasi akun mandiri.

### Diagram Mermaid: Publik Flow

```mermaid
flowchart TD
    A[Mulai: Akses URL Sistem Tracer Study] --> B{Pilih Rute Akses}

    B -->|Akses URL Utama /| C[Landing Page Beranda]
    C --> C1[Lihat Informasi & Sambutan Tracer Study UKDW]
    C --> C2[Lihat Panduan & Tujuan Pelacakan Lulusan]
    C --> C3[Klik Tombol Masuk / Login]
    C3 --> D[Halaman Login: /login]

    B -->|Buka Tautan Email Atasan| E[Formulir Evaluasi Atasan: /evaluasi-atasan/token]
    E --> E1[Akses Khusus Pengguna Lulusan Berbasis Token Unik]

    B -->|Akses URL /login| D[Halaman Login Terpadu: /login]
    D --> D1{Status Pengguna}
    D1 -->|Ingat Password| D2[Input Username & Password -> Masuk ke Dashboard Sesuai Role]
    D1 -->|Lupa Password| F[Halaman Lupa Sandi: /forgot-password]
    F --> F1[Input Email Terdaftar]
    F1 --> F2[Sistem Mengirim Tautan Reset ke Email]
    F2 --> F3[Buka Tautan Email: /reset-password/token]
    F3 --> F4[Input Password Baru -> Simpan & Redirect ke Login]
    F4 --> D
```

### Rincian Alur Publik / Tamu Sebenarnya:
1. **Landing Page Beranda (`/`)**:
   - Menampilkan profil sistem Tracer Study UKDW, sambutan resmi, informasi urgensi tracer study bagi peningkatan mutu dan akreditasi kampus, serta tautan menuju halaman login.
2. **Evaluasi Atasan Publik Berbasis Token (`/evaluasi-atasan/{token}`)**:
   - Tautan langsung dari email undangan resmi untuk pimpinan tempat alumni bekerja tanpa perlu membuat akun atau login.
3. **Halaman Login Terpadu (`/login`)**:
   - Satu pintu masuk otentikasi yang mengarahkan pengguna secara otomatis ke dashboard perannya masing-masing (Alumni, Admin Prodi, Admin Fakultas, Admin Biro 3, Superadmin).
4. **Lupa & Reset Kata Sandi (`/forgot-password` & `/reset-password/{token}`)**:
   - Alur mandiri untuk memulihkan kata sandi akun melalui email yang terdaftar.

---

## 7. User Flow: Atasan Langsung / Pengguna Lulusan (Public Token-Based Evaluation Flow)

Pimpinan/atasan langsung di perusahaan tempat alumni bekerja dapat memberikan evaluasi kepuasan dan penilaian kinerja lulusan secara aman melalui tautan publik ber-token unik tanpa perlu registrasi atau login akun.

### Diagram Mermaid: Evaluasi Atasan Flow

```mermaid
flowchart TD
    A[Alumni Menyimpan Profil dan Data Atasan] --> S[Sistem Menyiapkan Record Evaluasi]
    S --> T[Alumni atau Admin Memilih Kirim Email ke Atasan]
    T --> B[Sistem Memvalidasi dan Mengirim Undangan]
    B --> C[Atasan Menerima Email dengan Tautan Unik Token]
    C --> D[Atasan Klik Tautan: /evaluasi-atasan/token]
    D --> E{Validasi Token Sistem}
    E -->|Token Tidak Ditemukan| F[Halaman Tautan Tidak Ditemukan]
    E -->|Token Valid| G[Buka Formulir Evaluasi Full Page]

    G --> H[Periksa Data Identitas Alumni Yang Dinilai]
    G --> I[Bagian I: Lengkapi / Perbarui Profil Instansi & Kontak Atasan]
    I --> J[Tersimpan Otomatis ke Master Tabel Perusahaan & Atasan]

    G --> K[Bagian II: Evaluasi Kesiapan & Kinerja Lulusan]
    K --> K1[Nilai Tingkat Kesiapan Alumni dalam Bekerja]
    K --> K2[Nilai Aspek Kinerja Lulusan pada Matriks Likert]
    K --> K3[Tuliskan Catatan Tambahan / Saran Khusus Opsional]

    K1 & K2 & K3 --> L[Klik Tombol: Kirim Respon Evaluasi Kinerja]
    L --> M[Sistem Validasi Kelengkapan Jawaban]
    M -->|Ada Butir Terlewat| N[SweetAlert2: Peringatan Aspek Belum Lengkap]
    N --> K
    M -->|Lengkap| O[Simpan Jawaban ke respon_evaluasi_atasan]
    O --> P[Update evaluasi_atasan: is_submitted = true, submitted_at = now]
    P --> Q[Tampilkan Status Berhasil & Kartu Ucapan Terima Kasih Resmi]
    Q --> R[Selesai]
```

### Rincian Alur Atasan:
1. **Pemicu Undangan**: penyimpanan profil menyiapkan record `evaluasi_atasan` bila data atasan tersedia. Pengiriman email dilakukan melalui aksi terpisah `POST /alumni/kirim-email-atasan` oleh alumni atau admin yang diizinkan. Token dihasilkan sepanjang 40 karakter; kapasitas kolom database 64 karakter.
2. **Akses Formulir Tanpa Hambatan**: Atasan membuka tautan langsung dari email (`/evaluasi-atasan/{token}`). Tidak dibutuhkan proses registrasi atau login akun.
3. **Penyempurnaan Profil Instansi (Bagian I)**: Atasan dapat memverifikasi atau melengkapi nama instansi, alamat, no telp/fax, website, bentuk perusahaan, skala usaha, jumlah pegawai, jumlah alumni UKDW, dan standar gaji pertama. Data perusahaan dan atasan disinkronkan ke tabel master `perusahaan` dan `atasan`.
4. **Penilaian Kesiapan & Kinerja (Bagian II)**:
   - Menilai tingkat kesiapan alumni dalam bekerja (pilihan ganda).
   - Menilai butir aspek kinerja lulusan pada tabel ber-header lengket (*sticky header*) yang memuat nama alumni.
   - Menyampaikan saran/masukan konstruktif untuk universitas.
5. **Konfirmasi & Audit**: Respon tersimpan ke tabel `respon_evaluasi_atasan` dan status berubah menjadi *"Sudah Selesai Diisi"*.

---

## Alur Detail Perusahaan dan Konsistensi Jawaban

Pada daftar Master Data Perusahaan, administrator membuka peninjauan untuk melihat seluruh field master: identitas, lokasi, alamat, kontak, website, sektor, skala, bentuk perusahaan, jumlah pegawai, status verifikasi, pembuat, dan timestamp. Rekomendasi master memiliki tombol informasi yang membuka detail kandidat sebelum konsolidasi.

Pada penyimpanan jawaban tracer, `tracer.question_id` menyimpan ID subpertanyaan dari `ref_subpertanyaan2021`, sementara `kode_pertanyaan` dan teks pertanyaan dipertahankan sebagai snapshot laporan. Sistem mempertahankan answer untuk tampilan teks dan mengisi answer_json sebagai proyeksi terstruktur. F505 (Take Home Pay) selalu disinkronkan dari `biodata.gaji`, termasuk menimpa nilai F505 lama. Data lama yang hanya memiliki answer dinormalisasi tanpa menghapus nilai asli. Request API LinkedIn tetap menerima JSON, sedangkan request Inertia menerima redirect valid dengan flash message.

Pada halaman profil alumni, alur self-service hanya mengirim dan menampilkan data yang diperlukan untuk tracer. NIK, NPWP, KK, NISN, BPJS, email mahasiswa, data pembimbing/penguji, publikasi, dan data orang tua tetap berada di sumber resmi, tetapi tidak dikirim sebagai props halaman alumni. Judul tugas akhir dan tanggal kelulusan tetap dikirim sebagai referensi akademik non-sensitif. Penyimpanan profil parsial tidak mengosongkan field sensitif yang tidak dikirim.

Pada detail admin, hasil evaluasi atasan menampilkan nilai dari kolom \`respon_evaluasi_atasan.nilai\` dan status survei berdasarkan \`evaluasi_atasan.is_submitted\`. Token akses tidak ditampilkan pada detail alumni karena merupakan kredensial tautan survei publik.

## Ringkasan Matriks Akses Peran (RBAC Matrix)

Matriks berikut mencerminkan hak akses aktual berdasarkan middleware peran (`role:superadmin`, `role:admin_biro3`, `role:admin_fakultas`, `role:admin_prodi`, `role:alumni`, dan rute publik) pada aplikasi:

| Modul / Fitur Sistem | Alumni | Admin Prodi | Admin Fakultas | Admin Biro III | Superadmin | Atasan (Token) | Publik |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| **Landing Page & Beranda** (`/`) | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ |
| **Otentikasi Login & Reset Password** | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ |
| **Kelola Profil & Riwayat Kerja Sendiri** | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Isi Kuesioner Tracer Universitas** | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Isi Kuesioner Khusus Prodi** | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Isi Evaluasi Kinerja Lulusan (Token Publik)** | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ |
| **Dashboard Pemantauan Partisipasi** | Status Pribadi | Khusus Prodi | Khusus Fakultas | Universitas (KPI) | Universitas (Audit) | ❌ | ❌ |
| **Direktori & Detail Data Alumni** | ❌ | Khusus Prodi | Khusus Fakultas | Seluruh Kampus | Seluruh Kampus | ❌ | ❌ |
| **Ekspor Rekap Alumni ke Excel** | ❌ | Khusus Prodi | Khusus Fakultas | Seluruh Kampus | Seluruh Kampus | ❌ | ❌ |
| **Kelola Kuesioner Khusus Prodi (Section/Soal)** | ❌ | ✅ (Prodi Sendiri) | ❌ | ❌ | ✅ (Semua Prodi) | ❌ | ❌ |
| **Kelola Kuesioner Universitas & Section** | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |
| **Kelola Soal Evaluasi Atasan** | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |
| **Verifikasi Pengajuan Perusahaan Alumni** | ❌ | Khusus Prodi | Khusus Fakultas | ❌ | Seluruh Kampus | ❌ | ❌ |
| **Sinkronisasi Profil LinkedIn Alumni** | Tidak | Prodi sendiri | Fakultas sendiri | Baca hasil saja | Semua lingkup | Tidak | Tidak |
| **Manajemen Akun Alumni (Reset Sandi Default)** | ❌ | Khusus Prodi | Khusus Fakultas | ❌ | Seluruh Pengguna | ❌ | ❌ |
| **Audit Trail & Log Aktivitas Sistem** | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |

---
*Dokumen ini diperbarui sesuai dengan konfigurasi rute, controller, dan hak akses riil pada basis kode Sistem Tracer Study.*


## Catatan implementasi dan sumber

Revisi 21 Oktober 2026 mengikuti `routes/web.php`, middleware role, controller tiap peran dan service bersama. Admin Prodi/Fakultas juga memiliki modul LinkedIn pada `/prodi/linkedin-sync` dan `/fakultas/linkedin-sync`, termasuk review hasil sesuai lingkup. Apify hanya mendukung sinkronisasi satuan; batch ditolak. Master akademik ditampilkan sebagai referensi, bukan field bebas edit.

Token evaluasi tidak mempunyai aturan kedaluwarsa otomatis pada controller yang dibaca. `is_submitted` dicatat dan dikirim ke antarmuka, tetapi handler POST belum menolak pengiriman ulang berdasarkan flag tersebut. Dokumen tidak mengklaim penguncian server yang belum ada.

Lihat [DFD dan berkas draw.io](dfd.md) untuk perpindahan data; urutan klik di dokumen ini bukan DFD.
