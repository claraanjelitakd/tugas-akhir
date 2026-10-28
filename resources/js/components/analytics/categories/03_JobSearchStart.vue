<script setup>
/**
 * Komponen Indikator 03: Waktu Mulai Mencari Pekerjaan (F3).
 *
 * Mengelola visualisasi waktu mulai mencari kerja secara mandiri:
 * 1. Diverging Bar: Posisi waktu terhadap kelulusan dengan skala natural (<2 bulan, <=6 bulan, dsb) tanpa tanda +/-.
 * 2. Bar Chart: Distribusi frekuensi kategori periode waktu mulai mencari pekerjaan.
 * Dilengkapi narasi analisis komprehensif berstandar laporan resmi Tracer Study UKDW.
 */
import { computed } from 'vue';
import AnalyticsTabbedContainer from '../AnalyticsTabbedContainer.vue';
import {
    baseOption,
    escapeHtml,
    formatNumber,
    formatPercentage,
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

/**
 * Helper konversi label mentah F3 menjadi skala natural tanpa tanda +/-
 * Contoh: < 2 bulan, 3-5 bulan, <= 6 bulan, > 6 bulan
 */
const formatNaturalScale = (label) => {
    if (!label) return '-';
    const match = String(label).match(/(\d+)/);
    const num = match ? parseInt(match[1], 10) : null;
    const lower = String(label).toLowerCase();
    const isBefore = lower.includes('sebelum');
    const isAfter = lower.includes('setelah') || lower.includes('sesudah');

    if (lower.includes('saat') || lower.includes('tepat') || num === 0) {
        return 'Saat kelulusan (0 bln)';
    }

    if (num !== null) {
        let scaleText = `${num} bulan`;
        if (num <= 2) {
            scaleText = '< 2 bulan';
        } else if (num === 6) {
            scaleText = '<= 6 bulan';
        } else if (num > 6) {
            scaleText = '> 6 bulan';
        }

        if (isBefore) {
            return `${scaleText} sebelum lulus`;
        }
        if (isAfter) {
            return `${scaleText} setelah lulus`;
        }
        return scaleText;
    }

    return label;
};

/**
 * Format label ringkas untuk sumbu yAxis chart
 */
const formatShortAxisLabel = (label) => {
    if (!label) return '-';
    const match = String(label).match(/(\d+)/);
    const num = match ? parseInt(match[1], 10) : null;
    const lower = String(label).toLowerCase();

    if (lower.includes('saat') || num === 0) return 'Saat lulus';
    if (num !== null) {
        if (num <= 2) return '< 2 bln';
        if (num === 6) return '<= 6 bln';
        if (num > 6) return '> 6 bln';
        return `${num} bln`;
    }
    return label;
};

// Hitung total sebelum dan setelah kelulusan
const beforeCount = computed(() => {
    return series.value
        .filter((item) => seriesLabel(item).toLowerCase().includes('sebelum'))
        .reduce((acc, curr) => acc + seriesMetric(curr), 0);
});

const afterCount = computed(() => {
    return series.value
        .filter((item) => !seriesLabel(item).toLowerCase().includes('sebelum'))
        .reduce((acc, curr) => acc + seriesMetric(curr), 0);
});

const beforePercentage = computed(() => {
    const total = beforeCount.value + afterCount.value;
    return total > 0 ? ((beforeCount.value / total) * 100).toFixed(1) : '0';
});

const afterPercentage = computed(() => {
    const total = beforeCount.value + afterCount.value;
    return total > 0 ? ((afterCount.value / total) * 100).toFixed(1) : '0';
});

// -----------------------------------------------------------------------------
// 1. DIVERGING BAR: Posisi Waktu terhadap Kelulusan (Skala Natural Tanpa Tanda +/-)
// -----------------------------------------------------------------------------
const divergingData = computed(() => {
    return series.value.map((item) => {
        const rawLabel = seriesLabel(item);
        const naturalLabel = formatNaturalScale(rawLabel);
        const shortLabel = formatShortAxisLabel(rawLabel);
        const isBefore = rawLabel.toLowerCase().includes('sebelum');
        const count = seriesMetric(item);
        const val = count * (isBefore ? -1 : 1);
        const selected = !props.selectedLabel || props.selectedLabel === rawLabel;
        return {
            name: rawLabel,
            naturalLabel,
            shortLabel,
            displayLabel: `${shortLabel} (${isBefore ? 'Sebelum' : 'Setelah'})`,
            value: val,
            absoluteValue: count,
            percentage: validResponses.value > 0 ? ((count / validResponses.value) * 100).toFixed(1) : 0,
            itemStyle: {
                color: isBefore ? '#64748B' : '#0D542B',
                opacity: selected ? 1 : 0.22,
                borderRadius: isBefore ? [4, 0, 0, 4] : [0, 4, 4, 0],
            },
        };
    });
});

const divergingOption = computed(() => ({
    ...baseOption(validResponses.value),
    grid: { left: 110, right: 36, top: 28, bottom: 48, containLabel: true },
    xAxis: {
        type: 'value',
        name: 'Jumlah Alumni',
        nameLocation: 'center',
        nameGap: 30,
        axisLabel: { formatter: (value) => Math.abs(value) },
        splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
    },
    yAxis: {
        type: 'category',
        data: divergingData.value.map((i) => i.displayLabel),
        axisLabel: {
            fontSize: 12,
            fontWeight: '600',
            color: '#334155',
        },
    },
    tooltip: {
        ...baseOption().tooltip,
        formatter: ({ data }) => `<strong>${escapeHtml(data.naturalLabel)}</strong><br>Jumlah: <strong>${formatNumber(data.absoluteValue, 0)} alumni (${data.percentage}%)</strong><br>Kategori: ${data.value < 0 ? 'Inisiatif sebelum kelulusan' : 'Setelah dinyatakan lulus'}`,
    },
    series: [{
        name: 'Alumni',
        type: 'bar',
        data: divergingData.value,
        label: {
            show: true,
            formatter: ({ data }) => formatNumber(data.absoluteValue, 0),
            position: 'inside',
            color: '#FFFFFF',
            fontWeight: 'bold',
            fontSize: 11,
        },
    }],
}));

const divergingTable = computed(() => ({
    columns: [
        { key: 'naturalLabel', label: 'Rentang Waktu Mulai Mencari' },
        { key: 'timing', label: 'Fase Kelulusan' },
        { key: 'count', label: 'Jumlah Alumni', align: 'right' },
        { key: 'percentage', label: 'Persentase', align: 'right' },
    ],
    rows: divergingData.value.map((item) => ({
        naturalLabel: item.naturalLabel,
        timing: item.value < 0 ? 'Sebelum Kelulusan' : 'Setelah Kelulusan',
        count: formatNumber(item.absoluteValue, 0),
        percentage: `${item.percentage}%`,
    })),
}));

// -----------------------------------------------------------------------------
// 2. BAR CHART: Distribusi Kategori Waktu Mulai (Skala Natural Terurut)
// -----------------------------------------------------------------------------
const sortedCategories = computed(() => {
    return [...series.value]
        .map((item) => {
            const rawLabel = seriesLabel(item);
            const naturalLabel = formatNaturalScale(rawLabel);
            const val = seriesMetric(item);
            const selected = !props.selectedLabel || props.selectedLabel === rawLabel;
            return {
                name: rawLabel,
                naturalLabel,
                value: val,
                percentage: validResponses.value > 0 ? ((val / validResponses.value) * 100).toFixed(1) : 0,
                itemStyle: {
                    color: rawLabel.toLowerCase().includes('sebelum') ? '#64748B' : '#0D542B',
                    opacity: selected ? 1 : 0.22,
                    borderRadius: [0, 4, 4, 0],
                },
            };
        })
        .sort((a, b) => b.value - a.value);
});

const distributionBarOption = computed(() => {
    const rows = [...sortedCategories.value].reverse();
    return {
        ...baseOption(validResponses.value),
        grid: { left: 160, right: 60, top: 16, bottom: 32, containLabel: true },
        xAxis: { type: 'value', name: 'Jumlah Alumni', splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } } },
        yAxis: {
            type: 'category',
            data: rows.map((i) => i.naturalLabel),
            axisLabel: { fontSize: 11, color: '#334155' },
        },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            formatter: (params) => {
                const item = rows[params[0]?.dataIndex];
                if (!item) return '';
                return `<strong>${escapeHtml(item.naturalLabel)}</strong><br>Jumlah: <strong>${formatNumber(item.value, 0)} alumni</strong><br>Proporsi: <strong>${item.percentage}%</strong>`;
            },
        },
        series: [{
            name: 'Jumlah Alumni',
            type: 'bar',
            barMaxWidth: 24,
            data: rows,
            label: {
                show: true,
                position: 'right',
                formatter: ({ data }) => `${formatNumber(data.value, 0)} (${data.percentage}%)`,
                fontSize: 10,
                color: '#475569',
            },
        }],
    };
});

