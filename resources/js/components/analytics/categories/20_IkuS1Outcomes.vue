<script setup>
/**
 * Komponen indikator capaian karier lulusan S1 (IKU Outcomes).
 *
 * Menampilkan distribusi perolehan bobot IKU per kategori karier dalam bentuk
 * horizontal stacked bar chart. Tiap kategori karier memuat jumlah responden (n)
 * pada label sumbu kiri, dengan segmen bertumpuk yang menampilkan jumlah dan
 * persentase perolehan bobot (1.2, 1.0, 0.8, 0.6, 0.2-0.5, dan 0.0).
 * Sesuai standar Kepmendikbudristek No. 210/M/2023.
 */
import { computed, ref } from 'vue';
import AnalyticsTabbedContainer from '../AnalyticsTabbedContainer.vue';
import { baseOption, formatNumber, formatPercentage } from '../analyticsChartOptions';

// Kontrak payload indikator capaian lulusan dari backend.
const props = defineProps({
    chart: { type: Object, default: () => ({}) },
    selectedLabel: { type: String, default: '' },
    loading: { type: Boolean, default: false },
});

// Event seleksi diteruskan ke panel analitik induk untuk drill-down tabel detail.
const emit = defineEmits(['select', 'reset']);

// State toggle panduan aturan bobot IKU (aktif default agar langsung terbaca).
const showWeightGuide = ref(true);

// Seri kategori karier dan rincian klasifikasi individu.
const series = computed(() => props.chart?.series || []);
const records = computed(() => props.chart?.records || []);
const summary = computed(() => props.chart?.summary || {});

// Definisi tier bobot detail standar Kepmendikbudristek No. 210/M/2023 dengan palet konsisten Response Rate.
const WEIGHT_TIERS = [
    {
        key: 'w12',
        label: 'Bobot 1.2',
        color: '#0D542B',
        textColor: '#FFFFFF',
        match: (w) => Number(w) === 1.2,
        desc: 'Wirausaha omzet ≥ 1,2× UMP & tunggu ≤ 6 bln',
    },
    {
        key: 'w10',
        label: 'Bobot 1.0',
        color: '#0D542B',
        textColor: '#FFFFFF',
        match: (w) => Number(w) === 1.0,
        desc: 'Gaji ≥ 1,2× UMP & tunggu ≤ 6 bln / Wirausaha omzet ≥ 1,2× UMP',
    },
    {
        key: 'w08',
        label: 'Bobot 0.8',
        color: '#D97706',
        textColor: '#FFFFFF',
        match: (w) => Number(w) === 0.8,
        desc: 'Gaji ≥ 1,2× UMP & tunggu 6–12 bln / Wirausaha omzet < 1,2× UMP',
    },
    {
        key: 'w06',
        label: 'Bobot 0.6',
        color: '#D97706',
        textColor: '#FFFFFF',
        match: (w) => Number(w) === 0.6,
        desc: 'Gaji < 1,2× UMP / Studi lanjut ≤ 12 bln',
    },
    {
        key: 'w_prop',
        label: 'Bobot 0.2–0.5',
        color: '#DC2626',
        textColor: '#FFFFFF',
        match: (w) => Number(w) >= 0.2 && Number(w) <= 0.5,
        desc: 'Pekerja lepas / freelancer proporsional',
    },
    {
        key: 'w00',
        label: 'Bobot 0.0 (Tidak Berbobot)',
        color: '#CBD5E1',
        textColor: '#0F172A',
        match: (w) => Number(w) === 0,
        desc: 'Tunggu ≥ 12 bln / Studi > 12 bln / Tanpa posisi terstruktur',
    },
];

