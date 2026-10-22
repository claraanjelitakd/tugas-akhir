<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $tableComments = [
            'data_akademik' => 'Sumber resmi data akademik. Data identitas dan dokumen sensitif dipertahankan untuk integrasi resmi, tetapi tidak ditampilkan pada profil self-service alumni.',
            'biodata' => 'Profil aktif tracer alumni. Kolom identitas sensitif lama dipertahankan untuk kompatibilitas dan audit, tetapi tidak ditampilkan pada profil self-service alumni.',
            'yudisium' => 'Sumber resmi kelulusan dan tugas akhir. Data pembimbing, penguji, publikasi, dan predikat dipertahankan untuk kebutuhan akademik, tetapi tidak ditampilkan pada profil self-service alumni.',
            'data_orang_tua' => 'Data referensi keluarga alumni. Dipertahankan untuk kebutuhan administrasi resmi, tetapi tidak ditampilkan pada profil self-service alumni untuk menerapkan minimisasi data.',
        ];

        foreach ($tableComments as $table => $comment) {
            DB::statement("ALTER TABLE `{$table}` COMMENT = ".DB::getPdo()->quote($comment));
        }

        $columnComments = [
            ['data_akademik', 'nik', 'CHAR(16)', 'Data identitas kependudukan sensitif; dipertahankan sebagai sumber akademik resmi dan tidak ditampilkan pada profil self-service alumni.'],
            ['data_akademik', 'no_kk', 'CHAR(16)', 'Data kartu keluarga sensitif; dipertahankan sebagai sumber akademik resmi dan tidak ditampilkan pada profil self-service alumni.'],
            ['data_akademik', 'nisn', 'VARCHAR(15)', 'Data identitas pendidikan sensitif; dipertahankan sebagai sumber akademik resmi dan tidak ditampilkan pada profil self-service alumni.'],
            ['data_akademik', 'no_bpjs', 'VARCHAR(20)', 'Data jaminan kesehatan sensitif; dipertahankan sebagai sumber akademik resmi dan tidak ditampilkan pada profil self-service alumni.'],
            ['data_akademik', 'npwp', 'VARCHAR(25)', 'Data perpajakan sensitif; dipertahankan sebagai sumber akademik resmi dan tidak ditampilkan pada profil self-service alumni.'],
            ['data_akademik', 'email_students', 'VARCHAR(100)', 'Email institusi mahasiswa; dipertahankan sebagai data akademik resmi dan tidak ditampilkan pada profil self-service alumni.'],
            ['biodata', 'nik', 'CHAR(16)', 'Data identitas kependudukan sensitif lama; tidak diubah oleh simpan profil alumni dan tidak ditampilkan pada profil self-service.'],
            ['biodata', 'npwp', 'VARCHAR(25)', 'Data perpajakan sensitif lama; tidak diubah oleh simpan profil alumni dan tidak ditampilkan pada profil self-service.'],
            ['yudisium', 'dosen_pembimbing_1', 'VARCHAR(100)', 'Data akademik sensitif; dipertahankan untuk administrasi yudisium dan tidak ditampilkan pada profil self-service alumni.'],
            ['yudisium', 'dosen_pembimbing_2', 'VARCHAR(100)', 'Data akademik sensitif; dipertahankan untuk administrasi yudisium dan tidak ditampilkan pada profil self-service alumni.'],
            ['yudisium', 'dosen_penguji_1', 'VARCHAR(100)', 'Data akademik sensitif; dipertahankan untuk administrasi yudisium dan tidak ditampilkan pada profil self-service alumni.'],
            ['yudisium', 'dosen_penguji_2', 'VARCHAR(100)', 'Data akademik sensitif; dipertahankan untuk administrasi yudisium dan tidak ditampilkan pada profil self-service alumni.'],
            ['yudisium', 'jenis_publikasi', 'VARCHAR(100)', 'Data publikasi akademik; dipertahankan untuk administrasi yudisium dan tidak ditampilkan pada profil self-service alumni.'],
            ['yudisium', 'status_publikasi', 'VARCHAR(50)', 'Data publikasi akademik; dipertahankan untuk administrasi yudisium dan tidak ditampilkan pada profil self-service alumni.'],
            ['yudisium', 'keterangan_hasil_yudisium', 'VARCHAR(60)', 'Predikat hasil yudisium; dipertahankan untuk administrasi akademik dan tidak ditampilkan pada profil self-service alumni.'],
            ['yudisium', 'status_lulus', "ENUM('Belum','Proses','Lulus','Tidak Lulus') NOT NULL DEFAULT 'Belum'", 'Status kelulusan resmi; dipertahankan untuk administrasi akademik dan tidak ditampilkan pada profil self-service alumni.'],
        ];

        foreach ($columnComments as [$table, $column, $type, $comment]) {
            $nullability = $column === 'status_lulus' ? '' : ' NULL';
            DB::statement("ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` {$type}{$nullability} COMMENT ".DB::getPdo()->quote($comment));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE `data_akademik` COMMENT = ''");
        DB::statement("ALTER TABLE `biodata` COMMENT = ''");
        DB::statement("ALTER TABLE `yudisium` COMMENT = ''");
        DB::statement("ALTER TABLE `data_orang_tua` COMMENT = ''");
    }
};
