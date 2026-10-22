<!--
  Halaman: Master Data Wilayah & Upah Minimum Provinsi (UMP)
  File: resources/js/Pages/SuperAdmin/MasterWilayah/Index.vue
  Hak Akses: Super Administrator
-->
<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';
import Sidebar from '../Components/Sidebar.vue';

const props = defineProps({
    user: Object,
    active_tab: {
        type: String,
        default: 'provinsi',
    },
    stats: {
        type: Object,
        default: () => ({
            total_provinsi: 0,
            total_kabupaten: 0,
            total_ump_configured: 0,
            avg_ump: 0,
            min_ump: { besaran: 0, provinsi: '-' },
            max_ump: { besaran: 0, provinsi: '-' },
            selected_year: 2026,
        }),
    },
    available_years: {
        type: Array,
        default: () => [2026],
    },
    all_provinces: {
        type: Array,
        default: () => [],
    },
    provinces: {
        type: Array,
        default: () => [],
    },
    kabupatens: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
    umps: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();

// State Navigasi Tab & Filter
const currentTab = ref(props.active_tab || 'provinsi');
const search = ref(props.filters.search || '');
const selectedYear = ref(props.filters.tahun || 2026);
const selectedProvinsiId = ref(props.filters.propinsi_id || '');
const perPage = ref(props.filters.per_page || 15);
const filterStatusUmp = ref('all'); // 'all', 'configured', 'unconfigured'

// Format Rupiah Helper
const formatRupiah = (val) => {
    if (val === null || val === undefined || isNaN(val)) return 'Rp 0';
    return 'Rp ' + Number(val).toLocaleString('id-ID');
};

// ==========================================
// STATE MODAL PROVINSI (MURNI CRUD)
// ==========================================
const showProvinsiModal = ref(false);
const isEditingProvinsi = ref(false);
const formProvinsi = ref({
    id: null,
    kode_provinsi: '',
    nama_provinsi: '',
});

// ==========================================
// STATE MODAL KABUPATEN (MURNI CRUD)
// ==========================================
const showKabupatenModal = ref(false);
const isEditingKabupaten = ref(false);
const formKabupaten = ref({
    id: null,
    propinsi_id: '',
    kode_kabupaten: '',
    nama_kabupaten: '',
});

// ==========================================
// STATE MODAL UMP (PER PROVINSI)
// ==========================================
const showUmpModal = ref(false);
const isEditingUmp = ref(false);
const formUmp = ref({
    id: null,
    kode_provinsi: '',
    tahun: 2026,
    besaran: '',
    catatan: '',
});

// ==========================================
// STATE MODAL SALIN UMP ANTAR TAHUN
// ==========================================
const showCopyYearModal = ref(false);
const formCopyYear = ref({
    source_year: 2026,
    target_year: 2027,
    percentage_increase: 0,
});

// Loading state
const isSubmitting = ref(false);

// Filter Handler
let searchDebounce = null;
const handleSearch = () => {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => {
        applyFilters();
    }, 400);
};

const switchTab = (tab) => {
    currentTab.value = tab;
    search.value = '';
    applyFilters();
};

const applyFilters = () => {
    router.get('/superadmin/master-wilayah', {
        tab: currentTab.value,
        search: search.value || undefined,
        tahun: selectedYear.value != 2026 ? selectedYear.value : undefined,
        propinsi_id: currentTab.value === 'kabupaten' && selectedProvinsiId.value ? selectedProvinsiId.value : undefined,
        per_page: currentTab.value === 'kabupaten' && perPage.value != 15 ? perPage.value : undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    search.value = '';
    selectedProvinsiId.value = '';
    selectedYear.value = 2026;
    perPage.value = 15;
    filterStatusUmp.value = 'all';
    router.get('/superadmin/master-wilayah', { tab: currentTab.value }, { preserveState: true });
};

// Filtered UMP pada sisi klien berdasarkan status_ump
const filteredUmps = computed(() => {
    if (!props.umps || !Array.isArray(props.umps)) return [];
    if (filterStatusUmp.value === 'configured') {
        return props.umps.filter(item => item.has_ump);
    }
    if (filterStatusUmp.value === 'unconfigured') {
        return props.umps.filter(item => !item.has_ump);
    }
    return props.umps;
});

const totalUmpConfiguredCount = computed(() => {
    if (!props.umps || !Array.isArray(props.umps)) return 0;
    return props.umps.filter(item => item.has_ump).length;
});

const totalUmpUnconfiguredCount = computed(() => {
    if (!props.umps || !Array.isArray(props.umps)) return 0;
    return props.umps.filter(item => !item.has_ump).length;
});

// ==========================================
// ACTIONS PROVINSI (CRUD ONLY)
// ==========================================
const openCreateProvinsi = () => {
    isEditingProvinsi.value = false;
    formProvinsi.value = {
        id: null,
        kode_provinsi: '',
        nama_provinsi: '',
    };
    showProvinsiModal.value = true;
};

const openEditProvinsi = (prov) => {
    isEditingProvinsi.value = true;
    formProvinsi.value = {
        id: prov.id,
        kode_provinsi: prov.kode_provinsi,
        nama_provinsi: prov.nama_provinsi,
    };
    showProvinsiModal.value = true;
};

const submitProvinsi = () => {
    if (!formProvinsi.value.kode_provinsi || !formProvinsi.value.nama_provinsi) {
        Swal.fire({
            icon: 'warning',
            title: 'Form Belum Lengkap',
            text: 'Kode Provinsi dan Nama Provinsi wajib diisi.',
            confirmButtonColor: '#0D542B',
        });
        return;
    }

    isSubmitting.value = true;
    if (isEditingProvinsi.value) {
        router.put(`/superadmin/master-wilayah/provinsi/${formProvinsi.value.id}`, {
            kode_provinsi: formProvinsi.value.kode_provinsi,
            nama_provinsi: formProvinsi.value.nama_provinsi,
        }, {
            onSuccess: () => {
                showProvinsiModal.value = false;
                isSubmitting.value = false;
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: 'Data provinsi berhasil diperbarui.',
                    timer: 1800,
                    showConfirmButton: false,
                });
            },
            onError: (err) => {
                isSubmitting.value = false;
                const msg = Object.values(err)[0] || 'Gagal memperbarui data provinsi.';
                Swal.fire({ icon: 'error', title: 'Terjadi Kesalahan', text: msg, confirmButtonColor: '#0D542B' });
            },
        });
    } else {
        router.post('/superadmin/master-wilayah/provinsi', {
            kode_provinsi: formProvinsi.value.kode_provinsi,
            nama_provinsi: formProvinsi.value.nama_provinsi,
        }, {
            onSuccess: () => {
                showProvinsiModal.value = false;
                isSubmitting.value = false;
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: 'Provinsi baru berhasil ditambahkan.',
                    timer: 1800,
                    showConfirmButton: false,
                });
            },
            onError: (err) => {
                isSubmitting.value = false;
                const msg = Object.values(err)[0] || 'Gagal menambahkan provinsi baru.';
                Swal.fire({ icon: 'error', title: 'Terjadi Kesalahan', text: msg, confirmButtonColor: '#0D542B' });
            },
        });
    }
};

