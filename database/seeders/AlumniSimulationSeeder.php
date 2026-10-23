<?php

namespace Database\Seeders;

use App\Models\Atasan;
use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\Kabupaten;
use App\Models\Perusahaan;
use App\Models\Prodi;
use App\Models\ProdiQuestion;
use App\Models\ProdiResponse;
use App\Models\Propinsi;
use App\Models\RefSubpertanyaan2021;
use App\Models\Tracer;
use App\Models\User;
use App\Models\Yudisium;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder Simulasi Alumni Tracer Study Komprehensif (12 Program Studi UKDW)
 *
 * Tujuan:
 * Menyiapkan data simulasi riil kuesioner Tracer Study untuk seluruh 12 Program Studi di UKDW.
 * Setiap Program Studi memiliki minimal 10 alumni dengan distribusi data:
 * - 4 Orang: Lengkap 100% (Status: Selesai) dengan 4 Kategori Karier Berbeda:
 *     1. Bekerja di Perusahaan Swasta / BUMN (Dapat kerja sebelum lulus / F504 = Ya)
 *     2. Wiraswasta / Pendiri Usaha Mandiri (Founder & Owner)
 *     3. Melanjutkan Pendidikan S2 (Studi Lanjut Pascasarjana & Beasiswa LPDP)
 *     4. Bekerja di Startup / Industri Kreatif / Multinasional (Dapat kerja setelah lulus / F504 = Tidak)
 * - 3 Orang: Sedang Mengisi (Status: Belum Selesai, progress pengisian 40% - 75%)
 * - 3 Orang: Belum Mengisi (Status: Belum Selesai, hanya data induk akademik & yudisium)
 *
 * Seluruh alumni memiliki catatan kelulusan Yudisium dengan proses_yudisium = 'Lulus'
 * serta tahun_akademik_lulus yang sinkron persis dengan tahun_lulus.
 */
