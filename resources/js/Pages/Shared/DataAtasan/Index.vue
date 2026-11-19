<!--
  Halaman master Data Atasan berbasis relasi satu alumni dengan satu atasan.
  Setiap baris berasal dari profil alumni, sehingga nama atasan dapat berulang.
-->
<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AdminSidebar from '@/components/AdminSidebar.vue';
import PaginationControls from '@/components/PaginationControls.vue';

// Kontrak payload direktori relasi alumni-atasan dari controller bersama.
const props = defineProps({
    user: { type: Object, default: () => ({}) },
    role: { type: String, default: 'superadmin' },
    roleTitle: { type: String, default: 'Super Administrator' },
    prefix: { type: String, default: 'superadmin' },
    scopeLabel: { type: String, default: 'Seluruh universitas' },
    showProdiFilter: { type: Boolean, default: true },
    atasans: { type: Object, default: () => ({ data: [], links: [] }) },
    prodis: { type: Array, default: () => [] },
    provinces: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ search: '', company: '', provinsi: '', prodi_id: '', per_page: 10 }) },
    stats: {
        type: Object,
        default: () => ({
            total_data_atasan: 0,
            alumni_terhubung: 0,
            atasan_unik: 0,
            perusahaan_terhubung: 0,
            evaluasi_selesai: 0,
        }),
    },
    masterOptions: {
        type: Object,
        default: () => ({ alumni: [], perusahaan: [], propinsi: [], kabupaten: [], negara: [] }),
    },
});

// State filter lokal mengikuti pola filter pada master perusahaan.
const search = ref(props.filters.search || '');
const companySearch = ref(props.filters.company || '');
const selectedProvinsi = ref(props.filters.provinsi || '');
const selectedProdiId = ref(props.filters.prodi_id || '');
const perPage = ref(Number(props.filters.per_page || 10));

// State searchable dropdown filter perusahaan head office
const showCompanyFilterDropdown = ref(false);

const filteredCompanyFilterOptions = computed(() => {
    const query = companySearch.value.trim().toLowerCase();
    const list = props.masterOptions?.perusahaan || [];
    if (!query) {
        return list.slice(0, 30);
    }
    return list.filter((c) => {
        return (c.nama_perusahaan && c.nama_perusahaan.toLowerCase().includes(query))
            || (c.alamat && c.alamat.toLowerCase().includes(query));
    }).slice(0, 30);
});

const selectCompanyFilter = (companyName) => {
    companySearch.value = companyName;
    showCompanyFilterDropdown.value = false;
    applyFilters();
};

const clearCompanyFilter = () => {
    companySearch.value = '';
    showCompanyFilterDropdown.value = false;
    applyFilters();
};

// State modal detail satu relasi alumni-atasan.
const showDetailModal = ref(false);
const activeRecord = ref(null);
const showAtasanFormModal = ref(false);
const isSubmittingAtasan = ref(false);
const atasanFormMode = ref('create');
const atasanForm = ref({
    id: null,
    biodata_id: '',
    perusahaan_id: '',
    nama: '',
    email: '',
    telepon: '',
});

// State pencarian alumni pada modal form atasan
const alumniSearch = ref('');
const showAlumniDropdown = ref(false);

const filteredAlumniOptions = computed(() => {
    const query = alumniSearch.value.trim().toLowerCase();
    const list = props.masterOptions?.alumni || [];
    if (!query) {
        return list.slice(0, 30);
    }
    return list.filter((a) => {
        return (a.nim && a.nim.toLowerCase().includes(query))
            || (a.nama && a.nama.toLowerCase().includes(query))
            || (a.prodi && a.prodi.toLowerCase().includes(query));
    }).slice(0, 30);
});

const selectedAlumni = computed(() => {
    if (!atasanForm.value.biodata_id) return null;
    return (props.masterOptions?.alumni || []).find((a) => a.id === atasanForm.value.biodata_id);
});

const selectAlumni = (alumni) => {
    atasanForm.value.biodata_id = alumni.id;
    if (alumni.perusahaan_id && !atasanForm.value.perusahaan_id) {
        atasanForm.value.perusahaan_id = alumni.perusahaan_id;
    }
    showAlumniDropdown.value = false;
    alumniSearch.value = '';
};

const clearSelectedAlumni = () => {
    atasanForm.value.biodata_id = '';
    alumniSearch.value = '';
};

// State modal Tambah Perusahaan Baru (Menunggu Verifikasi)
const showCreateCompanyModal = ref(false);
const isSubmittingCompany = ref(false);
const companyError = ref('');

const newCompanyForm = ref({
    jenis_lokasi: 'Dalam Negeri',
    nama_perusahaan: '',
    bentuk_perusahaan: 'PT (Perseroan Terbatas)',
    jenis_perusahaan: '3 - Perusahaan swasta',
    jenis_perusahaan_lainnya: '',
    kode_negara: '',
    kode_provinsi: '',
    kode_kabupaten: '',
    alamat: '',
    kode_pos: '',
    skala: 'Nasional',
    no_telp_fax: '',
});

const bentukPerusahaanOptions = [
    'PT (Perseroan Terbatas)',
    'CV (Commanditaire Vennootschap)',
    'Firma',
    'BUMN (Badan Usaha Milik Negara)',
    'BUMD (Badan Usaha Milik Daerah)',
    'Instansi Pemerintah / Kementerian',
    'Lembaga Non-Profit / Yayasan / LSM',
    'Organisasi Internasional / Multilateral',
    'Koperasi',
    'Usaha Dagang (UD) / Perorangan',
    'Lainnya',
];

const jenisPerusahaanOptions = [
    '1 - Instansi pemerintah',
    '2 - Organisasi non-profit / Lembaga Swadaya Masyarakat',
    '3 - Perusahaan swasta',
    '4 - Perusahaan BUMN / BUMD',
    '5 - Institusi / Organisasi Multilateral',
    '6 - Lembaga Pendidikan',
    '7 - Wirausaha / Perusahaan sendiri',
    '8 - Lainnya',
];

const availableKabupatens = computed(() => {
    if (!newCompanyForm.value.kode_provinsi) return [];
    return (props.masterOptions?.kabupaten || []).filter(
        (k) => k.kode_provinsi === newCompanyForm.value.kode_provinsi
    );
});

const openCreateCompanyModal = () => {
    newCompanyForm.value = {
        jenis_lokasi: 'Dalam Negeri',
        nama_perusahaan: '',
        bentuk_perusahaan: 'PT (Perseroan Terbatas)',
        jenis_perusahaan: '3 - Perusahaan swasta',
        jenis_perusahaan_lainnya: '',
        kode_negara: '',
        kode_provinsi: '',
        kode_kabupaten: '',
        alamat: '',
        kode_pos: '',
        skala: 'Nasional',
        no_telp_fax: '',
    };
    companyError.value = '';
    showCreateCompanyModal.value = true;
};

