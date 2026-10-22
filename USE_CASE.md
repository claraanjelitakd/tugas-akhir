# Dokumen Spesifikasi Use Case Sistem Tracer Study (SERU - UKDW)

Dokumen ini merupakan spesifikasi lengkap Use Case (*Use Case Specification*) untuk sistem informasi **Tracer Study / SERU (Sistem Ekosistem Rekam Jejak Alumni)** pada **Universitas Kristen Duta Wacana (UKDW)**. Seluruh aktor, use case, alur kejadian (*flow of events*), prakondisi, pascakondisi, dan relasi disusun secara akurat berdasarkan arsitektur, rute, pengendali (*controller*), model, dan basis data aktual yang aktif pada aplikasi.

Sesuai prinsip pemodelan UML standar, proses bisnis yang identik antar-peran (seperti **Sinkronisasi Profil LinkedIn**, **Verifikasi Perusahaan Baru**, **Manajemen Akun & Reset Kata Sandi**, serta **Direktori Alumni**) dikonsolidasikan ke dalam **1 Use Case terpadu** dengan pembagian wewenang data (*data authorization scope*) yang terdefinisi secara presisi, serta dipresentasikan dalam **1 diagram proses tunggal yang utuh (tanpa terpisah)**.

---

> Revisi 21 Oktober 2026: dicocokkan dengan rute dan handler. Publik/atasan bukan role akun. Penyimpanan profil dan pengiriman undangan adalah dua aksi berbeda. Biro III tidak menjalankan sinkronisasi LinkedIn. Diagram konteks dan DFD tersedia di [docs/dfd.md](docs/dfd.md).

> Revisi 22 Oktober 2026: UC-15 tetap satu use case verifikasi perusahaan untuk Superadmin, Admin Fakultas, dan Admin Prodi. Ketiganya memakai tampilan dan tindakan yang sama, tetapi cakupan data dibatasi oleh role: seluruh universitas, prodi dalam fakultas, atau prodi pengguna. Pengaturan kuesioner per prodi juga memakai halaman bersama dengan batas daftar prodi yang sama.

## 1. Landasan Teori & Komponen UML Use Case

Berdasarkan standar Unified Modeling Language (UML), use case digunakan untuk memodelkan interaksi antara pengguna eksternal (*actor*) dengan fungsionalitas di dalam batas sistem (*system boundary*).

### Tabel 1.1 Daftar Diagram UML Terkait
| No | Diagram UML | Tujuan (*Purpose of the UML Diagram*) |
| :---: | :--- | :--- |
| 1 | **UML Use Case Diagram** | Memvisualisasikan fungsionalitas bisnis diskret sistem dari perspektif aktor eksternal serta menggambarkan batasan sistem (*system boundary*). |
| 2 | **UML Component Diagram** | Menunjukkan hubungan, ketergantungan (*dependencies*), dan organisasi antarkomponen perangkat lunak dalam sistem (seperti Controller, Service, Middleware, Provider LinkedIn/API, dan Database). |
| 3 | **UML Deployment Diagram** | Menggambarkan topologi perangkat keras dan lingkungan *runtime* tempat komponen perangkat lunak dijalankan (Web Server Nginx/Apache, PHP 8.3 Runtime, MariaDB Database Server, dan Layanan Cloud). |

### Tabel 1.2 Komponen UML Use Case Diagram
| No | Nama Komponen | Notasi UML | Tujuan / Fungsi (*Purpose*) |
| :---: | :--- | :---: | :--- |
| 1 | **System Boundary** | Kotak Persegi Panjang (*Rectangle Box*) | • Mewakili batas dan cakupan (*scope*) dari sistem aplikasi Tracer Study.<br>• Merangkum seluruh fungsionalitas diskret yang disediakan oleh sistem.<br>• Memisahkan lingkungan internal sistem dari aktor eksternal. |
| 2 | **Actors** | *Stick Figure* | • Pengguna atau entitas luar yang berinteraksi dengan sistem.<br>• Aktor dapat berupa manusia (Alumni, Admin, Pengguna Lulusan), organisasi, maupun sistem eksternal (LinkedIn Apify Provider).<br>• Berinteraksi langsung dengan satu atau lebih use case melalui asosiasi. |
| 3 | **Usecases** | Elips / Oval | • Representasi visual dari fungsionalitas bisnis diskret dalam sistem.<br>• Menjamin setiap proses bisnis terdefinisi secara independen, terukur, dan bermakna bagi pengguna.<br>• Menjelaskan urutan tindakan yang menghasilkan nilai bagi aktor. |

### Tabel 1.3 Relasi Use Case UML (*Usecase Relationships*)
| No | Relasi Use Case | Notasi UML | Fungsionalitas & Penjelasan |
| :---: | :--- | :---: | :--- |
| 1 | **Association** | Garis Lurus Padat (*Solid Line*) | Menghubungkan aktor dengan use case yang diaksesnya tanpa arah spesifik. |
| 2 | **Directed Association** | Garis Panah Padat (*Solid Directed Line*) | Menunjukkan hubungan satu arah di mana aktor secara eksplisit memicu atau mengendalikan jalannya use case. |
| 3 | **Include** | Garis Putus-putus Berpanah dengan stereotipe `<<include>>` | Menunjukkan bahwa eksekusi use case dasar secara mutlak menyertakan fungsionalitas dari use case lain yang dituju (*mandatory inclusion*). |
| 4 | **Extend** | Garis Putus-putus Berpanah dengan stereotipe `<<extend>>` | Menunjukkan perluasan opsional di mana use case tambahan menyisipkan perilakunya ke use case dasar hanya jika kondisi tertentu terpenuhi (*optional branch*). |
| 5 | **Generalization** | Garis Panah Kosong / Segitiga (*Hollow Arrow*) | Hubungan hierarki antara use case induk (*parent*) dan satu atau lebih use case anak (*child*), di mana child mewarisi struktur dan menambahkan perilaku spesifik. |
| 6 | **Dependency** | Garis Putus-putus Berpanah (*Dashed Arrow*) | Menunjukkan dependensi fungsional di mana perubahan atau keberadaan suatu use case bergantung pada use case lain. |

---

## 2. Identifikasi Aktor Sistem (*Actors Identification*)

Sistem Tracer Study UKDW melibatkan 7 (tujuh) kategori aktor riil:

1. **Publik / Tamu (Guest)**
   Pengunjung umum tanpa autentikasi yang dapat mengakses halaman utama (*Landing Page*), membaca informasi tracer study, dan mengakses halaman login.
2. **Alumni / Lulusan**
   Mahasiswa UKDW yang telah lulus yudisium dan terdaftar di sistem. Bertugas memutakhirkan biodata, riwayat karir, mendaftarkan data instansi/atasan, serta mengisi kuesioner tracer study universitas dan program studi.
3. **Pengguna Lulusan / Atasan Langsung (Employer/Supervisor)**
   Pimpinan atau atasan langsung di tempat alumni bekerja. Mengisi instrumen survei evaluasi kinerja lulusan secara aman melalui tautan khusus ber-token 40-karakter unik tanpa perlu proses pendaftaran akun.
4. **Admin Program Studi (Admin Prodi)**
   Pengelola akademik pada masing-masing Program Studi. Bertugas mengelola instrumen kuesioner khusus prodi, memantau capaian alumni prodi, memverifikasi usulan perusahaan dari alumni prodi, mengelola akun mahasiswa se-prodi, dan menjalankan sinkronisasi profil LinkedIn untuk alumni prodinya.
5. **Admin Fakultas**
   Pengelola tingkat Dekanat / Gugus Kendali Mutu Fakultas. Bertugas memantau statistik kelengkapan tracer study lintas program studi dalam fakultas, mengaudit direktori alumni fakultas, memverifikasi perusahaan se-fakultas, mengelola akun mahasiswa tingkat fakultas, dan menjalankan sinkronisasi profil LinkedIn untuk alumni se-fakultas.
6. **Admin Biro III (Kemahasiswaan, Alumni & Pengembangan Karir)**
   Pengelola eksekutif universitas. Memantau KPI partisipasi dan *response rate* kampus, mengaudit data alumni se-universitas, mengekspor laporan, serta meninjau riwayat LinkedIn pada detail profil alumni tanpa menjalankan sinkronisasi.
7. **Superadmin**
   Administrator sistem teknis tertinggi. Mengendalikan instrumen kuesioner universitas (Kemdikbudristek), kuesioner prodi, kuesioner evaluasi atasan, verifikasi perusahaan seluruh universitas, sinkronisasi massal (*batch*) LinkedIn se-kampus, manajemen akun seluruh peran, dan audit *log activities*.

---

## 3. Diagram UML Use Case Sistem

### 3.1 Rujukan diagram

Representasi Mermaid di bawah adalah peta fungsi untuk pembacaan. Render gambar use case lama belum mencerminkan koreksi akses Biro III dan tidak dipakai sebagai spesifikasi. Untuk diagram konteks dan aliran data yang bisa diedit gunakan [berkas draw.io](docs/tracer-study-dfd.drawio). Diagram use case dan DFD memiliki tujuan serta simbol berbeda.

### 3.2 Diagram Use Case Interaktif (Mermaid Code Representation)

Diagram proses interaktif berikut memodelkan seluruh fungsionalitas sistem dalam **1 diagram alur tunggal**:

