<!--
  Halaman Direktori Mahasiswa & Alumni (Super Admin)
  File: resources/js/Pages/SuperAdmin/Alumni/Index.vue
  
  Warna Resmi Solid UKDW (Sesuai Logo & Standar Kampus):
  - Hijau Utama: #0D542B
  - Hijau Hover: #093c1f
  - Kuning Aksen: #FDC700
  - Putih & Slate: #FFFFFF & #F8FAFC
  Desain profesional, rapi, bersih, responsif, dan elegan dengan visual feedback yang jelas.
-->
<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Swal from 'sweetalert2';
import Sidebar from '../Components/Sidebar.vue';

const props = defineProps({
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

// Pagination lokal ala DataTables
const perPage = ref(10);
const currentPage = ref(1);

const totalPages = computed(() => {
    return Math.ceil(props.alumnis.length / perPage.value) || 1;
});

const paginatedAlumnis = computed(() => {
    const start = (currentPage.value - 1) * perPage.value;
    return props.alumnis.slice(start, start + perPage.value);
});

const goToPage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
};

// Helper inisial avatar
const getInitials = (name) => {
    if (!name) return 'M';
    const clean = name.trim().replace(/[^a-zA-Z\s]/g, '');
    const parts = clean.split(/\s+/).filter(Boolean);
    if (parts.length === 0) return 'M';
    if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
    return (parts[0][0] + parts[1][0]).toUpperCase();
};

// Terapkan Filter ke URL
let searchTimeout = null;
const applyFilters = () => {
    currentPage.value = 1;
    router.get('/superadmin/alumni', {
        search: search.value || undefined,
        tahun: tahun.value || undefined,
        semester: semester.value !== 'all' ? semester.value : undefined,
        target: target.value !== 'all' ? target.value : undefined,
        status: status.value !== 'all' ? status.value : undefined,
        prodi_id: prodiId.value !== 'all' ? prodiId.value : undefined,
        linkedin_status: linkedinStatus.value !== 'all' ? linkedinStatus.value : undefined,
    }, {
        preserveState: true,
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
    router.get('/superadmin/alumni', { tahun: tahun.value }, { preserveState: true });
};

// Fitur Unduh ZIP per Program Studi untuk Tahun Filter Tertentu
const handleDownloadZip = () => {
    const selectedYear = tahun.value || props.daftarTahun[0] || '';

    if (prodiId.value && prodiId.value !== 'all') {
        const url = `/superadmin/alumni/export-zip?prodi_id=${prodiId.value}&tahun=${encodeURIComponent(selectedYear)}`;
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
                const url = `/superadmin/alumni/export-zip?prodi_id=${result.value}&tahun=${encodeURIComponent(selectedYear)}`;
                window.location.href = url;
            }
        });
    }
};

// =========================================================================
// FITUR AKSI: WHATSAPP, EMAIL FLASH, & SINKRONISASI LINKEDIN IN-PLACE
// =========================================================================

// 1. WhatsApp Personal Modal
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

// 2. Kirim Email Pengingat Satuan (Flash Direct)
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

            router.post(`/superadmin/alumni/${alumni.id}/send-email`, {}, {
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

// 3. Sinkronisasi LinkedIn Direct & History Modal
const showLinkedInHistoryModal = ref(false);
const activeLinkedInAlumni = ref(null);
const linkedInHistoryList = ref([]);
const isLoadingHistory = ref(false);

const handleSyncLinkedIn = async (alumni) => {
    if (!alumni.linkedin_url) {
        Swal.fire({
            title: 'URL LinkedIn Belum Diisi',
            html: `<p class="text-xs text-slate-600">Alumni <b>${alumni.nama}</b> (${alumni.nim}) belum memiliki URL profil LinkedIn di database.</p>
                   <p class="text-xs text-slate-500 mt-2">Masukkan URL profil LinkedIn untuk memulai sinkronisasi:</p>`,
            icon: 'info',
            input: 'url',
            inputPlaceholder: 'https://www.linkedin.com/in/username',
            showCancelButton: true,
            confirmButtonText: 'Simpan & Sinkronkan',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#0077B5',
            inputValidator: (val) => {
                if (!val || !val.includes('linkedin.com')) {
                    return 'Harap masukkan tautan profil LinkedIn yang valid!';
                }
            }
        }).then(async (res) => {
            if (res.isConfirmed && res.value) {
                try {
                    await fetch(`/superadmin/alumni/${alumni.id}/profile`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                        },
                        body: JSON.stringify({ linkedin_url: res.value })
                    });
                    alumni.linkedin_url = res.value;
                    executeLinkedInSync(alumni);
                } catch (e) {
                    executeLinkedInSync(alumni);
                }
            }
        });
        return;
    }

    Swal.fire({
        title: 'Sinkronkan LinkedIn?',
        html: `<p class="text-xs text-slate-600">Jalankan sinkronisasi profil LinkedIn untuk alumni <b>${alumni.nama}</b>?</p>
               <div class="mt-2 text-xs font-mono text-blue-700 bg-blue-50 p-2 rounded-lg break-all border border-blue-200/60">${alumni.linkedin_url}</div>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0077B5',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Ya, Sinkronkan Sekarang',
        cancelButtonText: 'Batal',
    }).then((res) => {
        if (res.isConfirmed) {
            executeLinkedInSync(alumni);
        }
    });
};

const executeLinkedInSync = async (alumni) => {
    Swal.fire({
        title: 'Menyinkronkan LinkedIn...',
        html: '<p class="text-xs text-slate-600">Sistem sedang menghubungi LinkedIn untuk memperbarui data karir alumni. Mohon tunggu...</p>',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        },
    });

    try {
        const response = await fetch(`/superadmin/linkedin-sync/${alumni.id}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        });

        const data = await response.json();
        Swal.close();

        if (response.ok && data.success) {
            await Swal.fire({
                icon: 'success',
                title: 'Sinkronisasi Berhasil!',
                text: data.message || 'Data LinkedIn alumni berhasil diperbarui.',
                confirmButtonColor: '#0D542B',
            });
            router.reload({ preserveScroll: true });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Sinkronisasi Gagal',
                text: data.message || 'Gagal menyinkronkan profil LinkedIn.',
                confirmButtonColor: '#0D542B',
            });
        }
    } catch (err) {
        Swal.close();
        Swal.fire({
            icon: 'warning',
            title: 'Koneksi Terputus / Waktu Habis',
            text: err.message || 'Koneksi ke server terputus saat sinkronisasi LinkedIn.',
            confirmButtonColor: '#0D542B',
        });
    }
};

