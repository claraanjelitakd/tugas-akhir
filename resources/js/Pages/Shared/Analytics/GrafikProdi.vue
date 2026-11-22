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
import { generateAnalyticsPdf } from '@/utils/analyticsPdfExporter';

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

// Mengambil kode program studi yang sesuai dengan login peran atau data instrumen.
const defaultProdiId = (payload) => {
    if (props.user?.role === 'admin_prodi' && props.user?.kode_prodi) {
        return String(props.user.kode_prodi);
    }
    const prodis = payload?.filters?.available_prodis || [];
    return String(
        payload?.filters?.selected_kode_prodi
        || prodis.find((prodi) => String(prodi.kode_prodi) === '72')?.kode_prodi
        || prodis.find((prodi) => prodi.has_questions)?.kode_prodi
        || prodis[0]?.kode_prodi
        || '',
    );
};

// ID prodi aktif yang dikirim ke endpoint JSON.
const selectedProdiId = ref(String(props.analytics?.filters?.selected_kode_prodi || defaultProdiId(props.analytics)));
// ID bagian pertanyaan aktif yang dikirim ke endpoint JSON.
const selectedSectionId = ref(String(props.analytics?.filters?.selected_section_id || ''));
// Tahun lulusan aktif yang dikirim ke endpoint JSON.
const selectedYear = ref(String(props.analytics?.filters?.selected_year || 'ALL'));

// Kunci penyimpanan tab analitik agar pilihan Grafik/Data konsisten lintas indikator.
const ANALYTICS_PANEL_STORAGE_KEY = 'tracer.analytics.active-panel';

// Membaca tab terakhir dengan fallback aman untuk SSR atau browser tanpa localStorage.
const storedAnalyticsPanel = () => {
    if (typeof window === 'undefined') return 'charts';

    const storedPanel = window.localStorage.getItem(ANALYTICS_PANEL_STORAGE_KEY);
    return ['charts', 'pivot', 'data'].includes(storedPanel) ? storedPanel : 'charts';
};

// Panel utama mengikuti Grafik Universitas: Grafik, Pivot, atau Data.
const activePanel = ref(storedAnalyticsPanel());

// Menyimpan tab aktif agar pergantian bagian dan program studi tidak mengubah konteks pengguna.
watch(activePanel, (panel) => {
    if (typeof window !== 'undefined') {
        window.localStorage.setItem(ANALYTICS_PANEL_STORAGE_KEY, panel);
    }
});
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

// State pencarian dan paginasi untuk tabel drill-down responden inline di bawah grafik.
const drillSearch = ref('');
// Nomor halaman aktif untuk drill-down inline.
const drillCurrentPage = ref(1);
// Batas baris per halaman untuk drill-down inline (default: 10).
const drillRowsPerPage = ref(10);

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
            { label: 'Rata-rata Skor (Mean)', value: `${summary.value.average_score || 0} / 5`, note: 'Rata-rata seluruh skor valid (skala 1-5)' },
            { label: 'Tingkat Kepuasan', value: `${summary.value.satisfaction_rate || 0}%`, note: 'Persentase skor 4 (Setuju) dan 5 (Sangat Setuju)' },
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
            kode_prodi: selectedProdiId.value,
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

// Mengembalikan filter ke program studi aktif, bagian default, dan tahun berjalan.
const resetFilters = () => {
    selectedProdiId.value = (props.user?.role === 'admin_prodi' && props.user?.kode_prodi)
        ? String(props.user.kode_prodi)
        : defaultProdiId(currentAnalytics.value);
    selectedSectionId.value = '';
    selectedYear.value = defaultYear();
    selectedItem.value = null;
    activeQuestionContext.value = null;
    tableSearch.value = '';
    drillSearch.value = '';
    scheduleLoad();
};

// Menangani klik grafik dan menyimpan konteks pertanyaan/kategori yang dipilih.
const handleChartSelect = (selection) => {
    selectedItem.value = selection;
    drillCurrentPage.value = 1;
    drillSearch.value = '';
};

// Menangani klik filter kategori dari tabel pivot interaktif
const handlePivotSelectCategory = (selection) => {
    selectedItem.value = {
        ...selection,
        fromPivot: true,
        item: {
            key: selection.code,
            title: selection.questionText,
            type: selection.type,
        },
    };
    activePanel.value = 'charts';
    drillCurrentPage.value = 1;
    drillSearch.value = '';
};

// Menyimpan tab pertanyaan aktif agar kartu ringkasan selalu ikut berganti.
const handleQuestionContext = (item) => {
    activeQuestionContext.value = item || null;
};

