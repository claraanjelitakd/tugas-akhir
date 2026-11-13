<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\EvaluasiAtasan;
use App\Models\KuesionerOpsi;
use App\Models\KuesionerPertanyaan;
use App\Models\KuesionerSection;
use App\Models\KuesionerSectionPertanyaan;
use App\Models\LogActivity;
use App\Services\Kuesioner\QuestionDefaultOptionsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller Kelola Kuesioner Evaluasi Atasan untuk Super Administrator.
 *
 * Mengelola instrumen kepuasan pengguna lulusan menggunakan skema database terpadu:
 * 1. Bagian / Section instrumen evaluasi atasan (kuesioner_section dengan target 'atasan')
 * 2. Butir pertanyaan evaluasi kinerja lulusan (kuesioner_pertanyaan & kuesioner_section_pertanyaan)
 * 3. Riwayat respon evaluasi atasan yang masuk (evaluasi_atasan & kuesioner_jawaban)
 */
class KelolaEvaluasiAtasanController extends Controller
{
    /**
     * Menampilkan halaman manajemen kuesioner evaluasi atasan, section, dan riwayat respon.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();

        // 1. Ambil seluruh section evaluasi atasan beserta jumlah pertanyaannya
        $sections = KuesionerSection::where('target', 'atasan')
            ->withCount('pertanyaan')
            ->orderBy('order', 'asc')
            ->get()
            ->map(function (KuesionerSection $s) {
                $key = match ((int) $s->order) {
                    1 => 'instansi',
                    2 => 'karakteristik',
                    3 => 'penilaian',
                    default => strtolower(str_replace(['SEC_EVA_', 'SEC_ATASAN_', 'SEC_'], '', $s->kode_section)),
                };

                return [
                    'id' => $s->id,
                    'key' => $key,
                    'title' => $s->judul,
                    'description' => $s->deskripsi,
                    'order' => (int) $s->order,
                    'source' => $key === 'instansi'
                        ? 'perusahaan, atasan, evaluasi_atasan'
                        : ($key === 'karakteristik' ? 'perusahaan, evaluasi_atasan' : 'kuesioner_jawaban'),
                    'pertanyaans_count' => (int) $s->pertanyaan_count,
                ];
            });

        // 2. Ambil seluruh pertanyaan instrumen evaluasi atasan beserta relasi section
        $sectionIds = $sections->pluck('id')->all();
        $pivotQuestions = KuesionerSectionPertanyaan::with(['pertanyaan.opsi', 'section'])
            ->whereIn('section_id', $sectionIds)
            ->orderBy('order', 'asc')
            ->get();

        $companyFieldCodes = [
            'EVA_bentuk_perusahaan', 'EVA_skala_perusahaan', 'EVA_jumlah_pegawai', 'EVA_jumlah_alumni_ukdw', 'EVA_standar_gaji_pertama',
            'bentuk_perusahaan', 'skala_perusahaan', 'jumlah_pegawai', 'jumlah_alumni_ukdw', 'standar_gaji_pertama',
        ];

        $questions = $pivotQuestions->map(function (KuesionerSectionPertanyaan $pivot) use ($companyFieldCodes) {
            $p = $pivot->pertanyaan;
            if (! $p) {
                return null;
            }

            $cleanCode = str_starts_with($p->kode_pertanyaan, 'EVA_') ? substr($p->kode_pertanyaan, 4) : $p->kode_pertanyaan;

            return [
                'id' => $p->id,
                'pertanyaan_id' => $p->id,
                'section_id' => $pivot->section_id,
                'kode' => $cleanCode,
                'full_kode' => $p->kode_pertanyaan,
                'label' => $p->label_pertanyaan,
                'aspek' => $p->teks_pertanyaan,
                'deskripsi' => $p->keterangan,
                'tipe' => $p->tipe,
                'order' => (int) $pivot->order,
                'is_required' => (bool) $pivot->wajib,
                'tampil_di' => $pivot->tampil_di ?: 'kuesioner',
                'is_active' => (bool) $p->is_active,
                'is_company_field' => in_array($p->kode_pertanyaan, $companyFieldCodes, true) || in_array($cleanCode, $companyFieldCodes, true),
                'pilihan_jawaban' => $p->opsi->pluck('label')->all(),
                'options' => $p->opsi->map(static fn (KuesionerOpsi $option): array => [
                    'id' => $option->id,
                    'code' => $option->kode_opsi ?: $p->kode_pertanyaan.'-'.str_pad((string) $option->order, 2, '0', STR_PAD_LEFT),
                    'option_text' => $option->label,
                    'jump_to' => $option->lompat_ke_pertanyaan_id,
                    'order' => (int) $option->order,
                ])->values()->all(),
                'section' => [
                    'id' => $pivot->section?->id,
                    'title' => $pivot->section?->judul,
                    'key' => strtolower(str_replace(['SEC_EVA_', 'SEC_'], '', $pivot->section?->kode_section ?? '')),
                ],
            ];
        })->filter()->values();

        $existingQuestionIds = $questions->pluck('id')->all();
        $bankQuestions = KuesionerPertanyaan::query()
            ->where('is_active', true)
            ->where('is_reusable', true)
            ->with([
                'pemilikProdi:id,nama_prodi,kode_prodi',
                'sections:id,target,prodi_id,judul',
                'opsi' => fn ($query) => $query->orderBy('order'),
            ])
            ->orderByDesc('id')
            ->get()
            ->map(function (KuesionerPertanyaan $question) use ($existingQuestionIds): array {
                $isAtasan = str_starts_with($question->kode_pertanyaan, 'EVA_')
                    || $question->sections->contains(fn (KuesionerSection $section): bool => $section->target === 'atasan');

                return [
                    'id' => $question->id,
                    'kode' => $question->kode_pertanyaan,
                    'label' => $question->label_pertanyaan,
                    'teks' => $question->teks_pertanyaan,
                    'tipe' => $question->tipe,
                    'asal' => $isAtasan
                        ? 'Evaluasi Atasan'
                        : ($question->pemilikProdi?->nama_prodi ?: 'Universitas (Pusat)'),
                    'is_already_added' => in_array($question->id, $existingQuestionIds, true),
                    'section_ids' => $question->sections
                        ->where('target', 'atasan')
                        ->pluck('id')
                        ->values()
                        ->all(),
                    'section_ids' => $question->sections
                        ->where('target', 'atasan')
                        ->pluck('id')
                        ->values()
                        ->all(),
                    'options' => $question->opsi->map(fn (KuesionerOpsi $option): array => [
                        'label' => $option->label,
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();

        // 3. Ambil riwayat evaluasi atasan yang masuk
        $evaluasis = EvaluasiAtasan::with([
            'biodata.user',
            'biodata.dataAkademik',
            'biodata.prodi',
            'perusahaan',
            'atasan',
            'respons.pertanyaan',
            'respons.opsi',
        ])
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('SuperAdmin/EvaluasiAtasan/Index', [
            'user' => $user,
            'sections' => $sections,
            'stepperOptions' => $sections->map(static fn (array $sec): array => [
                'value' => $sec['key'] ?: 'sec_'.$sec['id'],
                'label' => $sec['title'],
            ])->values(),
            'questions' => $questions,
            'bankQuestions' => $bankQuestions,
            'availableJumpTargets' => $questions
                ->map(fn (array $question): array => [
                    'code' => $question['full_kode'] ?: $question['kode'],
                    'text' => $question['label'] ?: $question['aspek'],
                ])
                ->values()
                ->all(),
            'evaluasis' => $evaluasis,
            'stats' => [
                'total_sections' => $sections->count(),
                'total_questions' => $questions->count(),
                'active_questions' => $questions->where('is_active', true)->count(),
                'total_evaluasi' => EvaluasiAtasan::count(),
                'submitted_evaluasi' => EvaluasiAtasan::where('is_submitted', true)->count(),
            ],
        ]);
    }

    /**
     * Tambah bagian / section evaluasi atasan baru.
     */
    public function storeSection(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'nullable|string',
            'order' => 'nullable|integer',
        ]);

        $maxOrder = KuesionerSection::where('target', 'atasan')->max('order') ?? 0;
        $order = $validated['order'] ?? ($maxOrder + 1);

        $baseKey = 'SEC_EVA_'.strtoupper(Str::slug($validated['title'], '_'));
        $key = $baseKey;
        $counter = 1;
        while (KuesionerSection::where('kode_section', $key)->exists()) {
            $key = "{$baseKey}_{$counter}";
            $counter++;
        }

        $section = KuesionerSection::create([
            'target' => 'atasan',
            'kode_section' => $key,
            'judul' => $validated['title'],
            'deskripsi' => $validated['description'] ?? null,
            'order' => $order,
            'is_active' => true,
        ]);

        LogActivity::record('KUESIONER_ATASAN_SECTION_CREATED', 'Membuat section kuesioner evaluasi atasan: '.$validated['title'].'.', $section, null, $validated);

        return redirect()->back()->with('success', 'Bagian kuesioner evaluasi atasan berhasil ditambahkan.');
    }

    /**
     * Perbarui bagian / section evaluasi atasan.
     */
    public function updateSection(Request $request, int $id): RedirectResponse
    {
        $section = KuesionerSection::where('target', 'atasan')->findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'nullable|string',
            'order' => 'nullable|integer',
        ]);

        $section->update([
            'judul' => $validated['title'],
            'deskripsi' => $validated['description'] ?? null,
            'order' => $validated['order'] ?? $section->order,
        ]);

        LogActivity::record('KUESIONER_ATASAN_SECTION_UPDATED', 'Memperbarui section kuesioner evaluasi atasan: '.$section->judul.'.', $section, null, $validated);

        return redirect()->back()->with('success', 'Bagian kuesioner evaluasi atasan berhasil diperbarui.');
    }

    /**
     * Hapus bagian / section evaluasi atasan.
     */
    public function destroySection(int $id): RedirectResponse
    {
        $section = KuesionerSection::where('target', 'atasan')->findOrFail($id);

        if ($section->pertanyaan()->count() > 0) {
            return redirect()->back()->withErrors([
                'message' => 'Bagian ini tidak dapat dihapus karena masih memuat butir pertanyaan aktif.',
            ]);
        }

        $section->delete();

        LogActivity::record('KUESIONER_ATASAN_SECTION_DELETED', 'Menghapus section kuesioner evaluasi atasan: '.$section->judul.'.', null, $section->toArray());

        return redirect()->back()->with('success', 'Bagian kuesioner evaluasi atasan berhasil dihapus.');
    }

    /**
     * Mengatur ulang urutan naik/turun bagian / section evaluasi atasan.
     */
    public function reorderSections(Request $request): RedirectResponse
    {
        if ($request->has(['id', 'direction'])) {
            $validated = $request->validate([
                'id' => 'required|exists:kuesioner_section,id',
                'direction' => 'required|in:up,down',
            ]);

            $currentSection = KuesionerSection::where('target', 'atasan')->findOrFail($validated['id']);
            $operator = $validated['direction'] === 'up' ? '<' : '>';
            $sortOrder = $validated['direction'] === 'up' ? 'desc' : 'asc';

            $adjacentSection = KuesionerSection::where('target', 'atasan')
                ->where('order', $operator, $currentSection->order)
                ->orderBy('order', $sortOrder)
                ->first();

            if ($adjacentSection) {
                $tempOrder = $currentSection->order;
                $currentSection->update(['order' => $adjacentSection->order]);
                $adjacentSection->update(['order' => $tempOrder]);
            }

            return redirect()->back()->with('success', 'Urutan bagian evaluasi atasan berhasil diperbarui.');
        }

        if ($request->has('orders')) {
            $validated = $request->validate([
                'orders' => 'required|array',
                'orders.*.id' => 'required|exists:kuesioner_section,id',
                'orders.*.order' => 'required|integer',
            ]);

            foreach ($validated['orders'] as $item) {
                KuesionerSection::where('target', 'atasan')
                    ->where('id', $item['id'])
                    ->update(['order' => $item['order']]);
            }

            return redirect()->back()->with('success', 'Seluruh urutan bagian berhasil diperbarui.');
        }

        return redirect()->back()->withErrors(['message' => 'Parameter pengurutan section tidak valid.']);
    }

    /**
     * Tambah butir pertanyaan instrumen evaluasi atasan baru.
     */
    public function storeQuestion(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'section_id' => 'nullable|integer|exists:kuesioner_section,id',
            'kode' => 'nullable|string|max:50',
            'label' => 'nullable|string|max:255',
            'aspek' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'tipe' => 'nullable|string|in:likert_5,pilihan_ganda,single_choice,radio_input,multiple_choice,checkbox,text,textarea',
            'pilihan_jawaban' => 'nullable|array',
            'option_jump_to' => 'nullable|array',
            'option_jump_to.*' => 'nullable|string|max:50',
            'order' => 'nullable|integer',
            'is_required' => 'nullable|boolean',
        ]);

        $sectionId = $validated['section_id'] ?? KuesionerSection::where('target', 'atasan')->orderBy('id', 'desc')->value('id');

        $kodeRaw = ! empty($validated['kode']) ? Str::slug($validated['kode'], '_') : Str::slug($validated['aspek'], '_');
        $kode = str_starts_with($kodeRaw, 'eva_') ? strtoupper($kodeRaw) : 'EVA_'.strtoupper($kodeRaw);

        $baseKode = $kode;
        $counter = 1;
        while (KuesionerPertanyaan::where('kode_pertanyaan', $kode)->exists()) {
            $kode = "{$baseKode}_{$counter}";
            $counter++;
        }

        $tipe = match ($validated['tipe'] ?? 'likert_5') {
            'single_choice' => 'pilihan_ganda',
            'checkbox' => 'multiple_choice',
            default => $validated['tipe'] ?? 'likert_5',
        };

        $pilihanJawaban = $validated['pilihan_jawaban'] ?? null;
        if ($tipe === 'likert_5' && empty($pilihanJawaban)) {
            $pilihanJawaban = QuestionDefaultOptionsService::getDefaultOptionTexts('rating_5');
        }
        if ($tipe === 'radio_input' && empty($pilihanJawaban)) {
            $pilihanJawaban = QuestionDefaultOptionsService::getDefaultOptionTexts('radio_input');
        }

        $pertanyaan = KuesionerPertanyaan::create([
            'kode_pertanyaan' => $kode,
            'label_pertanyaan' => filled($validated['label'] ?? null) ? trim($validated['label']) : null,
            'teks_pertanyaan' => $validated['aspek'],
            'keterangan' => $validated['deskripsi'] ?? null,
            'tipe' => $tipe,
            'pemilik_prodi_id' => null,
            'is_reusable' => true,
            'is_active' => true,
        ]);

        $maxOrder = KuesionerSectionPertanyaan::where('section_id', $sectionId)->max('order') ?? 0;
        $order = $validated['order'] ?? ($maxOrder + 1);

        KuesionerSectionPertanyaan::create([
            'section_id' => $sectionId,
            'pertanyaan_id' => $pertanyaan->id,
            'order' => $order,
            'wajib' => true,
            'tampil_di' => 'kuesioner',
            'is_active' => true,
        ]);

        KuesionerSectionPertanyaan::where('pertanyaan_id', $pertanyaan->id)
            ->where('section_id', $sectionId)
            ->update(['wajib' => $request->boolean('is_required', true)]);

        if (is_array($pilihanJawaban)) {
            foreach ($pilihanJawaban as $idx => $label) {
                KuesionerOpsi::create([
                    'pertanyaan_id' => $pertanyaan->id,
                    'kode_opsi' => $kode.'-'.str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT),
                    'label' => (string) $label,
                    'nilai_skor' => $tipe === 'likert_5' ? $idx + 1 : null,
                    'lompat_ke_pertanyaan_id' => filled($validated['option_jump_to'][$idx] ?? null)
                        ? trim((string) $validated['option_jump_to'][$idx])
                        : null,
                    'order' => $idx + 1,
                ]);
            }
        }

        LogActivity::record('KUESIONER_ATASAN_QUESTION_CREATED', 'Membuat pertanyaan evaluasi atasan: '.$pertanyaan->kode_pertanyaan.'.', $pertanyaan, null, $validated);

        return redirect()->back()->with('success', 'Butir pertanyaan evaluasi atasan berhasil ditambahkan.');
    }

    /**
     * Perbarui butir pertanyaan evaluasi atasan.
     */
    public function updateQuestion(Request $request, int $id): RedirectResponse
    {
        $pertanyaan = KuesionerPertanyaan::findOrFail($id);

        $validated = $request->validate([
            'section_id' => 'nullable|integer|exists:kuesioner_section,id',
            'label' => 'nullable|string|max:255',
            'aspek' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'tipe' => 'nullable|string|in:likert_5,pilihan_ganda,single_choice,radio_input,multiple_choice,checkbox,text,textarea',
            'pilihan_jawaban' => 'nullable|array',
            'option_jump_to' => 'nullable|array',
            'option_jump_to.*' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
            'order' => 'nullable|integer',
            'is_required' => 'nullable|boolean',
        ]);

        $tipe = match ($validated['tipe'] ?? $pertanyaan->tipe) {
            'single_choice' => 'pilihan_ganda',
            'checkbox' => 'multiple_choice',
            default => $validated['tipe'] ?? $pertanyaan->tipe,
        };

        $pertanyaan->update([
            'label_pertanyaan' => filled($validated['label'] ?? null) ? trim($validated['label']) : null,
            'teks_pertanyaan' => $validated['aspek'],
            'keterangan' => $validated['deskripsi'] ?? null,
            'tipe' => $tipe,
            'is_active' => $validated['is_active'] ?? $pertanyaan->is_active,
        ]);

        $sectionId = $validated['section_id'] ?? null;
        if ($sectionId) {
            $pivot = KuesionerSectionPertanyaan::where('pertanyaan_id', $pertanyaan->id)->first();
            if ($pivot) {
                $pivot->update([
                    'section_id' => $sectionId,
                    'order' => $validated['order'] ?? $pivot->order,
                    'wajib' => $request->boolean('is_required', (bool) $pivot->wajib),
                ]);
            }
        }

        $pilihanJawaban = $validated['pilihan_jawaban'] ?? null;
        if ($tipe === 'likert_5' && empty($pilihanJawaban)) {
            $pilihanJawaban = QuestionDefaultOptionsService::getDefaultOptionTexts('rating_5');
        }
        if ($tipe === 'radio_input' && empty($pilihanJawaban)) {
            $pilihanJawaban = QuestionDefaultOptionsService::getDefaultOptionTexts('radio_input');
        }
        if (is_array($pilihanJawaban) && ! empty($pilihanJawaban)) {
            $pertanyaan->opsi()->delete();
            foreach ($pilihanJawaban as $idx => $label) {
                KuesionerOpsi::create([
                    'pertanyaan_id' => $pertanyaan->id,
                    'kode_opsi' => $pertanyaan->kode_pertanyaan.'-'.str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT),
                    'label' => (string) $label,
                    'nilai_skor' => $tipe === 'likert_5' ? $idx + 1 : null,
                    'lompat_ke_pertanyaan_id' => filled($validated['option_jump_to'][$idx] ?? null)
                        ? trim((string) $validated['option_jump_to'][$idx])
                        : null,
                    'order' => $idx + 1,
                ]);
            }
        }

        LogActivity::record('KUESIONER_ATASAN_QUESTION_UPDATED', 'Memperbarui pertanyaan evaluasi atasan: '.$pertanyaan->kode_pertanyaan.'.', $pertanyaan, null, $validated);

        return redirect()->back()->with('success', 'Butir pertanyaan evaluasi atasan berhasil diperbarui.');
    }

    /**
     * Menyimpan opsi pilihan jawaban untuk pertanyaan evaluasi atasan.
     */
    public function storeOption(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'question_id' => 'required|exists:kuesioner_pertanyaan,id',
            'code' => 'nullable|string|max:50',
            'option_text' => 'required|string',
            'jump_to' => 'nullable|string|max:50',
            'order' => 'nullable|integer',
        ]);

        $question = $this->findAtasanQuestion((int) $validated['question_id']);
        $order = $validated['order'] ?? (($question->opsi()->max('order') ?? 0) + 1);

        $option = $question->opsi()->create([
            'kode_opsi' => trim((string) ($validated['code'] ?? '')) ?: $question->kode_pertanyaan.'-'.str_pad((string) $order, 2, '0', STR_PAD_LEFT),
            'label' => trim($validated['option_text']),
            'nilai_skor' => $question->tipe === 'likert_5' ? (int) $order : null,
            'lompat_ke_pertanyaan_id' => $validated['jump_to'] ?? null,
            'order' => (int) $order,
        ]);

        LogActivity::record('KUESIONER_ATASAN_OPTION_CREATED', 'Menambahkan opsi jawaban pada pertanyaan '.$question->kode_pertanyaan.'.', $option, null, $validated);

        return redirect()->back()->with('success', 'Opsi jawaban evaluasi atasan berhasil ditambahkan.');
    }

    /**
     * Memperbarui label, kode, urutan, dan percabangan opsi evaluasi atasan.
     */
    public function updateOption(Request $request, int $id): RedirectResponse
    {
        $option = KuesionerOpsi::with('pertanyaan')->findOrFail($id);
        $question = $this->findAtasanQuestion((int) $option->pertanyaan_id);

        $validated = $request->validate([
            'code' => 'nullable|string|max:50',
            'option_text' => 'required|string',
            'jump_to' => 'nullable|string|max:50',
            'order' => 'nullable|integer',
        ]);

        $order = $validated['order'] ?? $option->order;
        $option->update([
            'kode_opsi' => trim((string) ($validated['code'] ?? $option->kode_opsi)) ?: $option->kode_opsi,
            'label' => trim($validated['option_text']),
            'nilai_skor' => $question->tipe === 'likert_5' ? (int) $order : null,
            'lompat_ke_pertanyaan_id' => $validated['jump_to'] ?? null,
            'order' => (int) $order,
        ]);

        LogActivity::record('KUESIONER_ATASAN_OPTION_UPDATED', 'Memperbarui opsi jawaban pada pertanyaan '.$question->kode_pertanyaan.'.', $option, null, $validated);

        return redirect()->back()->with('success', 'Opsi jawaban evaluasi atasan berhasil diperbarui.');
    }

    /**
     * Menghapus opsi pilihan jawaban evaluasi atasan.
     */
    public function destroyOption(int $id): RedirectResponse
    {
        $option = KuesionerOpsi::findOrFail($id);
        $this->findAtasanQuestion((int) $option->pertanyaan_id);
        $questionCode = $option->pertanyaan?->kode_pertanyaan;
        $option->delete();

        LogActivity::record('KUESIONER_ATASAN_OPTION_DELETED', 'Menghapus opsi jawaban pada pertanyaan '.$questionCode.'.', null, $option->toArray());

        return redirect()->back()->with('success', 'Opsi jawaban evaluasi atasan berhasil dihapus.');
    }

    /**
     * Memastikan pertanyaan berada pada section instrumen evaluasi atasan.
     */
    private function findAtasanQuestion(int $questionId): KuesionerPertanyaan
    {
        $question = KuesionerPertanyaan::findOrFail($questionId);
        $isAtasanQuestion = KuesionerSectionPertanyaan::where('pertanyaan_id', $question->id)
            ->whereHas('section', static fn ($query) => $query->where('target', 'atasan'))
            ->exists();

        abort_unless($isAtasanQuestion, 403, 'Pertanyaan bukan bagian dari instrumen evaluasi atasan.');

        return $question;
    }

    /**
     * Toggle status aktif/nonaktif butir pertanyaan evaluasi.
     */
    public function toggleQuestion(int $id): RedirectResponse
    {
        $question = KuesionerPertanyaan::findOrFail($id);
        $question->update(['is_active' => ! $question->is_active]);

        return redirect()->back()->with('success', 'Status keaktifan butir evaluasi berhasil diubah.');
    }

    /**
     * Hapus butir pertanyaan evaluasi atasan.
     */
    public function destroyQuestion(int $id): RedirectResponse
    {
        $question = KuesionerPertanyaan::findOrFail($id);

        $companyFieldCodes = [
            'EVA_bentuk_perusahaan', 'EVA_skala_perusahaan', 'EVA_jumlah_pegawai', 'EVA_jumlah_alumni_ukdw', 'EVA_standar_gaji_pertama',
            'bentuk_perusahaan', 'skala_perusahaan', 'jumlah_pegawai', 'jumlah_alumni_ukdw', 'standar_gaji_pertama',
        ];

        if (in_array($question->kode_pertanyaan, $companyFieldCodes, true)) {
            return redirect()->back()->withErrors([
                'message' => 'Pertanyaan karakteristik perusahaan tidak dapat dihapus karena terhubung langsung ke entitas master perusahaan.',
            ]);
        }

        KuesionerSectionPertanyaan::where('pertanyaan_id', $question->id)->delete();
        $question->opsi()->delete();
        $question->delete();

        LogActivity::record('KUESIONER_ATASAN_QUESTION_DELETED', 'Menghapus pertanyaan evaluasi atasan: '.$question->kode_pertanyaan.'.', null, $question->toArray());

        return redirect()->back()->with('success', 'Butir aspek evaluasi atasan berhasil dihapus.');
    }

    /**
     * Reorder urutan pertanyaan.
     */
    public function reorderQuestions(Request $request): RedirectResponse
    {
        if ($request->has(['id', 'direction'])) {
            $validated = $request->validate([
                'id' => 'required|exists:kuesioner_pertanyaan,id',
                'direction' => 'required|in:up,down',
            ]);

            $currentQuestion = KuesionerPertanyaan::findOrFail($validated['id']);
            $currentPivot = KuesionerSectionPertanyaan::where('pertanyaan_id', $currentQuestion->id)->first();

            if ($currentPivot) {
                $operator = $validated['direction'] === 'up' ? '<' : '>';
                $sortOrder = $validated['direction'] === 'up' ? 'desc' : 'asc';

                $adjacentPivot = KuesionerSectionPertanyaan::where('section_id', $currentPivot->section_id)
                    ->where('order', $operator, $currentPivot->order)
                    ->orderBy('order', $sortOrder)
                    ->first();

                if ($adjacentPivot) {
                    $tempOrder = $currentPivot->order;
                    $currentPivot->update(['order' => $adjacentPivot->order]);
                    $adjacentPivot->update(['order' => $tempOrder]);
                }
            }

            return redirect()->back()->with('success', 'Urutan butir pertanyaan evaluasi berhasil diperbarui.');
        }

        if ($request->has('orders')) {
            $validated = $request->validate([
                'orders' => 'required|array',
                'orders.*.id' => 'required|exists:kuesioner_pertanyaan,id',
                'orders.*.order' => 'required|integer',
            ]);

            foreach ($validated['orders'] as $item) {
                KuesionerSectionPertanyaan::where('pertanyaan_id', $item['id'])
                    ->update(['order' => $item['order']]);
            }

            return redirect()->back()->with('success', 'Seluruh urutan butir aspek evaluasi atasan berhasil diperbarui.');
        }

        return redirect()->back()->withErrors(['message' => 'Parameter pengurutan pertanyaan tidak valid.']);
    }

    /**
     * Menyematkan pertanyaan aktif yang reusable dari Bank Soal ke section evaluasi atasan.
     *
     * @return RedirectResponse Redirect kembali ke halaman pengelolaan instrumen.
     */
    public function attachFromBank(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'section_id' => 'required|exists:kuesioner_section,id',
            'pertanyaan_ids' => 'required|array|min:1',
            'pertanyaan_ids.*' => 'required|exists:kuesioner_pertanyaan,id',
        ]);

        $section = KuesionerSection::whereKey($validated['section_id'])
            ->where('target', 'atasan')
            ->firstOrFail();

        $questions = KuesionerPertanyaan::query()
            ->whereIn('id', $validated['pertanyaan_ids'])
            ->where('is_active', true)
            ->where('is_reusable', true)
            ->get();

        $order = (int) (KuesionerSectionPertanyaan::where('section_id', $section->id)->max('order') ?: 0);
        $addedCount = 0;

        foreach ($questions as $question) {
            $placement = KuesionerSectionPertanyaan::firstOrCreate(
                [
                    'section_id' => $section->id,
                    'pertanyaan_id' => $question->id,
                ],
                [
                    'order' => ++$order,
                    'wajib' => true,
                    'is_active' => true,
                    'tampil_di' => 'kuesioner',
                ]
            );

            if (! $placement->wasRecentlyCreated) {
                $placement->update(['is_active' => true]);
            }

            $addedCount++;
        }

        LogActivity::record('KUESIONER_ATASAN_BANK_ATTACHED', 'Menyematkan '.$addedCount.' pertanyaan dari Bank Soal ke section '.$section->judul.'.', $section, null, [
            'pertanyaan_ids' => $validated['pertanyaan_ids'],
        ]);

        return redirect()->back()->with('success', $addedCount.' pertanyaan dari Bank Soal berhasil disematkan.');
    }
}
