<?php

namespace Database\Seeders;

use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\Kabupaten;
use App\Models\KuesionerJawaban;
use App\Models\KuesionerPertanyaan;
use App\Models\KuesionerSection;
use App\Models\Perusahaan;
use App\Models\Prodi;
use App\Models\Propinsi;
use App\Models\User;
use App\Models\Yudisium;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Menambahkan 50 alumni baru nyata kohort 2026 dengan variasi karakteristik
 * metode pembelajaran dan indikator tracer study yang kaya antar program studi.
 *
 * Seeder ini TIDAK menghapus data lama (non-destructive).
 */
class AlumniVariasiAnalitikSeeder extends Seeder
{
    public function run(): void
    {
        $prodis = Prodi::where('jenjang', 'S1')->get()->keyBy('kode_prodi');
        if ($prodis->isEmpty()) {
            return;
        }

        $univSection = KuesionerSection::where('target', 'univ')->first();
        $univSectionId = $univSection?->id ?: 1;

        $univQuestions = KuesionerPertanyaan::query()
            ->whereHas('sections', fn ($q) => $q->where('target', 'univ'))
            ->with(['opsi' => fn ($q) => $q->orderBy('order')])
            ->get()
            ->keyBy('kode_pertanyaan');

        $propinsiDiy = Propinsi::where('kode_provinsi', '34')->first();
        $kabSleman = Kabupaten::where('kode_kabupaten', '34.04')->first();
        $kabKotaYogya = Kabupaten::where('kode_kabupaten', '34.71')->first();
        $propinsiJkt = Propinsi::where('kode_provinsi', '31')->first();
        $kabJaksel = Kabupaten::where('kode_kabupaten', '31.74')->first();

        // Referensi perusahaan riil untuk variasi pekerjaan
        $companies = [
            'tech' => Perusahaan::firstOrCreate(
                ['nama_perusahaan' => 'PT Tokopedia'],
                [
                    'kode_provinsi' => $propinsiJkt?->kode_provinsi ?: '31',
                    'kode_kabupaten' => $kabJaksel?->kode_kabupaten ?: '31.74',
                    'alamat' => 'Tokopedia Tower, Jl. Prof. Dr. Satrio Kav. 11, Setiabudi, Jakarta Selatan',
                    'sektor' => 'Teknologi Informasi & E-Commerce',
                    'skala' => 'Nasional',
                    'jenis_perusahaan' => 'Perusahaan swasta',
                    'jenis_lokasi' => 'Dalam Negeri',
                ]
            ),
            'bumn' => Perusahaan::firstOrCreate(
                ['nama_perusahaan' => 'PT Telkom Indonesia (Persero) Tbk'],
                [
                    'kode_provinsi' => $propinsiJkt?->kode_provinsi ?: '31',
                    'kode_kabupaten' => $kabJaksel?->kode_kabupaten ?: '31.74',
                    'alamat' => 'Telkom Landmark Tower, Jl. Jend. Gatot Subroto Kav. 52, Jakarta Selatan',
                    'sektor' => 'Telekomunikasi & Digital',
                    'skala' => 'Nasional',
                    'jenis_perusahaan' => 'BUMN/BUMD',
                    'jenis_lokasi' => 'Dalam Negeri',
                ]
            ),
            'hospital' => Perusahaan::firstOrCreate(
                ['nama_perusahaan' => 'RS Panti Rapih Yogyakarta'],
                [
                    'kode_provinsi' => $propinsiDiy?->kode_provinsi ?: '34',
                    'kode_kabupaten' => $kabKotaYogya?->kode_kabupaten ?: '34.71',
                    'alamat' => 'Jl. Cik Di Tiro No. 30, Terban, Gondokusuman, Kota Yogyakarta',
                    'sektor' => 'Kesehatan & Rumah Sakit',
                    'skala' => 'Regional/Lokal',
                    'jenis_perusahaan' => 'Perusahaan swasta',
                    'jenis_lokasi' => 'Dalam Negeri',
                ]
            ),
            'bank' => Perusahaan::firstOrCreate(
                ['nama_perusahaan' => 'PT Bank Central Asia Tbk'],
                [
                    'kode_provinsi' => $propinsiDiy?->kode_provinsi ?: '34',
                    'kode_kabupaten' => $kabKotaYogya?->kode_kabupaten ?: '34.71',
                    'alamat' => 'Jl. Jend. Sudirman No. 49-51, Terban, Gondokusuman, Kota Yogyakarta',
                    'sektor' => 'Perbankan & Keuangan',
                    'skala' => 'Nasional',
                    'jenis_perusahaan' => 'Perusahaan swasta',
                    'jenis_lokasi' => 'Dalam Negeri',
                ]
            ),
            'school' => Perusahaan::firstOrCreate(
                ['nama_perusahaan' => 'SMA Bopkri 1 Yogyakarta'],
                [
                    'kode_provinsi' => $propinsiDiy?->kode_provinsi ?: '34',
                    'kode_kabupaten' => $kabKotaYogya?->kode_kabupaten ?: '34.71',
                    'alamat' => 'Jl. Wardhani No. 2, Kotabaru, Gondokusuman, Kota Yogyakarta',
                    'sektor' => 'Pendidikan',
                    'skala' => 'Regional/Lokal',
                    'jenis_perusahaan' => 'Organisasi non-profit/LSM',
                    'jenis_lokasi' => 'Dalam Negeri',
                ]
            ),
            'arch' => Perusahaan::firstOrCreate(
                ['nama_perusahaan' => 'PT Urbane Indonesia'],
                [
                    'kode_provinsi' => $propinsiJkt?->kode_provinsi ?: '31',
                    'kode_kabupaten' => $kabJaksel?->kode_kabupaten ?: '31.74',
                    'alamat' => 'Jl. Senopati No. 45, Kebayoran Baru, Jakarta Selatan',
                    'sektor' => 'Arsitektur & Desain Perancangan',
                    'skala' => 'Nasional',
                    'jenis_perusahaan' => 'Perusahaan swasta',
                    'jenis_lokasi' => 'Dalam Negeri',
                ]
            ),
        ];

        // Daftar 50 Alumni Baru Nyata Lintas 12 Program Studi
        $alumniList = [
            // FTI - Informatika (71) - 4 orang
            ['prodi' => '71', 'nim' => '71220010', 'nama' => 'Daniel Ardiansyah Pratama', 'jk' => 'Laki-laki', 'agama' => 'Kristen Protestan', 'status' => 'Karyawan', 'company' => 'tech', 'posisi' => 'Software Engineer Backend', 'gaji' => 9500000, 'wait_type' => 'before', 'wait_months' => 2],
            ['prodi' => '71', 'nim' => '71220011', 'nama' => 'Stephanie Clarissa Kusuma', 'jk' => 'Perempuan', 'agama' => 'Katolik', 'status' => 'Karyawan', 'company' => 'bumn', 'posisi' => 'Data Analyst', 'gaji' => 8800000, 'wait_type' => 'after', 'wait_months' => 3],
            ['prodi' => '71', 'nim' => '71220012', 'nama' => 'Ricky Handoko Setiawan', 'jk' => 'Laki-laki', 'agama' => 'Buddha', 'status' => 'Founder', 'company' => null, 'posisi' => 'Founder / Lead Developer', 'gaji' => 12000000, 'wait_type' => 'before', 'wait_months' => 4],
            ['prodi' => '71', 'nim' => '71220013', 'nama' => 'Maria Natalia Nugroho', 'jk' => 'Perempuan', 'agama' => 'Katolik', 'status' => 'Studi Lanjut', 'company' => null, 'posisi' => 'Mahasiswa Pascasarjana (Awardee Beasiswa LPDP)', 'gaji' => 5000000, 'wait_type' => 'study', 'wait_months' => 5],

            // FTI - Sistem Informasi (72) - 4 orang
            ['prodi' => '72', 'nim' => '72220010', 'nama' => 'Jonathan Pratama Wijaya', 'jk' => 'Laki-laki', 'agama' => 'Kristen Protestan', 'status' => 'Karyawan', 'company' => 'tech', 'posisi' => 'ERP Consultant', 'gaji' => 9200000, 'wait_type' => 'before', 'wait_months' => 1],
            ['prodi' => '72', 'nim' => '72220011', 'nama' => 'Aurelia Vania Santoso', 'jk' => 'Perempuan', 'agama' => 'Katolik', 'status' => 'Karyawan', 'company' => 'bank', 'posisi' => 'Business Analyst', 'gaji' => 8500000, 'wait_type' => 'after', 'wait_months' => 2],
            ['prodi' => '72', 'nim' => '72220012', 'nama' => 'Jason Gunawan Sutanto', 'jk' => 'Laki-laki', 'agama' => 'Kristen Protestan', 'status' => 'Freelancer', 'company' => null, 'posisi' => 'Freelance UI/UX Designer', 'gaji' => 6000000, 'wait_type' => 'after', 'wait_months' => 4],
            ['prodi' => '72', 'nim' => '72220013', 'nama' => 'Felicia Santoso Halim', 'jk' => 'Perempuan', 'agama' => 'Kristen Protestan', 'status' => 'Studi Lanjut', 'company' => null, 'posisi' => 'Mahasiswa S2 Magister Sistem Informasi', 'gaji' => 4500000, 'wait_type' => 'study', 'wait_months' => 6],

            // FB - Manajemen (11) - 4 orang
            ['prodi' => '11', 'nim' => '11220010', 'nama' => 'Brandon Aditya Hartono', 'jk' => 'Laki-laki', 'agama' => 'Katolik', 'status' => 'Karyawan', 'company' => 'bank', 'posisi' => 'Management Trainee', 'gaji' => 8500000, 'wait_type' => 'before', 'wait_months' => 2],
            ['prodi' => '11', 'nim' => '11220011', 'nama' => 'Nadya Putri Maharani', 'jk' => 'Perempuan', 'agama' => 'Islam', 'status' => 'Karyawan', 'company' => 'tech', 'posisi' => 'Digital Marketing Specialist', 'gaji' => 7800000, 'wait_type' => 'after', 'wait_months' => 3],
            ['prodi' => '11', 'nim' => '11220012', 'nama' => 'William Tanujaya Wibowo', 'jk' => 'Laki-laki', 'agama' => 'Kristen Protestan', 'status' => 'Founder', 'company' => null, 'posisi' => 'Co-Founder & COO', 'gaji' => 11000000, 'wait_type' => 'before', 'wait_months' => 3],
            ['prodi' => '11', 'nim' => '11220013', 'nama' => 'Cindy Novita Gunardi', 'jk' => 'Perempuan', 'agama' => 'Katolik', 'status' => 'Karyawan', 'company' => 'bumn', 'posisi' => 'Human Capital Officer', 'gaji' => 8000000, 'wait_type' => 'after', 'wait_months' => 4],

            // FB - Akuntansi (12) - 4 orang
            ['prodi' => '12', 'nim' => '12220010', 'nama' => 'Kevin Hartono Salim', 'jk' => 'Laki-laki', 'agama' => 'Kristen Protestan', 'status' => 'Karyawan', 'company' => 'bank', 'posisi' => 'Junior Auditor', 'gaji' => 8200000, 'wait_type' => 'before', 'wait_months' => 1],
            ['prodi' => '12', 'nim' => '12220011', 'nama' => 'Jessica Amanda Chandra', 'jk' => 'Perempuan', 'agama' => 'Katolik', 'status' => 'Karyawan', 'company' => 'bumn', 'posisi' => 'Tax Consultant', 'gaji' => 7900000, 'wait_type' => 'after', 'wait_months' => 2],
            ['prodi' => '12', 'nim' => '12220012', 'nama' => 'Samuel Kristianto Nugraha', 'jk' => 'Laki-laki', 'agama' => 'Kristen Protestan', 'status' => 'Karyawan', 'company' => 'tech', 'posisi' => 'Financial Planning Analyst', 'gaji' => 8600000, 'wait_type' => 'after', 'wait_months' => 5],
            ['prodi' => '12', 'nim' => '12220013', 'nama' => 'Vanessa Angeline Soegiharto', 'jk' => 'Perempuan', 'agama' => 'Buddha', 'status' => 'Studi Lanjut', 'company' => null, 'posisi' => 'Mahasiswa Magister Akuntansi (PPAk)', 'gaji' => 4800000, 'wait_type' => 'study', 'wait_months' => 4],

            // FAD - Arsitektur (21) - 4 orang
            ['prodi' => '21', 'nim' => '21220010', 'nama' => 'Christopher Daniel Soetanto', 'jk' => 'Laki-laki', 'agama' => 'Katolik', 'status' => 'Karyawan', 'company' => 'arch', 'posisi' => 'Junior Architect & 3D Visualizer', 'gaji' => 7800000, 'wait_type' => 'before', 'wait_months' => 2],
            ['prodi' => '21', 'nim' => '21220011', 'nama' => 'Michelle Anastasia Wibowo', 'jk' => 'Perempuan', 'agama' => 'Kristen Protestan', 'status' => 'Karyawan', 'company' => 'arch', 'posisi' => 'Landscape Architectural Drafter', 'gaji' => 7500000, 'wait_type' => 'after', 'wait_months' => 3],
            ['prodi' => '21', 'nim' => '21220012', 'nama' => 'Nathan Wicaksono Pamungkas', 'jk' => 'Laki-laki', 'agama' => 'Islam', 'status' => 'Freelancer', 'company' => null, 'posisi' => 'Freelance Architectural Designer', 'gaji' => 6500000, 'wait_type' => 'after', 'wait_months' => 5],
            ['prodi' => '21', 'nim' => '21220013', 'nama' => 'Clara Beatrice Rahardjo', 'jk' => 'Perempuan', 'agama' => 'Katolik', 'status' => 'Studi Lanjut', 'company' => null, 'posisi' => 'Mahasiswa Program Profesi Arsitek (PPAr)', 'gaji' => 4500000, 'wait_type' => 'study', 'wait_months' => 3],

            // FAD - Desain Produk (22) - 4 orang
            ['prodi' => '22', 'nim' => '22220010', 'nama' => 'Darren Pratama Adikusumo', 'jk' => 'Laki-laki', 'agama' => 'Islam', 'status' => 'Karyawan', 'company' => 'tech', 'posisi' => 'Product Designer & Ergonomist', 'gaji' => 8400000, 'wait_type' => 'before', 'wait_months' => 1],
            ['prodi' => '22', 'nim' => '22220011', 'nama' => 'Fiona Nathania Sasongko', 'jk' => 'Perempuan', 'agama' => 'Kristen Protestan', 'status' => 'Founder', 'company' => null, 'posisi' => 'Founder Studio Desain Kriya', 'gaji' => 9800000, 'wait_type' => 'before', 'wait_months' => 3],
            ['prodi' => '22', 'nim' => '22220012', 'nama' => 'Andrew Setiawan Tandiono', 'jk' => 'Laki-laki', 'agama' => 'Katolik', 'status' => 'Freelancer', 'company' => null, 'posisi' => 'Freelance Industrial CAD Designer', 'gaji' => 5800000, 'wait_type' => 'after', 'wait_months' => 4],
            ['prodi' => '22', 'nim' => '22220013', 'nama' => 'Gabriella Audrey Kusuma', 'jk' => 'Perempuan', 'agama' => 'Kristen Protestan', 'status' => 'Karyawan', 'company' => 'bumn', 'posisi' => 'Packaging & Brand Specialist', 'gaji' => 7600000, 'wait_type' => 'after', 'wait_months' => 6],

            // Biotek - Biologi (41) - 4 orang
            ['prodi' => '41', 'nim' => '41220010', 'nama' => 'Andreas Yoga Danendra', 'jk' => 'Laki-laki', 'agama' => 'Katolik', 'status' => 'Karyawan', 'company' => 'bumn', 'posisi' => 'Environmental Analyst & QC', 'gaji' => 7500000, 'wait_type' => 'before', 'wait_months' => 2],
            ['prodi' => '41', 'nim' => '41220011', 'nama' => 'Patricia Dewi Wulandari', 'jk' => 'Perempuan', 'agama' => 'Islam', 'status' => 'Karyawan', 'company' => 'hospital', 'posisi' => 'Laboratorium Mikrobiologi Staff', 'gaji' => 7000000, 'wait_type' => 'after', 'wait_months' => 3],
            ['prodi' => '41', 'nim' => '41220012', 'nama' => 'Hendra Kurniawan Hendrawan', 'jk' => 'Laki-laki', 'agama' => 'Katolik', 'status' => 'Studi Lanjut', 'company' => null, 'posisi' => 'Mahasiswa Magister Bioteknologi', 'gaji' => 4500000, 'wait_type' => 'study', 'wait_months' => 4],
            ['prodi' => '41', 'nim' => '41220013', 'nama' => 'Olivia Prameswari Ayu', 'jk' => 'Perempuan', 'agama' => 'Kristen Protestan', 'status' => 'Karyawan', 'company' => 'bumn', 'posisi' => 'Bio-Research Assistant', 'gaji' => 7200000, 'wait_type' => 'after', 'wait_months' => 5],

            // Biotek - Teknologi Pangan (42) - 4 orang
            ['prodi' => '42', 'nim' => '42220010', 'nama' => 'Raymond Kusuma Wicaksono', 'jk' => 'Laki-laki', 'agama' => 'Katolik', 'status' => 'Karyawan', 'company' => 'bumn', 'posisi' => 'Food Quality Assurance Officer', 'gaji' => 8100000, 'wait_type' => 'before', 'wait_months' => 1],
            ['prodi' => '42', 'nim' => '42220011', 'nama' => 'Valerie Christina Dewi', 'jk' => 'Perempuan', 'agama' => 'Kristen Protestan', 'status' => 'Karyawan', 'company' => 'bumn', 'posisi' => 'R&D Product Formulator', 'gaji' => 8300000, 'wait_type' => 'after', 'wait_months' => 2],
            ['prodi' => '42', 'nim' => '42220012', 'nama' => 'Nicholas Surya Pambudi', 'jk' => 'Laki-laki', 'agama' => 'Katolik', 'status' => 'Founder', 'company' => null, 'posisi' => 'Owner Usaha Pangan Fermentasi', 'gaji' => 9500000, 'wait_type' => 'before', 'wait_months' => 4],
            ['prodi' => '42', 'nim' => '42220013', 'nama' => 'Natasha Kirana Azzahra', 'jk' => 'Perempuan', 'agama' => 'Islam', 'status' => 'Karyawan', 'company' => 'bumn', 'posisi' => 'Nutritionist & Quality Inspector', 'gaji' => 7400000, 'wait_type' => 'after', 'wait_months' => 4],

            // Kedokteran (61) - 5 orang
            ['prodi' => '61', 'nim' => '61220010', 'nama' => 'Edward Alexander Tedjokusumo', 'jk' => 'Laki-laki', 'agama' => 'Katolik', 'status' => 'Studi Lanjut', 'company' => 'hospital', 'posisi' => 'Dokter Muda (Co-Assisten Pendidikan Profesi Dokter)', 'gaji' => 4000000, 'wait_type' => 'study', 'wait_months' => 1],
            ['prodi' => '61', 'nim' => '61220011', 'nama' => 'Catherine Michelle Wijaya', 'jk' => 'Perempuan', 'agama' => 'Kristen Protestan', 'status' => 'Studi Lanjut', 'company' => 'hospital', 'posisi' => 'Dokter Muda (Pendidikan Profesi Dokter)', 'gaji' => 4000000, 'wait_type' => 'study', 'wait_months' => 1],
            ['prodi' => '61', 'nim' => '61220012', 'nama' => 'Vincent Wijaya Santoso', 'jk' => 'Laki-laki', 'agama' => 'Kristen Protestan', 'status' => 'Studi Lanjut', 'company' => 'hospital', 'posisi' => 'Dokter Muda (Rotasi Klinik RS Panti Rapih)', 'gaji' => 4000000, 'wait_type' => 'study', 'wait_months' => 2],
            ['prodi' => '61', 'nim' => '61220013', 'nama' => 'Felisitas Amanda Susilo', 'jk' => 'Perempuan', 'agama' => 'Katolik', 'status' => 'Karyawan', 'company' => 'hospital', 'posisi' => 'Medical Research Assistant & Asisten Lab', 'gaji' => 8500000, 'wait_type' => 'before', 'wait_months' => 2],
            ['prodi' => '61', 'nim' => '61220014', 'nama' => 'Bryan Christian Kusumah', 'jk' => 'Laki-laki', 'agama' => 'Buddha', 'status' => 'Studi Lanjut', 'company' => 'hospital', 'posisi' => 'Dokter Muda (Co-Assisten Rumah Sakit)', 'gaji' => 4000000, 'wait_type' => 'study', 'wait_months' => 2],

            // Theologi - Filsafat Keilahian (31) - 4 orang
            ['prodi' => '31', 'nim' => '31220010', 'nama' => 'Samuel Sitompul Nababan', 'jk' => 'Laki-laki', 'agama' => 'Kristen Protestan', 'status' => 'Karyawan', 'company' => 'school', 'posisi' => 'Guru Agama Kristen & Pembimbing Rohani', 'gaji' => 6500000, 'wait_type' => 'before', 'wait_months' => 2],
            ['prodi' => '31', 'nim' => '31220011', 'nama' => 'Ruth Anggraeni Hutabarat', 'jk' => 'Perempuan', 'agama' => 'Kristen Protestan', 'status' => 'Karyawan', 'company' => 'school', 'posisi' => 'Staf Pelayanan Konseling & Pastoral', 'gaji' => 6200000, 'wait_type' => 'after', 'wait_months' => 3],
            ['prodi' => '31', 'nim' => '31220012', 'nama' => 'David Simatupang Marpaung', 'jk' => 'Laki-laki', 'agama' => 'Kristen Protestan', 'status' => 'Studi Lanjut', 'company' => null, 'posisi' => 'Mahasiswa Magister Teologi (M.Th)', 'gaji' => 4500000, 'wait_type' => 'study', 'wait_months' => 4],
            ['prodi' => '31', 'nim' => '31220013', 'nama' => 'Maria Hutapea Silalahi', 'jk' => 'Perempuan', 'agama' => 'Kristen Protestan', 'status' => 'Karyawan', 'company' => 'school', 'posisi' => 'Pengembang Kurikulum Etika & Karakter', 'gaji' => 6000000, 'wait_type' => 'after', 'wait_months' => 5],

            // FKH - Pendidikan Bahasa Inggris (81) - 5 orang
            ['prodi' => '81', 'nim' => '81220010', 'nama' => 'Anthony Wijaya Widiantoro', 'jk' => 'Laki-laki', 'agama' => 'Kristen Protestan', 'status' => 'Karyawan', 'company' => 'school', 'posisi' => 'English Teacher & Curriculum Specialist', 'gaji' => 6800000, 'wait_type' => 'before', 'wait_months' => 1],
            ['prodi' => '81', 'nim' => '81220011', 'nama' => 'Brenda Clarissa Wardhani', 'jk' => 'Perempuan', 'agama' => 'Islam', 'status' => 'Karyawan', 'company' => 'tech', 'posisi' => 'Content Creator & Bilingual Copywriter', 'gaji' => 7400000, 'wait_type' => 'after', 'wait_months' => 2],
            ['prodi' => '81', 'nim' => '81220012', 'nama' => 'Jeremy Tan Soeharto', 'jk' => 'Laki-laki', 'agama' => 'Katolik', 'status' => 'Freelancer', 'company' => null, 'posisi' => 'Freelance Sworn Translator & Interpreter', 'gaji' => 6200000, 'wait_type' => 'after', 'wait_months' => 3],
            ['prodi' => '81', 'nim' => '81220013', 'nama' => 'Diana Kurnia Susanto', 'jk' => 'Perempuan', 'agama' => 'Kristen Protestan', 'status' => 'Studi Lanjut', 'company' => null, 'posisi' => 'Mahasiswa Magister Linguistik Terapan', 'gaji' => 4600000, 'wait_type' => 'study', 'wait_months' => 5],
            ['prodi' => '81', 'nim' => '81220014', 'nama' => 'Michael Setiadi Wiryawan', 'jk' => 'Laki-laki', 'agama' => 'Kristen Protestan', 'status' => 'Karyawan', 'company' => 'school', 'posisi' => 'IELTS/TOEFL Instructor', 'gaji' => 7100000, 'wait_type' => 'after', 'wait_months' => 4],

            // FKH - Studi Humanitas (82) - 4 orang
            ['prodi' => '82', 'nim' => '82220010', 'nama' => 'Gregorius Raditya Dananjaya', 'jk' => 'Laki-laki', 'agama' => 'Katolik', 'status' => 'Karyawan', 'company' => 'tech', 'posisi' => 'Community Manager & Social Researcher', 'gaji' => 7200000, 'wait_type' => 'before', 'wait_months' => 2],
            ['prodi' => '82', 'nim' => '82220011', 'nama' => 'Bernadette Sekar Langit', 'jk' => 'Perempuan', 'agama' => 'Katolik', 'status' => 'Karyawan', 'company' => 'school', 'posisi' => 'Cultural Project Officer & Peneliti Sosial', 'gaji' => 6500000, 'wait_type' => 'after', 'wait_months' => 3],
            ['prodi' => '82', 'nim' => '82220012', 'nama' => 'Ignatius Bima Senopati', 'jk' => 'Laki-laki', 'agama' => 'Katolik', 'status' => 'Freelancer', 'company' => null, 'posisi' => 'Independent Researcher & Penulis', 'gaji' => 5500000, 'wait_type' => 'after', 'wait_months' => 6],
            ['prodi' => '82', 'nim' => '82220013', 'nama' => 'Theresia Ayu Kinasih', 'jk' => 'Perempuan', 'agama' => 'Katolik', 'status' => 'Studi Lanjut', 'company' => null, 'posisi' => 'Mahasiswa Magister Ilmu Budaya & Humaniora', 'gaji' => 4500000, 'wait_type' => 'study', 'wait_months' => 4],
        ];

        // Profil Skor Karakteristik Metode Pembelajaran per Program Studi (F21 s.d. F27)
        // Format: [F21 (Kuliah), F22 (Demo), F23 (Riset), F24 (Magang), F25 (Praktikum), F26 (Lapangan), F27 (Diskusi)]
        $methodProfilesByProdi = [
            '71' => [5, 4, 4, 5, 5, 2, 5], // Informatika: Sangat kuat di Magang, Praktikum, Kuliah, Diskusi
            '72' => [5, 4, 4, 5, 4, 3, 5], // Sistem Informasi: Kuat di Magang, Kuliah, Diskusi
            '11' => [5, 3, 3, 5, 2, 3, 5], // Manajemen: Sangat kuat di Diskusi & Magang & Kuliah
            '12' => [5, 3, 3, 5, 3, 2, 5], // Akuntansi: Kuat di Magang, Kuliah, Diskusi
            '21' => [5, 5, 4, 5, 5, 4, 4], // Arsitektur: Sangat kuat di Studio/Praktikum, Demo, Magang
            '22' => [5, 5, 4, 5, 5, 3, 4], // Desain Produk: Sangat kuat di Studio, Demo, Magang
            '41' => [4, 4, 5, 5, 5, 3, 4], // Biologi: Sangat kuat di Lab Praktikum, Riset, Magang
            '42' => [4, 4, 5, 5, 5, 3, 4], // Teknologi Pangan: Sangat kuat di Lab Praktikum, Riset, Magang
            '61' => [5, 5, 4, 5, 5, 4, 5], // Kedokteran: Sangat kuat di Praktikum, Demo, Magang Klinik, Kuliah, Diskusi
            '31' => [5, 2, 5, 4, 1, 3, 5], // Filsafat Keilahian: Sangat kuat di Diskusi, Kuliah, Riset
            '81' => [5, 4, 3, 5, 4, 3, 5], // PBI: Sangat kuat di Diskusi, Magang PPL, Kuliah
            '82' => [5, 2, 5, 4, 1, 3, 5], // Studi Humanitas: Sangat kuat di Diskusi, Kuliah, Riset
        ];

        $scaleDescriptions = [
            1 => 'Tidak Sama Sekali',
            2 => 'Kurang',
            3 => 'Cukup Besar',
            4 => 'Besar',
            5 => 'Sangat Besar',
        ];

        $passwordHash = Hash::make('password123');
        $graduatedDate = '2026-02-15';
        $studyDate = '2026-08-01';

        foreach ($alumniList as $idx => $item) {
            $prodiObj = $prodis->get($item['prodi']);
            if (! $prodiObj) {
                continue;
            }

            $nim = $item['nim'];
            $nama = $item['nama'];
            $email = strtolower(str_replace(' ', '', explode(' ', $nama)[0])).$item['prodi'].$nim.'@students.ukdw.ac.id';

            // 1. User
            $user = User::firstOrCreate(
                ['username' => $nim],
                [
                    'name' => $nama,
                    'email' => $email,
                    'password' => $passwordHash,
                    'role' => 'alumni',
                    'prodi_id' => $prodiObj->id,
                    'fakultas_id' => $prodiObj->fakultas_id,
                    'must_change_password' => 0,
                ]
            );

            // 2. Data Akademik dengan sebaran IPK realistis (kombinasi 2.xx dan 3.xx)
            $ipkPool = [
                2.48, 2.65, 2.78, 2.85, 2.92, // Kelompok IPK 2 - <3
                3.05, 3.18, 3.25, 3.34, 3.42, // Kelompok IPK 3 - <3.5
                3.55, 3.65, 3.72, 3.80, 3.92, // Kelompok IPK 3.5 - 4.0
            ];
            $ipk = $ipkPool[$idx % count($ipkPool)];
            $totalSks = ($item['prodi'] === '61') ? 160 : 144;
            $birthDate = '2001-'.str_pad((string) (($idx % 12) + 1), 2, '0', STR_PAD_LEFT).'-'.str_pad((string) (($idx % 28) + 1), 2, '0', STR_PAD_LEFT);
            $personalEmail = strtolower(str_replace(' ', '', explode(' ', $nama)[0])).'.alumni26@gmail.com';
            $nomorTelepon = '0813'.str_pad((string) (10000000 + $idx * 17), 8, '0', STR_PAD_LEFT);

            $dataAkademik = DataAkademik::updateOrCreate(
                ['nim' => $nim],
                [
                    'nama' => $nama,
                    'email_pribadi' => $personalEmail,
                    'prodi_id' => $prodiObj->id,
                    'angkatan_masuk' => 2022,
                    'tahun_akademik_masuk' => '2022/2023 (Semester Ganjil)',
                    'status_mahasiswa' => 'Lulus',
                    'tahun_akademik_lulus' => 'Gasal 2025/2026',
                    'tahun_lulus' => 2026,
                    'tanggal_lulus' => $graduatedDate,
                    'ip_kumulatif' => $ipk,
                    'total_sks' => $totalSks,
                    'total_angka_kualitas' => round($ipk * $totalSks, 2),
                    'tanggal_lahir' => $birthDate,
                    'jenis_kelamin' => $item['jk'],
                    'warga_negara' => 'WNI',
                ]
            );

            // 3. Yudisium
            $yudisium = Yudisium::updateOrCreate(
                ['nim' => $nim],
                [
                    'judul_ta' => 'Riset Komparasi dan Evaluasi Keberlanjutan dalam Bidang '.$prodiObj->nama_prodi,
                    'judul_ta_inggris' => 'Comparative Research and Sustainability Evaluation in '.$prodiObj->nama_prodi,
                    'url_publikasi' => 'https://repository.ukdw.ac.id/handle/123456789/'.$nim,
                    'jenis_publikasi' => 'Jurnal Nasional Terakreditasi',
                    'status_publikasi' => 'Terbit',
                    'keterangan_hasil_yudisium' => $ipk >= 3.75 ? 'Lulus dengan Pujian (Cumlaude)' : 'Sangat Memuaskan',
                    'status_lulus' => 'Lulus',
                ]
            );

            // 4. Perusahaan & Profil Karier
            $comp = $item['company'] ? ($companies[$item['company']] ?? null) : null;
            $statusKarier = $item['status'];
            $profileCareerStatus = match ($statusKarier) {
                'Karyawan' => 'Bekerja (Full Time)',
                'Founder' => 'Wirausaha',
                'Freelancer' => 'Freelance / Konsultan Mandiri',
                'Studi Lanjut' => 'Melanjutkan Pendidikan',
                default => 'Bekerja (Full Time)',
            };

            $isStudy = $statusKarier === 'Studi Lanjut';
            $pendTingkat = $isStudy ? 'S2' : null;
            $ptStudi = $isStudy ? 'Sekolah Pascasarjana Universitas Gadjah Mada' : null;
            $prodiStudi = $isStudy ? 'Magister '.$prodiObj->nama_prodi : null;
            $tglStudi = $isStudy ? $studyDate : null;

            // 5. Biodata
            $biodata = Biodata::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'nim' => $nim,
                    'yudisium_id' => $yudisium->id,
                    'prodi_id' => $prodiObj->id,
                    'email_pribadi' => $personalEmail,
                    'nomor_telepon' => $nomorTelepon,
                    'alamat_saat_ini' => 'Jl. Kaliurang Km. '.($idx % 10 + 5).' No. '.($idx * 3 + 12).', Condongcatur, Sleman, DI Yogyakarta',
                    'kategori_pekerjaan' => $profileCareerStatus,
                    'perusahaan_id' => $comp?->id,
                    'posisi_jabatan' => $item['posisi'],
                    'posisi_wiraswasta' => $statusKarier === 'Founder' ? 'Founder / Co-Founder' : ($statusKarier === 'Freelancer' ? 'Freelancer' : null),
                    'gaji' => $item['gaji'],
                    'pendidikan_tingkat' => $pendTingkat,
                    'perguruan_tinggi' => $ptStudi,
                    'pendidikan_prodi' => $prodiStudi,
                    'skills' => 'Professional Expertise in '.$prodiObj->nama_prodi,
                    'experience' => '3+ years experience as '.$item['posisi'],
                ]
            );

