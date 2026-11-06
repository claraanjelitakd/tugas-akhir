<?php

namespace Tests\Feature;

use App\Models\Atasan;
use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\KuesionerJawaban;
use App\Models\KuesionerOpsi;
use App\Models\KuesionerPertanyaan;
use App\Models\KuesionerSection;
use App\Models\KuesionerSectionPertanyaan;
use App\Models\Perusahaan;
use App\Models\Prodi;
use App\Models\RefFakultas;
use App\Models\User;
use App\Models\Yudisium;
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
 * penghapusan data sensitif dari profil, serta auto-fill data Atasan saat memilih Owner/Wiraswasta.
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
            'email_pribadi' => 'clara.akademik@ukdw.ac.id',
            'tanggal_lahir' => '2001-08-15',
            'jenis_kelamin' => 'Perempuan',
            'warga_negara' => 'WNI',
        ]);

        $this->biodata = Biodata::create([
            'user_id' => $this->alumniUser->id,
            'nim' => '71200001',
            'prodi_id' => $this->prodi->id,
            'nama' => 'Clara Anjelita',
            'email_pribadi' => 'clara.pribadi@gmail.com',
            'nomor_telepon' => '081292090001',
        ]);

        DataAkademik::where('nim', $this->biodata->nim)->update([
            'tanggal_lulus' => '2026-09-01',
        ]);

        Yudisium::create([
            'nim' => $this->biodata->nim,
            'judul_ta' => 'Sistem Informasi Tracer Study',
            'judul_ta_inggris' => 'Tracer Study Information System',
        ]);

        KuesionerSection::firstOrCreate(
            ['target' => 'univ'],
            [
                'kode_section' => 'SEC_UNIV_1',
                'judul' => 'Kuesioner Universitas',
                'order' => 1,
                'is_active' => true,
            ]
        );
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

    public function test_sensitive_reference_data_is_not_exposed_on_alumni_profile_and_is_preserved_on_save(): void
    {
        $page = $this->actingAs($this->alumniUser)->get(route('alumni.profile'));

        $page->assertInertia(fn ($assertion) => $assertion
            ->missingAll([
                'biodataData',
                'formData.nik',
                'formData.npwp',
                'formData.no_kk',
                'formData.nisn',
                'formData.no_bpjs',
                'formData.email_students',
                'formData.nama_orang_tua',
                'formData.dosen_pembimbing_1',
                'formData.dosen_penguji_1',
                'formData.status_yudisium',
                'formData.status_publikasi',
            ])
            ->where('alumniData.id', $this->biodata->id)
            ->where('formData.judul_ta', 'Sistem Informasi Tracer Study')
            ->where('formData.judul_ta_inggris', 'Tracer Study Information System')
            ->where('formData.tanggal_lulus', '2026-09-01')
            ->missing('alumniData.dataAkademik')
            ->missing('alumniData.yudisium')
            ->missing('alumniData.orangTua')
        );

        $adminFormData = AdminAlumniProfileService::buildFormData($this->biodata->fresh());
        $this->assertArrayNotHasKey('nik', $adminFormData);
        $this->assertArrayNotHasKey('npwp', $adminFormData);
        $this->assertArrayNotHasKey('email_students', $adminFormData);
        $this->assertArrayNotHasKey('dosen_pembimbing_1', $adminFormData);
        $this->assertArrayNotHasKey('status_yudisium', $adminFormData);
        $this->assertArrayNotHasKey('nama_orang_tua', $adminFormData);
        $this->assertSame('Sistem Informasi Tracer Study', $adminFormData['judul_ta']);
        $this->assertSame('2026-09-01', $adminFormData['tanggal_lulus']);

        $response = $this->actingAs($this->alumniUser)->post(route('alumni.profile.simpan'), [
            'nomor_telepon' => '081292090002',
        ]);

        $response->assertRedirect();
        $this->assertNull($this->biodata->fresh()->getAttribute('npwp'));
    }

    /**
     * Test field sensitif lama tidak dapat disimpan oleh alumni.
     */
    public function test_removed_sensitive_fields_cannot_be_modified_by_alumni(): void
    {
        $response = $this->actingAs($this->alumniUser)->post('/alumni/profile', [
            'nama' => 'Clara Anjelita Updated',
            'nik' => '9999999999999999',
            'nomor_telepon' => '081292090002',
        ]);

        $response->assertRedirect();
        $this->biodata->refresh();

        $this->assertNull($this->biodata->getAttribute('nik'));
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

    /**
     * Memastikan pembaruan parsial admin tidak mengosongkan field profil yang tidak dikirim.
     */
    public function test_admin_partial_profile_update_preserves_unsubmitted_profile_data(): void
    {
        $company = Perusahaan::create([
            'nama_perusahaan' => 'PT Data Lama',
            'status_verifikasi' => 'Terverifikasi',
            'alamat' => 'Alamat Head Office Lama',
            'kode_pos' => '55281',
            'homepage' => 'https://data-lama.example.test',
            'no_telp_fax' => '0274-123456',
        ]);
        $atasan = Atasan::create([
            'perusahaan_id' => $company->id,
            'nama' => 'Atasan Lama',
            'email' => 'atasan.lama@example.test',
            'telepon' => '081234567890',
        ]);

        $this->biodata->update([
            'nomor_telepon' => '081200000001',
            'alamat_saat_ini' => 'Domisili Lama',
            'kode_pos' => '55111',
            'skills' => 'Laravel, Vue',
            'experience' => 'Pengalaman kerja lama',
            'kategori_pekerjaan' => 'Pekerja',
            'posisi_jabatan' => 'Staf Lama',
            'gaji' => 7000000,
            'perusahaan_id' => $company->id,
            'atasan_id' => $atasan->id,
        ]);

        AdminAlumniProfileService::updateProfile($this->biodata, [
            'posisi_jabatan' => 'Lead Engineer',
        ]);

        $freshBiodata = $this->biodata->fresh();
        $this->assertSame('Lead Engineer', $freshBiodata->posisi_jabatan);
        $this->assertSame('081200000001', $freshBiodata->nomor_telepon);
        $this->assertSame('Domisili Lama', $freshBiodata->alamat_saat_ini);
        $this->assertSame('Laravel, Vue', $freshBiodata->skills);
        $this->assertSame('Pengalaman kerja lama', $freshBiodata->experience);
        $this->assertSame(7000000, $freshBiodata->gaji);
        $this->assertSame($company->id, $freshBiodata->perusahaan_id);
        $this->assertSame($atasan->id, $freshBiodata->atasan_id);

        $this->assertSame('Alamat Head Office Lama', $company->fresh()->alamat);
        $this->assertSame('atasan.lama@example.test', $atasan->fresh()->email);
        $this->assertSame('081234567890', $atasan->fresh()->telepon);
    }

    public function test_profile_updates_biodata_without_changing_reference_data(): void
    {
        $academicEmail = DataAkademik::where('nim', $this->biodata->nim)->value('email_pribadi');

        $response = $this->actingAs($this->alumniUser)->post('/alumni/profile', [
            'email_pribadi' => 'email.biodata.baru@example.com',
        ]);

        $response->assertRedirect();
        $this->assertSame('email.biodata.baru@example.com', $this->biodata->fresh()->email_pribadi);
        $this->assertSame($academicEmail, DataAkademik::where('nim', $this->biodata->nim)->value('email_pribadi'));
        $this->assertNull($this->biodata->fresh()->getAttribute('orang_tua_id'));
    }

    /**
     * Memastikan biodata mengambil email akademik hanya saat pembuatan awal.
     */
    public function test_new_biodata_copies_academic_email_without_two_way_sync(): void
    {
        $nim = '71200002';
        $academicEmail = 'academic.source@example.com';

        User::create([
            'name' => 'Alumni Email Source',
            'email' => 'source.user@example.com',
            'username' => $nim,
            'password' => Hash::make('password123'),
            'role' => 'alumni',
            'prodi_id' => $this->prodi->id,
            'must_change_password' => false,
        ]);

        DataAkademik::create([
            'nim' => $nim,
            'nama' => 'Alumni Email Source',
            'email_pribadi' => $academicEmail,
        ]);

        $biodata = Biodata::create([
            'nim' => $nim,
            'prodi_id' => $this->prodi->id,
            'email_pribadi' => null,
        ]);

        $biodata->update(['email_pribadi' => 'updated.biodata@example.com']);

        $this->assertSame($academicEmail, DataAkademik::where('nim', $nim)->value('email_pribadi'));
        $this->assertSame('updated.biodata@example.com', $biodata->fresh()->email_pribadi);
    }

    /** Memastikan profil alumni tidak menurunkan status atau menghapus metadata master head office. */
    public function test_profile_preserves_verified_head_office_when_form_is_partial(): void
    {
        $company = Perusahaan::create([
            'nama_perusahaan' => 'PT Master Head Office',
            'status_verifikasi' => 'Terverifikasi',
            'alamat' => 'Jl. Kantor Pusat No. 1',
            'kode_pos' => '12950',
            'homepage' => 'https://master.example.test',
            'no_telp_fax' => '021-123456',
        ]);

        $response = $this->actingAs($this->alumniUser)->post(route('alumni.profile.simpan'), [
            'nama_perusahaan' => $company->nama_perusahaan,
        ]);

        $response->assertRedirect();

        $company->refresh();
        $this->assertSame('Terverifikasi', $company->status_verifikasi);
        $this->assertSame('Jl. Kantor Pusat No. 1', $company->alamat);
        $this->assertSame('12950', $company->kode_pos);
        $this->assertSame('https://master.example.test', $company->homepage);
        $this->assertSame('021-123456', $company->no_telp_fax);
        $this->assertSame($company->id, $this->biodata->fresh()->perusahaan_id);
    }

    /** Memastikan kandidat perusahaan pending milik alumni lain tidak bocor ke dropdown profil. */
    public function test_profile_company_options_hide_pending_companies_from_other_users(): void
    {
        $otherUser = User::create([
            'name' => 'Alumni Lain',
            'email' => 'alumni-lain@example.com',
            'username' => '71200010',
            'password' => Hash::make('password123'),
            'role' => 'alumni',
            'prodi_id' => $this->prodi->id,
            'must_change_password' => false,
        ]);

        $ownPending = Perusahaan::create([
            'nama_perusahaan' => 'PT Pending Milik Clara',
            'status_verifikasi' => 'Menunggu Verifikasi',
            'created_by_user_id' => $this->alumniUser->id,
        ]);
        $otherPending = Perusahaan::create([
            'nama_perusahaan' => 'PT Pending Milik Alumni Lain',
            'status_verifikasi' => 'Menunggu Verifikasi',
            'created_by_user_id' => $otherUser->id,
        ]);

        $this->actingAs($this->alumniUser)
            ->get(route('alumni.profile'))
            ->assertInertia(fn ($page) => $page->where('companies', function ($companies) use ($ownPending, $otherPending): bool {
                $ids = collect($companies)->pluck('id')->all();

                return in_array($ownPending->id, $ids, true) && ! in_array($otherPending->id, $ids, true);
            }));
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
        $this->assertDatabaseCount('evaluasi_atasan', 0);
    }

    /**
     * Test sinkronisasi Take Home Pay (Gaji) ke kuesioner Tracer F505.
     */
    public function test_gaji_syncs_to_tracer_f505(): void
    {
        $qF505 = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'F505',
            'teks_pertanyaan' => 'Berapa rata-rata pendapatan anda per bulan ? (take home pay)?',
            'tipe' => 'number',
            'keterangan' => 'Nominal Take Home Pay',
            'is_active' => true,
        ]);

        KuesionerJawaban::create([
            'responden_type' => 'alumni',
            'biodata_id' => $this->biodata->id,
            'section_id' => 1,
            'pertanyaan_id' => $qF505->id,
            'kode_pertanyaan' => 'F505',
            'jawaban_teks' => '850000',
            'jawaban_json' => ['F5051' => '850000', 'total' => 850000],
        ]);

        $this->biodata->update(['gaji' => 9500000]);
        KuesionerSyncService::syncProfileResponses($this->biodata);

        $tracer = KuesionerJawaban::where('biodata_id', $this->biodata->id)
            ->where('kode_pertanyaan', 'F505')
            ->first();

        $this->assertNotNull($tracer);
        $this->assertSame('9500000', $tracer->jawaban_teks);
        $this->assertSame($qF505->id, $tracer->pertanyaan_id);
        $this->assertSame($qF505->id, $tracer->pertanyaan->id);
        $this->assertSame('9500000', $tracer->jawaban_json['F5051']);
        $this->assertSame(9500000, $tracer->jawaban_json['total']);
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
        $qF11 = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'F11',
            'teks_pertanyaan' => 'Apa jenis perusahaan/instansi/institusi tempat Anda bekerja sekarang?',
            'tipe' => 'radio',
            'is_active' => true,
        ]);

        $qF5C = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'F5C',
            'teks_pertanyaan' => 'Bila berwiraswasta, apa posisi/jabatan Anda saat ini?',
            'tipe' => 'text',
            'is_active' => true,
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

        $tracerF11 = KuesionerJawaban::where('biodata_id', $this->biodata->id)->where('kode_pertanyaan', 'F11')->first();
        $this->assertNotNull($tracerF11);
        $this->assertEquals('Perusahaan swasta', $tracerF11->jawaban_teks);

        $tracerF5C = KuesionerJawaban::where('biodata_id', $this->biodata->id)->where('kode_pertanyaan', 'F5C')->first();
        $this->assertNotNull($tracerF5C);
        $this->assertEquals('Chief Executive Officer', $tracerF5C->jawaban_teks);
    }

    public function test_dynamic_profile_dropdown_saves_other_text_to_tracer(): void
    {
        $sec = KuesionerSection::firstOrCreate(
            ['target' => 'univ'],
            ['kode_section' => 'SEC_UNIV_1', 'judul' => 'Kuesioner Universitas', 'order' => 1, 'is_active' => true]
        );

        $question = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'BIO_BIDANG_TEST',
            'teks_pertanyaan' => 'Bidang pekerjaan atau divisi',
            'tipe' => 'dropdown',
            'is_active' => true,
        ]);

        KuesionerSectionPertanyaan::create([
            'section_id' => $sec->id,
            'pertanyaan_id' => $question->id,
            'order' => 90,
            'wajib' => true,
            'tampil_di' => 'profile',
        ]);

        KuesionerOpsi::create([
            'pertanyaan_id' => $question->id,
            'kode_opsi' => '1',
            'label' => 'Teknologi Informasi',
            'order' => 1,
        ]);
        KuesionerOpsi::create([
            'pertanyaan_id' => $question->id,
            'kode_opsi' => '2',
            'label' => 'Lainnya',
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
        $this->assertDatabaseHas('kuesioner_jawaban', [
            'biodata_id' => $this->biodata->id,
            'pertanyaan_id' => $question->id,
            'jawaban_teks' => 'Lainnya: Konsultan Independen',
        ]);

        $saved = KuesionerJawaban::where('biodata_id', $this->biodata->id)
            ->where('pertanyaan_id', $question->id)
            ->firstOrFail();
        $this->assertSame('Lainnya', $saved->jawaban_json['selected']);
        $this->assertSame('Konsultan Independen', $saved->jawaban_json['input']);
    }

    /**
     * Memastikan dropdown bidang pekerjaan memakai sumber pertanyaan yang
     * disiapkan oleh test, bukan bergantung pada data migration historis.
     */
    public function test_bidang_pekerjaan_dropdown_saves_to_biodata(): void
    {
        $question = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'BIO_BIDANG_DIVISI',
            'teks_pertanyaan' => 'Bidang pekerjaan atau divisi',
            'tipe' => 'dropdown',
            'is_active' => true,
        ]);

        KuesionerOpsi::updateOrCreate(
            ['pertanyaan_id' => $question->id, 'kode_opsi' => '5'],
            ['label' => 'Teknologi Informasi', 'order' => 1]
        );
        KuesionerOpsi::updateOrCreate(
            ['pertanyaan_id' => $question->id, 'kode_opsi' => '15'],
            ['label' => 'Lainnya', 'order' => 2]
        );

        $page = $this->actingAs($this->alumniUser)->get(route('alumni.profile'));
        $page->assertInertia(fn ($assertion) => $assertion
            ->where('refOptions.BIO_BIDANG_DIVISI.0.option_text', 'Teknologi Informasi')
            ->where('profileQuestions', [])
        );

        $response = $this->actingAs($this->alumniUser)->post(route('alumni.profile.simpan'), [
            'bidang_pekerjaan' => '15 - Lainnya',
            'bidang_pekerjaan_lainnya' => 'Konsultan Independen',
        ]);

        $response->assertRedirect();
        $this->assertSame('Konsultan Independen', $this->biodata->fresh()->bidang_pekerjaan);
        $this->assertDatabaseMissing('kuesioner_jawaban', [
            'biodata_id' => $this->biodata->id,
            'kode_pertanyaan' => 'BIO_BIDANG_DIVISI',
        ]);
    }

    /**
     * Test pengisian profil kategori Melanjutkan Pendidikan lengkap dengan tingkat, PT, prodi, dan atasan/dosen pembimbing.
     */
    public function test_melanjutkan_pendidikan_profile_and_atasan_saves_properly(): void
    {
        $qF18b = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'F18b',
            'teks_pertanyaan' => 'Perguruan Tinggi',
            'tipe' => 'text',
            'is_active' => true,
        ]);

        $qF18c = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'F18c',
            'teks_pertanyaan' => 'Program Studi',
            'tipe' => 'text',
            'is_active' => true,
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
        $tracerF18b = KuesionerJawaban::where('biodata_id', $this->biodata->id)->where('kode_pertanyaan', 'F18b')->first();
        $this->assertNotNull($tracerF18b);
        $this->assertEquals('Universitas Gadjah Mada', $tracerF18b->jawaban_teks);

        $tracerF18c = KuesionerJawaban::where('biodata_id', $this->biodata->id)->where('kode_pertanyaan', 'F18c')->first();
        $this->assertNotNull($tracerF18c);
        $this->assertEquals('Magister Ilmu Komputer', $tracerF18c->jawaban_teks);
    }
}
