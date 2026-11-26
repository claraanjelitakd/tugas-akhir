<script setup>
/**
 * Komponen Indikator 01: Response Rate Universitas dan Program Studi.
 *
 * Mengimplementasikan satu visualisasi response rate dengan panjang batang proporsional
 * terhadap persentase capaian, garis target, jumlah responden, dan inline drill-down.
 * Kartu Kilas Indikator menyajikan narasi ringkas dan manusiawi yang terinspirasi dari
 *    Daily Summary Apple iOS Weather (bebas dari AI slop).
 * Inline Data Table Drill-Down ("Don't Make Me Think"): Saat batang grafik diklik,
 *    daftar data responden langsung muncul secara mulus di bawah grafik lengkap
 *    dengan filter status, pencarian, aksi kontak (WhatsApp/Email), dan unduh Excel.
 */
import { computed, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import AnalyticsEChart from '../AnalyticsEChart.vue';
import PaginationControls from '@/components/PaginationControls.vue';
import {
    baseOption,
    escapeHtml,
    formatNumber,
    responseRatePalette,
} from '../analyticsChartOptions';
import { generateAnalyticsPdf } from '@/utils/analyticsPdfExporter';

// Kontrak properti data indikator, filter aktif, dan status pemuatan data.
const props = defineProps({
    user: { type: Object, default: null },
    chart: { type: Object, default: () => ({}) },
    selectedYear: { type: String, default: '' },
    selectedLabel: { type: String, default: '' },
    loading: { type: Boolean, default: false },
});

// Menentukan apakah aksi kontak tersedia untuk alumni pada lingkup admin aktif.
const canContactRecord = (record) => {
    const role = props.user?.role;

    if (role === 'superadmin' || role === 'admin_fakultas') {
        return true;
    }

    return role === 'admin_prodi'
        && String(props.user?.kode_prodi || '') === String(record?.kode_prodi || '');
};

// Event seleksi data diteruskan ke panel induk untuk cross-filtering.
const emit = defineEmits(['select', 'reset', 'show-data']);

// State seleksi program studi dan status pengisian.
const selectedProdiName = ref('');
const selectedStatus = ref('all'); // 'all', 'sudah', atau 'belum'

// Kontrak grafik tunggal response rate, target, dan jumlah responden.
const activeChartTab = ref('ranking');
const activeChartType = 'Batang partisipasi alumni';
const activeChartReason = 'Panjang batang menunjukkan total alumni; segmen abu-abu di sisi kiri menunjukkan alumni yang belum mengisi, sedangkan segmen berwarna menunjukkan alumni yang sudah mengisi.';

// Pengurutan capaian pada grafik.
const sortAsc = ref(false);

// Mode tampilan: 'chart' (grafik visual) atau 'table' (data ringkasan tabulasi prodi)
const activeViewMode = ref('chart');

// Referensi komponen EChart untuk unduh berkas gambar PNG
const echartRef = ref(null);

const handleDownloadChart = () => {
    if (activeViewMode.value !== 'chart') activeViewMode.value = 'chart';
    setTimeout(() => {
        echartRef.value?.downloadPng('grafik-tingkat-partisipasi-alumni');
    }, 120);
};

const handleDownloadPdf = () => {
    const chartBase64 = echartRef.value?.getImageDataUrl ? echartRef.value.getImageDataUrl() : null;

    const headers = ['No', 'Program Studi', 'Populasi (N)', 'Sudah Mengisi', 'Belum Mengisi', 'Response Rate', 'Target', 'Gap Target', 'Status Capaian'];

    const sortedList = [...displaySeries.value].sort((a, b) => sortAsc.value ? a.response_rate - b.response_rate : b.response_rate - a.response_rate);

    const rows = sortedList.map((item, idx) => [
        idx + 1,
        item.nama_prodi || '-',
        formatNumber(item.population, 0),
        formatNumber(item.respondents, 0),
        formatNumber(item.unanswered, 0),
        `${formatNumber(item.response_rate, 1)}%`,
        `${formatNumber(item.target, 0)}%`,
        `${item.target_gap >= 0 ? '+' : ''}${formatNumber(item.target_gap, 1)}%`,
        getParticipationStatus(item).label || '-',
    ]);

    const summaryNarrative = [
        naturalSummary.value?.title ? `${naturalSummary.value.title}:` : '',
        naturalSummary.value?.lead || '',
        naturalSummary.value?.detail || '',
    ].filter(Boolean).join(' ');

    generateAnalyticsPdf({
        title: '01. Response Rate Universitas',
        subtitle: 'Tingkat Partisipasi Pengisian Tracer Study Alumni Antar Program Studi',
        cohortYear: activeYear.value,
        scopeName: selectedProdiName.value ? `Fokus: ${selectedProdiName.value}` : 'Seluruh Program Studi',
        summaryText: summaryNarrative,
        chartImageBase64: chartBase64,
        tableHeaders: headers,
        tableRows: rows,
        fileName: `Laporan_Analisis_Response_Rate_${String(activeYear.value).replace(/[^a-zA-Z0-9_-]/g, '_')}`,
    });
};

// State kontrol tabel drill-down inline di bawah grafik.
const searchQuery = ref('');
const currentPage = ref(1);
const rowsPerPage = ref(10);
const forceShowTable = ref(true);

// Ekstraksi data seri program studi dan catatan alumni dari payload.
const series = computed(() => props.chart?.series || []);
const records = computed(() => props.chart?.records || []);
const activeYear = computed(() => props.selectedYear === 'ALL'
    ? 'Semua Tahun'
    : (props.selectedYear || records.value[0]?.tahun_lulus || String(new Date().getFullYear())));

// Target response rate dinamis dari backend summary atau item seri (default: 60%)
const currentTarget = computed(() => {
    return Number(props.chart?.summary?.target ?? 60.0);
});

// Seri response rate sudah diagregasi backend; Vue hanya menggunakannya untuk tampilan.
const displaySeries = computed(() => series.value);

// Data program studi yang sedang dipilih secara aktif pada grafik.
const selectedProdiItem = computed(() => {
    if (!selectedProdiName.value) return null;
    return displaySeries.value.find((item) => item.nama_prodi === selectedProdiName.value) || null;
});

// Reset halaman paginasi tabel inline ketika prodi, status, atau pencarian berubah.
watch([selectedProdiName, selectedStatus, searchQuery], () => {
    currentPage.value = 1;
});

// Sinkronisasi apabila selectedLabel direset dari komponen induk.
watch(() => props.selectedLabel, (newVal) => {
    if (!newVal && selectedProdiName.value) {
        selectedProdiName.value = '';
        selectedStatus.value = 'all';
        forceShowTable.value = false;
    } else if (newVal && newVal !== selectedProdiName.value) {
        const matched = displaySeries.value.find((item) => item.nama_prodi === newVal);
        if (matched) {
            selectedProdiName.value = matched.nama_prodi;
            forceShowTable.value = true;
        }
    }
});

// -----------------------------------------------------------------------------
// KARTU RINGKASAN ALAMI (Daily Summary Ala Apple iOS Weather)
// Berganti secara cerdas menyesuaikan mode grafik yang aktif:
// 1. Mode 100% Stacked Bar: Fokus pada Komposisi Responden (Sudah vs Belum Mengisi)
// 2. Mode Ranking & Target: Fokus pada Evaluasi Capaian terhadap Ambang Standar Dinamis
// -----------------------------------------------------------------------------
const naturalSummary = computed(() => {
    const sum = props.chart?.summary || {};
    const pop = Number(sum.population || 0);
    const resp = Number(sum.respondents || 0);
    const unans = Number(sum.unanswered ?? Math.max(pop - resp, 0));
    const rate = Number(sum.response_rate || 0);
    const unansRate = pop > 0 ? Number(((unans / pop) * 100).toFixed(1)) : 0;
    const year = activeYear.value;

    const list = [...displaySeries.value].sort((a, b) => b.response_rate - a.response_rate);
    const highest = list[0];
    const lowest = list[list.length - 1];
    const totalProdi = list.length;

    // --- ANALISIS MODE 1: 100% STACKED BAR (DISTRIBUSI KOMPOSISI) ---
    if (activeChartTab.value === 'stacked') {
        if (selectedProdiItem.value) {
            const p = selectedProdiItem.value;
            return {
                title: `Kilas Komposisi · ${p.nama_prodi}`,
                statusBadge: `${formatNumber(p.response_rate, 1)}% Responden`,
                badgeClass: 'bg-white text-[#0D542B] border border-[#0D542B]/30',
                lead: `Dari populasi ${formatNumber(p.population, 0)} alumni ${p.nama_prodi} kohort ${year}, tercatat ${formatNumber(p.respondents, 0)} alumni (${formatNumber(p.response_rate, 1)}%) telah mengisi kuesioner dan ${formatNumber(p.unanswered, 0)} alumni (${formatNumber(p.unanswered_rate, 1)}%) belum mengisi.`,
                detail: `Proporsi alumni yang belum mengisi kuesioner pada program studi ini sebesar ${formatNumber(p.unanswered_rate, 1)}%. Gunakan daftar responden di bawah untuk menghubungi alumni yang belum berpartisipasi.`,
            };
        }

        return {
            title: `Kilas Komposisi Partisipasi Kohort ${year} · Distribusi Komposisi`,
            statusBadge: 'Distribusi Komposisi',
            badgeClass: 'bg-white text-slate-700 border border-slate-300',
            lead: `Dari total ${formatNumber(pop, 0)} alumni kohort ${year}, tercatat ${formatNumber(resp, 0)} alumni (${formatNumber(rate, 1)}%) telah berpartisipasi mengisi kuesioner, sementara ${formatNumber(unans, 0)} alumni (${formatNumber(unansRate, 1)}%) masih berstatus belum mengisi.`,
            detail: highest && lowest && highest.nama_prodi !== lowest.nama_prodi
                ? `Komposisi responden tertinggi diraih oleh ${highest.nama_prodi} (${formatNumber(highest.response_rate, 1)}%), sedangkan proporsi belum mengisi terbanyak berada pada ${lowest.nama_prodi} (${formatNumber(lowest.unanswered_rate, 1)}% / ${formatNumber(lowest.unanswered, 0)} alumni). Klik segmen batang untuk menyaring data.`
                : 'Klik segmen batang untuk melihat komposisi detail masing-masing program studi.',
        };
    }

    // Ringkasan mengikuti program studi yang dipilih atau seluruh universitas.
    if (selectedProdiItem.value) {
        const p = selectedProdiItem.value;
        const rankIndex = list.findIndex((item) => item.nama_prodi === p.nama_prodi) + 1;
        return {
            title: `Kilas Ranking · ${p.nama_prodi}`,
            statusBadge: `${formatNumber(p.response_rate, 1)}% Response Rate`,
            badgeClass: 'bg-white text-[#0D542B] border border-[#0D542B]/30',
            lead: `Program studi ${p.nama_prodi} menempati peringkat ${rankIndex} dari ${totalProdi} program studi dengan response rate ${formatNumber(p.response_rate, 1)}%.`,
            detail: `Sebanyak ${formatNumber(p.respondents, 0)} dari ${formatNumber(p.population, 0)} alumni tercatat sebagai responden valid.`,
        };
    }

    return {
        title: `Kilas Ranking Response Rate · ${year}`,
        statusBadge: `${formatNumber(rate, 1)}% Response Rate`,
        badgeClass: 'bg-white text-[#0D542B] border border-[#0D542B]/30',
        lead: `Response rate universitas tercatat ${formatNumber(rate, 1)}% atau ${formatNumber(resp, 0)} dari ${formatNumber(pop, 0)} alumni.`,
        detail: highest && lowest && highest.nama_prodi !== lowest.nama_prodi
            ? `Capaian tertinggi berada pada ${highest.nama_prodi} (${formatNumber(highest.response_rate, 1)}%; ${formatNumber(highest.respondents, 0)}/${formatNumber(highest.population, 0)} alumni), sementara ${lowest.nama_prodi} berada pada peringkat terbawah (${formatNumber(lowest.response_rate, 1)}%; ${formatNumber(lowest.respondents, 0)}/${formatNumber(lowest.population, 0)} alumni).`
            : 'Klik batang untuk melihat rincian alumni pada program studi yang dipilih.',
    };
});

// -----------------------------------------------------------------------------
// 1. GRAFIK 100% STACKED HORIZONTAL BAR (Sudah vs Belum per Program Studi)
// Palet warna harmonis: Hijau Institusi UKDW (#0D542B) & Neutral Soft Slate (#94A3B8)
// -----------------------------------------------------------------------------
const stackedBarOption = computed(() => {
    const list = [...displaySeries.value].sort((a, b) => {
        return sortAsc.value ? a.response_rate - b.response_rate : b.response_rate - a.response_rate;
    });
    const prodiNames = list.map((item) => item.nama_prodi);

    return {
        ...baseOption(),
        themePalette: responseRatePalette,
        legend: {
            data: ['Belum Mengisi', 'Sudah Mengisi'],
            top: 6,
            textStyle: { color: '#334155', fontWeight: 600, fontSize: 12 },
        },
        grid: { left: 16, right: 36, top: 44, bottom: 20, containLabel: true },
        xAxis: {
            type: 'value',
            min: 0,
            max: 100,
            axisLabel: { formatter: '{value}%', color: '#64748B', fontSize: 11 },
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
        },
        yAxis: {
            type: 'category',
            data: prodiNames,
            inverse: true,
            axisLabel: { color: '#1E293B', fontWeight: 500, width: 170, overflow: 'truncate', fontSize: 12 },
            axisTick: { show: false },
            axisLine: { lineStyle: { color: '#CBD5E1' } },
        },
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            formatter: (params) => {
                const prodi = params[0]?.name;
                const item = list.find((s) => s.nama_prodi === prodi);
                if (!item) return '';
                return `
                    <div style="font-family: inherit; font-size: 12px; line-height: 1.5;">
                        <div style="font-weight: 700; border-bottom: 1px solid #E2E8F0; padding-bottom: 4px; margin-bottom: 6px; color: #0F172A;">${escapeHtml(prodi)}</div>
                        <div style="display: flex; justify-content: space-between; gap: 16px; color: #475569;"><span>Populasi Kohort:</span><strong>${formatNumber(item.population, 0)} alumni</strong></div>
                        <div style="display: flex; justify-content: space-between; gap: 16px; color: #64748B; margin-top: 2px;"><span>Belum Mengisi:</span><strong>${formatNumber(item.unanswered, 0)} alumni (${formatNumber(item.unanswered_rate, 1)}%)</strong></div>
                        <div style="display: flex; justify-content: space-between; gap: 16px; color: #0D542B; margin-top: 2px;"><span>Sudah Mengisi:</span><strong>${formatNumber(item.respondents, 0)} alumni (${formatNumber(item.response_rate, 1)}%)</strong></div>
                    </div>
                `;
            },
        },
        series: [
            {
                name: 'Belum Mengisi',
                type: 'bar',
                stack: 'total',
                stackOrder: 0,
                barMaxWidth: 26,
                itemStyle: {
                    color: responseRatePalette[1],
                    borderRadius: (params) => {
                        const row = list[params.dataIndex];
                        return (row?.response_rate === 0) ? [3, 3, 3, 3] : [3, 0, 0, 3];
                    },
                    opacity: (params) => {
                        if (!selectedProdiName.value) return 1;
                        if (params.name === selectedProdiName.value) {
                            return selectedStatus.value === 'sudah' ? 0.35 : 1;
                        }
                        return 0.22;
                    },
                },
                label: {
                    show: true,
                    position: 'inside',
                    formatter: ({ value }) => (value >= 10 ? `${formatNumber(value, 1)}%` : ''),
                    color: '#0F172A',
                    fontWeight: 600,
                    fontSize: 10,
                },
                data: list.map((item) => ({
                    name: item.nama_prodi,
                    value: item.unanswered_rate,
                    kode_prodi: item.kode_prodi,
                    series_status: 'belum',
                })),
            },
            {
                name: 'Sudah Mengisi',
                type: 'bar',
                stack: 'total',
                stackOrder: 1,
                barMaxWidth: 26,
                itemStyle: {
                    color: '#0D542B',
                    borderRadius: (params) => {
                        const row = list[params.dataIndex];
                        return (row?.unanswered_rate === 0) ? [3, 3, 3, 3] : [0, 3, 3, 0];
                    },
                    opacity: (params) => {
                        if (!selectedProdiName.value) return 1;
                        if (params.name === selectedProdiName.value) {
                            return selectedStatus.value === 'belum' ? 0.35 : 1;
                        }
                        return 0.22;
                    },
                },
                label: {
                    show: true,
                    position: 'inside',
                    formatter: ({ value }) => (value >= 10 ? `${formatNumber(value, 1)}%` : ''),
                    color: '#FFFFFF',
                    fontWeight: 600,
                    fontSize: 10,
                },
                data: list.map((item) => ({
                    name: item.nama_prodi,
                    value: item.response_rate,
                    kode_prodi: item.kode_prodi,
                    series_status: 'sudah',
                })),
            },
        ],
    };
});

