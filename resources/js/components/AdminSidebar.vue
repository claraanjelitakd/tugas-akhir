<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    role: { type: String, default: 'superadmin' },
    user: { type: Object, default: () => ({}) },
    prodi: { type: Object, default: () => ({}) },
    fakultas: { type: Object, default: () => ({}) },
    activeMenu: { type: String, default: '' },
});

const page = usePage();
const isMobileOpen = ref(false);

const roleDefinitions = {
    superadmin: {
        dashboard: '/superadmin/dashboard',
        roleLabel: 'Super Administrator',
        fallbackName: 'Super Administrator',
        fallbackEmail: 'admin@ukdw.ac.id',
        alumniLabel: 'Data Alumni',
        capabilities: { universityQuestionnaire: true, prodiQuestionnaire: true, supervisorEvaluation: true, wilayah: true, linkedin: true, company: true, accounts: true, logs: true, analytics: true },
    },
    admin_prodi: {
        dashboard: '/prodi/dashboard',
        roleLabel: 'Admin Program Studi',
        fallbackName: 'Admin Program Studi',
        fallbackEmail: 'prodi@ukdw.ac.id',
        alumniLabel: 'Data Alumni Prodi',
        capabilities: { prodiQuestionnaire: true, linkedin: true, company: true, accounts: true },
    },
    admin_fakultas: {
        dashboard: '/fakultas/dashboard',
        roleLabel: 'Admin Fakultas',
        fallbackName: 'Admin Fakultas',
        fallbackEmail: 'fakultas@ukdw.ac.id',
        alumniLabel: 'Data Alumni Fakultas',
        capabilities: { prodiQuestionnaire: true, linkedin: true, company: true, accounts: true },
    },
    admin_biro3: {
        dashboard: '/biro3/dashboard',
        roleLabel: 'Biro 3 Kemahasiswaan',
        fallbackName: 'Admin Biro 3 UKDW',
        fallbackEmail: 'biro3@ukdw.ac.id',
        alumniLabel: 'Data Alumni Universitas',
        capabilities: {},
    },
};

const createMenuItem = (key, href, label, icon, prefixes = []) => ({
    key,
    href,
    label,
    icon,
    ...(prefixes.length > 0 ? { prefixes } : {}),
});

const createSections = (role) => {
    const definition = roleDefinitions[role] || roleDefinitions.superadmin;
    const capabilities = definition.capabilities || {};
    const prefix = definition.dashboard.split('/')[1];
    const prodiQuestionnaireHref = role === 'admin_prodi'
        ? '/prodi/kuesioner'
        : role === 'admin_fakultas'
            ? '/fakultas/prodi-kuesioner'
            : '/superadmin/prodi-kuesioner';
    const sections = [
        {
            label: 'Ringkasan & Analisis',
            items: [
                createMenuItem('dashboard', definition.dashboard, 'Dashboard Utama', 'home'),
                ...(capabilities.analytics ? [createMenuItem('analytics', '/superadmin/analitik', 'Grafik Universitas', 'chart')] : []),
                createMenuItem('alumni', `/${prefix}/alumni`, definition.alumniLabel, 'users'),
                ...(capabilities.linkedin ? [createMenuItem('linkedin-sync', `/${prefix}/linkedin-sync`, 'Sinkronisasi LinkedIn', 'linkedin')] : []),
            ],
        },
        {
            label: 'Manajemen Kuesioner',
            items: [
                ...(capabilities.universityQuestionnaire ? [createMenuItem('kuesioner-univ', '/superadmin/pertanyaan', 'Kuesioner Universitas', 'document', ['/superadmin/pertanyaan', '/superadmin/sections'])] : []),
                ...(capabilities.prodiQuestionnaire ? [createMenuItem(
                    'kuesioner-prodi',
                    prodiQuestionnaireHref,
                    'Kuesioner Per Prodi',
                    'book',
                    role === 'admin_prodi' ? ['/prodi/kuesioner', '/prodi/sections', '/prodi/section', '/prodi/pertanyaan'] : [],
                )] : []),
                ...(capabilities.supervisorEvaluation ? [createMenuItem('evaluasi-atasan', '/superadmin/evaluasi-atasan', 'Kuesioner Evaluasi Atasan', 'checklist')] : []),
            ],
        },
        {
            label: 'Master Data & Verifikasi',
            items: [
                ...(capabilities.wilayah ? [createMenuItem('master-wilayah', '/superadmin/master-wilayah', 'Master Wilayah & UMP', 'globe')] : []),
                ...(capabilities.company ? [createMenuItem('perusahaan', `/${prefix}/perusahaan`, 'Master Data Perusahaan', 'building')] : []),
                ...(capabilities.accounts ? [createMenuItem('manajemen-akun', `/${prefix}/manajemen-akun`, 'Manajemen Akun', 'key')] : []),
                ...(capabilities.logs ? [createMenuItem('logs', '/superadmin/logs', 'Log Aktivitas', 'document')] : []),
            ],
        },
    ];

    return sections.filter((section) => section.items.length > 0);
};

