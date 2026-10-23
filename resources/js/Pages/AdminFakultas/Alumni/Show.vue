<!--
  Halaman Detail Mahasiswa & Audit Kuesioner Tracer Study (Admin Fakultas)
  File: resources/js/Pages/AdminFakultas/Alumni/Show.vue
  
  Format Tampilan Modular:
  1. Detail Profil (Urutan 1, Komponen FormPribadi, FormAkademik, FormKarier)
  2. Kuesioner Universitas (Urutan 2, Tampilan Data Tables ala Excel)
  3. Kuesioner Program Studi: [Nama Prodi] (Urutan 3, Tampilan Data Tables ala Excel)
  4. Evaluasi Atasan (Urutan 4, Pengguna Lulusan & 13 Butir Penilaian)
  5. Hasil LinkedIn (Urutan 5, Staging & Audit Rekam Karier)
-->
<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Sidebar from '../Components/Sidebar.vue';

// Sub-komponen modular untuk setiap tab audit alumni
import AlumniStepper from '@/Components/AlumniAudit/AlumniStepper.vue';
import TabProfil from '@/Components/AlumniAudit/TabProfil.vue';
import TabKuesionerUniv from '@/Components/AlumniAudit/TabKuesionerUniv.vue';
import TabKuesionerProdi from '@/Components/AlumniAudit/TabKuesionerProdi.vue';
import TabEvaluasiAtasan from '@/Components/AlumniAudit/TabEvaluasiAtasan.vue';
import TabLinkedin from '@/Components/AlumniAudit/TabLinkedin.vue';

const props = defineProps({
    user: Object,
    fakultas: Object,
    alumni: {
        type: Object,
        required: true,
    },
    evaluasi: {
        type: Object,
        required: true,
    },
    sections: {
        type: Array,
        default: () => [],
    },
    prodiSections: {
        type: Array,
        default: () => [],
    },
    prodiEvaluasi: {
        type: Object,
        default: () => ({
            is_complete: false,
            percentage: 0,
            answered_count: 0,
            total_questions: 0,
        }),
    },
    formData: {
        type: Object,
        default: () => ({}),
    },
    provinces: {
        type: Array,
        default: () => [],
    },
    kabupatens: {
        type: Array,
        default: () => [],
    },
    companies: {
        type: Array,
        default: () => [],
    },
    negaras: {
        type: Array,
        default: () => [],
    },
    refOptions: {
        type: Object,
        default: () => ({}),
    },
    linkedinSyncResult: {
        type: Object,
        default: null,
    },
    linkedinTraceMapping: {
        type: Array,
        default: () => [],
    },
    linkedinHistory: {
        type: Array,
        default: () => [],
    },
    evaluasiAtasan: {
        type: Object,
        default: null,
    },
    pertanyaanEvaluasiAtasan: {
        type: Array,
        default: () => [],
    },
});

// Urutan Tab Utama: 'profil', 'kuesioner', 'kuesioner_prodi', 'evaluasi_atasan', 'linkedin'
const initialTab = (typeof window !== 'undefined' && new URLSearchParams(window.location.search).get('tab')) || 'profil';
const activeMainTab = ref(['profil', 'kuesioner', 'kuesioner_prodi', 'evaluasi_atasan', 'linkedin'].includes(initialTab) ? initialTab : 'profil');

// Sinkronisasi query param URL saat tab berganti tanpa reload halaman
watch(activeMainTab, (newTab) => {
    if (typeof window !== 'undefined') {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', newTab);
        window.history.replaceState({}, '', url.toString());
    }
});

