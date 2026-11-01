<script setup>
/**
 * Grafik standar untuk satu bagian pertanyaan skala Likert program studi.
 * Semua butir ditampilkan dalam satu grafik horizontal supaya label panjang
 * tetap terbaca dan pola visual antarbagian tetap konsisten.
 */
import { computed } from 'vue';
import AnalyticsEChart from '@/components/analytics/AnalyticsEChart.vue';

// Data visualisasi per butir yang sudah dihitung oleh service analitik.
const props = defineProps({
    items: { type: Array, default: () => [] },
    caption: { type: String, default: '' },
});

// Event klik membawa butir pertanyaan agar halaman induk dapat menampilkan records.
const emit = defineEmits(['select']);

// Mengatur tinggi berdasarkan jumlah butir agar grafik tidak memadat atau terpotong.
const chartHeight = computed(() => Math.max(420, props.items.length * 62 + 80));

// Memecah label panjang ke beberapa baris tanpa mengubah teks sumber.
const wrapLabel = (label) => {
    const words = String(label || '').split(/\s+/);
    const lines = [];
    let current = '';
    words.forEach((word) => {
        if ((current + ' ' + word).trim().length > 48 && current) {
            lines.push(current);
            current = word;
        } else {
            current = `${current} ${word}`.trim();
        }
    });
    if (current) lines.push(current);
    return lines.join('\n');
};

// Mengubah event ECharts menjadi payload drill-down yang stabil untuk halaman induk.
const handleSelect = (params) => {
    const item = props.items[params?.dataIndex] || {};
    if (!item.key) return;
    emit('select', {
        item,
        label: item.title,
        value: params?.value,
        dataIndex: params?.dataIndex,
    });
};

// Menyiapkan opsi ECharts dengan skala 1-5 dan satu warna hijau UKDW.
const chartOption = computed(() => ({
    tooltip: {
        trigger: 'axis',
        axisPointer: { type: 'shadow' },
        backgroundColor: '#FFFFFF',
        borderColor: '#CBD5E1',
        borderWidth: 1,
        textStyle: { color: '#0F172A', fontSize: 12 },
        formatter: (params) => {
            const point = params?.[0];
            const item = props.items[point?.dataIndex] || {};
            const stat = item.stat || {};
            return `<strong>${item.title || point?.name || ''}</strong><br/>Rata-rata: <b>${stat.average ?? 0}</b> / 5<br/>Median: <b>${stat.median ?? 0}</b><br/>Jawaban valid: ${stat.valid_count ?? 0}`;
        },
    },
    grid: { left: 340, right: 50, top: 20, bottom: 36, containLabel: true },
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
        data: props.items.map((item) => wrapLabel(item.title)),
        axisLabel: { color: '#334155', fontSize: 11, lineHeight: 16, width: 310, overflow: 'break' },
        axisLine: { lineStyle: { color: '#CBD5E1' } },
    },
    series: [{
        name: 'Rata-rata skor',
        type: 'bar',
        barMaxWidth: 28,
        data: props.items.map((item) => item.stat?.average || 0),
        itemStyle: { color: '#0D542B', borderRadius: [0, 4, 4, 0] },
        label: { show: true, position: 'right', color: '#334155', fontWeight: 'bold', formatter: '{c}' },
        markLine: { silent: true, symbol: 'none', lineStyle: { color: '#64748B', type: 'dashed' }, data: [{ xAxis: 3 }] },
    }],
}));
</script>

<template>
    <!-- Kontrak visual: caption selalu berada dekat grafik dan tidak ikut terpotong. -->
    <section class="min-w-0 space-y-3">
        <p class="text-xs leading-5 text-slate-600">{{ caption }}</p>
        <div v-if="items.length" class="min-w-0 overflow-x-auto">
            <AnalyticsEChart :option="chartOption" :height="chartHeight" aria-label="Grafik rata-rata skor Likert program studi" @select="handleSelect" />
        </div>
        <p v-else class="rounded-lg border border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">Belum ada butir yang dapat divisualisasikan.</p>
    </section>
</template>
