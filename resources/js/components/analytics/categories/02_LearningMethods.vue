<script setup>
/**
 * Komponen Indikator 02: Penekanan Metode Pembelajaran.
 *
 * Mengelola visualisasi dan tabel data secara mandiri di dalam berkas ini:
 * 1. Line Chart: Penekanan Metode Belajar Median (Gambar 4.3 laporan resmi)
 * 2. Bar Chart: Perbandingan Median Metode dengan 1 warna solid terurut
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

// Ekstraksi data seri dan catatan responden valid.
const series = computed(() => props.chart?.series || []);
const records = computed(() => props.chart?.records || []);
const validResponses = computed(() => props.chart?.summary?.answered_count || records.value.length);

// -----------------------------------------------------------------------------
// 1. LINE CHART: Penekanan Metode Belajar Median (Gambar 4.3)
// -----------------------------------------------------------------------------
const methodsLineOption = computed(() => {
    const labels = series.value.map(seriesLabel);
    const scores = series.value.map((item) => Number(item.score || 0));

    return {
        ...baseOption(validResponses.value),
        grid: { left: 16, right: 28, top: 28, bottom: 44, containLabel: true },
        xAxis: {
            type: 'category',
            data: labels,
            axisLabel: { rotate: labels.length > 5 ? 24 : 0, color: '#334155', width: 115, overflow: 'truncate' },
            axisLine: { lineStyle: { color: '#CBD5E1' } },
        },
        yAxis: {
            type: 'value',
            min: 0,
            max: 5,
            interval: 1,
            name: 'Median Skor (Skala 1 - 5)',
            axisLabel: { color: '#64748B' },
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
        },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'axis',
            formatter: (params) => {
                const point = params[0] || {};
                return `<strong>${escapeHtml(point.name)}</strong><br>Median skor: <strong>${formatNumber(point.value, 1)}</strong><br>N valid: ${formatNumber(validResponses.value, 0)}`;
            },
        },
        series: [{
            name: 'Median skor',
            type: 'line',
            data: scores,
            itemStyle: { color: '#0D542B' },
            lineStyle: { width: 3, color: '#0D542B' },
            symbol: 'circle',
            symbolSize: 8,
            emphasis: { scale: 1.6 },
            label: { show: true, position: 'top', color: '#0D542B', fontWeight: 700, formatter: ({ value }) => formatNumber(value, 1) },
        }],
    };
});

const methodsLineTable = computed(() => ({
    columns: [
        { key: 'name', label: 'Metode Pembelajaran' },
        { key: 'score', label: 'Median Skor (1-5)', align: 'right' },
        { key: 'count', label: 'Responden (N)', align: 'right' },
    ],
    rows: series.value.map((item) => ({
        name: seriesLabel(item),
        score: formatNumber(item.score, 1),
        count: formatNumber(item.count || validResponses.value, 0),
    })),
}));

// -----------------------------------------------------------------------------
// 2. BAR CHART: Perbandingan Median Metode (1 Warna Solid Terurut)
// -----------------------------------------------------------------------------
const sortedMethods = computed(() => {
    return [...series.value]
        .map((item) => {
            const label = seriesLabel(item);
            const val = Number(item.score || 0);
            const selected = !props.selectedLabel || props.selectedLabel === label;
            return {
                name: label,
                value: val,
                itemStyle: { color: '#0D542B', opacity: selected ? 1 : 0.22 },
            };
        })
        .sort((a, b) => b.value - a.value);
});

const methodsBarOption = computed(() => {
    const rows = sortedMethods.value;

    return {
        ...baseOption(validResponses.value),
        grid: { left: 12, right: 38, top: 12, bottom: rows.length > 8 ? 44 : 24, containLabel: true },
        xAxis: {
            type: 'value',
            name: 'Median skor',
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
                return `<strong>${escapeHtml(point.name)}</strong><br>Median skor: <strong>${formatNumber(point.value, 1)}</strong><br>N valid: ${formatNumber(validResponses.value, 0)}`;
            },
        },
        series: [{
            name: 'Median skor',
            type: 'bar',
            barMaxWidth: 26,
            emphasis: { focus: 'self', itemStyle: { opacity: 1 } },
            label: { show: true, position: 'right', color: '#334155', fontWeight: 600, formatter: ({ value }) => formatNumber(value, 1) },
            data: rows,
        }],
    };
});

const methodsBarTable = computed(() => ({
    columns: [
        { key: 'name', label: 'Metode Pembelajaran' },
        { key: 'score', label: 'Median Skor', align: 'right' },
    ],
    rows: sortedMethods.value.map((item) => ({
        name: item.name,
        score: formatNumber(item.value, 1),
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
            { key: 'topCategory', label: 'Metode Dominan', align: 'left' },
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
        <!-- 1. Penekanan Metode Pembelajaran (Median) -->
        <AnalyticsChartCard
            chart-key="methods_line"
            title="Penekanan metode pembelajaran (median)"
            description="Tren nilai median penekanan metode belajar (mengadopsi Gambar 4.3 Laporan Tracer Study)."
            :option="methodsLineOption"
            :table-data="methodsLineTable"
            :rows="records"
            :height="380"
            :selected-label="selectedLabel"
            :loading="loading"
            @select="emit('select', $event)"
            @reset="emit('reset')"
        />

        <!-- 2. Perbandingan Median Metode (1 Warna Solid Terurut) -->
        <AnalyticsChartCard
            chart-key="likert"
            title="Perbandingan median metode"
            description="Metode diurutkan dari skor paling ditekankan ke paling kurang ditekankan dengan 1 warna konsisten."
            :option="methodsBarOption"
            :table-data="methodsBarTable"
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
