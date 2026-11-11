# Database Views

Dokumen ini adalah kontrak view runtime untuk dashboard, audit kelengkapan,
autofill kuesioner, dan pemetaan jawaban. DDL aktif disimpan satu per view di
`database/migrations` agar definisi setiap view mudah ditelusuri.

Status view: **KANONIK/AKTIF READ-MODEL** berarti dipakai service runtime;
**LEGACY ALIAS** berarti dipertahankan untuk kompatibilitas dan bukan sumber
utama service baru; **DERIVED/OPERASIONAL** berarti proyeksi baca dari tabel
kanonik dan tidak menerima penulisan data bisnis.

## View aktif

| View | Model/service pengguna | Fungsi |
|---|---|---|
| v_alumni_profile_summary | AlumniProfileSummary | Ringkasan profil, akademik, yudisium, karier, perusahaan, dan atasan. |
| v_alumni_tracer_univ_status | AlumniTracerUnivStatus | Jumlah butir wajib dan jawaban kuesioner universitas. |
| v_alumni_tracer_prodi_status | AlumniTracerProdiStatus | Jumlah butir dan jawaban kuesioner program studi. |
| v_alumni_audit_rekap | AlumniAuditRekap | Rekap gabungan kelengkapan untuk dashboard dan direktori. |
| v_alumni_kuesioner_autofill | AlumniKuesionerAutofill | Nilai autofill dari biodata, data_akademik, perusahaan, dan yudisium. |
| v_question_mappings | QuestionMapping | Metadata pemetaan kode pertanyaan ke tabel dan kolom kanonik. |
| v_alumni_linkedin_plot | AlumniLinkedinPlot | Status staging, approval, kecocokan nama, dan plot LinkedIn. |
| v_kuesioner_rekap_jawaban | Legacy, belum direferensikan aplikasi | Rekap denormalisasi lama; dipertahankan hanya untuk kompatibilitas migration history dan kandidat penghapusan pada baseline schema berikutnya. |
| v_analytics_population | TracerAnalyticsService | Populasi ringan satu baris per alumni dari biodata, akademik, prodi, perusahaan, URL profil LinkedIn, dan provinsi kerja tanpa agregasi kuesioner prodi. |
| v_analytics_response_rate | TracerAnalyticsService | Status minimal satu jawaban wajib kuesioner universitas per alumni; agregasi ini sengaja dipisahkan dari populasi. |
| v_analytics_profile_completion | TracerAnalyticsService | Status kelengkapan profil per alumni. |
| v_analytics_wait_time | TracerAnalyticsService | Nilai waktu tunggu yang sudah dinormalisasi dari F502/F504/F506. |
| v_analytics_salary_ump | TracerAnalyticsService | Gaji, UMP, ambang 1,2 kali UMP, dan status pemenuhan. |
| v_analytics_work_location | TracerAnalyticsService | Provinsi perusahaan tempat alumni bekerja. |
| v_analytics_employment_status | TracerAnalyticsService | Jawaban F8 yang diproyeksikan per alumni. |
| v_analytics_alignment | TracerAnalyticsService | Jawaban F14/F15 yang diproyeksikan per alumni. |
| v_analytics_tracer_answers | TracerAnalyticsService | Jawaban tracer terbaru per alumni dan pertanyaan untuk indikator gabungan F3-F27; riwayat lama tidak digandakan. |
| v_analytics_learning_methods | Legacy alias | Proyeksi F21-F27; service kini memfilter `v_analytics_tracer_answers` langsung. |
| v_analytics_job_search_start | Legacy alias | Proyeksi F3; service kini memfilter `v_analytics_tracer_answers` langsung. |
| v_analytics_job_search_methods | Legacy alias | Proyeksi F4; service kini memfilter `v_analytics_tracer_answers` langsung. |
| v_analytics_application_pipeline | Legacy alias | Proyeksi F6/F7/F7A; service kini memfilter `v_analytics_tracer_answers` langsung. |
| v_analytics_job_search_activity | Legacy alias | Proyeksi F10; service kini memfilter `v_analytics_tracer_answers` langsung. |
| v_analytics_workplace_type | Legacy alias | Proyeksi F11; service kini memfilter `v_analytics_tracer_answers` langsung. |
| v_analytics_further_study | Legacy alias | Proyeksi F12/F18b/F18c/F18d; service kini memfilter `v_analytics_tracer_answers` langsung. |
| v_analytics_mismatch_reasons | Legacy alias | Proyeksi F16; service kini memfilter `v_analytics_tracer_answers` langsung. |
| v_analytics_competency_gap | Legacy alias | Proyeksi F17a/F17b; service kini memfilter `v_analytics_tracer_answers` langsung. |
| v_iku_s1_aee | IkuS1AnalyticsService | Seluruh populasi akademik S1, status pengecualian, dan penanda lulus tepat waktu untuk AEE. |
| v_iku_s1_lulusan | IkuS1AnalyticsService | Lulusan S1 dengan status karier, masa tunggu, gaji, UMP/provinsi, dan tanggal studi lanjut untuk formula bobot IKU. |

