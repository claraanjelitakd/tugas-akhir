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

// Warna status konsisten dengan Response Rate: Abu-abu (kiri), Merah, Kuning, Hijau (kanan).
const umpStatusColors = {
    unavailable: '#CBD5E1',
    below: '#DC2626',
    above: '#0D542B',
};

// Urutan kanonik role karier agar konsisten di semua tab dan grafik IKU.
const CANONICAL_ROLE_ORDER = [
    'Karyawan',
    'Founder / Co-Founder',
    'Freelancer',
    'Wirausaha Lainnya',
    'Melanjutkan Pendidikan',
    'Tidak memenuhi klasifikasi',
];

const getRoleSortIndex = (role) => {
    const idx = CANONICAL_ROLE_ORDER.indexOf(role);
    return idx === -1 ? 99 : idx;
};

// Menampilkan persentase dan rasio jumlah (count/total) secara proporsional agar tidak bertubrukan pada segmen sempit.
const formatStackPercentageLabel = ({ data }) => {
    const count = Number(data?.count || 0);
    const total = Number(data?.total || 0);
    const percentage = Number(data?.percentage || 0);
    const axisMax = Number(data?.axisMax || 0);
    if (!count || percentage < 7) return '';

    const widthRatio = axisMax > 0 ? (count / axisMax) : (percentage / 100);

    // Jika segmen terlalu sempit (< 6% lebar chart atau kurang dari 3 responden pada skala besar), sembunyikan label
    if (widthRatio < 0.06 || (axisMax >= 40 && count < 3)) {
        return '';
    }

    // Jika segmen cukup lebar (>= 14% lebar chart dan >= 15% dari total baris), tampilkan persentase dan rasio jumlah
    if (widthRatio >= 0.14 && percentage >= 15 && total > 0) {
        return `${formatPercentage(percentage)} (${formatNumber(count, 0)}/${formatNumber(total, 0)})`;
    }

    // Jika sedang, tampilkan hanya persentase agar ringkas dan tidak menabrak batas bar
    return formatPercentage(percentage);
};

// Menormalkan nama properti payload backend tanpa menghitung ulang records, diurutkan konsisten.
const roleStats = computed(() => [...(props.chart?.role_stats || [])]
    .map((item) => ({
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
    }))
    .sort((a, b) => getRoleSortIndex(a.role) - getRoleSortIndex(b.role)));

// Opsi grafik komposisi status UMP per role dari agregasi backend (Abu-abu kiri -> Merah -> Hijau kanan).
const umpCompositionOption = computed(() => {
    const rows = roleStats.value;
    const categories = ['Belum dapat dibandingkan', 'Di bawah 1,2× UMP', 'Di atas 1,2× UMP'];
    const colors = [umpStatusColors.unavailable, umpStatusColors.below, umpStatusColors.above];
    const labelColors = ['#0F172A', '#FFFFFF', '#FFFFFF'];
    const maxTotal = Math.max(1, ...rows.map((item) => item.count));
    const axisMax = Math.max(10, Math.ceil(maxTotal / 10) * 10);

    return {
        ...baseOption(records.value.length),
        themePalette: colors,
        legend: { show: false },
        grid: { left: 24, right: 80, top: 20, bottom: 44, containLabel: true },
        xAxis: { type: 'value', min: 0, max: axisMax, name: 'Jumlah alumni (orang)', nameLocation: 'middle', nameGap: 30, axisLabel: { formatter: (value) => formatNumber(value, 0), color: '#64748B' }, splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } } },
        yAxis: { type: 'category', inverse: true, data: rows.map((item) => item.role), axisLabel: { color: '#334155', fontWeight: 500, width: 190, overflow: 'truncate' }, axisTick: { show: false } },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            formatter: (params) => {
                const item = rows[params?.[0]?.dataIndex];
                if (!item) return '';

                return `${item.role}<br/>Di atas 1,2× UMP (Memenuhi): ${item.above} (${formatPercentage(item.abovePercentage)})<br/>Di bawah 1,2× UMP: ${item.below} (${formatPercentage(item.belowPercentage)})<br/>Belum dapat dibandingkan: ${item.unavailable} (${formatPercentage(item.unavailablePercentage)})<br/>Total responden: ${item.count}`;
            },
        },
        series: categories.map((category, index) => ({
            name: category,
            type: 'bar',
            stack: 'total',
            stackOrder: index,
            barMaxWidth: 26,
            itemStyle: { color: colors[index] },
            label: { show: true, position: 'inside', formatter: formatStackPercentageLabel, color: labelColors[index], fontWeight: 700, fontSize: 10 },
            data: rows.map((item) => ({
                name: item.role,
                category,
                value: category === categories[0] ? item.unavailable : category === categories[1] ? item.below : item.above,
                count: category === categories[0] ? item.unavailable : category === categories[1] ? item.below : item.above,
                total: item.count,
                percentage: category === categories[0] ? item.unavailablePercentage : category === categories[1] ? item.belowPercentage : item.abovePercentage,
                role: item.role,
                axisMax,
            })),
        })),
    };
});

