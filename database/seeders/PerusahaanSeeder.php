<?php

namespace Database\Seeders;

use App\Models\Kabupaten;
use App\Models\Perusahaan;
use App\Models\Propinsi;
use Illuminate\Database\Seeder;

/**
 * Seeder Perusahaan
 *
 * Mengisi data master institusi dan perusahaan tempat alumni bekerja dengan atribut lengkap:
 * - nama_perusahaan
 * - kode_provinsi & kode_kabupaten
 * - alamat & kode_pos
 * - sektor
 * - skala (Regional/Lokal, Nasional, Internasional)
 * - jenis_perusahaan (Instansi pemerintah, BUMN/BUMD, Perusahaan swasta, Wiraswasta, NGO, Multilateral, Lainnya)
 * - jenis_perusahaan_lainnya
 * - jenis_lokasi (Dalam Negeri / Luar Negeri)
 * - kode_negara
 * - status_verifikasi (Terverifikasi / Menunggu Verifikasi)
 */
class PerusahaanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. DI Yogyakarta & Sleman / Kota Yogyakarta
        $diy = Propinsi::where('kode_provinsi', '34')
            ->orWhere('nama_provinsi', 'LIKE', '%Yogyakarta%')
            ->first() ?? Propinsi::firstOrCreate(['nama_provinsi' => 'DI Yogyakarta', 'kode_provinsi' => '34']);

        $sleman = Kabupaten::where('kode_kabupaten', '34.04')
            ->orWhere(function ($q) use ($diy) {
                $q->where('kode_provinsi', $diy->kode_provinsi)->where('nama_kabupaten', 'LIKE', '%Sleman%');
            })
            ->first() ?? Kabupaten::firstOrCreate(['kode_provinsi' => $diy->kode_provinsi, 'nama_kabupaten' => 'Sleman', 'kode_kabupaten' => '34.04']);

        $kotaYogya = Kabupaten::where('kode_kabupaten', '34.71')
            ->orWhere(function ($q) use ($diy) {
                $q->where('kode_provinsi', $diy->kode_provinsi)->where('nama_kabupaten', 'LIKE', '%Yogyakarta%');
            })
            ->first() ?? Kabupaten::firstOrCreate(['kode_provinsi' => $diy->kode_provinsi, 'nama_kabupaten' => 'Kota Yogyakarta', 'kode_kabupaten' => '34.71']);

        // 2. DKI Jakarta & Kota Jakarta Pusat / Jakarta Selatan
        $jakarta = Propinsi::where('kode_provinsi', '31')
            ->orWhere('nama_provinsi', 'LIKE', '%Jakarta%')
            ->first() ?? Propinsi::firstOrCreate(['nama_provinsi' => 'DKI Jakarta', 'kode_provinsi' => '31']);

        $jakpus = Kabupaten::where('kode_kabupaten', '31.71')
            ->orWhere(function ($q) use ($jakarta) {
                $q->where('kode_provinsi', $jakarta->kode_provinsi)->where('nama_kabupaten', 'LIKE', '%Jakarta Pusat%');
            })
            ->first() ?? Kabupaten::firstOrCreate(['kode_provinsi' => $jakarta->kode_provinsi, 'nama_kabupaten' => 'Kota Jakarta Pusat', 'kode_kabupaten' => '31.71']);

        $jaksel = Kabupaten::where('kode_kabupaten', '31.74')
            ->orWhere(function ($q) use ($jakarta) {
                $q->where('kode_provinsi', $jakarta->kode_provinsi)->where('nama_kabupaten', 'LIKE', '%Jakarta Selatan%');
            })
            ->first() ?? Kabupaten::firstOrCreate(['kode_provinsi' => $jakarta->kode_provinsi, 'nama_kabupaten' => 'Kota Jakarta Selatan', 'kode_kabupaten' => '31.74']);

        // 3. Jawa Barat & Bandung
        $jabar = Propinsi::where('kode_provinsi', '32')
            ->orWhere('nama_provinsi', 'LIKE', '%Jawa Barat%')
            ->first() ?? Propinsi::firstOrCreate(['nama_provinsi' => 'Jawa Barat', 'kode_provinsi' => '32']);

        $bandung = Kabupaten::where('kode_kabupaten', '32.73')
            ->orWhere(function ($q) use ($jabar) {
                $q->where('kode_provinsi', $jabar->kode_provinsi)->where('nama_kabupaten', 'LIKE', '%Bandung%');
            })
            ->first() ?? Kabupaten::firstOrCreate(['kode_provinsi' => $jabar->kode_provinsi, 'nama_kabupaten' => 'Kota Bandung', 'kode_kabupaten' => '32.73']);

        // 4. Jawa Timur & Surabaya
        $jatim = Propinsi::where('kode_provinsi', '35')
            ->orWhere('nama_provinsi', 'LIKE', '%Jawa Timur%')
            ->first() ?? Propinsi::firstOrCreate(['nama_provinsi' => 'Jawa Timur', 'kode_provinsi' => '35']);

        $surabaya = Kabupaten::where('kode_kabupaten', '35.78')
            ->orWhere(function ($q) use ($jatim) {
                $q->where('kode_provinsi', $jatim->kode_provinsi)->where('nama_kabupaten', 'LIKE', '%Surabaya%');
            })
            ->first() ?? Kabupaten::firstOrCreate(['kode_provinsi' => $jatim->kode_provinsi, 'nama_kabupaten' => 'Kota Surabaya', 'kode_kabupaten' => '35.78']);

        // 5. Bali & Badung
        $bali = Propinsi::where('kode_provinsi', '51')
            ->orWhere('nama_provinsi', 'LIKE', '%Bali%')
            ->first() ?? Propinsi::firstOrCreate(['nama_provinsi' => 'Bali', 'kode_provinsi' => '51']);

        $badung = Kabupaten::where('kode_kabupaten', '51.03')
            ->orWhere(function ($q) use ($bali) {
                $q->where('kode_provinsi', $bali->kode_provinsi)->where('nama_kabupaten', 'LIKE', '%Badung%');
            })
            ->first() ?? Kabupaten::firstOrCreate(['kode_provinsi' => $bali->kode_provinsi, 'nama_kabupaten' => 'Badung', 'kode_kabupaten' => '51.03']);

        // 6. Aceh & Aceh Selatan
        $aceh = Propinsi::where('kode_provinsi', '11')
            ->orWhere('nama_provinsi', 'LIKE', '%Aceh%')
            ->first() ?? Propinsi::firstOrCreate(['nama_provinsi' => 'Aceh (NAD)', 'kode_provinsi' => '11']);

        $acehSelatan = Kabupaten::where('kode_kabupaten', '11.01')
            ->orWhere(function ($q) use ($aceh) {
                $q->where('kode_provinsi', $aceh->kode_provinsi)->where('nama_kabupaten', 'LIKE', '%Aceh Selatan%');
            })
            ->first() ?? Kabupaten::firstOrCreate(['kode_provinsi' => $aceh->kode_provinsi, 'nama_kabupaten' => 'Aceh Selatan', 'kode_kabupaten' => '11.01']);

        // 7. Papua & Mimika
        $papua = Propinsi::where('kode_provinsi', '94')
            ->orWhere('nama_provinsi', 'LIKE', '%Papua%')
            ->first() ?? Propinsi::firstOrCreate(['nama_provinsi' => 'Papua', 'kode_provinsi' => '94']);

        $mimika = Kabupaten::where('kode_kabupaten', '94.12')
            ->orWhere(function ($q) use ($papua) {
                $q->where('kode_provinsi', $papua->kode_provinsi)->where('nama_kabupaten', 'LIKE', '%Mimika%');
            })
            ->first() ?? Kabupaten::firstOrCreate(['kode_provinsi' => $papua->kode_provinsi, 'nama_kabupaten' => 'Mimika', 'kode_kabupaten' => '94.12']);

        $perusahaans = [
            // ==========================================
            // 1. Perusahaan di DI Yogyakarta (Sleman & Kota Yogyakarta)
            // ==========================================
            [
                'nama_perusahaan' => 'PT Gameloft Indonesia',
                'kode_provinsi' => $diy->kode_provinsi,
                'kode_kabupaten' => $sleman->kode_kabupaten,
                'alamat' => 'Pacific Building, Jl. Laksda Adisucipto No. 157, Sleman',
                'kode_pos' => '55281',
                'sektor' => 'Teknologi Informasi & Game Development',
                'skala' => 'Internasional',
                'jenis_perusahaan' => 'Perusahaan swasta',
                'jenis_perusahaan_lainnya' => null,
                'jenis_lokasi' => 'Dalam Negeri',
                'kode_negara' => 'ID',
                'status_verifikasi' => 'Terverifikasi',
            ],
            [
                'nama_perusahaan' => 'PT Niagahoster',
                'kode_provinsi' => $diy->kode_provinsi,
                'kode_kabupaten' => $sleman->kode_kabupaten,
                'alamat' => 'Jl. Palagan Tentara Pelajar No. 81, Sleman',
                'kode_pos' => '55581',
                'sektor' => 'Hosting, Cloud & Web Services',
                'skala' => 'Nasional',
                'jenis_perusahaan' => 'Perusahaan swasta',
                'jenis_perusahaan_lainnya' => null,
                'jenis_lokasi' => 'Dalam Negeri',
                'kode_negara' => 'ID',
                'status_verifikasi' => 'Terverifikasi',
            ],
            [
                'nama_perusahaan' => 'PT Djarum Sleman Regional',
                'kode_provinsi' => $diy->kode_provinsi,
                'kode_kabupaten' => $sleman->kode_kabupaten,
                'alamat' => 'Jl. Magelang Km 7.5, Mlati, Sleman',
                'kode_pos' => '55284',
                'sektor' => 'FMCG, Distribusi & Manufaktur',
                'skala' => 'Nasional',
                'jenis_perusahaan' => 'Perusahaan swasta',
                'jenis_perusahaan_lainnya' => null,
                'jenis_lokasi' => 'Dalam Negeri',
                'kode_negara' => 'ID',
                'status_verifikasi' => 'Terverifikasi',
            ],
            [
                'nama_perusahaan' => 'CV Javan Cipta Solusi',
                'kode_provinsi' => $diy->kode_provinsi,
                'kode_kabupaten' => $sleman->kode_kabupaten,
                'alamat' => 'Jl. Kaliurang Km 9.2, Ngaglik, Sleman',
                'kode_pos' => '55581',
                'sektor' => 'Jasa Konsultan TI & Software Engineering',
                'skala' => 'Regional/Lokal',
                'jenis_perusahaan' => 'Perusahaan swasta',
                'jenis_perusahaan_lainnya' => null,
                'jenis_lokasi' => 'Dalam Negeri',
                'kode_negara' => 'ID',
                'status_verifikasi' => 'Terverifikasi',
            ],
            [
                'nama_perusahaan' => 'RS Bethesda Yogyakarta',
                'kode_provinsi' => $diy->kode_provinsi,
                'kode_kabupaten' => $kotaYogya->kode_kabupaten,
                'alamat' => 'Jl. Jend. Sudirman No. 70, Gondokusuman, Kota Yogyakarta',
                'kode_pos' => '55224',
                'sektor' => 'Pelayanan Kesehatan & Rumah Sakit',
                'skala' => 'Regional/Lokal',
                'jenis_perusahaan' => 'Organisasi non-profit/Lembaga Swadaya Masyarakat',
                'jenis_perusahaan_lainnya' => null,
                'jenis_lokasi' => 'Dalam Negeri',
                'kode_negara' => 'ID',
                'status_verifikasi' => 'Terverifikasi',
            ],
            [
                'nama_perusahaan' => 'Universitas Kristen Duta Wacana (UKDW)',
                'kode_provinsi' => $diy->kode_provinsi,
                'kode_kabupaten' => $kotaYogya->kode_kabupaten,
                'alamat' => 'Jl. Dr. Wahidin Sudirohusodo No. 5-25, Gondokusuman, Kota Yogyakarta',
                'kode_pos' => '55224',
                'sektor' => 'Pendidikan Tinggi, Riset & Akademik',
                'skala' => 'Nasional',
                'jenis_perusahaan' => 'Organisasi non-profit/Lembaga Swadaya Masyarakat',
                'jenis_perusahaan_lainnya' => null,
                'jenis_lokasi' => 'Dalam Negeri',
                'kode_negara' => 'ID',
                'status_verifikasi' => 'Terverifikasi',
            ],
            [
                'nama_perusahaan' => 'Dinas Komunikasi dan Informatika DIY',
                'kode_provinsi' => $diy->kode_provinsi,
                'kode_kabupaten' => $kotaYogya->kode_kabupaten,
                'alamat' => 'Jl. Brigjen Katamso No. 1, Gondomanan, Kota Yogyakarta',
                'kode_pos' => '55121',
                'sektor' => 'Pemerintahan & Pelayanan Publik',
                'skala' => 'Regional/Lokal',
                'jenis_perusahaan' => 'Instansi pemerintah',
                'jenis_perusahaan_lainnya' => null,
                'jenis_lokasi' => 'Dalam Negeri',
                'kode_negara' => 'ID',
                'status_verifikasi' => 'Terverifikasi',
            ],
            [
                'nama_perusahaan' => 'PT Bank BPD DIY',
                'kode_provinsi' => $diy->kode_provinsi,
                'kode_kabupaten' => $kotaYogya->kode_kabupaten,
                'alamat' => 'Jl. Tentara Pelajar No. 7, Bumijo, Jetis, Kota Yogyakarta',
                'kode_pos' => '55231',
                'sektor' => 'Perbankan Daerah & Finansial',
                'skala' => 'Regional/Lokal',
                'jenis_perusahaan' => 'BUMN/BUMD',
                'jenis_perusahaan_lainnya' => null,
                'jenis_lokasi' => 'Dalam Negeri',
                'kode_negara' => 'ID',
                'status_verifikasi' => 'Terverifikasi',
            ],
            [
                'nama_perusahaan' => 'Studio Kasatria Arsitektur & Desain',
                'kode_provinsi' => $diy->kode_provinsi,
                'kode_kabupaten' => $sleman->kode_kabupaten,
                'alamat' => 'Jl. Damai No. 12, Ngaglik, Sleman',
                'kode_pos' => '55581',
                'sektor' => 'Jasa Perancangan Arsitektur & Desain Interior',
                'skala' => 'Regional/Lokal',
                'jenis_perusahaan' => 'Wiraswasta/perusahaan sendiri',
                'jenis_perusahaan_lainnya' => null,
                'jenis_lokasi' => 'Dalam Negeri',
                'kode_negara' => 'ID',
                'status_verifikasi' => 'Terverifikasi',
            ],

            // ==========================================
            // 2. Perusahaan di DKI Jakarta (Jakarta Pusat & Selatan)
            // ==========================================
            [
                'nama_perusahaan' => 'PT Bank Central Asia Tbk (Kantor Pusat)',
                'kode_provinsi' => $jakarta->kode_provinsi,
                'kode_kabupaten' => $jakpus->kode_kabupaten,
                'alamat' => 'Menara BCA, Grand Indonesia, Jl. M.H. Thamrin No. 1, Kota Jakarta Pusat',
                'kode_pos' => '10310',
                'sektor' => 'Perbankan & Layanan Finansial',
                'skala' => 'Nasional',
                'jenis_perusahaan' => 'Perusahaan swasta',
                'jenis_perusahaan_lainnya' => null,
                'jenis_lokasi' => 'Dalam Negeri',
                'kode_negara' => 'ID',
                'status_verifikasi' => 'Terverifikasi',
            ],
            [
                'nama_perusahaan' => 'PT Telekomunikasi Indonesia Tbk (Telkom Landmark)',
                'kode_provinsi' => $jakarta->kode_provinsi,
                'kode_kabupaten' => $jaksel->kode_kabupaten,
                'alamat' => 'The Telkom Hub, Jl. Jend. Gatot Subroto Kav. 52, Kota Jakarta Selatan',
                'kode_pos' => '12710',
                'sektor' => 'Telekomunikasi, Digital & Jaringan',
                'skala' => 'Nasional',
                'jenis_perusahaan' => 'BUMN/BUMD',
                'jenis_perusahaan_lainnya' => null,
                'jenis_lokasi' => 'Dalam Negeri',
                'kode_negara' => 'ID',
                'status_verifikasi' => 'Terverifikasi',
            ],
            [
                'nama_perusahaan' => 'PT Tokopedia (GoTo Group)',
                'kode_provinsi' => $jakarta->kode_provinsi,
                'kode_kabupaten' => $jaksel->kode_kabupaten,
                'alamat' => 'Tokopedia Tower Ciputra World 2, Jl. Prof. DR. Satrio Kav. 11, Kota Jakarta Selatan',
                'kode_pos' => '12930',
                'sektor' => 'E-Commerce & Layanan Finansial Digital',
                'skala' => 'Nasional',
                'jenis_perusahaan' => 'Perusahaan swasta',
                'jenis_perusahaan_lainnya' => null,
                'jenis_lokasi' => 'Dalam Negeri',
                'kode_negara' => 'ID',
                'status_verifikasi' => 'Terverifikasi',
            ],
            [
                'nama_perusahaan' => 'PT Astra International Tbk',
                'kode_provinsi' => $jakarta->kode_provinsi,
                'kode_kabupaten' => $jakpus->kode_kabupaten,
                'alamat' => 'Menara Astra, Jl. Jend. Sudirman Kav. 5-6, Kota Jakarta Pusat',
                'kode_pos' => '10220',
                'sektor' => 'Otomotif, Jasa Keuangan & Agribisnis',
                'skala' => 'Internasional',
                'jenis_perusahaan' => 'Perusahaan swasta',
                'jenis_perusahaan_lainnya' => null,
                'jenis_lokasi' => 'Dalam Negeri',
                'kode_negara' => 'ID',
                'status_verifikasi' => 'Terverifikasi',
            ],
            [
                'nama_perusahaan' => 'PT Pertamina (Persero) Pusat',
                'kode_provinsi' => $jakarta->kode_provinsi,
                'kode_kabupaten' => $jakpus->kode_kabupaten,
                'alamat' => 'Jl. Medan Merdeka Timur No. 1A, Gambir, Kota Jakarta Pusat',
                'kode_pos' => '10110',
                'sektor' => 'Energi, Minyak, Gas & Petrokimia',
                'skala' => 'Internasional',
                'jenis_perusahaan' => 'BUMN/BUMD',
                'jenis_perusahaan_lainnya' => null,
                'jenis_lokasi' => 'Dalam Negeri',
                'kode_negara' => 'ID',
                'status_verifikasi' => 'Terverifikasi',
            ],
            [
                'nama_perusahaan' => 'PT Bank Mandiri (Persero) Tbk',
                'kode_provinsi' => $jakarta->kode_provinsi,
                'kode_kabupaten' => $jaksel->kode_kabupaten,
                'alamat' => 'Plaza Mandiri, Jl. Jend. Gatot Subroto Kav. 36-38, Kota Jakarta Selatan',
                'kode_pos' => '12190',
                'sektor' => 'Perbankan & Pasar Modal',
                'skala' => 'Nasional',
                'jenis_perusahaan' => 'BUMN/BUMD',
                'jenis_perusahaan_lainnya' => null,
                'jenis_lokasi' => 'Dalam Negeri',
                'kode_negara' => 'ID',
                'status_verifikasi' => 'Terverifikasi',
            ],
            [
                'nama_perusahaan' => 'Kementerian Komunikasi dan Digital RI',
                'kode_provinsi' => $jakarta->kode_provinsi,
                'kode_kabupaten' => $jakpus->kode_kabupaten,
                'alamat' => 'Jl. Medan Merdeka Barat No. 9, Gambir, Kota Jakarta Pusat',
                'kode_pos' => '10110',
                'sektor' => 'Pemerintahan & Regulasi Digital',
                'skala' => 'Nasional',
                'jenis_perusahaan' => 'Instansi pemerintah',
                'jenis_perusahaan_lainnya' => null,
                'jenis_lokasi' => 'Dalam Negeri',
                'kode_negara' => 'ID',
                'status_verifikasi' => 'Terverifikasi',
            ],
            [
                'nama_perusahaan' => 'UNICEF Representative Office Indonesia',
                'kode_provinsi' => $jakarta->kode_provinsi,
                'kode_kabupaten' => $jakpus->kode_kabupaten,
                'alamat' => 'World Trade Centre 2 Lt. 22, Jl. Jend. Sudirman Kav. 29-31, Kota Jakarta Pusat',
                'kode_pos' => '12920',
                'sektor' => 'Organisasi Multilateral Kemanusiaan & Perlindungan Anak',
                'skala' => 'Internasional',
                'jenis_perusahaan' => 'Institusi/Organisasi Multilateral',
                'jenis_perusahaan_lainnya' => null,
                'jenis_lokasi' => 'Dalam Negeri',
                'kode_negara' => 'ID',
                'status_verifikasi' => 'Terverifikasi',
            ],
            [
                'nama_perusahaan' => 'Yayasan WWF Indonesia',
                'kode_provinsi' => $jakarta->kode_provinsi,
                'kode_kabupaten' => $jaksel->kode_kabupaten,
                'alamat' => 'Graha Simatupang Tower 2 Unit C, Jl. TB Simatupang Kav. 38, Kota Jakarta Selatan',
                'kode_pos' => '12540',
                'sektor' => 'Konservasi Alam, Lingkungan & Keanekaragaman Hayati',
                'skala' => 'Nasional',
                'jenis_perusahaan' => 'Organisasi non-profit/Lembaga Swadaya Masyarakat',
                'jenis_perusahaan_lainnya' => null,
                'jenis_lokasi' => 'Dalam Negeri',
                'kode_negara' => 'ID',
                'status_verifikasi' => 'Terverifikasi',
            ],

            // ==========================================
            // 3. Perusahaan di Jawa Barat & Jawa Timur
            // ==========================================
            [
                'nama_perusahaan' => 'PT Dirgantara Indonesia (Persero)',
                'kode_provinsi' => $jabar->kode_provinsi,
                'kode_kabupaten' => $bandung->kode_kabupaten,
                'alamat' => 'Jl. Pajajaran No. 154, Cicendo, Kota Bandung',
                'kode_pos' => '40174',
                'sektor' => 'Dirgantara, Manufaktur Pesawat & Teknologi Pertahanan',
                'skala' => 'Internasional',
                'jenis_perusahaan' => 'BUMN/BUMD',
                'jenis_perusahaan_lainnya' => null,
                'jenis_lokasi' => 'Dalam Negeri',
                'kode_negara' => 'ID',
                'status_verifikasi' => 'Terverifikasi',
            ],
            [
                'nama_perusahaan' => 'PT Shopee International Indonesia (Surabaya Office)',
                'kode_provinsi' => $jatim->kode_provinsi,
                'kode_kabupaten' => $surabaya->kode_kabupaten,
                'alamat' => 'Pakuwon Tower Lt. 15, Jl. Embong Malang No. 21-31, Kota Surabaya',
                'kode_pos' => '60261',
                'sektor' => 'E-Commerce, Logistik & Pembayaran Digital',
                'skala' => 'Internasional',
                'jenis_perusahaan' => 'Perusahaan swasta',
                'jenis_perusahaan_lainnya' => null,
                'jenis_lokasi' => 'Dalam Negeri',
                'kode_negara' => 'ID',
                'status_verifikasi' => 'Terverifikasi',
            ],

            // ==========================================
            // 4. Perusahaan di Luar Pulau Jawa
            // ==========================================
            [
                'nama_perusahaan' => 'PT Perkebunan Nusantara I (PTPN Unit Aceh Selatan)',
                'kode_provinsi' => $aceh->kode_provinsi,
                'kode_kabupaten' => $acehSelatan->kode_kabupaten,
                'alamat' => 'Jl. Merdeka No. 45, Tapaktuan, Aceh Selatan',
                'kode_pos' => '23715',
                'sektor' => 'Perkebunan, Kelapa Sawit & Agribisnis',
                'skala' => 'Nasional',
                'jenis_perusahaan' => 'BUMN/BUMD',
                'jenis_perusahaan_lainnya' => null,
                'jenis_lokasi' => 'Dalam Negeri',
                'kode_negara' => 'ID',
                'status_verifikasi' => 'Terverifikasi',
            ],
            [
                'nama_perusahaan' => 'PT Bank Aceh Syariah Cabang Tapaktuan',
                'kode_provinsi' => $aceh->kode_provinsi,
                'kode_kabupaten' => $acehSelatan->kode_kabupaten,
                'alamat' => 'Jl. Jenderal Sudirman No. 18, Tapaktuan, Aceh Selatan',
                'kode_pos' => '23711',
                'sektor' => 'Perbankan Syariah & Finansial',
                'skala' => 'Regional/Lokal',
                'jenis_perusahaan' => 'BUMN/BUMD',
                'jenis_perusahaan_lainnya' => null,
                'jenis_lokasi' => 'Dalam Negeri',
                'kode_negara' => 'ID',
                'status_verifikasi' => 'Terverifikasi',
            ],
            [
                'nama_perusahaan' => 'PT Bank Pembangunan Daerah Bali (Bank BPD Bali)',
                'kode_provinsi' => $bali->kode_provinsi,
                'kode_kabupaten' => $badung->kode_kabupaten,
                'alamat' => 'Jl. Raya Kuta No. 88, Kuta, Badung',
                'kode_pos' => '80361',
                'sektor' => 'Perbankan Daerah & Pariwisata',
                'skala' => 'Regional/Lokal',
                'jenis_perusahaan' => 'BUMN/BUMD',
                'jenis_perusahaan_lainnya' => null,
                'jenis_lokasi' => 'Dalam Negeri',
                'kode_negara' => 'ID',
                'status_verifikasi' => 'Terverifikasi',
            ],
            [
                'nama_perusahaan' => 'PT Freeport Indonesia',
                'kode_provinsi' => $papua->kode_provinsi,
                'kode_kabupaten' => $mimika->kode_kabupaten,
                'alamat' => 'Kuala Kencana, Tembagapura, Mimika',
                'kode_pos' => '99920',
                'sektor' => 'Pertambangan Mineral, Tembaga & Emas',
                'skala' => 'Internasional',
                'jenis_perusahaan' => 'Perusahaan swasta',
                'jenis_perusahaan_lainnya' => null,
                'jenis_lokasi' => 'Dalam Negeri',
                'kode_negara' => 'ID',
                'status_verifikasi' => 'Terverifikasi',
            ],

            // ==========================================
            // 5. Perusahaan di Luar Negeri (International)
            // ==========================================
            [
                'nama_perusahaan' => 'Google Asia Pacific Pte. Ltd.',
                'kode_provinsi' => null,
                'kode_kabupaten' => null,
                'alamat' => '70 Pasir Panjang Rd, #03-71 Mapletree Business City II',
                'kode_pos' => '117371',
                'sektor' => 'Teknologi Informasi, Mesin Pencari & Kecerdasan Buatan (AI)',
                'skala' => 'Internasional',
                'jenis_perusahaan' => 'Perusahaan swasta',
                'jenis_perusahaan_lainnya' => null,
                'jenis_lokasi' => 'Luar Negeri',
                'kode_negara' => 'SG',
                'status_verifikasi' => 'Terverifikasi',
            ],
            [
                'nama_perusahaan' => 'Grab Holdings Inc. (Singapore HQ)',
                'kode_provinsi' => null,
                'kode_kabupaten' => null,
                'alamat' => '3 Media Close, Grab@Singapore HQ',
                'kode_pos' => '138498',
                'sektor' => 'Transportasi Online, Superapp & FinTech Global',
                'skala' => 'Internasional',
                'jenis_perusahaan' => 'Perusahaan swasta',
                'jenis_perusahaan_lainnya' => null,
                'jenis_lokasi' => 'Luar Negeri',
                'kode_negara' => 'SG',
                'status_verifikasi' => 'Terverifikasi',
            ],
            [
                'nama_perusahaan' => 'Rakuten Group, Inc. (Tokyo HQ)',
                'kode_provinsi' => null,
                'kode_kabupaten' => null,
                'alamat' => '1-14-1 Tamagawa, Setagaya-ku, Tokyo',
                'kode_pos' => '158-0094',
                'sektor' => 'E-Commerce Global, FinTech & Telekomunikasi',
                'skala' => 'Internasional',
                'jenis_perusahaan' => 'Perusahaan swasta',
                'jenis_perusahaan_lainnya' => null,
                'jenis_lokasi' => 'Luar Negeri',
                'kode_negara' => 'JP',
                'status_verifikasi' => 'Terverifikasi',
            ],
            [
                'nama_perusahaan' => 'Microsoft Corporation (APAC Regional HQ)',
                'kode_provinsi' => null,
                'kode_kabupaten' => null,
                'alamat' => '1 Marina Boulevard, #22-01 One Marina Boulevard',
                'kode_pos' => '018989',
                'sektor' => 'Perangkat Lunak, Cloud Computing & Solusi Enterprise',
                'skala' => 'Internasional',
                'jenis_perusahaan' => 'Perusahaan swasta',
                'jenis_perusahaan_lainnya' => null,
                'jenis_lokasi' => 'Luar Negeri',
                'kode_negara' => 'SG',
                'status_verifikasi' => 'Terverifikasi',
            ],
            [
                'nama_perusahaan' => 'CV Theresia Inovasi Mandiri',
                'kode_provinsi' => $diy->kode_provinsi,
                'kode_kabupaten' => $sleman->kode_kabupaten,
                'alamat' => 'Jl. Kaliurang Km 5 No. 12, Caturtunggal',
                'kode_pos' => '55281',
                'sektor' => 'Teknologi Informasi & Konsultan Software',
                'skala' => 'Regional/Lokal',
                'jenis_perusahaan' => 'Wiraswasta',
                'jenis_perusahaan_lainnya' => null,
                'jenis_lokasi' => 'Dalam Negeri',
                'kode_negara' => 'ID',
                'status_verifikasi' => 'Menunggu Verifikasi',
            ],
            [
                'nama_perusahaan' => 'PT Gojek Indonesia Cabang DIY',
                'kode_provinsi' => $diy->kode_provinsi,
                'kode_kabupaten' => $kotaYogya->kode_kabupaten,
                'alamat' => 'Jl. AM Sangaji No. 42, Jetis',
                'kode_pos' => '55233',
                'sektor' => 'Transportasi & Ekosistem Digital',
                'skala' => 'Nasional',
                'jenis_perusahaan' => 'Perusahaan swasta',
                'jenis_perusahaan_lainnya' => null,
                'jenis_lokasi' => 'Dalam Negeri',
                'kode_negara' => 'ID',
                'status_verifikasi' => 'Menunggu Verifikasi',
            ],
        ];

        foreach ($perusahaans as $perusahaan) {
            Perusahaan::updateOrCreate(
                ['nama_perusahaan' => $perusahaan['nama_perusahaan']],
                $perusahaan
            );
        }
    }
}
