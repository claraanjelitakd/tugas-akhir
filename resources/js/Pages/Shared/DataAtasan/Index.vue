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
    filters: { type: Object, default: () => ({ search: '', prodi_id: '', per_page: 10 }) },
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
        default: () => ({ alumni: [], perusahaan: [] }),
    },
});

// State filter lokal mengikuti pola filter pada master perusahaan.
const search = ref(props.filters.search || '');
const selectedProdiId = ref(props.filters.prodi_id || '');
const perPage = ref(Number(props.filters.per_page || 10));

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

// Menentukan judul halaman sesuai scope role admin aktif.
const pageTitle = computed(() => `Data Atasan - ${props.roleTitle}`);

// Menentukan apakah ada filter yang sedang aktif.
const hasActiveFilters = computed(() => Boolean(search.value || selectedProdiId.value || perPage.value !== 10));

// Sinkronkan filter lokal saat server mengembalikan props baru setelah navigasi.
watch(() => props.filters, (newFilters) => {
    search.value = newFilters?.search || '';
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

// Mengirim filter direktori ke endpoint role aktif.
const applyFilters = () => {
    router.get(`/${props.prefix}/atasan`, {
        search: search.value || undefined,
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
    selectedProdiId.value = '';
    perPage.value = 10;
    router.get(`/${props.prefix}/atasan`, {}, { preserveState: true, preserveScroll: true });
};
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
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-if="role === 'superadmin'"
                            type="button"
                            class="inline-flex w-fit items-center rounded-lg bg-[#FDC700] px-4 py-2 text-xs font-bold text-[#0D542B] transition-colors hover:bg-yellow-300"
                            @click="openCreateAtasanModal"
                        >
                            Tambah Data Atasan
                        </button>
                        <Link
                            :href="`/${prefix}/analitik-atasan`"
                            class="inline-flex w-fit items-center rounded-lg bg-white px-4 py-2 text-xs font-bold text-[#0D542B] transition-colors hover:bg-emerald-50"
                        >
                            Lihat Grafik Evaluasi Atasan
                        </Link>
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
                    <div class="grid grid-cols-1 gap-3.5 text-xs sm:grid-cols-2 lg:grid-cols-4">
                        <div class="lg:col-span-2">
                            <label class="mb-1 block text-[11px] font-bold uppercase text-slate-600">Cari Alumni / Atasan / Perusahaan</label>
                            <input v-model="search" type="search" placeholder="Ketik nama, NIM, email, atau perusahaan..." class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#0D542B]" @input="handleSearch" />
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
                                    <div class="mt-1 text-[10px] font-medium text-emerald-700">Pengisi melalui Profil alumni</div>
                                </td>
                                <td class="border-r border-slate-100 px-3.5 py-3">
                                    <div class="font-semibold leading-snug text-slate-900">{{ record.atasan?.nama || '-' }}</div>
                                    <div class="mt-1 text-[10px] text-slate-400">Kontak tersedia di detail</div>
                                </td>
                                <td class="border-r border-slate-100 px-3.5 py-3 font-medium text-slate-800">{{ record.perusahaan?.nama || 'Belum terhubung' }}</td>
                                <td class="border-r border-slate-100 px-3.5 py-3">
                                    <span class="inline-flex rounded-full border px-2.5 py-0.5 text-[10px] font-semibold" :class="evaluationClass(record.evaluasi?.status)">{{ record.evaluasi?.status || 'Belum ada evaluasi' }}</span>
                                    <div v-if="record.evaluasi?.submitted_at" class="mt-1 text-[10px] text-slate-400">{{ formatDate(record.evaluasi.submitted_at) }}</div>
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-600 transition-colors hover:bg-slate-50 hover:text-slate-900" title="Lihat detail data atasan" aria-label="Lihat detail data atasan" @click="openDetailModal(record)">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                        </button>
                                        <button v-if="role === 'superadmin' && record.atasan" type="button" class="inline-flex h-9 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-[11px] font-bold text-slate-700 transition-colors hover:bg-slate-50 hover:text-[#0D542B]" title="Edit data atasan" @click="openEditAtasanModal(record)">
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

        <!-- Modal tambah atau edit data atasan untuk Superadmin. -->
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
                    <div v-if="atasanFormMode === 'create'">
                        <label class="mb-1 block text-xs font-bold text-slate-700">Alumni <span class="text-rose-600">*</span></label>
                        <select v-model="atasanForm.biodata_id" required class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0D542B] focus:outline-none focus:ring-1 focus:ring-[#0D542B]">
                            <option value="">Pilih alumni</option>
                            <option v-for="alumni in masterOptions.alumni" :key="alumni.id" :value="alumni.id">
                                {{ alumni.nim }} · {{ alumni.nama }}{{ alumni.prodi ? ` · ${alumni.prodi}` : '' }}{{ alumni.has_atasan ? ' · memperbarui data atasan' : '' }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-bold text-slate-700">Perusahaan / Organisasi</label>
                        <select v-model="atasanForm.perusahaan_id" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0D542B] focus:outline-none focus:ring-1 focus:ring-[#0D542B]">
                            <option value="">Gunakan perusahaan dari profil alumni</option>
                            <option v-for="company in masterOptions.perusahaan" :key="company.id" :value="company.id">{{ company.nama_perusahaan }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-bold text-slate-700">Nama Atasan <span class="text-rose-600">*</span></label>
                        <input v-model="atasanForm.nama" required type="text" maxlength="100" placeholder="Nama lengkap atasan" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-[#0D542B] focus:outline-none focus:ring-1 focus:ring-[#0D542B]" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-bold text-slate-700">Email Atasan</label>
                            <input v-model="atasanForm.email" type="email" maxlength="100" placeholder="atasan@perusahaan.id" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-[#0D542B] focus:outline-none focus:ring-1 focus:ring-[#0D542B]" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-bold text-slate-700">Telepon / WhatsApp</label>
                            <input v-model="atasanForm.telepon" type="text" maxlength="20" placeholder="08xxxxxxxxxx" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-[#0D542B] focus:outline-none focus:ring-1 focus:ring-[#0D542B]" />
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-200 pt-4">
                        <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50" @click="closeAtasanFormModal">Batal</button>
                        <button type="submit" :disabled="isSubmittingAtasan" class="rounded-lg bg-[#0D542B] px-4 py-2 text-xs font-bold text-white hover:bg-[#08381c] disabled:cursor-not-allowed disabled:opacity-60">{{ isSubmittingAtasan ? 'Menyimpan...' : 'Simpan Data Atasan' }}</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal detail relasi untuk pemeriksaan cepat sebelum membuka profil alumni. -->
        <div v-if="showDetailModal && activeRecord" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="closeDetailModal">
            <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-[#0D542B]">Detail Relasi</p>
                        <h2 class="mt-1 text-lg font-bold text-slate-900">Data Atasan Alumni</h2>
                    </div>
                    <button type="button" class="rounded-lg border border-slate-200 px-3 py-1 text-sm text-slate-500 hover:bg-slate-50" aria-label="Tutup detail" @click="closeDetailModal">Tutup</button>
                </div>
                <!-- Card detail dipisah agar identitas alumni pengisi dan kontak atasan tidak bertumpuk. -->
                <div class="grid gap-3 px-5 py-5 text-sm sm:grid-cols-2">
                    <div class="rounded-xl border border-emerald-100 bg-emerald-50/60 p-4 sm:col-span-2">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-emerald-800">Alumni Pengisi</p>
                        <p class="mt-1 font-semibold text-slate-900">{{ activeRecord.alumni.nama }}</p>
                        <p class="mt-0.5 font-mono text-xs text-slate-500">{{ activeRecord.alumni.nim }}</p>
                        <p class="mt-1 text-xs text-slate-600">{{ activeRecord.alumni.prodi || '-' }}<span v-if="activeRecord.alumni.tahun_lulus"> · Lulus {{ activeRecord.alumni.tahun_lulus }}</span></p>
                        <p class="mt-2 text-xs font-medium text-emerald-800">Mengisi melalui Profil alumni</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Atasan</p>
                        <p class="mt-1 font-semibold text-slate-900">{{ activeRecord.atasan?.nama || '-' }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Perusahaan / Organisasi</p>
                        <p class="mt-1 font-semibold text-slate-900">{{ activeRecord.perusahaan?.nama || '-' }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Email Atasan</p>
                        <p class="mt-1 break-all text-slate-700">{{ activeRecord.atasan?.email || 'Belum diisi' }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Telepon Atasan</p>
                        <p class="mt-1 text-slate-700">{{ activeRecord.atasan?.telepon || 'Belum diisi' }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-4 sm:col-span-2">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Status Evaluasi</p>
                        <p class="mt-1 font-semibold text-slate-700">{{ activeRecord.evaluasi?.status || 'Belum ada evaluasi' }}</p>
                    </div>
                </div>
                <div class="flex justify-end border-t border-slate-200 px-5 py-4">
                    <Link :href="alumniDetailPath(activeRecord.alumni.id)" class="rounded-lg bg-[#0D542B] px-4 py-2 text-xs font-bold text-white hover:bg-[#08381c]">Buka Profil Alumni</Link>
                </div>
            </div>
        </div>
    </div>
</template>
