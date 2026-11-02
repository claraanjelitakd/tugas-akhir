<script setup>
/**
 * Halaman grafik evaluasi pengguna lulusan dari atasan.
 * Data statistik dan pilihan pertanyaan berasal dari AtasanAnalyticsService;
 * komponen ini hanya memilih bentuk visualisasi dan mengelola filter halaman.
 */
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import Sidebar from '../Components/Sidebar.vue';
import AnalyticsEChart from '@/components/analytics/AnalyticsEChart.vue';

// Props awal berisi payload evaluasi atasan dari controller.
const props = defineProps({
    user: { type: Object, default: () => ({}) },
    analytics: { type: Object, default: () => ({ filters: {}, summary: {}, question_stats: [], records: [] }) },
});

// Menyimpan payload aktif agar filter dapat memperbarui grafik tanpa reload penuh.
const currentAnalytics = ref(props.analytics);
// Menandai proses pemuatan data JSON.
const isLoading = ref(false);
// Menyimpan pesan error yang dapat dibaca pengguna.
const loadError = ref('');
// Menahan request agar perubahan filter berurutan tidak membuat banyak query.
let filterTimer = null;

// Filter tahun lulusan dan program studi yang aktif.
const selectedYear = ref(String(props.analytics?.filters?.selected_year || 'ALL'));
const selectedProdiId = ref(String(props.analytics?.filters?.selected_prodi_id || ''));

// Mengambil bagian payload yang dipakai bersama oleh kartu, grafik, dan tabel.
const filters = computed(() => currentAnalytics.value?.filters || {});
const summary = computed(() => currentAnalytics.value?.summary || {});
const questionStats = computed(() => currentAnalytics.value?.question_stats || []);
const readiness = computed(() => currentAnalytics.value?.readiness || {});
const prodiSummary = computed(() => currentAnalytics.value?.prodi_summary || []);
const records = computed(() => currentAnalytics.value?.records || []);

// Memformat angka tanpa mengubah nilai asli dari backend.
const formatNumber = (value, digits = 0) => Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: digits });

// Grafik rata-rata aspek Likert hanya memakai pertanyaan yang memiliki jawaban valid.
const scoreOption = computed(() => {
    const items = questionStats.value.filter((item) => item.type === 'likert_5' && item.valid_count > 0);

    return {
        grid: { left: 250, right: 40, top: 20, bottom: 35, containLabel: false },
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, formatter: (params) => `${params?.[0]?.name || '-'}<br/>Rata-rata: ${params?.[0]?.value ?? '-'}` },
        xAxis: { type: 'value', min: 0, max: 5, interval: 1, name: 'Skor (1-5)' },
        yAxis: { type: 'category', inverse: true, data: items.map((item) => item.aspek) },
        series: [{ type: 'bar', name: 'Rata-rata skor', data: items.map((item) => item.average), itemStyle: { color: '#0D542B', borderRadius: [0, 5, 5, 0] }, label: { show: true, position: 'right', formatter: '{c}' } }],
    };
});

// Grafik kesiapan kerja memakai seluruh opsi aktif dari metadata pertanyaan.
const readinessOption = computed(() => ({
    grid: { left: 30, right: 30, top: 35, bottom: 55, containLabel: true },
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, formatter: (params) => `${params?.[0]?.name || '-'}<br/>Jawaban: ${params?.[0]?.value ?? 0}` },
    xAxis: { type: 'category', data: (readiness.value.distribution || []).map((item) => item.label), axisLabel: { interval: 0, rotate: 20 } },
    yAxis: { type: 'value', name: 'Jawaban' },
    series: [{ type: 'bar', name: 'Jawaban', data: (readiness.value.distribution || []).map((item) => item.count), itemStyle: { color: '#2F7D4A', borderRadius: [5, 5, 0, 0] }, label: { show: true, position: 'top' } }],
}));