### Status setiap view

| Status | View |
| :--- | :--- |
| **DERIVED/OPERASIONAL** | `v_alumni_profile_summary`, `v_alumni_tracer_univ_status`, `v_alumni_tracer_prodi_status`, `v_alumni_audit_rekap`, `v_alumni_kuesioner_autofill`, `v_question_mappings`, `v_alumni_linkedin_plot` |
| **LEGACY ALIAS** | `v_kuesioner_rekap_jawaban`, `v_analytics_learning_methods`, `v_analytics_job_search_start`, `v_analytics_job_search_methods`, `v_analytics_application_pipeline`, `v_analytics_job_search_activity`, `v_analytics_workplace_type`, `v_analytics_further_study`, `v_analytics_mismatch_reasons`, `v_analytics_competency_gap` |
| **KANONIK/AKTIF READ-MODEL** | `v_analytics_population`, `v_analytics_response_rate`, `v_analytics_profile_completion`, `v_analytics_wait_time`, `v_analytics_salary_ump`, `v_analytics_work_location`, `v_analytics_employment_status`, `v_analytics_alignment`, `v_analytics_tracer_answers`, `v_iku_s1_aee`, `v_iku_s1_lulusan` |

Kode pertanyaan dan kode opsi pada output read-model berstatus
**DISPLAY/DERIVED**: kode idealnya ditarik dari tabel master melalui relasi ID
dan tidak menjadi sumber tulis baru pada tabel jawaban. Audit saat ini masih
menandai `v_kuesioner_rekap_jawaban` dan `v_iku_s1_lulusan` sebagai pengecualian
yang membaca snapshot kode lama; keduanya harus dipindahkan ke relasi
`pertanyaan_id`/`opsi_id` saat refaktor runtime dilakukan.

## Rincian fungsi per view

