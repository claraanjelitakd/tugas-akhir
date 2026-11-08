<script setup>
/**
 * Komponen Indikator 12: Hubungan Bidang Studi dengan Pekerjaan (Horizontal Match - F14).
 *
 * Mengelola visualisasi keselarasan kurikulum bidang studi dengan pekerjaan alumni:
 * 1. Stacked Bar Antarprodi: Komposisi kategori keselarasan dengan pemisah visual yang ringan.
 * 2. Line Chart: Tren Persentase Horizontal Match (Sangat Erat + Erat) per Program Studi
 * 3. Donut Chart: Distribusi Jawaban Kuesioner Universitas
 */
import { computed } from 'vue';
import AnalyticsTabbedContainer from '../AnalyticsTabbedContainer.vue';
import {
    baseOption,
    escapeHtml,
    formatNumber,
    formatPercentage,
    recordCategory,
    seriesLabel,
    seriesMetric,
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

// Ekstraksi data seri dan catatan responden.
const series = computed(() => props.chart?.series || []);
const records = computed(() => props.chart?.records || []);
const validResponses = computed(() => props.chart?.summary?.answered_count || records.value.length);

// -----------------------------------------------------------------------------
// STANDAR SKALA & WARNA KESELARASAN BIDANG STUDI (Mengadopsi Indikator 02)
// Skala Likert 1-5 resmi instrumen Tracer Study F14:
// 1 = Tidak Sama Sekali (Merah), 2 = Kurang Erat (Oranye), 3 = Cukup Erat (Kuning),
// 4 = Erat (Hijau Muda), 5 = Sangat Erat (Hijau Tua UKDW).
// -----------------------------------------------------------------------------
const getScaleLevel = (label) => {
    const l = String(label || '').toLowerCase().trim();
    if (l.includes('sangat erat')) return 5;
    if (l === 'erat' || (l.includes('erat') && !l.includes('kurang') && !l.includes('cukup') && !l.includes('tidak'))) return 4;
    if (l.includes('cukup')) return 3;
    if (l.includes('kurang')) return 2;
    if (l.includes('tidak')) return 1;
    return 3;
};

const scaleDescriptions = {
    1: 'Tidak Sama Sekali',
    2: 'Kurang Erat',
    3: 'Cukup Erat',
    4: 'Erat',
    5: 'Sangat Erat',
};

const scaleColors = {
    1: '#B91C1C', // Merah - Tidak Sama Sekali
    2: '#EA580C', // Oranye - Kurang Erat
    3: '#FACC15', // Kuning Emas - Cukup Erat
    4: '#5EBA7D', // Hijau Muda - Erat
    5: '#1E6B37', // Hijau Tua - Sangat Erat
};

const getAlignmentColor = (label) => {
    const lvl = getScaleLevel(label);
    return scaleColors[lvl] || '#5EBA7D';
};

// -----------------------------------------------------------------------------
// 1. STACKED BAR ANTARPRODI (Desain & Palet Warna Indikator 02 + Vertical Scale Bar)
// -----------------------------------------------------------------------------
const prodis = computed(() => [...new Set(records.value.map((r) => r.nama_prodi || 'Tanpa prodi'))]);
const orderedLevels = [1, 2, 3, 4, 5];

const stackedOption = computed(() => {
    const listProdis = prodis.value;
    const prodiTotals = Object.fromEntries(
        listProdis.map((p) => [p, records.value.filter((r) => (r.nama_prodi || 'Tanpa prodi') === p).length]),
    );

    const seriesList = orderedLevels.map((lvl) => {
        const catName = scaleDescriptions[lvl];
        const catData = listProdis.map((prodi) => {
            const count = records.value.filter((r) => {
                const p = r.nama_prodi || 'Tanpa prodi';
                return p === prodi && getScaleLevel(recordCategory(r)) === lvl;
            }).length;
            const total = Math.max(prodiTotals[prodi], 1);
            const pct = Number(((count / total) * 100).toFixed(1));
            return {
                value: pct,
                count,
                total,
                prodi,
                category: catName,
                scoreLevel: lvl,
            };
        });

        const selected = !props.selectedLabel || props.selectedLabel === catName || getScaleLevel(props.selectedLabel) === lvl;

        return {
            name: `${lvl} = ${catName}`,
            type: 'bar',
            stack: 'total',
            barMaxWidth: 26,
            label: {
                show: true,
                position: 'inside',
                formatter: (param) => {
                    const val = Number(param.value ?? 0);
                    if (val < 10) return '';
                    return `${Math.round(val)}%`;
                },
                color: lvl === 3 ? '#1E293B' : '#FFFFFF',
                fontSize: 11,
                fontWeight: 700,
            },
            itemStyle: {
                color: scaleColors[lvl],
                opacity: selected ? 1 : 0.22,
                borderColor: '#FFFFFF',
                borderWidth: 1.5,
            },
            data: catData,
        };
    });

    const labels = listProdis.map((p) => `${p} (n=${prodiTotals[p]})`);

    return {
        ...baseOption(records.value.length),
        legend: {
            show: false, // Digantikan oleh vertical scale bar di sisi kanan
        },
        grid: { left: 24, right: 210, top: 40, bottom: 48, containLabel: true },
        xAxis: {
            type: 'value',
            min: 0,
            max: 100,
            name: 'Proporsi jawaban alumni',
            nameLocation: 'middle',
            nameGap: 30,
            axisLabel: { formatter: '{value}%', color: '#64748B', fontSize: 11 },
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
        },
        yAxis: {
            type: 'category',
            inverse: true,
            data: labels,
            axisLabel: {
                color: '#1E293B',
                fontWeight: 500,
                fontSize: 12,
                width: 170,
                overflow: 'break',
            },
            axisTick: { show: false },
            axisLine: { lineStyle: { color: '#CBD5E1' } },
        },
        graphic: [
            // Vertical Scale Bar Legenda Skala Penilaian di sisi kanan (Urutan: 5 Hijau Tua paling atas s/d 1 Merah paling bawah)
            {
                type: 'group',
                right: 18,
                top: 'middle',
                children: [
                    // Teks vertikal "Tingkat Keselarasan"
                    {
                        type: 'text',
                        left: 0,
                        top: 110,
                        rotation: -Math.PI / 2,
                        style: {
                            text: 'Skala Penilaian',
                            fill: '#0F172A',
                            font: 'bold 11px Inter, sans-serif',
                            textAlign: 'center',
                        },
                    },

                    // Segmen 5: Hijau Tua - Sangat Erat (Skala 5)
                    {
                        type: 'text',
                        left: 18,
                        top: 15,
                        style: { text: '5', fill: '#0F172A', font: '600 11px Inter, sans-serif', textAlign: 'right' },
                    },
                    {
                        type: 'rect',
                        left: 28,
                        top: 0,
                        shape: { width: 22, height: 44 },
                        style: { fill: '#1E6B37', stroke: '#1E293B', lineWidth: 1 },
                    },
                    {
                        type: 'text',
                        left: 58,
                        top: 15,
                        style: { text: 'Sangat Erat', fill: '#1E293B', font: '500 11px Inter, sans-serif' },
                    },

                    // Segmen 4: Hijau Muda - Erat (Skala 4)
                    {
                        type: 'text',
                        left: 18,
                        top: 59,
                        style: { text: '4', fill: '#0F172A', font: '600 11px Inter, sans-serif', textAlign: 'right' },
                    },
                    {
                        type: 'rect',
                        left: 28,
                        top: 44,
                        shape: { width: 22, height: 44 },
                        style: { fill: '#5EBA7D', stroke: '#1E293B', lineWidth: 1 },
                    },
                    {
                        type: 'text',
                        left: 58,
                        top: 59,
                        style: { text: 'Erat', fill: '#1E293B', font: '500 11px Inter, sans-serif' },
                    },

                    // Segmen 3: Kuning - Cukup Erat (Skala 3)
                    {
                        type: 'text',
                        left: 18,
                        top: 103,
                        style: { text: '3', fill: '#0F172A', font: '600 11px Inter, sans-serif', textAlign: 'right' },
                    },
                    {
                        type: 'rect',
                        left: 28,
                        top: 88,
                        shape: { width: 22, height: 44 },
                        style: { fill: '#FACC15', stroke: '#1E293B', lineWidth: 1 },
                    },
                    {
                        type: 'text',
                        left: 58,
                        top: 103,
                        style: { text: 'Cukup Erat', fill: '#1E293B', font: '500 11px Inter, sans-serif' },
                    },

                    // Segmen 2: Oranye - Kurang Erat (Skala 2)
                    {
                        type: 'text',
                        left: 18,
                        top: 147,
                        style: { text: '2', fill: '#0F172A', font: '600 11px Inter, sans-serif', textAlign: 'right' },
                    },
                    {
                        type: 'rect',
                        left: 28,
                        top: 132,
                        shape: { width: 22, height: 44 },
                        style: { fill: '#EA580C', stroke: '#1E293B', lineWidth: 1 },
                    },
                    {
                        type: 'text',
                        left: 58,
                        top: 147,
                        style: { text: 'Kurang Erat', fill: '#1E293B', font: '500 11px Inter, sans-serif' },
                    },

                    // Segmen 1: Merah - Tidak Sama Sekali (Skala 1)
                    {
                        type: 'text',
                        left: 18,
                        top: 191,
                        style: { text: '1', fill: '#0F172A', font: '600 11px Inter, sans-serif', textAlign: 'right' },
                    },
                    {
                        type: 'rect',
                        left: 28,
                        top: 176,
                        shape: { width: 22, height: 44 },
                        style: { fill: '#B91C1C', stroke: '#1E293B', lineWidth: 1 },
                    },
                    {
                        type: 'text',
                        left: 58,
                        top: 191,
                        style: { text: 'Tidak Sama Sekali', fill: '#1E293B', font: '500 11px Inter, sans-serif' },
                    },
                ],
            },
        ],
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'item',
            formatter: (params) => {
                const d = params.data || {};
                return `
                    <div style="min-width: 170px;">
                        <div style="font-weight: 700; color: #0F172A; font-size: 13px; border-bottom: 1px solid #F1F5F9; padding-bottom: 4px; margin-bottom: 6px;">
                            ${escapeHtml(d.prodi || params.name)}
                        </div>
                        <div style="font-size: 12px; color: #475569; margin-bottom: 3px;">
                            Tingkat Keselarasan: <strong style="color: #0F172A;">${escapeHtml(d.category || params.seriesName)}</strong>
                        </div>
                        <div style="font-size: 12px; color: #475569; margin-bottom: 3px;">
                            Proporsi di Prodi: <strong style="color: #0D542B; font-size: 13px;">${formatNumber(d.value, 1)}%</strong>
                        </div>
                        <div style="font-size: 11px; color: #64748B;">
                            Jumlah Alumni: <strong style="color: #334155;">${formatNumber(d.count, 0)} dari n=${formatNumber(d.total, 0)} alumni</strong>
                        </div>
                    </div>
                `;
            },
        },
        series: seriesList,
    };
});

