# Instrumen Moderated Usability Testing

## Portal Tracer Study UKDW — Stakeholder Superadmin

## 1. Identitas Dokumen

| Elemen | Keterangan |
|---|---|
| Produk | Portal Tracer Study UKDW |
| Metode | Moderated Usability Testing |
| Stakeholder | Superadmin |
| Moderator | ................................................ |
| Tanggal pengujian | ................................................ |
| Versi aplikasi | ................................................ |
| Lingkungan pengujian | Lokal / Staging / Lainnya: ....................... |
| Durasi target | 60–90 menit per peserta |

Dokumen ini digunakan untuk menguji kemudahan Superadmin dalam mengelola konfigurasi, data, analitik, dan audit Portal Tracer Study UKDW. Pengujian berfokus pada perilaku pengguna ketika menyelesaikan pekerjaan, bukan pada kemampuan peserta untuk menghafal struktur menu.

PSSUQ tidak termasuk dalam instrumen ini dan dapat ditambahkan pada tahap pengukuran kepuasan setelah usability testing selesai.

---

## 2. Tujuan Pengujian

Pengujian ini bertujuan untuk mengetahui apakah Superadmin dapat:

1. Login dan memahami struktur navigasi utama.
2. Memahami informasi penting pada Dashboard.
3. Menemukan dan memeriksa data alumni.
4. Memverifikasi data perusahaan.
5. Mengelola section dan pertanyaan kuesioner Universitas.
6. Mengelola pertanyaan kuesioner Program Studi.
7. Mengelola instrumen Evaluasi Pengguna Lulusan atau Atasan.
8. Membaca dan menelusuri grafik analitik.
9. Mengelola akun pengguna dan proses reset kata sandi.
10. Mengelola master wilayah, negara, dan UMP.
11. Meninjau hasil sinkronisasi profil LinkedIn.
12. Membaca log aktivitas untuk menelusuri perubahan data.
13. Mengekspor data yang dibutuhkan untuk pelaporan.

---

## 3. Ruang Lingkup Pengujian

### 3.1 Fitur yang diuji

| Area | Halaman utama | Fokus pengujian |
|---|---|---|
| Autentikasi | `/login` | Login, pesan kesalahan, dan pengalihan role |
| Dashboard | `/superadmin/dashboard` | Pemahaman KPI, ringkasan, dan navigasi |
| Data alumni | `/superadmin/alumni` | Pencarian, filter, detail, profil, tab Hasil LinkedIn, approval, histori, dan ekspor |
| Perusahaan | `/superadmin/perusahaan` | Pencarian, pemeriksaan, verifikasi, dan penolakan |
| Section Universitas | `/superadmin/sections` | Tambah, ubah, urutkan, dan hapus data uji |
| Pertanyaan Universitas | `/superadmin/pertanyaan` | Membuat pertanyaan, opsi, dan pengaturan aktif |
| Kuesioner Prodi | `/superadmin/prodi-kuesioner` | Placement, section, pertanyaan, dan opsi |
| Evaluasi Atasan | `/superadmin/evaluasi-atasan` | Section, pertanyaan, opsi Likert, dan riwayat respons |
| Analitik Universitas | `/superadmin/analitik` | Filter, grafik, tabel, dan drill-down |
| Analitik Prodi | `/superadmin/analitik-prodi` | Analisis jawaban berdasarkan prodi dan pertanyaan |
| Analitik Atasan | `/superadmin/analitik-atasan` | Respons dan hasil evaluasi pengguna lulusan |
| Manajemen akun | `/superadmin/manajemen-akun` | Pencarian akun, ubah data, dan reset kata sandi |
| Master wilayah | `/superadmin/master-wilayah` | Provinsi, kabupaten, negara, dan UMP |
| LinkedIn | Tab Hasil LinkedIn pada detail alumni; `/superadmin/linkedin-sync` sebagai alur alternatif | Sinkronisasi, review, approval, reject, plot ulang, histori, dan audit trace |
| Daftar atasan | `/superadmin/atasan` | Pencarian dan peninjauan data pengguna lulusan |
| Log aktivitas | `/superadmin/logs` | Filter dan pemeriksaan audit trail |

### 3.2 Fitur yang tidak menjadi fokus utama

Fitur berikut tidak menjadi task utama, tetapi dapat dicatat apabila muncul selama sesi:

- Fitur akun Alumni.
- Pengisian kuesioner oleh Alumni.
- Pengisian evaluasi oleh Pengguna Lulusan.
- Pengujian performa, keamanan teknis, dan kompatibilitas browser secara mendalam.
- PSSUQ atau kuesioner kepuasan terstandar.

---

## 4. Karakteristik Peserta

Peserta ideal adalah orang yang pernah atau akan menjalankan tugas administratif lintas data pada Portal Tracer Study, misalnya pengelola sistem, pengelola data akademik, atau administrator universitas.

### 4.1 Kriteria peserta

- Memahami tugas administrasi data universitas.
- Tidak harus mengetahui struktur teknis aplikasi.
- Tidak diberi pelatihan khusus mengenai versi aplikasi yang sedang diuji.
- Bersedia menyampaikan pemikiran dan kebingungan selama proses pengujian.

### 4.2 Identitas peserta

| Elemen | Jawaban |
|---|---|
| Kode peserta | ................................................ |
| Nama/inisial | ................................................ |
| Jabatan | ................................................ |
| Lama pengalaman sebagai admin | ................................................ |
| Pengalaman menggunakan sistem sejenis | ................................................ |
| Perangkat yang digunakan | ................................................ |
| Browser | ................................................ |
| Tanggal pengujian | ................................................ |

---

## 5. Persiapan Sebelum Pengujian

### 5.1 Persiapan moderator

Moderator menyiapkan:

