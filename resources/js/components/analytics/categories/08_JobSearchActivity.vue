<script setup>
/**
 * Komponen Indikator 08: Keaktifan Mencari Pekerjaan (Instrumen F10).
 *
 * Mengelola visualisasi keaktifan pencarian pekerjaan alumni dalam 4 minggu terakhir:
 * - Sesuai kuesioner resmi Tracer Study Dikti (Instrumen F10):
 *   "Apakah anda aktif mencari pekerjaan dalam 4 minggu terakhir? Pilihlah Satu Jawaban. KEMUDIAN LANJUT KE F17"
 * - Hanya menampilkan opsi jawaban yang memiliki respons.
 * - Menyediakan awan kata untuk penjelasan bebas pada opsi Lainnya.
 */
import { computed } from 'vue';
import AnalyticsTabbedContainer from '../AnalyticsTabbedContainer.vue';
import {
    baseOption,
    escapeHtml,
    formatNumber,
    formatPercentage,
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

// Opsi kosong tidak ditampilkan pada grafik atau tabel agar pembacaan hanya berfokus pada data aktual.
const filledSeries = computed(() => series.value.filter((item) => seriesMetric(item) > 0));

// -----------------------------------------------------------------------------
// HORIZONTAL BAR: Distribusi Keaktifan Mencari Kerja (F10)
// -----------------------------------------------------------------------------
const barOption = computed(() => {
    // Susun opsi F10 yang benar-benar terisi sesuai urutan resmi kuesioner Dikti.
    const rows = filledSeries.value.map((item, idx) => {
        const label = seriesLabel(item);
        const count = seriesMetric(item);
        const selected = !props.selectedLabel || props.selectedLabel === label;
        const optNum = item.kode_opsi || (idx + 1);
        return {
            name: `${optNum} - ${label}`,
            rawLabel: label,
            value: count,
            percentage: item.percentage || 0,
            itemStyle: {
                color: '#0D542B',
                opacity: selected ? 1 : 0.22,
                borderRadius: [0, 4, 4, 0],
            },
        };
    });

    const chartHeight = Math.max(380, rows.length * 56 + 80);

    return {
        ...baseOption(validResponses.value),
        grid: {
            left: 20,
            right: 90,
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
            inverse: true,
            data: rows.map((r) => r.name),
            axisLabel: {
                width: 360,
                overflow: 'break',
                fontSize: 11,
                lineHeight: 15,
                color: '#1E293B',
                fontWeight: 600,
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
            barMaxWidth: 26,
            data: rows,
            label: {
                show: true,
                position: 'right',
                formatter: ({ data }) => `${formatNumber(data.value, 0)} (${formatPercentage(data.percentage)})`,
                fontSize: 11,
                fontWeight: 600,
                color: '#334155',
            },
        }],
        _dynamicHeight: chartHeight,
    };
});

const barTable = computed(() => ({
    columns: [
        { key: 'no', label: 'No.', align: 'center' },
        { key: 'status', label: 'Opsi Jawaban (Instrumen F10)' },
        { key: 'count', label: 'Jumlah Alumni (n)', align: 'right' },
        { key: 'percentage', label: 'Persentase', align: 'right' },
    ],
    rows: filledSeries.value.map((item, idx) => ({
        no: item.kode_opsi || (idx + 1),
        status: seriesLabel(item),
        count: formatNumber(seriesMetric(item), 0),
        percentage: formatPercentage(item.percentage || 0),
    })),
}));

// Isian bebas opsi Lainnya ditampilkan terpisah agar pola kata dapat dibaca tanpa mencampurnya dengan pilihan baku F10.
const lainnyaRecords = computed(() => records.value.filter((record) => {
    const category = String(record.category || '').trim().toLowerCase();
    const detail = String(record.detail || '').trim();

    return (category === 'lainnya' || category === 'other') && detail !== '';
}));

const lainnyaTable = computed(() => ({
    columns: [
        { key: 'nim', label: 'NIM' },
        { key: 'nama', label: 'Nama Alumni' },
        { key: 'nama_prodi', label: 'Program Studi' },
        { key: 'answer', label: 'Isian Aktivitas Lainnya' },
    ],
    rows: lainnyaRecords.value.map((record) => ({
        nim: record.nim || '-',
        nama: record.nama || '-',
        nama_prodi: record.nama_prodi || '-',
        answer: record.detail,
    })),
}));

// Konfigurasi views untuk kontainer terpadu
const views = computed(() => {
    const list = [
        {
            key: 'activity_bar',
            chartType: 'horizontal_bar',
            tabLabel: 'Keaktifan 4 Minggu Terakhir',
            title: 'Keaktifan mencari pekerjaan dalam 4 minggu terakhir',
            description: 'Instrumen F10: Apakah anda aktif mencari pekerjaan dalam 4 minggu terakhir? Pilihlah Satu Jawaban. KEMUDIAN LANJUT KE F17.',
            option: barOption.value,
            tableData: barTable.value,
            height: Math.max(380, filledSeries.value.length * 56 + 80),
            analysis: {
                title: 'Kilas Keaktifan dan Transisi Karir Alumni',
                statusBadge: 'Instrumen F10 Dikti',
                badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
                lead: 'Instrumen F10 merekam status keaktifan alumni dalam mencari kerja pada 4 minggu terakhir sebelum melangkah ke instrumen evaluasi kompetensi F17.',
                detail: 'Sebagian besar alumni memilih opsi "Tidak" karena telah berstatus bekerja atau menempuh studi lanjut. Bagi lulusan yang berada pada masa transisi, kategori didominasi oleh mereka yang sedang menunggu pengumuman hasil lamaran kerja serta yang telah memiliki kepastian waktu mulai bekerja dalam 2 minggu ke depan.',
            },
        },
    ];

    if (lainnyaRecords.value.length > 0) {
        list.push({
            key: 'lainnya_detail',
            chartType: 'word_cloud',
            tabLabel: `Awan Kata Lainnya (${lainnyaRecords.value.length})`,
            title: 'Awan Kata Isian Aktivitas Pencarian Kerja Lainnya',
            description: 'Kata kunci dari penjelasan bebas alumni yang memilih opsi Lainnya pada instrumen F10.',
            wordCloudTitle: 'Awan Kata Aktivitas Pencarian Kerja Lainnya',
            wordCloudSubtitle: 'Visualisasi kata kunci hanya dari isian teks bebas yang benar-benar ditulis alumni.',
            wordCloudRecords: lainnyaRecords.value,
            option: barOption.value,
            tableData: lainnyaTable.value,
            height: 380,
            analysis: {
                title: 'Kilas Isian Bebas F10',
                statusBadge: 'Awan Kata',
                badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
                lead: `${lainnyaRecords.value.length} alumni memberikan penjelasan aktivitas pencarian kerja di luar opsi baku F10.`,
                detail: 'Awan kata membantu melihat tema yang muncul tanpa mengubah pilihan jawaban resmi pada distribusi utama.',
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
        indicator-badge="Indikator 11"
        @select="emit('select', $event)"
        @reset="emit('reset')"
    />
</template>
