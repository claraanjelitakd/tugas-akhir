<script setup>
/**
 * Komponen Indikator 01: Response Rate Universitas dan Program Studi.
 *
 * Menyajikan visualisasi analitik interaktif 2-tone (Emerald Green & Amber Gold):
 * 1. Visualisasi 1: 100% Stacked Horizontal Bar Chart (Sudah vs Belum per program studi)
 * 2. Visualisasi 2: Ranking Response Rate & Capaian terhadap Target Standar 60%
 *
 * Data detail individu, pencarian, aksi WhatsApp/Email, dan ekspor disajikan
 * secara profesional pada Tab Data utama.
 */
import { computed, ref } from 'vue';
import AnalyticsEChart from '../AnalyticsEChart.vue';
import {
    baseOption,
    escapeHtml,
    formatNumber,
} from '../analyticsChartOptions';

// Kontrak properti data indikator, filter aktif, dan status pemuatan data.
const props = defineProps({
    chart: { type: Object, default: () => ({}) },
    selectedLabel: { type: String, default: '' },
    loading: { type: Boolean, default: false },
});

// Event seleksi data diteruskan ke panel induk untuk cross-filtering.
const emit = defineEmits(['select', 'reset', 'show-data']);

// Tab visualisasi aktif: 'stacked' (100% Stacked Bar) atau 'ranking' (Target 60%).
const activeChartTab = ref('stacked');

// State seleksi program studi dan status pengisian.
const selectedProdiName = ref('');
const selectedStatus = ref('all'); // 'all', 'sudah', atau 'belum'

// Pengurutan capaian pada grafik.
const sortAsc = ref(false);

// Ekstraksi data seri program studi dan catatan alumni dari payload.
const series = computed(() => props.chart?.series || []);
const records = computed(() => props.chart?.records || []);
const activeYear = computed(() => records.value[0]?.tahun_lulus || String(new Date().getFullYear()));

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
        const criteria = item.criteria || (rate >= (target + 10) ? 'Sangat Baik' : (rate >= target ? 'Memenuhi Target' : 'Perlu Peningkatan'));

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
            criteria,
        };
    });
});

// Data program studi yang sedang dipilih secara aktif.
const selectedProdiItem = computed(() => {
    if (!selectedProdiName.value) return null;
    return displaySeries.value.find((item) => item.nama_prodi === selectedProdiName.value) || null;
});

// Hitungan jumlah alumni sesuai prodi dan status aktif.
const selectedCount = computed(() => {
    let list = records.value;
    if (selectedProdiName.value) {
        list = list.filter((r) => r.nama_prodi === selectedProdiName.value);
    }
    if (selectedStatus.value === 'sudah') {
        list = list.filter((r) => Boolean(r.answered));
    } else if (selectedStatus.value === 'belum') {
        list = list.filter((r) => !r.answered);
    }
    return list.length;
});

