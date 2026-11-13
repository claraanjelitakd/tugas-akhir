<?php

namespace App\Http\Controllers\Shared\Kuesioner;

use App\Http\Controllers\Controller;
use App\Models\KuesionerOpsi;
use App\Models\KuesionerPertanyaan;
use App\Models\KuesionerSection;
use App\Models\KuesionerSectionPertanyaan;
use App\Models\LogActivity;
use App\Models\Prodi;
use App\Models\User;
use App\Services\Kuesioner\QuestionDefaultOptionsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller bersama Pengaturan Kuesioner Program Studi (Unified Survey Engine).
 *
 * Mengelola instrumen pertanyaan tingkat Program Studi menggunakan skema terpadu:
 * - kuesioner_section
 * - kuesioner_pertanyaan
 * - kuesioner_section_pertanyaan (rekaman penempatan & multi-placement)
 * - kuesioner_opsi
 *
 * Seluruh operasi berjalan pada skema kuesioner terpadu tanpa menulis ke tabel legacy.
 */
class KelolaKuesionerProdiController extends Controller
{
    /**
     * Mengambil akun terautentikasi dengan tipe model User yang eksplisit.
     *
     * @return User Akun yang sedang menjalankan request.
     */
    private function authenticatedUser(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 401, 'Pengguna belum terautentikasi.');

