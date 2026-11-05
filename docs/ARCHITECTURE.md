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
- `biodata.bidang_pekerjaan` dan `biodata.divisi` adalah dua atribut karier yang berbeda. Bidang menyatakan rumpun pekerjaan, sedangkan divisi menyatakan unit kerja aktual; keduanya diproyeksikan pada direktori dan analitik.
- NIK, No KK, dan NPWP tidak lagi menjadi bagian dari data aplikasi. Kolom tersebut tidak dibuat pada migration schema aktif.
- Tabel `data_orang_tua` dan foreign key `biodata.orang_tua_id` tidak ada pada schema aktif karena data orang tua tidak digunakan dalam profil maupun ekspor Excel.
- Data pembimbing/penguji dihapus dari `yudisium`; judul tugas akhir, tanggal lulus, status lulus, dan publikasi yang masih digunakan tetap dipertahankan.
- Data sekolah asal dan tempat lahir tidak termasuk schema aktif. Domisili alumni dan lokasi perusahaan memakai `kode_provinsi` serta `kode_kabupaten` sebagai foreign key berbasis primary key natural; negara perusahaan memakai `kode_negara` sebagai FK ke `ref_negara.kode_negara`.
- Nama perusahaan pada direktori mengambil master `perusahaan` sebagai entitas head office. Cabang atau lokasi tempat tinggal alumni tidak ditulis ke master perusahaan; lokasi alumni ditampilkan dari domisili aktif pada `biodata`.
- Filter direktori memakai opsi yang dikirim backend dalam `filterOptions`. Wilayah
  dipisahkan menjadi provinsi dan kabupaten/kota; pilihan kabupaten/kota di UI
  dibatasi oleh provinsi yang dipilih dan query backend tetap menerapkan keduanya.
  Nilai filter wilayah memakai ID master kode_provinsi dan kode_kabupaten;
  opsi provinsi/kabupaten berasal dari tabel master, sedangkan opsi karier hanya
  menyaring nilai yang tersedia pada scope alumni aktif.
- Kolom status kelengkapan direktori selalu memproyeksikan status kuesioner Univ
  dan Prodi, profil, serta atasan. Kuesioner Prodi tanpa pertanyaan aktif dikirim
  sebagai nilai tidak berlaku (`NULL`) dan ditampilkan sebagai `Prodi: -`.
- Direktori mempertahankan aksi kirim email per alumni. Flash notification dari
  endpoint ditampilkan kembali setelah pengiriman berhasil atau ditolak.
- Direktori operasional tetap memakai ikon LinkedIn untuk sinkronisasi dan review; grafik analitik hanya memakai tautan profil langsung saat pengguna membuka detail alumni.

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

### Modul Analitik Evaluasi Atasan

Analitik evaluasi pengguna lulusan memakai alur data yang sama dengan modul
analitik lain, tetapi sumbernya adalah tabel transaksi evaluasi atasan:

| Lapisan | Implementasi | Tanggung jawab |
| --- | --- | --- |
| Route | `GET /superadmin/analitik-atasan` dan `/data` | Menyediakan navigasi dan payload JSON untuk Superadmin. |
| Controller | `AtasanAnalyticsController` | Mengirim props Inertia atau JSON tanpa menghitung agregasi di Vue. |
| Service | `AtasanAnalyticsService` | Memuat evaluasi terkirim, memetakan opsi master ke skor, dan menghitung ringkasan. |
| View | `SuperAdmin/Analytics/GrafikAtasan.vue` | Mengelola filter, empty state, tiga grafik, tabel, dan metodologi. |
| Chart | `AnalyticsEChart.vue` | Merender opsi ECharts dan menangani resize/loading bersama. |

Nilai aspek dan opsi tidak ditulis ulang pada view. Perubahan instrumen aktif di
`pertanyaan_evaluasi_atasan` langsung tercermin pada payload selama tipe dan
opsinya valid.

### Provider LinkedIn dan mode mock Apify

