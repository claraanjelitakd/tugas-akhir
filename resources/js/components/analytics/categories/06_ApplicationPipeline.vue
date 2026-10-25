<script setup>
/**
 * Komponen Indikator 06: Lamaran, Respons, dan Wawancara.
 *
 * Mengelola seluruh visualisasi dan tabel data tahapan lamaran secara mandiri:
 * 1. Line Chart: Pipeline Lamaran per Prodi (Gambar 4.7 laporan resmi: dilamar, respon, wawancara)
 * 2. Bar Chart: Median Tahapan Lamaran Universitas
 * 3. Funnel: Funnel Proses Lamaran
 * 4. Scatter: Hubungan Lamaran dan Respons Individu
 */
import { computed } from 'vue';
import AnalyticsChartCard from '../AnalyticsChartCard.vue';
import {
    analyticsPalette,
    baseOption,
    calculateMedian,
    escapeHtml,
    formatNumber,
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

// Ekstraksi catatan responden dan data seri.
const series = computed(() => props.chart?.series || []);
const records = computed(() => props.chart?.records || []);
const validResponses = computed(() => props.chart?.summary?.answered_count || props.chart?.summary?.response_count || records.value.length);

// -----------------------------------------------------------------------------
// 1. LINE CHART: Tahapan Lamaran per Program Studi (Gambar 4.7)
// -----------------------------------------------------------------------------
const prodiPipelineData = computed(() => {
    const prodis = [...new Set(records.value.map((r) => r.nama_prodi || 'Tanpa prodi'))];
    return prodis.map((prodi) => {
        const prodiRows = records.value.filter((r) => (r.nama_prodi || 'Tanpa prodi') === prodi);
        const f6 = prodiRows.filter((r) => r.kode_pertanyaan === 'F6').map((r) => Number(r.value));
        const f7 = prodiRows.filter((r) => r.kode_pertanyaan === 'F7').map((r) => Number(r.value));
        const f7a = prodiRows.filter((r) => r.kode_pertanyaan === 'F7A' || r.kode_pertanyaan === 'F7a').map((r) => Number(r.value));

        return {
            prodi,
            count: new Set(prodiRows.map((r) => r.biodata_id)).size,
            dilamar: calculateMedian(f6),
            merespon: calculateMedian(f7),
            wawancara: calculateMedian(f7a),
        };
    }).filter((item) => item.count > 0).sort((a, b) => b.count - a.count);
});

const pipelineLineOption = computed(() => {
    const list = prodiPipelineData.value;
    const labels = list.map((item) => `${item.prodi}\n(n=${item.count})`);

    return {
        ...baseOption(records.value.length),
        legend: {
            data: ['Perusahaan Dilamar', 'Merespon', 'Mewawancara'],
            bottom: 0,
            icon: 'circle',
            textStyle: { color: '#334155', fontWeight: 600 },
        },
        grid: { left: 16, right: 30, top: 28, bottom: 58, containLabel: true },
        xAxis: {
            type: 'category',
            data: labels,
            axisLabel: { color: '#334155', fontSize: 11, interval: 0 },
            axisLine: { lineStyle: { color: '#CBD5E1' } },
        },
        yAxis: {
            type: 'value',
            name: 'Median Jumlah Perusahaan',
            axisLabel: { color: '#64748B' },
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
        },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'axis',
            formatter: (params) => {
                const item = list[params[0]?.dataIndex ?? 0] || {};
                return `<strong>${escapeHtml(item.prodi)}</strong> (n=${item.count})<br>Dilamar: <strong>${formatNumber(item.dilamar, 1)}</strong><br>Merespon: <strong>${formatNumber(item.merespon, 1)}</strong><br>Mewawancara: <strong>${formatNumber(item.wawancara, 1)}</strong>`;
            },
        },
        series: [
            {
                name: 'Perusahaan Dilamar',
                type: 'line',
                data: list.map((g) => g.dilamar),
                itemStyle: { color: '#2563EB' },
                lineStyle: { width: 3, color: '#2563EB' },
                symbol: 'circle',
                symbolSize: 8,
                emphasis: { scale: 1.6 },
                label: { show: true, position: 'top', formatter: ({ value }) => formatNumber(value, 1) },
            },
            {
                name: 'Merespon',
                type: 'line',
                data: list.map((g) => g.merespon),
                itemStyle: { color: '#DC2626' },
                lineStyle: { width: 3, color: '#DC2626' },
                symbol: 'rect',
                symbolSize: 8,
                emphasis: { scale: 1.6 },
                label: { show: true, position: 'bottom', formatter: ({ value }) => formatNumber(value, 1) },
            },
            {
                name: 'Mewawancara',
                type: 'line',
                data: list.map((g) => g.wawancara),
                itemStyle: { color: '#0D542B' },
                lineStyle: { width: 3, color: '#0D542B' },
                symbol: 'triangle',
                symbolSize: 8,
                emphasis: { scale: 1.6 },
                label: { show: true, position: 'right', formatter: ({ value }) => formatNumber(value, 1) },
            },
        ],
    };
});

