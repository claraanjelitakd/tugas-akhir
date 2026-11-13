# Instrumen Moderated Usability Testing

## Portal Tracer Study UKDW — Stakeholder Alumni

## 1. Identitas Dokumen

| Elemen | Keterangan |
|---|---|
| Produk | Portal Tracer Study UKDW |
| Metode | Moderated Usability Testing |
| Stakeholder | Alumni |
| Moderator | ................................................ |
| Tanggal pengujian | ................................................ |
| Versi aplikasi | ................................................ |
| Lingkungan pengujian | Lokal / Staging / Lainnya: ....................... |
| Durasi target | 45–60 menit per peserta |

Dokumen ini digunakan untuk menguji kemudahan Alumni dalam mengakses portal, melengkapi profil, mengisi kuesioner, dan mengirim undangan evaluasi kepada atasan. PSSUQ tidak termasuk dalam instrumen ini dan dapat diberikan setelah seluruh task selesai.

---

## 2. Tujuan Pengujian

Pengujian bertujuan untuk mengetahui apakah Alumni dapat:

1. Login dan memahami navigasi utama portal.
2. Menggunakan alur lupa password dan menerima tautan pemulihan melalui email.
3. Mengganti password wajib setelah login pertama atau ketika diarahkan sistem.
4. Memahami informasi progres pada Dashboard.
5. Mengisi serta memperbarui data pribadi, alamat, dan akademik.
6. Mengisi data karier, perusahaan, serta atasan secara benar.
7. Memahami bahwa nama atasan diisi berdasarkan data atasan Alumni sendiri.
8. Mengisi Kuesioner Universitas sampai menyimpan jawaban.
9. Mengikuti pertanyaan bercabang atau `Jump To` apabila muncul.
10. Mengisi satu bagian perwakilan Kuesioner Prodi.
11. Mengirim undangan evaluasi kepada atasan.
12. Memahami pesan validasi, status tersimpan, dan status kelengkapan.
13. Logout dan memahami perilaku sesi setelah keluar.

---

## 3. Ruang Lingkup Pengujian

| Area | Halaman utama | Fokus pengujian |
|---|---|---|
| Autentikasi | `/login`, `/forgot-password`, `/reset-password/{token}`, `/change-password` | Login, lupa password, tautan reset melalui email, dan ganti password |
| Dashboard Alumni | `/alumni/dashboard` | Pemahaman progres profil dan kuesioner |
| Profil Alumni | `/alumni/profile` | Identitas, alamat, karier, perusahaan, atasan, LinkedIn, dan akademik |
| Perusahaan | Modal perusahaan pada Profil | Memilih perusahaan, mengusulkan perusahaan baru, dan memahami status pengajuan |
| Kuesioner Universitas | `/alumni/kuesioner` | Pengisian, navigasi section, validasi, branching, penyimpanan, dan submit |
| Kuesioner Prodi | `/alumni/kuesioner-prodi` | Satu task perwakilan untuk memahami konteks kuesioner prodi |
| Evaluasi Atasan | Aksi pada Profil Karier | Mengisi data atasan dan mengirim undangan evaluasi melalui email |
| Sesi | Seluruh halaman Alumni | Logout dan perlindungan halaman setelah sesi berakhir |

### 3.1 Fitur yang tidak menjadi fokus

- Approval atau rejection hasil sinkronisasi LinkedIn.
- Pengelolaan master perusahaan oleh administrator.
- Pengelolaan section dan pertanyaan kuesioner oleh Superadmin.
- Analitik universitas dan analitik program studi.
- Pengisian evaluasi oleh pengguna lulusan atau atasan.
- Pengujian performa, keamanan teknis, dan kompatibilitas browser secara mendalam.
- PSSUQ atau kuesioner kepuasan terstandar.

---

## 4. Karakteristik Peserta

Peserta adalah alumni atau pengguna yang mewakili alumni UKDW dan dapat menjalankan pengisian profil serta tracer study sebagai pengguna akhir.

### 4.1 Kriteria peserta

- Pernah atau berpotensi mengisi data alumni dan kuesioner tracer study.
- Tidak harus memahami istilah teknis aplikasi.
- Tidak diberi pelatihan khusus mengenai versi aplikasi yang sedang diuji.
- Bersedia menyampaikan pemikiran, keraguan, dan kebingungan selama pengujian.

### 4.2 Identitas peserta

