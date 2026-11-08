<script setup>
/**
 * Komponen Indikator 08: Keaktifan Mencari Pekerjaan (Instrumen F10).
 *
 * Mengelola visualisasi keaktifan pencarian pekerjaan alumni dalam 4 minggu terakhir:
 * - Sesuai kuesioner resmi Tracer Study Dikti (Instrumen F10):
 *   "Apakah anda aktif mencari pekerjaan dalam 4 minggu terakhir? Pilihlah Satu Jawaban. KEMUDIAN LANJUT KE F17"
 * - Menampilkan seluruh 5 opsi jawaban berurutan (1 s.d. 5) secara jelas.
 * - Dilengkapi persentase pada batang, tabel rincian data responden, dan narasi analisis komprehensif.
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

// -----------------------------------------------------------------------------
// HORIZONTAL BAR: Distribusi Keaktifan Mencari Kerja (F10)
// -----------------------------------------------------------------------------
const barOption = computed(() => {
    // Susun opsi F10 sesuai urutan resmi kuesioner Dikti (1 - 5)
    const rows = [...series.value].map((item, idx) => {
        const label = seriesLabel(item);
        const count = seriesMetric(item);
        const selected = !props.selectedLabel || props.selectedLabel === label;
        const isWaitingOrWorking = label.toLowerCase().includes('menunggu') || label.toLowerCase().includes('mulai bekerja') || label.toLowerCase().includes('ya');
        const optNum = item.kode_opsi || (idx + 1);
        return {
            name: `${optNum} - ${label}`,
            rawLabel: label,
            value: count,
            percentage: item.percentage || 0,
            itemStyle: {
                color: isWaitingOrWorking ? '#0D542B' : '#64748B',
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
                formatter: ({ data }) => data.value > 0 ? `${formatNumber(data.value, 0)} (${formatPercentage(data.percentage)})` : '0',
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
    rows: series.value.map((item, idx) => ({
        no: item.kode_opsi || (idx + 1),
        status: seriesLabel(item),
        count: formatNumber(seriesMetric(item), 0),
        percentage: formatPercentage(item.percentage || 0),
    })),
}));

// Konfigurasi views untuk kontainer terpadu
const views = computed(() => {
    return [
        {
            key: 'activity_bar',
            chartType: 'horizontal_bar',
            tabLabel: 'Keaktifan 4 Minggu Terakhir',
            title: 'Keaktifan mencari pekerjaan dalam 4 minggu terakhir',
            description: 'Instrumen F10: Apakah anda aktif mencari pekerjaan dalam 4 minggu terakhir? Pilihlah Satu Jawaban. KEMUDIAN LANJUT KE F17.',
            option: barOption.value,
            tableData: barTable.value,
            height: Math.max(380, series.value.length * 56 + 80),
            analysis: {
                title: 'Kilas Keaktifan dan Transisi Karir Alumni',
                statusBadge: 'Instrumen F10 Dikti',
                badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
                lead: 'Instrumen F10 merekam status keaktifan alumni dalam mencari kerja pada 4 minggu terakhir sebelum melangkah ke instrumen evaluasi kompetensi F17.',
                detail: 'Sebagian besar alumni memilih opsi "Tidak" karena telah berstatus bekerja atau menempuh studi lanjut. Bagi lulusan yang berada pada masa transisi, kategori didominasi oleh mereka yang sedang menunggu pengumuman hasil lamaran kerja serta yang telah memiliki kepastian waktu mulai bekerja dalam 2 minggu ke depan.',
            },
        },
    ];
});
</script>

<template>
    <AnalyticsTabbedContainer
        :views="views"
        :rows="records"
        :selected-label="selectedLabel"
        :loading="loading"
        indicator-badge="Indikator 08"
        @select="emit('select', $event)"
        @reset="emit('reset')"
    />
</template>