const confirmDeleteProvinsi = (prov) => {
    Swal.fire({
        title: 'Hapus Provinsi?',
        html: `Apakah Anda yakin ingin menghapus data provinsi <b>${prov.nama_provinsi}</b> (Kode: <code>${prov.kode_provinsi}</code>)?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#DC2626',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(`/superadmin/master-wilayah/provinsi/${prov.id}`, {
                onSuccess: () => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Terhapus',
                        text: 'Provinsi berhasil dihapus dari sistem.',
                        timer: 1800,
                        showConfirmButton: false,
                    });
                },
                onError: (err) => {
                    const msg = err.error || Object.values(err)[0] || 'Gagal menghapus provinsi.';
                    Swal.fire({ icon: 'error', title: 'Tidak Dapat Dihapus', text: msg, confirmButtonColor: '#0D542B' });
                },
            });
        }
    });
};

// ==========================================
// ACTIONS KABUPATEN (CRUD ONLY)
// ==========================================
const openCreateKabupaten = () => {
    isEditingKabupaten.value = false;
    formKabupaten.value = {
        id: null,
        propinsi_id: selectedProvinsiId.value || (props.all_provinces[0]?.id || ''),
        kode_kabupaten: '',
        nama_kabupaten: '',
    };
    showKabupatenModal.value = true;
};

const openEditKabupaten = (kab) => {
    isEditingKabupaten.value = true;
    formKabupaten.value = {
        id: kab.id,
        propinsi_id: kab.propinsi_id,
        kode_kabupaten: kab.kode_kabupaten,
        nama_kabupaten: kab.nama_kabupaten,
    };
    showKabupatenModal.value = true;
};

const submitKabupaten = () => {
    if (!formKabupaten.value.propinsi_id || !formKabupaten.value.kode_kabupaten || !formKabupaten.value.nama_kabupaten) {
        Swal.fire({
            icon: 'warning',
            title: 'Form Belum Lengkap',
            text: 'Provinsi Induk, Kode Kabupaten, dan Nama Kabupaten wajib diisi.',
            confirmButtonColor: '#0D542B',
        });
        return;
    }

    isSubmitting.value = true;
    if (isEditingKabupaten.value) {
        router.put(`/superadmin/master-wilayah/kabupaten/${formKabupaten.value.id}`, formKabupaten.value, {
            onSuccess: () => {
                showKabupatenModal.value = false;
                isSubmitting.value = false;
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: 'Data kabupaten/kota berhasil diperbarui.',
                    timer: 1800,
                    showConfirmButton: false,
                });
            },
            onError: (err) => {
                isSubmitting.value = false;
                const msg = Object.values(err)[0] || 'Gagal memperbarui kabupaten/kota.';
                Swal.fire({ icon: 'error', title: 'Terjadi Kesalahan', text: msg, confirmButtonColor: '#0D542B' });
            },
        });
    } else {
        router.post('/superadmin/master-wilayah/kabupaten', formKabupaten.value, {
            onSuccess: () => {
                showKabupatenModal.value = false;
                isSubmitting.value = false;
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: 'Kabupaten/Kota baru berhasil ditambahkan.',
                    timer: 1800,
                    showConfirmButton: false,
                });
            },
            onError: (err) => {
                isSubmitting.value = false;
                const msg = Object.values(err)[0] || 'Gagal menambahkan kabupaten/kota.';
                Swal.fire({ icon: 'error', title: 'Terjadi Kesalahan', text: msg, confirmButtonColor: '#0D542B' });
            },
        });
    }
};

const confirmDeleteKabupaten = (kab) => {
    Swal.fire({
        title: 'Hapus Kabupaten/Kota?',
        html: `Apakah Anda yakin ingin menghapus data <b>${kab.nama_kabupaten}</b> (Kode: <code>${kab.kode_kabupaten}</code>)?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#DC2626',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(`/superadmin/master-wilayah/kabupaten/${kab.id}`, {
                onSuccess: () => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Terhapus',
                        text: 'Kabupaten/Kota berhasil dihapus.',
                        timer: 1800,
                        showConfirmButton: false,
                    });
                },
                onError: (err) => {
                    const msg = err.error || Object.values(err)[0] || 'Gagal menghapus kabupaten/kota.';
                    Swal.fire({ icon: 'error', title: 'Tidak Dapat Dihapus', text: msg, confirmButtonColor: '#0D542B' });
                },
            });
        }
    });
};

// ==========================================
// ACTIONS UMP (DITETAPKAN PER PROVINSI)
// ==========================================
const openCreateUmp = () => {
    isEditingUmp.value = false;
    formUmp.value = {
        id: null,
        kode_provinsi: props.all_provinces[0]?.kode_provinsi || '',
        tahun: selectedYear.value,
        besaran: '',
        catatan: '',
    };
    showUmpModal.value = true;
};

const openConfigureUmp = (item) => {
    isEditingUmp.value = true;
    formUmp.value = {
        id: item.id || null,
        kode_provinsi: item.kode_provinsi,
        tahun: item.tahun || selectedYear.value,
        besaran: item.besaran || '',
        catatan: item.catatan || '',
    };
    showUmpModal.value = true;
};

