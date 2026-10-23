<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Sidebar from './Components/Sidebar.vue';
import AdminPageHeader from '@/components/AdminPageHeader.vue';

const props = defineProps({
    user: Object,
    stats: {
        type: Object,
        default: () => ({
            total_alumni: 0,
            total_responden: 0,
            persentase_respon: 0,
            total_pertanyaan: 0,
            total_prodi: 0,
            alumni_linkedin: 0,
            total_pending_perusahaan: 0,
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
});

// State Tab Interaktif
const activeTab = ref('prodi'); // 'prodi' | 'alumni'

// State Pencarian & Sorting Prodi
const searchProdiQuery = ref('');
const sortByRate = ref(true); // true = desc, false = asc

// State Pencarian & Filter Alumni
const searchAlumniQuery = ref('');
const filterStatus = ref('all'); // 'all' | 'responded' | 'not_responded' | 'linkedin'

// Filter & Sort Prodi Universitas
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
    <Head title="Dashboard Admin Biro 3 - Tracer Study UKDW" />

    <div class="min-h-screen bg-[#f8fafc] text-gray-800 flex font-sans">
        
        <!-- Sidebar Terpadu Admin Biro 3 -->
        <Sidebar :user="user" />

        <!-- Area Konten Utama -->
        <div class="flex-1 flex flex-col min-w-0 lg:pl-72">
            
            <AdminPageHeader
                eyebrow="Dashboard Eksekutif Biro III"
                title="Dashboard Eksekutif Biro III"
                description="Pusat pemantauan indikator kinerja utama tracer study universitas, audit keterisian kuesioner, dan jejaring karir LinkedIn."
                context-label="Partisipasi Kampus"
                :context-value="`${stats?.persentase_respon || 0}% Respon · ${stats?.total_responden || 0} dari ${stats?.total_alumni || 0} Alumni`"
            />

            <!-- Main Body Area -->
            <main class="admin-page-main relative z-10 mx-auto flex w-full max-w-[1440px] min-w-0 flex-1 space-y-6 overflow-x-auto px-4 pb-12 sm:px-6 lg:px-8">
                
                <!-- Grid 4 Kartu KPI Metrik Eksekutif -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    
                    <!-- KPI 1: Total Alumni Universitas -->
                    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-2xs hover:shadow-xs transition-shadow">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Alumni</span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700">Database</span>
                        </div>
                        <div class="text-3xl font-extrabold text-slate-900 tracking-tight mt-3">
                            {{ stats?.total_alumni || 0 }}
                        </div>
                        <p class="mt-2 text-xs text-slate-400">
                            Tersebar di {{ stats?.total_prodi || 0 }} Program Studi
                        </p>
                    </div>

                    <!-- KPI 2: Responden Masuk -->
                    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-2xs hover:shadow-xs transition-shadow">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-[#0D542B] uppercase tracking-wider">Responden Masuk</span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-[#0D542B] border border-emerald-200">Respon</span>
                        </div>
                        <div class="text-3xl font-extrabold text-[#0D542B] tracking-tight mt-3">
                            {{ stats?.total_responden || 0 }}
                        </div>
                        <div class="mt-2 flex items-center justify-between text-xs text-slate-400">
                            <span>Tingkat respon:</span>
                            <span class="font-bold text-[#0D542B]">{{ stats?.persentase_respon || 0 }}%</span>
                        </div>
                    </div>

                    <!-- KPI 3: LinkedIn Terdata -->
                    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-2xs hover:shadow-xs transition-shadow">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-blue-700 uppercase tracking-wider">Profil LinkedIn</span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">Karier</span>
                        </div>
                        <div class="text-3xl font-extrabold text-blue-700 tracking-tight mt-3">
                            {{ stats?.alumni_linkedin || 0 }}
                        </div>
                        <p class="mt-2 text-xs text-slate-400">
                            Alumni memiliki akun LinkedIn
                        </p>
                    </div>

                    <!-- KPI 4: Butir Instrumen Kuesioner -->
                    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-2xs hover:shadow-xs transition-shadow">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Instrumen Nasional</span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700">Dikti</span>
                        </div>
                        <div class="text-3xl font-extrabold text-slate-900 tracking-tight mt-3">
                            {{ stats?.total_pertanyaan || 0 }}
                        </div>
                        <p class="mt-2 text-xs text-slate-400">
                            Butir pertanyaan aktif universitas
                        </p>
                    </div>

                </div>

                <!-- Modul Pintasan Aksi Cepat Biro 3 -->
                <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-2xs">
                    <div class="flex items-center justify-between mb-3.5">
                        <span class="text-xs font-bold text-slate-900 uppercase tracking-wider">Pintasan Menu Cepat Biro III</span>
                        <span class="text-xs text-slate-400">Aksi Pengelolaan</span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <Link 
                            href="/biro3/alumni" 
                            class="p-3.5 rounded-xl bg-slate-50 hover:bg-emerald-50/70 border border-slate-200 hover:border-emerald-200 transition-all flex items-center gap-3 group cursor-pointer"
                        >
                            <div class="w-9 h-9 rounded-lg bg-emerald-100 text-[#0D542B] flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                            </div>
                            <div class="text-left">
                                <span class="text-xs font-bold text-slate-800 group-hover:text-[#0D542B] block">Direktori Alumni Universitas</span>
                                <span class="text-[11px] text-slate-500">Audit & telusuri data seluruh alumni</span>
                            </div>
                        </Link>

                        <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between">
                            <div>
                                <span class="text-xs font-bold text-slate-800 block">Antrean Verifikasi Instansi</span>
                                <span class="text-[11px] text-slate-500">
                                    {{ stats?.total_pending_perusahaan || 0 }} usulan perusahaan baru
                                </span>
                            </div>
                            <span 
                                v-if="stats?.total_pending_perusahaan > 0"
                                class="px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-900 border border-amber-300"
                            >
                                {{ stats.total_pending_perusahaan }} Pending
                            </span>
                            <span v-else class="text-xs font-bold text-emerald-800">Clear</span>
                        </div>
                    </div>
                </div>

                <!-- Area Panel Interaktif: Tab Switcher Capaian Seluruh Prodi vs Alumni Terbaru -->
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
                                Partisipasi Seluruh Program Studi ({{ prodiSummaries.length }})
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
                                Pratinjau Alumni Terdaftar ({{ recentAlumni.length }})
                            </button>
                        </div>

                        <Link 
                            href="/biro3/alumni"
                            class="text-xs font-bold text-[#0D542B] hover:underline mb-2 sm:mb-0"
                        >
                            Buka Seluruh Data Alumni &rarr;
                        </Link>
                    </div>

                    <!-- TAB KONTEN 1: MATRIKS PARTISIPASI SELURUH PRODI UNIVERSITAS -->
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
                                        <th class="py-3 px-4 text-center">Responden</th>
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
                                                        class="bg-[#0D542B] h-2 rounded-full transition-all duration-500" 
                                                        :style="{ width: `${p.response_rate}%` }"
                                                    ></div>
                                                </div>
                                                <span class="font-extrabold text-xs text-slate-800 w-12 text-right">{{ p.response_rate }}%</span>
                                            </div>
                                        </td>
                                        <td class="py-3 px-4 text-right">
                                            <Link 
                                                :href="`/biro3/alumni?prodi_id=${p.id}`" 
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

                    <!-- TAB KONTEN 2: PRATINJAU ALUMNI TERBARU -->
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
                                    @click="filterStatus = 'responded'" 
                                    :class="['px-3 py-1.5 text-xs font-bold rounded-lg cursor-pointer transition-colors', filterStatus === 'responded' ? 'bg-[#0D542B] text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200']"
                                >
                                    Sudah Mengisi
                                </button>
                                <button 
                                    @click="filterStatus = 'not_responded'" 
                                    :class="['px-3 py-1.5 text-xs font-bold rounded-lg cursor-pointer transition-colors', filterStatus === 'not_responded' ? 'bg-[#0D542B] text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200']"
                                >
                                    Belum Mengisi
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
                                                class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-[#0D542B] border border-emerald-200"
                                            >
                                                Sudah Respon
                                            </span>
                                            <span 
                                                v-else 
                                                class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-900 border border-amber-200"
                                            >
                                                Belum Mengisi
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
                                                :href="`/biro3/alumni/${alumni.id}`" 
                                                class="inline-flex items-center px-2.5 py-1 text-[11px] font-bold rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100 transition-colors"
                                            >
                                                Audit Profil &rarr;
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
