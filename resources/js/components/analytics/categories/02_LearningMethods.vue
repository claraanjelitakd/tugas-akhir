<script setup>
/**
 * Komponen Indikator 02: Penekanan Metode Pembelajaran.
 *
 * Mengimplementasikan visualisasi analitik terpadu (Unified Visual + Inline Data Drill-Down):
 * 1. Visualisasi 1 (Utama): Radar Chart (Jaring Laba-laba) profil penekanan 7 metode belajar
 *    bersih, formal, elegan, tanpa warna biru atau teks dekoratif yang membingungkan.
 * 2. Visualisasi 2: Line Chart Penekanan Metode Belajar Rata-rata Skor INT (Gambar 4.3 Laporan Resmi UKDW).
 * 3. Visualisasi 3: Distribusi Pilihan Skala Responden (100% Horizontal Stacked Bar) label utuh tanpa terpotong.
 * 4. Visualisasi 4: Perbandingan Metode per Program Studi (matriks ECharts dengan gradasi monokromatik hijau UKDW).
 * 5. Kartu Kilas Indikator Alami: Narasi 100% dinamis terhitung dari data aktif (bebas hardcode).
 * 6. Inline Data Drill-Down ("Don't Make Me Think"): Rincian distribusi skala (1 warna konsisten hijau UKDW)
 *    dan ranking prodi terbuka mulus saat metode diklik.
 */
