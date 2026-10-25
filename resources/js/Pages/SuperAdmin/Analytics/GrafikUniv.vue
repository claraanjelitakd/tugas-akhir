<script setup>
/**
 * Halaman Business Intelligence Grafik Universitas memuat satu indikator per
 * permintaan, memperbarui data melalui HTTP JSON, dan mempertahankan konteks
 * eksplorasi pada tab Analisis, Grafik, serta Data.
 */
import { computed, defineAsyncComponent, nextTick, ref, watch } from 'vue';
import { Head, Link, useHttp } from '@inertiajs/vue3';
import Sidebar from '../Components/Sidebar.vue';

// Memuat panel visualisasi ECharts secara terpisah (lazy loading) agar halaman dan filter tampil lebih cepat.
const AnalyticsChartsPanel = defineAsyncComponent(() => import('@/components/analytics/AnalyticsChartsPanel.vue'));

// Data awal yang dikirim dari TracerAnalyticsController@index melalui Inertia (berisi user & payload analytics).
const props = defineProps({
    user: Object,
    analytics: {
        type: Object,
        default: () => ({ filters: {}, chart_options: [], selected_chart: 'response_rate', chart: {} }),
    },
});

// Menyimpan data analytics aktif di memori Vue, otomatis diperbarui saat user mengubah filter tanpa reload halaman.
const currentAnalytics = ref(props.analytics);

// Client HTTP dari Inertia v3 untuk mengambil data JSON secara asinkron dari endpoint /superadmin/analitik/data.
const analyticsRequest = useHttp({});

// Menyimpan pesan kesalahan jika request pengambilan data JSON gagal atau koneksi jaringan terputus.
const loadError = ref('');

// ID timer penunda (debounce) agar tidak mengirim request berulang saat dropdown/filter diklik beruntun.
let filterTimer = null;

// Tab panel yang sedang aktif di tampilan: 'insights' (Analisis), 'charts' (Grafik), atau 'records' (Data Responden).
const activePanel = ref('charts');

// Menyimpan elemen grafik yang diklik user untuk keperluan cross-filtering (misal: memilih kategori tertentu).
const selectedItem = ref(null);

// Menyimpan objek provinsi yang diklik user pada peta interaktif MapLibre.
const selectedProvince = ref(null);

// Mengatur status buka/tutup panel samping (Drawer) yang menampilkan rincian alumni hasil cross-filter.
const drawerOpen = ref(false);

// Mengatur visibilitas dan status buka/tutup bagian metodologi perhitungan indikator.
const methodologyOpen = ref(false);

// Nilai filter tahun lulusan aktif yang dipilih pengguna pada dropdown tahun (default: tahun aktif backend).
const selectedYear = ref(String(props.analytics?.filters?.selected_year || new Date().getFullYear()));

// Nilai filter tahun acuan UMP aktif (khusus digunakan pada indikator perbandingan gaji terhadap UMP).
const selectedUmpYear = ref(String(props.analytics?.filters?.selected_ump_year || ''));

// Nilai filter ID program studi aktif (kosong berarti mencakup seluruh universitas).
const selectedProdiId = ref(String(props.analytics?.filters?.selected_prodi_id || ''));

// Kunci indikator analitik aktif yang sedang ditampilkan (misal: 'wait_time', 'salary_vs_ump', 'response_rate').
const selectedChartKey = ref(String(props.analytics?.selected_chart || 'response_rate'));

// Kata kunci pencarian nama atau NIM alumni pada tab tabel data responden.
const tableSearch = ref('');

// Indeks kolom yang sedang digunakan sebagai dasar pengurutan data tabel responden.
const sortColumn = ref(0);

// Arah pengurutan tabel: 'asc' untuk urut naik (A-Z) atau 'desc' untuk urut turun (Z-A).
const sortDirection = ref('asc');

// Nomor halaman aktif pada paginasi tabel data responden.
const currentPage = ref(1);

// Batas jumlah baris data per halaman pada tabel responden (default: 20 baris).
const rowsPerPage = ref(20);

// Objek data indikator yang sedang aktif (berisi ringkasan summary, series grafik, dan records detail).
const chart = computed(() => currentAnalytics.value?.chart || {});

// Daftar seluruh opsi indikator analitik universitas yang tersedia dari backend.
const chartOptions = computed(() => currentAnalytics.value?.chart_options || []);

// Kunci indikator terpilih yang dikonfirmasi aktif oleh backend.
const selectedChart = computed(() => currentAnalytics.value?.selected_chart || 'response_rate');

// Daftar data responden individu untuk indikator aktif, bersumber dari database view backend.
const records = computed(() => chart.value.records || []);

// Status ketersediaan data indikator aktif (membedakan antara data tersedia vs belum ada jawaban valid).
const dataAvailability = computed(() => chart.value.data_availability || {
    status: records.value.length ? 'available' : 'no_valid_answers',
    population_count: 0,
    record_count: records.value.length,
    message: 'Belum ada data valid.',
});

// Menentukan apakah indikator aktif memiliki data valid yang siap ditampilkan ke layar.
const hasChartData = computed(() => dataAvailability.value.status === 'available');

