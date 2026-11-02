<?php

namespace App\Http\Controllers\SuperAdmin\KelolaSection;

use App\Http\Controllers\Controller;
use App\Models\Kuesioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Class KelolaKuesionerController
 *
 * Mengelola instrumen Kuesioner Induk Tracer Study bagi Super Admin.
 * Operasi:
 * 1. Tambah kuesioner baru (store)
 * 2. Perbarui kuesioner (update)
 * 3. Hapus kuesioner (destroy)
 * 4. Toggle status aktif kuesioner (toggleActive)
 */
class KelolaKuesionerController extends Controller
{
    /**
     * Menyimpan kuesioner induk baru.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'year' => 'required|integer|min:2000|max:2100',
            'is_active' => 'nullable|boolean',
            'target_response_rate' => 'nullable|numeric|min:1|max:100',
        ]);

        $isActive = $request->boolean('is_active', true);
        $targetResponseRate = isset($validated['target_response_rate']) ? (float) $validated['target_response_rate'] : 60.0;

        // Jika diset sebagai aktif, nonaktifkan kuesioner lain agar konsisten
        if ($isActive) {
            Kuesioner::query()->update(['is_active' => false]);
        }

        Kuesioner::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'year' => $validated['year'],
            'is_active' => $isActive,
            'target_response_rate' => $targetResponseRate,
        ]);

        return redirect()->back()->with('success', 'Kuesioner induk baru berhasil ditambahkan.');
    }

    /**
     * Memperbarui data kuesioner induk yang sudah ada.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $kuesioner = Kuesioner::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'year' => 'required|integer|min:2000|max:2100',
            'is_active' => 'nullable|boolean',
            'target_response_rate' => 'nullable|numeric|min:1|max:100',
        ]);

        $isActive = $request->boolean('is_active', $kuesioner->is_active);
        $targetResponseRate = isset($validated['target_response_rate']) ? (float) $validated['target_response_rate'] : ($kuesioner->target_response_rate ?? 60.0);

        // Jika diset sebagai aktif, nonaktifkan kuesioner lain
        if ($isActive && ! $kuesioner->is_active) {
            Kuesioner::where('id', '!=', $id)->update(['is_active' => false]);
        }

        $kuesioner->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'year' => $validated['year'],
            'is_active' => $isActive,
            'target_response_rate' => $targetResponseRate,
        ]);

        return redirect()->back()->with('success', 'Data kuesioner induk berhasil diperbarui.');
    }

    /**
     * Mengubah status aktif/nonaktif kuesioner dengan cepat.
     */
    public function toggleActive(int $id): RedirectResponse
    {
        $kuesioner = Kuesioner::findOrFail($id);
        $newStatus = ! $kuesioner->is_active;

        if ($newStatus) {
            // Nonaktifkan kuesioner lain jika yang ini diaktifkan
            Kuesioner::where('id', '!=', $id)->update(['is_active' => false]);
        }

        $kuesioner->update(['is_active' => $newStatus]);

        $statusText = $newStatus ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()->back()->with('success', "Kuesioner \"{$kuesioner->title}\" berhasil {$statusText}.");
    }

    /**
     * Menghapus kuesioner beserta relasinya jika diizinkan.
     */
    public function destroy(int $id): RedirectResponse
    {
        $kuesioner = Kuesioner::withCount('kelompokPertanyaan')->findOrFail($id);

        // Cek apakah kuesioner memiliki seksi / bagian
        if ($kuesioner->kelompok_pertanyaan_count > 0) {
            // Cek apakah ada opsi paksa hapus atau peringatan
            // Untuk keamanan integritas data, kuesioner dengan seksi dan pertanyaan di-cascade secara aman
            foreach ($kuesioner->kelompokPertanyaan as $section) {
                foreach ($section->subpertanyaans as $sub) {
                    $sub->detils()->delete();
                }
                $section->subpertanyaans()->delete();
            }
            $kuesioner->kelompokPertanyaan()->delete();
        }

        $title = $kuesioner->title;
        $kuesioner->delete();

        return redirect()->back()->with('success', "Kuesioner \"{$title}\" berhasil dihapus.");
    }
}
