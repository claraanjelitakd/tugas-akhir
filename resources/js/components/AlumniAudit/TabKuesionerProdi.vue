<!--
  Komponen Bersama: Tab Kuesioner Program Studi (Admin View)
  Lokasi: resources/js/Components/AlumniAudit/TabKuesionerProdi.vue
  
  Mendukung peran Super Admin, Fakultas, Prodi, dan Biro 3.
-->
<script setup>
import { ref, computed } from 'vue';

/**
 * Definisi properti komponen tab kuesioner program studi.
 */
const props = defineProps({
    /** Objek data alumni */
    alumni: {
        type: Object,
        required: true,
    },
    /** Daftar seksi kuesioner program studi beserta butir pertanyaan dan jawaban */
    prodiSections: {
        type: Array,
        default: () => [],
    },
    /** Rekap evaluasi kelulusan & persentase kuesioner prodi */
    prodiEvaluasi: {
        type: Object,
        default: () => ({}),
    },
});

/** ID seksi kuesioner prodi aktif untuk filter ('all' atau ID seksi numerik) */
const activeProdiSectionId = ref('all');

/** Kueri teks pencarian butir pertanyaan / jawaban kuesioner prodi */
const searchQueryProdi = ref('');

const filteredProdiSections = computed(() => {
    let result = props.prodiSections || [];

    if (activeProdiSectionId.value !== 'all') {
        result = result.filter(s => s.id === activeProdiSectionId.value);
    }

    if (!searchQueryProdi.value.trim()) {
        return result;
    }

    const query = searchQueryProdi.value.toLowerCase();
    return result.map(section => {
        const matchesSection = (section.title || '').toLowerCase().includes(query);
        const filteredQuestions = (section.questions || []).filter(q => {
            const matchCode = (q.code || '').toLowerCase().includes(query);
            const matchText = (q.question_text || '').toLowerCase().includes(query);
            const matchAnswer = (q.answer_text || '').toLowerCase().includes(query);
            return matchCode || matchText || matchAnswer;
        });

        if (matchesSection) {
            return section;
        }

        if (filteredQuestions.length > 0) {
            return {
                ...section,
                questions: filteredQuestions,
            };
        }

        return null;
    }).filter(Boolean);
});
</script>

