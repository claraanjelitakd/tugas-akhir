/**
 * Factory opsi Apache ECharts untuk seluruh indikator Grafik Universitas.
 *
 * Mengimplementasikan visualisasi data flat, bersih, tanpa tekstur decal,
 * mendukung satu warna kohesif untuk metrik sejenis, grafik garis perbandingan
 * gap sesuai laporan resmi Tracer Study, serta kontrak data tabel terintegrasi
 * (Grafik | Data) untuk setiap visualisasi.
 */

// Palet bersama seluruh grafik: satu tone hijau UKDW dengan putih sebagai ruang visual.
export const analyticsColors = {
    green: '#0D542B',
    greenMid: '#2F7D4A',
    greenSoft: '#78B18B',
    greenPale: '#B7D7C0',
    greenMist: '#DCEBE0',
    white: '#FFFFFF',
    scaleOne: '#F3F8F4',
    scaleTwo: '#DCEEE1',
    scaleThree: '#B9D8C3',
    scaleFour: '#2F7D4A',
    scaleFive: '#0D542B',
    text: '#334155',
    textStrong: '#0F172A',
    grid: 'rgba(13, 84, 43, 0.14)',
    axis: 'rgba(13, 84, 43, 0.36)',
};

// Palet khusus Indikator 01 mempertahankan kontras hijau-kuning untuk komposisi respons.
export const responseRatePalette = ['#0D542B', '#D6A928'];

// Urutan warna seri konsisten untuk grafik dengan dua atau lebih seri.
export const analyticsPalette = [
    analyticsColors.green,
    analyticsColors.greenMid,
    analyticsColors.greenSoft,
    analyticsColors.greenPale,
    analyticsColors.greenMist,
];

// Urutan warna ordinal skala 1-5 dibuat naik dari netral ke capaian tertinggi.
export const analyticsScalePalette = [
    analyticsColors.scaleOne,
    analyticsColors.scaleTwo,
    analyticsColors.scaleThree,
    analyticsColors.scaleFour,
    analyticsColors.scaleFive,
];

// Format angka Indonesia untuk label, tooltip, dan sumbu.
export const formatNumber = (value, digits = 1) => Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: digits });

// Format persentase standar dengan simbol persen.
export const formatPercentage = (value, digits = 1) => `${formatNumber(value, digits)}%`;

// Format rupiah ringkas untuk sumbu dan label grafik.
export const formatCompactCurrency = (value) => new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    notation: 'compact',
    maximumFractionDigits: 1,
}).format(Number(value || 0));

// Format rupiah penuh untuk tabel ringkasan data.
export const formatFullCurrency = (value) => value === null || value === undefined || value === ''
    ? '-'
    : new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value || 0));

// Escape teks agar tidak dirender sebagai markup HTML pada tooltip.
export const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

// Label seri mendukung kontrak kategori, prodi, dan provinsi.
export const seriesLabel = (item) => item?.label || item?.nama_prodi || item?.province_name || item?.key || 'Tanpa label';

// Nilai metrik utama menyesuaikan tipe indikator.
export const seriesMetric = (item, type = '') => {
    if (type === 'learning_methods') return Number(item?.score || 0);
    if (type === 'application_pipeline') return Number(item?.median || 0);
    if (type === 'competency') return Number(item?.gap || 0);
    if (type === 'response_rate') return Number(item?.response_rate || 0);

    return Number(item?.count ?? item?.respondents ?? item?.population ?? 0);
};

// Kategori record dipakai bersama oleh bar, heatmap, dan tabel data.
export const recordCategory = (record) => record?.answer || record?.status || record?.comparison || record?.method || record?.competency || record?.kode_pertanyaan || 'Tanpa kategori';

// Perhitungan median numerik dari daftar nilai.
export const calculateMedian = (values) => {
    if (!values || !values.length) return 0;
    const sorted = [...values].filter(Number.isFinite).sort((a, b) => a - b);
    if (!sorted.length) return 0;
    const mid = Math.floor(sorted.length / 2);
    return sorted.length % 2 !== 0 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
};

// Pengelompokan record per program studi untuk metrik median dan jumlah sampel n.
export const getProdiMedians = (records, valueKey) => {
    const prodiMap = new Map();
    records.forEach((record) => {
        const prodi = record.nama_prodi || 'Tanpa prodi';
        const val = Number(record[valueKey]);
        if (Number.isFinite(val)) {
            if (!prodiMap.has(prodi)) prodiMap.set(prodi, []);
            prodiMap.get(prodi).push(val);
        }
    });

    return [...prodiMap.entries()]
        .map(([prodi, vals]) => ({
            prodi,
            count: vals.length,
            median: Number(calculateMedian(vals).toFixed(1)),
        }))
        .filter((item) => item.count > 0)
        .sort((left, right) => right.median - left.median || right.count - left.count);
};

// Opsi dasar ECharts dengan decal dinonaktifkan dan animasi halus agar tidak kaku.
export const baseOption = (validResponses = 0) => ({
    color: analyticsPalette,
    aria: { enabled: true, decal: { show: false } },
    backgroundColor: analyticsColors.white,
    textStyle: { fontFamily: 'Inter, ui-sans-serif, system-ui, sans-serif', color: analyticsColors.textStrong },
    animation: true,
    animationDuration: 700,
    animationEasing: 'cubicOut',
    animationDurationUpdate: 450,
    animationEasingUpdate: 'cubicOut',
    tooltip: {
        trigger: 'item',
        confine: true,
        appendToBody: false,
        backgroundColor: analyticsColors.white,
        borderColor: 'transparent',
        borderWidth: 0,
        textStyle: { color: analyticsColors.textStrong, fontSize: 12 },
        extraCssText: 'max-width:320px;white-space:normal;border:0;box-shadow:none;border-radius:6px;padding:10px 14px;',
    },
    graphic: validResponses > 0 ? [{
        type: 'text',
        right: 8,
        bottom: 0,
        silent: true,
        style: { text: `N valid: ${formatNumber(validResponses, 0)}`, fill: analyticsColors.text, fontSize: 11 },
    }] : [],
});

// Menyalin opsi ECharts tanpa merusak formatter function yang disediakan tiap grafik.
const cloneChartOption = (value) => {
    if (Array.isArray(value)) return value.map(cloneChartOption);
    if (value && typeof value === 'object') {
        return Object.fromEntries(Object.entries(value).map(([key, item]) => [key, cloneChartOption(item)]));
    }

    return value;
};

// Mengambil panjang teks sumbu agar margin dapat mengikuti label terpanjang.
const axisTextLength = (value) => {
    if (Array.isArray(value)) return value.reduce((max, item) => Math.max(max, axisTextLength(item)), 0);
    if (value && typeof value === 'object') return axisTextLength(value.value ?? value.name ?? '');
    return String(value ?? '').length;
};

// Menentukan margin minimum agar judul sumbu dan label kategori tidak terpotong.
const normalizeChartGrid = (option) => {
    const xAxes = Array.isArray(option.xAxis) ? option.xAxis : (option.xAxis ? [option.xAxis] : []);
    const yAxes = Array.isArray(option.yAxis) ? option.yAxis : (option.yAxis ? [option.yAxis] : []);
    const hasCategoryX = xAxes.some((axis) => axis?.type === 'category');
    const longestYLabel = yAxes.reduce((max, axis) => Math.max(
        max,
        axisTextLength(axis?.name),
        axisTextLength(axis?.data),
    ), 0);
    const longestXLabel = xAxes.reduce((max, axis) => Math.max(
        max,
        axisTextLength(axis?.name),
        axisTextLength(axis?.data),
    ), 0);
    // `containLabel` sudah menambahkan ruang label di luar grid; jangan menggandakan
    // lebar label ke grid.left karena hasilnya membuat plot terdorong terlalu kanan.
    const requiredLeft = 40;
    const requiredBottom = hasCategoryX
        ? Math.min(128, Math.max(72, longestXLabel * 2.6 + 42))
        : 40;
    const grids = Array.isArray(option.grid) ? option.grid : (option.grid ? [option.grid] : []);

    grids.forEach((grid) => {
        grid.left = Math.max(Number(grid.left) || 0, requiredLeft);
        grid.right = Math.max(Number(grid.right) || 0, 32);
        grid.top = Math.max(Number(grid.top) || 0, 24);
        grid.bottom = Math.max(Number(grid.bottom) || 0, requiredBottom);
        grid.containLabel = true;
    });

    if (grids.length === 0 && (xAxes.length > 0 || yAxes.length > 0)) {
        option.grid = [{
            left: requiredLeft,
            right: 32,
            top: 24,
            bottom: requiredBottom,
            containLabel: true,
        }];
    }

    yAxes.forEach((axis) => {
        if (axis?.type !== 'category') return;
        axis.axisLabel = axis.axisLabel || {};
        axis.axisLabel.width = Math.max(Number(axis.axisLabel.width) || 0, Math.min(250, longestYLabel * 6.2 + 8));
        axis.axisLabel.lineHeight = Math.max(Number(axis.axisLabel.lineHeight) || 0, 16);
        axis.axisLabel.margin = Math.max(Number(axis.axisLabel.margin) || 0, 14);
        axis.axisLabel.overflow = 'break';
    });
};

// Menghapus warna lokal yang tidak konsisten, border, dan shadow dari setiap elemen data.
const normalizeChartStyle = (style, color) => {
    if (!style || typeof style !== 'object') return;

    style.color = color;
    style.borderColor = 'transparent';
    style.borderWidth = 0;
    style.shadowBlur = 0;
    style.shadowColor = 'transparent';
};

// Memilih warna teks yang terbaca berdasarkan kecerahan warna seri atau sel.
const readableLabelColor = (color) => {
    const hex = String(color || '').replace('#', '');
    if (hex.length !== 6) return analyticsColors.textStrong;
    const channels = [0, 2, 4].map((index) => Number.parseInt(hex.slice(index, index + 2), 16) / 255);
    const luminance = channels.map((channel) => channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4);
    const relativeLuminance = 0.2126 * luminance[0] + 0.7152 * luminance[1] + 0.0722 * luminance[2];
    return relativeLuminance > 0.45 ? analyticsColors.textStrong : analyticsColors.white;
};

// Menentukan urutan segmen bertumpuk secara semantik dan stabil antarindikator.
const stackOrderKey = (label) => {
    const text = String(label || '').toLowerCase();
    const scale = text.match(/^([1-5])\s*=/);
    if (scale) return [0, Number(scale[1]), text];
    const orderedTerms = [
        '< 2',
        '2-6',
        '> 6',
        'setelah',
        'sebelum',
        'tidak mencari',
        'bekerja',
        'wiraswasta',
        'melanjutkan',
        'mencari kerja',
        'belum',
    ];
    const priority = orderedTerms.findIndex((term) => text.includes(term));
    return [1, priority < 0 ? orderedTerms.length : priority, text];
};

