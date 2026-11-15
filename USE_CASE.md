# Spesifikasi Use Case Sistem Tracer Study UKDW

Dokumen ini merupakan spesifikasi normatif use case untuk Sistem Informasi Tracer Study Universitas Kristen Duta Wanna (UKDW). Struktur use case, pembagian aktor, serta relasi asosiasi dan ekstensi pada dokumen ini diselaraskan secara penuh dengan Use Case Diagram terbaru, arsitektur route pada `routes/web.php`, controller pendukung, serta schema database aktif `tracer`.

---

## 1. Identifikasi Aktor

Sistem Tracer Study UKDW melibatkan 8 aktor yang terdiri atas aktor manusia (primer), generalisasi peran administratif, dan satu sistem eksternal:

| Aktor | Kategori | Deskripsi dan Batasan Hak Akses |
| :--- | :--- | :--- |
| **Alumni** | Pengguna Primer (Manusia) | Lulusan UKDW yang memiliki akun sistem. Berwenang mengelola data profil dan riwayat karier, mengajukan penambahan perusahaan baru, serta mengisi kuesioner tingkat universitas dan kuesioner program studi. |
| **Pengguna Lulusan (Atasan)** | Pengguna Primer (Manusia) | Atasan langsung alumni di instansi/perusahaan tempat alumni bekerja. Berwenang mengisi survei evaluasi kinerja alumni melalui tautan token publik tanpa memerlukan akun login sistem. |
| **Superadmin (ITC)** | Administrator Utama (Manusia) | Administrator sistem tingkat universitas (Biro ITC). Memiliki wewenang mutlak atas seluruh fitur sistem, termasuk manajemen akun, audit log aktivitas, instrumen kuesioner universitas dan atasan, master wilayah dan UMP, kuesioner prodi, verifikasi perusahaan, dan analitik. |
| **admin** | Aktor Induk (Generalisasi) | Entitas generalisasi peran administrator institusi. Mewarisi hak akses pengelolaan data alumni, pemantauan laporan, master data perusahaan, serta master data pengguna lulusan. |
| **admin_prodi** | Administrator Spesialisasi | Aktor turunan dari `admin` untuk lingkup Program Studi. Selain mewarisi fungsi `admin` dalam batasan prodinya, memiliki wewenang langsung dan eksklusif untuk mengelola instrumen kuesioner program studi bersangkutan. |
| **admin_fakultas** | Administrator Spesialisasi | Aktor turunan dari `admin` untuk lingkup Fakultas. Mewarisi hak akses `admin` untuk mengawasi seluruh data alumni, perusahaan, dan laporan pada program studi di bawah fakultasnya. |
| **admin_biro3** | Administrator Spesialisasi | Aktor turunan dari `admin` untuk lingkup Biro III (Kemahasiswaan & Alumni). Mengawasi data direktori alumni, laporan, dan komunikasi tingkat universitas. |
| **Apify** | Sistem Eksternal (`<<system>>`) | Layanan komputasi awan web scraping LinkedIn yang menerima pemicuan request profil alumni dan mengembalikan payload riwayat pengalaman kerja serta keahlian untuk proses peninjauan staging. |

```
        +---------------------------------------------------+
        |                       admin                       |
        | (Aktor Induk: Data Alumni, Laporan, Perusahaan)   |
        +---------------------------------------------------+
              ^                   ^                   ^
              |                   |                   |
        +-----+-------+   +-------+------+   +--------+-------+
        | admin_prodi |   |admin_fakultas|   |  admin_biro3   |
        +-------------+   +--------------+   +----------------+
        (Kelola Kuesioner
             Prodi)
```

---

## 2. Hubungan Asosiasi dan Relasi Extend

### 2.1 Hubungan Generalisasi Aktor
`admin_prodi`, `admin_fakultas`, dan `admin_biro3` adalah spesialisasi dari aktor induk `admin`. Setiap use case yang diasosiasikan dengan `admin` secara otomatis dapat diakses oleh ketiga peran tersebut sesuai dengan batasan lingkup (scope wewenang) masing-masing. Khusus use case **Mengelola kuesioner prodi**, garis asosiasi ditarik langsung ke `admin_prodi` dan `Superadmin (ITC)`, sehingga `admin_biro3` dan `admin_fakultas` tidak memiliki akses pengelolaan instrumen prodi.

### 2.2 Hubungan Extend (`<<extend>>`)
Relasi `<<extend>>` digunakan untuk memperluas fungsionalitas use case dasar pada titik ekstensi (*extension point*) tertentu dengan kondisi pemicu (*extension condition*):

1. **Mengubah Kata Sandi** `<<extend>>` **Login**: Dipicu ketika pengguna terautentikasi memiliki atribut `must_change_password = true` (login perdana dengan kredensial bawaan) atau memilih opsi perubahan kata sandi berkala.
2. **Memulihkan Kata Sandi** `<<extend>>` **Login**: Dipicu saat pengguna tidak dapat mengingat kata sandi pada halaman login dan menekan tombol lupa kata sandi untuk meminta token reset via email.
3. **Mengelola Master UMP** `<<extend>>` **Mengelola master Wilayah**: Dipicu saat Superadmin membuka entitas provinsi tertentu dan membutuhkan konfigurasi nilai Upah Minimum Provinsi tahunan sebagai batas acuan IKU-1.
4. **Menambah daftar perusahaan** `<<extend>>` **Mengelola profile alumni**: Dipicu saat alumni memperbarui data pekerjaan namun instansi tempat bekerja belum terdaftar pada master perusahaan sistem.
5. **Sinkronisasi Linkedin** `<<extend>>` **Mengelola data alumni**: Dipicu saat admin memilih alumni yang memiliki URL LinkedIn valid untuk melakukan penarikan data profil otomatis via Apify.
6. **Menghubungi Alumni** `<<extend>>` **Mengelola data alumni**: Dipicu saat admin ingin mengirimkan pesan komunikasi email resmi (undangan pengisian, pengingat, atau konfirmasi).
7. **Ekspor Laporan** `<<extend>>` **Mengelola data alumni**: Dipicu saat admin/superadmin mengunduh berkas spreadsheet direktori alumni sesuai kriteria filter.
8. **Ekspor Laporan** `<<extend>>` **Melihat Laporan**: Dipicu saat admin/superadmin mengunduh agregasi data analitik statistik IKU-1 dan grafik tracer study ke format Excel/ZIP.
9. **Verifikasi Perusahaan** `<<extend>>` **Master Data Perusahaan**: Dipicu saat admin meninjau antrean pengajuan perusahaan baru yang diinput oleh alumni untuk diverifikasi, ditolak, atau digabungkan ke data yang telah ada.

---

## 3. Daftar Ringkasan 23 Use Case

