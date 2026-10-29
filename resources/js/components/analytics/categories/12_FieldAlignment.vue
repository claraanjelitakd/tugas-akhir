<script setup>
/**
 * Komponen Indikator 12: Hubungan Bidang Studi dengan Pekerjaan (Horizontal Match - F14).
 *
 * Mengelola visualisasi keselarasan kurikulum bidang studi dengan pekerjaan alumni:
 * 1. Stacked Bar Antarprodi: Komposisi kategori keselarasan dengan pemisah visual yang ringan.
 * 2. Line Chart: Tren Persentase Horizontal Match (Sangat Erat + Erat) per Program Studi
 * 3. Donut Chart: Distribusi Jawaban Kuesioner Universitas
 */
import { computed } from 'vue';
import AnalyticsTabbedContainer from '../AnalyticsTabbedContainer.vue';
import {
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

// Palet warna bertingkat institusional UKDW yang harmonis dan konsisten
const getAlignmentColor = (label) => {
    const l = String(label || '').toLowerCase().trim();
    if (l.includes('sangat erat')) return '#0D542B';
    if (l === 'erat' || (l.includes('erat') && !l.includes('kurang') && !l.includes('cukup') && !l.includes('tidak'))) {
        return '#2F7D4A';
    }
    if (l.includes('cukup')) return '#78B18B';
    if (l.includes('kurang')) return '#B7D7C0';
    if (l.includes('tidak')) return '#DCEBE0';
    return '#78B18B';
};

// -----------------------------------------------------------------------------
// 1. STACKED BAR ANTARPRODI (Label Persentase dan Urutan Kategori Konsisten)
// -----------------------------------------------------------------------------
const prodis = computed(() => [...new Set(records.value.map((r) => r.nama_prodi || 'Tanpa prodi'))]);
const categories = computed(() => [...new Set(series.value.map((s) => s.label))]);

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
            barMaxWidth: 28,
            label: {
                show: true,
                position: 'inside',
                formatter: (params) => params.value > 0 ? `${params.value}%` : '',
                fontSize: 10,
                color: '#FFFFFF',
                fontWeight: 'bold',
            },
            itemStyle: {
                color: getAlignmentColor(cat),
                opacity: selected ? 1 : 0.22,
                borderColor: 'transparent',
                borderWidth: 0,
            },
            data: catData,
        };
    });

    const labels = listProdis.map((p) => `${p} (n=${prodiTotals[p]})`);

    return {
        ...baseOption(records.value.length),
        legend: {
            data: cats,
            bottom: 0,
            icon: 'circle',
            textStyle: { color: '#334155', fontSize: 11, fontWeight: 600 },
        },
        grid: { left: 24, right: 36, top: 20, bottom: 56, containLabel: true },
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
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'item',
            formatter: ({ data }) => {
                if (!data) return '';
                return `<strong>${escapeHtml(data.prodi)}</strong><br>Keselarasan: <strong>${escapeHtml(data.category)}</strong><br>Jumlah: <strong>${data.count} alumni</strong> (dari total n=${data.total})<br>Proporsi: <strong>${data.value}%</strong>`;
            },
        },
        series: seriesList,
    };
});

const stackedTable = computed(() => ({
    columns: [
        { key: 'prodi', label: 'Program Studi' },
        { key: 'total', label: 'Total Alumni (n)', align: 'right' },
        { key: 'erat', label: 'Erat / Sangat Erat', align: 'right' },
        { key: 'cukup', label: 'Cukup Erat', align: 'right' },
        { key: 'kurang', label: 'Kurang / Tidak Sama Sekali', align: 'right' },
    ],
    rows: prodis.value.map((prodi) => {
        const rows = records.value.filter((r) => (r.nama_prodi || 'Tanpa prodi') === prodi);
        const total = rows.length;
        const countErat = rows.filter((r) => {
            const ans = String(recordCategory(r) || '').toLowerCase();
            return ans.includes('sangat erat') || ans === 'erat';
        }).length;
        const countCukup = rows.filter((r) => String(recordCategory(r) || '').toLowerCase().includes('cukup')).length;
        const countKurang = total - (countErat + countCukup);

        return {
            prodi,
            total: formatNumber(total, 0),
            erat: `${countErat} (${formatPercentage(total ? (countErat / total) * 100 : 0)})`,
            cukup: `${countCukup} (${formatPercentage(total ? (countCukup / total) * 100 : 0)})`,
            kurang: `${countKurang} (${formatPercentage(total ? (countKurang / total) * 100 : 0)})`,
        };
    }),
}));