            // 6. Jawaban Kuesioner Universitas Lengkap
            $answers = [];

            // Identitas dasar
            $answers['F1'] = $nim;
            $answers['F2A'] = $nama;
            $answers['F2B'] = $dataAkademik->nomor_hp;
            $answers['F2C'] = $biodata->email_pribadi;
            $answers['F2D'] = $biodata->alamat_domisili;

            // Status F8
            $answers['F8'] = match ($statusKarier) {
                'Karyawan' => 'Bekerja (full time/part time)',
                'Founder', 'Freelancer' => 'Wiraswasta',
                'Studi Lanjut' => 'Melanjutkan Pendidikan',
                default => 'Bekerja (full time/part time)',
            };

            // Masa tunggu & Pencarian Kerja
            if ($item['wait_type'] === 'before') {
                $answers['F504'] = 'Ya';
                $answers['F502'] = (string) $item['wait_months'];
                $answers['F506'] = '0';
                $answers['F3'] = 'Sebelum lulus '.$item['wait_months'].' bulan';
            } elseif ($item['wait_type'] === 'after') {
                $answers['F504'] = 'Tidak';
                $answers['F502'] = '0';
                $answers['F506'] = (string) $item['wait_months'];
                $answers['F3'] = 'Sesudah lulus '.$item['wait_months'].' bulan';
            } else {
                // Studi lanjut
                $answers['F504'] = 'Tidak';
                $answers['F502'] = '0';
                $answers['F506'] = (string) $item['wait_months'];
                $answers['F3'] = 'Saya tidak mencari kerja';
                $answers['F18b'] = $ptStudi;
                $answers['F18c'] = $prodiStudi;
                $answers['F18d'] = $tglStudi;
                $answers['F12'] = 'Beasiswa Pemerintah';
            }

