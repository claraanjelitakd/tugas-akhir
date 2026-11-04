<?php

namespace Tests\Feature;

use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\KuesionerJawaban;
use App\Models\KuesionerPertanyaan;
use App\Models\KuesionerSection;
use App\Models\Perusahaan;
use App\Models\Prodi;
use App\Models\User;
use App\Services\Kuesioner\KelengkapanTracerService;
use Database\Seeders\KuesionerSeeder;
use Database\Seeders\UnifiedKuesionerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class KuesionerBranchingCompletenessTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Biodata $biodata;

    protected int $univSectionId;

    protected function setUp(): void
    {
        parent::setUp();

        $prodi = Prodi::create([
            'kode_prodi' => '72',
            'nama_prodi' => 'Sistem Informasi',
        ]);

        $this->user = User::create([
            'username' => '72220099',
            'name' => 'Testing Alumni',
            'email' => 'alumni@example.com',
            'password' => Hash::make('password123'),
            'role' => 'alumni',
            'must_change_password' => false,
        ]);

        DataAkademik::create([
            'nim' => '72220099',
            'nama' => 'Testing Alumni',
            'email_pribadi' => 'alumni.personal@example.com',
            'tanggal_lahir' => '2001-05-15',
            'jenis_kelamin' => 'Laki-laki',
            'nomor_telepon' => '081234567890',
            'alamat_saat_ini' => 'Jl. Solo Km 10',
            'ip_kumulatif' => 3.80,
            'tahun_akademik_lulus' => 'Gasal 2025/2026',
            'tahun_lulus' => '2026',
        ]);

        $perusahaan = Perusahaan::create([
            'nama_perusahaan' => 'PT Tech Global',
            'alamat' => 'Jl. Sudirman No. 10',
            'skala' => 'Nasional',
        ]);

        $this->biodata = Biodata::create([
            'user_id' => $this->user->id,
            'nim' => '72220099',
            'nama' => 'Testing Alumni',
            'prodi_id' => $prodi->id,
            'posisi_jabatan' => 'Software Engineer',
            'skills' => 'Web Development',
            'experience' => 'Cloud Computing',
            'linkedin_url' => 'https://linkedin.com/in/test',
            'linkedin_username' => 'test',
            'instagram_url' => 'https://instagram.com/test',
            'facebook_url' => 'https://facebook.com/test',
            'zipcode' => '55281',
            'perusahaan_id' => $perusahaan->id,
            'tahun_lulus' => '2026',
        ]);

        // Jalankan seeders kuesioner terpadu
        $this->seed([
            KuesionerSeeder::class,
            UnifiedKuesionerSeeder::class,
        ]);

        $univSection = KuesionerSection::where('target', 'univ')->first();
        $this->univSectionId = $univSection?->id ?: 1;
    }

    private function saveAnswer(string $code, mixed $val): void
    {
        $q = KuesionerPertanyaan::where('kode_pertanyaan', $code)->with('opsi')->first();
        if (! $q) {
            return;
        }

        $opsi = $q->opsi->first(fn ($o) => mb_strtolower(trim($o->label)) === mb_strtolower(trim((string) $val)));

        KuesionerJawaban::updateOrCreate(
            [
                'responden_type' => 'alumni',
                'biodata_id' => $this->biodata->id,
                'pertanyaan_id' => $q->id,
            ],
            [
                'evaluasi_atasan_id' => null,
                'section_id' => $this->univSectionId,
                'kode_pertanyaan' => $q->kode_pertanyaan,
                'opsi_id' => $opsi?->id,
                'kode_opsi' => $opsi?->kode_opsi,
                'jawaban_teks' => is_string($val) ? $val : json_encode($val),
                'jawaban_json' => is_array($val) ? $val : null,
            ]
        );
    }

    public function test_working_alumni_with_f504_yes_reaches_100_percent(): void
    {
        // Berikan jawaban untuk alur bekerja dengan F504 = "Ya"
        $answers = [
            'F8' => 'Bekerja (full time/part time)',
            'F504' => 'Ya',
            'F502' => '2',
            'F505' => '7000000',
            'F12' => 'Biaya Sendiri / Keluarga',
            'F14' => 'Sangat Erat',
            'F15' => 'Tingkat yang sama',
        ];

        // Isi F17 (F17-1 s/d F17-54 atau F17a/b)
        $f17Questions = KuesionerPertanyaan::where('is_active', true)
            ->where('kode_pertanyaan', 'LIKE', 'F17%')
            ->get();

        foreach ($f17Questions as $q) {
            $answers[$q->kode_pertanyaan] = '4';
        }

        foreach ($answers as $code => $val) {
            $this->saveAnswer($code, $val);
        }

        $eval = KelengkapanTracerService::evaluasiKuesionerWajib($this->biodata);

        $this->assertTrue($eval['is_complete'], 'Missing questions: '.json_encode($eval['missing_questions']));
        $this->assertEquals(100, $eval['percentage'], 'Persentase kuesioner harus 100%.');
        $this->assertEmpty($eval['missing_questions'], 'Tidak boleh ada pertanyaan missing untuk alur yang valid.');
    }

    public function test_further_study_alumni_reaches_100_percent(): void
    {
        // Alur Melanjutkan Pendidikan: F8 = 'Melanjutkan Pendidikan' -> melompati F504, F502, F505, F506, F14, F15
        $answers = [
            'F8' => 'Melanjutkan Pendidikan',
            'F12' => 'Biaya Sendiri / Keluarga',
        ];

        // Isi F17
        $f17Questions = KuesionerPertanyaan::where('is_active', true)
            ->where('kode_pertanyaan', 'LIKE', 'F17%')
            ->get();

        foreach ($f17Questions as $q) {
            $answers[$q->kode_pertanyaan] = '4';
        }

        foreach ($answers as $code => $val) {
            $this->saveAnswer($code, $val);
        }

        $eval = KelengkapanTracerService::evaluasiKuesionerWajib($this->biodata);

        $this->assertTrue($eval['is_complete'], 'Missing questions for studi lanjut: '.json_encode($eval['missing_questions']));
        $this->assertEquals(100, $eval['percentage'], 'Persentase kuesioner studi lanjut harus 100%.');
        $this->assertEmpty($eval['missing_questions']);
    }

    public function test_unemployed_not_seeking_alumni_reaches_100_percent(): void
    {
        // Alur Belum Memungkinkan Bekerja
        $answers = [
            'F8' => 'Belum memungkinkan bekerja',
            'F12' => 'Biaya Sendiri / Keluarga',
        ];

        // Isi F17
        $f17Questions = KuesionerPertanyaan::where('is_active', true)
            ->where('kode_pertanyaan', 'LIKE', 'F17%')
            ->get();

        foreach ($f17Questions as $q) {
            $answers[$q->kode_pertanyaan] = '3';
        }

        foreach ($answers as $code => $val) {
            $this->saveAnswer($code, $val);
        }

        $eval = KelengkapanTracerService::evaluasiKuesionerWajib($this->biodata);

        $this->assertTrue($eval['is_complete'], 'Missing questions: '.json_encode($eval['missing_questions']));
        $this->assertEquals(100, $eval['percentage']);
        $this->assertEmpty($eval['missing_questions']);
    }

    public function test_working_alumni_with_f504_no_reaches_100_percent(): void
    {
        // Alur Bekerja dengan F504 = "Tidak" (mendapatkan pekerjaan > 6 bulan -> isi F506)
        $answers = [
            'F8' => 'Bekerja (full time/part time)',
            'F504' => 'Tidak',
            'F506' => '8',
            'F12' => 'Biaya Sendiri / Keluarga',
            'F14' => 'Erat',
            'F15' => 'Tingkat yang sama',
        ];

        // Isi F17
        $f17Questions = KuesionerPertanyaan::where('is_active', true)
            ->where('kode_pertanyaan', 'LIKE', 'F17%')
            ->get();

        foreach ($f17Questions as $q) {
            $answers[$q->kode_pertanyaan] = '5';
        }

        foreach ($answers as $code => $val) {
            $this->saveAnswer($code, $val);
        }

        $eval = KelengkapanTracerService::evaluasiKuesionerWajib($this->biodata);

        $this->assertTrue($eval['is_complete'], 'Missing questions for F504=Tidak: '.json_encode($eval['missing_questions']));
        $this->assertEquals(100, $eval['percentage']);
        $this->assertEmpty($eval['missing_questions']);
    }
}
