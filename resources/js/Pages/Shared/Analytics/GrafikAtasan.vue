<script setup>
/**
 * Halaman Business Intelligence Grafik Evaluasi Atasan (Pengguna Lulusan).
 *
 * Mengadopsi arsitektur terpadu Grafik Universitas & Grafik Prodi:
 * 1. Fokus Indikator Tunggal (One Focused Indicator at a Time) melalui selektor terpadu:
 *    - Indikator 01: Evaluasi Aspek Kinerja Lulusan (Horizontal Stacked Bar 100% Skala Likert 1-5)
 *    - Indikator 02: Tingkat Kesiapan Kerja Alumni (Horizontal Stacked Bar Distribusi Kesiapan)
 *    - Indikator 03: Komparasi Mutu Antar Program Studi (Ranking Rata-rata Skor per Prodi)
 * 2. Interaktivitas Klik & Cross-Filtering Dinamis ("Don't Make Me Think"):
 *    - Saat batang atau segmen pilihan jawaban diklik, tabel responden di bawahnya langsung
 *      menyaring alumni, atasan langsung, dan perusahaan pembentuk data tersebut secara instan.
 * 3. Bebas Emotikon/Emoji dan Mengikuti Palet Institusional UKDW (#0D542B).
 */
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import Sidebar from '../Components/Sidebar.vue';
import AnalyticsEChart from '@/components/analytics/AnalyticsEChart.vue';
import PaginationControls from '@/components/PaginationControls.vue';
import {
    baseOption,
    escapeHtml,
    formatNumber,
} from '@/components/analytics/analyticsChartOptions';

// Props awal berisi payload evaluasi atasan dari controller.
const props = defineProps({
    user: { type: Object, default: () => ({}) },
    analytics: { type: Object, default: () => ({ filters: {}, summary: {}, question_stats: [], records: [] }) },
});

// Menyimpan payload aktif agar filter dapat memperbarui grafik tanpa reload penuh.
const currentAnalytics = ref(props.analytics);
// Menandai proses pemuatan data JSON.
const isLoading = ref(false);
// Menyimpan pesan error yang dapat dibaca pengguna.
const loadError = ref('');
// Menahan request agar perubahan filter berurutan tidak membuat banyak query.
let filterTimer = null;

// Filter tahun lulusan dan program studi yang aktif.
const selectedYear = ref(String(props.analytics?.filters?.selected_year || 'ALL'));
const selectedProdiId = ref(String(props.analytics?.filters?.selected_prodi_id || ''));

// Kunci penyimpanan tab analitik agar pilihan Grafik/Data konsisten lintas indikator.
const ANALYTICS_PANEL_STORAGE_KEY = 'tracer.analytics.atasan.active-panel';

// Membaca tab terakhir dengan fallback aman untuk SSR atau browser tanpa localStorage.
const storedAnalyticsPanel = () => {
    if (typeof window === 'undefined') return 'charts';
    const storedPanel = window.localStorage.getItem(ANALYTICS_PANEL_STORAGE_KEY);
    return ['charts', 'data'].includes(storedPanel) ? storedPanel : 'charts';
};

// Panel navigasi utama: 'charts' (Grafik & Analisis) atau 'data' (Data Responden).
const activePanel = ref(storedAnalyticsPanel());

// Menyimpan tab aktif ke localStorage.
watch(activePanel, (panel) => {
    if (typeof window !== 'undefined') {
        window.localStorage.setItem(ANALYTICS_PANEL_STORAGE_KEY, panel);
    }
});

// Opsi daftar indikator utama evaluasi atasan.
const indicatorOptions = [
    {
        key: 'kinerja',
        code: 'Indikator 01',
        title: 'Evaluasi Aspek Kinerja Lulusan',
        badge: '7 Butir Kompetensi',
        description: 'Distribusi penilaian atasan langsung terhadap 7 butir aspek kompetensi alumni pada skala Likert 1-5.',
    },
    {
        key: 'kesiapan',
        code: 'Indikator 02',
        title: 'Tingkat Kesiapan Kerja Alumni',
        badge: 'Adaptasi Kerja',
        description: 'Distribusi kesiapan alumni saat pertama kali mulai bekerja dan beradaptasi di lingkungan profesional.',
    },
    {
        key: 'prodi',
        code: 'Indikator 03',
        title: 'Perbandingan Antar Program Studi',
        badge: 'Benchmark Prodi',
        description: 'Matriks heatmap pemeringkatan skor rata-rata evaluasi kinerja alumni per aspek kompetensi berdasarkan program studi asal.',
    },
];

// Indikator aktif yang dipilih pengguna.
const selectedIndicatorKey = ref('kinerja');
const currentIndicator = computed(() => indicatorOptions.find((i) => i.key === selectedIndicatorKey.value) || indicatorOptions[0]);

// Mode visualisasi dalam indikator aktif: 'chart' (Visualisasi) atau 'table' (Data Ringkasan Tabulasi).
const activeViewMode = ref('chart');

// State cross-filtering: elemen grafik yang sedang diklik user.
const selectedItem = ref(null);

// State pencarian dan paginasi untuk tabel data responden.
const tableSearch = ref('');
const currentPage = ref(1);
const rowsPerPage = ref(15);

// Mengambil bagian payload yang dipakai bersama oleh kartu, grafik, dan tabel.
const filters = computed(() => currentAnalytics.value?.filters || {});
const summary = computed(() => currentAnalytics.value?.summary || {});
const questionStats = computed(() => currentAnalytics.value?.question_stats || []);
const readiness = computed(() => currentAnalytics.value?.readiness || {});
const prodiSummary = computed(() => currentAnalytics.value?.prodi_summary || []);
const records = computed(() => currentAnalytics.value?.records || []);

// Daftar butir pertanyaan berjenis Likert 5 poin yang memiliki jawaban valid.
const likertItems = computed(() => questionStats.value.filter((item) => item.type === 'likert_5' && item.valid_count > 0));

// Reset seleksi elemen grafik saat indikator utama berubah.
watch(selectedIndicatorKey, () => {
    selectedItem.value = null;
    currentPage.value = 1;
});

// Ringkasan aspek kinerja dengan nilai tertinggi dan area peningkatan.
const performanceHighlights = computed(() => {
    const items = [...likertItems.value];
    if (items.length === 0) {
        return { highest: null, lowest: null, overallAverage: null };
    }

    items.sort((a, b) => (b.average ?? 0) - (a.average ?? 0));
    const highest = items[0];
    const lowest = items[items.length - 1];
    const totalScore = items.reduce((acc, curr) => acc + (curr.average ?? 0), 0);
    const overallAverage = Number((totalScore / items.length).toFixed(1));

    return { highest, lowest, overallAverage };
});

// Ringkasan tingkat kesiapan kerja alumni.
const readinessHighlights = computed(() => {
    const distribution = readiness.value?.distribution || [];
    const total = distribution.reduce((sum, item) => sum + (item.count || 0), 0);
    if (total === 0) {
        return { readyRate: 0, topLabel: '-', total: 0 };
    }

    const readyCount = distribution
        .filter((item) => ['sangat siap', 'cukup siap'].includes(String(item.label || '').trim().toLowerCase()))
        .reduce((sum, item) => sum + (item.count || 0), 0);

    const sorted = [...distribution].sort((a, b) => (b.count || 0) - (a.count || 0));

    return {
        readyRate: Number(((readyCount / total) * 100).toFixed(1)),
        topLabel: sorted[0]?.label || '-',
        total,
    };
});

// -----------------------------------------------------------------------------
// STANDAR SKALA & WARNA LIKERT EVALUASI ATASAN (Mengadopsi Indikator 02)
// 1 = Sangat Kurang (Merah), 2 = Kurang (Oranye), 3 = Cukup (Kuning),
// 4 = Baik (Hijau Muda), 5 = Sangat Baik (Hijau Tua UKDW).
// Urutan array 1 -> 5 agar tumpukan batang horizontal dimulai dari kiri (Level 1).
// -----------------------------------------------------------------------------
const likertScaleLevels = [
    { level: 1, label: 'Sangat Kurang', color: '#B91C1C', textColor: '#FFFFFF' },
    { level: 2, label: 'Kurang', color: '#EA580C', textColor: '#FFFFFF' },
    { level: 3, label: 'Cukup', color: '#FACC15', textColor: '#1E293B' },
    { level: 4, label: 'Baik', color: '#2F7D4A', textColor: '#FFFFFF' },
    { level: 5, label: 'Sangat Baik', color: '#0D542B', textColor: '#FFFFFF' },
];