Pemilihan provider sinkronisasi dikendalikan oleh `LINKEDIN_PROVIDER` pada
konfigurasi server. Nilai `mock_apify` memakai kontrak staging yang sama dengan
Apify, tetapi membaca fixture JSON lokal dari `APIFY_MOCK_PATH`; token dan
request eksternal tidak digunakan. Mode ini menjaga pengembangan lokal tetap
aman ketika LinkedIn mengembalikan `HTTP 999` atau kuota actor habis. Provider
mock tidak boleh dianggap sebagai data produksi dan tetap mengharuskan proses
review sebelum perubahan biodata diterapkan.

Untuk membedakan pengujian mock lokal dengan profil live, URL LinkedIn pada
database lokal menggunakan akhiran `-mock` (misal `jovanyulianto-mock`). Sistem
secara otomatis mencocokkan handle bersih ke berkas kanonik di `storage/app/mock/apify`.

Normalizer staging mendukung actor `harvestapi/linkedin-profile-scraper`:
- Mengambil nama lengkap dari `firstName` dan `lastName`.
- Mengutamakan `topSkills` (array maupun string berpemisah `•`) untuk field keahlian `skills`.
- Mengurai posisi aktif dari `currentPosition[0]` serta menduplikasi riwayat pekerjaan secara aman.
- Memetakan bidang industri (`industry.name`) ke atribut usulan `bidang_pekerjaan` alumni.
- Mendeteksi riwayat studi lanjut pascasarjana secara cerdas (`further_study`: Doktor/S3, Magister/S2, Profesi, atau entri aktif non-almamater UKDW) untuk dipetakan ke `pendidikan_tingkat`, `perguruan_tinggi`, dan `pendidikan_prodi`.
- Payload request resmi HarvestAPI dikirim dengan `"profileScraperMode": "Profile details no email ($4 per 1k)"` dan `"queries": ["https://..."]`.
- Menyimpan payload mentah utuh pada kolom `scraped_data` (tipe `JSON` di MySQL). MySQL binary JSON secara alami mengurutkan kunci objek secara alfabetis saat disimpan. Field lokasi dan atribut lainnya dipastikan berupa string aman agar tabel pemetaan audit trace (`LinkedinTraceHelper`) tidak memicu kesalahan tipe di frontend Vue.

### Aturan Preservasi Data Scraping LinkedIn (Staging & Backup)

Data staging hasil scraping LinkedIn (`linkedin_sync_results`) dan tautan URL profil alumni untuk pengujian tidak boleh hilang saat `php artisan migrate:fresh --seed`:
1. `LinkedinSyncResultSeeder` terdaftar pada `DatabaseSeeder.php` dan otomatis dijalankan saat proses migrasi fresh atau seeding ulang.
2. Seeder membaca snapshot cadangan dari `storage/app/backup/linkedin_sync_results_backup.json` dan memulihkannya berbasis NIM alumni (bukan sekadar auto-increment `biodata_id`), sehingga perubahan ID tabel tidak merusak data scraping.
3. Seeder memastikan profil uji kunci (Jovan Yulianto NIM `72220002` dan Argo Wibowo NIM `71170001`) selalu terhubung dengan fixture kanoniknya di `storage/app/mock/apify`.
4. Setiap penarikan scraping baru (`syncSingle`) serta mutasi persetujuan/penolakan (`approveResult` / `rejectResult`) di `LinkedInSyncController` otomatis mencadangkan status data terkini ke berkas JSON cadangan di disk.

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

## Kuesioner Terpadu (Unified Survey Engine)

Aplikasi menggunakan arsitektur kuesioner terpadu (*Unified Survey Engine*) yang mengonsolidasikan seluruh jenis instrumen survei ke dalam satu set tabel kanonik:
- `kuesioner_section`: Mengelompokkan bagian/stepper kuesioner (`target`: `'univ'`, `'prodi'`, atau `'atasan'`).
- `kuesioner_pertanyaan`: Bank butir pertanyaan terpusat untuk kuesioner universitas, kuesioner prodi, dan kuesioner atasan.
- `kuesioner_section_pertanyaan`: Mengatur penempatan butir pertanyaan pada bagian, urutan tampilan, status tampil lokal (`is_active`), dan status kewajiban (`wajib = 1` atau `0`). Setiap baris adalah satu penggunaan pertanyaan. Kolom `id` baris ini disebut `placement_id` pada payload/antarmuka aplikasi; `pertanyaan_id` tetap menunjuk master pertanyaan. Status lokal memungkinkan Prodi pengguna menonaktifkan pertanyaan pinjaman tanpa memengaruhi placement Prodi lain.

