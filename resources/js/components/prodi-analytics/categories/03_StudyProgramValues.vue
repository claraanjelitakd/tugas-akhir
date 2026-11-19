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
// Opsi grafik membaca skor rata-rata (mean) dari backend.
const chartOption = computed(() => buildLikertOption(props.items));
// Klik batang atau segmen skala Likert membawa butir dan skala yang dipilih.
const handleSelect = (params) => {
    const item = props.items[params?.dataIndex];
    const d = params?.data;
    if (!item?.key) return;

    if (d?.scaleLevel) {
        emit('select', {
            item,
            label: `${d.scaleLevel} - ${d.scaleLabel}`,
            value: d.scaleLevel,
            scaleLevel: d.scaleLevel,
            fromPivot: true,
            fromChart: true,
            type: 'rating_5',
            code: item.key,
            questionText: item.title,
            dataIndex: params?.dataIndex,
        });
    } else {
        emit('select', { item, label: item.title, value: params?.value, dataIndex: params?.dataIndex });
    }
};
</script>

<template>
    <!-- Setiap nilai prodi dibandingkan pada sumbu 1-5 dengan garis acuan skor 3. -->
    <section class="min-w-0 overflow-x-auto">
        <div v-if="items.length" class="min-w-[720px]"><AnalyticsEChart :option="chartOption" :height="likertChartHeight(items)" aria-label="Grafik nilai program studi" @select="handleSelect" /></div>
        <p v-else class="rounded-lg border border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">Belum ada nilai program studi yang dapat divisualisasikan.</p>
    </section>
</template>