```mermaid
flowchart LR
    %% Actors
    subgraph ActorsLeft [Aktor Eksternal / Pengguna Umum]
        A_Guest["Publik / Tamu (Guest)"]
        A_Alumni["Alumni / Lulusan"]
        A_Atasan["Atasan Langsung (Employer)"]
    end

    subgraph ActorsRight [Aktor Administrator Sistem]
        A_Prodi["Admin Program Studi"]
        A_Fakultas["Admin Fakultas"]
        A_Biro3["Admin Biro III"]
        A_Super["Superadmin"]
    end

    %% System Boundary
    subgraph SystemBoundary ["System Boundary: Sistem Tracer Study UKDW (SERU)"]
        %% Public & Auth
        UC01(["UC-01: Mengakses Beranda Publik"])
        UC02(["UC-02: Melakukan Autentikasi (Login)"])
        UC03(["UC-03: Memulihkan Kata Sandi (Reset Password)"])
        UC04(["UC-04: Mengubah Kata Sandi Wajib"])
        UC05(["UC-05: Mengakhiri Sesi (Logout)"])

        %% Alumni
        UC06(["UC-06: Mengakses Dashboard Alumni"])
        UC07(["UC-07: Mengelola Profil & Pekerjaan Alumni"])
        UC08(["UC-08: Mendaftarkan Instansi Perusahaan Baru"])
        UC09(["UC-09: Mengisi Kuesioner Universitas (Kemdikbud)"])
        UC10(["UC-10: Mengisi Kuesioner Khusus Prodi"])

        %% Employer
        UC11(["UC-11: Mengisi Evaluasi Kinerja Lulusan (Token-Based)"])

        %% Admin Shared Modules (Unified 1 Use Case untuk Multi-Role)
        UC12(["UC-12: Mengakses Dashboard Monitoring & Analisis"])
        UC13(["UC-13: Mengelola Direktori Alumni & Audit Detail"])
        UC14(["UC-14: Mengekspor Rekapitulasi Data ke Excel"])
        UC15(["UC-15: Memverifikasi Perusahaan Baru (3 Role)"])
        UC16(["UC-16: Manajemen Akun & Reset Password (3 Role)"])
        UC17(["UC-17: Sinkronisasi LinkedIn Alumni (3 Role Utama)"])

        %% Configuration & Audit
        UC18(["UC-18: Mengelola Section Kuesioner Prodi"])
        UC19(["UC-19: Mengelola Soal & Opsi Kuesioner Prodi"])
        UC20(["UC-20: Mengelola Kuesioner Universitas & Branching"])
        UC21(["UC-21: Mengelola Instrumen Evaluasi Atasan"])
        UC22(["UC-22: Memantau Log Aktivitas & Audit Trail"])
    end

    %% Relationships - Guest
    A_Guest --> UC01
    A_Guest --> UC02

    %% Relationships - Alumni
    A_Alumni --> UC02
    A_Alumni --> UC05
    A_Alumni --> UC06
    A_Alumni --> UC07
    A_Alumni --> UC09
    A_Alumni --> UC10

    %% Relationships - Employer
    A_Atasan --> UC11

    %% Relationships - Admin Program Studi
    A_Prodi --> UC12
    A_Prodi --> UC13
    A_Prodi --> UC15
    A_Prodi --> UC16
    A_Prodi --> UC17
    A_Prodi --> UC18

    %% Relationships - Admin Fakultas
    A_Fakultas --> UC12
    A_Fakultas --> UC13
    A_Fakultas --> UC15
    A_Fakultas --> UC16
    A_Fakultas --> UC17

    %% Relationships - Admin Biro III
    A_Biro3 --> UC12
    A_Biro3 --> UC13
    A_Biro3 --> UC14

    %% Relationships - Superadmin
    A_Super --> UC12
    A_Super --> UC13
    A_Super --> UC15
    A_Super --> UC16
    A_Super --> UC17
    A_Super --> UC18
    A_Super --> UC20
    A_Super --> UC21
    A_Super --> UC22

    %% Include, Extend & Dependency Relationships
    UC03 -.->|"<<extend>>"| UC02
    UC04 -.->|"<<include>>"| UC02
    UC08 -.->|"<<extend>>"| UC07
    UC14 -.->|"<<extend>>"| UC13
    UC19 -.->|"<<include>>"| UC18
    UC07 -.->|"<<memicu token>>"| UC11
```

---

## 4. Daftar Ringkasan Use Case (*Use Case Master Index*)

Sistem Tracer Study UKDW merangkum **22 Use Case diskret** yang mencakup seluruh alur bisnis aplikasi:

| ID Use Case | Nama Use Case | Aktor Terlibat | Catatan & Relasi UML |
| :--- | :--- | :--- | :--- |
| **UC-01** | Mengakses Beranda Publik (*Landing Page*) | Publik / Tamu, Seluruh Pengguna | Association |
| **UC-02** | Melakukan Autentikasi Pengguna (*Login*) | Alumni, Seluruh Staf Admin | Association |
| **UC-03** | Memulihkan Kata Sandi (*Forgot & Reset Password*) | Alumni, Seluruh Staf Admin | `<<extend>>` ke UC-02 |
| **UC-04** | Mengubah Kata Sandi Bawaan Wajib (*Must Change Password*) | Alumni (Sandi Bawaan Tanggal Lahir) | `<<include>>` dari UC-02 |
| **UC-05** | Mengakhiri Sesi Akun (*Logout*) | Seluruh Pengguna Terautentikasi | Association |
| **UC-06** | Mengakses Dashboard Alumni | Alumni | Association |
| **UC-07** | Mengelola Profil Biodata & Riwayat Karir | Alumni | Memicu Token Evaluasi ke UC-11 |
| **UC-08** | Mendaftarkan Instansi Perusahaan Baru | Alumni | `<<extend>>` ke UC-07 |
| **UC-09** | Mengisi Kuesioner Tracer Study Universitas (Kemdikbud) | Alumni | Association |
| **UC-10** | Mengisi Kuesioner Khusus Program Studi | Alumni | Association |
| **UC-11** | Mengisi Evaluasi Kinerja Lulusan (Token-Based) | Pengguna Lulusan / Atasan Langsung | Dependency dari UC-07 (Tanpa Login) |
| **UC-12** | Mengakses Dashboard Monitoring & Analisis Kinerja | Admin Prodi, Fak, Biro III, Superadmin | Association (Multi-Role Scope) |
| **UC-13** | Mengelola Direktori Alumni & Audit Detail | Admin Prodi, Fak, Biro III, Superadmin | Association (Multi-Role Scope) |
| **UC-14** | Mengekspor Rekapitulasi Data Alumni ke Excel | Admin Prodi, Fak, Biro III, Superadmin | `<<extend>>` ke UC-13 |
| **UC-15** | Memverifikasi Pengajuan Perusahaan Baru | **Admin Prodi, Admin Fakultas, Superadmin** | Association (**1 Proses untuk 3 Role**) |
| **UC-16** | Mengelola Akun Alumni & Reset Sandi Default | **Admin Prodi, Admin Fakultas, Superadmin** | Association (**1 Proses untuk 3 Role**) |
| **UC-17** | Melakukan Sinkronisasi Profil LinkedIn Alumni | **Admin Prodi, Admin Fakultas, Superadmin** | Association (**1 Proses untuk 3 Role Utama**) |
| **UC-18** | Mengelola Bagian (*Section*) Kuesioner Prodi | Admin Program Studi, Superadmin | Association |
| **UC-19** | Mengelola Butir Pertanyaan & Opsi Kuesioner Prodi | Admin Program Studi, Superadmin | `<<include>>` ke UC-18 |
| **UC-20** | Mengelola Kuesioner Universitas, Section & Branching | Superadmin | Association |
| **UC-21** | Mengelola Instrumen Evaluasi Atasan Langsung | Superadmin | Association |
| **UC-22** | Memantau Log Aktivitas & Audit Trail Sistem | Superadmin | Association |

---

## 5. Spesifikasi Rinci Deskripsi Use Case (*Use Case Specifications*)

---

### UC-01. Mengakses Beranda Publik (Landing Page)
- **Usecase ID**: UC-01
- **Usecase Name**: Mengakses Beranda Publik (*Landing Page*)
- **Actors Involved**: Publik / Tamu (Guest), Seluruh Pengguna
- **Description**: Menampilkan profil sistem informasi Tracer Study UKDW (SERU), statistik ringkas, pengumuman urgensi pelacakan jejak alumni bagi akreditasi, serta navigasi menuju halaman login.
- **Preconditions**: Perangkat pengguna terhubung ke jaringan internet dan dapat mengakses alamat URL sistem (`/`).
- **Main Flow (Basic Flow)**:
  1. Pengguna membuka URL utama aplikasi (`GET /`).
  2. Sistem merespons dengan menampilkan *Landing Page* beranda publik yang memuat sambutan, panduan pengisian, dan tombol "Masuk / Login".
  3. Pengguna membaca informasi yang disajikan.
- **Alternate Flow (Alternative Flow)**:
  - Jika pengguna memilih tombol "Masuk / Login", sistem mengarahkan pengguna ke halaman Login (`GET /login`) [Menuju UC-02].
  - Jika pengguna adalah atasan yang membuka tautan dari email, pengguna langsung diarahkan ke form evaluasi [Menuju UC-11].
- **Post Conditions**: Pengguna mendapatkan informasi umum mengenai kegiatan Tracer Study UKDW.

---

