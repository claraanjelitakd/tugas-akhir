<script setup>
/**
 * Komponen Indikator 07: Status Pekerjaan Alumni & IKU 1 (F8).
 *
 * Mengelola visualisasi status pekerjaan alumni dengan palet elegan UKDW:
 * 1. Donut Chart: Proporsi Status Pekerjaan Universitas (Warna UKDW)
 * 2. Stacked Bar: Komposisi Status per Program Studi (Lengkap dengan n & persentase)
 * 3. Sankey Diagram: Alur Program Studi ke Status Pekerjaan (Rapi, tanpa clipping)
 * Serta dilengkapi tabel drill-down cerdas yang berubah sesuai kategori yang dipilih:
 * - Melanjutkan Pendidikan: Sebaran universitas, prodi lanjut, jenjang
 * - Bekerja: Nama perusahaan, posisi/jabatan, provinsi lokasi kerja
 * - Wiraswasta: Nama usaha/startup, jenis usaha, peran/jabatan
 */
import { computed, ref } from 'vue';
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

// Palet warna institusional yang harmonis, formal, dan tidak silau di mata
const statusColorMap = {
    'Bekerja': '#0D542B',                         // Hijau primer UKDW
    'Wiraswasta': '#D97706',                      // Amber / emas hangat
    'Melanjutkan pendidikan': '#334155',          // Slate gelap
    'Sedang mencari kerja': '#64748B',            // Slate netral
    'Belum memungkinkan bekerja': '#94A3B8',      // Slate lembut
};

const getStatusColor = (label) => statusColorMap[label] || '#475569';

