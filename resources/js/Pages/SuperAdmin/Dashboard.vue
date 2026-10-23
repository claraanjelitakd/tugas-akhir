<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Sidebar from '@/Pages/SuperAdmin/Components/Sidebar.vue';

const props = defineProps({
    user: Object,
    availableYears: {
        type: Array,
        default: () => [],
    },
    selectedYear: {
        type: [String, Number],
        default: () => new Date().getFullYear(),
    },
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
const activeCompletionMetric = ref('profile_and_university');
const selectedYear = ref(String(props.selectedYear));

watch(() => props.selectedYear, (year) => {
    selectedYear.value = String(year);
});

const applyYearFilter = () => {
    router.get('/superadmin/dashboard', {
        tahun: selectedYear.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

// State Pencarian & Sorting Prodi
const searchProdiQuery = ref('');
const sortByRate = ref(true); // true = desc, false = asc

// State Pencarian & Filter Alumni
const searchAlumniQuery = ref('');
const filterStatus = ref('all'); // 'all' | 'responded' | 'not_responded' | 'linkedin'

const responseRateClasses = {
    red: {
        badge: 'bg-[#FDC700] text-[#0D542B]',
        text: 'text-[#0D542B]',
        bar: 'bg-[#FDC700]',
    },
    yellow: {
        badge: 'bg-[#FDC700] text-[#0D542B]',
        text: 'text-[#0D542B]',
        bar: 'bg-[#FDC700]',
    },
    green: {
        badge: 'bg-[#0D542B] text-white',
        text: 'text-[#0D542B]',
        bar: 'bg-[#0D542B]',
    },
};

const getResponseRateClass = (code, part) => {
    return responseRateClasses[code]?.[part] || responseRateClasses.yellow[part];
};

const getCompletionBarClass = (percentage) => {
    return percentage >= 100 ? 'bg-[#0D542B]' : 'bg-[#FDC700]';
};

const completionMetricOptions = [
    {
        key: 'profile_and_university',
        label: 'Gabungan',
        description: 'Profil dan kuesioner universitas lengkap',
    },
    {
        key: 'profile',
        label: 'Profil',
        description: 'Data profil alumni lengkap',
    },
    {
        key: 'university_questionnaire',
        label: 'Kuesioner Univ.',
        description: 'Kuesioner universitas lengkap',
    },
];

const selectedCompletionMetric = computed(() => {
    return props.completionMetrics?.[activeCompletionMetric.value] || {
        label: 'Kelengkapan',
        completed_count: 0,
        population: 0,
        percentage: 0,
    };
});

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
        
        <Sidebar :user="user" />

        <div class="flex-1 flex flex-col min-w-0 lg:pl-72">
            
            <header class="bg-[#0D542B] text-white pt-8 pb-16 px-4 sm:px-6 lg:px-8">
                <div class="w-full max-w-[1440px] mx-auto">
                    <nav class="flex items-center gap-2 text-xs text-emerald-100 font-medium mb-2.5">
                        <span>Tracer Study</span>
                        <span class="text-emerald-300/60">/</span>
                        <span class="text-white font-bold bg-white/10 px-2 py-0.5 rounded-md">Pusat Kendali</span>
                    </nav>

                    <h1 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-white tracking-tight">
                        Dashboard Super Administrator
                    </h1>
                    <p class="text-emerald-100/90 text-xs sm:text-sm font-normal mt-1.5 max-w-2xl leading-relaxed">
                        Pantau partisipasi, kelengkapan, dan tindak lanjut utama universitas.
                    </p>
                    <div class="mt-5 flex flex-wrap items-center gap-2">
                        <label for="dashboard-year" class="text-xs font-bold text-white">Tahun lulusan</label>
                        <select
                            id="dashboard-year"
                            v-model="selectedYear"
                            @change="applyYearFilter"
                            aria-label="Pilih tahun lulusan dashboard"
                            class="appearance-none cursor-pointer rounded-lg bg-[#FDC700] px-3 py-2 text-xs font-extrabold text-[#0D542B] outline-none focus:outline-none"
                        >
                            <option v-for="year in availableYears" :key="year" :value="year">{{ year }}</option>
                        </select>
                    </div>
                </div>
            </header>

            <main class="admin-page-main w-full max-w-[1440px] mx-auto -mt-10 px-4 sm:px-6 lg:px-8 space-y-6 pb-12">
                
                <div
                    v-if="stats?.total_pending_perusahaan > 0"
                    class="bg-[#FDC700] rounded-xl px-4 py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                >
                    <div>
                        <span class="text-xs font-bold text-[#0D542B] block">
                            {{ stats.total_pending_perusahaan }} perusahaan menunggu verifikasi
                        </span>
                        <span class="text-xs text-[#0D542B]">
                            Antrean ini ditampilkan untuk semua tahun lulusan.
                        </span>
                    </div>
                    <Link
                        href="/superadmin/perusahaan"
                        class="inline-flex items-center justify-center px-3 py-1.5 text-xs font-bold rounded-lg bg-[#0D542B] text-white hover:bg-[#08381c] transition-colors shrink-0"
                    >
                        Tinjau sekarang
                    </Link>
                </div>

                <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3.5 sm:gap-4">
                    <article class="bg-white rounded-xl p-5 shadow-sm min-w-0">
                        <div class="flex items-start justify-between gap-3">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-[#0D542B]">Response rate universitas</p>
                            <span
                                :class="['px-1.5 py-0.5 rounded text-[9px] font-bold shrink-0', getResponseRateClass(stats?.response_rate_status?.code, 'badge')]"
                            >
                                {{ stats?.response_rate_status?.label || 'Belum dihitung' }}
                            </span>
                        </div>
                        <div class="mt-4 flex items-end gap-2">
                            <p class="text-3xl sm:text-4xl font-extrabold tracking-tight text-[#0D542B]">{{ stats?.response_rate || 0 }}%</p>
                            <p class="pb-1 text-xs text-slate-500">{{ stats?.total_responden || 0 }} dari {{ stats?.total_alumni || 0 }} alumni</p>
                        </div>
                        <div class="mt-4 h-2 rounded-full bg-white overflow-hidden">
                            <div
                                :class="['h-2 rounded-full transition-all duration-500', getResponseRateClass(stats?.response_rate_status?.code, 'bar')]"
                                :style="{ width: (Math.min(stats?.response_rate || 0, 100) + '%') }"
                            ></div>
                        </div>
                    </article>

                    <article class="bg-white rounded-xl p-5 shadow-sm min-w-0">
                        <div class="flex items-start justify-between gap-3">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-600">Kelengkapan gabungan</p>
                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-[#FDC700] text-[#0D542B] shrink-0">
                                Profil + Univ.
                            </span>
                        </div>
                        <div class="mt-4 flex items-end gap-2">
                            <p class="text-3xl sm:text-4xl font-extrabold tracking-tight text-[#0D542B]">{{ completionMetrics?.profile_and_university?.percentage || 0 }}%</p>
                            <p class="pb-1 text-xs text-slate-500">{{ completionMetrics?.profile_and_university?.completed_count || 0 }} alumni lengkap</p>
                        </div>
                        <div class="mt-4 h-2 rounded-full bg-slate-200 overflow-hidden">
                            <div
                                :class="['h-2 rounded-full transition-all duration-500', getCompletionBarClass(completionMetrics?.profile_and_university?.percentage || 0)]"
                                :style="{ width: (Math.min(completionMetrics?.profile_and_university?.percentage || 0, 100) + '%') }"
                            ></div>
                        </div>
                    </article>

                    <article class="bg-white rounded-xl p-5 shadow-sm min-w-0 sm:col-span-2 xl:col-span-1">
                        <div class="flex items-start justify-between gap-3">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-600">Populasi lulusan</p>
                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-[#0D542B] text-white shrink-0">
                                Universitas
                            </span>
                        </div>
                        <div class="mt-4 flex items-end gap-2">
                            <p class="text-3xl sm:text-4xl font-extrabold tracking-tight text-slate-900">{{ stats?.total_alumni || 0 }}</p>
                            <p class="pb-1 text-xs text-slate-500">alumni terdata</p>
                        </div>
                        <p class="mt-4 text-xs text-slate-500">
                            Tahun lulusan {{ selectedYear }} pada {{ stats?.total_prodi || 0 }} program studi.
                        </p>
                    </article>
                </section>

                <div class="flex flex-wrap items-center gap-x-6 gap-y-2 px-1 text-xs text-slate-500">
                    <span>Instrumen <strong class="text-slate-700">{{ stats?.total_pertanyaan || 0 }}</strong> butir / <strong class="text-slate-700">{{ stats?.total_sections || 0 }}</strong> section</span>
                    <span><strong class="text-[#0D542B]">{{ stats?.total_linkedin || 0 }}</strong> profil LinkedIn terhubung</span>
                    <Link href="/superadmin/pertanyaan" class="font-bold text-[#0D542B] hover:underline">Kelola instrumen</Link>
                    <Link href="/superadmin/linkedin-sync" class="font-bold text-[#0D542B] hover:underline">Buka sinkronisasi LinkedIn</Link>
                </div>

                <section class="bg-white rounded-2xl shadow-sm overflow-hidden">
                    <div class="px-5 sm:px-6 py-5">
                        <h2 class="text-sm font-extrabold text-slate-900">Kelengkapan data</h2>
                        <p class="text-xs text-slate-500 mt-1">
                            Pilih satu indikator untuk melihat detail. Kuesioner prodi tidak dihitung.
                        </p>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 mt-4">
                            <button
                                v-for="option in completionMetricOptions"
                                :key="option.key"
                                type="button"
                                @click="activeCompletionMetric = option.key"
                                :title="option.description"
                                :aria-pressed="activeCompletionMetric === option.key"
                                :class="[
                                    'w-full px-3 py-2.5 rounded-lg text-xs font-bold text-left transition-colors',
                                    activeCompletionMetric === option.key
                                        ? 'bg-[#0D542B] text-white'
                                        : 'bg-slate-100 text-slate-600 hover:bg-[#FDC700] hover:text-[#0D542B]'
                                ]"
                            >
                                {{ option.label }}
                            </button>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-[1fr_1.5fr] gap-6 p-5 sm:p-6">
                        <div>
                            <p class="text-xs font-bold text-slate-500 uppercase tracking-wide">{{ selectedCompletionMetric.label }}</p>
                            <div class="mt-3 flex items-end gap-2">
                                <span class="text-4xl font-extrabold tracking-tight text-[#0D542B]">{{ selectedCompletionMetric.percentage || 0 }}%</span>
                                <span class="pb-1 text-xs text-slate-500">lengkap</span>
                            </div>
                            <p class="mt-2 text-xs text-slate-500">
                                {{ selectedCompletionMetric.completed_count || 0 }} dari {{ selectedCompletionMetric.population || 0 }} alumni
                            </p>
                        </div>
                        <div class="flex flex-col justify-center">
                            <div class="h-3 rounded-full bg-slate-200 overflow-hidden">
                                <div
                                    :class="['h-3 rounded-full transition-all duration-500', getCompletionBarClass(selectedCompletionMetric.percentage || 0)]"
                                    :style="{ width: (Math.min(selectedCompletionMetric.percentage || 0, 100) + '%') }"
                                ></div>
                            </div>
                            <p class="mt-3 text-xs leading-5 text-slate-500">
                                Persentase mengikuti status audit data. Pertanyaan yang tidak berlaku karena alur percabangan tidak dihitung sebagai kekurangan.
                            </p>
                        </div>
                    </div>
                </section>

                <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                    
                    <div class="px-6 pt-5 pb-3 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <button 
                                type="button"
                                @click="activeTab = 'prodi'"
                                :class="[
                                    'px-3.5 py-2.5 text-xs font-bold rounded-lg transition-all cursor-pointer',
                                    activeTab === 'prodi'
                                        ? 'text-white bg-[#0D542B]'
                                        : 'text-slate-500 hover:text-[#0D542B] hover:bg-slate-100'
                                ]"
                            >
                                Partisipasi Seluruh Prodi ({{ prodiSummaries.length }})
                            </button>
                            <button 
                                type="button"
                                @click="activeTab = 'alumni'"
                                :class="[
                                    'px-3.5 py-2.5 text-xs font-bold rounded-lg transition-all cursor-pointer',
                                    activeTab === 'alumni'
                                        ? 'text-white bg-[#0D542B]'
                                        : 'text-slate-500 hover:text-[#0D542B] hover:bg-slate-100'
                                ]"
                            >
                                Alumni Terbaru ({{ recentAlumni.length }})
                            </button>
                            <button 
                                type="button"
                                @click="activeTab = 'logs'"
                                :class="[
                                    'px-3.5 py-2.5 text-xs font-bold rounded-lg transition-all cursor-pointer',
                                    activeTab === 'logs'
                                        ? 'text-white bg-[#0D542B]'
                                        : 'text-slate-500 hover:text-[#0D542B] hover:bg-slate-100'
                                ]"
                            >
                                Log Audit Sistem ({{ recentLogs.length }})
                            </button>
                            <button 
                                v-if="pendingPerusahaanList.length > 0"
                                type="button"
                                @click="activeTab = 'perusahaan'"
                                :class="[
                                    'px-3.5 py-2.5 text-xs font-bold rounded-lg transition-all cursor-pointer',
                                    activeTab === 'perusahaan'
                                        ? 'text-[#0D542B] bg-[#FDC700]'
                                        : 'text-slate-500 hover:text-[#0D542B] hover:bg-slate-100'
                                ]"
                            >
                                Antrean ACC Perusahaan ({{ pendingPerusahaanList.length }})
                            </button>
                        </div>

                        <Link 
                            :href="activeTab === 'alumni' ? '/superadmin/alumni' : (activeTab === 'logs' ? '/superadmin/logs' : (activeTab === 'perusahaan' ? '/superadmin/perusahaan' : '/superadmin/alumni'))"
                            class="text-xs font-bold text-[#0D542B] hover:underline mb-2 sm:mb-0"
                        >
                            Lihat Modul Lengkap
                        </Link>
                    </div>

                    <div v-if="activeTab === 'prodi'" class="p-6 space-y-4">
                        
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <button 
                                    @click="sortByRate = !sortByRate" 
                                    type="button"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 text-xs font-bold text-slate-700 hover:bg-[#FDC700] hover:text-[#0D542B] cursor-pointer"
                                >
                                    <span>Urutkan Response Rate:</span>
                                    <span class="text-[#0D542B]">{{ sortByRate ? 'Tertinggi' : 'Terendah' }}</span>
                                </button>
                            </div>

                            <div class="relative max-w-xs w-full">
                                <input 
                                    v-model="searchProdiQuery" 
                                    type="text" 
                                    placeholder="Cari program studi..." 
                                    class="w-full pl-8 pr-3 py-1.5 text-xs rounded-xl bg-slate-100 focus:outline-none focus:bg-white"
                                />
                                <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                            </div>
                        </div>

                        <div class="overflow-x-auto rounded-xl bg-slate-50">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-100 text-slate-600 font-bold uppercase tracking-wider text-[11px]">
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
                                            <span :class="['inline-flex mt-1 px-2 py-0.5 rounded-full text-[10px] font-bold', getResponseRateClass(p.response_rate_status?.code, 'badge')]">
                                                {{ p.response_rate_status?.label || 'Belum dihitung' }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-right">
                                            <Link 
                                                :href="`/superadmin/alumni?prodi_id=${p.id}`" 
                                                class="inline-flex items-center px-2.5 py-1 text-[11px] font-bold rounded-lg bg-[#0D542B] text-white hover:bg-[#08381c] transition-colors cursor-pointer"
                                            >
                                                Filter Alumni
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

                    <div v-else-if="activeTab === 'alumni'" class="p-6 space-y-4">
                        
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
                                    :class="['px-3 py-1.5 text-xs font-bold rounded-lg cursor-pointer transition-colors', filterStatus === 'linkedin' ? 'bg-[#0D542B] text-white' : 'bg-slate-100 text-slate-600 hover:bg-[#FDC700] hover:text-[#0D542B]']"
                                >
                                    Ada LinkedIn
                                </button>
                            </div>

                            <div class="relative max-w-xs w-full">
                                <input 
                                    v-model="searchAlumniQuery" 
                                    type="text" 
                                    placeholder="Cari NIM, Nama, atau Prodi..." 
                                    class="w-full pl-8 pr-3 py-1.5 text-xs rounded-xl bg-slate-100 focus:outline-none focus:bg-white"
                                />
                                <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                            </div>
                        </div>

                        <div class="overflow-x-auto rounded-xl bg-slate-50">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-100 text-slate-600 font-bold uppercase tracking-wider text-[11px]">
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
                                                class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#0D542B] text-white"
                                            >
                                                Sudah Respon
                                            </span>
                                            <span 
                                                v-else 
                                                class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#FDC700] text-[#0D542B]"
                                            >
                                                Belum Mengisi
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <span 
                                                v-if="alumni.has_linkedin" 
                                                class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#0D542B] text-white"
                                            >
                                                Terhubung
                                            </span>
                                            <span v-else class="text-slate-400 text-[11px]">-</span>
                                        </td>
                                        <td class="py-3 px-4 text-right">
                                            <Link 
                                                :href="`/superadmin/alumni/${alumni.id}`" 
                                                class="inline-flex items-center px-2.5 py-1 text-[11px] font-bold rounded-lg bg-[#0D542B] text-white hover:bg-[#08381c] transition-colors cursor-pointer"
                                            >
                                                Audit
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

                    <div v-else-if="activeTab === 'logs'" class="p-6">
                        <div class="space-y-3">
                            <div 
                                v-for="log in recentLogs" 
                                :key="log.id" 
                                class="p-4 rounded-xl bg-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-200 transition-colors"
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

                    <div v-else-if="activeTab === 'perusahaan'" class="p-6">
                        <div class="space-y-3">
                            <div 
                                v-for="c in pendingPerusahaanList" 
                                :key="c.id" 
                                class="p-4 rounded-xl bg-[#FDC700] flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-[#E6B400] transition-colors"
                            >
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold text-slate-900">{{ c.nama }}</span>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#0D542B] text-white">
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
                                    Verifikasi Sekarang
                                </Link>
                            </div>

                            <div v-if="pendingPerusahaanList.length === 0" class="py-8 text-center text-slate-400">
                                Tidak ada antrean usulan perusahaan baru yang menunggu verifikasi saat ini.
                            </div>
                        </div>
                    </div>

                </div>

            </main>

            <footer class="text-slate-400 text-xs text-center py-5">
                &copy; {{ new Date().getFullYear() }} Universitas Kristen Duta Wacana. Hak Cipta Dilindungi.
            </footer>

        </div>
    </div>
</template>
