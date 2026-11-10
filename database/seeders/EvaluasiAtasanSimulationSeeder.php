<?php

namespace Database\Seeders;

use App\Models\Biodata;
use App\Models\EvaluasiAtasan;
use App\Models\KuesionerJawaban;
use App\Models\KuesionerPertanyaan;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Seeder respons evaluasi atasan untuk seluruh alumni yang memiliki atasan.
 *
 * Jawaban selalu mengambil label dan opsi dari master instrumen aktif agar data
 * simulasi mengikuti kontrak form publik dan dapat dihitung oleh analitik.
 */
class EvaluasiAtasanSimulationSeeder extends Seeder
{
    /**
     * Mengisi evaluasi atasan tersubmit untuk seluruh relasi alumni yang valid.
     */
    public function run(): void
    {
        $questions = KuesionerPertanyaan::query()
            ->with(['opsi', 'sections'])
            ->where('is_active', true)
            ->whereHas('sections', function ($query): void {
                $query->where('kuesioner_section.target', 'atasan')
                    ->where('kuesioner_section_pertanyaan.is_active', true);
            })
            ->get();

        Biodata::query()
            ->whereNotNull('perusahaan_id')
            ->whereNotNull('atasan_id')
            ->with(['perusahaan', 'atasan'])
            ->chunkById(100, function (Collection $biodatas) use ($questions): void {
                foreach ($biodatas as $biodata) {
                    $this->seedEvaluation($biodata, $questions);
                }
            });
    }

    /**
     * Membuat atau memperbarui satu evaluasi dan seluruh responsnya.
     *
     * @param  Collection<int, KuesionerPertanyaan>  $questions
     */
    private function seedEvaluation(Biodata $biodata, Collection $questions): void
    {
        $evaluation = EvaluasiAtasan::updateOrCreate(
            ['biodata_id' => $biodata->id],
            [
                'perusahaan_id' => $biodata->perusahaan_id,
                'atasan_id' => $biodata->atasan_id,
                'token' => hash('sha256', 'seed-evaluasi-atasan-'.$biodata->nim),
                'is_submitted' => true,
                'submitted_at' => Carbon::create(2025, 10, 15, 9, 0, 0),
            ]
        );

        $evaluation->respons()->delete();

        $categoryIndex = $this->careerCategoryIndex($biodata);
        $companyAnswers = [
            'EVA_jumlah_pegawai' => '< 50 Orang',
            'EVA_jumlah_alumni_ukdw' => '< 5 Orang',
            'EVA_standar_gaji_pertama' => $this->salaryOptionLabel((int) ($biodata->gaji ?? 0)),
        ];

        foreach ($questions as $question) {
            $options = $question->opsi->sortBy('order')->values();
            if ($options->isEmpty()) {
                continue;
            }

            $label = $companyAnswers[$question->kode_pertanyaan]
                ?? $this->evaluationOptionLabel($question->kode_pertanyaan, $options, $categoryIndex, $biodata->id);
            $option = $options->first(fn ($item): bool => $item->label === $label) ?? $options->first();
            $section = $question->sections
                ->filter(fn ($item): bool => $item->target === 'atasan' && (bool) $item->pivot->is_active)
                ->sortBy(fn ($item): array => [(int) $item->order, (int) $item->pivot->order])
                ->first();

            KuesionerJawaban::create([
                'responden_type' => 'atasan',
                'biodata_id' => $biodata->id,
                'evaluasi_atasan_id' => $evaluation->id,
                'section_id' => $section?->id ?? 1,
                'pertanyaan_id' => $question->id,
                'kode_pertanyaan' => $question->kode_pertanyaan,
                'opsi_id' => $option->id,
                'kode_opsi' => $option->kode_opsi,
                'jawaban_teks' => $option->label,
                'jawaban_json' => null,
            ]);
        }
    }

    /**
     * Menentukan variasi skenario karier untuk pilihan kesiapan kerja.
     */
    private function careerCategoryIndex(Biodata $biodata): int
    {
        if ($biodata->kategori_pekerjaan === 'Melanjutkan Pendidikan') {
            return 2;
        }

        if ($biodata->kategori_pekerjaan === 'Wiraswasta') {
            return str_contains(strtolower((string) $biodata->posisi_wiraswasta), 'freelancer') ? 4 : 1;
        }

        return 0;
    }

    /**
     * Memilih opsi evaluasi secara deterministik dari opsi aktif master form.
     *
     * @param  Collection<int, mixed>  $options
     */
    private function evaluationOptionLabel(
        string $questionCode,
        Collection $options,
        int $categoryIndex,
        int $biodataId
    ): string {
        $preferredIndex = match ($questionCode) {
            'EVA_tingkat_kesiapan_kerja' => $categoryIndex === 2 ? 1 : ($categoryIndex === 4 ? 2 : 0),
            default => ($biodataId + strlen($questionCode)) % $options->count(),
        };

        return (string) ($options->get(min($preferredIndex, $options->count() - 1))?->label ?? $options->first()->label);
    }

    /**
     * Memetakan gaji alumni ke rentang pilihan gaji pertama pada form atasan.
     */
    private function salaryOptionLabel(int $salary): string
    {
        return match (true) {
            $salary < 1000000 => '< 1.000.000',
            $salary <= 1500000 => '1.000.000 - 1.500.000',
            $salary <= 2000000 => '1.500.000 - 2.000.000',
            $salary <= 3000000 => '2.000.000 - 3.000.000',
            $salary <= 4000000 => '3.000.000 - 4.000.000',
            default => '> 5.000.000',
        };
    }
}