// -----------------------------------------------------------------------------
// 2. GRAFIK RANKING RESPONSE RATE & CAPAIAN TERHADAP TARGET STANDAR DINAMIS
// Garis target menggunakan slate tegas (#334155), batang di bawah target slate (#64748B)
// -----------------------------------------------------------------------------
const rankingChartOption = computed(() => {
    const list = [...displaySeries.value].sort((a, b) => {
        return sortAsc.value ? a.response_rate - b.response_rate : b.response_rate - a.response_rate;
    });
    const prodiNames = list.map((item) => item.nama_prodi);

    return {
        ...baseOption(),
        themePalette: responseRatePalette,
        grid: { left: 16, right: 48, top: 24, bottom: 20, containLabel: true },
        xAxis: {
            type: 'value',
            min: 0,
            max: 100,
            axisLabel: { formatter: '{value}%', color: '#64748B', fontSize: 11 },
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
        },
        yAxis: {
            type: 'category',
            data: prodiNames,
            inverse: true,
            axisLabel: { color: '#1E293B', fontWeight: 500, width: 170, overflow: 'truncate', fontSize: 12 },
            axisTick: { show: false },
            axisLine: { lineStyle: { color: '#CBD5E1' } },
        },
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            formatter: (params) => {
                const prodi = params[0]?.name;
                const item = list.find((s) => s.nama_prodi === prodi);
                if (!item) return '';
                const gapSign = item.target_gap >= 0 ? '+' : '';
                return `
                    <div style="font-family: inherit; font-size: 12px; line-height: 1.5;">
                        <div style="font-weight: 700; border-bottom: 1px solid #E2E8F0; padding-bottom: 4px; margin-bottom: 6px; color: #0F172A;">${escapeHtml(prodi)}</div>
                        <div style="display: flex; justify-content: space-between; gap: 16px;"><span>Response Rate:</span><strong>${formatNumber(item.response_rate, 1)}%</strong></div>
                        <div style="display: flex; justify-content: space-between; gap: 16px; color: #475569;"><span>Responden (N):</span><strong>${formatNumber(item.respondents, 0)} dari ${formatNumber(item.population, 0)}</strong></div>
                        <div style="display: flex; justify-content: space-between; gap: 16px; color: #7A5B00;"><span>Target Standar:</span><strong>${formatNumber(item.target, 1)}%</strong></div>
                        <div style="display: flex; justify-content: space-between; gap: 16px; font-weight: 700; color: ${item.target_gap >= 0 ? '#0D542B' : '#7A5B00'};">
                            <span>Selisih Target:</span><strong>${gapSign}${formatNumber(item.target_gap, 1)}%</strong>
                        </div>
                    </div>
                `;
            },
        },
        series: [
            {
                name: 'Response Rate (%)',
                type: 'bar',
                barMaxWidth: 26,
                itemStyle: {
                    borderRadius: [0, 4, 4, 0],
                    color: (params) => {
                        const row = list[params.dataIndex];
                        return participationColor(row);
                    },
                    opacity: (params) => {
                        if (!selectedProdiName.value) return 1;
                        return params.name === selectedProdiName.value ? 1 : 0.28;
                    },
                },
                label: {
                    show: true,
                    position: 'right',
                    color: '#1E293B',
                    fontWeight: 600,
                    fontSize: 11,
                    formatter: ({ value, data }) => `${formatNumber(value, 1)}% · N ${formatNumber(data?.respondents, 0)}/${formatNumber(data?.population, 0)}`,
                },
                markLine: {
                    symbol: 'none',
                    lineStyle: { color: '#334155', type: 'dashed', width: 1.5 }, // Slate berwibawa menggantikan merah bentrok
                    label: {
                        formatter: `Target ${formatNumber(currentTarget.value, 1)}%`,
                        position: 'insideEndTop',
                        color: '#334155',
                        fontWeight: 'bold',
                        fontSize: 10,
                    },
                    data: [{ xAxis: currentTarget.value }],
                },
                data: list.map((item) => ({
                    name: item.nama_prodi,
                    value: item.response_rate,
                    kode_prodi: item.kode_prodi,
                    respondents: item.respondents,
                    population: item.population,
                    series_status: 'all',
                    itemStyle: {
                    color: participationColor(item),
                    },
                })),
            },
        ],
    };
});

