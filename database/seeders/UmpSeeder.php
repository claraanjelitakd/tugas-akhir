<?php

namespace Database\Seeders;

use App\Models\Propinsi;
use App\Models\Ump;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Memuat UMP dari sumber CSV kanonik untuk tahun yang disediakan.
 *
 * Kode provinsi pada sumber resmi dapat berbeda dari kode master wilayah lama
 * yang sudah dipakai oleh foreign key aplikasi. Resolusi dilakukan berdasarkan
 * nama provinsi yang dinormalisasi agar relasi wilayah existing tetap valid.
 */
class UmpSeeder extends Seeder
{
    /**
     * Membaca CSV, memvalidasi angka, memetakan provinsi ke master, lalu
     * memperbarui data UMP secara atomik.
     *
     * @throws RuntimeException Jika struktur CSV, nilai numerik, atau master
     *                          provinsi tidak sesuai kontrak.
     */
    public function run(): void
    {
        $sourcePath = database_path('data/ump_2026.csv');
        $rows = $this->readSourceRows($sourcePath);
        $propinsiByName = Propinsi::query()->get()->keyBy(
            fn (Propinsi $propinsi): string => $this->normalizeProvinceName($propinsi->nama_provinsi)
        );
        $umpData = [];

        foreach ($rows as $rowNumber => $row) {
            $provinceName = $this->normalizeProvinceName($row['nama_provinsi']);
            $propinsi = $propinsiByName->get($provinceName);

            if (! $propinsi instanceof Propinsi) {
                throw new RuntimeException("Provinsi '{$row['nama_provinsi']}' pada baris {$rowNumber} tidak ditemukan di master propinsi.");
            }

            $umpData[] = [
                'kode_provinsi' => $propinsi->kode_provinsi,
                'tahun' => (int) $row['tahun'],
                'besaran' => (int) $row['besaran'],
                'catatan' => null,
            ];
        }

        $years = array_values(array_unique(array_column($umpData, 'tahun')));
        $provinceCodes = array_values(array_unique(array_column($umpData, 'kode_provinsi')));

        DB::transaction(function () use ($umpData, $years, $provinceCodes): void {
            Ump::query()
                ->whereIn('tahun', $years)
                ->whereNotIn('kode_provinsi', $provinceCodes)
                ->delete();

            foreach ($umpData as $data) {
                Ump::updateOrCreate(
                    [
                        'kode_provinsi' => $data['kode_provinsi'],
                        'tahun' => $data['tahun'],
                    ],
                    [
                        'besaran' => $data['besaran'],
                        'catatan' => $data['catatan'],
                    ]
                );
            }
        });

        $this->command?->info('Berhasil memuat '.count($umpData).' data UMP dari '.$sourcePath.'.');
    }

    /**
     * Membaca dan memvalidasi baris data dari CSV UMP.
     *
     * @return list<array{kode_provinsi_sumber: string, nama_provinsi: string, tahun: string, besaran: string}>
     */
    private function readSourceRows(string $sourcePath): array
    {
        if (! is_file($sourcePath)) {
            throw new RuntimeException("Sumber CSV UMP tidak ditemukan: {$sourcePath}");
        }

        $handle = fopen($sourcePath, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Sumber CSV UMP tidak dapat dibaca: {$sourcePath}");
        }

        try {
            $header = fgetcsv($handle);
            $expectedHeader = ['kode_provinsi_sumber', 'nama_provinsi', 'tahun', 'besaran'];

            if ($header !== $expectedHeader) {
                throw new RuntimeException('Header CSV UMP tidak sesuai kontrak: '.implode(',', $expectedHeader));
            }

            $rows = [];
            $rowNumber = 1;

            while (($columns = fgetcsv($handle)) !== false) {
                $rowNumber++;

                if ($columns === [null] || count($columns) === 0) {
                    continue;
                }

                if (count($columns) !== count($expectedHeader)) {
                    throw new RuntimeException("CSV UMP pada baris {$rowNumber} memiliki jumlah kolom tidak valid.");
                }

                [$sourceCode, $provinceName, $year, $amount] = array_map(
                    static fn (?string $value): string => trim((string) $value),
                    $columns
                );

                if (! preg_match('/^\d{2}$/', $sourceCode)) {
                    throw new RuntimeException("Kode provinsi sumber pada baris {$rowNumber} harus dua digit.");
                }

                if ($provinceName === '' || ! ctype_digit($year) || ! ctype_digit($amount) || (int) $year < 1 || (int) $amount < 1) {
                    throw new RuntimeException("Nama provinsi, tahun, dan besaran pada baris {$rowNumber} tidak valid.");
                }

                $rows[] = [
                    'kode_provinsi_sumber' => $sourceCode,
                    'nama_provinsi' => $provinceName,
                    'tahun' => $year,
                    'besaran' => $amount,
                ];
            }
        } finally {
            fclose($handle);
        }

        if ($rows === []) {
            throw new RuntimeException('CSV UMP tidak memiliki data.');
        }

        $uniqueSourceKeys = array_map(
            static fn (array $row): string => $row['kode_provinsi_sumber'].'-'.$row['tahun'],
            $rows
        );

        if (count($uniqueSourceKeys) !== count(array_unique($uniqueSourceKeys))) {
            throw new RuntimeException('CSV UMP memiliki kode provinsi dan tahun yang duplikat.');
        }

        return $rows;
    }

    /**
     * Menyamakan nama provinsi CSV dengan alias pada master wilayah aplikasi.
     */
    private function normalizeProvinceName(string $provinceName): string
    {
        $withoutAlias = preg_replace('/\s*\([^)]*\)/u', '', trim($provinceName)) ?? trim($provinceName);

        return mb_strtolower($withoutAlias);
    }
}
