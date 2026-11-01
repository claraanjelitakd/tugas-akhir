<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    message: String,
    title: String,
    status: {
        type: String,
        default: 'not_found', // 'submitted' atau 'not_found'
    },
});

const isSubmitted = computed(() => props.status === 'submitted');
</script>

<template>
    <Head :title="(isSubmitted ? 'Evaluasi Berhasil Diterima' : 'Akses Formulir Evaluasi') + ' - UKDW'" />

    <!-- LAYOUT RESPONSIF FULL PAGE CLEAN TANPA BORDER DAN SHADOW DENGAN PALET HIJAU TUA & KUNING UKDW -->
    <div class="min-h-screen w-full bg-white flex flex-col justify-between font-sans text-slate-900 antialiased selection:bg-[#0D542B] selection:text-white">
        <!-- HEADER INSTITUSI DENGAN LOGO RESMI DAN AKSEN KUNING EMAS UKDW -->
        <header class="w-full pt-8 sm:pt-12 px-6 flex flex-col items-center text-center">
            <div class="w-16 h-16 sm:w-20 sm:h-20 flex items-center justify-center">
                <img
                    src="/uploads/logo/logo-ukdw.png"
                    alt="Logo UKDW"
                    class="w-full h-full object-contain"
                />
            </div>
            <h1 class="mt-4 text-base sm:text-lg font-black tracking-tight text-[#0D542B] uppercase leading-snug">
                Universitas Kristen Duta Wacana
            </h1>
            <p class="text-xs sm:text-sm font-semibold text-[#0D542B]">
                Pusat Alumni &amp; Karir &bull; Evaluasi Pengguna Lulusan
            </p>
            <!-- AKSEN GARIS KUNING UKDW -->
            <div class="mt-3 w-12 h-1 bg-[#FDC700] rounded-full"></div>
        </header>

        <!-- KONTEN TENGAH BERSIH DAN FLAT ALA ILUSTRASI THANK YOU -->
        <main class="flex-1 flex items-center justify-center px-6 py-8 w-full max-w-xl mx-auto text-center">
            <div class="w-full space-y-6">
                <!-- ILUSTRASI SURAT / PESAN TERKIRIM BERKUALITAS DARI ASSETS RESMI -->
                <div class="w-32 h-32 sm:w-40 sm:h-40 mx-auto flex items-center justify-center">
                    <img
                        v-if="isSubmitted"
                        src="/uploads/pigo/pigo-update-data.png"
                        alt="Evaluasi Selesai"
                        class="w-full h-full object-contain"
                    />
                    <img
                        v-else
                        src="/uploads/pigo/loading-pigo.png"
                        alt="Informasi Evaluasi"
                        class="w-full h-full object-contain"
                    />
                </div>

                <!-- PESAN UCAPAN TERIMA KASIH UTAMA -->
                <div class="space-y-3">
                    <h2 class="text-2xl sm:text-3xl font-black text-[#0D542B] tracking-tight">
                        {{ isSubmitted ? 'Terima Kasih!' : 'Tautan Evaluasi Belum Teridentifikasi' }}
                    </h2>
                    <p class="text-sm sm:text-base text-slate-700 leading-relaxed font-medium">
                        {{ message || 'Tautan formulir evaluasi atasan yang Anda buka tidak memiliki token valid atau telah kadaluarsa.' }}
                    </p>
                    <p v-if="isSubmitted" class="text-xs sm:text-sm text-slate-500 font-normal">
                        Salinan konfirmasi respon evaluasi telah kami kirimkan ke alamat email Anda.
                    </p>
                </div>

                <!-- TOMBOL FLAT FORMAL TANPA ARROW, HANYA HIJAU TUA DENGAN HOVER KUNING -->
                <div class="pt-4 flex justify-center">
                    <a
                        v-if="isSubmitted"
                        href="https://www.ukdw.ac.id"
                        target="_blank"
                        class="px-8 py-3 bg-[#0D542B] hover:bg-[#0a4222] text-[#FDC700] hover:text-white font-extrabold text-sm rounded-lg transition-colors cursor-pointer"
                    >
                        Kunjungi Situs Resmi UKDW
                    </a>
                </div>
            </div>
        </main>

        <!-- FOOTER FORMAL -->
        <footer class="py-6 text-center text-xs text-slate-500">
            <p class="font-medium text-slate-500">
                Pusat Alumni &amp; Karir Universitas Kristen Duta Wacana
            </p>
        </footer>
    </div>
</template>
