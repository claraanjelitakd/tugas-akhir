# Task Usability Testing Portal Tracer Study UKDW

Dokumen ini digunakan untuk menguji apakah setiap stakeholder dapat menyelesaikan tugas utama tanpa bantuan teknis. Penguji mencatat waktu, kesalahan, titik kebingungan, dan komentar spontan peserta.

## Aturan pelaksanaan

- Gunakan data uji tahun 2026.
- Jangan memberi instruksi teknis seperti nama tabel, endpoint, atau nama kolom kepada peserta.
- Bacakan skenario, lalu biarkan peserta menentukan langkahnya sendiri.
- Jika peserta berhenti lebih dari 60 detik, catat sebagai titik hambatan.
- Satu task berhasil jika peserta mencapai hasil yang diminta tanpa koreksi data manual.
- Untuk task yang mengubah data, gunakan akun dan data uji.
- Catat: berhasil/gagal, durasi, salah klik, bantuan, dan komentar peserta.

## Skala penilaian

| Nilai | Arti |
| --- | --- |
| 0 | Tidak dapat menyelesaikan |
| 1 | Selesai dengan bantuan besar |
| 2 | Selesai dengan sedikit bantuan |
| 3 | Selesai mandiri, tetapi masih ragu/lambat |
| 4 | Selesai mandiri dan lancar |

Kriteria minimum: sekurang-kurangnya 80% task kritis selesai mandiri dengan nilai minimal 3.

# Stakeholder A — Super Administrator Universitas

## A1. Login dan orientasi

### US-SA-01 — Memahami menu utama

Skenario: Anda baru ditugaskan mengelola Portal Tracer Study UKDW.

Tugas:

1. Login sebagai Super Administrator.
2. Temukan Dashboard, Grafik Universitas, Grafik Prodi, Grafik Evaluasi Atasan, Data Alumni, tiga menu Kuesioner, tiga menu Master Data, dan Log Aktivitas.
3. Jelaskan fungsi setiap kelompok menu.

Berhasil jika peserta dapat membedakan konfigurasi kuesioner, master data, data alumni, dan analitik.

### US-SA-02 — Berpindah menu saat loading

1. Buka Grafik Universitas.
2. Saat loading tampil, klik Data Alumni.
3. Buka Kuesioner Universitas.
4. Kembali ke Grafik Universitas.

Berhasil jika halaman tidak terkunci oleh overlay loading.

## A2. Kuesioner Universitas

### US-SA-03 — Membuat section

Tambahkan section Pengalaman Magang dengan judul, deskripsi, dan urutan. Edit deskripsi, pindahkan urutan, lalu hapus data uji.

Berhasil jika section, perubahan, urutan, dan penghapusan konsisten setelah reload.

### US-SA-04 — Membuat pertanyaan dengan label grafik

Buat pertanyaan:

- Kode: PSI-3-05
- Label: Kontribusi inovasi alumni
- Teks lengkap: Setelah belajar dari Program Studi Sistem Informasi, saya akan menginspirasi masyarakat dengan karya kreatif dan inovatif.
- Tipe: Skala Rating 1–5
- Wajib: Ya

Buka kembali mode Edit.

Berhasil jika label tersedia pada create dan edit, sementara teks lengkap tetap tersimpan.

### US-SA-05 — Membuat opsi dan Jump To langsung saat create

Buat pertanyaan Status pekerjaan dengan opsi:

- Bekerja → F14
- Melanjutkan Pendidikan → F18
- Belum bekerja → lanjut normal

Atur target pada setiap baris saat create, simpan, edit kembali, ubah target, lalu kosongkan target.

Berhasil jika Jump To dapat diatur sejak create, diedit, dan dikosongkan.

### US-SA-06 — Menguji default tipe pertanyaan

Buat dan uji Radio, Radio + Teks, Radio + Angka Bulan, Checkbox, Checkbox + Isian, Dropdown, Rating 1–5, Text, Textarea, Number, Date, dan File.

Periksa bahwa opsi hanya muncul pada tipe yang relevan, rating memiliki lima opsi, bulan memiliki contoh isian bulan, dan skor rating dihasilkan server.

