<?php

namespace Database\Seeders;

use App\Models\Atasan;
use App\Models\Biodata;
use App\Models\EvaluasiAtasan;
use App\Models\Kabupaten;
use App\Models\LinkedinSyncResult;
use App\Models\LogActivity;
use App\Models\Perusahaan;
use App\Models\Prodi;
use App\Models\Propinsi;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Class ScreenshotEnrichmentSeeder
 *
 * Menyiapkan data riil berkualitas tinggi untuk keperluan tangkapan layar (screenshot) Bab 3 & Bab 4:
 * 1. Master Perusahaan Resmi Terverifikasi dan Antrean Usulan dengan Deteksi Kemiripan Nama yang Kaya.
 * 2. Data Staging LinkedIn Alumni Zela Poiema (Lulusan 2025) berstatus 'pending' (Menunggu Review).
 * 3. Foto profil profesional nyata untuk alumni kohort 2026.
 * 4. Token publik evaluasi atasan yang siap diakses di peramban.
 * 5. Rekaman audit trail (LogActivity) dengan old_values & new_values terstruktur.
 */
class ScreenshotEnrichmentSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Menjalankan ScreenshotEnrichmentSeeder...');

        $this->enrichPerusahaanMasterDanPending();
        $this->enrichLinkedInZela2025();
        $this->enrichFotoAlumni2026();
        $this->enrichEvaluasiAtasan();
        $this->enrichLogActivity();

        $this->command?->info('ScreenshotEnrichmentSeeder selesai dijalankan dengan sukses!');
    }

    /**
     * Memastikan perusahaan induk berstatus 'Terverifikasi' dan usulan alumni berstatus 'Menunggu Verifikasi'.
     */
    private function enrichPerusahaanMasterDanPending(): void
    {
        $diy = Propinsi::where('kode_provinsi', '34')->first() ?? Propinsi::first();
        $sleman = Kabupaten::where('kode_kabupaten', '34.04')->first() ?? Kabupaten::where('kode_provinsi', $diy->kode_provinsi)->first();
        $kotaYogya = Kabupaten::where('kode_kabupaten', '34.71')->first() ?? $sleman;

        $dki = Propinsi::where('kode_provinsi', '31')->first() ?? $diy;
        $jaksel = Kabupaten::where('kode_kabupaten', '31.74')->first() ?? Kabupaten::where('kode_provinsi', $dki->kode_provinsi)->first();

        // 1. Pastikan Perusahaan Master Resmi Berstatus 'Terverifikasi'
        $canonicalMasters = [
            [
                'nama' => 'PT Bank Central Asia Tbk',
                'kode_provinsi' => $dki->kode_provinsi,
                'kode_kabupaten' => $jaksel->kode_kabupaten,
                'alamat' => 'Menara BCA, Grand Indonesia, Jl. M.H. Thamrin No. 1, Jakarta Pusat',
                'sektor' => 'Perbankan & Jasa Keuangan',
                'skala' => 'Nasional',
                'jenis_perusahaan' => 'Perusahaan swasta',
            ],
            [
                'nama' => 'PT Telkom Indonesia (Persero) Tbk',
                'kode_provinsi' => $dki->kode_provinsi,
                'kode_kabupaten' => $jaksel->kode_kabupaten,
                'alamat' => 'Telkom Landmark Tower, Jl. Jend. Gatot Subroto Kav. 52, Jakarta Selatan',
                'sektor' => 'Telekomunikasi & Digital',
                'skala' => 'Nasional',
                'jenis_perusahaan' => 'BUMN/BUMD',
            ],
            [
                'nama' => 'PT Gojek Indonesia',
                'kode_provinsi' => $dki->kode_provinsi,
                'kode_kabupaten' => $jaksel->kode_kabupaten,
                'alamat' => 'Gedung Pasaraya Blok M, Gedung B Lt. 6, Kebayoran Baru, Jakarta Selatan',
                'sektor' => 'Transportasi & Logistik On-Demand',
                'skala' => 'Nasional',
                'jenis_perusahaan' => 'Perusahaan swasta',
            ],
            [
                'nama' => 'PT Tokopedia',
                'kode_provinsi' => $dki->kode_provinsi,
                'kode_kabupaten' => $jaksel->kode_kabupaten,
                'alamat' => 'Tokopedia Tower Ciputra World 2, Jl. Prof. Dr. Satrio Kav. 11, Jakarta Selatan',
                'sektor' => 'E-Commerce & Layanan Digital',
                'skala' => 'Nasional',
                'jenis_perusahaan' => 'Perusahaan swasta',
            ],
            [
                'nama' => 'PT Shopee International Indonesia',
                'kode_provinsi' => $dki->kode_provinsi,
                'kode_kabupaten' => $jaksel->kode_kabupaten,
                'alamat' => 'Pacific Century Place Lt. 26, SCBD Lot 10, Jl. Jend. Sudirman Kav. 52-53, Jakarta',
                'sektor' => 'E-Commerce & Digital Platform',
                'skala' => 'Internasional',
                'jenis_perusahaan' => 'Perusahaan swasta',
            ],
            [
                'nama' => 'PT Astra Honda Motor',
                'kode_provinsi' => $dki->kode_provinsi,
                'kode_kabupaten' => $jaksel->kode_kabupaten,
                'alamat' => 'Jl. Laksda Yos Sudarso, Sunter I, Jakarta Utara',
                'sektor' => 'Manufaktur & Otomotif',
                'skala' => 'Nasional',
                'jenis_perusahaan' => 'Perusahaan swasta',
            ],
            [
                'nama' => 'PT Gameloft Indonesia',
                'kode_provinsi' => $diy->kode_provinsi,
                'kode_kabupaten' => $sleman->kode_kabupaten,
                'alamat' => 'Pacific Building Lt. 3-4, Jl. Laksda Adisucipto No. 157, Sleman, DI Yogyakarta',
                'sektor' => 'Pengembangan Game & Multimedia Digital',
                'skala' => 'Internasional',
                'jenis_perusahaan' => 'Perusahaan swasta',
            ],
            [
                'nama' => 'PT Bank Mandiri (Persero) Tbk',
                'kode_provinsi' => $dki->kode_provinsi,
                'kode_kabupaten' => $jaksel->kode_kabupaten,
                'alamat' => 'Plaza Mandiri, Jl. Jend. Gatot Subroto Kav. 36-38, Jakarta Selatan',
                'sektor' => 'Perbankan & Jasa Keuangan',
                'skala' => 'Nasional',
                'jenis_perusahaan' => 'BUMN/BUMD',
            ],
            [
                'nama' => 'PT Trinusa Travelindo (Traveloka)',
                'kode_provinsi' => $dki->kode_provinsi,
                'kode_kabupaten' => $jaksel->kode_kabupaten,
                'alamat' => 'Traveloka Campus, BSD Green Office Park, Tangerang',
                'sektor' => 'Teknologi Informasi & Agen Perjalanan Daring',
                'skala' => 'Internasional',
                'jenis_perusahaan' => 'Perusahaan swasta',
            ],
            [
                'nama' => 'RS Panti Rapih Yogyakarta',
                'kode_provinsi' => $diy->kode_provinsi,
                'kode_kabupaten' => $kotaYogya->kode_kabupaten,
                'alamat' => 'Jl. Cik Di Tiro No. 30, Terban, Gondokusuman, Kota Yogyakarta',
                'sektor' => 'Pelayanan Kesehatan & Rumah Sakit',
                'skala' => 'Regional/Lokal',
                'jenis_perusahaan' => 'Perusahaan swasta',
            ],
            [
                'nama' => 'Universitas Kristen Duta Wacana',
                'kode_provinsi' => $diy->kode_provinsi,
                'kode_kabupaten' => $kotaYogya->kode_kabupaten,
                'alamat' => 'Jl. Dr. Wahidin Sudirohusodo No. 5-25, Kotabaru, Kota Yogyakarta',
                'sektor' => 'Pendidikan Tinggi',
                'skala' => 'Nasional',
                'jenis_perusahaan' => 'Perguruan Tinggi',
            ],
        ];

        foreach ($canonicalMasters as $m) {
            Perusahaan::updateOrCreate(
                ['nama_perusahaan' => $m['nama']],
                [
                    'kode_provinsi' => $m['kode_provinsi'],
                    'kode_kabupaten' => $m['kode_kabupaten'],
                    'alamat' => $m['alamat'],
                    'sektor' => $m['sektor'],
                    'skala' => $m['skala'],
                    'jenis_perusahaan' => $m['jenis_perusahaan'],
                    'status_verifikasi' => 'Terverifikasi',
                    'jenis_lokasi' => 'Dalam Negeri',
                    'kode_negara' => 'ID',
                ]
            );
        }

        // 2. Buat Usulan Perusahaan Alumni yang Mirip dengan Status 'Menunggu Verifikasi'
        $pendingProposals = [
            [
                'nama' => 'Bank BCA Kantor Cabang Utama Yogyakarta',
                'alamat' => 'Jl. Jend. Sudirman No. 49-51, Kota Yogyakarta',
                'skala' => 'Nasional',
                'sektor' => 'Perbankan',
                'provinsi' => $diy->kode_provinsi,
                'kabupaten' => $kotaYogya->kode_kabupaten,
            ],
            [
                'nama' => 'PT Telkom Indonesia Witel Yogyakarta',
                'alamat' => 'Jl. Yos Sudarso No. 9, Kotabaru, Gondokusuman, Kota Yogyakarta',
                'skala' => 'Nasional',
                'sektor' => 'Telekomunikasi',
                'provinsi' => $diy->kode_provinsi,
                'kabupaten' => $kotaYogya->kode_kabupaten,
            ],
            [
                'nama' => 'Shopee Express Hub Sleman',
                'alamat' => 'Jl. Ring Road Utara, Maguwoharjo, Depok, Sleman',
                'skala' => 'Nasional',
                'sektor' => 'Logistik E-Commerce',
                'provinsi' => $diy->kode_provinsi,
                'kabupaten' => $sleman->kode_kabupaten,
            ],
            [
                'nama' => 'PT Tokopedia Mitra Jaya',
                'alamat' => 'Ruko Timoho Regency, Jl. Ipda Tut Harsono, Umbulharjo, Yogyakarta',
                'skala' => 'Nasional',
                'sektor' => 'Teknologi Informasi',
                'provinsi' => $diy->kode_provinsi,
                'kabupaten' => $kotaYogya->kode_kabupaten,
            ],
            [
                'nama' => 'Gameloft Studios Jogja',
                'alamat' => 'Pacific Building Lt. 3, Jl. Laksda Adisucipto, Sleman',
                'skala' => 'Internasional',
                'sektor' => 'Game Development',
                'provinsi' => $diy->kode_provinsi,
                'kabupaten' => $sleman->kode_kabupaten,
            ],
            [
                'nama' => 'Astra Motor Magelang Cabang Sleman',
                'alamat' => 'Jl. Magelang Km 7.5, Mlati, Sleman',
                'skala' => 'Regional/Lokal',
                'sektor' => 'Otomotif & Distribusi',
                'provinsi' => $diy->kode_provinsi,
                'kabupaten' => $sleman->kode_kabupaten,
            ],
            [
                'nama' => 'Bank Mandiri KCP Sudirman Yogyakarta',
                'alamat' => 'Jl. Jend. Sudirman No. 26, Gowongan, Jetis, Kota Yogyakarta',
                'skala' => 'Nasional',
                'sektor' => 'Perbankan',
                'provinsi' => $diy->kode_provinsi,
                'kabupaten' => $kotaYogya->kode_kabupaten,
            ],
            [
                'nama' => 'Gojek Tech Cabang DIY',
                'alamat' => 'Jl. Gejayan No. 12, Caturtunggal, Depok, Sleman',
                'skala' => 'Nasional',
                'sektor' => 'Transportasi Daring',
                'provinsi' => $diy->kode_provinsi,
                'kabupaten' => $sleman->kode_kabupaten,
            ],
            [
                'nama' => 'Traveloka Tech Center',
                'alamat' => 'Jl. Palagan Tentara Pelajar Km 8, Sariharjo, Ngaglik, Sleman',
                'skala' => 'Internasional',
                'sektor' => 'Teknologi Informasi',
                'provinsi' => $diy->kode_provinsi,
                'kabupaten' => $sleman->kode_kabupaten,
            ],
            [
                'nama' => 'Klinik Pratama Panti Rapih Gejayan',
                'alamat' => 'Jl. Affandi No. 28, Sleman, DI Yogyakarta',
                'skala' => 'Regional/Lokal',
                'sektor' => 'Kesehatan',
                'provinsi' => $diy->kode_provinsi,
                'kabupaten' => $sleman->kode_kabupaten,
            ],
        ];

        $prodiTI = Prodi::where('kode_prodi', '71')->first();
        $prodiSI = Prodi::where('kode_prodi', '72')->first();
        $userAdmin = User::where('role', 'superadmin')->first();

        foreach ($pendingProposals as $idx => $p) {
            $kodeProdi = ($idx % 2 === 0) ? $prodiTI?->kode_prodi : $prodiSI?->kode_prodi;

            Perusahaan::updateOrCreate(
                ['nama_perusahaan' => $p['nama']],
                [
                    'kode_provinsi' => $p['provinsi'],
                    'kode_kabupaten' => $p['kabupaten'],
                    'alamat' => $p['alamat'],
                    'sektor' => $p['sektor'],
                    'skala' => $p['skala'],
                    'jenis_perusahaan' => 'Perusahaan swasta',
                    'status_verifikasi' => 'Menunggu Verifikasi',
                    'jenis_lokasi' => 'Dalam Negeri',
                    'kode_negara' => 'ID',
                    'created_by_user_id' => $userAdmin?->id,
                    'created_by_kode_prodi' => $kodeProdi,
                ]
            );
        }
    }

    /**
     * Memastikan data staging LinkedIn Zela Poiema (Lulusan 2025) siap di-review.
     */
    private function enrichLinkedInZela2025(): void
    {
        $zelaBiodata = Biodata::where('nim', '72210501')->first();

        if (! $zelaBiodata) {
            $this->command?->warn('Biodata Zela (72210501) belum ada di database.');

            return;
        }

        $mockFile = storage_path('app/mock/apify/zela.json');
        if (! File::exists($mockFile)) {
            $mockFile = storage_path('app/mock/apify/zela-poiema-christy-mantohana-napitupulu.json');
        }

        $scrapedData = [];
        if (File::exists($mockFile)) {
            $scrapedData = json_decode(File::get($mockFile), true) ?: [];
        }

        $zelaBiodata->update([
            'foto' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?q=80&w=400&auto=format&fit=crop',
            'linkedin_url' => 'https://www.linkedin.com/in/zela-poiema-christy-mantohana-napitupulu',
            'linkedin_username' => 'zela-poiema-christy-mantohana-napitupulu',
        ]);

        // Jadikan seluruh status staging Zela 'pending' agar tombol "Review Hasil" tampil di antarmuka
        LinkedinSyncResult::where('biodata_id', $zelaBiodata->id)->update([
            'status' => 'pending',
            'scraped_data' => $scrapedData,
            'scraped_at' => now(),
            'reviewed_at' => null,
            'user_id' => null,
        ]);
    }

    /**
     * Memberikan foto profil profesional resolusi tinggi untuk alumni kohort 2026.
     */
    private function enrichFotoAlumni2026(): void
    {
        $portraits = [
            'Laki-laki' => [
                'https://images.unsplash.com/photo-1534528741775-53994a69daeb?q=80&w=400&auto=format&fit=crop',
                'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?q=80&w=400&auto=format&fit=crop',
                'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?q=80&w=400&auto=format&fit=crop',
                'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?q=80&w=400&auto=format&fit=crop',
                'https://images.unsplash.com/photo-1492562080023-ab3db95bfbce?q=80&w=400&auto=format&fit=crop',
            ],
            'Perempuan' => [
                'https://images.unsplash.com/photo-1580489944761-15a19d654956?q=80&w=400&auto=format&fit=crop',
                'https://images.unsplash.com/photo-1544005313-94ddf0286df2?q=80&w=400&auto=format&fit=crop',
                'https://images.unsplash.com/photo-1517841905240-472988babdf9?q=80&w=400&auto=format&fit=crop',
                'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?q=80&w=400&auto=format&fit=crop',
                'https://images.unsplash.com/photo-1534528741775-53994a69daeb?q=80&w=400&auto=format&fit=crop',
            ],
        ];

        $alumni2026 = Biodata::whereHas('dataAkademik', function ($q) {
            $q->where('tahun_lulus', 2026);
        })->with('dataAkademik')->take(20)->get();

        $maleIndex = 0;
        $femaleIndex = 0;

        foreach ($alumni2026 as $biodata) {
            $jk = $biodata->dataAkademik?->jenis_kelamin ?? 'Laki-laki';
            if ($jk === 'Perempuan') {
                $fotoUrl = $portraits['Perempuan'][$femaleIndex % count($portraits['Perempuan'])];
                $femaleIndex++;
            } else {
                $fotoUrl = $portraits['Laki-laki'][$maleIndex % count($portraits['Laki-laki'])];
                $maleIndex++;
            }

            $biodata->update(['foto' => $fotoUrl]);
        }
    }

    /**
     * Memastikan data evaluasi atasan memiliki sampel yang siap dibuka via token publik (UC-08).
     */
    private function enrichEvaluasiAtasan(): void
    {
        $alumniSiap = Biodata::whereHas('dataAkademik', fn ($q) => $q->where('tahun_lulus', 2026))
            ->whereNotNull('perusahaan_id')
            ->first();

        if (! $alumniSiap) {
            $alumniSiap = Biodata::whereNotNull('perusahaan_id')->first();
        }

        if (! $alumniSiap) {
            return;
        }

        // 1. Buat / Pastikan Atasan Langsung
        $atasan = Atasan::firstOrCreate(
            [
                'email' => 'supervisor.it@bca.co.id',
            ],
            [
                'nama' => 'Ir. Hendra Gunawan, M.T.',
                'telepon' => '081234567890',
                'perusahaan_id' => $alumniSiap->perusahaan_id,
            ]
        );

        $alumniSiap->update(['atasan_id' => $atasan->id]);

        // 2. Token Aktif Khusus Screenshot (UC-08)
        EvaluasiAtasan::updateOrCreate(
            ['token' => 'evaluasi-atasan-2026-demo'],
            [
                'biodata_id' => $alumniSiap->id,
                'perusahaan_id' => $alumniSiap->perusahaan_id,
                'atasan_id' => $atasan->id,
                'is_submitted' => false,
                'submitted_at' => null,
            ]
        );
    }

    /**
     * Memperkaya rekaman LogActivity untuk pengujian modal UC-23 (old_values vs new_values).
     */
    private function enrichLogActivity(): void
    {
        $userSuper = User::where('role', 'superadmin')->first();
        if (! $userSuper) {
            return;
        }

        $logs = [
            [
                'user_id' => $userSuper->id,
                'action' => 'Verifikasi Perusahaan',
                'description' => 'Superadmin menyetujui entitas perusahaan baru PT Shopee International Indonesia',
                'model_type' => 'App\Models\Perusahaan',
                'model_id' => '1',
                'old_values' => ['status_verifikasi' => 'Menunggu Verifikasi'],
                'new_values' => ['status_verifikasi' => 'Terverifikasi', 'verified_by' => $userSuper->id],
                'created_at' => Carbon::now()->subMinutes(15),
            ],
            [
                'user_id' => $userSuper->id,
                'action' => 'Sinkronisasi LinkedIn',
                'description' => 'Superadmin menyetujui data karier alumni ZELA POIEMA CHRISTY MANTOHANA NAPITUPULU dari LinkedIn',
                'model_type' => 'App\Models\LinkedinSyncResult',
                'model_id' => '1',
                'old_values' => ['status' => 'pending'],
                'new_values' => ['status' => 'approved', 'reviewed_by' => $userSuper->id],
                'created_at' => Carbon::now()->subHours(2),
            ],
            [
                'user_id' => $userSuper->id,
                'action' => 'Pembaruan Profil Alumni',
                'description' => 'Alumni memperbarui nomor telepon dan tautan profil profesional',
                'model_type' => 'App\Models\Biodata',
                'model_id' => '1',
                'old_values' => ['nomor_telepon' => '081299990001', 'linkedin_username' => null],
                'new_values' => ['nomor_telepon' => '081299990002', 'linkedin_username' => 'zela.poiema'],
                'created_at' => Carbon::now()->subDay(),
            ],
        ];

        foreach ($logs as $l) {
            LogActivity::create($l);
        }
    }
}
