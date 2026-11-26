<script setup>
/**
 * Komponen indikator IKU Rasio Kelulusan Tepat Waktu S1 (Maks. 4 Tahun).
 *
 * Data merupakan read-model lulusan S1 dan tidak menyediakan aksi perubahan.
 * Grafik memakai jumlah denominator lulusan sebagai panjang batang horizontal,
 * dengan segmen hijau menampilkan kelulusan tepat waktu (<= 4 tahun) beserta
 * label persentase langsung di dalam batang.
 */
import { computed, ref } from 'vue';
import AnalyticsTabbedContainer from '../AnalyticsTabbedContainer.vue';
import { baseOption, formatNumber, formatPercentage } from '../analyticsChartOptions';

// Kontrak payload indikator Rasio Kelulusan Tepat Waktu dari backend.
const props = defineProps({
    chart: { type: Object, default: () => ({}) },
    selectedLabel: { type: String, default: '' },
    loading: { type: Boolean, default: false },
});

// Event seleksi diteruskan ke panel analitik induk.
const emit = defineEmits(['select', 'reset']);

// Seri rasio kelulusan per program studi dan catatan akademik individu.
const series = computed(() => props.chart?.series || []);
const records = computed(() => props.chart?.records || []);
const summary = computed(() => props.chart?.summary || {});

// State urutan grafik dan tabel; false berarti capaian tertinggi ditampilkan lebih dahulu.
const sortAsc = ref(false);

// Target status visual dikirim backend agar tidak menjadi angka hardcode di Vue.
const statusTarget = computed(() => Number(summary.value.status_target || 60));

// Menentukan warna dan label status capaian berdasarkan realisasi kelulusan tepat waktu.
const getStatus = (percentage) => {
    if (percentage >= statusTarget.value) {
        return { key: 'green', label: 'Hijau: >= Target', color: '#0D542B' };
    }

    if (percentage >= statusTarget.value * 0.75) {
        return { key: 'yellow', label: 'Kuning: 75%-<100% Target', color: '#D97706' };
    }

    return { key: 'red', label: 'Merah: <75% Target', color: '#DC2626' };
};

// Menyiapkan baris visual dengan denominator, persentase, dan status warna.
const chartRows = computed(() => [...series.value]
    .map((item) => {
        return {
            ...item,
            denominator: Number(item.eligible || 0),
            onTime: Number(item.on_time || 0),
            late: Number(item.late || 0),
            percentage: Number(item.aee_realization || 0),
            status: {
                key: item.status_key,
                label: item.status_label,
                color: item.status_color,
            },
        };
    })
    .sort((left, right) => {
        const percentageDifference = sortAsc.value
            ? left.percentage - right.percentage
            : right.percentage - left.percentage;

        return percentageDifference || (sortAsc.value
            ? left.denominator - right.denominator
            : right.denominator - left.denominator);
    }));

