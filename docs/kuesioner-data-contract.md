# Kontrak Data Kuesioner dan Analitik

Dokumen ini menjelaskan bagaimana pertanyaan Universitas, Program Studi, dan
Atasan memakai satu bank pertanyaan serta satu tabel jawaban tanpa kehilangan
tipe input dan aturan perhitungannya.

## 1. Satu bank pertanyaan, banyak penempatan

`kuesioner_pertanyaan` adalah master butir pertanyaan. Pertanyaan dapat dibuat
oleh Universitas, Prodi, atau pengelola instrumen lain, lalu ditempatkan pada
satu atau beberapa section melalui `kuesioner_section_pertanyaan`.

Penempatan menentukan konteks penggunaan, urutan, kewajiban, dan status tampil
lokal. Penempatan tidak mengubah tipe pertanyaan master.

```text
kuesioner_pertanyaan
        |
        +-- kuesioner_section_pertanyaan -- section Universitas
        +-- kuesioner_section_pertanyaan -- section Prodi A
        +-- kuesioner_section_pertanyaan -- section Prodi B
        +-- kuesioner_section_pertanyaan -- section Atasan
```

Jika satu pertanyaan sudah mempunyai jawaban, tipe master tidak boleh diubah
sembarangan. Bila kebutuhan input atau analisisnya berbeda, buat pertanyaan
baru dengan kode baru agar jawaban lama tetap dapat dibaca.

## 2. Tipe input pertanyaan

Field `kuesioner_pertanyaan.tipe` menentukan kontrol input, bentuk validasi,
dan cara normalisasi jawaban.

| Tipe | Contoh penggunaan | Penyimpanan utama | Bentuk analisis |
| --- | --- | --- | --- |
| `text` | Nama perusahaan atau jawaban singkat | `jawaban_teks` | Jumlah jawaban valid atau daftar teks |
| `textarea` | Alasan dan pengalaman | `jawaban_teks` | Daftar teks, kata kunci, atau word cloud |
| `number` | Jumlah pegawai atau gaji | `jawaban_teks` sebagai representasi angka | Rata-rata, median, jumlah, rentang, atau distribusi setelah casting numerik |
| `single_choice` / `radio` | Status pekerjaan | `opsi_id`, `kode_opsi`, `jawaban_teks` | Frekuensi dan persentase per opsi |
| `dropdown` / `searchable_select` | Pilihan satu dari daftar panjang | `opsi_id`, `kode_opsi`, `jawaban_teks` | Frekuensi dan persentase per opsi |
| `multiple_choice` / `checkbox` | Skills atau alasan jamak | `jawaban_json` dan representasi `jawaban_teks` | Frekuensi setiap opsi; satu responden dapat dihitung pada beberapa opsi |
| `likert_5` / `rating_5` | Penilaian kemampuan atau kepuasan | `opsi_id`, `kode_opsi`, `jawaban_teks` | Distribusi skor, rata-rata, median, dan persentase tingkat tinggi |
| `multiple_number` | Rincian nominal beberapa komponen | `jawaban_json` dan `jawaban_teks` | Agregasi per komponen dan total |
| `matrix` / `matrix_dual` | Penilaian berbentuk matriks | `jawaban_json` | Agregasi per baris, kolom, atau pasangan dimensi |
| `header` | Judul/pemisah section | Tidak ada jawaban | Tidak ikut denominator |

`jawaban_teks` bertipe teks agar satu tabel dapat menyimpan teks, angka, label
pilihan, dan representasi baca. Ini tidak berarti semua input bebas dari
validasi. Tipe `number` tetap harus menerima nilai numerik yang dapat
dinormalisasi sebelum masuk ke analitik.

## 2a. Field terstruktur untuk IKU S1

Status profil `kategori_pekerjaan` menjadi fallback dari F8 dan harus memakai
kategori kanonik: pekerja/karyawan, wiraswasta, melanjutkan pendidikan, atau
tidak bekerja. F504 membedakan pekerjaan sebelum lulus; F502 menyimpan bulan
sebelum lulus dan F506 bulan setelah lulus. Posisi founder/co-founder dan
freelancer dibaca dari field terstruktur `posisi_wiraswasta`, bukan dari teks
`experience`. Nilai freelancer yang didukung adalah `Freelancer`, `Freelancer /
Konsultan Mandiri`, `Pekerja Lepas`, dan variasi label yang setara. Studi lanjut
memakai F18d sebagai tanggal mulai.

`v_iku_s1_lulusan` memproyeksikan field tersebut, lalu
`IkuS1AnalyticsService` menghitung bobot masa tunggu, gaji terhadap 1,2× UMP,
dan studi lanjut kurang dari satu tahun. Nilai placeholder seperti angka `4`
tidak boleh dipakai untuk tanggal, jenjang, atau masa tunggu; angka `4` hanya
valid jika memang merupakan skor Likert pada pertanyaan berskala.

## 2b. Populasi AEE