const closeCreateCompanyModal = () => {
    showCreateCompanyModal.value = false;
    isSubmittingCompany.value = false;
    companyError.value = '';
};

const submitNewCompany = async () => {
    if (!newCompanyForm.value.nama_perusahaan?.trim()) {
        companyError.value = 'Nama perusahaan wajib diisi.';
        return;
    }
    if (newCompanyForm.value.jenis_lokasi === 'Luar Negeri' && !newCompanyForm.value.kode_negara) {
        companyError.value = 'Negara tempat bekerja wajib dipilih untuk instansi luar negeri.';
        return;
    }

    isSubmittingCompany.value = true;
    companyError.value = '';

    try {
        const response = await fetch(`/${props.prefix}/atasan/perusahaan`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify(newCompanyForm.value),
        });

        const data = await response.json();
        if (response.ok && data.perusahaan) {
            props.masterOptions.perusahaan.unshift(data.perusahaan);
            atasanForm.value.perusahaan_id = data.perusahaan.id;
            closeCreateCompanyModal();
        } else {
            companyError.value = data.message || 'Gagal menyimpan perusahaan baru.';
        }
    } catch (e) {
        companyError.value = 'Terjadi kesalahan sistem saat menyimpan perusahaan.';
    } finally {
        isSubmittingCompany.value = false;
    }
};

// Menentukan judul halaman sesuai scope role admin aktif.
const pageTitle = computed(() => `Data Atasan - ${props.roleTitle}`);

// Menentukan apakah ada filter yang sedang aktif.
const hasActiveFilters = computed(() => Boolean(
    search.value || companySearch.value || selectedProvinsi.value || selectedProdiId.value || perPage.value !== 10
));

// Sinkronkan filter lokal saat server mengembalikan props baru setelah navigasi.
watch(() => props.filters, (newFilters) => {
    search.value = newFilters?.search || '';
    companySearch.value = newFilters?.company || '';
    selectedProvinsi.value = newFilters?.provinsi || '';
    selectedProdiId.value = newFilters?.prodi_id || '';
    perPage.value = Number(newFilters?.per_page || 10);
}, { deep: true });

// Format angka ringkasan dengan format Indonesia.
const formatNumber = (value) => new Intl.NumberFormat('id-ID').format(value || 0);

// Format waktu evaluasi bila evaluasi atasan sudah dikirim.
const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    if (Number.isNaN(date.getTime())) return '-';

    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
};

// Menentukan URL detail alumni sesuai role admin.
const alumniDetailPath = (alumniId) => {
    if (props.role === 'admin_fakultas') return `/fakultas/alumni/${alumniId}`;
    if (props.role === 'admin_prodi') return `/prodi/alumni/${alumniId}`;
    if (props.role === 'admin_biro3') return `/biro3/alumni/${alumniId}`;

    return `/superadmin/alumni/${alumniId}`;
};

// Membuka modal detail data atasan dari baris yang dipilih.
const openDetailModal = (record) => {
    activeRecord.value = record;
    showDetailModal.value = true;
};

// Menutup modal detail dan membersihkan baris aktif.
const closeDetailModal = () => {
    showDetailModal.value = false;
    activeRecord.value = null;
};

// Membuka form tambah relasi atasan baru pada profil alumni terpilih.
const openCreateAtasanModal = () => {
    atasanFormMode.value = 'create';
    atasanForm.value = {
        id: null,
        biodata_id: '',
        perusahaan_id: '',
        nama: '',
        email: '',
        telepon: '',
    };
    alumniSearch.value = '';
    showAlumniDropdown.value = false;
    showAtasanFormModal.value = true;
};

// Membuka form edit dengan data atasan yang sudah tampil pada direktori.
const openEditAtasanModal = (record) => {
    atasanFormMode.value = 'edit';
    atasanForm.value = {
        id: record.atasan?.id || null,
        biodata_id: record.alumni?.id || '',
        perusahaan_id: record.atasan?.perusahaan_id || record.perusahaan?.id || '',
        nama: record.atasan?.nama || '',
        email: record.atasan?.email || '',
        telepon: record.atasan?.telepon || '',
    };
    alumniSearch.value = '';
    showAlumniDropdown.value = false;
    showAtasanFormModal.value = true;
};

// Menutup form tambah atau edit dan mengembalikan state form ke kondisi bersih.
const closeAtasanFormModal = () => {
    showAtasanFormModal.value = false;
    isSubmittingAtasan.value = false;
};

// Menyimpan data atasan melalui endpoint sesuai mode form yang sedang aktif.
const submitAtasanForm = () => {
    isSubmittingAtasan.value = true;
    const endpoint = atasanFormMode.value === 'create'
        ? `/${props.prefix}/atasan`
        : `/${props.prefix}/atasan/${atasanForm.value.id}`;
    const payload = { ...atasanForm.value };
    const requestOptions = {
        preserveScroll: true,
        onSuccess: () => closeAtasanFormModal(),
        onError: () => { isSubmittingAtasan.value = false; },
    };

    if (atasanFormMode.value === 'create') {
        router.post(endpoint, payload, requestOptions);

        return;
    }

    router.put(endpoint, payload, requestOptions);
};

// Menentukan kelas badge status evaluasi.
const evaluationClass = (status) => {
    if (status === 'Selesai') return 'bg-emerald-50 text-emerald-800 border-emerald-200';
    if (status === 'Menunggu') return 'bg-amber-50 text-amber-800 border-amber-200';

    return 'bg-slate-100 text-slate-600 border-slate-200';
};

// Menentukan style badge evaluasi likert dan kesiapan kerja.
const getLikertBadgeStyle = (label) => {
    const l = String(label || '').toLowerCase();
    if (l.includes('sangat baik') || l.includes('sangat tinggi') || l.includes('sangat siap')) return 'bg-emerald-100 text-[#0D542B] border-emerald-300';
    if (l.includes('baik') || l.includes('tinggi') || l.includes('cukup siap')) return 'bg-emerald-50 text-[#2F7D4A] border-emerald-200';
    if (l.includes('cukup') || l.includes('sedang')) return 'bg-amber-50 text-amber-800 border-amber-200';
    if (l.includes('kurang') || l.includes('rendah') || l.includes('kurang siap')) return 'bg-orange-50 text-orange-800 border-orange-200';
    if (l.includes('sangat kurang') || l.includes('sangat rendah') || l.includes('tidak')) return 'bg-rose-50 text-rose-800 border-rose-200';
    return 'bg-slate-100 text-slate-700 border-slate-200';
};

