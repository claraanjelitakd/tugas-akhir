<script setup>
/**
 * Komponen Indikator 06: Lamaran, Respons, dan Wawancara (F6, F7, F7a).
 *
 * Mengelola pipeline lamaran per prodi, tabel audit individu, konversi tahapan,
 * dan median tahapan lamaran tingkat universitas.
 */
import { computed } from 'vue';
import AnalyticsTabbedContainer from '../AnalyticsTabbedContainer.vue';
import {
    baseOption,
    escapeHtml,
    formatNumber,
    seriesLabel,
} from '../analyticsChartOptions';

// Kontrak properti data indikator, filter aktif, dan status pemuatan data.
const props = defineProps({
    chart: { type: Object, default: () => ({}) },
    selectedLabel: { type: String, default: '' },
    heatmapMode: { type: String, default: 'count' },
    loading: { type: Boolean, default: false },
});

// Event seleksi data diteruskan ke panel induk untuk cross-filtering.
const emit = defineEmits(['select', 'reset']);

// Ekstraksi data dari payload backend.
const scatter1 = computed(() => props.chart?.scatter_applications_responses || {});
const scatter2 = computed(() => props.chart?.scatter_responses_interviews || {});
const conversion = computed(() => props.chart?.conversion_stages || {});
const series = computed(() => props.chart?.series || []);
const records = computed(() => props.chart?.records || []);
const alumniList = computed(() => props.chart?.alumni_list || []);
const validResponses = computed(() => props.chart?.summary?.answered_count || props.chart?.summary?.response_count || records.value.length);

// Menghubungkan daftar alumni terpadu agar memiliki field prodi dan nilai tahapan individual untuk drill-down klik
const enrichedRows = computed(() => {
    const list = alumniList.value;
    if (list && list.length > 0) {
        return list.map((a) => ({
            ...a,
            nama_alumni: a.nama,
            prodi: a.nama_prodi,
            dilamar: a.f6,
            merespon: a.f7,
            wawancara: a.f7a,
            category: a.nama_prodi,
            target: a.nama_prodi,
        }));
    }

    return records.value;
});

// Palet warna Viridis kontinu untuk skala IPK 0.0 - 4.0
const viridisColors = ['#440154', '#3b528b', '#21918c', '#5ec962', '#fde725'];

// Konfigurasi visualMap bersama untuk colorbar vertikal IPK di sebelah kanan grafik scatter
const createIpkVisualMap = () => ({
    type: 'continuous',
    min: 0,
    max: 4,
    dimension: 2,
    orient: 'vertical',
    right: 14,
    top: 'center',
    text: ['IPK 4.0', 'IPK 0.0'],
    textGap: 8,
    itemWidth: 14,
    itemHeight: 140,
    calculable: true,
    inRange: {
        color: viridisColors,
    },
    outOfRange: {
        color: '#94A3B8',
    },
    formatter: (val) => Number(val).toFixed(1),
    textStyle: {
        color: '#475569',
        fontSize: 11,
        fontWeight: 600,
    },
});

/**
 * Mendispersikan koordinat titik diskrit yang bertumpuk pada koordinat bulat yang sama
 * menggunakan algoritma Sunflower / Fermat spiral (radius r <= 0.32).
 *
 * Titik-titik alumni yang memiliki nilai F6 dan F7 sama akan mekar menjadi gugus bola
 * yang rapi, terpisah, dan tidak saling bertumpuk, sehingga seluruh alumni terlihat
 * dengan jelas dan dapat disorot (hover) satu per satu.
 */
function applyClusterJitter(points, xKey = 'f6', yKey = 'f7') {
    if (!points || !points.length) return [];

    // Kelompokkan indeks titik berdasarkan koordinat bulat asli (rawX, rawY)
    const groups = {};
    points.forEach((pt, idx) => {
        const rawX = Number(pt[xKey] ?? pt.value?.[0] ?? 0);
        const rawY = Number(pt[yKey] ?? pt.value?.[1] ?? 0);
        const key = `${rawX},${rawY}`;
        if (!groups[key]) groups[key] = [];
        groups[key].push(idx);
    });

    return points.map((pt, idx) => {
        const rawX = Number(pt[xKey] ?? pt.value?.[0] ?? 0);
        const rawY = Number(pt[yKey] ?? pt.value?.[1] ?? 0);
        const ipk = pt.ipk !== undefined && pt.ipk !== null ? Number(pt.ipk) : null;
        const key = `${rawX},${rawY}`;
        const group = groups[key] || [idx];
        const count = group.length;
        const posInGroup = group.indexOf(idx);

        let jx = 0;
        let jy = 0;
        if (count > 1) {
            // Golden angle dispersion: theta = i * 137.507764 deg (2.399963 rad)
            // Radius mekar proporsional hingga maksimum 0.32 agar tidak melompat ke bilangan bulat berikutnya
            const maxRadius = Math.min(0.32, 0.08 + (count * 0.01));
            const r = maxRadius * Math.sqrt((posInGroup + 0.5) / count);
            const theta = posInGroup * 2.399963;
            jx = r * Math.cos(theta);
            jy = r * Math.sin(theta);
        }

        return {
            ...pt,
            rawX,
            rawY,
            ipk,
            value: [
                Number((rawX + jx).toFixed(3)),
                Number((rawY + jy).toFixed(3)),
                ipk,
            ],
        };
    });
}

