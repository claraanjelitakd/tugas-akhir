<?php

namespace Database\Seeders;

use App\Models\KelompokPertanyaan;
use App\Models\KuesionerJawaban;
use App\Models\KuesionerOpsi;
use App\Models\KuesionerPertanyaan;
use App\Models\KuesionerSection;
use App\Models\KuesionerSectionPertanyaan;
use App\Models\PertanyaanEvaluasiAtasan;
use App\Models\ProdiQuestion;
use App\Models\ProdiQuestionOption;
use App\Models\ProdiQuestionSection;
use App\Models\ProdiResponse;
use App\Models\RefSubpertanyaan2021;
use App\Models\RefSubpertanyaanDetil;
use App\Models\ResponEvaluasiAtasan;
use App\Models\SectionEvaluasiAtasan;
use App\Models\Tracer;
use Illuminate\Database\Seeder;
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

        // 1. MIGRATION KUESIONER UNIVERSITAS
        $this->migrateUniversitas();

        // 2. MIGRATION KUESIONER PROGRAM STUDI
        $this->migrateProdi();

        // 3. MIGRATION EVALUASI ATASAN
        $this->migrateAtasan();

        // 4. MIGRATION RESPON JAWABAN (Tracer, Prodi Response, Respon Atasan)
        $this->migrateResponses();

        $this->command?->info('Konsolidasi data Unified Survey Engine selesai!');
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
                ['kode_section' => 'SEC_UNIV_'.$kelompok->kode_kelompok],
                [
                    'target' => 'univ',
                    'prodi_id' => null,
                    'judul' => $kelompok->nama_kelompok,
                    'deskripsi' => $kelompok->deskripsi,
                    'order' => (int) $kelompok->kode_kelompok,
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
                    ->orWhere('kode_sub_pertanyaan', $sub->kode_pertanyaan)
                    ->orderBy('id')
                    ->get();

                foreach ($detils as $idx => $detil) {
                    KuesionerOpsi::updateOrCreate(
                        [
                            'pertanyaan_id' => $pertanyaan->id,
                            'kode_opsi' => $detil->kode_opsi ?: ($sub->kode_pertanyaan.'_'.($idx + 1)),
                        ],
                        [
                            'label' => $detil->opsi,
                            'nilai_skor' => is_numeric($detil->nilai) ? (int) $detil->nilai : null,
                            'order' => $idx + 1,
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
                    'judul' => 'Evaluasi Pengguna Lulusan',
                    'deskripsi' => 'Penilaian kinerja dan kesiapan alumni di lingkungan kerja.',
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
                    'judul' => $sec->judul,
                    'deskripsi' => $sec->deskripsi ?? null,
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
     */
    private function migrateResponses(): void
    {
        // 1. Jawaban Tracer Study (Universitas)
        if (SchemaHasTable('tracer')) {
            $univSection = KuesionerSection::where('target', 'univ')->first();
            if ($univSection) {
                $tracers = Tracer::query()->whereNotNull('biodata_id')->get();
                foreach ($tracers as $tracer) {
                    $pertanyaan = KuesionerPertanyaan::where('kode_pertanyaan', $tracer->kode_pertanyaan)->first();
                    if (! $pertanyaan) {
                        continue;
                    }

                    $opsi = null;
                    if ($tracer->answer) {
                        $opsi = KuesionerOpsi::where('pertanyaan_id', $pertanyaan->id)
                            ->where('label', $tracer->answer)
                            ->first();
                    }

                    KuesionerJawaban::updateOrCreate(
                        [
                            'responden_type' => 'alumni',
                            'biodata_id' => $tracer->biodata_id,
                            'pertanyaan_id' => $pertanyaan->id,
                        ],
                        [
                            'section_id' => $univSection->id,
                            'opsi_id' => $opsi?->id,
                            'jawaban_teks' => $tracer->answer,
                            'jawaban_json' => is_array($tracer->answer_json) ? $tracer->answer_json : null,
                            'created_at' => $tracer->created_at ?: now(),
                        ]
                    );
                }
            }
        }

        // 2. Jawaban Kuesioner Prodi
        if (SchemaHasTable('prodi_response')) {
            $prodiResponses = ProdiResponse::with('question')->whereNotNull('biodata_id')->get();
            foreach ($prodiResponses as $resp) {
                if (! $resp->question) {
                    continue;
                }

                $pertanyaan = KuesionerPertanyaan::where('kode_pertanyaan', $resp->question->code)->first();
                if (! $pertanyaan) {
                    continue;
                }

                $sec = KuesionerSection::where('target', 'prodi')
                    ->where('prodi_id', $resp->question->prodi_id)
                    ->first();

                if (! $sec) {
                    continue;
                }

                $opsi = null;
                if ($resp->answer_text) {
                    $opsi = KuesionerOpsi::where('pertanyaan_id', $pertanyaan->id)
                        ->where('label', $resp->answer_text)
                        ->first();
                }

                KuesionerJawaban::updateOrCreate(
                    [
                        'responden_type' => 'alumni',
                        'biodata_id' => $resp->biodata_id,
                        'pertanyaan_id' => $pertanyaan->id,
                    ],
                    [
                        'section_id' => $sec->id,
                        'opsi_id' => $opsi?->id,
                        'jawaban_teks' => $resp->answer_text,
                        'jawaban_json' => is_array($resp->answer_json) ? $resp->answer_json : null,
                        'created_at' => $resp->created_at ?: now(),
                    ]
                );
            }
        }

        // 3. Jawaban Evaluasi Atasan
        if (SchemaHasTable('respon_evaluasi_atasan')) {
            $atasanSection = KuesionerSection::where('target', 'atasan')->first();
            if ($atasanSection) {
                $evalResponses = ResponEvaluasiAtasan::with('pertanyaan')->get();
                foreach ($evalResponses as $resp) {
                    if (! $resp->pertanyaan) {
                        continue;
                    }

                    $pertanyaan = KuesionerPertanyaan::where('kode_pertanyaan', 'EVA_'.$resp->pertanyaan->kode)->first();
                    if (! $pertanyaan) {
                        continue;
                    }

                    $opsi = KuesionerOpsi::where('pertanyaan_id', $pertanyaan->id)
                        ->where('label', $resp->nilai)
                        ->first();

                    KuesionerJawaban::updateOrCreate(
                        [
                            'responden_type' => 'atasan',
                            'evaluasi_atasan_id' => $resp->evaluasi_atasan_id,
                            'pertanyaan_id' => $pertanyaan->id,
                        ],
                        [
                            'section_id' => $atasanSection->id,
                            'opsi_id' => $opsi?->id,
                            'jawaban_teks' => $resp->nilai,
                            'created_at' => $resp->created_at ?: now(),
                        ]
                    );
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
