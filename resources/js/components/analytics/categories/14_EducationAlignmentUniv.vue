<script setup>
/**
 * Komponen Indikator 14: Keselarasan Tingkat Pendidikan Tingkat Perguruan Tinggi (F15).
 *
 * Mengadopsi standar laporan resmi Tracer Study UKDW:
 * 1. Donut Chart: Proporsi Tingkat Pendidikan Sesuai Pekerjaan Universitas (Gambar 4.15)
 * 2. Matriks Heatmap: Sebaran Keselarasan Tingkat Pendidikan per Prodi (mengadopsi matriks perbandingan prodi Indikator 02)
 * 3. Stacked Bar: Komposisi Keselarasan Pendidikan Antarprodi (Gambar 4.14, batas ambang >= 10%)
 * Dilengkapi narasi analisis mendalam berstandar laporan resmi Tracer Study UKDW.
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

// Palet warna yang 100% selaras dengan Indikator 13 dan standar institusi UKDW
const getColor = (label) => {
    const l = String(label || '').toLowerCase().trim();
    if (l.includes('sama') || (l.includes('setingkat') && !l.includes('lebih'))) {
        return '#0D542B'; // Tingkat yang sama (Hijau UKDW)
    }
    if (l.includes('lebih tinggi')) {
        return '#2F7D4A'; // Setingkat lebih tinggi
    }
    if (l.includes('lebih rendah')) {
        return '#78B18B'; // Setingkat lebih rendah
    }
    if (l.includes('tidak perlu')) {
        return '#64748B'; // Tidak perlu pendidikan tinggi (Slate netral)
    }
    return '#475569';
};

// -----------------------------------------------------------------------------
// 1. PIE / DONUT: Keselarasan Pendidikan Tingkat Perguruan Tinggi (Gambar 4.15)
// -----------------------------------------------------------------------------
const pieOption = computed(() => {
    const data = series.value.map((item) => {
        const label = seriesLabel(item);
        const count = seriesMetric(item);
        const selected = !props.selectedLabel || props.selectedLabel === label;
        return {
            name: label,
            value: count,
            percentage: item.percentage || 0,
            itemStyle: {
                color: getColor(label),
                opacity: selected ? 1 : 0.22,
            },
        };
    });

    return {
        ...baseOption(validResponses.value),
        legend: {
            orient: 'horizontal',
            bottom: 0,
            icon: 'circle',
            textStyle: { color: '#334155', fontSize: 11, fontWeight: 600 },
        },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'item',
            formatter: ({ data }) => `<strong>${escapeHtml(data.name)}</strong><br>Jumlah: <strong>${formatNumber(data.value, 0)} alumni</strong><br>Persentase: <strong>${formatPercentage(data.percentage)}</strong>`,
        },
        series: [{
            name: 'Keselarasan Perguruan Tinggi',
            type: 'pie',
            radius: ['42%', '68%'],
            center: ['50%', '46%'],
            avoidLabelOverlap: true,
            itemStyle: {
                borderRadius: 4,
                borderColor: '#FFFFFF',
                borderWidth: 2,
            },
            label: {
                show: true,
                formatter: ({ data }) => `${formatPercentage(data.percentage)} ${data.name}`,
                fontSize: 11,
                fontWeight: 'bold',
                color: '#1E293B',
            },
            data,
        }],
    };
});

const pieTable = computed(() => ({
    columns: [
        { key: 'category', label: 'Tingkat Pendidikan Sesuai Pekerjaan' },
        { key: 'count', label: 'Jumlah Responden (n)', align: 'right' },
        { key: 'percentage', label: 'Persentase', align: 'right' },
    ],
    rows: series.value.map((item) => ({
        category: seriesLabel(item),
        count: formatNumber(seriesMetric(item), 0),
        percentage: formatPercentage(item.percentage || 0),
    })),
}));

// -----------------------------------------------------------------------------
// 2. MATRIKS HEATMAP: Sebaran Keselarasan per Program Studi (Mengadopsi Indikator 02)
// -----------------------------------------------------------------------------
const prodis = computed(() => [...new Set(records.value.map((r) => r.nama_prodi || 'Tanpa prodi'))]);
const categories = computed(() => {
    const activeCats = series.value.filter((s) => seriesMetric(s) > 0).map((s) => s.label);
    return activeCats.length > 0 ? activeCats : [...new Set(series.value.map((s) => s.label))];
});

const heatmapData = computed(() => {
    const listProdis = prodis.value;
    const cats = categories.value;
    const prodiTotals = Object.fromEntries(
        listProdis.map((p) => [p, records.value.filter((r) => (r.nama_prodi || 'Tanpa prodi') === p).length]),
    );

    const values = [];
    listProdis.forEach((prodi, yIndex) => {
        cats.forEach((cat, xIndex) => {
            const count = records.value.filter((r) => (r.nama_prodi || 'Tanpa prodi') === prodi && recordCategory(r) === cat).length;
            const total = Math.max(prodiTotals[prodi], 1);
            const pct = Number(((count / total) * 100).toFixed(1));
            values.push({
                value: [xIndex, yIndex, pct],
                prodi,
                category: cat,
                count,
                total,
            });
        });
    });

    const maxPct = Math.max(...values.map((v) => v.value[2]), 10);

    return { values, maxPct, prodiTotals };
});

const heatmapOption = computed(() => {
    const { values, maxPct, prodiTotals } = heatmapData.value;
    const cats = categories.value;
    const listProdis = prodis.value;
    const yLabels = listProdis.map((p) => `${p} (n=${prodiTotals[p]})`);

    return {
        ...baseOption(records.value.length),
        grid: { left: 24, right: 120, top: 75, bottom: 24, containLabel: true },
        xAxis: {
            type: 'category',
            position: 'top',
            data: cats,
            axisLabel: {
                rotate: -20,
                color: '#1E293B',
                fontSize: 11,
                fontWeight: 600,
                interval: 0,
            },
            axisTick: { show: true, alignWithLabel: true, lineStyle: { color: '#CBD5E1' } },
            axisLine: { lineStyle: { color: '#CBD5E1' } },
            splitArea: { show: false },
        },
        yAxis: {
            type: 'category',
            inverse: true,
            data: yLabels,
            axisLabel: { color: '#1E293B', fontSize: 11, fontWeight: 500 },
            axisTick: { show: true, alignWithLabel: true, lineStyle: { color: '#CBD5E1' } },
            axisLine: { lineStyle: { color: '#CBD5E1' } },
            splitArea: { show: false },
        },
        visualMap: {
            type: 'continuous',
            min: 0,
            max: Math.min(100, Math.ceil(maxPct / 10) * 10),
            orient: 'vertical',
            right: 14,
            top: 'middle',
            itemWidth: 16,
            itemHeight: 220,
            calculable: true,
            inRange: {
                color: ['#F8FAFC', '#DCEBE0', '#B7D7C0', '#78B18B', '#2F7D4A', '#0D542B'],
            },
            text: ['Tinggi (%)', '0%'],
            textGap: 8,
            textStyle: { color: '#1E293B', fontSize: 11, fontWeight: 600 },
            formatter: (val) => `${Number(val).toFixed(0)}%`,
        },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'item',
            formatter: ({ data }) => {
                if (!data) return '';
                return `
                    <div class="font-sans text-xs">
                        <div class="font-bold text-slate-900 border-b border-slate-200 pb-1 mb-1.5">${escapeHtml(data.prodi)}</div>
                        <div class="text-slate-600 mb-0.5"><span class="text-slate-500">Tingkat Keselarasan:</span> <strong>${escapeHtml(data.category)}</strong></div>
                        <div class="text-slate-600 mb-0.5"><span class="text-slate-500">Jumlah Alumni:</span> <strong>${formatNumber(data.count, 0)}</strong> (dari n=${data.total})</div>
                        <div class="text-slate-600"><span class="text-slate-500">Proporsi di Prodi:</span> <strong class="text-emerald-700 font-mono text-sm">${formatNumber(data.value[2], 1)}%</strong></div>
                    </div>
                `;
            },
        },
        series: [{
            name: 'Proporsi Keselarasan Pendidikan',
            type: 'heatmap',
            data: values,
            itemStyle: {
                borderColor: '#FFFFFF',
                borderWidth: 1.5,
            },
            label: {
                show: true,
                formatter: ({ data }) => data.value[2] > 0 ? `${formatNumber(data.value[2], 0)}%` : '',
                fontSize: 10,
                fontWeight: 600,
                color: (param) => param.data?.value[2] >= 30 ? '#FFFFFF' : '#1E293B',
            },
            emphasis: {
                itemStyle: {
                    shadowBlur: 8,
                    shadowColor: 'rgba(13, 84, 43, 0.25)',
                    borderColor: '#0D542B',
                    borderWidth: 2,
                },
            },
        }],
    };
});

const heatmapTable = computed(() => {
    const cats = categories.value;
    const prodiTotals = heatmapData.value.prodiTotals;

    const columns = [
        { key: 'prodi', label: 'Program Studi' },
        { key: 'total', label: 'Responden (n)', align: 'right' },
        ...cats.map((cat, i) => ({
            key: `cat_${i}`,
            label: cat,
            align: 'right',
        })),
    ];

    const rows = prodis.value.map((prodi) => {
        const row = {
            prodi,
            total: formatNumber(prodiTotals[prodi] || 0, 0),
        };
        cats.forEach((cat, i) => {
            const count = records.value.filter((r) => (r.nama_prodi || 'Tanpa prodi') === prodi && recordCategory(r) === cat).length;
            const total = Math.max(prodiTotals[prodi], 1);
            const pct = ((count / total) * 100).toFixed(1);
            row[`cat_${i}`] = `${formatNumber(count, 0)} (${pct}%)`;
        });
        return row;
    });

    return { columns, rows };
});

// -----------------------------------------------------------------------------
// 3. STACKED BAR ANTARPRODI (Warna Konsisten dengan Indikator Lain & Label %)
// -----------------------------------------------------------------------------
const stackedOption = computed(() => {
    const listProdis = prodis.value;
    const cats = categories.value;
    const prodiTotals = Object.fromEntries(
        listProdis.map((p) => [p, records.value.filter((r) => (r.nama_prodi || 'Tanpa prodi') === p).length]),
    );

    const seriesList = cats.map((cat) => {
        const catData = listProdis.map((prodi) => {
            const count = records.value.filter((r) => (r.nama_prodi || 'Tanpa prodi') === prodi && recordCategory(r) === cat).length;
            const total = Math.max(prodiTotals[prodi], 1);
            const pct = Number(((count / total) * 100).toFixed(1));
            return {
                value: pct,
                count,
                total,
                prodi,
                category: cat,
            };
        });

        const selected = !props.selectedLabel || props.selectedLabel === cat;

        return {
            name: cat,
            type: 'bar',
            stack: 'total',
            barMaxWidth: 28,
            label: {
                show: true,
                position: 'inside',
                formatter: (params) => params.value >= 10 ? `${params.value}%` : '',
                fontSize: 10,
                color: '#FFFFFF',
                fontWeight: 'bold',
            },
            itemStyle: {
                color: getColor(cat),
                opacity: selected ? 1 : 0.22,
                borderColor: '#FFFFFF',
                borderWidth: 1.5,
            },
            data: catData,
        };
    });

    const labels = listProdis.map((p) => `${p} (n=${prodiTotals[p]})`);

    return {
        ...baseOption(records.value.length),
        legend: {
            data: cats,
            bottom: 0,
            icon: 'circle',
            textStyle: { color: '#334155', fontSize: 11, fontWeight: 600 },
        },
        grid: { left: 24, right: 36, top: 20, bottom: 56, containLabel: true },
        xAxis: {
            type: 'value',
            max: 100,
            axisLabel: { formatter: '{value}%' },
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
        },
        yAxis: {
            type: 'category',
            inverse: true,
            data: labels,
            axisLabel: { color: '#334155', fontSize: 11, width: 200, overflow: 'break' },
        },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'item',
            formatter: ({ data }) => {
                if (!data) return '';
                return `<strong>${escapeHtml(data.prodi)}</strong><br>Keselarasan: <strong>${escapeHtml(data.category)}</strong><br>Jumlah: <strong>${data.count} alumni</strong> (dari total n=${data.total})<br>Proporsi: <strong>${data.value}%</strong>`;
            },
        },
        series: seriesList,
    };
});

const stackedTable = computed(() => ({
    columns: [
        { key: 'prodi', label: 'Program Studi' },
        { key: 'total', label: 'Total Alumni (n)', align: 'right' },
        { key: 'sama', label: 'Tingkat yang Sama', align: 'right' },
        { key: 'tinggi', label: 'Setingkat Lebih Tinggi', align: 'right' },
        { key: 'rendah', label: 'Setingkat Lebih Rendah', align: 'right' },
        { key: 'tidakPerlu', label: 'Tidak Perlu PT', align: 'right' },
    ],
    rows: prodis.value.map((prodi) => {
        const rows = records.value.filter((r) => (r.nama_prodi || 'Tanpa prodi') === prodi);
        const total = rows.length;
        const countSama = rows.filter((r) => String(recordCategory(r) || '').toLowerCase().includes('sama')).length;
        const countTinggi = rows.filter((r) => String(recordCategory(r) || '').toLowerCase().includes('lebih tinggi')).length;
        const countRendah = rows.filter((r) => String(recordCategory(r) || '').toLowerCase().includes('lebih rendah')).length;
        const countTidak = rows.filter((r) => String(recordCategory(r) || '').toLowerCase().includes('tidak perlu')).length;

        return {
            prodi,
            total: formatNumber(total, 0),
            sama: `${countSama} (${formatPercentage(total ? (countSama / total) * 100 : 0)})`,
            tinggi: `${countTinggi} (${formatPercentage(total ? (countTinggi / total) * 100 : 0)})`,
            rendah: `${countRendah} (${formatPercentage(total ? (countRendah / total) * 100 : 0)})`,
            tidakPerlu: `${countTidak} (${formatPercentage(total ? (countTidak / total) * 100 : 0)})`,
        };
    }),
}));

// Konfigurasi views untuk kontainer terpadu
const views = computed(() => [
    {
        key: 'pie',
        chartType: 'donut',
        tabLabel: 'Proporsi Universitas',
        title: 'Keselarasan tingkat pendidikan tingkat universitas',
        description: 'Proporsi keseluruhan kualifikasi tingkat pendidikan yang dianggap sesuai dengan pekerjaan alumni pada tingkat universitas (Gambar 4.15).',
        option: pieOption.value,
        tableData: pieTable.value,
        height: 380,
        analysis: {
            title: 'Kilas Kualifikasi Pendidikan Universitas (Gambar 4.15)',
            statusBadge: 'Tingkat Universitas',
            badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
            lead: series.value[0]?.label
                ? `Kategori kualifikasi dominan pada tingkat universitas adalah "${series.value[0].label}" dengan proporsi ${formatPercentage(series.value[0].percentage)} (${series.value[0].count} alumni).`
                : 'Data keselarasan pendidikan universitas tersaji lengkap.',
            detail: 'Tingginya proporsi alumni yang bekerja pada jenjang yang sama atau lebih tinggi mencerminkan kesesuaian derajat gelar sarjana UKDW terhadap ekspektasi kualifikasi minimum industri.',
        },
    },
    {
        key: 'by_prodi_heatmap',
        chartType: 'heatmap',
        tabLabel: 'Sebaran per Prodi',
        title: 'Sebaran keselarasan tingkat pendidikan per program studi',
        description: 'Matriks intensitas dan proporsi keselarasan kualifikasi tingkat pendidikan terhadap pekerjaan di setiap program studi UKDW (mengadopsi matriks perbandingan prodi Indikator 02).',
        option: heatmapOption.value,
        tableData: heatmapTable.value,
        height: Math.max(460, prodis.value.length * 36 + 120),
        analysis: {
            title: 'Kilas Sebaran Keselarasan Vertikal Antarjurusan',
            statusBadge: `${prodis.value.length} Program Studi`,
            badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
            lead: `Matriks sebaran memperlihatkan konsistensi keselarasan vertikal ijazah alumni di ${prodis.value.length} program studi.`,
            detail: 'Kategori tingkat yang sama menunjukkan dominasi kuat di seluruh program studi keilmuan spesifik dan profesi (Teologi, Kedokteran, Informatika, Sistem Informasi, dan Akuntansi), menandakan kualifikasi gelar S1/profesi UKDW sesuai dengan standar rekrutmen jabatan yang diemban.',
        },
    },
    {
        key: 'stacked',
        chartType: 'stacked_horizontal_100',
        tabLabel: 'Komposisi Bertumpuk',
        title: 'Komposisi keselarasan tingkat pendidikan per program studi',
        description: 'Perbandingan kualifikasi tingkat pendidikan yang dianggap sesuai untuk pekerjaan antarprogram studi (mengadopsi Gambar 4.14 Laporan Tracer Study UKDW).',
        option: stackedOption.value,
        tableData: stackedTable.value,
        height: Math.max(380, prodis.value.length * 36 + 120),
        analysis: {
            title: 'Kilas Keselarasan Vertikal Antarjurusan (Gambar 4.14)',
            statusBadge: 'Gambar 4.14 Laporan Resmi',
            badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
            lead: 'Data pada Gambar 4.14 menunjukkan keselarasan tingkat pendidikan dan pekerjaan yang sama. Prodi Teologi, Akuntansi, Informatika, Sistem Informasi, dan profesi Kedokteran menunjukkan keselarasan pekerjaan yang saat ini ditekuni dengan tingkat pendidikan yang sama menunjukkan lebih dari 80%.',
            detail: 'Sementara itu prodi lainnya yaitu Manajemen, Arsitektur, Desain Produk, Biologi, dan Pendidikan Bahasa Inggris memiliki tingkat keselarasan di kisaran 66% - 78% mengatakan bahwa pekerjaan yang mereka tekuni saat ini membutuhkan tingkat pendidikan yang sama.',
        },
    },
]);
</script>

<template>
    <AnalyticsTabbedContainer
        :views="views"
        :rows="records"
        :selected-label="selectedLabel"
        :loading="loading"
        indicator-badge="Indikator 14"
        @select="emit('select', $event)"
        @reset="emit('reset')"
    />
</template>
