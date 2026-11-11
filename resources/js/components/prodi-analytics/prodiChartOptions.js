/**
 * Opsi ECharts bersama untuk grafik Analitik Program Studi.
 *
 * File ini hanya berisi fungsi pembentuk opsi dan ukuran grafik. Logika
 * pemilihan bagian, tab, dan event klik tetap berada di masing-masing file
 * `categories/*.vue` agar mudah dibaca saat live coding.
 */

// Memecah label panjang agar sumbu Y tidak bertumpuk pada grafik horizontal.
export const wrapChartLabel = (label, maxLength = 48) => {
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

// Menghitung tinggi grafik Likert dari jumlah baris label setiap pernyataan.
export const likertChartHeight = (items) => Math.max(460, items.reduce((total, item) => {
    const labelLines = wrapChartLabel(item.title).split('\n').length;
    return total + Math.max(76, labelLines * 18 + 28);
}, 72));

// Menghitung tinggi grafik pilihan dari jumlah kategori jawaban aktif.
export const choiceChartHeight = (item) => Math.max(280, (item?.categories?.length || 1) * 46 + 100);

// Membuat opsi grafik rata-rata skor Likert dengan garis acuan skor 3.
export const buildLikertOption = (items) => ({
    tooltip: {
        trigger: 'axis',
        axisPointer: { type: 'shadow' },
        backgroundColor: '#FFFFFF',
        borderColor: '#CBD5E1',
        textStyle: { color: '#0F172A', fontSize: 12 },
        formatter: (params) => {
            const point = params?.[0];
            const item = items[point?.dataIndex] || {};
            const stat = item.stat || {};

            return `<strong>${item.title || point?.name || ''}</strong><br/>Skor: <b>${stat.average ?? 0}</b> / 5<br/>Median: <b>${stat.median ?? 0}</b><br/>Jawaban valid: ${stat.valid_count ?? 0}`;
        },
    },
    grid: { left: 20, right: 44, top: 20, bottom: 36, containLabel: true },
    xAxis: {
        type: 'value',
        min: 0,
        max: 5,
        interval: 1,
        name: 'Skor (1-5)',
        nameLocation: 'middle',
        nameGap: 28,
        axisLabel: { color: '#475569', fontSize: 11 },
        splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
    },
    yAxis: {
        type: 'category',
        inverse: true,
        data: items.map((item) => wrapChartLabel(item.title)),
        axisLabel: { color: '#334155', fontSize: 11, lineHeight: 16, width: 260, overflow: 'break' },
        axisLine: { lineStyle: { color: '#CBD5E1' } },
    },
    series: [{
        name: 'Skor penilaian',
        type: 'bar',
        barMaxWidth: 28,
        data: items.map((item) => item.stat?.average || 0),
        itemStyle: { color: '#0D542B', borderRadius: [0, 4, 4, 0] },
        label: { show: true, position: 'right', color: '#334155', fontWeight: 'bold', formatter: '{c}' },
        markLine: { silent: true, symbol: 'none', lineStyle: { color: '#64748B', type: 'dashed' }, data: [{ xAxis: 3 }] },
    }],
});

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