// Distribusi masa tunggu per role yang sudah diagregasi backend, diurutkan konsisten dengan Status UMP.
const waitStats = computed(() => [...(props.chart?.wait_stats || [])]
    .sort((a, b) => getRoleSortIndex(a.role) - getRoleSortIndex(b.role)));

// Opsi stacked bar masa tunggu dengan 4 warna konsisten Response Rate (Abu-abu kiri -> Merah -> Kuning -> Hijau kanan).
const waitTimeOption = computed(() => {
    const rows = waitStats.value;
    const segmentDefs = [
        {
            key: 'unavailable',
            label: 'Belum Tersedia',
            color: '#CBD5E1',
            textColor: '#0F172A',
            stackOrder: 0,
            getCount: (item) => Number(item.counts?.['Masa Tunggu Tidak Tersedia'] || 0),
        },
        {
            key: 'over_12',
            label: '≥12 bulan (Tidak Berbobot)',
            color: '#DC2626',
            textColor: '#FFFFFF',
            stackOrder: 1,
            getCount: (item) => Number(item.counts?.['≥12 bulan'] || 0),
        },
        {
            key: '6_to_12',
            label: '6–<12 bulan (Menengah)',
            color: '#D97706',
            textColor: '#FFFFFF',
            stackOrder: 2,
            getCount: (item) => Number(item.counts?.['6–<12 bulan'] || 0),
        },
        {
            key: 'under_6_or_before',
            label: '<6 bulan / Sebelum Lulus (Utama)',
            color: '#0D542B',
            textColor: '#FFFFFF',
            stackOrder: 3,
            getCount: (item) => Number(item.counts?.['Sebelum Lulus'] || 0) + Number(item.counts?.['<6 bulan'] || 0),
        },
    ];
    const maxTotal = Math.max(1, ...rows.map((item) => item.total));
    const axisMax = Math.max(10, Math.ceil(maxTotal / 10) * 10);

    return {
        ...baseOption(records.value.length),
        themePalette: segmentDefs.map((s) => s.color),
        legend: { show: false },
        grid: { left: 24, right: 80, top: 20, bottom: 44, containLabel: true },
        xAxis: { type: 'value', min: 0, max: axisMax, name: 'Jumlah alumni (orang)', nameLocation: 'middle', nameGap: 30, axisLabel: { formatter: (value) => formatNumber(value, 0), color: '#64748B' }, splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } } },
        yAxis: { type: 'category', inverse: true, data: rows.map((item) => item.role), axisLabel: { color: '#334155', fontWeight: 500, width: 190, overflow: 'truncate' }, axisTick: { show: false } },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            formatter: (params) => {
                const item = rows[params?.[0]?.dataIndex];
                if (!item) return '';
                const rawCategories = ['Sebelum Lulus', '<6 bulan', '6–<12 bulan', '≥12 bulan', 'Masa Tunggu Tidak Tersedia'];

                return `${item.role}<br/>${rawCategories.map((category) => `${category}: ${item.counts[category]} (${formatPercentage(item.percentages?.[category] || 0)})`).join('<br/>')}<br/>Total responden: ${item.total}`;
            },
        },
        series: segmentDefs.map((seg) => ({
            name: seg.label,
            type: 'bar',
            stack: 'total',
            stackOrder: seg.stackOrder,
            barMaxWidth: 26,
            itemStyle: { color: seg.color },
            label: { show: true, position: 'inside', formatter: formatStackPercentageLabel, color: seg.textColor, fontWeight: 700, fontSize: 10 },
            data: rows.map((item) => {
                const count = seg.getCount(item);
                const total = Number(item.total || 0);
                const pct = total > 0 ? Number(((count / total) * 100).toFixed(1)) : 0;

                return {
                    name: item.role,
                    category: seg.label,
                    value: count,
                    count,
                    total,
                    percentage: pct,
                    role: item.role,
                    axisMax,
                };
            }),
        })),
    };
});

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

