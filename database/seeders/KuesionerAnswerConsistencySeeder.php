<?php

namespace Database\Seeders;

use App\Models\KuesionerJawaban;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Menormalkan jawaban pilihan yang sudah tersimpan terhadap master opsi.
 *
 * Seeder ini tidak menghapus jawaban atau menggabungkan opsi lintas pertanyaan.
 * Ia hanya memperbaiki referensi opsi dan teks label untuk data lama.
 */
class KuesionerAnswerConsistencySeeder extends Seeder
{
    /**
     * Menyelaraskan opsi ID, kode opsi, dan teks label pada jawaban lama.
     */
    public function run(): void
    {
        $updated = 0;

        KuesionerJawaban::query()
            ->with(['pertanyaan.opsi', 'opsi'])
            ->whereNotNull('pertanyaan_id')
            ->orderBy('id')
            ->chunkById(200, function ($answers) use (&$updated): void {
                foreach ($answers as $answer) {
                    $before = [
                        $answer->opsi_id,
                        $answer->kode_opsi,
                        $answer->jawaban_teks,
                        $answer->jawaban_json,
                    ];

                    $answer->save();

                    $after = [
                        $answer->opsi_id,
                        $answer->kode_opsi,
                        $answer->jawaban_teks,
                        $answer->jawaban_json,
                    ];

                    if ($before !== $after) {
                        $updated++;
                    }
                }
            });

        $duplicateOptionCodes = DB::table('kuesioner_opsi')
            ->select('pertanyaan_id', 'kode_opsi')
            ->whereNotNull('kode_opsi')
            ->groupBy('pertanyaan_id', 'kode_opsi')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($duplicateOptionCodes > 0) {
            $this->command?->warn("Ditemukan {$duplicateOptionCodes} kombinasi pertanyaan + kode opsi yang masih ganda; tidak dihapus otomatis.");
        }

        $this->command?->info("Jawaban pilihan dinormalisasi: {$updated} baris.");
    }
}
