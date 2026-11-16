<script setup>
/**
 * Hub visualisasi Grafik Program Studi.
 *
 * Mengarahkan bagian instrumen Program Studi ke sembilan komponen kategori,
 * sehingga halaman GrafikProdi hanya menangani filter, tab, dan rincian data.
 * Opsi ECharts yang berulang berada di `prodiChartOptions.js`, sedangkan
 * aturan tab dan tampilan tiap bagian tetap terlihat langsung di kategorinya.
 */
import { computed, defineAsyncComponent } from 'vue';
import InterestAndContribution from './categories/01_InterestAndContribution.vue';
import ChoiceQuestionsChart from './ChoiceQuestionsChart.vue';

// Komponen bagian 02 dimuat saat dipilih agar bundle awal tetap kecil.
const LearningExperience = defineAsyncComponent(() => import('./categories/02_LearningExperience.vue'));
// Komponen bagian 03 dimuat saat dipilih agar bundle awal tetap kecil.
const StudyProgramValues = defineAsyncComponent(() => import('./categories/03_StudyProgramValues.vue'));
// Komponen bagian 04 dimuat saat dipilih agar bundle awal tetap kecil.
const AvailableFacilities = defineAsyncComponent(() => import('./categories/04_AvailableFacilities.vue'));
// Komponen bagian 05 dimuat saat dipilih agar bundle awal tetap kecil.
const LecturersAndStaff = defineAsyncComponent(() => import('./categories/05_LecturersAndStaff.vue'));
// Komponen bagian 06 dimuat saat dipilih agar bundle awal tetap kecil.
const CoursesOffered = defineAsyncComponent(() => import('./categories/06_CoursesOffered.vue'));
// Komponen bagian 07 dimuat saat dipilih agar bundle awal tetap kecil.
const OverallSatisfaction = defineAsyncComponent(() => import('./categories/07_OverallSatisfaction.vue'));
// Komponen bagian 08 dimuat saat dipilih agar bundle awal tetap kecil.
const StudyProgramRecommendation = defineAsyncComponent(() => import('./categories/08_StudyProgramRecommendation.vue'));
// Komponen bagian 09 dimuat saat dipilih agar bundle awal tetap kecil.
const HopesAndFeedback = defineAsyncComponent(() => import('./categories/09_HopesAndFeedback.vue'));

// Kontrak payload grafik aktif dan daftar visualisasi yang telah dihitung backend.
const props = defineProps({
    items: { type: Array, default: () => [] },
    sectionOrder: { type: Number, default: 0 },
    chartType: { type: String, default: '' },
});

// Event pemilihan elemen grafik diteruskan ke halaman untuk drill-down responsden.
const emit = defineEmits(['select', 'context-change']);

// ALUR 5: `sectionOrder` berasal dari filters.sections[].order. Angka itu
// memilih file kategori, misalnya order 1 memilih 01_InterestAndContribution.
const categoryComponents = {
    1: InterestAndContribution,
    2: LearningExperience,
    3: StudyProgramValues,
    4: AvailableFacilities,
    5: LecturersAndStaff,
    6: CoursesOffered,
    7: OverallSatisfaction,
    8: StudyProgramRecommendation,
    9: HopesAndFeedback,
};

// Grafik tidak memilih file dari nama grafik. File kategori dipilih dari urutan
// bagian kuesioner, lalu file tersebut membaca items pertanyaan yang aktif.
const activeComponent = computed(() => {
    if (props.chartType === 'choice') {
        return ChoiceQuestionsChart;
    }

    return categoryComponents[props.sectionOrder] || InterestAndContribution;
});

// Pemancar tunggal menjaga kontrak event semua kategori tetap sama.
const handleSelect = (selection) => emit('select', selection);
// Mengirim pertanyaan aktif agar KPI halaman mengikuti tab yang sedang dibaca.
const handleContextChange = (item) => emit('context-change', item);
</script>

<template>
    <!-- Satu kategori bagian dimount pada satu waktu agar grafik dan analisisnya tidak bertabrakan. -->
    <section class="min-w-0">
        <component :is="activeComponent" :items="items" @select="handleSelect" @context-change="handleContextChange" />
    </section>
</template>