const stackedTable = computed(() => ({
    columns: [
        { key: 'prodi', label: 'Program Studi' },
        { key: 'total', label: 'Total Alumni (n)', align: 'right' },
        { key: 'sangat_erat', label: 'Sangat Erat (5)', align: 'right' },
        { key: 'erat', label: 'Erat (4)', align: 'right' },
        { key: 'cukup', label: 'Cukup Erat (3)', align: 'right' },
        { key: 'kurang', label: 'Kurang Erat (2)', align: 'right' },
        { key: 'tidak', label: 'Tidak Sama Sekali (1)', align: 'right' },
    ],
    rows: prodis.value.map((prodi) => {
        const rows = records.value.filter((r) => (r.nama_prodi || 'Tanpa prodi') === prodi);
        const total = rows.length;
        const countByLvl = (lvl) => rows.filter((r) => getScaleLevel(recordCategory(r)) === lvl).length;
        const fmtPct = (cnt) => `${cnt} (${total > 0 ? ((cnt / total) * 100).toFixed(1) : 0}%)`;

        return {
            prodi,
            total: formatNumber(total, 0),
            sangat_erat: fmtPct(countByLvl(5)),
            erat: fmtPct(countByLvl(4)),
            cukup: fmtPct(countByLvl(3)),
            kurang: fmtPct(countByLvl(2)),
            tidak: fmtPct(countByLvl(1)),
        };
    }),
}));

