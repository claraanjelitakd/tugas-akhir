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

/**
 * Mendispersikan koordinat titik diskrit yang bertumpuk pada koordinat bulat yang sama
 * menggunakan algoritma Sunflower / Fermat spiral (radius r <= 0.32).
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
            value: [
                Number((rawX + jx).toFixed(3)),
                Number((rawY + jy).toFixed(3)),
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

// -----------------------------------------------------------------------------
// 1. STACKED BAR 1: Lamaran vs Respons per Prodi (100% Horizontal Stacked)
// Mengamati proporsi lamaran yang berhasil direspons perusahaan (Basis: 100% Lamaran).
// - Merah (#B91C1C): Mengirim Lamaran (belum/tidak direspons)
// - Kuning (#EAB308): Direspons (F7)
// -----------------------------------------------------------------------------
const pipelineLamaranOption = computed(() => {
    const list = prodiPipelineData.value;
    const categories = ['Mengirim Lamaran', 'Direspons'];
    const colors = ['#B91C1C', '#EAB308'];
    const labelColors = ['#FFFFFF', '#1E293B'];

    const fmtCount = (val) => {
        if (val === null || val === undefined) return '0';
        return val % 1 === 0 ? formatNumber(val, 0) : formatNumber(val, 1);
    };

    return {
        ...baseOption(records.value.length),
        legend: {
            data: categories,
            top: 8,
            left: 'center',
            icon: 'rect',
            itemWidth: 14,
            itemHeight: 12,
            itemGap: 28,
            textStyle: { color: '#334155', fontWeight: 600, fontSize: 12 },
        },
        grid: { left: 16, right: 48, top: 48, bottom: 28, containLabel: true },
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
            data: list.map((item) => `${item.prodi} (n=${item.count})`),
            axisLabel: { color: '#334155', width: 190, overflow: 'truncate', fontWeight: 500 },
        },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            formatter: (params) => {
                const item = list[params[0]?.dataIndex ?? 0] || {};
                const dilamar = Number(item.dilamar || 0);
                const merespon = Number(item.merespon || 0);
                const pctResp = dilamar > 0 ? (merespon / dilamar) * 100 : 0;
                const pctBelum = Math.max(0, 100 - pctResp);

                return `
                    <div class="font-sans text-xs">
                        <div class="font-bold text-slate-900 border-b border-slate-200 pb-1 mb-1.5">${escapeHtml(item.prodi)} (n=${item.count} alumni)</div>
                        <div class="text-slate-700 mb-1">• <span class="font-semibold text-rose-700">Mengirim Lamaran:</span> <strong>${Math.round(pctBelum)}%</strong> <span class="text-slate-500">(${fmtCount(Math.max(0, dilamar - merespon))} perus. belum direspons)</span></div>
                        <div class="text-slate-700 mb-1">• <span class="font-semibold text-amber-600">Direspons (F7):</span> <strong>${Math.round(pctResp)}%</strong> <span class="text-slate-500">(median ${fmtCount(merespon)} dari ${fmtCount(dilamar)} perus.)</span></div>
                        <div class="text-[11px] text-slate-400 border-t border-slate-100 pt-1 mt-1">Klik batang untuk memfilter alumni prodi ini.</div>
                    </div>
                `;
            },
        },
        series: [
            {
                name: 'Mengirim Lamaran',
                type: 'bar',
                stack: 'lamaran',
                barMaxWidth: 26,
                itemStyle: {
                    color: colors[0],
                    borderRadius: [4, 0, 0, 4],
                },
                label: {
                    show: true,
                    position: 'inside',
                    formatter: ({ data }) => {
                        const val = data?.value;
                        const count = data?.count;
                        if (!val || val < 10) return '';
                        return `${Math.round(val)}% (${fmtCount(count)} perus.)`;
                    },
                    color: labelColors[0],
                    fontWeight: 700,
                    fontSize: 11,
                },
                data: list.map((item) => {
                    const dilamar = Number(item.dilamar || 0);
                    const merespon = Number(item.merespon || 0);
                    const pctResp = dilamar > 0 ? (merespon / dilamar) * 100 : 0;
                    const pctBelum = Math.max(0, 100 - pctResp);

                    return {
                        name: item.prodi,
                        prodi: item.prodi,
                        category: 'Mengirim Lamaran',
                        value: Number(pctBelum.toFixed(1)),
                        count: Math.max(0, dilamar - merespon),
                        dilamar,
                        merespon,
                    };
                }),
            },
            {
                name: 'Direspons',
                type: 'bar',
                stack: 'lamaran',
                barMaxWidth: 26,
                itemStyle: {
                    color: colors[1],
                    borderRadius: [0, 4, 4, 0],
                },
                label: {
                    show: true,
                    position: 'inside',
                    formatter: ({ data }) => {
                        const val = data?.value;
                        const count = data?.count;
                        if (!val || val < 10) return '';
                        return `${Math.round(val)}% (${fmtCount(count)} perus.)`;
                    },
                    color: labelColors[1],
                    fontWeight: 700,
                    fontSize: 11,
                },
                data: list.map((item) => {
                    const dilamar = Number(item.dilamar || 0);
                    const merespon = Number(item.merespon || 0);
                    const pctResp = dilamar > 0 ? (merespon / dilamar) * 100 : 0;

                    return {
                        name: item.prodi,
                        prodi: item.prodi,
                        category: 'Direspons',
                        value: Number(pctResp.toFixed(1)),
                        count: merespon,
                        dilamar,
                        merespon,
                    };
                }),
            },
        ],
    };
});

// -----------------------------------------------------------------------------
// 2. STACKED BAR 2: Respons vs Wawancara per Prodi (100% Horizontal Stacked)
// Mengamati proporsi respons yang berlanjut ke tahap wawancara (Basis: 100% Respons).
// - Kuning (#EAB308): Direspons (belum/tidak diwawancara)
// - Hijau (#0D542B): Diwawancara (F7a)
// -----------------------------------------------------------------------------
const pipelineWawancaraOption = computed(() => {
    const list = prodiPipelineData.value;
    const categories = ['Direspons', 'Diwawancara'];
    const colors = ['#EAB308', '#0D542B'];
    const labelColors = ['#1E293B', '#FFFFFF'];

    const fmtCount = (val) => {
        if (val === null || val === undefined) return '0';
        return val % 1 === 0 ? formatNumber(val, 0) : formatNumber(val, 1);
    };

    return {
        ...baseOption(records.value.length),
        legend: {
            data: categories,
            top: 8,
            left: 'center',
            icon: 'rect',
            itemWidth: 14,
            itemHeight: 12,
            itemGap: 28,
            textStyle: { color: '#334155', fontWeight: 600, fontSize: 12 },
        },
        grid: { left: 16, right: 48, top: 48, bottom: 28, containLabel: true },
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
            data: list.map((item) => `${item.prodi} (n=${item.count})`),
            axisLabel: { color: '#334155', width: 190, overflow: 'truncate', fontWeight: 500 },
        },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            formatter: (params) => {
                const item = list[params[0]?.dataIndex ?? 0] || {};
                const merespon = Number(item.merespon || 0);
                const wawancara = Number(item.wawancara || 0);
                const pctIntv = merespon > 0 ? (wawancara / merespon) * 100 : 0;
                const pctBelum = Math.max(0, 100 - pctIntv);

                return `
                    <div class="font-sans text-xs">
                        <div class="font-bold text-slate-900 border-b border-slate-200 pb-1 mb-1.5">${escapeHtml(item.prodi)} (n=${item.count} alumni)</div>
                        <div class="text-slate-700 mb-1">• <span class="font-semibold text-amber-600">Direspons:</span> <strong>${Math.round(pctBelum)}%</strong> <span class="text-slate-500">(${fmtCount(Math.max(0, merespon - wawancara))} perus. tanpa wawancara)</span></div>
                        <div class="text-slate-700 mb-1">• <span class="font-semibold text-emerald-800">Diwawancara (F7a):</span> <strong>${Math.round(pctIntv)}%</strong> <span class="text-slate-500">(median ${fmtCount(wawancara)} dari ${fmtCount(merespon)} perus.)</span></div>
                        <div class="text-[11px] text-slate-400 border-t border-slate-100 pt-1 mt-1">Klik batang untuk memfilter alumni prodi ini.</div>
                    </div>
                `;
            },
        },
        series: [
            {
                name: 'Direspons',
                type: 'bar',
                stack: 'wawancara',
                barMaxWidth: 26,
                itemStyle: {
                    color: colors[0],
                    borderRadius: [4, 0, 0, 4],
                },
                label: {
                    show: true,
                    position: 'inside',
                    formatter: ({ data }) => {
                        const val = data?.value;
                        const count = data?.count;
                        if (!val || val < 10) return '';
                        return `${Math.round(val)}% (${fmtCount(count)} perus.)`;
                    },
                    color: labelColors[0],
                    fontWeight: 700,
                    fontSize: 11,
                },
                data: list.map((item) => {
                    const merespon = Number(item.merespon || 0);
                    const wawancara = Number(item.wawancara || 0);
                    const pctIntv = merespon > 0 ? (wawancara / merespon) * 100 : 0;
                    const pctBelum = Math.max(0, 100 - pctIntv);

                    return {
                        name: item.prodi,
                        prodi: item.prodi,
                        category: 'Direspons',
                        value: Number(pctBelum.toFixed(1)),
                        count: Math.max(0, merespon - wawancara),
                        merespon,
                        wawancara,
                    };
                }),
            },
            {
                name: 'Diwawancara',
                type: 'bar',
                stack: 'wawancara',
                barMaxWidth: 26,
                itemStyle: {
                    color: colors[1],
                    borderRadius: [0, 4, 4, 0],
                },
                label: {
                    show: true,
                    position: 'inside',
                    formatter: ({ data }) => {
                        const val = data?.value;
                        const count = data?.count;
                        if (!val || val < 10) return '';
                        return `${Math.round(val)}% (${fmtCount(count)} perus.)`;
                    },
                    color: labelColors[1],
                    fontWeight: 700,
                    fontSize: 11,
                },
                data: list.map((item) => {
                    const merespon = Number(item.merespon || 0);
                    const wawancara = Number(item.wawancara || 0);
                    const pctIntv = merespon > 0 ? (wawancara / merespon) * 100 : 0;

                    return {
                        name: item.prodi,
                        prodi: item.prodi,
                        category: 'Diwawancara',
                        value: Number(pctIntv.toFixed(1)),
                        count: wawancara,
                        merespon,
                        wawancara,
                    };
                }),
            },
        ],
    };
});

const pipelineLineTable = computed(() => ({
    columns: [
        { key: 'prodi', label: 'Program Studi' },
        { key: 'count', label: 'Responden (n)', align: 'right' },
        { key: 'dilamar', label: 'Median Dilamar (F6)', align: 'right' },
        { key: 'merespon', label: 'Median Merespon (F7)', align: 'right' },
        { key: 'wawancara', label: 'Median Wawancara (F7a)', align: 'right' },
        { key: 'rasio_respons', label: 'Tingkat Respons (%)', align: 'right' },
        { key: 'rasio_wawancara', label: 'Tingkat Wawancara (%)', align: 'right' },
    ],
    rows: prodiPipelineData.value.map((item) => {
        const fmt = (val) => {
            if (val === null || val === undefined) return '-';
            return val % 1 === 0 ? formatNumber(val, 0) : formatNumber(val, 1);
        };
        const dilamar = Number(item.dilamar || 0);
        const merespon = Number(item.merespon || 0);
        const wawancara = Number(item.wawancara || 0);
        const rasioResp = dilamar > 0 ? `${formatNumber((merespon / dilamar) * 100, 1)}%` : '-';
        const rasioIntv = merespon > 0 ? `${formatNumber((wawancara / merespon) * 100, 1)}%` : '-';

        return {
            prodi: item.prodi,
            count: formatNumber(item.count, 0),
            dilamar: fmt(item.dilamar),
            merespon: fmt(item.merespon),
            wawancara: fmt(item.wawancara),
            rasio_respons: rasioResp,
            rasio_wawancara: rasioIntv,
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
        grid: { left: 45, right: 40, top: 40, bottom: 50, containLabel: true },
        legend: {
            show: true,
            top: 6,
            right: 40,
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
                const f6Val = d.rawX !== undefined ? d.rawX : d.f6;
                const f7Val = d.rawY !== undefined ? d.rawY : d.f7;
                return `
                    <div class="font-sans text-xs">
                        <div class="font-bold text-slate-900 border-b border-slate-200 pb-1 mb-1.5">${escapeHtml(d.name || 'Alumni')} (${escapeHtml(d.nim || '-')})</div>
                        <div class="text-slate-600 mb-0.5"><span class="text-slate-500">Program Studi:</span> <strong>${escapeHtml(d.prodi || '-')}</strong></div>
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
        grid: { left: 45, right: 40, top: 40, bottom: 50, containLabel: true },
        legend: {
            show: true,
            top: 6,
            right: 40,
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
                const f7Val = d.rawX !== undefined ? d.rawX : d.f7;
                const f7aVal = d.rawY !== undefined ? d.rawY : d.f7a;
                return `
                    <div class="font-sans text-xs">
                        <div class="font-bold text-slate-900 border-b border-slate-200 pb-1 mb-1.5">${escapeHtml(d.name || 'Alumni')} (${escapeHtml(d.nim || '-')})</div>
                        <div class="text-slate-600 mb-0.5"><span class="text-slate-500">Program Studi:</span> <strong>${escapeHtml(d.prodi || '-')}</strong></div>
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

// Tabel audit individu untuk lamaran terhadap undangan wawancara / diterima dari backend.
const scatter3Table = computed(() => props.chart?.relation_tables?.applications_interviews || {
    columns: [],
    rows: [],
    summary: { columns: [], rows: [] },
});

// -----------------------------------------------------------------------------
// 4. BAR CHART: Persentase Konversi Tahapan Rekrutmen (2-Bar Stacked)
// -----------------------------------------------------------------------------
const conversionStagesData = computed(() => {
    const st = props.chart?.conversion_stages?.stages;
    if (Array.isArray(st) && st.length > 0) {
        return st;
    }

    return [
        { key: 'app_to_resp', label: 'Lamaran → Respons', from_count: 0, to_count: 0, percentage: 0 },
        { key: 'resp_to_intv', label: 'Respons → Wawancara', from_count: 0, to_count: 0, percentage: 0 },
        { key: 'app_to_intv', label: 'Lamaran → Wawancara', from_count: 0, to_count: 0, percentage: 0 },
    ];
});

// Opsi 2-bar stacked horizontal untuk konversi agregat universitas.
// Menggunakan palet standar Masa Tunggu / UMP: Abu-abu (#CBD5E1) paling kiri, Hijau UKDW (#0D542B) di kanan.
// Bar 1: Melamar -> Direspons (100% basis lamaran)
// Bar 2: Respons -> Diwawancara (100% basis respons)
const conversionBarOption = computed(() => {
    const stages = conversionStagesData.value;
    const st1 = stages.find((s) => s.key === 'app_to_resp') || { from_count: 0, to_count: 0, percentage: 0 };
    const st2 = stages.find((s) => s.key === 'resp_to_intv') || { from_count: 0, to_count: 0, percentage: 0 };

    const stageCategories = [
        '1. Melamar → Direspons',
        '2. Respons → Diwawancara',
    ];

    const pct1 = Number(st1.percentage || 0);
    const pct1Belum = Math.max(0, 100 - pct1);
    const pct2 = Number(st2.percentage || 0);
    const pct2Belum = Math.max(0, 100 - pct2);

    return {
        ...baseOption(alumniList.value.length),
        legend: {
            data: ['Belum Tercapai / Belum Lolos', 'Berhasil Lolos (Direspons / Diwawancara)'],
            top: 8,
            left: 'center',
            icon: 'rect',
            itemWidth: 14,
            itemHeight: 12,
            itemGap: 18,
            textStyle: { color: '#334155', fontWeight: 600, fontSize: 12 },
        },
        grid: {
            left: 24,
            right: 48,
            top: 48,
            bottom: 36,
            containLabel: true,
        },
        xAxis: {
            type: 'value',
            min: 0,
            max: 100,
            name: 'Persentase Konversi (%)',
            nameLocation: 'middle',
            nameGap: 24,
            nameTextStyle: { color: '#64748B', fontSize: 11, fontWeight: 500 },
            axisLabel: {
                formatter: '{value}%',
                color: '#64748B',
                fontSize: 11,
            },
            splitLine: {
                lineStyle: { color: '#E2E8F0', type: 'dashed' },
            },
        },
        yAxis: {
            type: 'category',
            inverse: true,
            data: stageCategories,
            axisLabel: {
                color: '#334155',
                fontWeight: 600,
                fontSize: 12,
            },
            axisTick: { show: false },
            axisLine: { lineStyle: { color: '#CBD5E1' } },
        },
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            formatter: (params) => {
                const idx = params?.[0]?.dataIndex ?? 0;
                const isStage1 = idx === 0;
                const st = isStage1 ? st1 : st2;
                const fromCount = Number(st.from_count || 0);
                const toCount = Number(st.to_count || 0);
                const belumCount = Math.max(0, fromCount - toCount);
                const pct = Number(st.percentage || 0);
                const pctBelum = Math.max(0, 100 - pct);

                return `
                    <div class="font-sans text-xs">
                        <div class="font-bold text-slate-900 border-b border-slate-200 pb-1 mb-1.5">${escapeHtml(stageCategories[idx])}</div>
                        <div class="text-slate-700 mb-1">Tingkat Keberhasilan: <strong class="text-emerald-700 font-mono">${formatNumber(pct, 1)}%</strong></div>
                        <div class="text-slate-600 mb-0.5">• Berhasil Lolos (Hijau): <strong>${formatNumber(toCount, 0)} (${formatNumber(pct, 1)}%)</strong></div>
                        <div class="text-slate-500 mb-0.5">• Belum Tercapai (Abu-abu): <strong>${formatNumber(belumCount, 0)} (${formatNumber(pctBelum, 1)}%)</strong></div>
                        <div class="text-[11px] text-slate-500 border-t border-slate-100 pt-1 mt-1">Total basis awal: <strong>${formatNumber(fromCount, 0)}</strong> (${isStage1 ? 'total lamaran dikirim' : 'total respons diterima'})</div>
                    </div>
                `;
            },
        },
        series: [
            {
                name: 'Belum Tercapai / Belum Lolos',
                type: 'bar',
                stack: 'conversion_stack',
                barMaxWidth: 28,
                itemStyle: {
                    color: '#CBD5E1',
                    borderRadius: [4, 0, 0, 4],
                },
                label: {
                    show: true,
                    position: 'inside',
                    formatter: ({ value }) => value >= 10 ? `${formatNumber(value, 1)}%` : '',
                    color: '#1E293B',
                    fontWeight: 600,
                    fontSize: 11,
                },
                data: [
                    { value: Number(pct1Belum.toFixed(1)), count: Math.max(0, st1.from_count - st1.to_count) },
                    { value: Number(pct2Belum.toFixed(1)), count: Math.max(0, st2.from_count - st2.to_count) },
                ],
            },
            {
                name: 'Berhasil Lolos (Direspons / Diwawancara)',
                type: 'bar',
                stack: 'conversion_stack',
                barMaxWidth: 28,
                itemStyle: {
                    color: '#0D542B',
                    borderRadius: [0, 4, 4, 0],
                },
                label: {
                    show: true,
                    position: 'inside',
                    formatter: ({ value }) => value >= 10 ? `${formatNumber(value, 1)}%` : '',
                    color: '#FFFFFF',
                    fontWeight: 600,
                    fontSize: 11,
                },
                data: [
                    { value: Number(pct1.toFixed(1)), count: st1.to_count },
                    { value: Number(pct2.toFixed(1)), count: st2.to_count },
                ],
            },
        ],
    };
});

const conversionBarTable = computed(() => {
    const stages = conversionStagesData.value;
    const st1 = stages.find((s) => s.key === 'app_to_resp') || { from_count: 0, to_count: 0, percentage: 0 };
    const st2 = stages.find((s) => s.key === 'resp_to_intv') || { from_count: 0, to_count: 0, percentage: 0 };

    const columns = [
        { key: 'tahapan', label: 'Tahapan Proses Rekrutmen' },
        { key: 'tahap_awal', label: 'Basis Awal (Penyebut)', align: 'right' },
        { key: 'belum_lolos', label: 'Belum Lolos (Abu-abu)', align: 'right' },
        { key: 'berhasil', label: 'Berhasil Lolos (Hijau)', align: 'right' },
        { key: 'persentase_total', label: 'Tingkat Keberhasilan (%)', align: 'right' },
        { key: 'keterangan', label: 'Interpretasi Hasil' },
    ];

    const tableRows = [
        {
            tahapan: '1. Melamar → Direspons',
            tahap_awal: formatNumber(st1.from_count, 0),
            belum_lolos: `${formatNumber(Math.max(0, st1.from_count - st1.to_count), 0)} (${formatNumber(100 - Number(st1.percentage || 0), 1)}%)`,
            berhasil: `${formatNumber(st1.to_count, 0)} (${formatNumber(st1.percentage, 1)}%)`,
            persentase_total: `${formatNumber(st1.percentage, 1)}%`,
            keterangan: 'Rasio berkas lamaran yang berhasil memperoleh tanggapan balik dari perusahaan.',
        },
        {
            tahapan: '2. Respons → Diwawancara',
            tahap_awal: formatNumber(st2.from_count, 0),
            belum_lolos: `${formatNumber(Math.max(0, st2.from_count - st2.to_count), 0)} (${formatNumber(100 - Number(st2.percentage || 0), 1)}%)`,
            berhasil: `${formatNumber(st2.to_count, 0)} (${formatNumber(st2.percentage, 1)}%)`,
            persentase_total: `${formatNumber(st2.percentage, 1)}%`,
            keterangan: 'Rasio perusahaan yang merespons dan berlanjut ke pemanggilan wawancara kerja.',
        },
    ];

    return {
        columns,
        rows: tableRows,
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
            key: 'pipeline_lamaran',
            chartType: 'grouped_horizontal_bar',
            tabLabel: 'Lamaran vs Respons',
            title: 'Pipeline Lamaran vs Respons per Prodi',
            description: 'Proporsi perbandingan lamaran terkirim (Merah) dan respons perusahaan (Kuning) per program studi.',
            option: pipelineLamaranOption.value,
            tableData: pipelineLineTable.value,
            height: 480,
            analysis: {
                title: 'Kilas Lamaran ke Respons',
                statusBadge: 'Tahap 1: Lamaran',
                badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
                lead: 'Komparasi proporsi lamaran yang berhasil memperoleh respons perusahaan di setiap program studi.',
                detail: 'Diagram batang bertumpuk 100% horizontal memperlihatkan persentase dan median jumlah perusahaan yang direspons.',
            },
        },
        {
            key: 'pipeline_wawancara',
            chartType: 'grouped_horizontal_bar',
            tabLabel: 'Respons vs Wawancara',
            title: 'Pipeline Respons vs Wawancara per Prodi',
            description: 'Proporsi perbandingan respons perusahaan (Kuning) dan panggilan wawancara (Hijau) per program studi.',
            option: pipelineWawancaraOption.value,
            tableData: pipelineLineTable.value,
            height: 480,
            analysis: {
                title: 'Kilas Respons ke Wawancara',
                statusBadge: 'Tahap 2: Wawancara',
                badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
                lead: 'Komparasi proporsi respons perusahaan yang berhasil berlanjut ke panggilan wawancara kerja di setiap program studi.',
                detail: 'Diagram batang bertumpuk 100% horizontal memperlihatkan efisiensi seleksi lanjutan setelah lamaran direspons.',
            },
        },
        {
            key: 'conversion_stages',
            chartType: 'horizontal_bar',
            tabLabel: 'Konversi Tahapan',
            title: 'Konversi Tahapan Rekrutmen',
            description: 'Rasio konversi tahapan proses rekrutmen dari lamaran hingga wawancara.',
            option: conversionBarOption.value,
            tableData: conversionBarTable.value,
            height: 320,
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
        {
            key: 'scatter_applications_responses',
            chartType: 'table',
            tabLabel: 'Pivot Lamaran vs Respons',
            title: 'Pivot Lamaran vs Respons',
            description: 'Tabel pivot perbandingan lamaran (F6) dan respons (F7) per program studi.',
            tableOnly: true,
            option: null,
            tableData: scatter1Table.value,
            analysis: {
                title: 'Kilas Lamaran dan Respons',
                statusBadge: `N = ${formatNumber(s1.summary?.valid_n || 0, 0)}`,
                badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
                lead: 'Perbandingan agregat lamaran terkirim dan respons perusahaan yang diterima alumni.',
                detail: 'Tabel pivot menyajikan distribusi rata-rata per program studi.',
            },
        },
        {
            key: 'scatter_responses_interviews',
            chartType: 'table',
            tabLabel: 'Pivot Respons vs Wawancara',
            title: 'Pivot Respons vs Wawancara',
            description: 'Tabel pivot perbandingan respons (F7) dan wawancara (F7a) per program studi.',
            tableOnly: true,
            option: null,
            tableData: scatter2Table.value,
            analysis: {
                title: 'Kilas Respons dan Wawancara',
                statusBadge: `N = ${formatNumber(s2.summary?.valid_n || 0, 0)}`,
                badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
                lead: 'Perbandingan agregat respons perusahaan terhadap undangan wawancara kerja yang diperoleh alumni.',
                detail: 'Tabel pivot menyajikan distribusi rata-rata per program studi.',
            },
        },
        {
            key: 'scatter_applications_interviews',
            chartType: 'table',
            tabLabel: 'Pivot Lamaran vs Diterima',
            title: 'Pivot Lamaran vs Diterima',
            description: 'Tabel pivot perbandingan lamaran (F6) dan undangan wawancara / diterima (F7a) per program studi.',
            tableOnly: true,
            option: null,
            tableData: scatter3Table.value,
            analysis: {
                title: 'Kilas Lamaran dan Diterima',
                statusBadge: `N = ${formatNumber(props.chart?.summary?.answered_count || 0, 0)}`,
                badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
                lead: 'Rasio end-to-end dari seluruh berkas lamaran yang dikirim hingga panggilan wawancara kerja/diterima.',
                detail: 'Tabel pivot menyajikan distribusi rata-rata per program studi.',
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