| ID | Nama Use Case | Aktor Terkait | Hubungan Khusus | Rujukan Implementasi Utama |
| :--- | :--- | :--- | :--- | :--- |
| **UC01** | Login | Alumni, admin, Superadmin (ITC) | Use case dasar autentikasi | `/login`, `LoginController`, tabel `users` |
| **UC02** | Mengubah Kata Sandi | Alumni, admin, Superadmin (ITC) | `<<extend>>` ke UC01 (Login) | `/change-password`, `ChangePasswordController` |
| **UC03** | Memulihkan Kata Sandi | Alumni, admin, Superadmin (ITC) | `<<extend>>` ke UC01 (Login) | `/forgot-password`, `ForgotPasswordController` |
| **UC04** | Manajemen akun | Superadmin (ITC) | Asosiasi langsung | `/superadmin/manajemen-akun`, `UserManagementController` |
| **UC05** | Melihat Log Aktivitas | Superadmin (ITC) | Asosiasi langsung | `/superadmin/logs`, `ActivityLogController` |
| **UC06** | Mengelola kuesioner universitas | Superadmin (ITC) | Asosiasi langsung | `/superadmin/kuesioner`, `KuesionerController` |
| **UC07** | Mengelola kuesioner pengguna lulusan | Superadmin (ITC) | Asosiasi langsung | `/superadmin/evaluasi-atasan`, `EvaluasiAtasanController` |
| **UC08** | Mengelola master Wilayah | Superadmin (ITC) | Use case dasar data wilayah | `/superadmin/master-wilayah`, `MasterWilayahController` |
| **UC09** | Mengelola Master UMP | Superadmin (ITC) | `<<extend>>` ke UC08 (Wilayah) | `/superadmin/master-wilayah` (Tab UMP), tabel `ump` |
| **UC10** | Mengelola profile alumni | Alumni | Use case dasar biodata alumni | `/alumni/profile`, `AlumniProfileController` |
| **UC11** | Menambah daftar perusahaan | Alumni | `<<extend>>` ke UC10 (Profile) | `/alumni/company`, `CompanyController` |
| **UC12** | Mengisi Kuesioner Universitas | Alumni | Asosiasi langsung | `/alumni/kuesioner`, `AlumniKuesionerController` |
| **UC13** | Mengisi Kuesioner Program Studi | Alumni | Asosiasi langsung | `/alumni/kuesioner-prodi`, `AlumniKuesionerProdiController` |
| **UC14** | Evaluasi kinerja Alumni | Pengguna Lulusan (Atasan) | Asosiasi langsung (Public Token) | `/evaluasi-atasan/{token}`, `EvaluasiAtasanPublicController` |
| **UC15** | Mengelola data alumni | admin, Superadmin (ITC) | Use case dasar direktori alumni | `/{role}/alumni`, `AlumniManagementController` |
| **UC16** | Sinkronisasi Linkedin | admin, Superadmin (ITC), Apify | `<<extend>>` ke UC15 (Data Alumni) | `/{role}/linkedin-sync`, `LinkedinSyncController` |
| **UC17** | Menghubungi Alumni | admin, Superadmin (ITC) | `<<extend>>` ke UC15 (Data Alumni) | `/{role}/alumni/{id}/contact`, `ContactAlumniController` |
| **UC18** | Melihat Laporan | admin, Superadmin (ITC) | Use case dasar laporan analitik | `/{role}/analitik`, `/{role}/dashboard`, `AnalitikController` |
| **UC19** | Ekspor Laporan | admin, Superadmin (ITC) | `<<extend>>` ke UC15 & UC18 | `/{role}/export`, `ExportController` |
| **UC20** | Mengelola kuesioner prodi | admin_prodi, Superadmin (ITC) | Asosiasi langsung (Prodi & ITC) | `/{role}/prodi-kuesioner`, `ProdiKuesionerController` |
| **UC21** | Master Data Perusahaan | admin, Superadmin (ITC) | Use case dasar master perusahaan | `/{role}/perusahaan`, `CompanyController` |
| **UC22** | Verifikasi Perusahaan | admin, Superadmin (ITC) | `<<extend>>` ke UC21 (Perusahaan)| `/{role}/perusahaan/verifikasi`, `CompanyVerificationController` |
| **UC23** | Master Data Pengguna Lulusan | admin, Superadmin (ITC) | Asosiasi langsung | `/{role}/pengguna-lulusan`, `AtasanManagementController` |

---

## 4. Spesifikasi Rinci Use Case (Tabel Formal)

Berikut adalah tabel spesifikasi teknis untuk setiap use case sesuai format standar UML:

### UC01 — Login

| Elemen | Spesifikasi |
| :--- | :--- |
| **Identitas Use Case** | UC01 — Login |
| **Aktor** | Alumni, `admin` (`admin_prodi`, `admin_fakultas`, `admin_biro3`), Superadmin (ITC) |
| **Deskripsi** | Melakukan proses autentikasi akun pengguna ke dalam sistem Tracer Study UKDW menggunakan kredensial berupa kombinasi nama pengguna (`username`/NIM/Email) dan kata sandi (`password`), serta menetapkan sesi dan hak akses sesuai role. |
| **Prasyarat** | Pengguna telah memiliki akun aktif pada tabel `users` dan membuka halaman `/login`. |
| **Pascasarat** | Sesi autentikasi terbentuk pada sistem dan pengguna diarahkan ke dashboard sesuai perannya. |
| **Alur Dasar (Normal)** | 1. Pengguna mengakses halaman login sistem.<br>2. Sistem menampilkan antarmuka formulir autentikasi.<br>3. Pengguna memasukkan `username` dan `password`, lalu mengirim formulir.<br>4. Sistem melakukan verifikasi kredensial melalui fungsi otentikasi.<br>5. Sistem meregenerasi ID sesi pengguna untuk mencegah session fixation.<br>6. Sistem memeriksa status `must_change_password`. Jika bernilai `false`, sistem mengarahkan pengguna ke dashboard role terkait (Alumni, Admin Prodi, Admin Fakultas, Admin Biro III, atau Superadmin). |
| **Alur Alternatif** | **A1. Penggantian Kata Sandi Wajib:** Pada langkah 6, jika akun terdeteksi memiliki status `must_change_password = true`, sistem mengalihkan alur ke **UC02 (Mengubah Kata Sandi)**.<br>**A2. Pemulihan Kata Sandi:** Pada langkah 3, pengguna yang lupa kata sandi memilih opsi "Lupa Kata Sandi" sehingga alur dialihkan ke **UC03 (Memulihkan Kata Sandi)**. |
| **Alur Eksepsi** | **E1. Kredensial Tidak Valid:** Kredensial tidak cocok dengan basis data; sistem menolak autentikasi dan menampilkan pesan peringatan.<br>**E2. Pembatasan Percobaan Login (Rate Limiting):** Pengguna gagal login melebihi ambang batas yang ditentukan; sistem memblokir upaya login sementara waktu (HTTP 429). |
| **Rujukan Teknis** | Route: `GET /login`, `POST /login` \| Controller: `LoginController` \| Model/Tabel: `User`, tabel `users`. |

---

### UC02 — Mengubah Kata Sandi

| Elemen | Spesifikasi |
| :--- | :--- |
| **Identitas Use Case** | UC02 — Mengubah Kata Sandi |
| **Aktor** | Alumni, `admin` (`admin_prodi`, `admin_fakultas`, `admin_biro3`), Superadmin (ITC) |
| **Deskripsi** | Melakukan pembaruan kata sandi akun pengguna, baik sebagai pemenuhan kewajiban login pertama kali bagi alumni maupun pembaruan kata sandi berkala atas inisiatif pengguna. |
| **Prasyarat** | Pengguna terautentikasi pada sesi aktif atau dialihkan secara otomatis oleh middleware autentikasi karena `must_change_password = true`. |
| **Pascasarat** | Hash kata sandi baru tersimpan pada database dan atribut `must_change_password` disetel menjadi `false`. |
| **Alur Dasar (Normal)** | 1. Pengguna membuka formulir ubah kata sandi.<br>2. Pengguna memasukkan kata sandi saat ini, kata sandi baru, dan konfirmasi kata sandi baru.<br>3. Sistem memvalidasi kesesuaian kata sandi lama dan memastikan kata sandi baru memenuhi kebijakan kompleksitas sandi.<br>4. Sistem memperbarui hash sandi pada basis data dan menyetel `must_change_password = false`.<br>5. Sistem menampilkan notifikasi keberhasilan dan mengarahkan pengguna ke dashboard utama. |
| **Alur Alternatif** | **A1. Pembatalan Perubahan:** Pengguna yang tidak berkewajiban ubah sandi dapat membatalkan dan kembali ke dashboard. |
| **Alur Eksepsi** | **E1. Kata Sandi Lama Salah:** Sistem menolak proses dan memberikan pesan kesalahan pada field kata sandi lama.<br>**E2. Validasi Sandi Baru Tidak Memenuhi Syarat:** Konfirmasi tidak cocok atau panjang sandi kurang dari batas minimum sistem. |
| **Rujukan Teknis** | Route: `GET /change-password`, `POST /change-password` \| Controller: `ChangePasswordController` \| Tabel: `users`. |

---

### UC03 — Memulihkan Kata Sandi