// Daftar 16 kunci indikator wajib laporan Tracer Study universitas yang disusun berurutan.
const mandatoryChartKeys = [
    'response_rate',
    'learning_methods',
    'job_search_start',
    'job_search_methods',
    'wait_time',
    'application_pipeline',
    'employment_status',
    'job_search_activity',
    'workplace_type',
    'further_study',
    'salary_vs_ump',
    'field_alignment',
    'education_alignment',
    'education_alignment_university',
    'mismatch_reasons',
    'competency_gap',
];

// Opsi dropdown 16 indikator laporan resmi dengan nomor urut 1 sampai 16.
const mandatoryOptions = computed(() => mandatoryChartKeys
    .map((key, index) => {
        const option = chartOptions.value.find((item) => item.key === key);
        return option ? { ...option, number: index + 1 } : null;
    })
    .filter(Boolean));

// Opsi dropdown untuk indikator tambahan di luar 16 laporan resmi (Profil Alumni dan Wilayah Kerja).
const additionalOptions = computed(() => chartOptions.value.filter((option) => !mandatoryChartKeys.includes(option.key)));

// Fungsi pemformat angka numerik dengan pemisah ribuan standar Indonesia (titik).
const formatNumber = (value, digits = 1) => Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: digits });

// Fungsi pemformat angka persentase dengan akhiran simbol persen (contoh: '85.4%').
const formatPercentage = (value) => `${formatNumber(value)}%`;

// Fungsi pemformat angka menjadi format mata uang Rupiah penuh (contoh: 'Rp 6.500.000').
const formatCurrency = (value) => value === null || value === undefined || value === ''
    ? '-'
    : new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value));

// Mengambil nilai kategori tunggal dari suatu record untuk sinkronisasi cross-filter lintas visualisasi.
const recordCategory = (record) => record.answer || record.status || record.comparison || record.method || record.competency || record.kode_pertanyaan || '';

// Menyaring daftar records responden alumni berdasarkan interaksi klik pada grafik, petak treemap, atau wilayah peta.
const selectedRecords = computed(() => {
    let result = records.value;
    const selection = selectedItem.value;

    if (selectedProvince.value) {
        result = result.filter((record) => String(record.province_code || '') === String(selectedProvince.value.province_code || ''));
    }

    if (!selection?.label) return result;
    if (selection.meets !== undefined) return result.filter((record) => Boolean(record.meets_ump) === Boolean(selection.meets));
    if (selection.min !== undefined) {
        return result.filter((record) => Number(record.months) >= Number(selection.min)
            && Number(record.months) <= Number(selection.max ?? Number.POSITIVE_INFINITY));
    }

    const label = String(selection.label);
    const matchingCategory = result.filter((record) => recordCategory(record) === label);
    if (matchingCategory.length) return matchingCategory;

    const matchingPerson = result.filter((record) => record.nama === label || String(record.biodata_id || '') === String(selection.biodata_id || ''));
    if (matchingPerson.length) return matchingPerson;

    const matchingProdi = result.filter((record) => record.nama_prodi === label);
    return matchingProdi.length ? matchingProdi : result;
});

// KPI ringkas menyesuaikan statistik aktual indikator aktif.
const kpiCards = computed(() => {
    const summary = chart.value.summary || {};

    if (chart.value.type === 'salary') {
        return [
            { label: 'Median gaji', value: formatCurrency(summary.median_salary), note: `${formatNumber(summary.comparable_count, 0)} data dapat dibandingkan` },
            { label: 'Memenuhi 1,2 × UMP', value: formatPercentage(summary.meeting_percentage), note: `${formatNumber(summary.meeting_count, 0)} alumni memenuhi` },
            { label: 'Belum memenuhi', value: formatNumber(summary.below_count, 0), note: 'Alumni di bawah ambang provinsi' },
            { label: 'Belum dapat dibandingkan', value: formatNumber(summary.not_comparable_count, 0), note: 'Gaji, perusahaan, atau UMP belum lengkap' },
        ];
    }

    if (chart.value.type === 'wait_time') {
        return [
            { label: 'Median waktu tunggu', value: `${formatNumber(summary.median_months)} bulan`, note: `${formatNumber(summary.response_count, 0)} respon valid` },
            { label: 'Kuartil bawah', value: `${formatNumber(summary.p25_months)} bulan`, note: '25% alumni berada pada atau di bawah nilai ini' },
            { label: 'Kuartil atas', value: `${formatNumber(summary.p75_months)} bulan`, note: '75% alumni berada pada atau di bawah nilai ini' },
            { label: 'Rentang antarkuartil', value: `${formatNumber(Number(summary.p75_months || 0) - Number(summary.p25_months || 0))} bulan`, note: 'Sebaran 50% data tengah' },
        ];
    }

    if (chart.value.type === 'response_rate') {
        return [
            { label: 'Populasi kohort', value: formatNumber(summary.population, 0), note: 'Alumni pada filter aktif' },
            { label: 'Responden', value: formatNumber(summary.respondents, 0), note: 'Minimal satu jawaban wajib' },
            { label: 'Response rate', value: formatPercentage(summary.response_rate), note: 'Responden dibagi populasi kohort' },
        ];
    }

    if (chart.value.type === 'competency') {
        return [
            { label: 'Responden valid', value: formatNumber(summary.answered_count, 0), note: 'Alumni dalam perhitungan gap' },
            { label: 'Gap terbesar', value: summary.largest_gap_label || summary.leading_category || '-', note: summary.largest_gap === undefined ? 'Lihat perbandingan pada grafik' : `Selisih ${formatNumber(summary.largest_gap, 2)}` },
            { label: 'Jumlah dimensi', value: formatNumber(chart.value.series?.length, 0), note: 'Kompetensi yang dibandingkan' },
        ];
    }

    return [
        { label: 'Responden valid', value: formatNumber(summary.answered_count ?? summary.response_count ?? records.value.length, 0), note: 'Data yang masuk perhitungan' },
        { label: 'Kategori dominan', value: summary.leading_category || chart.value.series?.[0]?.label || '-', note: summary.leading_percentage === undefined ? 'Berdasarkan nilai tertinggi' : `${formatPercentage(summary.leading_percentage)} dari respon valid` },
        { label: 'Jumlah kategori', value: formatNumber(chart.value.series?.length, 0), note: 'Kategori tersedia pada indikator' },
    ];
});

