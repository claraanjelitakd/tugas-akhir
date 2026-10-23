<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->recreateAuditView(true);
    }

    public function down(): void
    {
        $this->recreateAuditView(false);
    }

    private function recreateAuditView(bool $emptyProdiIsNotApplicable): void
    {
        DB::statement('DROP VIEW IF EXISTS v_alumni_audit_rekap');

        $prodiPercentageFallback = $emptyProdiIsNotApplicable ? 'NULL' : '100';
        $prodiCompletion = $emptyProdiIsNotApplicable
            ? 'WHEN COALESCE(tps.total_prodi_questions, 0) > 0 AND COALESCE(tps.answered_prodi_questions, 0) >= tps.total_prodi_questions THEN 1 WHEN COALESCE(tps.total_prodi_questions, 0) > 0 THEN 0 ELSE NULL'
            : 'WHEN COALESCE(tps.total_prodi_questions, 0) = 0 OR COALESCE(tps.answered_prodi_questions, 0) >= tps.total_prodi_questions THEN 1 ELSE 0';

        $sql = <<<'SQL'
            CREATE VIEW v_alumni_audit_rekap AS
            SELECT
                ps.*,
                COALESCE(tus.total_mandatory_univ, 0) AS total_mandatory_univ,
                COALESCE(tus.answered_mandatory_univ, 0) AS answered_mandatory_univ,
                COALESCE(tus.total_answered_univ, 0) AS total_answered_univ,
                CASE
                    WHEN COALESCE(tus.total_mandatory_univ, 0) > 0
                    THEN CASE
                        WHEN ROUND((COALESCE(tus.answered_mandatory_univ, 0) * 100.0) / tus.total_mandatory_univ) > 100 THEN 100
                        ELSE ROUND((COALESCE(tus.answered_mandatory_univ, 0) * 100.0) / tus.total_mandatory_univ)
                    END
                    ELSE 100
                END AS univ_percentage,
                CASE
                    WHEN COALESCE(tus.total_mandatory_univ, 0) = 0 OR COALESCE(tus.answered_mandatory_univ, 0) >= tus.total_mandatory_univ
                    THEN 1
                    ELSE 0
                END AS is_complete_univ,
                COALESCE(tps.total_prodi_questions, 0) AS total_prodi_questions,
                COALESCE(tps.answered_prodi_questions, 0) AS answered_prodi_questions,
                CASE
                    WHEN COALESCE(tps.total_prodi_questions, 0) > 0
                    THEN CASE
                        WHEN ROUND((COALESCE(tps.answered_prodi_questions, 0) * 100.0) / tps.total_prodi_questions) > 100 THEN 100
                        ELSE ROUND((COALESCE(tps.answered_prodi_questions, 0) * 100.0) / tps.total_prodi_questions)
                    END
                    ELSE __PRODI_FALLBACK__
                END AS prodi_percentage,
                CASE __PRODI_COMPLETE__ END AS is_complete_prodi,
                CASE
                    WHEN ps.is_profile_complete = 1
                         AND (COALESCE(tus.total_mandatory_univ, 0) = 0 OR COALESCE(tus.answered_mandatory_univ, 0) >= tus.total_mandatory_univ)
                         AND (COALESCE(tps.total_prodi_questions, 0) = 0 OR COALESCE(tps.answered_prodi_questions, 0) >= tps.total_prodi_questions)
                    THEN 1
                    ELSE 0
                END AS is_complete_total,
                CASE
                    WHEN ps.is_profile_complete = 1
                         AND (COALESCE(tus.total_mandatory_univ, 0) = 0 OR COALESCE(tus.answered_mandatory_univ, 0) >= tus.total_mandatory_univ)
                         AND (COALESCE(tps.total_prodi_questions, 0) = 0 OR COALESCE(tps.answered_prodi_questions, 0) >= tps.total_prodi_questions)
                    THEN 'Selesai'
                    ELSE 'Belum Selesai'
                END AS status_tracer_label
            FROM v_alumni_profile_summary ps
            LEFT JOIN v_alumni_tracer_univ_status tus ON ps.biodata_id = tus.biodata_id
            LEFT JOIN v_alumni_tracer_prodi_status tps ON ps.biodata_id = tps.biodata_id
            SQL;

        DB::statement(str_replace(
            ['__PRODI_FALLBACK__', '__PRODI_COMPLETE__'],
            [$prodiPercentageFallback, $prodiCompletion],
            $sql,
        ));
    }
};
