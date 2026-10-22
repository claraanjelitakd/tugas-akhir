<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';
import Sidebar from '../Components/Sidebar.vue';

const props = defineProps({
    user: Object,
    logs: Object,
    filters: Object,
    actionsList: {
        type: Array,
        default: () => [],
    },
    stats: {
        type: Object,
        default: () => ({
            total_logs: 0,
            total_today: 0,
        }),
    },
});

const search = ref(props.filters.search || '');
const selectedAction = ref(props.filters.action || '');
const perPage = ref(props.filters.per_page || 15);

let searchTimeout = null;
const handleSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 400);
};

const applyFilters = () => {
    router.get('/superadmin/logs', {
        search: search.value || undefined,
        action: selectedAction.value || undefined,
        per_page: perPage.value != 15 ? perPage.value : undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    search.value = '';
    selectedAction.value = '';
    perPage.value = 15;
    router.get('/superadmin/logs', {}, { preserveState: true });
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
};

const openDetailModal = (log) => {
    const oldValJson = log.old_values ? JSON.stringify(log.old_values, null, 2) : 'Tidak ada data lama';
    const newValJson = log.new_values ? JSON.stringify(log.new_values, null, 2) : 'Tidak ada data baru';

    Swal.fire({
        title: `<span class="text-base font-bold text-gray-900">Detail Log Audit #${log.id}</span>`,
        html: `
            <div class="text-left text-xs space-y-3 pt-1 text-gray-800">
                <div class="grid grid-cols-2 gap-2 bg-gray-50 p-3 rounded border border-gray-200">
                    <div><span class="text-gray-500">Pelaku:</span> <strong>${log.user?.name || 'Sistem'}</strong></div>
                    <div><span class="text-gray-500">Username:</span> <strong>${log.user?.username || '-'}</strong></div>
                    <div><span class="text-gray-500">Role:</span> <strong>${log.user?.role || '-'}</strong></div>
                    <div><span class="text-gray-500">IP Address:</span> <strong>${log.ip_address || '-'}</strong></div>
                    <div class="col-span-2"><span class="text-gray-500">Waktu:</span> <strong>${formatDate(log.created_at)}</strong></div>
                </div>

                <div>
                    <label class="block font-bold text-gray-700 mb-1">Aksi & Deskripsi:</label>
                    <div class="p-2 bg-emerald-50 text-emerald-900 border border-emerald-200 rounded font-medium">${log.action} - ${log.description || '-'}</div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-2 pt-1">
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Nilai Lama (Old Values):</label>
                        <pre class="p-2 bg-slate-900 text-slate-100 rounded text-[11px] overflow-x-auto max-h-48 font-mono">${oldValJson}</pre>
                    </div>
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Nilai Baru / Detail (New Values):</label>
                        <pre class="p-2 bg-slate-900 text-emerald-300 rounded text-[11px] overflow-x-auto max-h-48 font-mono">${newValJson}</pre>
                    </div>
                </div>
            </div>
        `,
        width: '720px',
        confirmButtonText: 'Tutup',
        confirmButtonColor: '#0D542B',
    });
};
</script>

<template>
    <Head title="Log Aktivitas Sistem - Super Admin Tracer Study UKDW" />

    <div class="min-h-screen bg-slate-50 flex font-sans">
        <Sidebar :user="user" />

        <div class="flex-1 flex flex-col min-w-0 lg:pl-72">
            <!-- Header Solid UKDW Green Konsisten -->
            <div class="admin-page-header bg-[#0D542B] text-white pt-8 pb-16 px-4 sm:px-6 lg:px-8 border-b border-emerald-900/40">
                <div class="w-full max-w-[1440px] mx-auto flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2 text-xs text-white/70 font-medium mb-1.5">
                            <Link href="/superadmin/dashboard" class="hover:text-white transition-colors">Dashboard</Link>
                            <span>/</span>
                            <span class="text-white font-semibold">Log Aktivitas</span>
                        </div>
                        <h1 class="text-2xl font-bold tracking-tight text-white">
                            Log Aktivitas & Audit Trail Sistem
                        </h1>
                        <p class="text-xs text-emerald-100/90 mt-1 max-w-2xl leading-relaxed">
                            Rekam jejak seluruh aktivitas pengguna, verifikasi perusahaan, perubahan data, dan histori login/reset password.
                        </p>
                    </div>

                    <!-- Stat Counters 2-Tone Bersih -->
                    <div class="bg-white/10 border border-white/20 rounded-xl px-6 py-3 flex items-center gap-6 text-center shadow-xs">
                        <div>
                            <div class="text-2xl font-black text-white leading-none mb-1">{{ stats.total_logs }}</div>
                            <div class="text-[10px] font-bold text-emerald-100/80 tracking-wide uppercase">TOTAL AKTIVITAS</div>
                        </div>
                        <div class="w-px h-8 bg-white/20"></div>
                        <div>
                            <div class="text-2xl font-black text-[#FDC700] leading-none mb-1">{{ stats.total_today }}</div>
                            <div class="text-[10px] font-bold text-emerald-100/80 tracking-wide uppercase">AKTIVITAS HARI INI</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kontainer Konten Utama -->
            <main class="admin-page-main w-full max-w-[1440px] mx-auto -mt-10 px-4 sm:px-6 lg:px-8 space-y-6 pb-12">
                <!-- Filter Bar -->
                <div class="bg-white p-3.5 rounded-xl border border-gray-300 flex flex-col md:flex-row md:items-center justify-between gap-3 text-xs shadow-2xs">
                    <div class="flex items-center gap-3 flex-wrap">
                        <!-- Filter Jenis Aksi -->
                        <div class="flex items-center gap-1.5">
                            <span class="font-bold text-gray-700">Filter Aksi:</span>
                            <select
                                v-model="selectedAction"
                                @change="applyFilters"
                                class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white focus:outline-none focus:ring-1 focus:ring-emerald-700 font-medium cursor-pointer"
                            >
                                <option value="">Semua Jenis Aksi (All)</option>
                                <option v-for="act in actionsList" :key="act" :value="act">
                                    {{ act }}
                                </option>
                            </select>
                        </div>

                        <!-- Per Page -->
                        <div class="flex items-center gap-1.5">
                            <span class="text-gray-500 font-medium">Tampilkan:</span>
                            <select
                                v-model="perPage"
                                @change="applyFilters"
                                class="px-2 py-1.5 border border-gray-300 rounded-lg text-xs bg-white focus:outline-none focus:ring-1 focus:ring-emerald-700 font-medium cursor-pointer"
                            >
                                <option :value="15">15 data</option>
                                <option :value="30">30 data</option>
                                <option :value="50">50 data</option>
                            </select>
                        </div>
                    </div>

                    <!-- Search Box -->
                    <div class="relative w-full md:w-72">
                        <input
                            type="text"
                            v-model="search"
                            @input="handleSearch"
                            placeholder="Cari deskripsi, username, IP..."
                            class="w-full pl-9 pr-8 py-1.5 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-emerald-700 placeholder-gray-400 font-medium"
                        />
                        <svg class="w-4 h-4 text-gray-400 absolute left-2.5 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <button
                            v-if="search || selectedAction"
                            @click="resetFilters"
                            class="absolute right-2.5 top-1.5 text-gray-400 hover:text-gray-600 text-xs font-bold"
                            title="Reset Filter"
                        >
                            &times;
                        </button>
                    </div>
                </div>

                <!-- Table View -->
                <div class="bg-white rounded-xl border border-gray-300 shadow-2xs overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-full text-left border-collapse border border-gray-300 text-xs bg-white">
                        <thead>
                            <tr class="bg-gray-100 text-gray-800 font-semibold border-b border-gray-300 text-[11px] whitespace-nowrap">
                                <th class="border border-gray-300 px-2.5 py-2 text-center w-12 whitespace-nowrap">No</th>
                                <th class="border border-gray-300 px-3 py-2 w-44 whitespace-nowrap">Waktu</th>
                                <th class="border border-gray-300 px-3 py-2 w-48 whitespace-nowrap">Pengguna (Pelaku)</th>
                                <th class="border border-gray-300 px-3 py-2 w-44 whitespace-nowrap">Aksi</th>
                                <th class="border border-gray-300 px-3 py-2 whitespace-nowrap">Deskripsi Aktivitas</th>
                                <th class="border border-gray-300 px-3 py-2 w-32 whitespace-nowrap">IP Address</th>
                                <th class="border border-gray-300 px-2.5 py-2 text-center w-16 whitespace-nowrap">Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="logs.data.length === 0">
                                <td colspan="7" class="border border-gray-300 p-8 text-center text-gray-400 italic">
                                    Belum ada rekaman log aktivitas yang sesuai dengan kriteria filter.
                                </td>
                            </tr>
                            <tr
                                v-for="(log, idx) in logs.data"
                                :key="log.id"
                                class="hover:bg-gray-50/80 transition-colors"
                            >
                                <td class="border border-gray-300 px-2.5 py-2 text-center text-gray-600 font-medium">
                                    {{ (logs.current_page - 1) * logs.per_page + idx + 1 }}
                                </td>
                                <td class="border border-gray-300 px-3 py-2 font-semibold text-gray-700 whitespace-nowrap">
                                    {{ formatDate(log.created_at) }}
                                </td>
                                <td class="border border-gray-300 px-3 py-2">
                                    <div v-if="log.user" class="space-y-0.5">
                                        <div class="font-bold text-gray-900">{{ log.user.name }}</div>
                                        <div class="text-[11px] text-gray-500 font-mono">{{ log.user.username }} ({{ log.user.role }})</div>
                                    </div>
                                    <div v-else class="italic text-gray-400">Sistem / Pengunjung</div>
                                </td>
                                <td class="border border-gray-300 px-3 py-2">
                                    <span class="px-2 py-0.5 rounded font-mono text-[10px] font-bold uppercase tracking-wider bg-gray-100 text-gray-700 border border-gray-300 inline-block">
                                        {{ log.action }}
                                    </span>
                                </td>
                                <td class="border border-gray-300 px-3 py-2 leading-relaxed font-medium text-gray-800">
                                    {{ log.description || '-' }}
                                </td>
                                <td class="border border-gray-300 px-3 py-2 font-mono text-gray-600 text-[11px]">
                                    {{ log.ip_address || '-' }}
                                </td>
                                <td class="border border-gray-300 px-2.5 py-2 text-center">
                                    <button
                                        @click="openDetailModal(log)"
                                        class="p-1.5 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors cursor-pointer"
                                        title="Lihat Detail Log"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                    <!-- Pagination -->
                    <div v-if="logs.total > 0" class="px-4 py-3 border-t border-gray-200 bg-slate-50 flex items-center justify-between text-xs text-gray-600">
                        <div>
                            Menampilkan {{ logs.from }} - {{ logs.to }} dari total {{ logs.total }} log.
                        </div>
                        <div class="flex gap-1">
                            <Link
                                v-for="(link, lIdx) in logs.links"
                                :key="lIdx"
                                :href="link.url || '#'"
                                v-html="link.label"
                                class="px-2.5 py-1 rounded text-xs font-semibold transition-colors"
                                :class="[
                                    link.active
                                        ? 'bg-[#0D542B] text-white'
                                        : link.url
                                            ? 'bg-white text-gray-700 hover:bg-gray-200 border border-gray-300'
                                            : 'text-gray-400 cursor-not-allowed pointer-events-none'
                                ]"
                            />
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>
</template>
