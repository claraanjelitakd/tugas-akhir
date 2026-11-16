<script setup>
/**
 * Komponen Indikator 09: Jenis Tempat Bekerja (Instrumen F11).
 *
 * Mengelola visualisasi sektor/badan usaha tempat alumni bekerja:
 * 1. Horizontal Bar: Proporsi Jenis Instansi Tingkat Universitas
 * 2. Matriks Heatmap: Sebaran Jenis Tempat Kerja per Program Studi (mengadopsi matriks perbandingan prodi Indikator 02)
 * 3. Stacked Bar: Komposisi Sektor per Program Studi (100% horizontal dengan batas ambang >= 10%)
 * Isian teks bebas Lainnya dipisahkan menjadi awan kata agar tidak mencampuri klasifikasi baku F11.
 */
import { computed } from 'vue';
import AnalyticsTabbedContainer from '../AnalyticsTabbedContainer.vue';
import {
    analyticsScalePalette,
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
// Agregat per program studi sudah dihitung backend dan dipakai untuk heatmap serta stacked bar.
const prodiSeries = computed(() => props.chart?.prodi_series || []);
const records = computed(() => props.chart?.records || []);
const validResponses = computed(() => props.chart?.summary?.answered_count || records.value.length);

// Label ringkas mencegah legenda skala bertabrakan dengan area batang.
const workplaceLegendLabel = (label) => ({
    'Perusahaan swasta': 'Perusahaan swasta',
    'Instansi pemerintah': 'Instansi pemerintah',
    'Organisasi non-profit/LSM': 'Organisasi nirlaba/LSM',
    'BUMN/BUMD': 'BUMN/BUMD',
    'Wiraswasta/perusahaan sendiri': 'Wiraswasta',
    'Institusi/Organisasi Multilateral': 'Multilateral',
}[label] || label);

// Legenda kategori vertikal mengikuti tata letak Indikator 02 tanpa memberi makna nilai pada kategori nominal F11.
const workplaceStackLegend = (categories) => {
    const rowHeight = categories.length > 5 ? 36 : 44;
    const totalHeight = categories.length * rowHeight;
    const reversedCategories = [...categories].reverse();
    const children = [{
        type: 'text',
        left: 0,
        top: Math.max(0, (totalHeight / 2) - 8),
        rotation: -Math.PI / 2,
        style: {
            text: 'Jenis tempat kerja',
            fill: '#0F172A',
            font: 'bold 11px Inter, sans-serif',
            textAlign: 'center',
        },
    }];

    reversedCategories.forEach((category, index) => {
        const paletteIndex = categories.indexOf(category) % analyticsScalePalette.length;
        const top = index * rowHeight;
        children.push(
            {
                type: 'rect',
                left: 28,
                top,
                shape: { width: 22, height: rowHeight },
                style: { fill: analyticsScalePalette[paletteIndex], stroke: '#1E293B', lineWidth: 1 },
            },
            {
                type: 'text',
                left: 58,
                top: top + Math.max(10, (rowHeight / 2) - 6),
                style: {
                    text: workplaceLegendLabel(category),
                    fill: '#1E293B',
                    font: '500 11px Inter, sans-serif',
                },
            },
        );
    });

    return [{ type: 'group', right: 18, top: 'middle', children }];
};

// -----------------------------------------------------------------------------
// 1. BAR CHART: Proporsi Jenis Tempat Bekerja Universitas (Horizontal Bar)
// -----------------------------------------------------------------------------
const barOption = computed(() => {
    // Filter hanya kategori yang memiliki respon (>0) agar tampilan bersih
    const activeSeries = [...series.value]
        .filter((item) => seriesMetric(item) > 0)
        .sort((a, b) => seriesMetric(a) - seriesMetric(b)); // Naik agar teratas adalah nilai terbanyak

    const data = activeSeries.map((item) => {
        const label = seriesLabel(item);
        const count = seriesMetric(item);
        const selected = !props.selectedLabel || props.selectedLabel === label;
        return {
            name: label,
            value: count,
            percentage: item.percentage || 0,
            itemStyle: {
                color: '#0D542B',
                opacity: selected ? 1 : 0.22,
                borderRadius: [0, 4, 4, 0],
            },
        };
    });

    const chartHeight = Math.max(340, data.length * 44 + 70);

    return {
        ...baseOption(validResponses.value),
        grid: {
            left: 20,
            right: 110,
            top: 24,
            bottom: 40,
            containLabel: true,
        },
        xAxis: {
            type: 'value',
            name: 'Jumlah Alumni',
            nameLocation: 'middle',
            nameGap: 28,
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
        },
        yAxis: {
            type: 'category',
            data: data.map((d) => d.name),
            axisLabel: {
                width: 260,
                overflow: 'break',
                fontSize: 11,
                lineHeight: 14,
                color: '#334155',
            },
        },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            formatter: (params) => {
                const item = data[params[0]?.dataIndex];
                if (!item) return '';
                return `<strong>${escapeHtml(item.name)}</strong><br>Jumlah: <strong>${formatNumber(item.value, 0)} alumni</strong> (${formatPercentage(item.percentage)})`;
            },
        },
        series: [{
            name: 'Jumlah Alumni',
            type: 'bar',
            barMaxWidth: 22,
            data,
            label: {
                show: true,
                position: 'right',
                distance: 8,
                formatter: ({ data }) => `${formatNumber(data.value, 0)} (${formatPercentage(data.percentage)})`,
                fontSize: 11,
                fontWeight: 700,
                color: '#0F172A',
            },
            labelLayout: {
                hideOverlap: false,
            },
        }],
        _dynamicHeight: chartHeight,
    };
});