// Mengurutkan seri pada setiap kelompok stack tanpa mengubah seri non-stack.
const normalizeStackOrder = (series) => {
    const stackNames = [...new Set(series.filter((item) => item?.stack).map((item) => item.stack))];
    stackNames.forEach((stackName) => {
        const indexes = series
            .map((item, index) => item?.stack === stackName ? index : -1)
            .filter((index) => index >= 0);
        const ordered = indexes.map((index) => series[index]).sort((left, right) => {
            const leftKey = stackOrderKey(left.name);
            const rightKey = stackOrderKey(right.name);
            return leftKey[0] - rightKey[0] || leftKey[1] - rightKey[1] || leftKey[2].localeCompare(rightKey[2], 'id');
        });
        indexes.forEach((index, position) => {
            series[index] = ordered[position];
        });
    });
};

// Menyamakan warna seri, tooltip, legenda, dan elemen sumbu pada semua tipe grafik.
export const normalizeAnalyticsOption = (option) => {
    const normalized = cloneChartOption(option || {});
    const customPalette = Array.isArray(option?.themePalette) && option.themePalette.length
        ? option.themePalette
        : null;

    delete normalized.themePalette;
    normalized.color = customPalette || analyticsPalette;
    normalized.backgroundColor = analyticsColors.white;

    normalizeChartGrid(normalized);

    const xAxes = Array.isArray(normalized.xAxis) ? normalized.xAxis : (normalized.xAxis ? [normalized.xAxis] : []);
    const yAxes = Array.isArray(normalized.yAxis) ? normalized.yAxis : (normalized.yAxis ? [normalized.yAxis] : []);
    [...xAxes, ...yAxes].forEach((axis) => {
        if (!axis) return;
        axis.axisLine = axis.axisLine || {};
        axis.axisLine.lineStyle = axis.axisLine.lineStyle || {};
        axis.axisLine.lineStyle.color = analyticsColors.axis;
        axis.axisLabel = axis.axisLabel || {};
        axis.axisLabel.color = analyticsColors.text;
        if (axis.type === 'category') {
            axis.axisLabel.overflow = 'break';
            axis.axisLabel.hideOverlap = false;
        }
        if (axis.nameTextStyle) axis.nameTextStyle.color = analyticsColors.text;
        if (axis.splitLine) {
            axis.splitLine.lineStyle = axis.splitLine.lineStyle || {};
            axis.splitLine.lineStyle.color = analyticsColors.grid;
        }
    });

    if (normalized.legend) {
        normalized.legend.textStyle = normalized.legend.textStyle || {};
        normalized.legend.textStyle.color = analyticsColors.text;
    }

    if (normalized.title) {
        const titles = Array.isArray(normalized.title) ? normalized.title : [normalized.title];
        titles.forEach((title) => {
            title.textStyle = title.textStyle || {};
            title.textStyle.color = analyticsColors.textStrong;
        });
    }

    if (normalized.tooltip) {
        normalized.tooltip.backgroundColor = analyticsColors.white;
        normalized.tooltip.borderColor = 'transparent';
        normalized.tooltip.borderWidth = 0;
        normalized.tooltip.textStyle = normalized.tooltip.textStyle || {};
        normalized.tooltip.textStyle.color = analyticsColors.textStrong;
        normalized.tooltip.extraCssText = 'max-width:320px;white-space:normal;border:0;box-shadow:none;border-radius:6px;padding:10px 14px;';
        if (normalized.tooltip.axisPointer) {
            normalized.tooltip.axisPointer.lineStyle = normalized.tooltip.axisPointer.lineStyle || {};
            normalized.tooltip.axisPointer.lineStyle.color = analyticsColors.greenMid;
            normalized.tooltip.axisPointer.shadowStyle = normalized.tooltip.axisPointer.shadowStyle || {};
            normalized.tooltip.axisPointer.shadowStyle.color = 'rgba(13, 84, 43, 0.12)';
        }
    }

    if (Array.isArray(normalized.series)) {
        normalizeStackOrder(normalized.series);
        normalized.series.forEach((series, seriesIndex) => {
            const scaleValue = /^([1-5])\s*=/.exec(String(series.name || ''))?.[1];
            const seriesColor = scaleValue
                ? analyticsScalePalette[Number(scaleValue) - 1]
                : (customPalette || analyticsPalette)[seriesIndex % (customPalette || analyticsPalette).length];
            normalizeChartStyle(series.itemStyle, seriesColor);
            if (series.lineStyle) {
                series.lineStyle.color = seriesColor;
                series.lineStyle.shadowBlur = 0;
            }
            if (series.areaStyle) {
                series.areaStyle.color = seriesColor;
                series.areaStyle.shadowBlur = 0;
            }
            normalizeChartStyle(series.emphasis?.itemStyle, seriesColor);
            if (series.emphasis?.lineStyle) series.emphasis.lineStyle.color = seriesColor;
            if (series.type === 'bar' && series.stack) {
                const percentageAxis = normalized.xAxis?.max === 100
                    || normalized.yAxis?.max === 100
                    || (Array.isArray(series.data) && series.data.some((datum) => Number(typeof datum === 'object' ? datum.value : datum) > 1));
                const originalFormatter = series.label?.formatter;
                series.label = {
                    ...(series.label || {}),
                    show: true,
                    position: series.label?.position || 'inside',
                    color: readableLabelColor(seriesColor),
                    formatter: originalFormatter || ((params) => {
                        const value = Number(params?.value ?? params?.data?.value ?? 0);
                        if (value <= 0) return '';
                        return percentageAxis ? `${formatNumber(value, 1)}%` : formatNumber(value, 0);
                    }),
                };
            }
            if (series.label && series.type === 'bar') {
                series.label.color = readableLabelColor(seriesColor);
            } else if (series.label && series.label.color !== analyticsColors.white) {
                series.label.color = analyticsColors.textStrong;
            }
            if (series.type === 'heatmap' && series.label) {
                series.label.color = (params) => Number(params?.value?.[2] || 0) >= 4
                    ? analyticsColors.white
                    : analyticsColors.textStrong;
            }
            if (series.type === 'heatmap') {
                series.itemStyle = series.itemStyle || {};
                series.itemStyle.borderColor = analyticsColors.white;
                series.itemStyle.borderWidth = 1;
                series.emphasis = series.emphasis || {};
                series.emphasis.itemStyle = series.emphasis.itemStyle || {};
                series.emphasis.itemStyle.borderColor = analyticsColors.green;
                series.emphasis.itemStyle.borderWidth = 1;
            }
            if (series.markLine) {
                series.markLine.lineStyle = series.markLine.lineStyle || {};
                series.markLine.lineStyle.color = analyticsColors.greenMid;
                if (series.markLine.label) series.markLine.label.color = analyticsColors.text;
            }
            if (series.type === 'sankey' && Array.isArray(series.links)) {
                series.links.forEach((link, linkIndex) => {
                    link.lineStyle = link.lineStyle || {};
                    link.lineStyle.color = analyticsPalette[linkIndex % analyticsPalette.length];
                    link.lineStyle.opacity = 0.42;
                    link.lineStyle.shadowBlur = 0;
                });
            }

            if (Array.isArray(series.data)) {
                series.data.forEach((datum, datumIndex) => {
                    if (!datum || typeof datum !== 'object') return;
                    const explicitDatumColor = customPalette && typeof datum.itemStyle?.color === 'string'
                        ? datum.itemStyle.color
                        : null;
                    const datumColor = explicitDatumColor || (['pie', 'funnel', 'treemap', 'sankey'].includes(series.type)
                        ? (customPalette || analyticsPalette)[datumIndex % (customPalette || analyticsPalette).length]
                        : seriesColor);
                    normalizeChartStyle(datum.itemStyle, datumColor);
                    normalizeChartStyle(datum.emphasis?.itemStyle, datumColor);
                    if (datum.lineStyle) {
                        datum.lineStyle.color = datumColor;
                        datum.lineStyle.shadowBlur = 0;
                    }
                    if (datum.areaStyle) {
                        datum.areaStyle.color = datumColor;
                        datum.areaStyle.shadowBlur = 0;
                    }
                    if (datum.label && series.type === 'heatmap') {
                        const heatmapValue = Number(datum.value?.[2] || 0);
                        datum.label.color = heatmapValue >= 4 ? analyticsColors.white : analyticsColors.textStrong;
                    } else if (datum.label && datum.label.color !== analyticsColors.white) {
                        datum.label.color = analyticsColors.textStrong;
                    }
                });
            }
        });
    }

    if (Array.isArray(normalized.series)) {
        normalized.series.forEach((series) => {
            if (series.type !== 'pie') return;
            series.label = {
                ...(series.label || {}),
                show: true,
                position: 'outside',
                color: analyticsColors.textStrong,
                overflow: 'break',
            };
            series.labelLine = {
                ...(series.labelLine || {}),
                show: true,
                length: Math.max(Number(series.labelLine?.length) || 0, 14),
                length2: Math.max(Number(series.labelLine?.length2) || 0, 10),
                lineStyle: {
                    ...(series.labelLine?.lineStyle || {}),
                    color: analyticsColors.greenMid,
                    width: 1.5,
                },
            };
        });
    }

    if (normalized.visualMap?.pieces) {
        normalized.visualMap.pieces.forEach((piece, index) => {
            const value = Number(piece.value);
            piece.color = value >= 1 && value <= 5
                ? analyticsScalePalette[value - 1]
                : analyticsPalette[index % analyticsPalette.length];
        });
    }
    if (normalized.visualMap?.inRange?.color) {
        normalized.visualMap.inRange.color = analyticsScalePalette;
    }

    if (normalized.radar) {
        normalized.radar.axisName = normalized.radar.axisName || {};
        normalized.radar.axisName.color = analyticsColors.text;
        normalized.radar.splitLine = normalized.radar.splitLine || {};
        normalized.radar.splitLine.lineStyle = normalized.radar.splitLine.lineStyle || {};
        normalized.radar.splitLine.lineStyle.color = analyticsColors.grid;
        normalized.radar.splitArea = normalized.radar.splitArea || {};
        normalized.radar.splitArea.show = false;
        normalized.radar.splitArea.areaStyle = normalized.radar.splitArea.areaStyle || {};
        normalized.radar.splitArea.areaStyle.color = [analyticsColors.white, analyticsColors.greenMist];
    }

    if (Array.isArray(normalized.graphic)) {
        normalized.graphic.forEach((graphic, graphicIndex) => {
            if (!graphic.style) return;
            if (graphic.type === 'text') {
                if (graphic.style.fill) graphic.style.fill = analyticsColors.text;
                return;
            }
            if (graphic.style.fill) graphic.style.fill = (customPalette || analyticsPalette)[graphicIndex % (customPalette || analyticsPalette).length];
            if (graphic.style.stroke) graphic.style.stroke = analyticsColors.greenMid;
            graphic.style.shadowBlur = 0;
            graphic.style.shadowColor = 'transparent';
        });
    }

    return normalized;
};

