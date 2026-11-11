<script setup>
/**
 * Komponen Indikator 13: Keselarasan Tingkat Pendidikan Sesuai Pekerjaan per Prodi (Vertical Match - F15).
 *
 * Mengadopsi Gambar 4.14 Laporan Tracer Study UKDW:
 * 1. Bar Chart: Respon Keselarasan Tingkat Pendidikan yang Sama per Program Studi (Gambar 4.14)
 * 2. Stacked Bar: Komposisi Lengkap Kualifikasi Pendidikan per Program Studi
 */
import { computed } from 'vue';
import AnalyticsTabbedContainer from '../AnalyticsTabbedContainer.vue';
import {
    analyticsScalePalette,
    baseOption,
    escapeHtml,
    formatNumber,
    formatPercentage,
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

const prodis = computed(() => [...new Set(records.value.map((r) => r.nama_prodi || 'Tanpa prodi'))]);

// -----------------------------------------------------------------------------
// 1. BAR CHART: Vertical Match per Program Studi (Gambar 4.14 Laporan Resmi)
// -----------------------------------------------------------------------------
const prodiVerticalMatchData = computed(() => {
    if (props.chart?.prodi_series) {
        return props.chart.prodi_series.map((item) => ({
            prodi: item.prodi,
            matches: Number(item.match_count || 0),
            total: Number(item.total || 0),
            percentage: Number(item.match_percentage || 0),
        }));
    }

    return prodis.value.map((prodi) => {
        const rows = records.value.filter((r) => (r.nama_prodi || 'Tanpa prodi') === prodi);
        const total = rows.length;
        // Alumni yang menyatakan tingkat pendidikan yang sama (S1) dengan pekerjaan
        const matches = rows.filter((r) => {
            const ans = String(recordCategory(r) || '').toLowerCase();
            return ans.includes('sama') || ans === 'setingkat';
        }).length;
        const pct = total > 0 ? Number(((matches / total) * 100).toFixed(1)) : 0;

        return {
            prodi,
            matches,
            total,
            percentage: pct,
        };
    }).sort((a, b) => b.percentage - a.percentage);
});

const verticalMatchOption = computed(() => {
    const list = prodiVerticalMatchData.value;
    const labels = list.map((i) => `${i.prodi}\n(n=${i.total})`);

    return {
        ...baseOption(validResponses.value),
        grid: { left: 24, right: 36, top: 28, bottom: 80, containLabel: true },
        xAxis: {
            type: 'category',
            data: labels,
            axisLabel: { color: '#334155', fontSize: 11, interval: 0, rotate: 30, lineHeight: 14 },
            axisLine: { lineStyle: { color: '#CBD5E1' } },
        },
        yAxis: {
            type: 'value',
            name: 'Persentase Tingkat Pendidikan Sama (%)',
            max: 100,
            axisLabel: { formatter: '{value}%' },
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
        },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'axis',
            formatter: (params) => {
                const item = list[params[0]?.dataIndex ?? 0] || {};
                return `<strong>${escapeHtml(item.prodi)}</strong><br>Keselarasan Tingkat Sama: <strong>${item.percentage}%</strong><br>Jumlah alumni: <strong>${item.matches} dari ${item.total} responden</strong>`;
            },
        },
        series: [{
            name: 'Tingkat Pendidikan Sama',
            type: 'line',
            data: list.map((i) => i.percentage),
            itemStyle: { color: '#0D542B' },
            lineStyle: { width: 3, color: '#0D542B' },
            symbol: 'circle',
            symbolSize: 8,
            emphasis: { scale: 1.5 },
            label: {
                show: true,
                position: 'top',
                color: '#0D542B',
                fontWeight: 'bold',
                fontSize: 11,
                formatter: ({ value }) => `${value}%`,
            },
        }],
    };
});

const verticalMatchTable = computed(() => ({
    columns: [
        { key: 'prodi', label: 'Program Studi' },
        { key: 'percentageDisplay', label: 'Persentase Tingkat Sama (%)', align: 'right' },
        { key: 'countDisplay', label: 'Alumni Selaras / Total', align: 'right' },
    ],
    rows: prodiVerticalMatchData.value.map((i) => ({
        prodi: i.prodi,
        percentageDisplay: `${i.percentage}%`,
        countDisplay: `${i.matches} / ${i.total} alumni`,
    })),
}));

// -----------------------------------------------------------------------------
// 2. MATRIKS HEATMAP: Sebaran Kualifikasi per Program Studi (Mengadopsi Indikator 02)
// -----------------------------------------------------------------------------
const categories = computed(() => {
    const activeCats = series.value.filter((s) => seriesMetric(s) > 0).map((s) => s.label);
    return activeCats.length > 0 ? activeCats : [...new Set(series.value.map((s) => s.label))];
});

