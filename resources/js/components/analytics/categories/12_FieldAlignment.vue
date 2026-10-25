<script setup>
/**
 * Komponen Indikator 12: Keselarasan Bidang Pekerjaan.
 *
 * Mengelola seluruh visualisasi dan data tabel secara mandiri:
 * 1. Stacked Bar: Komposisi Keselarasan Bidang per Program Studi (100%)
 * 2. Heatmap: Pola Keselarasan Antarprogram Studi
 * 3. Sankey Diagram: Aliran Program Studi ke Tingkat Keselarasan Bidang
 */
import { computed } from 'vue';
import AnalyticsChartCard from '../AnalyticsChartCard.vue';
import {
    heatmap,
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
// 1. STACKED BAR: Komposisi Keselarasan Bidang per Program Studi (100%)
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
// 3. SANKEY DIAGRAM: Aliran Program Studi ke Kategori Keselarasan
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
        <!-- 1. Komposisi per Program Studi -->
        <AnalyticsChartCard
            chart-key="stacked"
            title="Komposisi keselarasan bidang per program studi"
            description="Tingkat kesesuaian antara ilmu yang dipelajari selama kuliah dengan pekerjaan yang ditekuni."
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
            title="Pola keselarasan bidang antarprogram studi"
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

        <!-- 3. Aliran Program Studi ke Kategori Keselarasan -->
        <AnalyticsChartCard
            chart-key="sankey"
            title="Aliran program studi ke tingkat keselarasan"
            description="Visualisasi jalur alumni dari setiap program studi menuju tingkat keselarasan vertikal bidang kerja."
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
