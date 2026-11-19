<script setup>
/**
 * Bagian 05: Dosen dan Tenaga Kependidikan.
 * Grafik utama: skor pengajaran dosen dan kualitas layanan staf FTI.
 */
import { computed } from 'vue';
import AnalyticsEChart from '@/components/analytics/AnalyticsEChart.vue';
import { buildLikertOption, likertChartHeight } from '../prodiChartOptions';

// Butir dosen dan staf dari section aktif.
const props = defineProps({ items: { type: Array, default: () => [] } });
// Event klik aspek layanan.
const emit = defineEmits(['select']);
// Opsi grafik dibangun dari skor jawaban aktual.
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
    <!-- Satu grafik horizontal menjaga banyak aspek layanan tidak saling bertabrakan. -->
    <section class="min-w-0 overflow-x-auto">
        <div v-if="items.length" class="min-w-[720px]"><AnalyticsEChart :option="chartOption" :height="likertChartHeight(items)" aria-label="Grafik dosen dan tenaga kependidikan" @select="handleSelect" /></div>
        <p v-else class="rounded-lg border border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">Belum ada aspek dosen dan staf yang dapat divisualisasikan.</p>
    </section>
</template>