### UC-02. Melakukan Autentikasi Pengguna (Login)
- **Usecase ID**: UC-02
- **Usecase Name**: Melakukan Autentikasi Pengguna (*Login*)
- **Actors Involved**: Alumni / Lulusan, Admin Program Studi, Admin Fakultas, Admin Biro III, Superadmin
- **Description**: Memverifikasi identitas pengguna berdasarkan kredensial (Username/NIM dan Kata Sandi) untuk memberikan hak akses sesi ke menu dan modul sesuai perannya (*Role-Based Access Control*).
- **Preconditions**: Pengguna sudah memiliki akun terdaftar di sistem Tracer Study dan membuka halaman `/login`.
- **Main Flow (Basic Flow)**:
  1. Pengguna memasukkan `username` (NIM untuk alumni, NIP/Username untuk admin) dan `password`.
  2. Pengguna menekan tombol "Masuk".
  3. Sistem memvalidasi kredensial terhadap tabel `users`.
  4. Sistem memeriksa flag `must_change_password`:
     - Jika bernilai `false`, sistem mengarahkan pengguna ke Dashboard sesuai peran masing-masing:
       - Alumni $\rightarrow$ `/alumni/dashboard`
       - Admin Prodi $\rightarrow$ `/prodi/dashboard`
       - Admin Fakultas $\rightarrow$ `/fakultas/dashboard`
       - Admin Biro III $\rightarrow$ `/biro3/dashboard`
       - Superadmin $\rightarrow$ `/superadmin/dashboard`
  5. Sesi login pengguna resmi aktif.
- **Alternate Flow (Alternative Flow)**:
  - **A1. Kredensial Tidak Cocok**: Sistem menampilkan pesan kesalahan "Kombinasi username atau password salah" dan pengguna diminta mengisi ulang.
  - **A2. Password Bawaan Pertama Kali (`must_change_password = true`)**: Sistem mengarahkan paksa pengguna ke rute `/change-password` [Menuju UC-04].
  - **A3. Lupa Kata Sandi**: Pengguna mengklik tautan "Lupa Kata Sandi" [Menuju UC-03].
- **Post Conditions**: Pengguna berhasil masuk ke dalam sistem dengan token sesi yang valid dan diarahkan ke antarmuka kerja sesuai perannya.

---

### UC-03. Memulihkan Kata Sandi (Forgot & Reset Password)
- **Usecase ID**: UC-03
- **Usecase Name**: Memulihkan Kata Sandi (*Forgot & Reset Password*)
- **Actors Involved**: Alumni / Lulusan, Seluruh Pengguna Terdaftar
- **Description**: Fasilitas pemulihan kata sandi mandiri melalui pengiriman tautan token acak ke alamat email terdaftar pengguna.
- **Preconditions**: Pengguna berada pada halaman login (`/login`) dan tidak mengingat kata sandinya.
- **Main Flow (Basic Flow)**:
  1. Pengguna mengklik opsi "Lupa Kata Sandi" dan diarahkan ke form `/forgot-password`.
  2. Pengguna memasukkan alamat email terdaftar dan menekan tombol "Kirim Link Reset".
  3. Sistem memeriksa keberadaan email di database `users`.
  4. Sistem menghasilkan token reset acak, menyimpannya di tabel pemulihan sandi, dan mengirimkan email resmi berisi URL tautan reset (`/reset-password/{token}`).
  5. Pengguna membuka email, mengklik tautan, dan mengisi formulir kata sandi baru.
  6. Sistem memverifikasi token, mengupdate sandi terenkripsi di database, dan menghapus token reset yang telah dipakai.
  7. Sistem mengarahkan pengguna kembali ke halaman Login dengan pesan sukses.
- **Alternate Flow (Alternative Flow)**:
  - **A1. Email Tidak Ditemukan**: Sistem menampilkan notifikasi bahwa alamat email tidak terdaftar di sistem.
  - **A2. Token Kedaluwarsa / Tidak Valid**: Sistem menolak akses form reset sandi dan meminta pengguna mengirimkan ulang permintaan pemulihan.
- **Post Conditions**: Kata sandi akun berhasil diperbarui dan pengguna dapat login dengan kata sandi yang baru.

---

### UC-04. Mengubah Kata Sandi Bawaan Wajib (Force Change Password)
- **Usecase ID**: UC-04
- **Usecase Name**: Mengubah Kata Sandi Bawaan Wajib (*Must Change Password*)
- **Actors Involved**: Alumni / Lulusan
- **Description**: Memaksa alumni yang baru pertama kali login menggunakan password bawaan tanggal lahir (`DDMMYYYY`) atau setelah direset oleh admin untuk menentukan kata sandi pribadi yang aman sebelum dapat mengakses fitur aplikasi.
- **Preconditions**: Alumni berhasil login, namun atribut `must_change_password` pada akun bernilai `true`.
- **Main Flow (Basic Flow)**:
  1. Sistem mencegat akses alumni via middleware `must_change_password` dan mengarahkan ke form `/change-password`.
  2. Alumni memasukkan kata sandi saat ini (*current password*), kata sandi baru (*minimal 8 karakter*), dan konfirmasi kata sandi baru.
  3. Sistem memvalidasi kesesuaian input.
  4. Sistem memperbarui hash password baru di tabel `users` dan mengubah flag `must_change_password` menjadi `false`.
  5. Sistem mengarahkan alumni ke halaman `/alumni/dashboard`.
- **Alternate Flow (Alternative Flow)**:
  - Jika kata sandi saat ini keliru atau konfirmasi kata sandi tidak cocok, sistem menampilkan pesan peringatan dan formulir tetap terbuka.
- **Post Conditions**: Kata sandi akun alumni berubah menjadi kata sandi pribadi yang aman dan batasan middleware dihilangkan.

---

### UC-05. Mengakhiri Sesi Akun (Logout)
- **Usecase ID**: UC-05
- **Usecase Name**: Mengakhiri Sesi Akun (*Logout*)
- **Actors Involved**: Seluruh Pengguna Terautentikasi (Alumni, Admin Prodi, Fak, Biro III, Superadmin)
- **Description**: Menutup sesi autentikasi pengguna secara aman, membatalkan token sesi, meregenerasi CSRF token, dan mengembalikan pengguna ke halaman beranda publik.
- **Preconditions**: Pengguna dalam keadaan login aktif.
- **Main Flow (Basic Flow)**:
  1. Pengguna menekan tombol "Keluar / Logout" pada menu navigasi.
  2. Sistem menerima permintaan (`POST` / `GET /logout`).
  3. Sistem menghapus data sesi pengguna (`Auth::logout()`), membatalkan sesi (`$request->session()->invalidate()`), dan meregenerasi token CSRF (`$request->session()->regenerateToken()`).
  4. Sistem mengarahkan pengguna kembali ke halaman utama publik (`/`).
- **Alternate Flow (Alternative Flow)**: Tidak ada alur alternatif.
- **Post Conditions**: Sesi pengguna berakhir sepenuhnya dan hak akses ke menu internal dicabut.

---

### UC-06. Mengakses Dashboard Alumni
- **Usecase ID**: UC-06
- **Usecase Name**: Mengakses Dashboard Alumni
- **Actors Involved**: Alumni / Lulusan
- **Description**: Menyajikan ringkasan status kelengkapan pengisian tracer study alumni: persentase pengisian kuesioner universitas, kuesioner program studi, riwayat pekerjaan, status evaluasi atasan, serta tautan cepat ke modul yang belum lengkap.
- **Preconditions**: Alumni telah login dan menyelesaikan perubahan kata sandi bawaan (`/alumni/dashboard`).
- **Main Flow (Basic Flow)**:
  1. Alumni membuka dashboard.
  2. Sistem menghitung status kelengkapan data via `KelengkapanTracerService`:
     - Kuesioner Universitas (persentase & status selesai/belum).
     - Kuesioner Prodi (status selesai/belum).
     - Kelengkapan Profil & Data Karir.
     - Status konfirmasi atasan (apakah sudah mengisi evaluasi).
  3. Sistem menampilkan kartu status dan tombol aksi cepat (*call-to-action*) menuju pengisian formulir.
- **Alternate Flow (Alternative Flow)**:
  - Jika seluruh kuesioner telah lengkap 100%, sistem menampilkan badge "Tracer Study Selesai" dan pesan apresiasi.
- **Post Conditions**: Alumni memahami status kontribusinya pada tracer study kampus.

---

### UC-07. Mengelola Profil Biodata & Riwayat Karir
- **Usecase ID**: UC-07
- **Usecase Name**: Mengelola Profil Biodata & Riwayat Karir
- **Actors Involved**: Alumni / Lulusan
- **Description**: Memutakhirkan data identitas pribadi (email aktif, nomor HP, kontak darurat, alamat domisili), riwayat jabatan, instansi tempat bekerja, profil media sosial/LinkedIn, serta memasukkan nama dan email atasan langsung.
- **Preconditions**: Alumni berada pada menu `/alumni/profile`.
- **Main Flow (Basic Flow)**:
  1. Sistem menampilkan form profil biodata yang memuat data akademik (NIM, Nama, IPK, Tahun Lulus - *readonly*) dan data kontak/karir (*editable*).
  2. Alumni mengisi kontak terkini, tautan profil LinkedIn, status pekerjaan saat ini, memilih perusahaan dari daftar master, posisi jabatan, serta mengisi nama & email atasan langsung.
  3. Alumni menekan tombol "Simpan Perubahan" (`POST /alumni/profile`).
  4. Sistem memvalidasi kelengkapan data.
  5. Sistem memperbarui tabel `biodata` dan riwayat pekerjaan.
  6. **Pemicu Evaluasi Atasan**: Jika email atasan diisi dan instansi terdaftar, sistem menyiapkan record evaluasi dan token 40 karakter pada tabel `evaluasi_atasan`. Email baru dikirim melalui aksi terpisah `POST /alumni/kirim-email-atasan`; atasan kemudian mengakses UC-11.
  7. Sistem menampilkan notifikasi sukses pembaruan profil.
