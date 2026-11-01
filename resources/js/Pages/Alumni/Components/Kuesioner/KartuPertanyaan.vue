<!--
  Komponen: Kartu Butir Pertanyaan Kuesioner Tracer Study Alumni
  File: resources/js/Pages/Alumni/Components/Kuesioner/KartuPertanyaan.vue
  Fungsi: Merender kartu butir pertanyaan individu beserta seluruh varian tipe input:
          - rating_5 (skala skor 1-5 dengan panduan)
          - searchable_select (dropdown pencarian bidang pekerjaan)
          - single_choice / radio (pilihan tunggal dengan input kustom)
          - multiple_choice / checkbox (pilihan ganda dengan input kustom)
          - radio_input / radio_text (opsi radio dengan input kolom)
          - multiple_number (input nominal penghasilan & kalkulasi gaji)
          - number (angka)
          - text (teks bebas)
-->
<script setup>
import SearchableSelect from '@/components/form/searchable-select.vue';

const props = defineProps({
    subpertanyaan: {
        type: Object,
        required: true,
    },
    form: {
        type: Object,
        required: true,
    },
    isVisible: {
        type: Boolean,
        default: true,
    },
    isPaired: {
        type: Boolean,
        default: false,
    },
});

// Helper pembersih teks instruksi dari teks pertanyaan
const getCleanQuestionText = (text) => {
    if (!text) return '';
    return text
        .replace(/(\s*[\(\[\{]?\s*(jawaban\s+bisa\s+lebih\s+dari\s+satu|pilih(lah)?\s+satu\s+atau\s+lebih|pilih(lah)?\s+satu\s+jawaban|pilih(lah)?\s+salah\s+satu)\s*[\)\]\}]?\.?)/gi, '')
        .trim();
};

// Helper pembersih teks keterangan agar tidak menduplikasi instruksi pilihan
const getCleanKeterangan = (text) => {
    if (!text) return '';
    const clean = text
        .replace(/(\s*[\(\[\{]?\s*(jawaban\s+bisa\s+lebih\s+dari\s+satu|pilih(lah)?\s+satu\s+atau\s+lebih|pilih(lah)?\s+satu\s+jawaban|pilih(lah)?\s+salah\s+satu)\s*[\)\]\}]?\.?)/gi, '')
        .trim();
    return clean;
};

// Instruksi bawaan (default) sesuai tipe pertanyaan agar konsisten di seluruh kartu soal
const getTypeInstruction = (type) => {
    switch (type) {
        case 'radio':
        case 'single_choice':
            return 'Pilih salah satu jawaban';
        case 'checkbox':
        case 'multiple_choice':
            return 'Jawaban bisa lebih dari satu (centang yang sesuai)';
        case 'radio_text':
            return 'Pilih salah satu jawaban (tuliskan keterangan jika memilih Lainnya)';
        case 'radio_input':
            return 'Pilih salah satu jawaban dan lengkapi angka pada kolom isian';
        case 'rating_5':
            return 'Pilih satu angka skala 1 s/d 5';
        case 'dropdown':
        case 'searchable_select':
            return 'Pilih salah satu opsi dari daftar';
        case 'multiple_number':
            return 'Isi nominal angka pada kolom yang tersedia (bisa lebih dari satu)';
        case 'multiple_textbox':
            return 'Lengkapi kolom-kolom isian di bawah ini';
        case 'matrix':
        case 'matrix_dual':
            return 'Pilih satu tingkat penilaian pada setiap baris aspek';
        case 'number':
            return 'Masukkan jawaban berupa angka';
        case 'text':
            return 'Tuliskan jawaban singkat pada kolom di bawah ini';
        case 'textarea':
            return 'Tuliskan uraian jawaban pada kolom di bawah ini';
        case 'date':
            return 'Pilih tanggal sesuai kalender';
        case 'time':
            return 'Pilih waktu (jam dan menit)';
        case 'file':
            return 'Unggah berkas dokumen pendukung';
        default:
            return '';
    }
};

// Deteksi apakah opsi membutuhkan input teks kustom (Lainnya / Other / Tuliskan / ...)
const isCustomOption = (opt) => {
    if (!opt) return false;
    if (typeof opt === 'object' && opt.is_other) return true;
    const text = (typeof opt === 'object' ? opt.option_text : opt) || '';
    const lower = String(text).trim().toLowerCase();
    return lower === 'other' || lower.includes('lainnya') || lower.includes('tuliskan') || lower.includes('...') || lower.includes('…');
};

// Mencegah karakter aneh pada input angka
const filterNumberInput = (event) => {
    if (['e', 'E', '+', '-', '.'].includes(event.key)) {
        event.preventDefault();
    }
};

// Format angka ke mata uang Rupiah standar
const formatRupiah = (val) => {
    const num = Number(val) || 0;
    return 'Rp ' + num.toLocaleString('id-ID');
};

// Menghitung total nominal untuk pertanyaan bertipe multiple_number secara dinamis
const getMultipleNumberTotal = (q) => {
    if (!props.form.answers[q.id] || typeof props.form.answers[q.id] !== 'object') return 0;
    let total = 0;
    (q.detils || q.options)?.forEach(opt => {
        const optCode = opt.kode_opsi || opt.code || opt.id;
        const val = props.form.answers[q.id][optCode];
        if (val !== null && val !== '' && !isNaN(val)) {
            const num = parseInt(val, 10);
            if (num > 0) {
                total += num;
            }
        }
    });
    return total;
};