// Insight otomatis disusun dari hasil perhitungan aktual, bukan teks statis umum.
const automaticInsights = computed(() => {
    const summary = chart.value.summary || {};
    const insights = [];

    if (chart.value.type === 'salary') {
        insights.push(`${formatPercentage(summary.meeting_percentage)} dari alumni yang dapat dibandingkan telah mencapai ambang 1,2 kali UMP.`);
        insights.push(`${formatNumber(summary.below_count, 0)} alumni masih berada di bawah ambang dan perlu ditelusuri berdasarkan prodi, perusahaan, serta wilayah kerja.`);
        insights.push(`Rentang gaji valid berada pada ${formatCurrency(summary.lowest_salary)} sampai ${formatCurrency(summary.highest_salary)}, dengan median ${formatCurrency(summary.median_salary)}.`);
        return insights;
    }

    if (chart.value.type === 'wait_time') {
        const faster = records.value.filter((record) => record.comparison === 'Lebih cepat').length;
        const slower = records.value.filter((record) => record.comparison === 'Lebih lama').length;
        insights.push(`Median waktu memperoleh pekerjaan adalah ${formatNumber(summary.median_months)} bulan dengan rentang tengah ${formatNumber(summary.p25_months)}–${formatNumber(summary.p75_months)} bulan.`);
        insights.push(`${formatNumber(faster, 0)} alumni lebih cepat dari median dan ${formatNumber(slower, 0)} alumni lebih lama dari median.`);
        return insights;
    }

    if (chart.value.type === 'response_rate') {
        insights.push(`Response rate universitas pada filter aktif adalah ${formatPercentage(summary.response_rate)} dari ${formatNumber(summary.population, 0)} alumni dalam kohort.`);
        const lowest = [...(chart.value.series || [])].sort((left, right) => Number(left.response_rate || 0) - Number(right.response_rate || 0))[0];
        if (lowest) insights.push(`${lowest.nama_prodi || lowest.label} memiliki response rate terendah, yaitu ${formatPercentage(lowest.response_rate)}.`);
        return insights;
    }

    if (summary.leading_category) insights.push(`${summary.leading_category} menjadi kategori terbesar dengan proporsi ${formatPercentage(summary.leading_percentage)}.`);
    insights.push(`${formatNumber(summary.answered_count ?? summary.response_count ?? records.value.length, 0)} responden valid menjadi dasar perhitungan indikator ini.`);
    insights.push('Perbandingan antarprogram studi dapat diperiksa pada grafik komposisi dan heatmap yang telah dinormalisasi.');
    return insights;
});

// Menentukan daftar nama kolom header tabel data responden secara dinamis berdasarkan tipe indikator aktif.
const tableColumns = computed(() => {
    if (chart.value.type === 'salary') return ['Nama', 'NIM', 'Program studi', 'Perusahaan', 'Provinsi', 'Gaji', 'Ambang 1,2 × UMP', 'Status'];
    if (chart.value.type === 'wait_time') return ['Nama', 'NIM', 'Program studi', 'Perusahaan', 'Bulan', 'Perbandingan'];
    if (chart.value.type === 'location') return ['Nama', 'NIM', 'Program studi', 'Perusahaan', 'Provinsi kerja'];
    if (chart.value.type === 'application_pipeline') return ['Nama', 'NIM', 'Program studi', 'Tahap', 'Jumlah'];
    if (chart.value.type === 'learning_methods') return ['Nama', 'NIM', 'Program studi', 'Metode', 'Skor'];
    if (chart.value.type === 'competency') return ['Nama', 'NIM', 'Program studi', 'Kompetensi', 'Saat lulus', 'Dibutuhkan', 'Gap'];
    return ['Nama', 'NIM', 'Program studi', 'Jawaban'];
});

