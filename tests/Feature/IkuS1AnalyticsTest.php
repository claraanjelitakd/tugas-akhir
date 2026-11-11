<?php

namespace Tests\Feature;

use App\Models\Atasan;
use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\KuesionerJawaban;
use App\Models\KuesionerPertanyaan;
use App\Models\KuesionerSection;
use App\Models\Perusahaan;
use App\Models\Prodi;
use App\Models\Propinsi;
use App\Models\Ump;
use App\Models\User;
use App\Services\Analytics\TracerAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Memastikan formula dan sumber data indikator IKU S1 berjalan dari schema fresh.
 */
class IkuS1AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Filter tahun tidak mengulang tahun yang sama dan memilih tahun data
     * terbaru ketika halaman dibuka tanpa parameter tahun.
     */
    public function test_available_years_are_unique_and_default_to_latest_data_year(): void
    {
        DataAkademik::create([
            'nim' => '71180010', 'nama' => 'Kohort 2025', 'angkatan_masuk' => 2021,
            'status_mahasiswa' => 'Lulus', 'tahun_lulus' => 2025,
        ]);

        $chart = app(TracerAnalyticsService::class)->buildUniversityChart('iku_s1_aee');

        $this->assertSame(['ALL', '2025'], $chart['filters']['available_years']);
        $this->assertSame('2025', $chart['filters']['selected_year']);
    }

    /**
     * AEE hanya memakai populasi lulusan S1 dan tidak memasukkan mahasiswa
     * aktif atau status akademik non-lulus ke dalam denominator.
     */
    public function test_aee_uses_complete_academic_population_and_exclusions(): void
    {
        $prodi = Prodi::create(['kode_prodi' => '71', 'nama_prodi' => 'Informatika', 'jenjang' => 'S1']);

        DataAkademik::create([
            'nim' => '71180001', 'nama' => 'Lulus Tepat Waktu', 'prodi_id' => $prodi->id,
            'angkatan_masuk' => 2022, 'tahun_akademik_masuk' => '2022/2023 (Semester Ganjil)',
            'status_mahasiswa' => 'Lulus', 'tahun_akademik_lulus' => 'Genap 2025/2026', 'tahun_lulus' => 2026,
        ]);
        DataAkademik::create([
            'nim' => '71180002', 'nama' => 'Lulus Tidak Tepat Waktu', 'prodi_id' => $prodi->id,
            'angkatan_masuk' => 2022, 'tahun_akademik_masuk' => '2022/2023 (Semester Ganjil)',
            'status_mahasiswa' => 'Lulus', 'tahun_akademik_lulus' => 'Genap 2026/2027', 'tahun_lulus' => 2027,
        ]);

        foreach (['Drop Out', 'Pindah', 'Cuti'] as $index => $status) {
            DataAkademik::create([
                'nim' => '7118000'.($index + 3), 'nama' => $status, 'prodi_id' => $prodi->id,
                'angkatan_masuk' => 2022, 'tahun_akademik_masuk' => '2022/2023 (Semester Ganjil)',
                'status_mahasiswa' => $status,
            ]);
        }

        $chart = app(TracerAnalyticsService::class)->buildUniversityChart('iku_s1_aee', 'ALL');

        $this->assertSame(2, $chart['chart']['summary']['population']);
        $this->assertSame(2, $chart['chart']['summary']['eligible_count']);
        $this->assertSame(0, $chart['chart']['summary']['excluded_count']);
        $this->assertSame(1, $chart['chart']['summary']['on_time_count']);
        $this->assertSame(50.0, $chart['chart']['summary']['aee_realization']);
        $this->assertSame(200.0, $chart['chart']['summary']['aee_pt']);
        $this->assertSame(1, $chart['chart']['summary']['program_count']);
        $this->assertSame('Informatika', $chart['chart']['summary']['top_program_label']);
        $this->assertSame(50.0, $chart['chart']['summary']['top_program_aee']);
        $this->assertSame(60.0, $chart['chart']['summary']['status_target']);
    }

    /**
     * Batang AEE hanya memisahkan lulusan tepat waktu dari lulusan terlambat;
     * mahasiswa aktif tidak lagi menjadi kategori abu-abu pada grafik.
     */
    public function test_aee_separates_late_and_unidentified_eligible_students(): void
    {
        $prodi = Prodi::create(['kode_prodi' => '73', 'nama_prodi' => 'Manajemen', 'jenjang' => 'S1']);

        DataAkademik::create([
            'nim' => '73180001', 'nama' => 'Lulus Tepat Waktu', 'prodi_id' => $prodi->id,
            'angkatan_masuk' => 2022, 'tahun_akademik_masuk' => '2022/2023 (Semester Ganjil)',
            'status_mahasiswa' => 'Lulus', 'tahun_akademik_lulus' => 'Genap 2025/2026', 'tahun_lulus' => 2026,
        ]);
        DataAkademik::create([
            'nim' => '73180002', 'nama' => 'Lulus Terlambat', 'prodi_id' => $prodi->id,
            'angkatan_masuk' => 2022, 'tahun_akademik_masuk' => '2022/2023 (Semester Ganjil)',
            'status_mahasiswa' => 'Lulus', 'tahun_akademik_lulus' => 'Genap 2026/2027', 'tahun_lulus' => 2027,
        ]);
        DataAkademik::create([
            'nim' => '73180003', 'nama' => 'Status Belum Teridentifikasi', 'prodi_id' => $prodi->id,
            'angkatan_masuk' => 2022, 'tahun_akademik_masuk' => '2022/2023 (Semester Ganjil)',
            'status_mahasiswa' => 'Aktif',
        ]);

        $chart = app(TracerAnalyticsService::class)->buildUniversityChart('iku_s1_aee', 'ALL');
        $series = $chart['chart']['series'][0];
        $this->assertSame(1, $series['on_time']);
        $this->assertSame(1, $series['late']);
        $this->assertSame(0, $series['unidentified']);
        $this->assertSame(2, $series['population']);
        $this->assertSame(2, $series['eligible']);
        $this->assertSame(1, $chart['chart']['summary']['late_count']);
        $this->assertSame(0, $chart['chart']['summary']['unidentified_count']);
    }

    /**
     * Capaian karier memakai F8/F504/F506/F18d serta ambang 1,2 kali UMP,
     * bukan menebak bobot dari kolom experience.
     */
    public function test_outcome_chart_applies_structured_career_weight(): void
    {
        $prodi = Prodi::create(['kode_prodi' => '72', 'nama_prodi' => 'Sistem Informasi', 'jenjang' => 'S1']);
        Propinsi::create(['kode_provinsi' => '34', 'nama_provinsi' => 'DI Yogyakarta']);
        Ump::create(['kode_provinsi' => '34', 'tahun' => 2026, 'besaran' => 2000000]);

        $employee = $this->createGraduate($prodi, '72180001', 'Pekerja / Karyawan', 6000000, 2025);
        $student = $this->createGraduate($prodi, '72180002', 'Melanjutkan Pendidikan', null, 2025);
        $unclassified = $this->createGraduate($prodi, '72180003', 'Wiraswasta', 6000000, 2025);
        $company = Perusahaan::create([
            'nama_perusahaan' => 'Perusahaan Uji IKU', 'kode_provinsi' => '34', 'kode_negara' => 'ID',
            'alamat' => 'Jl. Cabang Uji No. 1', 'kode_pos' => '55111',
            'skala' => 'Nasional', 'status_verifikasi' => 'Terverifikasi',
        ]);
        $supervisor = Atasan::create([
            'perusahaan_id' => $company->id, 'nama' => 'Atasan Uji',
            'email' => 'atasan@example.test', 'telepon' => '081234567890',
        ]);
        $employee->update([
            'perusahaan_id' => $company->id, 'atasan_id' => $supervisor->id,
            'posisi_jabatan' => 'Product Analyst', 'skills' => 'Analisis Data, SQL',
            'pendidikan_tingkat' => 'S2', 'perguruan_tinggi' => 'Universitas Uji',
            'pendidikan_prodi' => 'Magister Manajemen',
        ]);
        $section = KuesionerSection::create(['kode_section' => 'IKU-S1-TEST', 'target' => 'univ', 'judul' => 'IKU S1']);

        $this->answer($employee, $section, 'F504', 'Tidak');
        $this->answer($employee, $section, 'F506', '2');
        $this->answer($student, $section, 'F18d', '2025-09-01');

        $chart = app(TracerAnalyticsService::class)->buildUniversityChart('iku_s1_outcomes', 'ALL', 2026);

        $this->assertSame(2, $chart['chart']['summary']['valid_respondents']);
        $this->assertSame(1.6, (float) $chart['chart']['summary']['weighted_score']);
        $this->assertSame(80.0, (float) $chart['chart']['summary']['percentage']);
        $this->assertCount(3, $chart['chart']['records']);
        $this->assertSame(['Karyawan', 'Wirausaha Lainnya', 'Melanjutkan Pendidikan'], array_column($chart['chart']['series'], 'label'));
        $employeeSeries = collect($chart['chart']['series'])->firstWhere('label', 'Karyawan');
        $otherEntrepreneurSeries = collect($chart['chart']['series'])->firstWhere('label', 'Wirausaha Lainnya');
        $studySeries = collect($chart['chart']['series'])->firstWhere('label', 'Melanjutkan Pendidikan');
        $this->assertSame(1, $employeeSeries['count']);
        $this->assertSame(1, $employeeSeries['valid_count']);
        $this->assertSame(2, $employeeSeries['percentage_denominator_count']);
        $this->assertSame('met', $employeeSeries['status_key']);
        $this->assertSame('#0D542B', $employeeSeries['status_color']);
        $this->assertSame(0.0, (float) $otherEntrepreneurSeries['weighted_score']);
        $this->assertSame('unavailable', $otherEntrepreneurSeries['status_key']);
        $this->assertSame('#CBD5E1', $otherEntrepreneurSeries['status_color']);
        $this->assertSame('warning', $studySeries['status_key']);
        $unclassifiedRecord = collect($chart['chart']['records'])->firstWhere('nim', $unclassified->nim);
        $this->assertSame('Wirausaha Lainnya', $unclassifiedRecord['career_category']);
        $this->assertNull($unclassifiedRecord['before_graduation']);

        $employeeRecord = collect($chart['chart']['records'])->firstWhere('nim', '72180001');
        $this->assertSame('Perusahaan Uji IKU', $employeeRecord['nama_perusahaan']);
        $this->assertSame('Jl. Cabang Uji No. 1, DI Yogyakarta, 55111', $employeeRecord['lokasi_kerja']);
        $this->assertSame('Atasan Uji', $employeeRecord['nama_atasan']);
        $this->assertSame('Product Analyst', $employeeRecord['posisi_jabatan']);
        $this->assertSame('Analisis Data, SQL', $employeeRecord['skills']);
        $this->assertSame('Karyawan + Studi Lanjut', $employeeRecord['career_status_label']);
        $this->assertStringContainsString('Universitas Uji', $employeeRecord['study_detail']);
    }

    /**
     * Kode opsi F18d tidak boleh dianggap tanggal studi lanjut dan peran
     * wirausaha pada jabatan tetap dipetakan ke kategori founder.
     */
    public function test_outcome_chart_rejects_study_option_codes_and_reads_entrepreneur_role(): void
    {
        $prodi = Prodi::create(['kode_prodi' => '72', 'nama_prodi' => 'Sistem Informasi', 'jenjang' => 'S1']);
        Propinsi::create(['kode_provinsi' => '34', 'nama_provinsi' => 'DI Yogyakarta']);
        Ump::create(['kode_provinsi' => '34', 'tahun' => 2026, 'besaran' => 2000000]);

        $employee = $this->createGraduate($prodi, '72180004', 'Pekerja / Karyawan', 6000000, 2025);
        $entrepreneur = $this->createGraduate($prodi, '72180005', 'Wiraswasta', 6000000, 2025);
        $entrepreneur->update(['posisi_jabatan' => 'Founder & Owner']);
        $section = KuesionerSection::create(['kode_section' => 'IKU-S1-ROLE', 'target' => 'univ', 'judul' => 'IKU S1 Role']);

        $this->answer($employee, $section, 'F504', 'Tidak');
        $this->answer($employee, $section, 'F506', '2');
        $this->answer($employee, $section, 'F18d', '4');
        $this->answer($entrepreneur, $section, 'F504', 'Tidak');
        $this->answer($entrepreneur, $section, 'F506', '2');

        $chart = app(TracerAnalyticsService::class)->buildUniversityChart('iku_s1_outcomes', 'ALL', 2026);
        $employeeRecord = collect($chart['chart']['records'])->firstWhere('nim', $employee->nim);
        $entrepreneurRecord = collect($chart['chart']['records'])->firstWhere('nim', $entrepreneur->nim);

        $this->assertSame('Karyawan', $employeeRecord['career_status_label']);
        $this->assertSame('', $employeeRecord['study_detail']);
        $this->assertSame('Founder / Co-Founder', $entrepreneurRecord['career_category']);
        $this->assertSame('Founder & Owner', $entrepreneurRecord['career_role']);
        $this->assertContains('Founder / Co-Founder', array_column($chart['chart']['series'], 'label'));
    }

    /**
     * Owner tidak boleh berubah menjadi Founder; kategori mengikuti role master.
     */
    public function test_outcome_chart_keeps_owner_as_other_entrepreneur_role(): void
    {
        $prodi = Prodi::create(['kode_prodi' => '72', 'nama_prodi' => 'Sistem Informasi', 'jenjang' => 'S1']);
        Propinsi::create(['kode_provinsi' => '34', 'nama_provinsi' => 'DI Yogyakarta']);
        Ump::create(['kode_provinsi' => '34', 'tahun' => 2026, 'besaran' => 2000000]);

        $owner = $this->createGraduate($prodi, '72180006', 'Wiraswasta', 6000000, 2025);
        $owner->update(['posisi_wiraswasta' => 'Owner']);

        $chart = app(TracerAnalyticsService::class)->buildUniversityChart('iku_s1_outcomes', 'ALL', 2026);
        $record = collect($chart['chart']['records'])->firstWhere('nim', $owner->nim);

        $this->assertSame('Wirausaha Lainnya', $record['career_category']);
        $this->assertSame('Owner', $record['career_role']);
    }

    /**
     * Status gabungan bekerja dan studi lanjut tetap masuk kategori utama Karyawan.
     */
    public function test_outcome_chart_keeps_combined_employee_in_employee_category(): void
    {
        $prodi = Prodi::create(['kode_prodi' => '72', 'nama_prodi' => 'Sistem Informasi', 'jenjang' => 'S1']);
        Propinsi::create(['kode_provinsi' => '34', 'nama_provinsi' => 'DI Yogyakarta']);
        Ump::create(['kode_provinsi' => '34', 'tahun' => 2026, 'besaran' => 2000000]);

        $employee = $this->createGraduate($prodi, '72180007', 'Karyawan + Studi Lanjut', 6000000, 2025);
        $employee->update([
            'posisi_jabatan' => 'Product Analyst',
            'pendidikan_tingkat' => 'S2',
            'perguruan_tinggi' => 'Universitas Uji',
            'pendidikan_prodi' => 'Magister Manajemen',
        ]);
        $section = KuesionerSection::create(['kode_section' => 'IKU-S1-COMBINED', 'target' => 'univ', 'judul' => 'IKU S1 Combined']);

        $this->answer($employee, $section, 'F504', 'Tidak');
        $this->answer($employee, $section, 'F506', '2');

        $chart = app(TracerAnalyticsService::class)->buildUniversityChart('iku_s1_outcomes', 'ALL', 2026);
        $record = collect($chart['chart']['records'])->firstWhere('nim', $employee->nim);

        $this->assertSame('Karyawan', $record['career_category']);
        $this->assertSame('Karyawan + Studi Lanjut', $record['career_status_label']);
        $this->assertContains('Karyawan', array_column($chart['chart']['series'], 'label'));
    }

    /**
     * Status studi lanjut dengan data pekerjaan tetap masuk kategori Karyawan.
     */
    public function test_outcome_chart_promotes_study_status_with_work_profile_to_employee(): void
    {
        $prodi = Prodi::create(['kode_prodi' => '72', 'nama_prodi' => 'Sistem Informasi', 'jenjang' => 'S1']);
        Propinsi::create(['kode_provinsi' => '34', 'nama_provinsi' => 'DI Yogyakarta']);
        Ump::create(['kode_provinsi' => '34', 'tahun' => 2026, 'besaran' => 2000000]);

        $company = Perusahaan::create([
            'nama_perusahaan' => 'Perusahaan Kerja dan Studi Uji',
            'kode_provinsi' => '34',
            'kode_negara' => 'ID',
            'status_verifikasi' => 'Terverifikasi',
        ]);
        $student = $this->createGraduate($prodi, '72180008', 'Melanjutkan Pendidikan', 6000000, 2025);
        $student->update([
            'perusahaan_id' => $company->id,
            'posisi_jabatan' => 'Product Analyst',
            'bidang_pekerjaan' => 'Teknologi Informasi',
            'pendidikan_tingkat' => 'S2',
            'perguruan_tinggi' => 'Universitas Uji',
            'pendidikan_prodi' => 'Magister Manajemen',
        ]);
        $section = KuesionerSection::create(['kode_section' => 'IKU-S1-STUDY-WORK', 'target' => 'univ', 'judul' => 'IKU S1 Study Work']);

        $this->answer($student, $section, 'F504', 'Tidak');
        $this->answer($student, $section, 'F506', '2');

        $chart = app(TracerAnalyticsService::class)->buildUniversityChart('iku_s1_outcomes', 'ALL', 2026);
        $record = collect($chart['chart']['records'])->firstWhere('nim', $student->nim);

        $this->assertSame('Karyawan', $record['career_category']);
        $this->assertSame('Karyawan + Studi Lanjut', $record['career_status_label']);
    }

    /**
     * Grafik UMP membedakan freelancer dari founder dan memakai bobot role
     * yang configurable, bukan menganggap semua status Wiraswasta sama.
     */
    public function test_ump_weighted_chart_classifies_freelancer_role(): void
    {
        $prodi = Prodi::create(['kode_prodi' => '72', 'nama_prodi' => 'Sistem Informasi', 'jenjang' => 'S1']);
        Propinsi::create(['kode_provinsi' => '34', 'nama_provinsi' => 'DI Yogyakarta']);
        Ump::create(['kode_provinsi' => '34', 'tahun' => 2026, 'besaran' => 2000000]);
        $freelancer = $this->createGraduate($prodi, '72180003', 'Wiraswasta', 3000000, 2025);
        $freelancer->update([
            'posisi_wiraswasta' => 'Freelancer / Konsultan Mandiri',
            'perusahaan_id' => Perusahaan::create([
                'nama_perusahaan' => 'Studio Freelancer Uji',
                'kode_provinsi' => '34',
                'kode_negara' => 'ID',
                'status_verifikasi' => 'Terverifikasi',
            ])->id,
        ]);
        $section = KuesionerSection::create(['kode_section' => 'IKU-S1-FREELANCE', 'target' => 'univ', 'judul' => 'IKU S1 Freelancer']);

        $this->answer($freelancer, $section, 'F504', 'Tidak');
        $this->answer($freelancer, $section, 'F506', '2');

        $chart = app(TracerAnalyticsService::class)->buildUniversityChart('iku_s1_ump_weighted', '2025', 2026);
        $record = $chart['chart']['records'][0];

        $this->assertSame('Freelancer', $record['career_category']);
        $this->assertSame(2400000.0, (float) $record['threshold']);
        $this->assertSame(0.5, (float) $record['weight']);
        $this->assertSame(1, $chart['chart']['summary']['above_threshold_count']);
        $this->assertSame('needs_biro3_confirmation', $chart['chart']['summary']['weight_confirmation_status']);
        $waitStat = $chart['chart']['wait_stats'][0];
        $this->assertSame(1, $waitStat['counts']['<6 bulan']);
        $this->assertSame(100.0, (float) $waitStat['percentages']['<6 bulan']);
    }

    /**
     * Membuat lulusan S1 beserta profil karier minimal untuk fixture indikator.
     */
    private function createGraduate(Prodi $prodi, string $nim, string $career, ?float $salary, int $year): Biodata
    {
        $user = User::create([
            'username' => $nim, 'name' => 'Alumni '.$nim, 'email' => $nim.'@example.test',
            'password' => Hash::make('password'), 'role' => 'alumni', 'prodi_id' => $prodi->id,
            'must_change_password' => false,
        ]);
        DataAkademik::create([
            'nim' => $nim, 'nama' => $user->name, 'prodi_id' => $prodi->id,
            'angkatan_masuk' => $year - 4, 'tahun_akademik_masuk' => ($year - 4).'/'.$year.' (Semester Ganjil)',
            'status_mahasiswa' => 'Lulus', 'tahun_akademik_lulus' => 'Genap '.($year - 1).'/'.$year,
            'tahun_lulus' => $year, 'tanggal_lulus' => $year.'-06-30',
        ]);

        return Biodata::create([
            'user_id' => $user->id, 'nim' => $nim, 'prodi_id' => $prodi->id,
            'email_pribadi' => $user->email, 'kategori_pekerjaan' => $career, 'gaji' => $salary,
        ]);
    }

    /**
     * Menyimpan jawaban tracer sederhana pada section universitas.
     */
    private function answer(Biodata $biodata, KuesionerSection $section, string $code, string $value): void
    {
        $question = KuesionerPertanyaan::create([
            'kode_pertanyaan' => $code.'-'.$biodata->nim, 'teks_pertanyaan' => $code, 'tipe' => 'text',
        ]);

        KuesionerJawaban::create([
            'responden_type' => 'alumni', 'biodata_id' => $biodata->id, 'section_id' => $section->id,
            'pertanyaan_id' => $question->id, 'kode_pertanyaan' => $code, 'jawaban_teks' => $value,
        ]);
    }
}
