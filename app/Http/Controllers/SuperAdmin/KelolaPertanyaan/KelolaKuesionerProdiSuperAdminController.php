<?php

namespace App\Http\Controllers\SuperAdmin\KelolaPertanyaan;

use App\Http\Controllers\Controller;
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
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller Pengaturan Kuesioner Prodi untuk Super Administrator.
 * Memungkinkan Superadmin memilih prodi, melihat section & kuesioner per prodi,
 * serta mengelola penuh (CRUD & Reorder) section, pertanyaan, dan opsi kuesioner prodi.
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

    /**
     * Memastikan section, pertanyaan, atau opsi berada dalam lingkup akses akun.
     */
    private function ensureSectionAccess(User $user, ProdiQuestionSection $section): void
    {
        $this->ensureProdiAccess($user, (int) $section->prodi_id);
    }

    private function ensureQuestionAccess(User $user, ProdiQuestion $question): void
    {
        $this->ensureProdiAccess($user, (int) $question->prodi_id);
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
     * Menampilkan halaman utama Pengaturan Kuesioner Prodi untuk Superadmin.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user()->load(['fakultas', 'prodi']);

        // Ambil daftar program studi sesuai lingkup akses akun.
        $prodis = $this->accessibleProdiQuery($user)
            ->select('id', 'nama_prodi', 'kode_prodi', 'fakultas_id')
            ->with('fakultas:id,nama_fakultas')
            ->withCount(['prodiQuestions', 'prodiQuestionSections'])
            ->orderBy('nama_prodi')
            ->get();

        // Tentukan prodi mana yang dipilih (default prodi pertama jika tidak ada query)
        $selectedProdiId = $request->query('prodi_id')
            ? (int) $request->query('prodi_id')
            : ($prodis->first()?->id ?? 0);

        $selectedProdi = $prodis->firstWhere('id', $selectedProdiId) ?? $prodis->first();
        $selectedProdiId = (int) ($selectedProdi?->id ?? 0);

        // Ambil section untuk prodi yang dipilih
        $sections = $selectedProdiId
            ? ProdiQuestionSection::where('prodi_id', $selectedProdiId)
                ->withCount('questions')
                ->orderBy('order', 'asc')
                ->get()
            : collect();

        // Ambil pertanyaan untuk prodi yang dipilih
        $questions = $selectedProdiId
            ? ProdiQuestion::where('prodi_id', $selectedProdiId)
                ->with(['section', 'options'])
                ->orderBy('order', 'asc')
                ->get()
            : collect();

        // Target lompatan (jump_to) yang tersedia
        $availableJumpTargets = $selectedProdiId
            ? ProdiQuestion::where('prodi_id', $selectedProdiId)
                ->whereNotNull('code')
                ->orderBy('order', 'asc')
                ->pluck('code')
            : collect();

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
            'availableJumpTargets' => $availableJumpTargets,
            'stats' => [
                'total_sections' => $sections->count(),
                'total_questions' => $questions->count(),
            ],
        ]);
    }

    // =========================================================================
    // SECTION PRODI MANAGEMENT (SUPERADMIN)
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
            $maxOrder = ProdiQuestionSection::where('prodi_id', $prodiId)->max('order') ?? 0;
            $validated['order'] = $maxOrder + 1;
        }

        ProdiQuestionSection::create($validated);

        return redirect()->back()->with('success', 'Section kuesioner prodi berhasil ditambahkan.');
    }

    /**
     * Memperbarui section kuesioner prodi.
     */
    public function updateSection(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();
        $section = ProdiQuestionSection::findOrFail($id);
        $this->ensureSectionAccess($user, $section);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'order' => 'nullable|integer',
        ]);

        if (empty($validated['order'])) {
            unset($validated['order']);
        }

        $section->update($validated);

        return redirect()->back()->with('success', 'Section kuesioner prodi berhasil diperbarui.');
    }

    /**
     * Menghapus section kuesioner prodi beserta seluruh pertanyaannya.
     */
    public function destroySection(int $id): RedirectResponse
    {
        $user = Auth::user();
        $section = ProdiQuestionSection::with('questions.options')->findOrFail($id);
        $this->ensureSectionAccess($user, $section);

        foreach ($section->questions as $question) {
            $question->options()->delete();
        }
        $section->questions()->delete();
        $section->delete();

        return redirect()->back()->with('success', 'Section kuesioner prodi berhasil dihapus.');
    }

    /**
     * Mengatur urutan posisi section prodi.
     */
    public function reorderSection(Request $request): RedirectResponse
    {
        if ($request->has(['id', 'direction'])) {
            $validated = $request->validate([
                'id' => 'required|exists:prodi_question_section,id',
                'direction' => 'required|in:up,down',
            ]);

            $user = Auth::user();
            $currentSection = ProdiQuestionSection::findOrFail($validated['id']);
            $this->ensureSectionAccess($user, $currentSection);
            $prodiId = $currentSection->prodi_id;

            $operator = $validated['direction'] === 'up' ? '<' : '>';
            $sortOrder = $validated['direction'] === 'up' ? 'desc' : 'asc';

            $adjacentSection = ProdiQuestionSection::where('prodi_id', $prodiId)
                ->where('order', $operator, $currentSection->order)
                ->orderBy('order', $sortOrder)
                ->first();

            if ($adjacentSection) {
                $tempOrder = $currentSection->order;
                $currentSection->update(['order' => $adjacentSection->order]);
                $adjacentSection->update(['order' => $tempOrder]);
            }

            return redirect()->back()->with('success', 'Urutan section prodi berhasil dipindahkan.');
        }

        if ($request->has(['prodi_id', 'orders'])) {
            $validated = $request->validate([
                'prodi_id' => 'required|exists:prodi,id',
                'orders' => 'required|array',
                'orders.*.id' => 'required|exists:prodi_question_section,id',
                'orders.*.order' => 'required|integer',
            ]);

            $user = Auth::user();
            $this->ensureProdiAccess($user, (int) $validated['prodi_id']);

            foreach ($validated['orders'] as $item) {
                ProdiQuestionSection::where('id', $item['id'])
                    ->where('prodi_id', $validated['prodi_id'])
                    ->update(['order' => $item['order']]);
            }

            return redirect()->back()->with('success', 'Urutan section prodi berhasil diperbarui.');
        }

        return redirect()->back()->withErrors(['message' => 'Parameter pengurutan tidak valid.']);
    }

    // =========================================================================
    // QUESTION PRODI MANAGEMENT (SUPERADMIN)
    // =========================================================================

    /**
     * Menyimpan pertanyaan kuesioner prodi baru.
     */
    public function storeQuestion(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'prodi_id' => 'required|exists:prodi,id',
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('prodi_question', 'code')->where('prodi_id', $request->input('prodi_id')),
            ],
            'question_text' => 'required|string',
            'type' => 'required|string|in:text,textarea,number,single_choice,radio,radio_input,radio_text,multiple_choice,dropdown,searchable_select,rating_5,multiple_number,matrix,matrix_dual,multiple_textbox,date,time,file,header',
            'is_required' => 'boolean',
            'prodi_question_section_id' => 'nullable|exists:prodi_question_section,id',
            'order' => 'nullable|integer',
        ]);

        $user = Auth::user();
        $prodiId = (int) $validated['prodi_id'];
        $this->ensureProdiAccess($user, $prodiId);

        if ($validated['type'] === 'radio') {
            $validated['type'] = 'single_choice';
        }

        if (empty($validated['prodi_question_section_id'])) {
            $section = ProdiQuestionSection::where('prodi_id', $prodiId)->orderBy('order', 'asc')->first();
            if (! $section) {
                $section = ProdiQuestionSection::create([
                    'prodi_id' => $prodiId,
                    'title' => 'Bagian Evaluasi & Relevansi Program Studi',
                    'order' => 1,
                ]);
            }
            $validated['prodi_question_section_id'] = $section->id;
        } else {
            ProdiQuestionSection::whereKey($validated['prodi_question_section_id'])
                ->where('prodi_id', $prodiId)
                ->firstOrFail();
        }

        $validated['is_required'] = $request->boolean('is_required');

        if (empty($validated['order'])) {
            $maxOrder = ProdiQuestion::where('prodi_id', $prodiId)
                ->where('prodi_question_section_id', $validated['prodi_question_section_id'])
                ->max('order') ?? 0;
            $validated['order'] = $maxOrder + 1;
        }

        $question = ProdiQuestion::create($validated);

        if ($request->has('options') && is_array($request->options) && count($request->options) > 0) {
            $orderCounter = 1;
            foreach ($request->options as $idx => $optText) {
                $trimmed = trim((string) $optText);
                if ($trimmed !== '') {
                    $question->options()->create([
                        'code' => $question->code.'-'.str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT),
                        'option_text' => $trimmed,
                        'order' => $orderCounter++,
                    ]);
                }
            }
        } else {
            QuestionDefaultOptionsService::generateForProdiQuestion($question);
        }

        return redirect()->back()->with('success', 'Pertanyaan kuesioner prodi beserta pilihan opsi jawaban default berhasil ditambahkan.');
    }

    /**
     * Memperbarui pertanyaan kuesioner prodi.
     */
    public function updateQuestion(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();
        $question = ProdiQuestion::findOrFail($id);
        $this->ensureQuestionAccess($user, $question);

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('prodi_question', 'code')
                    ->where('prodi_id', $question->prodi_id)
                    ->ignore($question->id),
            ],
            'question_text' => 'required|string',
            'type' => 'required|string|in:text,textarea,number,single_choice,radio,radio_input,radio_text,multiple_choice,dropdown,searchable_select,rating_5,multiple_number,matrix,matrix_dual,multiple_textbox,date,time,file,header',
            'is_required' => 'boolean',
            'prodi_question_section_id' => 'nullable|exists:prodi_question_section,id',
            'order' => 'nullable|integer',
        ]);

        if ($validated['type'] === 'radio') {
            $validated['type'] = 'single_choice';
        }

        if (! empty($validated['prodi_question_section_id'])) {
            ProdiQuestionSection::whereKey($validated['prodi_question_section_id'])
                ->where('prodi_id', $question->prodi_id)
                ->firstOrFail();
        }

        $validated['is_required'] = $request->boolean('is_required');

        if (empty($validated['order'])) {
            unset($validated['order']);
        }

        $question->update($validated);

        return redirect()->back()->with('success', 'Pertanyaan kuesioner prodi berhasil diperbarui.');
    }

    /**
     * Menghapus pertanyaan kuesioner prodi.
     */
    public function destroyQuestion(int $id): RedirectResponse
    {
        $user = Auth::user();
        $question = ProdiQuestion::findOrFail($id);
        $this->ensureQuestionAccess($user, $question);
        $question->options()->delete();
        $question->delete();

        return redirect()->back()->with('success', 'Pertanyaan kuesioner prodi berhasil dihapus.');
    }

    /**
     * Mengatur urutan pertanyaan kuesioner prodi.
     */
    public function reorderQuestion(Request $request): RedirectResponse
    {
        if ($request->has(['id', 'direction'])) {
            $validated = $request->validate([
                'id' => 'required|exists:prodi_question,id',
                'direction' => 'required|in:up,down',
            ]);

            $user = Auth::user();
            $currentQuestion = ProdiQuestion::findOrFail($validated['id']);
            $this->ensureQuestionAccess($user, $currentQuestion);
            $prodiId = $currentQuestion->prodi_id;

            $operator = $validated['direction'] === 'up' ? '<' : '>';
            $sortOrder = $validated['direction'] === 'up' ? 'desc' : 'asc';

            $adjacentQuery = ProdiQuestion::where('prodi_id', $prodiId);
            if ($currentQuestion->prodi_question_section_id) {
                $adjacentQuery->where('prodi_question_section_id', $currentQuestion->prodi_question_section_id);
            }

            $adjacentQuestion = $adjacentQuery
                ->where('order', $operator, $currentQuestion->order)
                ->orderBy('order', $sortOrder)
                ->first();

            if (! $adjacentQuestion) {
                $adjacentQuestion = ProdiQuestion::where('prodi_id', $prodiId)
                    ->where('order', $operator, $currentQuestion->order)
                    ->orderBy('order', $sortOrder)
                    ->first();
            }

            if ($adjacentQuestion) {
                $tempOrder = $currentQuestion->order;
                $currentQuestion->update(['order' => $adjacentQuestion->order]);
                $adjacentQuestion->update(['order' => $tempOrder]);
            }

            return redirect()->back()->with('success', 'Urutan pertanyaan berhasil dipindahkan.');
        }

        if ($request->has(['prodi_id', 'orders'])) {
            $validated = $request->validate([
                'prodi_id' => 'required|exists:prodi,id',
                'orders' => 'required|array',
                'orders.*.id' => 'required|exists:prodi_question,id',
                'orders.*.order' => 'required|integer',
            ]);

            $user = Auth::user();
            $this->ensureProdiAccess($user, (int) $validated['prodi_id']);

            foreach ($validated['orders'] as $item) {
                ProdiQuestion::where('id', $item['id'])
                    ->where('prodi_id', $validated['prodi_id'])
                    ->update(['order' => $item['order']]);
            }

            return redirect()->back()->with('success', 'Urutan pertanyaan berhasil diperbarui.');
        }

        return redirect()->back()->withErrors(['message' => 'Parameter pengurutan tidak valid.']);
    }

    // =========================================================================
    // OPTION PRODI MANAGEMENT (SUPERADMIN)
    // =========================================================================

    /**
     * Menyimpan opsi pertanyaan prodi.
     */
    public function storeOption(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $validated = $request->validate([
            'prodi_question_id' => 'required|exists:prodi_question,id',
            'code' => 'required|string|max:50',
            'option_text' => 'required|string',
            'value' => 'nullable|string',
            'jump_to' => 'nullable|string',
            'order' => 'nullable|integer',
        ]);

        $question = ProdiQuestion::findOrFail($validated['prodi_question_id']);
        $this->ensureQuestionAccess($user, $question);

        if (empty($validated['order'])) {
            $maxOrder = ProdiQuestionOption::where('prodi_question_id', $question->id)->max('order') ?? 0;
            $validated['order'] = $maxOrder + 1;
        }

        ProdiQuestionOption::create($validated);

        return redirect()->back()->with('success', 'Opsi jawaban kuesioner prodi berhasil ditambahkan.');
    }

    /**
     * Memperbarui opsi pertanyaan prodi.
     */
    public function updateOption(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();
        $option = ProdiQuestionOption::findOrFail($id);
        $this->ensureQuestionAccess($user, $option->question);

        $validated = $request->validate([
            'code' => 'required|string|max:50',
            'option_text' => 'required|string',
            'value' => 'nullable|string',
            'jump_to' => 'nullable|string',
            'order' => 'nullable|integer',
        ]);

        if (empty($validated['order'])) {
            unset($validated['order']);
        }

        $option->update($validated);

        return redirect()->back()->with('success', 'Opsi jawaban kuesioner prodi berhasil diperbarui.');
    }

    /**
     * Menghapus opsi pertanyaan prodi.
     */
    public function destroyOption(int $id): RedirectResponse
    {
        $user = Auth::user();
        $option = ProdiQuestionOption::findOrFail($id);
        $this->ensureQuestionAccess($user, $option->question);
        $option->delete();

        return redirect()->back()->with('success', 'Opsi jawaban kuesioner prodi berhasil dihapus.');
    }
}