// Datum kategori dengan dukungan warna solid tunggal atau palet warna.
const categoryDatum = (item, index, selectedLabel, metric, type, forcedColor = null) => {
    const label = seriesLabel(item);
    const selected = !selectedLabel || selectedLabel === label;
    const baseColor = forcedColor || analyticsPalette[index % analyticsPalette.length];

    return {
        ...item,
        name: label,
        value: metric(item, type),
        itemStyle: { color: baseColor, opacity: selected ? 1 : 0.22 },
    };
};

/**
 * Batang horizontal dengan satu warna solid konsisten untuk metrik pada kategori yang sama.
 */
export const horizontalBar = (series, type, selectedLabel, validResponses, title = 'Jumlah', solidColor = '#0D542B') => {
    const rows = [...series]
        .map((item, index) => categoryDatum(item, index, selectedLabel, seriesMetric, type, solidColor))
        .sort((left, right) => right.value - left.value);

    const option = {
        ...baseOption(validResponses),
        grid: { left: 12, right: 38, top: 12, bottom: rows.length > 8 ? 44 : 24, containLabel: true },
        xAxis: {
            type: 'value',
            name: title,
            nameLocation: 'middle',
            nameGap: 26,
            axisLabel: { color: '#64748B' },
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
        },
        yAxis: {
            type: 'category',
            inverse: true,
            data: rows.map((item) => item.name),
            axisLabel: { color: '#334155', width: 170, overflow: 'truncate' },
            axisTick: { show: false },
            axisLine: { lineStyle: { color: '#CBD5E1' } },
        },
        dataZoom: rows.length > 10 ? [{ type: 'inside', yAxisIndex: 0 }] : [],
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            formatter: (params) => {
                const point = params[0]?.data || {};
                const percentage = point.percentage !== undefined ? `<br>Persentase: <strong>${formatNumber(point.percentage)}%</strong>` : '';
                return `<strong>${escapeHtml(point.name)}</strong><br>${escapeHtml(title)}: <strong>${formatNumber(point.value)}</strong>${percentage}<br>N valid: ${formatNumber(validResponses, 0)}`;
            },
        },
        series: [{
            name: title,
            type: 'bar',
            barMaxWidth: 26,
            emphasis: { focus: 'self', itemStyle: { opacity: 1 } },
            label: { show: true, position: 'right', color: '#334155', fontWeight: 600, formatter: ({ value }) => formatNumber(value) },
            data: rows,
        }],
    };

    const tableData = {
        columns: [
            { key: 'name', label: 'Kategori / Label' },
            { key: 'formattedValue', label: title, align: 'right' },
            ...(rows.some((row) => row.percentage !== undefined) ? [{ key: 'percentage', label: 'Persentase', align: 'right' }] : []),
        ],
        rows: rows.map((item) => ({
            name: item.name,
            value: item.value,
            formattedValue: formatNumber(item.value, 1),
            percentage: item.percentage !== undefined ? `${formatNumber(item.percentage)}%` : undefined,
        })),
    };

    return { option, tableData };
};

/**
 * Line Chart perbandingan gap kompetensi (mengadopsi Gambar 4.17 Laporan Tracer Study).
 * Membandingkan skor kompetensi saat lulus vs skor yang dibutuhkan pekerjaan.
 */
export const competencyLine = (series, validResponses) => {
    const labels = series.map(seriesLabel);
    const gradScores = series.map((item) => Number(item.graduation_score || 0));
    const needScores = series.map((item) => Number(item.need_score || 0));

    const option = {
        ...baseOption(validResponses),
        legend: {
            data: ['Kompetensi Saat Lulus', 'Kompetensi Dibutuhkan'],
            bottom: 0,
            icon: 'circle',
            textStyle: { color: '#334155', fontWeight: 600 },
        },
        grid: { left: 16, right: 34, top: 28, bottom: 48, containLabel: true },
        xAxis: {
            type: 'category',
            data: labels,
            axisLabel: { rotate: labels.length > 5 ? 24 : 0, color: '#334155', width: 110, overflow: 'truncate' },
            axisLine: { lineStyle: { color: '#CBD5E1' } },
        },
        yAxis: {
            type: 'value',
            min: 1,
            max: 5,
            interval: 0.5,
            name: 'Skor Likert (1 - 5)',
            axisLabel: { color: '#64748B' },
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
        },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'axis',
            formatter: (params) => {
                const titleText = escapeHtml(params[0]?.name || '');
                const grad = Number(params.find((p) => p.seriesName === 'Kompetensi Saat Lulus')?.value || 0);
                const need = Number(params.find((p) => p.seriesName === 'Kompetensi Dibutuhkan')?.value || 0);
                const gap = Number((need - grad).toFixed(2));
                const gapNote = gap > 0 ? `<span style="color:#F59E0B">Gap deficit: +${formatNumber(gap, 2)} (perlu ditingkatkan)</span>` : `<span style="color:#10B981">Gap surplus: ${formatNumber(gap, 2)} (terpenuhi)</span>`;
                return `<strong>${titleText}</strong><br>Saat lulus: <strong>${formatNumber(grad, 2)}</strong><br>Dibutuhkan: <strong>${formatNumber(need, 2)}</strong><br>${gapNote}<br>N valid: ${formatNumber(validResponses, 0)}`;
            },
        },
        series: [
            {
                name: 'Kompetensi Saat Lulus',
                type: 'line',
                data: gradScores,
                itemStyle: { color: '#2563EB' },
                lineStyle: { width: 3, color: '#2563EB' },
                symbol: 'circle',
                symbolSize: 8,
                label: { show: true, position: 'top', color: '#1D4ED8', fontWeight: 600, formatter: ({ value }) => formatNumber(value, 2) },
            },
            {
                name: 'Kompetensi Dibutuhkan',
                type: 'line',
                data: needScores,
                itemStyle: { color: '#0D542B' },
                lineStyle: { width: 3, color: '#0D542B' },
                symbol: 'rect',
                symbolSize: 8,
                label: { show: true, position: 'bottom', color: '#0A4322', fontWeight: 600, formatter: ({ value }) => formatNumber(value, 2) },
            },
        ],
    };

    const tableData = {
        columns: [
            { key: 'name', label: 'Dimensi Kompetensi' },
            { key: 'graduation', label: 'Saat Lulus', align: 'right' },
            { key: 'need', label: 'Dibutuhkan', align: 'right' },
            { key: 'gap', label: 'Gap (Selisih)', align: 'right' },
            { key: 'status', label: 'Status Evaluasi', align: 'center' },
        ],
        rows: series.map((item) => {
            const grad = Number(item.graduation_score || 0);
            const need = Number(item.need_score || 0);
            const gap = Number((need - grad).toFixed(2));
            return {
                name: seriesLabel(item),
                graduation: formatNumber(grad, 2),
                need: formatNumber(need, 2),
                gap: gap > 0 ? `+${formatNumber(gap, 2)}` : formatNumber(gap, 2),
                status: gap > 0 ? 'Perlu Ditingkatkan' : 'Terpenuhi',
                isDeficit: gap > 0,
            };
        }),
    };

    return { option, tableData };
};

/**
 * Line Chart penekanan metode belajar (mengadopsi Gambar 4.3 Laporan Tracer Study).
 */
export const methodsLine = (series, validResponses) => {
    const labels = series.map(seriesLabel);
    const scores = series.map((item) => Number(item.score || 0));

    const option = {
        ...baseOption(validResponses),
        grid: { left: 16, right: 28, top: 28, bottom: 44, containLabel: true },
        xAxis: {
            type: 'category',
            data: labels,
            axisLabel: { rotate: labels.length > 5 ? 24 : 0, color: '#334155', width: 115, overflow: 'truncate' },
            axisLine: { lineStyle: { color: '#CBD5E1' } },
        },
        yAxis: {
            type: 'value',
            min: 0,
            max: 5,
            interval: 1,
            name: 'Median Skor (Skala 1 - 5)',
            axisLabel: { color: '#64748B' },
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
        },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'axis',
            formatter: (params) => {
                const point = params[0] || {};
                return `<strong>${escapeHtml(point.name)}</strong><br>Median skor: <strong>${formatNumber(point.value, 1)}</strong><br>N valid: ${formatNumber(validResponses, 0)}`;
            },
        },
        series: [{
            name: 'Median skor',
            type: 'line',
            data: scores,
            itemStyle: { color: '#0D542B' },
            lineStyle: { width: 3, color: '#0D542B' },
            symbol: 'circle',
            symbolSize: 8,
            label: { show: true, position: 'top', color: '#0D542B', fontWeight: 700, formatter: ({ value }) => formatNumber(value, 1) },
        }],
    };

    const tableData = {
        columns: [
            { key: 'name', label: 'Metode Pembelajaran' },
            { key: 'score', label: 'Median Skor (1-5)', align: 'right' },
            { key: 'count', label: 'Responden (N)', align: 'right' },
        ],
        rows: series.map((item) => ({
            name: seriesLabel(item),
            score: formatNumber(item.score, 1),
            count: formatNumber(item.count || validResponses, 0),
        })),
    };

    return { option, tableData };
};

/**
 * Line Chart per program studi dengan sampel n (mengadopsi Gambar 4.6 & 4.12 Laporan Tracer Study).
 */
export const prodiMetricLine = (records, valueKey, metricTitle, yName, isCurrency = false) => {
    const prodiData = getProdiMedians(records, valueKey);
    const labelsWithN = prodiData.map((item) => `${item.prodi}\n(n=${item.count})`);
    const values = prodiData.map((item) => item.median);

    const option = {
        ...baseOption(records.length),
        grid: { left: 16, right: 30, top: 28, bottom: prodiData.length > 5 ? 56 : 38, containLabel: true },
        xAxis: {
            type: 'category',
            data: labelsWithN,
            axisLabel: { color: '#334155', fontSize: 11, interval: 0 },
            axisLine: { lineStyle: { color: '#CBD5E1' } },
        },
        yAxis: {
            type: 'value',
            name: yName,
            axisLabel: {
                color: '#64748B',
                formatter: (val) => isCurrency ? formatCompactCurrency(val) : formatNumber(val),
            },
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
        },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'axis',
            formatter: (params) => {
                const index = params[0]?.dataIndex ?? 0;
                const item = prodiData[index] || {};
                const displayVal = isCurrency ? formatFullCurrency(item.median) : `${formatNumber(item.median, 1)} ${yName}`;
                return `<strong>${escapeHtml(item.prodi)}</strong><br>${escapeHtml(metricTitle)}: <strong>${displayVal}</strong><br>Jumlah responden: <strong>n = ${formatNumber(item.count, 0)}</strong>`;
            },
        },
        series: [{
            name: metricTitle,
            type: 'line',
            data: values,
            itemStyle: { color: '#2563EB' },
            lineStyle: { width: 3, color: '#2563EB' },
            symbol: 'circle',
            symbolSize: 8,
            label: {
                show: true,
                position: 'top',
                color: '#1E3A8A',
                fontWeight: 700,
                formatter: ({ value }) => isCurrency ? formatCompactCurrency(value) : formatNumber(value, 1),
            },
        }],
    };

    const tableData = {
        columns: [
            { key: 'prodi', label: 'Program Studi' },
            { key: 'medianDisplay', label: metricTitle, align: 'right' },
            { key: 'count', label: 'Responden (n)', align: 'right' },
        ],
        rows: prodiData.map((item) => ({
            prodi: item.prodi,
            medianDisplay: isCurrency ? formatFullCurrency(item.median) : formatNumber(item.median, 1),
            count: formatNumber(item.count, 0),
        })),
    };

    return { option, tableData };
};