// Mendapatkan nilai nominal rupiah penuh terkonversi untuk preview per item multiple_number
const getMultipleNumberItemPreview = (val) => {
    if (val === null || val === '' || isNaN(val)) return 'Rp 0';
    const num = parseInt(val, 10);
    if (num <= 0) return 'Rp 0';
    return formatRupiah(num);
};

// Panduan teks skala penilaian 1 s/d 5 (Likert Scale)
const getScaleGuide = (code) => {
    const c = (code || '').toUpperCase();

    if (c.startsWith('F19')) {
        return { min: 'Tidak Sama Sekali', max: 'Sangat Besar' };
    }
    if (c.startsWith('F21')) {
        return { min: 'Sangat Buruk', max: 'Sangat Baik' };
    }
    if (c.startsWith('F20') || c.startsWith('F22')) {
        return { min: 'Sangat Rendah', max: 'Sangat Tinggi' };
    }
    return { min: 'Tidak Sama Sekali / Sangat Buruk', max: 'Sangat Baik / Sangat Besar' };
};

const ratingScoresMap = {
    'Sangat Rendah': 1,
    'Rendah': 2,
    'Cukup': 3,
    'Tinggi': 4,
    'Sangat Tinggi': 5,
    'Sangat Buruk': 1,
    'Buruk': 2,
    'Cukup Baik': 3,
    'Baik': 4,
    'Sangat Baik': 5,
    'Tidak Sama Sekali': 1,
    'Kurang': 2,
    'Cukup Besar': 3,
    'Besar': 4,
    'Sangat Besar': 5,
};

const isRatingScoreSelected = (qId, score) => {
    const val = props.form.answers[qId];
    if (val === undefined || val === null || val === '') return false;
    if (val == score) return true;
    if (typeof val === 'string' && ratingScoresMap[val] === score) return true;
    const n = Number(val);
    return !isNaN(n) && n === score;
};

const setRatingScore = (qId, score) => {
    props.form.answers[qId] = score;
};

// Helper pemisah teks opsi yang memiliki titik-titik (...)
const getSplitDotsText = (text) => {
    if (!text) return { hasDots: false, before: '', after: '' };
    const dotPattern = /\.{2,}|…/;
    if (!dotPattern.test(text)) {
        return { hasDots: false, before: text, after: '' };
    }
    const parts = text.split(dotPattern);
    return {
        hasDots: true,
        before: parts[0] ? parts[0].trim() : '',
        after: parts[1] ? parts[1].trim() : ''
    };
};

// Helper radio_input
const getRadioInputVal = (qId, optId) => {
    if (!props.form.answers[qId] || typeof props.form.answers[qId] !== 'object') return '';
    if (!props.form.answers[qId].inputs) return '';
    return props.form.answers[qId].inputs[optId] ?? '';
};

const setRadioInputVal = (qId, optId, val, optText = null) => {
    if (!props.form.answers[qId] || typeof props.form.answers[qId] !== 'object') {
        props.form.answers[qId] = { selected: '', input: '', inputs: {} };
    }
    if (optText) {
        props.form.answers[qId].selected = optText;
    }
    if (!props.form.answers[qId].inputs) {
        props.form.answers[qId].inputs = {};
    }

    // Bersihkan nilai input pada opsi-opsi radio lainnya agar eksklusif hanya satu opsi yang aktif
    const options = props.subpertanyaan.detils || props.subpertanyaan.options || [];
    options.forEach(o => {
        if (o.id !== optId) {
            props.form.answers[qId].inputs[o.id] = '';
        }
    });

    props.form.answers[qId].inputs[optId] = val;
    props.form.answers[qId].input = val;
};

const handleRadioOptionSelect = (qId, optId, optText = null) => {
    if (!props.form.answers[qId] || typeof props.form.answers[qId] !== 'object') {
        props.form.answers[qId] = { selected: '', input: '', inputs: {} };
    }
    if (optText) {
        props.form.answers[qId].selected = optText;
    }
    if (!props.form.answers[qId].inputs) {
        props.form.answers[qId].inputs = {};
    }

    // Bersihkan nilai input pada opsi-opsi radio lainnya agar eksklusif hanya satu opsi yang aktif
    const options = props.subpertanyaan.detils || props.subpertanyaan.options || [];
    options.forEach(o => {
        if (o.id !== optId) {
            props.form.answers[qId].inputs[o.id] = '';
        }
    });

    props.form.answers[qId].input = props.form.answers[qId].inputs[optId] ?? '';
};

// Helper format input angka nominal langsung di dalam text box
const formatNumberWithDots = (val) => {
    if (val === null || val === undefined || val === '') return '';
    const clean = String(val).replace(/[^0-9]/g, '');
    if (!clean) return '';
    const num = Number(clean);
    return isNaN(num) ? '' : new Intl.NumberFormat('id-ID').format(num);
};

// Handler saat alumni mengetik di kolom multiple_number
const handleMultipleNumberInput = (qId, optCode, event) => {
    if (!props.form.answers[qId] || typeof props.form.answers[qId] !== 'object') {
        props.form.answers[qId] = {};
    }
    const rawVal = event.target.value;
    const cleanDigits = rawVal.replace(/[^0-9]/g, '');
    if (!cleanDigits) {
        props.form.answers[qId][optCode] = '';
        event.target.value = '';
        return;
    }
    const num = Number(cleanDigits);
    props.form.answers[qId][optCode] = num;
    event.target.value = new Intl.NumberFormat('id-ID').format(num);
};