| View | Grain data | Fungsi operasional | Aturan penting |
|---|---|---|---|
| `v_alumni_profile_summary` | Satu baris per alumni | Menyatukan data untuk halaman detail alumni dan pembaruan profil admin. | Profil aktif tetap dari `biodata`; akademik hanya referensi/snapshot. |
| `v_alumni_tracer_univ_status` | Satu baris per alumni | Menghitung kemajuan kuesioner Universitas. | Hanya butir wajib yang berlaku menurut skip logic masuk denominator. |
| `v_alumni_tracer_prodi_status` | Satu baris per alumni dan scope prodi | Menghitung kemajuan kuesioner Program Studi. | Prodi tanpa placement aktif bernilai netral, bukan 100%. |
| `v_alumni_audit_rekap` | Satu baris per alumni | Menyediakan status ringkas Profil, Univ, Prodi, dan Atasan untuk dashboard/direktori. | Tidak menjadi sumber data baru; hanya rekap read-only. |
| `v_alumni_kuesioner_autofill` | Satu baris per alumni | Memasok nilai awal untuk field kuesioner yang dapat diisi otomatis. | Tidak menggantikan jawaban yang sudah diisi alumni. |
| `v_question_mappings` | Satu baris per kode pertanyaan | Memetakan kode instrumen ke sumber/kolom kanonik. | Menjaga service tidak bergantung pada nama section atau tabel legacy. |
| `v_alumni_linkedin_plot` | Satu baris per alumni | Menampilkan staging sync LinkedIn, hasil kecocokan, dan status review. | Data staging/review tidak langsung menimpa profil alumni tanpa approval. |
| `v_kuesioner_rekap_jawaban` | Satu baris per jawaban | Menyediakan rekap jawaban terpadu untuk kebutuhan audit dan ekspor. | `nilai_skor` berasal dari `kuesioner_opsi`; view ini bukan sumber bobot IKU S1. |
| `v_analytics_population` | Satu baris per alumni | Menjadi populasi dasar semua analitik tracer dan tabel drill-down. | Tidak mengagregasi jawaban kuesioner supaya filter dan scope tetap akurat. |
| `v_analytics_response_rate` | Satu baris per alumni | Menandai alumni yang memiliki jawaban wajib Universitas. | Dipisah dari populasi agar pembilang response rate dapat diaudit. |
| `v_analytics_profile_completion` | Satu baris per alumni | Menandai kelengkapan profil alumni untuk dashboard dan analitik. | Menggunakan kontrak field profil aktif, bukan field legacy. |
| `v_analytics_wait_time` | Satu baris per alumni | Menyediakan masa tunggu kerja yang dinormalisasi. | Membaca F502/F504/F506 dengan fallback terstruktur, bukan teks experience. |
| `v_analytics_salary_ump` | Satu baris per alumni | Membandingkan gaji dengan UMP serta ambang 1,2 kali UMP. | UMP mengikuti provinsi perusahaan dan tahun UMP terpilih. |
| `v_analytics_work_location` | Satu baris per alumni | Menyediakan provinsi penempatan untuk sebaran lokasi kerja. | Lokasi kerja dibedakan dari domisili pribadi alumni dan head office. |
| `v_analytics_employment_status` | Satu baris per alumni | Menyediakan status aktivitas utama F8 untuk komposisi pekerjaan. | Status disajikan apa adanya; tidak ditebak dari jabatan/experience. |
| `v_analytics_alignment` | Satu baris per alumni dan indikator | Menyediakan F14/F15 untuk keselarasan bidang serta tingkat pendidikan. | Setiap indikator tetap dipisahkan agar label/kategori tidak tercampur. |
| `v_analytics_tracer_answers` | Satu baris per jawaban terbaru alumni-pertanyaan | Menjadi sumber umum indikator tracer F3–F27. | Memilih `MAX(kuesioner_jawaban.id)` per `biodata_id` dan `pertanyaan_id`; tidak mengakses tabel respons legacy. |
| `v_analytics_learning_methods` | Legacy alias | Proyeksi lama F21–F27; service memakai filter kode pada `v_analytics_tracer_answers`. | Alias dipertahankan untuk kompatibilitas database lama. |
| `v_analytics_job_search_start` | Legacy alias | Proyeksi lama F3; service memakai filter kode pada `v_analytics_tracer_answers`. | Alias dipertahankan untuk kompatibilitas database lama. |
| `v_analytics_job_search_methods` | Legacy alias | Proyeksi lama F4; service memakai filter kode pada `v_analytics_tracer_answers`. | Alias dipertahankan untuk kompatibilitas database lama. |
| `v_analytics_application_pipeline` | Legacy alias | Proyeksi lama F6/F7/F7A; service memakai filter kode pada `v_analytics_tracer_answers`. | Alias dipertahankan untuk kompatibilitas database lama. |
| `v_analytics_job_search_activity` | Legacy alias | Proyeksi lama F10; service memakai filter kode pada `v_analytics_tracer_answers`. | Alias dipertahankan untuk kompatibilitas database lama. |
| `v_analytics_workplace_type` | Legacy alias | Proyeksi lama F11; service memakai filter kode pada `v_analytics_tracer_answers`. | Alias dipertahankan untuk kompatibilitas database lama. |
| `v_analytics_further_study` | Legacy alias | Proyeksi lama F12/F18b–F18d; service memakai filter kode pada `v_analytics_tracer_answers`. | Alias dipertahankan untuk kompatibilitas database lama. |
| `v_analytics_mismatch_reasons` | Legacy alias | Proyeksi lama F16; service memakai filter kode pada `v_analytics_tracer_answers`. | Alias dipertahankan untuk kompatibilitas database lama. |
| `v_analytics_competency_gap` | Legacy alias | Proyeksi lama F17a/F17b; service memakai filter kode pada `v_analytics_tracer_answers`. | Alias dipertahankan untuk kompatibilitas database lama. |
| `v_iku_s1_aee` | Satu baris per lulusan akademik S1 | Menjadi populasi AEE hanya untuk data dengan status akademik `Lulus`. | Status aktif, AR, AK, DO, Pindah, dan Cuti tidak masuk; service hanya membandingkan tepat waktu dan terlambat. |
| `v_iku_s1_lulusan` | Satu baris per lulusan S1 | Menjadi basis formula IKU karier berbobot. | Role karier, masa tunggu, gaji, UMP, dan studi lanjut dihitung terstruktur oleh service. |