// -----------------------------------------------------------------------------
// 1. LINE CHART: Tahapan Lamaran per Program Studi (Gambar 4.7 Laporan Resmi)
// Data pipeline per program studi dan nilai median dihitung secara terpusat oleh backend.
// -----------------------------------------------------------------------------
const prodiPipelineData = computed(() => {
    if (props.chart?.prodi_pipeline && props.chart.prodi_pipeline.length > 0) {
        return props.chart.prodi_pipeline;
    }

    return [];
});

// Opsi stacked bar 100% horizontal untuk membandingkan proporsi tahapan pipeline per prodi.
// Menggunakan batang bertumpuk (stacked bar) dengan legend diletakkan di bagian atas.
const pipelineStackedBarOption = computed(() => {
    const list = prodiPipelineData.value;
    const categories = ['Perusahaan Dilamar', 'Merespon', 'Mewawancara'];
    const colors = ['#B91C1C', '#EAB308', '#0D542B'];
    const labelColors = ['#FFFFFF', '#334155', '#FFFFFF'];
    const rows = list.map((item) => {
        const total = item.dilamar + item.merespon + item.wawancara;

        return { ...item, total };
    });

    return {
        ...baseOption(records.value.length),
        legend: {
            data: categories,
            top: 0,
            left: 'center',
            textStyle: { color: '#334155', fontWeight: 600, fontSize: 12 },
        },
        grid: { left: 16, right: 48, top: 40, bottom: 24, containLabel: true },
        xAxis: {
            type: 'value',
            min: 0,
            max: 100,
            axisLabel: { formatter: '{value}%', color: '#64748B' },
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
        },
        yAxis: {
            type: 'category',
            inverse: true,
            data: rows.map((item) => `${item.prodi} (n=${item.count})`),
            axisLabel: { color: '#334155', width: 180, overflow: 'truncate', fontWeight: 500 },
        },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            formatter: (params) => {
                const item = rows[params[0]?.dataIndex ?? 0] || {};

                return `
                    <div class="font-sans text-xs">
                        <div class="font-bold text-slate-900 border-b border-slate-200 pb-1 mb-1.5">${escapeHtml(item.prodi)} (n=${item.count} alumni)</div>
                        ${categories.map((category, index) => {
                            const value = [item.dilamar, item.merespon, item.wawancara][index];
                            const percentage = item.total > 0 ? (value / item.total) * 100 : 0;

                            return `<div class="text-slate-600 mb-0.5">${category}: <strong>${formatNumber(value, 1)} perusahaan (${formatNumber(percentage, 1)}%)</strong></div>`;
                        }).join('')}
                        <div class="text-[11px] text-slate-500 border-t border-slate-100 pt-1 mt-1">Klik batang untuk memfilter rincian alumni prodi ini.</div>
                    </div>
                `;
            },
        },
        series: categories.map((category, index) => ({
            name: category,
            type: 'bar',
            stack: 'pipeline',
            barMaxWidth: 18,
            itemStyle: { color: colors[index] },
            label: {
                show: true,
                position: 'inside',
                formatter: ({ data }) => {
                    const pct = data?.percentage;
                    return pct && pct >= 10 ? `${formatNumber(pct, 1)}%` : '';
                },
                color: labelColors[index],
                fontWeight: 600,
                fontSize: 10,
            },
            data: rows.map((item) => {
                const value = [item.dilamar, item.merespon, item.wawancara][index];
                const percentage = item.total > 0 ? (value / item.total) * 100 : 0;

                return {
                    name: item.prodi,
                    prodi: item.prodi,
                    category,
                    stageKey: ['f6', 'f7', 'f7a'][index],
                    value: Number(percentage.toFixed(2)),
                    percentage,
                    count: value,
                };
            }),
        })),
    };
});

const pipelineLineTable = computed(() => ({
    columns: [
        { key: 'prodi', label: 'Program Studi' },
        { key: 'dilamar', label: 'Median Dilamar (F6)', align: 'right' },
        { key: 'merespon', label: 'Median Merespon (F7)', align: 'right' },
        { key: 'wawancara', label: 'Median Wawancara (F7a)', align: 'right' },
        { key: 'count', label: 'Responden (n)', align: 'right' },
    ],
    rows: prodiPipelineData.value.map((item) => {
        const fmt = (val) => {
            if (val === null || val === undefined) return '-';
            return val % 1 === 0 ? formatNumber(val, 0) : formatNumber(val, 1);
        };

        return {
            prodi: item.prodi,
            dilamar: fmt(item.dilamar),
            merespon: fmt(item.merespon),
            wawancara: fmt(item.wawancara),
            count: formatNumber(item.count, 0),
        };
    }),
}));

