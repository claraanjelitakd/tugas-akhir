<!--
  Komponen: Tabel Evaluasi Kompetensi F17 Tracer Study Alumni
  File: resources/js/Pages/Alumni/Components/Kuesioner/TabelF17.vue
  Fungsi: Menampilkan tabel perbandingan 2 sisi untuk butir F17:
          Kolom A: Kemampuan Diri Saat Lulus (Hijau UKDW)
          Tengah: Aspek Kompetensi & Badge Komparasi
          Kolom B: Kebutuhan Saat Ini / Kontribusi Kampus UKDW (Kuning/Amber UKDW)
-->
<script setup>
import { computed } from 'vue';

const props = defineProps({
    question: {
        type: Object,
        default: null,
    },
    pairs: {
        type: Array,
        default: () => [],
    },
    form: {
        type: Object,
        required: true,
    },
});

// 7 Aspek Kompetensi Utama Tracer Study 2021
const defaultAspects = [
    { key: 'etika', aspectNumber: 1, name: 'Etika' },
    { key: 'bidang_ilmu', aspectNumber: 2, name: 'Keahlian berdasarkan bidang ilmu' },
    { key: 'bahasa_inggris', aspectNumber: 3, name: 'Bahasa Inggris' },
    { key: 'ti', aspectNumber: 4, name: 'Penggunaan teknologi informasi' },
    { key: 'komunikasi', aspectNumber: 5, name: 'Komunikasi' },
    { key: 'kerjasama_tim', aspectNumber: 6, name: 'Kerja sama tim' },
    { key: 'pengembangan_diri', aspectNumber: 7, name: 'Pengembangan diri' },
];

const scoreLabels = {
    1: 'Sangat Rendah',
    2: 'Rendah',
    3: 'Cukup',
    4: 'Tinggi',
    5: 'Sangat Tinggi',
};

const ratingScores = {
    'Sangat Rendah': 1,
    'Rendah': 2,
    'Cukup': 3,
    'Tinggi': 4,
    'Sangat Tinggi': 5,
    'Tidak Sama Sekali': 1,
    'Kurang': 2,
    'Cukup Besar': 3,
    'Besar': 4,
    'Sangat Besar': 5,
    'Sangat Kurang': 1,
    '1': 1,
    '2': 2,
    '3': 3,
    '4': 4,
    '5': 5,
};

const isDualMatrixMode = computed(() => {
    return !props.pairs || props.pairs.length === 0;
});

const activeAspectsList = computed(() => {
    if (isDualMatrixMode.value) {
        return defaultAspects;
    }
    return props.pairs;
});

// Helper getter & setter nilai skor A dan B
const getScoreA = (item) => {
    if (isDualMatrixMode.value) {
        return props.form.answers[props.question?.id]?.[item.key]?.A ?? null;
    }
    return props.form.answers[item.qA?.id] ?? null;
};

const setScoreA = (item, score) => {
    const val = scoreLabels[score] || score;
    if (isDualMatrixMode.value) {
        if (!props.form.answers[props.question.id] || typeof props.form.answers[props.question.id] !== 'object') {
            props.form.answers[props.question.id] = {};
        }
        if (!props.form.answers[props.question.id][item.key]) {
            props.form.answers[props.question.id][item.key] = {};
        }
        props.form.answers[props.question.id][item.key].A = val;
    } else if (item.qA) {
        props.form.answers[item.qA.id] = val;
    }
};

const getScoreB = (item) => {
    if (isDualMatrixMode.value) {
        return props.form.answers[props.question?.id]?.[item.key]?.B ?? null;
    }
    return props.form.answers[item.qB?.id] ?? null;
};

const setScoreB = (item, score) => {
    const val = scoreLabels[score] || score;
    if (isDualMatrixMode.value) {
        if (!props.form.answers[props.question.id] || typeof props.form.answers[props.question.id] !== 'object') {
            props.form.answers[props.question.id] = {};
        }
        if (!props.form.answers[props.question.id][item.key]) {
            props.form.answers[props.question.id][item.key] = {};
        }
        props.form.answers[props.question.id][item.key].B = val;
    } else if (item.qB) {
        props.form.answers[item.qB.id] = val;
    }
};

