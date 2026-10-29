<script setup>
/**
 * Komponen Indikator 15: Alasan Mengambil Pekerjaan Tidak Sesuai Pendidikan (F16).
 *
 * Mengadopsi Gambar 4.16 Laporan Tracer Study UKDW:
 * 1. Horizontal Bar: Seluruh opsi resmi instrumen kuesioner F16 tanpa terpotong
 * 2. Tabel Lengkap Tabulasi Alasan & Isian Lainnya
 */
import { computed } from 'vue';
import AnalyticsTabbedContainer from '../AnalyticsTabbedContainer.vue';
import {
    baseOption,
    escapeHtml,
    formatNumber,
    formatPercentage,
    recordCategory,
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

// -----------------------------------------------------------------------------
// 1. HORIZONTAL BAR: Seluruh 14 Opsi Resmi F16 (Gambar 4.16 PDF Laporan Resmi)
// -----------------------------------------------------------------------------
const barOption = computed(() => {
    // Balik urutan agar opsi dengan frekuensi terbesar berada di paling atas sumbu Y
    const rows = [...series.value].map((item) => {
        const label = seriesLabel(item);
        const count = seriesMetric(item);
        const selected = !props.selectedLabel || props.selectedLabel === label;
        return {
            name: label,
            value: count,
            percentage: item.percentage || 0,
            itemStyle: {
                color: '#0D542B',
                opacity: selected ? 1 : 0.22,
                borderRadius: [0, 4, 4, 0],
            },
        };
    }).reverse();

    return {
        ...baseOption(validResponses.value),
        grid: {
            left: 20,
            right: 64,
            top: 24,
            bottom: 30,
            containLabel: true,
        },
        xAxis: {
            type: 'value',
            name: 'Jumlah Alumni',
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
        },
        yAxis: {
            type: 'category',
            data: rows.map((r) => r.name),
            axisLabel: {
                width: 340, // Ruang lebar agar kalimat panjang opsi tidak terpotong
                overflow: 'break',
                fontSize: 11,
                lineHeight: 14,
                color: '#334155',
            },
        },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            formatter: (params) => {
                const item = rows[params[0]?.dataIndex];
                if (!item) return '';
                return `<strong>${escapeHtml(item.name)}</strong><br>Jumlah: <strong>${formatNumber(item.value, 0)} alumni</strong><br>Proporsi: <strong>${formatPercentage(item.percentage)}</strong>`;
            },
        },
        series: [{
            name: 'Jumlah Alumni',
            type: 'bar',
            barMaxWidth: 22,
            data: rows,
            label: {
                show: true,
                position: 'right',
                formatter: ({ data }) => data.value > 0 ? `${formatNumber(data.value, 0)} (${formatPercentage(data.percentage)})` : '0',
                fontSize: 10,
                color: '#475569',
            },
        }],
    };
});

const barTable = computed(() => ({
    columns: [
        { key: 'name', label: 'Alasan Mengambil Pekerjaan (Instrumen F16)' },
        { key: 'count', label: 'Jumlah Responden (n)', align: 'right' },
        { key: 'percentage', label: 'Persentase', align: 'right' },
    ],
    rows: series.value.map((item) => ({
        name: seriesLabel(item),
        count: formatNumber(seriesMetric(item), 0),
        percentage: formatPercentage(item.percentage || 0),
    })),
}));

// Rincian alumni pada opsi lainnya
const lainnyaRecords = computed(() => {
    return records.value.filter((r) => {
        const ans = String(recordCategory(r) || '').toLowerCase();
        return ans.includes('lain');
    });
});

const lainnyaTable = computed(() => ({
    columns: [
        { key: 'nim', label: 'NIM' },
        { key: 'nama', label: 'Nama Alumni' },
        { key: 'nama_prodi', label: 'Program Studi' },
        { key: 'answer', label: 'Isian Alasan' },
    ],
    rows: lainnyaRecords.value.map((r) => ({
        nim: r.nim || '-',
        nama: r.nama || '-',
        nama_prodi: r.nama_prodi || '-',
        answer: r.answer || 'Lainnya',
    })),
}));

// Konfigurasi views untuk kontainer terpadu
const views = computed(() => {
    const list = [
        {
            key: 'reasons_distribution',
            chartType: 'horizontal_bar',
            tabLabel: 'Distribusi Alasan',
            title: 'Alasan mengambil pekerjaan yang tidak sesuai pendidikan',
            description: 'Respon alumni terhadap alasan mengambil pekerjaan yang tidak sesuai pendidikan (mengadopsi Gambar 4.16 Laporan Tracer Study UKDW).',
            option: barOption.value,
            tableData: barTable.value,
            height: Math.max(560, series.value.length * 38 + 90),
            analysis: {
                title: 'Kilas Analisis Alasan Ketidaksesuaian (Subbab 4.16)',
                statusBadge: 'Gambar 4.16',
                badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
                lead: 'Pada bagian ini responden dapat memilih alternatif pilihan jawaban lebih dari 1 (multiple choice). Gambar 4.16 memberikan informasi mengenai alasan alumni tidak mengambil pekerjaan sesuai dengan jalur pendidikannya.',
                detail: 'Sebagian besar alumni menyatakan bekerja di tempat yang sesuai dengan pendidikannya. Bagi alumni yang memilih pekerjaan tidak sesuai pendidikan, alasan dominan didorong oleh prospek karier yang lebih baik, pekerjaan yang lebih menarik, pendapatan yang lebih tinggi, serta faktor kenyamanan dan kedekatan lokasi tempat kerja.',
            },
        },
    ];

    if (lainnyaRecords.value.length > 0) {
        list.push({
            key: 'lainnya_detail',
            chartType: 'table',
            tabLabel: `Isian Lainnya (${lainnyaRecords.value.length})`,
            title: 'Rincian isian alumni pada opsi alasan lainnya',
            description: 'Daftar jawaban bebas dan pertimbangan personal alternatif yang disampaikan alumni pada instrumen F16.',
            option: barOption.value,
            tableData: lainnyaTable.value,
            height: 380,
            analysis: {
                title: 'Kilas Isian Bebas F16',
                statusBadge: 'Respon Mandiri',
                badgeClass: 'bg-amber-50 text-amber-800 border border-amber-200',
                lead: `Terdapat ${lainnyaRecords.value.length} alumni yang mencantumkan penjelasan alasan khusus di luar opsi baku.`,
                detail: 'Membantu universitas memahami motivasi unik karir alumni dalam dinamika pasar tenaga kerja modern.',
            },
        });
    }

    return list;
});
</script>

<template>
    <AnalyticsTabbedContainer
        :views="views"
        :rows="records"
        :selected-label="selectedLabel"
        :loading="loading"
        indicator-badge="Indikator 15"
        @select="emit('select', $event)"
        @reset="emit('reset')"
    />
</template>