// Mengubah satu objek record responden menjadi array nilai sel yang sejajar dengan urutan header tabel.
const tableCells = (record) => {
    if (chart.value.type === 'salary') return [record.nama, record.nim, record.nama_prodi, record.nama_perusahaan, record.province_name, formatCurrency(record.salary), formatCurrency(record.threshold), record.meets_ump ? 'Memenuhi' : 'Belum memenuhi'];
    if (chart.value.type === 'wait_time') return [record.nama, record.nim, record.nama_prodi, record.nama_perusahaan, `${formatNumber(record.months)} bulan`, record.comparison];
    if (chart.value.type === 'location') return [record.nama, record.nim, record.nama_prodi, record.nama_perusahaan, record.province_name];
    if (chart.value.type === 'response_rate') return [record.nama, record.nim, record.nama_prodi, record.answered ? 'Mengisi' : 'Belum mengisi'];
    if (chart.value.type === 'application_pipeline') return [record.nama, record.nim, record.nama_prodi, record.kode_pertanyaan, formatNumber(record.value)];
    if (chart.value.type === 'learning_methods') return [record.nama, record.nim, record.nama_prodi, record.method, formatNumber(record.score, 2)];
    if (chart.value.type === 'competency') return [record.nama, record.nim, record.nama_prodi, record.competency, formatNumber(record.graduation_score, 2), formatNumber(record.need_score, 2), formatNumber(record.gap, 2)];
    return [record.nama, record.nim, record.nama_prodi, record.answer || record.status];
};

// Menghasilkan daftar seluruh baris tabel responden setelah melalui pencarian kata kunci dan pengurutan (sorting).
const tableRows = computed(() => {
    const query = tableSearch.value.trim().toLowerCase();
    const rows = selectedRecords.value
        .map((record) => ({ record, cells: tableCells(record) }))
        .filter((row) => !query || row.cells.some((cell) => String(cell ?? '').toLowerCase().includes(query)));

    return rows.sort((left, right) => {
        const leftValue = left.cells[sortColumn.value] ?? '';
        const rightValue = right.cells[sortColumn.value] ?? '';
        const comparison = String(leftValue).localeCompare(String(rightValue), 'id', { numeric: true });
        return sortDirection.value === 'asc' ? comparison : -comparison;
    });
});

// Menghitung jumlah total halaman tabel berdasarkan total baris dan batas data per halaman.
const pageCount = computed(() => Math.max(Math.ceil(tableRows.value.length / Number(rowsPerPage.value)), 1));

// Mengambil potongan baris data khusus untuk nomor halaman yang sedang aktif saat ini (paginasi).
const paginatedRows = computed(() => tableRows.value.slice((currentPage.value - 1) * Number(rowsPerPage.value), currentPage.value * Number(rowsPerPage.value)));

// Menghitung rincian sebaran responden per prodi dari data yang tersaring, ditampilkan pada panel samping Drawer.
const selectionBreakdown = computed(() => Object.entries(selectedRecords.value.reduce((groups, record) => {
    const key = record.nama_prodi || 'Tanpa prodi';
    groups[key] = (groups[key] || 0) + 1;
    return groups;
}, {})).map(([label, count]) => ({ label, count })).sort((left, right) => right.count - left.count));

// Membangun URL endpoint JSON dengan parameter filter aktif untuk pengambilan data indikator secara asinkron.
const analyticsDataUrl = (chartKey = selectedChartKey.value) => {
    const query = new URLSearchParams({ grafik: chartKey, tahun: selectedYear.value });
    if (selectedUmpYear.value) query.set('tahun_ump', selectedUmpYear.value);
    if (selectedProdiId.value) query.set('prodi_id', selectedProdiId.value);
    return `/superadmin/analitik/data?${query.toString()}`;
};

// Memperbarui URL di address bar browser tanpa reload agar kondisi filter dapat disalin atau dibagikan.
const syncBrowserUrl = (chartKey) => {
    const url = analyticsDataUrl(chartKey).replace('/analitik/data', '/analitik');
    window.history.replaceState(window.history.state, '', url);
};

// Mengambil data indikator baru secara asinkron dari backend dan memperbarui state halaman tanpa reload.
const loadAnalytics = async (chartKey = selectedChartKey.value) => {
    analyticsRequest.cancel();
    loadError.value = '';

    await analyticsRequest.get(analyticsDataUrl(chartKey), {
        onSuccess: (data) => {
            currentAnalytics.value = data;
            selectedChartKey.value = data.selected_chart;
            selectedYear.value = String(data.filters?.selected_year || selectedYear.value);
            selectedUmpYear.value = String(data.filters?.selected_ump_year || selectedUmpYear.value);
            selectedItem.value = null;
            selectedProvince.value = null;
            drawerOpen.value = false;
            currentPage.value = 1;
            syncBrowserUrl(data.selected_chart);
        },
        onHttpException: (response) => {
            selectedChartKey.value = selectedChart.value;
            loadError.value = `Data analitik gagal dimuat (HTTP ${response.status}).`;
        },
        onNetworkError: () => {
            selectedChartKey.value = selectedChart.value;
            loadError.value = 'Koneksi terputus saat memuat data analitik.';
        },
    });
};

// Menjadwalkan pengambilan data dengan jeda waktu singkat (debounce) agar tidak mengirim request berlebihan.
const scheduleLoad = (chartKey = selectedChartKey.value) => {
    window.clearTimeout(filterTimer);
    filterTimer = window.setTimeout(() => loadAnalytics(chartKey), 240);
};

