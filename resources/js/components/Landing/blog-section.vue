<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    // Background class yang dapat diatur dari Beranda.vue
    bgClass: {
        type: String,
        default: 'bg-white'
    }
});

// Deteksi apakah background bertipe terang (putih, kuning, dsb.)
const isLightBg = computed(() => {
    const bg = (props.bgClass || '').toLowerCase();
    return bg.includes('white') || bg.includes('ffc700') || bg.includes('yellow') || bg.includes('amber') || bg.includes('slate-50') || bg.includes('slate-100') || bg.includes('gray-50') || bg.includes('gray-100');
});

const blogs = [
    {
        id: 1,
        judul: 'Sosialisasi Tracer Study Nasional Kemendikbudristek 2026',
        kategori: 'Regulasi & Mutu',
        tanggal: '24 Sep 2026',
        penulis: 'Biro 3 UKDW',
        ringkasan: 'Langkah strategis pelaporan data lulusan untuk pemenuhan Indikator Kinerja Utama (IKU-1) perguruan tinggi se-Indonesia.',
        tag: 'IKU-1'
    },
    {
        id: 2,
        judul: 'Pembaruan Kurikulum Berbasis Kebutuhan Industri Era AI',
        kategori: 'Akademik',
        tanggal: '18 Sep 2026',
        penulis: 'LPPM UKDW',
        ringkasan: 'Hasil survei pengguna lulusan menunjukkan tingginya permintaan terhadap keterampilan problem-solving dan rekayasa cerdas.',
        tag: 'Kurikulum'
    },
    {
        id: 3,
        judul: 'Temu Kangen & Bursa Kerja Alumni Lintas Angkatan',
        kategori: 'Kegiatan Alumni',
        tanggal: '10 Sep 2026',
        penulis: 'Ikatan Alumni',
        ringkasan: 'Mempererat persaudaraan alumni Duta Wacana sekaligus membuka peluang karier baru dan mentoring untuk lulusan muda.',
        tag: 'Networking'
    },
    {
        id: 4,
        judul: 'Apresiasi Bagi Responden Kuesioner Tracer Study Cepat',
        kategori: 'Informasi',
        tanggal: '02 Sep 2026',
        penulis: 'Humas Kampus',
        ringkasan: 'Program reward dan merchandise eksklusif bagi alumni yang telah menyelesaikan pengisian kuesioner pada periode pembukaan.',
        tag: 'Merchandise'
    }
];

const blogContainer = ref(null);

const scrollLeft = () => {
    if (blogContainer.value) {
        blogContainer.value.scrollBy({ left: -300, behavior: 'smooth' });
    }
};

const scrollRight = () => {
    if (blogContainer.value) {
        blogContainer.value.scrollBy({ left: 300, behavior: 'smooth' });
    }
};
</script>

