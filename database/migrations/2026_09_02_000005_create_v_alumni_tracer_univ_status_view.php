<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Membuat database view 'v_alumni_tracer_univ_status' untuk pembacaan data teragregasi aplikasi.
 *
 * Definisi dioptimalkan dengan:
 * 1. Mengeliminasi pemanggilan UPPER() pada kolom terindeks (kode_pertanyaan = 'F8')
 *    agar MySQL dapat memanfaatkan index B-tree kuesioner_jawaban_kode_pertanyaan_idx secara penuh.
 * 2. Menghitung target total pertanyaan wajib satu kali (precomputed single row derived table)
 *    sehingga tidak mengevaluasi 4 correlated subqueries berulang untuk setiap baris alumni.
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
    CASE status_info.status_karier
        WHEN 'bekerja' THEN tc.count_bekerja
        WHEN 'pendidikan' THEN tc.count_pendidikan
        WHEN 'belum_memungkinkan' THEN tc.count_belum
        ELSE tc.count_mencari
    END AS total_mandatory_univ,
    COUNT(DISTINCT CASE
        WHEN sp.wajib = 1 AND q.tipe != 'header'
         AND q.kode_pertanyaan NOT LIKE 'BIO_%'
         AND (sp.tampil_di IS NULL OR LOWER(sp.tampil_di) NOT IN ('profil', 'profile'))
         AND q.kode_pertanyaan NOT IN ('F1','F2A','F2B','F2C','F2D','F5A1','F5A2','F5B','F5C','F5D','F510','F2E','F2E1','F2E2','F2E3','F2F','F2G','F2H','F11','F505','F502','F506')
         AND (
            CASE
                WHEN status_info.status_karier = 'pendidikan' THEN q.kode_pertanyaan NOT IN ('F10','F3','F4','F504','F6','F7','F7A','F14','F15','F16')
                WHEN status_info.status_karier = 'belum_memungkinkan' THEN q.kode_pertanyaan NOT IN ('F10','F3','F4','F504','F6','F7','F7A','F14','F15','F16','F18','F12','F18A','F18B','F18C','F18D')
                WHEN status_info.status_karier = 'mencari_kerja' THEN q.kode_pertanyaan NOT IN ('F504','F14','F15','F16','F18','F12','F18A','F18B','F18C','F18D')
                ELSE q.kode_pertanyaan NOT IN ('F10','F18','F12','F18A','F18B','F18C','F18D')
            END
         )
         AND ((j.jawaban_teks IS NOT NULL AND TRIM(j.jawaban_teks) != '') OR (j.jawaban_json IS NOT NULL AND j.jawaban_json NOT IN ('','[]','{}')))
        THEN q.id
        ELSE NULL
    END)
    + MAX(CASE
        WHEN status_info.status_karier = 'bekerja'
         AND q.kode_pertanyaan IN ('F502','F506')
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
CROSS JOIN (
    SELECT
        (SELECT COUNT(DISTINCT sp.pertanyaan_id)
         FROM kuesioner_section_pertanyaan sp
         INNER JOIN kuesioner_section s ON s.id = sp.section_id
         INNER JOIN kuesioner_pertanyaan q ON q.id = sp.pertanyaan_id
         WHERE s.target = 'univ' AND sp.wajib = 1 AND q.tipe != 'header' AND q.is_active = 1
           AND q.kode_pertanyaan NOT LIKE 'BIO_%'
           AND (sp.tampil_di IS NULL OR LOWER(sp.tampil_di) NOT IN ('profil', 'profile'))
           AND q.kode_pertanyaan NOT IN ('F1','F2A','F2B','F2C','F2D','F5A1','F5A2','F5B','F5C','F5D','F510','F2E','F2E1','F2E2','F2E3','F2F','F2G','F2H','F11','F505','F502','F506')
           AND q.kode_pertanyaan NOT IN ('F10','F18','F12','F18A','F18B','F18C','F18D')
        ) + CASE WHEN EXISTS (
            SELECT 1
            FROM kuesioner_section_pertanyaan sp3
            INNER JOIN kuesioner_section s3 ON s3.id = sp3.section_id
            INNER JOIN kuesioner_pertanyaan q3 ON q3.id = sp3.pertanyaan_id
            WHERE s3.target = 'univ' AND sp3.wajib = 1 AND q3.kode_pertanyaan IN ('F502','F506')
        ) THEN 1 ELSE 0 END AS count_bekerja,

        (SELECT COUNT(DISTINCT sp.pertanyaan_id)
         FROM kuesioner_section_pertanyaan sp
         INNER JOIN kuesioner_section s ON s.id = sp.section_id
         INNER JOIN kuesioner_pertanyaan q ON q.id = sp.pertanyaan_id
         WHERE s.target = 'univ' AND sp.wajib = 1 AND q.tipe != 'header' AND q.is_active = 1
           AND q.kode_pertanyaan NOT LIKE 'BIO_%'
           AND (sp.tampil_di IS NULL OR LOWER(sp.tampil_di) NOT IN ('profil', 'profile'))
           AND q.kode_pertanyaan NOT IN ('F1','F2A','F2B','F2C','F2D','F5A1','F5A2','F5B','F5C','F5D','F510','F2E','F2E1','F2E2','F2E3','F2F','F2G','F2H','F11','F505','F502','F506')
           AND q.kode_pertanyaan NOT IN ('F10','F3','F4','F504','F6','F7','F7A','F14','F15','F16')
        ) AS count_pendidikan,

        (SELECT COUNT(DISTINCT sp.pertanyaan_id)
         FROM kuesioner_section_pertanyaan sp
         INNER JOIN kuesioner_section s ON s.id = sp.section_id
         INNER JOIN kuesioner_pertanyaan q ON q.id = sp.pertanyaan_id
         WHERE s.target = 'univ' AND sp.wajib = 1 AND q.tipe != 'header' AND q.is_active = 1
           AND q.kode_pertanyaan NOT LIKE 'BIO_%'
           AND (sp.tampil_di IS NULL OR LOWER(sp.tampil_di) NOT IN ('profil', 'profile'))
           AND q.kode_pertanyaan NOT IN ('F1','F2A','F2B','F2C','F2D','F5A1','F5A2','F5B','F5C','F5D','F510','F2E','F2E1','F2E2','F2E3','F2F','F2G','F2H','F11','F505','F502','F506')
           AND q.kode_pertanyaan NOT IN ('F10','F3','F4','F504','F6','F7','F7A','F14','F15','F16','F18','F12','F18A','F18B','F18C','F18D')
        ) AS count_belum,

        (SELECT COUNT(DISTINCT sp.pertanyaan_id)
         FROM kuesioner_section_pertanyaan sp
         INNER JOIN kuesioner_section s ON s.id = sp.section_id
         INNER JOIN kuesioner_pertanyaan q ON q.id = sp.pertanyaan_id
         WHERE s.target = 'univ' AND sp.wajib = 1 AND q.tipe != 'header' AND q.is_active = 1
           AND q.kode_pertanyaan NOT LIKE 'BIO_%'
           AND (sp.tampil_di IS NULL OR LOWER(sp.tampil_di) NOT IN ('profil', 'profile'))
           AND q.kode_pertanyaan NOT IN ('F1','F2A','F2B','F2C','F2D','F5A1','F5A2','F5B','F5C','F5D','F510','F2E','F2E1','F2E2','F2E3','F2F','F2G','F2H','F11','F505','F502','F506')
           AND q.kode_pertanyaan NOT IN ('F504','F14','F15','F16','F18','F12','F18A','F18B','F18C','F18D')
        ) AS count_mencari
) tc
LEFT JOIN (
    SELECT
        b_sub.id AS biodata_id,
        CASE
            WHEN b_sub.kategori_pekerjaan LIKE '%Melanjutkan Pendidikan%' OR LOWER(kj8.jawaban_teks) LIKE '%melanjutkan pendidikan%' THEN 'pendidikan'
            WHEN b_sub.kategori_pekerjaan LIKE '%Belum Memungkinkan%' OR b_sub.kategori_pekerjaan = 'Tidak Bekerja' OR LOWER(kj8.jawaban_teks) LIKE '%belum memungkinkan%' THEN 'belum_memungkinkan'
            WHEN b_sub.kategori_pekerjaan LIKE '%Mencari Kerja%' OR LOWER(kj8.jawaban_teks) LIKE '%mencari kerja%' THEN 'mencari_kerja'
            ELSE 'bekerja'
        END AS status_karier
    FROM biodata b_sub
    LEFT JOIN (
        SELECT biodata_id, MAX(jawaban_teks) AS jawaban_teks
        FROM kuesioner_jawaban
        WHERE kode_pertanyaan = 'F8'
        GROUP BY biodata_id
    ) kj8 ON kj8.biodata_id = b_sub.id
) status_info ON status_info.biodata_id = b.id
LEFT JOIN (
    SELECT kj.biodata_id, kj.pertanyaan_id, kj.section_id, kj.jawaban_teks, kj.jawaban_json
    FROM kuesioner_jawaban kj
    INNER JOIN kuesioner_section ks ON ks.id = kj.section_id
    WHERE ks.target = 'univ'
) j ON j.biodata_id = b.id
LEFT JOIN kuesioner_pertanyaan q ON j.pertanyaan_id = q.id
LEFT JOIN kuesioner_section_pertanyaan sp ON sp.pertanyaan_id = q.id
GROUP BY b.id, status_info.status_karier;
SQL
            );
        } else {
            DB::statement(<<<'SQL'
CREATE VIEW `v_alumni_tracer_univ_status` AS
SELECT `b`.`id` AS `biodata_id`,
    CASE `status_info`.`status_karier`
        WHEN 'bekerja' THEN `target_counts`.`count_bekerja`
        WHEN 'pendidikan' THEN `target_counts`.`count_pendidikan`
        WHEN 'belum_memungkinkan' THEN `target_counts`.`count_belum`
        ELSE `target_counts`.`count_mencari`
    END AS `total_mandatory_univ`,
    COUNT(DISTINCT CASE
        WHEN `sp`.`wajib` = 1 AND `q`.`tipe` <> 'header'
         AND `q`.`kode_pertanyaan` NOT LIKE 'BIO_%'
         AND (`sp`.`tampil_di` IS NULL OR LOWER(`sp`.`tampil_di`) NOT IN ('profil', 'profile'))
         AND `q`.`kode_pertanyaan` NOT IN ('F1','F2A','F2B','F2C','F2D','F5A1','F5A2','F5B','F5C','F5D','F510','F2E','F2E1','F2E2','F2E3','F2F','F2G','F2H','F11','F505','F502','F506')
         AND (
            CASE
                WHEN `status_info`.`status_karier` = 'pendidikan' THEN `q`.`kode_pertanyaan` NOT IN ('F10','F3','F4','F504','F6','F7','F7A','F14','F15','F16')
                WHEN `status_info`.`status_karier` = 'belum_memungkinkan' THEN `q`.`kode_pertanyaan` NOT IN ('F10','F3','F4','F504','F6','F7','F7A','F14','F15','F16','F18','F12','F18A','F18B','F18C','F18D')
                WHEN `status_info`.`status_karier` = 'mencari_kerja' THEN `q`.`kode_pertanyaan` NOT IN ('F504','F14','F15','F16','F18','F12','F18A','F18B','F18C','F18D')
                ELSE `q`.`kode_pertanyaan` NOT IN ('F10','F18','F12','F18A','F18B','F18C','F18D')
            END
         )
         AND ((`j`.`jawaban_teks` IS NOT NULL AND TRIM(`j`.`jawaban_teks`) <> '') OR (`j`.`jawaban_json` IS NOT NULL AND `j`.`jawaban_json` NOT IN ('','[]','{}')))
        THEN `q`.`id`
        ELSE NULL
    END)
    + MAX(CASE
        WHEN `status_info`.`status_karier` = 'bekerja'
         AND `q`.`kode_pertanyaan` IN ('F502','F506')
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
JOIN (
    SELECT
        (SELECT COUNT(DISTINCT `sp`.`pertanyaan_id`)
         FROM `kuesioner_section_pertanyaan` `sp`
         INNER JOIN `kuesioner_section` `s` ON `s`.`id` = `sp`.`section_id`
         INNER JOIN `kuesioner_pertanyaan` `q` ON `q`.`id` = `sp`.`pertanyaan_id`
         WHERE `s`.`target` = 'univ' AND `sp`.`wajib` = 1 AND `q`.`tipe` <> 'header' AND `q`.`is_active` = 1
           AND `q`.`kode_pertanyaan` NOT LIKE 'BIO_%'
           AND (`sp`.`tampil_di` IS NULL OR LOWER(`sp`.`tampil_di`) NOT IN ('profil', 'profile'))
           AND `q`.`kode_pertanyaan` NOT IN ('F1','F2A','F2B','F2C','F2D','F5A1','F5A2','F5B','F5C','F5D','F510','F2E','F2E1','F2E2','F2E3','F2F','F2G','F2H','F11','F505','F502','F506')
           AND `q`.`kode_pertanyaan` NOT IN ('F10','F18','F12','F18A','F18B','F18C','F18D')
        ) + (SELECT CASE WHEN EXISTS (
            SELECT 1
            FROM `kuesioner_section_pertanyaan` `sp3`
            INNER JOIN `kuesioner_section` `s3` ON `s3`.`id` = `sp3`.`section_id`
            INNER JOIN `kuesioner_pertanyaan` `q3` ON `q3`.`id` = `sp3`.`pertanyaan_id`
            WHERE `s3`.`target` = 'univ' AND `sp3`.`wajib` = 1 AND `q3`.`kode_pertanyaan` IN ('F502','F506')
        ) THEN 1 ELSE 0 END) AS `count_bekerja`,

        (SELECT COUNT(DISTINCT `sp`.`pertanyaan_id`)
         FROM `kuesioner_section_pertanyaan` `sp`
         INNER JOIN `kuesioner_section` `s` ON `s`.`id` = `sp`.`section_id`
         INNER JOIN `kuesioner_pertanyaan` `q` ON `q`.`id` = `sp`.`pertanyaan_id`
         WHERE `s`.`target` = 'univ' AND `sp`.`wajib` = 1 AND `q`.`tipe` <> 'header' AND `q`.`is_active` = 1
           AND `q`.`kode_pertanyaan` NOT LIKE 'BIO_%'
           AND (`sp`.`tampil_di` IS NULL OR LOWER(`sp`.`tampil_di`) NOT IN ('profil', 'profile'))
           AND `q`.`kode_pertanyaan` NOT IN ('F1','F2A','F2B','F2C','F2D','F5A1','F5A2','F5B','F5C','F5D','F510','F2E','F2E1','F2E2','F2E3','F2F','F2G','F2H','F11','F505','F502','F506')
           AND `q`.`kode_pertanyaan` NOT IN ('F10','F3','F4','F504','F6','F7','F7A','F14','F15','F16')
        ) AS `count_pendidikan`,

        (SELECT COUNT(DISTINCT `sp`.`pertanyaan_id`)
         FROM `kuesioner_section_pertanyaan` `sp`
         INNER JOIN `kuesioner_section` `s` ON `s`.`id` = `sp`.`section_id`
         INNER JOIN `kuesioner_pertanyaan` `q` ON `q`.`id` = `sp`.`pertanyaan_id`
         WHERE `s`.`target` = 'univ' AND `sp`.`wajib` = 1 AND `q`.`tipe` <> 'header' AND `q`.`is_active` = 1
           AND `q`.`kode_pertanyaan` NOT LIKE 'BIO_%'
           AND (`sp`.`tampil_di` IS NULL OR LOWER(`sp`.`tampil_di`) NOT IN ('profil', 'profile'))
           AND `q`.`kode_pertanyaan` NOT IN ('F1','F2A','F2B','F2C','F2D','F5A1','F5A2','F5B','F5C','F5D','F510','F2E','F2E1','F2E2','F2E3','F2F','F2G','F2H','F11','F505','F502','F506')
           AND `q`.`kode_pertanyaan` NOT IN ('F10','F3','F4','F504','F6','F7','F7A','F14','F15','F16','F18','F12','F18A','F18B','F18C','F18D')
        ) AS `count_belum`,

        (SELECT COUNT(DISTINCT `sp`.`pertanyaan_id`)
         FROM `kuesioner_section_pertanyaan` `sp`
         INNER JOIN `kuesioner_section` `s` ON `s`.`id` = `sp`.`section_id`
         INNER JOIN `kuesioner_pertanyaan` `q` ON `q`.`id` = `sp`.`pertanyaan_id`
         WHERE `s`.`target` = 'univ' AND `sp`.`wajib` = 1 AND `q`.`tipe` <> 'header' AND `q`.`is_active` = 1
           AND `q`.`kode_pertanyaan` NOT LIKE 'BIO_%'
           AND (`sp`.`tampil_di` IS NULL OR LOWER(`sp`.`tampil_di`) NOT IN ('profil', 'profile'))
           AND `q`.`kode_pertanyaan` NOT IN ('F1','F2A','F2B','F2C','F2D','F5A1','F5A2','F5B','F5C','F5D','F510','F2E','F2E1','F2E2','F2E3','F2F','F2G','F2H','F11','F505','F502','F506')
           AND `q`.`kode_pertanyaan` NOT IN ('F504','F14','F15','F16','F18','F12','F18A','F18B','F18C','F18D')
        ) AS `count_mencari`
) `target_counts`
LEFT JOIN (
    SELECT
        `b_sub`.`id` AS `biodata_id`,
        CASE
            WHEN `b_sub`.`kategori_pekerjaan` LIKE '%Melanjutkan Pendidikan%' OR LOWER(`kj8`.`jawaban_teks`) LIKE '%melanjutkan pendidikan%' THEN 'pendidikan'
            WHEN `b_sub`.`kategori_pekerjaan` LIKE '%Belum Memungkinkan%' OR `b_sub`.`kategori_pekerjaan` = 'Tidak Bekerja' OR LOWER(`kj8`.`jawaban_teks`) LIKE '%belum memungkinkan%' THEN 'belum_memungkinkan'
            WHEN `b_sub`.`kategori_pekerjaan` LIKE '%Mencari Kerja%' OR LOWER(`kj8`.`jawaban_teks`) LIKE '%mencari kerja%' THEN 'mencari_kerja'
            ELSE 'bekerja'
        END AS `status_karier`
    FROM `biodata` `b_sub`
    LEFT JOIN (
        SELECT `biodata_id`, MAX(`jawaban_teks`) AS `jawaban_teks`
        FROM `kuesioner_jawaban`
        WHERE `kode_pertanyaan` = 'F8'
        GROUP BY `biodata_id`
    ) `kj8` ON `kj8`.`biodata_id` = `b_sub`.`id`
) `status_info` ON `status_info`.`biodata_id` = `b`.`id`
LEFT JOIN (
    SELECT `kj`.`biodata_id`, `kj`.`pertanyaan_id`, `kj`.`section_id`, `kj`.`jawaban_teks`, `kj`.`jawaban_json`
    FROM `kuesioner_jawaban` `kj`
    INNER JOIN `kuesioner_section` `ks` ON `ks`.`id` = `kj`.`section_id`
    WHERE `ks`.`target` = 'univ'
) `j` ON `j`.`biodata_id` = `b`.`id`
LEFT JOIN `kuesioner_pertanyaan` `q` ON `j`.`pertanyaan_id` = `q`.`id`
LEFT JOIN `kuesioner_section_pertanyaan` `sp` ON `sp`.`pertanyaan_id` = `q`.`id`
GROUP BY `b`.`id`, `status_info`.`status_karier`,
    `target_counts`.`count_bekerja`,
    `target_counts`.`count_pendidikan`,
    `target_counts`.`count_belum`,
    `target_counts`.`count_mencari`;
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