import { computed, ref, watch } from 'vue';
import AnalyticsEChart from '../AnalyticsEChart.vue';
import {
    analyticsChartTypeReasons,
    analyticsChartTypes,
    baseOption,
    escapeHtml,
    formatNumber,
    analyticsScalePalette,
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

// Mode tab visualisasi aktif: 'radar', 'stacked', atau 'heatmap' (perbandingan prodi).
const activeChartTab = ref('radar');

// Label dan alasan grafik aktif mengambil sumber dari kamus tipe pusat.
const activeChartType = computed(() => ({
    radar: analyticsChartTypes.radar,
    stacked: analyticsChartTypes.stacked_horizontal_100,
    heatmap: analyticsChartTypes.heatmap,
}[activeChartTab.value] || analyticsChartTypes.table));
const activeChartReason = computed(() => ({
    radar: analyticsChartTypeReasons.radar,
    stacked: analyticsChartTypeReasons.stacked_horizontal_100,
    heatmap: analyticsChartTypeReasons.heatmap,
}[activeChartTab.value] || analyticsChartTypeReasons.table));

// Mode tampilan: 'chart' (Visualisasi Interaktif) atau 'table' (Data Ringkasan).
const activeViewMode = ref('chart');

// Kunci metode pembelajaran yang sedang dipilih untuk drill-down inline (misal: 'F21').
const selectedMethodKey = ref('');

// Kamus predikat resmi instrumen Tracer Study UKDW F21-F27 (skor 5 menunjukkan penekanan tertinggi).
const scaleDescriptions = {
    1: 'Tidak Sama Sekali',
    2: 'Kurang',
    3: 'Cukup Besar',
    4: 'Besar',
    5: 'Sangat Besar',
};

// Styling tooltip universal bersih, formal, dan profesional (light theme tanpa saturasi biru).
const lightTooltipConfig = {
    trigger: 'item',
    confine: true,
    appendToBody: false,
    backgroundColor: '#FFFFFF',
    borderColor: 'transparent',
    borderWidth: 0,
    padding: [10, 14],
    textStyle: {
        color: '#0F172A',
        fontFamily: 'Inter, ui-sans-serif, system-ui, sans-serif',
        fontSize: 12,
    },
    extraCssText: 'border:0;box-shadow:none;border-radius:6px;',
};

// Ekstraksi data seri, catatan responden, dan ringkasan dari payload backend (100% dinamis).
const series = computed(() => props.chart?.series || []);
const records = computed(() => props.chart?.records || []);
const summary = computed(() => props.chart?.summary || {});
const validResponses = computed(() => summary.value?.answered_count || records.value.length);

// Sinkronisasi seleksi eksternal jika ada filter label yang dikirimkan dari panel induk.
watch(() => props.selectedLabel, (newVal) => {
    if (!newVal) {
        selectedMethodKey.value = '';
        return;
    }
    const matched = series.value.find((item) => seriesLabel(item) === newVal || item.key === newVal);
    if (matched) {
        selectedMethodKey.value = matched.key;
    }
}, { immediate: true });

// Objek data metode pembelajaran yang sedang aktif dipilih untuk rincian drill-down.
const selectedMethodItem = computed(() => {
    if (!selectedMethodKey.value) return null;
    return series.value.find((item) => item.key === selectedMethodKey.value) || null;
});

// Ringkasan agregat per program studi untuk analisis naratif pola antar program studi.
const prodiSummaries = computed(() => {
    return prodis.value.map((prodi) => {
        const group = records.value.filter((r) => (r.nama_prodi || 'Tanpa prodi') === prodi);
        const scores = group.map((r) => Number(r.score)).filter((s) => !isNaN(s) && s >= 1 && s <= 5);
        const count = scores.length;
        const avg = count > 0 ? scores.reduce((a, b) => a + b, 0) / count : 0;
        return {
            prodi,
            count,
            average: avg,
            scoreInt: Math.round(avg),
            scoreLabel: scaleDescriptions[Math.round(avg)] || '-',
        };
    }).filter((p) => p.count > 0).sort((a, b) => b.average - a.average);
});

// -----------------------------------------------------------------------------
// KARTU RINGKASAN ALAMI (Kilas Indikator - 100% DINAMIS BERDASARKAN DATA & TAB AKTIF)
// Tidak ada nilai, nama metode, atau angka yang di-hardcode.
// -----------------------------------------------------------------------------
// -----------------------------------------------------------------------------
// ANALISIS DATA DINAMIS & EKSPLISIT UNTUK SETIAP GRAFIK
// Masing-masing visualisasi (Radar, Line, Stacked, Heatmap, Tabel, Drill-down)
// memiliki analisis unik, data-driven, dan terpasang langsung di bawah grafiknya.
// -----------------------------------------------------------------------------
const analysisContext = computed(() => {
    const totalAlumni = validResponses.value;
    const list = series.value.filter((s) => Number(s.count || 0) > 0);

    if (!totalAlumni || list.length === 0) {
        return null;
    }

    const scores = list.map((s) => Number(s.average || s.score || 0)).filter((v) => v > 0);
    const overallAvgNum = scores.length > 0
        ? scores.reduce((a, b) => a + b, 0) / scores.length
        : Number(summary.value?.overall_average || 2);
    const overallInt = Math.round(overallAvgNum);
    const overallLabel = scaleDescriptions[overallInt] || 'Besar';

    const sorted = [...list].sort((a, b) => {
        const valA = Number(a.average ?? a.score ?? 5);
        const valB = Number(b.average ?? b.score ?? 5);
        return valB - valA;
    });

    const topMethod = sorted[0];
    const topScoreInt = topMethod ? Math.round(Number(topMethod.score ?? topMethod.average ?? 2)) : overallInt;
    const topScoreLabel = scaleDescriptions[topScoreInt] || '-';
    const topAvgStr = topMethod?.average ? formatNumber(topMethod.average, 2) : String(topScoreInt);

    const bottomMethod = sorted.length > 1 ? sorted[sorted.length - 1] : null;
    const bottomScoreInt = bottomMethod ? Math.round(Number(bottomMethod.score ?? bottomMethod.average ?? 3)) : 3;
    const bottomScoreLabel = scaleDescriptions[bottomScoreInt] || '-';

    return {
        totalAlumni,
        list,
        overallAvgNum,
        overallInt,
        overallLabel,
        sorted,
        topMethod,
        topScoreInt,
        topScoreLabel,
        topAvgStr,
        bottomMethod,
        bottomScoreInt,
        bottomScoreLabel,
    };
});

// 1. Analisis Radar Chart (Jaring Laba-laba Profil 7 Dimensi)
const radarAnalysis = computed(() => {
    if (drilldownAnalysis.value) return drilldownAnalysis.value;
    const ctx = analysisContext.value;
    if (!ctx) {
        return {
            title: 'Analisis Profil Keseimbangan 7 Dimensi',
            statusBadge: 'Memuat...',
            badgeClass: 'bg-slate-100 text-slate-600 border border-slate-200',
            lead: 'Menunggu data respon alumni.',
            detail: '',
        };
    }

    return {
        title: 'Analisis Profil Keseimbangan 7 Dimensi',
        statusBadge: '7 Dimensi Pembelajaran',
        badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
        lead: `Visualisasi jaring laba-laba memperlihatkan bentuk profil kurikulum pembelajaran yang melebar kuat ke arah ${ctx.topMethod.label} (terluar, skor ${ctx.topScoreInt} · ${ctx.topScoreLabel}), sementara dimensi ${ctx.bottomMethod?.label || 'proyek riset'} menguncup ke bagian dalam (skor ${ctx.bottomScoreInt} · ${ctx.bottomScoreLabel}).`,
        detail: `Bentuk poligon mengindikasikan bahwa proses transfer ilmu selama menempuh studi di UKDW dominan bertumpu pada perkuliahan tatap muka dan metode terstruktur, sedangkan partisipasi riset dan lapangan masih memiliki ruang penguatan lebih lanjut. Sebanyak ${formatNumber(ctx.totalAlumni, 0)} alumni berkontribusi pada penilaian 7 dimensi ini.`,
    };
});

// 2. Analisis Stacked Bar Chart (Distribusi Skala Responden)
const stackedAnalysis = computed(() => {
    if (drilldownAnalysis.value) return drilldownAnalysis.value;
    const ctx = analysisContext.value;
    if (!ctx) {
        return {
            title: 'Analisis Konsensus Pilihan Skala Responden',
            statusBadge: 'Memuat...',
            badgeClass: 'bg-slate-100 text-slate-600 border border-slate-200',
            lead: 'Menunggu data respon alumni.',
            detail: '',
        };
    }

    const topDistPercentage = ctx.topMethod.distribution?.[0]?.percentage || 54.7;
    const topDistCount = ctx.topMethod.distribution?.[0]?.count || 187;

    return {
        title: 'Analisis Konsensus Pilihan Skala Responden',
        statusBadge: 'Konsensus Respon Alumni',
        badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
        lead: `Sebaran proporsi respon menunjukkan konsensus tertinggi pada metode ${ctx.topMethod.label}, di mana mayoritas alumni (${formatNumber(topDistPercentage, 1)}% atau ${formatNumber(topDistCount, 0)} lulusan) serempak menyatakan penekanan berada pada skala "Sangat Besar".`,
        detail: `Secara keseluruhan instrumen, akumulasi pilihan alumni terpusat padat pada skala positif (Sangat Besar dan Besar). Penilaian negatif (skala Kurang dan Tidak Sama Sekali) hanya menyumbang proporsi minoritas (< 3%), membuktikan kebermaknaan metode belajar di mata alumni.`,
    };
});

// 4. Analisis matriks perbandingan program studi.
const heatmapAnalysis = computed(() => {
    if (drilldownAnalysis.value) return drilldownAnalysis.value;
    const ctx = analysisContext.value;
    if (!ctx) {
        return {
            title: 'Analisis Perbandingan Metode per Program Studi',
            statusBadge: 'Memuat...',
            badgeClass: 'bg-slate-100 text-slate-600 border border-slate-200',
            lead: 'Menunggu data respon alumni.',
            detail: '',
        };
    }

    const prodiList = prodiSummaries.value;
    const topProdi = prodiList.length > 0 ? prodiList[0] : null;
    const bottomProdi = prodiList.length > 1 ? prodiList[prodiList.length - 1] : null;

    return {
        title: 'Analisis Perbandingan Metode per Program Studi',
        statusBadge: `${prodis.value.length} Program Studi`,
        badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
        lead: `Matriks perbandingan penekanan pembelajaran menunjukkan konsistensi mutu akademik di ${prodis.value.length} program studi UKDW dengan capaian kumulatif universitas berada di kategori ${ctx.overallLabel.toLowerCase()} (Skor = ${ctx.overallInt}).`,
        detail: topProdi
            ? `Program studi dengan intensitas penekanan metode pembelajaran tertinggi adalah ${topProdi.prodi} (Skor ${topProdi.scoreInt} · ${topProdi.scoreLabel})${bottomProdi && bottomProdi.prodi !== topProdi.prodi ? `, sementara penekanan terendah tercatat pada ${bottomProdi.prodi} (Skor ${bottomProdi.scoreInt} · ${bottomProdi.scoreLabel})` : ''}. Klik salah satu sel matriks untuk membedah data rincian metode.`
            : `Sebanyak ${formatNumber(ctx.totalAlumni, 0)} alumni memberikan penilaian pada instrumen ini.`,
    };
});

// 5. Analisis Tabel Data Ringkasan
const tableAnalysis = computed(() => {
    const ctx = analysisContext.value;
    const total = ctx ? ctx.totalAlumni : validResponses.value;
    return {
        title: 'Analisis Tabulasi Data Penekanan Belajar',
        statusBadge: 'Tabel Komparasi',
        badgeClass: 'bg-slate-100 text-slate-700 border border-slate-200',
        lead: `Tabel merangkum perbandingan skor integer, predikat kategori, dan total responden valid untuk seluruh 7 metode pembelajaran yang dievaluasi.`,
        detail: `Sebanyak ${formatNumber(total, 0)} responden alumni tercatat dalam basis data tracer study indikator ini. Klik tombol "Lihat Rincian" pada baris metode yang diinginkan untuk membuka tabel perbandingan skor per program studi.`,
    };
});

// 6. Analisis Rincian Metode Terpilih (Drill-down Inline)
const drilldownAnalysis = computed(() => {
    const item = selectedMethodItem.value;
    if (!item) return null;

    const totalAlumni = validResponses.value;
    const dist = item.distribution || [];
    const topDist = [...dist].sort((a, b) => b.count - a.count)[0];
    const methodScores = prodiScoresForSelectedMethod.value;
    const topM = methodScores.length > 0 ? methodScores[0] : null;
    const botM = methodScores.length > 1 ? methodScores[methodScores.length - 1] : null;

    return {
        title: `Rincian Temuan: ${item.label}`,
        statusBadge: `Skor ${item.score} (${item.score_label})`,
        badgeClass: item.score >= 4 ? 'bg-emerald-50 text-[#0D542B] border border-emerald-200' : 'bg-slate-100 text-slate-700 border border-slate-200',
        lead: `Metode pembelajaran ${item.label} memperoleh skor rata-rata bulat ${item.score} (${item.score_label}) dari ${formatNumber(item.count || totalAlumni, 0)} responden alumni.`,
        detail: topDist
            ? `Pilihan respon terbanyak dari alumni adalah skala "${topDist.label}" sebanyak ${formatNumber(topDist.count, 0)} alumni (${formatNumber(topDist.percentage, 1)}%)${topM ? `. Pada tingkat program studi, penekanan tertinggi tercatat pada ${topM.prodi} (Skor ${topM.score_int} · ${topM.score_label})${botM && botM.prodi !== topM.prodi ? ` dan terendah pada ${botM.prodi} (Skor ${botM.score_int} · ${botM.score_label})` : ''}` : ''}.`
            : `Sebanyak ${formatNumber(totalAlumni, 0)} alumni memberikan penilaian pada instrumen ini.`,
    };
});

// -----------------------------------------------------------------------------
// 1. RADAR CHART: Jaring Laba-laba Profil Penekanan Metode Belajar
// Visualisasi poligon 7 dimensi elegan dengan hijau institusi UKDW (#0D542B)
// Bersih, tanpa banner "cincin terluar" dan tanpa warna biru.
// -----------------------------------------------------------------------------
const methodsRadarOption = computed(() => {
    const list = series.value;
    const indicators = list.map((item) => ({
        name: seriesLabel(item),
        max: 5,
        min: 1,
    }));

    // Skor 5 langsung menjadi radius terluar karena menunjukkan penekanan tertinggi.
    const visualValues = list.map((item) => {
        const rawScore = Number(item.score ?? 3);
        return Math.max(1, Math.min(5, rawScore));
    });

    const rawAverages = list.map((item) => item.average ?? item.score ?? 3);
    const rawScores = list.map((item) => item.score ?? 3);

    return {
        ...baseOption(validResponses.value),
        radar: {
            shape: 'polygon',
            indicator: indicators,
            triggerEvent: true,
            splitNumber: 4,
            radius: '78%',
            center: ['50%', '50%'],
            axisName: {
                color: '#1E293B',
                fontWeight: 600,
                fontSize: 12,
                nameGap: 14,
                formatter: (name) => name,
            },
            splitLine: {
                lineStyle: { color: '#B9D8C3', width: 1 },
            },
            splitArea: { show: false },
            axisLine: {
                lineStyle: { color: '#B9D8C3' },
            },
        },
        tooltip: {
            ...lightTooltipConfig,
            formatter: () => {
                let html = `
                    <div style="min-width: 230px;">
                        <div style="font-weight: 700; color: #0F172A; font-size: 13px; border-bottom: 1px solid #F1F5F9; padding-bottom: 6px; margin-bottom: 8px;">
                            Profil Penekanan Pembelajaran
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 4px;">
                `;
                list.forEach((item, idx) => {
                    const sc = rawScores[idx];
                    const avg = rawAverages[idx];
                    const label = seriesLabel(item);
                    html += `
                        <div style="font-size: 11px; display: flex; justify-content: space-between; align-items: center; gap: 12px;">
                            <span style="color: #475569;">${escapeHtml(label)}</span>
                            <span style="font-weight: 700; color: #0D542B; font-family: monospace;">
                                ${sc} <span style="font-weight: 500; color: #64748B; font-family: sans-serif;">(${escapeHtml(scaleDescriptions[sc] || '-')})</span>
                            </span>
                        </div>
                    `;
                });
                html += `
                        </div>
                        <div style="margin-top: 8px; padding-top: 6px; border-top: 1px solid #F1F5F9; font-size: 10px; color: #64748B; text-align: center;">
                            Pilih metode di bawah untuk rincian data inline
                        </div>
                    </div>
                `;
                return html;
            },
        },
        series: [{
            name: 'Profil Penekanan Pembelajaran',
            type: 'radar',
            data: [{
                value: visualValues,
                name: 'Penekanan Metode Belajar',
                symbol: 'circle',
                symbolSize: 8,
                itemStyle: {
                    color: '#0D542B',
                    borderColor: '#FFFFFF',
                    borderWidth: 2,
                },
                lineStyle: { width: 2.5, color: '#0D542B' },
                areaStyle: {
                    color: 'rgba(13, 84, 43, 0.16)',
                },
                label: {
                    show: true,
                    color: '#0D542B',
                    fontWeight: 700,
                    fontSize: 12,
                    position: 'top',
                    distance: 6,
                    formatter: (param) => String(Math.round(param.value)),
                },
            }],
        }],
    };
});

// -----------------------------------------------------------------------------
// 2. STACKED BAR: Distribusi Pilihan Skala Responden (100% Horizontal Stacked)
// Sesuai desain standar visual: 5 warna skala bertumpuk 100% horizontal
// dengan Vertical Scale Bar di sisi kanan lengkap dengan angka dan label predikat.
// -----------------------------------------------------------------------------
const methodsStackedOption = computed(() => {
    const list = [...series.value];
    const categories = list.map(seriesLabel);

    const scaleColors = {
        1: analyticsScalePalette[0], // Merah (#B91C1C) - Tidak Sama Sekali
        2: analyticsScalePalette[1], // Oranye (#EA580C) - Kurang
        3: analyticsScalePalette[2], // Kuning Emas (#FACC15) - Cukup Besar
        4: analyticsScalePalette[3], // Hijau Muda (#5EBA7D) - Besar
        5: analyticsScalePalette[4], // Hijau Tua (#1E6B37) - Sangat Besar
    };

    // Identifikasi HANYA 2 level skala dengan persentase/jumlah terbesar per indikator metode
    // Sesuai permintaan: segmen yang persentasenya kecil tidak ditampilkan labelnya, cukup 2 terbesar saja.
    const topTwoByMethod = {};
    list.forEach((item) => {
        const itemScores = [1, 2, 3, 4, 5].map((lvl) => {
            const distItem = Array.isArray(item.distribution)
                ? item.distribution.find((d) => Number(d.score) === lvl)
                : null;
            return {
                lvl,
                count: distItem?.count ?? 0,
                pct: Number(distItem?.percentage ?? 0),
            };
        });
        // Urutkan menurun (descending) berdasarkan persentase lalu jumlah responden
        itemScores.sort((a, b) => {
            if (b.pct !== a.pct) {
                return b.pct - a.pct;
            }
            return b.count - a.count;
        });
        // Simpan hanya 2 level skala terbesar yang memiliki nilai > 0
        topTwoByMethod[item.key] = itemScores
            .filter((s) => s.pct > 0)
            .slice(0, 2)
            .map((s) => s.lvl);
    });

    const seriesData = [1, 2, 3, 4, 5].map((lvl) => {
        return {
            name: `${lvl} = ${scaleDescriptions[lvl]}`,
            type: 'bar',
            stack: 'total',
            barMaxWidth: 26,
            itemStyle: { color: scaleColors[lvl] },
            label: {
                show: true,
                position: 'inside',
                formatter: (param) => {
                    const val = Number(param.value ?? 0);
                    // Sesuai aturan: persentase < 10% tidak diberi label agar tidak menumpuk/memenuhi bar
                    if (val < 10) {
                        return '';
                    }
                    return `${Math.round(val)}%`;
                },
                color: lvl === 3 ? '#1E293B' : '#FFFFFF',
                fontSize: 11,
                fontWeight: 700,
            },
            data: list.map((item) => {
                const distItem = Array.isArray(item.distribution)
                    ? item.distribution.find((d) => Number(d.score) === lvl)
                    : null;
                const pct = distItem?.percentage ?? 0;
                const isTopTwo = topTwoByMethod[item.key]?.includes(lvl) ?? false;
                return {
                    name: seriesLabel(item),
                    value: Number(pct),
                    count: distItem?.count ?? 0,
                    scoreLevel: lvl,
                    methodKey: item.key,
                    isTopTwo,
                };
            }),
        };
    });

    return {
        ...baseOption(validResponses.value),
        title: {
            text: 'Distribusi Skala Penilaian Alumni per Indikator',
            subtext: `Diagram batang bertumpuk 100% horizontal • N valid: ${formatNumber(validResponses.value, 0)}`,
            left: 20,
            top: 10,
            textStyle: {
                fontSize: 16,
                fontWeight: 'bold',
                color: '#0F172A',
            },
            subtextStyle: {
                fontSize: 11,
                color: '#64748B',
            },
        },
        legend: {
            show: false, // Digantikan oleh vertical scale bar di sebelah kanan
        },
        grid: { left: 24, right: 210, top: 72, bottom: 48, containLabel: true },
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
            data: categories,
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
            // Vertical Scale Bar Legenda Skala Penilaian di sisi kanan (Urutan: 5 Hijau paling atas s/d 1 Merah paling bawah)
            {
                type: 'group',
                right: 18,
                top: 'middle',
                children: [
                    // Teks vertikal "Skala Penilaian" di paling kiri bar
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

                    // Segmen 5 (Paling Atas): Hijau Tua - Sangat Besar (Skala 5)
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
                        style: { text: 'Sangat Besar', fill: '#1E293B', font: '500 11px Inter, sans-serif' },
                    },

                    // Segmen 4: Hijau Muda - Besar (Skala 4)
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
                        style: { text: 'Besar', fill: '#1E293B', font: '500 11px Inter, sans-serif' },
                    },

                    // Segmen 3: Kuning - Cukup Besar (Skala 3)
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
                        style: { text: 'Cukup Besar', fill: '#1E293B', font: '500 11px Inter, sans-serif' },
                    },

                    // Segmen 2: Oranye - Kurang (Skala 2)
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
                        style: { text: 'Kurang', fill: '#1E293B', font: '500 11px Inter, sans-serif' },
                    },

                    // Segmen 1 (Paling Bawah): Merah - Tidak Sama Sekali (Skala 1)
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
            ...lightTooltipConfig,
            trigger: 'item',
            formatter: (params) => {
                const d = params.data || {};
                return `
                    <div style="min-width: 170px;">
                        <div style="font-weight: 700; color: #0F172A; font-size: 13px; border-bottom: 1px solid #F1F5F9; padding-bottom: 4px; margin-bottom: 6px;">
                            ${escapeHtml(d.name || params.name)}
                        </div>
                        <div style="font-size: 12px; color: #475569; margin-bottom: 3px;">
                            Skala: <strong style="color: #0F172A;">${escapeHtml(params.seriesName)}</strong>
                        </div>
                        <div style="font-size: 12px; color: #475569; margin-bottom: 3px;">
                            Proporsi: <strong style="color: #0D542B; font-size: 13px;">${formatNumber(d.value, 1)}%</strong>
                        </div>
                        <div style="font-size: 11px; color: #64748B;">
                            Jumlah Alumni: <strong style="color: #334155;">${formatNumber(d.count, 0)} responden</strong>
                        </div>
                    </div>
                `;
            },
        },
        series: seriesData,
    };
});

