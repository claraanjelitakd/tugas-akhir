<script setup>
/**
 * Panel jawaban terbuka program studi.
 * Jawaban tidak diringkas menjadi angka sehingga konteks masukan alumni tetap
 * dapat dibaca lengkap pada tab visualisasi maupun tab data.
 */
import { computed } from 'vue';

// Menerima visualisasi text dan records yang sudah dinormalisasi backend.
const props = defineProps({
    items: { type: Array, default: () => [] },
});

// Menggabungkan respons seluruh pertanyaan terbuka untuk ringkasan visual.
const responses = computed(() => props.items.flatMap((item) => item.records || []));
</script>

<template>
    <!-- Caption menjelaskan mengapa jawaban text ditampilkan sebagai daftar, bukan grafik angka. -->
    <section class="space-y-4">
        <p class="text-xs leading-5 text-slate-600">Jawaban terbuka ditampilkan apa adanya agar pembaca dapat menilai konteks harapan dan masukan alumni.</p>
        <div v-if="responses.length" class="grid gap-3 lg:grid-cols-2">
            <article v-for="record in responses" :key="record.response_id" class="min-w-0 rounded-xl border border-slate-200 bg-white p-4">
                <div class="flex min-w-0 items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-xs font-bold text-slate-900">{{ record.nama }}</p>
                        <p class="mt-0.5 text-[11px] text-slate-500">{{ record.nim }} · {{ record.tahun_lulus }}</p>
                    </div>
                    <span class="shrink-0 text-[11px] text-slate-400">{{ record.kode }}</span>
                </div>
                <p class="mt-3 whitespace-pre-line break-words text-sm leading-6 text-slate-700">{{ record.jawaban_label }}</p>
                <p class="mt-3 border-t border-slate-100 pt-2 text-[11px] leading-5 text-slate-500">{{ record.aspek }}</p>
            </article>
        </div>
        <p v-else class="rounded-lg border border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">Belum ada jawaban terbuka yang valid.</p>
    </section>
</template>
