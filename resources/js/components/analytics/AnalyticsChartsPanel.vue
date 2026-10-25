<script setup>
/**
 * Panel visualisasi analitik universitas bertindak sebagai Hub Index.
 *
 * Mengarahkan pemilihan indikator ke 16 komponen kategori visualisasi terpisah,
 * menjaga konsistensi state seleksi cross-filter, serta mode heatmap jumlah/persentase.
 */
import { computed, defineAsyncComponent, ref, watch } from 'vue';

// Pemuatan asynchronous 16 komponen kategori analitik mandiri
const ResponseRate = defineAsyncComponent(() => import('./categories/01_ResponseRate.vue'));
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
        <!-- Bilah kontrol eksplorasi grafik dan normalisasi heatmap -->
        <section class="flex flex-col justify-between gap-3 bg-white p-4 shadow-sm sm:flex-row sm:items-center border border-slate-100">
            <div class="min-w-0">
                <p class="text-xs font-bold uppercase tracking-wide text-[#0D542B]">Eksplorasi grafik interaktif</p>
                <p class="mt-1 text-sm text-slate-600">
                    <span v-if="selectedLabel">Filter aktif: <strong class="text-slate-900">{{ selectedLabel }}</strong></span>
                    <span v-else>Setiap grafik dilengkapi tabel data ringkasan. Klik elemen grafik atau baris tabel untuk menyorot data.</span>
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex rounded bg-slate-100 p-1" aria-label="Mode heatmap">
                    <button
                        type="button"
                        class="rounded px-3 py-1.5 text-xs font-bold transition-all"
                        :class="heatmapMode === 'count' ? 'bg-white text-[#0D542B] shadow-sm' : 'text-slate-600'"
                        @click="heatmapMode = 'count'"
                    >
                        Jumlah
                    </button>
                    <button
                        type="button"
                        class="rounded px-3 py-1.5 text-xs font-bold transition-all"
                        :class="heatmapMode === 'percentage' ? 'bg-white text-[#0D542B] shadow-sm' : 'text-slate-600'"
                        @click="heatmapMode = 'percentage'"
                    >
                        Persentase
                    </button>
                </div>
                <button
                    v-if="selectedLabel"
                    type="button"
                    class="rounded bg-[#FDC700] px-3 py-1.5 text-xs font-bold text-[#0D542B] hover:bg-[#EAB800]"
                    @click="resetSelection"
                >
                    Reset pilihan
                </button>
            </div>
        </section>

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
        />
    </div>
</template>
