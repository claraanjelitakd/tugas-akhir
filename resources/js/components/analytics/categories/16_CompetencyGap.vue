<script setup>
/**
 * Komponen Indikator 16: Gap Kompetensi Lulusan (F17).
 *
 * Mengelola visualisasi gap kompetensi lulusan dalam 2 mode perspektif:
 * 1. Gap Analysis Diagram per Dimensi (Satu per satu: Etika, Keahlian Bidang Ilmu, dsb.)
 *    Model visual segitiga area "THE GAP" antara garis Potential (Where We Want To Be)
 *    dan Performance (Where We Are) sesuai standar presentasi analitik bisnis / PowerPoint.
 * 2. Peta Komparasi Seluruh Dimensi (Kombinasi Bar & Line 7 Aspek F17 sekaligus).
 *
 * Dilengkapi narasi analisis mendalam berstandar laporan resmi Tracer Study UKDW Subbab 4.17.
 */
import { computed, ref } from 'vue';
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

// State aspek kompetensi yang sedang diamati satu per satu (default: aspek pertama)
const selectedAspectIndex = ref(0);

// Daftar aspek kompetensi dari data backend
const aspects = computed(() => {
    return series.value.map((item, index) => {
        const grad = Number(item.graduation_score || 0);
        const need = Number(item.need_score || 0);
        const gap = grad - need;
        return {
            index,
            key: item.key || `aspect_${index}`,
            label: seriesLabel(item),
            grad,
            need,
            gap,
            count: Number(item.count || 0),
            isDeficit: need > grad,
        };
    });
});

// Objek aspek kompetensi aktif
const activeAspect = computed(() => {
    return aspects.value[selectedAspectIndex.value] || aspects.value[0] || {
        label: 'Etika',
        grad: 3,
        need: 5,
        gap: -2,
        isDeficit: true,
    };
});

// Pilih aspek kompetensi
const selectAspect = (index) => {
    selectedAspectIndex.value = index;
};

// Palet indikator mengikuti tema bersama seluruh grafik analitik UKDW.
const chartColors = {
    potential: '#0D542B',   // Hijau UKDW (Target / Potential)
    performance: '#059669', // Emerald (Bekal Lulus / Performance)
    axis: '#64748B',
    gridLine: '#E2E8F0',
};

