<?php

namespace App\Http\Controllers\AdminProdi\KelolaPertanyaan;

use App\Http\Controllers\Controller;
use App\Models\KuesionerOpsi;
use App\Models\KuesionerPertanyaan;
use App\Models\KuesionerSection;
use App\Models\KuesionerSectionPertanyaan;
use App\Services\Kuesioner\QuestionDefaultOptionsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Controller untuk menangani aksi Tambah, Update, dan Hapus butir pertanyaan kuesioner program studi.
 */
class SimpanPertanyaanProdiController extends Controller
{
    /**
     * Menyimpan butir pertanyaan kuesioner prodi baru.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user->prodi_id) {
            return redirect()->back()->with('error', 'Akun Anda belum terhubung dengan program studi.');
        }

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('kuesioner_pertanyaan', 'kode_pertanyaan')->where('pemilik_prodi_id', $user->prodi_id),
            ],
            'question_text' => 'required|string',
            'type' => 'required|string|in:text,textarea,number,single_choice,radio,radio_input,radio_text,multiple_choice,checkbox,dropdown,searchable_select,rating_5,multiple_number,matrix,matrix_dual,multiple_textbox,date,time,file,header',
            'is_required' => 'boolean',
            'prodi_question_section_id' => 'nullable|exists:kuesioner_section,id',
            'order' => 'nullable|integer',
        ]);

        if ($validated['type'] === 'radio') {
            $validated['type'] = 'single_choice';
        }
        if ($validated['type'] === 'checkbox') {
            $validated['type'] = 'multiple_choice';
        }

        // Resolusi section
        if (empty($validated['prodi_question_section_id'])) {
            $section = KuesionerSection::where('target', 'prodi')
                ->where('prodi_id', $user->prodi_id)
                ->orderBy('order', 'asc')
                ->first();

            if (! $section) {
                $section = KuesionerSection::create([
                    'target' => 'prodi',
                    'prodi_id' => $user->prodi_id,
                    'kode_section' => 'PRODI_'.$user->prodi_id.'_SEC1',
                    'judul' => 'Bagian Evaluasi & Relevansi Program Studi',
                    'order' => 1,
                    'is_active' => true,
                ]);
            }
            $sectionId = $section->id;
        } else {
            $section = KuesionerSection::where('id', $validated['prodi_question_section_id'])
                ->where('target', 'prodi')
                ->where('prodi_id', $user->prodi_id)
                ->firstOrFail();
            $sectionId = $section->id;
        }

        $order = $validated['order'] ?? null;
        if (empty($order)) {
            $maxOrder = KuesionerSectionPertanyaan::where('section_id', $sectionId)->max('order') ?? 0;
            $order = $maxOrder + 1;
        }

        $question = KuesionerPertanyaan::create([
            'kode_pertanyaan' => trim($validated['code']),
            'teks_pertanyaan' => trim($validated['question_text']),
            'tipe' => $validated['type'],
            'pemilik_prodi_id' => $user->prodi_id,
            'is_reusable' => true,
            'is_active' => true,
        ]);

        KuesionerSectionPertanyaan::create([
            'section_id' => $sectionId,
            'pertanyaan_id' => $question->id,
            'order' => $order,
            'wajib' => $request->boolean('is_required', true),
        ]);

        // Jika terdapat opsi manual yang dikirimkan bersama form
        if ($request->has('options') && is_array($request->options) && count($request->options) > 0) {
            $orderCounter = 1;
            foreach ($request->options as $idx => $optText) {
                $trimmed = trim((string) $optText);
                if ($trimmed !== '') {
                    KuesionerOpsi::create([
                        'pertanyaan_id' => $question->id,
                        'kode_opsi' => $question->kode_pertanyaan.'-'.str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT),
                        'label' => $trimmed,
                        'order' => $orderCounter++,
                    ]);
                }
            }
        } else {
            QuestionDefaultOptionsService::generateForUnifiedQuestion($question);
        }

        return redirect()->back()->with('success', 'Pertanyaan kuesioner prodi beserta pilihan opsi jawaban default berhasil ditambahkan.');
    }

    /**
     * Memperbarui data butir pertanyaan kuesioner prodi.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();
        $question = KuesionerPertanyaan::where('id', $id)
            ->where('pemilik_prodi_id', $user->prodi_id)
            ->firstOrFail();

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('kuesioner_pertanyaan', 'kode_pertanyaan')
                    ->where('pemilik_prodi_id', $user->prodi_id)
                    ->ignore($question->id),
            ],
            'question_text' => 'required|string',
            'type' => 'required|string|in:text,textarea,number,single_choice,radio,radio_input,radio_text,multiple_choice,checkbox,dropdown,searchable_select,rating_5,multiple_number,matrix,matrix_dual,multiple_textbox,date,time,file,header',
            'is_required' => 'boolean',
            'prodi_question_section_id' => 'nullable|exists:kuesioner_section,id',
            'order' => 'nullable|integer',
        ]);

        if ($validated['type'] === 'radio') {
            $validated['type'] = 'single_choice';
        }
        if ($validated['type'] === 'checkbox') {
            $validated['type'] = 'multiple_choice';
        }

        $question->update([
            'kode_pertanyaan' => trim($validated['code']),
            'teks_pertanyaan' => trim($validated['question_text']),
            'tipe' => $validated['type'],
        ]);

        if (! empty($validated['prodi_question_section_id'])) {
            KuesionerSection::where('id', $validated['prodi_question_section_id'])
                ->where('target', 'prodi')
                ->where('prodi_id', $user->prodi_id)
                ->firstOrFail();

            $pivot = KuesionerSectionPertanyaan::where('pertanyaan_id', $question->id)->first();
            if ($pivot) {
                $pivot->update([
                    'section_id' => $validated['prodi_question_section_id'],
                    'order' => $validated['order'] ?? $pivot->order,
                    'wajib' => $request->boolean('is_required', true),
                ]);
            } else {
                KuesionerSectionPertanyaan::create([
                    'section_id' => $validated['prodi_question_section_id'],
                    'pertanyaan_id' => $question->id,
                    'order' => $validated['order'] ?? 1,
                    'wajib' => $request->boolean('is_required', true),
                ]);
            }
        }

        return redirect()->back()->with('success', 'Pertanyaan kuesioner prodi berhasil diperbarui.');
    }

    /**
     * Menghapus butir pertanyaan kuesioner prodi beserta seluruh opsinya.
     */
    public function destroy(int $id): RedirectResponse
    {
        $user = Auth::user();
        $question = KuesionerPertanyaan::where('id', $id)
            ->where('pemilik_prodi_id', $user->prodi_id)
            ->firstOrFail();

        $question->opsi()->delete();
        KuesionerSectionPertanyaan::where('pertanyaan_id', $question->id)->delete();
        $question->delete();

        return redirect()->back()->with('success', 'Pertanyaan kuesioner prodi berhasil dihapus.');
    }

    /**
     * Mengatur ulang urutan posisi butir pertanyaan kuesioner prodi.
     */
    public function reorder(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $prodiId = $user->prodi_id;

        if ($request->has(['id', 'direction'])) {
            $validated = $request->validate([
                'id' => 'required|exists:kuesioner_pertanyaan,id',
                'direction' => 'required|in:up,down',
            ]);

            $currentPivot = KuesionerSectionPertanyaan::where('pertanyaan_id', $validated['id'])->firstOrFail();

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

            return redirect()->back()->with('success', 'Urutan pertanyaan berhasil dipindahkan.');
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

            return redirect()->back()->with('success', 'Seluruh urutan pertanyaan berhasil diperbarui.');
        }

        return redirect()->back()->withErrors(['message' => 'Parameter pengurutan tidak valid.']);
    }
}
