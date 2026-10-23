<!--
    Dokumentasi halaman:
    - Menjadi halaman khusus untuk meminta tautan reset kata sandi.
    - submit mengirim NIM, username, atau email ke POST /forgot-password.
    - Status sukses tetap ditampilkan setelah redirect back dari backend.
    - Tidak ada service frontend khusus; pencarian akun, pembuatan token, dan email dilakukan backend.
-->
<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthBackdrop from '@/components/Otentikasi/AuthBackdrop.vue';

// Form identitas akun yang dipakai untuk mencari email reset.
const form = useForm({ username_atau_email: '' });

// Mengirim permintaan reset dan membersihkan input jika request berhasil.
const submit = () => {
    form.post('/forgot-password', {
        onSuccess: () => form.reset('username_atau_email'),
    });
};
</script>

<template>
    <Head title="Lupa Kata Sandi - Tracer Study UKDW" />

    <AuthBackdrop>
        <!-- Wrapper kartu pemulihan akun responsif. -->
        <div class="flex min-h-screen items-center justify-center p-4 sm:p-8">
            <div class="relative grid min-h-[620px] w-full max-w-6xl overflow-hidden rounded-[2rem] bg-black/10 shadow-2xl ring-1 ring-white/25 backdrop-blur-[2px] lg:grid-cols-[1.05fr_0.95fr]">
                <!-- Panel konteks pemulihan dan informasi keamanan. -->
                <section class="flex flex-col justify-between p-7 text-white sm:p-10 lg:p-14">
                    <div>
                        <Link href="/login" class="mb-14 inline-flex items-center gap-3">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white p-2 shadow-lg">
                                <img src="/uploads/auth/logo-ukdw.png" alt="Logo UKDW" class="h-full w-full object-contain" />
                            </span>
                            <span>
                                <span class="block text-sm font-extrabold tracking-tight">TRACER STUDY UKDW</span>
                                <span class="block text-[11px] text-white/75">Universitas Kristen Duta Wacana</span>
                            </span>
                        </Link>

                        <p class="mb-3 text-xs font-bold uppercase tracking-[0.2em] text-[#FDC700]">Pemulihan akun</p>
                        <h1 class="max-w-xl text-4xl font-extrabold leading-[1.05] tracking-tight sm:text-5xl lg:text-6xl">Kembali masuk tanpa ribet.</h1>
                        <p class="mt-5 max-w-md text-sm leading-7 text-white/85 sm:text-base">Masukkan identitas akunmu. Jika email terdaftar, kami akan mengirim tautan untuk membuat kata sandi baru.</p>
                    </div>

                    <div class="mt-12 max-w-md rounded-2xl bg-black/20 p-5 ring-1 ring-white/20 backdrop-blur-md">
                        <p class="mb-3 text-xs font-extrabold uppercase tracking-wider text-[#FDC700]">Catatan keamanan</p>
                        <p class="text-sm leading-6 text-white/80">Tautan reset hanya berlaku sementara. Jangan membagikan tautan tersebut kepada orang lain.</p>
                    </div>
                </section>

                <!-- Panel form permintaan tautan reset. -->
                <section class="flex items-center p-4 sm:p-8 lg:p-10">
                    <div class="w-full rounded-[1.75rem] bg-white/90 p-6 text-[#123D2B] shadow-2xl backdrop-blur-xl sm:p-9">
                        <div class="mb-8">
                            <p class="mb-2 text-xs font-bold uppercase tracking-[0.18em] text-[#0D542B]">Butuh bantuan masuk?</p>
                            <h2 class="text-3xl font-extrabold tracking-tight">Lupa kata sandi?</h2>
                            <p class="mt-2 text-sm leading-6 text-slate-500">Masukkan NIM, username, atau email yang terdaftar di Tracer Study UKDW.</p>
                        </div>

                        <div v-if="$page.props.flash?.status" class="mb-5 rounded-2xl bg-[#EAF5ED] px-4 py-3 text-sm leading-6 text-[#0D542B]">
                            {{ $page.props.flash.status }}
                        </div>
                        <div v-if="form.errors.username_atau_email" class="mb-5 rounded-2xl bg-[#FFF1F0] px-4 py-3 text-sm leading-6 text-[#B42318]">
                            {{ form.errors.username_atau_email }}
                        </div>

                        <form class="space-y-5" @submit.prevent="submit">
                            <div>
                                <label for="username_atau_email" class="mb-2 block text-sm font-bold">NIM / Username / Email</label>
                                <input id="username_atau_email" v-model="form.username_atau_email" type="text" required autofocus placeholder="Contoh: 71200101 atau nama@email.com" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-sm outline-none transition placeholder:text-slate-400 focus:border-[#0D542B] focus:ring-4 focus:ring-[#0D542B]/10" />
                            </div>

                            <button type="submit" :disabled="form.processing" class="flex w-full items-center justify-center rounded-2xl bg-[#0D542B] px-5 py-3.5 text-sm font-bold text-white transition hover:bg-[#08763A] disabled:cursor-not-allowed disabled:opacity-60">
                                {{ form.processing ? 'Mengirim...' : 'Kirim tautan reset' }}
                            </button>
                        </form>

                        <div class="mt-8 rounded-2xl bg-[#F7F9F6] p-4 text-sm leading-6 text-slate-500">
                            <p class="font-bold text-[#123D2B]">Email belum terdaftar?</p>
                            <p class="mt-1">Hubungi PIC resmi Biro 3 UKDW di <a href="mailto:biro3@staff.ukdw.ac.id" class="font-bold text-[#0D542B] hover:underline">biro3@staff.ukdw.ac.id</a>.</p>
                        </div>

                        <div class="mt-7 text-center text-sm">
                            <Link href="/login" class="font-bold text-[#0D542B] hover:underline">Kembali ke login</Link>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AuthBackdrop>
</template>
