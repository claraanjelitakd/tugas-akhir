<script setup>
/**
 * Panel visualisasi analitik universitas bertindak sebagai Hub Index.
 *
 * Mengarahkan pemilihan indikator ke 16 komponen kategori visualisasi terpisah,
 * menjaga konsistensi state seleksi cross-filter, serta mode heatmap jumlah/persentase.
 */
import { computed, defineAsyncComponent, ref, watch } from 'vue';

import ResponseRate from './categories/01_ResponseRate.vue';
const LearningMethods = defineAsyncComponent(() => import('./categories/02_LearningMethods.vue'));
const JobSearchStart = defineAsyncComponent(() => import('./categories/03_JobSearchStart.vue'));
const JobSearchMethods = defineAsyncComponent(() => import('./categories/04_JobSearchMethods.vue'));
const WaitTime = defineAsyncComponent(() => import('./categories/05_WaitTime.vue'));
const ApplicationPipeline = defineAsyncComponent(() => import('./categories/06_ApplicationPipeline.vue'));
const EmploymentStatus = defineAsyncComponent(() => import('./categories/07_EmploymentStatus.vue'));
const JobSearchActivity = defineAsyncComponent(() => import('./categories/08_JobSearchActivity.vue'));
const WorkplaceType = defineAsyncComponent(() => import('./categories/09_WorkplaceType.vue'));
const FurtherStudy = defineAsyncComponent(() => import('./categories/10_FurtherStudy.vue'));
const SalaryVsUmp = defineAsyncComponent(() => import('./categories/11_SalaryVsUmp.vue'));
const FieldAlignment = defineAsyncComponent(() => import('./categories/12_FieldAlignment.vue'));
const EducationAlignment = defineAsyncComponent(() => import('./categories/13_EducationAlignment.vue'));
const EducationAlignmentUniv = defineAsyncComponent(() => import('./categories/14_EducationAlignmentUniv.vue'));
const MismatchReasons = defineAsyncComponent(() => import('./categories/15_MismatchReasons.vue'));
const CompetencyGap = defineAsyncComponent(() => import('./categories/16_CompetencyGap.vue'));
const ProfileCompletion = defineAsyncComponent(() => import('./categories/17_ProfileCompletion.vue'));
const WorkLocation = defineAsyncComponent(() => import('./categories/18_WorkLocation.vue'));

// Kontrak properti data indikator aktif dari controller
const props = defineProps({
    chart: { type: Object, default: () => ({}) },
    selectedChart: { type: String, default: 'response_rate' },
    availableUmpYears: { type: Array, default: () => [] },
    selectedUmpYear: { type: String, default: '' },
    loading: { type: Boolean, default: false },
});

// Event diteruskan ke halaman induk untuk koordinasi filter global
const emit = defineEmits(['select-category', 'select-province', 'change-ump-year', 'show-data', 'show-methodology']);

// Selection lokal untuk cross-filter
const selectedLabel = ref('');
const heatmapMode = ref('count');

// Registry pemetaan key indikator ke komponen kategori terkait
const categoryComponents = {
    response_rate: ResponseRate,
    learning_methods: LearningMethods,
    job_search_start: JobSearchStart,
    job_search_methods: JobSearchMethods,
    wait_time: WaitTime,
    application_pipeline: ApplicationPipeline,
    employment_status: EmploymentStatus,
    job_search_activity: JobSearchActivity,
    workplace_type: WorkplaceType,
    further_study: FurtherStudy,
    salary_vs_ump: SalaryVsUmp,
    field_alignment: FieldAlignment,
    education_alignment: EducationAlignment,
    education_alignment_university: EducationAlignmentUniv,
    mismatch_reasons: MismatchReasons,
    competency_gap: CompetencyGap,
    profile_completion: ProfileCompletion,
    work_location: WorkLocation,
};

// Komponen aktif berdasarkan indikator yang dipilih di filter
const activeComponent = computed(() => categoryComponents[props.selectedChart] || ResponseRate);

// Normalisasi label hasil klik pada elemen chart atau tabel data
const selectionFromParams = (params) => {
    const data = params?.data || {};
    const label = data.category || data.target || data.name || data.prodi || params?.name || '';

    return {
        ...data,
        label,
        series_name: params?.seriesName || '',
        data_type: params?.dataType || '',
    };
};

// Klik chart atau baris tabel memperbarui selection
const selectPoint = (params) => {
    const selection = selectionFromParams(params);
    if (!selection.label) return;

    const isClearing = selectedLabel.value === selection.label;
    selectedLabel.value = isClearing ? '' : selection.label;
    emit('select-category', isClearing ? { label: '' } : selection);
};

// Reset selection mengembalikan filter lokal dan global
const resetSelection = () => {
    selectedLabel.value = '';
    emit('select-category', { label: '' });
};

// Pergantian indikator selalu mereset filter lokal
watch(() => props.selectedChart, () => {
    selectedLabel.value = '';
    heatmapMode.value = 'count';
});
</script>

<template>
    <div class="space-y-6">
        <!-- Render komponen kategori mandiri yang sedang aktif -->
        <component
            :is="activeComponent"
            :chart="chart"
            :selected-label="selectedLabel"
            :heatmap-mode="heatmapMode"
            :available-ump-years="availableUmpYears"
            :selected-ump-year="selectedUmpYear"
            :loading="loading"
            @select="selectPoint"
            @reset="resetSelection"
            @select-province="emit('select-province', $event)"
            @change-ump-year="emit('change-ump-year', $event)"
            @show-data="emit('show-data', $event)"
        />
    </div>
</template>