/**
 * Line Chart tahapan lamaran per program studi (mengadopsi Gambar 4.7 Laporan Tracer Study).
 */
export const prodiPipelineLine = (records) => {
    const prodis = [...new Set(records.map((r) => r.nama_prodi || 'Tanpa prodi'))];
    const grouped = prodis.map((prodi) => {
        const prodiRows = records.filter((r) => (r.nama_prodi || 'Tanpa prodi') === prodi);
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

    const labelsWithN = grouped.map((item) => `${item.prodi}\n(n=${item.count})`);

    const option = {
        ...baseOption(records.length),
        legend: {
            data: ['Perusahaan Dilamar', 'Merespon', 'Mewawancara'],
            bottom: 0,
            icon: 'circle',
            textStyle: { color: '#334155', fontWeight: 600 },
        },
        grid: { left: 16, right: 30, top: 28, bottom: 58, containLabel: true },
        xAxis: {
            type: 'category',
            data: labelsWithN,
            axisLabel: { color: '#334155', fontSize: 11, interval: 0 },
            axisLine: { lineStyle: { color: '#CBD5E1' } },
        },
        yAxis: {
            type: 'value',
            name: 'Median Jumlah Perusahaan',
            axisLabel: { color: '#64748B' },
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
        },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'axis',
            formatter: (params) => {
                const item = grouped[params[0]?.dataIndex ?? 0] || {};
                return `<strong>${escapeHtml(item.prodi)}</strong> (n=${item.count})<br>Median dilamar: <strong>${formatNumber(item.dilamar, 0)}</strong><br>Median merespon: <strong>${formatNumber(item.merespon, 0)}</strong><br>Median wawancara: <strong>${formatNumber(item.wawancara, 0)}</strong>`;
            },
        },
        series: [
            {
                name: 'Perusahaan Dilamar',
                type: 'line',
                data: grouped.map((g) => g.dilamar),
                itemStyle: { color: '#2563EB' },
                lineStyle: { width: 3, color: '#2563EB' },
                symbol: 'circle',
                symbolSize: 8,
                label: { show: true, position: 'top', formatter: ({ value }) => formatNumber(value, 0) },
            },
            {
                name: 'Merespon',
                type: 'line',
                data: grouped.map((g) => g.merespon),
                itemStyle: { color: '#DC2626' },
                lineStyle: { width: 3, color: '#DC2626' },
                symbol: 'rect',
                symbolSize: 8,
                label: { show: true, position: 'bottom', formatter: ({ value }) => formatNumber(value, 0) },
            },
            {
                name: 'Mewawancara',
                type: 'line',
                data: grouped.map((g) => g.wawancara),
                itemStyle: { color: '#0D542B' },
                lineStyle: { width: 3, color: '#0D542B' },
                symbol: 'triangle',
                symbolSize: 8,
                label: { show: true, position: 'right', formatter: ({ value }) => formatNumber(value, 0) },
            },
        ],
    };

    const tableData = {
        columns: [
            { key: 'prodi', label: 'Program Studi' },
            { key: 'dilamar', label: 'Dilamar (Median)', align: 'right' },
            { key: 'merespon', label: 'Merespon (Median)', align: 'right' },
            { key: 'wawancara', label: 'Wawancara (Median)', align: 'right' },
            { key: 'count', label: 'Responden (n)', align: 'right' },
        ],
        rows: grouped.map((item) => ({
            prodi: item.prodi,
            dilamar: formatNumber(item.dilamar, 0),
            merespon: formatNumber(item.merespon, 0),
            wawancara: formatNumber(item.wawancara, 0),
            count: formatNumber(item.count, 0),
        })),
    };

    return { option, tableData };
};

/**
 * Donut chart proporsi dengan solid colors dan tabel data ringkasan.
 */
export const donut = (series, selectedLabel, validResponses) => {
    const sorted = [...series].sort((left, right) => seriesMetric(right) - seriesMetric(left));
    const visible = sorted.slice(0, 5).map((item, index) => categoryDatum(item, index, selectedLabel, seriesMetric));
    const remainder = sorted.slice(5).reduce((total, item) => total + seriesMetric(item), 0);
    if (remainder > 0) visible.push(categoryDatum({ label: 'Kategori lainnya', count: remainder }, 5, selectedLabel, seriesMetric));

    const totalCount = sorted.reduce((sum, item) => sum + seriesMetric(item), 0);

    const option = {
        ...baseOption(validResponses),
        legend: { type: 'scroll', orient: 'horizontal', bottom: 0, textStyle: { color: '#475569' } },
        tooltip: {
            ...baseOption().tooltip,
            formatter: ({ data, percent }) => `<strong>${escapeHtml(data.name)}</strong><br>Jumlah: <strong>${formatNumber(data.value)}</strong><br>Persentase: <strong>${formatNumber(percent)}%</strong><br>N valid: ${formatNumber(validResponses, 0)}`,
        },
        series: [{
            name: 'Proporsi',
            type: 'pie',
            radius: ['48%', '72%'],
            center: ['50%', '44%'],
            selectedMode: 'single',
            minAngle: 4,
            avoidLabelOverlap: true,
            itemStyle: { borderColor: '#FFFFFF', borderWidth: 2 },
            emphasis: { scale: true, scaleSize: 6, focus: 'self' },
            label: { formatter: '{b}\n{d}%', color: '#334155' },
            labelLine: {
                show: true,
                length: 16,
                length2: 12,
                lineStyle: { color: analyticsColors.greenMid, width: 1.5 },
            },
            data: visible,
        }],
        graphic: [
            ...baseOption(validResponses).graphic,
            { type: 'text', left: 'center', top: '38%', silent: true, style: { text: formatNumber(validResponses, 0), fill: '#0D542B', fontSize: 24, fontWeight: 800, textAlign: 'center' } },
            { type: 'text', left: 'center', top: '47%', silent: true, style: { text: 'respon valid', fill: '#64748B', fontSize: 11, textAlign: 'center' } },
        ],
    };

    const tableData = {
        columns: [
            { key: 'name', label: 'Kategori' },
            { key: 'count', label: 'Jumlah Alumni', align: 'right' },
            { key: 'percentage', label: 'Persentase', align: 'right' },
        ],
        rows: sorted.map((item) => {
            const count = seriesMetric(item);
            const percentage = totalCount > 0 ? Number(((count / totalCount) * 100).toFixed(1)) : 0;
            return {
                name: seriesLabel(item),
                count: formatNumber(count, 0),
                percentage: `${formatNumber(percentage)}%`,
            };
        }),
    };

    return { option, tableData };
};

/**
 * Treemap komposisi kategori.
 */
export const treemap = (series, selectedLabel, validResponses) => {
    const dataItems = series
        .filter((item) => seriesMetric(item) > 0)
        .map((item, index) => categoryDatum(item, index, selectedLabel, seriesMetric));

    const option = {
        ...baseOption(validResponses),
        tooltip: {
            ...baseOption().tooltip,
            formatter: ({ data }) => `<strong>${escapeHtml(data.name)}</strong><br>Jumlah: <strong>${formatNumber(data.value)}</strong><br>${formatNumber((data.value / Math.max(validResponses, 1)) * 100)}% dari N valid`,
        },
        series: [{
            name: 'Komposisi',
            type: 'treemap',
            roam: false,
            nodeClick: 'link',
            breadcrumb: { show: true, bottom: 0 },
            top: 6,
            bottom: 28,
            label: { show: true, formatter: '{b}\n{c}', overflow: 'break', fontWeight: 700 },
            upperLabel: { show: false },
            itemStyle: { borderColor: '#FFFFFF', borderWidth: 2, gapWidth: 2 },
            emphasis: { focus: 'ancestor' },
            data: dataItems,
        }],
    };

    const tableData = {
        columns: [
            { key: 'name', label: 'Kategori' },
            { key: 'count', label: 'Jumlah', align: 'right' },
            { key: 'percentage', label: 'Persentase', align: 'right' },
        ],
        rows: dataItems.map((item) => ({
            name: item.name,
            count: formatNumber(item.value, 0),
            percentage: `${formatNumber((item.value / Math.max(validResponses, 1)) * 100)}%`,
        })),
    };

    return { option, tableData };
};

/**
 * Heatmap pola antarprogram studi tanpa decal, dengan nilai angka langsung di sel.
 */
export const heatmap = (records, selectedLabel, mode = 'count') => {
    const prodis = [...new Set(records.map((record) => record.nama_prodi || 'Tanpa prodi'))];
    const categories = [...new Set(records.map(recordCategory))];
    const totalsByProdi = Object.fromEntries(prodis.map((prodi) => [prodi, records.filter((record) => (record.nama_prodi || 'Tanpa prodi') === prodi).length]));
    const values = [];

    prodis.forEach((prodi, yIndex) => {
        categories.forEach((category, xIndex) => {
            const count = records.filter((record) => (record.nama_prodi || 'Tanpa prodi') === prodi && recordCategory(record) === category).length;
            const value = mode === 'percentage' ? Number(((count / Math.max(totalsByProdi[prodi], 1)) * 100).toFixed(1)) : count;
            values.push({
                value: [xIndex, yIndex, value],
                category,
                prodi,
                count,
                itemStyle: { opacity: !selectedLabel || category === selectedLabel ? 1 : 0.22 },
            });
        });
    });

    const maximum = Math.max(...values.map((item) => item.value[2]), 1);

    const option = {
        ...baseOption(records.length),
        grid: { left: 12, right: 24, top: 20, bottom: categories.length > 5 ? 100 : 64, containLabel: true },
        xAxis: {
            type: 'category',
            data: categories,
            axisLabel: { rotate: categories.length > 4 ? 26 : 0, color: '#334155', width: 110, overflow: 'truncate' },
            splitArea: { show: false },
        },
        yAxis: {
            type: 'category',
            data: prodis,
            axisLabel: { color: '#334155', width: 130, overflow: 'truncate' },
            splitArea: { show: false },
        },
        visualMap: {
            min: 0,
            max: maximum,
            calculable: true,
            orient: 'horizontal',
            left: 'center',
            bottom: 0,
            inRange: { color: analyticsScalePalette },
            text: [mode === 'percentage' ? 'Tinggi (%)' : 'Tinggi', 'Rendah'],
            textStyle: { color: '#475569', fontSize: 11 },
        },
        tooltip: {
            ...baseOption().tooltip,
            formatter: ({ data }) => `<strong>${escapeHtml(data.prodi)}</strong><br>${escapeHtml(data.category)}<br>Jumlah: <strong>${formatNumber(data.count, 0)}</strong>${mode === 'percentage' ? `<br>Proporsi prodi: <strong>${formatNumber(data.value[2])}%</strong>` : ''}`,
        },
        series: [{
            name: mode === 'percentage' ? 'Persentase' : 'Jumlah',
            type: 'heatmap',
            data: values,
            itemStyle: { borderColor: '#FFFFFF', borderWidth: 1 },
            label: {
                show: values.length <= 80,
                fontSize: 10,
                color: (param) => param.data?.value[2] > maximum * 0.45 ? '#FFFFFF' : '#1E293B',
                formatter: ({ value }) => value[2] > 0 ? value[2] : '',
            },
            emphasis: { itemStyle: { shadowBlur: 6, shadowColor: 'rgba(15,23,42,.2)' } },
            progressive: 1000,
        }],
    };

    const tableData = {
        columns: [
            { key: 'prodi', label: 'Program Studi' },
            { key: 'total', label: 'Total Jawaban', align: 'right' },
            { key: 'topCategory', label: 'Kategori Terbanyak', align: 'left' },
        ],
        rows: prodis.map((prodi) => {
            const prodiValues = values.filter((v) => v.prodi === prodi);
            const total = prodiValues.reduce((sum, v) => sum + v.count, 0);
            const top = [...prodiValues].sort((a, b) => b.count - a.count)[0];
            return {
                prodi,
                total: formatNumber(total, 0),
                topCategory: top && top.count > 0 ? `${top.category} (${top.count})` : '-',
            };
        }),
    };

    return { option, tableData };
};

/**
 * Batang 100% bertumpuk antarprogram studi dengan tabel ringkasan.
 */
export const stackedByProdi = (records, selectedLabel) => {
    const prodis = [...new Set(records.map((record) => record.nama_prodi || 'Tanpa prodi'))]
        .sort((left, right) => {
            const leftTotal = records.filter((record) => (record.nama_prodi || 'Tanpa prodi') === left).length;
            const rightTotal = records.filter((record) => (record.nama_prodi || 'Tanpa prodi') === right).length;
            return rightTotal - leftTotal || left.localeCompare(right, 'id');
        });
    const categories = [...new Set(records.map(recordCategory))];

    const option = {
        ...baseOption(new Set(records.map((record) => record.biodata_id)).size),
        legend: { type: 'scroll', bottom: 0, textStyle: { color: '#475569' } },
        grid: { left: 12, right: 24, top: 18, bottom: 52, containLabel: true },
        xAxis: { type: 'value', max: 100, axisLabel: { formatter: '{value}%', color: '#64748B' }, splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } } },
        yAxis: { type: 'category', inverse: true, data: prodis, axisLabel: { color: '#334155', width: 140, overflow: 'truncate' } },
        tooltip: { ...baseOption().tooltip, trigger: 'axis', axisPointer: { type: 'shadow' }, valueFormatter: (value) => `${formatNumber(value)}%` },
        series: categories.map((category, index) => ({
            name: category,
            type: 'bar',
            stack: 'total',
            barMaxWidth: 26,
            emphasis: { focus: 'series' },
            itemStyle: { color: analyticsPalette[index % analyticsPalette.length], opacity: !selectedLabel || selectedLabel === category ? 1 : 0.2 },
            data: prodis.map((prodi) => {
                const prodiRows = records.filter((record) => (record.nama_prodi || 'Tanpa prodi') === prodi);
                const count = prodiRows.filter((record) => recordCategory(record) === category).length;
                return Number(((count / Math.max(prodiRows.length, 1)) * 100).toFixed(1));
            }),
        })),
    };

    const tableData = {
        columns: [
            { key: 'prodi', label: 'Program Studi' },
            { key: 'totalRespondents', label: 'Responden', align: 'right' },
            { key: 'dominantCategory', label: 'Kategori Dominan', align: 'left' },
        ],
        rows: prodis.map((prodi) => {
            const prodiRows = records.filter((r) => (r.nama_prodi || 'Tanpa prodi') === prodi);
            const counts = categories.map((cat) => ({
                cat,
                count: prodiRows.filter((r) => recordCategory(r) === cat).length,
            })).sort((a, b) => b.count - a.count);
            const top = counts[0];
            const pct = prodiRows.length > 0 && top ? ((top.count / prodiRows.length) * 100).toFixed(1) : 0;
            return {
                prodi,
                totalRespondents: formatNumber(prodiRows.length, 0),
                dominantCategory: top && top.count > 0 ? `${top.cat} (${pct}%)` : '-',
            };
        }),
    };

    return { option, tableData };
};

