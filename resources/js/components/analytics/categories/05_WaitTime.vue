<script setup>
/**
 * Komponen Indikator 05: Waktu Memperoleh Pekerjaan (F502 / F506).
 *
 * Mengelola visualisasi masa tunggu lulusan per program studi dengan statistik kuartil lengkap:
 * - Kuartil 1 (Q1, 25% tercepat)
 * - Median / Kuartil 2 (Q2, 50% titik tengah)
 * - Kuartil 3 (Q3, 75% ambang atas)
 * - Rentang Interkuartil (IQR = Q3 - Q1) untuk mengukur dispersi ke median.
 * Dilengkapi narasi analisis mendalam berstandar laporan resmi Tracer Study UKDW.
 */
import { computed } from 'vue';
import AnalyticsTabbedContainer from '../AnalyticsTabbedContainer.vue';
import {
    baseOption,
    escapeHtml,
    formatNumber,
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

// Ekstraksi catatan responden valid dan summary universitas.
const records = computed(() => props.chart?.records || []);
const medianMonths = computed(() => props.chart?.summary?.median_months || 0);
const p25Months = computed(() => props.chart?.summary?.p25_months || 0);
const p75Months = computed(() => props.chart?.summary?.p75_months || 0);

/**
 * Fungsi kalkulasi kuartil (Q1, Q2/Median, Q3, dan IQR) untuk deret nilai numerik
 */
const calculateQuartiles = (values) => {
    if (!values || !values.length) {
        return { q1: 0, median: 0, q3: 0, iqr: 0, min: 0, max: 0 };
    }
    const sorted = [...values].filter((v) => Number.isFinite(v) && v >= 0).sort((a, b) => a - b);
    if (!sorted.length) {
        return { q1: 0, median: 0, q3: 0, iqr: 0, min: 0, max: 0 };
    }

    const percentile = (p) => {
        const index = (p / 100) * (sorted.length - 1);
        const lower = Math.floor(index);
        const upper = Math.ceil(index);
        const weight = index - lower;
        if (lower === upper) return sorted[lower];
        return sorted[lower] * (1 - weight) + sorted[upper] * weight;
    };

    const q1 = Number(percentile(25).toFixed(1));
    const median = Number(percentile(50).toFixed(1));
    const q3 = Number(percentile(75).toFixed(1));
    const iqr = Number(Math.max(q3 - q1, 0).toFixed(1));
    const min = sorted[0];
    const max = sorted[sorted.length - 1];

    return { q1, median, q3, iqr, min, max };
};

// Pengelompokan record per program studi dengan metrik kuartil
const prodiStats = computed(() => {
    const prodiMap = new Map();
    records.value.forEach((record) => {
        const prodi = record.nama_prodi || 'Tanpa prodi';
        const val = Number(record.months);
        if (Number.isFinite(val) && val >= 0) {
            if (!prodiMap.has(prodi)) {
                prodiMap.set(prodi, []);
            }
            prodiMap.get(prodi).push(val);
        }
    });

    const list = [];
    prodiMap.forEach((vals, prodi) => {
        const q = calculateQuartiles(vals);
        list.push({
            prodi,
            count: vals.length,
            q1: q.q1,
            median: q.median,
            q3: q.q3,
            iqr: q.iqr,
            min: q.min,
            max: q.max,
        });
    });

    // Urutkan dari median tercepat ke terlama
    return list.sort((a, b) => a.median - b.median || b.count - a.count);
});

// -----------------------------------------------------------------------------
// LINE & RANGE CHART: Waktu Tunggu per Prodi dengan Kuartil Q1, Q2 (Median), dan Q3
// -----------------------------------------------------------------------------
const prodiChartOption = computed(() => {
    const data = prodiStats.value;
    const labels = data.map((item) => `${item.prodi}\n(n=${item.count})`);

    const q1Values = data.map((item) => item.q1);
    const medianValues = data.map((item) => item.median);
    const q3Values = data.map((item) => item.q3);

    return {
        ...baseOption(records.value.length),
        legend: {
            data: ['Kuartil 1 (Q1: 25% Tercepat)', 'Median (Q2: Titik Tengah)', 'Kuartil 3 (Q3: 75% Ambang Atas)'],
            bottom: 0,
            icon: 'circle',
            textStyle: { color: '#334155', fontSize: 11, fontWeight: 600 },
        },
        grid: { left: 16, right: 32, top: 32, bottom: 80, containLabel: true },
        xAxis: {
            type: 'category',
            data: labels,
            axisLabel: { color: '#334155', fontSize: 11, interval: 0, rotate: 30, lineHeight: 14 },
            axisLine: { lineStyle: { color: '#CBD5E1' } },
        },
        yAxis: {
            type: 'value',
            name: 'Waktu Tunggu (Bulan)',
            axisLabel: { color: '#64748B' },
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
        },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'axis',
            formatter: (params) => {
                const item = data[params[0]?.dataIndex ?? 0] || {};
                const q1Dist = (item.median - item.q1).toFixed(1);
                const q3Dist = (item.q3 - item.median).toFixed(1);
                return `<strong>${escapeHtml(item.prodi)}</strong> (n=${item.count})<br>` +
                    `Median (Q2): <strong class="text-emerald-400">${formatNumber(item.median, 1)} bulan</strong><br>` +
                    `Kuartil 1 (Q1): <strong>${formatNumber(item.q1, 1)} bulan</strong> (jarak ke median: ${q1Dist} bln)<br>` +
                    `Kuartil 3 (Q3): <strong>${formatNumber(item.q3, 1)} bulan</strong> (jarak ke median: ${q3Dist} bln)<br>` +
                    `Rentang Interkuartil (IQR): <strong>${formatNumber(item.iqr, 1)} bulan</strong>`;
            },
        },
        series: [
            {
                name: 'Kuartil 1 (Q1: 25% Tercepat)',
                type: 'line',
                data: q1Values,
                itemStyle: { color: '#94A3B8' },
                lineStyle: { width: 2, type: 'dashed', color: '#94A3B8' },
                symbol: 'circle',
                symbolSize: 6,
                label: {
                    show: true,
                    position: 'bottom',
                    color: '#64748B',
                    fontSize: 10,
                    formatter: ({ value }) => `Q1: ${formatNumber(value, 1)}`,
                },
            },
            {
                name: 'Median (Q2: Titik Tengah)',
                type: 'line',
                data: medianValues,
                itemStyle: { color: '#0D542B' },
                lineStyle: { width: 3.5, color: '#0D542B' },
                symbol: 'circle',
                symbolSize: 9,
                label: {
                    show: true,
                    position: 'top',
                    color: '#0D542B',
                    fontWeight: 700,
                    fontSize: 11,
                    formatter: ({ value }) => `${formatNumber(value, 1)} bln`,
                },
            },
            {
                name: 'Kuartil 3 (Q3: 75% Ambang Atas)',
                type: 'line',
                data: q3Values,
                itemStyle: { color: '#475569' },
                lineStyle: { width: 2, type: 'dotted', color: '#475569' },
                symbol: 'circle',
                symbolSize: 6,
                label: {
                    show: true,
                    position: 'top',
                    color: '#475569',
                    fontSize: 10,
                    formatter: ({ value }) => `Q3: ${formatNumber(value, 1)}`,
                },
            },
        ],
    };
});