            // Gaji
            $answers['F505'] = (string) $item['gaji'];

            // Detail Perusahaan / Wirausaha
            if ($comp) {
                $answers['F2E'] = $comp->nama_perusahaan;
                $answers['F5B'] = $comp->nama_perusahaan;
                $answers['F11'] = $comp->jenis_perusahaan;
            } elseif ($statusKarier === 'Founder') {
                $answers['F2E'] = 'Wirausaha Mandiri';
                $answers['F5B'] = 'Wirausaha Mandiri';
                $answers['F11'] = 'Wiraswasta/perusahaan sendiri';
                $answers['F5C'] = 'Founder';
            } elseif ($statusKarier === 'Freelancer') {
                $answers['F2E'] = 'Pekerja Mandiri / Lepas';
                $answers['F5B'] = 'Pekerja Mandiri / Lepas';
                $answers['F11'] = 'Lainnya';
                $answers['F5C'] = 'Freelancer / Konsultan Mandiri';
            }

            // Keselarasan F14 & F15
            $answers['F14'] = match ($idx % 3) {
                0 => 'Sangat erat',
                1 => 'Erat',
                default => 'Cukup erat',
            };
            $answers['F15'] = 'Tingkat yang sama';

            // Kompetensi F17a & F17b (Variasi skor penguasaan vs kebutuhan)
            $compScales = ['Sangat Rendah', 'Rendah', 'Cukup', 'Tinggi', 'Sangat Tinggi'];
            for ($k = 1; $k <= 7; $k++) {
                $gradVal = min(5, max(2, (($idx + $k) % 4) + 2));
                $needVal = min(5, max(3, (($idx + $k + 1) % 3) + 3));
                $answers['F17a'.$k] = $compScales[$gradVal - 1];
                $answers['F17b'.$k] = $compScales[$needVal - 1];
            }

