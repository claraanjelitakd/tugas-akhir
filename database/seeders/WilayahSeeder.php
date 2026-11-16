<?php

namespace Database\Seeders;

use App\Models\Kabupaten;
use App\Models\Propinsi;
use Illuminate\Database\Seeder;

/**
 * Seeder Wilayah
 *
 * Mengimpor data referensi Master Propinsi dan Kabupaten/Kota dari file CSV.
 */
class WilayahSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Impor Data Propinsi
        $provinsiCsvFile = base_path('data/provinsi.csv');
        if (! file_exists($provinsiCsvFile)) {
            $provinsiCsvFile = database_path('data/provinsi.csv');
        }
        if (! file_exists($provinsiCsvFile)) {
            $provinsiCsvFile = base_path('provinsi.csv');
        }

        if (file_exists($provinsiCsvFile)) {
            $provinsiData = array_map('str_getcsv', file($provinsiCsvFile));
            // Hapus header
            $headerProvinsi = array_shift($provinsiData);

            $this->command->info('Mulai memasukkan data propinsi...');
            $provInserted = 0;
            foreach ($provinsiData as $row) {
                if (count($row) >= 2) {
                    Propinsi::updateOrCreate(
                        ['kode_provinsi' => $row[0]],
                        ['nama_provinsi' => $row[1]]
                    );
                    $provInserted++;
                }
            }
            $this->command->info("Selesai! $provInserted propinsi diproses.");
        } else {
            $this->command->error('File provinsi.csv tidak ditemukan di direktori /data!');
        }

        // 2. Load semua propinsi ke memory agar tidak query berulang-ulang
        $propinsis = Propinsi::whereNotNull('kode_provinsi')->get()->keyBy('kode_provinsi');
        $this->command->info('Berhasil memuat '.$propinsis->count().' propinsi dari database.');

        // 3. Impor Data Kabupaten/Kota
        $kabupatenCsvFile = base_path('data/kabupaten_kota.csv');
        if (! file_exists($kabupatenCsvFile)) {
            $kabupatenCsvFile = database_path('data/kabupaten_kota.csv');
        }
        if (! file_exists($kabupatenCsvFile)) {
            $kabupatenCsvFile = base_path('kabupaten_kota.csv');
        }
        if (file_exists($kabupatenCsvFile)) {
            $kabupatenData = array_map('str_getcsv', file($kabupatenCsvFile));
            // Hapus header
            $headerKabupaten = array_shift($kabupatenData);

            $this->command->info('Mulai memasukkan '.count($kabupatenData).' data kabupaten/kota...');

            $inserted = 0;
            $updated = 0;

            // Kita proses satu per satu agar updateOrCreate berjalan aman tanpa duplicate
            foreach ($kabupatenData as $row) {
                if (count($row) >= 2) {
                    $kodeKabupaten = $row[0]; // contoh: "11.01"
                    $namaKabupaten = $row[1]; // contoh: "Aceh Selatan"

                    // Ekstrak kode provinsi
                    $kodeProvinsiArr = explode('.', $kodeKabupaten);
                    $kodeProvinsi = $kodeProvinsiArr[0]; // contoh: "11"

                    // Ambil dari collection yang sudah di-load di memory
                    $propinsi = $propinsis->get($kodeProvinsi);

                    if ($propinsi) {
                        $kab = Kabupaten::where('kode_kabupaten', $kodeKabupaten)->first();
                        if (! $kab) {
                            Kabupaten::create([
                                'kode_kabupaten' => $kodeKabupaten,
                                'kode_provinsi' => $propinsi->kode_provinsi,
                                'nama_kabupaten' => $namaKabupaten,
                            ]);
                            $inserted++;
                        } else {
                            $kab->update([
                                'kode_provinsi' => $propinsi->kode_provinsi,
                                'nama_kabupaten' => $namaKabupaten,
                            ]);
                            $updated++;
                        }
                    }
                }
            }

            $this->command->info("Selesai! $inserted kabupaten baru ditambahkan, $updated kabupaten diperbarui.");
            $total = Kabupaten::count();
            $this->command->info("Total keseluruhan kabupaten di database sekarang: $total");

        } else {
            $this->command->error('File kabupaten_kota.csv tidak ditemukan di direktori root!');
        }
    }
}
