<script setup>
/**
 * Komponen Kontainer Terpadu dengan Navigasi Tab Visualisasi Analitik (Stay & Zero Layout Shift).
 *
 * Menggabungkan beberapa visualisasi grafik dari satu indikator ke dalam satu kontainer kokoh
 * dengan navigasi tab di kanan atas, analisis naratif data-driven di bawah grafik,
 * dan drill-down data inline seketika tanpa pergeseran tata letak (layout shift).
 */
import { computed, ref, watch } from 'vue';
import AnalyticsEChart from './AnalyticsEChart.vue';
import AnalyticsWordCloud from './AnalyticsWordCloud.vue';
import PaginationControls from '@/components/PaginationControls.vue';
import {
    analyticsChartTypeReasons,
    analyticsChartTypes,
    escapeHtml,
    formatFullCurrency,
    formatNumber,
    getAnalyticsChartHeight,
} from './analyticsChartOptions';
import { generateAnalyticsPdf } from '@/utils/analyticsPdfExporter';

// Kontrak properti kontainer terpadu
const props = defineProps({
    // Daftar tampilan visualisasi: [{ key, chartType, tabLabel, title, description, reason, option, tableData, height, analysis, isMap, tableOnly }]
    views: { type: Array, required: true },
    // Catatan record responden untuk drill-down inline
    rows: { type: Array, default: () => [] },
    // Label item atau kategori yang sedang aktif tersaring
    selectedLabel: { type: String, default: '' },
    // Status loading data
    loading: { type: Boolean, default: false },
    // Badge indikator (opsional, misal "Indikator 03")
    indicatorBadge: { type: String, default: '' },
    // Kunci tab default yang aktif pertama kali
    defaultTab: { type: String, default: '' },
});

// Event seleksi elemen, arah kursor (hover), dan reset filter
const emit = defineEmits(['select', 'reset', 'select-province', 'hover', 'hover-out']);

// State tab visualisasi yang sedang aktif
const activeTabKey = ref(props.defaultTab || props.views[0]?.key || '');

// Sinkronisasi jika defaultTab atau views berubah
watch(() => props.views, (newViews) => {
    if (!newViews.some((v) => v.key === activeTabKey.value)) {
        activeTabKey.value = newViews[0]?.key || '';
    }
}, { immediate: true });

// Kunci penyimpanan mode visualisasi agar Grafik/Data Ringkasan tetap konsisten lintas indikator.
const ANALYTICS_VIEW_MODE_STORAGE_KEY = 'tracer.analytics.view-mode';

// Membaca mode terakhir dengan fallback aman untuk SSR atau browser tanpa localStorage.
const storedAnalyticsViewMode = () => {
    if (typeof window === 'undefined') return 'chart';

    const storedMode = window.localStorage.getItem(ANALYTICS_VIEW_MODE_STORAGE_KEY);
    return ['chart', 'table'].includes(storedMode) ? storedMode : 'chart';
};

// Mode tampilan: 'chart' (grafik visual) atau 'table' (data ringkasan).
const activeViewMode = ref(storedAnalyticsViewMode());

// Menyimpan mode aktif agar pergantian grafik tidak mengembalikan pengguna ke mode lain.
watch(activeViewMode, (mode) => {
    if (typeof window !== 'undefined') {
        window.localStorage.setItem(ANALYTICS_VIEW_MODE_STORAGE_KEY, mode);
    }
});

// Objek view yang sedang aktif saat ini
const currentView = computed(() => {
    return props.views.find((v) => v.key === activeTabKey.value) || props.views[0] || {};
});

// Menandai view yang hanya menyediakan tabel tanpa visualisasi grafik.
const currentViewIsTableOnly = computed(() => currentView.value.tableOnly === true);

// Memilih tab dan langsung menyesuaikan mode tampilan sesuai kontrak view.
const selectView = (view) => {
    activeTabKey.value = view.key;
    if (view.tableOnly) {
        activeViewMode.value = 'table';
    }
};

// Menjaga view tabel-only tidak pernah kembali ke mode grafik.
watch(currentViewIsTableOnly, (isTableOnly) => {
    if (isTableOnly) activeViewMode.value = 'table';
}, { immediate: true });

// Label jenis grafik aktif memakai tipe eksplisit dari view dan kamus pusat.
const currentChartType = computed(() => analyticsChartTypes[currentView.value.chartType] || 'Visualisasi Analitik');

// Alasan pemilihan grafik memakai tipe eksplisit agar tidak bergantung pada nama key view.
const currentChartReason = computed(() => currentView.value.reason || currentView.value.analysis?.reason || analyticsChartTypeReasons[currentView.value.chartType] || 'Dipilih agar pola data pada indikator ini dapat dibandingkan secara langsung.');

// Tinggi grafik memakai preset pusat ketika view tidak menetapkan kebutuhan khusus.
const currentChartHeight = computed(() => currentView.value.height || getAnalyticsChartHeight(currentView.value.chartType));

// Pembersih label sumbu grafik dari anotasi jumlah sampel seperti "(n=15)"
const cleanChartLabel = (raw) => String(raw || '')
    .replace(/\s*\(n=\d+\)/gi, '')
    .replace(/\n+/g, ' ')
    .trim();

// State detail titik/elemen grafik yang sedang diklik
const selectedPoint = ref(null);

// Referensi komponen EChart untuk unduh berkas gambar PNG
const echartRef = ref(null);

const handleDownloadChart = () => {
    if (activeViewMode.value !== 'chart') {
        activeViewMode.value = 'chart';
    }
    setTimeout(() => {
        const rawTitle = currentView.value.title || currentView.value.tabLabel || 'grafik-analitik';
        const safeTitle = String(rawTitle).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
        echartRef.value?.downloadPng(safeTitle);
    }, 120);
};

const handleDownloadPdf = () => {
    const chartBase64 = echartRef.value?.getImageDataUrl ? echartRef.value.getImageDataUrl() : null;

    const headers = (activeColumns.value || []).map((col) => col.label || col.key);
    const rows = (activeRows.value || []).map((row) => {
        return (activeColumns.value || []).map((col) => {
            const val = row[col.key];
            if (val === null || val === undefined) return '-';
            return String(val);
        });
    });

    const summaryNarrative = [
        computedAnalysis.value?.title ? `${computedAnalysis.value.title}:` : '',
        computedAnalysis.value?.lead || '',
        computedAnalysis.value?.detail || '',
    ].filter(Boolean).join(' ');

    const rawTitle = currentView.value.title || currentView.value.tabLabel || 'Laporan Analisis Indikator';
    const cleanFileName = `Laporan_${String(rawTitle).replace(/[^a-zA-Z0-9_-]+/g, '_')}`;

    generateAnalyticsPdf({
        title: rawTitle,
        subtitle: currentView.value.description || '',
        summaryText: summaryNarrative,
        chartImageBase64: chartBase64,
        tableHeaders: headers,
        tableRows: rows,
        fileName: cleanFileName,
    });
};

