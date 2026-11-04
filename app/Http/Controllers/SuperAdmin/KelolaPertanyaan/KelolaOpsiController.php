<?php

namespace App\Http\Controllers\SuperAdmin\KelolaPertanyaan;

use App\Http\Controllers\Controller;
use App\Models\KuesionerOpsi;
use App\Models\KuesionerPertanyaan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Class KelolaOpsiController
 *
 * Fungsi:
 * Controller khusus untuk menangani aksi pengelolaan opsi pilihan jawaban pada pertanyaan:
 * 1. Tambah pilihan opsi jawaban baru (storeOption)
 * 2. Perbarui data opsi jawaban dan target lompatan alur branching (updateOption)
 * 3. Hapus opsi pilihan jawaban (destroyOption)
 */
class KelolaOpsiController extends Controller
{
    /**
     * Menyimpan opsi pilihan jawaban baru untuk butir pertanyaan tertentu.
     */
    public function storeOption(Request $request, int $questionId): RedirectResponse
    {
        $pertanyaan = KuesionerPertanyaan::findOrFail($questionId);

        $validated = $request->validate([
            'option_text' => 'required|string',
            'score' => 'nullable|integer',
            'value' => 'nullable|string',
            'jump_to' => 'nullable|string|max:50',
            'order' => 'nullable|integer',
        ]);

        $order = $validated['order'] ?? (($pertanyaan->opsi()->max('order') ?? 0) + 1);
        $score = is_numeric($validated['score'] ?? null)
            ? (int) $validated['score']
            : (is_numeric($validated['value'] ?? null) ? (int) $validated['value'] : null);

        $pertanyaan->opsi()->create([
            'label' => trim($validated['option_text']),
            'nilai_skor' => $score,
            'lompat_ke_pertanyaan_id' => $validated['jump_to'] ?: null,
            'order' => (int) $order,
        ]);

        return redirect()->back()->with('success', 'Pilihan opsi jawaban berhasil ditambahkan.');
    }

    /**
     * Memperbarui data opsi jawaban dan target alur percabangan.
     */
    public function updateOption(Request $request, int $optionId): RedirectResponse
    {
        $option = KuesionerOpsi::findOrFail($optionId);

        $validated = $request->validate([
            'option_text' => 'required|string',
            'score' => 'nullable|integer',
            'value' => 'nullable|string',
            'jump_to' => 'nullable|string|max:50',
            'order' => 'nullable|integer',
        ]);

        $score = is_numeric($validated['score'] ?? null)
            ? (int) $validated['score']
            : (is_numeric($validated['value'] ?? null) ? (int) $validated['value'] : null);

        $option->update([
            'label' => trim($validated['option_text']),
            'nilai_skor' => $score ?? $option->nilai_skor,
            'lompat_ke_pertanyaan_id' => $validated['jump_to'] ?: null,
            'order' => $validated['order'] ?? $option->order,
        ]);

        return redirect()->back()->with('success', 'Pilihan opsi jawaban berhasil diperbarui.');
    }

    /**
     * Menghapus opsi pilihan jawaban.
     */
    public function destroyOption(int $optionId): RedirectResponse
    {
        $option = KuesionerOpsi::findOrFail($optionId);
        $option->delete();

        return redirect()->back()->with('success', 'Pilihan opsi jawaban berhasil dihapus.');
    }
}
