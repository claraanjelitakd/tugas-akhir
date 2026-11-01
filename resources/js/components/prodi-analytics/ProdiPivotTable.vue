<script setup>
/**
 * Pivot table dinamis untuk seluruh pertanyaan pada bagian aktif.
 * Tabel ini menjadi fallback analitik ketika ada tipe pertanyaan baru yang
 * belum memiliki visual khusus, sehingga data tetap dapat diperiksa.
 */
// Menerima baris pivot yang dibuat dari metadata pertanyaan dan jawaban valid.
defineProps({
    pivot: { type: Array, default: () => [] },
});

// Menampilkan tipe database dengan istilah yang mudah dipahami pengguna.
const typeLabel = (type) => ({
    rating_5: 'Skala 1-5',
    single_choice: 'Pilihan tunggal',
    select: 'Pilihan tunggal',
    multiple_choice: 'Pilihan ganda',
    text: 'Jawaban terbuka',
    textarea: 'Jawaban terbuka',
}[type] || type || 'Pertanyaan');
</script>

<template>
    <!-- Tabel ringkas per pertanyaan menjaga semua instrumen baru tetap terbaca. -->
    <section class="space-y-3">
        <div v-if="pivot.length" class="space-y-4">
            <article v-for="row in pivot" :key="row.question_id" class="overflow-hidden rounded-xl border border-slate-200">
                <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="min-w-0 flex-1 text-sm font-bold leading-5 text-slate-900">{{ row.question_text }}</p>
                        <span class="shrink-0 text-[11px] font-semibold text-slate-500">{{ typeLabel(row.type) }} · {{ row.code }}</span>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[620px] border-collapse text-xs">
                        <thead class="bg-white text-left text-[11px] uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="border-b border-slate-200 px-4 py-2">Kategori / ukuran</th>
                                <th class="border-b border-slate-200 px-4 py-2 text-right">Jumlah</th>
                                <th class="border-b border-slate-200 px-4 py-2 text-right">Persentase</th>
                                <th class="border-b border-slate-200 px-4 py-2 text-right">Rata-rata</th>
                                <th class="border-b border-slate-200 px-4 py-2 text-right">Median</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!row.categories?.length">
                                <td class="px-4 py-3 text-slate-600">Jawaban valid</td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-900">{{ row.valid_count || 0 }}</td>
                                <td class="px-4 py-3 text-right text-slate-500">-</td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-900">{{ row.average ?? '-' }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-900">{{ row.median ?? '-' }}</td>
                            </tr>
                            <tr v-for="category in row.categories" :key="`${row.question_id}-${category.label}`" class="border-t border-slate-100">
                                <td class="px-4 py-2.5 text-slate-700">{{ category.label }}</td>
                                <td class="px-4 py-2.5 text-right font-semibold text-slate-900">{{ category.count }}</td>
                                <td class="px-4 py-2.5 text-right text-slate-600">{{ category.percentage }}%</td>
                                <td class="px-4 py-2.5 text-right text-slate-500">{{ row.average ?? '-' }}</td>
                                <td class="px-4 py-2.5 text-right text-slate-500">{{ row.median ?? '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>
        </div>
        <p v-else class="rounded-lg border border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">Belum ada data pivot yang dapat ditampilkan.</p>
    </section>
</template>
