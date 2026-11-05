<!--
  Project: SERU (Sistem Ekosistem Rekam Jejak Alumni) - Tracer Study Universitas Kristen Duta Wacana (UKDW)
  Folder: resources/js/Pages/Alumni/Components/
  File: AlumniDirectoryShared.vue
  Deskripsi: Komponen bersama direktori mahasiswa dan alumni beserta audit kelengkapan tracer study, filter kohort, pencarian, dan aksi unduh rekap terpadu lintas peran (Super Admin, Fakultas, Prodi, Biro 3).
-->
<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Swal from 'sweetalert2';
import AdminSidebar from '@/components/AdminSidebar.vue';

const props = defineProps({
    user: {
        type: Object,
        default: null,
    },
    role: {
        type: String,
        default: 'superadmin',
    },
    roleTitle: {
        type: String,
        default: 'Super Administrator',
    },
    prefix: {
        type: String,
        default: 'superadmin',
    },
    prodi: {
        type: Object,
        default: null,
    },
    fakultas: {
        type: Object,
        default: null,
    },
    alumnis: {
        type: Array,
        default: () => [],
    },
    daftarTahun: {
        type: Array,
        default: () => [],
    },
    daftarTarget: {
        type: Array,
        default: () => [],
    },
    prodis: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    stats: {
        type: Object,
        default: () => ({
            total_alumni: 0,
            total_selesai: 0,
            total_belum_selesai: 0,
            persentase_selesai: 0,
            total_linkedin_synced: 0,
            persentase_linkedin: 0,
        }),
    },
    pagination: {
        type: Object,
        default: null,
    },
    canExportZip: {
        type: Boolean,
        default: false,
    },
    canBlastEmail: {
        type: Boolean,
        default: false,
    },
    showProdiFilter: {
        type: Boolean,
        default: true,
    },
});

// State Filter
const defaultTahun = props.filters.tahun || props.daftarTahun[0] || '';
const search = ref(props.filters.search || '');
const tahun = ref(defaultTahun);
const semester = ref(props.filters.semester || 'all');
const target = ref(props.filters.target || 'all');
const status = ref(props.filters.status || 'all');
const prodiId = ref(props.filters.prodi_id || 'all');
const linkedinStatus = ref(props.filters.linkedin_status || 'all');

// Sinkronkan state filter jika server mengembalikan props filters baru
watch(() => props.filters, (newFilters) => {
    if (newFilters) {
        if (newFilters.tahun !== undefined) {
            tahun.value = newFilters.tahun;
        }
        if (newFilters.semester !== undefined) {
            semester.value = newFilters.semester || 'all';
        }
        if (newFilters.target !== undefined) {
            target.value = newFilters.target || 'all';
        }
        if (newFilters.status !== undefined) {
            status.value = newFilters.status || 'all';
        }
        if (newFilters.prodi_id !== undefined) {
            prodiId.value = newFilters.prodi_id || 'all';
        }
        if (newFilters.linkedin_status !== undefined) {
            linkedinStatus.value = newFilters.linkedin_status || 'all';
        }
    }
}, { deep: true });

// Pagination server-side
const perPage = ref(props.pagination?.per_page || 10);
const currentPage = ref(props.pagination?.current_page || 1);

const totalPages = computed(() => {
    return props.pagination?.last_page || 1;
});

const paginationItems = computed(() => {
    const total = totalPages.value;
    const current = currentPage.value;

    if (total <= 7) {
        return Array.from({ length: total }, (_, index) => ({ type: 'page', value: index + 1 }));
    }

    const items = [{ type: 'page', value: 1 }];

    if (current > 4) {
        items.push({ type: 'ellipsis', value: 'start' });
    }

    const start = Math.max(2, current - 1);
    const end = Math.min(total - 1, current + 1);

    for (let page = start; page <= end; page += 1) {
        items.push({ type: 'page', value: page });
    }

    if (current < total - 3) {
        items.push({ type: 'ellipsis', value: 'end' });
    }

    items.push({ type: 'page', value: total });

    return items;
});

const paginatedAlumnis = computed(() => {
    return props.alumnis;
});

const paginationFrom = computed(() => props.pagination?.from || 0);
const paginationTo = computed(() => props.pagination?.to || 0);
const paginationTotal = computed(() => props.pagination?.total || 0);

watch(() => props.pagination, (newPagination) => {
    if (newPagination) {
        currentPage.value = newPagination.current_page || 1;
        perPage.value = newPagination.per_page || perPage.value;
    }
}, { deep: true });

