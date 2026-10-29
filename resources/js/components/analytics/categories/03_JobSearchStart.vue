<script setup>
/**
 * Komponen Indikator 03: Waktu Mulai Mencari Pekerjaan (F3).
 *
 * Mengelola visualisasi waktu mulai mencari kerja secara mandiri:
 * 1. Stacked Column Chart: Komposisi waktu mulai mencari kerja sebelum, setelah, atau tidak mencari kerja.
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
        .filter((item) => {
            const label = seriesLabel(item).toLowerCase();
            return !label.includes('sebelum') && !label.includes('tidak mencari');
        })
        .reduce((acc, curr) => acc + seriesMetric(curr), 0);
});

// Total jawaban F3 menjadi denominator tunggal untuk seluruh rentang waktu.
const totalTimingCount = computed(() => series.value.reduce((total, item) => total + seriesMetric(item), 0));

// Jumlah alumni yang memilih tidak mencari pekerjaan dipisahkan dari rentang waktu.
const notSearchingCount = computed(() => series.value
    .filter((item) => seriesLabel(item).toLowerCase().includes('tidak mencari'))
    .reduce((acc, curr) => acc + seriesMetric(curr), 0));

const beforePercentage = computed(() => {
    const total = totalTimingCount.value;
    return total > 0 ? ((beforeCount.value / total) * 100).toFixed(1) : '0';
});

const afterPercentage = computed(() => {
    const total = totalTimingCount.value;
    return total > 0 ? ((afterCount.value / total) * 100).toFixed(1) : '0';
});

// Mengubah label mentah menjadi tiga rentang waktu yang konsisten.
const classifyTimingRange = (rawLabel) => {
    const label = String(rawLabel || '').toLowerCase();
    if (label.includes('tidak mencari')) return { phase: 'Tidak mencari kerja', range: '' };
    const months = Number(label.match(/(\d+)/)?.[1] || 0);
    if (label.includes('>')) return { phase: label.includes('sebelum') ? 'Sebelum lulus' : 'Setelah lulus', range: '> 6 bulan' };
    if (months <= 2) return { phase: label.includes('sebelum') ? 'Sebelum lulus' : 'Setelah lulus', range: '< 2 bulan' };
    if (months <= 6) return { phase: label.includes('sebelum') ? 'Sebelum lulus' : 'Setelah lulus', range: '2–6 bulan' };
    return { phase: label.includes('sebelum') ? 'Sebelum lulus' : 'Setelah lulus', range: '> 6 bulan' };
};

// Agregasi rentang sebelum/setelah lulus serta kategori tidak mencari kerja.
const timingRangeRows = computed(() => {
    const order = [
        ['Sebelum lulus', '< 2 bulan'],
        ['Sebelum lulus', '2–6 bulan'],
        ['Sebelum lulus', '> 6 bulan'],
        ['Setelah lulus', '< 2 bulan'],
        ['Setelah lulus', '2–6 bulan'],
        ['Setelah lulus', '> 6 bulan'],
        ['Tidak mencari kerja', ''],
    ];
    const grouped = new Map(order.map(([phase, range]) => [`${phase}|${range}`, { phase, range, count: 0 }]));

    series.value.forEach((item) => {
        const classification = classifyTimingRange(seriesLabel(item));
        const key = `${classification.phase}|${classification.range}`;
        if (!grouped.has(key)) grouped.set(key, { ...classification, count: 0 });
        grouped.get(key).count += seriesMetric(item);
    });

    return [...grouped.values()]
        .filter((item) => item.count > 0)
        .map((item) => ({
            ...item,
            label: item.range ? `${item.phase}\n${item.range}` : item.phase,
            percentage: totalTimingCount.value > 0 ? Number(((item.count / totalTimingCount.value) * 100).toFixed(1)) : 0,
        }));
});

// Diagram batang vertikal biru menampilkan distribusi rentang waktu secara langsung.
const timingRangeOption = computed(() => ({
    ...baseOption(validResponses.value),
    themePalette: ['#2F6DB0'],
    grid: { left: 64, right: 36, top: 28, bottom: 92, containLabel: true },
    xAxis: {
        type: 'category',
        data: timingRangeRows.value.map((item) => item.label),
        axisLabel: { color: '#334155', fontWeight: '600', interval: 0, lineHeight: 16 },
    },
    yAxis: {
        type: 'value',
        max: 100,
        name: 'Persentase Alumni',
        nameLocation: 'middle',
        nameGap: 46,
        axisLabel: { formatter: '{value}%' },
        splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
    },
    tooltip: {
        ...baseOption().tooltip,
        trigger: 'axis',
        axisPointer: { type: 'shadow' },
        formatter: (params) => {
            const item = timingRangeRows.value[params[0]?.dataIndex];
            return item ? `<strong>${escapeHtml(item.phase)}${item.range ? ` · ${escapeHtml(item.range)}` : ''}</strong><br>Jumlah: <strong>${formatNumber(item.count, 0)} alumni</strong><br>Proporsi: <strong>${formatNumber(item.percentage, 1)}%</strong>` : '';
        },
    },
    series: [{
        name: 'Alumni',
        type: 'bar',
        barMaxWidth: 52,
        itemStyle: { color: '#2F6DB0', borderRadius: [5, 5, 0, 0] },
        data: timingRangeRows.value.map((item) => ({ value: item.percentage, count: item.count, phase: item.phase, range: item.range })),
        label: {
            show: true,
            position: 'top',
            formatter: ({ data }) => `${formatNumber(data.value, 1)}%`,
            color: '#1E4F82',
            fontWeight: 'bold',
        },
    }],
}));

// Tabel ringkas mengikuti urutan rentang waktu pada diagram batang.
const timingRangeTable = computed(() => ({
    columns: [
        { key: 'label', label: 'Rentang Waktu Mulai Mencari' },
        { key: 'count', label: 'Jumlah Alumni', align: 'right' },
        { key: 'percentage', label: 'Persentase', align: 'right' },
    ],
    rows: timingRangeRows.value.map((item) => ({
        label: item.range ? `${item.phase} · ${item.range}` : item.phase,
        count: formatNumber(item.count, 0),
        percentage: `${formatNumber(item.percentage, 1)}%`,
    })),
}));

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
        key: 'range',
        tabLabel: 'Rentang Waktu',
        title: 'Sebaran rentang waktu mulai mencari pekerjaan',
        description: 'Batang biru membandingkan proporsi alumni berdasarkan rentang waktu sebelum lulus, setelah lulus, dan yang tidak mencari kerja.',
        option: timingRangeOption.value,
        tableData: timingRangeTable.value,
        height: 420,
        analysis: {
            title: 'Kilas Kesiapan Karir Alumni UKDW',
            statusBadge: 'Perilaku Kesiapan',
            badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
            lead: `Sebanyak ${formatNumber(beforeCount.value, 0)} alumni (${beforePercentage.value}%) mulai mencari kerja sebelum lulus, ${formatNumber(afterCount.value, 0)} alumni (${afterPercentage.value}%) setelah lulus, dan ${formatNumber(notSearchingCount.value, 0)} alumni tidak mencari kerja.`,
            detail: 'Rentang waktu diseragamkan menjadi < 2 bulan, 2–6 bulan, dan > 6 bulan agar perbedaan momentum pencarian kerja antar fase dapat dibandingkan secara konsisten.',
            reason: 'Dipilih sebagai Vertical Bar Chart agar frekuensi setiap rentang waktu terbaca langsung, dengan satuan dan batas rentang yang konsisten.',
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