class AlumniSimulationSeeder extends Seeder
{
    /**
     * Jalankan database seeds simulasi alumni.
     */
    public function run(): void
    {
        // 1. Ambil seluruh master Program Studi UKDW
        $prodis = Prodi::all()->keyBy('kode_prodi');
        if ($prodis->isEmpty()) {
            $this->command->error('Master Prodi belum di-seed!');

            return;
        }

        // 2. Siapkan data referensi wilayah & instansi
        $propinsiDiy = Propinsi::where('kode_provinsi', '34')->orWhere('nama_provinsi', 'LIKE', '%Yogyakarta%')->first();
        $kabSleman = Kabupaten::where('kode_kabupaten', '34.04')->orWhere('nama_kabupaten', 'LIKE', '%Sleman%')->first();
        $kabKotaYogya = Kabupaten::where('kode_kabupaten', '34.71')->orWhere('nama_kabupaten', 'LIKE', '%Yogyakarta%')->first();

        $propinsiJkt = Propinsi::where('kode_provinsi', '31')->orWhere('nama_provinsi', 'LIKE', '%Jakarta%')->first();
        $kabJaksel = Kabupaten::where('kode_kabupaten', '31.74')->orWhere('nama_kabupaten', 'LIKE', '%Jakarta Selatan%')->first();
        $kabJakpus = Kabupaten::where('kode_kabupaten', '31.71')->orWhere('nama_kabupaten', 'LIKE', '%Jakarta Pusat%')->first();

        $perusahaans = Perusahaan::all();

        // 3. Matriks Target Kelulusan Sinkron (Representasi Lengkap 2021 s/d 2026)
        // Format: [angkatan_masuk, tahun_akademik_lulus, tahun_lulus]
        $targetKelulusanList = [
            ['angkatan' => '2017', 'semester' => 'Gasal 2020/2021', 'tahun' => 2021], // Index 0: Selesai (Kategori 1: Swasta/BUMN)
            ['angkatan' => '2017', 'semester' => 'Genap 2020/2021', 'tahun' => 2021], // Index 1: Selesai (Kategori 2: Wiraswasta)
            ['angkatan' => '2018', 'semester' => 'Gasal 2021/2022', 'tahun' => 2022], // Index 2: Selesai (Kategori 3: Studi Lanjut S2)
            ['angkatan' => '2018', 'semester' => 'Genap 2021/2022', 'tahun' => 2022], // Index 3: Selesai (Kategori 4: Startup/Industri Kreatif)
            ['angkatan' => '2019', 'semester' => 'Gasal 2022/2023', 'tahun' => 2023], // Index 4: Sedang Mengisi (Progress ~60%)
            ['angkatan' => '2019', 'semester' => 'Genap 2022/2023', 'tahun' => 2023], // Index 5: Sedang Mengisi (Progress ~50%)
            ['angkatan' => '2020', 'semester' => 'Gasal 2023/2024', 'tahun' => 2024], // Index 6: Sedang Mengisi (Progress ~40%)
            ['angkatan' => '2020', 'semester' => 'Genap 2023/2024', 'tahun' => 2024], // Index 7: Belum Mengisi (0%)
            ['angkatan' => '2021', 'semester' => 'Gasal 2024/2025', 'tahun' => 2025], // Index 8: Belum Mengisi (0%)
            ['angkatan' => '2022', 'semester' => 'Genap 2025/2026', 'tahun' => 2026], // Index 9: Belum Mengisi (0%)
        ];

        // 4. Bank 120 Nama Mahasiswa Riil Unik Tanpa Duplikasi Lintas Seluruh 12 Prodi UKDW
        $bankNamaPerProdi = [
            // FTI - Informatika (71)
            '71' => [
                0 => ['nama' => 'Benedictus Daniel Setiawan', 'jk' => 'Laki-laki', 'agama' => 'Katolik'],
                1 => ['nama' => 'Clara Anjelita Kusumadewi', 'jk' => 'Perempuan', 'agama' => 'Kristen Protestan'],
                2 => ['nama' => 'Christian Aditya Pratama', 'jk' => 'Laki-laki', 'agama' => 'Kristen Protestan'],
                3 => ['nama' => 'Theresia Cindy Paramitha', 'jk' => 'Perempuan', 'agama' => 'Katolik'],
                4 => ['nama' => 'Jonathan Edward Wibowo', 'jk' => 'Laki-laki', 'agama' => 'Kristen Protestan'],
                5 => ['nama' => 'Gabriella Maria Santoso', 'jk' => 'Perempuan', 'agama' => 'Katolik'],
                6 => ['nama' => 'Timothy Kevin Kurniawan', 'jk' => 'Laki-laki', 'agama' => 'Kristen Protestan'],
                7 => ['nama' => 'Angelina Fiona Putri', 'jk' => 'Perempuan', 'agama' => 'Islam'],
                8 => ['nama' => 'Samuel Evan Prasetya', 'jk' => 'Laki-laki', 'agama' => 'Kristen Protestan'],
                9 => ['nama' => 'Jessica Laurencia Tan', 'jk' => 'Perempuan', 'agama' => 'Buddha'],
            ],
            // FTI - Sistem Informasi (72)
            '72' => [
                0 => ['nama' => 'David Alexander Haryanto', 'jk' => 'Laki-laki', 'agama' => 'Kristen Protestan'],
                1 => ['nama' => 'Stephanie Amanda Sihombing', 'jk' => 'Perempuan', 'agama' => 'Kristen Protestan'],
                2 => ['nama' => 'Michael Steven Nugroho', 'jk' => 'Laki-laki', 'agama' => 'Katolik'],
                3 => ['nama' => 'Nathania Grace Gunawan', 'jk' => 'Perempuan', 'agama' => 'Kristen Protestan'],
                4 => ['nama' => 'Andreas Timothy Wijaya', 'jk' => 'Laki-laki', 'agama' => 'Kristen Protestan'],
                5 => ['nama' => 'Felicia Michelle Chandra', 'jk' => 'Perempuan', 'agama' => 'Katolik'],
                6 => ['nama' => 'Reynaldi Bagus Prakoso', 'jk' => 'Laki-laki', 'agama' => 'Islam'],
                7 => ['nama' => 'Patricia Clarissa Dewanti', 'jk' => 'Perempuan', 'agama' => 'Katolik'],
                8 => ['nama' => 'Vincentius Bagas Baskoro', 'jk' => 'Laki-laki', 'agama' => 'Katolik'],
                9 => ['nama' => 'Vanessa Stephanie Hartono', 'jk' => 'Perempuan', 'agama' => 'Kristen Protestan'],
            ],
            // FB - Manajemen (11)
            '11' => [
                0 => ['nama' => 'Adrian Pratama Yudha', 'jk' => 'Laki-laki', 'agama' => 'Islam'],
                1 => ['nama' => 'Beatrice Natasha Widjaja', 'jk' => 'Perempuan', 'agama' => 'Kristen Protestan'],
                2 => ['nama' => 'Calvin Christopher Susanto', 'jk' => 'Laki-laki', 'agama' => 'Katolik'],
                3 => ['nama' => 'Danielle Felicia Surya', 'jk' => 'Perempuan', 'agama' => 'Kristen Protestan'],
                4 => ['nama' => 'Edward Jonathan Halim', 'jk' => 'Laki-laki', 'agama' => 'Kristen Protestan'],
                5 => ['nama' => 'Fiona Clarissa Salim', 'jk' => 'Perempuan', 'agama' => 'Buddha'],
                6 => ['nama' => 'Gilbert Natanael Tedja', 'jk' => 'Laki-laki', 'agama' => 'Kristen Protestan'],
                7 => ['nama' => 'Helena Patricia Winata', 'jk' => 'Perempuan', 'agama' => 'Katolik'],
                8 => ['nama' => 'Ivan Sebastian Wardhana', 'jk' => 'Laki-laki', 'agama' => 'Kristen Protestan'],
                9 => ['nama' => 'Jennifer Olivia Gunawan', 'jk' => 'Perempuan', 'agama' => 'Kristen Protestan'],
            ],
            // FB - Akuntansi (12)
            '12' => [
                0 => ['nama' => 'Kenneth Alexander Hartanto', 'jk' => 'Laki-laki', 'agama' => 'Kristen Protestan'],
                1 => ['nama' => 'Laurencia Cindy Kusuma', 'jk' => 'Perempuan', 'agama' => 'Katolik'],
                2 => ['nama' => 'Matthew Nicholas Tanudjaya', 'jk' => 'Laki-laki', 'agama' => 'Kristen Protestan'],
                3 => ['nama' => 'Nicole Valerie Pangestu', 'jk' => 'Perempuan', 'agama' => 'Buddha'],
                4 => ['nama' => 'Oliver Gabriel Sugiarto', 'jk' => 'Laki-laki', 'agama' => 'Katolik'],
                5 => ['nama' => 'Pamela Aurelia Santoso', 'jk' => 'Perempuan', 'agama' => 'Kristen Protestan'],
                6 => ['nama' => 'Quincy Brian Wijaya', 'jk' => 'Laki-laki', 'agama' => 'Kristen Protestan'],
                7 => ['nama' => 'Rebecca Devina Chandra', 'jk' => 'Perempuan', 'agama' => 'Katolik'],
                8 => ['nama' => 'Stefanus Reynard Salim', 'jk' => 'Laki-laki', 'agama' => 'Katolik'],
                9 => ['nama' => 'Tiffany Amanda Kristianto', 'jk' => 'Perempuan', 'agama' => 'Kristen Protestan'],
            ],
            // FAD - Arsitektur (21)
            '21' => [
                0 => ['nama' => 'Albertus Rio Pramono', 'jk' => 'Laki-laki', 'agama' => 'Katolik'],
                1 => ['nama' => 'Bianca Michelle Nugraha', 'jk' => 'Perempuan', 'agama' => 'Kristen Protestan'],
                2 => ['nama' => 'Christopher Evan Saputra', 'jk' => 'Laki-laki', 'agama' => 'Kristen Protestan'],
                3 => ['nama' => 'Deborah Astrid Soetanto', 'jk' => 'Perempuan', 'agama' => 'Katolik'],
                4 => ['nama' => 'Emmanuel Kevin Surya', 'jk' => 'Laki-laki', 'agama' => 'Katolik'],
                5 => ['nama' => 'Florentina Citra Lestari', 'jk' => 'Perempuan', 'agama' => 'Katolik'],
                6 => ['nama' => 'Geraldus Dimas Wicaksono', 'jk' => 'Laki-laki', 'agama' => 'Islam'],
                7 => ['nama' => 'Hannah Kimberly Anggoro', 'jk' => 'Perempuan', 'agama' => 'Kristen Protestan'],
                8 => ['nama' => 'Ignatius Bayu Rahardjo', 'jk' => 'Laki-laki', 'agama' => 'Katolik'],
                9 => ['nama' => 'Janice Michelle Wibisono', 'jk' => 'Perempuan', 'agama' => 'Kristen Protestan'],
            ],
            // FAD - Desain Produk (22)
            '22' => [
                0 => ['nama' => 'Julian Bagus Adiputra', 'jk' => 'Laki-laki', 'agama' => 'Islam'],
                1 => ['nama' => 'Karen Stephanie Tandiono', 'jk' => 'Perempuan', 'agama' => 'Kristen Protestan'],
                2 => ['nama' => 'Leonardus Rio Setiadi', 'jk' => 'Laki-laki', 'agama' => 'Katolik'],
                3 => ['nama' => 'Michelle Andrea Prabowo', 'jk' => 'Perempuan', 'agama' => 'Kristen Protestan'],
                4 => ['nama' => 'Nathanael Dennis Suwandi', 'jk' => 'Laki-laki', 'agama' => 'Kristen Protestan'],
                5 => ['nama' => 'Olivia Nathania Gunardi', 'jk' => 'Perempuan', 'agama' => 'Katolik'],
                6 => ['nama' => 'Philipus Kevin Hermawan', 'jk' => 'Laki-laki', 'agama' => 'Katolik'],
                7 => ['nama' => 'Queeny Felita Sasongko', 'jk' => 'Perempuan', 'agama' => 'Kristen Protestan'],
                8 => ['nama' => 'Raymond Christian Kusumo', 'jk' => 'Laki-laki', 'agama' => 'Kristen Protestan'],
                9 => ['nama' => 'Sherly Vania Anggraeni', 'jk' => 'Perempuan', 'agama' => 'Islam'],
            ],
            // Biotek - Biologi (41)
            '41' => [
                0 => ['nama' => 'Antonius Bagas Prakoso', 'jk' => 'Laki-laki', 'agama' => 'Katolik'],
                1 => ['nama' => 'Brigitta Ayu Maharani', 'jk' => 'Perempuan', 'agama' => 'Katolik'],
                2 => ['nama' => 'Cornelius Arya Danendra', 'jk' => 'Laki-laki', 'agama' => 'Katolik'],
                3 => ['nama' => 'Dominique Vania Pradita', 'jk' => 'Perempuan', 'agama' => 'Kristen Protestan'],
                4 => ['nama' => 'Fabianus Ryan Hendrawan', 'jk' => 'Laki-laki', 'agama' => 'Katolik'],
                5 => ['nama' => 'Gabriella Sekar Kinanthi', 'jk' => 'Perempuan', 'agama' => 'Kristen Protestan'],
                6 => ['nama' => 'Hendricus Galih Permana', 'jk' => 'Laki-laki', 'agama' => 'Katolik'],
                7 => ['nama' => 'Irene Larasati Wulandari', 'jk' => 'Perempuan', 'agama' => 'Islam'],
                8 => ['nama' => 'Justinus Ilham Cahyono', 'jk' => 'Laki-laki', 'agama' => 'Katolik'],
                9 => ['nama' => 'Karolina Dwi Rahmawati', 'jk' => 'Perempuan', 'agama' => 'Islam'],
            ],
            // Biotek - Teknologi Pangan (42)
            '42' => [
                0 => ['nama' => 'Lambertus Agung Wicaksono', 'jk' => 'Laki-laki', 'agama' => 'Katolik'],
                1 => ['nama' => 'Marcella Prameswari Dewi', 'jk' => 'Perempuan', 'agama' => 'Kristen Protestan'],
                2 => ['nama' => 'Nicolaus Yudha Pratama', 'jk' => 'Laki-laki', 'agama' => 'Katolik'],
                3 => ['nama' => 'Odilia Citra Puspitasari', 'jk' => 'Perempuan', 'agama' => 'Katolik'],
                4 => ['nama' => 'Paulus Rian Kurniasandi', 'jk' => 'Laki-laki', 'agama' => 'Katolik'],
                5 => ['nama' => 'Regina Shafa Azzahra', 'jk' => 'Perempuan', 'agama' => 'Islam'],
                6 => ['nama' => 'Stefanus Dimas Anggoro', 'jk' => 'Laki-laki', 'agama' => 'Katolik'],
                7 => ['nama' => 'Theodora Nindya Kirana', 'jk' => 'Perempuan', 'agama' => 'Kristen Protestan'],
                8 => ['nama' => 'Urbanus Rio Pambudi', 'jk' => 'Laki-laki', 'agama' => 'Katolik'],
                9 => ['nama' => 'Veronica Salsabila Putri', 'jk' => 'Perempuan', 'agama' => 'Islam'],
            ],
            // Kedokteran (61)
            '61' => [
                0 => ['nama' => 'Alexander William Hartono', 'jk' => 'Laki-laki', 'agama' => 'Kristen Protestan'],
                1 => ['nama' => 'Belinda Christine Wijaya', 'jk' => 'Perempuan', 'agama' => 'Kristen Protestan'],
                2 => ['nama' => 'Christian Jeremy Tedjokusumo', 'jk' => 'Laki-laki', 'agama' => 'Katolik'],
                3 => ['nama' => 'Dian Natalia Soedirgo', 'jk' => 'Perempuan', 'agama' => 'Katolik'],
                4 => ['nama' => 'Eric Fernando Santoso', 'jk' => 'Laki-laki', 'agama' => 'Kristen Protestan'],
                5 => ['nama' => 'Felisitas Maya Sukamto', 'jk' => 'Perempuan', 'agama' => 'Katolik'],
                6 => ['nama' => 'Gregory Hans Tanuwidjaja', 'jk' => 'Laki-laki', 'agama' => 'Kristen Protestan'],
                7 => ['nama' => 'Hilda Carissa Suhanda', 'jk' => 'Perempuan', 'agama' => 'Kristen Protestan'],
                8 => ['nama' => 'Ivan Darryl Kusumah', 'jk' => 'Laki-laki', 'agama' => 'Buddha'],
                9 => ['nama' => 'Jovita Evelyn Susilo', 'jk' => 'Perempuan', 'agama' => 'Katolik'],
            ],
            // Theologi - Filsafat Keilahian (31)
            '31' => [
                0 => ['nama' => 'Abraham Yehezkiel Nababan', 'jk' => 'Laki-laki', 'agama' => 'Kristen Protestan'],
                1 => ['nama' => 'Abigail Damaris Situmorang', 'jk' => 'Perempuan', 'agama' => 'Kristen Protestan'],
                2 => ['nama' => 'Benyamin Kornelius Simanjuntak', 'jk' => 'Laki-laki', 'agama' => 'Kristen Protestan'],
                3 => ['nama' => 'Deborah Ruth Hutabarat', 'jk' => 'Perempuan', 'agama' => 'Kristen Protestan'],
                4 => ['nama' => 'Ezra Nathanael Pasaribu', 'jk' => 'Laki-laki', 'agama' => 'Kristen Protestan'],
                5 => ['nama' => 'Filadelfia Ester Manurung', 'jk' => 'Perempuan', 'agama' => 'Kristen Protestan'],
                6 => ['nama' => 'Gideon Samuel Marpaung', 'jk' => 'Laki-laki', 'agama' => 'Kristen Protestan'],
                7 => ['nama' => 'Hanna Maria Silalahi', 'jk' => 'Perempuan', 'agama' => 'Kristen Protestan'],
                8 => ['nama' => 'Immanuel Yohanes Nainggolan', 'jk' => 'Laki-laki', 'agama' => 'Kristen Protestan'],
                9 => ['nama' => 'Junita Kristina Siregar', 'jk' => 'Perempuan', 'agama' => 'Kristen Protestan'],
            ],
            // FKH - Pendidikan Bahasa Inggris (81)
            '81' => [
                0 => ['nama' => 'Anthony Kevin Widiantoro', 'jk' => 'Laki-laki', 'agama' => 'Kristen Protestan'],
                1 => ['nama' => 'Brenda Callista Wardhani', 'jk' => 'Perempuan', 'agama' => 'Islam'],
                2 => ['nama' => 'Clifford Bryan Soeharto', 'jk' => 'Laki-laki', 'agama' => 'Katolik'],
                3 => ['nama' => 'Daphne Maureen Susanto', 'jk' => 'Perempuan', 'agama' => 'Kristen Protestan'],
                4 => ['nama' => 'Edwin Richard Natasasmita', 'jk' => 'Laki-laki', 'agama' => 'Kristen Protestan'],
                5 => ['nama' => 'Fiona Clarice Handayani', 'jk' => 'Perempuan', 'agama' => 'Katolik'],
                6 => ['nama' => 'Glenn Patrick Wiryawan', 'jk' => 'Laki-laki', 'agama' => 'Kristen Protestan'],
                7 => ['nama' => 'Hazel Audrey Permatasari', 'jk' => 'Perempuan', 'agama' => 'Islam'],
                8 => ['nama' => 'Ian Gregory Sudarmono', 'jk' => 'Laki-laki', 'agama' => 'Katolik'],
                9 => ['nama' => 'Jessica Audrey Wibowo', 'jk' => 'Perempuan', 'agama' => 'Kristen Protestan'],
            ],
            // FKH - Studi Humanitas (82)
            '82' => [
                0 => ['nama' => 'Aloysius Raditya Dananjaya', 'jk' => 'Laki-laki', 'agama' => 'Katolik'],
                1 => ['nama' => 'Bernadette Sekar Ayu', 'jk' => 'Perempuan', 'agama' => 'Katolik'],
                2 => ['nama' => 'Claudius Bima Senopati', 'jk' => 'Laki-laki', 'agama' => 'Katolik'],
                3 => ['nama' => 'Dorothea Sekar Langit', 'jk' => 'Perempuan', 'agama' => 'Katolik'],
                4 => ['nama' => 'Eusebius Bagas Priyambodo', 'jk' => 'Laki-laki', 'agama' => 'Katolik'],
                5 => ['nama' => 'Francisca Ratna Juwita', 'jk' => 'Perempuan', 'agama' => 'Katolik'],
                6 => ['nama' => 'Gregorius Bayu Samudra', 'jk' => 'Laki-laki', 'agama' => 'Katolik'],
                7 => ['nama' => 'Helena Putri Kinasih', 'jk' => 'Perempuan', 'agama' => 'Islam'],
                8 => ['nama' => 'Irenaeus Wisnu Wardana', 'jk' => 'Laki-laki', 'agama' => 'Katolik'],
                9 => ['nama' => 'Jacinda Tiara Kusuma', 'jk' => 'Perempuan', 'agama' => 'Kristen Protestan'],
            ],
        ];

        // 5. Bank Judul Tugas Akhir / Skripsi Representatif per Program Studi
        $bankJudulTA = [
            '71' => 'Rancang Bangun Sistem Klasifikasi Citra Medis Berbasis Convolutional Neural Network',
            '72' => 'Analisis dan Perancangan Sistem Informasi Enterprise Resource Planning Terintegrasi',
            '11' => 'Pengaruh Digital Marketing dan Brand Trust terhadap Keputusan Pembelian Konsumen Generasi Z',
            '12' => 'Analisis Pengaruh Fraud Triangle dan Whistleblowing System terhadap Pencegahan Kecurangan Finansial',
            '21' => 'Perancangan Kawasan Pusat Kebudayaan Tropis Berkelanjutan dengan Konsep Biophilic Design',
            '22' => 'Desain Alat Bantu Fisioterapi Portabel Ramah Disabilitas Berbasis Ergonomi Produk',
            '41' => 'Uji Aktivitas Antibakteri Ekstrak Daun Kelor terhadap Pertumbuhan Bakteri Patogen Pangan',
            '42' => 'Karakterisasi Fisikokimia dan Sensoris Produk Fermentasi Tepung Mocaf Fortifikasi Serat Alami',
            '61' => 'Hubungan Pola Tidur dan Tingkat Stres terhadap Indeks Prestasi Akademik Mahasiswa Kedokteran',
            '31' => 'Konstruksi Teologi Persahabatan Kontekstual dalam Pelayanan Komunitas Lintas Agama di Era Post-Truth',
            '81' => 'The Effectiveness of Task-Based Language Teaching in Improving ESL Students Speaking Fluency',
            '82' => 'Representasi Identitas Sosio-Kultural Masyarakat Urban dalam Film Indonesia Kontemporer',
        ];

        // 6. Ambil master butir kuesioner universitas (ref_subpertanyaan2021)
        $univQuestions = RefSubpertanyaan2021::all()->keyBy('kode_pertanyaan');

        // Iterasi ke seluruh 12 Program Studi
        foreach ($prodis as $kodeProdi => $prodi) {
            $kodeProdiStr = (string) $kodeProdi;
            // Butir pertanyaan spesifik prodi (jika ada)
            $prodiQuestions = ProdiQuestion::where('prodi_id', $prodi->id)
                ->where('type', '!=', 'header')
                ->get();

            // Ambil daftar nama khusus program studi ini
            $prodiNamaList = $bankNamaPerProdi[$kodeProdiStr] ?? $bankNamaPerProdi['71'];

            // Iterasi 10 Alumni untuk Program Studi Ini
            for ($i = 0; $i < 10; $i++) {
                $cfg = $targetKelulusanList[$i];
                $angkatan = $cfg['angkatan'];
                $duaDigitAngkatan = substr($angkatan, 2, 2);

                // NIM format standar 8 digit UKDW: [kode_prodi (2 digit)][angkatan (2 digit)][nomor_urut (4 digit)]
                $nomorUrut = str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);
                $nim = $kodeProdiStr.$duaDigitAngkatan.$nomorUrut;

                // Khusus Sistem Informasi (72): Jangan bentrok dengan akun pengujian Joshua Andrean (72220001)
                if ($kodeProdiStr === '72' && $nim === '72220001') {
                    $nim = '72220099';
                }

                $alumniBio = $prodiNamaList[$i] ?? ['nama' => 'Alumni '.$kodeProdiStr.' '.$i, 'jk' => 'Laki-laki', 'agama' => 'Kristen Protestan'];
                $nama = $alumniBio['nama'];
                $isLaki = ($alumniBio['jk'] === 'Laki-laki');
                $agama = $alumniBio['agama'];
                $birthYear = (int) $angkatan - 18;
                $birthDate = $birthYear.'-08-15';
                $defaultPassword = Carbon::parse($birthDate)->format('dmY');

                $personalEmail = strtolower(str_replace(' ', '', explode(' ', $nama)[0])).$kodeProdiStr.($i + 1).'@gmail.com';

                // A. Buat / Perbarui Akun User Alumni
                $user = User::updateOrCreate(
                    ['username' => $nim],
                    [
                        'name' => $nama,
                        'email' => $personalEmail,
                        'password' => Hash::make($defaultPassword),
                        'role' => 'alumni',
                        'prodi_id' => $prodi->id,
                        'must_change_password' => true,
                    ]
                );

                // B. Buat Data Induk Akademik Mahasiswa (Single Source of Truth)
                $ipk = round(3.40 + (($i * 6) % 55) / 100, 2);
                $totalSks = ($kodeProdiStr === '61') ? 160 : 144;
                $dataAkademik = DataAkademik::updateOrCreate(
                    ['nim' => $nim],
                    [
                        'nama' => $nama,
                        'angkatan_masuk' => (int) $angkatan,
                        'status_mahasiswa' => 'Lulus',
                        'tahun_akademik_lulus' => $cfg['semester'],
                        'tahun_lulus' => $cfg['tahun'],
                        'tanggal_lulus' => $cfg['tahun'].'-'.($i % 2 === 0 ? '02-15' : '08-20'),
                        'ip_kumulatif' => $ipk,
                        'total_sks' => $totalSks,
                        'total_angka_kualitas' => round($ipk * $totalSks, 2),
                        'tempat_lahir' => ($i % 2 === 0 ? 'Yogyakarta' : 'Jakarta'),
                        'tanggal_lahir' => $birthDate,
                        'agama' => $agama,
                        'jenis_kelamin' => $alumniBio['jk'],
                        'golongan_darah' => ['O', 'A', 'B', 'AB'][$i % 4],
                        'warga_negara' => 'WNI',
                        'nisn' => '003'.str_pad((string) (1234500 + (int) $kodeProdiStr * 10 + $i), 7, '0', STR_PAD_LEFT),
                        'no_bpjs' => '000189'.str_pad((string) (7654000 + (int) $kodeProdiStr * 10 + $i), 7, '0', STR_PAD_LEFT),
                        'nomor_telepon' => '0812'.rand(1000, 9999).str_pad((string) ((int) $kodeProdiStr * 10 + $i), 4, '0', STR_PAD_LEFT),
                        'email_pribadi' => $personalEmail,
                        'email_students' => $nim.'@students.ukdw.ac.id',
                        'alamat_saat_ini' => 'Jl. Solo Km. '.($i + 4).' No. '.($i * 7 + 10).', Caturtunggal',
                        'kelurahan' => 'Caturtunggal',
                        'kecamatan' => 'Depok',
                        'kabupaten_id' => $kabSleman?->id,
                        'propinsi_id' => $propinsiDiy?->id,
                        'kode_pos' => '55281',
                        'asal_sekolah' => 'SMA Negeri '.(($i % 5) + 1).' Yogyakarta',
                        'alamat_asal_sekolah' => 'Jl. Kebangsaan No. 10',
                        'kota_kabupaten_asal_sekolah' => 'Kota Yogyakarta',
                        'provinsi_asal_sekolah' => 'DI Yogyakarta',
                        'jurusan_asal_sekolah' => ($kodeProdiStr === '11' || $kodeProdiStr === '12' ? 'IPS' : 'IPA'),
                    ]
                );

                // C. Buat Catatan Yudisium Kelulusan (Target Tracer Study Resmi)
                $judulTA = $bankJudulTA[$kodeProdi] ?? 'Analisis dan Implementasi Sistem Terintegrasi di Lingkungan Profesional';
                $yudisium = Yudisium::updateOrCreate(
                    ['nim' => $nim],
                    [
                        'judul_ta' => $judulTA,
                        'judul_ta_inggris' => 'Design and Analysis of Integrated System in Professional Environment',
                        'url_publikasi' => 'https://repository.ukdw.ac.id/handle/123456789/'.$nim,
                        'jenis_publikasi' => ($i % 2 === 0) ? 'Jurnal Nasional Terakreditasi' : 'Prosiding Seminar Internasional',
                        'status_publikasi' => 'Terbit',
                        'keterangan_hasil_yudisium' => ($ipk >= 3.75 ? 'Lulus dengan Pujian (Cumlaude)' : 'Sangat Memuaskan'),
                        'status_lulus' => 'Lulus', // WAJIB Lulus agar terdeteksi sebagai target tracer
                    ]
                );

                // E. Bangun Profil Biodata & Variasi Status Responden
                $cleanUsername = strtolower(str_replace(' ', '', explode(' ', $nama)[0])).$nim;

                // =========================================================================
                // SKENARIO 1: LENGKAP 100% (Status: Selesai) - 4 Kategori Karier Berbeda
                // =========================================================================
                if ($i < 4) {
                    $this->seedAlumniLengkap(
                        $i,
                        $user,
                        $nim,
                        $nama,
                        $prodi,
                        $dataAkademik,
                        $yudisium,
                        $cfg,
                        $perusahaans,
                        $propinsiDiy,
                        $kabSleman,
                        $propinsiJkt,
                        $kabJaksel,
                        $cleanUsername,
                        $univQuestions,
                        $prodiQuestions
                    );
                }
                // =========================================================================
                // SKENARIO 2: SEDANG MENGISI (Status: Belum Selesai, Progress 40% - 75%)
                // =========================================================================
                elseif ($i >= 4 && $i <= 6) {
                    $this->seedAlumniSedangMengisi(
                        $i,
                        $user,
                        $nim,
                        $nama,
                        $prodi,
                        $dataAkademik,
                        $yudisium,
                        $cfg,
                        $perusahaans,
                        $propinsiDiy,
                        $kabSleman,
                        $cleanUsername,
                        $univQuestions
                    );
                }
                // =========================================================================
                // SKENARIO 3: BELUM MENGISI (Status: Belum Selesai, 0% Pengisian Kuesioner)
                // =========================================================================
                else {
                    $this->seedAlumniBelumMengisi(
                        $user,
                        $nim,
                        $nama,
                        $prodi,
                        $dataAkademik,
                        $yudisium,
                        $cfg
                    );
                }
            }
        }
    }

    /**
     * Seeder untuk Alumni Lengkap 100% (Status Selesai)
     * Mengimplementasikan 4 Kategori Berbeda:
     * - Kategori 1 (Index 0): Bekerja Swasta / BUMN (Bekerja sebelum lulus / F504 Ya)
     * - Kategori 2 (Index 1): Wiraswasta / Pendiri Usaha Sendiri
     * - Kategori 3 (Index 2): Melanjutkan Pendidikan S2 (Studi Lanjut)
     * - Kategori 4 (Index 3): Bekerja Startup / Multinasional (Mencari setelah lulus / F504 Tidak)
     */
    protected function seedAlumniLengkap(
        int $kategoriIdx,
        User $user,
        string $nim,
        string $nama,
        Prodi $prodi,
        DataAkademik $dataAkademik,
        Yudisium $yudisium,
        array $cfg,
        $perusahaans,
        $propinsiDiy,
        $kabSleman,
        $propinsiJkt,
        $kabJaksel,
        string $cleanUsername,
        $univQuestions,
        $prodiQuestions
    ): void {
        // Data Perusahaan & Atasan Berdasarkan Kategori
        $perusahaanId = null;
        $atasanId = null;
        $kategoriPekerjaan = 'Bekerja (Full Time)';
        $posisiJabatan = 'Professional Staff';
        $posisiWiraswasta = null;
        $pendidikanTingkat = null;
        $perguruanTinggi = null;
        $pendidikanProdi = null;
        $gaji = 8500000;

        if ($kategoriIdx === 0) {
            // Kategori 1: Bekerja Swasta / BUMN
            $kategoriPekerjaan = 'Bekerja (Full Time)';
            $posisiJabatan = ($prodi->kode_prodi === '71' || $prodi->kode_prodi === '72') ? 'Software Engineer' :
                (($prodi->kode_prodi === '11' || $prodi->kode_prodi === '12') ? 'Senior Financial Analyst' :
                (($prodi->kode_prodi === '21') ? 'Junior Architect' : 'Professional Specialist'));
            $gaji = 9500000;

            $perusahaan = Perusahaan::firstOrCreate(
                ['nama_perusahaan' => 'PT Bank Mandiri (Persero) Tbk'],
                [
                    'propinsi_id' => $propinsiJkt?->id,
                    'kabupaten_id' => $kabJaksel?->id,
                    'alamat' => 'Jl. Gatot Subroto Kav. 36-38, Senayan',
                    'sektor' => 'Perbankan & Keuangan',
                    'skala' => 'Nasional',
                    'jenis_perusahaan' => 'BUMN/BUMD',
                    'jenis_lokasi' => 'Dalam Negeri',
                    'negara' => 'Indonesia',
                    'status_verifikasi' => 'Terverifikasi',
                ]
            );
            $perusahaanId = $perusahaan->id;

            $atasan = Atasan::firstOrCreate(
                ['email' => 'atasan.mandiri.'.$nim.'@mandiri.co.id'],
                [
                    'nama' => 'Hartono Kusumo, S.E., MBA.',
                    'telepon' => '081223344001',
                ]
            );
            $atasanId = $atasan->id;
        } elseif ($kategoriIdx === 1) {
            // Kategori 2: Wiraswasta / Founder
            $kategoriPekerjaan = 'Wiraswasta';
            $posisiWiraswasta = 'Founder / Owner';
            $posisiJabatan = 'Founder & Chief Executive Officer';
            $gaji = 15000000;

            $perusahaan = Perusahaan::firstOrCreate(
                ['nama_perusahaan' => 'CV '.explode(' ', $nama)[0].' Inovasi Mandiri'],
                [
                    'propinsi_id' => $propinsiDiy?->id,
                    'kabupaten_id' => $kabSleman?->id,
                    'alamat' => 'Jl. Kaliurang Km. 5, Pogung Kidul',
                    'sektor' => 'Industri Kreatif & Digital Agency',
                    'skala' => 'Nasional',
                    'jenis_perusahaan' => 'Wiraswasta',
                    'jenis_lokasi' => 'Dalam Negeri',
                    'negara' => 'Indonesia',
                    'status_verifikasi' => 'Terverifikasi',
                ]
            );
            $perusahaanId = $perusahaan->id;

            $atasan = Atasan::firstOrCreate(
                ['email' => 'partner.'.$nim.'@inovasimandiri.id'],
                [
                    'nama' => 'Rendra Pratama, S.Kom. (Co-Founder)',
                    'telepon' => '081223344002',
                ]
            );
            $atasanId = $atasan->id;
        } elseif ($kategoriIdx === 2) {
            // Kategori 3: Melanjutkan Pendidikan S2
            $kategoriPekerjaan = 'Melanjutkan Pendidikan';
            $pendidikanTingkat = 'S2';
            $perguruanTinggi = 'Universitas Gadjah Mada';
            $pendidikanProdi = 'Magister '.$prodi->nama_prodi;
            $posisiJabatan = 'Mahasiswa Pascasarjana (Awardee Beasiswa LPDP)';
            $gaji = 5000000; // Tunjangan hidup bulanan beasiswa

            $perusahaan = Perusahaan::firstOrCreate(
                ['nama_perusahaan' => 'Sekolah Pascasarjana Universitas Gadjah Mada'],
                [
                    'propinsi_id' => $propinsiDiy?->id,
                    'kabupaten_id' => $kabSleman?->id,
                    'alamat' => 'Jl. Teknika Utara, Pogung',
                    'sektor' => 'Pendidikan Tinggi & Riset',
                    'skala' => 'Internasional',
                    'jenis_perusahaan' => 'Instansi pemerintah',
                    'jenis_lokasi' => 'Dalam Negeri',
                    'negara' => 'Indonesia',
                    'status_verifikasi' => 'Terverifikasi',
                ]
            );
            $perusahaanId = $perusahaan->id;

            $atasan = Atasan::firstOrCreate(
                ['email' => 'kaprodi.s2.'.$nim.'@mail.ugm.ac.id'],
                [
                    'nama' => 'Prof. Dr. Ir. Wahyu Wibowo, M.Sc. (Dosen Pembimbing S2)',
                    'telepon' => '081223344003',
                ]
            );
            $atasanId = $atasan->id;
        } else {
            // Kategori 4: Bekerja di Startup / Tech Multinasional (F504 = Tidak)
            $kategoriPekerjaan = 'Bekerja (Full Time)';
            $posisiJabatan = ($prodi->kode_prodi === '71' || $prodi->kode_prodi === '72') ? 'Product Data Analyst' : 'Business Development Specialist';
            $gaji = 11000000;

            $perusahaan = Perusahaan::firstOrCreate(
                ['nama_perusahaan' => 'PT GoTo Gojek Tokopedia Tbk'],
                [
                    'propinsi_id' => $propinsiJkt?->id,
                    'kabupaten_id' => $kabJaksel?->id,
                    'alamat' => 'Pasaraya Blok M Gedung B Lt. 6, Kebayoran Baru',
                    'sektor' => 'Teknologi Informasi & E-Commerce',
                    'skala' => 'Internasional',
                    'jenis_perusahaan' => 'Perusahaan swasta',
                    'jenis_lokasi' => 'Dalam Negeri',
                    'negara' => 'Indonesia',
                    'status_verifikasi' => 'Terverifikasi',
                ]
            );
            $perusahaanId = $perusahaan->id;

            $atasan = Atasan::firstOrCreate(
                ['email' => 'lead.goto.'.$nim.'@gotocompany.com'],
                [
                    'nama' => 'Maya Indrawati, S.T., M.Kom. (Engineering Manager)',
                    'telepon' => '081223344004',
                ]
            );
            $atasanId = $atasan->id;
        }

        // Simpan Record Biodata Lengkap (100% Profil)
        $biodata = Biodata::updateOrCreate(
            ['user_id' => $user->id],
            [
                'nim' => $nim,
                'yudisium_id' => $yudisium->id,
                'prodi_id' => $prodi->id,
                'email_pribadi' => $dataAkademik->email_pribadi,
                'nomor_telepon' => $dataAkademik->nomor_telepon,
                'alamat_saat_ini' => $dataAkademik->alamat_saat_ini,
                'kabupaten_id' => $dataAkademik->kabupaten_id,
                'propinsi_id' => $dataAkademik->propinsi_id,
                'kode_pos' => $dataAkademik->kode_pos,
                'instagram_url' => 'https://instagram.com/'.$cleanUsername,
                'facebook_url' => 'https://facebook.com/'.$cleanUsername,
                'linkedin_url' => 'https://linkedin.com/in/'.$cleanUsername,
                'linkedin_username' => $cleanUsername,
                'skills' => 'Professional Expertise in '.$prodi->nama_prodi,
                'experience' => 'Innovation, Research, and Community Development',
                'perusahaan_id' => $perusahaanId,
                'atasan_id' => $atasanId,
                'kategori_pekerjaan' => $kategoriPekerjaan,
                'posisi_jabatan' => $posisiJabatan,
                'posisi_wiraswasta' => $posisiWiraswasta,
                'pendidikan_tingkat' => $pendidikanTingkat,
                'perguruan_tinggi' => $perguruanTinggi,
                'pendidikan_prodi' => $pendidikanProdi,
                'gaji' => $gaji,
                'jenis_pekerjaan' => ($prodi->kode_prodi === '31' ? 'Gerejawi' : null),
            ]
        );

        // Jangan reset kuesioner Joshua Andrean jika sudah memiliki jawaban
        if ($nim === '72220001' && Tracer::where('biodata_id', $biodata->id)->count() > 0) {
            return;
        }

        // Isi Jawaban Kuesioner Universitas Lengkap (100% Wajib Terpenuhi)
        $this->seedTracerAnswersLengkap($biodata, $kategoriIdx, $cfg['tahun'], $univQuestions);

        // Isi Jawaban Kuesioner Khusus Prodi (Jika Prodi Memiliki Instrumen Soal)
        $this->seedProdiAnswersLengkap($biodata, $prodiQuestions);
    }

    /**
     * Mengisi Butir Kuesioner Universitas 100% Sesuai Kategori
     */
    protected function seedTracerAnswersLengkap(Biodata $biodata, int $kategoriIdx, int $tahunLulus, $univQuestions): void
    {
        // Bersihkan jawaban tracer sebelumnya untuk alumni ini
        Tracer::where('biodata_id', $biodata->id)->delete();

        $answers = [];

        // 1. Status Pekerjaan F8 (Standar Dikti)
        if ($kategoriIdx === 0) {
            $answers['F8'] = 'Bekerja (full time/part time)';
        } elseif ($kategoriIdx === 1) {
            $answers['F8'] = 'Wiraswasta';
        } elseif ($kategoriIdx === 2) {
            $answers['F8'] = 'Melanjutkan Pendidikan';
        } else {
            $answers['F8'] = 'Bekerja (full time/part time)';
        }

        // 3. Butir Kompetensi Wajib (F17a1 s/d F17b7) - Nilai skala 1 s/d 5
        $kompetensiCodes = [
            'F17a1', 'F17b1', 'F17a2', 'F17b2', 'F17a3', 'F17b3',
            'F17a4', 'F17b4', 'F17a5', 'F17b5', 'F17a6', 'F17b6',
            'F17a7', 'F17b7',
        ];
        foreach ($kompetensiCodes as $c) {
            $answers[$c] = '4'; // 4 = Setuju / Tinggi
        }

        // 4. Jalur Non-Pendidikan (Bekerja / Wiraswasta: Kategori 0, 1, 3)
        if ($kategoriIdx !== 2) {
            $answers['F14'] = 'Sangat Erat';
            $answers['F15'] = 'Tingkat yang Sama';

            if ($kategoriIdx === 0 || $kategoriIdx === 1) {
                // Mendapatkan kerja sebelum lulus
                $answers['F504'] = 'Ya';
                $answers['F502'] = '3'; // 3 bulan sebelum lulus
                $answers['F3'] = 'Sebelum lulus ... bulan: 4';
            } else {
                // Mendapatkan kerja setelah lulus (Kategori 3 / Index 3)
                $answers['F504'] = 'Tidak';
                $answers['F506'] = '2'; // 2 bulan setelah lulus
                $answers['F3'] = 'Setelah lulus ... bulan: 2';
            }

            $answers['F4'] = 'Mencari lewat internet/iklan online/milis';
            $answers['F6'] = '4';
            $answers['F7'] = '3';
            $answers['F7A'] = '2';
            $answers['F505'] = '8500000';
        } else {
            // Jalur Melanjutkan Pendidikan (Kategori 2 / Index 2)
            $answers['F12'] = 'Beasiswa Pemerintah';
            $answers['F18b'] = 'Universitas Gadjah Mada';
            $answers['F18c'] = 'Program Magister Pascasarjana';
            $answers['F18d'] = '2025-09-01';
        }

        // Simpan seluruh record ke tabel tracer
        foreach ($answers as $kode => $ans) {
            $q = $univQuestions->get($kode);
            if ($q) {
                Tracer::create([
                    'biodata_id' => $biodata->id,
                    'question_id' => $q->id,
                    'nim' => $biodata->nim,
                    'kelompok' => $q->kelompok,
                    'kode_pertanyaan' => $q->kode_pertanyaan,
                    'subpertanyaan' => $q->subpertanyaan,
                    'answer' => $ans,
                    'answer_json' => null,
                    'keterangan' => null,
                    'tahun_lulus' => (string) $tahunLulus,
                ]);
            }
        }
    }

    /**
     * Mengisi Kuesioner Khusus Prodi Lengkap 100%
     */
    protected function seedProdiAnswersLengkap(Biodata $biodata, $prodiQuestions): void
    {
        if ($prodiQuestions->isEmpty()) {
            return;
        }

        ProdiResponse::where('biodata_id', $biodata->id)->delete();

        foreach ($prodiQuestions as $pq) {
            $ans = '4'; // Default skala likert 4 = Setuju
            if ($pq->type === 'text' || $pq->type === 'textarea') {
                $ans = 'Program studi memberikan bekal keahlian yang relevan dan aplikatif di dunia profesional.';
            } elseif ($pq->type === 'radio' || $pq->type === 'dropdown') {
                $ans = 'Sangat Setuju';
            }

            ProdiResponse::create([
                'biodata_id' => $biodata->id,
                'prodi_question_id' => $pq->id,
                'answer_text' => $ans,
                'answer_json' => null,
            ]);
        }
    }

    /**
     * Seeder untuk Alumni Status: Sedang Mengisi (Progress 40% - 75%, Status Belum Selesai)
     */
    protected function seedAlumniSedangMengisi(
        int $idx,
        User $user,
        string $nim,
        string $nama,
        Prodi $prodi,
        DataAkademik $dataAkademik,
        Yudisium $yudisium,
        array $cfg,
        $perusahaans,
        $propinsiDiy,
        $kabSleman,
        string $cleanUsername,
        $univQuestions
    ): void {
        // Ambil salah satu perusahaan terdaftar
        $perusahaan = $perusahaans->first();

        $biodata = Biodata::updateOrCreate(
            ['user_id' => $user->id],
            [
                'nim' => $nim,
                'yudisium_id' => $yudisium->id,
                'prodi_id' => $prodi->id,
                'email_pribadi' => $dataAkademik->email_pribadi,
                'nomor_telepon' => $dataAkademik->nomor_telepon,
                'alamat_saat_ini' => $dataAkademik->alamat_saat_ini,
                'kabupaten_id' => $kabSleman?->id,
                'propinsi_id' => $propinsiDiy?->id,
                'kode_pos' => '55281',
                'instagram_url' => 'https://instagram.com/'.$cleanUsername,
                'facebook_url' => null,
                'linkedin_url' => 'https://linkedin.com/in/'.$cleanUsername,
                'linkedin_username' => $cleanUsername,
                'skills' => 'Bidang Peminatan '.$prodi->nama_prodi,
                'experience' => 'Karier Profesional',
                'perusahaan_id' => $perusahaan?->id,
                'atasan_id' => null,
                'kategori_pekerjaan' => 'Bekerja (Full Time)',
                'posisi_jabatan' => 'Junior Associate',
                'gaji' => 6500000,
            ]
        );

        Tracer::where('biodata_id', $biodata->id)->delete();
        ProdiResponse::where('biodata_id', $biodata->id)->delete();

        // Hanya jawab sebagian kuesioner wajib (misal 8 dari 20 butir = 40% - 50%)
        $partialAnswers = [
            'F8' => 'Bekerja (full time/part time)',
            'F504' => 'Ya',
            'F502' => '2',
            'F12' => 'Biaya Sendiri / Keluarga',
            'F14' => 'Erat',
            'F17a1' => '4',
            'F17b1' => '4',
            'F17a2' => '4',
        ];

        foreach ($partialAnswers as $kode => $ans) {
            $q = $univQuestions->get($kode);
            if ($q) {
                Tracer::create([
                    'biodata_id' => $biodata->id,
                    'question_id' => $q->id,
                    'nim' => $biodata->nim,
                    'kelompok' => $q->kelompok,
                    'kode_pertanyaan' => $q->kode_pertanyaan,
                    'subpertanyaan' => $q->subpertanyaan,
                    'answer' => $ans,
                    'answer_json' => null,
                    'keterangan' => null,
                    'tahun_lulus' => (string) $cfg['tahun'],
                ]);
            }
        }
    }

    /**
     * Seeder untuk Alumni Status: Belum Mengisi (Hanya memiliki data induk akademik & yudisium)
     */
    protected function seedAlumniBelumMengisi(
        User $user,
        string $nim,
        string $nama,
        Prodi $prodi,
        DataAkademik $dataAkademik,
        Yudisium $yudisium,
        array $cfg
    ): void {
        // Record Biodata dasar (tanpa data mandiri / karier)
        $biodata = Biodata::updateOrCreate(
            ['user_id' => $user->id],
            [
                'nim' => $nim,
                'yudisium_id' => $yudisium->id,
                'prodi_id' => $prodi->id,
                'nomor_telepon' => null,
                'email_pribadi' => null,
                'alamat_saat_ini' => null,
                'kabupaten_id' => null,
                'propinsi_id' => null,
                'kode_pos' => null,
                'instagram_url' => null,
                'facebook_url' => null,
                'linkedin_url' => null,
                'linkedin_username' => null,
                'skills' => null,
                'experience' => null,
                'perusahaan_id' => null,
                'atasan_id' => null,
                'kategori_pekerjaan' => null,
                'posisi_jabatan' => null,
                'posisi_wiraswasta' => null,
                'pendidikan_tingkat' => null,
                'perguruan_tinggi' => null,
                'pendidikan_prodi' => null,
                'gaji' => null,
                'jenis_pekerjaan' => null,
            ]
        );

        // Pastikan tidak ada jawaban kuesioner tersimpan (0% Progress)
        Tracer::where('biodata_id', $biodata->id)->delete();
        ProdiResponse::where('biodata_id', $biodata->id)->delete();
    }
}
