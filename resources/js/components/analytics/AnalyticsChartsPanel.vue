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
    selectedYear: { type: String, default: '' },
    availableUmpYears: { type: Array, default: () => [] },
    selectedUmpYear: { type: String, default: '' },
    selectedLabel: { type: String, default: '' },
    loading: { type: Boolean, default: false },
});

// Event diteruskan ke halaman induk untuk koordinasi filter global
const emit = defineEmits(['select-category', 'select-province', 'change-ump-year', 'show-data', 'show-methodology']);

// Selection lokal untuk cross-filter, disinkronkan dengan prop selectedLabel
const selectedLabel = ref(props.selectedLabel || '');
const heatmapMode = ref('count');

// Sinkronisasi bila props.selectedLabel berubah dari luar (misal klik KPI card di atas)
watch(() => props.selectedLabel, (val) => {
    selectedLabel.value = val || '';
});

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
    <div class="relative space-y-6">
        <!-- Indikator status pemuatan data indikator yang jelas & profesional -->
        <transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 -translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-1"
        >
            <div
                v-if="loading"
                class="absolute -top-3 left-0 right-0 z-30 flex items-center justify-center"
            >
                <div class="inline-flex items-center gap-2 rounded-full border border-emerald-300 bg-[#0D542B] px-4 py-1.5 text-xs font-semibold text-white shadow-lg">
                    <svg class="h-3.5 w-3.5 animate-spin text-emerald-200" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Memperbarui data indikator...</span>
                </div>
            </div>
        </transition>

        <!-- Render komponen kategori mandiri yang sedang aktif dengan efek transisi opacity saat loading -->
        <div :class="{ 'opacity-60 pointer-events-none transition-opacity duration-200': loading }">
            <component
                :is="activeComponent"
                :chart="chart"
                :selected-label="selectedLabel"
                :selected-year="selectedYear"
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
    </div>
</template>