| Elemen | Jawaban |
|---|---|
| Kode peserta | ................................................ |
| Nama/inisial | ................................................ |
| Tahun lulus atau status alumni | ................................................ |
| Program studi | ................................................ |
| Pengalaman menggunakan portal sejenis | ................................................ |
| Perangkat yang digunakan | ................................................ |
| Browser | ................................................ |
| Tanggal pengujian | ................................................ |

---

## 5. Persiapan Sebelum Pengujian

### 5.1 Persiapan moderator

Moderator menyiapkan:

1. Akun Alumni normal untuk alur login.
2. Akun Alumni dengan status wajib mengganti password untuk task `change-password`.
3. Akun atau data email uji yang dapat menerima tautan lupa password.
4. Data uji yang dapat diubah tanpa memengaruhi data produksi.
5. Alumni yang memiliki data profil sebagian lengkap dan sebagian belum lengkap.
6. Alumni dengan data perusahaan yang sudah tersedia.
7. Data kuesioner Universitas yang memiliki pertanyaan pilihan dan branching.
8. Kuesioner Prodi dengan minimal satu section dan pertanyaan aktif.
9. Alamat email uji untuk atasan.
10. Timer, lembar observasi, dan alat perekam apabila peserta menyetujui.
11. Data cadangan sebelum task yang mengubah profil atau jawaban.

### 5.2 Kondisi data minimum

- Akun Alumni dapat login dan diarahkan ke Dashboard.
- Tersedia email uji untuk menerima tautan reset password.
- Tersedia akun dengan `must_change_password` aktif untuk menguji ganti password wajib.
- Data akademik dasar dan program studi tersedia.
- Profil memiliki beberapa field yang sudah terisi dan beberapa field yang belum lengkap.
- Minimal satu perusahaan tersedia pada pilihan perusahaan.
- Data atasan dapat diisi dengan nama dan email uji.
- Kuesioner Universitas memiliki section aktif dan pertanyaan wajib.
- Tersedia minimal satu pertanyaan dengan pilihan yang dapat memunculkan pertanyaan lanjutan.
- Kuesioner Prodi memiliki minimal satu section dan pertanyaan aktif.
- Alamat email atasan uji dapat menerima atau mensimulasikan pengiriman email.

### 5.3 Data uji yang direkomendasikan

| Jenis data | Contoh data uji |
|---|---|
| Nama atasan | `Budi Santoso - Atasan Uji` |
| Email atasan | `atasan.uji@example.test` |
| Perusahaan | Pilih perusahaan yang sudah tersedia |
| Nama perusahaan usulan | `Perusahaan Uji Alumni` |
| Jawaban teks | `Pengalaman kerja membantu saya menerapkan kemampuan analisis data.` |
| Data profil | Nomor telepon, alamat, LinkedIn, posisi, dan bidang pekerjaan uji |

---

## 6. Prosedur Pengujian

### 6.1 Urutan sesi

| Tahap | Durasi target | Kegiatan |
|---|---:|---|
| Pembukaan | 3 menit | Perkenalan dan penjelasan tujuan |
| Pra-pengujian | 5 menit | Pertanyaan latar belakang peserta |
| Alur utama | 30–45 menit | Peserta menjalankan task secara berurutan |
| Wawancara akhir | 7 menit | Menggali kesan, hambatan, dan saran |
| Penutupan | 2 menit | Konfirmasi catatan dan ucapan terima kasih |

### 6.2 Aturan untuk peserta

1. Peserta mengerjakan task dengan akun Alumni.
2. Peserta diminta berpikir keras atau mengucapkan pertimbangannya.
3. Peserta boleh menyampaikan apabila tidak memahami suatu istilah atau tombol.
4. Peserta tidak perlu takut melakukan kesalahan.
5. Peserta tidak perlu menyelesaikan task yang tidak disebutkan moderator.

### 6.3 Aturan untuk moderator

1. Moderator membacakan skenario, bukan langkah teknis secara langsung.
2. Moderator tidak menyebut nama tombol sebelum peserta mencoba sendiri.
3. Moderator mencatat perilaku dan hambatan, bukan langsung memperbaiki kesalahan.
4. Jika peserta berhenti selama 60 detik, catat sebagai titik hambatan.
5. Jika peserta tidak dapat melanjutkan selama 120 detik, berikan bantuan bertahap.
6. Semua bantuan harus dicatat pada lembar observasi.
7. Task perubahan profil dan jawaban harus menggunakan data uji.

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

