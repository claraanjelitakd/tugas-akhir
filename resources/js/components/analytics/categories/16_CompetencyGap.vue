<script setup>
/**
 * Komponen Indikator 16: Gap Kompetensi Lulusan.
 *
 * Mengelola seluruh visualisasi dan data tabel secara mandiri di dalam berkas ini:
 * 1. Line Chart: Perbandingan Kompetensi Saat Lulus vs Dibutuhkan (Gambar 4.17 laporan resmi)
 * 2. Dumbbell Chart: Rentang Gap Kompetensi per Dimensi
 * 3. Radar Chart: Profil Jaring Laba-laba Kompetensi
 * 4. Heatmap: Pola Antarprogram Studi
 */
import { computed } from 'vue';
import AnalyticsChartCard from '../AnalyticsChartCard.vue';
import {
    analyticsPalette,
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
// 1. LINE CHART: Perbandingan Kompetensi Saat Lulus vs Dibutuhkan (Means)
// -----------------------------------------------------------------------------
const competencyLineOption = computed(() => {
    const labels = series.value.map(seriesLabel);
    const gradScores = series.value.map((item) => Number(item.graduation_score || 0));
    const needScores = series.value.map((item) => Number(item.need_score || 0));

    return {
        ...baseOption(validResponses.value),
        legend: {
            data: ['Kompetensi Saat Lulus', 'Kompetensi Dibutuhkan'],
            bottom: 0,
            icon: 'circle',
            textStyle: { color: '#334155', fontWeight: 600 },
        },
        grid: { left: 16, right: 34, top: 28, bottom: 48, containLabel: true },
        xAxis: {
            type: 'category',
            data: labels,
            axisLabel: { rotate: labels.length > 5 ? 24 : 0, color: '#334155', width: 110, overflow: 'truncate' },
            axisLine: { lineStyle: { color: '#CBD5E1' } },
        },
        yAxis: {
            type: 'value',
            min: 1,
            max: 5,
            interval: 0.5,
            name: 'Skor Likert (1 - 5)',
            axisLabel: { color: '#64748B' },
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
        },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'axis',
            formatter: (params) => {
                const titleText = escapeHtml(params[0]?.name || '');
                const grad = Number(params.find((p) => p.seriesName === 'Kompetensi Saat Lulus')?.value || 0);
                const need = Number(params.find((p) => p.seriesName === 'Kompetensi Dibutuhkan')?.value || 0);
                const gap = Number((need - grad).toFixed(2));
                const gapNote = gap > 0
                    ? `<span style="color:#F59E0B">Gap deficit: +${formatNumber(gap, 2)} (perlu ditingkatkan)</span>`
                    : `<span style="color:#10B981">Gap surplus: ${formatNumber(gap, 2)} (terpenuhi)</span>`;
                return `<strong>${titleText}</strong><br>Saat lulus: <strong>${formatNumber(grad, 2)}</strong><br>Dibutuhkan: <strong>${formatNumber(need, 2)}</strong><br>${gapNote}<br>N valid: ${formatNumber(validResponses.value, 0)}`;
            },
        },
        series: [
            {
                name: 'Kompetensi Saat Lulus',
                type: 'line',
                data: gradScores,
                itemStyle: { color: '#2563EB' },
                lineStyle: { width: 3, color: '#2563EB' },
                symbol: 'circle',
                symbolSize: 8,
                emphasis: { scale: 1.6 },
                label: { show: true, position: 'top', color: '#1D4ED8', fontWeight: 600, formatter: ({ value }) => formatNumber(value, 2) },
            },
            {
                name: 'Kompetensi Dibutuhkan',
                type: 'line',
                data: needScores,
                itemStyle: { color: '#0D542B' },
                lineStyle: { width: 3, color: '#0D542B' },
                symbol: 'rect',
                symbolSize: 8,
                emphasis: { scale: 1.6 },
                label: { show: true, position: 'bottom', color: '#0A4322', fontWeight: 600, formatter: ({ value }) => formatNumber(value, 2) },
            },
        ],
    };
});

