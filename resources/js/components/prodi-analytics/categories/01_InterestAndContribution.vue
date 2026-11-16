<script setup>
/**
 * Bagian 01: Formulir Peminatan dan Kontribusi Alumni.
 *
 * Tiga grafik di bagian ini berasal dari tiga pertanyaan berbeda. Karena itu
 * tab memakai `items`, bukan nama Pertanyaan 1/2/3, sehingga konteks tetap
 * terlihat dan mudah diubah ketika instrumen diperbarui.
 */
import { computed, ref, watch } from 'vue';
import AnalyticsEChart from '@/components/analytics/AnalyticsEChart.vue';
import AnalyticsWordCloud from '@/components/analytics/AnalyticsWordCloud.vue';
import { buildChoiceOption, choiceAnalysis, choiceChartHeight } from '../prodiChartOptions';

// ALUR 6: backend mengirim tiga objek `items`, satu objek untuk setiap
// pertanyaan. Setiap item berisi key, title, categories, values, dan stat.
const props = defineProps({
    items: { type: Array, default: () => [] },
});

// Event klik pilihan diteruskan ke tabel drill-down halaman induk.
const emit = defineEmits(['select', 'context-change']);

// ALUR 7: key item disimpan sebagai tab aktif. Saat tab diklik, activeItem
// berubah sehingga ECharts menerima data pertanyaan yang berbeda.
const activeQuestionKey = ref('');

// Reset tab ke pertanyaan pertama setiap payload filter berubah.
watch(() => props.items, (items) => {
    if (!items.some((item) => item.key === activeQuestionKey.value)) {
        activeQuestionKey.value = items[0]?.key || '';
    }
}, { immediate: true });

// ALUR 8: item aktif menjadi sumber judul tab, label sumbu, nilai batang,
// tooltip, dan analisis. Tidak ada nama grafik yang ditulis manual di sini.
const activeItem = computed(() => props.items.find((item) => item.key === activeQuestionKey.value) || null);

// Judul tab memakai teks lengkap dari database agar konteks pertanyaan tidak hilang.
const tabLabel = (item) => {
    return String(item?.title || 'Pertanyaan');
};

// Memberi tahu halaman induk bahwa KPI harus mengikuti pertanyaan aktif.
watch(activeItem, (item) => emit('context-change', item), { immediate: true });

// Analisis aktif selalu dihitung dari kategori pada pertanyaan yang sedang dibuka.
const activeAnalysis = computed(() => choiceAnalysis(activeItem.value));

// Isian bebas dari opsi Lainnya dipisahkan backend agar tidak menjadi kategori batang.
const otherWordCloud = computed(() => activeItem.value?.other_word_cloud || {});

// Menetapkan tampilan terpisah agar Word Cloud tidak memenuhi grafik distribusi utama.
const activeVisualization = ref('chart');

// Word Cloud tetap ditawarkan jika opsi Lainnya tersedia, meskipun belum ada teks.
const hasOtherWordCloud = computed(() => (otherWordCloud.value.texts || [])
    .some((text) => String(text || '').trim() !== ''));

// Menentukan apakah pertanyaan aktif memang memiliki opsi Lainnya dari master.
const showOtherWordCloudTab = computed(() => activeItem.value?.has_other_option === true || hasOtherWordCloud.value);

// Mengembalikan tampilan ke grafik utama ketika pertanyaan aktif diganti.
watch(activeItem, () => {
    activeVisualization.value = 'chart';
});

// ALUR 9: klik batang mengirim item dan label ke GrafikProdi, lalu halaman
// menyaring records untuk tabel detail alumni yang membentuk pilihan tersebut.
const handleSelect = (params) => {
    const item = activeItem.value;
    const label = item?.categories?.[params?.dataIndex];

    if (!item?.key || !label) {
        return;
    }

    emit('select', { item, label, value: params?.value, dataIndex: params?.dataIndex });
};
</script>

