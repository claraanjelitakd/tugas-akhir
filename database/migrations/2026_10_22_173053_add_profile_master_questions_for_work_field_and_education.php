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
        $questions = [
            [
                'kode_pertanyaan' => 'BIO_BIDANG_DIVISI',
                'subpertanyaan' => 'Bidang pekerjaan atau divisi',
                'keterangan' => 'Pilihan bidang/divisi pekerjaan pada profil alumni',
                'order' => 91,
            ],
            [
                'kode_pertanyaan' => 'BIO_PEND_TINGKAT',
                'subpertanyaan' => 'Jenjang pendidikan studi lanjut',
                'keterangan' => 'Nilai dari LinkedIn atau sumber eksternal yang tidak cocok masuk Lainnya',
                'order' => 92,
            ],
        ];

        foreach ($questions as $question) {
            DB::table('ref_subpertanyaan2021')->updateOrInsert(
                ['kode_pertanyaan' => $question['kode_pertanyaan']],
                [
                    'kelompok' => 'BIO',
                    'subpertanyaan' => $question['subpertanyaan'],
                    'type' => 'dropdown',
                    'keterangan' => $question['keterangan'],
                    'wajib' => false,
                    'tampil_di' => 'profil',
                    'order' => $question['order'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        $questionIds = DB::table('ref_subpertanyaan2021')
            ->whereIn('kode_pertanyaan', array_column($questions, 'kode_pertanyaan'))
            ->pluck('id', 'kode_pertanyaan');

        $options = [
            'BIO_BIDANG_DIVISI' => [
                'Akademik / Pendidikan',
                'Administrasi / Tata Usaha',
                'Keuangan / Akuntansi',
                'Sumber Daya Manusia',
                'Teknologi Informasi',
                'Operasional',
                'Pemasaran / Komunikasi',
                'Penjualan / Pengembangan Bisnis',
                'Penelitian dan Pengembangan',
                'Pelayanan / Customer Service',
                'Produksi / Manufaktur',
                'Kesehatan',
                'Hukum',
                'Teknik',
                'Lainnya',
            ],
            'BIO_PEND_TINGKAT' => [
                'D3',
                'D4',
                'S1',
                'S2',
                'S3',
                'Profesi',
                'Spesialis',
                'Lainnya',
            ],
        ];

        foreach ($options as $code => $labels) {
            foreach ($labels as $index => $label) {
                DB::table('ref_subpertanyaan_detil')->updateOrInsert(
                    [
                        'pertanyaan_id' => $questionIds[$code],
                        'kode_opsi' => (string) ($index + 1),
                    ],
                    [
                        'kode_pertanyaan' => $code,
                        'option_text' => $label,
                        'jump_to' => null,
                        'order' => $index + 1,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $questionIds = DB::table('ref_subpertanyaan2021')
            ->whereIn('kode_pertanyaan', ['BIO_BIDANG_DIVISI', 'BIO_PEND_TINGKAT'])
            ->pluck('id');

        DB::table('ref_subpertanyaan_detil')->whereIn('pertanyaan_id', $questionIds)->delete();
        DB::table('ref_subpertanyaan2021')->whereIn('id', $questionIds)->delete();
    }
};
