<script setup>
/**
 * Halaman Grafik Program Studi dengan struktur shell yang sama seperti Grafik
 * Universitas: header konteks, filter, kartu ringkasan, grafik, dan data.
 * Visualisasi dibaca dari metadata pertanyaan sehingga instrumen baru tetap
 * dapat ditampilkan tanpa menambah kondisi per pertanyaan di halaman ini.
 */
import { computed, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import Sidebar from '../Components/Sidebar.vue';
import ProdiChoiceChart from '@/components/prodi-analytics/ProdiChoiceChart.vue';
import ProdiLikertChart from '@/components/prodi-analytics/ProdiLikertChart.vue';
import ProdiPivotTable from '@/components/prodi-analytics/ProdiPivotTable.vue';
import ProdiTextResponses from '@/components/prodi-analytics/ProdiTextResponses.vue';

// Props awal dari controller berisi filter, instrumen, ringkasan, grafik, dan records.
const props = defineProps({
    user: { type: Object, default: () => ({}) },
    analytics: {
        type: Object,
        default: () => ({ has_data: false, prodi: {}, filters: {}, chart: {} }),
    },
});

// Menyimpan payload analitik aktif agar filter dapat dimuat tanpa reload halaman penuh.
const currentAnalytics = ref(props.analytics);
// Menandai request data yang sedang berlangsung dan error yang perlu dilihat pengguna.
const isLoading = ref(false);
const loadError = ref('');
let filterTimer = null;

// Nilai filter aktif yang dikirim ke endpoint JSON analitik prodi.
const selectedProdiId = ref(String(props.analytics?.filters?.selected_prodi_id || '2'));
const selectedSectionId = ref(String(props.analytics?.filters?.selected_section_id || ''));
const selectedYear = ref(String(props.analytics?.filters?.selected_year || 'ALL'));

// Panel utama mengikuti Grafik Universitas: Grafik atau Data.
const activePanel = ref('charts');
// Subtampilan Data menyediakan rincian records dan pivot instrumen.
const dataView = ref('records');
// Elemen grafik yang dipilih untuk drill-down responden inline.
const selectedItem = ref(null);

// State pencarian dan paginasi untuk tabel data pembentuk grafik.
const tableSearch = ref('');
const currentPage = ref(1);
const rowsPerPage = ref(20);

// Nilai turunan dari payload backend yang dipakai oleh template dan komponen grafik.
const hasData = computed(() => Boolean(currentAnalytics.value?.has_data));
const currentProdi = computed(() => currentAnalytics.value?.prodi || {});
const filters = computed(() => currentAnalytics.value?.filters || {});
const chart = computed(() => currentAnalytics.value?.chart || {});
const summary = computed(() => chart.value?.summary || {});
const visualizations = computed(() => chart.value?.visualizations || []);
const pivotRows = computed(() => chart.value?.pivot || []);
const records = computed(() => chart.value?.records || []);

// Memilih tahun berjalan bila tersedia dan ALL bila tahun berjalan belum ada.
const defaultYear = () => {
    const currentYear = String(new Date().getFullYear());
    return (filters.value.available_years || []).includes(currentYear) ? currentYear : 'ALL';
};

// Memformat angka dengan format Indonesia untuk kartu dan tabel.
const formatNumber = (value, digits = 1) => Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: digits });

