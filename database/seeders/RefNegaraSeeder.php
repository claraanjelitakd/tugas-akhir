<?php

namespace Database\Seeders;

use App\Models\RefNegara;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Seeder Master Referensi Negara Seluruh Dunia
 *
 * Mengimpor 194 data negara dunia dari berkas CSV terpusat di `data/daftar_negara_dunia.csv`
 * ke tabel basis data `ref_negara`. Dilengkapi mekanisme:
 * - Pengecekan skema tabel untuk mencegah error saat proses migrasi bertahap.
 * - Resolusi jalur berkas CSV bertingkat (data/ -> database/data/ -> root).
 * - Stream reading via fgetcsv untuk efisiensi memori dan akurasi karakter enclosure/delimiter.
 * - Pencegahan konflik Integrity Constraint Violation pada kolom `kode_negara` dan `nama_negara`.
 */
class RefNegaraSeeder extends Seeder
{
    /**
     * Jalankan proses seeding master referensi negara.
     */
    public function run(): void
    {
        // 1. Verifikasi ketersediaan tabel pada basis data
        if (! Schema::hasTable('ref_negara')) {
            $this->command?->warn('Tabel ref_negara belum tersedia. Lewati seeding negara.');

            return;
        }

        // 2. Tentukan lokasi berkas CSV terpusat di folder /data/ dengan fallback
        $csvPaths = [
            base_path('data/daftar_negara_dunia.csv'),
            database_path('data/daftar_negara_dunia.csv'),
            base_path('daftar_negara_dunia.csv'),
        ];

        $csvPath = null;
        foreach ($csvPaths as $path) {
            if (File::exists($path)) {
                $csvPath = $path;
                break;
            }
        }

        if (! $csvPath) {
            $this->command?->warn('Berkas CSV daftar_negara_dunia.csv tidak ditemukan di direktori /data.');

            return;
        }

        // 3. Buka stream berkas CSV
        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            $this->command?->error('Gagal membuka berkas CSV: '.$csvPath);

            return;
        }

        // Lewati baris header
        fgetcsv($handle);

        while (($row = fgetcsv($handle)) !== false) {
            if (empty($row) || count($row) < 3) {
                continue;
            }

            $namaNegara = trim($row[0] ?? '');
            $ibuKota = trim($row[1] ?? '');
            $kodeNegara = strtoupper(substr(trim($row[2] ?? ''), 0, 2));
            $benua = isset($row[3]) ? trim($row[3]) : null;

            if (empty($namaNegara) || empty($kodeNegara)) {
                continue;
            }

            // Cari berdasarkan kode negara atau nama negara untuk mencegah konflik unique constraint.
            $negara = RefNegara::where('kode_negara', $kodeNegara)
                ->orWhere('nama_negara', $namaNegara)
                ->first();

            $attributes = [
                'nama_negara' => $namaNegara,
                'ibu_kota' => $ibuKota ?: null,
                'kode_negara' => $kodeNegara,
                'benua' => $benua ?: null,
            ];

            if ($negara) {
                $negara->update($attributes);
            } else {
                RefNegara::create($attributes);
            }
        }

        fclose($handle);
    }
}