// Helper upload berkas / file
const handleFileUpload = (qId, event) => {
    const file = event.target.files?.[0];
    if (file) {
        props.form.answers[qId] = file.name;
    }
};

// Helper multiple_choice / checkbox
const isCheckboxChecked = (qId, optionText) => {
    return Array.isArray(props.form.answers[qId]) && props.form.answers[qId].includes(optionText);
};

const toggleCheckboxOption = (qId, optionText) => {
    if (!Array.isArray(props.form.answers[qId])) {
        props.form.answers[qId] = [];
    }
    const idx = props.form.answers[qId].indexOf(optionText);
    if (idx > -1) {
        props.form.answers[qId].splice(idx, 1);
    } else {
        props.form.answers[qId].push(optionText);
    }
};

// Helper pendeteksi satuan unit untuk input angka
const getNumberUnit = (q) => {
    const code = (q.kode_pertanyaan || q.code || '').toUpperCase();
    const text = (q.subpertanyaan || q.question_text || '').toLowerCase();

    if (code === 'F502' || code === 'F506' || text.includes('bulan')) {
        return 'Bulan';
    }
    if (code === 'F6' || code === 'F7' || code === 'F7A' || text.includes('lamar') || text.includes('perusahaan') || text.includes('instansi') || text.includes('wawancara')) {
        return '/ perusahaan';
    }
    if (text.includes('orang')) {
        return 'Orang';
    }
    if (text.includes('hari')) {
        return 'Hari';
    }
    if (text.includes('tahun')) {
        return 'Tahun';
    }
    return '';
};

// Helper stepper increment/decrement angka
const stepNumberInput = (qId, delta) => {
    const current = Number(props.form.answers[qId]) || 0;
    const updated = Math.max(0, current + delta);
    props.form.answers[qId] = updated;
};
</script>

