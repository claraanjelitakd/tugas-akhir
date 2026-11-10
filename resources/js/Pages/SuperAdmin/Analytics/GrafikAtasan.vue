<script setup>
/**
 * Halaman grafik evaluasi pengguna lulusan dari atasan.
 * Memisahkan visualisasi per kategori indikator (Aspek Kinerja, Kesiapan Kerja,
 * dan Perbandingan Program Studi) serta memisahkan tab Grafik dan Data Responden
 * agar visualisasi leluasa dan mudah dibaca.
 */
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import Sidebar from '../Components/Sidebar.vue';
import AnalyticsEChart from '@/components/analytics/AnalyticsEChart.vue';
import PaginationControls from '@/components/PaginationControls.vue';

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

// Panel navigasi utama: 'charts' (Grafik & Analisis) atau 'records' (Data Responden).
const activePanel = ref('charts');

// Kategori grafik indikator aktif: 'kinerja' (Aspek Kinerja), 'kesiapan' (Kesiapan Kerja), 'prodi' (Perbandingan Prodi).
const selectedCategory = ref('kinerja');

// Aspek terpilih untuk melihat rincian breakdown distribusi frekuensi jawaban Likert.
const selectedAspectCode = ref('');

// State pencarian dan paginasi untuk tabel data responden.
const tableSearch = ref('');
const currentPage = ref(1);
const rowsPerPage = ref(20);

// Status buka-tutup kartu metodologi perhitungan.
const methodologyOpen = ref(false);

// Mengambil bagian payload yang dipakai bersama oleh kartu, grafik, dan tabel.
const filters = computed(() => currentAnalytics.value?.filters || {});
const summary = computed(() => currentAnalytics.value?.summary || {});
const questionStats = computed(() => currentAnalytics.value?.question_stats || []);
const readiness = computed(() => currentAnalytics.value?.readiness || {});
const prodiSummary = computed(() => currentAnalytics.value?.prodi_summary || []);
const records = computed(() => currentAnalytics.value?.records || []);

// Daftar butir pertanyaan berjenis Likert 5 poin yang memiliki jawaban valid.
const likertItems = computed(() => questionStats.value.filter((item) => item.type === 'likert_5' && item.valid_count > 0));

// Butir aspek yang sedang dipilih untuk breakdown distribusi jawaban.
const activeAspect = computed(() => {
    if (!selectedAspectCode.value) {
        return likertItems.value[0] || null;
    }
    return likertItems.value.find((item) => item.kode === selectedAspectCode.value) || likertItems.value[0] || null;
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

// Memformat angka tanpa mengubah nilai asli dari backend.
const formatNumber = (value, digits = 0) => Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: digits });

// Opsi ECharts untuk grafik rata-rata aspek kinerja alumni (full width, leluasa, dan terbaca jelas).
const scoreOption = computed(() => {
    const items = likertItems.value;
    const names = items.map((item) => item.aspek);
    const values = items.map((item) => item.average);
    const maxVal = 5;

    return {
        grid: { left: 24, right: 75, top: 25, bottom: 25, containLabel: true },
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            formatter: (params) => {
                const param = params?.[0];
                if (!param) return '-';
                const item = items.find((i) => i.aspek === param.name);
                const count = item ? item.valid_count : 0;
                return `<div class="font-bold text-slate-800">${param.name}</div>`
                    + `<div class="mt-1 text-xs text-slate-600">Rata-rata skor: <span class="font-bold text-[#0D542B]">${param.value ?? '-'} / 5</span></div>`
                    + `<div class="text-xs text-slate-500">Responden: ${formatNumber(count)} jawaban</div>`;
            },
        },
        xAxis: {
            type: 'value',
            min: 0,
            max: maxVal,
            interval: 1,
            name: 'Skor (1-5)',
            nameLocation: 'end',
            nameTextStyle: { color: '#64748B', fontSize: 11, fontWeight: 600 },
            axisLine: { show: true, lineStyle: { color: '#CBD5E1' } },
            axisLabel: { color: '#64748B', fontSize: 11 },
            splitLine: { show: true, lineStyle: { type: 'dashed', color: '#E2E8F0' } },
        },
        yAxis: {
            type: 'category',
            inverse: true,
            data: names,
            axisTick: { show: false },
            axisLine: { show: true, lineStyle: { color: '#CBD5E1' } },
            axisLabel: {
                color: '#1E293B',
                fontSize: 12,
                fontWeight: 500,
                width: 320,
                overflow: 'break',
                lineHeight: 16,
            },
        },
        series: [{
            type: 'bar',
            name: 'Rata-rata skor',
            data: values,
            barWidth: 20,
            showBackground: true,
            backgroundStyle: { color: '#F1F5F9', borderRadius: [0, 4, 4, 0] },
            itemStyle: { color: '#0D542B', borderRadius: [0, 4, 4, 0] },
            label: {
                show: true,
                position: 'right',
                formatter: (val) => `${val.value} / 5`,
                color: '#0D542B',
                fontWeight: 'bold',
                fontSize: 12,
                distance: 8,
            },
        }],
    };
});