            // Penekanan Metode Pembelajaran F21 s.d. F27 Sesuai Rumpun Program Studi
            $baseMethodScores = $methodProfilesByProdi[$item['prodi']] ?? [5, 4, 4, 5, 4, 3, 5];
            $methodKeys = ['F21', 'F22', 'F23', 'F24', 'F25', 'F26', 'F27'];

            foreach ($methodKeys as $mIdx => $mCode) {
                $baseScore = $baseMethodScores[$mIdx];
                // Variasi individual realistis dengan sesekali perbedaan -1
                $jitter = ($idx % 5 === 0 && $mIdx % 2 === 0) ? -1 : 0;
                $finalScore = min(5, max(1, $baseScore + $jitter));
                $answers[$mCode] = $scaleDescriptions[$finalScore];
            }

            // Simpan seluruh jawaban ke tabel kuesioner_jawaban
            foreach ($answers as $qCode => $answerVal) {
                $qObj = $univQuestions->get($qCode);
                if (! $qObj) {
                    continue;
                }

                $matchedOption = $qObj->opsi->first(fn ($o) => mb_strtolower(trim((string) $o->label)) === mb_strtolower(trim((string) $answerVal)));

                KuesionerJawaban::updateOrCreate(
                    [
                        'biodata_id' => $biodata->id,
                        'pertanyaan_id' => $qObj->id,
                    ],
                    [
                        'section_id' => $univSectionId,
                        'kode_pertanyaan' => $qCode,
                        'opsi_id' => $matchedOption?->id,
                        'kode_opsi' => $matchedOption?->kode_opsi,
                        'jawaban_teks' => (string) $answerVal,
                        'jawaban_json' => is_array($answerVal) ? $answerVal : (is_numeric($answerVal) ? [(string) $answerVal] : null),
                        'responden_type' => 'alumni',
                    ]
                );
            }
        }
    }
}