        return $user;
    }

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

    /**
     * Memastikan pertanyaan hanya dapat diubah oleh prodi yang tercatat sebagai pemilik master.
     */
    private function ensureQuestionOwner(User $user, KuesionerPertanyaan $question, int $prodiId): void
    {
        $this->ensureProdiAccess($user, $prodiId);

        if ($user->role === 'superadmin') {
            return;
        }

        abort_unless(
            (int) $question->pemilik_prodi_id === $prodiId,
            403,
            'Pertanyaan ini berasal dari sumber lain dan hanya dapat digunakan, bukan diubah.'
        );
    }

    /**
     * Memastikan section tujuan berada pada prodi yang sedang dikelola akun.
     */
    private function findAccessibleProdiSection(User $user, int $prodiId, int $sectionId): KuesionerSection
    {
        $this->ensureProdiAccess($user, $prodiId);

        return KuesionerSection::whereKey($sectionId)
            ->where('target', 'prodi')
            ->where('prodi_id', $prodiId)
            ->firstOrFail();
    }

    /**
     * Memastikan operasi opsi hanya berjalan pada master milik prodi pemiliknya.
     */
    private function ensureOwnedQuestionForOption(User $user, KuesionerPertanyaan $question): void
    {
        if ($user->role === 'superadmin') {
            return;
        }

        abort_unless($question->pemilik_prodi_id !== null, 403, 'Opsi pertanyaan umum dikelola dari sumber kanoniknya.');

        $this->ensureQuestionOwner($user, $question, (int) $question->pemilik_prodi_id);
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
        $user = $this->authenticatedUser();
        $user->load(['fakultas', 'prodi']);

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

        // Jika belum ada section di kuesioner_section, buatkan default
        if ($selectedProdiId && $rawSections->isEmpty()) {
            KuesionerSection::create([
                'target' => 'prodi',
                'prodi_id' => $selectedProdiId,
                'kode_section' => 'PRODI_'.$selectedProdiId.'_SEC1',
                'judul' => 'Bagian Evaluasi & Relevansi Program Studi',
                'deskripsi' => 'Kelompok pertanyaan kuesioner khusus Program Studi '.($selectedProdi?->nama_prodi ?? ''),
                'order' => 1,
                'is_active' => true,
            ]);

            $rawSections = KuesionerSection::where('target', 'prodi')
                ->where('prodi_id', $selectedProdiId)
                ->withCount('sectionPertanyaan')
                ->orderBy('order', 'asc')
                ->get();
        }

        $sectionIds = $rawSections->pluck('id')->all();
        $selectedSectionId = (int) $request->query('section_id', 0);
        if (! in_array($selectedSectionId, $sectionIds, true)) {
            $selectedSectionId = null;
        }

        // Ambil butir pertanyaan yang terhubung ke section-section prodi ini via kuesioner_section_pertanyaan
        $placementItems = KuesionerSectionPertanyaan::whereIn('section_id', $sectionIds)
            ->with([
                'pertanyaan.opsi' => fn ($q) => $q->orderBy('order'),
                'pertanyaan.pemilikProdi:id,nama_prodi,kode_prodi',
                'section:id,judul,order',
            ])
            ->orderBy('order', 'asc')
            ->get();

        $placementSectionIdsByQuestion = $placementItems
            ->groupBy('pertanyaan_id')
            ->map(fn ($placements) => $placements
                ->pluck('section_id')
                ->map(fn ($sectionId) => (string) $sectionId)
                ->values()
                ->all());

        $questions = $placementItems->map(function (KuesionerSectionPertanyaan $placement) use ($selectedProdiId, $user, $placementSectionIdsByQuestion) {
            $master = $placement->pertanyaan;

            return [
                'id' => $master->id,
                'placement_id' => $placement->id,
                'code' => $master->kode_pertanyaan,
                'label' => $master->label_pertanyaan,
                'question_text' => $master->teks_pertanyaan,
                'type' => $master->tipe,
                'order' => $placement->order,
                'is_required' => (bool) $placement->wajib,
                'tampil_di' => $placement->tampil_di ?: 'kuesioner',
                'is_active' => (bool) $master->is_active,
                'is_placement_active' => (bool) $placement->is_active,
                'is_reusable' => (bool) $master->is_reusable,
                'pemilik_prodi_id' => $master->pemilik_prodi_id,
                'can_edit' => $user->role === 'superadmin'
                    || (int) $master->pemilik_prodi_id === $selectedProdiId,
                'section_id' => $placement->section_id,
                'section' => [
                    'id' => $placement->section_id,
                    'title' => $placement->section?->judul,
                ],
                'target_section_ids' => collect($placementSectionIdsByQuestion->get($master->id, []))
                    ->reject(fn (string $sectionId): bool => (int) $sectionId === (int) $placement->section_id)
                    ->values()
                    ->all(),
                'asal' => str_starts_with($master->kode_pertanyaan, 'EVA_')
                    ? 'Evaluasi Atasan'
                    : ($master->pemilikProdi ? $master->pemilikProdi->nama_prodi : 'Umum / Universitas'),
                'options' => $master->opsi->map(fn (KuesionerOpsi $opt) => [
                    'id' => $opt->id,
                    'code' => $opt->kode_opsi ?: $master->kode_pertanyaan.'-'.str_pad((string) $opt->order, 2, '0', STR_PAD_LEFT),
                    'option_text' => $opt->label,
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
            'questions_count' => $questions->where('section_id', $s->id)->count(),
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
                    'label' => $p->label_pertanyaan,
                    'teks' => $p->teks_pertanyaan,
                    'tipe' => $p->tipe,
                    'asal' => $asal,
                    'is_already_added' => in_array($p->kode_pertanyaan, $existingCodes, true),
                    'options' => $p->opsi->map(fn ($o) => [
                        'label' => $o->label,
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();

        // Target lompatan ditampilkan bersama kode dan teks agar admin dapat mengenali pertanyaan tujuan.
        $availableJumpTargets = $questions
            ->map(fn (array $question): array => [
                'code' => $question['code'],
                'label' => $question['label'] ?: Str::limit($question['question_text'], 65),
                'text' => $question['question_text'],
                'section' => $question['section']['title'] ?? null,
            ])
            ->filter(fn (array $target): bool => ! empty($target['code']))
            ->unique('code')
            ->values()
            ->all();

        return Inertia::render('Shared/Kuesioner/ProdiKuesionerIndex', [
            'user' => $user,
            'role' => $user->role,
            'basePath' => $this->basePath($user),
            'dashboardPath' => $this->dashboardPath($user),
            'fakultas' => $user->fakultas,
            'prodis' => $prodis,
            'selectedProdi' => $selectedProdi,
            'selectedSectionId' => $selectedSectionId,
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

        $user = $this->authenticatedUser();
        $prodiId = (int) $validated['prodi_id'];
        $this->ensureProdiAccess($user, $prodiId);

        if (empty($validated['order'])) {
            $maxOrder = KuesionerSection::where('target', 'prodi')->where('prodi_id', $prodiId)->max('order') ?? 0;
            $validated['order'] = $maxOrder + 1;
        }

        // Simpan ke skema terpadu kuesioner_section
        $code = 'PRODI_'.$prodiId.'_'.Str::upper(Str::slug(Str::limit($validated['title'], 15, ''))).'_'.time();
        $section = KuesionerSection::create([
            'target' => 'prodi',
            'prodi_id' => $prodiId,
            'kode_section' => $code,
            'judul' => $validated['title'],
            'deskripsi' => $validated['description'] ?? null,
            'order' => $validated['order'],
            'is_active' => true,
        ]);

        LogActivity::record('KUESIONER_PRODI_SECTION_CREATED', 'Membuat section kuesioner prodi: '.$section->judul.'.', $section, null, $section->toArray());

        return redirect()->back()->with('success', 'Bagian (section) kuesioner prodi berhasil ditambahkan.');
    }

    /**
     * Memperbarui section kuesioner prodi.
     */
    public function updateSection(Request $request, int $id): RedirectResponse
    {
        $user = $this->authenticatedUser();

        $section = KuesionerSection::findOrFail($id);
        $this->ensureProdiAccess($user, (int) $section->prodi_id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'order' => 'nullable|integer',
        ]);

        $section->update([
            'judul' => $validated['title'],
            'deskripsi' => $validated['description'] ?? null,
            'order' => $validated['order'] ?? $section->order,
        ]);

        LogActivity::record('KUESIONER_PRODI_SECTION_UPDATED', 'Memperbarui section kuesioner prodi: '.$section->judul.'.', $section, null, $validated);

        return redirect()->back()->with('success', 'Bagian (section) kuesioner prodi berhasil diperbarui.');
    }

    /**
     * Menghapus section kuesioner prodi.
     */
    public function destroySection(int $id): RedirectResponse
    {
        $user = $this->authenticatedUser();

        $section = KuesionerSection::with('sectionPertanyaan')->findOrFail($id);
        $this->ensureProdiAccess($user, (int) $section->prodi_id);

        // Hapus relasi penempatan di section ini
        $section->sectionPertanyaan()->delete();
        $section->delete();

        LogActivity::record('KUESIONER_PRODI_SECTION_DELETED', 'Menghapus section kuesioner prodi: '.$section->judul.'.', null, $section->toArray());

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

            $user = $this->authenticatedUser();
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
    public function toggleActive(Request $request, int $questionId): RedirectResponse
    {
        $validated = $request->validate([
            'prodi_id' => 'nullable|exists:prodi,id',
        ]);

        $user = $this->authenticatedUser();
        $question = KuesionerPertanyaan::findOrFail($questionId);
        $prodiId = (int) ($validated['prodi_id'] ?? $question->pemilik_prodi_id ?? 0);
        $this->ensureQuestionOwner($user, $question, $prodiId);

        $question->is_active = ! $question->is_active;
        $question->save();

        $statusText = $question->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()->back()->with('success', "Pertanyaan [{$question->kode_pertanyaan}] berhasil {$statusText}.");
    }

    /**
     * Mengubah status tampil pertanyaan hanya pada placement section yang sedang dikelola.
     */
    public function togglePlacementActive(Request $request, int $placementId): RedirectResponse
    {
        $validated = $request->validate([
            'prodi_id' => 'required|exists:prodi,id',
        ]);

        $user = $this->authenticatedUser();
        $placement = KuesionerSectionPertanyaan::with(['section', 'pertanyaan'])->findOrFail($placementId);
        $prodiId = (int) $validated['prodi_id'];

        $this->ensureProdiAccess($user, $prodiId);
        abort_unless(
            (int) $placement->section?->prodi_id === $prodiId,
            403,
            'Placement pertanyaan tidak berada dalam lingkup program studi ini.'
        );

        $placement->update(['is_active' => ! $placement->is_active]);
        $statusText = $placement->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()->back()->with('success', "Pertanyaan [{$placement->pertanyaan?->kode_pertanyaan}] berhasil {$statusText} pada section ini.");
    }

    /**
     * Menyimpan butir pertanyaan kuesioner baru ke Bank Soal terpadu dan menempatkannya ke section.
     */
    public function storeQuestion(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'prodi_id' => 'required|exists:prodi,id',
            'code' => 'required|string|max:50',
            'label' => 'nullable|string|max:255',
            'question_text' => 'required|string',
            'type' => 'required|string|in:text,textarea,number,single_choice,radio,radio_input,radio_text,multiple_choice,checkbox,multiple_choice_text,dropdown,searchable_select,rating_5,multiple_number,matrix,matrix_dual,multiple_textbox,date,time,file,header',
            'is_required' => 'boolean',
            'is_reusable' => 'boolean',
            'tampil_di' => 'nullable|string|in:kuesioner,profil,profile,both,hidden,none',
            'section_id' => 'nullable',
            'target_section_ids' => 'nullable|array',
            'order' => 'nullable|integer',
            'options' => 'nullable|array',
            'options.*.code' => 'nullable|string|max:50|distinct',
            'options.*.label' => 'nullable|string',
            'options.*.jump_to' => 'nullable|string|max:50',
        ]);

        $user = $this->authenticatedUser();
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
        $rawSectionId = $validated['section_id'] ?? null;
        $unifiedSection = $rawSectionId
            ? $this->findAccessibleProdiSection($user, $prodiId, (int) $rawSectionId)
            : null;

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
        $canManageMaster = false;

        if (! $master) {
            $master = KuesionerPertanyaan::create([
                'kode_pertanyaan' => $code,
                'label_pertanyaan' => filled($validated['label'] ?? null) ? trim($validated['label']) : null,
                'teks_pertanyaan' => trim($validated['question_text']),
                'tipe' => $validated['type'],
                'pemilik_prodi_id' => $prodiId,
                'is_reusable' => $request->boolean('is_reusable', true),
                'is_active' => true,
            ]);
            $canManageMaster = true;
        } elseif ((int) $master->pemilik_prodi_id === $prodiId) {
            // Pemilik boleh memperbarui master miliknya saat memakai kembali kode yang sama.
            $master->update([
                'teks_pertanyaan' => trim($validated['question_text']),
                'label_pertanyaan' => filled($validated['label'] ?? null) ? trim($validated['label']) : null,
                'tipe' => $validated['type'],
                'is_reusable' => $request->boolean('is_reusable', true),
            ]);
            $canManageMaster = true;
        } elseif (! $master->is_reusable || ! $master->is_active) {
            abort(403, 'Pertanyaan dari sumber lain tidak tersedia untuk digunakan kembali.');
        }

        $order = $validated['order'] ?? null;
        if (empty($order)) {
            $maxOrder = KuesionerSectionPertanyaan::where('section_id', $sectionId)->max('order') ?? 0;
            $order = $maxOrder + 1;
        }

        // Pasang relasi penempatan di section utama
        KuesionerSectionPertanyaan::updateOrCreate(
            [
                'section_id' => $sectionId,
                'pertanyaan_id' => $master->id,
            ],
            [
                'order' => $order,
                'wajib' => $request->boolean('is_required', true),
                'is_active' => true,
                'tampil_di' => $validated['tampil_di'] ?? 'kuesioner',
            ]
        );

        // Pasang relasi penempatan di section lain jika dipilih (multi-placement)
        if (! empty($validated['target_section_ids'])) {
            foreach ($validated['target_section_ids'] as $extraSecId) {
                if ((int) $extraSecId !== $sectionId) {
                    $extraSection = $user->role === 'superadmin'
                        ? KuesionerSection::whereKey((int) $extraSecId)->where('target', 'prodi')->firstOrFail()
                        : $this->findAccessibleProdiSection($user, $prodiId, (int) $extraSecId);
                    $extraOrder = (KuesionerSectionPertanyaan::where('section_id', $extraSecId)->max('order') ?? 0) + 1;
                    KuesionerSectionPertanyaan::firstOrCreate(
                        [
                            'section_id' => $extraSection->id,
                            'pertanyaan_id' => $master->id,
                        ],
                        [
                            'order' => $extraOrder,
                            'wajib' => $request->boolean('is_required', true),
                            'is_active' => true,
                            'tampil_di' => $validated['tampil_di'] ?? 'kuesioner',
                        ]
                    );
                }
            }
        }

        // Opsi hanya boleh dikelola saat master baru dibuat atau milik prodi tujuan.
        if ($canManageMaster) {
            if ($request->has('options') && is_array($request->options) && count($request->options) > 0) {
                $orderCounter = 1;
                foreach ($request->options as $opt) {
                    $labelText = is_array($opt) ? ($opt['option_text'] ?? $opt['label'] ?? '') : (string) $opt;
                    $optionCode = is_array($opt) ? trim((string) ($opt['code'] ?? $opt['kode_opsi'] ?? '')) : '';
                    $trimmed = trim((string) $labelText);

                    if ($trimmed !== '') {
                        $optionOrder = $orderCounter++;
                        KuesionerOpsi::create([
                            'pertanyaan_id' => $master->id,
                            'kode_opsi' => $optionCode !== '' ? $optionCode : $master->kode_pertanyaan.'-'.str_pad((string) $optionOrder, 2, '0', STR_PAD_LEFT),
                            'label' => $trimmed,
                            'nilai_skor' => $master->tipe === 'rating_5' ? $optionOrder : null,
                            'lompat_ke_pertanyaan_id' => is_array($opt) && filled($opt['jump_to'] ?? null) ? trim((string) $opt['jump_to']) : null,
                            'order' => $optionOrder,
                        ]);
                    }
                }
            } else {
                QuestionDefaultOptionsService::generateForUnifiedQuestion($master);
            }
        }

        LogActivity::record('KUESIONER_PRODI_QUESTION_CREATED', 'Membuat dan menempatkan pertanyaan kuesioner prodi: '.$master->kode_pertanyaan.'.', $master, null, $validated);

        return redirect()->back()->with('success', 'Pertanyaan kuesioner prodi berhasil ditambahkan ke Bank Soal dan disematkan ke bagian tujuan.');
    }

    /**
     * Memperbarui pertanyaan kuesioner prodi.
     */
    public function updateQuestion(Request $request, int $questionId): RedirectResponse
    {
        $user = $this->authenticatedUser();
        $master = KuesionerPertanyaan::findOrFail($questionId);

        $validated = $request->validate([
            'prodi_id' => 'required|exists:prodi,id',
            'code' => 'required|string|max:50',
            'label' => 'nullable|string|max:255',
            'question_text' => 'required|string',
            'type' => 'required|string|in:text,textarea,number,single_choice,radio,radio_input,radio_text,multiple_choice,checkbox,multiple_choice_text,dropdown,searchable_select,rating_5,multiple_number,matrix,matrix_dual,multiple_textbox,date,time,file,header',
            'is_required' => 'boolean',
            'is_reusable' => 'boolean',
            'tampil_di' => 'nullable|string|in:kuesioner,profil,profile,both,hidden,none',
            'section_id' => 'required|exists:kuesioner_section,id',
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
        $prodiId = (int) $validated['prodi_id'];
        $sectionId = (int) $validated['section_id'];
        $this->ensureQuestionOwner($user, $master, $prodiId);
        $this->findAccessibleProdiSection($user, $prodiId, $sectionId);

        $master->update([
            'kode_pertanyaan' => $code,
            'label_pertanyaan' => filled($validated['label'] ?? null) ? trim($validated['label']) : null,
            'teks_pertanyaan' => trim($validated['question_text']),
            'tipe' => $validated['type'],
            'is_reusable' => $request->boolean('is_reusable', true),
        ]);

        // Perbarui relasi penempatan di section bersangkutan
        $placement = KuesionerSectionPertanyaan::where('pertanyaan_id', $master->id)
            ->where('section_id', $sectionId)
            ->first();

        if ($placement) {
            $placement->update([
                'order' => $validated['order'] ?? $placement->order,
                'wajib' => $request->boolean('is_required', true),
                'tampil_di' => $validated['tampil_di'] ?? $placement->tampil_di ?? 'kuesioner',
            ]);
        } else {
            KuesionerSectionPertanyaan::create([
                'section_id' => $sectionId,
                'pertanyaan_id' => $master->id,
                'order' => $validated['order'] ?? 1,
                'wajib' => $request->boolean('is_required', true),
                'is_active' => true,
                'tampil_di' => $validated['tampil_di'] ?? 'kuesioner',
            ]);
        }

        // Perbarui multi-placement section jika disediakan
        if ($request->has('target_section_ids')) {
            $targetIds = array_filter(array_map('intval', (array) $request->target_section_ids));
            $targetIds[] = $sectionId;
            $targetIds = array_unique($targetIds);

            foreach ($targetIds as $tid) {
                $this->findAccessibleProdiSection($user, $prodiId, $tid);
            }

            $ownedSectionIds = KuesionerSection::where('target', 'prodi')
                ->where('prodi_id', $prodiId)
                ->pluck('id');

            KuesionerSectionPertanyaan::where('pertanyaan_id', $master->id)
                ->whereIn('section_id', $ownedSectionIds)
                ->whereNotIn('section_id', $targetIds)
                ->delete();

            // Tambahkan relasi penempatan yang belum ada
            foreach ($targetIds as $tid) {
                KuesionerSectionPertanyaan::firstOrCreate(
                    ['section_id' => $tid, 'pertanyaan_id' => $master->id],
                    [
                        'order' => 99,
                        'wajib' => $request->boolean('is_required', true),
                        'is_active' => true,
                        'tampil_di' => $validated['tampil_di'] ?? 'kuesioner',
                    ]
                );
            }
        }

        LogActivity::record('KUESIONER_PRODI_QUESTION_UPDATED', 'Memperbarui pertanyaan kuesioner prodi: '.$master->kode_pertanyaan.'.', $master, null, $validated);

        return redirect()->back()->with('success', 'Pertanyaan kuesioner prodi berhasil diperbarui.');
    }

    /**
     * Menghapus satu placement pertanyaan dari section prodi.
     */
    public function destroyQuestion(Request $request, int $placementId): RedirectResponse
    {
        $validated = $request->validate([
            'prodi_id' => 'required|exists:prodi,id',
        ]);

        $user = $this->authenticatedUser();
        $prodiId = (int) $validated['prodi_id'];
        $this->ensureProdiAccess($user, $prodiId);
        $placement = KuesionerSectionPertanyaan::with('pertanyaan')->findOrFail($placementId);
        abort_unless(
            (int) $placement->section()->value('prodi_id') === $prodiId,
            403,
            'Placement pertanyaan tidak berada dalam lingkup program studi ini.'
        );
        $master = $placement->pertanyaan;

        // Lepas hanya placement pada section yang dipilih, bukan seluruh penggunaan lintas section/prodi.
        $placement->delete();

        // Master milik sendiri baru boleh dibersihkan jika sudah tidak dipakai dan belum memiliki jawaban.
        if ((int) $master->pemilik_prodi_id === $prodiId
            && ! KuesionerSectionPertanyaan::where('pertanyaan_id', $master->id)->exists()
            && $master->jawaban()->count() === 0) {
            $master->opsi()->delete();
            $master->delete();
        }

        LogActivity::record('KUESIONER_PRODI_QUESTION_DETACHED', 'Melepas pertanyaan kuesioner prodi dari section.', $master, null, [
            'placement_id' => $placementId,
            'prodi_id' => $prodiId,
        ]);

        return redirect()->back()->with('success', 'Pertanyaan kuesioner berhasil dihapus dari instrumen.');
    }

    /**
     * Mengatur urutan butir pertanyaan dalam section.
     */
    public function reorderQuestion(Request $request): RedirectResponse
    {
        if ($request->has(['placement_id', 'direction'])) {
            $validated = $request->validate([
                'prodi_id' => 'required|exists:prodi,id',
                'placement_id' => 'required|exists:kuesioner_section_pertanyaan,id',
                'direction' => 'required|in:up,down',
            ]);

            $user = $this->authenticatedUser();
            $prodiId = (int) $validated['prodi_id'];
            $this->ensureProdiAccess($user, $prodiId);
            $currentPlacement = KuesionerSectionPertanyaan::with('section')->findOrFail($validated['placement_id']);
            abort_unless(
                (int) $currentPlacement->section?->prodi_id === $prodiId,
                403,
                'Placement pertanyaan tidak berada dalam lingkup program studi ini.'
            );

            if ($currentPlacement) {
                $operator = $validated['direction'] === 'up' ? '<' : '>';
                $sortOrder = $validated['direction'] === 'up' ? 'desc' : 'asc';

                $adjacentPlacement = KuesionerSectionPertanyaan::where('section_id', $currentPlacement->section_id)
                    ->where('order', $operator, $currentPlacement->order)
                    ->orderBy('order', $sortOrder)
                    ->first();

                if ($adjacentPlacement) {
                    $tempOrder = $currentPlacement->order;
                    $currentPlacement->update(['order' => $adjacentPlacement->order]);
                    $adjacentPlacement->update(['order' => $tempOrder]);
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
            'question_id' => 'required|exists:kuesioner_pertanyaan,id',
            'code' => 'nullable|string|max:50',
            'option_text' => 'required|string',
            'jump_to' => 'nullable|string',
            'order' => 'nullable|integer',
        ]);

        $master = KuesionerPertanyaan::findOrFail($validated['question_id']);
        $this->ensureOwnedQuestionForOption($this->authenticatedUser(), $master);

        if (empty($validated['order'])) {
            $maxOrder = KuesionerOpsi::where('pertanyaan_id', $master->id)->max('order') ?? 0;
            $validated['order'] = $maxOrder + 1;
        }

        $opt = KuesionerOpsi::create([
            'pertanyaan_id' => $master->id,
            'kode_opsi' => trim((string) ($validated['code'] ?? '')) ?: $master->kode_pertanyaan.'-'.str_pad((string) $validated['order'], 2, '0', STR_PAD_LEFT),
            'label' => trim($validated['option_text']),
            'nilai_skor' => $master->tipe === 'rating_5' ? (int) $validated['order'] : null,
            'lompat_ke_pertanyaan_id' => $validated['jump_to'] ?? null,
            'order' => $validated['order'],
        ]);

        LogActivity::record('KUESIONER_PRODI_OPTION_CREATED', 'Menambahkan opsi jawaban pada pertanyaan '.$master->kode_pertanyaan.'.', $opt, null, $validated);

        return redirect()->back()->with('success', 'Opsi jawaban kuesioner berhasil ditambahkan.');
    }

    /**
     * Memperbarui opsi pilihan jawaban kuesioner.
     */
    public function updateOption(Request $request, int $id): RedirectResponse
    {
        $option = KuesionerOpsi::with('pertanyaan')->findOrFail($id);
        $this->ensureOwnedQuestionForOption($this->authenticatedUser(), $option->pertanyaan);

        $validated = $request->validate([
            'code' => 'nullable|string|max:50',
            'option_text' => 'required|string',
            'jump_to' => 'nullable|string',
            'order' => 'nullable|integer',
        ]);

        $order = $validated['order'] ?? $option->order;

        $option->update([
            'kode_opsi' => trim((string) ($validated['code'] ?? $option->kode_opsi)) ?: $option->kode_opsi,
            'label' => trim($validated['option_text']),
            'nilai_skor' => $option->pertanyaan?->tipe === 'rating_5' ? (int) $order : null,
            'lompat_ke_pertanyaan_id' => $validated['jump_to'] ?? null,
            'order' => $order,
        ]);

        LogActivity::record('KUESIONER_PRODI_OPTION_UPDATED', 'Memperbarui opsi jawaban pada pertanyaan '.($option->pertanyaan?->kode_pertanyaan ?? '-').'.', $option, null, $validated);

        return redirect()->back()->with('success', 'Opsi jawaban kuesioner berhasil diperbarui.');
    }

    /**
     * Menghapus opsi pilihan jawaban kuesioner.
     */
    public function destroyOption(int $id): RedirectResponse
    {
        $option = KuesionerOpsi::with('pertanyaan')->findOrFail($id);
        $this->ensureOwnedQuestionForOption($this->authenticatedUser(), $option->pertanyaan);
        $questionCode = $option->pertanyaan?->kode_pertanyaan;
        $option->delete();

        LogActivity::record('KUESIONER_PRODI_OPTION_DELETED', 'Menghapus opsi jawaban pada pertanyaan '.$questionCode.'.', null, $option->toArray());

        return redirect()->back()->with('success', 'Opsi jawaban kuesioner berhasil dihapus.');
    }

    /**
     * Menyematkan butir pertanyaan dari Bank Soal ke bagian (section) kuesioner prodi tujuan.
     */
    public function attachFromBank(Request $request): RedirectResponse
    {
        $user = $this->authenticatedUser();
        $validated = $request->validate([
            'prodi_id' => 'required|exists:prodi,id',
            'section_id' => 'required|exists:kuesioner_section,id',
            'pertanyaan_ids' => 'required|array|min:1',
            'pertanyaan_ids.*' => 'required|exists:kuesioner_pertanyaan,id',
        ]);

        $this->ensureProdiAccess($user, (int) $validated['prodi_id']);

        $prodiId = (int) $validated['prodi_id'];
        $sectionId = (int) $validated['section_id'];
        $this->findAccessibleProdiSection($user, $prodiId, $sectionId);

        $selectedQuestions = KuesionerPertanyaan::with('opsi')
            ->whereIn('id', $validated['pertanyaan_ids'])
            ->where('is_active', true)
            ->where('is_reusable', true)
            ->get();

        $maxOrder = (int) (KuesionerSectionPertanyaan::where('section_id', $sectionId)->max('order') ?: 0);
        $addedCount = 0;

        foreach ($selectedQuestions as $bankQuestion) {
            $placement = KuesionerSectionPertanyaan::where('section_id', $sectionId)
                ->where('pertanyaan_id', $bankQuestion->id)
                ->first();

            if ($placement) {
                // Jika relasi penempatan sudah ada sebelumnya (misal sempat dinonaktifkan), aktifkan kembali
                $placement->update([
                    'is_active' => true,
                ]);
            } else {
                $maxOrder++;
                KuesionerSectionPertanyaan::create([
                    'section_id' => $sectionId,
                    'pertanyaan_id' => $bankQuestion->id,
                    'order' => $maxOrder,
                    'wajib' => true,
                    'is_active' => true,
                ]);
            }

            $addedCount++;
        }

        LogActivity::record('KUESIONER_PRODI_BANK_ATTACHED', 'Menyematkan '.$addedCount.' pertanyaan dari Bank Soal ke section kuesioner prodi.', null, null, [
            'section_id' => $sectionId,
            'prodi_id' => $prodiId,
            'pertanyaan_ids' => $validated['pertanyaan_ids'],
        ], KuesionerSectionPertanyaan::class, $sectionId);

        return redirect()->back()->with('success', $addedCount.' butir pertanyaan dari Bank Soal berhasil disematkan ke bagian kuesioner.');
    }
}
