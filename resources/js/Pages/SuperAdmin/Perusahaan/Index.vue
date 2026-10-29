<!--
  Halaman: Pengelolaan & Verifikasi Master Perusahaan
  File: resources/js/Pages/SuperAdmin/Perusahaan/Index.vue

  Standar Antarmuka Resmi Universitas Kristen Duta Wacana:
  - Header solid hijau resmi UKDW #0D542B dengan kartu statistik interaktif (-mt-10) yang dapat diklik untuk memfilter data
  - Modal "Tambah Perusahaan Baru" identik 100% dengan rancangan visual form profil (Radio Lokasi, F11, Provinsi/Kabupaten/Negara, Kode Pos, Skala, Alamat)
  - Tabel institusi formal, bersih, tanpa kolom Alumni Bekerja terpisah
  - Bilah Aksi menonjolkan daftar alumni, memakai ikon untuk detail/edit/hapus, dan dropdown keputusan hanya untuk usulan yang belum menjadi master
  - Modal Konsolidasi Master Data terstruktur dan elegan
-->
<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Swal from 'sweetalert2';
import SuperAdminSidebar from '../Components/Sidebar.vue';
import AdminFakultasSidebar from '../../AdminFakultas/Components/Sidebar.vue';
import AdminProdiSidebar from '../../AdminProdi/Components/Sidebar.vue';

const props = defineProps({
    user: Object,
    role: {
        type: String,
        default: 'superadmin',
    },
    basePath: {
        type: String,
        default: '/superadmin/perusahaan',
    },
    dashboardPath: {
        type: String,
        default: '/superadmin/dashboard',
    },
    fakultas: {
        type: Object,
        default: () => ({}),
    },
    prodi: {
        type: Object,
        default: () => ({}),
    },
    canCreate: {
        type: Boolean,
        default: true,
    },
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
    negaras: {
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
            total_all: 0,
            total_pending_all: 0,
            total_verified: 0,
            total_rejected: 0,
        }),
    },
});

const isSuperAdmin = computed(() => props.role === 'superadmin');
const isFaculty = computed(() => props.role === 'admin_fakultas');
const isProdi = computed(() => props.role === 'admin_prodi');
const canFilterProdi = computed(() => !isProdi.value && props.prodis.length > 1);
const pageTitle = computed(() => isFaculty.value
    ? 'Pengelolaan Master Perusahaan - Admin Fakultas'
    : isProdi.value
        ? 'Pengelolaan Master Perusahaan - Admin Program Studi'
        : 'Pengelolaan Master Perusahaan - Super Admin Tracer Study UKDW');
const pageDescription = computed(() => isFaculty.value
    ? 'Validasi data mitra institusi dan verifikasi usulan instansi kerja untuk seluruh program studi di fakultas Anda.'
    : isProdi.value
        ? 'Validasi data mitra institusi dan verifikasi usulan instansi kerja dari alumni program studi Anda.'
        : 'Pusat validasi data mitra institusi, verifikasi usulan instansi kerja, dan konsolidasi profil karir alumni Universitas Kristen Duta Wacana.');

// ========================================================
// STATE FILTER & PENCARIAN
// ========================================================
const search = ref(props.filters.search || '');
const selectedProdiId = ref(props.filters.prodi_id || '');
const scope = ref(props.filters.scope || 'all');
const statusFilter = ref(props.filters.status || 'all');
const perPage = ref(props.filters.per_page || 10);

let searchTimeout = null;
const handleSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 400);
};

const filterByStatus = (newStatus) => {
    statusFilter.value = newStatus;
    applyFilters();
};

const applyFilters = () => {
    router.get(props.basePath, {
        search: search.value || undefined,
        prodi_id: selectedProdiId.value || undefined,
        scope: scope.value !== 'all' ? scope.value : undefined,
        status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
        per_page: perPage.value != 10 ? perPage.value : undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    search.value = '';
    selectedProdiId.value = '';
    scope.value = 'all';
    statusFilter.value = 'all';
    perPage.value = 10;
    router.get(props.basePath, {}, { preserveState: true });
};

// ========================================================
// HELPER FORMAT & ALUMNI
// ========================================================
const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return '-';
    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
};

const getRoleLabel = (role) => {
    switch (role) {
        case 'superadmin':
            return 'Super Administrator';
        case 'admin_biro_tiga':
            return 'Biro III Kemahasiswaan';
        case 'admin_fakultas':
            return 'Admin Fakultas';
        case 'admin_prodi':
            return 'Admin Program Studi';
        case 'alumni':
            return 'Alumni';
        default:
            return role ? role.replace('_', ' ').toUpperCase() : 'Verifikator';
    }
};

const getCompanyAlumniList = (company) => {
    if (!company) return [];
    if (company.biodata && company.biodata.length > 0) {
        return company.biodata;
    }
    if (company.creator && company.creator.biodata) {
        return [company.creator.biodata];
    }
    return [];
};

// Menyajikan jumlah alumni yang terlihat pengguna, termasuk fallback relasi pengaju.
const getCompanyAlumniCount = (company) => company?.biodata_count ?? getCompanyAlumniList(company).length;

const getAlumniDisplayName = (alumni) => {
    return alumni.data_akademik?.nama || alumni.user?.name || alumni.nama || 'Nama Alumni';
};

const getAlumniProdi = (alumni) => {
    return alumni.prodi?.nama_prodi || '-';
};

const getAlumniAngkatan = (alumni) => {
    if (alumni.data_akademik?.angkatan_masuk) {
        return `Angkatan ${alumni.data_akademik.angkatan_masuk}`;
    }
    if (alumni.data_akademik?.tahun_lulus) {
        return `Lulus ${alumni.data_akademik.tahun_lulus}`;
    }
    return '-';
};

const getAlumniJabatan = (alumni) => {
    return alumni.posisi_jabatan || 'Alumni / Pegawai Aktif';
};

const alumniDetailPath = (alumniId) => {
    if (!alumniId) return null;

    if (isFaculty.value) {
        return '/fakultas/alumni/' + alumniId;
    }

    if (isProdi.value) {
        return '/prodi/alumni/' + alumniId;
    }

    return '/superadmin/alumni/' + alumniId;
};

// ========================================================
// MODAL 1: DAFTAR ALUMNI BEKERJA (MENU LIST HAMBURGER)
// ========================================================
const showAlumniModal = ref(false);
const activeCompanyForAlumni = ref(null);
const alumniSearchQuery = ref('');

const openAlumniModal = (company) => {
    activeCompanyForAlumni.value = company;
    alumniSearchQuery.value = '';
    showAlumniModal.value = true;
};

const closeAlumniModal = () => {
    showAlumniModal.value = false;
    activeCompanyForAlumni.value = null;
    alumniSearchQuery.value = '';
};

const filteredAlumniList = computed(() => {
    const list = getCompanyAlumniList(activeCompanyForAlumni.value);
    if (!alumniSearchQuery.value.trim()) return list;

    const q = alumniSearchQuery.value.toLowerCase().trim();
    return list.filter((alumni) => {
        const name = getAlumniDisplayName(alumni).toLowerCase();
        const nim = (alumni.nim || '').toLowerCase();
        const prodi = getAlumniProdi(alumni).toLowerCase();
        const jabatan = getAlumniJabatan(alumni).toLowerCase();
        return name.includes(q) || nim.includes(q) || prodi.includes(q) || jabatan.includes(q);
    });
});

// ========================================================
// MODAL 2: PENINJAUAN LENGKAP USULAN PERUSAHAAN
// ========================================================
const showReviewModal = ref(false);
const activeCompanyForReview = ref(null);

// State indikator pemuatan rekomendasi kemiripan otomatis per halaman.
const isLoadingRecommendations = ref(false);
// Cache promise mencegah satu perusahaan dipanggil lebih dari sekali saat halaman dan modal meminta data bersamaan.
const recommendationRequests = new Map();

// Mengambil rekomendasi kemiripan master resmi tanpa menunggu klik pengguna.
const fetchCompanyRecommendations = async (company) => {
    if (!company || !company.id) return [];
    if (Array.isArray(company.recommendations)) {
        return company.recommendations;
    }
    if (recommendationRequests.has(company.id)) {
        return recommendationRequests.get(company.id);
    }

    const request = (async () => {
        company.isLoadingRecommendations = true;
        try {
            const response = await fetch(`${props.basePath}/${company.id}/recommendations`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (response.ok) {
                const data = await response.json();
                company.recommendations = Array.isArray(data) ? data : [];
            } else {
                company.recommendations = [];
            }
        } catch (e) {
            console.error('Gagal mengambil rekomendasi kemiripan:', e);
            company.recommendations = [];
        } finally {
            company.isLoadingRecommendations = false;
            recommendationRequests.delete(company.id);
        }

        return company.recommendations;
    })();

    recommendationRequests.set(company.id, request);

    return request;
};

// Memuat rekomendasi untuk seluruh perusahaan pada halaman aktif tanpa tombol perantara.
const loadPageRecommendations = async () => {
    const pageCompanies = props.companies?.data || [];
    await Promise.all(pageCompanies.map((company) => fetchCompanyRecommendations(company)));
};

// Mengulang pemuatan hanya saat isi halaman berubah, misalnya setelah filter atau pindah halaman.
watch(
    () => (props.companies?.data || []).map((company) => company.id).join(','),
    loadPageRecommendations,
    { immediate: true },
);

const openReviewModal = async (company) => {
    activeCompanyForReview.value = company;
    showReviewModal.value = true;

    // Jika rekomendasi belum dimuat, ambil secara asinkron saat modal dibuka
    if (company && company.recommendations === undefined) {
        isLoadingRecommendations.value = true;
        await fetchCompanyRecommendations(company);
        isLoadingRecommendations.value = false;
    }
};

const closeReviewModal = () => {
    showReviewModal.value = false;
    activeCompanyForReview.value = null;
};

const displayValue = (value) => {
    return value === null || value === undefined || value === '' ? '-' : String(value);
};

const getCompanyDetailFields = (company) => {
    if (!company) return [];

    return [
        ['ID Master', company.id],
        ['Nama Perusahaan / Instansi', company.nama_perusahaan],
        ['Provinsi', company.propinsi?.nama_provinsi || company.nama_provinsi || company.propinsi_id],
        ['Kabupaten / Kota', company.kabupaten?.nama_kabupaten || company.nama_kabupaten || company.kabupaten_id],
        ['Alamat', company.alamat],
        ['Kode Pos', company.kode_pos],
        ['Homepage / Website', company.homepage],
        ['Nomor Telepon / Fax', company.no_telp_fax],
        ['Sektor', company.sektor],
        ['Skala', company.skala],
        ['Bentuk Perusahaan', company.bentuk_perusahaan],
        ['Jumlah Pegawai', company.jumlah_pegawai],
        ['Jenis Perusahaan', company.jenis_perusahaan],
        ['Jenis Perusahaan Lainnya', company.jenis_perusahaan_lainnya],
        ['Jenis Lokasi', company.jenis_lokasi],
        ['Negara', company.negara],
        ['Status Verifikasi', company.status_verifikasi],
        ['Dibuat Oleh', company.creator?.name || company.creator_name || company.created_by_user_id],
        ['Role Pembuat', company.creator?.role || company.creator_role ? getRoleLabel(company.creator?.role || company.creator_role) : '-'],
        ['Prodi Pengusul', company.createdProdi?.nama_prodi || company.created_prodi_name || company.created_by_prodi_id],
        ['Dibuat Pada', formatDate(company.created_at)],
        ['Diperbarui Pada', formatDate(company.updated_at)],
    ].map(([label, value]) => ({ label, value: displayValue(value) }));
};

const escapeHtml = (value) => String(value ?? '-')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

// State reaktif untuk visibilitas dan data rincian rekomendasi master perusahaan
// 1. ref(false) menandai modal dalam kondisi tertutup secara default
// 2. ref(null) menyimpan referensi objek data perusahaan rekomendasi yang sedang diklik
const showRecommendationModal = ref(false);
const activeRecommendation = ref(null);

/**
 * Membuka modal detail rekomendasi perusahaan master
 * @param {Object} recommendation Objek perusahaan rekomendasi yang dipilih
 */
const openRecommendationDetail = (recommendation) => {
    activeRecommendation.value = recommendation;
    showRecommendationModal.value = true;
};

/**
 * Menutup modal detail rekomendasi perusahaan master dan membersihkan objek data aktif
 */
const closeRecommendationModal = () => {
    showRecommendationModal.value = false;
    activeRecommendation.value = null;
};

// ========================================================
// MODAL 3: KONSOLIDASI MASTER TERVERIFIKASI
// ========================================================
const showConsolidateModal = ref(false);
const activePendingCompany = ref(null);
const activeTargetVerifiedCompany = ref(null);
const isSubmittingConsolidate = ref(false);

const openConsolidateModal = (pendingCompany, targetVerifiedCompany) => {
    activePendingCompany.value = pendingCompany;
    activeTargetVerifiedCompany.value = targetVerifiedCompany;
    showConsolidateModal.value = true;
};

const closeConsolidateModal = () => {
    showConsolidateModal.value = false;
    activePendingCompany.value = null;
    activeTargetVerifiedCompany.value = null;
};

const submitConsolidate = () => {
    if (!activePendingCompany.value || !activeTargetVerifiedCompany.value) return;
    isSubmittingConsolidate.value = true;
    router.post(`${props.basePath}/${activePendingCompany.value.id}/replace`, {
        target_company_id: activeTargetVerifiedCompany.value.id,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            isSubmittingConsolidate.value = false;
            closeConsolidateModal();
            if (showReviewModal.value) closeReviewModal();
            Swal.fire({
                icon: 'success',
                title: 'Konsolidasi Berhasil',
                text: `Data usulan berhasil dikonsolidasikan ke master "${activeTargetVerifiedCompany.value.nama_perusahaan}".`,
                confirmButtonColor: '#0D542B',
            });
        },
        onError: () => {
            isSubmittingConsolidate.value = false;
        },
    });
};

// ========================================================
// MODAL 4: PENOLAKAN USULAN (TOMBOL X DENGAN ALASAN)
// ========================================================
const showRejectModal = ref(false);
const activeCompanyForReject = ref(null);
const rejectReason = ref('');
const isSubmittingReject = ref(false);

const openRejectModal = (company) => {
    activeCompanyForReject.value = company;
    rejectReason.value = '';
    showRejectModal.value = true;
};

const closeRejectModal = () => {
    showRejectModal.value = false;
    activeCompanyForReject.value = null;
    rejectReason.value = '';
};

const setQuickReason = (reason) => {
    rejectReason.value = reason;
};

const submitReject = () => {
    if (!activeCompanyForReject.value) return;
    const rejectedCompanyName = activeCompanyForReject.value.nama_perusahaan;
    isSubmittingReject.value = true;
    router.post(`${props.basePath}/${activeCompanyForReject.value.id}/reject`, {
        alasan: rejectReason.value.trim() || undefined,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            isSubmittingReject.value = false;
            closeRejectModal();
            if (showReviewModal.value) closeReviewModal();
            Swal.fire({
                icon: 'success',
                title: 'Usulan Ditolak',
                text: `Pengajuan "${rejectedCompanyName}" telah ditandai Ditolak.`,
                confirmButtonColor: '#0D542B',
            });
        },
        onError: () => {
            isSubmittingReject.value = false;
        },
    });
};

