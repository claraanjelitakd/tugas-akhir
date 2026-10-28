<script setup>
/**
 * Komponen Indikator 18: Sebaran Wilayah Kerja Alumni.
 *
 * Mengelola seluruh visualisasi dan data tabel secara mandiri dalam kontainer terpadu:
 * 1. Horizontal Bar: Distribusi Provinsi Utama Tempat Bekerja
 * 2. Treemap: Komposisi Wilayah Kerja
 * 3. Heatmap: Pola Antarprogram Studi
 * 4. Peta Interaktif MapLibre: Sebaran Geografis Provinsi
 */
import { computed, defineAsyncComponent } from 'vue';
import AnalyticsTabbedContainer from '../AnalyticsTabbedContainer.vue';
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
// 2. TREEMAP: Komposisi Wilayah Karir Alumni
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

// Konfigurasi views untuk kontainer terpadu
const views = computed(() => [
    {
        key: 'distribution',
        tabLabel: 'Distribusi Provinsi',
        title: 'Distribusi provinsi tempat bekerja alumni',
        description: 'Daftar provinsi tempat perusahaan atau kantor alumni beroperasi.',
        option: barOption.value,
        tableData: barTable.value,
        height: Math.max(340, Math.min(series.value.length * 38 + 90, 640)),
        analysis: {
            title: 'Kilas Provinsi Pusat Kerja',
            statusBadge: 'Provinsi Utama',
            badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
            lead: series.value[0]?.label ? `Provinsi dengan konsentrasi alumni tertinggi adalah ${series.value[0].label} (${series.value[0].count} alumni).` : 'Data wilayah kerja terdata lengkap.',
            detail: 'Menggambarkan jangkauan serapan geografis lulusan di tingkat regional dan nasional.',
        },
    },
    {
        key: 'treemap',
        tabLabel: 'Komposisi Bidang',
        title: 'Komposisi wilayah karir alumni',
        description: 'Luas blok merefleksikan konsentrasi serapan alumni pada masing-masing daerah.',
        option: treemapOption.value,
        tableData: treemapTable.value,
        height: 380,
        analysis: {
            title: 'Kilas Kerapatan Regional',
            statusBadge: 'Peta Kerapatan',
            badgeClass: 'bg-slate-100 text-slate-700',
            lead: 'Visualisasi treemap membandingkan proporsi penyerapan kerja alumni di berbagai daerah administratif.',
            detail: 'Semakin besar luas bidang, semakin signifikan peran wilayah tersebut sebagai magnet karier lulusan.',
        },
    },
    {
        key: 'heatmap',
        tabLabel: 'Antar Prodi',
        title: 'Pola sebaran geografis antarprogram studi',
        description: props.heatmapMode === 'percentage'
            ? 'Intensitas dan angka sel menunjukkan persentase kategori dalam setiap program studi.'
            : 'Intensitas dan angka sel menunjukkan jumlah record pada setiap kategori dan program studi.',
        option: heatmapOption.value,
        tableData: heatmapTable.value,
        height: Math.max(360, Math.min(prodisCount.value * 34 + 140, 580)),
        analysis: {
            title: 'Kilas Mobilitas Antarjurusan',
            statusBadge: 'Matriks Geografis',
            badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
            lead: `Sebaran mobilitas alumni dari ${prodisCount.value} program studi menuju provinsi tujuan kerja.`,
            detail: 'Klik salah satu sel untuk melihat rincian alumni pada program studi dan provinsi terkait.',
        },
    },
    {
        key: 'map',
        tabLabel: 'Peta Wilayah',
        title: 'Peta sebaran wilayah kerja alumni',
        description: 'Sebaran provinsi perusahaan tempat alumni berkarir. Klik wilayah untuk menelusuri data.',
        isMap: true,
        analysis: {
            title: 'Kilas Peta Spasial Indonesia',
            statusBadge: 'Peta Geospasial',
            badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
            lead: 'Peta interaktif memetakan sebaran spasial alumni di seluruh kepulauan Indonesia.',
            detail: 'Klik suatu provinsi pada peta untuk menyaring data alumni yang berkarier di provinsi tersebut.',
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
        indicator-badge="Indikator 18"
        @select="emit('select', $event)"
        @reset="emit('reset')"
    >
        <template #map>
            <AnalyticsMap
                :regions="chart.regions || []"
                metric-label="alumni"
                @province-selected="emit('select-province', $event)"
            />
        </template>
    </AnalyticsTabbedContainer>
</template>