// Konfigurasi Navigasi Stepper
const stepperTabs = computed(() => {
    const list = [
        {
            id: 'profil',
            title: 'Detail Profil',
            subtitle: 'Data Pribadi & Akademik',
            isComplete: !!props.evaluasi?.profile?.is_complete,
            statusText: props.evaluasi?.profile?.is_complete 
                ? 'Lengkap (100%)' 
                : `${props.evaluasi?.profile?.percentage || 0}% Terisi`,
        },
        {
            id: 'kuesioner',
            title: 'Kuesioner Universitas',
            subtitle: 'Wajib Dikti',
            isComplete: !!props.evaluasi?.questionnaire?.is_complete,
            statusText: props.evaluasi?.questionnaire?.is_complete 
                ? 'Lengkap (100%)' 
                : `${props.evaluasi?.questionnaire?.mandatory_percentage || 0}% Wajib`,
        },
    ];

    if (props.alumni?.prodi_id) {
        list.push({
            id: 'kuesioner_prodi',
            title: 'Kuesioner Prodi',
            subtitle: props.alumni.prodi?.nama_prodi || 'Program Studi',
            isComplete: !!props.prodiEvaluasi?.is_complete,
            statusText: props.prodiEvaluasi?.is_complete 
                ? 'Lengkap (100%)' 
                : `${props.prodiEvaluasi?.percentage || 0}% Terisi`,
        });
    }

    list.push({
        id: 'evaluasi_atasan',
        title: 'Evaluasi Atasan',
        subtitle: 'Pengguna Lulusan',
        isComplete: !!props.evaluasiAtasan?.is_submitted,
        statusText: props.evaluasiAtasan?.is_submitted 
            ? 'Sudah Diisi' 
            : (props.evaluasiAtasan ? 'Pending' : 'Belum Ada'),
    });

    list.push({
        id: 'linkedin',
        title: 'Hasil LinkedIn',
        subtitle: 'Audit Rekam Karier',
        isComplete: !!props.linkedinSyncResult,
        statusText: props.linkedinSyncResult 
            ? `${props.linkedinTraceMapping?.length || 0} Data Terlacak` 
            : 'Belum Ada Data',
    });

    return list;
});

const isStepperLineCompleted = (index) => {
    return !!stepperTabs.value[index]?.isComplete;
};

// Helper Semester & Yudisium
const semesterKelulusan = computed(() => {
    return props.alumni?.data_akademik?.tahun_akademik_lulus || props.alumni?.tahun_lulus || '-';
});

const statusYudisium = computed(() => {
    return props.alumni?.data_akademik?.yudisium?.proses_yudisium || 'Lulus';
});
</script>