// -----------------------------------------------------------------------------
// 2. SCATTER PLOT 1: Lamaran vs Respons Perusahaan (F6 vs F7)
// -----------------------------------------------------------------------------
const scatter1Option = computed(() => {
    const s1 = scatter1.value;
    const points = applyClusterJitter(s1.data || [], 'f6', 'f7');
    const trendline = s1.trendline;

    const seriesList = [
        {
            name: 'Alumni',
            type: 'scatter',
            symbolSize: 9,
            itemStyle: {
                opacity: 0.85,
                borderColor: '#FFFFFF',
                borderWidth: 1,
            },
            emphasis: {
                scale: 1.8,
                focus: 'self',
                itemStyle: {
                    borderColor: '#0D542B',
                    borderWidth: 2,
                    opacity: 1,
                },
            },
            data: points,
        },
    ];

    if (trendline && trendline.start && trendline.end) {
        seriesList.push({
            name: 'Garis Tren',
            type: 'line',
            data: [trendline.start, trendline.end],
            showSymbol: false,
            lineStyle: {
                type: 'dashed',
                color: '#0D542B',
                width: 2.5,
            },
            tooltip: {
                show: true,
            },
        });
    }

    return {
        ...baseOption(),
        grid: { left: 45, right: 120, top: 40, bottom: 50, containLabel: true },
        visualMap: createIpkVisualMap(),
        legend: {
            show: true,
            top: 6,
            right: 120,
            data: [
                { name: 'Alumni', icon: 'circle' },
                { name: 'Garis Tren', icon: 'line', lineStyle: { type: 'dashed' } },
            ],
            textStyle: { color: '#475569', fontSize: 11, fontWeight: 500 },
        },
        xAxis: {
            type: 'value',
            name: 'Jumlah Perusahaan Dilamar (F6)',
            nameLocation: 'middle',
            nameGap: 30,
            nameTextStyle: { color: '#334155', fontWeight: 600, fontSize: 11 },
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
            axisLine: { lineStyle: { color: '#CBD5E1' } },
        },
        yAxis: {
            type: 'value',
            name: 'Jumlah Perusahaan Merespons (F7)',
            nameLocation: 'middle',
            nameGap: 36,
            nameTextStyle: { color: '#334155', fontWeight: 600, fontSize: 11 },
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
            axisLine: { lineStyle: { color: '#CBD5E1' } },
        },
        tooltip: {
            trigger: 'item',
            formatter: (params) => {
                if (params.seriesName === 'Garis Tren') {
                    if (!trendline) return '';
                    const sign = trendline.intercept >= 0 ? '+' : '-';
                    return `
                        <div class="font-sans text-xs">
                            <strong class="text-slate-900">Garis Tren Linier OLS</strong><br>
                            Persamaan: <code class="font-bold text-[#0D542B]">y = ${trendline.slope}x ${sign} ${Math.abs(trendline.intercept)}</code><br>
                            <span class="text-slate-500">Kemiringan (Slope): <strong>${trendline.slope}</strong></span>
                        </div>
                    `;
                }
                const d = params.data || {};
                const ipkFormatted = d.ipk !== null && d.ipk !== undefined ? Number(d.ipk).toFixed(2) : 'Tidak tersedia';
                const f6Val = d.rawX !== undefined ? d.rawX : d.f6;
                const f7Val = d.rawY !== undefined ? d.rawY : d.f7;
                return `
                    <div class="font-sans text-xs">
                        <div class="font-bold text-slate-900 border-b border-slate-200 pb-1 mb-1.5">${escapeHtml(d.name || 'Alumni')} (${escapeHtml(d.nim || '-')})</div>
                        <div class="text-slate-600 mb-0.5"><span class="text-slate-500">Program Studi:</span> <strong>${escapeHtml(d.prodi || '-')}</strong></div>
                        <div class="text-slate-600 mb-0.5"><span class="text-slate-500">IPK Kumulatif:</span> <strong class="text-emerald-700 font-mono">${ipkFormatted}</strong></div>
                        <div class="text-slate-600 mb-0.5"><span class="text-slate-500">Perusahaan Dilamar (F6):</span> <strong>${formatNumber(f6Val, 0)}</strong></div>
                        <div class="text-slate-600"><span class="text-slate-500">Perusahaan Merespons (F7):</span> <strong>${formatNumber(f7Val, 0)}</strong></div>
                    </div>
                `;
            },
        },
        series: seriesList,
    };
});

