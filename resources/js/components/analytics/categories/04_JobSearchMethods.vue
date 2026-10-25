<script setup>
/**
 * Komponen Indikator 04: Cara Mencari Pekerjaan.
 *
 * Mengelola seluruh visualisasi dan data tabel secara mandiri:
 * 1. Horizontal Bar: Distribusi Saluran Pencarian Kerja (1 warna solid)
 * 2. Treemap: Komposisi Saluran Pencarian Kerja
 * 3. Heatmap: Pola Antarprogram Studi
 */
import { computed } from 'vue';
import AnalyticsChartCard from '../AnalyticsChartCard.vue';
import {
    heatmap,
    horizontalBar,
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
// 1. HORIZONTAL BAR: Distribusi Cara Mencari Kerja
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

// -----------------------------------------------------------------------------
// 2. TREEMAP: Komposisi Saluran Pencarian Kerja
// -----------------------------------------------------------------------------
const treemapChart = computed(() => treemap(
    series.value,
    props.selectedLabel,
    validResponses.value,
));
const treemapOption = computed(() => treemapChart.value.option);
const treemapTable = computed(() => treemapChart.value.tableData);

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
const prodisCount = computed(() => new Set(records.value.map((r) => r.nama_prodi)).size);
</script>

<template>
    <div class="space-y-6">
        <!-- 1. Distribusi Saluran Pencarian Kerja -->
        <AnalyticsChartCard
            chart-key="distribution"
            title="Distribusi saluran pencarian pekerjaan"
            description="Saluran dan metode yang digunakan alumni untuk memperoleh pekerjaan pertama."
            :option="barOption"
            :table-data="barTable"
            :rows="records"
            :height="Math.max(340, Math.min(series.length * 38 + 90, 640))"
            :selected-label="selectedLabel"
            :loading="loading"
            @select="emit('select', $event)"
            @reset="emit('reset')"
        />

        <!-- 2. Komposisi Saluran Pencarian Kerja -->
        <AnalyticsChartCard
            chart-key="treemap"
            title="Komposisi metode pencarian"
            description="Luas bidang mencerminkan efektivitas saluran dalam menyerap alumni."
            :option="treemapOption"
            :table-data="treemapTable"
            :rows="records"
            :height="380"
            :selected-label="selectedLabel"
            :loading="loading"
            @select="emit('select', $event)"
            @reset="emit('reset')"
        />

        <!-- 3. Pola Antarprogram Studi (Heatmap) -->
        <AnalyticsChartCard
            chart-key="heatmap"
            title="Pola saluran antarprogram studi"
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
