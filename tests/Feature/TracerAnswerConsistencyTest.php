<?php

namespace Tests\Feature;

use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\Tracer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TracerAnswerConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_text_answer_always_gets_a_json_projection(): void
    {
        $biodata = $this->createBiodata('72230001', 'Tracer Test');

        $tracer = Tracer::create([
            'biodata_id' => $biodata->id,
            'nim' => $biodata->nim,
            'kelompok' => 'F1',
            'kode_pertanyaan' => 'F1A',
            'subpertanyaan' => 'Pertanyaan teks',
            'answer' => 'Jawaban lama',
        ]);

        $tracer->refresh();

        $this->assertSame(['value' => 'Jawaban lama'], $tracer->answer_json);
        $this->assertSame('Jawaban lama', $tracer->answer_text);
    }

    public function test_json_answer_always_gets_a_readable_text_projection(): void
    {
        $biodata = $this->createBiodata('72230002', 'Tracer JSON Test');

        $tracer = Tracer::create([
            'biodata_id' => $biodata->id,
            'nim' => $biodata->nim,
            'kelompok' => 'F1',
            'kode_pertanyaan' => 'F1B',
            'subpertanyaan' => 'Pertanyaan pilihan',
            'answer_json' => [
                'selected' => 'Lainnya',
                'input' => 'Bidang khusus',
            ],
        ]);

        $tracer->refresh();

        $this->assertSame('Lainnya: Bidang khusus', $tracer->answer_text);
        $this->assertSame('Lainnya: Bidang khusus', $tracer->answer);
    }

    private function createBiodata(string $nim, string $name): Biodata
    {
        $user = User::create([
            'username' => $nim,
            'name' => $name,
            'password' => Hash::make('password'),
            'role' => 'alumni',
            'must_change_password' => false,
        ]);

        DataAkademik::create([
            'nim' => $nim,
            'nama' => $name,
        ]);

        return Biodata::create([
            'user_id' => $user->id,
            'nim' => $nim,
            'nama' => $name,
        ]);
    }
}
