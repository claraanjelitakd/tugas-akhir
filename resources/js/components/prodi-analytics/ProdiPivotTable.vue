<script setup>
/**
 * ProdiPivotTable
 *
 * Komponen pivot table dinamis dan interaktif untuk seluruh pertanyaan kuesioner prodi aktif.
 * Menyajikan tabulasi distribusi jawaban tanpa kolom redundan berulang (-) dengan membedakan
 * struktur tabel antara skala Likert (skor 1-5) dan pilihan ganda/tunggal.
 *
 * Fitur Interaktif:
 * - Pencarian instan butir pertanyaan dan kategori.
 * - Baris kategori interaktif: klik baris/tombol memicu drill-down data responden.
 * - Progress bar visual proporsi warna institusi UKDW #0D542B.
 */
import { computed, ref } from 'vue';

const props = defineProps({
    pivot: { type: Array, default: () => [] },
});

const emit = defineEmits(['select-category']);

// Kata kunci pencarian tabel pivot
const pivotSearch = ref('');

// Menampilkan label tipe instrumen
const typeLabel = (type) => ({
    rating_5: 'Skala 1-5 (Likert)',
    single_choice: 'Pilihan Tunggal',
    select: 'Pilihan Dropdown',
    multiple_choice: 'Pilihan Ganda',
    text: 'Jawaban Terbuka',
    textarea: 'Jawaban Terbuka Panjang',
}[type] || type || 'Pertanyaan');

// Filter baris pivot berdasarkan kata kunci pencarian
const filteredPivot = computed(() => {
    const query = pivotSearch.value.trim().toLowerCase();
    if (!query) return props.pivot || [];

    return (props.pivot || []).filter((row) => {
        const matchTitle = String(row.question_text || '').toLowerCase().includes(query)
            || String(row.question_label || '').toLowerCase().includes(query)
            || String(row.code || '').toLowerCase().includes(query);

        const matchCategory = (row.categories || []).some((c) =>
            String(c.label || '').toLowerCase().includes(query)
        );

        return matchTitle || matchCategory;
    });
});

// Penanganan klik kategori untuk memfilter data responden pembentuk
const handleCategoryClick = (row, category) => {
    emit('select-category', {
        questionId: row.question_id,
        code: row.code,
        questionText: row.question_text,
        type: row.type,
        label: category.label,
        value: category.value ?? category.label,
    });
};
</script>