// Menampilkan kartu ringkasan dengan ukuran dan hierarki yang sama seperti universitas.
const kpiCards = computed(() => {
    const firstItem = visualizations.value[0] || {};
    const firstStat = firstItem.stat || {};

    if (chart.value.type === 'likert') {
        return [
            { label: 'Responden Evaluasi', value: formatNumber(summary.value.total_respondents, 0), note: `Dari ${formatNumber(summary.value.population_count, 0)} alumni terdata` },
            { label: 'Jawaban Valid', value: formatNumber(summary.value.valid_answers, 0), note: `${summary.value.total_questions || 0} butir pada bagian aktif` },
            { label: 'Rata-rata Skor', value: `${summary.value.average_score || 0} / 5`, note: 'Rata-rata seluruh skor valid' },
            { label: 'Median Skor', value: summary.value.median_score || 0, note: 'Nilai tengah seluruh skor valid' },
        ];
    }

    if (chart.value.type === 'choice') {
        return [
            { label: 'Responden Evaluasi', value: formatNumber(summary.value.total_respondents, 0), note: `Dari ${formatNumber(summary.value.population_count, 0)} alumni terdata` },
            { label: 'Pertanyaan Aktif', value: formatNumber(summary.value.total_questions, 0), note: 'Pertanyaan pilihan pada bagian aktif' },
            { label: 'Pilihan Terbanyak', value: firstStat.top_choice || '-', note: `${firstStat.top_count || 0} jawaban pada pertanyaan pertama` },
            { label: 'Jawaban Valid', value: formatNumber(summary.value.valid_answers, 0), note: 'Jawaban yang masuk distribusi' },
        ];
    }

    return [
        { label: 'Responden Evaluasi', value: formatNumber(summary.value.total_respondents, 0), note: `Dari ${formatNumber(summary.value.population_count, 0)} alumni terdata` },
        { label: 'Pertanyaan Aktif', value: formatNumber(summary.value.total_questions, 0), note: 'Pertanyaan terbuka pada bagian aktif' },
        { label: 'Jawaban Terisi', value: formatNumber(summary.value.total_feedbacks, 0), note: 'Masukan yang dapat dibaca' },
        { label: 'Tingkat Respons', value: `${summary.value.response_rate || 0}%`, note: 'Responden dibanding populasi terpilih' },
    ];
});

