<script setup>
/**
 * Grafik pilihan tunggal dan pilihan ganda program studi.
 * Setiap pertanyaan dibuat sebagai grafik horizontal terpisah agar pilihan
 * baru otomatis mendapat bentuk visual yang sama tanpa menambah kode khusus.
 */
import { computed } from 'vue';
import AnalyticsEChart from '@/components/analytics/AnalyticsEChart.vue';

// Menerima daftar pertanyaan choice dari service backend.
const props = defineProps({
    items: { type: Array, default: () => [] },
});

// Event klik kategori dipakai halaman induk untuk memfilter responden pembentuk.
const emit = defineEmits(['select']);

// Menjaga setiap grafik tetap cukup tinggi untuk label pilihan yang panjang.
const chartHeight = (item) => Math.max(240, (item.categories?.length || 1) * 42 + 92);

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
    grid: { left: 250, right: 50, top: 20, bottom: 34, containLabel: true },
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
        axisLabel: { color: '#334155', fontSize: 11, width: 220, overflow: 'break' },
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
    <!-- Setiap pertanyaan memiliki judul, caption, dan grafik yang identik strukturnya. -->
    <div class="space-y-8">
        <section v-for="item in items" :key="item.key" class="min-w-0 border-b border-slate-100 pb-6 last:border-b-0">
            <h3 class="text-sm font-bold leading-5 text-slate-900">{{ item.title }}</h3>
            <p class="mt-1 text-xs leading-5 text-slate-500">{{ item.subtitle }}</p>
            <p class="mt-2 text-xs leading-5 text-slate-600">{{ item.caption }}</p>
            <div v-if="item.categories?.length" class="mt-2 min-w-0 overflow-x-auto">
                <AnalyticsEChart :option="optionFor(item)" :height="chartHeight(item)" aria-label="Grafik distribusi pilihan program studi" @select="handleSelect($event, item)" />
            </div>
            <p v-else class="mt-3 rounded-lg border border-slate-200 bg-slate-50 p-5 text-center text-sm text-slate-500">Belum ada pilihan valid.</p>
        </section>
    </div>
</template>
