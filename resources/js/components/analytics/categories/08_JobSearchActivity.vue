<script setup>
/**
 * Komponen Indikator 08: Keaktifan Mencari Pekerjaan (Instrumen F10).
 *
 * Mengelola visualisasi keaktifan pencarian pekerjaan alumni dalam 4 minggu terakhir:
 * - Horizontal Bar Chart: Menampilkan seluruh kategori status keaktifan secara jelas tanpa terpotong.
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
    // Urutkan dari frekuensi terendah ke tertinggi untuk tampilan bar horizontal ke atas
    const rows = [...series.value].map((item) => {
        const label = seriesLabel(item);
        const count = seriesMetric(item);
        const selected = !props.selectedLabel || props.selectedLabel === label;
        const isWaitingOrWorking = label.toLowerCase().includes('menunggu') || label.toLowerCase().includes('mulai bekerja') || label.toLowerCase().includes('ya');
        return {
            name: label,
            value: count,
            percentage: item.percentage || 0,
            itemStyle: {
                color: isWaitingOrWorking ? '#0D542B' : '#64748B',
                opacity: selected ? 1 : 0.22,
                borderRadius: [0, 4, 4, 0],
            },
        };
    }).reverse();

    const chartHeight = Math.max(460, rows.length * 52 + 90);

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
                lineHeight: 15,
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
            barMaxWidth: 24,
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
        { key: 'status', label: 'Status Keaktifan (Instrumen F10)' },
        { key: 'count', label: 'Jumlah Alumni (n)', align: 'right' },
        { key: 'percentage', label: 'Persentase', align: 'right' },
    ],
    rows: series.value.map((item) => ({
        status: seriesLabel(item),
        count: formatNumber(seriesMetric(item), 0),
        percentage: formatPercentage(item.percentage || 0),
    })),
}));

// Konfigurasi views untuk kontainer terpadu
const views = computed(() => {
    const topActivity = series.value[0];

    return [
        {
            key: 'activity_bar',
            tabLabel: 'Keaktifan 4 Minggu Terakhir',
            title: 'Keaktifan mencari pekerjaan dalam 4 minggu terakhir',
            description: 'Distribusi status keaktifan alumni dalam mencari pekerjaan pada periode 4 minggu terakhir (Instrumen F10).',
            option: barOption.value,
            tableData: barTable.value,
            height: Math.max(460, series.value.length * 52 + 90),
            analysis: {
                title: 'Kilas Keaktifan dan Transisi Karir Alumni',
                statusBadge: 'Status Keaktifan F10',
                badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
                lead: topActivity?.label
                    ? `Data menunjukkan kategori terbanyak adalah "${topActivity.label}" dengan jumlah ${topActivity.count} alumni (${formatPercentage(topActivity.percentage)}).`
                    : 'Data keaktifan mencari pekerjaan tersaji lengkap.',
                detail: 'Analisis pada instrumen F10 memperlihatkan dinamika alumni yang sedang berada di fase transisi. Responden yang menyatakan tidak aktif mencari kerja umumnya terbagi antara mereka yang sedang menanti panggilan kerja (waiting pipeline) atau telah menandatangani kesepakatan kerja dan dijadwalkan mulai bekerja dalam waktu dekat. Sementara bagi kelompok yang masih aktif melamar, pendampingan dari Pusat Karir UKDW melalui info loker mitra dan program persiapan interview tetap menjadi prioritas strategis.',
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
