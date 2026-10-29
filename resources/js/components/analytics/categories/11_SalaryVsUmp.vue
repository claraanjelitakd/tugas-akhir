<script setup>
/**
 * Komponen Indikator 11: Pendapatan per Bulan dan Ambang 1,2× UMP Provinsi.
 *
 * Mengelola visualisasi pendapatan alumni dengan standar analitik mendalam:
 * 1. Line/Bar: Median Gaji per Program Studi (Lengkap dengan Min, Q1, Median, Q3, Max, nama tidak terpotong)
 * 2. Bar per Prodi: Pemenuhan Ambang 1,2× UMP per Jurusan beserta daftar alumni belum memenuhi ambang
 * 3. Peta Spasial Interaktif: Sebaran provinsi kerja, jumlah alumni, dan perbandingan UMP
 */
import { computed, ref } from 'vue';
import AnalyticsTabbedContainer from '../AnalyticsTabbedContainer.vue';
import {
    baseOption,
    escapeHtml,
    formatCompactCurrency,
    formatFullCurrency,
    formatNumber,
    formatPercentage,
} from '../analyticsChartOptions';

// Kontrak properti data indikator, tahun UMP, dan status loading
const props = defineProps({
    chart: { type: Object, default: () => ({}) },
    selectedLabel: { type: String, default: '' },
    heatmapMode: { type: String, default: 'count' },
    availableUmpYears: { type: Array, default: () => [] },
    selectedUmpYear: { type: String, default: '' },
    loading: { type: Boolean, default: false },
});

// Event seleksi data, perubahan tahun UMP, dan klik provinsi
const emit = defineEmits(['select', 'reset', 'change-ump-year', 'select-province']);

// Ekstraksi catatan responden dan ringkasan
const records = computed(() => props.chart?.records || []);
const summary = computed(() => props.chart?.summary || {});
const validResponses = computed(() => records.value.length);

// Helper perhitungan kuartil statistik
const getQuantile = (sorted, percentile) => {
    if (!sorted.length) return 0;
    const position = (sorted.length - 1) * percentile;
    const base = Math.floor(position);
    const rest = position - base;
    return sorted[base + 1] === undefined ? sorted[base] : sorted[base] + rest * (sorted[base + 1] - sorted[base]);
};

// -----------------------------------------------------------------------------
// 1. STATISTIK LENGKAP PENDAPATAN PER PRODI (Min, Q1, Median, Q3, Max)
// Menggabungkan 3 Garis: Q1, Median (Q2 di-highlight), dan Q3
// -----------------------------------------------------------------------------
const prodisList = computed(() => [...new Set(records.value.map((r) => r.nama_prodi || 'Tanpa prodi'))]);

const prodiSalaryStats = computed(() => {
    return prodisList.value.map((prodi) => {
        const prodiRows = records.value.filter((r) => (r.nama_prodi || 'Tanpa prodi') === prodi);
        const salaries = prodiRows.map((r) => Number(r.salary)).filter((s) => Number.isFinite(s) && s > 0).sort((a, b) => a - b);
        const count = salaries.length;

        if (count === 0) {
            return { prodi, count: 0, min: 0, q1: 0, median: 0, q3: 0, max: 0 };
        }

        return {
            prodi,
            count,
            min: salaries[0],
            q1: getQuantile(salaries, 0.25),
            median: getQuantile(salaries, 0.5),
            q3: getQuantile(salaries, 0.75),
            max: salaries[salaries.length - 1],
        };
    }).filter((item) => item.count > 0).sort((a, b) => b.median - a.median);
});