// Menjadwalkan pemuatan JSON agar perubahan filter tidak mengirim request bertubi-tubi.
const scheduleLoad = () => {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(async () => {
        isLoading.value = true;
        loadError.value = '';
        selectedItem.value = null;
        const params = new URLSearchParams({
            prodi_id: selectedProdiId.value,
            section_id: selectedSectionId.value,
            tahun: selectedYear.value,
        });

        try {
            const response = await fetch(`/superadmin/analitik-prodi/data?${params.toString()}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok) {
                throw new Error('analytics-request-failed');
            }
            currentAnalytics.value = await response.json();
            selectedSectionId.value = String(currentAnalytics.value.filters?.selected_section_id || '');
            currentPage.value = 1;
            tableSearch.value = '';
        } catch (error) {
            console.error('Gagal memuat analitik prodi:', error);
            loadError.value = 'Gagal memuat data analitik program studi.';
        } finally {
            isLoading.value = false;
        }
    }, 250);
};

// Menetapkan filter tahun berjalan saat program studi diganti.
const handleProdiChange = () => {
    selectedSectionId.value = '';
    selectedYear.value = String(new Date().getFullYear());
    scheduleLoad();
};

// Mengembalikan filter ke Sistem Informasi, bagian default, dan tahun berjalan.
const resetFilters = () => {
    selectedProdiId.value = '2';
    selectedSectionId.value = '';
    selectedYear.value = defaultYear();
    selectedItem.value = null;
    activePanel.value = 'charts';
    dataView.value = 'records';
    tableSearch.value = '';
    scheduleLoad();
};

// Menangani klik grafik dan menyimpan konteks pertanyaan/kategori yang dipilih.
const handleChartSelect = (selection) => {
    selectedItem.value = selection;
    activePanel.value = 'charts';
    currentPage.value = 1;
};

// Menghapus filter drill-down agar grafik kembali ke ringkasan seluruh bagian.
const clearSelection = () => {
    selectedItem.value = null;
    currentPage.value = 1;
};

// Membatasi rows pada pertanyaan/kategori yang diklik pada grafik.
const selectedRecords = computed(() => {
    const selection = selectedItem.value;
    if (!selection?.item?.key) return [];

    const questionRecords = records.value.filter((record) => record.kode === selection.item.key);
    if (selection.item.type !== 'choice' || !selection.label) return questionRecords;

    const label = String(selection.label).toLowerCase();
    return questionRecords.filter((record) => String(record.jawaban_label || '').toLowerCase().split(',').some((value) => value.trim() === label));
});

// Menyaring tabel berdasarkan drill-down grafik dan kata kunci pencarian.
const filteredRecords = computed(() => {
    const source = selectedItem.value ? selectedRecords.value : records.value;
    const query = tableSearch.value.trim().toLowerCase();
    if (!query) return source;

    return source.filter((record) => [record.nama, record.nim, record.aspek, record.jawaban_label, record.kode]
        .some((value) => String(value || '').toLowerCase().includes(query)));
});

// Membuat halaman tabel lokal tanpa mengubah jumlah data yang dihitung backend.
const totalPages = computed(() => Math.max(1, Math.ceil(filteredRecords.value.length / rowsPerPage.value)));
const paginatedRecords = computed(() => {
    const start = (currentPage.value - 1) * rowsPerPage.value;
    return filteredRecords.value.slice(start, start + rowsPerPage.value);
});

// Menyesuaikan halaman ketika hasil pencarian menjadi lebih pendek.
watch([tableSearch, rowsPerPage], () => { currentPage.value = 1; });
watch(totalPages, () => {
    if (currentPage.value > totalPages.value) currentPage.value = totalPages.value;
});

// Menyelaraskan filter lokal setelah server mengembalikan payload baru.
watch(() => props.analytics, (newAnalytics) => {
    currentAnalytics.value = newAnalytics;
    selectedProdiId.value = String(newAnalytics?.filters?.selected_prodi_id || '2');
    selectedSectionId.value = String(newAnalytics?.filters?.selected_section_id || '');
    selectedYear.value = String(newAnalytics?.filters?.selected_year || 'ALL');
}, { deep: true });
</script>

<template>
    <Head :title="`Grafik Prodi - ${currentProdi.nama_prodi || 'Program Studi'}`" />

    <div class="min-h-screen bg-[#F5F7F8] text-slate-900 lg:flex">
        <Sidebar :user="user" active-menu="analytics-prodi" />

        <!-- Offset kiri 18rem mengikuti sidebar fixed pada Grafik Universitas. -->
        <main class="min-w-0 flex-1 lg:pl-72">
            <!-- Header hijau dan breadcrumb disamakan dengan halaman analitik universitas. -->
            <header class="border-b border-emerald-900/40 bg-[#0D542B] px-5 py-7 text-white sm:px-8">
                <div class="mx-auto max-w-[1480px]">
                    <div class="flex flex-wrap items-center gap-2 text-xs font-medium text-emerald-100">
                        <Link href="/superadmin/dashboard" class="font-semibold hover:text-white">Dashboard</Link>
                        <span class="text-emerald-300/70">/</span>
                        <span class="rounded bg-white/10 px-2 py-0.5 font-bold text-white">Grafik Prodi</span>
                    </div>
                    <div class="mt-5 flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-200">Tracer Study Program Studi</p>
                            <h1 class="mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl">Grafik Prodi</h1>
                            <p class="mt-2 max-w-3xl text-sm leading-6 text-emerald-100/90">Eksplorasi instrumen dan jawaban alumni {{ currentProdi.nama_prodi || 'program studi' }} dengan perhitungan yang dapat ditelusuri.</p>
                        </div>
                        <p class="text-xs text-emerald-100/90">Sumber aktif: Kuesioner Program Studi</p>
                    </div>
                </div>
            </header>

            <div class="mx-auto mt-5 max-w-[1480px] space-y-5 px-5 pb-8 sm:px-8">
                <!-- Filter utama mengikuti susunan dan ukuran filter Grafik Universitas. -->
                <section class="bg-white p-5 shadow-lg shadow-slate-900/5">
                    <div class="grid gap-4 xl:grid-cols-[minmax(18rem,2fr)_minmax(18rem,2fr)_10rem_auto] xl:items-end">
                        <label class="block min-w-0 text-xs font-bold uppercase tracking-wide text-slate-500">
                            Program studi
                            <select v-model="selectedProdiId" class="mt-2 block w-full rounded bg-slate-100 px-3 py-3 text-sm font-bold normal-case text-slate-900 outline-none focus:bg-white focus:ring-2 focus:ring-[#0D542B]" @change="handleProdiChange">
                                <option v-for="prodi in filters.available_prodis || []" :key="prodi.id" :value="String(prodi.id)">{{ prodi.nama_prodi }}</option>
                            </select>
                        </label>
                        <label class="block min-w-0 text-xs font-bold uppercase tracking-wide text-slate-500">
                            Dimensi / topik pertanyaan
                            <select v-model="selectedSectionId" class="mt-2 block w-full rounded bg-slate-100 px-3 py-3 text-sm font-bold normal-case text-slate-900 outline-none focus:bg-white focus:ring-2 focus:ring-[#0D542B]" @change="scheduleLoad">
                                <option v-for="section in filters.sections || []" :key="section.id" :value="String(section.id)">Bagian {{ section.order }} · {{ section.title }} ({{ section.question_count }} butir)</option>
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
                        <p v-if="isLoading" class="font-semibold text-[#0D542B]">Memperbarui analitik tanpa memuat ulang halaman.</p>
                        <p v-if="loadError" class="font-semibold text-[#B42318]">{{ loadError }}</p>
                    </div>
                </section>

                <!-- Empty state tetap menjelaskan status populasi dan instrumen. -->
                <section v-if="!hasData" class="bg-white p-8 text-center rounded-xl border border-slate-200">
                    <p class="text-xs font-bold uppercase tracking-wide text-[#0D542B]">Status data program studi</p>
                    <h2 class="mt-2 text-xl font-extrabold">Data belum dapat divisualisasikan</h2>
                    <p class="mx-auto mt-2 max-w-2xl text-sm leading-6 text-slate-600">{{ currentAnalytics.message || 'Belum ada instrumen pertanyaan program studi yang dapat ditampilkan.' }}</p>
                    <button type="button" class="mt-5 rounded bg-slate-100 px-4 py-2.5 text-sm font-bold text-slate-800 transition-colors hover:bg-slate-200" @click="resetFilters">Reset filter</button>
                </section>

                <template v-else>
                    <!-- Identitas indikator dan empat kartu ringkasan mengikuti struktur universitas. -->
                    <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                            <div class="min-w-0">
                                <h2 class="text-xl font-extrabold text-slate-900 sm:text-2xl">{{ chart.title }}</h2>
                                <p class="mt-1 max-w-3xl text-xs leading-relaxed text-slate-500 sm:text-sm">{{ chart.subtitle }}</p>
                            </div>
                            <div v-if="selectedItem" class="flex flex-wrap items-center gap-2 self-start sm:self-auto">
                                <span class="text-xs text-slate-500">Analisis Terpilih:</span>
                                <span class="rounded border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-bold text-[#0D542B]">{{ selectedItem.label }}</span>
                                <button type="button" class="rounded border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-100" @click="clearSelection">Kembali ke analisis keseluruhan</button>
                            </div>
                        </div>
                        <div class="mt-5 grid w-full grid-cols-2 gap-3.5 lg:grid-cols-4">
                            <div v-for="card in kpiCards" :key="card.label" class="relative flex flex-col justify-between rounded-xl border border-slate-200 bg-white p-4.5">
                                <div>
                                    <dt class="text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ card.label }}</dt>
                                    <dd class="mt-1.5 line-clamp-2 text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl" :title="String(card.value)">{{ card.value }}</dd>
                                </div>
                                <p class="mt-2.5 border-t border-slate-100 pt-2 text-xs leading-relaxed text-slate-500">{{ card.note }}</p>
                            </div>
                        </div>
                    </section>

                    <!-- Narasi ringkas menjelaskan arti angka sebelum grafik dibaca. -->
                    <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                        <p class="text-xs font-bold uppercase tracking-wider text-[#0D542B]">Kilas Analisis</p>
                        <p class="mt-2 text-sm font-semibold leading-6 text-slate-900">{{ chart.insights?.headline }}</p>
                        <ul class="mt-3 space-y-1.5 text-xs leading-5 text-slate-600">
                            <li v-for="(point, index) in chart.insights?.points || []" :key="index">{{ point }}</li>
                        </ul>
                    </section>

                    <!-- Tab utama hanya dua seperti Grafik Universitas; analisis klik tampil inline. -->
                    <nav class="flex gap-1 rounded-xl border border-slate-200 bg-white p-1.5" role="tablist" aria-label="Tampilan analitik program studi">
                        <button v-for="panel in [{ key: 'charts', label: 'Grafik' }, { key: 'data', label: 'Data' }]" :key="panel.key" type="button" role="tab" :aria-selected="activePanel === panel.key" class="flex-1 rounded-lg px-4 py-2.5 text-sm font-bold transition-all" :class="activePanel === panel.key ? 'bg-[#0D542B] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'" @click="activePanel = panel.key">{{ panel.label }}</button>
                    </nav>

                    <!-- Panel grafik menampilkan drill-down analisis dan jumlah responden pembentuk. -->
                    <section v-if="activePanel === 'charts'" class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                        <div class="border-b border-slate-100 pb-4">
                            <p class="text-xs font-bold uppercase tracking-wider text-[#0D542B]">Visualisasi Grafik</p>
                            <h2 class="mt-1 text-xl font-extrabold text-slate-900">{{ chart.title }}</h2>
                            <p class="mt-1 text-xs leading-5 text-slate-500">{{ chart.caption }}</p>
                        </div>

                        <div class="mt-5 min-w-0">
                            <ProdiLikertChart v-if="chart.type === 'likert'" :items="visualizations" :caption="chart.caption" @select="handleChartSelect" />
                            <ProdiChoiceChart v-else-if="chart.type === 'choice'" :items="visualizations" @select="handleChartSelect" />
                            <ProdiTextResponses v-else :items="visualizations" />
                        </div>

                        <section v-if="selectedItem" class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50/40 p-4">
                            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                                <div class="min-w-0">
                                    <p class="text-xs font-bold uppercase tracking-wider text-[#0D542B]">Analisis terpilih</p>
                                    <h3 class="mt-1 text-base font-extrabold leading-6 text-slate-900">{{ selectedItem.label }}</h3>
                                    <p class="mt-1 text-xs leading-5 text-slate-600">{{ selectedItem.item?.title }}</p>
                                </div>
                                <button type="button" class="shrink-0 rounded-lg bg-[#0D542B] px-3.5 py-2 text-xs font-bold text-white transition-colors hover:bg-[#0A4322]" @click="activePanel = 'data'; dataView = 'records'">Lihat data pembentuk ({{ selectedRecords.length }})</button>
                            </div>
                            <p class="mt-3 border-t border-emerald-200 pt-3 text-sm leading-6 text-slate-700">Klik grafik menyaring responden yang membentuk nilai atau kategori tersebut. Data lengkap tetap tersedia pada tab Data.</p>
                            <!-- Pratinjau nama membuat hasil klik dapat dipahami tanpa berpindah halaman. -->
                            <div v-if="selectedRecords.length" class="mt-3 grid gap-2 border-t border-emerald-200 pt-3 sm:grid-cols-2 lg:grid-cols-3">
                                <div v-for="record in selectedRecords.slice(0, 6)" :key="record.response_id" class="rounded-lg border border-emerald-100 bg-white px-3 py-2">
                                    <p class="truncate text-xs font-bold text-slate-800">{{ record.nama }}</p>
                                    <p class="mt-0.5 text-[11px] text-slate-500">{{ record.nim }} · {{ record.tahun_lulus }}</p>
                                </div>
                            </div>
                            <p v-if="selectedRecords.length > 6" class="mt-2 text-[11px] text-slate-500">Menampilkan 6 nama pertama dari {{ selectedRecords.length }} responden. Buka Data untuk melihat semuanya.</p>
                        </section>
                    </section>

                    <!-- Panel data memiliki records dan pivot dinamis agar struktur universitas tetap ringkas. -->
                    <section v-else class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                        <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-[#0D542B]">Detail Data Responden</p>
                                <h2 class="mt-1 text-xl font-extrabold text-slate-900">{{ formatNumber(filteredRecords.length, 0) }} baris data sesuai filter</h2>
                                <p class="mt-1 text-xs text-slate-500">Data individual terhubung langsung dengan jawaban yang membentuk grafik.</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" class="rounded-lg px-3.5 py-2 text-xs font-bold transition-colors" :class="dataView === 'records' ? 'bg-[#0D542B] text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'" @click="dataView = 'records'">Data responden</button>
                                <button type="button" class="rounded-lg px-3.5 py-2 text-xs font-bold transition-colors" :class="dataView === 'pivot' ? 'bg-[#0D542B] text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'" @click="dataView = 'pivot'">Pivot pertanyaan</button>
                            </div>
                        </div>

                        <template v-if="dataView === 'records'">
                            <div v-if="selectedItem" class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3.5 py-2.5 text-xs text-emerald-900">
                                <span>Filter grafik aktif: <strong>{{ selectedItem.label }}</strong></span>
                                <button type="button" class="font-bold underline" @click="clearSelection">Hapus filter grafik</button>
                            </div>
                            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <input v-model="tableSearch" type="search" class="block w-full max-w-md rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2 text-xs text-slate-900 outline-none focus:border-[#0D542B] focus:bg-white focus:ring-1 focus:ring-[#0D542B]" placeholder="Cari nama, NIM, pertanyaan, atau jawaban..." />
                                <label class="flex items-center gap-2 text-xs text-slate-500">Per halaman:
                                    <select v-model="rowsPerPage" class="rounded-lg border border-slate-300 bg-slate-50 px-2.5 py-1.5 text-xs font-semibold outline-none focus:border-[#0D542B]"><option :value="10">10</option><option :value="20">20</option><option :value="50">50</option></select>
                                </label>
                            </div>
                            <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200">
                                <table class="min-w-[920px] w-full divide-y divide-slate-200 text-left text-xs">
                                    <thead><tr class="bg-slate-100 font-semibold text-slate-700"><th class="w-12 px-3 py-3 text-center">No</th><th class="px-3 py-3">Nama Alumni</th><th class="px-3 py-3">NIM</th><th class="px-3 py-3">Tahun Lulus</th><th class="px-3 py-3 min-w-[300px]">Pertanyaan</th><th class="px-3 py-3 min-w-[190px]">Jawaban</th><th class="px-3 py-3 text-center">Skor</th></tr></thead>
                                    <tbody class="divide-y divide-slate-100 bg-white">
                                        <tr v-if="paginatedRecords.length === 0"><td colspan="7" class="px-3 py-8 text-center text-slate-500">Tidak ada data responden yang sesuai.</td></tr>
                                        <tr v-for="(row, index) in paginatedRecords" :key="row.response_id || index" class="transition-colors hover:bg-slate-50">
                                            <td class="px-3 py-2.5 text-center font-mono text-slate-400">{{ (currentPage - 1) * rowsPerPage + index + 1 }}</td><td class="px-3 py-2.5 font-semibold text-slate-800">{{ row.nama }}</td><td class="px-3 py-2.5 font-mono text-slate-700">{{ row.nim }}</td><td class="px-3 py-2.5 text-slate-600">{{ row.tahun_lulus }}</td><td class="px-3 py-2.5 leading-5 text-slate-700">{{ row.aspek }}</td><td class="px-3 py-2.5 font-medium leading-5 text-slate-800">{{ row.jawaban_label || '-' }}</td><td class="px-3 py-2.5 text-center font-bold text-slate-700">{{ row.skor ?? '-' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="flex items-center justify-between pt-4 text-xs text-slate-500"><span>Menampilkan {{ paginatedRecords.length }} dari {{ filteredRecords.length }} jawaban</span><div class="flex items-center gap-2"><button type="button" class="rounded-lg border border-slate-200 px-3 py-1.5 font-semibold hover:bg-slate-50 disabled:opacity-40" :disabled="currentPage <= 1" @click="currentPage--">Sebelumnya</button><span>Halaman {{ currentPage }} dari {{ totalPages }}</span><button type="button" class="rounded-lg border border-slate-200 px-3 py-1.5 font-semibold hover:bg-slate-50 disabled:opacity-40" :disabled="currentPage >= totalPages" @click="currentPage++">Berikutnya</button></div></div>
                        </template>
                        <ProdiPivotTable v-else :pivot="pivotRows" />
                    </section>
                </template>
            </div>
        </main>
    </div>
</template>
