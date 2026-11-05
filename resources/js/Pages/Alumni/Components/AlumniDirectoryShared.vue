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
    filterOptions: {
        type: Object,
        default: () => ({
            wilayah: {
                provinsi: [],
                kabupaten: [],
            },
            head_office: [],
            skills: [],
            bidang: [],
            unit: [],
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
const provinsi = ref(props.filters.kode_provinsi || 'all');
const kabupaten = ref(props.filters.kode_kabupaten || 'all');
const provinsiSearch = ref('');
const kabupatenSearch = ref('');
const showProvinsiDropdown = ref(false);
const showKabupatenDropdown = ref(false);
const headOffice = ref(props.filters.head_office || 'all');
const skills = ref(props.filters.skills || 'all');
const bidang = ref(props.filters.bidang || 'all');
const unit = ref(props.filters.unit || 'all');

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
        if (newFilters.kode_provinsi !== undefined) {
            provinsi.value = newFilters.kode_provinsi || 'all';
        }
        if (newFilters.kode_kabupaten !== undefined) {
            kabupaten.value = newFilters.kode_kabupaten || 'all';
        }
        if (newFilters.head_office !== undefined) {
            headOffice.value = newFilters.head_office || 'all';
        }
        if (newFilters.skills !== undefined) {
            skills.value = newFilters.skills || 'all';
        }
        if (newFilters.bidang !== undefined) {
            bidang.value = newFilters.bidang || 'all';
        }
        if (newFilters.unit !== undefined) {
            unit.value = newFilters.unit || 'all';
        }
    }
}, { deep: true });

// Pagination server-side
const perPage = ref(props.pagination?.per_page || 10);
const currentPage = ref(props.pagination?.current_page || 1);

const totalPages = computed(() => {
    return props.pagination?.last_page || 1;
});

// Batasi pilihan provinsi berdasarkan kata pencarian.
const filteredProvinsiOptions = computed(() => {
    const options = props.filterOptions?.wilayah?.provinsi || [];
    const keyword = provinsiSearch.value.trim().toLowerCase();

    if (!keyword) {
        return options;
    }

    return options.filter((option) => String(option.label || '').toLowerCase().includes(keyword));
});

// Batasi pilihan kabupaten/kota berdasarkan provinsi dan kata pencarian.
const filteredKabupatenOptions = computed(() => {
    const options = props.filterOptions?.wilayah?.kabupaten || [];
    const keyword = kabupatenSearch.value.trim().toLowerCase();
    const scopedOptions = provinsi.value === 'all'
        ? options
        : options.filter((option) => option.provinsi === provinsi.value);

    if (!keyword) {
        return scopedOptions;
    }

    return scopedOptions.filter((option) => String(option.label || '').toLowerCase().includes(keyword));
});

// Menampilkan label provinsi yang sedang aktif pada input pencarian.
const selectedProvinsiLabel = computed(() => {
    if (provinsi.value === 'all') {
        return '';
    }

    return props.filterOptions?.wilayah?.provinsi?.find((option) => option.value === provinsi.value)?.label || '';
});

// Menampilkan label kabupaten/kota yang sedang aktif pada input pencarian.
const selectedKabupatenLabel = computed(() => {
    if (kabupaten.value === 'all') {
        return '';
    }

    return props.filterOptions?.wilayah?.kabupaten?.find((option) => option.value === kabupaten.value)?.label || '';
});

// Memilih provinsi, mereset kabupaten/kota, dan menerapkan filter berbasis ID.
const selectProvinsi = (option) => {
    provinsi.value = option.value;
    provinsiSearch.value = '';
    showProvinsiDropdown.value = false;
    kabupaten.value = 'all';
    kabupatenSearch.value = '';
    showKabupatenDropdown.value = false;
    applyFilters(1);
};