// -----------------------------------------------------------------------------
// 2. MATRIKS HEATMAP: Sebaran Keselarasan Bidang per Program Studi (Mengadopsi Indikator 02)
// -----------------------------------------------------------------------------
const heatmapCategories = computed(() => orderedLevels.map((lvl) => scaleDescriptions[lvl]));

const heatmapData = computed(() => {
    const listProdis = prodis.value;
    const cats = heatmapCategories.value;
    const prodiTotals = Object.fromEntries(
        listProdis.map((p) => [p, records.value.filter((r) => (r.nama_prodi || 'Tanpa prodi') === p).length]),
    );

    const rawValues = [];
    listProdis.forEach((prodi, yIndex) => {
        cats.forEach((cat, xIndex) => {
            const lvl = xIndex + 1;
            const count = records.value.filter((r) => {
                const p = r.nama_prodi || 'Tanpa prodi';
                return p === prodi && getScaleLevel(recordCategory(r)) === lvl;
            }).length;
            const total = Math.max(prodiTotals[prodi], 1);
            const pct = Number(((count / total) * 100).toFixed(1));
            rawValues.push({
                value: [xIndex, yIndex, pct],
                prodi,
                category: cat,
                count,
                total,
            });
        });
    });

    // Persentase asli tetap dipakai untuk tooltip dan tabel. Skor 1-5 hanya
    // dipakai untuk palet diskret merah-ke-hijau yang sama dengan Indikator 02.
    const positivePercentages = rawValues
        .map((item) => item.value[2])
        .filter((percentage) => percentage > 0);
    const minPct = Math.min(...positivePercentages, 0);
    const maxPct = Math.max(...positivePercentages, 0);
    const values = rawValues.map((item) => {
        const percentage = item.value[2];
        const intensity = percentage <= 0
            ? 1
            : (maxPct === minPct ? 3 : Math.round(1 + ((percentage - minPct) / (maxPct - minPct)) * 4));

        return {
            ...item,
            rawPercentage: percentage,
            value: [item.value[0], item.value[1], intensity],
        };
    });

    return { values, maxPct, prodiTotals };
});