const getLikertLevelFromLabel = (label) => {
    const l = String(label || '').toLowerCase().trim();
    if (!l) return 3;

    // Level 1: Sangat Kurang / Sangat Rendah / Tidak Sama Sekali
    if (l.includes('sangat kurang') || l.includes('sangat rendah') || l.includes('tidak') || l.includes('sangat kecil')) {
        return 1;
    }
    // Level 5: Sangat Baik / Sangat Tinggi / Sangat Besar
    if (l.includes('sangat baik') || l.includes('sangat tinggi') || l.includes('sangat besar') || l.includes('sangat mampu')) {
        return 5;
    }
    // Level 2: Kurang / Rendah / Kecil
    if (l.includes('kurang') || l.includes('rendah') || l.includes('kecil') || l.includes('jarang')) {
        return 2;
    }
    // Level 3: Cukup / Sedang
    if (l.includes('cukup') || l.includes('sedang')) {
        return 3;
    }
    // Level 4: Baik / Tinggi / Besar (setelah melewati filter 'sangat' dan 'kurang')
    if (l.includes('baik') || l.includes('tinggi') || l.includes('besar') || l.includes('mampu')) {
        return 4;
    }

    return 3;
};

// -----------------------------------------------------------------------------
// 1. GRAFIK INDIKATOR 01: STACKED BAR ASPEK KINERJA LULUSAN
// Sumbu Y = Aspek Kinerja, Sumbu X = Jumlah Respon Atasan (n), Segmen = Skala Likert 1-5, Ujung Batang = Total Respon
// Legenda diposisikan di sisi kanan grafik dengan warna yang sinkron 100%.
// -----------------------------------------------------------------------------
const kinerjaStackedOption = computed(() => {
    const items = likertItems.value;
    const aspectNames = items.map((item) => `${item.label || item.aspek} (n=${item.valid_count})`);

    const seriesList = likertScaleLevels.map((scale) => {
        const data = items.map((item) => {
            const dist = item.distribution || [];
            const matchedDist = dist.find((d) => getLikertLevelFromLabel(d.label) === scale.level);
            const count = matchedDist ? matchedDist.count : 0;
            const total = item.valid_count > 0 ? item.valid_count : 1;
            const percentage = Number(((count / total) * 100).toFixed(1));

            const isSelected = !selectedItem.value
                || (selectedItem.value.type === 'aspek_opsi'
                    && selectedItem.value.kode === item.kode
                    && selectedItem.value.level === scale.level);

            return {
                value: count,
                count,
                total: item.valid_count,
                percentage,
                aspek: item.label || item.aspek,
                kode: item.kode,
                scaleLevel: scale.level,
                scaleLabel: scale.label,
                itemStyle: {
                    color: scale.color,
                    opacity: isSelected ? 1 : 0.25,
                    borderColor: '#FFFFFF',
                    borderWidth: 1.5,
                },
            };
        });

        return {
            name: `${scale.level} - ${scale.label}`,
            type: 'bar',
            stack: 'total',
            stackOrder: scale.level,
            barMaxWidth: 26,
            itemStyle: {
                color: scale.color,
            },
            label: {
                show: true,
                position: 'inside',
                formatter: (param) => {
                    const cnt = Number(param.value ?? 0);
                    if (cnt <= 0) return '';
                    const pct = param.data?.percentage ?? 0;
                    return pct >= 12 ? `${cnt} (${Math.round(pct)}%)` : `${cnt}`;
                },
                color: scale.textColor,
                fontSize: 10,
                fontWeight: 700,
            },
            data,
        };
    });

    // Series transparan di ujung kanan bar untuk menampilkan label total respon
    seriesList.push({
        name: 'Total Respon',
        type: 'bar',
        stack: 'total',
        stackOrder: 99,
        barMaxWidth: 26,
        data: items.map((item) => ({
            value: 0,
            total: item.valid_count,
            aspek: item.label || item.aspek,
            kode: item.kode,
        })),
        label: {
            show: true,
            position: 'right',
            distance: 8,
            formatter: (params) => {
                const total = params.data?.total ?? 0;
                return total > 0 ? `${total} respon` : '';
            },
            color: '#0F172A',
            fontSize: 11,
            fontWeight: 700,
        },
        itemStyle: {
            color: 'rgba(0, 0, 0, 0)',
        },
        tooltip: {
            show: false,
        },
    });

    return {
        ...baseOption(summary.value.submitted || 0),
        themePalette: likertScaleLevels.map((s) => s.color),
        legend: {
            show: true,
            orient: 'vertical',
            right: 16,
            top: 'middle',
            icon: 'rect',
            itemWidth: 14,
            itemHeight: 12,
            itemGap: 12,
            data: [
                '5 - Sangat Baik',
                '4 - Baik',
                '3 - Cukup',
                '2 - Kurang',
                '1 - Sangat Kurang',
            ],
            textStyle: { color: '#334155', fontSize: 11, fontWeight: 600 },
        },
        grid: { left: 24, right: 210, top: 20, bottom: 36, containLabel: true },
        xAxis: {
            type: 'value',
            min: 0,
            minInterval: 1,
            name: 'Jumlah respon atasan (n)',
            nameLocation: 'middle',
            nameGap: 24,
            axisLabel: {
                formatter: (val) => Math.round(val),
                color: '#64748B',
                fontSize: 11,
            },
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
        },
        yAxis: {
            type: 'category',
            inverse: true,
            data: aspectNames,
            axisTick: { show: false },
            axisLine: { lineStyle: { color: '#CBD5E1' } },
            axisLabel: {
                color: '#1E293B',
                fontSize: 11,
                fontWeight: 600,
                width: 230,
                overflow: 'break',
                lineHeight: 14,
            },
        },
        tooltip: {
            trigger: 'item',
            formatter: (params) => {
                const d = params.data || {};
                if (!d.aspek || d.scaleLevel === undefined) return '';
                return `
                    <div style="min-width: 200px; font-family: inherit;">
                        <div style="font-weight: 700; color: #0F172A; font-size: 13px; border-bottom: 1px solid #F1F5F9; padding-bottom: 4px; margin-bottom: 6px;">
                            ${escapeHtml(d.aspek)}
                        </div>
                        <div style="font-size: 12px; color: #475569; margin-bottom: 3px;">
                            Penilaian: <strong style="color: #0F172A;">${escapeHtml(d.scaleLabel)}</strong>
                        </div>
                        <div style="font-size: 12px; color: #475569; margin-bottom: 3px;">
                            Jumlah Respon: <strong style="color: #0D542B; font-size: 13px;">${formatNumber(d.count, 0)} atasan</strong>
                        </div>
                        <div style="font-size: 11px; color: #64748B;">
                            Proporsi: <strong style="color: #334155;">${formatNumber(d.percentage, 1)}%</strong> dari total ${formatNumber(d.total, 0)} atasan
                        </div>
                        <div style="margin-top: 6px; padding-top: 4px; border-top: 1px dashed #E2E8F0; font-size: 10px; color: #0D542B; font-weight: 600;">
                            Klik batang untuk menyaring responden alumni
                        </div>
                    </div>
                `;
            },
        },
        series: seriesList,
    };
});

// Tabel Ringkasan Aspek Kinerja untuk Tab Data Ringkasan.
const kinerjaSummaryTable = computed(() => {
    const items = likertItems.value;
    const columns = [
        { key: 'aspek', label: 'Aspek Kompetensi' },
        { key: 'valid_count', label: 'Total Respon (n)', align: 'right' },
        { key: 'average', label: 'Rata-rata Skor (1-5)', align: 'center' },
        { key: 'sangat_baik', label: 'Sangat Baik (5)', align: 'right' },
        { key: 'baik', label: 'Baik (4)', align: 'right' },
        { key: 'cukup', label: 'Cukup (3)', align: 'right' },
        { key: 'kurang', label: 'Kurang (2)', align: 'right' },
        { key: 'sangat_kurang', label: 'Sangat Kurang (1)', align: 'right' },
    ];

    const rows = items.map((item) => {
        const dist = item.distribution || [];
        const total = item.valid_count > 0 ? item.valid_count : 1;
        const getPct = (lvl) => {
            const m = dist.find((d) => getLikertLevelFromLabel(d.label) === lvl);
            const cnt = m ? m.count : 0;
            return `${cnt} (${((cnt / total) * 100).toFixed(1)}%)`;
        };

        return {
            aspek: item.label || item.aspek,
            valid_count: formatNumber(item.valid_count, 0),
            average: item.average ? `${item.average} / 5` : '-',
            sangat_baik: getPct(5),
            baik: getPct(4),
            cukup: getPct(3),
            kurang: getPct(2),
            sangat_kurang: getPct(1),
        };
    });

    return { columns, rows };
});

