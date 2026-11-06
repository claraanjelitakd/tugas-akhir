<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeder Master Kuesioner Terpadu (Unified Survey Engine).
 *
 * Mengisi seluruh master instrumen survei kuesioner ke dalam skema terpadu:
 * - kuesioner_section
 * - kuesioner_pertanyaan
 * - kuesioner_section_pertanyaan
 * - kuesioner_opsi
 *
 * Sumber data master diambil dari berkas fixture kanonik terpadu:
 * database/seeders/data/unified_kuesioner_instruments.json
 */
class UnifiedKuesionerSeeder extends Seeder
{
    /**
     * Jalankan proses seeding instrumen kuesioner terpadu.
     */
    public function run(): void
    {
        $this->command?->info('Memulai seeding master instrumen Unified Survey Engine...');

        $fixturePath = database_path('seeders/data/unified_kuesioner_instruments.json');
        if (file_exists($fixturePath)) {
            $this->seedFromFixture($fixturePath);
        } else {
            $this->command?->error("Berkas fixture instrumen {$fixturePath} tidak ditemukan.");
        }

        $this->command?->info('Seeding master instrumen Unified Survey Engine selesai!');
    }

    /**
     * Memulihkan seluruh instrumen kuesioner kanonik dari berkas JSON fixture.
     */
    private function seedFromFixture(string $path): void
    {
        $content = json_decode((string) file_get_contents($path), true);
        if (! is_array($content)) {
            return;
        }

        Schema::disableForeignKeyConstraints();

        try {
            if (! empty($content['sections'])) {
                foreach ($content['sections'] as $s) {
                    if (! empty($s['prodi_id']) && ! DB::table('prodi')->where('id', $s['prodi_id'])->exists()) {
                        DB::table('prodi')->insertOrIgnore([
                            'id' => $s['prodi_id'],
                            'kode_prodi' => 'PR'.$s['prodi_id'],
                            'nama_prodi' => 'Program Studi '.$s['prodi_id'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    DB::table('kuesioner_section')->updateOrInsert(
                        ['id' => $s['id']],
                        $s
                    );
                }
            }

            if (! empty($content['pertanyaan'])) {
                foreach ($content['pertanyaan'] as $p) {
                    if (! empty($p['pemilik_prodi_id']) && ! DB::table('prodi')->where('id', $p['pemilik_prodi_id'])->exists()) {
                        DB::table('prodi')->insertOrIgnore([
                            'id' => $p['pemilik_prodi_id'],
                            'kode_prodi' => 'PR'.$p['pemilik_prodi_id'],
                            'nama_prodi' => 'Program Studi '.$p['pemilik_prodi_id'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    DB::table('kuesioner_pertanyaan')->updateOrInsert(
                        ['id' => $p['id']],
                        $p
                    );
                }
            }

            if (! empty($content['section_pertanyaan'])) {
                $this->removeDuplicateAtasanPlacements($content['section_pertanyaan']);

                foreach ($content['section_pertanyaan'] as $sp) {
                    DB::table('kuesioner_section_pertanyaan')->updateOrInsert(
                        ['id' => $sp['id']],
                        $sp
                    );
                }
            }

            if (! empty($content['opsi'])) {
                foreach ($content['opsi'] as $o) {
                    DB::table('kuesioner_opsi')->updateOrInsert(
                        ['id' => $o['id']],
                        $o
                    );
                }
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    /**
     * Menghapus hanya penempatan lama yang membuat field karakteristik dan penilaian
     * tampil ganda pada section profil atasan. Master pertanyaan dan respons tidak disentuh.
     *
     * @param  array<int, array<string, mixed>>  $placements
     */
    private function removeDuplicateAtasanPlacements(array $placements): void
    {
        $canonicalAtasanQuestionIds = collect($placements)
            ->whereIn('section_id', [20, 21])
            ->pluck('pertanyaan_id');

        $duplicateQuestionIds = $canonicalAtasanQuestionIds->unique()->values();

        if ($duplicateQuestionIds->isEmpty()) {
            return;
        }

        DB::table('kuesioner_section_pertanyaan')
            ->where('section_id', 19)
            ->whereIn('pertanyaan_id', $duplicateQuestionIds->all())
            ->delete();
    }
}
