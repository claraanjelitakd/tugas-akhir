<script setup>
/**
 * Komponen Indikator 18: Sebaran Wilayah Kerja Alumni.
 *
 * Mengelola seluruh visualisasi dan data tabel secara mandiri:
 * 1. Horizontal Bar: Distribusi Provinsi Utama Tempat Bekerja
 * 2. Treemap: Komposisi Wilayah Kerja
 * 3. Heatmap: Pola Antarprogram Studi
 * 4. Peta Interaktif MapLibre: Sebaran Geografis Provinsi
 */
import { computed, defineAsyncComponent } from 'vue';
import AnalyticsChartCard from '../AnalyticsChartCard.vue';
import {
    heatmap,
    horizontalBar,
    treemap,
} from '../analyticsChartOptions';

// Peta wilayah kerja dimuat secara asynchronous.
const AnalyticsMap = defineAsyncComponent(() => import('../AnalyticsMap.vue'));

// Kontrak properti data indikator, filter aktif, dan status pemuatan data.
const props = defineProps({
    chart: { type: Object, default: () => ({}) },
    selectedLabel: { type: String, default: '' },
    heatmapMode: { type: String, default: 'count' },
    loading: { type: Boolean, default: false },
});

// Event seleksi data dan klik provinsi di peta.
const emit = defineEmits(['select', 'reset', 'select-province']);

// Ekstraksi data seri dan catatan responden.
const series = computed(() => props.chart?.series || []);
const records = computed(() => props.chart?.records || []);
const validResponses = computed(() => props.chart?.summary?.answered_count || props.chart?.summary?.response_count || records.value.length);

// -----------------------------------------------------------------------------
// 1. HORIZONTAL BAR: Distribusi Provinsi Tempat Bekerja
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
// 2. TREEMAP: Komposisi Wilayah Kerja
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
        <!-- 1. Distribusi Provinsi Utama -->
        <AnalyticsChartCard
            chart-key="distribution"
            title="Distribusi provinsi tempat bekerja alumni"
            description="Daftar provinsi tempat perusahaan atau kantor alumni beroperasi."
            :option="barOption"
            :table-data="barTable"
            :rows="records"
            :height="Math.max(340, Math.min(series.length * 38 + 90, 640))"
            :selected-label="selectedLabel"
            :loading="loading"
            @select="emit('select', $event)"
            @reset="emit('reset')"
        />

        <!-- 2. Komposisi Wilayah Kerja -->
        <AnalyticsChartCard
            chart-key="treemap"
            title="Komposisi wilayah karir alumni"
            description="Luas blok merefleksikan konsentrasi serapan alumni pada masing-masing daerah."
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
            title="Pola sebaran geografis antarprogram studi"
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

        <!-- 4. Peta Geografis Interaktif MapLibre -->
        <article class="bg-white p-5 shadow-sm sm:p-6 border border-slate-100">
            <div class="border-b border-slate-100 pb-4">
                <h3 class="text-lg font-extrabold text-slate-900">Peta sebaran wilayah kerja</h3>
                <p class="mt-1 text-sm text-slate-500">Sebaran provinsi perusahaan tempat alumni berkarir. Klik wilayah untuk menelusuri data.</p>
            </div>
            <AnalyticsMap
                class="mt-4"
                :regions="chart.regions || []"
                metric-label="alumni"
                @province-selected="emit('select-province', $event)"
            />
        </article>
    </div>
</template>