| Elemen | Spesifikasi |
| :--- | :--- |
| **Identitas Use Case** | UC03 — Memulihkan Kata Sandi |
| **Aktor** | Alumni, `admin` (`admin_prodi`, `admin_fakultas`, `admin_biro3`), Superadmin (ITC) |
| **Deskripsi** | Memfasilitasi pengguna yang lupa kata sandi untuk memperoleh tautan pemulihan melalui email dan menetapkan kata sandi baru secara mandiri. |
| **Prasyarat** | Pengguna memiliki email terdaftar pada akun sistem dan membuka halaman pemulihan kata sandi. |
| **Pascasarat** | Token pemulihan divalidasi, hash kata sandi baru diperbarui, dan token kedaluwarsa dihapus. |
| **Alur Dasar (Normal)** | 1. Pengguna membuka halaman lupa kata sandi pada `/forgot-password`.<br>2. Pengguna memasukkan alamat email akun terdaftar.<br>3. Sistem memverifikasi keberadaan email, men-generate token unik, dan mengirimkan URL reset ke kotak masuk email pengguna.<br>4. Pengguna membuka tautan reset yang diterima (`/reset-password/{token}`).<br>5. Pengguna memasukkan kata sandi baru dan konfirmasi kata sandi.<br>6. Sistem memvalidasi token serta menyimpan kata sandi baru.<br>7. Sistem menghapus token pemulihan dan mengarahkan pengguna kembali ke halaman login. |
| **Alur Alternatif** | **A1. Permintaan Ulang Token:** Pengguna dapat meminta pengiriman ulang tautan reset apabila email belum diterima dalam kurun waktu tertentu. |
| **Alur Eksepsi** | **E1. Token Kedaluwarsa atau Tidak Valid:** Tautan telah kedaluwarsa atau token tidak cocok; sistem menolak form dan meminta pengguna mengulang dari awal.<br>**E2. Email Tidak Ditemukan:** Sistem menampilkan pesan netral untuk menjaga keamanan data akun. |
| **Rujukan Teknis** | Route: `/forgot-password`, `/reset-password/{token}` \| Controller: `ForgotPasswordController`, `ResetPasswordController` \| Tabel: `password_reset_tokens`. |

---

### UC04 — Manajemen Akun

| Elemen | Spesifikasi |
| :--- | :--- |
| **Identitas Use Case** | UC04 — Manajemen akun |
| **Aktor** | Superadmin (ITC) |
| **Deskripsi** | Mengelola akun seluruh pengguna sistem (Admin Fakultas, Admin Prodi, Admin Biro III, dan Alumni), termasuk pembuatan akun manual, sinkronisasi massal akun alumni dari data yudisium, pembaruan status, dan peresetan kata sandi akun alumni ke default. |
| **Prasyarat** | Superadmin login dengan hak akses penuh dan membuka menu `/superadmin/manajemen-akun`. |
| **Pascasarat** | Perubahan akun tersimpan, akun baru terdaftar, atau kata sandi pengguna direset ke format default (tanggal lahir). |
| **Alur Dasar (Normal)** | 1. Superadmin membuka modul manajemen akun.<br>2. Sistem menampilkan daftar pengguna terfilter berdasarkan tab role (Admin, Alumni).<br>3. Superadmin dapat mencari akun berdasarkan nama, NIM, atau email.<br>4. Superadmin memilih aksi pembuatan akun baru, pengubahan profil akun, penonaktifan akun, atau reset sandi.<br>5. Sistem memvalidasi data dan menyimpan perubahan ke tabel `users`.<br>6. Sistem mencatat transaksi ke dalam log aktivitas audit. |
| **Alur Alternatif** | **A1. Sinkronisasi Akun Alumni Massal:** Superadmin menjalankan fungsi `syncAlumniAccounts` untuk membuat akun alumni baru secara otomatis dari data akademik yudisium terbaru.<br>**A2. Reset Kata Sandi Alumni:** Superadmin memilih reset sandi; sistem mengembalikan kata sandi alumni ke tanggal lahir (`ddmmyyyy`) dan menyetel kembali `must_change_password = true`. |
| **Alur Eksepsi** | **E1. Username atau Email Duplikat:** Sistem menolak pembuatan/pembaruan jika `username` atau `email` telah digunakan oleh akun lain. |
| **Rujukan Teknis** | Route: `/superadmin/manajemen-akun`, `/superadmin/manajemen-akun/sync` \| Controller: `UserManagementController` \| Tabel: `users`, `data_akademik`. |

---

### UC05 — Melihat Log Aktivitas

| Elemen | Spesifikasi |
| :--- | :--- |
| **Identitas Use Case** | UC05 — Melihat Log Aktivitas |
| **Aktor** | Superadmin (ITC) |
| **Deskripsi** | Memantau seluruh rekaman jejak audit sistem (*audit trail*) yang mencatat aktivitas pengguna, riwayat autentikasi, perubahan data kritis, persetujuan verifikasi, dan eksekusi sinkronisasi. |
| **Prasyarat** | Superadmin login dan membuka menu `/superadmin/logs`. |
| **Pascasarat** | Riwayat log ditampilkan secara informatif tanpa melakukan perubahan terhadap data log. |
| **Alur Dasar (Normal)** | 1. Superadmin membuka halaman log aktivitas.<br>2. Sistem menampilkan daftar kronologis aktivitas sistem.<br>3. Superadmin dapat melakukan pemfilteran berdasarkan aksi (`action`), model entitas (`model_type`), rentang tanggal, atau pengguna.<br>4. Superadmin dapat membuka rincian perubahan data lama (*old values*) dan data baru (*new values*) serta alamat IP dan user agent. |
| **Alur Alternatif** | **A1. Pencarian Kata Kunci:** Superadmin mencari log spesifik menggunakan kata kunci deskripsi aktivitas. |
| **Alur Eksepsi** | **E1. Data Log Kosong:** Tidak ada aktivitas yang sesuai filter; sistem menampilkan status kosong secara informatif. |
| **Rujukan Teknis** | Route: `GET /superadmin/logs` \| Controller: `ActivityLogController` \| Tabel: `log_activities`. |

---

### UC06 — Mengelola Kuesioner Universitas

| Elemen | Spesifikasi |
| :--- | :--- |
| **Identitas Use Case** | UC06 — Mengelola kuesioner universitas |
| **Aktor** | Superadmin (ITC) |
| **Deskripsi** | Mengelola instrumen kuesioner tracer study tingkat universitas (standar Dikti), meliputi penetapan paket tahun kuesioner aktif, pengelolaan bagian kuesioner (*sections*), butir pertanyaan (*questions*), opsi jawaban (*options*), dan aturan percabangan (*branching logic*). |
| **Prasyarat** | Superadmin login dan membuka modul `/superadmin/kuesioner`. |
| **Pascasarat** | Konfigurasi struktur instrumen tersimpan dan menjadi acuan langsung pada formulir kuesioner alumni. |
| **Alur Dasar (Normal)** | 1. Superadmin membuka modul kuesioner universitas.<br>2. Superadmin membuat atau memilih paket kuesioner tahunan aktif.<br>3. Superadmin mengelola bagian/section kuesioner (menambah, mengubah judul/deskripsi, mengurutkan).<br>4. Superadmin menambahkan pertanyaan, memilih tipe input (teks, pilihan tunggal, jamak, rating, dll.), serta menyusun opsi jawaban.<br>5. Superadmin menyusun kondisi percabangan logika (*jump_to* dan *tampil_di*).<br>6. Sistem memvalidasi dan menyimpan struktur instrumen. |
| **Alur Alternatif** | **A1. Duplikasi Paket:** Superadmin menduplikasi instrumen tahun sebelumnya untuk digunakan pada tahun kuesioner baru.<br>**A2. Penonaktifan Butir Pertanyaan:** Superadmin menonaktifkan pertanyaan tertentu tanpa menghapus riwayat jawaban responden terdahulu. |
| **Alur Eksepsi** | **E1. Kode Pertanyaan Duplikat:** Kode pertanyaan unik telah digunakan; sistem menolak penyimpanan.<br>**E2. Percabangan Tidak Valid:** Target *jump_to* mengarah ke kode pertanyaan yang tidak ditemukan. |
| **Rujukan Teknis** | Route: `/superadmin/kuesioner`, `/superadmin/sections`, `/superadmin/pertanyaan` \| Controller: `KuesionerController` \| Tabel: `kuesioner`, `kuesioner_section`, `kuesioner_pertanyaan`, `kuesioner_opsi`. |

---

### UC07 — Mengelola Kuesioner Pengguna Lulusan