watch(perPage, () => {
    applyFilters(1);
});

const goToPage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
        applyFilters(page);
    }
};

const getInitials = (name) => {
    if (!name) return 'M';
    const clean = name.trim().replace(/[^a-zA-Z\s]/g, '');
    const parts = clean.split(/\s+/).filter(Boolean);
    if (parts.length === 0) return 'M';
    if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
    return (parts[0][0] + parts[1][0]).toUpperCase();
};

let searchTimeout = null;
const applyFilters = (page = 1) => {
    currentPage.value = page;
    router.get(`/${props.prefix}/alumni`, {
        search: search.value || undefined,
        tahun: tahun.value || undefined,
        semester: semester.value !== 'all' ? semester.value : undefined,
        target: target.value !== 'all' ? target.value : undefined,
        status: status.value !== 'all' ? status.value : undefined,
        prodi_id: prodiId.value !== 'all' ? prodiId.value : undefined,
        linkedin_status: linkedinStatus.value !== 'all' ? linkedinStatus.value : undefined,
        page,
        per_page: perPage.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const filterByStatus = (statusValue) => {
    linkedinStatus.value = 'all';
    if (statusValue === 'all') {
        status.value = 'all';
    } else if (status.value === statusValue) {
        status.value = 'all';
    } else {
        status.value = statusValue;
    }
    applyFilters();
};

const filterByLinkedin = (linkedinVal = 'sudah_sinkron') => {
    status.value = 'all';
    if (linkedinStatus.value === linkedinVal) {
        linkedinStatus.value = 'all';
    } else {
        linkedinStatus.value = linkedinVal;
    }
    applyFilters();
};

const handleSearchInput = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 350);
};

const resetFilters = () => {
    search.value = '';
    tahun.value = props.daftarTahun[0] || '';
    semester.value = 'all';
    target.value = 'all';
    status.value = 'all';
    prodiId.value = 'all';
    linkedinStatus.value = 'all';
    currentPage.value = 1;
    applyFilters(1);
};

// Fitur Unduh ZIP per Program Studi
const handleDownloadZip = () => {
    const selectedYear = tahun.value || props.daftarTahun[0] || '';

    if (prodiId.value && prodiId.value !== 'all') {
        const url = `/${props.prefix}/alumni/export-zip?prodi_id=${prodiId.value}&tahun=${encodeURIComponent(selectedYear)}`;
        window.location.href = url;
    } else {
        const prodiOptions = {};
        props.prodis.forEach((p) => {
            prodiOptions[p.id] = `${p.kode_prodi} - ${p.nama_prodi}`;
        });

        Swal.fire({
            title: 'Unduh ZIP Rekap Excel',
            text: `Pilih Program Studi untuk mengunduh arsip ZIP berisi berkas Excel (.xls) per alumni pada Tahun Kelulusan ${selectedYear}:`,
            input: 'select',
            inputOptions: prodiOptions,
            inputPlaceholder: '-- Pilih Program Studi --',
            showCancelButton: true,
            confirmButtonText: 'Unduh ZIP Sekarang',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#0D542B',
            cancelButtonColor: '#64748b',
            inputValidator: (value) => {
                if (!value) {
                    return 'Silakan pilih Program Studi terlebih dahulu!';
                }
            },
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                const url = `/${props.prefix}/alumni/export-zip?prodi_id=${result.value}&tahun=${encodeURIComponent(selectedYear)}`;
                window.location.href = url;
            }
        });
    }
};

// WhatsApp Modal
const showWaModal = ref(false);
const activeWaAlumni = ref(null);
const waRecipientPhone = ref('');
const waMessageText = ref('');

const formatPhoneForWA = (rawPhone) => {
    if (!rawPhone) return '';
    let cleaned = String(rawPhone).replace(/\D/g, '');
    if (cleaned.startsWith('0')) {
        cleaned = '62' + cleaned.slice(1);
    } else if (cleaned.startsWith('8')) {
        cleaned = '62' + cleaned;
    }
    return cleaned;
};

const openWhatsAppModal = (alumni) => {
    activeWaAlumni.value = alumni;
    waRecipientPhone.value = alumni.nomor_telepon || '';
    
    const baseUrl = window.location.origin;
    const loginUrl = `${baseUrl}/login`;

    waMessageText.value = `Halo Sdr/i ${alumni.nama},\n\nSalam hangat dari Universitas Kristen Duta Wacana (UKDW).\n\nKami mengundang Anda untuk melengkapi instrumen kuesioner Tracer Study UKDW melalui portal resmi kami:\n${loginUrl}\n\n*Panduan Masuk ke Portal:*\n• Username: *${alumni.nim}* (NIM Anda)\n• Password: Kata sandi akun Tracer Study Anda\n\nPartisipasi Anda sangat berarti bagi pengembangan kurikulum dan peningkatan mutu almamater. Jika memerlukan bantuan, silakan balas pesan ini.\n\nTerima kasih banyak atas kerjasamanya!\nSalam,\nTim Tracer Study UKDW`;

    showWaModal.value = true;
};

