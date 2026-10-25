<script setup>
/**
 * Komponen Indikator 11: Pendapatan per Bulan dan Perbandingan 1,2× UMP.
 *
 * Mengelola seluruh visualisasi dan tabel data pendapatan alumni secara mandiri:
 * 1. Line Chart: Median Gaji per Program Studi (Gambar 4.12 laporan resmi dalam rupiah)
 * 2. Boxplot: Sebaran Gaji per Program Studi (Q1, Median, Q3, Outlier)
 * 3. Bullet Chart: Proporsi Pemenuhan Ambang 1,2× UMP Provinsi
 * 4. Scatter Plot: Gaji Aktual terhadap Ambang Provinsi
 * 5. Peta Interaktif Wilayah Kerja Alumni
 */
import { computed, defineAsyncComponent } from 'vue';
import AnalyticsChartCard from '../AnalyticsChartCard.vue';
import {
    baseOption,
    escapeHtml,
    formatCompactCurrency,
    formatFullCurrency,
    formatNumber,
    getProdiMedians,
} from '../analyticsChartOptions';

// Peta wilayah kerja dimuat secara asynchronous.
const AnalyticsMap = defineAsyncComponent(() => import('../AnalyticsMap.vue'));

// Kontrak properti data indikator, tahun UMP, dan status loading.
const props = defineProps({
    chart: { type: Object, default: () => ({}) },
    selectedLabel: { type: String, default: '' },
    heatmapMode: { type: String, default: 'count' },
    availableUmpYears: { type: Array, default: () => [] },
    selectedUmpYear: { type: String, default: '' },
    loading: { type: Boolean, default: false },
});

// Event seleksi data, perubahan tahun UMP, dan klik provinsi.
const emit = defineEmits(['select', 'reset', 'change-ump-year', 'select-province']);

// Ekstraksi catatan responden dan ringkasan.
const records = computed(() => props.chart?.records || []);
const summary = computed(() => props.chart?.summary || {});

// -----------------------------------------------------------------------------
// 1. LINE CHART: Median Gaji per Program Studi (Gambar 4.12)
// -----------------------------------------------------------------------------
const prodiSalaryData = computed(() => getProdiMedians(records.value, 'salary'));

const prodiSalaryOption = computed(() => {
    const list = prodiSalaryData.value;
    const labels = list.map((item) => `${item.prodi}\n(n=${item.count})`);
    const values = list.map((item) => item.median);

    return {
        ...baseOption(records.value.length),
        grid: { left: 16, right: 30, top: 28, bottom: list.length > 5 ? 56 : 38, containLabel: true },
        xAxis: {
            type: 'category',
            data: labels,
            axisLabel: { color: '#334155', fontSize: 11, interval: 0 },
            axisLine: { lineStyle: { color: '#CBD5E1' } },
        },
        yAxis: {
            type: 'value',
            name: 'Rupiah',
            axisLabel: { color: '#64748B', formatter: formatCompactCurrency },
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
        },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'axis',
            formatter: (params) => {
                const item = list[params[0]?.dataIndex ?? 0] || {};
                return `<strong>${escapeHtml(item.prodi)}</strong><br>Median gaji: <strong>${formatFullCurrency(item.median)}</strong><br>Jumlah responden: <strong>n = ${formatNumber(item.count, 0)}</strong>`;
            },
        },
        series: [{
            name: 'Median Gaji',
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
                formatter: ({ value }) => formatCompactCurrency(value),
            },
        }],
    };
});

const prodiSalaryTable = computed(() => ({
    columns: [
        { key: 'prodi', label: 'Program Studi' },
        { key: 'medianDisplay', label: 'Median Pendapatan', align: 'right' },
        { key: 'count', label: 'Responden (n)', align: 'right' },
    ],
    rows: prodiSalaryData.value.map((item) => ({
        prodi: item.prodi,
        medianDisplay: formatFullCurrency(item.median),
        count: formatNumber(item.count, 0),
    })),
}));

// -----------------------------------------------------------------------------
// 2. BOXPLOT: Sebaran Gaji per Program Studi
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
            .map((r) => Number(r.salary))
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

const salaryBoxplotOption = computed(() => {
    const { groups, values, outliers } = boxplotData.value;

    return {
        ...baseOption(records.value.length),
        legend: { data: ['Distribusi', 'Outlier'], bottom: 0 },
        grid: { left: 16, right: 24, top: 20, bottom: groups.length > 6 ? 90 : 54, containLabel: true },
        xAxis: { type: 'category', data: groups, axisLabel: { rotate: groups.length > 5 ? 25 : 0, width: 120, overflow: 'truncate' } },
        yAxis: { type: 'value', axisLabel: { formatter: formatCompactCurrency }, splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } } },
        dataZoom: groups.length > 8 ? [{ type: 'inside' }] : [],
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'item',
            formatter: ({ seriesType, name, value }) => {
                if (seriesType === 'scatter') return `<strong>${escapeHtml(groups[value[0]])}</strong><br>Outlier: ${formatFullCurrency(value[1])}`;
                const statistics = value.length > 5 ? value.slice(-5) : value;
                return `<strong>${escapeHtml(name)}</strong><br>Whisker bawah: ${formatFullCurrency(statistics[0])}<br>Q1: ${formatFullCurrency(statistics[1])}<br>Median: ${formatFullCurrency(statistics[2])}<br>Q3: ${formatFullCurrency(statistics[3])}<br>Whisker atas: ${formatFullCurrency(statistics[4])}`;
            },
        },
        series: [
            { name: 'Distribusi', type: 'boxplot', data: values, itemStyle: { color: '#8DD8AE', borderColor: '#0D542B', borderWidth: 2 } },
            { name: 'Outlier', type: 'scatter', data: outliers, symbolSize: 7, itemStyle: { color: '#DC2626' } },
        ],
    };
});

