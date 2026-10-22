<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Sidebar from './Components/Sidebar.vue';
import AdminPageHeader from '@/components/AdminPageHeader.vue';

const props = defineProps({
    user: Object,
    prodi: Object,
    stats: {
        type: Object,
        default: () => ({
            totalAlumni: 0,
            totalSections: 0,
            totalPertanyaan: 0,
            totalRespondenProdi: 0,
            persentasePartisipasi: 0,
            totalUnivSelesai: 0,
            persentaseUniv: 0,
            totalPendingPerusahaan: 0,
            totalLinkedIn: 0,
        }),
    },
    sectionsList: {
        type: Array,
        default: () => [],
    },
    recentAlumnis: {
        type: Array,
        default: () => [],
    },
});

// State Tab Interaktif
const activeTab = ref('alumni'); // 'alumni' | 'instrumen'

// State Pencarian & Filter Alumni
const searchQuery = ref('');
const filterStatus = ref('all'); // 'all' | 'complete' | 'incomplete' | 'linkedin'

// Filter komputasi alumni secara instan dan responsif
const filteredAlumni = computed(() => {
    let result = props.recentAlumnis;

    // Filter status
    if (filterStatus.value === 'complete') {
        result = result.filter(a => a.is_univ_complete && a.is_prodi_complete);
    } else if (filterStatus.value === 'incomplete') {
        result = result.filter(a => !a.is_univ_complete || !a.is_prodi_complete);
    } else if (filterStatus.value === 'linkedin') {
        result = result.filter(a => a.has_linkedin);
    }

    // Filter teks pencarian
    if (searchQuery.value.trim() !== '') {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(a => 
            (a.nama && a.nama.toLowerCase().includes(query)) ||
            (a.nim && a.nim.toLowerCase().includes(query)) ||
            (a.tahun_lulus && String(a.tahun_lulus).includes(query))
        );
    }

    return result;
});
</script>