const prodiSalaryOption = computed(() => {
    const list = prodiSalaryStats.value;
    const labels = list.map((item) => `${item.prodi}\n(n=${item.count})`);

    return {
        ...baseOption(validResponses.value),
        legend: {
            data: ['Kuartil 1 (Q1)', 'Median Pendapatan (Q2)', 'Kuartil 3 (Q3)'],
            bottom: 0,
            icon: 'circle',
            textStyle: { color: '#334155', fontWeight: 600, fontSize: 11 },
        },
        grid: { left: 24, right: 36, top: 32, bottom: 80, containLabel: true },
        xAxis: {
            type: 'category',
            data: labels,
            axisLabel: {
                color: '#334155',
                fontSize: 11,
                interval: 0,
                rotate: 30,
                lineHeight: 14,
            },
            axisLine: { lineStyle: { color: '#CBD5E1' } },
        },
        yAxis: {
            type: 'value',
            name: 'Pendapatan (Rupiah)',
            axisLabel: { color: '#64748B', formatter: formatCompactCurrency },
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
        },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'axis',
            formatter: (params) => {
                const item = list[params[0]?.dataIndex ?? 0] || {};
                return `
                    <div class="space-y-1">
                        <p class="font-extrabold text-sm border-b pb-1 text-slate-800">${escapeHtml(item.prodi)} (n=${item.count})</p>
                        <p class="text-xs">Tertinggi (Max): <strong>${formatFullCurrency(item.max)}</strong></p>
                        <p class="text-xs text-slate-700">Kuartil 3 (Q3): <strong>${formatFullCurrency(item.q3)}</strong></p>
                        <p class="text-xs text-[#0D542B]"><strong>Median (Q2): ${formatFullCurrency(item.median)}</strong></p>
                        <p class="text-xs text-slate-500">Kuartil 1 (Q1): <strong>${formatFullCurrency(item.q1)}</strong></p>
                        <p class="text-xs">Terendah (Min): <strong>${formatFullCurrency(item.min)}</strong></p>
                    </div>
                `;
            },
        },
        series: [
            {
                name: 'Kuartil 1 (Q1)',
                type: 'line',
                data: list.map((i) => i.q1),
                itemStyle: { color: '#64748B' },
                lineStyle: { width: 2, type: 'dashed', color: '#64748B' },
                symbol: 'circle',
                symbolSize: 6,
            },
            {
                name: 'Median Pendapatan (Q2)',
                type: 'line',
                data: list.map((i) => i.median),
                itemStyle: { color: '#0D542B' },
                lineStyle: { width: 3.5, color: '#0D542B' },
                symbol: 'circle',
                symbolSize: 9,
                emphasis: { scale: 1.5 },
                label: {
                    show: true,
                    position: 'top',
                    color: '#0D542B',
                    fontWeight: 'bold',
                    fontSize: 10,
                    formatter: ({ value }) => formatCompactCurrency(value),
                },
            },
            {
                name: 'Kuartil 3 (Q3)',
                type: 'line',
                data: list.map((i) => i.q3),
                itemStyle: { color: '#334155' },
                lineStyle: { width: 2, type: 'dashed', color: '#334155' },
                symbol: 'circle',
                symbolSize: 6,
            },
        ],
    };
});

const prodiSalaryTable = computed(() => ({
    columns: [
        { key: 'prodi', label: 'Program Studi' },
        { key: 'count', label: 'Sampel (n)', align: 'right' },
        { key: 'min', label: 'Terendah (Min)', align: 'right' },
        { key: 'q1', label: 'Q1 (25%)', align: 'right' },
        { key: 'median', label: 'Median (Q2)', align: 'right' },
        { key: 'q3', label: 'Q3 (75%)', align: 'right' },
        { key: 'max', label: 'Tertinggi (Max)', align: 'right' },
    ],
    rows: prodiSalaryStats.value.map((item) => ({
        prodi: item.prodi,
        count: formatNumber(item.count, 0),
        min: formatFullCurrency(item.min),
        q1: formatFullCurrency(item.q1),
        median: formatFullCurrency(item.median),
        q3: formatFullCurrency(item.q3),
        max: formatFullCurrency(item.max),
    })),
}));

// -----------------------------------------------------------------------------
// 2. GAJI VS AMBANG 1,2× UMP PER PROGRAM STUDI & RINCIAN ALUMNI
// -----------------------------------------------------------------------------
const prodiUmpData = computed(() => {
    return prodisList.value.map((prodi) => {
        const rows = records.value.filter((r) => (r.nama_prodi || 'Tanpa prodi') === prodi);
        const total = rows.length;
        const meets = rows.filter((r) => r.meets_ump).length;
        const below = total - meets;
        const meetsPct = total > 0 ? Number(((meets / total) * 100).toFixed(1)) : 0;
        const belowPct = total > 0 ? Number(((below / total) * 100).toFixed(1)) : 0;

        return {
            prodi,
            total,
            meets,
            below,
            meetsPct,
            belowPct,
        };
    }).filter((i) => i.total > 0).sort((a, b) => b.meetsPct - a.meetsPct);
});

