# Deskripsi Use Case — Tracer Study UKDW

> Format: Suriya Sundaramoorthy (2022) — *UML Diagramming: A Case Study Approach*
>
> Template: **Usecase ID | Usecase Name | Actors Involved | Description | Preconditions | Main Flow | Post Conditions | Alternate Flow**

---

## Tabel 3.4 — UC-01: Login

| Komponen | Keterangan |
| :--- | :--- |
| **Usecase ID** | UC-01 |
| **Usecase Name** | Login |
| **Actors Involved** | Alumni, Admin Prodi, Admin Fakultas, Biro 3, Superadmin (ITC) |
| **Description** | Pengguna melakukan autentikasi untuk mengakses sistem sesuai peran masing-masing. |
| **Preconditions** | Pengguna memiliki akun yang terdaftar dalam sistem. |
| **Main Flow** | 1. Pengguna mengakses halaman login sistem. 2. Pengguna memasukkan NIM/username dan kata sandi. 3. Sistem memvalidasi kredensial dan memverifikasi peran pengguna. 4. Sistem memeriksa status kata sandi (apakah pertama kali login). 5. Jika kata sandi bawaan belum diganti, sistem mengarahkan ke halaman ubah kata sandi wajib. 6. Jika valid dan kata sandi sudah diubah, sistem menampilkan dashboard sesuai peran pengguna. |
| **Post Conditions** | Pengguna berhasil masuk dan diarahkan ke halaman dashboard sesuai perannya. |
| **Alternate Flow** | Jika kredensial tidak valid, sistem menampilkan pesan kesalahan. Jika pengguna lupa kata sandi, dapat mengakses UC-03. |

---

## Tabel 3.5 — UC-02: Mengubah Kata Sandi

| Komponen | Keterangan |
| :--- | :--- |
| **Usecase ID** | UC-02 |
| **Usecase Name** | Mengubah Kata Sandi |
| **Actors Involved** | Alumni, Admin Prodi, Admin Fakultas, Biro 3, Superadmin (ITC) |
| **Description** | Pengguna mengubah kata sandi akun melalui halaman pengaturan profil atau saat login pertama kali. |
| **Preconditions** | Pengguna telah login atau diarahkan sistem karena wajib mengganti kata sandi bawaan. |
| **Main Flow** | 1. Pengguna mengakses halaman pengaturan akun. 2. Pengguna memasukkan kata sandi lama dan kata sandi baru. 3. Pengguna mengonfirmasi kata sandi baru. 4. Sistem memvalidasi kata sandi lama dan memeriksa kesesuaian konfirmasi. 5. Sistem menyimpan kata sandi baru. |
| **Post Conditions** | Kata sandi pengguna berhasil diperbarui. |
| **Alternate Flow** | Jika kata sandi lama tidak sesuai, sistem menampilkan pesan kesalahan. Jika konfirmasi tidak cocok, sistem meminta pengisian ulang. |

---

## Tabel 3.6 — UC-03: Memulihkan Kata Sandi

| Komponen | Keterangan |
| :--- | :--- |
| **Usecase ID** | UC-03 |
| **Usecase Name** | Memulihkan Kata Sandi |
| **Actors Involved** | Alumni, Admin Prodi, Admin Fakultas, Biro 3 |
| **Description** | Pengguna memulihkan kata sandi secara mandiri melalui tautan pemulihan ke email terdaftar. |
| **Preconditions** | Pengguna memiliki akun dengan alamat email yang telah terdaftar. |
| **Main Flow** | 1. Pengguna mengakses halaman lupa kata sandi. 2. Pengguna memasukkan NIM/username atau email. 3. Sistem mengirimkan tautan pemulihan berbatas waktu (60 menit). 4. Pengguna mengakses tautan dan mengisi kata sandi baru. 5. Sistem menyimpan kata sandi baru. |
| **Post Conditions** | Kata sandi berhasil direset dan pengguna dapat login menggunakan kata sandi baru. |
| **Alternate Flow** | Jika akun tidak ditemukan, sistem menampilkan pesan data tidak ditemukan. Jika tautan kadaluarsa, pengguna diminta mengajukan permintaan ulang. |

---

## Tabel 3.7 — UC-04: Mengelola Profil Alumni