1. Akun Superadmin khusus pengujian.
2. Data uji yang dapat diubah tanpa memengaruhi data produksi.
3. Daftar task dan lembar observasi.
4. Timer atau aplikasi pencatat waktu.
5. Rekaman layar apabila peserta memberikan persetujuan.
6. File hasil ekspor atau folder unduhan yang dapat diperiksa.
7. Data cadangan sebelum task yang membuat perubahan.

### 5.2 Kondisi data minimum

Sebelum sesi dimulai, pastikan tersedia:

- Minimal satu alumni dengan profil lengkap.
- Minimal satu alumni dengan data belum lengkap.
- Minimal satu alumni dengan jawaban kuesioner.
- Minimal satu perusahaan berstatus menunggu verifikasi.
- Minimal satu perusahaan berstatus terverifikasi.
- Minimal satu pertanyaan aktif dan satu pertanyaan nonaktif.
- Minimal satu section kuesioner Universitas.
- Minimal satu section dan pertanyaan kuesioner Prodi.
- Minimal satu pertanyaan Evaluasi Atasan dengan opsi Likert.
- Data analitik untuk lebih dari satu tahun atau program studi.
- Minimal satu akun alumni untuk simulasi reset kata sandi.
- Data provinsi, kabupaten, negara, dan UMP.
- Minimal satu hasil sinkronisasi LinkedIn berstatus menunggu peninjauan.
- Minimal satu alumni yang memiliki URL LinkedIn dan dapat dibuka dari direktori Data Alumni.
- Minimal satu hasil LinkedIn berstatus `pending` untuk diuji melalui tab Hasil LinkedIn pada detail alumni.
- Minimal satu hasil LinkedIn berstatus `approved` untuk diuji melalui histori atau plot ulang.
- Log aktivitas dari beberapa jenis tindakan.

### 5.3 Data uji yang direkomendasikan

Gunakan data uji yang mudah dikenali, misalnya:

| Jenis data | Contoh data uji |
|---|---|
| Nama section baru | `UT Superadmin - Pengalaman Magang` |
| Kode pertanyaan baru | `UT-SA-001` |
| Nama perusahaan baru | `Perusahaan Uji Usability Superadmin` |
| Nama alumni | Gunakan alumni dari fixture uji |
| Nama akun target | Gunakan akun alumni uji |
| Tahun analitik | Tahun yang memiliki data respon |

Jangan menggunakan nama, email, atau data pribadi nyata apabila tidak diperlukan untuk task.

---

## 6. Prosedur Pengujian

### 6.1 Urutan sesi

| Tahap | Durasi target | Kegiatan |
|---|---:|---|
| Pembukaan | 3 menit | Perkenalan dan penjelasan tujuan |
| Pra-pengujian | 5 menit | Pertanyaan latar belakang peserta |
| Task utama | 45–70 menit | Peserta menyelesaikan task secara mandiri |
| Wawancara akhir | 10 menit | Menggali kesan dan masalah utama |
| Penutupan | 2 menit | Konfirmasi catatan dan ucapan terima kasih |

### 6.2 Aturan untuk peserta

1. Peserta mengerjakan task dengan akun Superadmin.
2. Peserta diminta berpikir keras atau mengucapkan pertimbangannya.
3. Peserta boleh menyampaikan apabila tidak memahami suatu bagian.
4. Peserta tidak perlu takut melakukan kesalahan.
5. Peserta tidak perlu menyelesaikan task di luar data yang diminta.

### 6.3 Aturan untuk moderator

1. Moderator membacakan skenario, bukan langkah teknis.
2. Moderator tidak menyebutkan nama tombol atau menu sebelum peserta mencoba sendiri.
3. Moderator tidak mengarahkan kursor peserta.
4. Moderator mencatat kejadian, bukan langsung memperbaiki masalah.
5. Jika peserta berhenti selama 60 detik, catat sebagai titik hambatan.
6. Jika peserta tidak dapat melanjutkan selama 120 detik, berikan bantuan bertahap.
7. Semua bantuan harus dicatat pada lembar observasi.
8. Task yang mengubah data harus menggunakan data uji.

### 6.4 Bantuan bertahap

| Level | Bentuk bantuan |
|---:|---|
| 0 | Tidak ada bantuan |
| 1 | Bertanya, “Apa yang sedang Anda cari?” |
| 2 | Mengulang tujuan task tanpa menyebut tombol |
| 3 | Menjelaskan fungsi halaman secara umum |
| 4 | Menunjukkan lokasi fitur |
| 5 | Moderator membantu menyelesaikan langkah |

---

## 7. Naskah Pembukaan Moderator

Bacakan naskah berikut sebelum task dimulai:

> Terima kasih telah bersedia mengikuti pengujian ini. Tujuan pengujian adalah mengetahui apakah Portal Tracer Study UKDW mudah digunakan oleh Superadmin.
>
> Yang sedang diuji adalah sistemnya, bukan kemampuan Anda. Tidak ada jawaban benar atau salah. Silakan melakukan tugas seperti yang biasa Anda lakukan dan ceritakan apa yang sedang Anda pikirkan.
>
> Kami akan mencatat waktu, kesalahan, bagian yang membingungkan, dan bantuan yang diperlukan. Mohon jangan khawatir apabila mengalami kesulitan karena hal tersebut merupakan informasi penting untuk perbaikan sistem.

Apabila sesi direkam, tambahkan:

> Apakah Anda bersedia apabila layar dan suara selama sesi direkam untuk kepentingan analisis penelitian?

---

## 8. Pertanyaan Pra-Pengujian

1. Apa pekerjaan utama Anda yang berkaitan dengan pengelolaan data atau sistem?
2. Apakah Anda pernah menggunakan sistem tracer study sebelumnya?
3. Seberapa sering Anda menggunakan aplikasi berbasis web untuk pekerjaan administrasi?
4. Data apa yang biasanya Anda cari atau kelola?
5. Menurut Anda, pekerjaan apa yang paling penting dilakukan oleh Superadmin?

---

# 9. Skenario dan Task Pengujian

## SA-01 — Login dan Memahami Navigasi

**Halaman awal moderator:** `/login`

