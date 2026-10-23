<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->recreateViews(true);
    }

    public function down(): void
    {
        $this->recreateViews(false);
    }

    private function recreateViews(bool $useDynamicMandatoryCount): void
    {
        DB::statement('DROP VIEW IF EXISTS v_alumni_audit_rekap');
        DB::statement('DROP VIEW IF EXISTS v_alumni_tracer_univ_status');

        $totalMandatoryExpression = $useDynamicMandatoryCount
            ? <<<'SQL'
                CASE
                    WHEN MAX(CASE WHEN UPPER(q.kode_pertanyaan) = 'F8' THEN LOWER(t.answer) ELSE '' END) LIKE '%melanjutkan pendidikan%'
                         OR MAX(CASE WHEN UPPER(q.kode_pertanyaan) = 'F8' THEN t.answer ELSE '' END) = '4'
                         OR MAX(CASE WHEN UPPER(q.kode_pertanyaan) = 'F8' THEN LOWER(t.answer) ELSE '' END) LIKE '%belum memungkinkan%'
                         OR MAX(CASE WHEN UPPER(q.kode_pertanyaan) = 'F8' THEN t.answer ELSE '' END) = '2'
                    THEN (
                        SELECT COUNT(*)
                        FROM ref_subpertanyaan2021 q2
                        WHERE q2.wajib = 1
                          AND q2.type != 'header'
                          AND UPPER(q2.kode_pertanyaan) NOT IN (
                              'F1', 'F2A', 'F2B', 'F2C', 'F2D',
                              'BIO_TEMPAT_LAHIR', 'BIO_TANGGAL_LAHIR', 'BIO_JK', 'BIO_TGL_LULUS', 'BIO_JUDUL_TA', 'BIO_NIK', 'BIO_NPWP',
                              'F5A1', 'F5A2', 'F510', 'F5B', 'F5C', 'F5D',
                              'F2E', 'F2E1', 'F2E2', 'F2E3', 'F2F', 'F2G', 'F2H',
                              'F11', 'F505', 'F502', 'F506', 'F504', 'F14', 'F15'
                          )
                    )
                    ELSE (
                        SELECT COUNT(*)
                        FROM ref_subpertanyaan2021 q2
                        WHERE q2.wajib = 1
                          AND q2.type != 'header'
                          AND UPPER(q2.kode_pertanyaan) NOT IN (
                              'F1', 'F2A', 'F2B', 'F2C', 'F2D',
                              'BIO_TEMPAT_LAHIR', 'BIO_TANGGAL_LAHIR', 'BIO_JK', 'BIO_TGL_LULUS', 'BIO_JUDUL_TA', 'BIO_NIK', 'BIO_NPWP',
                              'F5A1', 'F5A2', 'F510', 'F5B', 'F5C', 'F5D',
                              'F2E', 'F2E1', 'F2E2', 'F2E3', 'F2F', 'F2G', 'F2H',
                              'F11', 'F505', 'F502', 'F506'
                          )
                    )
                    + CASE
                        WHEN EXISTS (
                            SELECT 1
                            FROM ref_subpertanyaan2021 q3
                            WHERE q3.wajib = 1
                              AND q3.type != 'header'
                              AND UPPER(q3.kode_pertanyaan) IN ('F502', 'F506')
                        ) THEN 1
                        ELSE 0
                    END
                END
                SQL
            : <<<'SQL'
                CASE
                    WHEN MAX(CASE WHEN q.kode_pertanyaan = 'F8' THEN LOWER(t.answer) ELSE '' END) LIKE '%melanjutkan pendidikan%'
                         OR MAX(CASE WHEN q.kode_pertanyaan = 'F8' THEN t.answer ELSE '' END) = '4'
                         OR MAX(CASE WHEN q.kode_pertanyaan = 'F8' THEN LOWER(t.answer) ELSE '' END) LIKE '%belum memungkinkan%'
                         OR MAX(CASE WHEN q.kode_pertanyaan = 'F8' THEN t.answer ELSE '' END) = '2'
                    THEN 16
                    ELSE 20
                END
                SQL;

        $statusView = <<<'SQL'
            CREATE VIEW v_alumni_tracer_univ_status AS
            SELECT
                b.id AS biodata_id,
                __TOTAL_MANDATORY__ AS total_mandatory_univ,
                (
                    COUNT(DISTINCT CASE
                        WHEN q.wajib = 1
                             AND q.type != 'header'
                             AND UPPER(q.kode_pertanyaan) NOT IN (
                                 'F1', 'F2A', 'F2B', 'F2C', 'F2D',
                                 'BIO_TEMPAT_LAHIR', 'BIO_TANGGAL_LAHIR', 'BIO_JK', 'BIO_TGL_LULUS', 'BIO_JUDUL_TA', 'BIO_NIK', 'BIO_NPWP',
                                 'F5A1', 'F5A2', 'F510', 'F5B', 'F5C', 'F5D',
                                 'F2E', 'F2E1', 'F2E2', 'F2E3', 'F2F', 'F2G', 'F2H',
                                 'F11', 'F505', 'F502', 'F506'
                             )
                             AND (
                                 (t.answer IS NOT NULL AND TRIM(t.answer) != '')
                                 OR (t.answer_json IS NOT NULL AND t.answer_json != '' AND t.answer_json != '[]' AND t.answer_json != '{}')
                             )
                        THEN q.id
                        ELSE NULL
                    END)
                    + MAX(CASE
                        WHEN UPPER(q.kode_pertanyaan) IN ('F502', 'F506')
                             AND (
                                 (t.answer IS NOT NULL AND TRIM(t.answer) != '')
                                 OR (t.answer_json IS NOT NULL AND t.answer_json != '' AND t.answer_json != '[]' AND t.answer_json != '{}')
                             )
                        THEN 1
                        ELSE 0
                    END)
                ) AS answered_mandatory_univ,
                COUNT(DISTINCT CASE
                    WHEN (
                        (t.answer IS NOT NULL AND TRIM(t.answer) != '')
                        OR (t.answer_json IS NOT NULL AND t.answer_json != '' AND t.answer_json != '[]' AND t.answer_json != '{}')
                    )
                    THEN t.question_id
                    ELSE NULL
                END) AS total_answered_univ
            FROM biodata b
            LEFT JOIN tracer t ON t.biodata_id = b.id
            LEFT JOIN ref_subpertanyaan2021 q ON t.question_id = q.id
            GROUP BY b.id
            SQL;

        DB::statement(str_replace('__TOTAL_MANDATORY__', $totalMandatoryExpression, $statusView));
        $this->recreateAuditView();
    }

    private function recreateAuditView(): void
    {
        DB::statement(<<<'SQL'
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
                    ELSE 0
                END AS univ_percentage,
                CASE
                    WHEN COALESCE(tus.total_mandatory_univ, 0) > 0 AND COALESCE(tus.answered_mandatory_univ, 0) >= tus.total_mandatory_univ
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
                    ELSE NULL
                END AS prodi_percentage,
                CASE
                    WHEN COALESCE(tps.total_prodi_questions, 0) > 0 AND COALESCE(tps.answered_prodi_questions, 0) >= tps.total_prodi_questions THEN 1
                    WHEN COALESCE(tps.total_prodi_questions, 0) > 0 THEN 0
                    ELSE NULL
                END AS is_complete_prodi,
                CASE
                    WHEN ps.is_profile_complete = 1
                         AND (COALESCE(tus.total_mandatory_univ, 0) > 0 AND COALESCE(tus.answered_mandatory_univ, 0) >= tus.total_mandatory_univ)
                         AND (COALESCE(tps.total_prodi_questions, 0) = 0 OR COALESCE(tps.answered_prodi_questions, 0) >= tps.total_prodi_questions)
                    THEN 1
                    ELSE 0
                END AS is_complete_total,
                CASE
                    WHEN ps.is_profile_complete = 1
                         AND (COALESCE(tus.total_mandatory_univ, 0) > 0 AND COALESCE(tus.answered_mandatory_univ, 0) >= tus.total_mandatory_univ)
                         AND (COALESCE(tps.total_prodi_questions, 0) = 0 OR COALESCE(tps.answered_prodi_questions, 0) >= tps.total_prodi_questions)
                    THEN 'Selesai'
                    ELSE 'Belum Selesai'
                END AS status_tracer_label
            FROM v_alumni_profile_summary ps
            LEFT JOIN v_alumni_tracer_univ_status tus ON ps.biodata_id = tus.biodata_id
            LEFT JOIN v_alumni_tracer_prodi_status tps ON ps.biodata_id = tps.biodata_id
            SQL);
    }
};
