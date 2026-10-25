<script setup>
/**
 * Komponen Indikator 15: Alasan Ketidaksesuaian Pekerjaan.
 *
 * Mengelola seluruh visualisasi dan data tabel secara mandiri:
 * 1. Horizontal Bar: Distribusi Utama Alasan Ketidaksesuaian
 * 2. Treemap: Komposisi Alasan Ketidaksesuaian
 * 3. Heatmap: Pola Alasan per Program Studi
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
// 1. HORIZONTAL BAR: Distribusi Alasan Mengambil Pekerjaan Tidak Sesuai
// -----------------------------------------------------------------------------
const barChart = computed(() => horizontalBar(
    series.value,
    props.chart?.type,
    props.selectedLabel,
    validResponses.value,
    'Jumlah Responden',
    '#0D542B',
));
const barOption = computed(() => barChart.value.option);
const barTable = computed(() => barChart.value.tableData);

// -----------------------------------------------------------------------------
// 2. TREEMAP: Komposisi Kategori Alasan Ketidaksesuaian
// -----------------------------------------------------------------------------
const treemapChart = computed(() => treemap(
    series.value,
    props.selectedLabel,
    validResponses.value,
));
const treemapOption = computed(() => treemapChart.value.option);
const treemapTable = computed(() => treemapChart.value.tableData);

// -----------------------------------------------------------------------------
// 3. HEATMAP: Pola Alasan per Program Studi
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
        <!-- 1. Distribusi Utama Alasan Ketidaksesuaian -->
        <AnalyticsChartCard
            chart-key="distribution"
            title="Distribusi alasan ketidaksesuaian pekerjaan"
            description="Urutan alasan utama alumni memilih pekerjaan yang tidak selaras dengan program studi mereka."
            :option="barOption"
            :table-data="barTable"
            :rows="records"
            :height="Math.max(340, Math.min(series.length * 38 + 90, 640))"
            :selected-label="selectedLabel"
            :loading="loading"
            @select="emit('select', $event)"
            @reset="emit('reset')"
        />

        <!-- 2. Komposisi Kategori Alasan -->
        <AnalyticsChartCard
            chart-key="treemap"
            title="Komposisi proporsi alasan"
            description="Luas petak merepresentasikan frekuensi alasan; klik petak untuk memfilter data terkait."
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
            title="Pola alasan antarprogram studi"
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
