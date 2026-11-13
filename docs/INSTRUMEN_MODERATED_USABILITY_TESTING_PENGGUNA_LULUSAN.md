# Instrumen Moderated Usability Testing

## Portal Tracer Study UKDW — Stakeholder Pengguna Lulusan / Atasan

## 1. Identitas Dokumen

| Elemen | Keterangan |
|---|---|
| Produk | Portal Tracer Study UKDW |
| Metode | Moderated Usability Testing |
| Stakeholder | Pengguna Lulusan / Atasan |
| Moderator | ................................................ |
| Tanggal pengujian | ................................................ |
| Versi aplikasi | ................................................ |
| Lingkungan pengujian | Lokal / Staging / Lainnya: ....................... |
| Durasi target | 20–30 menit per peserta |

Dokumen ini digunakan untuk menguji kemudahan pengguna lulusan atau atasan dalam mengisi evaluasi alumni UKDW melalui tautan publik. Peserta tidak perlu membuat akun atau login. PSSUQ dapat diberikan setelah formulir selesai dikirim.

---

## 2. Tujuan Pengujian

Pengujian bertujuan untuk mengetahui apakah Pengguna Lulusan atau Atasan dapat:

1. Membuka tautan evaluasi dari email atau pesan undangan.
2. Memahami identitas alumni, perusahaan, dan tujuan evaluasi.
3. Memahami bahwa evaluasi dapat diisi tanpa login.
4. Mengikuti navigasi langkah formulir.
5. Mengisi karakteristik perusahaan dan sebaran alumni UKDW.
6. Memberikan penilaian kesiapan, kinerja, dan kompetensi alumni.
7. Memahami pilihan skala penilaian dan pertanyaan wajib.
8. Memperbaiki jawaban sebelum formulir dikirim.
9. Mengirim evaluasi dan memahami konfirmasi bahwa respon berhasil tersimpan.
10. Mengetahui perilaku tautan apabila token tidak valid atau evaluasi sudah pernah dikirim.

---

## 3. Ruang Lingkup Pengujian

| Area | Halaman utama | Fokus pengujian |
|---|---|---|
| Tautan publik | `/evaluasi-atasan/{token}` | Membuka evaluasi tanpa login |
| Identitas evaluasi | Banner dan ringkasan halaman | Memastikan alumni dan perusahaan yang dinilai sudah benar |
| Karakteristik perusahaan | Langkah Karakteristik Perusahaan & Sebaran Alumni | Jumlah pegawai, jumlah alumni UKDW, dan standar gaji pertama |
| Penilaian alumni | Langkah Penilaian Kesiapan & Kinerja Alumni | Kesiapan kerja, kompetensi, skala penilaian, dan catatan |
| Submit evaluasi | Tombol kirim pada langkah terakhir | Validasi, konfirmasi, dan penyimpanan respon |
| Status token | Halaman not found atau status submitted | Token salah, token tidak ditemukan, atau evaluasi sudah selesai |

### 3.1 Fitur yang tidak menjadi fokus

- Login atau pembuatan akun Pengguna Lulusan.
- Pengelolaan pertanyaan oleh Superadmin.
- Pengubahan master perusahaan.
- Pengubahan data profil alumni.
- Analitik evaluasi oleh administrator.
- Pengujian performa, keamanan teknis, dan kompatibilitas browser secara mendalam.
- PSSUQ atau kuesioner kepuasan terstandar.

---

## 4. Karakteristik Peserta

Peserta adalah atasan langsung, pengguna lulusan, supervisor, HR, atau pihak perusahaan yang mengenal kinerja alumni yang dievaluasi.

### 4.1 Kriteria peserta

- Mengenal alumni yang tercantum pada tautan evaluasi.
- Pernah atau bersedia menilai kinerja dan kompetensi alumni.
- Tidak harus memahami sistem tracer study.
- Dapat membaca email atau pesan undangan dan membuka tautan.
- Bersedia menyampaikan kebingungan selama pengisian.

### 4.2 Identitas peserta

| Elemen | Jawaban |
|---|---|
| Kode peserta | ................................................ |
| Nama/inisial | ................................................ |
| Jabatan | ................................................ |
| Instansi/perusahaan | ................................................ |
| Hubungan dengan alumni | Atasan langsung / HR / Pengguna lulusan / Lainnya |
| Lama mengenal alumni | ................................................ |
| Perangkat yang digunakan | ................................................ |
| Browser | ................................................ |
| Tanggal pengujian | ................................................ |

---

## 5. Persiapan Sebelum Pengujian

### 5.1 Persiapan moderator

Moderator menyiapkan:

1. Satu token evaluasi aktif yang belum pernah disubmit.
2. Nama alumni dan perusahaan yang sesuai dengan token.
3. Token tidak valid untuk menguji halaman error.
4. Token yang sudah pernah disubmit untuk menguji status selesai.
5. Data pertanyaan aktif yang memiliki pilihan skala penilaian.
6. Data uji perusahaan, jumlah pegawai, jumlah alumni UKDW, dan standar gaji pertama.
7. Timer, lembar observasi, dan alat perekam apabila peserta menyetujui.
8. Rencana pengujian ulang apabila peserta menutup halaman sebelum submit.

### 5.2 Kondisi data minimum

- Token evaluasi aktif dapat dibuka melalui URL publik.
- Informasi alumni dan perusahaan tersedia.
- Tersedia minimal satu pertanyaan wajib pada langkah karakteristik.
- Tersedia pertanyaan kesiapan kerja dan kompetensi dengan opsi skala.
- Tersedia area saran atau catatan evaluasi.
- Token sudah disubmit untuk pengujian akses ulang.

---

## 6. Prosedur Pengujian

### 6.1 Urutan sesi

| Tahap | Durasi target | Kegiatan |
|---|---:|---|
| Pembukaan | 3 menit | Perkenalan dan penjelasan tujuan |
| Pra-pengujian | 3 menit | Pertanyaan latar belakang peserta |
| Pengisian evaluasi | 10–15 menit | Peserta membuka token dan mengisi formulir |
| Pengujian status | 3 menit | Menguji validasi, token, atau akses ulang |
| Wawancara akhir | 5 menit | Menggali kesan, hambatan, dan saran |
| Penutupan | 1 menit | Konfirmasi catatan dan ucapan terima kasih |

### 6.2 Aturan untuk peserta

1. Peserta mengisi evaluasi berdasarkan pengalaman nyata terhadap alumni.
2. Peserta tidak perlu login atau membuat akun.
3. Peserta boleh bertanya, tetapi moderator mencatat pertanyaan tersebut sebagai potensi masalah usability.
4. Peserta diminta menyampaikan alasan ketika memilih jawaban.
5. Peserta tidak perlu menghafalkan isi pertanyaan.

### 6.3 Aturan untuk moderator

1. Moderator mengirim atau menampilkan tautan, bukan menjelaskan seluruh langkah pengisian.
2. Moderator tidak mengarahkan jawaban penilaian peserta.
3. Moderator mencatat apakah peserta memeriksa identitas alumni sebelum mengisi.
4. Moderator mencatat apabila peserta salah memahami skala atau status tombol.
5. Jika peserta berhenti selama 60 detik, catat sebagai titik hambatan.
6. Jika peserta tidak dapat melanjutkan selama 120 detik, berikan bantuan bertahap.

### 6.4 Bantuan bertahap

| Level | Bentuk bantuan |
|---:|---|
| 0 | Tidak ada bantuan |
| 1 | Bertanya, “Bagian mana yang sedang Anda cari?” |
| 2 | Mengulang tujuan pengisian tanpa menyebut jawaban |
| 3 | Menjelaskan fungsi langkah secara umum |
| 4 | Menunjukkan lokasi tombol atau bagian formulir |
| 5 | Moderator membantu menyelesaikan langkah |

---

## 7. Naskah Pembukaan Moderator

> Terima kasih telah bersedia mengikuti pengujian ini. Anda akan mencoba formulir evaluasi alumni UKDW yang biasanya diakses melalui tautan undangan.
>
> Yang sedang diuji adalah kemudahan formulirnya, bukan kemampuan Anda dalam menilai alumni. Tidak ada jawaban benar atau salah untuk penilaian kinerja. Silakan ceritakan apa yang Anda pikirkan saat membaca dan mengisi formulir.
>
> Kami akan mencatat waktu, bagian yang membingungkan, dan bantuan yang diperlukan. Mohon jangan khawatir apabila mengalami kesulitan karena hal tersebut menjadi masukan untuk perbaikan sistem.

Apabila sesi direkam, tambahkan:

> Apakah Anda bersedia apabila layar dan suara selama sesi direkam untuk kepentingan analisis penelitian?

---

## 8. Pertanyaan Pra-Pengujian

1. Apa jabatan atau hubungan Anda dengan alumni yang akan dinilai?
2. Apakah Anda pernah mengisi evaluasi alumni atau survei kinerja sebelumnya?
3. Apakah Anda biasanya menerima formulir melalui email, WhatsApp, atau media lain?
4. Informasi apa yang Anda harapkan terlihat sebelum mulai menilai alumni?
5. Bagian apa yang biasanya membuat Anda ragu saat mengisi survei online?

---

## 9. Tabel 1. Task Scenario

Task disusun sebagai alur pengisian evaluasi publik. Task token error dan token yang sudah selesai menjadi task alternatif, bukan task yang harus dijalankan pada token aktif yang sama.

