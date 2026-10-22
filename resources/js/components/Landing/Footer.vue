<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Link } from '@inertiajs/vue3';

const currentYear = new Date().getFullYear();
const showScrollTop = ref(false);

const checkScroll = () => {
    showScrollTop.value = window.scrollY > 300;
};

const scrollToTop = () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

onMounted(() => {
    window.addEventListener('scroll', checkScroll);
});

onUnmounted(() => {
    window.removeEventListener('scroll', checkScroll);
});
</script>

<template>
    <footer class="bg-[#004D25] text-white border-t border-white/10 relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-8 mb-10">
                
                <!-- Col 1: Kontak Kami & Alamat Biro 3 UKDW -->
                <div class="md:col-span-5">
                    <div class="flex items-center space-x-3 mb-4">
                        <div class="w-11 h-11 rounded-2xl bg-white p-1.5 flex items-center justify-center shadow-md">
                            <img src="/uploads/logo/logo-ukdw.png" alt="Logo UKDW" class="w-full h-full object-contain" />
                        </div>
                        <div>
                            <span class="text-base font-black text-white leading-tight block">TRACER STUDY UKDW</span>
                            <span class="text-xs text-white/80 font-semibold tracking-wide">Universitas Kristen Duta Wacana</span>
                        </div>
                    </div>
                    
                    <h4 class="text-xs font-black text-[#FFC700] uppercase tracking-widest mb-2">Kontak Kami</h4>
                    <p class="text-xs text-white/85 leading-relaxed max-w-sm mb-2 font-medium">
                        Biro 3 UKDW (Kemahasiswaan, Alumni &amp; Pengembangan Karir)<br/>
                        Jl. Dr. Wahidin Sudirohusodo No. 5-25 Yogyakarta 55224
                    </p>
                    <div class="space-y-1 text-xs text-white/80">
                        <p class="flex items-center gap-2">
                            <span class="text-[#FFC700] font-bold">Email:</span>
                            <a href="mailto:biro3@staff.ukdw.ac.id" class="hover:text-[#FFC700] transition-colors underline">biro3@staff.ukdw.ac.id</a>
                        </p>
                        <p class="flex items-center gap-2">
                            <span class="text-[#FFC700] font-bold">Telp:</span>
                            <span>(0274) 563929 ext. 103</span>
                        </p>
                    </div>
                </div>

                <!-- Col 2: Navigasi Cepat -->
                <div class="md:col-span-3">
                    <h3 class="text-xs font-black text-[#FFC700] uppercase tracking-widest mb-3">Navigasi Halaman</h3>
                    <ul class="space-y-2 text-xs">
                        <li>
                            <Link href="/" class="text-white/85 hover:text-[#FFC700] transition-colors font-medium">HOME</Link>
                        </li>
                        <li>
                            <a href="#tentang" class="text-white/85 hover:text-[#FFC700] transition-colors font-medium">TENTANG</a>
                        </li>
                        <li>
                            <a href="#panduan" class="text-white/85 hover:text-[#FFC700] transition-colors font-medium">PANDUAN</a>
                        </li>
                        <li>
                            <a href="#statistik" class="text-white/85 hover:text-[#FFC700] transition-colors font-medium">STATISTIK</a>
                        </li>
                        <li>
                            <a href="#peta-alumni" class="text-white/85 hover:text-[#FFC700] transition-colors font-medium">PETA SEBARAN</a>
                        </li>
                        <li>
                            <a href="#berkas" class="text-white/85 hover:text-[#FFC700] transition-colors font-medium">BERKAS</a>
                        </li>
                    </ul>
                </div>

                <!-- Col 3: Tautan Penting -->
                <div class="md:col-span-4">
                    <h3 class="text-xs font-black text-[#FFC700] uppercase tracking-widest mb-3">Tautan Penting</h3>
                    <ul class="space-y-2.5 text-xs mb-5">
                        <li>
                            <Link href="/login" class="text-white/85 hover:text-[#FFC700] transition-colors font-medium flex items-center gap-1.5">
                                <span>Login Admin &amp; Alumni</span>
                            </Link>
                        </li>
                        <li>
                            <a href="https://www.ukdw.ac.id/" target="_blank" class="text-white/85 hover:text-[#FFC700] transition-colors font-medium flex items-center gap-1.5">
                                <span>Universitas Kristen Duta Wacana</span>
                            </a>
                        </li>
                        <li>
                            <a href="https://tracerstudy.ukdw.ac.id/" target="_blank" class="text-white/85 hover:text-[#FFC700] transition-colors font-medium flex items-center gap-1.5">
                                <span>Portal Tracer Study Nasional</span>
                            </a>
                        </li>
                    </ul>
                    
                    <Link
                        v-if="!$page.props.auth?.user"
                        href="/login"
                        class="inline-flex items-center justify-center w-full sm:w-auto px-7 py-3 rounded-full bg-[#FFC700] hover:bg-[#FBBF24] text-slate-950 text-xs font-black transition-all shadow-md hover:shadow-lg active:scale-95 cursor-pointer"
                    >
                        <span>Masuk ke Akun Tracer</span>
                    </Link>
                </div>

            </div>

            <!-- Bottom Copyright -->
            <div class="border-t border-white/10 pt-6 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-white/70">
                <p>
                    &copy; 2018 - {{ currentYear }} Universitas Kristen Duta Wacana. All Rights Reserved.
                </p>
                <p>
                    Portal Tracer Study Resmi UKDW Yogyakarta.
                </p>
            </div>
        </div>

        <!-- Floating Yellow Scroll-To-Top Button -->
        <button
            v-show="showScrollTop"
            @click="scrollToTop"
            class="fixed bottom-6 right-6 z-50 w-11 h-11 rounded-full bg-[#FFC700] hover:bg-[#FBBF24] text-slate-950 shadow-2xl flex items-center justify-center transition-all hover:scale-105 active:scale-95 focus:outline-none cursor-pointer"
            title="Kembali ke Atas"
        >
            <svg class="w-5 h-5 font-black" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 15l7-7 7 7"/></svg>
        </button>
    </footer>
</template>
