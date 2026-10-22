<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    // Background class yang dapat diatur dari Beranda.vue
    bgClass: {
        type: String,
        default: 'bg-[#FFC700]'
    }
});

// Deteksi apakah background bertipe terang (kuning, putih, dsb.)
const isLightBg = computed(() => {
    const bg = (props.bgClass || '').toLowerCase();
    return bg.includes('white') || bg.includes('ffc700') || bg.includes('yellow') || bg.includes('amber') || bg.includes('slate-50') || bg.includes('slate-100') || bg.includes('gray-50') || bg.includes('gray-100');
});

const berkasList = [
    {
        id: 1,
        title: 'Surat Edaran Tracer Study (WR III)',
        desc: 'Surat keputusan resmi dari Wakil Rektor III mengenai pelaksanaan kegiatan Tracer Study UKDW.',
        format: 'PDF',
        url: 'https://tracerstudy.ukdw.ac.id/alumni_files/surat_edaran_tracer_study.pdf'
    },
    {
        id: 2,
        title: 'Formulir Kuesioner Tracer Study',
        desc: 'Berkas formulir kuesioner cetak / panduan isian data riwayat karier alumni UKDW.',
        format: 'PDF',
        url: 'https://tracerstudy.ukdw.ac.id/alumni_files/form_tracer_study_2018.pdf'
    },
    {
        id: 3,
        title: 'Surat Evaluasi Kepuasan Pengguna',
        desc: 'Dokumen pengantar resmi untuk survei kepuasan atasan/pengguna lulusan UKDW.',
        format: 'PDF',
        url: 'https://tracerstudy.ukdw.ac.id/alumni_files/SURAT%20EVALUASI.pdf'
    },
    {
        id: 4,
        title: 'Form Evaluasi Pengguna Lulusan UKDW',
        desc: 'Format instrumen penilaian kompetensi lulusan bagi mitra perusahaan atau instansi kerja.',
        format: 'DOCX',
        url: 'https://tracerstudy.ukdw.ac.id/alumni_files/form_evaluasi_pengguna_lulusan_ukdw.docx'
    }
];
</script>

<template>
    <section id="berkas" :class="[bgClass, 'py-20 lg:py-24 relative overflow-hidden transition-colors duration-300', isLightBg ? 'text-slate-950 border-b border-amber-300/70' : 'text-white border-b border-white/10']">
        <!-- Glow Accents -->
        <div v-if="!isLightBg" class="absolute -top-32 -right-32 w-96 h-96 bg-[#004D25]/80 rounded-full blur-3xl pointer-events-none"></div>
        <div v-if="!isLightBg" class="absolute -bottom-32 -left-32 w-96 h-96 bg-[#FFC700]/10 rounded-full blur-3xl pointer-events-none"></div>
        <div v-else class="absolute -top-32 -right-32 w-96 h-96 bg-white/40 rounded-full blur-3xl pointer-events-none"></div>
        <div v-if="isLightBg" class="absolute -bottom-32 -left-32 w-96 h-96 bg-amber-600/15 rounded-full blur-3xl pointer-events-none"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 w-full">
            
            <!-- Section Header -->
            <div class="text-center max-w-3xl mx-auto mb-14">
                <span 
                    :class="isLightBg ? 'bg-[#004D25] text-[#FFC700]' : 'bg-[#FFC700] text-slate-950'"
                    class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full font-black text-xs uppercase tracking-wider mb-2.5 shadow-md"
                >
                    Dokumen &amp; Berkas Resmi
                </span>
                <h2 :class="isLightBg ? 'text-slate-950' : 'text-white'" class="text-2xl sm:text-3xl lg:text-4xl font-black tracking-tight mt-1 mb-2">
                    UNDUH BERKAS &amp; DOKUMEN TRACER
                </h2>
                <p :class="isLightBg ? 'text-slate-900/85 font-medium' : 'text-white/80 font-medium'" class="text-xs sm:text-sm leading-relaxed">
                    Akses berkas surat edaran resmi pelaksanaan Tracer Study dan instrumen evaluasi kepuasan pengguna lulusan.
                </p>
                <div :class="isLightBg ? 'bg-[#004D25]' : 'bg-[#FFC700]'" class="w-14 h-1 mx-auto rounded-full mt-4"></div>
            </div>

            <!-- Grid 4 Berkas Download (Squircle Cards) -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
                <div 
                    v-for="berkas in berkasList" 
                    :key="berkas.id"
                    :class="isLightBg ? 'bg-white/95 border-amber-200/90 hover:border-[#004D25]/50 hover:bg-white text-slate-900 shadow-md hover:shadow-xl' : 'bg-white/10 border-white/20 hover:border-[#FFC700]/70 hover:bg-white/15 text-white shadow-2xl hover:shadow-2xl'"
                    class="backdrop-blur-2xl rounded-3xl border p-5 transition-all flex flex-col justify-between group"
                >
                    <div>
                        <!-- Header Berkas: Badge Format -->
                        <div class="flex items-center justify-between mb-3">
                            <span 
                                class="text-[10px] font-black px-2.5 py-0.5 rounded-full"
                                :class="berkas.format === 'PDF' 
                                    ? (isLightBg ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-red-500/20 text-red-300 border border-red-400/30') 
                                    : (isLightBg ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-[#FFC700]/20 text-[#FFC700] border border-[#FFC700]/30')"
                            >
                                {{ berkas.format }}
                            </span>
                            <div 
                                :class="isLightBg ? 'bg-[#004D25] text-[#FFC700]' : 'bg-[#FFC700] text-slate-950'"
                                class="w-8 h-8 rounded-full flex items-center justify-center font-bold shadow-md"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                        </div>

                        <!-- Judul Berkas -->
                        <h3 
                            :class="isLightBg ? 'text-slate-950 group-hover:text-[#004D25]' : 'text-white group-hover:text-[#FFC700]'"
                            class="text-sm font-black transition-colors line-clamp-2 leading-snug mb-1.5"
                        >
                            {{ berkas.title }}
                        </h3>

                        <!-- Deskripsi Singkat -->
                        <p 
                            :class="isLightBg ? 'text-slate-600' : 'text-white/75'"
                            class="text-xs leading-relaxed line-clamp-3"
                        >
                            {{ berkas.desc }}
                        </p>
                    </div>

                    <!-- Tombol Download Pill -->
                    <div :class="isLightBg ? 'border-t border-slate-100' : 'border-t border-white/15'" class="mt-5 pt-3">
                        <a 
                            :href="berkas.url" 
                            target="_blank" 
                            :class="isLightBg ? 'bg-[#004D25] hover:bg-[#03542B] text-[#FFC700]' : 'bg-[#FFC700] hover:bg-[#FBBF24] text-slate-950'"
                            class="w-full py-2.5 px-3 rounded-full font-black text-xs transition-all flex items-center justify-center gap-1.5 shadow-md hover:shadow-lg active:scale-95 cursor-pointer"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            <span>Unduh Berkas</span>
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </section>
</template>
