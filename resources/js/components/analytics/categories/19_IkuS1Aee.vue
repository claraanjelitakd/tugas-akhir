<script setup>
/**
 * Komponen indikator IKU AEE S1.
 *
 * Data merupakan read-model akademik dan tidak menyediakan aksi perubahan.
 * Grafik memakai jumlah denominator sebagai panjang batang, sedangkan warna
 * segmen tepat waktu menunjukkan status capaian terhadap target visual.
 */
import { computed } from 'vue';
import AnalyticsTabbedContainer from '../AnalyticsTabbedContainer.vue';
import { baseOption, formatNumber, formatPercentage } from '../analyticsChartOptions';

// Kontrak payload indikator AEE dari backend.
const props = defineProps({
    chart: { type: Object, default: () => ({}) },
    selectedLabel: { type: String, default: '' },
    loading: { type: Boolean, default: false },
});

// Event seleksi diteruskan ke panel analitik induk.
const emit = defineEmits(['select', 'reset']);

// Seri AEE per program studi dan catatan akademik individu.
const series = computed(() => props.chart?.series || []);
const records = computed(() => props.chart?.records || []);
const summary = computed(() => props.chart?.summary || {});

// Target status visual dikirim backend agar tidak menjadi angka hardcode di Vue.
const statusTarget = computed(() => Number(summary.value.status_target || 60));

// Menentukan warna dan label status capaian berdasarkan realisasi AEE.
const getStatus = (percentage) => {
    if (percentage >= statusTarget.value) {
        return { key: 'green', label: 'Hijau: ≥ Target', color: '#0D542B' };
    }

    if (percentage >= statusTarget.value * 0.75) {
        return { key: 'yellow', label: 'Kuning: 75%–<100% Target', color: '#D97706' };
    }

    return { key: 'red', label: 'Merah: <75% Target', color: '#DC2626' };
};

// Menyiapkan baris visual dengan denominator, persentase, dan status warna.
const chartRows = computed(() => [...series.value]
    .map((item) => {
        const denominator = Number(item.eligible || 0);
        const onTime = Number(item.on_time || 0);
        const late = Number(item.late || 0);
        const unidentified = Number(item.unidentified || 0);
        const notOnTime = late + unidentified;
        const percentage = denominator > 0 ? Number(((onTime / denominator) * 100).toFixed(1)) : 0;

        return {
            ...item,
            denominator,
            onTime,
            late,
            unidentified,
            notOnTime,
            percentage,
            status: getStatus(percentage),
        };
    })
    .sort((left, right) => right.percentage - left.percentage || right.denominator - left.denominator));

// Opsi stacked bar horizontal dengan panjang berbasis jumlah mahasiswa.
const chartOption = computed(() => {
    const rows = chartRows.value;
    const maxDenominator = Math.max(...rows.map((item) => item.denominator), 0);
    const axisMax = Math.max(10, Math.ceil(maxDenominator / 10) * 10);
    // Segmen disusun dari status yang belum dapat ditentukan menuju capaian tepat waktu.
    const segmentDefinitions = [
        { key: 'unidentified', valueKey: 'unidentified', label: 'Abu-abu: Belum Teridentifikasi', color: '#CBD5E1', stackOrder: 0 },
        { key: 'late', valueKey: 'late', label: 'Merah: Belum Tepat Waktu', color: '#DC2626', stackOrder: 1 },
        { key: 'on_time', valueKey: 'onTime', label: 'Hijau: Tepat Waktu', color: '#0D542B', stackOrder: 2 },
    ];

    return {
        ...baseOption(rows.reduce((total, item) => total + item.denominator, 0)),
        // Palet dikunci agar normalizer global mempertahankan makna setiap segmen.
        themePalette: segmentDefinitions.map((item) => item.color),
        graphic: [{
            type: 'text',
            left: 12,
            top: 0,
            style: {
                text: 'Urutan: Tertinggi → Terendah',
                fill: '#334155',
                fontSize: 11,
                fontWeight: 600,
                backgroundColor: '#F8FAFC',
                borderColor: '#CBD5E1',
                borderWidth: 1,
                borderRadius: 4,
                padding: [5, 8],
            },
        }],
        legend: {
            data: segmentDefinitions.map((item) => item.label),
            top: 2,
            right: 8,
            itemWidth: 12,
            itemHeight: 12,
            textStyle: { color: '#334155', fontSize: 10, fontWeight: 600 },
        },
        grid: { left: 24, right: 72, top: 58, bottom: 46, containLabel: true },
        xAxis: {
            type: 'value',
            min: 0,
            max: axisMax,
            name: 'Jumlah mahasiswa (orang)',
            nameLocation: 'middle',
            nameGap: 28,
            axisLabel: { color: '#64748B', fontSize: 11 },
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
        },
        yAxis: {
            type: 'category',
            inverse: true,
            data: rows.map((item) => item.nama_prodi),
            axisLabel: { color: '#334155', width: 180, overflow: 'truncate' },
            axisTick: { show: false },
        },
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            formatter: (params) => {
                const item = rows[params?.[0]?.dataIndex];
                if (!item) return '';

                return `${item.nama_prodi}<br/>Capaian tepat waktu: ${formatPercentage(item.percentage)} (${item.onTime}/${item.denominator})<br/>Belum tepat waktu: ${formatNumber(item.late, 0)} mahasiswa<br/>Belum teridentifikasi: ${formatNumber(item.unidentified, 0)} mahasiswa<br/>Status target: ${item.status.label}<br/>Dikecualikan: ${formatNumber(Number(item.population || 0) - item.denominator, 0)} mahasiswa`;
            },
        },
        series: [
            ...segmentDefinitions.map((definition) => ({
                name: definition.label,
                type: 'bar',
                stack: 'total',
                stackOrder: definition.stackOrder,
                barMaxWidth: 26,
                itemStyle: {
                    color: definition.color,
                    opacity: (params) => {
                        const row = rows[params.dataIndex];
                        return !props.selectedLabel || props.selectedLabel === row?.nama_prodi ? 1 : 0.22;
                    },
                },
                label: {
                    show: true,
                    position: 'inside',
                    formatter: ({ data }) => data?.value > 0 ? `${formatPercentage(data.percentage)}` : '',
                    color: '#FFFFFF',
                    fontWeight: 700,
                    fontSize: 10,
                },
                data: rows.map((item) => ({
                    name: item.nama_prodi,
                    category: definition.label,
                    value: item[definition.valueKey],
                    percentage: item.denominator > 0
                        ? Number(((item[definition.valueKey] / item.denominator) * 100).toFixed(1))
                        : 0,
                    count: item[definition.valueKey],
                    total: item.denominator,
                    prodi: item.nama_prodi,
                })),
            })),
        ],
    };
});

