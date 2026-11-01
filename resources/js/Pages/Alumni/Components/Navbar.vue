<script setup>
/**
 * Komponen Navigasi Utama (Navbar) Portal Alumni UKDW.
 *
 * Menampilkan logo institusi, tautan navigasi halaman alumni, serta menu profil
 * berbentuk lingkaran (foto profil otomatis terisi jika sudah diunggah, atau inisial)
 * dengan indikator caping ke bawah yang membuka dropdown berisi Nama, NIM, dan aksi Keluar.
 */
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import Swal from 'sweetalert2';

// Kontrak properti user yang dikirim oleh halaman induk
const props = defineProps({
    user: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();

// State buka/tutup menu dropdown profil di kanan atas
const isDropdownOpen = ref(false);
const dropdownRef = ref(null);

// Mengambil objek user aktif dari prop atau shared Inertia auth
const currentUser = computed(() => {
    return props.user?.name ? props.user : (page.props.auth?.user || { name: 'Alumni UKDW' });
});

// Mengambil URL foto profil alumni jika tersedia di biodata atau objek user
const userPhotoUrl = computed(() => {
    const rawPhoto = currentUser.value?.foto
        || currentUser.value?.alumni?.foto
        || page.props.auth?.user?.alumni?.foto
        || page.props.formData?.foto
        || '';

    return typeof rawPhoto === 'string' && rawPhoto.trim() !== '' ? rawPhoto.trim() : '';
});

// Mengambil inisial maksimal 2 huruf dari nama alumni jika foto belum ada
const userInitials = computed(() => {
    return (currentUser.value.name || 'AL')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((name) => name.charAt(0))
        .join('')
        .toUpperCase();
});

// Mengambil NIM atau username alumni untuk ditampilkan di dalam dropdown
const userNim = computed(() => {
    return currentUser.value?.alumni?.nim
        || currentUser.value?.username
        || currentUser.value?.nim
        || page.props.auth?.user?.alumni?.nim
        || 'Alumni';
});

// Indikator menu navigasi yang sedang aktif berdasarkan URL
const isDashboardActive = computed(() => {
    return page.url === '/alumni' || page.url.startsWith('/alumni/dashboard');
});

const isProfileActive = computed(() => page.url.startsWith('/alumni/profile'));

const isKuesionerActive = computed(() => {
    return page.url === '/alumni/kuesioner' || (page.url.startsWith('/alumni/kuesioner') && !page.url.startsWith('/alumni/kuesioner-prodi'));
});

const isKuesionerProdiActive = computed(() => page.url.startsWith('/alumni/kuesioner-prodi'));

// Daftar menu navigasi utama alumni
const navigationItems = [
    { href: '/alumni/dashboard', label: 'Dashboard', active: isDashboardActive },
    { href: '/alumni/profile', label: 'Profil Alumni', active: isProfileActive },
    { href: '/alumni/kuesioner', label: 'Kuesioner Universitas', active: isKuesionerActive },
    { href: '/alumni/kuesioner-prodi', label: 'Kuesioner Prodi', active: isKuesionerProdiActive },
];

// Toggle menu dropdown saat tombol avatar diklik
const toggleDropdown = () => {
    isDropdownOpen.value = !isDropdownOpen.value;
};

// Menutup dropdown saat pengguna klik di luar area menu
const handleClickOutside = (event) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
        isDropdownOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside);
});

