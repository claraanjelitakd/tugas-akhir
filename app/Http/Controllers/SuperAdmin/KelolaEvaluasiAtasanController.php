<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\EvaluasiAtasan;
use App\Models\KuesionerOpsi;
use App\Models\KuesionerPertanyaan;
use App\Models\KuesionerSection;
use App\Models\KuesionerSectionPertanyaan;
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
                'aspek' => $p->teks_pertanyaan,
                'deskripsi' => $p->keterangan,
                'tipe' => $p->tipe,
                'order' => (int) $pivot->order,
                'is_active' => (bool) $p->is_active,
                'is_company_field' => in_array($p->kode_pertanyaan, $companyFieldCodes, true) || in_array($cleanCode, $companyFieldCodes, true),
                'pilihan_jawaban' => $p->opsi->pluck('label')->all(),
                'section' => [
                    'id' => $pivot->section?->id,
                    'title' => $pivot->section?->judul,
                    'key' => strtolower(str_replace(['SEC_EVA_', 'SEC_'], '', $pivot->section?->kode_section ?? '')),
                ],
            ];
        })->filter()->values();

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
            'stepperOptions' => $sections->map(static fn (array $sec): array => [
                'value' => $sec['key'] ?: 'sec_'.$sec['id'],
                'label' => $sec['title'],
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

        $maxOrder = KuesionerSection::where('target', 'atasan')->max('order') ?? 0;
        $order = $validated['order'] ?? ($maxOrder + 1);

        $baseKey = 'SEC_EVA_'.strtoupper(Str::slug($validated['title'], '_'));
        $key = $baseKey;
        $counter = 1;
        while (KuesionerSection::where('kode_section', $key)->exists()) {
            $key = "{$baseKey}_{$counter}";
            $counter++;
        }

        KuesionerSection::create([
            'target' => 'atasan',
            'kode_section' => $key,
            'judul' => $validated['title'],
            'deskripsi' => $validated['description'] ?? null,
            'order' => $order,
            'is_active' => true,
        ]);

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
            'aspek' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'tipe' => 'nullable|string|in:likert_5,pilihan_ganda,single_choice,multiple_choice,checkbox,text,textarea',
            'pilihan_jawaban' => 'nullable|array',
            'order' => 'nullable|integer',
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
            $pilihanJawaban = ['Sangat Tinggi', 'Tinggi', 'Cukup', 'Kurang', 'Sangat Kurang'];
        }

        $pertanyaan = KuesionerPertanyaan::create([
            'kode_pertanyaan' => $kode,
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

        if (is_array($pilihanJawaban)) {
            foreach ($pilihanJawaban as $idx => $label) {
                KuesionerOpsi::create([
                    'pertanyaan_id' => $pertanyaan->id,
                    'label' => (string) $label,
                    'nilai_skor' => count($pilihanJawaban) - $idx,
                    'order' => $idx + 1,
                ]);
            }
        }

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
            'aspek' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'tipe' => 'nullable|string|in:likert_5,pilihan_ganda,single_choice,multiple_choice,checkbox,text,textarea',
            'pilihan_jawaban' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'order' => 'nullable|integer',
        ]);

        $tipe = match ($validated['tipe'] ?? $pertanyaan->tipe) {
            'single_choice' => 'pilihan_ganda',
            'checkbox' => 'multiple_choice',
            default => $validated['tipe'] ?? $pertanyaan->tipe,
        };

        $pertanyaan->update([
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
                ]);
            }
        }

        $pilihanJawaban = $validated['pilihan_jawaban'] ?? null;
        if (is_array($pilihanJawaban) && ! empty($pilihanJawaban)) {
            $pertanyaan->opsi()->delete();
            foreach ($pilihanJawaban as $idx => $label) {
                KuesionerOpsi::create([
                    'pertanyaan_id' => $pertanyaan->id,
                    'label' => (string) $label,
                    'nilai_skor' => count($pilihanJawaban) - $idx,
                    'order' => $idx + 1,
                ]);
            }
        }

        return redirect()->back()->with('success', 'Butir pertanyaan evaluasi atasan berhasil diperbarui.');
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
}