const salaryBoxplotTable = computed(() => ({
    columns: [
        { key: 'group', label: 'Program Studi' },
        { key: 'medianDisplay', label: 'Median', align: 'right' },
        { key: 'iqrDisplay', label: 'Q1 – Q3', align: 'right' },
        { key: 'nDisplay', label: 'Sampel (n)', align: 'right' },
    ],
    rows: boxplotData.value.statsTable.map((item) => ({
        group: item.group,
        medianDisplay: formatFullCurrency(item.median),
        iqrDisplay: `${formatCompactCurrency(item.q1)} – ${formatCompactCurrency(item.q3)}`,
        nDisplay: formatNumber(item.n, 0),
    })),
}));

// -----------------------------------------------------------------------------
// 3. BULLET CHART: Pemenuhan Ambang 1,2× UMP
// -----------------------------------------------------------------------------
const meetingCount = computed(() => summary.value.meeting_count || 0);
const belowCount = computed(() => summary.value.below_count || 0);

const bulletOption = computed(() => {
    const positive = meetingCount.value;
    const negative = belowCount.value;
    const total = Math.max(positive + negative, 1);
    const posPct = Number(((positive / total) * 100).toFixed(1));
    const negPct = Number(((negative / total) * 100).toFixed(1));

    return {
        ...baseOption(total),
        legend: { data: ['Memenuhi 1,2× UMP', 'Belum memenuhi'], bottom: 0 },
        grid: { left: 18, right: 18, top: 40, bottom: 44, containLabel: true },
        xAxis: { type: 'value', max: 100, axisLabel: { formatter: '{value}%' }, splitLine: { show: false } },
        yAxis: { type: 'category', data: ['Komposisi'], axisTick: { show: false }, axisLine: { show: false } },
        tooltip: { ...baseOption().tooltip, trigger: 'axis', axisPointer: { type: 'shadow' }, valueFormatter: (value) => `${formatNumber(value)}%` },
        series: [
            { name: 'Memenuhi 1,2× UMP', type: 'bar', stack: 'total', barWidth: 38, itemStyle: { color: '#0D542B' }, label: { show: posPct >= 10, position: 'inside', formatter: `${posPct}%`, color: '#FFFFFF', fontWeight: 700 }, data: [posPct] },
            { name: 'Belum memenuhi', type: 'bar', stack: 'total', barWidth: 38, itemStyle: { color: '#DC2626' }, label: { show: negPct >= 10, position: 'inside', formatter: `${negPct}%`, color: '#FFFFFF', fontWeight: 700 }, data: [negPct] },
        ],
    };
});

const bulletTable = computed(() => {
    const total = Math.max(meetingCount.value + belowCount.value, 1);
    return {
        columns: [
            { key: 'status', label: 'Status Kelayakan' },
            { key: 'count', label: 'Jumlah Alumni', align: 'right' },
            { key: 'percentage', label: 'Persentase', align: 'right' },
        ],
        rows: [
            { status: 'Memenuhi 1,2× UMP', count: formatNumber(meetingCount.value, 0), percentage: `${((meetingCount.value / total) * 100).toFixed(1)}%` },
            { status: 'Belum memenuhi', count: formatNumber(belowCount.value, 0), percentage: `${((belowCount.value / total) * 100).toFixed(1)}%` },
        ],
    };
});

