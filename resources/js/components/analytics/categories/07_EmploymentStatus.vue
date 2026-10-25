<script setup>
/**
 * Komponen Indikator 07: Status Pekerjaan Alumni.
 *
 * Mengelola seluruh visualisasi dan data tabel secara mandiri:
 * 1. Donut Chart: Proporsi Status Bekerja Universitas
 * 2. Stacked Bar: Komposisi Status per Program Studi (100%)
 * 3. Sankey Diagram: Aliran Program Studi ke Status Pekerjaan
 */
import { computed } from 'vue';
import AnalyticsChartCard from '../AnalyticsChartCard.vue';
import {
    donut,
    sankey,
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
// 1. DONUT CHART: Proporsi Status Pekerjaan Universitas
// -----------------------------------------------------------------------------
const donutChart = computed(() => donut(
    series.value,
    props.selectedLabel,
    validResponses.value,
));
const donutOption = computed(() => donutChart.value.option);
const donutTable = computed(() => donutChart.value.tableData);

// -----------------------------------------------------------------------------
// 2. STACKED BAR: Komposisi Status per Program Studi (100%)
// -----------------------------------------------------------------------------
const stackedChart = computed(() => stackedByProdi(
    records.value,
    props.selectedLabel,
));
const stackedOption = computed(() => stackedChart.value.option);
const stackedTable = computed(() => stackedChart.value.tableData);
const prodisCount = computed(() => new Set(records.value.map((r) => r.nama_prodi)).size);

// -----------------------------------------------------------------------------
// 3. SANKEY DIAGRAM: Aliran Program Studi ke Status Pekerjaan
// -----------------------------------------------------------------------------
const sankeyChart = computed(() => sankey(
    records.value,
    props.selectedLabel,
));
const sankeyOption = computed(() => sankeyChart.value.option);
const sankeyTable = computed(() => sankeyChart.value.tableData);
</script>

<template>
    <div class="space-y-6">
        <!-- 1. Proporsi Status Pekerjaan Universitas -->
        <AnalyticsChartCard
            chart-key="donut"
            title="Proporsi status pekerjaan universitas"
            description="Distribusi alumni yang telah bekerja, melanjutkan studi, wirausaha, atau belum bekerja."
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
            title="Komposisi status per program studi"
            description="Setiap batang dinormalisasi 100% agar perbandingan antardepartemen objektif dan adil."
            :option="stackedOption"
            :table-data="stackedTable"
            :rows="records"
            :height="Math.max(340, Math.min(prodisCount * 34 + 110, 560))"
            :selected-label="selectedLabel"
            :loading="loading"
            @select="emit('select', $event)"
            @reset="emit('reset')"
        />

        <!-- 3. Aliran Program Studi ke Status Pekerjaan -->
        <AnalyticsChartCard
            chart-key="sankey"
            title="Aliran program studi ke status pekerjaan"
            description="Visualisasi interaktif jalur keterserapan alumni dari program studi ke kategori pekerjaan."
            :option="sankeyOption"
            :table-data="sankeyTable"
            :rows="records"
            :height="420"
            :selected-label="selectedLabel"
            :loading="loading"
            @select="emit('select', $event)"
            @reset="emit('reset')"
        />
    </div>
</template>