<template>
    <!-- KARTU KHUSUS TIPE HEADER / INSTRUKSI -->
    <div
        v-if="subpertanyaan.type === 'header'"
        v-show="isVisible"
        class="bg-emerald-50/70 border border-emerald-200/80 p-4 sm:p-6 rounded-2xl sm:rounded-3xl shadow-xs"
    >
        <div class="flex items-start gap-3 sm:gap-4">
            <span class="shrink-0 bg-[#005B3C] text-white px-2.5 py-1 rounded-xl text-xs sm:text-sm font-black shadow-xs">
                {{ subpertanyaan.kode_pertanyaan }}
            </span>
            <div>
                <h3 class="text-base sm:text-lg font-black text-gray-900 leading-snug">
                    {{ subpertanyaan.subpertanyaan }}
                </h3>
                <p v-if="subpertanyaan.keterangan || subpertanyaan.sub_detail" class="text-xs sm:text-sm text-emerald-800 mt-1 font-semibold">
                    {{ subpertanyaan.keterangan || subpertanyaan.sub_detail }}
                </p>
            </div>
        </div>
    </div>

    <!-- KARTU PERTANYAAN STANDAR -->
    <div
        v-else
        v-show="isVisible"
        class="bg-white p-5 sm:p-7 md:p-8 rounded-2xl sm:rounded-3xl shadow-xs border border-gray-100 relative z-0 transition-all hover:border-emerald-200"
        :class="[
            isPaired ? 'flex flex-col justify-between h-full' : ''
        ]"
        :style="{ zIndex: subpertanyaan.type === 'searchable_select' ? 10 : 1 }"
    >
        <!-- Header Soal: Kode & Teks Pertanyaan (Format Formal) -->
        <div :class="isPaired ? 'mb-4' : 'mb-4 sm:mb-6'">
            <div class="flex items-center gap-2 mb-2">
                <span class="inline-block bg-[#0D542B] text-white text-[10px] sm:text-[11px] font-bold uppercase px-2 py-0.5 rounded tracking-wider shadow-2xs">
                    {{ subpertanyaan.kode_pertanyaan }}
                </span>
            </div>

            <h2 :class="isPaired ? 'text-sm sm:text-base md:text-lg font-bold leading-snug tracking-tight text-gray-900' : 'text-base sm:text-xl md:text-2xl font-bold text-gray-900 leading-snug tracking-tight'">
                {{ getCleanQuestionText(subpertanyaan.subpertanyaan) }}
                <span v-if="subpertanyaan.wajib" class="text-rose-500 font-bold ml-1">*</span>
            </h2>

            <p v-if="getCleanKeterangan(subpertanyaan.keterangan || subpertanyaan.sub_detail)" class="text-xs sm:text-sm text-gray-600 font-medium mt-1">
                {{ getCleanKeterangan(subpertanyaan.keterangan || subpertanyaan.sub_detail) }}
            </p>

            <!-- Badge Instruksi Default Sesuai Tipe Pertanyaan -->
            <div v-if="getTypeInstruction(subpertanyaan.type)" class="mt-2.5">
                <span class="inline-block px-3 py-1 rounded-full bg-slate-100 border border-slate-200 text-[11px] font-semibold text-slate-700">
                    {{ getTypeInstruction(subpertanyaan.type) }}
                </span>
            </div>
        </div>

        <!-- TIPE INPUT: Teks Biasa -->
        <div v-if="subpertanyaan.type === 'text'">
            <input
                type="text"
                v-model="form.answers[subpertanyaan.id]"
                :required="subpertanyaan.wajib && isVisible"
                class="w-full rounded-xl sm:rounded-2xl bg-gray-50/90 p-3.5 sm:p-5 text-gray-900 font-bold focus:bg-white focus:ring-2 focus:ring-[#005B3C] transition-all text-sm sm:text-base border-0 shadow-xs"
                placeholder="Ketik jawabanmu di sini..."
            >
        </div>

        <!-- TIPE INPUT: Paragraf / Textarea -->
        <div v-else-if="subpertanyaan.type === 'textarea'">
            <textarea
                v-model="form.answers[subpertanyaan.id]"
                :required="subpertanyaan.wajib && isVisible"
                rows="4"
                class="w-full rounded-xl sm:rounded-2xl bg-gray-50/90 p-3.5 sm:p-5 text-gray-900 font-medium focus:bg-white focus:ring-2 focus:ring-[#005B3C] transition-all text-sm sm:text-base border-0 shadow-xs resize-y"
                placeholder="Tuliskan uraian jawabanmu di sini..."
            ></textarea>
        </div>

        <!-- TIPE INPUT: Angka / Number (Desain Rapi & Bersih) -->
        <div v-else-if="subpertanyaan.type === 'number'" :class="{'mt-auto pt-2': isPaired}">
            <div class="flex items-center gap-2.5 sm:gap-3">
                <div class="inline-flex items-center bg-gray-50 border border-gray-200/90 rounded-xl p-1 shadow-2xs">
                    <!-- Tombol Kurang (-) -->
                    <button
                        type="button"
                        @click="stepNumberInput(subpertanyaan.id, -1)"
                        class="w-8 h-8 sm:w-9 sm:h-9 rounded-lg bg-white hover:bg-gray-100 text-gray-700 font-black text-base flex items-center justify-center transition-all shadow-2xs active:scale-95 border border-gray-200/80 cursor-pointer"
                        title="Kurang 1"
                    >
                        &minus;
                    </button>

                    <!-- Input Nilai -->
                    <input
                        type="number"
                        v-model="form.answers[subpertanyaan.id]"
                        :required="subpertanyaan.wajib && isVisible"
                        @keydown="filterNumberInput"
                        min="0"
                        class="w-16 sm:w-20 bg-transparent py-1 px-2 text-gray-900 font-black font-mono text-center text-base sm:text-lg border-0 focus:ring-0 placeholder-gray-400 outline-none"
                        placeholder="0"
                    >

                    <!-- Tombol Tambah (+) -->
                    <button
                        type="button"
                        @click="stepNumberInput(subpertanyaan.id, 1)"
                        class="w-8 h-8 sm:w-9 sm:h-9 rounded-lg bg-white hover:bg-gray-100 text-gray-700 font-black text-base flex items-center justify-center transition-all shadow-2xs active:scale-95 border border-gray-200/80 cursor-pointer"
                        title="Tambah 1"
                    >
                        +
                    </button>
                </div>

                <span v-if="getNumberUnit(subpertanyaan)" class="text-xs sm:text-sm font-bold text-gray-700 select-none whitespace-nowrap">
                    {{ getNumberUnit(subpertanyaan) }}
                </span>
            </div>
        </div>

        <!-- TIPE INPUT: Dropdown Menu -->
        <div v-else-if="subpertanyaan.type === 'dropdown'">
            <select
                v-model="form.answers[subpertanyaan.id]"
                :required="subpertanyaan.wajib && isVisible"
                class="w-full rounded-xl sm:rounded-2xl bg-gray-50/90 p-3.5 sm:p-5 text-gray-900 font-bold focus:bg-white focus:ring-2 focus:ring-[#005B3C] transition-all text-sm sm:text-base border-0 shadow-xs cursor-pointer"
            >
                <option value="" disabled>-- Pilih salah satu opsi --</option>
                <option
                    v-for="opt in (subpertanyaan.detils || subpertanyaan.options)"
                    :key="opt.id"
                    :value="opt.option_text"
                >
                    {{ opt.option_text }}
                </option>
            </select>
        </div>

        <!-- TIPE INPUT: Tanggal (Date) -->
        <div v-else-if="subpertanyaan.type === 'date'" class="max-w-xs">
            <input
                type="date"
                v-model="form.answers[subpertanyaan.id]"
                :required="subpertanyaan.wajib && isVisible"
                class="w-full rounded-xl sm:rounded-2xl bg-gray-50/90 p-3.5 sm:p-4 text-gray-900 font-bold focus:bg-white focus:ring-2 focus:ring-[#005B3C] transition-all text-sm sm:text-base border-0 shadow-xs"
            >
        </div>

        <!-- TIPE INPUT: Waktu (Time) -->
        <div v-else-if="subpertanyaan.type === 'time'" class="max-w-xs">
            <input
                type="time"
                v-model="form.answers[subpertanyaan.id]"
                :required="subpertanyaan.wajib && isVisible"
                class="w-full rounded-xl sm:rounded-2xl bg-gray-50/90 p-3.5 sm:p-4 text-gray-900 font-bold focus:bg-white focus:ring-2 focus:ring-[#005B3C] transition-all text-sm sm:text-base border-0 shadow-xs"
            >
        </div>

        <!-- TIPE INPUT: Upload Berkas (File) -->
        <div v-else-if="subpertanyaan.type === 'file'" class="p-6 border-2 border-dashed border-gray-300 rounded-2xl text-center bg-gray-50/70 hover:bg-emerald-50/50 transition-colors">
            <input
                type="file"
                :id="'file_' + subpertanyaan.id"
                class="sr-only"
                @change="handleFileUpload(subpertanyaan.id, $event)"
            >
            <label :for="'file_' + subpertanyaan.id" class="cursor-pointer flex flex-col items-center justify-center">
                <svg class="w-8 h-8 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                <span class="font-bold text-sm text-[#005B3C] hover:underline">Klik untuk mengunggah berkas</span>
                <span class="text-xs text-gray-500 mt-1">PDF, DOC, DOCX, JPG, PNG (Maks. 5MB)</span>
                <span v-if="form.answers[subpertanyaan.id]" class="mt-2 text-xs font-mono font-bold text-emerald-800 bg-emerald-100 px-2.5 py-1 rounded-lg">
                    ✓ Berkas terpilih: {{ form.answers[subpertanyaan.id] }}
                </span>
            </label>
        </div>

        <!-- TIPE INPUT: Searchable Select -->
        <div v-else-if="subpertanyaan.type === 'searchable_select'" class="relative">
            <SearchableSelect
                v-model="form.answers[subpertanyaan.id]"
                :options="subpertanyaan.detils || subpertanyaan.options"
                :required="subpertanyaan.wajib && isVisible"
                placeholder="Cari bidang pekerjaan..."
            />
        </div>

        <!-- TIPE INPUT: Skala Penilaian / Rating 1 s/d 5 (Sesuai Layout F2 & F17) -->
        <div v-else-if="subpertanyaan.type === 'rating_5'" class="space-y-3">
            <div class="p-4 sm:p-6 bg-gray-50/80 rounded-2xl border border-gray-200/80 shadow-2xs">
                <div class="max-w-xl mx-auto">
                    <div class="flex items-center justify-between text-xs font-bold text-gray-600 mb-3 px-1">
                        <span>Sangat Rendah</span>
                        <span>Sangat Tinggi</span>
                    </div>
                    <div class="flex items-center justify-between gap-2 sm:gap-4">
                        <label
                            v-for="score in 5"
                            :key="score"
                            class="cursor-pointer select-none flex-1 flex flex-col items-center group"
                        >
                            <input
                                type="radio"
                                :name="'question_' + subpertanyaan.id"
                                :value="score"
                                :checked="isRatingScoreSelected(subpertanyaan.id, score)"
                                @change="setRatingScore(subpertanyaan.id, score)"
                                :required="subpertanyaan.wajib && isVisible"
                                class="sr-only"
                            >
                            <div
                                class="w-10 h-10 sm:w-12 sm:h-12 rounded-full flex items-center justify-center font-black text-sm sm:text-base shadow-2xs transition-all active:scale-95"
                                :class="[
                                    isRatingScoreSelected(subpertanyaan.id, score)
                                        ? 'bg-[#005B3C] text-white ring-2 ring-offset-2 ring-emerald-500 shadow-sm scale-105'
                                        : 'bg-white text-gray-700 border border-gray-200 hover:border-[#005B3C] hover:bg-emerald-50/50'
                                ]"
                            >
                                {{ score }}
                            </div>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- TIPE INPUT: Pilihan Tunggal / Radio -->
        <div v-else-if="subpertanyaan.type === 'radio' || subpertanyaan.type === 'single_choice'">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5 sm:gap-3">
                <label v-for="opt in (subpertanyaan.detils || subpertanyaan.options)" :key="opt.id" class="cursor-pointer group block select-none">
                    <input
                        type="radio"
                        :name="'question_'+subpertanyaan.id"
                        :value="opt.option_text"
                        v-model="form.answers[subpertanyaan.id]"
                        :required="subpertanyaan.wajib && isVisible"
                        class="sr-only"
                    >
                    <div
                        class="p-3.5 sm:p-4 rounded-xl font-bold text-xs sm:text-sm transition-all flex items-center gap-3 border shadow-2xs"
                        :class="[
                            form.answers[subpertanyaan.id] === opt.option_text
                                ? 'bg-emerald-50/90 border-[#005B3C] text-[#005B3C] ring-1 ring-[#005B3C]'
                                : 'bg-gray-50/70 border-gray-200/90 text-gray-800 hover:bg-emerald-50/30 hover:border-[#005B3C]'
                        ]"
                    >
                        <div
                            class="w-4 h-4 sm:w-5 sm:h-5 rounded-full border flex items-center justify-center shrink-0 transition-colors"
                            :class="form.answers[subpertanyaan.id] === opt.option_text ? 'border-[#005B3C] bg-[#005B3C]' : 'border-gray-300 bg-white'"
                        >
                            <div v-if="form.answers[subpertanyaan.id] === opt.option_text" class="w-1.5 h-1.5 sm:w-2 sm:h-2 rounded-full bg-white"></div>
                        </div>

                        <div class="flex-1 text-left">
                            <div v-if="getSplitDotsText(opt.option_text).hasDots" class="inline-flex flex-wrap items-center gap-1.5 leading-relaxed">
                                <span v-if="getSplitDotsText(opt.option_text).before">{{ getSplitDotsText(opt.option_text).before }}</span>
                                <input
                                    type="text"
                                    v-model="form.answers[subpertanyaan.id + '_custom']"
                                    @click.stop="form.answers[subpertanyaan.id] = opt.option_text"
                                    @focus="form.answers[subpertanyaan.id] = opt.option_text"
                                    placeholder="..."
                                    class="w-16 sm:w-20 py-0.5 px-1.5 text-center font-black rounded-md text-xs font-mono shadow-2xs focus:outline-none border border-gray-300 focus:ring-1 focus:ring-[#005B3C] bg-white text-gray-900"
                                    :required="subpertanyaan.wajib && form.answers[subpertanyaan.id] === opt.option_text"
                                >
                                <span v-if="getSplitDotsText(opt.option_text).after">{{ getSplitDotsText(opt.option_text).after }}</span>
                            </div>
                            <span v-else>{{ opt.option_text }}</span>

                            <div
                                v-if="!getSplitDotsText(opt.option_text).hasDots && form.answers[subpertanyaan.id] === opt.option_text && isCustomOption(opt)"
                                class="w-full mt-2"
                                @click.stop
                            >
                                <input
                                    type="text"
                                    v-model="form.answers[subpertanyaan.id + '_custom']"
                                    placeholder="Tuliskan jawaban spesifik di sini..."
                                    class="w-full rounded-lg bg-white text-gray-900 placeholder-gray-400 p-2 text-xs sm:text-sm font-semibold border border-gray-300 focus:ring-1 focus:ring-[#005B3C] focus:outline-none"
                                    required
                                >
                            </div>
                        </div>
                    </div>
                </label>
            </div>
        </div>

        <!-- TIPE INPUT: Pilihan Ganda / Checkbox -->
        <div v-else-if="subpertanyaan.type === 'checkbox' || subpertanyaan.type === 'multiple_choice'">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5 sm:gap-3">
                <label v-for="opt in (subpertanyaan.detils || subpertanyaan.options)" :key="opt.id" class="cursor-pointer group block select-none">
                    <input
                        type="checkbox"
                        :value="opt.option_text"
                        :checked="isCheckboxChecked(subpertanyaan.id, opt.option_text)"
                        @change="toggleCheckboxOption(subpertanyaan.id, opt.option_text)"
                        class="sr-only"
                    >
                    <div
                        class="p-3.5 sm:p-4 rounded-xl font-bold text-xs sm:text-sm transition-all flex items-center gap-3 border shadow-2xs"
                        :class="[
                            isCheckboxChecked(subpertanyaan.id, opt.option_text)
                                ? 'bg-emerald-50/90 border-[#005B3C] text-[#005B3C] ring-1 ring-[#005B3C]'
                                : 'bg-gray-50/70 border-gray-200/90 text-gray-800 hover:bg-emerald-50/30 hover:border-[#005B3C]'
                        ]"
                    >
                        <div
                            class="w-4 h-4 sm:w-5 sm:h-5 rounded-md border flex items-center justify-center shrink-0 transition-colors"
                            :class="isCheckboxChecked(subpertanyaan.id, opt.option_text) ? 'border-[#005B3C] bg-[#005B3C] text-white' : 'border-gray-300 bg-white'"
                        >
                            <svg v-if="isCheckboxChecked(subpertanyaan.id, opt.option_text)" class="w-3 h-3 stroke-current" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                        </div>

                        <div class="flex-1 text-left">
                            <div v-if="getSplitDotsText(opt.option_text).hasDots" class="inline-flex flex-wrap items-center gap-1.5 leading-relaxed">
                                <span v-if="getSplitDotsText(opt.option_text).before">{{ getSplitDotsText(opt.option_text).before }}</span>
                                <input
                                    type="text"
                                    v-model="form.answers[subpertanyaan.id + '_custom']"
                                    @click.stop="() => { if (!isCheckboxChecked(subpertanyaan.id, opt.option_text)) toggleCheckboxOption(subpertanyaan.id, opt.option_text); }"
                                    @focus="() => { if (!isCheckboxChecked(subpertanyaan.id, opt.option_text)) toggleCheckboxOption(subpertanyaan.id, opt.option_text); }"
                                    placeholder="..."
                                    class="w-16 sm:w-20 py-0.5 px-1.5 text-center font-black rounded-md text-xs font-mono shadow-2xs focus:outline-none border border-gray-300 focus:ring-1 focus:ring-[#005B3C] bg-white text-gray-900"
                                >
                                <span v-if="getSplitDotsText(opt.option_text).after">{{ getSplitDotsText(opt.option_text).after }}</span>
                            </div>
                            <span v-else>{{ opt.option_text }}</span>

                            <div
                                v-if="!getSplitDotsText(opt.option_text).hasDots && isCheckboxChecked(subpertanyaan.id, opt.option_text) && isCustomOption(opt)"
                                class="w-full mt-2"
                                @click.stop
                            >
                                <input
                                    type="text"
                                    v-model="form.answers[subpertanyaan.id + '_custom']"
                                    placeholder="Tuliskan jawaban spesifik di sini..."
                                    class="w-full rounded-lg bg-white text-gray-900 placeholder-gray-400 p-2 text-xs sm:text-sm font-semibold border border-gray-300 focus:ring-1 focus:ring-[#005B3C] focus:outline-none"
                                >
                            </div>
                        </div>
                    </div>
                </label>
            </div>
        </div>


        <!-- TIPE INPUT: Radio Input / Radio Text -->
        <div v-else-if="subpertanyaan.type === 'radio_input' || subpertanyaan.type === 'radio_text'">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5 sm:gap-3">
                <label v-for="opt in (subpertanyaan.detils || subpertanyaan.options)" :key="opt.id" class="cursor-pointer group block select-none">
                    <input
                        type="radio"
                        :name="'question_'+subpertanyaan.id"
                        :value="opt.option_text"
                        v-model="form.answers[subpertanyaan.id].selected"
                        @change="handleRadioOptionSelect(subpertanyaan.id, opt.id, opt.option_text)"
                        class="sr-only"
                    >
                    <div
                        class="p-3.5 sm:p-4 rounded-xl font-bold text-xs sm:text-sm transition-all flex items-center gap-3 border shadow-2xs"
                        :class="[
                            form.answers[subpertanyaan.id]?.selected === opt.option_text
                                ? 'bg-emerald-50/90 border-[#005B3C] text-[#005B3C] ring-1 ring-[#005B3C]'
                                : 'bg-gray-50/70 border-gray-200/90 text-gray-800 hover:bg-emerald-50/30 hover:border-[#005B3C]'
                        ]"
                    >
                        <div
                            class="w-4 h-4 sm:w-5 sm:h-5 rounded-full border flex items-center justify-center shrink-0 transition-colors"
                            :class="form.answers[subpertanyaan.id]?.selected === opt.option_text ? 'border-[#005B3C] bg-[#005B3C]' : 'border-gray-300 bg-white'"
                        >
                            <div v-if="form.answers[subpertanyaan.id]?.selected === opt.option_text" class="w-1.5 h-1.5 sm:w-2 sm:h-2 rounded-full bg-white"></div>
                        </div>

                        <div class="flex-1 text-left">
                            <div v-if="getSplitDotsText(opt.option_text).hasDots" class="inline-flex flex-wrap items-center gap-1.5 leading-relaxed">
                                <span v-if="getSplitDotsText(opt.option_text).before">{{ getSplitDotsText(opt.option_text).before }}</span>
                                <input
                                    :type="subpertanyaan.type === 'radio_input' ? 'number' : 'text'"
                                    :value="getRadioInputVal(subpertanyaan.id, opt.id)"
                                    @input="setRadioInputVal(subpertanyaan.id, opt.id, $event.target.value, opt.option_text)"
                                    @click.stop="handleRadioOptionSelect(subpertanyaan.id, opt.id, opt.option_text)"
                                    @focus="handleRadioOptionSelect(subpertanyaan.id, opt.id, opt.option_text)"
                                    @keydown="subpertanyaan.type === 'radio_input' ? filterNumberInput($event) : null"
                                    min="0"
                                    placeholder="..."
                                    class="w-16 sm:w-20 py-0.5 px-1.5 text-center font-black rounded-md text-xs font-mono shadow-2xs focus:outline-none border border-gray-300 focus:ring-1 focus:ring-[#005B3C] bg-white text-gray-900"
                                    :required="subpertanyaan.wajib && form.answers[subpertanyaan.id]?.selected === opt.option_text"
                                >
                                <span v-if="getSplitDotsText(opt.option_text).after">{{ getSplitDotsText(opt.option_text).after }}</span>
                            </div>
                            <span v-else>{{ opt.option_text }}</span>

                            <div
                                v-if="!getSplitDotsText(opt.option_text).hasDots && form.answers[subpertanyaan.id]?.selected === opt.option_text && isCustomOption(opt)"
                                class="w-full mt-2"
                                @click.stop
                            >
                                <input
                                    :type="subpertanyaan.type === 'radio_input' ? 'number' : 'text'"
                                    :value="getRadioInputVal(subpertanyaan.id, opt.id)"
                                    @input="setRadioInputVal(subpertanyaan.id, opt.id, $event.target.value, opt.option_text)"
                                    :placeholder="subpertanyaan.type === 'radio_input' ? 'Masukkan angka...' : 'Tuliskan di sini...'"
                                    @keydown="subpertanyaan.type === 'radio_input' ? filterNumberInput($event) : null"
                                    class="w-full rounded-lg bg-white text-gray-900 placeholder-gray-400 p-2 text-xs sm:text-sm font-semibold border border-gray-300 focus:ring-1 focus:ring-[#005B3C] focus:outline-none"
                                    required
                                >
                            </div>
                        </div>
                    </div>
                </label>
            </div>
        </div>

        <!-- TIPE INPUT: Multiple Number (Take Home Pay Gaji F13 / F505) -->
        <div v-else-if="subpertanyaan.type === 'multiple_number'" class="space-y-4 sm:space-y-6">
            <div class="p-3.5 sm:p-4 rounded-xl bg-slate-50 border border-slate-200 text-slate-800 flex items-start gap-3 text-xs sm:text-sm">
                <div>
                    <p class="font-bold text-slate-900">Petunjuk Pengisian Penghasilan (Take Home Pay):</p>
                    <p class="text-[11px] sm:text-xs text-slate-600 mt-1 leading-relaxed">
                        Data ini disinkronkan dengan data pendapatan pada Profil Anda. Anda dapat memperbarui atau merinci nominal pada kolom di bawah (format titik ribuan tertata otomatis).
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:gap-4">
                <div
                    v-for="opt in (subpertanyaan.detils || subpertanyaan.options)"
                    :key="opt.id"
                    class="flex flex-col md:flex-row md:items-center justify-between p-3.5 sm:p-5 bg-gray-50/90 rounded-xl sm:rounded-2xl gap-3 sm:gap-4 shadow-xs"
                >
                    <div class="flex-1">
                        <div class="font-bold text-gray-800 text-sm sm:text-base">{{ opt.option_text }}</div>
                        <div v-if="form.answers[subpertanyaan.id]?.[opt.kode_opsi || opt.code || opt.id]" class="text-xs font-bold text-[#005B3C] mt-1">
                            Terbaca: {{ getMultipleNumberItemPreview(form.answers[subpertanyaan.id]?.[opt.kode_opsi || opt.code || opt.id]) }} / bulan
                        </div>
                    </div>

                    <div class="w-full md:w-80">
                        <!-- Input Box Terformat Langsung -->
                        <div class="relative w-full flex items-center rounded-lg sm:rounded-xl bg-white border border-gray-200 shadow-xs focus-within:ring-2 focus-within:ring-[#005B3C] focus-within:border-transparent transition-all overflow-hidden">
                            <span class="pl-3.5 pr-1 font-bold text-gray-400 select-none text-sm sm:text-base">Rp</span>
                            <input
                                type="text"
                                :value="formatNumberWithDots(form.answers[subpertanyaan.id]?.[opt.kode_opsi || opt.code || opt.id])"
                                @input="handleMultipleNumberInput(subpertanyaan.id, opt.kode_opsi || opt.code || opt.id, $event)"
                                placeholder="0"
                                class="w-full py-2.5 sm:py-3 px-2 bg-transparent font-mono font-black border-0 focus:ring-0 text-right text-base sm:text-lg text-gray-900 placeholder-gray-300 tracking-wide"
                            >
                            <span class="pr-3.5 pl-1 text-xs font-semibold text-gray-400 select-none">/ bln</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Take Home Pay -->
            <div class="flex items-center justify-between p-3.5 sm:p-5 bg-emerald-50 rounded-xl sm:rounded-2xl border border-emerald-200/60 shadow-xs">
                <div class="font-black text-[#005B3C] text-sm sm:text-base">Total Penghasilan (Take Home Pay):</div>
                <div class="font-black text-[#005B3C] text-base sm:text-xl font-mono">
                    {{ formatRupiah(getMultipleNumberTotal(subpertanyaan)) }}
                </div>
            </div>
        </div>

        <!-- TIPE INPUT: Matrix (Dinamis dari database detils / options) -->
        <div v-else-if="subpertanyaan.type === 'matrix'" class="space-y-4">
            <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-xs">
                <table class="w-full text-xs sm:text-sm text-left border-collapse min-w-[600px]">
                    <thead class="bg-emerald-50/80 text-emerald-950 font-bold border-b border-gray-200">
                        <tr>
                            <th class="p-3 sm:p-4 text-left">Aspek Penilaian</th>
                            <th v-for="sc in [
                                { val: 1, label: 'Tidak Sama Sekali' },
                                { val: 2, label: 'Kurang' },
                                { val: 3, label: 'Cukup Besar' },
                                { val: 4, label: 'Besar' },
                                { val: 5, label: 'Sangat Besar' }
                            ]" :key="sc.val" class="p-2 sm:p-3 text-center w-20 sm:w-28">
                                <div class="text-[11px] sm:text-xs font-black">{{ sc.label }}</div>
                                <div class="text-[10px] text-emerald-700 font-mono">({{ sc.val }})</div>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="item in (subpertanyaan.detils || subpertanyaan.options || [])"
                            :key="item.kode_opsi || item.id"
                            class="hover:bg-gray-50/80 transition-colors"
                        >
                            <td class="p-3 sm:p-4 font-bold text-gray-800">
                                <span v-if="item.kode_opsi" class="inline-block font-mono text-[11px] bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded mr-2">
                                    {{ item.kode_opsi }}
                                </span>
                                {{ item.option_text }}
                            </td>
                            <td v-for="score in 5" :key="score" class="p-2 sm:p-3 text-center">
                                <label class="cursor-pointer block py-1">
                                    <input
                                        type="radio"
                                        :name="'matrix_' + subpertanyaan.id + '_' + (item.kode_opsi || item.id)"
                                        :value="score"
                                        :checked="form.answers[subpertanyaan.id]?.[item.kode_opsi || item.id] == score"
                                        @change="if (!form.answers[subpertanyaan.id] || typeof form.answers[subpertanyaan.id] !== 'object') form.answers[subpertanyaan.id] = {}; form.answers[subpertanyaan.id][item.kode_opsi || item.id] = score"
                                        class="w-4 h-4 sm:w-5 sm:h-5 text-[#005B3C] focus:ring-[#005B3C] accent-[#005B3C]"
                                    >
                                </label>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TIPE INPUT: Multiple Textbox (Dinamis dari subpertanyaan.detils) -->
        <div v-else-if="subpertanyaan.type === 'multiple_textbox'" class="space-y-3 sm:space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                <div
                    v-for="field in (subpertanyaan.detils && subpertanyaan.detils.length > 0 ? subpertanyaan.detils : (subpertanyaan.options || []))"
                    :key="field.id || field.kode_opsi"
                    class="space-y-1"
                >
                    <label class="block text-xs font-bold text-gray-700">
                        <span v-if="field.kode_opsi" class="inline-block font-mono text-[10px] bg-gray-100 text-gray-600 px-1 py-0.5 rounded mr-1">
                            {{ field.kode_opsi }}
                        </span>
                        {{ field.option_text }}
                    </label>
                    <input
                        :type="(field.kode_opsi && field.kode_opsi.toUpperCase().endsWith('D')) || (field.option_text && field.option_text.toLowerCase().includes('tanggal')) ? 'date' : 'text'"
                        :value="form.answers[subpertanyaan.id]?.[field.kode_opsi || field.id] || ''"
                        @input="if (!form.answers[subpertanyaan.id] || typeof form.answers[subpertanyaan.id] !== 'object') form.answers[subpertanyaan.id] = {}; form.answers[subpertanyaan.id][field.kode_opsi || field.id] = $event.target.value"
                        :placeholder="'Masukkan ' + (field.option_text || '').toLowerCase() + '...'"
                        class="w-full rounded-xl bg-white border border-gray-300 p-2.5 sm:p-3 text-sm focus:ring-2 focus:ring-[#005B3C] focus:border-transparent transition-all font-medium"
                    >
                </div>
            </div>
        </div>
    </div>
</template>