`data_akademik` adalah sumber seluruh mahasiswa masuk, bukan hanya baris yang
memiliki biodata alumni. Semua program pada baseline ini berjenjang S1 dan
periode masuk reguler disimpan sebagai semester ganjil. `v_iku_s1_aee`
mengeluarkan Drop Out, Pindah, dan Cuti dari denominator, tetapi tetap
mempertahankan barisnya untuk audit populasi. Dalam payload grafik AEE,
mahasiswa `Lulus` yang melewati masa standar diklasifikasikan sebagai terlambat;
mahasiswa dalam denominator yang belum `Lulus` diklasifikasikan sebagai status
kelulusan belum teridentifikasi.

## 3. Cara grafik menghitung

Service analitik membaca `kuesioner_pertanyaan.tipe` dan memilih formatter yang
sesuai. Frontend hanya menerima payload hasil agregasi; Vue tidak menebak tipe
data dari redaksi pertanyaan.

```text
pertanyaan + tipe + opsi
        |
        v
jawaban_teks / jawaban_json
        |
        v
formatter analitik sesuai tipe
        |
        v
summary + visualizations + records + methodology
```

Rumus dasar:

- Pilihan tunggal: `frekuensi opsi = jumlah responden yang memilih opsi`.
- Pilihan ganda: array jawaban dipecah, kemudian setiap opsi dihitung. Satu
  responden boleh menyumbang ke beberapa opsi.
- Likert: jawaban valid dipetakan ke skor 1–5, kemudian dihitung rata-rata,
  median, dan distribusi skornya.
- Angka: nilai dinormalisasi dan di-cast ke numerik, lalu dihitung memakai
  agregasi yang sesuai dengan makna pertanyaannya.
- Teks: tidak dipaksakan menjadi angka. Jawaban ditampilkan sebagai detail,
  daftar respons, atau analisis teks.

Denominator selalu ditulis dalam `methodology` payload. Jawaban kosong, opsi
yang tidak sah, dan nilai numerik yang tidak dapat dinormalisasi tidak masuk
perhitungan valid.

## 4. Angka membutuhkan aturan analitik

Tipe input `number` hanya menjawab “jawaban berupa angka”. Ia belum menjawab
rumus yang harus dipakai. Karena itu, setiap pertanyaan angka perlu memiliki
aturan analitik yang jelas, misalnya:

| Pertanyaan | Unit | Agregasi yang sesuai |
| --- | --- | --- |
| Jumlah pegawai | orang | Median/rata-rata dan rentang |
| Jumlah alumni UKDW | orang | Jumlah, median, atau rata-rata |
| Standar gaji pertama | rupiah/bulan | Median, kuartil, dan rata-rata |
| Waktu tunggu kerja | bulan | Median, kuartil, dan distribusi rentang |
| Jumlah perusahaan yang dilamar | perusahaan | Median dan distribusi |

Pertanyaan dinamis tidak boleh mengandalkan nama pertanyaan untuk menentukan
rumus. Kontrak lanjutan yang ideal adalah menyimpan metadata analitik seperti:

```text
input_type: number
analytic_type: average | median | sum | distribution
unit: orang | rupiah | bulan | persen
```

Untuk indikator resmi yang sudah mempunyai kode seperti F3, F6, F502, F505,
atau F506, aturan analitik dapat tetap berada di service indikator karena
rumusnya memang bagian dari instrumen resmi.

## 5. Status implementasi saat ini

- Penyimpanan terpadu sudah memakai `kuesioner_jawaban`.
- Pilihan tunggal/ganda, Likert, dan jawaban teks sudah mempunyai alur
  normalisasi dan formatter analitik.
- Beberapa indikator Universitas memakai service khusus berdasarkan kode
  pertanyaan resmi.
- Formatter analitik Prodi saat ini memiliki jalur khusus untuk `rating_5` dan
  `text`/`textarea`; tipe lain masuk jalur pilihan. Pertanyaan `number` dinamis
  karena itu perlu formatter numerik khusus sebelum dipakai sebagai grafik
  numerik umum.

Catatan ini penting agar pertanyaan angka tidak dianggap sudah otomatis
menjadi rata-rata hanya karena field `tipe` bernilai `number`.

## 6. Sumber kode utama

| File | Tanggung jawab |
| --- | --- |
| `app/Models/KuesionerPertanyaan.php` | Master pertanyaan dan relasi opsi/placement |
| `app/Models/KuesionerJawaban.php` | Penyimpanan jawaban terpadu |
| `app/Services/Kuesioner/QuestionDefaultOptionsService.php` | Instruksi dan opsi awal berdasarkan tipe |
| `app/Http/Controllers/Alumni/Kuesioner/SimpanJawabanController.php` | Normalisasi payload jawaban sebelum disimpan |
| `app/Services/Analytics/ProdiAnalyticsService.php` | Agregasi analitik dinamis per section Prodi |
| `app/Services/Analytics/TracerAnalyticsService.php` | Agregasi indikator Universitas berbasis kode instrumen |
| `resources/js/Pages/Shared/Kuesioner/ProdiKuesionerIndex.vue` | Pengelolaan master, placement, dan tipe input |
| `docs/panduan-analitik.md` | Rumus dan cara membaca payload grafik |
| `docs/ARCHITECTURE.md` | Kontrak arsitektur dan sumber data kanonik |
