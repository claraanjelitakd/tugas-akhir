<script setup>
/**
 * Komponen Indikator 10: Studi Lanjut dan Pembiayaan (Instrumen F12, F18).
 *
 * Mengelola visualisasi studi lanjut alumni secara terpadu:
 * 1. Jenjang Studi Lanjut: Grafik jenjang pendidikan (S1, S2, S3, Profesi) dengan drill-down
 *    daftar perguruan tinggi tujuan dan jumlah mahasiswa di bawah grafik.
 * 2. Sumber Pembiayaan: Grafik skema pendanaan studi (Beasiswa Pemerintah, Mandiri, Swasta).
 * 3. Tabel Detail Responden: Tabulasi lengkap nama alumni, prodi lanjut, PT, dan sumber biaya.
 * Dilengkapi narasi analisis komprehensif mengadopsi format laporan resmi Tracer Study UKDW.
 */
import { computed, ref, watch } from 'vue';
import AnalyticsTabbedContainer from '../AnalyticsTabbedContainer.vue';
import {
    baseOption,
    escapeHtml,
    formatNumber,
    formatPercentage,
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
const degreeSeries = computed(() => props.chart?.series || []);
const fundingSeries = computed(() => props.chart?.funding_series || []);
const universityByDegree = computed(() => props.chart?.university_by_degree || {});
const records = computed(() => props.chart?.records || []);
const validResponses = computed(() => props.chart?.summary?.answered_count || records.value.length);

// State jenjang yang aktif dipilih untuk drill-down universitas
const activeSelectedDegree = ref('');

// Sinkronkan default jenjang aktif saat data termuat
watch([degreeSeries, () => props.selectedLabel], ([series, selected]) => {
    if (selected && series.some((s) => s.label === selected)) {
        activeSelectedDegree.value = selected;
    } else if (!activeSelectedDegree.value && series.length > 0) {
        activeSelectedDegree.value = series[0].label;
    }
}, { immediate: true });

// Menangani pemilihan jenjang atau sumber pembiayaan dari klik grafik maupun tombol pill
const handleDegreeSelect = (payload) => {
    const label = typeof payload === 'string' ? payload : (payload?.label || payload?.name || '');
    if (!label) return;
    if (degreeSeries.value.some((s) => s.label === label)) {
        activeSelectedDegree.value = label;
    }
    emit('select', typeof payload === 'string' ? { label, name: label } : payload);
};

// -----------------------------------------------------------------------------
// 1. BAR CHART: Jenjang Studi Lanjut (Hanya Menampilkan Jenjang yang Ada Data)
// -----------------------------------------------------------------------------
const degreeBarOption = computed(() => {
    // Hanya tampilkan jenjang yang memiliki data (>0)
    const rows = [...degreeSeries.value].filter((item) => seriesMetric(item) > 0).map((item) => {
        const label = seriesLabel(item);
        const count = seriesMetric(item);
        const isSelected = activeSelectedDegree.value === label || (!activeSelectedDegree.value && (!props.selectedLabel || props.selectedLabel === label));
        return {
            name: label,
            value: count,
            percentage: item.percentage || 0,
            itemStyle: {
                color: isSelected ? '#0D542B' : '#64748B',
                opacity: isSelected ? 1 : 0.28,
                borderRadius: [0, 4, 4, 0],
            },
        };
    }).reverse();

    const chartHeight = Math.max(260, rows.length * 52 + 70);

    return {
        ...baseOption(validResponses.value),
        grid: { left: 24, right: 90, top: 20, bottom: 30, containLabel: true },
        xAxis: { type: 'value', name: 'Jumlah Alumni', splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } } },
        yAxis: {
            type: 'category',
            data: rows.map((r) => r.name),
            axisLabel: { color: '#334155', fontSize: 12, fontWeight: 'bold' },
        },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            formatter: (params) => {
                const item = rows[params[0]?.dataIndex];
                if (!item) return '';
                return `<strong>Jenjang: ${escapeHtml(item.name)}</strong><br>Jumlah: <strong>${formatNumber(item.value, 0)} alumni</strong><br><span class="text-xs text-slate-500">Klik untuk melihat sebaran universitas</span>`;
            },
        },
        series: [{
            name: 'Jumlah Alumni',
            type: 'bar',
            barMaxWidth: 26,
            data: rows,
            label: {
                show: true,
                position: 'right',
                distance: 8,
                formatter: ({ data }) => `${formatNumber(data.value, 0)} alumni`,
                fontSize: 11,
                fontWeight: 'bold',
                color: '#0D542B',
            },
        }],
        _dynamicHeight: chartHeight,
    };
});

