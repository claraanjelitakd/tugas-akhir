# Arsitektur Antarmuka Admin

Dokumen ini menjelaskan struktur antarmuka dan batas akses untuk Superadmin, Admin Fakultas, Admin Program Studi, dan Admin Biro III. Tujuannya adalah menjaga halaman dengan fungsi yang sama tetap memakai pola visual dan komponen yang sama, sementara cakupan datanya ditentukan oleh role pengguna.

## Prinsip

- Satu fungsi inti memakai satu halaman utama bila struktur dan perilakunya sama.
- Role hanya menentukan sidebar, judul, route dasar, filter yang tersedia, dan batas data.
- Pembatasan data wajib berada di controller dan query backend; filter di browser bukan mekanisme otorisasi.
- Komponen bersama berada di `resources/js/components` dan digunakan lewat wrapper pada direktori role yang sudah ada.

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

Halaman self-service \`/alumni/profile\` hanya menerima props yang diperlukan untuk pengisian tracer. NIK, NPWP, KK, NISN, BPJS, email mahasiswa, data pembimbing/penguji, publikasi, dan data orang tua tidak dikirim sebagai props profil alumni dan tidak dirender di frontend. Judul tugas akhir dan tanggal kelulusan tetap ditampilkan sebagai referensi akademik non-sensitif.

Kolom serta data lama tidak dihapus. Data tersebut tetap menjadi sumber resmi untuk integrasi akademik, audit admin, ekspor, dan kompatibilitas migrasi. Controller penyimpanan profil juga tidak lagi menulis field identitas sensitif ketika field itu tidak dikirim, sehingga menyembunyikan field tidak mengosongkan data lama.

Keputusan ini memakai kebijakan visibilitas eksplisit pada controller dan komponen, bukan komentar blok template. Komentar database pada migrasi \`document_sensitive_profile_data_policy\` menjelaskan alasan retensi dan pembatasan tampilan untuk setiap tabel/kolom terkait.

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
