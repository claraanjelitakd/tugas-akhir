<?php

namespace App\Http\Controllers\SuperAdmin\KelolaPertanyaan;

use App\Http\Controllers\Controller;
use App\Models\KuesionerOpsi;
use App\Models\KuesionerPertanyaan;
use App\Models\LogActivity;
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
            'code' => 'required|string|max:50',
            'option_text' => 'required|string',
            'jump_to' => 'nullable|string|max:50',
            'order' => 'nullable|integer',
        ]);

        $order = $validated['order'] ?? (($pertanyaan->opsi()->max('order') ?? 0) + 1);

        $option = $pertanyaan->opsi()->create([
            'kode_opsi' => trim($validated['code']),
            'label' => trim($validated['option_text']),
            'nilai_skor' => $pertanyaan->tipe === 'rating_5' ? (int) $order : null,
            'lompat_ke_pertanyaan_id' => $validated['jump_to'] ?? null,
            'order' => (int) $order,
        ]);

        LogActivity::record('KUESIONER_UNIV_OPTION_CREATED', 'Menambahkan opsi jawaban pada pertanyaan '.$pertanyaan->kode_pertanyaan.'.', $option, null, $validated);

        return redirect()->back()->with('success', 'Pilihan opsi jawaban berhasil ditambahkan.');
    }

    /**
     * Memperbarui data opsi jawaban dan target alur percabangan.
     */
    public function updateOption(Request $request, int $optionId): RedirectResponse
    {
        $option = KuesionerOpsi::findOrFail($optionId);

        $validated = $request->validate([
            'code' => 'required|string|max:50',
            'option_text' => 'required|string',
            'jump_to' => 'nullable|string|max:50',
            'order' => 'nullable|integer',
        ]);

        $order = $validated['order'] ?? $option->order;

        $option->update([
            'kode_opsi' => trim($validated['code']),
            'label' => trim($validated['option_text']),
            'nilai_skor' => $option->pertanyaan?->tipe === 'rating_5' ? (int) $order : null,
            'lompat_ke_pertanyaan_id' => $validated['jump_to'] ?? null,
            'order' => $order,
        ]);

        LogActivity::record('KUESIONER_UNIV_OPTION_UPDATED', 'Memperbarui opsi jawaban pada pertanyaan '.($option->pertanyaan?->kode_pertanyaan ?? '-').'.', $option, null, $validated);

        return redirect()->back()->with('success', 'Pilihan opsi jawaban berhasil diperbarui.');
    }

    /**
     * Menghapus opsi pilihan jawaban.
     */
    public function destroyOption(int $optionId): RedirectResponse
    {
        $option = KuesionerOpsi::findOrFail($optionId);
        $questionCode = $option->pertanyaan?->kode_pertanyaan;
        $option->delete();

        LogActivity::record('KUESIONER_UNIV_OPTION_DELETED', 'Menghapus opsi jawaban pada pertanyaan '.$questionCode.'.', null, $option->toArray());

        return redirect()->back()->with('success', 'Pilihan opsi jawaban berhasil dihapus.');
    }
}