const executeSendWa = () => {
    const formatted = formatPhoneForWA(waRecipientPhone.value);
    if (!formatted) {
        Swal.fire({
            icon: 'warning',
            title: 'Nomor WhatsApp Tidak Ditemukan',
            text: 'Nomor telepon/WhatsApp alumni ini belum terdaftar di sistem. Silakan masukkan nomor terlebih dahulu.',
            confirmButtonColor: '#0D542B',
        });
        return;
    }
    const url = `https://wa.me/${formatted}?text=${encodeURIComponent(waMessageText.value)}`;
    window.open(url, '_blank');
    showWaModal.value = false;
};

// Kirim Email Pengingat Satuan
const handleSendSingleEmail = (alumni) => {
    if (!alumni.email) {
        Swal.fire({
            icon: 'warning',
            title: 'Email Tidak Terdaftar',
            text: `Alumni ${alumni.nama} (${alumni.nim}) belum memiliki alamat email yang tersimpan di sistem.`,
            confirmButtonColor: '#0D542B',
        });
        return;
    }

    Swal.fire({
        title: 'Kirim Email Pengingat?',
        html: `<p class="text-xs text-slate-600">Kirim email resmi pengingat pengisian tracer study beserta panduan login ke <b>${alumni.nama}</b> (<span class="font-mono text-emerald-800">${alumni.email}</span>)?</p>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0D542B',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Ya, Kirim Email',
        cancelButtonText: 'Batal',
    }).then((res) => {
        if (res.isConfirmed) {
            Swal.fire({
                title: 'Mengirim Email...',
                text: 'Mohon tunggu sebentar, sistem sedang memproses pengiriman.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                },
            });

            router.post(`/${props.prefix}/alumni/${alumni.id}/send-email`, {}, {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Email Terkirim',
                        text: `Email pengingat berhasil dikirimkan ke ${alumni.nama} (${alumni.email}).`,
                        confirmButtonColor: '#0D542B',
                    });
                },
                onError: () => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal Mengirim Email',
                        text: 'Terjadi kendala pada server saat memproses pengiriman email.',
                        confirmButtonColor: '#0D542B',
                    });
                },
            });
        }
    });
};

// LinkedIn History Modal
const showLinkedInHistoryModal = ref(false);
const activeLinkedInAlumni = ref(null);
const linkedInHistoryList = ref([]);
const isLoadingHistory = ref(false);

const openLinkedInHistoryModal = async (alumni) => {
    activeLinkedInAlumni.value = alumni;
    showLinkedInHistoryModal.value = true;
    isLoadingHistory.value = true;
    linkedInHistoryList.value = [];

    try {
        const response = await fetch(`/${props.prefix}/linkedin-sync/alumni/${alumni.id}/history`);
        if (response.ok) {
            const data = await response.json();
            linkedInHistoryList.value = data.history || [];
        }
    } catch {
        linkedInHistoryList.value = [];
    } finally {
        isLoadingHistory.value = false;
    }
};

const formatTanggal = (dateStr) => {
    if (!dateStr) return '-';
    try {
        const d = new Date(dateStr);
        return d.toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return dateStr;
    }
};
</script>

<template>
    <Head :title="`Direktori Mahasiswa & Tracer Study - ${roleTitle}`" />

    <div class="min-h-screen bg-[#F8FAFC] text-slate-800 font-sans flex">
        <!-- Sidebar Terpadu dengan Kontrak Peran -->
        <AdminSidebar :user="user" :role="role" :prodi="prodi" :fakultas="fakultas" />

        <!-- Area Konten Utama -->
        <div class="flex-1 flex flex-col min-w-0 lg:pl-72">
            
            <!-- Header Solid Hijau Resmi UKDW #0D542B -->
            <header class="admin-page-header bg-[#0D542B] text-white pt-8 pb-16 px-4 sm:px-6 lg:px-8 border-b border-emerald-900/40">
                <div class="w-full max-w-[1440px] mx-auto flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div>
                        <!-- Breadcrumbs -->
                        <nav class="flex items-center gap-2 text-xs text-emerald-100 font-medium mb-2.5">
                            <Link :href="`/${prefix}/dashboard`" class="hover:text-white transition-colors flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                                <span>Dashboard</span>
                            </Link>
                            <span class="text-emerald-400">/</span>
                            <span class="text-white font-semibold">Data Alumni</span>
                        </nav>

                        <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight">
                            Direktori Mahasiswa &amp; Tracer Study
                        </h1>
                        <p class="text-emerald-100/85 text-xs sm:text-sm mt-1 max-w-2xl font-normal leading-relaxed">
                            Pemantauan audit kelengkapan kuesioner tracer study dan profil responden alumni Universitas Kristen Duta Wacana.
                        </p>
                    </div>

                    <!-- Tombol Aksi Header -->
                    <div class="flex items-center gap-3 shrink-0 flex-wrap">
                        <button 
                            v-if="canExportZip"
                            @click="handleDownloadZip" 
                            type="button"
                            class="px-4 py-2.5 bg-[#FDC700] hover:bg-[#e6b400] text-slate-900 rounded-xl text-xs font-bold transition-all shadow-sm flex items-center gap-2 cursor-pointer active:scale-95"
                            title="Unduh seluruh instrumen dan audit kuesioner alumni per Program Studi dalam format ZIP"
                        >
                            <svg class="w-4 h-4 text-slate-900" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            <span>Unduh ZIP Excel Prodi</span>
                        </button>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="admin-page-main flex-1 w-full max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8 -mt-10 pb-16 space-y-6">

                <!-- KARTU STATISTIK RINGKASAN AUDIT ALUMNI -->
                <div class="grid grid-cols-2 md:grid-cols-5 gap-3 sm:gap-4">
                    <!-- 1. Total Mahasiswa -->
                    <div 
                        @click="filterByStatus('all')"
                        class="bg-white rounded-2xl p-4 sm:p-5 border shadow-xs transition-all cursor-pointer relative overflow-hidden group"
                        :class="status === 'all' && linkedinStatus === 'all' ? 'border-[#0D542B] ring-2 ring-[#0D542B]/10' : 'border-slate-200/80 hover:border-slate-300'"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] sm:text-xs font-bold uppercase tracking-wider text-slate-600">Total Mahasiswa</span>
                            <span v-if="status === 'all' && linkedinStatus === 'all'" class="text-[9px] font-bold bg-[#0D542B] text-white px-2 py-0.5 rounded-full">Aktif</span>
                        </div>
                        <div class="text-2xl sm:text-3xl font-black text-slate-900 mt-2">
                            {{ stats.total_alumni || 0 }}
                        </div>
                        <div class="text-[11px] text-slate-600 mt-1 font-medium truncate">
                            Tahun Kelulusan {{ tahun }}
                        </div>
                    </div>

                    <!-- 2. Kuesioner Selesai -->
                    <div 
                        @click="filterByStatus('selesai')"
                        class="bg-white rounded-2xl p-4 sm:p-5 border shadow-xs transition-all cursor-pointer relative overflow-hidden group"
                        :class="status === 'selesai' ? 'border-[#0D542B] ring-2 ring-[#0D542B]/10' : 'border-slate-200/80 hover:border-slate-300'"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] sm:text-xs font-bold uppercase tracking-wider text-slate-600">Kuesioner Selesai</span>
                            <span v-if="status === 'selesai'" class="text-[9px] font-bold bg-[#0D542B] text-white px-2 py-0.5 rounded-full">Aktif</span>
                        </div>
                        <div class="text-2xl sm:text-3xl font-black text-slate-900 mt-2">
                            {{ stats.total_selesai || 0 }}
                        </div>
                        <div class="text-[11px] text-slate-600 mt-1 font-medium truncate">
                            Lengkap 100%
                        </div>
                    </div>

                    <!-- 3. Belum Selesai -->
                    <div 
                        @click="filterByStatus('belum_selesai')"
                        class="bg-white rounded-2xl p-4 sm:p-5 border shadow-xs transition-all cursor-pointer relative overflow-hidden group"
                        :class="status === 'belum_selesai' ? 'border-[#0D542B] ring-2 ring-[#0D542B]/10' : 'border-slate-200/80 hover:border-slate-300'"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] sm:text-xs font-bold uppercase tracking-wider text-slate-600">Belum Selesai</span>
                            <span v-if="status === 'belum_selesai'" class="text-[9px] font-bold bg-[#0D542B] text-white px-2 py-0.5 rounded-full">Aktif</span>
                        </div>
                        <div class="text-2xl sm:text-3xl font-black text-slate-900 mt-2">
                            {{ stats.total_belum_selesai || 0 }}
                        </div>
                        <div class="text-[11px] text-slate-600 mt-1 font-medium truncate">
                            Perlu tindak lanjut
                        </div>
                    </div>

                    <!-- 4. Rasio Selesai -->
                    <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200/80 shadow-xs relative overflow-hidden">
                        <span class="text-[10px] sm:text-xs font-bold uppercase tracking-wider text-slate-600">Rasio Selesai</span>
                        <div class="text-2xl sm:text-3xl font-black text-slate-900 mt-2">
                            {{ stats.persentase_selesai || 0 }}%
                        </div>
                        <div class="text-[11px] text-slate-600 mt-1 font-medium truncate">
                            Kepatuhan responden
                        </div>
                    </div>

                    <!-- 5. Tersinkron LinkedIn -->
                    <div 
                        @click="filterByLinkedin('sudah_sinkron')"
                        class="bg-white rounded-2xl p-4 sm:p-5 border shadow-xs transition-all cursor-pointer relative overflow-hidden group col-span-2 md:col-span-1"
                        :class="linkedinStatus === 'sudah_sinkron' ? 'border-[#0D542B] ring-2 ring-[#0D542B]/10' : 'border-slate-200/80 hover:border-slate-300'"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] sm:text-xs font-bold uppercase tracking-wider text-slate-600">Tersinkron LinkedIn</span>
                            <span v-if="linkedinStatus === 'sudah_sinkron'" class="text-[9px] font-bold bg-[#0D542B] text-white px-2 py-0.5 rounded-full">Aktif</span>
                        </div>
                        <div class="text-2xl sm:text-3xl font-black text-slate-900 mt-2">
                            {{ stats.total_linkedin_synced || 0 }}
                        </div>
                        <div class="text-[11px] text-slate-600 mt-1 font-medium truncate">
                            {{ stats.persentase_linkedin || 0 }}% dari total alumni
                        </div>
                    </div>
                </div>

                <!-- FILTER BAR PENCARIAN & PARAMETER KELULUSAN -->
                <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-xs space-y-4">
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-2">
                            <h2 class="text-xs font-extrabold uppercase tracking-wider text-slate-700">Filter &amp; Pencarian Mahasiswa</h2>
                            <span v-if="tahun" class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-md">
                                Tahun: {{ tahun }}
                            </span>
                        </div>
                        <button 
                            @click="resetFilters" 
                            type="button" 
                            class="text-xs font-bold text-slate-600 hover:text-[#0D542B] transition-colors cursor-pointer"
                        >
                            Reset Filter
                        </button>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-6 gap-3 pt-1">
                        <!-- 1. Cari Nama / NIM -->
                        <div class="sm:col-span-2">
                            <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Cari Nama / NIM</label>
                            <div class="relative">
                                <input 
                                    v-model="search" 
                                    @input="handleSearchInput" 
                                    type="text" 
                                    placeholder="Ketik nama atau NIM..." 
                                    class="w-full text-xs font-medium bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 pl-9 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0D542B]/20 focus:border-[#0D542B] transition-all"
                                />
                                <svg class="w-4 h-4 text-slate-600 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </div>
                        </div>

                        <!-- 2. Tahun Kelulusan -->
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">
                                Tahun Kelulusan <span class="text-emerald-700 font-bold">*</span>
                            </label>
                            <select 
                                v-model="tahun" 
                                @change="applyFilters(1)"
                                class="w-full text-xs font-semibold bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0D542B]/20 focus:border-[#0D542B] transition-all cursor-pointer"
                            >
                                <option v-for="t in daftarTahun" :key="t" :value="t">
                                    Tahun {{ t }}
                                </option>
                            </select>
                        </div>

                        <!-- 3. Target Kelulusan (Semester + Tahun) -->
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Target Kelulusan</label>
                            <select 
                                v-model="target" 
                                @change="applyFilters(1)"
                                class="w-full text-xs font-semibold bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0D542B]/20 focus:border-[#0D542B] transition-all cursor-pointer"
                            >
                                <option value="all">Semua Target</option>
                                <option v-for="tgt in daftarTarget" :key="tgt" :value="tgt">
                                    {{ tgt }}
                                </option>
                            </select>
                        </div>

                        <!-- 4. Semester -->
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Semester</label>
                            <select 
                                v-model="semester" 
                                @change="applyFilters(1)"
                                class="w-full text-xs font-semibold bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0D542B]/20 focus:border-[#0D542B] transition-all cursor-pointer"
                            >
                                <option value="all">Semua Semester</option>
                                <option value="Gasal">Gasal</option>
                                <option value="Genap">Genap</option>
                            </select>
                        </div>

                        <!-- 5. Status Kuesioner -->
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Status Kuesioner</label>
                            <select 
                                v-model="status" 
                                @change="applyFilters(1)"
                                class="w-full text-xs font-semibold bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0D542B]/20 focus:border-[#0D542B] transition-all cursor-pointer"
                            >
                                <option value="all">Semua Status</option>
                                <option value="selesai">Selesai (100%)</option>
                                <option value="belum_selesai">Belum Selesai</option>
                            </select>
                        </div>

                        <!-- 6. Status LinkedIn -->
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Status LinkedIn</label>
                            <select 
                                v-model="linkedinStatus" 
                                @change="applyFilters(1)"
                                class="w-full text-xs font-semibold bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0D542B]/20 focus:border-[#0D542B] transition-all cursor-pointer"
                            >
                                <option value="all">Semua LinkedIn</option>
                                <option value="sudah_sinkron">Sudah Disinkron</option>
                                <option value="menunggu_approval">Menunggu Review</option>
                                <option value="belum_sinkron">Belum Disinkron</option>
                            </select>
                        </div>

                        <!-- 7. Filter Program Studi (Opsional) -->
                        <div v-if="showProdiFilter" class="sm:col-span-2 md:col-span-2">
                            <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Program Studi</label>
                            <select 
                                v-model="prodiId" 
                                @change="applyFilters(1)"
                                class="w-full text-xs font-semibold bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0D542B]/20 focus:border-[#0D542B] transition-all cursor-pointer"
                            >
                                <option value="all">Semua Program Studi</option>
                                <option v-for="p in prodis" :key="p.id" :value="p.id">
                                    {{ p.kode_prodi ? `${p.kode_prodi} - ` : '' }}{{ p.nama_prodi }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- TABEL MAHASISWA & ALUMNI -->
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
                    <!-- Table Header Action Area -->
                    <div class="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50/50">
                        <div class="flex items-center gap-2 text-xs text-slate-600 font-semibold">
                            <span>Tampilkan</span>
                            <select 
                                v-model="perPage" 
                                class="text-xs font-bold bg-white border border-slate-200 rounded-lg px-2.5 py-1 focus:outline-none focus:border-[#0D542B] cursor-pointer"
                            >
                                <option :value="10">10</option>
                                <option :value="25">25</option>
                                <option :value="50">50</option>
                            </select>
                            <span>data per halaman</span>
                        </div>

                        <div class="text-xs font-semibold text-slate-600">
                            Menampilkan <span class="font-bold text-slate-900">{{ paginationFrom }} – {{ paginationTo }}</span> dari <span class="font-bold text-slate-900">{{ paginationTotal }}</span> total mahasiswa (Tahun {{ tahun }})
                        </div>
                    </div>

                    <!-- Tabel Data -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200/80 text-[11px] font-bold text-slate-600 uppercase tracking-wider">
                                    <th class="py-3 px-4 w-12 text-center">No</th>
                                    <th class="py-3 px-4 min-w-[200px]">Mahasiswa / Alumni</th>
                                    <th class="py-3 px-4 min-w-[150px]">Program Studi</th>
                                    <th class="py-3 px-4 min-w-[130px]">Target Kelulusan</th>
                                    <th class="py-3 px-4 min-w-[160px]">Status Kelengkapan</th>
                                    <th class="py-3 px-4 min-w-[140px]">Sinkronisasi LinkedIn</th>
                                    <th class="py-3 px-4 w-28 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr 
                                    v-for="(item, idx) in paginatedAlumnis" 
                                    :key="item.id || item.nim"
                                    class="hover:bg-slate-50/80 transition-colors group"
                                >
                                    <!-- No -->
                                    <td class="py-3.5 px-4 text-center font-semibold text-slate-600">
                                        {{ paginationFrom + idx }}
                                    </td>

                                    <!-- Mahasiswa / Alumni -->
                                    <td class="py-3.5 px-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-emerald-50 text-[#0D542B] font-bold text-xs flex items-center justify-center shrink-0 border border-emerald-200/60 shadow-2xs">
                                                {{ getInitials(item.nama) }}
                                            </div>
                                            <div class="min-w-0">
                                                <Link 
                                                    :href="`/${prefix}/alumni/${item.id}`"
                                                    class="font-bold text-slate-900 hover:text-[#0D542B] transition-colors truncate block text-xs"
                                                >
                                                    {{ item.nama || 'Mahasiswa' }}
                                                </Link>
                                                <div class="text-[11px] text-slate-600 font-mono tracking-tight flex items-center gap-1.5 mt-0.5">
                                                    <span>{{ item.nim }}</span>
                                                    <span v-if="item.email" class="text-slate-600">&bull;</span>
                                                    <span v-if="item.email" class="text-slate-600 font-sans truncate max-w-[140px]">{{ item.email }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Program Studi -->
                                    <td class="py-3.5 px-4">
                                        <div class="font-semibold text-slate-800 text-xs truncate max-w-[160px]">
                                            {{ item.nama_prodi || '-' }}
                                        </div>
                                        <div class="text-[11px] text-slate-600 truncate max-w-[160px]">
                                            {{ item.nama_fakultas || '-' }}
                                        </div>
                                    </td>

                                    <!-- Target Kelulusan -->
                                    <td class="py-3.5 px-4">
                                        <div class="font-semibold text-slate-800 text-xs">
                                            {{ item.tahun_akademik_lulus || '-' }}
                                        </div>
                                        <div class="text-[11px] text-slate-600 mt-0.5">
                                            Tahun {{ item.tahun_lulus || '-' }} &bull; {{ item.status_yudisium || 'Lulus' }}
                                        </div>
                                    </td>

                                    <!-- Status Kelengkapan -->
                                    <td class="py-3.5 px-4">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span 
                                                class="px-2 py-0.5 text-[10px] font-bold rounded-md"
                                                :class="(item.univ_percentage ?? item.progress?.questionnaire?.percentage ?? 0) >= 100 ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-slate-100 text-slate-700'"
                                            >
                                                Univ: {{ item.univ_percentage ?? item.progress?.questionnaire?.percentage ?? 0 }}%
                                            </span>
                                            <span 
                                                v-if="(item.total_prodi_questions ?? item.progress?.prodi?.total_questions ?? 0) > 0"
                                                class="px-2 py-0.5 text-[10px] font-bold rounded-md"
                                                :class="(item.prodi_percentage ?? item.progress?.prodi?.percentage ?? 0) >= 100 ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-slate-100 text-slate-700'"
                                            >
                                                Prodi: {{ item.prodi_percentage ?? item.progress?.prodi?.percentage ?? 0 }}%
                                            </span>
                                        </div>
                                        <div class="text-[10px] text-slate-600 mt-1 flex items-center gap-1">
                                            <span>Profil: <b :class="(item.is_profile_complete ?? item.progress?.profile?.is_complete) ? 'text-emerald-700' : 'text-slate-600'">{{ (item.is_profile_complete ?? item.progress?.profile?.is_complete) ? 'Lengkap' : 'Belum' }}</b></span>
                                            <span>&bull;</span>
                                            <span>Atasan: <b :class="(item.evaluasi_atasan_status || item.evaluasi_atasan?.status) === 'submitted' ? 'text-emerald-700' : 'text-slate-600'">{{ (item.evaluasi_atasan_status || item.evaluasi_atasan?.status) === 'submitted' ? 'Diisi' : 'Belum Ada' }}</b></span>
                                        </div>
                                    </td>

                                    <!-- Sinkronisasi LinkedIn -->
                                    <td class="py-3.5 px-4">
                                        <div class="flex items-center gap-2">
                                            <span 
                                                v-if="(item.linkedin_sync_status || item.linkedin_sync?.status) === 'approved'"
                                                class="px-2.5 py-0.5 bg-emerald-50 border border-emerald-200 text-emerald-800 font-bold text-[10px] rounded-full inline-flex items-center gap-1"
                                            >
                                                <svg class="w-3 h-3 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                                <span>Tersinkron</span>
                                            </span>
                                            <span 
                                                v-else-if="(item.linkedin_sync_status || item.linkedin_sync?.status) === 'scraped' || (item.linkedin_sync_status || item.linkedin_sync?.status) === 'pending'"
                                                class="px-2.5 py-0.5 bg-amber-50 border border-amber-200 text-amber-800 font-bold text-[10px] rounded-full"
                                            >
                                                Menunggu Review
                                            </span>
                                            <span 
                                                v-else
                                                class="px-2.5 py-0.5 bg-slate-100 text-slate-600 font-medium text-[10px] rounded-full"
                                            >
                                                Belum Disinkron
                                            </span>
                                        </div>
                                        <div class="text-[10px] text-slate-600 mt-1 truncate max-w-[150px]">
                                            <span v-if="item.linkedin_username || item.linkedin_url" class="font-mono">{{ item.linkedin_username || 'Ada link' }}</span>
                                            <span v-else>Belum ada riwayat</span>
                                        </div>
                                    </td>

                                    <!-- Aksi -->
                                    <td class="py-3.5 px-4 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <!-- Detail Button -->
                                            <Link 
                                                :href="`/${prefix}/alumni/${item.id}`"
                                                class="p-1.5 text-slate-600 hover:text-[#0D542B] hover:bg-emerald-50 rounded-lg transition-colors cursor-pointer"
                                                title="Lihat Detail Alumni & Audit Kuesioner"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            </Link>

                                            <!-- WhatsApp Button -->
                                            <button 
                                                @click="openWhatsAppModal(item)"
                                                type="button"
                                                class="p-1.5 text-slate-600 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg transition-colors cursor-pointer"
                                                title="Hubungi via WhatsApp"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                            </button>

                                            <!-- Email Reminder Button -->
                                            <button 
                                                @click="handleSendSingleEmail(item)"
                                                type="button"
                                                class="p-1.5 text-slate-600 hover:text-amber-700 hover:bg-amber-50 rounded-lg transition-colors cursor-pointer"
                                                title="Kirim Email Pengingat Kuesioner"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Empty State -->
                                <tr v-if="paginatedAlumnis.length === 0">
                                    <td colspan="7" class="py-12 text-center text-slate-600">
                                        <p class="font-bold text-sm text-slate-700">Tidak ada data mahasiswa atau alumni</p>
                                        <p class="text-xs text-slate-600 mt-1">Coba sesuaikan kata kunci pencarian atau parameter filter tahun kelulusan.</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Controls -->
                    <div v-if="totalPages > 1" class="px-5 py-4 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50/50">
                        <div class="text-xs text-slate-600 font-semibold">
                            Halaman <span class="font-bold text-slate-900">{{ currentPage }}</span> dari <span class="font-bold text-slate-900">{{ totalPages }}</span>
                        </div>

                        <div class="flex items-center gap-1.5">
                            <button 
                                @click="goToPage(currentPage - 1)" 
                                :disabled="currentPage === 1"
                                class="px-3 py-1.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed transition-all inline-flex items-center gap-1 shadow-2xs"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                                <span>Sebelumnya</span>
                            </button>

                            <template v-for="item in paginationItems" :key="`${item.type}-${item.value}`">
                                <span
                                    v-if="item.type === 'ellipsis'"
                                    class="flex h-8 w-8 items-center justify-center text-xs font-bold text-slate-600"
                                    aria-hidden="true"
                                >
                                    …
                                </span>
                                <button
                                    v-else
                                    @click="goToPage(item.value)"
                                    class="h-8 w-8 rounded-xl text-xs font-bold transition-all shadow-2xs cursor-pointer"
                                    :class="currentPage === item.value ? 'bg-[#0D542B] text-white shadow-xs' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-100'"
                                >
                                    {{ item.value }}
                                </button>
                            </template>

                            <button 
                                @click="goToPage(currentPage + 1)" 
                                :disabled="currentPage === totalPages"
                                class="px-3 py-1.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed transition-all inline-flex items-center gap-1 shadow-2xs"
                            >
                                <span>Selanjutnya</span>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                            </button>
                        </div>
                    </div>
                </div>

            </main>
        </div>

        <!-- MODAL WHATSAPP -->
        <div v-if="showWaModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl border border-slate-200/80 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-sm font-extrabold text-slate-900">Hubungi via WhatsApp</h3>
                    <button @click="showWaModal = false" class="text-slate-600 hover:text-slate-600 text-lg">&times;</button>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Nomor WhatsApp Alumni</label>
                    <input 
                        v-model="waRecipientPhone" 
                        type="text" 
                        class="w-full text-xs font-mono bg-slate-50 border border-slate-200 rounded-xl px-3 py-2"
                        placeholder="Contoh: 08123456789"
                    />
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Pesan Ajakan</label>
                    <textarea 
                        v-model="waMessageText" 
                        rows="6" 
                        class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl p-3 leading-relaxed"
                    ></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button @click="showWaModal = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold">Batal</button>
                    <button @click="executeSendWa" class="px-4 py-2 bg-[#0D542B] hover:bg-[#093c1f] text-white rounded-xl text-xs font-bold">Buka WhatsApp</button>
                </div>
            </div>
        </div>

    </div>
</template>