// -----------------------------------------------------------------------------
// 2. GRAFIK INDIKATOR 02: KESIAPAN KERJA ALUMNI (HORIZONTAL BAR DENGAN JUMLAH & %)
// Sumbu Y = Kategori Kesiapan, Sumbu X = Jumlah Jawaban Atasan, Label = n (persentase)
// -----------------------------------------------------------------------------
const readinessColors = {
    'sangat siap': '#0D542B',
    'cukup siap': '#2F7D4A',
    'kurang siap': '#EA580C',
    'tidak siap': '#B91C1C',
};

const readinessOption = computed(() => {
    const distribution = readiness.value?.distribution || [];
    const total = distribution.reduce((sum, item) => sum + (item.count || 0), 0);
    const labels = distribution.map((item) => item.label);
    const counts = distribution.map((item) => item.count);

    const seriesData = distribution.map((item) => {
        const count = Number(item.count || 0);
        const pct = total > 0 ? Number(((count / total) * 100).toFixed(1)) : 0;
        const norm = String(item.label || '').trim().toLowerCase();
        const barColor = readinessColors[norm] || '#2F7D4A';
        const isSelected = !selectedItem.value
            || (selectedItem.value.type === 'kesiapan' && selectedItem.value.label.toLowerCase() === norm);

        return {
            name: item.label,
            value: count,
            percentage: pct,
            total,
            itemStyle: {
                color: barColor,
                opacity: isSelected ? 1 : 0.25,
                borderRadius: [0, 4, 4, 0],
            },
        };
    });

    return {
        ...baseOption(total),
        grid: { left: 24, right: 90, top: 20, bottom: 36, containLabel: true },
        xAxis: {
            type: 'value',
            minInterval: 1,
            name: 'Jumlah alumni dinilai (n)',
            nameLocation: 'middle',
            nameGap: 24,
            axisLabel: { color: '#64748B', fontSize: 11 },
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
        },
        yAxis: {
            type: 'category',
            inverse: true,
            data: labels,
            axisTick: { show: false },
            axisLine: { lineStyle: { color: '#CBD5E1' } },
            axisLabel: { color: '#1E293B', fontSize: 12, fontWeight: 600 },
        },
        tooltip: {
            trigger: 'item',
            formatter: ({ data }) => {
                if (!data) return '';
                return `
                    <div style="font-family: inherit; font-size: 12px;">
                        <div style="font-weight: 700; color: #0F172A; border-bottom: 1px solid #E2E8F0; padding-bottom: 4px; margin-bottom: 4px;">
                            ${escapeHtml(data.name)}
                        </div>
                        <div style="color: #475569;">Jumlah Respon: <strong style="color: #0D542B;">${formatNumber(data.value, 0)} alumni</strong></div>
                        <div style="color: #475569;">Proporsi: <strong style="color: #0F172A;">${data.percentage}%</strong> dari ${formatNumber(data.total, 0)} atasan</div>
                        <div style="margin-top: 4px; font-size: 10px; color: #0D542B; font-weight: 600;">Klik batang untuk menyaring responden alumni</div>
                    </div>
                `;
            },
        },
        series: [{
            name: 'Kesiapan Kerja',
            type: 'bar',
            data: seriesData,
            barWidth: 26,
            label: {
                show: true,
                position: 'right',
                formatter: ({ data }) => `${formatNumber(data.value, 0)} (${data.percentage}%)`,
                color: '#1E293B',
                fontWeight: 700,
                fontSize: 11,
                distance: 6,
            },
        }],
    };
});

// Tabel Ringkasan Kesiapan Kerja untuk Tab Data Ringkasan.
const readinessSummaryTable = computed(() => {
    const distribution = readiness.value?.distribution || [];
    const total = distribution.reduce((sum, item) => sum + (item.count || 0), 0);

    return {
        columns: [
            { key: 'label', label: 'Tingkat Kesiapan Kerja' },
            { key: 'count', label: 'Jumlah Alumni Dinilai (n)', align: 'right' },
            { key: 'percentage', label: 'Proporsi (%)', align: 'right' },
        ],
        rows: distribution.map((item) => {
            const count = Number(item.count || 0);
            const pct = total > 0 ? ((count / total) * 100).toFixed(1) : 0;
            return {
                label: item.label,
                count: formatNumber(count, 0),
                percentage: `${pct}%`,
            };
        }),
    };
});

// -----------------------------------------------------------------------------
// 3. GRAFIK INDIKATOR 03: BENCHMARK PROGRAM STUDI (HEATMAP MATRIKS PER PRODI & ASPEK)
// Sumbu Y = Program Studi, Sumbu X = Aspek Kompetensi, Nilai = Skor (1 - 5)
// -----------------------------------------------------------------------------
const prodiSeries = computed(() => currentAnalytics.value?.prodi_series || currentAnalytics.value?.prodi_summary || []);
const prodis = computed(() => prodiSeries.value.map((item) => item.prodi));

const prodiHeatmapData = computed(() => {
    const listProdis = prodiSeries.value;
    const questions = likertItems.value.length > 0 ? likertItems.value : questionStats.value.filter((item) => item.type === 'likert_5');
    const values = [];

    listProdis.forEach((prodiItem, yIndex) => {
        questions.forEach((q, xIndex) => {
            const aspect = prodiItem.aspects?.find((item) => item.key === q.kode || item.label === q.label) || {};
            const scoreInt = Number(aspect.score_int || 0);
            values.push({
                value: [xIndex, yIndex, scoreInt],
                rawAvg: Number(aspect.average || 0),
                scoreInt,
                count: Number(aspect.count || 0),
                prodi: prodiItem.prodi,
                aspectKey: q.kode,
                aspectLabel: q.label || q.aspek,
            });
        });
    });

    return { values, questions };
});

const prodiOption = computed(() => {
    const { values, questions } = prodiHeatmapData.value;
    const aspectLabels = questions.map((q) => q.label || q.aspek);
    const prodiNames = prodis.value;

    return {
        ...baseOption(summary.value.submitted || 0),
        grid: { left: 20, right: 150, top: 80, bottom: 20, containLabel: true },
        xAxis: {
            type: 'category',
            position: 'top',
            data: aspectLabels,
            axisLabel: {
                rotate: -35,
                color: '#1E293B',
                fontSize: 11,
                fontWeight: 600,
                interval: 0,
                formatter: (val) => {
                    const clean = String(val || '');
                    if (clean.length > 28) {
                        return clean.slice(0, 25) + '...';
                    }
                    return clean;
                },
            },
            axisTick: { show: true, alignWithLabel: true, lineStyle: { color: '#CBD5E1' } },
            axisLine: { lineStyle: { color: '#CBD5E1' } },
            splitArea: { show: false },
        },
        yAxis: {
            type: 'category',
            inverse: true,
            data: prodiNames,
            axisLabel: { color: '#1E293B', width: 170, fontSize: 11, fontWeight: 500 },
            axisTick: { show: true, alignWithLabel: true, lineStyle: { color: '#CBD5E1' } },
            axisLine: { lineStyle: { color: '#CBD5E1' } },
            splitArea: { show: false },
        },
        visualMap: {
            type: 'continuous',
            min: 1,
            max: 5,
            precision: 0,
            orient: 'vertical',
            right: 18,
            top: 'middle',
            itemWidth: 18,
            itemHeight: 240,
            inRange: {
                color: [
                    '#B91C1C', // 1: Merah pekat (Highlight)
                    '#EF4444', // 2: Merah terang (Highlight)
                    '#E2F3E7', // 3: Hijau pucat netral
                    '#2F7D4A', // 4: Hijau sedang
                    '#0D542B', // 5: Hijau tua institusi UKDW
                ],
            },
            text: ['5 (Sangat Baik)', '1 (Sangat Kurang)'],
            textGap: 10,
            textStyle: {
                color: '#1E293B',
                fontSize: 11,
                fontWeight: 600,
            },
        },
        tooltip: {
            ...lightTooltipConfig,
            formatter: ({ data }) => {
                if (!data) return '';
                const score = data.value?.[2] || data.scoreInt || 0;
                return `
                    <div style="min-width: 190px; font-family: inherit;">
                        <div style="font-weight: 700; color: #0F172A; font-size: 13px; border-bottom: 1px solid #F1F5F9; padding-bottom: 4px; margin-bottom: 6px;">
                            ${escapeHtml(data.prodi)}
                        </div>
                        <div style="font-size: 12px; color: #475569; margin-bottom: 3px;">
                            Aspek: <strong style="color: #0F172A;">${escapeHtml(data.aspectLabel)}</strong>
                        </div>
                        <div style="font-size: 12px; color: #475569; margin-bottom: 3px;">
                            Skor Rerata: <strong style="color: ${score <= 2 ? '#B91C1C' : '#0D542B'}; font-size: 13px;">${data.rawAvg || score} / 5</strong> (${escapeHtml(scaleDescriptions[score] || '-')})
                        </div>
                        <div style="font-size: 11px; color: #64748B;">
                            Total Responden: <strong style="color: #334155;">${formatNumber(data.count, 0)} atasan</strong>
                        </div>
                        <div style="margin-top: 5px; font-size: 10px; color: #0D542B; font-weight: 600; border-top: 1px dashed #E2E8F0; padding-top: 4px;">
                            Klik sel untuk menyaring responden alumni
                        </div>
                    </div>
                `;
            },
        },
        series: [{
            name: 'Rata-rata Skor per Prodi',
            type: 'heatmap',
            data: values,
            itemStyle: {
                borderColor: '#FFFFFF',
                borderWidth: 1.5,
            },
            label: {
                show: false,
            },
            emphasis: {
                focus: 'none',
                itemStyle: {
                    borderColor: '#FFFFFF',
                    borderWidth: 1.5,
                    shadowBlur: 0,
                    shadowColor: 'transparent',
                },
                label: {
                    show: true,
                    formatter: ({ data }) => {
                        const score = data?.value?.[2] || data?.scoreInt || 0;
                        return score > 0 ? String(score) : '';
                    },
                    color: ({ data }) => {
                        const score = data?.value?.[2] || data?.scoreInt || 0;
                        return score === 3 ? '#0F172A' : '#FFFFFF';
                    },
                    fontWeight: 'bold',
                    fontSize: 12,
                },
            },
        }],
    };
});