| No | Fitur | Skenario | Task | Indikator Kesuksesan |
|---:|---|---|---|---|
| 1 | Membuka Tautan Evaluasi | Anda menerima tautan evaluasi dari alumni atau universitas melalui email atau pesan. | Buka tautan `/evaluasi-atasan/{token}` tanpa login > tunggu halaman selesai dimuat. | Peserta dapat membuka formulir tanpa mencari halaman login atau membuat akun. |
| 2 | Memeriksa Identitas Evaluasi | Sebelum menjawab, Anda ingin memastikan alumni dan perusahaan yang tampil sudah benar. | Baca nama alumni, program studi, perusahaan, dan informasi pengantar evaluasi. | Peserta dapat menyebutkan siapa alumni yang dinilai dan memahami tujuan formulir. |
| 3 | Navigasi Langkah Formulir | Formulir memiliki beberapa langkah pengisian. | Baca stepper > buka langkah karakteristik > berpindah ke langkah penilaian > kembali ke langkah sebelumnya. | Peserta memahami urutan langkah dan dapat berpindah tanpa kehilangan jawaban. |
| 4 | Karakteristik Perusahaan | Anda diminta melengkapi informasi umum perusahaan dan sebaran alumni UKDW. | Isi jumlah pegawai, jumlah alumni UKDW, dan standar gaji pertama apabila field tersedia. | Peserta memahami label, tipe input, satuan, dan field yang wajib diisi. |
| 5 | Penilaian Kesiapan Kerja | Anda ingin menilai kesiapan kerja alumni ketika mulai bekerja. | Buka langkah penilaian > pilih satu tingkat kesiapan kerja sesuai pengalaman. | Peserta memahami arti skala dan dapat memilih satu jawaban dengan yakin. |
| 6 | Penilaian Kompetensi Alumni | Anda diminta memberi penilaian terhadap beberapa kompetensi alumni. | Isi seluruh pertanyaan kompetensi menggunakan skala yang tersedia > baca label penilaian sebelum memilih. | Semua pertanyaan wajib terisi dan peserta memahami arah skala penilaian. |
| 7 | Saran dan Catatan | Anda ingin memberikan masukan untuk pengembangan alumni. | Isi saran atau catatan evaluasi > periksa kembali isi jawaban. | Peserta menemukan area catatan dan memahami bahwa masukan bersifat opsional atau wajib sesuai label. |
| 8 | Validasi Sebelum Submit | Anda belum mengisi salah satu pertanyaan wajib dan mencoba mengirim formulir. | Kosongkan satu field wajib > klik tombol kirim atau lanjutkan > baca pesan validasi > lengkapi field tersebut. | Sistem menunjukkan field yang belum lengkap dan peserta dapat memperbaikinya tanpa kehilangan jawaban lain. |
| 9 | Review dan Submit | Anda sudah yakin seluruh jawaban benar dan ingin mengirim evaluasi. | Periksa kembali jawaban > klik tombol kirim evaluasi > konfirmasi apabila muncul. | Evaluasi berhasil dikirim dan peserta memahami bahwa jawaban tersimpan. |
| 10 | Konfirmasi Berhasil | Setelah submit, Anda ingin memastikan evaluasi benar-benar diterima. | Baca halaman konfirmasi > identifikasi nama alumni, perusahaan, dan pesan penerimaan respon. | Peserta yakin proses selesai dan dapat menjelaskan bukti keberhasilan pengiriman. |
| 11 | Token Tidak Valid | Anda membuka tautan yang tidak lengkap atau tokennya salah. | Buka `/evaluasi-atasan/{token-tidak-valid}`. | Sistem menampilkan pesan error yang jelas dan peserta memahami bahwa tautan tidak dapat digunakan. |
| 12 | Evaluasi Sudah Selesai | Anda membuka kembali tautan yang sebelumnya sudah digunakan untuk submit. | Buka token berstatus submitted > baca informasi halaman. | Sistem menampilkan status bahwa evaluasi telah selesai dan tidak menampilkan form baru untuk diisi ulang. |

---

## 10. Urutan Pelaksanaan dan Dependensi

| Alur | Urutan task | Keterangan |
|---|---|---|
| Alur pengisian utama | 1 → 2 → 3 → 4 → 5 → 6 → 7 → 8 → 9 → 10 | Peserta membuka tautan, memeriksa konteks, mengisi dua langkah, memperbaiki validasi, lalu submit. |
| Alur token error | 11 | Dijalankan menggunakan token terpisah agar tidak mengganggu evaluasi aktif. |
| Alur token selesai | 12 | Dijalankan menggunakan token yang memang sudah pernah disubmit. |

### Catatan Dependensi

