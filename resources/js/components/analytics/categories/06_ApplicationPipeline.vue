<script setup>
/**
 * Komponen Indikator 06: Lamaran, Respons, dan Wawancara (F6, F7, F7a).
 *
 * Mengelola visualisasi tahapan lamaran kerja alumni:
 * 1. Line Chart: Pipeline Lamaran per Prodi (Gambar 4.7 Laporan Resmi UKDW)
 * 2. Bar Chart: Median Tahapan Lamaran Tingkat Universitas
 */
import { computed } from 'vue';
import AnalyticsTabbedContainer from '../AnalyticsTabbedContainer.vue';
import {
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
// 1. LINE CHART: Tahapan Lamaran per Program Studi (Gambar 4.7 Laporan Resmi)
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
            textStyle: { color: '#334155', fontWeight: 600, fontSize: 12 },
        },
        grid: { left: 24, right: 36, top: 32, bottom: 80, containLabel: true },
        xAxis: {
            type: 'category',
            data: labels,
            axisLabel: {
                color: '#334155',
                fontSize: 11,
                interval: 0,
                rotate: 30,
                lineHeight: 14,
            },
            axisLine: { lineStyle: { color: '#CBD5E1' } },
        },
        yAxis: {
            type: 'value',
            name: 'Median Jumlah Perusahaan',
            nameLocation: 'end',
            axisLabel: { color: '#64748B' },
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
        },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'axis',
            formatter: (params) => {
                const item = list[params[0]?.dataIndex ?? 0] || {};
                return `<strong>${escapeHtml(item.prodi)}</strong> (n=${item.count})<br>Median perusahaan dilamar: <strong>${formatNumber(item.dilamar, 0)}</strong><br>Median perusahaan merespon: <strong>${formatNumber(item.merespon, 0)}</strong><br>Median panggilan wawancara: <strong>${formatNumber(item.wawancara, 0)}</strong>`;
            },
        },
        series: [
            {
                name: 'Perusahaan Dilamar',
                type: 'line',
                data: list.map((g) => g.dilamar),
                itemStyle: { color: '#334155' },
                lineStyle: { width: 3, color: '#334155' },
                symbol: 'circle',
                symbolSize: 8,
                emphasis: { scale: 1.5 },
                label: { show: true, position: 'top', formatter: ({ value }) => formatNumber(value, 0), fontSize: 11, fontWeight: 'bold' },
            },
            {
                name: 'Merespon',
                type: 'line',
                data: list.map((g) => g.merespon),
                itemStyle: { color: '#78B18B' },
                lineStyle: { width: 3, color: '#78B18B' },
                symbol: 'rect',
                symbolSize: 8,
                emphasis: { scale: 1.5 },
                label: { show: true, position: 'bottom', formatter: ({ value }) => formatNumber(value, 0), fontSize: 11, fontWeight: 'bold' },
            },
            {
                name: 'Mewawancara',
                type: 'line',
                data: list.map((g) => g.wawancara),
                itemStyle: { color: '#0D542B' },
                lineStyle: { width: 3, color: '#0D542B' },
                symbol: 'triangle',
                symbolSize: 8,
                emphasis: { scale: 1.5 },
                label: { show: true, position: 'right', formatter: ({ value }) => formatNumber(value, 0), fontSize: 11, fontWeight: 'bold' },
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
        dilamar: formatNumber(item.dilamar, 0),
        merespon: formatNumber(item.merespon, 0),
        wawancara: formatNumber(item.wawancara, 0),
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
        itemStyle: { color: '#0D542B', borderRadius: [0, 4, 4, 0] },
    }));

    return {
        ...baseOption(validResponses.value),
        grid: { left: 24, right: 48, top: 20, bottom: 30, containLabel: true },
        xAxis: { type: 'value', name: 'Median Jumlah Perusahaan', splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } } },
        yAxis: {
            type: 'category',
            inverse: true,
            data: list.map((i) => i.name),
            axisLabel: { color: '#334155', fontWeight: 600, fontSize: 12 },
        },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            formatter: (params) => {
                const pt = params[0]?.data || {};
                return `<strong>${escapeHtml(pt.name)}</strong><br>Median: <strong>${formatNumber(pt.value, 0)} perusahaan</strong><br>Respon valid: ${formatNumber(pt.count, 0)} alumni`;
            },
        },
        series: [{
            name: 'Median',
            type: 'bar',
            barMaxWidth: 32,
            data: list,
            label: {
                show: true,
                position: 'right',
                fontWeight: 'bold',
                color: '#0D542B',
                formatter: ({ value }) => `${formatNumber(value, 0)} perusahaan`,
            },
        }],
    };
});

const stagesBarTable = computed(() => ({
    columns: [
        { key: 'name', label: 'Tahapan Proses Lamaran' },
        { key: 'medianDisplay', label: 'Median Perusahaan', align: 'right' },
        { key: 'countDisplay', label: 'Responden Valid', align: 'right' },
    ],
    rows: series.value.map((item) => ({
        name: seriesLabel(item),
        medianDisplay: formatNumber(item.median, 0),
        countDisplay: formatNumber(item.count, 0),
    })),
}));

// Konfigurasi views untuk kontainer terpadu: Tren Tahapan Prodi & Median Universitas
const views = computed(() => [
    {
        key: 'pipeline_line',
        tabLabel: 'Pipeline per Prodi',
        title: 'Perusahaan dilamar, merespon, dan wawancara per program studi',
        description: 'Perbandingan median tiga tahapan lamaran per prodi (mengadopsi Gambar 4.7 Laporan Tracer Study UKDW).',
        option: pipelineLineOption.value,
        tableData: pipelineLineTable.tableData || pipelineLineTable.value,
        height: 420,
        analysis: {
            title: 'Kilas Efisiensi Rekrutmen Prodi',
            statusBadge: 'Pipeline Antarjurusan',
            badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
            lead: 'Gambar 4.7 memperlihatkan konversi dari jumlah lamaran yang diajukan hingga perolehan panggilan wawancara kerja per program studi.',
            detail: 'Selisih antara jumlah perusahaan yang dilamar dan yang merespon mencerminkan tingginya daya saing portofolio alumni UKDW, di mana sebagian besar lulusan tidak memerlukan banyak lamaran untuk segera dipanggil wawancara.',
        },
    },
    {
        key: 'stages',
        tabLabel: 'Median Universitas',
        title: 'Median tahapan proses lamaran universitas',
        description: 'Perbandingan agregat median perusahaan yang dilamar, merespon, dan mewawancara di tingkat perguruan tinggi.',
        option: stagesBarOption.value,
        tableData: stagesBarTable.value,
        height: 320,
        analysis: {
            title: 'Kilas Rerata Universitas',
            statusBadge: 'Median Agregat',
            badgeClass: 'bg-slate-100 text-slate-700',
            lead: 'Rerata median universitas menunjukkan efisiensi proses pencarian kerja yang relatif terarah.',
            detail: 'Angka median wawancara yang mendekati angka respons membuktikan bahwa berkas lamaran alumni yang direspons memiliki probabilitas sangat tinggi untuk berlanjut ke tahap wawancara kerja.',
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
        indicator-badge="Indikator 06"
        @select="emit('select', $event)"
        @reset="emit('reset')"
    />
</template>