| Komponen | Keterangan |
| :--- | :--- |
| **Usecase ID** | UC-04 |
| **Usecase Name** | Mengelola Profil Alumni |
| **Actors Involved** | Alumni, Admin Prodi, Admin Fakultas |
| **Description** | Alumni memperbarui data profil dan riwayat pekerjaan. Admin dapat menelusuri dan memvalidasi data sesuai cakupan. |
| **Preconditions** | Pengguna telah login ke dalam sistem. |
| **Main Flow** | 1. Alumni mengakses halaman profil. 2. Alumni memperbarui data identitas, kontak, riwayat pendidikan lanjutan, dan informasi pekerjaan. 3. Sistem memvalidasi kelengkapan data wajib. 4. Jika perusahaan belum terdaftar, alumni menambahkan data perusahaan baru. 5. Sistem menyimpan pembaruan dan memperbarui status kelengkapan. |
| **Post Conditions** | Data profil alumni terbarui dan status kelengkapan profil diperbarui. |
| **Alternate Flow** | Jika data wajib belum diisi, sistem mencegah penyimpanan. Admin hanya dapat melihat (read-only). |

---

## Tabel 3.8 — UC-05: Mengisi Kuesioner Universitas

| Komponen | Keterangan |
| :--- | :--- |
| **Usecase ID** | UC-05 |
| **Usecase Name** | Mengisi Kuesioner Universitas |
| **Actors Involved** | Alumni |
| **Description** | Alumni mengisi kuesioner Tracer Study tingkat universitas secara bertahap per section dengan aturan percabangan berdasarkan kondisi pekerjaan. |
| **Preconditions** | Alumni telah login dan kuesioner universitas dalam status aktif. |
| **Main Flow** | 1. Alumni mengakses halaman kuesioner universitas. 2. Sistem menampilkan section secara bertahap. 3. Alumni mengisi jawaban per section. 4. Sistem menerapkan branching logic berdasarkan kondisi pekerjaan. 5. Sistem memvalidasi isian wajib. 6. Alumni melanjutkan hingga seluruh section selesai. 7. Sistem mencatat status kuesioner alumni sebagai selesai. |
| **Post Conditions** | Status kuesioner universitas alumni tercatat selesai dan data jawaban tersimpan. |
| **Alternate Flow** | Jika kuesioner belum aktif, sistem menampilkan informasi belum tersedia. Alumni dapat menyimpan jawaban sementara. |

---

## Tabel 3.9 — UC-06: Mengisi Kuesioner Program Studi

| Komponen | Keterangan |
| :--- | :--- |
| **Usecase ID** | UC-06 |
| **Usecase Name** | Mengisi Kuesioner Program Studi |
| **Actors Involved** | Alumni |
| **Description** | Alumni mengisi kuesioner tambahan khusus yang dirancang oleh program studi asal alumni. |
| **Preconditions** | Alumni telah login; kuesioner prodi aktif; program studi telah mengonfigurasi instrumen kuesioner. |
| **Main Flow** | 1. Alumni mengakses halaman kuesioner program studi. 2. Sistem menampilkan instrumen sesuai program studi asal. 3. Alumni mengisi jawaban per section. 4. Sistem memvalidasi isian wajib dan menyimpan jawaban. 5. Sistem mencatat status kuesioner prodi sebagai selesai. |
| **Post Conditions** | Data jawaban kuesioner prodi tersimpan dan terhubung dengan data alumni. |
| **Alternate Flow** | Jika program studi belum mengonfigurasi kuesioner, sistem menampilkan pesan belum tersedia. |

---

## Tabel 3.10 — UC-07: Evaluasi Kinerja Alumni

| Komponen | Keterangan |
| :--- | :--- |
| **Usecase ID** | UC-07 |
| **Usecase Name** | Evaluasi Kinerja Alumni |
| **Actors Involved** | Pengguna Lulusan (Atasan) |
| **Description** | Atasan alumni mengisi formulir evaluasi melalui tautan token unik tanpa memerlukan akun dalam sistem. |
| **Preconditions** | Atasan telah menerima email berisi tautan evaluasi dengan token yang valid, belum kadaluarsa, dan formulir evaluasi dalam status aktif. |
| **Main Flow** | 1. Atasan mengakses tautan evaluasi dari email undangan. 2. Sistem memvalidasi keabsahan dan masa aktif token. 3. Sistem menampilkan formulir evaluasi kinerja. 4. Atasan mengisi penilaian kompetensi per aspek. 5. Atasan mengirimkan formulir evaluasi. 6. Sistem menyimpan jawaban dan menghubungkannya dengan data alumni. |
| **Post Conditions** | Data evaluasi kinerja tersimpan dan status evaluasi alumni diperbarui menjadi selesai. |
| **Alternate Flow** | Jika token kadaluarsa, sistem menampilkan pesan tautan tidak berlaku. Jika instrumen belum aktif, sistem menampilkan pesan formulir tidak tersedia. |