// Opsi ECharts untuk distribusi jawaban butir aspek kinerja yang dipilih.
const aspectDetailOption = computed(() => {
    const item = activeAspect.value;
    if (!item) return {};

    const distribution = item.distribution || [];
    const labels = distribution.map((d) => d.label);
    const counts = distribution.map((d) => d.count);
    const total = counts.reduce((acc, c) => acc + c, 0);

    return {
        grid: { left: 24, right: 60, top: 25, bottom: 25, containLabel: true },
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            formatter: (params) => {
                const param = params?.[0];
                if (!param) return '-';
                const count = param.value ?? 0;
                const pct = total > 0 ? ((count / total) * 100).toFixed(1) : '0';
                return `<div class="font-bold text-slate-800">${param.name}</div>`
                    + `<div class="mt-1 text-xs text-slate-600">Jumlah: <span class="font-bold text-[#0D542B]">${formatNumber(count)}</span> (${pct}%)</div>`;
            },
        },
        xAxis: {
            type: 'value',
            minInterval: 1,
            name: 'Jumlah Respon',
            nameTextStyle: { color: '#64748B', fontSize: 11, fontWeight: 600 },
            axisLine: { show: true, lineStyle: { color: '#CBD5E1' } },
            splitLine: { show: true, lineStyle: { type: 'dashed', color: '#E2E8F0' } },
        },
        yAxis: {
            type: 'category',
            inverse: true,
            data: labels,
            axisTick: { show: false },
            axisLine: { show: true, lineStyle: { color: '#CBD5E1' } },
            axisLabel: { color: '#1E293B', fontSize: 12, fontWeight: 500 },
        },
        series: [{
            type: 'bar',
            data: counts,
            barWidth: 22,
            itemStyle: { color: '#2F7D4A', borderRadius: [0, 4, 4, 0] },
            label: {
                show: true,
                position: 'right',
                formatter: (val) => {
                    const count = val.value ?? 0;
                    const pct = total > 0 ? ((count / total) * 100).toFixed(1) : '0';
                    return `${count} (${pct}%)`;
                },
                color: '#2F7D4A',
                fontWeight: 'bold',
                fontSize: 11,
            },
        }],
    };
});

