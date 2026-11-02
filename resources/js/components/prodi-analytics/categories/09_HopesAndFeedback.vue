<script setup>
/**
 * Bagian 09: Harapan dan Masukan Program Studi.
 *
 * Grafik angka tidak dipaksakan untuk jawaban terbuka. Setiap respons
 * ditampilkan sebagai kartu agar isi saran alumni tetap utuh dan interaktif.
 */
import { computed } from 'vue';

// Daftar pertanyaan terbuka dan records jawaban dari payload analitik.
const props = defineProps({ items: { type: Array, default: () => [] } });

// Menggabungkan respons dari dua pertanyaan terbuka pada bagian ini.
const responses = computed(() => props.items.flatMap((item) => item.records || []));
</script>

<template>
    <!-- Jawaban dibaca sebagai data kualitatif, bukan diringkas menjadi angka. -->
    <section class="space-y-4">
        <p class="text-xs leading-5 text-slate-600">Harapan dan masukan ditampilkan apa adanya agar konteks alumni tetap terbaca.</p>
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