// Grafik perbandingan rata-rata skor antarprogram studi.
const prodiOption = computed(() => ({
    grid: { left: 160, right: 35, top: 20, bottom: 35 },
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, formatter: (params) => `${params?.[0]?.name || '-'}<br/>Rata-rata: ${params?.[0]?.value ?? '-'}` },
    xAxis: { type: 'value', min: 0, max: 5, interval: 1, name: 'Skor (1-5)' },
    yAxis: { type: 'category', inverse: true, data: prodiSummary.value.map((item) => item.prodi) },
    series: [{ type: 'bar', name: 'Rata-rata skor', data: prodiSummary.value.map((item) => item.average), itemStyle: { color: '#78B18B', borderRadius: [0, 5, 5, 0] }, label: { show: true, position: 'right', formatter: '{c}' } }],
}));

// Memuat payload baru ketika filter tahun atau prodi berubah.
const scheduleLoad = () => {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(async () => {
        isLoading.value = true;
        loadError.value = '';
        const params = new URLSearchParams({ tahun: selectedYear.value, prodi_id: selectedProdiId.value });

        try {
            const response = await fetch(`/superadmin/analitik-atasan/data?${params.toString()}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok) {
                throw new Error('analytics-request-failed');
            }
            currentAnalytics.value = await response.json();
        } catch (error) {
            console.error('Gagal memuat grafik evaluasi atasan:', error);
            loadError.value = 'Gagal memuat data grafik evaluasi atasan.';
        } finally {
            isLoading.value = false;
        }
    }, 250);
};

// Mengembalikan filter ke seluruh tahun dan seluruh program studi.
const resetFilters = () => {
    selectedYear.value = 'ALL';
    selectedProdiId.value = '';
    scheduleLoad();
};

// Menjaga state lokal tetap mengikuti props ketika halaman Inertia dinavigasi ulang.
watch(() => props.analytics, (value) => {
    currentAnalytics.value = value;
    selectedYear.value = String(value?.filters?.selected_year || 'ALL');
    selectedProdiId.value = String(value?.filters?.selected_prodi_id || '');
}, { deep: true });

// Membatalkan debounce saat halaman berpindah agar timer tidak mengubah state komponen yang sudah dilepas.
onBeforeUnmount(() => {
    clearTimeout(filterTimer);
});
</script>

<template>
    <Head title="Grafik Evaluasi Atasan - Tracer Study" />

    <div class="min-h-screen bg-[#F5F7F8] text-slate-900 lg:flex">
        <Sidebar :user="user" active-menu="analytics-atasan" />

        <main class="min-w-0 flex-1 lg:pl-72">
            <header class="border-b border-emerald-900/40 bg-[#0D542B] px-5 py-7 text-white sm:px-8">
                <div class="mx-auto max-w-[1480px]">
                    <div class="flex flex-wrap items-center gap-2 text-xs font-medium text-emerald-100">
                        <Link href="/superadmin/dashboard" class="font-semibold hover:text-white">Dashboard</Link>
                        <span class="text-emerald-300/70">/</span>
                        <span class="rounded bg-white/10 px-2 py-0.5 font-bold text-white">Grafik Evaluasi Atasan</span>
                    </div>
                    <div class="mt-5 flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                        <div>
                            <h1 class="mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl">Grafik Evaluasi Atasan</h1>
                            <p class="mt-2 max-w-3xl text-sm leading-6 text-emerald-100/90">Ringkasan jawaban pengguna lulusan untuk menilai kesiapan dan kinerja alumni berdasarkan instrumen aktif.</p>
                        </div>
                        <p class="text-xs text-emerald-100/90">Sumber aktif: Evaluasi Pengguna Lulusan</p>
                    </div>
                </div>
            </header>

            <div class="mx-auto mt-5 max-w-[1480px] space-y-5 px-5 pb-8 sm:px-8">
                <section class="bg-white p-5 shadow-lg shadow-slate-900/5">
                    <div class="grid gap-4 xl:grid-cols-[minmax(18rem,2fr)_minmax(15rem,1fr)_auto] xl:items-end">
                        <label class="block min-w-0 text-xs font-bold uppercase tracking-wide text-slate-500">
                            Program studi
                            <select v-model="selectedProdiId" class="mt-2 block w-full rounded bg-slate-100 px-3 py-3 text-sm font-bold normal-case text-slate-900 outline-none focus:bg-white focus:ring-2 focus:ring-[#0D542B]" @change="scheduleLoad">
                                <option value="">Semua program studi</option>
                                <option v-for="prodi in filters.prodis || []" :key="prodi.id" :value="String(prodi.id)">{{ prodi.nama_prodi }}</option>
                            </select>
                        </label>
                        <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">
                            Tahun lulusan
                            <select v-model="selectedYear" class="mt-2 block w-full rounded bg-slate-100 px-3 py-3 text-sm font-bold normal-case text-slate-900 outline-none focus:bg-white focus:ring-2 focus:ring-[#0D542B]" @change="scheduleLoad">
                                <option v-for="year in filters.available_years || ['ALL']" :key="year" :value="String(year)">{{ year === 'ALL' ? 'Semua Tahun' : year }}</option>
                            </select>
                        </label>
                        <button type="button" class="rounded bg-slate-100 px-4 py-3 text-sm font-bold text-slate-700 transition-colors hover:bg-slate-200" @click="resetFilters">Reset filter</button>
                    </div>
                    <div v-if="isLoading || loadError" class="mt-4 border-t border-slate-100 pt-3 text-sm">
                        <p v-if="isLoading" class="font-semibold text-[#0D542B]">Memperbarui grafik evaluasi atasan.</p>
                        <p v-if="loadError" class="font-semibold text-rose-700">{{ loadError }}</p>
                    </div>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                    <div class="grid grid-cols-2 gap-3.5 lg:grid-cols-5">
                        <div v-for="card in [
                            { label: 'Populasi evaluasi', value: formatNumber(summary.population), note: 'Undangan pada filter aktif' },
                            { label: 'Sudah mengisi', value: formatNumber(summary.submitted), note: 'Respons terkirim' },
                            { label: 'Belum mengisi', value: formatNumber(summary.pending), note: 'Menunggu jawaban atasan' },
                            { label: 'Response rate', value: `${formatNumber(summary.response_rate, 1)}%`, note: 'Respons terkirim / populasi' },
                            { label: 'Rata-rata skor', value: summary.average_score ?? '-', note: 'Skor bulat aspek Likert' },
                        ]" :key="card.label" class="flex min-h-28 flex-col justify-between rounded-xl border border-slate-200 bg-white p-4">
                            <dt class="text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ card.label }}</dt>
                            <dd class="mt-1 text-2xl font-extrabold tracking-tight text-slate-900">{{ card.value }}</dd>
                            <p class="mt-2 border-t border-slate-100 pt-2 text-xs text-slate-500">{{ card.note }}</p>
                        </div>
                    </div>
                </section>

                <section v-if="!currentAnalytics.has_data" class="rounded-xl border border-slate-200 bg-white p-8 text-center">
                    <h2 class="text-xl font-extrabold">Belum ada evaluasi atasan pada filter ini</h2>
                    <p class="mt-2 text-sm text-slate-600">Grafik akan terisi otomatis setelah atasan mengirimkan evaluasi pengguna lulusan.</p>
                </section>

                <template v-else>
                    <section class="grid gap-5 xl:grid-cols-2">
                        <article class="rounded-xl border border-slate-200 bg-white p-5">
                            <h2 class="text-lg font-extrabold">Rata-rata Aspek Kinerja Alumni</h2>
                            <p class="mt-1 text-sm text-slate-500">Skor setiap aspek dihitung dari pilihan jawaban master yang tersimpan pada respons valid.</p>
                            <div class="mt-4 min-h-[420px]"><AnalyticsEChart :option="scoreOption" :loading="isLoading" :height="420" aria-label="Grafik rata-rata aspek kinerja alumni" /></div>
                        </article>

                        <article class="rounded-xl border border-slate-200 bg-white p-5">
                            <h2 class="text-lg font-extrabold">Tingkat Kesiapan Alumni</h2>
                            <p class="mt-1 text-sm text-slate-500">Distribusi jawaban mengikuti pilihan aktif pada pertanyaan kesiapan kerja.</p>
                            <div class="mt-4 min-h-[420px]"><AnalyticsEChart :option="readinessOption" :loading="isLoading" :height="420" aria-label="Grafik distribusi kesiapan alumni" /></div>
                        </article>

                        <article class="rounded-xl border border-slate-200 bg-white p-5 xl:col-span-2">
                            <h2 class="text-lg font-extrabold">Perbandingan Rata-rata antarprogram Studi</h2>
                            <p class="mt-1 text-sm text-slate-500">Perbandingan hanya memakai evaluasi terkirim dan skor pilihan yang dapat dipetakan dari master pertanyaan.</p>
                            <div class="mt-4 min-h-[420px]"><AnalyticsEChart :option="prodiOption" :loading="isLoading" :height="420" aria-label="Grafik perbandingan rata-rata evaluasi antarprogram studi" /></div>
                        </article>
                    </section>

                    <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                        <h2 class="text-lg font-extrabold">Data Respon Evaluasi</h2>
                        <p class="mt-1 text-sm text-slate-500">Tabel ini menampilkan data pembentuk grafik agar setiap skor dapat ditelusuri kembali ke evaluasi atasan.</p>
                        <div class="mt-4 overflow-x-auto">
                            <table class="w-full min-w-[820px] text-left text-xs">
                                <thead class="border-b border-slate-200 bg-slate-50 text-[11px] uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th class="px-3 py-3">Alumni</th>
                                        <th class="px-3 py-3">Program Studi</th>
                                        <th class="px-3 py-3">Perusahaan</th>
                                        <th class="px-3 py-3">Atasan</th>
                                        <th class="px-3 py-3 text-center">Jawaban</th>
                                        <th class="px-3 py-3 text-center">Rata-rata</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="record in records" :key="record.evaluasi_id" class="hover:bg-slate-50">
                                        <td class="px-3 py-3"><div class="font-bold text-slate-900">{{ record.nama || '-' }}</div><div class="text-[11px] text-slate-500">{{ record.nim || '-' }}</div></td>
                                        <td class="px-3 py-3 text-slate-700">{{ record.nama_prodi || '-' }}</td>
                                        <td class="px-3 py-3 text-slate-700">{{ record.nama_perusahaan || '-' }}</td>
                                        <td class="px-3 py-3 text-slate-700">{{ record.nama_atasan || '-' }}</td>
                                        <td class="px-3 py-3 text-center font-semibold text-slate-700">{{ record.answers_count }}</td>
                                        <td class="px-3 py-3 text-center font-bold text-[#0D542B]">{{ record.average_score ?? '-' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="rounded-xl border border-slate-200 bg-white p-5">
                        <h2 class="text-sm font-extrabold uppercase tracking-wide text-[#0D542B]">Metodologi Perhitungan</h2>
                        <div class="mt-3 grid gap-3 text-sm leading-6 text-slate-600 md:grid-cols-3">
                            <p>{{ currentAnalytics.methodology?.formula }}</p>
                            <p>{{ currentAnalytics.methodology?.scale }}</p>
                            <p>{{ currentAnalytics.methodology?.denominator }}</p>
                        </div>
                        <p class="mt-3 border-t border-slate-100 pt-3 text-xs text-slate-500">Sumber: {{ currentAnalytics.methodology?.source }}</p>
                    </section>
                </template>
            </div>
        </main>
    </div>
</template>