// Menangani pergantian indikator dari dropdown pilihan grafik dan memicu pengambilan data baru.
const changeChart = (event) => {
    selectedChartKey.value = event.target.value;
    activePanel.value = 'charts';
    scheduleLoad(selectedChartKey.value);
};

// Menangani aksi klik pada elemen data grafik untuk menyaring data responden dan membuka panel Drawer.
const selectSeriesItem = (item) => {
    if (!item?.label) {
        selectedItem.value = null;
        drawerOpen.value = false;
        return;
    }
    selectedItem.value = item;
    drawerOpen.value = true;
};

// Menangani aksi klik pada wilayah provinsi di peta interaktif MapLibre untuk menerapkan filter geografis.
const selectProvince = (province) => {
    selectedProvince.value = selectedProvince.value?.province_code === province.province_code ? null : province;
    selectedItem.value = selectedProvince.value ? { label: province.province_name } : null;
    drawerOpen.value = Boolean(selectedProvince.value);
};

// Mengalihkan tampilan ke tab Data Responden dengan tetap mempertahankan filter yang sedang aktif.
const showDataPanel = () => {
    activePanel.value = 'data';
    drawerOpen.value = false;
};

// Membuka bagian metodologi perhitungan indikator dan melakukan scroll halus ke elemen tersebut.
const showMethodology = async () => {
    methodologyOpen.value = true;
    await nextTick();
    document.getElementById('analytics-methodology')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
};

// Sorting kolom membalik arah jika header yang sama dipilih lagi.
const sortTable = (columnIndex) => {
    if (sortColumn.value === columnIndex) sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    else {
        sortColumn.value = columnIndex;
        sortDirection.value = 'asc';
    }
    currentPage.value = 1;
};

// Reset filter mengembalikan prodi ke seluruh universitas tanpa mengubah indikator.
const resetGlobalFilters = () => {
    selectedProdiId.value = '';
    selectedUmpYear.value = String(currentAnalytics.value?.filters?.available_ump_years?.[0] || selectedYear.value);
    scheduleLoad();
};

watch([tableSearch, rowsPerPage], () => { currentPage.value = 1; });
watch(pageCount, () => { if (currentPage.value > pageCount.value) currentPage.value = pageCount.value; });
</script>

