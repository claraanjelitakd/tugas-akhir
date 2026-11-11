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
    calculateMedian,
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
// -----------------------------------------------------------------------------
const prodiPipelineData = computed(() => {
    if (props.chart?.prodi_pipeline) {
        return props.chart.prodi_pipeline;
    }

    const list = alumniList.value;
    if (list && list.length > 0) {
        const prodiMap = {};
        list.forEach((a) => {
            const p = a.nama_prodi || 'Tanpa prodi';
            if (!prodiMap[p]) prodiMap[p] = { prodi: p, f6: [], f7: [], f7a: [], count: 0 };
            prodiMap[p].count++;
            if (a.f6 !== null && a.f6 !== undefined) prodiMap[p].f6.push(Number(a.f6));
            if (a.f7 !== null && a.f7 !== undefined) prodiMap[p].f7.push(Number(a.f7));
            if (a.f7a !== null && a.f7a !== undefined) prodiMap[p].f7a.push(Number(a.f7a));
        });

        return Object.values(prodiMap)
            .map((item) => ({
                prodi: item.prodi,
                count: item.count,
                dilamar: calculateMedian(item.f6),
                merespon: calculateMedian(item.f7),
                wawancara: calculateMedian(item.f7a),
            }))
            .filter((item) => item.count > 0)
            .sort((a, b) => b.count - a.count);
    }

    const prodis = [...new Set(records.value.map((r) => r.nama_prodi || 'Tanpa prodi'))];
    return prodis.map((prodi) => {
        const prodiRows = records.value.filter((r) => (r.nama_prodi || 'Tanpa prodi') === prodi);
        const f6 = prodiRows.filter((r) => r.kode_pertanyaan === 'F6').map((r) => Number(r.value));
        const f7 = prodiRows.filter((r) => r.kode_pertanyaan === 'F7').map((r) => Number(r.value));
        const f7a = prodiRows.filter((r) => r.kode_pertanyaan === 'F7A' || r.kode_pertanyaan === 'F7a').map((r) => Number(r.value));

        return {
            prodi,
            count: new Set(prodiRows.map((r) => r.biodata_id)).size,
            dilamar: calculateMedian(f6),
            merespon: calculateMedian(f7),
            wawancara: calculateMedian(f7a),
        };
    }).filter((item) => item.count > 0).sort((a, b) => b.count - a.count);
});

