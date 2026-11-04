<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeder Master Butir Instrumen Evaluasi Kepuasan Pengguna Lulusan (Atasan) UKDW
 *
 * Mengalihkan pembenihan instrumen evaluasi atasan ke UnifiedKuesionerSeeder
 * yang menggunakan skema tabel terpadu kuesioner_section, kuesioner_pertanyaan,
 * kuesioner_section_pertanyaan, dan kuesioner_opsi.
 */
class PertanyaanEvaluasiAtasanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(UnifiedKuesionerSeeder::class);
    }
}
