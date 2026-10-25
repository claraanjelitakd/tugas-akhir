<script setup>
/**
 * Komponen Indikator 10: Studi Lanjut Alumni.
 *
 * Mengelola seluruh visualisasi dan data tabel secara mandiri:
 * 1. Sankey Diagram: Aliran Program Studi ke Jenjang Pendidikan Lanjutan
 * 2. Stacked Bar: Komposisi Studi Lanjut per Program Studi (100%)
 * 3. Treemap: Komposisi Pembiayaan dan Jenjang
 */
import { computed } from 'vue';
import AnalyticsChartCard from '../AnalyticsChartCard.vue';
import {
    sankey,
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
// 1. SANKEY DIAGRAM: Aliran Program Studi ke Jenjang Studi Lanjut
// -----------------------------------------------------------------------------
const sankeyChart = computed(() => sankey(
    records.value,
    props.selectedLabel,
));
const sankeyOption = computed(() => sankeyChart.value.option);
const sankeyTable = computed(() => sankeyChart.value.tableData);

// -----------------------------------------------------------------------------
// 2. STACKED BAR: Komposisi Studi Lanjut per Program Studi (100%)
// -----------------------------------------------------------------------------
const stackedChart = computed(() => stackedByProdi(
    records.value,
    props.selectedLabel,
));
const stackedOption = computed(() => stackedChart.value.option);
const stackedTable = computed(() => stackedChart.value.tableData);
const prodisCount = computed(() => new Set(records.value.map((r) => r.nama_prodi)).size);

// -----------------------------------------------------------------------------
// 3. TREEMAP: Komposisi Pembiayaan dan Jenjang
// -----------------------------------------------------------------------------
const treemapChart = computed(() => treemap(
    series.value,
    props.selectedLabel,
    validResponses.value,
));
const treemapOption = computed(() => treemapChart.value.option);
const treemapTable = computed(() => treemapChart.value.tableData);
</script>

<template>
    <div class="space-y-6">
        <!-- 1. Aliran Program Studi ke Studi Lanjut -->
        <AnalyticsChartCard
            chart-key="sankey"
            title="Aliran program studi ke jenjang studi lanjut"
            description="Visualisasi korelasi asal program studi S1 terhadap peminatan dan jenjang studi lanjutan alumni."
            :option="sankeyOption"
            :table-data="sankeyTable"
            :rows="records"
            :height="420"
            :selected-label="selectedLabel"
            :loading="loading"
            @select="emit('select', $event)"
            @reset="emit('reset')"
        />

        <!-- 2. Komposisi per Program Studi -->
        <AnalyticsChartCard
            chart-key="stacked"
            title="Komposisi peminatan studi lanjut per program studi"
            description="Proporsi alumni masing-masing prodi yang mengambil pendidikan profesi, magister, atau doktoral."
            :option="stackedOption"
            :table-data="stackedTable"
            :rows="records"
            :height="Math.max(340, Math.min(prodisCount * 34 + 110, 560))"
            :selected-label="selectedLabel"
            :loading="loading"
            @select="emit('select', $event)"
            @reset="emit('reset')"
        />

        <!-- 3. Komposisi Pembiayaan / Jenjang -->
        <AnalyticsChartCard
            chart-key="treemap"
            title="Komposisi kategori studi lanjut"
            description="Luas blok merefleksikan jumlah alumni berdasarkan sumber beasiswa atau skema pembiayaan studi."
            :option="treemapOption"
            :table-data="treemapTable"
            :rows="records"
            :height="380"
            :selected-label="selectedLabel"
            :loading="loading"
            @select="emit('select', $event)"
            @reset="emit('reset')"
        />
    </div>
</template>
