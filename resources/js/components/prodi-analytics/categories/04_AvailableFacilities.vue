<script setup>
/**
 * Bagian 04: Fasilitas yang Tersedia.
 * Grafik utama: skor fasilitas laboratorium, perangkat lunak, kelas, internet, dan ruang diskusi.
 */
import { computed } from 'vue';
import AnalyticsEChart from '@/components/analytics/AnalyticsEChart.vue';
import { buildLikertOption, likertChartHeight } from '../prodiChartOptions';

// Butir fasilitas aktif yang telah dihitung service.
const props = defineProps({ items: { type: Array, default: () => [] } });
// Event klik batang fasilitas.
const emit = defineEmits(['select']);
// Opsi ECharts fasilitas berasal dari items aktual.
const chartOption = computed(() => buildLikertOption(props.items));
// Mengubah indeks batang menjadi item pertanyaan.
const handleSelect = (params) => {
    const item = props.items[params?.dataIndex];
    if (item?.key) emit('select', { item, label: item.title, value: params?.value, dataIndex: params?.dataIndex });
};
</script>

<template>
    <!-- Label fasilitas dibungkus otomatis agar grafik tetap terbaca pada layar kecil. -->
    <section class="min-w-0 overflow-x-auto">
        <div v-if="items.length" class="min-w-[720px]"><AnalyticsEChart :option="chartOption" :height="likertChartHeight(items)" aria-label="Grafik fasilitas program studi" @select="handleSelect" /></div>
        <p v-else class="rounded-lg border border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">Belum ada fasilitas yang dapat divisualisasikan.</p>
    </section>
</template>