Untuk operasi pengelolaan kuesioner Prodi, PUT/edit dan pengelolaan opsi memakai ID master (`pertanyaan_id`). Toggle aktif, hapus penggunaan, dan reorder memakai ID penempatan (`placement_id`), yaitu nilai kolom `id` pada `kuesioner_section_pertanyaan`. Dengan demikian, satu pertanyaan master dapat dipakai di banyak section tanpa operasi lokal saling mengubah.
- `kuesioner_opsi`: Master opsi pilihan jawaban beserta skor bobot numerik dan kode lompatan (*jump_to*).
- `kuesioner_jawaban`: Transaksi jawaban terpadu untuk Alumni maupun Atasan penilai (`responden_type`: `'alumni'` atau `'atasan'`).

Fungsi kuesioner program studi memakai satu halaman bersama:

| File tampilan | Role |
| --- | --- |
| `resources/js/Pages/SuperAdmin/Pertanyaan/ProdiKuesionerIndex.vue` | Superadmin, Admin Fakultas, dan Admin Prodi |

Controller `app/Http/Controllers/SuperAdmin/KelolaPertanyaan/KelolaKuesionerProdiSuperAdminController.php` menerima konteks role dan memberikan daftar program studi sesuai otorisasi. Route Superadmin, Fakultas, dan Prodi tetap terpisah agar middleware dan batas akses tetap jelas.

## Kontrak Data dan Response

### Jawaban Kuesioner Terpadu

Satu rekaman pada `kuesioner_jawaban` menjadi unit jawaban per responden (`biodata_id` untuk alumni, atau `evaluasi_atasan_id` untuk atasan) dan per butir pertanyaan (`pertanyaan_id`). Kolom `kode_pertanyaan` dipertahankan sebagai snapshot identitas butir untuk kompatibilitas dan kecepatan kueri analitik. Nilai jawaban teks/angka disimpan pada `jawaban_teks`, sedangkan pilihan ganda terstruktur disimpan pada `jawaban_json`.

F505 (Take Home Pay) memiliki sumber tunggal pada `biodata.gaji`. Setiap sinkronisasi profil menimpa jawaban F505 lama dengan nominal gaji terbaru dan menyimpannya dalam `jawaban_teks`, sehingga nilai lama tidak menjadi sumber inkonsistensi.

### Response Inertia dan API

Endpoint LinkedIn yang dipanggil dengan fetch dan header Accept: application/json tetap mengembalikan JSON. Jika endpoint approve, plot ulang, atau reject dipanggil sebagai request Inertia dengan header X-Inertia, controller mengembalikan redirect dengan flash/error sehingga Inertia tidak menerima plain JSON.

### Detail Master Perusahaan

Perusahaan tetap menjadi sumber seluruh field master. Controller memuat scalar field dan relasi yang diperlukan, sedangkan SuperAdmin/Perusahaan/Index.vue menampilkan seluruh field pada modal detail. Rekomendasi master membawa atribut yang sama dan dapat dibuka melalui tombol informasi sebelum konsolidasi.

Link profil alumni pada halaman bersama tidak menggunakan prefix superadmin secara tetap. Prefix detail dipilih dari role aktif agar Admin Prodi dan Admin Fakultas tetap berada pada route dan middleware lingkupnya sendiri, sehingga tidak menghasilkan 403 atau 404 karena salah prefix.

### Kebijakan Data Sensitif pada Profil Alumni

