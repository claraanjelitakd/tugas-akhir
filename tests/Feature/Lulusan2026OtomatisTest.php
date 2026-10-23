<?php

namespace Tests\Feature;

use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\Kabupaten;
use App\Models\Prodi;
use App\Models\Propinsi;
use App\Models\User;
use App\Models\Yudisium;
use Database\Seeders\Lulusan2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Lulusan2026OtomatisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Prodi::firstOrCreate(['kode_prodi' => '71'], ['nama_prodi' => 'Informatika']);
        Prodi::firstOrCreate(['kode_prodi' => '72'], ['nama_prodi' => 'Sistem Informasi']);

        $this->seed(Lulusan2026Seeder::class);
    }

    /**
     * Membuktikan bahwa 10 alumni lulusan 2026 yang baru otomatis menarik data akademik,
     * dan kolom profil di biodata kosong/null.
     */
    public function test_alumni_2026_otomatis_menarik_data_akademik_dan_profil_kosong(): void
    {
        $nims = [
            '71220011', '71220012', '71220013', '71220014', '71220015',
            '72220011', '72220012', '72220013', '72220014', '72220015',
        ];

        foreach ($nims as $nim) {
            $biodata = Biodata::where('nim', $nim)->first();

            $this->assertNotNull($biodata, "Biodata untuk NIM {$nim} harus ada.");

            // 1. Bukti: Kolom fisik di tabel biodata TIDAK menyimpan nama & tahun lulus
            $this->assertArrayNotHasKey('nama', $biodata->getAttributes(), 'Tabel biodata tidak boleh menduplikasi kolom nama.');
            $this->assertArrayNotHasKey('tahun_lulus', $biodata->getAttributes(), 'Tabel biodata tidak boleh menduplikasi kolom tahun_lulus.');

            // 2. Bukti: Data nama dan tahun lulus otomatis ditarik via accessor dari data_akademik
            $dataAkademik = DataAkademik::where('nim', $nim)->first();
            $this->assertNotNull($dataAkademik);
            $this->assertEquals($dataAkademik->nama, $biodata->nama, "Nama di Biodata harus persis sama dengan DataAkademik ({$dataAkademik->nama})");
            $this->assertEquals('2026', $biodata->tahun_lulus, 'Tahun lulus harus otomatis 2026.');

            // 3. Bukti: Profil karier & perusahaan murni kosong (NULL)
            $this->assertNull($biodata->posisi_jabatan, 'Posisi jabatan harus null / kosong.');
            $this->assertNull($biodata->perusahaan_id, 'Perusahaan ID harus null / kosong.');
            $this->assertNull($biodata->gaji, 'Gaji harus null / kosong.');

            // 4. Bukti: Database View v_alumni_profile_summary otomatis menarik data tanpa duplikasi
            $viewRow = DB::table('v_alumni_profile_summary')->where('nim', $nim)->first();
            $this->assertNotNull($viewRow, "View v_alumni_profile_summary harus menampilkan alumni {$nim}.");
            $this->assertEquals($dataAkademik->nama, $viewRow->nama);
            $this->assertEquals(2026, $viewRow->tahun_lulus);
            $this->assertNull($viewRow->posisi_jabatan);
            $this->assertNull($viewRow->perusahaan_id);

            // 5. Bukti: Akun User otomatis dibuat dengan tanggal lahir sebagai default password
            $user = User::where('username', $nim)->first();
            $this->assertNotNull($user, "Akun user {$nim} harus ada.");
            $this->assertEquals('alumni', $user->role);
        }
    }

    /**
     * Membuktikan bahwa CUKUP menambahkan di data_akademik dan yudisium,
     * sistem secara otomatis (via model event & view) membuat akun user & skeleton biodata
     * dan langsung menarik seluruh datanya secara otomatis tanpa perlu mengisi tabel biodata secara manual!
     */
    public function test_hanya_tambah_data_akademik_dan_yudisium_otomatis_terbuat_biodata_dan_tertarik_profilnya(): void
    {
        $testNim = '71220099';

        $propinsi = Propinsi::firstOrCreate(['kode_provinsi' => '34'], ['nama_provinsi' => 'DI Yogyakarta']);
        $kabupaten = Kabupaten::firstOrCreate(['kode_kabupaten' => '34.04'], ['propinsi_id' => $propinsi->id, 'nama_kabupaten' => 'Kabupaten Sleman']);

        // 1. Tambahkan HANYA di data_akademik
        $dataAkademik = DataAkademik::create([
            'nim' => $testNim,
            'nama' => 'Bintang Pratama Putra',
            'angkatan_masuk' => '2022',
            'status_mahasiswa' => 'L',
            'tahun_akademik_lulus' => 'Genap 2025/2026',
            'tahun_lulus' => 2026,
            'tanggal_lulus' => '2026-09-01',
            'ip_kumulatif' => 3.95,
            'total_sks' => 144,
            'tempat_lahir' => 'Yogyakarta',
            'tanggal_lahir' => '2002-12-25',
            'agama' => 'Kristen Protestan',
            'jenis_kelamin' => 'Laki-laki',
            'kabupaten_id' => $kabupaten->id,
            'propinsi_id' => $propinsi->id,
        ]);

        // Pastikan saat ini BELUM ada baris di biodata
        $this->assertNull(Biodata::where('nim', $testNim)->first());

        // 2. Tambahkan di yudisium dengan status 'Lulus' (Trigger Lifecycle Event)
        $yudisium = Yudisium::create([
            'nim' => $testNim,
            'judul_ta' => 'Pengembangan Sistem Cerdas Terintegrasi',
            'status_lulus' => 'Lulus',
            'keterangan_hasil_yudisium' => 'Lulus dengan Pujian (Cumlaude)',
        ]);

        // 4. Verifikasi: Baris biodata dan user OTOMATIS tercipta oleh event hook!
        $biodataOtomatis = Biodata::where('nim', $testNim)->first();
        $this->assertNotNull($biodataOtomatis, 'Baris Biodata harus otomatis tercipta ketika yudisium berstatus Lulus!');

        $userOtomatis = User::where('username', $testNim)->first();
        $this->assertNotNull($userOtomatis, 'Akun User harus otomatis tercipta!');
        $this->assertEquals('alumni', $userOtomatis->role);

        // 5. Verifikasi: Seluruh data otomatis ketarik via Accessor & SQL View
        $this->assertEquals('Bintang Pratama Putra', $biodataOtomatis->nama);
        $this->assertEquals('2026', $biodataOtomatis->tahun_lulus);
        $this->assertStringContainsString('2002-12-25', (string) $biodataOtomatis->tanggal_lahir);
        $this->assertNull($biodataOtomatis->posisi_jabatan);
        $this->assertNull($biodataOtomatis->perusahaan_id);

        $viewRow = DB::table('v_alumni_profile_summary')->where('nim', $testNim)->first();
        $this->assertNotNull($viewRow);
        $this->assertEquals('Bintang Pratama Putra', $viewRow->nama);
        $this->assertEquals(2026, $viewRow->tahun_lulus);
        $this->assertEquals('Ir. Bambang Putra', $viewRow->nama_orang_tua);
    }
}
