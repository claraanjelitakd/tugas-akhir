<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Membuat database view 'v_alumni_tracer_univ_status' untuk pembacaan data teragregasi aplikasi.
 *
 * Definisi MySQL dan SQLite dipertahankan dalam satu migration karena sintaks
 * fungsi JSON, konkatenasi, dan casting berbeda antar driver database.
 */
return new class extends Migration
{
    /**
     * Membuat view 'v_alumni_tracer_univ_status' setelah tabel sumber tersedia.
     */
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_alumni_tracer_univ_status');

        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement(<<<'SQL'
CREATE VIEW v_alumni_tracer_univ_status AS
SELECT b.id AS biodata_id,
    ((SELECT COUNT(DISTINCT sp2.pertanyaan_id)
      FROM kuesioner_section_pertanyaan sp2
      INNER JOIN kuesioner_section s2 ON s2.id = sp2.section_id
      INNER JOIN kuesioner_pertanyaan q2 ON q2.id = sp2.pertanyaan_id
      WHERE s2.target = 'univ'
        AND sp2.wajib = 1
        AND q2.tipe != 'header'
        AND q2.is_active = 1
        AND UPPER(q2.kode_pertanyaan) NOT IN ('F1','F2A','F2B','F2C','F2D','BIO_TEMPAT_LAHIR','BIO_TANGGAL_LAHIR','BIO_JK','BIO_TGL_LULUS','BIO_JUDUL_TA','F5A1','F5A2','F5B','F5C','F5D','F510','F2E','F2E1','F2E2','F2E3','F2F','F2G','F2H','F11','F505','F502','F506')
     )
     + CASE WHEN EXISTS (
         SELECT 1
         FROM kuesioner_section_pertanyaan sp3
         INNER JOIN kuesioner_section s3 ON s3.id = sp3.section_id
         INNER JOIN kuesioner_pertanyaan q3 ON q3.id = sp3.pertanyaan_id
         WHERE s3.target = 'univ' AND sp3.wajib = 1 AND UPPER(q3.kode_pertanyaan) IN ('F502','F506')
       ) THEN 1 ELSE 0 END
    ) AS total_mandatory_univ,
    COUNT(DISTINCT CASE
        WHEN sp.wajib = 1 AND q.tipe != 'header'
         AND UPPER(q.kode_pertanyaan) NOT IN ('F1','F2A','F2B','F2C','F2D','BIO_TEMPAT_LAHIR','BIO_TANGGAL_LAHIR','BIO_JK','BIO_TGL_LULUS','BIO_JUDUL_TA','F5A1','F5A2','F5B','F5C','F5D','F510','F2E','F2E1','F2E2','F2E3','F2F','F2G','F2H','F11','F505','F502','F506')
         AND ((j.jawaban_teks IS NOT NULL AND TRIM(j.jawaban_teks) != '') OR (j.jawaban_json IS NOT NULL AND j.jawaban_json NOT IN ('','[]','{}')))
        THEN q.id
        ELSE NULL
    END)
    + MAX(CASE
        WHEN UPPER(q.kode_pertanyaan) IN ('F502','F506')
         AND ((j.jawaban_teks IS NOT NULL AND TRIM(j.jawaban_teks) != '') OR (j.jawaban_json IS NOT NULL AND j.jawaban_json NOT IN ('','[]','{}')))
        THEN 1
        ELSE 0
    END) AS answered_mandatory_univ,
    COUNT(DISTINCT CASE
        WHEN ((j.jawaban_teks IS NOT NULL AND TRIM(j.jawaban_teks) != '') OR (j.jawaban_json IS NOT NULL AND j.jawaban_json NOT IN ('','[]','{}')))
        THEN j.pertanyaan_id
        ELSE NULL
    END) AS total_answered_univ
FROM biodata b
LEFT JOIN (
    SELECT kj.biodata_id, kj.pertanyaan_id, kj.section_id, kj.jawaban_teks, kj.jawaban_json
    FROM kuesioner_jawaban kj
    INNER JOIN kuesioner_section ks ON ks.id = kj.section_id
    WHERE ks.target = 'univ'
) j ON j.biodata_id = b.id
LEFT JOIN kuesioner_pertanyaan q ON j.pertanyaan_id = q.id
LEFT JOIN kuesioner_section_pertanyaan sp ON sp.pertanyaan_id = q.id
GROUP BY b.id;
SQL
            );
        } else {
            DB::statement(<<<'SQL'
CREATE VIEW `v_alumni_tracer_univ_status` AS
SELECT `b`.`id` AS `biodata_id`,
    ((SELECT COUNT(DISTINCT `sp2`.`pertanyaan_id`)
      FROM `kuesioner_section_pertanyaan` `sp2`
      INNER JOIN `kuesioner_section` `s2` ON `s2`.`id` = `sp2`.`section_id`
      INNER JOIN `kuesioner_pertanyaan` `q2` ON `q2`.`id` = `sp2`.`pertanyaan_id`
      WHERE `s2`.`target` = 'univ'
        AND `sp2`.`wajib` = 1
        AND `q2`.`tipe` <> 'header'
        AND `q2`.`is_active` = 1
        AND UPPER(`q2`.`kode_pertanyaan`) NOT IN ('F1','F2A','F2B','F2C','F2D','BIO_TEMPAT_LAHIR','BIO_TANGGAL_LAHIR','BIO_JK','BIO_TGL_LULUS','BIO_JUDUL_TA','F5A1','F5A2','F5B','F5C','F5D','F510','F2E','F2E1','F2E2','F2E3','F2F','F2G','F2H','F11','F505','F502','F506')
     )
     + CASE WHEN EXISTS (
         SELECT 1
         FROM `kuesioner_section_pertanyaan` `sp3`
         INNER JOIN `kuesioner_section` `s3` ON `s3`.`id` = `sp3`.`section_id`
         INNER JOIN `kuesioner_pertanyaan` `q3` ON `q3`.`id` = `sp3`.`pertanyaan_id`
         WHERE `s3`.`target` = 'univ' AND `sp3`.`wajib` = 1 AND UPPER(`q3`.`kode_pertanyaan`) IN ('F502','F506')
       ) THEN 1 ELSE 0 END
    ) AS `total_mandatory_univ`,
    COUNT(DISTINCT CASE
        WHEN `sp`.`wajib` = 1 AND `q`.`tipe` <> 'header'
         AND UPPER(`q`.`kode_pertanyaan`) NOT IN ('F1','F2A','F2B','F2C','F2D','BIO_TEMPAT_LAHIR','BIO_TANGGAL_LAHIR','BIO_JK','BIO_TGL_LULUS','BIO_JUDUL_TA','F5A1','F5A2','F5B','F5C','F5D','F510','F2E','F2E1','F2E2','F2E3','F2F','F2G','F2H','F11','F505','F502','F506')
         AND ((`j`.`jawaban_teks` IS NOT NULL AND TRIM(`j`.`jawaban_teks`) <> '') OR (`j`.`jawaban_json` IS NOT NULL AND `j`.`jawaban_json` NOT IN ('','[]','{}')))
        THEN `q`.`id`
        ELSE NULL
    END)
    + MAX(CASE
        WHEN UPPER(`q`.`kode_pertanyaan`) IN ('F502','F506')
         AND ((`j`.`jawaban_teks` IS NOT NULL AND TRIM(`j`.`jawaban_teks`) <> '') OR (`j`.`jawaban_json` IS NOT NULL AND `j`.`jawaban_json` NOT IN ('','[]','{}')))
        THEN 1
        ELSE 0
    END) AS `answered_mandatory_univ`,
    COUNT(DISTINCT CASE
        WHEN ((`j`.`jawaban_teks` IS NOT NULL AND TRIM(`j`.`jawaban_teks`) <> '') OR (`j`.`jawaban_json` IS NOT NULL AND `j`.`jawaban_json` NOT IN ('','[]','{}')))
        THEN `j`.`pertanyaan_id`
        ELSE NULL
    END) AS `total_answered_univ`
FROM `biodata` `b`
LEFT JOIN (
    SELECT `kj`.`biodata_id`, `kj`.`pertanyaan_id`, `kj`.`section_id`, `kj`.`jawaban_teks`, `kj`.`jawaban_json`
    FROM `kuesioner_jawaban` `kj`
    INNER JOIN `kuesioner_section` `ks` ON `ks`.`id` = `kj`.`section_id`
    WHERE `ks`.`target` = 'univ'
) `j` ON `j`.`biodata_id` = `b`.`id`
LEFT JOIN `kuesioner_pertanyaan` `q` ON `j`.`pertanyaan_id` = `q`.`id`
LEFT JOIN `kuesioner_section_pertanyaan` `sp` ON `sp`.`pertanyaan_id` = `q`.`id`
GROUP BY `b`.`id`;
SQL
            );
        }
    }

    /**
     * Menghapus view 'v_alumni_tracer_univ_status' saat migration dibatalkan.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_alumni_tracer_univ_status');
    }
};
