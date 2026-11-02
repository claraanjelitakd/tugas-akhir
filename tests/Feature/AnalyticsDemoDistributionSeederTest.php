<?php

namespace Tests\Feature;

use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\Kabupaten;
use App\Models\Prodi;
use App\Models\ProdiQuestion;
use App\Models\ProdiQuestionOption;
use App\Models\ProdiQuestionSection;
use App\Models\ProdiResponse;
use App\Models\Propinsi;
use App\Models\User;
use Database\Seeders\AnalyticsDemoDistributionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Memastikan data demo indikator analitik membentuk pola lintas prodi yang terbaca.
 */
class AnalyticsDemoDistributionSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Jawaban demo F21 mencerminkan profil metode yang berbeda per prodi.
     */
    public function test_seeder_creates_distinct_learning_method_profiles_for_study_programs(): void
    {
        $province = Propinsi::create([
            'kode_provinsi' => '34',
            'nama_provinsi' => 'DI Yogyakarta',
        ]);
        Kabupaten::create([
            'propinsi_id' => $province->id,
            'kode_kabupaten' => '34.71',
            'nama_kabupaten' => 'Kota Yogyakarta',
        ]);
        $informatika = Prodi::create([
            'kode_prodi' => '71',
            'nama_prodi' => 'Informatika',
        ]);
        $sistemInformasi = Prodi::create([
            'kode_prodi' => '72',
            'nama_prodi' => 'Sistem Informasi',
        ]);
        $section = ProdiQuestionSection::create([
            'prodi_id' => $sistemInformasi->id,
            'title' => 'Kontribusi Alumni',
            'order' => 1,
        ]);
        $prodiQuestion = ProdiQuestion::create([
            'prodi_id' => $sistemInformasi->id,
            'prodi_question_section_id' => $section->id,
            'code' => 'PSI-1-04',
            'question_text' => 'Kontribusi alumni',
            'type' => 'multiple_choice',
            'order' => 1,
        ]);
        foreach (['Kuliah Umum / Dosen Praktisi', 'Kerjasama Magang', 'Lainnya (Tuliskan)'] as $order => $optionText) {
            ProdiQuestionOption::create([
                'prodi_question_id' => $prodiQuestion->id,
                'code' => 'PSI-1-04-0'.($order + 1),
                'option_text' => $optionText,
                'order' => $order + 1,
            ]);
        }
        $questions = collect(['F8', 'F21', 'F22', 'F23', 'F24', 'F25', 'F26', 'F27'])
            ->mapWithKeys(fn (string $code, int $index): array => [$code => DB::table('ref_subpertanyaan2021')->insertGetId([
                'kelompok' => $code === 'F8' ? 'F8' : 'F2',
                'kode_pertanyaan' => $code,
                'subpertanyaan' => 'Pertanyaan '.$code,
                'type' => 'radio',
                'wajib' => true,
                'order' => $index + 1,
            ])]);
        $informatikaBiodata = $this->createDemoAlumnus('71000001', $informatika);
        $sistemInformasiBiodata = $this->createDemoAlumnus('72000001', $sistemInformasi);
        $sistemInformasiBiodataLain = $this->createDemoAlumnus('72000002', $sistemInformasi);

        foreach ([$informatikaBiodata, $sistemInformasiBiodata, $sistemInformasiBiodataLain] as $biodata) {
            DB::table('tracer')->insert([
                'biodata_id' => $biodata->id,
                'question_id' => $questions['F8'],
                'nim' => $biodata->nim,
                'kelompok' => 'F8',
                'kode_pertanyaan' => 'F8',
                'subpertanyaan' => 'Status pekerjaan',
                'answer' => 'Bekerja (full time/part time)',
                'tahun_lulus' => now()->year,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->seed(AnalyticsDemoDistributionSeeder::class);

        $this->assertDatabaseHas('tracer', [
            'biodata_id' => $informatikaBiodata->id,
            'kode_pertanyaan' => 'F21',
            'answer' => 'Sangat Besar',
        ]);
        $this->assertDatabaseHas('tracer', [
            'biodata_id' => $sistemInformasiBiodata->id,
            'kode_pertanyaan' => 'F21',
            'answer' => 'Besar',
        ]);
        $this->assertDatabaseHas('prodi_response', [
            'biodata_id' => $sistemInformasiBiodata->id,
            'prodi_question_id' => $prodiQuestion->id,
            'answer_text' => 'Kuliah Umum / Dosen Praktisi',
        ]);
        $this->assertDatabaseHas('prodi_response', [
            'biodata_id' => $sistemInformasiBiodataLain->id,
            'prodi_question_id' => $prodiQuestion->id,
            'answer_text' => 'Lainnya (Tuliskan), Kerjasama Magang',
        ]);
        $this->assertNotSame(
            ProdiResponse::where('biodata_id', $sistemInformasiBiodata->id)->where('prodi_question_id', $prodiQuestion->id)->value('answer_text'),
            ProdiResponse::where('biodata_id', $sistemInformasiBiodataLain->id)->where('prodi_question_id', $prodiQuestion->id)->value('answer_text'),
        );
    }

    /**
     * Membuat alumni demo dengan relasi minimum yang dibutuhkan seeder.
     */
    private function createDemoAlumnus(string $nim, Prodi $prodi): Biodata
    {
        $user = User::create([
            'username' => 'demo-'.$nim,
            'name' => 'Alumni Demo '.$nim,
            'email' => 'demo.'.$nim.'@alumni.ukdw.ac.id',
            'password' => Hash::make('password123'),
            'role' => 'alumni',
            'must_change_password' => false,
        ]);
        DataAkademik::create([
            'nim' => $nim,
            'nama' => 'Alumni Demo '.$nim,
            'tanggal_lahir' => '2001-08-15',
            'tahun_lulus' => now()->year,
        ]);

        return Biodata::create([
            'user_id' => $user->id,
            'nim' => $nim,
            'prodi_id' => $prodi->id,
            'nomor_telepon' => '081234567890',
            'email_pribadi' => 'demo.'.$nim.'@alumni.ukdw.ac.id',
        ]);
    }
}
