# Database Views

Dokumen ini adalah kontrak view runtime untuk dashboard, audit kelengkapan,
autofill kuesioner, dan pemetaan jawaban. DDL aktif disimpan satu per view di
`database/migrations` agar definisi setiap view mudah ditelusuri.

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
| v_analytics_population | TracerAnalyticsService | Populasi ringan satu baris per alumni dari biodata, akademik, prodi, perusahaan, URL profil LinkedIn, dan provinsi kerja tanpa agregasi kuesioner prodi. |
| v_analytics_response_rate | TracerAnalyticsService | Status minimal satu jawaban wajib kuesioner universitas per alumni; agregasi ini sengaja dipisahkan dari populasi. |
| v_analytics_profile_completion | TracerAnalyticsService | Status kelengkapan profil per alumni. |
| v_analytics_wait_time | TracerAnalyticsService | Nilai waktu tunggu yang sudah dinormalisasi dari F502/F504/F506. |
| v_analytics_salary_ump | TracerAnalyticsService | Gaji, UMP, ambang 1,2 kali UMP, dan status pemenuhan. |
| v_analytics_work_location | TracerAnalyticsService | Provinsi perusahaan tempat alumni bekerja. |
| v_analytics_employment_status | TracerAnalyticsService | Jawaban F8 yang diproyeksikan per alumni. |
| v_analytics_alignment | TracerAnalyticsService | Jawaban F14/F15 yang diproyeksikan per alumni. |
| v_analytics_tracer_answers | TracerAnalyticsService | Jawaban tracer kanonik per alumni dan kode pertanyaan untuk indikator gabungan F3-F27; pencarian memakai indeks lookup pada `kuesioner_jawaban`. |
| v_analytics_learning_methods | TracerAnalyticsService | Jawaban F21-F27 untuk radar, distribusi Likert, dan heatmap metode pembelajaran. |
| v_analytics_job_search_start | TracerAnalyticsService | Jawaban F3 untuk waktu mulai mencari pekerjaan. |
| v_analytics_job_search_methods | TracerAnalyticsService | Jawaban pilihan jamak F4 untuk cara mencari kerja. |
| v_analytics_application_pipeline | TracerAnalyticsService | Nilai F6, F7, dan F7A untuk pipeline lamaran sampai wawancara. |
| v_analytics_job_search_activity | TracerAnalyticsService | Jawaban F10 untuk aktivitas pencarian kerja. |
| v_analytics_workplace_type | TracerAnalyticsService | Jawaban F11 untuk jenis tempat kerja. |
| v_analytics_further_study | TracerAnalyticsService | Jawaban F12, F18b, F18c, dan F18d untuk sumber biaya, perguruan tinggi, program studi, serta tanggal mulai studi lanjut. |
| v_analytics_mismatch_reasons | TracerAnalyticsService | Jawaban pilihan jamak F16 untuk alasan ketidaksesuaian pekerjaan. |
| v_analytics_competency_gap | TracerAnalyticsService | Pasangan F17a/F17b untuk perbandingan kompetensi saat lulus dan kebutuhan kerja. |

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
- Tugas akhir dan publikasi: yudisium.
- Jawaban universitas dan prodi: `kuesioner_jawaban.jawaban_teks`,
  `jawaban_json`, `pertanyaan_id`, dan `kode_pertanyaan`. Jenis responden
  dibedakan oleh `responden_type` dan konteks evaluasi atasan oleh
  `evaluasi_atasan_id`.

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
