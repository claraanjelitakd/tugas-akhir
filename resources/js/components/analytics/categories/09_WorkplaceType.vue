<script setup>
/**
 * Komponen Indikator 09: Jenis Tempat Bekerja (Instrumen F11).
 *
 * Mengelola visualisasi sektor/badan usaha tempat alumni bekerja:
 * 1. Donut / Bar Chart: Proporsi Jenis Instansi Universitas dengan palet konsisten UKDW
 * 2. Stacked Bar: Komposisi Sektor per Program Studi dilengkapi label persentase di dalam batang
 * 3. Tabel Tabulasi Lengkap: Rincian responden dan badan usaha
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

// Palet warna tetap per jenis instansi agar 100% konsisten antar diagram
const workplaceColorMap = {
    'Perusahaan swasta': '#0D542B',                         // Hijau primer UKDW
    'BUMN/BUMD': '#0F766E',                                  // Teal tegas
    'Instansi pemerintah': '#334155',                        // Slate gelap
    'Organisasi non-profit/LSM': '#64748B',                  // Slate netral
    'Wiraswasta/perusahaan sendiri': '#D97706',              // Amber hangat
    'Institusi/Organisasi Multilateral': '#475569',          // Slate medium
    'Lainnya': '#94A3B8',                                    // Slate lembut
};

const getWorkplaceColor = (label) => workplaceColorMap[label] || '#475569';

// -----------------------------------------------------------------------------
// 1. DONUT CHART: Proporsi Jenis Tempat Bekerja Universitas
// -----------------------------------------------------------------------------
const donutOption = computed(() => {
    // Filter hanya kategori yang memiliki respon (>0) agar tampilan bersih
    const activeSeries = series.value.filter((item) => seriesMetric(item) > 0);

    const data = activeSeries.map((item) => {
        const label = seriesLabel(item);
        const count = seriesMetric(item);
        const selected = !props.selectedLabel || props.selectedLabel === label;
        return {
            name: label,
            value: count,
            percentage: item.percentage || 0,
            itemStyle: {
                color: getWorkplaceColor(label),
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
            formatter: ({ data }) => `<strong>${escapeHtml(data.name)}</strong><br>Jumlah: <strong>${formatNumber(data.value, 0)} alumni</strong><br>Proporsi: <strong>${formatPercentage(data.percentage)}</strong>`,
        },
        series: [{
            name: 'Jenis Tempat Bekerja',
            type: 'pie',
            radius: ['42%', '70%'],
            center: ['50%', '46%'],
            avoidLabelOverlap: true,
            itemStyle: {
                borderRadius: 4,
                borderColor: '#FFFFFF',
                borderWidth: 2,
            },
            label: {
                show: true,
                formatter: ({ data }) => `${formatPercentage(data.percentage)}`,
                fontSize: 11,
                fontWeight: 'bold',
                color: '#1E293B',
            },
            data,
        }],
    };
});

const donutTable = computed(() => ({
    columns: [
        { key: 'name', label: 'Jenis Perusahaan / Instansi (F11)' },
        { key: 'count', label: 'Jumlah Alumni (n)', align: 'right' },
        { key: 'percentage', label: 'Persentase', align: 'right' },
    ],
    rows: series.value.map((item) => ({
        name: seriesLabel(item),
        count: formatNumber(seriesMetric(item), 0),
        percentage: formatPercentage(item.percentage || 0),
    })),
}));

// -----------------------------------------------------------------------------
// 2. STACKED BAR: Komposisi Sektor per Program Studi (Lengkap dengan Label %)
// -----------------------------------------------------------------------------
const prodis = computed(() => [...new Set(records.value.map((r) => r.nama_prodi || 'Tanpa prodi'))]);
const categories = computed(() => {
    // Ambil hanya kategori yang aktif di dataset
    const activeCats = series.value.filter((s) => seriesMetric(s) > 0).map((s) => s.label);
    return activeCats.length > 0 ? activeCats : [...new Set(series.value.map((s) => s.label))];
});

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
            barMaxWidth: 26,
            label: {
                show: true,
                position: 'inside',
                formatter: (params) => params.value >= 7 ? `${params.value}%` : '',
                fontSize: 10,
                color: '#FFFFFF',
                fontWeight: 'bold',
            },
            itemStyle: {
                color: getWorkplaceColor(cat),
                opacity: selected ? 1 : 0.22,
                borderColor: '#FFFFFF',
                borderWidth: 1,
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
        grid: { left: 24, right: 36, top: 20, bottom: 64, containLabel: true },
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
                return `<strong>${escapeHtml(data.prodi)}</strong><br>Instansi: <strong>${escapeHtml(data.category)}</strong><br>Jumlah: <strong>${data.count} alumni</strong> (dari total n=${data.total})<br>Proporsi: <strong>${data.value}%</strong>`;
            },
        },
        series: seriesList,
    };
});

const stackedTable = computed(() => ({
    columns: [
        { key: 'prodi', label: 'Program Studi' },
        { key: 'total', label: 'Total Alumni (n)', align: 'right' },
        { key: 'summary', label: 'Rincian Sektor Terbanyak', align: 'left' },
    ],
    rows: prodis.value.map((prodi) => {
        const rows = records.value.filter((r) => (r.nama_prodi || 'Tanpa prodi') === prodi);
        const summary = categories.value
            .map((cat) => ({ cat, count: rows.filter((r) => recordCategory(r) === cat).length }))
            .filter((i) => i.count > 0)
            .map((i) => `${i.cat}: ${i.count}`)
            .join(' | ');

        return {
            prodi,
            total: formatNumber(rows.length, 0),
            summary: summary || '-',
        };
    }),
}));

// Konfigurasi views untuk kontainer terpadu
const views = computed(() => {
    const topSector = series.value.find((s) => seriesMetric(s) > 0) || series.value[0];

    return [
        {
            key: 'proportion',
            tabLabel: 'Proporsi Universitas',
            title: 'Proporsi jenis tempat bekerja tingkat universitas',
            description: 'Distribusi alumni bekerja berdasarkan klasifikasi badan usaha atau instansi tempat kerja (Instrumen F11).',
            option: donutOption.value,
            tableData: donutTable.value,
            height: 380,
            analysis: {
                title: 'Kilas Penyerapan Sektor Kerja Alumni',
                statusBadge: 'Klasifikasi Sektor F11',
                badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
                lead: topSector?.label
                    ? `Sektor dengan penyerapan alumni terbesar adalah "${topSector.label}" dengan jumlah ${topSector.count} alumni (${formatPercentage(topSector.percentage)}).`
                    : 'Data jenis tempat bekerja tersaji lengkap.',
                detail: 'Karakteristik serapan kerja alumni UKDW terdistribusi aktif di berbagai sektor strategis: perusahaan swasta korporasi nasional/multinasional, instansi pemerintah (ASN/kementerian), badan usaha milik negara (BUMN/BUMD), serta organisasi non-profit/LSM dan kewirausahaan mandiri.',
            },
        },
        {
            key: 'by_prodi',
            tabLabel: 'Komposisi per Prodi',
            title: 'Komposisi jenis tempat bekerja per program studi',
            description: 'Perbandingan proporsi sektor tempat bekerja alumni pada masing-masing program studi di lingkungan UKDW.',
            option: stackedOption.value,
            tableData: stackedTable.value,
            height: Math.max(400, prodis.value.length * 36 + 120),
            analysis: {
                title: 'Kilas Pola Sektor Antarjurusan',
                statusBadge: 'Sebaran Program Studi',
                badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
                lead: `Komposisi sektor kerja bervariasi secara khas sesuai profil kompetensi lulusan di ${prodis.value.length} program studi.`,
                detail: 'Lulusan program studi rumpun teknologi informasi (Informatika dan Sistem Informasi) serta ekonomi bisnis (Manajemen dan Akuntansi) menunjukkan konsentrasi serapan tinggi pada sektor perusahaan swasta dan BUMN. Sementara itu, lulusan Kedokteran dan Pendidikan Bahasa Inggris terserap kuat pada instansi pemerintah dan yayasan/organisasi nirlaba, sedangkan prodi Desain Produk dan Arsitektur menunjukkan kontribusi wirausaha mandiri/studio yang menonjol.',
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
        indicator-badge="Indikator 09"
        @select="emit('select', $event)"
        @reset="emit('reset')"
    />
</template>
