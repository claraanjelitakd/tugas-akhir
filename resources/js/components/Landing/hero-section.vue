<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({})
    },
    // Background class yang dapat diatur dari Beranda.vue
    bgClass: {
        type: String,
        default: 'bg-slate-950'
    }
});

const searchQuery = ref('');

const emit = defineEmits(['searchAlumni']);

const handleSearch = () => {
    // Scroll ke bagian direktori & peta alumni dan trigger pencarian
    const targetSection = document.getElementById('peta-alumni');
    if (targetSection) {
        targetSection.scrollIntoView({ behavior: 'smooth' });
    }
    emit('searchAlumni', searchQuery.value);
};
</script>

<template>
    <section :class="[bgClass, 'relative pt-24 pb-28 lg:pt-32 lg:pb-40 overflow-visible transition-colors duration-300']">
        
        <!--
          ========================================================================
          VIDEO BACKGROUND AUTOPLAY (KAMPUS UKDW)
          ========================================================================
          Sumber Video: https://youtu.be/CPJSkXnOj1A (ID: CPJSkXnOj1A)
          
          Parameter Penjelasan YouTube Embed API:
          - autoplay=1        : Video diputar otomatis segera setelah DOM siap.
          - mute=1            : Wajib senyap (muted = 100%) agar diizinkan autoplay oleh
                                kebijakan Browser modern (Chrome, Edge, Safari, Firefox).
          - loop=1            : Memutar video terus menerus tanpa henti.
          - playlist=CPJSkXnOj1A : Wajib disertakan bersama loop=1 agar loop bekerja pada YouTube Iframe API.
          - controls=0        : Menyembunyikan tombol play/pause, seekbar, volume, dan kontrol player.
          - showinfo=0        : Menyembunyikan judul video dan profil pengunggah.
          - rel=0             : Mencegah rekomendasi video acak dari luar kanal saat jeda.
          - disablekb=1       : Menonaktifkan shortcut keyboard agar tidak mengganggu navigasi pengguna.
          - modestbranding=1  : Meminimalkan watermark logo YouTube.
          - playsinline=1     : Memastikan video diputar di dalam background (inline) pada perangkat seluler.
        -->
        <div class="absolute inset-0 z-0 overflow-hidden pointer-events-none select-none">
            <!-- 
              Iframe YouTube Video UKDW:
              - cc_load_policy=0 & cc_lang_pref=none : Mematikan closed caption.
              - iv_load_policy=3 : Mematikan anotasi.
              - Menggeser posisi iframe (-translate-y-[36%] scale-115) sehingga baris subtitle YouTube 
                secara fisik terpotong ke bawah di luar batas viewport overflow-hidden.
            -->
            <iframe
                class="absolute top-1/2 left-1/2 w-[180vw] h-[180vh] min-w-full min-h-full -translate-x-1/2 -translate-y-[36%] scale-115 object-cover"
                src="https://www.youtube-nocookie.com/embed/CPJSkXnOj1A?autoplay=1&mute=1&loop=1&playlist=CPJSkXnOj1A&controls=0&showinfo=0&rel=0&disablekb=1&modestbranding=1&playsinline=1&enablejsapi=1&cc_load_policy=0&cc_lang_pref=none&hl=id"
                title="Video Profil Resmi Universitas Kristen Duta Wacana"
                frameborder="0"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen
            ></iframe>

            <!-- 
              Overlay Netral Bening (Tanpa Lapisan Hijau Pekat):
              Video kampus UKDW terlihat jernih dan nyata. Gradasi lembut di sisi kiri memastikan teks tetap terbaca dengan kontras tajam.
            -->
            <div class="absolute inset-0 bg-black/20"></div>
            <div class="absolute inset-0 bg-gradient-to-r from-black/85 via-black/30 to-transparent"></div>
            <!-- Masking gradasi gelap bawah untuk menjamin caption tertutup sepenuhnya -->
            <div class="absolute bottom-0 inset-x-0 h-44 bg-gradient-to-t from-slate-950 via-slate-950/80 to-transparent"></div>
        </div>

        <!-- Konten Hero Beranda (Persis Struktur & Tipografi Portal UB) -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            
            <div class="max-w-3xl">
                <!-- Judul Utama (Putih & Kuning Cerah Sesuai Portal UB) -->
                <h1 class="hero-title text-white text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight uppercase leading-tight drop-shadow-lg">
                    PORTAL TRACER STUDY
                </h1>
                
                <h2 class="hero-subtitle text-[#FFC700] text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight uppercase leading-tight mt-1 mb-4 drop-shadow-xl">
                    UNIVERSITAS KRISTEN DUTA WACANA
                </h2>
                
                <p class="text-slate-200 text-base sm:text-lg font-medium leading-relaxed max-w-2xl mb-8 drop-shadow-md">
                    Website ini berisi informasi pelaksanaan Tracer Study di Universitas Kristen Duta Wacana
                </p>

                <!-- Search Bar Persis Portal UB: Input Putih + Tombol Kuning -->
                <form @submit.prevent="handleSearch" class="flex w-full max-w-xl rounded-full overflow-hidden shadow-2xl border-2 border-white/30 focus-within:border-[#FFC700] transition-all">
                    <input 
                        type="text" 
                        v-model="searchQuery"
                        placeholder="Cari apapun disini..." 
                        class="w-full px-5 py-3.5 text-slate-900 bg-white font-medium text-sm focus:outline-none placeholder-slate-400"
                    />
                    <button 
                        type="submit" 
                        class="px-6 bg-[#FFC700] hover:bg-[#FBBF24] text-slate-950 transition-colors flex items-center justify-center font-black shrink-0 cursor-pointer"
                        title="Cari"
                    >
                        <svg class="w-5 h-5 font-bold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </button>
                </form>
            </div>

        </div>

        <!-- 
          3 Overlapping Cards di Bawah Hero — Glossy Frosted Glass Style (Sesuai Gambar 2):
          - Tracer Study
          - Survey Pengguna Lulusan
          - Laporan Tracer Study
          Tanpa warna gelap, menggunakan efek frosted glass apple modern (backdrop blur, white highlight, glow)
        -->
        <div class="relative lg:-bottom-12 z-20 px-4 sm:px-6 lg:px-8 mt-12 lg:mt-0">
            <div class="max-w-7xl mx-auto w-full">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-stretch">
                    
                    <!-- Card 1: Tracer Study (Sesuai Gambar 3) -->
                    <div class="rounded-2xl sm:rounded-3xl p-6 sm:p-7 bg-[#222724]/80 hover:bg-[#222724]/95 backdrop-blur-2xl border border-white/20 shadow-2xl text-white hover:-translate-y-2 transition-all duration-300 flex flex-col justify-between group min-h-[220px]">
                        <div>
                            <div class="flex items-center justify-between gap-3 mb-3">
                                <h3 class="text-xl sm:text-2xl font-black text-[#FFC700] tracking-tight drop-shadow-md">
                                    Tracer Study
                                </h3>
                                <div class="w-8 h-8 rounded-xl bg-white/10 backdrop-blur-md border border-white/20 flex items-center justify-center text-[#FFC700] shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
                                </div>
                            </div>
                            <p class="text-white/90 text-sm sm:text-base font-medium leading-relaxed mb-6">
                                Ditujukan kepada seluruh alumni UKDW dalam rentang waktu 1-2 tahun setelah lulus guna mengevaluasi proses pendidikan.
                            </p>
                        </div>
                        <div class="pt-2">
                            <Link 
                                v-if="!$page.props.auth?.user"
                                href="/login" 
                                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-[#FFC700] hover:bg-[#FBBF24] text-slate-950 font-black text-xs sm:text-sm shadow-md hover:shadow-lg transition-all hover:scale-105 active:scale-95"
                            >
                                <span>Selengkapnya</span>
                                <svg class="w-4 h-4 font-black" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </Link>
                            <Link 
                                v-else
                                href="/alumni/dashboard" 
                                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-[#FFC700] hover:bg-[#FBBF24] text-slate-950 font-black text-xs sm:text-sm shadow-md hover:shadow-lg transition-all hover:scale-105 active:scale-95"
                            >
                                <span>Buka Kuesioner</span>
                                <svg class="w-4 h-4 font-black" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </Link>
                        </div>
                    </div>

                    <!-- Card 2: Survey Pengguna Lulusan (Sesuai Gambar 3) -->
                    <div class="rounded-2xl sm:rounded-3xl p-6 sm:p-7 bg-[#222724]/80 hover:bg-[#222724]/95 backdrop-blur-2xl border border-white/20 shadow-2xl text-white hover:-translate-y-2 transition-all duration-300 flex flex-col justify-between group min-h-[220px]">
                        <div>
                            <div class="flex items-center justify-between gap-3 mb-3">
                                <h3 class="text-xl sm:text-2xl font-black text-white tracking-tight drop-shadow-md group-hover:text-[#FFC700] transition-colors">
                                    Survey Pengguna Lulusan
                                </h3>
                                <div class="w-8 h-8 rounded-xl bg-white/10 backdrop-blur-md border border-white/20 flex items-center justify-center text-[#FFC700] shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                </div>
                            </div>
                            <p class="text-white/90 text-sm sm:text-base font-medium leading-relaxed mb-6">
                                Ditujukan kepada seluruh pengguna lulusan UKDW (perusahaan/instansi) dalam mengukur kualitas lulusan dari sudut pandang pengguna.
                            </p>
                        </div>
                        <div class="pt-2">
                            <a 
                                href="#peta-alumni" 
                                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-[#FFC700] hover:bg-[#FBBF24] text-slate-950 font-black text-xs sm:text-sm shadow-md hover:shadow-lg transition-all hover:scale-105 active:scale-95"
                            >
                                <span>Selengkapnya</span>
                                <svg class="w-4 h-4 font-black" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </a>
                        </div>
                    </div>

                    <!-- Card 3: Laporan Tracer Study (Sesuai Gambar 3) -->
                    <div class="rounded-2xl sm:rounded-3xl p-6 sm:p-7 bg-[#222724]/80 hover:bg-[#222724]/95 backdrop-blur-2xl border border-white/20 shadow-2xl text-white hover:-translate-y-2 transition-all duration-300 flex flex-col justify-between group min-h-[220px]">
                        <div>
                            <div class="flex items-center justify-between gap-3 mb-3">
                                <h3 class="text-xl sm:text-2xl font-black text-white tracking-tight drop-shadow-md group-hover:text-[#FFC700] transition-colors">
                                    Laporan Tracer Study
                                </h3>
                                <div class="w-8 h-8 rounded-xl bg-white/10 backdrop-blur-md border border-white/20 flex items-center justify-center text-[#FFC700] shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                </div>
                            </div>
                            <p class="text-white/90 text-sm sm:text-base font-medium leading-relaxed mb-6">
                                Laporan hasil Tracer Study dilaporkan setiap tahun dan disosialisasikan kepada seluruh pemangku kepentingan.
                            </p>
                        </div>
                        <div class="pt-2">
                            <a 
                                href="#statistik" 
                                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-[#FFC700] hover:bg-[#FBBF24] text-slate-950 font-black text-xs sm:text-sm shadow-md hover:shadow-lg transition-all hover:scale-105 active:scale-95"
                            >
                                <span>Selengkapnya</span>
                                <svg class="w-4 h-4 font-black" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </a>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </section>
</template>
