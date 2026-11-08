<script setup>
/**
 * Komponen Indikator 06: Lamaran, Respons, dan Wawancara (F6, F7, F7a).
 *
 * Mengelola lima visualisasi komprehensif alur pencarian kerja alumni:
 * 1. Line Chart: Pipeline Lamaran per Prodi (Median Dilamar, Merespon, Mewawancara - Gambar 4.7 Laporan Resmi UKDW)
 * 2. Scatter Plot: Hubungan Lamaran vs Respons Perusahaan (F6 vs F7, skala warna IPK 0-4 Viridis, 256 titik alumni mekar)
 * 3. Scatter Plot: Hubungan Respons vs Undangan Wawancara (F7 vs F7a, skala warna IPK 0-4 Viridis, 256 titik alumni mekar)
 * 4. Horizontal Bar Chart: Persentase Konversi Tahapan Lamaran hingga Wawancara (Weighted Conversion Rate)
 * 5. Bar Chart: Median Tahapan Lamaran Tingkat Universitas
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

const pipelineLineOption = computed(() => {
    const list = prodiPipelineData.value;
    const labels = list.map((item) => `${item.prodi}\n(n=${item.count})`);

    return {
        ...baseOption(records.value.length),
        legend: {
            data: ['Perusahaan Dilamar', 'Merespon', 'Mewawancara'],
            bottom: 0,
            icon: 'circle',
            textStyle: { color: '#334155', fontWeight: 600, fontSize: 12 },
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
            name: 'Median Jumlah Perusahaan',
            nameLocation: 'end',
            axisLabel: { color: '#64748B' },
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
        },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'axis',
            formatter: (params) => {
                const item = list[params[0]?.dataIndex ?? 0] || {};
                return `
                    <div class="font-sans text-xs">
                        <div class="font-bold text-slate-900 border-b border-slate-200 pb-1 mb-1.5">${escapeHtml(item.prodi)} (n=${item.count})</div>
                        <div class="text-slate-600 mb-0.5">Perusahaan dilamar: <strong>${formatNumber(item.dilamar, 1)}</strong></div>
                        <div class="text-slate-600 mb-0.5">Perusahaan merespon: <strong>${formatNumber(item.merespon, 1)}</strong></div>
                        <div class="text-slate-600">Panggilan wawancara: <strong>${formatNumber(item.wawancara, 1)}</strong></div>
                    </div>
                `;
            },
        },
        series: [
            {
                name: 'Perusahaan Dilamar',
                type: 'line',
                data: list.map((g) => g.dilamar),
                itemStyle: { color: '#334155' },
                lineStyle: { width: 3, color: '#334155' },
                symbol: 'circle',
                symbolSize: 8,
                emphasis: { scale: 1.5 },
                label: {
                    show: true,
                    position: 'top',
                    formatter: ({ value }) => formatNumber(value, 1),
                    fontSize: 11,
                    fontWeight: 'bold',
                    color: '#334155',
                },
            },
            {
                name: 'Merespon',
                type: 'line',
                data: list.map((g) => g.merespon),
                itemStyle: { color: '#0D542B' },
                lineStyle: { width: 3, color: '#0D542B' },
                symbol: 'rect',
                symbolSize: 8,
                emphasis: { scale: 1.5 },
                label: {
                    show: true,
                    position: 'top',
                    formatter: ({ value }) => formatNumber(value, 1),
                    fontSize: 11,
                    fontWeight: 'bold',
                    color: '#0D542B',
                },
            },
            {
                name: 'Mewawancara',
                type: 'line',
                data: list.map((g) => g.wawancara),
                itemStyle: { color: '#059669' },
                lineStyle: { width: 3, color: '#059669' },
                symbol: 'triangle',
                symbolSize: 8,
                emphasis: { scale: 1.5 },
                label: {
                    show: true,
                    position: 'bottom',
                    formatter: ({ value }) => formatNumber(value, 1),
                    fontSize: 11,
                    fontWeight: 'bold',
                    color: '#059669',
                },
            },
        ],
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

const scatter1Table = computed(() => {
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

const scatter2Table = computed(() => {
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

// -----------------------------------------------------------------------------
// 4. HORIZONTAL BAR CHART: Persentase Konversi Tahapan Lamaran
// -----------------------------------------------------------------------------
const conversionBarOption = computed(() => {
    const stages = conversion.value?.stages || [];
    const labels = stages.map((s) => s.label);
    const dataValues = stages.map((s) => ({
        name: s.label,
        value: s.percentage,
        from_count: s.from_count,
        to_count: s.to_count,
        from_label: s.from_label,
        to_label: s.to_label,
        formula: s.formula,
        denominator_note: s.denominator_note,
        itemStyle: {
            color: '#0D542B',
            borderRadius: [0, 6, 6, 0],
        },
    }));

    return {
        ...baseOption(),
        grid: { left: 24, right: 110, top: 24, bottom: 36, containLabel: true },
        xAxis: {
            type: 'value',
            name: 'Tingkat Konversi (%)',
            min: 0,
            max: (val) => Math.min(100, Math.ceil((val.max + 15) / 10) * 10),
            axisLabel: {
                formatter: '{value}%',
                color: '#64748B',
            },
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
        },
        yAxis: {
            type: 'category',
            inverse: true,
            data: labels,
            axisLabel: {
                color: '#1E293B',
                fontWeight: 600,
                fontSize: 12,
            },
            axisTick: { show: false },
            axisLine: { lineStyle: { color: '#CBD5E1' } },
        },
        tooltip: {
            trigger: 'item',
            formatter: (params) => {
                const pt = params.data || {};
                return `
                    <div class="font-sans text-xs">
                        <div class="font-bold text-slate-900 border-b border-slate-200 pb-1 mb-1.5">${escapeHtml(pt.name)}</div>
                        <div class="text-slate-700 mb-1"><span class="font-medium text-slate-500">Konversi:</span> <strong class="text-emerald-700 font-mono text-sm">${formatNumber(pt.value, 1)}%</strong></div>
                        <div class="text-slate-600 mb-0.5"><span class="font-medium text-slate-500">${escapeHtml(pt.to_label)}:</span> <strong>${formatNumber(pt.to_count, 0)}</strong> (Pembilang)</div>
                        <div class="text-slate-600 mb-1"><span class="font-medium text-slate-500">${escapeHtml(pt.from_label)}:</span> <strong>${formatNumber(pt.from_count, 0)}</strong> (Penyebut)</div>
                        <div class="text-[11px] text-slate-500 bg-slate-50 p-1.5 rounded border border-slate-200 mb-1 font-mono">${escapeHtml(pt.formula)}</div>
                        <div class="text-[10px] text-slate-400 italic">${escapeHtml(pt.denominator_note)}</div>
                    </div>
                `;
            },
        },
        series: [{
            name: 'Persentase Konversi',
            type: 'bar',
            barMaxWidth: 34,
            data: dataValues,
            label: {
                show: true,
                position: 'right',
                formatter: ({ data }) => `${formatNumber(data.value, 1)}% (${formatNumber(data.to_count, 0)} / ${formatNumber(data.from_count, 0)})`,
                fontWeight: 'bold',
                color: '#0D542B',
                fontSize: 12,
            },
        }],
    };
});

const conversionBarTable = computed(() => {
    const stages = conversion.value?.stages || [];

    return {
        columns: [
            { key: 'tahapan', label: 'Tahapan Proses Lamaran' },
            { key: 'tahap_awal', label: 'Jumlah Awal (Penyebut)', align: 'right' },
            { key: 'tahap_berikutnya', label: 'Jumlah Lanjutan (Pembilang)', align: 'right' },
            { key: 'persentase', label: 'Persentase Konversi', align: 'right' },
            { key: 'keterangan_penyebut', label: 'Keterangan & Ketentuan Penyebut' },
        ],
        rows: stages.map((s) => ({
            tahapan: s.label,
            tahap_awal: `${formatNumber(s.from_count, 0)} (${s.from_label})`,
            tahap_berikutnya: `${formatNumber(s.to_count, 0)} (${s.to_label})`,
            persentase: `${formatNumber(s.percentage, 1)}%`,
            keterangan_penyebut: s.denominator_note,
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

    const rho1 = s1.stats?.rho !== null && s1.stats?.rho !== undefined ? formatNumber(s1.stats.rho, 4) : '-';
    const p1 = s1.stats?.p_value !== null && s1.stats?.p_value !== undefined ? (s1.stats.p_value < 0.001 ? '< 0,001' : formatNumber(s1.stats.p_value, 4)) : '-';

    const rho2 = s2.stats?.rho !== null && s2.stats?.rho !== undefined ? formatNumber(s2.stats.rho, 4) : '-';
    const p2 = s2.stats?.p_value !== null && s2.stats?.p_value !== undefined ? (s2.stats.p_value < 0.001 ? '< 0,001' : formatNumber(s2.stats.p_value, 4)) : '-';

    return [
        {
            key: 'pipeline_line',
            chartType: 'line',
            tabLabel: 'Pipeline per Prodi',
            title: 'Perusahaan dilamar, merespon, dan wawancara per program studi',
            description: 'Perbandingan median tiga tahapan lamaran per prodi (mengadopsi Gambar 4.7 Laporan Resmi Tracer Study UKDW). Dilengkapi label nilai median pada setiap titik garis.',
            option: pipelineLineOption.value,
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
            chartType: 'scatter',
            tabLabel: 'Lamaran vs Respons',
            title: 'Hubungan Jumlah Lamaran dengan Jumlah Respons Perusahaan',
            description: 'Scatter plot sebaran titik alumni memperlihatkan relasi jumlah lamaran kerja (F6) dan respons perusahaan (F7). Seluruh titik alumni dimekarkan secara visual dengan skala warna IPK (0–4) dan garis tren linier OLS.',
            option: scatter1Option.value,
            tableData: scatter1Table.value,
            height: 460,
            analysis: {
                title: 'Kilas Hubungan Lamaran & Respons',
                statusBadge: `ρ = ${rho1} (p = ${p1})`,
                badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
                lead: `Analisis korelasi Spearman mencatat koefisien ρ = ${rho1} (signifikansi p = ${p1}, N = ${formatNumber(s1.summary?.valid_n || 0, 0)}), menunjukkan korelasi positif yang sangat kuat antara intensitas pelamaran kerja dengan perolehan respons dari perusahaan.`,
                detail: `Gradasi warna IPK (palet Viridis) memperlihatkan bahwa lulusan dengan IPK tinggi (kuning-hijau cerah) menunjukkan efektivitas penerimaan respons yang solid pada setiap rentang frekuensi lamaran kerja yang diajukan.`,
            },
        },
        {
            key: 'scatter_responses_interviews',
            chartType: 'scatter',
            tabLabel: 'Respons vs Wawancara',
            title: 'Hubungan Jumlah Respons Perusahaan dengan Undangan Wawancara',
            description: 'Scatter plot sebaran titik alumni memperlihatkan relasi jumlah perusahaan yang merespons (F7) dengan undangan wawancara (F7a). Seluruh titik alumni dimekarkan secara visual dengan skala warna IPK (0–4) dan garis tren linier OLS.',
            option: scatter2Option.value,
            tableData: scatter2Table.value,
            height: 460,
            analysis: {
                title: 'Kilas Hubungan Respons & Wawancara',
                statusBadge: `ρ = ${rho2} (p = ${p2})`,
                badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
                lead: `Korelasi Spearman ρ = ${rho2} (signifikansi p = ${p2}, N = ${formatNumber(s2.summary?.valid_n || 0, 0)}) membuktikan bahwa perolehan respons perusahaan berbanding lurus dan memiliki probabilitas sangat tinggi untuk berlanjut ke tahap wawancara kerja.`,
                detail: `Garis tren linier yang menanjak konsisten mengindikasikan bahwa berkas lamaran yang direspons positif oleh pemberi kerja hampir selalu berlanjut menjadi jadwal wawancara bagi alumni.`,
            },
        },
        {
            key: 'conversion_stages',
            chartType: 'horizontal_bar',
            tabLabel: 'Konversi Tahapan',
            title: 'Persentase Konversi Lamaran hingga Wawancara',
            description: 'Tingkat konversi berbobot (weighted conversion) dari lamaran ke respons, respons ke wawancara, dan lamaran ke wawancara dengan penyebut transparan.',
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
