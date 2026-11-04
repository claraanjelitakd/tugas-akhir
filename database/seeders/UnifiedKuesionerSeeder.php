<?php

namespace Database\Seeders;

use App\Models\KelompokPertanyaan;
use App\Models\KuesionerOpsi;
use App\Models\KuesionerPertanyaan;
use App\Models\KuesionerSection;
use App\Models\KuesionerSectionPertanyaan;
use App\Models\PertanyaanEvaluasiAtasan;
use App\Models\ProdiQuestion;
use App\Models\ProdiQuestionOption;
use App\Models\ProdiQuestionSection;
use App\Models\RefSubpertanyaan2021;
use App\Models\RefSubpertanyaanDetil;
use App\Models\SectionEvaluasiAtasan;
use App\Models\Tracer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeder Migrasi Terpadu Kuesioner (Unified Survey Engine).
 *
 * Mengonsolidasikan seluruh data master instrumen dan respon kuesioner dari 3 silo lama:
 * 1. Kuesioner Universitas (kelompok_pertanyaan, ref_subpertanyaan2021, ref_subpertanyaan_detil, tracer)
 * 2. Kuesioner Program Studi (prodi_question_section, prodi_question, prodi_question_option, prodi_response)
 * 3. Kuesioner Evaluasi Atasan (section_evaluasi_atasan, pertanyaan_evaluasi_atasan, respon_evaluasi_atasan)
 *
 * ke dalam 5 tabel kanonik terpadu:
 * - kuesioner_section
 * - kuesioner_pertanyaan
 * - kuesioner_section_pertanyaan
 * - kuesioner_opsi
 * - kuesioner_jawaban
 */
