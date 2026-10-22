<?php

namespace Tests\Feature;

use App\Models\Atasan;
use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\DataOrangTua;
use App\Models\Prodi;
use App\Models\RefFakultas;
use App\Models\RefSubpertanyaan2021;
use App\Models\RefSubpertanyaanDetil;
use App\Models\Tracer;
use App\Models\User;
use App\Services\Alumni\AdminAlumniProfileService;
use App\Services\Kuesioner\KuesionerSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * AlumniProfileEnhancementTest
 *
 * Menguji integrasi fitur RefFakultas, perapian tabel Biodata, Take Home Pay (Gaji),
 * penguncian NIK (read-only), serta auto-fill data Atasan saat memilih Owner/Wiraswasta.
 */
class AlumniProfileEnhancementTest extends TestCase
{
    use RefreshDatabase;

    protected User $alumniUser;

    protected Biodata $biodata;

    protected Prodi $prodi;

    protected RefFakultas $fakultas;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Buat Fakultas & Prodi
        $this->fakultas = RefFakultas::create([
            'kode_fakultas' => '7',
            'nama_fakultas' => 'Fakultas Teknologi Informasi',
        ]);

        $this->prodi = Prodi::create([
            'fakultas_id' => $this->fakultas->id,
            'kode_prodi' => '71',
            'nama_prodi' => 'Informatika',
        ]);

        // 2. Buat User & Data Akademik
        $this->alumniUser = User::create([
            'name' => 'Clara Anjelita',
            'email' => 'clara@ukdw.ac.id',
            'username' => '71200001',
            'password' => Hash::make('password123'),
            'role' => 'alumni',
            'prodi_id' => $this->prodi->id,
            'must_change_password' => false,
        ]);

        $dataAkademik = DataAkademik::create([
            'nim' => '71200001',
            'nama' => 'Clara Anjelita',
            'tempat_lahir' => 'Sleman',
            'tanggal_lahir' => '2001-08-15',
            'jenis_kelamin' => 'Perempuan',
            'golongan_darah' => 'O',
            'warga_negara' => 'WNI',
            'nik' => '3404100000000001',
            'nisn' => '0031234567',
        ]);