<template>
    <section class="space-y-4">
        <!-- Toolbar Kontrol Pencarian Pivot -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-100 pb-3">
            <div class="w-full max-w-md">
                <input
                    v-model="pivotSearch"
                    type="search"
                    class="block w-full rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2 text-xs text-slate-900 outline-none focus:border-[#0D542B] focus:bg-white focus:ring-1 focus:ring-[#0D542B]"
                    placeholder="Cari pertanyaan atau opsi pilihan di pivot..."
                />
            </div>
            <p class="text-xs text-slate-500">
                Menampilkan {{ filteredPivot.length }} dari {{ pivot.length }} pertanyaan
            </p>
        </div>

        <div v-if="filteredPivot.length" class="space-y-4">
            <article
                v-for="row in filteredPivot"
                :key="row.question_id"
                class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xs transition-all"
            >
                <!-- Header Kartu Pertanyaan -->
                <div class="border-b border-slate-200 bg-slate-50/80 px-4 py-3">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <span class="inline-block rounded px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-[#0D542B] border border-emerald-200/60 mb-1">
                                {{ row.code }} · {{ typeLabel(row.type) }}
                            </span>
                            <h4 class="text-sm font-bold text-slate-900 leading-snug">
                                {{ row.question_text }}
                            </h4>
                        </div>

                        <!-- KPI Ringkas di Header Kartu (Bebas dari deretan tanda minus) -->
                        <div class="flex flex-wrap items-center gap-2 self-start sm:self-center shrink-0">
                            <!-- Metrik Khusus Skala Likert -->
                            <template v-if="row.type === 'rating_5'">
                                <span class="inline-flex items-center gap-1 rounded-md bg-white px-2.5 py-1 text-xs font-bold text-slate-800 border border-slate-200 shadow-2xs">
                                    <span class="text-slate-500 font-normal">Rata-rata:</span>
                                    <span class="text-[#0D542B] font-extrabold">{{ typeof row.average === 'number' ? row.average.toFixed(2) : (row.average || '0') }}</span>
                                    <span class="text-slate-400">/ 5</span>
                                </span>
                                <span v-if="row.satisfaction_rate !== undefined" class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-bold text-[#0D542B] border border-emerald-200/80">
                                    <span class="font-normal text-emerald-800">Kepuasan:</span>
                                    {{ row.satisfaction_rate }}%
                                </span>
                            </template>

                            <!-- Metrik Khusus Pilihan -->
                            <template v-else-if="row.top_choice && row.top_choice !== '-'">
                                <span class="inline-flex items-center gap-1 rounded-md bg-white px-2.5 py-1 text-xs text-slate-700 border border-slate-200 shadow-2xs">
                                    <span class="text-slate-500">Terbanyak:</span>
                                    <strong class="text-slate-900 truncate max-w-[180px]">{{ row.top_choice }}</strong>
                                    <span class="text-slate-400">({{ row.top_count }})</span>
                                </span>
                            </template>

                            <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-600">
                                {{ row.valid_count || 0 }} respons
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Tubuh Tabel Pivot: STRUKTUR KOLOM SPESIFIK & INTERAKTIF -->
                <div class="overflow-x-auto">
                    <!-- TAMPILAN 1: UNTUK SKALA 1-5 (LIKERT) -->
                    <table v-if="row.type === 'rating_5'" class="w-full min-w-[640px] text-left text-xs divide-y divide-slate-100">
                        <thead class="bg-slate-50/50 text-[11px] font-semibold text-slate-600 uppercase tracking-wider">
                            <tr>
                                <th class="w-20 px-4 py-2.5 text-center">Skor</th>
                                <th class="px-4 py-2.5">Keterangan Skala</th>
                                <th class="w-28 px-4 py-2.5 text-right">Jumlah Alumni</th>
                                <th class="w-24 px-4 py-2.5 text-right">Persentase</th>
                                <th class="w-48 px-4 py-2.5">Distribusi Proporsi</th>
                                <th class="w-28 px-4 py-2.5 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr
                                v-for="cat in row.categories || []"
                                :key="`${row.question_id}-${cat.label}`"
                                class="hover:bg-emerald-50/30 transition-colors group cursor-pointer"
                                @click="handleCategoryClick(row, cat)"
                            >
                                <td class="px-4 py-2 text-center">
                                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full font-bold text-xs bg-slate-100 text-slate-800 group-hover:bg-[#0D542B] group-hover:text-white transition-colors">
                                        {{ cat.value }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 font-medium text-slate-800">
                                    {{ cat.label.replace(/^\d+\s*-\s*/, '') }}
                                </td>
                                <td class="px-4 py-2 text-right font-mono font-bold text-slate-900">
                                    {{ cat.count }}
                                </td>
                                <td class="px-4 py-2 text-right font-mono text-slate-600">
                                    {{ cat.percentage }}%
                                </td>
                                <td class="px-4 py-2">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                                            <div
                                                class="h-full rounded-full bg-[#0D542B] transition-all duration-300"
                                                :style="{ width: `${cat.percentage}%` }"
                                            ></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] font-semibold text-[#0D542B] hover:bg-[#0D542B] hover:text-white transition-colors shadow-2xs"
                                        @click.stop="handleCategoryClick(row, cat)"
                                    >
                                        Filter Responden
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- TAMPILAN 2: UNTUK PERTANYAAN PILIHAN (CHOICE / MULTIPLE CHOICE) -->
                    <table v-else-if="row.categories && row.categories.length" class="w-full min-w-[640px] text-left text-xs divide-y divide-slate-100">
                        <thead class="bg-slate-50/50 text-[11px] font-semibold text-slate-600 uppercase tracking-wider">
                            <tr>
                                <th class="w-12 px-4 py-2.5 text-center">No</th>
                                <th class="px-4 py-2.5">Opsi Pilihan Jawaban</th>
                                <th class="w-28 px-4 py-2.5 text-right">Jumlah Pilihan</th>
                                <th class="w-24 px-4 py-2.5 text-right">Proporsi</th>
                                <th class="w-48 px-4 py-2.5">Visualisasi Distribusi</th>
                                <th class="w-28 px-4 py-2.5 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr
                                v-for="(cat, idx) in row.categories"
                                :key="`${row.question_id}-${cat.label}`"
                                class="hover:bg-emerald-50/30 transition-colors group cursor-pointer"
                                @click="handleCategoryClick(row, cat)"
                            >
                                <td class="px-4 py-2 text-center font-mono text-slate-400">
                                    {{ idx + 1 }}
                                </td>
                                <td class="px-4 py-2 font-medium text-slate-800">
                                    {{ cat.label }}
                                </td>
                                <td class="px-4 py-2 text-right font-mono font-bold text-slate-900">
                                    {{ cat.count }}
                                </td>
                                <td class="px-4 py-2 text-right font-mono text-slate-600">
                                    {{ cat.percentage }}%
                                </td>
                                <td class="px-4 py-2">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                                            <div
                                                class="h-full rounded-full bg-[#0D542B] transition-all duration-300"
                                                :style="{ width: `${cat.percentage}%` }"
                                            ></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] font-semibold text-[#0D542B] hover:bg-[#0D542B] hover:text-white transition-colors shadow-2xs"
                                        @click.stop="handleCategoryClick(row, cat)"
                                    >
                                        Filter Responden
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- TAMPILAN 3: UNTUK PERTANYAAN TERBUKA / TEXT -->
                    <div v-else class="p-4 bg-slate-50/30 text-xs text-slate-600">
                        <p class="font-medium">
                            Pertanyaan ini berformat jawaban terbuka dengan total <strong>{{ row.valid_count || 0 }} respons</strong> alumni.
                            Daftar isi masukan lengkap dapat Anda telusuri pada tab <strong>Data Responden</strong>.
                        </p>
                    </div>
                </div>
            </article>
        </div>

        <p v-else class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500 shadow-2xs">
            Tidak ada pertanyaan atau kategori pivot yang cocok dengan kata kunci pencarian.
        </p>
    </section>
</template>
