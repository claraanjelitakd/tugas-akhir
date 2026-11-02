<script setup>
/**
 * Bagian 03: Nilai-Nilai Program Studi.
 * Grafik utama: batang horizontal skor persepsi alumni pada setiap nilai prodi.
 */
import { computed } from 'vue';
import AnalyticsEChart from '@/components/analytics/AnalyticsEChart.vue';
import { buildLikertOption, likertChartHeight } from '../prodiChartOptions';

// Pernyataan nilai prodi dari metadata instrumen aktif.
const props = defineProps({ items: { type: Array, default: () => [] } });
// Event klik pernyataan dipakai untuk drill-down data.
const emit = defineEmits(['select']);
// Opsi grafik membaca skor rata-rata dan median dari backend.
const chartOption = computed(() => buildLikertOption(props.items));
// Mengirim butir yang diklik ke halaman Grafik Prodi.
const handleSelect = (params) => {
    const item = props.items[params?.dataIndex];
    if (item?.key) emit('select', { item, label: item.title, value: params?.value, dataIndex: params?.dataIndex });
};
</script>

<template>
    <!-- Setiap nilai prodi dibandingkan pada sumbu 1-5 dengan garis acuan skor 3. -->
    <section class="min-w-0 overflow-x-auto">
        <div v-if="items.length" class="min-w-[720px]"><AnalyticsEChart :option="chartOption" :height="likertChartHeight(items)" aria-label="Grafik nilai program studi" @select="handleSelect" /></div>
        <p v-else class="rounded-lg border border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">Belum ada nilai program studi yang dapat divisualisasikan.</p>
    </section>
</template>