// ========================================================
// AKSI HAPUS PERUSAHAAN (DENGAN PENGECEKAN DEPENDENSI)
// ========================================================
// Menampilkan konfirmasi sebelum menghapus instansi perusahaan
const confirmDeleteCompany = (company) => {
    // Menghitung jumlah alumni yang terikat pada perusahaan ini
    const alumniCount = company.biodata ? company.biodata.length : 0;

    // Jika perusahaan masih memiliki relasi alumni, tolak penghapusan langsung
    if (alumniCount > 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Perusahaan Tidak Dapat Dihapus',
            html: `Perusahaan <b>"${company.nama_perusahaan}"</b> masih terhubung dengan <b>${alumniCount} data alumni</b>.<br><br>Untuk menjaga integritas data riwayat karir alumni, silakan gunakan tombol <b>"Sinkronkan"</b> untuk mengalihkan alumni ke master terverifikasi sebelum menghapus usulan ini.`,
            confirmButtonColor: '#0D542B',
            confirmButtonText: 'Mengerti',
        });
        return;
    }

    // Konfirmasi dialog hapus data perusahaan yang tidak memiliki relasi alumni
    Swal.fire({
        title: 'Hapus Perusahaan?',
        html: `Apakah Anda yakin ingin menghapus data instansi <b>"${company.nama_perusahaan}"</b>?<br><span class="text-xs text-slate-500 mt-2 block">Data yang dihapus tidak dapat dipulihkan kembali.</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#DC2626',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(`${props.basePath}/${company.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Terhapus',
                        text: `Perusahaan "${company.nama_perusahaan}" berhasil dihapus dari sistem.`,
                        timer: 1600,
                        showConfirmButton: false,
                    });
                },
                onError: (err) => {
                    const msg = Object.values(err)[0] || 'Gagal menghapus data perusahaan.';
                    Swal.fire({
                        icon: 'error',
                        title: 'Penghapusan Gagal',
                        text: msg,
                        confirmButtonColor: '#0D542B',
                    });
                },
            });
        }
    });
};

// ========================================================
// MODAL 5: TAMBAH PERUSAHAAN BARU (IDENTIK GAMBAR 1 & 2)
// ========================================================
const showCreateModal = ref(false);
const isSubmittingCreate = ref(false);
const createForm = ref({
    jenis_lokasi: 'Dalam Negeri',
    nama_perusahaan: '',
    jenis_perusahaan: '3 - Perusahaan swasta',
    propinsi_id: '',
    kabupaten_id: '',
    negara: '',
    kode_pos: '',
    skala: 'Nasional',
    alamat: '',
});

const openCreateModal = () => {
    createForm.value = {
        jenis_lokasi: 'Dalam Negeri',
        nama_perusahaan: '',
        jenis_perusahaan: '3 - Perusahaan swasta',
        propinsi_id: '',
        kabupaten_id: '',
        negara: '',
        kode_pos: '',
        skala: 'Nasional',
        alamat: '',
    };
    availableKabupatensForCreate.value = [];
    showCreateModal.value = true;
};

const closeCreateModal = () => {
    showCreateModal.value = false;
};

// ========================================================
// OPTIMASI WILAYAH: CASCADING DROPDOWN KABUPATEN ASINKRON
// ========================================================
// Cache daftar kabupaten per provinsi di memori browser agar request tidak berulang
const kabupatensCache = ref({});
const availableKabupatensForCreate = ref([]);
const availableKabupatensForEdit = ref([]);
const isLoadingKabupatenCreate = ref(false);
const isLoadingKabupatenEdit = ref(false);

