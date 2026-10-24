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
CREATE VIEW v_alumni_tracer_prodi_status AS SELECT b.id AS biodata_id, b.prodi_id,
    COALESCE((SELECT COUNT(*) FROM prodi_question pq WHERE pq.prodi_id = b.prodi_id AND pq.type != 'header'), 0) AS total_prodi_questions,
    COUNT(DISTINCT CASE WHEN ((pr.answer_text IS NOT NULL AND TRIM(pr.answer_text) != '') OR (pr.answer_json IS NOT NULL AND pr.answer_json NOT IN ('','[]','{}'))) THEN pr.prodi_question_id ELSE NULL END) AS answered_prodi_questions
FROM biodata b LEFT JOIN prodi_response pr ON pr.biodata_id = b.id GROUP BY b.id, b.prodi_id;
SQL
            );
        } else {
            DB::statement(<<<'SQL'
CREATE VIEW `v_alumni_tracer_prodi_status` AS select `b`.`id` AS `biodata_id`,`b`.`prodi_id` AS `prodi_id`,coalesce((select count(0) from `prodi_question` `pq` where ((`pq`.`prodi_id` = `b`.`prodi_id`) and (`pq`.`type` <> 'header'))),0) AS `total_prodi_questions`,count(distinct (case when (((`pr`.`answer_text` is not null) and (trim(`pr`.`answer_text`) <> '')) or ((`pr`.`answer_json` is not null) and (`pr`.`answer_json` not in ('','[]','{}')))) then `pr`.`prodi_question_id` else NULL end)) AS `answered_prodi_questions` from (`biodata` `b` left join `prodi_response` `pr` on((`pr`.`biodata_id` = `b`.`id`))) group by `b`.`id`,`b`.`prodi_id`;
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