- **Alternate Flow (Alternative Flow)**:
  - **A1. Perusahaan Belum Ada di Master**: Alumni memilih opsi "Tambah Perusahaan Baru" [Menuju UC-08].
- **Post Conditions**: Data profil dan pekerjaan alumni terbarui di basis data; token evaluasi atasan tercipta.

---

### UC-08. Mendaftarkan Instansi Perusahaan Baru

Endpoint bersama `POST /alumni/company` menerima Alumni, Admin Prodi, Admin Fakultas, Admin Biro III dan Superadmin. Alur berikut menjelaskan kasus alumni; pencatatan perusahaan oleh admin tidak otomatis menghubungkan biodata admin. Verifikasi tetap mengikuti UC-15.
- **Usecase ID**: UC-08
- **Usecase Name**: Mendaftarkan Instansi Perusahaan Baru
- **Actors Involved**: Alumni / Lulusan
- **Description**: Fasilitas bagi alumni untuk mengusulkan nama instansi/perusahaan tempat ia bekerja jika belum tersedia pada basis data master perusahaan kampus.
- **Preconditions**: Alumni sedang membuka form profil pekerjaan dan perusahaan tidak ditemukan pada daftar pilihan.
- **Main Flow (Basic Flow)**:
  1. Alumni menekan tombol "Tambah Perusahaan Baru".
  2. Sistem menampilkan modal pop-up pendaftaran perusahaan baru.
  3. Alumni mengisi: Nama Perusahaan, Sektor Usaha (BUMN, Swasta, Pendidikan, Wirausaha, dll), Alamat, Kota/Kabupaten, Provinsi, dan Negara.
  4. Alumni menekan tombol "Kirim Pengajuan" (`POST /alumni/company`).
  5. Sistem menyimpan entitas baru ke tabel `perusahaan` dengan status `Menunggu Verifikasi` dan mengaitkan `created_by_prodi_id` sesuai prodi alumni.
  6. Sistem otomatis memilih perusahaan tersebut pada form profil alumni.
  7. Sistem menampilkan notifikasi bahwa pengajuan instansi berhasil dan akan diverifikasi oleh admin.
- **Alternate Flow (Alternative Flow)**:
  - Jika nama instansi mirip dengan yang sudah ada di master, sistem menyarankan nama instansi yang sudah terverifikasi.
- **Post Conditions**: Entitas perusahaan baru tercipta dengan status `Menunggu Verifikasi` dan masuk ke antrean verifikasi admin [UC-15].

---

### UC-09. Mengisi Kuesioner Tracer Study Universitas (Kemdikbudristek)
- **Usecase ID**: UC-09
- **Usecase Name**: Mengisi Kuesioner Tracer Study Universitas (Kemdikbud)
- **Actors Involved**: Alumni / Lulusan
- **Description**: Menjawab rangkaian butir pertanyaan kuesioner pelacakan jejak alumni standar nasional (Kemdikbudristek) dengan mekanisme otomatis percabangan alur logika (*dynamic branching logic*).
- **Preconditions**: Alumni membuka rute `/alumni/kuesioner`.
- **Main Flow (Basic Flow)**:
  1. Sistem memuat kuesioner universitas aktif beserta seksi-seksi pertanyaan (`kelompok_pertanyaan`).
  2. Alumni menjawab pertanyaan per bagian (pilihan tunggal, pilihan ganda, isian teks, angka gaji/penghasilan, waktu tunggu kerja, dan skala keselarasan).
  3. **Logika Branching**: Jika alumni memilih opsi tertentu (misal: "Belum Bekerja"), sistem secara dinamis melompati (*jump to*) pertanyaan mengenai detail instansi pekerjaan sesuai konfigurasi `jump_to`.
  4. Alumni menekan tombol "Simpan Jawaban" (`POST /alumni/kuesioner`).
  5. Sistem memvalidasi isian wajib (*is_required*), menyimpan jawaban ke tabel `jawaban`, dan memperbarui status kelengkapan kuesioner nasional.
  6. Sistem menampilkan pesan terima kasih dan memperbarui persentase dashboard alumni.
- **Alternate Flow (Alternative Flow)**:
  - Jika terdapat pertanyaan wajib yang terlewat, sistem memfokuskan layar ke butir pertanyaan bersangkutan dan menampilkan peringatan validasi.
- **Post Conditions**: Respon kuesioner nasional tersimpan secara permanen di database dan siap diolah untuk pelaporan Dikti.

---

### UC-10. Mengisi Kuesioner Khusus Program Studi
- **Usecase ID**: UC-10
- **Usecase Name**: Mengisi Kuesioner Khusus Program Studi
- **Actors Involved**: Alumni / Lulusan
- **Description**: Menjawab butir-butir instrumen survei mandiri yang dirancang khusus oleh program studi asal alumni untuk mengukur relevansi kurikulum, fasilitas lab, dan kompetensi spesifik prodi.
- **Preconditions**: Program studi alumni memiliki paket kuesioner aktif (`/alumni/kuesioner-prodi`).
- **Main Flow (Basic Flow)**:
  1. Sistem mengambil konfigurasi bagian (`prodi_question_section`) dan butir pertanyaan (`prodi_question`) khusus untuk `prodi_id` alumni yang login.
  2. Alumni mengisi respon kuesioner (pilihan ganda, skala likert kepuasan, masukan esai kurikulum).
  3. Alumni menekan tombol "Kirim Kuesioner Prodi" (`POST /alumni/kuesioner-prodi`).
  4. Sistem menyimpan respon ke tabel `prodi_response` dan detail pilihan ke `prodi_response_answer`.
  5. Sistem menandai status penyelesaian kuesioner prodi alumni menjadi selesai.
- **Alternate Flow (Alternative Flow)**:
  - Jika prodi alumni belum merilis pertanyaan kuesioner khusus, sistem menampilkan notifikasi ramah bahwa kuesioner prodi belum tersedia dan langsung menandai statusnya valid.
- **Post Conditions**: Respon survei prodi tersimpan rapi untuk kebutuhan evaluasi kurikulum dan akreditasi prodi (LAM Infokom, LAM Teknik, dll).

---

### UC-11. Mengisi Evaluasi Kinerja Lulusan (Token-Based Employer Survey)
- **Usecase ID**: UC-11
- **Usecase Name**: Mengisi Evaluasi Kinerja Lulusan (*Token-Based*)
- **Actors Involved**: Pengguna Lulusan / Atasan Langsung (Employer/Supervisor)
- **Description**: Pengisian instrumen survei evaluasi kepuasan pengguna lulusan oleh pimpinan/atasan tempat alumni bekerja melalui tautan token acak 40-karakter tanpa perlu melakukan registrasi akun atau login ke sistem.
- **Preconditions**: Atasan menerima email resmi berisi URL ber-token unik (`/evaluasi-atasan/{token}`).
- **Main Flow (Basic Flow)**:
  1. Atasan mengklik tautan evaluasi dari email.
  2. Sistem memvalidasi token terhadap tabel `evaluasi_atasan`:
     - Token ditemukan pada `evaluasi_atasan`; status `is_submitted` dikirim ke halaman. Controller tidak menerapkan pemeriksaan masa berlaku token maupun penolakan submit ulang berdasarkan flag ini.
  3. Sistem menampilkan formulir evaluasi yang menyajikan nama alumni, instansi, serta butir penilaian kinerja (etika, keahlian bidang ilmu, komunikasi, kerjasama tim, kepemimpinan, dan pengembangan diri).
  4. Atasan mengisi penilaian skala likert serta masukan kualitatif.
  5. Atasan menekan tombol "Kirim Evaluasi" (`POST /evaluasi-atasan/{token}`).
  6. Sistem menyimpan jawaban ke tabel `respon_evaluasi_atasan`, mengubah `is_submitted` menjadi `true`, dan mencatat stempel waktu pengisian.
  7. Sistem menampilkan ucapan terima kasih resmi dari Rektorat UKDW.
- **Alternate Flow (Alternative Flow)**:
  - **A1. Token Tidak Ditemukan**: GET menampilkan `Public/EvaluasiAtasanNotFound`; POST menggunakan pencarian `firstOrFail`.
  - **A2. Formulir Sudah Pernah Diisi**: Sistem mengirim status `is_submitted` dan jawaban yang tersimpan ke halaman. Status tampilan tidak sama dengan penolakan submit ulang oleh server.
- **Post Conditions**: Hasil evaluasi tersimpan dan `is_submitted` serta `submitted_at` diperbarui. Penguncian submit ulang di server tidak diklaim oleh dokumen ini.

---