        $this->biodata = Biodata::create([
            'user_id' => $this->alumniUser->id,
            'nim' => '71200001',
            'prodi_id' => $this->prodi->id,
            'nama' => 'Clara Anjelita',
            'nik' => '3404100000000001',
            'email_pribadi' => 'clara.pribadi@gmail.com',
            'nomor_telepon' => '081292090001',
        ]);
    }

    /**
     * Test relasi Fakultas -> Prodi -> Biodata.
     */
    public function test_fakultas_and_prodi_relation(): void
    {
        $this->assertNotNull($this->prodi->fakultas);
        $this->assertEquals('Fakultas Teknologi Informasi', $this->prodi->fakultas->nama_fakultas);
        $this->assertEquals('7', $this->prodi->fakultas->kode_fakultas);
    }

    /**
     * Test NIK bersifat read-only dan tidak dapat diubah oleh alumni.
     */
    public function test_nik_cannot_be_modified_by_alumni(): void
    {
        $response = $this->actingAs($this->alumniUser)->post('/alumni/profile', [
            'nama' => 'Clara Anjelita Updated',
            'nik' => '9999999999999999', // mencoba mengganti NIK
            'nomor_telepon' => '081292090002',
        ]);

        $response->assertRedirect();
        $this->biodata->refresh();

        // NIK harus tetap bernilai asli
        $this->assertEquals('3404100000000001', $this->biodata->nik);
        $this->assertEquals('081292090002', $this->biodata->nomor_telepon);
    }

    public function test_profile_rejects_non_image_uploads(): void
    {
        $response = $this->actingAs($this->alumniUser)->post('/alumni/profile', [
            'foto' => UploadedFile::fake()->create('payload.php', 10, 'application/x-php'),
        ]);

        $response->assertSessionHasErrors('foto');
        $this->assertNull($this->biodata->fresh()->foto);
    }

    public function test_profile_can_save_other_data_without_reuploading_existing_photo(): void
    {
        $photoPath = '/uploads/profile/existing-photo.jpg';
        $this->biodata->update(['foto' => $photoPath]);

        $response = $this->actingAs($this->alumniUser)->post('/alumni/profile', [
            'foto' => $photoPath,
            'nomor_telepon' => '081292090002',
        ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors('foto');
        $this->assertSame('081292090002', $this->biodata->fresh()->nomor_telepon);
        $this->assertSame($photoPath, $this->biodata->fresh()->foto);
    }

    public function test_admin_profile_stores_a_new_photo_upload(): void
    {
        $photo = UploadedFile::fake()->image('admin-profile.jpg');

        AdminAlumniProfileService::updateProfile($this->biodata, [
            'foto' => $photo,
        ]);

        $storedPhoto = $this->biodata->fresh()->foto;

        try {
            $this->assertIsString($storedPhoto);
            $this->assertStringStartsWith('/uploads/profile/profile_71200001_', $storedPhoto);
            $this->assertFileExists(public_path(ltrim($storedPhoto, '/')));
        } finally {
            if (is_string($storedPhoto)) {
                @unlink(public_path(ltrim($storedPhoto, '/')));
            }
        }
    }

    public function test_profile_updates_biodata_without_changing_reference_data(): void
    {
        $parent = DataOrangTua::create([
            'nim' => $this->biodata->nim,
            'nama_orang_tua' => 'Nama Referensi',
            'pekerjaan' => 'Pekerjaan Referensi',
        ]);
        $this->biodata->update(['orang_tua_id' => $parent->id]);
        $academicEmail = DataAkademik::where('nim', $this->biodata->nim)->value('email_pribadi');

        $response = $this->actingAs($this->alumniUser)->post('/alumni/profile', [
            'email_pribadi' => 'email.biodata.baru@example.com',
            'nama_orang_tua' => 'Nama Yang Tidak Boleh Tersimpan',
            'pekerjaan_orang_tua' => 'Pekerjaan Yang Tidak Boleh Tersimpan',
        ]);

        $response->assertRedirect();
        $this->assertSame('email.biodata.baru@example.com', $this->biodata->fresh()->email_pribadi);
        $this->assertSame($academicEmail, DataAkademik::where('nim', $this->biodata->nim)->value('email_pribadi'));
        $this->assertSame('Nama Referensi', $parent->fresh()->nama_orang_tua);
        $this->assertSame('Pekerjaan Referensi', $parent->fresh()->pekerjaan);
    }

    /**
     * Test saat alumni memilih posisi Owner, data Atasan otomatis terisi dengan data alumni.
     */
    public function test_atasan_is_autofilled_when_position_is_owner(): void
    {
        $response = $this->actingAs($this->alumniUser)->post('/alumni/profile', [
            'nama' => 'Clara Anjelita',
            'email_pribadi' => 'clara.pribadi@gmail.com',
            'nomor_telepon' => '081292090001',
            'nama_perusahaan' => 'PT Clara Startup Digital',
            'posisi_jabatan' => 'Owner',
            'gaji' => '8500000',
            // Data atasan sengaja dikosongkan untuk menguji auto-fill
            'nama_atasan' => '',
            'email_atasan' => '',
            'telepon_atasan' => '',
        ]);

        $response->assertRedirect();
        $this->biodata->refresh();

        $this->assertEquals('Owner', $this->biodata->posisi_jabatan);
        $this->assertEquals(8500000, $this->biodata->gaji);
        $this->assertNotNull($this->biodata->atasan_id);

        $atasan = Atasan::find($this->biodata->atasan_id);
        $this->assertNotNull($atasan);
        $this->assertEquals('Clara Anjelita', $atasan->nama);
        $this->assertEquals('clara.pribadi@gmail.com', $atasan->email);
        $this->assertEquals('081292090001', $atasan->telepon);
    }

    /**
     * Test sinkronisasi Take Home Pay (Gaji) ke kuesioner Tracer F505.
     */
    public function test_gaji_syncs_to_tracer_f505(): void
    {
        $qF505 = RefSubpertanyaan2021::create([
            'section_kode' => '4',
            'kelompok' => 'F5',
            'kode_pertanyaan' => 'F505',
            'subpertanyaan' => 'Berapa rata-rata pendapatan anda per bulan ? (take home pay)?',
            'type' => 'number',
            'keterangan' => 'Nominal Take Home Pay',
            'wajib' => 1,
            'order' => 25,
        ]);

        $this->biodata->update(['gaji' => 9500000]);
        KuesionerSyncService::syncProfileResponses($this->biodata);

        $tracer = Tracer::where('biodata_id', $this->biodata->id)
            ->where('kode_pertanyaan', 'F505')
            ->first();

        $this->assertNotNull($tracer);
        $this->assertEquals('9500000', $tracer->answer);
    }

    /**
     * Test input gaji nominal murni tersimpan persis sesuai input (tanpa pengali otomatis).
     */
    public function test_gaji_input_saved_as_exact_nominal(): void
    {
        $response = $this->actingAs($this->alumniUser)->post('/alumni/profile', [
            'nama' => 'Clara Anjelita',
            'gaji' => '5.000.000',
        ]);

        $response->assertRedirect();
        $this->biodata->refresh();

        $this->assertEquals(5000000, $this->biodata->gaji);
    }

    /**
     * Test input gaji di bawah Rp 1.000 memicu error validasi minimal ribuan.
     */
    public function test_gaji_below_thousand_fails_validation(): void
    {
        $response = $this->actingAs($this->alumniUser)->post('/alumni/profile', [
            'nama' => 'Clara Anjelita',
            'gaji' => '709',
        ]);

        $response->assertSessionHasErrors('gaji');
    }

    /**
     * Test sinkronisasi F11 (Jenis Perusahaan) dan F5C (Wiraswasta) ke tabel tracer.
     */
    public function test_f11_and_wiraswasta_f5c_syncs_to_tracer(): void
    {
        $qF11 = RefSubpertanyaan2021::create([
            'kelompok' => 'F11',
            'kode_pertanyaan' => 'F11',
            'subpertanyaan' => 'Apa jenis perusahaan/instansi/institusi tempat Anda bekerja sekarang?',
            'type' => 'radio',
            'wajib' => 1,
            'order' => 30,
        ]);

        $qF5C = RefSubpertanyaan2021::create([
            'kelompok' => 'F5',
            'kode_pertanyaan' => 'F5C',
            'subpertanyaan' => 'Bila berwiraswasta, apa posisi/jabatan Anda saat ini?',
            'type' => 'text',
            'wajib' => 0,
            'order' => 31,
        ]);

        $response = $this->actingAs($this->alumniUser)->post('/alumni/profile', [
            'nama' => 'Clara Anjelita',
            'nama_perusahaan' => 'PT Clara Inovasi Teknologi',
            'perusahaan_jenis_perusahaan' => 'Perusahaan swasta',
            'kategori_pekerjaan' => 'Wiraswasta',
            'posisi_wiraswasta' => 'Chief Executive Officer',
        ]);

        $response->assertRedirect();
        $this->biodata->refresh();

        $this->assertEquals('Wiraswasta', $this->biodata->kategori_pekerjaan);
        $this->assertEquals('Chief Executive Officer', $this->biodata->posisi_wiraswasta);
        $this->assertEquals('Perusahaan swasta', $this->biodata->perusahaan->jenis_perusahaan);

        $tracerF11 = Tracer::where('biodata_id', $this->biodata->id)->where('kode_pertanyaan', 'F11')->first();
        $this->assertNotNull($tracerF11);
        $this->assertEquals('Perusahaan swasta', $tracerF11->answer);

        $tracerF5C = Tracer::where('biodata_id', $this->biodata->id)->where('kode_pertanyaan', 'F5C')->first();
        $this->assertNotNull($tracerF5C);
        $this->assertEquals('Chief Executive Officer', $tracerF5C->answer);
    }

    public function test_dynamic_profile_dropdown_saves_other_text_to_tracer(): void
    {
        $question = RefSubpertanyaan2021::create([
            'kelompok' => 'BIO',
            'kode_pertanyaan' => 'BIO_BIDANG_TEST',
            'subpertanyaan' => 'Bidang pekerjaan atau divisi',
            'type' => 'dropdown',
            'wajib' => 1,
            'tampil_di' => 'profile',
            'order' => 90,
        ]);

        RefSubpertanyaanDetil::create([
            'pertanyaan_id' => $question->id,
            'kode_pertanyaan' => $question->kode_pertanyaan,
            'kode_opsi' => '1',
            'option_text' => 'Teknologi Informasi',
            'order' => 1,
        ]);
        RefSubpertanyaanDetil::create([
            'pertanyaan_id' => $question->id,
            'kode_pertanyaan' => $question->kode_pertanyaan,
            'kode_opsi' => '2',
            'option_text' => 'Lainnya',
            'order' => 2,
        ]);

        $page = $this->actingAs($this->alumniUser)->get(route('alumni.profile'));
        $page->assertInertia(fn ($assertion) => $assertion
            ->where('profileQuestions.0.id', $question->id)
            ->where('formData.profile_answers', [])
        );

        $response = $this->actingAs($this->alumniUser)->post(route('alumni.profile.simpan'), [
            'profile_answers' => [$question->id => 'Lainnya'],
            'profile_answer_custom' => [$question->id => 'Konsultan Independen'],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tracer', [
            'biodata_id' => $this->biodata->id,
            'question_id' => $question->id,
            'answer' => 'Lainnya: Konsultan Independen',
        ]);

        $saved = Tracer::where('biodata_id', $this->biodata->id)
            ->where('question_id', $question->id)
            ->firstOrFail();
        $this->assertSame('Lainnya', $saved->answer_json['selected']);
        $this->assertSame('Konsultan Independen', $saved->answer_json['input']);
    }

    public function test_bidang_pekerjaan_dropdown_saves_to_biodata(): void
    {
        $question = RefSubpertanyaan2021::where('kode_pertanyaan', 'BIO_BIDANG_DIVISI')->firstOrFail();

        RefSubpertanyaanDetil::updateOrCreate(
            ['pertanyaan_id' => $question->id, 'kode_opsi' => '5'],
            ['kode_pertanyaan' => $question->kode_pertanyaan, 'option_text' => 'Teknologi Informasi', 'order' => 1]
        );
        RefSubpertanyaanDetil::updateOrCreate(
            ['pertanyaan_id' => $question->id, 'kode_opsi' => '15'],
            ['kode_pertanyaan' => $question->kode_pertanyaan, 'option_text' => 'Lainnya', 'order' => 2]
        );

        $page = $this->actingAs($this->alumniUser)->get(route('alumni.profile'));
        $page->assertInertia(fn ($assertion) => $assertion
            ->where('refOptions.BIO_BIDANG_DIVISI.0.option_text', 'Akademik / Pendidikan')
            ->where('profileQuestions', [])
        );

        $response = $this->actingAs($this->alumniUser)->post(route('alumni.profile.simpan'), [
            'bidang_pekerjaan' => 'Lainnya',
            'bidang_pekerjaan_lainnya' => 'Konsultan Independen',
        ]);

        $response->assertRedirect();
        $this->assertSame('Konsultan Independen', $this->biodata->fresh()->bidang_pekerjaan);
        $this->assertDatabaseMissing('tracer', [
            'biodata_id' => $this->biodata->id,
            'kode_pertanyaan' => 'BIO_BIDANG_DIVISI',
        ]);
    }

    /**
     * Test pengisian profil kategori Melanjutkan Pendidikan lengkap dengan tingkat, PT, prodi, dan atasan/dosen pembimbing.
     */
    public function test_melanjutkan_pendidikan_profile_and_atasan_saves_properly(): void
    {
        $qF18b = RefSubpertanyaan2021::create([
            'section_kode' => '10',
            'kelompok' => 'F18',
            'kode_pertanyaan' => 'F18b',
            'subpertanyaan' => 'Perguruan Tinggi',
            'type' => 'text',
            'wajib' => 1,
            'order' => 68,
        ]);

        $qF18c = RefSubpertanyaan2021::create([
            'section_kode' => '10',
            'kelompok' => 'F18',
            'kode_pertanyaan' => 'F18c',
            'subpertanyaan' => 'Program Studi',
            'type' => 'text',
            'wajib' => 1,
            'order' => 69,
        ]);

        $response = $this->actingAs($this->alumniUser)->post('/alumni/profile', [
            'nama' => 'Clara Anjelita',
            'kategori_pekerjaan' => 'Melanjutkan Pendidikan',
            'pendidikan_tingkat' => 'S2',
            'perguruan_tinggi' => 'Universitas Gadjah Mada',
            'pendidikan_prodi' => 'Magister Ilmu Komputer',
            'nama_atasan' => 'Prof. Dr. Ir. Pembimbing Utama',
            'email_atasan' => 'pembimbing@ugm.ac.id',
            'telepon_atasan' => '081234567899',
        ]);

        $response->assertRedirect();
        $this->biodata->refresh();

        $this->assertEquals('Melanjutkan Pendidikan', $this->biodata->kategori_pekerjaan);
        $this->assertEquals('S2', $this->biodata->pendidikan_tingkat);
        $this->assertEquals('Universitas Gadjah Mada', $this->biodata->perguna_tinggi ?? $this->biodata->perguruan_tinggi);
        $this->assertEquals('Magister Ilmu Komputer', $this->biodata->pendidikan_prodi);

        // Pastikan atasan/dosen pembimbing tetap tersimpan dengan baik
        $this->assertNotNull($this->biodata->atasan_id);
        $atasan = Atasan::find($this->biodata->atasan_id);
        $this->assertEquals('Prof. Dr. Ir. Pembimbing Utama', $atasan->nama);
        $this->assertEquals('pembimbing@ugm.ac.id', $atasan->email);
        $this->assertEquals('081234567899', $atasan->telepon);

        // Pastikan sinkronisasi ke tracer F18b dan F18c berjalan
        $tracerF18b = Tracer::where('biodata_id', $this->biodata->id)->where('kode_pertanyaan', 'F18b')->first();
        $this->assertNotNull($tracerF18b);
        $this->assertEquals('Universitas Gadjah Mada', $tracerF18b->answer);

        $tracerF18c = Tracer::where('biodata_id', $this->biodata->id)->where('kode_pertanyaan', 'F18c')->first();
        $this->assertNotNull($tracerF18c);
        $this->assertEquals('Magister Ilmu Komputer', $tracerF18c->answer);
    }
}
