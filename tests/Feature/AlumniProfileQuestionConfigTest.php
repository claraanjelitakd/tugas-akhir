<?php

namespace Tests\Feature;

use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\KuesionerPertanyaan;
use App\Models\KuesionerSection;
use App\Models\KuesionerSectionPertanyaan;
use App\Models\User;
use App\Services\Alumni\AdminAlumniProfileService;
use App\Services\Kuesioner\KelengkapanTracerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Memastikan konfigurasi pertanyaan profil membawa status wajib dari penempatan
 * pertanyaan sehingga frontend dan evaluator profil memakai aturan yang sama.
 */
class AlumniProfileQuestionConfigTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Status wajib dan opsional pada pivot dipertahankan di payload konfigurasi profil.
     */
    public function test_profile_question_config_preserves_required_state(): void
    {
        $section = KuesionerSection::create([
            'kode_section' => 'SEC_PROFILE_CONFIG',
            'target' => 'univ',
            'judul' => 'Profil Alumni',
            'order' => 1,
            'is_active' => true,
        ]);

        $requiredQuestion = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'BIO_INSTAGRAM_URL',
            'teks_pertanyaan' => 'Tautan Profil Instagram',
            'tipe' => 'text',
            'is_active' => true,
        ]);

        $optionalQuestion = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'BIO_FACEBOOK_URL',
            'teks_pertanyaan' => 'Tautan Profil Facebook',
            'tipe' => 'text',
            'is_active' => true,
        ]);

        KuesionerSectionPertanyaan::create([
            'section_id' => $section->id,
            'pertanyaan_id' => $requiredQuestion->id,
            'order' => 1,
            'wajib' => true,
            'is_active' => true,
            'tampil_di' => 'profil',
        ]);

        KuesionerSectionPertanyaan::create([
            'section_id' => $section->id,
            'pertanyaan_id' => $optionalQuestion->id,
            'order' => 2,
            'wajib' => false,
            'is_active' => true,
            'tampil_di' => 'profil',
        ]);

        $configs = AdminAlumniProfileService::getProfileQuestionConfigs();

        $this->assertTrue($configs['BIO_INSTAGRAM_URL']['wajib']);
        $this->assertFalse($configs['BIO_FACEBOOK_URL']['wajib']);
    }

    /**
     * Field profil opsional tidak masuk ke daftar kekurangan evaluator backend.
     */
    public function test_profile_evaluator_ignores_optional_profile_fields(): void
    {
        $user = User::create([
            'username' => 'optional-profile-test',
            'name' => 'Alumni Optional',
            'email' => 'optional-profile@example.com',
            'password' => Hash::make('password'),
            'role' => 'alumni',
            'must_change_password' => false,
        ]);

        DataAkademik::create([
            'nim' => '99999999',
            'nama' => 'Alumni Optional',
            'email_pribadi' => 'optional-profile@example.com',
            'ip_kumulatif' => 3.5,
            'tahun_lulus' => 2025,
        ]);

        $biodata = Biodata::create([
            'user_id' => $user->id,
            'nim' => '99999999',
        ]);

        $section = KuesionerSection::create([
            'kode_section' => 'SEC_PROFILE_EVALUATOR',
            'target' => 'univ',
            'judul' => 'Profil Alumni',
            'order' => 1,
            'is_active' => true,
        ]);

        $instagram = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'BIO_INSTAGRAM_URL',
            'teks_pertanyaan' => 'Tautan Profil Instagram',
            'tipe' => 'text',
            'is_active' => true,
        ]);

        $facebook = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'BIO_FACEBOOK_URL',
            'teks_pertanyaan' => 'Tautan Profil Facebook',
            'tipe' => 'text',
            'is_active' => true,
        ]);

        KuesionerSectionPertanyaan::insert([
            [
                'section_id' => $section->id,
                'pertanyaan_id' => $instagram->id,
                'order' => 1,
                'wajib' => true,
                'is_active' => true,
                'tampil_di' => 'profil',
            ],
            [
                'section_id' => $section->id,
                'pertanyaan_id' => $facebook->id,
                'order' => 2,
                'wajib' => false,
                'is_active' => true,
                'tampil_di' => 'profil',
            ],
        ]);

        $evaluation = KelengkapanTracerService::evaluasiProfil($biodata);

        $this->assertContains('Instagram Profil URL', $evaluation['missing_fields']);
        $this->assertNotContains('Facebook Profil URL', $evaluation['missing_fields']);
    }
}
