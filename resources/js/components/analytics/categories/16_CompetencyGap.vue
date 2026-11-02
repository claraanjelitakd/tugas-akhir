<script setup>
/**
 * Komponen Indikator 16: Gap Kompetensi Lulusan (F17).
 *
 * Mengelola visualisasi gap kompetensi lulusan dalam 1 grafik terpadu:
 * - Batang (Bar): Kompetensi Saat Lulus (A) dan Kompetensi Diperlukan di Pekerjaan (B).
 * - Garis (Line): Gap Selisih (A - B) dengan penanda surplus (+) atau defisit (-).
 * Dilengkapi narasi analisis mendalam berstandar laporan resmi Tracer Study UKDW Subbab 4.17.
 */
import { computed } from 'vue';
import AnalyticsTabbedContainer from '../AnalyticsTabbedContainer.vue';
import {
    analyticsColors,
    baseOption,
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

// Ekstraksi data seri dan catatan responden valid.
const series = computed(() => props.chart?.series || []);
const records = computed(() => props.chart?.records || []);
const summary = computed(() => props.chart?.summary || {});
const validResponses = computed(() => summary.value.answered_count || records.value.length);

// Palet indikator mengikuti tema bersama seluruh grafik analitik.
const chartColors = {
    graduation: analyticsColors.green,
    need: analyticsColors.greenMid,
    gap: analyticsColors.greenSoft,
    axis: analyticsColors.text,
};

// -----------------------------------------------------------------------------
// 1 GRAFIK TERPADU: Batang (Saat Lulus & Diperlukan) + Garis (Gap Selisih)
// -----------------------------------------------------------------------------
const unifiedOption = computed(() => {
    const labels = series.value.map(seriesLabel);
    const gradScores = series.value.map((item) => Number(item.graduation_score || 0));
    const needScores = series.value.map((item) => Number(item.need_score || 0));
    const gapScores = series.value.map((item) => {
        const grad = Number(item.graduation_score || 0);
        const need = Number(item.need_score || 0);
        return Number((grad - need).toFixed(2));
    });

    return {
        ...baseOption(validResponses.value),
        legend: {
            data: ['Kompetensi Saat Lulus (Batang)', 'Kompetensi Diperlukan Kerja (Batang)', 'Gap Selisih Surplus/Defisit (Garis)'],
            bottom: 8,
            itemGap: 16,
            icon: 'circle',
            textStyle: { color: '#334155', fontWeight: 600, fontSize: 11 },
        },
        grid: { left: 92, right: 92, top: 52, bottom: 88, containLabel: true },
        xAxis: {
            type: 'category',
            data: labels,
            axisLabel: {
                color: '#334155',
                fontSize: 11,
                interval: 0,
                width: 118,
                overflow: 'break',
                lineHeight: 14,
            },
            axisLine: { lineStyle: { color: '#CBD5E1', width: 1 } },
        },
        yAxis: [
            {
                type: 'value',
                min: 0,
                max: 5,
                interval: 1,
                name: 'Skor Likert (1 - 5)',
                nameGap: 24,
                nameTextStyle: { color: '#475569', fontWeight: 600 },
                axisLabel: { color: chartColors.axis },
                splitLine: { show: false },
            },
            {
                type: 'value',
                min: -3,
                max: 3,
                interval: 1,
                name: 'Gap Selisih',
                nameGap: 24,
                nameTextStyle: { color: chartColors.gap, fontWeight: 600 },
                axisLabel: {
                    color: chartColors.gap,
                    formatter: (v) => (v > 0 ? `+${v}` : `${v}`),
                },
                splitLine: { show: false },
            },
        ],
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            formatter: (params) => {
                const titleText = escapeHtml(params[0]?.name || '');
                const grad = Number(params.find((p) => p.seriesName.includes('Saat Lulus'))?.value || 0);
                const need = Number(params.find((p) => p.seriesName.includes('Diperlukan'))?.value || 0);
                const gap = Number((grad - need).toFixed(2));
                const gapSign = gap > 0 ? `+${gap}` : `${gap}`;
                const gapStatus = gap >= 0
                    ? '<span style="color:#0D542B;font-weight:700">Surplus (Bekal Terpenuhi)</span>'
                    : '<span style="color:#2F7D4A;font-weight:700">Defisit (Perlu Penguatan)</span>';

                return `
                    <div class="space-y-1">
                        <p class="font-extrabold text-sm border-b border-emerald-100 pb-1 text-[#0D542B]">${titleText}</p>
                        <p class="text-xs text-slate-600">Saat Lulus (A): <strong class="text-[#0D542B]">${formatNumber(grad, 2)}</strong></p>
                        <p class="text-xs text-slate-600">Diperlukan Kerja (B): <strong class="text-[#0D542B]">${formatNumber(need, 2)}</strong></p>
                        <p class="text-xs text-slate-600">Gap Selisih (A - B): <strong class="text-[#0D542B]">${gapSign}</strong> (${gapStatus})</p>
                    </div>
                `;
            },
        },
        series: [
            {
                name: 'Kompetensi Saat Lulus (Batang)',
                type: 'bar',
                yAxisIndex: 0,
                barMaxWidth: 22,
                barGap: '18%',
                data: gradScores,
                itemStyle: { color: chartColors.graduation, borderRadius: [4, 4, 0, 0] },
                label: {
                    show: true,
                    position: 'top',
                    formatter: ({ value }) => formatNumber(value, 2),
                    fontSize: 10,
                    fontWeight: 'bold',
                    color: chartColors.graduation,
                },
            },
            {
                name: 'Kompetensi Diperlukan Kerja (Batang)',
                type: 'bar',
                yAxisIndex: 0,
                barMaxWidth: 22,
                barGap: '18%',
                data: needScores,
                itemStyle: { color: chartColors.need, borderRadius: [4, 4, 0, 0] },
                label: {
                    show: true,
                    position: 'top',
                    formatter: ({ value }) => formatNumber(value, 2),
                    fontSize: 10,
                    fontWeight: 'bold',
                    color: '#475569',
                },
            },
            {
                name: 'Gap Selisih Surplus/Defisit (Garis)',
                type: 'line',
                yAxisIndex: 1,
                z: 10,
                data: gapScores,
                itemStyle: { color: chartColors.gap },
                lineStyle: { width: 3, color: chartColors.gap },
                symbol: 'circle',
                symbolSize: 8,
                label: {
                    show: true,
                    position: 'top',
                    distance: 6,
                    formatter: ({ value, dataIndex }) => {
                        const firstIndex = gapScores.findIndex((score) => score === value);

                        if (dataIndex !== firstIndex) return '';

                        return value > 0 ? `+${formatNumber(value, 2)}` : formatNumber(value, 2);
                    },
                    fontSize: 10,
                    fontWeight: 'bold',
                    color: chartColors.gap,
                },
            },
        ],
    };
});