### UC-12. Mengakses Dashboard Monitoring & Analisis Kinerja (Multi-Role Scope)
- **Usecase ID**: UC-12
- **Usecase Name**: Mengakses Dashboard Monitoring & Analisis Kinerja
- **Actors Involved**: Admin Program Studi, Admin Fakultas, Admin Biro III, Superadmin
- **Description**: Pusat analisis data visual dan pemantauan Indikator Kinerja Utama (KPI) Tracer Study kampus, menampilkan metrik keterisian responden, rasio respon (*response rate*), status alumni tersinkron LinkedIn, serta perbandingan kinerja antar-unit sesuai hak akses peran.
- **Scope & Role Authorization**:
  - **Admin Prodi** (`/prodi/dashboard`): Menampilkan total alumni prodi, jumlah alumni mengisi, persentase kelengkapan prodi, dan daftar kuesioner prodi aktif.
  - **Admin Fakultas** (`/fakultas/dashboard`): Menampilkan total agregat alumni fakultas, persentase keterisian fakultas, dan tabel komparasi capaian partisipasi per program studi dalam fakultas.
  - **Admin Biro III** (`/biro3/dashboard`): Menampilkan KPI Tracer Study universitas, total responden unik nasional, response rate kampus, dan status tautan LinkedIn alumni.
  - **Superadmin** (`/superadmin/dashboard`): Menampilkan ringkasan global universitas, status keaktifan paket kuesioner Kemdikbud, antrean verifikasi perusahaan se-kampus, dan log audit aktivitas terbaru.
- **Preconditions**: Aktor telah terautentikasi dan memiliki hak akses peran administrator terkait.
- **Main Flow (Basic Flow)**:
  1. Aktor mengakses rute dashboard perannya.
  2. Pengendali dashboard menghitung metrik agregat data secara otomatis dari database dengan membatasi cakupan filter peran (*role scoping*).
  3. Sistem menyajikan kartu metrik, grafik perkembangan, dan tabel perbandingan capaian.
- **Alternate Flow (Alternative Flow)**:
  - Pengguna dapat mengklik kartu metrik untuk berpindah langsung ke direktori alumni atau antrean verifikasi perusahaan.
- **Post Conditions**: Administrator memperoleh visualisasi analitik kinerja pelacakan alumni yang akurat dan *real-time*.

---

### UC-13. Mengelola Direktori Alumni & Audit Detail (Multi-Role Scope)
- **Usecase ID**: UC-13
- **Usecase Name**: Mengelola Direktori Alumni & Audit Detail
- **Actors Involved**: Admin Program Studi, Admin Fakultas, Admin Biro III, Superadmin
- **Description**: Menelusuri seluruh data alumni, melakukan pencarian dan penyaringan berjenjang (tahun kelulusan, semester, target kelulusan, prodi, status kuesioner, status sinkronisasi LinkedIn), meninjau scorecard kelengkapan 4 pilar (Univ, Prodi, Profil, Atasan), memantau ringkasan status LinkedIn alumni beserta reviewer dan timestamp terakhir, meninjau rincian biodata, riwayat karir (keahlian & pengalaman), lembar respon kuesioner nasional, respon kuesioner prodi, kuesioner atasan, serta navigasi terarah (*deep link*) ke tab audit trace LinkedIn.
- **Scope & Role Authorization**:
  - **Admin Prodi** (`/prodi/alumni`): Terbatas pada alumni program studinya sendiri.
  - **Admin Fakultas** (`/fakultas/alumni`): Alumni seluruh prodi dalam naungan fakultasnya (disertai dropdown filter prodi fakultas).
  - **Admin Biro III** (`/biro3/alumni`): Seluruh alumni universitas lintas fakultas dan prodi.
  - **Superadmin** (`/superadmin/alumni`): Akses audit global seluruh alumni universitas dilengkapi deep link audit scraping LinkedIn, kartu statistik LinkedIn, filter status LinkedIn, dan ekspor ZIP Excel per prodi.
- **Preconditions**: Aktor membuka modul direktori alumni sesuai perannya.
- **Main Flow (Basic Flow)**:
  1. Sistem menyajikan daftar tabel alumni sesuai batasan hak akses peran dengan fit 100% viewport (bebas horizontal scrollbar berlebih).
  2. Sistem menampilkan 5 Kartu Statistik Interaktif di bagian atas:
     - Total Mahasiswa
     - Kuesioner Selesai (100%)
     - Belum Selesai (Perlu tindak lanjut)
     - Rasio Penyelesaian (%)
     - **Tersinkron LinkedIn** (Menampilkan total alumni tersinkron dan rasio kepatuhan, dapat diklik untuk memfilter langsung alumni yang tersinkron).
  3. Aktor menerapkan filter pencarian:
     - Pencarian Kata Kunci (Nama, NIM, Email)
     - Tahun Kelulusan (Default ke tahun terbaru)
     - Target Kelulusan (Semester + Tahun)
     - Semester Kelulusan (Gasal / Genap)
     - Status Kuesioner (Semua, Selesai 100%, Belum Selesai)
     - **Status LinkedIn** (*Semua LinkedIn, Sudah Disinkronkan, Pending Review, Belum Disinkron, Ditolak*)
     - Program Studi
  4. Sistem menyajikan data tabel dengan kolom:
     - **Mahasiswa / Alumni**: Nama, NIM, dan Email kontak resmi.
     - **Program Studi & Fakultas**.
     - **Periode / Target Kelulusan**: Semester, tahun lulus, dan status kelulusan.
     - **Status Kelengkapan (Scorecard 2x2 Formal)**: Menampilkan Univ %, Prodi %, Profil (Lengkap/Belum), dan Atasan (Diisi/Menunggu/Belum Ada) dalam format grid simetris tanpa emoticon.
     - **Sinkronisasi LinkedIn**: Menampilkan badge status sinkronisasi, tanggal dan waktu terakhir disinkronkan, nama reviewer (admin pemeriksa), posisi pekerjaan terkini, serta link cepat ke rincian tab audit.
     - **Aksi Terpadu**: Tombol Detail Profil, WhatsApp pengingat, kirim email kuesioner, dan tautan langsung ke tab audit LinkedIn (`?tab=linkedin`).
  5. Aktor mengklik salah satu nama alumni atau tombol detail untuk membuka halaman detail audit (`{base_route}/alumni/{id}`).
  6. Sistem menampilkan 5 Tab Utama: Profil Biodata, Riwayat Karir (`skills` & `experience`), Lembar Jawaban Kuesioner Universitas, Jawaban Kuesioner Prodi, dan **Tab Hasil LinkedIn & Audit Trace Scraping**.
  7. Superadmin, Admin Fakultas, dan Admin Prodi dapat mengoreksi field profil yang diizinkan; Biro III hanya membaca. Aksi pembaruan menggunakan (`POST {base_route}/alumni/{id}/profile`).
  8. **Deep Link ke LinkedIn Tab**: Tombol LinkedIn di direktori alumni mengarahkan langsung ke Tab ke-5 (`/superadmin/alumni/{id}?tab=linkedin`) yang memuat tabel penelusuran audit trace mapping 10-27 baris dan form usulan data LinkedIn.
- **Alternate Flow (Alternative Flow)**:
  - Aktor dapat mengunduh berkas laporan dalam format Excel [UC-14] atau mengunduh arsip ZIP Excel per Program Studi untuk tahun aktif.
- **Post Conditions**: Data direktori alumni terpantau, terverifikasi keabsahannya, dan siap diaudit.

---

### UC-14. Mengekspor Rekapitulasi Data Alumni ke Excel
- **Usecase ID**: UC-14
- **Usecase Name**: Mengekspor Rekapitulasi Data Alumni ke Excel
- **Actors Involved**: Admin Program Studi, Admin Fakultas, Admin Biro III, Superadmin
- **Description**: Mengunduh rekapitulasi data tracer study alumni dalam berkas spreadsheet Excel (`.xlsx`) resmi sesuai batasan wewenang unit kerja untuk keperluan akreditasi prodi/institusi atau pelaporan Dikti.
- **Preconditions**: Aktor berada pada direktori alumni atau detail alumni dan menekan tombol "Ekspor Excel".
- **Main Flow (Basic Flow)**:
  1. Aktor menekan tombol "Ekspor ke Excel" (`GET {base_route}/alumni/export-excel` atau per alumni `{id}/export-excel`).
  2. Sistem melalui `AlumniTracerExcelExporter` menyaring data alumni sesuai cakupan peran:
     - Admin Prodi $\rightarrow$ Rekap khusus prodi bersangkutan.
     - Admin Fakultas $\rightarrow$ Rekap seluruh prodi se-fakultas.
     - Admin Biro III / Superadmin $\rightarrow$ Rekap universitas menyeluruh.
  3. Sistem mengompilasi baris data (data diri, pekerjaan, instansi, detail jawaban kuesioner) ke lembar kerja Excel berformat standar Dikti.
  4. Browser mengunduh berkas `.xls` yang disusun exporter dari HTML, atau ZIP berisi berkas ekspor pada endpoint yang mendukungnya.
- **Alternate Flow (Alternative Flow)**:
  - Jika data kosong berdasarkan filter yang dipilih, berkas Excel yang diunduh hanya memuat baris header kolom.
- **Post Conditions**: File rekapitulasi data Excel tersimpan di komputer lokal pengguna.

---

