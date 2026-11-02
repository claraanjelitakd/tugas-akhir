<script setup>
/**
 * Grafik pilihan tunggal dan pilihan ganda program studi.
 * Setiap pertanyaan dibuat sebagai grafik horizontal terpisah agar pilihan
 * baru otomatis mendapat bentuk visual yang sama tanpa menambah kode khusus.
 */
import { computed, ref, watch } from 'vue';
import AnalyticsEChart from '@/components/analytics/AnalyticsEChart.vue';

// Menerima daftar pertanyaan choice dari service backend.
const props = defineProps({
    items: { type: Array, default: () => [] },
});

// Event klik kategori dipakai halaman induk untuk memfilter responden pembentuk.
const emit = defineEmits(['select']);

// Menjaga setiap grafik tetap cukup tinggi untuk label pilihan yang panjang.
const chartHeight = (item) => Math.max(240, (item.categories?.length || 1) * 42 + 92);

// Grid menghitung area label dari konten sumbu agar batang memakai lebar kartu secara proporsional.
const chartGrid = { left: 20, right: 44, top: 20, bottom: 34, containLabel: true };

// Kunci pertanyaan aktif membuat satu grafik tampil dalam satu waktu agar visualisasi tidak bertabrakan.
const activeQuestionKey = ref('');

// Menjaga pertanyaan aktif tetap valid saat bagian atau filter analitik berubah.
watch(() => props.items, (items) => {
    if (!items.some((item) => item.key === activeQuestionKey.value)) {
        activeQuestionKey.value = items[0]?.key || '';
    }
}, { immediate: true });

// Pertanyaan aktif menjadi satu-satunya sumber grafik, caption, dan analisis pada panel ini.
const activeItem = computed(() => props.items.find((item) => item.key === activeQuestionKey.value) || null);

// Analisis mandiri menjelaskan pilihan dominan dari pertanyaan yang sedang dibaca.
const activeAnalysis = computed(() => {
    const item = activeItem.value;
    const categories = item?.stat?.categories || [];
    const highestCount = Math.max(...categories.map((category) => Number(category.count || 0)), 0);
    const leadingCategories = categories.filter((category) => Number(category.count || 0) === highestCount);

    if (!item || highestCount === 0) {
        return null;
    }

    const labels = leadingCategories.map((category) => `"${category.label}"`).join(', ');
    const isTie = leadingCategories.length > 1;

    return {
        lead: isTie
            ? `Belum ada satu pilihan dominan: ${labels} masing-masing dipilih ${highestCount} alumni.`
            : `Pilihan dominan adalah ${labels} dengan ${highestCount} jawaban (${leadingCategories[0].percentage}%).`,
        detail: `${item.stat?.valid_count || 0} jawaban valid membentuk distribusi pada pertanyaan ini.`,
    };
});

// Ringkasan tab memakai redaksi pertanyaan agar navigasi tetap bermakna pada layar sempit.
const tabLabel = (item) => {
    const title = String(item?.title || 'Pertanyaan');

    return title.length > 48 ? `${title.slice(0, 48).trimEnd()}…` : title;
};

// Mengirim konteks pertanyaan dan kategori yang dipilih dari ECharts.
const handleSelect = (params, item) => {
    const label = item.categories?.[params?.dataIndex];
    if (!item.key || !label) return;
    emit('select', {
        item,
        label,
        value: params?.value,
        dataIndex: params?.dataIndex,
    });
};

// Membuat opsi ECharts untuk satu pertanyaan choice.
const optionFor = (item) => ({
    tooltip: {
        trigger: 'axis',
        axisPointer: { type: 'shadow' },
        backgroundColor: '#FFFFFF',
        borderColor: '#CBD5E1',
        textStyle: { color: '#0F172A', fontSize: 12 },
        formatter: (params) => {
            const point = params?.[0];
            const category = item.stat?.categories?.[point?.dataIndex] || {};
            return `<strong>${point?.name || ''}</strong><br/>Jumlah: <b>${category.count ?? point?.value ?? 0}</b><br/>Proporsi: <b>${category.percentage ?? 0}%</b>`;
        },
    },
    grid: chartGrid,
    xAxis: {
        type: 'value',
        name: 'Jumlah alumni',
        nameLocation: 'middle',
        nameGap: 26,
        min: 0,
        axisLabel: { color: '#475569', fontSize: 11 },
        splitLine: { lineStyle: { color: '#E2E8F0', type: 'dashed' } },
    },
    yAxis: {
        type: 'category',
        inverse: true,
        data: item.categories || [],
        axisLabel: { color: '#334155', fontSize: 11, width: 200, overflow: 'break' },
        axisLine: { lineStyle: { color: '#CBD5E1' } },
    },
    series: [{
        name: 'Jumlah alumni',
        type: 'bar',
        barMaxWidth: 28,
        data: item.values || [],
        itemStyle: { color: '#0D542B', borderRadius: [0, 4, 4, 0] },
        label: {
            show: true,
            position: 'right',
            color: '#334155',
            fontWeight: 'bold',
            formatter: (params) => `${params.value} (${item.stat?.categories?.[params.dataIndex]?.percentage ?? 0}%)`,
        },
    }],
});
</script>

<template>
    <!-- Tab pertanyaan memisahkan beberapa grafik pilihan dalam satu alur pembacaan yang rapi. -->
    <section class="min-w-0">
        <nav v-if="items.length > 1" class="flex gap-1 overflow-x-auto rounded-lg border border-slate-200 bg-slate-50 p-1" aria-label="Pilihan pertanyaan grafik">
            <button v-for="item in items" :key="item.key" type="button" class="max-w-64 shrink-0 truncate rounded-md px-3 py-2 text-xs font-bold transition-colors" :class="activeQuestionKey === item.key ? 'bg-[#0D542B] text-white' : 'text-slate-600 hover:bg-white'" :title="item.title" @click="activeQuestionKey = item.key">{{ tabLabel(item) }}</button>
        </nav>

        <template v-if="activeItem">
            <h3 class="mt-4 text-sm font-bold leading-5 text-slate-900">{{ activeItem.title }}</h3>
            <p class="mt-1 text-xs leading-5 text-slate-500">{{ activeItem.subtitle }}</p>
            <p class="mt-2 text-xs leading-5 text-slate-600">{{ activeItem.caption }}</p>
            <div v-if="activeItem.categories?.length" class="mt-3 min-w-0 overflow-x-auto">
                <!-- Lebar minimum melindungi pembacaan label kategori pada layar ponsel. -->
                <div class="min-w-[640px]">
                    <AnalyticsEChart :option="optionFor(activeItem)" :height="chartHeight(activeItem)" aria-label="Grafik distribusi pilihan program studi" @select="handleSelect($event, activeItem)" />
                </div>
            </div>
            <p v-else class="mt-3 rounded-lg border border-slate-200 bg-slate-50 p-5 text-center text-sm text-slate-500">Belum ada pilihan valid.</p>

            <!-- Analisis khusus ditempatkan tepat setelah grafik pertanyaan aktif. -->
            <section v-if="activeAnalysis" class="mt-4 rounded-lg border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-xs font-bold uppercase tracking-wider text-[#0D542B]">Analisis pertanyaan aktif</p>
                <p class="mt-2 text-sm font-semibold leading-6 text-slate-900">{{ activeAnalysis.lead }}</p>
                <p class="mt-1 text-xs leading-5 text-slate-600">{{ activeAnalysis.detail }}</p>
            </section>
        </template>
    </section>
</template>
