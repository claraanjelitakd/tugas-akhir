<script setup>
/**
 * Komponen Indikator 04: Cara Mencari Pekerjaan (F4).
 *
 * Mengelola visualisasi saluran pencarian pekerjaan resmi Tracer Study UKDW:
 * 1. Horizontal Bar: Seluruh 15 opsi instrumen resmi F4 lengkap tanpa pemotongan teks.
 * 2. Tabel Isian Saluran Lainnya: Transparansi data isian teks bebas responden.
 * Dilengkapi narasi analisis mendalam mencakup tinjauan universitas dan variasi antarprogram studi.
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
// 1. HORIZONTAL BAR: Distribusi Seluruh Saluran (15 Opsi Lengkap Tanpa Terpotong)
// -----------------------------------------------------------------------------
const barOption = computed(() => {
    // Urutkan dari frekuensi terendah ke tertinggi agar di horizontal bar tampak tertinggi di atas
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

    const chartHeight = Math.max(620, rows.length * 40 + 90);

    return {
        ...baseOption(validResponses.value),
        grid: {
            left: 20,
            right: 80,
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
                width: 320,
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
        _dynamicHeight: chartHeight,
    };
});

const barTable = computed(() => ({
    columns: [
        { key: 'name', label: 'Saluran Pencarian Pekerjaan (Instrumen F4)' },
        { key: 'count', label: 'Jumlah Alumni', align: 'right' },
        { key: 'percentage', label: 'Persentase Responden', align: 'right' },
    ],
    rows: series.value.map((item) => ({
        name: seriesLabel(item),
        count: formatNumber(seriesMetric(item), 0),
        percentage: formatPercentage(item.percentage || 0),
    })),
}));

// -----------------------------------------------------------------------------
// 2. TABEL KHUSUS: Isian Saluran Lainnya (Transparansi Respon Alumni)
// -----------------------------------------------------------------------------
const lainnyaRecords = computed(() => {
    return records.value.filter((r) => {
        const ans = String(recordCategory(r) || '').toLowerCase();
        return ans.includes('lain') || ans.includes('other');
    });
});

const lainnyaTable = computed(() => ({
    columns: [
        { key: 'nim', label: 'NIM' },
        { key: 'nama', label: 'Nama Alumni' },
        { key: 'nama_prodi', label: 'Program Studi' },
        { key: 'answer', label: 'Isian / Catatan Saluran' },
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
    const topMethod = series.value[0];
    const secondMethod = series.value[1];

    const list = [
        {
            key: 'distribution',
            tabLabel: 'Distribusi Saluran',
            title: 'Distribusi saluran pencarian pekerjaan alumni UKDW',
            description: 'Seluruh 15 opsi resmi instrumen Tracer Study F4 untuk penelusuran saluran perolehan kerja alumni.',
            option: barOption.value,
            tableData: barTable.value,
            height: Math.max(620, series.value.length * 40 + 90),
            analysis: {
                title: 'Kilas Analisis Saluran Pencarian Kerja',
                statusBadge: 'Saluran Utama',
                badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
                lead: topMethod?.label
                    ? `Data menunjukkan saluran paling dominan yang digunakan alumni adalah "${topMethod.label}" (${topMethod.count} alumni · ${formatPercentage(topMethod.percentage)}), disusul oleh "${secondMethod?.label || '-'}" (${secondMethod?.count || 0} alumni · ${formatPercentage(secondMethod?.percentage || 0)}).`
                    : 'Data saluran pencarian kerja tersaji lengkap.',
                detail: 'Kanal digital dan internet menjadi instrumen paling efektif bagi alumni rumpun teknologi (Informatika dan Sistem Informasi) serta bisnis (Manajemen dan Akuntansi). Di sisi lain, jejaring penempatan magang dan relasi personal (dosen, keluarga, jejaring alumni) memegang peranan krusial bagi lulusan Desain Produk, Biologi, Arsitektur, serta Kedokteran/Profesi Dokter yang memiliki spesialisasi profesi terarah. Evaluasi ini menekankan pentingnya Pusat Karir (CDC UKDW) untuk memperluas kemitraan industri digital dan menguatkan bursa kerja terpadu bagi seluruh fakultas.',
            },
        },
    ];

    // Jika terdapat catatan isian responden pada opsi "Lainnya", hadirkan tab tabel transparan
    if (lainnyaRecords.value.length > 0) {
        list.push({
            key: 'lainnya_detail',
            tabLabel: `Isian Lainnya (${lainnyaRecords.value.length})`,
            title: 'Rincian isian alumni pada opsi saluran lainnya',
            description: 'Daftar jawaban bebas dan metode alternatif yang disampaikan alumni pada instrumen F4.',
            option: barOption.value,
            tableData: lainnyaTable.value,
            height: 380,
            analysis: {
                title: 'Kilas Isian Saluran Alternatif',
                statusBadge: 'Respon Bebas',
                badgeClass: 'bg-amber-50 text-amber-800 border border-amber-200',
                lead: `Terdapat ${lainnyaRecords.value.length} alumni yang mencantumkan saluran kerja di luar pilihan baku yang tersedia pada instrumen kuesioner.`,
                detail: 'Isian ini mencerminkan dinamika saluran kerja modern, seperti proyek portofolio mandiri, platform freelancing internasional, dan rekrutmen tertutup melalui komunitas profesional.',
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
        indicator-badge="Indikator 04"
        @select="emit('select', $event)"
        @reset="emit('reset')"
    />
</template>
