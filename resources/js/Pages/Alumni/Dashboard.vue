<script setup>
import { Head, Link } from '@inertiajs/vue3';
import Navbar from './Components/Navbar.vue';

defineProps({
    user: { type: Object, default: () => ({}) },
    alumni: { type: Object, default: () => ({}) },
    biodata: { type: Object, default: () => ({}) },
    profilePercentage: { type: Number, default: 0 },
    profileCompleted: { type: Boolean, default: false },
    profileFilledCount: { type: Number, default: 0 },
    profileTotalCount: { type: Number, default: 30 },
    questionnairePercentage: { type: Number, default: 0 },
    questionnaireCompleted: { type: Boolean, default: false },
    questionnaireAnsweredCount: { type: Number, default: 0 },
    questionnaireTotalCount: { type: Number, default: 61 },
    prodiQuestionsCount: { type: Number, default: 0 },
    prodiAnsweredCount: { type: Number, default: 0 },
    prodiCompleted: { type: Boolean, default: false },
    prodiPercentage: { type: Number, default: 100 },
    overallPercentage: { type: Number, default: 0 },
    isOverallComplete: { type: Boolean, default: false },
});
</script>

<template>
    <Head title="Dashboard Alumni - Tracer Study UKDW" />

    <div class="flex min-h-screen flex-col bg-[#f7f8f6] font-sans text-slate-900">
        <Navbar :user="user" />

        <main class="w-full flex-1">
            <div class="mx-auto max-w-7xl px-4 pb-16 pt-8 sm:px-6 sm:pt-10 lg:px-8">
                <section class="" aria-label="Tahapan pengisian tracer study">
                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
                        <article class="flex min-h-[370px] flex-col justify-between rounded-2xl border border-slate-200 border-t-4 border-t-[#0D542B] bg-white p-6 shadow-[0_8px_24px_rgba(15,23,42,0.04)] transition-shadow duration-200 hover:shadow-[0_12px_30px_rgba(15,23,42,0.08)] sm:p-7">
                            <div>
                                <div class="mb-8 flex items-center justify-between gap-3">
                                    <span class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-400">Tahap 1</span>
                                    <span v-if="profileCompleted" class="rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-[#0D542B]">Selesai</span>
                                    <span v-else class="rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-semibold text-slate-600">Belum Lengkap</span>
                                </div>
                                <h2 class="text-xl font-bold tracking-tight text-slate-950">Profil &amp; Riwayat Karier</h2>
                                <p class="mt-2 text-sm leading-6 text-slate-500">Pemutakhiran data domisili, kontak, serta riwayat pekerjaan alumni.</p>
                                <div class="mt-7 rounded-xl border border-slate-200 bg-slate-50/80 p-4">
                                    <div class="mb-2 flex items-center justify-between gap-3 text-sm">
                                        <span class="font-medium text-slate-600">Kelengkapan Data</span>
                                        <span class="font-bold text-slate-950">{{ profilePercentage }}%</span>
                                    </div>
                                    <div class="h-2 w-full overflow-hidden rounded-full bg-slate-200" role="progressbar" :aria-valuenow="profilePercentage" aria-valuemin="0" aria-valuemax="100" aria-label="Kelengkapan profil">
                                        <div class="h-full rounded-full bg-[#0D542B] transition-all duration-300" :style="{ width: `${profilePercentage}%` }"></div>
                                    </div>
                                    <p class="mt-2.5 text-[11px] text-slate-400">{{ profileFilledCount }} dari {{ profileTotalCount }} data terisi</p>
                                </div>
                            </div>
                            <Link href="/alumni/profile" class="mt-7 inline-flex h-11 w-full items-center justify-center rounded-xl bg-[#0D542B] px-4 text-sm font-semibold text-white transition-colors hover:bg-[#093d1f] focus:outline-none focus:ring-2 focus:ring-[#0D542B] focus:ring-offset-2">
                                {{ profileCompleted ? 'Perbarui Profil' : 'Lengkapi Profil' }}
                            </Link>
                        </article>

                        <article class="flex min-h-[370px] flex-col justify-between rounded-2xl border border-slate-200 border-t-4 border-t-[#0D542B] bg-white p-6 shadow-[0_8px_24px_rgba(15,23,42,0.04)] transition-shadow duration-200 hover:shadow-[0_12px_30px_rgba(15,23,42,0.08)] sm:p-7">
                            <div>
                                <div class="mb-8 flex items-center justify-between gap-3">
                                    <span class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-400">Tahap 2</span>
                                    <span v-if="questionnaireCompleted" class="rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-[#0D542B]">Selesai</span>
                                    <span v-else class="rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-semibold text-slate-600">Belum Lengkap</span>
                                </div>
                                <h2 class="text-xl font-bold tracking-tight text-slate-950">Kuesioner Universitas</h2>
                                <p class="mt-2 text-sm leading-6 text-slate-500">Kuesioner pelacakan jejak alumni standar nasional Dikti.</p>
                                <div class="mt-7 rounded-xl border border-slate-200 bg-slate-50/80 p-4">
                                    <div class="mb-2 flex items-center justify-between gap-3 text-sm">
                                        <span class="font-medium text-slate-600">Keterisian Butir Wajib</span>
                                        <span class="font-bold text-slate-950">{{ questionnairePercentage }}%</span>
                                    </div>
                                    <div class="h-2 w-full overflow-hidden rounded-full bg-slate-200" role="progressbar" :aria-valuenow="questionnairePercentage" aria-valuemin="0" aria-valuemax="100" aria-label="Keterisian kuesioner universitas">
                                        <div class="h-full rounded-full bg-[#0D542B] transition-all duration-300" :style="{ width: `${questionnairePercentage}%` }"></div>
                                    </div>
                                    <p class="mt-2.5 text-[11px] text-slate-400">{{ questionnaireAnsweredCount }} dari {{ questionnaireTotalCount }} butir terjawab</p>
                                </div>
                            </div>
                            <Link href="/alumni/kuesioner" class="mt-7 inline-flex h-11 w-full items-center justify-center rounded-xl bg-[#0D542B] px-4 text-sm font-semibold text-white transition-colors hover:bg-[#093d1f] focus:outline-none focus:ring-2 focus:ring-[#0D542B] focus:ring-offset-2">
                                {{ questionnaireCompleted ? 'Lihat Kuesioner' : 'Isi Kuesioner' }}
                            </Link>
                        </article>

                        <article class="flex min-h-[370px] flex-col justify-between rounded-2xl border border-slate-200 border-t-4 border-t-[#0D542B] bg-white p-6 shadow-[0_8px_24px_rgba(15,23,42,0.04)] transition-shadow duration-200 hover:shadow-[0_12px_30px_rgba(15,23,42,0.08)] md:col-span-2 lg:col-span-1 sm:p-7">
                            <div>
                                <div class="mb-8 flex items-center justify-between gap-3">
                                    <span class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-400">Tahap 3</span>
                                    <span v-if="prodiCompleted && prodiQuestionsCount > 0" class="rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-[#0D542B]">Selesai</span>
                                    <span v-else-if="prodiQuestionsCount === 0" class="rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-semibold text-slate-600">Belum Ada Soal</span>
                                    <span v-else class="rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-semibold text-slate-600">Belum Lengkap</span>
                                </div>
                                <h2 class="text-xl font-bold tracking-tight text-slate-950">Kuesioner Program Studi</h2>
                                <p class="mt-2 text-sm leading-6 text-slate-500">Evaluasi kurikulum dan proses pembelajaran tingkat program studi.</p>
                                <div class="mt-7 rounded-xl border border-slate-200 bg-slate-50/80 p-4">
                                    <div class="mb-2 flex items-center justify-between gap-3 text-sm">
                                        <span class="font-medium text-slate-600">Progres Kuesioner Prodi</span>
                                        <span class="font-bold text-slate-950">{{ prodiPercentage }}%</span>
                                    </div>
                                    <div class="h-2 w-full overflow-hidden rounded-full bg-slate-200" role="progressbar" :aria-valuenow="prodiPercentage" aria-valuemin="0" aria-valuemax="100" aria-label="Progres kuesioner program studi">
                                        <div class="h-full rounded-full bg-[#0D542B] transition-all duration-300" :style="{ width: `${prodiPercentage}%` }"></div>
                                    </div>
                                    <p class="mt-2.5 text-[11px] text-slate-400">
                                        <span v-if="prodiQuestionsCount > 0">{{ prodiAnsweredCount }} dari {{ prodiQuestionsCount }} butir terjawab</span>
                                        <span v-else>Belum ada instrumen soal prodi</span>
                                    </p>
                                </div>
                            </div>
                            <Link href="/alumni/kuesioner-prodi" class="mt-7 inline-flex h-11 w-full items-center justify-center rounded-xl bg-[#0D542B] px-4 text-sm font-semibold text-white transition-colors hover:bg-[#093d1f] focus:outline-none focus:ring-2 focus:ring-[#0D542B] focus:ring-offset-2">
                                {{ prodiCompleted ? 'Lihat Kuesioner Prodi' : 'Isi Kuesioner Prodi' }}
                            </Link>
                        </article>
                    </div>
                </section>
            </div>
        </main>


        <footer class="border-t border-slate-200 bg-white">
            <div class="mx-auto flex max-w-7xl flex-col gap-1 px-4 py-6 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                <p class="font-medium text-slate-600">Tracer Study Alumni · Universitas Kristen Duta Wacana</p>
            </div>
        </footer>
    </div>
</template>
