<script setup>
/**
 * Komponen Indikator 09: Jenis Tempat Bekerja.
 *
 * Mengelola seluruh visualisasi dan data tabel secara mandiri:
 * 1. Treemap: Komposisi Jenis Instansi / Perusahaan
 * 2. Donut Chart: Proporsi Universitas
 * 3. Stacked Bar: Komposisi per Program Studi (100%)
 */
import { computed } from 'vue';
import AnalyticsChartCard from '../AnalyticsChartCard.vue';
import {
    donut,
    stackedByProdi,
    treemap,
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
// 1. TREEMAP: Komposisi Jenis Tempat Bekerja
// -----------------------------------------------------------------------------
const treemapChart = computed(() => treemap(
    series.value,
    props.selectedLabel,
    validResponses.value,
));
const treemapOption = computed(() => treemapChart.value.option);
const treemapTable = computed(() => treemapChart.value.tableData);

// -----------------------------------------------------------------------------
// 2. DONUT CHART: Proporsi Universitas
// -----------------------------------------------------------------------------
const donutChart = computed(() => donut(
    series.value,
    props.selectedLabel,
    validResponses.value,
));
const donutOption = computed(() => donutChart.value.option);
const donutTable = computed(() => donutChart.value.tableData);

// -----------------------------------------------------------------------------
// 3. STACKED BAR: Komposisi Jenis Tempat Bekerja per Program Studi (100%)
// -----------------------------------------------------------------------------
const stackedChart = computed(() => stackedByProdi(
    records.value,
    props.selectedLabel,
));
const stackedOption = computed(() => stackedChart.value.option);
const stackedTable = computed(() => stackedChart.value.tableData);
const prodisCount = computed(() => new Set(records.value.map((r) => r.nama_prodi)).size);
</script>

<template>
    <div class="space-y-6">
        <!-- 1. Komposisi Jenis Instansi -->
        <AnalyticsChartCard
            chart-key="treemap"
            title="Komposisi jenis instansi tempat bekerja"
            description="Sebaran alumni pada instansi pemerintah, BUMN, swasta nasional, multinasional, wirausaha, dll."
            :option="treemapOption"
            :table-data="treemapTable"
            :rows="records"
            :height="380"
            :selected-label="selectedLabel"
            :loading="loading"
            @select="emit('select', $event)"
            @reset="emit('reset')"
        />

        <!-- 2. Proporsi Universitas -->
        <AnalyticsChartCard
            chart-key="donut"
            title="Proporsi instansi keseluruhan universitas"
            description="Persentase penyerapan alumni berdasarkan sektor badan hukum instansi pemberi kerja."
            :option="donutOption"
            :table-data="donutTable"
            :rows="records"
            :height="380"
            :selected-label="selectedLabel"
            :loading="loading"
            @select="emit('select', $event)"
            @reset="emit('reset')"
        />

        <!-- 3. Komposisi per Program Studi -->
        <AnalyticsChartCard
            chart-key="stacked"
            title="Komposisi sektor kerja per program studi"
            description="Karakteristik sektor penyerap lulusan untuk masing-masing program studi."
            :option="stackedOption"
            :table-data="stackedTable"
            :rows="records"
            :height="Math.max(340, Math.min(prodisCount * 34 + 110, 560))"
            :selected-label="selectedLabel"
            :loading="loading"
            @select="emit('select', $event)"
            @reset="emit('reset')"
        />
    </div>
</template>