const competencyLineTable = computed(() => ({
    columns: [
        { key: 'name', label: 'Dimensi Kompetensi' },
        { key: 'graduation', label: 'Saat Lulus', align: 'right' },
        { key: 'need', label: 'Dibutuhkan', align: 'right' },
        { key: 'gap', label: 'Gap (Selisih)', align: 'right' },
        { key: 'status', label: 'Status Evaluasi', align: 'center' },
    ],
    rows: series.value.map((item) => {
        const grad = Number(item.graduation_score || 0);
        const need = Number(item.need_score || 0);
        const gap = Number((need - grad).toFixed(2));
        return {
            name: seriesLabel(item),
            graduation: formatNumber(grad, 2),
            need: formatNumber(need, 2),
            gap: gap > 0 ? `+${formatNumber(gap, 2)}` : formatNumber(gap, 2),
            status: gap > 0 ? 'Perlu Ditingkatkan' : 'Terpenuhi',
            isDeficit: gap > 0,
        };
    }),
}));

// -----------------------------------------------------------------------------
// 2. DUMBBELL CHART: Rentang Gap Kompetensi
// -----------------------------------------------------------------------------
const sortedDumbbell = computed(() => [...series.value].sort((left, right) => Number(right.gap || 0) - Number(left.gap || 0)));

const dumbbellOption = computed(() => {
    const list = sortedDumbbell.value;

    return {
        ...baseOption(validResponses.value),
        grid: { left: 16, right: 32, top: 20, bottom: 30, containLabel: true },
        xAxis: { type: 'value', min: 0, max: 5, interval: 1, splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } } },
        yAxis: { type: 'category', inverse: true, data: list.map(seriesLabel), axisLabel: { width: 160, overflow: 'truncate' } },
        tooltip: {
            ...baseOption().tooltip,
            formatter: ({ data }) => `<strong>${escapeHtml(data.name)}</strong><br>Saat lulus: <strong>${formatNumber(data.value[0], 2)}</strong><br>Dibutuhkan: <strong>${formatNumber(data.value[1], 2)}</strong><br>Gap: <strong>${formatNumber(data.value[1] - data.value[0], 2)}</strong>`,
        },
        series: [{
            name: 'Gap kompetensi',
            type: 'custom',
            renderItem: (params, api) => {
                const graduation = api.coord([api.value(0), api.value(2)]);
                const need = api.coord([api.value(1), api.value(2)]);
                return {
                    type: 'group',
                    children: [
                        { type: 'line', shape: { x1: graduation[0], y1: graduation[1], x2: need[0], y2: need[1] }, style: { stroke: '#94A3B8', lineWidth: 4 } },
                        { type: 'circle', shape: { cx: graduation[0], cy: graduation[1], r: 7 }, style: { fill: '#2563EB' } },
                        { type: 'circle', shape: { cx: need[0], cy: need[1], r: 7 }, style: { fill: '#0D542B' } },
                    ],
                };
            },
            encode: { x: [0, 1], y: 2 },
            data: list.map((item, index) => ({ name: seriesLabel(item), value: [Number(item.graduation_score || 0), Number(item.need_score || 0), index] })),
        }],
    };
});

const dumbbellTable = computed(() => ({
    columns: [
        { key: 'name', label: 'Dimensi' },
        { key: 'grad', label: 'Saat Lulus', align: 'right' },
        { key: 'need', label: 'Dibutuhkan', align: 'right' },
        { key: 'gap', label: 'Gap', align: 'right' },
    ],
    rows: sortedDumbbell.value.map((item) => ({
        name: seriesLabel(item),
        grad: formatNumber(item.graduation_score, 2),
        need: formatNumber(item.need_score, 2),
        gap: formatNumber(Number(item.need_score || 0) - Number(item.graduation_score || 0), 2),
    })),
}));