---

## Tabel 3.11 — UC-08: Manajemen Akun

| Komponen | Keterangan |
| :--- | :--- |
| **Usecase ID** | UC-08 |
| **Usecase Name** | Manajemen Akun |
| **Actors Involved** | Superadmin (ITC) |
| **Description** | Superadmin mengelola akun pengguna termasuk pembuatan akun massal alumni, pengelolaan akun admin, dan reset kata sandi. |
| **Preconditions** | Superadmin telah login ke dalam sistem. |
| **Main Flow** | 1. Superadmin mengakses halaman manajemen akun. 2. Superadmin dapat membuat akun alumni massal berdasarkan sinkronisasi data lulusan. 3. Superadmin menambah, mengedit, atau menonaktifkan akun administrator. 4. Superadmin melakukan reset kata sandi pengguna secara manual. |
| **Post Conditions** | Data akun pengguna berhasil dikelola; perubahan pengaturan sinkronisasi tersimpan. |
| **Alternate Flow** | Jika username sudah ada, sistem menolak pembuatan duplikasi akun. |

---

## Tabel 3.12 — UC-09: Mengelola Kuesioner Universitas

| Komponen | Keterangan |
| :--- | :--- |
| **Usecase ID** | UC-09 |
| **Usecase Name** | Mengelola Kuesioner Universitas |
| **Actors Involved** | Superadmin (ITC) |
| **Description** | Superadmin mengelola instrumen kuesioner Tracer Study tingkat universitas meliputi section, butir pertanyaan, opsi jawaban, dan aturan percabangan. |
| **Preconditions** | Superadmin telah login ke dalam sistem. |
| **Main Flow** | 1. Superadmin mengakses halaman pengelolaan kuesioner universitas. 2. Superadmin menambah, mengedit, atau menghapus section kuesioner. 3. Superadmin menambah butir pertanyaan, mengatur opsi jawaban, dan aturan percabangan (branching logic). 4. Superadmin mengatur urutan tampil dan status aktif. 5. Superadmin mengaktifkan atau menonaktifkan kuesioner secara keseluruhan. |
| **Post Conditions** | Instrumen kuesioner universitas berhasil dikonfigurasi dan siap diisi alumni. |
| **Alternate Flow** | Jika kuesioner aktif dan telah ada respons alumni, penghapusan butir yang sudah memiliki jawaban dibatasi. |

---

## Tabel 3.13 — UC-10: Mengelola Kuesioner Pengguna Lulusan

| Komponen | Keterangan |
| :--- | :--- |
| **Usecase ID** | UC-10 |
| **Usecase Name** | Mengelola Kuesioner Pengguna Lulusan |
| **Actors Involved** | Superadmin (ITC) |
| **Description** | Superadmin mengelola instrumen formulir evaluasi kinerja alumni yang diisi oleh pengguna lulusan (atasan). |
| **Preconditions** | Superadmin telah login ke dalam sistem. |
| **Main Flow** | 1. Superadmin mengakses halaman pengelolaan kuesioner evaluasi atasan. 2. Superadmin menambah, mengedit, atau menghapus section dan butir pertanyaan evaluasi. 3. Superadmin mengatur opsi jawaban beserta bobot penilaian. 4. Superadmin mengatur status aktif instrumen evaluasi. |
| **Post Conditions** | Instrumen evaluasi pengguna lulusan berhasil dikonfigurasi. |
| **Alternate Flow** | Jika terdapat evaluasi berlangsung (token aktif), perubahan instrumen signifikan dikonfirmasi terlebih dahulu. |

---

## Tabel 3.14 — UC-11: Mengelola Kuesioner Prodi

| Komponen | Keterangan |
| :--- | :--- |
| **Usecase ID** | UC-11 |
| **Usecase Name** | Mengelola Kuesioner Prodi |
| **Actors Involved** | Admin Prodi, Admin Fakultas |
| **Description** | Admin Prodi atau Admin Fakultas mengelola instrumen kuesioner khusus program studi. |
| **Preconditions** | Admin Prodi atau Admin Fakultas telah login. |
| **Main Flow** | 1. Admin mengakses halaman pengelolaan kuesioner program studi. 2. Admin membuat section kuesioner baru. 3. Admin menambahkan butir pertanyaan dari bank soal atau membuat butir baru. 4. Admin mengatur opsi jawaban dan urutan tampil. 5. Admin mengaktifkan atau menonaktifkan butir dan section. |
| **Post Conditions** | Instrumen kuesioner prodi berhasil dikonfigurasi dan siap diisi alumni program studi terkait. |
| **Alternate Flow** | Admin Fakultas dapat mengelola kuesioner untuk seluruh prodi di fakultasnya. Butir yang memiliki jawaban terkait tidak dapat dihapus. |