/**
 * Diagram Sankey untuk alur program studi ke kategori jawaban.
 */
export const sankey = (records, selectedLabel) => {
    const linksMap = new Map();
    records.forEach((record) => {
        const source = record.nama_prodi || 'Tanpa prodi';
        const target = recordCategory(record);
        const key = `${source}::${target}`;
        linksMap.set(key, { source, target, value: (linksMap.get(key)?.value || 0) + 1 });
    });
    const links = [...linksMap.values()];
    const nodes = [...new Set(links.flatMap((link) => [link.source, link.target]))].map((name) => ({
        name,
        itemStyle: { opacity: !selectedLabel || selectedLabel === name || links.some((link) => link.target === selectedLabel && link.source === name) ? 1 : 0.28 },
    }));

    const option = {
        ...baseOption(new Set(records.map((record) => record.biodata_id)).size),
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'item',
            formatter: ({ dataType, data }) => dataType === 'edge' ? `${escapeHtml(data.source)} → ${escapeHtml(data.target)}<br><strong>${formatNumber(data.value, 0)} alumni</strong>` : `<strong>${escapeHtml(data.name)}</strong>`,
        },
        series: [{
            type: 'sankey',
            data: nodes,
            links,
            left: 16,
            right: 16,
            top: 16,
            bottom: 16,
            draggable: true,
            nodeAlign: 'justify',
            lineStyle: { color: 'gradient', curveness: 0.5, opacity: 0.35 },
            emphasis: { focus: 'adjacency', lineStyle: { opacity: 0.75 } },
            label: { color: '#334155', fontSize: 11 },
        }],
    };

    const tableData = {
        columns: [
            { key: 'source', label: 'Program Studi' },
            { key: 'target', label: 'Kategori Tujuan' },
            { key: 'value', label: 'Jumlah Alumni', align: 'right' },
        ],
        rows: links.sort((a, b) => b.value - a.value).slice(0, 15).map((link) => ({
            source: link.source,
            target: link.target,
            value: formatNumber(link.value, 0),
        })),
    };

    return { option, tableData };
};

/**
 * Box plot sebaran data per program studi dengan tabel statistik ringkasan.
 */
export const boxplot = (records, valueKey, currency = false) => {
    const groups = [...new Set(records.map((record) => record.nama_prodi || 'Universitas'))];
    const quantile = (sorted, percentile) => {
        if (!sorted.length) return 0;
        const position = (sorted.length - 1) * percentile;
        const base = Math.floor(position);
        const rest = position - base;
        return sorted[base + 1] === undefined ? sorted[base] : sorted[base] + rest * (sorted[base + 1] - sorted[base]);
    };

    const outliers = [];
    const statsTable = [];

    const values = groups.map((group, groupIndex) => {
        const sorted = records
            .filter((record) => (record.nama_prodi || 'Universitas') === group)
            .map((record) => Number(record[valueKey]))
            .filter(Number.isFinite)
            .sort((a, b) => a - b);

        const q1 = quantile(sorted, 0.25);
        const median = quantile(sorted, 0.5);
        const q3 = quantile(sorted, 0.75);
        const iqr = q3 - q1;
        const lowerLimit = q1 - (1.5 * iqr);
        const upperLimit = q3 + (1.5 * iqr);
        const whiskerMin = sorted.find((value) => value >= lowerLimit) ?? sorted[0] ?? 0;
        const whiskerMax = [...sorted].reverse().find((value) => value <= upperLimit) ?? sorted.at(-1) ?? 0;

        sorted.filter((value) => value < whiskerMin || value > whiskerMax).forEach((value) => outliers.push([groupIndex, value]));

        statsTable.push({
            group,
            n: sorted.length,
            q1,
            median,
            q3,
        });

        return [whiskerMin, q1, median, q3, whiskerMax];
    });

    const option = {
        ...baseOption(records.length),
        legend: { data: ['Distribusi', 'Outlier'], bottom: 0 },
        grid: { left: 16, right: 24, top: 20, bottom: groups.length > 6 ? 90 : 54, containLabel: true },
        xAxis: { type: 'category', data: groups, axisLabel: { rotate: groups.length > 5 ? 25 : 0, width: 120, overflow: 'truncate' } },
        yAxis: { type: 'value', axisLabel: { formatter: (value) => currency ? formatCompactCurrency(value) : formatNumber(value) }, splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } } },
        dataZoom: groups.length > 8 ? [{ type: 'inside' }] : [],
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'item',
            formatter: ({ seriesType, name, value }) => {
                if (seriesType === 'scatter') return `<strong>${escapeHtml(groups[value[0]])}</strong><br>Outlier: ${currency ? formatCompactCurrency(value[1]) : formatNumber(value[1])}`;
                const statistics = value.length > 5 ? value.slice(-5) : value;
                const display = (item) => currency ? formatCompactCurrency(item) : formatNumber(item);
                return `<strong>${escapeHtml(name)}</strong><br>Whisker bawah: ${display(statistics[0])}<br>Q1: ${display(statistics[1])}<br>Median: ${display(statistics[2])}<br>Q3: ${display(statistics[3])}<br>Whisker atas: ${display(statistics[4])}`;
            },
        },
        series: [
            { name: 'Distribusi', type: 'boxplot', data: values, itemStyle: { color: '#8DD8AE', borderColor: '#0D542B', borderWidth: 2 } },
            { name: 'Outlier', type: 'scatter', data: outliers, symbolSize: 7, itemStyle: { color: '#DC2626' } },
        ],
    };

    const tableData = {
        columns: [
            { key: 'group', label: 'Program Studi' },
            { key: 'medianDisplay', label: 'Median', align: 'right' },
            { key: 'iqrDisplay', label: 'Q1 – Q3', align: 'right' },
            { key: 'nDisplay', label: 'Sampel (n)', align: 'right' },
        ],
        rows: statsTable.map((item) => ({
            group: item.group,
            medianDisplay: currency ? formatFullCurrency(item.median) : formatNumber(item.median, 1),
            iqrDisplay: currency ? `${formatCompactCurrency(item.q1)} – ${formatCompactCurrency(item.q3)}` : `${formatNumber(item.q1, 1)} – ${formatNumber(item.q3, 1)}`,
            nDisplay: formatNumber(item.n, 0),
        })),
    };

    return { option, tableData };
};

/**
 * Histogram waktu tunggu dengan 1 warna solid emerald dan garis batas median.
 */
