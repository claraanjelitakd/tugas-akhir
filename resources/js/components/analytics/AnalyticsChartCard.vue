<script setup>
/**
 * Komponen kartu visualisasi menyajikan tata letak terintegrasi Grafik | Data.
 *
 * Mengeliminasi tombol toolbar yang berlebihan dan menyajikan grafik interaktif
 * berdampingan dengan tabel ringkasan data numerik yang sinkron dua arah.
 */
import { computed, nextTick, ref, watch } from 'vue';
import AnalyticsEChart from './AnalyticsEChart.vue';
import { formatNumber } from './analyticsChartOptions';

// Kontrak properti visualisasi, opsi chart, data tabel terstruktur, dan analisis spesifik.
const props = defineProps({
    chartKey: { type: String, required: true },
    title: { type: String, required: true },
    description: { type: String, default: '' },
    option: { type: Object, required: true },
    tableData: { type: Object, default: () => ({ columns: [], rows: [] }) },
    rows: { type: Array, default: () => [] },
    height: { type: [Number, String], default: 380 },
    selectedLabel: { type: String, default: '' },
    loading: { type: Boolean, default: false },
    analysis: { type: [Object, String], default: null },
});

// Event seleksi data diteruskan ke panel induk untuk cross-filtering.
const emit = defineEmits(['select', 'reset']);

// Referensi instans ECharts dan kontainer kartu.
const chartRef = ref(null);
const cardRef = ref(null);

// Mode tampilan aktif: 'split' (Grafik | Data), 'chart' (Hanya Grafik), atau 'table' (Hanya Data).
const viewMode = ref('split');

// Sanitasi nama file untuk pengunduhan gambar PNG.
const filename = () => props.title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'grafik-tracer-study';

// Kolom tabel terstruktur dari properti atau fallback default.
const columns = computed(() => {
    if (props.tableData?.columns?.length) {
        return props.tableData.columns;
    }
    return [
        { key: 'name', label: 'Kategori / Dimensi' },
        { key: 'value', label: 'Nilai', align: 'right' },
    ];
});

// Baris tabel terstruktur dari properti atau hasil ekstraksi fallback.
const displayRows = computed(() => {
    if (props.tableData?.rows?.length) {
        return props.tableData.rows;
    }
    if (props.option?.series?.[0]?.data?.length) {
        return props.option.series[0].data.map((item) => ({
            name: item.name || item.prodi || 'Item',
            value: item.value ?? '-',
        }));
    }
    return [];
});

// Penanganan klik baris tabel untuk memicu cross-filter dua arah.
const handleRowClick = (row) => {
    const label = row.name || row.prodi || row.label || row.status || '';
    if (!label) return;

    emit('select', {
        data: {
            ...row,
            label,
            name: label,
        },
    });
};

// Penanganan klik langsung pada elemen ECharts.
const handleChartSelect = (params) => {
    emit('select', params);
};

// Menyesuaikan ukuran chart saat berganti mode tampilan.
watch(viewMode, async () => {
    await nextTick();
    chartRef.value?.resize();
});

// Analisis narasi spesifik data-driven untuk grafik kartu ini.
const computedAnalysis = computed(() => {
    if (props.analysis) {
        if (typeof props.analysis === 'string') {
            return {
                title: `Analisis Temuan · ${props.title}`,
                statusBadge: 'Kilas Analisis',
                badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
                lead: props.analysis,
                detail: '',
            };
        }
        return props.analysis;
    }

    const rows = displayRows.value;
    if (!rows || rows.length === 0) return null;

    // Filter baris yang memiliki nilai numerik valid
    const numericRows = rows.map((r) => {
        const rawVal = r.value ?? r.count ?? r.total ?? 0;
        const num = typeof rawVal === 'number' ? rawVal : parseFloat(String(rawVal).replace(/[^0-9.-]+/g, ''));
        const name = r.name || r.prodi || r.label || r.category || 'Kategori';
        return {
            name,
            rawVal,
            num: isNaN(num) ? 0 : num,
            formatted: typeof rawVal === 'number' ? formatNumber(rawVal, 0) : String(rawVal),
        };
    }).filter((r) => r.num > 0);

    if (numericRows.length === 0) return null;

    const sorted = [...numericRows].sort((a, b) => b.num - a.num);
    const topItem = sorted[0];
    const bottomItem = sorted.length > 1 ? sorted[sorted.length - 1] : null;
    const totalVal = sorted.reduce((acc, curr) => acc + curr.num, 0);

    const topPct = totalVal > 0 ? ((topItem.num / totalVal) * 100).toFixed(1) : null;

    return {
        title: `Analisis Temuan · ${props.title}`,
        statusBadge: `${rows.length} Kategori Terdata`,
        badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
        lead: topPct
            ? `Distribusi data menunjukkan konsentrasi tertinggi berada pada kategori "${topItem.name}" dengan total ${topItem.formatted} alumni (${topPct}% dari akumulasi kategori terhitung).`
            : `Kategori dengan capaian tertinggi tercatat pada "${topItem.name}" dengan nilai ${topItem.formatted}.`,
        detail: bottomItem && bottomItem.name !== topItem.name
            ? `Sebaliknya, frekuensi terendah tercatat pada kategori "${bottomItem.name}" dengan ${bottomItem.formatted} alumni. Klik segmen batang atau baris tabel untuk membedah data alumni terkait.`
            : 'Klik segmen batang atau baris tabel untuk membedah data alumni terkait.',
    };
});
</script>