// -----------------------------------------------------------------------------
// 2. GRAFIK GARIS: Horizontal Match (Persentase Selaras) per Program Studi
// -----------------------------------------------------------------------------
const prodiMatchLineData = computed(() => {
    return prodis.value.map((prodi) => {
        const rows = records.value.filter((r) => (r.nama_prodi || 'Tanpa prodi') === prodi);
        const total = rows.length;
        const matches = rows.filter((r) => {
            const ans = String(recordCategory(r) || '').toLowerCase();
            return ans.includes('sangat erat') || ans === 'erat';
        }).length;
        const percentage = total > 0 ? Number(((matches / total) * 100).toFixed(1)) : 0;
        return {
            prodi,
            count: matches,
            total,
            percentage,
        };
    }).sort((a, b) => b.percentage - a.percentage);
});

const lineMatchOption = computed(() => {
    const list = prodiMatchLineData.value;
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
            name: 'Persentase Horizontal Match (%)',
            max: 100,
            axisLabel: { formatter: '{value}%' },
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
        },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'axis',
            formatter: (params) => {
                const item = list[params[0]?.dataIndex ?? 0] || {};
                return `<strong>${escapeHtml(item.prodi)}</strong><br>Tingkat Keselarasan Horizontal: <strong>${item.percentage}%</strong><br>Jumlah alumni selaras: <strong>${item.count} dari ${item.total} alumni</strong>`;
            },
        },
        series: [{
            name: 'Horizontal Match',
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

const lineMatchTable = computed(() => ({
    columns: [
        { key: 'prodi', label: 'Program Studi' },
        { key: 'percentageDisplay', label: 'Tingkat Keselarasan (%)', align: 'right' },
        { key: 'countDisplay', label: 'Alumni Selaras / Total', align: 'right' },
    ],
    rows: prodiMatchLineData.value.map((i) => ({
        prodi: i.prodi,
        percentageDisplay: `${i.percentage}%`,
        countDisplay: `${i.count} / ${i.total} alumni`,
    })),
}));

// -----------------------------------------------------------------------------
// 3. DONUT CHART: Distribusi Kuesioner Tingkat Universitas
// -----------------------------------------------------------------------------
const univDonutOption = computed(() => {
    const data = series.value.map((item, index) => {
        const label = seriesLabel(item);
        const count = seriesMetric(item);
        const selected = !props.selectedLabel || props.selectedLabel === label;
        return {
            name: label,
            value: count,
            percentage: item.percentage || 0,
            itemStyle: {
                color: getAlignmentColor(label, index),
                opacity: selected ? 1 : 0.22,
            },
        };
    });

    return {
        ...baseOption(validResponses.value),
        legend: {
            orient: 'horizontal',
            bottom: 0,
            icon: 'circle',
            textStyle: { color: '#334155', fontSize: 11, fontWeight: 600 },
        },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'item',
            formatter: ({ data }) => `<strong>${escapeHtml(data.name)}</strong><br>Jumlah: <strong>${formatNumber(data.value, 0)} alumni</strong><br>Proporsi: <strong>${formatPercentage(data.percentage)}</strong>`,
        },
        series: [{
            name: 'Distribusi Keselarasan',
            type: 'pie',
            radius: ['44%', '70%'],
            center: ['50%', '46%'],
            avoidLabelOverlap: true,
            itemStyle: {
                borderRadius: 4,
                borderColor: '#FFFFFF',
                borderWidth: 2,
            },
            label: {
                show: true,
                formatter: ({ data }) => `${formatPercentage(data.percentage)}`,
                fontSize: 11,
                fontWeight: 'bold',
                color: '#1E293B',
            },
            data,
        }],
    };
});

const univDonutTable = computed(() => ({
    columns: [
        { key: 'category', label: 'Tingkat Keselarasan Bidang (F14)' },
        { key: 'count', label: 'Jumlah Alumni (n)', align: 'right' },
        { key: 'percentage', label: 'Persentase', align: 'right' },
    ],
    rows: series.value.map((item) => ({
        category: seriesLabel(item),
        count: formatNumber(seriesMetric(item), 0),
        percentage: formatPercentage(item.percentage || 0),
    })),
}));

// Analisis dinamis Horizontal Match per Program Studi
const dynamicHorizontalAnalysis = computed(() => {
    const list = prodiMatchLineData.value;
    if (list.length === 0) {
        return {
            title: 'Kilas Metodologi & Capaian Horizontal Match (IKU 1)',
            statusBadge: 'Rumus Resmi Dikti',
            badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
            lead: 'Data keselarasan horizontal sedang dimuat.',
            detail: '',
        };
    }

    const top = list[0];
    const bottom = list[list.length - 1];
    const rincian = list.map((i, idx) => `${idx + 1}. ${i.prodi}: ${i.percentage}%`).join(', ');

    return {
        title: 'Kilas Metodologi & Capaian Horizontal Match (IKU 1)',
        statusBadge: 'Rumus Resmi Dikti',
        badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
        lead: `Metodologi Perhitungan: Persentase Horizontal Match dihitung dari total alumni yang menyatakan pekerjaannya "Sangat Erat" atau "Erat" dengan bidang ilmu dibagi seluruh responden program studi terkait × 100% (baku mutu IKU 1). Capaian tertinggi diraih oleh ${top.prodi} (${top.percentage}%), sedangkan capaian terendah tercatat pada ${bottom.prodi} (${bottom.percentage}%).`,
        detail: `Rincian persentase keselarasan horizontal untuk seluruh program studi secara berurutan: ${rincian}.`,
    };
});

// Konfigurasi views untuk kontainer terpadu
const views = computed(() => [
    {
        key: 'stacked',
        chartType: 'stacked_horizontal_100',
        tabLabel: 'Komposisi Antarprodi',
        title: 'Komposisi keselarasan bidang per program studi',
        description: 'Perbandingan komposisi keselarasan bidang kerja antarprogram studi dengan persentase pada setiap segmen.',
        option: stackedOption.value,
        tableData: stackedTable.value,
        height: Math.max(380, prodis.value.length * 36 + 120),
        analysis: {
            title: 'Kilas Keselarasan Kurikulum & Karier',
            statusBadge: 'Distribusi Keselarasan',
            badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
            lead: series.value[0]?.label
                ? `Kategori keselarasan horizontal dominan adalah "${series.value[0].label}" dengan proporsi ${formatPercentage(series.value[0].percentage)} (${series.value[0].count} alumni).`
                : 'Data keselarasan bidang pekerjaan tersaji lengkap.',
            detail: 'Setiap segmen menunjukkan proporsi alumni yang menilai bidang kerjanya selaras dengan keilmuan yang ditempuh selama masa studi di UKDW.',
        },
    },
    {
        key: 'line_match',
        chartType: 'percentage_line',
        tabLabel: 'Horizontal Match Garis',
        title: 'Tren horizontal match (persentase selaras) per program studi',
        description: 'Grafik garis persentase alumni yang menyatakan bidang pekerjaannya selaras (Sangat Erat dan Erat) dengan disiplin ilmu kuliah.',
        option: lineMatchOption.value,
        tableData: lineMatchTable.value,
        height: 400,
        analysis: dynamicHorizontalAnalysis.value,
    },
    {
        key: 'univ_distribution',
        chartType: 'donut',
        tabLabel: 'Distribusi Universitas',
        title: 'Hubungan bidang studi dengan pekerjaan tingkat universitas',
        description: 'Distribusi jawaban kuesioner resmi instrumen F14 seluruh universitas beserta tabulasi data alumni per kategori.',
        option: univDonutOption.value,
        tableData: univDonutTable.value,
        height: 380,
        analysis: {
            title: 'Kilas Kuesioner F14 Universitas',
            statusBadge: 'Instrumen F14',
            badgeClass: 'bg-slate-100 text-slate-700',
            lead: 'Distribusi menyeluruh mencerminkan keselarasan horisontal lulusan UKDW secara agregat.',
            detail: 'Alumni pada kategori erat dan sangat erat menjadi bukti keberhasilan transfer kompetensi program sarjana ke lingkungan kerja nyata.',
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
        indicator-badge="Indikator 12"
        @select="emit('select', $event)"
        @reset="emit('reset')"
    />
</template>
