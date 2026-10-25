<script setup>
/**
 * Wrapper Apache ECharts untuk seluruh visualisasi analitik universitas.
 * Komponen ini mengelola lifecycle, resize, loading, dan event interaksi agar
 * setiap indikator tidak membuat implementasi chart engine sendiri.
 */
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import * as echarts from 'echarts/core';
import {
    BarChart,
    BoxplotChart,
    CustomChart,
    FunnelChart,
    HeatmapChart,
    LineChart,
    PieChart,
    RadarChart,
    SankeyChart,
    ScatterChart,
    TreemapChart,
} from 'echarts/charts';
import {
    AriaComponent,
    DataZoomComponent,
    DatasetComponent,
    GridComponent,
    LegendComponent,
    MarkLineComponent,
    TitleComponent,
    ToolboxComponent,
    TooltipComponent,
    TransformComponent,
    VisualMapComponent,
} from 'echarts/components';
import { LabelLayout, UniversalTransition } from 'echarts/features';
import { CanvasRenderer } from 'echarts/renderers';

// Registrasi hanya mencakup tipe chart dan fitur yang dipakai dashboard.
echarts.use([
    AriaComponent,
    BarChart,
    BoxplotChart,
    CanvasRenderer,
    CustomChart,
    DataZoomComponent,
    DatasetComponent,
    FunnelChart,
    GridComponent,
    HeatmapChart,
    LabelLayout,
    LegendComponent,
    LineChart,
    MarkLineComponent,
    PieChart,
    RadarChart,
    SankeyChart,
    ScatterChart,
    TitleComponent,
    ToolboxComponent,
    TooltipComponent,
    TransformComponent,
    TreemapChart,
    UniversalTransition,
    VisualMapComponent,
]);

// Props menerima opsi ECharts murni agar wrapper tetap reusable.
const props = defineProps({
    option: { type: Object, required: true },
    height: { type: [Number, String], default: 380 },
    loading: { type: Boolean, default: false },
    ariaLabel: { type: String, default: 'Grafik analitik interaktif' },
});

// Event membawa titik yang dipilih dan perubahan kontrol internal chart.
const emit = defineEmits(['select', 'legend-change', 'zoom-change']);

// Referensi DOM dan instance engine dijaga lokal untuk mencegah memory leak.
const chartElement = ref(null);
let chartInstance = null;
let resizeObserver = null;

// Menghormati preferensi reduced motion pada sistem operasi pengguna.
const prefersReducedMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;

// Menerapkan opsi baru tanpa membuat ulang instance sehingga transisi tetap halus.
const renderChart = async () => {
    await nextTick();
    if (!chartElement.value) return;

    if (!chartInstance) {
        chartInstance = echarts.init(chartElement.value, null, { renderer: 'canvas' });
        chartInstance.on('click', (params) => emit('select', params));
        chartInstance.on('legendselectchanged', (params) => emit('legend-change', params));
        chartInstance.on('datazoom', (params) => emit('zoom-change', params));
    }

    chartInstance.setOption({
        animation: !prefersReducedMotion(),
        animationDuration: prefersReducedMotion() ? 0 : 350,
        animationDurationUpdate: prefersReducedMotion() ? 0 : 280,
        animationEasingUpdate: 'cubicOut',
        ...props.option,
    }, { notMerge: true, lazyUpdate: true });
    syncLoading();
};

// Menampilkan loading native ECharts tanpa menutupi konteks chart sebelumnya.
const syncLoading = () => {
    if (!chartInstance) return;

    if (props.loading) {
        chartInstance.showLoading('default', {
            text: 'Memperbarui data',
            color: '#0D542B',
            textColor: '#334155',
            maskColor: 'rgba(255, 255, 255, 0.78)',
        });
        return;
    }

    chartInstance.hideLoading();
};

// Mengembalikan zoom dan selection internal chart ke kondisi awal.
const reset = () => {
    chartInstance?.dispatchAction({ type: 'restore' });
    renderChart();
};

// Mengunduh chart sebagai PNG dengan latar putih dan resolusi tajam.
const downloadPng = (filename = 'grafik-tracer-study') => {
    if (!chartInstance) return;

    const link = document.createElement('a');
    link.download = `${filename}.png`;
    link.href = chartInstance.getDataURL({ type: 'png', pixelRatio: 2, backgroundColor: '#FFFFFF' });
    link.click();
};

// Mengaktifkan atau menonaktifkan seluruh legend yang tersedia.
const toggleLegend = () => {
    if (!chartInstance) return;

    const option = chartInstance.getOption();
    const names = (option.series || []).map((item) => item.name).filter(Boolean);
    names.forEach((name) => chartInstance.dispatchAction({ type: 'legendToggleSelect', name }));
};

// Memaksa resize setelah container masuk mode fullscreen atau berubah layout.
const resize = () => chartInstance?.resize();

// Lifecycle membuat ResizeObserver dan membuang instance ketika komponen hilang.
onMounted(() => {
    renderChart();
    resizeObserver = new ResizeObserver(() => resize());
    if (chartElement.value) resizeObserver.observe(chartElement.value);
});

watch(() => props.option, renderChart, { deep: true });
watch(() => props.loading, syncLoading, { immediate: true });

onBeforeUnmount(() => {
    resizeObserver?.disconnect();
    chartInstance?.dispose();
    chartInstance = null;
});

defineExpose({ downloadPng, reset, resize, toggleLegend });
</script>

<template>
    <!-- Container chart memiliki tinggi eksplisit agar ECharts dapat menghitung canvas secara stabil. -->
    <div
        ref="chartElement"
        class="w-full"
        :style="{ height: typeof height === 'number' ? `${height}px` : height }"
        role="img"
        :aria-label="ariaLabel"
    ></div>
</template>
