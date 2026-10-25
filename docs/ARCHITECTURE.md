# Arsitektur Antarmuka Admin

Dokumen ini menjelaskan struktur antarmuka dan batas akses untuk Superadmin, Admin Fakultas, Admin Program Studi, dan Admin Biro III. Tujuannya adalah menjaga halaman dengan fungsi yang sama tetap memakai pola visual dan komponen yang sama, sementara cakupan datanya ditentukan oleh role pengguna.

## Prinsip

- Satu fungsi inti memakai satu halaman utama bila struktur dan perilakunya sama.
- Role hanya menentukan sidebar, judul, route dasar, filter yang tersedia, dan batas data.
- Pembatasan data wajib berada di controller dan query backend; filter di browser bukan mekanisme otorisasi.
- Komponen bersama berada di `resources/js/components` dan digunakan lewat wrapper pada direktori role yang sudah ada.

### Kontrak data profil setelah audit 23 Oktober 2026

- `biodata.alamat_saat_ini` adalah satu-satunya field alamat domisili alumni. Alias runtime `alamat` tidak lagi digunakan; alamat perusahaan tetap berada di tabel `perusahaan`.
- `biodata.skills` dan `biodata.experience` adalah field kanonik. Alias lama `expert` dan `minat` dihapus dari model, frontend, sinkronisasi LinkedIn, exporter, dan seeder.
- NIK, No KK, dan NPWP tidak lagi menjadi bagian dari data aplikasi. Kolom tersebut tidak dibuat pada migration schema aktif.
- Tabel `data_orang_tua` dan foreign key `biodata.orang_tua_id` tidak ada pada schema aktif karena data orang tua tidak digunakan dalam profil maupun ekspor Excel.
- Data pembimbing/penguji dihapus dari `yudisium`; judul tugas akhir, tanggal lulus, status lulus, dan publikasi yang masih digunakan tetap dipertahankan.
- NISN, BPJS, email mahasiswa, dan negara belum dihapus karena masih menjadi bagian dari sumber akademik/integrasi yang tersedia; visibilitasnya tetap dikendalikan oleh payload halaman.

### Kontrak migration schema

Schema runtime untuk instalasi baru disusun dalam `database/migrations` dengan satu migration untuk setiap tabel atau view aktif. Migration historis yang berisi perubahan bertahap, `add`, `refresh`, dan backfill telah dipadatkan ke definisi pembentuk tabel/view final. `php artisan migrate` menjalankan migration tersebut, sedangkan `php artisan db:seed` mengisi data master dan simulasi.

### Kontrak metrik Dashboard Superadmin

DashboardController mengambil metrik ringkasan dari satu agregasi v_alumni_audit_rekap, bukan menghitung status per alumni di dalam loop. Kontrak dashboard memisahkan tiga hal:

1. **Response rate**: jumlah alumni dengan minimal satu jawaban wajib universitas dibagi seluruh alumni lulusan. Ini mengukur partisipasi awal, bukan kelengkapan.
2. **Kelengkapan profil**: jumlah baris dengan is_profile_complete = 1.
3. **Kelengkapan kuesioner universitas**: jumlah baris dengan is_complete_univ = 1; pertanyaan opsional dan pertanyaan wajib yang dilewati oleh alur tidak menjadi kekurangan pada sumber statusnya.

Kartu gabungan hanya menganggap alumni lengkap apabila profil dan kuesioner universitas sama-sama lengkap. Kuesioner prodi tetap tersedia di modul audit, tetapi tidak memengaruhi panel kelengkapan Dashboard Superadmin pada tahap ini.

Klasifikasi response rate bersifat eksplisit dan dapat diubah di controller: merah untuk <50%, kuning untuk 50–74,9%, dan hijau untuk >=75%. Frontend menerima kode status dari backend dan hanya memetakan kode tersebut ke kelas warna statis agar aman terhadap proses build Tailwind.

## Struktur Frontend