### UC-15. Memverifikasi Pengajuan Perusahaan Baru (1 Proses untuk 3 Role)
- **Usecase ID**: UC-15
- **Usecase Name**: Memverifikasi Pengajuan Perusahaan Baru
- **Actors Involved**: **Admin Program Studi, Admin Fakultas, Superadmin**
- **Description**: Memvalidasi, mengoreksi, mengesahkan, menolak, atau mengalihkan pengajuan data entitas perusahaan baru dari alumni yang berstatus `Menunggu Verifikasi` agar basis data industri mitra kampus konsisten, valid, dan bebas dari duplikasi nama.
- **Scope & Role Authorization (3 Role)**:
  1. **Admin Program Studi** (`/prodi/perusahaan`): Memverifikasi pengajuan instansi dari alumni program studinya sendiri (`created_by_prodi_id = prodi admin`).
  2. **Admin Fakultas** (`/fakultas/perusahaan`): Memverifikasi pengajuan instansi dari alumni seluruh prodi dalam fakultasnya.
  3. **Superadmin** (`/superadmin/perusahaan`): Otoritas tertinggi verifikasi seluruh pengajuan instansi se-universitas dengan opsi filter per prodi.
- **Preconditions**: Terdapat entitas perusahaan berstatus `Menunggu Verifikasi` pada database `perusahaan` sesuai cakupan wewenang peran yang login.
- **Main Flow (Basic Flow)**:
  1. Aktor membuka menu verifikasi perusahaan (`{base_route}/perusahaan`).
  2. Sistem menampilkan daftar pengajuan instansi yang menunggu persetujuan (Nama, Sektor, Alamat, Alumni Pengaju).
  3. Sistem melalui algoritma *string similarity* secara otomatis menampilkan skor kemiripan dan merekomendasikan perusahaan master terverifikasi yang mirip jika terdeteksi indikasi duplikasi.
  4. Aktor memilih salah satu dari 4 opsi tindakan keputusan:
     - **Opsi A - Setujui Baru (Verify)**: Mengesahkan perusahaan menjadi entitas master terverifikasi (`POST {base_route}/perusahaan/{id}/verify`).
     - **Opsi B - Alihkan ke Master Terdaftar (Replace)**: Mengalihkan relasi tempat kerja alumni ke perusahaan master yang sudah ada, lalu menghapus data usulan duplikat (`POST {base_route}/perusahaan/{id}/replace`).
     - **Opsi C - Koreksi & Verifikasi (Update & Verify)**: Memperbaiki ejaan nama, sektor usaha, atau alamat perusahaan lalu langsung memverifikasi (`PUT {base_route}/perusahaan/{id}`).
     - **Opsi D - Tolak Pengajuan (Reject)**: Menolak pengajuan karena instansi fiktif atau tidak valid (`POST {base_route}/perusahaan/{id}/reject`).
  5. Sistem memperbarui basis data dan mencatat tindakan pada log audit aktivitas (`log_activities`).
  6. Sistem memunculkan notifikasi sukses keputusan verifikasi.
- **Alternate Flow (Alternative Flow)**:
  - Aktor menggunakan fitur pencarian master instansi (`GET {base_route}/perusahaan/search-verified`) untuk mencari nama perusahaan resmi sebelum menentukan aksi replace.
- **Post Conditions**: Status pengajuan perusahaan diperbarui menjadi `Terverifikasi`, `Ditolak`, atau dialihkan ke master perusahaan sah.

---

### UC-16. Mengelola Akun Alumni & Reset Sandi Default (1 Proses untuk 3 Role)
- **Usecase ID**: UC-16
- **Usecase Name**: Mengelola Akun Alumni & Reset Sandi Default
- **Actors Involved**: **Admin Program Studi, Admin Fakultas, Superadmin**
- **Description**: Mengelola akun pengguna (memperbarui email, username, nama) dan memfasilitasi pemulihan akses akun alumni yang mengalami kendala login dengan mereset kata sandi secara aman kembali ke standar tanggal lahir bawaan (`DDMMYYYY`), mengaktifkan flag wajib ganti sandi, serta mengirimkan notifikasi email resmi.
- **Scope & Role Authorization (3 Role)**:
  1. **Admin Program Studi** (`/prodi/manajemen-akun`): Khusus mengelola dan mereset akun alumni di bawah prodi admin.
  2. **Admin Fakultas** (`/fakultas/manajemen-akun`): Mengelola dan mereset akun alumni lintas prodi di bawah fakultas admin.
  3. **Superadmin** (`/superadmin/manajemen-akun`): Mengelola akun dari seluruh peran (`admin_fakultas`, `admin_prodi`, `alumni`) se-universitas.
- **Preconditions**: Aktor membuka menu manajemen akun pada rutenya masing-masing.
- **Main Flow (Basic Flow)**:
  1. Sistem menampilkan daftar akun pengguna sesuai cakupan filter peran.
  2. Aktor mencari akun berdasarkan NIM, Nama, atau Email.
  3. **Pembaruan Profil Akun**: Aktor dapat mengedit email atau nama pengguna lalu menyimpan (`PUT {base_route}/manajemen-akun/{id}`).
  4. **Reset Sandi Bawaan**: Jika alumni lupa akses, aktor menekan tombol "Reset Default & Notify" (`POST {base_route}/manajemen-akun/{id}/reset-default-notify`).
  5. Sistem mengambil tanggal lahir alumni dari tabel `data_akademik`, memformat menjadi `DDMMYYYY`, mengenkripsi menjadi hash password baru di tabel `users`, dan menyetel `must_change_password = true`.
  6. Sistem mengirimkan surat elektronik resmi ke alamat email alumni berisi pemberitahuan reset sandi default dan instruksi login.
  7. Sistem mencatat tindakan reset ke tabel `log_activities`.
  8. Sistem memunculkan notifikasi sukses reset akun.
- **Alternate Flow (Alternative Flow)**:
  - Jika tanggal lahir di tabel data akademik kosong, sistem menampilkan peringatan validasi agar admin melengkapi tanggal lahir terlebih dahulu.
- **Post Conditions**: Kata sandi akun alumni kembali ke tanggal lahir default, flag ganti sandi wajib aktif, dan alumni menerima notifikasi di email.

---

### UC-17. Melakukan Sinkronisasi Profil LinkedIn Alumni (1 Proses untuk 3 Role Utama)
- **Usecase ID**: UC-17
- **Usecase Name**: Melakukan Sinkronisasi Profil LinkedIn Alumni (Single & Batch Sync)
- **Actors Involved**: **Admin Program Studi, Admin Fakultas, Superadmin**
- **Description**: Mengambil data posisi jabatan terkini, nama instansi tempat bekerja, riwayat pengalaman kerja (*experience*), daftar keahlian (*skills*), dan foto profil fisik alumni dari LinkedIn secara otomatis menggunakan layanan scraper/provider terintegrasi (LinkedIn Profile Provider / Apify) secara satuan (*single sync*) maupun massal (*batch sync*), meninjau hasil ekstraksi pada modal peninjauan (*staging review*), menyetujui (*approve*) atau menolak (*reject*) hasil sinkronisasi, serta meninjau riwayat riil sinkronisasi (*history*).
- **Scope & Role Authorization (3 Role)**:
  1. **Admin Program Studi** (`/prodi/linkedin-sync`): Mengelola sinkronisasi direktori alumni khusus program studinya sendiri.
  2. **Admin Fakultas** (`/fakultas/linkedin-sync`): Mengelola sinkronisasi alumni dari seluruh program studi dalam naungan fakultasnya.
  3. **Superadmin** (`/superadmin/linkedin-sync`): Mengelola sinkronisasi alumni menyeluruh se-universitas lintas fakultas dan angkatan, lengkap dengan integrasi tombol aksi cepat buka audit alumni (`/superadmin/alumni/{id}`).
  4. **Admin Biro III**: hanya meninjau hasil/riwayat LinkedIn pada detail alumni; tidak memiliki rute sinkronisasi, approve atau reject.
- **Preconditions**: Alumni memiliki profil/username LinkedIn yang tersimpan di sistem, dan aktor membuka modul sinkronisasi LinkedIn pada rutenya masing-masing.
- **Main Flow (Basic Flow)**:
  1. Sistem menampilkan direktori alumni lengkap dengan filter Tahun Kelulusan, Target Periode, Semester, Prodi, dan Status Sinkronisasi (*Belum Sinkron, Menunggu Review, Disetujui, Ditolak, Gagal*).
     - *Jika diakses dari tautan detail audit alumni, sistem otomatis mengaktifkan tahun lulus yang sesuai, menerapkan filter pencarian, memberi sorotan hijau "Profil Dipilih", dan melakukan auto-scroll ke baris profil tersebut.*
  2. **Aksi Tombol Cepat Detail Alumni (Ikon Mata)**: Pada kolom Aksi di baris tabel, admin dapat mengklik tombol ikon mata untuk langsung membuka halaman detail profil lengkap alumni.
  3. **Eksekusi Sinkronisasi**:
     - **Alur Satuan (Single Sync)**: Aktor menekan tombol "Sinkronkan" pada salah satu baris alumni (`POST {base_route}/linkedin-sync/{id}`).
     - **Alur Massal (Batch Sync)**: Aktor menekan tombol "Sinkronisasi Massal" untuk memproses alumni pada tahun yang dipilih melalui driver yang mendukung batch; Apify menolak batch dengan HTTP 400 (`POST {base_route}/linkedin-sync/batch`).
  4. Sistem menghubungi penyedia penarikan data LinkedIn (Official API / Apify Third-Party Provider) untuk mengambil JSON profil karir publik alumni.
  5. Pada provider Apify, sistem menyimpan hasil ekstraksi ke `linkedin_sync_results` dengan status `pending`. Driver mock/API mengikuti alur `LinkedInProfileService`.
  6. **Peninjauan Staging (Review Modal)**: Aktor mengklik tombol "Tinjau Hasil" (`GET {base_route}/linkedin-sync/results/{id}`). Sistem membuka modal pop-up yang menyajikan komparasi data saat ini vs data hasil LinkedIn: posisi jabatan, nama instansi kantor, periode bekerja, keahlian (*skills*), dan foto profil fisik alumni.
  7. **Keputusan Persetujuan**:
     - **Jika Disetujui**: Aktor menekan tombol "Setujui & Terapkan" (`POST {base_route}/linkedin-sync/results/{id}/approve`). Sistem otomatis memperbarui data jabatan, nama instansi, `skills`, `experience`, serta foto profil fisik pada tabel `biodata`, dan menandai status sinkronisasi sebagai `approved`.
     - **Jika Ditolak**: Aktor menekan tombol "Tolak Hasil" (`POST {base_route}/linkedin-sync/results/{id}/reject`). Sistem mempertahankan data lama di database dan menandai status sinkronisasi sebagai `rejected`.
  8. Aktor dapat melihat histori riil jejak sinkronisasi pada `{base_route}/linkedin-sync/alumni/{id}/history`.
  9. Sistem mencatat seluruh operasi ke `log_activities`.