<template>
    <Head title="Grafik Universitas - Tracer Study" />

    <div class="min-h-screen bg-[#F5F7F8] text-slate-900 lg:flex">
        <Sidebar :user="user" active-menu="analytics" />

        <main class="min-w-0 flex-1 lg:pl-72">
            <!-- Header ringkas menjaga fokus pada konteks universitas dan filter utama. -->
            <header class="bg-[#0D542B] px-5 pb-24 pt-7 text-white sm:px-8">
                <div class="mx-auto max-w-[1480px]">
                    <div class="flex items-center gap-2 text-xs text-emerald-100">
                        <Link href="/superadmin/dashboard" class="hover:text-white">Tracer Study</Link>
                        <span>/</span>
                        <span>Grafik Universitas</span>
                    </div>
                    <div class="mt-5 flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#FDC700]">Interactive analytics</p>
                            <h1 class="mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl">Grafik Universitas</h1>
                            <p class="mt-2 max-w-2xl text-sm leading-6 text-emerald-100">Eksplorasi indikator tracer study dengan denominator, metodologi, dan data pembentuk yang dapat ditelusuri.</p>
                        </div>
                        <p class="text-xs text-emerald-100">Sumber aktif: Kuesioner Universitas dan Profil Alumni</p>
                    </div>
                </div>
            </header>

            <div class="mx-auto -mt-16 max-w-[1480px] space-y-5 px-5 pb-8 sm:px-8">
                <!-- Global filter bar memperbarui data tanpa reload halaman penuh. -->
                <section class="bg-white p-5 shadow-lg shadow-slate-900/5">
                    <div class="grid gap-4 xl:grid-cols-[minmax(18rem,2fr)_10rem_minmax(13rem,1fr)_auto] xl:items-end">
                        <label class="block min-w-0 text-xs font-bold uppercase tracking-wide text-slate-500">
                            Indikator analisis
                            <select :value="selectedChartKey" class="mt-2 block w-full rounded bg-slate-100 px-3 py-3 text-sm font-bold normal-case text-slate-900 outline-none focus:bg-white focus:ring-2 focus:ring-[#0D542B]" @change="changeChart">
                                <optgroup label="16 indikator evaluasi wajib">
                                    <option v-for="option in mandatoryOptions" :key="option.key" :value="option.key">{{ String(option.number).padStart(2, '0') }} · {{ option.label }}</option>
                                </optgroup>
                                <optgroup v-if="additionalOptions.length" label="Analitik tambahan">
                                    <option v-for="option in additionalOptions" :key="option.key" :value="option.key">{{ option.label }}</option>
                                </optgroup>
                            </select>
                        </label>
                        <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">
                            Tahun lulusan
                            <select v-model="selectedYear" class="mt-2 block w-full rounded bg-[#FDC700] px-3 py-3 text-sm font-extrabold normal-case text-[#0D542B] outline-none" @change="scheduleLoad()">
                                <option v-for="year in currentAnalytics.filters?.available_years || []" :key="year" :value="String(year)">{{ year }}</option>
                            </select>
                        </label>
                        <label class="block min-w-0 text-xs font-bold uppercase tracking-wide text-slate-500">
                            Program studi
                            <select v-model="selectedProdiId" class="mt-2 block w-full rounded bg-slate-100 px-3 py-3 text-sm font-bold normal-case text-slate-900 outline-none focus:bg-white focus:ring-2 focus:ring-[#0D542B]" @change="scheduleLoad()">
                                <option value="">Semua program studi</option>
                                <option v-for="prodi in currentAnalytics.filters?.prodis || []" :key="prodi.id" :value="String(prodi.id)">{{ prodi.nama }}</option>
                            </select>
                        </label>
                        <button type="button" class="rounded bg-slate-100 px-4 py-3 text-sm font-bold text-slate-700 hover:bg-slate-200" @click="resetGlobalFilters">Reset filter</button>
                    </div>
                    <div v-if="analyticsRequest.processing || loadError" class="mt-4 border-t border-slate-100 pt-3 text-sm">
                        <p v-if="analyticsRequest.processing" class="font-semibold text-[#0D542B]">Memperbarui indikator tanpa memuat ulang halaman.</p>
                        <p v-if="loadError" class="font-semibold text-[#B42318]">{{ loadError }}</p>
                    </div>
                </section>

                <!-- Identitas indikator dan KPI menyediakan konteks sebelum visual eksploratif. -->
                <section class="bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase tracking-wide text-[#0D542B]">Indikator aktif</p>
                            <h2 class="mt-2 text-2xl font-extrabold text-slate-950">{{ chart.title }}</h2>
                            <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-500">{{ chart.subtitle }}</p>
                        </div>
                        <div class="flex items-center gap-2 text-xs font-semibold text-slate-500">
                            <span>{{ selectedYear }}</span>
                            <span>·</span>
                            <span>{{ selectedProdiId ? currentAnalytics.filters?.prodis?.find((item) => String(item.id) === selectedProdiId)?.nama : 'Seluruh program studi' }}</span>
                        </div>
                    </div>
                    <dl v-if="hasChartData" class="mt-6 grid gap-px overflow-hidden bg-slate-200 sm:grid-cols-2 xl:grid-cols-4">
                        <div v-for="card in kpiCards" :key="card.label" class="bg-white p-4 sm:p-5">
                            <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ card.label }}</dt>
                            <dd class="mt-2 text-2xl font-extrabold text-[#0D542B]">{{ card.value }}</dd>
                            <p class="mt-1 text-xs leading-5 text-slate-500">{{ card.note }}</p>
                        </div>
                    </dl>
                </section>

                <!-- Empty state membedakan penyebab kosong dan mempertahankan kontrol filter. -->
                <section v-if="!hasChartData" class="bg-white p-8 text-center shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-[#0D542B]">Status data indikator</p>
                    <h2 class="mt-2 text-xl font-extrabold">Data belum dapat divisualisasikan</h2>
                    <p class="mx-auto mt-2 max-w-2xl text-sm leading-6 text-slate-600">{{ dataAvailability.message }}</p>
                    <div class="mx-auto mt-5 grid max-w-lg grid-cols-2 gap-px bg-slate-200">
                        <div class="bg-slate-50 p-4"><p class="text-xs font-bold text-slate-500">Populasi terpilih</p><p class="mt-1 text-2xl font-extrabold text-[#0D542B]">{{ formatNumber(dataAvailability.population_count, 0) }}</p></div>
                        <div class="bg-slate-50 p-4"><p class="text-xs font-bold text-slate-500">Jawaban valid</p><p class="mt-1 text-2xl font-extrabold text-[#0D542B]">{{ formatNumber(dataAvailability.record_count, 0) }}</p></div>
                    </div>
                    <button type="button" class="mt-5 rounded bg-[#FDC700] px-4 py-2.5 text-sm font-bold text-[#0D542B]" @click="resetGlobalFilters">Reset filter</button>
                </section>

                <template v-else>
                    <!-- Navigasi tiga fungsi utama tidak mengulang judul dan metadata pada setiap tab. -->
                    <nav class="flex gap-1 bg-white p-1.5 shadow-sm" role="tablist" aria-label="Tampilan analitik">
                        <button v-for="panel in [{ key: 'analysis', label: 'Analisis' }, { key: 'charts', label: 'Grafik' }, { key: 'data', label: 'Data' }]" :key="panel.key" type="button" role="tab" :aria-selected="activePanel === panel.key" class="flex-1 rounded px-4 py-3 text-sm font-bold transition-colors" :class="activePanel === panel.key ? 'bg-[#0D542B] text-white' : 'text-slate-600 hover:bg-slate-100'" @click="activePanel = panel.key">{{ panel.label }}</button>
                    </nav>

                    <!-- Analisis otomatis berisi temuan aktual dan kelompok prioritas. -->
                    <section v-if="activePanel === 'analysis'" class="grid gap-5 xl:grid-cols-[1.35fr_.65fr]">
                        <article class="bg-white p-6 shadow-sm">
                            <p class="text-xs font-bold uppercase tracking-wide text-[#0D542B]">Temuan utama</p>
                            <h3 class="mt-2 text-xl font-extrabold">Interpretasi berdasarkan filter aktif</h3>
                            <ol class="mt-5 space-y-4">
                                <li v-for="(insight, index) in automaticInsights" :key="insight" class="grid grid-cols-[2rem_1fr] gap-3 text-sm leading-6 text-slate-700">
                                    <span class="flex h-8 w-8 items-center justify-center rounded bg-[#FDC700] font-extrabold text-[#0D542B]">{{ index + 1 }}</span>
                                    <span>{{ insight }}</span>
                                </li>
                            </ol>
                        </article>
                        <article class="bg-[#0D542B] p-6 text-white shadow-sm">
                            <p class="text-xs font-bold uppercase tracking-wide text-[#FDC700]">Tindak lanjut</p>
                            <h3 class="mt-2 text-xl font-extrabold">Arah eksplorasi berikutnya</h3>
                            <p class="mt-3 text-sm leading-6 text-emerald-100">Gunakan tab Grafik untuk memilih kategori atau kelompok alumni. Detail pilihan akan terbuka tanpa menghilangkan filter tahun dan program studi.</p>
                            <button type="button" class="mt-6 rounded bg-[#FDC700] px-4 py-2.5 text-sm font-bold text-[#0D542B]" @click="activePanel = 'charts'">Buka grafik interaktif</button>
                        </article>
                    </section>

                    <!-- Kombinasi ECharts merender grafik utama dan pendukung secara responsif. -->
                    <Suspense v-else-if="activePanel === 'charts'">
                        <AnalyticsChartsPanel
                            :chart="chart"
                            :selected-chart="selectedChart"
                            :available-ump-years="currentAnalytics.filters?.available_ump_years || []"
                            :selected-ump-year="selectedUmpYear"
                            :loading="analyticsRequest.processing"
                            @select-category="selectSeriesItem"
                            @select-province="selectProvince"
                            @change-ump-year="selectedUmpYear = String($event); scheduleLoad()"
                            @show-data="showDataPanel"
                            @show-methodology="showMethodology"
                        />
                        <template #fallback>
                            <section class="grid gap-5 xl:grid-cols-2">
                                <div class="h-[460px] animate-pulse bg-white shadow-sm xl:col-span-2"></div>
                                <div class="h-[390px] animate-pulse bg-white shadow-sm"></div>
                                <div class="h-[390px] animate-pulse bg-white shadow-sm"></div>
                            </section>
                        </template>
                    </Suspense>

                    <!-- Data table tetap tersedia sebagai lapisan detail dengan pencarian dan pagination. -->
                    <section v-else class="bg-white p-5 shadow-sm sm:p-6">
                        <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wide text-[#0D542B]">Detail data</p>
                                <h2 class="mt-1 text-xl font-extrabold">{{ formatNumber(tableRows.length, 0) }} baris sesuai filter</h2>
                                <p class="mt-1 text-sm text-slate-500">Data individu hanya ditampilkan kepada role yang berwenang.</p>
                            </div>
                            <div class="grid gap-2 sm:grid-cols-[minmax(14rem,22rem)_7rem]">
                                <label class="text-xs font-bold text-slate-500">Cari data
                                    <input v-model="tableSearch" type="search" class="mt-1 block w-full rounded bg-slate-100 px-3 py-2.5 text-sm text-slate-900 outline-none focus:ring-2 focus:ring-[#0D542B]" placeholder="Nama, NIM, prodi, perusahaan" />
                                </label>
                                <label class="text-xs font-bold text-slate-500">Per halaman
                                    <select v-model="rowsPerPage" class="mt-1 block w-full rounded bg-slate-100 px-3 py-2.5 text-sm font-bold outline-none">
                                        <option :value="10">10</option><option :value="20">20</option><option :value="50">50</option>
                                    </select>
                                </label>
                            </div>
                        </div>
                        <div v-if="selectedItem || selectedProvince" class="mt-4 flex flex-wrap items-center justify-between gap-2 bg-[#FFF7CC] p-3 text-sm text-[#0D542B]">
                            <span>Filter grafik: <strong>{{ selectedProvince?.province_name || selectedItem?.label }}</strong></span>
                            <button type="button" class="font-bold underline" @click="selectedItem = null; selectedProvince = null">Hapus filter</button>
                        </div>
                        <div class="mt-5 overflow-x-auto">
                            <table class="min-w-full text-left text-sm">
                                <thead><tr class="bg-slate-100"><th v-for="(column, index) in tableColumns" :key="column" class="whitespace-nowrap px-3 py-3"><button type="button" class="font-bold text-slate-700" @click="sortTable(index)">{{ column }} <span v-if="sortColumn === index">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span></button></th></tr></thead>
                                <tbody>
                                    <tr v-for="(row, index) in paginatedRows" :key="row.record.biodata_id ? `${row.record.biodata_id}-${index}` : index" class="border-b border-slate-100 hover:bg-slate-50"><td v-for="(cell, cellIndex) in row.cells" :key="cellIndex" class="whitespace-nowrap px-3 py-3 text-slate-600">{{ cell }}</td></tr>
                                    <tr v-if="!paginatedRows.length"><td :colspan="tableColumns.length" class="px-3 py-10 text-center text-slate-500">Tidak ada data yang cocok dengan pencarian dan filter aktif.</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4 flex items-center justify-between gap-3 text-sm text-slate-600">
                            <span>Halaman {{ currentPage }} dari {{ pageCount }}</span>
                            <div class="flex gap-2"><button type="button" class="rounded bg-slate-100 px-3 py-2 font-bold disabled:opacity-40" :disabled="currentPage <= 1" @click="currentPage--">Sebelumnya</button><button type="button" class="rounded bg-slate-100 px-3 py-2 font-bold disabled:opacity-40" :disabled="currentPage >= pageCount" @click="currentPage++">Berikutnya</button></div>
                        </div>
                    </section>
                </template>

                <!-- Metodologi dapat dibuka dari toolbar chart dan selalu berada pada indikator aktif. -->
                <details id="analytics-methodology" :open="methodologyOpen" class="bg-slate-200 p-5 sm:p-6" @toggle="methodologyOpen = $event.target.open">
                    <summary class="cursor-pointer text-sm font-extrabold uppercase tracking-wide text-[#0D542B]">Sumber dan metodologi perhitungan</summary>
                    <dl class="mt-4 grid gap-4 text-sm md:grid-cols-2 xl:grid-cols-5">
                        <div><dt class="font-bold text-slate-800">Rumus</dt><dd class="mt-1 leading-6 text-slate-600">{{ chart.methodology?.formula }}</dd></div>
                        <div><dt class="font-bold text-slate-800">Denominator</dt><dd class="mt-1 leading-6 text-slate-600">{{ chart.methodology?.denominator }}</dd></div>
                        <div><dt class="font-bold text-slate-800">Sumber data</dt><dd class="mt-1 break-words font-mono text-xs leading-6 text-slate-600">{{ chart.methodology?.source }}</dd></div>
                        <div><dt class="font-bold text-slate-800">Tipe dan missing data</dt><dd class="mt-1 leading-6 text-slate-600">{{ chart.metadata?.data_type }}. {{ formatNumber(chart.metadata?.missing_count, 0) }} alumni belum masuk perhitungan.</dd></div>
                        <div><dt class="font-bold text-slate-800">Pembaruan</dt><dd class="mt-1 leading-6 text-slate-600">Dihitung {{ chart.metadata?.generated_at ? new Date(chart.metadata.generated_at).toLocaleString('id-ID') : '-' }}.<br>{{ chart.metadata?.percentage_basis }}</dd></div>
                    </dl>
                </details>
            </div>
        </main>

        <!-- Drawer drill-down mempertahankan grafik di belakang dan memberi breadcrumb selection. -->
        <div v-if="drawerOpen" class="fixed inset-0 z-50 flex justify-end bg-slate-950/30" @click.self="drawerOpen = false">
            <aside class="h-full w-full max-w-xl overflow-y-auto bg-white p-5 shadow-2xl sm:p-7" aria-label="Detail selection grafik">
                <div class="flex items-start justify-between gap-4 border-b border-slate-200 pb-5">
                    <div>
                        <p class="text-xs font-bold text-slate-500">Semua data / {{ selectedProvince?.province_name || selectedItem?.label }}</p>
                        <h2 class="mt-2 text-2xl font-extrabold text-slate-950">Detail pilihan</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ formatNumber(selectedRecords.length, 0) }} record membentuk selection ini.</p>
                    </div>
                    <button type="button" class="rounded bg-slate-100 px-3 py-2 text-sm font-bold text-slate-700" @click="drawerOpen = false">Tutup</button>
                </div>
                <section class="mt-6">
                    <h3 class="text-sm font-extrabold uppercase tracking-wide text-[#0D542B]">Sebaran program studi</h3>
                    <div class="mt-3 space-y-2">
                        <div v-for="item in selectionBreakdown.slice(0, 8)" :key="item.label" class="flex items-center justify-between gap-3 bg-slate-50 px-3 py-2.5 text-sm"><span class="font-semibold text-slate-700">{{ item.label }}</span><span class="font-extrabold text-[#0D542B]">{{ formatNumber(item.count, 0) }}</span></div>
                    </div>
                </section>
                <section class="mt-7">
                    <h3 class="text-sm font-extrabold uppercase tracking-wide text-[#0D542B]">Contoh data pembentuk</h3>
                    <div class="mt-3 divide-y divide-slate-100">
                        <div v-for="record in selectedRecords.slice(0, 8)" :key="`${record.biodata_id}-${record.kode_pertanyaan || record.answer || record.status}`" class="py-3">
                            <p class="font-bold text-slate-900">{{ record.nama }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ record.nim }} · {{ record.nama_prodi }} · {{ recordCategory(record) || record.nama_perusahaan || record.province_name }}</p>
                        </div>
                        <p v-if="!selectedRecords.length" class="py-8 text-center text-sm text-slate-500">Tidak ada detail individu pada selection ini.</p>
                    </div>
                </section>
                <button type="button" class="mt-7 w-full rounded bg-[#0D542B] px-4 py-3 text-sm font-bold text-white" @click="showDataPanel">Buka seluruh data terfilter</button>
            </aside>
        </div>
    </div>
</template>
