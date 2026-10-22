<script setup>
import { defineProps } from 'vue';

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            total_alumni: 120,
            total_perusahaan: 32,
            total_prodi: 12,
            total_responden: 88,
            persentase_karier: 94
        })
    },
    // Background class yang dapat diatur dari Beranda.vue
    bgClass: {
        type: String,
        default: 'bg-[#004D25]'
    }
});
</script>

<template>
    <section id="statistik" :class="[bgClass, 'py-20 lg:py-24 text-white border-b border-white/10 relative overflow-hidden transition-colors duration-300']">
        <!-- Glossy Glow Accents -->
        <div class="absolute -top-32 -left-32 w-96 h-96 bg-[#03542B]/80 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-32 -right-32 w-96 h-96 bg-[#FFC700]/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 w-full">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                
                <!-- Left Column: Key Stats Numbers (Glossy Frosted Glass Container) -->
                <div class="lg:col-span-4 bg-white/10 backdrop-blur-2xl rounded-3xl p-7 sm:p-8 border border-white/20 shadow-2xl space-y-6 text-white">
                    
                    <!-- Metric 1 -->
                    <div>
                        <div class="text-3xl sm:text-4xl font-black text-[#FFC700] tracking-tight">
                            {{ stats.total_alumni || 120 }}<span class="text-white">+</span>
                        </div>
                        <h3 class="text-sm font-bold text-white mt-1">Alumni Terdata Resmi</h3>
                        <p class="text-xs text-white/75 mt-0.5 leading-relaxed">
                            Lulusan Duta Wacana terdaftar dalam sistem pangkalan data karier institusi.
                        </p>
                    </div>

                    <div class="border-t border-white/15"></div>

                    <!-- Metric 2 -->
                    <div>
                        <div class="text-3xl sm:text-4xl font-black text-[#FFC700] tracking-tight">
                            {{ stats.total_perusahaan || 32 }}<span class="text-white">+</span>
                        </div>
                        <h3 class="text-sm font-bold text-white mt-1">Mitra Perusahaan Terverifikasi</h3>
                        <p class="text-xs text-white/75 mt-0.5 leading-relaxed">
                            Instansi pemerintah, BUMN, perbankan, dan swasta multinasional penyerap lulusan.
                        </p>
                    </div>

                    <div class="border-t border-white/15"></div>

                    <!-- Metric 3 -->
                    <div>
                        <div class="text-3xl sm:text-4xl font-black text-[#FFC700] tracking-tight">
                            {{ stats.persentase_karier || 94 }}<span class="text-white">%</span>
                        </div>
                        <h3 class="text-sm font-bold text-white mt-1">Tingkat Serapan &amp; Studi Lanjut</h3>
                        <p class="text-xs text-white/75 mt-0.5 leading-relaxed">
                            Alumni yang telah aktif berkarier profesional, wirausaha, atau studi pascasarjana.
                        </p>
                    </div>

                </div>

                <!-- Middle Column: Visual Image Container with Pigo Mascot (Glossy Frosted Squircle) -->
                <div class="lg:col-span-4 flex justify-center">
                    <div class="relative w-full max-w-sm bg-white/10 backdrop-blur-2xl rounded-3xl p-6 border border-white/20 shadow-2xl flex flex-col items-center justify-center min-h-[380px] overflow-hidden group">
                        <!-- Background Glow Accent -->
                        <div class="absolute -top-10 -right-10 w-44 h-44 bg-[#03542B]/80 rounded-full blur-2xl"></div>
                        <div class="absolute -bottom-10 -left-10 w-40 h-40 bg-[#FFC700]/20 rounded-full blur-2xl"></div>

                        <!-- Mascot Image Pigo Update Data -->
                        <img 
                            src="/uploads/pigo/pigo-update-data.png" 
                            alt="Maskot Pigo Update Data" 
                            class="relative z-10 max-h-[260px] w-auto object-contain drop-shadow-2xl transition-transform duration-500 group-hover:scale-105"
                        />

                        <div class="relative z-10 mt-3 text-center">
                            <span class="inline-flex items-center px-4 py-1.5 rounded-full bg-black/40 backdrop-blur-md text-xs font-bold text-white border border-white/20 shadow-lg">
                                Evaluasi Kinerja Alumni Real-Time
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Narrative & Transformasi (Glossy Frosted Container) -->
                <div class="lg:col-span-4 bg-white/10 backdrop-blur-2xl rounded-3xl p-7 sm:p-8 border border-white/20 shadow-2xl text-white">
                    <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-[#FFC700] text-slate-950 font-black text-xs uppercase tracking-wider mb-2 shadow-md">
                        Dampak Nyata
                    </div>
                    <h2 class="text-xl sm:text-2xl font-black text-white tracking-tight mt-1 mb-3 leading-snug">
                        Transformasi Kurikulum &amp; Ekosistem Karier UKDW
                    </h2>
                    
                    <p class="text-white/80 text-xs sm:text-sm leading-relaxed mb-5">
                        Umpan balik dari penelusuran alumni adalah kompas strategis almamater. Setiap masukan kuesioner diterjemahkan menjadi pembaruan silabus perkuliahan, penguatan sertifikasi kompetensi, dan perluasan jejaring magang serta rekrutmen kerja.
                    </p>

                    <div class="space-y-3">
                        <div class="flex items-start gap-2.5">
                            <div class="w-5 h-5 rounded-full bg-[#FFC700] text-slate-950 flex items-center justify-center shrink-0 mt-0.5 font-black text-xs shadow-md">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <span class="text-xs font-semibold text-white/90">
                                Akreditasi Program Studi &amp; Institusi berstandar BAN-PT dan LAM.
                            </span>
                        </div>

                        <div class="flex items-start gap-2.5">
                            <div class="w-5 h-5 rounded-full bg-[#FFC700] text-slate-950 flex items-center justify-center shrink-0 mt-0.5 font-black text-xs shadow-md">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <span class="text-xs font-semibold text-white/90">
                                Penyelarasan kompetensi lulusan dengan standar industri masa depan.
                            </span>
                        </div>

                        <div class="flex items-start gap-2.5">
                            <div class="w-5 h-5 rounded-full bg-[#FFC700] text-slate-950 flex items-center justify-center shrink-0 mt-0.5 font-black text-xs shadow-md">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <span class="text-xs font-semibold text-white/90">
                                Penguatan sinergi alumni untuk bursa kerja dan inkubasi usaha.
                            </span>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </section>
</template>