// Tabel Ringkasan Program Studi untuk Tab Data Ringkasan.
const prodiSummaryTable = computed(() => ({
    columns: [
        { key: 'prodi', label: 'Program Studi' },
        { key: 'respondents', label: 'Jumlah Responden Atasan (n)', align: 'right' },
        { key: 'average', label: 'Rata-rata Skor Evaluasi (1-5)', align: 'center' },
    ],
    rows: prodiSummary.value.map((item) => ({
        prodi: item.prodi,
        respondents: formatNumber(item.respondents, 0),
        average: item.average ? `${item.raw_average || item.average} / 5` : '-',
    })),
}));

// -----------------------------------------------------------------------------
// EVENT HANDLER KLIK GRAFIK & CROSS-FILTERING REAKTIF
// -----------------------------------------------------------------------------
const handleChartSelect = (params) => {
    if (!params) return;

    if (selectedIndicatorKey.value === 'kinerja') {
        const d = params.data;
        if (d && d.kode && d.scaleLevel) {
            if (selectedItem.value?.type === 'aspek_opsi'
                && selectedItem.value.kode === d.kode
                && selectedItem.value.level === d.scaleLevel) {
                selectedItem.value = null;
            } else {
                selectedItem.value = {
                    type: 'aspek_opsi',
                    kode: d.kode,
                    aspek: d.aspek,
                    level: d.scaleLevel,
                    opsi: d.scaleLabel,
                };
            }
        }
    } else if (selectedIndicatorKey.value === 'kesiapan') {
        const label = params.name || params.data?.name;
        if (label) {
            if (selectedItem.value?.type === 'kesiapan' && selectedItem.value.label === label) {
                selectedItem.value = null;
            } else {
                selectedItem.value = {
                    type: 'kesiapan',
                    label,
                };
            }
        }
    } else if (selectedIndicatorKey.value === 'prodi') {
        const d = params.data;
        const prodiName = d?.prodi || params.data?.name || String(params.name || '').split(' (n=')[0].trim();
        if (prodiName) {
            if (selectedItem.value?.type === 'prodi'
                && selectedItem.value.name === prodiName
                && (!d?.aspectLabel || selectedItem.value.aspect === d.aspectLabel)) {
                selectedItem.value = null;
            } else {
                selectedItem.value = {
                    type: 'prodi',
                    name: prodiName,
                    aspect: d?.aspectLabel || null,
                    aspectKey: d?.aspectKey || null,
                    score: d?.scoreInt || null,
                };
            }
        }
    }

    currentPage.value = 1;
};

// Reset seleksi filter grafik aktif.
const resetSelection = () => {
    selectedItem.value = null;
    currentPage.value = 1;
};

// Label deskriptif filter grafik aktif untuk ditampilkan pada banner context.
const selectedFilterLabel = computed(() => {
    const sel = selectedItem.value;
    if (!sel) return '';
    if (sel.type === 'aspek_opsi') return `${sel.aspek} · Nilai: ${sel.opsi}`;
    if (sel.type === 'kesiapan') return `Kesiapan Kerja: ${sel.label}`;
    if (sel.type === 'prodi') {
        if (sel.aspect) {
            return `${sel.name} · Aspek: ${sel.aspect}${sel.score ? ` (Skor: ${sel.score})` : ''}`;
        }
        return `Program Studi: ${sel.name}`;
    }
    return '';
});

// Mengambil jawaban evaluasi atasan untuk kode pertanyaan tertentu (toleran prefix EVA_).
const getRecordAnswer = (record, kode) => {
    if (!record?.answers || !kode) return '';
    return record.answers[kode]
        || record.answers[`EVA_${kode}`]
        || record.answers[kode.replace(/^EVA_/, '')]
        || '';
};

// Menyaring records berdasarkan filter grafik aktif (cross-filtering).
const selectedRecords = computed(() => {
    const list = records.value;
    const sel = selectedItem.value;
    if (!sel) return list;

    if (sel.type === 'aspek_opsi') {
        return list.filter((r) => {
            const rawAns = getRecordAnswer(r, sel.kode);
            if (!rawAns) return false;

            return getLikertLevelFromLabel(rawAns) === Number(sel.level)
                || String(rawAns).trim().toLowerCase() === String(sel.opsi).trim().toLowerCase();
        });
    }

    if (sel.type === 'kesiapan') {
        return list.filter((r) => {
            const rawAns = getRecordAnswer(r, 'tingkat_kesiapan_kerja');
            return String(rawAns).trim().toLowerCase() === String(sel.label).trim().toLowerCase();
        });
    }

    if (sel.type === 'prodi') {
        return list.filter((r) => r.nama_prodi === sel.name);
    }

    return list;
});

// Menyaring tabel data responden berdasarkan kata kunci pencarian.
const filteredRecords = computed(() => {
    const list = selectedRecords.value;
    const query = tableSearch.value.trim().toLowerCase();
    if (!query) return list;

    return list.filter((r) => [r.nama, r.nim, r.nama_prodi, r.nama_perusahaan, r.nama_atasan]
        .some((val) => String(val || '').toLowerCase().includes(query)));
});

// Paginasi tabel responden lokal.
const totalPages = computed(() => Math.max(1, Math.ceil(filteredRecords.value.length / rowsPerPage.value)));
const paginatedRecords = computed(() => {
    const start = (currentPage.value - 1) * rowsPerPage.value;
    return filteredRecords.value.slice(start, start + rowsPerPage.value);
});

// Reset halaman paginasi jika pencarian atau jumlah per halaman berubah.
watch([tableSearch, rowsPerPage], () => {
    currentPage.value = 1;
});
watch(totalPages, () => {
    if (currentPage.value > totalPages.value) currentPage.value = totalPages.value;
});

// Prefix dasar rute analitik yang menyesuaikan role pengguna aktif
const analyticsBaseUrl = computed(() => {
    if (typeof window !== 'undefined') {
        const path = window.location.pathname;
        if (path.startsWith('/prodi')) return '/prodi';
        if (path.startsWith('/fakultas')) return '/fakultas';
        if (path.startsWith('/biro3')) return '/biro3';
    }
    return '/superadmin';
});

// Mengarahkan detail alumni ke endpoint detail.
const detailUrl = (record) => {
    const detailBaseUrl = analyticsBaseUrl.value === '/fakultas'
        ? '/fakultas/analitik'
        : ['/prodi', '/biro3'].includes(analyticsBaseUrl.value)
            ? `${analyticsBaseUrl.value}/analitik`
        : analyticsBaseUrl.value;

    return `${detailBaseUrl}/alumni/${record.biodata_id}`;
};

// Menampilkan tautan profil LinkedIn hanya untuk URL profil yang valid.
const isValidLinkedInProfileUrl = (value) => {
    if (!value) return false;
    try {
        const url = new URL(value);
        return ['http:', 'https:'].includes(url.protocol) && /(^|\.)linkedin\.com$/i.test(url.hostname);
    } catch {
        return false;
    }
};

