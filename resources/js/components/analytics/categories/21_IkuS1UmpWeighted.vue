<script setup>
/**
 * Menampilkan audit UMP, masa tunggu, dan bobot IKU S1.
 *
 * Seluruh klasifikasi, agregasi, persentase, dan bobot berasal dari backend.
 * Komponen ini hanya memetakan payload final ke grafik, tabel, dan drill-down.
 */
import { computed } from 'vue';
import AnalyticsTabbedContainer from '../AnalyticsTabbedContainer.vue';
import {
    analyticsColors,
    baseOption,
    formatNumber,
    formatPercentage,
} from '../analyticsChartOptions';

// Kontrak payload indikator UMP berbobot dari service analitik.
const props = defineProps({
    chart: { type: Object, default: () => ({}) },
    selectedLabel: { type: String, default: '' },
    loading: { type: Boolean, default: false },
});

// Event seleksi baris atau reset selection diteruskan ke panel induk.
const emit = defineEmits(['select', 'reset']);

// Records dan ringkasan hanya dibaca untuk tabel detail dan metadata visual.
const records = computed(() => props.chart?.records || []);
const summary = computed(() => props.chart?.summary || {});

// Warna status pendapatan: memenuhi hijau, di bawah merah, data kosong abu-abu.
const umpStatusColors = {
    above: '#0D542B',
    below: '#B91C1C',
    unavailable: '#CBD5E1',
};

// Warna role dipakai konsisten pada grafik total bobot dan legendanya.
const roleColors = {
    Karyawan: '#0D542B',
    'Founder / Co-Founder': '#166534',
    Freelancer: '#EAB308',
    'Wirausaha Lainnya': '#F97316',
    'Melanjutkan Pendidikan': '#64748B',
    'Tidak memenuhi klasifikasi': '#CBD5E1',
};

// Mengambil warna role dengan slate netral sebagai fallback tampilan.
const roleColor = (role) => roleColors[role] || '#94A3B8';

// Menampilkan persentase backend jika segmen cukup besar untuk dibaca.
const formatStackPercentageLabel = ({ data }) => {
    const count = Number(data?.count || 0);
    const percentage = Number(data?.percentage || 0);

    return percentage >= 10 && count >= 5 ? formatPercentage(percentage) : '';
};

// Menormalkan nama properti payload backend tanpa menghitung ulang records.
const roleStats = computed(() => (props.chart?.role_stats || []).map((item) => ({
    ...item,
    role: item.label,
    count: Number(item.count || 0),
    weightedScore: Number(item.weighted_score || 0),
    weightedCount: Number(item.weighted_count || 0),
    averageWeight: Number(item.average_weight || 0),
    above: Number(item.above_count || 0),
    below: Number(item.below_count || 0),
    unavailable: Number(item.unavailable_count || 0),
    abovePercentage: Number(item.above_percentage || 0),
    belowPercentage: Number(item.below_percentage || 0),
    unavailablePercentage: Number(item.unavailable_percentage || 0),
})));

// Opsi grafik komposisi status UMP per role dari agregasi backend.
const umpCompositionOption = computed(() => {
    const rows = roleStats.value;
    const categories = ['Belum dapat dibandingkan', 'Di bawah 1,2× UMP', 'Di atas 1,2× UMP'];
    const colors = [umpStatusColors.unavailable, umpStatusColors.below, umpStatusColors.above];
    const labelColors = ['#334155', '#FFFFFF', '#FFFFFF'];
    const maxTotal = Math.max(1, ...rows.map((item) => item.count));

    return {
        ...baseOption(records.value.length),
        themePalette: colors,
        legend: { data: categories, bottom: 0, textStyle: { color: '#475569', fontSize: 11 } },
        grid: { left: 24, right: 36, top: 28, bottom: 56, containLabel: true },
        xAxis: { type: 'value', min: 0, max: maxTotal, name: 'Jumlah alumni', nameTextStyle: { color: '#64748B' }, axisLabel: { formatter: (value) => formatNumber(value, 0), color: '#64748B' }, splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } } },
        yAxis: { type: 'category', inverse: true, data: rows.map((item) => `${item.role} (n=${item.count})`), axisLabel: { color: '#334155', width: 210, overflow: 'truncate' } },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            formatter: (params) => {
                const item = rows[params?.[0]?.dataIndex];
                if (!item) return '';

                return `${item.role}<br/>Di atas ambang: ${item.above} (${formatPercentage(item.abovePercentage)})<br/>Di bawah ambang: ${item.below} (${formatPercentage(item.belowPercentage)})<br/>Belum dapat dibandingkan: ${item.unavailable} (${formatPercentage(item.unavailablePercentage)})<br/>Total responden: ${item.count}`;
            },
        },
        series: categories.map((category, index) => ({
            name: category,
            type: 'bar',
            stack: 'total',
            stackOrder: index,
            barMaxWidth: 28,
            itemStyle: { color: colors[index] },
            label: { show: true, position: 'inside', formatter: formatStackPercentageLabel, color: labelColors[index], fontWeight: 600, fontSize: 10 },
            data: rows.map((item) => ({
                name: item.role,
                category,
                value: category === categories[0] ? item.unavailable : category === categories[1] ? item.below : item.above,
                count: category === categories[0] ? item.unavailable : category === categories[1] ? item.below : item.above,
                percentage: category === categories[0] ? item.unavailablePercentage : category === categories[1] ? item.belowPercentage : item.abovePercentage,
                prodi: item.role,
            })),
        })),
    };
});

