# Panduan Analitik Universitas dan Program Studi

Dokumen ini menjelaskan cara data kuesioner berubah menjadi grafik pada halaman Superadmin:

- `/superadmin/analitik` untuk analitik tingkat Universitas.
- `/superadmin/analitik-prodi` untuk analitik berdasarkan bagian kuesioner Program Studi.
- `/superadmin/analitik-atasan` untuk analitik evaluasi pengguna lulusan oleh atasan.

> Catatan: backend tidak mengirim gambar grafik. Backend menghitung data dan mengirim payload JSON. Vue dan ECharts membaca payload tersebut lalu menggambar visualisasinya.

Semua angka, label pilihan, dan rincian respon pada grafik berasal dari payload hasil query database. Nilai contoh hanya boleh ditambahkan melalui seeder demo. Jika data kosong, komponen menampilkan tanda `-` atau empty state, bukan membuat baris sintetis.

## 1. Gambaran besar alur data

```text
Browser membuka halaman
        |
        v
Route Laravel (routes/web.php)
        |
        v
Controller analitik
        |
        v
Service menghitung data dan rumus
        |
        v
Payload JSON: summary, visualizations, records, methodology
        |
        v
Komponen Vue memilih grafik
        |
        v
AnalyticsEChart merender batang/garis/donat/radar/tabel
```

### Analitik Universitas

```text
/superadmin/analitik
  -> TracerAnalyticsController@index()
  -> TracerAnalyticsService::buildUniversityChart()
  -> GrafikUniv.vue
  -> AnalyticsChartsPanel.vue
  -> categories/01_ResponseRate.vue ... categories/18_WorkLocation.vue
```

Filter indikator dikirim ke endpoint JSON berikut sehingga halaman tidak perlu dimuat ulang:

```text
GET /superadmin/analitik/data?grafik=response_rate&tahun=2025
```

`AnalyticsChartsPanel.vue` memakai `chart.selected_chart` sebagai kunci. Contoh:

```js
selected_chart: 'response_rate'
```

Kunci tersebut dicari pada registry komponen:

```js
const categoryComponents = {
    response_rate: ResponseRate,
    learning_methods: LearningMethods,
    // ... indikator lain
};
```

### Analitik Program Studi

```text
/superadmin/analitik-prodi
  -> ProdiAnalyticsController@index()
  -> ProdiAnalyticsService::buildProdiChart()
  -> GrafikProdi.vue
  -> ProdiAnalyticsChartsPanel.vue
  -> categories/01_...vue ... categories/09_...vue
```

Filter bagian dikirim ke endpoint:

```text
GET /superadmin/analitik-prodi/data?prodi_id=2&section_id=10&tahun=ALL
```

Pada Prodi, komponen dipilih dari `section.order`, bukan dari nama grafik:

```js
const categoryComponents = {
    1: InterestAndContribution,
    2: LearningExperience,
    // ...
    9: HopesAndFeedback,
};
```

### Analitik Evaluasi Atasan

```text
/superadmin/analitik-atasan
  -> AtasanAnalyticsController@index()
  -> AtasanAnalyticsService::build()
  -> GrafikAtasan.vue
  -> AnalyticsEChart.vue
```

Halaman ini membaca `evaluasi_atasan`, `respon_evaluasi_atasan`,
`pertanyaan_evaluasi_atasan`, `biodata`, `data_akademik`, dan `prodi`. Tiga
visualisasi dibentuk dari payload yang sama:

1. Rata-rata aspek kinerja: hanya pertanyaan bertipe `likert_5`; opsi pertama
   pada master bernilai 5 dan opsi terakhir bernilai 1.
2. Tingkat kesiapan alumni: distribusi frekuensi dari opsi pertanyaan
   `tingkat_kesiapan_kerja`, termasuk opsi tanpa jawaban agar instrumen tetap
   terlihat lengkap.
3. Perbandingan program studi: rata-rata skor Likert per prodi dari evaluasi
   yang sudah dikirim.

Filter tahun dan program studi menggunakan endpoint berikut:

```text
GET /superadmin/analitik-atasan/data?tahun=2026&prodi_id=2
```

Tidak ada nama aspek, pilihan jawaban, jumlah responden, atau nilai contoh
yang ditulis di Vue. Jika database kosong, halaman menampilkan empty state.
Kartu data respon di bawah grafik menjadi sumber penelusuran setiap agregasi.

### Highlight sidebar

Semua wrapper sidebar role memakai `AdminSidebar.vue`. Komponen tersebut
menormalkan URL aktif sebelum membandingkan prefix, sehingga menu tetap
ter-highlight pada halaman dengan query string filter, hash, atau trailing
slash. Halaman yang memiliki konteks khusus dapat mengirim `active-menu`,
sedangkan halaman lain otomatis mengikuti path Inertia saat ini.