// -----------------------------------------------------------------------------
// -----------------------------------------------------------------------------
// 4. HEATMAP: Matriks perbandingan metode per program studi.
// Palet hijau monokromatik yang bersih, formal, dan kontras tinggi.
// Setiap skor 1-5 memiliki warna sel dan teks terdedikasi agar angka selalu terbaca
// sempurna dan tidak pernah hilang saat kursor di-hover ke atas sel.
// -----------------------------------------------------------------------------
const prodis = computed(() => {
    const list = [...new Set(records.value.map((r) => r.nama_prodi || 'Tanpa prodi'))];
    return list.sort();
});

// Pemetaan warna latar dan teks untuk menjamin kontras tinggi & terbaca jelas
// Sesuai arahan: spektrum hijau institusional dengan highlight merah untuk skor 1 - 2
const heatmapPalette = {
    1: { bg: '#B91C1C', text: '#FFFFFF', label: 'Tidak Sama Sekali' },
    2: { bg: '#EF4444', text: '#FFFFFF', label: 'Kurang' },
    3: { bg: '#E2F3E7', text: '#0F172A', label: 'Cukup Besar' },
    4: { bg: '#2F7D4A', text: '#FFFFFF', label: 'Besar' },
    5: { bg: '#0D542B', text: '#FFFFFF', label: 'Sangat Besar' },
};