> Terima kasih telah bersedia mengikuti pengujian ini. Tujuan pengujian adalah mengetahui apakah Portal Tracer Study UKDW mudah digunakan oleh Alumni untuk mengisi profil dan kuesioner.
>
> Yang sedang diuji adalah sistemnya, bukan kemampuan Anda. Tidak ada jawaban benar atau salah. Silakan melakukan tugas seperti yang biasa Anda lakukan dan ceritakan apa yang sedang Anda pikirkan.
>
> Kami akan mencatat waktu, kesalahan, bagian yang membingungkan, dan bantuan yang diperlukan. Mohon jangan khawatir apabila mengalami kesulitan karena hal tersebut merupakan informasi penting untuk perbaikan sistem.

Apabila sesi direkam, tambahkan:

> Apakah Anda bersedia apabila layar dan suara selama sesi direkam untuk kepentingan analisis penelitian?

---

## 8. Pertanyaan Pra-Pengujian

1. Apakah Anda pernah mengisi tracer study atau kuesioner alumni sebelumnya?
2. Seberapa sering Anda menggunakan aplikasi berbasis web?
3. Data apa yang menurut Anda paling penting diperbarui setelah lulus?
4. Apakah Anda pernah mengisi data atasan atau mengirim undangan evaluasi sebelumnya?
5. Bagian apa yang biasanya membuat Anda ragu saat mengisi formulir online?

---

## 9. Tabel 1. Task Scenario

Task disusun sebagai alur utama yang saling berhubungan. Task Kuesioner Prodi dibuat sebagai task perwakilan, sedangkan Kuesioner Universitas diuji lebih lengkap.