### Catatan kinerja query IKU

`v_iku_s1_lulusan` mengambil jawaban tracer terbaru per alumni dan kode pertanyaan melalui subquery terurut berdasarkan `id`. Tabel `kuesioner_jawaban` karena itu memiliki indeks komposit `kuesioner_jawaban_analytics_lookup_idx` pada `(biodata_id, responden_type, kode_pertanyaan, id)` agar request grafik IKU tidak melakukan pemindaian berulang pada seluruh jawaban.

## Kontrak sumber data

- Kontak aktif: biodata.nomor_telepon dan biodata.email_pribadi.
- Domisili aktif: biodata.alamat_saat_ini, kelurahan, kecamatan,
  kode_kabupaten, kode_provinsi, dan kode_pos.
- Direktori karier: biodata.prodi_id, perusahaan_id, bidang_pekerjaan, divisi,
  skills, linkedin_url, serta kode wilayah domisili. Nama perusahaan diambil dari
  master `perusahaan`; lokasi yang ditampilkan sebagai lokasi alumni memakai
  domisili biodata, bukan alamat kantor/head office perusahaan.
- Direktori menyediakan filter server-side untuk provinsi lalu kabupaten/kota
  domisili secara bertingkat, head office, skills, bidang, dan unit/divisi. Nilai
  pilihan `Lainnya` disimpan sebagai teks custom pada field kanonik agar nilai
  custom tersebut langsung muncul pada direktori.
- Opsi skills, bidang, unit/divisi, dan head office hanya dibentuk dari nilai
  yang benar-benar tersedia pada alumni dalam scope aktif. Aksi email pengingat
  per alumni tetap memakai flash success/error dari response Inertia.
- Status direktori selalu menampilkan `Univ`, `Prodi`, `Profil`, dan `Atasan`.
  Jika prodi alumni belum memiliki placement kuesioner aktif, status `Prodi`
  ditampilkan sebagai `-` dan tidak dianggap sebagai 100%.
- Tautan LinkedIn pada direktori operasional tetap menjadi aksi sinkronisasi/review.
  Tautan profil langsung hanya diproyeksikan melalui `v_analytics_population` dan
  dipakai pada tabel detail analitik Universitas, Prodi, dan Atasan.
- Identitas akademik: data_akademik.nama, tanggal lahir, NIM, email_pribadi
  sumber akademik, IPK, dan data kelulusan.
- Populasi akademik IKU: `data_akademik.prodi_id`, `angkatan_masuk`,
  `tahun_akademik_masuk`, dan `status_mahasiswa`. Seluruh mahasiswa masuk
  populasi awal; Drop Out, Pindah, dan Cuti dikeluarkan dari denominator AEE.