// Status dan warna partisipasi berdasarkan ambang capaian absolut:
// Hijau : rate >= 50% -> Capaian baik
// Kuning: rate >= 30% && rate < 50% -> Perlu ditingkatkan
// Merah : rate < 30% -> Perlu perhatian dan tindak lanjut
const getParticipationStatus = (rate) => {
    if (rate && typeof rate === 'object' && rate.status_label) {
        return {
            key: rate.status_key,
            label: rate.status_label,
            meaning: rate.status_meaning,
            color: rate.status_color,
            badgeClass: rate.status_key === 'met'
                ? 'bg-emerald-50 text-[#0D542B] border-emerald-300'
                : (rate.status_key === 'warning' ? 'bg-amber-50 text-amber-700 border-amber-300' : 'bg-red-50 text-red-700 border-red-300'),
        };
    }

    const numRate = Number(rate || 0);

    if (numRate >= 50) {
        return {
            key: 'met',
            label: 'Capaian Baik',
            meaning: '50% atau lebih',
            color: '#0D542B', // Hijau Institusi UKDW
            badgeClass: 'bg-emerald-50 text-[#0D542B] border-emerald-300',
        };
    }
    if (numRate >= 30) {
        return {
            key: 'warning',
            label: 'Perlu Ditingkatkan',
            meaning: '30% hingga kurang dari 50%',
            color: '#D97706', // Kuning/Amber
            badgeClass: 'bg-amber-50 text-amber-700 border-amber-300',
        };
    }
    return {
        key: 'danger',
        label: 'Perlu Perhatian',
        meaning: 'Kurang dari 30%',
        color: '#DC2626', // Merah
        badgeClass: 'bg-red-50 text-red-700 border-red-300',
    };
};