const roleConfig = computed(() => {
    const definition = roleDefinitions[props.role] || roleDefinitions.superadmin;

    return {
        ...definition,
        sections: createSections(props.role),
    };
});

const currentUser = computed(() => props.user?.name ? props.user : (page.props.auth?.user || {
    name: roleConfig.value.fallbackName,
    email: roleConfig.value.fallbackEmail,
}));

const currentScope = computed(() => {
    if (props.role === 'admin_prodi') {
        return props.prodi?.nama_prodi || page.props.auth?.user?.prodi?.nama_prodi || 'Program Studi';
    }
    if (props.role === 'admin_fakultas') {
        return props.fakultas?.nama_fakultas || page.props.auth?.user?.fakultas?.nama_fakultas || 'Fakultas';
    }
    if (props.role === 'admin_biro3') {
        return 'Biro 3 Kemahasiswaan';
    }
    return 'Universitas';
});

const userInitials = computed(() => (currentUser.value?.name || 'UK').split(' ').map((part) => part[0]).slice(0, 2).join('').toUpperCase());

const isActive = (item) => {
    if (props.activeMenu && props.activeMenu === item.key) {
        return true;
    }

    return (item.prefixes || [item.href]).some((prefix) => page.url === prefix || page.url.startsWith(`${prefix}/`));
};

const toggleMobileMenu = () => { isMobileOpen.value = !isMobileOpen.value; };
const closeMobileMenu = () => { isMobileOpen.value = false; };