// Memuat payload baru ketika filter tahun atau prodi berubah.
const scheduleLoad = () => {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(async () => {
        isLoading.value = true;
        loadError.value = '';
        selectedItem.value = null;
        const params = new URLSearchParams({ tahun: selectedYear.value, prodi_id: selectedProdiId.value });

        try {
            const response = await fetch(`${analyticsBaseUrl.value}/analitik-atasan/data?${params.toString()}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok) {
                throw new Error('analytics-request-failed');
            }
            currentAnalytics.value = await response.json();
            currentPage.value = 1;
        } catch (error) {
            console.error('Gagal memuat grafik evaluasi atasan:', error);
            loadError.value = 'Gagal memuat data grafik evaluasi atasan.';
        } finally {
            isLoading.value = false;
        }
    }, 250);
};

// Mengembalikan filter ke seluruh tahun dan seluruh program studi.
const resetFilters = () => {
    selectedYear.value = 'ALL';
    selectedProdiId.value = '';
    selectedItem.value = null;
    tableSearch.value = '';
    currentPage.value = 1;
    scheduleLoad();
};

// Menjaga state lokal tetap mengikuti props ketika halaman Inertia dinavigasi ulang.
watch(() => props.analytics, (value) => {
    currentAnalytics.value = value;
    selectedYear.value = String(value?.filters?.selected_year || 'ALL');
    selectedProdiId.value = String(value?.filters?.selected_prodi_id || '');
}, { deep: true });

onBeforeUnmount(() => {
    clearTimeout(filterTimer);
});
</script>

<template>
    <Head title="Grafik Evaluasi Atasan - Tracer Study" />

    <div class="min-h-screen bg-[#F5F7F8] text-slate-900 lg:flex min-w-[1280px]">
        <Sidebar :user="user" active-menu="analytics-atasan" />

        <main class="min-w-0 flex-1 lg:pl-72">
            <!-- Header hijau institusi UKDW -->
            <header class="border-b border-emerald-900/40 bg-[#0D542B] px-5 py-7 text-white sm:px-8">
                <div class="w-full">
                    <div class="flex flex-wrap items-center gap-2 text-xs font-medium text-emerald-100">
                        <Link :href="`${analyticsBaseUrl}/dashboard`" class="font-semibold hover:text-white">Dashboard</Link>
                        <span class="text-emerald-300/70">/</span>
                        <span class="rounded bg-white/10 px-2 py-0.5 font-bold text-white">Grafik Evaluasi Atasan</span>
                    </div>
                    <div class="mt-5 flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                        <div class="min-w-0">
                            <h1 class="mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl">Grafik Evaluasi Atasan</h1>
                            <p class="mt-2 max-w-3xl text-sm leading-6 text-emerald-100/90">Visualisasi komprehensif jawaban atasan langsung dan pengguna lulusan mengenai kinerja, kompetensi, dan kesiapan alumni di tempat kerja.</p>
                        </div>
                        <p class="text-xs text-emerald-100/90">Sumber aktif: Kuesioner Evaluasi Pengguna Lulusan</p>
                    </div>
                </div>
            </header>

            <div class="mt-5 space-y-5 px-5 pb-8 sm:px-8 w-full">
                <!-- Filter Bar Standar dengan Pemilih Indikator Terpadu -->
                <section class="bg-white p-5 shadow-lg shadow-slate-900/5 rounded-xl border border-slate-200">
                    <div class="grid gap-4 xl:grid-cols-[minmax(18rem,2fr)_minmax(14rem,1.2fr)_minmax(12rem,1fr)_auto] xl:items-end">
                        <!-- Pemilih Indikator Utama (One Focused Indicator) -->
                        <label class="block min-w-0 text-xs font-bold uppercase tracking-wide text-slate-500">
                            Pilih Indikator
                            <select
                                v-model="selectedIndicatorKey"
                                class="mt-2 block w-full rounded bg-slate-100 px-3 py-3 text-sm font-bold normal-case text-slate-900 outline-none focus:bg-white focus:ring-2 focus:ring-[#0D542B]"
                            >
                                <option v-for="opt in indicatorOptions" :key="opt.key" :value="opt.key">
                                    {{ opt.code }}: {{ opt.title }}
                                </option>
                            </select>
                        </label>

                        <!-- Filter Program Studi -->
                        <label class="block min-w-0 text-xs font-bold uppercase tracking-wide text-slate-500">
                            Program Studi
                            <select
                                v-model="selectedProdiId"
                                class="mt-2 block w-full rounded bg-slate-100 px-3 py-3 text-sm font-bold normal-case text-slate-900 outline-none focus:bg-white focus:ring-2 focus:ring-[#0D542B]"
                                @change="scheduleLoad"
                            >
                                <option value="">Semua program studi</option>
                                <option v-for="prodi in filters.prodis || []" :key="prodi.id" :value="String(prodi.id)">
                                    {{ prodi.nama_prodi }}
                                </option>
                            </select>
                        </label>

                        <!-- Filter Tahun Lulusan -->
                        <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">
                            Tahun Lulusan
                            <select
                                v-model="selectedYear"
                                class="mt-2 block w-full rounded bg-slate-100 px-3 py-3 text-sm font-bold normal-case text-slate-900 outline-none focus:bg-white focus:ring-2 focus:ring-[#0D542B]"
                                @change="scheduleLoad"
                            >
                                <option v-for="year in filters.available_years || ['ALL']" :key="year" :value="String(year)">
                                    {{ year === 'ALL' ? 'Semua Tahun' : year }}
                                </option>
                            </select>
                        </label>

                        <!-- Tombol Reset -->
                        <button
                            type="button"
                            class="rounded bg-slate-100 px-4 py-3 text-sm font-bold text-slate-700 transition-colors hover:bg-slate-200 cursor-pointer"
                            @click="resetFilters"
                        >
                            Reset filter
                        </button>
                    </div>

                    <div v-if="isLoading || loadError" class="mt-4 border-t border-slate-100 pt-3 text-sm">
                        <p v-if="isLoading" class="font-semibold text-[#0D542B]">Memperbarui data evaluasi atasan...</p>
                        <p v-if="loadError" class="font-semibold text-rose-700">{{ loadError }}</p>
                    </div>
                </section>

                <!-- Kartu KPI Ringkasan -->
                <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                    <div class="grid grid-cols-2 gap-3.5 lg:grid-cols-5">
                        <div
                            v-for="card in [
                                { label: 'Populasi Evaluasi', value: formatNumber(summary.population), note: 'Undangan pada filter aktif' },
                                { label: 'Sudah Mengisi', value: formatNumber(summary.submitted), note: 'Evaluasi masuk dan valid' },
                                { label: 'Belum Mengisi', value: formatNumber(summary.pending), note: 'Menunggu jawaban atasan' },
                                { label: 'Response Rate', value: `${formatNumber(summary.response_rate, 1)}%`, note: 'Evaluasi masuk / populasi' },
                                { label: 'Rata-rata Skor', value: summary.average_score ? `${summary.average_score} / 5` : '-', note: 'Skor agregat aspek Likert' },
                            ]"
                            :key="card.label"
                            class="flex min-h-28 flex-col justify-between rounded-xl border border-slate-200 bg-white p-4"
                        >
                            <dt class="text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ card.label }}</dt>
                            <dd class="mt-1 text-2xl font-extrabold tracking-tight text-slate-900">{{ card.value }}</dd>
                            <p class="mt-2 border-t border-slate-100 pt-2 text-xs text-slate-500">{{ card.note }}</p>
                        </div>
                    </div>
                </section>

                <!-- State Kosong bila Belum Ada Data -->
                <section v-if="!currentAnalytics.has_data" class="rounded-xl border border-slate-200 bg-white p-8 text-center">
                    <h2 class="text-xl font-extrabold">Belum ada evaluasi atasan pada filter ini</h2>
                    <p class="mt-2 text-sm text-slate-600">Grafik akan terisi otomatis setelah atasan mengirimkan evaluasi pengguna lulusan.</p>
                </section>

                <template v-else>
                    <!-- Navigasi Tab Utama: Grafik & Analisis vs Data Responden -->
                    <nav class="flex gap-1 rounded-xl border border-slate-200 bg-white p-1.5" role="tablist" aria-label="Tampilan evaluasi atasan">
                        <button
                            type="button"
                            role="tab"
                            :aria-selected="activePanel === 'charts'"
                            class="flex-1 rounded-lg px-4 py-2.5 text-sm font-bold transition-all cursor-pointer"
                            :class="activePanel === 'charts' ? 'bg-[#0D542B] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'"
                            @click="activePanel = 'charts'"
                        >
                            Grafik & Analisis
                        </button>
                        <button
                            type="button"
                            role="tab"
                            :aria-selected="activePanel === 'data'"
                            class="flex-1 rounded-lg px-4 py-2.5 text-sm font-bold transition-all cursor-pointer"
                            :class="activePanel === 'data' ? 'bg-[#0D542B] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'"
                            @click="activePanel = 'data'"
                        >
                            Data Responden ({{ selectedRecords.length }})
                        </button>
                    </nav>

                    <!-- TAB 1: GRAFIK & ANALISIS TERPADU -->
                    <div v-if="activePanel === 'charts'" class="space-y-5">
                        <!-- Kontainer Terpadu Indikator Tunggal (Sesuai Standar Grafik Univ & Prodi) -->
                        <article class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6 shadow-xs">
                            <!-- Header Indikator & Toggle Tampilan -->
                            <div class="border-b border-slate-100 pb-4">
                                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-bold text-[#0D542B]">
                                                {{ currentIndicator.code }}
                                            </span>
                                            <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">
                                                {{ currentIndicator.badge }}
                                            </span>
                                        </div>
                                        <h2 class="mt-2 text-xl font-extrabold text-slate-900 sm:text-2xl">
                                            {{ currentIndicator.title }}
                                        </h2>
                                        <p class="mt-1 text-xs leading-relaxed text-slate-500 sm:text-sm">
                                            {{ currentIndicator.description }}
                                        </p>
                                    </div>

                                    <!-- Toggle Grafik vs Data Ringkasan Tabulasi (Gaya Segmented Pill Netral Slate) -->
                                    <div class="flex shrink-0 items-center gap-1 rounded-lg border border-slate-200 bg-slate-100 p-1">
                                        <button
                                            type="button"
                                            class="rounded-md px-3 py-1.5 text-xs font-semibold transition-all cursor-pointer whitespace-nowrap"
                                            :class="activeViewMode === 'chart' ? 'bg-white font-bold text-slate-900 shadow-xs border border-slate-200/80' : 'text-slate-600 hover:text-slate-900'"
                                            @click="activeViewMode = 'chart'"
                                        >
                                            Grafik
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-md px-3 py-1.5 text-xs font-semibold transition-all cursor-pointer whitespace-nowrap"
                                            :class="activeViewMode === 'table' ? 'bg-white font-bold text-slate-900 shadow-xs border border-slate-200/80' : 'text-slate-600 hover:text-slate-900'"
                                            @click="activeViewMode = 'table'"
                                        >
                                            Data Ringkasan
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- BANNER FILTER GRAFIK AKTIF (CROSS-FILTERING FEEDBACK) -->
                            <div
                                v-if="selectedItem"
                                class="mt-4 flex items-center justify-between rounded-lg bg-emerald-50 px-4 py-2.5 text-xs text-emerald-900 border border-emerald-200"
                            >
                                <div class="flex items-center gap-2">
                                    <span class="inline-block h-2 w-2 rounded-full bg-[#0D542B]"></span>
                                    <span>Filter grafik aktif: <strong>{{ selectedFilterLabel }}</strong> (<strong>{{ selectedRecords.length }}</strong> alumni dinilai)</span>
                                </div>
                                <button
                                    type="button"
                                    class="font-bold underline hover:text-emerald-700 cursor-pointer"
                                    @click="resetSelection"
                                >
                                    Hapus filter grafik
                                </button>
                            </div>

                            <!-- TAMPILAN GRAFIK VISUAL (ACTIVE VIEW MODE = 'CHART') -->
                            <div v-if="activeViewMode === 'chart'" class="mt-5">
                                <!-- Indikator 01: Evaluasi Aspek Kinerja (Horizontal Stacked Bar 100%) -->
                                <div v-if="selectedIndicatorKey === 'kinerja'" class="min-h-[500px]">
                                    <AnalyticsEChart
                                        :option="kinerjaStackedOption"
                                        :loading="isLoading"
                                        :height="Math.max(480, likertItems.length * 52)"
                                        aria-label="Grafik distribusi aspek kinerja alumni"
                                        @select="handleChartSelect"
                                    />
                                </div>

                                <!-- Indikator 02: Tingkat Kesiapan Kerja (Horizontal Bar dengan Jumlah & %) -->
                                <div v-else-if="selectedIndicatorKey === 'kesiapan'" class="min-h-[380px]">
                                    <AnalyticsEChart
                                        :option="readinessOption"
                                        :loading="isLoading"
                                        :height="360"
                                        aria-label="Grafik distribusi kesiapan kerja alumni"
                                        @select="handleChartSelect"
                                    />
                                </div>

                                <!-- Indikator 03: Perbandingan Program Studi (Heatmap Matriks Antar Prodi & Aspek) -->
                                <div v-else-if="selectedIndicatorKey === 'prodi'" class="min-h-[480px]">
                                    <div v-if="prodis.length === 0" class="flex h-64 items-center justify-center rounded-lg border border-dashed border-slate-200 text-xs text-slate-500">
                                        Belum ada data evaluasi atasan yang sesuai dengan filter tahun terpilih.
                                    </div>
                                    <div v-else class="space-y-4">
                                        <AnalyticsEChart
                                            :option="prodiOption"
                                            :loading="isLoading"
                                            :height="Math.max(480, prodis.length * 36 + 120)"
                                            aria-label="Grafik matriks perbandingan skor evaluasi atasan antarprogram studi"
                                            @select="handleChartSelect"
                                        />
                                        <div class="flex flex-wrap items-center justify-between gap-3 px-1 text-xs text-slate-500">
                                            <p class="leading-relaxed">
                                                <span class="font-semibold text-slate-700">Caption:</span>
                                                Intensitas skor rata-rata evaluasi kinerja alumni per aspek kompetensi pada setiap program studi dengan gradasi hijau UKDW dan sorotan merah untuk skor rendah (1–2).
                                            </p>
                                            <p class="font-semibold text-slate-400">N valid: {{ summary.submitted || 0 }}</p>
                                        </div>

                                        <!-- Legenda Skala Penilaian Jelas & Kontras -->
                                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50/80 p-3 text-xs">
                                            <div class="text-slate-600">
                                                Responden Evaluasi Masuk: <strong class="text-slate-900 font-semibold">{{ formatNumber(summary.submitted || 0, 0) }} atasan</strong>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="font-bold text-slate-500 mr-0.5">Skala Penilaian:</span>
                                                <div class="flex items-center gap-1.5">
                                                    <span class="inline-block h-3.5 w-3.5 rounded-sm bg-[#B91C1C]"></span>
                                                    <span class="text-slate-700">1 (Sangat Kurang)</span>
                                                </div>
                                                <div class="flex items-center gap-1.5">
                                                    <span class="inline-block h-3.5 w-3.5 rounded-sm bg-[#EF4444]"></span>
                                                    <span class="text-slate-700">2 (Kurang)</span>
                                                </div>
                                                <div class="flex items-center gap-1.5">
                                                    <span class="inline-block h-3.5 w-3.5 rounded-sm bg-[#E2F3E7] border border-slate-200"></span>
                                                    <span class="text-slate-700">3 (Cukup)</span>
                                                </div>
                                                <div class="flex items-center gap-1.5">
                                                    <span class="inline-block h-3.5 w-3.5 rounded-sm bg-[#2F7D4A]"></span>
                                                    <span class="text-slate-700">4 (Baik)</span>
                                                </div>
                                                <div class="flex items-center gap-1.5">
                                                    <span class="inline-block h-3.5 w-3.5 rounded-sm bg-[#0D542B]"></span>
                                                    <span class="text-slate-700">5 (Sangat Baik)</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- KILAS INDIKATOR (APPLE WEATHER STYLE - BEBAS AI SLOP) -->
                                <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50/70 p-4 sm:p-5">
                                    <p class="text-xs font-bold uppercase tracking-wider text-[#0D542B]">
                                        Kilas Analisis {{ currentIndicator.shortLabel }}
                                    </p>

                                    <!-- Kilas Kinerja -->
                                    <div v-if="selectedIndicatorKey === 'kinerja'" class="mt-3 grid gap-4 sm:grid-cols-3">
                                        <div class="rounded-lg border border-slate-200 bg-white p-3.5">
                                            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Rata-rata Agregat</p>
                                            <p class="mt-1 text-2xl font-extrabold text-[#0D542B]">
                                                {{ performanceHighlights.overallAverage ?? '-' }} <span class="text-xs font-semibold text-slate-400">/ 5</span>
                                            </p>
                                            <p class="mt-1 text-xs text-slate-500">Dari {{ likertItems.length }} butir kompetensi yang dinilai.</p>
                                        </div>
                                        <div class="rounded-lg border border-slate-200 bg-white p-3.5">
                                            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Aspek Skor Tertinggi</p>
                                            <p class="mt-1 truncate text-sm font-extrabold text-slate-900" :title="performanceHighlights.highest?.aspek">
                                                {{ performanceHighlights.highest?.aspek || '-' }}
                                            </p>
                                            <p class="mt-1 text-xs font-bold text-emerald-800">Skor {{ performanceHighlights.highest?.average ?? '-' }} / 5</p>
                                        </div>
                                        <div class="rounded-lg border border-slate-200 bg-white p-3.5">
                                            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Fokus Peningkatan</p>
                                            <p class="mt-1 truncate text-sm font-extrabold text-slate-900" :title="performanceHighlights.lowest?.aspek">
                                                {{ performanceHighlights.lowest?.aspek || '-' }}
                                            </p>
                                            <p class="mt-1 text-xs font-bold text-slate-700">Skor {{ performanceHighlights.lowest?.average ?? '-' }} / 5</p>
                                        </div>
                                    </div>

                                    <!-- Kilas Kesiapan -->
                                    <div v-else-if="selectedIndicatorKey === 'kesiapan'" class="mt-3 grid gap-4 sm:grid-cols-3">
                                        <div class="rounded-lg border border-slate-200 bg-white p-3.5">
                                            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Tingkat Kesiapan Positif</p>
                                            <p class="mt-1 text-2xl font-extrabold text-[#0D542B]">{{ readinessHighlights.readyRate }}%</p>
                                            <p class="mt-1 text-xs text-slate-500">Alumni dinilai Sangat Siap atau Cukup Siap.</p>
                                        </div>
                                        <div class="rounded-lg border border-slate-200 bg-white p-3.5">
                                            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Pilihan Terbanyak</p>
                                            <p class="mt-1 text-lg font-extrabold text-slate-900">{{ readinessHighlights.topLabel }}</p>
                                            <p class="mt-1 text-xs text-slate-500">Frekuensi respons paling dominan.</p>
                                        </div>
                                        <div class="rounded-lg border border-slate-200 bg-white p-3.5">
                                            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Total Evaluasi Kesiapan</p>
                                            <p class="mt-1 text-2xl font-extrabold text-slate-900">{{ formatNumber(readinessHighlights.total) }}</p>
                                            <p class="mt-1 text-xs text-slate-500">Respon valid yang tercatat.</p>
                                        </div>
                                    </div>

                                    <!-- Kilas Prodi -->
                                    <div v-else class="mt-3 grid gap-4 sm:grid-cols-3">
                                        <div class="rounded-lg border border-slate-200 bg-white p-3.5">
                                            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Total Program Studi</p>
                                            <p class="mt-1 text-2xl font-extrabold text-[#0D542B]">{{ prodiSummary.length }}</p>
                                            <p class="mt-1 text-xs text-slate-500">Jurusan dengan evaluasi atasan masuk.</p>
                                        </div>
                                        <div class="rounded-lg border border-slate-200 bg-white p-3.5">
                                            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Skor Prodi Tertinggi</p>
                                            <p class="mt-1 truncate text-sm font-extrabold text-slate-900">{{ prodiSummary[0]?.prodi || '-' }}</p>
                                            <p class="mt-1 text-xs font-bold text-emerald-800">Skor {{ prodiSummary[0]?.average ?? '-' }} / 5 ({{ prodiSummary[0]?.respondents || 0 }} atasan)</p>
                                        </div>
                                        <div class="rounded-lg border border-slate-200 bg-white p-3.5">
                                            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Respon Terbanyak</p>
                                            <p class="mt-1 truncate text-sm font-extrabold text-slate-900">
                                                {{ [...prodiSummary].sort((a,b) => b.respondents - a.respondents)[0]?.prodi || '-' }}
                                            </p>
                                            <p class="mt-1 text-xs font-bold text-slate-700">
                                                {{ [...prodiSummary].sort((a,b) => b.respondents - a.respondents)[0]?.respondents || 0 }} evaluasi masuk
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- TAMPILAN DATA RINGKASAN TABULASI (ACTIVE VIEW MODE = 'TABLE') -->
                            <div v-else class="mt-5 overflow-x-auto rounded-lg border border-slate-200">
                                <table class="w-full min-w-[720px] text-left text-xs">
                                    <thead class="border-b border-slate-200 bg-slate-50 text-[11px] uppercase tracking-wide text-slate-500">
                                        <tr v-if="selectedIndicatorKey === 'kinerja'">
                                            <th v-for="col in kinerjaSummaryTable.columns" :key="col.key" class="px-3.5 py-3" :class="col.align === 'right' ? 'text-right' : (col.align === 'center' ? 'text-center' : 'text-left')">
                                                {{ col.label }}
                                            </th>
                                        </tr>
                                        <tr v-else-if="selectedIndicatorKey === 'kesiapan'">
                                            <th v-for="col in readinessSummaryTable.columns" :key="col.key" class="px-3.5 py-3" :class="col.align === 'right' ? 'text-right' : 'text-left'">
                                                {{ col.label }}
                                            </th>
                                        </tr>
                                        <tr v-else>
                                            <th v-for="col in prodiSummaryTable.columns" :key="col.key" class="px-3.5 py-3" :class="col.align === 'right' ? 'text-right' : (col.align === 'center' ? 'text-center' : 'text-left')">
                                                {{ col.label }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <!-- Baris Kinerja -->
                                        <template v-if="selectedIndicatorKey === 'kinerja'">
                                            <tr v-for="row in kinerjaSummaryTable.rows" :key="row.aspek" class="hover:bg-slate-50">
                                                <td class="px-3.5 py-2.5 font-bold text-slate-800">{{ row.aspek }}</td>
                                                <td class="px-3.5 py-2.5 text-right font-semibold text-slate-600">{{ row.valid_count }}</td>
                                                <td class="px-3.5 py-2.5 text-center font-extrabold text-[#0D542B]">{{ row.average }}</td>
                                                <td class="px-3.5 py-2.5 text-right text-slate-700">{{ row.sangat_baik }}</td>
                                                <td class="px-3.5 py-2.5 text-right text-slate-700">{{ row.baik }}</td>
                                                <td class="px-3.5 py-2.5 text-right text-slate-700">{{ row.cukup }}</td>
                                                <td class="px-3.5 py-2.5 text-right text-slate-700">{{ row.kurang }}</td>
                                                <td class="px-3.5 py-2.5 text-right text-slate-700">{{ row.sangat_kurang }}</td>
                                            </tr>
                                        </template>

                                        <!-- Baris Kesiapan -->
                                        <template v-else-if="selectedIndicatorKey === 'kesiapan'">
                                            <tr v-for="row in readinessSummaryTable.rows" :key="row.label" class="hover:bg-slate-50">
                                                <td class="px-3.5 py-2.5 font-bold text-slate-800">{{ row.label }}</td>
                                                <td class="px-3.5 py-2.5 text-right font-semibold text-slate-600">{{ row.count }}</td>
                                                <td class="px-3.5 py-2.5 text-right font-extrabold text-[#0D542B]">{{ row.percentage }}</td>
                                            </tr>
                                        </template>

                                        <!-- Baris Prodi -->
                                        <template v-else>
                                            <tr v-for="row in prodiSummaryTable.rows" :key="row.prodi" class="hover:bg-slate-50">
                                                <td class="px-3.5 py-2.5 font-bold text-slate-800">{{ row.prodi }}</td>
                                                <td class="px-3.5 py-2.5 text-right font-semibold text-slate-600">{{ row.respondents }}</td>
                                                <td class="px-3.5 py-2.5 text-center font-extrabold text-[#0D542B]">{{ row.average }}</td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>

                            <!-- SEAMLESS INLINE CONTEXT TABLE (DON'T MAKE ME THINK) -->
                            <!-- Muncul mulus tepat di bawah grafik untuk menampilkan responden pembentuk elemen grafik -->
                            <div class="mt-8 border-t border-slate-200 pt-6">
                                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                                    <div>
                                        <h3 class="text-base font-extrabold text-slate-900">
                                            Daftar Responden Alumni & Atasan
                                            <span v-if="selectedItem" class="ml-1 text-xs font-semibold text-[#0D542B]">
                                                ({{ selectedFilterLabel }})
                                            </span>
                                        </h3>
                                        <p class="mt-0.5 text-xs text-slate-500">
                                            {{ selectedItem ? 'Menampilkan alumni yang membentuk segmen grafik terpilih.' : 'Daftar seluruh evaluasi pengguna lulusan yang masuk pada filter aktif.' }}
                                        </p>
                                    </div>

                                    <div class="w-full sm:w-72">
                                        <input
                                            v-model="tableSearch"
                                            type="text"
                                            placeholder="Cari nama, NIM, atasan, instansi..."
                                            class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-800 outline-none placeholder:text-slate-400 focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B]"
                                        />
                                    </div>
                                </div>

                                <!-- Tabel Inline Responden -->
                                <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200">
                                    <table class="w-full min-w-[760px] text-left text-xs">
                                        <thead class="border-b border-slate-200 bg-slate-50 text-[11px] uppercase tracking-wide text-slate-500">
                                            <tr>
                                                <th class="w-12 px-3.5 py-3 text-center">No</th>
                                                <th class="px-3.5 py-3">Nama Alumni & NIM</th>
                                                <th class="px-3.5 py-3">Program Studi</th>
                                                <th class="px-3.5 py-3">Perusahaan / Instansi</th>
                                                <th class="px-3.5 py-3">Nama Atasan Langsung</th>
                                                <th v-if="selectedItem?.type === 'aspek_opsi'" class="px-3.5 py-3 text-center">Nilai Aspek</th>
                                                <th v-else-if="selectedItem?.type === 'kesiapan'" class="px-3.5 py-3 text-center">Kesiapan Kerja</th>
                                                <th v-else class="px-3.5 py-3 text-center">Rata-rata Skor</th>
                                                <th class="px-3.5 py-3 text-center">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            <tr v-for="(record, idx) in paginatedRecords" :key="record.evaluasi_id" class="hover:bg-slate-50">
                                                <td class="px-3.5 py-2.5 text-center font-mono text-slate-400">
                                                    {{ (currentPage - 1) * rowsPerPage + idx + 1 }}
                                                </td>
                                                <td class="px-3.5 py-2.5">
                                                    <div class="font-bold text-slate-900">{{ record.nama || '-' }}</div>
                                                    <div class="font-mono text-[11px] text-slate-500">{{ record.nim || '-' }}</div>
                                                </td>
                                                <td class="px-3.5 py-2.5 font-medium text-slate-700">
                                                    {{ record.nama_prodi || '-' }}
                                                </td>
                                                <td class="px-3.5 py-2.5 text-slate-800">
                                                    {{ record.nama_perusahaan || '-' }}
                                                </td>
                                                <td class="px-3.5 py-2.5 font-medium text-slate-800">
                                                    {{ record.nama_atasan || '-' }}
                                                </td>
                                                <!-- Kolom Nilai Reaktif Sesuai Pilihan Grafik -->
                                                <td v-if="selectedItem?.type === 'aspek_opsi'" class="px-3.5 py-2.5 text-center">
                                                    <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-bold text-[#0D542B]">
                                                        {{ getRecordAnswer(record, selectedItem.kode) || selectedItem.opsi }}
                                                    </span>
                                                </td>
                                                <td v-else-if="selectedItem?.type === 'kesiapan'" class="px-3.5 py-2.5 text-center">
                                                    <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-bold text-[#0D542B]">
                                                        {{ getRecordAnswer(record, 'tingkat_kesiapan_kerja') || selectedItem.label }}
                                                    </span>
                                                </td>
                                                <td v-else class="px-3.5 py-2.5 text-center font-extrabold text-[#0D542B]">
                                                    {{ record.average_score ? `${record.average_score} / 5` : '-' }}
                                                </td>
                                                <td class="px-3.5 py-2.5 text-center">
                                                    <Link
                                                        :href="detailUrl(record)"
                                                        class="rounded bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-700 hover:bg-slate-200 transition-colors"
                                                    >
                                                        Detail
                                                    </Link>
                                                </td>
                                            </tr>
                                            <tr v-if="paginatedRecords.length === 0">
                                                <td :colspan="selectedItem ? 8 : 7" class="px-4 py-8 text-center text-slate-500">
                                                    Tidak ada data responden yang sesuai dengan filter atau kata kunci pencarian.
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Kontrol Paginasi -->
                                <div class="mt-4 flex flex-col items-center justify-between gap-3 sm:flex-row">
                                    <div class="text-xs text-slate-500">
                                        Menampilkan {{ paginatedRecords.length ? (currentPage - 1) * rowsPerPage + 1 : 0 }} - {{ Math.min(currentPage * rowsPerPage, filteredRecords.length) }} dari {{ filteredRecords.length }} responden
                                    </div>
                                    <PaginationControls
                                        :current-page="currentPage"
                                        :total-pages="totalPages"
                                        @page-change="currentPage = $event"
                                    />
                                </div>
                            </div>
                        </article>
                    </div>

                    <!-- TAB 2: DATA RESPONDEN LENGKAP -->
                    <section v-else class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6 shadow-xs">
                        <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end border-b border-slate-100 pb-4">
                            <div>
                                <h2 class="text-xl font-extrabold text-slate-900">Data Responden Evaluasi Atasan</h2>
                                <p class="mt-1 text-xs text-slate-500">Daftar lengkap respon kuesioner evaluasi pengguna lulusan yang masuk dan terverifikasi.</p>
                            </div>
                            <div class="w-full sm:w-80">
                                <input
                                    v-model="tableSearch"
                                    type="text"
                                    placeholder="Cari nama, NIM, atasan, instansi..."
                                    class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-800 outline-none placeholder:text-slate-400 focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B]"
                                />
                            </div>
                        </div>

                        <!-- Banner Filter Grafik Aktif jika Ada -->
                        <div
                            v-if="selectedItem"
                            class="mt-4 flex items-center justify-between rounded-lg bg-emerald-50 px-4 py-2.5 text-xs text-emerald-900 border border-emerald-200"
                        >
                            <span>Filter grafik aktif: <strong>{{ selectedFilterLabel }}</strong> ({{ selectedRecords.length }} alumni)</span>
                            <button
                                type="button"
                                class="font-bold underline hover:text-emerald-700 cursor-pointer"
                                @click="resetSelection"
                            >
                                Hapus filter grafik
                            </button>
                        </div>

                        <!-- Tabel Utama Data Responden -->
                        <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200">
                            <table class="w-full min-w-[800px] text-left text-xs">
                                <thead class="border-b border-slate-200 bg-slate-50 text-[11px] uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th class="w-12 px-3.5 py-3 text-center">No</th>
                                        <th class="px-3.5 py-3">Nama Alumni & NIM</th>
                                        <th class="px-3.5 py-3">Program Studi</th>
                                        <th class="px-3.5 py-3">Perusahaan / Instansi</th>
                                        <th class="px-3.5 py-3">Nama Atasan Langsung</th>
                                        <th class="px-3.5 py-3 text-center">Rata-rata Skor</th>
                                        <th class="px-3.5 py-3 text-center">LinkedIn</th>
                                        <th class="px-3.5 py-3 text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="(record, idx) in paginatedRecords" :key="record.evaluasi_id" class="hover:bg-slate-50">
                                        <td class="px-3.5 py-2.5 text-center font-mono text-slate-400">
                                            {{ (currentPage - 1) * rowsPerPage + idx + 1 }}
                                        </td>
                                        <td class="px-3.5 py-2.5">
                                            <div class="font-bold text-slate-900">{{ record.nama || '-' }}</div>
                                            <div class="font-mono text-[11px] text-slate-500">{{ record.nim || '-' }}</div>
                                        </td>
                                        <td class="px-3.5 py-2.5 font-medium text-slate-700">
                                            {{ record.nama_prodi || '-' }}
                                        </td>
                                        <td class="px-3.5 py-2.5 text-slate-800">
                                            {{ record.nama_perusahaan || '-' }}
                                        </td>
                                        <td class="px-3.5 py-2.5 font-medium text-slate-800">
                                            {{ record.nama_atasan || '-' }}
                                        </td>
                                        <td class="px-3.5 py-2.5 text-center font-extrabold text-[#0D542B]">
                                            {{ record.average_score ? `${record.average_score} / 5` : '-' }}
                                        </td>
                                        <td class="px-3.5 py-2.5 text-center">
                                            <a
                                                v-if="isValidLinkedInProfileUrl(record.linkedin_url)"
                                                :href="record.linkedin_url"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="font-bold text-sky-700 hover:underline"
                                            >
                                                Profil
                                            </a>
                                            <span v-else class="text-slate-400">-</span>
                                        </td>
                                        <td class="px-3.5 py-2.5 text-center">
                                            <Link
                                                :href="detailUrl(record)"
                                                class="rounded bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-700 hover:bg-slate-200 transition-colors"
                                            >
                                                Detail
                                            </Link>
                                        </td>
                                    </tr>
                                    <tr v-if="paginatedRecords.length === 0">
                                        <td colspan="8" class="px-4 py-8 text-center text-slate-500">
                                            Tidak ada data responden yang sesuai dengan filter atau kata kunci pencarian.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Kontrol Paginasi -->
                        <div class="mt-4 flex flex-col items-center justify-between gap-3 sm:flex-row">
                            <div class="text-xs text-slate-500">
                                Menampilkan {{ paginatedRecords.length ? (currentPage - 1) * rowsPerPage + 1 : 0 }} - {{ Math.min(currentPage * rowsPerPage, filteredRecords.length) }} dari {{ filteredRecords.length }} responden
                            </div>
                            <PaginationControls
                                :current-page="currentPage"
                                :total-pages="totalPages"
                                @page-change="currentPage = $event"
                            />
                        </div>
                    </section>
                </template>
            </div>
        </main>
    </div>
</template>