const handleOpenLinkedInHistory = async (alumni) => {
    activeLinkedInAlumni.value = alumni;
    showLinkedInHistoryModal.value = true;
    isLoadingHistory.value = true;
    linkedInHistoryList.value = [];

    try {
        const response = await fetch(`/superadmin/linkedin-sync/alumni/${alumni.id}/history`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const data = await response.json();
        if (data.success) {
            linkedInHistoryList.value = data.history || [];
        }
    } catch (err) {
        console.error('Error fetching history:', err);
    } finally {
        isLoadingHistory.value = false;
    }
};
</script>

<template>
    <Head title="Direktori Mahasiswa & Alumni - Super Admin" />

    <div class="min-h-screen bg-[#F8FAFC] text-slate-800 font-sans flex">
        <!-- Sidebar Resmi Super Admin -->
        <Sidebar />

        <!-- Area Konten Utama -->
        <div class="flex-1 flex flex-col min-w-0 lg:pl-72">
            
            <!-- Header Solid Hijau Resmi UKDW #0D542B -->
            <header class="admin-page-header bg-[#0D542B] text-white pt-8 pb-16 px-4 sm:px-6 lg:px-8 border-b border-emerald-900/40">
                <div class="w-full max-w-[1440px] mx-auto flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div>
                        <!-- Breadcrumbs -->
                        <nav class="flex items-center gap-2 text-xs text-emerald-100 font-medium mb-2.5">
                            <Link href="/superadmin/dashboard" class="hover:text-white transition-colors flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                                </svg>
                                <span>Dashboard</span>
                            </Link>
                            <span class="text-emerald-300/60">/</span>
                            <span class="text-white font-bold bg-white/10 px-2 py-0.5 rounded-md">Data Alumni</span>
                        </nav>

                        <h1 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-white tracking-tight">
                            Direktori Mahasiswa & Tracer Study
                        </h1>
                        <p class="text-emerald-100/90 text-xs sm:text-sm font-normal mt-1.5 max-w-2xl leading-relaxed">
                            Pemantauan audit kelengkapan kuesioner tracer study dan profil responden alumni Universitas Kristen Duta Wacana.
                        </p>

                        <!-- Tombol Aksi Cepat Header -->
                        <div class="flex items-center gap-3 mt-4 flex-wrap">
                            <button 
                                type="button"
                                @click="handleDownloadZip"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-[#FDC700] hover:bg-[#e6b400] text-slate-900 rounded-xl text-xs font-extrabold transition-all shadow-xs cursor-pointer active:scale-95"
                                title="Unduh arsip ZIP berisi berkas Excel per alumni untuk Program Studi pada tahun yang difilter"
                            >
                                <svg class="w-4 h-4 text-slate-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                <span>Unduh ZIP Excel Prodi</span>
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Container -->
            <main class="admin-page-main w-full max-w-[1440px] mx-auto -mt-10 px-4 sm:px-6 lg:px-8 space-y-6 pb-12">
                
                <!-- 5 Kartu Statistik Interaktif & Filter Cepat (Formal, Flat, Bersih) -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3.5 sm:gap-4">
                    <!-- 1. Total Mahasiswa (Filter: Semua Status) -->
                    <div 
                        @click="filterByStatus('all')"
                        class="bg-white rounded-xl p-4.5 border cursor-pointer transition-all select-none"
                        :class="status === 'all' && linkedinStatus === 'all' ? 'border-[#0D542B] ring-1 ring-[#0D542B] shadow-xs' : 'border-slate-200 hover:border-slate-300 shadow-2xs'"
                        title="Klik untuk menampilkan semua mahasiswa"
                    >
                        <div class="flex items-center justify-between gap-1.5">
                            <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Total Mahasiswa</p>
                            <span v-if="status === 'all' && linkedinStatus === 'all'" class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-[#0D542B] text-white">Aktif</span>
                        </div>
                        <p class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight mt-1.5">{{ stats.total_alumni }}</p>
                        <p class="text-xs text-slate-500 mt-1">Tahun Kelulusan {{ tahun }}</p>
                    </div>

                    <!-- 2. Kuesioner Selesai (Filter: Selesai) -->
                    <div 
                        @click="filterByStatus('selesai')"
                        class="bg-white rounded-xl p-4.5 border cursor-pointer transition-all select-none"
                        :class="status === 'selesai' ? 'border-[#0D542B] ring-1 ring-[#0D542B] shadow-xs' : 'border-slate-200 hover:border-slate-300 shadow-2xs'"
                        title="Klik untuk memfilter responden kuesioner selesai (100%)"
                    >
                        <div class="flex items-center justify-between gap-1.5">
                            <p class="text-[11px] font-bold text-slate-600 uppercase tracking-wider">Kuesioner Selesai</p>
                            <span v-if="status === 'selesai'" class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-[#0D542B] text-white">Aktif</span>
                        </div>
                        <p class="text-2xl sm:text-3xl font-extrabold text-[#0D542B] tracking-tight mt-1.5">{{ stats.total_selesai }}</p>
                        <p class="text-xs text-slate-500 mt-1">Lengkap 100%</p>
                    </div>

                    <!-- 3. Belum Selesai (Filter: Belum Selesai) -->
                    <div 
                        @click="filterByStatus('belum_selesai')"
                        class="bg-white rounded-xl p-4.5 border cursor-pointer transition-all select-none"
                        :class="status === 'belum_selesai' ? 'border-[#0D542B] ring-1 ring-[#0D542B] shadow-xs' : 'border-slate-200 hover:border-slate-300 shadow-2xs'"
                        title="Klik untuk memfilter responden yang belum selesai"
                    >
                        <div class="flex items-center justify-between gap-1.5">
                            <p class="text-[11px] font-bold text-slate-600 uppercase tracking-wider">Belum Selesai</p>
                            <span v-if="status === 'belum_selesai'" class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-[#0D542B] text-white">Aktif</span>
                        </div>
                        <p class="text-2xl sm:text-3xl font-extrabold text-slate-800 tracking-tight mt-1.5">{{ stats.total_belum_selesai }}</p>
                        <p class="text-xs text-slate-500 mt-1">Perlu tindak lanjut</p>
                    </div>

                    <!-- 4. Rasio Penyelesaian -->
                    <div 
                        @click="filterByStatus('selesai')"
                        class="bg-white rounded-xl p-4.5 border cursor-pointer transition-all select-none"
                        :class="status === 'selesai' ? 'border-[#0D542B] ring-1 ring-[#0D542B] shadow-xs' : 'border-slate-200 hover:border-slate-300 shadow-2xs'"
                        title="Klik untuk memfilter responden selesai"
                    >
                        <div class="flex items-center justify-between gap-1.5">
                            <p class="text-[11px] font-bold text-slate-600 uppercase tracking-wider">Rasio Selesai</p>
                            <span v-if="status === 'selesai'" class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-[#0D542B] text-white">Aktif</span>
                        </div>
                        <p class="text-2xl sm:text-3xl font-extrabold text-[#0D542B] tracking-tight mt-1.5">{{ stats.persentase_selesai }}%</p>
                        <p class="text-xs text-slate-500 mt-1">Kepatuhan responden</p>
                    </div>

                    <!-- 5. Tersinkron LinkedIn (Filter Cepat) -->
                    <div 
                        @click="filterByLinkedin('sudah_sinkron')"
                        class="bg-white rounded-xl p-4.5 border cursor-pointer transition-all select-none"
                        :class="linkedinStatus === 'sudah_sinkron' ? 'border-[#0D542B] ring-1 ring-[#0D542B] shadow-xs' : 'border-slate-200 hover:border-slate-300 shadow-2xs'"
                        title="Klik untuk memfilter alumni yang sudah disinkronkan dengan LinkedIn"
                    >
                        <div class="flex items-center justify-between gap-1.5">
                            <p class="text-[11px] font-bold uppercase tracking-wider" :class="linkedinStatus === 'sudah_sinkron' ? 'text-[#0D542B]' : 'text-slate-600'">Tersinkron LinkedIn</p>
                            <span v-if="linkedinStatus === 'sudah_sinkron'" class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-[#0D542B] text-white">Aktif</span>
                        </div>
                        <p class="text-2xl sm:text-3xl font-extrabold tracking-tight mt-1.5" :class="linkedinStatus === 'sudah_sinkron' ? 'text-[#0D542B]' : 'text-slate-900'">{{ stats.total_linkedin_synced || 0 }}</p>
                        <p class="text-xs text-slate-500 mt-1">{{ stats.persentase_linkedin || 0 }}% dari total alumni</p>
                    </div>
                </div>

                <!-- Panel Filter Komprehensif & Modern -->
                <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-2xs">
                    <!-- Header Filter Panel -->
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                        <div class="flex items-center gap-2.5">
                            <h2 class="text-xs sm:text-sm font-extrabold text-slate-900 uppercase tracking-wider">
                                Filter & Pencarian Mahasiswa
                            </h2>
                            <span class="text-xs px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700 border border-slate-200 font-bold">
                                Tahun: {{ tahun }}
                            </span>
                        </div>
                        <div>
                            <button 
                                @click="resetFilters" 
                                class="text-xs text-slate-500 hover:text-slate-800 font-bold transition-colors cursor-pointer px-2.5 py-1.5 rounded-lg hover:bg-slate-100"
                            >
                                Reset Filter
                            </button>
                        </div>
                    </div>

                    <!-- Grid Form Controls (7 Kolom Responsif) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7 gap-3">
                        <!-- 1. Pencarian Nama / NIM -->
                        <div class="space-y-1">
                            <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wide">
                                Cari Nama / NIM
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                                <input 
                                    type="text" 
                                    v-model="search" 
                                    @input="handleSearchInput" 
                                    placeholder="Ketik nama atau NIM..." 
                                    class="w-full text-xs rounded-xl border border-slate-200 bg-slate-50/80 py-2.5 pl-9 pr-3 font-medium text-slate-900 placeholder:text-slate-400 focus:bg-white focus:border-[#0D542B] focus:ring-2 focus:ring-[#0D542B]/15 outline-none transition-all"
                                />
                            </div>
                        </div>

                        <!-- 2. Filter Tahun Kelulusan -->
                        <div class="space-y-1">
                            <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wide">
                                Tahun Kelulusan <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative">
                                <select 
                                    v-model="tahun" 
                                    @change="applyFilters" 
                                    class="w-full text-xs rounded-xl border border-slate-200 bg-slate-50/80 py-2.5 px-3 font-bold text-slate-900 focus:bg-white focus:border-[#0D542B] focus:ring-2 focus:ring-[#0D542B]/15 outline-none transition-all cursor-pointer"
                                >
                                    <option v-for="t in daftarTahun" :key="t" :value="t">
                                        Tahun {{ t }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- 3. Filter Target Kelulusan -->
                        <div class="space-y-1">
                            <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wide">
                                Target Kelulusan
                            </label>
                            <select 
                                v-model="target" 
                                @change="applyFilters" 
                                class="w-full text-xs rounded-xl border border-slate-200 bg-slate-50/80 py-2.5 px-3 font-medium text-slate-900 focus:bg-white focus:border-[#0D542B] focus:ring-2 focus:ring-[#0D542B]/15 outline-none transition-all cursor-pointer"
                            >
                                <option value="all">Semua Target</option>
                                <option v-for="tgt in daftarTarget" :key="tgt" :value="tgt">
                                    {{ tgt }}
                                </option>
                            </select>
                        </div>

                        <!-- 4. Filter Semester Kelulusan -->
                        <div class="space-y-1">
                            <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wide">
                                Semester
                            </label>
                            <select 
                                v-model="semester" 
                                @change="applyFilters" 
                                class="w-full text-xs rounded-xl border border-slate-200 bg-slate-50/80 py-2.5 px-3 font-medium text-slate-900 focus:bg-white focus:border-[#0D542B] focus:ring-2 focus:ring-[#0D542B]/15 outline-none transition-all cursor-pointer"
                            >
                                <option value="all">Semua Semester</option>
                                <option value="Gasal">Gasal</option>
                                <option value="Genap">Genap</option>
                            </select>
                        </div>

                        <!-- 5. Filter Status Pengisian -->
                        <div class="space-y-1">
                            <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wide">
                                Status Kuesioner
                            </label>
                            <select 
                                v-model="status" 
                                @change="applyFilters" 
                                class="w-full text-xs rounded-xl border border-slate-200 bg-slate-50/80 py-2.5 px-3 font-medium text-slate-900 focus:bg-white focus:border-[#0D542B] focus:ring-2 focus:ring-[#0D542B]/15 outline-none transition-all cursor-pointer"
                            >
                                <option value="all">Semua Status</option>
                                <option value="selesai">Selesai (100%)</option>
                                <option value="belum_selesai">Belum Selesai</option>
                            </select>
                        </div>

                        <!-- 6. Filter Status LinkedIn -->
                        <div class="space-y-1">
                            <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wide">
                                Status LinkedIn
                            </label>
                            <select 
                                v-model="linkedinStatus" 
                                @change="applyFilters" 
                                class="w-full text-xs rounded-xl border border-slate-200 bg-slate-50/80 py-2.5 px-3 font-medium text-slate-900 focus:bg-white focus:border-[#0D542B] focus:ring-2 focus:ring-[#0D542B]/15 outline-none transition-all cursor-pointer"
                            >
                                <option value="all">Semua LinkedIn</option>
                                <option value="sudah_sinkron">Sudah Disinkronkan</option>
                                <option value="pending">Pending Review</option>
                                <option value="belum_sinkron">Belum Disinkron</option>
                                <option value="ditolak">Ditolak</option>
                            </select>
                        </div>

                        <!-- 7. Filter Program Studi -->
                        <div class="space-y-1">
                            <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wide">
                                Program Studi
                            </label>
                            <select 
                                v-model="prodiId" 
                                @change="applyFilters" 
                                class="w-full text-xs rounded-xl border border-slate-200 bg-slate-50/80 py-2.5 px-3 font-medium text-slate-900 focus:bg-white focus:border-[#0D542B] focus:ring-2 focus:ring-[#0D542B]/15 outline-none transition-all cursor-pointer"
                            >
                                <option value="all">Semua Program Studi</option>
                                <option v-for="p in prodis" :key="p.id" :value="p.id">
                                    {{ p.kode_prodi }} - {{ p.nama_prodi }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Card Tabel Mahasiswa ala Master Data (Border Rapi, Tetap/Fixed saat Layar Dikecilkan) -->
                <div class="bg-white rounded-xl border border-gray-300 shadow-2xs overflow-hidden">
                    <!-- Bar Kontrol DataTables -->
                    <div class="p-3.5 sm:p-4 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-gray-50/70">
                        <div class="flex items-center gap-2 text-xs font-medium text-gray-600">
                            <span>Tampilkan</span>
                            <select v-model="perPage" class="text-xs rounded-lg border border-gray-300 bg-white py-1.5 px-2.5 font-bold text-gray-800 outline-none focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] cursor-pointer">
                                <option :value="5">5</option>
                                <option :value="10">10</option>
                                <option :value="25">25</option>
                                <option :value="50">50</option>
                            </select>
                            <span>data per halaman</span>
                        </div>

                        <div class="text-xs text-gray-500 font-medium">
                            Menampilkan <span class="font-bold text-gray-900">{{ (currentPage - 1) * perPage + (alumnis.length > 0 ? 1 : 0) }}</span> &ndash; 
                            <span class="font-bold text-gray-900">{{ Math.min(currentPage * perPage, alumnis.length) }}</span> dari 
                            <span class="font-bold text-gray-900">{{ alumnis.length }}</span> total mahasiswa (Tahun {{ tahun }})
                        </div>
                    </div>

                    <!-- Tabel Data ala Master Data (min-w-[1200px] dengan overflow-x-auto agar fixed/stay saat dikecilkan) -->
                    <div class="w-full overflow-x-auto">
                        <table class="w-full min-w-[1200px] text-left border-collapse border border-gray-300 text-xs bg-white">
                            <thead>
                                <tr class="bg-gray-100 text-gray-800 font-semibold border-b border-gray-300 text-[11px] whitespace-nowrap">
                                    <th class="border border-gray-300 px-2.5 py-2.5 text-center w-12 whitespace-nowrap">No</th>
                                    <th class="border border-gray-300 px-3 py-2.5 min-w-[220px] w-64 whitespace-nowrap">Mahasiswa / Alumni</th>
                                    <th class="border border-gray-300 px-3 py-2.5 min-w-[180px] w-52 whitespace-nowrap">Program Studi</th>
                                    <th class="border border-gray-300 px-3 py-2.5 min-w-[160px] w-44 whitespace-nowrap">Target Kelulusan</th>
                                    <th class="border border-gray-300 px-3 py-2.5 min-w-[210px] w-56 whitespace-nowrap">Status Kelengkapan</th>
                                    <th class="border border-gray-300 px-3 py-2.5 min-w-[240px] w-64 whitespace-nowrap">Sinkronisasi LinkedIn</th>
                                    <th class="border border-gray-300 px-2.5 py-2.5 text-center w-32 whitespace-nowrap">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="paginatedAlumnis.length === 0">
                                    <td colspan="7" class="border border-gray-300 p-8 text-center text-gray-400 italic">
                                        Tidak ada data mahasiswa atau alumni yang cocok dengan kriteria filter aktif saat ini.
                                    </td>
                                </tr>
                                <tr 
                                    v-for="(alumni, idx) in paginatedAlumnis" 
                                    :key="alumni.id" 
                                    class="hover:bg-gray-50/80 transition-colors"
                                    :class="idx % 2 === 0 ? 'bg-white' : 'bg-gray-50/30'"
                                >
                                    <!-- No -->
                                    <td class="border border-gray-300 px-2.5 py-3 text-center font-mono font-medium text-gray-500">
                                        {{ (currentPage - 1) * perPage + idx + 1 }}
                                    </td>

                                    <!-- Identitas Mahasiswa (Nama & NIM Bersih) -->
                                    <td class="border border-gray-300 px-3 py-3">
                                        <div class="font-bold text-gray-900 text-xs">
                                            {{ alumni.nama }}
                                        </div>
                                        <div class="font-mono text-[11px] text-gray-500 mt-0.5">
                                            {{ alumni.nim }}
                                        </div>
                                        <div v-if="alumni.email" class="text-[10px] text-gray-400 truncate max-w-[200px] mt-0.5" :title="alumni.email">
                                            {{ alumni.email }}
                                        </div>
                                    </td>

                                    <!-- Program Studi -->
                                    <td class="border border-gray-300 px-3 py-3">
                                        <div class="font-medium text-gray-800 text-xs">
                                            {{ alumni.prodi }}
                                        </div>
                                        <div v-if="alumni.fakultas && alumni.fakultas !== '-'" class="text-[10px] text-gray-500 mt-0.5">
                                            {{ alumni.fakultas }}
                                        </div>
                                    </td>

                                    <!-- Periode / Target Kelulusan (Bersih & Rapi) -->
                                    <td class="border border-gray-300 px-3 py-3">
                                        <div class="font-medium text-gray-800 text-xs">
                                            {{ alumni.tahun_akademik_lulus }}
                                        </div>
                                        <div class="text-[10px] text-gray-500 mt-0.5">
                                            Tahun {{ alumni.tahun_lulus }} &bull; {{ alumni.status_yudisium || 'Lulus' }}
                                        </div>
                                    </td>

                                    <!-- Kolom Status Kelengkapan: Format Bersih, Proporsional & Formal -->
                                    <td class="border border-gray-300 px-3.5 py-3">
                                        <div class="space-y-1.5 text-xs">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span 
                                                    class="px-2 py-0.5 rounded text-[11px] font-semibold border"
                                                    :class="alumni.kelengkapan?.questionnaire?.is_complete ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-gray-100 text-gray-700 border-gray-200'"
                                                    title="Kuesioner Universitas"
                                                >
                                                    Univ: {{ alumni.kelengkapan?.questionnaire?.is_complete ? '100%' : `${alumni.kelengkapan?.questionnaire?.percentage || 0}%` }}
                                                </span>
                                                <span 
                                                    class="px-2 py-0.5 rounded text-[11px] font-semibold border"
                                                    :class="alumni.kelengkapan?.prodi?.is_complete ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-gray-100 text-gray-700 border-gray-200'"
                                                    title="Kuesioner Program Studi"
                                                >
                                                    Prodi: {{ alumni.kelengkapan?.prodi?.is_complete ? '100%' : `${alumni.kelengkapan?.prodi?.percentage || 0}%` }}
                                                </span>
                                            </div>
                                            <div class="text-[11px] text-gray-500 flex items-center gap-2 pt-0.5">
                                                <span>Profil: <span class="font-semibold" :class="alumni.kelengkapan?.profile?.is_complete ? 'text-emerald-700' : 'text-gray-600'">{{ alumni.kelengkapan?.profile?.is_complete ? 'Lengkap' : 'Belum' }}</span></span>
                                                <span class="text-gray-300">&bull;</span>
                                                <span>Atasan: <span class="font-semibold" :class="alumni.evaluasi_atasan?.is_submitted ? 'text-emerald-700' : (alumni.evaluasi_atasan?.has_evaluasi ? 'text-amber-700' : 'text-gray-500')">{{ alumni.evaluasi_atasan?.is_submitted ? 'Diisi' : (alumni.evaluasi_atasan?.has_evaluasi ? 'Menunggu' : 'Belum Ada') }}</span></span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Kolom Sinkronisasi LinkedIn: Keterangan Waktu, Reviewer, dan Pekerjaan -->
                                    <td class="border border-gray-300 px-3.5 py-3">
                                        <div class="space-y-1">
                                            <!-- Status Badge -->
                                            <div>
                                                <span 
                                                    v-if="alumni.linkedin_sync?.status === 'approved'"
                                                    class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200"
                                                >
                                                    Sudah Disinkronkan
                                                </span>
                                                <span 
                                                    v-else-if="alumni.linkedin_sync?.status === 'pending'"
                                                    class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-50 text-amber-800 border border-amber-200"
                                                >
                                                    Pending Review
                                                </span>
                                                <span 
                                                    v-else-if="alumni.linkedin_sync?.status === 'rejected'"
                                                    class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-rose-50 text-rose-800 border border-rose-200"
                                                >
                                                    Ditolak
                                                </span>
                                                <span 
                                                    v-else
                                                    class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-gray-100 text-gray-600 border border-gray-200"
                                                >
                                                    Belum Disinkron
                                                </span>
                                            </div>

                                            <!-- Terakhir Kapan & Oleh Siapa -->
                                            <div v-if="alumni.linkedin_sync?.waktu_terakhir" class="text-[11px] text-gray-600 leading-tight pt-0.5">
                                                <div>
                                                    <span class="text-gray-400">Terakhir:</span> 
                                                    <span class="font-medium text-gray-700 ml-1">{{ alumni.linkedin_sync.waktu_terakhir }}</span>
                                                </div>
                                                <div v-if="alumni.linkedin_sync.reviewer_name" class="text-gray-500 truncate max-w-[210px]" :title="alumni.linkedin_sync.reviewer_name">
                                                    <span class="text-gray-400">Oleh:</span> 
                                                    <span class="font-medium text-gray-700 ml-1">{{ alumni.linkedin_sync.reviewer_name }}</span>
                                                </div>
                                            </div>
                                            <div v-else class="text-[11px] text-gray-400 italic pt-0.5">
                                                Belum ada riwayat
                                            </div>

                                            <!-- Pekerjaan LinkedIn Terakhir jika ada -->
                                            <div 
                                                v-if="alumni.linkedin_sync?.current_job || alumni.linkedin_sync?.last_job" 
                                                class="text-[11px] text-gray-600 truncate max-w-[210px] font-medium pt-0.5"
                                                :title="`${alumni.linkedin_sync.current_job || alumni.linkedin_sync.last_job} di ${alumni.linkedin_sync.current_company || alumni.linkedin_sync.last_company || '-'}`"
                                            >
                                                {{ alumni.linkedin_sync.current_job || alumni.linkedin_sync.last_job }} <span v-if="alumni.linkedin_sync.current_company || alumni.linkedin_sync.last_company">&bull; {{ alumni.linkedin_sync.current_company || alumni.linkedin_sync.last_company }}</span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Kolom Aksi Terpadu (Monokrom, Formal & Bersih) -->
                                    <td class="border border-gray-300 px-2.5 py-3 text-center">
                                        <div class="inline-flex items-center justify-center gap-1">
                                            <!-- 1. Detail Profil & Kuesioner -->
                                            <Link 
                                                :href="`/superadmin/alumni/${alumni.id}`" 
                                                class="p-1.5 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors cursor-pointer"
                                                title="Lihat Detail Alumni & Kuesioner"
                                                aria-label="Lihat Detail Alumni"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </Link>

                                            <!-- 2. WhatsApp -->
                                            <button 
                                                type="button"
                                                @click="openWhatsAppModal(alumni)"
                                                class="p-1.5 text-gray-600 hover:text-emerald-700 hover:bg-gray-100 rounded-lg transition-colors cursor-pointer"
                                                title="Hubungi via WhatsApp (Undangan Kuesioner)"
                                                aria-label="Hubungi via WhatsApp"
                                            >
                                                <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24">
                                                    <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/>
                                                </svg>
                                            </button>

                                            <!-- 3. Kirim Email Pengingat -->
                                            <button 
                                                type="button"
                                                @click="handleSendSingleEmail(alumni)"
                                                class="p-1.5 text-gray-600 hover:text-sky-700 hover:bg-gray-100 rounded-lg transition-colors cursor-pointer"
                                                title="Kirim Email Pengingat Kuesioner Langsung"
                                                aria-label="Kirim Email Pengingat"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                                </svg>
                                            </button>

                                            <!-- 4. Riwayat / Sinkronisasi LinkedIn Direct -->
                                            <Link
                                                v-if="alumni.linkedin_sync?.status === 'approved' || alumni.linkedin_sync?.status === 'pending'"
                                                :href="`/superadmin/alumni/${alumni.id}?tab=linkedin`"
                                                class="p-1.5 rounded-lg transition-colors cursor-pointer"
                                                :class="alumni.linkedin_sync.status === 'approved' ? 'text-[#0077B5] hover:bg-sky-50' : 'text-amber-700 hover:bg-amber-50'"
                                                :title="`Buka Riwayat LinkedIn: Status ${alumni.linkedin_sync.status_label}`"
                                                aria-label="Buka Riwayat LinkedIn"
                                            >
                                                <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24">
                                                    <path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/>
                                                </svg>
                                            </Link>
                                            <button 
                                                v-else
                                                type="button"
                                                @click="handleSyncLinkedIn(alumni)"
                                                class="p-1.5 text-gray-400 hover:text-[#0077B5] hover:bg-gray-100 rounded-lg transition-colors cursor-pointer"
                                                :title="alumni.linkedin_url ? `Sinkronkan LinkedIn: ${alumni.linkedin_url}` : 'Sinkronkan LinkedIn Alumni (Belum Disinkron)'"
                                                aria-label="Sinkronkan LinkedIn"
                                            >
                                                <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24">
                                                    <path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- DataTables Pagination Controls Modern -->
                    <div v-if="alumnis.length > 0" class="p-4 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50/50">
                        <div class="text-xs text-slate-500 font-medium">
                            Halaman <span class="font-bold text-slate-900">{{ currentPage }}</span> dari <span class="font-bold text-slate-900">{{ totalPages }}</span>
                        </div>

                        <div class="flex items-center gap-1.5">
                            <!-- Tombol Sebelumnya -->
                            <button 
                                @click="goToPage(currentPage - 1)" 
                                :disabled="currentPage === 1"
                                class="px-3 py-1.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed transition-all inline-flex items-center gap-1 shadow-2xs"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                </svg>
                                <span>Sebelumnya</span>
                            </button>
                            
                            <!-- Nomor Halaman -->
                            <button 
                                v-for="p in totalPages" 
                                :key="p"
                                @click="goToPage(p)"
                                class="w-8 h-8 rounded-xl text-xs font-bold transition-all shadow-2xs cursor-pointer"
                                :class="currentPage === p ? 'bg-[#0D542B] text-white shadow-xs' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-100'"
                            >
                                {{ p }}
                            </button>

                            <!-- Tombol Selanjutnya -->
                            <button 
                                @click="goToPage(currentPage + 1)" 
                                :disabled="currentPage === totalPages"
                                class="px-3 py-1.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed transition-all inline-flex items-center gap-1 shadow-2xs"
                            >
                                <span>Selanjutnya</span>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

            </main>
        </div>
        <!-- ================================================================= -->
        <!-- MODAL HUBUNGI VIA WHATSAPP PERSONAL                               -->
        <!-- ================================================================= -->
        <div v-if="showWaModal" class="fixed inset-0 z-50 overflow-y-auto bg-black/60 flex items-center justify-center p-4 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-gray-200 relative animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3 mb-4">
                    <div class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center">
                            <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                        </span>
                        <div>
                            <h3 class="text-sm font-extrabold text-gray-900">Hubungi Alumni via WhatsApp</h3>
                            <p class="text-[11px] text-gray-500">{{ activeWaAlumni?.nama }} ({{ activeWaAlumni?.nim }})</p>
                        </div>
                    </div>
                    <button @click="showWaModal = false" class="text-gray-400 hover:text-gray-600 p-1 text-lg leading-none cursor-pointer">
                        &times;
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Nomor Tujuan WhatsApp:</label>
                        <input 
                            v-model="waRecipientPhone" 
                            type="text" 
                            placeholder="Contoh: 08123456789 atau 628123456789" 
                            class="w-full text-xs font-mono bg-gray-50 border border-gray-300 rounded-xl px-3 py-2 text-gray-900 focus:ring-2 focus:ring-[#0D542B] focus:outline-none"
                        />
                        <p class="text-[10px] text-gray-500 mt-1">Nomor otomatis diformat ke kode negara 62 saat membuka WhatsApp.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Pesan WhatsApp (Termasuk Link & Info Login):</label>
                        <textarea 
                            v-model="waMessageText" 
                            rows="9"
                            class="w-full text-xs bg-gray-50 border border-gray-300 rounded-xl p-3 text-gray-800 leading-relaxed font-sans focus:ring-2 focus:ring-[#0D542B] focus:outline-none"
                        ></textarea>
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end gap-2.5 border-t border-gray-100 pt-3">
                    <button 
                        @click="showWaModal = false"
                        class="px-4 py-2 text-xs font-bold text-gray-600 hover:bg-gray-100 rounded-xl transition-colors cursor-pointer"
                    >
                        Batal
                    </button>
                    <button 
                        @click="executeSendWa"
                        class="px-5 py-2 text-xs font-extrabold bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl shadow-xs transition-all flex items-center gap-1.5 cursor-pointer"
                    >
                        <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                        <span>Buka WhatsApp Web / App</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ================================================================= -->
        <!-- MODAL RIWAYAT (HISTORY) SINKRONISASI LINKEDIN                     -->
        <!-- ================================================================= -->
        <div v-if="showLinkedInHistoryModal" class="fixed inset-0 z-50 overflow-y-auto bg-black/60 flex items-center justify-center p-4 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-2xl w-full p-6 shadow-2xl border border-gray-200 relative animate-in fade-in zoom-in-95 duration-150 max-h-[85vh] flex flex-col">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3 mb-4">
                    <div class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-xl bg-blue-100 text-[#0077B5] flex items-center justify-center font-bold">
                            in
                        </span>
                        <div>
                            <h3 class="text-sm font-extrabold text-gray-900">Riwayat Sinkronisasi LinkedIn</h3>
                            <p class="text-[11px] text-gray-500">{{ activeLinkedInAlumni?.nama }} ({{ activeLinkedInAlumni?.nim }})</p>
                        </div>
                    </div>
                    <button @click="showLinkedInHistoryModal = false" class="text-gray-400 hover:text-gray-600 p-1 text-lg leading-none cursor-pointer">
                        &times;
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto pr-1">
                    <div v-if="isLoadingHistory" class="py-12 text-center text-slate-500 text-xs">
                        <div class="inline-block w-6 h-6 border-2 border-slate-300 border-t-[#0077B5] rounded-full animate-spin mb-2"></div>
                        <p>Memuat riwayat sinkronisasi...</p>
                    </div>

                    <div v-else-if="linkedInHistoryList.length === 0" class="py-12 text-center text-slate-400 text-xs">
                        <p class="font-bold text-slate-600">Belum Ada Riwayat Sinkronisasi</p>
                        <p class="mt-1">Alumni ini belum pernah disinkronkan melalui provider LinkedIn.</p>
                    </div>

                    <div v-else class="space-y-3">
                        <div 
                            v-for="item in linkedInHistoryList" 
                            :key="item.id"
                            class="p-4 rounded-xl border bg-slate-50/70 border-slate-200 flex flex-col gap-2"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-mono text-slate-500 font-semibold">{{ item.scraped_at || '-' }}</span>
                                <span 
                                    class="text-[10px] uppercase font-extrabold px-2 py-0.5 rounded-full"
                                    :class="{
                                        'bg-emerald-100 text-emerald-800': item.status === 'approved',
                                        'bg-amber-100 text-amber-800': item.status === 'pending',
                                        'bg-rose-100 text-rose-800': item.status === 'rejected',
                                    }"
                                >
                                    {{ item.status }}
                                </span>
                            </div>

                            <div v-if="item.preview" class="text-xs bg-white p-3 rounded-lg border border-slate-200/80 space-y-1">
                                <div class="font-bold text-slate-800">{{ item.preview.posisi || 'Posisi / Jabatan tidak terdeteksi' }}</div>
                                <div class="text-slate-600 font-medium">{{ item.preview.perusahaan || 'Perusahaan tidak terdeteksi' }}</div>
                                <div v-if="item.preview.lokasi" class="text-[11px] text-slate-400">📍 {{ item.preview.lokasi }}</div>
                            </div>

                            <div v-if="item.reviewed_at" class="text-[10px] text-slate-500 flex items-center justify-between pt-1 border-t border-slate-200/60">
                                <span>Ditinjau oleh: <b>{{ item.reviewer_name }}</b></span>
                                <span>Waktu tinjau: {{ item.reviewed_at }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-gray-100 flex justify-end">
                    <button 
                        @click="showLinkedInHistoryModal = false"
                        class="px-4 py-2 text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors cursor-pointer"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>

    </div>
</template>
