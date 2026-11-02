<script setup>
/**
 * Bagian 08: Rekomendasi Program Studi.
 * Grafik utama: kelayakan dan kesediaan alumni merekomendasikan program studi.
 */
import { computed } from 'vue';
import AnalyticsEChart from '@/components/analytics/AnalyticsEChart.vue';
import { buildLikertOption, likertChartHeight } from '../prodiChartOptions';

// Pernyataan rekomendasi aktif.
const props = defineProps({ items: { type: Array, default: () => [] } });
// Event klik pernyataan rekomendasi.
const emit = defineEmits(['select']);
// Opsi grafik dibangun dari distribusi skor aktual.
const chartOption = computed(() => buildLikertOption(props.items));
// Mengirim pernyataan terpilih ke drill-down.
const handleSelect = (params) => {
    const item = props.items[params?.dataIndex];
    if (item?.key) emit('select', { item, label: item.title, value: params?.value, dataIndex: params?.dataIndex });
};
</script>

<template>
    <!-- Dua butir rekomendasi dibandingkan pada skala yang sama dan responsif. -->
    <section class="min-w-0 overflow-x-auto">
        <div v-if="items.length" class="min-w-[720px]"><AnalyticsEChart :option="chartOption" :height="likertChartHeight(items)" aria-label="Grafik rekomendasi program studi" @select="handleSelect" /></div>
        <p v-else class="rounded-lg border border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">Belum ada rekomendasi yang dapat divisualisasikan.</p>
    </section>
</template>