// -----------------------------------------------------------------------------
// 1. GRAFIK 100% STACKED HORIZONTAL BAR (Sudah vs Belum per Program Studi)
// -----------------------------------------------------------------------------
const stackedBarOption = computed(() => {
    const list = [...displaySeries.value].sort((a, b) => {
        return sortAsc.value ? a.response_rate - b.response_rate : b.response_rate - a.response_rate;
    });
    const prodiNames = list.map((item) => item.nama_prodi);

    return {
        ...baseOption(),
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
                        <div style="display: flex; justify-content: space-between; gap: 16px; color: #D97706; margin-top: 2px;"><span>Belum Mengisi:</span><strong>${formatNumber(item.unanswered, 0)} alumni (${formatNumber(item.unanswered_rate, 1)}%)</strong></div>
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
                    formatter: ({ value }) => (value >= 12 ? `${formatNumber(value, 1)}%` : ''),
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
                    color: '#F59E0B',
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
                    formatter: ({ value }) => (value >= 12 ? `${formatNumber(value, 1)}%` : ''),
                    color: '#1E293B',
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
// 2. GRAFIK RANKING RESPONSE RATE & CAPAIAN TERHADAP TARGET STANDAR 60%
// -----------------------------------------------------------------------------
const rankingChartOption = computed(() => {
    const list = [...displaySeries.value].sort((a, b) => {
        return sortAsc.value ? a.response_rate - b.response_rate : b.response_rate - a.response_rate;
    });
    const prodiNames = list.map((item) => item.nama_prodi);

    return {
        ...baseOption(),
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
                        <div style="display: flex; justify-content: space-between; gap: 16px; color: #64748B;"><span>Target Standar:</span><strong>${formatNumber(item.target, 1)}%</strong></div>
                        <div style="display: flex; justify-content: space-between; gap: 16px; font-weight: 700; color: ${item.target_gap >= 0 ? '#0D542B' : '#D97706'};">
                            <span>Selisih Target:</span><strong>${gapSign}${formatNumber(item.target_gap, 1)}%</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; gap: 16px; margin-top: 4px; border-top: 1px solid #E2E8F0; padding-top: 4px;">
                            <span>Kriteria:</span><strong>${escapeHtml(item.criteria)}</strong>
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
                        if (val >= currentTarget.value) return '#0D542B'; // Memenuhi target (Brand Dark Green)
                        return '#F59E0B'; // Belum mencapai target (Amber Gold)
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
                    lineStyle: { color: '#B91C1C', type: 'dashed', width: 2 },
                    label: {
                        formatter: `Target ${formatNumber(currentTarget.value, 1)}%`,
                        position: 'insideEndTop',
                        color: '#B91C1C',
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
                })),
            },
        ],
    };
});

// Menangani interaksi klik pada segmen grafik.
const handleChartClick = (params) => {
    const prodi = params.name || params.data?.name;
    if (!prodi) return;

    const seriesStatus = params.data?.series_status || (params.seriesName === 'Belum Mengisi' ? 'belum' : 'sudah');

    // Klik ulang segmen yang sama mereset pilihan
    if (selectedProdiName.value === prodi && selectedStatus.value === seriesStatus) {
        resetSelection();
        return;
    }

    selectedProdiName.value = prodi;
    selectedStatus.value = seriesStatus;
    emit('select', { label: prodi, status: seriesStatus, prodi_id: params.data?.prodi_id });
};