const legacyScatter1Table = computed(() => {
    const s1 = scatter1.value;
    const summary = s1.summary || {};
    const stats = s1.stats || {};
    const trendline = s1.trendline;
    const sign = trendline?.intercept >= 0 ? '+' : '-';

    return {
        columns: [
            { key: 'metrik', label: 'Metrik Analisis' },
            { key: 'nilai', label: 'Nilai Statistik', align: 'right' },
            { key: 'keterangan', label: 'Keterangan & Interpretasi' },
        ],
        rows: [
            {
                metrik: 'Jumlah Alumni Valid (N)',
                nilai: formatNumber(summary.valid_n || 0, 0),
                keterangan: 'Responden dengan isian numerik valid pada F6 dan F7',
            },
            {
                metrik: 'Total Perusahaan Dilamar (F6)',
                nilai: formatNumber(summary.total_applications || 0, 0),
                keterangan: 'Akumulasi seluruh berkas lamaran yang dikirim alumni',
            },
            {
                metrik: 'Total Perusahaan Merespons (F7)',
                nilai: formatNumber(summary.total_responses || 0, 0),
                keterangan: 'Akumulasi seluruh respons balik dari perusahaan',
            },
            {
                metrik: 'Total Undangan Wawancara (F7a)',
                nilai: formatNumber(props.chart?.summary?.total_interviews || 0, 0),
                keterangan: 'Akumulasi total panggilan wawancara kerja yang diperoleh',
            },
            {
                metrik: 'Median IPK Alumni',
                nilai: summary.median_ipk !== null && summary.median_ipk !== undefined ? formatNumber(summary.median_ipk, 2) : '-',
                keterangan: 'Nilai tengah IPK lulusan pada kelompok analisis ini',
            },
            {
                metrik: 'Rata-rata IPK Alumni',
                nilai: summary.mean_ipk !== null && summary.mean_ipk !== undefined ? formatNumber(summary.mean_ipk, 2) : '-',
                keterangan: 'Rata-rata hitung IPK responden valid',
            },
            {
                metrik: 'Korelasi Spearman (ρ)',
                nilai: stats.rho !== null && stats.rho !== undefined ? formatNumber(stats.rho, 4) : '-',
                keterangan: Number(stats.rho) >= 0.7 ? 'Korelasi positif sangat kuat' : (Number(stats.rho) >= 0.5 ? 'Korelasi positif kuat' : 'Korelasi moderat'),
            },
            {
                metrik: 'Nilai Signifikansi (p-value)',
                nilai: stats.p_value !== null && stats.p_value !== undefined ? (stats.p_value < 0.001 ? '< 0,001' : formatNumber(stats.p_value, 4)) : '-',
                keterangan: (stats.p_value !== null && stats.p_value < 0.05) ? 'Signifikan secara statistik (p < 0,05)' : 'Tidak signifikan secara statistik',
            },
            {
                metrik: 'Persamaan Garis Tren',
                nilai: trendline ? `y = ${trendline.slope}x ${sign} ${Math.abs(trendline.intercept)}` : '-',
                keterangan: 'Regresi linier OLS (Slope dan intersep perbandingan lamaran)',
            },
        ],
    };
});

// -----------------------------------------------------------------------------
// 3. SCATTER PLOT 2: Respons vs Undangan Wawancara (F7 vs F7a)
// -----------------------------------------------------------------------------
const scatter2Option = computed(() => {
    const s2 = scatter2.value;
    const points = applyClusterJitter(s2.data || [], 'f7', 'f7a');
    const trendline = s2.trendline;

    const seriesList = [
        {
            name: 'Alumni',
            type: 'scatter',
            symbolSize: 9,
            itemStyle: {
                opacity: 0.85,
                borderColor: '#FFFFFF',
                borderWidth: 1,
            },
            emphasis: {
                scale: 1.8,
                focus: 'self',
                itemStyle: {
                    borderColor: '#0D542B',
                    borderWidth: 2,
                    opacity: 1,
                },
            },
            data: points,
        },
    ];

    if (trendline && trendline.start && trendline.end) {
        seriesList.push({
            name: 'Garis Tren',
            type: 'line',
            data: [trendline.start, trendline.end],
            showSymbol: false,
            lineStyle: {
                type: 'dashed',
                color: '#0D542B',
                width: 2.5,
            },
            tooltip: {
                show: true,
            },
        });
    }

    return {
        ...baseOption(),
        grid: { left: 45, right: 120, top: 40, bottom: 50, containLabel: true },
        visualMap: createIpkVisualMap(),
        legend: {
            show: true,
            top: 6,
            right: 120,
            data: [
                { name: 'Alumni', icon: 'circle' },
                { name: 'Garis Tren', icon: 'line', lineStyle: { type: 'dashed' } },
            ],
            textStyle: { color: '#475569', fontSize: 11, fontWeight: 500 },
        },
        xAxis: {
            type: 'value',
            name: 'Jumlah Perusahaan Merespons (F7)',
            nameLocation: 'middle',
            nameGap: 30,
            nameTextStyle: { color: '#334155', fontWeight: 600, fontSize: 11 },
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
            axisLine: { lineStyle: { color: '#CBD5E1' } },
        },
        yAxis: {
            type: 'value',
            name: 'Jumlah Undangan Wawancara (F7a)',
            nameLocation: 'middle',
            nameGap: 36,
            nameTextStyle: { color: '#334155', fontWeight: 600, fontSize: 11 },
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
            axisLine: { lineStyle: { color: '#CBD5E1' } },
        },
        tooltip: {
            trigger: 'item',
            formatter: (params) => {
                if (params.seriesName === 'Garis Tren') {
                    if (!trendline) return '';
                    const sign = trendline.intercept >= 0 ? '+' : '-';
                    return `
                        <div class="font-sans text-xs">
                            <strong class="text-slate-900">Garis Tren Linier OLS</strong><br>
                            Persamaan: <code class="font-bold text-[#0D542B]">y = ${trendline.slope}x ${sign} ${Math.abs(trendline.intercept)}</code><br>
                            <span class="text-slate-500">Kemiringan (Slope): <strong>${trendline.slope}</strong></span>
                        </div>
                    `;
                }
                const d = params.data || {};
                const ipkFormatted = d.ipk !== null && d.ipk !== undefined ? Number(d.ipk).toFixed(2) : 'Tidak tersedia';
                const f7Val = d.rawX !== undefined ? d.rawX : d.f7;
                const f7aVal = d.rawY !== undefined ? d.rawY : d.f7a;
                return `
                    <div class="font-sans text-xs">
                        <div class="font-bold text-slate-900 border-b border-slate-200 pb-1 mb-1.5">${escapeHtml(d.name || 'Alumni')} (${escapeHtml(d.nim || '-')})</div>
                        <div class="text-slate-600 mb-0.5"><span class="text-slate-500">Program Studi:</span> <strong>${escapeHtml(d.prodi || '-')}</strong></div>
                        <div class="text-slate-600 mb-0.5"><span class="text-slate-500">IPK Kumulatif:</span> <strong class="text-emerald-700 font-mono">${ipkFormatted}</strong></div>
                        <div class="text-slate-600 mb-0.5"><span class="text-slate-500">Perusahaan Merespons (F7):</span> <strong>${formatNumber(f7Val, 0)}</strong></div>
                        <div class="text-slate-600"><span class="text-slate-500">Undangan Wawancara (F7a):</span> <strong>${formatNumber(f7aVal, 0)}</strong></div>
                    </div>
                `;
            },
        },
        series: seriesList,
    };
});