<template>
    <div class="space-y-5">
        <!-- Heading & Bar Filter / Pencarian -->
        <div class="bg-white p-4 sm:p-5 rounded-xl shadow-xs border border-slate-200 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm sm:text-base font-bold text-slate-900">
                        Kuesioner Program Studi: {{ alumni.prodi?.nama_prodi || 'Program Studi' }}
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">Daftar instrumen butir evaluasi kurikulum dan kepuasan khusus program studi.</p>
                </div>
                <div class="flex items-center gap-2">
                    <span 
                        class="px-2.5 py-0.5 rounded text-[11px] font-semibold border"
                        :class="prodiEvaluasi?.is_complete ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-amber-50 text-amber-800 border-amber-200'"
                    >
                        Status: {{ prodiEvaluasi?.is_complete ? 'Selesai (100%)' : `Belum Lengkap (${prodiEvaluasi?.percentage || 0}%)` }}
                    </span>
                </div>
            </div>

            <!-- Dropdown Filter Bagian Prodi & Input Pencarian -->
            <div class="pt-3 border-t border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-3">
                <div class="flex items-center gap-2.5 w-full md:w-auto">
                    <label class="text-xs font-bold text-slate-600 whitespace-nowrap">Filter Bagian:</label>
                    <select 
                        v-model="activeProdiSectionId"
                        class="w-full md:w-80 px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs font-semibold text-slate-800 focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B] transition-all cursor-pointer"
                    >
                        <option value="all">Semua Bagian ({{ prodiSections.length }} Bagian)</option>
                        <option 
                            v-for="s in prodiSections" 
                            :key="s.id" 
                            :value="s.id"
                        >
                            {{ s.title }} {{ s.unanswered_mandatory_count > 0 ? `(${s.unanswered_mandatory_count} belum)` : '' }}
                        </option>
                    </select>
                </div>

                <div class="w-full md:w-72">
                    <input 
                        type="text" 
                        v-model="searchQueryProdi" 
                        placeholder="Cari kode, pertanyaan, jawaban..." 
                        class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-800 focus:bg-white focus:ring-1 focus:ring-[#0D542B] focus:border-[#0D542B] transition-all"
                    />
                </div>
            </div>
        </div>

        <!-- Tabel Data Kuesioner Prodi -->
        <div class="bg-white rounded-xl shadow-xs border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-slate-700 border-b border-slate-200 text-[11px] uppercase font-bold tracking-wider">
                            <th class="w-12 text-center py-2.5 px-3 border-r border-slate-200">No</th>
                            <th class="w-24 text-center py-2.5 px-3 border-r border-slate-200">Kode</th>
                            <th class="py-2.5 px-4 border-r border-slate-200 min-w-[280px]">Pertanyaan / Deskripsi Instrumen</th>
                            <th class="w-24 text-center py-2.5 px-3 border-r border-slate-200">Tipe</th>
                            <th class="w-20 text-center py-2.5 px-3 border-r border-slate-200">Sifat</th>
                            <th class="w-28 text-center py-2.5 px-3 border-r border-slate-200">Status</th>
                            <th class="w-80 py-2.5 px-4 min-w-[240px]">Jawaban Alumni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="section in filteredProdiSections" :key="section.id">
                            <!-- Header Bagian Prodi -->
                            <tr class="bg-amber-50 text-amber-950 border-y border-amber-200 font-bold">
                                <td colspan="7" class="py-2.5 px-4 text-xs">
                                    <span>{{ section.title }}</span>
                                    <span v-if="section.description" class="font-normal text-slate-600 ml-1">
                                        &mdash; {{ section.description }}
                                    </span>
                                </td>
                            </tr>

                            <!-- Baris Butir Pertanyaan -->
                            <tr 
                                v-for="(q, idx) in section.questions" 
                                :key="q.id"
                                :class="[
                                    q.is_header 
                                        ? 'bg-amber-50/40 text-amber-950 font-bold border-b border-slate-200'
                                        : 'bg-white hover:bg-slate-50/70 border-b border-slate-100'
                                ]"
                                class="transition-colors text-xs text-slate-800"
                            >
                                <td class="text-center py-2 px-3 text-slate-400 font-medium border-r border-slate-100">
                                    {{ idx + 1 }}
                                </td>
                                <td class="text-center py-2 px-3 font-mono font-semibold text-slate-700 border-r border-slate-100">
                                    {{ q.code || '-' }}
                                </td>

                                <td v-if="q.is_header" colspan="5" class="py-2 px-4 font-bold text-amber-950 text-xs">
                                    {{ q.question_text }}
                                </td>
                                <td v-else class="py-2 px-4 font-medium text-slate-900 border-r border-slate-100 leading-relaxed">
                                    {{ q.question_text }}
                                </td>

                                <td v-if="!q.is_header" class="text-center py-2 px-3 capitalize font-normal text-slate-500 border-r border-slate-100 text-[11px]">
                                    {{ q.type || 'text' }}
                                </td>

                                <td v-if="!q.is_header" class="text-center py-2 px-3 border-r border-slate-100">
                                    <span v-if="q.is_mandatory" class="px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                                        Wajib
                                    </span>
                                    <span v-else class="px-2 py-0.5 rounded text-[10px] font-normal bg-slate-50 text-slate-500">
                                        Opsional
                                    </span>
                                </td>

                                <td v-if="!q.is_header" class="text-center py-2 px-3 border-r border-slate-100">
                                    <span v-if="q.is_answered" class="font-bold text-xs text-emerald-700">
                                        Terisi
                                    </span>
                                    <span v-else class="font-medium text-xs text-rose-500">
                                        Kosong
                                    </span>
                                </td>

                                <td v-if="!q.is_header" class="py-2 px-4 text-slate-900 leading-relaxed">
                                    <span v-if="q.is_answered" class="text-xs font-semibold text-slate-900 whitespace-pre-line">
                                        {{ q.answer_text }}
                                    </span>
                                    <span v-else class="text-xs text-rose-400 font-medium italic">
                                        -
                                    </span>
                                </td>
                            </tr>
                        </template>

                        <tr v-if="filteredProdiSections.length === 0">
                            <td colspan="7" class="py-10 text-center text-slate-400 text-xs italic">
                                Belum ada butir pertanyaan kuesioner program studi yang terkonfigurasi.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
