<script setup>
/**
 * Komponen indikator capaian karier lulusan S1 (IKU Outcomes).
 *
 * Formula bobot telah dihitung backend dari status karier, masa tunggu, UMP,
 * dan tanggal studi lanjut. Komponen menampilkan distribusi responden, kontribusi
 * bobot ke skor IKU, dan rincian perbandingan poin bobot per kategori karier.
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

// Event seleksi diteruskan ke panel analitik induk.
const emit = defineEmits(['select', 'reset']);

// State toggle panduan aturan bobot IKU (aktif default agar langsung terbaca)
const showWeightGuide = ref(true);

// Seri kategori karier dan rincian klasifikasi individu.
const series = computed(() => props.chart?.series || []);
const records = computed(() => props.chart?.records || []);
const summary = computed(() => props.chart?.summary || {});

// Opsi batang jumlah alumni dengan warna status kontribusi dari backend.
const chartOption = computed(() => ({
    ...baseOption(),
    grid: { left: 24, right: 110, top: 22, bottom: 52, containLabel: true },
    xAxis: {
        type: 'value',
        min: 0,
        minInterval: 1,
        name: 'Jumlah alumni (orang)',
        nameLocation: 'middle',
        nameGap: 30,
        axisLabel: { formatter: (value) => formatNumber(value, 0), color: '#64748B' },
        splitLine: { lineStyle: { color: '#E2E8F0' } },
    },
    yAxis: {
        type: 'category',
        inverse: true,
        data: series.value.map((item) => item.label),
        axisLabel: { color: '#334155', width: 190, overflow: 'truncate' },
        axisTick: { show: false },
    },
    tooltip: {
        trigger: 'axis',
        axisPointer: { type: 'shadow' },
        formatter: (params) => {
            const item = series.value[params?.[0]?.dataIndex];
            return item
                ? `${item.label}<br/>Jumlah alumni: ${formatNumber(item.count, 0)} orang<br/>Valid berbobot: ${formatNumber(item.valid_count, 0)} orang<br/>Total poin (Σ bobot): ${formatNumber(item.weighted_score, 2)}<br/>Kontribusi ke skor IKU: ${formatPercentage(item.percentage)} (dari ${formatNumber(item.percentage_denominator_count, 0)} responden valid)<br/>Status target: ${item.status_label}`
                : '';
        },
    },
    series: [{
        name: 'Jumlah alumni',
        type: 'bar',
        barMaxWidth: 32,
        data: series.value.map((item) => ({
            name: item.label,
            value: item.count,
            category: item.label,
            itemStyle: { color: item.status_color, borderRadius: [0, 4, 4, 0] },
        })),
        label: {
            show: true,
            position: 'right',
            color: '#334155',
            fontSize: 10,
            formatter: (params) => {
                const item = series.value[params.dataIndex];
                if (!item) return '';
                return `${formatNumber(item.count, 0)} mhs (${formatPercentage(item.percentage)})`;
            },
        },
    }],
}));

// Opsi grafik perbandingan alumni yang memenuhi bobot vs tidak memenuhi bobot per kategori
const weightComparisonOption = computed(() => {
    const categories = series.value.map((item) => item.label);
    const maxVal = Math.max(
        1,
        ...series.value.map((item) => item.valid_count),
        ...series.value.map((item) => Math.max(0, item.count - item.valid_count))
    );
    const axisMax = Math.max(10, Math.ceil(maxVal / 5) * 5);

    return {
        ...baseOption(),
        legend: {
            show: true,
            bottom: 0,
            textStyle: { color: '#475569', fontSize: 11 },
            data: ['Memenuhi Bobot (Bobot > 0)', 'Tidak Memenuhi Bobot (Bobot = 0)'],
        },
        grid: { left: 24, right: 110, top: 24, bottom: 56, containLabel: true },
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
            axisLabel: { color: '#334155', width: 190, overflow: 'truncate' },
            axisTick: { show: false },
        },
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            formatter: (params) => {
                const item = series.value[params?.[0]?.dataIndex];
                if (!item) return '';
                const unweighted = Math.max(0, item.count - item.valid_count);
                const meetPct = item.count > 0 ? ((item.valid_count / item.count) * 100).toFixed(1) : 0;
                const failPct = item.count > 0 ? ((unweighted / item.count) * 100).toFixed(1) : 0;
                const avgWeight = item.valid_count > 0 ? (item.weighted_score / item.valid_count).toFixed(2) : '0';

                return `${item.label} (Total: ${formatNumber(item.count, 0)} alumni)<br/>`
                    + `<span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:#0D542B;margin-right:5px;"></span>`
                    + `Memenuhi Bobot (> 0): <strong>${formatNumber(item.valid_count, 0)} mhs (${formatPercentage(meetPct)})</strong><br/>`
                    + `<span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:#DC2626;margin-right:5px;"></span>`
                    + `Tidak Memenuhi Bobot (0): <strong>${formatNumber(unweighted, 0)} mhs (${formatPercentage(failPct)})</strong><br/>`
                    + `Total poin bobot (&Sigma; bobot): ${formatNumber(item.weighted_score, 2)} poin<br/>`
                    + `Rata-rata bobot / alumni: ${avgWeight}<br/>`
                    + `Kontribusi ke skor IKU: ${formatPercentage(item.percentage)}`;
            },
        },
        series: [
            {
                name: 'Memenuhi Bobot (Bobot > 0)',
                type: 'bar',
                barMaxWidth: 16,
                itemStyle: { color: '#0D542B', borderRadius: [0, 3, 3, 0] },
                label: {
                    show: true,
                    position: 'right',
                    color: '#0D542B',
                    fontWeight: 700,
                    fontSize: 10,
                    formatter: ({ value, data }) => {
                        if (!value) return '';
                        return `${formatNumber(value, 0)} mhs (${formatPercentage(data?.percentage)})`;
                    },
                },
                data: series.value.map((item) => {
                    const pct = item.count > 0 ? Number(((item.valid_count / item.count) * 100).toFixed(1)) : 0;
                    return {
                        name: item.label,
                        category: 'Memenuhi Bobot (Bobot > 0)',
                        value: item.valid_count,
                        count: item.valid_count,
                        total: item.count,
                        percentage: pct,
                    };
                }),
            },
            {
                name: 'Tidak Memenuhi Bobot (Bobot = 0)',
                type: 'bar',
                barMaxWidth: 16,
                itemStyle: { color: '#DC2626', borderRadius: [0, 3, 3, 0] },
                label: {
                    show: true,
                    position: 'right',
                    color: '#DC2626',
                    fontWeight: 700,
                    fontSize: 10,
                    formatter: ({ value, data }) => {
                        if (!value) return '';
                        return `${formatNumber(value, 0)} mhs (${formatPercentage(data?.percentage)})`;
                    },
                },
                data: series.value.map((item) => {
                    const unweighted = Math.max(0, item.count - item.valid_count);
                    const pct = item.count > 0 ? Number(((unweighted / item.count) * 100).toFixed(1)) : 0;
                    return {
                        name: item.label,
                        category: 'Tidak Memenuhi Bobot (Bobot = 0)',
                        value: unweighted,
                        count: unweighted,
                        total: item.count,
                        percentage: pct,
                    };
                }),
            },
        ],
    };
});

// Tabel kontribusi per kategori agar formula dapat ditelusuri tanpa membaca tooltip.
const tableData = computed(() => ({
    columns: [
        { key: 'name', label: 'Kategori' },
        { key: 'count', label: 'Jumlah alumni', align: 'right' },
        { key: 'valid_count', label: 'Valid berbobot', align: 'right' },
        { key: 'weighted_score', label: 'Σ Bobot', align: 'right' },
        { key: 'percentage', label: 'Kontribusi ke IKU', align: 'right' },
    ],
    rows: series.value.map((item) => ({
        name: item.label,
        count: `${formatNumber(item.count, 0)} orang`,
        valid_count: `${formatNumber(item.valid_count, 0)} orang`,
        weighted_score: `${formatNumber(item.weighted_score, 2)} poin`,
        percentage: formatPercentage(item.percentage),
    })),
}));

// Tabel rincian perbandingan alumni memenuhi vs tidak memenuhi bobot
const weightTableData = computed(() => ({
    columns: [
        { key: 'name', label: 'Kategori Karier' },
        { key: 'count', label: 'Total Alumni', align: 'right' },
        { key: 'valid_count', label: 'Memenuhi Bobot (> 0)', align: 'right' },
        { key: 'unweighted_count', label: 'Tidak Memenuhi (Bobot 0)', align: 'right' },
        { key: 'meet_percentage', label: 'Rasio Memenuhi (%)', align: 'right' },
        { key: 'weighted_score', label: 'Total Poin (Σ Bobot)', align: 'right' },
        { key: 'percentage', label: 'Kontribusi ke IKU', align: 'right' },
    ],
    rows: series.value.map((item) => {
        const unweighted = Math.max(0, item.count - item.valid_count);
        const meetPct = item.count > 0 ? ((item.valid_count / item.count) * 100).toFixed(1) : 0;
        return {
            name: item.label,
            count: `${formatNumber(item.count, 0)} orang`,
            valid_count: `${formatNumber(item.valid_count, 0)} orang`,
            unweighted_count: `${formatNumber(unweighted, 0)} orang`,
            meet_percentage: formatPercentage(meetPct),
            weighted_score: `${formatNumber(item.weighted_score, 2)} poin`,
            percentage: formatPercentage(item.percentage),
        };
    }),
}));

// Narasi ringkas menjelaskan denominator dan skor akhir indikator.
const analysis = computed(() => ({
    title: 'Kilas Capaian Karier S1',
    statusBadge: formatPercentage(summary.value.percentage),
    badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
    lead: `${formatNumber(summary.value.valid_respondents, 0)} responden lulusan masuk denominator dengan total bobot ${formatNumber(summary.value.weighted_score, 2)}.`,
    detail: `Panjang bar menunjukkan jumlah alumni dalam kategori. Persentase dan warna menunjukkan kontribusi Σ bobot terhadap seluruh ${formatNumber(summary.value.percentage_denominator_count, 0)} responden valid IKU, bukan persentase di dalam kategori. Warna mengikuti standar capaian: hijau ≥50%, kuning 30%–<50%, merah <30%, dan abu-abu jika tidak berbobot.`,
}));

// Narasi ringkas untuk tab perbandingan bobot
const weightAnalysis = computed(() => ({
    title: 'Kilas Perbandingan Bobot terhadap Jumlah Responden',
    statusBadge: `${formatNumber(summary.value.weighted_score, 2)} Total Poin Bobot`,
    badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
    lead: `Dari ${formatNumber(summary.value.population, 0)} lulusan S1, sebanyak ${formatNumber(summary.value.valid_respondents, 0)} alumni memenuhi syarat berbobot dengan akumulasi ${formatNumber(summary.value.weighted_score, 2)} poin bobot.`,
    detail: `Grafik membandingkan secara langsung jumlah alumni yang memenuhi bobot (bobot > 0, hijau) dan yang belum memenuhi (bobot = 0, merah) pada masing-masing kategori karier.`,
}));

// Kolom drill-down detail alumni yang seragam dengan tabel data analitik
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

// Konfigurasi tampilan grafik dan tabel capaian lulusan.
const views = computed(() => [
    {
        key: 'outcomes',
        chartType: 'horizontal_bar',
        tabLabel: 'Capaian Karier',
        title: 'Jumlah Alumni S1 Bekerja, Berwirausaha, atau Studi Lanjut',
        description: 'Panjang bar menunjukkan jumlah alumni per kategori; label dan warna menunjukkan kontribusi Σ bobot terhadap seluruh responden valid IKU. Ambang gaji memakai UMP tahun acuan.',
        option: chartOption.value,
        tableData: tableData.value,
        height: Math.max(340, Math.min(series.value.length * 54 + 120, 560)),
        analysis: analysis.value,
        drillDownColumns,
    },
    {
        key: 'weights_breakdown',
        chartType: 'horizontal_bar',
        tabLabel: 'Memenuhi vs Tidak Memenuhi Bobot',
        title: 'Perbandingan Alumni Memenuhi vs Tidak Memenuhi Bobot per Kategori',
        description: 'Membandingkan jumlah alumni yang memenuhi kriteria IKU (bobot > 0, hijau) dan yang belum memenuhi kriteria (bobot = 0, merah) pada setiap kategori karier.',
        option: weightComparisonOption.value,
        tableData: weightTableData.value,
        height: Math.max(360, Math.min(series.value.length * 58 + 140, 600)),
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
                <!-- Toolbar Atas: Legenda Status Kontribusi & Tombol Panduan Bobot -->
                <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200/80 bg-slate-50/70 px-3 py-2 text-[11px] text-slate-600">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5">
                        <span class="font-bold text-slate-800">Status Kontribusi IKU:</span>
                        <span class="inline-flex items-center gap-1.5">
                            <span class="h-2.5 w-2.5 rounded-xs bg-[#0D542B]"></span>
                            <strong>Hijau:</strong> &ge; 50% (Tinggi)
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <span class="h-2.5 w-2.5 rounded-xs bg-[#D97706]"></span>
                            <strong>Kuning:</strong> 30%&ndash;&lt;50% (Sedang)
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <span class="h-2.5 w-2.5 rounded-xs bg-[#DC2626]"></span>
                            <strong>Merah:</strong> &lt; 30% (Rendah)
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <span class="h-2.5 w-2.5 rounded-xs bg-[#CBD5E1]"></span>
                            <strong>Abu-abu:</strong> Tidak Berbobot (0%)
                        </span>
                    </div>

                    <button
                        type="button"
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded border border-slate-200 bg-white px-2 py-1 font-semibold text-slate-700 shadow-xs transition-colors hover:bg-slate-100"
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

                    <!-- 4 Kartu Kriteria Bobot -->
                    <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 md:grid-cols-4">
                        <div class="rounded border border-slate-200/80 bg-slate-50/70 p-2.5">
                            <div class="font-bold text-slate-900 border-b border-slate-200/60 pb-1 mb-1.5">1. Karyawan / Bekerja</div>
                            <ul class="space-y-1 text-[11px] text-slate-600">
                                <li><strong class="text-emerald-800">Bobot 1.0:</strong> Tunggu &le; 6 bln & Gaji &ge; 1,2&times; UMP</li>
                                <li><strong class="text-emerald-700">Bobot 0.8:</strong> Tunggu 6&ndash;12 bln & Gaji &ge; 1,2&times; UMP</li>
                                <li><strong class="text-amber-700">Bobot 0.6:</strong> Tunggu &le; 12 bln & Gaji &lt; 1,2&times; UMP</li>
                                <li><strong class="text-rose-700">Bobot 0.0:</strong> Tunggu &ge; 12 bln (tidak berbobot)</li>
                            </ul>
                        </div>

                        <div class="rounded border border-slate-200/80 bg-slate-50/70 p-2.5">
                            <div class="font-bold text-slate-900 border-b border-slate-200/60 pb-1 mb-1.5">2. Wirausaha (Founder)</div>
                            <ul class="space-y-1 text-[11px] text-slate-600">
                                <li><strong class="text-emerald-800">Bobot 1.2:</strong> Tunggu &le; 6 bln & Omzet &ge; 1,2&times; UMP</li>
                                <li><strong class="text-emerald-700">Bobot 1.0:</strong> Tunggu &gt; 6 bln & Omzet &ge; 1,2&times; UMP</li>
                                <li><strong class="text-amber-700">Bobot 0.8:</strong> Tunggu &le; 6 bln & Omzet &lt; 1,2&times; UMP</li>
                                <li><strong class="text-amber-600">Bobot 0.6:</strong> Tunggu &gt; 6 bln & Omzet &lt; 1,2&times; UMP</li>
                            </ul>
                        </div>

                        <div class="rounded border border-slate-200/80 bg-slate-50/70 p-2.5">
                            <div class="font-bold text-slate-900 border-b border-slate-200/60 pb-1 mb-1.5">3. Melanjutkan Studi</div>
                            <ul class="space-y-1 text-[11px] text-slate-600">
                                <li><strong class="text-emerald-700">Bobot 0.6:</strong> Mulai studi lanjut (S2/Profesi) &le; 12 bulan setelah tanggal wisuda.</li>
                                <li><strong class="text-rose-700">Bobot 0.0:</strong> Mulai studi &gt; 12 bulan setelah wisuda atau tanggal studi belum terdata.</li>
                            </ul>
                        </div>

                        <div class="rounded border border-slate-200/80 bg-slate-50/70 p-2.5">
                            <div class="font-bold text-slate-900 border-b border-slate-200/60 pb-1 mb-1.5">4. Freelancer & Lainnya</div>
                            <ul class="space-y-1 text-[11px] text-slate-600">
                                <li><strong class="text-amber-700">Bobot 0.2&ndash;0.5:</strong> Pekerja lepas dihitung proporsional pendapatan & masa tunggu.</li>
                                <li><strong class="text-rose-700">Bobot 0.0:</strong> Wirausaha lainnya tanpa posisi / jabatan terstruktur.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </AnalyticsTabbedContainer>
</template>