| Elemen | Spesifikasi |
| :--- | :--- |
| **Identitas Use Case** | UC07 — Mengelola kuesioner pengguna lulusan |
| **Aktor** | Superadmin (ITC) |
| **Deskripsi** | Mengatur instrumen survei evaluasi atasan/pengguna lulusan (aspek kepuasan etika, keahlian, kerja sama, dan komunikasi), skala penilaian, butir evaluasi karakteristik perusahaan, dan urutan tahapan formulir evaluasi. |
| **Prasyarat** | Superadmin login dan membuka menu `/superadmin/evaluasi-atasan`. |
| **Pascasarat** | Master instrumen evaluasi atasan diperbarui dan secara dinamis diterapkan pada halaman evaluasi publik. |
| **Alur Dasar (Normal)** | 1. Superadmin membuka menu pengelolaan instrumen evaluasi atasan.<br>2. Sistem menampilkan daftar aspek dan butir pertanyaan evaluasi.<br>3. Superadmin menambah atau memperbarui pertanyaan evaluasi, menentukan bobot skala, serta kategori kompetensi.<br>4. Superadmin menetapkan status aktif dan urutan tampilan pertanyaan.<br>5. Sistem memvalidasi dan menyimpan perubahan. |
| **Alur Alternatif** | **A1. Pengaturan Stepper Evaluasi:** Superadmin mengatur konfigurasi tahapan pengisian formulir evaluasi atasan. |
| **Alur Eksepsi** | **E1. Pertanyaan Memiliki Respon:** Sistem mencegah penghapusan permanen pertanyaan yang telah memiliki data respon survei masuk. |
| **Rujukan Teknis** | Route: `/superadmin/evaluasi-atasan` \| Controller: `EvaluasiAtasanController` \| Tabel: `pertanyaan_evaluasi_atasan`. |

---

### UC08 — Mengelola Master Wilayah

| Elemen | Spesifikasi |
| :--- | :--- |
| **Identitas Use Case** | UC08 — Mengelola master Wilayah |
| **Aktor** | Superadmin (ITC) |
| **Deskripsi** | Mengelola data referensi geografis sistem yang mencakup provinsi, kabupaten/kota, serta negara berstandar ISO-3166-1 alpha-2 sebagai data acuan domisili alumni dan kantor perusahaan. |
| **Prasyarat** | Superadmin login dan membuka menu `/superadmin/master-wilayah`. |
| **Pascasarat** | Data wilayah tersimpan dan menjadi acuan validasi referensi foreign key di seluruh sistem. |
| **Alur Dasar (Normal)** | 1. Superadmin membuka modul master wilayah.<br>2. Sistem menampilkan data provinsi, kabupaten/kota, dan negara.<br>3. Superadmin dapat menambah, mengubah, atau memperbarui nama dan kode provinsi (`kode_provinsi`) serta kabupaten/kota (`kode_kabupaten`).<br>4. Superadmin dapat mengelola kode dan nama negara (`kode_negara`).<br>5. Sistem menyimpan entitas wilayah baru dengan integritas referensial. |
| **Alur Alternatif** | **A1. Pengelolaan UMP:** Dari entitas provinsi terpilih, Superadmin memperluas aksi ke pengelolaan UMP melalui **UC09 (Mengelola Master UMP)**. |
| **Alur Eksepsi** | **E1. Kode Wilayah Duplikat:** Kode wilayah yang dimasukkan sudah ada; sistem menolak penyimpanan.<br>**E2. Hapus Wilayah Berelasi:** Penghapusan ditolak jika entitas wilayah sedang digunakan oleh data profil alumni atau perusahaan aktif. |
| **Rujukan Teknis** | Route: `/superadmin/master-wilayah` \| Controller: `MasterWilayahController` \| Tabel: `propinsi`, `kabupaten`, `ref_negara`. |

---

### UC09 — Mengelola Master UMP

| Elemen | Spesifikasi |
| :--- | :--- |
| **Identitas Use Case** | UC09 — Mengelola Master UMP |
| **Aktor** | Superadmin (ITC) |
| **Deskripsi** | Menetapkan dan memperbarui besaran nilai Upah Minimum Provinsi (UMP) tahunan untuk masing-masing provinsi sebagai nilai ambang batas evaluasi indikator kinerja utama penghasilan alumni (IKU-1). Use case ini memperluas UC08. |
| **Prasyarat** | Data entitas provinsi telah tersedia pada UC08 dan Superadmin membuka tab/menu UMP. |
| **Pascasarat** | Nilai nominal UMP per tahun dan provinsi tersimpan dan digunakan dalam perhitungan analitik IKU-1. |
| **Alur Dasar (Normal)** | 1. Superadmin memilih provinsi tertentu pada modul wilayah.<br>2. Superadmin membuka data riwayat penetapan UMP provinsi tersebut.<br>3. Superadmin menginput tahun penetapan dan nilai nominal UMP.<br>4. Sistem memvalidasi keunikan kombinasi `kode_provinsi` dan `tahun`.<br>5. Sistem menyimpan data UMP.<br>6. Mesin analitik IKU-1 langsung menggunakan nilai acuan baru tersebut. |
| **Alur Alternatif** | **A1. Duplikasi/Salin UMP Antartahun:** Superadmin menyalin data penetapan UMP dari tahun sebelumnya dengan persentase penyesuaian tertentu. |
| **Alur Eksepsi** | **E1. Kombinasi Provinsi dan Tahun Ganda:** Nilai UMP untuk provinsi dan tahun tersebut sudah terdaftar; sistem menolak duplikasi dan menyarankan pembaruan nilai. |
| **Rujukan Teknis** | Route: `/superadmin/master-wilayah` (Submodul UMP) \| Controller: `MasterWilayahController` \| Tabel: `ump`. |

---

### UC10 — Mengelola Profile Alumni

| Elemen | Spesifikasi |
| :--- | :--- |
| **Identitas Use Case** | UC10 — Mengelola profile alumni |
| **Aktor** | Alumni |
| **Deskripsi** | Mengelola informasi pribadi lulusan, meliputi kontak pribadi, alamat domisili, status aktivitas (bekerja, studi lanjut, wiraswasta, mencari kerja), pemilihan instansi tempat bekerja, pengisian data atasan langsung, dan URL profil LinkedIn. |
| **Prasyarat** | Alumni berhasil login dan membuka halaman `/alumni/profile`. |
| **Pascasarat** | Data profil tersimpan pada tabel `biodata` dan relasi karier diperbarui. |
| **Alur Dasar (Normal)** | 1. Alumni membuka halaman profil.<br>2. Sistem menampilkan data akademik tetap (NIM, Nama, Prodi, Tahun Lulus) dan form data dinamis.<br>3. Alumni memperbarui nomor telepon, email personal, domisili wilayah, dan status kegiatan.<br>4. Untuk alumni yang bekerja, alumni memilih instansi tempat bekerja dari daftar perusahaan yang terdaftar.<br>5. Alumni mengisikan nama, jabatan, dan email atasan langsung untuk survei pengguna lulusan.<br>6. Alumni memasukkan tautan URL profil LinkedIn.<br>7. Alumni menekan tombol simpan profil.<br>8. Sistem memvalidasi dan menyimpan pembaruan ke basis data. |
| **Alur Alternatif** | **A1. Perusahaan Belum Terdaftar:** Pada langkah 4, jika nama perusahaan alumni belum ada di daftar, alumni memilih opsi tambah perusahaan sehingga alur beralih ke **UC11 (Menambah daftar perusahaan)**.<br>**A2. Pengiriman Undangan Evaluasi Atasan:** Setelah data atasan disimpan, sistem menyediakan opsi bagi alumni untuk langsung memicu pengiriman email evaluasi ke atasan. |
| **Alur Eksepsi** | **E1. Format Data Tidak Valid:** Format email atau URL LinkedIn salah; sistem menampilkan pesan validasi.<br>**E2. Kode Wilayah Tidak Cocok:** Wilayah yang dipilih tidak valid terhadap master data. |
| **Rujukan Teknis** | Route: `GET /alumni/profile`, `POST /alumni/profile` \| Controller: `AlumniProfileController` \| Tabel: `biodata`, `perusahaan`, `atasan`. |

---

### UC11 — Menambah Daftar Perusahaan

