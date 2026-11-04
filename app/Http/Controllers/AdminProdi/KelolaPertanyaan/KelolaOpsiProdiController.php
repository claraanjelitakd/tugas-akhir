<?php

namespace App\Http\Controllers\AdminProdi\KelolaPertanyaan;

use App\Http\Controllers\Controller;
use App\Models\KuesionerOpsi;
use App\Models\KuesionerPertanyaan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Controller untuk mengelola opsi jawaban pada pertanyaan kuesioner program studi.
 */
class KelolaOpsiProdiController extends Controller
{
    /**
     * Menambahkan opsi jawaban baru pada pertanyaan kuesioner prodi.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if ($request->has('prodi_question_id') && ! $request->has('question_id')) {
            $request->merge(['question_id' => $request->prodi_question_id]);
        }

        $validated = $request->validate([
            'question_id' => 'required|exists:kuesioner_pertanyaan,id',
            'option_text' => 'required|string',
            'code' => 'nullable|string|max:50',
            'jump_to' => 'nullable|string|max:50',
            'order' => 'nullable|integer',
        ]);

        // Verifikasi pertanyaan milik prodi admin ini
        $question = KuesionerPertanyaan::where('id', $validated['question_id'])
            ->where('pemilik_prodi_id', $user->prodi_id)
            ->firstOrFail();

        if (empty($validated['code'])) {
            $count = $question->opsi()->count() + 1;
            $validated['code'] = $question->kode_pertanyaan.'-'.str_pad((string) $count, 2, '0', STR_PAD_LEFT);
        }

        if (empty($validated['order'])) {
            $validated['order'] = ($question->opsi()->max('order') ?? 0) + 1;
        }

        KuesionerOpsi::create([
            'pertanyaan_id' => $question->id,
            'kode_opsi' => $validated['code'],
            'label' => $validated['option_text'],
            'lompat_ke_pertanyaan_id' => $validated['jump_to'] ?? null,
            'order' => $validated['order'],
        ]);

        return redirect()->back()->with('success', 'Opsi pilihan jawaban berhasil ditambahkan.');
    }

    /**
     * Memperbarui teks atau kode opsi jawaban.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();
        $option = KuesionerOpsi::with('pertanyaan')->findOrFail($id);

        if ($option->pertanyaan->pemilik_prodi_id !== $user->prodi_id) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengubah opsi ini.');
        }

        $validated = $request->validate([
            'option_text' => 'required|string',
            'code' => 'nullable|string|max:50',
            'jump_to' => 'nullable|string|max:50',
            'order' => 'nullable|integer',
        ]);

        $option->update([
            'label' => $validated['option_text'],
            'kode_opsi' => $validated['code'] ?? $option->kode_opsi,
            'lompat_ke_pertanyaan_id' => $validated['jump_to'] ?? $option->lompat_ke_pertanyaan_id,
            'order' => $validated['order'] ?? $option->order,
        ]);

        return redirect()->back()->with('success', 'Opsi pilihan jawaban berhasil diperbarui.');
    }

    /**
     * Menghapus opsi jawaban.
     */
    public function destroy(int $id): RedirectResponse
    {
        $user = Auth::user();
        $option = KuesionerOpsi::with('pertanyaan')->findOrFail($id);

        if ($option->pertanyaan->pemilik_prodi_id !== $user->prodi_id) {
            abort(403, 'Anda tidak memiliki hak akses untuk menghapus opsi ini.');
        }

        $option->delete();

        return redirect()->back()->with('success', 'Opsi pilihan jawaban berhasil dihapus.');
    }

    /**
     * Alias method untuk menyimpan opsi via route pertanyaan/{questionId}/options.
     */
    public function storeOption(Request $request, int $questionId): RedirectResponse
    {
        $request->merge(['question_id' => $questionId]);

        return $this->store($request);
    }
}