**Skenario untuk peserta:**

Anda baru ditugaskan sebagai Superadmin Portal Tracer Study UKDW. Masuk ke sistem dan cari tahu fitur utama yang tersedia.

**Task:**

1. Login menggunakan akun Superadmin.
2. Periksa informasi yang muncul setelah login.
3. Temukan halaman untuk mengelola alumni.
4. Temukan halaman untuk mengelola perusahaan.
5. Temukan halaman untuk mengelola kuesioner Universitas.
6. Temukan halaman untuk mengelola kuesioner Prodi.
7. Temukan halaman untuk mengelola Evaluasi Atasan.
8. Temukan halaman analitik.
9. Temukan halaman manajemen akun.
10. Temukan halaman log aktivitas.

**Kriteria keberhasilan:**

- Peserta berhasil login tanpa bantuan.
- Peserta mengetahui bahwa halaman setelah login adalah Dashboard.
- Peserta dapat menemukan minimal lima area utama secara mandiri.
- Peserta dapat menjelaskan fungsi umum setiap area yang ditemukan.

**Hal yang diamati:**

- Apakah peserta memahami struktur sidebar atau navigasi?
- Apakah istilah menu mudah dipahami?
- Apakah peserta membedakan pengelolaan data dan analitik?
- Apakah peserta menemukan fitur yang dibutuhkan tanpa membuka banyak halaman yang tidak relevan?

---

## SA-02 — Memahami Dashboard Superadmin

**Halaman awal moderator:** `/superadmin/dashboard`

**Skenario untuk peserta:**

Anda ingin mengetahui kondisi terbaru Portal Tracer Study sebelum menentukan pekerjaan yang harus dilakukan.

**Task:**

1. Jelaskan informasi apa yang paling penting pada Dashboard.
2. Cari jumlah alumni atau responden yang ditampilkan.
3. Cari informasi mengenai kelengkapan atau progres pengisian.
4. Cari informasi yang membutuhkan tindak lanjut.
5. Pilih salah satu informasi dan buka halaman detailnya apabila tersedia.
6. Jelaskan tindakan apa yang akan Anda lakukan setelah membaca Dashboard.

**Kriteria keberhasilan:**

- Peserta dapat menemukan informasi ringkasan utama.
- Peserta dapat membedakan angka total, respon, dan data yang belum lengkap.
- Peserta dapat menentukan minimal satu tindak lanjut berdasarkan Dashboard.

**Pertanyaan setelah task:**

- “Apakah Dashboard memberikan informasi yang Anda harapkan?”
- “Angka atau bagian mana yang paling sulit dipahami?”
- “Informasi apa yang menurut Anda masih kurang?”

---

## SA-03 — Mencari dan Memeriksa Detail Alumni

**Halaman awal moderator:** `/superadmin/alumni`

**Skenario untuk peserta:**

Seorang pimpinan meminta Anda memeriksa data alumni tertentu, termasuk profil, pekerjaan, dan jawaban kuesionernya.

**Task:**

1. Cari alumni berdasarkan nama atau NIM.
2. Gunakan filter tahun atau program studi apabila tersedia.
3. Buka detail alumni.
4. Periksa identitas dan data akademik.
5. Periksa kelengkapan profil.
6. Periksa status pekerjaan dan perusahaan.
7. Periksa informasi atasan.
8. Periksa jawaban kuesioner Universitas.
9. Periksa jawaban kuesioner Prodi.
10. Periksa hasil evaluasi atasan apabila tersedia.
11. Buka tab **Hasil LinkedIn** pada detail alumni.
12. Periksa status sinkronisasi terakhir.
13. Periksa data hasil LinkedIn, pemetaan data, dan histori sinkronisasi apabila tersedia.

**Kriteria keberhasilan:**

- Peserta menemukan alumni yang diminta.
- Peserta dapat membuka detail tanpa salah memilih data.
- Peserta dapat menemukan minimal tiga jenis informasi yang diminta.
- Peserta memahami hubungan antara profil, kuesioner, perusahaan, dan evaluasi.
- Peserta mengetahui bahwa hasil sinkronisasi LinkedIn dapat diaudit dari detail alumni.

**Hal yang diamati:**

- Apakah peserta memahami label atau tab detail?
- Apakah informasi terlalu padat?
- Apakah peserta dapat membedakan data akademik dan data yang dapat diubah?

---

## SA-04 — Mengubah Data Profil Alumni dan Meninjau Tab LinkedIn

**Halaman awal moderator:** Detail alumni dari `/superadmin/alumni`

**Skenario untuk peserta:**

Anda menerima koreksi data dari alumni. Perbarui satu informasi profil pada data uji.

**Task:**

1. Buka data alumni yang telah ditentukan.
2. Ubah satu field kontak atau karier yang diizinkan.
3. Simpan perubahan.
4. Muat ulang halaman.
5. Pastikan perubahan tetap tersimpan.
6. Periksa apakah ada pesan keberhasilan atau kegagalan.
7. Buka tab **Hasil LinkedIn**.
8. Periksa apakah status hasil sinkronisasi dan data pemetaan dapat dibedakan dari data profil utama.

**Kriteria keberhasilan:**

- Peserta menemukan area edit yang benar.
- Peserta tidak mengubah data akademik yang bersifat referensi.
- Perubahan berhasil disimpan.
- Peserta dapat memastikan perubahan setelah halaman dimuat ulang.
- Peserta dapat membedakan data profil utama, hasil scraping, dan data audit LinkedIn.

---

## SA-04A — Sinkronisasi dan Approval dari Detail Data Alumni

**Halaman awal moderator:** `/superadmin/alumni` → detail alumni → tab **Hasil LinkedIn**

**Skenario untuk peserta:**

Seorang alumni memiliki URL LinkedIn pada profilnya. Anda perlu mengambil data terbaru, meninjau hasilnya, lalu menentukan data mana yang boleh diterapkan ke profil alumni.

**Task:**