const prodiUmpOption = computed(() => {
    const list = prodiUmpData.value;
    const labels = list.map((i) => `${i.prodi} (n=${i.total})`);

    return {
        ...baseOption(validResponses.value),
        legend: {
            data: ['Memenuhi 1,2× UMP', 'Belum Memenuhi'],
            bottom: 0,
            icon: 'circle',
            textStyle: { color: '#334155', fontWeight: 600, fontSize: 12 },
        },
        grid: { left: 24, right: 36, top: 20, bottom: 56, containLabel: true },
        xAxis: {
            type: 'value',
            max: 100,
            axisLabel: { formatter: '{value}%' },
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
        },
        yAxis: {
            type: 'category',
            inverse: true,
            data: labels,
            axisLabel: { color: '#334155', fontSize: 11, width: 190, overflow: 'break' },
        },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'item',
            formatter: ({ data }) => {
                if (!data) return '';
                return `<strong>${escapeHtml(data.prodi)}</strong><br>${escapeHtml(data.name)}: <strong>${data.count} alumni (${data.value}%)</strong><br>Total alumni prodi: n=${data.total}`;
            },
        },
        series: [
            {
                name: 'Memenuhi 1,2× UMP',
                type: 'bar',
                stack: 'total',
                barMaxWidth: 26,
                itemStyle: { color: '#0D542B', borderColor: '#FFFFFF', borderWidth: 1 },
                data: list.map((i) => ({ value: i.meetsPct, count: i.meets, total: i.total, prodi: i.prodi, name: 'Memenuhi 1,2× UMP' })),
                label: { show: true, position: 'inside', formatter: ({ data }) => data.value > 0 ? `${data.value}%` : '', color: '#FFFFFF', fontWeight: 'bold', fontSize: 10 },
            },
            {
                name: 'Belum Memenuhi',
                type: 'bar',
                stack: 'total',
                barMaxWidth: 26,
                itemStyle: { color: '#94A3B8', borderColor: '#FFFFFF', borderWidth: 1 },
                data: list.map((i) => ({ value: i.belowPct, count: i.below, total: i.total, prodi: i.prodi, name: 'Belum Memenuhi' })),
                label: { show: true, position: 'inside', formatter: ({ data }) => data.value > 0 ? `${data.value}%` : '', color: '#FFFFFF', fontWeight: 'bold', fontSize: 10 },
            },
        ],
    };
});

const prodiUmpTable = computed(() => ({
    columns: [
        { key: 'prodi', label: 'Program Studi' },
        { key: 'total', label: 'Total Alumni (n)', align: 'right' },
        { key: 'meets', label: 'Memenuhi 1,2× UMP', align: 'right' },
        { key: 'below', label: 'Belum Memenuhi', align: 'right' },
    ],
    rows: prodiUmpData.value.map((i) => ({
        prodi: i.prodi,
        total: formatNumber(i.total, 0),
        meets: `${i.meets} (${formatPercentage(i.meetsPct)})`,
        below: `${i.below} (${formatPercentage(i.belowPct)})`,
    })),
}));

// Rincian spesifik alumni yang BELUM memenuhi ambang 1,2x UMP
const alumniBelowUmp = computed(() => {
    return records.value.filter((r) => !r.meets_ump);
});

// -----------------------------------------------------------------------------
// 3. TABEL & GRAFIK STATUS PEMENUHAN 1,2× UMP PER PROVINSI
// (Menggantikan peta spasial sesuai instruksi pengguna: list provinsi dan status pemenuhan)
// -----------------------------------------------------------------------------
const provinceStats = computed(() => {
    const provinceMap = new Map();
    records.value.forEach((r) => {
        const prov = r.workplace_province_name || r.province_name || 'Tidak Teridentifikasi';
        const cur = provinceMap.get(prov) || {
            province: prov,
            total: 0,
            meets: 0,
            below: 0,
            salaries: [],
        };
        cur.total += 1;
        if (r.meets_ump) {
            cur.meets += 1;
        } else {
            cur.below += 1;
        }
        if (Number(r.salary) > 0) {
            cur.salaries.push(Number(r.salary));
        }
        provinceMap.set(prov, cur);
    });

    return [...provinceMap.values()].map((p) => {
        const sorted = p.salaries.sort((a, b) => a - b);
        const median = sorted.length ? getQuantile(sorted, 0.5) : 0;
        const meetsPct = p.total > 0 ? Number(((p.meets / p.total) * 100).toFixed(1)) : 0;
        const belowPct = p.total > 0 ? Number(((p.below / p.total) * 100).toFixed(1)) : 0;
        let statusLabel = 'Perlu Perhatian';
        if (meetsPct >= 80) statusLabel = 'Memenuhi Mayoritas';
        else if (meetsPct >= 50) statusLabel = 'Sebagian Memenuhi';

        return {
            province: p.province,
            total: p.total,
            meets: p.meets,
            below: p.below,
            meetsPct,
            belowPct,
            medianSalary: median,
            statusLabel,
        };
    }).sort((a, b) => b.total - a.total);
});