export const waitHistogram = (records, median, selectedLabel) => {
    const bins = [
        { label: '0–3 bulan', min: 0, max: 3 },
        { label: '4–6 bulan', min: 4, max: 6 },
        { label: '7–12 bulan', min: 7, max: 12 },
        { label: '>12 bulan', min: 13, max: Number.POSITIVE_INFINITY },
    ].map((bin, index) => {
        const count = records.filter((record) => Number(record.months) >= bin.min && Number(record.months) <= bin.max).length;
        return categoryDatum({ ...bin, count, percentage: (count / Math.max(records.length, 1)) * 100 }, index, selectedLabel, seriesMetric, '', '#0D542B');
    });

    const option = {
        ...baseOption(records.length),
        grid: { left: 16, right: 28, top: 34, bottom: 42, containLabel: true },
        xAxis: { type: 'category', data: bins.map((bin) => bin.name), axisLabel: { color: '#334155', fontWeight: 600 } },
        yAxis: { type: 'value', name: 'Jumlah Alumni', splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } } },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            formatter: (params) => {
                const bin = params[0].data;
                return `<strong>${escapeHtml(bin.name)}</strong><br>Frekuensi: <strong>${formatNumber(bin.value, 0)}</strong><br>Persentase: <strong>${formatNumber(bin.percentage)}%</strong><br>Median keseluruhan: ${formatNumber(median)} bulan`;
            },
        },
        series: [{
            name: 'Frekuensi',
            type: 'bar',
            barMaxWidth: 56,
            data: bins,
            markLine: {
                silent: true,
                symbol: 'none',
                label: { formatter: `Median: ${formatNumber(median)} bln`, color: '#0D542B', fontWeight: 700 },
                lineStyle: { color: '#0D542B', type: 'dashed', width: 2 },
                data: [{ xAxis: median <= 3 ? '0–3 bulan' : median <= 6 ? '4–6 bulan' : median <= 12 ? '7–12 bulan' : '>12 bulan' }],
            },
        }],
    };

    const tableData = {
        columns: [
            { key: 'name', label: 'Rentang Waktu Tunggu' },
            { key: 'count', label: 'Jumlah Alumni', align: 'right' },
            { key: 'percentage', label: 'Persentase', align: 'right' },
        ],
        rows: bins.map((bin) => ({
            name: bin.name,
            count: formatNumber(bin.value, 0),
            percentage: `${formatNumber(bin.percentage)}%`,
        })),
    };

    return { option, tableData };
};

/**
 * Kurva kumulatif waktu tunggu memperoleh pekerjaan.
 */
export const cumulativeWait = (records) => {
    const values = records.map((record) => Number(record.months)).filter(Number.isFinite).sort((a, b) => a - b);
    const months = [...new Set(values)];
    const data = months.map((month) => [month, Number(((values.filter((value) => value <= month).length / Math.max(values.length, 1)) * 100).toFixed(1))]);

    const option = {
        ...baseOption(records.length),
        grid: { left: 16, right: 26, top: 22, bottom: 44, containLabel: true },
        xAxis: { type: 'value', name: 'Bulan ke-', nameLocation: 'middle', nameGap: 26, min: 0, splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } } },
        yAxis: { type: 'value', name: 'Persentase Kumulatif', max: 100, axisLabel: { formatter: '{value}%' }, splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } } },
        tooltip: { ...baseOption().tooltip, trigger: 'axis', formatter: (params) => `<strong>Bulan ke-${formatNumber(params[0].value[0])}</strong><br>${formatNumber(params[0].value[1])}% alumni telah memperoleh pekerjaan` },
        series: [{ name: 'Kumulatif', type: 'line', smooth: true, symbolSize: 6, lineStyle: { color: '#0D542B', width: 3 }, itemStyle: { color: '#0D542B' }, areaStyle: { color: 'rgba(13,84,43,.08)' }, data }],
    };

    const milestones = [1, 2, 3, 6, 12];
    const tableData = {
        columns: [
            { key: 'milestone', label: 'Bulan Target' },
            { key: 'cumulativeDisplay', label: 'Capaian Kumulatif', align: 'right' },
        ],
        rows: milestones.map((m) => {
            const count = values.filter((v) => v <= m).length;
            const pct = values.length ? ((count / values.length) * 100).toFixed(1) : 0;
            return {
                milestone: `≤ ${m} bulan`,
                cumulativeDisplay: `${formatNumber(pct)}% (${formatNumber(count, 0)} alumni)`,
            };
        }),
    };

    return { option, tableData };
};

/**
 * Radar penilaian metode pembelajaran atau kompetensi.
 */
export const radar = (series, competency = false) => {
    const validCount = Math.max(...series.map((item) => Number(item.count || 0)), 0);

    const option = {
        ...baseOption(validCount),
        legend: { data: competency ? ['Saat lulus', 'Dibutuhkan'] : ['Median penilaian'], bottom: 0 },
        radar: {
            center: ['50%', '47%'],
            radius: '66%',
            indicator: series.map((item) => ({ name: seriesLabel(item), max: 5 })),
            axisName: { color: '#334155', fontSize: 11 },
            splitArea: { areaStyle: { color: ['#FFFFFF', '#F8FAFC'] } },
            splitLine: { lineStyle: { color: '#CBD5E1' } },
        },
        tooltip: { ...baseOption().tooltip, trigger: 'item' },
        series: [{
            type: 'radar',
            data: competency
                ? [
                    { name: 'Saat lulus', value: series.map((item) => Number(item.graduation_score || 0)), areaStyle: { color: 'rgba(37,99,235,.15)' }, lineStyle: { color: '#2563EB', width: 2 }, itemStyle: { color: '#2563EB' } },
                    { name: 'Dibutuhkan', value: series.map((item) => Number(item.need_score || 0)), areaStyle: { color: 'rgba(13,84,43,.15)' }, lineStyle: { color: '#0D542B', width: 2 }, itemStyle: { color: '#0D542B' } },
                ]
                : [{ name: 'Median penilaian', value: series.map((item) => Number(item.score || 0)), areaStyle: { color: 'rgba(13,84,43,.15)' }, lineStyle: { color: '#0D542B', width: 2 }, itemStyle: { color: '#0D542B' } }],
        }],
    };

    const tableData = {
        columns: competency
            ? [
                { key: 'name', label: 'Dimensi Kompetensi' },
                { key: 'grad', label: 'Saat Lulus', align: 'right' },
                { key: 'need', label: 'Dibutuhkan', align: 'right' },
            ]
            : [
                { key: 'name', label: 'Metode Pembelajaran' },
                { key: 'score', label: 'Median Skor (1-5)', align: 'right' },
            ],
        rows: series.map((item) => competency ? {
            name: seriesLabel(item),
            grad: formatNumber(item.graduation_score, 2),
            need: formatNumber(item.need_score, 2),
        } : {
            name: seriesLabel(item),
            score: formatNumber(item.score, 1),
        }),
    };

    return { option, tableData };
};

/**
 * Diagram Dumbbell untuk selisih skor kompetensi.
 */
export const dumbbell = (series) => {
    const sorted = [...series].sort((left, right) => Number(right.gap || 0) - Number(left.gap || 0));

    const option = {
        ...baseOption(Math.max(...sorted.map((item) => Number(item.count || 0)), 0)),
        grid: { left: 16, right: 32, top: 20, bottom: 30, containLabel: true },
        xAxis: { type: 'value', min: 0, max: 5, interval: 1, splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } } },
        yAxis: { type: 'category', inverse: true, data: sorted.map(seriesLabel), axisLabel: { width: 160, overflow: 'truncate' } },
        tooltip: { ...baseOption().tooltip, formatter: ({ data }) => `<strong>${escapeHtml(data.name)}</strong><br>Saat lulus: <strong>${formatNumber(data.value[0], 2)}</strong><br>Dibutuhkan: <strong>${formatNumber(data.value[1], 2)}</strong><br>Gap: <strong>${formatNumber(data.value[1] - data.value[0], 2)}</strong>` },
        series: [{
            name: 'Gap kompetensi',
            type: 'custom',
            renderItem: (params, api) => {
                const graduation = api.coord([api.value(0), api.value(2)]);
                const need = api.coord([api.value(1), api.value(2)]);
                return {
                    type: 'group',
                    children: [
                        { type: 'line', shape: { x1: graduation[0], y1: graduation[1], x2: need[0], y2: need[1] }, style: { stroke: '#94A3B8', lineWidth: 4 } },
                        { type: 'circle', shape: { cx: graduation[0], cy: graduation[1], r: 7 }, style: { fill: '#2563EB' } },
                        { type: 'circle', shape: { cx: need[0], cy: need[1], r: 7 }, style: { fill: '#0D542B' } },
                    ],
                };
            },
            encode: { x: [0, 1], y: 2 },
            data: sorted.map((item, index) => ({ name: seriesLabel(item), value: [Number(item.graduation_score || 0), Number(item.need_score || 0), index] })),
        }],
    };

    const tableData = {
        columns: [
            { key: 'name', label: 'Dimensi' },
            { key: 'grad', label: 'Saat Lulus', align: 'right' },
            { key: 'need', label: 'Dibutuhkan', align: 'right' },
            { key: 'gap', label: 'Gap', align: 'right' },
        ],
        rows: sorted.map((item) => ({
            name: seriesLabel(item),
            grad: formatNumber(item.graduation_score, 2),
            need: formatNumber(item.need_score, 2),
            gap: formatNumber(Number(item.need_score || 0) - Number(item.graduation_score || 0), 2),
        })),
    };

    return { option, tableData };
};

/**
 * Diagram Funnel proses lamaran pekerjaan.
 */
export const funnel = (series) => {
    const option = {
        ...baseOption(Math.max(...series.map((item) => Number(item.count || 0)), 0)),
        tooltip: { ...baseOption().tooltip, formatter: ({ data }) => `<strong>${escapeHtml(data.name)}</strong><br>Median: <strong>${formatNumber(data.value)}</strong><br>Jawaban valid: ${formatNumber(data.count, 0)}` },
        series: [{
            name: 'Tahap lamaran',
            type: 'funnel',
            left: '10%',
            width: '80%',
            top: 20,
            bottom: 24,
            minSize: '16%',
            maxSize: '100%',
            sort: 'descending',
            gap: 4,
            label: { show: true, position: 'inside', formatter: '{b}: {c}', fontWeight: 700 },
            emphasis: { focus: 'self' },
            data: series.map((item, index) => ({ name: seriesLabel(item), value: Number(item.median || 0), count: item.count, itemStyle: { color: analyticsPalette[index] } })),
        }],
    };

    const tableData = {
        columns: [
            { key: 'name', label: 'Tahapan Lamaran' },
            { key: 'medianDisplay', label: 'Median Perusahaan', align: 'right' },
            { key: 'count', label: 'Responden Valid', align: 'right' },
        ],
        rows: series.map((item) => ({
            name: seriesLabel(item),
            medianDisplay: formatNumber(item.median, 1),
            count: formatNumber(item.count || 0, 0),
        })),
    };

    return { option, tableData };
};