const heatmapOption = computed(() => {
    const { values, prodiTotals } = heatmapData.value;
    const cats = heatmapCategories.value;
    const listProdis = prodis.value;
    const yLabels = listProdis.map((p) => `${p} (n=${prodiTotals[p]})`);

    return {
        ...baseOption(records.value.length),
        grid: { left: 20, right: 150, top: 75, bottom: 20, containLabel: true },
        xAxis: {
            type: 'category',
            position: 'top',
            data: cats,
            axisLabel: {
                rotate: -35,
                color: '#1E293B',
                fontSize: 11,
                fontWeight: 600,
                interval: 0,
            },
            axisTick: { show: true, alignWithLabel: true, lineStyle: { color: '#CBD5E1' } },
            axisLine: { lineStyle: { color: '#CBD5E1' } },
            splitArea: { show: false },
        },
        yAxis: {
            type: 'category',
            inverse: true,
            data: yLabels,
            axisLabel: { color: '#1E293B', width: 170, fontSize: 11, fontWeight: 500 },
            axisTick: { show: true, alignWithLabel: true, lineStyle: { color: '#CBD5E1' } },
            axisLine: { lineStyle: { color: '#CBD5E1' } },
            splitArea: { show: false },
        },
        visualMap: {
            type: 'continuous',
            min: 1,
            max: 5,
            precision: 0,
            orient: 'vertical',
            right: 18,
            top: 'middle',
            itemWidth: 18,
            itemHeight: 240,
            inRange: {
                color: ['#B91C1C', '#EF4444', '#E2F3E7', '#2F7D4A', '#0D542B'],
            },
            text: ['5 (Proporsi tertinggi)', '1 (Proporsi terendah)'],
            textGap: 10,
            textStyle: { color: '#1E293B', fontSize: 11, fontWeight: 600 },
        },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'item',
            formatter: ({ data }) => {
                if (!data) return '';
                return `
                    <div class="font-sans text-xs">
                        <div class="font-bold text-slate-900 border-b border-slate-200 pb-1 mb-1.5">${escapeHtml(data.prodi)}</div>
                        <div class="text-slate-600 mb-0.5"><span class="text-slate-500">Tingkat Keselarasan:</span> <strong>${escapeHtml(data.category)}</strong></div>
                        <div class="text-slate-600 mb-0.5"><span class="text-slate-500">Jumlah Alumni:</span> <strong>${formatNumber(data.count, 0)}</strong> (dari n=${data.total})</div>
                        <div class="text-slate-600"><span class="text-slate-500">Proporsi di Prodi:</span> <strong class="text-emerald-700 font-mono text-sm">${formatNumber(data.rawPercentage, 1)}%</strong></div>
                    </div>
                `;
            },
        },
        series: [{
            name: 'Proporsi Keselarasan Bidang',
            type: 'heatmap',
            data: values,
            itemStyle: {
                borderColor: '#FFFFFF',
                borderWidth: 1.5,
            },
            label: {
                show: false,
            },
            emphasis: {
                focus: 'none',
                itemStyle: {
                    borderColor: '#FFFFFF',
                    borderWidth: 1.5,
                    shadowBlur: 0,
                    shadowColor: 'transparent',
                },
                label: {
                    show: true,
                    fontSize: 13,
                    fontWeight: 800,
                    formatter: ({ data }) => (data?.rawPercentage > 0 ? `${formatNumber(data.rawPercentage, 0)}%` : '-'),
                },
            },
        }],
    };
});

