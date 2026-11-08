<?php

namespace Database\Seeders;

use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Menormalkan sumber populasi akademik untuk indikator IKU S1.
 *
 * Seeder ini memastikan seluruh rekam akademik mempunyai program studi dan
 * periode masuk semester ganjil. Ia juga menambahkan contoh mahasiswa yang
 * belum menjadi alumni agar denominator AEE tidak hanya berisi data lulus.
 */
class IkuS1AcademicPopulationSeeder extends Seeder
{
    /**
     * Menjalankan normalisasi data akademik dan populasi mahasiswa non-alumni.
     */
    public function run(): void
    {
        $this->normalizeExistingAcademicRows();
        $this->seedNonGraduatePopulation();
    }

    /**
     * Mengisi prodi serta periode masuk pada seluruh data akademik yang sudah ada.
     */
    private function normalizeExistingAcademicRows(): void
    {
        DataAkademik::query()->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $academic) {
                $prodiId = $academic->prodi_id
                    ?? User::query()->where('username', $academic->nim)->value('prodi_id')
                    ?? Biodata::query()->where('nim', $academic->nim)->value('prodi_id');

                $academic->forceFill([
                    'prodi_id' => $prodiId,
                    'tahun_akademik_masuk' => DataAkademik::formatAcademicEntryPeriod($academic->angkatan_masuk),
                ])->saveQuietly();
            }
        });
    }

    /**
     * Menambahkan mahasiswa yang aktif, cuti, DO, pindah, dan status akademik internal.
     */
    private function seedNonGraduatePopulation(): void
    {
        $prodiByCode = Prodi::query()->pluck('id', 'kode_prodi');
        $rows = [
            ['nim' => '90110001', 'nama' => 'Mahasiswa Aktif S1', 'kode_prodi' => '11', 'angkatan' => 2024, 'status' => 'Aktif'],
            ['nim' => '90110002', 'nama' => 'Mahasiswa Cuti Panjang S1', 'kode_prodi' => '11', 'angkatan' => 2021, 'status' => 'Cuti'],
            ['nim' => '90120001', 'nama' => 'Mahasiswa Drop Out S1', 'kode_prodi' => '12', 'angkatan' => 2020, 'status' => 'Drop Out'],
            ['nim' => '90210001', 'nama' => 'Mahasiswa Pindah S1', 'kode_prodi' => '21', 'angkatan' => 2019, 'status' => 'Pindah'],
            ['nim' => '90410001', 'nama' => 'Mahasiswa Administratif AR S1', 'kode_prodi' => '41', 'angkatan' => 2025, 'status' => 'AR'],
            ['nim' => '90420001', 'nama' => 'Mahasiswa Administratif AK S1', 'kode_prodi' => '42', 'angkatan' => 2024, 'status' => 'AK'],
        ];

        DB::transaction(function () use ($rows, $prodiByCode): void {
            foreach ($rows as $row) {
                $prodiId = $prodiByCode->get($row['kode_prodi']);
                if ($prodiId === null) {
                    continue;
                }

                DataAkademik::updateOrCreate(
                    ['nim' => $row['nim']],
                    [
                        'nama' => $row['nama'],
                        'prodi_id' => $prodiId,
                        'angkatan_masuk' => $row['angkatan'],
                        'tahun_akademik_masuk' => DataAkademik::formatAcademicEntryPeriod($row['angkatan']),
                        'status_mahasiswa' => $row['status'],
                        'tahun_akademik_lulus' => null,
                        'tahun_lulus' => null,
                        'tanggal_lulus' => null,
                    ],
                );
            }
        });
    }
}