| Elemen | Spesifikasi |
| :--- | :--- |
| **Identitas Use Case** | UC11 — Menambah daftar perusahaan |
| **Aktor** | Alumni |
| **Deskripsi** | Menambahkan data instansi/perusahaan baru tempat alumni bekerja apabila entitas perusahaan tersebut belum tersedia pada direktori master sistem. Status perusahaan baru berstatus 'Menunggu Verifikasi'. Use case ini memperluas UC10. |
| **Prasyarat** | Alumni berada pada formulir profil (UC10) dan tidak menemukan perusahaan yang sesuai. |
| **Pascasarat** | Record perusahaan baru tersimpan pada tabel `perusahaan` dengan status verifikasi `Menunggu Verifikasi`. |
| **Alur Dasar (Normal)** | 1. Alumni menekan tombol "Tambah Perusahaan Baru" pada modal pemilihan perusahaan.<br>2. Sistem menampilkan formulir pengajuan instansi.<br>3. Alumni menginput nama perusahaan, sektor industri, jenis instansi, alamat, dan wilayah kota/provinsi/negara.<br>4. Sistem memeriksa kesamaan atau kemiripan nama terhadap master perusahaan yang sudah terverifikasi.<br>5. Jika belum ada, sistem menyimpan data perusahaan dengan atribut `status_verifikasi = 'Menunggu Verifikasi'` dan mencatat ID alumni pengusul.<br>6. Perusahaan baru terpilih secara otomatis pada profil alumni yang bersangkutan. |
| **Alur Alternatif** | **A1. Pemilihan Rekomendasi Mirip:** Pada langkah 4, sistem mendeteksi perusahaan dengan nama identik/mirip yang sudah terverifikasi; alumni memilih perusahaan rekomendasi tersebut tanpa membuat record ganda. |
| **Alur Eksepsi** | **E1. Data Wajib Kosong:** Nama perusahaan atau sektor tidak diisi; sistem menolak pengajuan. |
| **Rujukan Teknis** | Route: `POST /alumni/company` \| Controller: `CompanyController` \| Tabel: `perusahaan`. |

---

### UC12 — Mengisi Kuesioner Universitas

| Elemen | Spesifikasi |
| :--- | :--- |
| **Identitas Use Case** | UC12 — Mengisi Kuesioner Universitas |
| **Aktor** | Alumni |
| **Deskripsi** | Mengisi instrumen kuesioner pelacakan jejak alumni tingkat universitas standar Dikti/Kemendikbudristek yang aktif, dengan dukungan navigasi dinamis dan aturan percabangan pertanyaan. |
| **Prasyarat** | Alumni login dan paket kuesioner universitas aktif tersedia di sistem. |
| **Pascasarat** | Jawaban kuesioner tersimpan pada tabel `kuesioner_jawaban` dan status kuesioner alumni diperbarui. |
| **Alur Dasar (Normal)** | 1. Alumni membuka menu kuesioner universitas.<br>2. Sistem memuat bagian kuesioner, pertanyaan aktif, dan jawaban sementara yang pernah diisi.<br>3. Alumni mengisi jawaban pada tiap butir pertanyaan.<br>4. Sistem secara dinamis menampilkan atau menyembunyikan pertanyaan bersyarat berdasarkan aturan percabangan (*branching logic*).<br>5. Alumni menekan tombol simpan atau submit kuesioner.<br>6. Sistem memvalidasi kelengkapan seluruh butir wajib.<br>7. Sistem menyimpan jawaban ke dalam basis data dan menandai status kuesioner universitas telah selesai. |
| **Alur Alternatif** | **A1. Simpan Jawaban Sementara:** Alumni menyimpan draf jawaban sebagian dan dapat melanjutkan kembali di lain waktu. |
| **Alur Eksepsi** | **E1. Pertanyaan Wajib Belum Diisi:** Sistem menandai butir pertanyaan yang terlewat dan menahan proses submit sampai seluruh pertanyaan wajib terisi. |
| **Rujukan Teknis** | Route: `GET /alumni/kuesioner`, `POST /alumni/kuesioner` \| Controller: `AlumniKuesionerController` \| Tabel: `kuesioner_jawaban`. |

---

### UC13 — Mengisi Kuesioner Program Studi

| Elemen | Spesifikasi |
| :--- | :--- |
| **Identitas Use Case** | UC13 — Mengisi Kuesioner Program Studi |
| **Aktor** | Alumni |
| **Deskripsi** | Menjawab butir-butir pertanyaan kuesioner spesifik yang dirancang khusus oleh program studi asal alumni untuk mengukur capaian pembelajaran lulusan (CPL) dan evaluasi kurikulum spesifik prodi. |
| **Prasyarat** | Alumni login dan program studi alumni memiliki instrumen kuesioner prodi yang aktif. |
| **Pascasarat** | Jawaban tersimpan pada tabel `kuesioner_jawaban` dengan penanda respon kuesioner program studi. |
| **Alur Dasar (Normal)** | 1. Alumni membuka menu kuesioner prodi.<br>2. Sistem memuat butir pertanyaan yang aktif untuk `prodi_id` alumni.<br>3. Alumni mengisi respon sesuai tipe pertanyaan (skala Likert, pilihan berganda, uraian masukan kurikulum).<br>4. Alumni menekan tombol simpan jawaban kuesioner prodi.<br>5. Sistem memvalidasi kelengkapan jawaban dan menyimpannya ke basis data.<br>6. Sistem menampilkan pemberitahuan bahwa kuesioner prodi berhasil diselesaikan. |
| **Alur Alternatif** | **A1. Kuesioner Prodi Belum Diterbitkan:** Jika prodi belum mengaktifkan instrumen, sistem menampilkan informasi bahwa kuesioner prodi belum tersedia tanpa mengganggu kelulusan pengisian tracer universitas. |
| **Alur Eksepsi** | **E1. Gagal Menyimpan Jawaban:** Gangguan koneksi saat submit; sistem menampilkan pesan galat tanpa menghilangkan isian formulir. |
| **Rujukan Teknis** | Route: `GET /alumni/kuesioner-prodi`, `POST /alumni/kuesioner-prodi` \| Controller: `AlumniKuesionerProdiController` \| Tabel: `kuesioner_jawaban`. |

---

### UC14 — Evaluasi Kinerja Alumni

| Elemen | Spesifikasi |
| :--- | :--- |
| **Identitas Use Case** | UC14 — Evaluasi kinerja Alumni |
| **Aktor** | Pengguna Lulusan (Atasan) |
| **Deskripsi** | Mengisi instrumen survei evaluasi kinerja dan kepuasan atasan terhadap lulusan UKDW melalui tautan token unik publik tanpa memerlukan registrasi akun pengguna. |
| **Prasyarat** | Atasan menerima email/tautan evaluasi berisi token unik yang valid pada tabel `evaluasi_atasan`. |
| **Pascasarat** | Jawaban survei tersimpan pada `kuesioner_jawaban`, status `is_submitted` pada `evaluasi_atasan` disetel `true`, dan waktu submit dicatat. |
| **Alur Dasar (Normal)** | 1. Atasan membuka tautan evaluasi `/evaluasi-atasan/{token}`.<br>2. Sistem memvalidasi keabsahan token dan menampilkan informasi nama alumni serta form evaluasi kinerja.<br>3. Atasan melengkapi konfirmasi data profil perusahaan.<br>4. Atasan mengisi penilaian skala kompetensi alumni (etika, keahlian bidang ilmu, bahasa asing, penggunaan teknologi, kerja sama, dan pengembangan diri).<br>5. Atasan memberikan masukan/saran perbaikan bagi lulusan UKDW.<br>6. Atasan menekan tombol kirim evaluasi.<br>7. Sistem menyimpan jawaban ke basis data, memperbarui status pengisian evaluasi menjadi selesai, dan menampilkan halaman ucapan terima kasih. |
| **Alur Alternatif** | **A1. Akses Token Telah Terisi:** Jika evaluasi telah diisi sebelumnya, sistem menampilkan halaman konfirmasi bahwa penilaian telah sukses tercatat. |
| **Alur Eksepsi** | **E1. Token Tidak Valid atau Kedaluwarsa:** Sistem menampilkan halaman 404/not found atau informasi bahwa tautan tidak dapat digunakan. |
| **Rujukan Teknis** | Route: `GET /evaluasi-atasan/{token}`, `POST /evaluasi-atasan/{token}` \| Controller: `EvaluasiAtasanPublicController` \| Tabel: `evaluasi_atasan`, `kuesioner_jawaban`. |

---

### UC15 — Mengelola Data Alumni

