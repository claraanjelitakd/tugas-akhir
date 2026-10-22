<script setup>
import { computed } from 'vue';

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
</script>

<template>
    <section id="tentang" :class="[bgClass, 'py-20 lg:py-24 relative overflow-hidden transition-colors duration-300', isLightBg ? 'text-slate-950 border-b border-slate-200' : 'text-white border-b border-white/10']">
        <!-- Glow Accents -->
        <div v-if="!isLightBg" class="absolute -top-32 -left-32 w-96 h-96 bg-[#03542B]/80 rounded-full blur-3xl pointer-events-none"></div>
        <div v-if="!isLightBg" class="absolute -bottom-32 -right-32 w-96 h-96 bg-[#FFC700]/15 rounded-full blur-3xl pointer-events-none"></div>
        <div v-else class="absolute -top-32 -left-32 w-96 h-96 bg-emerald-100/50 rounded-full blur-3xl pointer-events-none"></div>
        <div v-if="isLightBg" class="absolute -bottom-32 -right-32 w-96 h-96 bg-amber-100/60 rounded-full blur-3xl pointer-events-none"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 w-full">
            
            <!-- Section Header -->
            <div class="text-center max-w-3xl mx-auto mb-14">
                <span class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full bg-[#004D25] text-[#FFC700] font-black text-xs uppercase tracking-wider mb-3 shadow-md">
                    Tracer Study Lulusan
                </span>
                <h2 :class="isLightBg ? 'text-[#004D25]' : 'text-white'" class="text-2xl sm:text-3xl lg:text-4xl font-black tracking-tight uppercase leading-tight">
                    UNIVERSITAS KRISTEN DUTA WACANA
                </h2>
                <p :class="isLightBg ? 'text-[#004D25]/90 font-medium' : 'text-white/80 font-medium'" class="text-sm sm:text-base mt-2">
                    Evaluasi mutu pembelajaran dan integrasi rekam jejak lulusan di dunia profesional
                </p>
                <div :class="isLightBg ? 'bg-[#004D25]' : 'bg-[#FFC700]'" class="w-14 h-1 mx-auto rounded-full mt-4"></div>
            </div>

            <!-- Grid: Sambutan WR III & Informasi Tracer Study -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-stretch">
                
                <!-- Kolom Kiri: Sambutan WR III (Glossy Frosted Card) -->
                <div :class="isLightBg ? 'bg-white border-slate-200/90 shadow-xl text-[#00381A]' : 'bg-white/10 border-white/20 shadow-2xl text-white'" class="lg:col-span-7 backdrop-blur-2xl rounded-3xl p-7 sm:p-9 border flex flex-col justify-between">
                    <div>
                        <!-- Badge Salam -->
                        <div class="flex items-center gap-2 mb-4">
                            <span :class="isLightBg ? 'bg-amber-100 text-amber-900 border border-amber-300/80 font-black' : 'bg-[#FFC700] text-slate-950 font-black'" class="px-3.5 py-1 rounded-full text-xs uppercase tracking-wider shadow-sm">
                                Salam Sejahtera
                            </span>
                        </div>

                        <h3 :class="isLightBg ? 'text-[#004D25]' : 'text-white'" class="text-xl sm:text-2xl font-black mb-4 tracking-tight leading-snug">
                            Salam SORBUM DUTA WACANA
                        </h3>

                        <div :class="isLightBg ? 'text-[#00381A]' : 'text-white/85'" class="space-y-4 text-sm sm:text-base leading-relaxed font-normal">
                            <p>
                                Salam sehat dan semangat bagi semua alumni Duta Wacana dalam melakukan aktivitas &amp; karya di berbagai bidang professional kerja masing-masing.
                            </p>
                            <p>
                                Kita semua patut bersyukur kepada Tuhan Yang Maha Esa, bahwa UKDW senantiasa mampu menjaga kualitas dan produktifitas layanan Pendidikan Tinggi, serta menghidupi nilai-nilai ke-DUTAWACANA-an untuk melangkah menjadi kampus yang melayani dunia (<em :class="isLightBg ? 'text-[#004D25] font-black' : 'text-[#FFC700] font-bold'" class="not-italic">service to the world</em>) dan melakukan yang terbaik (<em :class="isLightBg ? 'text-[#004D25] font-black' : 'text-[#FFC700] font-bold'" class="not-italic">striving for excellence</em>) karena dukungan semua alumni.
                            </p>
                        </div>
                    </div>

                    <!-- Tanda Pengenal Pejabat WR III -->
                    <div :class="isLightBg ? 'bg-slate-50 border-slate-200/90' : 'bg-black/30 border-white/15'" class="mt-8 pt-6 border-t flex items-center gap-4 backdrop-blur-md p-4 rounded-2xl border">
                        <div class="w-12 h-12 rounded-2xl bg-[#004D25] text-[#FFC700] flex items-center justify-center font-black text-base shrink-0 shadow-md">
                            WR
                        </div>
                        <div>
                            <h4 :class="isLightBg ? 'text-[#004D25]' : 'text-white'" class="text-base font-extrabold leading-tight">
                                Dr. Parmonangan Manurung, S.T., M.T., IAI.
                            </h4>
                            <p :class="isLightBg ? 'text-[#004D25]/90' : 'text-[#FFC700]'" class="text-xs font-bold mt-0.5">
                                Wakil Rektor Bidang Kemahasiswaan, Alumni, Informasi &amp; Inovasi
                            </p>
                            <p :class="isLightBg ? 'text-[#004D25]/75' : 'text-white/70'" class="text-[11px] font-medium">
                                Universitas Kristen Duta Wacana
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Kolom Kanan: Tujuan & Manfaat Tracer Study (Squircle Cards) -->
                <div class="lg:col-span-5 flex flex-col gap-5">
                    
                    <!-- Card 1: Tujuan Tracer Study -->
                    <div :class="isLightBg ? 'bg-white border-slate-200/90 shadow-xl text-[#00381A]' : 'bg-white/10 border-white/20 shadow-2xl text-white'" class="backdrop-blur-2xl rounded-3xl p-6 sm:p-7 border flex-1 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center gap-2 mb-3">
                                <span :class="isLightBg ? 'bg-emerald-50 text-[#004D25] border border-emerald-200/70 font-bold' : 'bg-white/15 text-[#FFC700] border border-[#FFC700]/30 font-bold'" class="px-3 py-0.5 rounded-full text-[11px]">
                                    Informasi &amp; Tujuan
                                </span>
                            </div>
                            <h4 :class="isLightBg ? 'text-[#004D25]' : 'text-white'" class="text-sm sm:text-base font-black mb-3 leading-snug">
                                Tracer Study bertujuan untuk memperoleh umpan balik guna:
                            </h4>
                            <ul :class="isLightBg ? 'text-[#00381A]' : 'text-white/85'" class="space-y-2.5 text-xs font-medium">
                                <li class="flex items-start gap-2.5">
                                    <span class="w-5 h-5 rounded-full bg-[#004D25] text-[#FFC700] flex items-center justify-center font-bold text-xs shrink-0 mt-0.5 shadow-sm">1</span>
                                    <span>Mengetahui aktivitas dan status alumni setelah menyelesaikan studi di kampus.</span>
                                </li>
                                <li class="flex items-start gap-2.5">
                                    <span class="w-5 h-5 rounded-full bg-[#004D25] text-[#FFC700] flex items-center justify-center font-bold text-xs shrink-0 mt-0.5 shadow-sm">2</span>
                                    <span>Mengukur kompetensi lulusan serta kecocokan dengan kebutuhan pasar kerja.</span>
                                </li>
                                <li class="flex items-start gap-2.5">
                                    <span class="w-5 h-5 rounded-full bg-[#004D25] text-[#FFC700] flex items-center justify-center font-bold text-xs shrink-0 mt-0.5 shadow-sm">3</span>
                                    <span>Memetakan penyerapan dunia kerja dan jejaring profesional alumni nasional.</span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Card 2: Manfaat bagi UKDW -->
                    <div :class="isLightBg ? 'bg-white border-slate-200/90 shadow-xl text-[#00381A]' : 'bg-white/10 border-white/20 shadow-2xl text-white'" class="backdrop-blur-2xl rounded-3xl p-6 sm:p-7 border flex-1 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center gap-2 mb-3">
                                <span :class="isLightBg ? 'bg-amber-100 text-amber-900 border border-amber-300/80 font-black' : 'bg-[#FFC700] text-slate-950 font-black'" class="px-3 py-0.5 rounded-full text-[11px] shadow-sm">
                                    Dampak bagi Almamater
                                </span>
                            </div>
                            <h4 :class="isLightBg ? 'text-[#004D25]' : 'text-white'" class="text-sm sm:text-base font-black mb-3 leading-snug">
                                Hasil Tracer Study membantu institusi untuk:
                            </h4>
                            <ul :class="isLightBg ? 'text-[#00381A]' : 'text-white/85'" class="space-y-2.5 text-xs font-medium">
                                <li class="flex items-start gap-2.5">
                                    <div class="w-5 h-5 rounded-full bg-[#004D25] text-[#FFC700] flex items-center justify-center font-bold text-xs shrink-0 mt-0.5 shadow-sm">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    </div>
                                    <span>Menyiapkan dan menyelaraskan kurikulum agar adaptif dengan dinamika dunia kerja modern.</span>
                                </li>
                                <li class="flex items-start gap-2.5">
                                    <div class="w-5 h-5 rounded-full bg-[#004D25] text-[#FFC700] flex items-center justify-center font-bold text-xs shrink-0 mt-0.5 shadow-sm">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    </div>
                                    <span>Mengukur capaian pembelajaran serta memenuhi standar akreditasi nasional dan internasional.</span>
                                </li>
                            </ul>
                        </div>
                    </div>

                </div>

            </div>

        </div>
    </section>
</template>