Halaman self-service `/alumni/profile` hanya menerima props yang diperlukan untuk pengisian tracer. NIK, NPWP, KK, data orang tua, dan data pembimbing/penguji tidak lagi menjadi bagian dari schema aktif. `data_akademik.email_pribadi` menjadi snapshot email dari sumber akademik dan hanya dipakai sebagai nilai awal saat `biodata` dibuat; kontak aktif alumni tetap berada pada `biodata.email_pribadi` dan tidak ditulis balik ke data akademik. Email sumber akademik tidak dikirim ke form self-service sebagai field terpisah. Judul tugas akhir dan tanggal kelulusan tetap ditampilkan sebagai referensi akademik non-sensitif.

Kontak dan domisili aktif hanya ditulis ke `biodata`. Perubahan `biodata.email_pribadi` tidak mengubah snapshot `data_akademik.email_pribadi`.

Payload audit evaluasi atasan juga dipetakan melalui `EvaluasiAtasan::toAdminAuditArray()`. Payload tersebut hanya membawa status, identitas atasan, instansi, dan jawaban per butir; token akses survei tidak dikirim ke halaman detail alumni. Jawaban evaluasi atasan disimpan langsung ke `kuesioner_jawaban` dengan `responden_type = 'atasan'` dan `evaluasi_atasan_id = evaluasi_atasan.id`.

### Sinkronisasi Dua Arah Data Perusahaan (Profil Alumni dan Evaluasi Atasan)

Atribut perusahaan pada halaman profil alumni (`FormKarier.vue` dan `SimpanProfilController`) serta halaman evaluasi pengguna lulusan (`EvaluasiAtasanForm.vue` dan `EvaluasiAtasanController`) membaca dan menulis langsung ke record master `perusahaan` yang sama:
- Field yang tersinkronisasi dua arah: `nama_perusahaan`, `bentuk_perusahaan`, `jenis_perusahaan`, `jenis_perusahaan_lainnya`, `skala`, `jenis_lokasi`, `kode_negara`, `kode_provinsi`, `kode_kabupaten`, `alamat`, `kode_pos`, `no_telp_fax`, dan `homepage`.
- Karakteristik survei pengguna lulusan yang diisi khusus oleh atasan disimpan sesuai peruntukannya: karakteristik instansi disimpan pada tabel `perusahaan`, sedangkan `jumlah_alumni_ukdw` dan `standar_gaji_pertama` disimpan pada tabel `evaluasi_atasan`. Seluruh skor penilaian kinerja disimpan pada tabel `kuesioner_jawaban`.

### Keseragaman Komponen Kartu Pertanyaan Kuesioner Universitas dan Prodi

Komponen `KartuPertanyaan.vue` (Kuesioner Universitas) dan `KartuPertanyaanProdi.vue` (Kuesioner Program Studi) menggunakan pola visual dan kontrak interaksi yang sama:
- Setiap kartu soal secara otomatis menampilkan badge instruksi bawaan (*default*) sesuai tipe pertanyaan (`Pilih salah satu jawaban`, `Jawaban bisa lebih dari satu (centang yang sesuai)`, dsb.).
- Tipe pilihan tunggal (`single_choice`, `radio`, `radio_text`, `radio_input`) menampilkan indikator lingkaran radio button (`rounded-full`) di sisi kiri opsi, sedangkan tipe pilihan ganda (`multiple_choice`, `checkbox`) menampilkan indikator kotak centang (`rounded-md` dengan ikon centang).
- Opsi `Lainnya` / `Other` pada pilihan tunggal maupun pilihan ganda membuka input teks spesifik (`_custom`) yang divalidasi dan disimpan ke database.
- Indikator ceklist selesai pada Stepper `KuesionerProdi.vue` hanya aktif apabila seluruh pertanyaan wajib (atau seluruh pertanyaan non-header) pada bagian tersebut telah terjawab valid.

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

Dashboard Admin Prodi, Admin Fakultas, dan Biro 3 menggunakan kontrak data yang sama melalui `RoleDashboardDataService` dan komponen Vue `RoleDashboard`. Controller role hanya menentukan scope data dan metadata navigasi, sedangkan service membaca `v_alumni_audit_rekap` untuk menghitung populasi, response rate, kelengkapan profil, kelengkapan kuesioner universitas, ringkasan per prodi, serta pratinjau alumni. Direktori juga menerapkan filter server-side untuk wilayah domisili, head office, skills, bidang, dan unit/divisi.

