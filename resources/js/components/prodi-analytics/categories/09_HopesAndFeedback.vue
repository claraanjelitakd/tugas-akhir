<script setup>
/**
 * Bagian 09: Harapan dan Masukan Program Studi (Saran & Aspirasi Alumni).
 *
 * Menghadirkan visualisasi Word Cloud interaktif untuk memetakan kata kunci,
 * isu utama, dan aspirasi yang paling sering disampaikan alumni secara visual,
 * dilengkapi filter kategori terpisah antara Harapan dan Saran/Masukan kurikulum,
 * serta kartu respon utuh yang terhubung mulus (cross-filtering) saat kata diklik.
 */
import { computed, ref } from 'vue';
import AnalyticsWordCloud from '@/components/analytics/AnalyticsWordCloud.vue';

// Daftar pertanyaan terbuka dan records jawaban dari payload analitik.
const props = defineProps({
    items: { type: Array, default: () => [] },
});

// State tab kategori aktif: 'all' (Semua), 'harapan' (Harapan), 'saran' (Saran & Masukan)
const activeTab = ref('all');

// State kata kunci yang sedang difilter dari Word Cloud
const selectedKeyword = ref('');

// Identifikasi pertanyaan Harapan dan Saran
const harapanItem = computed(() => {
    return props.items.find((item) => {
        const title = (item.title || item.question_text || '').toLowerCase();
        return title.includes('harapan') || title.includes('aspirasi') || title.includes('ke depannya');
    }) || props.items[0] || null;
});

const saranItem = computed(() => {
    return props.items.find((item) => {
        const title = (item.title || item.question_text || '').toLowerCase();
        return title.includes('saran') || title.includes('masukan') || title.includes('kualitas');
    }) || props.items[1] || null;
});

// Respon berdasarkan tab kategori yang aktif
const currentResponses = computed(() => {
    if (activeTab.value === 'harapan') {
        return harapanItem.value?.records || [];
    }
    if (activeTab.value === 'saran') {
        return saranItem.value?.records || [];
    }
    // 'all': gabungkan seluruh pertanyaan terbuka
    return props.items.flatMap((item) => item.records || []);
});

// Ekstraksi kumpulan teks untuk input Word Cloud sesuai tab kategori
const feedbackTexts = computed(() => {
    return currentResponses.value
        .map((r) => r.jawaban_label || r.answer || '')
        .filter((t) => t.trim() !== '');
});

// Konfigurasi visualisasi Word Cloud dinamis berdasarkan tab aktif
const cloudConfig = computed(() => {
    if (activeTab.value === 'harapan') {
        return {
            title: 'Awan Kata (Word Cloud) Harapan Alumni ke Depan',
            subtitle: 'Distribusi visual aspirasi, cita-cita, dan harapan alumni bagi kemajuan program studi.',
        };
    }
    if (activeTab.value === 'saran') {
        return {
            title: 'Awan Kata (Word Cloud) Saran & Masukan Peningkatan Mutu',
            subtitle: 'Distribusi visual saran perbaikan kurikulum, laboratorium, sertifikasi industri, dan fasilitas studi.',
        };
    }
    return {
        title: 'Awan Kata (Word Cloud) Aspirasi & Saran Alumni',
        subtitle: 'Distribusi visual kata kunci paling dominan dari seluruh saran dan harapan yang disampaikan alumni program studi.',
    };
});

// Filter kartu respon berdasarkan kata kunci aktif
const filteredResponses = computed(() => {
    if (!selectedKeyword.value) return currentResponses.value;
    const q = selectedKeyword.value.toLowerCase();
    return currentResponses.value.filter((r) => {
        const text = String(r.jawaban_label || r.answer || '').toLowerCase();
        return text.includes(q);
    });
});

const switchTab = (tab) => {
    activeTab.value = tab;
    selectedKeyword.value = '';
};

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
        <!-- Bilah Tab Pemisah Kategori (Semua / Harapan / Saran) -->
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 pb-3">
            <div class="inline-flex rounded-xl bg-slate-100 p-1 text-xs font-semibold">
                <button
                    type="button"
                    @click="switchTab('all')"
                    class="rounded-lg px-3.5 py-1.5 transition-all cursor-pointer"
                    :class="activeTab === 'all'
                        ? 'bg-[#0D542B] text-white shadow-xs font-bold'
                        : 'text-slate-600 hover:text-slate-900'"
                >
                    Semua Masukan ({{ (harapanItem?.records?.length || 0) + (saranItem?.records?.length || 0) }})
                </button>
                <button
                    type="button"
                    @click="switchTab('harapan')"
                    class="rounded-lg px-3.5 py-1.5 transition-all cursor-pointer"
                    :class="activeTab === 'harapan'
                        ? 'bg-[#0D542B] text-white shadow-xs font-bold'
                        : 'text-slate-600 hover:text-slate-900'"
                >
                    Harapan Alumni ({{ harapanItem?.records?.length || 0 }})
                </button>
                <button
                    type="button"
                    @click="switchTab('saran')"
                    class="rounded-lg px-3.5 py-1.5 transition-all cursor-pointer"
                    :class="activeTab === 'saran'
                        ? 'bg-[#0D542B] text-white shadow-xs font-bold'
                        : 'text-slate-600 hover:text-slate-900'"
                >
                    Saran &amp; Masukan ({{ saranItem?.records?.length || 0 }})
                </button>
            </div>

            <p class="text-xs text-slate-500 font-medium">
                Pilih kategori untuk melihat fokus kata kunci harapan vs saran secara terpisah.
            </p>
        </div>

        <!-- Visualisasi Word Cloud Aspirasi & Saran Alumni -->
        <AnalyticsWordCloud
            v-if="feedbackTexts.length > 0"
            :texts="feedbackTexts"
            :title="cloudConfig.title"
            :subtitle="cloudConfig.subtitle"
            :selected-word="selectedKeyword"
            :max-words="150"
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
                    Daftar {{ activeTab === 'harapan' ? 'Harapan' : (activeTab === 'saran' ? 'Saran & Masukan' : 'Masukan & Harapan') }} Lengkap ({{ filteredResponses.length }} alumni)
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
                <p v-else>Belum ada jawaban terbuka yang valid pada kategori ini.</p>
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
