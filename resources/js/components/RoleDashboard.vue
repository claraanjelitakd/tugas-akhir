<script setup>
// Kontrak halaman dashboard bersama untuk Admin Prodi, Fakultas, dan Biro 3.
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AdminSidebar from '@/Components/AdminSidebar.vue';

// Props metrik dan data audit yang sudah dibatasi oleh controller sesuai scope role.
const props = defineProps({
    role: { type: String, required: true },
    user: { type: Object, default: () => ({}) },
    prodi: { type: Object, default: () => ({}) },
    fakultas: { type: Object, default: () => ({}) },
    availableYears: { type: Array, default: () => [] },
    selectedYear: { type: [String, Number], default: () => new Date().getFullYear() },
    dashboardStats: {
        type: Object,
        default: () => ({
            total_alumni: 0,
            total_responden: 0,
            response_rate: 0,
            total_prodi: 0,
            total_linkedin: 0,
            total_pertanyaan: 0,
            total_sections: 0,
            total_pending_perusahaan: 0,
            response_rate_status: { code: 'red', label: 'Di bawah 50%' },
        }),
    },
    completionMetrics: {
        type: Object,
        default: () => ({
            profile: { completed_count: 0, population: 0, percentage: 0, label: 'Profil Alumni' },
            university_questionnaire: { completed_count: 0, population: 0, percentage: 0, label: 'Kuesioner Universitas' },
            profile_and_university: { completed_count: 0, population: 0, percentage: 0, label: 'Profil dan Kuesioner Universitas' },
        }),
    },
    prodiSummaries: { type: Array, default: () => [] },
    recentAlumni: { type: Array, default: () => [] },
    sectionsList: { type: Array, default: () => [] },
});

// State tab, filter, pencarian, dan tahun yang sedang ditampilkan.
const activeTab = ref('prodi');
const activeCompletionMetric = ref('profile_and_university');
const selectedYear = ref(String(props.selectedYear));
const searchProdiQuery = ref('');
const searchAlumniQuery = ref('');
const filterStatus = ref('all');
const sortByRate = ref(true);

// Menjaga select tahun mengikuti respons Inertia setelah filter diterapkan.
watch(() => props.selectedYear, (year) => {
    selectedYear.value = String(year);
});

// Konfigurasi label dan tautan yang berbeda antar role.
const roleConfig = computed(() => {
    const configs = {
        admin_prodi: {
            label: 'Admin Program Studi',
            title: `Dashboard ${props.prodi?.nama_prodi || 'Program Studi'}`,
            description: 'Pantau partisipasi, kelengkapan, dan tindak lanjut tracer study pada program studi.',
            contextLabel: 'Program Studi',
            contextValue: `${props.prodi?.nama_prodi || 'Program Studi'} (Kode: ${props.prodi?.kode_prodi || '-'})`,
            prefix: '/prodi',
            links: { alumni: '/prodi/alumni', company: '/prodi/perusahaan', linkedin: '/prodi/linkedin-sync', questionnaire: '/prodi/kuesioner', accounts: '/prodi/manajemen-akun', sections: '/prodi/sections' },
        },
        admin_fakultas: {
            label: 'Admin Fakultas',
            title: `Dashboard ${props.fakultas?.nama_fakultas || 'Fakultas'}`,
            description: 'Pantau partisipasi, kelengkapan, dan tindak lanjut tracer study lintas program studi dalam fakultas.',
            contextLabel: 'Fakultas',
            contextValue: props.fakultas?.nama_fakultas || 'Fakultas',
            prefix: '/fakultas',
            links: { alumni: '/fakultas/alumni', company: '/fakultas/perusahaan', linkedin: '/fakultas/linkedin-sync', questionnaire: '/fakultas/prodi-kuesioner', accounts: '/fakultas/manajemen-akun' },
        },
        admin_biro3: {
            label: 'Biro 3 Kemahasiswaan',
            title: 'Dashboard Biro 3',
            description: 'Pantau partisipasi, kelengkapan, dan tindak lanjut tracer study pada tingkat universitas.',
            contextLabel: 'Lingkup',
            contextValue: 'Universitas',
            prefix: '/biro3',
            links: { alumni: '/biro3/alumni' },
        },
    };

    return configs[props.role] || configs.admin_biro3;
});