// -----------------------------------------------------------------------------
// 4. SCATTER: Gaji Aktual terhadap Ambang Provinsi
// -----------------------------------------------------------------------------
const scatterOption = computed(() => {
    const meetsRows = records.value.filter((r) => r.meets_ump);
    const belowRows = records.value.filter((r) => !r.meets_ump);

    return {
        ...baseOption(records.value.length),
        legend: { data: ['Memenuhi', 'Belum memenuhi'], bottom: 0 },
        grid: { left: 16, right: 24, top: 18, bottom: 52, containLabel: true },
        xAxis: { type: 'value', name: 'Ambang 1,2 × UMP', nameLocation: 'middle', nameGap: 28, axisLabel: { formatter: formatCompactCurrency }, splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } } },
        yAxis: { type: 'value', name: 'Gaji alumni', axisLabel: { formatter: formatCompactCurrency }, splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } } },
        tooltip: { ...baseOption().tooltip, formatter: ({ data }) => `<strong>${escapeHtml(data.name)}</strong><br>${escapeHtml(data.prodi)}<br>${escapeHtml(data.company)}<br>Gaji: <strong>${formatCompactCurrency(data.value[1])}</strong><br>Ambang: ${formatCompactCurrency(data.value[0])}<br>Status: ${data.meets ? 'Memenuhi' : 'Belum memenuhi'}` },
        series: [
            { name: 'Memenuhi', type: 'scatter', symbolSize: 10, data: meetsRows.map((r) => ({ name: r.nama, prodi: r.nama_prodi, company: r.nama_perusahaan, meets: true, value: [Number(r.threshold), Number(r.salary)] })), itemStyle: { color: '#0D542B', opacity: 0.75 } },
            { name: 'Belum memenuhi', type: 'scatter', symbolSize: 10, data: belowRows.map((r) => ({ name: r.nama, prodi: r.nama_prodi, company: r.nama_perusahaan, meets: false, value: [Number(r.threshold), Number(r.salary)] })), itemStyle: { color: '#DC2626', opacity: 0.75 } },
        ],
    };
});

const scatterTable = computed(() => ({
    columns: [
        { key: 'name', label: 'Nama Alumni' },
        { key: 'company', label: 'Perusahaan' },
        { key: 'salary', label: 'Gaji Aktual', align: 'right' },
        { key: 'status', label: 'Status UMP', align: 'center' },
    ],
    rows: records.value.slice(0, 15).map((r) => ({
        name: r.nama,
        company: r.nama_perusahaan || '-',
        salary: formatFullCurrency(r.salary),
        status: r.meets_ump ? 'Memenuhi 1,2×' : 'Belum Memenuhi',
    })),
}));
</script>

<template>
    <div class="space-y-6">
        <!-- 1. Pendapatan per Bulan per Program Studi (Gambar 4.12) -->
        <AnalyticsChartCard
            chart-key="prodi_salary_line"
            title="Pendapatan per bulan per program studi"
            description="Median gaji alumni per program studi dalam rupiah (mengadopsi Gambar 4.12 Laporan Tracer Study)."
            :option="prodiSalaryOption"
            :table-data="prodiSalaryTable"
            :rows="records"
            :height="410"
            :selected-label="selectedLabel"
            :loading="loading"
            @select="emit('select', $event)"
            @reset="emit('reset')"
        />

        <!-- 2. Sebaran Gaji per Program Studi (Boxplot) -->
        <AnalyticsChartCard
            chart-key="boxplot"
            title="Sebaran gaji per program studi"
            description="Median, kuartil, whisker 1,5 × IQR, dan outlier gaji alumni."
            :option="salaryBoxplotOption"
            :table-data="salaryBoxplotTable"
            :rows="records"
            :height="430"
            :selected-label="selectedLabel"
            :loading="loading"
            @select="emit('select', $event)"
            @reset="emit('reset')"
        />

        <!-- 3. Pemenuhan Ambang 1,2× UMP -->
        <AnalyticsChartCard
            chart-key="bullet"
            title="Pemenuhan ambang 1,2 × UMP"
            description="Proporsi alumni yang gajinya memenuhi vs belum memenuhi ambang provinsi."
            :option="bulletOption"
            :table-data="bulletTable"
            :rows="records"
            :height="280"
            :selected-label="selectedLabel"
            :loading="loading"
            @select="emit('select', $event)"
            @reset="emit('reset')"
        />

        <!-- 4. Gaji Aktual terhadap Ambang Provinsi (Scatter) -->
        <AnalyticsChartCard
            chart-key="scatter"
            title="Gaji aktual terhadap ambang provinsi"
            description="Sebaran alumni terhadap ambang provinsi kerja."
            :option="scatterOption"
            :table-data="scatterTable"
            :rows="records"
            :height="420"
            :selected-label="selectedLabel"
            :loading="loading"
            @select="emit('select', $event)"
            @reset="emit('reset')"
        />

        <!-- 5. Peta Sebaran Wilayah Kerja Alumni -->
        <article class="bg-white p-5 shadow-sm sm:p-6 border border-slate-100">
            <div class="flex flex-col justify-between gap-3 border-b border-slate-100 pb-4 sm:flex-row sm:items-start">
                <div>
                    <h3 class="text-lg font-extrabold text-slate-900">Peta sebaran pendapatan dan wilayah kerja</h3>
                    <p class="mt-1 text-sm text-slate-500">Lokasi mengikuti provinsi perusahaan tempat alumni bekerja. Klik wilayah untuk menelusuri data alumni.</p>
                </div>
                <label class="text-xs font-bold text-slate-600">
                    Tahun UMP
                    <select
                        :value="selectedUmpYear"
                        class="mt-1 block rounded bg-slate-100 px-3 py-2 text-sm font-bold text-slate-900 outline-none"
                        @change="emit('change-ump-year', $event.target.value)"
                    >
                        <option v-for="year in availableUmpYears" :key="year" :value="year">{{ year }}</option>
                    </select>
                </label>
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
