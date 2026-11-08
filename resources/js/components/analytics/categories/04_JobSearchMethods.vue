<script setup>
/**
 * Komponen Indikator 04: Cara Mencari Pekerjaan (F4).
 *
 * Mengelola visualisasi saluran pencarian pekerjaan resmi Tracer Study UKDW:
 * 1. Horizontal Bar: Opsi instrumen F4 yang benar-benar terisi, tanpa menampilkan kategori nol.
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
// 1. HORIZONTAL BAR: Distribusi Seluruh Saluran (Opsi Terisi)
// -----------------------------------------------------------------------------
const barOption = computed(() => {
    // Urutkan dari frekuensi terendah ke tertinggi agar di horizontal bar tampak tertinggi di atas
    const rows = [...series.value].filter((item) => seriesMetric(item) > 0).map((item) => {
        const label = item.short_label || seriesLabel(item);
        const fullLabel = item.full_label || label;
        const count = seriesMetric(item);
        const selected = !props.selectedLabel || props.selectedLabel === label || props.selectedLabel === fullLabel;
        return {
            name: label,
            fullLabel,
            value: count,
            percentage: item.percentage || 0,
            itemStyle: {
                color: '#0D542B',
                opacity: selected ? 1 : 0.22,
                borderRadius: [0, 4, 4, 0],
            },
        };
    }).reverse();

    const chartHeight = Math.max(380, rows.length * 48 + 80);

    return {
        ...baseOption(validResponses.value),
        grid: {
            left: 20,
            right: 130,
            top: 24,
            bottom: 44,
            containLabel: true,
        },
        xAxis: {
            type: 'value',
            name: 'Jumlah Alumni',
            nameLocation: 'middle',
            nameGap: 28,
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
        },
        yAxis: {
            type: 'category',
            data: rows.map((r) => r.name),
            axisLabel: {
                width: 200,
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
                const hasDifferentFull = item.fullLabel && item.fullLabel !== item.name;
                return `
                    <div style="min-width: 190px;">
                        <div style="font-weight: 700; color: #0F172A; font-size: 13px; ${hasDifferentFull ? 'margin-bottom: 2px;' : 'border-bottom: 1px solid #F1F5F9; padding-bottom: 4px; margin-bottom: 6px;'}">
                            ${escapeHtml(item.name)}
                        </div>
                        ${hasDifferentFull ? `<div style="font-size: 11px; color: #64748B; border-bottom: 1px solid #F1F5F9; padding-bottom: 4px; margin-bottom: 6px; line-height: 1.35;">${escapeHtml(item.fullLabel)}</div>` : ''}
                        <div style="font-size: 12px; color: #475569; margin-bottom: 2px;">
                            Jumlah: <strong style="color: #0D542B; font-size: 13px;">${formatNumber(item.value, 0)} alumni</strong>
                        </div>
                        <div style="font-size: 11px; color: #64748B;">
                            Proporsi: <strong style="color: #334155;">${formatPercentage(item.percentage)}%</strong>
                        </div>
                    </div>
                `;
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
                distance: 8,
                formatter: ({ data }) => data.value > 0 ? `${formatNumber(data.value, 0)} alumni` : '0',
                fontSize: 11,
                fontWeight: 700,
                color: '#0F172A',
            },
            labelLayout: {
                hideOverlap: false,
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
    rows: series.value.filter((item) => seriesMetric(item) > 0).map((item) => {
        const short = item.short_label || seriesLabel(item);
        const full = item.full_label || short;
        return {
            name: short !== full ? `${short} (${full})` : short,
            count: formatNumber(seriesMetric(item), 0),
            percentage: formatPercentage(item.percentage || 0),
        };
    }),
}));

// -----------------------------------------------------------------------------
// 2. TABEL KHUSUS: Isian Saluran Lainnya (Transparansi Respon Alumni)
// -----------------------------------------------------------------------------
const lainnyaRecords = computed(() => {
    return records.value.filter((r) => {
        const cat = String(r.category || recordCategory(r) || '').toLowerCase();
        const ans = String(r.answer || '').toLowerCase();
        return cat.includes('lain') || cat.includes('other') || ans.includes('lain') || ans.includes('other');
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
        answer: r.detail || r.answer || 'Lainnya',
    })),
}));

// Konfigurasi views untuk kontainer terpadu
const views = computed(() => {
    const filledSeries = series.value.filter((item) => seriesMetric(item) > 0);
    const topMethod = filledSeries[0];
    const secondMethod = filledSeries[1];

    const list = [
        {
            key: 'distribution',
            chartType: 'horizontal_bar',
            tabLabel: 'Distribusi Saluran',
            title: 'Distribusi saluran pencarian pekerjaan alumni UKDW',
            description: 'Opsi resmi instrumen Tracer Study F4 yang memiliki jawaban terisi untuk penelusuran saluran perolehan kerja alumni.',
            option: barOption.value,
            tableData: barTable.value,
            height: Math.max(380, filledSeries.length * 40 + 90),
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

    // Jika terdapat catatan isian responden pada opsi "Lainnya", hadirkan tab Word Cloud interaktif
    if (lainnyaRecords.value.length > 0) {
        list.push({
            key: 'lainnya_detail',
            chartType: 'word_cloud',
            tabLabel: `Awan Kata Lainnya (${lainnyaRecords.value.length})`,
            title: 'Awan Kata (Word Cloud) Isian Saluran Kerja Lainnya',
            description: 'Visualisasi frekuensi kata kunci dari saluran alternatif dan metode kerja yang disampaikan alumni pada instrumen F4.',
            wordCloudTitle: 'Awan Kata (Word Cloud) Saluran Pencarian Kerja Lainnya',
            wordCloudSubtitle: 'Visualisasi kata kunci dari metode alternatif yang dituliskan alumni di luar pilihan baku instrumen F4.',
            wordCloudRecords: lainnyaRecords.value,
            option: barOption.value,
            tableData: lainnyaTable.value,
            height: 380,
            analysis: {
                title: 'Kilas Isian Saluran Alternatif',
                statusBadge: 'Word Cloud',
                badgeClass: 'bg-amber-50 text-amber-800 border border-amber-200',
                lead: `Terdapat ${lainnyaRecords.value.length} alumni yang mencantumkan saluran kerja di luar pilihan baku yang diolah dalam bentuk awan kata interaktif.`,
                detail: 'Isian ini mencerminkan dinamika saluran kerja modern, seperti proyek portofolio mandiri, platform freelancing internasional, hackathon, dan rekrutmen tertutup melalui komunitas profesional.',
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
        indicator-badge="Indikator 07"
        @select="emit('select', $event)"
        @reset="emit('reset')"
    />
</template>
