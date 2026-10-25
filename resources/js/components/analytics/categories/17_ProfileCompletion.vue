<script setup>
/**
 * Komponen Indikator 17: Kelengkapan Profil Alumni.
 *
 * Mengelola seluruh visualisasi dan data tabel secara mandiri:
 * 1. Donut Chart: Proporsi Kelengkapan Profil Universitas
 * 2. Stacked Bar: Komposisi per Program Studi (100%)
 * 3. Heatmap: Pola Antarprogram Studi
 */
import { computed } from 'vue';
import AnalyticsChartCard from '../AnalyticsChartCard.vue';
import {
    donut,
    heatmap,
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
// 1. DONUT CHART: Proporsi Kelengkapan Profil Universitas
// -----------------------------------------------------------------------------
const donutChart = computed(() => donut(
    series.value,
    props.selectedLabel,
    validResponses.value,
));
const donutOption = computed(() => donutChart.value.option);
const donutTable = computed(() => donutChart.value.tableData);

// -----------------------------------------------------------------------------
// 2. STACKED BAR: Komposisi per Program Studi (100%)
// -----------------------------------------------------------------------------
const stackedChart = computed(() => stackedByProdi(
    records.value,
    props.selectedLabel,
));
const stackedOption = computed(() => stackedChart.value.option);
const stackedTable = computed(() => stackedChart.value.tableData);
const prodisCount = computed(() => new Set(records.value.map((r) => r.nama_prodi)).size);

// -----------------------------------------------------------------------------
// 3. HEATMAP: Pola Antarprogram Studi
// -----------------------------------------------------------------------------
const heatmapChart = computed(() => heatmap(
    records.value,
    props.selectedLabel,
    props.heatmapMode,
));
const heatmapOption = computed(() => heatmapChart.value.option);
const heatmapTable = computed(() => heatmapChart.value.tableData);
</script>

<template>
    <div class="space-y-6">
        <!-- 1. Proporsi Kelengkapan Profil -->
        <AnalyticsChartCard
            chart-key="donut"
            title="Proporsi kelengkapan profil alumni"
            description="Persentase alumni dengan profil lengkap, belum lengkap, atau belum diverifikasi."
            :option="donutOption"
            :table-data="donutTable"
            :rows="records"
            :height="380"
            :selected-label="selectedLabel"
            :loading="loading"
            @select="emit('select', $event)"
            @reset="emit('reset')"
        />

        <!-- 2. Komposisi per Program Studi -->
        <AnalyticsChartCard
            chart-key="stacked"
            title="Komposisi kelengkapan profil per program studi"
            description="Tingkat partisipasi pembaruan data profil lulusan pada masing-masing jurusan."
            :option="stackedOption"
            :table-data="stackedTable"
            :rows="records"
            :height="Math.max(340, Math.min(prodisCount * 34 + 110, 560))"
            :selected-label="selectedLabel"
            :loading="loading"
            @select="emit('select', $event)"
            @reset="emit('reset')"
        />

        <!-- 3. Pola Antarprogram Studi (Heatmap) -->
        <AnalyticsChartCard
            chart-key="heatmap"
            title="Pola kelengkapan data antarprogram studi"
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
    </div>
</template>