const heatmapTable = computed(() => {
    const cats = heatmapCategories.value;
    const prodiTotals = heatmapData.value.prodiTotals;

    const columns = [
        { key: 'prodi', label: 'Program Studi' },
        { key: 'total', label: 'Responden (n)', align: 'right' },
        ...cats.map((cat, i) => ({
            key: `cat_${i}`,
            label: cat,
            align: 'right',
        })),
    ];

    const rows = prodis.value.map((prodi) => {
        const row = {
            prodi,
            total: formatNumber(prodiTotals[prodi] || 0, 0),
        };
        cats.forEach((cat, i) => {
            const lvl = i + 1;
            const count = records.value.filter((r) => {
                const p = r.nama_prodi || 'Tanpa prodi';
                return p === prodi && getScaleLevel(recordCategory(r)) === lvl;
            }).length;
            const total = Math.max(prodiTotals[prodi], 1);
            const pct = ((count / total) * 100).toFixed(1);
            row[`cat_${i}`] = `${formatNumber(count, 0)} (${pct}%)`;
        });
        return row;
    });

    return { columns, rows };
});

// -----------------------------------------------------------------------------
// 2. GRAFIK GARIS: Horizontal Match (Persentase Selaras) per Program Studi
// -----------------------------------------------------------------------------
const prodiMatchLineData = computed(() => {
    return prodis.value.map((prodi) => {
        const rows = records.value.filter((r) => (r.nama_prodi || 'Tanpa prodi') === prodi);
        const total = rows.length;
        const matches = rows.filter((r) => {
            const ans = String(recordCategory(r) || '').toLowerCase();
            return ans.includes('sangat erat') || ans === 'erat';
        }).length;
        const percentage = total > 0 ? Number(((matches / total) * 100).toFixed(1)) : 0;
        return {
            prodi,
            count: matches,
            total,
            percentage,
        };
    }).sort((a, b) => b.percentage - a.percentage);
});

const lineMatchOption = computed(() => {
    const list = prodiMatchLineData.value;
    const labels = list.map((i) => `${i.prodi}\n(n=${i.total})`);

    return {
        ...baseOption(validResponses.value),
        grid: { left: 24, right: 36, top: 28, bottom: 80, containLabel: true },
        xAxis: {
            type: 'category',
            data: labels,
            axisLabel: { color: '#334155', fontSize: 11, interval: 0, rotate: 30, lineHeight: 14 },
            axisLine: { lineStyle: { color: '#CBD5E1' } },
        },
        yAxis: {
            type: 'value',
            name: 'Persentase Horizontal Match (%)',
            max: 100,
            axisLabel: { formatter: '{value}%' },
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
        },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'axis',
            formatter: (params) => {
                const item = list[params[0]?.dataIndex ?? 0] || {};
                return `<strong>${escapeHtml(item.prodi)}</strong><br>Tingkat Keselarasan Horizontal: <strong>${item.percentage}%</strong><br>Jumlah alumni selaras: <strong>${item.count} dari ${item.total} alumni</strong>`;
            },
        },
        series: [{
            name: 'Horizontal Match',
            type: 'line',
            data: list.map((i) => i.percentage),
            itemStyle: { color: '#0D542B' },
            lineStyle: { width: 3, color: '#0D542B' },
            symbol: 'circle',
            symbolSize: 8,
            emphasis: { scale: 1.5 },
            label: {
                show: true,
                position: 'top',
                color: '#0D542B',
                fontWeight: 'bold',
                fontSize: 11,
                formatter: ({ value }) => `${value}%`,
            },
        }],
    };
});

