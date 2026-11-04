<?php

namespace Tests\Feature;

use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\Kabupaten;
use App\Models\KuesionerJawaban;
use App\Models\KuesionerOpsi;
use App\Models\KuesionerPertanyaan;
use App\Models\KuesionerSection;
use App\Models\KuesionerSectionPertanyaan;
use App\Models\Prodi;
use App\Models\Propinsi;
use App\Models\User;
use Database\Seeders\AnalyticsDemoDistributionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'kode_provinsi' => $province->kode_provinsi,
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

        $univSection = KuesionerSection::create([
            'kode_section' => 'SEC_UNIV_1',
            'judul' => 'Kuesioner Dikti',
            'target' => 'univ',
            'order' => 1,
            'is_active' => true,
        ]);

        $section = KuesionerSection::create([
            'kode_section' => 'SEC_PRODI_72',
            'prodi_id' => $sistemInformasi->id,
            'judul' => 'Kontribusi Alumni',
            'target' => 'prodi',
            'order' => 1,
            'is_active' => true,
        ]);

        $prodiQuestion = KuesionerPertanyaan::create([
            'pemilik_prodi_id' => $sistemInformasi->id,
            'kode_pertanyaan' => 'PSI-1-04',
            'teks_pertanyaan' => 'Kontribusi alumni',
            'tipe' => 'multiple_choice',
            'is_active' => true,
        ]);

        KuesionerSectionPertanyaan::create([
            'section_id' => $section->id,
            'pertanyaan_id' => $prodiQuestion->id,
            'order' => 1,
        ]);

        foreach (['Kuliah Umum / Dosen Praktisi', 'Kerjasama Magang', 'Sponsor', 'Lainnya (Tuliskan)'] as $order => $optionText) {
            KuesionerOpsi::create([
                'pertanyaan_id' => $prodiQuestion->id,
                'kode_opsi' => 'PSI-1-04-0'.($order + 1),
                'label' => $optionText,
                'order' => $order + 1,
            ]);
        }

        $questions = collect(['F8', 'F21', 'F22', 'F23', 'F24', 'F25', 'F26', 'F27'])
            ->mapWithKeys(function (string $code, int $index) use ($univSection): array {
                $q = KuesionerPertanyaan::create([
                    'kode_pertanyaan' => $code,
                    'teks_pertanyaan' => 'Pertanyaan '.$code,
                    'tipe' => 'radio',
                    'is_active' => true,
                ]);
                KuesionerSectionPertanyaan::create([
                    'section_id' => $univSection->id,
                    'pertanyaan_id' => $q->id,
                    'order' => $index + 1,
                ]);

                return [$code => $q->id];
            });

        $informatikaBiodata = $this->createDemoAlumnus('71000001', $informatika);
        $sistemInformasiBiodata = $this->createDemoAlumnus('72000001', $sistemInformasi);
        $sistemInformasiBiodataLain = $this->createDemoAlumnus('72000002', $sistemInformasi);
        $sistemInformasiBiodataSponsor = $this->createDemoAlumnus('72000003', $sistemInformasi);
        $sistemInformasiBiodataMagang = $this->createDemoAlumnus('72000004', $sistemInformasi);

        foreach ([$informatikaBiodata, $sistemInformasiBiodata, $sistemInformasiBiodataLain, $sistemInformasiBiodataSponsor, $sistemInformasiBiodataMagang] as $biodata) {
            KuesionerJawaban::create([
                'responden_type' => 'alumni',
                'biodata_id' => $biodata->id,
                'section_id' => $univSection->id,
                'pertanyaan_id' => $questions['F8'],
                'kode_pertanyaan' => 'F8',
                'jawaban_teks' => 'Bekerja (full time/part time)',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->seed(AnalyticsDemoDistributionSeeder::class);

        $this->assertDatabaseHas('kuesioner_jawaban', [
            'biodata_id' => $informatikaBiodata->id,
            'kode_pertanyaan' => 'F21',
            'jawaban_teks' => 'Sangat Besar',
        ]);
        $this->assertDatabaseHas('kuesioner_jawaban', [
            'biodata_id' => $sistemInformasiBiodata->id,
            'kode_pertanyaan' => 'F21',
            'jawaban_teks' => 'Besar',
        ]);
        $this->assertDatabaseHas('kuesioner_jawaban', [
            'biodata_id' => $sistemInformasiBiodata->id,
            'kode_pertanyaan' => 'PSI-1-04',
            'jawaban_teks' => 'Sponsor',
        ]);
        $this->assertDatabaseHas('kuesioner_jawaban', [
            'biodata_id' => $sistemInformasiBiodataLain->id,
            'kode_pertanyaan' => 'PSI-1-04',
            'jawaban_teks' => 'Lainnya, Kuliah Umum / Dosen Praktisi',
        ]);
        $this->assertDatabaseHas('kuesioner_jawaban', [
            'biodata_id' => $sistemInformasiBiodataSponsor->id,
            'kode_pertanyaan' => 'PSI-1-04',
            'jawaban_teks' => 'Kuliah Umum / Dosen Praktisi',
        ]);
        $this->assertDatabaseHas('kuesioner_jawaban', [
            'biodata_id' => $sistemInformasiBiodataMagang->id,
            'kode_pertanyaan' => 'PSI-1-04',
            'jawaban_teks' => 'Kerjasama Magang',
        ]);
        $this->assertNotSame(
            KuesionerJawaban::where('biodata_id', $sistemInformasiBiodata->id)->where('kode_pertanyaan', 'PSI-1-04')->value('jawaban_teks'),
            KuesionerJawaban::where('biodata_id', $sistemInformasiBiodataLain->id)->where('kode_pertanyaan', 'PSI-1-04')->value('jawaban_teks'),
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