// Opsi stacked bar 100% horizontal untuk membandingkan proporsi tahapan pipeline per prodi.
const pipelineStackedBarOption = computed(() => {
    const list = prodiPipelineData.value;
    const categories = ['Perusahaan Dilamar', 'Merespon', 'Mewawancara'];
    const colors = ['#B91C1C', '#FACC15', '#0D542B'];
    const labelColors = ['#FFFFFF', '#334155', '#FFFFFF'];
    const rows = list.map((item) => {
        const total = item.dilamar + item.merespon + item.wawancara;

        return { ...item, total };
    });

    return {
        ...baseOption(records.value.length),
        legend: {
            data: categories,
            bottom: 0,
            textStyle: { color: '#334155', fontWeight: 600, fontSize: 12 },
        },
        grid: { left: 24, right: 36, top: 28, bottom: 64, containLabel: true },
        xAxis: {
            type: 'value',
            min: 0,
            max: 100,
            name: 'Proporsi median tahapan',
            axisLabel: { formatter: '{value}%', color: '#64748B' },
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
        },
        yAxis: {
            type: 'category',
            inverse: true,
            data: rows.map((item) => `${item.prodi} (n=${item.count})`),
            axisLabel: { color: '#334155', width: 220, overflow: 'truncate' },
        },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'axis',
            formatter: (params) => {
                const item = rows[params[0]?.dataIndex ?? 0] || {};
                return `
                    <div class="font-sans text-xs">
                        <div class="font-bold text-slate-900 border-b border-slate-200 pb-1 mb-1.5">${escapeHtml(item.prodi)} (n=${item.count})</div>
                        ${categories.map((category, index) => {
        const value = [item.dilamar, item.merespon, item.wawancara][index];
        const percentage = item.total > 0 ? (value / item.total) * 100 : 0;

        return `<div class="text-slate-600 mb-0.5">${category}: <strong>${formatNumber(value, 1)} (${formatNumber(percentage, 1)}%)</strong></div>`;
    }).join('')}
                        <div class="text-slate-600">Total median tahapan: <strong>${formatNumber(item.total, 1)}</strong></div>
                    </div>
                `;
            },
        },
        series: categories.map((category, index) => ({
            name: category,
            type: 'bar',
            stack: 'pipeline',
            stackOrder: index,
            barMaxWidth: 28,
            itemStyle: { color: colors[index] },
            label: {
                show: true,
                position: 'inside',
                formatter: ({ data }) => data?.value >= 10 ? `${formatNumber(data.value, 1)}%` : '',
                color: labelColors[index],
                fontWeight: 600,
                fontSize: 10,
            },
            data: rows.map((item) => {
                const value = [item.dilamar, item.merespon, item.wawancara][index];

                return {
                    name: item.prodi,
                    category,
                    value: item.total > 0 ? Number(((value / item.total) * 100).toFixed(1)) : 0,
                    count: value,
                    total: item.total,
                    prodi: item.prodi,
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
    rows: prodiPipelineData.value.map((item) => ({
        prodi: item.prodi,
        dilamar: formatNumber(item.dilamar, 1),
        merespon: formatNumber(item.merespon, 1),
        wawancara: formatNumber(item.wawancara, 1),
        count: formatNumber(item.count, 0),
    })),
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

// Menyusun detail individu dan pivot prodi dari dua tahap pipeline yang dibandingkan.
const buildPipelineAuditTable = (data, firstKey, secondKey, firstLabel, secondLabel) => {
    const detail = (data || []).map((item) => {
        const firstValue = Number(item[firstKey]);
        const secondValue = Number(item[secondKey]);
        const percentage = firstValue > 0 ? (secondValue / firstValue) * 100 : null;

        return {
            nama_alumni: item.name || '-',
            nim: item.nim || '-',
            prodi: item.prodi || 'Tanpa prodi',
            ipk: item.ipk === null || item.ipk === undefined ? '-' : formatNumber(item.ipk, 2),
            tahap_awal: formatNumber(firstValue, 0),
            tahap_akhir: formatNumber(secondValue, 0),
            persentase: percentage === null ? '-' : `${formatNumber(percentage, 1)}%`,
            _firstValue: firstValue,
            _secondValue: secondValue,
            _percentage: percentage,
            _ipk: item.ipk === null || item.ipk === undefined ? null : Number(item.ipk),
        };
    }).sort((left, right) => (right._percentage ?? -1) - (left._percentage ?? -1) || right._ipk - left._ipk);

    const groups = new Map();
    detail.forEach((row) => {
        if (!groups.has(row.prodi)) groups.set(row.prodi, []);
        groups.get(row.prodi).push(row);
    });

    const pivot = [...groups.entries()].map(([prodi, rows]) => {
        const validPercentages = rows.filter((row) => row._percentage !== null);
        const validIpk = rows.filter((row) => row._ipk !== null).map((row) => row._ipk);
        const top = validPercentages.sort((left, right) => right._percentage - left._percentage || (right._ipk ?? -1) - (left._ipk ?? -1))[0];
        const average = (key, source = rows) => source.length > 0 ? source.reduce((sum, row) => sum + row[key], 0) / source.length : null;

        return {
            prodi,
            alumni_valid: formatNumber(rows.length, 0),
            rata_ipk: validIpk.length > 0 ? formatNumber(validIpk.reduce((sum, value) => sum + value, 0) / validIpk.length, 2) : '-',
            rata_awal: formatNumber(average('_firstValue'), 1),
            rata_akhir: formatNumber(average('_secondValue'), 1),
            rata_persentase: validPercentages.length > 0 ? `${formatNumber(average('_percentage', validPercentages), 1)}%` : '-',
            alumni_tertinggi: top ? `${top.nama_alumni} (${formatNumber(top._percentage, 1)}%)` : '-',
            ipk_tertinggi: top?._ipk === null || top?._ipk === undefined ? '-' : formatNumber(top._ipk, 2),
            _sortPercentage: top?._percentage ?? -1,
        };
    }).sort((left, right) => right._sortPercentage - left._sortPercentage || left.prodi.localeCompare(right.prodi, 'id'));

    return {
        columns: [
            { key: 'nama_alumni', label: 'Nama Alumni' },
            { key: 'nim', label: 'NIM' },
            { key: 'prodi', label: 'Program Studi' },
            { key: 'ipk', label: 'IPK', align: 'right' },
            { key: 'tahap_awal', label: firstLabel, align: 'right' },
            { key: 'tahap_akhir', label: secondLabel, align: 'right' },
            { key: 'persentase', label: 'Persentase', align: 'right' },
        ],
        rows: detail,
        summary: {
            columns: [
                { key: 'prodi', label: 'Program Studi' },
                { key: 'alumni_valid', label: 'Alumni Valid', align: 'right' },
                { key: 'rata_ipk', label: 'Rata-rata IPK', align: 'right' },
                { key: 'rata_awal', label: `Rata-rata ${firstLabel}`, align: 'right' },
                { key: 'rata_akhir', label: `Rata-rata ${secondLabel}`, align: 'right' },
                { key: 'rata_persentase', label: 'Rata-rata Persentase', align: 'right' },
                { key: 'alumni_tertinggi', label: 'Persentase Tertinggi' },
                { key: 'ipk_tertinggi', label: 'IPK Alumni Tertinggi', align: 'right' },
            ],
            rows: pivot,
        },
    };
};

// Tabel audit individu untuk lamaran terhadap respons perusahaan.
const scatter1Table = computed(() => props.chart?.relation_tables?.applications_responses || buildPipelineAuditTable(
    scatter1.value.data,
    'f6',
    'f7',
    'Lamaran (F6)',
    'Respons (F7)',
));

// Tabel audit individu untuk respons perusahaan terhadap undangan wawancara.
const scatter2Table = computed(() => props.chart?.relation_tables?.responses_interviews || buildPipelineAuditTable(
    scatter2.value.data,
    'f7',
    'f7a',
    'Respons (F7)',
    'Wawancara (F7a)',
));

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

// Mengelompokkan IPK dengan interval konsisten dan tetap menyimpan nilai kosong.
const getConversionIpkBand = (value) => {
    const unavailableBand = conversionIpkBands.find((band) => band.key === 'ipk_unavailable');
    if (value === null || value === undefined || String(value).trim() === '') return unavailableBand;

    const ipk = Number(value);
    if (!Number.isFinite(ipk) || ipk < 0 || ipk > 4) return unavailableBand;

    return conversionIpkBands.find((band) => band.min !== undefined && ipk >= band.min && (ipk < band.max || (band.max === 4 && ipk <= 4))) || unavailableBand;
};

// Mengagregasi jumlah tahap per kelompok IPK; panjang bar memakai jumlah pembilang.
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

    const stageRecords = alumniList.value.filter((record) => {
        const from = Number(record[stage.fromKey]);
        const to = Number(record[stage.toKey]);

        return Number.isFinite(from) && from > 0 && Number.isFinite(to) && to >= 0;
    });
    const fromCount = stageRecords.reduce((sum, record) => sum + Number(record[stage.fromKey]), 0);
    const toCount = stageRecords.reduce((sum, record) => sum + Number(record[stage.toKey]), 0);
    const counts = Object.fromEntries(conversionIpkBands.map((band) => [band.key, 0]));

    stageRecords.forEach((record) => {
        const band = getConversionIpkBand(record.ipk);
        counts[band.key] += Number(record[stage.toKey]);
    });

    return {
        ...stage,
        fromCount,
        toCount,
        conversionPercentage: fromCount > 0 ? (toCount / fromCount) * 100 : 0,
        counts,
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
            ...conversionIpkBands.map((band) => ({ key: band.key, label: band.label, align: 'right' })),
        ],
        rows: rows.map((row) => ({
            tahapan: row.label,
            tahap_awal: formatNumber(row.fromCount, 0),
            tahap_berikutnya: formatNumber(row.toCount, 0),
            persentase: `${formatNumber(row.conversionPercentage, 1)}%`,
            ...Object.fromEntries(conversionIpkBands.map((band) => {
                const count = row.counts[band.key] || 0;
                const percentage = row.toCount > 0 ? (count / row.toCount) * 100 : 0;

                return [band.key, `${formatNumber(count, 0)} (${formatNumber(percentage, 1)}%)`];
            })),
        })),
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
            key: 'pipeline_line',
            chartType: 'stacked_horizontal_100',
            tabLabel: 'Pipeline per Prodi',
            title: 'Perusahaan dilamar, merespon, dan wawancara per program studi',
            description: 'Distribusi 100% median tiga tahapan pipeline per prodi. Merah = dilamar, kuning = merespon, hijau = wawancara.',
            option: pipelineStackedBarOption.value,
            tableData: pipelineLineTable.value,
            height: 440,
            analysis: {
                title: 'Kilas Efisiensi Rekrutmen Prodi',
                statusBadge: 'Pipeline Antarjurusan',
                badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
                lead: 'Gambar 4.7 memperlihatkan konversi dari jumlah lamaran yang diajukan hingga perolehan panggilan wawancara kerja per program studi.',
                detail: 'Selisih antara jumlah perusahaan yang dilamar dan yang merespon mencerminkan tingginya daya saing portofolio alumni UKDW, di mana sebagian besar lulusan tidak memerlukan banyak lamaran untuk segera dipanggil wawancara.',
            },
        },
        {
            key: 'scatter_applications_responses',
            chartType: 'table',
            tabLabel: 'Lamaran vs Respons',
            title: 'Hubungan Jumlah Lamaran dengan Jumlah Respons Perusahaan',
            description: 'Tabel detail per alumni menampilkan persentase respons terhadap lamaran, IPK, serta pivot per program studi dengan alumni berpersentase tertinggi.',
            tableOnly: true,
            option: null,
            tableData: scatter1Table.value,
            analysis: {
                title: 'Kilas Lamaran dan Respons',
                statusBadge: `N = ${formatNumber(s1.summary?.valid_n || 0, 0)}`,
                badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
                lead: 'Detail individu diurutkan dari persentase respons tertinggi dan menyertakan IPK untuk perbandingan langsung.',
                detail: 'Pivot program studi menampilkan rata-rata IPK, rata-rata tahap, persentase rata-rata, dan alumni dengan persentase respons tertinggi.',
            },
        },
        {
            key: 'scatter_responses_interviews',
            chartType: 'table',
            tabLabel: 'Respons vs Wawancara',
            title: 'Hubungan Jumlah Respons Perusahaan dengan Undangan Wawancara',
            description: 'Tabel detail per alumni menampilkan persentase undangan wawancara terhadap respons, IPK, serta pivot per program studi dengan alumni berpersentase tertinggi.',
            tableOnly: true,
            option: null,
            tableData: scatter2Table.value,
            analysis: {
                title: 'Kilas Respons dan Wawancara',
                statusBadge: `N = ${formatNumber(s2.summary?.valid_n || 0, 0)}`,
                badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
                lead: 'Detail individu diurutkan dari persentase undangan wawancara tertinggi dan menyertakan IPK untuk perbandingan langsung.',
                detail: 'Pivot program studi menampilkan rata-rata IPK, rata-rata tahap, persentase rata-rata, dan alumni dengan persentase wawancara tertinggi.',
            },
        },
        {
            key: 'conversion_stages',
            chartType: 'stacked_horizontal',
            tabLabel: 'Konversi Tahapan',
            title: 'Persentase Konversi Lamaran hingga Wawancara',
            description: 'Panjang batang menunjukkan jumlah tahap lanjutan. Warna menunjukkan rentang IPK: abu-abu tidak tersedia, merah 0–<1, oranye 1–<2, kuning 2–<3, dan hijau 3–4. Label segmen menunjukkan persentasenya.',
            option: conversionBarOption.value,
            tableData: conversionBarTable.value,
            height: 360,
            analysis: {
                title: 'Kilas Efisiensi Konversi Rekrutmen',
                statusBadge: 'Konversi Berbobot',
                badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
                lead: `Dari seluruh proses pencarian kerja, sebanyak ${formatNumber(st0.percentage, 1)}% lamaran berhasil memperoleh respons (${formatNumber(st0.to_count, 0)} dari ${formatNumber(st0.from_count, 0)} lamaran), dan ${formatNumber(st1.percentage, 1)}% dari respons tersebut berhasil dikonversi menjadi undangan wawancara (${formatNumber(st1.to_count, 0)} dari ${formatNumber(st1.from_count, 0)} respons).`,
                detail: `Secara menyeluruh (end-to-end), rasio konversi lamaran langsung hingga undangan wawancara berada pada angka ${formatNumber(st2.percentage, 1)}% (${formatNumber(st2.to_count, 0)} dari ${formatNumber(st2.from_count, 0)} total lamaran).`,
            },
        },
        {
            key: 'stages',
            chartType: 'horizontal_bar',
            tabLabel: 'Median Universitas',
            title: 'Median tahapan proses lamaran universitas',
            description: 'Perbandingan agregat median perusahaan yang dilamar, merespon, dan mewawancara di tingkat perguruan tinggi.',
            option: stagesBarOption.value,
            tableData: stagesBarTable.value,
            height: 320,
            analysis: {
                title: 'Kilas Rerata Universitas',
                statusBadge: 'Median Agregat',
                badgeClass: 'bg-slate-100 text-slate-700',
                lead: 'Rerata median universitas menunjukkan efisiensi proses pencarian kerja yang relatif terarah.',
                detail: 'Angka median wawancara yang mendekati angka respons membuktikan bahwa berkas lamaran alumni yang direspons memiliki probabilitas sangat tinggi untuk berlanjut ke tahap wawancara kerja.',
            },
        },
    ];
});
</script>

<template>
    <AnalyticsTabbedContainer
        :views="views"
        :rows="records"
        :selected-label="selectedLabel"
        :loading="loading"
        indicator-badge="Indikator 09"
        @select="emit('select', $event)"
        @reset="emit('reset')"
    />
</template>