## 2. Bentuk payload yang perlu dipahami

### Payload Universitas

Service Universitas mengirim data seperti berikut:

```js
{
    selected_chart: 'response_rate',
    chart: {
        key: 'response_rate',
        type: 'response_rate',
        title: 'Response Rate',
        summary: { /* angka ringkasan */ },
        series: [ /* data per prodi */ ],
        records: [ /* data alumni untuk drill-down */ ],
        methodology: { /* rumus, sumber tabel, denominator */ },
    },
}
```

### Payload Prodi

Service Prodi mengirim satu bagian aktif. Jika bagian tersebut mempunyai tiga pertanyaan pilihan, `visualizations` berisi tiga item:

```js
{
    chart: {
        type: 'choice',
        title: 'Formulir Peminatan & Kontribusi Alumni',
        visualizations: [
            {
                key: 'PSI-1-01',
                title: 'Konsentrasi yang diambil',
                categories: ['Sistem Informasi Enterprise', 'Data Analytics'],
                values: [5, 3],
                stat: {
                    valid_count: 8,
                    categories: [
                        { label: 'Sistem Informasi Enterprise', count: 5, percentage: 62.5 },
                        { label: 'Data Analytics', count: 3, percentage: 37.5 },
                    ],
                },
            },
            // item kedua: kesediaan berkontribusi
            // item ketiga: bentuk kontribusi
        ],
    },
}
```

Di `01_InterestAndContribution.vue`, setiap item menjadi satu tab. Saat tab diklik, `activeItem` berubah. Fungsi `buildChoiceOption(activeItem)` lalu menggunakan `categories` sebagai label sumbu Y dan `values` sebagai panjang batang.

## 3. Struktur file frontend

```text
resources/js/components/analytics/
├── AnalyticsChartsPanel.vue       # Hub indikator Universitas
├── AnalyticsEChart.vue             # Pembungkus ECharts
├── AnalyticsTabbedContainer.vue    # Tab grafik/data dan drill-down
├── analyticsChartOptions.js        # Opsi dan kamus grafik Universitas
└── categories/
    ├── 01_ResponseRate.vue
    ├── 02_LearningMethods.vue
    └── ... sampai 18_WorkLocation.vue

resources/js/components/prodi-analytics/
├── ProdiAnalyticsChartsPanel.vue   # Hub 9 bagian Prodi
├── ProdiPivotTable.vue              # Tabulasi data pertanyaan
├── prodiChartOptions.js             # Fungsi opsi Likert dan pilihan
└── categories/
    ├── 01_InterestAndContribution.vue
    ├── 02_LearningExperience.vue
    └── ... sampai 09_HopesAndFeedback.vue
```

Pola edit paling mudah:

1. Cari kategori yang ingin diubah.
2. Lihat `items` yang diterima dari backend.
3. Ubah template atau opsi grafik di file kategori tersebut.
4. Jika perubahan berlaku untuk semua grafik sejenis, ubah `prodiChartOptions.js` atau `analyticsChartOptions.js`.

## 4. Rumus Grafik Universitas

Rumus resmi juga dikirim pada `chart.methodology`. Daftar berikut adalah versi ringkas untuk memahami perhitungannya.

### 01. Response Rate

```text
Response Rate (%) = (jumlah alumni responden valid / jumlah populasi kohort) × 100%
Belum Mengisi (%) = 100% - Response Rate (%)
Gap Target = Response Rate (%) - Target Response Rate (%)
```

Responden valid adalah alumni yang memenuhi kriteria jawaban wajib kuesioner Universitas. Populasi berasal dari alumni pada tahun lulus dan prodi yang dipilih.

### 02. Penekanan Metode Pembelajaran

```text
Rata-rata_k = jumlah skor valid metode k / jumlah skor valid metode k
Skor INT_k = ROUND(Rata-rata_k)
Proporsi skala s = (jumlah alumni yang memilih skala s / jumlah jawaban valid metode k) × 100%
```

Skala: 1 Tidak Sama Sekali, 2 Kurang, 3 Cukup Besar, 4 Besar, 5 Sangat Besar.

### 03. Waktu Mulai Mencari Pekerjaan

```text
Proporsi fase dan rentang = jumlah pada fase/rentang / total jawaban valid F3 × 100%
Proporsi fase = jumlah seluruh rentang pada fase / total jawaban valid F3 × 100%
```

Fase dibedakan menjadi sebelum lulus, setelah lulus, dan tidak mencari kerja. Rentang waktunya kurang dari 2 bulan, 2–6 bulan, dan lebih dari 6 bulan.

### 04. Metode Pencarian Pekerjaan

Ini adalah pertanyaan pilihan jamak:

```text
Frekuensi metode m = jumlah pemilihan metode m
Proporsi metode m = frekuensi metode m / total seluruh pilihan valid × 100%
```

Karena satu alumni boleh memilih beberapa metode, total persentase dapat lebih dari 100%.

### 05. Waktu Tunggu Mendapatkan Pekerjaan

```text
Waktu tunggu Wi = nilai F502 atau F506 yang berlaku
Q1 = P25(W)
Median = P50(W)
Q3 = P75(W)
IQR = Q3 - Q1
```

Grafik per prodi memakai kuartil dan median agar distribusi tidak disimpulkan hanya dari rata-rata.

### 06. Pipeline Lamaran

Tahapnya adalah F6 Dilamar, F7 Merespon, dan F7A Wawancara:

```text
Median tahap t = P50(nilai valid tahap t)
Rasio partisipasi tahap t = jumlah responden tahap t / jumlah responden F6 × 100%
```

### 07. Status Pekerjaan

```text
Proporsi status s = jumlah alumni pada status s / total jawaban valid F8 × 100%
IKU 1 Awal = (Bekerja + Wiraswasta + Studi Lanjut) / total valid F8 × 100%
```

Selain donat Universitas, data dapat dikelompokkan per prodi dan ditampilkan sebagai stacked bar atau Sankey.

### 08. Keaktifan Mencari Pekerjaan

```text
Proporsi aktivitas k = jumlah alumni dengan jawaban F10 = k / total valid F10 × 100%
```

### 09. Jenis Tempat Kerja

```text
Proporsi jenis instansi j = jumlah alumni pada jenis j / total valid F11 × 100%
Proporsi jenis j pada prodi p = jumlah alumni prodi p pada jenis j / total alumni prodi p × 100%
```

### 10. Studi Lanjut

```text
Proporsi jenjang d = jumlah alumni pada jenjang d / total alumni studi lanjut × 100%
Proporsi sumber biaya b = jumlah alumni dengan biaya b / total alumni studi lanjut × 100%
Proporsi perguruan tinggi u pada jenjang d = jumlah d,u / jumlah jenjang d × 100%
```

### 11. Gaji dibandingkan UMP

```text
Ambang provinsi = ROUND(1,2 × UMP provinsi, 2)
Memenuhi = 1 jika gaji alumni >= ambang provinsi
Persentase memenuhi = jumlah memenuhi / jumlah data yang dapat dibandingkan × 100%
Q1, Median, Q3 = P25, P50, P75 gaji valid
```

Data yang tidak mempunyai gaji, provinsi perusahaan, atau UMP tahun terpilih tidak masuk pembanding.

### 12. Hubungan Bidang Studi dengan Pekerjaan

```text
Proporsi kategori k = jumlah jawaban kategori k / total valid F14 × 100%
Horizontal Match prodi p = (Sangat Erat + Erat) pada prodi p / total valid prodi p × 100%
```

### 13. Keselarasan Tingkat Pendidikan per Prodi

```text
Vertical Match prodi p = jumlah jawaban Tingkat yang Sama pada prodi p / total valid F15 prodi p × 100%
Proporsi kualifikasi k = jumlah kategori k pada prodi p / total valid prodi p × 100%
```

### 14. Keselarasan Tingkat Pendidikan Universitas

```text
Proporsi kategori k Universitas = jumlah kategori k / total valid F15 Universitas × 100%
Proporsi kategori k pada prodi p = jumlah kategori k prodi p / total valid prodi p × 100%
```

### 15. Alasan Ketidaksesuaian Pekerjaan

```text
Frekuensi alasan r = jumlah alumni yang memilih alasan r
Proporsi alasan r = frekuensi alasan r / total responden valid × 100%
```

Ini pilihan jamak, sehingga total persentase boleh lebih dari 100%.

### 16. Gap Kompetensi

Untuk dimensi kompetensi d:

```text
Saat Lulus A_d = ROUND(Rata-rata(F17a_d))
Diperlukan Kerja B_d = ROUND(Rata-rata(F17b_d))
Gap Visual = A_d - B_d
Gap Kebutuhan = B_d - A_d
```

Nilai A, B, dan gap dibulatkan ke bilangan bulat terdekat karena sumbernya adalah skala Likert 1-5.

Gap positif berarti penguasaan saat lulus lebih tinggi daripada kebutuhan. Gap negatif berarti masih ada kekurangan.

### 17. Kelengkapan Profil

```text
Profil lengkap jika nama, NIM, nomor telepon, dan email terisi
Kelengkapan (%) = jumlah profil lengkap / total populasi × 100%
Belum lengkap (%) = 100% - Kelengkapan (%)
```

### 18. Wilayah Kerja

```text
Jumlah provinsi = COUNT(alumni yang bekerja pada provinsi tersebut)
Proporsi provinsi = jumlah provinsi / total alumni dengan wilayah kerja valid × 100%
```