<template>
    <Head :title="`Detail Mahasiswa - ${alumni.data_akademik?.nama || alumni.nim} - Admin Fakultas`" />

    <div class="min-h-screen bg-[#f8fafc] text-gray-800 font-sans flex overscroll-none">
        <!-- Sidebar Resmi Admin Fakultas -->
        <Sidebar :user="user" :fakultas="fakultas" />

        <!-- Area Konten Utama -->
        <div class="flex-1 flex flex-col min-w-0 lg:pl-72 overscroll-none">
            
            <!-- Header Solid Hijau Resmi UKDW #0D542B -->
            <header class="admin-page-header bg-[#0D542B] text-white py-6 px-4 sm:px-6 lg:px-8 border-b border-[#0A4322]">
                <div class="max-w-[1400px] mx-auto">
                    <div class="flex items-center gap-2 text-xs text-white/80 font-medium mb-3">
                        <Link href="/fakultas/dashboard" class="hover:underline">Dashboard</Link>
                        <span>/</span>
                        <Link href="/fakultas/alumni" class="hover:underline">Data Alumni</Link>
                        <span>/</span>
                        <span class="text-white font-bold">Detail Mahasiswa</span>
                    </div>

                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-5">
                        <div class="flex items-center gap-4">
                            <!-- Avatar Foto Profil Alumni -->
                            <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl overflow-hidden border-2 border-white/30 shadow-md bg-white/10 shrink-0 flex items-center justify-center">
                                <img 
                                    v-if="formData.foto || alumni.foto" 
                                    :src="formData.foto || alumni.foto" 
                                    :alt="alumni.data_akademik?.nama || alumni.user?.name" 
                                    class="w-full h-full object-cover object-top"
                                />
                                <span v-else class="text-white font-black text-2xl">
                                    {{ (alumni.data_akademik?.nama || alumni.user?.name || 'A').charAt(0) }}
                                </span>
                            </div>

                            <div>
                                <!-- Badges Header Solid UKDW -->
                                <div class="flex flex-wrap items-center gap-2.5 mb-2">
                                    <!-- Badge Yudisium: Kuning UKDW #FDC700 murni -->
                                    <span class="px-3.5 py-1 bg-[#FDC700] text-black font-bold text-xs rounded-full">
                                        Yudisium: {{ statusYudisium }}
                                    </span>
                                    <span class="px-3.5 py-1 bg-black/20 text-white font-medium text-xs rounded-full">
                                        Periode: {{ semesterKelulusan }}
                                    </span>
                                    <span 
                                        class="px-3.5 py-1 text-xs font-bold rounded-full"
                                        :class="evaluasi.is_complete ? 'bg-white text-[#0D542B]' : 'bg-[#FDC700] text-black'"
                                    >
                                        Status Tracer: {{ evaluasi.status }}
                                    </span>
                                </div>

                                <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">
                                    {{ alumni.data_akademik?.nama || alumni.user?.name || 'Mahasiswa UKDW' }}
                                </h1>
                                
                                <p class="text-white/90 text-xs sm:text-sm mt-1 font-medium">
                                    NIM: <span class="font-mono font-bold text-white">{{ alumni.nim }}</span> &bull; 
                                    Program Studi: <span class="font-semibold text-white">{{ alumni.prodi?.nama_prodi || '-' }}</span>
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 shrink-0 flex-wrap">
                            <a 
                                :href="`/fakultas/alumni/${alumni.id}/export-excel`"
                                class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#FDC700] hover:bg-[#e5b400] text-black text-xs font-extrabold rounded-xl transition-all shadow-sm cursor-pointer"
                                title="Download seluruh butir pertanyaan & jawaban mahasiswa ini ke file Excel (CSV)"
                            >
                                <svg class="w-4 h-4 text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                <span>Download Excel (CSV)</span>
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="admin-page-main max-w-[1400px] w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6 pb-16">

                <!-- Navigasi Stepper Tahapan Audit Alumni Terpadu -->
                <AlumniStepper :tabs="stepperTabs" v-model="activeMainTab" />

                <!-- ============================================================= -->
                <!-- 1. TAB DETAIL PROFIL ALUMNI                                   -->
                <!-- ============================================================= -->
                <TabProfil 
                    v-show="activeMainTab === 'profil'"
                    :alumni="alumni"
                    :form-data="formData"
                    :provinces="provinces"
                    :kabupatens="kabupatens"
                    :negaras="negaras"
                    :companies="companies"
                    :ref-options="refOptions"
                    :evaluasi-atasan="evaluasiAtasan"
                    :update-route="`/fakultas/alumni/${alumni.id}/profile`"
                />

                <!-- ============================================================= -->
                <!-- 2. TAB KUESIONER UNIVERSITAS                                  -->
                <!-- ============================================================= -->
                <TabKuesionerUniv 
                    v-show="activeMainTab === 'kuesioner'"
                    :sections="sections"
                    :evaluasi="evaluasi"
                />

                <!-- ============================================================= -->
                <!-- 3. TAB KUESIONER PROGRAM STUDI                                -->
                <!-- ============================================================= -->
                <TabKuesionerProdi 
                    v-show="activeMainTab === 'kuesioner_prodi'"
                    :alumni="alumni"
                    :prodi-sections="prodiSections"
                    :prodi-evaluasi="prodiEvaluasi"
                />

                <!-- ============================================================= -->
                <!-- 4. TAB EVALUASI ATASAN (PENGGUNA LULUSAN)                     -->
                <!-- ============================================================= -->
                <TabEvaluasiAtasan 
                    v-show="activeMainTab === 'evaluasi_atasan'"
                    :alumni="alumni"
                    :evaluasi-atasan="evaluasiAtasan"
                    :pertanyaan-evaluasi-atasan="pertanyaanEvaluasiAtasan"
                />

                <!-- ============================================================= -->
                <!-- 5. TAB HASIL SINKRONISASI & AUDIT LINKEDIN                    -->
                <!-- ============================================================= -->
                <TabLinkedin 
                    v-show="activeMainTab === 'linkedin'"
                    :alumni="alumni"
                    :linkedin-sync-result="linkedinSyncResult"
                    :linkedin-trace-mapping="linkedinTraceMapping"
                    :linkedin-history="linkedinHistory"
                    :is-super-admin="false"
                />

            </main>
        </div>
    </div>
</template>