1. Buka direktori Data Alumni.
2. Cari alumni yang telah ditentukan berdasarkan nama atau NIM.
3. Buka detail alumni.
4. Buka tab **Hasil LinkedIn**.
5. Periksa URL LinkedIn yang terdaftar.
6. Jalankan **Sinkronkan Ulang** atau aksi sinkronisasi yang tersedia.
7. Tunggu sampai hasil sinkronisasi selesai dimuat.
8. Periksa status hasil, misalnya `Pending Review`.
9. Periksa data hasil LinkedIn, termasuk posisi, perusahaan, bidang, divisi, skills, foto, atau studi lanjut apabila tersedia.
10. Bandingkan data hasil LinkedIn dengan data profil alumni saat ini.
11. Buka pemetaan audit untuk melihat atribut hasil scraping dan kolom tujuan basis data.
12. Buka data JSON mentah apabila diperlukan untuk pemeriksaan lebih lanjut.
13. Ubah data usulan pada form approval apabila terdapat informasi yang tidak sesuai.
14. Setujui hasil LinkedIn.
15. Muat ulang halaman detail alumni.
16. Periksa apakah status berubah menjadi disetujui dan data yang dipilih diterapkan pada profil alumni.

**Kriteria keberhasilan:**

- Peserta memulai proses dari Data Alumni, bukan dari halaman LinkedIn Sync terpisah.
- Peserta menemukan tab Hasil LinkedIn tanpa bantuan langsung.
- Peserta memahami bahwa hasil scraping berstatus staging atau pending sebelum approval.
- Peserta dapat membandingkan data hasil scraping dengan data profil utama.
- Peserta dapat memeriksa pemetaan audit sebelum menyetujui.
- Approval berhasil dan perubahan terlihat pada detail alumni setelah reload.

**Hal yang diamati:**

- Apakah peserta memahami bahwa tombol sinkronisasi berada di detail alumni?
- Apakah peserta mengetahui perbedaan antara mengambil data, meninjau hasil, dan menerapkan hasil?
- Apakah status `Pending Review`, `Approved`, dan `Rejected` mudah dipahami?
- Apakah peserta tahu data apa saja yang akan berubah setelah approval?
- Apakah form approval terlalu panjang atau membingungkan?

---

## SA-04B — Menolak Hasil Sinkronisasi dari Detail Data Alumni

**Halaman awal moderator:** `/superadmin/alumni` → detail alumni → tab **Hasil LinkedIn**

**Skenario untuk peserta:**

Hasil sinkronisasi LinkedIn tidak sesuai dengan data alumni. Anda perlu menolak hasil tersebut agar tidak diterapkan ke profil utama.

**Task:**

1. Buka detail alumni dengan hasil LinkedIn berstatus `Pending Review`.
2. Periksa data hasil sinkronisasi.
3. Tentukan bahwa hasil tersebut tidak sesuai.
4. Tolak hasil sinkronisasi.
5. Periksa pesan konfirmasi.
6. Muat ulang halaman.
7. Pastikan hasil berstatus `Rejected`.
8. Pastikan data profil utama tidak tertimpa oleh hasil yang ditolak.

**Kriteria keberhasilan:**

- Peserta dapat menolak hasil dari tab Hasil LinkedIn.
- Sistem meminta konfirmasi sebelum penolakan.
- Status hasil berubah menjadi ditolak.
- Data profil utama tetap aman dan tidak tertimpa.

---

## SA-05 — Memverifikasi Perusahaan

**Halaman awal moderator:** `/superadmin/perusahaan`

**Skenario untuk peserta:**

Terdapat pengajuan perusahaan baru dari alumni. Anda perlu memeriksa data tersebut dan menentukan statusnya.

**Task:**

1. Cari perusahaan berstatus menunggu verifikasi.
2. Periksa nama perusahaan, lokasi, pengusul, dan informasi terkait.
3. Buka rekomendasi perusahaan serupa apabila tersedia.
4. Tentukan apakah data akan diverifikasi atau ditolak.
5. Jika data perlu diperbaiki, lakukan perubahan pada data uji.
6. Simpan keputusan.
7. Cari kembali perusahaan tersebut.
8. Pastikan status terbaru terlihat.

**Kriteria keberhasilan:**

- Peserta menemukan pengajuan yang benar.
- Peserta memahami perbedaan verifikasi, penolakan, dan perubahan data.
- Status perusahaan berubah sesuai keputusan.
- Peserta memahami dampak keputusan terhadap data alumni.

**Pertanyaan setelah task:**

- “Apa yang membuat Anda yakin bahwa perusahaan ini perlu diverifikasi atau ditolak?”
- “Apakah informasi untuk mengambil keputusan sudah cukup?”

---

## SA-06 — Mengelola Section Kuesioner Universitas

**Halaman awal moderator:** `/superadmin/sections`

**Skenario untuk peserta:**

Universitas ingin menambahkan bagian baru pada kuesioner untuk mengumpulkan informasi mengenai pengalaman magang alumni.

**Task:**

1. Buat section baru menggunakan nama data uji.
2. Isi deskripsi section.
3. Simpan section.
4. Ubah deskripsi section.
5. Ubah urutan section apabila fitur tersedia.
6. Muat ulang halaman.
7. Hapus section uji.

**Kriteria keberhasilan:**

- Peserta dapat membuat section.
- Peserta dapat mengedit section.
- Peserta dapat mengubah urutan apabila diperlukan.
- Peserta dapat menghapus data uji tanpa menghapus section aktif yang bukan target.

**Catatan moderator:**

Pastikan section uji tidak memiliki pertanyaan aktif sebelum menjalankan task penghapusan.

---

## SA-07 — Membuat dan Mengedit Pertanyaan Universitas

**Halaman awal moderator:** `/superadmin/pertanyaan`

**Skenario untuk peserta:**

Universitas ingin menambahkan pertanyaan baru mengenai kontribusi inovasi alumni.

**Task:**

