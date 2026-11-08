<script setup>
/**
 * Bagian 09: Harapan dan Masukan Program Studi (Saran & Aspirasi Alumni).
 *
 * Menghadirkan visualisasi Word Cloud interaktif untuk memetakan kata kunci,
 * isu utama, dan aspirasi yang paling sering disampaikan alumni secara visual,
 * disertai kartu respon utuh yang terhubung mulus (cross-filtering) saat kata diklik.
 */
import { computed, ref } from 'vue';
import AnalyticsWordCloud from '@/components/analytics/AnalyticsWordCloud.vue';

// Daftar pertanyaan terbuka dan records jawaban dari payload analitik.
const props = defineProps({
    items: { type: Array, default: () => [] },
});

// Menggabungkan respons dari dua pertanyaan terbuka pada bagian ini.
const responses = computed(() => props.items.flatMap((item) => item.records || []));

// Ekstraksi kumpulan teks untuk input Word Cloud
const allFeedbackTexts = computed(() => {
    return responses.value.map((r) => r.jawaban_label || r.answer || '').filter((t) => t.trim() !== '');
});

// State kata kunci yang sedang difilter dari Word Cloud
const selectedKeyword = ref('');

// Filter kartu respon berdasarkan kata kunci aktif
const filteredResponses = computed(() => {
    if (!selectedKeyword.value) return responses.value;
    const q = selectedKeyword.value.toLowerCase();
    return responses.value.filter((r) => {
        const text = String(r.jawaban_label || r.answer || '').toLowerCase();
        return text.includes(q);
    });
});

const handleWordSelect = (word) => {
    selectedKeyword.value = word;
};

const handleWordReset = () => {
    selectedKeyword.value = '';
};
</script>

<template>
    <!-- Jawaban dibaca sebagai data kualitatif dengan visualisasi Word Cloud interaktif -->
    <section class="space-y-5">
        <!-- Visualisasi Word Cloud Aspirasi & Saran Alumni -->
        <AnalyticsWordCloud
            v-if="allFeedbackTexts.length > 0"
            :texts="allFeedbackTexts"
            title="Awan Kata (Word Cloud) Aspirasi & Saran Alumni"
            subtitle="Distribusi visual kata kunci paling dominan dari seluruh saran dan harapan yang disampaikan alumni program studi."
            :selected-word="selectedKeyword"
            :height="360"
            @select="handleWordSelect"
            @reset="handleWordReset"
        />

        <!-- Indikator Filter Kata Aktif -->
        <div v-if="selectedKeyword" class="flex items-center justify-between gap-3 rounded-lg border border-emerald-200 bg-emerald-50/60 px-4 py-2.5 text-xs">
            <div class="flex items-center gap-2 text-slate-700">
                <span class="font-semibold text-slate-500">Menyaring respon dengan kata kunci:</span>
                <span class="rounded bg-[#0D542B] px-2.5 py-0.5 font-bold text-white">{{ selectedKeyword }}</span>
                <span class="text-slate-500">({{ filteredResponses.length }} respon ditemukan)</span>
            </div>
            <button
                type="button"
                class="rounded-md border border-slate-200 bg-white px-2.5 py-1 text-xs font-bold text-slate-700 hover:bg-slate-100 cursor-pointer transition-colors"
                @click="handleWordReset"
            >
                Tampilkan Semua Respon
            </button>
        </div>

        <!-- Daftar Kartu Respon Utuh -->
        <div>
            <div class="mb-3 flex items-center justify-between">
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-600">
                    Daftar Masukan & Harapan Lengkap ({{ filteredResponses.length }} alumni)
                </h4>
            </div>

            <div v-if="filteredResponses.length" class="grid gap-3 lg:grid-cols-2">
                <article
                    v-for="record in filteredResponses"
                    :key="record.response_id || record.id"
                    class="min-w-0 rounded-xl border border-slate-200 bg-white p-4 shadow-2xs hover:border-emerald-300 transition-colors"
                >
                    <div class="flex min-w-0 items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-xs font-bold text-slate-900">{{ record.nama }}</p>
                            <p class="mt-0.5 text-[11px] text-slate-500">{{ record.nim }} · {{ record.tahun_lulus }}</p>
                        </div>
                        <span class="shrink-0 rounded bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">{{ record.kode }}</span>
                    </div>
                    <p class="mt-3 whitespace-pre-line break-words text-sm leading-6 text-slate-700">{{ record.jawaban_label }}</p>
                    <p class="mt-3 border-t border-slate-100 pt-2 text-[11px] leading-5 text-slate-500">{{ record.aspek }}</p>
                </article>
            </div>
            <div v-else class="rounded-lg border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">
                <p v-if="selectedKeyword">Tidak ditemukan respon yang memuat kata kunci "{{ selectedKeyword }}".</p>
                <p v-else>Belum ada jawaban terbuka yang valid.</p>
                <button
                    v-if="selectedKeyword"
                    type="button"
                    class="mt-2 text-xs font-bold text-[#0D542B] hover:underline cursor-pointer"
                    @click="handleWordReset"
                >
                    Hapus filter kata kunci
                </button>
            </div>
        </div>
    </section>
</template>
