<script setup>
/**
 * Komponen Indikator 05: Waktu Memperoleh Pekerjaan.
 *
 * Mengelola seluruh visualisasi dan tabel data waktu tunggu alumni secara mandiri:
 * 1. Line Chart: Waktu Tunggu per Prodi (Gambar 4.6 laporan resmi dengan sampel n)
 * 2. Bar Chart: Distribusi Frekuensi Waktu Tunggu (0-3 bln, 4-6 bln, dst.)
 * 3. Boxplot: Sebaran Waktu Tunggu per Program Studi
 * 4. Line Chart: Kurva Kumulatif Memperoleh Pekerjaan
 */
import { computed } from 'vue';
import AnalyticsChartCard from '../AnalyticsChartCard.vue';
import {
    baseOption,
    calculateMedian,
    escapeHtml,
    formatNumber,
    getProdiMedians,
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

// Ekstraksi catatan responden valid.
const records = computed(() => props.chart?.records || []);
const medianMonths = computed(() => props.chart?.summary?.median_months || 0);

// -----------------------------------------------------------------------------
// 1. LINE CHART: Waktu Tunggu per Program Studi (Gambar 4.6)
// -----------------------------------------------------------------------------
const prodiWaitData = computed(() => getProdiMedians(records.value, 'months'));

const prodiLineOption = computed(() => {
    const data = prodiWaitData.value;
    const labels = data.map((item) => `${item.prodi}\n(n=${item.count})`);
    const values = data.map((item) => item.median);

    return {
        ...baseOption(records.value.length),
        grid: { left: 16, right: 30, top: 28, bottom: data.length > 5 ? 56 : 38, containLabel: true },
        xAxis: {
            type: 'category',
            data: labels,
            axisLabel: { color: '#334155', fontSize: 11, interval: 0 },
            axisLine: { lineStyle: { color: '#CBD5E1' } },
        },
        yAxis: {
            type: 'value',
            name: 'Median (Bulan)',
            axisLabel: { color: '#64748B' },
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
        },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'axis',
            formatter: (params) => {
                const item = data[params[0]?.dataIndex ?? 0] || {};
                return `<strong>${escapeHtml(item.prodi)}</strong><br>Median waktu tunggu: <strong>${formatNumber(item.median, 1)} bulan</strong><br>Jumlah responden: <strong>n = ${formatNumber(item.count, 0)}</strong>`;
            },
        },
        series: [{
            name: 'Median Waktu Tunggu',
            type: 'line',
            data: values,
            itemStyle: { color: '#2563EB' },
            lineStyle: { width: 3, color: '#2563EB' },
            symbol: 'circle',
            symbolSize: 8,
            emphasis: { scale: 1.6 },
            label: {
                show: true,
                position: 'top',
                color: '#1E3A8A',
                fontWeight: 700,
                formatter: ({ value }) => formatNumber(value, 1),
            },
        }],
    };
});

const prodiLineTable = computed(() => ({
    columns: [
        { key: 'prodi', label: 'Program Studi' },
        { key: 'median', label: 'Median Waktu Tunggu', align: 'right' },
        { key: 'count', label: 'Responden (n)', align: 'right' },
    ],
    rows: prodiWaitData.value.map((item) => ({
        prodi: item.prodi,
        median: `${formatNumber(item.median, 1)} bulan`,
        count: formatNumber(item.count, 0),
    })),
}));

// -----------------------------------------------------------------------------
// 2. BAR CHART: Distribusi Interval Waktu Tunggu
// -----------------------------------------------------------------------------
const waitBins = computed(() => {
    const list = [
        { name: '0–3 bulan', min: 0, max: 3 },
        { name: '4–6 bulan', min: 4, max: 6 },
        { name: '7–12 bulan', min: 7, max: 12 },
        { name: '>12 bulan', min: 13, max: Number.POSITIVE_INFINITY },
    ];
    const total = Math.max(records.value.length, 1);

    return list.map((bin) => {
        const count = records.value.filter((r) => Number(r.months) >= bin.min && Number(r.months) <= bin.max).length;
        const pct = ((count / total) * 100).toFixed(1);
        const selected = !props.selectedLabel || props.selectedLabel === bin.name;
        return {
            ...bin,
            value: count,
            percentage: pct,
            itemStyle: { color: '#0D542B', opacity: selected ? 1 : 0.22 },
        };
    });
});