// Menghapus filter drill-down agar grafik kembali ke ringkasan seluruh bagian.
const clearSelection = () => {
    selectedItem.value = null;
    drillCurrentPage.value = 1;
    drillSearch.value = '';
};

// Teks ringkasan fokus analisis yang sedang dipilih
const selectedItemHeadline = computed(() => {
    if (!selectedItem.value) return '';
    const sel = selectedItem.value;
    const isChoice = sel.item?.type === 'choice'
        || ['choice', 'single_choice', 'multiple_choice', 'select'].includes(sel.type);

    if (isChoice) {
        return `Pilihan: "${sel.label}" pada butir "${sel.item?.title || sel.questionText || ''}"`;
    }
    if (sel.fromPivot && sel.type === 'rating_5') {
        return `Butir: "${sel.questionText || sel.item?.title || ''}" (Skor ${sel.value} · ${sel.label})`;
    }
    return `Butir: "${sel.item?.title || sel.label}" (Rata-rata: ${sel.value ? Number(sel.value).toFixed(2) : '-'} / 5)`;
});

// Memberikan styling badge highlight yang sangat jelas dan kontras tinggi sesuai nilai skor atau opsi pilihan
const getAnswerBadge = (record) => {
    if (record.skor !== null && record.skor !== undefined && !isNaN(Number(record.skor))) {
        const score = Number(record.skor);
        if (score >= 4) {
            return {
                class: 'bg-[#DCFCE7] text-[#065F46] border border-[#86EFAC] font-bold',
                label: `Skor ${score} · ${record.jawaban_label || (score === 5 ? 'Sangat Setuju' : 'Setuju')}`,
            };
        }
        if (score === 3) {
            return {
                class: 'bg-slate-100 text-slate-700 border border-slate-300 font-semibold',
                label: `Skor 3 · ${record.jawaban_label || 'Netral'}`,
            };
        }
        return {
            class: 'bg-[#FFE4E6] text-[#9F1239] border border-[#FECDD3] font-bold',
            label: `Skor ${score} · ${record.jawaban_label || (score === 1 ? 'Sangat Tidak Setuju' : 'Tidak Setuju')}`,
        };
    }

    return {
        class: 'bg-[#F0FDF4] text-[#0D542B] border border-[#A7F3D0] font-bold',
        label: record.jawaban_label || '-',
    };
};

// Membatasi rows pada pertanyaan/kategori yang diklik pada grafik atau pivot.
const selectedRecords = computed(() => {
    const selection = selectedItem.value;
    if (!selection) return records.value;

    const code = selection.item?.key || selection.code;
    if (!code) return records.value;

    let questionRecords = records.value.filter((record) => String(record.kode) === String(code));

    // Jika filter spesifik dari pivot table Likert (klik baris skor 1-5):
    if (selection.fromPivot && selection.type === 'rating_5' && selection.value !== undefined && selection.value !== null) {
        const targetScore = Number(selection.value);
        return questionRecords.filter((record) => Number(record.skor) === targetScore);
    }

    const isChoice = selection.item?.type === 'choice'
        || ['choice', 'single_choice', 'multiple_choice', 'select'].includes(selection.type);

    if (isChoice && selection.label) {
        const targetLabel = String(selection.label).toLowerCase().trim();
        return questionRecords.filter((record) => {
            const answer = String(record.jawaban_label || '').toLowerCase();
            return answer.split(',').some((val) => val.trim() === targetLabel);
        });
    }

    return questionRecords;
});

// Responden drill-down untuk tampilan panel grafik inline
const drillRecords = computed(() => {
    return selectedItem.value ? selectedRecords.value : records.value;
});

// Menyaring data responden drill-down berdasarkan kata kunci pencarian inline
const filteredDrillRecords = computed(() => {
    const source = drillRecords.value;
    const query = drillSearch.value.trim().toLowerCase();
    if (!query) return source;

    return source.filter((record) => [
        record.nama,
        record.nim,
        record.tahun_lulus,
        record.aspek,
        record.jawaban_label,
        record.jawaban_lainnya,
        record.nama_perusahaan,
        record.posisi_jabatan,
        record.divisi,
        record.bidang_pekerjaan,
        record.skills,
        record.kode,
    ].some((value) => String(value || '').toLowerCase().includes(query)));
});

// Paginasi tabel drill-down inline di bawah grafik
const drillTotalPages = computed(() => Math.max(1, Math.ceil(filteredDrillRecords.value.length / drillRowsPerPage.value)));
const paginatedDrillRecords = computed(() => {
    const start = (drillCurrentPage.value - 1) * drillRowsPerPage.value;
    return filteredDrillRecords.value.slice(start, start + drillRowsPerPage.value);
});

