<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    // Background class yang dapat diatur dari Beranda.vue
    bgClass: {
        type: String,
        default: 'bg-gradient-to-b from-[#FFC700] via-[#FBBF24] to-[#F59E0B]'
    }
});
</script>

<template>
    <!-- Section Alur Partisipasi (Kuning UKDW Sesuai Arahan Pengguna) -->
    <section id="panduan" :class="[bgClass, 'py-20 lg:py-24 text-slate-950 border-y border-amber-300 relative overflow-hidden transition-colors duration-300']">
        <!-- Glossy Glow Accents (Warm & Subtle White Highlights) -->
        <div class="absolute -top-32 -right-32 w-96 h-96 bg-white/30 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-32 -left-32 w-96 h-96 bg-amber-600/15 rounded-full blur-3xl pointer-events-none"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 w-full">
            
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-start mb-12">
                
                <!-- Left Column (Title & Guide Intro) -->
                <div class="lg:col-span-5">
                    <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-[#004D25] text-[#FFC700] font-black text-xs uppercase tracking-wider mb-3 shadow-md">
                        Alur Partisipasi
                    </div>
                    <h2 class="text-2xl sm:text-3xl lg:text-4xl font-black text-slate-950 tracking-tight mt-1 mb-4 leading-tight">
                        Panduan Langkah Bagi Alumni Baru
                    </h2>
                    <p class="text-slate-900/90 text-sm sm:text-base leading-relaxed mb-6 font-medium">
                        Pengisian kuesioner Tracer Study dirancang cepat, ringkas, dan aman. Ikuti empat tahapan mudah di samping untuk mencatatkan rekam jejak Anda dalam basis data universitas.
                    </p>

                    <div class="p-4 rounded-2xl bg-white/85 backdrop-blur-xl border border-white/90 flex items-center gap-3.5 mb-8 shadow-md text-slate-900">
                        <div class="w-10 h-10 rounded-xl bg-[#004D25] text-[#FFC700] flex items-center justify-center shrink-0 font-black shadow-sm">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div>
                            <span class="text-xs font-black text-slate-950 block">Estimasi Waktu Pengisian</span>
                            <span class="text-xs text-slate-700 font-medium">Hanya membutuhkan sekitar 5 - 10 menit</span>
                        </div>
                    </div>
                </div>

                <!-- Right Column (Vertical Steps 1, 2, 3, 4 - High Contrast Cards) -->
                <div class="lg:col-span-7 space-y-3.5">
                    
                    <!-- Step 1 -->
                    <div class="p-5 rounded-2xl bg-white/90 hover:bg-white backdrop-blur-xl border border-amber-200/80 hover:border-[#004D25]/50 transition-all group shadow-md">
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 rounded-xl bg-[#004D25] text-[#FFC700] font-black text-xs flex items-center justify-center shrink-0 shadow-sm group-hover:scale-105 transition-transform">
                                01
                            </div>
                            <div>
                                <h3 class="text-sm sm:text-base font-black text-slate-950 group-hover:text-[#004D25] transition-colors">
                                    Step 1: Masuk Menggunakan Akun UKDW
                                </h3>
                                <p class="text-xs sm:text-sm text-slate-700 mt-1 leading-relaxed">
                                    Gunakan NIM alumni dan kata sandi yang telah terdaftar. Jika lupa kata sandi, hubungi admin program studi atau manfaatkan fitur pemulihan akun.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div class="p-5 rounded-2xl bg-white/90 hover:bg-white backdrop-blur-xl border border-amber-200/80 hover:border-[#004D25]/50 transition-all group shadow-md">
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 rounded-xl bg-[#004D25] text-[#FFC700] font-black text-xs flex items-center justify-center shrink-0 shadow-sm group-hover:scale-105 transition-transform">
                                02
                            </div>
                            <div>
                                <h3 class="text-sm sm:text-base font-black text-slate-950 group-hover:text-[#004D25] transition-colors">
                                    Step 2: Lengkapi Profil &amp; Riwayat Karier
                                </h3>
                                <p class="text-xs sm:text-sm text-slate-700 mt-1 leading-relaxed">
                                    Perbarui status aktivitas Anda saat ini: bekerja full-time, wiraswasta/bisnis mandiri, studi lanjut pascasarjana, atau sedang dalam pencarian kerja.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div class="p-5 rounded-2xl bg-white/90 hover:bg-white backdrop-blur-xl border border-amber-200/80 hover:border-[#004D25]/50 transition-all group shadow-md">
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 rounded-xl bg-[#004D25] text-[#FFC700] font-black text-xs flex items-center justify-center shrink-0 shadow-sm group-hover:scale-105 transition-transform">
                                03
                            </div>
                            <div>
                                <h3 class="text-sm sm:text-base font-black text-slate-950 group-hover:text-[#004D25] transition-colors">
                                    Step 3: Isi Kuesioner Universitas &amp; Program Studi
                                </h3>
                                <p class="text-xs sm:text-sm text-slate-700 mt-1 leading-relaxed">
                                    Jawab pertanyaan standar kementerian mengenai waktu tunggu kerja, kesesuaian bidang ilmu, serta masukan kurikulum khusus program studi Anda.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4 -->
                    <div class="p-5 rounded-2xl bg-white/90 hover:bg-white backdrop-blur-xl border border-amber-200/80 hover:border-[#004D25]/50 transition-all group shadow-md">
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 rounded-xl bg-[#004D25] text-[#FFC700] font-black text-xs flex items-center justify-center shrink-0 shadow-sm group-hover:scale-105 transition-transform">
                                04
                            </div>
                            <div>
                                <h3 class="text-sm sm:text-base font-black text-slate-950 group-hover:text-[#004D25] transition-colors">
                                    Step 4: Simpan &amp; Konfirmasi Partisipasi
                                </h3>
                                <p class="text-xs sm:text-sm text-slate-700 mt-1 leading-relaxed">
                                    Kirimkan jawaban Anda. Data akan langsung tervalidasi oleh sistem dan tercatat resmi sebagai kontributor capaian akreditasi almamater.
                                </p>
                            </div>
                        </div>
                    </div>

                </div>

            </div>

            <!-- 
              ========================================================================
              PETUNJUK AKUN PENGISIAN (Username & Password) 
              Pengaturan Warna: Putih Bersih (bg-white)
              ========================================================================
            -->
            <div class="bg-white rounded-3xl border border-slate-200/90 p-6 sm:p-8 shadow-xl text-slate-900">
                <div class="flex items-center gap-2.5 mb-5">
                    <span class="w-3 h-3 rounded-full bg-[#004D25] shadow-sm"></span>
                    <h3 class="text-base sm:text-lg font-black text-[#004D25] uppercase tracking-wider">
                        Petunjuk Akun Pengisian (Username &amp; Password)
                    </h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <!-- Akun Alumni UKDW -->
                    <div class="p-5 rounded-2xl bg-slate-50/90 border border-slate-200/90 space-y-2.5 shadow-sm text-slate-900">
                        <div class="flex items-center justify-between">
                            <span class="px-3.5 py-1 rounded-full bg-[#004D25] text-[#FFC700] text-xs font-black shadow-sm">
                                Alumni UKDW
                            </span>
                            <span class="text-[11px] font-bold text-[#004D25] bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-200/60">
                                Akun Terdaftar
                            </span>
                        </div>
                        <div class="text-xs sm:text-sm text-slate-700 space-y-1.5 pt-1">
                            <p>
                                <strong class="text-slate-950">Username:</strong> Menggunakan <span class="font-bold text-[#004D25]">NIM</span> saat kuliah.
                            </p>
                            <p>
                                <strong class="text-slate-950">Password:</strong> Tanggal lahir format <code class="bg-amber-100/80 text-slate-900 px-2 py-0.5 rounded-md font-bold border border-amber-300/80">ddmmyyyy</code>.
                            </p>
                            <p class="text-xs text-slate-600 italic bg-white p-3 rounded-xl border border-slate-200/80 mt-2 shadow-xs">
                                Contoh: <strong class="text-[#004D25] font-black not-italic">01011990</strong> (untuk kelahiran 1 Januari 1990).
                            </p>
                        </div>
                    </div>

                    <!-- Akun Pengguna Lulusan / Perusahaan -->
                    <div class="p-5 rounded-2xl bg-slate-50/90 border border-slate-200/90 space-y-2.5 shadow-sm text-slate-900">
                        <div class="flex items-center justify-between">
                            <span class="px-3.5 py-1 rounded-full bg-[#004D25] text-[#FFC700] text-xs font-black shadow-sm">
                                Pengguna Lulusan / Perusahaan
                            </span>
                            <span class="text-[11px] font-bold text-[#004D25] bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-200/60">
                                Akses Kuesioner Mitra
                            </span>
                        </div>
                        <div class="text-xs sm:text-sm text-slate-700 space-y-1.5 pt-1">
                            <p class="leading-relaxed">
                                <strong class="text-slate-950">Akses Kuesioner Mitra:</strong>
                            </p>
                            <p class="leading-relaxed text-slate-600">
                                Menggunakan kredensial khusus yang telah dikirimkan secara resmi melalui email penanggung jawab instansi/perusahaan.
                            </p>
                            <p class="text-xs text-slate-600 italic bg-white p-3 rounded-xl border border-slate-200/80 mt-2 shadow-xs">
                                Cek kotak masuk atau folder spam email PIC perusahaan Anda.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>
</template>
