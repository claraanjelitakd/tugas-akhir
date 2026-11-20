<?php

namespace Database\Seeders;

use App\Models\Prodi;
use App\Models\RefFakultas;
use Illuminate\Database\Seeder;

/**
 * Seeder RefFakultas
 *
 * Lokasi: database/seeders/RefFakultasSeeder.php
 * Fungsi:
 * 1. Mengisi data master seluruh Fakultas di lingkungan UKDW ke tabel ref_fakultas.
 * 2. Mengaitkan relasi fakultas_id pada setiap Program Studi berdasarkan digit pertama kode_prodi.
 */
class RefFakultasSeeder extends Seeder
{
    /**
     * Jalankan seeder fakultas dan pemetaan relasi prodi.
     */
    public function run(): void
    {
        // 1. Data 7 Fakultas Resmi UKDW
        $fakultasList = [
            ['kode' => '1', 'nama' => 'Fakultas Bisnis'],
            ['kode' => '2', 'nama' => 'Fakultas Arsitektur dan Desain'],
            ['kode' => '3', 'nama' => 'Fakultas Theologi'],
            ['kode' => '4', 'nama' => 'Fakultas Bioteknologi'],
            ['kode' => '6', 'nama' => 'Fakultas Kedokteran'],
            ['kode' => '7', 'nama' => 'Fakultas Teknologi Informasi'],
            ['kode' => '8', 'nama' => 'Fakultas Kependidikan dan Humaniora'],
        ];

        $fakultasModels = [];
        foreach ($fakultasList as $f) {
            $fakultas = RefFakultas::updateOrCreate(
                ['kode_fakultas' => $f['kode']],
                ['nama_fakultas' => $f['nama']]
            );
            $fakultasModels[$f['kode']] = $fakultas->kode_fakultas;
        }

        // 2. Hubungkan kode_fakultas ke seluruh record tabel ref_prodi
        $prodis = Prodi::all();
        foreach ($prodis as $prodi) {
            $kodeDepan = substr((string) $prodi->kode_prodi, 0, 1);
            if (isset($fakultasModels[$kodeDepan])) {
                $prodi->update([
                    'kode_fakultas' => $fakultasModels[$kodeDepan],
                ]);
            }
        }
    }
}