const barTable = computed(() => ({
    columns: [
        { key: 'name', label: 'Jenis Perusahaan / Instansi (F11)' },
        { key: 'count', label: 'Jumlah Alumni (n)', align: 'right' },
        { key: 'percentage', label: 'Persentase', align: 'right' },
    ],
    rows: series.value.map((item) => ({
            name: seriesLabel(item),
            count: formatNumber(seriesMetric(item), 0),
            percentage: formatPercentage(item.percentage || 0),
        })),
}));

// Isian Lainnya dapat berasal dari teks jawaban F11 atau metadata perusahaan yang dipilih alumni.
const lainnyaRecords = computed(() => records.value.flatMap((record) => {
    const category = String(record.category || record.answer || '').trim().toLowerCase();
    const detail = String(record.detail || record.jenis_perusahaan_lainnya || '').trim();
    const hasValidDetail = detail !== '' && detail !== '-' && !['lainnya', 'other'].includes(detail.toLowerCase());

    if (!['lainnya', 'other'].includes(category) || !hasValidDetail) {
        return [];
    }

    return [{ ...record, other_detail: detail }];
}));

const lainnyaTable = computed(() => ({
    columns: [
        { key: 'nim', label: 'NIM' },
        { key: 'nama', label: 'Nama Alumni' },
        { key: 'nama_prodi', label: 'Program Studi' },
        { key: 'nama_perusahaan', label: 'Perusahaan / Instansi' },
        { key: 'answer', label: 'Jenis Tempat Kerja Lainnya' },
    ],
    rows: lainnyaRecords.value.map((record) => ({
        nim: record.nim || '-',
        nama: record.nama || '-',
        nama_prodi: record.nama_prodi || '-',
        nama_perusahaan: record.nama_perusahaan || '-',
        answer: record.other_detail,
    })),
}));

// Kontrak AnalyticsWordCloud membaca field answer; detail master perusahaan diproyeksikan tanpa mengubah record asli.
const lainnyaWordCloudRecords = computed(() => lainnyaRecords.value.map((record) => ({
    ...record,
    answer: record.other_detail,
})));

// -----------------------------------------------------------------------------
// 2. MATRIKS HEATMAP: Sebaran Sektor per Program Studi (Mengadopsi Indikator 02)
// -----------------------------------------------------------------------------
const prodis = computed(() => prodiSeries.value.length > 0
    ? prodiSeries.value.map((item) => item.prodi)
    : [...new Set(records.value.map((r) => r.nama_prodi || 'Tanpa prodi'))].sort());

// Palet diskret sama dengan matriks Perbandingan Prodi Indikator 02: rendah merah, tinggi hijau tua.
const workplaceHeatmapPalette = ['#B91C1C', '#EF4444', '#E2F3E7', '#2F7D4A', '#0D542B'];
const categories = computed(() => {
    // Ambil hanya kategori yang aktif di dataset
    const activeCats = series.value.filter((s) => seriesMetric(s) > 0).map((s) => s.label);
    return activeCats.length > 0 ? activeCats : [...new Set(series.value.map((s) => s.label))];
});