const handleLogout = () => {
    Swal.fire({
        title: 'Konfirmasi Keluar',
        text: `Apakah Anda yakin ingin keluar dari sesi ${roleConfig.value.roleLabel}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0D542B',
        cancelButtonColor: '#9CA3AF',
        confirmButtonText: 'Ya, Keluar',
        cancelButtonText: 'Batal',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            router.post('/logout');
        }
    });
};
</script>

<template>
    <div>
        <header class="lg:hidden sticky top-0 z-40 flex items-center justify-between border-b border-slate-200 bg-white px-4 py-2.5 shadow-2xs">
            <div class="flex items-center gap-3">
                <button type="button" @click="toggleMobileMenu" class="cursor-pointer rounded-lg p-1.5 text-slate-700 transition-colors hover:bg-slate-100 hover:text-slate-900 focus:outline-none" aria-label="Buka Menu">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>
                <Link :href="roleConfig.dashboard" class="flex items-center gap-2">
                    <img src="/uploads/logo/logo-ukdw.png" alt="Logo UKDW" class="h-7 w-7 object-contain" onerror="this.style.display='none'" />
                    <div><span class="block text-xs font-bold leading-tight tracking-tight text-slate-900">Tracer Study</span><span class="block text-[10px] font-semibold uppercase tracking-wider text-[#0D542B]">{{ roleConfig.roleLabel }}</span></div>
                </Link>
            </div>
            <span class="rounded-md border border-slate-200 bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-700">{{ userInitials }}</span>
        </header>

        <div v-if="isMobileOpen" class="fixed inset-0 z-50 bg-black/40 transition-opacity duration-200 lg:hidden" @click="closeMobileMenu"></div>

        <aside class="fixed inset-y-0 left-0 z-50 flex h-screen w-72 flex-col justify-between overflow-hidden border-r border-slate-200 bg-white shadow-lg transition-transform duration-200 ease-in-out lg:shadow-none" :class="isMobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">
            <div class="shrink-0 border-b border-slate-200 bg-white p-4">
                <div class="flex items-center justify-between">
                    <Link :href="roleConfig.dashboard" class="flex min-w-0 items-center gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white p-1 shadow-2xs"><img src="/uploads/logo/logo-ukdw.png" alt="Logo UKDW" class="h-full w-full object-contain" onerror="this.style.display='none'" /></div>
                        <div class="min-w-0 flex-1"><span class="block text-sm font-bold leading-tight tracking-tight text-slate-900">Tracer Study UKDW</span><span class="mt-0.5 block truncate text-[11px] font-semibold uppercase tracking-wider text-[#0D542B]">{{ roleConfig.roleLabel }}</span></div>
                    </Link>
                    <button type="button" @click="closeMobileMenu" class="rounded-md p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 lg:hidden" aria-label="Tutup Menu"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>
                </div>
            </div>

            <nav class="min-h-0 flex-1 space-y-1 overflow-y-auto px-3 py-3">
                <div v-for="section in roleConfig.sections" :key="section.label">
                    <div class="px-2.5 pb-1 pt-3 first:pt-1"><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ section.label }}</span></div>
                    <Link v-for="item in section.items" :key="item.key" :href="item.href" @click="closeMobileMenu" :aria-current="isActive(item) ? 'page' : undefined" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-xs font-semibold transition-colors" :class="isActive(item) ? 'bg-[#0D542B] text-white shadow-2xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'">
                        <svg v-if="item.icon === 'linkedin'" class="h-4 w-4 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z" /></svg>
                        <svg v-else class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path v-if="item.icon === 'home'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001 1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            <path v-else-if="item.icon === 'users'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            <path v-else-if="item.icon === 'building'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            <path v-else-if="item.icon === 'book'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                            <path v-else-if="item.icon === 'document'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            <path v-else-if="item.icon === 'checklist'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                            <path v-else-if="item.icon === 'globe'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            <path v-else-if="item.icon === 'chart'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 20V10m7 10V4m7 16v-7" />
                            <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 11-4 0 2 2 0 014 0zM4 19v-2a3 3 0 013-3h10a3 3 0 013 3v2" />
                        </svg>
                        <span class="truncate">{{ item.label }}</span>
                    </Link>
                </div>
            </nav>

            <div class="shrink-0 border-t border-slate-200 bg-white p-3"><div class="flex items-center justify-between gap-2.5 rounded-lg border border-slate-200 bg-slate-50 p-2.5"><div class="flex min-w-0 flex-1 items-center gap-2.5"><div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#0D542B] text-xs font-bold text-white shadow-2xs">{{ userInitials }}</div><div class="min-w-0 flex-1"><span class="block truncate text-xs font-semibold text-slate-900">{{ currentUser.name }}</span><span class="block truncate text-[10px] text-slate-500">{{ currentScope }}</span></div></div><button type="button" @click="handleLogout" class="shrink-0 cursor-pointer rounded-md border border-transparent p-1.5 text-slate-400 transition-colors hover:border-slate-200 hover:bg-white hover:text-rose-600" title="Keluar dari Sistem" aria-label="Logout"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636a9 9 0 11-12.728 0M12 3v9" /></svg></button></div></div>
        </aside>
    </div>
</template>
