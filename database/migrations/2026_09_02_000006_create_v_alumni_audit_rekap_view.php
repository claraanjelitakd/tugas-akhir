<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Membuat database view 'v_alumni_audit_rekap' untuk pembacaan data teragregasi aplikasi.
 *
 * Definisi MySQL dan SQLite dipertahankan dalam satu migration karena sintaks
 * fungsi JSON, konkatenasi, dan casting berbeda antar driver database.
 */
return new class extends Migration
{
    /**
     * Membuat view 'v_alumni_audit_rekap' setelah tabel sumber tersedia.
     */
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_alumni_audit_rekap');

        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement(<<<'SQL'
CREATE VIEW v_alumni_audit_rekap AS SELECT ps.*,
    COALESCE(tus.total_mandatory_univ, 0) AS total_mandatory_univ,
    COALESCE(tus.answered_mandatory_univ, 0) AS answered_mandatory_univ,
    COALESCE(tus.total_answered_univ, 0) AS total_answered_univ,
    CASE WHEN COALESCE(tus.total_mandatory_univ, 0) > 0 THEN ROUND((COALESCE(tus.answered_mandatory_univ, 0) * 100.0) / tus.total_mandatory_univ) ELSE 0 END AS univ_percentage,
    CASE WHEN COALESCE(tus.total_mandatory_univ, 0) > 0 AND COALESCE(tus.answered_mandatory_univ, 0) >= tus.total_mandatory_univ THEN 1 ELSE 0 END AS is_complete_univ,
    COALESCE(tps.total_prodi_questions, 0) AS total_prodi_questions,
    COALESCE(tps.answered_prodi_questions, 0) AS answered_prodi_questions,
    CASE WHEN COALESCE(tps.total_prodi_questions, 0) > 0 THEN ROUND((COALESCE(tps.answered_prodi_questions, 0) * 100.0) / tps.total_prodi_questions) ELSE NULL END AS prodi_percentage,
    CASE WHEN COALESCE(tps.total_prodi_questions, 0) > 0 AND COALESCE(tps.answered_prodi_questions, 0) >= tps.total_prodi_questions THEN 1 WHEN COALESCE(tps.total_prodi_questions, 0) > 0 THEN 0 ELSE NULL END AS is_complete_prodi,
    CASE WHEN ps.is_profile_complete = 1 AND COALESCE(tus.total_mandatory_univ, 0) > 0 AND COALESCE(tus.answered_mandatory_univ, 0) >= tus.total_mandatory_univ AND (COALESCE(tps.total_prodi_questions, 0) = 0 OR COALESCE(tps.answered_prodi_questions, 0) >= tps.total_prodi_questions) THEN 1 ELSE 0 END AS is_complete_total,
    CASE WHEN ps.is_profile_complete = 1 AND COALESCE(tus.total_mandatory_univ, 0) > 0 AND COALESCE(tus.answered_mandatory_univ, 0) >= tus.total_mandatory_univ AND (COALESCE(tps.total_prodi_questions, 0) = 0 OR COALESCE(tps.answered_prodi_questions, 0) >= tps.total_prodi_questions) THEN 'Selesai' ELSE 'Belum Selesai' END AS status_tracer_label
FROM v_alumni_profile_summary ps
LEFT JOIN v_alumni_tracer_univ_status tus ON ps.biodata_id = tus.biodata_id
LEFT JOIN v_alumni_tracer_prodi_status tps ON ps.biodata_id = tps.biodata_id;
SQL
            );
        } else {
            DB::statement(<<<'SQL'
CREATE VIEW `v_alumni_audit_rekap` AS select `ps`.`biodata_id` AS `biodata_id`,`ps`.`user_id` AS `user_id`,`ps`.`nim` AS `nim`,`ps`.`nama` AS `nama`,`ps`.`email` AS `email`,`ps`.`email_pribadi` AS `email_pribadi`,`ps`.`email_students` AS `email_students`,`ps`.`nomor_telepon` AS `nomor_telepon`,`ps`.`tempat_lahir` AS `tempat_lahir`,`ps`.`tanggal_lahir` AS `tanggal_lahir`,`ps`.`jenis_kelamin` AS `jenis_kelamin`,`ps`.`warga_negara` AS `warga_negara`,`ps`.`alamat` AS `alamat`,`ps`.`kelurahan` AS `kelurahan`,`ps`.`kecamatan` AS `kecamatan`,`ps`.`kode_pos` AS `kode_pos`,`ps`.`propinsi_id` AS `propinsi_id`,`ps`.`nama_provinsi` AS `nama_provinsi`,`ps`.`kabupaten_id` AS `kabupaten_id`,`ps`.`nama_kabupaten` AS `nama_kabupaten`,`ps`.`instagram_url` AS `instagram_url`,`ps`.`facebook_url` AS `facebook_url`,`ps`.`linkedin_url` AS `linkedin_url`,`ps`.`linkedin_username` AS `linkedin_username`,`ps`.`skills` AS `skills`,`ps`.`experience` AS `experience`,`ps`.`prodi_id` AS `prodi_id`,`ps`.`kode_prodi` AS `kode_prodi`,`ps`.`nama_prodi` AS `nama_prodi`,`ps`.`fakultas_id` AS `fakultas_id`,`ps`.`kode_fakultas` AS `kode_fakultas`,`ps`.`nama_fakultas` AS `nama_fakultas`,`ps`.`singkatan_fakultas` AS `singkatan_fakultas`,`ps`.`angkatan_masuk` AS `angkatan_masuk`,`ps`.`tahun_lulus` AS `tahun_lulus`,`ps`.`tanggal_lulus` AS `tanggal_lulus`,`ps`.`tahun_akademik_lulus` AS `tahun_akademik_lulus`,`ps`.`ipk` AS `ipk`,`ps`.`total_sks` AS `total_sks`,`ps`.`total_angka_kualitas` AS `total_angka_kualitas`,`ps`.`status_mahasiswa` AS `status_mahasiswa`,`ps`.`asal_sekolah` AS `asal_sekolah`,`ps`.`alamat_asal_sekolah` AS `alamat_asal_sekolah`,`ps`.`kota_kabupaten_asal_sekolah` AS `kota_kabupaten_asal_sekolah`,`ps`.`provinsi_asal_sekolah` AS `provinsi_asal_sekolah`,`ps`.`jurusan_asal_sekolah` AS `jurusan_asal_sekolah`,`ps`.`judul_ta` AS `judul_ta`,`ps`.`judul_ta_inggris` AS `judul_ta_inggris`,`ps`.`url_publikasi` AS `url_publikasi`,`ps`.`jenis_publikasi` AS `jenis_publikasi`,`ps`.`status_publikasi` AS `status_publikasi`,`ps`.`keterangan_hasil_yudisium` AS `keterangan_hasil_yudisium`,`ps`.`status_yudisium` AS `status_yudisium`,`ps`.`kategori_pekerjaan` AS `kategori_pekerjaan`,`ps`.`posisi_jabatan` AS `posisi_jabatan`,`ps`.`posisi_wiraswasta` AS `posisi_wiraswasta`,`ps`.`pendidikan_tingkat` AS `pendidikan_tingkat`,`ps`.`perguruan_tinggi` AS `perguruan_tinggi`,`ps`.`pendidikan_prodi` AS `pendidikan_prodi`,`ps`.`gaji` AS `gaji`,`ps`.`jenis_pekerjaan` AS `jenis_pekerjaan`,`ps`.`perusahaan_id` AS `perusahaan_id`,`ps`.`nama_perusahaan` AS `nama_perusahaan`,`ps`.`alamat_perusahaan` AS `alamat_perusahaan`,`ps`.`sektor_perusahaan` AS `sektor_perusahaan`,`ps`.`skala_perusahaan` AS `skala_perusahaan`,`ps`.`jenis_perusahaan` AS `jenis_perusahaan`,`ps`.`jenis_lokasi_perusahaan` AS `jenis_lokasi_perusahaan`,`ps`.`negara_perusahaan` AS `negara_perusahaan`,`ps`.`nama_atasan` AS `nama_atasan`,`ps`.`email_atasan` AS `email_atasan`,`ps`.`telepon_atasan` AS `telepon_atasan`,`ps`.`is_profile_complete` AS `is_profile_complete`,coalesce(`tus`.`total_mandatory_univ`,0) AS `total_mandatory_univ`,coalesce(`tus`.`answered_mandatory_univ`,0) AS `answered_mandatory_univ`,coalesce(`tus`.`total_answered_univ`,0) AS `total_answered_univ`,(case when (coalesce(`tus`.`total_mandatory_univ`,0) > 0) then round(((coalesce(`tus`.`answered_mandatory_univ`,0) * 100.0) / `tus`.`total_mandatory_univ`),0) else 0 end) AS `univ_percentage`,(case when ((coalesce(`tus`.`total_mandatory_univ`,0) > 0) and (coalesce(`tus`.`answered_mandatory_univ`,0) >= `tus`.`total_mandatory_univ`)) then 1 else 0 end) AS `is_complete_univ`,coalesce(`tps`.`total_prodi_questions`,0) AS `total_prodi_questions`,coalesce(`tps`.`answered_prodi_questions`,0) AS `answered_prodi_questions`,(case when (coalesce(`tps`.`total_prodi_questions`,0) > 0) then round(((coalesce(`tps`.`answered_prodi_questions`,0) * 100.0) / `tps`.`total_prodi_questions`),0) else NULL end) AS `prodi_percentage`,(case when ((coalesce(`tps`.`total_prodi_questions`,0) > 0) and (coalesce(`tps`.`answered_prodi_questions`,0) >= `tps`.`total_prodi_questions`)) then 1 when (coalesce(`tps`.`total_prodi_questions`,0) > 0) then 0 else NULL end) AS `is_complete_prodi`,(case when ((`ps`.`is_profile_complete` = 1) and (coalesce(`tus`.`total_mandatory_univ`,0) > 0) and (coalesce(`tus`.`answered_mandatory_univ`,0) >= `tus`.`total_mandatory_univ`) and ((coalesce(`tps`.`total_prodi_questions`,0) = 0) or (coalesce(`tps`.`answered_prodi_questions`,0) >= `tps`.`total_prodi_questions`))) then 1 else 0 end) AS `is_complete_total`,(case when ((`ps`.`is_profile_complete` = 1) and (coalesce(`tus`.`total_mandatory_univ`,0) > 0) and (coalesce(`tus`.`answered_mandatory_univ`,0) >= `tus`.`total_mandatory_univ`) and ((coalesce(`tps`.`total_prodi_questions`,0) = 0) or (coalesce(`tps`.`answered_prodi_questions`,0) >= `tps`.`total_prodi_questions`))) then 'Selesai' else 'Belum Selesai' end) AS `status_tracer_label` from ((`v_alumni_profile_summary` `ps` left join `v_alumni_tracer_univ_status` `tus` on((`ps`.`biodata_id` = `tus`.`biodata_id`))) left join `v_alumni_tracer_prodi_status` `tps` on((`ps`.`biodata_id` = `tps`.`biodata_id`)));
SQL
            );
        }
    }

    /**
     * Menghapus view 'v_alumni_audit_rekap' saat migration dibatalkan.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_alumni_audit_rekap');
    }
};
