<script setup>
/**
 * Bagian 06: Mata Kuliah yang Ditawarkan.
 * Grafik utama: relevansi industri, minat karier, fleksibilitas, struktur, dan kesiapan kerja.
 */
import { computed } from 'vue';
import AnalyticsEChart from '@/components/analytics/AnalyticsEChart.vue';
import { buildLikertOption, likertChartHeight } from '../prodiChartOptions';

// Butir mata kuliah aktif.
const props = defineProps({ items: { type: Array, default: () => [] } });
// Event klik butir kurikulum.
const emit = defineEmits(['select']);
// Opsi grafik berasal dari skor bulat payload analitik.
const chartOption = computed(() => buildLikertOption(props.items));
// Mengirim pertanyaan yang dipilih.
const handleSelect = (params) => {
    const item = props.items[params?.dataIndex];
    if (item?.key) emit('select', { item, label: item.title, value: params?.value, dataIndex: params?.dataIndex });
};
</script>

<template>
    <!-- Visualisasi kurikulum memakai satu skala agar semua butir mudah dibandingkan. -->
    <section class="min-w-0 overflow-x-auto">
        <div v-if="items.length" class="min-w-[720px]"><AnalyticsEChart :option="chartOption" :height="likertChartHeight(items)" aria-label="Grafik mata kuliah yang ditawarkan" @select="handleSelect" /></div>
        <p v-else class="rounded-lg border border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">Belum ada mata kuliah yang dapat divisualisasikan.</p>
    </section>
</template>
