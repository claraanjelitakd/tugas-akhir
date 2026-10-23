# Database Views

Dokumen ini adalah kontrak view runtime untuk dashboard, audit kelengkapan,
autofill kuesioner, dan pemetaan jawaban. DDL aktif dikelola oleh
database/migrations/2026_10_23_141414_finalize_profile_schema_contract.php.
SQL dari migrasi lama tidak lagi menjadi referensi runtime.

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

## Kontrak sumber data

- Kontak aktif: biodata.nomor_telepon dan biodata.email_pribadi.
- Domisili aktif: biodata.alamat_saat_ini, kelurahan, kecamatan,
  kabupaten_id, propinsi_id, dan kode_pos.
- Identitas akademik: data_akademik.nama, tanggal lahir, NIM, NISN, BPJS,
  email mahasiswa, IPK, dan data kelulusan.
- Tugas akhir dan publikasi: yudisium.
- Jawaban universitas: tracer.answer dan tracer.answer_json, dengan
  question_id sebagai foreign key dan snapshot kode untuk audit.
- Jawaban prodi: prodi_response.answer_text, answer_json, dan
  prodi_question_id.

View tidak menyimpan data bisnis baru. View hanya memproyeksikan data tabel
agar rumus dashboard dan autofill memiliki satu sumber logika.

## Kelengkapan

is_profile_complete memakai identitas, NIM, telepon, dan email aktif.
v_alumni_tracer_univ_status menghitung butir wajib sesuai skip logic.
Pertanyaan yang tidak berlaku tidak dihitung sebagai kekurangan. Jika prodi
belum memiliki butir, is_complete_prodi bernilai netral dan tidak membuat
profil dianggap kurang lengkap.

## Perubahan schema

Migrasi kontrak final melakukan:

1. Menghapus view sementara sebelum perubahan tabel.
2. Menambahkan kelurahan dan kecamatan ke biodata bila instalasi lama belum memilikinya.
3. Menyalin data kontak/domisi lama ke biodata hanya ketika nilai biodata masih kosong.
4. Menghapus kolom duplikat dari data_akademik.
5. Menghapus field sensitif, relasi tabel orang tua, dan field pembimbing/penguji yang memang sudah diputuskan tidak digunakan.
6. Membuat ulang seluruh view dengan sumber kanonik.

Database lama bernama tracer bukan koneksi aktif aplikasi dan tidak disentuh.
