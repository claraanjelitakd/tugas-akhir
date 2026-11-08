<script setup>
/**
 * Komponen rincian perbandingan UMP dan bobot IKU S1.
 *
 * Seluruh seri grafik dihitung dari records hasil klasifikasi backend. Komponen
 * tidak menyaring baris berbobot nol agar data yang belum memenuhi atau belum
 * dapat dibandingkan tetap terlihat pada grafik dan tabel rincian individu.
 */
import { computed } from 'vue';
import AnalyticsTabbedContainer from '../AnalyticsTabbedContainer.vue';
import {
    analyticsColors,
    analyticsScalePalette,
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

// Seri agregat dan records individu dari backend.
const records = computed(() => props.chart?.records || []);
const summary = computed(() => props.chart?.summary || {});

// Urutan role hanya mengatur tampilan; jumlah dan nilainya selalu dihitung dari records.
const roleOrder = [
    'Karyawan',
    'Founder / Co-Founder',
    'Freelancer',
    'Melanjutkan Pendidikan',
    'Wirausaha Lainnya',
    'Tidak memenuhi klasifikasi',
];

// Menentukan kelompok perbandingan UMP yang dapat diaudit dari satu record.
const comparisonCategory = (record) => {
    if (record.salary_above_threshold === true || Number(record.salary_above_threshold) === 1) {
        return 'Di atas 1,2× UMP';
    }

    if (record.salary !== null && record.salary !== undefined && record.threshold !== null && record.threshold !== undefined) {
        return 'Di bawah 1,2× UMP';
    }

    return 'Belum dapat dibandingkan';
};

// Mengelompokkan seluruh alumni menurut role, status UMP, dan bobot aktual.
const roleStats = computed(() => {
    const grouped = new Map();

    records.value.forEach((record) => {
        const role = record.career_category || 'Tidak memenuhi klasifikasi';
        const current = grouped.get(role) || {
            role,
            count: 0,
            above: 0,
            below: 0,
            unavailable: 0,
            weightedScore: 0,
            weightedCount: 0,
        };
        const category = comparisonCategory(record);
        const weight = Number(record.weight || 0);

        current.count += 1;
        current[category === 'Di atas 1,2× UMP' ? 'above' : category === 'Di bawah 1,2× UMP' ? 'below' : 'unavailable'] += 1;
        current.weightedScore += weight;
        if (weight > 0) current.weightedCount += 1;
        grouped.set(role, current);
    });

    return [...grouped.values()].sort((left, right) => {
        const leftIndex = roleOrder.indexOf(left.role);
        const rightIndex = roleOrder.indexOf(right.role);
        return (leftIndex < 0 ? roleOrder.length : leftIndex) - (rightIndex < 0 ? roleOrder.length : rightIndex);
    }).map((item) => ({
        ...item,
        averageWeight: item.weightedCount > 0 ? item.weightedScore / item.weightedCount : 0,
    }));
});

// Menormalkan baris role menjadi proporsi 100% untuk grafik komposisi UMP.
const roleChartRows = computed(() => roleStats.value.map((item) => ({
    ...item,
    abovePercentage: item.count > 0 ? Number(((item.above / item.count) * 100).toFixed(1)) : 0,
    belowPercentage: item.count > 0 ? Number(((item.below / item.count) * 100).toFixed(1)) : 0,
    unavailablePercentage: item.count > 0 ? Number(((item.unavailable / item.count) * 100).toFixed(1)) : 0,
})));

// Opsi grafik komposisi status UMP per role, termasuk data yang belum lengkap.
const umpCompositionOption = computed(() => {
    const rows = roleChartRows.value;
    const categories = ['Di atas 1,2× UMP', 'Di bawah 1,2× UMP', 'Belum dapat dibandingkan'];
    // Gunakan palet status Indikator 02: baik = hijau tua, belum memenuhi = merah, data kosong = slate.
    const colors = [analyticsScalePalette[4], analyticsScalePalette[0], '#CBD5E1'];

    return {
        ...baseOption(records.value.length),
        themePalette: colors,
        legend: { data: categories, bottom: 0, textStyle: { color: '#475569', fontSize: 11 } },
        grid: { left: 24, right: 36, top: 28, bottom: 56, containLabel: true },
        xAxis: { type: 'value', min: 0, max: 100, axisLabel: { formatter: '{value}%', color: '#64748B' }, splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } } },
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
            barMaxWidth: 28,
            itemStyle: { color: colors[index] },
            label: { show: true, position: 'inside', formatter: ({ value }) => (value >= 10 ? `${formatNumber(value, 1)}%` : ''), color: index === 2 ? '#334155' : '#FFFFFF', fontWeight: 600, fontSize: 10 },
            data: rows.map((item) => ({
                name: item.role,
                category,
                value: category === categories[0] ? item.abovePercentage : category === categories[1] ? item.belowPercentage : item.unavailablePercentage,
                count: category === categories[0] ? item.above : category === categories[1] ? item.below : item.unavailable,
                total: item.count,
                prodi: item.role,
            })),
        })),
    };
});