// Metrik kelengkapan yang dipilih pada panel detail.
const selectedCompletionMetric = computed(() => props.completionMetrics?.[activeCompletionMetric.value] || {
    label: 'Kelengkapan',
    completed_count: 0,
    population: 0,
    percentage: 0,
});

// Daftar pilihan panel kelengkapan yang mengabaikan kuesioner prodi.
const completionMetricOptions = [
    { key: 'profile_and_university', label: 'Gabungan', description: 'Profil dan kuesioner universitas lengkap' },
    { key: 'profile', label: 'Profil', description: 'Data profil alumni lengkap' },
    { key: 'university_questionnaire', label: 'Kuesioner Univ.', description: 'Kuesioner universitas lengkap' },
];

// Menyaring daftar program studi secara lokal agar tabel tetap responsif.
const filteredProdis = computed(() => {
    let list = [...props.prodiSummaries];
    const query = searchProdiQuery.value.trim().toLowerCase();

    if (query) {
        list = list.filter((prodi) => (prodi.nama_prodi || '').toLowerCase().includes(query) || String(prodi.kode_prodi || '').toLowerCase().includes(query));
    }

    return list.sort((first, second) => sortByRate.value
        ? second.response_rate - first.response_rate
        : first.response_rate - second.response_rate);
});

// Menyaring pratinjau alumni berdasarkan status audit dan pencarian.
const filteredAlumni = computed(() => {
    let list = [...props.recentAlumni];
    const query = searchAlumniQuery.value.trim().toLowerCase();

    if (filterStatus.value === 'responded') list = list.filter((alumni) => alumni.has_responded);
    if (filterStatus.value === 'not_responded') list = list.filter((alumni) => ! alumni.has_responded);
    if (filterStatus.value === 'linkedin') list = list.filter((alumni) => alumni.has_linkedin);
    if (query) {
        list = list.filter((alumni) => [alumni.nama, alumni.nim, alumni.prodi].some((value) => String(value || '').toLowerCase().includes(query)));
    }

    return list;
});

