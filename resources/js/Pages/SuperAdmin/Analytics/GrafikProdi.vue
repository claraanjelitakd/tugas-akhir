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
import ProdiAnalyticsChartsPanel from '@/components/prodi-analytics/ProdiAnalyticsChartsPanel.vue';
import ProdiPivotTable from '@/components/prodi-analytics/ProdiPivotTable.vue';
import PaginationControls from '@/components/PaginationControls.vue';

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
// Menandai request data JSON yang sedang berlangsung.
const isLoading = ref(false);
// Menyimpan pesan error request agar dapat ditampilkan di bawah filter.
const loadError = ref('');
// Menahan request filter selama 250 ms agar perubahan dropdown tidak bertubi-tubi.
let filterTimer = null;

// Mengambil ID Sistem Informasi dari metadata prodi, bukan mengunci ID database.
const defaultProdiId = (payload) => {
    const prodis = payload?.filters?.available_prodis || [];
    return String(
        prodis.find((prodi) => String(prodi.kode_prodi) === '72')?.id
        || prodis.find((prodi) => prodi.has_questions)?.id
        || prodis[0]?.id
        || '',
    );
};

// ID prodi aktif yang dikirim ke endpoint JSON.
const selectedProdiId = ref(String(props.analytics?.filters?.selected_prodi_id || defaultProdiId(props.analytics)));
// ID bagian pertanyaan aktif yang dikirim ke endpoint JSON.
const selectedSectionId = ref(String(props.analytics?.filters?.selected_section_id || ''));
// Tahun lulusan aktif yang dikirim ke endpoint JSON.
const selectedYear = ref(String(props.analytics?.filters?.selected_year || 'ALL'));

// Panel utama mengikuti Grafik Universitas: Grafik atau Data.
const activePanel = ref('charts');
// Subtampilan Data menyediakan rincian records dan pivot instrumen.
const dataView = ref('records');
// Elemen grafik yang dipilih untuk drill-down responden inline.
const selectedItem = ref(null);
// Pertanyaan choice yang sedang aktif; dipakai agar KPI tidak selalu membaca item pertama.
const activeQuestionContext = ref(null);
// Menyimpan status buka/tutup metodologi agar pola informasi sama dengan Grafik Universitas.
const methodologyOpen = ref(false);

// State pencarian dan paginasi untuk tabel data pembentuk grafik.
const tableSearch = ref('');
// Nomor halaman tabel records yang sedang dibuka.
const currentPage = ref(1);
// Batas jumlah records per halaman.
const rowsPerPage = ref(20);

// Menentukan apakah payload memiliki data yang dapat divisualisasikan.
const hasData = computed(() => Boolean(currentAnalytics.value?.has_data));
// Mengambil identitas program studi aktif dari payload backend.
const currentProdi = computed(() => currentAnalytics.value?.prodi || {});
// Mengambil seluruh filter dan pilihan section dari payload backend.
const filters = computed(() => currentAnalytics.value?.filters || {});
// Mengambil konfigurasi chart bagian aktif dari payload backend.
const chart = computed(() => currentAnalytics.value?.chart || {});
// Mengambil ringkasan KPI bagian aktif.
const summary = computed(() => chart.value?.summary || {});
// Mengambil daftar item pertanyaan untuk komponen kategori.
const visualizations = computed(() => chart.value?.visualizations || []);
// Mengambil data pivot untuk tab Data Ringkasan.
const pivotRows = computed(() => chart.value?.pivot || []);
// Mengambil records alumni untuk tabel dan drill-down.
const records = computed(() => chart.value?.records || []);
// ALUR 11: ID section dari filter dicocokkan ke metadata section untuk
// mendapatkan order. Order ini diteruskan ke hub agar file kategori yang tepat
// dapat dimount tanpa menebak dari judul pertanyaan.
const selectedSectionOrder = computed(() => Number((filters.value.sections || [])
    .find((section) => String(section.id) === String(filters.value.selected_section_id))?.order || 0));

// Memilih tahun berjalan bila tersedia dan ALL bila tahun berjalan belum ada.
const defaultYear = () => {
    const currentYear = String(new Date().getFullYear());
    return (filters.value.available_years || []).includes(currentYear) ? currentYear : 'ALL';
};