// Mengubah masa tunggu menjadi kategori grafis yang tetap dan dapat dibandingkan.
const waitCategory = (record) => {
    if (record.career_category === 'Melanjutkan Pendidikan') return 'Melanjutkan Pendidikan';
    if (record.before_graduation) return 'Sebelum Lulus';
    if (record.months === null || record.months === undefined) return 'Masa Tunggu Tidak Tersedia';

    const months = Number(record.months);
    if (months < 6) return '<6 bulan';
    if (months < 12) return '6–<12 bulan';
    return '≥12 bulan';
};

// Distribusi masa tunggu per role dari records nyata, termasuk 12 bulan atau lebih.
const waitStats = computed(() => {
    const categories = ['Sebelum Lulus', '<6 bulan', '6–<12 bulan', '≥12 bulan', 'Melanjutkan Pendidikan', 'Masa Tunggu Tidak Tersedia'];
    return roleStats.value.map((role) => {
        const roleRows = records.value.filter((record) => (record.career_category || 'Tidak memenuhi klasifikasi') === role.role);
        const counts = Object.fromEntries(categories.map((category) => [category, roleRows.filter((record) => waitCategory(record) === category).length]));
        return { role: role.role, total: roleRows.length, counts };
    });
});

// Opsi stacked bar masa tunggu per role.
const waitTimeOption = computed(() => {
    const rows = waitStats.value;
    const categories = ['Sebelum Lulus', '<6 bulan', '6–<12 bulan', '≥12 bulan', 'Melanjutkan Pendidikan', 'Masa Tunggu Tidak Tersedia'];
    // Urutan warna mengikuti skala Indikator 02; studi lanjut dan data kosong memakai netral slate.
    const colors = [analyticsScalePalette[4], analyticsScalePalette[3], analyticsScalePalette[2], analyticsScalePalette[1], '#64748B', '#CBD5E1'];

    return {
        ...baseOption(records.value.length),
        themePalette: colors,
        legend: { data: categories, bottom: 0, type: 'scroll', textStyle: { color: '#475569', fontSize: 11 } },
        grid: { left: 24, right: 36, top: 28, bottom: 66, containLabel: true },
        xAxis: { type: 'value', min: 0, max: 100, axisLabel: { formatter: '{value}%', color: '#64748B' }, splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } } },
        yAxis: { type: 'category', inverse: true, data: rows.map((item) => `${item.role} (n=${item.total})`), axisLabel: { color: '#334155', width: 210, overflow: 'truncate' } },
        tooltip: { ...baseOption().tooltip, trigger: 'axis', axisPointer: { type: 'shadow' }, formatter: (params) => {
            const item = rows[params?.[0]?.dataIndex];
            if (!item) return '';
            return `${item.role}<br/>${categories.map((category) => `${category}: ${item.counts[category]} (${formatPercentage(item.total > 0 ? (item.counts[category] / item.total) * 100 : 0)})`).join('<br/>')}<br/>Total: ${item.total}`;
        } },
        series: categories.map((category, index) => ({
            name: category,
            type: 'bar',
            stack: 'total',
            barMaxWidth: 28,
            itemStyle: { color: colors[index] },
            label: { show: true, position: 'inside', formatter: ({ value }) => (value >= 10 ? `${formatNumber(value, 1)}%` : ''), color: index >= 4 ? '#334155' : '#FFFFFF', fontWeight: 600, fontSize: 10 },
            data: rows.map((item) => ({
                name: item.role,
                category,
                value: item.total > 0 ? Number(((item.counts[category] / item.total) * 100).toFixed(1)) : 0,
                count: item.counts[category],
                total: item.total,
                prodi: item.role,
            })),
        })),
    };
});