---

## Tabel 3.15 — UC-12: Mengelola Data Alumni

| Komponen | Keterangan |
| :--- | :--- |
| **Usecase ID** | UC-12 |
| **Usecase Name** | Mengelola Data Alumni |
| **Actors Involved** | Admin Prodi, Admin Fakultas, Biro 3, Superadmin (ITC) |
| **Description** | Administrator menelusuri, memantau, dan mengelola data alumni sesuai cakupan aksesnya. |
| **Preconditions** | Administrator telah login. |
| **Main Flow** | 1. Admin mengakses halaman data alumni. 2. Admin menelusuri dengan filter (tahun kelulusan, prodi, status kuesioner, status profil). 3. Admin melihat detail profil dan riwayat karier alumni. 4. Admin memantau status pengisian kuesioner. 5. Superadmin dapat memperbarui atau mengoreksi data alumni. |
| **Post Conditions** | Informasi data alumni berhasil diakses dan dikelola sesuai cakupan wewenang. |
| **Alternate Flow** | Jika administrator berusaha mengakses data di luar cakupan wewenangnya, sistem menolak akses. |

---

## Tabel 3.16 — UC-13: Master Data Perusahaan

| Komponen | Keterangan |
| :--- | :--- |
| **Usecase ID** | UC-13 |
| **Usecase Name** | Master Data Perusahaan |
| **Actors Involved** | Admin Prodi, Admin Fakultas, Superadmin (ITC) |
| **Description** | Administrator mengelola direktori perusahaan termasuk verifikasi pengajuan alumni dengan dukungan rekomendasi kecocokan nama. |
| **Preconditions** | Administrator telah login. |
| **Main Flow** | 1. Admin mengakses halaman master data perusahaan. 2. Admin menelusuri, menambah, dan mengedit data perusahaan. 3. Sistem menampilkan daftar perusahaan menunggu verifikasi. 4. Sistem menyediakan rekomendasi kecocokan nama (string similarity matching). 5. Admin memutuskan: setujui sebagai entitas baru, gabungkan dengan yang sudah ada, atau tolak. |
| **Post Conditions** | Data perusahaan berhasil dikelola; perusahaan yang diverifikasi diperbarui statusnya. |
| **Alternate Flow** | Jika kecocokan nama sangat tinggi, sistem menampilkan peringatan kemungkinan duplikasi. |

---

## Tabel 3.17 — UC-14: Melihat Laporan

| Komponen | Keterangan |
| :--- | :--- |
| **Usecase ID** | UC-14 |
| **Usecase Name** | Melihat Laporan |
| **Actors Involved** | Admin Prodi, Admin Fakultas, Biro 3, Superadmin (ITC) |
| **Description** | Administrator mengakses laporan analitik interaktif meliputi capaian IKU, grafik distribusi karier, persentase respons kuesioner, dan komparasi pendapatan terhadap standar UMP. |
| **Preconditions** | Administrator telah login. |
| **Main Flow** | 1. Admin mengakses halaman laporan analitik. 2. Sistem menampilkan grafik, persentase respons, capaian IKU 2 dan IKU 4, dan komparasi gaji terhadap UMP. 3. Admin memfilter berdasarkan program studi, tahun kelulusan, angkatan, dll. 4. Admin menelusuri detail visualisasi data secara interaktif (drill-down). |
| **Post Conditions** | Laporan analitik berhasil ditampilkan sesuai cakupan akses dan filter. |
| **Alternate Flow** | Jika belum ada data respons untuk filter yang dipilih, sistem menampilkan pesan data belum tersedia. |

---

## Tabel 3.18 — UC-15: Menghubungi Alumni