<template>
    <!-- Tiga grafik choice dibaca melalui tab pertanyaan yang lebar dan responsif. -->
    <section class="min-w-0 space-y-4">
        <nav v-if="items.length > 1" class="grid gap-1 rounded-lg border border-slate-200 bg-slate-50 p-1 sm:grid-cols-3" aria-label="Grafik bagian formulir peminatan dan kontribusi">
            <button
                v-for="item in items"
                :key="item.key"
                type="button"
                class="min-h-16 min-w-0 rounded-md px-4 py-3 text-left text-xs font-bold leading-5 transition-colors sm:text-center"
                :class="activeQuestionKey === item.key ? 'bg-[#0D542B] text-white shadow-sm' : 'text-slate-600 hover:bg-white hover:text-slate-900'"
                :title="item.title"
                @click="activeQuestionKey = item.key"
            >
                <span class="block whitespace-normal break-words">{{ tabLabel(item) }}</span>
            </button>
        </nav>

        <template v-if="activeItem">
            <!-- Grafik utama dan Word Cloud Lainnya dipisahkan sebagai tab visualisasi. -->
            <nav v-if="showOtherWordCloudTab" class="inline-flex rounded-lg border border-slate-200 bg-slate-50 p-1" aria-label="Tampilan grafik pertanyaan">
                <button
                    type="button"
                    class="rounded-md px-3 py-1.5 text-xs font-bold transition-colors"
                    :class="activeVisualization === 'chart' ? 'bg-[#0D542B] text-white shadow-sm' : 'text-slate-600 hover:bg-white hover:text-slate-900'"
                    @click="activeVisualization = 'chart'"
                >
                    Grafik Distribusi
                </button>
                <button
                    type="button"
                    class="rounded-md px-3 py-1.5 text-xs font-bold transition-colors"
                    :class="activeVisualization === 'word_cloud' ? 'bg-[#0D542B] text-white shadow-sm' : 'text-slate-600 hover:bg-white hover:text-slate-900'"
                    @click="activeVisualization = 'word_cloud'"
                >
                    Word Cloud Isian Lainnya
                </button>
            </nav>

            <!-- Judul lengkap tetap tampil di bawah tab untuk membantu live coding dan audit konteks. -->
            <div>
                <h3 class="text-base font-bold leading-6 text-slate-900">{{ activeItem.title }}</h3>
                <p class="mt-1 text-xs leading-5 text-slate-500">{{ activeItem.subtitle }}</p>
                <p class="mt-2 text-xs leading-5 text-slate-600">{{ activeItem.caption }}</p>
            </div>

            <div v-if="activeVisualization === 'chart' && activeItem.categories?.length" class="min-w-0 overflow-x-auto">
                <div class="min-w-[720px]">
                    <AnalyticsEChart :option="buildChoiceOption(activeItem)" :height="choiceChartHeight(activeItem)" aria-label="Grafik distribusi bagian formulir peminatan dan kontribusi" @select="handleSelect" />
                </div>
            </div>
            <p v-else-if="activeVisualization === 'chart'" class="rounded-lg border border-slate-200 bg-slate-50 p-5 text-center text-sm text-slate-500">Belum ada pilihan valid untuk pertanyaan ini.</p>

            <!-- Analisis berada tepat di bawah grafik aktif dan tidak memakai teks hardcode. -->
            <section v-if="activeVisualization === 'chart' && activeAnalysis" class="rounded-lg border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-xs font-bold uppercase tracking-wider text-[#0D542B]">Analisis pertanyaan aktif</p>
                <p class="mt-2 text-sm font-semibold leading-6 text-slate-900">{{ activeAnalysis.lead }}</p>
                <p class="mt-1 text-xs leading-5 text-slate-600">{{ activeAnalysis.detail }}</p>
            </section>

            <!-- Semua jawaban Lainnya dibaca sebagai teks, bukan angka kategori. -->
            <AnalyticsWordCloud
                v-if="activeVisualization === 'word_cloud' && hasOtherWordCloud"
                :texts="otherWordCloud.texts"
                :records="otherWordCloud.records || []"
                title="Word Cloud Isian Lainnya"
                subtitle="Kata yang paling sering muncul dari isian bebas alumni."
                :height="300"
                :max-words="100"
            />
            <p
                v-else-if="activeVisualization === 'word_cloud' && showOtherWordCloudTab"
                class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-sm text-slate-500"
            >
                Belum ada isian teks dari opsi Lainnya.
            </p>
        </template>
    </section>
</template>