const provinceOption = computed(() => {
    const list = provinceStats.value;
    const labels = list.map((i) => `${i.province} (n=${i.total})`);

    return {
        ...baseOption(validResponses.value),
        legend: {
            data: ['Memenuhi 1,2× UMP', 'Belum Memenuhi'],
            bottom: 0,
            icon: 'circle',
            textStyle: { color: '#334155', fontWeight: 600, fontSize: 11 },
        },
        grid: { left: 24, right: 36, top: 20, bottom: 56, containLabel: true },
        xAxis: {
            type: 'value',
            max: 100,
            axisLabel: { formatter: '{value}%' },
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
        },
        yAxis: {
            type: 'category',
            inverse: true,
            data: labels,
            axisLabel: { color: '#334155', fontSize: 11, width: 180, overflow: 'break' },
        },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'item',
            formatter: ({ data }) => {
                if (!data) return '';
                return `<strong>${escapeHtml(data.province)}</strong><br>${escapeHtml(data.name)}: <strong>${data.count} alumni (${data.value}%)</strong><br>Total alumni di provinsi ini: n=${data.total}`;
            },
        },
        series: [
            {
                name: 'Memenuhi 1,2× UMP',
                type: 'bar',
                stack: 'total',
                barMaxWidth: 24,
                itemStyle: { color: '#0D542B', borderColor: '#FFFFFF', borderWidth: 1 },
                data: list.map((i) => ({ value: i.meetsPct, count: i.meets, total: i.total, province: i.province, name: 'Memenuhi 1,2× UMP' })),
                label: { show: true, position: 'inside', formatter: ({ data }) => data.value > 0 ? `${data.value}%` : '', color: '#FFFFFF', fontWeight: 'bold', fontSize: 10 },
            },
            {
                name: 'Belum Memenuhi',
                type: 'bar',
                stack: 'total',
                barMaxWidth: 24,
                itemStyle: { color: '#94A3B8', borderColor: '#FFFFFF', borderWidth: 1 },
                data: list.map((i) => ({ value: i.belowPct, count: i.below, total: i.total, province: i.province, name: 'Belum Memenuhi' })),
                label: { show: true, position: 'inside', formatter: ({ data }) => data.value > 0 ? `${data.value}%` : '', color: '#FFFFFF', fontWeight: 'bold', fontSize: 10 },
            },
        ],
    };
});

const provinceStatusTable = computed(() => ({
    columns: [
        { key: 'province', label: 'Provinsi Penempatan' },
        { key: 'total', label: 'Alumni (n)', align: 'right' },
        { key: 'meetsDisplay', label: 'Memenuhi 1,2× UMP', align: 'right' },
        { key: 'belowDisplay', label: 'Belum Memenuhi', align: 'right' },
        { key: 'percentageDisplay', label: '% Memenuhi', align: 'right' },
        { key: 'medianDisplay', label: 'Median Gaji', align: 'right' },
        { key: 'status', label: 'Status Kelayakan', align: 'center' },
    ],
    rows: provinceStats.value.map((p) => ({
        province: p.province,
        total: formatNumber(p.total, 0),
        meetsDisplay: `${p.meets} alumni`,
        belowDisplay: `${p.below} alumni`,
        percentageDisplay: `${p.meetsPct}%`,
        medianDisplay: formatFullCurrency(p.medianSalary),
        status: p.statusLabel,
    })),
}));