- **Alternate Flow (Alternative Flow)**:
  - **A1. URL LinkedIn Tidak Valid / Profil Privat**: Sistem mencatat pesan kegagalan pada tabel sinkronisasi, menyetel status `failed`, dan menampilkan notifikasi kesalahan yang deskriptif kepada admin.
  - **A2. Kuota Provider Habis / Timeout**: Sistem menangani exception gracefully via `LinkedInSyncException` tanpa menghentikan aplikasi.
- **Post Conditions**: Riwayat karir alumni terbarui dengan data faktual dari LinkedIn, tersimpan pada rekam histori, dan tersinkronisasi dua arah dengan modul audit direktori alumni.

---

### UC-18. Mengelola Bagian (Section) Kuesioner Program Studi
- **Usecase ID**: UC-18
- **Usecase Name**: Mengelola Bagian (*Section*) Kuesioner Program Studi
- **Actors Involved**: Admin Program Studi, Superadmin
- **Description**: Menyusun, mengubah judul, mendeskripsikan, menghapus, serta mengatur nomor urutan (*reorder*) kelompok seksi pada instrumen kuesioner khusus program studi.
- **Preconditions**: Aktor membuka modul kelola section prodi (`/prodi/sections` untuk Admin Prodi, atau `/superadmin/prodi-kuesioner` untuk Superadmin).
- **Main Flow (Basic Flow)**:
  1. Sistem menampilkan daftar section yang ada pada tabel `prodi_question_section`.
  2. Aktor dapat melakukan:
     - **Tambah**: Mengisi judul section dan deskripsi lalu menyimpan (`POST /prodi/sections`).
     - **Ubah**: Mengedit judul atau deskripsi section (`PUT /prodi/sections/{id}`).
     - **Reorder**: Menyeret atau mengatur urutan bobot nomor urut section (`POST /prodi/sections/reorder`).
     - **Hapus**: Menghapus section yang tidak lagi dipakai (`DELETE /prodi/sections/{id}`).
  3. Sistem memperbarui database dan menampilkan notifikasi berhasil.
- **Alternate Flow (Alternative Flow)**:
  - Hapus dibatalkan jika terdapat butir pertanyaan yang masih aktif di dalam section tersebut.
- **Post Conditions**: Struktur kelompok seksi kuesioner prodi terorganisasi dengan rapi di database.

---

### UC-19. Mengelola Butir Pertanyaan dan Opsi Jawaban Kuesioner Prodi
- **Usecase ID**: UC-19
- **Usecase Name**: Mengelola Butir Pertanyaan dan Opsi Jawaban Kuesioner Prodi
- **Actors Involved**: Admin Program Studi, Superadmin
- **Description**: Mengonfigurasi butir-butir pertanyaan survei mandiri prodi (kode unik soal, teks pertanyaan, tipe input: pilihan tunggal, pilihan ganda, isian teks, angka, rating skala 5) beserta pilihan opsinya.
- **Preconditions**: Section kuesioner prodi telah dibuat sebelumnya (`/prodi/pertanyaan`).
- **Main Flow (Basic Flow)**:
  1. Aktor memilih section induk tempat pertanyaan bernaung.
  2. Aktor menambahkan butir pertanyaan baru: mengisi kode unik, teks pertanyaan, memilih tipe input, status wajib diisi (*is_required*), dan urutan (`POST /prodi/pertanyaan`).
  3. Untuk tipe pilihan ganda/checkbox: Aktor menambahkan pilihan opsi pada tabel `prodi_question_option` (`POST /prodi/opsi`).
  4. Aktor dapat mengubah, menyusun ulang (*reorder*), atau menghapus butir pertanyaan.
  5. Sistem menyimpan seluruh konfigurasi ke tabel `prodi_question` dan `prodi_question_option`.
- **Alternate Flow (Alternative Flow)**:
  - Penghapusan butir pertanyaan dicegah jika sudah memiliki relasi data pada `prodi_response`.
- **Post Conditions**: Butir instrumen kuesioner prodi siap disajikan kepada alumni saat mengisi kuesioner prodi.

---

### UC-20. Mengelola Kuesioner Universitas & Logika Percabangan (Branching)
- **Usecase ID**: UC-20
- **Usecase Name**: Mengelola Kuesioner Universitas & Logika Percabangan (*Branching*)
- **Actors Involved**: Superadmin
- **Description**: Pengelolaan menyeluruh kuesioner tracer study universitas (standar Kemdikbudristek): mengaktifkan paket kuesioner tahunan, mengelola grup bagian (*section*), butir pertanyaan, pilihan opsi, dan aturan logika percabangan loncat soal (*branching jump_to*).
- **Preconditions**: Superadmin membuka modul kuesioner universitas (`/superadmin/sections`, `/superadmin/kuesioner`, `/superadmin/pertanyaan`).
- **Main Flow (Basic Flow)**:
  1. **Kelola Paket Kuesioner Induk**: Superadmin membuat paket kuesioner tahunan baru atau mengaktifkan/menonaktifkan kuesioner aktif (`PATCH /superadmin/kuesioner/{id}/toggle-active`).
  2. **Kelola Section**: Superadmin membuat, mengedit, dan mengatur nomor urutan bagian kuesioner pada tabel `kelompok_pertanyaan`.
  3. **Kelola Pertanyaan**: Superadmin menambah/mengubah pertanyaan pada tabel `ref_subpertanyaan2021` (kode unik, teks pertanyaan, tipe input, penanda wajib, tampil di profil/kuesioner, dan nomor urut).
  4. **Kelola Opsi & Branching**: Superadmin memasukkan opsi jawaban pada tabel `ref_subpertanyaan_detil` dan menyetel kode pertanyaan tujuan percabangan (`jump_to`) jika responden memilih opsi tertentu.
  5. Sistem memvalidasi integritas relasi dan menyimpan perubahan.
- **Alternate Flow (Alternative Flow)**:
  - Jika kode pertanyaan duplikat, sistem menampilkan validasi error kode unik.
- **Post Conditions**: Konfigurasi instrumen kuesioner universitas diperbarui dan langsung terefleksi pada formulir kuesioner yang diisi alumni.

---

### UC-21. Mengelola Instrumen Evaluasi Pengguna Lulusan / Atasan Langsung
- **Usecase ID**: UC-21
- **Usecase Name**: Mengelola Instrumen Evaluasi Pengguna Lulusan / Atasan Langsung
- **Actors Involved**: Superadmin
- **Description**: Mengelola butir-butir pertanyaan survei penilaian kinerja lulusan yang akan dijawab oleh pimpinan/atasan tempat alumni bekerja melalui tautan token publik.
- **Preconditions**: Superadmin membuka rute `/superadmin/evaluasi-atasan`.
- **Main Flow (Basic Flow)**:
  1. Sistem menampilkan daftar butir penilaian pada tabel `pertanyaan_evaluasi_atasan`.
  2. Superadmin dapat:
     - Menambah pertanyaan baru: mengisi teks pertanyaan, tipe (likert matriks, teks, pilihan), dan urutan (`POST /superadmin/evaluasi-atasan/pertanyaan`).
     - Mengubah teks pertanyaan yang sudah ada (`PUT /pertanyaan/{id}`).
     - Mengaktifkan atau menonaktifkan status pertanyaan (`PATCH /pertanyaan/{id}/toggle`).
     - Mengatur ulang urutan butir pertanyaan (`POST /pertanyaan/reorder`).
     - Menghapus butir pertanyaan yang tidak relevan (`DELETE /pertanyaan/{id}`).
  3. Sistem memvalidasi dan menyimpan struktur baru.
- **Alternate Flow (Alternative Flow)**:
  - Pertanyaan yang telah memiliki riwayat jawaban ditandai nonaktif alih-alih dihapus permanen untuk menjaga integritas data historis.
- **Post Conditions**: Formulir evaluasi atasan (`/evaluasi-atasan/{token}`) akan menyajikan butir penilaian sesuai konfigurasi terbaru ini.

---