1. Buat pertanyaan menggunakan kode `UT-SA-001`.
2. Isi label singkat.
3. Isi teks pertanyaan lengkap.
4. Pilih tipe pertanyaan yang sesuai.
5. Atur pertanyaan sebagai wajib diisi.
6. Simpan pertanyaan.
7. Cari pertanyaan yang baru dibuat.
8. Edit label atau teks pertanyaan.
9. Aktifkan atau nonaktifkan pertanyaan uji.
10. Pastikan status terbaru terlihat.

**Kriteria keberhasilan:**

- Peserta dapat membedakan kode, label, dan teks lengkap.
- Pertanyaan berhasil disimpan.
- Peserta dapat menemukan kembali pertanyaan.
- Perubahan dan status aktif tersimpan.

---

## SA-08 — Mengelola Opsi dan Aturan Jump To

**Halaman awal moderator:** `/superadmin/pertanyaan`

**Skenario untuk peserta:**

Universitas ingin menampilkan pertanyaan lanjutan yang berbeda berdasarkan status pekerjaan alumni.

**Task:**

1. Buat atau buka pertanyaan pilihan tunggal pada data uji.
2. Tambahkan opsi “Bekerja”.
3. Tambahkan opsi “Melanjutkan Pendidikan”.
4. Tambahkan opsi “Belum Bekerja”.
5. Atur tujuan pertanyaan lanjutan untuk salah satu opsi.
6. Simpan perubahan.
7. Buka kembali pertanyaan dalam mode edit.
8. Ubah tujuan pertanyaan.
9. Kosongkan salah satu tujuan.
10. Simpan kembali.

**Kriteria keberhasilan:**

- Peserta dapat menambah dan mengubah opsi.
- Peserta memahami fungsi Jump To.
- Peserta dapat mengosongkan Jump To tanpa menghapus opsi.
- Sistem menampilkan pesan yang jelas apabila tujuan tidak valid.

---

## SA-09 — Menggunakan Bank Pertanyaan dan Placement

**Halaman awal moderator:** `/superadmin/pertanyaan` atau `/superadmin/prodi-kuesioner`

**Skenario untuk peserta:**

Universitas ingin menggunakan pertanyaan yang sudah ada pada section lain tanpa membuat pertanyaan duplikat.

**Task:**

1. Cari pertanyaan berdasarkan kode atau teks.
2. Periksa informasi asal pertanyaan.
3. Pilih pertanyaan yang sesuai.
4. Tempatkan pertanyaan pada section tujuan.
5. Tempatkan pertanyaan yang sama pada section kedua apabila tersedia.
6. Periksa apakah master pertanyaan tetap satu.
7. Hapus salah satu placement uji.

**Kriteria keberhasilan:**

- Peserta dapat menemukan pertanyaan dari bank.
- Peserta memahami perbedaan master pertanyaan dan penempatan.
- Peserta tidak membuat pertanyaan duplikat.
- Peserta dapat menghapus placement tanpa menghapus master yang masih digunakan.

---

## SA-10 — Mengelola Kuesioner Program Studi

**Halaman awal moderator:** `/superadmin/prodi-kuesioner`

**Skenario untuk peserta:**

Program studi meminta Superadmin menyesuaikan kuesioner khusus agar dapat digunakan dalam pengumpulan data alumni.

**Task:**

1. Pilih program studi yang memiliki data uji.
2. Buat atau buka section kuesioner Prodi.
3. Tambahkan pertanyaan baru.
4. Atur tipe dan status wajib.
5. Tambahkan opsi jawaban apabila diperlukan.
6. Simpan pertanyaan.
7. Ubah urutan pertanyaan.
8. Nonaktifkan pertanyaan uji.
9. Aktifkan kembali pertanyaan uji.

**Kriteria keberhasilan:**

- Peserta dapat memilih konteks program studi yang tepat.
- Peserta memahami bahwa kuesioner Prodi berbeda dari kuesioner Universitas.
- Perubahan hanya berlaku pada program studi yang dipilih.

---

## SA-11 — Mengelola Instrumen Evaluasi Atasan

**Halaman awal moderator:** `/superadmin/evaluasi-atasan`

**Skenario untuk peserta:**

Universitas ingin menambahkan aspek penilaian baru pada evaluasi yang diisi oleh pengguna lulusan atau atasan.

**Task:**

1. Buka daftar bagian evaluasi.
2. Buka daftar pertanyaan.
3. Buat pertanyaan evaluasi uji.
4. Isi kode, aspek, teks pertanyaan, tipe, dan urutan.
5. Periksa opsi skala Likert.
6. Simpan pertanyaan.
7. Edit teks pertanyaan.
8. Nonaktifkan pertanyaan uji.
9. Buka tab riwayat respons.
10. Cari respons yang sudah tersedia.

**Kriteria keberhasilan:**

- Peserta dapat membedakan bagian, pertanyaan, dan riwayat respons.
- Peserta dapat membuat pertanyaan evaluasi.
- Peserta memahami skala penilaian.
- Peserta dapat meninjau respons tanpa mengubah data respons.

---

## SA-12 — Membaca Analitik Universitas

**Halaman awal moderator:** `/superadmin/analitik`

**Skenario untuk peserta:**

Pimpinan meminta ringkasan hasil Tracer Study lulusan tahun 2026, termasuk indikator pekerjaan dan respons alumni.

**Task:**

1. Pilih tahun yang memiliki data uji.
2. Pilih program studi apabila tersedia.
3. Identifikasi indikator dengan nilai tertinggi.
4. Identifikasi indikator dengan nilai terendah.
5. Jelaskan arti jumlah dan persentase pada grafik.
6. Periksa jumlah responden valid.
7. Pilih salah satu kategori pada grafik.
8. Buka data detail atau drill-down yang tersedia.
9. Cari salah satu alumni dari data detail.
10. Kembali ke ringkasan grafik.

**Kriteria keberhasilan:**

