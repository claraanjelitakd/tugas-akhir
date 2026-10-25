<script setup>
/**
 * Komponen Indikator 01: Response Rate Universitas dan Program Studi.
 *
 * Mengelola seluruh visualisasi dan tabel data partisipasi responden secara mandiri:
 * 1. Grouped Bar: Perbandingan Populasi Target vs Responden Mengisi per Prodi
 * 2. Bar Chart: Response Rate (%) per Program Studi (1 warna solid terurut)
 * 3. Heatmap: Pola Antarprogram Studi
 */
import { computed } from 'vue';
import AnalyticsChartCard from '../AnalyticsChartCard.vue';
import {
    baseOption,
    escapeHtml,
    formatNumber,
    recordCategory,
    seriesLabel,
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
const totalPopulation = computed(() => series.value.reduce((sum, item) => sum + Number(item.population || 0), 0));

// -----------------------------------------------------------------------------
// 1. GROUPED BAR: Populasi Target vs Responden Mengisi per Program Studi
// -----------------------------------------------------------------------------
const groupedBarOption = computed(() => {
    const list = series.value;

    return {
        ...baseOption(totalPopulation.value),
        legend: { data: ['Populasi Target', 'Responden Mengisi'], bottom: 0 },
        grid: { left: 16, right: 22, top: 20, bottom: 64, containLabel: true },
        xAxis: { type: 'category', data: list.map(seriesLabel), axisLabel: { rotate: list.length > 5 ? 26 : 0, width: 110, overflow: 'truncate' } },
        yAxis: { type: 'value', name: 'Jumlah Alumni', splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } } },
        tooltip: { ...baseOption().tooltip, trigger: 'axis', axisPointer: { type: 'shadow' } },
        series: [
            { name: 'Populasi Target', type: 'bar', barMaxWidth: 28, itemStyle: { color: '#94A3B8' }, data: list.map((item) => Number(item.population || 0)) },
            { name: 'Responden Mengisi', type: 'bar', barMaxWidth: 28, itemStyle: { color: '#0D542B' }, data: list.map((item) => Number(item.respondents || 0)) },
        ],
    };
});

const groupedBarTable = computed(() => ({
    columns: [
        { key: 'name', label: 'Program Studi' },
        { key: 'population', label: 'Populasi Kohort', align: 'right' },
        { key: 'respondents', label: 'Responden', align: 'right' },
        { key: 'rate', label: 'Response Rate', align: 'right' },
    ],
    rows: series.value.map((item) => {
        const pop = Number(item.population || 0);
        const resp = Number(item.respondents || 0);
        const rate = pop > 0 ? ((resp / pop) * 100).toFixed(1) : '0';
        return {
            name: seriesLabel(item),
            population: formatNumber(pop, 0),
            respondents: formatNumber(resp, 0),
            rate: `${formatNumber(rate)}%`,
        };
    }),
}));

// -----------------------------------------------------------------------------
// 2. BAR CHART: Response Rate (%) per Program Studi
// -----------------------------------------------------------------------------
const sortedRates = computed(() => {
    return [...series.value]
        .map((item) => {
            const label = seriesLabel(item);
            const rate = Number(item.response_rate || 0);
            const selected = !props.selectedLabel || props.selectedLabel === label;
            return {
                name: label,
                value: rate,
                itemStyle: { color: '#0D542B', opacity: selected ? 1 : 0.22 },
            };
        })
        .sort((a, b) => b.value - a.value);
});

const rateBarOption = computed(() => {
    const rows = sortedRates.value;

    return {
        ...baseOption(totalPopulation.value),
        grid: { left: 12, right: 38, top: 12, bottom: rows.length > 8 ? 44 : 24, containLabel: true },
        xAxis: {
            type: 'value',
            name: 'Response rate (%)',
            nameLocation: 'middle',
            nameGap: 26,
            axisLabel: { color: '#64748B' },
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
        },
        yAxis: {
            type: 'category',
            inverse: true,
            data: rows.map((item) => item.name),
            axisLabel: { color: '#334155', width: 170, overflow: 'truncate' },
            axisTick: { show: false },
            axisLine: { lineStyle: { color: '#CBD5E1' } },
        },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            formatter: (params) => {
                const point = params[0]?.data || {};
                return `<strong>${escapeHtml(point.name)}</strong><br>Response rate: <strong>${formatNumber(point.value)}%</strong>`;
            },
        },
        series: [{
            name: 'Response rate (%)',
            type: 'bar',
            barMaxWidth: 26,
            emphasis: { focus: 'self', itemStyle: { opacity: 1 } },
            label: { show: true, position: 'right', color: '#334155', fontWeight: 600, formatter: ({ value }) => `${formatNumber(value)}%` },
            data: rows,
        }],
    };
});

const rateBarTable = computed(() => ({
    columns: [
        { key: 'name', label: 'Program Studi' },
        { key: 'rateDisplay', label: 'Response Rate', align: 'right' },
    ],
    rows: sortedRates.value.map((item) => ({
        name: item.name,
        rateDisplay: `${formatNumber(item.value)}%`,
    })),
}));

// -----------------------------------------------------------------------------
// 3. HEATMAP: Pola Antarprogram Studi
// -----------------------------------------------------------------------------
const prodis = computed(() => [...new Set(records.value.map((record) => record.nama_prodi || 'Tanpa prodi'))]);
const categories = computed(() => [...new Set(records.value.map(recordCategory))]);