const participationColor = (rate) => {
    return getParticipationStatus(rate).color;
};

// Grafik tunggal: panjang batang adalah total alumni, warna menunjukkan status pengisian.
const participationBarOption = computed(() => {
    const list = [...displaySeries.value].sort((a, b) => {
        return sortAsc.value ? a.response_rate - b.response_rate : b.response_rate - a.response_rate;
    });
    const maxPopulation = Math.max(...list.map((item) => Number(item.population) || 0), 10);

    return {
        ...baseOption(),
        grid: { left: 18, right: 36, top: 20, bottom: 48, containLabel: true },
        xAxis: {
            type: 'value',
            min: 0,
            max: Math.ceil(maxPopulation * 1.05),
            name: 'Jumlah alumni (orang)',
            nameLocation: 'middle',
            nameGap: 30,
            axisLabel: { color: '#64748B', fontSize: 11 },
            splitLine: { lineStyle: { color: '#E2E8F0' } },
        },
        yAxis: {
            type: 'category',
            data: list.map((item) => item.nama_prodi),
            inverse: true,
            axisLabel: {
                color: (val) => selectedProdiName.value === val ? '#0D542B' : '#1E293B',
                fontWeight: (val) => selectedProdiName.value === val ? 800 : 500,
                width: 170,
                overflow: 'truncate',
                fontSize: 12,
            },
            axisTick: { show: false },
            axisLine: { lineStyle: { color: '#CBD5E1' } },
        },
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            formatter: (params) => {
                const prodi = params[0]?.name;
                const item = list.find((entry) => entry.nama_prodi === prodi);
                if (!item) return '';

                const status = getParticipationStatus(item);
                const targetVal = Number(item.target || currentTarget.value);
                return `
                    <div style="font-family: inherit; font-size: 12px; line-height: 1.5;">
                        <div style="font-weight: 700; border-bottom: 1px solid #E2E8F0; padding-bottom: 4px; margin-bottom: 6px; color: #0F172A;">${escapeHtml(prodi)}</div>
                        <div style="display: flex; justify-content: space-between; gap: 16px;"><span>Partisipasi:</span><strong>${formatNumber(item.response_rate, 1)}%</strong></div>
                        <div style="display: flex; justify-content: space-between; gap: 16px; margin: 2px 0;"><span>Status Capaian:</span><strong style="color: ${status.color};">${escapeHtml(status.label)}</strong></div>
                        <div style="font-size: 11px; color: #64748B; margin-bottom: 4px;">(${escapeHtml(status.meaning)})</div>
                        <div style="display: flex; justify-content: space-between; gap: 16px; color: #64748B;"><span>Belum mengisi:</span><strong>${formatNumber(item.unanswered, 0)} alumni</strong></div>
                        <div style="display: flex; justify-content: space-between; gap: 16px; color: ${status.color};"><span>Sudah mengisi:</span><strong>${formatNumber(item.respondents, 0)} alumni</strong></div>
                        <div style="display: flex; justify-content: space-between; gap: 16px; color: #475569;"><span>N:</span><strong>${formatNumber(item.respondents, 0)}/${formatNumber(item.population, 0)}</strong></div>
                        <div style="display: flex; justify-content: space-between; gap: 16px; color: #64748B; font-size: 11px; margin-top: 4px; border-top: 1px dashed #E2E8F0; padding-top: 4px;"><span>Target:</span><span>${formatNumber(targetVal, 0)}% (Batas warna: 30% / 50%)</span></div>
                    </div>
                `;
            },
        },
        series: [
            {
                name: 'Belum Mengisi',
                type: 'bar',
                stack: 'population',
                stackOrder: 0,
                barMaxWidth: 28,
                itemStyle: {
                    color: '#CBD5E1',
                    borderRadius: (params) => {
                        const row = list[params.dataIndex];
                        return (row?.respondents === 0) ? [3, 3, 3, 3] : [3, 0, 0, 3];
                    },
                    opacity: (params) => !selectedProdiName.value || params.name === selectedProdiName.value ? 1 : 0.28,
                },
                label: {
                    show: true,
                    position: 'inside',
                    color: '#0F172A',
                    fontWeight: 700,
                    fontSize: 10,
                    formatter: ({ data }) => {
                        if (!data || !data.value || data.value <= 0) return '';
                        const unans = Number(data.value);
                        const pop = Number(data.population || 0);
                        const rate = pop > 0 ? formatNumber((unans / pop) * 100, 1) : '0';
                        if (pop > 0 && (unans / pop) >= 0.25) {
                            return `${rate}% (${unans})`;
                        }
                        return `${unans}`;
                    },
                },
                data: list.map((item) => ({
                    name: item.nama_prodi,
                    value: item.unanswered,
                    respondents: item.respondents,
                    population: item.population,
                    kode_prodi: item.kode_prodi,
                    series_status: 'belum',
                    itemStyle: {
                        color: '#CBD5E1',
                        borderRadius: (item.respondents === 0) ? [3, 3, 3, 3] : [3, 0, 0, 3],
                        opacity: !selectedProdiName.value || item.nama_prodi === selectedProdiName.value ? 1 : 0.22,
                        borderWidth: selectedProdiName.value === item.nama_prodi ? 2 : 0,
                        borderColor: '#475569',
                    },
                })),
            },
            {
                name: 'Sudah Mengisi',
                type: 'bar',
                stack: 'population',
                stackOrder: 1,
                barMaxWidth: 28,
                itemStyle: {
                    borderRadius: (params) => {
                        const row = list[params.dataIndex];
                        return (row?.unanswered === 0) ? [3, 3, 3, 3] : [0, 3, 3, 0];
                    },
                    color: (params) => {
                        const row = list[params.dataIndex];
                        return participationColor(row);
                    },
                    opacity: (params) => !selectedProdiName.value || params.name === selectedProdiName.value ? 1 : 0.28,
                },
                label: {
                    show: true,
                    position: 'inside',
                    color: '#FFFFFF',
                    fontWeight: 700,
                    fontSize: 11,
                    formatter: ({ data }) => {
                        if (!data) return '';
                        const rate = formatNumber(data.response_rate, 1);
                        const resp = formatNumber(data.respondents, 0);
                        const pop = formatNumber(data.population, 0);
                        return `${rate}% (${resp}/${pop})`;
                    },
                },
                data: list.map((item) => ({
                    name: item.nama_prodi,
                    value: item.respondents,
                    response_rate: item.response_rate,
                    target: item.target,
                    respondents: item.respondents,
                    population: item.population,
                    kode_prodi: item.kode_prodi,
                    series_status: 'sudah',
                    itemStyle: {
                        color: participationColor(item),
                        borderRadius: (item.unanswered === 0) ? [3, 3, 3, 3] : [0, 3, 3, 0],
                        opacity: !selectedProdiName.value || item.nama_prodi === selectedProdiName.value ? 1 : 0.22,
                        borderWidth: selectedProdiName.value === item.nama_prodi ? 2 : 0,
                        borderColor: '#0D542B',
                    },
                })),
            },
        ],
    };
});