const lineMatchTable = computed(() => ({
    columns: [
        { key: 'prodi', label: 'Program Studi' },
        { key: 'percentageDisplay', label: 'Tingkat Keselarasan (%)', align: 'right' },
        { key: 'countDisplay', label: 'Alumni Selaras / Total', align: 'right' },
    ],
    rows: prodiMatchLineData.value.map((i) => ({
        prodi: i.prodi,
        percentageDisplay: `${i.percentage}%`,
        countDisplay: `${i.count} / ${i.total} alumni`,
    })),
}));

// -----------------------------------------------------------------------------
// 3. DONUT CHART: Distribusi Kuesioner Tingkat Universitas
// -----------------------------------------------------------------------------
const univDonutOption = computed(() => {
    const data = series.value.map((item, index) => {
        const label = seriesLabel(item);
        const count = seriesMetric(item);
        const selected = !props.selectedLabel || props.selectedLabel === label;
        return {
            name: label,
            value: count,
            percentage: item.percentage || 0,
            itemStyle: {
                color: getAlignmentColor(label, index),
                opacity: selected ? 1 : 0.22,
            },
        };
    });

    return {
        ...baseOption(validResponses.value),
        legend: {
            orient: 'horizontal',
            bottom: 0,
            icon: 'circle',
            textStyle: { color: '#334155', fontSize: 11, fontWeight: 600 },
        },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'item',
            formatter: ({ data }) => `<strong>${escapeHtml(data.name)}</strong><br>Jumlah: <strong>${formatNumber(data.value, 0)} alumni</strong><br>Proporsi: <strong>${formatPercentage(data.percentage)}</strong>`,
        },
        series: [{
            name: 'Distribusi Keselarasan',
            type: 'pie',
            radius: ['44%', '70%'],
            center: ['50%', '46%'],
            avoidLabelOverlap: true,
            itemStyle: {
                borderRadius: 4,
                borderColor: '#FFFFFF',
                borderWidth: 2,
            },
            label: {
                show: true,
                formatter: ({ data }) => `${formatPercentage(data.percentage)}`,
                fontSize: 11,
                fontWeight: 'bold',
                color: '#1E293B',
            },
            data,
        }],
    };
});

const univDonutTable = computed(() => ({
    columns: [
        { key: 'category', label: 'Tingkat Keselarasan Bidang (F14)' },
        { key: 'count', label: 'Jumlah Alumni (n)', align: 'right' },
        { key: 'percentage', label: 'Persentase', align: 'right' },
    ],
    rows: series.value.map((item) => ({
        category: seriesLabel(item),
        count: formatNumber(seriesMetric(item), 0),
        percentage: formatPercentage(item.percentage || 0),
    })),
}));

// Analisis dinamis Horizontal Match per Program Studi
const dynamicHorizontalAnalysis = computed(() => {
    const list = prodiMatchLineData.value;
    if (list.length === 0) {
        return {
            title: 'Kilas Metodologi & Capaian Horizontal Match (IKU 1)',
            statusBadge: 'Rumus Resmi Dikti',
            badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
            lead: 'Data keselarasan horizontal sedang dimuat.',
            detail: '',
        };
    }

    const top = list[0];
    const bottom = list[list.length - 1];
    const rincian = list.map((i, idx) => `${idx + 1}. ${i.prodi}: ${i.percentage}%`).join(', ');

    return {
        title: 'Kilas Metodologi & Capaian Horizontal Match (IKU 1)',
        statusBadge: 'Rumus Resmi Dikti',
        badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
        lead: `Metodologi Perhitungan: Persentase Horizontal Match dihitung dari total alumni yang menyatakan pekerjaannya "Sangat Erat" atau "Erat" dengan bidang ilmu dibagi seluruh responden program studi terkait × 100% (baku mutu IKU 1). Capaian tertinggi diraih oleh ${top.prodi} (${top.percentage}%), sedangkan capaian terendah tercatat pada ${bottom.prodi} (${bottom.percentage}%).`,
        detail: `Rincian persentase keselarasan horizontal untuk seluruh program studi secara berurutan: ${rincian}.`,
    };
});