const legacyScatter2Table = computed(() => {
    const s2 = scatter2.value;
    const summary = s2.summary || {};
    const stats = s2.stats || {};
    const trendline = s2.trendline;
    const sign = trendline?.intercept >= 0 ? '+' : '-';

    return {
        columns: [
            { key: 'metrik', label: 'Metrik Analisis' },
            { key: 'nilai', label: 'Nilai Statistik', align: 'right' },
            { key: 'keterangan', label: 'Keterangan & Interpretasi' },
        ],
        rows: [
            {
                metrik: 'Jumlah Alumni Valid (N)',
                nilai: formatNumber(summary.valid_n || 0, 0),
                keterangan: 'Responden dengan isian numerik valid pada F7 dan F7a',
            },
            {
                metrik: 'Total Perusahaan Merespons (F7)',
                nilai: formatNumber(summary.total_responses || 0, 0),
                keterangan: 'Akumulasi respons diterima dari perusahaan',
            },
            {
                metrik: 'Total Undangan Wawancara (F7a)',
                nilai: formatNumber(summary.total_interviews || 0, 0),
                keterangan: 'Akumulasi total undangan wawancara yang diraih alumni',
            },
            {
                metrik: 'Total Perusahaan Dilamar (F6)',
                nilai: formatNumber(props.chart?.summary?.total_applications || 0, 0),
                keterangan: 'Akumulasi total lamaran terkirim dari seluruh lulusan',
            },
            {
                metrik: 'Median IPK Alumni',
                nilai: summary.median_ipk !== null && summary.median_ipk !== undefined ? formatNumber(summary.median_ipk, 2) : '-',
                keterangan: 'Nilai tengah IPK lulusan pada kelompok analisis ini',
            },
            {
                metrik: 'Rata-rata IPK Alumni',
                nilai: summary.mean_ipk !== null && summary.mean_ipk !== undefined ? formatNumber(summary.mean_ipk, 2) : '-',
                keterangan: 'Rata-rata hitung IPK responden valid',
            },
            {
                metrik: 'Korelasi Spearman (ρ)',
                nilai: stats.rho !== null && stats.rho !== undefined ? formatNumber(stats.rho, 4) : '-',
                keterangan: Number(stats.rho) >= 0.7 ? 'Korelasi positif sangat kuat' : (Number(stats.rho) >= 0.5 ? 'Korelasi positif kuat' : 'Korelasi moderat'),
            },
            {
                metrik: 'Nilai Signifikansi (p-value)',
                nilai: stats.p_value !== null && stats.p_value !== undefined ? (stats.p_value < 0.001 ? '< 0,001' : formatNumber(stats.p_value, 4)) : '-',
                keterangan: (stats.p_value !== null && stats.p_value < 0.05) ? 'Signifikan secara statistik (p < 0,05)' : 'Tidak signifikan secara statistik',
            },
            {
                metrik: 'Persamaan Garis Tren',
                nilai: trendline ? `y = ${trendline.slope}x ${sign} ${Math.abs(trendline.intercept)}` : '-',
                keterangan: 'Regresi linier OLS (Slope dan intersep konversi respons ke wawancara)',
            },
        ],
    };
});

