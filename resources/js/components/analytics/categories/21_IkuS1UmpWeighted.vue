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
        legend: { show: false },
        grid: { left: 24, right: 90, top: 20, bottom: 35, containLabel: true },
        xAxis: { type: 'value', min: 0, max: maxTotal, name: 'Jumlah alumni', nameTextStyle: { color: '#64748B', padding: [0, 0, 0, 8] }, axisLabel: { formatter: (value) => formatNumber(value, 0), color: '#64748B' }, splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } } },
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
                role: item.role,
            })),
        })),
    };
});

// Distribusi masa tunggu per role yang sudah diagregasi backend.
const waitStats = computed(() => props.chart?.wait_stats || []);

// Opsi stacked bar masa tunggu menggunakan jumlah dan persentase backend.
const waitTimeOption = computed(() => {
    const rows = waitStats.value;
    const categories = ['Sebelum Lulus', '<6 bulan', '6–<12 bulan', '≥12 bulan', 'Masa Tunggu Tidak Tersedia'];
    const colors = ['#0D542B', '#0284C7', '#F59E0B', '#DC2626', '#CBD5E1'];
    const labelColors = ['#FFFFFF', '#FFFFFF', '#334155', '#FFFFFF', '#334155'];
    const maxTotal = Math.max(1, ...rows.map((item) => item.total));

    return {
        ...baseOption(records.value.length),
        themePalette: colors,
        legend: { show: false },
        grid: { left: 24, right: 90, top: 20, bottom: 35, containLabel: true },
        xAxis: { type: 'value', min: 0, max: maxTotal, name: 'Jumlah alumni', nameTextStyle: { color: '#64748B', padding: [0, 0, 0, 8] }, axisLabel: { formatter: (value) => formatNumber(value, 0), color: '#64748B' }, splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } } },
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
                role: item.role,
            })),
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
        tableData: tableData.value,
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
                <!-- Legenda Status UMP jika tab aktif adalah Status UMP -->
                <div
                    v-if="view?.key === 'ump-composition'"
                    class="flex flex-wrap items-center gap-x-4 gap-y-1.5 rounded-lg border border-slate-200/80 bg-slate-50/70 px-3 py-2 text-[11px] text-slate-600"
                >
                    <span class="font-bold text-slate-800">Status Pendapatan vs 1,2× UMP:</span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-xs bg-[#0D542B]"></span>
                        <strong>Di atas 1,2× UMP (Memenuhi)</strong>
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-xs bg-[#B91C1C]"></span>
                        <strong>Di bawah 1,2× UMP</strong>
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-xs bg-[#CBD5E1]"></span>
                        <strong>Belum dapat dibandingkan (Gaji atau UMP kosong)</strong>
                    </span>
                </div>

                <!-- Legenda Masa Tunggu jika tab aktif adalah Masa Tunggu -->
                <div
                    v-else-if="view?.key === 'wait-time'"
                    class="flex flex-wrap items-center gap-x-4 gap-y-1.5 rounded-lg border border-slate-200/80 bg-slate-50/70 px-3 py-2 text-[11px] text-slate-600"
                >
                    <span class="font-bold text-slate-800">Kategori Masa Tunggu:</span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-xs bg-[#0D542B]"></span>
                        <strong>Sebelum Lulus</strong>
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-xs bg-[#0284C7]"></span>
                        <strong>&lt;6 bulan</strong>
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-xs bg-[#F59E0B]"></span>
                        <strong>6–&lt;12 bulan</strong>
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-xs bg-[#DC2626]"></span>
                        <strong>&ge;12 bulan</strong>
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-xs bg-[#CBD5E1]"></span>
                        <strong>Belum Tersedia</strong>
                    </span>
                </div>
            </div>
        </template>
    </AnalyticsTabbedContainer>
</template>