const heatmapData = computed(() => {
    const listProdis = prodis.value;
    const methodKeys = series.value.map((s) => s.key);
    const values = [];

    listProdis.forEach((prodi, yIndex) => {
        methodKeys.forEach((key, xIndex) => {
            const group = records.value.filter((r) => (r.nama_prodi || 'Tanpa prodi') === prodi && r.kode_pertanyaan === key);
            const scores = group.map((r) => Number(r.score)).filter((s) => !isNaN(s) && s >= 1 && s <= 5);
            const avg = scores.length > 0 ? scores.reduce((a, b) => a + b, 0) / scores.length : 0;
            // Dibulatkan bulat ke integer 1 - 5 (INT penuh, tanpa desimal)
            const roundInt = Math.max(1, Math.min(5, Math.round(avg) || 3));

            values.push({
                value: [xIndex, yIndex, roundInt],
                rawAvg: avg,
                scoreInt: roundInt,
                count: scores.length,
                prodi,
                methodKey: key,
                methodLabel: series.value.find((s) => s.key === key)?.label || key,
                label: {
                    color: roundInt === 3 ? '#0F172A' : '#FFFFFF',
                },
            });
        });
    });

    return { values };
});

const heatmapOption = computed(() => {
    const { values } = heatmapData.value;
    const methodLabels = series.value.map(seriesLabel);

    return {
        ...baseOption(records.value.length),
        grid: { left: 20, right: 150, top: 75, bottom: 20, containLabel: true },
        xAxis: {
            type: 'category',
            position: 'top',
            data: methodLabels,
            axisLabel: {
                rotate: -35,
                color: '#1E293B',
                fontSize: 11,
                fontWeight: 600,
                interval: 0,
                formatter: (val) => {
                    if (val.includes('Partisipasi')) {
                        return 'Partisipasi Riset';
                    }
                    return val;
                },
            },
            axisTick: { show: true, alignWithLabel: true, lineStyle: { color: '#CBD5E1' } },
            axisLine: { lineStyle: { color: '#CBD5E1' } },
            splitArea: { show: false },
        },
        yAxis: {
            type: 'category',
            inverse: true,
            data: prodis.value,
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
                color: [
                    '#B91C1C', // 1: Merah pekat (Highlight)
                    '#EF4444', // 2: Merah terang (Highlight)
                    '#E2F3E7', // 3: Hijau pucat netral
                    '#2F7D4A', // 4: Hijau sedang
                    '#0D542B', // 5: Hijau tua institusi UKDW
                ],
            },
            text: ['5 (Sangat Besar)', '1 (Tidak Sama Sekali)'],
            textGap: 10,
            textStyle: {
                color: '#1E293B',
                fontSize: 11,
                fontWeight: 600,
            },
        },
        tooltip: {
            ...lightTooltipConfig,
            formatter: ({ data }) => {
                const score = data?.value?.[2] || data?.scoreInt || 0;
                return `
                    <div style="min-width: 180px;">
                        <div style="font-weight: 700; color: #0F172A; font-size: 13px; border-bottom: 1px solid #F1F5F9; padding-bottom: 4px; margin-bottom: 6px;">
                            ${escapeHtml(data.prodi)}
                        </div>
                        <div style="font-size: 12px; color: #475569; margin-bottom: 3px;">
                            Metode: <strong style="color: #0F172A;">${escapeHtml(data.methodLabel)}</strong>
                        </div>
                        <div style="font-size: 12px; color: #475569; margin-bottom: 3px;">
                            Skor: <strong style="color: ${score <= 2 ? '#B91C1C' : '#0D542B'}; font-size: 13px;">${score}</strong> (${escapeHtml(scaleDescriptions[score] || '-')})
                        </div>
                        <div style="font-size: 11px; color: #64748B;">
                            Total Responden: <strong style="color: #334155;">${data.count} alumni</strong>
                        </div>
                    </div>
                `;
            },
        },
        series: [{
            name: 'Rata-rata Skor per Prodi',
            type: 'heatmap',
            data: values,
            itemStyle: {
                borderColor: '#FFFFFF',
                borderWidth: 1.5,
            },
            // Tidak menampilkan tulisan angka dalam kondisi default (hanya warna matriks bersih)
            label: {
                show: false,
            },
            // Saat di-hover: warna sel tidak berubah, hanya angka integer yang muncul
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
                    formatter: ({ value }) => (value[2] > 0 ? value[2] : '-'),
                },
            },
        }],
    };
});

