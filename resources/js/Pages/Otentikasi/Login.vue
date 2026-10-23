<!--
    Dokumentasi halaman:
    - Menampilkan autentikasi alumni dan pengguna lulusan.
    - submit mengirim kredensial ke POST /login.
    - Pemulihan password diarahkan ke halaman /forgot-password.
    - AuthBackdrop menyatukan background dan identitas visual seluruh autentikasi.
    - Tidak ada service backend di komponen ini; request ditangani route/controller Laravel.
-->
<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthBackdrop from '@/components/Otentikasi/AuthBackdrop.vue';

// Pesan status yang dikirim controller setelah redirect autentikasi.
defineProps({
    status: String,
});

// Form kredensial utama untuk login.
const form = useForm({ username: '', password: '', remember: false });
// State visibilitas password pada form utama.
const showPassword = ref(false);

// Mengirim kredensial login dan mengosongkan password setelah request selesai.
const submit = () => {
    form.post('/login', { onFinish: () => form.reset('password') });
};

</script>

<template>
    <Head title="Masuk - Tracer Study UKDW" />

    <AuthBackdrop>
        <!-- Wrapper kartu autentikasi responsif. -->
        <div class="flex min-h-screen items-center justify-center p-4 sm:p-8">
            <div class="relative grid min-h-[620px] w-full max-w-6xl overflow-hidden rounded-[2rem] bg-black/10 shadow-2xl ring-1 ring-white/25 backdrop-blur-[2px] lg:grid-cols-[1.1fr_0.9fr]">
                <!-- Panel informasi dan petunjuk akun. -->
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

                        <p class="mb-3 text-xs font-bold uppercase tracking-[0.2em] text-[#FDC700]">Tracer study UKDW</p>
                        <h2 class="max-w-xl text-4xl font-extrabold leading-[1.05] tracking-tight sm:text-5xl lg:text-6xl">
                            Ceritakan langkahmu. Bangun masa depanmu.
                        </h2>
                        <p class="mt-5 max-w-md text-sm leading-7 text-white/85 sm:text-base">
                            Satu ruang untuk memperbarui data alumni dan membantu UKDW memahami perjalanan kariermu.
                        </p>
                    </div>

                    <div class="mt-12 max-w-md rounded-2xl bg-black/20 p-5 ring-1 ring-white/20 backdrop-blur-md">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <span class="text-xs font-extrabold uppercase tracking-wider text-[#FDC700]">Petunjuk akun</span>
                            <span class="rounded-full bg-[#FDC700] px-2.5 py-1 text-[10px] font-bold text-[#123D2B]">Info penting</span>
                        </div>
                        <div class="space-y-2 text-xs leading-5 text-white/80">
                            <p><strong class="text-white">Alumni:</strong> username menggunakan NIM dan password awal menggunakan tanggal lahir format <strong class="text-[#FDC700]">ddmmyyyy</strong>.</p>
                            <p><strong class="text-white">Mitra pengguna lulusan:</strong> gunakan kredensial yang dikirim melalui email resmi.</p>
                        </div>
                    </div>
                </section>

                <!-- Panel form login yang mengambang di atas foto. -->
                <section class="flex items-center p-4 sm:p-8 lg:p-10">
                    <div class="w-full rounded-[1.75rem] bg-white/90 p-6 text-[#123D2B] shadow-2xl backdrop-blur-xl sm:p-9">
                        <div class="mb-8">
                            <p class="mb-2 text-xs font-bold uppercase tracking-[0.18em] text-[#0D542B]">Autentikasi pengguna</p>
                            <h1 class="text-3xl font-extrabold tracking-tight">Masuk ke akun</h1>
                            <p class="mt-2 text-sm leading-6 text-slate-500">Gunakan NIM atau username untuk melanjutkan.</p>
                        </div>

                        <div v-if="status" class="mb-5 rounded-2xl bg-[#EAF5ED] px-4 py-3 text-sm text-[#0D542B]">{{ status }}</div>
                        <div v-if="form.errors.username || form.errors.password" class="mb-5 rounded-2xl bg-[#FFF1F0] px-4 py-3 text-sm text-[#B42318]">{{ form.errors.username || form.errors.password }}</div>

                        <form class="space-y-5" @submit.prevent="submit">
                            <div>
                                <label for="username" class="mb-2 block text-sm font-bold">Username / NIM</label>
                                <input id="username" v-model="form.username" type="text" required autofocus placeholder="Masukkan NIM atau username" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-sm outline-none transition placeholder:text-slate-400 focus:border-[#0D542B] focus:ring-4 focus:ring-[#0D542B]/10" />
                            </div>

                            <div>
                                <label for="password" class="mb-2 block text-sm font-bold">Kata sandi</label>
                                <div class="relative">
                                    <input id="password" v-model="form.password" :type="showPassword ? 'text' : 'password'" required placeholder="Masukkan kata sandi" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 pr-12 text-sm outline-none transition placeholder:text-slate-400 focus:border-[#0D542B] focus:ring-4 focus:ring-[#0D542B]/10" />
                                    <button type="button" class="absolute inset-y-0 right-0 px-4 text-slate-400 hover:text-[#0D542B]" aria-label="Tampilkan kata sandi" @click="showPassword = !showPassword">
                                        <svg v-if="!showPassword" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7C7.523 19 3.732 16.057 2.458 12z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                        <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3l18 18M10.584 10.587a2 2 0 002.829 2.828M9.88 4.24A10.7 10.7 0 0112 4c4.478 0 8.268 2.943 9.542 7a10.7 10.7 0 01-4.04 5.496M6.228 6.228C4.466 7.555 3.154 9.17 2.458 12c.8 2.55 2.6 4.63 5.02 5.796"/></svg>
                                    </button>
                                </div>
                            </div>

                            <div class="flex items-center justify-between gap-4">
                                <label class="flex items-center gap-2 text-sm text-slate-500"><input v-model="form.remember" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-[#0D542B] focus:ring-[#0D542B]" /> Ingat saya</label>
                                <Link href="/forgot-password" class="text-sm font-bold text-[#0D542B] hover:underline">Lupa kata sandi?</Link>
                            </div>

                            <button type="submit" :disabled="form.processing" class="flex w-full items-center justify-center rounded-2xl bg-[#0D542B] px-5 py-3.5 text-sm font-bold text-white transition hover:bg-[#08763A] disabled:cursor-not-allowed disabled:opacity-60">
                                <svg v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                {{ form.processing ? 'Memproses...' : 'Masuk ke sistem' }}
                            </button>
                        </form>

                        <p class="mt-8 text-center text-xs text-slate-400">© {{ new Date().getFullYear() }} Universitas Kristen Duta Wacana</p>
                    </div>
                </section>
            </div>
        </div>

    </AuthBackdrop>
</template>