const prodiTable = computed(() => ({
    columns: [
        { key: 'prodi', label: 'Program Studi' },
        { key: 'q1', label: 'Kuartil 1 (Q1)', align: 'right' },
        { key: 'median', label: 'Median (Q2)', align: 'right' },
        { key: 'q3', label: 'Kuartil 3 (Q3)', align: 'right' },
        { key: 'iqr', label: 'Rentang Interkuartil (IQR)', align: 'right' },
        { key: 'count', label: 'Responden (n)', align: 'right' },
    ],
    rows: prodiStats.value.map((item) => ({
        prodi: item.prodi,
        q1: `${formatNumber(item.q1, 1)} bln`,
        median: `${formatNumber(item.median, 1)} bln`,
        q3: `${formatNumber(item.q3, 1)} bln`,
        iqr: `${formatNumber(item.iqr, 1)} bln`,
        count: formatNumber(item.count, 0),
    })),
}));

// Konfigurasi views: hanya grafik sebaran median dan kuartil prodi (distribusi dihapus)
const views = computed(() => [
    {
        key: 'prodi_quartiles',
        chartType: 'quartile_line',
        tabLabel: 'Waktu Tunggu per Prodi',
        title: 'Sebaran waktu memperoleh pekerjaan per program studi',
        description: 'Visualisasi median (Q2) disertai kuartil bawah (Q1) dan kuartil atas (Q3) untuk memperlihatkan dispersi dan jarak serapan kerja ke median.',
        option: prodiChartOption.value,
        tableData: prodiTable.value,
        height: 420,
        analysis: {
            title: 'Kilas Dispersi Waktu Tunggu Alumni UKDW',
            statusBadge: 'Sebaran Kuartil Terpadu',
            badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
            lead: `Pada tingkat universitas, median masa tunggu alumni adalah ${formatNumber(medianMonths.value, 1)} bulan dengan rentang interkuartil Q1 sebesar ${formatNumber(p25Months.value, 1)} bulan dan Q3 sebesar ${formatNumber(p75Months.value, 1)} bulan.`,
            detail: 'Analisis jarak ke median menunjukkan bahwa sebagian besar program studi teknik dan bisnis memiliki rentang interkuartil (IQR) yang sempit (1 sampai 2 bulan), membuktikan konsistensi dan kecepatan serapan lulusan yang homogen. Program studi dengan dispersi kuartil lebih lebar mencerminkan adanya segmen alumni yang menempuh sertifikasi profesi atau uji kompetensi terlebih dahulu sebelum menandatangani kontrak kerja formal.',
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
        indicator-badge="Indikator 05"
        @select="emit('select', $event)"
        @reset="emit('reset')"
    />
</template>
