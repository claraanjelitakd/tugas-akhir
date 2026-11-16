<?php

namespace Tests\Feature;

use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\KuesionerJawaban;
use App\Models\KuesionerOpsi;
use App\Models\KuesionerPertanyaan;
use App\Models\KuesionerSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TracerAnswerConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_kuesioner_jawaban_stores_text_and_json_properly(): void
    {
        $biodata = $this->createBiodata('72230001', 'Tracer Test');

        $section = KuesionerSection::create([
            'kode_section' => 'SEC-UNIV-1',
            'target' => 'univ',
            'judul' => 'Bagian Satu',
            'order' => 1,
            'is_active' => true,
        ]);

        $pertanyaan = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'F1A',
            'teks_pertanyaan' => 'Pertanyaan teks',
            'tipe' => 'text',
            'is_active' => true,
        ]);

        $jawaban = KuesionerJawaban::create([
            'responden_type' => 'alumni',
            'biodata_id' => $biodata->id,
            'section_id' => $section->id,
            'pertanyaan_id' => $pertanyaan->id,
            'kode_pertanyaan' => 'F1A',
            'jawaban_teks' => 'Jawaban teks alumni',
            'jawaban_json' => ['value' => 'Jawaban teks alumni'],
        ]);

        $jawaban->refresh();

        $this->assertSame('Jawaban teks alumni', $jawaban->jawaban_teks);
        $this->assertSame(['value' => 'Jawaban teks alumni'], $jawaban->jawaban_json);
        $this->assertSame('Jawaban teks alumni', $jawaban->nilai);
    }

    public function test_kuesioner_jawaban_handles_json_with_catatan_accessor(): void
    {
        $biodata = $this->createBiodata('72230002', 'Tracer JSON Test');

        $section = KuesionerSection::create([
            'kode_section' => 'SEC-ATASAN-1',
            'target' => 'atasan',
            'judul' => 'Evaluasi Kinerja',
            'order' => 1,
            'is_active' => true,
        ]);

        $pertanyaan = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'EVA_kompetensi',
            'teks_pertanyaan' => 'Kompetensi lulusan',
            'tipe' => 'rating_5',
            'is_active' => true,
        ]);

        $jawaban = KuesionerJawaban::create([
            'responden_type' => 'atasan',
            'biodata_id' => $biodata->id,
            'section_id' => $section->id,
            'pertanyaan_id' => $pertanyaan->id,
            'kode_pertanyaan' => 'EVA_kompetensi',
            'jawaban_teks' => 'Sangat Tinggi',
            'jawaban_json' => [
                'selected' => 'Sangat Tinggi',
                'catatan' => 'Sangat profesional dan cepat beradaptasi',
            ],
        ]);

        $jawaban->refresh();

        $this->assertSame('Sangat Tinggi', $jawaban->jawaban_teks);
        $this->assertSame('Sangat Tinggi', $jawaban->nilai);
        $this->assertSame('Sangat profesional dan cepat beradaptasi', $jawaban->catatan);
    }

    public function test_option_answer_uses_master_label_and_question_scoped_code(): void
    {
        $biodata = $this->createBiodata('72230003', 'Tracer Option Test');
        $section = KuesionerSection::create([
            'kode_section' => 'SEC-UNIV-OPTION',
            'target' => 'univ',
            'judul' => 'Status Pekerjaan',
            'order' => 1,
            'is_active' => true,
        ]);
        $question = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'F8',
            'teks_pertanyaan' => 'Jelaskan status Anda saat ini',
            'tipe' => 'radio',
            'is_active' => true,
        ]);
        $option = KuesionerOpsi::create([
            'pertanyaan_id' => $question->id,
            'kode_opsi' => '3',
            'label' => 'Wiraswasta',
            'order' => 3,
        ]);

        $answer = KuesionerJawaban::create([
            'responden_type' => 'alumni',
            'biodata_id' => $biodata->id,
            'section_id' => $section->id,
            'pertanyaan_id' => $question->id,
            'kode_pertanyaan' => 'F8',
            'opsi_id' => $option->id,
            'kode_opsi' => '3',
            'jawaban_teks' => 'Wirausaha',
            'jawaban_json' => ['selected' => 'Wirausaha'],
        ]);

        $answer->refresh();

        $this->assertSame($option->id, $answer->opsi_id);
        $this->assertSame('3', $answer->kode_opsi);
        $this->assertSame('Wiraswasta', $answer->jawaban_teks);
        $this->assertSame('Wiraswasta', $answer->jawaban_json['selected']);
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
        ]);
    }
}