// Menyaring tabel tab Data berdasarkan kata kunci pencarian.
const filteredRecords = computed(() => {
    const source = selectedItem.value ? selectedRecords.value : records.value;
    const query = tableSearch.value.trim().toLowerCase();
    if (!query) return source;

    return source.filter((record) => [
        record.nama,
        record.nim,
        record.tahun_lulus,
        record.aspek,
        record.jawaban_label,
        record.jawaban_lainnya,
        record.nama_perusahaan,
        record.posisi_jabatan,
        record.divisi,
        record.bidang_pekerjaan,
        record.skills,
        record.kode,
    ].some((value) => String(value || '').toLowerCase().includes(query)));
});

// Membuat halaman tabel tab Data lokal tanpa mengubah jumlah data yang dihitung backend.
const totalPages = computed(() => Math.max(1, Math.ceil(filteredRecords.value.length / rowsPerPage.value)));
const paginatedRecords = computed(() => {
    const start = (currentPage.value - 1) * rowsPerPage.value;
    return filteredRecords.value.slice(start, start + rowsPerPage.value);
});

// Menghasilkan dan mengunduh berkas laporan PDF analitik program studi lengkap dengan grafik dan tabel data
const printProdiReport = () => {
    const canvas = document.querySelector('main figure canvas, main canvas');
    const chartBase64 = canvas ? canvas.toDataURL('image/png') : null;

    const headers = ['No', 'Nama Alumni', 'NIM', 'Pertanyaan / Aspek', 'Jawaban'];
    const rows = (filteredRecords.value || []).map((rec, idx) => [
        idx + 1,
        rec.nama || '-',
        rec.nim || '-',
        rec.aspek || rec.kode || '-',
        rec.jawaban_label || '-',
    ]);

    const titleText = chart.value?.title || 'Analisis Kuesioner Khusus Program Studi';
    const prodiText = currentProdi.value?.nama_prodi || 'Program Studi';
    const cohortYearText = selectedYear.value === 'ALL' ? 'Semua Angkatan' : selectedYear.value;
    const summaryNarrative = summary.value?.narrative || summary.value?.keterangan || chart.value?.insights?.headline || '';

    generateAnalyticsPdf({
        title: titleText,
        subtitle: `Hasil Analisis Kuesioner Program Studi ${prodiText}`,
        cohortYear: cohortYearText,
        scopeName: prodiText,
        summaryText: summaryNarrative,
        chartImageBase64: chartBase64,
        tableHeaders: headers,
        tableRows: rows,
        fileName: `Laporan_Analisis_Prodi_${prodiText.replace(/[^a-zA-Z0-9_-]/g, '_')}`,
    });
};

// Mengunduh canvas grafik aktif sebagai berkas gambar PNG
const handleDownloadProdiChart = () => {
    const canvas = document.querySelector('main figure canvas, main canvas');
    if (canvas) {
        const link = document.createElement('a');
        const safeTitle = `grafik-prodi-${chart.value?.title || 'analitik'}`.toLowerCase().replace(/[^a-z0-9]+/g, '-');
        link.download = `${safeTitle}.png`;
        link.href = canvas.toDataURL('image/png');
        link.click();
    }
};

// Menyesuaikan halaman ketika hasil pencarian menjadi lebih pendek.
watch([tableSearch, rowsPerPage], () => { currentPage.value = 1; });
watch(totalPages, () => {
    if (currentPage.value > totalPages.value) currentPage.value = totalPages.value;
});

// Menyesuaikan halaman drill-down ketika kata kunci atau ukuran baris berubah.
watch([drillSearch, drillRowsPerPage], () => { drillCurrentPage.value = 1; });
watch(drillTotalPages, () => {
    if (drillCurrentPage.value > drillTotalPages.value) drillCurrentPage.value = drillTotalPages.value;
});