// Konfigurasi views untuk kontainer terpadu
const views = computed(() => [
    {
        key: 'stacked',
        chartType: 'stacked_horizontal_100',
        tabLabel: 'Komposisi Antarprodi',
        title: 'Komposisi keselarasan bidang per program studi',
        description: 'Perbandingan komposisi keselarasan bidang kerja antarprogram studi dengan skala penilaian 1-5 (mengadopsi desain & palet Indikator 02).',
        option: stackedOption.value,
        tableData: stackedTable.value,
        height: Math.max(380, prodis.value.length * 36 + 120),
        analysis: {
            title: 'Kilas Keselarasan Kurikulum & Karier',
            statusBadge: 'Distribusi Keselarasan',
            badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
            lead: series.value[0]?.label
                ? `Kategori keselarasan horizontal dominan adalah "${series.value[0].label}" dengan proporsi ${formatPercentage(series.value[0].percentage)} (${series.value[0].count} alumni).`
                : 'Data keselarasan bidang pekerjaan tersaji lengkap.',
            detail: 'Setiap segmen menunjukkan proporsi alumni yang menilai bidang kerjanya selaras dengan keilmuan yang ditempuh selama masa studi di UKDW.',
        },
    },
    {
        key: 'by_prodi_heatmap',
        chartType: 'matrix_heatmap',
        tabLabel: 'Sebaran per Prodi',
        title: 'Matriks sebaran keselarasan bidang per program studi',
        description: 'Sebaran persentase keselarasan bidang kerja alumni di setiap program studi dalam format matriks heatmap.',
        option: heatmapOption.value,
        tableData: heatmapTable.value,
        height: Math.max(380, prodis.value.length * 36 + 120),
        analysis: {
            title: 'Kilas Matriks Sebaran per Prodi',
            statusBadge: 'Matriks Sebaran',
            badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
            lead: 'Visualisasi intensitas pilihan keselarasan bidang kerja per program studi untuk memetakan konsentrasi tingkat relevansi kurikulum.',
            detail: 'Warna hijau yang lebih pekat mencerminkan konsentrasi alumni yang lebih tinggi pada kategori keselarasan terkait.',
        },
    },
    {
        key: 'line_match',
        chartType: 'percentage_line',
        tabLabel: 'Horizontal Match Garis',
        title: 'Tren horizontal match (persentase selaras) per program studi',
        description: 'Grafik garis persentase alumni yang menyatakan bidang pekerjaannya selaras (Sangat Erat dan Erat) dengan disiplin ilmu kuliah.',
        option: lineMatchOption.value,
        tableData: lineMatchTable.value,
        height: 400,
        analysis: dynamicHorizontalAnalysis.value,
    },
    {
        key: 'univ_distribution',
        chartType: 'donut',
        tabLabel: 'Distribusi Universitas',
        title: 'Hubungan bidang studi dengan pekerjaan tingkat universitas',
        description: 'Distribusi jawaban kuesioner resmi instrumen F14 seluruh universitas beserta tabulasi data alumni per kategori.',
        option: univDonutOption.value,
        tableData: univDonutTable.value,
        height: 380,
        analysis: {
            title: 'Kilas Kuesioner F14 Universitas',
            statusBadge: 'Instrumen F14',
            badgeClass: 'bg-slate-100 text-slate-700',
            lead: 'Distribusi menyeluruh mencerminkan keselarasan horisontal lulusan UKDW secara agregat.',
            detail: 'Alumni pada kategori erat dan sangat erat menjadi bukti keberhasilan transfer kompetensi program sarjana ke lingkungan kerja nyata.',
        },
    },
]);
</script>

<template>
    <AnalyticsTabbedContainer
        :views="views"
        :rows="records"
        :selected-label="selectedLabel"
        :loading="loading"
        indicator-badge="Indikator 15"
        @select="emit('select', $event)"
        @reset="emit('reset')"
    />
</template>
