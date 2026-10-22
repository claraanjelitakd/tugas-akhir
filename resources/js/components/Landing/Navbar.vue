<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';

const isOpen = ref(false);
const isScrolled = ref(false);

const handleScroll = () => {
    isScrolled.value = window.scrollY > 20;
};

onMounted(() => {
    window.addEventListener('scroll', handleScroll, { passive: true });
    handleScroll();
});

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
});

const getDashboardUrl = (role) => {
    switch(role) {
        case 'superadmin': return '/superadmin/dashboard';
        case 'admin_biro3': return '/biro3/dashboard';
        case 'admin_fakultas': return '/fakultas/dashboard';
        case 'admin_prodi': return '/prodi/dashboard';
        case 'alumni': return '/alumni/dashboard';
        default: return '/';
    }
};
</script>

<template>
    <nav 
        class="sticky top-0 z-50 transition-all duration-300 text-white"
        :class="[
            isScrolled 
                ? 'bg-[#002f17]/40 backdrop-blur-2xl backdrop-saturate-200 border-b border-white/20 shadow-[0_8px_32px_0_rgba(0,0,0,0.35)] py-0.5' 
                : 'bg-[#004D25]/80 backdrop-blur-md border-b border-white/10 py-0'
        ]"
    >
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <!-- Logo & Branding (iOS Sleek Style) -->
                <div class="flex items-center">
                    <Link href="/" class="flex items-center space-x-3 group">
                        <div class="w-10 h-10 rounded-xl bg-white p-1.5 flex items-center justify-center shadow-xs group-hover:scale-105 transition-transform">
                            <img src="/uploads/logo/logo-ukdw.png" alt="Logo UKDW" class="w-full h-full object-contain" />
                        </div>
                        <div class="flex flex-col">
                            <span class="text-base font-black text-white leading-tight group-hover:text-[#FFC700] transition-colors tracking-tight">TRACER STUDY</span>
                            <span class="text-[11px] text-white/75 font-semibold tracking-wide">Universitas Kristen Duta Wacana</span>
                        </div>
                    </Link>
                </div>

                <!-- Desktop Menu (iOS Glass Pills) -->
                <div class="hidden sm:flex sm:items-center sm:space-x-2 lg:space-x-3 text-xs lg:text-sm font-bold">
                    <Link href="/" class="text-white/85 hover:text-white hover:bg-white/15 px-3 py-1.5 rounded-full transition-all">HOME</Link>
                    <a href="#tentang" class="text-white/85 hover:text-white hover:bg-white/15 px-3 py-1.5 rounded-full transition-all">TENTANG</a>
                    <a href="#panduan" class="text-white/85 hover:text-white hover:bg-white/15 px-3 py-1.5 rounded-full transition-all">PANDUAN</a>
                    <a href="#statistik" class="text-white/85 hover:text-white hover:bg-white/15 px-3 py-1.5 rounded-full transition-all">STATISTIK</a>
                    <a href="#peta-alumni" class="text-white/85 hover:text-white hover:bg-white/15 px-3 py-1.5 rounded-full transition-all">PETA SEBARAN</a>
                    <a href="#berkas" class="text-white/85 hover:text-white hover:bg-white/15 px-3 py-1.5 rounded-full transition-all">BERKAS</a>
                    
                    <div class="pl-2">
                        <Link
                            v-if="!$page.props.auth?.user"
                            href="/login"
                            class="bg-[#FFC700] hover:bg-[#FBBF24] text-slate-950 px-5 py-2 rounded-full text-xs lg:text-sm font-black transition-all shadow-md hover:shadow-lg hover:scale-105 active:scale-95 inline-flex items-center gap-1.5 cursor-pointer"
                        >
                            <span>MASUK</span>
                        </Link>
                        <Link
                            v-else
                            :href="getDashboardUrl($page.props.auth.user.role)"
                            class="bg-white/20 hover:bg-white/30 text-white border border-white/25 px-5 py-2 rounded-full text-xs lg:text-sm font-bold transition-all shadow-xs hover:shadow-md hover:scale-105 active:scale-95 inline-flex items-center gap-1.5 cursor-pointer backdrop-blur-md"
                        >
                            <span>DASHBOARD</span>
                        </Link>
                    </div>
                </div>

                <!-- Mobile menu button -->
                <div class="flex items-center sm:hidden">
                    <button @click="isOpen = !isOpen" class="inline-flex items-center justify-center p-2 rounded-xl text-white/80 hover:text-[#FFC700] hover:bg-white/10 focus:outline-none cursor-pointer">
                        <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                            <path v-if="!isOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Menu (iOS Frosted Dropdown) -->
        <div v-show="isOpen" class="sm:hidden border-t border-white/15 bg-[#00381A]/95 backdrop-blur-2xl text-white">
            <div class="px-4 pt-3 pb-5 space-y-1.5">
                <Link href="/" class="block px-3 py-2 rounded-xl text-sm font-bold text-white/90 hover:text-[#FFC700] hover:bg-white/10">HOME</Link>
                <a href="#tentang" @click="isOpen = false" class="block px-3 py-2 rounded-xl text-sm font-bold text-white/90 hover:text-[#FFC700] hover:bg-white/10">TENTANG</a>
                <a href="#panduan" @click="isOpen = false" class="block px-3 py-2 rounded-xl text-sm font-bold text-white/90 hover:text-[#FFC700] hover:bg-white/10">PANDUAN</a>
                <a href="#statistik" @click="isOpen = false" class="block px-3 py-2 rounded-xl text-sm font-bold text-white/90 hover:text-[#FFC700] hover:bg-white/10">STATISTIK</a>
                <a href="#peta-alumni" @click="isOpen = false" class="block px-3 py-2 rounded-xl text-sm font-bold text-white/90 hover:text-[#FFC700] hover:bg-white/10">PETA SEBARAN</a>
                <a href="#berkas" @click="isOpen = false" class="block px-3 py-2 rounded-xl text-sm font-bold text-white/90 hover:text-[#FFC700] hover:bg-white/10">BERKAS</a>
                
                <Link
                    v-if="!$page.props.auth?.user"
                    href="/login"
                    class="block w-full text-center mt-3 bg-[#FFC700] text-slate-950 py-2.5 rounded-full text-sm font-black hover:bg-[#FBBF24]"
                >
                    MASUK
                </Link>
                <Link
                    v-else
                    :href="getDashboardUrl($page.props.auth.user.role)"
                    class="block w-full text-center mt-3 bg-white/20 text-white py-2.5 rounded-full text-sm font-bold hover:bg-white/30"
                >
                    DASHBOARD
                </Link>
            </div>
        </div>
    </nav>
</template>