// Menyelaraskan filter lokal setelah server mengembalikan payload baru.
watch(() => props.analytics, (newAnalytics) => {
    currentAnalytics.value = newAnalytics;
    selectedProdiId.value = String(newAnalytics?.filters?.selected_kode_prodi || defaultProdiId(newAnalytics));
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
                            <select
                                v-model="selectedProdiId"
                                :disabled="props.user?.role === 'admin_prodi'"
                                class="mt-2 block w-full rounded bg-slate-100 px-3 py-3 text-sm font-bold normal-case text-slate-900 outline-none focus:bg-white focus:ring-2 focus:ring-[#0D542B] disabled:cursor-not-allowed disabled:bg-slate-200"
                                @change="handleProdiChange"
                            >
                                <option
                                    v-for="prodi in filters.available_prodis || []"
                                    :key="prodi.kode_prodi"
                                    :value="String(prodi.kode_prodi)"
                                >
                                    {{ prodi.nama_prodi }}
                                </option>
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

                    <!-- Kontainer Terpadu: Header, Bilah Aksi, Grafik & Data Responden -->
                    <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                        <!-- Header Terpadu: Judul di Kiri, Bilah Aksi Terpadu di Kanan (Tepat di Dalam Kartu) -->
                        <div class="border-b border-slate-100 pb-4 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="min-w-0 flex-1">
                                <template v-if="activePanel === 'charts'">
                                    <p class="text-xs font-bold uppercase tracking-wider text-[#0D542B]">Visualisasi Grafik</p>
                                    <h2 class="mt-1 text-xl font-extrabold text-slate-900">{{ chart.title }}</h2>
                                    <p class="mt-1 text-xs leading-5 text-slate-500">{{ chart.caption }}</p>
                                    <!-- Skala Likert dijelaskan di dekat grafik agar tidak hilang di kartu header. -->
                                    <p v-if="chart.type === 'likert'" class="mt-2 rounded border border-slate-200 bg-slate-50 px-3 py-2 text-xs leading-5 text-slate-600">
                                        Skala Penilaian: 1 = Sangat Tidak Setuju, 2 = Tidak Setuju, 3 = Netral, 4 = Setuju, 5 = Sangat Setuju.
                                    </p>
                                </template>
                                <template v-else-if="activePanel === 'pivot'">
                                    <p class="text-xs font-bold uppercase tracking-wider text-[#0D542B]">Pivot Tabulasi Pertanyaan</p>
                                    <h2 class="mt-1 text-xl font-extrabold text-slate-900">Distribusi Jawaban per Butir Instrumen</h2>
                                    <p class="mt-1 text-xs text-slate-500">Tabulasi distribusi frekuensi, persentase, dan rata-rata skor seluruh pertanyaan pada bagian aktif.</p>
                                </template>
                                <template v-else>
                                    <p class="text-xs font-bold uppercase tracking-wider text-[#0D542B]">Detail Data Responden</p>
                                    <h2 class="mt-1 text-xl font-extrabold text-slate-900">{{ formatNumber(filteredRecords.length, 0) }} baris data sesuai filter</h2>
                                    <p class="mt-1 text-xs text-slate-500">Data individual terhubung langsung dengan jawaban yang membentuk grafik.</p>
                                </template>
                            </div>

                            <!-- Bilah Aksi Terpadu: Grafik | Pivot | Data | Unduh Grafik | Laporan PDF -->
                            <div class="flex flex-wrap items-center gap-2 self-start lg:self-center shrink-0">
                                <div class="flex items-center gap-1 rounded-lg bg-slate-100 p-1 border border-slate-200 text-xs no-print">
                                    <button
                                        type="button"
                                        class="rounded-md px-3 py-1.5 font-bold transition-all cursor-pointer whitespace-nowrap"
                                        :class="activePanel === 'charts' ? 'bg-white text-slate-900 shadow-xs border border-slate-200/80' : 'text-slate-600 hover:text-slate-900'"
                                        @click="activePanel = 'charts'"
                                    >
                                        Grafik
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-md px-3 py-1.5 font-bold transition-all cursor-pointer whitespace-nowrap"
                                        :class="activePanel === 'pivot' ? 'bg-white text-slate-900 shadow-xs border border-slate-200/80' : 'text-slate-600 hover:text-slate-900'"
                                        @click="activePanel = 'pivot'"
                                    >
                                        Pivot
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-md px-3 py-1.5 font-bold transition-all cursor-pointer whitespace-nowrap"
                                        :class="activePanel === 'data' ? 'bg-white text-slate-900 shadow-xs border border-slate-200/80' : 'text-slate-600 hover:text-slate-900'"
                                        @click="activePanel = 'data'"
                                    >
                                        Data
                                    </button>
                                    <span class="mx-0.5 h-3.5 w-px bg-slate-300"></span>
                                    <button
                                        type="button"
                                        class="rounded-md px-3 py-1.5 font-medium text-slate-700 hover:bg-white hover:text-[#0D542B] transition-all cursor-pointer whitespace-nowrap"
                                        @click="handleDownloadProdiChart"
                                        title="Unduh gambar grafik saat ini (PNG)"
                                    >
                                        Unduh Grafik
                                    </button>
                                    <span class="mx-0.5 h-3.5 w-px bg-slate-300"></span>
                                    <button
                                        type="button"
                                        class="rounded-md px-3 py-1.5 font-medium text-slate-700 hover:bg-white hover:text-[#0D542B] transition-all cursor-pointer whitespace-nowrap"
                                        @click="printProdiReport"
                                        title="Unduh laporan analisis lengkap prodi ini (PDF)"
                                    >
                                        Laporan PDF
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Panel grafik menampilkan visualisasi, analisis, dan drill-down inline. -->
                        <div v-if="activePanel === 'charts'" class="mt-5 space-y-6">
                            <div class="min-w-0">
                                <ProdiAnalyticsChartsPanel :items="visualizations" :section-order="selectedSectionOrder" :chart-type="chart.type" @select="handleChartSelect" @context-change="handleQuestionContext" />
                                <p class="mt-3 border-t border-slate-100 pt-3 text-xs leading-5 text-slate-500">
                                    <span class="font-semibold text-[#0D542B]">Petunjuk:</span>
                                    Klik batang atau kategori grafik untuk memfilter rincian responden secara langsung di bawah ini.
                                </p>
                            </div>

                            <!-- Pilihan sudah memiliki analisis di bawah grafik; tipe lain memakai Kilas global. -->
                            <section v-if="chart.type !== 'choice'" class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                                <p class="text-xs font-bold uppercase tracking-wider text-[#0D542B]">Kilas Analisis</p>
                                <p class="mt-2 text-sm font-semibold leading-6 text-slate-900">{{ chart.insights?.headline }}</p>
                                <ul class="mt-3 space-y-1.5 text-xs leading-5 text-slate-600">
                                    <li v-for="(point, index) in chart.insights?.points || []" :key="index">{{ point }}</li>
                                </ul>
                            </section>

                            <!-- Seamless Inline Context: Rincian Data Responden Drill-Down Lengkap ala Response Rate -->
                            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs">
                                <!-- Header Banner Fokus Analisis / Status Drill-Down -->
                                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center border-b border-slate-100 pb-4">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold uppercase tracking-wider" :class="selectedItem ? 'bg-emerald-100 text-[#0D542B]' : 'bg-slate-100 text-slate-700'">
                                                {{ selectedItem ? 'Fokus Analisis Terpilih' : 'Seluruh Responden Bagian Aktif' }}
                                            </span>
                                            <span class="text-xs font-semibold text-slate-500">
                                                {{ formatNumber(filteredDrillRecords.length, 0) }} responden
                                            </span>
                                        </div>
                                        <h3 class="mt-1.5 text-base font-extrabold text-slate-900 break-words">
                                            {{ selectedItem ? selectedItemHeadline : chart.title }}
                                        </h3>
                                        <p class="mt-1 text-xs text-slate-500">
                                            {{ selectedItem ? 'Menampilkan alumni yang membentuk nilai/pilihan ini dengan highlight jawaban yang dipilih.' : 'Klik segmen grafik di atas atau butir pada pivot untuk memfokuskan data responden tertentu.' }}
                                        </p>
                                    </div>
                                    <div v-if="selectedItem" class="shrink-0">
                                        <button
                                            type="button"
                                            class="rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 shadow-xs transition-colors hover:bg-slate-50 hover:text-slate-900 cursor-pointer"
                                            @click="clearSelection"
                                        >
                                            Tampilkan Seluruh Responden
                                        </button>
                                    </div>
                                </div>

                                <!-- Toolbar Pencarian & Filter Per-Halaman Drill-Down -->
                                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="w-full max-w-md">
                                        <input
                                            v-model="drillSearch"
                                            type="search"
                                            class="block w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2 text-xs text-slate-900 outline-none focus:border-[#0D542B] focus:bg-white focus:ring-1 focus:ring-[#0D542B]"
                                            placeholder="Cari nama, NIM, pekerjaan, atau jawaban..."
                                        />
                                    </div>
                                    <label class="flex items-center gap-2 text-xs text-slate-500 self-end sm:self-auto">
                                        Per halaman:
                                        <select v-model="drillRowsPerPage" class="rounded-lg border border-slate-300 bg-slate-50 px-2.5 py-1.5 text-xs font-semibold outline-none focus:border-[#0D542B]">
                                            <option :value="10">10</option>
                                            <option :value="20">20</option>
                                            <option :value="50">50</option>
                                        </select>
                                    </label>
                                </div>

                                <!-- Tabel Responden Drill-Down Lengkap -->
                                <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200">
                                    <table class="min-w-[1080px] w-full divide-y divide-slate-200 text-left text-xs">
                                        <thead>
                                            <tr class="bg-slate-100 font-semibold text-slate-700">
                                                <th class="w-12 px-3 py-3 text-center">No</th>
                                                <th class="px-3 py-3 whitespace-nowrap">NIM</th>
                                                <th class="px-3 py-3 min-w-[160px]">Nama Alumni</th>
                                                <th class="px-3 py-3 text-center whitespace-nowrap">Tahun Lulus</th>
                                                <th class="px-3 py-3 min-w-[220px]">Pekerjaan &amp; Perusahaan</th>
                                                <th class="px-3 py-3 min-w-[220px]">Pertanyaan / Aspek</th>
                                                <th class="px-3 py-3 min-w-[200px]">Highlight Jawaban Terpilih</th>
                                                <th class="px-3 py-3 min-w-[180px]">Isian Lainnya</th>
                                                <th class="px-3 py-3 text-center w-28 whitespace-nowrap">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 bg-white">
                                            <tr v-if="paginatedDrillRecords.length === 0">
                                                <td colspan="9" class="px-3 py-8 text-center text-slate-500">
                                                    Tidak ada data responden yang sesuai dengan kriteria pencarian.
                                                </td>
                                            </tr>
                                            <tr
                                                v-for="(record, index) in paginatedDrillRecords"
                                                :key="record.response_id || index"
                                                class="transition-colors hover:bg-slate-50"
                                            >
                                                <td class="px-3 py-2.5 text-center font-mono text-slate-400">
                                                    {{ (drillCurrentPage - 1) * drillRowsPerPage + index + 1 }}
                                                </td>
                                                <td class="px-3 py-2.5 font-mono text-slate-700 whitespace-nowrap">
                                                    {{ record.nim }}
                                                </td>
                                                <td class="px-3 py-2.5 font-semibold text-slate-800 whitespace-nowrap">
                                                    {{ record.nama }}
                                                </td>
                                                <td class="px-3 py-2.5 text-center text-slate-600 whitespace-nowrap">
                                                    {{ record.tahun_lulus }}
                                                </td>
                                                <td class="px-3 py-2.5 align-top text-slate-700">
                                                    <div class="space-y-0.5 leading-snug">
                                                        <p class="font-bold text-slate-900">
                                                            {{ record.nama_perusahaan !== '-' ? record.nama_perusahaan : 'Belum Bekerja / Terdata' }}
                                                        </p>
                                                        <p v-if="record.posisi_jabatan && record.posisi_jabatan !== '-'" class="text-slate-600 font-medium">
                                                            {{ record.posisi_jabatan }}
                                                            <span v-if="record.divisi && record.divisi !== '-'" class="text-slate-400">· {{ record.divisi }}</span>
                                                        </p>
                                                        <p v-if="record.bidang_pekerjaan && record.bidang_pekerjaan !== '-'" class="text-[11px] text-slate-500">
                                                            Bidang: {{ record.bidang_pekerjaan }}
                                                        </p>
                                                    </div>
                                                </td>
                                                <td class="px-3 py-2.5 leading-5 text-slate-700">
                                                    {{ record.aspek }}
                                                </td>
                                                <td class="px-3 py-2.5 align-middle">
                                                    <!-- Highlight Jawaban Terpilih (High-Contrast Badge) -->
                                                    <span
                                                        class="inline-flex items-center px-2.5 py-1 rounded-md text-xs shadow-xs"
                                                        :class="getAnswerBadge(record).class"
                                                    >
                                                        {{ getAnswerBadge(record).label }}
                                                    </span>
                                                </td>
                                                <td class="px-3 py-2.5 leading-5 text-slate-700">
                                                    {{ record.jawaban_lainnya || '-' }}
                                                </td>
                                                <td class="px-3 py-2.5 text-center whitespace-nowrap">
                                                    <div class="inline-flex items-center justify-center gap-1">
                                                        <!-- Detail Profil Alumni -->
                                                        <Link
                                                            :href="detailUrl(record)"
                                                            class="p-1.5 text-slate-600 hover:text-[#0D542B] hover:bg-emerald-50 rounded-lg transition-colors cursor-pointer"
                                                            title="Lihat Profil & Detail Kuesioner Alumni"
                                                        >
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                                        </Link>
                                                        <!-- WhatsApp -->
                                                        <a
                                                            v-if="record.nomor_telepon"
                                                            :href="formatWhatsAppUrl(record)"
                                                            target="_blank"
                                                            class="p-1.5 text-slate-600 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg transition-colors cursor-pointer"
                                                            title="Hubungi via WhatsApp"
                                                        >
                                                            <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z" /></svg>
                                                        </a>
                                                        <!-- Email -->
                                                        <a
                                                            v-if="record.email"
                                                            :href="formatEmailUrl(record)"
                                                            class="p-1.5 text-slate-600 hover:text-[#0D542B] hover:bg-emerald-50 rounded-lg transition-colors cursor-pointer"
                                                            title="Kirim email"
                                                        >
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 00-2 2z" /></svg>
                                                        </a>
                                                        <!-- LinkedIn -->
                                                        <a
                                                            v-if="isValidLinkedInProfileUrl(record.linkedin_url)"
                                                            :href="record.linkedin_url"
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            class="p-1.5 text-slate-600 hover:text-[#0077B5] hover:bg-blue-50 rounded-lg transition-colors cursor-pointer"
                                                            title="Buka profil LinkedIn"
                                                        >
                                                            <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24" aria-hidden="true"><path d="M20.45 20.45h-3.56v-5.58c0-1.33-.03-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.68H9.34V9h3.42v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28zM5.32 7.43a2.06 2.06 0 1 1 0-4.12 2.06 2.06 0 0 1 0 4.12zM3.54 9h3.56v11.45H3.54V9z" /></svg>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Paginasi Drill-Down -->
                                <PaginationControls
                                    :total="filteredDrillRecords.length"
                                    :current-page="drillCurrentPage"
                                    :page-size="drillRowsPerPage"
                                    summary-label="responden"
                                    @update:current-page="drillCurrentPage = $event"
                                />
                            </section>
                        </div>

                        <!-- Panel Pivot: Tab Tabel Pivot Pertanyaan Dinamis & Interaktif -->
                        <div v-else-if="activePanel === 'pivot'" class="mt-5 space-y-4">
                            <ProdiPivotTable :pivot="pivotRows" @select-category="handlePivotSelectCategory" />
                        </div>

                        <!-- Panel Data: Tab Tabel Data Responden Lengkap -->
                        <div v-else class="mt-5 space-y-4">
                            <div v-if="selectedItem" class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3.5 py-2.5 text-xs text-emerald-900">
                                <span>Filter aktif: <strong>{{ selectedItemHeadline }}</strong></span>
                                <button type="button" class="font-bold underline cursor-pointer" @click="clearSelection">Hapus filter</button>
                            </div>
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <input v-model="tableSearch" type="search" class="block w-full max-w-md rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2 text-xs text-slate-900 outline-none focus:border-[#0D542B] focus:bg-white focus:ring-1 focus:ring-[#0D542B]" placeholder="Cari nama, NIM, pertanyaan, pekerjaan, atau jawaban..." />
                                <label class="flex items-center gap-2 text-xs text-slate-500">Per halaman:
                                    <select v-model="rowsPerPage" class="rounded-lg border border-slate-300 bg-slate-50 px-2.5 py-1.5 text-xs font-semibold outline-none focus:border-[#0D542B]"><option :value="10">10</option><option :value="20">20</option><option :value="50">50</option></select>
                                </label>
                            </div>
                            <div class="overflow-x-auto rounded-lg border border-slate-200">
                                <table class="min-w-[1080px] w-full divide-y divide-slate-200 text-left text-xs">
                                    <thead>
                                        <tr class="bg-slate-100 font-semibold text-slate-700">
                                            <th class="w-12 px-3 py-3 text-center">No</th>
                                            <th class="px-3 py-3 whitespace-nowrap">NIM</th>
                                            <th class="px-3 py-3 min-w-[160px]">Nama Alumni</th>
                                            <th class="px-3 py-3 text-center whitespace-nowrap">Tahun Lulus</th>
                                            <th class="px-3 py-3 min-w-[220px]">Pekerjaan &amp; Perusahaan</th>
                                            <th class="px-3 py-3 min-w-[220px]">Pertanyaan / Aspek</th>
                                            <th class="px-3 py-3 min-w-[200px]">Highlight Jawaban</th>
                                            <th class="px-3 py-3 min-w-[180px]">Isian Lainnya</th>
                                            <th class="px-3 py-3 text-center w-28 whitespace-nowrap">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white">
                                        <tr v-if="paginatedRecords.length === 0">
                                            <td colspan="9" class="px-3 py-8 text-center text-slate-500">Tidak ada data responden yang sesuai.</td>
                                        </tr>
                                        <tr v-for="(row, index) in paginatedRecords" :key="row.response_id || index" class="transition-colors hover:bg-slate-50">
                                            <td class="px-3 py-2.5 text-center font-mono text-slate-400">{{ (currentPage - 1) * rowsPerPage + index + 1 }}</td>
                                            <td class="px-3 py-2.5 font-mono text-slate-700 whitespace-nowrap">{{ row.nim }}</td>
                                            <td class="px-3 py-2.5 font-semibold text-slate-800 whitespace-nowrap">{{ row.nama }}</td>
                                            <td class="px-3 py-2.5 text-center text-slate-600 whitespace-nowrap">{{ row.tahun_lulus }}</td>
                                            <td class="px-3 py-2.5 align-top text-slate-700">
                                                <div class="space-y-0.5 leading-snug">
                                                    <p class="font-bold text-slate-900">{{ row.nama_perusahaan !== '-' ? row.nama_perusahaan : 'Belum Bekerja / Terdata' }}</p>
                                                    <p v-if="row.posisi_jabatan && row.posisi_jabatan !== '-'" class="text-slate-600 font-medium">{{ row.posisi_jabatan }} <span v-if="row.divisi && row.divisi !== '-'" class="text-slate-400">· {{ row.divisi }}</span></p>
                                                    <p v-if="row.bidang_pekerjaan && row.bidang_pekerjaan !== '-'" class="text-[11px] text-slate-500">Bidang: {{ row.bidang_pekerjaan }}</p>
                                                </div>
                                            </td>
                                            <td class="px-3 py-2.5 leading-5 text-slate-700">{{ row.aspek }}</td>
                                            <td class="px-3 py-2.5 align-middle">
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs shadow-xs" :class="getAnswerBadge(row).class">
                                                    {{ getAnswerBadge(row).label }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2.5 leading-5 text-slate-700">{{ row.jawaban_lainnya || '-' }}</td>
                                            <td class="px-3 py-2.5 text-center whitespace-nowrap">
                                                <div class="inline-flex items-center justify-center gap-1">
                                                    <Link :href="detailUrl(row)" class="p-1.5 text-slate-600 hover:text-[#0D542B] hover:bg-emerald-50 rounded-lg transition-colors cursor-pointer" title="Lihat Detail Alumni">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                                    </Link>
                                                    <a v-if="row.nomor_telepon" :href="formatWhatsAppUrl(row)" target="_blank" class="p-1.5 text-slate-600 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg transition-colors cursor-pointer" title="Hubungi via WhatsApp">
                                                        <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z" /></svg>
                                                    </a>
                                                    <a v-if="row.email" :href="formatEmailUrl(row)" class="p-1.5 text-slate-600 hover:text-[#0D542B] hover:bg-emerald-50 rounded-lg transition-colors cursor-pointer" title="Kirim email">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 00-2 2z" /></svg>
                                                    </a>
                                                    <a v-if="isValidLinkedInProfileUrl(row.linkedin_url)" :href="row.linkedin_url" target="_blank" rel="noopener noreferrer" class="p-1.5 text-slate-600 hover:text-[#0077B5] hover:bg-blue-50 rounded-lg transition-colors cursor-pointer" title="Buka LinkedIn">
                                                        <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24" aria-hidden="true"><path d="M20.45 20.45h-3.56v-5.58c0-1.33-.03-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.68H9.34V9h3.42v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28zM5.32 7.43a2.06 2.06 0 1 1 0-4.12 2.06 2.06 0 0 1 0 4.12zM3.54 9h3.56v11.45H3.54V9z" /></svg>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <PaginationControls
                                :total="filteredRecords.length"
                                :current-page="currentPage"
                                :page-size="rowsPerPage"
                                summary-label="responden"
                                @update:current-page="currentPage = $event"
                            />
                        </div>
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

<style>
@media print {
    header,
    nav,
    aside,
    button,
    .no-print,
    .lg\:pl-72 {
        padding-left: 0 !important;
    }
    aside,
    nav[role="tablist"],
    .no-print {
        display: none !important;
    }
    body, main {
        background-color: #ffffff !important;
        color: #000000 !important;
        font-size: 11pt !important;
    }
    .min-w-\[1280px\] {
        min-width: 100% !important;
    }
    .shadow-lg, .shadow-md, .shadow-sm, .shadow-2xs, .border {
        box-shadow: none !important;
        border-color: #e2e8f0 !important;
    }
    figure, table, section {
        break-inside: avoid;
        page-break-inside: avoid;
    }
}
</style>