// Opsi ECharts untuk tingkat kesiapan kerja alumni.
const readinessOption = computed(() => {
    const distribution = readiness.value?.distribution || [];
    const total = distribution.reduce((sum, item) => sum + (item.count || 0), 0);
    const labels = distribution.map((item) => item.label);
    const counts = distribution.map((item) => item.count);

    return {
        grid: { left: 24, right: 24, top: 40, bottom: 40, containLabel: true },
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            formatter: (params) => {
                const param = params?.[0];
                if (!param) return '-';
                const count = param.value ?? 0;
                const pct = total > 0 ? ((count / total) * 100).toFixed(1) : '0';
                return `<div class="font-bold text-slate-800">${param.name}</div>`
                    + `<div class="mt-1 text-xs text-slate-600">Jawaban: <span class="font-bold text-[#0D542B]">${formatNumber(count)}</span> (${pct}%)</div>`;
            },
        },
        xAxis: {
            type: 'category',
            data: labels,
            axisTick: { show: false },
            axisLine: { show: true, lineStyle: { color: '#CBD5E1' } },
            axisLabel: { color: '#1E293B', fontSize: 12, fontWeight: 600 },
        },
        yAxis: {
            type: 'value',
            minInterval: 1,
            name: 'Jumlah Jawaban',
            nameTextStyle: { color: '#64748B', fontSize: 11, fontWeight: 600 },
            axisLine: { show: true, lineStyle: { color: '#CBD5E1' } },
            splitLine: { show: true, lineStyle: { type: 'dashed', color: '#E2E8F0' } },
        },
        series: [{
            type: 'bar',
            name: 'Jawaban',
            data: counts,
            barWidth: 44,
            itemStyle: { color: '#0D542B', borderRadius: [6, 6, 0, 0] },
            label: {
                show: true,
                position: 'top',
                formatter: (val) => {
                    const count = val.value ?? 0;
                    const pct = total > 0 ? ((count / total) * 100).toFixed(1) : '0';
                    return `${count} (${pct}%)`;
                },
                color: '#0D542B',
                fontWeight: 'bold',
                fontSize: 12,
            },
        }],
    };
});

// Opsi ECharts untuk perbandingan skor rata-rata antar program studi.
const prodiOption = computed(() => {
    const list = prodiSummary.value;
    const names = list.map((item) => item.prodi);
    const averages = list.map((item) => item.average);

    return {
        grid: { left: 24, right: 75, top: 25, bottom: 25, containLabel: true },
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            formatter: (params) => {
                const param = params?.[0];
                if (!param) return '-';
                const item = list.find((i) => i.prodi === param.name);
                const resp = item ? item.respondents : 0;
                return `<div class="font-bold text-slate-800">${param.name}</div>`
                    + `<div class="mt-1 text-xs text-slate-600">Rata-rata: <span class="font-bold text-[#0D542B]">${param.value ?? '-'} / 5</span></div>`
                    + `<div class="text-xs text-slate-500">Responden evaluasi: ${formatNumber(resp)}</div>`;
            },
        },
        xAxis: {
            type: 'value',
            min: 0,
            max: 5,
            interval: 1,
            name: 'Skor (1-5)',
            nameLocation: 'end',
            nameTextStyle: { color: '#64748B', fontSize: 11, fontWeight: 600 },
            axisLine: { show: true, lineStyle: { color: '#CBD5E1' } },
            splitLine: { show: true, lineStyle: { type: 'dashed', color: '#E2E8F0' } },
        },
        yAxis: {
            type: 'category',
            inverse: true,
            data: names,
            axisTick: { show: false },
            axisLine: { show: true, lineStyle: { color: '#CBD5E1' } },
            axisLabel: {
                color: '#1E293B',
                fontSize: 12,
                fontWeight: 500,
                width: 260,
                overflow: 'break',
                lineHeight: 16,
            },
        },
        series: [{
            type: 'bar',
            name: 'Rata-rata skor',
            data: averages,
            barWidth: 20,
            showBackground: true,
            backgroundStyle: { color: '#F1F5F9', borderRadius: [0, 4, 4, 0] },
            itemStyle: { color: '#2F7D4A', borderRadius: [0, 4, 4, 0] },
            label: {
                show: true,
                position: 'right',
                formatter: (val) => `${val.value} / 5`,
                color: '#2F7D4A',
                fontWeight: 'bold',
                fontSize: 12,
                distance: 8,
            },
        }],
    };
});

