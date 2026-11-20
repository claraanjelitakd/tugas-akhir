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

        $prodiIdMap = [
            1 => '71',
            2 => '72',
            3 => '11',
            4 => '12',
            10 => '31',
        ];

        $prodiNames = [
            '11' => 'Manajemen',
            '12' => 'Akuntansi',
            '21' => 'Arsitektur',
            '22' => 'Desain Produk',
            '31' => 'Filsafat Keilahian',
            '41' => 'Biologi',
            '42' => 'Teknologi Pangan',
            '61' => 'Kedokteran',
            '71' => 'Informatika',
            '72' => 'Sistem Informasi',
            '81' => 'Pendidikan Bahasa Inggris',
            '82' => 'Studi Humanitas',
        ];

        try {
            if (! empty($content['sections'])) {
                $defaultKuesionerId = null;
                if (Schema::hasTable('kuesioner')) {
                    $defaultKuesionerId = DB::table('kuesioner')->where('is_active', true)->value('id')
                        ?? DB::table('kuesioner')->value('id');

                    if (! $defaultKuesionerId) {
                        $defaultKuesionerId = DB::table('kuesioner')->insertGetId([
                            'title' => 'Tracer Study UKDW 2021',
                            'description' => 'Kuesioner Pelacakan Jejak Alumni Universitas Kristen Duta Wacana (Standar Tracer Study 2021)',
                            'year' => 2021,
                            'is_active' => 1,
                            'target_response_rate' => 60.00,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                foreach ($content['sections'] as $s) {
                    if (array_key_exists('prodi_id', $s)) {
                        $rawId = $s['prodi_id'];
                        unset($s['prodi_id']);
                        $s['kode_prodi'] = $rawId !== null ? ($prodiIdMap[$rawId] ?? (string) $rawId) : null;
                    }

                    if (! array_key_exists('kuesioner_id', $s) || empty($s['kuesioner_id'])) {
                        $s['kuesioner_id'] = $defaultKuesionerId;
                    }

                    if (! empty($s['kode_prodi']) && ! DB::table('ref_prodi')->where('kode_prodi', $s['kode_prodi'])->exists()) {
                        DB::table('ref_prodi')->insertOrIgnore([
                            'kode_prodi' => $s['kode_prodi'],
                            'nama_prodi' => $prodiNames[$s['kode_prodi']] ?? ('Program Studi '.$s['kode_prodi']),
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
                    if (array_key_exists('pemilik_prodi_id', $p)) {
                        $rawId = $p['pemilik_prodi_id'];
                        unset($p['pemilik_prodi_id']);
                        $p['pemilik_kode_prodi'] = $rawId !== null ? ($prodiIdMap[$rawId] ?? (string) $rawId) : null;
                    }

                    if (! empty($p['pemilik_kode_prodi']) && ! DB::table('ref_prodi')->where('kode_prodi', $p['pemilik_kode_prodi'])->exists()) {
                        DB::table('ref_prodi')->insertOrIgnore([
                            'kode_prodi' => $p['pemilik_kode_prodi'],
                            'nama_prodi' => $prodiNames[$p['pemilik_kode_prodi']] ?? ('Program Studi '.$p['pemilik_kode_prodi']),
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