<template>
    <!-- Kartu visualisasi formal dengan penyajian Grafik | Data terpadu -->
    <article ref="cardRef" class="flex min-h-0 flex-col bg-white p-5 shadow-sm sm:p-6 border border-slate-100">
        <!-- Header kartu dengan judul, deskripsi, dan tombol mode tampilan -->
        <header class="flex flex-col justify-between gap-3 border-b border-slate-100 pb-4 sm:flex-row sm:items-center">
            <div class="min-w-0">
                <h3 class="text-base font-extrabold text-slate-900 sm:text-lg">{{ title }}</h3>
                <p v-if="description" class="mt-1 max-w-3xl text-xs sm:text-sm leading-relaxed text-slate-500">{{ description }}</p>
            </div>

            <!-- Kontrol tampilan formal tanpa tombol dekoratif berlebihan -->
            <div class="flex items-center gap-2 self-start sm:self-auto shrink-0">
                <div class="flex items-center rounded bg-slate-100 p-0.5" role="group" aria-label="Mode tampilan">
                    <button
                        type="button"
                        class="rounded px-2.5 py-1 text-xs font-bold transition-all"
                        :class="viewMode === 'split' ? 'bg-white text-[#0D542B] shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                        @click="viewMode = 'split'"
                    >
                        Grafik | Data
                    </button>
                    <button
                        type="button"
                        class="rounded px-2.5 py-1 text-xs font-bold transition-all"
                        :class="viewMode === 'chart' ? 'bg-white text-[#0D542B] shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                        @click="viewMode = 'chart'"
                    >
                        Grafik
                    </button>
                    <button
                        type="button"
                        class="rounded px-2.5 py-1 text-xs font-bold transition-all"
                        :class="viewMode === 'table' ? 'bg-white text-[#0D542B] shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                        @click="viewMode = 'table'"
                    >
                        Data
                    </button>
                </div>

                <button
                    type="button"
                    title="Unduh PNG"
                    class="rounded bg-slate-100 px-2 py-1 text-xs font-bold text-slate-700 hover:bg-slate-200"
                    @click="chartRef?.downloadPng(filename())"
                >
                    PNG
                </button>
            </div>
        </header>

        <!-- Konten visualisasi sesuai mode aktif -->
        <div class="mt-4 min-h-0">
            <!-- Mode Split: Visual Grafik di kiri, Tabel Data di kanan -->
            <div v-if="viewMode === 'split'" class="grid grid-cols-1 gap-6 xl:grid-cols-12 items-start">
                <div class="min-w-0 xl:col-span-7">
                    <AnalyticsEChart
                        ref="chartRef"
                        class="min-h-0"
                        :option="option"
                        :height="height"
                        :loading="loading"
                        :aria-label="`${title}. ${description}`"
                        @select="handleChartSelect"
                    />
                </div>

                <!-- Tabel ringkasan data numerik yang terhubung langsung -->
                <div class="min-w-0 xl:col-span-5 flex flex-col border border-slate-200 bg-white">
                    <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-3.5 py-2.5">
                        <span class="text-xs font-extrabold uppercase tracking-wide text-slate-700">Tabel Data Ringkasan</span>
                        <span v-if="selectedLabel" class="text-xs font-bold text-[#0D542B]">
                            Pilihan: {{ selectedLabel }}
                        </span>
                    </div>

                    <div class="overflow-x-auto max-h-[380px] overflow-y-auto">
                        <table class="min-w-full text-left text-xs divide-y divide-slate-100">
                            <thead>
                                <tr class="bg-slate-50">
                                    <th
                                        v-for="col in columns"
                                        :key="col.key"
                                        class="whitespace-nowrap px-3 py-2 font-bold text-slate-700"
                                        :class="col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : 'text-left'"
                                    >
                                        {{ col.label }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="(row, idx) in displayRows"
                                    :key="idx"
                                    class="transition-colors hover:bg-slate-50 cursor-pointer"
                                    :class="(selectedLabel && (row.name === selectedLabel || row.prodi === selectedLabel || row.label === selectedLabel)) ? 'bg-emerald-50 font-bold border-l-4 border-[#0D542B]' : ''"
                                    @click="handleRowClick(row)"
                                >
                                    <td
                                        v-for="col in columns"
                                        :key="col.key"
                                        class="whitespace-nowrap px-3 py-2 text-slate-700"
                                        :class="col.align === 'right' ? 'text-right font-medium' : col.align === 'center' ? 'text-center' : 'text-left'"
                                    >
                                        <!-- Penanda status evaluasi kompetensi -->
                                        <span
                                            v-if="col.key === 'status'"
                                            class="inline-block rounded px-2 py-0.5 text-xs font-bold"
                                            :class="row.isDeficit ? 'bg-amber-100 text-amber-900' : 'bg-emerald-100 text-emerald-900'"
                                        >
                                            {{ row[col.key] }}
                                        </span>
                                        <template v-else>
                                            {{ row[col.key] ?? '-' }}
                                        </template>
                                    </td>
                                </tr>
                                <tr v-if="!displayRows.length">
                                    <td :colspan="columns.length" class="px-3 py-6 text-center text-slate-400">
                                        Data ringkasan belum tersedia.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Mode Grafik Penuh -->
            <div v-else-if="viewMode === 'chart'" class="w-full">
                <AnalyticsEChart
                    ref="chartRef"
                    class="min-h-0"
                    :option="option"
                    :height="height"
                    :loading="loading"
                    :aria-label="`${title}. ${description}`"
                    @select="handleChartSelect"
                />
            </div>

            <!-- Mode Data Penuh -->
            <div v-else class="w-full border border-slate-200 bg-white">
                <div class="overflow-x-auto max-h-[460px] overflow-y-auto">
                    <table class="min-w-full text-left text-xs divide-y divide-slate-100">
                        <thead>
                            <tr class="bg-slate-50">
                                <th
                                    v-for="col in columns"
                                    :key="col.key"
                                    class="whitespace-nowrap px-4 py-3 font-bold text-slate-700"
                                    :class="col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : 'text-left'"
                                >
                                    {{ col.label }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="(row, idx) in displayRows"
                                :key="idx"
                                class="transition-colors hover:bg-slate-50 cursor-pointer"
                                :class="(selectedLabel && (row.name === selectedLabel || row.prodi === selectedLabel || row.label === selectedLabel)) ? 'bg-emerald-50 font-bold border-l-4 border-[#0D542B]' : ''"
                                @click="handleRowClick(row)"
                            >
                                <td
                                    v-for="col in columns"
                                    :key="col.key"
                                    class="whitespace-nowrap px-4 py-2.5 text-slate-700"
                                    :class="col.align === 'right' ? 'text-right font-medium' : col.align === 'center' ? 'text-center' : 'text-left'"
                                >
                                    <span
                                        v-if="col.key === 'status'"
                                        class="inline-block rounded px-2 py-0.5 text-xs font-bold"
                                        :class="row.isDeficit ? 'bg-amber-100 text-amber-900' : 'bg-emerald-100 text-emerald-900'"
                                    >
                                        {{ row[col.key] }}
                                    </span>
                                    <template v-else>
                                        {{ row[col.key] ?? '-' }}
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Kotak Analisis Naratif Spesifik di Bawah Grafik / Tabel -->
        <div v-if="computedAnalysis" class="mt-4 rounded-lg border border-slate-200 bg-slate-50/70 p-4">
            <div class="flex items-center justify-between gap-2 border-b border-slate-200/60 pb-2">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-700">
                    {{ computedAnalysis.title }}
                </span>
                <span class="rounded px-2 py-0.5 text-[11px] font-bold" :class="computedAnalysis.badgeClass">
                    {{ computedAnalysis.statusBadge }}
                </span>
            </div>
            <p class="mt-2.5 text-xs sm:text-sm font-semibold text-slate-900 leading-relaxed">
                {{ computedAnalysis.lead }}
            </p>
            <p v-if="computedAnalysis.detail" class="mt-1 text-xs text-slate-600 leading-relaxed">
                {{ computedAnalysis.detail }}
            </p>
        </div>
    </article>
</template>