// -----------------------------------------------------------------------------
// 1. GAP ANALYSIS DIAGRAM (VISUALISASI SATU PER SATU SESUAI GAMBAR POWERPOINT)
// -----------------------------------------------------------------------------
const gapDiagramOption = computed(() => {
    const item = activeAspect.value;
    const gradScore = item.grad;
    const needScore = item.need;
    const gap = item.gap;
    const isDeficit = item.isDeficit;
    const gapSign = gap > 0 ? `+${gap}` : `${gap}`;
    const statusText = gap >= 0 ? 'Surplus (Bekal Terpenuhi)' : 'Defisit (Kebutuhan Penguatan)';

    // Baseline di titik awal (Skala Likert minimal 1.0)
    const baselineY = 1.0;

    // Warna arsiran area segitiga THE GAP
    const gapAreaColor = isDeficit ? 'rgba(234, 88, 12, 0.14)' : 'rgba(13, 84, 43, 0.14)';
    const gapBorderColor = isDeficit ? 'rgba(234, 88, 12, 0.55)' : 'rgba(13, 84, 43, 0.55)';
    const gapTextColor = isDeficit ? '#9A3412' : '#0D542B';

    return {
        ...baseOption(validResponses.value),
        title: {
            text: `Gap Analysis Diagram: ${item.label}`,
            subtext: `Evaluasi selisih bekal lulusan (Performance) vs tuntutan dunia kerja (Potential) • N valid: ${formatNumber(validResponses.value, 0)}`,
            left: 20,
            top: 12,
            textStyle: {
                fontSize: 16,
                fontWeight: 'bold',
                color: '#0F172A',
            },
            subtextStyle: {
                fontSize: 11,
                color: '#64748B',
            },
        },
        legend: {
            show: true,
            bottom: 10,
            itemGap: 24,
            icon: 'circle',
            textStyle: { color: '#334155', fontWeight: 600, fontSize: 11 },
            data: [
                'Potential (Where We Want To Be) - Tuntutan Kerja',
                'Performance (Where We Are) - Bekal Saat Lulus',
            ],
        },
        grid: {
            left: 48,
            right: 250,
            top: 76,
            bottom: 64,
            containLabel: true,
        },
        xAxis: {
            type: 'category',
            boundaryGap: false,
            data: ['Titik Masuk Kuliah (Baseline)', 'Evaluasi di Dunia Kerja'],
            axisLine: { lineStyle: { color: '#94A3B8', width: 2 } },
            axisTick: { show: false },
            axisLabel: {
                color: '#334155',
                fontWeight: 700,
                fontSize: 12,
                margin: 14,
            },
            splitLine: { show: false },
        },
        yAxis: {
            type: 'value',
            min: 1,
            max: 5,
            interval: 1,
            name: 'Skor Likert (1 - 5)',
            nameGap: 26,
            nameTextStyle: { color: '#475569', fontWeight: 700, fontSize: 11 },
            axisLine: { show: true, lineStyle: { color: '#94A3B8', width: 2 } },
            axisLabel: { color: '#475569', fontWeight: 600, fontSize: 11 },
            splitLine: { lineStyle: { color: chartColors.gridLine, type: 'dashed' } },
        },
        tooltip: {
            trigger: 'item',
            formatter: () => `
                <div class="space-y-1 p-1">
                    <p class="font-extrabold text-sm border-b pb-1 text-[#0D542B]">${item.label}</p>
                    <p class="text-xs text-slate-600">Potential (Tuntutan Kerja): <strong class="text-[#0D542B]">${needScore}</strong></p>
                    <p class="text-xs text-slate-600">Performance (Saat Lulus): <strong class="text-emerald-700">${gradScore}</strong></p>
                    <p class="text-xs text-slate-600">Gap Selisih: <strong class="${isDeficit ? 'text-amber-700' : 'text-[#0D542B]'}">${gapSign} poin</strong> (${statusText})</p>
                </div>
            `,
        },
        series: [
            // Series 1: Area Segitiga Arsiran "THE GAP"
            {
                type: 'custom',
                z: 2,
                renderItem: (params, api) => {
                    const p0 = api.coord([0, baselineY]);
                    const pPotential = api.coord([1, needScore]);
                    const pPerformance = api.coord([1, gradScore]);

                    // Titik tengah / centroid segitiga
                    const centerX = (p0[0] + pPotential[0] + pPerformance[0]) / 3;
                    const centerY = (p0[1] + pPotential[1] + pPerformance[1]) / 3;

                    return {
                        type: 'group',
                        children: [
                            // Area Segitiga
                            {
                                type: 'polygon',
                                shape: {
                                    points: [p0, pPotential, pPerformance],
                                },
                                style: {
                                    fill: gapAreaColor,
                                    stroke: gapBorderColor,
                                    lineWidth: 1.5,
                                    lineDash: [4, 4],
                                },
                            },
                            // Garis Penutup Vertikal di Kanan Antara Titik Potential & Performance
                            {
                                type: 'line',
                                shape: {
                                    x1: pPotential[0],
                                    y1: pPotential[1],
                                    x2: pPerformance[0],
                                    y2: pPerformance[1],
                                },
                                style: {
                                    stroke: gapBorderColor,
                                    lineWidth: 2,
                                },
                            },
                            // Teks "THE GAP"
                            {
                                type: 'text',
                                style: {
                                    text: 'THE GAP',
                                    x: centerX + 20,
                                    y: centerY - 9,
                                    textAlign: 'center',
                                    textVerticalAlign: 'middle',
                                    fill: '#0F172A',
                                    fontSize: 13,
                                    fontWeight: 900,
                                    letterSpacing: 2,
                                },
                            },
                            // Teks Selisih Poin
                            {
                                type: 'text',
                                style: {
                                    text: `${gapSign} Poin (${isDeficit ? 'Defisit' : 'Surplus'})`,
                                    x: centerX + 20,
                                    y: centerY + 11,
                                    textAlign: 'center',
                                    textVerticalAlign: 'middle',
                                    fill: gapTextColor,
                                    fontSize: 11,
                                    fontWeight: 700,
                                },
                            },
                        ],
                    };
                },
                data: [0],
            },
            // Series 2: Garis Potential (Where We Want To Be) - Tuntutan Kerja
            {
                name: 'Potential (Where We Want To Be) - Tuntutan Kerja',
                type: 'line',
                z: 5,
                data: [baselineY, needScore],
                lineStyle: {
                    width: 3.5,
                    type: 'dashed',
                    color: chartColors.potential,
                },
                itemStyle: {
                    color: chartColors.potential,
                    borderColor: '#FFFFFF',
                    borderWidth: 2.5,
                },
                symbol: 'circle',
                symbolSize: 13,
                label: {
                    show: true,
                    formatter: (param) => {
                        if (param.dataIndex === 1) {
                            return `Potential (Where We Want To Be)\nTuntutan Kerja: Skor ${needScore}`;
                        }
                        return '';
                    },
                    position: 'right',
                    distance: 14,
                    color: chartColors.potential,
                    fontSize: 11,
                    fontWeight: 700,
                    lineHeight: 16,
                },
            },
            // Series 3: Garis Performance (Where We Are) - Bekal Saat Lulus
            {
                name: 'Performance (Where We Are) - Bekal Saat Lulus',
                type: 'line',
                z: 5,
                data: [baselineY, gradScore],
                lineStyle: {
                    width: 3.5,
                    type: 'solid',
                    color: chartColors.performance,
                },
                itemStyle: {
                    color: chartColors.performance,
                    borderColor: '#FFFFFF',
                    borderWidth: 2.5,
                },
                symbol: 'circle',
                symbolSize: 13,
                label: {
                    show: true,
                    formatter: (param) => {
                        if (param.dataIndex === 1) {
                            return `Performance (Where We Are)\nBekal Saat Lulus: Skor ${gradScore}`;
                        }
                        return '';
                    },
                    position: 'right',
                    distance: 14,
                    color: chartColors.performance,
                    fontSize: 11,
                    fontWeight: 700,
                    lineHeight: 16,
                },
            },
        ],
    };
});