| Elemen | Spesifikasi |
| :--- | :--- |
| **Identitas Use Case** | UC15 — Mengelola data alumni |
| **Aktor** | `admin` (`admin_prodi`, `admin_fakultas`, `admin_biro3`), Superadmin (ITC) |
| **Deskripsi** | Menampilkan direktori alumni, memfilter berdasarkan kriteria akademik/kelulusan, melihat detail komprehensif profil akademik, riwayat karier, status pengisian kuesioner, serta memperbarui data kontak atau snapshot atasan sesuai batasan lingkup wewenang. |
| **Prasyarat** | Aktor login dan memiliki wewenang akses direktori alumni pada tingkat prodi, fakultas, atau universitas. |
| **Pascasarat** | Data direktori ditampilkan; setiap pembaruan data biodata atau status tersimpan dengan jejak audit. |
| **Alur Dasar (Normal)** | 1. Admin mengakses modul direktori alumni.<br>2. Sistem menerapkan filter otomatis sesuai batasan peran (admin prodi hanya melihat prodi miliknya; admin fakultas melihat prodi dalam fakultasnya; biro 3 dan superadmin melihat seluruh prodi).<br>3. Admin mencari data berdasarkan NIM, nama, tahun angkatan, atau status kerja.<br>4. Admin memilih satu record alumni untuk melihat detail lengkap.<br>5. Sistem menampilkan rekap data akademik, isian kuesioner, data karier, dan status evaluasi atasan.<br>6. Admin dapat melakukan penyuntingan data profil yang diizinkan dan menyimpannya. |
| **Alur Alternatif** | **A1. Sinkronisasi LinkedIn:** Admin memilih opsi sinkronisasi data profil dari LinkedIn melalui **UC16 (Sinkronisasi Linkedin)**.<br>**A2. Mengirim Komunikasi Email:** Admin memilih opsi pengiriman komunikasi ke alumni melalui **UC17 (Menghubungi Alumni)**.<br>**A3. Ekspor Data Alumni:** Admin memilih opsi unduh data melalui **UC19 (Ekspor Laporan)**. |
| **Alur Eksepsi** | **E1. Akses Di Luar Lingkup (Unauthorized Scope):** Admin mencoba mengakses detail alumni dari program studi di luar hak aksesnya; sistem memblokir akses dan mengembalikan respon 403 Forbidden. |
| **Rujukan Teknis** | Route: `/{role}/alumni`, `/{role}/alumni/{id}` \| Controller: `AlumniManagementController` \| Tabel: `biodata`, `data_akademik`, `yudisium`. |

---

### UC16 — Sinkronisasi LinkedIn

| Elemen | Spesifikasi |
| :--- | :--- |
| **Identitas Use Case** | UC16 — Sinkronisasi Linkedin |
| **Aktor** | `admin` (`admin_prodi`, `admin_fakultas`, `admin_biro3`), Superadmin (ITC), Apify (`<<system>>`) |
| **Deskripsi** | Melakukan penarikan data riwayat pengalaman kerja dan keahlian alumni secara terotomatisasi dari LinkedIn memanfaatkan layanan eksternal Apify, menyimpan data hasil scraping ke tabel staging, serta menyediakan mekanisme review dan approval sebelum data diterapkan ke profil alumni. Use case ini memperluas UC15. |
| **Prasyarat** | Alumni memiliki URL profil LinkedIn yang valid pada profilnya, dan token API Apify terkonfigurasi. |
| **Pascasarat** | Data staging tersimpan pada `linkedin_sync_results`; hasil persetujuan (approval) admin tersimpan ke tabel `biodata`. |
| **Alur Dasar (Normal)** | 1. Admin memilih alumni atau sekumpulan alumni yang memiliki URL profil LinkedIn pada modul data alumni.<br>2. Admin menekan tombol "Sinkronisasi LinkedIn".<br>3. Sistem mengirimkan request scraping profil ke layanan pihak ketiga Apify.<br>4. Apify memproses penarikan profil dan mengembalikan payload data terstruktur (posisi pekerjaan, nama instansi, masa kerja, keterampilan).<br>5. Sistem menyimpan payload ke tabel penampungan (`linkedin_sync_results`) dengan status `pending` dan otomatis mencadangkan snapshot data ke penyimpanan disk.<br>6. Admin meninjau data perbandingan antara data lama dan data scraping.<br>7. Admin menyetujui (*approve*) integrasi data.<br>8. Sistem memperbarui data karier dan keahlian pada tabel `biodata` alumni. |
| **Alur Alternatif** | **A1. Penolakan Data Hasil Scraping:** Pada langkah 7, jika data scraping tidak relevan, admin menolak (*reject*) sinkronisasi; data profil alumni tidak mengalami perubahan. |
| **Alur Eksepsi** | **E1. Kegagalan Koneksi Apify / Limit Kuota Habis:** Layanan Apify gagal merespon atau kuota habis; sistem mencatat status `failed` pada riwayat sinkronisasi dan tidak mengubah data profil yang ada.<br>**E2. Profil LinkedIn Bersifat Privat:** Payload data tidak dapat ditarik; sistem memberikan notifikasi kesalahan spesifik. |
| **Rujukan Teknis** | Route: `/{role}/linkedin-sync`, `/{role}/linkedin-sync/{id}/approve` \| Controller: `LinkedinSyncController` \| Service: `ApifyScraperService` \| Tabel: `linkedin_sync_results`. |

---

### UC17 — Menghubungi Alumni

| Elemen | Spesifikasi |
| :--- | :--- |
| **Identitas Use Case** | UC17 — Menghubungi Alumni |
| **Aktor** | `admin` (`admin_prodi`, `admin_fakultas`, `admin_biro3`), Superadmin (ITC) |
| **Deskripsi** | Mengirimkan pesan komunikasi resmi melalui email kepada alumni tertentu atau kelompok alumni berdasarkan kriteria tertentu untuk undangan pengisian kuesioner tracer study, pengingat kelengkapan profil, atau informasi universitas. Use case ini memperluas UC15. |
| **Prasyarat** | Alumni memiliki alamat email yang valid dan admin memiliki wewenang akses terhadap alumni bersangkutan. |
| **Pascasarat** | Pesan email terkirim ke alamat email alumni dan riwayat pengiriman komunikasi tercatat pada sistem. |
| **Alur Dasar (Normal)** | 1. Admin membuka data alumni yang ingin dihubungi pada direktori alumni.<br>2. Admin menekan tombol "Hubungi Alumni" / "Kirim Email".<br>3. Admin memilih templat pesan (undangan kuesioner, pengingat, atau pesan kustom) dan menyunting isi pesan.<br>4. Admin menekan tombol kirim.<br>5. Sistem memvalidasi kelayakan alamat email penerima dan mengirimkan pesan melalui subsistem mailer.<br>6. Sistem mencatat waktu dan status pengiriman ke dalam log komunikasi. |
| **Alur Alternatif** | **A1. Pengiriman Massal (Broadcast):** Admin memfilter sekelompok alumni (misalnya alumni yang belum mengisi kuesioner) dan mengirimkan pesan pengingat secara serentak. |
| **Alur Eksepsi** | **E1. Alamat Email Tidak Valid atau Gagal Kirim:** Layanan SMTP gagal mengirimkan pesan; sistem menampilkan status pengiriman gagal dan mencatat galat pada log. |
| **Rujukan Teknis** | Route: `/{role}/alumni/{id}/contact`, `POST /{role}/alumni/broadcast` \| Controller: `ContactAlumniController` \| Mailer: `TracerNotificationMail`. |

---

### UC18 — Melihat Laporan

| Elemen | Spesifikasi |
| :--- | :--- |
| **Identitas Use Case** | UC18 — Melihat Laporan |
| **Aktor** | `admin` (`admin_prodi`, `admin_fakultas`, `admin_biro3`), Superadmin (ITC) |
| **Deskripsi** | Mengakses dan memantau dasbor analitik pelaporan eksekutif, indikator kinerja utama IKU-1 (lulusan mendapat pekerjaan layak, melanjutkan studi, atau berwiraswasta), statistik kuesioner universitas, kuesioner prodi, dan analitik kepuasan atasan secara visual dan interaktif tanpa jeda perpindahan tab. |
| **Prasyarat** | Aktor login dan memiliki wewenang pemantauan laporan pada level prodi, fakultas, atau universitas. |
| **Pascasarat** | Ringkasan analitik, grafik distribusi, dan tabel drill-down disajikan sesuai dengan parameter filter yang dipilih. |
| **Alur Dasar (Normal)** | 1. Admin membuka menu Laporan / Analitik.<br>2. Sistem menerapkan batasan data (scope) sesuai role dan menghitung agregasi data analitik secara otomatis dari view database (`v_analytics_*`).<br>3. Sistem menampilkan ringkasan kilas indikator, grafik pencapaian IKU-1, masa tunggu kerja, keselarasan horizontal/vertikal, dan sebaran pendapatan terhadap UMP.<br>4. Admin dapat memilih filter tahun yudisium, fakultas, atau program studi.<br>5. Admin dapat mengklik elemen batang grafik untuk langsung membuka tabel rincian data (drill-down) secara inline tepat di bawah grafik. |
| **Alur Alternatif** | **A1. Pemantauan Analitik Prodi:** Admin membuka sub-analitik kuesioner program studi untuk mengevaluasi umpan balik spesifik kurikulum prodi.<br>**A2. Ekspor Laporan:** Dari halaman laporan yang sedang dilihat, admin memilih opsi ekspor untuk mengunduh rekapitulasi data melalui **UC19 (Ekspor Laporan)**. |
| **Alur Eksepsi** | **E1. Tidak Ada Data Responden:** Filter yang dipilih menghasilkan nol responden; sistem menampilkan visualisasi kondisi kosong secara informatif. |
| **Rujukan Teknis** | Route: `/{role}/analitik`, `/{role}/analitik-prodi`, `/{role}/analitik-atasan` \| Controller: `AnalitikController` \| View Database: `v_analytics_iku1`, `v_alumni_tracer_univ_status`. |