// Memilih kabupaten/kota dari provinsi yang sudah dipilih.
const selectKabupaten = (option) => {
    kabupaten.value = option.value;
    kabupatenSearch.value = '';
    showKabupatenDropdown.value = false;
    applyFilters(1);
};

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
        kode_provinsi: provinsi.value !== 'all' ? provinsi.value : undefined,
        kode_kabupaten: kabupaten.value !== 'all' ? kabupaten.value : undefined,
        head_office: headOffice.value !== 'all' ? headOffice.value : undefined,
        skills: skills.value !== 'all' ? skills.value : undefined,
        bidang: bidang.value !== 'all' ? bidang.value : undefined,
        unit: unit.value !== 'all' ? unit.value : undefined,
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
    provinsi.value = 'all';
    kabupaten.value = 'all';
    provinsiSearch.value = '';
    kabupatenSearch.value = '';
    showProvinsiDropdown.value = false;
    showKabupatenDropdown.value = false;
    headOffice.value = 'all';
    skills.value = 'all';
    bidang.value = 'all';
    unit.value = 'all';
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

// Mengirim pengingat kuesioner ke satu alumni melalui endpoint sesuai role aktif.
const handleSendEmail = (alumni) => {
    Swal.fire({
        title: 'Kirim email pengingat?',
        text: 'Email pengingat akan dikirim ke ' + alumni.nama + '.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Kirim Email',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#0D542B',
        cancelButtonColor: '#64748b',
    }).then((result) => {
        if (!result.isConfirmed) {
            return;
        }

        const endpoint = '/' + props.prefix + '/alumni/' + alumni.id + '/send-email';
        router.post(endpoint, {}, {
            preserveScroll: true,
            onSuccess: (response) => {
                const flash = response.props.flash || {};

                if (flash.error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Email Tidak Terkirim',
                        text: flash.error,
                        confirmButtonColor: '#0D542B',
                    });

                    return;
                }

                Swal.fire({
                    icon: 'success',
                    title: 'Email Berhasil Dikirim',
                    text: flash.success || ('Email pengingat berhasil dikirim ke ' + alumni.nama + '.'),
                    confirmButtonColor: '#0D542B',
                });
            },
            onError: () => {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal Mengirim Email',
                    text: 'Email pengingat tidak dapat dikirim saat ini.',
                    confirmButtonColor: '#0D542B',
                });
            },
        });
    });
};

// Preview foto alumni yang dipilih dari tabel direktori.
const showPhotoPreview = ref(false);
const activePhotoAlumni = ref(null);

const openPhotoPreview = (alumni) => {
    if (!alumni?.foto) {
        return;
    }

    activePhotoAlumni.value = alumni;
    showPhotoPreview.value = true;
};

// Menyusun ringkasan karier sesuai aktivitas alumni saat ini.
const careerSummary = (alumni) => {
    const category = String(alumni?.kategori_pekerjaan || '').toLowerCase();
    const studyDetails = [alumni?.pendidikan_tingkat, alumni?.perguruan_tinggi, alumni?.pendidikan_prodi]
        .filter(Boolean)
        .join(' - ');

    if (category.includes('melanjutkan') || studyDetails) {
        return [
            'Studi lanjut',
            studyDetails || 'Detail studi belum diisi',
        ];
    }

    if (category.includes('wiraswasta') || category.includes('wirausaha')) {
        return ['Wiraswasta', alumni.posisi_wiraswasta || alumni.experience || 'Detail usaha belum diisi'];
    }

    if (category.includes('tidak') || category.includes('mencari')) {
        return ['Aktivitas', alumni.kategori_pekerjaan || 'Belum bekerja'];
    }

    return ['Experience', alumni.experience || 'Belum ada pengalaman yang diisi'];
};

// Validasi URL profil LinkedIn sebelum menyimpan URL untuk proses sinkronisasi.
const isValidLinkedInProfileUrl = (value) => {
    try {
        const url = new URL(value);
        return ['http:', 'https:'].includes(url.protocol)
            && /(^|\.)linkedin\.com$/i.test(url.hostname)
            && url.pathname.toLowerCase().startsWith('/in/');
    } catch {
        return false;
    }
};

