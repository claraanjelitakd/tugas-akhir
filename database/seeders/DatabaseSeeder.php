<?php

namespace Database\Seeders;

use App\Models\Prodi;
use Illuminate\Database\Seeder;

/**
 * Seeder Utama Database
 *
 * Menjalankan seluruh seeder master data, kuesioner, dan data awal aplikasi.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Menjalankan dataset default yang ringkas, koheren, dan cukup untuk
     * pengujian aplikasi, kuesioner, dashboard, IKU S1, serta LinkedIn.
     */
    public function run(): void
    {
        // 1. Seed master program studi dan referensi yang dipakai runtime.
        $prodiList = [
            ['kode' => '71', 'nama' => 'Informatika', 'jenjang' => 'S1'],
            ['kode' => '72', 'nama' => 'Sistem Informasi', 'jenjang' => 'S1'],
            ['kode' => '11', 'nama' => 'Manajemen', 'jenjang' => 'S1'],
            ['kode' => '12', 'nama' => 'Akuntansi', 'jenjang' => 'S1'],
            ['kode' => '21', 'nama' => 'Arsitektur', 'jenjang' => 'S1'],
            ['kode' => '22', 'nama' => 'Desain Produk', 'jenjang' => 'S1'],
            ['kode' => '41', 'nama' => 'Biologi', 'jenjang' => 'S1'],
            ['kode' => '42', 'nama' => 'Teknologi Pangan', 'jenjang' => 'S1'],
            ['kode' => '61', 'nama' => 'Kedokteran', 'jenjang' => 'S1'],
            ['kode' => '31', 'nama' => 'Filsafat Keilahian', 'jenjang' => 'S1'],
            ['kode' => '81', 'nama' => 'Pendidikan Bahasa Inggris', 'jenjang' => 'S1'],
            ['kode' => '82', 'nama' => 'Studi Humanitas', 'jenjang' => 'S1'],
        ];

        foreach ($prodiList as $p) {
            Prodi::updateOrCreate(
                ['kode_prodi' => $p['kode']],
                ['nama_prodi' => $p['nama'], 'jenjang' => $p['jenjang']]
            );
        }

        // 2. Seed master fakultas, wilayah, negara, dan UMP.
        $this->call([
            RefFakultasSeeder::class,
            RefNegaraSeeder::class,
            WilayahSeeder::class,
            UmpSeeder::class,
        ]);

        // 3. Seed akun administrasi dasar. Data perusahaan dan alumni dibuat
        //    oleh fixture simulasi kanonik agar tidak terjadi duplikasi.
        $this->call([
            UserSeeder::class,
        ]);

        // 4. Seed Questionnaire Data (Kuesioner Induk & Unified Survey Engine)
        $this->call([
            KuesionerSeeder::class,
            UnifiedKuesionerSeeder::class,
        ]);

        // 5. Seed satu akun pengujian pengisian mandiri.
        $this->call([
            JoshuaAndreanSeeder::class,
        ]);

        // 6. Seed satu kohort kanonik 2026 lintas 12 prodi dengan maksimal
        //    100 lulusan yang menghubungkan seluruh data induk dan turunannya.
        $this->call([
            AlumniSimulationSeeder::class,
            KuesionerAnswerConsistencySeeder::class,
        ]);

        // 7. Preservasi staging hasil scraping LinkedIn sesuai kontrak backup.
        $this->call([
            LinkedinSyncResultSeeder::class,
        ]);
    }
}