// Tabel audit individu untuk lamaran terhadap respons perusahaan dari backend.
const scatter1Table = computed(() => props.chart?.relation_tables?.applications_responses || {
    columns: [],
    rows: [],
    summary: { columns: [], rows: [] },
});

// Tabel audit individu untuk respons perusahaan terhadap undangan wawancara dari backend.
const scatter2Table = computed(() => props.chart?.relation_tables?.responses_interviews || {
    columns: [],
    rows: [],
    summary: { columns: [], rows: [] },
});

// -----------------------------------------------------------------------------
// 4. STACKED BAR: Distribusi Konversi berdasarkan Kelompok IPK
// -----------------------------------------------------------------------------
const conversionIpkBands = [
    { key: 'ipk_unavailable', label: 'IPK Tidak tersedia', color: '#CBD5E1', labelColor: '#334155' },
    { key: 'ipk_0_1', label: 'IPK 0–<1', min: 0, max: 1, color: '#B91C1C', labelColor: '#FFFFFF' },
    { key: 'ipk_1_2', label: 'IPK 1–<2', min: 1, max: 2, color: '#F97316', labelColor: '#FFFFFF' },
    { key: 'ipk_2_3', label: 'IPK 2–<3', min: 2, max: 3, color: '#FACC15', labelColor: '#334155' },
    { key: 'ipk_3_4', label: 'IPK 3–4', min: 3, max: 4, color: '#0D542B', labelColor: '#FFFFFF' },
];

// Mendefinisikan pembilang dan penyebut setiap tahap agar angka tetap audit-able.
const conversionStageDefinitions = [
    { key: 'app_to_resp', label: 'Lamaran → Respons', fromKey: 'f6', toKey: 'f7' },
    { key: 'resp_to_intv', label: 'Respons → Wawancara', fromKey: 'f7', toKey: 'f7a' },
    { key: 'app_to_intv', label: 'Lamaran → Wawancara', fromKey: 'f6', toKey: 'f7a' },
];

// Mengagregasi jumlah tahap per kelompok IPK; statistik segmen dipasok oleh backend.
const conversionIpkStats = computed(() => conversionStageDefinitions.map((stage) => {
    const backendStage = props.chart?.conversion_ipk_stats?.find((item) => item.key === stage.key);
    if (backendStage) {
        return {
            ...stage,
            fromCount: backendStage.from_count,
            toCount: backendStage.to_count,
            conversionPercentage: backendStage.conversion_percentage,
            counts: backendStage.counts,
            segmentPercentages: backendStage.segment_percentages,
        };
    }

    return {
        ...stage,
        fromCount: 0,
        toCount: 0,
        conversionPercentage: 0,
        counts: Object.fromEntries(conversionIpkBands.map((band) => [band.key, 0])),
        segmentPercentages: Object.fromEntries(conversionIpkBands.map((band) => [band.key, 0])),
    };
}));

// Format label segmen berdasarkan proporsi jumlah pembilang pada setiap tahap.
const formatConversionIpkLabel = ({ data }) => data?.percentage >= 10 ? `${formatNumber(data.percentage, 1)}%` : '';

const conversionBarOption = computed(() => {
    const rows = conversionIpkStats.value;
    const maxTotal = Math.max(1, ...rows.map((row) => row.toCount));

    return {
        ...baseOption(alumniList.value.length),
        themePalette: conversionIpkBands.map((band) => band.color),
        legend: { data: conversionIpkBands.map((band) => band.label), bottom: 0, type: 'scroll', textStyle: { color: '#475569', fontSize: 11 } },
        grid: { left: 24, right: 48, top: 28, bottom: 64, containLabel: true },
        xAxis: { type: 'value', min: 0, max: maxTotal, name: 'Jumlah perusahaan', nameTextStyle: { color: '#64748B' }, axisLabel: { formatter: (value) => formatNumber(value, 0), color: '#64748B' }, splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } } },
        yAxis: { type: 'category', inverse: true, data: rows.map((row) => row.label), axisLabel: { color: '#334155', fontWeight: 600, fontSize: 12 }, axisTick: { show: false }, axisLine: { lineStyle: { color: '#CBD5E1' } } },
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            formatter: (params) => {
                const row = rows[params?.[0]?.dataIndex];
                if (!row) return '';

                return `
                    <div class="font-sans text-xs">
                        <div class="font-bold text-slate-900 border-b border-slate-200 pb-1 mb-1.5">${escapeHtml(row.label)}</div>
                        <div class="text-slate-700 mb-1">Konversi: <strong class="text-emerald-700 font-mono">${formatNumber(row.conversionPercentage, 1)}%</strong></div>
                        ${conversionIpkBands.map((band) => {
                            const count = row.counts[band.key] || 0;
                            const percentage = row.toCount > 0 ? (count / row.toCount) * 100 : 0;

                            return `<div class="text-slate-600 mb-0.5">${band.label}: <strong>${formatNumber(count, 0)} (${formatNumber(percentage, 1)}%)</strong></div>`;
                        }).join('')}
                        <div class="text-slate-600 mt-1">Total pembilang: <strong>${formatNumber(row.toCount, 0)}</strong></div>
                        <div class="text-slate-600">Total penyebut: <strong>${formatNumber(row.fromCount, 0)}</strong></div>
                    </div>
                `;
            },
        },
        series: conversionIpkBands.map((band, index) => ({
            name: band.label,
            type: 'bar',
            stack: 'conversion',
            stackOrder: index,
            barMaxWidth: 34,
            itemStyle: { color: band.color },
            label: { show: true, position: 'inside', formatter: formatConversionIpkLabel, color: band.labelColor, fontWeight: 600, fontSize: 10 },
            data: rows.map((row) => {
                const count = row.counts[band.key] || 0;

                return { name: row.label, category: band.label, value: count, count, total: row.toCount, percentage: row.toCount > 0 ? (count / row.toCount) * 100 : 0, prodi: row.label };
            }),
        })),
    };
});