// Reset seleksi kembali ke keadaan awal.
const resetSelection = () => {
    selectedProdiName.value = '';
    selectedStatus.value = 'all';
    emit('reset');
};

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
        <!-- Card Container Grafik Responsif 2-Tone -->
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs">
            <!-- Header Kartu & Pilihan 2 Visualisasi Utama -->
            <div class="flex flex-col justify-between gap-4 border-b border-slate-100 pb-4 sm:flex-row sm:items-center">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="rounded bg-emerald-50 px-2 py-0.5 text-xs font-bold text-[#0D542B]">Indikator 01</span>
                        <h3 class="text-base font-extrabold text-slate-900">
                            {{ activeChartTab === 'stacked' ? 'Distribusi Komposisi Responden (100% Stacked)' : 'Ranking Response Rate & Kriteria Capaian' }}
                        </h3>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ activeChartTab === 'stacked' ? 'Klik segmen hijau (Sudah Mengisi) atau kuning (Belum Mengisi) pada batang untuk menyaring data.' : `Garis putus-putus merah menandakan ambang batas target standar ${formatNumber(currentTarget, 1)}%.` }}
                    </p>
                </div>

                <!-- Switcher 2 Grafik Utama Sesuai Permintaan Pengguna -->
                <div class="flex items-center gap-1 rounded-lg bg-slate-100 p-1">
                    <button
                        type="button"
                        class="rounded-md px-3.5 py-1.5 text-xs font-bold transition-all cursor-pointer"
                        :class="activeChartTab === 'stacked' ? 'bg-[#0D542B] text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                        @click="activeChartTab = 'stacked'"
                    >
                        100% Stacked Bar
                    </button>
                    <button
                        type="button"
                        class="rounded-md px-3.5 py-1.5 text-xs font-bold transition-all cursor-pointer"
                        :class="activeChartTab === 'ranking' ? 'bg-[#0D542B] text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                        @click="activeChartTab = 'ranking'"
                    >
                        Ranking & Target {{ formatNumber(currentTarget, 1) }}%
                    </button>
                </div>
            </div>

            <!-- Toolbar Kontrol: Toggle Pengurutan & Indikator Seleksi -->
            <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs">
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 font-semibold text-slate-700 hover:bg-slate-100 cursor-pointer"
                        @click="sortAsc = !sortAsc"
                    >
                        <span>Urutan:</span>
                        <span class="font-bold text-[#0D542B]">{{ sortAsc ? 'Terendah → Tertinggi' : 'Tertinggi → Terendah' }}</span>
                    </button>
                </div>

                <!-- Tombol Cepat Unduh Excel -->
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 font-bold text-slate-700 hover:bg-slate-50 cursor-pointer transition-colors"
                        title="Unduh seluruh data dalam 3 sheet: Ringkasan, Sudah Mengisi, Belum Mengisi"
                        @click="downloadExcel('all')"
                    >
                        Download Excel 3 Sheet
                    </button>
                </div>
            </div>

            <!-- Bilah Status Seleksi Aktif (Jika batang/segmen diklik) -->
            <div
                v-if="selectedProdiName || selectedStatus !== 'all'"
                class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-emerald-200 bg-emerald-50/70 p-3 text-xs"
            >
                <div class="flex items-center gap-2">
                    <span class="font-bold text-slate-600">Pilihan Aktif:</span>
                    <span class="font-extrabold text-[#0D542B] text-sm">{{ selectedProdiName || 'Seluruh Program Studi' }}</span>
                    <span>·</span>
                    <span
                        class="rounded px-2 py-0.5 font-bold"
                        :class="selectedStatus === 'sudah' ? 'bg-emerald-200 text-emerald-900' : (selectedStatus === 'belum' ? 'bg-amber-200 text-amber-900' : 'bg-slate-200 text-slate-800')"
                    >
                        {{ selectedStatus === 'sudah' ? 'Sudah Mengisi' : (selectedStatus === 'belum' ? 'Belum Mengisi' : 'Semua Status') }}
                    </span>
                    <span class="text-slate-500">({{ selectedCount }} alumni)</span>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="rounded-md bg-[#0D542B] px-3 py-1 font-bold text-white hover:bg-[#0A4322] cursor-pointer"
                        @click="emit('show-data')"
                    >
                        Lihat Data di Tab Data →
                    </button>
                    <button
                        type="button"
                        class="rounded-md bg-white border border-slate-300 px-2.5 py-1 font-bold text-slate-700 hover:bg-slate-100 cursor-pointer"
                        @click="downloadExcel(selectedStatus)"
                    >
                        Download Excel ({{ selectedCount }})
                    </button>
                    <button
                        type="button"
                        class="rounded-md bg-amber-100 px-2 py-1 font-bold text-amber-900 hover:bg-amber-200 cursor-pointer"
                        @click="resetSelection"
                    >
                        Reset
                    </button>
                </div>
            </div>

            <!-- Area Render Grafik ECharts -->
            <div class="mt-4">
                <!-- Visualisasi 1: 100% Stacked Horizontal Bar -->
                <div v-show="activeChartTab === 'stacked'">
                    <AnalyticsEChart
                        :option="stackedBarOption"
                        :height="Math.max(400, displaySeries.length * 36 + 70)"
                        :loading="loading"
                        aria-label="Grafik 100% Stacked Bar Response Rate"
                        @select="handleChartClick"
                    />
                </div>

                <!-- Visualisasi 2: Ranking Response Rate & Target Dinamis -->
                <div v-show="activeChartTab === 'ranking'">
                    <AnalyticsEChart
                        :option="rankingChartOption"
                        :height="Math.max(400, displaySeries.length * 36 + 70)"
                        :loading="loading"
                        :aria-label="`Grafik Ranking Response Rate dan Target ${formatNumber(currentTarget, 1)}%`"
                        @select="handleChartClick"
                    />
                </div>
            </div>

        </div>
    </div>
</template>