### UC-22. Memantau Log Aktivitas & Audit Trail Sistem
- **Usecase ID**: UC-22
- **Usecase Name**: Memantau Log Aktivitas & Audit Trail Sistem
- **Actors Involved**: Superadmin
- **Description**: Meninjau rekaman jejak audit sistem (*system audit logs*) terhadap seluruh aksi kritis pengguna (seperti verifikasi perusahaan, reset sandi default, persetujuan sinkronisasi LinkedIn, pembaruan kuesioner, dan aktivitas autentikasi) untuk akuntabilitas dan keamanan sistem.
- **Preconditions**: Superadmin membuka rute `/superadmin/logs`.
- **Main Flow (Basic Flow)**:
  1. Sistem mengambil catatan dari tabel `log_activities`.
  2. Sistem menampilkan tabel audit trail yang memuat: nama pengguna, peran, aksi (`action`), model yang terpengaruh (`model_type` & `model_id`), deskripsi perubahan, nilai lama & nilai baru (`old_values` & `new_values` dalam format JSON), alamat IP (`ip_address`), *user agent*, serta stempel waktu (*timestamp*).
  3. Superadmin dapat memfilter log berdasarkan tipe aksi atau mencari entri spesifik.
- **Alternate Flow (Alternative Flow)**:
  - Jika tidak ada aktivitas yang cocok dengan filter, sistem menampilkan informasi bahwa entri log tidak ditemukan.
- **Post Conditions**: Superadmin memperoleh kepastian audit dan rekam jejak transparansi atas setiap modifikasi data di sistem.

---

### UC-23. Menampilkan Detail Lengkap Master Perusahaan dan Rekomendasi
- **Usecase ID**: UC-23
- **Usecase Name**: Menampilkan Detail Lengkap Master Perusahaan dan Rekomendasi
- **Actors Involved**: Superadmin, Admin Fakultas, Admin Prodi
- **Description**: Administrator meninjau seluruh atribut perusahaan, termasuk informasi lokasi, kontak, klasifikasi, status verifikasi, pembuat data, dan waktu perubahan.
- **Main Flow**:
  1. Administrator membuka /superadmin/perusahaan, /fakultas/perusahaan, atau /prodi/perusahaan sesuai role.
  2. Administrator membuka detail perusahaan dari tombol peninjauan.
  3. Sistem menampilkan seluruh field master perusahaan dan data relasi lokasi/pengusul yang tersedia.
  4. Pada setiap rekomendasi master, administrator dapat membuka tombol informasi untuk melihat detail kandidat sebelum melakukan konsolidasi.
- **Alternate Flow**:
  - Jika relasi wilayah atau pengusul sudah dihapus, sistem tetap menampilkan field dengan tanda - , bukan menghasilkan error pada modal.
- **Post Conditions**: Administrator dapat mengambil keputusan verifikasi atau konsolidasi berdasarkan data perusahaan yang lengkap.

### UC-24. Menyimpan Jawaban Tracer Secara Konsisten
- **Usecase ID**: UC-24
- **Usecase Name**: Menyimpan dan Menampilkan Jawaban Tracer secara Konsisten
- **Actors Involved**: Alumni, Superadmin, Admin Fakultas, Admin Prodi
- **Description**: Sistem mempertahankan teks jawaban untuk kompatibilitas laporan dan proyeksi JSON untuk form, pilihan ganda, nilai numerik, serta jawaban Lainnya. Identitas butir disimpan pada `tracer.question_id` sebagai foreign key ke `ref_subpertanyaan2021.id`; F505 menggunakan `biodata.gaji` sebagai sumber nominal tunggal.
- **Main Flow**:
  1. Alumni atau proses sinkronisasi menyimpan jawaban melalui model Tracer.
  2. Sistem mengisi answer sebagai representasi teks yang dapat dibaca.
  3. Sistem mengisi answer_json sebagai representasi terstruktur atau { "value": "..." } untuk jawaban teks sederhana.
  4. Saat menyimpan atau membaca F505, sistem menyamakan nilainya dengan `biodata.gaji` dan menimpa nominal lama bila berbeda.
  5. Saat membaca data lama, sistem menggunakan nilai yang tersedia tanpa menghapus jawaban.
- **Alternate Flow**:
  - Jika salah satu kolom kosong, model melengkapi proyeksi yang hilang.
  - Jika data JSON lama tidak valid, nilai teks dipertahankan sebagai { "value": "..." }.
- **Post Conditions**: Export, detail audit, dan form pengisian tetap dapat membaca data lama maupun data yang sudah dinormalisasi.

### UC-25. Menampilkan Profil Alumni dengan Minimisasi Data Sensitif
- **Usecase ID**: UC-25
- **Usecase Name**: Menampilkan Profil Alumni dengan Minimisasi Data Sensitif
- **Actors Involved**: Alumni
- **Description**: Sistem menampilkan hanya data yang diperlukan untuk pengisian tracer pada halaman profil alumni. Data kependudukan, dokumen, pembimbing/penguji, publikasi, dan keluarga tetap disimpan pada sumber resmi tetapi tidak dikirim sebagai props atau dirender pada halaman self-service. Judul tugas akhir dan tanggal kelulusan tetap ditampilkan sebagai referensi akademik non-sensitif.
- **Preconditions**: Alumni telah login dan membuka `/alumni/profile`.
- **Main Flow (Basic Flow)**:
  1. Controller memuat konteks minimum profil, data karier, dan data tracer yang diperlukan.
  2. Sistem tidak mengirim NIK, NPWP, KK, NISN, BPJS, email mahasiswa, data pembimbing/penguji, publikasi, atau data orang tua ke props halaman profil.
  3. Sistem menampilkan tiga tab: Identitas & Alamat, Karier & Jejaring, dan Akademik dasar termasuk judul tugas akhir serta tanggal kelulusan.
  4. Saat profil disimpan, field sensitif yang tidak dikirim tidak diubah atau dikosongkan.
- **Alternate Flow (Alternative Flow)**:
  - Data sensitif tetap dapat dipakai oleh sumber akademik, audit admin, ekspor, dan integrasi resmi sesuai otorisasi masing-masing.
  - Jika data lama kosong, sistem tidak membuat nilai pengganti dari halaman profil alumni.
- **Post Conditions**: Data lama tetap utuh, payload browser lebih terbatas, dan profil alumni hanya menampilkan data yang relevan untuk tracer.

## 6. Matriks Keterlacakan Use Case terhadap Peran (*Use Case Traceability Matrix*)

Matriks berikut mengonfirmasi pemetaan antara use case yang dimodelkan dengan hak akses pengguna pada basis kode Tracer Study UKDW:

| ID Use Case | Nama Use Case | Tamu / Publik | Alumni | Atasan (Token) | Admin Prodi | Admin Fakultas | Admin Biro III | Superadmin |
| :---: | :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| **UC-01** | Mengakses Beranda Publik | **X** | **X** | - | **X** | **X** | **X** | **X** |
| **UC-02** | Melakukan Autentikasi (Login) | **X** | **X** | - | **X** | **X** | **X** | **X** |
| **UC-03** | Memulihkan Kata Sandi | **X** | **X** | - | **X** | **X** | **X** | **X** |
| **UC-04** | Mengubah Kata Sandi Wajib | - | **X** | - | - | - | - | - |
| **UC-05** | Mengakhiri Sesi (Logout) | - | **X** | - | **X** | **X** | **X** | **X** |
| **UC-06** | Dashboard Alumni | - | **X** | - | - | - | - | - |
| **UC-07** | Kelola Profil & Pekerjaan Alumni | - | **X** | - | - | - | - | - |
| **UC-08** | Tambah Perusahaan Baru | - | **X** | - | **X** | **X** | **X** | **X** |
| **UC-09** | Isi Kuesioner Universitas | - | **X** | - | - | - | - | - |
| **UC-10** | Isi Kuesioner Prodi | - | **X** | - | - | - | - | - |
| **UC-11** | Evaluasi Kinerja Lulusan (Atasan) | - | - | **X** | - | - | - | - |
| **UC-12** | Dashboard Monitoring & Analisis Kinerja | - | - | - | **X** | **X** | **X** | **X** |
| **UC-13** | Kelola Direktori Alumni & Audit Detail | - | - | - | **X** | **X** | **X** | **X** |
| **UC-14** | Ekspor Rekap Alumni ke Excel | - | - | - | **X** | **X** | **X** | **X** |
| **UC-15** | Verifikasi Perusahaan Baru (3 Role) | - | - | - | **X** | **X** | - | **X** |
| **UC-16** | Manajemen Akun & Reset Sandi (3 Role) | - | - | - | **X** | **X** | - | **X** |
| **UC-17** | Sinkronisasi Profil LinkedIn Alumni | - | - | - | **X** | **X** | - | **X** |
| **UC-18** | Kelola Section Kuesioner Prodi | - | - | - | **X** | - | - | **X** |
| **UC-19** | Kelola Soal & Opsi Prodi | - | - | - | **X** | - | - | **X** |
| **UC-20** | Kelola Kuesioner Universitas & Branching | - | - | - | - | - | - | **X** |
| **UC-21** | Kelola Soal Evaluasi Atasan | - | - | - | - | - | - | **X** |
| **UC-22** | Log Aktivitas & Audit Trail | - | - | - | - | - | - | **X** |
| **UC-25** | Minimisasi Data Sensitif pada Profil Alumni | - | **X** | - | - | - | - | - |

---

*Catatan: Dokumen spesifikasi Use Case ini disinkronkan secara presisi dengan arsitektur rute di `routes/web.php`, struktur basis data di `ERD.md`, dan hak akses middleware sistem Tracer Study UKDW (SERU).*