const heatmapData = computed(() => {
    const listProdis = prodis.value;
    const cats = categories.value;
    const prodiTotals = Object.fromEntries(
        listProdis.map((p) => [p, records.value.filter((r) => (r.nama_prodi || 'Tanpa prodi') === p).length]),
    );

    const values = [];
    listProdis.forEach((prodi, yIndex) => {
        cats.forEach((cat, xIndex) => {
            const count = records.value.filter((r) => (r.nama_prodi || 'Tanpa prodi') === prodi && recordCategory(r) === cat).length;
            const total = Math.max(prodiTotals[prodi], 1);
            const pct = Number(((count / total) * 100).toFixed(1));
            values.push({
                value: [xIndex, yIndex, pct],
                prodi,
                category: cat,
                count,
                total,
            });
        });
    });

    const maxPct = Math.max(...values.map((v) => v.value[2]), 10);

    return { values, maxPct, prodiTotals };
});

const heatmapOption = computed(() => {
    const { values, maxPct, prodiTotals } = heatmapData.value;
    const cats = categories.value;
    const listProdis = prodis.value;
    const yLabels = listProdis.map((p) => `${p} (n=${prodiTotals[p]})`);

    return {
        ...baseOption(records.value.length),
        grid: { left: 24, right: 120, top: 75, bottom: 24, containLabel: true },
        xAxis: {
            type: 'category',
            position: 'top',
            data: cats,
            axisLabel: {
                rotate: -20,
                color: '#1E293B',
                fontSize: 11,
                fontWeight: 600,
                interval: 0,
            },
            axisTick: { show: true, alignWithLabel: true, lineStyle: { color: '#CBD5E1' } },
            axisLine: { lineStyle: { color: '#CBD5E1' } },
            splitArea: { show: false },
        },
        yAxis: {
            type: 'category',
            inverse: true,
            data: yLabels,
            axisLabel: { color: '#1E293B', fontSize: 11, fontWeight: 500 },
            axisTick: { show: true, alignWithLabel: true, lineStyle: { color: '#CBD5E1' } },
            axisLine: { lineStyle: { color: '#CBD5E1' } },
            splitArea: { show: false },
        },
        visualMap: {
            type: 'continuous',
            min: 0,
            max: Math.min(100, Math.ceil(maxPct / 10) * 10),
            orient: 'vertical',
            right: 14,
            top: 'middle',
            itemWidth: 16,
            itemHeight: 220,
            calculable: true,
            inRange: {
                color: analyticsScalePalette,
            },
            text: ['Tinggi (%)', 'Rendah (%)'],
            textGap: 8,
            textStyle: { color: '#1E293B', fontSize: 11, fontWeight: 600 },
            formatter: (val) => `${Number(val).toFixed(0)}%`,
        },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'item',
            formatter: ({ data }) => {
                if (!data) return '';
                return `
                    <div class="font-sans text-xs">
                        <div class="font-bold text-slate-900 border-b border-slate-200 pb-1 mb-1.5">${escapeHtml(data.prodi)}</div>
                        <div class="text-slate-600 mb-0.5"><span class="text-slate-500">Kualifikasi:</span> <strong>${escapeHtml(data.category)}</strong></div>
                        <div class="text-slate-600 mb-0.5"><span class="text-slate-500">Jumlah Alumni:</span> <strong>${formatNumber(data.count, 0)}</strong> (dari n=${data.total})</div>
                        <div class="text-slate-600"><span class="text-slate-500">Proporsi di Prodi:</span> <strong class="text-emerald-700 font-mono text-sm">${formatNumber(data.value[2], 1)}%</strong></div>
                    </div>
                `;
            },
        },
        series: [{
            name: 'Proporsi Kualifikasi Pendidikan',
            type: 'heatmap',
            data: values,
            itemStyle: {
                borderColor: '#FFFFFF',
                borderWidth: 1.5,
            },
            label: {
                show: true,
                formatter: ({ data }) => (data && data.value[2] > 0 ? `${formatNumber(data.value[2], 0)}%` : ''),
                fontSize: 10,
                fontWeight: 600,
                color: (param) => (param.data?.value[2] >= 30 ? '#FFFFFF' : '#1E293B'),
            },
            emphasis: {
                itemStyle: {
                    shadowBlur: 8,
                    shadowColor: 'rgba(13, 84, 43, 0.25)',
                    borderColor: '#0D542B',
                    borderWidth: 2,
                },
            },
        }],
    };
});

