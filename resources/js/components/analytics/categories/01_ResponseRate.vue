<script setup>
/**
 * Komponen Indikator 01: Response Rate Universitas dan Program Studi.
 *
 * Mengimplementasikan visualisasi analitik terpadu (Unified Visual + Inline Data Drill-Down):
 * 1. Visualisasi 1: 100% Stacked Horizontal Bar Chart (Sudah Mengisi vs Belum Mengisi)
 *    dengan warna harmonis: Hijau Institusi UKDW (#0D542B) & Neutral Slate (#94A3B8).
 * 2. Visualisasi 2: Ranking Response Rate & Capaian terhadap Target Standar Dinamis
 *    dengan penanda garis target slate berwibawa (#334155).
 * 3. Kartu Kilas Indikator Alami: Narasi ringkas dan manusiawi yang terinspirasi dari
 *    Daily Summary Apple iOS Weather (bebas dari AI slop).
 * 4. Inline Data Table Drill-Down ("Don't Make Me Think"): Saat segmen grafik diklik,
 *    daftar data responden langsung muncul secara mulus di bawah grafik lengkap
 *    dengan filter status, pencarian, aksi kontak (WhatsApp/Email), dan unduh Excel.
 */
import { computed, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import AnalyticsEChart from '../AnalyticsEChart.vue';
import {
    baseOption,
    escapeHtml,
    formatNumber,
    responseRatePalette,
} from '../analyticsChartOptions';

// Kontrak properti data indikator, filter aktif, dan status pemuatan data.
const props = defineProps({
    chart: { type: Object, default: () => ({}) },
    selectedYear: { type: String, default: '' },
    selectedLabel: { type: String, default: '' },
    loading: { type: Boolean, default: false },
});

// Event seleksi data diteruskan ke panel induk untuk cross-filtering.
const emit = defineEmits(['select', 'reset', 'show-data']);

// Tab visualisasi aktif: 'stacked' (100% Stacked Bar) atau 'ranking' (Target Dinamis).
const activeChartTab = ref('stacked');

// State seleksi program studi dan status pengisian.
const selectedProdiName = ref('');
const selectedStatus = ref('all'); // 'all', 'sudah', atau 'belum'

// Pengurutan capaian pada grafik.
const sortAsc = ref(false);

// State kontrol tabel drill-down inline di bawah grafik.
const searchQuery = ref('');
const currentPage = ref(1);
const rowsPerPage = ref(10);
const forceShowTable = ref(false);

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

// Seri yang dinormalisasi dengan data populasi, responden, dan belum mengisi.
const displaySeries = computed(() => {
    return series.value.map((item) => {
        const pop = Number(item.population || 0);
        const resp = Number(item.respondents || 0);
        const unans = Number(item.unanswered ?? Math.max(pop - resp, 0));
        const rate = pop > 0 ? Number(((resp / pop) * 100).toFixed(1)) : 0;
        const unansRate = pop > 0 ? Number(((unans / pop) * 100).toFixed(1)) : 0;
        const target = Number(item.target ?? currentTarget.value);
        const gap = Number(item.target_gap ?? Number((rate - target).toFixed(1)));
        return {
            prodi_id: item.prodi_id,
            nama_prodi: item.nama_prodi || 'Tanpa prodi',
            population: pop,
            respondents: resp,
            unanswered: unans,
            response_rate: rate,
            unanswered_rate: unansRate,
            target,
            target_gap: gap,
        };
    });
});

// Data program studi yang sedang dipilih secara aktif pada grafik.
const selectedProdiItem = computed(() => {
    if (!selectedProdiName.value) return null;
    return displaySeries.value.find((item) => item.nama_prodi === selectedProdiName.value) || null;
});

// Reset halaman paginasi tabel inline ketika prodi, status, atau pencarian berubah.
watch([selectedProdiName, selectedStatus, searchQuery], () => {
    currentPage.value = 1;
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
    const target = Number(sum.target ?? currentTarget.value);
    const gap = Number(sum.target_gap ?? Number((rate - target).toFixed(1)));
    const gapSign = gap >= 0 ? '+' : '';
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

    // --- ANALISIS MODE 2: RANKING & TARGET ---
    if (selectedProdiItem.value) {
        const p = selectedProdiItem.value;
        const pGapSign = p.target_gap >= 0 ? '+' : '';
        const rankIndex = list.findIndex((item) => item.nama_prodi === p.nama_prodi) + 1;
        return {
            title: `Kilas Ranking · ${p.nama_prodi}`,
            statusBadge: `${formatNumber(p.response_rate, 1)}% Response Rate`,
            badgeClass: 'bg-white text-[#0D542B] border border-[#0D542B]/30',
            lead: `Program studi ${p.nama_prodi} menempati peringkat ${rankIndex} dari ${totalProdi} program studi dengan response rate ${formatNumber(p.response_rate, 1)}% (${pGapSign}${formatNumber(p.target_gap, 1)}% terhadap target standar ${formatNumber(p.target, 1)}%).`,
            detail: `Sebanyak ${formatNumber(p.respondents, 0)} alumni telah tercatat sebagai responden valid. Selisih terhadap target standar adalah ${pGapSign}${formatNumber(p.target_gap, 1)}%.`,
        };
    }

    return {
        title: `Kilas Ranking Response Rate · ${year}`,
        statusBadge: `${formatNumber(rate, 1)}% Response Rate`,
        badgeClass: 'bg-white text-[#0D542B] border border-[#0D542B]/30',
        lead: `Response rate universitas tercatat ${formatNumber(rate, 1)}% dengan selisih ${gapSign}${formatNumber(gap, 1)}% terhadap target standar ${formatNumber(target, 1)}%.`,
        detail: highest && lowest && highest.nama_prodi !== lowest.nama_prodi
            ? `Peringkat pertama dipimpin oleh ${highest.nama_prodi} (${formatNumber(highest.response_rate, 1)}%), sementara ${lowest.nama_prodi} berada pada peringkat terbawah (${formatNumber(lowest.response_rate, 1)}%). Garis target menunjukkan ambang pembanding universitas.`
            : 'Garis target menunjukkan ambang pembanding universitas.',
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
            data: ['Sudah Mengisi', 'Belum Mengisi'],
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
                        <div style="display: flex; justify-content: space-between; gap: 16px; color: #0D542B; margin-top: 2px;"><span>Sudah Mengisi:</span><strong>${formatNumber(item.respondents, 0)} alumni (${formatNumber(item.response_rate, 1)}%)</strong></div>
                        <div style="display: flex; justify-content: space-between; gap: 16px; color: #7A5B00; margin-top: 2px;"><span>Belum Mengisi:</span><strong>${formatNumber(item.unanswered, 0)} alumni (${formatNumber(item.unanswered_rate, 1)}%)</strong></div>
                    </div>
                `;
            },
        },
        series: [
            {
                name: 'Sudah Mengisi',
                type: 'bar',
                stack: 'total',
                barMaxWidth: 26,
                itemStyle: {
                    color: '#0D542B',
                    borderRadius: [3, 0, 0, 3],
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
                    formatter: ({ value }) => (value > 0 ? `${formatNumber(value, 1)}%` : ''),
                    color: '#FFFFFF',
                    fontWeight: 600,
                    fontSize: 10,
                },
                data: list.map((item) => ({
                    name: item.nama_prodi,
                    value: item.response_rate,
                    prodi_id: item.prodi_id,
                    series_status: 'sudah',
                })),
            },
            {
                name: 'Belum Mengisi',
                type: 'bar',
                stack: 'total',
                barMaxWidth: 26,
                itemStyle: {
                    color: responseRatePalette[1],
                    borderRadius: [0, 3, 3, 0],
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
                    formatter: ({ value }) => (value > 0 ? `${formatNumber(value, 1)}%` : ''),
                    color: '#0F172A',
                    fontWeight: 600,
                    fontSize: 10,
                },
                data: list.map((item) => ({
                    name: item.nama_prodi,
                    value: item.unanswered_rate,
                    prodi_id: item.prodi_id,
                    series_status: 'belum',
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
                        const val = params.value;
                        if (val >= currentTarget.value) return '#0D542B'; // Memenuhi target (Brand Dark Green UKDW)
                        return responseRatePalette[1];
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
                    formatter: ({ value }) => `${formatNumber(value, 1)}%`,
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
                    prodi_id: item.prodi_id,
                    series_status: 'all',
                    itemStyle: {
                        color: item.response_rate >= currentTarget.value ? responseRatePalette[0] : responseRatePalette[1],
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
    emit('select', { label: prodi, status: seriesStatus, prodi_id: params.data?.prodi_id });
};

// Reset seleksi kembali ke keadaan awal.
const resetSelection = () => {
    selectedProdiName.value = '';
    selectedStatus.value = 'all';
    searchQuery.value = '';
    currentPage.value = 1;
    forceShowTable.value = false;
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

// Apakah tabel drill-down inline ditampilkan.
const isTableVisible = computed(() => {
    return Boolean(selectedProdiName.value) || forceShowTable.value;
});

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

// Mendapatkan URL detail alumni dan kuesioner.
const detailUrl = (record) => `/superadmin/alumni/${record.biodata_id || record.user_id}`;

// Unduh file Excel dari endpoint server-side resmi sesuai filter aktif.
const downloadExcel = (statusTarget = null) => {
    const statusParam = statusTarget || selectedStatus.value || 'all';
    const prodiIdParam = selectedProdiItem.value?.prodi_id || '';
    const yearParam = activeYear.value || '2026';

    const url = `/superadmin/analitik/export-response-rate?tahun=${encodeURIComponent(yearParam)}&prodi_id=${encodeURIComponent(prodiIdParam)}&status=${encodeURIComponent(statusParam)}`;
    window.location.href = url;
};
</script>

<template>
    <div class="space-y-4">
        <!-- Kartu Container Grafik ECharts Interaktif Terpadu -->
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs">
            <!-- Header Kartu & Segmented Switcher Grafik (Tata Letak Kokoh Tanpa Layout Shift) -->
            <div class="flex flex-col justify-between gap-4 border-b border-slate-100 pb-4 sm:flex-row sm:items-center">
                <div class="min-h-[46px]">
                    <div class="flex items-center gap-2">
                        <span class="rounded bg-white px-2 py-0.5 text-xs font-bold text-[#0D542B] border border-[#0D542B]/25">Indikator 01</span>
                        <h3 class="text-base font-extrabold text-slate-900">
                            {{ activeChartTab === 'stacked' ? 'Distribusi Komposisi Responden' : 'Ranking Response Rate dan Target' }}
                        </h3>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ activeChartTab === 'stacked' ? 'Klik segmen batang untuk langsung membuka data responden di bawah grafik.' : `Garis putus-putus abu-abu gelap menandai ambang target ${formatNumber(currentTarget, 1)}%.` }}
                    </p>
                </div>

                <!-- Switcher 2 Visualisasi Utama -->
                <div class="flex items-center gap-1 rounded-lg bg-slate-100 p-1 shrink-0">
                    <button
                        type="button"
                        class="rounded-md px-3.5 py-1.5 text-xs font-bold transition-all cursor-pointer whitespace-nowrap"
                        :class="activeChartTab === 'stacked' ? 'bg-[#0D542B] text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                        @click="activeChartTab = 'stacked'"
                    >
                        100% Stacked Bar
                    </button>
                    <button
                        type="button"
                        class="rounded-md px-3.5 py-1.5 text-xs font-bold transition-all cursor-pointer whitespace-nowrap"
                        :class="activeChartTab === 'ranking' ? 'bg-[#0D542B] text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                        @click="activeChartTab = 'ranking'"
                    >
                        Ranking & Target {{ formatNumber(currentTarget, 1) }}%
                    </button>
                </div>
            </div>

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
                        Tampilkan Seluruh Universitas
                    </button>
                </div>
            </div>

            <!-- Area Render Grafik ECharts Responsif -->
            <div class="mt-4">
                <!-- Visualisasi 1: 100% Stacked Horizontal Bar -->
                <div v-show="activeChartTab === 'stacked'" class="space-y-4">
                    <AnalyticsEChart
                        :option="stackedBarOption"
                        :height="Math.max(400, displaySeries.length * 36 + 70)"
                        :loading="loading"
                        aria-label="Grafik 100% Stacked Bar Response Rate"
                        @select="handleChartClick"
                    />
                    <p class="px-1 text-xs leading-relaxed text-slate-500">
                        <span class="font-semibold text-slate-700">Caption:</span>
                        Distribusi proporsi alumni yang sudah dan belum mengisi kuesioner pada setiap program studi.
                    </p>

                    <!-- Kotak Analisis Khusus Mode 100% Stacked Bar -->
                    <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-4">
                        <div class="flex items-center justify-between gap-2 border-b border-slate-200/60 pb-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-700">
                                {{ naturalSummary.title }}
                            </span>
                            <span class="rounded px-2 py-0.5 text-[11px] font-bold" :class="naturalSummary.badgeClass">
                                {{ naturalSummary.statusBadge }}
                            </span>
                        </div>
                        <p class="mt-2.5 text-xs sm:text-sm font-semibold text-slate-900 leading-relaxed">
                            {{ naturalSummary.lead }}
                        </p>
                        <p class="mt-1 text-xs text-slate-600 leading-relaxed">
                            {{ naturalSummary.detail }}
                        </p>
                        <p class="mt-2 border-t border-slate-200/70 pt-2 text-xs text-slate-500 leading-relaxed">
                            <span class="font-semibold text-[#0D542B]">Alasan pemilihan ({{ activeChartTab === 'stacked' ? '100% Stacked Bar Chart' : 'Bullet Chart' }}):</span>
                            {{ activeChartTab === 'stacked' ? 'Dipilih untuk memperlihatkan proporsi alumni yang sudah dan belum mengisi pada setiap program studi dalam total 100%.' : 'Dipilih untuk membandingkan response rate setiap program studi dengan target universitas melalui batang aktual dan penanda target.' }}
                        </p>
                    </div>
                </div>

                <!-- Visualisasi 2: Ranking Response Rate & Target Dinamis -->
                <div v-show="activeChartTab === 'ranking'" class="space-y-4">
                    <AnalyticsEChart
                        :option="rankingChartOption"
                        :height="Math.max(400, displaySeries.length * 36 + 70)"
                        :loading="loading"
                        :aria-label="`Grafik Ranking Response Rate dan Target ${formatNumber(currentTarget, 1)}%`"
                        @select="handleChartClick"
                    />
                    <p class="px-1 text-xs leading-relaxed text-slate-500">
                        <span class="font-semibold text-slate-700">Caption:</span>
                        Ranking response rate per program studi dengan garis target universitas sebagai pembanding.
                    </p>

                    <!-- Kotak Analisis Khusus Mode Ranking & Target -->
                    <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-4">
                        <div class="flex items-center justify-between gap-2 border-b border-slate-200/60 pb-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-700">
                                {{ naturalSummary.title }}
                            </span>
                            <span class="rounded px-2 py-0.5 text-[11px] font-bold" :class="naturalSummary.badgeClass">
                                {{ naturalSummary.statusBadge }}
                            </span>
                        </div>
                        <p class="mt-2.5 text-xs sm:text-sm font-semibold text-slate-900 leading-relaxed">
                            {{ naturalSummary.lead }}
                        </p>
                        <p class="mt-1 text-xs text-slate-600 leading-relaxed">
                            {{ naturalSummary.detail }}
                        </p>
                        <p class="mt-2 border-t border-slate-200/70 pt-2 text-xs text-slate-500 leading-relaxed">
                            <span class="font-semibold text-[#0D542B]">Alasan pemilihan (Bullet Chart):</span>
                            Dipilih untuk membandingkan response rate setiap program studi dengan target universitas melalui batang aktual dan penanda target.
                        </p>
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

                    <!-- Tombol Aksi Tutup Rincian -->
                    <button
                        type="button"
                        class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-200 cursor-pointer transition-colors"
                        @click="resetSelection"
                    >
                        Tutup Rincian
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
                    Halaman {{ currentPage }} dari {{ totalPages }} ({{ formatNumber(filteredInlineRecords.length, 0) }} total data)
                </div>
            </div>

            <!-- Tabel Data Responden -->
            <div class="mt-3 overflow-x-auto rounded-lg border border-slate-200">
                <table class="min-w-full text-left text-xs divide-y divide-slate-200">
                    <thead>
                        <tr class="bg-slate-50 text-slate-700 font-semibold">
                            <th class="px-3 py-2.5 text-center w-12">No</th>
                            <th class="px-3 py-2.5">NIM</th>
                            <th class="px-3 py-2.5">Nama Alumni</th>
                            <th v-if="!selectedProdiName" class="px-3 py-2.5">Program Studi</th>
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
                            <td class="px-3 py-2 text-center text-slate-400 font-mono">
                                {{ (currentPage - 1) * rowsPerPage + index + 1 }}
                            </td>
                            <td class="px-3 py-2 font-mono text-slate-700 whitespace-nowrap">
                                {{ record.nim }}
                            </td>
                            <td class="px-3 py-2 font-semibold text-slate-800 whitespace-nowrap">
                                {{ record.nama }}
                            </td>
                            <td v-if="!selectedProdiName" class="px-3 py-2 text-slate-600 whitespace-nowrap">
                                {{ record.nama_prodi }}
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

                                    <!-- WhatsApp Pengingat (Jika ada nomor telepon) -->
                                    <a
                                        v-if="record.nomor_telepon"
                                        :href="formatWhatsAppUrl(record)"
                                        target="_blank"
                                        class="p-1 text-slate-600 hover:text-emerald-700 hover:bg-slate-100 rounded transition-colors"
                                        title="Hubungi via WhatsApp"
                                    >
                                        <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24">
                                            <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z" />
                                        </svg>
                                    </a>

                                    <!-- Email Pengingat (Jika ada email) -->
                                    <a
                                        v-if="record.email"
                                        :href="formatEmailUrl(record)"
                                        class="p-1 text-slate-600 hover:text-sky-700 hover:bg-slate-100 rounded transition-colors"
                                        title="Kirim Email Pengingat"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!paginatedInlineRecords.length">
                            <td :colspan="selectedProdiName ? 6 : 7" class="px-3 py-8 text-center text-slate-500">
                                Tidak ada data alumni yang cocok dengan pencarian atau status terpilih.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Kontrol Paginasi Tabel Inline -->
            <div class="mt-3 flex items-center justify-between text-xs text-slate-600">
                <span>Halaman {{ currentPage }} dari {{ totalPages }}</span>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="rounded border border-slate-300 bg-white px-2.5 py-1 font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-40 cursor-pointer"
                        :disabled="currentPage <= 1"
                        @click="currentPage--"
                    >
                        Sebelumnya
                    </button>
                    <button
                        type="button"
                        class="rounded border border-slate-300 bg-white px-2.5 py-1 font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-40 cursor-pointer"
                        :disabled="currentPage >= totalPages"
                        @click="currentPage++"
                    >
                        Berikutnya
                    </button>
                </div>
            </div>
        </div>

    </div>
</template>
