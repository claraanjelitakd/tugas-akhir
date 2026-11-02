<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\EvaluasiAtasan;
use App\Models\PertanyaanEvaluasiAtasan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller Kelola Kuesioner Evaluasi Atasan untuk Super Administrator.
 *
 * Mengelola:
 * 1. Butir instrumen pertanyaan evaluasi kepuasan pengguna lulusan (CRUD & Reorder)
 * 2. Daftar riwayat respon evaluasi atasan yang masuk
 */
class KelolaEvaluasiAtasanController extends Controller
{
    /**
     * Menampilkan halaman manajemen kuesioner evaluasi atasan & riwayat respon.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();

        // 1. Ambil seluruh pertanyaan instrumen evaluasi atasan
        $questions = PertanyaanEvaluasiAtasan::orderBy('order', 'asc')->get();

        // Section stepper berasal dari kontrak master yang sama dengan formulir publik.
        $sections = collect(PertanyaanEvaluasiAtasan::stepperSections())
            ->map(function (array $section) use ($questions): array {
                $section['question_count'] = $questions
                    ->where('langkah', $section['key'])
                    ->count();

                return $section;
            })
            ->values();

        // 2. Ambil riwayat evaluasi atasan yang masuk
        $evaluasis = EvaluasiAtasan::with(['biodata.dataAkademik', 'biodata.prodi', 'perusahaan', 'atasan', 'respons.pertanyaan'])
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('SuperAdmin/EvaluasiAtasan/Index', [
            'user' => $user,
            'sections' => $sections,
            'questions' => $questions,
            'stepperOptions' => $sections
                ->map(fn (array $section): array => ['value' => $section['key'], 'label' => $section['title']])
                ->values(),
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
     * Tambah butir pertanyaan instrumen evaluasi atasan baru.
     */
    public function storeQuestion(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'aspek' => 'required|string|max:255',
            'kode' => 'nullable|string|max:50|unique:pertanyaan_evaluasi_atasan,kode',
            'deskripsi' => 'nullable|string',
            'kategori' => 'nullable|string|max:50',
            'langkah' => 'nullable|in:instansi,karakteristik,penilaian',
            'order' => 'nullable|integer',
        ]);

        $kode = ! empty($validated['kode'])
            ? Str::slug($validated['kode'], '_')
            : Str::slug($validated['aspek'], '_');

        // Pastikan unik
        $baseKode = $kode;
        $counter = 1;
        while (PertanyaanEvaluasiAtasan::where('kode', $kode)->exists()) {
            $kode = "{$baseKode}_{$counter}";
            $counter++;
        }

        $maxOrder = PertanyaanEvaluasiAtasan::max('order') ?? 0;

        PertanyaanEvaluasiAtasan::create([
            'kode' => $kode,
            'aspek' => $validated['aspek'],
            'deskripsi' => $validated['deskripsi'] ?? null,
            'kategori' => $this->categoryForStep($validated['langkah'] ?? null, $validated['kategori'] ?? null, $kode),
            'order' => $validated['order'] ?? ($maxOrder + 1),
            'is_active' => true,
        ]);

        return redirect()->back()->with('success', 'Butir aspek evaluasi atasan berhasil ditambahkan.');
    }

    /**
     * Perbarui butir pertanyaan evaluasi atasan.
     */
    public function updateQuestion(Request $request, int $id): RedirectResponse
    {
        $question = PertanyaanEvaluasiAtasan::findOrFail($id);

        $validated = $request->validate([
            'aspek' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'kategori' => 'nullable|string|max:50',
            'langkah' => 'nullable|in:instansi,karakteristik,penilaian',
            'is_active' => 'nullable|boolean',
            'order' => 'nullable|integer',
        ]);

        $question->update([
            'aspek' => $validated['aspek'],
            'deskripsi' => $validated['deskripsi'] ?? null,
            'kategori' => $this->categoryForStep($validated['langkah'] ?? null, $validated['kategori'] ?? $question->kategori, $question->kode),
            'is_active' => $validated['is_active'] ?? $question->is_active,
            'order' => $validated['order'] ?? $question->order,
        ]);

        return redirect()->back()->with('success', 'Butir aspek evaluasi atasan berhasil diperbarui.');
    }

    /**
     * Toggle status aktif/nonaktif butir pertanyaan evaluasi.
     */
    public function toggleQuestion(int $id): RedirectResponse
    {
        $question = PertanyaanEvaluasiAtasan::findOrFail($id);
        $question->update(['is_active' => ! $question->is_active]);

        return redirect()->back()->with('success', 'Status keaktifan butir evaluasi berhasil diubah.');
    }

    /**
     * Hapus butir pertanyaan evaluasi atasan.
     */
    public function destroyQuestion(int $id): RedirectResponse
    {
        $question = PertanyaanEvaluasiAtasan::findOrFail($id);
        $question->delete();

        return redirect()->back()->with('success', 'Butir aspek evaluasi atasan berhasil dihapus.');
    }

    /**
     * Reorder urutan pertanyaan.
     */
    public function reorderQuestions(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'orders' => 'required|array',
            'orders.*.id' => 'required|exists:pertanyaan_evaluasi_atasan,id',
            'orders.*.order' => 'required|integer',
        ]);

        foreach ($validated['orders'] as $item) {
            PertanyaanEvaluasiAtasan::where('id', $item['id'])->update(['order' => $item['order']]);
        }

        return redirect()->back()->with('success', 'Urutan butir aspek evaluasi atasan berhasil diperbarui.');
    }

    /**
     * Mengubah kunci stepper dari frontend menjadi kategori yang kompatibel dengan schema lama.
     */
    private function categoryForStep(?string $step, ?string $fallback, ?string $kode = null): string
    {
        if ($kode !== null && in_array($kode, PertanyaanEvaluasiAtasan::COMPANY_FIELD_CODES, true)) {
            return 'Karakteristik Perusahaan';
        }

        $stepCategories = collect(PertanyaanEvaluasiAtasan::stepperSections())
            ->mapWithKeys(fn (array $section): array => [$section['key'] => match ($section['key']) {
                'instansi' => 'Profil Perusahaan',
                'karakteristik' => 'Karakteristik Perusahaan',
                default => 'Penilaian Kinerja',
            }])
            ->all();

        return $step !== null && isset($stepCategories[$step])
            ? $stepCategories[$step]
            : ($fallback ?: 'Penilaian Kinerja');
    }
}