class UnifiedKuesionerSeeder extends Seeder
{
    /**
     * Jalankan proses sinkronisasi dan migrasi data ke skema terpadu.
     */
    public function run(): void
    {
        $this->command?->info('Memulai konsolidasi data ke Unified Survey Engine...');

        // 0. SEED INSTRUMEN KANONIK DARI FIXTURE TERPADU
        $fixturePath = database_path('seeders/data/unified_kuesioner_instruments.json');
        if (file_exists($fixturePath)) {
            $this->seedFromFixture($fixturePath);
        }

        // 1. MIGRATION KUESIONER UNIVERSITAS (JIKA TABEL LEGACY ADA)
        $this->migrateUniversitas();

        // 2. MIGRATION KUESIONER PROGRAM STUDI (JIKA TABEL LEGACY ADA)
        $this->migrateProdi();

        // 3. MIGRATION EVALUASI ATASAN (JIKA TABEL LEGACY ADA)
        $this->migrateAtasan();

        // 4. MIGRATION RESPON JAWABAN (Tracer, Prodi Response, Respon Atasan)
        $this->migrateResponses();

        $this->command?->info('Konsolidasi data Unified Survey Engine selesai!');
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
     * Mengonsolidasikan kuesioner tingkat universitas (Kemendikbud Dikti).
     */
    private function migrateUniversitas(): void
    {
        if (! SchemaHasTable('kelompok_pertanyaan') || ! SchemaHasTable('ref_subpertanyaan2021')) {
            return;
        }

        $kelompoks = KelompokPertanyaan::all();
        foreach ($kelompoks as $kelompok) {
            $section = KuesionerSection::updateOrCreate(
                ['kode_section' => 'SEC_UNIV_'.($kelompok->kode_kelompok ?: $kelompok->id)],
                [
                    'target' => 'univ',
                    'prodi_id' => null,
                    'judul' => $kelompok->title ?: ('Bagian '.$kelompok->kode_kelompok),
                    'deskripsi' => null,
                    'order' => (int) ($kelompok->order ?: 1),
                    'is_active' => true,
                ]
            );

            $subpertanyaans = RefSubpertanyaan2021::where('kelompok_pertanyaan_id', $kelompok->id)
                ->orWhere('kelompok', $kelompok->kode_kelompok)
                ->get();

            foreach ($subpertanyaans as $sub) {
                $pertanyaan = KuesionerPertanyaan::updateOrCreate(
                    ['kode_pertanyaan' => $sub->kode_pertanyaan],
                    [
                        'teks_pertanyaan' => $sub->subpertanyaan,
                        'tipe' => $sub->type ?: 'text',
                        'pemilik_prodi_id' => null,
                        'is_reusable' => true,
                        'keterangan' => $sub->keterangan,
                        'is_active' => true,
                    ]
                );

                KuesionerSectionPertanyaan::updateOrCreate(
                    [
                        'section_id' => $section->id,
                        'pertanyaan_id' => $pertanyaan->id,
                    ],
                    [
                        'order' => (int) ($sub->order ?: 1),
                        'wajib' => (bool) $sub->wajib,
                        'tampil_di' => $sub->tampil_di,
                    ]
                );

                // Opsi pilihan jawaban dari ref_subpertanyaan_detil
                $detils = RefSubpertanyaanDetil::where('pertanyaan_id', $sub->id)
                    ->orWhere('kode_pertanyaan', $sub->kode_pertanyaan)
                    ->orderBy('id')
                    ->get();

                foreach ($detils as $idx => $detil) {
                    KuesionerOpsi::updateOrCreate(
                        [
                            'pertanyaan_id' => $pertanyaan->id,
                            'kode_opsi' => $detil->kode_opsi ?: ($sub->kode_pertanyaan.'_'.($idx + 1)),
                        ],
                        [
                            'label' => $detil->option_text,
                            'nilai_skor' => is_numeric($detil->kode_opsi) ? (int) $detil->kode_opsi : null,
                            'order' => (int) ($detil->order ?: ($idx + 1)),
                        ]
                    );
                }
            }
        }
    }

    /**
     * Mengonsolidasikan kuesioner program studi.
     */
    private function migrateProdi(): void
    {
        if (! SchemaHasTable('prodi_question_section') || ! SchemaHasTable('prodi_question')) {
            return;
        }

        $sections = ProdiQuestionSection::all();
        foreach ($sections as $sec) {
            $section = KuesionerSection::updateOrCreate(
                ['kode_section' => 'SEC_PRODI_'.$sec->prodi_id.'_'.$sec->id],
                [
                    'target' => 'prodi',
                    'prodi_id' => $sec->prodi_id,
                    'judul' => $sec->title,
                    'deskripsi' => $sec->description,
                    'order' => (int) ($sec->order ?: 1),
                    'is_active' => true,
                ]
            );

            $questions = ProdiQuestion::where('prodi_question_section_id', $sec->id)->get();
            foreach ($questions as $q) {
                $pertanyaan = KuesionerPertanyaan::updateOrCreate(
                    ['kode_pertanyaan' => $q->code],
                    [
                        'teks_pertanyaan' => $q->question_text,
                        'tipe' => $q->type ?: 'single_choice',
                        'pemilik_prodi_id' => $q->prodi_id,
                        'is_reusable' => true,
                        'is_active' => true,
                    ]
                );

                KuesionerSectionPertanyaan::updateOrCreate(
                    [
                        'section_id' => $section->id,
                        'pertanyaan_id' => $pertanyaan->id,
                    ],
                    [
                        'order' => (int) ($q->order ?: 1),
                        'wajib' => (bool) $q->is_required,
                    ]
                );

                $options = ProdiQuestionOption::where('prodi_question_id', $q->id)->orderBy('order')->get();
                foreach ($options as $opt) {
                    KuesionerOpsi::updateOrCreate(
                        [
                            'pertanyaan_id' => $pertanyaan->id,
                            'kode_opsi' => $q->code.'_'.$opt->order,
                        ],
                        [
                            'label' => $opt->option_text,
                            'nilai_skor' => $opt->score,
                            'order' => (int) ($opt->order ?: 1),
                        ]
                    );
                }
            }
        }
    }

    /**
     * Mengonsolidasikan kuesioner evaluasi atasan (pengguna lulusan).
     */
    private function migrateAtasan(): void
    {
        if (! SchemaHasTable('section_evaluasi_atasan') || ! SchemaHasTable('pertanyaan_evaluasi_atasan')) {
            return;
        }

        $sections = SectionEvaluasiAtasan::all();
        if ($sections->isEmpty()) {
            $sections = collect([
                (object) [
                    'id' => 1,
                    'title' => 'Evaluasi Pengguna Lulusan',
                    'description' => 'Penilaian kinerja dan kesiapan alumni di lingkungan kerja.',
                    'order' => 1,
                ],
            ]);
        }

        foreach ($sections as $sec) {
            $section = KuesionerSection::updateOrCreate(
                ['kode_section' => 'SEC_ATASAN_'.$sec->id],
                [
                    'target' => 'atasan',
                    'prodi_id' => null,
                    'judul' => $sec->title ?? 'Evaluasi Pengguna Lulusan',
                    'deskripsi' => $sec->description ?? null,
                    'order' => (int) ($sec->order ?: 1),
                    'is_active' => true,
                ]
            );

            $questions = PertanyaanEvaluasiAtasan::query()
                ->when(isset($sec->id) && $sec->id > 1, fn ($q) => $q->where('section_id', $sec->id))
                ->get();

            foreach ($questions as $q) {
                $pertanyaan = KuesionerPertanyaan::updateOrCreate(
                    ['kode_pertanyaan' => 'EVA_'.$q->kode],
                    [
                        'teks_pertanyaan' => $q->aspek,
                        'tipe' => $q->tipe ?: 'likert_5',
                        'pemilik_prodi_id' => null,
                        'is_reusable' => true,
                        'keterangan' => $q->deskripsi,
                        'is_active' => (bool) $q->is_active,
                    ]
                );

                KuesionerSectionPertanyaan::updateOrCreate(
                    [
                        'section_id' => $section->id,
                        'pertanyaan_id' => $pertanyaan->id,
                    ],
                    [
                        'order' => (int) ($q->order ?: 1),
                        'wajib' => true,
                    ]
                );

                if (is_array($q->pilihan_jawaban)) {
                    foreach ($q->pilihan_jawaban as $idx => $pilihan) {
                        KuesionerOpsi::updateOrCreate(
                            [
                                'pertanyaan_id' => $pertanyaan->id,
                                'kode_opsi' => 'EVA_'.$q->kode.'_'.($idx + 1),
                            ],
                            [
                                'label' => (string) $pilihan,
                                'nilai_skor' => count($q->pilihan_jawaban) - $idx,
                                'order' => $idx + 1,
                            ]
                        );
                    }
                }
            }
        }
    }

    /**
     * Memindahkan riwayat respon alumni dan atasan ke tabel kuesioner_jawaban terpadu.
     * Menggunakan direct batch SQL agar ribuan data terproses dalam hitungan detik.
     */
    private function migrateResponses(): void
    {
        // 1. Jawaban Tracer Study (Universitas)
        if (SchemaHasTable('tracer')) {
            $univSection = KuesionerSection::where('target', 'univ')->first();
            if ($univSection) {
                if (DB::connection()->getDriverName() === 'sqlite') {
                    DB::statement("
                        INSERT OR IGNORE INTO kuesioner_jawaban (
                            responden_type, biodata_id, section_id, pertanyaan_id, opsi_id, jawaban_teks, jawaban_json, created_at, updated_at
                        )
                        SELECT
                            'alumni', t.biodata_id, ?, p.id, o.id, t.answer, t.answer_json, COALESCE(t.created_at, CURRENT_TIMESTAMP), COALESCE(t.updated_at, CURRENT_TIMESTAMP)
                        FROM tracer t
                        JOIN kuesioner_pertanyaan p ON t.kode_pertanyaan = p.kode_pertanyaan
                        LEFT JOIN kuesioner_opsi o ON p.id = o.pertanyaan_id AND o.label = t.answer
                        WHERE t.biodata_id IS NOT NULL
                    ", [$univSection->id]);
                } else {
                    DB::statement("
                        INSERT INTO kuesioner_jawaban (
                            responden_type, biodata_id, section_id, pertanyaan_id, opsi_id, jawaban_teks, jawaban_json, created_at, updated_at
                        )
                        SELECT
                            'alumni', t.biodata_id, ?, p.id, o.id, t.answer, t.answer_json, COALESCE(t.created_at, NOW()), COALESCE(t.updated_at, NOW())
                        FROM tracer t
                        JOIN kuesioner_pertanyaan p ON t.kode_pertanyaan = p.kode_pertanyaan
                        LEFT JOIN kuesioner_opsi o ON p.id = o.pertanyaan_id AND o.label = t.answer
                        WHERE t.biodata_id IS NOT NULL
                    ", [$univSection->id]);
                }
            }
        }

        // 2. Jawaban Kuesioner Prodi
        if (SchemaHasTable('prodi_response') && SchemaHasTable('prodi_question')) {
            $prodiSection = KuesionerSection::where('target', 'prodi')->first();
            $defaultSecId = $prodiSection?->id ?: 1;

            if (DB::connection()->getDriverName() === 'sqlite') {
                DB::statement("
                    INSERT OR IGNORE INTO kuesioner_jawaban (
                        responden_type, biodata_id, section_id, pertanyaan_id, opsi_id, jawaban_teks, jawaban_json, created_at, updated_at
                    )
                    SELECT
                        'alumni', pr.biodata_id, COALESCE(sec.id, ?), p.id, o.id, pr.answer_text, pr.answer_json, COALESCE(pr.created_at, CURRENT_TIMESTAMP), COALESCE(pr.updated_at, CURRENT_TIMESTAMP)
                    FROM prodi_response pr
                    JOIN prodi_question pq ON pr.prodi_question_id = pq.id
                    JOIN kuesioner_pertanyaan p ON pq.code = p.kode_pertanyaan
                    LEFT JOIN kuesioner_section sec ON sec.target = 'prodi' AND sec.prodi_id = pq.prodi_id
                    LEFT JOIN kuesioner_opsi o ON p.id = o.pertanyaan_id AND o.label = pr.answer_text
                    WHERE pr.biodata_id IS NOT NULL
                ", [$defaultSecId]);
            } else {
                DB::statement("
                    INSERT INTO kuesioner_jawaban (
                        responden_type, biodata_id, section_id, pertanyaan_id, opsi_id, jawaban_teks, jawaban_json, created_at, updated_at
                    )
                    SELECT
                        'alumni', pr.biodata_id, COALESCE(sec.id, ?), p.id, o.id, pr.answer_text, pr.answer_json, COALESCE(pr.created_at, NOW()), COALESCE(pr.updated_at, NOW())
                    FROM prodi_response pr
                    JOIN prodi_question pq ON pr.prodi_question_id = pq.id
                    JOIN kuesioner_pertanyaan p ON pq.code = p.kode_pertanyaan
                    LEFT JOIN kuesioner_section sec ON sec.target = 'prodi' AND sec.prodi_id = pq.prodi_id
                    LEFT JOIN kuesioner_opsi o ON p.id = o.pertanyaan_id AND o.label = pr.answer_text
                    WHERE pr.biodata_id IS NOT NULL
                ", [$defaultSecId]);
            }
        }

        // 3. Jawaban Evaluasi Atasan
        if (SchemaHasTable('respon_evaluasi_atasan') && SchemaHasTable('pertanyaan_evaluasi_atasan')) {
            $atasanSection = KuesionerSection::where('target', 'atasan')->first();
            if ($atasanSection) {
                if (DB::connection()->getDriverName() === 'sqlite') {
                    DB::statement("
                        INSERT OR IGNORE INTO kuesioner_jawaban (
                            responden_type, evaluasi_atasan_id, section_id, pertanyaan_id, opsi_id, jawaban_teks, created_at, updated_at
                        )
                        SELECT
                            'atasan', rea.evaluasi_atasan_id, ?, p.id, o.id, rea.nilai, COALESCE(rea.created_at, CURRENT_TIMESTAMP), COALESCE(rea.updated_at, CURRENT_TIMESTAMP)
                        FROM respon_evaluasi_atasan rea
                        JOIN pertanyaan_evaluasi_atasan pea ON rea.pertanyaan_id = pea.id
                        JOIN kuesioner_pertanyaan p ON ('EVA_' || pea.kode) = p.kode_pertanyaan
                        LEFT JOIN kuesioner_opsi o ON p.id = o.pertanyaan_id AND o.label = rea.nilai
                        WHERE rea.evaluasi_atasan_id IS NOT NULL
                    ", [$atasanSection->id]);
                } else {
                    DB::statement("
                        INSERT INTO kuesioner_jawaban (
                            responden_type, evaluasi_atasan_id, section_id, pertanyaan_id, opsi_id, jawaban_teks, created_at, updated_at
                        )
                        SELECT
                            'atasan', rea.evaluasi_atasan_id, ?, p.id, o.id, rea.nilai, COALESCE(rea.created_at, NOW()), COALESCE(rea.updated_at, NOW())
                        FROM respon_evaluasi_atasan rea
                        JOIN pertanyaan_evaluasi_atasan pea ON rea.pertanyaan_id = pea.id
                        JOIN kuesioner_pertanyaan p ON CONCAT('EVA_', pea.kode) = p.kode_pertanyaan
                        LEFT JOIN kuesioner_opsi o ON p.id = o.pertanyaan_id AND o.label = rea.nilai
                        WHERE rea.evaluasi_atasan_id IS NOT NULL
                    ", [$atasanSection->id]);
                }
            }
        }
    }
}

/**
 * Memeriksa apakah suatu tabel telah tersedia di skema database.
 */
function SchemaHasTable(string $table): bool
{
    return Schema::hasTable($table);
}