- Peserta dapat menggunakan filter.
- Peserta memahami perbedaan jumlah dan persentase.
- Peserta dapat membaca indikator tertinggi dan terendah.
- Peserta dapat menelusuri grafik ke data detail.

---

## SA-13 — Membaca Analitik Program Studi

**Halaman awal moderator:** `/superadmin/analitik-prodi`

**Skenario untuk peserta:**

Universitas ingin membandingkan jawaban kuesioner khusus antarprogram studi dan menemukan pertanyaan yang memiliki hasil paling rendah.

**Task:**

1. Pilih program studi.
2. Pilih section.
3. Pilih pertanyaan yang memiliki respons.
4. Baca distribusi jawaban.
5. Identifikasi nilai tertinggi dan terendah.
6. Pilih elemen grafik untuk membuka detail.
7. Periksa tabel data pembentuk grafik.
8. Periksa teks lengkap pertanyaan.

**Kriteria keberhasilan:**

- Peserta dapat berpindah dari program studi ke section dan pertanyaan.
- Peserta memahami konteks grafik yang sedang dilihat.
- Peserta dapat membuka detail tanpa kehilangan konteks analisis.

---

## SA-14 — Membaca Analitik Evaluasi Atasan

**Halaman awal moderator:** `/superadmin/analitik-atasan`

**Skenario untuk peserta:**

Universitas ingin mengetahui penilaian pengguna lulusan terhadap kompetensi alumni.

**Task:**

1. Pilih tahun lulusan.
2. Pilih program studi.
3. Periksa jumlah evaluasi yang masuk.
4. Periksa distribusi kesiapan kerja apabila tersedia.
5. Periksa rata-rata penilaian kompetensi.
6. Identifikasi aspek dengan nilai tertinggi dan terendah.
7. Buka tabel penelusuran.
8. Cari salah satu alumni yang telah dievaluasi.

**Kriteria keberhasilan:**

- Peserta dapat menggunakan filter analitik.
- Peserta memahami jumlah respons dan rata-rata penilaian.
- Peserta dapat menghubungkan hasil analitik dengan alumni yang menjadi sumber data.

---

## SA-15 — Mengelola Akun dan Reset Kata Sandi

**Halaman awal moderator:** `/superadmin/manajemen-akun`

**Skenario untuk peserta:**

Seorang alumni melaporkan bahwa ia tidak dapat masuk ke sistem. Anda perlu mencari akunnya dan melakukan reset kata sandi.

**Task:**

1. Cari akun berdasarkan nama, NIM, atau email.
2. Periksa informasi akun.
3. Ubah satu data non-sensitif pada akun uji.
4. Simpan perubahan.
5. Jalankan proses reset kata sandi.
6. Periksa pesan konfirmasi.
7. Cari kembali akun tersebut.
8. Jelaskan apa yang harus dilakukan pengguna setelah reset.

**Kriteria keberhasilan:**

- Peserta menemukan akun yang benar.
- Peserta dapat membedakan edit data akun dan reset kata sandi.
- Peserta memahami bahwa pengguna perlu mengganti kata sandi setelah reset.
- Sistem menampilkan konfirmasi yang cukup jelas.

---

## SA-16 — Mengelola Master Wilayah, Negara, dan UMP

**Halaman awal moderator:** `/superadmin/master-wilayah`

**Skenario untuk peserta:**

Data wilayah dan UMP perlu diperbarui agar dapat digunakan pada profil alumni dan analitik.

**Task:**

1. Cari provinsi berdasarkan nama atau kode.
2. Tambahkan atau edit data provinsi uji.
3. Cari kabupaten berdasarkan provinsi.
4. Tambahkan atau edit data kabupaten uji.
5. Cari negara berdasarkan kode.
6. Tambahkan atau edit data negara uji.
7. Tambahkan data UMP untuk tahun uji.
8. Coba membuat data UMP dengan kombinasi provinsi dan tahun yang sama.
9. Periksa pesan validasi duplikasi.
10. Hapus data uji yang tidak direferensikan.

**Kriteria keberhasilan:**

- Peserta memahami pembagian master provinsi, kabupaten, negara, dan UMP.
- Peserta dapat menggunakan hubungan provinsi-kabupaten.
- Sistem menolak data duplikat dengan pesan yang dapat dipahami.
- Peserta tidak menghapus data master yang masih digunakan.

---

## SA-17 — Meninjau Sinkronisasi LinkedIn

**Halaman awal moderator:** `/superadmin/linkedin-sync`

**Skenario untuk peserta:**

Beberapa data profil LinkedIn alumni telah dikumpulkan. Gunakan halaman direktori sinkronisasi sebagai alur alternatif untuk memantau banyak alumni sekaligus, lalu buka kembali salah satu alumni pada halaman Data Alumni untuk melanjutkan audit detail.

**Task:**

1. Cari alumni yang memiliki hasil sinkronisasi.
2. Gunakan filter status atau tahun apabila tersedia.
3. Periksa status hasil sinkronisasi.
4. Buka detail hasil atau tautan menuju Data Alumni.
5. Lanjutkan peninjauan dari tab Hasil LinkedIn pada detail alumni.
6. Periksa histori sinkronisasi alumni.
7. Setujui atau tolak hasil uji apabila datanya sesuai atau tidak sesuai.
8. Kembali ke direktori dan periksa status setelah keputusan disimpan.

**Kriteria keberhasilan:**

- Peserta memahami perbedaan data hasil sinkronisasi dan data profil aktif.
- Peserta dapat membuka detail dan histori.
- Peserta mengambil keputusan berdasarkan informasi yang tersedia.
- Status keputusan terlihat setelah proses selesai.
- Peserta memahami bahwa halaman ini berfungsi untuk monitoring banyak alumni, sedangkan approval detail dilakukan melalui alur Data Alumni.

---

## SA-18 — Meninjau Data Atasan

**Halaman awal moderator:** `/superadmin/atasan`

**Skenario untuk peserta:**