// Kelompok segmen grafik bertumpuk selaras dengan 4 warna Response Rate (Abu-abu di kiri, diikuti Merah, Kuning, Hijau).
const CHART_COLOR_TIERS = [
    {
        key: 'tier_00',
        label: 'Abu-abu: Bobot 0.0 (Tidak Berbobot)',
        shortLabel: 'Bobot 0.0',
        color: '#CBD5E1',
        textColor: '#0F172A',
        stackOrder: 0,
        match: (w) => Number(w) === 0,
    },
    {
        key: 'tier_prop',
        label: 'Merah: Bobot 0.2–0.5 (Proporsional)',
        shortLabel: 'Bobot 0.2–0.5',
        color: '#DC2626',
        textColor: '#FFFFFF',
        stackOrder: 1,
        match: (w) => Number(w) >= 0.2 && Number(w) <= 0.5,
    },
    {
        key: 'tier_mid',
        label: 'Kuning: Bobot 0.6–0.8 (Menengah / Studi Lanjut)',
        shortLabel: 'Bobot 0.6–0.8',
        color: '#D97706',
        textColor: '#FFFFFF',
        stackOrder: 2,
        match: (w) => Number(w) === 0.6 || Number(w) === 0.8,
    },
    {
        key: 'tier_high',
        label: 'Hijau: Bobot 1.0–1.2 (Penuh / Utama)',
        shortLabel: 'Bobot 1.0–1.2',
        color: '#0D542B',
        textColor: '#FFFFFF',
        stackOrder: 3,
        match: (w) => Number(w) === 1.0 || Number(w) === 1.2,
    },
];

// Kamus kriteria spesifik per kategori karier sesuai Kepmendikbudristek No. 210/M/2023.
const getCategoryTierDesc = (category, tierKey) => {
    const catLower = (category || '').toLowerCase();
    const isKaryawan = catLower.includes('karyawan') || catLower.includes('bekerja');
    const isFounder = catLower.includes('founder') || catLower.includes('wirausaha');
    const isStudi = catLower.includes('studi') || catLower.includes('pendidikan');
    const isFreelance = catLower.includes('freelancer') || catLower.includes('lepas');

    if (isKaryawan) {
        switch (tierKey) {
            case 'w10': return 'Tunggu ≤ 6 bln & Gaji ≥ 1,2× UMP';
            case 'w08': return 'Tunggu 6–12 bln & Gaji ≥ 1,2× UMP';
            case 'w06': return 'Tunggu ≤ 12 bln & Gaji < 1,2× UMP';
            case 'w00': return 'Tunggu ≥ 12 bln (tidak berbobot)';
            default: return '';
        }
    }

    if (isFounder) {
        switch (tierKey) {
            case 'w12': return 'Tunggu ≤ 6 bln & Omzet ≥ 1,2× UMP';
            case 'w10': return 'Tunggu > 6 bln & Omzet ≥ 1,2× UMP';
            case 'w08': return 'Tunggu ≤ 6 bln & Omzet < 1,2× UMP';
            case 'w06': return 'Tunggu > 6 bln & Omzet < 1,2× UMP';
            case 'w00': return 'Tanpa posisi terstruktur / belum berbobot';
            default: return '';
        }
    }

    if (isStudi) {
        switch (tierKey) {
            case 'w06': return 'Mulai S2/Profesi ≤ 12 bln setelah wisuda';
            case 'w00': return 'Mulai studi > 12 bln atau belum terdata';
            default: return '';
        }
    }

    if (isFreelance) {
        switch (tierKey) {
            case 'w_prop': return 'Pekerja lepas dihitung proporsional pendapatan & masa tunggu';
            case 'w00': return 'Tanpa posisi / jabatan terstruktur';
            default: return '';
        }
    }

    return '';
};

