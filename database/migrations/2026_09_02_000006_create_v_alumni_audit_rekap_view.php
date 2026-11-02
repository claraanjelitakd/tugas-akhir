<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Membuat view audit rekap dari view profil dan status kuesioner.
 */
return new class extends Migration
{
    /**
     * Membuat view audit yang menambahkan statistik penyelesaian tracer.
     */
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_alumni_audit_rekap');

        DB::statement(<<<'SQL'
CREATE VIEW v_alumni_audit_rekap AS
SELECT ps.*,
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
    }

    /**
     * Menghapus view audit saat migration dibatalkan.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_alumni_audit_rekap');
    }
};