const heatmapData = computed(() => {
    const listProdis = prodis.value;
    const cats = categories.value;
    if (prodiSeries.value.length > 0) {
        const prodiTotals = Object.fromEntries(prodiSeries.value.map((item) => [item.prodi, item.total]));
        const rawValues = listProdis.flatMap((prodi, yIndex) => {
            const backendProdi = prodiSeries.value.find((item) => item.prodi === prodi);

            return cats.map((cat, xIndex) => {
                const backendItem = backendProdi?.items?.find((item) => item.label === cat);
                const count = backendItem?.count || 0;

                return {
                    xIndex,
                    yIndex,
                    percentage: backendItem?.percentage || 0,
                    prodi,
                    category: cat,
                    count,
                    total: backendProdi?.total || 0,
                };
            });
        });
        const maxPct = Math.max(...rawValues.map((value) => value.percentage), 1);
        const values = rawValues.map((value) => ({
            ...value,
            value: [
                value.xIndex,
                value.yIndex,
                Math.max(1, Math.min(5, Math.round((value.percentage / maxPct) * 4) + 1)),
            ],
        }));

        return { values, maxPct, prodiTotals };
    }

    const prodiTotals = Object.fromEntries(
        listProdis.map((p) => [p, records.value.filter((r) => (r.nama_prodi || 'Tanpa prodi') === p).length]),
    );

    const rawValues = [];
    listProdis.forEach((prodi, yIndex) => {
        cats.forEach((cat, xIndex) => {
            const count = records.value.filter((r) => (r.nama_prodi || 'Tanpa prodi') === prodi && recordCategory(r) === cat).length;
            const total = Math.max(prodiTotals[prodi], 1);
            const pct = Number(((count / total) * 100).toFixed(1));
            rawValues.push({
                xIndex,
                yIndex,
                percentage: pct,
                prodi,
                category: cat,
                count,
                total,
            });
        });
    });

    const maxPct = Math.max(...rawValues.map((value) => value.percentage), 1);
    const values = rawValues.map((value) => ({
        ...value,
        // Tingkat 1–5 hanya mengendalikan intensitas visual relatif; persentase asli tetap dipakai pada tooltip dan tabel.
        value: [
            value.xIndex,
            value.yIndex,
            Math.max(1, Math.min(5, Math.round((value.percentage / maxPct) * 4) + 1)),
        ],
    }));

    return { values, maxPct, prodiTotals };
});