- Task 2 harus dilakukan sebelum peserta mengisi jawaban agar dapat diketahui apakah identitas alumni dan perusahaan mudah diverifikasi.
- Task 4 sampai 7 merupakan isi utama evaluasi dan dapat dijalankan berurutan.
- Task 8 sengaja menggunakan satu field kosong untuk menguji validasi; moderator harus memastikan jawaban tidak benar-benar hilang.
- Task 9 dan 10 merupakan satu rangkaian submit dan konfirmasi.
- Task 11 dan 12 tidak dijalankan pada token aktif yang sama dengan task utama.

---

## 11. Task Prioritas

Jika waktu pengujian terbatas, gunakan task berikut:

| Prioritas | Task | Alasan |
|---|---|---|
| Kritis | 1–3 | Menguji akses publik, konteks identitas, dan navigasi formulir. |
| Kritis | 4–6 | Menguji pengisian karakteristik dan penilaian utama. |
| Kritis | 8–10 | Menguji validasi, submit, dan konfirmasi berhasil. |
| Pendukung | 7 | Menguji saran dan catatan evaluasi. |
| Pendukung | 11–12 | Menguji pesan token invalid dan evaluasi yang sudah selesai. |

---

## 12. Kriteria Penilaian Task

| Nilai | Keterangan |
|---:|---|
| 0 | Peserta tidak dapat menyelesaikan task. |
| 1 | Peserta selesai dengan bantuan besar atau diarahkan langkah demi langkah. |
| 2 | Peserta selesai dengan sedikit bantuan. |
| 3 | Peserta selesai mandiri, tetapi masih ragu, lambat, atau melakukan kesalahan. |
| 4 | Peserta selesai mandiri dan lancar. |

Task dianggap berhasil apabila tujuan utama tercapai. Task tidak dianggap lancar apabila peserta hanya berhasil setelah moderator menunjukkan lokasi tombol atau jawaban.

---

## 13. Lembar Observasi Moderator

| No. task | Berhasil | Waktu | Salah klik | Bantuan level | Nilai | Catatan perilaku |
|---:|---|---:|---:|---:|---:|---|
| 1–3 | Ya/Tidak | .... | .... | 0–5 | 0–4 | .... |
| 4–7 | Ya/Tidak | .... | .... | 0–5 | 0–4 | .... |
| 8–10 | Ya/Tidak | .... | .... | 0–5 | 0–4 | .... |
| 11–12 | Ya/Tidak | .... | .... | 0–5 | 0–4 | .... |

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
4. Apakah nama langkah, label skala, dan tombol sesuai dengan pemahaman Anda?
5. Apakah Anda yakin evaluasi sudah berhasil dikirim?
6. Apakah ada informasi yang seharusnya tersedia tetapi tidak ditampilkan?

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

1. Apakah tautan evaluasi mudah dibuka?
2. Apakah Anda yakin alumni dan perusahaan yang tampil sudah benar?
3. Apakah pembagian langkah formulir membantu pengisian?
4. Apakah istilah dan skala penilaian mudah dipahami?
5. Apakah Anda tahu pertanyaan mana yang wajib diisi?
6. Apakah pesan validasi membantu ketika ada jawaban yang belum lengkap?
7. Apakah Anda yakin respon sudah tersimpan setelah submit?
8. Apakah ada kekhawatiran terkait pengisian evaluasi tanpa login?
9. Tiga hal apa yang paling perlu diperbaiki?

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

### 16.3 Kriteria keberhasilan pengujian

Pengujian Pengguna Lulusan dianggap memenuhi target apabila:

1. Minimal 80% task kritis berhasil diselesaikan.
2. Minimal 80% task kritis diselesaikan secara mandiri dengan nilai minimal 3.
3. Tidak terdapat masalah kritis pada pembukaan token, pengisian, validasi, submit, atau konfirmasi.
4. Peserta dapat menjelaskan siapa alumni yang dinilai dan tujuan evaluasi.
5. Peserta yakin bahwa respon telah tersimpan setelah submit.

---

## 17. Klasifikasi Temuan Masalah

| Tingkat | Definisi | Contoh |
|---|---|---|
| Kritis | Peserta tidak dapat mengisi atau mengirim evaluasi. | Tombol submit tidak dapat digunakan tanpa pesan yang jelas. |
| Tinggi | Peserta dapat melanjutkan hanya setelah dibantu atau berisiko salah menilai. | Peserta tidak memahami arah skala penilaian. |
| Sedang | Peserta berhasil tetapi membutuhkan waktu lama atau melakukan beberapa kesalahan. | Peserta tidak tahu harus berpindah ke langkah penilaian. |
| Rendah | Masalah kecil yang tidak menghambat task. | Teks pengantar terlalu panjang. |

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

> Terima kasih atas waktu dan evaluasi yang diberikan. Respon Anda membantu UKDW memahami kesiapan dan kinerja alumni di dunia kerja. Catatan yang kami gunakan berfokus pada pengalaman penggunaan formulir, bukan pada penilaian pribadi Anda.