// Tabel pivot ringkasan distribusi masa tunggu per role karier.
const waitTableData = computed(() => ({
    columns: [
        { key: 'name', label: 'Role Karier' },
        { key: 'total', label: 'Responden', align: 'right' },
        { key: 'before', label: 'Sebelum Lulus', align: 'right' },
        { key: 'under_6', label: '<6 Bulan', align: 'right' },
        { key: 'under_12', label: '6–<12 Bulan', align: 'right' },
        { key: 'over_12', label: '≥12 Bulan', align: 'right' },
        { key: 'unavailable', label: 'Tidak Tersedia', align: 'right' },
    ],
    rows: waitStats.value.map((item) => {
        const total = Number(item.total || 0);
        const fmt = (cat) => {
            const cnt = Number(item.counts?.[cat] || 0);
            const pct = Number(item.percentages?.[cat] || 0);
            return cnt > 0 ? `${formatNumber(cnt, 0)} (${formatPercentage(pct)})` : '0';
        };

        return {
            name: item.role,
            total: formatNumber(total, 0),
            before: fmt('Sebelum Lulus'),
            under_6: fmt('<6 bulan'),
            under_12: fmt('6–<12 bulan'),
            over_12: fmt('≥12 bulan'),
            unavailable: fmt('Masa Tunggu Tidak Tersedia'),
        };
    }),
}));

// Narasi ringkas menjelaskan cakupan seluruh data, bukan hanya baris berbobot positif.
const analysis = computed(() => ({
    title: 'Kilas Pendapatan, Masa Tunggu, dan Bobot IKU S1',
    statusBadge: `${formatPercentage(summary.value.above_threshold_percentage)} di atas ambang`,
    badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
    lead: `${formatNumber(records.value.length, 0)} records lulusan ditampilkan; ${formatNumber(summary.value.comparable_count, 0)} memiliki gaji dan ambang UMP yang dapat dibandingkan.`,
    detail: `Total skor bobot ${formatNumber(summary.value.weighted_score, 2)} dari ${formatNumber(summary.value.valid_respondents, 0)} responden valid. Baris tanpa bobot tetap ditampilkan agar alasan tidak memenuhi dapat ditelusuri.`,
}));