// Menyimpan URL LinkedIn lalu memulai sinkronisasi dari direktori alumni.
const handleSyncLinkedIn = async (alumni) => {
    const result = await Swal.fire({
        title: alumni.linkedin_url ? 'Perbarui URL LinkedIn' : 'Masukkan URL LinkedIn',
        html: `<p class="text-xs text-slate-600">URL ini akan digunakan untuk sinkronisasi profil <b>${alumni.nama}</b> (${alumni.nim}).</p>`,
        icon: 'info',
        input: 'url',
        inputValue: alumni.linkedin_url || '',
        inputPlaceholder: 'https://www.linkedin.com/in/username',
        showCancelButton: true,
        confirmButtonText: 'Simpan & Sinkronkan',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#0D542B',
        cancelButtonColor: '#94a3b8',
        inputValidator: (value) => {
            if (!isValidLinkedInProfileUrl(value?.trim())) {
                return 'Masukkan URL profil LinkedIn yang valid dengan format https://www.linkedin.com/in/...';
            }
        },
    });

    if (!result.isConfirmed || !result.value) {
        return;
    }

    try {
        const url = result.value.trim();
        const headers = {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        };
        const saveResponse = await fetch(`/${props.prefix}/linkedin-sync/${alumni.id}/url`, {
            method: 'PATCH',
            headers,
            body: JSON.stringify({ linkedin_url: url }),
        });
        const saveData = await saveResponse.json();

        if (!saveResponse.ok || !saveData.success) {
            throw new Error(saveData.message || 'URL LinkedIn gagal diperbarui.');
        }

        const syncResponse = await fetch(`/${props.prefix}/linkedin-sync/${alumni.id}`, {
            method: 'POST',
            headers,
        });
        const syncData = await syncResponse.json();

        if (!syncResponse.ok || !syncData.success) {
            throw new Error(syncData.message || 'Sinkronisasi LinkedIn gagal dijalankan.');
        }

        Swal.fire({
            icon: 'success',
            title: 'Sinkronisasi Dimulai',
            text: 'Profil LinkedIn masuk ke proses review.',
            confirmButtonColor: '#0D542B',
        });
        router.reload({ preserveScroll: true });
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Sinkronisasi Gagal',
            text: error.message || 'Terjadi kesalahan saat menyinkronkan profil LinkedIn.',
            confirmButtonColor: '#0D542B',
        });
    }
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

                        <!-- Filter provinsi searchable sebagai tingkat pertama wilayah berbasis kode master. -->
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Provinsi</label>
                            <div class="relative">
                                <input
                                    v-model="provinsiSearch"
                                    type="search"
                                    :placeholder="selectedProvinsiLabel || 'Cari provinsi...'"
                                    @focus="showProvinsiDropdown = true"
                                    @blur="setTimeout(() => { showProvinsiDropdown = false; }, 150)"
                                    class="w-full text-xs font-semibold bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 pr-8 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0D542B]/20 focus:border-[#0D542B]"
                                />
                                <button
                                    type="button"
                                    class="absolute inset-y-0 right-0 px-2 text-slate-500"
                                    aria-label="Buka pilihan provinsi"
                                    @click="showProvinsiDropdown = !showProvinsiDropdown"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 9-7 7-7-7"/></svg>
                                </button>
                                <div v-if="showProvinsiDropdown" class="absolute z-30 mt-1 max-h-56 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-lg">
                                    <button
                                        type="button"
                                        class="block w-full rounded-lg px-3 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                        @mousedown.prevent="selectProvinsi({ value: 'all', label: 'Semua Provinsi' })"
                                    >
                                        Semua Provinsi
                                    </button>
                                    <button
                                        v-for="option in filteredProvinsiOptions"
                                        :key="option.value"
                                        type="button"
                                        class="block w-full rounded-lg px-3 py-2 text-left text-xs text-slate-700 hover:bg-emerald-50"
                                        @mousedown.prevent="selectProvinsi(option)"
                                    >
                                        {{ option.label }}
                                    </button>
                                    <p v-if="filteredProvinsiOptions.length === 0" class="px-3 py-2 text-xs text-slate-500">Provinsi tidak ditemukan.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Filter kabupaten/kota searchable dan bergantung pada provinsi terpilih. -->
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Kabupaten / Kota</label>
                            <div class="relative">
                                <input
                                    v-model="kabupatenSearch"
                                    type="search"
                                    :placeholder="provinsi === 'all' ? 'Pilih provinsi dahulu' : (selectedKabupatenLabel || 'Cari kabupaten/kota...')"
                                    :disabled="provinsi === 'all'"
                                    @focus="showKabupatenDropdown = true"
                                    @blur="setTimeout(() => { showKabupatenDropdown = false; }, 150)"
                                    class="w-full text-xs font-semibold bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 pr-8 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0D542B]/20 focus:border-[#0D542B] disabled:cursor-not-allowed disabled:bg-slate-100"
                                />
                                <button
                                    type="button"
                                    :disabled="provinsi === 'all'"
                                    class="absolute inset-y-0 right-0 px-2 text-slate-500 disabled:cursor-not-allowed"
                                    aria-label="Buka pilihan kabupaten atau kota"
                                    @click="showKabupatenDropdown = !showKabupatenDropdown"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 9-7 7-7-7"/></svg>
                                </button>
                                <div v-if="showKabupatenDropdown && provinsi !== 'all'" class="absolute z-30 mt-1 max-h-56 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-lg">
                                    <button
                                        type="button"
                                        class="block w-full rounded-lg px-3 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                        @mousedown.prevent="selectKabupaten({ value: 'all', label: 'Semua Kabupaten / Kota' })"
                                    >
                                        Semua Kabupaten / Kota
                                    </button>
                                    <button
                                        v-for="option in filteredKabupatenOptions"
                                        :key="option.provinsi + '-' + option.value"
                                        type="button"
                                        class="block w-full rounded-lg px-3 py-2 text-left text-xs text-slate-700 hover:bg-emerald-50"
                                        @mousedown.prevent="selectKabupaten(option)"
                                    >
                                        {{ option.label }}
                                    </button>
                                    <p v-if="filteredKabupatenOptions.length === 0" class="px-3 py-2 text-xs text-slate-500">Kabupaten/kota tidak ditemukan.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Filter perusahaan berdasarkan nama head office master. -->
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Head Office</label>
                            <select
                                v-model="headOffice"
                                @change="applyFilters(1)"
                                class="w-full text-xs font-semibold bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0D542B]/20 focus:border-[#0D542B] transition-all cursor-pointer"
                            >
                                <option value="all">Semua Head Office</option>
                                <option v-for="option in filterOptions.head_office" :key="option" :value="option">
                                    {{ option }}
                                </option>
                            </select>
                        </div>

                        <!-- Filter frasa skills yang berasal dari profil alumni atau LinkedIn. -->
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Skills</label>
                            <select
                                v-model="skills"
                                @change="applyFilters(1)"
                                class="w-full text-xs font-semibold bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0D542B]/20 focus:border-[#0D542B] transition-all cursor-pointer"
                            >
                                <option value="all">Semua Skills</option>
                                <option v-for="option in filterOptions.skills" :key="option" :value="option">
                                    {{ option }}
                                </option>
                            </select>
                        </div>

                        <!-- Filter rumpun bidang pekerjaan. -->
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Bidang</label>
                            <select
                                v-model="bidang"
                                @change="applyFilters(1)"
                                class="w-full text-xs font-semibold bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0D542B]/20 focus:border-[#0D542B] transition-all cursor-pointer"
                            >
                                <option value="all">Semua Bidang</option>
                                <option v-for="option in filterOptions.bidang" :key="option" :value="option">
                                    {{ option }}
                                </option>
                            </select>
                        </div>

                        <!-- Filter unit atau divisi tempat alumni bekerja. -->
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Unit / Divisi</label>
                            <select
                                v-model="unit"
                                @change="applyFilters(1)"
                                class="w-full text-xs font-semibold bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0D542B]/20 focus:border-[#0D542B] transition-all cursor-pointer"
                            >
                                <option value="all">Semua Unit / Divisi</option>
                                <option v-for="option in filterOptions.unit" :key="option" :value="option">
                                    {{ option }}
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
                    <div class="overflow-x-auto border-t border-slate-200">
                        <div class="flex items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-2 text-[11px] font-semibold text-slate-500">
                            <span>Geser ke samping untuk melihat kolom lengkap.</span>
                            <span class="hidden shrink-0 text-slate-400 sm:inline">Aksi tetap terlihat di sisi kanan.</span>
                        </div>
                        <table class="directory-table min-w-[1900px] w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200/80 text-[11px] font-bold text-slate-600 uppercase tracking-wider">
                                    <th class="py-3 px-4 w-12 text-center">No</th>
                                    <th class="py-3 px-4 min-w-[200px]">Mahasiswa / Alumni</th>
                                    <th class="py-3 px-4 min-w-[150px]">Program Studi</th>
                                    <th class="py-3 px-4 min-w-[230px]">Perusahaan &amp; Lokasi</th>
                                    <th class="py-3 px-4 min-w-[220px]">Pekerjaan &amp; Posisi</th>
                                    <th class="py-3 px-4 min-w-[220px]">Skills</th>
                                    <th class="py-3 px-4 min-w-[210px]">Experience / Aktivitas</th>
                                    <th class="py-3 px-4 min-w-[130px]">Target Kelulusan</th>
                                    <th class="py-3 px-4 min-w-[220px]">Status Kelengkapan</th>
                                    <th class="py-3 px-4 min-w-[150px]">Sinkronisasi LinkedIn</th>
                                    <th class="sticky right-0 z-20 w-44 min-w-[176px] bg-slate-50 py-3 px-4 text-center">Aksi</th>
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
                                            <button
                                                type="button"
                                                class="w-10 h-10 overflow-hidden rounded-full bg-emerald-50 text-[#0D542B] font-bold text-xs flex items-center justify-center shrink-0 border border-emerald-200/60 shadow-2xs"
                                                :class="item.foto ? 'cursor-zoom-in' : 'cursor-default'"
                                                :title="item.foto ? 'Lihat foto profil' : 'Belum ada foto profil'"
                                                @click="openPhotoPreview(item)"
                                            >
                                                <img v-if="item.foto" :src="item.foto" :alt="`Foto ${item.nama || 'alumni'}`" class="h-full w-full object-contain" />
                                                <span v-else>{{ getInitials(item.nama) }}</span>
                                            </button>
                                            <div class="min-w-0">
                                                <Link 
                                                    :href="`/${prefix}/alumni/${item.id}`"
                                                    class="font-bold text-slate-900 hover:text-[#0D542B] transition-colors truncate block text-xs"
                                                >
                                                    {{ item.nama || 'Mahasiswa' }}
                                                </Link>
                                                <div class="text-[11px] text-slate-600 font-mono tracking-tight mt-0.5">
                                                    <span>{{ item.nim }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Program Studi -->
                                    <td class="py-3.5 px-4">
                                        <div class="font-semibold text-slate-800 text-xs truncate max-w-[160px]">
                                            {{ item.nama_prodi || item.prodi || '-' }}
                                        </div>
                                        <div class="text-[11px] text-slate-600 truncate max-w-[160px]">
                                            {{ item.nama_fakultas || item.fakultas || '-' }}
                                        </div>
                                    </td>

                                    <!-- Perusahaan menampilkan head office master dan domisili alumni secara terpisah. -->
                                    <td class="py-3.5 px-4">
                                        <div class="space-y-1 text-xs">
                                            <div class="truncate max-w-[230px]" :title="item.nama_perusahaan || item.perusahaan || ''">
                                                <span class="font-semibold text-slate-500">Head office:</span>
                                                <span class="font-semibold text-slate-800"> {{ item.nama_perusahaan || item.perusahaan || '-' }}</span>
                                            </div>
                                            <div class="truncate max-w-[230px]" :title="item.domisili || ''">
                                                <span class="font-semibold text-slate-500">Lokasi saat ini:</span>
                                                <span class="text-slate-700"> {{ item.domisili || '-' }}</span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Bidang, unit, dan posisi jabatan diringkas dalam satu konteks visual. -->
                                    <td class="py-3.5 px-4">
                                        <div class="space-y-1 text-xs">
                                            <div class="truncate max-w-[220px]" :title="item.bidang_pekerjaan || ''">
                                                <span class="font-semibold text-slate-500">Bidang:</span>
                                                <span class="text-slate-700"> {{ item.bidang_pekerjaan || '-' }}</span>
                                            </div>
                                            <div class="truncate max-w-[220px]" :title="item.divisi || ''">
                                                <span class="font-semibold text-slate-500">Unit:</span>
                                                <span class="text-slate-700"> {{ item.divisi || '-' }}</span>
                                            </div>
                                            <div class="truncate max-w-[220px]" :title="item.posisi_jabatan || item.posisi_wiraswasta || ''">
                                                <span class="font-semibold text-slate-500">Posisi:</span>
                                                <span class="text-slate-700"> {{ item.posisi_jabatan || item.posisi_wiraswasta || '-' }}</span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Skills ditampilkan sebagai frasa utuh, bukan dipotong per kata. -->
                                    <td class="py-3.5 px-4">
                                        <div v-if="item.skills_list?.length" class="flex max-w-[260px] flex-wrap gap-1">
                                            <span
                                                v-for="skill in item.skills_list.slice(0, 6)"
                                                :key="skill"
                                                class="rounded-md border border-slate-200 bg-slate-50 px-1.5 py-0.5 text-[10px] font-medium text-slate-700"
                                            >
                                                {{ skill }}
                                            </span>
                                            <span v-if="item.skills_list.length > 6" class="px-1.5 py-0.5 text-[10px] text-slate-500">
                                                +{{ item.skills_list.length - 6 }} lainnya
                                            </span>
                                        </div>
                                        <span v-else class="text-xs text-slate-500">-</span>
                                    </td>

                                    <!-- Experience menyesuaikan status bekerja, studi lanjut, atau wiraswasta. -->
                                    <td class="py-3.5 px-4">
                                        <div class="max-w-[210px] text-xs" :title="careerSummary(item)[1]">
                                            <div class="font-semibold text-slate-500">{{ careerSummary(item)[0] }}:</div>
                                            <div class="mt-0.5 line-clamp-2 text-slate-700">{{ careerSummary(item)[1] }}</div>
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
                                        <div class="flex items-center gap-1.5 whitespace-nowrap">
                                            <span 
                                                class="px-2 py-0.5 text-[10px] font-bold rounded-md"
                                                :class="(item.univ_percentage ?? item.progress?.questionnaire?.percentage ?? 0) >= 100 ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-slate-100 text-slate-700'"
                                            >
                                                Univ: {{ item.univ_percentage ?? item.progress?.questionnaire?.percentage ?? 0 }}%
                                            </span>
                                            <span
                                                class="px-2 py-0.5 text-[10px] font-bold rounded-md"
                                                :class="(item.total_prodi_questions ?? item.progress?.prodi?.total_questions ?? 0) > 0 && (item.prodi_percentage ?? item.progress?.prodi?.percentage ?? 0) >= 100 ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-slate-100 text-slate-700'"
                                                :title="(item.total_prodi_questions ?? item.progress?.prodi?.total_questions ?? 0) > 0 ? 'Status pengisian kuesioner Prodi' : 'Belum ada kuesioner Prodi yang ditetapkan'"
                                            >
                                                Prodi: {{ (item.total_prodi_questions ?? item.progress?.prodi?.total_questions ?? 0) > 0 ? `${item.prodi_percentage ?? item.progress?.prodi?.percentage ?? 0}%` : '-' }}
                                            </span>
                                        </div>
                                        <div class="mt-1 flex items-center gap-2 whitespace-nowrap text-[10px] text-slate-600">
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
                                        <button
                                            v-if="item.linkedin_sync?.has_sync || item.linkedin_sync_status"
                                            type="button"
                                            class="mt-1 text-[10px] font-bold text-[#0D542B] underline decoration-dotted underline-offset-2 hover:text-emerald-800"
                                            @click="openLinkedInHistoryModal(item)"
                                        >
                                            Lihat riwayat ({{ item.linkedin_sync?.sync_count || 0 }})
                                        </button>
                                    </td>

                                    <!-- Aksi -->
                                    <td class="sticky right-0 z-10 w-44 min-w-[176px] bg-white py-3.5 px-4 text-center shadow-[-8px_0_12px_-12px_rgba(15,23,42,0.45)] group-hover:bg-slate-50">
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


                                            <!-- Email Button -->
                                            <button
                                                type="button"
                                                @click="handleSendEmail(item)"
                                                class="p-1.5 text-slate-600 hover:text-[#0D542B] hover:bg-emerald-50 rounded-lg transition-colors cursor-pointer"
                                                title="Kirim email pengingat kuesioner"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6.75A2.25 2.25 0 015.25 4.5h13.5A2.25 2.25 0 0121 6.75v10.5a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 17.25V6.75zm1.5.25 6.557 5.245a1.5 1.5 0 001.886 0L19.5 7"/></svg>
                                            </button>

                                            <!-- LinkedIn tetap menjadi aksi sinkronisasi pada direktori alumni. -->
                                            <button
                                                type="button"
                                                @click.prevent="handleSyncLinkedIn(item)"
                                                class="p-1.5 text-slate-600 hover:text-[#0077B5] hover:bg-blue-50 rounded-lg transition-colors cursor-pointer"
                                                title="Sinkronkan profil LinkedIn"
                                            >
                                                <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/>
                                                </svg>
                                            </button>

                                        </div>
                                    </td>
                                </tr>

                                <!-- Empty State -->
                                <tr v-if="paginatedAlumnis.length === 0">
                                    <td colspan="9" class="py-12 text-center text-slate-600">
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

        <!-- MODAL PREVIEW FOTO ALUMNI -->
        <div v-if="showPhotoPreview" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4" @click.self="showPhotoPreview = false">
            <div class="relative w-full max-w-md rounded-2xl bg-white p-4 shadow-2xl">
                <button
                    type="button"
                    class="absolute right-3 top-3 z-10 rounded-full bg-white/90 px-2.5 py-1 text-lg leading-none text-slate-600 shadow-sm hover:text-slate-900"
                    title="Tutup preview foto"
                    @click="showPhotoPreview = false"
                >
                    &times;
                </button>
                <img
                    :src="activePhotoAlumni?.foto"
                    :alt="`Foto ${activePhotoAlumni?.nama || 'alumni'}`"
                    class="max-h-[70vh] w-full rounded-xl object-contain"
                />
                <p class="mt-3 text-center text-sm font-bold text-slate-900">{{ activePhotoAlumni?.nama }}</p>
            </div>
        </div>

        <!-- MODAL RIWAYAT LINKEDIN -->
        <div v-if="showLinkedInHistoryModal" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 flex items-center justify-center p-4 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-2xl w-full p-6 shadow-2xl border border-slate-200 relative animate-in fade-in zoom-in-95 duration-150 max-h-[85vh] flex flex-col">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                    <div class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-xl bg-blue-100 text-[#0077B5] flex items-center justify-center font-bold">
                            in
                        </span>
                        <div>
                            <h3 class="text-sm font-extrabold text-slate-900">Riwayat Sinkronisasi LinkedIn</h3>
                            <p class="text-[11px] text-slate-500">{{ activeLinkedInAlumni?.nama }} ({{ activeLinkedInAlumni?.nim }})</p>
                        </div>
                    </div>
                    <button @click="showLinkedInHistoryModal = false" class="text-slate-400 hover:text-slate-600 p-1 text-lg leading-none cursor-pointer">
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
                                <div v-if="item.preview.lokasi" class="text-[11px] text-slate-400">Lokasi: {{ item.preview.lokasi }}</div>
                            </div>

                            <div v-if="item.reviewed_at" class="text-[10px] text-slate-500 flex items-center justify-between pt-1 border-t border-slate-200/60">
                                <span>Ditinjau oleh: <b>{{ item.reviewer_name }}</b></span>
                                <span>Waktu tinjau: {{ item.reviewed_at }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-100 flex justify-end">
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

<style scoped>
.directory-table th,
.directory-table td {
    border-right: 1px solid rgb(226 232 240);
}

.directory-table th:last-child,
.directory-table td:last-child {
    border-right: 0;
}
</style>