Anda ingin memastikan data pengguna lulusan atau atasan yang terdaftar dapat ditelusuri dengan benar.

**Task:**

1. Cari atasan berdasarkan nama.
2. Cari berdasarkan perusahaan.
3. Periksa alumni yang terkait.
4. Periksa status evaluasi apabila ditampilkan.
5. Buka detail atau tautan terkait apabila tersedia.

**Kriteria keberhasilan:**

- Peserta dapat menemukan data atasan.
- Peserta memahami hubungan atasan, perusahaan, dan alumni.
- Peserta dapat membedakan data atasan dengan respons evaluasi.

---

## SA-19 — Membaca Log Aktivitas

**Halaman awal moderator:** `/superadmin/logs`

**Skenario untuk peserta:**

Terjadi perubahan pada data perusahaan dan Superadmin perlu mengetahui siapa yang melakukan perubahan serta nilai sebelum dan sesudahnya.

**Task:**

1. Cari log berdasarkan jenis tindakan.
2. Cari log berdasarkan model atau data yang berubah.
3. Gunakan filter tanggal apabila tersedia.
4. Cari aktivitas yang dilakukan oleh pengguna tertentu.
5. Buka detail perubahan.
6. Identifikasi nilai sebelum perubahan.
7. Identifikasi nilai setelah perubahan.
8. Identifikasi waktu dan pengguna yang melakukan tindakan.

**Kriteria keberhasilan:**

- Peserta dapat menemukan aktivitas yang diminta.
- Peserta dapat membaca old value dan new value.
- Peserta dapat menyimpulkan siapa dan kapan perubahan dilakukan.

---

## SA-20 — Mengekspor Data

**Halaman awal moderator:** `/superadmin/alumni`

**Skenario untuk peserta:**

Pimpinan meminta data alumni untuk dianalisis lebih lanjut di luar sistem.

**Task:**

1. Pilih data atau filter yang akan diekspor.
2. Ekspor data alumni secara massal apabila tersedia.
3. Buka file hasil ekspor.
4. Periksa nama file dan formatnya.
5. Periksa header kolom.
6. Cocokkan beberapa data dengan halaman aplikasi.
7. Ekspor detail satu alumni dalam format Excel apabila tersedia.

**Kriteria keberhasilan:**

- Peserta dapat menemukan fungsi ekspor.
- File berhasil diunduh.
- File dapat dibuka.
- Data dan header dapat dipahami serta sesuai dengan filter.

---

## SA-21 — Logout dan Pengujian Sesi

**Halaman awal moderator:** Halaman Superadmin apa pun.

**Skenario untuk peserta:**

Anda telah selesai bekerja dan ingin keluar dari sistem dengan aman.

**Task:**

1. Cari fungsi logout.
2. Keluar dari sistem.
3. Kembali ke halaman Dashboard menggunakan tombol Back browser.
4. Coba membuka halaman internal secara langsung.
5. Jelaskan hasil yang Anda harapkan.

**Kriteria keberhasilan:**

- Peserta dapat logout tanpa bantuan.
- Halaman internal tidak dapat digunakan tanpa sesi login.
- Sistem mengarahkan peserta ke halaman publik atau login sesuai perilaku aplikasi.

---

## 10. Task Prioritas

Apabila waktu pengujian terbatas, gunakan task berikut sebagai task kritis:

| Prioritas | Task | Alasan |
|---|---|---|
| Kritis | SA-01 | Menentukan apakah peserta memahami navigasi awal |
| Kritis | SA-02 | Menilai pemahaman terhadap informasi utama |
| Kritis | SA-03 | Menguji pencarian dan audit data alumni |
| Kritis | SA-04A | Menguji sinkronisasi dan approval dari detail Data Alumni |
| Kritis | SA-05 | Menguji proses verifikasi perusahaan |
| Kritis | SA-06 | Menguji pengelolaan section kuesioner |
| Kritis | SA-07 | Menguji pengelolaan pertanyaan |
| Kritis | SA-12 | Menguji pemahaman analitik utama |
| Kritis | SA-15 | Menguji pengelolaan akun dan reset sandi |
| Pendukung | SA-09 | Menguji bank pertanyaan dan placement |
| Pendukung | SA-11 | Menguji instrumen Evaluasi Atasan |
| Pendukung | SA-16 | Menguji master wilayah dan UMP |
| Pendukung | SA-17 | Menguji review LinkedIn |
| Pendukung | SA-19 | Menguji audit trail |
| Pendukung | SA-20 | Menguji ekspor data |

---

## 11. Kriteria Penilaian Task

| Nilai | Keterangan |
|---:|---|
| 0 | Peserta tidak dapat menyelesaikan task |
| 1 | Peserta selesai dengan bantuan besar atau diarahkan langkah demi langkah |
| 2 | Peserta selesai dengan sedikit bantuan |
| 3 | Peserta selesai mandiri, tetapi masih ragu, lambat, atau melakukan kesalahan |
| 4 | Peserta selesai mandiri dan lancar |

Task dianggap berhasil apabila tujuan utama tercapai. Task tidak dianggap lancar apabila peserta hanya berhasil setelah moderator menunjukkan tombol atau lokasi fitur.

---

## 12. Lembar Observasi Moderator

| Kode task | Berhasil | Waktu | Salah klik | Bantuan level | Nilai | Catatan perilaku |
|---|---|---:|---:|---:|---:|---|
| SA-01 | Ya/Tidak | .... | .... | 0–5 | 0–4 | .... |
| SA-02 | Ya/Tidak | .... | .... | 0–5 | 0–4 | .... |
| SA-03 | Ya/Tidak | .... | .... | 0–5 | 0–4 | .... |
| SA-05 | Ya/Tidak | .... | .... | 0–5 | 0–4 | .... |
| SA-06 | Ya/Tidak | .... | .... | 0–5 | 0–4 | .... |
| SA-07 | Ya/Tidak | .... | .... | 0–5 | 0–4 | .... |
| SA-12 | Ya/Tidak | .... | .... | 0–5 | 0–4 | .... |
| SA-15 | Ya/Tidak | .... | .... | 0–5 | 0–4 | .... |