```text
resources/js/
├── components/
│   ├── AdminSidebar.vue
│   └── AdminPageHeader.vue
└── Pages/
    ├── SuperAdmin/
    │   └── Components/Sidebar.vue
    ├── AdminFakultas/
    │   └── Components/Sidebar.vue
    ├── AdminProdi/
    │   └── Components/Sidebar.vue
    └── AdminBiroTiga/
        └── Components/Sidebar.vue
```

### Komponen bersama

| File | Tanggung jawab |
| --- | --- |
| `resources/js/components/AdminSidebar.vue` | Sumber konfigurasi navigasi seluruh role admin: identitas role, kelompok menu, tautan, tombol keluar, serta perilaku sidebar responsif. |
| `resources/js/components/AdminPageHeader.vue` | Header ringkas untuk dashboard admin dengan judul, konteks role, dan navigasi pendukung. |

### Wrapper sidebar per role

Wrapper mempertahankan jalur impor lama halaman-halaman admin. Wrapper tidak menyimpan markup sidebar sendiri; semuanya meneruskan props ke `AdminSidebar.vue`.

| Role | Wrapper | Props konteks |
| --- | --- | --- |
| Superadmin | `resources/js/Pages/SuperAdmin/Components/Sidebar.vue` | `user` |
| Admin Fakultas | `resources/js/Pages/AdminFakultas/Components/Sidebar.vue` | `user`, `fakultas` |
| Admin Prodi | `resources/js/Pages/AdminProdi/Components/Sidebar.vue` | `user`, `prodi` |
| Admin Biro III | `resources/js/Pages/AdminBiroTiga/Components/Sidebar.vue` | `user` |

## Halaman Bersama

### Master Data Perusahaan

Satu tampilan utama digunakan oleh semua role admin:

| File tampilan | Pengguna | Route dasar | Pembatasan data |
| --- | --- | --- | --- |
| `resources/js/Pages/SuperAdmin/Perusahaan/Index.vue` | Superadmin | `/superadmin/perusahaan` | Seluruh universitas |
| `resources/js/Pages/SuperAdmin/Perusahaan/Index.vue` | Admin Fakultas | `/fakultas/perusahaan` | Program studi yang berada dalam fakultas pengguna |
| `resources/js/Pages/SuperAdmin/Perusahaan/Index.vue` | Admin Prodi | `/prodi/perusahaan` | Data perusahaan yang terkait dengan program studi pengguna |

Halaman menerima props berikut untuk menentukan konteks tanpa menggandakan tampilan:

- `role`: `superadmin`, `admin_fakultas`, atau `admin_prodi`.
- `basePath`: route untuk filter, verifikasi, penolakan, pembaruan, dan konsolidasi.
- `dashboardPath`: tautan breadcrumb kembali ke dashboard role terkait.
- `fakultas` atau `prodi`: identitas unit pengguna.
- `canCreate`: hanya `true` untuk Superadmin; tombol tambah perusahaan tidak muncul pada Fakultas dan Prodi.

Filter dibedakan sesuai wewenang:

- Superadmin dapat memilih program studi dan lingkup pengajuan.
- Admin Fakultas dapat memilih program studi di fakultasnya.
- Admin Prodi tidak dapat berpindah program studi.

## Backend dan Route

| Role | Controller | Halaman Inertia | Route indeks |
| --- | --- | --- | --- |
| Superadmin | `app/Http/Controllers/SuperAdmin/Perusahaan/VerifikasiPerusahaanSuperAdminController.php` | `SuperAdmin/Perusahaan/Index` | `/superadmin/perusahaan` |
| Admin Fakultas | `app/Http/Controllers/AdminFakultas/VerifikasiPerusahaan/VerifikasiPerusahaanFakultasController.php` | `SuperAdmin/Perusahaan/Index` | `/fakultas/perusahaan` |
| Admin Prodi | `app/Http/Controllers/AdminProdi/VerifikasiPerusahaan/VerifikasiPerusahaanProdiController.php` | `SuperAdmin/Perusahaan/Index` | `/prodi/perusahaan` |

