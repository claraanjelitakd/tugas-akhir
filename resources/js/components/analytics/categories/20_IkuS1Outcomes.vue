<script setup>
/**
 * Komponen indikator capaian karier lulusan S1.
 *
 * Formula bobot telah dihitung backend dari status karier, masa tunggu, UMP,
 * dan tanggal studi lanjut. Komponen hanya menampilkan hasil dan rincian alumni
 * yang dipilih langsung dari grafik.
 */
import { computed } from 'vue';
import AnalyticsTabbedContainer from '../AnalyticsTabbedContainer.vue';
import { analyticsColors, baseOption, formatNumber, formatPercentage } from '../analyticsChartOptions';

// Kontrak payload indikator capaian lulusan dari backend.
const props = defineProps({
    chart: { type: Object, default: () => ({}) },
    selectedLabel: { type: String, default: '' },
    loading: { type: Boolean, default: false },
});

// Event seleksi diteruskan ke panel analitik induk.
const emit = defineEmits(['select', 'reset']);

// Seri kategori karier dan rincian klasifikasi individu.
const series = computed(() => props.chart?.series || []);
const records = computed(() => props.chart?.records || []);
const summary = computed(() => props.chart?.summary || {});

// Opsi batang kontribusi berbobot per kategori karier.
const chartOption = computed(() => ({
    ...baseOption(),
    grid: { left: 24, right: 70, top: 22, bottom: 34, containLabel: true },
    xAxis: {
        type: 'value',
        min: 0,
        axisLabel: { formatter: '{value}%', color: '#64748B' },
        splitLine: { lineStyle: { color: '#E2E8F0' } },
    },
    yAxis: {
        type: 'category',
        inverse: true,
        data: series.value.map((item) => item.label),
        axisLabel: { color: '#334155', width: 190, overflow: 'truncate' },
        axisTick: { show: false },
    },
    tooltip: {
        trigger: 'axis',
        axisPointer: { type: 'shadow' },
        formatter: (params) => {
            const item = series.value[params?.[0]?.dataIndex];
            return item ? `${item.label}<br/>Responden: ${item.count}<br/>Skor bobot: ${item.weighted_score}<br/>Kontribusi: ${formatPercentage(item.percentage)}` : '';
        },
    },
    series: [{
        name: 'Kontribusi berbobot',
        type: 'bar',
        barMaxWidth: 32,
        data: series.value.map((item) => ({ name: item.label, value: item.percentage, category: item.label })),
        itemStyle: { color: analyticsColors.green, borderRadius: [0, 4, 4, 0] },
        label: { show: true, position: 'right', color: '#334155', formatter: (params) => formatPercentage(series.value[params.dataIndex]?.percentage) },
    }],
}));

// Tabel kontribusi per kategori agar formula dapat ditelusuri tanpa membaca tooltip.
const tableData = computed(() => ({
    columns: [
        { key: 'name', label: 'Kategori' },
        { key: 'count', label: 'Responden', align: 'right' },
        { key: 'weighted_score', label: 'Σ Bobot', align: 'right' },
        { key: 'percentage', label: 'Kontribusi', align: 'right' },
    ],
    rows: series.value.map((item) => ({
        name: item.label,
        count: formatNumber(item.count, 0),
        weighted_score: formatNumber(item.weighted_score, 2),
        percentage: formatPercentage(item.percentage),
    })),
}));

// Narasi ringkas menjelaskan denominator dan skor akhir indikator.
const analysis = computed(() => ({
    title: 'Kilas Capaian Karier S1',
    statusBadge: formatPercentage(summary.value.percentage),
    badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
    lead: `${formatNumber(summary.value.valid_respondents, 0)} responden lulusan masuk denominator dengan total bobot ${formatNumber(summary.value.weighted_score, 2)}.`,
    detail: 'Karyawan, founder/co-founder, freelancer, studi lanjut, dan wirausaha lainnya dipisahkan berdasarkan data terstruktur. Wirausaha lainnya tetap tampil untuk audit, tetapi tidak diberi bobot tanpa role yang jelas.',
}));

// Konfigurasi tampilan grafik dan tabel capaian lulusan.
const views = computed(() => [{
    key: 'outcomes',
    chartType: 'horizontal_bar',
    tabLabel: 'Capaian Karier',
    title: 'Persentase Lulusan S1 Bekerja, Berwirausaha, atau Studi Lanjut',
    description: 'Nilai merupakan Σ bobot / jumlah responden valid × 100%; ambang gaji memakai UMP tahun acuan.',
    option: chartOption.value,
    tableData: tableData.value,
    height: Math.max(340, Math.min(series.value.length * 54 + 120, 560)),
    analysis: analysis.value,
    // Kolom drill-down mengikuti kebutuhan Data Alumni dan tetap bersumber dari payload backend.
    drillDownColumns: [
        { key: 'nama', label: 'Nama Alumni' },
        { key: 'nim', label: 'NIM' },
        { key: 'nama_prodi', label: 'Program Studi' },
        { key: 'career_status_label', label: 'Status Karier' },
        { key: 'nama_perusahaan', label: 'Perusahaan / Usaha' },
        { key: 'lokasi_kerja', label: 'Lokasi Cabang / Kerja' },
        { key: 'nama_atasan', label: 'Atasan' },
        { key: 'posisi_jabatan', label: 'Jabatan' },
        { key: 'bidang_pekerjaan', label: 'Bidang Pekerjaan' },
        { key: 'divisi', label: 'Divisi' },
        { key: 'career_role', label: 'Peran Wirausaha' },
        { key: 'skills', label: 'Skills' },
        { key: 'study_detail', label: 'Studi Lanjut' },
        { key: 'weight', label: 'Bobot' },
    ],
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
