<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Membuat database view 'v_question_mappings' untuk pembacaan data teragregasi aplikasi.
 *
 * Definisi MySQL dan SQLite dipertahankan dalam satu migration karena sintaks
 * fungsi JSON, konkatenasi, dan casting berbeda antar driver database.
 */
return new class extends Migration
{
    /**
     * Membuat view 'v_question_mappings' setelah tabel sumber tersedia.
     */
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_question_mappings');

        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement(<<<'SQL'
CREATE VIEW v_question_mappings AS SELECT 1 AS id, 'data_akademik' AS table_name, 'nim' AS column_name, q.id AS question_id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'F1'
UNION ALL SELECT 2, 'data_akademik', 'nama', q.id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'F2A'
UNION ALL SELECT 3, 'biodata', 'nomor_telepon', q.id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'F2B'
UNION ALL SELECT 4, 'biodata', 'email_pribadi', q.id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'F2C'
UNION ALL SELECT 5, 'biodata', 'alamat_saat_ini', q.id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'F2D'
UNION ALL SELECT 6, 'data_akademik', 'tempat_lahir', q.id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'BIO_TEMPAT_LAHIR'
UNION ALL SELECT 7, 'data_akademik', 'tanggal_lahir', q.id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'BIO_TANGGAL_LAHIR'
UNION ALL SELECT 8, 'data_akademik', 'jenis_kelamin', q.id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'BIO_JK'
UNION ALL SELECT 9, 'data_akademik', 'tanggal_lulus', q.id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'BIO_TGL_LULUS'
UNION ALL SELECT 10, 'yudisium', 'judul_ta', q.id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'BIO_JUDUL_TA'
UNION ALL SELECT 11, 'perusahaan', 'nama_perusahaan', q.id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'F2E'
UNION ALL SELECT 12, 'atasan', 'nama', q.id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'F2E1'
UNION ALL SELECT 13, 'atasan', 'telepon', q.id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'F2E2'
UNION ALL SELECT 14, 'atasan', 'email', q.id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'F2E3'
UNION ALL SELECT 15, 'perusahaan', 'alamat', q.id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'F2F'
UNION ALL SELECT 16, 'biodata', 'posisi_jabatan', q.id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'F2G'
UNION ALL SELECT 17, 'perusahaan', 'skala', q.id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'F2H'
UNION ALL SELECT 18, 'perusahaan', 'nama_perusahaan', q.id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'F5B'
UNION ALL SELECT 19, 'biodata', 'posisi_jabatan', q.id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'F5C'
UNION ALL SELECT 20, 'perusahaan', 'skala', q.id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'F5D'
UNION ALL SELECT 21, 'perusahaan', 'alamat', q.id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'F510';
SQL
            );
        } else {
            DB::statement(<<<'SQL'
CREATE VIEW `v_question_mappings` AS select 1 AS `id`,'data_akademik' AS `table_name`,'nim' AS `column_name`,`q`.`id` AS `question_id`,`q`.`kode_pertanyaan` AS `kode_pertanyaan` from `ref_subpertanyaan2021` `q` where (`q`.`kode_pertanyaan` = 'F1') union all select 2 AS `2`,'data_akademik' AS `data_akademik`,'nama' AS `nama`,`q`.`id` AS `id`,`q`.`kode_pertanyaan` AS `kode_pertanyaan` from `ref_subpertanyaan2021` `q` where (`q`.`kode_pertanyaan` = 'F2A') union all select 3 AS `3`,'biodata' AS `biodata`,'nomor_telepon' AS `nomor_telepon`,`q`.`id` AS `id`,`q`.`kode_pertanyaan` AS `kode_pertanyaan` from `ref_subpertanyaan2021` `q` where (`q`.`kode_pertanyaan` = 'F2B') union all select 4 AS `4`,'biodata' AS `biodata`,'email_pribadi' AS `email_pribadi`,`q`.`id` AS `id`,`q`.`kode_pertanyaan` AS `kode_pertanyaan` from `ref_subpertanyaan2021` `q` where (`q`.`kode_pertanyaan` = 'F2C') union all select 5 AS `5`,'biodata' AS `biodata`,'alamat_saat_ini' AS `alamat_saat_ini`,`q`.`id` AS `id`,`q`.`kode_pertanyaan` AS `kode_pertanyaan` from `ref_subpertanyaan2021` `q` where (`q`.`kode_pertanyaan` = 'F2D') union all select 6 AS `6`,'data_akademik' AS `data_akademik`,'tempat_lahir' AS `tempat_lahir`,`q`.`id` AS `id`,`q`.`kode_pertanyaan` AS `kode_pertanyaan` from `ref_subpertanyaan2021` `q` where (`q`.`kode_pertanyaan` = 'BIO_TEMPAT_LAHIR') union all select 7 AS `7`,'data_akademik' AS `data_akademik`,'tanggal_lahir' AS `tanggal_lahir`,`q`.`id` AS `id`,`q`.`kode_pertanyaan` AS `kode_pertanyaan` from `ref_subpertanyaan2021` `q` where (`q`.`kode_pertanyaan` = 'BIO_TANGGAL_LAHIR') union all select 8 AS `8`,'data_akademik' AS `data_akademik`,'jenis_kelamin' AS `jenis_kelamin`,`q`.`id` AS `id`,`q`.`kode_pertanyaan` AS `kode_pertanyaan` from `ref_subpertanyaan2021` `q` where (`q`.`kode_pertanyaan` = 'BIO_JK') union all select 9 AS `9`,'data_akademik' AS `data_akademik`,'tanggal_lulus' AS `tanggal_lulus`,`q`.`id` AS `id`,`q`.`kode_pertanyaan` AS `kode_pertanyaan` from `ref_subpertanyaan2021` `q` where (`q`.`kode_pertanyaan` = 'BIO_TGL_LULUS') union all select 10 AS `10`,'yudisium' AS `yudisium`,'judul_ta' AS `judul_ta`,`q`.`id` AS `id`,`q`.`kode_pertanyaan` AS `kode_pertanyaan` from `ref_subpertanyaan2021` `q` where (`q`.`kode_pertanyaan` = 'BIO_JUDUL_TA') union all select 11 AS `11`,'perusahaan' AS `perusahaan`,'nama_perusahaan' AS `nama_perusahaan`,`q`.`id` AS `id`,`q`.`kode_pertanyaan` AS `kode_pertanyaan` from `ref_subpertanyaan2021` `q` where (`q`.`kode_pertanyaan` = 'F2E') union all select 12 AS `12`,'atasan' AS `atasan`,'nama' AS `nama`,`q`.`id` AS `id`,`q`.`kode_pertanyaan` AS `kode_pertanyaan` from `ref_subpertanyaan2021` `q` where (`q`.`kode_pertanyaan` = 'F2E1') union all select 13 AS `13`,'atasan' AS `atasan`,'telepon' AS `telepon`,`q`.`id` AS `id`,`q`.`kode_pertanyaan` AS `kode_pertanyaan` from `ref_subpertanyaan2021` `q` where (`q`.`kode_pertanyaan` = 'F2E2') union all select 14 AS `14`,'atasan' AS `atasan`,'email' AS `email`,`q`.`id` AS `id`,`q`.`kode_pertanyaan` AS `kode_pertanyaan` from `ref_subpertanyaan2021` `q` where (`q`.`kode_pertanyaan` = 'F2E3') union all select 15 AS `15`,'perusahaan' AS `perusahaan`,'alamat' AS `alamat`,`q`.`id` AS `id`,`q`.`kode_pertanyaan` AS `kode_pertanyaan` from `ref_subpertanyaan2021` `q` where (`q`.`kode_pertanyaan` = 'F2F') union all select 16 AS `16`,'biodata' AS `biodata`,'posisi_jabatan' AS `posisi_jabatan`,`q`.`id` AS `id`,`q`.`kode_pertanyaan` AS `kode_pertanyaan` from `ref_subpertanyaan2021` `q` where (`q`.`kode_pertanyaan` = 'F2G') union all select 17 AS `17`,'perusahaan' AS `perusahaan`,'skala' AS `skala`,`q`.`id` AS `id`,`q`.`kode_pertanyaan` AS `kode_pertanyaan` from `ref_subpertanyaan2021` `q` where (`q`.`kode_pertanyaan` = 'F2H') union all select 18 AS `18`,'perusahaan' AS `perusahaan`,'nama_perusahaan' AS `nama_perusahaan`,`q`.`id` AS `id`,`q`.`kode_pertanyaan` AS `kode_pertanyaan` from `ref_subpertanyaan2021` `q` where (`q`.`kode_pertanyaan` = 'F5B') union all select 19 AS `19`,'biodata' AS `biodata`,'posisi_jabatan' AS `posisi_jabatan`,`q`.`id` AS `id`,`q`.`kode_pertanyaan` AS `kode_pertanyaan` from `ref_subpertanyaan2021` `q` where (`q`.`kode_pertanyaan` = 'F5C') union all select 20 AS `20`,'perusahaan' AS `perusahaan`,'skala' AS `skala`,`q`.`id` AS `id`,`q`.`kode_pertanyaan` AS `kode_pertanyaan` from `ref_subpertanyaan2021` `q` where (`q`.`kode_pertanyaan` = 'F5D') union all select 21 AS `21`,'perusahaan' AS `perusahaan`,'alamat' AS `alamat`,`q`.`id` AS `id`,`q`.`kode_pertanyaan` AS `kode_pertanyaan` from `ref_subpertanyaan2021` `q` where (`q`.`kode_pertanyaan` = 'F510');
SQL
            );
        }
    }

    /**
     * Menghapus view 'v_question_mappings' saat migration dibatalkan.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_question_mappings');
    }
};