const waitHistogramOption = computed(() => ({
    ...baseOption(records.value.length),
    grid: { left: 16, right: 28, top: 34, bottom: 42, containLabel: true },
    xAxis: { type: 'category', data: waitBins.value.map((b) => b.name), axisLabel: { color: '#334155', fontWeight: 600 } },
    yAxis: { type: 'value', name: 'Jumlah Alumni', splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } } },
    tooltip: {
        ...baseOption().tooltip,
        trigger: 'axis',
        axisPointer: { type: 'shadow' },
        formatter: (params) => {
            const bin = params[0].data;
            return `<strong>${escapeHtml(bin.name)}</strong><br>Frekuensi: <strong>${formatNumber(bin.value, 0)}</strong><br>Persentase: <strong>${formatNumber(bin.percentage)}%</strong><br>Median keseluruhan: ${formatNumber(medianMonths.value)} bulan`;
        },
    },
    series: [{
        name: 'Frekuensi',
        type: 'bar',
        barMaxWidth: 56,
        data: waitBins.value,
        emphasis: { focus: 'self', itemStyle: { opacity: 1 } },
        markLine: {
            silent: true,
            symbol: 'none',
            label: { formatter: `Median: ${formatNumber(medianMonths.value)} bln`, color: '#0D542B', fontWeight: 700 },
            lineStyle: { color: '#0D542B', type: 'dashed', width: 2 },
            data: [{ xAxis: medianMonths.value <= 3 ? '0–3 bulan' : medianMonths.value <= 6 ? '4–6 bulan' : medianMonths.value <= 12 ? '7–12 bulan' : '>12 bulan' }],
        },
    }],
}));

const waitHistogramTable = computed(() => ({
    columns: [
        { key: 'name', label: 'Rentang Waktu Tunggu' },
        { key: 'count', label: 'Jumlah Alumni', align: 'right' },
        { key: 'percentage', label: 'Persentase', align: 'right' },
    ],
    rows: waitBins.value.map((b) => ({
        name: b.name,
        count: formatNumber(b.value, 0),
        percentage: `${formatNumber(b.percentage)}%`,
    })),
}));

// -----------------------------------------------------------------------------
// 3. BOXPLOT: Sebaran Waktu Tunggu per Program Studi
// -----------------------------------------------------------------------------
const boxplotData = computed(() => {
    const groups = [...new Set(records.value.map((r) => r.nama_prodi || 'Universitas'))];
    const quantile = (sorted, percentile) => {
        if (!sorted.length) return 0;
        const position = (sorted.length - 1) * percentile;
        const base = Math.floor(position);
        const rest = position - base;
        return sorted[base + 1] === undefined ? sorted[base] : sorted[base] + rest * (sorted[base + 1] - sorted[base]);
    };

    const outliers = [];
    const statsTable = [];

    const values = groups.map((group, groupIndex) => {
        const sorted = records.value
            .filter((r) => (r.nama_prodi || 'Universitas') === group)
            .map((r) => Number(r.months))
            .filter(Number.isFinite)
            .sort((a, b) => a - b);

        const q1 = quantile(sorted, 0.25);
        const median = quantile(sorted, 0.5);
        const q3 = quantile(sorted, 0.75);
        const iqr = q3 - q1;
        const lowerLimit = q1 - (1.5 * iqr);
        const upperLimit = q3 + (1.5 * iqr);
        const whiskerMin = sorted.find((val) => val >= lowerLimit) ?? sorted[0] ?? 0;
        const whiskerMax = [...sorted].reverse().find((val) => val <= upperLimit) ?? sorted.at(-1) ?? 0;

        sorted.filter((val) => val < whiskerMin || val > whiskerMax).forEach((val) => outliers.push([groupIndex, val]));

        statsTable.push({ group, n: sorted.length, q1, median, q3 });
        return [whiskerMin, q1, median, q3, whiskerMax];
    });

    return { groups, values, outliers, statsTable };
});

const boxplotOption = computed(() => {
    const { groups, values, outliers } = boxplotData.value;

    return {
        ...baseOption(records.value.length),
        legend: { data: ['Distribusi', 'Outlier'], bottom: 0 },
        grid: { left: 16, right: 24, top: 20, bottom: groups.length > 6 ? 90 : 54, containLabel: true },
        xAxis: { type: 'category', data: groups, axisLabel: { rotate: groups.length > 5 ? 25 : 0, width: 120, overflow: 'truncate' } },
        yAxis: { type: 'value', name: 'Bulan', splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } } },
        dataZoom: groups.length > 8 ? [{ type: 'inside' }] : [],
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'item',
            formatter: ({ seriesType, name, value }) => {
                if (seriesType === 'scatter') return `<strong>${escapeHtml(groups[value[0]])}</strong><br>Outlier: ${formatNumber(value[1], 1)} bulan`;
                const statistics = value.length > 5 ? value.slice(-5) : value;
                return `<strong>${escapeHtml(name)}</strong><br>Batas bawah: ${formatNumber(statistics[0], 1)} bln<br>Q1: ${formatNumber(statistics[1], 1)} bln<br>Median: ${formatNumber(statistics[2], 1)} bln<br>Q3: ${formatNumber(statistics[3], 1)} bln<br>Batas atas: ${formatNumber(statistics[4], 1)} bln`;
            },
        },
        series: [
            { name: 'Distribusi', type: 'boxplot', data: values, itemStyle: { color: '#8DD8AE', borderColor: '#0D542B', borderWidth: 2 } },
            { name: 'Outlier', type: 'scatter', data: outliers, symbolSize: 7, itemStyle: { color: '#DC2626' } },
        ],
    };
});