Route didefinisikan dalam `routes/web.php`. Endpoint verifikasi, penolakan, pembaruan, pencarian master, dan penggantian perusahaan tetap memakai prefix role masing-masing.

Controller Fakultas dan Prodi membatasi daftar maupun aksi melalui query lingkup yang sama. Dengan demikian, data yang dapat ditampilkan juga merupakan data yang dapat diverifikasi oleh role tersebut.

## Kuesioner Per Prodi

Fungsi kuesioner program studi juga memakai satu halaman bersama:

| File tampilan | Role |
| --- | --- |
| `resources/js/Pages/SuperAdmin/Pertanyaan/ProdiKuesionerIndex.vue` | Superadmin, Admin Fakultas, dan Admin Prodi |

Controller `app/Http/Controllers/SuperAdmin/KelolaPertanyaan/KelolaKuesionerProdiSuperAdminController.php` menerima konteks role dan memberikan daftar program studi sesuai otorisasi. Route Superadmin, Fakultas, dan Prodi tetap terpisah agar middleware dan batas akses tetap jelas.

## Kontrak Data dan Response

### Jawaban Tracer

Satu record tracer tetap menjadi unit jawaban per alumni dan per pertanyaan. Kolom `question_id` adalah foreign key ke `ref_subpertanyaan2021.id` meskipun namanya historis memakai istilah `question`; kolom ini menjadi identitas stabil butir untuk pemeliharaan, sedangkan `kode_pertanyaan` dan `subpertanyaan` adalah salinan untuk kompatibilitas laporan. Relasi Eloquent ke master memakai nama `masterPertanyaan` karena `subpertanyaan` sudah digunakan sebagai kolom snapshot teks. `answer` digunakan sebagai representasi teks untuk export dan kompatibilitas, sedangkan `answer_json` digunakan sebagai representasi terstruktur. Untuk jawaban teks sederhana, bentuk JSON minimum adalah { "value": "..." }. Struktur khusus jawaban lama tetap dipertahankan agar radio input, matriks, pilihan ganda, dan nominal tidak rusak.

F505 (Take Home Pay) memiliki sumber tunggal pada `biodata.gaji`. Setiap sinkronisasi profil menimpa jawaban F505 lama dengan nominal gaji terbaru dan menyimpannya dalam `answer` serta `answer_json`, sehingga nilai lama tidak dapat menjadi sumber berbeda.

Model Tracer melengkapi proyeksi yang kosong ketika record disimpan. Migrasi backfill_legacy_tracer_answer_json mengisi record lama tanpa menghapus nilai yang sudah ada.

### Response Inertia dan API

Endpoint LinkedIn yang dipanggil dengan fetch dan header Accept: application/json tetap mengembalikan JSON. Jika endpoint approve, plot ulang, atau reject dipanggil sebagai request Inertia dengan header X-Inertia, controller mengembalikan redirect dengan flash/error sehingga Inertia tidak menerima plain JSON.

### Detail Master Perusahaan

Perusahaan tetap menjadi sumber seluruh field master. Controller memuat scalar field dan relasi yang diperlukan, sedangkan SuperAdmin/Perusahaan/Index.vue menampilkan seluruh field pada modal detail. Rekomendasi master membawa atribut yang sama dan dapat dibuka melalui tombol informasi sebelum konsolidasi.

Link profil alumni pada halaman bersama tidak menggunakan prefix superadmin secara tetap. Prefix detail dipilih dari role aktif agar Admin Prodi dan Admin Fakultas tetap berada pada route dan middleware lingkupnya sendiri, sehingga tidak menghasilkan 403 atau 404 karena salah prefix.

### Kebijakan Data Sensitif pada Profil Alumni

