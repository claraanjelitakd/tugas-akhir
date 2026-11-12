<?php

namespace Tests\Feature;

use App\Models\Atasan;
use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\EvaluasiAtasan;
use App\Models\KuesionerJawaban;
use App\Models\KuesionerPertanyaan;
use App\Models\Perusahaan;
use App\Models\Prodi;
use App\Models\User;
use App\Models\Yudisium;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    /**
     * Memastikan fixture default hanya memakai kohort 2026 dan seluruh relasi alumni tersambung.
     */
    public function test_default_fixture_keeps_alumni_entities_consistent(): void
    {
        $this->seed(DatabaseSeeder::class);

        $graduates = DataAkademik::query()->whereNotNull('tahun_lulus')->get();

        $this->assertSame(100, $graduates->count());
        $this->assertSame([2026], $graduates->pluck('tahun_lulus')->unique()->sort()->values()->all());
        $this->assertSame(12, $graduates->pluck('prodi_id')->unique()->count());
        $this->assertSame(0, $graduates->filter(fn (DataAkademik $academic): bool => blank($academic->nama) || $academic->nama === '-')->count());
        $biodataWithSupervisors = Biodata::query()->whereNotNull('atasan_id')->count();

        $this->assertSame(
            $biodataWithSupervisors,
            Atasan::query()->whereHas('biodata')->count()
        );
        $this->assertSame(0, Atasan::query()->whereDoesntHave('biodata')->count());
        $this->assertLessThanOrEqual(
            1,
            (int) (Atasan::query()->withCount('biodata')->get()->max('biodata_count') ?? 0)
        );
        $this->assertSame(
            0,
            Perusahaan::query()
                ->whereDoesntHave('biodata')
                ->whereDoesntHave('atasan')
                ->count()
        );

        foreach ($graduates as $academic) {
            $user = User::query()->where('username', $academic->nim)->first();
            $biodata = Biodata::query()->where('nim', $academic->nim)->first();
            $yudisium = Yudisium::query()->where('nim', $academic->nim)->first();

            $this->assertNotNull($user);
            $this->assertNotNull($biodata);
            $this->assertNotNull($yudisium);
            $this->assertSame($academic->nama, $user->name);
            $this->assertSame($academic->nama, $biodata->nama);
            $this->assertSame($academic->nama, $yudisium->dataAkademik?->nama);

            if ($biodata->atasan_id !== null) {
                $this->assertNotNull($biodata->perusahaan_id);
                $this->assertSame($biodata->perusahaan_id, $biodata->atasan?->perusahaan_id);
            }
        }
    }

    /**
     * Memastikan jawaban fixture bervariasi tanpa memutus relasi antar sumber data.
     */
    public function test_default_fixture_has_varied_linked_questionnaire_answers(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThanOrEqual(
            3,
            KuesionerJawaban::query()
                ->where('responden_type', 'alumni')
                ->where('kode_pertanyaan', 'F3')
                ->distinct('jawaban_teks')
                ->count('jawaban_teks')
        );
        $this->assertGreaterThanOrEqual(
            3,
            KuesionerJawaban::query()
                ->where('responden_type', 'alumni')
                ->where('kode_pertanyaan', 'F6')
                ->distinct('jawaban_teks')
                ->count('jawaban_teks')
        );
        $this->assertSame(
            99,
            KuesionerJawaban::query()
                ->where('responden_type', 'alumni')
                ->whereIn('kode_pertanyaan', ['F21', 'F22', 'F23', 'F24', 'F25', 'F26', 'F27'])
                ->distinct('biodata_id')
                ->count('biodata_id')
        );
        $this->assertGreaterThanOrEqual(
            2,
            KuesionerJawaban::query()
                ->where('responden_type', 'alumni')
                ->whereHas('section', fn ($query) => $query->where('target', 'prodi'))
                ->whereIn('kode_pertanyaan', ['PSI-1-01', 'PSI-1-02', 'PSI-1-03', 'PSI-1-04'])
                ->distinct('jawaban_teks')
                ->count('jawaban_teks')
        );
        $this->assertGreaterThan(
            0,
            DB::table('kuesioner_jawaban as graduation')
                ->join('kuesioner_jawaban as required', function ($join): void {
                    $join->on('required.biodata_id', '=', 'graduation.biodata_id')
                        ->where('required.responden_type', 'alumni')
                        ->where('required.kode_pertanyaan', 'F17b1');
                })
                ->where('graduation.responden_type', 'alumni')
                ->where('graduation.kode_pertanyaan', 'F17a1')
                ->whereColumn('graduation.jawaban_teks', '<>', 'required.jawaban_teks')
                ->count()
        );

        $this->assertSame(
            0,
            KuesionerJawaban::query()
                ->where(function ($query): void {
                    $query->whereNull('biodata_id')
                        ->orWhereDoesntHave('biodata');
                })
                ->count()
        );

        $this->assertSame(
            0,
            KuesionerJawaban::query()
                ->where('responden_type', 'atasan')
                ->where(function ($query): void {
                    $query->whereNull('evaluasi_atasan_id')
                        ->orWhereDoesntHave('evaluasiAtasan');
                })
                ->count()
        );

        foreach (EvaluasiAtasan::query()->with(['biodata', 'perusahaan', 'atasan'])->get() as $evaluation) {
            $this->assertNotNull($evaluation->biodata);
            $this->assertSame($evaluation->biodata->perusahaan_id, $evaluation->perusahaan_id);
            $this->assertSame($evaluation->biodata->atasan_id, $evaluation->atasan_id);
            $this->assertSame($evaluation->perusahaan_id, $evaluation->atasan?->perusahaan_id);
        }
    }
}