// Opsi grafik horizontal stacked bar chart per kategori karier.
const stackedOption = computed(() => {
    // Label sumbu Y memuat nama kategori karier selaras dengan format sumbu AEE.
    const categories = series.value.map((catItem) => catItem.label);

    const maxVal = Math.max(1, ...series.value.map((item) => item.count || 0));
    const axisMax = Math.max(10, Math.ceil(maxVal / 10) * 10);

    return {
        ...baseOption(),
        themePalette: CHART_COLOR_TIERS.map((t) => t.color),
        legend: { show: false },
        grid: { left: 24, right: 80, top: 20, bottom: 44, containLabel: true },
        xAxis: {
            type: 'value',
            min: 0,
            max: axisMax,
            name: 'Jumlah alumni (orang)',
            nameLocation: 'middle',
            nameGap: 30,
            axisLabel: { formatter: (value) => formatNumber(value, 0), color: '#64748B' },
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
        },
        yAxis: {
            type: 'category',
            inverse: true,
            data: categories,
            axisLabel: { color: '#334155', fontWeight: 500, width: 190, overflow: 'truncate' },
            axisTick: { show: false },
        },
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            formatter: (params) => {
                const catIdx = params?.[0]?.dataIndex;
                const catItem = series.value[catIdx];
                if (!catItem) return '';

                const catRows = records.value.filter((r) => r.career_category === catItem.label);
                const total = catItem.count || catRows.length || 1;
                const valid = catItem.valid_count || catRows.filter((r) => Number(r.weight || 0) > 0).length;
                const validPct = Number(((valid / total) * 100).toFixed(1));
                const avgWeight = valid > 0 ? (catItem.weighted_score / valid).toFixed(2) : '0';

                let tierRowsHtml = '';
                WEIGHT_TIERS.forEach((tier) => {
                    const count = catRows.filter((r) => tier.match(r.weight)).length;
                    if (count > 0) {
                        const pct = ((count / total) * 100).toFixed(1);
                        const desc = getCategoryTierDesc(catItem.label, tier.key);
                        tierRowsHtml += `<div class="py-1 border-b border-slate-100 last:border-b-0">`
                            + `<div class="flex items-center justify-between gap-3 text-xs">`
                            + `<span class="inline-flex items-center gap-1.5 font-semibold text-slate-800">`
                            + `<span class="inline-block w-2.5 h-2.5 rounded-xs" style="background:${tier.color};"></span>`
                            + `<span>${tier.label}</span>`
                            + `</span>`
                            + `<span class="font-bold text-slate-900">${formatNumber(count, 0)} alumni (${formatPercentage(pct)})</span>`
                            + `</div>`
                            + (desc ? `<div class="text-[11px] text-slate-500 pl-4 mt-0.5 leading-tight">${desc}</div>` : '')
                            + `</div>`;
                    }
                });

                return `<div class="font-sans text-xs space-y-1.5 min-w-[280px] max-w-[360px]">`
                    + `<div class="font-bold text-sm text-slate-900 border-b border-slate-200 pb-1">${catItem.label} (Total: ${formatNumber(total, 0)} alumni)</div>`
                    + `<div class="space-y-0.5">${tierRowsHtml}</div>`
                    + `<div class="mt-2 pt-1.5 border-t border-slate-200 text-slate-600 text-[11px] space-y-0.5">`
                    + `<div>Memenuhi Kriteria IKU: <strong class="text-[#0D542B]">${formatNumber(valid, 0)} alumni (${formatPercentage(validPct)})</strong></div>`
                    + `<div>Total Poin Bobot (&Sigma; Bobot): <strong>${formatNumber(catItem.weighted_score, 2)} poin</strong></div>`
                    + `<div>Rata-rata Bobot / Alumni: <strong>${avgWeight}</strong></div>`
                    + `<div>Kontribusi ke Skor Akhir IKU: <strong>${formatPercentage(catItem.percentage)}</strong></div>`
                    + `</div>`
                    + `</div>`;
            },
        },
        series: CHART_COLOR_TIERS.map((tier) => ({
            id: `tier_${tier.key}`,
            name: tier.label,
            type: 'bar',
            stack: 'total',
            stackOrder: tier.stackOrder,
            barMaxWidth: 26,
            itemStyle: { color: tier.color },
            label: {
                show: true,
                position: 'inside',
                color: tier.textColor,
                fontWeight: 700,
                fontSize: 10,
                formatter: ({ value, data }) => {
                    const count = Number(data?.count || value || 0);
                    const total = Number(data?.total || 0);
                    const percentage = Number(data?.percentage || 0);
                    const axisMax = Number(data?.axisMax || 0);
                    if (!count || percentage < 7) return '';

                    const widthRatio = axisMax > 0 ? (count / axisMax) : (percentage / 100);

                    // Sembunyikan label jika segmen terlalu sempit agar tidak menabrak segmen sebelah
                    if (widthRatio < 0.06 || (axisMax >= 40 && count < 3)) {
                        return '';
                    }

                    // Tampilkan persentase dan rasio jumlah jika segmen cukup lebar
                    if (widthRatio >= 0.14 && percentage >= 15 && total > 0) {
                        return `${formatPercentage(percentage)} (${formatNumber(count, 0)}/${formatNumber(total, 0)})`;
                    }

                    return `${formatPercentage(percentage)}`;
                },
            },
            data: series.value.map((catItem) => {
                const catRows = records.value.filter((r) => r.career_category === catItem.label);
                const tierRows = catRows.filter((r) => tier.match(r.weight));
                const count = tierRows.length;
                const total = catItem.count || catRows.length || 1;
                const pct = Number(((count / total) * 100).toFixed(1));

                return {
                    name: catItem.label,
                    role: catItem.label,
                    category: tier.label,
                    value: count,
                    count: count,
                    total: total,
                    percentage: pct,
                    tierKey: tier.key,
                    tierLabel: tier.shortLabel,
                    axisMax,
                };
            }),
        })),
    };
});