Halaman self-service `/alumni/profile` hanya menerima props yang diperlukan untuk pengisian tracer. NIK, NPWP, KK, data orang tua, dan data pembimbing/penguji tidak lagi menjadi bagian dari schema aktif. NISN, BPJS, email mahasiswa, dan publikasi tetap berada pada sumber akademik yang diperlukan untuk audit/admin, tetapi tidak dikirim ke form self-service. Judul tugas akhir dan tanggal kelulusan tetap ditampilkan sebagai referensi akademik non-sensitif.

Kontak dan domisili aktif hanya ditulis ke `biodata`. Migrasi final melakukan backfill dari kolom legacy sebelum kolom duplikat dihapus, tanpa menimpa nilai biodata yang sudah ada. Database terpisah `tracer` tidak disentuh.

Payload audit evaluasi atasan juga dipetakan melalui \`EvaluasiAtasan::toAdminAuditArray()\`. Payload tersebut hanya membawa status, identitas atasan, instansi, dan jawaban per butir; token akses survei tidak dikirim ke halaman detail alumni. Nilai jawaban dibaca dari kolom sumber \`respon_evaluasi_atasan.nilai\`, bukan alias \`skor\`.

## Pengujian

| File | Cakupan |
| --- | --- |
| `tests/Feature/VerifikasiPerusahaanTest.php` | Tampilan bersama perusahaan, props konteks role, verifikasi perusahaan, dan pembatasan lingkup Prodi/Fakultas. |
| `tests/Feature/SuperAdminPerusahaanDanKuesionerProdiTest.php` | Akses serta tindakan perusahaan dan kuesioner oleh Superadmin. |
| `tests/Feature/AdminFakultasKuesionerProdiTest.php` | Batas akses kuesioner per prodi untuk Fakultas dan Prodi. |

## Aturan Pengembangan Lanjutan

Saat menambah fungsi administrasi yang sama lintas role, gunakan pola berikut:

1. Pertahankan satu halaman Inertia bersama bila struktur interaksinya sama.
2. Berikan `role`, `basePath`, `dashboardPath`, dan data unit melalui props dari controller.
3. Tampilkan perbedaan hanya pada filter atau aksi yang memang berbeda wewenang.
4. Terapkan batas akses pada query dan action controller, lalu tambahkan feature test untuk role yang diizinkan dan yang ditolak.
5. Tambahkan file baru atau perubahan pola ke dokumen ini.

## Autentikasi dan Aset Visual

Alur login, reset password, dan wajib ganti password menggunakan komponen visual bersama `resources/js/components/Otentikasi/AuthBackdrop.vue`. Detail endpoint, batas frontend/backend, aturan komentar komponen, dan organisasi aset tersedia di [arsitektur-autentikasi.md](arsitektur-autentikasi.md).
### Dashboard Pengelola Berbasis Scope

Dashboard Admin Prodi, Admin Fakultas, dan Biro 3 menggunakan kontrak data yang sama melalui `RoleDashboardDataService` dan komponen Vue `RoleDashboard`. Controller role hanya menentukan scope data dan metadata navigasi, sedangkan service membaca `v_alumni_audit_rekap` untuk menghitung populasi, response rate, kelengkapan profil, kelengkapan kuesioner universitas, ringkasan per prodi, serta pratinjau alumni.

Komponen dashboard menerapkan layout responsif yang sama untuk ketiga role. Tautan pintasan tetap dibatasi berdasarkan route yang tersedia pada role tersebut, sehingga Biro 3 tidak menerima tautan modul yang hanya dimiliki Admin Prodi atau Admin Fakultas. Filter tahun diteruskan sebagai query `tahun` ke dashboard role dan seluruh metrik pada halaman dihitung ulang memakai tahun yang sama.

### Analitik Tracer Study

Halaman analitik Superadmin tersedia pada `/superadmin/analitik` dan dirender oleh `SuperAdmin/Analytics/GrafikUniv.vue`. Dropdown memilih satu indikator universitas dalam satu waktu. Render awal tetap memakai respons Inertia, sedangkan pergantian indikator/filter mengambil JSON dari `/superadmin/analitik/data` melalui `useHttp`. Controller meneruskan kunci grafik ke `TracerAnalyticsService::buildUniversityChart()`, sehingga backend hanya membaca database view dan dataset yang diperlukan oleh grafik aktif tanpa melakukan reload halaman penuh.

Halaman dibagi menjadi tiga tab: Analisis menyusun temuan otomatis dari statistik aktual, Grafik menampilkan visual utama dan pendukung yang saling melakukan cross-filter, sedangkan Data menyediakan pencarian, sorting, dan pagination detail. Klik batang, titik, irisan, treemap, heatmap, Sankey, atau provinsi membuka drawer drill-down tanpa menghilangkan konteks filter. Kuesioner prodi tidak dipakai pada halaman ini; filter program studi hanya membatasi populasi lulusan universitas.

Dataset indikator aktif dibaca melalui view terpisah: `v_analytics_population`,
`v_analytics_response_rate`, `v_analytics_profile_completion`,
`v_analytics_wait_time`, `v_analytics_salary_ump`,
`v_analytics_work_location`, `v_analytics_employment_status`, dan
`v_analytics_alignment`, dan view indikator lanjutan
`v_analytics_learning_methods`, `v_analytics_job_search_start`,
`v_analytics_job_search_methods`, `v_analytics_application_pipeline`,
`v_analytics_job_search_activity`, `v_analytics_workplace_type`,
`v_analytics_further_study`, `v_analytics_mismatch_reasons`, serta
`v_analytics_competency_gap`. Seluruh view indikator lanjutan memakai
`v_analytics_tracer_answers` sebagai fondasi jawaban kanonik per alumni dan
kode pertanyaan. Keunikan pasangan `biodata_id` dan `kode_pertanyaan` dijamin
oleh unique key tabel `tracer`, sehingga view tidak melakukan agregasi
`MAX(id)` berulang. Service tidak mengambil seluruh jawaban F2–F18 lalu
memilahnya di PHP. View bukan cache materialisasi, sehingga indeks pada tabel
sumber tetap menjadi bagian penting dari performa.

`v_analytics_population` hanya membaca identitas, akademik, program studi,
perusahaan, dan provinsi kerja dari tabel kanonik. Status pernah menjawab
butir wajib dihitung khusus oleh `v_analytics_response_rate`; indikator lain
tidak ikut membayar biaya agregasi response rate. Denominator kohort dihitung
langsung dari `biodata` dan `data_akademik` tanpa memanggil rekap audit prodi.

`v_analytics_further_study` secara khusus mencakup F12, F18b, F18c, dan F18d.
Service memberi label dimensi sebelum agregasi agar sumber biaya tidak
tercampur dengan nama perguruan tinggi, program studi, atau tanggal mulai.

Indikator lanjutan memakai komponen visual ringan sesuai bentuk datanya:
Seluruh visual analitik menggunakan wrapper reusable Apache ECharts 6, bukan
batang HTML atau SVG statis. Kombinasi mencakup histogram dan box plot native,
batang divergen, donut maksimal lima kategori, treemap, funnel, scatter,
radar, custom-series dumbbell, Sankey, heatmap dengan mode jumlah/persentase,
dan kurva kumulatif. Peta wilayah memakai MapLibre GL JS dengan choropleth,
zoom, pan, popup, layer toggle, dan marker cluster berbasis centroid provinsi
perusahaan. Komponen ECharts dan MapLibre dimuat sebagai chunk terpisah agar
shell halaman, filter, dan metadata dapat dirender lebih dahulu.
Metadata indikator mencatat tipe data, jumlah missing, dasar persentase, serta
waktu perhitungan. Seluruh kartu visualisasi dibungkus oleh komponen universal
`AnalyticsChartCard.vue` dengan tata letak terpadu `[ Grafik | Data ]` (grafik
di sebelah kiri dan tabel data terstruktur di sebelah kanan), pemilih mode
tampilan, serta tombol ekspor PNG, mengeliminasi tombol toolbar berlebih.
Tabel data sampingan mendukung pencarian dan interaksi dua arah dengan grafik
(klik baris tabel menyorot elemen visual, klik grafik menyorot baris tabel).

Visualisasi 18 indikator dipecah ke berkas mandiri pada direktori
`resources/js/components/analytics/categories/` (`01_ResponseRate.vue` s.d.
`18_WorkLocation.vue`) dan dimuat secara asinkron melalui Index Hub
`AnalyticsChartsPanel.vue`. Grafik garis perbandingan resmi diadopsi sesuai
laporan Tracer Study (Gambar 4.3 untuk metode belajar, Gambar 4.6 untuk waktu
tunggu per prodi, Gambar 4.7 untuk tahapan lamaran, Gambar 4.12 untuk pendapatan
per prodi, dan Gambar 4.17 untuk gap kompetensi). Decal pattern dinonaktifkan
demi tampilan flat bersih dan warna solid brand `#0D542B` digunakan untuk metrik
sejenis. Halaman analitik lama `SuperAdmin/Analytics/Index.vue` telah dihapus.
Saat record kosong, `data_availability` membedakan tidak ada populasi, belum ada
jawaban valid, dan jawaban yang tidak berlaku akibat percabangan.