| Komponen | Keterangan |
| :--- | :--- |
| **Usecase ID** | UC-15 |
| **Usecase Name** | Menghubungi Alumni |
| **Actors Involved** | Admin Prodi, Admin Fakultas, Biro 3 |
| **Description** | Administrator melakukan komunikasi tindak lanjut kepada alumni melalui WhatsApp dan email untuk meningkatkan response rate kuesioner. |
| **Preconditions** | Administrator telah login dan data kontak alumni tersedia dalam sistem. |
| **Main Flow** | 1. Admin memilih alumni yang akan dihubungi. 2. Admin mengirim pesan melalui WhatsApp Click-to-Chat dengan templat dinamis. 3. Admin mengirim email pengingat kuesioner secara individual. 4. Admin mengirim email massal kepada sejumlah alumni sekaligus. 5. Sistem mencatat pengiriman dalam log aktivitas. |
| **Post Conditions** | Komunikasi tindak lanjut berhasil dilakukan dan tercatat dalam sistem. |
| **Alternate Flow** | Jika email alumni tidak tersedia, opsi pengiriman email tidak aktif. Jika nomor WhatsApp tidak tersedia, opsi WhatsApp tidak aktif. |

---

## Tabel 3.19 — UC-16: Sinkronisasi LinkedIn

| Komponen | Keterangan |
| :--- | :--- |
| **Usecase ID** | UC-16 |
| **Usecase Name** | Sinkronisasi LinkedIn |
| **Actors Involved** | Admin Prodi, Admin Fakultas, Superadmin (ITC), Apify (Sistem) |
| **Description** | Administrator melakukan sinkronisasi data riwayat karier alumni dari LinkedIn menggunakan Apify dengan alur peninjauan dan persetujuan sebelum data diterapkan. |
| **Preconditions** | Administrator telah login; URL profil LinkedIn alumni tersimpan; dan layanan Apify tersedia. |
| **Main Flow** | 1. Admin memilih alumni yang memiliki URL profil LinkedIn. 2. Admin menginisiasi proses sinkronisasi. 3. Sistem mengirim permintaan ke Apify dengan parameter URL LinkedIn. 4. Apify mengekstraksi data profil dan mengembalikan hasil dalam format JSON. 5. Sistem memetakan data ke struktur staging. 6. Admin meninjau data staging dan memutuskan approve atau reject. 7. Data yang disetujui diterapkan ke profil alumni. |
| **Post Conditions** | Data riwayat karier alumni berhasil diperbarui berdasarkan informasi LinkedIn yang telah disetujui. |
| **Alternate Flow** | Jika Apify tidak tersedia atau URL tidak valid, sistem menampilkan pesan kesalahan. Jika semua data ditolak, profil alumni tidak berubah. |

---

## Tabel 3.20 — UC-17: Ekspor Laporan

| Komponen | Keterangan |
| :--- | :--- |
| **Usecase ID** | UC-17 |
| **Usecase Name** | Ekspor Laporan |
| **Actors Involved** | Admin Prodi, Admin Fakultas, Biro 3, Superadmin (ITC) |
| **Description** | Administrator mengekspor data rekapitulasi alumni dan jawaban kuesioner ke format spreadsheet. |
| **Preconditions** | Administrator telah login dan data yang akan diekspor tersedia. |
| **Main Flow** | 1. Admin mengakses fasilitas ekspor data. 2. Admin memilih parameter filter (prodi, tahun kelulusan, jenis data). 3. Sistem memproses data sesuai filter dan membentuk file .xlsx atau .zip. 4. Sistem menyediakan file untuk diunduh. |
| **Post Conditions** | File ekspor berhasil diproses dan diunduh oleh administrator. |
| **Alternate Flow** | Jika tidak ada data sesuai filter, sistem menampilkan pesan data kosong. Jika volume data sangat besar, sistem memproses secara asinkron. |

---

## Tabel 3.21 — UC-18: Melihat Log Aktivitas

| Komponen | Keterangan |
| :--- | :--- |
| **Usecase ID** | UC-18 |
| **Usecase Name** | Melihat Log Aktivitas |
| **Actors Involved** | Superadmin (ITC) |
| **Description** | Superadmin menelusuri dan memantau riwayat aktivitas seluruh pengguna untuk keperluan audit. |
| **Preconditions** | Superadmin telah login. |
| **Main Flow** | 1. Superadmin mengakses halaman log aktivitas. 2. Sistem menampilkan riwayat aktivitas: jenis aksi, pengguna, waktu, dan detail perubahan data. 3. Superadmin memfilter berdasarkan pengguna, jenis aktivitas, model data, atau rentang waktu. 4. Superadmin menelusuri detail setiap entri log untuk melihat old_values dan new_values. |
| **Post Conditions** | Riwayat aktivitas berhasil ditampilkan dan dapat diekspor. |
| **Alternate Flow** | Jika filter tidak menghasilkan data, sistem menampilkan tampilan kosong. |