// Distribusi masa tunggu per role yang sudah diagregasi backend.
const waitStats = computed(() => props.chart?.wait_stats || []);

// Opsi stacked bar masa tunggu menggunakan jumlah dan persentase backend.
const waitTimeOption = computed(() => {
    const rows = waitStats.value;
    const categories = ['Sebelum Lulus', '<6 bulan', '6–<12 bulan', '≥12 bulan', 'Melanjutkan Pendidikan', 'Masa Tunggu Tidak Tersedia'];
    const colors = ['#0D542B', '#FACC15', '#F97316', '#B91C1C', '#64748B', '#CBD5E1'];
    const labelColors = ['#FFFFFF', '#334155', '#FFFFFF', '#FFFFFF', '#FFFFFF', '#334155'];
    const maxTotal = Math.max(1, ...rows.map((item) => item.total));

    return {
        ...baseOption(records.value.length),
        themePalette: colors,
        legend: { data: categories, bottom: 0, type: 'scroll', textStyle: { color: '#475569', fontSize: 11 } },
        grid: { left: 24, right: 36, top: 28, bottom: 66, containLabel: true },
        xAxis: { type: 'value', min: 0, max: maxTotal, name: 'Jumlah alumni', nameTextStyle: { color: '#64748B' }, axisLabel: { formatter: (value) => formatNumber(value, 0), color: '#64748B' }, splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } } },
        yAxis: { type: 'category', inverse: true, data: rows.map((item) => `${item.role} (n=${item.total})`), axisLabel: { color: '#334155', width: 210, overflow: 'truncate' } },
        tooltip: { ...baseOption().tooltip, trigger: 'axis', axisPointer: { type: 'shadow' }, formatter: (params) => {
            const item = rows[params?.[0]?.dataIndex];
            if (!item) return '';

            return `${item.role}<br/>${categories.map((category) => `${category}: ${item.counts[category]} (${formatPercentage(item.percentages?.[category] || 0)})`).join('<br/>')}<br/>Total: ${item.total}`;
        } },
        series: categories.map((category, index) => ({
            name: category,
            type: 'bar',
            stack: 'total',
            stackOrder: index,
            barMaxWidth: 28,
            itemStyle: { color: colors[index] },
            label: { show: true, position: 'inside', formatter: formatStackPercentageLabel, color: labelColors[index], fontWeight: 600, fontSize: 10 },
            data: rows.map((item) => ({
                name: item.role,
                category,
                value: item.counts[category],
                count: item.counts[category],
                percentage: Number(item.percentages?.[category] || 0),
                prodi: item.role,
            })),
        })),
    };
});

// Opsi grafik total bobot hanya memvisualisasikan agregasi backend.
const weightOption = computed(() => ({
    ...baseOption(records.value.length),
    legend: {
        data: roleStats.value.map((item) => ({ name: item.role, icon: 'roundRect', itemStyle: { color: roleColor(item.role) } })),
        selectedMode: false,
        bottom: 0,
        type: 'scroll',
        textStyle: { color: '#475569', fontSize: 11 },
    },
    grid: { left: 24, right: 90, top: 22, bottom: 56, containLabel: true },
    xAxis: { type: 'value', min: 0, axisLabel: { color: '#64748B' }, splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } } },
    yAxis: { type: 'category', inverse: true, data: roleStats.value.map((item) => `${item.role} (N=${item.count})`), axisLabel: { color: '#334155', width: 240, overflow: 'truncate' } },
    tooltip: { ...baseOption().tooltip, trigger: 'axis', axisPointer: { type: 'shadow' }, formatter: (params) => {
        const item = roleStats.value[params?.[0]?.dataIndex];
        return item ? `${item.role}<br/>Σ bobot: ${formatNumber(item.weightedScore, 2)}<br/>Jumlah responden: ${item.count}<br/>Rata-rata bobot valid: ${formatNumber(item.averageWeight, 2)}<br/>Responden valid berbobot: ${item.weightedCount}` : '';
    } },
    series: [{
        name: 'Σ Bobot',
        type: 'bar',
        barMaxWidth: 28,
        itemStyle: { color: analyticsColors.green, borderRadius: [0, 4, 4, 0] },
        label: { show: true, position: 'right', color: '#334155', formatter: ({ value, data }) => `${formatNumber(value, 2)} (N=${data?.respondentCount || 0})` },
        data: roleStats.value.map((item) => ({
            name: item.role,
            category: item.role,
            value: item.weightedScore,
            count: item.weightedCount,
            respondentCount: item.count,
            prodi: item.role,
            itemStyle: { color: roleColor(item.role), borderRadius: [0, 4, 4, 0] },
        })),
    }],
}));