// Konfirmasi keluar sesi menggunakan modal resmi
const handleLogout = () => {
    isDropdownOpen.value = false;
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
                <!-- Kiri: Identitas Aplikasi & Logo UKDW -->
                <Link href="/alumni/dashboard" class="group flex min-w-0 items-center gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white p-1 shadow-sm transition-shadow group-hover:shadow-md">
                        <img src="/uploads/logo/logo-ukdw.png" alt="Logo UKDW" class="h-full w-full object-contain" />
                    </div>
                    <div class="min-w-0">
                        <span class="block truncate text-[15px] font-bold tracking-tight text-slate-950 transition-colors group-hover:text-[#0D542B] sm:text-lg">Tracer Study UKDW</span>
                        <span class="block text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">Portal Alumni</span>
                    </div>
                </Link>

                <!-- Tengah: Menu Navigasi Desktop -->
                <div class="hidden items-center justify-center gap-1 lg:flex">
                    <Link
                        v-for="item in navigationItems"
                        :key="item.href"
                        :href="item.href"
                        class="relative whitespace-nowrap px-4 py-3 text-sm font-semibold text-slate-600 transition-colors hover:text-slate-950"
                        :class="item.active.value ? 'text-[#0D542B]' : ''"
                        :aria-current="item.active.value ? 'page' : undefined"
                    >
                        {{ item.label }}
                        <span v-if="item.active.value" class="absolute inset-x-4 -bottom-[1px] h-[3px] rounded-t-full bg-[#0D542B]" aria-hidden="true"></span>
                    </Link>
                </div>

                <!-- Kanan: Lingkaran Avatar + Caping Bawah (Dropdown Nama, NIM, & Keluar) -->
                <div ref="dropdownRef" class="relative flex items-center justify-end">
                    <button
                        type="button"
                        @click="toggleDropdown"
                        class="group inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white p-1 pr-2.5 shadow-2xs transition-all hover:border-[#0D542B]/40 hover:bg-slate-50 focus:outline-none cursor-pointer"
                        :aria-expanded="isDropdownOpen"
                        aria-haspopup="true"
                        title="Menu Akun Alumni"
                    >
                        <!-- Lingkaran Foto Profil (Otomatis Terisi) atau Inisial -->
                        <span class="relative flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-[#E7F3ED] text-xs font-bold text-[#0D542B] ring-1 ring-[#0D542B]/15">
                            <img
                                v-if="userPhotoUrl"
                                :src="userPhotoUrl"
                                :alt="currentUser.name"
                                class="h-full w-full object-cover object-top"
                            />
                            <span v-else>{{ userInitials }}</span>
                        </span>

                        <!-- Ikon Caping ke Bawah -->
                        <svg
                            class="h-4 w-4 text-slate-500 transition-transform duration-200 group-hover:text-slate-800"
                            :class="{ 'rotate-180 text-[#0D542B]': isDropdownOpen }"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <!-- Menu Dropdown (Nama Alumni, NIM, Tautan Profil, dan Keluar) -->
                    <transition
                        enter-active-class="transition duration-150 ease-out"
                        enter-from-class="transform scale-95 opacity-0 -translate-y-1"
                        enter-to-class="transform scale-100 opacity-100 translate-y-0"
                        leave-active-class="transition duration-100 ease-in"
                        leave-from-class="transform scale-100 opacity-100 translate-y-0"
                        leave-to-class="transform scale-95 opacity-0 -translate-y-1"
                    >
                        <div
                            v-if="isDropdownOpen"
                            class="absolute right-0 top-full mt-2 w-60 origin-top-right rounded-2xl border border-slate-200 bg-white py-2 shadow-lg ring-1 ring-black/5 z-50"
                        >
                            <!-- Informasi Nama & NIM Alumni -->
                            <div class="border-b border-slate-100 px-4 py-3">
                                <p class="truncate text-sm font-bold text-slate-900">
                                    {{ currentUser.name }}
                                </p>
                                <p class="mt-0.5 font-mono text-xs font-medium text-slate-500">
                                    NIM: {{ userNim }}
                                </p>
                            </div>

                            <!-- Aksi Cepat ke Profil -->
                            <div class="py-1">
                                <Link
                                    href="/alumni/profile"
                                    class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-50 hover:text-[#0D542B]"
                                    @click="isDropdownOpen = false"
                                >
                                    <span>Kelola Profil Alumni</span>
                                </Link>
                            </div>

                            <!-- Tombol Keluar -->
                            <div class="border-t border-slate-100 pt-1">
                                <button
                                    type="button"
                                    @click="handleLogout"
                                    class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-xs font-bold text-slate-700 transition-colors hover:bg-slate-100 hover:text-slate-900 cursor-pointer"
                                >
                                    <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                    <span>Keluar</span>
                                </button>
                            </div>
                        </div>
                    </transition>
                </div>
            </div>
        </div>

        <!-- Menu Navigasi Mobile -->
        <div class="flex gap-1 overflow-x-auto border-t border-slate-100 px-3 py-2 text-xs lg:hidden">
            <Link
                v-for="item in navigationItems"
                :key="`mobile-${item.href}`"
                :href="item.href"
                class="relative whitespace-nowrap px-3 py-2 font-semibold text-slate-600 transition-colors hover:text-slate-950"
                :class="item.active.value ? 'text-[#0D542B]' : ''"
                :aria-current="item.active.value ? 'page' : undefined"
            >
                {{ item.label === 'Profil Alumni' ? 'Profil' : item.label }}
                <span v-if="item.active.value" class="absolute inset-x-3 -bottom-2 h-0.5 rounded-full bg-[#0D542B]" aria-hidden="true"></span>
            </Link>
        </div>
    </nav>
</template>