// Opsi stacked bar horizontal dengan panjang berbasis jumlah mahasiswa.
const chartOption = computed(() => {
    const rows = chartRows.value;
    const maxDenominator = Math.max(...rows.map((item) => item.denominator), 0);
    const axisMax = Math.max(10, Math.ceil(maxDenominator / 10) * 10);
    // Segmen menempatkan belum tepat waktu (merah) di urutan pertama (sisi kiri) dan lulusan tepat waktu (hijau) di sisi kanan.
    const segmentDefinitions = [
        { key: 'late', valueKey: 'late', label: 'Merah: Belum Tepat Waktu (> 4 Thn)', color: '#DC2626', stackOrder: 0 },
        { key: 'on_time', valueKey: 'onTime', label: 'Hijau: Tepat Waktu (<= 4 Thn)', color: '#0D542B', stackOrder: 1 },
    ];

    return {
        ...baseOption(rows.reduce((total, item) => total + item.denominator, 0)),
        // Palet dikunci agar normalizer global mempertahankan makna setiap segmen.
        themePalette: segmentDefinitions.map((item) => item.color),
        // Label urutan dan legenda disajikan lewat toolbar HTML rapi di atas grafik.
        graphic: [],
        legend: { show: false },
        grid: { left: 24, right: 72, top: 24, bottom: 46, containLabel: true },
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

                return `${item.nama_prodi}<br/>Realisasi AEE (&le; 4 Thn): ${formatPercentage(item.percentage)} (${item.onTime}/${item.denominator} mhs)<br/>Belum Tepat Waktu (&gt; 4 Thn): ${formatNumber(item.late, 0)} mhs<br/>Status target: ${item.status.label}`;
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
                    formatter: ({ data }) => {
                        if (!data?.value || data.percentage < 7) return '';

                        // Tampilkan rasio jumlah jika segmen cukup lebar (>= 16%), atau persentase saja jika sedang
                        return data.percentage >= 16
                            ? `${formatPercentage(data.percentage)} (${data.count}/${data.total})`
                            : `${formatPercentage(data.percentage)}`;
                    },
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

// Tabel ringkasan numerik dengan pembilang, penyebut, dan status capaian AEE.
const tableData = computed(() => ({
    columns: [
        { key: 'name', label: 'Program Studi' },
        { key: 'population', label: 'Populasi S1', align: 'right' },
        { key: 'eligible', label: 'Denominator (Lulus)', align: 'right' },
        { key: 'on_time', label: 'Tepat Waktu (<= 4 Thn)', align: 'right' },
        { key: 'not_on_time', label: 'Belum Tepat Waktu (> 4 Thn)', align: 'right' },
        { key: 'aee_realization', label: 'AEE Realisasi (%)', align: 'right' },
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

// Narasi ringkas menjelaskan cakupan dan capaian AEE rasio kelulusan aktual.
const analysis = computed(() => ({
    title: 'Kilas AEE S1: Rasio Kelulusan Tepat Waktu (Maks. 4 Tahun)',
    statusBadge: `${formatPercentage(summary.value.aee_pt)} capaian AEE PT`,
    badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
    lead: `${formatNumber(summary.value.on_time_count, 0)} dari ${formatNumber(summary.value.eligible_count, 0)} lulusan S1 berhasil lulus tepat waktu dalam batas maksimal 4 tahun (8 semester).`,
    detail: `AEE (Apparent Educational Efficiency) menghitung rasio kelulusan tepat waktu mahasiswa S1 dengan masa studi maksimal 4 tahun berstatus Lulus. Target universitas ${formatPercentage(statusTarget.value)}. Mahasiswa dengan status aktif, cuti, pindah, atau drop out tidak masuk populasi kelulusan.`,
}));

// Konfigurasi tampilan grafik dan tabel AEE Rasio Kelulusan Tepat Waktu.
const views = computed(() => [{
    key: 'aee',
    chartType: 'stacked_horizontal',
    tabLabel: 'AEE: Kelulusan Tepat Waktu (Maks. 4 Thn)',
    title: 'AEE S1: Rasio Kelulusan Tepat Waktu (Maks. 4 Tahun) per Program Studi',
    description: 'AEE (Apparent Educational Efficiency) S1: Batang horizontal menunjukkan jumlah lulusan S1: hijau untuk tepat waktu (<= 4 tahun) lengkap dengan persentase di dalam batang, dan merah untuk belum tepat waktu (> 4 tahun).',
    option: chartOption.value,
    tableData: tableData.value,
    height: Math.max(380, Math.min(series.value.length * 42 + 145, 700)),
    analysis: analysis.value,
    drillDownColumns: [
        { key: 'nama', label: 'Nama Mahasiswa' },
        { key: 'nim', label: 'NIM' },
        { key: 'nama_prodi', label: 'Program Studi' },
        { key: 'angkatan_masuk', label: 'Angkatan Masuk' },
        { key: 'status_mahasiswa', label: 'Status Akademik' },
        { key: 'is_lulus_tepat_waktu', label: 'Kelulusan' },
    ],
}]);
</script>

<template>
    <AnalyticsTabbedContainer
        :views="views"
        :rows="records"
        :selected-label="selectedLabel"
        :loading="loading"
        indicator-badge="IKU S1 · AEE"
        @select="emit('select', $event)"
        @reset="emit('reset')"
    >
        <template #before-chart>
            <div class="mb-3 space-y-2.5">
                <!-- Toolbar Kontrol: Toggle Urutan Capaian -->
                <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                    <button
                        type="button"
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 font-semibold text-slate-700 transition-colors hover:bg-slate-100"
                        @click="sortAsc = !sortAsc"
                    >
                        <span>Urutan:</span>
                        <span class="font-bold text-[#0D542B]">{{ sortAsc ? 'Terendah -> Tertinggi' : 'Tertinggi -> Terendah' }}</span>
                    </button>
                    <span class="text-[11px] text-slate-500">AEE menghitung lulusan S1 tepat waktu (&le; 4 tahun) dengan status akademik Lulus.</span>
                </div>

                <!-- Legenda Status Capaian yang Konsisten dengan Palet & Ambang Target -->
                <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200/80 bg-slate-50/70 px-3 py-2 text-[11px] text-slate-600">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5">
                        <span class="font-bold text-slate-800">Status Capaian:</span>
                        <span class="inline-flex items-center gap-1.5">
                            <span class="h-2.5 w-2.5 rounded-xs bg-[#DC2626]"></span>
                            <strong>Merah (Kiri):</strong> Belum Tepat Waktu (&gt; 4 Tahun)
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <span class="h-2.5 w-2.5 rounded-xs bg-[#0D542B]"></span>
                            <strong>Hijau (Kanan):</strong> Tepat Waktu (&le; 4 Tahun)
                        </span>
                    </div>
                </div>
            </div>
        </template>
    </AnalyticsTabbedContainer>
</template>
