<?php

namespace Tests\Feature;

use App\Models\AlumniLinkedinPlot;
use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\LinkedinSyncResult;
use App\Models\Perusahaan;
use App\Models\Prodi;
use App\Models\User;
use App\Services\Alumni\LinkedinTraceHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AlumniLinkedinPlotViewTest extends TestCase
{
    use RefreshDatabase;

    protected Prodi $prodi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prodi = Prodi::create([
            'kode_prodi' => '71',
            'nama_prodi' => 'Informatika',
        ]);
    }

    /**
     * Helper untuk membuat data alumni lengkap
     */
    protected function createAlumni(string $nim, string $nama, ?string $linkedinUrl = null): Biodata
    {
        $user = User::create([
            'username' => $nim,
            'name' => $nama,
            'email' => "{$nim}@students.ukdw.ac.id",
            'password' => Hash::make('password123'),
            'role' => 'alumni',
            'prodi_id' => $this->prodi->id,
            'must_change_password' => false,
        ]);

        DataAkademik::create([
            'nim' => $nim,
            'nama' => $nama,
            'email_pribadi' => "{$nim}@gmail.com",
            'tempat_lahir' => 'Yogyakarta',
            'tanggal_lahir' => '2001-01-01',
            'agama' => 'Kristen',
            'jenis_kelamin' => 'Laki-laki',
            'nomor_telepon' => '081234567890',
            'alamat_saat_ini' => 'Jl. Solo Km 5',
            'ip_kumulatif' => 3.75,
            'tahun_lulus' => 2024,
            'tahun_akademik_lulus' => 'Genap 2023/2024',
        ]);

        return Biodata::create([
            'user_id' => $user->id,
            'nim' => $nim,
            'nama' => $nama,
            'prodi_id' => $this->prodi->id,
            'linkedin_url' => $linkedinUrl,
            'linkedin_username' => $linkedinUrl ? basename($linkedinUrl) : null,
        ]);
    }

    /**
     * 1. Test view dapat diakses dan menampilkan alumni yang belum pernah disinkronkan
     */
    public function test_view_displays_alumni_without_linkedin_sync(): void
    {
        $alumni = $this->createAlumni('71200001', 'Aditya Pratama');

        $plot = AlumniLinkedinPlot::find($alumni->id);

        $this->assertNotNull($plot);
        $this->assertEquals('71200001', $plot->nim);
        $this->assertEquals('Aditya Pratama', $plot->nama_alumni);
        $this->assertNull($plot->sync_status);
        $this->assertFalse($plot->is_nama_sesuai);
        $this->assertEquals('Belum Ada Data LinkedIn', $plot->keterangan_nama);
        $this->assertFalse($plot->is_terplot_ke_biodata);
        $this->assertEquals('Belum Pernah Sync', $plot->status_plot_label);
    }

    /**
     * 2. Test deteksi kecocokan nama eksak dan status pending review
     */
    public function test_view_detects_exact_name_match_and_pending_status(): void
    {
        $alumni = $this->createAlumni('71200002', 'Budi Santoso', 'https://linkedin.com/in/budisantoso');

        LinkedinSyncResult::create([
            'biodata_id' => $alumni->id,
            'linkedin_url' => $alumni->linkedin_url,
            'linkedin_username' => 'budisantoso',
            'scraped_data' => [
                'name' => 'Budi Santoso',
                'headline' => 'Software Engineer at Tokopedia',
                'current_job' => 'Software Engineer',
                'current_company' => 'PT Tokopedia',
                'location' => 'Jakarta, Indonesia',
            ],
            'status' => 'pending',
            'scraped_at' => now(),
        ]);

        $plot = AlumniLinkedinPlot::find($alumni->id);

        $this->assertNotNull($plot);
        $this->assertEquals('pending', $plot->sync_status);
        $this->assertEquals('Budi Santoso', $plot->nama_linkedin);
        $this->assertEquals('Software Engineer', $plot->posisi_linkedin);
        $this->assertEquals('PT Tokopedia', $plot->perusahaan_linkedin);
        $this->assertTrue($plot->is_nama_sesuai);
        $this->assertEquals('Nama Sesuai Eksak', $plot->keterangan_nama);
        $this->assertEquals('Menunggu Review (Pending)', $plot->status_plot_label);
    }

    /**
     * 3. Test deteksi kecocokan nama parsial
     */
    public function test_view_detects_partial_name_match(): void
    {
        $alumni = $this->createAlumni('71200003', 'Citra Dewi Lestari', 'https://linkedin.com/in/citradewi');

        LinkedinSyncResult::create([
            'biodata_id' => $alumni->id,
            'linkedin_url' => $alumni->linkedin_url,
            'scraped_data' => [
                'name' => 'Citra Dewi',
                'current_job' => 'UI/UX Designer',
                'current_company' => 'Shopee',
            ],
            'status' => 'pending',
            'scraped_at' => now(),
        ]);

        $plot = AlumniLinkedinPlot::find($alumni->id);

        $this->assertNotNull($plot);
        $this->assertTrue($plot->is_nama_sesuai);
        $this->assertEquals('Nama Sesuai Parsial', $plot->keterangan_nama);
    }

    /**
     * 4. Test deteksi nama berbeda yang perlu verifikasi
     */
    public function test_view_flags_different_name_for_verification(): void
    {
        $alumni = $this->createAlumni('71200004', 'Deni Setiawan', 'https://linkedin.com/in/unknown-profile');

        LinkedinSyncResult::create([
            'biodata_id' => $alumni->id,
            'linkedin_url' => $alumni->linkedin_url,
            'scraped_data' => [
                'name' => 'Michael Johnson',
                'current_job' => 'DevOps Engineer',
                'current_company' => 'Google',
            ],
            'status' => 'pending',
            'scraped_at' => now(),
        ]);

        $plot = AlumniLinkedinPlot::find($alumni->id);

        $this->assertNotNull($plot);
        $this->assertFalse($plot->is_nama_sesuai);
        $this->assertEquals('Nama Berbeda (Perlu Verifikasi)', $plot->keterangan_nama);
    }

    /**
     * 5. Test status plotting ketika hasil sinkronisasi telah diapprove
     */
    public function test_view_identifies_approved_and_plotted_alumni(): void
    {
        $alumni = $this->createAlumni('71200005', 'Eko Nugroho', 'https://linkedin.com/in/ekonugroho');

        $perusahaan = Perusahaan::create([
            'nama_perusahaan' => 'PT GoTo Gojek Tokopedia',
            'status_verifikasi' => 'Terverifikasi',
        ]);

        $alumni->update([
            'posisi_jabatan' => 'Backend Developer',
            'perusahaan_id' => $perusahaan->id,
            'skills' => 'Go, PHP, MySQL',
        ]);

        LinkedinSyncResult::create([
            'biodata_id' => $alumni->id,
            'linkedin_url' => $alumni->linkedin_url,
            'scraped_data' => [
                'name' => 'Eko Nugroho',
                'current_job' => 'Backend Developer',
                'current_company' => 'PT GoTo Gojek Tokopedia',
            ],
            'status' => 'approved',
            'reviewed_at' => now(),
            'scraped_at' => now(),
        ]);

        $plot = AlumniLinkedinPlot::find($alumni->id);

        $this->assertNotNull($plot);
        $this->assertTrue($plot->is_nama_sesuai);
        $this->assertTrue($plot->is_terplot_ke_biodata);
        $this->assertEquals('Sudah Terplot (Approved)', $plot->status_plot_label);
        $this->assertEquals('PT GoTo Gojek Tokopedia', $plot->perusahaan_biodata);
        $this->assertEquals('Backend Developer', $plot->posisi_biodata);
    }

    /**
     * 6. Test Model Scopes (namaSesuai, terplot, pendingReview)
     */
    public function test_model_scopes_work_correctly(): void
    {
        $alumni1 = $this->createAlumni('71200006', 'Ferry Salim', 'https://linkedin.com/in/ferrysalim');
        $alumni2 = $this->createAlumni('71200007', 'Gita Gutawa', 'https://linkedin.com/in/gitagutawa');

        LinkedinSyncResult::create([
            'biodata_id' => $alumni1->id,
            'linkedin_url' => $alumni1->linkedin_url,
            'scraped_data' => ['name' => 'Ferry Salim', 'current_job' => 'Data Analyst'],
            'status' => 'pending',
        ]);

        LinkedinSyncResult::create([
            'biodata_id' => $alumni2->id,
            'linkedin_url' => $alumni2->linkedin_url,
            'scraped_data' => ['name' => 'Stranger Name', 'current_job' => 'Manager'],
            'status' => 'approved',
        ]);

        $this->assertEquals(1, AlumniLinkedinPlot::namaSesuai()->whereIn('biodata_id', [$alumni1->id, $alumni2->id])->count());
        $this->assertEquals(1, AlumniLinkedinPlot::pendingReview()->whereIn('biodata_id', [$alumni1->id, $alumni2->id])->count());
        $this->assertEquals(1, AlumniLinkedinPlot::terplot()->whereIn('biodata_id', [$alumni1->id, $alumni2->id])->count());
    }

    public function test_profile_location_is_not_reported_as_biodata_or_company_address_mapping(): void
    {
        $alumni = $this->createAlumni('71200008', 'Argo Wibowo', 'https://linkedin.com/in/argowibowo');
        $alumni->update(['alamat' => 'Jl. Timoho No. 45']);

        $result = LinkedinSyncResult::create([
            'biodata_id' => $alumni->id,
            'linkedin_url' => $alumni->linkedin_url,
            'scraped_data' => [[
                'full_name' => 'Argo Wibowo',
                'location' => 'Yogyakarta, Indonesia',
            ]],
            'status' => 'pending',
        ]);

        $locationRow = collect(LinkedinTraceHelper::buildTraceMapping($alumni->fresh(), $result))
            ->firstWhere('key', 'location');

        $this->assertSame('linkedin_sync_results', $locationRow['target_table']);
        $this->assertSame('scraped_data->location', $locationRow['target_column']);
        $this->assertSame('Tersimpan di Histori', $locationRow['status']);
        $this->assertSame('Yogyakarta, Indonesia', $locationRow['db_value']);
    }
}