// Menangani interaksi klik pada segmen batang grafik.
const handleChartClick = (params) => {
    const prodi = params.name || params.data?.name;
    if (!prodi) return;

    const seriesStatus = params.data?.series_status || (params.seriesName === 'Belum Mengisi' ? 'belum' : (params.seriesName === 'Sudah Mengisi' ? 'sudah' : 'all'));

    // Klik ulang segmen yang persis sama mereset seleksi
    if (selectedProdiName.value === prodi && selectedStatus.value === seriesStatus) {
        resetSelection();
        return;
    }

    selectedProdiName.value = prodi;
    selectedStatus.value = seriesStatus;
    forceShowTable.value = true;
    emit('select', { label: prodi, status: seriesStatus, kode_prodi: params.data?.kode_prodi });
};

// Reset seleksi kembali ke keadaan awal.
const resetSelection = () => {
    selectedProdiName.value = '';
    selectedStatus.value = 'all';
    searchQuery.value = '';
    currentPage.value = 1;
    emit('reset');
};

// -----------------------------------------------------------------------------
// LOGIKA DATA TABLE INLINE ("Don't Make Me Think" - Tanpa Pindah Tab)
// -----------------------------------------------------------------------------
const recordsInScope = computed(() => {
    if (!selectedProdiName.value) return records.value;
    return records.value.filter((r) => r.nama_prodi === selectedProdiName.value);
});

// Hitungan statistik responden untuk segmented button status tabel inline.
const countAllScope = computed(() => recordsInScope.value.length);
const countSudahScope = computed(() => recordsInScope.value.filter((r) => Boolean(r.answered) || r.status === 'Sudah Mengisi').length);
const countBelumScope = computed(() => recordsInScope.value.filter((r) => !r.answered && r.status !== 'Sudah Mengisi').length);

// Penyaringan daftar record berdasarkan status aktif dan kata kunci pencarian.
const filteredInlineRecords = computed(() => {
    let list = recordsInScope.value;

    if (selectedStatus.value === 'sudah') {
        list = list.filter((r) => Boolean(r.answered) || r.status === 'Sudah Mengisi');
    } else if (selectedStatus.value === 'belum') {
        list = list.filter((r) => !r.answered && r.status !== 'Sudah Mengisi');
    }

    if (searchQuery.value.trim()) {
        const query = searchQuery.value.trim().toLowerCase();
        list = list.filter((r) => {
            const nameMatch = String(r.nama || '').toLowerCase().includes(query);
            const nimMatch = String(r.nim || '').toLowerCase().includes(query);
            const prodiMatch = String(r.nama_prodi || '').toLowerCase().includes(query);
            return nameMatch || nimMatch || prodiMatch;
        });
    }

    return list;
});

// Total halaman paginasi tabel drill-down inline.
const totalPages = computed(() => {
    return Math.max(1, Math.ceil(filteredInlineRecords.value.length / rowsPerPage.value));
});

// Rekaman data yang tampil pada halaman aktif tabel inline.
const paginatedInlineRecords = computed(() => {
    const start = (currentPage.value - 1) * rowsPerPage.value;
    return filteredInlineRecords.value.slice(start, start + rowsPerPage.value);
});

// Apakah tabel drill-down inline ditampilkan (default selalu tampil agar data responden tidak hilang)
const isTableVisible = ref(true);

// Memformat nomor telepon alumni untuk tautan WhatsApp resmi.
const formatWhatsAppUrl = (record) => {
    const raw = String(record.nomor_telepon || '').replace(/\D/g, '');
    if (!raw) return '';
    const formatted = raw.startsWith('0') ? '62' + raw.slice(1) : (raw.startsWith('62') ? raw : '62' + raw);
    const text = encodeURIComponent(`Halo ${record.nama || 'Alumni'}, kami dari Biro Alumni Tracer Study UKDW mengingatkan untuk mengisi kuesioner tracer study. Mohon kesediaannya untuk berpartisipasi. Terima kasih.`);
    return `https://wa.me/${formatted}?text=${text}`;
};

// Memformat tautan email pengingat alumni.
const formatEmailUrl = (record) => {
    if (!record.email) return '';
    const subject = encodeURIComponent('Tracer Study UKDW');
    const body = encodeURIComponent(`Halo ${record.nama || 'Alumni'},\n\nKami dari Biro Alumni Tracer Study UKDW memohon kesediaan Anda untuk melengkapi kuesioner Tracer Study.\n\nTerima kasih.`);
    return `mailto:${record.email}?subject=${subject}&body=${body}`;
};

// Prefix rute berbasis konteks peran pengguna aktif di browser
const routePrefix = computed(() => {
    if (typeof window !== 'undefined') {
        const path = window.location.pathname;
        if (path.startsWith('/prodi')) return 'prodi';
        if (path.startsWith('/fakultas')) return 'fakultas';
        if (path.startsWith('/biro3')) return 'biro3';
    }
    return 'superadmin';
});

// Mendapatkan URL detail alumni dan kuesioner.
const detailUrl = (record) => {
    const detailPrefix = routePrefix.value === 'fakultas'
        ? 'fakultas/analitik'
        : ['prodi', 'biro3'].includes(routePrefix.value)
            ? `${routePrefix.value}/analitik`
        : routePrefix.value;

    return `/${detailPrefix}/alumni/${record.biodata_id || record.user_id}`;
};

// Unduh file Excel dari endpoint server-side resmi sesuai filter aktif.
const downloadExcel = (statusTarget = null) => {
    const statusParam = statusTarget || selectedStatus.value || 'all';
    const prodiIdParam = selectedProdiItem.value?.kode_prodi || '';
    // Gunakan tahun aktif dari filter; jika kosong, endpoint menafsirkan ALL.
    const yearParam = activeYear.value || 'ALL';

    const url = `/${routePrefix.value}/analitik/export-response-rate?tahun=${encodeURIComponent(yearParam)}&kode_prodi=${encodeURIComponent(prodiIdParam)}&status=${encodeURIComponent(statusParam)}`;
    window.location.href = url;
};
</script>