// Mengambil daftar kabupaten dari server berdasarkan ID provinsi terpilih
const fetchKabupatensByProvinsi = async (propinsiId) => {
    if (!propinsiId) return [];
    if (kabupatensCache.value[propinsiId]) {
        return kabupatensCache.value[propinsiId];
    }

    try {
        const response = await fetch(`/api/wilayah/kabupaten/${propinsiId}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (response.ok) {
            const data = await response.json();
            kabupatensCache.value[propinsiId] = data;
            return data;
        }
    } catch (e) {
        console.error('Gagal mengambil daftar kabupaten:', e);
    }
    return [];
};

const handlePropinsiChangeCreate = async () => {
    createForm.value.kabupaten_id = '';
    const propId = createForm.value.propinsi_id;
    if (propId) {
        isLoadingKabupatenCreate.value = true;
        availableKabupatensForCreate.value = await fetchKabupatensByProvinsi(propId);
        isLoadingKabupatenCreate.value = false;
    } else {
        availableKabupatensForCreate.value = [];
    }
};

const handlePropinsiChangeEdit = async () => {
    editForm.value.kabupaten_id = '';
    const propId = editForm.value.propinsi_id;
    if (propId) {
        isLoadingKabupatenEdit.value = true;
        availableKabupatensForEdit.value = await fetchKabupatensByProvinsi(propId);
        isLoadingKabupatenEdit.value = false;
    } else {
        availableKabupatensForEdit.value = [];
    }
};

const submitCreateForm = () => {
    if (!createForm.value.nama_perusahaan.trim()) {
        Swal.fire({
            icon: 'error',
            title: 'Validasi Data',
            text: 'Nama Perusahaan / Instansi wajib diisi.',
            confirmButtonColor: '#0D542B',
        });
        return;
    }

    if (createForm.value.jenis_lokasi === 'Dalam Negeri') {
        if (!createForm.value.propinsi_id) {
            Swal.fire({
                icon: 'error',
                title: 'Validasi Data',
                text: 'Provinsi wajib dipilih untuk institusi dalam negeri.',
                confirmButtonColor: '#0D542B',
            });
            return;
        }
        if (!createForm.value.kabupaten_id) {
            Swal.fire({
                icon: 'error',
                title: 'Validasi Data',
                text: 'Kabupaten / Kota wajib dipilih.',
                confirmButtonColor: '#0D542B',
            });
            return;
        }
    } else {
        if (!createForm.value.negara) {
            Swal.fire({
                icon: 'error',
                title: 'Validasi Data',
                text: 'Negara Tempat Bekerja wajib dipilih untuk institusi luar negeri.',
                confirmButtonColor: '#0D542B',
            });
            return;
        }
    }

    isSubmittingCreate.value = true;
    router.post(props.basePath, createForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            isSubmittingCreate.value = false;
            closeCreateModal();
            Swal.fire({
                icon: 'success',
                title: 'Perusahaan Berhasil Disimpan',
                text: `"${createForm.value.nama_perusahaan}" telah resmi ditambahkan ke master terverifikasi.`,
                confirmButtonColor: '#0D542B',
            });
        },
        onError: (err) => {
            isSubmittingCreate.value = false;
            const msg = Object.values(err)[0] || 'Terjadi kesalahan saat menyimpan data.';
            Swal.fire({
                icon: 'error',
                title: 'Gagal Menyimpan',
                text: msg,
                confirmButtonColor: '#0D542B',
            });
        },
    });
};

// ========================================================
// MODAL 6: SUNTING DATA PERUSAHAAN (SESUAI FORM IDENTIK)
// ========================================================
const showEditModal = ref(false);
const isSubmittingEdit = ref(false);
const editForm = ref({
    id: null,
    jenis_lokasi: 'Dalam Negeri',
    nama_perusahaan: '',
    jenis_perusahaan: '3 - Perusahaan swasta',
    propinsi_id: '',
    kabupaten_id: '',
    negara: '',
    kode_pos: '',
    skala: 'Nasional',
    alamat: '',
});

const openEditModal = async (company) => {
    editForm.value = {
        id: company.id,
        jenis_lokasi: company.jenis_lokasi || 'Dalam Negeri',
        nama_perusahaan: company.nama_perusahaan || '',
        jenis_perusahaan: company.jenis_perusahaan || '3 - Perusahaan swasta',
        propinsi_id: company.propinsi_id ? String(company.propinsi_id) : '',
        kabupaten_id: company.kabupaten_id ? String(company.kabupaten_id) : '',
        negara: company.negara || '',
        kode_pos: company.kode_pos || '',
        skala: company.skala || 'Nasional',
        alamat: company.alamat || '',
    };
    showEditModal.value = true;

    // Prefetch daftar kabupaten untuk provinsi yang sedang aktif diedit
    if (company.propinsi_id) {
        isLoadingKabupatenEdit.value = true;
        availableKabupatensForEdit.value = await fetchKabupatensByProvinsi(company.propinsi_id);
        isLoadingKabupatenEdit.value = false;
    } else {
        availableKabupatensForEdit.value = [];
    }
};

const closeEditModal = () => {
    showEditModal.value = false;
    editForm.value.id = null;
    availableKabupatensForEdit.value = [];
};

const submitEditForm = () => {
    if (!editForm.value.nama_perusahaan.trim()) {
        Swal.fire({
            icon: 'error',
            title: 'Validasi Data',
            text: 'Nama Perusahaan / Instansi wajib diisi.',
            confirmButtonColor: '#0D542B',
        });
        return;
    }

    isSubmittingEdit.value = true;
    router.put(`${props.basePath}/${editForm.value.id}`, editForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            isSubmittingEdit.value = false;
            closeEditModal();
            if (showReviewModal.value) closeReviewModal();
            Swal.fire({
                icon: 'success',
                title: 'Data Berhasil Diperbarui',
                text: 'Informasi institusi telah berhasil diperbarui dan diverifikasi.',
                confirmButtonColor: '#0D542B',
            });
        },
        onError: (err) => {
            isSubmittingEdit.value = false;
            const msg = Object.values(err)[0] || 'Terjadi kendala saat memperbarui data.';
            Swal.fire({
                icon: 'error',
                title: 'Pembaruan Gagal',
                text: msg,
                confirmButtonColor: '#0D542B',
            });
        },
    });
};

// ========================================================
// OPERASI AKSI CEPAT: VERIFIKASI LANGSUNG (TOMBOL V)
// ========================================================
const handleVerifyDirect = (company) => {
    Swal.fire({
        title: 'Verifikasi sebagai Master Resmi?',
        text: `Apakah Anda yakin ingin memverifikasi "${company.nama_perusahaan}" sebagai data master institusi resmi universitas?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Verifikasi Institusi',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#0D542B',
        cancelButtonColor: '#64748B',
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(`${props.basePath}/${company.id}/verify`, {}, {
                preserveScroll: true,
                onSuccess: () => {
                    if (showReviewModal.value) closeReviewModal();
                    Swal.fire({
                        icon: 'success',
                        title: 'Institusi Terverifikasi',
                        text: `"${company.nama_perusahaan}" kini berstatus resmi sebagai Master Data Terverifikasi.`,
                        confirmButtonColor: '#0D542B',
                    });
                },
            });
        }
    });
};

// Menyatukan keputusan verifikasi dalam satu menu yang hanya berisi Terima atau Tolak.
const handleVerificationAction = (company, event) => {
    const action = event.target.value;
    event.target.value = '';

    if (action === 'approve') handleVerifyDirect(company);
    if (action === 'reject') openRejectModal(company);
};
</script>