// Mengirim filter direktori ke endpoint role aktif.
const applyFilters = () => {
    router.get(`/${props.prefix}/atasan`, {
        search: search.value || undefined,
        company: companySearch.value || undefined,
        provinsi: selectedProvinsi.value || undefined,
        prodi_id: props.showProdiFilter ? (selectedProdiId.value || undefined) : undefined,
        per_page: perPage.value !== 10 ? perPage.value : undefined,
    }, {
        preserveState: true,
        replace: true,
        preserveScroll: true,
    });
};

// Debounce pencarian agar server tidak menerima request pada setiap ketikan.
let searchTimeout = null;
const handleSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => applyFilters(), 400);
};

// Mengembalikan seluruh filter ke kondisi awal.
const resetFilters = () => {
    search.value = '';
    companySearch.value = '';
    showCompanyFilterDropdown.value = false;
    selectedProvinsi.value = '';
    selectedProdiId.value = '';
    perPage.value = 10;
    router.get(`/${props.prefix}/atasan`, {}, { preserveState: true, preserveScroll: true });
};

// URL unduh data atasan Excel dengan meneruskan parameter filter aktif
const exportUrl = computed(() => {
    const params = new URLSearchParams();
    if (search.value) params.append('search', search.value);
    if (companySearch.value) params.append('company', companySearch.value);
    if (selectedProvinsi.value) params.append('provinsi', selectedProvinsi.value);
    if (selectedProdiId.value) params.append('prodi_id', selectedProdiId.value);
    const qs = params.toString();
    return `/${props.prefix}/atasan/export-excel${qs ? `?${qs}` : ''}`;
});
</script>

