<script setup>
/**
 * Bagian 07: Kepuasan Total.
 * Grafik utama: kepuasan pengalaman studi, perkembangan diri, dan keputusan memilih prodi.
 */
import { computed } from 'vue';
import AnalyticsEChart from '@/components/analytics/AnalyticsEChart.vue';
import { buildLikertOption, likertChartHeight } from '../prodiChartOptions';

// Pernyataan kepuasan total.
const props = defineProps({ items: { type: Array, default: () => [] } });
// Event klik kepuasan.
const emit = defineEmits(['select']);
// Opsi grafik membaca skor tiap pernyataan.
const chartOption = computed(() => buildLikertOption(props.items));
// Mengirim pernyataan kepuasan terpilih.
const handleSelect = (params) => {
    const item = props.items[params?.dataIndex];
    if (item?.key) emit('select', { item, label: item.title, value: params?.value, dataIndex: params?.dataIndex });
};
</script>

<template>
    <!-- Grafik kepuasan mendukung klik setiap pernyataan untuk melihat data pembentuknya. -->
    <section class="min-w-0 overflow-x-auto">
        <div v-if="items.length" class="min-w-[720px]"><AnalyticsEChart :option="chartOption" :height="likertChartHeight(items)" aria-label="Grafik kepuasan total program studi" @select="handleSelect" /></div>
        <p v-else class="rounded-lg border border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">Belum ada kepuasan yang dapat divisualisasikan.</p>
    </section>
</template>