// -----------------------------------------------------------------------------
// 3. RADAR CHART: Profil Radar Kompetensi
// -----------------------------------------------------------------------------
const radarOption = computed(() => ({
    ...baseOption(validResponses.value),
    legend: { data: ['Saat lulus', 'Dibutuhkan'], bottom: 0 },
    radar: {
        center: ['50%', '47%'],
        radius: '66%',
        indicator: series.value.map((item) => ({ name: seriesLabel(item), max: 5 })),
        axisName: { color: '#334155', fontSize: 11 },
        splitArea: { areaStyle: { color: ['#FFFFFF', '#F8FAFC'] } },
        splitLine: { lineStyle: { color: '#CBD5E1' } },
    },
    tooltip: { ...baseOption().tooltip, trigger: 'item' },
    series: [{
        type: 'radar',
        data: [
            { name: 'Saat lulus', value: series.value.map((item) => Number(item.graduation_score || 0)), areaStyle: { color: 'rgba(37,99,235,.15)' }, lineStyle: { color: '#2563EB', width: 2 }, itemStyle: { color: '#2563EB' } },
            { name: 'Dibutuhkan', value: series.value.map((item) => Number(item.need_score || 0)), areaStyle: { color: 'rgba(13,84,43,.15)' }, lineStyle: { color: '#0D542B', width: 2 }, itemStyle: { color: '#0D542B' } },
        ],
    }],
}));

const radarTable = computed(() => ({
    columns: [
        { key: 'name', label: 'Dimensi Kompetensi' },
        { key: 'grad', label: 'Saat Lulus', align: 'right' },
        { key: 'need', label: 'Dibutuhkan', align: 'right' },
    ],
    rows: series.value.map((item) => ({
        name: seriesLabel(item),
        grad: formatNumber(item.graduation_score, 2),
        need: formatNumber(item.need_score, 2),
    })),
}));

// -----------------------------------------------------------------------------
// 4. HEATMAP: Pola Antarprogram Studi
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
            { key: 'topCategory', label: 'Dimensi Tertinggi', align: 'left' },
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
        <!-- 1. Perbandingan Kompetensi Saat Lulus dan Dibutuhkan (Means) -->
        <AnalyticsChartCard
            chart-key="competency_line"
            title="Perbandingan kompetensi saat lulus dan yang diperlukan (Means)"
            description="Grafik garis perbandingan skor penguasaan saat lulus vs kebutuhan pekerjaan (mengadopsi Gambar 4.17 Laporan Tracer Study)."
            :option="competencyLineOption"
            :table-data="competencyLineTable"
            :rows="records"
            :height="420"
            :selected-label="selectedLabel"
            :loading="loading"
            @select="emit('select', $event)"
            @reset="emit('reset')"
        />

        <!-- 2. Rentang Gap Kompetensi (Dumbbell) -->
        <AnalyticsChartCard
            chart-key="dumbbell"
            title="Rentang gap kompetensi"
            description="Dua titik dan garis rentang memperlihatkan selisih kompetensi pada setiap dimensi."
            :option="dumbbellOption"
            :table-data="dumbbellTable"
            :rows="records"
            :height="Math.max(360, series.length * 42 + 90)"
            :selected-label="selectedLabel"
            :loading="loading"
            @select="emit('select', $event)"
            @reset="emit('reset')"
        />

        <!-- 3. Profil Radar Kompetensi -->
        <AnalyticsChartCard
            chart-key="radar"
            title="Profil radar kompetensi"
            description="Visualisasi jaring laba-laba untuk perbandingan dimensi pada skala 1-5."
            :option="radarOption"
            :table-data="radarTable"
            :rows="records"
            :height="420"
            :selected-label="selectedLabel"
            :loading="loading"
            @select="emit('select', $event)"
            @reset="emit('reset')"
        />

        <!-- 4. Pola Skor per Program Studi (Heatmap) -->
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
