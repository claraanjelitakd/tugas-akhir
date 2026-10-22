<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    message: String,
});

</script>

<template>
    <Head title="Akses Formulir Evaluasi - UKDW" />

    <div class="min-h-screen bg-slate-50 flex flex-col font-sans text-slate-900 antialiased">
        <!-- TOPBAR FULL WIDTH: IDENTITAS RESMI UNIVERSITAS -->
        <header class="bg-[#0D542B] text-white border-b-4 border-[#FDC700] shadow-sm w-full sticky top-0 z-30">
            <div class="w-full px-4 sm:px-6 lg:px-10 py-3.5 flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-white p-1 flex items-center justify-center shrink-0 shadow-xs border border-white/20">
                        <img 
                            src="/images/logo-ukdw.png" 
                            alt="Logo UKDW" 
                            class="w-full h-full object-contain"
                            onerror="this.src='/images/ukdw.png'"
                        />
                    </div>
                    <div>
                        <h1 class="text-sm sm:text-base font-extrabold tracking-tight text-white uppercase leading-tight">
                            Universitas Kristen Duta Wacana
                        </h1>
                        <p class="text-[11px] sm:text-xs text-emerald-100 font-medium">
                            Pusat Karir & Alumni &bull; Evaluasi Kinerja & Kepuasan Pengguna Lulusan
                        </p>
                    </div>
                </div>

                <div class="hidden sm:flex items-center gap-2 text-xs font-semibold text-emerald-100 bg-white/10 px-3 py-1.5 rounded-md border border-white/15">
                    <span class="w-2 h-2 rounded-full bg-[#FDC700]"></span>
                    <span>Portal Resmi Evaluasi Atasan</span>
                </div>
            </div>
        </header>

        <!-- MAIN CONTAINER -->
        <main class="flex-1 flex items-center justify-center p-4 sm:p-6 w-full">
            <div class="max-w-lg w-full bg-white rounded-xl border border-slate-200 p-6 sm:p-8 text-center shadow-2xs space-y-5">
                <div class="w-14 h-14 bg-amber-50 text-[#0D542B] border border-amber-200 rounded-full flex items-center justify-center mx-auto text-xl font-bold shadow-2xs">
                    <svg class="w-7 h-7 text-[#0D542B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>

                <div>
                    <h2 class="text-base sm:text-lg font-bold text-slate-900">Tautan Evaluasi Belum Teridentifikasi</h2>
                    <p class="text-xs sm:text-sm text-slate-600 mt-2 leading-relaxed">
                        {{ message || 'Tautan formulir evaluasi atasan yang Anda buka tidak memiliki token valid atau telah kadaluarsa.' }}
                    </p>
                </div>

                <div class="pt-2">
                    <Link 
                        href="/" 
                        class="inline-flex items-center justify-center px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-lg transition-colors border border-slate-200"
                    >
                        Kembali ke Halaman Utama Tracer Study
                    </Link>
                </div>
            </div>
        </main>

        <!-- FOOTER FULL WIDTH -->
        <footer class="bg-white border-t border-slate-200 py-4 text-xs text-slate-500 w-full">
            <div class="w-full px-4 sm:px-6 lg:px-10 flex flex-col sm:flex-row items-center justify-between gap-2 text-center sm:text-left">
                <span class="text-[11px] text-slate-400">Pusat Karir & Alumni Universitas Kristen Duta Wacana</span>
                <span class="text-[11px] text-slate-400">&copy; {{ new Date().getFullYear() }} UKDW</span>
            </div>
        </footer>
    </div>
</template>
