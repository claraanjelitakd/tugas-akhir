<script setup>
/**
 * Komponen Indikator 13: Keselarasan Tingkat Pendidikan.
 *
 * Mengelola seluruh visualisasi dan data tabel secara mandiri:
 * 1. Stacked Bar: Komposisi Keselarasan Pendidikan per Program Studi (100%)
 * 2. Heatmap: Pola Antarprogram Studi
 * 3. Horizontal Bar: Distribusi Tingkat Keselarasan Pendidikan
 */
import { computed } from 'vue';
import AnalyticsChartCard from '../AnalyticsChartCard.vue';
import {
    heatmap,
    horizontalBar,
    stackedByProdi,
} from '../analyticsChartOptions';

// Kontrak properti data indikator, filter aktif, dan status pemuatan data.
const props = defineProps({
    chart: { type: Object, default: () => ({}) },
    selectedLabel: { type: String, default: '' },
    heatmapMode: { type: String, default: 'count' },
    loading: { type: Boolean, default: false },
});

// Event seleksi data diteruskan ke panel induk untuk cross-filtering.
const emit = defineEmits(['select', 'reset']);

// Ekstraksi data seri dan catatan responden.
const series = computed(() => props.chart?.series || []);
const records = computed(() => props.chart?.records || []);
const validResponses = computed(() => props.chart?.summary?.answered_count || props.chart?.summary?.response_count || records.value.length);

// -----------------------------------------------------------------------------
// 1. STACKED BAR: Komposisi per Program Studi (100%)
// -----------------------------------------------------------------------------
const stackedChart = computed(() => stackedByProdi(
    records.value,
    props.selectedLabel,
));
const stackedOption = computed(() => stackedChart.value.option);
const stackedTable = computed(() => stackedChart.value.tableData);
const prodisCount = computed(() => new Set(records.value.map((r) => r.nama_prodi)).size);

// -----------------------------------------------------------------------------
// 2. HEATMAP: Pola Antarprogram Studi
// -----------------------------------------------------------------------------
const heatmapChart = computed(() => heatmap(
    records.value,
    props.selectedLabel,
    props.heatmapMode,
));
const heatmapOption = computed(() => heatmapChart.value.option);
const heatmapTable = computed(() => heatmapChart.value.tableData);

// -----------------------------------------------------------------------------
// 3. HORIZONTAL BAR: Distribusi Tingkat Keselarasan Pendidikan
// -----------------------------------------------------------------------------
const barChart = computed(() => horizontalBar(
    series.value,
    props.chart?.type,
    props.selectedLabel,
    validResponses.value,
    'Jumlah Alumni',
    '#0D542B',
));
const barOption = computed(() => barChart.value.option);
const barTable = computed(() => barChart.value.tableData);
</script>

<template>
    <div class="space-y-6">
        <!-- 1. Komposisi per Program Studi -->
        <AnalyticsChartCard
            chart-key="stacked"
            title="Komposisi keselarasan pendidikan per program studi"
            description="Tingkat kebutuhan kualifikasi jenjang sarjana pada posisi pekerjaan alumni."
            :option="stackedOption"
            :table-data="stackedTable"
            :rows="records"
            :height="Math.max(340, Math.min(prodisCount * 34 + 110, 560))"
            :selected-label="selectedLabel"
            :loading="loading"
            @select="emit('select', $event)"
            @reset="emit('reset')"
        />

        <!-- 2. Pola Antarprogram Studi (Heatmap) -->
        <AnalyticsChartCard
            chart-key="heatmap"
            title="Pola keselarasan pendidikan antarprogram studi"
            :description="heatmapMode === 'percentage'
                ? 'Intensitas dan angka sel menunjukkan persentase kategori dalam setiap program studi.'
                : 'Intensitas dan angka sel menunjukkan jumlah record pada setiap kategori dan program studi.'"
            :option="heatmapOption"
            :table-data="heatmapTable"
            :rows="records"
            :height="Math.max(360, Math.min(prodisCount * 34 + 140, 580))"
            :selected-label="selectedLabel"
            :loading="loading"
            @select="emit('select', $event)"
            @reset="emit('reset')"
        />

        <!-- 3. Distribusi Utama -->
        <AnalyticsChartCard
            chart-key="rate"
            title="Distribusi keselarasan tingkat pendidikan"
            description="Perbandingan proporsi alumni yang bekerja setingkat lebih tinggi, sama dengan, atau di bawah kualifikasi S1."
            :option="barOption"
            :table-data="barTable"
            :rows="records"
            :height="Math.max(340, Math.min(series.length * 38 + 90, 640))"
            :selected-label="selectedLabel"
            :loading="loading"
            @select="emit('select', $event)"
            @reset="emit('reset')"
        />
    </div>
</template>