const heatmapData = computed(() => {
    const listProdis = prodis.value;
    const listCats = categories.value;
    const totals = Object.fromEntries(listProdis.map((p) => [p, records.value.filter((r) => (r.nama_prodi || 'Tanpa prodi') === p).length]));
    const values = [];

    listProdis.forEach((prodi, yIndex) => {
        listCats.forEach((category, xIndex) => {
            const count = records.value.filter((r) => (r.nama_prodi || 'Tanpa prodi') === prodi && recordCategory(r) === category).length;
            const val = props.heatmapMode === 'percentage' ? Number(((count / Math.max(totals[prodi], 1)) * 100).toFixed(1)) : count;
            values.push({
                value: [xIndex, yIndex, val],
                category,
                prodi,
                count,
                itemStyle: { opacity: !props.selectedLabel || category === props.selectedLabel ? 1 : 0.22 },
            });
        });
    });

    const maximum = Math.max(...values.map((v) => v.value[2]), 1);
    return { values, maximum };
});

const heatmapOption = computed(() => {
    const { values, maximum } = heatmapData.value;

    return {
        ...baseOption(records.value.length),
        grid: { left: 12, right: 24, top: 20, bottom: categories.value.length > 5 ? 100 : 64, containLabel: true },
        xAxis: {
            type: 'category',
            data: categories.value,
            axisLabel: { rotate: categories.value.length > 4 ? 26 : 0, color: '#334155', width: 110, overflow: 'truncate' },
            splitArea: { show: false },
        },
        yAxis: {
            type: 'category',
            data: prodis.value,
            axisLabel: { color: '#334155', width: 130, overflow: 'truncate' },
            splitArea: { show: false },
        },
        visualMap: {
            min: 0,
            max: maximum,
            calculable: true,
            orient: 'horizontal',
            left: 'center',
            bottom: 0,
            inRange: { color: ['#F8FAFC', '#BBF7D0', '#4ADE80', '#16A34A', '#0D542B'] },
            text: [props.heatmapMode === 'percentage' ? 'Tinggi (%)' : 'Tinggi', 'Rendah'],
            textStyle: { color: '#475569', fontSize: 11 },
        },
        tooltip: {
            ...baseOption().tooltip,
            formatter: ({ data }) => `<strong>${escapeHtml(data.prodi)}</strong><br>${escapeHtml(data.category)}<br>Jumlah: <strong>${formatNumber(data.count, 0)}</strong>${props.heatmapMode === 'percentage' ? `<br>Proporsi: <strong>${formatNumber(data.value[2])}%</strong>` : ''}`,
        },
        series: [{
            name: props.heatmapMode === 'percentage' ? 'Persentase' : 'Jumlah',
            type: 'heatmap',
            data: values,
            itemStyle: { borderColor: '#FFFFFF', borderWidth: 1 },
            label: {
                show: values.length <= 80,
                fontSize: 10,
                color: (param) => param.data?.value[2] > maximum * 0.45 ? '#FFFFFF' : '#1E293B',
                formatter: ({ value }) => value[2] > 0 ? value[2] : '',
            },
            progressive: 1000,
        }],
    };
});

const heatmapTable = computed(() => {
    const { values } = heatmapData.value;
    return {
        columns: [
            { key: 'prodi', label: 'Program Studi' },
            { key: 'total', label: 'Total Respon', align: 'right' },
            { key: 'topCategory', label: 'Kategori Dominan', align: 'left' },
        ],
        rows: prodis.value.map((prodi) => {
            const prodiValues = values.filter((v) => v.prodi === prodi);
            const total = prodiValues.reduce((sum, v) => sum + v.count, 0);
            const top = [...prodiValues].sort((a, b) => b.count - a.count)[0];
            return {
                prodi,
                total: formatNumber(total, 0),
                topCategory: top && top.count > 0 ? `${top.category} (${top.count})` : '-',
            };
        }),
    };
});
</script>

<template>
    <div class="space-y-6">
        <!-- 1. Populasi Target vs Responden Mengisi per Prodi -->
        <AnalyticsChartCard
            chart-key="grouped"
            title="Populasi dan responden per program studi"
            description="Perbandingan jumlah alumni kohort dengan responden pengisi kuesioner universitas."
            :option="groupedBarOption"
            :table-data="groupedBarTable"
            :rows="records"
            :height="400"
            :selected-label="selectedLabel"
            :loading="loading"
            @select="emit('select', $event)"
            @reset="emit('reset')"
        />

        <!-- 2. Response Rate (%) per Program Studi -->
        <AnalyticsChartCard
            chart-key="rate"
            title="Response rate per program studi"
            description="Urutan capaian persentase partisipasi per program studi."
            :option="rateBarOption"
            :table-data="rateBarTable"
            :rows="records"
            :height="400"
            :selected-label="selectedLabel"
            :loading="loading"
            @select="emit('select', $event)"
            @reset="emit('reset')"
        />

        <!-- 3. Pola Antarprogram Studi (Heatmap) -->
        <AnalyticsChartCard
            chart-key="heatmap"
            title="Pola antarprogram studi"
            :description="heatmapMode === 'percentage'
                ? 'Intensitas dan angka sel menunjukkan persentase kategori dalam setiap program studi.'
                : 'Intensitas dan angka sel menunjukkan jumlah record pada setiap kategori dan program studi.'"
            :option="heatmapOption"
            :table-data="heatmapTable"
            :rows="records"
            :height="Math.max(360, prodis.length * 34 + 140)"
            :selected-label="selectedLabel"
            :loading="loading"
            @select="emit('select', $event)"
            @reset="emit('reset')"
        />
    </div>
</template>