// Menerapkan filter tahun melalui endpoint dashboard role yang sedang aktif.
const applyYearFilter = () => {
    router.get(`${roleConfig.value.prefix}/dashboard`, { tahun: selectedYear.value }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

// Mengarahkan kartu ringkasan ke panel detail yang relevan.
const focusPanel = (panel) => {
    activeTab.value = panel === 'population' ? 'alumni' : 'prodi';
    if (panel === 'completion') activeCompletionMetric.value = 'profile_and_university';
    requestAnimationFrame(() => document.getElementById(panel === 'completion' ? 'completion-detail' : 'dashboard-detail')?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
};

// Menentukan warna indikator response rate berdasarkan ambang audit universitas.
const responseRateClass = (code) => ({
    red: 'bg-red-600',
    yellow: 'bg-[#FDC700]',
    green: 'bg-[#0D542B]',
}[code] || 'bg-[#FDC700]');

// Menentukan warna progress kelengkapan dengan dua warna utama dashboard.
const completionBarClass = (percentage) => percentage >= 100 ? 'bg-[#0D542B]' : 'bg-[#FDC700]';

// Mengambil status scope sebagai teks singkat pada header.
const scopeLabel = computed(() => roleConfig.value.contextValue);
</script>

<template>
    <!-- Head memakai judul scope agar tiap role mudah dikenali di browser. -->
    <Head :title="`${roleConfig.title} - Tracer Study UKDW`" />

    <!-- Root layout dashboard bersama dengan sidebar, header, dan konten responsif. -->
    <div class="min-h-screen bg-[#f8fafc] font-sans text-slate-800">
        <AdminSidebar :role="role" :user="user" :prodi="prodi" :fakultas="fakultas" active-menu="dashboard" />

        <div class="flex min-h-screen min-w-0 flex-col lg:pl-72">
            <!-- Header hijau bersama untuk seluruh role pengelola. -->
            <header class="bg-[#0D542B] px-4 pb-16 pt-8 text-white sm:px-6 lg:px-8">
                <div class="mx-auto w-full max-w-[1440px]">
                    <div class="mb-2.5 flex items-center gap-2 text-xs font-medium text-emerald-100">
                        <span>Tracer Study</span><span class="text-emerald-300">/</span><span class="rounded-md bg-white/10 px-2 py-0.5 font-bold">{{ roleConfig.label }}</span>
                    </div>
                    <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                        <div>
                            <h1 class="text-2xl font-extrabold tracking-tight text-white sm:text-3xl lg:text-4xl">{{ roleConfig.title }}</h1>
                            <p class="mt-1.5 max-w-3xl text-xs leading-relaxed text-emerald-100 sm:text-sm">{{ roleConfig.description }}</p>
                        </div>
                        <div class="shrink-0 rounded-lg bg-[#FDC700] px-3.5 py-2 text-[#0D542B]">
                            <span class="block text-[10px] font-bold uppercase tracking-wider">{{ roleConfig.contextLabel }}</span>
                            <span class="text-xs font-extrabold">{{ scopeLabel }}</span>
                        </div>
                    </div>
                    <div class="mt-5 flex flex-wrap items-center gap-2">
                        <label for="role-dashboard-year" class="text-xs font-bold text-white">Tahun lulusan</label>
                        <select id="role-dashboard-year" v-model="selectedYear" @change="applyYearFilter" class="cursor-pointer appearance-none rounded-lg bg-[#FDC700] px-3 py-2 text-xs font-extrabold text-[#0D542B] outline-none">
                            <option v-for="year in availableYears" :key="year" :value="year">{{ year }}</option>
                        </select>
                    </div>
                </div>
            </header>

            <main class="relative z-10 mx-auto -mt-10 flex w-full max-w-[1440px] min-w-0 flex-1 flex-col gap-6 px-4 pb-12 sm:px-6 lg:px-8">
                <!-- Kartu ringkasan utama dapat diklik untuk membuka panel detail. -->
                <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <button type="button" @click="focusPanel('response')" class="min-w-0 rounded-xl bg-white p-5 text-left shadow-sm transition-shadow hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0D542B]">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-[#0D542B]">Response rate universitas</p>
                        <div class="mt-4 flex items-end gap-2"><p class="text-3xl font-extrabold tracking-tight text-[#0D542B] sm:text-4xl">{{ dashboardStats.response_rate || 0 }}%</p><p class="pb-1 text-xs text-slate-500">{{ dashboardStats.total_responden || 0 }} dari {{ dashboardStats.total_alumni || 0 }} alumni</p></div>
                        <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-200"><div :class="['h-2 rounded-full transition-all', responseRateClass(dashboardStats.response_rate_status?.code)]" :style="{ width: `${Math.min(dashboardStats.response_rate || 0, 100)}%` }"></div></div>
                    </button>
                    <button type="button" @click="focusPanel('completion')" class="min-w-0 rounded-xl bg-white p-5 text-left shadow-sm transition-shadow hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0D542B]">
                        <div class="flex items-start justify-between gap-3"><p class="text-[11px] font-bold uppercase tracking-wider text-slate-600">Kelengkapan gabungan</p><span class="shrink-0 rounded bg-[#FDC700] px-1.5 py-0.5 text-[9px] font-bold text-[#0D542B]">Profil + Univ.</span></div>
                        <div class="mt-4 flex items-end gap-2"><p class="text-3xl font-extrabold tracking-tight text-[#0D542B] sm:text-4xl">{{ completionMetrics.profile_and_university?.percentage || 0 }}%</p><p class="pb-1 text-xs text-slate-500">{{ completionMetrics.profile_and_university?.completed_count || 0 }} alumni lengkap</p></div>
                        <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-200"><div :class="['h-2 rounded-full transition-all', completionBarClass(completionMetrics.profile_and_university?.percentage || 0)]" :style="{ width: `${Math.min(completionMetrics.profile_and_university?.percentage || 0, 100)}%` }"></div></div>
                    </button>
                    <button type="button" @click="focusPanel('population')" class="min-w-0 rounded-xl bg-white p-5 text-left shadow-sm transition-shadow hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0D542B] sm:col-span-2 xl:col-span-1">
                        <div class="flex items-start justify-between gap-3"><p class="text-[11px] font-bold uppercase tracking-wider text-slate-600">Populasi lulusan</p><span class="shrink-0 rounded bg-[#0D542B] px-1.5 py-0.5 text-[9px] font-bold text-white">{{ role === 'admin_prodi' ? 'Prodi' : role === 'admin_fakultas' ? 'Fakultas' : 'Universitas' }}</span></div>
                        <div class="mt-4 flex items-end gap-2"><p class="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">{{ dashboardStats.total_alumni || 0 }}</p><p class="pb-1 text-xs text-slate-500">alumni terdata</p></div>
                        <p class="mt-4 text-xs text-slate-500">Tahun lulusan {{ selectedYear }} pada {{ dashboardStats.total_prodi || 0 }} program studi.</p>
                    </button>
                </section>

                <!-- Pintasan hanya menampilkan route yang benar-benar tersedia untuk role. -->
                <section class="rounded-2xl bg-white p-5 shadow-sm">
                    <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center"><h2 class="text-xs font-extrabold uppercase tracking-wider text-slate-900">Akses cepat</h2><span class="text-xs text-slate-400">{{ dashboardStats.total_pending_perusahaan || 0 }} perusahaan menunggu verifikasi</span></div>
                    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                        <Link :href="roleConfig.links.alumni" class="rounded-xl bg-slate-50 px-3 py-3 text-center text-xs font-bold text-slate-700 transition-colors hover:bg-[#0D542B] hover:text-white">Direktori Alumni</Link>
                        <Link v-if="roleConfig.links.company" :href="roleConfig.links.company" class="rounded-xl bg-slate-50 px-3 py-3 text-center text-xs font-bold text-slate-700 transition-colors hover:bg-[#0D542B] hover:text-white">Master Perusahaan</Link>
                        <Link v-if="roleConfig.links.linkedin" :href="roleConfig.links.linkedin" class="rounded-xl bg-slate-50 px-3 py-3 text-center text-xs font-bold text-slate-700 transition-colors hover:bg-[#0D542B] hover:text-white">Sinkronisasi LinkedIn</Link>
                        <Link v-if="roleConfig.links.questionnaire" :href="roleConfig.links.questionnaire" class="rounded-xl bg-slate-50 px-3 py-3 text-center text-xs font-bold text-slate-700 transition-colors hover:bg-[#0D542B] hover:text-white">Kuesioner</Link>
                        <Link v-if="roleConfig.links.accounts" :href="roleConfig.links.accounts" class="rounded-xl bg-slate-50 px-3 py-3 text-center text-xs font-bold text-slate-700 transition-colors hover:bg-[#0D542B] hover:text-white">Manajemen Akun</Link>
                    </div>
                </section>

                <!-- Detail kelengkapan memakai indikator yang sama dengan Superadmin. -->
                <section id="completion-detail" class="overflow-hidden rounded-2xl bg-white shadow-sm">
                    <div class="px-5 py-5 sm:px-6"><h2 class="text-sm font-extrabold text-slate-900">Kelengkapan data</h2><p class="mt-1 text-xs text-slate-500">Pilih satu indikator untuk melihat detail. Kuesioner prodi tidak dihitung.</p><div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-3"><button v-for="option in completionMetricOptions" :key="option.key" type="button" @click="activeCompletionMetric = option.key" :title="option.description" :class="['w-full rounded-lg px-3 py-2.5 text-left text-xs font-bold transition-colors', activeCompletionMetric === option.key ? 'bg-[#0D542B] text-white' : 'bg-slate-100 text-slate-600 hover:bg-[#FDC700] hover:text-[#0D542B]']">{{ option.label }}</button></div></div>
                    <div class="grid grid-cols-1 gap-6 p-5 sm:p-6 lg:grid-cols-[1fr_1.5fr]"><div><p class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ selectedCompletionMetric.label }}</p><div class="mt-3 flex items-end gap-2"><span class="text-4xl font-extrabold tracking-tight text-[#0D542B]">{{ selectedCompletionMetric.percentage || 0 }}%</span><span class="pb-1 text-xs text-slate-500">lengkap</span></div><p class="mt-2 text-xs text-slate-500">{{ selectedCompletionMetric.completed_count || 0 }} dari {{ selectedCompletionMetric.population || 0 }} alumni</p></div><div class="flex flex-col justify-center"><div class="h-3 overflow-hidden rounded-full bg-slate-200"><div :class="['h-3 rounded-full transition-all', completionBarClass(selectedCompletionMetric.percentage || 0)]" :style="{ width: `${Math.min(selectedCompletionMetric.percentage || 0, 100)}%` }"></div></div><p class="mt-3 text-xs leading-5 text-slate-500">Persentase mengikuti status audit data. Pertanyaan yang tidak berlaku karena alur percabangan tidak dihitung sebagai kekurangan.</p></div></div>
                </section>

                <!-- Panel tabel interaktif untuk distribusi prodi dan alumni terbaru. -->
                <section id="dashboard-detail" class="overflow-hidden rounded-2xl bg-white shadow-sm">
                    <div class="flex flex-col justify-between gap-4 border-b border-slate-100 px-5 pb-3 pt-5 sm:flex-row sm:items-center sm:px-6"><div class="flex flex-wrap items-center gap-1.5"><button type="button" @click="activeTab = 'prodi'" :class="['rounded-lg px-3.5 py-2.5 text-xs font-bold transition-colors', activeTab === 'prodi' ? 'bg-[#0D542B] text-white' : 'text-slate-500 hover:bg-slate-100 hover:text-[#0D542B]']">Partisipasi Prodi ({{ prodiSummaries.length }})</button><button type="button" @click="activeTab = 'alumni'" :class="['rounded-lg px-3.5 py-2.5 text-xs font-bold transition-colors', activeTab === 'alumni' ? 'bg-[#0D542B] text-white' : 'text-slate-500 hover:bg-slate-100 hover:text-[#0D542B]']">Alumni Terbaru ({{ recentAlumni.length }})</button><button v-if="sectionsList.length" type="button" @click="activeTab = 'sections'" :class="['rounded-lg px-3.5 py-2.5 text-xs font-bold transition-colors', activeTab === 'sections' ? 'bg-[#0D542B] text-white' : 'text-slate-500 hover:bg-slate-100 hover:text-[#0D542B]']">Section Kuesioner ({{ sectionsList.length }})</button></div><Link :href="roleConfig.links.alumni" class="text-xs font-bold text-[#0D542B] hover:underline">Buka direktori lengkap</Link></div>

                    <div v-if="activeTab === 'prodi'" class="space-y-4 p-5 sm:p-6"><div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"><button type="button" @click="sortByRate = !sortByRate" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-[#FDC700] hover:text-[#0D542B]">Urutkan response rate: {{ sortByRate ? 'tertinggi' : 'terendah' }}</button><input v-model="searchProdiQuery" type="search" placeholder="Cari program studi..." class="w-full rounded-lg bg-slate-100 px-3 py-2 text-xs outline-none focus:bg-white sm:max-w-xs" /></div><div class="overflow-x-auto rounded-xl bg-slate-50"><table class="w-full min-w-[720px] text-left text-xs"><thead class="bg-slate-100 text-[11px] uppercase tracking-wider text-slate-600"><tr><th class="px-4 py-3">Kode</th><th class="px-4 py-3">Program Studi</th><th class="px-4 py-3 text-center">Total Alumni</th><th class="px-4 py-3 text-center">Responden</th><th class="px-4 py-3">Response rate</th><th class="px-4 py-3 text-right">Aksi</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="prodiItem in filteredProdis" :key="prodiItem.id" class="hover:bg-white"><td class="px-4 py-3 font-mono font-bold text-slate-600">{{ prodiItem.kode_prodi }}</td><td class="px-4 py-3 font-bold text-slate-900">{{ prodiItem.nama_prodi }}</td><td class="px-4 py-3 text-center font-semibold">{{ prodiItem.total_alumni }}</td><td class="px-4 py-3 text-center font-bold text-[#0D542B]">{{ prodiItem.total_responden }}</td><td class="min-w-[170px] px-4 py-3"><div class="flex items-center gap-2"><div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-200"><div :class="['h-2 rounded-full', responseRateClass(prodiItem.response_rate_status?.code)]" :style="{ width: `${Math.min(prodiItem.response_rate || 0, 100)}%` }"></div></div><span class="w-12 text-right text-xs font-extrabold text-[#0D542B]">{{ prodiItem.response_rate }}%</span></div></td><td class="px-4 py-3 text-right"><Link :href="`${roleConfig.links.alumni}?prodi_id=${prodiItem.id}`" class="rounded-lg bg-[#0D542B] px-2.5 py-1 text-[11px] font-bold text-white hover:bg-[#08381c]">Lihat Alumni</Link></td></tr><tr v-if="filteredProdis.length === 0"><td colspan="6" class="px-4 py-8 text-center text-slate-400">Tidak ada program studi yang sesuai.</td></tr></tbody></table></div></div>

                    <div v-else-if="activeTab === 'alumni'" class="space-y-4 p-5 sm:p-6"><div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"><div class="flex flex-wrap gap-1.5"><button v-for="filter in [{ key: 'all', label: 'Semua' }, { key: 'responded', label: 'Sudah Mengisi' }, { key: 'not_responded', label: 'Belum Mengisi' }, { key: 'linkedin', label: 'Ada LinkedIn' }]" :key="filter.key" type="button" @click="filterStatus = filter.key" :class="['rounded-lg px-3 py-1.5 text-xs font-bold transition-colors', filterStatus === filter.key ? 'bg-[#0D542B] text-white' : 'bg-slate-100 text-slate-600 hover:bg-[#FDC700] hover:text-[#0D542B]']">{{ filter.label }}</button></div><input v-model="searchAlumniQuery" type="search" placeholder="Cari NIM, nama, atau prodi..." class="w-full rounded-lg bg-slate-100 px-3 py-2 text-xs outline-none focus:bg-white sm:max-w-xs" /></div><div class="overflow-x-auto rounded-xl bg-slate-50"><table class="w-full min-w-[760px] text-left text-xs"><thead class="bg-slate-100 text-[11px] uppercase tracking-wider text-slate-600"><tr><th class="px-4 py-3">NIM & Mahasiswa</th><th class="px-4 py-3">Program Studi</th><th class="px-4 py-3 text-center">Tahun Lulus</th><th class="px-4 py-3 text-center">Status Respon</th><th class="px-4 py-3 text-center">LinkedIn</th><th class="px-4 py-3 text-right">Aksi</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="alumni in filteredAlumni" :key="alumni.id" class="hover:bg-white"><td class="px-4 py-3"><div class="font-bold text-slate-900">{{ alumni.nama }}</div><div class="font-mono text-[11px] text-slate-500">{{ alumni.nim }}</div></td><td class="px-4 py-3 font-semibold text-slate-700">{{ alumni.prodi }}</td><td class="px-4 py-3 text-center font-medium text-slate-700">{{ alumni.tahun_lulus }}</td><td class="px-4 py-3 text-center"><span :class="['rounded-full px-2 py-0.5 text-[10px] font-bold', alumni.has_responded ? 'bg-[#0D542B] text-white' : 'bg-[#FDC700] text-[#0D542B]']">{{ alumni.has_responded ? 'Sudah Mengisi' : 'Belum Mengisi' }}</span></td><td class="px-4 py-3 text-center"><span v-if="alumni.has_linkedin" class="rounded-full bg-[#0D542B] px-2 py-0.5 text-[10px] font-bold text-white">Terhubung</span><span v-else class="text-[11px] text-slate-400">-</span></td><td class="px-4 py-3 text-right"><Link :href="`${roleConfig.links.alumni}/${alumni.id}`" class="rounded-lg bg-[#0D542B] px-2.5 py-1 text-[11px] font-bold text-white hover:bg-[#08381c]">Audit</Link></td></tr><tr v-if="filteredAlumni.length === 0"><td colspan="6" class="px-4 py-8 text-center text-slate-400">Tidak ada data alumni yang sesuai.</td></tr></tbody></table></div></div>

                    <div v-else class="space-y-3 p-5 sm:p-6"><div v-for="section in sectionsList" :key="section.id" class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3"><span class="text-sm font-bold text-slate-800">{{ section.title }}</span><span class="text-xs font-bold text-[#0D542B]">{{ section.questions_count }} butir</span></div><Link v-if="roleConfig.links.sections" :href="roleConfig.links.sections" class="inline-flex rounded-lg bg-[#0D542B] px-3 py-2 text-xs font-bold text-white">Kelola section</Link></div>
                </section>
            </main>

            <!-- Footer singkat menjaga hierarki informasi tetap bersih. -->
            <footer class="border-t border-slate-100 py-5 text-center text-xs text-slate-400">&copy; {{ new Date().getFullYear() }} Universitas Kristen Duta Wacana. Hak Cipta Dilindungi.</footer>
        </div>
    </div>
</template>