const getRatingScore = (val) => {
    if (val === undefined || val === null || val === '') return null;
    if (typeof val === 'number') {
        return (val >= 1 && val <= 5) ? val : null;
    }
    const str = val.toString().trim();
    if (ratingScores[str]) return ratingScores[str];

    // Pemeriksaan fleksibel case-insensitive
    const lower = str.toLowerCase();
    for (const [key, score] of Object.entries(ratingScores)) {
        if (key.toLowerCase() === lower) {
            return score;
        }
    }

    const n = Number(str);
    if (!isNaN(n) && n >= 1 && n <= 5) return n;
    return null;
};

// Indikator ringkas perbandingan nilai A (Kemampuan Diri) dan B (Kebutuhan/Kontribusi)
const getF17ComparisonBadge = (valA, valB) => {
    const a = getRatingScore(valA);
    const b = getRatingScore(valB);

    if (a !== null && b !== null) {
        if (a > b) {
            return {
                text: `A (${a}) > B (${b})`,
                badgeClass: 'bg-emerald-50 text-emerald-800 border-emerald-200'
            };
        } else if (a === b) {
            return {
                text: `A (${a}) = B (${b})`,
                badgeClass: 'bg-emerald-50 text-emerald-900 border-emerald-300 font-bold'
            };
        } else {
            return {
                text: `A (${a}) < B (${b})`,
                badgeClass: 'bg-amber-50 text-amber-900 border-amber-200'
            };
        }
    }
    return null;
};
</script>

