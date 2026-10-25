<script setup>
/**
 * Komponen kartu visualisasi menyajikan tata letak terintegrasi Grafik | Data.
 *
 * Mengeliminasi tombol toolbar yang berlebihan dan menyajikan grafik interaktif
 * berdampingan dengan tabel ringkasan data numerik yang sinkron dua arah.
 */
import { computed, nextTick, ref, watch } from 'vue';
import AnalyticsEChart from './AnalyticsEChart.vue';

// Kontrak properti visualisasi, opsi chart, dan data tabel terstruktur.
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
    </article>
</template>
