<script setup>
/**
 * Panel visualisasi analitik universitas bertindak sebagai Hub Index.
 *
 * Mengarahkan pemilihan indikator ke 16 komponen kategori visualisasi terpisah,
 * menjaga konsistensi state seleksi cross-filter, serta mode heatmap jumlah/persentase.
 */
import { computed, onErrorCaptured, ref, watch } from 'vue';

import ResponseRate from './categories/01_ResponseRate.vue';
import LearningMethods from './categories/02_LearningMethods.vue';
import JobSearchStart from './categories/03_JobSearchStart.vue';
import JobSearchMethods from './categories/04_JobSearchMethods.vue';
import WaitTime from './categories/05_WaitTime.vue';
import ApplicationPipeline from './categories/06_ApplicationPipeline.vue';
import EmploymentStatus from './categories/07_EmploymentStatus.vue';
import JobSearchActivity from './categories/08_JobSearchActivity.vue';
import WorkplaceType from './categories/09_WorkplaceType.vue';
import FurtherStudy from './categories/10_FurtherStudy.vue';
import SalaryVsUmp from './categories/11_SalaryVsUmp.vue';
import FieldAlignment from './categories/12_FieldAlignment.vue';
import EducationAlignment from './categories/13_EducationAlignment.vue';
import MismatchReasons from './categories/15_MismatchReasons.vue';
import CompetencyGap from './categories/16_CompetencyGap.vue';
import ProfileCompletion from './categories/17_ProfileCompletion.vue';
import WorkLocation from './categories/18_WorkLocation.vue';
import IkuS1Aee from './categories/19_IkuS1Aee.vue';
import IkuS1Outcomes from './categories/20_IkuS1Outcomes.vue';
import IkuS1UmpWeighted from './categories/21_IkuS1UmpWeighted.vue';

// Kontrak properti data indikator aktif dari controller
const props = defineProps({
    user: { type: Object, default: null },
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
const componentError = ref('');

// Menampilkan error setup komponen agar kegagalan grafik tidak berubah menjadi ruang kosong.
onErrorCaptured((error, _instance, info) => {
    componentError.value = `${error?.message || 'Kesalahan tidak diketahui'} (${info})`;
    console.error('[AnalyticsChartsPanel] Gagal merender kategori grafik.', error);
    return false;
});

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
    mismatch_reasons: MismatchReasons,
    competency_gap: CompetencyGap,
    profile_completion: ProfileCompletion,
    work_location: WorkLocation,
    iku_s1_aee: IkuS1Aee,
    iku_s1_outcomes: IkuS1Outcomes,
    iku_s1_ump_weighted: IkuS1UmpWeighted,
};

// Komponen aktif berdasarkan indikator yang dipilih di filter
const activeComponent = computed(() => categoryComponents[props.selectedChart] || ResponseRate);

// Pembersih label hasil klik dari informasi jumlah sampel seperti "(n=15)"
const cleanLabel = (raw) => String(raw || '')
    .replace(/\s*\(n=\d+\)/gi, '')
    .replace(/\n+/g, ' ')
    .trim();

// Normalisasi label hasil klik pada elemen chart atau tabel data
const selectionFromParams = (params) => {
    if (typeof params === 'string') {
        const cleaned = cleanLabel(params);
        return { label: cleaned, name: cleaned };
    }
    const data = params?.data && typeof params.data === 'object' && !Array.isArray(params.data)
        ? params.data
        : {};
    const rawLabel = params?.label || data.category || data.target || data.name || data.prodi || params?.category || params?.name || '';
    const label = cleanLabel(rawLabel);

    return {
        ...data,
        ...params,
        label,
        series_name: params?.seriesName || params?.series_name || '',
        data_type: params?.dataType || params?.data_type || '',
    };
};

// Klik chart atau baris tabel memperbarui selection
const selectPoint = (params) => {
    const selection = selectionFromParams(params);
    if (!selection.label) return;

    selectedLabel.value = selection.label;
    emit('select-category', selection);
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
        <!-- Error runtime ditampilkan inline agar diagnosis grafik dapat dilakukan tanpa console. -->
        <div v-if="componentError" class="rounded-xl border border-red-200 bg-red-50 p-6" role="alert">
            <p class="text-sm font-semibold text-red-800">Grafik gagal dirender.</p>
            <p class="mt-1 text-xs leading-relaxed text-red-700">{{ componentError }}</p>
        </div>

        <div v-else :class="{ 'opacity-60 pointer-events-none transition-opacity duration-200': loading }">
            <component
                :is="activeComponent"
                :chart="chart"
                :user="user"
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