`AnalyticsDemoDistributionSeeder` hanya menata akun dengan pola email demo untuk pengujian distribusi wilayah, gaji di bawah ambang, dan alumni belum bekerja. Data alumni non-demo tidak disentuh.

| Indikator | Sumber data | Bentuk visual |
| --- | --- | --- |
| Response rate | `v_analytics_response_rate.answered_mandatory_univ` | Bar horizontal per prodi |
| Waktu memperoleh pekerjaan | `tracer.F502`, `tracer.F504`, `tracer.F506` | Distribusi bucket dan median per prodi |
| Gaji dibandingkan 1,2× UMP | `biodata.gaji`, `perusahaan.propinsi_id`, `propinsi`, `ump` | Peta wilayah, bar per prodi, daftar memenuhi/belum, tabel individu |
| Wilayah kerja | `perusahaan.propinsi_id`, `propinsi` | Peta choropleth dan ranking provinsi |
| Status pekerjaan dan IKU 1 awal | `tracer.F8` | Bar status dan KPI |
| Hubungan bidang studi | `tracer.F14`, `ref_subpertanyaan_detil` | Bar kategori dan tabel alumni |
| Keselarasan tingkat pendidikan | `tracer.F15`, `ref_subpertanyaan_detil` | Bar kategori dan tabel alumni |
| Kelengkapan profil | `v_analytics_population.is_profile_complete` | Bar lengkap/belum lengkap dan tabel alumni |

