<?php

namespace Tests\Feature;

use App\Models\Biodata;
use App\Models\KuesionerJawaban;
use Database\Seeders\DashboardScenarioSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Memastikan fixture dashboard menghasilkan data karier dan studi lanjut
 * yang konsisten dengan percabangan instrumen tracer.
 */
class DashboardScenarioSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seeder tidak mengisi detail studi lanjut pada alumni yang tidak studi.
     */
    public function test_dashboard_fixture_keeps_study_fields_scoped_to_further_study_status(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DashboardScenarioSeeder::class);

        $employee = Biodata::query()->where('nim', '71220010')->firstOrFail();
        $answerCountAfterFirstRun = KuesionerJawaban::query()
            ->where('biodata_id', $employee->id)
            ->count();

        $this->seed(DashboardScenarioSeeder::class);

        $this->assertSame(
            $answerCountAfterFirstRun,
            KuesionerJawaban::query()->where('biodata_id', $employee->id)->count()
        );
        $this->assertSame('Bekerja (Full Time)', $employee->kategori_pekerjaan);
        $this->assertNull($employee->pendidikan_tingkat);
        $this->assertNull($employee->perguruan_tinggi);
        $this->assertDatabaseMissing('kuesioner_jawaban', [
            'biodata_id' => $employee->id,
            'kode_pertanyaan' => 'F18d',
        ]);

        $entrepreneur = Biodata::query()
            ->where('kategori_pekerjaan', 'Wiraswasta')
            ->firstOrFail();
        $this->assertNotEmpty($entrepreneur->posisi_wiraswasta);
        $this->assertContains(
            'Freelancer / Konsultan Mandiri',
            Biodata::query()->where('kategori_pekerjaan', 'Wiraswasta')->pluck('posisi_wiraswasta')->all()
        );
        $this->assertContains(
            'Pengelola Usaha',
            Biodata::query()->where('kategori_pekerjaan', 'Wiraswasta')->pluck('posisi_wiraswasta')->all()
        );
        $this->assertSame('Wiraswasta', $this->latestAnswer($entrepreneur->id, 'F8'));

        $furtherStudy = Biodata::query()
            ->where('kategori_pekerjaan', 'Melanjutkan Pendidikan')
            ->firstOrFail();
        $studyStart = $this->latestAnswer($furtherStudy->id, 'F18d');
        $this->assertNotNull($studyStart);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $studyStart);
        $this->assertNotNull($this->latestAnswer($furtherStudy->id, 'F12'));
    }

    /**
     * Mengambil jawaban terbaru dari fixture untuk memeriksa nilai kanonik.
     */
    private function latestAnswer(int $biodataId, string $code): ?string
    {
        return KuesionerJawaban::query()
            ->where('biodata_id', $biodataId)
            ->where('kode_pertanyaan', $code)
            ->latest('id')
            ->value('jawaban_teks');
    }
}
