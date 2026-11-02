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
CREATE VIEW v_alumni_tracer_univ_status AS SELECT b.id AS biodata_id,
    ((SELECT COUNT(*) FROM ref_subpertanyaan2021 q2 WHERE q2.wajib = 1 AND q2.type != 'header'
     AND UPPER(q2.kode_pertanyaan) NOT IN ('F1','F2A','F2B','F2C','F2D','BIO_TEMPAT_LAHIR','BIO_TANGGAL_LAHIR','BIO_JK','BIO_TGL_LULUS','BIO_JUDUL_TA','F5A1','F5A2','F5B','F5C','F5D','F510','F2E','F2E1','F2E2','F2E3','F2F','F2G','F2H','F11','F505','F502','F506'))
     + CASE WHEN EXISTS (SELECT 1 FROM ref_subpertanyaan2021 q3 WHERE q3.wajib = 1 AND UPPER(q3.kode_pertanyaan) IN ('F502','F506')) THEN 1 ELSE 0 END) AS total_mandatory_univ,
    COUNT(DISTINCT CASE WHEN q.wajib = 1 AND q.type != 'header'
        AND UPPER(q.kode_pertanyaan) NOT IN ('F1','F2A','F2B','F2C','F2D','BIO_TEMPAT_LAHIR','BIO_TANGGAL_LAHIR','BIO_JK','BIO_TGL_LULUS','BIO_JUDUL_TA','F5A1','F5A2','F5B','F5C','F5D','F510','F2E','F2E1','F2E2','F2E3','F2F','F2G','F2H','F11','F505','F502','F506')
        AND ((t.answer IS NOT NULL AND TRIM(t.answer) != '') OR (t.answer_json IS NOT NULL AND t.answer_json NOT IN ('','[]','{}'))) THEN q.id ELSE NULL END)
    + MAX(CASE WHEN UPPER(q.kode_pertanyaan) IN ('F502','F506')
        AND ((t.answer IS NOT NULL AND TRIM(t.answer) != '') OR (t.answer_json IS NOT NULL AND t.answer_json NOT IN ('','[]','{}'))) THEN 1 ELSE 0 END) AS answered_mandatory_univ,
    COUNT(DISTINCT CASE WHEN (t.answer IS NOT NULL AND TRIM(t.answer) != '') OR (t.answer_json IS NOT NULL AND t.answer_json NOT IN ('','[]','{}')) THEN t.question_id ELSE NULL END) AS total_answered_univ
FROM biodata b
LEFT JOIN tracer t ON t.biodata_id = b.id
LEFT JOIN ref_subpertanyaan2021 q ON t.question_id = q.id
GROUP BY b.id;
SQL
            );
        } else {
            DB::statement(<<<'SQL'
CREATE VIEW `v_alumni_tracer_univ_status` AS select `b`.`id` AS `biodata_id`,((select count(0) from `ref_subpertanyaan2021` `q2` where ((`q2`.`wajib` = 1) and (`q2`.`type` <> 'header') and (upper(`q2`.`kode_pertanyaan`) not in ('F1','F2A','F2B','F2C','F2D','BIO_TEMPAT_LAHIR','BIO_TANGGAL_LAHIR','BIO_JK','BIO_TGL_LULUS','BIO_JUDUL_TA','F5A1','F5A2','F5B','F5C','F5D','F510','F2E','F2E1','F2E2','F2E3','F2F','F2G','F2H','F11','F505','F502','F506')))) + (case when exists(select 1 from `ref_subpertanyaan2021` `q3` where ((`q3`.`wajib` = 1) and (upper(`q3`.`kode_pertanyaan`) in ('F502','F506')))) then 1 else 0 end)) AS `total_mandatory_univ`,(count(distinct (case when ((`q`.`wajib` = 1) and (`q`.`type` <> 'header') and (upper(`q`.`kode_pertanyaan`) not in ('F1','F2A','F2B','F2C','F2D','BIO_TEMPAT_LAHIR','BIO_TANGGAL_LAHIR','BIO_JK','BIO_TGL_LULUS','BIO_JUDUL_TA','F5A1','F5A2','F5B','F5C','F5D','F510','F2E','F2E1','F2E2','F2E3','F2F','F2G','F2H','F11','F505','F502','F506')) and (((`t`.`answer` is not null) and (trim(`t`.`answer`) <> '')) or ((`t`.`answer_json` is not null) and (`t`.`answer_json` not in ('','[]','{}'))))) then `q`.`id` else NULL end)) + max((case when ((upper(`q`.`kode_pertanyaan`) in ('F502','F506')) and (((`t`.`answer` is not null) and (trim(`t`.`answer`) <> '')) or ((`t`.`answer_json` is not null) and (`t`.`answer_json` not in ('','[]','{}'))))) then 1 else 0 end))) AS `answered_mandatory_univ`,count(distinct (case when (((`t`.`answer` is not null) and (trim(`t`.`answer`) <> '')) or ((`t`.`answer_json` is not null) and (`t`.`answer_json` not in ('','[]','{}')))) then `t`.`question_id` else NULL end)) AS `total_answered_univ` from ((`biodata` `b` left join `tracer` `t` on((`t`.`biodata_id` = `b`.`id`))) left join `ref_subpertanyaan2021` `q` on((`t`.`question_id` = `q`.`id`))) group by `b`.`id`;
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
