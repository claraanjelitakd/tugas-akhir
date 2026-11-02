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
import {
    analyticsChartTypeReasons,
    analyticsChartTypes,
    escapeHtml,
    formatNumber,
    getAnalyticsChartHeight,
} from './analyticsChartOptions';

// Kontrak properti kontainer terpadu
const props = defineProps({
    // Daftar tampilan visualisasi: [{ key, chartType, tabLabel, title, description, reason, option, tableData, height, analysis, isMap }]
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

// Event seleksi elemen dan reset filter
const emit = defineEmits(['select', 'reset', 'select-province']);

// State tab visualisasi yang sedang aktif
const activeTabKey = ref(props.defaultTab || props.views[0]?.key || '');

// Sinkronisasi jika defaultTab atau views berubah
watch(() => props.views, (newViews) => {
    if (!newViews.some((v) => v.key === activeTabKey.value)) {
        activeTabKey.value = newViews[0]?.key || '';
    }
}, { immediate: true });

// Mode tampilan: 'chart' (grafik visual) atau 'table' (data ringkasan)
const activeViewMode = ref('chart');

// Objek view yang sedang aktif saat ini
const currentView = computed(() => {
    return props.views.find((v) => v.key === activeTabKey.value) || props.views[0] || {};
});

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
    if (rec.degree || rec.university || rec.funding_source) {
        return [rec.degree, rec.university, rec.funding_source].filter(Boolean).join(' · ');
    }
    if (rec.province_name) return `${rec.province_name}${rec.nama_perusahaan ? ` (${rec.nama_perusahaan})` : ''}`;
    if (rec.status || rec.metode || rec.method) return rec.status || rec.metode || rec.method;
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

    // Filter khusus rentang waktu tunggu (histogram bucket min-max bulan)
    if (ptData.min !== undefined && ptData.max !== undefined && props.rows.some((r) => r.months !== undefined)) {
        return props.rows.filter((r) => Number(r.months) >= Number(ptData.min) && Number(r.months) <= Number(ptData.max));
    }
    const bucketMatch = rawTarget.match(/^(\d+)\s*[–-]\s*(\d+)\s*bln/i);
    if (bucketMatch && props.rows.some((r) => r.months !== undefined)) {
        const minM = Number(bucketMatch[1]);
        const maxM = Number(bucketMatch[2]);
        return props.rows.filter((r) => Number(r.months) >= minM && Number(r.months) <= maxM);
    }
    if (/^>=\s*12\s*bln/i.test(rawTarget) && props.rows.some((r) => r.months !== undefined)) {
        return props.rows.filter((r) => Number(r.months) >= 12);
    }

    // Filter khusus status pemenuhan UMP (Indikator 11)
    if ((target.includes('memenuhi') && target.includes('ump')) || (target.includes('di bawah') && target.includes('ump'))) {
        const wantsMeet = target.includes('memenuhi') && !target.includes('belum') && !target.includes('di bawah');
        return props.rows.filter((r) => {
            const matchUmp = Boolean(r.meets_ump) === wantsMeet;
            if (ptProdi) return matchUmp && String(r.nama_prodi || '').toLowerCase().includes(ptProdi);
            return matchUmp;
        });
    }

    // Filter khusus tahapan pipeline lamaran F6 / F7 / F7A (Indikator 06)
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
                r.university, r.province_name,
            ].map((v) => String(v || '').toLowerCase());
            return (rProdi.includes(ptProdi) || ptProdi.includes(rProdi))
                && rFields.some((f) => f && (f.includes(ptCat) || ptCat.includes(f)));
        });
        if (exactBoth.length > 0) return exactBoth;
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
const drillDownPage = ref(1);
const drillDownPageSize = 8;

const drillDownHasCompany = computed(() => {
    return drillDownRecords.value.some((r) => r.nama_perusahaan && r.nama_perusahaan !== '-');
});

const drillDownHasRegionalSplit = computed(() => {
    return drillDownRecords.value.some((r) =>
        (r.provinsi && r.provinsi !== '-') ||
        (r.kabupaten && r.kabupaten !== '-') ||
        (r.negara && r.negara !== '-')
    );
});

const drillDownHasRegion = computed(() => {
    return drillDownRecords.value.some((r) => (r.wilayah && r.wilayah !== '-') || (r.province_name && r.province_name !== '-') || (r.skala && r.skala !== '-'));
});

