<!--
  Komponen: Tabel Penekanan Metode Pembelajaran F2 Tracer Study Alumni
  File: resources/js/Pages/Alumni/Components/Kuesioner/TabelF2.vue
  Fungsi: Menampilkan matriks penilaian penekanan metode pembelajaran (F21 s/d F27)
  dengan skala 1 s/d 5 (Tidak Sama Sekali s/d Sangat Besar).
-->
<script setup>
import { computed } from 'vue';

const props = defineProps({
    headerQuestion: {
        type: Object,
        default: null,
    },
    questions: {
        type: Array,
        required: true,
    },
    form: {
        type: Object,
        required: true,
    },
});

// Daftar opsi skala 1-5 untuk Metode Pembelajaran
const scaleOptions = [
    { score: 1, label: 'Tidak Sama Sekali', short: 'TSS' },
    { score: 2, label: 'Kurang', short: 'K' },
    { score: 3, label: 'Cukup Besar', short: 'CB' },
    { score: 4, label: 'Besar', short: 'B' },
    { score: 5, label: 'Sangat Besar', short: 'SB' },
];

// Menghitung berapa metode yang sudah dinilai
const completedCount = computed(() => {
    return props.questions.filter(q => {
        const val = props.form.answers[q.id];
        return val !== undefined && val !== null && val !== '';
    }).length;
});

const handleSelect = (qId, optionText) => {
    props.form.answers[qId] = optionText;
};
</script>

<template>
    <div class="bg-white rounded-2xl sm:rounded-3xl shadow-xs border border-gray-100 overflow-hidden">
        <!-- Header Pertanyaan F2 -->
        <div class="p-5 sm:p-7 md:p-8 pb-4 sm:pb-5 border-b border-gray-100">
            <div class="flex items-center gap-2 mb-1.5">
                <span class="inline-block bg-[#005B3C] text-white text-[10px] sm:text-[11px] font-black uppercase px-2.5 py-0.5 rounded-md tracking-wider shadow-2xs">
                    F2
                </span>
                <span class="text-[10px] sm:text-[11px] font-semibold text-gray-400">
                    Penilaian Metode Pembelajaran
                </span>
            </div>
            <h2 class="text-base sm:text-xl md:text-2xl font-black text-gray-900 leading-snug tracking-tight">
                {{ headerQuestion?.subpertanyaan || headerQuestion?.question_text || 'Menurut anda seberapa besar penekanan pada metode pembelajaran di bawah ini dilaksanakan di program studi anda?' }}
            </h2>
            <p class="text-xs sm:text-sm text-gray-600 font-medium mt-1">
                Pilihlah tingkat penekanan dari 1 (Tidak Sama Sekali) hingga 5 (Sangat Besar) untuk setiap butir metode di bawah ini.
            </p>
        </div>

        <!-- Tabel Matriks Penilaian -->
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[620px]">
                <thead>
                    <tr class="bg-gray-50/90 border-b border-gray-200/80 text-gray-700 text-xs sm:text-sm">
                        <th class="py-3.5 px-4 font-black uppercase text-gray-600 text-center w-12">No</th>
                        <th class="py-3.5 px-4 font-black text-gray-800 min-w-[220px]">Metode Pembelajaran</th>
                        <th 
                            v-for="opt in scaleOptions" 
                            :key="opt.score"
                            class="py-3.5 px-2 text-center font-black text-xs min-w-[85px]"
                        >
                            <div class="font-extrabold text-gray-900">{{ opt.score }}</div>
                            <div class="text-[10px] sm:text-[11px] font-medium text-gray-500 leading-tight mt-0.5">
                                {{ opt.label }}
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr 
                        v-for="(q, idx) in questions" 
                        :key="q.id" 
                        class="transition-colors hover:bg-emerald-50/20"
                        :class="{'bg-emerald-50/30': !!form.answers[q.id]}"
                    >
                        <!-- Nomor Urut -->
                        <td class="py-3.5 px-4 text-center font-black text-xs sm:text-sm text-gray-500">
                            {{ idx + 1 }}
                        </td>

                        <!-- Nama Metode -->
                        <td class="py-3.5 px-4">
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-[11px] font-black px-1.5 py-0.5 rounded bg-gray-100 text-gray-700 border border-gray-200/60 shrink-0">
                                    {{ q.kode_pertanyaan || q.code }}
                                </span>
                                <span class="font-bold text-gray-900 text-xs sm:text-sm md:text-base">
                                    {{ q.subpertanyaan || q.question_text }}
                                </span>
                            </div>
                        </td>

                        <!-- 5 Pilihan Skala Radio -->
                        <td 
                            v-for="opt in scaleOptions" 
                            :key="opt.score"
                            class="py-3.5 px-2 text-center"
                        >
                            <label class="cursor-pointer inline-flex items-center justify-center p-1 group">
                                <input 
                                    type="radio" 
                                    :name="'f2_' + q.id" 
                                    :value="opt.label" 
                                    :checked="form.answers[q.id] === opt.label || form.answers[q.id] == opt.score"
                                    @change="handleSelect(q.id, opt.label)" 
                                    class="sr-only"
                                >
                                <div 
                                    class="w-8 h-8 sm:w-9 sm:h-9 rounded-full flex items-center justify-center font-bold text-xs sm:text-sm transition-all"
                                    :class="[
                                        (form.answers[q.id] === opt.label || form.answers[q.id] == opt.score)
                                            ? 'bg-[#005B3C] text-white ring-2 ring-offset-1 ring-[#005B3C] shadow-sm scale-105 font-black'
                                            : 'bg-white text-gray-700 border border-gray-300 hover:bg-emerald-50 hover:border-emerald-500'
                                    ]"
                                >
                                    {{ opt.score }}
                                </div>
                            </label>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
