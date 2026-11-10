<?php

namespace Tests\Feature;

use App\Models\EvaluasiAtasan;
use App\Models\KuesionerJawaban;
use App\Models\KuesionerPertanyaan;
use App\Models\Prodi;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Memastikan seeder simulasi memakai label opsi prodi yang aktif dan valid.
 */
class AlumniSimulationSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Jawaban kontribusi Sistem Informasi tidak lagi memakai angka mentah.
     */
    public function test_sistem_informasi_contribution_answers_use_master_options(): void
    {
        $this->seed(DatabaseSeeder::class);

        $question = KuesionerPertanyaan::query()
            ->where('kode_pertanyaan', 'PSI-1-04')
            ->firstOrFail();
        $allowedOptions = $question->opsi()->pluck('label')->all();
        $systemInformation = Prodi::query()->where('kode_prodi', '72')->firstOrFail();
        $answers = KuesionerJawaban::query()
            ->where('kode_pertanyaan', 'PSI-1-04')
            ->whereHas('biodata', fn ($query) => $query->where('prodi_id', $systemInformation->id))
            ->pluck('jawaban_teks');

        $this->assertContains('Kunjungan perusahaan', $allowedOptions);
        $this->assertNotEmpty($answers);
        $this->assertNotContains('4', $answers->all());
        $this->assertContains('Kunjungan perusahaan', $answers->all());
        $answers->each(fn (string $answer) => $this->assertContains($answer, $allowedOptions));

        $this->assertSame(
            0,
            KuesionerJawaban::query()
                ->where('responden_type', 'alumni')
                ->where('jawaban_teks', '4')
                ->whereHas('section', fn ($query) => $query->where('target', 'prodi'))
                ->count()
        );
    }

    /**
     * Evaluasi atasan simulasi memakai semua opsi aktif pada formulir terkini.
     */
    public function test_supervisor_evaluations_use_current_form_options(): void
    {
        $this->seed(DatabaseSeeder::class);

        $evaluation = EvaluasiAtasan::query()->with('respons.pertanyaan.opsi')->firstOrFail();
        $this->assertTrue($evaluation->is_submitted);
        $this->assertNotEmpty($evaluation->respons);

        foreach ($evaluation->respons as $response) {
            $allowedOptions = $response->pertanyaan->opsi->pluck('label')->all();

            $this->assertNotSame('4', $response->jawaban_teks);
            $this->assertContains($response->jawaban_teks, $allowedOptions);
            $this->assertNotNull($response->opsi_id);
            $this->assertSame($response->opsi->label, $response->jawaban_teks);
        }
    }
}
