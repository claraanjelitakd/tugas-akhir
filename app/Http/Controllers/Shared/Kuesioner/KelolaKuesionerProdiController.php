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
use Illuminate\Support\Facades\DB;
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
            ->when($user->role === 'admin_prodi', fn (Builder $query) => $query->whereKey($user->kode_prodi))
            ->when($user->role === 'admin_fakultas', fn (Builder $query) => $query->where('kode_fakultas', $user->kode_fakultas ?? null));
    }

    /**
     * Memastikan Prodi berada dalam lingkup akses akun.
     */
    private function ensureProdiAccess(User $user, string $prodiId): void
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
    private function ensureQuestionOwner(User $user, KuesionerPertanyaan $question, string $prodiId): void
    {
        $this->ensureProdiAccess($user, $prodiId);

        if ($user->role === 'superadmin') {
            return;
        }

        abort_unless(
            $question->pemilik_kode_prodi === $prodiId,
            403,
            'Pertanyaan ini berasal dari sumber lain dan hanya dapat digunakan, bukan diubah.'
        );
    }

    /**
     * Memastikan section tujuan berada pada prodi yang sedang dikelola akun.
     */
    private function findAccessibleProdiSection(User $user, string $prodiId, int $sectionId): KuesionerSection
    {
        $this->ensureProdiAccess($user, $prodiId);

        return KuesionerSection::whereKey($sectionId)
            ->where('target', 'prodi')
            ->where('kode_prodi', $prodiId)
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

        abort_unless($question->pemilik_kode_prodi !== null, 403, 'Opsi pertanyaan umum dikelola dari sumber kanoniknya.');

        $this->ensureQuestionOwner($user, $question, $question->pemilik_kode_prodi);
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
            ->select('kode_prodi', 'nama_prodi', 'kode_fakultas')
            ->with('fakultas:kode_fakultas,nama_fakultas')
            ->orderBy('nama_prodi')
            ->get();

        // Hitung ringkasan jumlah section dan pertanyaan per program studi
        $questionCounts = KuesionerSectionPertanyaan::query()
            ->join('kuesioner_section', 'kuesioner_section_pertanyaan.section_id', '=', 'kuesioner_section.id')
            ->where('kuesioner_section.target', 'prodi')
            ->groupBy('kuesioner_section.kode_prodi')
            ->selectRaw('kuesioner_section.kode_prodi, count(distinct kuesioner_section_pertanyaan.pertanyaan_id) as total')
            ->pluck('total', 'kuesioner_section.kode_prodi');

        $sectionCounts = KuesionerSection::query()
            ->where('target', 'prodi')
            ->groupBy('kode_prodi')
            ->selectRaw('kode_prodi, count(*) as total')
            ->pluck('total', 'kode_prodi');

        $prodis->each(function (Prodi $p) use ($questionCounts, $sectionCounts) {
            $p->sections_count = (int) ($sectionCounts->get($p->kode_prodi, 0));
            $p->questions_count = (int) ($questionCounts->get($p->kode_prodi, 0));
        });

        // Tentukan prodi mana yang dipilih.
        // Jika parameter URL kosong, prioritaskan prodi yang sudah memiliki instrumen agar data langsung tampil.
        $selectedProdiId = (string) $request->query('kode_prodi', '');
        if ($selectedProdiId === '') {
            $preferredProdi = $prodis->first(fn (Prodi $p): bool => ($p->questions_count ?? 0) > 0 || ($p->sections_count ?? 0) > 0);
            $selectedProdi = $preferredProdi ?? $prodis->first();
            $selectedProdiId = $selectedProdi?->kode_prodi ?? '';
        } else {
            $selectedProdi = $prodis->firstWhere('kode_prodi', $selectedProdiId) ?? $prodis->first();
            $selectedProdiId = $selectedProdi?->kode_prodi ?? '';
        }

        // Normalisasi urutan hanya dilakukan pada operasi mutasi (store/update/delete/reorder), bukan pada query baca (GET)

        // Ambil Section dari tabel terpadu kuesioner_section
        $rawSections = $selectedProdiId
            ? KuesionerSection::where('target', 'prodi')
                ->where('kode_prodi', $selectedProdiId)
                ->withCount('sectionPertanyaan')
                ->orderBy('order', 'asc')
                ->get()
            : collect();

        // Jika belum ada section di kuesioner_section, buatkan default
        if ($selectedProdiId && $rawSections->isEmpty()) {
            KuesionerSection::create([
                'target' => 'prodi',
                'kode_prodi' => $selectedProdiId,
                'kode_section' => 'PRODI_'.$selectedProdiId.'_SEC1',
                'judul' => 'Bagian Evaluasi & Relevansi Program Studi',
                'deskripsi' => 'Kelompok pertanyaan kuesioner khusus Program Studi '.($selectedProdi?->nama_prodi ?? ''),
                'order' => 1,
                'is_active' => true,
            ]);

            $rawSections = KuesionerSection::where('target', 'prodi')
                ->where('kode_prodi', $selectedProdiId)
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
                'pertanyaan.pemilikProdi:kode_prodi,nama_prodi',
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
                'pemilik_kode_prodi' => $master->pemilik_kode_prodi,
                'can_edit' => $user->role === 'superadmin'
                    || $master->pemilik_kode_prodi === $selectedProdiId,
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

        // Seluruh section yang relevan untuk penempatan tambahan (Multi-Placement) dalam lingkup prodi yang dikelola
        $allSections = KuesionerSection::select('id', 'target', 'kode_prodi', 'judul', 'kode_section')
            ->where('target', 'prodi')
            ->when($user->role === 'admin_prodi', fn ($q) => $q->where('kode_prodi', $selectedProdiId))
            ->when($user->role === 'admin_fakultas', fn ($q) => $q->whereIn('kode_prodi', $prodis->pluck('kode_prodi')))
            ->when($user->role === 'superadmin', fn ($q) => $q->where('kode_prodi', $selectedProdiId))
            ->with('prodi:kode_prodi,nama_prodi')
            ->orderBy('order')
            ->get()
            ->map(fn (KuesionerSection $s) => [
                'id' => $s->id,
                'judul' => $s->judul,
                'target' => $s->target,
                'kode_prodi' => $s->kode_prodi,
                'label' => $s->judul,
            ])
            ->values()
            ->all();

        // Bank Pertanyaan terpadu (lintas prodi & universitas yang aktif & reusable)
        $existingCodes = $questions->pluck('code')->filter()->all();
        $bankQuestions = KuesionerPertanyaan::query()
            ->where('is_reusable', true)
            ->where('is_active', true)
            ->with([
                'pemilikProdi:kode_prodi,nama_prodi',
                'sections:id,target,kode_prodi,judul',
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
                'total_prodis' => $prodis->count(),
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
     * Menormalkan urutan section program studi secara berurutan (1..N).
     */
    private function normalizeSectionOrders(string $prodiId): void
    {
        if (empty($prodiId)) {
            return;
        }

        $sections = KuesionerSection::where('target', 'prodi')
            ->where('kode_prodi', $prodiId)
            ->orderBy('order', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        foreach ($sections as $index => $section) {
            $expectedOrder = $index + 1;
            if ($section->order !== $expectedOrder) {
                $section->update(['order' => $expectedOrder]);
            }
        }
    }

    /**
     * Menyimpan section kuesioner prodi baru.
     */
    public function storeSection(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kode_prodi' => 'required|exists:ref_prodi,kode_prodi',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'order' => 'nullable|integer',
        ]);

        $user = $this->authenticatedUser();
        $prodiId = $validated['kode_prodi'];
        $this->ensureProdiAccess($user, $prodiId);

        $maxOrder = KuesionerSection::where('target', 'prodi')->where('kode_prodi', $prodiId)->max('order') ?? 0;
        $order = ! empty($validated['order']) ? (int) $validated['order'] : ($maxOrder + 1);

        // Simpan ke skema terpadu kuesioner_section
        $code = 'PRODI_'.$prodiId.'_'.Str::upper(Str::slug(Str::limit($validated['title'], 15, ''))).'_'.time();
        $section = KuesionerSection::create([
            'target' => 'prodi',
            'kode_prodi' => $prodiId,
            'kode_section' => $code,
            'judul' => $validated['title'],
            'deskripsi' => $validated['description'] ?? null,
            'order' => $order,
            'is_active' => true,
        ]);

        $this->normalizeSectionOrders($prodiId);

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
        $this->ensureProdiAccess($user, (string) $section->kode_prodi);

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

        $this->normalizeSectionOrders($section->kode_prodi);

        LogActivity::record('KUESIONER_PRODI_SECTION_UPDATED', 'Memperbarui section kuesioner prodi: '.$section->judul.'.', $section, null, $validated);

        return redirect()->back()->with('success', 'Bagian (section) kuesioner prodi berhasil diperbarui.');
    }

    /**
     * Menghapus section kuesioner prodi.
     * Diberi proteksi keamanan data: dilarang menghapus section jika masih memuat butir pertanyaan atau memiliki respon jawaban.
     */
    public function destroySection(int $id): RedirectResponse
    {
        $user = $this->authenticatedUser();

        $section = KuesionerSection::withCount(['sectionPertanyaan', 'jawaban'])->findOrFail($id);
        $prodiId = (string) $section->kode_prodi;
        $this->ensureProdiAccess($user, $prodiId);

        if ($section->section_pertanyaan_count > 0) {
            return redirect()->back()->withErrors([
                'message' => 'Bagian (section) "'.$section->judul.'" tidak dapat dihapus karena masih memuat '.$section->section_pertanyaan_count.' butir pertanyaan. Silakan pindahkan atau hapus butir pertanyaan terlebih dahulu.',
            ]);
        }

        if ($section->jawaban_count > 0) {
            return redirect()->back()->withErrors([
                'message' => 'Bagian (section) "'.$section->judul.'" tidak dapat dihapus karena telah memiliki data respon jawaban alumni yang terekam.',
            ]);
        }

        $section->delete();

        $this->normalizeSectionOrders($prodiId);

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
            $this->ensureProdiAccess($user, (string) $currentSection->kode_prodi);
            $prodiId = $currentSection->kode_prodi;

            $this->normalizeSectionOrders($prodiId);
            $currentSection->refresh();

            $operator = $validated['direction'] === 'up' ? '<' : '>';
            $sortOrder = $validated['direction'] === 'up' ? 'desc' : 'asc';

            $adjacentSection = KuesionerSection::where('target', 'prodi')
                ->where('kode_prodi', $prodiId)
                ->where('order', $operator, $currentSection->order)
                ->orderBy('order', $sortOrder)
                ->first();

            if ($adjacentSection) {
                $tempOrder = $currentSection->order;
                $currentSection->update(['order' => $adjacentSection->order]);
                $adjacentSection->update(['order' => $tempOrder]);
                $this->normalizeSectionOrders($prodiId);
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
            'kode_prodi' => 'nullable|exists:ref_prodi,kode_prodi',
        ]);

        $user = $this->authenticatedUser();
        $question = KuesionerPertanyaan::findOrFail($questionId);
        $prodiId = ($validated['kode_prodi'] ?? $question->pemilik_kode_prodi ?? 0);
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
            'kode_prodi' => 'required|exists:ref_prodi,kode_prodi',
        ]);

        $user = $this->authenticatedUser();
        $placement = KuesionerSectionPertanyaan::with(['section', 'pertanyaan'])->findOrFail($placementId);
        $prodiId = $validated['kode_prodi'];

        $this->ensureProdiAccess($user, $prodiId);
        abort_unless(
            $placement->section?->kode_prodi === $prodiId,
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
            'kode_prodi' => 'required|exists:ref_prodi,kode_prodi',
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
        $prodiId = $validated['kode_prodi'];
        $this->ensureProdiAccess($user, $prodiId);

        // Batasi ruang lingkup tampil_di: admin_prodi hanya boleh mengelola pertanyaan untuk kuesioner prodi
        if ($user->role === 'admin_prodi') {
            $validated['tampil_di'] = in_array($validated['tampil_di'] ?? '', ['kuesioner', 'hidden'], true)
                ? $validated['tampil_di']
                : 'kuesioner';
        }

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
                ->where('kode_prodi', $prodiId)
                ->first();

            if (! $unifiedSection) {
                $unifiedSection = KuesionerSection::create([
                    'target' => 'prodi',
                    'kode_prodi' => $prodiId,
                    'kode_section' => 'PRODI_'.$prodiId.'_SEC1',
                    'judul' => 'Bagian Evaluasi & Relevansi Program Studi',
                    'order' => 1,
                    'is_active' => true,
                ]);
            }
        }

        $sectionId = $unifiedSection->id;

        $master = DB::transaction(function () use ($validated, $code, $prodiId, $sectionId, $user, $request) {
            // Cek apakah pertanyaan dengan kode ini sudah ada di Bank Soal (Pencegahan Pertanyaan Double)
            $master = KuesionerPertanyaan::where('kode_pertanyaan', $code)->first();
            $canManageMaster = false;

            if (! $master) {
                $master = KuesionerPertanyaan::create([
                    'kode_pertanyaan' => $code,
                    'label_pertanyaan' => filled($validated['label'] ?? null) ? trim($validated['label']) : null,
                    'teks_pertanyaan' => trim($validated['question_text']),
                    'tipe' => $validated['type'],
                    'pemilik_kode_prodi' => $prodiId,
                    'is_reusable' => $request->boolean('is_reusable', true),
                    'is_active' => true,
                ]);
                $canManageMaster = true;
            } elseif ($master->pemilik_kode_prodi === $prodiId) {
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
                        $extraSection = KuesionerSection::whereKey((int) $extraSecId)
                            ->where('target', 'prodi')
                            ->when($user->role !== 'superadmin', fn ($q) => $q->where('kode_prodi', $prodiId))
                            ->first();

                        if (! $extraSection) {
                            continue;
                        }

                        $extraOrder = (KuesionerSectionPertanyaan::where('section_id', $extraSection->id)->max('order') ?? 0) + 1;
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
                    $processedOptionIds = [];
                    foreach ($request->options as $opt) {
                        $labelText = is_array($opt) ? ($opt['option_text'] ?? $opt['label'] ?? '') : (string) $opt;
                        $optionCode = is_array($opt) ? trim((string) ($opt['code'] ?? $opt['kode_opsi'] ?? '')) : '';
                        $trimmed = trim((string) $labelText);

                        if ($trimmed !== '') {
                            $optionOrder = $orderCounter++;
                            $resolvedCode = $optionCode !== '' ? $optionCode : $master->kode_pertanyaan.'-'.str_pad((string) $optionOrder, 2, '0', STR_PAD_LEFT);
                            $optionRecord = KuesionerOpsi::updateOrCreate(
                                [
                                    'pertanyaan_id' => $master->id,
                                    'kode_opsi' => $resolvedCode,
                                ],
                                [
                                    'label' => $trimmed,
                                    'nilai_skor' => $master->tipe === 'rating_5' ? $optionOrder : null,
                                    'lompat_ke_pertanyaan_id' => is_array($opt) && filled($opt['jump_to'] ?? null) ? trim((string) $opt['jump_to']) : null,
                                    'order' => $optionOrder,
                                ]
                            );
                            $processedOptionIds[] = $optionRecord->id;
                        }
                    }

                    // Sinkronkan opsi: hapus opsi usang jika pertanyaan belum memiliki responden jawaban
                    if (! empty($processedOptionIds) && $master->jawaban()->doesntExist()) {
                        KuesionerOpsi::where('pertanyaan_id', $master->id)
                            ->whereNotIn('id', $processedOptionIds)
                            ->delete();
                    }
                } else {
                    QuestionDefaultOptionsService::generateForUnifiedQuestion($master);
                }
            }

            return $master;
        });

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
            'kode_prodi' => 'required|exists:ref_prodi,kode_prodi',
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
            'options' => 'nullable|array',
            'options.*.code' => 'nullable|string|max:50',
            'options.*.label' => 'nullable|string',
            'options.*.jump_to' => 'nullable|string|max:50',
        ]);

        if ($validated['type'] === 'radio') {
            $validated['type'] = 'single_choice';
        }
        if ($validated['type'] === 'checkbox') {
            $validated['type'] = 'multiple_choice';
        }

        $code = Str::upper(trim($validated['code']));
        $prodiId = $validated['kode_prodi'];
        $sectionId = (int) $validated['section_id'];
        $this->ensureQuestionOwner($user, $master, $prodiId);
        $this->findAccessibleProdiSection($user, $prodiId, $sectionId);

        // Batasi ruang lingkup tampil_di: admin_prodi hanya boleh mengelola pertanyaan untuk kuesioner prodi
        if ($user->role === 'admin_prodi') {
            $validated['tampil_di'] = in_array($validated['tampil_di'] ?? '', ['kuesioner', 'hidden'], true)
                ? $validated['tampil_di']
                : 'kuesioner';
        }

        DB::transaction(function () use ($request, $master, $code, $validated, $sectionId, $prodiId, $user) {
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
                $rawTargetIds = array_filter(array_map('intval', (array) $request->target_section_ids));
                $rawTargetIds[] = $sectionId;
                $rawTargetIds = array_unique($rawTargetIds);

                // Filter hanya section yang sah dalam lingkup program studi ini
                $targetIds = KuesionerSection::whereIn('id', $rawTargetIds)
                    ->where('target', 'prodi')
                    ->when($user->role !== 'superadmin', fn ($q) => $q->where('kode_prodi', $prodiId))
                    ->pluck('id')
                    ->all();

                $ownedSectionIds = KuesionerSection::where('target', 'prodi')
                    ->where('kode_prodi', $prodiId)
                    ->pluck('id');

                KuesionerSectionPertanyaan::where('pertanyaan_id', $master->id)
                    ->whereIn('section_id', $ownedSectionIds)
                    ->whereNotIn('section_id', $targetIds)
                    ->delete();

                // Tambahkan relasi penempatan yang belum ada
                foreach ($targetIds as $tid) {
                    $extraOrder = (KuesionerSectionPertanyaan::where('section_id', $tid)->max('order') ?? 0) + 1;
                    KuesionerSectionPertanyaan::firstOrCreate(
                        ['section_id' => $tid, 'pertanyaan_id' => $master->id],
                        [
                            'order' => $extraOrder,
                            'wajib' => $request->boolean('is_required', true),
                            'is_active' => true,
                            'tampil_di' => $validated['tampil_di'] ?? 'kuesioner',
                        ]
                    );
                }
            }

            // Sinkronkan opsi pilihan jika form mengirim daftar opsi
            if ($request->has('options') && is_array($request->options) && count($request->options) > 0) {
                $orderCounter = 1;
                $processedOptionIds = [];
                foreach ($request->options as $opt) {
                    $labelText = is_array($opt) ? ($opt['option_text'] ?? $opt['label'] ?? '') : (string) $opt;
                    $optionCode = is_array($opt) ? trim((string) ($opt['code'] ?? $opt['kode_opsi'] ?? '')) : '';
                    $trimmed = trim((string) $labelText);

                    if ($trimmed !== '') {
                        $optionOrder = $orderCounter++;
                        $resolvedCode = $optionCode !== '' ? $optionCode : $master->kode_pertanyaan.'-'.str_pad((string) $optionOrder, 2, '0', STR_PAD_LEFT);
                        $optionRecord = KuesionerOpsi::updateOrCreate(
                            [
                                'pertanyaan_id' => $master->id,
                                'kode_opsi' => $resolvedCode,
                            ],
                            [
                                'label' => $trimmed,
                                'nilai_skor' => $master->tipe === 'rating_5' ? $optionOrder : null,
                                'lompat_ke_pertanyaan_id' => is_array($opt) && filled($opt['jump_to'] ?? null) ? trim((string) $opt['jump_to']) : null,
                                'order' => $optionOrder,
                            ]
                        );
                        $processedOptionIds[] = $optionRecord->id;
                    }
                }

                if (! empty($processedOptionIds) && $master->jawaban()->doesntExist()) {
                    KuesionerOpsi::where('pertanyaan_id', $master->id)
                        ->whereNotIn('id', $processedOptionIds)
                        ->delete();
                }
            }
        });

        LogActivity::record('KUESIONER_PRODI_QUESTION_UPDATED', 'Memperbarui pertanyaan kuesioner prodi: '.$master->kode_pertanyaan.'.', $master, null, $validated);

        return redirect()->back()->with('success', 'Pertanyaan kuesioner prodi berhasil diperbarui.');
    }

    /**
     * Menghapus satu placement pertanyaan dari section prodi.
     */
    public function destroyQuestion(Request $request, int $placementId): RedirectResponse
    {
        $validated = $request->validate([
            'kode_prodi' => 'required|exists:ref_prodi,kode_prodi',
        ]);

        $user = $this->authenticatedUser();
        $prodiId = $validated['kode_prodi'];
        $this->ensureProdiAccess($user, $prodiId);

        // Batasi ruang lingkup tampil_di: admin_prodi hanya boleh mengelola pertanyaan untuk kuesioner prodi
        if ($user->role === 'admin_prodi') {
            $validated['tampil_di'] = in_array($validated['tampil_di'] ?? '', ['kuesioner', 'hidden'], true)
                ? $validated['tampil_di']
                : 'kuesioner';
        }
        $placement = KuesionerSectionPertanyaan::with('pertanyaan')->findOrFail($placementId);
        abort_unless(
            $placement->section()->value('kode_prodi') === $prodiId,
            403,
            'Placement pertanyaan tidak berada dalam lingkup program studi ini.'
        );
        $master = $placement->pertanyaan;

        // Lepas hanya placement pada section yang dipilih, bukan seluruh penggunaan lintas section/prodi.
        $placement->delete();

        // Master milik sendiri baru boleh dibersihkan jika sudah tidak dipakai dan belum memiliki jawaban.
        if ($master->pemilik_kode_prodi === $prodiId
            && ! KuesionerSectionPertanyaan::where('pertanyaan_id', $master->id)->exists()
            && $master->jawaban()->count() === 0) {
            $master->opsi()->delete();
            $master->delete();
        }

        LogActivity::record('KUESIONER_PRODI_QUESTION_DETACHED', 'Melepas pertanyaan kuesioner prodi dari section.', $master, null, [
            'placement_id' => $placementId,
            'kode_prodi' => $prodiId,
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
                'kode_prodi' => 'required|exists:ref_prodi,kode_prodi',
                'placement_id' => 'required|exists:kuesioner_section_pertanyaan,id',
                'direction' => 'required|in:up,down',
            ]);

            $user = $this->authenticatedUser();
            $prodiId = $validated['kode_prodi'];
            $this->ensureProdiAccess($user, $prodiId);
            $currentPlacement = KuesionerSectionPertanyaan::with('section')->findOrFail($validated['placement_id']);
            abort_unless(
                $currentPlacement->section?->kode_prodi === $prodiId,
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

        $targetCode = trim((string) ($validated['code'] ?? ''));
        if ($targetCode === '') {
            $num = (int) $validated['order'];
            do {
                $targetCode = $master->kode_pertanyaan.'-'.str_pad((string) $num, 2, '0', STR_PAD_LEFT);
                $num++;
            } while (KuesionerOpsi::where('pertanyaan_id', $master->id)->where('kode_opsi', $targetCode)->exists());
        }

        $opt = KuesionerOpsi::updateOrCreate(
            [
                'pertanyaan_id' => $master->id,
                'kode_opsi' => $targetCode,
            ],
            [
                'label' => trim($validated['option_text']),
                'nilai_skor' => $master->tipe === 'rating_5' ? (int) $validated['order'] : null,
                'lompat_ke_pertanyaan_id' => $validated['jump_to'] ?? null,
                'order' => $validated['order'],
            ]
        );

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
            'kode_prodi' => 'required|exists:ref_prodi,kode_prodi',
            'section_id' => 'required|exists:kuesioner_section,id',
            'pertanyaan_ids' => 'required|array|min:1',
            'pertanyaan_ids.*' => 'required|exists:kuesioner_pertanyaan,id',
        ]);

        $this->ensureProdiAccess($user, (string) $validated['kode_prodi']);

        $prodiId = $validated['kode_prodi'];
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
            'kode_prodi' => $prodiId,
            'pertanyaan_ids' => $validated['pertanyaan_ids'],
        ], KuesionerSectionPertanyaan::class, $sectionId);

        return redirect()->back()->with('success', $addedCount.' butir pertanyaan dari Bank Soal berhasil disematkan ke bagian kuesioner.');
    }
}