| No | Fitur | Skenario | Task | Indikator Kesuksesan |
|---:|---|---|---|---|
| 1 | Login dan Sesi | Setelah menerima informasi akun, Anda ingin masuk ke Portal Tracer Study UKDW untuk memperbarui data alumni. | Buka `/login` > isi username atau NIM dan password > klik masuk > periksa halaman setelah login. | Berhasil login tanpa bantuan dan masuk ke Dashboard Alumni yang benar. |
| 1A | Ganti Password Wajib | Pada login pertama, sistem meminta Anda mengganti password bawaan sebelum melanjutkan. | Masuk dengan akun uji `must_change_password` > buka `/change-password` > isi password baru dan konfirmasi > simpan. | Peserta memahami alasan penggantian password, berhasil menyimpan password baru, dan diarahkan ke halaman Alumni. |
| 1B | Lupa Password | Anda tidak dapat mengingat password dan ingin meminta tautan pemulihan melalui email. | Dari halaman login > klik **Lupa kata sandi?** > buka `/forgot-password` > isi NIM, username, atau email > kirim permintaan. | Peserta menemukan fitur lupa password dan sistem menampilkan informasi bahwa tautan reset diproses atau dikirim ke email. |
| 1C | Reset Password melalui Email | Anda sudah menerima tautan reset password dan ingin membuat password baru. | Buka tautan email `/reset-password/{token}` > isi password baru dan konfirmasi > simpan > login kembali dengan password baru. | Peserta dapat mengikuti tautan, password baru tersimpan, dan login berhasil dengan kredensial baru. |
| 2 | Dashboard Alumni | Setelah login, Anda ingin mengetahui apakah profil dan kuesioner sudah lengkap. | Baca kartu progres profil, Kuesioner Universitas, dan Kuesioner Prodi > tentukan pekerjaan yang perlu dilakukan terlebih dahulu. | Peserta dapat menjelaskan status progres dan memilih tindakan berikutnya. |
| 3 | Profil Identitas dan Alamat | Anda ingin memastikan data pribadi dan alamat yang ditampilkan portal sudah benar. | Buka `/alumni/profile` > buka bagian Identitas dan Alamat > perbarui satu data uji > lanjutkan ke tahap berikutnya. | Field yang benar ditemukan, format input dipahami, dan perubahan tidak hilang saat berpindah tahap. |
| 4 | Profil Karier | Anda ingin melengkapi informasi pekerjaan setelah lulus. | Buka bagian Karier dan Jejaring > isi kategori pekerjaan, posisi, bidang, perusahaan, dan data pendukung yang relevan. | Peserta memahami hubungan antara kategori pekerjaan, posisi, dan perusahaan. |
| 5 | Data Atasan | Anda ingin mengisi nama atasan langsung untuk keperluan evaluasi pengguna lulusan. | Isi nama atasan, email atasan, dan nomor telepon bila tersedia > simpan profil. | Data atasan dapat diisi sebagai data milik Alumni sendiri dan tersimpan tanpa membingungkan peserta. |
| 6 | Perusahaan | Perusahaan tempat Anda bekerja belum tersedia atau perlu dipilih dari daftar. | Cari perusahaan pada pilihan yang tersedia > pilih perusahaan yang sesuai; jika tidak ada, buka opsi usulan perusahaan dan isi data uji. | Peserta dapat membedakan memilih perusahaan yang tersedia dan mengusulkan perusahaan baru. |
| 7 | Simpan Profil | Anda sudah mengisi beberapa bagian profil dan ingin memastikan seluruh perubahan tersimpan. | Klik tombol penyimpanan profil > perhatikan notifikasi > muat ulang halaman > periksa kembali identitas, karier, perusahaan, dan atasan. | Sistem menampilkan status penyimpanan yang jelas dan data tetap ada setelah reload. |
| 8 | Kuesioner Universitas: Navigasi | Anda ingin mulai mengisi kuesioner utama Tracer Study Universitas. | Buka `/alumni/kuesioner` > baca instruksi > mulai dari section pertama > gunakan tombol lanjut dan kembali. | Peserta memahami urutan section, status wajib, dan cara berpindah halaman. |
| 9 | Kuesioner Universitas: Pengisian | Anda ingin menjawab pertanyaan tentang pengalaman selama kuliah dan setelah lulus. | Isi pertanyaan wajib dan pilihan pada data uji > lanjutkan ke section berikutnya > periksa penanda pertanyaan yang belum lengkap. | Peserta dapat mengisi jawaban sesuai tipe input dan memahami validasi field wajib. |
| 10 | Kuesioner Universitas: Branching | Jawaban tertentu menyebabkan pertanyaan lanjutan tampil atau tidak tampil. | Pilih jawaban yang memicu pertanyaan lanjutan > baca pertanyaan yang muncul > ubah jawaban dan periksa perubahan alur. | Peserta memahami perubahan pertanyaan dan tidak menganggap pertanyaan yang tidak tampil sebagai error. |
| 11 | Kuesioner Universitas: Simpan dan Submit | Anda ingin menyimpan jawaban dan menyelesaikan kuesioner Universitas. | Simpan jawaban > kembali atau muat ulang bila diperlukan > periksa jawaban yang tersimpan > kirim kuesioner. | Jawaban tersimpan, pesan berhasil dipahami, dan status penyelesaian terlihat pada Dashboard. |
| 12 | Kuesioner Prodi | Program studi memiliki beberapa pertanyaan tambahan yang perlu Anda isi. | Buka `/alumni/kuesioner-prodi` > pilih atau buka section yang tersedia > isi satu bagian perwakilan > simpan atau lanjutkan. | Peserta dapat membedakan Kuesioner Prodi dari Kuesioner Universitas dan mengisi task perwakilan. |
| 13 | Undangan Evaluasi Atasan | Setelah data atasan lengkap, Anda ingin meminta atasan mengisi evaluasi tentang pengalaman kerja Anda. | Kembali ke bagian Karier > pilih aksi **Kirim email atasan** > periksa alamat penerima > konfirmasi pengiriman. | Peserta memahami penerima undangan, konfirmasi, dan notifikasi hasil pengiriman. |
| 14 | Pemeriksaan Status | Anda ingin memastikan perubahan profil, kuesioner, dan undangan evaluasi tercatat. | Kembali ke Dashboard atau Profil > periksa progres profil, status kuesioner, dan status undangan evaluasi atasan. | Peserta dapat menemukan status terbaru tanpa mengulang seluruh proses. |
| 15 | Logout dan Akses Sesi | Anda telah selesai mengisi data dan ingin keluar dari portal. | Buka menu akun > pilih logout > gunakan tombol Back atau buka URL internal secara langsung. | Logout berhasil dan halaman internal tidak dapat digunakan tanpa login kembali. |

---

## 10. Urutan Pelaksanaan dan Dependensi