Komponen dashboard menerapkan layout responsif yang sama untuk ketiga role. Tautan pintasan tetap dibatasi berdasarkan route yang tersedia pada role tersebut, sehingga Biro 3 tidak menerima tautan modul yang hanya dimiliki Admin Prodi atau Admin Fakultas. Filter tahun diteruskan sebagai query `tahun` ke dashboard role dan seluruh metrik pada halaman dihitung ulang memakai tahun yang sama.

### Analitik Tracer Study

Halaman analitik Superadmin tersedia pada `/superadmin/analitik` dan dirender oleh `SuperAdmin/Analytics/GrafikUniv.vue`. Dropdown memilih satu indikator universitas dalam satu waktu. Render awal tetap memakai respons Inertia, sedangkan pergantian indikator/filter mengambil JSON dari `/superadmin/analitik/data` melalui `useHttp`. Controller meneruskan kunci grafik ke `TracerAnalyticsService::buildUniversityChart()`, sehingga backend hanya membaca database view dan dataset yang diperlukan oleh grafik aktif tanpa melakukan reload halaman penuh.

Halaman dibagi menjadi tiga tab: Analisis menyusun temuan otomatis dari statistik aktual, Grafik menampilkan visual utama dan pendukung yang saling melakukan cross-filter, sedangkan Data menyediakan pencarian, sorting, dan pagination detail. Klik batang, titik, irisan, treemap, heatmap, Sankey, atau provinsi membuka drawer drill-down tanpa menghilangkan konteks filter. Kuesioner prodi tidak dipakai pada halaman ini; filter program studi hanya membatasi populasi lulusan universitas.

Pada konteks Admin Fakultas, tautan detail dari tabel/grafik memakai route `GET /fakultas/analitik/alumni/{id}`. Route ini mengizinkan pembacaan detail alumni lintas fakultas untuk kebutuhan analitik, tetapi mengirim prop `readonly = true`. Detail dari direktori biasa tetap memakai `GET /fakultas/alumni/{id}` dan hanya menerima alumni dalam fakultas admin. Endpoint mutasi profil dan ekspor tetap memeriksa scope fakultas sehingga mode baca-saja tidak menjadi jalur perubahan data.

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
perusahaan, URL profil LinkedIn, dan provinsi kerja dari tabel kanonik. URL tersebut
hanya menjadi tautan baca pada tabel analitik; direktori operasional tetap memakai
aksi sinkronisasi/review. Status responden kuesioner selesai
dihitung khusus oleh `v_analytics_response_rate` berbasis penyelesaian 100% butir kuesioner
universitas (`v_alumni_tracer_univ_status`), menjamin sinkronisasi mutlak dengan direktori
alumni. Denominator kohort dihitung langsung dari `biodata` dan `data_akademik` tanpa memanggil rekap audit prodi.

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
`AnalyticsChartsPanel.vue`. Setiap modul dibungkus oleh komponen terpadu
`AnalyticsTabbedContainer.vue` yang mengintegrasikan navigasi tab visualisasi,
analisis naratif data-driven ("Kilas Indikator"), drill-down inline tanpa
lompat tab, dan seleksi data dua arah.

