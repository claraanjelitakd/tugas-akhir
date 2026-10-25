<script setup>
/**
 * Komponen Indikator 03: Waktu Mulai Mencari Pekerjaan.
 *
 * Mengelola seluruh visualisasi dan tabel data waktu mulai mencari kerja secara mandiri:
 * 1. Diverging Bar: Posisi Waktu terhadap Kelulusan (Sebelum vs Sesudah Lulus)
 * 2. Bar Chart: Distribusi Frekuensi Kategori Waktu Mulai
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
    seriesMetric,
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
const validResponses = computed(() => props.chart?.summary?.answered_count || records.value.length);

// -----------------------------------------------------------------------------
// 1. DIVERGING BAR: Posisi Waktu terhadap Kelulusan
// -----------------------------------------------------------------------------
const divergingData = computed(() => {
    return series.value.map((item) => {
        const label = seriesLabel(item);
        const isBefore = label.toLowerCase().includes('sebelum');
        const count = seriesMetric(item);
        const val = count * (isBefore ? -1 : 1);
        const selected = !props.selectedLabel || props.selectedLabel === label;
        return {
            name: label,
            value: val,
            absoluteValue: count,
            itemStyle: { color: isBefore ? '#2563EB' : '#0D542B', opacity: selected ? 1 : 0.22 },
        };
    });
});

const divergingOption = computed(() => ({
    ...baseOption(validResponses.value),
    grid: { left: 18, right: 18, top: 22, bottom: 44, containLabel: true },
    xAxis: { type: 'value', axisLabel: { formatter: (value) => Math.abs(value) }, splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } } },
    yAxis: { type: 'category', data: divergingData.value.map((i) => i.name), axisLabel: { width: 170, overflow: 'truncate' } },
    tooltip: { ...baseOption().tooltip, formatter: ({ data }) => `<strong>${escapeHtml(data.name)}</strong><br>Jumlah: <strong>${formatNumber(data.absoluteValue, 0)} alumni</strong><br>${data.value < 0 ? 'Sebelum kelulusan' : 'Setelah atau saat kelulusan'}` },
    series: [{ name: 'Alumni', type: 'bar', data: divergingData.value, label: { show: true, formatter: ({ data }) => formatNumber(data.absoluteValue, 0), position: 'inside' } }],
}));

const divergingTable = computed(() => ({
    columns: [
        { key: 'name', label: 'Kategori Waktu' },
        { key: 'timing', label: 'Posisi Kelulusan' },
        { key: 'count', label: 'Jumlah Alumni', align: 'right' },
    ],
    rows: divergingData.value.map((item) => ({
        name: item.name,
        timing: item.value < 0 ? 'Sebelum Lulus' : 'Setelah Lulus',
        count: formatNumber(item.absoluteValue, 0),
    })),
}));

// -----------------------------------------------------------------------------
// 2. BAR CHART: Distribusi Kategori Waktu Mulai
// -----------------------------------------------------------------------------
const sortedCategories = computed(() => {
    return [...series.value]
        .map((item) => {
            const label = seriesLabel(item);
            const val = seriesMetric(item);
            const selected = !props.selectedLabel || props.selectedLabel === label;
            return {
                name: label,
                value: val,
                itemStyle: { color: '#0D542B', opacity: selected ? 1 : 0.22 },
            };
        })
        .sort((a, b) => b.value - a.value);
});

const distributionBarOption = computed(() => {
    const rows = sortedCategories.value;
    return {
        ...baseOption(validResponses.value),
        grid: { left: 12, right: 38, top: 12, bottom: 26, containLabel: true },
        xAxis: { type: 'value', name: 'Jumlah alumni', splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } } },
        yAxis: { type: 'category', inverse: true, data: rows.map((i) => i.name), axisLabel: { width: 170, overflow: 'truncate' } },
        tooltip: { ...baseOption().tooltip, trigger: 'axis', axisPointer: { type: 'shadow' } },
        series: [{
            name: 'Jumlah',
            type: 'bar',
            barMaxWidth: 26,
            data: rows,
            label: { show: true, position: 'right', formatter: ({ value }) => formatNumber(value, 0) },
        }],
    };
});

const distributionBarTable = computed(() => ({
    columns: [
        { key: 'name', label: 'Kategori Waktu Mulai' },
        { key: 'count', label: 'Jumlah Alumni', align: 'right' },
    ],
    rows: sortedCategories.value.map((item) => ({
        name: item.name,
        count: formatNumber(item.value, 0),
    })),
}));

// -----------------------------------------------------------------------------
// 3. HEATMAP: Pola Antarprogram Studi
// -----------------------------------------------------------------------------
const prodis = computed(() => [...new Set(records.value.map((r) => r.nama_prodi || 'Tanpa prodi'))]);
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
        xAxis: { type: 'category', data: categories.value, axisLabel: { rotate: categories.value.length > 4 ? 26 : 0, width: 110, overflow: 'truncate' } },
        yAxis: { type: 'category', data: prodis.value, axisLabel: { width: 130, overflow: 'truncate' } },
        visualMap: { min: 0, max: maximum, calculable: true, orient: 'horizontal', left: 'center', bottom: 0, inRange: { color: ['#F8FAFC', '#BBF7D0', '#4ADE80', '#16A34A', '#0D542B'] } },
        tooltip: { ...baseOption().tooltip, formatter: ({ data }) => `<strong>${escapeHtml(data.prodi)}</strong><br>${escapeHtml(data.category)}<br>Jumlah: <strong>${formatNumber(data.count, 0)}</strong>` },
        series: [{
            name: props.heatmapMode === 'percentage' ? 'Persentase' : 'Jumlah',
            type: 'heatmap',
            data: values,
            itemStyle: { borderColor: '#FFFFFF', borderWidth: 1 },
            label: { show: values.length <= 80, fontSize: 10, formatter: ({ value }) => value[2] > 0 ? value[2] : '' },
        }],
    };
});

const heatmapTable = computed(() => ({
    columns: [
        { key: 'prodi', label: 'Program Studi' },
        { key: 'total', label: 'Total Respon', align: 'right' },
    ],
    rows: prodis.value.map((prodi) => ({
        prodi,
        total: formatNumber(records.value.filter((r) => (r.nama_prodi || 'Tanpa prodi') === prodi).length, 0),
    })),
}));
</script>

<template>
    <div class="space-y-6">
        <!-- 1. Posisi Waktu terhadap Kelulusan (Diverging Bar) -->
        <AnalyticsChartCard
            chart-key="diverging"
            title="Posisi waktu terhadap kelulusan"
            description="Kategori sebelum lulus ditempatkan di kiri dan setelah lulus di kanan."
            :option="divergingOption"
            :table-data="divergingTable"
            :rows="records"
            :height="360"
            :selected-label="selectedLabel"
            :loading="loading"
            @select="emit('select', $event)"
            @reset="emit('reset')"
        />

        <!-- 2. Distribusi Kategori Waktu Mulai -->
        <AnalyticsChartCard
            chart-key="distribution"
            title="Distribusi kategori waktu mulai"
            description="Urutan kategori waktu mulai mencari pekerjaan dari frekuensi terbanyak."
            :option="distributionBarOption"
            :table-data="distributionBarTable"
            :rows="records"
            :height="360"
            :selected-label="selectedLabel"
            :loading="loading"
            @select="emit('select', $event)"
            @reset="emit('reset')"
        />

        <!-- 3. Pola Antarprogram Studi (Heatmap) -->
        <AnalyticsChartCard
            chart-key="heatmap"
            title="Pola antarprogram studi"
            :description="heatmapMode === 'percentage' ? 'Persentase kategori dalam setiap program studi.' : 'Jumlah record pada setiap kategori dan program studi.'"
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