| Alur | Urutan task | Keterangan |
|---|---|---|
| Alur autentikasi normal | 1 → 2 | Peserta login dengan akun normal lalu membaca Dashboard. |
| Cabang password wajib | 1 → 1A → 2 | Digunakan untuk akun yang ditandai wajib mengganti password pada login pertama. |
| Cabang lupa dan reset password | 1B → 1C → 1 → 2 | Peserta meminta tautan melalui email, membuat password baru, lalu login kembali. |
| Alur profil | 1 → 2 → 3 → 4 → 5 → 6 → 7 | Peserta memahami progres, melengkapi profil, mengisi perusahaan dan atasan, lalu menyimpan perubahan. |
| Alur Kuesioner Universitas | 7 → 8 → 9 → 10 → 11 | Kuesioner Universitas menjadi alur utama dan diuji dari navigasi sampai submit. |
| Alur Kuesioner Prodi | 11 → 12 | Kuesioner Prodi dicoba setelah kuesioner Universitas, tetapi hanya satu bagian perwakilan. |
| Alur Evaluasi Atasan | 5 → 13 → 14 | Data atasan harus tersedia sebelum undangan evaluasi dapat dikirim. |
| Alur penutupan | 14 → 15 | Peserta memeriksa status akhir sebelum logout. |

### Catatan Dependensi

- Task 1A, 1B, dan 1C adalah cabang autentikasi. Moderator tidak perlu menjalankan seluruh cabang pada peserta yang sama; pilih cabang sesuai akun uji dan waktu sesi.
- Task 1B dan 1C harus menggunakan email uji yang dapat dibuka peserta atau tautan reset yang telah disiapkan moderator.
- Task 1A tidak dijalankan menggunakan akun normal yang sudah pernah mengganti password karena flag wajib ganti password sudah tidak aktif.
- Task 5 dan 13 saling berhubungan. Email undangan tidak diuji sebelum data nama dan email atasan tersedia.
- Task 7 menjadi titik pemeriksaan sebelum peserta masuk ke Kuesioner Universitas.
- Task 8 sampai 11 merupakan rangkaian utama Kuesioner Universitas. Jika peserta gagal pada satu langkah, moderator membantu seperlunya lalu tetap mencatat task berikutnya secara terpisah.
- Task 12 hanya merupakan peninjauan dan pengisian singkat, bukan pengujian pengelolaan instrumen Prodi.
- Jika data email tidak benar-benar dikirim pada lingkungan uji, keberhasilan dinilai dari konfirmasi, flash, atau status proses yang ditampilkan sistem.

---

## 11. Task Prioritas

Jika waktu pengujian terbatas, gunakan task berikut:

| Prioritas | Task | Alasan |
|---|---|---|
| Kritis | 1 | Menguji login dan akses awal Alumni. |
| Kritis | 1B–1C | Menguji lupa password, tautan email, reset password, dan login ulang. |
| Pendukung | 1A | Menguji ganti password wajib pada login pertama. |
| Kritis | 2 | Menguji pemahaman progres dan prioritas pekerjaan. |
| Kritis | 3–7 | Menguji kelengkapan profil, perusahaan, dan data atasan. |
| Kritis | 8–11 | Menguji alur utama Kuesioner Universitas. |
| Kritis | 13 | Menguji pengiriman undangan evaluasi atasan. |
| Pendukung | 12 | Menguji pemahaman singkat Kuesioner Prodi. |
| Pendukung | 14–15 | Menguji pemeriksaan status dan logout. |

---

## 12. Kriteria Penilaian Task

| Nilai | Keterangan |
|---:|---|
| 0 | Peserta tidak dapat menyelesaikan task. |
| 1 | Peserta selesai dengan bantuan besar atau diarahkan langkah demi langkah. |
| 2 | Peserta selesai dengan sedikit bantuan. |
| 3 | Peserta selesai mandiri, tetapi masih ragu, lambat, atau melakukan kesalahan. |
| 4 | Peserta selesai mandiri dan lancar. |

Task dianggap berhasil apabila tujuan utama tercapai. Task tidak dianggap lancar apabila peserta hanya berhasil setelah moderator menunjukkan tombol atau lokasi fitur.

---

## 13. Lembar Observasi Moderator

| No. task | Berhasil | Waktu | Salah klik | Bantuan level | Nilai | Catatan perilaku |
|---:|---|---:|---:|---:|---:|---|
| 1 | Ya/Tidak | .... | .... | 0–5 | 0–4 | .... |
| 2 | Ya/Tidak | .... | .... | 0–5 | 0–4 | .... |
| 3–7 | Ya/Tidak | .... | .... | 0–5 | 0–4 | .... |
| 8–11 | Ya/Tidak | .... | .... | 0–5 | 0–4 | .... |
| 12 | Ya/Tidak | .... | .... | 0–5 | 0–4 | .... |
| 13 | Ya/Tidak | .... | .... | 0–5 | 0–4 | .... |
| 14–15 | Ya/Tidak | .... | .... | 0–5 | 0–4 | .... |

