<?php

namespace App\Http\Controllers\SuperAdmin\KelolaSection;

use App\Http\Controllers\Controller;
use App\Models\KuesionerSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Class KelolaSectionController
 *
 * Mengelola siklus hidup Bagian Kuesioner (KuesionerSection target 'univ')
 * pada instrumen Tracer Study universitas bagi SuperAdmin.
 */
class KelolaSectionController extends Controller
{
    /**
     * Menampilkan halaman utama Kelola Section Kuesioner (dialihkan ke halaman kuesioner terpadu tab sections).
     *
     * @param  Request  $request  Objek HTTP request yang masuk
     */
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('superadmin.pertanyaan.index', ['tab' => 'sections']);
    }

    /**
     * Menyimpan data section kuesioner baru ke dalam database.
     *
     * @param  Request  $request  Objek HTTP request berisi data form section
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'order' => 'nullable|integer|min:1',
        ]);

        if (empty($validated['order'])) {
            $maxOrder = KuesionerSection::where('target', 'univ')->max('order') ?? 0;
            $validated['order'] = $maxOrder + 1;
        }

        $code = 'SEC_UNIV_'.str_pad((string) $validated['order'], 2, '0', STR_PAD_LEFT);
        while (KuesionerSection::where('kode_section', $code)->exists()) {
            $code .= '_'.rand(10, 99);
        }

        KuesionerSection::create([
            'kode_section' => $code,
            'target' => 'univ',
            'prodi_id' => null,
            'judul' => $validated['title'],
            'deskripsi' => $validated['description'] ?? null,
            'order' => $validated['order'],
            'is_active' => true,
        ]);

        return redirect()->back()->with('success', 'Bagian kuesioner baru berhasil ditambahkan.');
    }

    /**
     * Memperbarui data judul atau urutan section kuesioner yang sudah ada.
     *
     * @param  Request  $request  Objek HTTP request berisi data pembaruan
     * @param  int  $id  ID primary key dari KuesionerSection yang diperbarui
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $section = KuesionerSection::where('target', 'univ')->findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'order' => 'nullable|integer|min:1',
        ]);

        $updateData = [
            'judul' => $validated['title'],
            'deskripsi' => $validated['description'] ?? null,
        ];

        if (! empty($validated['order'])) {
            $updateData['order'] = $validated['order'];
        }

        $section->update($updateData);

        return redirect()->back()->with('success', 'Data bagian kuesioner berhasil diperbarui.');
    }

    /**
     * Menghapus section kuesioner beserta relasi pivot pemetaannya.
     *
     * @param  int  $id  ID primary key KuesionerSection yang akan dihapus
     */
    public function destroy(int $id): RedirectResponse
    {
        $section = KuesionerSection::where('target', 'univ')->findOrFail($id);

        // Lepaskan pivot pemetaan pertanyaan terlebih dahulu
        $section->pertanyaan()->detach();

        // Hapus section
        $section->delete();

        return redirect()->back()->with('success', 'Bagian kuesioner berhasil dihapus.');
    }

    /**
     * Memindahkan atau mengatur ulang urutan posisi section.
     *
     * @param  Request  $request  Objek HTTP request berisi instruksi pengurutan
     */
    public function reorder(Request $request): RedirectResponse
    {
        if ($request->has(['id', 'direction'])) {
            $validated = $request->validate([
                'id' => 'required|exists:kuesioner_section,id',
                'direction' => 'required|in:up,down',
            ]);

            $currentSection = KuesionerSection::where('target', 'univ')->findOrFail($validated['id']);
            $operator = $validated['direction'] === 'up' ? '<' : '>';
            $sortOrder = $validated['direction'] === 'up' ? 'desc' : 'asc';

            $adjacentSection = KuesionerSection::where('target', 'univ')
                ->where('order', $operator, $currentSection->order)
                ->orderBy('order', $sortOrder)
                ->first();

            if ($adjacentSection) {
                $tempOrder = $currentSection->order;
                $currentSection->update(['order' => $adjacentSection->order]);
                $adjacentSection->update(['order' => $tempOrder]);
            }

            return redirect()->back()->with('success', 'Urutan bagian kuesioner berhasil dipindahkan.');
        }

        if ($request->has('orders')) {
            $validated = $request->validate([
                'orders' => 'required|array',
                'orders.*.id' => 'required|exists:kuesioner_section,id',
                'orders.*.order' => 'required|integer',
            ]);

            foreach ($validated['orders'] as $item) {
                KuesionerSection::where('target', 'univ')
                    ->where('id', $item['id'])
                    ->update(['order' => $item['order']]);
            }

            return redirect()->back()->with('success', 'Seluruh susunan urutan bagian kuesioner berhasil diperbarui.');
        }

        return redirect()->back()->withErrors(['message' => 'Parameter pengurutan tidak valid.']);
    }
}