// Opsi grafik total bobot per role untuk menjelaskan komponen Σ(nᵢ × kᵢ).
const weightOption = computed(() => ({
    ...baseOption(records.value.length),
    grid: { left: 24, right: 90, top: 22, bottom: 28, containLabel: true },
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
        data: roleStats.value.map((item) => ({ name: item.role, category: item.role, value: Number(item.weightedScore.toFixed(2)), count: item.weightedCount, respondentCount: item.count, prodi: item.role })),
    }],
}));

// Tabel ringkasan role menampilkan responden, status UMP, dan bobot aktual.
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
    title: 'Kilas UMP dan Bobot IKU S1',
    statusBadge: `${formatPercentage(summary.value.above_threshold_percentage)} di atas ambang`,
    badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
    lead: `${formatNumber(records.value.length, 0)} records lulusan ditampilkan; ${formatNumber(summary.value.comparable_count, 0)} memiliki gaji dan ambang UMP yang dapat dibandingkan.`,
    detail: `Total skor bobot ${formatNumber(summary.value.weighted_score, 2)} dari ${formatNumber(summary.value.valid_respondents, 0)} responden valid. Baris tanpa bobot tetap ditampilkan agar alasan tidak memenuhi dapat ditelusuri.`,
}));

// Beberapa view dipakai agar pengguna dapat membaca status UMP, masa tunggu, dan bobot secara terpisah.
const views = computed(() => [
    {
        key: 'ump-composition',
        chartType: 'stacked_horizontal_100',
        tabLabel: 'Status UMP',
        title: 'Komposisi Status Pendapatan terhadap 1,2× UMP',
        description: 'Perbandingan tiap role mencakup pendapatan di atas ambang, di bawah ambang, dan data yang belum dapat dibandingkan.',
        option: umpCompositionOption.value,
        tableData: tableData.value,
        height: Math.max(360, Math.min(roleStats.value.length * 54 + 130, 650)),
        analysis: analysis.value,
    },
    {
        key: 'wait-time',
        chartType: 'stacked_horizontal_100',
        tabLabel: 'Masa Tunggu',
        title: 'Distribusi Masa Tunggu per Role Karier',
        description: 'Masa tunggu dibedakan menjadi sebelum lulus, kurang dari 6 bulan, 6 sampai kurang dari 12 bulan, 12 bulan atau lebih, dan studi lanjut.',
        option: waitTimeOption.value,
        tableData: tableData.value,
        height: Math.max(380, Math.min(roleStats.value.length * 54 + 150, 680)),
        analysis: analysis.value,
    },
    {
        key: 'weighted-score',
        chartType: 'horizontal_bar',
        tabLabel: 'Total Bobot',
        title: 'Total Bobot IKU per Role Karier',
        description: 'Jumlah bobot setiap role menunjukkan kontribusi Σ(nᵢ × kᵢ); rincian individu tetap tersedia pada mode Data.',
        option: weightOption.value,
        tableData: tableData.value,
        height: Math.max(340, Math.min(roleStats.value.length * 54 + 120, 600)),
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