// Tabel rincian perolehan bobot per kategori karier.
const tableData = computed(() => ({
    columns: [
        { key: 'name', label: 'Kategori Karier' },
        { key: 'count', label: 'Total Alumni (n)', align: 'right' },
        { key: 'w12', label: 'Bobot 1.2', align: 'right' },
        { key: 'w10', label: 'Bobot 1.0', align: 'right' },
        { key: 'w08', label: 'Bobot 0.8', align: 'right' },
        { key: 'w06', label: 'Bobot 0.6', align: 'right' },
        { key: 'w_prop', label: 'Bobot 0.2–0.5', align: 'right' },
        { key: 'w00', label: 'Bobot 0.0', align: 'right' },
        { key: 'valid_rate', label: 'Rasio Berbobot (%)', align: 'right' },
        { key: 'weighted_score', label: 'Total Poin (Σ)', align: 'right' },
        { key: 'percentage', label: 'Kontribusi IKU (%)', align: 'right' },
    ],
    rows: series.value.map((catItem) => {
        const catRows = records.value.filter((r) => r.career_category === catItem.label);
        const total = catItem.count || catRows.length || 1;
        const valid = catItem.valid_count || catRows.filter((r) => Number(r.weight || 0) > 0).length;
        const validPct = Number(((valid / total) * 100).toFixed(1));

        const getTierCell = (tierKey) => {
            const tier = WEIGHT_TIERS.find((t) => t.key === tierKey);
            const count = catRows.filter((r) => tier.match(r.weight)).length;
            if (count === 0) return '-';
            const pct = ((count / total) * 100).toFixed(1);
            return `${formatNumber(count, 0)} (${formatPercentage(pct)})`;
        };

        return {
            name: catItem.label,
            count: `${formatNumber(total, 0)} orang`,
            w12: getTierCell('w12'),
            w10: getTierCell('w10'),
            w08: getTierCell('w08'),
            w06: getTierCell('w06'),
            w_prop: getTierCell('w_prop'),
            w00: getTierCell('w00'),
            valid_rate: formatPercentage(validPct),
            weighted_score: `${formatNumber(catItem.weighted_score, 2)} poin`,
            percentage: formatPercentage(catItem.percentage),
        };
    }),
}));

// Narasi ringkas untuk capaian bobot IKU.
const weightAnalysis = computed(() => ({
    title: 'Kilas Distribusi Bobot Karier Lulusan S1',
    statusBadge: `${formatNumber(summary.value.weighted_score, 2)} Total Poin Bobot`,
    badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
    lead: `Dari ${formatNumber(summary.value.population, 0)} responden lulusan S1, sebanyak ${formatNumber(summary.value.valid_respondents, 0)} alumni memenuhi kriteria berbobot IKU dengan akumulasi ${formatNumber(summary.value.weighted_score, 2)} poin bobot (Skor IKU Institusi: ${formatPercentage(summary.value.percentage)}).`,
    detail: 'Grafik batang bertumpuk (stacked) menyajikan perolehan bobot per kategori karier. Angka n pada label sumbu kiri menunjukkan jumlah responden per kategori, sedangkan nilai di dalam segmen batang menunjukkan jumlah alumni beserta persentase perolehan bobotnya.',
}));

// Kolom drill-down detail alumni yang seragam dengan tabel data analitik.
const drillDownColumns = [
    { key: 'nim', label: 'NIM' },
    { key: 'nama', label: 'Nama Alumni' },
    { key: 'nama_prodi', label: 'Program Studi' },
    { key: 'career_status_label', label: 'Status Aktivitas' },
    { key: 'nama_perusahaan', label: 'Perusahaan / Kampus S2' },
    { key: 'posisi_jabatan', label: 'Jabatan / Posisi' },
    { key: 'study_detail', label: 'Studi Lanjut' },
    { key: 'months', label: 'Masa Tunggu' },
    { key: 'salary_comparison', label: 'Status Gaji vs 1,2× UMP' },
    { key: 'weight', label: 'Bobot IKU' },
    { key: 'qualifies', label: 'Memenuhi IKU' },
];

