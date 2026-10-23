<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Sidebar from '@/Pages/SuperAdmin/Components/Sidebar.vue';
import AdminPageHeader from '@/components/AdminPageHeader.vue';

const props = defineProps({
    user: Object,
    stats: {
        type: Object,
        default: () => ({
            total_pertanyaan: 0,
            total_sections: 0,
            total_alumni: 0,
            total_responden: 0,
            total_prodi: 0,
            total_pending_perusahaan: 0,
            response_rate: 0,
            response_rate_status: {
                code: 'red',
                label: 'Di bawah 50%',
            },
            total_linkedin: 0,
        }),
    },
    completionMetrics: {
        type: Object,
        default: () => ({
            population: 0,
            profile: { completed_count: 0, population: 0, percentage: 0 },
            university_questionnaire: { completed_count: 0, population: 0, percentage: 0 },
            profile_and_university: { completed_count: 0, population: 0, percentage: 0 },
        }),
    },
    prodiSummaries: {
        type: Array,
        default: () => [],
    },
    recentAlumni: {
        type: Array,
        default: () => [],
    },
    recentLogs: {
        type: Array,
        default: () => [],
    },
    pendingPerusahaanList: {
        type: Array,
        default: () => [],
    },
});

// State Tab Interaktif
const activeTab = ref('prodi'); // 'prodi' | 'alumni' | 'logs' | 'perusahaan'

// State Pencarian & Sorting Prodi
const searchProdiQuery = ref('');
const sortByRate = ref(true); // true = desc, false = asc

// State Pencarian & Filter Alumni
const searchAlumniQuery = ref('');
const filterStatus = ref('all'); // 'all' | 'responded' | 'not_responded' | 'linkedin'

const responseRateClasses = {
    red: {
        badge: 'bg-[#F8E2E2] text-[#7F1D1D] border-[#D9A5A5]',
        text: 'text-[#9B2C2C]',
        bar: 'bg-[#9B2C2C]',
    },
    yellow: {
        badge: 'bg-[#F8EDC8] text-[#6A4B00] border-[#D8B642]',
        text: 'text-[#8A6500]',
        bar: 'bg-[#B28A00]',
    },
    green: {
        badge: 'bg-[#E9F2EC] text-[#0D542B] border-[#B7D5C1]',
        text: 'text-[#0D542B]',
        bar: 'bg-[#0D542B]',
    },
};

const getResponseRateClass = (code, part) => {
    return responseRateClasses[code]?.[part] || responseRateClasses.yellow[part];
};

const getCompletionBarClass = (percentage) => {
    return percentage >= 100 ? 'bg-[#0D542B]' : 'bg-[#B28A00]';
};

// Filter & Sort Prodi se-Universitas
const filteredProdis = computed(() => {
    let list = [...props.prodiSummaries];

    if (searchProdiQuery.value.trim() !== '') {
        const q = searchProdiQuery.value.toLowerCase();
        list = list.filter(p => 
            (p.nama_prodi && p.nama_prodi.toLowerCase().includes(q)) ||
            (p.kode_prodi && String(p.kode_prodi).toLowerCase().includes(q))
        );
    }

    list.sort((a, b) => {
        return sortByRate.value 
            ? (b.response_rate - a.response_rate) 
            : (a.response_rate - b.response_rate);
    });

    return list;
});

// Filter Alumni Universitas
const filteredAlumni = computed(() => {
    let list = props.recentAlumni;

    if (filterStatus.value === 'responded') {
        list = list.filter(a => a.has_responded);
    } else if (filterStatus.value === 'not_responded') {
        list = list.filter(a => !a.has_responded);
    } else if (filterStatus.value === 'linkedin') {
        list = list.filter(a => a.has_linkedin);
    }

    if (searchAlumniQuery.value.trim() !== '') {
        const q = searchAlumniQuery.value.toLowerCase();
        list = list.filter(a => 
            (a.nama && a.nama.toLowerCase().includes(q)) ||
            (a.nim && a.nim.toLowerCase().includes(q)) ||
            (a.prodi && a.prodi.toLowerCase().includes(q))
        );
    }

    return list;
});
</script>