/**
 * Scatter plot F6 terhadap F7.
 */
export const pipelineScatter = (records) => {
    const grouped = new Map();
    records.forEach((record) => {
        const current = grouped.get(record.biodata_id) || { nama: record.nama, prodi: record.nama_prodi };
        current[record.kode_pertanyaan] = Number(record.value);
        grouped.set(record.biodata_id, current);
    });
    const data = [...grouped.values()].filter((item) => Number.isFinite(item.F6) && Number.isFinite(item.F7)).map((item) => ({ name: item.nama, prodi: item.prodi, value: [item.F6, item.F7] }));

    const option = {
        ...baseOption(data.length),
        grid: { left: 16, right: 24, top: 18, bottom: 48, containLabel: true },
        xAxis: { type: 'value', name: 'Lamaran', nameLocation: 'middle', nameGap: 26, splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } } },
        yAxis: { type: 'value', name: 'Respons', splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } } },
        tooltip: { ...baseOption().tooltip, formatter: ({ data: point }) => `<strong>${escapeHtml(point.name)}</strong><br>${escapeHtml(point.prodi)}<br>Lamaran: ${formatNumber(point.value[0], 0)}<br>Respons: ${formatNumber(point.value[1], 0)}` },
        series: [{ name: 'Alumni', type: 'scatter', symbolSize: 9, data, itemStyle: { color: '#2563EB', opacity: 0.72 } }],
    };

    const tableData = {
        columns: [
            { key: 'name', label: 'Nama Alumni' },
            { key: 'prodi', label: 'Program Studi' },
            { key: 'applied', label: 'Dilamar', align: 'right' },
            { key: 'responded', label: 'Merespon', align: 'right' },
        ],
        rows: data.slice(0, 15).map((item) => ({
            name: item.name,
            prodi: item.prodi,
            applied: formatNumber(item.value[0], 0),
            responded: formatNumber(item.value[1], 0),
        })),
    };

    return { option, tableData };
};

/**
 * Scatter plot gaji terhadap ambang UMP.
 */
export const salaryScatter = (records) => {
    const meetsRows = records.filter((record) => record.meets_ump);
    const belowRows = records.filter((record) => !record.meets_ump);

    const option = {
        ...baseOption(records.length),
        legend: { data: ['Memenuhi', 'Belum memenuhi'], bottom: 0 },
        grid: { left: 16, right: 24, top: 18, bottom: 52, containLabel: true },
        xAxis: { type: 'value', name: 'Ambang 1,2 × UMP', nameLocation: 'middle', nameGap: 28, axisLabel: { formatter: formatCompactCurrency }, splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } } },
        yAxis: { type: 'value', name: 'Gaji alumni', axisLabel: { formatter: formatCompactCurrency }, splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } } },
        tooltip: { ...baseOption().tooltip, formatter: ({ data }) => `<strong>${escapeHtml(data.name)}</strong><br>${escapeHtml(data.prodi)}<br>${escapeHtml(data.company)}<br>Gaji: <strong>${formatCompactCurrency(data.value[1])}</strong><br>Ambang: ${formatCompactCurrency(data.value[0])}<br>Status: ${data.meets ? 'Memenuhi' : 'Belum memenuhi'}` },
        series: [
            { name: 'Memenuhi', type: 'scatter', symbolSize: 10, data: meetsRows.map((record) => ({ name: record.nama, prodi: record.nama_prodi, company: record.nama_perusahaan, meets: true, value: [Number(record.threshold), Number(record.salary)] })), itemStyle: { color: '#0D542B', opacity: 0.75 } },
            { name: 'Belum memenuhi', type: 'scatter', symbolSize: 10, data: belowRows.map((record) => ({ name: record.nama, prodi: record.nama_prodi, company: record.nama_perusahaan, meets: false, value: [Number(record.threshold), Number(record.salary)] })), itemStyle: { color: '#DC2626', opacity: 0.75 } },
        ],
    };

    const tableData = {
        columns: [
            { key: 'name', label: 'Nama Alumni' },
            { key: 'company', label: 'Perusahaan' },
            { key: 'salary', label: 'Gaji Aktual', align: 'right' },
            { key: 'status', label: 'Status UMP', align: 'center' },
        ],
        rows: records.slice(0, 15).map((record) => ({
            name: record.nama,
            company: record.nama_perusahaan || '-',
            salary: formatFullCurrency(record.salary),
            status: record.meets_ump ? 'Memenuhi 1,2×' : 'Belum Memenuhi',
        })),
    };

    return { option, tableData };
};

/**
 * Bullet status persentase pemenuhan 1,2x UMP.
 */
export const bullet = (positive, negative, positiveLabel, negativeLabel) => {
    const total = Math.max(positive + negative, 1);
    const posPct = Number(((positive / total) * 100).toFixed(1));
    const negPct = Number(((negative / total) * 100).toFixed(1));

    const option = {
        ...baseOption(total),
        legend: { data: [positiveLabel, negativeLabel], bottom: 0 },
        grid: { left: 18, right: 18, top: 40, bottom: 44, containLabel: true },
        xAxis: { type: 'value', max: 100, axisLabel: { formatter: '{value}%' }, splitLine: { show: false } },
        yAxis: { type: 'category', data: ['Komposisi'], axisTick: { show: false }, axisLine: { show: false } },
        tooltip: { ...baseOption().tooltip, trigger: 'axis', axisPointer: { type: 'shadow' }, valueFormatter: (value) => `${formatNumber(value)}%` },
        series: [
            { name: positiveLabel, type: 'bar', stack: 'total', barWidth: 38, itemStyle: { color: '#0D542B' }, label: { show: posPct >= 10, position: 'inside', formatter: `${posPct}%`, color: '#FFFFFF', fontWeight: 700 }, data: [posPct] },
            { name: negativeLabel, type: 'bar', stack: 'total', barWidth: 38, itemStyle: { color: '#DC2626' }, label: { show: negPct >= 10, position: 'inside', formatter: `${negPct}%`, color: '#FFFFFF', fontWeight: 700 }, data: [negPct] },
        ],
    };

    const tableData = {
        columns: [
            { key: 'status', label: 'Status Kelayakan' },
            { key: 'count', label: 'Jumlah Alumni', align: 'right' },
            { key: 'percentage', label: 'Persentase', align: 'right' },
        ],
        rows: [
            { status: positiveLabel, count: formatNumber(positive, 0), percentage: `${posPct}%` },
            { status: negativeLabel, count: formatNumber(negative, 0), percentage: `${negPct}%` },
        ],
    };

    return { option, tableData };
};

/**
 * Batang divergen F3 (sebelum vs sesudah lulus).
 */
export const diverging = (series, selectedLabel, validResponses) => {
    const normalized = series.map((item, index) => {
        const label = seriesLabel(item);
        const isBefore = label.toLowerCase().includes('sebelum');
        const value = seriesMetric(item) * (isBefore ? -1 : 1);
        return { ...categoryDatum(item, index, selectedLabel, seriesMetric, '', isBefore ? '#2563EB' : '#0D542B'), value, absoluteValue: seriesMetric(item) };
    });

    const option = {
        ...baseOption(validResponses),
        grid: { left: 18, right: 18, top: 22, bottom: 44, containLabel: true },
        xAxis: { type: 'value', axisLabel: { formatter: (value) => Math.abs(value) }, splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } } },
        yAxis: { type: 'category', data: normalized.map((item) => item.name), axisLabel: { width: 170, overflow: 'truncate' } },
        tooltip: { ...baseOption().tooltip, formatter: ({ data }) => `<strong>${escapeHtml(data.name)}</strong><br>Jumlah: <strong>${formatNumber(data.absoluteValue, 0)}</strong><br>${data.value < 0 ? 'Sebelum kelulusan' : 'Setelah atau saat kelulusan'}` },
        series: [{ name: 'Alumni', type: 'bar', data: normalized, label: { show: true, formatter: ({ data }) => formatNumber(data.absoluteValue, 0), position: 'inside' } }],
    };

    const tableData = {
        columns: [
            { key: 'name', label: 'Kategori Waktu' },
            { key: 'timing', label: 'Posisi Kelulusan' },
            { key: 'count', label: 'Jumlah Alumni', align: 'right' },
        ],
        rows: normalized.map((item) => ({
            name: item.name,
            timing: item.value < 0 ? 'Sebelum Lulus' : 'Setelah Lulus',
            count: formatNumber(item.absoluteValue, 0),
        })),
    };

    return { option, tableData };
};

/**
 * Grouped bar response rate universitas per prodi.
 */
export const responseGroupedBar = (series) => {
    const totalPop = series.reduce((sum, item) => sum + Number(item.population || 0), 0);

    const option = {
        ...baseOption(totalPop),
        legend: { data: ['Populasi Target', 'Responden Mengisi'], bottom: 0 },
        grid: { left: 16, right: 22, top: 20, bottom: 64, containLabel: true },
        xAxis: { type: 'category', data: series.map(seriesLabel), axisLabel: { rotate: series.length > 5 ? 26 : 0, width: 110, overflow: 'truncate' } },
        yAxis: { type: 'value', name: 'Jumlah Alumni', splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } } },
        tooltip: { ...baseOption().tooltip, trigger: 'axis', axisPointer: { type: 'shadow' } },
        series: [
            { name: 'Populasi Target', type: 'bar', barMaxWidth: 28, itemStyle: { color: '#94A3B8' }, data: series.map((item) => Number(item.population || 0)) },
            { name: 'Responden Mengisi', type: 'bar', barMaxWidth: 28, itemStyle: { color: '#0D542B' }, data: series.map((item) => Number(item.respondents || 0)) },
        ],
    };

    const tableData = {
        columns: [
            { key: 'name', label: 'Program Studi' },
            { key: 'population', label: 'Populasi Kohort', align: 'right' },
            { key: 'respondents', label: 'Responden', align: 'right' },
            { key: 'rate', label: 'Response Rate', align: 'right' },
        ],
        rows: series.map((item) => {
            const pop = Number(item.population || 0);
            const resp = Number(item.respondents || 0);
            const rate = pop > 0 ? ((resp / pop) * 100).toFixed(1) : '0';
            return {
                name: seriesLabel(item),
                population: formatNumber(pop, 0),
                respondents: formatNumber(resp, 0),
                rate: `${formatNumber(rate)}%`,
            };
        }),
    };

    return { option, tableData };
};

/**
 * Factory pembangun definisi visualisasi dan data pendukung untuk 16 indikator analitik.
 */