const heatmapTable = computed(() => {
    const cats = categories.value;
    const prodiTotals = heatmapData.value.prodiTotals;

    const columns = [
        { key: 'prodi', label: 'Program Studi' },
        { key: 'total', label: 'Responden (n)', align: 'right' },
        ...cats.map((cat, i) => ({
            key: `cat_${i}`,
            label: cat,
            align: 'right',
        })),
    ];

    const rows = prodis.value.map((prodi) => {
        const row = {
            prodi,
            total: formatNumber(prodiTotals[prodi] || 0, 0),
        };
        cats.forEach((cat, i) => {
            const count = records.value.filter((r) => (r.nama_prodi || 'Tanpa prodi') === prodi && recordCategory(r) === cat).length;
            const total = Math.max(prodiTotals[prodi], 1);
            const pct = ((count / total) * 100).toFixed(1);
            row[`cat_${i}`] = `${formatNumber(count, 0)} (${pct}%)`;
        });
        return row;
    });

    return { columns, rows };
});

// -----------------------------------------------------------------------------
// 3. STACKED BAR: Komposisi Lengkap Kualifikasi Pendidikan per Prodi
// -----------------------------------------------------------------------------
// Warna dipetakan berdasarkan makna kategori: merah untuk kualifikasi di bawah kebutuhan pendidikan,
// hijau untuk kualifikasi yang setara atau lebih tinggi.
const getEducationAlignmentColor = (label) => {
    const normalized = String(label || '').toLowerCase().trim();
    if (normalized.includes('lebih tinggi')) return '#0D542B';
    if (normalized.includes('sama')) return '#2F7D4A';
    if (normalized.includes('lebih rendah')) return '#EF4444';
    if (normalized.includes('tidak perlu')) return '#B91C1C';
    return '#64748B';
};

// Menjaga urutan stack dari kondisi paling rendah hingga paling tinggi secara konsisten.
const getEducationAlignmentOrder = (label) => {
    const normalized = String(label || '').toLowerCase().trim();
    if (normalized.includes('tidak perlu')) return 0;
    if (normalized.includes('lebih rendah')) return 1;
    if (normalized.includes('sama')) return 2;
    if (normalized.includes('lebih tinggi')) return 3;
    return 4;
};

const stackedOption = computed(() => {
    const listProdis = prodis.value;
    const cats = categories.value;
    const prodiTotals = Object.fromEntries(
        listProdis.map((p) => [p, records.value.filter((r) => (r.nama_prodi || 'Tanpa prodi') === p).length]),
    );

    const seriesList = cats.map((cat) => {
        const catData = listProdis.map((prodi) => {
            const count = records.value.filter((r) => (r.nama_prodi || 'Tanpa prodi') === prodi && recordCategory(r) === cat).length;
            const total = Math.max(prodiTotals[prodi], 1);
            const pct = Number(((count / total) * 100).toFixed(1));
            return {
                value: pct,
                count,
                total,
                prodi,
                category: cat,
            };
        });

        const selected = !props.selectedLabel || props.selectedLabel === cat;

        return {
            name: cat,
            type: 'bar',
            stack: 'total',
            stackOrder: getEducationAlignmentOrder(cat),
            barMaxWidth: 26,
            itemStyle: {
                // Fungsi menjaga warna semantik saat normalizer global mengurutkan seri stack.
                color: () => getEducationAlignmentColor(cat),
                opacity: selected ? 1 : 0.22,
                borderColor: '#FFFFFF',
                borderWidth: 1.5,
            },
            label: {
                show: true,
                position: 'inside',
                formatter: ({ data }) => (data && data.value >= 10 ? `${data.value}%` : ''),
                fontSize: 10,
                color: '#FFFFFF',
                fontWeight: 'bold',
            },
            data: catData,
        };
    });

    const labels = listProdis.map((p) => `${p} (n=${prodiTotals[p]})`);

    // Legenda vertikal mengikuti pola Indikator 02 dengan dua keluarga warna saja.
    const qualificationScale = [
        { label: 'Setingkat lebih tinggi', color: '#0D542B' },
        { label: 'Tingkat yang sama', color: '#2F7D4A' },
        { label: 'Setingkat lebih rendah', color: '#EF4444' },
        { label: 'Tidak perlu pendidikan tinggi', color: '#B91C1C' },
    ];
    const qualificationScaleGraphic = qualificationScale.flatMap((item, index) => {
        const top = index * 42;
        return [
            {
                type: 'rect',
                left: 30,
                top,
                shape: { width: 22, height: 42 },
                style: { fill: item.color, stroke: '#1E293B', lineWidth: 1 },
            },
            {
                type: 'text',
                left: 60,
                top: top + 13,
                style: { text: item.label, fill: '#1E293B', font: '500 11px Inter, sans-serif' },
            },
        ];
    });

    return {
        ...baseOption(records.value.length),
        legend: {
            show: false,
        },
        grid: { left: 24, right: 250, top: 20, bottom: 36, containLabel: true },
        xAxis: {
            type: 'value',
            max: 100,
            axisLabel: { formatter: '{value}%' },
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
        },
        yAxis: {
            type: 'category',
            inverse: true,
            data: labels,
            axisLabel: { color: '#334155', fontSize: 11, width: 190, overflow: 'break' },
        },
        graphic: [{
            type: 'group',
            right: 18,
            top: 'middle',
            children: [
                {
                    type: 'text',
                    left: 0,
                    top: 84,
                    rotation: -Math.PI / 2,
                    style: { text: 'Kualifikasi', fill: '#0F172A', font: 'bold 11px Inter, sans-serif', textAlign: 'center' },
                },
                ...qualificationScaleGraphic,
            ],
        }],
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'item',
            formatter: ({ data }) => {
                if (!data) return '';
                return `<strong>${escapeHtml(data.prodi)}</strong><br>${escapeHtml(data.category)}: <strong>${data.count} alumni (${data.value}%)</strong><br>Total alumni: n=${data.total}`;
            },
        },
        series: seriesList,
    };
});