### Catatan kejadian

| Waktu | Halaman | Kejadian | Dampak | Kutipan peserta |
|---|---|---|---|---|
| .... | .... | .... | Rendah/Sedang/Tinggi | “................................” |
| .... | .... | .... | Rendah/Sedang/Tinggi | “................................” |
| .... | .... | .... | Rendah/Sedang/Tinggi | “................................” |

---

## 13. Pertanyaan Setelah Setiap Task

Ajukan pertanyaan berikut setelah task selesai:

1. Seberapa mudah task ini dilakukan dari skala 1 sampai 7?
2. Bagian mana yang paling membingungkan?
3. Apa yang Anda harapkan terjadi pada langkah tersebut?
4. Apakah nama menu, tombol, dan status sesuai dengan pemahaman Anda?
5. Apakah Anda yakin task sudah berhasil?
6. Apakah ada informasi yang seharusnya ditampilkan tetapi tidak tersedia?

Skala:

| Nilai | Keterangan |
|---:|---|
| 1 | Sangat sulit |
| 2 | Sulit |
| 3 | Agak sulit |
| 4 | Netral |
| 5 | Agak mudah |
| 6 | Mudah |
| 7 | Sangat mudah |

---

## 14. Pertanyaan Wawancara Akhir

1. Bagian sistem apa yang paling mudah digunakan?
2. Bagian sistem apa yang paling sulit digunakan?
3. Fitur apa yang paling sering Anda gunakan sebagai Superadmin?
4. Apakah Dashboard membantu menentukan pekerjaan yang harus dilakukan?
5. Apakah Anda dapat membedakan data alumni, perusahaan, atasan, dan evaluasi?
6. Apakah struktur pengelolaan kuesioner mudah dipahami?
7. Apakah grafik analitik cukup jelas untuk mendukung pengambilan keputusan?
8. Apakah Anda merasa yakin ketika menyimpan atau mengubah data?
9. Apakah pesan keberhasilan dan kesalahan cukup jelas?
10. Tiga hal apa yang paling perlu diperbaiki?
11. Apakah ada task penting Superadmin yang belum tercakup dalam pengujian?

---

## 15. Analisis Hasil

### 15.1 Persentase keberhasilan task

```text
Persentase keberhasilan =
Jumlah task berhasil / Jumlah seluruh task × 100%
```

### 15.2 Persentase penyelesaian mandiri

```text
Persentase mandiri =
Jumlah task dengan nilai 3 atau 4 / Jumlah seluruh task × 100%
```

### 15.3 Rata-rata waktu

```text
Rata-rata waktu =
Total waktu penyelesaian / Jumlah task yang berhasil
```

### 15.4 Rata-rata kesalahan

```text
Rata-rata kesalahan =
Total salah klik atau kesalahan / Jumlah task
```

### 15.5 Kriteria keberhasilan pengujian

Pengujian Superadmin dianggap memenuhi target apabila:

1. Minimal 80% task kritis berhasil diselesaikan.
2. Minimal 80% task kritis diselesaikan secara mandiri dengan nilai minimal 3.
3. Tidak terdapat masalah kritis pada login, penyimpanan data, verifikasi perusahaan, analitik, dan reset akun.
4. Peserta dapat menemukan fungsi utama tanpa diarahkan langsung oleh moderator.
5. Peserta dapat menjelaskan kembali hasil atau status setelah melakukan perubahan.

---

## 16. Klasifikasi Temuan Masalah

| Tingkat | Definisi | Contoh |
|---|---|---|
| Kritis | Peserta tidak dapat menyelesaikan pekerjaan atau berisiko kehilangan data | Tidak dapat menyimpan pertanyaan |
| Tinggi | Peserta dapat melanjutkan hanya setelah dibantu atau terjadi salah keputusan | Salah memverifikasi perusahaan |
| Sedang | Peserta berhasil tetapi membutuhkan waktu lama atau melakukan beberapa kesalahan | Tidak memahami filter analitik |
| Rendah | Masalah kecil yang tidak menghambat task | Label kurang konsisten |

### Format temuan

| ID | Task | Halaman | Masalah | Frekuensi | Dampak | Prioritas | Rekomendasi |
|---|---|---|---|---:|---|---|---|
| F-01 | SA-... | `/...` | ................................ | .... | .... | .... | ................................ |
| F-02 | SA-... | `/...` | ................................ | .... | .... | .... | ................................ |

---

## 17. Ringkasan Hasil Pengujian

| Indikator | Hasil |
|---|---:|
| Jumlah peserta | .... |
| Jumlah task kritis | .... |
| Task kritis berhasil | .... |
| Persentase keberhasilan | ....% |
| Task kritis mandiri | .... |
| Persentase penyelesaian mandiri | ....% |
| Rata-rata waktu task | .... menit |
| Rata-rata kesalahan | .... |
| Temuan kritis | .... |
| Temuan tinggi | .... |
| Temuan sedang | .... |
| Temuan rendah | .... |

### Kesimpulan sementara

................................................................................

................................................................................

### Rekomendasi prioritas

1. .............................................................................
2. .............................................................................
3. .............................................................................

### Keputusan tindak lanjut

| Temuan | Tindakan | Penanggung jawab | Status |
|---|---|---|---|
| .... | .... | .... | Belum mulai / Berjalan / Selesai |
| .... | .... | .... | Belum mulai / Berjalan / Selesai |

---

## 18. Naskah Penutupan Moderator

> Terima kasih atas waktu dan masukan yang diberikan. Semua kesulitan yang muncul selama pengujian akan digunakan untuk memperbaiki Portal Tracer Study UKDW. Tidak ada penilaian terhadap kemampuan Anda sebagai peserta. Catatan yang kami gunakan berfokus pada pengalaman penggunaan sistem.