// Menghasilkan dan mengunduh berkas CSV dengan BOM UTF-8 yang langsung kompatibel dengan Microsoft Excel
const exportToCsv = (columns, rows, filename) => {
    if (!columns?.length || !rows?.length) return;
    const headerRow = columns.map((col) => `"${String(col.label || col.key || '').replace(/"/g, '""')}"`).join(',');
    const dataRows = rows.map((row) => {
        return columns.map((col) => {
            let val = row[col.key];
            if (val === null || val === undefined) val = '';
            if (typeof val === 'object') val = JSON.stringify(val);
            return `"${String(val).replace(/"/g, '""')}"`;
        }).join(',');
    });
    const csvContent = '\uFEFF' + [headerRow, ...dataRows].join('\r\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', filename.endsWith('.csv') ? filename : `${filename}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
};

// Mengunduh data tabel ringkasan aktif ke berkas Excel (CSV)
const handleDownloadSummaryCsv = () => {
    const rawTitle = currentView.value.title || currentView.value.tabLabel || 'data-ringkasan';
    const safeTitle = String(rawTitle).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    exportToCsv(activeColumns.value, activeRows.value, `${safeTitle}-ringkasan.csv`);
};

// Mengunduh rincian data individu (mengikuti filter aktif) ke berkas Excel (CSV)
const handleDownloadDetailCsv = () => {
    const rawTitle = currentView.value.title || currentView.value.tabLabel || 'data-analitik';
    const safeTitle = String(rawTitle).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    const filterTag = activeSelectionLabel.value ? `-${activeSelectionLabel.value.toLowerCase().replace(/[^a-z0-9]+/g, '-')}` : '';

    const rowsToExport = filteredDetailRows.value.map((row) => {
        const mapped = {};
        for (const col of detailColumns.value) {
            mapped[col.key] = formatDetailValue(row[col.key], col.key);
        }
        return mapped;
    });

    exportToCsv(detailColumns.value, rowsToExport, `${safeTitle}${filterTag}-rincian.csv`);
};

// Unduh Excel terpadu dari toolbar atas
const handleDownloadExcel = () => {
    if (detailColumns.value.length > 0) {
        handleDownloadDetailCsv();
    } else if (activeColumns.value.length > 0) {
        handleDownloadSummaryCsv();
    }
};

// State elemen grafik yang sedang diarahkan oleh kursor mouse (hover dinamis)
const hoveredPoint = ref(null);

const handleChartHover = (params) => {
    const rawData = params?.data && typeof params.data === 'object' && !Array.isArray(params.data)
        ? params.data
        : {};
    const prodi = cleanChartLabel(rawData.prodi || '');
    const category = cleanChartLabel(rawData.category || rawData.target || '');
    const seriesName = cleanChartLabel(params?.seriesName || '');
    const rawLabel = rawData.category || rawData.target || rawData.prodi || rawData.name || params?.name || params?.seriesName || '';
    const label = cleanChartLabel(rawLabel);

    if (!label) return;

    hoveredPoint.value = {
        label,
        prodi,
        category,
        seriesName,
        value: params?.value ?? rawData.value ?? null,
        percentage: rawData.percentage ?? null,
        data: rawData,
    };
    emit('hover', hoveredPoint.value);
};

const handleChartHoverOut = () => {
    hoveredPoint.value = null;
    emit('hover-out');
};

// State filter kata kunci untuk visualisasi Word Cloud
const wordCloudKeyword = ref('');
const handleWordCloudSelect = (word) => {
    wordCloudKeyword.value = word;
};
const handleWordCloudReset = () => {
    wordCloudKeyword.value = '';
};

// Label seleksi aktif (gabungan prop induk dan state klik lokal)
const activeSelectionLabel = computed(() => {
    if (selectedPoint.value?.prodi && selectedPoint.value?.category && selectedPoint.value.prodi !== selectedPoint.value.category) {
        return `${selectedPoint.value.prodi} · ${selectedPoint.value.category}`;
    }
    return cleanChartLabel(props.selectedLabel || selectedPoint.value?.label || '');
});

// Sinkronisasi state klik lokal ketika selectedLabel dari komponen induk berubah
watch(() => props.selectedLabel, (newVal) => {
    const cleaned = cleanChartLabel(newVal);
    if (!cleaned) {
        selectedPoint.value = null;
        return;
    }
    if (cleanChartLabel(selectedPoint.value?.label) !== cleaned) {
        selectedPoint.value = {
            label: cleaned,
            prodi: '',
            category: '',
            seriesName: '',
            value: null,
            data: {},
        };
    }
});

// Reset seleksi grafik untuk kembali ke analisis keseluruhan ("All")
const resetSelection = () => {
    selectedPoint.value = null;
    globalQualFilter.value = 'all';
    globalCareerFilter.value = 'all';
    drillDownSearch.value = '';
    detailSearch.value = '';
    emit('reset');
};

// Penanganan klik pada elemen grafik EChart
const handleChartClick = (params) => {
    const rawData = params?.data && typeof params.data === 'object' && !Array.isArray(params.data)
        ? params.data
        : {};
    const prodi = cleanChartLabel(rawData.prodi || '');
    const category = cleanChartLabel(rawData.category || rawData.target || '');
    const seriesName = cleanChartLabel(params?.seriesName || '');
    const rawLabel = rawData.category || rawData.target || rawData.prodi || rawData.name || params?.name || params?.seriesName || '';
    const label = cleanChartLabel(rawLabel);

    if (!label) return;

    const sameSelection = cleanChartLabel(selectedPoint.value?.label) === label
        && cleanChartLabel(selectedPoint.value?.prodi) === prodi
        && cleanChartLabel(selectedPoint.value?.category) === category
        && cleanChartLabel(selectedPoint.value?.seriesName) === seriesName;

    if (sameSelection && props.selectedLabel) {
        resetSelection();
        return;
    }

    selectedPoint.value = {
        label,
        prodi,
        category,
        seriesName,
        value: params?.value ?? rawData.value ?? null,
        data: rawData,
    };

    emit('select', {
        ...rawData,
        name: label,
        label,
        prodi,
        category,
        seriesName: params?.seriesName || '',
        dataType: params?.dataType || '',
        value: params?.value ?? rawData.value,
    });
};

// Filter lokal pencarian pada tabel ringkasan
const tableSearch = ref('');
const sortColumn = ref(0);
const sortDirection = ref('asc');
const currentPage = ref(1);
const rowsPerPage = 10;

// Reset paginasi saat tab atau mode berganti
watch([activeTabKey, activeViewMode], () => {
    tableSearch.value = '';
    currentPage.value = 1;
});

// Kolom dan baris tabel data ringkasan aktif
const activeColumns = computed(() => currentView.value.tableData?.columns || []);

// Ringkasan pivot opsional ditampilkan sebelum tabel detail per alumni.
const summaryColumns = computed(() => currentView.value.tableData?.summary?.columns || []);
const summaryRows = computed(() => currentView.value.tableData?.summary?.rows || []);

// Label field detail memakai metadata yang stabil; nilai selalu berasal dari rows backend.
const detailFieldLabels = Object.freeze({
    nim: 'NIM',
    nama: 'Nama Alumni',
    nama_prodi: 'Program Studi',
    nama_fakultas: 'Fakultas',
    nama_perusahaan: 'Perusahaan',
    workplace_nama_provinsi: 'Provinsi',
    workplace_nama_kabupaten: 'Kabupaten/Kota',
    province_name: 'Provinsi',
    kabupaten: 'Kabupaten/Kota',
    provinsi: 'Provinsi',
    wilayah: 'Wilayah',
    degree: 'Jenjang Studi Lanjut',
    study_program: 'Program Studi Lanjut',
    university: 'Perguruan Tinggi Lanjut',
    funding_source: 'Sumber Pembiayaan',
    status: 'Status',
    category: 'Kategori',
    answer: 'Jawaban',
    jawaban: 'Jawaban',
    method: 'Metode',
    metode: 'Metode',
    months: 'Masa Tunggu (Bulan)',
    salary: 'Pendapatan/Gaji',
    ump: 'UMP',
    meets_ump: 'Status 1,2× UMP',
    weight: 'Bobot IKU',
    career_status_label: 'Status Karier',
    nama_perusahaan: 'Perusahaan / Kampus S2',
    posisi_jabatan: 'Jabatan / Posisi',
    lokasi_kerja: 'Lokasi Cabang / Kerja',
    nama_atasan: 'Atasan',
    skills: 'Skills',
    bidang_pekerjaan: 'Bidang Pekerjaan',
    divisi: 'Divisi',
    study_detail: 'Studi Lanjut',
    before_graduation: 'Sebelum Lulus',
    after_graduation: 'Setelah Lulus',
    threshold: 'Ambang 1,2× UMP',
    salary_comparison: 'Status Gaji vs 1,2× UMP',
    qualifies: 'Memenuhi IKU',
    ipk: 'IPK',
    f6: 'Perusahaan Dilamar (F6)',
    f7: 'Perusahaan Merespons (F7)',
    f7a: 'Undangan Wawancara (F7a)',
    dilamar: 'Perusahaan Dilamar (F6)',
    merespon: 'Perusahaan Merespons (F7)',
    wawancara: 'Undangan Wawancara (F7a)',
});

// Field teknis, kontak pribadi, atau field status duplikasi tidak ditampilkan pada tabel analitik.
const hiddenDetailFields = new Set([
    'id',
    'academic_id',
    'biodata_id',
    'user_id',
    'prodi_id',
    'fakultas_id',
    'pertanyaan_id',
    'section_id',
    'email',
    'email_pribadi',
    'user_email',
    'created_at',
    'updated_at',
    'salary_above_threshold',
    'career_category',
    'career_role',
    'status_karier',
    'employment_status',
    'role',
]);

// Menemukan seluruh kolom yang benar-benar tersedia pada records indikator aktif.
const detailColumns = computed(() => {
    const keys = [...new Set(props.rows.flatMap((row) => Object.keys(row || {})))];
    const orderedKeys = [
        ...Object.keys(detailFieldLabels),
        ...keys,
    ].filter((key, index, list) => list.indexOf(key) === index);

    return orderedKeys
        .filter((key) => keys.includes(key) && !hiddenDetailFields.has(key))
        .map((key) => ({
            key,
            label: detailFieldLabels[key] || key.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()),
        }));
});

// Menormalkan nilai detail dari payload JSON tanpa mengubah sumber datanya.
const formatDetailValue = (value, key = '') => {
    if (value === null || value === undefined || value === '') return '-';
    if (key === 'salary' || key === 'ump' || key === 'threshold') {
        const num = Number(value);
        return isNaN(num) ? String(value) : formatFullCurrency(num);
    }
    if (key === 'meets_ump') {
        if (value === null || value === undefined) return 'Belum dapat dibandingkan';
        return value ? 'Memenuhi (≥ 1,2× UMP)' : 'Di bawah 1,2× UMP';
    }
    if (key === 'salary_comparison' || value === 'above' || value === 'below' || value === 'unavailable') {
        if (value === 'above') return 'Di atas 1,2× UMP (Memenuhi)';
        if (value === 'below') return 'Di bawah 1,2× UMP';
        return 'Belum dapat dibandingkan';
    }
    if (key === 'qualifies' || key === 'before_graduation' || typeof value === 'boolean') {
        if (typeof value === 'boolean') return value ? 'Ya' : 'Tidak';
        if (value === 'true' || value === '1' || value === 1) return 'Ya';
        if (value === 'false' || value === '0' || value === 0) return 'Tidak';
    }
    if (key === 'months') {
        const num = Number(value);
        return isNaN(num) ? String(value) : `${formatNumber(num, 1)} bulan`;
    }
    if (key === 'weight') {
        const num = Number(value);
        return isNaN(num) ? String(value) : formatNumber(num, 2);
    }
    if (Array.isArray(value)) return value.join(', ');
    if (typeof value === 'object') return JSON.stringify(value);
    return String(value);
};

// Filter terpadu kelayakan IKU dan aktivitas/karier
const globalQualFilter = ref('all');
const globalCareerFilter = ref('all');

// Helper penentuan apakah satu record berstatus kombinasi (bekerja/wirausaha + studi lanjut)
const recordIsCombination = (record) => {
    const statusLabel = String(record?.career_status_label || record?.status_karier || '').toLowerCase();
    const hasStudy = Boolean(record?.study_detail) || statusLabel.includes('studi') || statusLabel.includes('pendidikan');
    const hasWork = Boolean(record?.nama_perusahaan) || Boolean(record?.posisi_jabatan) || statusLabel.includes('bekerja') || statusLabel.includes('karyawan') || statusLabel.includes('founder') || statusLabel.includes('freelance');
    return statusLabel.includes('+') || statusLabel.includes('kombinasi') || (hasStudy && hasWork);
};

// Evaluasi apakah record memenuhi kriteria filter aktif
const matchCriteria = (record) => {
    // 1. Filter Kelayakan IKU (Sudah Memenuhi vs Belum Memenuhi)
    if (globalQualFilter.value === 'qualified') {
        const isQualified = Number(record.weight || 0) > 0 || record.qualifies === true || record.qualifies === 'Ya';
        if (!isQualified) return false;
    } else if (globalQualFilter.value === 'unqualified') {
        const isUnqualified = Number(record.weight || 0) === 0 || record.qualifies === false || record.qualifies === 'Tidak';
        if (!isUnqualified) return false;
    }

    // 2. Filter Kategori Aktivitas
    if (globalCareerFilter.value !== 'all') {
        const cat = String(record.career_category || '').toLowerCase();
        const statusLabel = String(record.career_status_label || record.status_karier || '').toLowerCase();
        const isComb = recordIsCombination(record);

        if (globalCareerFilter.value === 'kombinasi') {
            if (!isComb) return false;
        } else if (globalCareerFilter.value === 'karyawan') {
            if (!cat.includes('karyawan') && !statusLabel.includes('karyawan') && !statusLabel.includes('bekerja')) return false;
        } else if (globalCareerFilter.value === 'founder') {
            if (!cat.includes('founder') && !statusLabel.includes('founder') && !statusLabel.includes('wirausaha') && !statusLabel.includes('wiraswasta')) return false;
        } else if (globalCareerFilter.value === 'freelancer') {
            if (!cat.includes('freelance') && !statusLabel.includes('freelance')) return false;
        } else if (globalCareerFilter.value === 'pendidikan') {
            if (!cat.includes('pendidikan') && !statusLabel.includes('studi') && !Boolean(record.study_detail)) return false;
        }
    }

    return true;
};

const hasIkuOutcomeFields = computed(() => {
    return props.rows.some((r) => r.career_category !== undefined || r.weight !== undefined || r.career_status_label !== undefined);
});

const qualFilterOptions = computed(() => {
    const base = (selectedPoint.value?.label || props.selectedLabel) ? drillDownRecords.value : props.rows;
    const meetCount = base.filter((r) => Number(r.weight || 0) > 0 || r.qualifies === true || r.qualifies === 'Ya').length;
    const failCount = base.filter((r) => Number(r.weight || 0) === 0 || r.qualifies === false || r.qualifies === 'Tidak').length;
    return [
        { value: 'all', label: `Semua Kelayakan (${base.length})` },
        { value: 'qualified', label: `Sudah Memenuhi (${meetCount})` },
        { value: 'unqualified', label: `Belum Memenuhi (${failCount})` },
    ];
});

const careerFilterOptions = computed(() => {
    const base = (selectedPoint.value?.label || props.selectedLabel) ? drillDownRecords.value : props.rows;
    const countKombinasi = base.filter((r) => recordIsCombination(r)).length;
    const countKaryawan = base.filter((r) => {
        const c = String(r.career_category || '').toLowerCase();
        const s = String(r.career_status_label || r.status_karier || '').toLowerCase();
        return c.includes('karyawan') || s.includes('karyawan') || s.includes('bekerja');
    }).length;
    const countFounder = base.filter((r) => {
        const c = String(r.career_category || '').toLowerCase();
        const s = String(r.career_status_label || r.status_karier || '').toLowerCase();
        return c.includes('founder') || s.includes('founder') || s.includes('wirausaha');
    }).length;
    const countFreelance = base.filter((r) => {
        const c = String(r.career_category || '').toLowerCase();
        const s = String(r.career_status_label || r.status_karier || '').toLowerCase();
        return c.includes('freelance') || s.includes('freelance');
    }).length;
    const countStudi = base.filter((r) => {
        const c = String(r.career_category || '').toLowerCase();
        const s = String(r.career_status_label || r.status_karier || '').toLowerCase();
        return c.includes('pendidikan') || s.includes('studi') || Boolean(r.study_detail);
    }).length;

    return [
        { value: 'all', label: `Semua Aktivitas (${base.length})` },
        { value: 'karyawan', label: `Karyawan (${countKaryawan})` },
        { value: 'founder', label: `Founder / Wirausaha (${countFounder})` },
        { value: 'freelancer', label: `Freelancer (${countFreelance})` },
        { value: 'pendidikan', label: `Melanjutkan Studi (${countStudi})` },
        { value: 'kombinasi', label: `Kombinasi (Bekerja + Studi) (${countKombinasi})` },
    ];
});

// Kontrol pencarian dan paginasi tabel detail individu.
const detailSearch = ref('');
const detailPage = ref(1);
const detailPageSize = 15;
const detailSortKey = ref('nim');
const detailSortDirection = ref('asc');

// Menyaring records nyata berdasarkan seleksi grafik aktif, kriteria terpadu, dan kata kunci pencarian.
const filteredDetailRows = computed(() => {
    const query = detailSearch.value.trim().toLowerCase();
    const baseRows = (selectedPoint.value?.label || props.selectedLabel)
        ? drillDownRecords.value
        : props.rows;

    const criteriaFiltered = baseRows.filter((record) => matchCriteria(record));

    if (!query) return criteriaFiltered;

    return criteriaFiltered.filter((row) => detailColumns.value.some((column) => (
        formatDetailValue(row[column.key], column.key).toLowerCase().includes(query)
    )));
});

// Mengurutkan tabel detail berdasarkan kolom yang dipilih pengguna, default NIM naik.
const sortedDetailRows = computed(() => {
    const fallbackKey = detailColumns.value.find((column) => column.key === detailSortKey.value)?.key
        || detailColumns.value[0]?.key;
    if (!fallbackKey) return filteredDetailRows.value;

    return [...filteredDetailRows.value].sort((left, right) => {
        const leftValue = formatDetailValue(left[fallbackKey], fallbackKey);
        const rightValue = formatDetailValue(right[fallbackKey], fallbackKey);
        const comparison = leftValue.localeCompare(rightValue, 'id', { numeric: true, sensitivity: 'base' });
        return detailSortDirection.value === 'asc' ? comparison : -comparison;
    });
});

// Mengganti kolom dan arah pengurutan tanpa mengubah records sumber.
const toggleDetailSort = (key) => {
    if (detailSortKey.value === key) {
        detailSortDirection.value = detailSortDirection.value === 'asc' ? 'desc' : 'asc';
        return;
    }

    detailSortKey.value = key;
    detailSortDirection.value = 'asc';
};

// Paginasi tabel detail agar jumlah baris besar tetap nyaman dibaca.
const detailTotalPages = computed(() => Math.max(1, Math.ceil(sortedDetailRows.value.length / detailPageSize)));
const paginatedDetailRows = computed(() => {
    const start = (detailPage.value - 1) * detailPageSize;
    return sortedDetailRows.value.slice(start, start + detailPageSize);
});

// Reset tabel detail ketika data indikator atau pencarian detail berubah.
watch([() => props.rows, detailSearch], () => {
    detailPage.value = 1;
}, { deep: true });

// Kelas tambahan sel tabel ditentukan oleh kontrak kolom, tanpa mengubah nilai data.
const tableCellClass = (column, row) => {
    let extraClass = typeof column.className === 'function'
        ? column.className(row)
        : (column.className || '');

    if (currentView.value.chartType === 'stacked_horizontal') {
        if (column.key === 'above_count') extraClass = 'font-bold text-[#0D542B]';
        if (column.key === 'below_count') extraClass = 'text-slate-500';
        if (column.key === 'weighted_score' || column.key === 'percentage') extraClass = 'font-bold text-[#0D542B]';
    }

    return [
        column.align === 'right' ? 'text-right font-mono' : 'text-left',
        column.key.toLowerCase().includes('count') || column.key.toLowerCase().includes('total')
            ? 'font-semibold text-slate-900'
            : 'text-slate-700',
        extraClass,
    ];
};

const activeRows = computed(() => {
    const rawRows = currentView.value.tableData?.rows || [];
    const query = tableSearch.value.trim().toLowerCase();

    let filtered = rawRows;
    if (query) {
        filtered = rawRows.filter((row) => Object.values(row).some((val) => String(val ?? '').toLowerCase().includes(query)));
    }

    const colKey = activeColumns.value[sortColumn.value]?.key;
    if (colKey) {
        filtered = [...filtered].sort((a, b) => {
            const valA = a[colKey] ?? '';
            const valB = b[colKey] ?? '';
            const cmp = String(valA).localeCompare(String(valB), 'id', { numeric: true });
            return sortDirection.value === 'asc' ? cmp : -cmp;
        });
    }

    return filtered;
});

// Paginasi tabel ringkasan
const totalPages = computed(() => Math.max(1, Math.ceil(activeRows.value.length / rowsPerPage)));
const paginatedRows = computed(() => {
    const start = (currentPage.value - 1) * rowsPerPage;
    return activeRows.value.slice(start, start + rowsPerPage);
});

// Pengurutan tabel
const toggleSort = (colIndex) => {
    if (sortColumn.value === colIndex) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortColumn.value = colIndex;
        sortDirection.value = 'asc';
    }
};

// -----------------------------------------------------------------------------
// DRILL-DOWN INLINE RESPONDEN SESUAI LABEL TERPILIH
// -----------------------------------------------------------------------------
const recordDisplayAnswer = (rec) => {
    if (!rec) return '-';
    if (rec.jenis_perusahaan_lainnya && rec.jenis_perusahaan_lainnya !== '-') {
        return `${rec.answer || rec.category || 'Lainnya'} (${rec.jenis_perusahaan_lainnya})`;
    }
    // Jika record studi lanjut (memiliki degree, university, study_program, atau funding_source)
    if (rec.degree || rec.university || rec.study_program || rec.funding_source) {
        const parts = [];
        if (rec.degree) {
            const degLabel = rec.degree === 'Lainnya' ? 'Lainnya (Profesi/Pelatihan)' : `Jenjang: ${rec.degree}`;
            parts.push(degLabel);
        }
        if (rec.university && rec.university !== '-' && rec.university !== '4') {
            parts.push(rec.university);
        }
        if (rec.funding_source && rec.funding_source !== '-') {
            parts.push(rec.funding_source);
        }
        if (parts.length > 0) {
            return parts.join(' · ');
        }
    }
    if (rec.category || rec.jawaban || rec.answer) return rec.category || rec.jawaban || rec.answer;
    if (rec.competency && rec.gap !== undefined) {
        return `${rec.competency} (Lulus: ${formatNumber(rec.graduation_score, 2)}, Butuh: ${formatNumber(rec.need_score, 2)}, Gap: ${formatNumber(rec.gap, 2)})`;
    }
    if (rec.kode_pertanyaan && rec.value !== undefined) return `${rec.kode_pertanyaan}: ${formatNumber(rec.value, 0)}`;
    if (rec.months !== undefined && rec.months !== null) return `${formatNumber(rec.months, 1)} bulan (${rec.comparison || 'Waktu tunggu'})`;
    if (rec.salary !== undefined && rec.salary !== null) {
        const statusUmp = rec.meets_ump ? 'Memenuhi 1,2x UMP' : 'Belum memenuhi UMP';
        return `Rp ${formatNumber(rec.salary, 0)} · ${statusUmp}`;
    }
    if (rec.province_name) return `${rec.province_name}${rec.nama_perusahaan ? ` (${rec.nama_perusahaan})` : ''}`;
    if (rec.status || rec.metode || rec.method) return rec.status || rec.metode || rec.method;
    if (rec.is_lulus_tepat_waktu !== undefined) {
        return rec.is_lulus_tepat_waktu ? 'Tepat Waktu' : 'Belum Tepat Waktu';
    }
    return activeSelectionLabel.value || '-';
};

const drillDownRecords = computed(() => {
    const rawTarget = cleanChartLabel(props.selectedLabel || selectedPoint.value?.label || '');
    if (!rawTarget) return [];

    if (rawTarget.toLowerCase() === 'semua responden') {
        return props.rows;
    }

    const ptData = selectedPoint.value?.data || {};
    const ptProdi = cleanChartLabel(selectedPoint.value?.prodi || '').toLowerCase();
    const ptCat = cleanChartLabel(selectedPoint.value?.category || '').toLowerCase();
    const target = rawTarget.toLowerCase();

    // 1. Filter khusus IKU S1 UMP & Bobot (Audit Pendapatan, Masa Tunggu, Total Bobot per Role, Rasio Kelulusan)
    const hasIkuUmpFields = props.rows.some((r) => r.career_category !== undefined && (r.salary_comparison !== undefined || r.weight !== undefined));
    if (hasIkuUmpFields) {
        const prodiList = [...new Set(props.rows.map((r) => String(r.nama_prodi || '').toLowerCase()))];
        const isProdiMatch = (str) => Boolean(str) && prodiList.some((p) => p && (p === str || p.includes(str) || str.includes(p)));

        let prodiCandidate = '';
        let roleCandidate = '';
        let catCandidate = cleanChartLabel(ptCat || ptData.category || '').toLowerCase();

        // Uraikan jika label terpilih berformat gabungan role dan kategori, misal "Karyawan · Di atas 1,2× UMP"
        let baseName = cleanChartLabel(ptData.name || target || '').toLowerCase();
        if (baseName.includes(' · ') || baseName.includes(' - ')) {
            const separator = baseName.includes(' · ') ? ' · ' : ' - ';
            const parts = baseName.split(separator);
            const firstPart = parts[0]?.trim() || '';
            const secondPart = parts.slice(1).join(' ').trim();
            if (!catCandidate && secondPart) {
                catCandidate = secondPart;
            }
            baseName = firstPart;
        }

        if (ptProdi && isProdiMatch(ptProdi)) {
            prodiCandidate = ptProdi;
        } else if (isProdiMatch(baseName)) {
            prodiCandidate = baseName;
        }

        if (ptData.role) {
            roleCandidate = cleanChartLabel(ptData.role).toLowerCase();
        } else if (!prodiCandidate && baseName && !isProdiMatch(baseName)) {
            roleCandidate = baseName;
        }

        return props.rows.filter((r) => {
            const rRole = String(r.career_category || '').toLowerCase();
            const rProdi = String(r.nama_prodi || '').toLowerCase();

            // Cek kesesuaian prodi jika klik pada grafik per program studi
            if (prodiCandidate) {
                const matchProdi = rProdi.includes(prodiCandidate) || prodiCandidate.includes(rProdi);
                if (!matchProdi) return false;
            }

            // Cek kesesuaian role karier jika ada role yang diklik
            if (roleCandidate) {
                const matchRole = rRole.includes(roleCandidate)
                    || roleCandidate.includes(rRole)
                    || (roleCandidate.includes('founder') && rRole.includes('founder'))
                    || (roleCandidate.includes('wirausaha') && rRole.includes('wirausaha'));
                if (!matchRole) return false;
            }

            // 1.1 Status Bobot IKU (> 0 vs = 0)
            if (catCandidate.includes('bobot')) {
                if (catCandidate.includes('tidak') || catCandidate.includes('= 0') || catCandidate.includes('tanpa')) {
                    return Number(r.weight || 0) === 0 || r.qualifies === false;
                }
                if (catCandidate.includes('memenuhi') || catCandidate.includes('> 0')) {
                    return Number(r.weight || 0) > 0 || r.qualifies === true;
                }
            }

            // 1.2 Status UMP / Pendapatan
            if (catCandidate.includes('di atas') || catCandidate.includes('≥ 1,2') || catCandidate.includes('>= 1,2') || (catCandidate.includes('memenuhi') && !catCandidate.includes('belum') && !catCandidate.includes('tidak'))) {
                return r.salary_comparison === 'above' || r.salary_above_threshold === true;
            }
            if (catCandidate.includes('di bawah')) {
                return r.salary_comparison === 'below' || r.salary_above_threshold === false;
            }
            if (catCandidate.includes('belum dapat dibandingkan') || catCandidate.includes('belum terbandingkan') || catCandidate.includes('unavailable') || catCandidate.includes('tidak tersedia')) {
                if (currentView.value.key === 'wait-time') {
                    return (r.months === null || r.months === undefined) && !r.before_graduation;
                }
                return r.salary_comparison === 'unavailable' || r.salary_above_threshold === null;
            }

            // 1.3 Masa Tunggu
            if (catCandidate.includes('sebelum lulus')) {
                return Boolean(r.before_graduation);
            }
            if (catCandidate.includes('<6') || catCandidate.includes('< 6')) {
                return !r.before_graduation && r.months !== null && r.months !== undefined && Number(r.months) < 6;
            }
            if (catCandidate.includes('6–<12') || catCandidate.includes('6-12') || (catCandidate.includes('6') && catCandidate.includes('12'))) {
                return !r.before_graduation && r.months !== null && r.months !== undefined && Number(r.months) >= 6 && Number(r.months) < 12;
            }
            if (catCandidate.includes('≥12') || catCandidate.includes('>=12') || catCandidate.includes('> 12') || catCandidate.includes('≥ 12')) {
                return !r.before_graduation && r.months !== null && r.months !== undefined && Number(r.months) >= 12;
            }
            if (catCandidate.includes('melanjutkan pendidikan')) {
                return rRole.includes('melanjutkan pendidikan');
            }

            return true;
        });
    }

    // 2. Filter khusus Indikator 11 (Pendapatan & Ambang UMP per Prodi/Provinsi)
    const hasSalaryUmpFields = props.rows.some((r) => r.salary !== undefined && r.meets_ump !== undefined);
    if (hasSalaryUmpFields) {
        const catTarget = cleanChartLabel(ptCat || ptData.name || target || '').toLowerCase();
        const prodiTarget = cleanChartLabel(ptProdi || (!ptData.province ? target : '')).toLowerCase();
        const provinceTarget = cleanChartLabel(ptData.province || '').toLowerCase();

        return props.rows.filter((r) => {
            const rProdi = String(r.nama_prodi || '').toLowerCase();
            const rProv = String(r.province_name || '').toLowerCase();

            // Cek kesesuaian prodi (jika klik pada grafik prodi)
            if (prodiTarget && prodiTarget !== catTarget) {
                const matchProdi = rProdi.includes(prodiTarget) || prodiTarget.includes(rProdi);
                if (!matchProdi && !provinceTarget) return false;
            }

            // Cek kesesuaian provinsi (jika klik pada grafik provinsi)
            if (provinceTarget) {
                const matchProv = rProv.includes(provinceTarget) || provinceTarget.includes(rProv);
                if (!matchProv) return false;
            }

            // Cek status UMP jika kategori menyatakan status UMP
            if (catTarget.includes('di atas') || (catTarget.includes('memenuhi') && !catTarget.includes('belum') && !catTarget.includes('tidak'))) {
                return r.meets_ump === true;
            }
            if (catTarget.includes('di bawah')) {
                return r.meets_ump === false;
            }
            if (catTarget.includes('belum')) {
                return r.meets_ump === null;
            }

            return true;
        });
    }

    // 3. Filter khusus Masa Tunggu (Indikator 05 / 03)
    const hasWaitTimeFields = props.rows.some((r) => r.months !== undefined && (r.nama_prodi !== undefined || r.category !== undefined));
    if (hasWaitTimeFields) {
        const prodiTarget = cleanChartLabel(ptProdi || (!ptCat ? target : '')).toLowerCase();
        const catTarget = cleanChartLabel(ptCat || target).toLowerCase();

        // 3.1 Rentang waktu tunggu bucket min-max
        if (ptData.min !== undefined && ptData.max !== undefined) {
            return props.rows.filter((r) => Number(r.months) >= Number(ptData.min) && Number(r.months) <= Number(ptData.max));
        }
        const bucketMatch = rawTarget.match(/^(\d+)\s*[–-]\s*(\d+)\s*bln/i);
        if (bucketMatch) {
            const minM = Number(bucketMatch[1]);
            const maxM = Number(bucketMatch[2]);
            return props.rows.filter((r) => Number(r.months) >= minM && Number(r.months) <= maxM);
        }
        if (/^>=\s*12\s*bln/i.test(rawTarget)) {
            return props.rows.filter((r) => Number(r.months) >= 12);
        }

        // 3.2 Indikator 05: Kuartil Waktu Tunggu per Prodi
        if (prodiTarget && props.rows.some((r) => r.nama_prodi && String(r.nama_prodi).toLowerCase().includes(prodiTarget))) {
            return props.rows.filter((r) => {
                const rProdi = String(r.nama_prodi || '').toLowerCase();
                return rProdi.includes(prodiTarget) || prodiTarget.includes(rProdi);
            });
        }

        // 3.3 Indikator 03: Fase & Rentang Waktu Mulai Mencari Kerja
        if (catTarget) {
            const timingMatched = props.rows.filter((r) => {
                const rCat = String(r.category || r.status || '').toLowerCase();
                if (catTarget.includes('sebelum lulus')) return rCat.includes('sebelum') || Boolean(r.before_graduation);
                if (catTarget.includes('tidak mencari')) return rCat.includes('tidak mencari');
                if (catTarget.includes('<6') || catTarget.includes('< 6')) return Number(r.months) < 6;
                if (catTarget.includes('6-12') || catTarget.includes('6–12') || (catTarget.includes('6') && catTarget.includes('12'))) return Number(r.months) >= 6 && Number(r.months) <= 12;
                if (catTarget.includes('>12') || catTarget.includes('> 12')) return Number(r.months) > 12;
                return rCat.includes(catTarget) || catTarget.includes(rCat);
            });
            if (timingMatched.length > 0) return timingMatched;
        }
    }

    // Filter khusus tahapan pipeline lamaran F6 / F7 / F7A (Indikator 06)
    const hasPipelineFields = props.rows.some((r) => r.f6 !== undefined || r.dilamar !== undefined);
    if (hasPipelineFields) {
        const targetProdi = (ptProdi || (props.rows.some((r) => String(r.nama_prodi || r.prodi || '').toLowerCase() === target) ? target : '')).toLowerCase();
        const isDilamar = ptCat.includes('dilamar') || target.includes('dilamar') || ptCat.includes('f6') || target.includes('f6');
        const isMerespon = ptCat.includes('respon') || target.includes('respon') || ptCat.includes('f7') || target.includes('f7');
        const isWawancara = ptCat.includes('wawancara') || target.includes('wawancara') || ptCat.includes('f7a') || target.includes('f7a');

        const pipelineMatched = props.rows.filter((r) => {
            const rProdi = String(r.nama_prodi || r.prodi || '').toLowerCase();
            if (targetProdi && !rProdi.includes(targetProdi) && !targetProdi.includes(rProdi)) {
                return false;
            }
            if (isDilamar) return Number(r.f6 ?? r.dilamar ?? 0) > 0;
            if (isMerespon) return Number(r.f7 ?? r.merespon ?? 0) > 0;
            if (isWawancara) return Number(r.f7a ?? r.wawancara ?? 0) > 0;
            return true;
        });
        if (pipelineMatched.length > 0) return pipelineMatched;
    }

    const stageCodeMatch = rawTarget.match(/\((F6|F7A|F7)\)/i);
    if (stageCodeMatch && props.rows.some((r) => r.kode_pertanyaan)) {
        const code = stageCodeMatch[1].toUpperCase();
        return props.rows.filter((r) => String(r.kode_pertanyaan || '').toUpperCase() === code);
    }

    // Jika sel heatmap memiliki prodi dan kategori sekaligus
    if (ptProdi && ptCat && ptProdi !== ptCat) {
        const exactBoth = props.rows.filter((r) => {
            const rProdi = String(r.nama_prodi || '').toLowerCase();
            const rFields = [
                r.category, r.target, r.jawaban, r.answer, r.status,
                r.metode, r.method, r.competency, r.degree, r.funding_source,
                r.university, r.province_name, r.career_category, r.career_role,
                r.status_karier, r.career_status_label,
            ].map((v) => String(v || '').toLowerCase());
            return (rProdi.includes(ptProdi) || ptProdi.includes(rProdi))
                && rFields.some((f) => f && (f.includes(ptCat) || ptCat.includes(f)));
        });
        if (exactBoth.length > 0) return exactBoth;
    }

    // Filter khusus IKU S1 AEE (Kelulusan Tepat Waktu S1 per Program Studi)
    // r.is_lulus_tepat_waktu bernilai true/false atau 1/0
    const hasAeeFields = props.rows.some((r) => r.is_lulus_tepat_waktu !== undefined);
    if (hasAeeFields) {
        const targetProdi = (ptProdi || target).toLowerCase();
        const isTepatWaktuWants = ptCat.includes('tepat waktu') && !ptCat.includes('belum tepat waktu')
            ? true
            : (ptCat.includes('belum tepat waktu') ? false : null);

        const aeeFiltered = props.rows.filter((r) => {
            const rProdi = String(r.nama_prodi || '').toLowerCase();
            const prodiMatch = rProdi === targetProdi || rProdi.includes(targetProdi) || targetProdi.includes(rProdi);
            if (!prodiMatch) return false;
            if (isTepatWaktuWants === null) return true;
            return Boolean(r.is_lulus_tepat_waktu) === isTepatWaktuWants;
        });
        if (aeeFiltered.length > 0) return aeeFiltered;
    }

    return props.rows.filter((r) => {
        const fields = [
            r.category,
            r.target,
            r.jawaban,
            r.answer,
            r.status,
            r.comparison,
            r.metode,
            r.method,
            r.competency,
            r.kode_pertanyaan,
            r.nama_prodi,
            r.province_name,
            r.degree,
            r.funding_source,
            r.university,
            r.study_program,
            r.nama_perusahaan,
            r.career_category,
            r.career_role,
            r.status_karier,
            r.career_status_label,
            r.nama,
        ].map((val) => String(val || '').toLowerCase()).filter(Boolean);

        return fields.some((f) => f === target || f.includes(target) || target.includes(f));
    });
});

// -----------------------------------------------------------------------------
// ANALISIS NARATIF OTOMATIS & DINAMIS SAAT ELEMEN GRAFIK DIKLIK
// -----------------------------------------------------------------------------
const computedAnalysis = computed(() => {
    const selLabel = activeSelectionLabel.value;
    const rows = currentView.value.tableData?.rows || [];
    const cols = currentView.value.tableData?.columns || [];

    // Jika ada elemen grafik yang sedang dipilih, ubah analisis menyesuaikan item yang diklik
    if (selLabel) {
        const cleanSel = cleanChartLabel(props.selectedLabel || selectedPoint.value?.label || '').toLowerCase();
        const ptProdi = cleanChartLabel(selectedPoint.value?.prodi || '').toLowerCase();
        const ptCat = cleanChartLabel(selectedPoint.value?.category || '').toLowerCase();
        const ptData = selectedPoint.value?.data || {};

        // Cari baris tabel ringkasan yang cocok dengan item yang diklik
        const matchedRow = rows.find((r) => {
            const vals = Object.values(r).map((v) => cleanChartLabel(v).toLowerCase());
            if (ptProdi && ptCat && ptProdi !== ptCat) {
                return vals.some((v) => v === ptProdi || v.includes(ptProdi))
                    && vals.some((v) => v === ptCat || v.includes(ptCat));
            }
            return vals.some((v) => v === cleanSel || (v && cleanSel && (v.includes(cleanSel) || cleanSel.includes(v))));
        });

        // Susun metrik dari baris tabel ringkasan yang cocok
        const rowMetrics = [];
        if (matchedRow && cols.length > 0) {
            cols.slice(1).forEach((col) => {
                const val = matchedRow[col.key];
                if (val !== undefined && val !== null && val !== '' && val !== '-') {
                    rowMetrics.push(`${col.label}: ${val}`);
                }
            });
        } else {
            if (ptData.count !== undefined) rowMetrics.push(`Jumlah: ${formatNumber(ptData.count, 0)} alumni`);
            if (ptData.percentage !== undefined) rowMetrics.push(`Proporsi: ${formatNumber(ptData.percentage, 1)}%`);
            if (ptData.grad !== undefined && ptData.need !== undefined) {
                rowMetrics.push(`Saat Lulus: ${formatNumber(ptData.grad, 2)} · Kebutuhan: ${formatNumber(ptData.need, 2)} · Gap: ${formatNumber(ptData.gap, 2)}`);
            }
        }

        // Hitung rincian sebaran dari drillDownRecords
        const matchedRecs = drillDownRecords.value;
        const recCount = matchedRecs.length;

        // Kelompokkan berdasarkan program studi dan jawaban untuk narasi detail
        const prodiCounts = {};
        const answerCounts = {};
        matchedRecs.forEach((r) => {
            const p = r.nama_prodi || 'Tanpa prodi';
            prodiCounts[p] = (prodiCounts[p] || 0) + 1;
            const ans = recordDisplayAnswer(r);
            if (ans && ans !== '-' && ans !== selLabel) {
                answerCounts[ans] = (answerCounts[ans] || 0) + 1;
            }
        });

        const sortedProdis = Object.entries(prodiCounts).sort((a, b) => b[1] - a[1]);
        const sortedAnswers = Object.entries(answerCounts).sort((a, b) => b[1] - a[1]);

        let statusBadge = 'Fokus Terpilih';
        if (ptData.percentage !== undefined) {
            statusBadge = `${formatNumber(ptData.percentage, 1)}% (${formatNumber(ptData.count ?? recCount, 0)} Alumni)`;
        } else if (recCount > 0) {
            statusBadge = `${formatNumber(recCount, 0)} Data Terkait`;
        } else if (selectedPoint.value?.value !== null && selectedPoint.value?.value !== undefined && !Array.isArray(selectedPoint.value?.value)) {
            statusBadge = `Nilai: ${selectedPoint.value.value}`;
        }

        const leadParts = [];
        if (rowMetrics.length > 0) {
            leadParts.push(`Analisis terfokus pada "${selLabel}" mencatat rincian indikator: ${rowMetrics.join(' · ')}.`);
        } else {
            leadParts.push(`Analisis sedang difokuskan pada pilihan "${selLabel}" dari grafik ${currentView.value.tabLabel || currentView.value.title || 'aktif'}.`);
        }

        const detailParts = [];
        if (recCount > 0) {
            detailParts.push(`Terdapat ${formatNumber(recCount, 0)} catatan responden yang berkaitan langsung dengan pilihan ini`);
            if (sortedProdis.length > 1) {
                const topP = sortedProdis[0];
                const lowP = sortedProdis[sortedProdis.length - 1];
                detailParts.push(`dengan kontribusi terbesar dari ${topP[0]} (${formatNumber(topP[1], 0)} data) dan terkecil pada ${lowP[0]} (${formatNumber(lowP[1], 0)} data)`);
            } else if (sortedProdis.length === 1) {
                detailParts.push(`yang berasal dari program studi ${sortedProdis[0][0]}`);
            }
            if (sortedAnswers.length > 0) {
                const topA = sortedAnswers[0];
                detailParts.push(`serta distribusi keterangan dominan pada "${topA[0]}" (${formatNumber(topA[1], 0)} data)`);
            }
            detailParts[detailParts.length - 1] += '. Klik tombol "Kembali ke Analisis Keseluruhan" untuk meninjau analisis seluruh data.';
        } else {
            detailParts.push('Klik elemen grafik yang sama sekali lagi atau tekan tombol "Kembali ke Analisis Keseluruhan" untuk kembali ke ringkasan universitas.');
        }

        return {
            title: `Kilas Analisis Terpilih · ${selLabel}`,
            statusBadge,
            badgeClass: 'bg-white text-[#0D542B] border border-[#0D542B]/30',
            lead: leadParts.join(' '),
            detail: detailParts.join(', '),
        };
    }

    // Prioritas: Jika elemen sedang diarahkan kursor (hover) atau dipilih dan tersedia kamus analisis dinamis
    const activeHoverOrSelect = cleanChartLabel(hoveredPoint.value?.label || activeSelectionLabel.value);
    if (activeHoverOrSelect && currentView.value.analysisMap && currentView.value.analysisMap[activeHoverOrSelect]) {
        return currentView.value.analysisMap[activeHoverOrSelect];
    }

    // Analisis dinamis saat kursor mengarah ke segmen komposisi prodi bertumpuk
    if (hoveredPoint.value?.prodi && hoveredPoint.value?.category && hoveredPoint.value.data?.count !== undefined) {
        const d = hoveredPoint.value.data;
        return {
            title: `Kilas Komposisi · ${hoveredPoint.value.prodi}`,
            statusBadge: `${d.value}% ${hoveredPoint.value.category}`,
            badgeClass: 'bg-white text-[#0D542B] border border-[#0D542B]/30',
            lead: `Sebanyak ${d.value}% alumni ${hoveredPoint.value.prodi} tercatat berstatus ${hoveredPoint.value.category} (${d.count} dari ${d.total} responden di program studi ini).`,
            detail: `Persentase ${d.value}% ini dihitung secara spesifik terhadap populasi responden lulusan program studi ${hoveredPoint.value.prodi}.`,
        };
    }

    const custom = currentView.value.analysis;
    if (custom) {
        if (typeof custom === 'object') return custom;
        return {
            title: 'Kilas Indikator Visualisasi',
            statusBadge: 'Data Terkini',
            badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
            lead: custom,
            detail: 'Klik salah satu elemen pada grafik di atas untuk melihat analisis spesifik per kategori atau program studi.',
        };
    }

    // Auto-generasi narasi dari data tabel jika tersedia
    if (!rows.length) {
        return {
            title: 'Kilas Indikator Visualisasi',
            statusBadge: 'Terkini',
            badgeClass: 'bg-slate-100 text-slate-700',
            lead: 'Visualisasi menyajikan ringkasan agregasi indikator secara komprehensif.',
            detail: 'Gunakan navigasi tab untuk meninjau pola data dari berbagai perspektif analitik.',
        };
    }

    const first = rows[0] || {};
    const labelKey = Object.keys(first)[0] || 'name';
    const valKey = Object.keys(first)[1] || 'count';
    const topLabel = first[labelKey] || 'Kategori utama';
    const topVal = first[valKey] || 'tertinggi';

    return {
        title: 'Kilas Indikator Visualisasi',
        statusBadge: 'Dominan',
        badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
        lead: `Konsentrasi respon terbesar pada visualisasi ini tercatat pada ${topLabel} (${topVal}).`,
        detail: `Pola ini dihitung dari ${formatNumber(props.rows.length, 0)} total catatan responden pada kohort aktif. Klik elemen pada grafik untuk membedah analisis spesifik.`,
    };
});

const drillDownSearch = ref('');
// Filter status tambahan untuk membedakan bekerja, bekerja sekaligus studi, dan studi murni.
const drillDownCareerFilter = ref('all');
const drillDownPage = ref(1);
const drillDownPageSize = 8;

// Kolom khusus drill-down ditentukan oleh view ketika kebutuhan datanya lebih kaya dari tabel generik.
const customDrillDownColumns = computed(() => currentView.value.drillDownColumns || []);

const drillDownHasCompany = computed(() => {
    return drillDownRecords.value.some((r) => r.nama_perusahaan && r.nama_perusahaan !== '-');
});

const drillDownStudyProgramHeader = computed(() => {
    const hasStudyProgram = drillDownRecords.value.some((r) => r.study_program && r.study_program !== '-' && r.study_program !== r.nama_prodi);
    if (!hasStudyProgram) return 'Program Studi';
    const target = cleanChartLabel(props.selectedLabel || selectedPoint.value?.label || '').toLowerCase();
    if (target.includes('s2')) return 'Program Studi S2 (Lanjut)';
    if (target.includes('lainnya')) return 'Program Studi Lanjut / Profesi';
    return 'Program Studi Lanjut';
});

const drillDownHasRegionalSplit = computed(() => {
    return drillDownRecords.value.some((r) =>
        (r.provinsi && r.provinsi !== '-') ||
        (r.kabupaten && r.kabupaten !== '-') ||
        (r.nama_negara && r.nama_negara !== '-')
    );
});

const drillDownHasRegion = computed(() => {
    return drillDownRecords.value.some((r) => (r.wilayah && r.wilayah !== '-') || (r.province_name && r.province_name !== '-') || (r.skala && r.skala !== '-'));
});

// Menentukan apakah satu record memiliki aktivitas kerja berdasarkan kategori dan profil aktif.
const recordHasWork = (record) => {
    const category = String(record.career_category || '').toLowerCase();
    const status = String(record.career_status_label || '').toLowerCase();

    return category !== 'melanjutkan pendidikan'
        || status.includes('bekerja')
        || Boolean(record.nama_perusahaan)
        || Boolean(record.posisi_jabatan)
        || Boolean(record.bidang_pekerjaan)
        || Boolean(record.divisi);
};

// Menentukan apakah satu record memiliki data studi lanjut yang dapat ditampilkan.
const recordHasFurtherStudy = (record) => {
    const status = String(record.career_status_label || '').toLowerCase();

    return status.includes('studi lanjut') || Boolean(record.study_detail);
};

// Menyediakan opsi filter status beserta jumlah record pada drill-down aktif.
const drillDownCareerFilterOptions = computed(() => {
    const records = drillDownRecords.value;
    const count = (predicate) => records.filter(predicate).length;
    const working = (record) => recordHasWork(record);
    const studying = (record) => recordHasFurtherStudy(record);
    const hasWeight = records.some((r) => r.weight !== undefined);

    // Jika dataset memiliki kolom bobot (IKU S1 Outcomes / UMP), sediakan filter berbasis bobot
    if (hasWeight) {
        return [
            { value: 'all', label: 'Semua data', count: records.length },
            { value: 'weighted', label: 'Memenuhi IKU (Bobot > 0)', count: count((r) => Number(r.weight || 0) > 0) },
            { value: 'full_weight', label: 'Bobot Penuh (>= 1.0)', count: count((r) => Number(r.weight || 0) >= 1.0) },
            { value: 'partial_weight', label: 'Bobot Parsial (0.2-0.8)', count: count((r) => Number(r.weight || 0) > 0 && Number(r.weight || 0) < 1.0) },
            { value: 'unweighted', label: 'Belum Berbobot (Bobot 0)', count: count((r) => Number(r.weight || 0) === 0) },
        ];
    }

    return [
        { value: 'all', label: 'Semua status', count: records.length },
        { value: 'working', label: 'Bekerja', count: count((record) => working(record) && !studying(record)) },
        { value: 'working_study', label: 'Bekerja + Studi Lanjut', count: count((record) => working(record) && studying(record)) },
        { value: 'study_only', label: 'Studi Lanjut Murni', count: count((record) => !working(record) && studying(record)) },
    ];
});

// Menerapkan filter kriteria terpadu dan pencarian teks pada data drill-down secara berurutan.
const filteredDrillDownRecords = computed(() => {
    const q = drillDownSearch.value.trim().toLowerCase();
    const criteriaFiltered = drillDownRecords.value.filter((record) => matchCriteria(record));

    if (!q) return criteriaFiltered;

    return criteriaFiltered.filter((r) => {
        return String(r.nama || '').toLowerCase().includes(q)
            || String(r.nim || '').toLowerCase().includes(q)
            || String(r.nama_prodi || '').toLowerCase().includes(q)
            || String(r.nama_perusahaan || '').toLowerCase().includes(q)
            || String(r.lokasi_kerja || '').toLowerCase().includes(q)
            || String(r.nama_atasan || '').toLowerCase().includes(q)
            || String(r.skills || '').toLowerCase().includes(q)
            || String(r.career_role || '').toLowerCase().includes(q)
            || String(r.study_detail || '').toLowerCase().includes(q)
            || String(r.provinsi || '').toLowerCase().includes(q)
            || String(r.kabupaten || '').toLowerCase().includes(q)
            || String(r.nama_negara || '').toLowerCase().includes(q)
            || String(r.wilayah || '').toLowerCase().includes(q)
            || String(r.province_name || '').toLowerCase().includes(q);
    });
});

const drillDownTotalPages = computed(() => Math.max(1, Math.ceil(filteredDrillDownRecords.value.length / drillDownPageSize)));
const paginatedDrillDownRecords = computed(() => {
    const start = (drillDownPage.value - 1) * drillDownPageSize;
    return filteredDrillDownRecords.value.slice(start, start + drillDownPageSize);
});

watch(() => props.selectedLabel, () => {
    drillDownSearch.value = '';
    drillDownCareerFilter.value = 'all';
    drillDownPage.value = 1;
});

// Mengembalikan pagination ke halaman pertama ketika filter atau pencarian berubah.
watch([drillDownSearch, drillDownCareerFilter], () => {
    drillDownPage.value = 1;
});
</script>

<template>
    <!-- Kontainer Khusus Terpadu: Kokoh, Stay, dan Bebas dari Layout Shift -->
    <div class="relative overflow-hidden rounded-xl border border-slate-200 bg-white p-5 shadow-xs transition-all">
        <!-- Indikator progress bar halus saat data sedang dimuat -->
        <div v-if="loading" class="absolute top-0 left-0 right-0 h-1 bg-emerald-100 overflow-hidden z-20">
            <div class="h-full bg-[#0D542B] animate-pulse w-full"></div>
        </div>

        <!-- Header Kartu & Switcher Tab Visualisasi (Stay di Kanan Atas, Sejajar dan Bebas Layout Shift) -->
        <div class="flex flex-col justify-between gap-4 border-b border-slate-100 pb-4 sm:flex-row sm:items-center">
            <!-- Kolom Kiri: Judul & Subjudul dengan min-height tetap agar tata letak tidak bergeser -->
            <div class="min-h-[46px]">
                <div class="flex flex-wrap items-center gap-2">
                    <span v-if="indicatorBadge" class="rounded bg-white px-2 py-0.5 text-xs font-bold text-[#0D542B] border border-[#0D542B]/25">
                        {{ indicatorBadge }}
                    </span>
                    <h3 class="text-base font-extrabold text-slate-900">
                        <template v-if="activeViewMode === 'table'">Ringkasan: {{ currentView.tabLabel || currentView.title }}</template>
                        <template v-else>{{ currentView.title }}</template>
                    </h3>
                    <!-- Pill Seleksi Aktif di Header (Bebas dari Layout Shift / Getar) -->
                    <span v-if="activeSelectionLabel" class="inline-flex items-center gap-1.5 rounded-md border border-emerald-300 bg-emerald-50 px-2.5 py-0.5 text-xs">
                        <span class="text-slate-500 font-medium">Terpilih:</span>
                        <strong class="text-[#0D542B] font-bold">{{ activeSelectionLabel }}</strong>
                        <button
                            type="button"
                            class="ml-1 text-slate-400 hover:text-slate-700 font-bold cursor-pointer"
                            title="Reset seleksi pilihan"
                            @click="resetSelection"
                        >
                            ×
                        </button>
                    </span>
                </div>
                <p class="mt-1 text-xs text-slate-500 leading-relaxed max-w-3xl">
                    <template v-if="activeViewMode === 'table'">
                        Tabulasi data indikator.
                    </template>
                    <template v-else>
                        {{ currentView.description }}
                    </template>
                </p>
            </div>

            <!-- Kolom Kanan: Switcher Tab Tetap (Stay di Kanan Atas, Netral Tanpa Warna Legenda) -->
            <div class="flex items-center gap-2 shrink-0 self-start sm:self-auto">
                <!-- Switcher Antar Visualisasi (Selalu terlihat jika indikator memiliki lebih dari 1 sub-view) -->
                <div v-if="views.length > 1" class="flex items-center gap-1 rounded-lg bg-slate-100 p-1 border border-slate-200">
                    <button
                        v-for="view in views"
                        :key="view.key"
                        type="button"
                        class="rounded-md px-3 py-1.5 text-xs font-bold transition-all cursor-pointer whitespace-nowrap"
                        :class="activeTabKey === view.key ? 'bg-white font-bold text-slate-900 shadow-xs border border-slate-200/80' : 'text-slate-600 hover:text-slate-900'"
                        @click="selectView(view)"
                    >
                        {{ view.tabLabel || view.title }}
                    </button>
                </div>

                <!-- Bilah Aksi Terpadu: Grafik | Data | Unduh Grafik | Laporan PDF -->
                <div class="flex flex-wrap items-center gap-1 rounded-lg bg-slate-100 p-1 border border-slate-200 no-print text-xs">
                    <button
                        v-if="!currentViewIsTableOnly"
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
                    <span v-if="!currentViewIsTableOnly" class="mx-0.5 h-3.5 w-px bg-slate-300"></span>
                    <button
                        v-if="!currentViewIsTableOnly"
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
                        @click="handleDownloadExcel"
                        title="Unduh seluruh data indikator ke format Excel (CSV)"
                    >
                        Unduh Excel
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
        </div>

        <!-- TAMPILAN 1: MODE GRAFIK -->
        <div v-if="activeViewMode === 'chart'" class="mt-4 space-y-4">
            <!-- Slot Custom untuk visualisasi khusus (seperti Peta MapLibre) -->
            <div v-if="currentView.isMap" class="w-full">
                <slot :name="currentView.key">
                    <p class="p-8 text-center text-sm text-slate-500">Peta interaktif sedang disiapkan.</p>
                </slot>
                <p class="mt-2 px-1 text-xs leading-relaxed text-slate-500">
                    <span class="font-semibold text-slate-700">Caption:</span>
                    {{ currentView.caption || currentView.description || currentView.title }}
                </p>
            </div>

            <!-- Visualisasi Word Cloud Khusus (untuk isian lainnya, aspirasi, saran) -->
            <div v-else-if="currentView.isWordCloud || currentView.chartType === 'word_cloud'" class="w-full space-y-4">
                <AnalyticsWordCloud
                    :records="currentView.wordCloudRecords || (currentView.tableData?.rows || rows)"
                    :texts="currentView.wordCloudTexts || []"
                    :words="currentView.wordCloudWords || []"
                    :height="currentChartHeight || 380"
                    :title="currentView.wordCloudTitle || currentView.title"
                    :subtitle="currentView.wordCloudSubtitle || currentView.description"
                    :selected-word="wordCloudKeyword"
                    @select="handleWordCloudSelect"
                    @reset="handleWordCloudReset"
                />

                <!-- Tabel Transparansi Rincian di Bawah Word Cloud -->
                <div v-if="currentView.tableData" class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/70 px-4 py-2.5">
                        <span class="text-xs font-bold text-slate-700">Rincian Jawaban Responden</span>
                        <div class="flex items-center gap-2">
                            <span v-if="wordCloudKeyword" class="text-xs text-[#0D542B] font-semibold">
                                Tersaring memuat kata: <strong>"{{ wordCloudKeyword }}"</strong>
                            </span>
                            <button
                                v-if="wordCloudKeyword"
                                type="button"
                                class="rounded bg-white border border-slate-200 px-2 py-0.5 text-[11px] font-semibold text-slate-600 hover:bg-slate-50 cursor-pointer"
                                @click="handleWordCloudReset"
                            >
                                Hapus Filter
                            </button>
                        </div>
                    </div>
                    <div class="max-h-72 overflow-y-auto">
                        <table class="w-full text-left text-xs text-slate-700">
                            <thead class="sticky top-0 bg-slate-100 text-[11px] font-bold text-slate-600 uppercase border-b border-slate-200">
                                <tr>
                                    <th v-for="col in currentView.tableData.columns" :key="col.key" class="px-3.5 py-2" :class="col.align === 'right' ? 'text-right' : 'text-left'">
                                        {{ col.label }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="(row, rIdx) in currentView.tableData.rows.filter(r => !wordCloudKeyword || JSON.stringify(r).toLowerCase().includes(wordCloudKeyword.toLowerCase()))"
                                    :key="rIdx"
                                    class="hover:bg-emerald-50/30 transition-colors"
                                >
                                    <td v-for="col in currentView.tableData.columns" :key="col.key" class="px-3.5 py-2" :class="col.align === 'right' ? 'text-right font-mono' : ''">
                                        {{ row[col.key] }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Visualisasi ECharts Standar -->
            <figure v-else class="w-full">
                <!-- Slot Kontrol Tambahan Sebelum Grafik (misal: Selector Dimensi Gap Analitik) -->
                <slot name="before-chart" :view="currentView"></slot>

                <AnalyticsEChart
                    ref="echartRef"
                    :option="currentView.option"
                    :height="currentChartHeight"
                    :loading="loading"
                    @select="handleChartClick"
                    @hover="handleChartHover"
                    @hover-out="handleChartHoverOut"
                />
                <figcaption class="mt-2 px-1 text-xs leading-relaxed text-slate-500">
                    <span class="font-semibold text-slate-700">Caption:</span>
                    {{ currentView.caption || currentView.description || currentView.title }}
                    <span class="mt-1 block text-slate-400">Petunjuk: Klik elemen grafik untuk melihat rincian responden pada tabel di bawah. Data lengkap tersedia pada tab Data Ringkasan.</span>
                </figcaption>
            </figure>

            <!-- Kotak Analisis Naratif Spesifik (Apple iOS Weather Style) -->
            <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-4 transition-all">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/60 pb-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-700">
                        {{ computedAnalysis.title }}
                    </span>
                    <div class="flex items-center gap-2">
                        <span class="rounded px-2 py-0.5 text-[11px] font-bold" :class="computedAnalysis.badgeClass">
                            {{ computedAnalysis.statusBadge }}
                        </span>
                        <button
                            v-if="activeSelectionLabel"
                            type="button"
                            class="rounded border border-slate-200 bg-white px-2.5 py-0.5 text-[11px] font-bold text-[#0D542B] hover:bg-emerald-50 cursor-pointer transition-colors"
                            @click="resetSelection"
                        >
                            Kembali ke Analisis Keseluruhan
                        </button>
                    </div>
                </div>
                <p class="mt-2.5 text-xs sm:text-sm font-semibold text-slate-900 leading-relaxed">
                    {{ computedAnalysis.lead }}
                </p>
                <p v-if="computedAnalysis.detail" class="mt-1 text-xs text-slate-600 leading-relaxed">
                    {{ computedAnalysis.detail }}
                </p>
                <p v-if="activeViewMode === 'chart'" class="mt-2 border-t border-slate-200/70 pt-2 text-xs text-slate-500 leading-relaxed">
                    <span class="font-semibold text-[#0D542B]">Alasan pemilihan ({{ currentChartType }}):</span>
                    {{ currentChartReason }}
                </p>
            </div>

            <!-- Drill-Down Rincian Data Inline Saat Kategori Diklik -->
            <div v-if="activeSelectionLabel && drillDownRecords.length > 0" class="rounded-xl border border-emerald-200 bg-emerald-50/20 p-4 transition-all">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-emerald-200/60 pb-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="rounded bg-[#0D542B] px-2 py-0.5 text-[11px] font-bold text-white">Rincian Data Terpilih</span>
                            <span class="text-sm font-extrabold text-slate-900">{{ activeSelectionLabel }}</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-600">
                            Menampilkan {{ filteredDrillDownRecords.length }} dari {{ drillDownRecords.length }} alumni yang berkaitan dengan pilihan ini.
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Filter Terpadu Kelayakan & Kategori Karier -->
                        <template v-if="hasIkuOutcomeFields">
                            <select
                                v-model="globalQualFilter"
                                class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs text-slate-800 outline-none focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B]"
                                aria-label="Filter status kelayakan IKU"
                            >
                                <option v-for="option in qualFilterOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                            <select
                                v-model="globalCareerFilter"
                                class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs text-slate-800 outline-none focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B]"
                                aria-label="Filter kategori aktivitas"
                            >
                                <option v-for="option in careerFilterOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                        </template>
                        <input
                            v-model="drillDownSearch"
                            type="text"
                            placeholder="Cari nama, NIM, prodi, instansi..."
                            class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs text-slate-800 placeholder-slate-400 outline-none focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] sm:w-48"
                        />
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:text-[#0D542B] hover:border-[#0D542B]/40 transition-colors shadow-2xs cursor-pointer whitespace-nowrap"
                            @click="handleDownloadDetailCsv"
                            title="Unduh seluruh data yang sedang ditampilkan ke berkas Excel (CSV)"
                        >
                            Unduh Excel (CSV)
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100 cursor-pointer whitespace-nowrap"
                            @click="resetSelection"
                        >
                            Kembali ke Analisis Keseluruhan
                        </button>
                    </div>
                </div>

                <div class="mt-3 overflow-x-auto">
                    <!-- Tabel detail Outcomes memakai kolom eksplisit agar status kerja, atasan, skills, dan studi lanjut terbaca utuh. -->
                    <table v-if="customDrillDownColumns.length > 0" class="w-full min-w-max text-left text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 bg-white/60 text-slate-600">
                                <th v-for="column in customDrillDownColumns" :key="column.key" class="p-2 font-bold whitespace-nowrap">
                                    {{ column.label }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(rec, idx) in paginatedDrillDownRecords" :key="`custom-${idx}`" class="hover:bg-white/80">
                                <td v-for="column in customDrillDownColumns" :key="column.key" class="max-w-xs whitespace-normal p-2 text-slate-700 align-top">
                                    <!-- Status Aktivitas / Karier -->
                                    <template v-if="column.key === 'career_status_label' || column.key === 'status_karier'">
                                        <span
                                            v-if="recordIsCombination(rec)"
                                            class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-0.5 text-[11px] font-semibold text-indigo-700 border border-indigo-200"
                                            title="Alumni memiliki rekam jejak bekerja/wirausaha sekaligus studi lanjut"
                                        >
                                            Kombinasi: {{ rec[column.key] || 'Bekerja + Studi' }}
                                        </span>
                                        <span
                                            v-else-if="String(rec[column.key] || '').toLowerCase().includes('pendidikan') || String(rec[column.key] || '').toLowerCase().includes('studi')"
                                            class="inline-flex items-center rounded-md bg-sky-50 px-2 py-0.5 text-[11px] font-semibold text-sky-700 border border-sky-200"
                                        >
                                            Melanjutkan Studi
                                        </span>
                                        <span
                                            v-else-if="String(rec[column.key] || '').toLowerCase().includes('founder') || String(rec[column.key] || '').toLowerCase().includes('wirausaha')"
                                            class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-[#0D542B] border border-emerald-200"
                                        >
                                            Founder / Wirausaha
                                        </span>
                                        <span
                                            v-else-if="String(rec[column.key] || '').toLowerCase().includes('freelance')"
                                            class="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-800 border border-amber-200"
                                        >
                                            Freelancer
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700 border border-slate-200"
                                        >
                                            {{ rec[column.key] || 'Karyawan' }}
                                        </span>
                                    </template>
                                    <!-- Status Gaji vs 1,2x UMP -->
                                    <template v-else-if="column.key === 'salary_comparison'">
                                        <span
                                            v-if="rec[column.key] === 'above'"
                                            class="inline-flex items-center rounded bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-[#0D542B] border border-emerald-200"
                                        >
                                            Di atas 1,2× UMP (Memenuhi)
                                        </span>
                                        <span
                                            v-else-if="rec[column.key] === 'below'"
                                            class="inline-flex items-center rounded bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700 border border-slate-200"
                                        >
                                            Di bawah 1,2× UMP
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center rounded bg-slate-50 px-2 py-0.5 text-[11px] font-normal text-slate-500 border border-slate-200/60"
                                        >
                                            Belum dapat dibandingkan
                                        </span>
                                    </template>
                                    <!-- Memenuhi IKU -->
                                    <template v-else-if="column.key === 'qualifies'">
                                        <span
                                            v-if="rec[column.key] === true || rec[column.key] === 'Ya'"
                                            class="inline-flex items-center rounded bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-[#0D542B] border border-emerald-200"
                                        >
                                            Ya
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center rounded bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-500 border border-slate-200"
                                        >
                                            Tidak
                                        </span>
                                    </template>
                                    <template v-else>
                                        <span :class="column.key === 'nama' ? 'font-semibold text-slate-900' : ''">
                                            {{ formatDetailValue(rec[column.key], column.key) }}
                                        </span>
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <table v-else class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 bg-white/60 text-slate-600">
                                <th class="p-2 font-bold">Nama Alumni</th>
                                <th class="p-2 font-bold">NIM</th>
                                <th class="p-2 font-bold">{{ drillDownStudyProgramHeader }}</th>
                                <th v-if="drillDownHasCompany" class="p-2 font-bold">Nama Perusahaan / Usaha</th>
                                <template v-if="drillDownHasRegionalSplit">
                                    <th class="p-2 font-bold">Provinsi</th>
                                    <th class="p-2 font-bold">Kabupaten / Kota</th>
                                    <th class="p-2 font-bold">Negara</th>
                                </template>
                                <th v-else-if="drillDownHasRegion" class="p-2 font-bold">Wilayah / Region</th>
                                <th class="p-2 font-bold">Keterangan / Jawaban</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(rec, idx) in paginatedDrillDownRecords" :key="idx" class="hover:bg-white/80">
                                <td class="p-2 font-semibold text-slate-900">{{ rec.nama || '-' }}</td>
                                <td class="p-2 font-mono text-slate-600">{{ rec.nim || '-' }}</td>
                                <td class="p-2 text-slate-700">
                                    <template v-if="rec.study_program && rec.study_program !== '-' && rec.study_program !== rec.nama_prodi">
                                        <div class="font-semibold text-slate-900">{{ rec.study_program }}</div>
                                        <div class="text-[11px] text-slate-500">Asal S1 UKDW: {{ rec.nama_prodi || '-' }}</div>
                                    </template>
                                    <template v-else>
                                        {{ rec.nama_prodi || '-' }}
                                    </template>
                                </td>
                                <td v-if="drillDownHasCompany" class="p-2 font-medium text-slate-900">
                                    {{ rec.nama_perusahaan || '-' }}
                                </td>
                                <template v-if="drillDownHasRegionalSplit">
                                    <td class="p-2 text-slate-700">
                                        {{ rec.provinsi || '-' }}
                                    </td>
                                    <td class="p-2 text-slate-700">
                                        {{ rec.kabupaten || '-' }}
                                    </td>
                                    <td class="p-2 text-slate-700">
                                        <span v-if="rec.nama_negara && rec.nama_negara !== '-'" class="rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-700">
                                            {{ rec.nama_negara }}
                                        </span>
                                        <span v-else>-</span>
                                    </td>
                                </template>
                                <td v-else-if="drillDownHasRegion" class="p-2 text-slate-600">
                                    <span v-if="rec.wilayah && rec.wilayah !== '-'" class="rounded bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700">
                                        {{ rec.wilayah }}
                                    </span>
                                    <span v-else-if="rec.province_name && rec.province_name !== '-'" class="rounded bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700">
                                        {{ rec.province_name }}
                                    </span>
                                    <span v-else-if="rec.skala && rec.skala !== '-'" class="rounded bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700">
                                        {{ rec.skala }}
                                    </span>
                                    <span v-else>-</span>
                                </td>
                                <td class="p-2 font-medium text-[#0D542B]">{{ recordDisplayAnswer(rec) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination drill-down mengikuti label dan kontrol bersama. -->
                <PaginationControls
                    class="mt-3 border-emerald-200/40 pt-2"
                    :total="filteredDrillDownRecords.length"
                    :current-page="drillDownPage"
                    :page-size="drillDownPageSize"
                    :show-summary="false"
                    @update:current-page="drillDownPage = $event"
                />
            </div>
        </div>

        <!-- TAMPILAN 2: MODE DATA RINGKASAN (TABEL) -->
        <div v-else class="mt-4 space-y-3">
            <div v-if="summaryRows.length > 0" class="overflow-x-auto rounded-lg border border-emerald-200 bg-emerald-50/20">
                <div class="border-b border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-bold text-[#0D542B]">
                    Pivot per Program Studi
                </div>
                <table class="w-full min-w-max text-left text-xs">
                    <thead>
                        <tr class="border-b border-emerald-200 bg-white/70 text-slate-700">
                            <th v-for="column in summaryColumns" :key="column.key" class="p-2.5 font-bold" :class="column.align === 'right' ? 'text-right' : 'text-left'">
                                {{ column.label }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-emerald-100 bg-white/60">
                        <tr v-for="(row, index) in summaryRows" :key="index" class="hover:bg-emerald-50/50">
                            <td v-for="column in summaryColumns" :key="column.key" class="p-2.5" :class="column.align === 'right' ? 'text-right font-mono' : ''">
                                {{ row[column.key] ?? '-' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2">
                    <input
                        v-model="tableSearch"
                        type="text"
                        placeholder="Saring data ringkasan..."
                        class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs text-slate-800 placeholder-slate-400 outline-none focus:bg-white focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] sm:w-64"
                    />
                    <button
                        v-if="activeRows.length > 0"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:text-[#0D542B] hover:border-[#0D542B]/40 transition-colors shadow-2xs cursor-pointer whitespace-nowrap"
                        @click="handleDownloadSummaryCsv"
                        title="Unduh tabel ringkasan ke format Excel (CSV)"
                    >
                        Unduh Excel Ringkasan
                    </button>
                </div>
                <span class="text-xs text-slate-500">
                    Menampilkan {{ activeRows.length }} baris data
                </span>
            </div>

            <div class="overflow-x-auto rounded-lg border border-slate-200">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-slate-700">
                            <th
                                v-for="(col, idx) in activeColumns"
                                :key="col.key"
                                class="p-2.5 font-bold cursor-pointer select-none hover:bg-slate-100 transition-colors"
                                :class="col.align === 'right' ? 'text-right' : 'text-left'"
                                @click="toggleSort(idx)"
                            >
                                <div class="inline-flex items-center gap-1">
                                    <span>{{ col.label }}</span>
                                    <span v-if="sortColumn === idx" class="text-[#0D542B] text-[10px]">
                                        {{ sortDirection === 'asc' ? '▲' : '▼' }}
                                    </span>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="(row, idx) in paginatedRows" :key="idx" class="hover:bg-slate-50/70 transition-colors">
                            <td
                                v-for="col in activeColumns"
                                :key="col.key"
                                class="p-2.5"
                                :class="tableCellClass(col, row)"
                            >
                                {{ row[col.key] ?? '-' }}
                            </td>
                        </tr>
                        <tr v-if="paginatedRows.length === 0">
                            <td :colspan="activeColumns.length || 1" class="p-6 text-center text-slate-400">
                                Tidak ada data yang sesuai dengan pencarian.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination tabel ringkasan mengikuti label dan kontrol bersama. -->
            <PaginationControls
                :total="activeRows.length"
                :current-page="currentPage"
                :page-size="rowsPerPage"
                :show-summary="false"
                @update:current-page="currentPage = $event"
            />

            <!-- Tabel lengkap records nyata indikator aktif, tanpa menampilkan field teknis/kontak pribadi. -->
            <section v-if="detailColumns.length" class="mt-6 border-t border-slate-200 pt-4">
                <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <h4 class="text-sm font-extrabold text-slate-900">Rincian Data Individu</h4>
                            <span v-if="activeSelectionLabel" class="inline-flex items-center gap-1 rounded bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-[#0D542B] border border-emerald-200">
                                Tersaring: {{ activeSelectionLabel }}
                                <button type="button" class="ml-1 text-slate-400 hover:text-slate-700 cursor-pointer" title="Reset filter" @click="resetSelection">×</button>
                            </span>
                        </div>
                        <p class="mt-0.5 text-xs text-slate-500">
                            <span v-if="activeSelectionLabel">Menampilkan {{ filteredDetailRows.length }} alumni yang berkaitan dengan "{{ activeSelectionLabel }}".</span>
                            <span v-else>Menampilkan {{ filteredDetailRows.length }} alumni dari seluruh responden indikator ini.</span>
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Filter Terpadu Kelayakan & Kategori Karier -->
                        <template v-if="hasIkuOutcomeFields">
                            <select
                                v-model="globalQualFilter"
                                class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-800 outline-none focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B]"
                                aria-label="Filter status kelayakan IKU"
                            >
                                <option v-for="option in qualFilterOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                            <select
                                v-model="globalCareerFilter"
                                class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-800 outline-none focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B]"
                                aria-label="Filter kategori aktivitas"
                            >
                                <option v-for="option in careerFilterOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                        </template>
                        <input
                            v-model="detailSearch"
                            type="text"
                            placeholder="Cari nama, prodi, status..."
                            class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs text-slate-800 placeholder-slate-400 outline-none focus:border-[#0D542B] focus:bg-white focus:ring-1 focus:ring-[#0D542B] sm:w-56"
                        />
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:text-[#0D542B] hover:border-[#0D542B]/40 transition-colors shadow-2xs cursor-pointer whitespace-nowrap"
                            @click="handleDownloadDetailCsv"
                            title="Unduh seluruh rincian individu yang sedang ditampilkan ke berkas Excel (CSV)"
                        >
                            Unduh Excel (CSV)
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-lg border border-slate-200">
                    <table class="min-w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50 text-slate-700">
                                <th
                                    v-for="column in detailColumns"
                                    :key="column.key"
                                    class="whitespace-nowrap border-r border-slate-200 px-3 py-2.5 font-bold last:border-r-0"
                                    :title="column.key === 'salary_comparison' ? 'Kategori perbandingan pendapatan terhadap ambang batas 1,2× UMP provinsi tempat bekerja' : column.label"
                                >
                                    <button type="button" class="inline-flex items-center gap-1 cursor-pointer hover:text-[#0D542B]" @click="toggleDetailSort(column.key)">
                                        <span>{{ column.label }}</span>
                                        <span v-if="detailSortKey === column.key" class="text-[10px] text-[#0D542B]">
                                            {{ detailSortDirection === 'asc' ? '▲' : '▼' }}
                                        </span>
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-for="(row, rowIndex) in paginatedDetailRows" :key="rowIndex" class="hover:bg-slate-50/70">
                                <td v-for="column in detailColumns" :key="column.key" class="max-w-72 border-r border-slate-100 px-3 py-2.5 align-top text-slate-700 last:border-r-0">
                                    <!-- Status Aktivitas / Karier -->
                                    <template v-if="column.key === 'career_status_label' || column.key === 'status_karier'">
                                        <span
                                            v-if="recordIsCombination(row)"
                                            class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-0.5 text-[11px] font-semibold text-indigo-700 border border-indigo-200"
                                            title="Alumni memiliki rekam jejak bekerja/wirausaha sekaligus studi lanjut"
                                        >
                                            Kombinasi: {{ row[column.key] || 'Bekerja + Studi' }}
                                        </span>
                                        <span
                                            v-else-if="String(row[column.key] || '').toLowerCase().includes('pendidikan') || String(row[column.key] || '').toLowerCase().includes('studi')"
                                            class="inline-flex items-center rounded-md bg-sky-50 px-2 py-0.5 text-[11px] font-semibold text-sky-700 border border-sky-200"
                                        >
                                            Melanjutkan Studi
                                        </span>
                                        <span
                                            v-else-if="String(row[column.key] || '').toLowerCase().includes('founder') || String(row[column.key] || '').toLowerCase().includes('wirausaha')"
                                            class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-[#0D542B] border border-emerald-200"
                                        >
                                            Founder / Wirausaha
                                        </span>
                                        <span
                                            v-else-if="String(row[column.key] || '').toLowerCase().includes('freelance')"
                                            class="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-800 border border-amber-200"
                                        >
                                            Freelancer
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700 border border-slate-200"
                                        >
                                            {{ row[column.key] || 'Karyawan' }}
                                        </span>
                                    </template>
                                    <!-- Status Gaji vs 1,2x UMP -->
                                    <template v-else-if="column.key === 'salary_comparison'">
                                        <span
                                            v-if="row[column.key] === 'above'"
                                            class="inline-flex items-center rounded bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-[#0D542B] border border-emerald-200"
                                        >
                                            Di atas 1,2× UMP (Memenuhi)
                                        </span>
                                        <span
                                            v-else-if="row[column.key] === 'below'"
                                            class="inline-flex items-center rounded bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700 border border-slate-200"
                                        >
                                            Di bawah 1,2× UMP
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center rounded bg-slate-50 px-2 py-0.5 text-[11px] font-normal text-slate-500 border border-slate-200/60"
                                        >
                                            Belum dapat dibandingkan
                                        </span>
                                    </template>
                                    <template v-else-if="column.key === 'qualifies'">
                                        <span
                                            v-if="row[column.key] === true || row[column.key] === 'Ya'"
                                            class="inline-flex items-center rounded bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-[#0D542B] border border-emerald-200"
                                        >
                                            Ya
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center rounded bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600 border border-slate-200"
                                        >
                                            Tidak
                                        </span>
                                    </template>
                                    <template v-else>
                                        <span class="whitespace-normal break-words">{{ formatDetailValue(row[column.key], column.key) }}</span>
                                    </template>
                                </td>
                            </tr>
                            <tr v-if="paginatedDetailRows.length === 0">
                                <td :colspan="detailColumns.length" class="p-6 text-center text-slate-400">
                                    Tidak ada rincian individu yang sesuai dengan pencarian.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination tabel detail individu mengikuti label dan kontrol bersama. -->
                <PaginationControls
                    :total="sortedDetailRows.length"
                    :current-page="detailPage"
                    :page-size="detailPageSize"
                    :show-summary="false"
                    @update:current-page="detailPage = $event"
                />
            </section>
        </div>
    </div>
</template>