// Daftar universitas untuk jenjang yang sedang dipilih
const currentUniversityList = computed(() => {
    const deg = activeSelectedDegree.value || degreeSeries.value[0]?.label || 'S2';
    return universityByDegree.value[deg] || [];
});

const currentDegreeCount = computed(() => {
    const deg = activeSelectedDegree.value || degreeSeries.value[0]?.label || 'S2';
    const found = degreeSeries.value.find((s) => s.label === deg);
    return found ? found.count : currentUniversityList.value.reduce((a, b) => a + b.count, 0);
});

const degreeTable = computed(() => ({
    columns: [
        { key: 'name', label: 'Jenjang Pendidikan Lanjut' },
        { key: 'count', label: 'Jumlah Alumni (n)', align: 'right' },
        { key: 'percentage', label: 'Persentase', align: 'right' },
    ],
    rows: degreeSeries.value.map((item) => ({
        name: seriesLabel(item),
        count: formatNumber(seriesMetric(item), 0),
        percentage: formatPercentage(item.percentage || 0),
    })),
}));

// -----------------------------------------------------------------------------
// 2. HORIZONTAL BAR CHART: Sumber Pembiayaan Studi (F12)
// -----------------------------------------------------------------------------
const fundingOption = computed(() => {
    const data = [...fundingSeries.value].map((item) => {
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
                borderRadius: [0, 5, 5, 0],
            },
        };
    }).sort((left, right) => right.value - left.value).reverse();

    return {
        ...baseOption(validResponses.value),
        grid: { left: 180, right: 84, top: 24, bottom: 36, containLabel: true },
        xAxis: {
            type: 'value',
            name: 'Jumlah Alumni',
            nameLocation: 'middle',
            nameGap: 28,
            axisLabel: { formatter: (value) => formatNumber(value, 0) },
            splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
        },
        yAxis: {
            type: 'category',
            data: data.map((item) => item.name),
            axisLabel: { width: 160, overflow: 'break', color: '#334155' },
        },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            formatter: (params) => {
                const item = params[0]?.data;
                return item ? `<strong>${escapeHtml(item.name)}</strong><br>Jumlah: <strong>${formatNumber(item.value, 0)} alumni</strong>` : '';
            },
        },
        series: [{
            name: 'Sumber Pembiayaan',
            type: 'bar',
            barMaxWidth: 30,
            data,
            label: {
                show: true,
                position: 'right',
                formatter: ({ data }) => `${formatNumber(data.value, 0)} alumni`,
                fontSize: 11,
                fontWeight: 'bold',
                color: '#1E293B',
            },
        }],
    };
});

const fundingTable = computed(() => ({
    columns: [
        { key: 'name', label: 'Sumber Pembiayaan Studi (F12)' },
        { key: 'count', label: 'Jumlah Alumni (n)', align: 'right' },
        { key: 'percentage', label: 'Persentase', align: 'right' },
    ],
    rows: fundingSeries.value.map((item) => ({
        name: seriesLabel(item),
        count: formatNumber(seriesMetric(item), 0),
        percentage: formatPercentage(item.percentage || 0),
    })),
}));