// Tabel ringkasan role menampilkan nilai agregat yang dikirim backend.
const tableData = computed(() => ({
    columns: [
        { key: 'name', label: 'Role Karier' },
        { key: 'count', label: 'Responden', align: 'right' },
        { key: 'above_count', label: 'Di Atas 1,2× UMP', align: 'right' },
        { key: 'below_count', label: 'Di Bawah 1,2× UMP', align: 'right' },
        { key: 'unavailable_count', label: 'Belum Terbandingkan', align: 'right' },
        { key: 'weighted_score', label: 'Σ Bobot', align: 'right' },
        { key: 'average_weight', label: 'Rata-rata Bobot', align: 'right' },
    ],
    rows: roleStats.value.map((item) => ({
        name: item.role,
        count: formatNumber(item.count, 0),
        above_count: formatNumber(item.above, 0),
        below_count: formatNumber(item.below, 0),
        unavailable_count: formatNumber(item.unavailable, 0),
        weighted_score: formatNumber(item.weightedScore, 2),
        average_weight: formatNumber(item.averageWeight, 2),
    })),
}));

// Narasi ringkas menjelaskan cakupan seluruh data, bukan hanya baris berbobot positif.
const analysis = computed(() => ({
    title: 'Kilas Pendapatan, Masa Tunggu, dan Bobot IKU S1',
    statusBadge: `${formatPercentage(summary.value.above_threshold_percentage)} di atas ambang`,
    badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
    lead: `${formatNumber(records.value.length, 0)} records lulusan ditampilkan; ${formatNumber(summary.value.comparable_count, 0)} memiliki gaji dan ambang UMP yang dapat dibandingkan.`,
    detail: `Total skor bobot ${formatNumber(summary.value.weighted_score, 2)} dari ${formatNumber(summary.value.valid_respondents, 0)} responden valid. Baris tanpa bobot tetap ditampilkan agar alasan tidak memenuhi dapat ditelusuri.`,
}));

// Tiga view audit memisahkan pembanding gaji, masa tunggu, dan akumulasi bobot.
const views = computed(() => [
    {
        key: 'ump-composition',
        chartType: 'stacked_horizontal',
        tabLabel: 'Status UMP',
        title: 'Komposisi Status Pendapatan terhadap 1,2× UMP',
        description: 'Panjang bar menunjukkan jumlah alumni; label segmen menunjukkan persentase. Hijau = memenuhi, merah = di bawah, abu-abu = gaji atau UMP belum tersedia.',
        option: umpCompositionOption.value,
        tableData: tableData.value,
        height: Math.max(360, Math.min(roleStats.value.length * 54 + 130, 650)),
        analysis: analysis.value,
    },
    {
        key: 'wait-time',
        chartType: 'stacked_horizontal',
        tabLabel: 'Masa Tunggu',
        title: 'Distribusi Masa Tunggu per Role Karier',
        description: 'Panjang bar menunjukkan jumlah alumni; label segmen menunjukkan persentase. Hijau = sebelum lulus, kuning = <6 bulan, oranye = 6–<12 bulan, merah = ≥12 bulan, abu-abu = belum tersedia.',
        option: waitTimeOption.value,
        tableData: tableData.value,
        height: Math.max(380, Math.min(waitStats.value.length * 54 + 150, 680)),
        analysis: analysis.value,
    },
    {
        key: 'weighted-score',
        chartType: 'table',
        tabLabel: 'Total Bobot',
        title: 'Rincian Total Bobot IKU per Role Karier',
        description: 'Rincian jumlah responden, status pembanding UMP, total bobot, dan rata-rata bobot per role karier.',
        tableOnly: true,
        option: weightOption.value,
        tableData: tableData.value,
        analysis: analysis.value,
    },
]);
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
