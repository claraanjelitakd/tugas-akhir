<script setup>
/**
 * Komponen Indikator 17: Kelengkapan Profil Alumni.
 *
 * Mengelola seluruh visualisasi dan data tabel secara mandiri dalam kontainer terpadu:
 * 1. Donut Chart: Proporsi Kelengkapan Profil Universitas
 * 2. Stacked Bar: Komposisi per Program Studi (100%)
 * 3. Heatmap: Pola Antarprogram Studi
 */
import { computed } from 'vue';
import AnalyticsTabbedContainer from '../AnalyticsTabbedContainer.vue';
import {
    donut,
    formatNumber,
    formatPercentage,
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

// Konfigurasi views untuk kontainer terpadu
const views = computed(() => [
    {
        key: 'donut',
        tabLabel: 'Proporsi Donut',
        title: 'Proporsi kelengkapan profil alumni',
        description: 'Persentase alumni dengan profil lengkap, belum lengkap, atau belum diverifikasi.',
        option: donutOption.value,
        tableData: donutTable.value,
        height: 380,
        analysis: {
            title: 'Kilas Kelengkapan Data Alumni',
            statusBadge: 'Status Terkini',
            badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
            lead: series.value[0]?.label ? `Status profil mayoritas adalah ${series.value[0].label} (${series.value[0].count} alumni · ${formatPercentage(series.value[0].percentage)}).` : 'Data kelengkapan profil tersaji lengkap.',
            detail: 'Tingkat kelengkapan profil menjamin keakuratan tindak lanjut tracer study dan akreditasi prodi.',
        },
    },
    {
        key: 'stacked',
        tabLabel: 'Komposisi Prodi',
        title: 'Komposisi kelengkapan profil per program studi',
        description: 'Tingkat partisipasi pembaruan data profil lulusan pada masing-masing jurusan.',
        option: stackedOption.value,
        tableData: stackedTable.value,
        height: Math.max(340, Math.min(prodisCount.value * 34 + 110, 560)),
        analysis: {
            title: 'Kilas Partisipasi Antarjurusan',
            statusBadge: 'Perbandingan 100%',
            badgeClass: 'bg-slate-100 text-slate-700',
            lead: `Sebaran pembaruan profil alumni di ${prodisCount.value} program studi aktif.`,
            detail: 'Menunjukkan prodi dengan tingkat respons alumni tertinggi dalam melengkapi profil biodata.',
        },
    },
    {
        key: 'heatmap',
        tabLabel: 'Antar Prodi',
        title: 'Pola kelengkapan data antarprogram studi',
        description: props.heatmapMode === 'percentage'
            ? 'Intensitas dan angka sel menunjukkan persentase kategori dalam setiap program studi.'
            : 'Intensitas dan angka sel menunjukkan jumlah record pada setiap kategori dan program studi.',
        option: heatmapOption.value,
        tableData: heatmapTable.value,
        height: Math.max(360, Math.min(prodisCount.value * 34 + 140, 580)),
        analysis: {
            title: 'Kilas Matriks Partisipasi',
            statusBadge: 'Matriks Data',
            badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
            lead: 'Matriks heatmap membedah angka kelengkapan profil di setiap program studi.',
            detail: 'Klik salah satu sel untuk melihat rincian alumni pada program studi dan status terkait.',
        },
    },
]);
</script>

<template>
    <AnalyticsTabbedContainer
        :views="views"
        :rows="records"
        :selected-label="selectedLabel"
        :loading="loading"
        indicator-badge="Indikator 17"
        @select="emit('select', $event)"
        @reset="emit('reset')"
    />
</template>