const distributionBarTable = computed(() => ({
    columns: [
        { key: 'naturalLabel', label: 'Kategori Waktu Mulai Mencari' },
        { key: 'count', label: 'Jumlah Alumni', align: 'right' },
        { key: 'percentage', label: 'Proporsi', align: 'right' },
    ],
    rows: sortedCategories.value.map((item) => ({
        naturalLabel: item.naturalLabel,
        count: formatNumber(item.value, 0),
        percentage: `${item.percentage}%`,
    })),
}));

// Konfigurasi views untuk kontainer terpadu
const views = computed(() => [
    {
        key: 'diverging',
        tabLabel: 'Posisi Kelulusan',
        title: 'Posisi waktu mulai mencari kerja terhadap kelulusan',
        description: 'Perbandingan kelompok alumni yang berinisiatif mencari kerja sebelum wisuda (kiri) versus setelah dinyatakan lulus (kanan).',
        option: divergingOption.value,
        tableData: divergingTable.value,
        height: 380,
        analysis: {
            title: 'Kilas Kesiapan Karir Alumni UKDW',
            statusBadge: 'Perilaku Kesiapan',
            badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
            lead: `Data menunjukkan mayoritas lulusan, yakni sebanyak ${formatNumber(beforeCount.value, 0)} alumni (${beforePercentage.value}%), telah aktif mencari kerja sebelum resmi dinyatakan lulus/wisuda. Sementara itu, ${formatNumber(afterCount.value, 0)} alumni (${afterPercentage.value}%) baru memulai pencarian setelah kelulusan.`,
            detail: 'Inisiatif pencarian kerja sebelum wisuda (terbanyak pada rentang 4 sampai 6 bulan sebelum lulus) membuktikan tingginya proaktivitas alumni dalam memanfaatkan masa penyusunan tugas akhir/magang untuk menjajaki bursa kerja. Hal ini berkontribusi langsung pada pemendekan masa tunggu perolehan kerja pertama lulusan UKDW di dunia industri.',
        },
    },
    {
        key: 'distribution',
        tabLabel: 'Distribusi Rentang Waktu',
        title: 'Distribusi rentang waktu mulai mencari kerja',
        description: 'Urutan frekuensi rentang waktu mulai mencari pekerjaan dari skala terpadu.',
        option: distributionBarOption.value,
        tableData: distributionBarTable.value,
        height: 380,
        analysis: {
            title: 'Kilas Pola Momentum Pencarian Kerja',
            statusBadge: 'Sebaran Periode',
            badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
            lead: sortedCategories.value[0]?.naturalLabel
                ? `Pola paling dominan terjadi pada periode "${sortedCategories.value[0].naturalLabel}" dengan keterlibatan ${formatNumber(sortedCategories.value[0].value, 0)} responden (${sortedCategories.value[0].percentage}%).`
                : 'Distribusi kategori waktu terdata lengkap.',
            detail: 'Konsentrasi pencarian kerja pada semester akhir menunjukkan urgensi optimalisasi program pembekalan karir kampus (seperti bursa karir, pelatihan wawancara, dan campus hiring) yang diselenggarakan tepat 1 semester menjelang wisuda.',
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
        indicator-badge="Indikator 03"
        @select="emit('select', $event)"
        @reset="emit('reset')"
    />
</template>
