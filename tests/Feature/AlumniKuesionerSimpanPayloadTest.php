<?php

namespace Tests\Feature;

use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\KelompokPertanyaan;
use App\Models\Kuesioner;
use App\Models\Perusahaan;
use App\Models\Prodi;
use App\Models\RefSubpertanyaan2021;
use App\Models\Tracer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AlumniKuesionerSimpanPayloadTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Biodata $alumni;

    protected Kuesioner $kuesioner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'username' => '821220001',
            'name' => 'Alumni Test',
            'email' => 'alumni@example.com',
            'password' => Hash::make('password123'),
            'role' => 'alumni',
            'must_change_password' => false,
        ]);

        $prodi = Prodi::create([
            'kode_prodi' => '82',
            'nama_prodi' => 'Sistem Informasi',
            'jenjang' => 'S1',
        ]);

        DataAkademik::create([
            'nim' => '821220001',
            'nama' => 'Alumni Test',
            'status_mahasiswa' => 'AR',
            'tahun_lulus' => '2020',
            'ipk' => 3.75,
            'prodi_id' => $prodi->id,
        ]);

        $this->alumni = Biodata::create([
            'user_id' => $this->user->id,
            'nim' => '821220001',
            'prodi_id' => $prodi->id,
            'tahun_lulus' => '2020',
        ]);

        $this->kuesioner = Kuesioner::create([
            'title' => 'Tracer Study 2026',
            'year' => 2026,
            'is_active' => true,
        ]);

        $sec = KelompokPertanyaan::create([
            'kuesioner_id' => $this->kuesioner->id,
            'title' => 'Mencari Kerja',
            'order' => 3,
        ]);

        $qRadio = RefSubpertanyaan2021::create([
            'id' => 14,
            'kelompok_pertanyaan_id' => $sec->id,
            'kode_pertanyaan' => 'F21',
            'subpertanyaan' => 'Perkuliahan',
            'type' => 'radio',
            'order' => 1,
        ]);

        $qCheck = RefSubpertanyaan2021::create([
            'id' => 22,
            'kelompok_pertanyaan_id' => $sec->id,
            'kode_pertanyaan' => 'F4',
            'subpertanyaan' => 'Bagaimana anda mencari pekerjaan tersebut?',
            'type' => 'multiple_choice',
            'order' => 2,
        ]);
    }

    public function test_can_save_answers_with_nested_and_mismatched_payload(): void
    {
        $qRadio = RefSubpertanyaan2021::where('kode_pertanyaan', 'F21')->first();
        $qCheck = RefSubpertanyaan2021::where('kode_pertanyaan', 'F4')->first();

        $payload = [
            'answers' => [
                (string) $qRadio->id => [
                    'selected' => 'Kira-kira ... bulan sesudah lulus',
                    'input' => '5',
                    'inputs' => [
                        '11' => '1',
                        '12' => '5',
                        '13' => null,
                    ],
                ],
                (string) $qCheck->id => [
                    'Melalui iklan di koran/majalah, brosur',
                    'Melamar ke perusahaan tanpa mengetahui lowongan yang ada',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->post('/alumni/kuesioner', $payload);

        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('tracer', [
            'biodata_id' => $this->alumni->id,
            'question_id' => $qCheck->id,
            'answer' => 'Melalui iklan di koran/majalah, brosur, Melamar ke perusahaan tanpa mengetahui lowongan yang ada',
        ]);
    }

    public function test_answering_same_question_multiple_times_updates_record_without_duplication(): void
    {
        $qCheck = RefSubpertanyaan2021::where('kode_pertanyaan', 'F4')->first();

        // 1. Kirim jawaban pertama
        $payload1 = [
            'answers' => [
                (string) $qCheck->id => ['Melalui iklan di koran'],
            ],
        ];

        $response1 = $this->actingAs($this->user)
            ->post('/alumni/kuesioner', $payload1);

        $response1->assertStatus(302);

        // Pastikan ada 1 baris
        $count1 = Tracer::where('biodata_id', $this->alumni->id)
            ->where('question_id', $qCheck->id)
            ->count();
        $this->assertEquals(1, $count1);

        // 2. Kirim jawaban kedua (update jawaban yang sama)
        $payload2 = [
            'answers' => [
                (string) $qCheck->id => ['Mencari lewat internet/iklan online', 'Dihubungi perusahaan'],
            ],
        ];

        $response2 = $this->actingAs($this->user)
            ->post('/alumni/kuesioner', $payload2);

        $response2->assertStatus(302);

        // Pastikan jumlah baris tetap 1 (tidak duplikat) dan isinya ter-update
        $count2 = Tracer::where('biodata_id', $this->alumni->id)
            ->where('question_id', $qCheck->id)
            ->count();
        $this->assertEquals(1, $count2);

        $tracer = Tracer::where('biodata_id', $this->alumni->id)
            ->where('question_id', $qCheck->id)
            ->first();

        $this->assertEquals('Mencari lewat internet/iklan online, Dihubungi perusahaan', $tracer->answer);
    }

    public function test_invalid_company_scale_answer_is_not_written_to_enum_column(): void
    {
        $company = Perusahaan::create([
            'nama_perusahaan' => 'PT Perusahaan Uji',
            'skala' => 'Nasional',
            'status_verifikasi' => 'Menunggu Verifikasi',
            'jenis_lokasi' => 'Dalam Negeri',
            'negara' => 'Indonesia',
        ]);
        $this->alumni->update(['perusahaan_id' => $company->id]);

        $question = RefSubpertanyaan2021::create([
            'id' => 24,
            'kelompok_pertanyaan_id' => $this->kuesioner->sections()->first()->id,
            'kode_pertanyaan' => 'F2H',
            'subpertanyaan' => 'Skala perusahaan',
            'type' => 'radio',
            'order' => 3,
        ]);

        $response = $this->actingAs($this->user)->post('/alumni/kuesioner', [
            'answers' => [
                (string) $question->id => 'Tingkat yang Sama',
            ],
        ]);

        $response->assertRedirect();
        $this->assertSame('Nasional', $company->fresh()->skala);
    }
}
