<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Sidebar from './Components/Sidebar.vue';
import AdminPageHeader from '@/components/AdminPageHeader.vue';

const props = defineProps({
    user: Object,
    fakultas: Object,
    prodis: Array,
    prodiSummaries: {
        type: Array,
        default: () => [],
    },
    recentAlumni: {
        type: Array,
        default: () => [],
    },
    stats: {
        type: Object,
        default: () => ({
            total_alumni: 0,
            total_selesai: 0,
            total_belum_selesai: 0,
            persentase_selesai: 0,
            total_prodi: 0,
            total_pending_perusahaan: 0,
            alumni_linkedin: 0,
        }),
    },
});

// State Tab Interaktif
const activeTab = ref('prodi'); // 'prodi' | 'alumni'

// State Pencarian & Filter Prodi
const searchProdiQuery = ref('');
const sortByRate = ref(true); // true = desc, false = asc

// State Pencarian & Filter Alumni
const searchAlumniQuery = ref('');
const filterProdiId = ref('');
const filterStatus = ref('all'); // 'all' | 'complete' | 'incomplete' | 'linkedin'

// Filter & Sort Prodi
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

// Filter Alumni Fakultas
const filteredAlumni = computed(() => {
    let list = props.recentAlumni;

    if (filterProdiId.value !== '') {
        list = list.filter(a => a.prodi === filterProdiId.value || a.prodi?.includes(filterProdiId.value));
    }

    if (filterStatus.value === 'complete') {
        list = list.filter(a => a.is_complete);
    } else if (filterStatus.value === 'incomplete') {
        list = list.filter(a => !a.is_complete);
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
    <Head :title="`Dashboard Admin Fakultas - ${fakultas?.nama_fakultas || 'Fakultas'}`" />

    <div class="min-h-screen bg-[#f8fafc] text-gray-800 font-sans flex">
        <!-- Sidebar Terpadu Admin Fakultas -->
        <Sidebar :user="user" :fakultas="fakultas" />

        <!-- Area Konten Utama -->
        <div class="flex-1 flex flex-col min-w-0 lg:pl-72">
            
            <AdminPageHeader
                eyebrow="Dashboard Admin Fakultas"
                :title="`Dashboard ${fakultas?.nama_fakultas || 'Fakultas'}`"
                description="Pemantauan komparasi capaian tracer study lintas program studi di lingkungan fakultas."
                context-label="Rata-Rata Fakultas"
                :context-value="`${stats.persentase_selesai}% (${stats.total_selesai}/${stats.total_alumni} Responden)`"
            />

            <!-- Main Body Area -->
            <main class="admin-page-main relative z-10 mx-auto flex w-full max-w-[1440px] flex-1 min-w-0 space-y-6 overflow-x-auto px-4 pb-12 sm:px-6 lg:px-8">
                
                <!-- Notifikasi Peringatan Interaktif: Pending Perusahaan Fakultas -->
                <div 
                    v-if="stats.total_pending_perusahaan > 0" 
                    class="bg-amber-50 border border-amber-200 rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 animate-in fade-in duration-300"
                >
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-amber-200/80 text-amber-900 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                        <div>
                            <span class="text-xs font-bold text-amber-900 block">Antrean Verifikasi Perusahaan Fakultas</span>
                            <span class="text-xs text-amber-800">
                                Ada <strong>{{ stats.total_pending_perusahaan }} usulan instansi baru</strong> dari alumni di fakultas Anda yang menunggu persetujuan.
                            </span>
                        </div>
                    </div>
                    <Link 
                        href="/fakultas/perusahaan" 
                        class="inline-flex items-center justify-center px-4 py-2 text-xs font-bold rounded-xl bg-[#0D542B] text-white hover:bg-[#08381c] transition-colors shrink-0"
                    >
                        Verifikasi Perusahaan &rarr;
                    </Link>
                </div>

                <!-- 4 KPI Summary Cards (Real Data & Interactive Hover) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    
                    <!-- KPI 1: Total Alumni Fakultas -->
                    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-2xs hover:shadow-xs transition-shadow">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Alumni</span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700">Fakultas</span>
                        </div>
                        <div class="text-3xl font-extrabold text-slate-900 tracking-tight mt-3">
                            {{ stats.total_alumni }}
                        </div>
                        <div class="mt-2 flex items-center justify-between text-xs text-slate-400">
                            <span>Dari {{ stats.total_prodi }} Program Studi</span>
                            <span class="text-blue-700 font-semibold">{{ stats.alumni_linkedin }} LinkedIn</span>
                        </div>
                    </div>

                    <!-- KPI 2: Tracer Selesai Lengkap -->
                    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-2xs hover:shadow-xs transition-shadow">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-[#0D542B] uppercase tracking-wider">Tracer Selesai</span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-[#0D542B] border border-emerald-200">Lengkap</span>
                        </div>
                        <div class="text-3xl font-extrabold text-[#0D542B] tracking-tight mt-3">
                            {{ stats.total_selesai }}
                        </div>
                        <div class="mt-2 flex items-center justify-between text-xs text-slate-400">
                            <span>Tingkat Keterisian:</span>
                            <span class="font-bold text-[#0D542B]">{{ stats.persentase_selesai }}%</span>
                        </div>
                    </div>

                    <!-- KPI 3: Belum Selesai -->
                    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-2xs hover:shadow-xs transition-shadow">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Belum Selesai</span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-900 border border-amber-200">Perlu Dipacu</span>
                        </div>
                        <div class="text-3xl font-extrabold text-slate-900 tracking-tight mt-3">
                            {{ stats.total_belum_selesai }}
                        </div>
                        <div class="mt-2 flex items-center justify-between text-xs text-slate-400">
                            <span>Alumni dalam proses:</span>
                            <span class="font-bold text-amber-900">
                                {{ stats.total_alumni > 0 ? (100 - stats.persentase_selesai) : 0 }}%
                            </span>
                        </div>
                    </div>

                    <!-- KPI 4: Cakupan Program Studi -->
                    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-2xs hover:shadow-xs transition-shadow">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Cakupan Prodi</span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700">Struktur</span>
                        </div>
                        <div class="text-3xl font-extrabold text-slate-900 tracking-tight mt-3">
                            {{ stats.total_prodi }}
                        </div>
                        <div class="mt-2 flex items-center justify-between text-xs text-slate-400">
                            <span>Program studi aktif</span>
                            <span class="font-bold text-slate-700">Se-Fakultas</span>
                        </div>
                    </div>

                </div>

                <!-- Modul Pintasan Aksi Cepat Fakultas -->
                <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-2xs">
                    <div class="flex items-center justify-between mb-3.5">
                        <span class="text-xs font-bold text-slate-900 uppercase tracking-wider">Pintasan Menu Cepat Admin Fakultas</span>
                        <span class="text-xs text-slate-400">Aksi Administratif</span>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                        <Link 
                            href="/fakultas/alumni" 
                            class="p-3 rounded-xl bg-slate-50 hover:bg-emerald-50/70 border border-slate-200 hover:border-emerald-200 transition-all flex flex-col items-center text-center group cursor-pointer"
                        >
                            <svg class="w-5 h-5 text-slate-600 group-hover:text-[#0D542B] mb-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                            <span class="text-xs font-bold text-slate-700 group-hover:text-[#0D542B]">Direktori Alumni Fakultas</span>
                        </Link>

                        <Link 
                            href="/fakultas/perusahaan" 
                            class="p-3 rounded-xl bg-slate-50 hover:bg-emerald-50/70 border border-slate-200 hover:border-emerald-200 transition-all flex flex-col items-center text-center group relative cursor-pointer"
                        >
                            <span v-if="stats.total_pending_perusahaan > 0" class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-amber-500"></span>
                            <svg class="w-5 h-5 text-slate-600 group-hover:text-[#0D542B] mb-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                            <span class="text-xs font-bold text-slate-700 group-hover:text-[#0D542B]">Verifikasi Perusahaan</span>
                        </Link>

                        <Link 
                            href="/fakultas/linkedin-sync" 
                            class="p-3 rounded-xl bg-slate-50 hover:bg-emerald-50/70 border border-slate-200 hover:border-emerald-200 transition-all flex flex-col items-center text-center group cursor-pointer"
                        >
                            <svg class="w-5 h-5 text-slate-600 group-hover:text-[#0D542B] mb-1.5" fill="currentColor" viewBox="0 0 24 24"><path d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.28 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.75M6.46 10.9v8.37H9.2V10.9H6.46M7.83 6.45a1.62 1.62 0 1 0 0 3.24 1.62 1.62 0 0 0 0-3.24Z"/></svg>
                            <span class="text-xs font-bold text-slate-700 group-hover:text-[#0D542B]">Sinkronisasi LinkedIn</span>
                        </Link>

                        <Link
                            href="/fakultas/prodi-kuesioner"
                            class="p-3 rounded-xl bg-slate-50 hover:bg-emerald-50/70 border border-slate-200 hover:border-emerald-200 transition-all flex flex-col items-center text-center group cursor-pointer"
                        >
                            <svg class="w-5 h-5 text-slate-600 group-hover:text-[#0D542B] mb-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5.5A2.5 2.5 0 016.5 3H20v16H6.5A2.5 2.5 0 014 16.5v-11ZM4 16.5A2.5 2.5 0 016.5 19H20M8 7h8M8 11h8" /></svg>
                            <span class="text-xs font-bold text-slate-700 group-hover:text-[#0D542B]">Kuesioner Per Prodi</span>
                        </Link>

                        <Link 
                            href="/fakultas/manajemen-akun" 
                            class="p-3 rounded-xl bg-slate-50 hover:bg-emerald-50/70 border border-slate-200 hover:border-emerald-200 transition-all flex flex-col items-center text-center group cursor-pointer"
                        >
                            <svg class="w-5 h-5 text-slate-600 group-hover:text-[#0D542B] mb-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" /></svg>
                            <span class="text-xs font-bold text-slate-700 group-hover:text-[#0D542B]">Manajemen Akun</span>
                        </Link>
                    </div>
                </div>

                <!-- Area Panel Interaktif: Tab Switcher Antara Capaian Prodi & Pratinjau Alumni -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs overflow-hidden">
                    
                    <!-- Tab Header Bar -->
                    <div class="px-6 pt-5 pb-0 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-50/50">
                        <div class="flex items-center gap-2">
                            <button 
                                type="button"
                                @click="activeTab = 'prodi'"
                                :class="[
                                    'px-4 py-2.5 text-xs font-bold rounded-t-xl transition-all cursor-pointer border-b-2',
                                    activeTab === 'prodi'
                                        ? 'border-[#0D542B] text-[#0D542B] bg-white shadow-2xs'
                                        : 'border-transparent text-slate-500 hover:text-slate-900'
                                ]"
                            >
                                Partisipasi Lintas Prodi ({{ prodiSummaries.length }})
                            </button>
                            <button 
                                type="button"
                                @click="activeTab = 'alumni'"
                                :class="[
                                    'px-4 py-2.5 text-xs font-bold rounded-t-xl transition-all cursor-pointer border-b-2',
                                    activeTab === 'alumni'
                                        ? 'border-[#0D542B] text-[#0D542B] bg-white shadow-2xs'
                                        : 'border-transparent text-slate-500 hover:text-slate-900'
                                ]"
                            >
                                Pratinjau Alumni Fakultas ({{ recentAlumni.length }})
                            </button>
                        </div>

                        <Link 
                            href="/fakultas/alumni"
                            class="text-xs font-bold text-[#0D542B] hover:underline mb-2 sm:mb-0"
                        >
                            Buka Seluruh Direktori Fakultas &rarr;
                        </Link>
                    </div>

                    <!-- TAB KONTEN 1: MATRIKS PARTISIPASI PRODI LINTAS FAKULTAS -->
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
                                    placeholder="Cari nama prodi..." 
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
                                        <th class="py-3 px-4 text-center">Responden</th>
                                        <th class="py-3 px-4">Response Rate</th>
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
                                                        class="bg-[#0D542B] h-2 rounded-full transition-all duration-500" 
                                                        :style="{ width: `${p.response_rate}%` }"
                                                    ></div>
                                                </div>
                                                <span class="font-extrabold text-xs text-slate-800 w-12 text-right">{{ p.response_rate }}%</span>
                                            </div>
                                        </td>
                                        <td class="py-3 px-4 text-right">
                                            <Link 
                                                :href="`/fakultas/alumni?prodi_id=${p.id}`" 
                                                class="inline-flex items-center px-2.5 py-1 text-[11px] font-bold rounded-lg border border-slate-300 text-[#0D542B] hover:bg-emerald-50 transition-colors"
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

                    <!-- TAB KONTEN 2: PRATINJAU ALUMNI FAKULTAS TERBARU -->
                    <div v-else class="p-6 space-y-4">
                        
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
                                    @click="filterStatus = 'complete'" 
                                    :class="['px-3 py-1.5 text-xs font-bold rounded-lg cursor-pointer transition-colors', filterStatus === 'complete' ? 'bg-[#0D542B] text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200']"
                                >
                                    Sudah Selesai
                                </button>
                                <button 
                                    @click="filterStatus = 'incomplete'" 
                                    :class="['px-3 py-1.5 text-xs font-bold rounded-lg cursor-pointer transition-colors', filterStatus === 'incomplete' ? 'bg-[#0D542B] text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200']"
                                >
                                    Belum Selesai
                                </button>
                                <button 
                                    @click="filterStatus = 'linkedin'" 
                                    :class="['px-3 py-1.5 text-xs font-bold rounded-lg cursor-pointer transition-colors', filterStatus === 'linkedin' ? 'bg-blue-700 text-white' : 'bg-blue-50 text-blue-700 hover:bg-blue-100']"
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
                                        <th class="py-3 px-4 text-center">Status Kelengkapan</th>
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
                                                v-if="alumni.is_complete" 
                                                class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-[#0D542B] border border-emerald-200"
                                            >
                                                Selesai Lengkap
                                            </span>
                                            <span 
                                                v-else 
                                                class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-900 border border-amber-200"
                                            >
                                                Belum Lengkap
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <span 
                                                v-if="alumni.has_linkedin" 
                                                class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200"
                                            >
                                                Terhubung
                                            </span>
                                            <span v-else class="text-slate-400 text-[11px]">-</span>
                                        </td>
                                        <td class="py-3 px-4 text-right">
                                            <Link 
                                                :href="`/fakultas/alumni/${alumni.id}`" 
                                                class="inline-flex items-center px-2.5 py-1 text-[11px] font-bold rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100 transition-colors"
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

                </div>

            </main>

            <!-- Footer -->
            <footer class="text-slate-400 text-xs text-center py-5 border-t border-slate-100">
                &copy; {{ new Date().getFullYear() }} Universitas Kristen Duta Wacana. Hak Cipta Dilindungi.
            </footer>

        </div>
    </div>
</template>