### US-SA-07 — Menggunakan Bank Pertanyaan

Cari pertanyaan berdasarkan kode, label, teks, dan asal. Sematkan ke section Universitas. Pastikan sistem membuat placement, bukan master pertanyaan duplikat.

## A3. Kuesioner Program Studi

### US-SA-08 — Membuat pertanyaan Prodi dengan label

Buat pertanyaan Sistem Informasi dengan kode, label ringkas, teks lengkap, tipe Rating 1–5, dan wajib.

Berhasil jika label terlihat pada daftar dan grafik, sedangkan teks lengkap tersedia di Data.

### US-SA-09 — Membuat opsi Prodi beserta Jump To

Tambahkan pertanyaan pilihan, isi kode, teks, dan target Jump To pada form create. Simpan, edit opsi, dan kosongkan target.

### US-SA-10 — Multi-Placement

Buat pertanyaan pada section utama. Cari section tambahan melalui dropdown searchable, pilih, hapus satu placement, lalu gunakan Kosongkan semua.

Berhasil jika satu master memiliki beberapa placement tanpa menggandakan jawaban.

### US-SA-11 — Memastikan grafik memakai label pertanyaan

Buka Grafik Prodi dengan teks pertanyaan panjang. Bandingkan judul grafik dengan label opsi.

Berhasil jika judul, tab, dan label grafik memakai label pertanyaan; label opsi hanya menjadi kategori distribusi.

## A4. Kuesioner Evaluasi Atasan

### US-SA-12 — Membuat pertanyaan evaluasi atasan

Isi kode, label ringkas, teks aspek, tipe, dan urutan. Simpan, edit, dan periksa kembali.

### US-SA-13 — Default Likert dan Jump To

Buat pertanyaan Likert, periksa opsi 1–5, pastikan skor dihasilkan server, atur Jump To bila relevan, simpan, dan edit.

### US-SA-14 — Meninjau respon atasan

Buka Riwayat Respon Atasan, cari berdasarkan alumni/NIM/perusahaan/status, buka detail, lalu bandingkan jawaban dan skor dengan master opsi.

Berhasil jika nama alumni, prodi, perusahaan, atasan, pertanyaan, jawaban, dan skor tampil tanpa tanda strip yang keliru.

## A5. Master data dan konsistensi

### US-SA-15 — Master Data Perusahaan

Tambah, edit, cari, dan coba hapus perusahaan yang masih dipakai alumni.

### US-SA-16 — Master Data Atasan

Tambah atasan yang terkait perusahaan, hubungkan dengan alumni, edit data kontak, lalu cari berdasarkan nama, perusahaan, dan alumni.

Berhasil jika card alumni, perusahaan, dan atasan terpisah serta relasinya dapat ditelusuri.

### US-SA-17 — Audit data yatim

Pilih satu alumni lalu telusuri profil, prodi, perusahaan, atasan, jawaban Universitas, Prodi, Evaluasi Atasan, dan grafiknya.

Berhasil jika tidak ada relasi tersedia yang tampil sebagai strip.

## A6. Analitik dan ekspor

### US-SA-18 — Grafik Universitas

Pilih tahun 2026, ubah urutan, klik batang, buka tabel drill-down, pindah ke Data Ringkasan, lalu kembali ke Grafik.

Berhasil jika jumlah, warna, N valid, dan tabel pembentuk dapat dipahami.

### US-SA-19 — IKU S1

Bedakan Karyawan, Founder/Co-Founder, Freelancer, Wirausaha Lainnya, Melanjutkan Pendidikan, dan status gabungan Karyawan + Studi Lanjut. Buka detail alumninya.

Berhasil jika status gabungan tidak hilang dan data detail konsisten dengan Data Alumni.

### US-SA-20 — Masa tunggu dan UMP

Periksa urutan warna masa tunggu: abu-abu tidak tersedia, merah 12 bulan atau lebih, oranye 6 sampai kurang dari 12 bulan, kuning kurang dari 6 bulan, hijau sebelum lulus. Pada Status UMP, pastikan urutan stacked bar dari abu-abu, merah, lalu hijau. Pastikan sumbu X menggunakan Jumlah dan label segmen menggunakan persentase.