// -----------------------------------------------------------------------------
// ANALISIS DINAMIS BERDASARKAN DATA AKTUAL
// -----------------------------------------------------------------------------
const dynamicSalaryAnalysis = computed(() => {
    const list = prodiSalaryStats.value;
    if (list.length === 0) {
        return {
            title: 'Kilas Capaian Finansial Alumni',
            statusBadge: 'Distribusi Lengkap',
            badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
            lead: 'Data pendapatan alumni sedang dimuat.',
            detail: '',
        };
    }

    const top = list[0];
    const bottom = list[list.length - 1];
    const rincian = list.map((i, idx) => `${idx + 1}. ${i.prodi}: Median ${formatCompactCurrency(i.median)} (Q1: ${formatCompactCurrency(i.q1)}, Q3: ${formatCompactCurrency(i.q3)})`).join(', ');

    return {
        title: 'Kilas Capaian Finansial Alumni',
        statusBadge: 'Distribusi Lengkap',
        badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
        lead: `Distribusi pendapatan alumni per program studi menunjukkan perbandingan sebaran 5 angka statistik. Program studi dengan median pendapatan tertinggi adalah ${top.prodi} (${formatFullCurrency(top.median)}), sedangkan terendah adalah ${bottom.prodi} (${formatFullCurrency(bottom.median)}).`,
        detail: `Rincian median dan rentang interkuartil (Q1 - Q3) seluruh program studi: ${rincian}.`,
    };
});

const dynamicUmpProdiAnalysis = computed(() => {
    const list = prodiUmpData.value;
    if (list.length === 0) {
        return {
            title: 'Kilas Kelayakan Pendapatan Wilayah',
            statusBadge: 'Standar 1,2× UMP',
            badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
            lead: 'Data pemenuhan ambang UMP sedang dimuat.',
            detail: '',
        };
    }

    const top = list[0];
    const bottom = list[list.length - 1];
    const rincian = list.map((i, idx) => `${idx + 1}. ${i.prodi}: ${i.meetsPct}% (${i.meets}/${i.total} alumni)`).join(', ');

    return {
        title: 'Kilas Kelayakan Pendapatan Wilayah',
        statusBadge: 'Standar 1,2× UMP',
        badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
        lead: `Sebanyak ${formatPercentage(summary.value.meeting_percentage || 0)} alumni yang dapat dibandingkan telah memenuhi atau melampaui ambang 1,2× UMP provinsi tempat bekerja. Program studi dengan tingkat pemenuhan tertinggi adalah ${top.prodi} (${top.meetsPct}%), sedangkan terendah adalah ${bottom.prodi} (${bottom.meetsPct}%).`,
        detail: `Rincian persentase pemenuhan ambang 1,2× UMP per program studi: ${rincian}. Terdapat ${alumniBelowUmp.value.length} alumni yang belum memenuhi ambang.`,
    };
});

const dynamicProvinceAnalysis = computed(() => {
    const list = provinceStats.value;
    if (list.length === 0) {
        return {
            title: 'Kilas Kelayakan per Provinsi',
            statusBadge: 'Sebaran Provinsi',
            badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
            lead: 'Data penempatan provinsi sedang dimuat.',
            detail: '',
        };
    }

    const top = list[0];
    const rincian = list.slice(0, 5).map((p, idx) => `${idx + 1}. ${p.province}: ${p.meetsPct}% memenuhi (${p.total} alumni, median ${formatCompactCurrency(p.medianSalary)})`).join(', ');

    return {
        title: 'Kilas Kelayakan Pendapatan per Provinsi',
        statusBadge: `${list.length} Provinsi`,
        badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
        lead: `Lulusan UKDW tersebar aktif di ${list.length} provinsi dengan konsentrasi penempatan terbesar di ${top.province} (${top.total} alumni · ${top.meetsPct}% memenuhi ambang 1,2× UMP).`,
        detail: `Rincian 5 provinsi penempatan terbanyak: ${rincian}. Tabel di bawah menyajikan daftar lengkap seluruh provinsi penempatan beserta status pemenuhan ambang.`,
    };
});