const unifiedTable = computed(() => ({
    columns: [
        { key: 'name', label: 'Dimensi Kompetensi (7 Aspek F17)' },
        { key: 'graduation', label: 'Saat Lulus (A)', align: 'right' },
        { key: 'need', label: 'Diperlukan Kerja (B)', align: 'right' },
        { key: 'gap', label: 'Gap Selisih (A - B)', align: 'right' },
        { key: 'status', label: 'Status Evaluasi', align: 'center' },
    ],
    rows: series.value.map((item) => {
        const grad = Number(item.graduation_score || 0);
        const need = Number(item.need_score || 0);
        const gap = Number((grad - need).toFixed(2));
        return {
            name: seriesLabel(item),
            graduation: formatNumber(grad, 2),
            need: formatNumber(need, 2),
            gap: gap > 0 ? `+${formatNumber(gap, 2)}` : formatNumber(gap, 2),
            status: gap >= 0 ? 'Surplus (Terpenuhi)' : 'Defisit (Evaluasi)',
        };
    }),
}));

// Narasi analisis dinamis Gambar 4.17 berdasarkan data aktual
const dynamicGapAnalysis = computed(() => {
    const items = series.value.map((item) => {
        const grad = Number(item.graduation_score || 0);
        const need = Number(item.need_score || 0);
        const gap = Number((grad - need).toFixed(2));
        return {
            name: seriesLabel(item),
            grad,
            need,
            gap,
        };
    });

    if (items.length === 0) {
        return {
            title: 'Kilas Analisis Kompetensi & Gap Lulusan (Gambar 4.17)',
            statusBadge: 'Gambar 4.17',
            badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
            lead: 'Data kompetensi lulusan sedang dimuat.',
            detail: '',
        };
    }

    const sortedByGap = [...items].sort((a, b) => b.gap - a.gap);
    const topGap = sortedByGap[0];
    const bottomGap = sortedByGap[sortedByGap.length - 1];

    const sortedByGrad = [...items].sort((a, b) => b.grad - a.grad);
    const topGrad = sortedByGrad[0];
    const bottomGrad = sortedByGrad[sortedByGrad.length - 1];

    const rincianList = sortedByGap.map((i, idx) => `${idx + 1}. ${i.name} (gap: ${i.gap > 0 ? `+${formatNumber(i.gap, 2)}` : formatNumber(i.gap, 2)})`).join(', ');

    const lead = `Gambar 4.17 menyajikan perbandingan bekal kompetensi saat lulus dengan kebutuhan dunia kerja beserta gap selisihnya pada 7 dimensi. Kompetensi dengan kepemilikan tertinggi saat lulus adalah ${topGrad.name} (skor ${formatNumber(topGrad.grad, 2)}), sedangkan terendah adalah ${bottomGrad.name} (skor ${formatNumber(bottomGrad.grad, 2)}).`;

    const detail = `Berdasarkan selisih gap (bekal lulus dikurangi kebutuhan kerja), dimensi dengan gap paling surplus/selaras adalah ${topGap.name} (${topGap.gap > 0 ? `+${formatNumber(topGap.gap, 2)}` : formatNumber(topGap.gap, 2)}), sementara dimensi dengan defisit terbesar yang paling membutuhkan penguatan kurikulum adalah ${bottomGap.name} (${bottomGap.gap > 0 ? `+${formatNumber(bottomGap.gap, 2)}` : formatNumber(bottomGap.gap, 2)}). Rincian urutan gap 7 dimensi dari tertinggi ke terendah: ${rincianList}.`;

    return {
        title: 'Kilas Analisis Kompetensi & Gap Lulusan (Gambar 4.17)',
        statusBadge: 'Gambar 4.17 Dinamis',
        badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
        lead,
        detail,
    };
});

// Konfigurasi views: 1 grafik terpadu gabungan Bar + Line Chart
const views = computed(() => [
    {
        key: 'unified_gap',
        chartType: 'combo_bar_line',
        tabLabel: 'Kompetensi & Gap Selisih',
        title: 'Kompetensi saat lulus, diperlukan kerja, dan gap selisih',
        description: 'Grafik terpadu: batang hijau mengukur bekal saat lulus UKDW, batang slate mengukur kebutuhan dunia kerja, dan garis hijau-slate memperlihatkan gap selisih kompetensi.',
        option: unifiedOption.value,
        tableData: unifiedTable.value,
        height: 420,
        analysis: dynamicGapAnalysis.value,
    },
]);
</script>

<template>
    <AnalyticsTabbedContainer
        :views="views"
        :rows="records"
        :selected-label="selectedLabel"
        :loading="loading"
        indicator-badge="Indikator 16"
        @select="emit('select', $event)"
        @reset="emit('reset')"
    />
</template>