<template>
    <section id="blog" :class="[bgClass, 'py-20 lg:py-24 relative overflow-hidden transition-colors duration-300', isLightBg ? 'text-slate-950 border-b border-slate-200' : 'text-white border-b border-white/10']">
        <!-- Glow Accents -->
        <div v-if="!isLightBg" class="absolute -top-32 -left-32 w-96 h-96 bg-[#03542B]/80 rounded-full blur-3xl pointer-events-none"></div>
        <div v-if="!isLightBg" class="absolute -bottom-32 -right-32 w-96 h-96 bg-[#FFC700]/10 rounded-full blur-3xl pointer-events-none"></div>
        <div v-else class="absolute -top-32 -left-32 w-96 h-96 bg-emerald-100/50 rounded-full blur-3xl pointer-events-none"></div>
        <div v-if="isLightBg" class="absolute -bottom-32 -right-32 w-96 h-96 bg-amber-100/60 rounded-full blur-3xl pointer-events-none"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 w-full">
            
            <!-- Section Header -->
            <div class="flex items-end justify-between mb-8">
                <div>
                    <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-[#004D25] text-[#FFC700] font-black text-xs uppercase tracking-wider mb-2 shadow-md">
                        Warta Kampus
                    </div>
                    <h2 :class="isLightBg ? 'text-slate-950' : 'text-white'" class="text-2xl sm:text-3xl font-black tracking-tight mt-1">
                        Blog &amp; Agenda Terkini
                    </h2>
                    <p :class="isLightBg ? 'text-slate-600 font-medium' : 'text-white/80'" class="text-xs sm:text-sm mt-1">
                        Informasi seputar perkembangan almamater, panduan karier, dan agenda temu alumni.
                    </p>
                </div>

                <!-- Right Navigation Arrows Glossy Style -->
                <div class="hidden sm:flex items-center gap-2">
                    <button
                        @click="scrollLeft"
                        :class="isLightBg ? 'bg-slate-100/90 border-slate-300 text-slate-800 hover:bg-[#004D25] hover:text-[#FFC700]' : 'bg-white/10 border-white/20 text-white hover:bg-[#FFC700] hover:text-slate-950'"
                        class="w-10 h-10 rounded-full backdrop-blur-md border flex items-center justify-center transition-all shadow-md cursor-pointer active:scale-95"
                        title="Sebelumnya"
                    >
                        <svg class="w-4 h-4 font-bold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <button
                        @click="scrollRight"
                        :class="isLightBg ? 'bg-slate-100/90 border-slate-300 text-slate-800 hover:bg-[#004D25] hover:text-[#FFC700]' : 'bg-white/10 border-white/20 text-white hover:bg-[#FFC700] hover:text-slate-950'"
                        class="w-10 h-10 rounded-full backdrop-blur-md border flex items-center justify-center transition-all shadow-md cursor-pointer active:scale-95"
                        title="Berikutnya"
                    >
                        <svg class="w-4 h-4 font-bold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>

            <!-- 4 Blog Cards Matching Glossy Squircle -->
            <div 
                ref="blogContainer"
                class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 overflow-x-auto pb-2"
            >
                <div 
                    v-for="post in blogs" 
                    :key="post.id"
                    :class="isLightBg ? 'bg-white border-slate-200/90 shadow-md hover:shadow-xl hover:border-[#004D25]/40 text-slate-900' : 'bg-white/10 border-white/20 shadow-2xl hover:shadow-2xl hover:border-[#FFC700]/70 hover:bg-white/15 text-white'"
                    class="backdrop-blur-2xl rounded-3xl border transition-all flex flex-col justify-between overflow-hidden group cursor-pointer"
                >
                    <!-- Visual Header Placeholder -->
                    <div class="p-3.5 pb-0">
                        <div 
                            :class="isLightBg ? 'bg-slate-100 border-slate-200/80 group-hover:bg-slate-200/70' : 'bg-black/40 border-white/15 group-hover:bg-black/50'"
                            class="h-32 w-full rounded-2xl border flex items-center justify-center relative overflow-hidden transition-colors"
                        >
                            <div class="w-11 h-11 rounded-2xl bg-[#004D25] text-[#FFC700] flex items-center justify-center font-black shadow-md">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
                            </div>
                            
                            <span 
                                :class="isLightBg ? 'bg-white/95 text-slate-700 border-slate-200 shadow-xs' : 'bg-black/60 text-white border-white/20 shadow-xs'"
                                class="absolute top-2.5 right-2.5 backdrop-blur-md text-[10px] font-bold px-2.5 py-0.5 rounded-full border"
                            >
                                {{ post.tanggal }}
                            </span>
                        </div>
                    </div>

                    <!-- Post Body -->
                    <div class="p-4 pt-3 flex-1 flex flex-col">
                        <span 
                            :class="isLightBg ? 'text-[#004D25]' : 'text-[#FFC700]'"
                            class="text-[10px] font-black uppercase tracking-wider block mb-1"
                        >
                            {{ post.kategori }}
                        </span>
                        
                        <h3 
                            :class="isLightBg ? 'text-slate-900 group-hover:text-[#004D25]' : 'text-white group-hover:text-[#FFC700]'"
                            class="text-sm font-bold transition-colors line-clamp-2 leading-snug mb-2"
                        >
                            {{ post.judul }}
                        </h3>

                        <p 
                            :class="isLightBg ? 'text-slate-600' : 'text-white/75'"
                            class="text-xs line-clamp-3 leading-relaxed flex-1"
                        >
                            {{ post.ringkasan }}
                        </p>
                    </div>

                    <!-- Post Footer -->
                    <div 
                        :class="isLightBg ? 'border-t border-slate-100 bg-slate-50/80' : 'border-t border-white/15 bg-black/20'"
                        class="p-4 pt-3 flex items-center justify-between"
                    >
                        <span 
                            :class="isLightBg ? 'text-slate-500' : 'text-white/60'"
                            class="text-[11px] font-medium"
                        >
                            {{ post.penulis }}
                        </span>

                        <span 
                            :class="isLightBg ? 'text-[#004D25]' : 'text-[#FFC700]'"
                            class="inline-flex items-center gap-1 text-xs font-black group-hover:translate-x-0.5 transition-transform"
                        >
                            <span>Baca</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                        </span>
                    </div>
                </div>
            </div>

        </div>
    </section>
</template>