### US-SA-21 — AEE

Pilih tahun lulusan 2025 dan UMP 2026. Periksa Responden Valid, Kategori Dominan, Proporsi Dominan, lalu ganti prodi.

Berhasil jika proporsi dominan terisi saat data tersedia dan kategori berasal dari data valid AEE.

### US-SA-22 — Ekspor

Pilih indikator dan filter, ekspor Excel, buka file, dan cocokkan header, style, jumlah baris, label pertanyaan, serta nilai dengan layar.

# Stakeholder B — Admin Prodi

### US-AP-01 — Mengelola section Prodi

Buat, edit, urutkan, dan hapus section pada prodi sendiri. Pastikan akun tidak dapat mengubah prodi lain.

### US-AP-02 — Mengelola pertanyaan Prodi

Buat pertanyaan dengan kode, label, teks, tipe, opsi, Jump To, wajib, dan reusable. Edit label dan teks tanpa membuat master baru.

### US-AP-03 — Memakai Bank Pertanyaan

Cari pertanyaan, lihat asalnya, dan sematkan ke section Prodi. Pastikan pertanyaan milik sumber lain tidak dapat diubah.

### US-AP-04 — Multi-Placement

Tambah dan kosongkan placement melalui dropdown searchable. Pastikan jawaban tidak terduplikasi.

### US-AP-05 — Membaca Grafik Prodi

Pilih section, periksa label pertanyaan ringkas pada grafik, lalu buka Data untuk teks lengkap dan kategori opsi.

### US-AP-06 — Menguji batas akses

Coba membuka URL, mengedit pertanyaan, menghapus placement, dan menambah opsi milik prodi lain. Semua harus ditolak.

# Stakeholder C — Alumni

### US-AL-01 — Profil Alumni

Isi identitas, akademik, status karier, perusahaan/usaha, lokasi, jabatan, bidang, divisi, skills, atasan, dan studi lanjut. Simpan, keluar, masuk kembali, lalu edit satu field.

### US-AL-02 — Kuesioner Universitas

Uji Radio, Radio + Teks, Checkbox + Isian, Dropdown, Rating, Bulan, Text, dan Number. Pilih Lainnya dan isi teks bebas. Simpan sebagian, lanjutkan, kosongkan jawaban, dan simpan ulang.

### US-AL-03 — Kuesioner Prodi dan Jump To

Pilih jawaban yang melompati pertanyaan, ganti jawaban, dan pastikan pertanyaan yang kembali relevan dapat diisi.

### US-AL-04 — Status karier

Uji Karyawan, Founder, Freelancer, Wirausaha Lainnya, Tidak Bekerja, Melanjutkan Pendidikan, Karyawan + Studi Lanjut, dan Wirausaha + Studi Lanjut.

Berhasil jika profil dan analitik memakai kategori yang sama.

### US-AL-05 — Data atasan

Pilih perusahaan, isi atasan, simpan, dan pastikan card atasan terpisah dari card alumni.

# Stakeholder D — Atasan / Evaluator

### US-EV-01 — Membuka evaluasi

Buka tautan evaluasi, cari alumni, periksa perusahaan dan identitas, lalu mulai mengisi.

### US-EV-02 — Karakteristik perusahaan

Isi bentuk usaha, skala, jumlah pegawai, jumlah alumni UKDW, dan standar gaji pertama.

### US-EV-03 — Skala Likert

Isi seluruh skala 1–5, baca label skala, ubah satu jawaban, lalu kirim. Berhasil jika angka berasal dari opsi master dan skor analitik sesuai.

### US-EV-04 — Validasi

Kirim tanpa pertanyaan wajib, kembali ke butir kosong, lengkapi, lalu kirim ulang. Pesan harus menunjuk butir yang benar.

# Stakeholder E — Pimpinan / Pembaca Laporan

### US-PM-01 — Menemukan indikator

Cari Response Rate, AEE, IKU S1, metode pembelajaran, status pekerjaan, dan evaluasi atasan. Target maksimal 30 detik per indikator.

