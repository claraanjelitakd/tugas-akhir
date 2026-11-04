<?php

namespace Database\Seeders;

use App\Models\Prodi;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder Utama Database
 *
 * Menjalankan seluruh seeder master data, kuesioner, dan data awal aplikasi.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminPassword = Hash::make('password123'); // Ganti dengan password yang lebih aman di production

        // 1. Seed Tabel Master Prodi
        $prodiList = [
            ['kode' => '71', 'nama' => 'Informatika'],
            ['kode' => '72', 'nama' => 'Sistem Informasi'],
            ['kode' => '11', 'nama' => 'Manajemen'],
            ['kode' => '12', 'nama' => 'Akuntansi'],
            ['kode' => '21', 'nama' => 'Arsitektur'],
            ['kode' => '22', 'nama' => 'Desain Produk'],
            ['kode' => '41', 'nama' => 'Biologi'],
            ['kode' => '42', 'nama' => 'Teknologi Pangan'],
            ['kode' => '61', 'nama' => 'Kedokteran'],
            ['kode' => '31', 'nama' => 'Filsafat Keilahian'],
            ['kode' => '81', 'nama' => 'Pendidikan Bahasa Inggris'],
            ['kode' => '82', 'nama' => 'Studi Humanitas'],
        ];

        foreach ($prodiList as $p) {
            Prodi::updateOrCreate(
                ['kode_prodi' => $p['kode']],
                ['nama_prodi' => $p['nama']]
            );
        }

        // 2. Seed Master Fakultas, Wilayah, Negara & Hubungkan ke Prodi
        $this->call([
            RefFakultasSeeder::class,
            RefNegaraSeeder::class,
            WilayahSeeder::class,
            UmpSeeder::class,
        ]);

        // 3. Seed Master Pengguna, Perusahaan, dan Data Alumni
        $this->call([
            UserSeeder::class,
            PerusahaanSeeder::class,
            DataAkademikSeeder::class,
            BiodataSeeder::class,
            YudisiumSeeder::class,
        ]);

        // 4. Seed Questionnaire Data (Kuesioner Induk & Unified Survey Engine)
        $this->call([
            KuesionerSeeder::class,
            UnifiedKuesionerSeeder::class,
        ]);

        // 5. Seed Data Khusus Pengujian Alumni (Joshua Andrean)
        $this->call([
            JoshuaAndreanSeeder::class,
        ]);

        // 6. Seed Data Simulasi Komprehensif Lintas 12 Program Studi
        $this->call([
            AlumniSimulationSeeder::class,
        ]);

        // 7. Seed Tambahan 10 Alumni Lulusan 2026 (Profil Karier Kosong / Skeleton)
        $this->call([
            Lulusan2026Seeder::class,
        ]);

        // 8. Skenario populasi dashboard lintas tahun dan program studi
        $this->call([
            DashboardScenarioSeeder::class,
        ]);

        // 9. Distribusi data demo khusus untuk menguji analitik lintas wilayah dan status karier
        $this->call([
            AnalyticsDemoDistributionSeeder::class,
        ]);

        // 10. Seed 8 Profil Alumni Unggulan Sistem Informasi FTI UKDW
        $this->call([
            AlumniSistemInformasiSeeder::class,
        ]);

        // 11. Preservasi data scraping LinkedIn (Staging Apify & Fixture Pengujian)
        $this->call([
            LinkedinSyncResultSeeder::class,
        ]);

        // 12. Konsolidasi seluruh instrumen survei ke Unified Survey Engine
        $this->call([
            UnifiedKuesionerSeeder::class,
        ]);
    }
}