<template>
    <Head title="Dashboard Super Admin - Tracer Study UKDW" />

    <div class="min-h-screen bg-[#f8fafc] text-gray-800 flex font-sans">
        
        <!-- Sidebar Terpadu Super Admin -->
        <Sidebar :user="user" />

        <!-- Area Konten Utama -->
        <div class="flex-1 flex flex-col min-w-0 lg:pl-72">
            
            <AdminPageHeader
                eyebrow="Pusat Kendali"
                title="Dashboard Super Administrator"
                description="Kendali menyeluruh instrumen kuesioner universitas dan prodi, verifikasi perusahaan, direktori mahasiswa, serta audit log."
                context-label="Otoritas Pusat"
                :context-value="`${user?.name || 'Super Admin'} (Universitas)`"
            />

            <!-- Main Body Area -->
            <main class="flex-1 p-6 space-y-6 overflow-x-auto min-w-0">
                
                <!-- Notifikasi Peringatan: Pending Verifikasi Perusahaan -->
                <div 
                    v-if="stats?.total_pending_perusahaan > 0" 
                    class="bg-[#F8EDC8] border border-[#D8B642] rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                >
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-[#E7CF82] text-[#6A4B00] flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                        <div>
                            <span class="text-xs font-bold text-[#6A4B00] block">Antrean ACC Perusahaan Terpusat</span>
                            <span class="text-xs text-[#765A12]">
                                Terdapat <strong>{{ stats.total_pending_perusahaan }} perusahaan baru</strong> yang diajukan oleh alumni dari seluruh program studi menanti verifikasi Anda.
                            </span>
                        </div>
                    </div>
                    <Link 
                        href="/superadmin/perusahaan" 
                        class="inline-flex items-center justify-center px-4 py-2 text-xs font-bold rounded-xl bg-[#0D542B] text-white hover:bg-[#08381c] transition-colors shrink-0 cursor-pointer"
                    >
                        Kelola ACC Perusahaan &rarr;
                    </Link>
                </div>

                <!-- Grid 4 Kartu KPI Metrik Eksekutif (Real Data) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    
                    <!-- KPI 1: Total Alumni Terdaftar -->
                    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-2xs hover:shadow-xs transition-shadow">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Alumni</span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700">Database</span>
                        </div>
                        <div class="text-3xl font-extrabold text-slate-900 tracking-tight mt-3">
                            {{ stats?.total_alumni || 0 }}
                        </div>
                        <div class="mt-2 flex items-center justify-between text-xs text-slate-400">
                            <span>Dari {{ stats?.total_prodi || 0 }} Program Studi</span>
                            <span class="text-[#0D542B] font-semibold">{{ stats?.total_linkedin || 0 }} LinkedIn</span>
                        </div>
                    </div>

                    <!-- KPI 2: Responden Masuk & Response Rate -->
                    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-2xs hover:shadow-xs transition-shadow">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-[#0D542B] uppercase tracking-wider">Responden Masuk</span>
                            <span
                                :class="['px-2.5 py-0.5 rounded-full text-[10px] font-bold border', getResponseRateClass(stats?.response_rate_status?.code, 'badge')]"
                            >
                                {{ stats?.response_rate_status?.label || 'Belum dihitung' }}
                            </span>
                        </div>
                        <div class="text-3xl font-extrabold text-[#0D542B] tracking-tight mt-3">
                            {{ stats?.total_responden || 0 }}
                        </div>
                        <div class="mt-2 flex items-center justify-between text-xs text-slate-400">
                            <span>Tingkat respon kampus:</span>
                            <span :class="['font-bold', getResponseRateClass(stats?.response_rate_status?.code, 'text')]">
                                {{ stats?.response_rate || 0 }}%
                            </span>
                        </div>
                    </div>

                    <!-- KPI 3: Total Butir Pertanyaan & Section -->
                    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-2xs hover:shadow-xs transition-shadow">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Instrumen Kuesioner</span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700">Dikti</span>
                        </div>
                        <div class="text-3xl font-extrabold text-slate-900 tracking-tight mt-3">
                            {{ stats?.total_pertanyaan || 0 }}
                        </div>
                        <div class="mt-2 flex items-center justify-between text-xs text-slate-400">
                            <span>Terbagi dalam</span>
                            <span class="font-bold text-slate-700">{{ stats?.total_sections || 0 }} Section</span>
                        </div>
                    </div>

                    <!-- KPI 4: Pending ACC Perusahaan -->
                    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-2xs hover:shadow-xs transition-shadow">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-[#6A4B00] uppercase tracking-wider">ACC Perusahaan</span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-[#F8EDC8] text-[#6A4B00] border border-[#D8B642]">Approval</span>
                        </div>
                        <div class="text-3xl font-extrabold text-[#6A4B00] tracking-tight mt-3">
                            {{ stats?.total_pending_perusahaan || 0 }}
                        </div>
                        <div class="mt-2 flex items-center justify-between text-xs text-slate-400">
                            <span>Status:</span>
                            <span class="font-bold text-[#765A12]">Menunggu Verifikasi</span>
                        </div>
                    </div>

                </div>

                <!-- Capaian kelengkapan yang dipakai untuk dashboard universitas -->
                <section class="bg-white rounded-2xl p-5 border border-slate-200 shadow-2xs">
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2 mb-5">
                        <div>
                            <h2 class="text-sm font-extrabold text-slate-900">Kelengkapan Profil dan Kuesioner Universitas</h2>
                            <p class="text-xs text-slate-500 mt-1">
                                Persentase dihitung dari {{ completionMetrics.population || 0 }} alumni. Kuesioner prodi tidak termasuk pada ringkasan ini.
                            </p>
                        </div>
                        <span class="text-[11px] font-semibold text-slate-500">Wajib yang dilewati alur tidak dihitung sebagai kekurangan</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div
                            v-for="metric in [completionMetrics.profile, completionMetrics.university_questionnaire, completionMetrics.profile_and_university]"
                            :key="metric.label"
                            class="rounded-xl border border-slate-200 p-4"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <span class="text-xs font-bold text-slate-700">{{ metric.label }}</span>
                                <span class="text-lg font-extrabold text-[#0D542B]">{{ metric.percentage || 0 }}%</span>
                            </div>
                            <div class="mt-3 h-2 rounded-full bg-slate-200 overflow-hidden">
                                <div
                                    :class="['h-2 rounded-full transition-all duration-500', getCompletionBarClass(metric.percentage || 0)]"
                                    :style="{ width: `${Math.min(metric.percentage || 0, 100)}%` }"
                                ></div>
                            </div>
                            <p class="mt-2 text-[11px] text-slate-500">
                                {{ metric.completed_count || 0 }} dari {{ metric.population || 0 }} alumni lengkap
                            </p>
                        </div>
                    </div>
                </section>

                <!-- Modul Pintasan Aksi Cepat Superadmin (Navigation Hub) -->
                <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-2xs">
                    <div class="flex items-center justify-between mb-3.5">
                        <span class="text-xs font-bold text-slate-900 uppercase tracking-wider">Pusat Kendali Cepat Super Administrator</span>
                        <span class="text-xs text-slate-400">Akses Modul Utama</span>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3">
                        <Link 
                            href="/superadmin/alumni" 
                            class="p-3 rounded-xl bg-slate-50 hover:bg-[#E9F2EC] border border-slate-200 hover:border-[#B7D5C1] transition-all flex flex-col items-center text-center group cursor-pointer"
                        >
                            <svg class="w-5 h-5 text-slate-600 group-hover:text-[#0D542B] mb-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                            <span class="text-xs font-bold text-slate-700 group-hover:text-[#0D542B]">Direktori Alumni</span>
                        </Link>

                        <Link 
                            href="/superadmin/perusahaan" 
                            class="p-3 rounded-xl bg-slate-50 hover:bg-[#E9F2EC] border border-slate-200 hover:border-[#B7D5C1] transition-all flex flex-col items-center text-center group relative cursor-pointer"
                        >
                            <span v-if="stats?.total_pending_perusahaan > 0" class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-[#B28A00]"></span>
                            <svg class="w-5 h-5 text-slate-600 group-hover:text-[#0D542B] mb-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                            <span class="text-xs font-bold text-slate-700 group-hover:text-[#0D542B]">ACC Perusahaan</span>
                        </Link>

                        <Link 
                            href="/superadmin/linkedin-sync" 
                            class="p-3 rounded-xl bg-slate-50 hover:bg-[#E9F2EC] border border-slate-200 hover:border-[#B7D5C1] transition-all flex flex-col items-center text-center group cursor-pointer"
                        >
                            <svg class="w-5 h-5 text-slate-600 group-hover:text-[#0D542B] mb-1.5" fill="currentColor" viewBox="0 0 24 24"><path d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.28 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.75M6.46 10.9v8.37H9.2V10.9H6.46M7.83 6.45a1.62 1.62 0 1 0 0 3.24 1.62 1.62 0 0 0 0-3.24Z"/></svg>
                            <span class="text-xs font-bold text-slate-700 group-hover:text-[#0D542B]">Sync LinkedIn</span>
                        </Link>

                        <Link 
                            href="/superadmin/pertanyaan" 
                            class="p-3 rounded-xl bg-slate-50 hover:bg-[#E9F2EC] border border-slate-200 hover:border-[#B7D5C1] transition-all flex flex-col items-center text-center group cursor-pointer"
                        >
                            <svg class="w-5 h-5 text-slate-600 group-hover:text-[#0D542B] mb-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            <span class="text-xs font-bold text-slate-700 group-hover:text-[#0D542B]">Kuesioner Inti</span>
                        </Link>

                        <Link 
                            href="/superadmin/sections" 
                            class="p-3 rounded-xl bg-slate-50 hover:bg-[#E9F2EC] border border-slate-200 hover:border-[#B7D5C1] transition-all flex flex-col items-center text-center group cursor-pointer"
                        >
                            <svg class="w-5 h-5 text-slate-600 group-hover:text-[#0D542B] mb-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" /></svg>
                            <span class="text-xs font-bold text-slate-700 group-hover:text-[#0D542B]">Section Univ</span>
                        </Link>

                        <Link 
                            href="/superadmin/prodi-kuesioner" 
                            class="p-3 rounded-xl bg-slate-50 hover:bg-[#E9F2EC] border border-slate-200 hover:border-[#B7D5C1] transition-all flex flex-col items-center text-center group cursor-pointer"
                        >
                            <svg class="w-5 h-5 text-slate-600 group-hover:text-[#0D542B] mb-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                            <span class="text-xs font-bold text-slate-700 group-hover:text-[#0D542B]">Kuesioner Prodi</span>
                        </Link>

                        <Link 
                            href="/superadmin/manajemen-akun" 
                            class="p-3 rounded-xl bg-slate-50 hover:bg-[#E9F2EC] border border-slate-200 hover:border-[#B7D5C1] transition-all flex flex-col items-center text-center group cursor-pointer"
                        >
                            <svg class="w-5 h-5 text-slate-600 group-hover:text-[#0D542B] mb-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" /></svg>
                            <span class="text-xs font-bold text-slate-700 group-hover:text-[#0D542B]">Kelola Akun</span>
                        </Link>

                        <Link 
                            href="/superadmin/logs" 
                            class="p-3 rounded-xl bg-slate-50 hover:bg-[#E9F2EC] border border-slate-200 hover:border-[#B7D5C1] transition-all flex flex-col items-center text-center group cursor-pointer"
                        >
                            <svg class="w-5 h-5 text-slate-600 group-hover:text-[#0D542B] mb-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                            <span class="text-xs font-bold text-slate-700 group-hover:text-[#0D542B]">Audit Trail</span>
                        </Link>
                    </div>
                </div>

                <!-- Area Panel Interaktif: Tab Switcher 4 Tab (Prodi, Alumni, Logs, Pending Perusahaan) -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs overflow-hidden">
                    
                    <!-- Tab Header Bar -->
                    <div class="px-6 pt-5 pb-0 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-50/50">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <button 
                                type="button"
                                @click="activeTab = 'prodi'"
                                :class="[
                                    'px-3.5 py-2.5 text-xs font-bold rounded-t-xl transition-all cursor-pointer border-b-2',
                                    activeTab === 'prodi'
                                        ? 'border-[#0D542B] text-[#0D542B] bg-white shadow-2xs'
                                        : 'border-transparent text-slate-500 hover:text-slate-900'
                                ]"
                            >
                                Partisipasi Seluruh Prodi ({{ prodiSummaries.length }})
                            </button>
                            <button 
                                type="button"
                                @click="activeTab = 'alumni'"
                                :class="[
                                    'px-3.5 py-2.5 text-xs font-bold rounded-t-xl transition-all cursor-pointer border-b-2',
                                    activeTab === 'alumni'
                                        ? 'border-[#0D542B] text-[#0D542B] bg-white shadow-2xs'
                                        : 'border-transparent text-slate-500 hover:text-slate-900'
                                ]"
                            >
                                Alumni Terbaru ({{ recentAlumni.length }})
                            </button>
                            <button 
                                type="button"
                                @click="activeTab = 'logs'"
                                :class="[
                                    'px-3.5 py-2.5 text-xs font-bold rounded-t-xl transition-all cursor-pointer border-b-2',
                                    activeTab === 'logs'
                                        ? 'border-[#0D542B] text-[#0D542B] bg-white shadow-2xs'
                                        : 'border-transparent text-slate-500 hover:text-slate-900'
                                ]"
                            >
                                Log Audit Sistem ({{ recentLogs.length }})
                            </button>
                            <button 
                                v-if="pendingPerusahaanList.length > 0"
                                type="button"
                                @click="activeTab = 'perusahaan'"
                                :class="[
                                    'px-3.5 py-2.5 text-xs font-bold rounded-t-xl transition-all cursor-pointer border-b-2',
                                    activeTab === 'perusahaan'
                                        ? 'border-[#B28A00] text-[#6A4B00] bg-white shadow-2xs'
                                        : 'border-transparent text-slate-500 hover:text-slate-900'
                                ]"
                            >
                                Antrean ACC Perusahaan ({{ pendingPerusahaanList.length }})
                            </button>
                        </div>

                        <Link 
                            :href="activeTab === 'alumni' ? '/superadmin/alumni' : (activeTab === 'logs' ? '/superadmin/logs' : (activeTab === 'perusahaan' ? '/superadmin/perusahaan' : '/superadmin/alumni'))"
                            class="text-xs font-bold text-[#0D542B] hover:underline mb-2 sm:mb-0"
                        >
                            Lihat Modul Lengkap &rarr;
                        </Link>
                    </div>

                    <!-- TAB KONTEN 1: MATRIKS PARTISIPASI SELURUH PRODI KAMPUS -->
                    <div v-if="activeTab === 'prodi'" class="p-6 space-y-4">
                        
                        <!-- Filter & Sorting Bar -->
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <button 
                                    @click="sortByRate = !sortByRate" 
                                    type="button"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-300 text-xs font-bold text-slate-700 hover:bg-slate-50 cursor-pointer"
                                >
                                    <span>Urutkan Response Rate:</span>
                                    <span class="text-[#0D542B]">{{ sortByRate ? 'Tertinggi ↓' : 'Terendah ↑' }}</span>
                                </button>
                            </div>

                            <div class="relative max-w-xs w-full">
                                <input 
                                    v-model="searchProdiQuery" 
                                    type="text" 
                                    placeholder="Cari program studi..." 
                                    class="w-full pl-8 pr-3 py-1.5 text-xs rounded-xl border border-slate-300 focus:outline-hidden focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B] bg-white"
                                />
                                <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                            </div>
                        </div>

                        <!-- Tabel Partisipasi Prodi Interaktif -->
                        <div class="overflow-x-auto rounded-xl border border-slate-200">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 text-slate-600 font-bold border-b border-slate-200 uppercase tracking-wider text-[11px]">
                                    <tr>
                                        <th class="py-3 px-4">Kode</th>
                                        <th class="py-3 px-4">Program Studi</th>
                                        <th class="py-3 px-4 text-center">Total Alumni</th>
                                        <th class="py-3 px-4 text-center">Responden Masuk</th>
                                        <th class="py-3 px-4">Tingkat Partisipasi</th>
                                        <th class="py-3 px-4 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr 
                                        v-for="p in filteredProdis" 
                                        :key="p.id" 
                                        class="hover:bg-slate-50/80 transition-colors"
                                    >
                                        <td class="py-3 px-4 font-mono font-bold text-slate-600">
                                            {{ p.kode_prodi }}
                                        </td>
                                        <td class="py-3 px-4 font-bold text-slate-900">
                                            {{ p.nama_prodi }}
                                        </td>
                                        <td class="py-3 px-4 text-center font-semibold text-slate-700">
                                            {{ p.total_alumni }}
                                        </td>
                                        <td class="py-3 px-4 text-center font-bold text-[#0D542B]">
                                            {{ p.total_responden }}
                                        </td>
                                        <td class="py-3 px-4 min-w-[160px]">
                                            <div class="flex items-center gap-2">
                                                <div class="flex-1 bg-slate-200 rounded-full h-2 overflow-hidden">
                                                    <div 
                                                        :class="[getResponseRateClass(p.response_rate_status?.code, 'bar'), 'h-2 rounded-full transition-all duration-500']"
                                                        :style="{ width: `${p.response_rate}%` }"
                                                    ></div>
                                                </div>
                                                <span :class="['font-extrabold text-xs w-12 text-right', getResponseRateClass(p.response_rate_status?.code, 'text')]">
                                                    {{ p.response_rate }}%
                                                </span>
                                            </div>
                                            <span :class="['inline-flex mt-1 px-2 py-0.5 rounded-full border text-[10px] font-bold', getResponseRateClass(p.response_rate_status?.code, 'badge')]">
                                                {{ p.response_rate_status?.label || 'Belum dihitung' }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-right">
                                            <Link 
                                                :href="`/superadmin/alumni?prodi_id=${p.id}`" 
                                                class="inline-flex items-center px-2.5 py-1 text-[11px] font-bold rounded-lg border border-slate-300 text-[#0D542B] hover:bg-[#E9F2EC] transition-colors cursor-pointer"
                                            >
                                                Filter Alumni &rarr;
                                            </Link>
                                        </td>
                                    </tr>
                                    <tr v-if="filteredProdis.length === 0">
                                        <td colspan="6" class="py-8 text-center text-slate-400">
                                            Tidak ada program studi yang cocok dengan pencarian.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- TAB KONTEN 2: PRATINJAU ALUMNI TERBARU -->
                    <div v-else-if="activeTab === 'alumni'" class="p-6 space-y-4">
                        
                        <!-- Toolbar Filter Alumni -->
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <button 
                                    @click="filterStatus = 'all'" 
                                    :class="['px-3 py-1.5 text-xs font-bold rounded-lg cursor-pointer transition-colors', filterStatus === 'all' ? 'bg-[#0D542B] text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200']"
                                >
                                    Semua
                                </button>
                                <button 
                                    @click="filterStatus = 'responded'" 
                                    :class="['px-3 py-1.5 text-xs font-bold rounded-lg cursor-pointer transition-colors', filterStatus === 'responded' ? 'bg-[#0D542B] text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200']"
                                >
                                    Sudah Respon
                                </button>
                                <button 
                                    @click="filterStatus = 'not_responded'" 
                                    :class="['px-3 py-1.5 text-xs font-bold rounded-lg cursor-pointer transition-colors', filterStatus === 'not_responded' ? 'bg-[#0D542B] text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200']"
                                >
                                    Belum Respon
                                </button>
                                <button 
                                    @click="filterStatus = 'linkedin'" 
                                    :class="['px-3 py-1.5 text-xs font-bold rounded-lg cursor-pointer transition-colors', filterStatus === 'linkedin' ? 'bg-[#0D542B] text-white' : 'bg-[#E9F2EC] text-[#0D542B] hover:bg-[#D6E8DC]']"
                                >
                                    Ada LinkedIn
                                </button>
                            </div>

                            <div class="relative max-w-xs w-full">
                                <input 
                                    v-model="searchAlumniQuery" 
                                    type="text" 
                                    placeholder="Cari NIM, Nama, atau Prodi..." 
                                    class="w-full pl-8 pr-3 py-1.5 text-xs rounded-xl border border-slate-300 focus:outline-hidden focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B] bg-white"
                                />
                                <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                            </div>
                        </div>

                        <!-- Tabel Alumni Interaktif -->
                        <div class="overflow-x-auto rounded-xl border border-slate-200">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 text-slate-600 font-bold border-b border-slate-200 uppercase tracking-wider text-[11px]">
                                    <tr>
                                        <th class="py-3 px-4">NIM & Mahasiswa</th>
                                        <th class="py-3 px-4">Program Studi</th>
                                        <th class="py-3 px-4 text-center">Tahun Lulus</th>
                                        <th class="py-3 px-4 text-center">Status Respon</th>
                                        <th class="py-3 px-4 text-center">LinkedIn</th>
                                        <th class="py-3 px-4 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr 
                                        v-for="alumni in filteredAlumni" 
                                        :key="alumni.id" 
                                        class="hover:bg-slate-50/80 transition-colors"
                                    >
                                        <td class="py-3 px-4">
                                            <div class="font-bold text-slate-900">{{ alumni.nama }}</div>
                                            <div class="text-[11px] text-slate-500 font-mono">{{ alumni.nim }}</div>
                                        </td>
                                        <td class="py-3 px-4 font-semibold text-slate-700">
                                            {{ alumni.prodi }}
                                        </td>
                                        <td class="py-3 px-4 text-center font-medium text-slate-700">
                                            {{ alumni.tahun_lulus }}
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <span 
                                                v-if="alumni.has_responded" 
                                                class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#E9F2EC] text-[#0D542B] border border-[#B7D5C1]"
                                            >
                                                Sudah Respon
                                            </span>
                                            <span 
                                                v-else 
                                                class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#F8EDC8] text-[#6A4B00] border border-[#D8B642]"
                                            >
                                                Belum Mengisi
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <span 
                                                v-if="alumni.has_linkedin" 
                                                class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#E9F2EC] text-[#0D542B] border border-[#B7D5C1]"
                                            >
                                                Terhubung
                                            </span>
                                            <span v-else class="text-slate-400 text-[11px]">-</span>
                                        </td>
                                        <td class="py-3 px-4 text-right">
                                            <Link 
                                                :href="`/superadmin/alumni/${alumni.id}`" 
                                                class="inline-flex items-center px-2.5 py-1 text-[11px] font-bold rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100 transition-colors cursor-pointer"
                                            >
                                                Audit &rarr;
                                            </Link>
                                        </td>
                                    </tr>
                                    <tr v-if="filteredAlumni.length === 0">
                                        <td colspan="6" class="py-8 text-center text-slate-400">
                                            Tidak ada data alumni yang cocok dengan kriteria pencarian.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- TAB KONTEN 3: AUDIT TRAIL LOG SISTEM TERKINI -->
                    <div v-else-if="activeTab === 'logs'" class="p-6">
                        <div class="space-y-3">
                            <div 
                                v-for="log in recentLogs" 
                                :key="log.id" 
                                class="p-4 rounded-xl border border-slate-200 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-50 transition-colors"
                            >
                                <div class="flex items-start gap-3">
                                    <span class="w-8 h-8 rounded-lg bg-slate-200 text-slate-700 font-bold flex items-center justify-center text-xs shrink-0 mt-0.5">
                                        LOG
                                    </span>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-bold text-slate-900">{{ log.user_name }}</span>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-200 text-slate-700 uppercase">
                                                {{ log.action }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-slate-600 mt-1 leading-relaxed">{{ log.description }}</p>
                                    </div>
                                </div>
                                <div class="text-right shrink-0 text-[11px] text-slate-400 font-mono">
                                    <div>{{ log.created_at }}</div>
                                    <div v-if="log.ip_address" class="text-[10px]">{{ log.ip_address }}</div>
                                </div>
                            </div>

                            <div v-if="recentLogs.length === 0" class="py-8 text-center text-slate-400">
                                Belum ada rekaman log audit aktivitas terbaru.
                            </div>
                        </div>
                    </div>

                    <!-- TAB KONTEN 4: ANTREAN ACC PERUSAHAAN -->
                    <div v-else-if="activeTab === 'perusahaan'" class="p-6">
                        <div class="space-y-3">
                            <div 
                                v-for="c in pendingPerusahaanList" 
                                :key="c.id" 
                                class="p-4 rounded-xl border border-[#D8B642] bg-[#F8EDC8] flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-[#F3E4B4] transition-colors"
                            >
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold text-slate-900">{{ c.nama }}</span>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#E7CF82] text-[#6A4B00] border border-[#D8B642]">
                                            Menunggu Verifikasi
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-600 mt-1">
                                        Sektor: <strong>{{ c.sektor }}</strong> &bull; Wilayah: <strong>{{ c.kota }}</strong> &bull; Diusulkan oleh alumni prodi: <strong>{{ c.prodi }}</strong> ({{ c.created_at }})
                                    </p>
                                </div>
                                <Link 
                                    href="/superadmin/perusahaan" 
                                    class="inline-flex items-center px-3 py-1.5 text-xs font-bold rounded-lg bg-[#0D542B] text-white hover:bg-[#08381c] transition-colors shrink-0 cursor-pointer"
                                >
                                    Verifikasi Sekarang &rarr;
                                </Link>
                            </div>

                            <div v-if="pendingPerusahaanList.length === 0" class="py-8 text-center text-slate-400">
                                Tidak ada antrean usulan perusahaan baru yang menunggu verifikasi saat ini.
                            </div>
                        </div>
                    </div>

                </div>

            </main>

            <!-- Footer -->
            <footer class="text-slate-400 text-xs text-center py-5 border-t border-slate-100">
                &copy; {{ new Date().getFullYear() }} Universitas Kristen Duta Wacana. Hak Cipta Dilindungi.
            </footer>

        </div>
    </div>
</template>