// -----------------------------------------------------------------------------
// 3. TABEL DETAIL LENGKAP: Daftar Responden Alumni
// -----------------------------------------------------------------------------
const recordsTable = computed(() => ({
    columns: [
        { key: 'nim', label: 'NIM' },
        { key: 'nama', label: 'Nama Alumni' },
        { key: 'nama_prodi', label: 'Prodi Asal UKDW' },
        { key: 'degree', label: 'Jenjang' },
        { key: 'university', label: 'Perguruan Tinggi' },
        { key: 'study_program', label: 'Program Studi Lanjut' },
        { key: 'funding_source', label: 'Sumber Biaya' },
    ],
    rows: records.value.map((r) => ({
        nim: r.nim || '-',
        nama: r.nama || '-',
        nama_prodi: r.nama_prodi || '-',
        degree: r.degree || 'S2',
        university: r.university || 'Universitas Mitra',
        study_program: r.study_program || '-',
        funding_source: r.funding_source || 'Biaya Sendiri / Keluarga',
    })),
}));

// Analisis dinamis jenjang studi lanjut
const dynamicDegreeAnalysis = computed(() => {
    const list = [...degreeSeries.value].sort((a, b) => b.count - a.count);
    if (list.length === 0) {
        return {
            title: 'Kilas Peminatan Jenjang Akademik',
            statusBadge: 'Jenjang Studi Lanjut',
            badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
            lead: 'Data jenjang studi lanjut sedang dimuat.',
            detail: '',
        };
    }
    const top = list[0];
    const bottom = list[list.length - 1];
    const rincian = list.map((i, idx) => `${idx + 1}. ${i.label}: ${i.count} alumni (${formatPercentage(i.percentage)})`).join(', ');

    return {
        title: 'Kilas Peminatan Jenjang Akademik',
        statusBadge: 'Jenjang Studi Lanjut',
        badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
        lead: `Distribusi jenjang studi lanjut alumni UKDW menunjukkan bahwa peminatan terbesar berada pada jenjang ${top.label} (${top.count} alumni · ${formatPercentage(top.percentage)}), sedangkan peminatan terkecil tercatat pada jenjang ${bottom.label} (${bottom.count} alumni · ${formatPercentage(bottom.percentage)}).`,
        detail: `Rincian lengkap 5 jenjang pendidikan tinggi: ${rincian}. Klik salah satu jenjang untuk melihat daftar perguruan tinggi tujuan di bawah grafik.`,
    };
});

// Analisis dinamis skema pembiayaan studi lanjut (F12)
const dynamicFundingAnalysis = computed(() => {
    const list = [...fundingSeries.value].sort((a, b) => b.count - a.count);
    if (list.length === 0) {
        return {
            title: 'Kilas Sumber Pendanaan Studi',
            statusBadge: 'Skema Biaya F12',
            badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
            lead: 'Data pembiayaan studi lanjut sedang dimuat.',
            detail: '',
        };
    }
    const top = list[0];
    const bottom = list[list.length - 1];
    const rincian = list.map((i, idx) => `${idx + 1}. ${i.label}: ${i.count} alumni (${formatPercentage(i.percentage)})`).join(', ');

    return {
        title: 'Kilas Sumber Pendanaan Studi',
        statusBadge: 'Skema Biaya F12',
        badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
        lead: `Skema pembiayaan studi lanjut alumni terbesar bersumber dari "${top.label}" (${top.count} alumni · ${formatPercentage(top.percentage)}), sedangkan skema terendah adalah "${bottom.label}" (${bottom.count} alumni · ${formatPercentage(bottom.percentage)}).`,
        detail: `Rincian lengkap seluruh 5 opsi pilihan sumber pembiayaan instrumen F12: ${rincian}.`,
    };
});