const filteredDrillDownRecords = computed(() => {
    const q = drillDownSearch.value.trim().toLowerCase();
    if (!q) return drillDownRecords.value;

    return drillDownRecords.value.filter((r) => {
        return String(r.nama || '').toLowerCase().includes(q)
            || String(r.nim || '').toLowerCase().includes(q)
            || String(r.nama_prodi || '').toLowerCase().includes(q)
            || String(r.nama_perusahaan || '').toLowerCase().includes(q)
            || String(r.provinsi || '').toLowerCase().includes(q)
            || String(r.kabupaten || '').toLowerCase().includes(q)
            || String(r.negara || '').toLowerCase().includes(q)
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

        <!-- Header Kartu & Switcher Tab Visualisasi -->
        <div class="flex flex-col justify-between gap-4 border-b border-slate-100 pb-4 lg:flex-row lg:items-start">
            <!-- Kolom Kiri: Judul & Subjudul dengan min-height tetap agar tata letak tidak bergeser -->
            <div class="min-h-[50px] flex-1">
                <div class="flex items-center gap-2">
                    <span v-if="indicatorBadge" class="rounded bg-emerald-50 px-2 py-0.5 text-xs font-bold text-[#0D542B]">
                        {{ indicatorBadge }}
                    </span>
                    <h3 class="text-base font-extrabold text-slate-900">
                        <template v-if="activeViewMode === 'table'">Tabel Data Ringkasan: {{ currentView.tabLabel || currentView.title }}</template>
                        <template v-else>{{ currentView.title }}</template>
                    </h3>
                </div>
                <p class="mt-1 text-xs text-slate-500 leading-relaxed max-w-3xl">
                    <template v-if="activeViewMode === 'table'">
                        Tabel ringkasan tabulasi data indikator yang dapat diurutkan dan dicari.
                    </template>
                    <template v-else>
                        {{ currentView.description }}
                    </template>
                </p>

            </div>

            <!-- Kolom Kanan: Switcher Tab Tetap (Stay di Kanan Atas, Bebas dari Layout Shift) -->
            <div class="shrink-0 flex flex-wrap items-center justify-end gap-2">
                <!-- Switcher Antar Visualisasi (Hanya saat mode chart aktif) -->
                <div v-if="activeViewMode === 'chart' && views.length > 1" class="flex items-center gap-1 rounded-lg bg-slate-100 p-1">
                    <button
                        v-for="view in views"
                        :key="view.key"
                        type="button"
                        class="rounded-md px-3 py-1.5 text-xs font-bold transition-all cursor-pointer whitespace-nowrap"
                        :class="activeTabKey === view.key ? 'bg-[#0D542B] text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                        @click="activeTabKey = view.key"
                    >
                        {{ view.tabLabel || view.title }}
                    </button>
                </div>

                <!-- Switcher Mode: Grafik vs Data Ringkasan -->
                <div class="flex items-center gap-1 rounded-lg border border-slate-200 bg-white p-1">
                    <button
                        type="button"
                        class="rounded-md px-3 py-1.5 text-xs font-semibold transition-all cursor-pointer whitespace-nowrap"
                        :class="activeViewMode === 'chart' ? 'bg-[#0D542B] font-bold text-white shadow-xs' : 'text-slate-500 hover:text-slate-900'"
                        @click="activeViewMode = 'chart'"
                    >
                        Grafik
                    </button>
                    <button
                        type="button"
                        class="rounded-md px-3 py-1.5 text-xs font-semibold transition-all cursor-pointer whitespace-nowrap"
                        :class="activeViewMode === 'table' ? 'bg-slate-100 font-bold text-slate-900' : 'text-slate-500 hover:text-slate-900'"
                        @click="activeViewMode = 'table'"
                    >
                        Data Ringkasan
                    </button>
                </div>
            </div>
        </div>

        <!-- TAMPILAN 1: MODE GRAFIK -->
        <div v-if="activeViewMode === 'chart'" class="mt-4 space-y-4">
            <!-- Toolbar Kembali ke Analisis Keseluruhan saat item grafik dipilih -->
            <div v-if="activeSelectionLabel" class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-emerald-200 bg-emerald-50/40 px-3.5 py-2 text-xs">
                <div class="flex items-center gap-2 text-slate-700">
                    <span class="font-semibold text-slate-500">Analisis Terpilih:</span>
                    <span class="rounded bg-white border border-emerald-200 px-2 py-0.5 font-bold text-[#0D542B]">{{ activeSelectionLabel }}</span>
                </div>
                <button
                    type="button"
                    class="rounded-md border border-slate-200 bg-white px-2.5 py-1 text-xs font-bold text-slate-700 hover:bg-slate-100 cursor-pointer transition-colors"
                    @click="resetSelection"
                >
                    Kembali ke Analisis Keseluruhan
                </button>
            </div>

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

            <!-- Visualisasi ECharts Standar -->
            <figure v-else class="w-full">
                <AnalyticsEChart
                    :option="currentView.option"
                    :height="currentChartHeight"
                    :loading="loading"
                    @select="handleChartClick"
                />
                <figcaption class="mt-2 px-1 text-xs leading-relaxed text-slate-500">
                    <span class="font-semibold text-slate-700">Caption:</span>
                    {{ currentView.caption || currentView.description || currentView.title }}
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
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-emerald-200/60 pb-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="rounded bg-[#0D542B] px-2 py-0.5 text-[11px] font-bold text-white">Rincian Data Terpilih</span>
                            <span class="text-sm font-extrabold text-slate-900">{{ activeSelectionLabel }}</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-600">
                            Menampilkan {{ drillDownRecords.length }} alumni yang berkaitan dengan pilihan ini.
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <input
                            v-model="drillDownSearch"
                            type="text"
                            placeholder="Cari nama / NIM / prodi..."
                            class="rounded border border-slate-200 bg-white px-2.5 py-1 text-xs text-slate-800 placeholder-slate-400 outline-none focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B]"
                        />
                        <button
                            type="button"
                            class="rounded border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100 cursor-pointer"
                            @click="resetSelection"
                        >
                            Kembali ke Analisis Keseluruhan
                        </button>
                    </div>
                </div>

                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 bg-white/60 text-slate-600">
                                <th class="p-2 font-bold">Nama Alumni</th>
                                <th class="p-2 font-bold">NIM</th>
                                <th class="p-2 font-bold">Program Studi</th>
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
                                <td class="p-2 text-slate-700">{{ rec.nama_prodi || '-' }}</td>
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
                                        <span v-if="rec.negara && rec.negara !== '-'" class="rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-700">
                                            {{ rec.negara }}
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

                <!-- Paginasi Drill-down -->
                <div v-if="drillDownTotalPages > 1" class="mt-3 flex items-center justify-between border-t border-emerald-200/40 pt-2 text-xs text-slate-600">
                    <span>Halaman {{ drillDownPage }} dari {{ drillDownTotalPages }}</span>
                    <div class="flex gap-1">
                        <button
                            type="button"
                            :disabled="drillDownPage <= 1"
                            class="rounded border border-slate-200 bg-white px-2 py-0.5 font-bold disabled:opacity-40 cursor-pointer"
                            @click="drillDownPage--"
                        >
                            &larr; Prev
                        </button>
                        <button
                            type="button"
                            :disabled="drillDownPage >= drillDownTotalPages"
                            class="rounded border border-slate-200 bg-white px-2 py-0.5 font-bold disabled:opacity-40 cursor-pointer"
                            @click="drillDownPage++"
                        >
                            Next &rarr;
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAMPILAN 2: MODE DATA RINGKASAN (TABEL) -->
        <div v-else class="mt-4 space-y-3">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <input
                    v-model="tableSearch"
                    type="text"
                    placeholder="Saring data ringkasan..."
                    class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs text-slate-800 placeholder-slate-400 outline-none focus:bg-white focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] sm:w-64"
                />
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
                                :class="[
                                    col.align === 'right' ? 'text-right font-mono' : 'text-left',
                                    col.key.toLowerCase().includes('count') || col.key.toLowerCase().includes('total') ? 'font-semibold text-slate-900' : 'text-slate-700'
                                ]"
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

            <!-- Paginasi Tabel Ringkasan -->
            <div v-if="totalPages > 1" class="flex items-center justify-between pt-1 text-xs text-slate-600">
                <span>Halaman {{ currentPage }} dari {{ totalPages }}</span>
                <div class="flex gap-1">
                    <button
                        type="button"
                        :disabled="currentPage <= 1"
                        class="rounded border border-slate-200 bg-white px-2.5 py-1 font-bold disabled:opacity-40 cursor-pointer hover:bg-slate-50"
                        @click="currentPage--"
                    >
                        &larr; Sebelumnya
                    </button>
                    <button
                        type="button"
                        :disabled="currentPage >= totalPages"
                        class="rounded border border-slate-200 bg-white px-2.5 py-1 font-bold disabled:opacity-40 cursor-pointer hover:bg-slate-50"
                        @click="currentPage++"
                    >
                        Berikutnya &rarr;
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