---

### UC19 — Ekspor Laporan

| Elemen | Spesifikasi |
| :--- | :--- |
| **Identitas Use Case** | UC19 — Ekspor Laporan |
| **Aktor** | `admin` (`admin_prodi`, `admin_fakultas`, `admin_biro3`), Superadmin (ITC) |
| **Deskripsi** | Menghasilkan dan mengunduh berkas laporan rekapitulasi data alumni, hasil kuesioner tracer study, maupun ringkasan capaian analitik ke dalam format lembar kerja Excel (`.xlsx`) atau arsip terkompresi (`.zip`). Use case ini memperluas UC15 dan UC18. |
| **Prasyarat** | Aktor berada pada halaman data alumni (UC15) atau halaman laporan analitik (UC18) dan menekan tombol ekspor. |
| **Pascasarat** | Berkas spreadsheet yang berisi data sesuai kriteria filter berhasil diunduh ke komputer pengguna. |
| **Alur Dasar (Normal)** | 1. Admin memilih kriteria data yang ingin diekspor (rentang tahun lulus, prodi, status kerja).<br>2. Admin menekan tombol "Ekspor ke Excel".<br>3. Sistem membaca data dari database sesuai batasan wewenang aktor.<br>4. Sistem men-generate berkas spreadsheet dengan pemformatan kolom kanonik yang rapi.<br>5. Sistem mengirimkan respons unduhan berkas kepada peramban pengguna. |
| **Alur Alternatif** | **A1. Ekspor Data Alumni Individual:** Admin mengunduh lembar biodata lengkap satu alumni tertentu dalam format Excel.<br>**A2. Ekspor Arsip Massal (ZIP):** Admin memilih opsi ekspor arsip kumpulan berkas kuesioner dalam format ZIP. |
| **Alur Eksepsi** | **E1. Data Kosong:** Tidak ada record data yang memenuhi filter; sistem menampilkan pemberitahuan bahwa tidak ada data untuk diekspor. |
| **Rujukan Teknis** | Route: `/{role}/alumni/export-excel`, `/{role}/alumni/export-zip` \| Controller: `ExportController` \| Library: `Maatwebsite\Excel`. |

---

### UC20 — Mengelola Kuesioner Prodi

| Elemen | Spesifikasi |
| :--- | :--- |
| **Identitas Use Case** | UC20 — Mengelola kuesioner prodi |
| **Aktor** | `admin_prodi`, Superadmin (ITC) |
| **Deskripsi** | Mengelola instrumen pertanyaan kuesioner khusus program studi, meliputi pembuatan section kuesioner prodi, penambahan butir pertanyaan spesifik capaian lulusan prodi, penentuan tipe respon (Likert, pilihan ganda, isian teks), penataan urutan pertanyaan, dan pengelolaan opsi jawaban. |
| **Prasyarat** | `admin_prodi` login ke sistem (dibatasi hanya pada prodi miliknya) atau Superadmin login (dapat memilih prodi yang dituju). |
| **Pascasarat** | Struktur instrumen kuesioner prodi tersimpan dan otomatis menjadi instrumen formulir pada UC13 bagi alumni prodi tersebut. |
| **Alur Dasar (Normal)** | 1. Admin Prodi membuka menu Kuesioner Program Studi.<br>2. Sistem memuat daftar bagian (*section*) dan pertanyaan kuesioner prodi yang telah ada.<br>3. Admin Prodi menambah bagian baru atau memilih bagian yang akan diisi pertanyaan.<br>4. Admin Prodi menambahkan pertanyaan baru, memasukkan teks pertanyaan, menentukan apakah pertanyaan wajib diisi, serta memilih jenis input.<br>5. Untuk pertanyaan pilihan ganda, Admin Prodi menambahkan daftar opsi jawaban.<br>6. Admin Prodi mengatur urutan posisi pertanyaan.<br>7. Sistem memvalidasi dan menyimpan struktur kuesioner prodi ke basis data. |
| **Alur Alternatif** | **A1. Pengubahan Status Keaktifan Pertanyaan:** Admin menonaktifkan butir pertanyaan yang sudah tidak relevan tanpa menghapus data respon masa lalu.<br>**A2. Pengelolaan oleh Superadmin:** Superadmin membuka modul kuesioner prodi universitas, memilih program studi target, lalu melakukan pengelolaan instrumen. |
| **Alur Eksepsi** | **E1. Hak Akses Di Luar Prodi:** Admin prodi mencoba memanipulasi pertanyaan kuesioner prodi lain; sistem memblokir tindakan dengan respon 403 Forbidden.<br>**E2. Penghapusan Pertanyaan yang Telah Memiliki Jawaban:** Sistem menolak penghapusan permanen jika pertanyaan telah dijawab oleh alumni untuk menjaga integritas data historis. |
| **Rujukan Teknis** | Route: `/prodi/kuesioner`, `/superadmin/prodi-kuesioner` \| Controller: `ProdiKuesionerController` \| Tabel: `kuesioner_section`, `kuesioner_pertanyaan`, `kuesioner_opsi`. |

---

### UC21 — Master Data Perusahaan

| Elemen | Spesifikasi |
| :--- | :--- |
| **Identitas Use Case** | UC21 — Master Data Perusahaan |
| **Aktor** | `admin` (`admin_prodi`, `admin_fakultas`, `admin_biro3`), Superadmin (ITC) |
| **Deskripsi** | Menampilkan direktori master instansi/perusahaan tempat alumni bekerja, mencari perusahaan berdasarkan nama atau sektor industri, melihat detail data domisili kantor perusahaan, serta mengelola kelengkapan atribut perusahaan. |
| **Prasyarat** | Aktor login dan memiliki hak akses ke modul master perusahaan. |
| **Pascasarat** | Data direktori perusahaan ditampilkan; pembaruan data atribut perusahaan tersimpan pada basis data. |
| **Alur Dasar (Normal)** | 1. Admin membuka menu Master Perusahaan.<br>2. Sistem menampilkan daftar perusahaan terdaftar dengan status verifikasi, sektor industri, domisili provinsi/kabupaten/negara, dan jumlah alumni yang bekerja.<br>3. Admin mencari perusahaan berdasarkan kata kunci nama atau memfilter berdasarkan status verifikasi.<br>4. Admin membuka detail data perusahaan.<br>5. Admin dapat memperbarui klasifikasi industri, alamat, dan narasi profil perusahaan.<br>6. Sistem memvalidasi dan menyimpan pembaruan. |
| **Alur Alternatif** | **A1. Verifikasi Pengajuan Perusahaan:** Admin memilih perusahaan berstatus 'Menunggu Verifikasi' untuk diproses melalui **UC22 (Verifikasi Perusahaan)**. |
| **Alur Eksepsi** | **E1. Data Perusahaan Tidak Ditemukan:** ID perusahaan tidak valid; sistem mengembalikan halaman 404. |
| **Rujukan Teknis** | Route: `/{role}/perusahaan` \| Controller: `CompanyController` \| Tabel: `perusahaan`. |

---

### UC22 — Verifikasi Perusahaan

