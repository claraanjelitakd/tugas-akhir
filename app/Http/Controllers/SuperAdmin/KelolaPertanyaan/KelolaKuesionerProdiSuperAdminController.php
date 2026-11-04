<?php

namespace App\Http\Controllers\SuperAdmin\KelolaPertanyaan;

use App\Http\Controllers\Controller;
use App\Models\KuesionerOpsi;
use App\Models\KuesionerPertanyaan;
use App\Models\KuesionerSection;
use App\Models\KuesionerSectionPertanyaan;
use App\Models\Prodi;
use App\Models\ProdiQuestion;
use App\Models\ProdiQuestionOption;
use App\Models\ProdiQuestionSection;
use App\Models\User;
use App\Services\Kuesioner\QuestionDefaultOptionsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller Pengaturan Kuesioner Program Studi (Unified Survey Engine).
 *
 * Mengelola instrumen pertanyaan tingkat Program Studi menggunakan skema terpadu:
 * - kuesioner_section
 * - kuesioner_pertanyaan
 * - kuesioner_section_pertanyaan (pivot penempatan & multi-placement)
 * - kuesioner_opsi
 *
 * Dilengkapi dengan sinkronisasi dual-update ke tabel legacy (prodi_question & prodi_question_section)
 * untuk menjamin kompatibilitas runtime penuh selama masa transisi.
 */
class KelolaKuesionerProdiSuperAdminController extends Controller
{
    /**
     * Mendapatkan daftar Prodi sesuai lingkup akun yang sedang login.
     */
    private function accessibleProdiQuery(User $user): Builder
    {
        return Prodi::query()
            ->when($user->role === 'admin_prodi', fn (Builder $query) => $query->whereKey($user->prodi_id ?? 0))
            ->when($user->role === 'admin_fakultas', fn (Builder $query) => $query->where('fakultas_id', $user->fakultas_id ?? 0));
    }

    /**
     * Memastikan Prodi berada dalam lingkup akses akun.
     */
    private function ensureProdiAccess(User $user, int $prodiId): void
    {
        abort_unless(
            $this->accessibleProdiQuery($user)->whereKey($prodiId)->exists(),
            403,
            'Anda tidak memiliki akses ke program studi ini.'
        );
    }

    private function basePath(User $user): string
    {
        return match ($user->role) {
            'admin_fakultas' => '/fakultas/prodi-kuesioner',
            'admin_prodi' => '/prodi/kuesioner',
            default => '/superadmin/prodi-kuesioner',
        };
    }

    private function dashboardPath(User $user): string
    {
        return match ($user->role) {
            'admin_fakultas' => '/fakultas/dashboard',
            'admin_prodi' => '/prodi/dashboard',
            default => '/superadmin/dashboard',
        };
    }