<template>
    <div class="bg-white rounded-2xl sm:rounded-3xl shadow-xs border border-gray-100 overflow-hidden">
        <!-- Header Pertanyaan F17 -->
        <div class="p-5 sm:p-7 md:p-8 pb-4 sm:pb-5 border-b border-gray-100">
            <div>
                <div class="flex items-center gap-2 mb-1.5">
                    <span class="inline-block bg-[#005B3C] text-white text-[10px] sm:text-[11px] font-black uppercase px-2.5 py-0.5 rounded-md tracking-wider shadow-2xs">
                        F17
                    </span>
                    <span class="text-[10px] sm:text-[11px] font-semibold text-gray-400">
                        Evaluasi Kompetensi (A vs B)
                    </span>
                </div>
                <h2 class="text-base sm:text-xl md:text-2xl font-black text-gray-900 leading-snug tracking-tight">
                    Penguasaan Saat Lulus vs Tingkat Kebutuhan Saat Ini
                </h2>
                <p class="text-xs sm:text-sm text-gray-600 font-medium mt-1">
                    Bandingkan tingkat penguasaan Anda saat lulus (Kolom A) dengan tingkat kebutuhan kompetensi pada pekerjaan saat ini (Kolom B).
                </p>
            </div>
        </div>

        <!-- Tabel Komparasi Berdampingan -->
        <div>
            <!-- Petunjuk Geser Horizontal di Mobile Saja -->
            <div class="block md:hidden px-3.5 py-2 bg-emerald-50/80 text-[#005B3C] text-xs font-bold border-b border-emerald-100 flex items-center justify-between">
                <span>Geser tabel ke samping untuk melihat Kolom B</span>
                <span class="text-[10px] bg-emerald-200/60 px-2 py-0.5 rounded-full font-mono">Scroll &rarr;</span>
            </div>
            <div class="overflow-x-auto md:overflow-x-visible">
                <table class="w-full text-sm min-w-[620px] md:min-w-0 border-collapse">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <!-- Header Kolom A (Penguasaan Saat Lulus - Hijau) -->
                            <th class="py-3.5 sm:py-4 px-3 sm:px-4 w-[42%] bg-emerald-50/80 text-left border-r border-gray-200">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="w-5 h-5 sm:w-6 sm:h-6 rounded-md bg-[#005B3C] text-white flex items-center justify-center text-[10px] sm:text-xs font-black shadow-xs shrink-0">A</span>
                                    <span class="font-black text-emerald-950 text-xs sm:text-sm">Penguasaan Saat Lulus</span>
                                </div>
                                <div class="text-[11px] sm:text-xs text-emerald-800 font-medium mb-2.5 leading-tight">
                                    Tingkat kompetensi yang Anda kuasai saat lulus
                                </div>
                                
                                <!-- Header Skala: Sangat Rendah 1 2 3 4 5 Sangat Tinggi -->
                                <div class="bg-white/95 p-2 sm:p-2.5 rounded-xl border border-emerald-200/90 shadow-2xs flex items-center justify-between gap-1 text-xs">
                                    <span class="text-[10px] sm:text-[11px] font-bold text-emerald-800 whitespace-nowrap">Sangat Rendah</span>
                                    <div class="flex items-center justify-center gap-1.5 sm:gap-2.5 md:gap-3 flex-1">
                                        <span v-for="score in 5" :key="'hdr_a_' + score" class="w-6 sm:w-8 text-center text-xs font-black text-emerald-950">
                                            {{ score }}
                                        </span>
                                    </div>
                                    <span class="text-[10px] sm:text-[11px] font-bold text-emerald-800 whitespace-nowrap">Sangat Tinggi</span>
                                </div>
                            </th>

                            <!-- Header Tengah (Aspek Kompetensi) -->
                            <th class="py-3.5 sm:py-4 px-2 sm:px-3 text-center bg-gray-50 text-gray-800 font-black text-[11px] sm:text-xs uppercase tracking-wider w-[16%]">
                                Aspek Kompetensi
                            </th>

                            <!-- Header Kolom B (Tingkat Kebutuhan Saat Ini - Kuning/Amber) -->
                            <th class="py-3.5 sm:py-4 px-3 sm:px-4 w-[42%] bg-amber-50/80 text-left border-l border-gray-200">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="w-5 h-5 sm:w-6 sm:h-6 rounded-md bg-amber-600 text-white flex items-center justify-center text-[10px] sm:text-xs font-black shadow-xs shrink-0">B</span>
                                    <span class="font-black text-amber-950 text-xs sm:text-sm">Kebutuhan Saat Ini</span>
                                </div>
                                <div class="text-[11px] sm:text-xs text-amber-800 font-medium mb-2.5 leading-tight">
                                    Tingkat kebutuhan pada bidang pekerjaan Anda
                                </div>
                                
                                <!-- Header Skala: Sangat Rendah 1 2 3 4 5 Sangat Tinggi -->
                                <div class="bg-white/95 p-2 sm:p-2.5 rounded-xl border border-amber-200/90 shadow-2xs flex items-center justify-between gap-1 text-xs">
                                    <span class="text-[10px] sm:text-[11px] font-bold text-amber-800 whitespace-nowrap">Sangat Rendah</span>
                                    <div class="flex items-center justify-center gap-1.5 sm:gap-2.5 md:gap-3 flex-1">
                                        <span v-for="score in 5" :key="'hdr_b_' + score" class="w-6 sm:w-8 text-center text-xs font-black text-amber-950">
                                            {{ score }}
                                        </span>
                                    </div>
                                    <span class="text-[10px] sm:text-[11px] font-bold text-amber-800 whitespace-nowrap">Sangat Tinggi</span>
                                </div>
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100">
                        <tr 
                            v-for="item in activeAspectsList" 
                            :key="'aspect_' + (item.key || item.aspectNumber)" 
                            class="transition-colors hover:bg-gray-50/60"
                        >
                            <!-- Pilihan Kolom A (Hijau UKDW) -->
                            <td class="py-3.5 sm:py-4 px-2 sm:px-3 md:px-4 bg-emerald-50/20 border-r border-gray-200">
                                <div class="flex items-center justify-center gap-1.5 sm:gap-2.5 md:gap-3 lg:gap-4">
                                    <label 
                                        v-for="score in 5" 
                                        :key="score" 
                                        class="cursor-pointer select-none"
                                        :title="'Kolom A Skor: ' + score"
                                    >
                                        <input 
                                            type="radio" 
                                            :name="'f17_a_' + (item.key || item.qA?.id || item.aspectNumber)" 
                                            :value="score" 
                                            :checked="getRatingScore(getScoreA(item)) === score"
                                            @change="setScoreA(item, score)"
                                            class="sr-only"
                                        >
                                        <div 
                                            class="w-7 h-7 sm:w-8 sm:h-8 md:w-9 md:h-9 rounded-full flex items-center justify-center font-bold text-xs sm:text-sm transition-all shadow-2xs"
                                            :class="[
                                                getRatingScore(getScoreA(item)) === score
                                                    ? 'bg-[#005B3C] text-white ring-2 ring-offset-1 ring-emerald-500 shadow-sm scale-105 font-black'
                                                    : 'bg-white text-gray-700 border border-emerald-300 hover:bg-emerald-100/70 hover:border-emerald-400'
                                            ]"
                                        >
                                            {{ score }}
                                        </div>
                                    </label>
                                </div>
                            </td>

                            <!-- Aspek Kompetensi (Tengah) -->
                            <td class="py-3.5 sm:py-4 px-2 sm:px-3 text-center">
                                <div class="flex flex-col items-center justify-center gap-1">
                                    <span class="font-bold text-gray-900 text-xs sm:text-sm md:text-base leading-snug">
                                        {{ item.aspectNumber }}. {{ item.name || item.aspectName }}
                                    </span>
                                    <!-- Badge perbandingan ringkas (A > B, A = B, A < B) jika keduanya terisi -->
                                    <span 
                                        v-if="getF17ComparisonBadge(getScoreA(item), getScoreB(item))"
                                        class="inline-block px-1.5 py-0.5 rounded text-[9px] sm:text-[10px] font-semibold border"
                                        :class="getF17ComparisonBadge(getScoreA(item), getScoreB(item)).badgeClass"
                                    >
                                        {{ getF17ComparisonBadge(getScoreA(item), getScoreB(item)).text }}
                                    </span>
                                </div>
                            </td>

                            <!-- Pilihan Kolom B (Kuning / Amber UKDW) -->
                            <td class="py-3.5 sm:py-4 px-2 sm:px-3 md:px-4 bg-amber-50/20 border-l border-gray-200">
                                <div class="flex items-center justify-center gap-1.5 sm:gap-2.5 md:gap-3 lg:gap-4">
                                    <label 
                                        v-for="score in 5" 
                                        :key="score" 
                                        class="cursor-pointer select-none"
                                        :title="'Kolom B Skor: ' + score"
                                    >
                                        <input 
                                            type="radio" 
                                            :name="'f17_b_' + (item.key || item.qB?.id || item.aspectNumber)" 
                                            :value="score" 
                                            :checked="getRatingScore(getScoreB(item)) === score"
                                            @change="setScoreB(item, score)"
                                            class="sr-only"
                                        >
                                        <div 
                                            class="w-7 h-7 sm:w-8 sm:h-8 md:w-9 md:h-9 rounded-full flex items-center justify-center font-bold text-xs sm:text-sm transition-all shadow-2xs"
                                            :class="[
                                                getRatingScore(getScoreB(item)) === score
                                                    ? 'bg-amber-600 text-white ring-2 ring-offset-1 ring-amber-400 shadow-sm scale-105 font-black'
                                                    : 'bg-white text-gray-700 border border-amber-300 hover:bg-amber-100/70 hover:border-amber-400'
                                            ]"
                                        >
                                            {{ score }}
                                        </div>
                                    </label>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
