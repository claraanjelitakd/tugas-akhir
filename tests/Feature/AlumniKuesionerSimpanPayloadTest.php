<?php

namespace Tests\Feature;

use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\Kuesioner;
use App\Models\KuesionerJawaban;
use App\Models\KuesionerPertanyaan;
use App\Models\KuesionerSection;
use App\Models\KuesionerSectionPertanyaan;
use App\Models\Perusahaan;
use App\Models\Prodi;
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

    protected KuesionerSection $section;

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

        $this->section = KuesionerSection::create([
            'kode_section' => 'SEC-UNIV-3',
            'target' => 'univ',
            'judul' => 'Mencari Kerja',
            'order' => 3,
            'is_active' => true,
        ]);

        $qRadio = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'F21',
            'teks_pertanyaan' => 'Perkuliahan',
            'tipe' => 'single_choice',
            'is_reusable' => true,
            'is_active' => true,
        ]);

        $qCheck = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'F4',
            'teks_pertanyaan' => 'Bagaimana anda mencari pekerjaan tersebut?',
            'tipe' => 'multiple_choice',
            'is_reusable' => true,
            'is_active' => true,
        ]);

        KuesionerSectionPertanyaan::create([
            'section_id' => $this->section->id,
            'pertanyaan_id' => $qRadio->id,
            'order' => 1,
            'wajib' => true,
        ]);

        KuesionerSectionPertanyaan::create([
            'section_id' => $this->section->id,
            'pertanyaan_id' => $qCheck->id,
            'order' => 2,
            'wajib' => true,
        ]);
    }

    public function test_can_save_answers_with_nested_and_mismatched_payload(): void
    {
        $qRadio = KuesionerPertanyaan::where('kode_pertanyaan', 'F21')->first();
        $qCheck = KuesionerPertanyaan::where('kode_pertanyaan', 'F4')->first();

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

        $this->assertDatabaseHas('kuesioner_jawaban', [
            'biodata_id' => $this->alumni->id,
            'pertanyaan_id' => $qCheck->id,
            'jawaban_teks' => 'Melalui iklan di koran/majalah, brosur, Melamar ke perusahaan tanpa mengetahui lowongan yang ada',
        ]);
    }

    public function test_answering_same_question_multiple_times_updates_record_without_duplication(): void
    {
        $qCheck = KuesionerPertanyaan::where('kode_pertanyaan', 'F4')->first();

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
        $count1 = KuesionerJawaban::where('biodata_id', $this->alumni->id)
            ->where('pertanyaan_id', $qCheck->id)
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
        $count2 = KuesionerJawaban::where('biodata_id', $this->alumni->id)
            ->where('pertanyaan_id', $qCheck->id)
            ->count();
        $this->assertEquals(1, $count2);

        $jawaban = KuesionerJawaban::where('biodata_id', $this->alumni->id)
            ->where('pertanyaan_id', $qCheck->id)
            ->first();

        $this->assertEquals('Mencari lewat internet/iklan online, Dihubungi perusahaan', $jawaban->jawaban_teks);
    }

    public function test_invalid_company_scale_answer_is_not_written_to_enum_column(): void
    {
        $company = Perusahaan::create([
            'nama_perusahaan' => 'PT Perusahaan Uji',
            'skala' => 'Nasional',
            'status_verifikasi' => 'Menunggu Verifikasi',
            'jenis_lokasi' => 'Dalam Negeri',
            'kode_negara' => 'ID',
        ]);
        $this->alumni->update(['perusahaan_id' => $company->id]);

        $question = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'F2H',
            'teks_pertanyaan' => 'Skala perusahaan',
            'tipe' => 'single_choice',
            'is_reusable' => true,
            'is_active' => true,
        ]);

        KuesionerSectionPertanyaan::create([
            'section_id' => $this->section->id,
            'pertanyaan_id' => $question->id,
            'order' => 3,
            'wajib' => true,
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