### US-PM-02 — Menjelaskan grafik

Untuk satu grafik jelaskan panjang bar, warna, N valid, penyebut persentase, dan arti abu-abu.

### US-PM-03 — Menelusuri angka

Klik segmen, buka tabel inline, cari satu alumni, dan cocokkan dengan Data Alumni.

# Stakeholder F — Data Steward / QA

### US-QA-01 — Audit referensi antar data

Periksa sampel minimal 20 alumni: biodata, akademik, prodi, perusahaan, atasan, jawaban Universitas, jawaban Prodi, evaluasi atasan, dan opsi master.

### US-QA-02 — Audit label

Pastikan pertanyaan Universitas, Prodi, dan Atasan memiliki label ringkas; grafik memakai label; tabel detail tetap memakai teks lengkap.

### US-QA-03 — Audit Jump To

Ambil opsi yang memiliki Jump To, pastikan target berupa kode pertanyaan aktif, uji perubahan target, pengosongan target, dan jalankan kuesioner.

### US-QA-04 — Audit seeding

Setelah seeding 2026, hitung alumni, prodi, perusahaan, atasan, jawaban, dan evaluasi. Telusuri sampel acak dan pastikan data 2025 tidak ikut saat filter 2026.

### US-QA-05 — Audit pagination dan filter

Uji Data Alumni, Data Atasan, Master Perusahaan, Riwayat Evaluasi Atasan, detail grafik, dan Data Ringkasan. Periksa tombol, nomor halaman, search, filter, dan persistensi query.

# Matriks cakupan fitur

| Fitur | Superadmin | Admin Prodi | Alumni | Atasan | Pimpinan | QA |
| --- | :---: | :---: | :---: | :---: | :---: | :---: |
| Login dan navigasi | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Profil alumni | ✓ | - | ✓ | - | - | ✓ |
| Kuesioner Universitas | ✓ | - | ✓ | - | - | ✓ |
| Kuesioner Prodi | ✓ | ✓ | ✓ | - | ✓ | ✓ |
| Evaluasi Atasan | ✓ | - | - | ✓ | ✓ | ✓ |
| Label dan Jump To | ✓ | ✓ | - | - | - | ✓ |
| Master perusahaan | ✓ | terbatas | - | - | - | ✓ |
| Master atasan | ✓ | terbatas | ✓ | - | - | ✓ |
| Grafik dan drill-down | ✓ | ✓ | - | - | ✓ | ✓ |
| Ekspor | ✓ | sesuai kewenangan | - | - | ✓ | ✓ |
| Seeding dan konsistensi | - | - | - | - | - | ✓ |

# Form pencatatan penguji

| ID Task | Stakeholder | Berhasil? | Durasi | Nilai 0–4 | Bantuan | Salah klik | Catatan peserta | Severity |
| --- | --- | --- | --- | ---: | --- | ---: | --- | --- |
|  |  |  |  |  |  |  |  |  |

## Severity temuan

- S1 — Blocker: task tidak dapat diselesaikan atau data salah/berisiko hilang.
- S2 — Major: task dapat diselesaikan dengan bantuan atau terjadi salah data.
- S3 — Moderate: task dapat diselesaikan tetapi membingungkan/lambat.
- S4 — Minor: masalah kosmetik atau istilah yang tidak menghambat.

## Skenario akhir end-to-end

1. Alumni memperbarui profil.
2. Alumni memilih Karyawan + Studi Lanjut.
3. Alumni mengisi Kuesioner Universitas dan Prodi.
4. Alumni mengisi perusahaan dan atasan.
5. Atasan mengisi evaluasi.
6. Superadmin membuka grafik IKU S1 dan evaluasi atasan.
7. Pimpinan menelusuri satu angka sampai ke alumni.
8. QA memeriksa relasi dari sumber sampai grafik.

Kriteria lulus: seluruh data dapat ditelusuri dari satu alumni ke profil, perusahaan, atasan, tiga jenis jawaban, evaluasi atasan, tabel analitik, dan ekspor tanpa data yatim atau label berubah.

