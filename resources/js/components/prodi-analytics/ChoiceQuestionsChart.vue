<script setup>
/**
 * Renderer generik untuk bagian program studi yang memiliki pertanyaan pilihan.
 *
 * Setiap item tetap membaca tipe dan payload final dari backend. Opsi pilihan
 * ditampilkan sebagai batang, sedangkan jawaban terbuka dan isian Lainnya
 * ditampilkan sebagai word cloud agar tidak dipaksa menjadi kategori angka.
 */
import { computed, ref, watch } from 'vue';
import AnalyticsEChart from '@/components/analytics/AnalyticsEChart.vue';
import AnalyticsWordCloud from '@/components/analytics/AnalyticsWordCloud.vue';
import {
    buildChoiceOption,
    buildLikertOption,
    choiceAnalysis,
    choiceChartHeight,
} from './prodiChartOptions';

// Kontrak data item pertanyaan yang telah dihitung backend.
const props = defineProps({
    items: { type: Array, default: () => [] },
});

// Event klik pilihan diteruskan ke tabel drill-down halaman induk.
const emit = defineEmits(['select', 'context-change']);

// Menyimpan pertanyaan aktif agar satu bagian tetap ringkas saat memiliki banyak butir.
const activeQuestionKey = ref('');

// Menetapkan pertanyaan pertama sebagai tab aktif untuk payload baru.
watch(() => props.items, (items) => {
    if (!items.some((item) => item.key === activeQuestionKey.value)) {
        activeQuestionKey.value = items[0]?.key || '';
    }
}, { immediate: true });

// Mengambil item pertanyaan yang sedang dibaca.
const activeItem = computed(() => props.items.find((item) => item.key === activeQuestionKey.value) || null);

// Membangun konfigurasi grafik sesuai tipe pertanyaan dari backend.
const chartOption = computed(() => {
    if (activeItem.value?.type === 'choice') {
        return buildChoiceOption(activeItem.value);
    }
    if (activeItem.value?.type === 'likert') {
        return buildLikertOption([activeItem.value]);
    }

    return null;
});

// Mengambil analisis pilihan hanya untuk item yang memang bertipe choice.
const activeAnalysis = computed(() => activeItem.value?.type === 'choice' ? choiceAnalysis(activeItem.value) : null);

// Menyediakan teks jawaban terbuka untuk pertanyaan campuran tanpa perhitungan baru di frontend.
const activeTextResponses = computed(() => (activeItem.value?.records || [])
    .map((record) => record.jawaban_label || '')
    .filter((text) => String(text).trim() !== ''));

// Word cloud isian Lainnya berasal dari payload khusus backend.
const otherWordCloud = computed(() => activeItem.value?.other_word_cloud || {});

// Menyediakan tab terpisah untuk grafik distribusi dan Word Cloud isian bebas.
const activeVisualization = ref('chart');

// Word Cloud tetap ditawarkan jika opsi Lainnya tersedia, meskipun belum ada teks.
const hasOtherWordCloud = computed(() => (otherWordCloud.value.texts || [])
    .some((text) => String(text || '').trim() !== ''));

// Menentukan apakah pertanyaan aktif memang memiliki opsi Lainnya dari master.
const showOtherWordCloudTab = computed(() => activeItem.value?.has_other_option === true || hasOtherWordCloud.value);

// Memberi tahu halaman induk bahwa KPI mengikuti pertanyaan aktif.
watch(activeItem, (item) => emit('context-change', item), { immediate: true });

// Mengembalikan fokus ke grafik utama ketika pertanyaan aktif diganti.
watch(activeItem, () => {
    activeVisualization.value = 'chart';
});

// Mengirim kategori atau nilai yang diklik untuk drill-down responden.
const handleSelect = (params) => {
    const item = activeItem.value;
    if (!item?.key) {
        return;
    }

    const label = item.type === 'choice'
        ? item.categories?.[params?.dataIndex]
        : item.title;
    if (!label) {
        return;
    }

    emit('select', { item, label, value: params?.value, dataIndex: params?.dataIndex });
};
</script>

<template>
    <!-- Tab pertanyaan memakai metadata instrumen agar tidak ada label hardcode. -->
    <section class="min-w-0 space-y-4">
        <nav v-if="items.length > 1" class="grid gap-1 rounded-lg border border-slate-200 bg-slate-50 p-1 sm:grid-cols-3" aria-label="Pertanyaan pilihan program studi">
            <button
                v-for="item in items"
                :key="item.key"
                type="button"
                class="min-h-16 min-w-0 rounded-md px-4 py-3 text-left text-xs font-bold leading-5 transition-colors sm:text-center"
                :class="activeQuestionKey === item.key ? 'bg-[#0D542B] text-white shadow-sm' : 'text-slate-600 hover:bg-white hover:text-slate-900'"
                :title="item.title"
                @click="activeQuestionKey = item.key"
            >
                <span class="block whitespace-normal break-words">{{ item.title }}</span>
            </button>
        </nav>

        <template v-if="activeItem">
            <!-- Word Cloud dipisahkan dari grafik kategori agar halaman tetap ringkas. -->
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

            <!-- Konteks pertanyaan aktif selalu tampil tepat di atas visualisasinya. -->
            <div>
                <h3 class="text-base font-bold leading-6 text-slate-900">{{ activeItem.title }}</h3>
                <p class="mt-1 text-xs leading-5 text-slate-500">{{ activeItem.subtitle }}</p>
                <p class="mt-2 text-xs leading-5 text-slate-600">{{ activeItem.caption }}</p>
            </div>

            <!-- Grafik angka hanya dirender untuk choice dan Likert; teks memakai word cloud. -->
            <div v-if="activeVisualization === 'chart' && chartOption" class="min-w-0 overflow-x-auto">
                <div class="min-w-[720px]">
                    <AnalyticsEChart
                        :option="chartOption"
                        :height="activeItem.type === 'choice' ? choiceChartHeight(activeItem) : 420"
                        aria-label="Grafik pertanyaan program studi"
                        @select="handleSelect"
                    />
                </div>
            </div>
            <AnalyticsWordCloud
                v-else-if="activeVisualization === 'chart' && activeTextResponses.length"
                :texts="activeTextResponses"
                title="Word Cloud Jawaban Terbuka"
                subtitle="Kata yang paling sering muncul dari jawaban alumni."
                :height="320"
                :max-words="100"
            />
            <p v-else-if="activeVisualization === 'chart'" class="rounded-lg border border-slate-200 bg-slate-50 p-5 text-center text-sm text-slate-500">Belum ada jawaban valid untuk pertanyaan ini.</p>

            <!-- Analisis frekuensi hanya ditampilkan untuk kategori pilihan. -->
            <section v-if="activeVisualization === 'chart' && activeAnalysis" class="rounded-lg border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-xs font-bold uppercase tracking-wider text-[#0D542B]">Analisis pertanyaan aktif</p>
                <p class="mt-2 text-sm font-semibold leading-6 text-slate-900">{{ activeAnalysis.lead }}</p>
                <p class="mt-1 text-xs leading-5 text-slate-600">{{ activeAnalysis.detail }}</p>
            </section>

            <!-- Semua isian bebas opsi Lainnya dipisahkan dari batang kategori. -->
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
