<?php

namespace Database\Seeders;

use App\Models\Biodata;
use App\Models\RefSubpertanyaan2021;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Seeder distribusi jawaban realistis indikator F21 s.d. F27 (Penekanan Metode Pembelajaran).
 *
 * Menggantikan pola dummy modulo 20% yang datar dengan profil penekanan metode belajar
 * yang realistis sesuai karakter Laporan Tracer Study UKDW:
 * - Perkuliahan (F21) & Praktikum (F25) paling ditekankan (dominan Sangat Besar & Besar).
 * - Diskusi (F27) & Magang (F24) kategori Besar.
 * - Kerja Lapangan (F26), Demonstrasi (F22), dan Riset (F23) variatif antarprodi.
 */
class LearningMethodsRealisticSeeder extends Seeder
{
    /**
     * Menjalankan pengisian jawaban metode pembelajaran realistis.
     */
    public function run(): void
    {
        $questions = RefSubpertanyaan2021::query()
            ->whereIn('kode_pertanyaan', ['F21', 'F22', 'F23', 'F24', 'F25', 'F26', 'F27'])
            ->get()
            ->keyBy(fn (RefSubpertanyaan2021 $q): string => strtoupper($q->kode_pertanyaan));

        if ($questions->isEmpty()) {
            $this->command?->warn('Pertanyaan F21-F27 tidak ditemukan di ref_subpertanyaan2021.');

            return;
        }

        // Ambil seluruh biodata yang sudah memiliki catatan di tabel tracer
        $biodatas = Biodata::query()
            ->whereHas('tracers')
            ->with(['dataAkademik:nim,tahun_lulus', 'prodi:id,nama_prodi'])
            ->get();

        if ($biodatas->isEmpty()) {
            $biodatas = Biodata::query()
                ->with(['dataAkademik:nim,tahun_lulus', 'prodi:id,nama_prodi'])
                ->get();
        }

        $this->command?->info("Memproses {$biodatas->count()} alumni untuk metode pembelajaran F21-F27...");

        /**
         * Bobot distribusi kumulatif [Sangat Besar, Besar, Cukup Besar, Kurang, Tidak Sama Sekali].
         * Total bobot tiap array berjumlah 100%.
         */
        $methodWeightDistributions = [
            'F21' => [52, 38, 8, 2, 0],   // Perkuliahan: dominan Sangat Besar & Besar
            'F25' => [46, 40, 11, 2, 1],  // Praktikum: sangat tinggi
            'F27' => [38, 44, 14, 3, 1],  // Diskusi: tinggi
            'F24' => [30, 46, 17, 5, 2],  // Magang: besar
            'F26' => [20, 42, 26, 9, 3],  // Kerja Lapangan: variatif
            'F22' => [14, 34, 36, 12, 4], // Demonstrasi: cukup besar
            'F23' => [10, 28, 38, 18, 6], // Proyek Riset: bervariasi
        ];

        $scaleOptions = [
            1 => 'Sangat Besar',
            2 => 'Besar',
            3 => 'Cukup Besar',
            4 => 'Kurang',
            5 => 'Tidak Sama Sekali',
        ];

        $upsertRows = [];
        $now = now();

        foreach ($biodatas as $biodata) {
            $nim = $biodata->nim ?: '00000000';
            $tahunLulus = (int) ($biodata->dataAkademik?->tahun_lulus ?: 2024);
            $nimSeed = (int) preg_replace('/\D/', '', $nim) ?: $biodata->id;

            foreach ($methodWeightDistributions as $code => $weights) {
                $question = $questions->get($code);
                if (! $question) {
                    continue;
                }

                // Deterministic pseudo-random berbasis hash agar data konsisten per alumni
                $hashVal = abs(crc32($nim.'_'.$code)) % 100; // 0 - 99
                $selectedLevel = 1;
                $cumulative = 0;

                foreach ($weights as $lvlIndex => $weight) {
                    $cumulative += $weight;
                    if ($hashVal < $cumulative) {
                        $selectedLevel = $lvlIndex + 1; // 1 s.d. 5
                        break;
                    }
                }

                $answerText = $scaleOptions[$selectedLevel] ?? 'Besar';

                $upsertRows[] = [
                    'biodata_id' => $biodata->id,
                    'question_id' => $question->id,
                    'nim' => $nim,
                    'kelompok' => $question->kelompok,
                    'kode_pertanyaan' => $question->kode_pertanyaan,
                    'subpertanyaan' => $question->subpertanyaan,
                    'answer' => $answerText,
                    'answer_json' => json_encode(['value' => $answerText]),
                    'tahun_lulus' => $tahunLulus,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Simpan dalam chunk 500 baris menggunakan upsert
        collect($upsertRows)->chunk(500)->each(function (Collection $chunk): void {
            DB::table('tracer')->upsert(
                $chunk->all(),
                ['biodata_id', 'kode_pertanyaan'],
                ['answer', 'answer_json', 'question_id', 'nim', 'kelompok', 'subpertanyaan', 'tahun_lulus', 'updated_at']
            );
        });

        $this->command?->info('Berhasil memperbarui data realistis untuk indikator F21-F27.');
    }
}