// Tabel ringkasan numerik AEE dengan pembilang, penyebut, dan status warna.
const tableData = computed(() => ({
    columns: [
        { key: 'name', label: 'Program Studi' },
        { key: 'population', label: 'Populasi', align: 'right' },
        { key: 'eligible', label: 'Denominator', align: 'right' },
        { key: 'on_time', label: 'Tepat Waktu', align: 'right' },
        { key: 'not_on_time', label: 'Belum Tepat Waktu', align: 'right' },
        { key: 'unidentified', label: 'Belum Teridentifikasi', align: 'right' },
        { key: 'aee_realization', label: 'AEE Realisasi', align: 'right' },
        { key: 'status', label: 'Status Capaian' },
    ],
    rows: chartRows.value.map((item) => ({
        name: item.nama_prodi,
        population: formatNumber(item.population, 0),
        eligible: formatNumber(item.denominator, 0),
        on_time: formatNumber(item.onTime, 0),
        not_on_time: formatNumber(item.late, 0),
        unidentified: formatNumber(item.unidentified, 0),
        aee_realization: formatPercentage(item.percentage),
        status: item.status.label,
    })),
}));

// Narasi ringkas menjelaskan cakupan dan capaian AEE aktual.
const analysis = computed(() => ({
    title: 'Kilas AEE S1',
    statusBadge: `${formatPercentage(summary.value.aee_pt)} capaian PT`,
    badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
    lead: `${formatNumber(summary.value.on_time_count, 0)} dari ${formatNumber(summary.value.eligible_count, 0)} mahasiswa yang masuk denominator lulus tepat waktu.`,
    detail: `Target status visual ${formatPercentage(statusTarget.value)}. Populasi akademik yang dibaca ${formatNumber(summary.value.population, 0)} mahasiswa; ${formatNumber(summary.value.excluded_count, 0)} mahasiswa dikecualikan dari denominator sesuai ketentuan IKU.`,
}));

// Konfigurasi tampilan grafik dan tabel AEE.
const views = computed(() => [{
    key: 'aee',
    chartType: 'stacked_horizontal',
    tabLabel: 'Kelulusan Tepat Waktu',
    title: 'Kelulusan Tepat Waktu S1 per Program Studi',
    description: 'Batang menunjukkan jumlah denominator: merah untuk lulusan belum tepat waktu, abu-abu untuk status kelulusan belum teridentifikasi, dan hijau untuk lulusan tepat waktu.',
    option: chartOption.value,
    tableData: tableData.value,
    height: Math.max(380, Math.min(series.value.length * 42 + 145, 700)),
    analysis: analysis.value,
}]);
</script>

<template>
    <AnalyticsTabbedContainer
        :views="views"
        :rows="records"
        :selected-label="selectedLabel"
        :loading="loading"
        indicator-badge="IKU S1"
        @select="emit('select', $event)"
        @reset="emit('reset')"
    />
</template>