const pipelineLineTable = computed(() => ({
    columns: [
        { key: 'prodi', label: 'Program Studi' },
        { key: 'dilamar', label: 'Dilamar (Median)', align: 'right' },
        { key: 'merespon', label: 'Merespon (Median)', align: 'right' },
        { key: 'wawancara', label: 'Wawancara (Median)', align: 'right' },
        { key: 'count', label: 'Responden (n)', align: 'right' },
    ],
    rows: prodiPipelineData.value.map((item) => ({
        prodi: item.prodi,
        dilamar: formatNumber(item.dilamar, 1),
        merespon: formatNumber(item.merespon, 1),
        wawancara: formatNumber(item.wawancara, 1),
        count: formatNumber(item.count, 0),
    })),
}));

// -----------------------------------------------------------------------------
// 2. BAR CHART: Median Tahapan Lamaran Universitas
// -----------------------------------------------------------------------------
const stagesBarOption = computed(() => {
    const list = series.value.map((item) => ({
        name: seriesLabel(item),
        value: Number(item.median || 0),
        count: item.count,
        itemStyle: { color: '#0D542B' },
    }));

    return {
        ...baseOption(validResponses.value),
        grid: { left: 16, right: 38, top: 14, bottom: 26, containLabel: true },
        xAxis: { type: 'value', name: 'Median', splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } } },
        yAxis: { type: 'category', inverse: true, data: list.map((i) => i.name), axisLabel: { color: '#334155' } },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            formatter: (params) => {
                const pt = params[0]?.data || {};
                return `<strong>${escapeHtml(pt.name)}</strong><br>Median: <strong>${formatNumber(pt.value, 1)}</strong><br>Jawaban valid: ${formatNumber(pt.count, 0)}`;
            },
        },
        series: [{
            name: 'Median',
            type: 'bar',
            barMaxWidth: 26,
            data: list,
            label: { show: true, position: 'right', fontWeight: 600, formatter: ({ value }) => formatNumber(value, 1) },
        }],
    };
});

const stagesBarTable = computed(() => ({
    columns: [
        { key: 'name', label: 'Tahapan Proses' },
        { key: 'medianDisplay', label: 'Median Perusahaan', align: 'right' },
        { key: 'countDisplay', label: 'Responden Valid', align: 'right' },
    ],
    rows: series.value.map((item) => ({
        name: seriesLabel(item),
        medianDisplay: formatNumber(item.median, 1),
        countDisplay: formatNumber(item.count, 0),
    })),
}));

// -----------------------------------------------------------------------------
// 3. FUNNEL: Funnel Proses Lamaran
// -----------------------------------------------------------------------------
const funnelOption = computed(() => ({
    ...baseOption(validResponses.value),
    tooltip: { ...baseOption().tooltip, formatter: ({ data }) => `<strong>${escapeHtml(data.name)}</strong><br>Median: <strong>${formatNumber(data.value)}</strong><br>Responden: ${formatNumber(data.count, 0)}` },
    series: [{
        name: 'Tahap lamaran',
        type: 'funnel',
        left: '10%',
        width: '80%',
        top: 20,
        bottom: 24,
        minSize: '16%',
        maxSize: '100%',
        sort: 'descending',
        gap: 4,
        label: { show: true, position: 'inside', formatter: '{b}: {c}', fontWeight: 700 },
        emphasis: { focus: 'self' },
        data: series.value.map((item, index) => ({ name: seriesLabel(item), value: Number(item.median || 0), count: item.count, itemStyle: { color: analyticsPalette[index] } })),
    }],
}));