// Konfigurasi tampilan grafik dan tabel capaian lulusan berbobot IKU.
const views = computed(() => [
    {
        key: 'stacked_weights',
        chartType: 'horizontal_bar',
        tabLabel: 'Distribusi Bobot Karier',
        title: 'Distribusi Perolehan Bobot IKU per Kategori Karier Lulusan S1',
        description: 'Grafik batang bertumpuk (stacked) menampilkan sebaran perolehan nilai bobot (1.2, 1.0, 0.8, 0.6, 0.2-0.5, dan 0.0) di setiap kategori karier. Label di dalam segmen menampilkan jumlah alumni dan persentase terhadap kategori tersebut.',
        option: stackedOption.value,
        tableData: tableData.value,
        height: Math.max(360, Math.min(series.value.length * 62 + 140, 600)),
        analysis: weightAnalysis.value,
        drillDownColumns,
    },
]);
</script>

<template>
    <AnalyticsTabbedContainer
        :views="views"
        :rows="records"
        :selected-label="selectedLabel"
        :loading="loading"
        indicator-badge="IKU S1"
        @select="emit('select', $event)"
        @reset="emit('reset')"
    >
        <template #before-chart>
            <div class="mb-3 space-y-2.5">
                <!-- Legenda Keterangan Warna di Atas Grafik (Konsisten dengan Response Rate) -->
                <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2 text-xs text-slate-700">
                    <div class="flex flex-wrap items-center gap-x-3.5 gap-y-2">
                        <span class="font-bold text-slate-900 pr-3 border-r border-slate-300">Keterangan Warna Bobot:</span>
                        <div class="flex items-center gap-1.5">
                            <span class="inline-block h-3 w-3 rounded-xs bg-[#CBD5E1]"></span>
                            <span><strong>Abu-abu:</strong> Bobot 0.0 (Tidak Berbobot)</span>
                        </div>
                        <span class="text-slate-300 hidden sm:inline">|</span>
                        <div class="flex items-center gap-1.5">
                            <span class="inline-block h-3 w-3 rounded-xs bg-[#0D542B]"></span>
                            <span><strong>Hijau:</strong> Bobot 1.0 – 1.2 (Penuh / Utama)</span>
                        </div>
                        <span class="text-slate-300 hidden sm:inline">|</span>
                        <div class="flex items-center gap-1.5">
                            <span class="inline-block h-3 w-3 rounded-xs bg-[#D97706]"></span>
                            <span><strong>Kuning:</strong> Bobot 0.6 – 0.8 (Menengah / Studi Lanjut)</span>
                        </div>
                        <span class="text-slate-300 hidden sm:inline">|</span>
                        <div class="flex items-center gap-1.5">
                            <span class="inline-block h-3 w-3 rounded-xs bg-[#DC2626]"></span>
                            <span><strong>Merah:</strong> Bobot 0.2 – 0.5 (Proporsional / Freelancer)</span>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-700 shadow-xs transition-colors hover:bg-slate-100"
                        @click="showWeightGuide = !showWeightGuide"
                    >
                        <span>{{ showWeightGuide ? 'Sembunyikan Panduan Bobot' : 'Panduan Perhitungan Bobot IKU' }}</span>
                    </button>
                </div>

                <!-- Panel Keterangan Perhitungan Bobot & Formula IKU S1 -->
                <div
                    v-if="showWeightGuide"
                    class="rounded-lg border border-slate-200 bg-white p-3.5 text-xs text-slate-700 shadow-xs space-y-2.5"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
                        <h4 class="font-bold text-slate-900">Keterangan Perhitungan Bobot IKU Lulusan S1</h4>
                        <span class="text-[11px] font-medium text-slate-500">Standar Kepmendikbudristek No. 210/M/2023</span>
                    </div>

                    <!-- Formula Resmi -->
                    <div class="rounded border border-emerald-200 bg-emerald-50/70 p-2.5 text-[11px] text-[#0D542B] space-y-1">
                        <div class="font-bold">Formula Skor Akhir IKU Universitas:</div>
                        <div class="font-mono text-xs font-semibold">Skor IKU = (&Sigma; Poin Bobot Seluruh Responden / Total Responden Valid IKU) &times; 100%</div>
                        <div class="text-[10px] text-slate-600 mt-0.5">
                            Total Responden Valid (t) adalah lulusan yang bekerja, berwirausaha, atau melanjutkan studi. Alumni yang memenuhi syarat akan menyumbangkan poin bobot (0,2 s.d. 1,2), sedangkan yang masa tunggunya &ge; 12 bulan bernilai bobot 0.
                        </div>
                    </div>

                    <!-- 4 Kartu Kriteria Bobot (Warna Selaras dengan Grafik & Legenda Atas) -->
                    <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 md:grid-cols-4">
                        <div class="rounded border border-slate-200/80 bg-slate-50/70 p-2.5">
                            <div class="font-bold text-slate-900 border-b border-slate-200/60 pb-1 mb-1.5">1. Karyawan / Bekerja</div>
                            <ul class="space-y-1.5 text-[11px] text-slate-600">
                                <li class="flex items-start gap-1.5">
                                    <span class="mt-1 inline-block h-2.5 w-2.5 shrink-0 rounded-xs bg-[#0D542B]"></span>
                                    <span><strong class="text-[#0D542B]">Bobot 1.0:</strong> Tunggu &le; 6 bln & Gaji &ge; 1,2&times; UMP</span>
                                </li>
                                <li class="flex items-start gap-1.5">
                                    <span class="mt-1 inline-block h-2.5 w-2.5 shrink-0 rounded-xs bg-[#D97706]"></span>
                                    <span><strong class="text-[#D97706]">Bobot 0.8:</strong> Tunggu 6&ndash;12 bln & Gaji &ge; 1,2&times; UMP</span>
                                </li>
                                <li class="flex items-start gap-1.5">
                                    <span class="mt-1 inline-block h-2.5 w-2.5 shrink-0 rounded-xs bg-[#D97706]"></span>
                                    <span><strong class="text-[#D97706]">Bobot 0.6:</strong> Tunggu &le; 12 bln & Gaji &lt; 1,2&times; UMP</span>
                                </li>
                                <li class="flex items-start gap-1.5">
                                    <span class="mt-1 inline-block h-2.5 w-2.5 shrink-0 rounded-xs bg-[#CBD5E1]"></span>
                                    <span><strong class="text-slate-600">Bobot 0.0:</strong> Tunggu &ge; 12 bln (tidak berbobot)</span>
                                </li>
                            </ul>
                        </div>

                        <div class="rounded border border-slate-200/80 bg-slate-50/70 p-2.5">
                            <div class="font-bold text-slate-900 border-b border-slate-200/60 pb-1 mb-1.5">2. Wirausaha (Founder)</div>
                            <ul class="space-y-1.5 text-[11px] text-slate-600">
                                <li class="flex items-start gap-1.5">
                                    <span class="mt-1 inline-block h-2.5 w-2.5 shrink-0 rounded-xs bg-[#0D542B]"></span>
                                    <span><strong class="text-[#0D542B]">Bobot 1.2:</strong> Tunggu &le; 6 bln & Omzet &ge; 1,2&times; UMP</span>
                                </li>
                                <li class="flex items-start gap-1.5">
                                    <span class="mt-1 inline-block h-2.5 w-2.5 shrink-0 rounded-xs bg-[#0D542B]"></span>
                                    <span><strong class="text-[#0D542B]">Bobot 1.0:</strong> Tunggu &gt; 6 bln & Omzet &ge; 1,2&times; UMP</span>
                                </li>
                                <li class="flex items-start gap-1.5">
                                    <span class="mt-1 inline-block h-2.5 w-2.5 shrink-0 rounded-xs bg-[#D97706]"></span>
                                    <span><strong class="text-[#D97706]">Bobot 0.8:</strong> Tunggu &le; 6 bln & Omzet &lt; 1,2&times; UMP</span>
                                </li>
                                <li class="flex items-start gap-1.5">
                                    <span class="mt-1 inline-block h-2.5 w-2.5 shrink-0 rounded-xs bg-[#D97706]"></span>
                                    <span><strong class="text-[#D97706]">Bobot 0.6:</strong> Tunggu &gt; 6 bln & Omzet &lt; 1,2&times; UMP</span>
                                </li>
                            </ul>
                        </div>

                        <div class="rounded border border-slate-200/80 bg-slate-50/70 p-2.5">
                            <div class="font-bold text-slate-900 border-b border-slate-200/60 pb-1 mb-1.5">3. Melanjutkan Studi</div>
                            <ul class="space-y-1.5 text-[11px] text-slate-600">
                                <li class="flex items-start gap-1.5">
                                    <span class="mt-1 inline-block h-2.5 w-2.5 shrink-0 rounded-xs bg-[#D97706]"></span>
                                    <span><strong class="text-[#D97706]">Bobot 0.6:</strong> Mulai studi lanjut (S2/Profesi) &le; 12 bulan setelah tanggal wisuda.</span>
                                </li>
                                <li class="flex items-start gap-1.5">
                                    <span class="mt-1 inline-block h-2.5 w-2.5 shrink-0 rounded-xs bg-[#CBD5E1]"></span>
                                    <span><strong class="text-slate-600">Bobot 0.0:</strong> Mulai studi &gt; 12 bulan setelah wisuda atau tanggal studi belum terdata.</span>
                                </li>
                            </ul>
                        </div>

                        <div class="rounded border border-slate-200/80 bg-slate-50/70 p-2.5">
                            <div class="font-bold text-slate-900 border-b border-slate-200/60 pb-1 mb-1.5">4. Freelancer & Lainnya</div>
                            <ul class="space-y-1.5 text-[11px] text-slate-600">
                                <li class="flex items-start gap-1.5">
                                    <span class="mt-1 inline-block h-2.5 w-2.5 shrink-0 rounded-xs bg-[#DC2626]"></span>
                                    <span><strong class="text-[#DC2626]">Bobot 0.2&ndash;0.5:</strong> Pekerja lepas dihitung proporsional pendapatan & masa tunggu.</span>
                                </li>
                                <li class="flex items-start gap-1.5">
                                    <span class="mt-1 inline-block h-2.5 w-2.5 shrink-0 rounded-xs bg-[#CBD5E1]"></span>
                                    <span><strong class="text-slate-600">Bobot 0.0:</strong> Wirausaha lainnya tanpa posisi / jabatan terstruktur.</span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Aturan Khusus Status Kombinasi (Bekerja / Wirausaha + Studi Lanjut) -->
                    <div class="rounded border border-slate-200 bg-slate-50 p-2.5 text-[11px] text-slate-700 space-y-1">
                        <div class="font-bold text-slate-900">Aturan Status Kombinasi (Bekerja / Founder / Freelancer + Melanjutkan Studi):</div>
                        <div class="text-slate-600 leading-relaxed">
                            1. <strong>Tidak Dihitung Ganda (Single Counting):</strong> Setiap alumni hanya dihitung 1 kali pada kategori karier utamanya (<strong>Karyawan</strong>, <strong>Founder / Co-Founder</strong>, atau <strong>Freelancer</strong>), dan diberi label <em>Kombinasi: ... + Studi Lanjut</em> pada tabel rincian.<br>
                            2. <strong>Prinsip Bobot Tertinggi (Highest Valid Weight):</strong> Sistem menghitung bobot dari jalur pekerjaan/wirausaha serta jalur studi lanjut, lalu mengambil <strong>nilai bobot tertinggi</strong> (<code class="font-mono text-[#0D542B]">max(bobot_kerja, bobot_studi)</code>). Contoh: Karyawan (<code class="font-mono">1.0</code>) + Studi S2 (<code class="font-mono">0.6</code>) diambil <strong>1.0</strong>; Founder (<code class="font-mono">1.2</code>) + Studi S2 (<code class="font-mono">0.6</code>) diambil <strong>1.2</strong>; atau jika pekerjaan belum memenuhi syarat (<code class="font-mono">0.0</code>) maupun Freelancer (<code class="font-mono">0.3</code>) tetapi kuliah S2 tepat waktu (<code class="font-mono">0.6</code>), maka diambil <strong>0.6</strong>.
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </AnalyticsTabbedContainer>
</template>
