<!--
  Halaman: Verifikasi & Rekomendasi Approval Perusahaan (Admin Fakultas)
  File: resources/js/Pages/AdminFakultas/Perusahaan/Index.vue
-->
<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Swal from 'sweetalert2';
import Sidebar from '../Components/Sidebar.vue';

const props = defineProps({
    user: Object,
    fakultas: Object,
    prodis: {
        type: Array,
        default: () => [],
    },
    companies: Object,
    propinsis: {
        type: Array,
        default: () => [],
    },
    kabupatens: {
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
            total_pending_fakultas: 0,
            total_pending_all: 0,
            total_verified: 0,
        }),
    },
});

// State Filter & Pencarian
const search = ref(props.filters.search || '');
const prodiId = ref(props.filters.prodi_id || '');
const statusFilter = ref(props.filters.status || 'all');
const perPage = ref(props.filters.per_page || 10);

let searchTimeout = null;
const handleSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 400);
};

const changeStatusTab = (newStatus) => {
    statusFilter.value = newStatus;
    applyFilters();
};

const applyFilters = () => {
    router.get('/fakultas/perusahaan', {
        search: search.value || undefined,
        prodi_id: prodiId.value || undefined,
        status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
        per_page: perPage.value != 10 ? perPage.value : undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    search.value = '';
    prodiId.value = '';
    perPage.value = 10;
    router.get('/fakultas/perusahaan', {}, { preserveState: true });
};

// ========================================================
// ========================================================
// 1. STATE MODAL VUE: DETAIL MASTER PERUSAHAAN (REKOMENDASI)
// ========================================================

// ref() membuat variabel reaktif untuk mengontrol buka/tutup popup detail master
const showRecDetailModal = ref(false);

// Menyimpan referensi objek perusahaan yang diajukan oleh alumni
const activeParentCompany = ref(null);

// Menyimpan data master perusahaan terverifikasi yang direkomendasikan sistem
const activeRecDetail = ref(null);

// Fungsi untuk membuka modal detail master:
// Menerima parentCompany (usulan alumni) dan rec (master terverifikasi yang mirip)
const openRecDetail = (parentCompany, rec) => {
    activeParentCompany.value = parentCompany;
    activeRecDetail.value = rec;
    showRecDetailModal.value = true;
};

// Fungsi untuk menutup modal detail master
const closeRecDetailModal = () => {
    showRecDetailModal.value = false;
    activeParentCompany.value = null;
    activeRecDetail.value = null;
};

// Menjalankan penggantian usulan ke master resmi dari dalam modal detail
const confirmReplaceFromDetail = () => {
    const parent = activeParentCompany.value;
    const rec = activeRecDetail.value;
    closeRecDetailModal();
    if (parent && rec) {
        handleAutoReplace(parent, rec);
    }
};

// ========================================================
// 2. STATE MODAL VUE: PENINJAUAN PENGAJUAN PERUSAHAAN (REVIEW)
// ========================================================

// Mengontrol apakah dialog peninjauan pengajuan perusahaan tampil di layar
const showReviewModal = ref(false);

// Menyimpan objek data perusahaan yang saat ini sedang ditinjau oleh admin fakultas
const activeCompanyForReview = ref(null);

// Membuka modal peninjauan pengajuan perusahaan
const openReviewModal = (company) => {
    activeCompanyForReview.value = company;
    showReviewModal.value = true;
};

// Menutup modal peninjauan pengajuan perusahaan
const closeReviewModal = () => {
    showReviewModal.value = false;
    activeCompanyForReview.value = null;
};

// Aksi 1: Verifikasi Langsung Sesuai Inputan Alumni
const handleVerifyDirect = (company) => {
    Swal.fire({
        title: 'Verifikasi Perusahaan?',
        text: `Apakah Anda yakin ingin memverifikasi "${company.nama_perusahaan}" sebagai master data perusahaan baru?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Verifikasi',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#0D542B',
        cancelButtonColor: '#6B7280',
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(`/fakultas/perusahaan/${company.id}/verify`, {}, {
                preserveScroll: true,
                onSuccess: () => {
                    closeReviewModal();
                    Swal.fire('Berhasil!', `Perusahaan "${company.nama_perusahaan}" telah diverifikasi.`, 'success');
                },
                onError: () => {
                    Swal.fire('Gagal!', 'Terjadi kesalahan saat memverifikasi perusahaan.', 'error');
                },
            });
        }
    });
};

// Aksi 2: Auto Replace dengan Perusahaan Terverifikasi yang Dipilih
const handleAutoReplace = (pendingCompany, targetVerifiedCompany) => {
    Swal.fire({
        title: 'Gantikan dengan Perusahaan Ini?',
        html: `
            <div class="text-left text-xs space-y-2">
                <p>Data inputan alumni: <strong class="text-rose-700">${pendingCompany.nama_perusahaan}</strong></p>
                <p>Akan digantikan dengan master resmi: <strong class="text-emerald-800">${targetVerifiedCompany.nama_perusahaan}</strong></p>
                <div class="p-2.5 bg-amber-50 border border-amber-200 rounded text-amber-900 mt-2">
                    ● Semua relasi alumni yang bekerja di perusahaan ini akan otomatis dialihkan ke master resmi.<br>
                    ● Record pengajuan duplikat akan dibersihkan agar database tetap rapi.
                </div>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Gantikan (Auto Replace)',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#0D542B',
        cancelButtonColor: '#6B7280',
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(`/fakultas/perusahaan/${pendingCompany.id}/replace`, {
                target_company_id: targetVerifiedCompany.id,
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    closeReviewModal();
                    Swal.fire('Berhasil Digantikan!', `Perusahaan berhasil digantikan dengan master "${targetVerifiedCompany.nama_perusahaan}".`, 'success');
                },
                onError: (errors) => {
                    Swal.fire('Gagal!', Object.values(errors).join('<br>'), 'error');
                },
            });
        }
    });
};