    /**
     * Menampilkan halaman utama Pengaturan Kuesioner Prodi berbasis Unified Survey Engine.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user()->load(['fakultas', 'prodi']);

        // Ambil daftar program studi sesuai lingkup akses akun
        $prodis = $this->accessibleProdiQuery($user)
            ->select('id', 'nama_prodi', 'kode_prodi', 'fakultas_id')
            ->with('fakultas:id,nama_fakultas')
            ->orderBy('nama_prodi')
            ->get();

        // Tentukan prodi mana yang dipilih
        $selectedProdiId = $request->query('prodi_id')
            ? (int) $request->query('prodi_id')
            : ($prodis->first()?->id ?? 0);

        $selectedProdi = $prodis->firstWhere('id', $selectedProdiId) ?? $prodis->first();
        $selectedProdiId = (int) ($selectedProdi?->id ?? 0);

        // Ambil Section dari tabel terpadu kuesioner_section
        $rawSections = $selectedProdiId
            ? KuesionerSection::where('target', 'prodi')
                ->where('prodi_id', $selectedProdiId)
                ->withCount('sectionPertanyaan')
                ->orderBy('order', 'asc')
                ->get()
            : collect();

        // Jika belum ada section di kuesioner_section, sinkronkan dari legacy atau buatkan default
        if ($selectedProdiId && $rawSections->isEmpty()) {
            $legacySections = Schema::hasTable('prodi_question_section')
                ? ProdiQuestionSection::where('prodi_id', $selectedProdiId)->orderBy('order')->get()
                : collect();
            if ($legacySections->isNotEmpty()) {
                foreach ($legacySections as $legSec) {
                    KuesionerSection::firstOrCreate(
                        [
                            'target' => 'prodi',
                            'prodi_id' => $selectedProdiId,
                            'judul' => $legSec->title,
                        ],
                        [
                            'kode_section' => 'PRODI_'.$selectedProdiId.'_'.Str::upper(Str::slug(Str::limit($legSec->title, 15, ''))),
                            'deskripsi' => $legSec->description,
                            'order' => $legSec->order,
                            'is_active' => true,
                        ]
                    );
                }
            } else {
                KuesionerSection::create([
                    'target' => 'prodi',
                    'prodi_id' => $selectedProdiId,
                    'kode_section' => 'PRODI_'.$selectedProdiId.'_SEC1',
                    'judul' => 'Bagian Evaluasi & Relevansi Program Studi',
                    'deskripsi' => 'Kelompok pertanyaan kuesioner khusus Program Studi '.$selectedProdi->nama_prodi,
                    'order' => 1,
                    'is_active' => true,
                ]);
            }

            $rawSections = KuesionerSection::where('target', 'prodi')
                ->where('prodi_id', $selectedProdiId)
                ->withCount('sectionPertanyaan')
                ->orderBy('order', 'asc')
                ->get();
        }

        $sectionIds = $rawSections->pluck('id')->all();

        // Ambil butir pertanyaan yang terhubung ke section-section prodi ini via kuesioner_section_pertanyaan
        $pivotItems = KuesionerSectionPertanyaan::whereIn('section_id', $sectionIds)
            ->with([
                'pertanyaan.opsi' => fn ($q) => $q->orderBy('order'),
                'pertanyaan.pemilikProdi:id,nama_prodi,kode_prodi',
                'section:id,judul,order',
            ])
            ->orderBy('order', 'asc')
            ->get();

        $questions = $pivotItems->map(function (KuesionerSectionPertanyaan $pivot) {
            $master = $pivot->pertanyaan;

            return [
                'id' => $master->id,
                'pivot_id' => $pivot->id,
                'code' => $master->kode_pertanyaan,
                'question_text' => $master->teks_pertanyaan,
                'type' => $master->tipe,
                'order' => $pivot->order,
                'is_required' => (bool) $pivot->wajib,
                'is_active' => (bool) $master->is_active,
                'is_reusable' => (bool) $master->is_reusable,
                'prodi_question_section_id' => $pivot->section_id,
                'section' => [
                    'id' => $pivot->section_id,
                    'title' => $pivot->section?->judul,
                ],
                'asal' => str_starts_with($master->kode_pertanyaan, 'EVA_')
                    ? 'Evaluasi Atasan'
                    : ($master->pemilikProdi ? $master->pemilikProdi->nama_prodi : 'Umum / Universitas'),
                'options' => $master->opsi->map(fn (KuesionerOpsi $opt) => [
                    'id' => $opt->id,
                    'code' => $master->kode_pertanyaan.'-'.str_pad((string) $opt->order, 2, '0', STR_PAD_LEFT),
                    'option_text' => $opt->label,
                    'score' => $opt->nilai_skor,
                    'jump_to' => $opt->lompat_ke_pertanyaan_id,
                    'order' => $opt->order,
                ])->values()->all(),
            ];
        })->values();

        // Format sections untuk komponen Vue
        $sections = $rawSections->map(fn (KuesionerSection $s) => [
            'id' => $s->id,
            'title' => $s->judul,
            'description' => $s->deskripsi,
            'order' => $s->order,
            'is_active' => (bool) $s->is_active,
            'questions_count' => $questions->where('prodi_question_section_id', $s->id)->count(),
        ])->values();

        // Seluruh section yang ada (Univ, Prodi, dsb) untuk fitur Multi-Placement (Penempatan Lintas Section)
        $allSections = KuesionerSection::select('id', 'target', 'prodi_id', 'judul', 'kode_section')
            ->with('prodi:id,nama_prodi')
            ->orderBy('target')
            ->orderBy('order')
            ->get()
            ->map(fn (KuesionerSection $s) => [
                'id' => $s->id,
                'judul' => $s->judul,
                'target' => $s->target,
                'prodi_id' => $s->prodi_id,
                'label' => ($s->target === 'univ' ? '[Universitas] ' : ($s->prodi ? '['.$s->prodi->nama_prodi.'] ' : '[Prodi] ')).$s->judul,
            ])
            ->values()
            ->all();

        // Bank Pertanyaan terpadu (lintas prodi & universitas yang aktif & reusable)
        $existingCodes = $questions->pluck('code')->filter()->all();
        $bankQuestions = KuesionerPertanyaan::query()
            ->where('is_reusable', true)
            ->where('is_active', true)
            ->with([
                'pemilikProdi:id,nama_prodi,kode_prodi',
                'sections:id,target,prodi_id,judul',
                'opsi' => fn ($q) => $q->orderBy('order'),
            ])
            ->orderBy('id', 'desc')
            ->get()
            ->map(function (KuesionerPertanyaan $p) use ($existingCodes) {
                $isAtasan = str_starts_with($p->kode_pertanyaan, 'EVA_')
                    || $p->sections->contains(fn ($s) => $s->target === 'atasan');

                if ($isAtasan) {
                    $asal = 'Evaluasi Atasan';
                } elseif ($p->pemilikProdi) {
                    $asal = $p->pemilikProdi->nama_prodi;
                } else {
                    $asal = 'Universitas (Pusat)';
                }

                return [
                    'id' => $p->id,
                    'kode' => $p->kode_pertanyaan,
                    'teks' => $p->teks_pertanyaan,
                    'tipe' => $p->tipe,
                    'asal' => $asal,
                    'is_already_added' => in_array($p->kode_pertanyaan, $existingCodes, true),
                    'options' => $p->opsi->map(fn ($o) => [
                        'label' => $o->label,
                        'score' => $o->nilai_skor,
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();

        // Target lompatan yang tersedia
        $availableJumpTargets = $questions->pluck('code')->filter()->values()->all();

        return Inertia::render('SuperAdmin/Pertanyaan/ProdiKuesionerIndex', [
            'user' => $user,
            'role' => $user->role,
            'basePath' => $this->basePath($user),
            'dashboardPath' => $this->dashboardPath($user),
            'fakultas' => $user->fakultas,
            'prodis' => $prodis,
            'selectedProdi' => $selectedProdi,
            'sections' => $sections,
            'questions' => $questions,
            'allSections' => $allSections,
            'bankQuestions' => $bankQuestions,
            'availableJumpTargets' => $availableJumpTargets,
            'stats' => [
                'total_sections' => $sections->count(),
                'total_questions' => $questions->count(),
                'total_bank_questions' => count($bankQuestions),
            ],
        ]);
    }

    // =========================================================================
    // SECTION PRODI MANAGEMENT (UNIFIED & MIRROR)
    // =========================================================================

    /**
     * Menyimpan section kuesioner prodi baru.
     */
    public function storeSection(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'prodi_id' => 'required|exists:prodi,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'order' => 'nullable|integer',
        ]);

        $user = Auth::user();
        $prodiId = (int) $validated['prodi_id'];
        $this->ensureProdiAccess($user, $prodiId);

        if (empty($validated['order'])) {
            $maxOrder = KuesionerSection::where('target', 'prodi')->where('prodi_id', $prodiId)->max('order') ?? 0;
            $validated['order'] = $maxOrder + 1;
        }

        // Simpan ke skema terpadu kuesioner_section
        $code = 'PRODI_'.$prodiId.'_'.Str::upper(Str::slug(Str::limit($validated['title'], 15, ''))).'_'.time();
        KuesionerSection::create([
            'target' => 'prodi',
            'prodi_id' => $prodiId,
            'kode_section' => $code,
            'judul' => $validated['title'],
            'deskripsi' => $validated['description'] ?? null,
            'order' => $validated['order'],
            'is_active' => true,
        ]);

        // Simpan mirror ke tabel legacy prodi_question_section jika ada
        if (Schema::hasTable('prodi_question_section')) {
            ProdiQuestionSection::create([
                'prodi_id' => $prodiId,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'order' => $validated['order'],
            ]);
        }

        return redirect()->back()->with('success', 'Bagian (section) kuesioner prodi berhasil ditambahkan.');
    }

    /**
     * Memperbarui section kuesioner prodi.
     */
    public function updateSection(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();

        $legacySec = Schema::hasTable('prodi_question_section') ? ProdiQuestionSection::find($id) : null;
        if ($legacySec) {
            $this->ensureProdiAccess($user, (int) $legacySec->prodi_id);
        }

        $section = KuesionerSection::find($id);
        if ($section) {
            $this->ensureProdiAccess($user, (int) $section->prodi_id);
        }

        abort_if(! $legacySec && ! $section, 404);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'order' => 'nullable|integer',
        ]);

        if ($section) {
            $oldTitle = $section->judul;

            $section->update([
                'judul' => $validated['title'],
                'deskripsi' => $validated['description'] ?? null,
                'order' => $validated['order'] ?? $section->order,
            ]);

            // Mirror ke legacy jika tabel ada
            if (Schema::hasTable('prodi_question_section')) {
                ProdiQuestionSection::where('prodi_id', $section->prodi_id)
                    ->where('title', $oldTitle)
                    ->update([
                        'title' => $validated['title'],
                        'description' => $validated['description'] ?? null,
                    ]);
            }
        }

        if ($legacySec && $legacySec->exists) {
            $legacySec->update($validated);
        }

        return redirect()->back()->with('success', 'Bagian (section) kuesioner prodi berhasil diperbarui.');
    }

    /**
     * Menghapus section kuesioner prodi.
     */
    public function destroySection(int $id): RedirectResponse
    {
        $user = Auth::user();

        $legacySec = Schema::hasTable('prodi_question_section') ? ProdiQuestionSection::find($id) : null;
        if ($legacySec) {
            $this->ensureProdiAccess($user, (int) $legacySec->prodi_id);
        }

        $section = KuesionerSection::with('sectionPertanyaan')->find($id);
        if ($section) {
            $this->ensureProdiAccess($user, (int) $section->prodi_id);
        }

        abort_if(! $legacySec && ! $section, 404);

        if ($section) {
            $title = $section->judul;
            $prodiId = $section->prodi_id;

            // Hapus pivot penempatan di section ini
            $section->sectionPertanyaan()->delete();
            $section->delete();

            // Mirror hapus ke legacy jika tabel ada
            if (Schema::hasTable('prodi_question_section')) {
                ProdiQuestionSection::where('prodi_id', $prodiId)
                    ->where('title', $title)
                    ->delete();
            }
        }

        if ($legacySec && $legacySec->exists) {
            $legacySec->delete();
        }

        return redirect()->back()->with('success', 'Bagian (section) kuesioner prodi berhasil dihapus.');
    }

    /**
     * Mengatur urutan posisi section prodi.
     */
    public function reorderSection(Request $request): RedirectResponse
    {
        if ($request->has(['id', 'direction'])) {
            $validated = $request->validate([
                'id' => 'required|exists:kuesioner_section,id',
                'direction' => 'required|in:up,down',
            ]);

            $user = Auth::user();
            $currentSection = KuesionerSection::findOrFail($validated['id']);
            $this->ensureProdiAccess($user, (int) $currentSection->prodi_id);
            $prodiId = $currentSection->prodi_id;

            $operator = $validated['direction'] === 'up' ? '<' : '>';
            $sortOrder = $validated['direction'] === 'up' ? 'desc' : 'asc';

            $adjacentSection = KuesionerSection::where('target', 'prodi')
                ->where('prodi_id', $prodiId)
                ->where('order', $operator, $currentSection->order)
                ->orderBy('order', $sortOrder)
                ->first();

            if ($adjacentSection) {
                $tempOrder = $currentSection->order;
                $currentSection->update(['order' => $adjacentSection->order]);
                $adjacentSection->update(['order' => $tempOrder]);
            }

            return redirect()->back()->with('success', 'Urutan bagian prodi berhasil dipindahkan.');
        }

        return redirect()->back()->withErrors(['message' => 'Parameter pengurutan tidak valid.']);
    }

    // =========================================================================
    // QUESTION PRODI MANAGEMENT (UNIFIED & ANTI-DUPLICATION)
    // =========================================================================

    /**
     * Toggle status aktif/non-aktif butir pertanyaan master secara instan.
     */
    public function toggleActive(Request $request, int $id): RedirectResponse
    {
        $question = KuesionerPertanyaan::findOrFail($id);
        $question->is_active = ! $question->is_active;
        $question->save();

        $statusText = $question->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()->back()->with('success', "Pertanyaan [{$question->kode_pertanyaan}] berhasil {$statusText}.");
    }

    /**
     * Menyimpan butir pertanyaan kuesioner baru ke Bank Soal terpadu dan menempatkannya ke section.
     */
    public function storeQuestion(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'prodi_id' => 'required|exists:prodi,id',
            'code' => 'required|string|max:50',
            'question_text' => 'required|string',
            'type' => 'required|string|in:text,textarea,number,single_choice,radio,radio_input,radio_text,multiple_choice,checkbox,dropdown,searchable_select,rating_5,multiple_number,matrix,matrix_dual,multiple_textbox,date,time,file,header',
            'is_required' => 'boolean',
            'is_reusable' => 'boolean',
            'prodi_question_section_id' => 'nullable',
            'target_section_ids' => 'nullable|array',
            'order' => 'nullable|integer',
            'options' => 'nullable|array',
        ]);

        $user = Auth::user();
        $prodiId = (int) $validated['prodi_id'];
        $this->ensureProdiAccess($user, $prodiId);

        if ($validated['type'] === 'radio') {
            $validated['type'] = 'single_choice';
        }
        if ($validated['type'] === 'checkbox') {
            $validated['type'] = 'multiple_choice';
        }

        $code = trim($validated['code']);

        // Resolusi Bagian (Section) Terpadu
        $rawSectionId = $validated['prodi_question_section_id'] ?? null;
        $unifiedSection = null;

        if ($rawSectionId) {
            $unifiedSection = KuesionerSection::find($rawSectionId);
            if (! $unifiedSection && Schema::hasTable('prodi_question_section')) {
                // Dukungan adaptif jika yang dikirim adalah ID legacy ProdiQuestionSection
                $legacySec = ProdiQuestionSection::find($rawSectionId);
                if ($legacySec) {
                    $unifiedSection = KuesionerSection::firstOrCreate(
                        [
                            'target' => 'prodi',
                            'prodi_id' => $legacySec->prodi_id,
                            'judul' => $legacySec->title,
                        ],
                        [
                            'kode_section' => 'PRODI_'.$legacySec->prodi_id.'_'.Str::upper(Str::slug(Str::limit($legacySec->title, 15, ''))),
                            'deskripsi' => $legacySec->description,
                            'order' => $legacySec->order,
                            'is_active' => true,
                        ]
                    );
                }
            }
        }

        if (! $unifiedSection) {
            $unifiedSection = KuesionerSection::where('target', 'prodi')
                ->where('prodi_id', $prodiId)
                ->first();

            if (! $unifiedSection) {
                $unifiedSection = KuesionerSection::create([
                    'target' => 'prodi',
                    'prodi_id' => $prodiId,
                    'kode_section' => 'PRODI_'.$prodiId.'_SEC1',
                    'judul' => 'Bagian Evaluasi & Relevansi Program Studi',
                    'order' => 1,
                    'is_active' => true,
                ]);
            }
        }

        $sectionId = $unifiedSection->id;

        // Cek apakah pertanyaan dengan kode ini sudah ada di Bank Soal (Pencegahan Pertanyaan Double)
        $master = KuesionerPertanyaan::where('kode_pertanyaan', $code)->first();

        if (! $master) {
            $master = KuesionerPertanyaan::create([
                'kode_pertanyaan' => $code,
                'teks_pertanyaan' => trim($validated['question_text']),
                'tipe' => $validated['type'],
                'pemilik_prodi_id' => $prodiId,
                'is_reusable' => $request->boolean('is_reusable', true),
                'is_active' => true,
            ]);
        } else {
            // Update teks dan tipe jika ada perubahan
            $master->update([
                'teks_pertanyaan' => trim($validated['question_text']),
                'tipe' => $validated['type'],
                'is_reusable' => $request->boolean('is_reusable', true),
            ]);
        }

        $order = $validated['order'];
        if (empty($order)) {
            $maxOrder = KuesionerSectionPertanyaan::where('section_id', $sectionId)->max('order') ?? 0;
            $order = $maxOrder + 1;
        }

        // Pasang pivot di section utama
        KuesionerSectionPertanyaan::updateOrCreate(
            [
                'section_id' => $sectionId,
                'pertanyaan_id' => $master->id,
            ],
            [
                'order' => $order,
                'wajib' => $request->boolean('is_required', true),
            ]
        );

        // Pasang pivot di section lain jika dipilih (Multi-placement)
        if (! empty($validated['target_section_ids'])) {
            foreach ($validated['target_section_ids'] as $extraSecId) {
                if ((int) $extraSecId !== $sectionId) {
                    $extraOrder = (KuesionerSectionPertanyaan::where('section_id', $extraSecId)->max('order') ?? 0) + 1;
                    KuesionerSectionPertanyaan::firstOrCreate(
                        [
                            'section_id' => $extraSecId,
                            'pertanyaan_id' => $master->id,
                        ],
                        [
                            'order' => $extraOrder,
                            'wajib' => $request->boolean('is_required', true),
                        ]
                    );
                }
            }
        }

        // Kelola opsi jawaban
        if ($request->has('options') && is_array($request->options) && count($request->options) > 0) {
            $orderCounter = 1;
            foreach ($request->options as $opt) {
                $labelText = is_array($opt) ? ($opt['option_text'] ?? $opt['label'] ?? '') : (string) $opt;
                $score = is_array($opt) ? ($opt['score'] ?? null) : null;
                $trimmed = trim((string) $labelText);

                if ($trimmed !== '') {
                    KuesionerOpsi::create([
                        'pertanyaan_id' => $master->id,
                        'label' => $trimmed,
                        'nilai_skor' => $score ? (int) $score : null,
                        'order' => $orderCounter++,
                    ]);
                }
            }
        } else {
            QuestionDefaultOptionsService::generateForUnifiedQuestion($master);
        }

        // Mirror ke tabel legacy prodi_question jika tabel legacy masih aktif
        if (Schema::hasTable('prodi_question') && Schema::hasTable('prodi_question_section')) {
            $legacySection = is_numeric($rawSectionId) && ProdiQuestionSection::whereKey($rawSectionId)->exists()
                ? ProdiQuestionSection::find($rawSectionId)
                : ProdiQuestionSection::firstOrCreate(
                    ['prodi_id' => $prodiId],
                    ['title' => $unifiedSection->judul, 'order' => 1]
                );

            $prodiQ = ProdiQuestion::updateOrCreate(
                [
                    'prodi_id' => $prodiId,
                    'code' => $master->kode_pertanyaan,
                ],
                [
                    'prodi_question_section_id' => $legacySection->id,
                    'question_text' => $master->teks_pertanyaan,
                    'type' => $master->tipe,
                    'is_required' => $request->boolean('is_required', true),
                    'order' => $order,
                ]
            );

            // Mirror opsi legacy
            if (Schema::hasTable('prodi_question_option')) {
                foreach ($master->opsi as $idx => $opt) {
                    ProdiQuestionOption::updateOrCreate(
                        [
                            'prodi_question_id' => $prodiQ->id,
                            'code' => $master->kode_pertanyaan.'-'.str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT),
                        ],
                        [
                            'option_text' => $opt->label,
                            'score' => $opt->nilai_skor,
                            'order' => $opt->order ?: ($idx + 1),
                        ]
                    );
                }
            }
        }

        return redirect()->back()->with('success', 'Pertanyaan kuesioner prodi berhasil ditambahkan ke Bank Soal dan disematkan ke bagian tujuan.');
    }

    /**
     * Memperbarui pertanyaan kuesioner prodi.
     */
    public function updateQuestion(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();
        $master = KuesionerPertanyaan::findOrFail($id);

        $validated = $request->validate([
            'code' => 'required|string|max:50',
            'question_text' => 'required|string',
            'type' => 'required|string|in:text,textarea,number,single_choice,radio,radio_input,radio_text,multiple_choice,checkbox,dropdown,searchable_select,rating_5,multiple_number,matrix,matrix_dual,multiple_textbox,date,time,file,header',
            'is_required' => 'boolean',
            'is_reusable' => 'boolean',
            'prodi_question_section_id' => 'required|exists:kuesioner_section,id',
            'target_section_ids' => 'nullable|array',
            'target_section_ids.*' => 'exists:kuesioner_section,id',
            'order' => 'nullable|integer',
        ]);

        if ($validated['type'] === 'radio') {
            $validated['type'] = 'single_choice';
        }
        if ($validated['type'] === 'checkbox') {
            $validated['type'] = 'multiple_choice';
        }

        $code = Str::upper(trim($validated['code']));
        $sectionId = (int) $validated['prodi_question_section_id'];

        $master->update([
            'kode_pertanyaan' => $code,
            'teks_pertanyaan' => trim($validated['question_text']),
            'tipe' => $validated['type'],
            'is_reusable' => $request->boolean('is_reusable', true),
        ]);

        // Perbarui pivot di section bersangkutan
        $pivot = KuesionerSectionPertanyaan::where('pertanyaan_id', $master->id)
            ->where('section_id', $sectionId)
            ->first();

        if ($pivot) {
            $pivot->update([
                'order' => $validated['order'] ?? $pivot->order,
                'wajib' => $request->boolean('is_required', true),
            ]);
        } else {
            KuesionerSectionPertanyaan::create([
                'section_id' => $sectionId,
                'pertanyaan_id' => $master->id,
                'order' => $validated['order'] ?? 1,
                'wajib' => $request->boolean('is_required', true),
            ]);
        }

        // Perbarui multi-placement section jika disediakan
        if ($request->has('target_section_ids')) {
            $targetIds = array_filter(array_map('intval', (array) $request->target_section_ids));
            $targetIds[] = $sectionId;
            $targetIds = array_unique($targetIds);

            // Tambahkan pivot yang belum ada
            foreach ($targetIds as $tid) {
                KuesionerSectionPertanyaan::firstOrCreate(
                    ['section_id' => $tid, 'pertanyaan_id' => $master->id],
                    ['order' => 99, 'wajib' => $request->boolean('is_required', true)]
                );
            }
        }

        // Mirror update ke legacy jika tabel ada
        if (Schema::hasTable('prodi_question')) {
            ProdiQuestion::where('code', $master->kode_pertanyaan)->update([
                'question_text' => $master->teks_pertanyaan,
                'type' => $master->tipe,
                'is_required' => $request->boolean('is_required', true),
            ]);
        }

        return redirect()->back()->with('success', 'Pertanyaan kuesioner prodi berhasil diperbarui.');
    }

    /**
     * Menghapus butir pertanyaan dari bagian prodi.
     */
    public function destroyQuestion(int $id): RedirectResponse
    {
        $master = KuesionerPertanyaan::with('sections')->findOrFail($id);

        // Lepas pivot dari section
        KuesionerSectionPertanyaan::where('pertanyaan_id', $master->id)->delete();

        // Jika pertanyaan tidak lagi terpasang di manapun dan belum memiliki respon jawaban, hapus master
        if ($master->jawaban()->count() === 0) {
            $master->opsi()->delete();
            $master->delete();
        }

        // Mirror hapus ke legacy jika tabel ada
        if (Schema::hasTable('prodi_question')) {
            $legacy = ProdiQuestion::where('code', $master->kode_pertanyaan)->first();
            if ($legacy) {
                if (Schema::hasTable('prodi_question_option')) {
                    $legacy->options()->delete();
                }
                $legacy->delete();
            }
        }

        return redirect()->back()->with('success', 'Pertanyaan kuesioner berhasil dihapus dari instrumen.');
    }

    /**
     * Mengatur urutan butir pertanyaan dalam section.
     */
    public function reorderQuestion(Request $request): RedirectResponse
    {
        if ($request->has(['id', 'direction'])) {
            $validated = $request->validate([
                'id' => 'required|exists:kuesioner_pertanyaan,id',
                'direction' => 'required|in:up,down',
            ]);

            $master = KuesionerPertanyaan::findOrFail($validated['id']);
            $currentPivot = KuesionerSectionPertanyaan::where('pertanyaan_id', $master->id)->first();

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

            return redirect()->back()->with('success', 'Urutan pertanyaan berhasil dipindahkan.');
        }

        return redirect()->back()->withErrors(['message' => 'Parameter pengurutan tidak valid.']);
    }

    // =========================================================================
    // OPTION PRODI MANAGEMENT (UNIFIED & MIRROR)
    // =========================================================================

    /**
     * Menyimpan opsi pilihan jawaban kuesioner.
     */
    public function storeOption(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'prodi_question_id' => 'required|exists:kuesioner_pertanyaan,id',
            'code' => 'nullable|string|max:50',
            'option_text' => 'required|string',
            'value' => 'nullable|string',
            'jump_to' => 'nullable|string',
            'order' => 'nullable|integer',
        ]);

        $master = KuesionerPertanyaan::findOrFail($validated['prodi_question_id']);

        if (empty($validated['order'])) {
            $maxOrder = KuesionerOpsi::where('pertanyaan_id', $master->id)->max('order') ?? 0;
            $validated['order'] = $maxOrder + 1;
        }

        $opt = KuesionerOpsi::create([
            'pertanyaan_id' => $master->id,
            'label' => trim($validated['option_text']),
            'nilai_skor' => is_numeric($validated['value']) ? (int) $validated['value'] : null,
            'lompat_ke_pertanyaan_id' => $validated['jump_to'] ?: null,
            'order' => $validated['order'],
        ]);

        // Mirror ke legacy jika tabel ada
        if (Schema::hasTable('prodi_question') && Schema::hasTable('prodi_question_option')) {
            $legacyQ = ProdiQuestion::where('code', $master->kode_pertanyaan)->first();
            if ($legacyQ) {
                ProdiQuestionOption::create([
                    'prodi_question_id' => $legacyQ->id,
                    'code' => $validated['code'] ?: ($master->kode_pertanyaan.'-'.str_pad((string) $opt->order, 2, '0', STR_PAD_LEFT)),
                    'option_text' => $opt->label,
                    'score' => $opt->nilai_skor,
                    'jump_to' => $opt->lompat_ke_pertanyaan_id,
                    'order' => $opt->order,
                ]);
            }
        }

        return redirect()->back()->with('success', 'Opsi jawaban kuesioner berhasil ditambahkan.');
    }

    /**
     * Memperbarui opsi pilihan jawaban kuesioner.
     */
    public function updateOption(Request $request, int $id): RedirectResponse
    {
        $option = KuesionerOpsi::with('pertanyaan')->findOrFail($id);

        $validated = $request->validate([
            'option_text' => 'required|string',
            'value' => 'nullable|string',
            'jump_to' => 'nullable|string',
            'order' => 'nullable|integer',
        ]);

        $oldLabel = $option->label;

        $option->update([
            'label' => trim($validated['option_text']),
            'nilai_skor' => is_numeric($validated['value']) ? (int) $validated['value'] : null,
            'lompat_ke_pertanyaan_id' => $validated['jump_to'] ?: null,
            'order' => $validated['order'] ?? $option->order,
        ]);

        // Mirror ke legacy jika tabel ada
        if (Schema::hasTable('prodi_question') && Schema::hasTable('prodi_question_option')) {
            $legacyQ = ProdiQuestion::where('code', $option->pertanyaan->kode_pertanyaan)->first();
            if ($legacyQ) {
                ProdiQuestionOption::where('prodi_question_id', $legacyQ->id)
                    ->where('option_text', $oldLabel)
                    ->update([
                        'option_text' => $option->label,
                        'score' => $option->nilai_skor,
                        'jump_to' => $option->lompat_ke_pertanyaan_id,
                    ]);
            }
        }

        return redirect()->back()->with('success', 'Opsi jawaban kuesioner berhasil diperbarui.');
    }

    /**
     * Menghapus opsi pilihan jawaban kuesioner.
     */
    public function destroyOption(int $id): RedirectResponse
    {
        $option = KuesionerOpsi::with('pertanyaan')->findOrFail($id);
        $oldLabel = $option->label;
        $qCode = $option->pertanyaan->kode_pertanyaan;

        $option->delete();

        // Mirror ke legacy jika tabel ada
        if (Schema::hasTable('prodi_question') && Schema::hasTable('prodi_question_option')) {
            $legacyQ = ProdiQuestion::where('code', $qCode)->first();
            if ($legacyQ) {
                ProdiQuestionOption::where('prodi_question_id', $legacyQ->id)
                    ->where('option_text', $oldLabel)
                    ->delete();
            }
        }

        return redirect()->back()->with('success', 'Opsi jawaban kuesioner berhasil dihapus.');
    }

    /**
     * Menyematkan butir pertanyaan dari Bank Soal ke bagian (section) kuesioner prodi tujuan.
     */
    public function attachFromBank(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $validated = $request->validate([
            'prodi_id' => 'required|exists:prodi,id',
            'section_id' => 'required|exists:kuesioner_section,id',
            'pertanyaan_ids' => 'required|array|min:1',
            'pertanyaan_ids.*' => 'required|exists:kuesioner_pertanyaan,id',
        ]);

        $this->ensureProdiAccess($user, (int) $validated['prodi_id']);

        $prodiId = (int) $validated['prodi_id'];
        $sectionId = (int) $validated['section_id'];

        $selectedQuestions = KuesionerPertanyaan::with('opsi')
            ->whereIn('id', $validated['pertanyaan_ids'])
            ->get();

        $maxOrder = (int) (KuesionerSectionPertanyaan::where('section_id', $sectionId)->max('order') ?: 0);
        $addedCount = 0;

        foreach ($selectedQuestions as $bankQuestion) {
            $maxOrder++;

            // Buat relasi pivot di kuesioner_section_pertanyaan
            $created = KuesionerSectionPertanyaan::firstOrCreate(
                [
                    'section_id' => $sectionId,
                    'pertanyaan_id' => $bankQuestion->id,
                ],
                [
                    'order' => $maxOrder,
                    'wajib' => true,
                ]
            );

            if ($created->wasRecentlyCreated) {
                $addedCount++;
            }

            // Mirror ke legacy prodi_question jika tabel masih aktif
            if (Schema::hasTable('prodi_question')) {
                $existingLegacy = ProdiQuestion::where('prodi_id', $prodiId)
                    ->where('code', $bankQuestion->kode_pertanyaan)
                    ->first();

                if (! $existingLegacy && Schema::hasTable('prodi_question_section')) {
                    $legacySec = ProdiQuestionSection::firstOrCreate(
                        ['prodi_id' => $prodiId],
                        ['title' => 'Bagian Kuesioner Program Studi', 'order' => 1]
                    );
                    $prodiQ = ProdiQuestion::create([
                        'prodi_id' => $prodiId,
                        'prodi_question_section_id' => $legacySec->id,
                        'code' => $bankQuestion->kode_pertanyaan,
                        'question_text' => $bankQuestion->teks_pertanyaan,
                        'type' => $bankQuestion->tipe,
                        'is_required' => true,
                        'order' => $maxOrder,
                    ]);

                    if (Schema::hasTable('prodi_question_option')) {
                        foreach ($bankQuestion->opsi as $idx => $opt) {
                            ProdiQuestionOption::create([
                                'prodi_question_id' => $prodiQ->id,
                                'code' => $bankQuestion->kode_pertanyaan.'-'.str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT),
                                'option_text' => $opt->label,
                                'score' => $opt->nilai_skor,
                                'order' => (int) ($opt->order ?: ($idx + 1)),
                            ]);
                        }
                    }
                }
            }
        }

        return redirect()->back()->with('success', $addedCount.' butir pertanyaan dari Bank Soal berhasil disematkan ke bagian kuesioner.');
    }
}