### Catatan kejadian

| Waktu | Halaman | Kejadian | Dampak | Kutipan peserta |
|---|---|---|---|---|
| .... | .... | .... | Rendah/Sedang/Tinggi | “................................” |
| .... | .... | .... | Rendah/Sedang/Tinggi | “................................” |
| .... | .... | .... | Rendah/Sedang/Tinggi | “................................” |

---

## 14. Pertanyaan Setelah Setiap Task

1. Seberapa mudah task ini dilakukan dari skala 1 sampai 7?
2. Bagian mana yang paling membingungkan?
3. Apa yang Anda harapkan terjadi pada langkah tersebut?
4. Apakah nama menu, tombol, dan status sesuai dengan pemahaman Anda?
5. Apakah Anda yakin task sudah berhasil?
6. Apakah ada informasi yang seharusnya ditampilkan tetapi tidak tersedia?

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

## 15. Pertanyaan Wawancara Akhir

1. Bagian sistem apa yang paling mudah digunakan?
2. Bagian sistem apa yang paling sulit digunakan?
3. Apakah proses lupa password dan menerima email reset mudah dipahami?
4. Apakah proses mengganti password baru jelas?
5. Apakah Dashboard membantu Anda mengetahui pekerjaan yang harus dilakukan?
6. Apakah pengisian Profil Alumni mudah dipahami?
7. Apakah input nama dan email atasan terasa jelas?
8. Apakah Kuesioner Universitas mudah diikuti dari awal sampai submit?
9. Apakah Anda memahami ketika pertanyaan lanjutan muncul atau tidak muncul?
10. Apakah perbedaan Kuesioner Universitas dan Kuesioner Prodi cukup jelas?
11. Apakah Anda yakin undangan evaluasi terkirim kepada atasan yang benar?
12. Tiga hal apa yang paling perlu diperbaiki?

---

## 16. Analisis Hasil

### 16.1 Persentase keberhasilan task

```text
Persentase keberhasilan =
Jumlah task berhasil / Jumlah seluruh task × 100%
```

### 16.2 Persentase penyelesaian mandiri

```text
Persentase mandiri =
Jumlah task dengan nilai 3 atau 4 / Jumlah seluruh task × 100%
```

### 16.3 Rata-rata waktu

```text
Rata-rata waktu =
Total waktu penyelesaian / Jumlah task yang berhasil
```

### 16.4 Kriteria keberhasilan pengujian

Pengujian Alumni dianggap memenuhi target apabila:

1. Minimal 80% task kritis berhasil diselesaikan.
2. Minimal 80% task kritis diselesaikan secara mandiri dengan nilai minimal 3.
3. Tidak terdapat masalah kritis pada login, pemulihan password, penyimpanan profil, submit kuesioner, atau pengiriman undangan evaluasi.
4. Peserta dapat membedakan Kuesioner Universitas dan Kuesioner Prodi.
5. Peserta memahami status data setelah menyimpan profil dan kuesioner.

---

## 17. Klasifikasi Temuan Masalah

| Tingkat | Definisi | Contoh |
|---|---|---|
| Kritis | Peserta tidak dapat menyelesaikan pekerjaan atau berisiko kehilangan data. | Jawaban kuesioner hilang setelah disimpan. |
| Tinggi | Peserta dapat melanjutkan hanya setelah dibantu atau terjadi salah keputusan. | Peserta mengirim undangan ke email atasan yang salah. |
| Sedang | Peserta berhasil tetapi membutuhkan waktu lama atau melakukan beberapa kesalahan. | Peserta tidak memahami navigasi section. |
| Rendah | Masalah kecil yang tidak menghambat task. | Label status kurang konsisten. |

### Format temuan

| ID | Task | Halaman | Masalah | Frekuensi | Dampak | Prioritas | Rekomendasi |
|---|---|---|---|---:|---|---|---|
| F-01 | T-... | `/...` | ................................ | .... | .... | .... | ................................ |
| F-02 | T-... | `/...` | ................................ | .... | .... | .... | ................................ |

---

## 18. Ringkasan Hasil Pengujian

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

## 19. Naskah Penutupan Moderator

> Terima kasih atas waktu dan masukan yang diberikan. Semua kesulitan yang muncul selama pengujian akan digunakan untuk memperbaiki Portal Tracer Study UKDW. Tidak ada penilaian terhadap kemampuan Anda sebagai peserta. Catatan yang kami gunakan berfokus pada pengalaman penggunaan sistem.