// Aksi 3: Tolak Pengajuan
const handleReject = (company) => {
    Swal.fire({
        title: 'Tolak Pengajuan Perusahaan?',
        text: `Tolak pengajuan "${company.nama_perusahaan}"? Status akan diubah menjadi Ditolak.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Tolak',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6B7280',
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(`/fakultas/perusahaan/${company.id}/reject`, {}, {
                preserveScroll: true,
                onSuccess: () => {
                    closeReviewModal();
                    Swal.fire('Ditolak!', `Pengajuan perusahaan "${company.nama_perusahaan}" telah ditolak.`, 'success');
                },
            });
        }
    });
};

// ========================================================
// 3. STATE MODAL VUE: SUNTING & VERIFIKASI PERUSAHAAN (EDIT)
// ========================================================

// Mengontrol apakah dialog modal sunting tampil di layar (true/false)
const showEditModal = ref(false);

// Menandai apakah request simpan suntingan sedang diproses di server
const isSubmittingEdit = ref(false);

// Menyimpan pesan galat validasi jika pengguna belum melengkapi field wajib
const editModalError = ref('');

// Objek form reaktif untuk menampung field sunting data perusahaan
const editForm = ref({
    id: null,
    nama_perusahaan: '',
    propinsi_id: '',
    kabupaten_id: '',
    alamat: '',
    kode_pos: '',
    skala: 'Nasional',
    jenis_perusahaan: 'Perusahaan swasta',
    jenis_lokasi: 'Dalam Negeri',
    negara: 'Indonesia',
});

// computed() menyaring daftar kabupaten sesuai provinsi yang dipilih pada form sunting
const filteredKabupatensForEdit = computed(() => {
    if (!editForm.value.propinsi_id) {
        return [];
    }
    return (props.kabupatens || []).filter(k => String(k.propinsi_id) === String(editForm.value.propinsi_id));
});

// watch() memantau perubahan provinsi pada form sunting
// Jika provinsi berubah dan kabupaten lama tidak ada di provinsi baru, kosongkan pilihan kabupaten
watch(() => editForm.value.propinsi_id, (newPropId) => {
    const isValid = filteredKabupatensForEdit.value.some(k => String(k.id) === String(editForm.value.kabupaten_id));
    if (!newPropId || !isValid) {
        editForm.value.kabupaten_id = '';
    }
});

// Membuka modal sunting data perusahaan dan mengisi nilai awal dari data yang dipilih
const openEditModal = (company) => {
    editModalError.value = '';
    editForm.value = {
        id: company.id,
        nama_perusahaan: company.nama_perusahaan || '',
        propinsi_id: company.propinsi_id || '',
        kabupaten_id: company.kabupaten_id || '',
        alamat: company.alamat || '',
        kode_pos: company.kode_pos || '',
        skala: company.skala || 'Nasional',
        jenis_perusahaan: company.jenis_perusahaan || 'Perusahaan swasta',
        jenis_lokasi: company.jenis_lokasi || 'Dalam Negeri',
        negara: company.negara || 'Indonesia',
    };
    showEditModal.value = true;
};

// Menutup modal sunting data perusahaan
const closeEditModal = () => {
    if (isSubmittingEdit.value) {
        return;
    }
    showEditModal.value = false;
    editModalError.value = '';
};

// Mengirim data hasil suntingan ke server melalui router.put Inertia
const submitEditForm = () => {
    const nama = editForm.value.nama_perusahaan ? editForm.value.nama_perusahaan.trim() : '';
    if (!nama) {
        editModalError.value = 'Nama perusahaan / instansi wajib diisi.';
        return;
    }

    isSubmittingEdit.value = true;
    editModalError.value = '';

    router.put(`/fakultas/perusahaan/${editForm.value.id}`, {
        ...editForm.value,
        nama_perusahaan: nama,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            isSubmittingEdit.value = false;
            closeEditModal();
            if (showReviewModal.value) {
                closeReviewModal();
            }
            Swal.fire({
                icon: 'success',
                title: 'Berhasil Disimpan!',
                text: 'Data perusahaan telah diperbarui dan diverifikasi.',
                confirmButtonColor: '#0D542B',
            });
        },
        onError: (errs) => {
            isSubmittingEdit.value = false;
            editModalError.value = Object.values(errs).join(', ') || 'Terjadi kesalahan saat menyimpan data.';
        },
    });
};
</script>

<template>
    <Head :title="`Verifikasi Perusahaan - ${fakultas?.nama_fakultas || 'Admin Fakultas'}`" />

    <div class="min-h-screen bg-slate-50 flex font-sans">
        <!-- Sidebar Terpadu Admin Fakultas -->
        <Sidebar :user="user" :fakultas="fakultas" />

        <!-- Area Konten Utama -->
        <div class="flex-1 flex flex-col min-w-0 lg:pl-72">
            <!-- Header Halaman Bersih & Flat -->
            <div class="admin-page-header bg-white border-b border-gray-200 px-6 py-5">
                <div class="w-full flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2 text-xs text-gray-500 font-medium mb-1">
                            <Link href="/fakultas/dashboard" class="hover:underline hover:text-[#0D542B]">Dashboard</Link>
                            <span>/</span>
                            <span class="text-gray-800 font-semibold">Verifikasi Perusahaan</span>
                        </div>
                        <h1 class="text-xl font-bold text-gray-900">
                            Verifikasi & Approval Perusahaan Alumni
                        </h1>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Pusat verifikasi, deteksi kemiripan data master, dan persetujuan perusahaan alumni dalam lingkup {{ fakultas?.nama_fakultas }}.
                        </p>
                    </div>

                    <!-- Widget Statistik Dual Sesuai Foto Pengguna -->
                    <div class="bg-slate-50/80 border border-slate-200/80 rounded-2xl px-8 py-3.5 flex items-center gap-8 text-center shadow-2xs">
                        <div>
                            <div class="text-2xl font-black text-[#0D542B] leading-none mb-1.5">{{ stats.total_pending_fakultas }}</div>
                            <div class="text-[11px] font-bold text-slate-600 tracking-wide uppercase">MENUNGGU VERIFIKASI</div>
                        </div>
                        <div class="w-px h-10 bg-slate-300/80"></div>
                        <div>
                            <div class="text-2xl font-black text-[#D97706] leading-none mb-1.5">{{ stats.total_verified }}</div>
                            <div class="text-[11px] font-bold text-slate-600 tracking-wide uppercase">MASTER TERVERIFIKASI</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Konten Utama -->
            <main class="admin-page-main w-full p-6 space-y-4">

                <!-- Tab Navigasi Filter Status Perusahaan -->
                <div class="flex items-center gap-2 border-b border-gray-200 pb-2">
                    <button
                        @click="changeStatusTab('all')"
                        class="px-4 py-2 text-xs font-bold rounded-lg transition-colors flex items-center gap-1.5 cursor-pointer"
                        :class="statusFilter === 'all' ? 'bg-[#0D542B] text-white shadow-xs' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200'"
                    >
                        <span>Semua Perusahaan</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px]" :class="statusFilter === 'all' ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-700'">{{ stats.total_all || companies.total }}</span>
                    </button>
                    <button
                        @click="changeStatusTab('Menunggu Verifikasi')"
                        class="px-4 py-2 text-xs font-bold rounded-lg transition-colors flex items-center gap-1.5 cursor-pointer"
                        :class="statusFilter === 'Menunggu Verifikasi' ? 'bg-[#0D542B] text-white shadow-xs' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200'"
                    >
                        <span>Menunggu Verifikasi</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px]" :class="statusFilter === 'Menunggu Verifikasi' ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-800'">{{ stats.total_pending_fakultas }}</span>
                    </button>
                    <button
                        @click="changeStatusTab('Terverifikasi')"
                        class="px-4 py-2 text-xs font-bold rounded-lg transition-colors flex items-center gap-1.5 cursor-pointer"
                        :class="statusFilter === 'Terverifikasi' ? 'bg-[#0D542B] text-white shadow-xs' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200'"
                    >
                        <span>Master Terverifikasi</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px]" :class="statusFilter === 'Terverifikasi' ? 'bg-white/20 text-white' : 'bg-emerald-100 text-emerald-800'">{{ stats.total_verified }}</span>
                    </button>
                </div>

                <!-- Bilah Filter & Pencarian (DataTables Top Bar) -->
                <div class="bg-white p-3.5 rounded-lg border border-gray-200 flex flex-col md:flex-row md:items-center justify-between gap-3 text-xs shadow-2xs">
                    <div class="flex items-center gap-3 flex-wrap">
                        <!-- Filter Prodi dalam Fakultas -->
                        <div class="flex items-center gap-1.5">
                            <span class="font-bold text-gray-700">Filter Prodi:</span>
                            <select
                                v-model="prodiId"
                                @change="applyFilters"
                                class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white focus:outline-none focus:ring-1 focus:ring-emerald-700 font-medium cursor-pointer"
                            >
                                <option value="">Semua Program Studi Fakultas</option>
                                <option v-for="p in prodis" :key="p.id" :value="p.id">{{ p.nama_prodi }}</option>
                            </select>
                        </div>

                        <!-- Per Page Dropdown (DataTables Length) -->
                        <div class="flex items-center gap-1.5">
                            <span class="text-gray-500 font-medium">Tampilkan:</span>
                            <select
                                v-model="perPage"
                                @change="applyFilters"
                                class="px-2 py-1.5 border border-gray-300 rounded-lg text-xs bg-white focus:outline-none focus:ring-1 focus:ring-emerald-700 font-medium cursor-pointer"
                            >
                                <option :value="10">10 data</option>
                                <option :value="25">25 data</option>
                                <option :value="50">50 data</option>
                            </select>
                        </div>
                    </div>

                    <!-- Kotak Pencarian (DataTables Filter) -->
                    <div class="relative w-full md:w-80">
                        <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-gray-400">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input
                            type="text"
                            v-model="search"
                            @input="handleSearch"
                            placeholder="Cari perusahaan, kota, atau nama alumni..."
                            class="w-full pl-8 pr-3 py-1.5 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-emerald-700"
                        />
                    </div>
                </div>

                <!-- Info Jumlah Data & Tombol Reset -->
                <div class="flex items-center justify-between text-xs text-gray-500 px-1">
                    <span>
                        Menampilkan <strong>{{ companies.data?.length || 0 }}</strong> dari total <strong>{{ companies.total || 0 }}</strong> perusahaan menunggu verifikasi
                    </span>
                    <button
                        v-if="search || prodiId || perPage != 10"
                        @click="resetFilters"
                        class="text-emerald-800 font-semibold hover:underline cursor-pointer"
                    >
                        Reset Filter
                    </button>
                </div>

                <!-- Tabel Data Pengajuan Perusahaan (Excel / DataTables Grid Style) -->
                <div class="bg-white border border-gray-300 overflow-x-auto">
                    <table class="w-full text-left border-collapse border border-gray-300 text-xs bg-white">
                        <thead>
                            <tr class="bg-gray-100 text-gray-800 font-semibold border-b border-gray-300 text-[11px]">
                                <th class="border border-gray-300 px-2.5 py-2 text-center w-10">No</th>
                                <th class="border border-gray-300 px-3 py-2 w-48">Nama Perusahaan</th>
                                <th class="border border-gray-300 px-3 py-2 min-w-[220px]">Lokasi Lengkap</th>
                                <th class="border border-gray-300 px-3 py-2 text-center w-32">Status</th>
                                <th class="border border-gray-300 px-3 py-2 w-44">User</th>
                                <th class="border border-gray-300 px-3 py-2 min-w-[200px]">Rekomendasi</th>
                                <th class="border border-gray-300 px-2.5 py-2 text-center w-28">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(company, index) in companies.data"
                                :key="company.id"
                                class="hover:bg-gray-50 transition-colors align-top"
                            >
                                <!-- No -->
                                <td class="border border-gray-300 px-2.5 py-2 text-center text-gray-600 font-medium">
                                    {{ ((companies.current_page - 1) * companies.per_page) + index + 1 }}
                                </td>

                                <!-- Nama Perusahaan -->
                                <td class="border border-gray-300 px-3 py-2 font-bold text-gray-900">
                                    {{ company.nama_perusahaan }}
                                </td>

                                <!-- Lokasi Lengkap (Negara, Prov, Kab, Alamat, Kode Pos) -->
                                <td class="border border-gray-300 px-3 py-2 text-gray-700">
                                    <div v-if="company.alamat" class="font-medium text-gray-900 mb-0.5">
                                        {{ company.alamat }}
                                    </div>
                                    <div class="text-gray-600 text-[11px]">
                                        {{ company.kabupaten?.nama_kabupaten || '-' }}, {{ company.propinsi?.nama_provinsi || '-' }}
                                        <span v-if="company.kode_pos">({{ company.kode_pos }})</span>
                                    </div>
                                    <div class="text-gray-500 text-[10px]">
                                        {{ company.negara || 'Indonesia' }}
                                    </div>
                                </td>

                                <!-- Status -->
                                <td class="border border-gray-300 px-3 py-2 text-center">
                                    <span class="text-amber-800 font-medium text-[11px]">
                                        {{ company.status_verifikasi || 'Menunggu Verifikasi' }}
                                    </span>
                                </td>

                                <!-- User (Alumni Pengaju) -->
                                <td class="border border-gray-300 px-3 py-2 text-gray-700">
                                    <div v-if="company.biodata && company.biodata.length > 0">
                                        <div class="font-bold text-gray-900 leading-tight">{{ company.biodata[0].nama }}</div>
                                        <div class="text-gray-500 text-[11px] font-mono mt-0.5">{{ company.biodata[0].nim }} • {{ company.biodata[0].prodi?.nama_prodi || '-' }}</div>
                                    </div>
                                    <div v-else-if="company.creator">
                                        <div class="font-bold text-gray-900 leading-tight">{{ company.creator.biodata?.nama || company.creator.name }}</div>
                                        <div class="text-gray-500 text-[11px] font-mono mt-0.5">{{ company.creator.biodata?.nim || company.creator.username }} • {{ company.created_prodi?.nama_prodi || company.creator.biodata?.prodi?.nama_prodi || '-' }}</div>
                                    </div>
                                    <span v-else class="text-gray-400 italic text-[11px]">-</span>
                                </td>

                                <!-- Rekomendasi -->
                                <td class="border border-gray-300 px-3 py-2">
                                    <div v-if="company.recommendations && company.recommendations.length > 0">
                                        <div
                                            v-for="rec in company.recommendations"
                                            :key="rec.id"
                                            class="flex items-center justify-between gap-1.5 py-1 border-b border-gray-200 last:border-b-0"
                                        >
                                            <span class="text-gray-900 font-medium truncate" :title="rec.nama_perusahaan">{{ rec.nama_perusahaan }}</span>
                                            <div class="flex items-center gap-1 shrink-0">
                                                <!-- Icon Info (i) -->
                                                <button
                                                    type="button"
                                                    @click="openRecDetail(company, rec)"
                                                    class="w-5 h-5 rounded hover:bg-gray-200 text-blue-600 flex items-center justify-center font-serif text-[11px] font-bold cursor-pointer"
                                                    title="Lihat detail rekomendasi"
                                                >
                                                    i
                                                </button>

                                                <!-- Icon Switch / Swap (Auto Replace) -->
                                                <button
                                                    type="button"
                                                    @click="handleAutoReplace(company, rec)"
                                                    class="w-5 h-5 rounded hover:bg-gray-200 text-[#0D542B] flex items-center justify-center cursor-pointer"
                                                    title="Gantikan (Auto Replace)"
                                                >
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <span v-else class="text-gray-400 italic text-[11px]">-</span>
                                </td>

                                <!-- Aksi (Icon-only Toolbar) -->
                                <td class="border border-gray-300 px-2.5 py-2 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <!-- Detail Mata -->
                                        <button
                                            type="button"
                                            @click="openReviewModal(company)"
                                            class="p-1 text-blue-600 hover:bg-blue-50 rounded cursor-pointer transition-colors"
                                            title="Tinjau Detail"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>

                                        <!-- Verifikasi Langsung -->
                                        <button
                                            v-if="company.status_verifikasi !== 'Terverifikasi'"
                                            type="button"
                                            @click="handleVerifyDirect(company)"
                                            class="p-1 text-[#0D542B] hover:bg-emerald-50 rounded cursor-pointer transition-colors"
                                            title="Verifikasi"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </button>

                                        <!-- Edit Data -->
                                        <button
                                            type="button"
                                            @click="openEditModal(company)"
                                            class="p-1 text-amber-600 hover:bg-amber-50 rounded cursor-pointer transition-colors"
                                            :title="company.status_verifikasi === 'Terverifikasi' ? 'Edit Data Perusahaan' : 'Edit & Verifikasi'"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>

                                        <!-- Delete / Hapus -->
                                        <button
                                            type="button"
                                            @click="handleReject(company)"
                                            class="p-1 text-red-600 hover:bg-red-50 rounded cursor-pointer transition-colors"
                                            :title="company.status_verifikasi === 'Terverifikasi' ? 'Hapus / Batalkan Verifikasi' : 'Hapus / Tolak'"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Empty State -->
                            <tr v-if="!companies.data || companies.data.length === 0">
                                <td colspan="7" class="py-8 text-center text-gray-500 border border-gray-300">
                                    Tidak ada pengajuan perusahaan yang menunggu verifikasi.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="companies.links && companies.links.length > 3" class="flex items-center justify-between text-xs text-gray-600 pt-2">
                    <span>Halaman {{ companies.current_page }} dari {{ companies.last_page }}</span>
                    <div class="flex items-center gap-1">
                        <Link
                            v-for="(link, i) in companies.links"
                            :key="i"
                            :href="link.url || '#'"
                            class="px-2.5 py-1.5 rounded-lg border text-xs font-semibold transition-colors"
                            :class="[
                                link.active ? 'bg-[#0D542B] text-white border-[#0D542B]' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50',
                                !link.url ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer'
                            ]"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL 1: DETAIL MASTER PERUSAHAAN REKOMENDASI (VUE MODAL) -->
    <!-- ======================================================== -->
    <Teleport to="body">
        <div
            v-if="showRecDetailModal && activeRecDetail"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 overflow-y-auto"
            role="dialog"
            aria-modal="true"
        >
            <div class="fixed inset-0 bg-slate-900/60 transition-opacity" @click="closeRecDetailModal"></div>

            <div class="relative bg-white rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden border border-slate-200 z-10 animate-in fade-in zoom-in-95 duration-150">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/70">
                    <h3 class="text-base font-bold text-slate-800">Detail Master Perusahaan</h3>
                    <button type="button" @click="closeRecDetailModal" class="text-slate-400 hover:text-slate-600 rounded-lg p-1.5 transition-colors cursor-pointer">
                        <span class="sr-only">Tutup</span>
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-6 space-y-4 text-xs text-slate-700">
                    <div class="border border-slate-200 rounded-xl p-4 bg-slate-50/50 space-y-2">
                        <div>
                            <span class="text-slate-400 text-[11px] font-semibold block">Nama Perusahaan Resmi:</span>
                            <span class="font-bold text-sm text-[#0D542B]">{{ activeRecDetail.nama_perusahaan }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 text-[11px] font-semibold block">Alamat Lengkap:</span>
                            <span class="font-medium text-slate-800">{{ activeRecDetail.alamat || '-' }}</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-200 text-[11px]">
                            <div><span class="text-slate-400">Provinsi:</span> <strong>{{ activeRecDetail.nama_provinsi || '-' }}</strong></div>
                            <div><span class="text-slate-400">Kabupaten/Kota:</span> <strong>{{ activeRecDetail.nama_kabupaten || '-' }}</strong></div>
                            <div><span class="text-slate-400">Kode Pos:</span> <strong>{{ activeRecDetail.kode_pos || '-' }}</strong></div>
                            <div><span class="text-slate-400">Negara:</span> <strong>{{ activeRecDetail.negara || 'Indonesia' }}</strong></div>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-3.5 border-t border-slate-100 flex items-center justify-end gap-2 bg-slate-50/50">
                    <button type="button" @click="closeRecDetailModal" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition-colors cursor-pointer">
                        Tutup
                    </button>
                    <button type="button" @click="confirmReplaceFromDetail" class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-[#0D542B] hover:bg-[#08381c] transition-all shadow-xs cursor-pointer">
                        Gantikan ke Master Ini
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- ======================================================== -->
    <!-- MODAL 2: PENINJAUAN PENGAJUAN PERUSAHAAN (VUE MODAL)     -->
    <!-- ======================================================== -->
    <Teleport to="body">
        <div
            v-if="showReviewModal && activeCompanyForReview"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 overflow-y-auto"
            role="dialog"
            aria-modal="true"
        >
            <div class="fixed inset-0 bg-slate-900/60 transition-opacity" @click="closeReviewModal"></div>

            <div class="relative bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] flex flex-col overflow-hidden border border-slate-200 z-10 animate-in fade-in zoom-in-95 duration-150">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/70">
                    <div>
                        <h3 class="text-base font-bold text-slate-800">Peninjauan Pengajuan Perusahaan</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Tinjau data inputan alumni dan rekomendasi data master yang mirip</p>
                    </div>
                    <button type="button" @click="closeReviewModal" class="text-slate-400 hover:text-slate-600 rounded-lg p-1.5 transition-colors cursor-pointer">
                        <span class="sr-only">Tutup</span>
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-6 space-y-4 overflow-y-auto flex-1 text-xs text-slate-800">
                    <!-- 1. Data Inputan Alumni -->
                    <div class="border border-slate-200 rounded-xl p-4 bg-slate-50/60 space-y-2">
                        <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                            <span class="font-bold text-slate-700 uppercase text-[10px] tracking-wider">1. Data Yang Diinputkan Alumni</span>
                            <span class="text-amber-800 font-semibold text-[11px] bg-amber-50 px-2 py-0.5 rounded border border-amber-200">
                                {{ activeCompanyForReview.status_verifikasi || 'Menunggu Verifikasi' }}
                            </span>
                        </div>
                        <div>
                            <span class="text-slate-400 text-[11px] block">Nama Perusahaan:</span>
                            <span class="font-bold text-slate-900 block text-sm">{{ activeCompanyForReview.nama_perusahaan }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 text-[11px] block">Alamat Lengkap:</span>
                            <span class="text-slate-800 font-medium block">{{ activeCompanyForReview.alamat || '-' }} (Kode Pos: {{ activeCompanyForReview.kode_pos || '-' }})</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-[11px] pt-1.5 border-t border-slate-200">
                            <div><span class="text-slate-400">Provinsi:</span> <strong>{{ activeCompanyForReview.propinsi?.nama_provinsi || '-' }}</strong></div>
                            <div><span class="text-slate-400">Kabupaten/Kota:</span> <strong>{{ activeCompanyForReview.kabupaten?.nama_kabupaten || '-' }}</strong></div>
                            <div><span class="text-slate-400">Skala Instansi:</span> <strong>{{ activeCompanyForReview.skala || '-' }}</strong></div>
                            <div><span class="text-slate-400">Jenis Usaha:</span> <strong>{{ activeCompanyForReview.jenis_perusahaan || '-' }}</strong></div>
                        </div>
                        <div class="pt-2 border-t border-slate-200">
                            <span class="text-slate-400 font-semibold block mb-1">Alumni Terkait:</span>
                            <div v-if="activeCompanyForReview.biodata && activeCompanyForReview.biodata.length > 0" class="space-y-1.5">
                                <div v-for="b in activeCompanyForReview.biodata" :key="b.id || b.nim" class="py-1 border-b border-slate-100 last:border-b-0">
                                    <span class="font-bold text-slate-900">{{ b.nama }}</span> <span class="text-slate-500 font-mono">({{ b.nim }})</span><br>
                                    <span class="text-slate-600 text-[11px]">{{ b.prodi?.nama_prodi || '-' }} {{ b.posisi_jabatan ? '• Jabatan: ' + b.posisi_jabatan : '' }}</span>
                                </div>
                            </div>
                            <div v-else-if="activeCompanyForReview.creator" class="py-1">
                                <span class="font-bold text-slate-900">{{ activeCompanyForReview.creator.biodata?.nama || activeCompanyForReview.creator.name }}</span>
                                <span class="text-slate-500 font-mono"> ({{ activeCompanyForReview.creator.biodata?.nim || activeCompanyForReview.creator.username }})</span><br>
                                <span class="text-slate-600 text-[11px]">{{ activeCompanyForReview.created_prodi?.nama_prodi || activeCompanyForReview.creator.biodata?.prodi?.nama_prodi || '-' }} (Pengaju Baru)</span>
                            </div>
                            <span v-else class="text-slate-400 italic">Belum ada data alumni</span>
                        </div>
                    </div>

                    <!-- 2. Rekomendasi Master Mirip -->
                    <div>
                        <span class="font-bold text-slate-700 uppercase text-[10px] tracking-wider block mb-1.5">2. Rekomendasi Master Terverifikasi (Auto Replace)</span>
                        <div v-if="activeCompanyForReview.recommendations && activeCompanyForReview.recommendations.length > 0" class="border border-slate-200 rounded-xl overflow-hidden">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="bg-slate-100 text-slate-700 border-b border-slate-200">
                                        <th class="px-3 py-2 font-semibold">Master Perusahaan</th>
                                        <th class="px-3 py-2 font-semibold">Lokasi Master</th>
                                        <th class="px-2 py-2 text-center font-semibold w-24">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="r in activeCompanyForReview.recommendations" :key="r.id" class="border-b border-slate-100 last:border-b-0">
                                        <td class="px-3 py-2 font-bold text-slate-900">{{ r.nama_perusahaan }}</td>
                                        <td class="px-3 py-2 text-slate-600">{{ r.nama_kabupaten || '-' }}, {{ r.nama_provinsi || '-' }}</td>
                                        <td class="px-2 py-2 text-center">
                                            <button
                                                type="button"
                                                @click="handleAutoReplace(activeCompanyForReview, r)"
                                                class="px-2.5 py-1 bg-[#0D542B] text-white font-semibold rounded-lg text-[11px] hover:bg-[#08381c] cursor-pointer transition-colors shadow-2xs"
                                            >
                                                Pilih (⇄)
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p v-else class="text-slate-400 italic text-xs py-2 bg-slate-50 border border-slate-200 rounded-xl px-3">
                            Tidak ditemukan nama master terverifikasi yang mirip.
                        </p>
                    </div>
                </div>

                <div class="px-6 py-3.5 border-t border-slate-100 flex items-center justify-between bg-slate-50/70">
                    <button type="button" @click="closeReviewModal" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-200 transition-colors cursor-pointer">
                        Tutup
                    </button>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="openEditModal(activeCompanyForReview)"
                            class="px-4 py-2 rounded-xl text-xs font-bold text-amber-800 bg-amber-100 hover:bg-amber-200 transition-colors cursor-pointer"
                        >
                            Sunting Data
                        </button>
                        <button
                            type="button"
                            @click="handleVerifyDirect(activeCompanyForReview)"
                            class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-[#0D542B] hover:bg-[#08381c] transition-all shadow-xs cursor-pointer"
                        >
                            Verifikasi Sesuai Inputan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- ======================================================== -->
    <!-- MODAL 3: SUNTING & VERIFIKASI PERUSAHAAN (VUE MODAL)     -->
    <!-- ======================================================== -->
    <Teleport to="body">
        <div
            v-if="showEditModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 overflow-y-auto"
            role="dialog"
            aria-modal="true"
        >
            <div class="fixed inset-0 bg-slate-900/60 transition-opacity" @click="closeEditModal"></div>

            <div class="relative bg-white rounded-2xl shadow-2xl max-w-lg w-full max-h-[90vh] flex flex-col overflow-hidden border border-slate-200 z-10 animate-in fade-in zoom-in-95 duration-150">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/70">
                    <div>
                        <h3 class="text-base font-bold text-slate-800">Sunting & Verifikasi Perusahaan</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Perbarui data instansi sebelum diverifikasi ke master resmi</p>
                    </div>
                    <button type="button" @click="closeEditModal" :disabled="isSubmittingEdit" class="text-slate-400 hover:text-slate-600 rounded-lg p-1.5 transition-colors cursor-pointer disabled:opacity-50">
                        <span class="sr-only">Tutup</span>
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitEditForm" class="p-6 space-y-4 overflow-y-auto flex-1 text-xs">
                    <div v-if="editModalError" class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-700 flex items-start gap-2">
                        <svg class="w-4 h-4 text-rose-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{{ editModalError }}</span>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Nama Perusahaan / Instansi *</label>
                        <input
                            type="text"
                            v-model="editForm.nama_perusahaan"
                            class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                            placeholder="Contoh: PT Bank Central Asia Tbk"
                            required
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Provinsi</label>
                            <select
                                v-model="editForm.propinsi_id"
                                class="w-full px-2.5 py-2 border border-slate-300 rounded-xl text-xs bg-white focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                            >
                                <option value="">-- Pilih Provinsi --</option>
                                <option v-for="p in props.propinsis || []" :key="p.id" :value="p.id">
                                    {{ p.nama_provinsi }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Kabupaten/Kota</label>
                            <select
                                v-model="editForm.kabupaten_id"
                                :disabled="!editForm.propinsi_id"
                                class="w-full px-2.5 py-2 border border-slate-300 rounded-xl text-xs bg-white focus:outline-none focus:ring-1 focus:ring-[#0D542B] disabled:bg-slate-100 disabled:text-slate-400"
                            >
                                <option value="">-- Pilih Kabupaten/Kota --</option>
                                <option v-for="k in filteredKabupatensForEdit" :key="k.id" :value="k.id">
                                    {{ k.nama_kabupaten }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Alamat Lengkap</label>
                        <textarea
                            v-model="editForm.alamat"
                            rows="2"
                            class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs focus:outline-none focus:ring-1 focus:ring-[#0D542B] resize-y"
                            placeholder="Alamat jalan, gedung, dsb..."
                        ></textarea>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Kode Pos</label>
                            <input
                                type="text"
                                v-model="editForm.kode_pos"
                                class="w-full px-2.5 py-2 border border-slate-300 rounded-xl text-xs focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                                placeholder="55224"
                            />
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Skala Usaha</label>
                            <select
                                v-model="editForm.skala"
                                class="w-full px-2 py-2 border border-slate-300 rounded-xl text-xs bg-white focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                            >
                                <option value="Nasional">Nasional</option>
                                <option value="Multinasional/Internasional">Multinasional</option>
                                <option value="Regional/Lokal">Regional/Lokal</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Jenis Usaha</label>
                            <select
                                v-model="editForm.jenis_perusahaan"
                                class="w-full px-2 py-2 border border-slate-300 rounded-xl text-xs bg-white focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                            >
                                <option value="Perusahaan swasta">Swasta</option>
                                <option value="BUMN/BUMD">BUMN/BUMD</option>
                                <option value="Instansi pemerintah">Pemerintah</option>
                                <option value="Organisasi non-profit/LSM">Non-profit</option>
                                <option value="Wiraswasta/Perusahaan sendiri">Wiraswasta</option>
                            </select>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            @click="closeEditModal"
                            :disabled="isSubmittingEdit"
                            class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition-colors cursor-pointer disabled:opacity-50"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="isSubmittingEdit"
                            class="px-5 py-2 rounded-xl text-xs font-bold text-white bg-[#0D542B] hover:bg-[#08381c] transition-all shadow-xs shrink-0 cursor-pointer disabled:opacity-50 flex items-center gap-1.5"
                        >
                            <svg v-if="isSubmittingEdit" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>{{ isSubmittingEdit ? 'Menyimpan...' : 'Simpan & Verifikasi' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