Grafik garis perbandingan resmi diadopsi sesuai laporan Tracer Study:
Gambar 4.3 untuk metode belajar (skor bulat INT 1-5, dengan 1 = Tidak Sama
Sekali dan 5 = Sangat Besar), Gambar 4.6 untuk waktu tunggu per prodi, Gambar
4.7 untuk tahapan lamaran, Gambar 4.10 untuk dispersi
pendapatan 3 garis kuartil (Q1, Median/Q2, Q3), Gambar 4.14 untuk vertical match
diagram garis per prodi, dan Gambar 4.17 untuk kombinasi batang bekal kampus vs
kebutuhan kerja dengan garis gap selisih (*Dual Y-Axis*). Sumbu X kategori prodi
diberi rotasi 30 derajat (`rotate: 30`) guna mencegah teks nama prodi panjang saling
bertumpuk (*nabrak*). Palet warna menerapkan harmoni institusi: Hijau UKDW (`#0D542B`)
sebagai jangkar primer, Slate netral lembut (`#94A3B8`) untuk status pembanding/belum,
dan Slate tegas (`#334155`) untuk garis target/gap tanpa warna oranye yang silau/bentrok.
Tahapan lamaran per program studi memakai median jumlah perusahaan dilamar, merespon,
dan wawancara; visual, tooltip, dan tabel menampilkan hasilnya sebagai bilangan bulat
tanpa pecahan desimal.
Halaman analitik lama `SuperAdmin/Analytics/Index.vue` telah dihapus.
Saat record kosong, `data_availability` membedakan tidak ada populasi, belum ada
jawaban valid, dan jawaban yang tidak berlaku akibat percabangan.

`AnalyticsDemoDistributionSeeder` hanya menata akun dengan pola email demo untuk pengujian distribusi wilayah, gaji di bawah ambang, dan alumni belum bekerja. Data alumni non-demo tidak disentuh.

| Indikator | Sumber data | Bentuk visual |
| --- | --- | --- |
| Response rate | `v_analytics_response_rate.answered_mandatory_univ` | Bar horizontal per prodi |
| Waktu memperoleh pekerjaan | `tracer.F502`, `tracer.F504`, `tracer.F506` | Distribusi bucket dan median per prodi |
| Gaji dibandingkan 1,2× UMP | `biodata.gaji`, `perusahaan.kode_provinsi`, `propinsi`, `ump` | Dispersi kuartil Q1/Q2/Q3 garis per prodi, kelayakan UMP per prodi, dan status per provinsi |
| Wilayah kerja | `perusahaan.kode_provinsi`, `propinsi` | Peta choropleth dan ranking provinsi |
| Status pekerjaan dan IKU 1 awal | `tracer.F8` | Bar status dan KPI |
| Hubungan bidang studi | `tracer.F14`, `ref_subpertanyaan_detil` | Bar kategori dan line chart keselarasan per prodi |
| Keselarasan tingkat pendidikan | `tracer.F15`, `ref_subpertanyaan_detil` | Bar kategori dan line chart keselarasan vertikal per prodi (Gambar 4.14) |
| Kompetensi & Gap Selisih | `tracer.F17` | Kombinasi batang Likert (0-5) dan garis gap selisih Dual Y-Axis (-3 s/d +3) |
| Kelengkapan profil | `v_analytics_population.is_profile_complete` | Bar lengkap/belum lengkap dan tabel alumni |

Grafik pendapatan menggunakan aturan `gaji >= 1,2 × UMP`, bukan sekadar `gaji >= UMP`. Record salary menyimpan nama alumni, NIM, program studi, perusahaan, provinsi, gaji, UMP, ambang pembanding, dan status. Grafik waktu tunggu mengklasifikasikan alumni sebagai lebih cepat, sama dengan, atau lebih lama dari median aktual pada filter aktif.

Perbandingan UMP hanya memakai alumni dengan gaji valid, provinsi tempat kerja, dan nilai UMP pada tahun yang dipilih. Nilai gaji `>= 1,2 × UMP` masuk kategori memenuhi; nilai lain masuk kategori di bawah. Data yang tidak memiliki salah satu komponen tersebut dilaporkan sebagai belum dapat dinilai dan tidak dipaksakan masuk denominator. Median digunakan sebagai statistik pusat gaji dan waktu tunggu; jumlah responden, P25, P75, dan cakupan data tetap ditampilkan agar angka tidak berdiri tanpa konteks.

Istilah “view” dibedakan menjadi tiga lapisan: halaman Inertia untuk satu ruang analitik, komponen Vue untuk satu jenis visual, dan database view untuk satu sumber indikator aktif. Panel penjelasan pada tiap indikator menyampaikan formula, denominator, serta field sumber; tab Data menyediakan baris individu untuk audit hasil agregasi.