const conversionBarTable = computed(() => {
    const rows = conversionIpkStats.value;

    return {
        columns: [
            { key: 'tahapan', label: 'Tahapan Proses Lamaran' },
            { key: 'tahap_awal', label: 'Jumlah Awal (Penyebut)', align: 'right' },
            { key: 'tahap_berikutnya', label: 'Jumlah Lanjutan (Pembilang)', align: 'right' },
            { key: 'persentase', label: 'Persentase Konversi', align: 'right' },
            { key: 'rumus', label: 'Alur & Rumus Perhitungan' },
            { key: 'keterangan', label: 'Interpretasi Hasil' },
        ],
        rows: rows.map((row) => {
            const pct = `${formatNumber(row.conversionPercentage, 1)}%`;
            const from = formatNumber(row.fromCount, 0);
            const to = formatNumber(row.toCount, 0);
            const rumus = `(${to} / ${from}) × 100% = ${pct}`;
            const keterangan = row.key === 'app_to_resp'
                ? `${pct} dari lamaran terkirim berhasil direspons perusahaan.`
                : (row.key === 'resp_to_intv'
                    ? `${pct} dari respons perusahaan berlanjut ke undangan wawancara.`
                    : `${pct} rasio end-to-end dari lamaran terkirim hingga meraih wawancara.`);

            return {
                tahapan: row.label,
                tahap_awal: from,
                tahap_berikutnya: to,
                persentase: pct,
                rumus,
                keterangan,
            };
        }),
    };
});

// -----------------------------------------------------------------------------
// 5. BAR CHART: Median Tahapan Lamaran Universitas
// -----------------------------------------------------------------------------
const stagesBarOption = computed(() => {
    const list = series.value.map((item) => ({
        name: seriesLabel(item),
        value: Number(item.median || 0),
        count: item.count,
        itemStyle: { color: '#0D542B', borderRadius: [0, 4, 4, 0] },
    }));

    return {
        ...baseOption(validResponses.value),
        grid: { left: 24, right: 48, top: 20, bottom: 30, containLabel: true },
        xAxis: { type: 'value', name: 'Median Jumlah Perusahaan', splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } } },
        yAxis: {
            type: 'category',
            inverse: true,
            data: list.map((i) => i.name),
            axisLabel: { color: '#334155', fontWeight: 600, fontSize: 12 },
        },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            formatter: (params) => {
                const pt = params[0]?.data || {};
                return `<strong>${escapeHtml(pt.name)}</strong><br>Median: <strong>${formatNumber(pt.value, 1)} perusahaan</strong><br>Respon valid: ${formatNumber(pt.count, 0)} alumni`;
            },
        },
        series: [{
            name: 'Median',
            type: 'bar',
            barMaxWidth: 32,
            data: list,
            label: {
                show: true,
                position: 'right',
                fontWeight: 'bold',
                color: '#0D542B',
                formatter: ({ value }) => `${formatNumber(value, 1)} perusahaan`,
            },
        }],
    };
});

const stagesBarTable = computed(() => ({
    columns: [
        { key: 'name', label: 'Tahapan Proses Lamaran' },
        { key: 'medianDisplay', label: 'Median Perusahaan', align: 'right' },
        { key: 'countDisplay', label: 'Responden Valid', align: 'right' },
    ],
    rows: series.value.map((item) => ({
        name: seriesLabel(item),
        medianDisplay: formatNumber(item.median, 1),
        countDisplay: formatNumber(item.count, 0),
    })),
}));