// Konfigurasi views untuk kontainer terpadu: Bebas Scatter, Bebas Boxplot, Rapi & Utuh
const views = computed(() => [
    {
        key: 'prodi_salary_line',
        tabLabel: 'Median & Kuartil Prodi',
        title: 'Statistik pendapatan per bulan per program studi',
        description: 'Menampilkan diagram garis 3 parameter utama: Kuartil 1 (Q1), Median (Q2 di-highlight), dan Kuartil 3 (Q3) per program studi.',
        option: prodiSalaryOption.value,
        tableData: prodiSalaryTable.value,
        height: 410,
        analysis: dynamicSalaryAnalysis.value,
    },
    {
        key: 'prodi_ump_bullet',
        tabLabel: 'Gaji vs Ambang UMP',
        title: 'Persentase pemenuhan ambang 1,2× UMP per program studi',
        description: 'Perbandingan proporsi alumni yang berhasil melampaui ambang standar kelayakan pendapatan 1,2× UMP provinsi tempat bekerja.',
        option: prodiUmpOption.value,
        tableData: prodiUmpTable.value,
        height: Math.max(380, prodisList.value.length * 34 + 110),
        analysis: dynamicUmpProdiAnalysis.value,
    },
    {
        key: 'province_status',
        tabLabel: 'Status per Provinsi',
        title: 'Status pemenuhan ambang 1,2× UMP per provinsi penempatan',
        description: 'Daftar provinsi tempat bekerja alumni beserta proporsi pemenuhan ambang 1,2× UMP, median pendapatan daerah, dan status kelayakan.',
        option: provinceOption.value,
        tableData: provinceStatusTable.value,
        height: Math.max(380, provinceStats.value.length * 32 + 100),
        analysis: dynamicProvinceAnalysis.value,
    },
]);
</script>

<template>
    <div class="space-y-6">
        <!-- Kontainer Tabbed Terpadu -->
        <AnalyticsTabbedContainer
            :views="views"
            :rows="records"
            :selected-label="selectedLabel"
            :loading="loading"
            indicator-badge="Indikator 11"
            @select="emit('select', $event)"
            @reset="emit('reset')"
        />

        <!-- Tabel Rincian Alumni yang BELUM Memenuhi Ambang 1,2x UMP -->
        <div class="rounded-xl border border-amber-200 bg-amber-50/20 p-5 shadow-xs transition-all">
            <div class="flex flex-col gap-2 border-b border-amber-200/60 pb-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="rounded bg-amber-600 px-2.5 py-0.5 text-xs font-bold text-white">
                            Data Telusur Ambang
                        </span>
                        <h4 class="text-sm font-extrabold text-slate-900">
                            Daftar Alumni Belum Memenuhi 1,2× UMP ({{ alumniBelowUmp.length }} Alumni)
                        </h4>
                    </div>
                    <p class="mt-1 text-xs text-slate-600">
                        Menampilkan identitas alumni, program studi, provinsi lokasi kerja, pendapatan aktual, serta ambang batas UMP daerah.
                    </p>
                </div>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-amber-100/50 text-[11px] font-bold uppercase text-amber-950">
                        <tr>
                            <th class="px-3 py-2.5">Alumni</th>
                            <th class="px-3 py-2.5">Program Studi</th>
                            <th class="px-3 py-2.5">Perusahaan / Lokasi</th>
                            <th class="px-3 py-2.5 text-right">Gaji Aktual</th>
                            <th class="px-3 py-2.5 text-right">Ambang 1,2× UMP</th>
                            <th class="px-3 py-2.5 text-right">Selisih Kekurangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-amber-100/60 text-slate-800">
                        <tr v-for="r in alumniBelowUmp" :key="r.biodata_id" class="hover:bg-amber-50/60">
                            <td class="px-3 py-2 font-semibold">
                                {{ r.nama }}
                                <span class="block text-[10px] text-slate-400 font-normal">NIM: {{ r.nim }}</span>
                            </td>
                            <td class="px-3 py-2">{{ r.nama_prodi }}</td>
                            <td class="px-3 py-2">
                                <span class="font-medium text-slate-900">{{ r.nama_perusahaan || '-' }}</span>
                                <span class="block text-[10px] text-[#0D542B]">{{ r.workplace_province_name || r.province_name || 'D.I. Yogyakarta' }}</span>
                            </td>
                            <td class="px-3 py-2 text-right font-bold text-slate-900">
                                {{ formatFullCurrency(r.salary) }}
                            </td>
                            <td class="px-3 py-2 text-right font-medium text-slate-600">
                                {{ formatFullCurrency(r.threshold) }}
                            </td>
                            <td class="px-3 py-2 text-right font-bold text-rose-700">
                                -{{ formatFullCurrency(Math.max(0, Number(r.threshold || 0) - Number(r.salary || 0))) }}
                            </td>
                        </tr>
                        <tr v-if="alumniBelowUmp.length === 0">
                            <td colspan="6" class="px-3 py-6 text-center text-slate-500">
                                Seluruh alumni yang dapat dibandingkan telah memenuhi atau melampaui ambang 1,2× UMP.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
