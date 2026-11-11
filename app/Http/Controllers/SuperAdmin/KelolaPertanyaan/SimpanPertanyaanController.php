<?php

namespace App\Http\Controllers\SuperAdmin\KelolaPertanyaan;

use App\Http\Controllers\Controller;
use App\Models\KuesionerPertanyaan;
use App\Models\KuesionerSectionPertanyaan;
use App\Services\Kuesioner\QuestionDefaultOptionsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Class SimpanPertanyaanController
 *
 * Fungsi:
 * Controller khusus untuk menangani aksi manipulasi data butir pertanyaan kuesioner universitas:
 * 1. Tambah pertanyaan baru (store)
 * 2. Perbarui data pertanyaan (update)
 * 3. Hapus pertanyaan beserta opsinya (destroy)
 * 4. Pindah urutan pertanyaan naik/turun dalam section yang sama (reorder)
 * 5. Toggle visibilitas kuesioner / profil (toggleVisibility)
 */
class SimpanPertanyaanController extends Controller
{
    /**
     * Menyimpan butir pertanyaan baru ke dalam database.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'section_id' => 'required|exists:kuesioner_section,id',
            'kode_pertanyaan' => 'required|string|max:50|unique:kuesioner_pertanyaan,kode_pertanyaan',
            'subpertanyaan' => 'required|string',
            'type' => 'required|string|in:radio,single_choice,radio_input,radio_text,multiple_choice,checkbox,multiple_choice_text,rating_5,multiple_number,matrix,matrix_dual,multiple_textbox,text,textarea,number,date,time,dropdown,searchable_select,file,header',
            'keterangan' => 'nullable|string|max:500',
            'wajib' => 'required|boolean',
            'tampil_di' => 'nullable|string|in:kuesioner,profile,profil,both,hidden,none',
            'order' => 'nullable|integer',
        ]);

        $sectionId = (int) $validated['section_id'];

        if (empty($validated['order'])) {
            $validated['order'] = (int) ((KuesionerSectionPertanyaan::where('section_id', $sectionId)->max('order') ?? 0) + 1);
        }

        if (empty($validated['tampil_di'])) {
            $validated['tampil_di'] = 'kuesioner';
        }

        if (empty($validated['keterangan'])) {
            $validated['keterangan'] = QuestionDefaultOptionsService::getDefaultInstruction($validated['type']);
        }

        $pertanyaan = KuesionerPertanyaan::create([
            'kode_pertanyaan' => trim($validated['kode_pertanyaan']),
            'teks_pertanyaan' => trim($validated['subpertanyaan']),
            'tipe' => $validated['type'],
            'keterangan' => $validated['keterangan'],
            'pemilik_prodi_id' => null,
            'is_reusable' => true,
            'is_active' => true,
        ]);

        KuesionerSectionPertanyaan::create([
            'section_id' => $sectionId,
            'pertanyaan_id' => $pertanyaan->id,
            'order' => (int) $validated['order'],
            'wajib' => (bool) $validated['wajib'],
            'tampil_di' => $validated['tampil_di'],
        ]);

        // Otomatis buatkan opsi pilihan default jika pertanyaan mendukung opsi
        QuestionDefaultOptionsService::generateForUnifiedQuestion($pertanyaan);

        return redirect()->back()->with('success', 'Pertanyaan baru beserta pilihan opsi jawaban default berhasil ditambahkan.');
    }

    /**
     * Memperbarui data butir pertanyaan yang sudah ada.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $pertanyaan = KuesionerPertanyaan::findOrFail($id);

        $validated = $request->validate([
            'section_id' => 'required|exists:kuesioner_section,id',
            'kode_pertanyaan' => 'required|string|max:50|unique:kuesioner_pertanyaan,kode_pertanyaan,'.$pertanyaan->id,
            'subpertanyaan' => 'required|string',
            'type' => 'required|string|in:radio,single_choice,radio_input,radio_text,multiple_choice,checkbox,multiple_choice_text,rating_5,multiple_number,matrix,matrix_dual,multiple_textbox,text,textarea,number,date,time,dropdown,searchable_select,file,header',
            'keterangan' => 'nullable|string|max:500',
            'wajib' => 'required|boolean',
            'tampil_di' => 'nullable|string|in:kuesioner,profile,profil,both,hidden,none',
            'order' => 'nullable|integer',
        ]);

        $sectionId = (int) $validated['section_id'];

        $pertanyaan->update([
            'kode_pertanyaan' => trim($validated['kode_pertanyaan']),
            'teks_pertanyaan' => trim($validated['subpertanyaan']),
            'tipe' => $validated['type'],
            'keterangan' => $validated['keterangan'] ?? $pertanyaan->keterangan,
        ]);

        $sp = KuesionerSectionPertanyaan::where('pertanyaan_id', $pertanyaan->id)->first();
        if ($sp) {
            $sp->update([
                'section_id' => $sectionId,
                'order' => $validated['order'] ?? $sp->order,
                'wajib' => (bool) $validated['wajib'],
                'tampil_di' => $validated['tampil_di'] ?? $sp->tampil_di,
            ]);
        } else {
            KuesionerSectionPertanyaan::create([
                'section_id' => $sectionId,
                'pertanyaan_id' => $pertanyaan->id,
                'order' => $validated['order'] ?? 1,
                'wajib' => (bool) $validated['wajib'],
                'tampil_di' => $validated['tampil_di'] ?? 'kuesioner',
            ]);
        }

        return redirect()->back()->with('success', 'Data pertanyaan berhasil diperbarui.');
    }

    /**
     * Menghapus butir pertanyaan.
     */
    public function destroy(int $id): RedirectResponse
    {
        $pertanyaan = KuesionerPertanyaan::findOrFail($id);

        KuesionerSectionPertanyaan::where('pertanyaan_id', $pertanyaan->id)->delete();

        if ($pertanyaan->jawaban()->count() === 0) {
            $pertanyaan->opsi()->delete();
            $pertanyaan->delete();
        }

        return redirect()->back()->with('success', 'Pertanyaan berhasil dihapus.');
    }

