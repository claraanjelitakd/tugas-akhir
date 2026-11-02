<script setup>
/**
 * Bagian 02: Pengalaman Belajar Selama Studi.
 * Grafik utama: batang horizontal rata-rata 9 pernyataan skala 1-5.
 */
import { computed } from 'vue';
import AnalyticsEChart from '@/components/analytics/AnalyticsEChart.vue';
import { buildLikertOption, likertChartHeight } from '../prodiChartOptions';

// Butir pengalaman belajar dan skor bulat yang dikirim service analitik.
const props = defineProps({ items: { type: Array, default: () => [] } });
// Event klik pernyataan membuka responden pembentuk skor di halaman induk.
const emit = defineEmits(['select']);
// Opsi grafik dibentuk dari data aktif, bukan dari angka yang ditulis di komponen.
const chartOption = computed(() => buildLikertOption(props.items));
// Klik batang membawa butir pertanyaan yang dipilih.
const handleSelect = (params) => {
    const item = props.items[params?.dataIndex];
    if (item?.key) emit('select', { item, label: item.title, value: params?.value, dataIndex: params?.dataIndex });
};
</script>

<template>
    <!-- Grafik pengalaman belajar memakai ruang label dinamis agar sembilan pernyataan tidak bertumpuk. -->
    <section class="min-w-0 overflow-x-auto">
        <div v-if="items.length" class="min-w-[720px]"><AnalyticsEChart :option="chartOption" :height="likertChartHeight(items)" aria-label="Grafik pengalaman belajar selama studi" @select="handleSelect" /></div>
        <p v-else class="rounded-lg border border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">Belum ada butir yang dapat divisualisasikan.</p>
    </section>
</template>
