<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    user: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();

const currentUser = computed(() => {
    return props.user?.name ? props.user : (page.props.auth?.user || { name: 'Alumni UKDW' });
});

const userInitials = computed(() => {
    return currentUser.value.name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((name) => name.charAt(0))
        .join('')
        .toUpperCase();
});

const isDashboardActive = computed(() => {
    return page.url === '/alumni' || page.url.startsWith('/alumni/dashboard');
});

const isProfileActive = computed(() => page.url.startsWith('/alumni/profile'));

const isKuesionerActive = computed(() => {
    return page.url === '/alumni/kuesioner' || (page.url.startsWith('/alumni/kuesioner') && !page.url.startsWith('/alumni/kuesioner-prodi'));
});

const isKuesionerProdiActive = computed(() => page.url.startsWith('/alumni/kuesioner-prodi'));

const navigationItems = [
    { href: '/alumni/dashboard', label: 'Dashboard', active: isDashboardActive },
    { href: '/alumni/profile', label: 'Profil Alumni', active: isProfileActive },
    { href: '/alumni/kuesioner', label: 'Kuesioner Universitas', active: isKuesionerActive },
    { href: '/alumni/kuesioner-prodi', label: 'Kuesioner Prodi', active: isKuesionerProdiActive },
];

const handleLogout = () => {
    Swal.fire({
        title: 'Konfirmasi Keluar',
        text: 'Apakah Anda yakin ingin mengakhiri sesi Tracer Study?',
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
    <nav class="sticky top-0 z-50 border-b border-slate-200 bg-white">
        <div class="mx-auto max-w-[1800px] px-4 sm:px-6 lg:px-10">
            <div class="grid min-h-[82px] grid-cols-[auto_1fr_auto] items-center gap-5 lg:gap-8">
                <Link href="/alumni/dashboard" class="group flex min-w-0 items-center gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white p-1 shadow-sm transition-shadow group-hover:shadow-md">
                        <img src="/uploads/logo/logo-ukdw.png" alt="Logo UKDW" class="h-full w-full object-contain" />
                    </div>
                    <div class="min-w-0">
                        <span class="block truncate text-[15px] font-bold tracking-tight text-slate-950 transition-colors group-hover:text-[#005B3C] sm:text-lg">Tracer Study UKDW</span>
                        <span class="block text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">Portal Alumni</span>
                    </div>
                </Link>

                <div class="hidden items-center justify-center gap-1 lg:flex">
                    <Link
                        v-for="item in navigationItems"
                        :key="item.href"
                        :href="item.href"
                        class="relative whitespace-nowrap px-4 py-3 text-sm font-semibold text-slate-600 transition-colors hover:text-slate-950"
                        :class="item.active.value ? 'text-[#005B3C]' : ''"
                        :aria-current="item.active.value ? 'page' : undefined"
                    >
                        {{ item.label }}
                        <span v-if="item.active.value" class="absolute inset-x-4 -bottom-[1px] h-[3px] rounded-t-full bg-[#0D542B]" aria-hidden="true"></span>
                    </Link>
                </div>

                <div class="flex min-w-0 items-center justify-end gap-3 sm:gap-4">
                    <div class="hidden items-center gap-2.5 border-l border-slate-200 pl-4 sm:flex">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#E7F3ED] text-xs font-bold text-[#0D542B]" aria-hidden="true">{{ userInitials }}</span>
                        <div class="min-w-0 text-right">
                            <span class="block max-w-[170px] truncate text-sm font-semibold leading-tight text-slate-900">{{ currentUser.name }}</span>
                            <span class="mt-1 block font-mono text-[10px] font-medium tracking-wide text-slate-400">{{ currentUser.username || currentUser.nim || 'Alumni' }}</span>
                        </div>
                    </div>
                    <button @click="handleLogout" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-slate-200 px-3 text-slate-600 transition-colors hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600 sm:px-3.5" title="Keluar dari akun" aria-label="Keluar">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        <span class="hidden text-sm font-semibold sm:inline">Keluar</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="flex gap-1 overflow-x-auto border-t border-slate-100 px-3 py-2 text-xs lg:hidden">
            <Link
                v-for="item in navigationItems"
                :key="`mobile-${item.href}`"
                :href="item.href"
                class="relative whitespace-nowrap px-3 py-2 font-semibold text-slate-600 transition-colors hover:text-slate-950"
                :class="item.active.value ? 'text-[#005B3C]' : ''"
                :aria-current="item.active.value ? 'page' : undefined"
            >
                {{ item.label === 'Profil Alumni' ? 'Profil' : item.label }}
                <span v-if="item.active.value" class="absolute inset-x-3 -bottom-2 h-0.5 rounded-full bg-[#0D542B]" aria-hidden="true"></span>
            </Link>
        </div>
    </nav>
</template>