// -----------------------------------------------------------------------------
// 1. DONUT CHART: Proporsi Status Pekerjaan Universitas
// -----------------------------------------------------------------------------
const donutOption = computed(() => {
    const data = series.value.map((item) => {
        const label = seriesLabel(item);
        const count = seriesMetric(item);
        const selected = !props.selectedLabel || props.selectedLabel === label;
        return {
            name: label,
            value: count,
            percentage: item.percentage || 0,
            itemStyle: {
                color: getStatusColor(label),
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
            textStyle: { color: '#334155', fontSize: 12, fontWeight: 600 },
        },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'item',
            formatter: ({ data }) => `<strong>${escapeHtml(data.name)}</strong><br>Jumlah: <strong>${formatNumber(data.value, 0)} alumni</strong><br>Proporsi: <strong>${formatPercentage(data.percentage)}</strong>`,
        },
        series: [{
            name: 'Status Pekerjaan',
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

const donutTable = computed(() => ({
    columns: [
        { key: 'status', label: 'Status Aktivitas Alumni' },
        { key: 'count', label: 'Jumlah Alumni (n)', align: 'right' },
        { key: 'percentage', label: 'Persentase', align: 'right' },
    ],
    rows: series.value.map((item) => ({
        status: seriesLabel(item),
        count: formatNumber(seriesMetric(item), 0),
        percentage: formatPercentage(item.percentage || 0),
    })),
}));

// -----------------------------------------------------------------------------
// 2. STACKED BAR: Komposisi Status per Program Studi (Dilengkapi n & %)
// -----------------------------------------------------------------------------
const prodis = computed(() => [...new Set(records.value.map((r) => r.nama_prodi || 'Tanpa prodi'))]);
const statusCategories = computed(() => [...new Set(series.value.map((s) => s.label))]);

const stackedOption = computed(() => {
    const listProdis = prodis.value;
    const cats = statusCategories.value;

    const prodiTotals = Object.fromEntries(
        listProdis.map((p) => [p, records.value.filter((r) => (r.nama_prodi || 'Tanpa prodi') === p).length]),
    );

    const seriesList = cats.map((cat) => {
        const catData = listProdis.map((prodi) => {
            const count = records.value.filter((r) => (r.nama_prodi || 'Tanpa prodi') === prodi && recordCategory(r) === cat).length;
            const total = Math.max(prodiTotals[prodi], 1);
            const pct = Number(((count / total) * 100).toFixed(1));
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
            barMaxWidth: 28,
            label: {
                show: true,
                position: 'inside',
                formatter: (params) => {
                    return params.value >= 7 ? `${params.value}%` : '';
                },
                fontSize: 10,
                color: '#FFFFFF',
                fontWeight: 600,
            },
            itemStyle: {
                color: getStatusColor(cat),
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
        legend: {
            data: cats,
            bottom: 0,
            icon: 'circle',
            textStyle: { color: '#334155', fontSize: 11, fontWeight: 600 },
        },
        grid: { left: 24, right: 36, top: 24, bottom: 60, containLabel: true },
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
            axisLabel: {
                color: '#334155',
                fontSize: 11,
                width: 200,
                overflow: 'break',
            },
        },
        tooltip: {
            ...baseOption().tooltip,
            trigger: 'item',
            formatter: ({ data }) => {
                if (!data) return '';
                return `<strong>${escapeHtml(data.prodi)}</strong><br>Status: <strong>${escapeHtml(data.category)}</strong><br>Jumlah: <strong>${data.count} alumni</strong> (dari total n=${data.total})<br>Proporsi: <strong>${data.value}%</strong>`;
            },
        },
        series: seriesList,
    };
});

const stackedTable = computed(() => ({
    columns: [
        { key: 'prodi', label: 'Program Studi' },
        { key: 'total', label: 'Total Responden (n)', align: 'right' },
        { key: 'bekerja', label: 'Bekerja', align: 'right' },
        { key: 'wiraswasta', label: 'Wiraswasta', align: 'right' },
        { key: 'studi', label: 'Lanjut Studi', align: 'right' },
        { key: 'lainnya', label: 'Lainnya / Mencari', align: 'right' },
    ],
    rows: prodis.value.map((prodi) => {
        const rows = records.value.filter((r) => (r.nama_prodi || 'Tanpa prodi') === prodi);
        const total = rows.length;
        const countBek = rows.filter((r) => recordCategory(r) === 'Bekerja').length;
        const countWir = rows.filter((r) => recordCategory(r) === 'Wiraswasta').length;
        const countStu = rows.filter((r) => recordCategory(r) === 'Melanjutkan pendidikan').length;
        const countOth = total - (countBek + countWir + countStu);

        return {
            prodi,
            total: formatNumber(total, 0),
            bekerja: `${countBek} (${formatPercentage(total ? (countBek / total) * 100 : 0)})`,
            wiraswasta: `${countWir} (${formatPercentage(total ? (countWir / total) * 100 : 0)})`,
            studi: `${countStu} (${formatPercentage(total ? (countStu / total) * 100 : 0)})`,
            lainnya: `${countOth} (${formatPercentage(total ? (countOth / total) * 100 : 0)})`,
        };
    }),
}));

// -----------------------------------------------------------------------------
// 3. SANKEY DIAGRAM: Aliran Program Studi ke Status Pekerjaan (Rapi & Bebas Clipping)
// -----------------------------------------------------------------------------
const sankeyOption = computed(() => {
    const prodiNames = prodis.value;
    const catNames = statusCategories.value;

    const nodes = [
        ...prodiNames.map((name) => ({ name, itemStyle: { color: '#0D542B' } })),
        ...catNames.map((name) => ({ name, itemStyle: { color: getStatusColor(name) } })),
    ];

    const links = [];
    prodiNames.forEach((prodi) => {
        catNames.forEach((cat) => {
            const count = records.value.filter((r) => (r.nama_prodi || 'Tanpa prodi') === prodi && recordCategory(r) === cat).length;
            if (count > 0) {
                links.push({
                    source: prodi,
                    target: cat,
                    value: count,
                    lineStyle: {
                        color: getStatusColor(cat),
                        opacity: 0.35,
                    },
                });
            }
        });
    });

    return {
        ...baseOption(records.value.length),
        tooltip: {
            trigger: 'item',
            formatter: ({ data }) => {
                if (data.source && data.target) {
                    return `<strong>${escapeHtml(data.source)} &rarr; ${escapeHtml(data.target)}</strong><br>Jumlah: <strong>${formatNumber(data.value, 0)} alumni</strong>`;
                }
                return `<strong>${escapeHtml(data.name)}</strong>`;
            },
        },
        series: [{
            type: 'sankey',
            layout: 'none',
            left: 20,
            right: 180,
            top: 24,
            bottom: 24,
            nodeWidth: 18,
            nodeGap: 16,
            draggable: false,
            emphasis: { focus: 'adjacency' },
            data: nodes,
            links,
            label: {
                position: 'right',
                color: '#334155',
                fontSize: 11,
                fontWeight: 600,
            },
            lineStyle: {
                curveness: 0.5,
            },
        }],
    };
});

const sankeyTable = computed(() => stackedTable.value);

// Konfigurasi views untuk kontainer terpadu
const views = computed(() => [
    {
        key: 'donut',
        tabLabel: 'Proporsi Universitas',
        title: 'Proporsi status pekerjaan universitas',
        description: 'Distribusi status alumni (Bekerja, Melanjutkan Pendidikan, Wiraswasta, dan Mencari Kerja) tingkat universitas.',
        option: donutOption.value,
        tableData: donutTable.value,
        height: 390,
        analysis: {
            title: 'Kilas Capaian Keterserapan Alumni (IKU 1)',
            statusBadge: 'Keterserapan Utama',
            badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
            lead: series.value[0]?.label
                ? `Sebagian besar lulusan UKDW telah terserap di dunia kerja dengan status ${series.value[0].label} sebesar ${formatPercentage(series.value[0].percentage)} (${series.value[0].count} alumni).`
                : 'Data status pekerjaan terdata lengkap.',
            detail: 'Kombinasi alumni yang berstatus bekerja, wiraswasta, dan melanjutkan studi pascasarjana menjadi pilar utama pemenuhan Indikator Kinerja Utama (IKU 1) Universitas Kristen Duta Wacana.',
        },
    },
    {
        key: 'stacked',
        tabLabel: 'Komposisi Prodi',
        title: 'Komposisi status pekerjaan per program studi',
        description: 'Perbandingan distribusi persentase dan jumlah responden (n) status pekerjaan di setiap jurusan.',
        option: stackedOption.value,
        tableData: stackedTable.value,
        height: Math.max(380, prodis.value.length * 36 + 120),
        analysis: {
            title: 'Kilas Keterserapan Antarprogram Studi',
            statusBadge: 'Sebaran Jurusan',
            badgeClass: 'bg-emerald-50 text-[#0D542B] border border-emerald-200',
            lead: `Tercatat variasi komposisi serapan alumni pada ${prodis.value.length} program studi yang berpartisipasi.`,
            detail: 'Setiap program studi menampilkan karakteristik transisi karir yang unik, di mana prodi vokasi/teknik menunjukkan tingkat kerja langsung yang tinggi dan prodi humaniora/sains mengombinasikan studi lanjut dan wirausaha.',
        },
    },
    {
        key: 'sankey',
        tabLabel: 'Alur Keterserapan',
        title: 'Sankey Diagram: Aliran Program Studi ke Status Pekerjaan',
        description: 'Ketebalan setiap pita menunjukkan jumlah alumni yang mengalir dari program studi asal menuju status pekerjaan atau studi lanjut.',
        option: sankeyOption.value,
        tableData: sankeyTable.value,
        height: Math.max(420, prodis.value.length * 28 + 100),
        analysis: {
            title: 'Kilas Aliran Kontribusi Karir',
            statusBadge: 'Aliran Proporsional',
            badgeClass: 'bg-slate-100 text-slate-700',
            lead: 'Diagram aliran menunjukkan kontribusi tiap program studi dalam menyuplai talenta ke pasar tenaga kerja dan studi lanjutan.',
            detail: 'Ketebalan pita aliran sebanding dengan kuantitas alumni yang diserap oleh masing-masing status karir.',
            reason: 'Dipilih untuk membaca hubungan asal program studi dan tujuan status karir secara simultan; pita yang lebih tebal berarti jumlah alumni yang lebih besar.',
        },
    },
]);

// -----------------------------------------------------------------------------
// DRILL-DOWN CERDAS KONTEKSTUAL (Ketika Pengguna Mengeklik Kategori Tertentu)
// -----------------------------------------------------------------------------
const activeFilteredRecords = computed(() => {
    if (!props.selectedLabel) return [];
    return records.value.filter((r) => recordCategory(r) === props.selectedLabel);
});

// Menentukan mode tabel drill-down berdasarkan kategori yang sedang dipilih
const drillDownType = computed(() => {
    const lbl = String(props.selectedLabel || '').toLowerCase();
    if (lbl.includes('pendidikan') || lbl.includes('studi')) return 'pendidikan';
    if (lbl.includes('wira')) return 'wiraswasta';
    if (lbl.includes('bekerja')) return 'bekerja';
    return 'umum';
});
</script>

<template>
    <div class="space-y-6">
        <AnalyticsTabbedContainer
            :views="views"
            :rows="records"
            :selected-label="selectedLabel"
            :loading="loading"
            indicator-badge="Indikator 07"
            @select="emit('select', $event)"
            @reset="emit('reset')"
        />

        <!-- Panel Drill-Down Kontekstual Kaya Data (Muncul Mulus Saat Kategori Diklik) -->
        <div
            v-if="selectedLabel && activeFilteredRecords.length > 0"
            class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs transition-all"
        >
            <div class="flex flex-col gap-2 border-b border-slate-100 pb-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="rounded bg-[#0D542B] px-2.5 py-0.5 text-xs font-bold text-white">
                            Profil Rincian: {{ selectedLabel }}
                        </span>
                        <span class="text-xs font-semibold text-slate-500">
                            {{ activeFilteredRecords.length }} alumni terdata
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-slate-600">
                        <template v-if="drillDownType === 'pendidikan'">
                            Daftar perguruan tinggi tujuan, program studi lanjutan, dan jenjang pendidikan alumni yang melanjutkan studi.
                        </template>
                        <template v-else-if="drillDownType === 'bekerja'">
                            Daftar sebaran perusahaan, posisi/jabatan, provinsi lokasi kerja, dan sektor instansi alumni bekerja.
                        </template>
                        <template v-else-if="drillDownType === 'wiraswasta'">
                            Daftar rincian unit bisnis, startup, usaha mandiri, dan jabatan yang dijalankan alumni wiraswasta.
                        </template>
                        <template v-else>
                            Daftar rincian alumni pada kategori {{ selectedLabel }}.
                        </template>
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 cursor-pointer"
                    @click="emit('reset')"
                >
                    Tutup Profil
                </button>
            </div>

            <!-- TABEL 1: DRILL-DOWN PENDIDIKAN LANJUT -->
            <div v-if="drillDownType === 'pendidikan'" class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-[11px] font-bold uppercase text-slate-600">
                        <tr>
                            <th class="px-3 py-2.5">Alumni</th>
                            <th class="px-3 py-2.5">Prodi Asal UKDW</th>
                            <th class="px-3 py-2.5">Perguruan Tinggi Lanjut</th>
                            <th class="px-3 py-2.5">Prodi Lanjut</th>
                            <th class="px-3 py-2.5 text-center">Jenjang</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-800">
                        <tr v-for="r in activeFilteredRecords" :key="r.biodata_id" class="hover:bg-slate-50/80">
                            <td class="px-3 py-2 font-semibold">
                                {{ r.nama }}
                                <span class="block text-[10px] text-slate-400 font-normal">NIM: {{ r.nim }}</span>
                            </td>
                            <td class="px-3 py-2">{{ r.nama_prodi }}</td>
                            <td class="px-3 py-2 font-medium text-slate-900">{{ r.study_univ || 'UKDW / Perguruan Tinggi Mitra' }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ r.study_prodi || 'Program Pascasarjana' }}</td>
                            <td class="px-3 py-2 text-center">
                                <span class="rounded bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-700">
                                    {{ r.study_jenjang || 'S2' }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- TABEL 2: DRILL-DOWN BEKERJA (Perusahaan & Lokasi) -->
            <div v-else-if="drillDownType === 'bekerja'" class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-[11px] font-bold uppercase text-slate-600">
                        <tr>
                            <th class="px-3 py-2.5">Alumni</th>
                            <th class="px-3 py-2.5">Prodi Asal</th>
                            <th class="px-3 py-2.5">Nama Perusahaan / Instansi</th>
                            <th class="px-3 py-2.5">Posisi / Jabatan</th>
                            <th class="px-3 py-2.5">Provinsi Penempatan</th>
                            <th class="px-3 py-2.5">Sektor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-800">
                        <tr v-for="r in activeFilteredRecords" :key="r.biodata_id" class="hover:bg-slate-50/80">
                            <td class="px-3 py-2 font-semibold">
                                {{ r.nama }}
                                <span class="block text-[10px] text-slate-400 font-normal">NIM: {{ r.nim }}</span>
                            </td>
                            <td class="px-3 py-2">{{ r.nama_prodi }}</td>
                            <td class="px-3 py-2 font-bold text-[#0D542B]">{{ r.nama_perusahaan || 'Perusahaan Mitra' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ r.posisi_jabatan || 'Staf Profesional' }}</td>
                            <td class="px-3 py-2 text-slate-600">
                                <span class="rounded bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-[#0D542B]">
                                    {{ r.workplace_province_name || 'D.I. Yogyakarta' }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-[11px] text-slate-500">{{ r.workplace_type || '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- TABEL 3: DRILL-DOWN WIRASWASTA (Usaha & Peran) -->
            <div v-else-if="drillDownType === 'wiraswasta'" class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-[11px] font-bold uppercase text-slate-600">
                        <tr>
                            <th class="px-3 py-2.5">Alumni</th>
                            <th class="px-3 py-2.5">Prodi Asal</th>
                            <th class="px-3 py-2.5">Nama Usaha / Startup</th>
                            <th class="px-3 py-2.5">Peran / Jabatan</th>
                            <th class="px-3 py-2.5">Provinsi Operasional</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-800">
                        <tr v-for="r in activeFilteredRecords" :key="r.biodata_id" class="hover:bg-slate-50/80">
                            <td class="px-3 py-2 font-semibold">
                                {{ r.nama }}
                                <span class="block text-[10px] text-slate-400 font-normal">NIM: {{ r.nim }}</span>
                            </td>
                            <td class="px-3 py-2">{{ r.nama_prodi }}</td>
                            <td class="px-3 py-2 font-bold text-amber-800">{{ r.nama_perusahaan || 'Usaha Mandiri Alumni' }}</td>
                            <td class="px-3 py-2">
                                <span class="rounded bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-900 border border-amber-200">
                                    {{ r.posisi_jabatan || 'Founder / Owner' }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-slate-600">{{ r.workplace_province_name || 'D.I. Yogyakarta' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- TABEL 4: DRILL-DOWN UMUM -->
            <div v-else class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-[11px] font-bold uppercase text-slate-600">
                        <tr>
                            <th class="px-3 py-2.5">Alumni</th>
                            <th class="px-3 py-2.5">Prodi</th>
                            <th class="px-3 py-2.5">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-800">
                        <tr v-for="r in activeFilteredRecords" :key="r.biodata_id" class="hover:bg-slate-50/80">
                            <td class="px-3 py-2 font-semibold">{{ r.nama }} (NIM: {{ r.nim }})</td>
                            <td class="px-3 py-2">{{ r.nama_prodi }}</td>
                            <td class="px-3 py-2 font-medium">{{ r.status }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