<template>
    <div class="space-y-4">
        <!-- Kartu Container Grafik ECharts Interaktif Terpadu -->
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs">
            <!-- Header grafik tunggal dengan informasi target yang selalu terlihat -->
            <div class="flex flex-col justify-between gap-4 border-b border-slate-100 pb-4 sm:flex-row sm:items-center">
                <div class="min-h-[46px]">
                    <div class="flex items-center gap-2">
                        <span class="rounded bg-white px-2 py-0.5 text-xs font-bold text-[#0D542B] border border-[#0D542B]/25">Indikator 01</span>
                        <h3 class="text-base font-extrabold text-slate-900">
                            Partisipasi Tracer Study per Program Studi
                        </h3>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        Panjang batang menunjukkan total alumni. Warna batang menunjukkan capaian terhadap target partisipasi, dan abu-abu menunjukkan alumni yang belum mengisi.
                    </p>
                </div>

                <!-- Bilah Aksi Terpadu: Grafik | Data | Unduh Grafik | Laporan PDF -->
                <div class="flex flex-wrap items-center gap-1 rounded-lg bg-slate-100 p-1 border border-slate-200 shrink-0 self-start sm:self-auto no-print text-xs">
                    <button
                        type="button"
                        class="rounded-md px-3 py-1.5 font-bold transition-all cursor-pointer whitespace-nowrap"
                        :class="activeViewMode === 'chart' ? 'bg-white text-slate-900 shadow-xs border border-slate-200/80' : 'text-slate-600 hover:text-slate-900'"
                        @click="activeViewMode = 'chart'"
                    >
                        Grafik
                    </button>
                    <button
                        type="button"
                        class="rounded-md px-3 py-1.5 font-bold transition-all cursor-pointer whitespace-nowrap"
                        :class="activeViewMode === 'table' ? 'bg-white text-slate-900 shadow-xs border border-slate-200/80' : 'text-slate-600 hover:text-slate-900'"
                        @click="activeViewMode = 'table'"
                    >
                        Data
                    </button>
                    <span class="mx-0.5 h-3.5 w-px bg-slate-300"></span>
                    <button
                        type="button"
                        class="rounded-md px-3 py-1.5 font-medium text-slate-700 hover:bg-white hover:text-[#0D542B] transition-all cursor-pointer whitespace-nowrap"
                        @click="handleDownloadChart"
                        title="Unduh gambar grafik saat ini (PNG)"
                    >
                        Unduh Grafik
                    </button>
                    <span class="mx-0.5 h-3.5 w-px bg-slate-300"></span>
                    <button
                        type="button"
                        class="rounded-md px-3 py-1.5 font-medium text-slate-700 hover:bg-white hover:text-[#0D542B] transition-all cursor-pointer whitespace-nowrap"
                        @click="handleDownloadPdf"
                        title="Unduh laporan analisis lengkap kategori ini (PDF)"
                    >
                        Laporan PDF
                    </button>
                </div>
            </div>

            <!-- TAMPILAN 1: MODE DATA RINGKASAN TABULASI -->
            <div v-if="activeViewMode === 'table'" class="mt-4 space-y-4">
                <div class="overflow-x-auto rounded-lg border border-slate-200">
                    <table class="min-w-full text-left text-xs divide-y divide-slate-200">
                        <thead class="bg-slate-50 text-slate-700 font-semibold">
                            <tr>
                                <th class="px-3.5 py-2.5 text-center w-12">No</th>
                                <th class="px-3.5 py-2.5">Program Studi</th>
                                <th class="px-3.5 py-2.5 text-right">Populasi (N)</th>
                                <th class="px-3.5 py-2.5 text-right">Sudah Mengisi</th>
                                <th class="px-3.5 py-2.5 text-right">Belum Mengisi</th>
                                <th class="px-3.5 py-2.5 text-right">Response Rate</th>
                                <th class="px-3.5 py-2.5 text-right">Target</th>
                                <th class="px-3.5 py-2.5 text-right">Gap Target</th>
                                <th class="px-3.5 py-2.5 text-center">Status Capaian</th>
                                <th class="px-3.5 py-2.5 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr
                                v-for="(item, idx) in [...displaySeries].sort((a, b) => sortAsc ? a.response_rate - b.response_rate : b.response_rate - a.response_rate)"
                                :key="item.kode_prodi || idx"
                                class="hover:bg-slate-50/70 transition-colors"
                                :class="selectedProdiName === item.nama_prodi ? 'bg-emerald-50/40 font-semibold' : ''"
                            >
                                <td class="px-3.5 py-2 text-center text-slate-400 font-mono">{{ idx + 1 }}</td>
                                <td class="px-3.5 py-2 font-medium text-slate-900 whitespace-nowrap">{{ item.nama_prodi }}</td>
                                <td class="px-3.5 py-2 text-right font-mono text-slate-700">{{ formatNumber(item.population, 0) }}</td>
                                <td class="px-3.5 py-2 text-right font-mono font-bold text-[#0D542B]">{{ formatNumber(item.respondents, 0) }}</td>
                                <td class="px-3.5 py-2 text-right font-mono text-slate-600">{{ formatNumber(item.unanswered, 0) }}</td>
                                <td class="px-3.5 py-2 text-right font-mono font-bold text-slate-900">{{ formatNumber(item.response_rate, 1) }}%</td>
                                <td class="px-3.5 py-2 text-right font-mono text-slate-500">{{ formatNumber(item.target, 0) }}%</td>
                                <td class="px-3.5 py-2 text-right font-mono" :class="item.target_gap >= 0 ? 'text-[#0D542B] font-semibold' : 'text-amber-700 font-semibold'">
                                    {{ item.target_gap >= 0 ? '+' : '' }}{{ formatNumber(item.target_gap, 1) }}%
                                </td>
                                <td class="px-3.5 py-2 text-center whitespace-nowrap">
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold border"
                                        :class="getParticipationStatus(item).badgeClass"
                                    >
                                        {{ getParticipationStatus(item).label }}
                                    </span>
                                </td>
                                <td class="px-3.5 py-2 text-center whitespace-nowrap">
                                    <button
                                        type="button"
                                        class="rounded border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-50 hover:text-[#0D542B] cursor-pointer transition-colors"
                                        @click="selectedProdiName = item.nama_prodi; activeViewMode = 'chart'; forceShowTable = true"
                                    >
                                        Lihat Responden
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAMPILAN 2: MODE GRAFIK VISUAL -->
            <div v-else>
                <!-- Toolbar Kontrol: Toggle Urutan & Aksi Unduh Cepat -->
                <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 font-semibold text-slate-700 hover:bg-slate-100 cursor-pointer"
                        @click="sortAsc = !sortAsc"
                    >
                        <span>Urutan:</span>
                        <span class="font-bold text-[#0D542B]">{{ sortAsc ? 'Terendah → Tertinggi' : 'Tertinggi → Terendah' }}</span>
                    </button>

                    <div class="flex items-center gap-2">
                        <button
                            v-if="selectedProdiName"
                            type="button"
                            class="rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-100 cursor-pointer transition-colors"
                            @click="resetSelection"
                        >
                            Kembali ke Analisis Keseluruhan
                        </button>
                    </div>
                </div>

                <!-- Legenda Keterangan Warna Capaian Target -->
                <div class="mt-3 flex flex-wrap items-center gap-x-3.5 gap-y-2 rounded-lg bg-slate-50 border border-slate-200 px-3.5 py-2 text-xs text-slate-700">
                    <span class="font-bold text-slate-900 pr-3 border-r border-slate-300">Target Universitas: {{ formatNumber(currentTarget, 0) }}%</span>
                    <div class="flex items-center gap-1.5">
                        <span class="inline-block h-3 w-3 rounded-xs bg-[#CBD5E1]"></span>
                        <span><strong>Abu-abu:</strong> Belum Mengisi</span>
                    </div>
                    <span class="text-slate-300 hidden sm:inline">|</span>
                    <div class="flex items-center gap-1.5">
                        <span class="inline-block h-3 w-3 rounded-xs bg-[#0D542B]"></span>
                        <span><strong>Hijau:</strong> &ge; 50% (Baik)</span>
                    </div>
                    <span class="text-slate-300 hidden sm:inline">|</span>
                    <div class="flex items-center gap-1.5">
                        <span class="inline-block h-3 w-3 rounded-xs bg-[#D97706]"></span>
                        <span><strong>Kuning:</strong> 30%–&lt;50% (Perlu Ditingkatkan)</span>
                    </div>
                    <span class="text-slate-300 hidden sm:inline">|</span>
                    <div class="flex items-center gap-1.5">
                        <span class="inline-block h-3 w-3 rounded-xs bg-[#DC2626]"></span>
                        <span><strong>Merah:</strong> &lt; 30% (Perlu Perhatian)</span>
                    </div>
                </div>

                <!-- Banner Informasi Status Fokus Grafik (Inline Tepat di Atas Visual) -->
                <div
                    v-if="selectedProdiName"
                    class="mt-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-xl border border-[#0D542B]/30 bg-emerald-50/50 px-4 py-3 text-slate-800 shadow-2xs animate-in fade-in duration-200"
                >
                    <div class="flex items-center gap-2.5 min-w-0">
                        <span class="h-2.5 w-2.5 rounded-full bg-[#0D542B] shrink-0 animate-pulse"></span>
                        <div class="min-w-0">
                            <span class="text-xs text-slate-500 font-medium block">Analisis Sedang Terfokus:</span>
                            <span class="text-sm font-extrabold text-[#0D542B] truncate block">
                                Program Studi {{ selectedProdiName }}
                            </span>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="shrink-0 inline-flex items-center gap-1.5 rounded-lg border border-[#0D542B]/40 bg-white px-3 py-1.5 text-xs font-bold text-[#0D542B] hover:bg-[#0D542B] hover:text-white transition-all cursor-pointer shadow-2xs"
                        @click="resetSelection"
                    >
                        <span>&times;</span>
                        <span>Kembali ke Analisis Keseluruhan</span>
                    </button>
                </div>

                <!-- Area Render Grafik ECharts Responsif -->
                <div class="mt-4">
                    <!-- Grafik tunggal response rate dengan target dinamis -->
                    <div class="space-y-4">
                        <AnalyticsEChart
                            ref="echartRef"
                            :option="participationBarOption"
                            :height="Math.max(400, displaySeries.length * 36 + 70)"
                            :loading="loading"
                            aria-label="Grafik partisipasi tracer study per program studi"
                            @select="handleChartClick"
                        />
                        <p class="px-1 text-xs leading-relaxed text-slate-500">
                            <span class="font-semibold text-slate-700">Caption:</span>
                            Partisipasi alumni per program studi. Segmen abu-abu di sisi kiri menunjukkan alumni yang belum mengisi kuesioner (beserta jumlahnya), sedangkan segmen berwarna di sisi kanan menunjukkan alumni yang sudah mengisi (persentase dan rasio responden terhadap total alumni).
                        </p>

                        <!-- Kotak ringkasan partisipasi -->
                        <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/60 pb-2">
                                <span class="text-xs font-bold uppercase tracking-wider text-slate-700">
                                    {{ naturalSummary.title }}
                                </span>
                                <div class="flex items-center gap-2">
                                    <span class="rounded px-2 py-0.5 text-[11px] font-bold" :class="naturalSummary.badgeClass">
                                        {{ naturalSummary.statusBadge }}
                                    </span>
                                    <button
                                        v-if="selectedProdiName"
                                        type="button"
                                        class="rounded border border-slate-200 bg-white px-2.5 py-0.5 text-[11px] font-bold text-[#0D542B] hover:bg-emerald-50 cursor-pointer transition-colors"
                                        @click="resetSelection"
                                    >
                                        Kembali ke Analisis Keseluruhan
                                    </button>
                                </div>
                            </div>
                            <p class="mt-2.5 text-xs sm:text-sm font-semibold text-slate-900 leading-relaxed">
                                {{ naturalSummary.lead }}
                            </p>
                            <p class="mt-1 text-xs text-slate-600 leading-relaxed">
                                {{ naturalSummary.detail }}
                            </p>
                            <p class="mt-2 border-t border-slate-200/70 pt-2 text-xs text-slate-500 leading-relaxed">
                                <span class="font-semibold text-[#0D542B]">Alasan pemilihan ({{ activeChartType }}):</span>
                                {{ activeChartReason }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ------------------------------------------------------------------- -->
        <!-- DATA TABLE DRILL-DOWN INLINE ("Don't Make Me Think")               -->
        <!-- Tampil langsung saat batang grafik diklik, tanpa perlu pindah tab   -->
        <!-- ------------------------------------------------------------------- -->
        <div
            v-if="isTableVisible"
            class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs transition-all"
        >
            <!-- Header Panel Data Drill-Down -->
            <div class="flex flex-col justify-between gap-4 border-b border-slate-100 pb-4 lg:flex-row lg:items-center">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="rounded bg-white px-2 py-0.5 text-xs font-bold text-[#0D542B] border border-[#0D542B]/25">
                            Data Responden
                        </span>
                        <h4 class="text-base font-extrabold text-slate-900">
                            {{ selectedProdiName ? `Rincian Program Studi: ${selectedProdiName}` : `Seluruh Alumni Kohort ${activeYear}` }}
                        </h4>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        Menampilkan {{ formatNumber(filteredInlineRecords.length, 0) }} alumni sesuai filter aktif.
                    </p>
                </div>

                <!-- Segmented Control Status Pengisian -->
                <div class="flex flex-wrap items-center gap-2">
                    <div class="flex rounded-lg bg-slate-100 p-1 text-xs">
                        <button
                            type="button"
                            class="rounded-md px-3 py-1 font-bold transition-all cursor-pointer"
                            :class="selectedStatus === 'all' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                            @click="selectedStatus = 'all'"
                        >
                            Semua ({{ countAllScope }})
                        </button>
                        <button
                            type="button"
                            class="rounded-md px-3 py-1 font-bold transition-all cursor-pointer"
                            :class="selectedStatus === 'sudah' ? 'bg-[#0D542B] text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                            @click="selectedStatus = 'sudah'"
                        >
                            Sudah Mengisi ({{ countSudahScope }})
                        </button>
                        <button
                            type="button"
                            class="rounded-md px-3 py-1 font-bold transition-all cursor-pointer"
                            :class="selectedStatus === 'belum' ? 'bg-[#D6A928] text-[#0F172A] shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                            @click="selectedStatus = 'belum'"
                        >
                            Belum Mengisi ({{ countBelumScope }})
                        </button>
                    </div>

                    <!-- Tombol Reset Prodi / Sembunyikan -->
                    <button
                        v-if="selectedProdiName"
                        type="button"
                        class="rounded-lg bg-white border border-slate-200 px-3 py-1.5 text-xs font-bold text-[#0D542B] hover:bg-emerald-50 cursor-pointer transition-colors"
                        @click="resetSelection"
                    >
                        Tampilkan Seluruh Program Studi
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-200 cursor-pointer transition-colors"
                        @click="isTableVisible = !isTableVisible"
                    >
                        {{ isTableVisible ? 'Sembunyikan Tabel' : 'Tampilkan Tabel' }}
                    </button>
                </div>
            </div>

            <!-- Toolbar Pencarian Cepat -->
            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="w-full max-w-sm">
                    <input
                        id="inline-search-alumni"
                        name="inline_search_alumni"
                        v-model="searchQuery"
                        type="search"
                        class="block w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-900 outline-none focus:border-[#0D542B] focus:bg-white focus:ring-1 focus:ring-[#0D542B]"
                        placeholder="Cari nama atau NIM alumni..."
                    />
                </div>
                <div class="text-xs text-slate-500">
                    {{ formatNumber(filteredInlineRecords.length, 0) }} total data
                </div>
            </div>

            <!-- Tabel Data Responden -->
            <div class="mt-3 overflow-x-auto rounded-lg border border-slate-200">
                <table class="min-w-full text-left text-xs divide-y divide-slate-200">
                    <thead>
                        <tr class="bg-slate-50 text-slate-700 font-semibold">
                            <th class="px-3 py-2.5">NIM</th>
                            <th class="px-3 py-2.5">Nama Alumni</th>
                            <th v-if="!selectedProdiName" class="px-3 py-2.5">Program Studi</th>
                            <th class="min-w-[240px] px-3 py-2.5">Perusahaan &amp; Lokasi</th>
                            <th class="min-w-[220px] px-3 py-2.5">Pekerjaan &amp; Posisi</th>
                            <th class="min-w-[220px] px-3 py-2.5">Skills</th>
                            <th class="px-3 py-2.5 text-center">Status Kuesioner</th>
                            <th class="px-3 py-2.5 text-center">Waktu Respons</th>
                            <th class="px-3 py-2.5 text-center w-28">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr
                            v-for="(record, index) in paginatedInlineRecords"
                            :key="record.biodata_id || record.nim || index"
                            class="hover:bg-slate-50/70 transition-colors"
                        >
                            <td class="px-3 py-2 font-mono text-slate-700 whitespace-nowrap">
                                {{ record.nim }}
                            </td>
                            <td class="px-3 py-2 font-semibold text-slate-800 whitespace-nowrap">
                                {{ record.nama }}
                            </td>
                            <td v-if="!selectedProdiName" class="px-3 py-2 text-slate-600 whitespace-nowrap">
                                {{ record.nama_prodi }}
                            </td>
                            <td class="px-3 py-2 align-top text-slate-700">
                                <div class="space-y-1 leading-relaxed">
                                    <p><span class="font-semibold text-slate-500">Perusahaan:</span> {{ record.nama_perusahaan || '-' }}</p>
                                    <p><span class="font-semibold text-slate-500">Head office:</span> {{ record.head_office_lokasi || '-' }}</p>
                                    <p><span class="font-semibold text-slate-500">Lokasi saat ini:</span> {{ record.domisili_saat_ini || '-' }}</p>
                                </div>
                            </td>
                            <td class="px-3 py-2 align-top text-slate-700">
                                <div class="space-y-1 leading-relaxed">
                                    <p><span class="font-semibold text-slate-500">Bidang:</span> {{ record.bidang_pekerjaan || '-' }}</p>
                                    <p><span class="font-semibold text-slate-500">Unit:</span> {{ record.divisi || '-' }}</p>
                                    <p><span class="font-semibold text-slate-500">Posisi:</span> {{ record.posisi_jabatan || '-' }}</p>
                                </div>
                            </td>
                            <td class="max-w-xs whitespace-normal px-3 py-2 align-top leading-relaxed text-slate-700">
                                {{ record.skills || '-' }}
                            </td>
                            <td class="px-3 py-2 text-center whitespace-nowrap">
                                <span
                                    v-if="record.answered || record.status === 'Sudah Mengisi'"
                                    class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200"
                                >
                                    Sudah Mengisi
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 text-slate-700 border border-slate-200"
                                >
                                    Belum Mengisi
                                </span>
                            </td>
                            <td class="px-3 py-2 text-center text-slate-500 whitespace-nowrap font-mono text-[11px]">
                                {{ record.last_response_at || '-' }}
                            </td>
                            <td class="px-3 py-2 text-center whitespace-nowrap">
                                <div class="inline-flex items-center justify-center gap-1">
                                    <!-- Detail Profil -->
                                    <Link
                                        :href="detailUrl(record)"
                                        class="p-1 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded transition-colors"
                                        title="Lihat Detail Alumni"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </Link>

                                    <!-- WhatsApp Pengingat -->
                                    <a
                                        v-if="canContactRecord(record) && record.nomor_telepon"
                                        :href="formatWhatsAppUrl(record)"
                                        target="_blank"
                                        class="p-1 text-slate-600 hover:text-emerald-700 hover:bg-slate-100 rounded transition-colors"
                                        title="Hubungi via WhatsApp"
                                    >
                                        <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24">
                                            <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z" />
                                        </svg>
                                    </a>
                                    <button
                                        v-else
                                        type="button"
                                        disabled
                                        class="cursor-not-allowed rounded p-1 text-slate-300"
                                        title="Nomor WhatsApp tidak tersedia"
                                        aria-label="Nomor WhatsApp tidak tersedia"
                                    >
                                        <svg class="h-4 w-4 fill-current" viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z" />
                                        </svg>
                                    </button>

                                    <!-- Email Pengingat -->
                                    <a
                                        v-if="canContactRecord(record) && record.email"
                                        :href="formatEmailUrl(record)"
                                        class="p-1 text-slate-600 hover:text-sky-700 hover:bg-slate-100 rounded transition-colors"
                                        title="Kirim Email Pengingat"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                    </a>
                                    <button
                                        v-else
                                        type="button"
                                        disabled
                                        class="cursor-not-allowed rounded p-1 text-slate-300"
                                        title="Email tidak tersedia"
                                        aria-label="Email tidak tersedia"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!paginatedInlineRecords.length">
                            <td :colspan="selectedProdiName ? 9 : 10" class="px-3 py-8 text-center text-slate-500">
                                Tidak ada data alumni yang cocok dengan pencarian atau status terpilih.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination tabel responden mengikuti kontrol bersama. -->
            <PaginationControls
                :total="filteredInlineRecords.length"
                :current-page="currentPage"
                :page-size="rowsPerPage"
                summary-label="data alumni"
                @update:current-page="currentPage = $event"
            />
        </div>

        <!-- Panel saat tabel disembunyikan pengguna -->
        <div v-else class="rounded-xl border border-dashed border-slate-300 bg-white p-4 text-center">
            <button
                type="button"
                class="rounded-lg bg-[#0D542B] px-4 py-2 text-xs font-bold text-white hover:bg-[#0A4322] cursor-pointer transition-colors"
                @click="isTableVisible = true"
            >
                Tampilkan Rincian Data Responden ({{ formatNumber(filteredInlineRecords.length, 0) }} Alumni)
            </button>
        </div>

    </div>
</template>