Data wilayah diambil dari relasi perusahaan alumni. Negara, provinsi, dan kabupaten/kota dipisahkan agar drill-down tidak ambigu.

## 5. Rumus Grafik Program Studi

### Bagian 01: Formulir Peminatan dan Kontribusi

Tiga pertanyaan dibaca sebagai tiga `items`, lalu ditampilkan sebagai tiga tab.

```text
Frekuensi pilihan p = jumlah alumni yang memilih p
Proporsi pilihan p = frekuensi p / jumlah jawaban valid pertanyaan × 100%
```

Untuk pertanyaan pilihan jamak, satu alumni dapat dihitung pada lebih dari satu pilihan. Karena itu total persentase bisa melebihi 100%.

### Bagian 02–08: Pertanyaan Skala Likert

Setiap butir memiliki skor 1–5:

```text
Rata-rata butir = jumlah skor valid / jumlah jawaban valid
Skor tampilan = ROUND(Rata-rata butir)
Median tampilan = ROUND(nilai tengah skor valid)
```

Skor tetap ditampilkan sebagai bilangan bulat supaya konsisten dengan skala kuesioner.

Bagian yang memakai pola ini:

1. Pengalaman Belajar Selama Studi.
2. Nilai-Nilai Program Studi.
3. Fasilitas yang Tersedia.
4. Dosen dan Tenaga Kependidikan.
5. Mata Kuliah yang Ditawarkan.
6. Kepuasan Total.
7. Rekomendasi Program Studi.

### Bagian 09: Harapan dan Masukan

Jawaban teks tidak dihitung sebagai rata-rata. Backend mengirim isi jawaban, identitas alumni, kode pertanyaan, dan tahun lulus. Frontend menampilkannya sebagai kartu teks.

## 6. Cara menelusuri grafik Bagian 01 saat live coding

Jika ingin mengubah grafik Konsentrasi:

1. Buka `ProdiAnalyticsService.php`.
2. Cari `formatChoiceQuestion()` untuk melihat bagaimana `categories`, `values`, dan `stat` dibuat.
3. Buka `01_InterestAndContribution.vue`.
4. Periksa `activeItem`. Itulah satu pertanyaan yang sedang aktif.
5. Periksa `buildChoiceOption(activeItem)`. Fungsi itu membuat `xAxis`, `yAxis`, `series`, warna, label, dan tooltip.
6. Periksa `AnalyticsEChart`. Komponen tersebut menerima opsi ECharts dan menampilkan grafik.

Jika ingin mengubah warna, label sumbu, atau tooltip semua grafik pilihan, ubah `prodiChartOptions.js`. Jika ingin mengubah tab Bagian 01 saja, ubah langsung `01_InterestAndContribution.vue`.

## 7. Perbedaan `summary`, `visualizations`, dan `records`

- `summary`: angka ringkasan untuk KPI dan narasi, misalnya jumlah responden dan rata-rata.
- `visualizations`: data yang dipakai menggambar grafik, misalnya kategori, nilai batang, dan statistik per pertanyaan.
- `records`: baris alumni yang dipakai untuk drill-down setelah grafik diklik.
- `pivot`: tabulasi alternatif untuk melihat frekuensi, persentase, rata-rata, dan median.
- `methodology`: rumus, penyebut, sumber tabel, view, dan alasan pemilihan grafik.

## 8. Cara membaca masalah grafik

1. Grafik kosong: cek `has_data`, `data_availability`, dan jumlah `records`.
2. Label salah: cek `question_text`, `option_text`, atau `title` pada payload.
3. Angka salah: cek fungsi formatter di service dan `methodology.denominator`.
4. Grafik bergeser atau bertumpuk: cek `grid.containLabel`, `chartHeight`, dan `min-w` pada kategori.
5. Tab salah: cek `sectionOrder` pada `GrafikProdi.vue` dan `categoryComponents` pada `ProdiAnalyticsChartsPanel.vue`.
6. Drill-down salah: cek payload event `select` dan pencocokan `record.kode` di `GrafikProdi.vue`.

## 9. File backend penting

| File | Tanggung jawab |
| --- | --- |
| `routes/web.php` | Mendefinisikan route halaman dan endpoint JSON. |
| `TracerAnalyticsController.php` | Menerima filter Grafik Universitas dan memanggil service. |
| `ProdiAnalyticsController.php` | Menerima filter Grafik Prodi dan memanggil service. |
| `TracerAnalyticsService.php` | Menghitung 18 indikator Universitas dan metodologinya. |
| `ProdiAnalyticsService.php` | Membaca section/pertanyaan Prodi dan menghitung visualisasi dinamis. |
| `AnalyticsEChart.vue` | Membungkus instance ECharts di frontend. |