const boxplotTable = computed(() => ({
    columns: [
        { key: 'group', label: 'Program Studi' },
        { key: 'medianDisplay', label: 'Median', align: 'right' },
        { key: 'iqrDisplay', label: 'Q1 – Q3', align: 'right' },
        { key: 'nDisplay', label: 'Sampel (n)', align: 'right' },
    ],
    rows: boxplotData.value.statsTable.map((item) => ({
        group: item.group,
        medianDisplay: `${formatNumber(item.median, 1)} bln`,
        iqrDisplay: `${formatNumber(item.q1, 1)} – ${formatNumber(item.q3, 1)} bln`,
        nDisplay: formatNumber(item.n, 0),
    })),
}));

// -----------------------------------------------------------------------------
// 4. LINE CHART: Kurva Kumulatif Memperoleh Pekerjaan
// -----------------------------------------------------------------------------
const cumulativeData = computed(() => {
    const values = records.value.map((r) => Number(r.months)).filter(Number.isFinite).sort((a, b) => a - b);
    const months = [...new Set(values)];
    const points = months.map((m) => [m, Number(((values.filter((v) => v <= m).length / Math.max(values.length, 1)) * 100).toFixed(1))]);
    return { values, points };
});

const cumulativeOption = computed(() => ({
    ...baseOption(records.value.length),
    grid: { left: 16, right: 26, top: 22, bottom: 44, containLabel: true },
    xAxis: { type: 'value', name: 'Bulan ke-', nameLocation: 'middle', nameGap: 26, min: 0, splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } } },
    yAxis: { type: 'value', name: 'Persentase Kumulatif', max: 100, axisLabel: { formatter: '{value}%' }, splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } } },
    tooltip: { ...baseOption().tooltip, trigger: 'axis', formatter: (params) => `<strong>Bulan ke-${formatNumber(params[0].value[0])}</strong><br>${formatNumber(params[0].value[1])}% alumni telah memperoleh pekerjaan` },
    series: [{ name: 'Kumulatif', type: 'line', smooth: true, symbolSize: 6, lineStyle: { color: '#0D542B', width: 3 }, itemStyle: { color: '#0D542B' }, areaStyle: { color: 'rgba(13,84,43,.08)' }, data: cumulativeData.value.points }],
}));

const cumulativeTable = computed(() => {
    const vals = cumulativeData.value.values;
    const milestones = [1, 2, 3, 6, 12];
    return {
        columns: [
            { key: 'milestone', label: 'Bulan Target' },
            { key: 'cumulativeDisplay', label: 'Capaian Kumulatif', align: 'right' },
        ],
        rows: milestones.map((m) => {
            const count = vals.filter((v) => v <= m).length;
            const pct = vals.length ? ((count / vals.length) * 100).toFixed(1) : 0;
            return {
                milestone: `≤ ${m} bulan`,
                cumulativeDisplay: `${formatNumber(pct)}% (${formatNumber(count, 0)} alumni)`,
            };
        }),
    };
});
</script>

<template>
    <div class="space-y-6">
        <!-- 1. Waktu Memperoleh Pekerjaan per Program Studi (Gambar 4.6) -->
        <AnalyticsChartCard
            chart-key="prodi_line"
            title="Waktu memperoleh pekerjaan per program studi"
            description="Median bulan waktu tunggu alumni per program studi dengan ukuran sampel n (mengadopsi Gambar 4.6 Laporan Tracer Study)."
            :option="prodiLineOption"
            :table-data="prodiLineTable"
            :rows="records"
            :height="390"
            :selected-label="selectedLabel"
            :loading="loading"
            @select="emit('select', $event)"
            @reset="emit('reset')"
        />

        <!-- 2. Distribusi Frekuensi Waktu Tunggu -->
        <AnalyticsChartCard
            chart-key="histogram"
            title="Distribusi waktu tunggu"
            description="Frekuensi alumni pada interval bulan aktual dengan garis referensi median."
            :option="waitHistogramOption"
            :table-data="waitHistogramTable"
            :rows="records"
            :height="380"
            :selected-label="selectedLabel"
            :loading="loading"
            @select="emit('select', $event)"
            @reset="emit('reset')"
        />

        <!-- 3. Box Plot Sebaran Waktu Tunggu -->
        <AnalyticsChartCard
            chart-key="boxplot"
            title="Box plot sebaran waktu tunggu per program studi"
            description="Whisker menggunakan 1,5 × IQR; titik di luar batas adalah outlier."
            :option="boxplotOption"
            :table-data="boxplotTable"
            :rows="records"
            :height="410"
            :selected-label="selectedLabel"
            :loading="loading"
            @select="emit('select', $event)"
            @reset="emit('reset')"
        />

        <!-- 4. Kurva Kumulatif Memperoleh Pekerjaan -->
        <AnalyticsChartCard
            chart-key="cumulative"
            title="Kurva kumulatif memperoleh pekerjaan"
            description="Persentase kumulatif alumni yang memperoleh pekerjaan dari bulan ke bulan."
            :option="cumulativeOption"
            :table-data="cumulativeTable"
            :rows="records"
            :height="380"
            :selected-label="selectedLabel"
            :loading="loading"
            @select="emit('select', $event)"
            @reset="emit('reset')"
        />
    </div>
</template>