// Menyaring tabel data responden berdasarkan kata kunci pencarian.
const filteredRecords = computed(() => {
    const list = records.value;
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

// Mengarahkan detail alumni Fakultas ke endpoint baca-saja lintas fakultas.
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

// Membatalkan debounce saat halaman berpindah agar timer tidak mengubah state komponen yang sudah dilepas.
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
                        <Link :href="`${analyticsBaseUrl.value}/dashboard`" class="font-semibold hover:text-white">Dashboard</Link>
                        <span class="text-emerald-300/70">/</span>
                        <span class="rounded bg-white/10 px-2 py-0.5 font-bold text-white">Grafik Evaluasi Atasan</span>
                    </div>
                    <div class="mt-5 flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                        <div class="min-w-0">
                            <h1 class="mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl">Grafik Evaluasi Atasan</h1>
                            <p class="mt-2 max-w-3xl text-sm leading-6 text-emerald-100/90">Visualisasi komprehensif jawaban atasan dan pengguna lulusan mengenai kinerja, kompetensi, dan kesiapan alumni di tempat kerja.</p>
                        </div>
                        <p class="text-xs text-emerald-100/90">Sumber aktif: Kuesioner Evaluasi Pengguna Lulusan</p>
                    </div>
                </div>
            </header>

            <div class="mt-5 space-y-5 px-5 pb-8 sm:px-8 w-full">
                <!-- Filter bar standar -->
                <section class="bg-white p-5 shadow-lg shadow-slate-900/5">
                    <div class="grid gap-4 xl:grid-cols-[minmax(18rem,2fr)_minmax(15rem,1fr)_auto] xl:items-end">
                        <label class="block min-w-0 text-xs font-bold uppercase tracking-wide text-slate-500">
                            Program studi
                            <select v-model="selectedProdiId" class="mt-2 block w-full rounded bg-slate-100 px-3 py-3 text-sm font-bold normal-case text-slate-900 outline-none focus:bg-white focus:ring-2 focus:ring-[#0D542B]" @change="scheduleLoad">
                                <option value="">Semua program studi</option>
                                <option v-for="prodi in filters.prodis || []" :key="prodi.id" :value="String(prodi.id)">{{ prodi.nama_prodi }}</option>
                            </select>
                        </label>
                        <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">
                            Tahun lulusan
                            <select v-model="selectedYear" class="mt-2 block w-full rounded bg-slate-100 px-3 py-3 text-sm font-bold normal-case text-slate-900 outline-none focus:bg-white focus:ring-2 focus:ring-[#0D542B]" @change="scheduleLoad">
                                <option v-for="year in filters.available_years || ['ALL']" :key="year" :value="String(year)">{{ year === 'ALL' ? 'Semua Tahun' : year }}</option>
                            </select>
                        </label>
                        <button type="button" class="rounded bg-slate-100 px-4 py-3 text-sm font-bold text-slate-700 transition-colors hover:bg-slate-200" @click="resetFilters">Reset filter</button>
                    </div>
                    <div v-if="isLoading || loadError" class="mt-4 border-t border-slate-100 pt-3 text-sm">
                        <p v-if="isLoading" class="font-semibold text-[#0D542B]">Memperbarui grafik evaluasi atasan...</p>
                        <p v-if="loadError" class="font-semibold text-rose-700">{{ loadError }}</p>
                    </div>
                </section>

                <!-- Kartu KPI Ringkasan -->
                <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                    <div class="grid grid-cols-2 gap-3.5 lg:grid-cols-5">
                        <div v-for="card in [
                            { label: 'Populasi Evaluasi', value: formatNumber(summary.population), note: 'Undangan pada filter aktif' },
                            { label: 'Sudah Mengisi', value: formatNumber(summary.submitted), note: 'Evaluasi masuk dan valid' },
                            { label: 'Belum Mengisi', value: formatNumber(summary.pending), note: 'Menunggu jawaban atasan' },
                            { label: 'Response Rate', value: `${formatNumber(summary.response_rate, 1)}%`, note: 'Evaluasi masuk / populasi' },
                            { label: 'Rata-rata Skor', value: summary.average_score ? `${summary.average_score} / 5` : '-', note: 'Skor agregat aspek Likert' },
                        ]" :key="card.label" class="flex min-h-28 flex-col justify-between rounded-xl border border-slate-200 bg-white p-4">
                            <dt class="text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ card.label }}</dt>
                            <dd class="mt-1 text-2xl font-extrabold tracking-tight text-slate-900">{{ card.value }}</dd>
                            <p class="mt-2 border-t border-slate-100 pt-2 text-xs text-slate-500">{{ card.note }}</p>
                        </div>
                    </div>
                </section>

                <!-- State kosong bila belum ada data -->
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
                            class="flex-1 rounded-lg px-4 py-2.5 text-sm font-bold transition-all"
                            :class="activePanel === 'charts' ? 'bg-[#0D542B] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'"
                            @click="activePanel = 'charts'"
                        >
                            Grafik & Analisis
                        </button>
                        <button
                            type="button"
                            role="tab"
                            :aria-selected="activePanel === 'records'"
                            class="flex-1 rounded-lg px-4 py-2.5 text-sm font-bold transition-all"
                            :class="activePanel === 'records' ? 'bg-[#0D542B] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'"
                            @click="activePanel = 'records'"
                        >
                            Data Responden ({{ formatNumber(records.length) }})
                        </button>
                    </nav>

                    <!-- TAB 1: GRAFIK & ANALISIS -->
                    <div v-if="activePanel === 'charts'" class="space-y-5">
                        <!-- Sub-tab Kategori Indikator -->
                        <div class="flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white p-2">
                            <span class="px-2 text-xs font-bold uppercase tracking-wider text-slate-400">Pilih Indikator:</span>
                            <button
                                type="button"
                                class="rounded-lg px-4 py-2 text-xs font-bold transition-colors"
                                :class="selectedCategory === 'kinerja' ? 'bg-[#0D542B] text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                                @click="selectedCategory = 'kinerja'"
                            >
                                Evaluasi Aspek Kinerja ({{ likertItems.length }} Aspek)
                            </button>
                            <button
                                type="button"
                                class="rounded-lg px-4 py-2 text-xs font-bold transition-colors"
                                :class="selectedCategory === 'kesiapan' ? 'bg-[#0D542B] text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                                @click="selectedCategory = 'kesiapan'"
                            >
                                Tingkat Kesiapan Kerja
                            </button>
                            <button
                                type="button"
                                class="rounded-lg px-4 py-2 text-xs font-bold transition-colors"
                                :class="selectedCategory === 'prodi' ? 'bg-[#0D542B] text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                                @click="selectedCategory = 'prodi'"
                            >
                                Perbandingan Antar Program Studi
                            </button>
                        </div>

                        <!-- INDIKATOR 1: EVALUASI ASPEK KINERJA -->
                        <section v-if="selectedCategory === 'kinerja'" class="space-y-5">
                            <article class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                                <div class="border-b border-slate-100 pb-4">
                                    <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                                        <div>
                                            <p class="text-xs font-bold uppercase tracking-wider text-[#0D542B]">Indikator Kinerja</p>
                                            <h2 class="mt-1 text-xl font-extrabold text-slate-900 sm:text-2xl">Rata-rata Penilaian Aspek Kinerja Alumni</h2>
                                            <p class="mt-1 text-xs leading-relaxed text-slate-500 sm:text-sm">Skor dihitung dari seluruh butir kompetensi dengan skala Likert 1-5 berdasarkan jawaban atasan yang terverifikasi.</p>
                                        </div>
                                        <div class="shrink-0 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                                            Skala: <span class="font-semibold text-slate-800">1 (Sangat Kurang) s/d 5 (Sangat Baik)</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Visualisasi Utama ECharts: Luas, Full Width, Label Sangat Jelas -->
                                <div class="mt-5 min-h-[560px]">
                                    <AnalyticsEChart
                                        :option="scoreOption"
                                        :loading="isLoading"
                                        :height="Math.max(540, likertItems.length * 44)"
                                        aria-label="Grafik rata-rata aspek kinerja alumni"
                                    />
                                </div>

                                <!-- Kilas Indikator (Apple Weather Style: Narasi Singkat dan Padat) -->
                                <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50/70 p-4 sm:p-5">
                                    <p class="text-xs font-bold uppercase tracking-wider text-[#0D542B]">Kilas Analisis Aspek Kinerja</p>
                                    <div class="mt-3 grid gap-4 sm:grid-cols-3">
                                        <div class="rounded-lg border border-slate-200 bg-white p-3.5">
                                            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Rata-rata Agregat</p>
                                            <p class="mt-1 text-2xl font-extrabold text-[#0D542B]">{{ performanceHighlights.overallAverage ?? '-' }} <span class="text-xs font-semibold text-slate-400">/ 5</span></p>
                                            <p class="mt-1 text-xs text-slate-500">Dari {{ likertItems.length }} butir kompetensi yang dievaluasi.</p>
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
                                </div>
                            </article>

                            <!-- Breakdown Rincian Distribusi Per Butir Aspek (Seamless Inline) -->
                            <article class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                                    <div>
                                        <p class="text-xs font-bold uppercase tracking-wider text-[#0D542B]">Rincian Pilihan Jawaban</p>
                                        <h3 class="mt-1 text-lg font-extrabold text-slate-900">Distribusi Frekuensi Jawaban per Aspek</h3>
                                        <p class="mt-1 text-xs text-slate-500">Pilih aspek di bawah untuk melihat rincian sebaran jawaban atasan dari Sangat Baik hingga Sangat Kurang.</p>
                                    </div>
                                    <div class="w-full sm:w-80">
                                        <select
                                            v-model="selectedAspectCode"
                                            class="block w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-800 outline-none focus:border-[#0D542B] focus:bg-white focus:ring-1 focus:ring-[#0D542B]"
                                        >
                                            <option v-for="item in likertItems" :key="item.kode" :value="item.kode">{{ item.aspek }}</option>
                                        </select>
                                    </div>
                                </div>

                                <div v-if="activeAspect" class="mt-5">
                                    <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-4">
                                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/80 pb-3">
                                            <span class="text-sm font-bold text-slate-800">{{ activeAspect.aspek }}</span>
                                            <span class="rounded bg-[#0D542B] px-2.5 py-1 text-xs font-bold text-white">Rata-rata: {{ activeAspect.average ?? '-' }} / 5</span>
                                        </div>
                                        <div class="mt-4 min-h-[260px]">
                                            <AnalyticsEChart
                                                :option="aspectDetailOption"
                                                :loading="isLoading"
                                                :height="260"
                                                aria-label="Distribusi frekuensi butir aspek terpilih"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </article>
                        </section>

                        <!-- INDIKATOR 2: TINGKAT KESIAPAN KERJA -->
                        <section v-else-if="selectedCategory === 'kesiapan'" class="space-y-5">
                            <article class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                                <div class="border-b border-slate-100 pb-4">
                                    <p class="text-xs font-bold uppercase tracking-wider text-[#0D542B]">Indikator Kesiapan Kerja</p>
                                    <h2 class="mt-1 text-xl font-extrabold text-slate-900 sm:text-2xl">Tingkat Kesiapan Alumni dalam Bekerja</h2>
                                    <p class="mt-1 text-xs leading-relaxed text-slate-500 sm:text-sm">Distribusi penilaian atasan terhadap kesiapan alumni saat pertama kali mulai bekerja dan beradaptasi.</p>
                                </div>

                                <div class="mt-5 min-h-[420px]">
                                    <AnalyticsEChart
                                        :option="readinessOption"
                                        :loading="isLoading"
                                        :height="420"
                                        aria-label="Grafik distribusi kesiapan alumni"
                                    />
                                </div>

                                <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50/70 p-4 sm:p-5">
                                    <p class="text-xs font-bold uppercase tracking-wider text-[#0D542B]">Kilas Kesiapan Kerja</p>
                                    <div class="mt-3 grid gap-4 sm:grid-cols-3">
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
                                </div>
                            </article>
                        </section>

                        <!-- INDIKATOR 3: PERBANDINGAN ANTAR PROGRAM STUDI -->
                        <section v-else-if="selectedCategory === 'prodi'" class="space-y-5">
                            <article class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                                <div class="border-b border-slate-100 pb-4">
                                    <p class="text-xs font-bold uppercase tracking-wider text-[#0D542B]">Komparasi Akademik</p>
                                    <h2 class="mt-1 text-xl font-extrabold text-slate-900 sm:text-2xl">Perbandingan Rata-rata Skor Antar Program Studi</h2>
                                    <p class="mt-1 text-xs leading-relaxed text-slate-500 sm:text-sm">Pemeringkatan skor evaluasi kinerja alumni berdasarkan program studi asal untuk keperluan penjaminan mutu dan benchmark.</p>
                                </div>

                                <div class="mt-5 min-h-[420px]">
                                    <AnalyticsEChart
                                        :option="prodiOption"
                                        :loading="isLoading"
                                        :height="Math.max(420, prodiSummary.length * 48)"
                                        aria-label="Grafik perbandingan rata-rata evaluasi antarprogram studi"
                                    />
                                </div>

                                <!-- Tabel Benchmark Peringkat Prodi -->
                                <div class="mt-6 overflow-x-auto rounded-lg border border-slate-200 bg-white">
                                    <table class="w-full min-w-[640px] text-left text-xs">
                                        <thead class="border-b border-slate-200 bg-slate-50 text-[11px] uppercase tracking-wide text-slate-500">
                                            <tr>
                                                <th class="w-12 px-3 py-3 text-center">No</th>
                                                <th class="px-3 py-3">Program Studi</th>
                                                <th class="px-3 py-3 text-center">Jumlah Responden</th>
                                                <th class="px-3 py-3 text-center">Rata-rata Skor</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            <tr v-for="(item, idx) in prodiSummary" :key="item.prodi" class="hover:bg-slate-50">
                                                <td class="px-3 py-2.5 text-center font-mono text-slate-400">{{ idx + 1 }}</td>
                                                <td class="px-3 py-2.5 font-bold text-slate-800">{{ item.prodi }}</td>
                                                <td class="px-3 py-2.5 text-center font-semibold text-slate-600">{{ formatNumber(item.respondents) }}</td>
                                                <td class="px-3 py-2.5 text-center font-extrabold text-[#0D542B]">{{ item.average ? `${item.average} / 5` : '-' }}</td>
                                            </tr>
                                            <tr v-if="prodiSummary.length === 0">
                                                <td colspan="4" class="px-3 py-6 text-center text-slate-500">Belum ada data program studi pada filter ini.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </article>
                        </section>
                    </div>

                    <!-- TAB 2: DATA RESPONDEN (TABEL LENGKAP & PAGINASI) -->
                    <section v-else class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                        <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-[#0D542B]">Data Responden Evaluasi</p>
                                <h2 class="mt-1 text-xl font-extrabold text-slate-900">{{ formatNumber(filteredRecords.length) }} respon terverifikasi</h2>
                                <p class="mt-1 text-xs text-slate-500">Daftar respon atasan yang menjadi data pembentuk grafik pada filter aktif.</p>
                            </div>
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                                <input
                                    v-model="tableSearch"
                                    type="search"
                                    class="block w-full min-w-[280px] rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2 text-xs text-slate-900 outline-none focus:border-[#0D542B] focus:bg-white focus:ring-1 focus:ring-[#0D542B]"
                                    placeholder="Cari alumni, NIM, prodi, atasan, perusahaan..."
                                />
                                <label class="flex items-center gap-2 whitespace-nowrap text-xs text-slate-500">
                                    Baris:
                                    <select
                                        v-model="rowsPerPage"
                                        class="rounded-lg border border-slate-300 bg-slate-50 px-2.5 py-1.5 text-xs font-semibold outline-none focus:border-[#0D542B]"
                                    >
                                        <option :value="10">10</option>
                                        <option :value="20">20</option>
                                        <option :value="50">50</option>
                                    </select>
                                </label>
                            </div>
                        </div>

                        <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200">
                            <table class="w-full min-w-[900px] text-left text-xs">
                                <thead class="border-b border-slate-200 bg-slate-100 text-[11px] uppercase tracking-wide text-slate-600">
                                    <tr>
                                        <th class="w-12 px-3 py-3 text-center">No</th>
                                        <th class="px-3 py-3">Alumni</th>
                                        <th class="px-3 py-3">Program Studi</th>
                                        <th class="px-3 py-3">Perusahaan</th>
                                        <th class="px-3 py-3">Atasan</th>
                                        <th class="px-3 py-3 text-center">Jawaban</th>
                                        <th class="px-3 py-3 text-center">Rata-rata</th>
                                        <th class="px-3 py-3 text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    <tr v-if="paginatedRecords.length === 0">
                                        <td colspan="8" class="px-3 py-8 text-center text-slate-500">Tidak ada respon evaluasi yang sesuai dengan pencarian.</td>
                                    </tr>
                                    <tr v-for="(record, index) in paginatedRecords" :key="record.evaluasi_id" class="transition-colors hover:bg-slate-50">
                                        <td class="px-3 py-2.5 text-center font-mono text-slate-400">{{ (currentPage - 1) * rowsPerPage + index + 1 }}</td>
                                        <td class="px-3 py-2.5">
                                            <div class="font-bold text-slate-900">{{ record.nama || '-' }}</div>
                                            <div class="font-mono text-[11px] text-slate-500">{{ record.nim || '-' }}</div>
                                        </td>
                                        <td class="px-3 py-2.5 text-slate-700">{{ record.nama_prodi || '-' }}</td>
                                        <td class="px-3 py-2.5 text-slate-700">{{ record.nama_perusahaan || '-' }}</td>
                                        <td class="px-3 py-2.5 text-slate-700">{{ record.nama_atasan || '-' }}</td>
                                        <td class="px-3 py-2.5 text-center font-semibold text-slate-700">{{ record.answers_count }}</td>
                                        <td class="px-3 py-2.5 text-center">
                                            <span
                                                class="inline-block rounded px-2 py-0.5 font-bold"
                                                :class="record.average_score >= 4 ? 'bg-emerald-50 text-[#0D542B]' : 'bg-slate-100 text-slate-700'"
                                            >
                                                {{ record.average_score ? `${record.average_score} / 5` : '-' }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2.5 text-center">
                                            <Link
                                                v-if="record.biodata_id"
                                                :href="detailUrl(record)"
                                                class="inline-flex items-center gap-1 rounded bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-200"
                                            >
                                                Detail
                                            </Link>
                                            <a
                                                v-if="isValidLinkedInProfileUrl(record.linkedin_url)"
                                                :href="record.linkedin_url"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="ml-1 inline-flex rounded bg-slate-100 p-1.5 text-slate-600 hover:bg-slate-200 hover:text-[#0A66C2]"
                                                title="Buka profil LinkedIn"
                                            >
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                                    <path d="M20.45 20.45h-3.56v-5.58c0-1.33-.03-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.68H9.34V9h3.42v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28zM5.32 7.43a2.06 2.06 0 1 1 0-4.12 2.06 2.06 0 0 1 0 4.12zM3.54 9h3.56v11.45H3.54V9z" />
                                                </svg>
                                            </a>
                                            <span v-else class="text-slate-400">-</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Bar Paginasi -->
                        <!-- Pagination data responden mengikuti kontrol bersama. -->
                        <PaginationControls
                            :total="filteredRecords.length"
                            :current-page="currentPage"
                            :page-size="rowsPerPage"
                            summary-label="respon"
                            @update:current-page="currentPage = $event"
                        />
                    </section>

                    <!-- Section Metodologi Perhitungan -->
                    <section class="rounded-xl border border-slate-200 bg-white p-5">
                        <div class="flex items-center justify-between">
                            <h2 class="text-xs font-extrabold uppercase tracking-wider text-[#0D542B]">Metodologi Perhitungan</h2>
                            <button
                                type="button"
                                class="text-xs font-semibold text-slate-500 hover:text-slate-800"
                                @click="methodologyOpen = !methodologyOpen"
                            >
                                {{ methodologyOpen ? 'Tutup rincian' : 'Buka rincian' }}
                            </button>
                        </div>
                        <div v-show="methodologyOpen" class="mt-3 grid gap-3 text-sm leading-6 text-slate-600 md:grid-cols-3">
                            <p>{{ currentAnalytics.methodology?.formula }}</p>
                            <p>{{ currentAnalytics.methodology?.scale }}</p>
                            <p>{{ currentAnalytics.methodology?.denominator }}</p>
                        </div>
                        <p class="mt-3 border-t border-slate-100 pt-3 text-xs text-slate-500">Sumber data: {{ currentAnalytics.methodology?.source }}</p>
                    </section>
                </template>
            </div>
        </main>
    </div>
</template>