const stackedTable = computed(() => ({
    columns: [
        { key: 'prodi', label: 'Program Studi' },
        { key: 'total', label: 'Total Alumni (n)', align: 'right' },
        { key: 'details', label: 'Sebaran Kualifikasi', align: 'left' },
    ],
    rows: prodis.value.map((prodi) => {
        const rows = records.value.filter((r) => (r.nama_prodi || 'Tanpa prodi') === prodi);
        const details = categories.value.map((cat) => {
            const cnt = rows.filter((r) => recordCategory(r) === cat).length;
            return `${cat}: ${cnt}`;
        }).join(' · ');

        return {
            prodi,
            total: formatNumber(rows.length, 0),
            details,
        };
    }),
}));

// Analisis dinamis Gambar 4.14 berdasarkan urutan capaian prodi
const dynamicVerticalAnalysis = computed(() => {
    const list = prodiVerticalMatchData.value;
    if (list.length === 0) {
        return {
            title: 'Kilas Analisis Vertical Match (Gambar 4.14)',
            statusBadge: 'Gambar 4.14',
            badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
            lead: 'Data keselarasan vertikal sedang dimuat.',
            detail: '',
        };
    }

    const top = list[0];
    const bottom = list[list.length - 1];
    const rincian = list.map((i, idx) => `${idx + 1}. ${i.prodi}: ${i.percentage}%`).join(', ');

    return {
        title: 'Kilas Analisis Vertical Match (Gambar 4.14)',
        statusBadge: 'Gambar 4.14 Dinamis',
        badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
        lead: `Data pada Gambar 4.14 menunjukkan keselarasan tingkat pendidikan dengan pekerjaan (vertical match) per program studi. Capaian keselarasan tertinggi diraih oleh ${top.prodi} (${top.percentage}%), sedangkan capaian terendah tercatat pada ${bottom.prodi} (${bottom.percentage}%).`,
        detail: `Rincian persentase alumni yang bekerja pada tingkat pendidikan yang sama untuk seluruh program studi secara berurutan: ${rincian}.`,
    };
});

// Konfigurasi views untuk kontainer terpadu
const views = computed(() => [
    {
        key: 'vertical_match',
        chartType: 'percentage_line',
        tabLabel: 'Vertical Match Garis',
        title: 'Keselarasan tingkat pendidikan sesuai pekerjaan per prodi (Diagram Garis)',
        description: 'Diagram garis respon alumni terhadap tingkat pendidikan sesuai pekerjaan per program studi (mengadopsi Gambar 4.14 Laporan Tracer Study UKDW).',
        option: verticalMatchOption.value,
        tableData: verticalMatchTable.value,
        height: 410,
        analysis: dynamicVerticalAnalysis.value,
    },
    {
        key: 'stacked_kualifikasi',
        chartType: 'stacked_horizontal_100',
        tabLabel: 'Komposisi Kualifikasi',
        title: 'Komposisi kualifikasi pendidikan per program studi',
        description: 'Sebaran lengkap pilihan jawaban kuesioner: Setingkat Lebih Tinggi, Sama, Setingkat Lebih Rendah, dan Tidak Perlu Pendidikan Tinggi.',
        option: stackedOption.value,
        tableData: stackedTable.value,
        height: Math.max(380, prodis.value.length * 36 + 120),
        analysis: {
            title: 'Kilas Komposisi Kualifikasi Kerja',
            statusBadge: 'Distribusi Lengkap',
            badgeClass: 'bg-slate-100 text-slate-700',
            lead: 'Evaluasi kualifikasi pekerjaan sarjana secara proporsional di seluruh jurusan.',
            detail: 'Dominasi jawaban "Sama" dan "Setingkat Lebih Tinggi" menandakan bahwa profil pekerjaan alumni memerlukan bekal keilmuan perguruan tinggi.',
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
        indicator-badge="Indikator 16"
        @select="emit('select', $event)"
        @reset="emit('reset')"
    />
</template>