- Seluruh `prodi` aktif pada baseline ini berjenjang `S1`. Periode masuk reguler
  disimpan dengan format `YYYY/YYYY+1 (Semester Ganjil)`.
- UMP: `ump.besaran` diisi oleh `database/data/ump_2026.csv` melalui
  `UmpSeeder`; nominal pada sumber seed harus berupa angka rupiah tanpa format
  tampilan. Relasi tetap memakai `ump.kode_provinsi` ke master `propinsi`.
- Tugas akhir dan publikasi: yudisium.
- Jawaban universitas dan prodi: `kuesioner_jawaban.jawaban_teks`,
  `jawaban_json`, `pertanyaan_id`, dan `kode_pertanyaan`. Jenis responden
  dibedakan oleh `responden_type` dan konteks evaluasi atasan oleh
  `evaluasi_atasan_id`.
- Cara membaca jawaban ditentukan oleh `kuesioner_pertanyaan.tipe`, bukan oleh
  nama section. Pilihan dihitung per opsi, Likert dihitung dari skor valid,
  jawaban ganda dibaca dari `jawaban_json`, teks ditampilkan sebagai data
  terbuka, dan angka harus di-cast setelah normalisasi. Detail kontrak ada di
  [kuesioner-data-contract.md](kuesioner-data-contract.md).

View tidak menyimpan data bisnis baru. View hanya memproyeksikan data tabel
agar rumus dashboard dan autofill memiliki satu sumber logika.

`skills` tetap disimpan sebagai teks kanonik pada `biodata`. Layanan direktori
menyediakan `skills_list` dengan pemisah koma, titik koma, pipa, atau baris baru;
frasa seperti `Project Management` dipertahankan utuh saat ditampilkan.

## Kelengkapan

is_profile_complete memakai identitas, NIM, telepon, dan email aktif.
v_alumni_tracer_univ_status menghitung butir wajib sesuai skip logic.
Pertanyaan yang tidak berlaku tidak dihitung sebagai kekurangan. Jika prodi
belum memiliki butir, is_complete_prodi bernilai netral dan tidak membuat
profil dianggap kurang lengkap.

## Kontrak schema

Baseline aktif hanya membuat tabel dan view yang digunakan aplikasi. Kontak aktif dan
domisili berada di `biodata`; `data_akademik.email_pribadi` hanya menyimpan snapshot
sumber akademik awal dan tidak menerima perubahan dari `biodata`;
data orang tua, NIK, NPWP, No. KK, dan field pembimbing/penguji tidak dibuat.
Untuk instalasi baru, `php artisan migrate` menjalankan migration tabel dan view,
sedangkan `php artisan db:seed` mengisi data awal.

Database lama bernama tracer bukan koneksi aktif aplikasi dan tidak disentuh.

### IKU S1

`v_iku_s1_aee` menghitung AEE per prodi dengan masa standar S1 delapan semester:
mahasiswa angkatan `YYYY` dianggap tepat waktu jika lulus pada `Genap
YYYY+3/YYYY+4`. `v_iku_s1_lulusan` menjadi sumber klasifikasi capaian karier.
Bobot dihitung oleh `IkuS1AnalyticsService`, bukan oleh Vue; frontend hanya
menampilkan pembilang, penyebut, bobot, dan metodologi.

Untuk klik kategori pada grafik Outcomes, service melakukan read-only enrichment
ke `biodata`, `perusahaan`, `propinsi`, `kabupaten`, dan `atasan`. Enrichment ini
menyediakan nama alumni, perusahaan dan lokasi cabang, atasan, skills, jabatan,
peran wirausaha, serta studi lanjut pada daftar detail tanpa mengubah read-model
atau membuat sumber data duplikat.

Klasifikasi batang AEE (`tepat waktu`, `terlambat`, dan `belum teridentifikasi`)
merupakan agregat pada `IkuS1AnalyticsService` dari kolom view yang sudah ada:
`status_mahasiswa`, `is_excluded`, dan `is_lulus_tepat_waktu`. Tidak ada tabel,
kolom, foreign key, atau relasi ERD tambahan untuk klasifikasi tersebut.