// Analisis naratif khusus dimensi terpilih
const aspectAnalysis = computed(() => {
    const item = activeAspect.value;
    const isDeficit = item.isDeficit;
    const gapSign = item.gap > 0 ? `+${item.gap}` : `${item.gap}`;

    const lead = `Pada dimensi "${item.label}", alumni menilai rata-rata bekal kompetensi saat lulus berada pada skor ${item.grad} (Performance / Where We Are), sementara tuntutan kebutuhan nyata di dunia kerja berada pada skor ${item.need} (Potential / Where We Want To Be).`;

    const detail = isDeficit
        ? `Terdapat defisit gap sebesar ${gapSign} poin. Hal ini mengindikasikan bahwa dunia industri menuntut penguasaan "${item.label}" yang lebih tinggi dibandingkan bekal awal kelulusan, sehingga program studi direkomendasikan memperkuat integrasi materi dan praktik pada dimensi ini ke dalam kurikulum pembelajaran.`
        : `Tercatat surplus gap sebesar ${gapSign} poin. Bekal kompetensi "${item.label}" yang ditanamkan selama perkuliahan di UKDW telah melampaui atau selaras dengan ekspektasi tuntutan operasional di lingkungan kerja alumni.`;

    return {
        title: `Kilas Gap Analisis · ${item.label}`,
        statusBadge: isDeficit ? 'Defisit Kompetensi' : 'Surplus Kompetensi',
        badgeClass: isDeficit ? 'bg-amber-50 text-amber-900 border border-amber-300' : 'bg-emerald-50 text-[#0D542B] border border-emerald-300',
        lead,
        detail,
    };
});