    /**
     * Mengubah status aktif master pertanyaan tanpa menghapus penempatan atau jawaban historis.
     */
    public function toggleActive(int $id): RedirectResponse
    {
        $pertanyaan = KuesionerPertanyaan::findOrFail($id);
        $pertanyaan->update(['is_active' => ! $pertanyaan->is_active]);

        $status = $pertanyaan->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()->back()->with('success', "Pertanyaan [{$pertanyaan->kode_pertanyaan}] berhasil {$status}.");
    }

    /**
     * Mengatur ulang urutan butir pertanyaan.
     */
    public function reorder(Request $request): RedirectResponse
    {
        if ($request->has(['id', 'direction'])) {
            $validated = $request->validate([
                'id' => 'required|exists:kuesioner_pertanyaan,id',
                'direction' => 'required|in:up,down',
            ]);

            $currentSp = KuesionerSectionPertanyaan::where('pertanyaan_id', $validated['id'])->firstOrFail();
            $operator = $validated['direction'] === 'up' ? '<' : '>';
            $sortOrder = $validated['direction'] === 'up' ? 'desc' : 'asc';

            $adjacentSp = KuesionerSectionPertanyaan::where('section_id', $currentSp->section_id)
                ->where('order', $operator, $currentSp->order)
                ->orderBy('order', $sortOrder)
                ->first();

            if ($adjacentSp) {
                $tempOrder = $currentSp->order;
                $currentSp->update(['order' => $adjacentSp->order]);
                $adjacentSp->update(['order' => $tempOrder]);
            }

            return redirect()->back()->with('success', 'Urutan pertanyaan berhasil diperbarui.');
        }

        if ($request->has('orders')) {
            $validated = $request->validate([
                'orders' => 'required|array',
                'orders.*.id' => 'required|exists:kuesioner_pertanyaan,id',
                'orders.*.order' => 'required|integer',
            ]);

            foreach ($validated['orders'] as $item) {
                KuesionerSectionPertanyaan::where('pertanyaan_id', $item['id'])->update(['order' => $item['order']]);
            }

            return redirect()->back()->with('success', 'Seluruh urutan pertanyaan berhasil diperbarui.');
        }

        return redirect()->back()->withErrors(['message' => 'Parameter pengurutan tidak valid.']);
    }