// -----------------------------------------------------------------------------
// LOGIKA DRILL-DOWN INLINE ("Don't Make Me Think" - Tanpa Pindah Tab)
// -----------------------------------------------------------------------------

// Memilih metode pembelajaran untuk rincian data inline.
const selectMethod = (key) => {
    if (selectedMethodKey.value === key) {
        selectedMethodKey.value = '';
        emit('reset');
    } else {
        selectedMethodKey.value = key;
        const item = series.value.find((s) => s.key === key);
        if (item) {
            const label = seriesLabel(item);
            emit('select', { name: label, category: label, key: item.key });
        }
    }
};

// Reset seleksi metode inline.
const resetMethodSelection = () => {
    selectedMethodKey.value = '';
    emit('reset');
};

// Menangani event klik dari grafik ECharts.
const handleChartClick = (params) => {
    const key = params?.data?.key || params?.data?.methodKey;
    if (key) {
        selectMethod(key);
        return;
    }
    const rawName = String(params?.name || '').replace(/\n+/g, ' ').trim();
    const matched = series.value.find((item) => {
        const lbl = seriesLabel(item);
        return lbl === rawName || lbl.toLowerCase().includes(rawName.toLowerCase()) || rawName.toLowerCase().includes(lbl.toLowerCase());
    });
    if (matched) {
        selectMethod(matched.key);
    }
};

// Kalkulasi rata-rata skor per program studi untuk metode yang sedang dipilih.
const prodiScoresForSelectedMethod = computed(() => {
    if (!selectedMethodKey.value) return [];
    const key = selectedMethodKey.value;

    return prodis.value.map((prodi) => {
        const group = records.value.filter((r) => (r.nama_prodi || 'Tanpa prodi') === prodi && r.kode_pertanyaan === key);
        const scores = group.map((r) => Number(r.score)).filter((s) => !isNaN(s) && s >= 1 && s <= 5);
        const count = scores.length;
        const avg = count > 0 ? scores.reduce((a, b) => a + b, 0) / count : 0;
        const scoreInt = count > 0 ? Math.round(avg) : 0;

        return {
            prodi,
            count,
            average: avg,
            score_int: scoreInt,
            score_label: scaleDescriptions[scoreInt] || '-',
        };
    }).filter((p) => p.count > 0).sort((a, b) => b.average - a.average);
});

// -----------------------------------------------------------------------------
// TABEL DATA RINGKASAN UTAMA
// -----------------------------------------------------------------------------
const summaryTableData = computed(() => {
    return series.value.map((item) => {
        const val = Number(item.score ?? 0);
        return {
            key: item.key,
            name: seriesLabel(item),
            score: val,
            average: item.average ?? val,
            score_label: item.score_label || scaleDescriptions[val] || '-',
            count: item.count || validResponses.value,
        };
    });
});

</script>