const submitUmp = () => {
    if (!formUmp.value.kode_provinsi || !formUmp.value.besaran || !formUmp.value.tahun) {
        Swal.fire({
            icon: 'warning',
            title: 'Form Belum Lengkap',
            text: 'Provinsi, Tahun, dan Besaran UMP wajib diisi.',
            confirmButtonColor: '#0D542B',
        });
        return;
    }

    isSubmitting.value = true;
    router.post('/superadmin/master-wilayah/ump', {
        kode_provinsi: formUmp.value.kode_provinsi,
        tahun: formUmp.value.tahun,
        besaran: formUmp.value.besaran,
        catatan: formUmp.value.catatan,
    }, {
        onSuccess: () => {
            showUmpModal.value = false;
            isSubmitting.value = false;
            Swal.fire({
                icon: 'success',
                title: 'Berhasil Disimpan',
                text: 'Ketetapan UMP berhasil disimpan ke sistem.',
                timer: 1800,
                showConfirmButton: false,
            });
        },
        onError: (err) => {
            isSubmitting.value = false;
            const msg = Object.values(err)[0] || 'Gagal menyimpan ketetapan UMP.';
            Swal.fire({ icon: 'error', title: 'Terjadi Kesalahan', text: msg, confirmButtonColor: '#0D542B' });
        },
    });
};