<template>
    <Head :title="`Dashboard Admin Prodi - ${prodi?.nama_prodi || 'Tracer Study UKDW'}`" />

    <div class="min-h-screen bg-[#f8fafc] text-gray-800 font-sans flex">
        <!-- Sidebar Terpadu Admin Program Studi -->
        <Sidebar :user="user" :prodi="prodi" />

        <!-- Area Konten Utama -->
        <div class="flex-1 flex flex-col min-w-0 lg:pl-72">
            
            <AdminPageHeader
                eyebrow="Dashboard Admin Prodi"
                :title="`Dashboard ${prodi?.nama_prodi || 'Program Studi'}`"
                description="Monitoring capaian pengisian tracer study alumni, pengelolaan instrumen survei prodi, dan verifikasi perusahaan."
                context-label="Program Studi"
                :context-value="`${prodi?.nama_prodi || 'Program Studi'} (Kode: ${prodi?.kode_prodi || '-'})`"
            />

            <!-- Main Body Area -->
            <main class="flex-1 p-6 space-y-6 overflow-x-auto min-w-0">
                
                <!-- Notifikasi Peringatan Interaktif: Pending ACC Perusahaan -->
                <div 
                    v-if="stats.totalPendingPerusahaan > 0" 
                    class="bg-amber-50 border border-amber-200 rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 animate-in fade-in duration-300"
                >
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-amber-200/80 text-amber-900 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div>
                            <span class="text-xs font-bold text-amber-900 block">Antrean Verifikasi Perusahaan</span>
                            <span class="text-xs text-amber-800">
                                Ada <strong>{{ stats.totalPendingPerusahaan }} pengajuan instansi baru</strong> dari alumni prodi Anda yang menunggu verifikasi.
                            </span>
                        </div>
                    </div>
                    <Link 
                        href="/prodi/perusahaan" 
                        class="inline-flex items-center justify-center px-4 py-2 text-xs font-bold rounded-xl bg-[#0D542B] text-white hover:bg-[#08381c] transition-colors shrink-0"
                    >
                        Tinjau Sekarang &rarr;
                    </Link>
                </div>

                <!-- Grid 4 Kartu KPI Metrik (Interaktif & Real Data) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    
                    <!-- KPI 1: Total Alumni Terdaftar -->
                    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-2xs hover:shadow-xs transition-shadow">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Alumni Prodi</span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700">Database</span>
                        </div>
                        <div class="text-3xl font-extrabold text-slate-900 tracking-tight mt-3">
                            {{ stats.totalAlumni }}
                        </div>
                        <div class="mt-2 flex items-center justify-between text-xs text-slate-400">
                            <span>Lulusan terdaftar di prodi</span>
                            <span class="text-blue-700 font-semibold">{{ stats.totalLinkedIn }} LinkedIn</span>
                        </div>
                    </div>

                    <!-- KPI 2: Partisipasi Kuesioner Prodi -->
                    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-2xs hover:shadow-xs transition-shadow">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-[#0D542B] uppercase tracking-wider">Respon Kuesioner Prodi</span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-[#0D542B] border border-emerald-200">Mandiri</span>
                        </div>
                        <div class="text-3xl font-extrabold text-[#0D542B] tracking-tight mt-3">
                            {{ stats.totalRespondenProdi }}
                        </div>
                        <div class="mt-2 flex items-center justify-between text-xs text-slate-400">
                            <span>Rasio keterisian:</span>
                            <span class="font-bold text-[#0D542B]">{{ stats.persentasePartisipasi }}%</span>
                        </div>
                    </div>

                    <!-- KPI 3: Penyelesaian Kuesioner Universitas -->
                    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-2xs hover:shadow-xs transition-shadow">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Selesai Kuesioner Univ</span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">Nasional</span>
                        </div>
                        <div class="text-3xl font-extrabold text-slate-900 tracking-tight mt-3">
                            {{ stats.totalUnivSelesai }}
                        </div>
                        <div class="mt-2 flex items-center justify-between text-xs text-slate-400">
                            <span>Rasio Dikti prodi:</span>
                            <span class="font-bold text-blue-700">{{ stats.persentaseUniv }}%</span>
                        </div>
                    </div>

                    <!-- KPI 4: Total Instrumen Pertanyaan Prodi -->
                    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-2xs hover:shadow-xs transition-shadow">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Instrumen Khusus</span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-900 border border-amber-200">Soal</span>
                        </div>
                        <div class="text-3xl font-extrabold text-slate-900 tracking-tight mt-3">
                            {{ stats.totalPertanyaan }}
                        </div>
                        <div class="mt-2 flex items-center justify-between text-xs text-slate-400">
                            <span>Tersebar di</span>
                            <span class="font-bold text-slate-700">{{ stats.totalSections }} Section</span>
                        </div>
                    </div>

                </div>

                <!-- Modul Pintasan Aksi Cepat (Quick Action Bar) -->
                <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-2xs">
                    <div class="flex items-center justify-between mb-3.5">
                        <span class="text-xs font-bold text-slate-900 uppercase tracking-wider">Pintasan Menu Cepat Admin Prodi</span>
                        <span class="text-xs text-slate-400">Aksi Rutin Harian</span>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                        <Link 
                            href="/prodi/alumni" 
                            class="p-3 rounded-xl bg-slate-50 hover:bg-emerald-50/70 border border-slate-200 hover:border-emerald-200 transition-all flex flex-col items-center text-center group cursor-pointer"
                        >
                            <svg class="w-5 h-5 text-slate-600 group-hover:text-[#0D542B] mb-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                            <span class="text-xs font-bold text-slate-700 group-hover:text-[#0D542B]">Direktori Alumni</span>
                        </Link>

                        <Link 
                            href="/prodi/perusahaan" 
                            class="p-3 rounded-xl bg-slate-50 hover:bg-emerald-50/70 border border-slate-200 hover:border-emerald-200 transition-all flex flex-col items-center text-center group relative cursor-pointer"
                        >
                            <span v-if="stats.totalPendingPerusahaan > 0" class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-amber-500"></span>
                            <svg class="w-5 h-5 text-slate-600 group-hover:text-[#0D542B] mb-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                            <span class="text-xs font-bold text-slate-700 group-hover:text-[#0D542B]">ACC Perusahaan</span>
                        </Link>

                        <Link 
                            href="/prodi/linkedin-sync" 
                            class="p-3 rounded-xl bg-slate-50 hover:bg-emerald-50/70 border border-slate-200 hover:border-emerald-200 transition-all flex flex-col items-center text-center group cursor-pointer"
                        >
                            <svg class="w-5 h-5 text-slate-600 group-hover:text-[#0D542B] mb-1.5" fill="currentColor" viewBox="0 0 24 24"><path d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.28 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.75M6.46 10.9v8.37H9.2V10.9H6.46M7.83 6.45a1.62 1.62 0 1 0 0 3.24 1.62 1.62 0 0 0 0-3.24Z"/></svg>
                            <span class="text-xs font-bold text-slate-700 group-hover:text-[#0D542B]">Sync LinkedIn</span>
                        </Link>

                        <Link
                            href="/prodi/kuesioner"
                            class="p-3 rounded-xl bg-slate-50 hover:bg-emerald-50/70 border border-slate-200 hover:border-emerald-200 transition-all flex flex-col items-center text-center group cursor-pointer"
                        >
                            <svg class="w-5 h-5 text-slate-600 group-hover:text-[#0D542B] mb-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5.5A2.5 2.5 0 016.5 3H20v16H6.5A2.5 2.5 0 014 16.5v-11ZM4 16.5A2.5 2.5 0 016.5 19H20M8 7h8M8 11h8" /></svg>
                            <span class="text-xs font-bold text-slate-700 group-hover:text-[#0D542B]">Kuesioner Per Prodi</span>
                        </Link>

                        <Link 
                            href="/prodi/manajemen-akun" 
                            class="p-3 rounded-xl bg-slate-50 hover:bg-emerald-50/70 border border-slate-200 hover:border-emerald-200 transition-all flex flex-col items-center text-center group cursor-pointer"
                        >
                            <svg class="w-5 h-5 text-slate-600 group-hover:text-[#0D542B] mb-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" /></svg>
                            <span class="text-xs font-bold text-slate-700 group-hover:text-[#0D542B]">Reset Akun</span>
                        </Link>
                    </div>
                </div>

                <!-- Area Panel Interaktif: Switcher Tab Antara Direktori & Instrumen -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs overflow-hidden">
                    
                    <!-- Tab Header Bar -->
                    <div class="px-6 pt-5 pb-0 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-50/50">
                        <div class="flex items-center gap-2">
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
                                Alumni Terbaru & Status Pengisian ({{ recentAlumnis.length }})
                            </button>
                            <button 
                                type="button"
                                @click="activeTab = 'instrumen'"
                                :class="[
                                    'px-4 py-2.5 text-xs font-bold rounded-t-xl transition-all cursor-pointer border-b-2',
                                    activeTab === 'instrumen'
                                        ? 'border-[#0D542B] text-[#0D542B] bg-white shadow-2xs'
                                        : 'border-transparent text-slate-500 hover:text-slate-900'
                                ]"
                            >
                                Struktur Section Kuesioner ({{ sectionsList.length }})
                            </button>
                        </div>

                        <Link 
                            :href="activeTab === 'alumni' ? '/prodi/alumni' : '/prodi/kuesioner'"
                            class="text-xs font-bold text-[#0D542B] hover:underline mb-2 sm:mb-0"
                        >
                            {{ activeTab === 'alumni' ? 'Buka Direktori Lengkap →' : 'Kelola Seluruh Section →' }}
                        </Link>
                    </div>

                    <!-- TAB KONTEN 1: TABEL ALUMNI INTERAKTIF -->
                    <div v-if="activeTab === 'alumni'" class="p-6 space-y-4">
                        
                        <!-- Toolbar Filter & Live Search -->
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
                                    Lengkap
                                </button>
                                <button 
                                    @click="filterStatus = 'incomplete'" 
                                    :class="['px-3 py-1.5 text-xs font-bold rounded-lg cursor-pointer transition-colors', filterStatus === 'incomplete' ? 'bg-[#0D542B] text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200']"
                                >
                                    Belum Lengkap
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
                                    v-model="searchQuery" 
                                    type="text" 
                                    placeholder="Cari NIM atau Nama..." 
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
                                        <th class="py-3 px-4">Tahun Lulus</th>
                                        <th class="py-3 px-4 text-center">Kuesioner Universitas</th>
                                        <th class="py-3 px-4 text-center">Kuesioner Prodi</th>
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
                                        <td class="py-3 px-4 font-medium text-slate-700">
                                            {{ alumni.tahun_lulus }}
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <span 
                                                v-if="alumni.is_univ_complete" 
                                                class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-[#0D542B] border border-emerald-200"
                                            >
                                                Lengkap
                                            </span>
                                            <span 
                                                v-else 
                                                class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-900 border border-amber-200"
                                            >
                                                Belum
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <span 
                                                v-if="alumni.is_prodi_complete" 
                                                class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-[#0D542B] border border-emerald-200"
                                            >
                                                Lengkap
                                            </span>
                                            <span 
                                                v-else 
                                                class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-900 border border-amber-200"
                                            >
                                                Belum
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
                                                :href="`/prodi/alumni/${alumni.id}`" 
                                                class="inline-flex items-center px-2.5 py-1 text-[11px] font-bold rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100 transition-colors"
                                            >
                                                Audit &rarr;
                                            </Link>
                                        </td>
                                    </tr>
                                    <tr v-if="filteredAlumni.length === 0">
                                        <td colspan="6" class="py-8 text-center text-slate-400">
                                            Tidak ada data alumni yang cocok dengan pencarian / filter ini.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- TAB KONTEN 2: STRUKTUR SECTION PRODI -->
                    <div v-else class="p-6">
                        <div class="space-y-3">
                            <div 
                                v-for="(section, idx) in sectionsList" 
                                :key="section.id" 
                                class="p-4 rounded-xl border border-slate-200 bg-slate-50/50 flex items-center justify-between hover:bg-slate-50 transition-colors"
                            >
                                <div class="flex items-center gap-3">
                                    <span class="w-7 h-7 rounded-lg bg-[#0D542B] text-white font-bold flex items-center justify-center text-xs shrink-0">
                                        {{ section.order || (idx + 1) }}
                                    </span>
                                    <div>
                                        <h4 class="text-xs font-bold text-slate-900">{{ section.title }}</h4>
                                        <p class="text-[11px] text-slate-500 mt-0.5">{{ section.questions_count }} butir pertanyaan aktif</p>
                                    </div>
                                </div>
                                <Link
                                    href="/prodi/kuesioner"
                                    class="text-xs font-bold text-[#0D542B] hover:underline"
                                >
                                    Kelola Soal &rarr;
                                </Link>
                            </div>

                            <div v-if="sectionsList.length === 0" class="py-8 text-center text-slate-400">
                                Belum ada section kuesioner prodi yang dibuat.
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