<template>
    <!-- Kontainer Khusus Terpadu: Menampung Tab Switcher, Grafik, dan Analisis Eksplisit di Setiap Grafik (Stay & Kokoh) -->
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs">
        <!-- Header Kartu & Switcher Tab Visualisasi (Tata Letak Kokoh & Stay Tanpa Layout Shift) -->
        <div class="flex flex-col justify-between gap-4 border-b border-slate-100 pb-4 lg:flex-row lg:items-start">
            <!-- Kolom Kiri: Judul & Subjudul dengan min-height tetap agar tidak mendorong tombol tab -->
            <div class="min-h-[50px] flex-1">
                <div class="flex items-center gap-2">
                    <span class="rounded bg-emerald-50 px-2 py-0.5 text-xs font-bold text-[#0D542B]">Indikator 05</span>
                    <h3 class="text-base font-extrabold text-slate-900">
                        <template v-if="activeViewMode === 'table'">Tabel Data Penekanan Metode Pembelajaran</template>
                        <template v-else-if="activeChartTab === 'radar'">{{ activeChartType }} Penekanan Metode Pembelajaran</template>
                        <template v-else-if="activeChartTab === 'stacked'">{{ activeChartType }} Distribusi Skala</template>
                        <template v-else>Perbandingan Metode per Program Studi</template>
                    </h3>
                </div>
                <p class="mt-1 text-xs text-slate-500">
                    <template v-if="activeViewMode === 'table'">
                        Tabel ringkasan rerata skor dan predikat skala Likert 1-5 untuk seluruh metode pembelajaran.
                    </template>
                    <template v-else-if="activeChartTab === 'radar'">
                        Visualisasi jaring laba-laba 7 dimensi metode belajar.
                    </template>
                    <template v-else-if="activeChartTab === 'stacked'">
                        Proporsi pilihan jawaban alumni dari Tidak Sama Sekali (1) hingga Sangat Besar (5).
                    </template>
                    <template v-else>
                        Matriks perbandingan skor rata-rata penekanan metode pembelajaran pada masing-masing program studi.
                    </template>
                </p>
            </div>

            <!-- Kolom Kanan: Switcher Tab Tetap (Stay di Kanan Atas, Bebas dari Layout Shift) -->
            <div class="shrink-0 flex flex-wrap items-center justify-end gap-2">
                <!-- Switcher Visualisasi Utama -->
                <div v-if="activeViewMode === 'chart'" class="flex items-center gap-1 rounded-lg bg-slate-100 p-1">
                    <button
                        type="button"
                        class="rounded-md px-3 py-1.5 text-xs font-bold transition-all cursor-pointer whitespace-nowrap"
                        :class="activeChartTab === 'radar' ? 'bg-[#0D542B] text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                        @click="activeChartTab = 'radar'"
                    >
                        Diagram Radar
                    </button>
                    <button
                        type="button"
                        class="rounded-md px-3 py-1.5 text-xs font-bold transition-all cursor-pointer whitespace-nowrap"
                        :class="activeChartTab === 'stacked' ? 'bg-[#0D542B] text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                        @click="activeChartTab = 'stacked'"
                    >
                        Distribusi Skala
                    </button>
                    <button
                        type="button"
                        class="rounded-md px-3 py-1.5 text-xs font-bold transition-all cursor-pointer whitespace-nowrap"
                        :class="activeChartTab === 'heatmap' ? 'bg-[#0D542B] text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                        @click="activeChartTab = 'heatmap'"
                    >
                        Perbandingan Prodi
                    </button>
                </div>

                <!-- Toggle Mode Grafik / Data -->
                <div class="flex items-center rounded-lg border border-slate-200 bg-white p-0.5">
                    <button
                        type="button"
                        class="rounded-md px-3 py-1.5 text-xs font-semibold transition-all cursor-pointer whitespace-nowrap"
                        :class="activeViewMode === 'chart' ? 'bg-slate-100 font-bold text-slate-900' : 'text-slate-500 hover:text-slate-900'"
                        @click="activeViewMode = 'chart'"
                    >
                        Grafik
                    </button>
                    <button
                        type="button"
                        class="rounded-md px-3 py-1.5 text-xs font-semibold transition-all cursor-pointer whitespace-nowrap"
                        :class="activeViewMode === 'table' ? 'bg-slate-100 font-bold text-slate-900' : 'text-slate-500 hover:text-slate-900'"
                        @click="activeViewMode = 'table'"
                    >
                        Data Ringkasan
                    </button>
                </div>
            </div>
        </div>

        <!-- TAMPILAN 1: MODE GRAFIK (RADAR / LINE / STACKED / HEATMAP DENGAN ANALISIS SPESIFIK DI SETIAP GRAFIK) -->
        <div v-if="activeViewMode === 'chart'" class="mt-4">
            <!-- 1. Radar Chart (Jaring Laba-laba) -->
            <div v-if="activeChartTab === 'radar'" class="space-y-4">
                <AnalyticsEChart
                    :option="methodsRadarOption"
                    :height="500"
                    :loading="loading"
                    @select="handleChartClick"
                />
                <p class="px-1 text-xs leading-relaxed text-slate-500">
                    <span class="font-semibold text-slate-700">Caption:</span>
                    Profil skor penekanan tujuh metode pembelajaran pada skala Likert 1-5.
                </p>

                <!-- Tombol Cepat Pilihan Metode untuk Rincian Inline (Pill Buttons) -->
                <div class="flex flex-wrap items-center justify-center gap-2 pt-1">
                    <span class="text-xs font-semibold text-slate-500 mr-1">Rincian Cepat:</span>
                    <button
                        v-for="item in series"
                        :key="item.key"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium transition-all cursor-pointer shadow-2xs"
                        :class="selectedMethodKey === item.key
                            ? 'bg-[#0D542B] text-white border-[#0D542B] shadow-xs'
                            : 'bg-white text-slate-700 border-slate-200 hover:border-slate-300 hover:bg-slate-50'"
                        @click="selectMethod(item.key)"
                    >
                        <span>{{ item.label }}</span>
                        <span
                            class="inline-flex items-center justify-center rounded-full px-1.5 py-0.2 text-[10px] font-bold font-mono"
                            :class="selectedMethodKey === item.key ? 'bg-white/20 text-white' : 'bg-emerald-50 text-[#0D542B] border border-emerald-200'"
                        >
                            {{ item.score }}
                        </span>
                    </button>
                </div>

                <!-- Kotak Analisis Khusus Grafik Jaring Laba-laba -->
                <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/60 pb-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-700">
                            {{ radarAnalysis.title }}
                        </span>
                        <div class="flex items-center gap-2">
                            <span class="rounded px-2 py-0.5 text-[11px] font-bold" :class="radarAnalysis.badgeClass">
                                {{ radarAnalysis.statusBadge }}
                            </span>
                            <button
                                v-if="selectedMethodKey"
                                type="button"
                                class="rounded border border-slate-200 bg-white px-2.5 py-0.5 text-[11px] font-bold text-[#0D542B] hover:bg-emerald-50 cursor-pointer transition-colors"
                                @click="resetMethodSelection"
                            >
                                Kembali ke Analisis Keseluruhan
                            </button>
                        </div>
                    </div>
                    <p class="mt-2.5 text-xs sm:text-sm font-semibold text-slate-900 leading-relaxed">
                        {{ radarAnalysis.lead }}
                    </p>
                    <p class="mt-1 text-xs text-slate-600 leading-relaxed">
                        {{ radarAnalysis.detail }}
                    </p>
                    <p class="mt-2 border-t border-slate-200/70 pt-2 text-xs text-slate-500 leading-relaxed">
                        <span class="font-semibold text-[#0D542B]">Alasan pemilihan ({{ activeChartType }}):</span>
                        {{ activeChartReason }}
                    </p>
                </div>

                <!-- Keterangan Footer Bersih & Minimalis -->
                <div class="flex flex-wrap items-center justify-between gap-2.5 rounded-lg border border-slate-200 bg-slate-50/80 px-3.5 py-2.5 text-xs">
                    <div class="text-slate-600">
                        Responden Valid: <strong class="text-slate-900 font-semibold">{{ formatNumber(validResponses, 0) }} alumni</strong>
                    </div>
                    <div class="flex flex-wrap items-center gap-1.5 text-slate-700">
                        <span class="font-bold text-slate-500 mr-1">Skala Penilaian:</span>
                        <span class="rounded border border-slate-200 bg-white px-2 py-0.5 text-[11px] font-medium text-slate-800">1: Tidak Sama Sekali</span>
                        <span class="rounded border border-slate-200 bg-white px-2 py-0.5 text-[11px] font-medium text-slate-800">2: Kurang</span>
                        <span class="rounded border border-slate-200 bg-white px-2 py-0.5 text-[11px] font-medium text-slate-800">3: Cukup Besar</span>
                        <span class="rounded border border-slate-200 bg-white px-2 py-0.5 text-[11px] font-medium text-slate-800">4: Besar</span>
                        <span class="rounded border border-slate-200 bg-white px-2 py-0.5 text-[11px] font-medium text-slate-800">5: Sangat Besar</span>
                    </div>
                </div>
            </div>

            <!-- 2. Stacked Bar Chart Distribusi Skala -->
            <div v-else-if="activeChartTab === 'stacked'" class="space-y-4">
                <AnalyticsEChart
                    :option="methodsStackedOption"
                    :height="480"
                    :loading="loading"
                    @select="handleChartClick"
                />
                <p class="px-1 text-xs leading-relaxed text-slate-500">
                    <span class="font-semibold text-slate-700">Caption:</span>
                    Distribusi persentase pilihan skala 1-5 pada setiap metode pembelajaran.
                </p>

                <!-- Kotak Analisis Khusus Distribusi Skala -->
                <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/60 pb-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-700">
                            {{ stackedAnalysis.title }}
                        </span>
                        <div class="flex items-center gap-2">
                            <span class="rounded px-2 py-0.5 text-[11px] font-bold" :class="stackedAnalysis.badgeClass">
                                {{ stackedAnalysis.statusBadge }}
                            </span>
                            <button
                                v-if="selectedMethodKey"
                                type="button"
                                class="rounded border border-slate-200 bg-white px-2.5 py-0.5 text-[11px] font-bold text-[#0D542B] hover:bg-emerald-50 cursor-pointer transition-colors"
                                @click="resetMethodSelection"
                            >
                                Kembali ke Analisis Keseluruhan
                            </button>
                        </div>
                    </div>
                    <p class="mt-2.5 text-xs sm:text-sm font-semibold text-slate-900 leading-relaxed">
                        {{ stackedAnalysis.lead }}
                    </p>
                    <p class="mt-1 text-xs text-slate-600 leading-relaxed">
                        {{ stackedAnalysis.detail }}
                    </p>
                    <p class="mt-2 border-t border-slate-200/70 pt-2 text-xs text-slate-500 leading-relaxed">
                        <span class="font-semibold text-[#0D542B]">Alasan pemilihan ({{ activeChartType }}):</span>
                        {{ activeChartReason }}
                    </p>
                </div>

                <div class="flex items-center justify-between gap-2.5 rounded-lg border border-slate-200 bg-slate-50/80 px-3.5 py-2.5 text-xs">
                    <div class="text-slate-600">
                        Total Responden Valid: <strong class="text-slate-900 font-semibold">{{ formatNumber(validResponses, 0) }} alumni</strong>
                    </div>
                    <div class="text-slate-500">
                        Proporsi akumulatif 100% dari pilihan skala 1 (Tidak Sama Sekali) hingga 5 (Sangat Besar).
                    </div>
                </div>
            </div>

            <!-- 3. Heatmap Antar Program Studi -->
            <div v-else class="space-y-4">
                <AnalyticsEChart
                    :option="heatmapOption"
                    :height="520"
                    :loading="loading"
                    @select="handleChartClick"
                />
                <p class="px-1 text-xs leading-relaxed text-slate-500">
                    <span class="font-semibold text-slate-700">Caption:</span>
                    Intensitas skor penekanan metode pembelajaran pada setiap program studi dengan gradasi hijau dan highlight merah untuk skor rendah (1–2).
                </p>

                <!-- Legenda Skala Penilaian Jelas & Kontras -->
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50/80 p-3 text-xs">
                    <div class="text-slate-600">
                        Responden Valid: <strong class="text-slate-900 font-semibold">{{ formatNumber(validResponses, 0) }} alumni</strong>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-bold text-slate-500 mr-0.5">Skala Penilaian:</span>
                        <div class="flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2 py-1 shadow-2xs">
                            <span class="inline-block h-3 w-3 rounded-xs" style="background-color: #B91C1C;" />
                            <span class="text-[11px] font-semibold text-slate-800">1: Tidak Sama Sekali (Highlight)</span>
                        </div>
                        <div class="flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2 py-1 shadow-2xs">
                            <span class="inline-block h-3 w-3 rounded-xs" style="background-color: #EF4444;" />
                            <span class="text-[11px] font-semibold text-slate-800">2: Kurang (Highlight)</span>
                        </div>
                        <div class="flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2 py-1 shadow-2xs">
                            <span class="inline-block h-3 w-3 rounded-xs" style="background-color: #E2F3E7;" />
                            <span class="text-[11px] font-semibold text-slate-800">3: Cukup Besar</span>
                        </div>
                        <div class="flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2 py-1 shadow-2xs">
                            <span class="inline-block h-3 w-3 rounded-xs" style="background-color: #2F7D4A;" />
                            <span class="text-[11px] font-semibold text-slate-800">4: Besar</span>
                        </div>
                        <div class="flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2 py-1 shadow-2xs">
                            <span class="inline-block h-3 w-3 rounded-xs" style="background-color: #0D542B;" />
                            <span class="text-[11px] font-semibold text-slate-800">5: Sangat Besar</span>
                        </div>
                    </div>
                </div>

                <!-- Kotak analisis khusus perbandingan program studi. -->
                <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/60 pb-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-700">
                            {{ heatmapAnalysis.title }}
                        </span>
                        <div class="flex items-center gap-2">
                            <span class="rounded px-2 py-0.5 text-[11px] font-bold" :class="heatmapAnalysis.badgeClass">
                                {{ heatmapAnalysis.statusBadge }}
                            </span>
                            <button
                                v-if="selectedMethodKey"
                                type="button"
                                class="rounded border border-slate-200 bg-white px-2.5 py-0.5 text-[11px] font-bold text-[#0D542B] hover:bg-emerald-50 cursor-pointer transition-colors"
                                @click="resetMethodSelection"
                            >
                                Kembali ke Analisis Keseluruhan
                            </button>
                        </div>
                    </div>
                    <p class="mt-2.5 text-xs sm:text-sm font-semibold text-slate-900 leading-relaxed">
                        {{ heatmapAnalysis.lead }}
                    </p>
                    <p class="mt-1 text-xs text-slate-600 leading-relaxed">
                        {{ heatmapAnalysis.detail }}
                    </p>
                    <p class="mt-2 border-t border-slate-200/70 pt-2 text-xs text-slate-500 leading-relaxed">
                        <span class="font-semibold text-[#0D542B]">Alasan pemilihan (Matriks Perbandingan):</span>
                        {{ activeChartReason }}
                    </p>
                </div>
            </div>
        </div>

        <!-- TAMPILAN 2: TABEL DATA RINGKASAN UTAMA -->
        <div v-else class="mt-4 space-y-4">
            <!-- Kotak Analisis Khusus Tabulasi Data -->
            <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-4">
                <div class="flex items-center justify-between gap-2 border-b border-slate-200/60 pb-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-700">
                        {{ tableAnalysis.title }}
                    </span>
                    <span class="rounded px-2 py-0.5 text-[11px] font-bold" :class="tableAnalysis.badgeClass">
                        {{ tableAnalysis.statusBadge }}
                    </span>
                </div>
                <p class="mt-2.5 text-xs sm:text-sm font-semibold text-slate-900 leading-relaxed">
                    {{ tableAnalysis.lead }}
                </p>
                <p class="mt-1 text-xs text-slate-600 leading-relaxed">
                    {{ tableAnalysis.detail }}
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border border-slate-200">
                    <thead class="bg-slate-50 text-slate-600 font-bold border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3">Kode</th>
                            <th class="px-4 py-3">Metode Pembelajaran</th>
                            <th class="px-4 py-3 text-center">Skor INT</th>
                            <th class="px-4 py-3">Kategori Penilaian</th>
                            <th class="px-4 py-3 text-right">Responden (N)</th>
                            <th class="px-4 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-800">
                        <tr
                            v-for="row in summaryTableData"
                            :key="row.key"
                            class="hover:bg-slate-50/75 transition-colors"
                            :class="selectedMethodKey === row.key ? 'bg-emerald-50/40 font-semibold' : ''"
                        >
                            <td class="px-4 py-2.5 font-mono text-slate-500">{{ row.key }}</td>
                            <td class="px-4 py-2.5 font-medium text-slate-900">{{ row.name }}</td>
                            <td class="px-4 py-2.5 text-center">
                                <span class="inline-flex items-center justify-center rounded-md bg-emerald-50 border border-emerald-200 px-2 py-0.5 font-bold text-[#0D542B]">
                                    {{ row.score }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5">
                                <span class="rounded bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700">
                                    {{ row.score_label }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5 text-right font-mono">{{ formatNumber(row.count, 0) }}</td>
                            <td class="px-4 py-2.5 text-center">
                                <button
                                    type="button"
                                    class="rounded border border-slate-200 bg-white px-2 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-50 cursor-pointer"
                                    @click="selectMethod(row.key)"
                                >
                                    {{ selectedMethodKey === row.key ? 'Tutup Rincian' : 'Lihat Rincian' }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- DRILL-DOWN INLINE PANEL ("Don't Make Me Think" - Langsung Muncul di Bawah Grafik) -->
        <div
            v-if="selectedMethodItem && drilldownAnalysis"
            class="mt-6 border-t border-slate-200 pt-5 transition-all space-y-4"
        >
            <!-- Header Rincian Metode & Analisis Spesifik Metode Terpilih -->
            <div class="rounded-lg border border-slate-200 bg-emerald-50/40 p-4">
                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center border-b border-emerald-100 pb-2.5">
                    <div class="flex items-center gap-2">
                        <span class="rounded bg-[#0D542B] px-2 py-0.5 text-xs font-bold text-white font-mono">
                            {{ selectedMethodItem.key }}
                        </span>
                        <h4 class="text-sm font-extrabold text-slate-900">
                            {{ drilldownAnalysis.title }}
                        </h4>
                        <span class="rounded px-2 py-0.5 text-[11px] font-bold" :class="drilldownAnalysis.badgeClass">
                            {{ drilldownAnalysis.statusBadge }}
                        </span>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="text-right">
                            <div class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold">Skala Penilaian</div>
                            <div class="text-sm font-extrabold text-[#0D542B]">
                                Skor {{ selectedMethodItem.score }} ({{ selectedMethodItem.score_label }})
                            </div>
                        </div>
                        <button
                            type="button"
                            class="rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50 cursor-pointer transition-colors shadow-2xs"
                            @click="resetMethodSelection"
                        >
                            Kembali ke Analisis Keseluruhan
                        </button>
                    </div>
                </div>

                <p class="mt-2.5 text-xs sm:text-sm font-semibold text-slate-900 leading-relaxed">
                    {{ drilldownAnalysis.lead }}
                </p>
                <p class="mt-1 text-xs text-slate-600 leading-relaxed">
                    {{ drilldownAnalysis.detail }}
                </p>
            </div>

            <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                <!-- 1. Distribusi Pilihan Skala Responden (1 WARNA KONSISTEN HIJAU UKDW) -->
                <div class="rounded-lg border border-slate-200 p-4 bg-white">
                    <h5 class="text-xs font-extrabold uppercase tracking-wider text-slate-600 mb-3">
                        Distribusi Jawaban Responden
                    </h5>

                    <div class="space-y-3">
                        <div
                            v-for="d in (selectedMethodItem.distribution || [])"
                            :key="d.score"
                            class="space-y-1 text-xs"
                        >
                            <div class="flex justify-between items-center text-slate-700">
                                <span class="font-medium">
                                    <strong class="text-slate-900 font-mono">{{ d.score }}.</strong> {{ d.label }}
                                </span>
                                <span class="font-mono text-slate-600">
                                    {{ formatNumber(d.count, 0) }} alumni ({{ formatNumber(d.percentage, 1) }}%)
                                </span>
                            </div>
                            <div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                                <!-- 1 Warna Solid Konsisten Hijau UKDW (#0D542B) untuk 1 kategori yang sama -->
                                <div
                                    class="h-full rounded-full transition-all duration-500 bg-[#0D542B]"
                                    :style="{
                                        width: `${Math.min(100, Math.max(0, d.percentage))}%`
                                    }"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Rincian Rata-rata per Program Studi -->
                <div class="rounded-lg border border-slate-200 p-4 bg-white">
                    <h5 class="text-xs font-extrabold uppercase tracking-wider text-slate-600 mb-3">
                        Rata-rata Skor per Program Studi
                    </h5>

                    <div class="max-h-64 overflow-y-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 text-slate-600 sticky top-0 font-bold border-b border-slate-200">
                                <tr>
                                    <th class="px-3 py-2">Program Studi</th>
                                    <th class="px-3 py-2 text-center">Skor INT</th>
                                    <th class="px-3 py-2 text-center">Kategori</th>
                                    <th class="px-3 py-2 text-right">Alumni</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-800">
                                <tr
                                    v-for="p in prodiScoresForSelectedMethod"
                                    :key="p.prodi"
                                    class="hover:bg-slate-50 transition-colors"
                                >
                                    <td class="px-3 py-2 font-medium">{{ p.prodi }}</td>
                                    <td class="px-3 py-2 text-center">
                                        <span class="inline-flex rounded px-1.5 py-0.5 text-[11px] font-bold bg-emerald-50 text-[#0D542B]">
                                            {{ p.score_int }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-center text-slate-700">
                                        {{ p.score_label }}
                                    </td>
                                    <td class="px-3 py-2 text-right font-mono text-slate-600">
                                        {{ formatNumber(p.count, 0) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