const confirmDeleteUmp = (umpItem) => {
    if (!umpItem.id) return;
    Swal.fire({
        title: 'Hapus Ketetapan UMP?',
        html: `Apakah Anda yakin ingin menghapus ketetapan UMP <b>${umpItem.nama_provinsi}</b> tahun <b>${umpItem.tahun}</b>?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#DC2626',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(`/superadmin/master-wilayah/ump/${umpItem.id}`, {
                onSuccess: () => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Terhapus',
                        text: 'Ketetapan UMP berhasil dihapus.',
                        timer: 1800,
                        showConfirmButton: false,
                    });
                },
                onError: (err) => {
                    const msg = Object.values(err)[0] || 'Gagal menghapus ketetapan UMP.';
                    Swal.fire({ icon: 'error', title: 'Kesalahan', text: msg, confirmButtonColor: '#0D542B' });
                },
            });
        }
    });
};

// Salin Antar Tahun
const openCopyYearModal = () => {
    formCopyYear.value = {
        source_year: selectedYear.value,
        target_year: Number(selectedYear.value) + 1,
        percentage_increase: 0,
    };
    showCopyYearModal.value = true;
};

const submitCopyYear = () => {
    if (formCopyYear.value.source_year === formCopyYear.value.target_year) {
        Swal.fire({
            icon: 'warning',
            title: 'Tahun Sama',
            text: 'Tahun target harus berbeda dengan tahun sumber.',
            confirmButtonColor: '#0D542B',
        });
        return;
    }

    isSubmitting.value = true;
    router.post('/superadmin/master-wilayah/ump/copy-year', formCopyYear.value, {
        onSuccess: () => {
            showCopyYearModal.value = false;
            isSubmitting.value = false;
            selectedYear.value = formCopyYear.value.target_year;
            Swal.fire({
                icon: 'success',
                title: 'Berhasil Disalin',
                text: `Ketetapan UMP tahun ${formCopyYear.value.target_year} berhasil digenerate.`,
                timer: 2000,
                showConfirmButton: false,
            });
        },
        onError: (err) => {
            isSubmitting.value = false;
            const msg = Object.values(err)[0] || 'Gagal menyalin data UMP.';
            Swal.fire({ icon: 'error', title: 'Terjadi Kesalahan', text: msg, confirmButtonColor: '#0D542B' });
        },
    });
};
</script>

<template>
    <div class="min-h-screen bg-slate-50 flex flex-col font-sans text-slate-800">
        <Head title="Master Wilayah & UMP - Super Admin" />

        <!-- SIDEBAR TERPADU SUPERADMIN -->
        <Sidebar :user="user" />

        <!-- KONTEN UTAMA HALAMAN -->
        <!-- KONTEN UTAMA HALAMAN -->
        <div class="flex-1 lg:pl-72 flex flex-col min-w-0 min-h-screen">
            <!-- Header Solid UKDW Green Konsisten -->
            <div class="admin-page-header bg-[#0D542B] text-white pt-8 pb-16 px-4 sm:px-6 lg:px-8 border-b border-emerald-900/40">
                <div class="w-full max-w-[1440px] mx-auto flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2 text-xs text-white/70 font-medium mb-1.5">
                            <Link href="/superadmin/dashboard" class="hover:text-white transition-colors">Dashboard</Link>
                            <span>/</span>
                            <span class="text-white font-semibold">Master Wilayah &amp; UMP</span>
                        </div>
                        <h1 class="text-2xl font-bold tracking-tight text-white">
                            Master Wilayah &amp; Standar UMP
                        </h1>
                        <p class="text-xs text-emerald-100/90 mt-1 max-w-2xl leading-relaxed">
                            Pengelolaan hierarki wilayah nasional dan regulasi Upah Minimum Provinsi (UMP) per tahun kelulusan tracer study.
                        </p>
                    </div>

                    <!-- TOMBOL AKSI CEPAT DI HEADER -->
                    <div class="flex items-center gap-2 flex-wrap">
                        <button
                            v-if="currentTab === 'provinsi'"
                            type="button"
                            @click="openCreateProvinsi"
                            class="px-4 py-2 bg-white text-[#0D542B] hover:bg-emerald-50 font-bold text-xs rounded-lg transition-colors flex items-center gap-2 cursor-pointer shadow-sm"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            <span>Tambah Provinsi</span>
                        </button>

                        <button
                            v-if="currentTab === 'kabupaten'"
                            type="button"
                            @click="openCreateKabupaten"
                            class="px-4 py-2 bg-white text-[#0D542B] hover:bg-emerald-50 font-bold text-xs rounded-lg transition-colors flex items-center gap-2 cursor-pointer shadow-sm"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            <span>Tambah Kabupaten/Kota</span>
                        </button>

                        <template v-if="currentTab === 'ump'">
                            <button
                                type="button"
                                @click="openCreateUmp"
                                class="px-4 py-2 bg-white text-[#0D542B] hover:bg-emerald-50 font-bold text-xs rounded-lg transition-colors flex items-center gap-2 cursor-pointer shadow-sm"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                <span>Atur UMP Provinsi</span>
                            </button>

                            <button
                                type="button"
                                @click="openCopyYearModal"
                                class="px-3.5 py-2 bg-white/10 hover:bg-white/20 text-white border border-white/20 rounded-lg text-xs font-semibold shadow-xs transition-colors cursor-pointer flex items-center gap-1.5"
                                title="Salin ketetapan UMP dari satu tahun ke tahun lainnya"
                            >
                                <svg class="w-4 h-4 text-emerald-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" />
                                </svg>
                                <span>Salin UMP Tahun</span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <!-- ISI KONTEN UTAMA -->
            <main class="admin-page-main w-full max-w-[1440px] mx-auto -mt-10 px-4 sm:px-6 lg:px-8 space-y-6 pb-12">
                <!-- KARTU STATISTIK RINGKASAN: Formal, Flat, 2-Tone -->
                <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3.5 sm:gap-4">
                    <!-- Kartu 1: Total Provinsi -->
                    <div class="bg-white rounded-xl p-4.5 border border-slate-200 shadow-2xs">
                        <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Master Provinsi</p>
                        <p class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight mt-1.5">{{ stats.total_provinsi }}</p>
                        <p class="text-xs text-slate-500 mt-1">Wilayah Tingkat I</p>
                    </div>

                    <!-- Kartu 2: Total Kabupaten/Kota -->
                    <div class="bg-white rounded-xl p-4.5 border border-slate-200 shadow-2xs">
                        <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Master Kabupaten / Kota</p>
                        <p class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight mt-1.5">{{ stats.total_kabupaten }}</p>
                        <p class="text-xs text-slate-500 mt-1">Wilayah Tingkat II</p>
                    </div>

                    <!-- Kartu 3: Regulasi UMP Tahun Ini -->
                    <div class="bg-white rounded-xl p-4.5 border border-slate-200 shadow-2xs">
                        <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Ketetapan UMP {{ selectedYear }}</p>
                        <p class="text-2xl sm:text-3xl font-extrabold text-[#0D542B] tracking-tight mt-1.5">{{ stats.total_ump_configured }} / {{ stats.total_provinsi }}</p>
                        <p class="text-xs text-slate-500 mt-1">{{ stats.total_ump_configured === stats.total_provinsi ? '100% Seluruh Provinsi Terdata' : `${stats.total_provinsi - stats.total_ump_configured} Provinsi Belum Diatur` }}</p>
                    </div>

                    <!-- Kartu 4: Rentang UMP Nasional -->
                    <div class="bg-white rounded-xl p-4.5 border border-slate-200 shadow-2xs">
                        <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Rata-rata UMP Nasional</p>
                        <p class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight mt-1.5">{{ formatRupiah(stats.avg_ump) }}</p>
                        <p class="text-xs text-slate-500 mt-1">Min: {{ formatRupiah(stats.min_ump.besaran) }} &bull; Max: {{ formatRupiah(stats.max_ump.besaran) }}</p>
                    </div>
                </div>

                <!-- TABS NAVIGASI RESMI: 2-tone clean styling -->
                <div class="bg-white p-2 rounded-xl border border-gray-300 shadow-2xs flex items-center gap-2 overflow-x-auto">
                    <button
                        type="button"
                        @click="switchTab('provinsi')"
                        class="px-4 py-2 text-xs font-bold rounded-lg transition-colors flex items-center gap-2 cursor-pointer whitespace-nowrap"
                        :class="currentTab === 'provinsi' ? 'bg-[#0D542B] text-white shadow-xs' : 'text-gray-600 hover:bg-gray-100'"
                    >
                        <span>Master Provinsi</span>
                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold" :class="currentTab === 'provinsi' ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-700'">{{ stats.total_provinsi }}</span>
                    </button>

                    <button
                        type="button"
                        @click="switchTab('kabupaten')"
                        class="px-4 py-2 text-xs font-bold rounded-lg transition-colors flex items-center gap-2 cursor-pointer whitespace-nowrap"
                        :class="currentTab === 'kabupaten' ? 'bg-[#0D542B] text-white shadow-xs' : 'text-gray-600 hover:bg-gray-100'"
                    >
                        <span>Master Kabupaten / Kota</span>
                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold" :class="currentTab === 'kabupaten' ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-700'">{{ stats.total_kabupaten }}</span>
                    </button>

                    <button
                        type="button"
                        @click="switchTab('ump')"
                        class="px-4 py-2 text-xs font-bold rounded-lg transition-colors flex items-center gap-2 cursor-pointer whitespace-nowrap"
                        :class="currentTab === 'ump' ? 'bg-[#0D542B] text-white shadow-xs' : 'text-gray-600 hover:bg-gray-100'"
                    >
                        <span>Upah Minimum Provinsi (UMP)</span>
                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold" :class="currentTab === 'ump' ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-700'">Tahun {{ selectedYear }}</span>
                    </button>
                </div>

                <!-- BILAH FILTER & PENCARIAN -->
                <div class="bg-white p-3.5 rounded-xl border border-gray-300 flex flex-col md:flex-row md:items-center justify-between gap-3 text-xs shadow-2xs">
                    <div class="flex items-center gap-3 flex-wrap">
                        <!-- Filter Provinsi Induk (Khusus Tab Kabupaten) -->
                        <div v-if="currentTab === 'kabupaten'" class="flex items-center gap-1.5">
                            <span class="font-bold text-gray-700">Provinsi Induk:</span>
                            <select
                                v-model="selectedProvinsiId"
                                @change="applyFilters"
                                class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white focus:outline-none focus:ring-1 focus:ring-emerald-700 font-medium cursor-pointer"
                            >
                                <option value="">Semua Provinsi</option>
                                <option v-for="p in all_provinces" :key="p.id" :value="p.id">
                                    [{{ p.kode_provinsi }}] {{ p.nama_provinsi }}
                                </option>
                            </select>
                        </div>

                        <!-- Filter Tahun UMP (Khusus Tab UMP) -->
                        <div v-if="currentTab === 'ump'" class="flex items-center gap-1.5">
                            <span class="font-bold text-gray-700">Tahun Ketetapan:</span>
                            <select
                                v-model="selectedYear"
                                @change="applyFilters"
                                class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white focus:outline-none focus:ring-1 focus:ring-emerald-700 font-semibold cursor-pointer"
                            >
                                <option v-for="yr in available_years" :key="yr" :value="yr">
                                    Tahun {{ yr }}
                                </option>
                            </select>
                        </div>

                        <!-- Filter Status UMP (Khusus Tab UMP) -->
                        <div v-if="currentTab === 'ump'" class="flex items-center gap-1.5">
                            <span class="font-bold text-gray-700">Filter Data:</span>
                            <select
                                v-model="filterStatusUmp"
                                class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white focus:outline-none focus:ring-1 focus:ring-emerald-700 font-medium cursor-pointer"
                            >
                                <option value="all">Semua Provinsi ({{ props.umps.length }})</option>
                                <option value="configured">Sudah Diatur ({{ totalUmpConfiguredCount }})</option>
                                <option value="unconfigured">Belum Diatur ({{ totalUmpUnconfiguredCount }})</option>
                            </select>
                        </div>

                        <!-- Per Page Dropdown (Khusus Tab Kabupaten) -->
                        <div v-if="currentTab === 'kabupaten'" class="flex items-center gap-1.5">
                            <span class="text-gray-500 font-medium">Tampilkan:</span>
                            <select
                                v-model="perPage"
                                @change="applyFilters"
                                class="px-2 py-1.5 border border-gray-300 rounded-lg text-xs bg-white focus:outline-none focus:ring-1 focus:ring-emerald-700 font-medium cursor-pointer"
                            >
                                <option :value="15">15 data</option>
                                <option :value="25">25 data</option>
                                <option :value="50">50 data</option>
                                <option :value="100">100 data</option>
                            </select>
                        </div>
                    </div>

                    <!-- Kotak Pencarian -->
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
                            :placeholder="currentTab === 'provinsi' ? 'Cari nama / kode provinsi...' : (currentTab === 'kabupaten' ? 'Cari nama / kode kabupaten...' : 'Cari nama / kode provinsi UMP...')"
                            class="w-full pl-8 pr-3 py-1.5 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-emerald-700 bg-white text-gray-900 placeholder-gray-400"
                        />
                    </div>
                </div>

                <!-- Info Jumlah Data & Tombol Reset -->
                <div class="flex items-center justify-between text-xs text-gray-500 px-1">
                    <span v-if="currentTab === 'provinsi'">
                        Menampilkan <strong class="text-gray-900">{{ provinces.length }}</strong> provinsi terdaftar
                    </span>
                    <span v-else-if="currentTab === 'kabupaten'">
                        Menampilkan <strong class="text-gray-900">{{ kabupatens.data?.length || 0 }}</strong> dari total <strong class="text-gray-900">{{ kabupatens.total || 0 }}</strong> kabupaten/kota
                    </span>
                    <span v-else>
                        Menampilkan <strong class="text-gray-900">{{ filteredUmps.length }}</strong> provinsi ketetapan UMP Tahun {{ selectedYear }}
                    </span>

                    <button
                        v-if="search || selectedProvinsiId || (currentTab === 'ump' && filterStatusUmp !== 'all')"
                        @click="resetFilters"
                        class="text-[#0D542B] font-semibold hover:underline cursor-pointer"
                    >
                        Reset Filter
                    </button>
                </div>

                <!-- ======================================================== -->
                <!-- TABEL 1: MASTER PROVINSI                                -->
                <!-- ======================================================== -->
                <div v-if="currentTab === 'provinsi'" class="bg-white rounded-xl border border-gray-300 overflow-x-auto shadow-2xs">
                    <table class="w-full min-w-full text-left border-collapse border border-gray-300 text-xs bg-white">
                        <thead>
                            <tr class="bg-gray-100 text-gray-800 font-semibold border-b border-gray-300 text-[11px] whitespace-nowrap">
                                <th class="border border-gray-300 px-2.5 py-2 text-center w-12 whitespace-nowrap">No</th>
                                <th class="border border-gray-300 px-3 py-2 w-36 whitespace-nowrap">Kode Provinsi</th>
                                <th class="border border-gray-300 px-3 py-2 whitespace-nowrap">Nama Provinsi</th>
                                <th class="border border-gray-300 px-3 py-2 text-center w-44 whitespace-nowrap">Jumlah Kab/Kota</th>
                                <th class="border border-gray-300 px-2.5 py-2 text-center w-24 whitespace-nowrap">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="provinces.length === 0">
                                <td colspan="5" class="border border-gray-300 p-8 text-center text-gray-400 italic">
                                    Tidak ada data provinsi yang cocok dengan pencarian.
                                </td>
                            </tr>
                            <tr
                                v-for="(prov, idx) in provinces"
                                :key="prov.id"
                                class="hover:bg-gray-50/80 transition-colors"
                            >
                                <td class="border border-gray-300 px-2.5 py-2 text-center text-gray-600 font-medium">
                                    {{ idx + 1 }}
                                </td>
                                <td class="border border-gray-300 px-3 py-2 font-mono font-bold text-gray-900">
                                    {{ prov.kode_provinsi }}
                                </td>
                                <td class="border border-gray-300 px-3 py-2 font-bold text-gray-900">
                                    {{ prov.nama_provinsi }}
                                </td>
                                <td class="border border-gray-300 px-3 py-2 text-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-gray-100 text-gray-700 border border-gray-200">
                                        {{ prov.total_kabupaten }} Daerah
                                    </span>
                                </td>
                                <td class="border border-gray-300 px-2.5 py-2 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <!-- Sunting (Pencil) -->
                                        <button
                                            type="button"
                                            @click="openEditProvinsi(prov)"
                                            class="p-1.5 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors cursor-pointer"
                                            title="Edit Provinsi"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>

                                        <!-- Hapus (Trash) -->
                                        <button
                                            type="button"
                                            @click="confirmDeleteProvinsi(prov)"
                                            class="p-1.5 text-gray-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer"
                                            title="Hapus Provinsi"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- ======================================================== -->
                <!-- TABEL 2: MASTER KABUPATEN                               -->
                <!-- ======================================================== -->
                <div v-if="currentTab === 'kabupaten'" class="space-y-4">
                    <div class="bg-white rounded-xl border border-gray-300 overflow-x-auto shadow-2xs">
                        <table class="w-full min-w-full text-left border-collapse border border-gray-300 text-xs bg-white">
                            <thead>
                                <tr class="bg-gray-100 text-gray-800 font-semibold border-b border-gray-300 text-[11px] whitespace-nowrap">
                                    <th class="border border-gray-300 px-2.5 py-2 text-center w-12 whitespace-nowrap">No</th>
                                    <th class="border border-gray-300 px-3 py-2 w-36 whitespace-nowrap">Kode Daerah</th>
                                    <th class="border border-gray-300 px-3 py-2 whitespace-nowrap">Nama Kabupaten / Kota</th>
                                    <th class="border border-gray-300 px-3 py-2 w-72 whitespace-nowrap">Provinsi Induk</th>
                                    <th class="border border-gray-300 px-2.5 py-2 text-center w-24 whitespace-nowrap">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!kabupatens.data || kabupatens.data.length === 0">
                                    <td colspan="5" class="border border-gray-300 p-8 text-center text-gray-400 italic">
                                        Tidak ada data kabupaten/kota yang sesuai kriteria pencarian.
                                    </td>
                                </tr>
                                <tr
                                    v-for="(kab, idx) in kabupatens.data"
                                    :key="kab.id"
                                    class="hover:bg-gray-50/80 transition-colors"
                                >
                                    <td class="border border-gray-300 px-2.5 py-2 text-center text-gray-600 font-medium">
                                        {{ ((kabupatens.current_page - 1) * kabupatens.per_page) + idx + 1 }}
                                    </td>
                                    <td class="border border-gray-300 px-3 py-2 font-mono font-bold text-gray-900">
                                        {{ kab.kode_kabupaten || '-' }}
                                    </td>
                                    <td class="border border-gray-300 px-3 py-2 font-bold text-gray-900">
                                        {{ kab.nama_kabupaten }}
                                    </td>
                                    <td class="border border-gray-300 px-3 py-2 text-gray-700">
                                        <span class="inline-flex items-center font-mono text-[11px] font-bold text-gray-700 bg-gray-100 px-1.5 py-0.5 rounded border border-gray-200 mr-1.5">
                                            {{ kab.kode_provinsi }}
                                        </span>
                                        <span>{{ kab.nama_provinsi }}</span>
                                    </td>
                                    <td class="border border-gray-300 px-2.5 py-2 text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <!-- Sunting (Pencil) -->
                                            <button
                                                type="button"
                                                @click="openEditKabupaten(kab)"
                                                class="p-1.5 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors cursor-pointer"
                                                title="Edit Kabupaten/Kota"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>

                                            <!-- Hapus (Trash) -->
                                            <button
                                                type="button"
                                                @click="confirmDeleteKabupaten(kab)"
                                                class="p-1.5 text-gray-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer"
                                                title="Hapus Kabupaten/Kota"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- PAGINASI KABUPATEN -->
                    <div v-if="kabupatens.links && kabupatens.links.length > 3" class="p-3 border border-gray-300 rounded-xl bg-white flex flex-col sm:flex-row items-center justify-between gap-3 text-xs shadow-2xs">
                        <span class="text-gray-500">
                            Menampilkan {{ kabupatens.from || 0 }} sampai {{ kabupatens.to || 0 }} dari {{ kabupatens.total || 0 }} data
                        </span>
                        <div class="flex items-center gap-1 flex-wrap">
                            <button
                                v-for="(link, i) in kabupatens.links"
                                :key="i"
                                type="button"
                                @click="link.url ? router.get(link.url, {}, { preserveState: true }) : null"
                                :disabled="!link.url || link.active"
                                class="px-2.5 py-1 text-xs rounded border transition-colors cursor-pointer disabled:cursor-not-allowed"
                                :class="[
                                    link.active
                                        ? 'bg-[#0D542B] text-white border-[#0D542B] font-bold'
                                        : link.url
                                            ? 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
                                            : 'bg-gray-100 text-gray-400 border-gray-200'
                                ]"
                                v-html="link.label"
                            ></button>
                        </div>
                    </div>
                </div>

                <!-- ======================================================== -->
                <!-- TABEL 3: STANDAR UMP PER PROVINSI                       -->
                <!-- ======================================================== -->
                <div v-if="currentTab === 'ump'" class="bg-white rounded-xl border border-gray-300 overflow-x-auto shadow-2xs">
                    <table class="w-full min-w-full text-left border-collapse border border-gray-300 text-xs bg-white">
                        <thead>
                            <tr class="bg-gray-100 text-gray-800 font-semibold border-b border-gray-300 text-[11px] whitespace-nowrap">
                                <th class="border border-gray-300 px-2.5 py-2 text-center w-12 whitespace-nowrap">No</th>
                                <th class="border border-gray-300 px-3 py-2 w-32 whitespace-nowrap">Kode Provinsi</th>
                                <th class="border border-gray-300 px-3 py-2 whitespace-nowrap">Nama Provinsi</th>
                                <th class="border border-gray-300 px-3 py-2 text-center w-24 whitespace-nowrap">Tahun</th>
                                <th class="border border-gray-300 px-3 py-2 text-right w-44 whitespace-nowrap">Standar UMP</th>
                                <th class="border border-gray-300 px-3 py-2 whitespace-nowrap">Dasar Ketetapan / Catatan SK</th>
                                <th class="border border-gray-300 px-2.5 py-2 text-center w-24 whitespace-nowrap">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="filteredUmps.length === 0">
                                <td colspan="7" class="border border-gray-300 p-8 text-center text-gray-400 italic">
                                    Tidak ada data ketetapan UMP yang cocok dengan filter.
                                </td>
                            </tr>
                            <tr
                                v-for="(u, idx) in filteredUmps"
                                :key="u.kode_provinsi"
                                class="hover:bg-gray-50/80 transition-colors"
                            >
                                <td class="border border-gray-300 px-2.5 py-2 text-center text-gray-600 font-medium">
                                    {{ idx + 1 }}
                                </td>
                                <td class="border border-gray-300 px-3 py-2 font-mono font-bold text-gray-900">
                                    {{ u.kode_provinsi }}
                                </td>
                                <td class="border border-gray-300 px-3 py-2 font-bold text-gray-900">
                                    {{ u.nama_provinsi }}
                                </td>
                                <td class="border border-gray-300 px-3 py-2 text-center font-semibold text-gray-700">
                                    {{ u.tahun }}
                                </td>
                                <td class="border border-gray-300 px-3 py-2 text-right">
                                    <span v-if="u.has_ump" class="font-bold text-gray-900 font-mono">
                                        {{ formatRupiah(u.besaran) }}
                                    </span>
                                    <span v-else class="text-rose-600 text-[11px] font-medium italic">
                                        Belum diatur
                                    </span>
                                </td>
                                <td class="border border-gray-300 px-3 py-2 text-gray-700">
                                    {{ u.catatan || '-' }}
                                </td>
                                <td class="border border-gray-300 px-2.5 py-2 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <!-- Jika sudah ada ketetapan UMP -->
                                        <template v-if="u.has_ump">
                                            <!-- Sunting (Pencil) -->
                                            <button
                                                type="button"
                                                @click="openConfigureUmp(u)"
                                                class="p-1.5 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors cursor-pointer"
                                                title="Ubah Ketetapan UMP"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>

                                            <!-- Hapus (Trash) -->
                                            <button
                                                type="button"
                                                @click="confirmDeleteUmp(u)"
                                                class="p-1.5 text-gray-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer"
                                                title="Hapus Ketetapan UMP"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </template>

                                        <!-- Jika belum ada ketetapan UMP: Tombol Tambah/Atur (Plus icon) -->
                                        <button
                                            v-else
                                            type="button"
                                            @click="openConfigureUmp(u)"
                                            class="p-1.5 text-gray-600 hover:text-[#0D542B] hover:bg-emerald-50 rounded-lg transition-colors cursor-pointer"
                                            title="Tetapkan UMP"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>

        <!-- ======================================================== -->
        <!-- MODAL 1: TAMBAH / EDIT PROVINSI (MURNI CRUD)             -->
        <!-- ======================================================== -->
        <div
            v-if="showProvinsiModal"
            class="fixed inset-0 z-50 overflow-y-auto bg-black/40 flex items-center justify-center p-4 backdrop-blur-xs"
        >
            <div class="bg-white rounded-xl border border-gray-300 shadow-2xl max-w-md w-full overflow-hidden transition-all">
                <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between bg-gray-50">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900">
                            {{ isEditingProvinsi ? 'Edit Data Provinsi' : 'Tambah Provinsi Baru' }}
                        </h3>
                        <p class="text-[11px] text-gray-500 mt-0.5">Master Data Wilayah Tingkat I Nasional</p>
                    </div>
                    <button
                        type="button"
                        @click="showProvinsiModal = false"
                        class="text-gray-400 hover:text-gray-600 cursor-pointer p-1 rounded-md hover:bg-gray-200/50"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitProvinsi" class="p-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">
                            Kode Provinsi <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            v-model="formProvinsi.kode_provinsi"
                            placeholder="Contoh: 34"
                            class="w-full text-xs px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-emerald-700 font-mono font-bold"
                            required
                        />
                        <span class="text-[11px] text-gray-400 mt-0.5 block">Kode standar resmi BPS / Kemendagri</span>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">
                            Nama Provinsi <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            v-model="formProvinsi.nama_provinsi"
                            placeholder="Contoh: DAERAH ISTIMEWA YOGYAKARTA"
                            class="w-full text-xs px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-emerald-700 uppercase font-semibold"
                            required
                        />
                    </div>

                    <div class="pt-3 border-t border-gray-200 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            @click="showProvinsiModal = false"
                            class="px-3.5 py-2 text-xs text-gray-600 hover:bg-gray-100 rounded-lg transition-colors cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="isSubmitting"
                            class="px-4 py-2 text-xs font-bold bg-[#0D542B] text-white rounded-lg hover:bg-[#0a4222] transition-colors cursor-pointer disabled:opacity-50 shadow-2xs"
                        >
                            {{ isSubmitting ? 'Menyimpan...' : (isEditingProvinsi ? 'Perbarui Provinsi' : 'Simpan Provinsi') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ======================================================== -->
        <!-- MODAL 2: TAMBAH / EDIT KABUPATEN (MURNI CRUD)            -->
        <!-- ======================================================== -->
        <div
            v-if="showKabupatenModal"
            class="fixed inset-0 z-50 overflow-y-auto bg-black/40 flex items-center justify-center p-4 backdrop-blur-xs"
        >
            <div class="bg-white rounded-xl border border-gray-300 shadow-2xl max-w-md w-full overflow-hidden transition-all">
                <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between bg-gray-50">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900">
                            {{ isEditingKabupaten ? 'Edit Kabupaten/Kota' : 'Tambah Kabupaten/Kota Baru' }}
                        </h3>
                        <p class="text-[11px] text-gray-500 mt-0.5">Master Data Wilayah Tingkat II Nasional</p>
                    </div>
                    <button
                        type="button"
                        @click="showKabupatenModal = false"
                        class="text-gray-400 hover:text-gray-600 cursor-pointer p-1 rounded-md hover:bg-gray-200/50"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitKabupaten" class="p-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">
                            Provinsi Induk <span class="text-rose-500">*</span>
                        </label>
                        <select
                            v-model="formKabupaten.propinsi_id"
                            class="w-full text-xs px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-emerald-700"
                            required
                        >
                            <option value="" disabled>-- Pilih Provinsi Induk --</option>
                            <option v-for="p in all_provinces" :key="p.id" :value="p.id">
                                [{{ p.kode_provinsi }}] {{ p.nama_provinsi }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">
                            Kode Kabupaten/Kota <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            v-model="formKabupaten.kode_kabupaten"
                            placeholder="Contoh: 34.71 atau 3404"
                            class="w-full text-xs px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-emerald-700 font-mono font-bold"
                            required
                        />
                        <span class="text-[11px] text-gray-400 mt-0.5 block">Kode wilayah tingkat II</span>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">
                            Nama Kabupaten / Kota <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            v-model="formKabupaten.nama_kabupaten"
                            placeholder="Contoh: KOTA YOGYAKARTA atau KAB. SLEMAN"
                            class="w-full text-xs px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-emerald-700 uppercase font-semibold"
                            required
                        />
                    </div>

                    <div class="pt-3 border-t border-gray-200 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            @click="showKabupatenModal = false"
                            class="px-3.5 py-2 text-xs text-gray-600 hover:bg-gray-100 rounded-lg transition-colors cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="isSubmitting"
                            class="px-4 py-2 text-xs font-bold bg-[#0D542B] text-white rounded-lg hover:bg-[#0a4222] transition-colors cursor-pointer disabled:opacity-50 shadow-2xs"
                        >
                            {{ isSubmitting ? 'Menyimpan...' : (isEditingKabupaten ? 'Perbarui Kabupaten' : 'Simpan Kabupaten') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ======================================================== -->
        <!-- MODAL 3: ATUR BESARAN UMP KHUSUS PER PROVINSI            -->
        <!-- ======================================================== -->
        <div
            v-if="showUmpModal"
            class="fixed inset-0 z-50 overflow-y-auto bg-black/40 flex items-center justify-center p-4 backdrop-blur-xs"
        >
            <div class="bg-white rounded-xl border border-gray-300 shadow-2xl max-w-md w-full overflow-hidden transition-all">
                <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between bg-gray-50">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900">
                            Atur Standar UMP Provinsi
                        </h3>
                        <p class="text-[11px] text-gray-500 mt-0.5">Ketetapan Upah Minimum Provinsi (Tahun {{ formUmp.tahun }})</p>
                    </div>
                    <button
                        type="button"
                        @click="showUmpModal = false"
                        class="text-gray-400 hover:text-gray-600 cursor-pointer p-1 rounded-md hover:bg-gray-200/50"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitUmp" class="p-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">
                            Provinsi <span class="text-rose-500">*</span>
                        </label>
                        <select
                            v-model="formUmp.kode_provinsi"
                            class="w-full text-xs px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-emerald-700 font-semibold"
                            required
                        >
                            <option value="" disabled>-- Pilih Provinsi --</option>
                            <option v-for="p in all_provinces" :key="p.kode_provinsi" :value="p.kode_provinsi">
                                [{{ p.kode_provinsi }}] {{ p.nama_provinsi }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">
                            Tahun Ketetapan <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="number"
                            v-model="formUmp.tahun"
                            class="w-full text-xs px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-emerald-700 font-bold font-mono"
                            required
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">
                            Besaran UMP (Nominal Rupiah) <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-2 text-xs font-bold text-gray-400">Rp</span>
                            <input
                                type="number"
                                v-model="formUmp.besaran"
                                placeholder="Contoh: 2417495"
                                class="w-full text-xs pl-9 pr-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-emerald-700 font-mono font-bold text-gray-900"
                                required
                            />
                        </div>
                        <div v-if="formUmp.besaran" class="mt-1 text-xs text-emerald-800 font-bold bg-emerald-50 px-2 py-1 rounded border border-emerald-200">
                            Pratinjau: {{ formatRupiah(formUmp.besaran) }}
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">
                            Dasar Ketetapan / Catatan SK Gubernur
                        </label>
                        <input
                            type="text"
                            v-model="formUmp.catatan"
                            placeholder="Contoh: SK Gubernur DIY No. 402/2025"
                            class="w-full text-xs px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-emerald-700"
                        />
                    </div>

                    <div class="pt-3 border-t border-gray-200 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            @click="showUmpModal = false"
                            class="px-3.5 py-2 text-xs text-gray-600 hover:bg-gray-100 rounded-lg transition-colors cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="isSubmitting"
                            class="px-4 py-2 text-xs font-bold bg-[#0D542B] text-white rounded-lg hover:bg-[#0a4222] transition-colors cursor-pointer disabled:opacity-50 shadow-2xs"
                        >
                            {{ isSubmitting ? 'Menyimpan...' : 'Simpan Ketetapan UMP' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ======================================================== -->
        <!-- MODAL 4: SALIN UMP ANTAR TAHUN                           -->
        <!-- ======================================================== -->
        <div
            v-if="showCopyYearModal"
            class="fixed inset-0 z-50 overflow-y-auto bg-black/40 flex items-center justify-center p-4 backdrop-blur-xs"
        >
            <div class="bg-white rounded-xl border border-gray-300 shadow-2xl max-w-md w-full overflow-hidden transition-all">
                <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between bg-gray-50">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900">
                            Salin Data UMP Antar Tahun
                        </h3>
                        <p class="text-[11px] text-gray-500 mt-0.5">Duplikasi ketetapan seluruh provinsi ke tahun berikutnya</p>
                    </div>
                    <button
                        type="button"
                        @click="showCopyYearModal = false"
                        class="text-gray-400 hover:text-gray-600 cursor-pointer p-1 rounded-md hover:bg-gray-200/50"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitCopyYear" class="p-5 space-y-4">
                    <p class="text-xs text-slate-600 leading-relaxed bg-blue-50 p-3 rounded-lg border border-blue-100 text-blue-900">
                        Fitur ini akan menyalin seluruh standar UMP dari tahun sumber ke tahun target sekaligus secara otomatis.
                    </p>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">
                                Tahun Sumber <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="number"
                                v-model="formCopyYear.source_year"
                                class="w-full text-xs px-3 py-2 border border-gray-300 rounded-lg bg-white font-mono font-bold"
                                required
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">
                                Tahun Target <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="number"
                                v-model="formCopyYear.target_year"
                                class="w-full text-xs px-3 py-2 border border-gray-300 rounded-lg bg-white font-bold font-mono text-[#0D542B]"
                                required
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">
                            Penyesuaian Kenaikan Rata-rata (%)
                        </label>
                        <div class="relative">
                            <input
                                type="number"
                                step="0.1"
                                v-model="formCopyYear.percentage_increase"
                                placeholder="0"
                                class="w-full text-xs pl-3 pr-8 py-2 border border-gray-300 rounded-lg bg-white font-mono"
                            />
                            <span class="absolute right-3 top-2 text-xs font-bold text-gray-400">%</span>
                        </div>
                        <span class="text-[11px] text-gray-400 mt-0.5 block">
                            Isi 0 jika nominal sama persis, atau isi persentase kenaikan (contoh: 5.5).
                        </span>
                    </div>

                    <div class="pt-3 border-t border-gray-200 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            @click="showCopyYearModal = false"
                            class="px-3.5 py-2 text-xs text-gray-600 hover:bg-gray-100 rounded-lg transition-colors cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="isSubmitting"
                            class="px-4 py-2 text-xs font-bold bg-[#0D542B] text-white rounded-lg hover:bg-[#0a4222] transition-colors cursor-pointer disabled:opacity-50 shadow-2xs"
                        >
                            {{ isSubmitting ? 'Memproses...' : 'Mulai Salin Data' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
