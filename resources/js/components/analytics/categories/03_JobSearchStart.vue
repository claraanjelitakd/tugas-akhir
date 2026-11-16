<script setup>
/**
 * Komponen Indikator 03: Waktu Mulai Mencari Pekerjaan (F3).
 *
 * Mengelola visualisasi waktu mulai mencari kerja secara mandiri:
 * 1. Stacked Bar Chart: Komposisi jumlah fase sebelum, setelah, atau tidak mencari kerja.
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

// Hitung total sebelum dan setelah kelulusan
const beforeCount = computed(() => {
    return Number(props.chart?.summary?.before_count || 0);
});

const afterCount = computed(() => {
    return Number(props.chart?.summary?.after_count || 0);
});

// Total jawaban F3 menjadi denominator tunggal untuk seluruh rentang waktu.
const totalTimingCount = computed(() => Number(props.chart?.summary?.response_count || 0));

// Jumlah alumni yang memilih tidak mencari pekerjaan dipisahkan dari rentang waktu.
const notSearchingCount = computed(() => series.value
    .length > 0 ? Number(props.chart?.summary?.not_searching_count || 0) : 0);

const beforePercentage = computed(() => {
    return Number(props.chart?.summary?.before_percentage || 0).toFixed(1);
});

const afterPercentage = computed(() => {
    return Number(props.chart?.summary?.after_percentage || 0).toFixed(1);
});

// Menggunakan fase dan rentang yang sudah dinormalisasi backend.
const classifyTimingRange = (item) => {
    return {
        phase: item?.phase || seriesLabel(item),
        range: item?.range || '',
        segmentKey: item?.segment_key || item?.phase_key || 'unknown',
    };
};

// Agregasi rentang sebelum/setelah lulus serta kategori tidak mencari kerja.
const timingRangeRows = computed(() => {
    if (props.chart?.timing_range_rows) {
        return props.chart.timing_range_rows;
    }

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
        const classification = classifyTimingRange(item);
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

// Diagram batang vertikal hijau menampilkan distribusi rentang waktu secara langsung.
const timingRangeOption = computed(() => ({
    ...baseOption(validResponses.value),
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
        itemStyle: { color: '#0D542B', borderRadius: [5, 5, 0, 0] },
        data: timingRangeRows.value.map((item) => ({ value: item.percentage, count: item.count, phase: item.phase, range: item.range })),
        label: {
            show: true,
            position: 'top',
            formatter: ({ data }) => `${formatNumber(data.value, 1)}%`,
            color: '#0D542B',
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

// Segmen warna tetap terbatas pada fase dan rentang yang telah disepakati.
const timingStackSegments = [
    { key: 'before_graduation', label: 'Sebelum Lulus', color: '#0D542B', labelColor: '#FFFFFF' },
    { key: 'after_under_6', label: '<6 bulan', color: '#FACC15', labelColor: '#334155' },
    { key: 'after_6_to_12', label: '6–12 bulan', color: '#F97316', labelColor: '#FFFFFF' },
    { key: 'after_over_12', label: '>12 bulan', color: '#B91C1C', labelColor: '#FFFFFF' },
    { key: 'after_unavailable', label: 'Masa tunggu tidak tersedia', color: '#CBD5E1', labelColor: '#334155' },
    { key: 'not_searching', label: 'Tidak mencari kerja', color: '#B91C1C', labelColor: '#FFFFFF' },
];

// Mengagregasi tepat tiga batang fase dengan panjang bar berbasis jumlah alumni.
const timingStackGroups = computed(() => {
    if (props.chart?.timing_stack_groups) {
        return props.chart.timing_stack_groups;
    }

    const groups = [
        { phase: 'Sebelum lulus', counts: Object.fromEntries(timingStackSegments.map((segment) => [segment.key, 0])) },
        { phase: 'Setelah lulus', counts: Object.fromEntries(timingStackSegments.map((segment) => [segment.key, 0])) },
        { phase: 'Tidak mencari kerja', counts: Object.fromEntries(timingStackSegments.map((segment) => [segment.key, 0])) },
    ];

    series.value.forEach((item) => {
        const classification = classifyTimingRange(item);
        const group = groups.find((candidate) => candidate.phase === classification.phase) || groups[2];
        group.counts[classification.segmentKey] = (group.counts[classification.segmentKey] || 0) + seriesMetric(item);
    });

    return groups;
});

// Diagram batang horizontal berbasis jumlah dengan warna fase dan masa tunggu.
const timingStackedOption = computed(() => {
    return {
        ...baseOption(validResponses.value),
        themePalette: timingStackSegments.map((segment) => segment.color),
        grid: { left: 24, right: 48, top: 28, bottom: 64, containLabel: true },
        xAxis: {
            type: 'value',
            min: 0,
            max: Math.max(1, ...timingStackGroups.value.map((group) => Object.values(group.counts).reduce((total, count) => total + count, 0))),
            name: 'Jumlah alumni',
            nameTextStyle: { color: '#64748B' },
            axisLabel: { formatter: (value) => formatNumber(value, 0), color: '#64748B' },
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
        },
        yAxis: {
            type: 'category',
            inverse: true,
            data: timingStackGroups.value.map((group) => group.phase),
            axisLabel: { color: '#334155', fontWeight: 600, fontSize: 12 },
            axisTick: { show: false },
            axisLine: { lineStyle: { color: '#CBD5E1' } },
        },
        legend: { data: timingStackSegments.map((segment) => segment.label), bottom: 0, type: 'scroll', textStyle: { color: '#475569', fontSize: 11 } },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            formatter: (params) => {
                const group = timingStackGroups.value[params[0]?.dataIndex];
                if (!group) return '';

                const total = Object.values(group.counts).reduce((sum, count) => sum + count, 0);
                return `<strong>${escapeHtml(group.phase)}</strong><br>${params.filter((param) => Number(param.value) > 0).map((param) => {
                    const count = Number(param.value) || 0;
                    const percentage = total > 0 ? (count / total) * 100 : 0;

                    return `${escapeHtml(param.seriesName)}: <strong>${formatNumber(percentage, 1)}%</strong> (${formatNumber(count, 0)} alumni)`;
                }).join('<br>')}`;
            },
        },
        series: timingStackSegments.map((segment) => ({
            name: segment.label,
            type: 'bar',
            stack: 'total',
            stackOrder: timingStackSegments.findIndex((candidate) => candidate.key === segment.key),
            barMaxWidth: 34,
            itemStyle: { color: segment.color },
            data: timingStackGroups.value.map((group) => {
                const count = group.counts[segment.key] || 0;
                const total = Object.values(group.counts).reduce((sum, value) => sum + value, 0);

                return { name: group.phase, value: count, count, percentage: total > 0 ? (count / total) * 100 : 0 };
            }),
            label: {
                show: true,
                position: 'inside',
                formatter: ({ data }) => data?.percentage >= 10 ? `${formatNumber(data.percentage, 1)}%` : '',
                color: segment.labelColor,
                fontWeight: 600,
                fontSize: 10,
            },
        })),
    };
});

// Tabel ringkas mengikuti urutan fase dan segmen pada diagram batang.
const timingStackedTable = computed(() => ({
    columns: [
        { key: 'phase', label: 'Fase' },
        { key: 'segment', label: 'Rentang Waktu' },
        { key: 'count', label: 'Jumlah Alumni', align: 'right' },
        { key: 'percentage', label: 'Persentase', align: 'right' },
    ],
    rows: timingStackGroups.value.flatMap((group) => {
        const total = Object.values(group.counts).reduce((sum, count) => sum + count, 0);

        return timingStackSegments
            .filter((segment) => group.counts[segment.key] > 0)
            .map((segment) => ({
                phase: group.phase,
                segment: segment.label,
                count: formatNumber(group.counts[segment.key], 0),
                percentage: `${formatNumber(total > 0 ? (group.counts[segment.key] / total) * 100 : 0, 1)}%`,
            }));
    }),
}));

// -----------------------------------------------------------------------------
// 1. DIVERGING BAR: Posisi Waktu terhadap Kelulusan (Skala Natural Tanpa Tanda +/-)
// -----------------------------------------------------------------------------
const divergingData = computed(() => {
    if (props.chart?.diverging_rows) {
        return props.chart.diverging_rows;
    }

    return series.value.map((item) => {
        const rawLabel = seriesLabel(item);
        const naturalLabel = item.full_label || item.label || rawLabel;
        const shortLabel = item.short_label || naturalLabel;
        const isBefore = item.phase_key === 'before_graduation';
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
    if (props.chart?.sorted_categories) {
        return props.chart.sorted_categories;
    }

    return [...series.value]
        .map((item) => {
            const rawLabel = seriesLabel(item);
            const naturalLabel = item.full_label || item.label || rawLabel;
            const val = seriesMetric(item);
            const selected = !props.selectedLabel || props.selectedLabel === rawLabel;
            return {
                name: rawLabel,
                naturalLabel,
                value: val,
                percentage: validResponses.value > 0 ? ((val / validResponses.value) * 100).toFixed(1) : 0,
                itemStyle: {
                    color: item.phase_key === 'before_graduation' ? '#64748B' : '#0D542B',
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
        key: 'timing_stacked',
        chartType: 'stacked_horizontal',
        tabLabel: 'Rentang Waktu Bertumpuk',
        title: 'Sebaran rentang waktu mulai mencari pekerjaan',
        description: 'Panjang batang menunjukkan jumlah alumni. Sebelum lulus ditampilkan hijau, tidak mencari kerja merah, sedangkan setelah lulus dibagi menjadi <6 bulan kuning, 6–12 bulan oranye, dan >12 bulan merah. Tidak ada kategori studi lanjut atau abu-abu.',
        option: timingStackedOption.value,
        tableData: timingStackedTable.value,
        height: 420,
        analysis: {
            title: 'Kilas Kesiapan Karir Alumni UKDW',
            statusBadge: 'Perilaku Kesiapan',
            badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
            lead: `Sebanyak ${formatNumber(beforeCount.value, 0)} alumni (${beforePercentage.value}%) mulai mencari kerja sebelum lulus, ${formatNumber(afterCount.value, 0)} alumni (${afterPercentage.value}%) setelah lulus, dan ${formatNumber(notSearchingCount.value, 0)} alumni tidak mencari kerja.`,
            detail: 'Sebelum lulus dan tidak mencari kerja tidak dipecah lagi. Hanya fase setelah lulus yang dibagi menjadi <6 bulan, 6–12 bulan, dan >12 bulan agar keterlambatan masuk kerja terlihat jelas.',
            reason: 'Dipilih sebagai Stacked Bar Chart berbasis jumlah agar panjang batang langsung menunjukkan banyaknya alumni dan warna hanya menjelaskan fase atau rentang masa tunggu.',
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
