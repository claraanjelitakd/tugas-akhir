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
import { baseOption, formatNumber, formatPercentage } from '../analyticsChartOptions';

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

// Opsi batang jumlah alumni dengan warna status kontribusi dari backend.
const chartOption = computed(() => ({
    ...baseOption(),
    grid: { left: 24, right: 92, top: 22, bottom: 52, containLabel: true },
    xAxis: {
        type: 'value',
        min: 0,
        minInterval: 1,
        name: 'Jumlah alumni (orang)',
        nameLocation: 'middle',
        nameGap: 30,
        axisLabel: { formatter: (value) => formatNumber(value, 0), color: '#64748B' },
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
            return item
                ? `${item.label}<br/>Jumlah alumni: ${formatNumber(item.count, 0)}<br/>Valid berbobot: ${formatNumber(item.valid_count, 0)}<br/>Σ bobot: ${formatNumber(item.weighted_score, 2)}<br/>Kontribusi: ${formatPercentage(item.percentage)} dari ${formatNumber(item.percentage_denominator_count, 0)} responden valid<br/>Status: ${item.status_label}`
                : '';
        },
    },
    series: [{
        name: 'Jumlah alumni',
        type: 'bar',
        barMaxWidth: 32,
        data: series.value.map((item) => ({
            name: item.label,
            value: item.count,
            category: item.label,
            itemStyle: { color: item.status_color, borderRadius: [0, 4, 4, 0] },
        })),
        label: { show: true, position: 'right', color: '#334155', formatter: (params) => formatPercentage(series.value[params.dataIndex]?.percentage) },
    }],
}));

// Tabel kontribusi per kategori agar formula dapat ditelusuri tanpa membaca tooltip.
const tableData = computed(() => ({
    columns: [
        { key: 'name', label: 'Kategori' },
        { key: 'count', label: 'Jumlah alumni', align: 'right' },
        { key: 'valid_count', label: 'Valid berbobot', align: 'right' },
        { key: 'weighted_score', label: 'Σ Bobot', align: 'right' },
        { key: 'percentage', label: 'Kontribusi', align: 'right' },
    ],
    rows: series.value.map((item) => ({
        name: item.label,
        count: formatNumber(item.count, 0),
        valid_count: formatNumber(item.valid_count, 0),
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
    detail: `Panjang bar menunjukkan jumlah alumni dalam kategori. Persentase dan warna menunjukkan kontribusi Σ bobot terhadap seluruh ${formatNumber(summary.value.percentage_denominator_count, 0)} responden valid IKU, bukan persentase di dalam kategori. Warna mengikuti response rate: hijau ≥50%, kuning 30%–<50%, merah <30%, dan abu-abu jika tidak berbobot.`,
}));

// Konfigurasi tampilan grafik dan tabel capaian lulusan.
const views = computed(() => [{
    key: 'outcomes',
    chartType: 'horizontal_bar',
    tabLabel: 'Capaian Karier',
    title: 'Jumlah Alumni S1 Bekerja, Berwirausaha, atau Studi Lanjut',
    description: 'Panjang bar menunjukkan jumlah alumni per kategori; label dan warna menunjukkan kontribusi Σ bobot terhadap seluruh responden valid IKU. Ambang gaji memakai UMP tahun acuan.',
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
