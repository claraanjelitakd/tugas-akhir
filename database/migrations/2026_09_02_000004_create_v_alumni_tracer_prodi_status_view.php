<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Membuat database view 'v_alumni_tracer_prodi_status' untuk pembacaan data teragregasi aplikasi.
 *
 * Definisi MySQL dan SQLite dipertahankan dalam satu migration karena sintaks
 * fungsi JSON, konkatenasi, dan casting berbeda antar driver database.
 */
return new class extends Migration
{
    /**
     * Membuat view 'v_alumni_tracer_prodi_status' setelah tabel sumber tersedia.
     */
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_alumni_tracer_prodi_status');

        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement(<<<'SQL'
CREATE VIEW v_alumni_tracer_prodi_status AS
SELECT b.id AS biodata_id, b.prodi_id,
    COALESCE((
        SELECT COUNT(DISTINCT ksp.pertanyaan_id)
        FROM kuesioner_section_pertanyaan ksp
        INNER JOIN kuesioner_section ks ON ks.id = ksp.section_id
        INNER JOIN kuesioner_pertanyaan kp ON kp.id = ksp.pertanyaan_id
        WHERE ks.target = 'prodi'
          AND ks.prodi_id = b.prodi_id
          AND kp.tipe != 'header'
          AND kp.is_active = 1
    ), 0) AS total_prodi_questions,
    COUNT(DISTINCT CASE
        WHEN ((j.jawaban_teks IS NOT NULL AND TRIM(j.jawaban_teks) != '') OR (j.jawaban_json IS NOT NULL AND j.jawaban_json NOT IN ('','[]','{}')))
        THEN j.pertanyaan_id
        ELSE NULL
    END) AS answered_prodi_questions
FROM biodata b
LEFT JOIN (
    SELECT kj.biodata_id, kj.pertanyaan_id, kj.jawaban_teks, kj.jawaban_json
    FROM kuesioner_jawaban kj
    INNER JOIN kuesioner_section ks ON ks.id = kj.section_id
    WHERE ks.target = 'prodi'
) j ON j.biodata_id = b.id
GROUP BY b.id, b.prodi_id;
SQL
            );
        } else {
            DB::statement(<<<'SQL'
CREATE VIEW `v_alumni_tracer_prodi_status` AS
SELECT `b`.`id` AS `biodata_id`, `b`.`prodi_id` AS `prodi_id`,
    COALESCE((
        SELECT COUNT(DISTINCT `ksp`.`pertanyaan_id`)
        FROM `kuesioner_section_pertanyaan` `ksp`
        INNER JOIN `kuesioner_section` `ks` ON `ks`.`id` = `ksp`.`section_id`
        INNER JOIN `kuesioner_pertanyaan` `kp` ON `kp`.`id` = `ksp`.`pertanyaan_id`
        WHERE `ks`.`target` = 'prodi'
          AND `ks`.`prodi_id` = `b`.`prodi_id`
          AND `kp`.`tipe` <> 'header'
          AND `kp`.`is_active` = 1
    ), 0) AS `total_prodi_questions`,
    COUNT(DISTINCT CASE
        WHEN ((`j`.`jawaban_teks` IS NOT NULL AND TRIM(`j`.`jawaban_teks`) <> '') OR (`j`.`jawaban_json` IS NOT NULL AND `j`.`jawaban_json` NOT IN ('','[]','{}')))
        THEN `j`.`pertanyaan_id`
        ELSE NULL
    END) AS `answered_prodi_questions`
FROM `biodata` `b`
LEFT JOIN (
    SELECT `kj`.`biodata_id`, `kj`.`pertanyaan_id`, `kj`.`jawaban_teks`, `kj`.`jawaban_json`
    FROM `kuesioner_jawaban` `kj`
    INNER JOIN `kuesioner_section` `ks` ON `ks`.`id` = `kj`.`section_id`
    WHERE `ks`.`target` = 'prodi'
) `j` ON `j`.`biodata_id` = `b`.`id`
GROUP BY `b`.`id`, `b`.`prodi_id`;
SQL
            );
        }
    }

    /**
     * Menghapus view 'v_alumni_tracer_prodi_status' saat migration dibatalkan.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_alumni_tracer_prodi_status');
    }
};