// Dua view audit memisahkan pembanding gaji dan masa tunggu terhadap standar IKU.
const views = computed(() => [
    {
        key: 'ump-composition',
        chartType: 'stacked_horizontal',
        tabLabel: 'Status UMP',
        title: 'Komposisi Status Pendapatan terhadap 1,2× UMP',
        description: 'Panjang bar menunjukkan jumlah alumni; label segmen menunjukkan persentase dalam role karier.',
        option: umpCompositionOption.value,
        tableData: tableData.value,
        height: Math.max(340, Math.min(roleStats.value.length * 54 + 110, 620)),
        analysis: analysis.value,
        drillDownColumns: [
            { key: 'nim', label: 'NIM' },
            { key: 'nama', label: 'Nama Alumni' },
            { key: 'nama_prodi', label: 'Program Studi' },
            { key: 'career_category', label: 'Role Karier' },
            { key: 'nama_perusahaan', label: 'Perusahaan / Kampus S2' },
            { key: 'months', label: 'Masa Tunggu' },
            { key: 'salary', label: 'Pendapatan/Gaji' },
            { key: 'ump', label: 'UMP' },
            { key: 'salary_comparison', label: 'Status UMP' },
            { key: 'weight', label: 'Bobot IKU' },
        ],
    },
    {
        key: 'wait-time',
        chartType: 'stacked_horizontal',
        tabLabel: 'Masa Tunggu',
        title: 'Distribusi Masa Tunggu per Role Karier',
        description: 'Panjang bar menunjukkan jumlah alumni; label segmen menunjukkan persentase dalam role karier.',
        option: waitTimeOption.value,
        tableData: waitTableData.value,
        height: Math.max(360, Math.min(waitStats.value.length * 54 + 120, 650)),
        analysis: analysis.value,
        drillDownColumns: [
            { key: 'nim', label: 'NIM' },
            { key: 'nama', label: 'Nama Alumni' },
            { key: 'nama_prodi', label: 'Program Studi' },
            { key: 'career_category', label: 'Role Karier' },
            { key: 'nama_perusahaan', label: 'Perusahaan / Kampus S2' },
            { key: 'months', label: 'Masa Tunggu' },
            { key: 'salary', label: 'Pendapatan/Gaji' },
            { key: 'ump', label: 'UMP' },
            { key: 'salary_comparison', label: 'Status UMP' },
            { key: 'weight', label: 'Bobot IKU' },
        ],
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
    >
        <template #before-chart="{ view }">
            <div class="mb-3">
                <!-- Legenda Status UMP jika tab aktif adalah Status UMP (Urutan Kiri ke Kanan: Abu-abu -> Merah -> Hijau) -->
                <div
                    v-if="view?.key === 'ump-composition'"
                    class="flex flex-wrap items-center gap-x-3.5 gap-y-2 rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2 text-xs text-slate-700"
                >
                    <span class="font-bold text-slate-900 pr-3 border-r border-slate-300">Status Pendapatan vs 1,2× UMP:</span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="h-3 w-3 rounded-xs bg-[#CBD5E1]"></span>
                        <span><strong>Abu-abu:</strong> Belum dapat dibandingkan</span>
                    </span>
                    <span class="text-slate-300 hidden sm:inline">|</span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="h-3 w-3 rounded-xs bg-[#DC2626]"></span>
                        <span><strong>Merah:</strong> Di bawah 1,2× UMP</span>
                    </span>
                    <span class="text-slate-300 hidden sm:inline">|</span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="h-3 w-3 rounded-xs bg-[#0D542B]"></span>
                        <span><strong>Hijau:</strong> Di atas 1,2× UMP (Memenuhi)</span>
                    </span>
                </div>

                <!-- Legenda Masa Tunggu jika tab aktif adalah Masa Tunggu (Urutan Kiri ke Kanan: Abu-abu -> Merah -> Kuning -> Hijau) -->
                <div
                    v-else-if="view?.key === 'wait-time'"
                    class="flex flex-wrap items-center gap-x-3.5 gap-y-2 rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2 text-xs text-slate-700"
                >
                    <span class="font-bold text-slate-900 pr-3 border-r border-slate-300">Kategori Masa Tunggu:</span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="h-3 w-3 rounded-xs bg-[#CBD5E1]"></span>
                        <span><strong>Abu-abu:</strong> Belum Tersedia</span>
                    </span>
                    <span class="text-slate-300 hidden sm:inline">|</span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="h-3 w-3 rounded-xs bg-[#DC2626]"></span>
                        <span><strong>Merah:</strong> &ge;12 bulan (Tidak Berbobot)</span>
                    </span>
                    <span class="text-slate-300 hidden sm:inline">|</span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="h-3 w-3 rounded-xs bg-[#D97706]"></span>
                        <span><strong>Kuning:</strong> 6–&lt;12 bulan (Menengah)</span>
                    </span>
                    <span class="text-slate-300 hidden sm:inline">|</span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="h-3 w-3 rounded-xs bg-[#0D542B]"></span>
                        <span><strong>Hijau:</strong> &lt;6 bulan / Sebelum Lulus (Utama)</span>
                    </span>
                </div>
            </div>
        </template>
    </AnalyticsTabbedContainer>
</template>