    /**
     * Mengubah visibilitas tampilnya butir pertanyaan (di Profil Alumni atau Kuesioner Tracer).
     */
    public function toggleVisibility(Request $request, int $id): RedirectResponse
    {
        $pertanyaan = KuesionerPertanyaan::findOrFail($id);
        $sp = KuesionerSectionPertanyaan::where('pertanyaan_id', $pertanyaan->id)->firstOrFail();
        $target = $request->input('target', 'profil');
        $current = $sp->tampil_di ?? 'kuesioner';

        if ($target === 'profil') {
            $isCurrentlyInProfile = in_array($current, ['profil', 'profile', 'both'], true);

            if ($isCurrentlyInProfile) {
                $newTampilDi = ($current === 'both') ? 'kuesioner' : 'hidden';
                $message = 'Pertanyaan ['.$pertanyaan->kode_pertanyaan.'] disembunyikan dari Profil Alumni.';
            } else {
                $newTampilDi = ($current === 'kuesioner') ? 'both' : 'profil';
                $message = 'Pertanyaan ['.$pertanyaan->kode_pertanyaan.'] kini ditampilkan di Profil Alumni.';
            }
        } else {
            $isCurrentlyInTracer = in_array($current, ['kuesioner', 'both'], true) || empty($current);

            if ($isCurrentlyInTracer) {
                $newTampilDi = ($current === 'both') ? 'profil' : 'hidden';
                $message = 'Pertanyaan ['.$pertanyaan->kode_pertanyaan.'] disembunyikan dari Kuesioner Tracer.';
            } else {
                $newTampilDi = ($current === 'profil') ? 'both' : 'kuesioner';
                $message = 'Pertanyaan ['.$pertanyaan->kode_pertanyaan.'] kini ditampilkan di Kuesioner Tracer.';
            }
        }

        $sp->update(['tampil_di' => $newTampilDi]);

        return redirect()->back()->with('success', $message);
    }

    /**
     * Menyematkan pertanyaan dari Bank Soal ke bagian (section) instrumen universitas.
     */
    public function attachFromBank(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'section_id' => 'required|exists:kuesioner_section,id',
            'pertanyaan_ids' => 'required|array|min:1',
            'pertanyaan_ids.*' => 'required|exists:kuesioner_pertanyaan,id',
        ]);

        $sectionId = (int) $validated['section_id'];
        $selectedQuestions = KuesionerPertanyaan::whereIn('id', $validated['pertanyaan_ids'])->get();

        $maxOrder = (int) (KuesionerSectionPertanyaan::where('section_id', $sectionId)->max('order') ?: 0);
        $addedCount = 0;

        foreach ($selectedQuestions as $bankQuestion) {
            $maxOrder++;

            $created = KuesionerSectionPertanyaan::firstOrCreate(
                [
                    'section_id' => $sectionId,
                    'pertanyaan_id' => $bankQuestion->id,
                ],
                [
                    'order' => $maxOrder,
                    'wajib' => true,
                    'tampil_di' => 'kuesioner',
                    'is_active' => true,
                ]
            );

            if ($created->wasRecentlyCreated) {
                $addedCount++;
            }
        }

        return redirect()->back()->with('success', "{$addedCount} butir pertanyaan dari Bank Soal berhasil disematkan ke instrumen kuesioner universitas.");
    }
}
