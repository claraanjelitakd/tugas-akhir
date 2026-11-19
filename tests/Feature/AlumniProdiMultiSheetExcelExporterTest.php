<?php

namespace Tests\Feature;

use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\Prodi;
use App\Models\User;
use App\Services\Export\AlumniProdiMultiSheetExcelExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

/**
 * AlumniProdiMultiSheetExcelExporterTest
 *
 * Menguji fungsionalitas dan integritas struktur berkas ekspor Excel multi-sheet per program studi.
 */
class AlumniProdiMultiSheetExcelExporterTest extends TestCase
{
    use RefreshDatabase;

    protected Prodi $prodi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prodi = Prodi::create([
            'kode_prodi' => '72',
            'nama_prodi' => 'Sistem Informasi',
        ]);

        $user = User::create([
            'username' => '72200001',
            'name' => 'Alumni Test',
            'email' => 'alumni.test@example.com',
            'password' => Hash::make('password123'),
            'role' => 'alumni',
            'must_change_password' => false,
        ]);

        DataAkademik::create([
            'nim' => '72200001',
            'nama' => 'Alumni Test',
            'prodi_id' => $this->prodi->id,
            'angkatan_masuk' => '2020',
            'tahun_lulus' => '2024',
            'tahun_akademik_lulus' => 'Genap 2023/2024',
            'ip_kumulatif' => '3.75',
        ]);

        Biodata::create([
            'user_id' => $user->id,
            'nim' => '72200001',
            'prodi_id' => $this->prodi->id,
            'warga_negara' => 'Indonesia (WNI)',
            'email_pribadi' => 'alumni.test@example.com',
            'nomor_telepon' => '081234567890',
        ]);
    }

    /**
     * Memastikan dokumen XML SpreadsheetML 5-sheet terbuat dengan benar untuk prodi tertentu.
     */
    public function test_it_generates_five_worksheets_for_prodi(): void
    {
        $xml = AlumniProdiMultiSheetExcelExporter::generateSpreadsheetXml($this->prodi);

        $this->assertNotEmpty($xml);
        $this->assertStringContainsString('ss:Name="Profil Alumni"', $xml);
        $this->assertStringContainsString('ss:Name="Kuesioner Univ"', $xml);
        $this->assertStringContainsString('ss:Name="Kuesioner Prodi"', $xml);
        $this->assertStringContainsString('ss:Name="Evaluasi Atasan"', $xml);
        $this->assertStringContainsString('ss:Name="Data LinkedIn"', $xml);
        $this->assertStringContainsString('Sistem Informasi', $xml);
        $this->assertStringContainsString('72200001', $xml);

        // Memastikan kolom yang telah dihapus tidak muncul di header Profil Alumni
        $this->assertStringNotContainsString('<Data ss:Type="String">Total SKS</Data>', $xml);
        $this->assertStringNotContainsString('<Data ss:Type="String">Total Angka Kualitas</Data>', $xml);
        $this->assertStringNotContainsString('<Data ss:Type="String">Email Kampus</Data>', $xml);
    }

    /**
     * Memastikan metode download mengembalikan StreamedResponse dengan header yang tepat.
     */
    public function test_it_returns_streamed_response(): void
    {
        $response = AlumniProdiMultiSheetExcelExporter::download($this->prodi->id);

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertEquals('application/vnd.ms-excel; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename=', (string) $response->headers->get('Content-Disposition'));
    }
}