Grafik pendapatan menggunakan aturan `gaji >= 1,2 × UMP`, bukan sekadar `gaji >= UMP`. Record salary menyimpan nama alumni, NIM, program studi, perusahaan, provinsi, gaji, UMP, ambang pembanding, dan status. Grafik waktu tunggu mengklasifikasikan alumni sebagai lebih cepat, sama dengan, atau lebih lama dari median aktual pada filter aktif.

Perbandingan UMP hanya memakai alumni dengan gaji valid, provinsi tempat kerja, dan nilai UMP pada tahun yang dipilih. Nilai gaji `>= 1,2 × UMP` masuk kategori memenuhi; nilai lain masuk kategori di bawah. Data yang tidak memiliki salah satu komponen tersebut dilaporkan sebagai belum dapat dinilai dan tidak dipaksakan masuk denominator. Median digunakan sebagai statistik pusat gaji dan waktu tunggu; jumlah responden, P25, P75, dan cakupan data tetap ditampilkan agar angka tidak berdiri tanpa konteks.

Istilah “view” dibedakan menjadi tiga lapisan: halaman Inertia untuk satu ruang analitik, komponen Vue untuk satu jenis visual, dan database view untuk satu sumber indikator aktif. Panel penjelasan pada tiap indikator menyampaikan formula, denominator, serta field sumber; tab Data menyediakan baris individu untuk audit hasil agregasi.
