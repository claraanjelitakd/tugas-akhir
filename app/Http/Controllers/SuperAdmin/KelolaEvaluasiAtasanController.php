<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\EvaluasiAtasan;
use App\Models\PertanyaanEvaluasiAtasan;
use App\Models\SectionEvaluasiAtasan;
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
 * 1. Bagian / Section instrumen evaluasi atasan (CRUD & Reorder)
 * 2. Butir pertanyaan evaluasi kepuasan pengguna lulusan (CRUD & Reorder)
 * 3. Riwayat respon evaluasi atasan yang masuk
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
        $sections = SectionEvaluasiAtasan::orderBy('order', 'asc')
            ->withCount('pertanyaans')
            ->get();

        // 2. Ambil seluruh pertanyaan instrumen evaluasi atasan beserta relasi section
        $questions = PertanyaanEvaluasiAtasan::with('section')
            ->orderBy('order', 'asc')
            ->get();

        // 3. Ambil riwayat evaluasi atasan yang masuk
        $evaluasis = EvaluasiAtasan::with([
            'biodata.dataAkademik',
            'biodata.prodi',
            'perusahaan',
            'atasan',
            'respons.pertanyaan',
        ])
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('SuperAdmin/EvaluasiAtasan/Index', [
            'user' => $user,
            'sections' => $sections,
            'stepperOptions' => $sections->map(static fn (SectionEvaluasiAtasan $sec): array => [
                'value' => $sec->key ?: 'sec_'.$sec->id,
                'label' => $sec->title,
            ])->values(),
            'questions' => $questions,
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

        $maxOrder = SectionEvaluasiAtasan::max('order') ?? 0;
        $key = Str::slug($validated['title'], '_');
        $baseKey = $key;
        $counter = 1;
        while (SectionEvaluasiAtasan::where('key', $key)->exists()) {
            $key = "{$baseKey}_{$counter}";
            $counter++;
        }

        SectionEvaluasiAtasan::create([
            'key' => $key,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'order' => $validated['order'] ?? ($maxOrder + 1),
        ]);

        return redirect()->back()->with('success', 'Bagian kuesioner evaluasi atasan berhasil ditambahkan.');
    }

    /**
     * Perbarui bagian / section evaluasi atasan.
     */
    public function updateSection(Request $request, int $id): RedirectResponse
    {
        $section = SectionEvaluasiAtasan::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'nullable|string',
            'order' => 'nullable|integer',
        ]);

        $section->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'order' => $validated['order'] ?? $section->order,
        ]);

        return redirect()->back()->with('success', 'Bagian kuesioner evaluasi atasan berhasil diperbarui.');
    }

    /**
     * Hapus bagian / section evaluasi atasan.
     */
    public function destroySection(int $id): RedirectResponse
    {
        $section = SectionEvaluasiAtasan::findOrFail($id);

        if ($section->pertanyaans()->count() > 0) {
            return redirect()->back()->withErrors([
                'message' => 'Bagian ini tidak dapat dihapus karena masih memuat butir pertanyaan aktif.',
            ]);
        }

        $section->delete();

        return redirect()->back()->with('success', 'Bagian kuesioner evaluasi atasan berhasil dihapus.');
    }

    /**
     * Mengatur ulang urutan naik/turun bagian / section evaluasi atasan.
     */
    public function reorderSections(Request $request): RedirectResponse
    {
        if ($request->has(['id', 'direction'])) {
            $validated = $request->validate([
                'id' => 'required|exists:section_evaluasi_atasan,id',
                'direction' => 'required|in:up,down',
            ]);

            $currentSection = SectionEvaluasiAtasan::findOrFail($validated['id']);
            $operator = $validated['direction'] === 'up' ? '<' : '>';
            $sortOrder = $validated['direction'] === 'up' ? 'desc' : 'asc';

            $adjacentSection = SectionEvaluasiAtasan::where('order', $operator, $currentSection->order)
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
                'orders.*.id' => 'required|exists:section_evaluasi_atasan,id',
                'orders.*.order' => 'required|integer',
            ]);

            foreach ($validated['orders'] as $item) {
                SectionEvaluasiAtasan::where('id', $item['id'])->update(['order' => $item['order']]);
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
        if (! $request->has('section_id') && $request->has('langkah')) {
            $sec = SectionEvaluasiAtasan::where('key', $request->input('langkah'))->first();
            if ($sec) {
                $request->merge(['section_id' => $sec->id]);
            }
        }

        $validated = $request->validate([
            'section_id' => 'nullable|integer|exists:section_evaluasi_atasan,id',
            'kode' => 'nullable|string|max:50|unique:pertanyaan_evaluasi_atasan,kode',
            'aspek' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'tipe' => 'nullable|string|in:likert_5,pilihan_ganda,single_choice,multiple_choice,checkbox,text,textarea',
            'pilihan_jawaban' => 'nullable|array',
            'order' => 'nullable|integer',
        ]);

        $sectionId = $validated['section_id'] ?? SectionEvaluasiAtasan::orderBy('id', 'desc')->value('id');
        $section = $sectionId ? SectionEvaluasiAtasan::find($sectionId) : null;

        $kode = ! empty($validated['kode'])
            ? Str::slug($validated['kode'], '_')
            : Str::slug($validated['aspek'], '_');

        $baseKode = $kode;
        $counter = 1;
        while (PertanyaanEvaluasiAtasan::where('kode', $kode)->exists()) {
            $kode = "{$baseKode}_{$counter}";
            $counter++;
        }

        $maxOrder = PertanyaanEvaluasiAtasan::max('order') ?? 0;

        // Normalisasi tipe input
        $tipe = match ($validated['tipe'] ?? 'likert_5') {
            'single_choice' => 'pilihan_ganda',
            'checkbox' => 'multiple_choice',
            default => $validated['tipe'] ?? 'likert_5',
        };

        // Jika likert_5 dan opsi jawaban kosong, isi default skala 5
        $pilihanJawaban = $validated['pilihan_jawaban'] ?? null;
        if ($tipe === 'likert_5' && empty($pilihanJawaban)) {
            $pilihanJawaban = ['Sangat Tinggi', 'Tinggi', 'Cukup', 'Kurang', 'Sangat Kurang'];
        }

        $kategori = match ($section?->key) {
            'instansi' => 'Profil Perusahaan',
            'karakteristik' => 'Karakteristik Perusahaan',
            'penilaian' => 'Penilaian Kinerja',
            default => $section?->title ?? 'Penilaian Kinerja',
        };

        PertanyaanEvaluasiAtasan::create([
            'section_id' => $section?->id,
            'kode' => $kode,
            'aspek' => $validated['aspek'],
            'deskripsi' => $validated['deskripsi'] ?? null,
            'kategori' => $kategori,
            'tipe' => $tipe,
            'pilihan_jawaban' => $pilihanJawaban,
            'order' => $validated['order'] ?? ($maxOrder + 1),
            'is_active' => true,
        ]);

        return redirect()->back()->with('success', 'Butir pertanyaan evaluasi atasan berhasil ditambahkan.');
    }

    /**
     * Perbarui butir pertanyaan evaluasi atasan.
     */
    public function updateQuestion(Request $request, int $id): RedirectResponse
    {
        $question = PertanyaanEvaluasiAtasan::findOrFail($id);

        if (! $request->has('section_id') && $request->has('langkah')) {
            $sec = SectionEvaluasiAtasan::where('key', $request->input('langkah'))->first();
            if ($sec) {
                $request->merge(['section_id' => $sec->id]);
            }
        }

        $validated = $request->validate([
            'section_id' => 'nullable|integer|exists:section_evaluasi_atasan,id',
            'aspek' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'tipe' => 'nullable|string|in:likert_5,pilihan_ganda,single_choice,multiple_choice,checkbox,text,textarea',
            'pilihan_jawaban' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'order' => 'nullable|integer',
        ]);

        $sectionId = $validated['section_id'] ?? $question->section_id;
        $section = $sectionId ? SectionEvaluasiAtasan::find($sectionId) : null;

        $tipe = match ($validated['tipe'] ?? $question->tipe) {
            'single_choice' => 'pilihan_ganda',
            'checkbox' => 'multiple_choice',
            default => $validated['tipe'] ?? $question->tipe,
        };

        $pilihanJawaban = $validated['pilihan_jawaban'] ?? $question->pilihan_jawaban;
        if ($tipe === 'likert_5' && empty($pilihanJawaban)) {
            $pilihanJawaban = ['Sangat Tinggi', 'Tinggi', 'Cukup', 'Kurang', 'Sangat Kurang'];
        }

        $kategori = match ($section?->key) {
            'instansi' => 'Profil Perusahaan',
            'karakteristik' => 'Karakteristik Perusahaan',
            'penilaian' => 'Penilaian Kinerja',
            default => $section?->title ?? $question->kategori,
        };

        $question->update([
            'section_id' => $section?->id ?? $question->section_id,
            'aspek' => $validated['aspek'],
            'deskripsi' => $validated['deskripsi'] ?? null,
            'kategori' => $kategori,
            'tipe' => $tipe,
            'pilihan_jawaban' => $pilihanJawaban,
            'is_active' => $validated['is_active'] ?? $question->is_active,
            'order' => $validated['order'] ?? $question->order,
        ]);

        return redirect()->back()->with('success', 'Butir pertanyaan evaluasi atasan berhasil diperbarui.');
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

        if ($question->is_company_field) {
            return redirect()->back()->withErrors([
                'message' => 'Pertanyaan karakteristik perusahaan tidak dapat dihapus karena terhubung langsung ke entitas master perusahaan.',
            ]);
        }

        $question->delete();

        return redirect()->back()->with('success', 'Butir aspek evaluasi atasan berhasil dihapus.');
    }

    /**
     * Reorder urutan pertanyaan (directional naik/turun atau bulk array).
     */
    public function reorderQuestions(Request $request): RedirectResponse
    {
        if ($request->has(['id', 'direction'])) {
            $validated = $request->validate([
                'id' => 'required|exists:pertanyaan_evaluasi_atasan,id',
                'direction' => 'required|in:up,down',
            ]);

            $currentQuestion = PertanyaanEvaluasiAtasan::findOrFail($validated['id']);
            $operator = $validated['direction'] === 'up' ? '<' : '>';
            $sortOrder = $validated['direction'] === 'up' ? 'desc' : 'asc';

            // Coba tukar dengan butir di section yang sama terlebih dahulu
            $adjacentQuestion = PertanyaanEvaluasiAtasan::where('section_id', $currentQuestion->section_id)
                ->where('order', $operator, $currentQuestion->order)
                ->orderBy('order', $sortOrder)
                ->first();

            // Jika tidak ada di section yang sama, tukar lintas pertanyaan
            if (! $adjacentQuestion) {
                $adjacentQuestion = PertanyaanEvaluasiAtasan::where('order', $operator, $currentQuestion->order)
                    ->orderBy('order', $sortOrder)
                    ->first();
            }

            if ($adjacentQuestion) {
                $tempOrder = $currentQuestion->order;
                $currentQuestion->update(['order' => $adjacentQuestion->order]);
                $adjacentQuestion->update(['order' => $tempOrder]);
            }

            return redirect()->back()->with('success', 'Urutan butir pertanyaan evaluasi berhasil diperbarui.');
        }

        if ($request->has('orders')) {
            $validated = $request->validate([
                'orders' => 'required|array',
                'orders.*.id' => 'required|exists:pertanyaan_evaluasi_atasan,id',
                'orders.*.order' => 'required|integer',
            ]);

            foreach ($validated['orders'] as $item) {
                PertanyaanEvaluasiAtasan::where('id', $item['id'])->update(['order' => $item['order']]);
            }

            return redirect()->back()->with('success', 'Seluruh urutan butir aspek evaluasi atasan berhasil diperbarui.');
        }

        return redirect()->back()->withErrors(['message' => 'Parameter pengurutan pertanyaan tidak valid.']);
    }
}
