<!--
    Dokumentasi halaman:
    - Dipakai setelah login ketika akun memiliki must_change_password aktif.
    - submit mengirim password baru ke POST /change-password.
    - Logout tetap tersedia bila pengguna belum siap mengubah password.
    - Tidak ada service frontend khusus; aturan password dan flag akun ditangani backend.
-->
<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthBackdrop from '@/components/Otentikasi/AuthBackdrop.vue';

// Form password baru untuk menyelesaikan kewajiban keamanan akun.
const form = useForm({
    password: '',
    password_confirmation: '',
});

// State visibilitas password baru.
const showPassword = ref(false);
// State visibilitas konfirmasi password baru.
const showConfirmPassword = ref(false);

// Mengirim password baru dan membersihkan nilai sensitif setelah request selesai.
const submit = () => {
    form.post('/change-password', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Ubah Kata Sandi - Tracer Study UKDW" />

    <AuthBackdrop>
        <!-- Wrapper kartu perubahan password responsif. -->
        <div class="flex min-h-screen items-center justify-center p-4 sm:p-8">
            <div class="relative grid min-h-[620px] w-full max-w-6xl overflow-hidden rounded-[2rem] bg-black/10 shadow-2xl ring-1 ring-white/25 backdrop-blur-[2px] lg:grid-cols-[1.05fr_0.95fr]">
                <!-- Panel alasan dan petunjuk keamanan akun. -->
                <section class="flex flex-col justify-between p-7 text-white sm:p-10 lg:p-14">
                    <div>
                        <Link href="/" class="mb-14 inline-flex items-center gap-3">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white p-2 shadow-lg">
                                <img src="/uploads/auth/logo-ukdw.png" alt="Logo UKDW" class="h-full w-full object-contain" />
                            </span>
                            <span>
                                <span class="block text-sm font-extrabold tracking-tight">TRACER STUDY UKDW</span>
                                <span class="block text-[11px] text-white/75">Universitas Kristen Duta Wacana</span>
                            </span>
                        </Link>

                        <p class="mb-3 text-xs font-bold uppercase tracking-[0.2em] text-[#FDC700]">Langkah wajib</p>
                        <h1 class="max-w-xl text-4xl font-extrabold leading-[1.05] tracking-tight sm:text-5xl lg:text-6xl">Buat akunmu lebih aman.</h1>
                        <p class="mt-5 max-w-md text-sm leading-7 text-white/85 sm:text-base">Kata sandi awal harus diganti sebelum kamu melanjutkan pengisian data Tracer Study UKDW.</p>
                    </div>

                    <div class="mt-12 max-w-md rounded-2xl bg-black/20 p-5 ring-1 ring-white/20 backdrop-blur-md">
                        <p class="mb-3 text-xs font-extrabold uppercase tracking-wider text-[#FDC700]">Tips kata sandi</p>
                        <ul class="space-y-2 text-sm leading-6 text-white/80">
                            <li class="flex gap-3"><span class="font-bold text-[#FDC700]">01</span><span>Minimal 12 karakter.</span></li>
                            <li class="flex gap-3"><span class="font-bold text-[#FDC700]">02</span><span>Gunakan kombinasi huruf, angka, atau simbol.</span></li>
                            <li class="flex gap-3"><span class="font-bold text-[#FDC700]">03</span><span>Hindari tanggal lahir dan nomor telepon.</span></li>
                        </ul>
                    </div>
                </section>

                <!-- Panel form wajib ganti password. -->
                <section class="flex items-center p-4 sm:p-8 lg:p-10">
                    <div class="w-full rounded-[1.75rem] bg-white/90 p-6 text-[#123D2B] shadow-2xl backdrop-blur-xl sm:p-9">
                        <div class="mb-8">
                            <p class="mb-2 text-xs font-bold uppercase tracking-[0.18em] text-[#0D542B]">Proteksi akun</p>
                            <h2 class="text-3xl font-extrabold tracking-tight">Ubah kata sandi</h2>
                            <p class="mt-2 text-sm leading-6 text-slate-500">Kata sandi baru akan digunakan saat login berikutnya.</p>
                        </div>

                        <div v-if="form.errors.password || form.errors.password_confirmation" class="mb-5 rounded-2xl bg-[#FFF1F0] px-4 py-3 text-sm text-[#B42318]">
                            {{ form.errors.password || form.errors.password_confirmation }}
                        </div>

                        <form class="space-y-5" @submit.prevent="submit">
                            <div>
                                <label for="password" class="mb-2 block text-sm font-bold">Kata sandi baru</label>
                                <div class="relative">
                                    <input id="password" v-model="form.password" :type="showPassword ? 'text' : 'password'" required autofocus placeholder="Minimal 12 karakter" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 pr-12 text-sm outline-none transition placeholder:text-slate-400 focus:border-[#0D542B] focus:ring-4 focus:ring-[#0D542B]/10" />
                                    <button type="button" class="absolute inset-y-0 right-0 px-4 text-slate-400 hover:text-[#0D542B]" aria-label="Tampilkan kata sandi" @click="showPassword = !showPassword">
                                        <svg v-if="!showPassword" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7C7.523 19 3.732 16.057 2.458 12z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                        <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3l18 18M10.584 10.587a2 2 0 002.829 2.828M9.88 4.24A10.7 10.7 0 0112 4c4.478 0 8.268 2.943 9.542 7a10.7 10.7 0 01-4.04 5.496M6.228 6.228C4.466 7.555 3.154 9.17 2.458 12c.8 2.55 2.6 4.63 5.02 5.796"/></svg>
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label for="password_confirmation" class="mb-2 block text-sm font-bold">Konfirmasi kata sandi</label>
                                <div class="relative">
                                    <input id="password_confirmation" v-model="form.password_confirmation" :type="showConfirmPassword ? 'text' : 'password'" required placeholder="Ulangi kata sandi baru" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 pr-12 text-sm outline-none transition placeholder:text-slate-400 focus:border-[#0D542B] focus:ring-4 focus:ring-[#0D542B]/10" />
                                    <button type="button" class="absolute inset-y-0 right-0 px-4 text-slate-400 hover:text-[#0D542B]" aria-label="Tampilkan konfirmasi kata sandi" @click="showConfirmPassword = !showConfirmPassword">
                                        <svg v-if="!showConfirmPassword" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7C7.523 19 3.732 16.057 2.458 12z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                        <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3l18 18M10.584 10.587a2 2 0 002.829 2.828M9.88 4.24A10.7 10.7 0 0112 4c4.478 0 8.268 2.943 9.542 7a10.7 10.7 0 01-4.04 5.496M6.228 6.228C4.466 7.555 3.154 9.17 2.458 12c.8 2.55 2.6 4.63 5.02 5.796"/></svg>
                                    </button>
                                </div>
                            </div>

                            <button type="submit" :disabled="form.processing" class="flex w-full items-center justify-center rounded-2xl bg-[#0D542B] px-5 py-3.5 text-sm font-bold text-white transition hover:bg-[#08763A] disabled:cursor-not-allowed disabled:opacity-60">
                                <svg v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                {{ form.processing ? 'Menyimpan...' : 'Simpan kata sandi baru' }}
                            </button>
                        </form>

                        <div class="mt-8 flex items-center justify-between gap-3 text-sm">
                            <span class="text-slate-400">Wajib dilakukan sekali.</span>
                            <Link href="/logout" method="post" as="button" class="font-bold text-[#0D542B] hover:underline">Keluar akun</Link>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AuthBackdrop>
</template>