<template>
    <Head :title="pageTitle" />

    <div class="min-h-screen min-w-[1280px] bg-[#F8FAFC] font-sans text-slate-800">
        <AdminSidebar :role="role" :user="user" active-menu="atasan" />

        <div class="flex min-h-screen flex-col lg:pl-72">
            <!-- Header institusional mengikuti struktur Master Data Perusahaan. -->
            <header class="border-b border-emerald-900/40 bg-[#0D542B] px-4 pb-16 pt-8 text-white sm:px-6 lg:px-8">
                <div class="flex w-full flex-col justify-between gap-6 md:flex-row md:items-center">
                    <div>
                        <nav class="mb-2.5 flex items-center gap-2 text-xs font-medium text-emerald-100">
                            <Link :href="`/${prefix}/dashboard`" class="transition-colors hover:text-white">Dashboard</Link>
                            <span class="text-emerald-300/60">/</span>
                            <span class="rounded-md bg-white/10 px-2 py-0.5 font-bold text-white">Data Atasan</span>
                        </nav>
                        <h1 class="text-2xl font-extrabold tracking-tight text-white sm:text-3xl lg:text-4xl">Master Data Atasan</h1>
                        <p class="mt-1.5 max-w-3xl text-xs font-normal leading-relaxed text-emerald-100/90 sm:text-sm">
                            Daftar relasi atasan yang diisi alumni melalui profil kerja. Satu baris mewakili satu alumni, sehingga atasan yang sama dapat tampil berulang.
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            v-if="['superadmin', 'admin_biro3', 'admin_fakultas', 'admin_prodi'].includes(role)"
                            type="button"
                            class="inline-flex w-fit items-center rounded-lg bg-[#FDC700] px-4 py-2 text-xs font-bold text-[#0D542B] transition-colors hover:bg-yellow-300"
                            @click="openCreateAtasanModal"
                        >
                            Tambah Data Atasan
                        </button>
                        <a
                            :href="exportUrl"
                            class="inline-flex w-fit items-center gap-1.5 rounded-lg bg-white px-4 py-2 text-xs font-bold text-[#0D542B] shadow-xs transition-colors hover:bg-emerald-50 cursor-pointer"
                            title="Unduh Rekap Data Atasan (.xls / .xlsx)"
                        >
                            <svg class="w-4 h-4 text-[#0D542B]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            <span>Download Data Atasan</span>
                        </a>
                    </div>
                </div>
            </header>

            <main class="-mt-10 w-full space-y-6 px-4 pb-12 sm:px-6 lg:px-8">
                <!-- Ringkasan data mengikuti konteks satu baris per alumni. -->
                <div class="grid grid-cols-2 gap-3.5 sm:gap-4 lg:grid-cols-4">
                    <div class="rounded-xl border border-slate-200 bg-white p-4.5 shadow-2xs">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Total Data Atasan</p>
                        <p class="mt-1.5 text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">{{ formatNumber(stats.total_data_atasan) }}</p>
                        <p class="mt-1 text-xs text-slate-500">Satu data per alumni</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-4.5 shadow-2xs">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-600">Alumni Terhubung</p>
                        <p class="mt-1.5 text-2xl font-extrabold tracking-tight text-slate-800 sm:text-3xl">{{ formatNumber(stats.alumni_terhubung) }}</p>
                        <p class="mt-1 text-xs text-slate-500">Profil alumni terisi atasan</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-4.5 shadow-2xs">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-600">Atasan Unik</p>
                        <p class="mt-1.5 text-2xl font-extrabold tracking-tight text-[#0D542B] sm:text-3xl">{{ formatNumber(stats.atasan_unik) }}</p>
                        <p class="mt-1 text-xs text-slate-500">Nilai nama/email yang tersimpan</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-4.5 shadow-2xs">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-600">Evaluasi Selesai</p>
                        <p class="mt-1.5 text-2xl font-extrabold tracking-tight text-slate-800 sm:text-3xl">{{ formatNumber(stats.evaluasi_selesai) }}</p>
                        <p class="mt-1 text-xs text-slate-500">Evaluasi dari relasi aktif</p>
                    </div>
                </div>

                <!-- Filter dan pencarian disusun dengan pola Master Data Perusahaan. -->
                <div class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-2xs">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-900">Filter & Pencarian Data Atasan</span>
                        <button v-if="hasActiveFilters" type="button" class="text-xs font-bold text-slate-500 transition-colors hover:text-[#0D542B]" @click="resetFilters">Reset Filter</button>
                    </div>
                    <div class="grid grid-cols-1 gap-3.5 text-xs sm:grid-cols-2 lg:grid-cols-5">
                        <div>
                            <label class="mb-1 block text-[11px] font-bold uppercase text-slate-600">Cari Alumni / Atasan</label>
                            <input v-model="search" type="search" placeholder="Nama, NIM, atau atasan..." class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#0D542B]" @input="handleSearch" />
                        </div>
                        <div>
                            <label class="mb-1 block text-[11px] font-bold uppercase text-slate-600">Perusahaan (Head Office)</label>
                            <div class="relative">
                                <input
                                    v-model="companySearch"
                                    type="search"
                                    placeholder="Cari & pilih kantor pusat..."
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 pr-7 text-xs font-medium focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                                    @focus="showCompanyFilterDropdown = true"
                                    @blur="setTimeout(() => { showCompanyFilterDropdown = false; }, 180)"
                                    @input="handleSearch"
                                />
                                <button
                                    type="button"
                                    class="absolute inset-y-0 right-0 px-2 text-slate-400 hover:text-slate-600"
                                    aria-label="Buka pilihan instansi kantor pusat"
                                    @click="showCompanyFilterDropdown = !showCompanyFilterDropdown"
                                >
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 9-7 7-7-7"/></svg>
                                </button>
                                <div
                                    v-if="showCompanyFilterDropdown"
                                    class="absolute z-30 mt-1 max-h-56 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-lg text-xs"
                                >
                                    <button
                                        type="button"
                                        class="block w-full rounded-lg px-3 py-2 text-left font-bold text-slate-700 hover:bg-slate-50"
                                        @mousedown.prevent="clearCompanyFilter"
                                    >
                                        Semua Perusahaan / Head Office
                                    </button>
                                    <button
                                        v-for="comp in filteredCompanyFilterOptions"
                                        :key="comp.id"
                                        type="button"
                                        class="block w-full rounded-lg px-3 py-2 text-left text-slate-700 hover:bg-emerald-50 hover:text-[#0D542B]"
                                        @mousedown.prevent="selectCompanyFilter(comp.nama_perusahaan)"
                                    >
                                        <div class="font-semibold">{{ comp.nama_perusahaan }}</div>
                                        <div v-if="comp.lokasi || comp.alamat" class="text-[10px] text-slate-500 truncate">{{ comp.lokasi || comp.alamat }}</div>
                                    </button>
                                    <p v-if="filteredCompanyFilterOptions.length === 0" class="px-3 py-2 text-xs text-slate-500 italic">Perusahaan tidak ditemukan.</p>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-[11px] font-bold uppercase text-slate-600">Lokasi Kerja (Domisili Alumni)</label>
                            <select v-model="selectedProvinsi" class="w-full cursor-pointer rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs font-medium focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#0D542B]" @change="applyFilters">
                                <option value="">Semua Wilayah Domisili</option>
                                <option value="LUAR_NEGERI">Luar Negeri (Semua)</option>
                                <option v-for="prov in (provinces.length > 0 ? provinces : (masterOptions.propinsi || []))" :key="prov.kode_provinsi" :value="prov.kode_provinsi">
                                    {{ prov.nama_provinsi }}
                                </option>
                            </select>
                        </div>
                        <div v-if="showProdiFilter">
                            <label class="mb-1 block text-[11px] font-bold uppercase text-slate-600">Program Studi</label>
                            <select v-model="selectedProdiId" class="w-full cursor-pointer rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs font-medium focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#0D542B]" @change="applyFilters">
                                <option value="">Semua Program Studi</option>
                                <option v-for="prodi in prodis" :key="prodi.id" :value="prodi.id">{{ prodi.kode_prodi }} · {{ prodi.nama_prodi }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-[11px] font-bold uppercase text-slate-600">Data per halaman</label>
                            <select v-model.number="perPage" class="w-full cursor-pointer rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs font-medium focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#0D542B]" @change="applyFilters">
                                <option :value="10">10</option>
                                <option :value="25">25</option>
                                <option :value="50">50</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Penanda kontrak data agar pengguna memahami pengulangan baris. -->
                <div class="rounded-xl border border-emerald-100 bg-emerald-50/70 px-4 py-3 text-xs leading-5 text-emerald-950">
                    <strong>Diisi oleh:</strong> Alumni melalui Profil alumni. Tidak ada PIC/collector pada master ini. Jika dua alumni mengisi atasan yang sama, datanya tetap tampil sebagai dua relasi alumni yang terpisah.
                </div>

                <!-- Tabel relasi alumni-atasan dengan action yang benar-benar tersedia. -->
                <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-2xs">
                    <table class="w-full min-w-[1180px] border-collapse bg-white text-left text-xs">
                        <thead>
                            <tr class="whitespace-nowrap border-b border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-800">
                                <th class="w-12 border-r border-slate-200 px-3 py-3 text-center">No</th>
                                <th class="min-w-[220px] border-r border-slate-200 px-3.5 py-3">Alumni Pengisi</th>
                                <th class="min-w-[220px] border-r border-slate-200 px-3.5 py-3">Atasan</th>
                                <th class="min-w-[210px] border-r border-slate-200 px-3.5 py-3">Perusahaan / Organisasi</th>
                                <th class="min-w-[170px] border-r border-slate-200 px-3.5 py-3">Status Evaluasi</th>
                                <th class="min-w-[210px] px-4 py-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="!atasans.data || atasans.data.length === 0">
                                <td colspan="6" class="p-8 text-center italic text-slate-400">Belum ada data atasan dari profil alumni yang sesuai.</td>
                            </tr>
                            <tr v-for="(record, index) in atasans.data" :key="record.id" class="transition-colors hover:bg-slate-50/80">
                                <td class="border-r border-slate-100 px-3 py-3 text-center font-medium text-slate-600">{{ ((atasans.current_page - 1) * atasans.per_page) + index + 1 }}</td>
                                <td class="border-r border-slate-100 px-3.5 py-3">
                                    <div class="font-bold leading-snug text-slate-900">{{ record.alumni?.nama || '-' }}</div>
                                    <div class="mt-0.5 font-mono text-[11px] text-slate-500">{{ record.alumni?.nim || '-' }}</div>
                                    <div class="mt-0.5 text-[11px] text-slate-500">{{ record.alumni?.prodi || '-' }}<span v-if="record.alumni?.tahun_lulus"> · Lulus {{ record.alumni.tahun_lulus }}</span></div>
                                    <div v-if="record.alumni?.domisili" class="mt-1 text-[10px] text-slate-600">
                                        <span class="font-semibold text-slate-700">Domisili:</span> {{ record.alumni.domisili }}
                                    </div>
                                    <div class="mt-1 text-[10px] font-medium text-emerald-700">Pengisi melalui Profil alumni</div>
                                </td>
                                <td class="border-r border-slate-100 px-3.5 py-3">
                                    <div class="font-semibold leading-snug text-slate-900">{{ record.atasan?.nama || '-' }}</div>
                                    <div class="mt-1 text-[10px] text-slate-400">Kontak tersedia di detail</div>
                                </td>
                                <td class="border-r border-slate-100 px-3.5 py-3 font-medium text-slate-800">
                                    <div class="font-bold leading-snug text-slate-900">{{ record.perusahaan?.nama || 'Belum terhubung' }}</div>
                                    <div v-if="record.evaluasi?.standar_gaji_pertama || record.evaluasi?.jumlah_pegawai" class="mt-1 flex flex-wrap gap-1">
                                        <span v-if="record.evaluasi?.standar_gaji_pertama" class="rounded border border-emerald-200 bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold text-[#0D542B]" title="Standar gaji pertama yang dilaporkan atasan">
                                            Gaji: {{ record.evaluasi.standar_gaji_pertama }}
                                        </span>
                                        <span v-if="record.evaluasi?.jumlah_pegawai" class="rounded border border-slate-200 bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-600" title="Jumlah pegawai perusahaan">
                                            {{ record.evaluasi.jumlah_pegawai }} pegawai
                                        </span>
                                    </div>
                                </td>
                                <td class="border-r border-slate-100 px-3.5 py-3">
                                    <span class="inline-flex rounded-full border px-2.5 py-0.5 text-[10px] font-semibold" :class="evaluationClass(record.evaluasi?.status)">{{ record.evaluasi?.status || 'Belum ada evaluasi' }}</span>
                                    <div v-if="record.evaluasi?.kesiapan_kerja" class="mt-1 text-[10px] font-medium text-slate-600">
                                        Kesiapan: <strong class="text-slate-800">{{ record.evaluasi.kesiapan_kerja }}</strong>
                                    </div>
                                    <div v-if="record.evaluasi?.submitted_at" class="mt-0.5 text-[10px] text-slate-400">{{ formatDate(record.evaluasi.submitted_at) }}</div>
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-600 transition-colors hover:bg-slate-50 hover:text-slate-900" title="Lihat detail data atasan" aria-label="Lihat detail data atasan" @click="openDetailModal(record)">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                        </button>
                                        <button v-if="['superadmin', 'admin_biro3', 'admin_fakultas', 'admin_prodi'].includes(role) && record.atasan" type="button" class="inline-flex h-9 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-[11px] font-bold text-slate-700 transition-colors hover:bg-slate-50 hover:text-[#0D542B]" title="Edit data atasan" @click="openEditAtasanModal(record)">
                                            Edit
                                        </button>
                                        <Link :href="alumniDetailPath(record.alumni.id)" class="inline-flex h-9 items-center justify-center rounded-lg bg-[#0D542B] px-3 text-[11px] font-bold text-white transition-colors hover:bg-[#08381c]">Lihat Profil Alumni</Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination server-side memakai label dan jumlah nomor halaman yang konsisten. -->
                <PaginationControls :pagination="atasans" summary-label="relasi atasan" />
            </main>

            <footer class="mt-12 border-t border-slate-200 py-6 text-center text-xs text-slate-400">&copy; {{ new Date().getFullYear() }} Universitas Kristen Duta Wacana. Hak Cipta Dilindungi.</footer>
        </div>

        <!-- Modal tambah atau edit data atasan. -->
        <div v-if="showAtasanFormModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="closeAtasanFormModal">
            <div class="w-full max-w-xl rounded-2xl border border-slate-200 bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-[#0D542B]">Master Data Atasan</p>
                        <h2 class="mt-1 text-lg font-bold text-slate-900">{{ atasanFormMode === 'create' ? 'Tambah Data Atasan' : 'Edit Data Atasan' }}</h2>
                    </div>
                    <button type="button" class="rounded-lg border border-slate-200 px-3 py-1 text-sm text-slate-500 hover:bg-slate-50" aria-label="Tutup form data atasan" @click="closeAtasanFormModal">Tutup</button>
                </div>
                <form class="space-y-4 px-5 py-5" @submit.prevent="submitAtasanForm">
                    <!-- Alumni (Searchable Selector) -->
                    <div v-if="atasanFormMode === 'create'" class="space-y-1.5">
                        <label class="block text-xs font-bold text-slate-700">
                            Alumni <span class="text-rose-600">*</span>
                        </label>
                        <!-- Alumni Terpilih -->
                        <div v-if="selectedAlumni" class="flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50/70 p-3">
                            <div class="min-w-0 flex-1 pr-2">
                                <p class="text-xs font-bold text-slate-900 truncate">{{ selectedAlumni.nama }}</p>
                                <p class="font-mono text-[11px] text-slate-500">{{ selectedAlumni.nim }} · {{ selectedAlumni.prodi || 'Program Studi' }}<span v-if="selectedAlumni.tahun_lulus"> · Lulus {{ selectedAlumni.tahun_lulus }}</span></p>
                                <span v-if="selectedAlumni.has_atasan" class="mt-1 inline-block rounded border border-amber-200 bg-amber-50 px-1.5 py-0.5 text-[9px] font-bold text-amber-800">
                                    Sudah ada data atasan (akan diperbarui)
                                </span>
                                <span v-else class="mt-1 inline-block rounded border border-slate-200 bg-slate-100 px-1.5 py-0.5 text-[9px] font-medium text-slate-600">
                                    Belum ada data atasan
                                </span>
                            </div>
                            <button type="button" class="shrink-0 text-xs font-bold text-rose-600 hover:underline cursor-pointer" @click="clearSelectedAlumni">
                                Ganti Alumni
                            </button>
                        </div>

                        <!-- Dropdown Pencarian Alumni -->
                        <div v-else class="relative">
                            <input
                                v-model="alumniSearch"
                                type="text"
                                placeholder="Ketik nama, NIM, atau program studi alumni..."
                                class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-xs text-slate-900 placeholder:text-slate-400 focus:border-[#0D542B] focus:outline-none focus:ring-1 focus:ring-[#0D542B]"
                                @focus="showAlumniDropdown = true"
                            />
                            <!-- Daftar Pilihan Alumni -->
                            <div
                                v-if="showAlumniDropdown"
                                class="absolute left-0 right-0 z-30 mt-1 max-h-56 overflow-y-auto rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg"
                            >
                                <div v-if="filteredAlumniOptions.length === 0" class="p-3 text-center text-xs italic text-slate-400">
                                    Tidak ada data alumni yang cocok dengan kata kunci "{{ alumniSearch }}".
                                </div>
                                <div
                                    v-for="alumni in filteredAlumniOptions"
                                    :key="alumni.id"
                                    class="cursor-pointer rounded-lg px-3 py-2 text-xs transition-colors hover:bg-slate-50"
                                    @click="selectAlumni(alumni)"
                                >
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-slate-900">{{ alumni.nama }}</span>
                                        <span class="font-mono text-[11px] text-slate-500">{{ alumni.nim }}</span>
                                    </div>
                                    <div class="flex items-center justify-between text-[11px] text-slate-500 mt-0.5">
                                        <span class="truncate">{{ alumni.prodi || '-' }}<span v-if="alumni.tahun_lulus"> · Lulus {{ alumni.tahun_lulus }}</span></span>
                                        <span v-if="alumni.has_atasan" class="shrink-0 ml-2 text-[10px] text-amber-700 font-medium">Ada atasan</span>
                                        <span v-else class="shrink-0 ml-2 text-[10px] text-slate-400">Belum ada atasan</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Perusahaan / Organisasi -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-bold text-slate-700">Perusahaan / Organisasi</label>
                            <button
                                type="button"
                                class="text-[11px] font-bold text-[#0D542B] hover:underline cursor-pointer"
                                @click="openCreateCompanyModal"
                            >
                                + Tambah Perusahaan Baru
                            </button>
                        </div>
                        <select
                            v-model="atasanForm.perusahaan_id"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-xs text-slate-800 focus:border-[#0D542B] focus:outline-none focus:ring-1 focus:ring-[#0D542B] cursor-pointer"
                        >
                            <option value="">Gunakan perusahaan dari profil alumni</option>
                            <option v-for="company in (masterOptions.perusahaan || [])" :key="company.id" :value="company.id">
                                {{ company.nama_perusahaan }} {{ company.status_verifikasi === 'Menunggu Verifikasi' ? '(Menunggu Verifikasi)' : '' }}
                            </option>
                        </select>
                    </div>

                    <!-- Nama Atasan -->
                    <div>
                        <label class="mb-1 block text-xs font-bold text-slate-700">Nama Atasan <span class="text-rose-600">*</span></label>
                        <input v-model="atasanForm.nama" required type="text" maxlength="100" placeholder="Nama lengkap atasan" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-xs text-slate-900 focus:border-[#0D542B] focus:outline-none focus:ring-1 focus:ring-[#0D542B]" />
                    </div>

                    <!-- Kontak Atasan -->
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-bold text-slate-700">Email Atasan</label>
                            <input v-model="atasanForm.email" type="email" maxlength="100" placeholder="atasan@perusahaan.id" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-xs text-slate-900 focus:border-[#0D542B] focus:outline-none focus:ring-1 focus:ring-[#0D542B]" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-bold text-slate-700">Telepon / WhatsApp</label>
                            <input v-model="atasanForm.telepon" type="text" maxlength="20" placeholder="08xxxxxxxxxx" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-xs text-slate-900 focus:border-[#0D542B] focus:outline-none focus:ring-1 focus:ring-[#0D542B]" />
                        </div>
                    </div>

                    <!-- Footer Tombol -->
                    <div class="flex justify-end gap-2 border-t border-slate-200 pt-4">
                        <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 cursor-pointer" @click="closeAtasanFormModal">Batal</button>
                        <button type="submit" :disabled="isSubmittingAtasan || (!atasanForm.biodata_id && atasanFormMode === 'create')" class="rounded-lg bg-[#0D542B] px-4 py-2 text-xs font-bold text-white hover:bg-[#08381c] disabled:cursor-not-allowed disabled:opacity-60 cursor-pointer">{{ isSubmittingAtasan ? 'Menyimpan...' : 'Simpan Data Atasan' }}</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Tambah Perusahaan Baru (Status: Menunggu Verifikasi) -->
        <div v-if="showCreateCompanyModal" class="fixed inset-0 z-60 flex items-center justify-center bg-black/60 p-4" @click.self="closeCreateCompanyModal">
            <div class="flex max-h-[90vh] w-full max-w-xl flex-col rounded-2xl border border-slate-200 bg-white shadow-2xl">
                <!-- Header Modal Sesuai Master Perusahaan -->
                <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/70 px-6 py-4">
                    <div>
                        <h3 class="text-base font-bold text-slate-800">Tambah Perusahaan Baru</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Daftarkan institusi/kantor pusat baru (Status: Menunggu Verifikasi)</p>
                    </div>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:text-slate-600 transition-colors cursor-pointer" @click="closeCreateCompanyModal">
                        <span class="sr-only">Tutup</span>
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <!-- Form Badan Modal Scrollable -->
                <form class="flex-1 overflow-y-auto p-6 space-y-4 text-xs" @submit.prevent="submitNewCompany">
                    <div v-if="companyError" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700">
                        {{ companyError }}
                    </div>

                    <!-- 1. Jenis Lokasi Perusahaan -->
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1.5">Jenis Lokasi Perusahaan <span class="text-rose-500">*</span></label>
                        <div class="flex items-center gap-6 mt-1">
                            <label class="inline-flex items-center gap-2 cursor-pointer font-medium text-slate-700">
                                <input type="radio" value="Dalam Negeri" v-model="newCompanyForm.jenis_lokasi" class="text-[#0D542B] focus:ring-[#0D542B] border-slate-300" />
                                <span>Dalam Negeri (Indonesia)</span>
                            </label>
                            <label class="inline-flex items-center gap-2 cursor-pointer font-medium text-slate-700">
                                <input type="radio" value="Luar Negeri" v-model="newCompanyForm.jenis_lokasi" class="text-[#0D542B] focus:ring-[#0D542B] border-slate-300" />
                                <span>Luar Negeri (Abroad)</span>
                            </label>
                        </div>
                    </div>

                    <!-- 2. Nama Perusahaan / Instansi (Head Office) -->
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Nama Perusahaan / Instansi (Head Office) <span class="text-rose-500">*</span></label>
                        <input type="text" v-model="newCompanyForm.nama_perusahaan" required placeholder="Contoh: PT Teknologi Nusantara / Google Singapore" class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs focus:border-[#0D542B] focus:outline-none focus:ring-1 focus:ring-[#0D542B]" />
                    </div>

                    <!-- 3. Bentuk & Jenis Perusahaan -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Bentuk Perusahaan / Institusi</label>
                            <select v-model="newCompanyForm.bentuk_perusahaan" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs focus:border-[#0D542B] focus:outline-none focus:ring-1 focus:ring-[#0D542B] cursor-pointer">
                                <option v-for="opt in bentukPerusahaanOptions" :key="opt" :value="opt">{{ opt }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Jenis Instansi (F11)</label>
                            <select v-model="newCompanyForm.jenis_perusahaan" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs focus:border-[#0D542B] focus:outline-none focus:ring-1 focus:ring-[#0D542B] cursor-pointer">
                                <option v-for="opt in jenisPerusahaanOptions" :key="opt" :value="opt">{{ opt }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- 4. Wilayah: Luar Negeri vs Dalam Negeri -->
                    <div v-if="newCompanyForm.jenis_lokasi === 'Luar Negeri'">
                        <label class="block font-semibold text-slate-700 mb-1">Negara Tempat Bekerja <span class="text-rose-500">*</span></label>
                        <select v-model="newCompanyForm.kode_negara" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs focus:border-[#0D542B] focus:outline-none focus:ring-1 focus:ring-[#0D542B] cursor-pointer">
                            <option value="">-- Pilih Negara --</option>
                            <option v-for="n in (masterOptions.negara || [])" :key="n.kode_negara" :value="n.kode_negara">{{ n.nama_negara }} ({{ n.benua || 'Dunia' }})</option>
                        </select>
                    </div>
                    <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Provinsi</label>
                            <select v-model="newCompanyForm.kode_provinsi" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs focus:border-[#0D542B] focus:outline-none focus:ring-1 focus:ring-[#0D542B] cursor-pointer">
                                <option value="">-- Pilih Provinsi --</option>
                                <option v-for="p in (masterOptions.propinsi || [])" :key="p.kode_provinsi" :value="p.kode_provinsi">{{ p.nama_provinsi }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Kabupaten / Kota</label>
                            <select v-model="newCompanyForm.kode_kabupaten" :disabled="!newCompanyForm.kode_provinsi" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs focus:border-[#0D542B] focus:outline-none focus:ring-1 focus:ring-[#0D542B] cursor-pointer disabled:bg-slate-100">
                                <option value="">-- Pilih Kota/Kabupaten --</option>
                                <option v-for="k in availableKabupatens" :key="k.kode_kabupaten" :value="k.kode_kabupaten">{{ k.nama_kabupaten }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- 5. Alamat Kantor & Kode Pos -->
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Alamat Kantor / Head Office</label>
                        <textarea v-model="newCompanyForm.alamat" rows="2" placeholder="Nama gedung, jalan, nomor kantor..." class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs focus:border-[#0D542B] focus:outline-none focus:ring-1 focus:ring-[#0D542B]"></textarea>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Skala Organisasi</label>
                            <select v-model="newCompanyForm.skala" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs focus:border-[#0D542B] focus:outline-none focus:ring-1 focus:ring-[#0D542B] cursor-pointer">
                                <option value="Lokal">Lokal</option>
                                <option value="Nasional">Nasional</option>
                                <option value="Internasional">Internasional</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Kode Pos</label>
                            <input type="text" v-model="newCompanyForm.kode_pos" placeholder="55224" maxlength="10" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs focus:border-[#0D542B] focus:outline-none focus:ring-1 focus:ring-[#0D542B]" />
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Telepon / Fax</label>
                            <input type="text" v-model="newCompanyForm.no_telp_fax" placeholder="021-xxxxxxx" maxlength="50" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs focus:border-[#0D542B] focus:outline-none focus:ring-1 focus:ring-[#0D542B]" />
                        </div>
                    </div>

                    <!-- 6. Footer Tombol -->
                    <div class="flex items-center justify-end gap-2 border-t border-slate-200 pt-4">
                        <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 cursor-pointer" @click="closeCreateCompanyModal">Batal</button>
                        <button type="submit" :disabled="isSubmittingCompany" class="rounded-lg bg-[#0D542B] px-4 py-2 text-xs font-bold text-white hover:bg-[#08381c] disabled:opacity-60 cursor-pointer">{{ isSubmittingCompany ? 'Menyimpan...' : 'Simpan Perusahaan' }}</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal detail relasi dan seluruh instrumen evaluasi atasan -->
        <div v-if="showDetailModal && activeRecord" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="closeDetailModal">
            <div class="flex max-h-[90vh] w-full max-w-4xl flex-col rounded-2xl border border-slate-200 bg-white shadow-2xl">
                <!-- Header Modal -->
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="rounded bg-emerald-100 px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wider text-[#0D542B]">
                                Evaluasi Pengguna Lulusan
                            </span>
                            <span class="rounded-full border px-2.5 py-0.5 text-[10px] font-semibold" :class="evaluationClass(activeRecord.evaluasi?.status)">
                                {{ activeRecord.evaluasi?.status || 'Belum ada evaluasi' }}
                            </span>
                        </div>
                        <h2 class="mt-1 text-lg font-bold text-slate-900">
                            Data Atasan & Hasil Evaluasi Kinerja Alumni
                        </h2>
                    </div>
                    <button type="button" class="rounded-lg border border-slate-200 px-3 py-1 text-sm font-semibold text-slate-500 hover:bg-slate-50 transition-colors" aria-label="Tutup detail" @click="closeDetailModal">
                        Tutup
                    </button>
                </div>

                <!-- Body Modal (Scrollable) -->
                <div class="flex-1 overflow-y-auto px-6 py-5 space-y-6 text-sm">
                    <!-- Section 1: Profil Alumni & Atasan Penilai -->
                    <div class="grid gap-4 sm:grid-cols-2">
                        <!-- Kartu Alumni -->
                        <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-emerald-800">Alumni yang Dinilai</p>
                            <p class="mt-1 text-base font-bold text-slate-900">{{ activeRecord.alumni.nama }}</p>
                            <p class="mt-0.5 font-mono text-xs text-slate-600">NIM: {{ activeRecord.alumni.nim }}</p>
                            <p class="mt-1 text-xs text-slate-600">
                                Program Studi: <strong class="text-slate-800">{{ activeRecord.alumni.prodi || '-' }}</strong>
                                <span v-if="activeRecord.alumni.tahun_lulus"> · Lulus {{ activeRecord.alumni.tahun_lulus }}</span>
                            </p>
                        </div>

                        <!-- Kartu Atasan & Perusahaan -->
                        <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-4">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Atasan Langsung & Instansi</p>
                            <p class="mt-1 text-base font-bold text-slate-900">{{ activeRecord.atasan?.nama || 'Belum ditentukan' }}</p>
                            <p class="mt-0.5 text-xs text-slate-600">
                                Perusahaan: <strong class="text-slate-800">{{ activeRecord.perusahaan?.nama || '-' }}</strong>
                            </p>
                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500 font-mono">
                                <div>Email: <span class="text-slate-800 font-sans">{{ activeRecord.atasan?.email || '-' }}</span></div>
                                <div>Telp/WA: <span class="text-slate-800 font-sans">{{ activeRecord.atasan?.telepon || '-' }}</span></div>
                            </div>
                        </div>
                    </div>

                    <!-- Jika Evaluasi Selesai: Tampilkan Seluruh Instrumen Pertanyaan Evaluasi -->
                    <template v-if="activeRecord.evaluasi?.is_submitted">
                        <!-- Section 2: Karakteristik Perusahaan & Ketenagakerjaan -->
                        <div class="rounded-xl border border-slate-200 bg-white p-5">
                            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                                <div>
                                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">
                                        Karakteristik Perusahaan & Ketenagakerjaan
                                    </h3>
                                    <p class="mt-0.5 text-xs text-slate-400">Informasi organisasi dan skala ketenagakerjaan alumni yang dilaporkan atasan</p>
                                </div>
                                <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600">
                                    5 Butir Profil
                                </span>
                            </div>

                            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                <div class="rounded-lg border border-slate-100 bg-slate-50/60 p-3">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Bentuk Perusahaan</p>
                                    <p class="mt-1 font-bold text-slate-900">{{ activeRecord.evaluasi.bentuk_perusahaan || activeRecord.perusahaan?.bentuk_perusahaan || '-' }}</p>
                                </div>
                                <div class="rounded-lg border border-slate-100 bg-slate-50/60 p-3">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Skala Organisasi</p>
                                    <p class="mt-1 font-bold text-slate-900">{{ activeRecord.evaluasi.skala_perusahaan || activeRecord.perusahaan?.skala || '-' }}</p>
                                </div>
                                <div class="rounded-lg border border-slate-100 bg-slate-50/60 p-3">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Jumlah Pegawai / Karyawan</p>
                                    <p class="mt-1 font-bold text-slate-900">{{ activeRecord.evaluasi.jumlah_pegawai || '-' }}</p>
                                </div>
                                <div class="rounded-lg border border-slate-100 bg-slate-50/60 p-3">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Jumlah Alumni UKDW Bekerja</p>
                                    <p class="mt-1 font-bold text-slate-900">{{ activeRecord.evaluasi.jumlah_alumni_ukdw || '-' }}</p>
                                </div>
                                <div class="rounded-lg border border-emerald-200 bg-emerald-50/80 p-3 sm:col-span-2">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-800">Standar Gaji Pertama Alumni UKDW (per bulan)</p>
                                    <p class="mt-1 text-base font-extrabold text-[#0D542B]">{{ activeRecord.evaluasi.standar_gaji_pertama || '-' }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Section 3: Tingkat Kesiapan Kerja Alumni -->
                        <div class="rounded-xl border border-slate-200 bg-white p-5">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">
                                Tingkat Kesiapan Kerja Alumni
                            </h3>
                            <div class="mt-3 flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50/60 p-4">
                                <span class="text-xs font-medium text-slate-700">Kesiapan alumni memasuki dunia kerja menurut atasan:</span>
                                <span class="rounded-full border px-3 py-1 text-xs font-bold" :class="getLikertBadgeStyle(activeRecord.evaluasi.kesiapan_kerja)">
                                    {{ activeRecord.evaluasi.kesiapan_kerja || 'Belum diisi' }}
                                </span>
                            </div>
                        </div>

                        <!-- Section 4: Penilaian 13 Butir Aspek Kinerja Lulusan -->
                        <div class="rounded-xl border border-slate-200 bg-white p-5">
                            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                                <div>
                                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">
                                        Penilaian Aspek Kinerja Lulusan (Skala 1–5)
                                    </h3>
                                    <p class="mt-0.5 text-xs text-slate-400">Rincian jawaban evaluasi atasan terhadap 13 butir kompetensi lulusan</p>
                                </div>
                                <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600">
                                    13 Butir Penilaian
                                </span>
                            </div>

                            <div class="mt-4 overflow-hidden rounded-lg border border-slate-200">
                                <table class="w-full text-left text-xs">
                                    <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-bold uppercase tracking-wide text-slate-600">
                                        <tr>
                                            <th class="px-3.5 py-2.5 text-center w-12">No</th>
                                            <th class="px-3.5 py-2.5">Aspek Kompetensi</th>
                                            <th class="px-3.5 py-2.5 text-center">Jawaban Atasan</th>
                                            <th class="px-3.5 py-2.5 text-center w-24">Skor</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <tr v-for="(item, idx) in activeRecord.evaluasi.kompetensi || []" :key="item.kode" class="hover:bg-slate-50/60 transition-colors">
                                            <td class="px-3.5 py-2.5 text-center font-mono text-slate-400">{{ idx + 1 }}</td>
                                            <td class="px-3.5 py-2.5 font-medium text-slate-900">{{ item.pertanyaan }}</td>
                                            <td class="px-3.5 py-2.5 text-center">
                                                <span class="rounded-full border px-2.5 py-0.5 text-[11px] font-bold" :class="getLikertBadgeStyle(item.jawaban)">
                                                    {{ item.jawaban }}
                                                </span>
                                            </td>
                                            <td class="px-3.5 py-2.5 text-center font-bold text-[#0D542B]">
                                                {{ item.skor ? `${item.skor} / 5` : '-' }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Section 5: Catatan Tambahan & Saran Pengguna Lulusan -->
                        <div class="rounded-xl border border-slate-200 bg-white p-5">
                            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                                <div>
                                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">
                                        Catatan Tambahan & Saran Pengguna Lulusan
                                    </h3>
                                    <p class="mt-0.5 text-xs text-slate-400">Masukan kualitatif atasan untuk peningkatan kompetensi alumni UKDW</p>
                                </div>
                                <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600">
                                    Kritik & Saran
                                </span>
                            </div>

                            <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50/60 p-4">
                                <p v-if="activeRecord.evaluasi?.saran" class="text-xs text-slate-800 leading-relaxed font-medium">
                                    "{{ activeRecord.evaluasi.saran }}"
                                </p>
                                <p v-else class="text-xs text-slate-400 italic">
                                    Tidak ada catatan tambahan atau saran khusus yang dituliskan oleh atasan.
                                </p>
                            </div>
                        </div>
                    </template>

                    <!-- Jika Evaluasi Belum Selesai -->
                    <div v-else class="rounded-xl border border-dashed border-slate-200 bg-slate-50/50 p-6 text-center">
                        <p class="text-sm font-bold text-slate-700">Instrumen Evaluasi Belum Diisi</p>
                        <p class="mt-1 text-xs text-slate-500">
                            Atasan langsung belum mengirimkan kuesioner evaluasi kinerja dan karakteristik perusahaan untuk alumni ini.
                        </p>
                    </div>
                </div>

                <!-- Footer Modal -->
                <div class="flex items-center justify-between border-t border-slate-200 px-6 py-4 bg-slate-50/50">
                    <div class="text-xs text-slate-500">
                        <span v-if="activeRecord.evaluasi?.submitted_at">
                            Waktu pengisian: <strong>{{ formatDate(activeRecord.evaluasi.submitted_at) }}</strong>
                        </span>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors" @click="closeDetailModal">
                            Tutup
                        </button>
                        <Link :href="alumniDetailPath(activeRecord.alumni.id)" class="rounded-lg bg-[#0D542B] px-4 py-2 text-xs font-bold text-white hover:bg-[#08381c] transition-colors">
                            Buka Profil Alumni
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