export const buildAnalyticsChartDefinitions = ({ chart, selectedChart, selectedLabel = '', heatmapMode = 'count' }) => {
    const series = chart?.series || [];
    const records = chart?.records || [];
    const validResponses = chart?.summary?.answered_count || chart?.summary?.response_count || records.length;

    // Helper pembungkus kartu standar
    const genericBar = () => {
        const { option, tableData } = horizontalBar(series, chart.type, selectedLabel, validResponses, 'Jumlah', '#0D542B');
        return {
            key: 'distribution',
            title: 'Distribusi utama',
            description: 'Pilih baris pada grafik atau tabel untuk menyaring visual terkait dan melihat detail responden.',
            option,
            tableData,
            rows: records,
            height: Math.max(340, Math.min(series.length * 38 + 90, 640)),
        };
    };

    const genericHeatmap = () => {
        const { option, tableData } = heatmap(records, selectedLabel, heatmapMode);
        return {
            key: 'heatmap',
            title: 'Pola antarprogram studi',
            description: heatmapMode === 'percentage'
                ? 'Intensitas dan angka sel menunjukkan persentase kategori dalam setiap program studi.'
                : 'Intensitas dan angka sel menunjukkan jumlah record pada setiap kategori dan program studi.',
            option,
            tableData,
            rows: records,
            height: Math.max(360, Math.min(new Set(records.map((item) => item.nama_prodi)).size * 34 + 140, 580)),
        };
    };

    const genericStacked = () => {
        const { option, tableData } = stackedByProdi(records, selectedLabel);
        return {
            key: 'stacked',
            title: 'Komposisi per program studi',
            description: 'Setiap batang dinormalisasi menjadi 100% agar prodi dengan jumlah responden berbeda tetap dapat dibandingkan.',
            option,
            tableData,
            rows: records,
            height: Math.max(340, Math.min(new Set(records.map((item) => item.nama_prodi)).size * 34 + 110, 560)),
        };
    };

    const genericDonut = () => {
        const { option, tableData } = donut(series, selectedLabel, validResponses);
        return {
            key: 'donut',
            title: 'Proporsi universitas',
            description: 'Menampilkan distribusi kategori proporsi keseluruhan universitas.',
            option,
            tableData,
            rows: records,
            height: 380,
        };
    };

    const genericTreemap = () => {
        const { option, tableData } = treemap(series, selectedLabel, validResponses);
        return {
            key: 'treemap',
            title: 'Komposisi kategori',
            description: 'Luas petak mengikuti jumlah jawaban aktual; klik petak untuk memilih kategori.',
            option,
            tableData,
            rows: records,
            height: 380,
        };
    };

    const genericSankey = () => {
        const { option, tableData } = sankey(records, selectedLabel);
        return {
            key: 'sankey',
            title: 'Aliran program studi ke kategori',
            description: 'Aliran hubungan program studi dan kategori jawaban aktual.',
            option,
            tableData,
            rows: records,
            height: 420,
        };
    };

    // Kamus definisi chart per indikator spesifik
    const definitions = {
        response_rate: [
            (() => {
                const { option, tableData } = responseGroupedBar(series);
                return { key: 'grouped', title: 'Populasi dan responden per program studi', description: 'Perbandingan jumlah alumni kohort dengan responden pengisi kuesioner universitas.', option, tableData, rows: records, height: 400 };
            })(),
            (() => {
                const { option, tableData } = horizontalBar(series, 'response_rate', selectedLabel, chart.summary?.population || records.length, 'Response rate (%)', '#0D542B');
                return { key: 'rate', title: 'Response rate per program studi', description: 'Urutan capaian persentase partisipasi per program studi.', option, tableData, rows: records, height: 400 };
            })(),
            genericHeatmap(),
        ],
        learning_methods: [
            (() => {
                const { option, tableData } = methodsLine(series, validResponses);
                return { key: 'methods_line', title: 'Penekanan metode pembelajaran (median)', description: 'Tren nilai median penekanan metode belajar (mengadopsi Gambar 4.3 Laporan Tracer Study).', option, tableData, rows: records, height: 380 };
            })(),
            (() => {
                const { option, tableData } = horizontalBar(series, 'learning_methods', selectedLabel, validResponses, 'Median skor', '#0D542B');
                return { key: 'likert', title: 'Perbandingan median metode', description: 'Metode diurutkan dari skor paling ditekankan ke paling kurang ditekankan dengan 1 warna konsisten.', option, tableData, rows: records, height: 380 };
            })(),
            genericHeatmap(),
        ],
        job_search_start: [
            (() => {
                const { option, tableData } = diverging(series, selectedLabel, validResponses);
                return { key: 'diverging', title: 'Posisi waktu terhadap kelulusan', description: 'Kategori sebelum lulus ditempatkan di kiri dan setelah lulus di kanan.', option, tableData, rows: records, height: 360 };
            })(),
            genericBar(),
            genericHeatmap(),
        ],
        job_search_methods: [genericBar(), genericTreemap(), genericHeatmap()],
        wait_time: [
            (() => {
                const { option, tableData } = prodiMetricLine(records, 'months', 'Median Waktu Tunggu', 'Median (Bulan)');
                return { key: 'prodi_line', title: 'Waktu memperoleh pekerjaan per program studi', description: 'Median bulan waktu tunggu alumni per program studi dengan ukuran sampel n (mengadopsi Gambar 4.6 Laporan Tracer Study).', option, tableData, rows: records, height: 390 };
            })(),
            (() => {
                const { option, tableData } = waitHistogram(records, chart.summary?.median_months || 0, selectedLabel);
                return { key: 'histogram', title: 'Distribusi waktu tunggu', description: 'Frekuensi alumni pada interval bulan aktual dengan garis referensi median.', option, tableData, rows: records, height: 380 };
            })(),
            (() => {
                const { option, tableData } = boxplot(records, 'months');
                return { key: 'boxplot', title: 'Box plot sebaran waktu tunggu per program studi', description: 'Whisker menggunakan 1,5 × IQR; titik di luar batas adalah outlier.', option, tableData, rows: records, height: 410 };
            })(),
            (() => {
                const { option, tableData } = cumulativeWait(records);
                return { key: 'cumulative', title: 'Kurva kumulatif memperoleh pekerjaan', description: 'Persentase kumulatif alumni yang memperoleh pekerjaan dari bulan ke bulan.', option, tableData, rows: records, height: 380 };
            })(),
        ],
        application_pipeline: [
            (() => {
                const { option, tableData } = prodiPipelineLine(records);
                return { key: 'pipeline_line', title: 'Jumlah perusahaan dilamar, merespon, dan wawancara per program studi', description: 'Perbandingan median tiga tahapan lamaran per prodi (mengadopsi Gambar 4.7 Laporan Tracer Study).', option, tableData, rows: records, height: 410 };
            })(),
            (() => {
                const { option, tableData } = horizontalBar(series, 'application_pipeline', selectedLabel, validResponses, 'Median tahapan', '#0D542B');
                return { key: 'stages', title: 'Median tahapan lamaran universitas', description: 'Perbandingan median lamaran, respons, dan wawancara universitas.', option, tableData, rows: records, height: 330 };
            })(),
            (() => {
                const { option, tableData } = funnel(series);
                return { key: 'funnel', title: 'Funnel proses lamaran', description: 'Penyusutan dari jumlah lamaran menuju panggilan wawancara.', option, tableData, rows: records, height: 380 };
            })(),
            (() => {
                const { option, tableData } = pipelineScatter(records);
                return { key: 'scatter', title: 'Hubungan lamaran dan respons individu', description: 'Setiap titik adalah alumni dengan pasangan jawaban F6 dan F7.', option, tableData, rows: records, height: 390 };
            })(),
        ],
        employment_status: [genericDonut(), genericStacked(), genericSankey()],
        job_search_activity: [genericDonut(), genericStacked(), genericHeatmap()],
        workplace_type: [genericTreemap(), genericDonut(), genericStacked()],
        further_study: [genericSankey(), genericStacked(), genericTreemap()],
        salary_vs_ump: [
            (() => {
                const { option, tableData } = prodiMetricLine(records, 'salary', 'Median Gaji', 'Rupiah', true);
                return { key: 'prodi_salary_line', title: 'Pendapatan per bulan per program studi', description: 'Median gaji alumni per program studi dalam rupiah (mengadopsi Gambar 4.12 Laporan Tracer Study).', option, tableData, rows: records, height: 410 };
            })(),
            (() => {
                const { option, tableData } = boxplot(records, 'salary', true);
                return { key: 'boxplot', title: 'Sebaran gaji per program studi', description: 'Median, kuartil, whisker 1,5 × IQR, dan outlier gaji alumni.', option, tableData, rows: records, height: 430 };
            })(),
            (() => {
                const { option, tableData } = bullet(chart.summary?.meeting_count || 0, chart.summary?.below_count || 0, 'Memenuhi 1,2× UMP', 'Belum memenuhi');
                return { key: 'bullet', title: 'Pemenuhan ambang 1,2 × UMP', description: 'Proporsi alumni yang gajinya memenuhi vs belum memenuhi ambang provinsi.', option, tableData, rows: records, height: 280 };
            })(),
            (() => {
                const { option, tableData } = salaryScatter(records);
                return { key: 'scatter', title: 'Gaji aktual terhadap ambang provinsi', description: 'Sebaran alumni terhadap ambang provinsi kerja.', option, tableData, rows: records, height: 420 };
            })(),
        ],
        field_alignment: [genericStacked(), genericHeatmap(), genericSankey()],
        education_alignment: [
            genericStacked(),
            genericHeatmap(),
            (() => {
                const { option, tableData } = horizontalBar(series, chart.type, selectedLabel, validResponses, 'Jumlah', '#0D542B');
                return { key: 'rate', title: 'Distribusi keselarasan pendidikan', description: 'Perbandingan proporsi keselarasan pendidikan alumni.', option, tableData, rows: records, height: 380 };
            })(),
        ],
        education_alignment_university: [genericDonut(), genericStacked(), genericHeatmap()],
        mismatch_reasons: [genericBar(), genericTreemap(), genericHeatmap()],
        competency_gap: [
            (() => {
                const { option, tableData } = competencyLine(series, validResponses);
                return { key: 'competency_line', title: 'Perbandingan kompetensi saat lulus dan yang diperlukan (Means)', description: 'Grafik garis perbandingan skor penguasaan saat lulus vs kebutuhan pekerjaan (mengadopsi Gambar 4.17 Laporan Tracer Study).', option, tableData, rows: records, height: 420 };
            })(),
            (() => {
                const { option, tableData } = dumbbell(series);
                return { key: 'dumbbell', title: 'Rentang gap kompetensi', description: 'Dua titik dan garis rentang memperlihatkan selisih kompetensi pada setiap dimensi.', option, tableData, rows: records, height: Math.max(360, series.length * 42 + 90) };
            })(),
            (() => {
                const { option, tableData } = radar(series, true);
                return { key: 'radar', title: 'Profil radar kompetensi', description: 'Visualisasi jaring laba-laba untuk perbandingan dimensi pada skala 1-5.', option, tableData, rows: records, height: 420 };
            })(),
            genericHeatmap(),
        ],
        profile_completion: [genericDonut(), genericStacked(), genericHeatmap()],
        work_location: [genericBar(), genericTreemap(), genericHeatmap()],
    };

    return definitions[selectedChart] || [genericBar(), genericHeatmap()];
};