| Elemen | Spesifikasi |
| :--- | :--- |
| **Identitas Use Case** | UC22 — Verifikasi Perusahaan |
| **Aktor** | `admin` (`admin_prodi`, `admin_fakultas`, `admin_biro3`), Superadmin (ITC) |
| **Deskripsi** | Memeriksa antrean pendaftaran perusahaan baru yang diajukan oleh alumni (UC11), mengevaluasi validitas entitas dan keabsahan wilayah, serta menetapkan status verifikasi: disetujui (*Terverifikasi*), ditolak (*Ditolak*), atau digabungkan (*Merged/Replaced*) ke data perusahaan yang sudah ada. Use case ini memperluas UC21. |
| **Prasyarat** | Terdapat data perusahaan dengan status `Menunggu Verifikasi` pada master perusahaan (UC21). |
| **Pascasarat** | Status verifikasi perusahaan berubah menjadi `Terverifikasi` atau `Ditolak`, atau seluruh relasi alumni dipindahkan ke record perusahaan kanonik yang dipilih saat penggabungan. |
| **Alur Dasar (Normal)** | 1. Admin membuka tab antrean verifikasi pada menu perusahaan.<br>2. Sistem menampilkan daftar pengajuan perusahaan baru beserta identitas alumni pengusul.<br>3. Admin memeriksa kesesuaian nama instansi, wilayah, dan sektor.<br>4. Sistem menampilkan rekomendasi perusahaan serupa yang sudah terverifikasi.<br>5. Jika data valid dan benar-benar entitas baru, admin menekan tombol "Verifikasi / Setujui".<br>6. Sistem memperbarui status perusahaan menjadi `Terverifikasi` dan mencatat ID admin peninjau serta stempel waktu verifikasi. |
| **Alur Alternatif** | **A1. Penggabungan Data Duplikat (Replace/Merge):** Admin mendeteksi bahwa perusahaan yang diajukan sebenarnya sudah ada (hanya berbeda variasi penulisan); admin memilih opsi "Gabungkan ke Perusahaan yang Ada", memilih perusahaan tujuan, dan sistem secara otomatis mengalihkan relasi profil alumni ke ID perusahaan kanonik tersebut.<br>**A2. Penolakan Pengajuan:** Admin menolak pengajuan perusahaan yang bersifat fiktif; status berubah menjadi `Ditolak`. |
| **Alur Eksepsi** | **E1. Penghapusan Dilarang:** Perusahaan yang berelasi dengan data profil alumni aktif tidak dapat dihapus secara keras (*hard delete*) untuk menjaga integritas data. |
| **Rujukan Teknis** | Route: `/{role}/perusahaan/{id}/verifikasi`, `POST /{role}/perusahaan/{id}/merge` \| Controller: `CompanyVerificationController` \| Tabel: `perusahaan`. |

---

### UC23 — Master Data Pengguna Lulusan

| Elemen | Spesifikasi |
| :--- | :--- |
| **Identitas Use Case** | UC23 — Master Data Pengguna Lulusan |
| **Aktor** | `admin` (`admin_prodi`, `admin_fakultas`, `admin_biro3`), Superadmin (ITC) |
| **Deskripsi** | Mengelola direktori data snapshot atasan/pengguna lulusan yang dimasukkan oleh alumni, memantau riwayat pengiriman token survei evaluasi, memvalidasi alamat email atasan, serta memicu pengiriman ulang tautan token evaluasi atasan yang belum diisi. |
| **Prasyarat** | Aktor login dan memiliki akses ke modul data atasan / pengguna lulusan. |
| **Pascasarat** | Data direktori atasan dan status respon survei ditampilkan; pemicuan kirim ulang token memperbarui data transaksi evaluasi. |
| **Alur Dasar (Normal)** | 1. Admin membuka menu Master Pengguna Lulusan.<br>2. Sistem menampilkan daftar atasan langsung beserta nama alumni terkait, instansi perusahaan, jabatan atasan, email, dan status pengisian evaluasi (Belum Dikirim, Menunggu Respon, Sudah Diisi).<br>3. Admin mencari record atasan berdasarkan nama atasan, instansi, atau nama alumni.<br>4. Admin dapat memperbarui alamat email atasan jika terdapat kesalahan ketik.<br>5. Admin menekan tombol "Kirim / Kirim Ulang Tautan Evaluasi".<br>6. Sistem membuat atau meregenerasi token unik evaluasi dan mengirimkan email undangan ke alamat email atasan. |
| **Alur Alternatif** | **A1. Pemfilteran Status Respon:** Admin memfilter daftar hanya menampilkan atasan yang belum mengisi evaluasi untuk keperluan tindak lanjut survei kepuasan. |
| **Alur Eksepsi** | **E1. Email Atasan Kosong atau Tidak Valid:** Sistem menolak pengiriman dan meminta admin melengkapi kontak email terlebih dahulu. |
| **Rujukan Teknis** | Route: `/{role}/pengguna-lulusan`, `POST /{role}/pengguna-lulusan/{id}/kirim-token` \| Controller: `AtasanManagementController` \| Tabel: `atasan`, `evaluasi_atasan`. |

---

## 5. Matriks Keterlacakan Aktor (Actor Traceability Matrix)

Tabel berikut memetakan keterhubungan 23 use case terhadap aktor-aktor sistem secara komprehensif:

| ID Use Case | Nama Use Case | Alumni | Pengguna Lulusan | admin_prodi | admin_fakultas | admin_biro3 | Superadmin (ITC) | Apify (`<<system>>`) |
| :--- | :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| **UC01** | Login | X | - | X | X | X | X | - |
| **UC02** | Mengubah Kata Sandi | X | - | X | X | X | X | - |
| **UC03** | Memulihkan Kata Sandi | X | - | X | X | X | X | - |
| **UC04** | Manajemen akun | - | - | - | - | - | X | - |
| **UC05** | Melihat Log Aktivitas | - | - | - | - | - | X | - |
| **UC06** | Mengelola kuesioner universitas | - | - | - | - | - | X | - |
| **UC07** | Mengelola kuesioner pengguna lulusan | - | - | - | - | - | X | - |
| **UC08** | Mengelola master Wilayah | - | - | - | - | - | X | - |
| **UC09** | Mengelola Master UMP | - | - | - | - | - | X | - |
| **UC10** | Mengelola profile alumni | X | - | - | - | - | - | - |
| **UC11** | Menambah daftar perusahaan | X | - | - | - | - | - | - |
| **UC12** | Mengisi Kuesioner Universitas | X | - | - | - | - | - | - |
| **UC13** | Mengisi Kuesioner Program Studi | X | - | - | - | - | - | - |
| **UC14** | Evaluasi kinerja Alumni | - | X | - | - | - | - | - |
| **UC15** | Mengelola data alumni | - | - | X | X | X | X | - |
| **UC16** | Sinkronisasi Linkedin | - | - | X | X | X | X | X |
| **UC17** | Menghubungi Alumni | - | - | X | X | X | X | - |
| **UC18** | Melihat Laporan | - | - | X | X | X | X | - |
| **UC19** | Ekspor Laporan | - | - | X | X | X | X | - |
| **UC20** | Mengelola kuesioner prodi | - | - | X | - | - | X | - |
| **UC21** | Master Data Perusahaan | - | - | X | X | X | X | - |
| **UC22** | Verifikasi Perusahaan | - | - | X | X | X | X | - |
| **UC23** | Master Data Pengguna Lulusan | - | - | X | X | X | X | - |

> *Catatan:* Tanda **X** menunjukkan aktor memiliki keterlibatan langsung atau melalui pewarisan peran `admin`. Hak akses pada `admin_prodi` dibatasi pada lingkup prodinya, `admin_fakultas` dibatasi pada fakultasnya, sedangkan `admin_biro3` dan `Superadmin` mencakup lingkup universitas.

---

## 6. Prinsip Keselarasan Data dan Standar Implementasi

1. **Integritas Database**: Seluruh implementasi use case terhubung ke basis data operasional `tracer`. Struktur relasi geografis menggunakan pasangan kanonik `kode_provinsi`, `kode_kabupaten`, dan `kode_negara` (ISO-3166-1 alpha-2).
2. **Sentralisasi Instrumen Respon**: Jawaban atas kuesioner universitas (UC12), kuesioner prodi (UC13), dan survei evaluasi atasan (UC14) disimpan secara terpusat pada tabel engine kuesioner `kuesioner_jawaban`.
3. **Preservasi Data Sinkronisasi**: Data hasil penarikan profil LinkedIn melalui Apify (UC16) wajib disimpan dalam penampungan staging `linkedin_sync_results` dan otomatis dicadangkan ke disk lokal agar snapshot data tidak hilang saat migrasi.
4. **Prinsip "Don't Make Me Think" pada Laporan**: Pada UC18 (Melihat Laporan), interaksi klik pada elemen grafik langsung memunculkan tabel data rincian terkait (*drill-down*) secara inline tepat di samping/bawah grafik tanpa memindahkan navigasi pengguna ke tab terpisah.
