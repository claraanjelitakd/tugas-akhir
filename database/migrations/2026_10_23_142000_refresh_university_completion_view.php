<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menyelaraskan jumlah butir wajib dengan pasangan F502/F506.
 *
 * Pasangan tersebut dihitung sebagai satu butir hanya bila salah satunya
 * aktif pada instrumen, sedangkan jawaban tetap dihitung satu kali.
 */
return new class extends Migration
{
    /**
     * Membuat ulang view status universitas dan view audit turunannya.
     */
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_alumni_audit_rekap');
        DB::statement('DROP VIEW IF EXISTS v_alumni_tracer_univ_status');

        DB::statement(<<<'SQL'
            CREATE VIEW v_alumni_tracer_univ_status AS
            SELECT b.id AS biodata_id,
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
            GROUP BY b.id
            SQL);

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
            LEFT JOIN v_alumni_tracer_prodi_status tps ON ps.biodata_id = tps.biodata_id
            SQL);
    }

    /**
     * View tetap dikelola oleh migrasi final dan tidak dikembalikan ke versi lama.
     */
    public function down(): void
    {
        //
    }
};