const funnelTable = computed(() => stagesBarTable.value);

// -----------------------------------------------------------------------------
// 4. SCATTER: Hubungan Lamaran dan Respons Individu
// -----------------------------------------------------------------------------
const scatterData = computed(() => {
    const grouped = new Map();
    records.value.forEach((r) => {
        const cur = grouped.get(r.biodata_id) || { nama: r.nama, prodi: r.nama_prodi };
        cur[r.kode_pertanyaan] = Number(r.value);
        grouped.set(r.biodata_id, cur);
    });
    return [...grouped.values()].filter((item) => Number.isFinite(item.F6) && Number.isFinite(item.F7)).map((item) => ({ name: item.nama, prodi: item.prodi, value: [item.F6, item.F7] }));
});

const scatterOption = computed(() => ({
    ...baseOption(scatterData.value.length),
    grid: { left: 16, right: 24, top: 18, bottom: 48, containLabel: true },
    xAxis: { type: 'value', name: 'Lamaran', nameLocation: 'middle', nameGap: 26, splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } } },
    yAxis: { type: 'value', name: 'Respons', splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } } },
    tooltip: { ...baseOption().tooltip, formatter: ({ data: point }) => `<strong>${escapeHtml(point.name)}</strong><br>${escapeHtml(point.prodi)}<br>Lamaran: ${formatNumber(point.value[0], 0)}<br>Respons: ${formatNumber(point.value[1], 0)}` },
    series: [{ name: 'Alumni', type: 'scatter', symbolSize: 9, data: scatterData.value, itemStyle: { color: '#2563EB', opacity: 0.72 } }],
}));

const scatterTable = computed(() => ({
    columns: [
        { key: 'name', label: 'Nama Alumni' },
        { key: 'prodi', label: 'Program Studi' },
        { key: 'applied', label: 'Dilamar', align: 'right' },
        { key: 'responded', label: 'Merespon', align: 'right' },
    ],
    rows: scatterData.value.slice(0, 15).map((item) => ({
        name: item.name,
        prodi: item.prodi,
        applied: formatNumber(item.value[0], 0),
        responded: formatNumber(item.value[1], 0),
    })),
}));
</script>

<template>
    <div class="space-y-6">
        <!-- 1. Pipeline Lamaran per Program Studi (Gambar 4.7) -->
        <AnalyticsChartCard
            chart-key="pipeline_line"
            title="Jumlah perusahaan dilamar, merespon, dan wawancara per program studi"
            description="Perbandingan median tiga tahapan lamaran per prodi (mengadopsi Gambar 4.7 Laporan Tracer Study)."
            :option="pipelineLineOption"
            :table-data="pipelineLineTable"
            :rows="records"
            :height="410"
            :selected-label="selectedLabel"
            :loading="loading"
            @select="emit('select', $event)"
            @reset="emit('reset')"
        />

        <!-- 2. Median Tahapan Lamaran Universitas -->
        <AnalyticsChartCard
            chart-key="stages"
            title="Median tahapan lamaran universitas"
            description="Perbandingan median lamaran, respons, dan wawancara universitas."
            :option="stagesBarOption"
            :table-data="stagesBarTable"
            :rows="records"
            :height="330"
            :selected-label="selectedLabel"
            :loading="loading"
            @select="emit('select', $event)"
            @reset="emit('reset')"
        />

        <!-- 3. Funnel Proses Lamaran -->
        <AnalyticsChartCard
            chart-key="funnel"
            title="Funnel proses lamaran"
            description="Penyusutan dari jumlah lamaran menuju panggilan wawancara."
            :option="funnelOption"
            :table-data="funnelTable"
            :rows="records"
            :height="380"
            :selected-label="selectedLabel"
            :loading="loading"
            @select="emit('select', $event)"
            @reset="emit('reset')"
        />

        <!-- 4. Hubungan Lamaran dan Respons Individu -->
        <AnalyticsChartCard
            chart-key="scatter"
            title="Hubungan lamaran dan respons individu"
            description="Setiap titik adalah alumni dengan pasangan jawaban F6 dan F7."
            :option="scatterOption"
            :table-data="scatterTable"
            :rows="records"
            :height="390"
            :selected-label="selectedLabel"
            :loading="loading"
            @select="emit('select', $event)"
            @reset="emit('reset')"
        />
    </div>
</template>