// Memformat angka dengan format Indonesia untuk kartu dan tabel.
const formatNumber = (value, digits = 1) => Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: digits });

// Membuat tautan WhatsApp dari nomor alumni tanpa mengubah data kontak yang tersimpan.
const formatWhatsAppUrl = (record) => {
    const raw = String(record.nomor_telepon || '').replace(/\D/g, '');
    if (!raw) return '';
    const formatted = raw.startsWith('0') ? `62${raw.slice(1)}` : (raw.startsWith('62') ? raw : `62${raw}`);
    const text = encodeURIComponent(`Halo ${record.nama || 'Alumni'}, kami dari Tracer Study UKDW ingin menindaklanjuti jawaban kuesioner program studi Anda. Terima kasih.`);

    return `https://wa.me/${formatted}?text=${text}`;
};

// Membuat tautan email untuk tindak lanjut jawaban alumni.
const formatEmailUrl = (record) => {
    if (!record.email) return '';
    const subject = encodeURIComponent('Tindak lanjut kuesioner Program Studi UKDW');
    const body = encodeURIComponent(`Halo ${record.nama || 'Alumni'},\n\nKami dari Tracer Study UKDW ingin menindaklanjuti jawaban kuesioner program studi Anda.\n\nTerima kasih.`);

    return `mailto:${record.email}?subject=${subject}&body=${body}`;
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

// Mengarahkan aksi detail ke halaman alumni yang sama dengan Grafik Universitas.
const detailUrl = (record) => {
    const detailBaseUrl = analyticsBaseUrl.value === '/fakultas'
        ? '/fakultas/analitik'
        : ['/prodi', '/biro3'].includes(analyticsBaseUrl.value)
            ? `${analyticsBaseUrl.value}/analitik`
        : analyticsBaseUrl.value;

    return `${detailBaseUrl}/alumni/${record.biodata_id}`;
};

// Menampilkan kartu ringkasan dengan ukuran dan hierarki yang sama seperti universitas.
const kpiCards = computed(() => {
    const activeItem = activeQuestionContext.value || visualizations.value[0] || {};
    const activeStat = activeItem.stat || {};

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
            { label: 'Pertanyaan Aktif', value: activeItem.title || '-', note: `${formatNumber(summary.value.total_questions, 0)} pertanyaan pilihan pada bagian aktif` },
            { label: 'Pilihan Terbanyak', value: activeStat.top_choice || '-', note: `${activeStat.top_count || 0} jawaban pada pertanyaan aktif` },
            { label: 'Jawaban Valid', value: formatNumber(activeStat.valid_count, 0), note: 'Jawaban pada pertanyaan aktif' },
        ];
    }

    return [
        { label: 'Responden Evaluasi', value: formatNumber(summary.value.total_respondents, 0), note: `Dari ${formatNumber(summary.value.population_count, 0)} alumni terdata` },
        { label: 'Pertanyaan Aktif', value: formatNumber(summary.value.total_questions, 0), note: 'Pertanyaan terbuka pada bagian aktif' },
        { label: 'Jawaban Terisi', value: formatNumber(summary.value.total_feedbacks, 0), note: 'Masukan yang dapat dibaca' },
        { label: 'Tingkat Respons', value: `${summary.value.response_rate || 0}%`, note: 'Responden dibanding populasi terpilih' },
    ];
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

// Menjadwalkan pemuatan JSON agar perubahan filter tidak mengirim request bertubi-tubi.
const scheduleLoad = () => {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(async () => {
        isLoading.value = true;
        loadError.value = '';
        selectedItem.value = null;
        activeQuestionContext.value = null;
        const params = new URLSearchParams({
            prodi_id: selectedProdiId.value,
            section_id: selectedSectionId.value,
            tahun: selectedYear.value,
        });

        try {
            const response = await fetch(`${analyticsBaseUrl.value}/analitik-prodi/data?${params.toString()}`, {
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
    selectedProdiId.value = defaultProdiId(currentAnalytics.value);
    selectedSectionId.value = '';
    selectedYear.value = defaultYear();
    selectedItem.value = null;
    activeQuestionContext.value = null;
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

// Menyimpan tab pertanyaan aktif agar kartu ringkasan selalu ikut berganti.
const handleQuestionContext = (item) => {
    activeQuestionContext.value = item || null;
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
    selectedProdiId.value = String(newAnalytics?.filters?.selected_prodi_id || defaultProdiId(newAnalytics));
    selectedSectionId.value = String(newAnalytics?.filters?.selected_section_id || '');
    selectedYear.value = String(newAnalytics?.filters?.selected_year || 'ALL');
}, { deep: true });
</script>

<template>
    <Head :title="`Grafik Prodi - ${currentProdi.nama_prodi || 'Program Studi'}`" />

    <div class="min-h-screen bg-[#F5F7F8] text-slate-900 lg:flex min-w-[1280px]">
        <Sidebar :user="user" active-menu="analytics-prodi" />

        <!-- Offset kiri 18rem mengikuti sidebar fixed pada Grafik Universitas. -->
        <main class="min-w-0 flex-1 lg:pl-72">
            <!-- Header hijau dan breadcrumb disamakan dengan halaman analitik universitas. -->
            <header class="border-b border-emerald-900/40 bg-[#0D542B] px-5 py-7 text-white sm:px-8">
                <div class="w-full">
                    <div class="flex flex-wrap items-center gap-2 text-xs font-medium text-emerald-100">
                        <Link :href="`${analyticsBaseUrl.value}/dashboard`" class="font-semibold hover:text-white">Dashboard</Link>
                        <span class="text-emerald-300/70">/</span>
                        <span class="rounded bg-white/10 px-2 py-0.5 font-bold text-white">Grafik Prodi</span>
                    </div>
                    <div class="mt-5 flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                        <div class="min-w-0">
                            <h1 class="mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl">Grafik Prodi</h1>
                            <p class="mt-2 max-w-3xl text-sm leading-6 text-emerald-100/90">Eksplorasi instrumen dan jawaban alumni {{ currentProdi.nama_prodi || 'program studi' }} dengan perhitungan yang dapat ditelusuri.</p>
                        </div>
                        <p class="text-xs text-emerald-100/90">Sumber aktif: Kuesioner Program Studi</p>
                    </div>
                </div>
            </header>

            <div class="mt-5 space-y-5 px-5 pb-8 sm:px-8 w-full">
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
                        </div>
                        <div class="mt-5 grid w-full grid-cols-2 gap-3.5 lg:grid-cols-4">
                            <div v-for="card in kpiCards" :key="card.label" class="relative flex flex-col justify-between rounded-xl border border-slate-200 bg-white p-4.5">
                                <div>
                                    <dt class="text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ card.label }}</dt>
                                    <dd
                                        class="mt-1.5 break-words whitespace-normal font-extrabold tracking-tight text-slate-900"
                                        :class="String(card.value || '').length > 18 ? 'text-sm leading-snug sm:text-base' : (String(card.value || '').length > 10 ? 'text-xl leading-tight sm:text-2xl' : 'text-2xl sm:text-3xl')"
                                        :title="String(card.value)"
                                    >
                                        {{ card.value }}
                                    </dd>
                                </div>
                                <p class="mt-2.5 border-t border-slate-100 pt-2 text-xs leading-relaxed text-slate-500">{{ card.note }}</p>
                            </div>
                        </div>
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
                            <!-- Skala Likert dijelaskan di dekat grafik agar tidak hilang di kartu header. -->
                            <p v-if="chart.type === 'likert'" class="mt-2 rounded border border-slate-200 bg-slate-50 px-3 py-2 text-xs leading-5 text-slate-600">
                                Skala Penilaian: 1 = Sangat Tidak Setuju, 2 = Tidak Setuju, 3 = Netral, 4 = Setuju, 5 = Sangat Setuju.
                            </p>
                        </div>

                        <div class="mt-5 min-w-0">
                            <ProdiAnalyticsChartsPanel :items="visualizations" :section-order="selectedSectionOrder" @select="handleChartSelect" @context-change="handleQuestionContext" />
                        </div>

                        <!-- Pilihan sudah memiliki analisis di bawah grafik; tipe lain memakai Kilas global. -->
                        <section v-if="chart.type !== 'choice'" class="mt-6 rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                            <p class="text-xs font-bold uppercase tracking-wider text-[#0D542B]">Kilas Analisis</p>
                            <p class="mt-2 text-sm font-semibold leading-6 text-slate-900">{{ chart.insights?.headline }}</p>
                            <ul class="mt-3 space-y-1.5 text-xs leading-5 text-slate-600">
                                <li v-for="(point, index) in chart.insights?.points || []" :key="index">{{ point }}</li>
                            </ul>
                        </section>

                        <section v-if="selectedItem" class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50/40 p-4">
                            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                                <div class="min-w-0">
                                    <p class="text-xs font-bold uppercase tracking-wider text-[#0D542B]">Analisis terpilih</p>
                                    <!-- Pertanyaan lengkap dipindahkan ke kartu inline agar tidak terpotong di header. -->
                                    <h3 class="mt-1 break-words text-base font-extrabold leading-6 text-slate-900">{{ selectedItem.item?.type === 'choice' ? selectedItem.label : selectedItem.item?.title }}</h3>
                                    <p class="mt-1 text-xs leading-5 text-slate-600">
                                        <span v-if="selectedItem.item?.type === 'choice'">Pertanyaan: {{ selectedItem.item?.title }}</span>
                                        <span v-else>Skor yang dipilih: {{ selectedItem.value ?? '-' }} / 5</span>
                                    </p>
                                </div>
                                <button type="button" class="shrink-0 rounded-lg bg-[#0D542B] px-3.5 py-2 text-xs font-bold text-white transition-colors hover:bg-[#0A4322]" @click="activePanel = 'data'; dataView = 'records'">Lihat data pembentuk ({{ selectedRecords.length }})</button>
                            </div>
                            <p class="mt-3 border-t border-emerald-200 pt-3 text-sm leading-6 text-slate-700">Klik grafik menyaring responden yang membentuk nilai atau kategori tersebut. Data lengkap tetap tersedia pada tab Data.</p>
                            <!-- Tabel ringkas mempertahankan konteks klik sekaligus memberi aksi tindak lanjut alumni. -->
                            <div class="mt-4 overflow-x-auto rounded-lg border border-emerald-200 bg-white">
                                <table class="min-w-[760px] w-full divide-y divide-slate-200 text-left text-xs">
                                    <thead class="bg-emerald-50/60 font-semibold text-slate-700"><tr><th class="px-3 py-2.5">Alumni</th><th class="px-3 py-2.5">NIM</th><th class="px-3 py-2.5">Jawaban</th><th class="px-3 py-2.5">Isian Lainnya</th><th class="px-3 py-2.5 text-center">Aksi</th></tr></thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <tr v-for="record in selectedRecords.slice(0, 6)" :key="record.response_id" class="hover:bg-slate-50">
                                            <td class="px-3 py-2.5 font-semibold text-slate-800">{{ record.nama }}</td><td class="px-3 py-2.5 font-mono text-slate-600">{{ record.nim }}</td><td class="px-3 py-2.5 text-slate-700">{{ record.jawaban_label || '-' }}</td><td class="px-3 py-2.5 text-slate-700">{{ record.jawaban_lainnya || '-' }}</td>
                                            <td class="px-3 py-2.5"><div class="flex items-center justify-center gap-1"><Link :href="detailUrl(record)" class="rounded p-1.5 text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900" title="Lihat detail alumni"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg></Link><a v-if="isValidLinkedInProfileUrl(record.linkedin_url)" :href="record.linkedin_url" target="_blank" rel="noopener noreferrer" class="rounded p-1.5 text-slate-600 transition-colors hover:bg-slate-100 hover:text-[#0A66C2]" title="Buka profil LinkedIn"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.45 20.45h-3.56v-5.58c0-1.33-.03-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.68H9.34V9h3.42v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28zM5.32 7.43a2.06 2.06 0 1 1 0-4.12 2.06 2.06 0 0 1 0 4.12zM3.54 9h3.56v11.45H3.54V9z" /></svg></a><a v-if="record.nomor_telepon" :href="formatWhatsAppUrl(record)" target="_blank" class="rounded p-1.5 text-slate-600 transition-colors hover:bg-slate-100 hover:text-emerald-700" title="Hubungi melalui WhatsApp"><svg class="h-4 w-4 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z" /></svg></a><a v-if="record.email" :href="formatEmailUrl(record)" class="rounded p-1.5 text-slate-600 transition-colors hover:bg-slate-100 hover:text-sky-700" title="Kirim email"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 00-2-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg></a></div></td>
                                        </tr>
                                        <tr v-if="selectedRecords.length === 0"><td colspan="5" class="px-3 py-6 text-center text-slate-500">Tidak ada responden yang membentuk pilihan ini.</td></tr>
                                    </tbody>
                                </table>
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
                                <table class="min-w-[1080px] w-full divide-y divide-slate-200 text-left text-xs">
                                    <thead><tr class="bg-slate-100 font-semibold text-slate-700"><th class="w-12 px-3 py-3 text-center">No</th><th class="px-3 py-3">Nama Alumni</th><th class="px-3 py-3">NIM</th><th class="px-3 py-3">Tahun Lulus</th><th class="px-3 py-3 min-w-[300px]">Pertanyaan</th><th class="px-3 py-3 min-w-[190px]">Jawaban</th><th class="px-3 py-3 min-w-[190px]">Isian Lainnya</th><th class="px-3 py-3 text-center">Skor</th></tr></thead>
                                    <tbody class="divide-y divide-slate-100 bg-white">
                                        <tr v-if="paginatedRecords.length === 0"><td colspan="8" class="px-3 py-8 text-center text-slate-500">Tidak ada data responden yang sesuai.</td></tr>
                                        <tr v-for="(row, index) in paginatedRecords" :key="row.response_id || index" class="transition-colors hover:bg-slate-50">
                                            <td class="px-3 py-2.5 text-center font-mono text-slate-400">{{ (currentPage - 1) * rowsPerPage + index + 1 }}</td><td class="px-3 py-2.5 font-semibold text-slate-800">{{ row.nama }}</td><td class="px-3 py-2.5 font-mono text-slate-700">{{ row.nim }}</td><td class="px-3 py-2.5 text-slate-600">{{ row.tahun_lulus }}</td><td class="px-3 py-2.5 leading-5 text-slate-700">{{ row.aspek }}</td><td class="px-3 py-2.5 font-medium leading-5 text-slate-800">{{ row.jawaban_label || '-' }}</td><td class="px-3 py-2.5 leading-5 text-slate-700">{{ row.jawaban_lainnya || '-' }}</td><td class="px-3 py-2.5 text-center font-bold text-slate-700">{{ row.skor ?? '-' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <!-- Pagination data responden mengikuti kontrol bersama. -->
                            <PaginationControls
                                :total="filteredRecords.length"
                                :current-page="currentPage"
                                :page-size="rowsPerPage"
                                summary-label="jawaban"
                                @update:current-page="currentPage = $event"
                            />
                        </template>
                        <ProdiPivotTable v-else :pivot="pivotRows" />
                    </section>
                </template>

                <!-- Metodologi mengikuti pola collapsible Grafik Universitas agar rumus lengkap tersedia tanpa mengganggu pembacaan grafik. -->
                <details v-if="hasData" id="prodi-analytics-methodology" :open="methodologyOpen" class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6" @toggle="methodologyOpen = $event.target.open">
                    <summary class="flex cursor-pointer select-none items-center justify-between gap-3">
                        <div>
                            <span class="text-xs font-extrabold uppercase tracking-wider text-[#0D542B]">Sumber dan metodologi perhitungan</span>
                            <p class="mt-0.5 text-xs text-slate-500">Rumus, penyebut, dan sumber tabel untuk visualisasi {{ chart.title }}.</p>
                        </div>
                        <span class="rounded border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ methodologyOpen ? 'Sembunyikan' : 'Tampilkan Rincian' }}</span>
                    </summary>

                    <div class="mt-5 space-y-5 border-t border-slate-100 pt-5">
                        <div class="grid gap-4 lg:grid-cols-12">
                            <section class="rounded-lg border border-emerald-200/80 bg-emerald-50/30 p-4 lg:col-span-7">
                                <p class="text-xs font-extrabold uppercase tracking-wider text-[#0D542B]">Rumus Perhitungan Utama</p>
                                <p class="mt-2 rounded-md border border-emerald-200/60 bg-white p-3 font-mono text-xs font-semibold leading-relaxed text-slate-900 sm:text-sm">{{ chart.methodology?.formula || '-' }}</p>
                                <div class="mt-3 grid gap-3 text-xs sm:grid-cols-2"><div><span class="font-bold text-slate-700">Denominator (Pembagi):</span><p class="mt-0.5 leading-relaxed text-slate-600">{{ chart.methodology?.denominator || '-' }}</p></div><div><span class="font-bold text-slate-700">Basis Persentase / Skala:</span><p class="mt-0.5 leading-relaxed text-slate-600">{{ chart.metadata?.percentage_basis || '-' }}</p></div></div>
                            </section>
                            <section class="flex flex-col justify-between space-y-3 rounded-lg border border-slate-200 bg-slate-50/70 p-4 text-xs lg:col-span-5"><div><p class="font-extrabold uppercase tracking-wider text-slate-700">Tipe Statistik &amp; Missing Data</p><p class="mt-1.5 leading-relaxed text-slate-600"><strong class="text-slate-900">{{ chart.metadata?.data_type || '-' }}.</strong> Sebanyak <strong class="text-slate-900">{{ formatNumber(chart.metadata?.missing_count, 0) }} alumni</strong> pada populasi terpilih belum masuk perhitungan.</p></div><div class="border-t border-slate-200/80 pt-2.5"><p class="font-bold text-slate-700">Ringkasan Sumber Data:</p><p class="mt-1 break-words font-mono text-[11px] leading-relaxed text-slate-600">{{ chart.methodology?.source || '-' }}</p></div><p class="border-t border-slate-200/80 pt-2 text-[11px] text-slate-500">Waktu kompilasi: <span class="font-semibold text-slate-700">{{ chart.metadata?.generated_at ? new Date(chart.metadata.generated_at).toLocaleString('id-ID') : '-' }}</span></p></section>
                        </div>

                        <section v-if="chart.methodology?.charts?.length" class="space-y-2.5"><h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-800">Rumus per butir visualisasi ({{ chart.methodology.charts.length }} butir)</h3><div class="grid gap-3 md:grid-cols-2"><article v-for="subChart in chart.methodology.charts" :key="subChart.key" class="rounded-lg border border-slate-200 bg-slate-50/50 p-3.5"><div class="flex items-center justify-between gap-2"><p class="text-xs font-extrabold text-slate-900">{{ subChart.title }}</p><span class="rounded border border-emerald-200 bg-emerald-50 px-2 py-0.5 font-mono text-[10px] font-bold text-[#0D542B]">{{ subChart.key }}</span></div><p class="mt-2 rounded border border-slate-200 bg-white px-2.5 py-2 font-mono text-xs leading-relaxed text-slate-800">{{ subChart.formula }}</p><p class="mt-2 text-[11px] text-slate-500"><span class="font-bold text-slate-700">Sumber:</span> <span class="font-mono text-slate-600">{{ subChart.source }}</span></p></article></div></section>

                        <section v-if="chart.methodology?.tables?.length" class="space-y-2.5"><h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-800">Daftar Tabel Database &amp; Kolom Pembentuk</h3><div class="overflow-x-auto rounded-lg border border-slate-200"><table class="min-w-[760px] w-full divide-y divide-slate-200 text-left text-xs"><thead class="bg-slate-50 font-bold text-slate-700"><tr><th class="w-48 px-3.5 py-2.5">Tabel</th><th class="w-80 px-3.5 py-2.5">Kolom</th><th class="px-3.5 py-2.5">Peran</th></tr></thead><tbody class="divide-y divide-slate-100 bg-white"><tr v-for="table in chart.methodology.tables" :key="table.table" class="hover:bg-slate-50/70"><td class="px-3.5 py-2 font-mono font-bold text-[#0D542B]">{{ table.table }}</td><td class="px-3.5 py-2 font-mono text-slate-700">{{ table.columns }}</td><td class="px-3.5 py-2 text-slate-600">{{ table.role }}</td></tr></tbody></table></div></section>
                    </div>
                </details>
            </div>
        </main>
    </div>
</template>