// Tabel ringkasan khusus dimensi terpilih
const aspectTable = computed(() => ({
    columns: [
        { key: 'metric', label: 'Indikator Evaluasi' },
        { key: 'score', label: 'Nilai Skor', align: 'right' },
        { key: 'description', label: 'Keterangan' },
    ],
    rows: [
        {
            metric: 'Potential (Where We Want To Be)',
            score: formatNumber(activeAspect.value.need, 0),
            description: 'Tingkat kompetensi yang dibutuhkan dalam dunia kerja',
        },
        {
            metric: 'Performance (Where We Are)',
            score: formatNumber(activeAspect.value.grad, 0),
            description: 'Tingkat penguasaan kompetensi saat lulus dari UKDW',
        },
        {
            metric: 'THE GAP (Selisih Evaluasi)',
            score: activeAspect.value.gap > 0 ? `+${activeAspect.value.gap}` : `${activeAspect.value.gap}`,
            description: activeAspect.value.isDeficit ? 'Defisit (Kebutuhan penguatan kurikulum)' : 'Surplus (Bekal melampaui kebutuhan)',
        },
    ],
}));

// -----------------------------------------------------------------------------
// 2. GRAFIK KOMPARASI SELURUH DIMENSI (PETA KOMPARASI MAKRO 7 ASPEK)
// -----------------------------------------------------------------------------
const unifiedOption = computed(() => {
    const labels = series.value.map(seriesLabel);
    const gradScores = series.value.map((item) => Number(item.graduation_score || 0));
    const needScores = series.value.map((item) => Number(item.need_score || 0));
    const gapScores = series.value.map((item) => {
        const grad = Number(item.graduation_score || 0);
        const need = Number(item.need_score || 0);
        return Math.round(grad - need);
    });

    return {
        ...baseOption(validResponses.value),
        title: {
            text: 'Peta Komparasi Seluruh Dimensi Kompetensi (7 Aspek F17)',
            subtext: `Perbandingan bekal saat lulus, tuntutan kerja, dan gap selisih seluruh aspek • N valid: ${formatNumber(validResponses.value, 0)}`,
            left: 20,
            top: 10,
            textStyle: { fontSize: 16, fontWeight: 'bold', color: '#0F172A' },
            subtextStyle: { fontSize: 11, color: '#64748B' },
        },
        legend: {
            data: ['Kompetensi Saat Lulus (Batang)', 'Kompetensi Diperlukan Kerja (Batang)', 'Gap Selisih Surplus/Defisit (Garis)'],
            bottom: 8,
            itemGap: 16,
            icon: 'circle',
            textStyle: { color: '#334155', fontWeight: 600, fontSize: 11 },
        },
        grid: { left: 92, right: 92, top: 64, bottom: 88, containLabel: true },
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
                nameTextStyle: { color: chartColors.potential, fontWeight: 600 },
                axisLabel: {
                    color: chartColors.potential,
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
                const gap = Math.round(grad - need);
                const gapSign = gap > 0 ? `+${gap}` : `${gap}`;
                const gapStatus = gap >= 0
                    ? '<span style="color:#0D542B;font-weight:700">Surplus (Bekal Terpenuhi)</span>'
                    : '<span style="color:#C2410C;font-weight:700">Defisit (Perlu Penguatan)</span>';

                return `
                    <div class="space-y-1">
                        <p class="font-extrabold text-sm border-b border-emerald-100 pb-1 text-[#0D542B]">${titleText}</p>
                        <p class="text-xs text-slate-600">Saat Lulus (A): <strong class="text-[#0D542B]">${formatNumber(grad, 0)}</strong></p>
                        <p class="text-xs text-slate-600">Diperlukan Kerja (B): <strong class="text-[#0D542B]">${formatNumber(need, 0)}</strong></p>
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
                itemStyle: { color: chartColors.potential, borderRadius: [4, 4, 0, 0] },
                label: {
                    show: true,
                    position: 'top',
                    formatter: ({ value }) => formatNumber(value, 0),
                    fontSize: 10,
                    fontWeight: 'bold',
                    color: chartColors.potential,
                },
            },
            {
                name: 'Kompetensi Diperlukan Kerja (Batang)',
                type: 'bar',
                yAxisIndex: 0,
                barMaxWidth: 22,
                barGap: '18%',
                data: needScores,
                itemStyle: { color: '#64748B', borderRadius: [4, 4, 0, 0] },
                label: {
                    show: true,
                    position: 'top',
                    formatter: ({ value }) => formatNumber(value, 0),
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
                itemStyle: { color: chartColors.performance },
                lineStyle: { width: 3, color: chartColors.performance },
                symbol: 'circle',
                symbolSize: 8,
                label: {
                    show: true,
                    position: 'top',
                    distance: 6,
                    formatter: ({ value, dataIndex }) => {
                        const firstIndex = gapScores.findIndex((score) => score === value);
                        if (dataIndex !== firstIndex) return '';
                        return value > 0 ? `+${formatNumber(value, 0)}` : formatNumber(value, 0);
                    },
                    fontSize: 10,
                    fontWeight: 'bold',
                    color: chartColors.performance,
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
        const gap = Math.round(grad - need);
        return {
            name: seriesLabel(item),
            graduation: formatNumber(grad, 0),
            need: formatNumber(need, 0),
            gap: gap > 0 ? `+${formatNumber(gap, 0)}` : formatNumber(gap, 0),
            status: gap >= 0 ? 'Surplus (Terpenuhi)' : 'Defisit (Evaluasi)',
        };
    }),
}));

const dynamicGapAnalysis = computed(() => {
    const items = aspects.value;
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

    const rincianList = sortedByGap.map((i, idx) => `${idx + 1}. ${i.label} (${i.gap > 0 ? `+${formatNumber(i.gap, 0)}` : formatNumber(i.gap, 0)})`).join(', ');

    const lead = `Gambar 4.17 menyajikan perbandingan bekal kompetensi saat lulus dengan kebutuhan dunia kerja beserta gap selisihnya pada 7 dimensi. Kompetensi dengan kepemilikan tertinggi saat lulus adalah ${topGrad.label} (skor ${formatNumber(topGrad.grad, 0)}), sedangkan terendah adalah ${bottomGrad.label} (skor ${formatNumber(bottomGrad.grad, 0)}).`;

    const detail = `Berdasarkan selisih gap (bekal lulus dikurangi kebutuhan kerja), dimensi dengan gap paling surplus/selaras adalah ${topGap.label} (${topGap.gap > 0 ? `+${formatNumber(topGap.gap, 0)}` : formatNumber(topGap.gap, 0)}), sementara dimensi dengan defisit terbesar yang paling membutuhkan penguatan kurikulum adalah ${bottomGap.label} (${bottomGap.gap > 0 ? `+${formatNumber(bottomGap.gap, 0)}` : formatNumber(bottomGap.gap, 0)}). Rincian urutan gap 7 dimensi dari tertinggi ke terendah: ${rincianList}.`;

    return {
        title: 'Kilas Analisis Kompetensi & Gap Lulusan (Gambar 4.17)',
        statusBadge: 'Gambar 4.17 Dinamis',
        badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
        lead,
        detail,
    };
});

// Konfigurasi views kontainer analitik
const views = computed(() => [
    {
        key: 'gap_analysis_diagram',
        chartType: 'line',
        tabLabel: 'Diagram Gap per Dimensi',
        title: `Gap Analysis Diagram: ${activeAspect.value.label}`,
        description: 'Visualisasi Gap Analysis Diagram: membandingkan garis ekspektasi tuntutan kerja (Potential / Where We Want To Be) dengan garis bekal kelulusan (Performance / Where We Are) serta area selisih (THE GAP).',
        option: gapDiagramOption.value,
        tableData: aspectTable.value,
        height: 440,
        analysis: aspectAnalysis.value,
    },
    {
        key: 'unified_gap',
        chartType: 'combo_bar_line',
        tabLabel: 'Komparasi Seluruh Dimensi',
        title: 'Peta Komparasi Seluruh Dimensi Kompetensi (7 Aspek F17)',
        description: 'Grafik komparasi makro: batang hijau menunjukkan rata-rata bekal saat lulus UKDW, batang abu-abu menunjukkan rata-rata kebutuhan dunia kerja, dan garis menunjukkan gap selisih.',
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
    >
        <!-- Slot Kontrol Tambahan: Bar Selector Dimensi Kompetensi (Satu per Satu) -->
        <template #before-chart="{ view }">
            <div v-if="view.key === 'gap_analysis_diagram'" class="mb-4 space-y-3">
                <!-- Baris Tombol Pemilih Dimensi Kompetensi (Chips Pills) -->
                <div>
                    <div class="flex items-center justify-between mb-1.5 flex-wrap gap-2">
                        <span class="text-xs font-bold text-slate-700">
                            Pilih Dimensi Kompetensi untuk Ditinjau Satu per Satu:
                        </span>
                        <span class="text-[11px] text-slate-500 font-medium">
                            Menampilkan <strong>{{ activeAspect.label }}</strong> (Dimensi {{ selectedAspectIndex + 1 }} dari {{ aspects.length }})
                        </span>
                    </div>

                    <div class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-none flex-wrap sm:flex-nowrap">
                        <button
                            v-for="(asp, idx) in aspects"
                            :key="asp.key"
                            type="button"
                            @click="selectAspect(idx)"
                            class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer whitespace-nowrap flex items-center gap-1.5 border"
                            :class="selectedAspectIndex === idx
                                ? 'bg-[#0D542B] text-white border-[#0D542B] shadow-xs'
                                : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50 hover:border-slate-300'"
                        >
                            <span>{{ asp.label }}</span>
                            <span
                                class="text-[10px] px-1.5 py-0.2 rounded-md font-mono"
                                :class="selectedAspectIndex === idx
                                    ? 'bg-white/20 text-white'
                                    : (asp.isDeficit ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800')"
                            >
                                {{ asp.gap > 0 ? `+${asp.gap}` : asp.gap }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Kartu Ringkasan Indikator Cepat untuk Dimensi Aktif -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-3.5 rounded-xl bg-slate-50 border border-slate-200 text-xs">
                    <!-- Potential (Tuntutan Kerja) -->
                    <div class="flex items-center gap-2.5">
                        <div class="w-2.5 h-2.5 rounded-full bg-[#0D542B] shrink-0"></div>
                        <div>
                            <span class="block text-slate-500 text-[11px] font-semibold">Tuntutan Dunia Kerja (Potential)</span>
                            <span class="font-extrabold text-sm text-[#0D542B]">Skor {{ activeAspect.need }} / 5</span>
                        </div>
                    </div>

                    <!-- Performance (Bekal Lulus) -->
                    <div class="flex items-center gap-2.5">
                        <div class="w-2.5 h-2.5 rounded-full bg-[#059669] shrink-0"></div>
                        <div>
                            <span class="block text-slate-500 text-[11px] font-semibold">Bekal Saat Lulus (Performance)</span>
                            <span class="font-extrabold text-sm text-emerald-700">Skor {{ activeAspect.grad }} / 5</span>
                        </div>
                    </div>

                    <!-- Gap Selisih -->
                    <div class="flex items-center gap-2.5 sm:border-l sm:border-slate-200 sm:pl-3">
                        <div
                            class="w-2.5 h-2.5 rounded-full shrink-0"
                            :class="activeAspect.isDeficit ? 'bg-amber-600' : 'bg-[#0D542B]'"
                        ></div>
                        <div>
                            <span class="block text-slate-500 text-[11px] font-semibold">THE GAP (Selisih Evaluasi)</span>
                            <span
                                class="font-extrabold text-sm"
                                :class="activeAspect.isDeficit ? 'text-amber-800' : 'text-[#0D542B]'"
                            >
                                {{ activeAspect.gap > 0 ? `+${activeAspect.gap}` : activeAspect.gap }} Poin
                                ({{ activeAspect.isDeficit ? 'Defisit' : 'Surplus' }})
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </AnalyticsTabbedContainer>
</template>