// Konfigurasi views untuk kontainer terpadu: hanya 2 grafik utama (rincian alumni dihapus)
const views = computed(() => [
    {
        key: 'degree_view',
        chartType: 'horizontal_bar',
        tabLabel: 'Jenjang Studi Lanjut',
        title: 'Distribusi jenjang studi lanjut alumni UKDW',
        description: 'Sebaran jenjang studi yang ditempuh lulusan (klik salah satu jenjang untuk melihat rincian universitas tujuan).',
        option: degreeBarOption.value,
        tableData: degreeTable.value,
        height: Math.max(260, degreeSeries.value.filter((s) => s.count > 0).length * 52 + 70),
        analysis: dynamicDegreeAnalysis.value,
    },
    {
        key: 'funding_view',
        chartType: 'horizontal_bar',
        tabLabel: 'Sumber Pembiayaan',
        title: 'Skema pembiayaan studi lanjut alumni (Instrumen F12)',
        description: 'Perbandingan sumber pendanaan antara biaya mandiri/keluarga dengan beasiswa pemerintah, perusahaan, atau lembaga mitra.',
        option: fundingOption.value,
        tableData: fundingTable.value,
        height: 380,
        analysis: dynamicFundingAnalysis.value,
    },
]);
</script>

<template>
    <div class="space-y-4">
        <!-- Kontainer Navigasi Tab Grafik & Analisis Utama -->
        <AnalyticsTabbedContainer
            :views="views"
            :rows="records"
            :selected-label="selectedLabel"
            :loading="loading"
            indicator-badge="Indikator 10"
            @select="handleDegreeSelect($event)"
            @reset="emit('reset')"
        />

        <!-- DRILL-DOWN PANEL: DAFTAR UNIVERSITAS TUJUAN PER JENJANG (SESUAI REQUEST PENGGUNA) -->
        <div class="rounded-xl border border-emerald-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-3">
                <div>
                    <span class="rounded bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-[#0D542B] border border-emerald-200">
                        Drill-down Perguruan Tinggi
                    </span>
                    <h4 class="mt-1 text-sm font-bold text-slate-900">
                        Daftar Universitas Tujuan Jenjang:
                        <span class="text-[#0D542B] underline">{{ activeSelectedDegree || 'Semua Jenjang' }}</span>
                        ({{ formatNumber(currentDegreeCount, 0) }} Alumni)
                    </h4>
                    <p class="text-xs text-slate-500">
                        Pilih jenjang di grafik atas untuk memperbarui rincian universitas dan jumlah alumni secara langsung.
                    </p>
                </div>
                <div class="flex gap-1.5 flex-wrap">
                    <button
                        v-for="deg in degreeSeries"
                        :key="deg.label"
                        type="button"
                        class="px-2.5 py-1 text-xs font-semibold rounded transition"
                        :class="activeSelectedDegree === deg.label ? 'bg-[#0D542B] text-white shadow-sm' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                        @click="handleDegreeSelect(deg.label)"
                    >
                        {{ deg.label }} ({{ deg.count }})
                    </button>
                </div>
            </div>

            <!-- TABEL DAFTAR PERGURUAN TINGGI & JUMLAH MAHASISWA -->
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-[11px] font-bold uppercase text-slate-600">
                        <tr>
                            <th class="px-3 py-2.5 w-12">No</th>
                            <th class="px-3 py-2.5">Perguruan Tinggi Tujuan</th>
                            <th class="px-3 py-2.5">Program Studi / Bidang Peminatan</th>
                            <th class="px-3 py-2.5 text-right w-36">Jumlah Alumni</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-800">
                        <tr
                            v-for="(item, idx) in currentUniversityList"
                            :key="item.university"
                            class="hover:bg-emerald-50/40 transition"
                        >
                            <td class="px-3 py-2.5 font-medium text-slate-500">{{ idx + 1 }}</td>
                            <td class="px-3 py-2.5 font-semibold text-slate-900">
                                {{ item.university }}
                            </td>
                            <td class="px-3 py-2.5 text-slate-600">
                                <span v-if="item.programs && item.programs.length">
                                    {{ item.programs.join(', ') }}
                                </span>
                                <span v-else class="text-slate-400 italic">Program studi tidak spesifik</span>
                            </td>
                            <td class="px-3 py-2.5 text-right font-bold text-[#0D542B]">
                                {{ formatNumber(item.count, 0) }} orang
                            </td>
                        </tr>
                        <tr v-if="currentUniversityList.length === 0">
                            <td colspan="4" class="px-3 py-4 text-center text-slate-400">
                                Belum ada data perguruan tinggi untuk jenjang ini.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
