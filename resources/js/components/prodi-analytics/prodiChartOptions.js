/**
 * Opsi ECharts bersama untuk grafik Analitik Program Studi.
 *
 * File ini hanya berisi fungsi pembentuk opsi dan ukuran grafik. Logika
 * pemilihan bagian, tab, dan event klik tetap berada di masing-masing file
 * `categories/*.vue` agar mudah dibaca saat live coding.
 */

// Memecah label panjang agar sumbu Y tidak bertumpuk pada grafik horizontal dan tidak terpotong (...).
export const wrapChartLabel = (label, maxLength = 36) => {
    const lines = [];
    let current = '';

    String(label || '').split(/\s+/).forEach((word) => {
        if ((`${current} ${word}`).trim().length > maxLength && current) {
            lines.push(current);
            current = word;
        } else {
            current = `${current} ${word}`.trim();
        }
    });

    if (current) {
        lines.push(current);
    }

    return lines.join('\n');
};

const escapeHtml = (text) => String(text ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

// Skala warna Likert 1-5 selaras dengan palet institusional UKDW dan indikator atasan
export const prodiLikertLevels = [
    { level: 1, label: 'Sangat Tidak Setuju', color: '#B91C1C', textColor: '#FFFFFF' },
    { level: 2, label: 'Tidak Setuju', color: '#EA580C', textColor: '#FFFFFF' },
    { level: 3, label: 'Netral', color: '#FACC15', textColor: '#1E293B' },
    { level: 4, label: 'Setuju', color: '#2F7D4A', textColor: '#FFFFFF' },
    { level: 5, label: 'Sangat Setuju', color: '#0D542B', textColor: '#FFFFFF' },
];

// Menghitung tinggi grafik Likert dari jumlah butir pertanyaan.
export const likertChartHeight = (items) => Math.max(460, (items || []).reduce((total, item) => {
    const labelLines = wrapChartLabel(item.title, 36).split('\n').length;
    return total + Math.max(72, labelLines * 16 + 32);
}, 110));

// Menghitung tinggi grafik pilihan dari jumlah kategori jawaban aktif.
export const choiceChartHeight = (item) => Math.max(280, (item?.categories?.length || 1) * 46 + 100);

// Membuat opsi grafik Horizontal Stacked Bar Skala Likert 1-5 dengan rata-rata skor bulat (INT).
export const buildLikertOption = (items) => {
    const aspectNames = (items || []).map((item) => wrapChartLabel(item.title, 36));

    const seriesList = prodiLikertLevels.map((scale) => {
        const data = (items || []).map((item) => {
            const dist = item.stat?.distribution || {};
            const count = Number(dist[scale.level] ?? 0);
            const total = item.stat?.valid_count > 0 ? item.stat.valid_count : 0;
            const percentage = total > 0 ? Number(((count / total) * 100).toFixed(1)) : 0;

            return {
                value: count,
                count,
                total,
                percentage,
                aspek: item.title,
                kode: item.key,
                scaleLevel: scale.level,
                scaleLabel: scale.label,
                avg: Math.round(Number(item.stat?.average || 0)),
                itemStyle: {
                    color: scale.color,
                    borderColor: '#FFFFFF',
                    borderWidth: 1.2,
                },
            };
        });

        return {
            name: `${scale.level} - ${scale.label}`,
            type: 'bar',
            stack: 'likert',
            barMaxWidth: 26,
            itemStyle: {
                color: scale.color,
            },
            label: {
                show: true,
                position: 'inside',
                formatter: (param) => {
                    const cnt = Number(param.value ?? 0);
                    if (cnt <= 0) return '';
                    const pct = param.data?.percentage ?? 0;
                    return pct >= 14 ? `${cnt} (${Math.round(pct)}%)` : `${cnt}`;
                },
                color: scale.textColor,
                fontSize: 10,
                fontWeight: 700,
            },
            data,
        };
    });

    // Series transparan di ujung kanan bar untuk menampilkan total responden & skor rata-rata bulat (INT)
    seriesList.push({
        name: 'Total & Rata-rata',
        type: 'bar',
        stack: 'likert',
        barMaxWidth: 26,
        data: (items || []).map((item) => {
            const total = item.stat?.valid_count ?? 0;
            const avg = Math.round(Number(item.stat?.average ?? 0));
            return {
                value: 0,
                total,
                avg,
                aspek: item.title,
                kode: item.key,
            };
        }),
        label: {
            show: true,
            position: 'right',
            distance: 8,
            formatter: (params) => {
                const total = params.data?.total ?? 0;
                const avg = params.data?.avg ?? 0;
                return total > 0 ? `${total} alumni · Rata-rata: ${avg}/5` : '';
            },
            color: '#0F172A',
            fontSize: 11,
            fontWeight: 700,
        },
        itemStyle: {
            color: 'rgba(0, 0, 0, 0)',
        },
        tooltip: {
            show: false,
        },
    });

    return {
        legend: {
            show: true,
            orient: 'horizontal',
            bottom: 4,
            icon: 'rect',
            itemWidth: 12,
            itemHeight: 12,
            itemGap: 14,
            data: [
                '5 - Sangat Setuju',
                '4 - Setuju',
                '3 - Netral',
                '2 - Tidak Setuju',
                '1 - Sangat Tidak Setuju',
            ],
            textStyle: { color: '#334155', fontSize: 11, fontWeight: 600 },
        },
        grid: { left: 20, right: 195, top: 16, bottom: 48, containLabel: true },
        xAxis: {
            type: 'value',
            min: 0,
            minInterval: 1,
            name: 'Jumlah alumni (n)',
            nameLocation: 'middle',
            nameGap: 24,
            axisLabel: {
                formatter: (val) => Math.round(val),
                color: '#64748B',
                fontSize: 11,
            },
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
        },
        yAxis: {
            type: 'category',
            inverse: true,
            data: aspectNames,
            axisTick: { show: false },
            axisLine: { lineStyle: { color: '#CBD5E1' } },
            axisLabel: {
                color: '#1E293B',
                fontSize: 11,
                fontWeight: 600,
                width: 280,
                overflow: 'break',
                lineHeight: 14,
            },
        },
        tooltip: {
            trigger: 'item',
            backgroundColor: '#FFFFFF',
            borderColor: '#CBD5E1',
            textStyle: { color: '#0F172A', fontSize: 12 },
            formatter: (params) => {
                const d = params.data || {};
                if (!d.aspek || d.scaleLevel === undefined) return '';
                return `
                    <div style="min-width: 210px; font-family: inherit;">
                        <div style="font-weight: 700; color: #0F172A; font-size: 13px; border-bottom: 1px solid #F1F5F9; padding-bottom: 4px; margin-bottom: 6px;">
                            ${escapeHtml(d.aspek)}
                        </div>
                        <div style="font-size: 12px; color: #475569; margin-bottom: 3px;">
                            Skala: <strong style="color: #0F172A;">${escapeHtml(d.scaleLabel)} (${d.scaleLevel})</strong>
                        </div>
                        <div style="font-size: 12px; color: #475569; margin-bottom: 3px;">
                            Jumlah Responden: <strong style="color: #0D542B; font-size: 13px;">${d.count} alumni</strong>
                        </div>
                        <div style="font-size: 11px; color: #64748B; margin-bottom: 3px;">
                            Proporsi: <strong style="color: #334155;">${d.percentage}%</strong> dari total ${d.total} alumni
                        </div>
                        <div style="font-size: 11px; color: #0D542B; font-weight: 600; border-top: 1px dashed #E2E8F0; padding-top: 4px; margin-top: 4px;">
                            Rata-rata Skor Butir: ${d.avg} / 5 (INT)
                        </div>
                    </div>
                `;
            },
        },
        series: seriesList,
    };
};

// ALUR 10: fungsi ini mengubah categories dan values dari satu item menjadi
// konfigurasi ECharts. ECharts kemudian menggambar batang berdasarkan data itu.
export const buildChoiceOption = (item) => ({
    tooltip: {
        trigger: 'axis',
        axisPointer: { type: 'shadow' },
        backgroundColor: '#FFFFFF',
        borderColor: '#CBD5E1',
        textStyle: { color: '#0F172A', fontSize: 12 },
        formatter: (params) => {
            const point = params?.[0];
            const category = item?.stat?.categories?.[point?.dataIndex] || {};

            return `<strong>${point?.name || ''}</strong><br/>Jumlah: <b>${category.count ?? point?.value ?? 0}</b><br/>Proporsi: <b>${category.percentage ?? 0}%</b>`;
        },
    },
    grid: { left: 24, right: 56, top: 20, bottom: 42, containLabel: true },
    xAxis: {
        type: 'value',
        name: 'Jumlah alumni',
        nameLocation: 'middle',
        nameGap: 28,
        min: 0,
        minInterval: 1,
        interval: Math.max(...(item?.values || []).map((value) => Number(value) || 0), 0) <= 10 ? 1 : undefined,
        axisLabel: {
            color: '#475569',
            fontSize: 11,
            formatter: (value) => Number.isInteger(Number(value)) ? String(value) : '',
        },
        splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
    },
    yAxis: {
        type: 'category',
        inverse: true,
        data: item?.categories || [],
        axisLabel: { color: '#334155', fontSize: 11, width: 240, overflow: 'break' },
        axisLine: { lineStyle: { color: '#CBD5E1' } },
    },
    series: [{
        name: 'Jumlah alumni',
        type: 'bar',
        barMaxWidth: 30,
        data: (item?.values || []).map((value) => Number(value) || 0),
        itemStyle: { color: '#0D542B', borderRadius: [0, 4, 4, 0] },
        label: {
            show: true,
            position: 'right',
            color: '#334155',
            fontWeight: 'bold',
            // Grafik menampilkan jumlah absolut; persentase tetap tersedia saat hover.
            formatter: (params) => `${params.value}`,
        },
    }],
});

// Menyusun analisis pilihan dominan dari frekuensi aktual dan menangani seri.
export const choiceAnalysis = (item) => {
    const categories = item?.stat?.categories || [];
    const highestCount = Math.max(...categories.map((category) => Number(category.count || 0)), 0);
    const leadingCategories = categories.filter((category) => Number(category.count || 0) === highestCount);
    const validCount = Number(item?.stat?.valid_count || 0);
    const totalSelections = categories.reduce((total, category) => total + Number(category.count || 0), 0);
    const lowestCategory = [...categories]
        .filter((category) => Number(category.count || 0) > 0)
        .sort((left, right) => Number(left.count || 0) - Number(right.count || 0))[0];

    if (!item || highestCount === 0) {
        return null;
    }

    const labels = leadingCategories.map((category) => `"${category.label}"`).join(', ');

    return {
        lead: leadingCategories.length > 1
            ? `Belum ada satu pilihan dominan: ${labels} masing-masing dipilih ${highestCount} alumni.`
            : `Pilihan dominan adalah ${labels} dengan ${highestCount} jawaban (${leadingCategories[0].percentage}%).`,
        detail: `${validCount} jawaban valid membentuk ${totalSelections} pilihan tercatat pada pertanyaan ini.${lowestCategory && lowestCategory.label !== leadingCategories[0]?.label ? ` Pilihan dengan frekuensi terendah adalah "${lowestCategory.label}" (${lowestCategory.count} jawaban).` : ''} Persentase lengkap tersedia saat grafik diarahkan dengan kursor.`,
    };
};