// -----------------------------------------------------------------------------
// Konfigurasi Views untuk Kontainer Terpadu (Stay & Zero Layout Shift)
// -----------------------------------------------------------------------------
const views = computed(() => {
    const s1 = scatter1.value;
    const s2 = scatter2.value;
    const st0 = conversion.value?.stages?.[0] || {};
    const st1 = conversion.value?.stages?.[1] || {};
    const st2 = conversion.value?.stages?.[2] || {};

    return [
        {
            key: 'pipeline_prodi',
            chartType: 'stacked_horizontal',
            tabLabel: 'Pipeline per Prodi',
            title: 'Pipeline Rekrutmen per Prodi',
            description: 'Proporsi median perusahaan dilamar, direspons, dan wawancara.',
            option: pipelineStackedBarOption.value,
            tableData: pipelineLineTable.value,
            height: 480,
            analysis: {
                title: 'Kilas Efisiensi Rekrutmen Prodi',
                statusBadge: 'Pipeline Antarjurusan',
                badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
                lead: 'Komparasi proporsi proses seleksi kerja dari tahap lamaran, respons perusahaan, hingga wawancara di masing-masing prodi.',
                detail: 'Proporsi bertumpuk memudahkan identifikasi disparitas efisiensi lamaran antarprogram studi.',
            },
        },
        {
            key: 'scatter_applications_responses',
            chartType: 'table',
            tabLabel: 'Lamaran vs Respons',
            title: 'Lamaran vs Respons',
            description: 'Tabel perbandingan lamaran (F6) dan respons (F7).',
            tableOnly: true,
            option: null,
            tableData: scatter1Table.value,
            analysis: {
                title: 'Kilas Lamaran dan Respons',
                statusBadge: `N = ${formatNumber(s1.summary?.valid_n || 0, 0)}`,
                badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
                lead: 'Detail individu diurutkan dari persentase respons tertinggi.',
                detail: 'Pivot program studi menampilkan rata-rata IPK, tahapan, dan persentase respons tertinggi.',
            },
        },
        {
            key: 'scatter_responses_interviews',
            chartType: 'table',
            tabLabel: 'Respons vs Wawancara',
            title: 'Respons vs Wawancara',
            description: 'Tabel perbandingan respons (F7) dan wawancara (F7a).',
            tableOnly: true,
            option: null,
            tableData: scatter2Table.value,
            analysis: {
                title: 'Kilas Respons dan Wawancara',
                statusBadge: `N = ${formatNumber(s2.summary?.valid_n || 0, 0)}`,
                badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
                lead: 'Detail individu diurutkan dari persentase undangan wawancara tertinggi.',
                detail: 'Pivot program studi menampilkan rata-rata IPK, tahapan, dan persentase wawancara tertinggi.',
            },
        },
        {
            key: 'conversion_stages',
            chartType: 'stacked_horizontal',
            tabLabel: 'Konversi Tahapan',
            title: 'Konversi Tahapan Rekrutmen',
            description: 'Rasio keberhasilan lamaran hingga wawancara per kelompok IPK.',
            option: conversionBarOption.value,
            tableData: conversionBarTable.value,
            height: 360,
            analysis: {
                title: 'Kilas Efisiensi Konversi Rekrutmen',
                statusBadge: 'Konversi Berbobot',
                badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
                lead: `Sebanyak ${formatNumber(st0.percentage, 1)}% lamaran berhasil memperoleh respons (${formatNumber(st0.to_count, 0)} dari ${formatNumber(st0.from_count, 0)} lamaran), dan ${formatNumber(st1.percentage, 1)}% dari respons tersebut berlanjut ke wawancara (${formatNumber(st1.to_count, 0)} dari ${formatNumber(st1.from_count, 0)} respons).`,
                detail: `Secara menyeluruh, rasio konversi lamaran langsung hingga wawancara adalah ${formatNumber(st2.percentage, 1)}% (${formatNumber(st2.to_count, 0)} dari ${formatNumber(st2.from_count, 0)} total lamaran).`,
            },
        },
        {
            key: 'stages',
            chartType: 'horizontal_bar',
            tabLabel: 'Median Universitas',
            title: 'Median Pipeline Universitas',
            description: 'Agregat median lamaran, respons, dan wawancara universitas.',
            option: stagesBarOption.value,
            tableData: stagesBarTable.value,
            height: 320,
            analysis: {
                title: 'Kilas Rerata Universitas',
                statusBadge: 'Median Agregat',
                badgeClass: 'bg-slate-100 text-slate-700',
                lead: 'Rerata median universitas menunjukkan efisiensi pencarian kerja.',
                detail: 'Median wawancara yang mendekati respons menunjukkan probabilitas tinggi berlanjut ke tahap wawancara.',
            },
        },
    ];
});
</script>

<template>
    <AnalyticsTabbedContainer
        :views="views"
        :rows="enrichedRows"
        :selected-label="selectedLabel"
        :loading="loading"
        indicator-badge="Indikator 09"
        @select="emit('select', $event)"
        @reset="emit('reset')"
    />
</template>
