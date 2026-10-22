<!--
  Halaman Direktori & Sinkronisasi LinkedIn Alumni
  File: resources/js/Pages/SuperAdmin/LinkedIn/Index.vue

  Mendukung Akses Terpadu Multi-Peran:
  1. Super Admin   : Akses lintas universitas (seluruh fakultas & prodi)
  2. Admin Fakultas: Akses terikat pada prodi-prodi di fakultas yang dinaungi
  3. Admin Prodi   : Akses khusus program studi yang bersangkutan

  Fitur Utama:
  - Tampilan selaras & serasi dengan halaman Direktori Alumni (/superadmin/alumni)
  - Solid Hijau UKDW (#0D542B) dan Kuning UKDW (#FDC700)
  - Filter lengkap: Pencarian Nama/NIM, Tahun Kelulusan, Target Periode, Semester, Status Sinkronisasi, dan Prodi
  - Modal Peninjauan (Review Approval) bersih tanpa emoticon anak kecil (ikon profesional SVG)
  - Sinkronisasi instan per-alumni (in-place) & sinkronisasi massal (bulk)
  - Modal histori snapshot lengkap hasil sinkronisasi
-->
<script setup>
import { Head, router, Link } from '@inertiajs/vue3';
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import Swal from 'sweetalert2';

// Import komponen Sidebar sesuai peran masing-masing
import SidebarSuperAdmin from '../Components/Sidebar.vue';
import SidebarFakultas from '../../AdminFakultas/Components/Sidebar.vue';
import SidebarProdi from '../../AdminProdi/Components/Sidebar.vue';

const props = defineProps({
    alumnis: {
        type: Array,
        default: () => [],
    },
    daftarTahun: {
        type: Array,
        default: () => [],
    },
    tahunTerpilih: {
        type: String,
        default: '',
    },
    daftarTarget: {
        type: Array,
        default: () => [],
    },
    prodis: {
        type: Array,
        default: () => [],
    },
    provider: {
        type: String,
        default: 'official',
    },
    targetAlumniId: {
        type: [Number, String],
        default: null,
    },
    role: {
        type: String,
        default: 'superadmin',
    },
    baseRoute: {
        type: String,
        default: '/superadmin/linkedin-sync',
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    stats: {
        type: Object,
        default: () => ({
            total_alumni: 0,
            dengan_linkedin: 0,
            berhasil_sinkron: 0,
            gagal_sinkron: 0,
            dilewati: 0,
            pending_review: 0,
            belum_sinkron: 0,
            persentase_sinkron: 0,
        }),
    },
});

// =========================================================================
// STATE FILTER & PENCARIAN (SELARAS DENGAN HALAMAN DATA ALUMNI)
// =========================================================================
const defaultTahun = props.filters.tahun || props.tahunTerpilih || props.daftarTahun[0] || '';
const search = ref(props.filters.search || '');
const selectedTahun = ref(defaultTahun);
const selectedSemester = ref(props.filters.semester || 'all');
const selectedTarget = ref(props.filters.target || 'all');
const selectedStatus = ref(props.filters.status || 'all');
const selectedStatusPlot = ref(props.filters.status_plot || 'all');
const selectedProdiId = ref(props.filters.prodi_id || 'all');
const highlightedAlumniId = ref(props.targetAlumniId ? Number(props.targetAlumniId) : null);

// Data Alumni Lokal (Reaktif untuk update status sinkronisasi tanpa reload halaman)
const alumniList = ref(props.alumnis.map(item => ({
    ...item,
    isSyncing: false,
})));

// Metrik Statistik Lokal
const localStats = ref({ ...props.stats });

// Sinkronkan data reaktif lokal jika server mengembalikan props baru (misal setelah filter tahun/prodi diterapkan)
watch(() => props.alumnis, (newAlumnis) => {
    alumniList.value = (newAlumnis || []).map(item => ({
        ...item,
        isSyncing: false,
    }));
}, { deep: true });

watch(() => props.stats, (newStats) => {
    localStats.value = { ...newStats };
}, { deep: true });

watch(() => props.filters, (newFilters) => {
    if (newFilters) {
        if (newFilters.tahun !== undefined) {
            selectedTahun.value = newFilters.tahun;
        }
        if (newFilters.semester !== undefined) {
            selectedSemester.value = newFilters.semester || 'all';
        }
        if (newFilters.target !== undefined) {
            selectedTarget.value = newFilters.target || 'all';
        }
        if (newFilters.status !== undefined) {
            selectedStatus.value = newFilters.status || 'all';
        }
        if (newFilters.status_plot !== undefined) {
            selectedStatusPlot.value = newFilters.status_plot || 'all';
        }
        if (newFilters.prodi_id !== undefined && props.role !== 'admin_prodi') {
            selectedProdiId.value = newFilters.prodi_id || 'all';
        }
    }
}, { deep: true });

// State Bulk Sync
const isBulkSyncing = ref(false);
const bulkProgress = ref({ current: 0, total: 0, percentage: 0 });

// State Modal Peninjauan (Review Approval Apify)
const isReviewModalOpen = ref(false);
const isReviewLoading = ref(false);
const isActionLoading = ref(false);
const activeReview = ref(null);
const activeAlumni = ref(null);
const activeReviewTab = ref('structured'); // 'structured' | 'raw_json'

// Form Approval Bersih (Bisa disesuaikan oleh admin sebelum disimpan)
const approveForm = ref({
    tipe_pekerjaan: 'pekerja', // 'pekerja' | 'wirausaha'
    posisi_jabatan: '',
    posisi_wiraswasta: '',
    nama_perusahaan: '',
    skills: '',
    sync_foto: true,
});

// State Modal Histori Sinkronisasi
const isHistoryModalOpen = ref(false);
const isHistoryLoading = ref(false);
const historyList = ref([]);
const historyAlumni = ref(null);

// Pagination Lokal ala DataTables
const perPage = ref(10);
const currentPage = ref(1);

// Filter data alumni di frontend berdasarkan input pencarian jika ada
const filteredAlumni = computed(() => {
    let result = alumniList.value;

    if (search.value && search.value.trim()) {
        const query = search.value.toLowerCase().trim();
        result = result.filter(item =>
            (item.nim && item.nim.toLowerCase().includes(query)) ||
            (item.nama && item.nama.toLowerCase().includes(query)) ||
            (item.linkedin_username && item.linkedin_username.toLowerCase().includes(query)) ||
            (item.linkedin_url && item.linkedin_url.toLowerCase().includes(query)) ||
            (item.perusahaan && item.perusahaan.toLowerCase().includes(query)) ||
            (item.posisi && item.posisi.toLowerCase().includes(query)) ||
            (item.nama_linkedin && item.nama_linkedin.toLowerCase().includes(query)) ||
            (item.perusahaan_linkedin && item.perusahaan_linkedin.toLowerCase().includes(query)) ||
            (item.posisi_linkedin && item.posisi_linkedin.toLowerCase().includes(query))
        );
    }

    if (selectedStatusPlot.value && selectedStatusPlot.value !== 'all') {
        if (selectedStatusPlot.value === 'ready') {
            result = result.filter(item => item.status_plot_label === 'Data Sama / Siap Diplot');
        } else if (selectedStatusPlot.value === 'nama_sesuai') {
            result = result.filter(item => item.is_nama_sesuai);
        } else if (selectedStatusPlot.value === 'nama_berbeda') {
            result = result.filter(item => !item.is_nama_sesuai && item.latest_sync_result);
        } else if (selectedStatusPlot.value === 'terplot') {
            result = result.filter(item => item.is_terplot_ke_biodata);
        }
    }

    return result;
});

const totalPages = computed(() => {
    return Math.ceil(filteredAlumni.value.length / perPage.value) || 1;
});

const paginatedAlumni = computed(() => {
    const start = (currentPage.value - 1) * perPage.value;
    return filteredAlumni.value.slice(start, start + perPage.value);
});

const goToPage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
};

// =========================================================================
// TERAPKAN FILTER KE SERVER (SINKRON KE URL)
// =========================================================================
let searchDebounceTimeout = null;

const applyFilters = () => {
    currentPage.value = 1;
    highlightedAlumniId.value = null;

    router.get(props.baseRoute, {
        search: search.value || undefined,
        tahun: selectedTahun.value || undefined,
        semester: selectedSemester.value !== 'all' ? selectedSemester.value : undefined,
        target: selectedTarget.value !== 'all' ? selectedTarget.value : undefined,
        status: selectedStatus.value !== 'all' ? selectedStatus.value : undefined,
        status_plot: selectedStatusPlot.value !== 'all' ? selectedStatusPlot.value : undefined,
        prodi_id: (props.role === 'admin_prodi' ? undefined : (selectedProdiId.value !== 'all' ? selectedProdiId.value : undefined)),
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const handleSearchInput = () => {
    clearTimeout(searchDebounceTimeout);
    searchDebounceTimeout = setTimeout(() => {
        applyFilters();
    }, 400);
};

const resetFilters = () => {
    search.value = '';
    selectedTahun.value = props.daftarTahun[0] || '';
    selectedSemester.value = 'all';
    selectedTarget.value = 'all';
    selectedStatus.value = 'all';
    selectedStatusPlot.value = 'all';
    selectedProdiId.value = props.role === 'admin_prodi' ? (props.filters.prodi_id || 'all') : 'all';
    currentPage.value = 1;

    router.get(props.baseRoute, {
        tahun: selectedTahun.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Auto scroll ke alumni tertentu jika dialihkan dari detail alumni
onMounted(() => {
    if (highlightedAlumniId.value) {
        const targetIndex = filteredAlumni.value.findIndex(a => a.id === highlightedAlumniId.value);
        if (targetIndex !== -1) {
            currentPage.value = Math.floor(targetIndex / perPage.value) + 1;
        }

        nextTick(() => {
            const el = document.getElementById(`alumni-row-${highlightedAlumniId.value}`);
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    }
});

// Token CSRF untuk request fetch
const getCsrfToken = () => {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
};

const showToast = (icon, title) => {
    Swal.fire({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        icon,
        title,
    });
};

// Hitung ulang metrik lokal setelah sinkronisasi/approval
const recalculateLocalStats = () => {
    let dengan = 0;
    let berhasil = 0;
    let gagal = 0;
    let pending = 0;

    alumniList.value.forEach(item => {
        if (item.linkedin_url || item.linkedin_username) dengan++;
        if (item.status_sync === 'Approved' || item.status_sync === 'Berhasil') berhasil++;
        if (item.status_sync === 'Rejected' || item.status_sync === 'Gagal') gagal++;
        if (item.status_sync === 'Pending Review') pending++;
    });

    localStats.value.dengan_linkedin = dengan;
    localStats.value.berhasil_sinkron = berhasil;
    localStats.value.gagal_sinkron = gagal;
    localStats.value.pending_review = pending;
    localStats.value.persentase_sinkron = localStats.value.total_alumni > 0
        ? Math.round((berhasil / localStats.value.total_alumni) * 100)
        : 0;
};

// =========================================================================
// SINKRONISASI INDIVIDUAL (ASYNC TANPA RELOAD HALAMAN)
// =========================================================================
const syncSingle = async (alumni) => {
    if (alumni.isSyncing) return;

    if (props.provider === 'apify') {
        if (!alumni.linkedin_url) {
            showToast('warning', 'URL LinkedIn belum diisi pada profil alumni ini.');
            return;
        }
    } else {
        if (!alumni.linkedin_username) {
            showToast('warning', 'Username LinkedIn belum tersedia pada profil alumni ini.');
            return;
        }
    }

    alumni.isSyncing = true;
    alumni.status_sync = 'Menyinkronkan...';
    alumni.status_color = 'indigo';

    try {
        const response = await fetch(`${props.baseRoute}/${alumni.id}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
        });

        const data = await response.json();

        if (response.ok && data.success) {
            if (props.provider === 'apify') {
                alumni.status_sync = 'Pending Review';
                alumni.status_color = 'amber';
                alumni.terakhir_sync = data.alumni.terakhir_sync;
                alumni.latest_sync_result = data.alumni.latest_sync_result;
                if (data.alumni.keterangan_nama) alumni.keterangan_nama = data.alumni.keterangan_nama;
                if (data.alumni.status_plot_label) alumni.status_plot_label = data.alumni.status_plot_label;
                if (data.alumni.nama_linkedin) alumni.nama_linkedin = data.alumni.nama_linkedin;
                if (data.alumni.posisi_linkedin) alumni.posisi_linkedin = data.alumni.posisi_linkedin;
                if (data.alumni.perusahaan_linkedin) alumni.perusahaan_linkedin = data.alumni.perusahaan_linkedin;
                if (data.alumni.is_nama_sesuai !== undefined) alumni.is_nama_sesuai = data.alumni.is_nama_sesuai;
                if (data.alumni.is_terplot_ke_biodata !== undefined) alumni.is_terplot_ke_biodata = data.alumni.is_terplot_ke_biodata;

                showToast('success', `${alumni.nama}: ${data.message}`);
                recalculateLocalStats();

                if (data.sync_result_id) {
                    openReviewModal(data.sync_result_id, alumni);
                }
            } else {
                alumni.perusahaan = data.alumni.perusahaan;
                alumni.posisi = data.alumni.posisi;
                alumni.status_sync = data.alumni.status_sync;
                alumni.status_color = data.alumni.status_color;
                alumni.terakhir_sync = data.alumni.terakhir_sync;
                alumni.terakhir_sync_human = data.alumni.terakhir_sync_human;

                showToast('success', `${alumni.nama}: ${data.message}`);
                recalculateLocalStats();
            }
        } else {
            alumni.status_sync = 'Gagal';
            alumni.status_color = 'rose';
            showToast('error', `${alumni.nama}: ${data.message || 'Sinkronisasi gagal.'}`);
            recalculateLocalStats();
        }
    } catch (err) {
        alumni.status_sync = 'Gagal';
        alumni.status_color = 'rose';
        showToast('error', `${alumni.nama}: Terjadi gangguan jaringan.`);
        recalculateLocalStats();
    } finally {
        alumni.isSyncing = false;
    }
};

// =========================================================================
// PENINJAUAN & APPROVAL STAGING (PROVIDER APIFY) - BEBAS EMOTICON
// =========================================================================
const copyRawJson = (raw) => {
    if (!raw) return;
    navigator.clipboard.writeText(JSON.stringify(raw, null, 2));
    showToast('success', 'Data JSON mentah berhasil disalin ke clipboard.');
};

const addSkillToForm = (skillName) => {
    if (!skillName) return;
    const current = approveForm.value.skills ? approveForm.value.skills.split(',').map(s => s.trim()).filter(Boolean) : [];
    if (!current.includes(skillName)) {
        current.push(skillName);
        approveForm.value.skills = current.join(', ');
    }
};

const openReviewModal = async (syncResultId, alumni) => {
    isReviewModalOpen.value = true;
    isReviewLoading.value = true;
    activeReview.value = null;
    activeAlumni.value = alumni;
    activeReviewTab.value = 'structured';

    try {
        const response = await fetch(`${props.baseRoute}/results/${syncResultId}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const data = await response.json();
        if (response.ok && data.success) {
            activeReview.value = data.result;
            approveForm.value.tipe_pekerjaan = 'pekerja';
            approveForm.value.posisi_jabatan = data.result.preview?.current_job || '';
            approveForm.value.posisi_wiraswasta = '';
            approveForm.value.nama_perusahaan = data.result.preview?.current_company || '';
            approveForm.value.sync_foto = !!data.result.preview?.profile_picture;

            // Inisialisasi Skills: Prioritas dari preview.skills, lalu suggested_skills, lalu data saat ini
            let initialSkills = '';
            if (Array.isArray(data.result.preview?.skills) && data.result.preview.skills.length > 0) {
                initialSkills = data.result.preview.skills.join(', ');
            } else if (typeof data.result.preview?.skills === 'string' && data.result.preview.skills.trim()) {
                initialSkills = data.result.preview.skills.trim();
            } else if (Array.isArray(data.result.preview?.suggested_skills) && data.result.preview.suggested_skills.length > 0) {
                initialSkills = data.result.preview.suggested_skills.join(', ');
            } else if (data.result.current_data?.skills) {
                initialSkills = data.result.current_data.skills;
            }
            approveForm.value.skills = initialSkills;
        } else {
            showToast('error', data.message || 'Gagal memuat rincian hasil sinkronisasi.');
            isReviewModalOpen.value = false;
        }
    } catch (err) {
        showToast('error', 'Terjadi kesalahan saat memuat data peninjauan.');
        isReviewModalOpen.value = false;
    } finally {
        isReviewLoading.value = false;
    }
};

const handleApprove = async () => {
    if (!activeReview.value || isActionLoading.value) return;

    const isWirausaha = approveForm.value.tipe_pekerjaan === 'wirausaha';
    const targetJabatan = isWirausaha
        ? (approveForm.value.posisi_wiraswasta || approveForm.value.posisi_jabatan || '-')
        : (approveForm.value.posisi_jabatan || '-');
    const targetPerusahaan = approveForm.value.nama_perusahaan || '-';
    const targetSkills = approveForm.value.skills?.trim() || '-';

    const result = await Swal.fire({
        title: 'Setujui & Terapkan Data?',
        html: `
            <div class="text-left text-xs text-slate-700 space-y-2.5">
                <p>Data berikut akan langsung diterapkan ke profil data utama alumni:</p>
                <div class="bg-slate-50 p-3 rounded-lg border border-slate-200 space-y-1.5 text-slate-800">
                    <p><span class="text-slate-500 font-medium">Kategori:</span> <b>${isWirausaha ? 'Wiraswasta / Usaha' : 'Karyawan / Pekerja'}</b></p>
                    <p><span class="text-slate-500 font-medium">${isWirausaha ? 'Posisi Usaha' : 'Posisi / Jabatan'}:</span> <b>${targetJabatan}</b></p>
                    <p><span class="text-slate-500 font-medium">Perusahaan / Usaha:</span> <b>${targetPerusahaan}</b></p>
                    <p><span class="text-slate-500 font-medium">Keahlian (Skills):</span> <b>${targetSkills}</b></p>
                </div>
                <div class="flex items-start gap-2 bg-emerald-50 text-emerald-900 p-2.5 rounded-lg border border-emerald-200">
                    <span class="font-bold shrink-0">Catatan:</span>
                    <span>Data jabatan, perusahaan, dan keahlian di database alumni akan diperbarui sesuai hasil peninjauan ini.</span>
                </div>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0D542B',
        cancelButtonColor: '#9CA3AF',
        confirmButtonText: 'Ya, Setujui & Simpan',
        cancelButtonText: 'Batal',
        reverseButtons: true,
    });

    if (!result.isConfirmed) return;

    isActionLoading.value = true;
    try {
        const response = await fetch(`${props.baseRoute}/results/${activeReview.value.id}/approve`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({
                tipe_pekerjaan: approveForm.value.tipe_pekerjaan,
                posisi_jabatan: approveForm.value.posisi_jabatan,
                posisi_wiraswasta: approveForm.value.posisi_wiraswasta,
                nama_perusahaan: approveForm.value.nama_perusahaan,
                skills: approveForm.value.skills,
                sync_foto: approveForm.value.sync_foto,
            }),
        });

        const data = await response.json();

        if (response.ok && data.success) {
            if (activeAlumni.value) {
                activeAlumni.value.perusahaan = data.alumni.perusahaan;
                activeAlumni.value.posisi = data.alumni.posisi;
                if (data.alumni.linkedin_username) activeAlumni.value.linkedin_username = data.alumni.linkedin_username;
                activeAlumni.value.status_sync = 'Approved';
                activeAlumni.value.status_color = 'emerald';
                activeAlumni.value.latest_sync_result = data.alumni.latest_sync_result;
                if (data.alumni.keterangan_nama) activeAlumni.value.keterangan_nama = data.alumni.keterangan_nama;
                if (data.alumni.status_plot_label) activeAlumni.value.status_plot_label = data.alumni.status_plot_label;
                if (data.alumni.nama_linkedin) activeAlumni.value.nama_linkedin = data.alumni.nama_linkedin;
                if (data.alumni.posisi_linkedin) activeAlumni.value.posisi_linkedin = data.alumni.posisi_linkedin;
                if (data.alumni.perusahaan_linkedin) activeAlumni.value.perusahaan_linkedin = data.alumni.perusahaan_linkedin;
                if (data.alumni.is_nama_sesuai !== undefined) activeAlumni.value.is_nama_sesuai = data.alumni.is_nama_sesuai;
                if (data.alumni.is_terplot_ke_biodata !== undefined) activeAlumni.value.is_terplot_ke_biodata = data.alumni.is_terplot_ke_biodata;
            }

            isReviewModalOpen.value = false;
            recalculateLocalStats();
            Swal.fire({
                title: 'Berhasil Disetujui',
                text: data.message,
                icon: 'success',
                confirmButtonColor: '#0D542B',
            });
        } else {
            showToast('error', data.message || 'Gagal menyetujui data.');
        }
    } catch (err) {
        showToast('error', 'Terjadi kesalahan sistem saat menyetujui data.');
    } finally {
        isActionLoading.value = false;
    }
};

const handleReject = async () => {
    if (!activeReview.value || isActionLoading.value) return;

    const result = await Swal.fire({
        title: 'Tolak Hasil Sinkronisasi?',
        text: 'Data utama alumni tidak akan diubah. Hasil scraping akan tetap tersimpan sebagai histori dengan status Ditolak.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#DC2626',
        cancelButtonColor: '#9CA3AF',
        confirmButtonText: 'Ya, Tolak Hasil Ini',
        cancelButtonText: 'Batal',
        reverseButtons: true,
    });

    if (!result.isConfirmed) return;

    isActionLoading.value = true;
    try {
        const response = await fetch(`${props.baseRoute}/results/${activeReview.value.id}/reject`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
        });

        const data = await response.json();

        if (response.ok && data.success) {
            if (activeAlumni.value) {
                activeAlumni.value.status_sync = 'Rejected';
                activeAlumni.value.status_color = 'rose';
                activeAlumni.value.latest_sync_result = data.alumni.latest_sync_result;
            }

            isReviewModalOpen.value = false;
            recalculateLocalStats();
            Swal.fire({
                title: 'Hasil Ditolak',
                text: data.message,
                icon: 'info',
                confirmButtonColor: '#0D542B',
            });
        } else {
            showToast('error', data.message || 'Gagal menolak hasil.');
        }
    } catch (err) {
        showToast('error', 'Terjadi gangguan sistem saat menolak hasil.');
    } finally {
        isActionLoading.value = false;
    }
};

// =========================================================================
// HISTORI SINKRONISASI
// =========================================================================
const openHistoryModal = async (alumni) => {
    isHistoryModalOpen.value = true;
    isHistoryLoading.value = true;
    historyAlumni.value = alumni;
    historyList.value = [];

    try {
        const response = await fetch(`${props.baseRoute}/alumni/${alumni.id}/history`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const data = await response.json();
        if (response.ok && data.success) {
            historyList.value = data.history || [];
        } else {
            showToast('error', data.message || 'Gagal memuat histori sinkronisasi.');
        }
    } catch (err) {
        showToast('error', 'Terjadi kesalahan sistem saat mengambil histori.');
    } finally {
        isHistoryLoading.value = false;
    }
};

// Helper teks role untuk breadcrumb & header
const roleTitle = computed(() => {
    if (props.role === 'admin_prodi') return 'Program Studi';
    if (props.role === 'admin_fakultas') return 'Fakultas';
    return 'Super Admin';
});

const dashboardRoute = computed(() => {
    if (props.role === 'admin_prodi') return '/prodi/dashboard';
    if (props.role === 'admin_fakultas') return '/fakultas/dashboard';
    return '/superadmin/dashboard';
});
</script>

<template>
    <Head :title="`Sinkronisasi LinkedIn - ${roleTitle}`" />

    <div class="min-h-screen bg-[#f8fafc] text-gray-800 font-sans flex">
        <!-- Sidebar Adaptif Sesuai Role Pengguna -->
        <SidebarSuperAdmin v-if="role === 'superadmin' || !role" activeMenu="linkedin-sync" />
        <SidebarFakultas v-else-if="role === 'admin_fakultas'" />
        <SidebarProdi v-else-if="role === 'admin_prodi'" />

        <!-- Area Konten Utama -->
        <div class="flex-1 flex flex-col min-w-0 lg:pl-72">

            <!-- Header Solid Hijau Resmi UKDW #0D542B (Serasi dengan superadmin/alumni) -->
            <header class="admin-page-header bg-[#0D542B] text-white pt-8 pb-16 px-4 sm:px-6 lg:px-8">
                <div class="w-full max-w-[1400px] mx-auto flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div>
                        <div class="flex items-center gap-2 text-xs text-white/80 font-medium mb-2">
                            <Link :href="dashboardRoute" class="hover:underline">Dashboard</Link>
                            <span>/</span>
                            <span>{{ roleTitle }}</span>
                            <span>/</span>
                            <span class="text-white font-bold">Sinkronisasi LinkedIn</span>
                        </div>

                        <div class="flex items-center gap-3 flex-wrap">
                            <h1 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight">
                                Sinkronisasi Data LinkedIn Alumni
                            </h1>

                            <!-- Badge Mode Provider -->
                            <span v-if="provider === 'apify'" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-white/15 text-white border border-white/20">
                                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                Apify Scraper (Staging & Review)
                            </span>
                            <span v-else class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-white/15 text-white border border-white/20">
                                <span class="w-2 h-2 rounded-full bg-[#FDC700]"></span>
                                Official LinkedIn Provider
                            </span>
                        </div>

                        <p class="text-white/90 text-sm sm:text-base font-normal mt-1 max-w-2xl leading-relaxed">
                            Pemetaan dan penelusuran riwayat karier alumni melalui profil publik LinkedIn secara terstruktur ke dalam database tracer study UKDW.
                        </p>
                    </div>

                    <!-- Ringkasan Cepat di Header -->
                    <div class="bg-white/10 border border-white/20 px-5 py-3 rounded-xl text-left md:text-right text-white">
                        <span class="text-xs text-emerald-100 font-bold uppercase tracking-wider block">Tingkat Sinkronisasi LinkedIn</span>
                        <span class="text-3xl font-extrabold text-white block mt-0.5">{{ localStats.persentase_sinkron }}%</span>
                        <span class="text-xs text-emerald-100/80 font-medium">{{ localStats.berhasil_sinkron }} dari {{ localStats.total_alumni }} Alumni Terhubung</span>
                    </div>
                </div>
            </header>

            <!-- Main Content Container -->
            <main class="admin-page-main w-full max-w-[1440px] mx-auto -mt-10 px-4 sm:px-6 lg:px-8 space-y-6 pb-16">

                <!-- 6 Kartu Statistik Ringkas & Profesional (Formal, Flat, Bersih) -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3.5">
                    <!-- 1. Total Alumni -->
                    <div class="bg-white rounded-xl p-4.5 border border-slate-200 shadow-2xs">
                        <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Total Alumni</p>
                        <p class="text-2xl font-extrabold text-slate-900 tracking-tight mt-1.5">{{ localStats.total_alumni }}</p>
                        <p class="text-xs text-slate-500 mt-1">Lulusan {{ selectedTahun }}</p>
                    </div>

                    <!-- 2. Terhubung LinkedIn -->
                    <div class="bg-white rounded-xl p-4.5 border border-slate-200 shadow-2xs">
                        <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Punya Akun</p>
                        <p class="text-2xl font-extrabold text-slate-900 tracking-tight mt-1.5">{{ localStats.dengan_linkedin }}</p>
                        <p class="text-xs text-slate-500 mt-1">{{ provider === 'apify' ? 'Ada URL Profil' : 'Ada Username' }}</p>
                    </div>

                    <!-- 3. Siap Diplot (Matching) -->
                    <div class="bg-white rounded-xl p-4.5 border border-slate-200 shadow-2xs">
                        <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Siap Diplot</p>
                        <p class="text-2xl font-extrabold text-slate-900 tracking-tight mt-1.5">{{ localStats.siap_diplot || 0 }}</p>
                        <p class="text-xs text-slate-500 mt-1">Data sama / cocok</p>
                    </div>

                    <!-- 4. Nama Sesuai (Validasi) -->
                    <div class="bg-white rounded-xl p-4.5 border border-slate-200 shadow-2xs">
                        <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Nama Cocok</p>
                        <p class="text-2xl font-extrabold text-slate-900 tracking-tight mt-1.5">{{ localStats.nama_sesuai || 0 }}</p>
                        <p class="text-xs text-slate-500 mt-1">Identik / parsial</p>
                    </div>

                    <!-- 5. Menunggu Review (Khusus Apify) -->
                    <div class="bg-white rounded-xl p-4.5 border border-slate-200 shadow-2xs">
                        <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Pending Review</p>
                        <p class="text-2xl font-extrabold text-slate-900 tracking-tight mt-1.5">{{ localStats.pending_review }}</p>
                        <p class="text-xs text-slate-500 mt-1">Menunggu approval</p>
                    </div>

                    <!-- 6. Berhasil / Approved -->
                    <div class="bg-white rounded-xl p-4.5 border border-slate-200 shadow-2xs">
                        <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Approved</p>
                        <p class="text-2xl font-extrabold text-[#0D542B] tracking-tight mt-1.5">{{ localStats.berhasil_sinkron }}</p>
                        <p class="text-xs text-slate-500 mt-1">Terplot ke database</p>
                    </div>
                </div>

                <!-- Panel Filter Komprehensif -->
                <div class="bg-white rounded-xl p-5 shadow-2xs border border-gray-300">
                    <div class="flex items-center justify-between border-b border-gray-200 pb-3.5 mb-4 flex-wrap gap-3">
                        <div class="flex items-center gap-3">
                            <h2 class="text-sm font-extrabold text-gray-900 uppercase tracking-wider">
                                Filter & Pencarian Alumni LinkedIn
                            </h2>
                            <span class="text-xs px-2.5 py-0.5 rounded-full bg-emerald-50 text-[#0D542B] font-bold border border-emerald-200">
                                Tahun Kelulusan: {{ selectedTahun }}
                            </span>
                        </div>
                        <div class="flex items-center gap-4">
                            <button 
                                type="button"
                                @click="resetFilters" 
                                class="text-xs text-gray-500 hover:text-gray-800 font-bold transition-colors cursor-pointer"
                            >
                                Reset Filter
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-7 gap-3.5">
                        <!-- 1. Pencarian Nama / NIM / Akun -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Cari Nama / NIM / LinkedIn</label>
                            <input 
                                type="text" 
                                v-model="search" 
                                @input="handleSearchInput" 
                                placeholder="Ketik nama, NIM, atau URL..." 
                                class="w-full text-xs rounded-lg border border-gray-300 bg-white py-2 px-3 font-medium text-gray-900 focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] outline-none"
                            />
                        </div>

                        <!-- 2. Filter Tahun Kelulusan -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Tahun Kelulusan *</label>
                            <select 
                                v-model="selectedTahun" 
                                @change="applyFilters" 
                                class="w-full text-xs rounded-lg border border-gray-300 bg-white py-2 px-3 font-bold text-gray-900 focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] outline-none cursor-pointer"
                            >
                                <option v-for="t in daftarTahun" :key="t" :value="t">
                                    {{ t }}
                                </option>
                            </select>
                        </div>

                        <!-- 3. Filter Target Kelulusan (Semester + Tahun) -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Target Kelulusan</label>
                            <select 
                                v-model="selectedTarget" 
                                @change="applyFilters" 
                                class="w-full text-xs rounded-lg border border-gray-300 bg-white py-2 px-3 font-medium text-gray-900 focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] outline-none cursor-pointer"
                            >
                                <option value="all">Semua Target</option>
                                <option v-for="tgt in daftarTarget" :key="tgt" :value="tgt">
                                    Target {{ tgt }}
                                </option>
                            </select>
                        </div>

                        <!-- 4. Filter Semester Kelulusan -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Semester</label>
                            <select 
                                v-model="selectedSemester" 
                                @change="applyFilters" 
                                class="w-full text-xs rounded-lg border border-gray-300 bg-white py-2 px-3 font-medium text-gray-900 focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] outline-none cursor-pointer"
                            >
                                <option value="all">Semua Semester</option>
                                <option value="Gasal">Gasal</option>
                                <option value="Genap">Genap</option>
                            </select>
                        </div>

                        <!-- 5. Filter Status Sinkronisasi -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Status Sinkronisasi</label>
                            <select 
                                v-model="selectedStatus" 
                                @change="applyFilters" 
                                class="w-full text-xs rounded-lg border border-gray-300 bg-white py-2 px-3 font-medium text-gray-900 focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] outline-none cursor-pointer"
                            >
                                <option value="all">Semua Status</option>
                                <option value="approved">Approved / Berhasil</option>
                                <option value="pending">Pending Review</option>
                                <option value="belum_sync">Belum Disinkronkan</option>
                                <option value="rejected">Ditolak / Gagal</option>
                                <option value="ada_linkedin">Memiliki Akun LinkedIn</option>
                            </select>
                        </div>

                        <!-- 6. Filter Status Plot & Validasi Nama -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Plotting & Nama</label>
                            <select 
                                v-model="selectedStatusPlot" 
                                @change="applyFilters" 
                                class="w-full text-xs rounded-lg border border-gray-300 bg-white py-2 px-3 font-medium text-gray-900 focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] outline-none cursor-pointer"
                            >
                                <option value="all">Semua Plot & Nama</option>
                                <option value="ready">Siap Diplot (Data Sama)</option>
                                <option value="nama_sesuai">Nama Sesuai (Eksak/Parsial)</option>
                                <option value="nama_berbeda">Nama Berbeda (Perlu Cek)</option>
                                <option value="terplot">Sudah Terplot ke Biodata</option>
                            </select>
                        </div>

                        <!-- 7. Filter Program Studi (Disesuaikan Peran) -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Program Studi</label>
                            <select 
                                v-model="selectedProdiId" 
                                @change="applyFilters" 
                                :disabled="role === 'admin_prodi'"
                                class="w-full text-xs rounded-lg border border-gray-300 bg-white py-2 px-3 font-medium text-gray-900 focus:border-[#0D542B] focus:ring-1 focus:ring-[#0D542B] outline-none disabled:opacity-75 disabled:cursor-not-allowed cursor-pointer"
                            >
                                <option v-if="role !== 'admin_prodi'" value="all">Semua Program Studi</option>
                                <option v-for="p in prodis" :key="p.id" :value="p.id">
                                    {{ p.kode_prodi ? `${p.kode_prodi} - ` : '' }}{{ p.nama_prodi }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Tabel Data Alumni & Status Sinkronisasi LinkedIn -->
                <div class="bg-white rounded-xl shadow-2xs border border-gray-300 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between flex-wrap gap-3">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-gray-700">Daftar Alumni:</span>
                            <span class="text-xs font-extrabold text-[#0D542B] bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded">
                                {{ filteredAlumni.length }} Data Ditemukan
                            </span>
                        </div>
                        <div class="text-xs text-gray-500 font-medium">
                            Menampilkan halaman {{ currentPage }} dari {{ totalPages }}
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-gray-700">
                            <thead class="bg-gray-100 text-gray-800 font-bold uppercase tracking-wider text-[11px] border-b border-gray-300">
                                <tr>
                                    <th class="py-3 px-4 border-r border-gray-200">Alumni / Mahasiswa</th>
                                    <th class="py-3 px-4 border-r border-gray-200">Program Studi</th>
                                    <th class="py-3 px-4 border-r border-gray-200">Akun LinkedIn</th>
                                    <th class="py-3 px-4 border-r border-gray-200">Karier Terkini</th>
                                    <th class="py-3 px-4 text-center border-r border-gray-200">Status Sinkronisasi</th>
                                    <th class="py-3 px-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-if="paginatedAlumni.length === 0">
                                    <td colspan="6" class="py-12 text-center text-gray-500">
                                        Tidak ada data alumni yang cocok dengan filter yang dipilih.
                                    </td>
                                </tr>

                                <tr 
                                    v-else 
                                    v-for="alumni in paginatedAlumni" 
                                    :key="alumni.id"
                                    :id="`alumni-row-${alumni.id}`"
                                    class="hover:bg-gray-50/70 transition-colors"
                                    :class="highlightedAlumniId === alumni.id ? 'bg-emerald-50/40' : ''"
                                >
                                    <!-- 1. Alumni Info -->
                                    <td class="py-3.5 px-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-xl bg-slate-100 text-[#0D542B] font-bold text-xs flex items-center justify-center shrink-0 border border-slate-200">
                                                {{ (alumni.nama || 'A').charAt(0) }}
                                            </div>
                                            <div>
                                                <Link 
                                                    :href="role === 'superadmin' ? `/superadmin/alumni/${alumni.id}` : (role === 'admin_fakultas' ? `/fakultas/alumni/${alumni.id}` : `/prodi/alumni/${alumni.id}`)"
                                                    class="font-bold text-gray-900 hover:text-[#0D542B] hover:underline block leading-tight text-xs sm:text-sm"
                                                >
                                                    {{ alumni.nama }}
                                                </Link>
                                                <span class="text-[11px] font-mono text-gray-500 block mt-0.5">
                                                    NIM: {{ alumni.nim }}
                                                </span>
                                                <!-- Badge Validasi Nama dari Database View v_alumni_linkedin_plot -->
                                                <div v-if="alumni.keterangan_nama" class="mt-1">
                                                    <span 
                                                        class="inline-block text-[10px] font-medium px-2 py-0.5 rounded border"
                                                        :class="alumni.keterangan_nama.includes('Nama Sesuai')
                                                            ? 'bg-emerald-50 text-[#0D542B] border-emerald-200'
                                                            : 'bg-gray-50 text-gray-700 border-gray-200'"
                                                    >
                                                        {{ alumni.keterangan_nama }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- 2. Program Studi -->
                                    <td class="py-3.5 px-4">
                                        <span class="font-semibold text-gray-800 block">{{ alumni.prodi }}</span>
                                        <span class="text-[10px] text-gray-500 block">{{ alumni.fakultas }}</span>
                                    </td>

                                    <!-- 3. Akun LinkedIn -->
                                    <td class="py-3.5 px-4">
                                        <div v-if="alumni.linkedin_url">
                                            <a 
                                                :href="alumni.linkedin_url" 
                                                target="_blank" 
                                                class="text-gray-700 hover:text-[#0D542B] hover:underline font-mono text-xs inline-flex items-center gap-1.5 max-w-[200px] truncate"
                                                title="Buka profil LinkedIn asli"
                                            >
                                                <svg class="w-3.5 h-3.5 shrink-0 fill-current text-gray-500" viewBox="0 0 24 24">
                                                    <path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/>
                                                </svg>
                                                <span class="truncate">{{ alumni.linkedin_url.replace(/^https?:\/\/(www\.)?linkedin\.com\/in\//, '') || 'Profil LinkedIn' }}</span>
                                            </a>
                                        </div>
                                        <div v-else-if="alumni.linkedin_username">
                                            <span class="font-mono text-gray-700 text-xs">@{{ alumni.linkedin_username }}</span>
                                        </div>
                                        <div v-else class="text-gray-400 text-[11px] italic">
                                            Belum diisi
                                        </div>
                                    </td>

                                    <!-- 4. Karier Terkini & Plotting LinkedIn (Database View v_alumni_linkedin_plot) -->
                                    <td class="py-3.5 px-4">
                                        <div class="space-y-1.5">
                                            <div>
                                                <span class="text-[9px] uppercase font-bold tracking-wider text-gray-500 block">Biodata UKDW:</span>
                                                <span class="font-semibold text-gray-800 block truncate max-w-[210px]" :title="alumni.posisi">{{ alumni.posisi || '-' }}</span>
                                                <span class="text-[11px] text-gray-500 block truncate max-w-[210px]" :title="alumni.perusahaan">{{ alumni.perusahaan || '-' }}</span>
                                            </div>

                                            <div v-if="alumni.posisi_linkedin || alumni.perusahaan_linkedin" class="pt-1 border-t border-gray-100">
                                                <span class="text-[9px] uppercase font-bold tracking-wider text-gray-600 flex items-center gap-1">
                                                    <svg class="w-2.5 h-2.5 fill-current text-gray-500" viewBox="0 0 24 24"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>
                                                    LinkedIn Scrape:
                                                </span>
                                                <span class="text-xs font-medium text-slate-800 block truncate max-w-[210px]" :title="alumni.posisi_linkedin">{{ alumni.posisi_linkedin || '-' }}</span>
                                                <span class="text-[11px] text-slate-500 block truncate max-w-[210px]" :title="alumni.perusahaan_linkedin">{{ alumni.perusahaan_linkedin || '-' }}</span>
                                            </div>

                                            <div v-if="alumni.status_plot_label" class="mt-1">
                                                <span 
                                                    class="inline-block text-[10px] font-medium px-2 py-0.5 rounded border"
                                                    :class="alumni.status_plot_label.includes('Approved') || alumni.status_plot_label.includes('Siap Diplot')
                                                        ? 'bg-emerald-50 text-[#0D542B] border-emerald-200' 
                                                        : 'bg-gray-50 text-gray-700 border-gray-200'"
                                                >
                                                    {{ alumni.status_plot_label }}
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- 5. Status Sinkronisasi -->
                                    <td class="py-3.5 px-4 text-center">
                                        <span 
                                            class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium border"
                                            :class="(alumni.status_sync === 'Approved' || alumni.status_sync === 'Berhasil')
                                                ? 'bg-emerald-50 text-[#0D542B] border-emerald-200'
                                                : 'bg-gray-50 text-gray-700 border-gray-200'"
                                        >
                                            {{ alumni.status_sync }}
                                        </span>
                                        <span class="text-[10px] text-gray-400 block mt-1 font-mono">
                                            {{ alumni.terakhir_sync || '-' }}
                                        </span>
                                    </td>

                                    <!-- 6. Aksi Cepat (Tombol Ikon Berukuran Pasti) -->
                                    <td class="py-3.5 px-4 text-center">
                                        <div class="inline-flex items-center justify-center gap-1.5">
                                            <!-- Tombol Sync Single (Icon Berukuran Pasti 32x32) -->
                                            <button
                                                type="button"
                                                @click="syncSingle(alumni)"
                                                :disabled="alumni.isSyncing"
                                                class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 text-white bg-[#0D542B] hover:bg-[#0A4322] active:scale-95 transition-all shadow-2xs disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                                                :title="alumni.isSyncing ? 'Sedang memproses sinkronisasi...' : 'Sinkronkan Profil LinkedIn'"
                                                aria-label="Sinkronkan Profil LinkedIn"
                                            >
                                                <svg v-if="!alumni.isSyncing" class="w-4 h-4 fill-current" viewBox="0 0 24 24">
                                                    <path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/>
                                                </svg>
                                                <svg v-else class="animate-spin w-4 h-4 text-white" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                                </svg>
                                            </button>

                                            <!-- Tombol Review (Jika status Pending Review, Icon Berukuran Pasti 32x32) -->
                                            <button
                                                v-if="alumni.latest_sync_result && alumni.latest_sync_result.status === 'pending'"
                                                type="button"
                                                @click="openReviewModal(alumni.latest_sync_result.id, alumni)"
                                                class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 text-gray-700 bg-white hover:bg-gray-100 hover:text-[#0D542B] border border-gray-300 active:scale-95 transition-all shadow-2xs cursor-pointer"
                                                title="Tinjau hasil scraping Apify"
                                                aria-label="Tinjau hasil sinkronisasi"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                                </svg>
                                            </button>

                                            <!-- Tombol Histori (Icon Berukuran Pasti 32x32) -->
                                            <button
                                                type="button"
                                                @click="openHistoryModal(alumni)"
                                                class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 text-gray-700 bg-white hover:bg-gray-100 hover:text-[#0D542B] border border-gray-300 active:scale-95 transition-all shadow-2xs cursor-pointer"
                                                title="Lihat riwayat sinkronisasi alumni ini"
                                                aria-label="Riwayat sinkronisasi"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Footer Pagination ala DataTables -->
                    <div class="px-6 py-4 bg-gray-50/50 border-t border-gray-300 flex items-center justify-between flex-wrap gap-4">
                        <div class="text-xs text-gray-500">
                            Menampilkan {{ filteredAlumni.length > 0 ? (currentPage - 1) * perPage + 1 : 0 }} sampai 
                            {{ Math.min(currentPage * perPage, filteredAlumni.length) }} dari 
                            {{ filteredAlumni.length }} data alumni
                        </div>

                        <div class="flex items-center gap-1.5">
                            <button
                                type="button"
                                @click="goToPage(currentPage - 1)"
                                :disabled="currentPage === 1"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
                            >
                                Sebelumnya
                            </button>

                            <button
                                v-for="p in Math.min(totalPages, 5)"
                                :key="p"
                                type="button"
                                @click="goToPage(p)"
                                class="w-8 h-8 rounded-lg text-xs font-bold transition-colors cursor-pointer"
                                :class="currentPage === p ? 'bg-[#0D542B] text-white shadow-2xs' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'"
                            >
                                {{ p }}
                            </button>

                            <button
                                type="button"
                                @click="goToPage(currentPage + 1)"
                                :disabled="currentPage === totalPages"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
                            >
                                Selanjutnya
                            </button>
                        </div>
                    </div>
                </div>

            </main>
        </div>

        <!-- ================================================================= -->
        <!-- MODAL PENINJAUAN & APPROVAL (APIFY) - LENGKAP & TRANSPARAN        -->
        <!-- ================================================================= -->
        <div
            v-if="isReviewModalOpen"
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
        >
            <div class="bg-white rounded-2xl max-w-5xl w-full shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[92vh]">
                <!-- Header Modal -->
                <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-bold text-slate-900">
                                Peninjauan Hasil Sinkronisasi LinkedIn
                            </h3>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-amber-100 text-amber-800">
                                Pending Review
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Alumni: <span class="font-semibold text-slate-800">{{ activeReview?.alumni_nama || activeAlumni?.nama }}</span>
                            ({{ activeReview?.alumni_nim || activeAlumni?.nim }}) &bull; Diambil: {{ activeReview?.scraped_at || 'Baru saja' }}
                        </p>
                    </div>

                    <div class="flex items-center gap-3">
                        <!-- Switch Tab: Terstruktur vs Raw JSON -->
                        <div v-if="activeReview" class="inline-flex rounded-xl border border-slate-200 p-0.5 bg-slate-200/60 text-xs shadow-2xs">
                            <button
                                type="button"
                                @click="activeReviewTab = 'structured'"
                                :class="[activeReviewTab === 'structured' ? 'bg-white text-slate-900 font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900']"
                                class="px-3 py-1.5 rounded-lg transition-all cursor-pointer flex items-center gap-1.5"
                            >
                                <svg class="w-3.5 h-3.5 text-[#0D542B]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <span>Ringkasan Terstruktur</span>
                            </button>
                            <button
                                type="button"
                                @click="activeReviewTab = 'raw_json'"
                                :class="[activeReviewTab === 'raw_json' ? 'bg-white text-slate-900 font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900']"
                                class="px-3 py-1.5 rounded-lg transition-all cursor-pointer flex items-center gap-1.5"
                            >
                                <svg class="w-3.5 h-3.5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                                </svg>
                                <span>{ } JSON Mentah</span>
                            </button>
                        </div>

                        <button
                            type="button"
                            @click="isReviewModalOpen = false"
                            class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-200/60 transition-colors cursor-pointer"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Body Modal -->
                <div class="p-6 overflow-y-auto space-y-5 flex-1">
                    <div v-if="isReviewLoading" class="py-16 text-center space-y-3">
                        <div class="inline-block w-8 h-8 border-4 border-emerald-600 border-t-transparent rounded-full animate-spin"></div>
                        <p class="text-xs text-slate-500">Memuat rincian hasil scraping Apify...</p>
                    </div>

                    <template v-else-if="activeReview">
                        <!-- Banner Peringatan Bersih -->
                        <div class="bg-amber-50 border border-amber-200 rounded-xl p-3.5 flex items-start gap-3">
                            <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div class="text-xs">
                                <p class="font-bold text-amber-900">
                                    Hasil sinkronisasi LinkedIn dari provider Apify dan belum diterapkan ke data utama.
                                </p>
                                <p class="text-amber-700 mt-0.5 leading-relaxed">
                                    Data profil utama alumni di database kampus <b>tidak akan berubah</b> sampai Anda menekan tombol <b>Setujui (Approve)</b>. Silakan periksa rincian data di bawah untuk memastikan data ini cocok sebelum disetujui.
                                </p>
                            </div>
                        </div>

                        <!-- Form Penyesuaian Usulan Sebelum Disetujui -->
                        <div class="bg-slate-50 p-4 sm:p-5 rounded-2xl border border-slate-200 space-y-3">
                            <div class="flex items-center justify-between flex-wrap gap-2">
                                <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-[#0D542B]"></span>
                                    <span>Penyesuaian Data Profil Sebelum Disetujui</span>
                                </h4>
                                <span class="text-[11px] text-slate-500">
                                    Data di bawah dapat disesuaikan sebelum diterapkan ke database utama
                                </span>
                            </div>

                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-3.5 text-xs">
                                <!-- Data Saat Ini -->
                                <div class="bg-white p-3.5 rounded-xl border border-slate-200 space-y-2">
                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">
                                        Data Saat Ini di Sistem:
                                    </span>
                                    <div class="space-y-1.5 text-xs">
                                        <p><span class="text-slate-500">Jabatan:</span> <b class="text-slate-800 block">{{ activeReview.current_data?.posisi_jabatan || '- Belum diisi -' }}</b></p>
                                        <p><span class="text-slate-500">Perusahaan:</span> <b class="text-slate-800 block">{{ activeReview.current_data?.nama_perusahaan || '- Belum diisi -' }}</b></p>
                                        <p><span class="text-slate-500">Keahlian (Skills):</span> <span class="text-slate-700 block truncate">{{ activeReview.current_data?.skills || '- Belum diisi -' }}</span></p>
                                    </div>
                                </div>

                                <!-- Form Penyesuaian Usulan -->
                                <div class="lg:col-span-2 bg-emerald-50/70 p-4 rounded-xl border border-emerald-200 space-y-3">
                                    <div class="flex items-center justify-between flex-wrap gap-2">
                                        <span class="text-[10px] font-bold text-emerald-800 uppercase tracking-wider">
                                            Data yang Akan Disimpan ke Profil Alumni:
                                        </span>
                                        <!-- Toggle Kategori -->
                                        <div class="inline-flex rounded-lg border border-emerald-300 p-0.5 bg-white text-[11px] shadow-2xs">
                                            <button
                                                type="button"
                                                @click="approveForm.tipe_pekerjaan = 'pekerja'"
                                                :class="[approveForm.tipe_pekerjaan === 'pekerja' ? 'bg-[#0D542B] text-white font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900']"
                                                class="px-2.5 py-1 rounded-md transition-all cursor-pointer flex items-center gap-1.5"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                                </svg>
                                                <span>Karyawan / Pekerja</span>
                                            </button>
                                            <button
                                                type="button"
                                                @click="approveForm.tipe_pekerjaan = 'wirausaha'"
                                                :class="[approveForm.tipe_pekerjaan === 'wirausaha' ? 'bg-[#0D542B] text-white font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900']"
                                                class="px-2.5 py-1 rounded-md transition-all cursor-pointer flex items-center gap-1.5"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                                </svg>
                                                <span>Wirausaha / Bisnis</span>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                                        <!-- Posisi Jabatan -->
                                        <div>
                                            <label class="block text-[11px] font-semibold text-emerald-950 mb-1">
                                                {{ approveForm.tipe_pekerjaan === 'wirausaha' ? 'Posisi Wiraswasta / Usaha:' : 'Posisi / Jabatan Pekerjaan:' }}
                                            </label>
                                            <input
                                                v-if="approveForm.tipe_pekerjaan === 'wirausaha'"
                                                type="text"
                                                v-model="approveForm.posisi_wiraswasta"
                                                :placeholder="approveForm.posisi_jabatan || 'contoh: Founder, Owner, Direktur'"
                                                class="w-full px-3 py-2 text-xs bg-white border border-emerald-300 rounded-lg focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B]"
                                            />
                                            <input
                                                v-else
                                                type="text"
                                                v-model="approveForm.posisi_jabatan"
                                                placeholder="contoh: Software Engineer, Analis Sistem"
                                                class="w-full px-3 py-2 text-xs bg-white border border-emerald-300 rounded-lg focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B]"
                                            />
                                        </div>

                                        <!-- Nama Perusahaan -->
                                        <div>
                                            <label class="block text-[11px] font-semibold text-emerald-950 mb-1">
                                                {{ approveForm.tipe_pekerjaan === 'wirausaha' ? 'Nama Unit Usaha / Bisnis:' : 'Nama Perusahaan / Instansi:' }}
                                            </label>
                                            <input
                                                type="text"
                                                v-model="approveForm.nama_perusahaan"
                                                placeholder="contoh: PT Teknologi Nusantara"
                                                class="w-full px-3 py-2 text-xs bg-white border border-emerald-300 rounded-lg focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B]"
                                            />
                                        </div>

                                        <!-- Kolom Keahlian (Skills) yang Akan Disimpan ke Profil -->
                                        <div class="sm:col-span-2">
                                            <label class="block text-[11px] font-semibold text-emerald-950 mb-1 flex items-center justify-between">
                                                <span>Keahlian / Skills yang Akan Disimpan ke Profil:</span>
                                                <span class="text-[10px] text-slate-500 font-normal">Sinkron ke kolom Keahlian profil alumni (pisahkan dengan koma)</span>
                                            </label>
                                            <input
                                                type="text"
                                                v-model="approveForm.skills"
                                                placeholder="contoh: IT Audit, Business Process Management, SQL, Python..."
                                                class="w-full px-3 py-2 text-xs bg-white border border-emerald-300 rounded-lg focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B]"
                                            />

                                            <!-- Pills Keahlian dari Hasil Scraping Asli LinkedIn -->
                                            <div
                                                v-if="activeReview.preview?.skills && activeReview.preview.skills.length > 0"
                                                class="flex items-center gap-1.5 flex-wrap mt-2"
                                            >
                                                <span class="text-[10px] text-slate-500 font-medium">Klik untuk menambahkan dari profil:</span>
                                                <button
                                                    v-for="sk in activeReview.preview.skills"
                                                    :key="sk"
                                                    type="button"
                                                    @click="addSkillToForm(sk)"
                                                    class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-emerald-100 hover:bg-emerald-200 text-emerald-900 border border-emerald-300 transition-colors cursor-pointer"
                                                    :title="'Tambahkan ' + sk"
                                                >
                                                    + {{ sk }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Opsi Foto Profil -->
                                    <div
                                        v-if="activeReview.preview?.profile_picture"
                                        class="p-2.5 bg-white/95 rounded-lg border border-emerald-300 flex items-center justify-between gap-3 shadow-2xs"
                                    >
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <img
                                                :src="activeReview.preview.profile_picture"
                                                alt="Foto LinkedIn"
                                                class="w-8 h-8 rounded-full object-cover border border-slate-200 shrink-0"
                                            />
                                            <div class="min-w-0 text-left">
                                                <span class="text-[11px] font-semibold text-slate-800 block">Terapkan Foto Profil LinkedIn ke Biodata Alumni</span>
                                                <span class="text-[10px] text-slate-500 block truncate">Foto akan disimpan ke sistem /uploads/profile/</span>
                                            </div>
                                        </div>
                                        <label class="flex items-center gap-1.5 cursor-pointer shrink-0 bg-emerald-50 px-2.5 py-1 rounded-md border border-emerald-200">
                                            <input
                                                type="checkbox"
                                                v-model="approveForm.sync_foto"
                                                class="rounded text-[#0D542B] focus:ring-[#0D542B] w-4 h-4 cursor-pointer"
                                            />
                                            <span class="text-xs font-semibold text-emerald-900">Gunakan</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ============================================================= -->
                        <!-- TAB 1: TAMPILAN DATA TERSTRUKTUR DARI HASIL SCRAPING LINKEDIN -->
                        <!-- ============================================================= -->
                        <div v-show="activeReviewTab === 'structured'" class="space-y-4">
                            <!-- Header Profil LinkedIn Terverifikasi -->
                            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-2xs space-y-4">
                                <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 pb-4 border-b border-slate-100">
                                    <div class="flex items-start gap-4">
                                        <img
                                            v-if="activeReview.preview?.profile_picture"
                                            :src="activeReview.preview.profile_picture"
                                            alt="Foto Profil LinkedIn"
                                            class="w-16 h-16 rounded-2xl object-cover border border-slate-200 shadow-xs shrink-0"
                                        />
                                        <div v-else class="w-16 h-16 rounded-2xl bg-emerald-50 border border-emerald-200 flex items-center justify-center text-emerald-800 font-extrabold text-xl shrink-0">
                                            {{ (activeReview.preview?.name || 'A').charAt(0) }}
                                        </div>

                                        <div class="space-y-1">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <h3 class="font-bold text-slate-900 text-base">
                                                    {{ activeReview.preview?.name || '-' }}
                                                </h3>
                                                <span v-if="activeReview.preview?.handle" class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 font-mono text-[10px] font-semibold">
                                                    @{{ activeReview.preview.handle }}
                                                </span>
                                            </div>

                                            <p class="text-slate-700 text-xs font-medium leading-relaxed">
                                                {{ activeReview.preview?.headline || '-' }}
                                            </p>

                                            <p class="text-slate-500 text-[11px] flex items-center gap-1.5 flex-wrap">
                                                <span>{{ activeReview.preview?.location || '-' }}</span>
                                                <span v-if="activeReview.preview?.country">&bull; {{ activeReview.preview.country }}</span>
                                                <span v-if="activeReview.preview?.followers !== null">&bull; {{ activeReview.preview.followers }} Pengikut</span>
                                                <span v-if="activeReview.preview?.connections !== null">&bull; {{ activeReview.preview.connections }} Koneksi</span>
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Link Langsung ke LinkedIn Asli untuk Pembuktian Admin -->
                                    <div class="shrink-0 flex items-center gap-2">
                                        <a
                                            :href="activeReview.linkedin_url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold text-[#0D542B] bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 transition-colors shadow-2xs"
                                        >
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.28 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.75M6.46 10.9h2.8v8.37h-2.8v-8.37M7.86 6.5a1.63 1.63 0 1 0 1.63 1.63A1.63 1.63 0 0 0 7.86 6.5z"/>
                                            </svg>
                                            <span>Buka Profil Asli &nearr;</span>
                                        </a>
                                    </div>
                                </div>

                                <!-- Ringkasan / About -->
                                <div v-if="activeReview.preview?.about" class="space-y-1">
                                    <span class="text-slate-500 font-bold block text-[11px] uppercase tracking-wider">Tentang / Ringkasan (About):</span>
                                    <p class="text-slate-700 leading-relaxed bg-slate-50 p-3 rounded-xl border border-slate-100 text-xs whitespace-pre-line font-sans">
                                        {{ activeReview.preview.about }}
                                    </p>
                                </div>
                            </div>

                            <!-- Riwayat Pendidikan Lengkap (Education) -->
                            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-2xs space-y-3">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
                                        <svg class="w-4 h-4 text-[#0D542B]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                                        </svg>
                                        <span>Riwayat Pendidikan ({{ activeReview.preview?.education?.length || 0 }})</span>
                                    </h4>
                                    <span class="text-[11px] text-slate-500">Pencocokan almamater & gelar akademik</span>
                                </div>

                                <div v-if="activeReview.preview?.education && activeReview.preview.education.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div
                                        v-for="(edu, idx) in activeReview.preview.education"
                                        :key="idx"
                                        class="p-3.5 rounded-xl border text-xs space-y-1 transition-all"
                                        :class="edu.school && edu.school.toLowerCase().includes('duta wacana') ? 'bg-emerald-50/50 border-emerald-300 ring-1 ring-emerald-200' : 'bg-slate-50 border-slate-200'"
                                    >
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="font-bold text-slate-900 text-xs">{{ edu.school || 'Sekolah / Kampus' }}</span>
                                            <span v-if="edu.school && edu.school.toLowerCase().includes('duta wacana')" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200 shrink-0">
                                                Almamater UKDW
                                            </span>
                                        </div>
                                        <p class="text-slate-700 font-medium">
                                            <span v-if="edu.degree">{{ edu.degree }}</span>
                                            <span v-if="edu.degree && edu.field_of_study"> &bull; </span>
                                            <span v-if="edu.field_of_study" class="text-slate-600">{{ edu.field_of_study }}</span>
                                        </p>
                                        <p v-if="edu.date" class="text-slate-500 text-[11px]">{{ edu.date }}</p>
                                    </div>
                                </div>
                                <div v-else class="text-center py-4 bg-slate-50 rounded-xl border border-slate-200 text-xs text-slate-500">
                                    Tidak ada data pendidikan spesifik pada profil ini.
                                </div>
                            </div>

                            <!-- Riwayat Pengalaman Kerja Lengkap (Experiences) -->
                            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-2xs space-y-3">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
                                        <svg class="w-4 h-4 text-[#0D542B]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                        </svg>
                                        <span>Riwayat Pengalaman Kerja ({{ activeReview.preview?.experience?.length || 0 }})</span>
                                    </h4>
                                    <span class="text-[11px] text-slate-500">Daftar riwayat jabatan & instansi</span>
                                </div>

                                <div v-if="activeReview.preview?.experience && activeReview.preview.experience.length > 0" class="space-y-2.5">
                                    <div
                                        v-for="(exp, idx) in activeReview.preview.experience"
                                        :key="idx"
                                        class="p-4 rounded-xl border border-slate-200 bg-white text-xs space-y-2 hover:border-slate-300 transition-colors"
                                    >
                                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="font-bold text-slate-900 text-sm">{{ exp.position || 'Posisi tidak disebutkan' }}</span>
                                                <span
                                                    v-if="exp.is_current"
                                                    class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200"
                                                >
                                                    Pekerjaan Saat Ini
                                                </span>
                                            </div>
                                            <span v-if="exp.date || exp.time_period" class="text-slate-500 text-[11px]">
                                                {{ exp.date || '' }} <span v-if="exp.time_period">({{ exp.time_period }})</span>
                                            </span>
                                        </div>

                                        <div class="flex items-center gap-2 flex-wrap text-slate-700 font-medium">
                                            <span>{{ exp.company || 'Perusahaan tidak disebutkan' }}</span>
                                            <span v-if="exp.location" class="text-slate-500">&bull; {{ exp.location }}</span>
                                            <a
                                                v-if="exp.company_url"
                                                :href="exp.company_url"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="text-sky-600 hover:underline text-[11px] font-normal"
                                            >
                                                Halaman Perusahaan &nearr;
                                            </a>
                                        </div>

                                        <p v-if="exp.description" class="text-slate-600 text-[11px] leading-relaxed bg-slate-50 p-2.5 rounded-lg border border-slate-100 whitespace-pre-line">
                                            {{ exp.description }}
                                        </p>
                                    </div>
                                </div>
                                <div v-else class="text-center py-4 bg-slate-50 rounded-xl border border-slate-200 text-xs text-slate-500">
                                    Tidak ada riwayat pengalaman kerja spesifik pada profil ini.
                                </div>
                            </div>

                            <!-- Daftar Keahlian / Skills dari Profil LinkedIn -->
                            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-2xs space-y-3">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
                                        <svg class="w-4 h-4 text-[#0D542B]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                        </svg>
                                        <span>Keahlian (Skills) dari LinkedIn ({{ activeReview.preview?.skills?.length || 0 }})</span>
                                    </h4>
                                    <button
                                        v-if="activeReview.preview?.skills && activeReview.preview.skills.length > 0"
                                        type="button"
                                        @click="approveForm.skills = activeReview.preview.skills.join(', ')"
                                        class="text-[11px] font-bold text-[#0D542B] hover:underline cursor-pointer"
                                    >
                                        Gunakan Semua di Form Penyesuaian
                                    </button>
                                </div>

                                <div v-if="activeReview.preview?.skills && activeReview.preview.skills.length > 0" class="flex items-center gap-2 flex-wrap">
                                    <span
                                        v-for="sk in activeReview.preview.skills"
                                        :key="'sk-' + sk"
                                        class="px-3 py-1.5 rounded-xl text-xs font-semibold bg-emerald-50 text-[#0D542B] border border-emerald-200 shadow-2xs"
                                    >
                                        {{ sk }}
                                    </span>
                                </div>
                                <div v-else class="text-center py-4 bg-slate-50 rounded-xl border border-slate-200 text-xs text-slate-500">
                                    Tidak ada data keahlian (skills) pada profil publik LinkedIn alumni ini.
                                </div>
                            </div>

                            <!-- Riwayat Sukarelawan jika ada -->
                            <div v-if="activeReview.preview?.volunteer_experiences && activeReview.preview.volunteer_experiences.length > 0" class="bg-white p-5 rounded-2xl border border-slate-200 shadow-2xs space-y-3">
                                <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">
                                    Pengalaman Sukarelawan / Volunteer ({{ activeReview.preview.volunteer_experiences.length }})
                                </h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div
                                        v-for="(vol, idx) in activeReview.preview.volunteer_experiences"
                                        :key="idx"
                                        class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs space-y-1"
                                    >
                                        <p class="font-bold text-slate-900">{{ vol.role || 'Volunteer' }}</p>
                                        <p class="text-slate-600">{{ vol.organization || '-' }}</p>
                                        <p v-if="vol.date" class="text-slate-400 text-[11px]">{{ vol.date }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ============================================================= -->
                        <!-- TAB 2: DATA MENTAH JSON (RAW JSON DARI HASIL SCRAPING APIFY)  -->
                        <!-- ============================================================= -->
                        <div v-show="activeReviewTab === 'raw_json'" class="space-y-3">
                            <div class="flex items-center justify-between flex-wrap gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-slate-800 uppercase tracking-wider">Respons Payload Mentah (Apify Dataset):</span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-mono bg-slate-200 text-slate-700">
                                        {{ JSON.stringify(activeReview.raw).length }} Karakter
                                    </span>
                                </div>

                                <button
                                    type="button"
                                    @click="copyRawJson(activeReview.raw)"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold text-slate-700 bg-white hover:bg-slate-100 border border-slate-300 transition-colors shadow-2xs cursor-pointer"
                                >
                                    <svg class="w-3.5 h-3.5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                    </svg>
                                    <span>Salin JSON Mentah</span>
                                </button>
                            </div>

                            <div class="p-4 bg-slate-900 text-emerald-400 font-mono text-xs rounded-2xl overflow-x-auto max-h-[500px] border border-slate-800 shadow-inner">
                                <pre class="leading-relaxed whitespace-pre-wrap">{{ JSON.stringify(activeReview.raw, null, 2) }}</pre>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Footer Aksi Modal (Bebas Emoticon) -->
                <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                    <div>
                        <span v-if="activeReview && activeReview.status !== 'pending'" class="text-xs text-slate-500 italic">
                            Catatan: Data ini telah di-review ({{ activeReview.status }}) pada {{ activeReview.reviewed_at }}.
                        </span>
                    </div>

                    <div class="flex items-center gap-2.5">
                        <button
                            type="button"
                            @click="isReviewModalOpen = false"
                            class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-700 bg-white hover:bg-slate-100 border border-slate-300 transition-colors cursor-pointer"
                        >
                            Tutup
                        </button>

                        <template v-if="activeReview && activeReview.status === 'pending'">
                            <button
                                type="button"
                                @click="handleReject"
                                :disabled="isActionLoading"
                                class="px-4 py-2 rounded-xl text-xs font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 transition-colors disabled:opacity-50 cursor-pointer"
                            >
                                Tolak (Reject)
                            </button>

                            <button
                                type="button"
                                @click="handleApprove"
                                :disabled="isActionLoading"
                                class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-[#0D542B] hover:bg-[#093c1f] transition-colors shadow-2xs disabled:opacity-50 cursor-pointer"
                            >
                                <svg v-if="!isActionLoading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span>Setujui (Approve) & Simpan Data</span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================================================================= -->
        <!-- MODAL HISTORI SINKRONISASI                                        -->
        <!-- ================================================================= -->
        <div
            v-if="isHistoryModalOpen"
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
        >
            <div class="bg-white rounded-2xl max-w-2xl w-full shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[85vh]">
                <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">
                            Histori Sinkronisasi LinkedIn
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Alumni: <span class="font-semibold text-slate-800">{{ historyAlumni?.nama }}</span> ({{ historyAlumni?.nim }})
                        </p>
                    </div>

                    <button
                        type="button"
                        @click="isHistoryModalOpen = false"
                        class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-200/60 transition-colors cursor-pointer"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto space-y-3 flex-1">
                    <div v-if="isHistoryLoading" class="py-12 text-center text-xs text-slate-500">
                        Memuat data histori...
                    </div>

                    <div v-else-if="historyList.length === 0" class="py-12 text-center text-xs text-slate-400">
                        Belum ada riwayat sinkronisasi untuk alumni ini.
                    </div>

                    <div
                        v-else
                        v-for="item in historyList"
                        :key="item.id"
                        class="p-4 rounded-xl border border-slate-200 hover:border-slate-300 transition-colors bg-white flex items-start justify-between gap-3 text-xs"
                    >
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-slate-400 text-[10px]">#{{ item.id }}</span>
                                <span
                                    class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                                    :class="[
                                        item.status === 'pending'
                                            ? 'bg-amber-100 text-amber-800'
                                            : (item.status === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800')
                                    ]"
                                >
                                    {{ item.status }}
                                </span>
                                <span class="text-slate-400 text-[11px] font-mono">{{ item.scraped_at }}</span>
                            </div>

                            <p class="text-slate-800">
                                <b>Jabatan:</b> {{ item.preview?.current_job || '-' }} &bull;
                                <b>Perusahaan:</b> {{ item.preview?.current_company || '-' }}
                            </p>

                            <p v-if="item.reviewed_at" class="text-[11px] text-slate-500">
                                Ditinjau oleh: <b>{{ item.reviewer_name }}</b> pada {{ item.reviewed_at }}
                            </p>
                        </div>

                        <button
                            type="button"
                            @click="isHistoryModalOpen = false; openReviewModal(item.id, historyAlumni)"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 transition-colors shrink-0 cursor-pointer"
                        >
                            Detail Snapshot
                        </button>
                    </div>
                </div>

                <div class="px-6 py-3 bg-slate-50 border-t border-slate-200 text-right">
                    <button
                        type="button"
                        @click="isHistoryModalOpen = false"
                        class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-700 bg-white hover:bg-slate-100 border border-slate-300 transition-colors cursor-pointer"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>

    </div>
</template>