const heatmapOption = computed(() => {
    const { values, prodiTotals } = heatmapData.value;
    const cats = categories.value;
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
            axisLabel: { color: '#1E293B', fontSize: 11, fontWeight: 500 },
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
                color: workplaceHeatmapPalette,
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
                        <div class="text-slate-600 mb-0.5"><span class="text-slate-500">Instansi:</span> <strong>${escapeHtml(data.category)}</strong></div>
                        <div class="text-slate-600 mb-0.5"><span class="text-slate-500">Jumlah Alumni:</span> <strong>${formatNumber(data.count, 0)}</strong> (dari n=${data.total})</div>
                        <div class="text-slate-600"><span class="text-slate-500">Proporsi di Prodi:</span> <strong class="text-emerald-700 font-mono text-sm">${formatNumber(data.percentage, 1)}%</strong></div>
                    </div>
                `;
            },
        },
        series: [{
            name: 'Proporsi Tempat Bekerja',
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
                    formatter: ({ data }) => (data?.percentage > 0 ? `${formatNumber(data.percentage, 0)}%` : '-'),
                    fontSize: 12,
                    fontWeight: 800,
                },
            },
        }],
    };
});

const heatmapTable = computed(() => {
    const cats = categories.value;
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
            const backendItem = prodiSeries.value.find((item) => item.prodi === prodi)?.items?.find((item) => item.label === cat);
            const count = backendItem?.count ?? records.value.filter((r) => (r.nama_prodi || 'Tanpa prodi') === prodi && recordCategory(r) === cat).length;
            const total = Math.max(prodiTotals[prodi], 1);
            const pct = backendItem?.percentage ?? ((count / total) * 100).toFixed(1);
            row[`cat_${i}`] = `${formatNumber(count, 0)} (${pct}%)`;
        });
        return row;
    });

    return { columns, rows };
});

// -----------------------------------------------------------------------------
// 3. STACKED BAR: Komposisi Sektor per Program Studi (Lengkap dengan Label %)
// -----------------------------------------------------------------------------
const stackedOption = computed(() => {
    const listProdis = prodis.value;
    const cats = categories.value;
    const prodiTotals = Object.fromEntries(prodiSeries.value.length > 0
        ? prodiSeries.value.map((item) => [item.prodi, item.total])
        : listProdis.map((p) => [p, records.value.filter((r) => (r.nama_prodi || 'Tanpa prodi') === p).length]));

    const seriesList = cats.map((cat) => {
        const paletteIndex = cats.indexOf(cat) % analyticsScalePalette.length;
        const catData = listProdis.map((prodi) => {
            const backendItem = prodiSeries.value.find((item) => item.prodi === prodi)?.items?.find((item) => item.label === cat);
            const count = backendItem?.count ?? records.value.filter((r) => (r.nama_prodi || 'Tanpa prodi') === prodi && recordCategory(r) === cat).length;
            const total = Math.max(prodiTotals[prodi], 1);
            const pct = backendItem?.percentage ?? Number(((count / total) * 100).toFixed(1));
            return {
                value: pct,
                count,
                total,
                prodi,
                category: cat,
            };
        });

        const selected = !props.selectedLabel || props.selectedLabel === cat;

        return {
            name: cat,
            type: 'bar',
            stack: 'total',
            stackOrder: paletteIndex + 1,
            barMaxWidth: 26,
            label: {
                show: true,
                position: 'inside',
                formatter: (params) => params.value >= 2 ? `${formatNumber(params.value, 1)}%` : '',
                fontSize: 10,
                color: paletteIndex === 2 ? '#1E293B' : '#FFFFFF',
                fontWeight: 'bold',
            },
            itemStyle: {
                color: analyticsScalePalette[paletteIndex],
                opacity: selected ? 1 : 0.22,
                borderColor: '#FFFFFF',
                borderWidth: 1,
            },
            data: catData,
        };
    });

    const labels = listProdis.map((p) => `${p} (n=${prodiTotals[p]})`);

    return {
        ...baseOption(records.value.length),
        // Palet dipasangkan langsung dengan urutan stack agar konsisten ketika salah satu kategori tersaring.
        themePalette: cats.map((_, index) => analyticsScalePalette[index % analyticsScalePalette.length]),
        legend: {
            show: false,
        },
        grid: { left: 24, right: 244, top: 24, bottom: 32, containLabel: true },
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
            axisLabel: { color: '#334155', fontSize: 11, width: 200, overflow: 'break' },
        },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'item',
            formatter: ({ data }) => {
                if (!data) return '';
                return `<strong>${escapeHtml(data.prodi)}</strong><br>Instansi: <strong>${escapeHtml(data.category)}</strong><br>Jumlah: <strong>${data.count} alumni</strong> (dari total n=${data.total})<br>Proporsi: <strong>${data.value}%</strong>`;
            },
        },
        graphic: workplaceStackLegend(cats),
        series: seriesList,
    };
});

const stackedTable = computed(() => ({
    columns: [
        { key: 'prodi', label: 'Program Studi' },
        { key: 'total', label: 'Total Alumni (n)', align: 'right' },
        { key: 'summary', label: 'Rincian Sektor Terbanyak', align: 'left' },
    ],
    rows: prodiSeries.value.length > 0
        ? prodiSeries.value.map((item) => {
            return {
                prodi: item.prodi,
                total: formatNumber(item.total, 0),
                summary: item.items.map((status) => `${status.label}: ${status.count} (${formatPercentage(status.percentage)})`).join('; '),
            };
        })
        : prodis.value.map((prodi) => {
        const rows = records.value.filter((r) => (r.nama_prodi || 'Tanpa prodi') === prodi);
        const summary = categories.value
            .map((cat) => ({ cat, count: rows.filter((r) => recordCategory(r) === cat).length }))
            .filter((i) => i.count > 0)
            .map((i) => `${i.cat}: ${i.count}`)
            .join(' | ');

        return {
            prodi,
            total: formatNumber(rows.length, 0),
            summary: summary || '-',
        };
    }),
}));

// Konfigurasi views untuk kontainer terpadu
const views = computed(() => {
    const topSector = series.value.find((s) => seriesMetric(s) > 0) || series.value[0];

    const list = [
        {
            key: 'proportion',
            chartType: 'horizontal_bar',
            tabLabel: 'Proporsi Universitas',
            title: 'Distribusi jenis tempat bekerja tingkat universitas',
            description: 'Distribusi alumni bekerja berdasarkan klasifikasi badan usaha atau instansi tempat kerja (Instrumen F11).',
            option: barOption.value,
            tableData: barTable.value,
            height: Math.max(340, series.value.filter((s) => seriesMetric(s) > 0).length * 44 + 70),
            analysis: {
                title: 'Kilas Penyerapan Sektor Kerja Alumni',
                statusBadge: 'Klasifikasi Sektor F11',
                badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
                lead: topSector?.label
                    ? `Sektor dengan penyerapan alumni terbesar adalah "${topSector.label}" dengan jumlah ${topSector.count} alumni (${formatPercentage(topSector.percentage)}).`
                    : 'Data jenis tempat bekerja tersaji lengkap.',
                detail: 'Karakteristik serapan kerja alumni UKDW terdistribusi aktif di berbagai sektor strategis: perusahaan swasta korporasi nasional/multinasional, instansi pemerintah (ASN/kementerian), badan usaha milik negara (BUMN/BUMD), serta organisasi non-profit/LSM dan kewirausahaan mandiri.',
            },
        },
        {
            key: 'by_prodi_heatmap',
            chartType: 'heatmap',
            tabLabel: 'Sebaran per Prodi',
            title: 'Sebaran jenis tempat bekerja per program studi',
            description: 'Matriks intensitas dan proporsi penyerapan alumni pada masing-masing sektor instansi di setiap program studi UKDW (mengadopsi matriks perbandingan prodi Indikator 02).',
            option: heatmapOption.value,
            tableData: heatmapTable.value,
            height: Math.max(460, prodis.value.length * 36 + 120),
            analysis: {
                title: 'Kilas Sebaran Sektor Kerja Antarjurusan',
                statusBadge: `${prodis.value.length} Program Studi`,
                badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
                lead: `Matriks sebaran memperlihatkan konsentrasi penyerapan alumni pada 5 sektor utama di ${prodis.value.length} program studi.`,
                detail: 'Sektor perusahaan swasta mendominasi serapan di hampir seluruh prodi teknologi dan bisnis, sementara lulusan kedokteran dan pendidikan terserap kuat pada instansi pemerintah dan yayasan sosial/pendidikan.',
            },
        },
        {
            key: 'by_prodi_stacked',
            chartType: 'stacked_horizontal_100',
            tabLabel: 'Komposisi Bertumpuk',
            title: 'Komposisi jenis tempat bekerja per program studi',
            description: 'Perbandingan proporsi sektor tempat bekerja alumni pada masing-masing program studi dalam diagram batang bertumpuk 100% horizontal.',
            option: stackedOption.value,
            tableData: stackedTable.value,
            height: Math.max(400, prodis.value.length * 36 + 120),
            analysis: {
                title: 'Kilas Pola Sektor Antarjurusan',
                statusBadge: 'Sebaran Program Studi',
                badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
                lead: `Komposisi sektor kerja bervariasi secara khas sesuai profil kompetensi lulusan di ${prodis.value.length} program studi.`,
                detail: 'Lulusan program studi rumpun teknologi informasi serta ekonomi bisnis menunjukkan konsentrasi serapan tinggi pada sektor swasta dan BUMN, sedangkan Kedokteran dan Pendidikan Bahasa Inggris terserap kuat pada instansi pemerintah dan organisasi nirlaba.',
            },
        },
    ];

    if (lainnyaRecords.value.length > 0) {
        list.push({
            key: 'lainnya_detail',
            chartType: 'word_cloud',
            tabLabel: `Awan Kata Lainnya (${lainnyaRecords.value.length})`,
            title: 'Awan Kata Jenis Tempat Kerja Lainnya',
            description: 'Kata kunci dari klasifikasi perusahaan atau instansi di luar pilihan baku F11.',
            wordCloudTitle: 'Awan Kata Jenis Tempat Kerja Lainnya',
            wordCloudSubtitle: 'Visualisasi hanya dari isian tambahan alumni atau metadata master perusahaan.',
            wordCloudRecords: lainnyaWordCloudRecords.value,
            option: barOption.value,
            tableData: lainnyaTable.value,
            height: 380,
            analysis: {
                title: 'Kilas Isian Bebas F11',
                statusBadge: 'Awan Kata',
                badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
                lead: `${lainnyaRecords.value.length} alumni memiliki klasifikasi tempat kerja di luar opsi baku F11.`,
                detail: 'Awan kata membantu membaca variasi klasifikasi tersebut tanpa menjadikannya kategori pembanding pada grafik utama.',
            },
        });
    }

    return list;
});
</script>

<template>
    <AnalyticsTabbedContainer
        :views="views"
        :rows="records"
        :selected-label="selectedLabel"
        :loading="loading"
        indicator-badge="Indikator 12"
        @select="emit('select', $event)"
        @reset="emit('reset')"
    />
</template>