<template>
    <Head :title="pageTitle" />

    <div class="min-h-screen bg-[#F8FAFC] text-slate-800 font-sans flex">
        <!-- Sidebar Resmi Super Admin -->
        <AdminFakultasSidebar v-if="isFaculty" :user="user" :fakultas="fakultas" />
        <AdminProdiSidebar v-else-if="isProdi" :user="user" :prodi="prodi" />
        <SuperAdminSidebar v-else :user="user" />

        <!-- Area Konten Utama -->
        <div class="flex-1 flex flex-col min-w-0 lg:pl-72">
            <!-- Header Solid Hijau Resmi UKDW #0D542B (Sesuai Gambar 3) -->
            <header class="admin-page-header bg-[#0D542B] text-white pt-8 pb-16 px-4 sm:px-6 lg:px-8 border-b border-emerald-900/40">
                <div class="w-full max-w-[1440px] mx-auto flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div>
                        <!-- Breadcrumbs -->
                        <nav class="flex items-center gap-2 text-xs text-emerald-100 font-medium mb-2.5">
                            <Link :href="dashboardPath" class="hover:text-white transition-colors flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                                </svg>
                                <span>Dashboard</span>
                            </Link>
                            <span class="text-emerald-300/60">/</span>
                            <span class="text-white font-bold bg-white/10 px-2 py-0.5 rounded-md">Master Perusahaan</span>
                        </nav>

                        <h1 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-white tracking-tight">
                            Pengelolaan Master Perusahaan & Kemitraan
                        </h1>
                        <p class="text-emerald-100/90 text-xs sm:text-sm font-normal mt-1.5 max-w-2xl leading-relaxed">
                            {{ pageDescription }}
                        </p>

                        <!-- Tombol Aksi Cepat Header -->
                        <div class="flex items-center gap-3 mt-4 flex-wrap">
                            <button
                                type="button"
                                v-if="canCreate"
                                @click="openCreateModal"
                                class="px-4 py-2 bg-white text-[#0D542B] hover:bg-emerald-50 font-bold text-xs rounded-lg transition-colors flex items-center gap-2 cursor-pointer shadow-sm"
                                title="Tambah entri perusahaan baru ke master data resmi"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                <span>Tambah Perusahaan Baru</span>
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Container dengan Negative Top Margin (-mt-10) Menumpuk Header Hijau -->
            <main class="admin-page-main w-full max-w-[1440px] mx-auto -mt-10 px-4 sm:px-6 lg:px-8 space-y-6 pb-12">
                <!-- 4 Kartu Statistik Interaktif & Filter Cepat (Bisa Diklik!) -->
                <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3.5 sm:gap-4">
                    <!-- 1. Total Institusi (Filter: Semua Status) -->
                    <div
                        @click="filterByStatus('all')"
                        class="bg-white rounded-xl p-4.5 border cursor-pointer transition-all select-none"
                        :class="statusFilter === 'all' ? 'border-[#0D542B] ring-1 ring-[#0D542B] shadow-xs' : 'border-slate-200 hover:border-slate-300 shadow-2xs'"
                        title="Klik untuk menampilkan seluruh perusahaan"
                    >
                        <div class="flex items-center justify-between gap-1.5">
                            <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Total Perusahaan</p>
                            <span v-if="statusFilter === 'all'" class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-[#0D542B] text-white">Aktif</span>
                        </div>
                        <p class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight mt-1.5">{{ stats.total_all }}</p>
                        <p class="text-xs text-slate-500 mt-1">Semua data terdaftar</p>
                    </div>

                    <!-- 2. Menunggu Peninjauan (Filter: Menunggu Verifikasi) -->
                    <div
                        @click="filterByStatus('Menunggu Verifikasi')"
                        class="bg-white rounded-xl p-4.5 border cursor-pointer transition-all select-none"
                        :class="statusFilter === 'Menunggu Verifikasi' ? 'border-[#0D542B] ring-1 ring-[#0D542B] shadow-xs' : 'border-slate-200 hover:border-slate-300 shadow-2xs'"
                        title="Klik untuk memfilter perusahaan yang menunggu verifikasi"
                    >
                        <div class="flex items-center justify-between gap-1.5">
                            <p class="text-[11px] font-bold text-slate-600 uppercase tracking-wider">Menunggu Peninjauan</p>
                            <span v-if="statusFilter === 'Menunggu Verifikasi'" class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-[#0D542B] text-white">Aktif</span>
                        </div>
                        <p class="text-2xl sm:text-3xl font-extrabold text-slate-800 tracking-tight mt-1.5">{{ stats.total_pending_all }}</p>
                        <p class="text-xs text-slate-500 mt-1">Perlu tindakan verifikasi</p>
                    </div>

                    <!-- 3. Master Terverifikasi (Filter: Terverifikasi) -->
                    <div
                        @click="filterByStatus('Terverifikasi')"
                        class="bg-white rounded-xl p-4.5 border cursor-pointer transition-all select-none"
                        :class="statusFilter === 'Terverifikasi' ? 'border-[#0D542B] ring-1 ring-[#0D542B] shadow-xs' : 'border-slate-200 hover:border-slate-300 shadow-2xs'"
                        title="Klik untuk memfilter master perusahaan resmi terverifikasi"
                    >
                        <div class="flex items-center justify-between gap-1.5">
                            <p class="text-[11px] font-bold text-slate-600 uppercase tracking-wider">Master Terverifikasi</p>
                            <span v-if="statusFilter === 'Terverifikasi'" class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-[#0D542B] text-white">Aktif</span>
                        </div>
                        <p class="text-2xl sm:text-3xl font-extrabold text-[#0D542B] tracking-tight mt-1.5">{{ stats.total_verified }}</p>
                        <p class="text-xs text-slate-500 mt-1">Mitra sah universitas</p>
                    </div>

                    <!-- 4. Usulan Ditolak (Filter: Ditolak) -->
                    <div
                        @click="filterByStatus('Ditolak')"
                        class="bg-white rounded-xl p-4.5 border cursor-pointer transition-all select-none"
                        :class="statusFilter === 'Ditolak' ? 'border-[#0D542B] ring-1 ring-[#0D542B] shadow-xs' : 'border-slate-200 hover:border-slate-300 shadow-2xs'"
                        title="Klik untuk memfilter pengajuan yang ditolak"
                    >
                        <div class="flex items-center justify-between gap-1.5">
                            <p class="text-[11px] font-bold text-slate-600 uppercase tracking-wider">Usulan Ditolak</p>
                            <span v-if="statusFilter === 'Ditolak'" class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-[#0D542B] text-white">Aktif</span>
                        </div>
                        <p class="text-2xl sm:text-3xl font-extrabold text-slate-800 tracking-tight mt-1.5">{{ stats.total_rejected }}</p>
                        <p class="text-xs text-slate-500 mt-1">Tidak memenuhi syarat</p>
                    </div>
                </div>

                <!-- Bilah Penyaringan & Pencarian (Format Identik Gambar 3) -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-2xs space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-slate-900 uppercase tracking-wider">
                                Filter & Pencarian Perusahaan
                            </span>
                            <span v-if="statusFilter !== 'all'" class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-300">
                                Status: {{ statusFilter }}
                            </span>
                        </div>
                        <button
                            v-if="search || selectedProdiId || scope !== 'all' || statusFilter !== 'all' || perPage != 10"
                            @click="resetFilters"
                            class="text-xs font-bold text-slate-500 hover:text-[#0D542B] transition-colors cursor-pointer"
                        >
                            Reset Filter
                        </button>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5 text-xs">
                        <!-- Kotak Pencarian -->
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Cari Nama / Kota</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </span>
                                <input
                                    type="text"
                                    v-model="search"
                                    @input="handleSearch"
                                    placeholder="Ketik nama perusahaan..."
                                    class="w-full pl-8 pr-3 py-2 border border-slate-200 rounded-xl text-xs bg-slate-50/50 focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                                />
                            </div>
                        </div>

                        <!-- Filter Program Studi -->
                        <div v-if="canFilterProdi">
                            <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Program Studi</label>
                            <select
                                v-model="selectedProdiId"
                                @change="applyFilters"
                                class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs bg-slate-50/50 focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#0D542B] font-medium cursor-pointer"
                            >
                                <option value="">Semua Program Studi</option>
                                <option v-for="p in prodis" :key="p.id" :value="p.id">
                                    {{ p.kode_prodi ? `[${p.kode_prodi}] ` : '' }}{{ p.nama_prodi }}
                                </option>
                            </select>
                        </div>

                        <!-- Filter Status Verifikasi -->
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Status Verifikasi</label>
                            <select
                                v-model="statusFilter"
                                @change="applyFilters"
                                class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs bg-slate-50/50 focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#0D542B] font-medium cursor-pointer"
                            >
                                <option value="all">Semua Status</option>
                                <option value="Menunggu Verifikasi">Menunggu Verifikasi</option>
                                <option value="Terverifikasi">Master Terverifikasi</option>
                                <option value="Ditolak">Usulan Ditolak</option>
                            </select>
                        </div>

                        <!-- Filter Lingkup Pengajuan -->
                        <div v-if="isSuperAdmin">
                            <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Lingkup Pengajuan</label>
                            <select
                                v-model="scope"
                                @change="applyFilters"
                                class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs bg-slate-50/50 focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#0D542B] font-medium cursor-pointer"
                            >
                                <option value="all">Seluruh Universitas</option>
                                <option value="prodi">Sesuai Prodi Terpilih</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Bilah Info Jumlah Data & Per Page -->
                <div class="flex items-center justify-between text-xs text-slate-600 px-1">
                    <div class="flex items-center gap-2">
                        <span>Tampilkan:</span>
                        <select
                            v-model="perPage"
                            @change="applyFilters"
                            class="px-2 py-1 border border-slate-300 rounded-lg text-xs bg-white focus:outline-none focus:ring-1 focus:ring-[#0D542B] font-medium cursor-pointer"
                        >
                            <option :value="10">10</option>
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                        </select>
                        <span>data per halaman</span>
                    </div>
                    <span>
                        Menampilkan <strong>{{ companies.from || 0 }} - {{ companies.to || 0 }}</strong> dari total <strong>{{ companies.total || 0 }}</strong> institusi terdaftar
                    </span>
                </div>

                <!-- Tabel Data Master & Usulan Perusahaan (Rapi & Bersih) -->
                <div class="bg-white border border-slate-200 rounded-2xl overflow-x-auto shadow-2xs">
                    <table class="w-full min-w-full text-left border-collapse text-xs bg-white">
                        <thead>
                            <tr class="bg-slate-50 text-slate-800 font-bold border-b border-slate-200 text-[11px] whitespace-nowrap">
                                <th class="border-r border-slate-200 px-3 py-3 text-center w-12">No</th>
                                <th class="border-r border-slate-200 px-3.5 py-3 min-w-[200px]">Nama Institusi / Perusahaan</th>
                                <th class="border-r border-slate-200 px-3.5 py-3 min-w-[200px]">Wilayah & Alamat Kantor</th>
                                <th class="border-r border-slate-200 px-3.5 py-3 min-w-[170px]">Pengaju Usulan</th>
                                <th class="border-r border-slate-200 px-3.5 py-3 min-w-[210px]">Status & Info Verifikasi</th>
                                <th class="border-r border-slate-200 px-3.5 py-3 min-w-[190px]">Rekomendasi Master</th>
                                <th class="px-4 py-3 text-center min-w-[230px] whitespace-nowrap">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="!companies.data || companies.data.length === 0">
                                <td colspan="7" class="p-8 text-center text-slate-400 italic">
                                    Tidak ada data institusi yang sesuai dengan kriteria penyaringan saat ini.
                                </td>
                            </tr>
                            <tr
                                v-for="(company, index) in companies.data"
                                :key="company.id"
                                class="hover:bg-slate-50/80 transition-colors"
                            >
                                <!-- No -->
                                <td class="border-r border-slate-100 px-3 py-3 text-center text-slate-600 font-medium">
                                    {{ ((companies.current_page - 1) * companies.per_page) + index + 1 }}
                                </td>

                                <!-- Nama Perusahaan & Karakteristik -->
                                <td class="border-r border-slate-100 px-3.5 py-3">
                                    <div class="font-bold text-slate-900 leading-snug">
                                        {{ company.nama_perusahaan }}
                                    </div>
                                    <div class="text-[11px] text-slate-500 mt-0.5">
                                        <span>{{ company.skala || 'Nasional' }}</span>
                                        <span v-if="company.jenis_perusahaan"> • {{ company.jenis_perusahaan }}</span>
                                    </div>
                                    <div v-if="company.homepage" class="text-[10px] text-slate-600 font-mono mt-0.5 truncate max-w-[180px]">
                                        {{ company.homepage }}
                                    </div>
                                </td>

                                <!-- Wilayah & Alamat Kantor -->
                                <td class="border-r border-slate-100 px-3.5 py-3 text-slate-700">
                                    <div v-if="company.alamat" class="font-medium text-slate-900 leading-snug mb-0.5">
                                        {{ company.alamat }}
                                    </div>
                                    <div class="text-slate-600 text-[11px]">
                                        <span v-if="company.kabupaten">{{ company.kabupaten.nama_kabupaten }}, </span>
                                        <span v-if="company.propinsi">{{ company.propinsi.nama_provinsi }}</span>
                                        <span v-if="!company.kabupaten && !company.propinsi">{{ company.negara || 'Indonesia' }}</span>
                                        <span v-if="company.kode_pos" class="font-mono text-slate-400"> ({{ company.kode_pos }})</span>
                                    </div>
                                    <div class="text-[10px] text-slate-400 mt-0.5">
                                        {{ company.jenis_lokasi || 'Dalam Negeri' }} • {{ company.negara || 'Indonesia' }}
                                    </div>
                                </td>

                                <!-- Kolom Pengaju Usulan -->
                                <td class="border-r border-slate-100 px-3.5 py-3">
                                    <div v-if="company.creator?.biodata">
                                        <div class="font-semibold text-slate-900 leading-tight">
                                            {{ getAlumniDisplayName(company.creator.biodata) }}
                                        </div>
                                        <div class="text-slate-500 text-[11px] font-mono mt-0.5">
                                            {{ company.creator.biodata.nim || '-' }} • {{ getAlumniProdi(company.creator.biodata) }}
                                        </div>
                                        <div class="text-[10px] text-slate-400 mt-0.5">
                                            Diajukan: {{ formatDate(company.created_at) }}
                                        </div>
                                    </div>
                                    <div v-else-if="company.creator">
                                        <div class="font-semibold text-slate-900 leading-tight">
                                            {{ company.creator.name }}
                                        </div>
                                        <div class="text-slate-500 text-[11px] mt-0.5">
                                            Role: {{ getRoleLabel(company.creator.role) }}
                                        </div>
                                        <div class="text-[10px] text-slate-400 mt-0.5">
                                            Dibuat: {{ formatDate(company.created_at) }}
                                        </div>
                                    </div>
                                    <div v-else-if="company.biodata && company.biodata.length > 0">
                                        <div class="font-semibold text-slate-900 leading-tight">
                                            {{ getAlumniDisplayName(company.biodata[0]) }}
                                        </div>
                                        <div class="text-slate-500 text-[11px] font-mono mt-0.5">
                                            {{ company.biodata[0].nim || '-' }} • {{ getAlumniProdi(company.biodata[0]) }}
                                        </div>
                                        <div class="text-[10px] text-slate-400 mt-0.5">
                                            Tercatat: {{ formatDate(company.created_at) }}
                                        </div>
                                    </div>
                                    <span v-else class="text-slate-400 italic text-[11px]">Master Internal Sistem</span>
                                </td>

                                <!-- Kolom Status & Info Verifikasi -->
                                <td class="border-r border-slate-100 px-3.5 py-3">
                                    <div class="mb-1">
                                        <span
                                            v-if="company.status_verifikasi === 'Terverifikasi'"
                                            class="inline-block px-2.5 py-0.5 text-[10px] font-semibold rounded-full bg-emerald-50 text-[#0D542B] border border-emerald-200"
                                        >
                                            Terverifikasi
                                        </span>
                                        <span
                                            v-else-if="company.status_verifikasi === 'Menunggu Verifikasi'"
                                            class="inline-block px-2.5 py-0.5 text-[10px] font-semibold rounded-full bg-gray-100 text-gray-700 border border-gray-200"
                                        >
                                            Menunggu Peninjauan
                                        </span>
                                        <span
                                            v-else-if="company.status_verifikasi === 'Ditolak'"
                                            class="inline-block px-2.5 py-0.5 text-[10px] font-semibold rounded-full bg-gray-100 text-gray-600 border border-gray-200"
                                        >
                                            Ditolak
                                        </span>
                                        <span
                                            v-else
                                            class="inline-block px-2.5 py-0.5 text-[10px] font-semibold rounded-full bg-gray-100 text-gray-700 border border-gray-200"
                                        >
                                            {{ company.status_verifikasi }}
                                        </span>
                                    </div>

                                    <div v-if="company.status_verifikasi === 'Terverifikasi'" class="text-[10px] text-slate-600 leading-tight space-y-0.5">
                                        <div>
                                            <span class="text-slate-400">Oleh:</span>
                                            <strong class="text-slate-800 ml-1">
                                                {{ company.latest_verification_log?.user?.name || company.creator?.name || 'Super Administrator' }}
                                            </strong>
                                        </div>
                                        <div class="text-slate-500">
                                            Role: {{ getRoleLabel(company.latest_verification_log?.user?.role || company.creator?.role || 'superadmin') }}
                                        </div>
                                        <div class="text-slate-400 font-mono">
                                            {{ formatDate(company.latest_verification_log?.created_at || company.updated_at) }}
                                        </div>
                                    </div>
                                    <div v-else-if="company.status_verifikasi === 'Ditolak'" class="text-[10px] text-slate-600 leading-tight space-y-0.5">
                                        <div>
                                            <span class="text-slate-400">Ditolak oleh:</span>
                                            <strong class="text-slate-800 ml-1">
                                                {{ company.latest_verification_log?.user?.name || 'Super Administrator' }}
                                            </strong>
                                        </div>
                                        <div class="text-slate-400 font-mono">
                                            {{ formatDate(company.latest_verification_log?.created_at || company.updated_at) }}
                                        </div>
                                    </div>
                                    <div v-else class="text-[10px] text-slate-400 italic">
                                        Belum diverifikasi oleh admin
                                    </div>
                                </td>

                                <!-- Rekomendasi Master -->
                                <td class="border-r border-slate-100 px-3.5 py-3">
                                    <div v-if="company.recommendations && company.recommendations.length > 0" class="space-y-1.5">
                                        <div
                                            v-for="rec in company.recommendations"
                                            :key="rec.id"
                                            class="p-1.5 bg-slate-50 border border-slate-200 rounded text-[11px]"
                                        >
                                            <div class="flex items-start justify-between gap-1">
                                                <div class="font-semibold text-slate-900 truncate">{{ rec.nama_perusahaan }}</div>
                                                <button
                                                    type="button"
                                                    @click="openRecommendationDetail(rec)"
                                                    class="inline-flex items-center justify-center w-5 h-5 shrink-0 rounded border border-slate-300 text-slate-600 hover:text-[#0D542B] hover:border-[#0D542B] transition-colors cursor-pointer"
                                                    title="Lihat detail rekomendasi master"
                                                    aria-label="Lihat detail rekomendasi master"
                                                >
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <circle cx="12" cy="12" r="9" stroke-width="2" />
                                                        <path stroke-linecap="round" stroke-width="2" d="M12 11v5m0-8h.01" />
                                                    </svg>
                                                </button>
                                            </div>
                                            <div class="text-slate-500 text-[10px] truncate">
                                                {{ rec.nama_kabupaten || '-' }}, {{ rec.nama_provinsi || '-' }}
                                            </div>
                                            <div class="mt-1">
                                                <button
                                                    type="button"
                                                    @click="openConsolidateModal(company, rec)"
                                                    class="inline-flex items-center gap-1 px-2 py-0.5 bg-[#0D542B] hover:bg-[#08381c] text-white font-semibold rounded text-[10px] transition-colors cursor-pointer"
                                                    title="Buka dialog konsolidasi master resmi"
                                                >
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                    </svg>
                                                    <span>Sinkronkan</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-else-if="company.isLoadingRecommendations" class="text-[11px] text-slate-500 italic">
                                        Menyiapkan rekomendasi...
                                    </div>
                                    <span v-else class="text-slate-400 italic text-[11px]">Tidak ada master yang cukup mirip</span>
                                </td>

                                <!-- Kolom aksi memisahkan navigasi data dari satu dropdown keputusan verifikasi. -->
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    <div class="flex min-w-[210px] flex-col items-stretch justify-center gap-2">
                                        <select
                                            v-if="company.status_verifikasi !== 'Terverifikasi'"
                                            aria-label="Pilih keputusan verifikasi"
                                            class="h-9 w-full rounded-lg border border-slate-300 bg-white px-2.5 text-[11px] font-semibold text-slate-700 outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200 cursor-pointer"
                                            @change="handleVerificationAction(company, $event)"
                                        >
                                            <option value="">Pilih keputusan verifikasi</option>
                                            <option value="approve">Terima sebagai master resmi</option>
                                            <option v-if="company.status_verifikasi !== 'Ditolak'" value="reject">Tolak usulan</option>
                                        </select>

                                        <button
                                            type="button"
                                            @click="openAlumniModal(company)"
                                            class="inline-flex h-9 w-full items-center justify-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 text-[11px] font-bold text-slate-700 transition-colors hover:bg-slate-50 cursor-pointer"
                                            :title="`Lihat daftar alumni (${getCompanyAlumniCount(company)})`"
                                        >
                                            <span>Lihat daftar alumni</span>
                                            <span class="rounded-full border border-slate-200 bg-white px-1.5 py-0.5 text-[10px]">{{ getCompanyAlumniCount(company) }}</span>
                                        </button>

                                        <!-- Ikon detail, sunting, dan hapus memiliki tooltip serta label aksesibilitas yang jelas. -->
                                        <div class="flex h-9 items-center justify-center gap-1.5">
                                            <button
                                                type="button"
                                                @click="openReviewModal(company)"
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-600 transition-colors hover:bg-slate-50 hover:text-slate-900 cursor-pointer"
                                                title="Lihat detail perusahaan"
                                                aria-label="Lihat detail perusahaan"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </button>
                                            <button
                                                type="button"
                                                @click="openEditModal(company)"
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-600 transition-colors hover:bg-slate-50 hover:text-slate-900 cursor-pointer"
                                                title="Sunting data perusahaan"
                                                aria-label="Sunting data perusahaan"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <button
                                                type="button"
                                                @click="confirmDeleteCompany(company)"
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-600 transition-colors hover:bg-slate-50 hover:text-rose-700 cursor-pointer"
                                                title="Hapus data perusahaan"
                                                aria-label="Hapus data perusahaan"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>

                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Navigasi Paginasi Halaman -->
                <div v-if="companies.links && companies.links.length > 3" class="flex items-center justify-between text-xs pt-2">
                    <span class="text-slate-500">
                        Halaman <strong>{{ companies.current_page }}</strong> dari <strong>{{ companies.last_page }}</strong>
                    </span>
                    <div class="flex items-center gap-1">
                        <template v-for="(link, i) in companies.links" :key="i">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                class="px-3 py-1.5 rounded-lg border text-xs font-semibold transition-colors"
                                :class="link.active ? 'bg-[#0D542B] text-white border-[#0D542B]' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100'"
                                v-html="link.label"
                            />
                            <span
                                v-else
                                class="px-3 py-1.5 rounded-lg border border-slate-100 text-xs text-slate-400 bg-slate-50"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </main>

            <!-- Footer Institusional -->
            <footer class="text-slate-400 text-xs text-center mt-12 py-6 border-t border-slate-200">
                &copy; {{ new Date().getFullYear() }} Universitas Kristen Duta Wacana. Hak Cipta Dilindungi.
            </footer>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL 1: DAFTAR ALUMNI BEKERJA (MENU LIST HAMBURGER)     -->
    <!-- ======================================================== -->
    <div
        v-if="showAlumniModal && activeCompanyForAlumni"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs p-4"
    >
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-3xl max-h-[90vh] flex flex-col">
            <!-- Header Modal -->
            <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50 rounded-t-2xl">
                <div>
                    <h3 class="text-base font-bold text-slate-900">
                        Daftar Alumni Bekerja
                    </h3>
                    <p class="text-xs text-slate-600 mt-0.5">
                        Institusi: <strong class="text-[#0D542B]">{{ activeCompanyForAlumni.nama_perusahaan }}</strong>
                    </p>
                </div>
                <button
                    type="button"
                    @click="closeAlumniModal"
                    class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-200 transition-colors cursor-pointer"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Bilah Pencarian Cepat di Dalam Modal -->
            <div class="p-4 border-b border-slate-200 bg-white flex items-center justify-between gap-4 text-xs">
                <div class="relative flex-1">
                    <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </span>
                    <input
                        type="text"
                        v-model="alumniSearchQuery"
                        placeholder="Cari berdasarkan nama alumni, NIM, prodi, atau jabatan..."
                        class="w-full pl-8 pr-3 py-1.5 border border-slate-300 rounded-xl text-xs focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                    />
                </div>
                <span class="text-slate-600 font-medium whitespace-nowrap">
                    Total: <strong>{{ filteredAlumniList.length }} Alumni</strong>
                </span>
            </div>

            <!-- Badan Tabel Alumni -->
            <div class="overflow-y-auto p-4 flex-1">
                <table class="w-full text-left border-collapse border border-slate-200 text-xs bg-white rounded-xl overflow-hidden">
                    <thead>
                        <tr class="bg-slate-50 text-slate-800 font-bold border-b border-slate-200 text-[11px]">
                            <th class="border border-slate-200 px-3 py-2 text-center w-10">No</th>
                            <th class="border border-slate-200 px-3 py-2 min-w-[180px]">Nama Mahasiswa / Alumni</th>
                            <th class="border border-slate-200 px-3 py-2 w-28">NIM</th>
                            <th class="border border-slate-200 px-3 py-2 min-w-[150px]">Program Studi</th>
                            <th class="border border-slate-200 px-3 py-2 w-28 text-center">Angkatan</th>
                            <th class="border border-slate-200 px-3 py-2 min-w-[140px]">Jabatan / Posisi</th>
                            <th class="border border-slate-200 px-3 py-2 text-center w-20">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="filteredAlumniList.length === 0">
                            <td colspan="7" class="border border-slate-200 p-6 text-center text-slate-400 italic">
                                Belum ada data alumni yang tercatat aktif bekerja pada institusi ini.
                            </td>
                        </tr>
                        <tr
                            v-for="(alumni, idx) in filteredAlumniList"
                            :key="alumni.id || idx"
                            class="hover:bg-slate-50/80 transition-colors"
                        >
                            <td class="border border-slate-200 px-3 py-2 text-center text-slate-600 font-medium">
                                {{ idx + 1 }}
                            </td>
                            <td class="border border-slate-200 px-3 py-2 font-bold text-slate-900">
                                {{ getAlumniDisplayName(alumni) }}
                            </td>
                            <td class="border border-slate-200 px-3 py-2 font-mono text-slate-700">
                                {{ alumni.nim || '-' }}
                            </td>
                            <td class="border border-slate-200 px-3 py-2 text-slate-700">
                                {{ getAlumniProdi(alumni) }}
                            </td>
                            <td class="border border-slate-200 px-3 py-2 text-center text-slate-700">
                                {{ getAlumniAngkatan(alumni) }}
                            </td>
                            <td class="border border-slate-200 px-3 py-2 text-slate-800 font-medium">
                                {{ getAlumniJabatan(alumni) }}
                            </td>
                            <td class="border border-slate-200 px-3 py-2 text-center">
                                <Link
                                    v-if="alumni.id"
                                    :href="alumniDetailPath(alumni.id)"
                                    class="inline-flex items-center justify-center p-1.5 text-blue-700 hover:bg-blue-50 rounded transition-colors"
                                    title="Lihat Detail Profil Alumni"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </Link>
                                <span v-else class="text-slate-400 text-[11px]">-</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Footer Modal -->
            <div class="px-6 py-3 border-t border-slate-200 bg-slate-50 rounded-b-2xl flex justify-end">
                <button
                    type="button"
                    @click="closeAlumniModal"
                    class="px-5 py-2 bg-slate-700 hover:bg-slate-800 text-white font-bold rounded-xl text-xs transition-colors cursor-pointer"
                >
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL 2: PENINJAUAN LENGKAP USULAN PERUSAHAAN             -->
    <!-- ======================================================== -->
    <div
        v-if="showReviewModal && activeCompanyForReview"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs p-4"
    >
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-3xl max-h-[92vh] flex flex-col">
            <!-- Header Modal -->
            <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50 rounded-t-2xl">
                <div>
                    <h3 class="text-base font-bold text-slate-900">
                        Peninjauan Usulan Perusahaan Alumni
                    </h3>
                    <p class="text-xs text-slate-600 mt-0.5">
                        Verifikasi keabsahan data usulan institusi kerja dan konsolidasi terhadap master data terdaftar.
                    </p>
                </div>
                <button
                    type="button"
                    @click="closeReviewModal"
                    class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-200 transition-colors cursor-pointer"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Badan Modal Scrollable -->
            <div class="overflow-y-auto p-6 space-y-5 text-xs flex-1">
                <!-- 1. Data Usulan Institusi -->
                <div class="border border-slate-200 rounded-xl p-4 bg-slate-50/70 space-y-2.5">
                    <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                        <span class="font-bold text-slate-700 tracking-wider text-[11px] uppercase">
                            1. DATA USULAN INSTITUSI KERJA
                        </span>
                        <span
                            class="px-2.5 py-0.5 rounded text-[11px] font-semibold border"
                            :class="activeCompanyForReview.status_verifikasi === 'Terverifikasi' ? 'bg-slate-100 text-[#0D542B] border-[#0D542B]/40' : 'bg-slate-100 text-slate-800 border-slate-300'"
                        >
                            {{ activeCompanyForReview.status_verifikasi || 'Menunggu Peninjauan' }}
                        </span>
                    </div>

                    <div>
                        <span class="text-slate-500 text-[11px] block">Nama Institusi / Perusahaan:</span>
                        <div class="text-base font-bold text-slate-900">{{ activeCompanyForReview.nama_perusahaan }}</div>
                    </div>

                    <div>
                        <span class="text-slate-500 text-[11px] block">Alamat Lengkap Kantor:</span>
                        <div class="font-medium text-slate-900">{{ activeCompanyForReview.alamat || 'Alamat tidak dicantumkan' }}</div>
                    </div>

                    <div class="pt-2 border-t border-slate-200">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-2 text-[11px]">
                            <div
                                v-for="field in getCompanyDetailFields(activeCompanyForReview)"
                                :key="field.label"
                                class="grid grid-cols-[minmax(130px,0.7fr)_minmax(0,1fr)] gap-2 border-b border-slate-100 py-1.5"
                            >
                                <span class="text-slate-500">{{ field.label }}</span>
                                <strong class="text-slate-900 break-words">{{ field.value }}</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Data Alumni Terkait / Pengaju -->
                <div class="space-y-2">
                    <span class="font-bold text-slate-700 tracking-wider text-[11px] uppercase block">
                        2. DATA ALUMNI TERKAIT / PENGAJU
                    </span>
                    <div class="border border-slate-200 rounded-xl overflow-hidden bg-white">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-50 text-slate-800 font-bold border-b border-slate-200 text-[11px]">
                                    <th class="px-3 py-2 border-r border-slate-200">Nama Lengkap Alumni</th>
                                    <th class="px-3 py-2 border-r border-slate-200 w-28">NIM</th>
                                    <th class="px-3 py-2 border-r border-slate-200 min-w-[140px]">Program Studi</th>
                                    <th class="px-3 py-2 border-r border-slate-200 w-24 text-center">Angkatan</th>
                                    <th class="px-3 py-2 border-r border-slate-200 min-w-[130px]">Posisi / Jabatan</th>
                                    <th class="px-3 py-2 text-center w-16">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-if="getCompanyAlumniList(activeCompanyForReview).length === 0">
                                    <td colspan="6" class="p-4 text-center text-slate-400 italic">
                                        Tidak ada data alumni yang tercatat mengajukan usulan ini.
                                    </td>
                                </tr>
                                <tr
                                    v-for="(alumni, aIdx) in getCompanyAlumniList(activeCompanyForReview)"
                                    :key="alumni.id || aIdx"
                                    class="hover:bg-slate-50"
                                >
                                    <td class="px-3 py-2 font-bold text-slate-900 border-r border-slate-100">
                                        {{ getAlumniDisplayName(alumni) }}
                                    </td>
                                    <td class="px-3 py-2 font-mono text-slate-700 border-r border-slate-100">
                                        {{ alumni.nim || '-' }}
                                    </td>
                                    <td class="px-3 py-2 text-slate-700 border-r border-slate-100">
                                        {{ getAlumniProdi(alumni) }}
                                    </td>
                                    <td class="px-3 py-2 text-center text-slate-700 border-r border-slate-100">
                                        {{ getAlumniAngkatan(alumni) }}
                                    </td>
                                    <td class="px-3 py-2 text-slate-800 font-medium border-r border-slate-100">
                                        {{ getAlumniJabatan(alumni) }}
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <Link
                                            v-if="alumni.id"
                                            :href="alumniDetailPath(alumni.id)"
                                            class="inline-flex items-center justify-center p-1 text-blue-700 hover:bg-blue-50 rounded transition-colors"
                                            title="Buka Profil Lengkap Alumni"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </Link>
                                        <span v-else class="text-slate-400 text-[11px]">-</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 3. Rekomendasi Master Kemiripan -->
                <div class="space-y-2">
                    <span class="font-bold text-slate-700 tracking-wider text-[11px] uppercase block">
                        3. REKOMENDASI MASTER TERVERIFIKASI (KONSOLIDASI DATA)
                    </span>

                    <!-- Indikator Loading Rekomendasi Asinkron -->
                    <div
                        v-if="isLoadingRecommendations || activeCompanyForReview.isLoadingRecommendations"
                        class="p-4 bg-slate-50 border border-slate-200 rounded-xl text-center text-xs text-slate-500 animate-pulse"
                    >
                        <div class="inline-flex items-center gap-2 text-slate-700 font-medium">
                            <svg class="animate-spin h-4 w-4 text-[#0D542B]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                             <span>Menyiapkan rekomendasi master terkait...</span>
                        </div>
                    </div>

                    <div
                        v-else-if="activeCompanyForReview.recommendations && activeCompanyForReview.recommendations.length > 0"
                        class="border border-slate-200 rounded-xl overflow-hidden bg-white"
                    >
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-50 text-slate-800 font-bold border-b border-slate-200 text-[11px]">
                                    <th class="px-3 py-2 border-r border-slate-200">Nama Master Resmi</th>
                                    <th class="px-3 py-2 border-r border-slate-200 min-w-[200px]">Wilayah Kantor</th>
                                    <th class="px-3 py-2 text-center w-36">Aksi Konsolidasi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="rec in activeCompanyForReview.recommendations"
                                    :key="rec.id"
                                    class="hover:bg-slate-50"
                                >
                                    <td class="px-3 py-2 font-bold text-slate-900 border-r border-slate-100">
                                        <div class="flex items-center justify-between gap-2">
                                            <span>{{ rec.nama_perusahaan }}</span>
                                            <button
                                                type="button"
                                                @click="openRecommendationDetail(rec)"
                                                class="inline-flex items-center justify-center w-5 h-5 shrink-0 rounded border border-slate-300 text-slate-600 hover:text-[#0D542B] hover:border-[#0D542B] transition-colors cursor-pointer"
                                                title="Lihat detail rekomendasi master"
                                                aria-label="Lihat detail rekomendasi master"
                                            >
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <circle cx="12" cy="12" r="9" stroke-width="2" />
                                                    <path stroke-linecap="round" stroke-width="2" d="M12 11v5m0-8h.01" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-slate-600 border-r border-slate-100">
                                        {{ rec.nama_kabupaten || '-' }}, {{ rec.nama_provinsi || '-' }}
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <button
                                            type="button"
                                            @click="openConsolidateModal(activeCompanyForReview, rec)"
                                            class="px-2.5 py-1 bg-[#0D542B] hover:bg-[#08381c] text-white font-bold rounded-lg text-[11px] transition-colors cursor-pointer"
                                        >
                                            Sinkronkan
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-500 italic text-[11px]">
                        Tidak ditemukan master terverifikasi dengan nama yang serupa dalam basis data. Usulan ini layak diverifikasi langsung sebagai master baru.
                    </div>
                </div>
            </div>

            <!-- Footer Modal Aksi -->
            <div class="px-6 py-3.5 border-t border-slate-200 bg-slate-50 rounded-b-2xl flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="openEditModal(activeCompanyForReview)"
                        class="px-4 py-2 bg-slate-700 hover:bg-slate-800 text-white font-bold rounded-xl text-xs transition-colors cursor-pointer"
                    >
                        Sunting Data
                    </button>
                    <select
                        aria-label="Pilih keputusan usulan perusahaan"
                        class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 outline-none transition focus:border-[#0D542B] focus:ring-2 focus:ring-[#0D542B]/10 cursor-pointer"
                        @change="handleVerificationAction(activeCompanyForReview, $event)"
                    >
                        <option value="">Pilih keputusan</option>
                        <option v-if="activeCompanyForReview.status_verifikasi !== 'Terverifikasi'" value="approve">Terima sebagai master resmi</option>
                        <option v-if="activeCompanyForReview.status_verifikasi !== 'Ditolak'" value="reject">Tolak usulan</option>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="closeReviewModal"
                        class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-800 font-bold rounded-xl text-xs transition-colors cursor-pointer"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL 3: KONSOLIDASI MASTER TERVERIFIKASI                 -->
    <!-- ======================================================== -->
    <div
        v-if="showConsolidateModal && activePendingCompany && activeTargetVerifiedCompany"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs p-4"
    >
        <div class="bg-white rounded-2xl shadow-xl border border-slate-300 w-full max-w-2xl flex flex-col">
            <!-- Header Modal -->
            <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50 rounded-t-2xl">
                <div>
                    <h3 class="text-base font-bold text-slate-900">
                        Konsolidasi ke Master Terverifikasi
                    </h3>
                    <p class="text-xs text-slate-600 mt-0.5">
                        Pencegahan duplikasi data institusi mitra dengan pengalihan ke master resmi universitas.
                    </p>
                </div>
                <button
                    type="button"
                    @click="closeConsolidateModal"
                    class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-200 transition-colors cursor-pointer"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Badan Modal: Perbandingan Institusional -->
            <div class="p-6 space-y-4 text-xs">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Sisi Kiri: Usulan Alumni -->
                    <div class="p-4 bg-slate-50 border border-slate-300 rounded-xl space-y-1.5">
                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">
                            USULAN PENGAJUAN ALUMNI
                        </span>
                        <div class="text-sm font-bold text-rose-800">
                            {{ activePendingCompany.nama_perusahaan }}
                        </div>
                        <div class="text-slate-600 text-[11px]">
                            {{ activePendingCompany.alamat || 'Alamat tidak ada' }}
                        </div>
                        <div class="text-slate-500 text-[11px]">
                            {{ activePendingCompany.kabupaten?.nama_kabupaten || '-' }}, {{ activePendingCompany.propinsi?.nama_provinsi || '-' }}
                        </div>
                    </div>

                    <!-- Sisi Kanan: Master Resmi -->
                    <div class="p-4 bg-slate-50 border border-[#0D542B]/40 rounded-xl space-y-1.5">
                        <span class="text-[10px] font-bold text-[#0D542B] uppercase tracking-wider block">
                            DIALIHKAN KE MASTER RESMI
                        </span>
                        <div class="text-sm font-bold text-[#0D542B]">
                            {{ activeTargetVerifiedCompany.nama_perusahaan }}
                        </div>
                        <div class="text-slate-600 text-[11px]">
                            {{ activeTargetVerifiedCompany.alamat || 'Alamat resmi tercatat' }}
                        </div>
                        <div class="text-slate-500 text-[11px]">
                            {{ activeTargetVerifiedCompany.nama_kabupaten || activeTargetVerifiedCompany.kabupaten?.nama_kabupaten || '-' }}, {{ activeTargetVerifiedCompany.nama_provinsi || activeTargetVerifiedCompany.propinsi?.nama_provinsi || '-' }}
                        </div>
                    </div>
                </div>

                <!-- Keterangan Formal Sistem -->
                <div class="p-3 bg-slate-100 border border-slate-300 rounded-xl text-slate-700 leading-relaxed text-[11px]">
                    Seluruh relasi profil, riwayat karir, dan jawaban tracer study alumni yang terhubung dengan usulan ini akan otomatis dialihkan ke institusi master resmi pilihan. Usulan pengajuan akan ditutup untuk menjaga kebersihan pangkalan data.
                </div>
            </div>

            <!-- Footer Modal -->
            <div class="px-6 py-3 border-t border-slate-200 bg-slate-50 rounded-b-2xl flex justify-end gap-2.5">
                <button
                    type="button"
                    @click="closeConsolidateModal"
                    class="px-5 py-2 bg-slate-200 hover:bg-slate-300 text-slate-800 font-bold rounded-xl text-xs transition-colors cursor-pointer"
                >
                    Batal
                </button>
                <button
                    type="button"
                    :disabled="isSubmittingConsolidate"
                    @click="submitConsolidate"
                    class="px-6 py-2 bg-[#0D542B] hover:bg-[#08381c] text-white font-bold rounded-xl text-xs transition-colors shadow-xs cursor-pointer disabled:opacity-50"
                >
                    {{ isSubmittingConsolidate ? 'Memproses Konsolidasi...' : 'Konfirmasi Konsolidasi Data' }}
                </button>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL 4: PENOLAKAN USULAN (TOMBOL X DENGAN ALASAN)       -->
    <!-- ======================================================== -->
    <div
        v-if="showRejectModal && activeCompanyForReject"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs p-4"
    >
        <div class="bg-white rounded-2xl shadow-xl border border-slate-300 w-full max-w-lg flex flex-col">
            <!-- Header Modal -->
            <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50 rounded-t-2xl">
                <div>
                    <h3 class="text-base font-bold text-slate-900">
                        Tolak Usulan Institusi (Tombol X)
                    </h3>
                    <p class="text-xs text-slate-600 mt-0.5">
                        Berikan alasan penolakan usulan untuk dicatat pada log audit sistem.
                    </p>
                </div>
                <button
                    type="button"
                    @click="closeRejectModal"
                    class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-200 transition-colors cursor-pointer"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Form Penolakan -->
            <div class="p-6 space-y-3.5 text-xs">
                <div>
                    <span class="text-slate-500 block text-[11px]">Nama Institusi Usulan:</span>
                    <strong class="text-sm font-bold text-slate-900">{{ activeCompanyForReject.nama_perusahaan }}</strong>
                </div>

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">
                        Pilih Alasan Standar Penolakan:
                    </label>
                    <div class="flex flex-wrap gap-1.5 mb-2">
                        <button
                            type="button"
                            @click="setQuickReason('Duplikat dengan master resmi yang sudah terdaftar')"
                            class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg border border-slate-300 text-[10px] font-medium cursor-pointer"
                        >
                            Duplikat Master
                        </button>
                        <button
                            type="button"
                            @click="setQuickReason('Nama perusahaan fiktif atau tidak valid')"
                            class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg border border-slate-300 text-[10px] font-medium cursor-pointer"
                        >
                            Nama Tidak Valid
                        </button>
                        <button
                            type="button"
                            @click="setQuickReason('Data alamat dan wilayah tidak lengkap/jelas')"
                            class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg border border-slate-300 text-[10px] font-medium cursor-pointer"
                        >
                            Data Tidak Lengkap
                        </button>
                    </div>

                    <label class="block font-semibold text-slate-700 mb-1">
                        Catatan / Alasan Penolakan:
                    </label>
                    <textarea
                        v-model="rejectReason"
                        rows="3"
                        placeholder="Tuliskan catatan alasan penolakan pengajuan perusahaan..."
                        class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                    ></textarea>
                </div>
            </div>

            <!-- Footer Modal -->
            <div class="px-6 py-3 border-t border-slate-200 bg-slate-50 rounded-b-2xl flex justify-end gap-2.5">
                <button
                    type="button"
                    @click="closeRejectModal"
                    class="px-5 py-2 bg-slate-200 hover:bg-slate-300 text-slate-800 font-bold rounded-xl text-xs transition-colors cursor-pointer"
                >
                    Batal
                </button>
                <button
                    type="button"
                    :disabled="isSubmittingReject"
                    @click="submitReject"
                    class="px-6 py-2 bg-rose-700 hover:bg-rose-800 text-white font-bold rounded-xl text-xs transition-colors shadow-xs cursor-pointer disabled:opacity-50"
                >
                    {{ isSubmittingReject ? 'Menolak Usulan...' : 'Konfirmasi Tolak Usulan' }}
                </button>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL 5: TAMBAH PERUSAHAAN BARU (IDENTIK GAMBAR 1 & 2)   -->
    <!-- ======================================================== -->
    <div
        v-if="showCreateModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs p-4"
    >
        <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-xl max-h-[92vh] flex flex-col">
            <!-- Header Modal: Centered Title persis Gambar 1 & 2 -->
            <div class="pt-6 pb-2 px-6 text-center">
                <h3 class="text-xl font-bold text-slate-900 tracking-tight">
                    Tambah Perusahaan Baru
                </h3>
            </div>

            <!-- Form Badan Modal Scrollable -->
            <form @submit.prevent="submitCreateForm" class="overflow-y-auto px-6 py-4 space-y-4 text-xs flex-1">
                <!-- 1. Jenis Lokasi Perusahaan (Radio Button Sesuai Gambar 1 & 2) -->
                <div>
                    <label class="block font-semibold text-slate-700 mb-1.5">
                        Jenis Lokasi Perusahaan <span class="text-rose-600">*</span>
                    </label>
                    <div class="flex items-center gap-6">
                        <label class="flex items-center gap-2 cursor-pointer font-medium text-slate-800 text-xs select-none">
                            <input
                                type="radio"
                                value="Dalam Negeri"
                                v-model="createForm.jenis_lokasi"
                                class="w-4 h-4 text-[#0D542B] border-slate-300 focus:ring-[#0D542B] cursor-pointer"
                            />
                            <span>Dalam Negeri (Indonesia)</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer font-medium text-slate-800 text-xs select-none">
                            <input
                                type="radio"
                                value="Luar Negeri"
                                v-model="createForm.jenis_lokasi"
                                class="w-4 h-4 text-[#0D542B] border-slate-300 focus:ring-[#0D542B] cursor-pointer"
                            />
                            <span>Luar Negeri (Abroad)</span>
                        </label>
                    </div>
                </div>

                <!-- 2. Nama Perusahaan / Instansi * -->
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">
                        Nama Perusahaan / Instansi <span class="text-rose-600">*</span>
                    </label>
                    <input
                        type="text"
                        v-model="createForm.nama_perusahaan"
                        required
                        placeholder="Contoh: PT Teknologi Nusantara / Google Singapore"
                        class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                    />
                </div>

                <!-- 3. Jenis Perusahaan / Instansi (F11) -->
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">
                        Jenis Perusahaan / Instansi (F11)
                    </label>
                    <select
                        v-model="createForm.jenis_perusahaan"
                        class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-xs bg-white text-slate-800 focus:outline-none focus:ring-1 focus:ring-[#0D542B] cursor-pointer"
                    >
                        <option value="1 - Instansi pemerintah">1 - Instansi pemerintah</option>
                        <option value="2 - Organisasi non-profit/LSM">2 - Organisasi non-profit/LSM</option>
                        <option value="3 - Perusahaan swasta">3 - Perusahaan swasta</option>
                        <option value="4 - BUMN / BUMD">4 - BUMN / BUMD</option>
                        <option value="5 - Institusi / Organisasi Multilateral">5 - Institusi / Organisasi Multilateral</option>
                        <option value="6 - Perguruan Tinggi / Lembaga Pendidikan">6 - Perguruan Tinggi / Lembaga Pendidikan</option>
                        <option value="7 - Usaha Mandiri / Wirausaha">7 - Usaha Mandiri / Wirausaha</option>
                        <option value="8 - Lainnya">8 - Lainnya</option>
                    </select>
                </div>

                <!-- 4. Kondisional: Provinsi & Kabupaten (Dalam Negeri) vs Negara Tempat Bekerja (Luar Negeri) -->
                <div v-if="createForm.jenis_lokasi === 'Dalam Negeri'" class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">
                            Provinsi <span class="text-rose-600">*</span>
                        </label>
                        <select
                            v-model="createForm.propinsi_id"
                            @change="handlePropinsiChangeCreate"
                            class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-xs bg-white text-slate-800 focus:outline-none focus:ring-1 focus:ring-[#0D542B] cursor-pointer"
                        >
                            <option value="">-- Pilih Provinsi --</option>
                            <option v-for="p in propinsis" :key="p.id" :value="p.id">
                                {{ p.nama_provinsi }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">
                            Kabupaten / Kota <span class="text-rose-600">*</span>
                        </label>
                        <select
                            v-model="createForm.kabupaten_id"
                            :disabled="!createForm.propinsi_id || isLoadingKabupatenCreate"
                            class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-xs bg-white text-slate-800 focus:outline-none focus:ring-1 focus:ring-[#0D542B] cursor-pointer disabled:bg-slate-100 disabled:cursor-not-allowed"
                        >
                            <option value="">
                                {{ isLoadingKabupatenCreate ? 'Memuat kabupaten/kota...' : (createForm.propinsi_id ? '-- Pilih Kabupaten / Kota --' : '-- Pilih Provinsi Dahulu --') }}
                            </option>
                            <option v-for="k in availableKabupatensForCreate" :key="k.id" :value="k.id">
                                {{ k.nama_kabupaten }}
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Luar Negeri: Dropdown Negara Tempat Bekerja (Persis Gambar 2) -->
                <div v-else>
                    <label class="block font-semibold text-slate-700 mb-1">
                        Negara Tempat Bekerja <span class="text-rose-600">*</span>
                    </label>
                    <select
                        v-model="createForm.negara"
                        required
                        class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-xs bg-white text-slate-800 focus:outline-none focus:ring-1 focus:ring-[#0D542B] cursor-pointer"
                    >
                        <option value="">-- Pilih Negara di Luar Negeri --</option>
                        <option v-for="n in negaras" :key="n.id" :value="n.nama_negara">
                            {{ n.nama_negara }}
                        </option>
                    </select>
                </div>

                <!-- 5. Kode Pos Perusahaan & Skala Perusahaan (Grid 2 Kolom Persis Gambar 1 & 2) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">
                            Kode Pos Perusahaan
                        </label>
                        <input
                            type="text"
                            v-model="createForm.kode_pos"
                            placeholder="55281"
                            class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                        />
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">
                            Skala Perusahaan
                        </label>
                        <select
                            v-model="createForm.skala"
                            class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-xs bg-white text-slate-800 focus:outline-none focus:ring-1 focus:ring-[#0D542B] cursor-pointer"
                        >
                            <option value="Lokal">Lokal</option>
                            <option value="Nasional">Nasional</option>
                            <option value="Internasional">Internasional</option>
                        </select>
                    </div>
                </div>

                <!-- 6. Alamat Jalan / Gedung Perusahaan (Textarea Persis Gambar 1 & 2) -->
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">
                        Alamat Jalan / Gedung Perusahaan
                    </label>
                    <textarea
                        v-model="createForm.alamat"
                        rows="3"
                        placeholder="Nama Jalan, Gedung, Nomor..."
                        class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                    ></textarea>
                </div>

                <!-- 7. Tombol Footer Centered (Persis Gambar 1 & 2) -->
                <div class="pt-3 pb-2 flex items-center justify-center gap-3">
                    <button
                        type="submit"
                        :disabled="isSubmittingCreate"
                        class="px-6 py-2.5 bg-[#005833] hover:bg-[#004226] text-white font-bold rounded-lg text-xs shadow-xs transition-colors cursor-pointer disabled:opacity-50"
                    >
                        {{ isSubmittingCreate ? 'Menyimpan...' : 'Simpan Perusahaan' }}
                    </button>
                    <button
                        type="button"
                        @click="closeCreateModal"
                        class="px-6 py-2.5 bg-[#8C98A4] hover:bg-[#788592] text-white font-bold rounded-lg text-xs transition-colors cursor-pointer"
                    >
                        Batal
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL 6: SUNTING DATA PERUSAHAAN                         -->
    <!-- ======================================================== -->
    <div
        v-if="showEditModal && editForm.id"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs p-4"
    >
        <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-xl max-h-[92vh] flex flex-col">
            <!-- Header Modal -->
            <div class="pt-6 pb-2 px-6 text-center">
                <h3 class="text-xl font-bold text-slate-900 tracking-tight">
                    Sunting Data Perusahaan
                </h3>
            </div>

            <!-- Form Badan Modal -->
            <form @submit.prevent="submitEditForm" class="overflow-y-auto px-6 py-4 space-y-4 text-xs flex-1">
                <!-- Jenis Lokasi -->
                <div>
                    <label class="block font-semibold text-slate-700 mb-1.5">
                        Jenis Lokasi Perusahaan <span class="text-rose-600">*</span>
                    </label>
                    <div class="flex items-center gap-6">
                        <label class="flex items-center gap-2 cursor-pointer font-medium text-slate-800 text-xs select-none">
                            <input
                                type="radio"
                                value="Dalam Negeri"
                                v-model="editForm.jenis_lokasi"
                                class="w-4 h-4 text-[#0D542B] border-slate-300 focus:ring-[#0D542B] cursor-pointer"
                            />
                            <span>Dalam Negeri (Indonesia)</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer font-medium text-slate-800 text-xs select-none">
                            <input
                                type="radio"
                                value="Luar Negeri"
                                v-model="editForm.jenis_lokasi"
                                class="w-4 h-4 text-[#0D542B] border-slate-300 focus:ring-[#0D542B] cursor-pointer"
                            />
                            <span>Luar Negeri (Abroad)</span>
                        </label>
                    </div>
                </div>

                <!-- Nama Perusahaan -->
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">
                        Nama Perusahaan / Instansi <span class="text-rose-600">*</span>
                    </label>
                    <input
                        type="text"
                        v-model="editForm.nama_perusahaan"
                        required
                        class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                    />
                </div>

                <!-- Jenis F11 -->
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">
                        Jenis Perusahaan / Instansi (F11)
                    </label>
                    <select
                        v-model="editForm.jenis_perusahaan"
                        class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-xs bg-white text-slate-800 focus:outline-none focus:ring-1 focus:ring-[#0D542B] cursor-pointer"
                    >
                        <option value="1 - Instansi pemerintah">1 - Instansi pemerintah</option>
                        <option value="2 - Organisasi non-profit/LSM">2 - Organisasi non-profit/LSM</option>
                        <option value="3 - Perusahaan swasta">3 - Perusahaan swasta</option>
                        <option value="4 - BUMN / BUMD">4 - BUMN / BUMD</option>
                        <option value="5 - Institusi / Organisasi Multilateral">5 - Institusi / Organisasi Multilateral</option>
                        <option value="6 - Perguruan Tinggi / Lembaga Pendidikan">6 - Perguruan Tinggi / Lembaga Pendidikan</option>
                        <option value="7 - Usaha Mandiri / Wirausaha">7 - Usaha Mandiri / Wirausaha</option>
                        <option value="8 - Lainnya">8 - Lainnya</option>
                    </select>
                </div>

                <!-- Provinsi & Kabupaten vs Negara -->
                <div v-if="editForm.jenis_lokasi === 'Dalam Negeri'" class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">
                            Provinsi <span class="text-rose-600">*</span>
                        </label>
                        <select
                            v-model="editForm.propinsi_id"
                            @change="handlePropinsiChangeEdit"
                            class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-xs bg-white text-slate-800 focus:outline-none focus:ring-1 focus:ring-[#0D542B] cursor-pointer"
                        >
                            <option value="">-- Pilih Provinsi --</option>
                            <option v-for="p in propinsis" :key="p.id" :value="p.id">
                                {{ p.nama_provinsi }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">
                            Kabupaten / Kota <span class="text-rose-600">*</span>
                        </label>
                        <select
                            v-model="editForm.kabupaten_id"
                            :disabled="!editForm.propinsi_id || isLoadingKabupatenEdit"
                            class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-xs bg-white text-slate-800 focus:outline-none focus:ring-1 focus:ring-[#0D542B] cursor-pointer disabled:bg-slate-100 disabled:cursor-not-allowed"
                        >
                            <option value="">
                                {{ isLoadingKabupatenEdit ? 'Memuat kabupaten/kota...' : (editForm.propinsi_id ? '-- Pilih Kabupaten / Kota --' : '-- Pilih Provinsi Dahulu --') }}
                            </option>
                            <option v-for="k in availableKabupatensForEdit" :key="k.id" :value="k.id">
                                {{ k.nama_kabupaten }}
                            </option>
                        </select>
                    </div>
                </div>

                <div v-else>
                    <label class="block font-semibold text-slate-700 mb-1">
                        Negara Tempat Bekerja <span class="text-rose-600">*</span>
                    </label>
                    <select
                        v-model="editForm.negara"
                        required
                        class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-xs bg-white text-slate-800 focus:outline-none focus:ring-1 focus:ring-[#0D542B] cursor-pointer"
                    >
                        <option value="">-- Pilih Negara di Luar Negeri --</option>
                        <option v-for="n in negaras" :key="n.id" :value="n.nama_negara">
                            {{ n.nama_negara }}
                        </option>
                    </select>
                </div>

                <!-- Kode Pos & Skala -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">
                            Kode Pos Perusahaan
                        </label>
                        <input
                            type="text"
                            v-model="editForm.kode_pos"
                            class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                        />
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">
                            Skala Perusahaan
                        </label>
                        <select
                            v-model="editForm.skala"
                            class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-xs bg-white text-slate-800 focus:outline-none focus:ring-1 focus:ring-[#0D542B] cursor-pointer"
                        >
                            <option value="Lokal">Lokal</option>
                            <option value="Nasional">Nasional</option>
                            <option value="Internasional">Internasional</option>
                        </select>
                    </div>
                </div>

                <!-- Alamat -->
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">
                        Alamat Jalan / Gedung Perusahaan
                    </label>
                    <textarea
                        v-model="editForm.alamat"
                        rows="3"
                        class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                    ></textarea>
                </div>

                <!-- Footer Centered -->
                <div class="pt-3 pb-2 flex items-center justify-center gap-3">
                    <button
                        type="submit"
                        :disabled="isSubmittingEdit"
                        class="px-6 py-2.5 bg-[#005833] hover:bg-[#004226] text-white font-bold rounded-lg text-xs shadow-xs transition-colors cursor-pointer disabled:opacity-50"
                    >
                        {{ isSubmittingEdit ? 'Menyimpan...' : 'Simpan Perubahan' }}
                    </button>
                    <button
                        type="button"
                        @click="closeEditModal"
                        class="px-6 py-2.5 bg-[#8C98A4] hover:bg-[#788592] text-white font-bold rounded-lg text-xs transition-colors cursor-pointer"
                    >
                        Batal
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL VUE: DETAIL REKOMENDASI MASTER PERUSAHAAN          -->
    <!-- ======================================================== -->
    <Teleport to="body">
        <div
            v-if="showRecommendationModal && activeRecommendation"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs p-4"
        >
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-lg max-h-[85vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-gray-900">Detail Rekomendasi Master</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Informasi lengkap data profil perusahaan master terdaftar.</p>
                    </div>
                    <button
                        type="button"
                        @click="closeRecommendationModal"
                        class="p-1 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="overflow-y-auto px-6 py-4 divide-y divide-gray-100 text-xs">
                    <div
                        v-for="field in getCompanyDetailFields(activeRecommendation)"
                        :key="field.label"
                        class="grid grid-cols-[160px_1fr] gap-3 py-2.5"
                    >
                        <span class="text-gray-500 font-medium">{{ field.label }}</span>
                        <strong class="text-gray-900 font-semibold break-words">{{ field.value }}</strong>
                    </div>
                </div>

                <div class="px-6 py-3 border-t border-gray-100 bg-gray-50 flex justify-end">
                    <button
                        type="button"
                        @click="closeRecommendationModal"
                        class="px-4 py-2 bg-[#0D542B] hover:bg-[#08381c] text-white text-xs font-semibold rounded-lg transition-colors cursor-pointer"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
