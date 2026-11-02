<!--
  Komponen: Kartu Butir Pertanyaan Kuesioner Program Studi Alumni
  File: resources/js/Pages/Alumni/Components/KuesionerProdi/KartuPertanyaanProdi.vue
  Fungsi: Merender kartu butir pertanyaan kuesioner prodi dengan desain yang identik
          dengan Kuesioner Universitas (KartuPertanyaan.vue):
          - Badge instruksi default pada setiap tipe pertanyaan
          - Indikator bulatan radio button (single choice / radio / radio_text / radio_input)
          - Indikator kotak centang checkbox (multiple choice / checkbox)
          - Input teks kustom otomatis untuk opsi Lainnya / Other / ...
-->
<script setup>
const props = defineProps({
    block: {
        type: Object,
        required: true,
    },
    form: {
        type: Object,
        required: true,
    },
});

// Pembersih teks instruksi duplikat pada judul pertanyaan
const getCleanQuestionText = (text) => {
    if (!text) return '';
    return text
        .replace(/(\s*[\(\[\{]?\s*(jawaban\s+bisa\s+lebih\s+dari\s+satu|pilih(lah)?\s+satu\s+atau\s+lebih|pilih(lah)?\s+satu\s+jawaban|pilih(lah)?\s+salah\s+satu)\s*[\)\]\}]?\.?)/gi, '')
        .trim();
};

// Instruksi bawaan (default) sesuai tipe pertanyaan agar konsisten dengan Kuesioner Universitas
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
        default:
            return '';
    }
};

// Pemisah teks opsi yang memiliki titik-titik (...)
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
        after: parts[1] ? parts[1].trim() : '',
    };
};

// Deteksi apakah opsi merupakan opsi kustom (Lainnya / Other / Tuliskan / ...)
const isCustomOption = (opt) => {
    if (!opt) return false;
    if (typeof opt === 'object' && opt.is_other) return true;
    const text = (typeof opt === 'object' ? opt.option_text : opt) || '';
    const lower = String(text).trim().toLowerCase();
    return lower === 'other' || lower.includes('lainnya') || lower.includes('tuliskan') || lower.includes('...') || lower.includes('…');
};

// Helper radio_text & radio_input
const getRadioSelected = (qId) => {
    const ans = props.form.answers[qId];
    if (ans && typeof ans === 'object') return ans.selected || '';
    return ans || '';
};

const getRadioInput = (qId) => {
    const ans = props.form.answers[qId];
    if (ans && typeof ans === 'object') return ans.input || '';
    return '';
};

const handleRadioSelect = (qId, optionText) => {
    if (!props.form.answers[qId] || typeof props.form.answers[qId] !== 'object') {
        props.form.answers[qId] = {
            selected: optionText,
            input: '',
        };
    } else {
        props.form.answers[qId].selected = optionText;
    }
};

const setRadioInput = (qId, val, optionText = null) => {
    if (!props.form.answers[qId] || typeof props.form.answers[qId] !== 'object') {
        props.form.answers[qId] = {
            selected: optionText || '',
            input: val,
        };
    } else {
        if (optionText) {
            props.form.answers[qId].selected = optionText;
        }
        props.form.answers[qId].input = val;
    }
};

// Helper checkbox / multiple_choice
const isCheckboxChecked = (qId, optionText) => {
    const ans = props.form.answers[qId];
    if (!Array.isArray(ans)) return false;
    return ans.includes(optionText);
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

// Helper angka & stepper
const filterNumberInput = (e) => {
    if (['e', 'E', '+', '-', '.'].includes(e.key)) {
        e.preventDefault();
    }
};

const stepNumberInput = (qId, delta) => {
    const current = Number(props.form.answers[qId]) || 0;
    const updated = Math.max(0, current + delta);
    props.form.answers[qId] = updated;
};
</script>

<template>
    <!-- 1. KARTU HEADER PETUNJUK (Jika Tipe Header) -->
    <div
        v-if="block.question?.type === 'header'"
        class="bg-emerald-50/70 border border-emerald-200/80 p-4 sm:p-6 rounded-2xl sm:rounded-3xl shadow-xs"
    >
        <div class="flex items-start gap-3 sm:gap-4">
            <span class="shrink-0 bg-[#005B3C] text-white px-2.5 py-1 rounded-xl text-xs sm:text-sm font-black shadow-xs">
                {{ block.question.code || 'Petunjuk' }}
            </span>
            <div>
                <h3 class="text-base sm:text-lg font-black text-gray-900 leading-snug">
                    {{ block.question.question_text }}
                </h3>
            </div>
        </div>
    </div>

    <!-- 2. KARTU PERTANYAAN STANDAR (Desain Identik Kuesioner Universitas) -->
    <div
        v-else-if="block.type === 'standard_question'"
        class="bg-white p-5 sm:p-7 md:p-8 rounded-2xl sm:rounded-3xl shadow-xs border border-gray-100 relative z-0 transition-all hover:border-emerald-200"
    >
        <!-- Header Soal: Kode, Teks Pertanyaan & Badge Instruksi Default -->
        <div class="mb-4 sm:mb-6">
            <div class="flex items-center gap-2 mb-2">
                <span class="inline-block bg-[#0D542B] text-white text-[10px] sm:text-[11px] font-bold uppercase px-2 py-0.5 rounded tracking-wider shadow-2xs">
                    {{ block.question.code }}
                </span>
            </div>

            <h2 class="text-base sm:text-xl md:text-2xl font-bold text-gray-900 leading-snug tracking-tight">
                {{ getCleanQuestionText(block.question.question_text) }}
                <span v-if="block.question.is_required" class="text-rose-500 font-bold ml-1">*</span>
            </h2>

            <!-- Badge Instruksi Default Sesuai Tipe Pertanyaan -->
            <div v-if="getTypeInstruction(block.question.type)" class="mt-2.5">
                <span class="inline-block px-3 py-1 rounded-full bg-slate-100 border border-slate-200 text-[11px] font-semibold text-slate-700">
                    {{ getTypeInstruction(block.question.type) }}
                </span>
            </div>
        </div>

        <!-- TIPE INPUT: Pilihan Tunggal (Radio / Single Choice) -->
        <div v-if="block.question.type === 'single_choice' || block.question.type === 'radio'">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5 sm:gap-3">
                <label v-for="opt in block.question.options" :key="opt.id" class="cursor-pointer group block select-none">
                    <input
                        type="radio"
                        :name="'q_' + block.question.id"
                        :value="opt.option_text"
                        v-model="form.answers[block.question.id]"
                        class="sr-only"
                    >
                    <div
                        class="p-3.5 sm:p-4 rounded-xl font-bold text-xs sm:text-sm transition-all flex items-center gap-3 border shadow-2xs"
                        :class="[
                            form.answers[block.question.id] === opt.option_text
                                ? 'bg-emerald-50/90 border-[#005B3C] text-[#005B3C] ring-1 ring-[#005B3C]'
                                : 'bg-gray-50/70 border-gray-200/90 text-gray-800 hover:bg-emerald-50/30 hover:border-[#005B3C]'
                        ]"
                    >
                        <!-- Indikator Bulatan Radio Button -->
                        <div
                            class="w-4 h-4 sm:w-5 sm:h-5 rounded-full border flex items-center justify-center shrink-0 transition-colors"
                            :class="form.answers[block.question.id] === opt.option_text ? 'border-[#005B3C] bg-[#005B3C]' : 'border-gray-300 bg-white'"
                        >
                            <div v-if="form.answers[block.question.id] === opt.option_text" class="w-1.5 h-1.5 sm:w-2 sm:h-2 rounded-full bg-white"></div>
                        </div>

                        <div class="flex-1 text-left">
                            <div v-if="getSplitDotsText(opt.option_text).hasDots" class="inline-flex flex-wrap items-center gap-1.5 leading-relaxed">
                                <span v-if="getSplitDotsText(opt.option_text).before">{{ getSplitDotsText(opt.option_text).before }}</span>
                                <input
                                    type="text"
                                    v-model="form.answers[block.question.id + '_custom']"
                                    @click.stop="form.answers[block.question.id] = opt.option_text"
                                    @focus="form.answers[block.question.id] = opt.option_text"
                                    placeholder="..."
                                    class="w-16 sm:w-20 py-0.5 px-1.5 text-center font-black rounded-md text-xs font-mono shadow-2xs focus:outline-none border border-gray-300 focus:ring-1 focus:ring-[#005B3C] bg-white text-gray-900"
                                >
                                <span v-if="getSplitDotsText(opt.option_text).after">{{ getSplitDotsText(opt.option_text).after }}</span>
                            </div>
                            <span v-else>{{ opt.option_text }}</span>

                            <!-- Kolom Isian Spesifik jika memilih Lainnya / Other -->
                            <div
                                v-if="!getSplitDotsText(opt.option_text).hasDots && form.answers[block.question.id] === opt.option_text && isCustomOption(opt)"
                                class="w-full mt-2"
                                @click.stop
                            >
                                <input
                                    type="text"
                                    v-model="form.answers[block.question.id + '_custom']"
                                    placeholder="Tuliskan jawaban spesifik di sini..."
                                    class="w-full rounded-lg bg-white text-gray-900 placeholder-gray-400 p-2 text-xs sm:text-sm font-semibold border border-gray-300 focus:ring-1 focus:ring-[#005B3C] focus:outline-none"
                                >
                            </div>
                        </div>
                    </div>
                </label>
            </div>
        </div>

        <!-- TIPE INPUT: Pilihan Ganda / Checkbox (Bisa memilih lebih dari satu) -->
        <div v-else-if="block.question.type === 'multiple_choice' || block.question.type === 'checkbox'">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5 sm:gap-3">
                <label v-for="opt in block.question.options" :key="opt.id" class="cursor-pointer group block select-none">
                    <input
                        type="checkbox"
                        :value="opt.option_text"
                        :checked="isCheckboxChecked(block.question.id, opt.option_text)"
                        @change="toggleCheckboxOption(block.question.id, opt.option_text)"
                        class="sr-only"
                    >
                    <div
                        class="p-3.5 sm:p-4 rounded-xl font-bold text-xs sm:text-sm transition-all flex items-center gap-3 border shadow-2xs"
                        :class="[
                            isCheckboxChecked(block.question.id, opt.option_text)
                                ? 'bg-emerald-50/90 border-[#005B3C] text-[#005B3C] ring-1 ring-[#005B3C]'
                                : 'bg-gray-50/70 border-gray-200/90 text-gray-800 hover:bg-emerald-50/30 hover:border-[#005B3C]'
                        ]"
                    >
                        <!-- Indikator Kotak Centang Checkbox -->
                        <div
                            class="w-4 h-4 sm:w-5 sm:h-5 rounded-md border flex items-center justify-center shrink-0 transition-colors"
                            :class="isCheckboxChecked(block.question.id, opt.option_text) ? 'border-[#005B3C] bg-[#005B3C] text-white' : 'border-gray-300 bg-white'"
                        >
                            <svg v-if="isCheckboxChecked(block.question.id, opt.option_text)" class="w-3 h-3 stroke-current" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                        </div>

                        <div class="flex-1 text-left">
                            <div v-if="getSplitDotsText(opt.option_text).hasDots" class="inline-flex flex-wrap items-center gap-1.5 leading-relaxed">
                                <span v-if="getSplitDotsText(opt.option_text).before">{{ getSplitDotsText(opt.option_text).before }}</span>
                                <input
                                    type="text"
                                    v-model="form.answers[block.question.id + '_custom']"
                                    @click.stop="() => { if (!isCheckboxChecked(block.question.id, opt.option_text)) toggleCheckboxOption(block.question.id, opt.option_text); }"
                                    @focus="() => { if (!isCheckboxChecked(block.question.id, opt.option_text)) toggleCheckboxOption(block.question.id, opt.option_text); }"
                                    placeholder="..."
                                    class="w-16 sm:w-20 py-0.5 px-1.5 text-center font-black rounded-md text-xs font-mono shadow-2xs focus:outline-none border border-gray-300 focus:ring-1 focus:ring-[#005B3C] bg-white text-gray-900"
                                >
                                <span v-if="getSplitDotsText(opt.option_text).after">{{ getSplitDotsText(opt.option_text).after }}</span>
                            </div>
                            <span v-else>{{ opt.option_text }}</span>

                            <!-- Kolom Isian Spesifik jika mencentang Lainnya / Other -->
                            <div
                                v-if="!getSplitDotsText(opt.option_text).hasDots && isCheckboxChecked(block.question.id, opt.option_text) && isCustomOption(opt)"
                                class="w-full mt-2"
                                @click.stop
                            >
                                <input
                                    type="text"
                                    v-model="form.answers[block.question.id + '_custom']"
                                    placeholder="Tuliskan jawaban spesifik di sini..."
                                    class="w-full rounded-lg bg-white text-gray-900 placeholder-gray-400 p-2 text-xs sm:text-sm font-semibold border border-gray-300 focus:ring-1 focus:ring-[#005B3C] focus:outline-none"
                                >
                            </div>
                        </div>
                    </div>
                </label>
            </div>
        </div>

        <!-- TIPE INPUT: Radio + Teks / Radio + Angka (radio_text & radio_input) -->
        <div v-else-if="block.question.type === 'radio_text' || block.question.type === 'radio_input'">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5 sm:gap-3">
                <label v-for="opt in block.question.options" :key="opt.id" class="cursor-pointer group block select-none">
                    <input
                        type="radio"
                        :name="'q_' + block.question.id"
                        :value="opt.option_text"
                        :checked="getRadioSelected(block.question.id) === opt.option_text"
                        @change="handleRadioSelect(block.question.id, opt.option_text)"
                        class="sr-only"
                    >
                    <div
                        class="p-3.5 sm:p-4 rounded-xl font-bold text-xs sm:text-sm transition-all flex items-center gap-3 border shadow-2xs"
                        :class="[
                            getRadioSelected(block.question.id) === opt.option_text
                                ? 'bg-emerald-50/90 border-[#005B3C] text-[#005B3C] ring-1 ring-[#005B3C]'
                                : 'bg-gray-50/70 border-gray-200/90 text-gray-800 hover:bg-emerald-50/30 hover:border-[#005B3C]'
                        ]"
                    >
                        <!-- Indikator Bulatan Radio Button -->
                        <div
                            class="w-4 h-4 sm:w-5 sm:h-5 rounded-full border flex items-center justify-center shrink-0 transition-colors"
                            :class="getRadioSelected(block.question.id) === opt.option_text ? 'border-[#005B3C] bg-[#005B3C]' : 'border-gray-300 bg-white'"
                        >
                            <div v-if="getRadioSelected(block.question.id) === opt.option_text" class="w-1.5 h-1.5 sm:w-2 sm:h-2 rounded-full bg-white"></div>
                        </div>

                        <div class="flex-1 text-left">
                            <div v-if="getSplitDotsText(opt.option_text).hasDots" class="inline-flex flex-wrap items-center gap-1.5 leading-relaxed">
                                <span v-if="getSplitDotsText(opt.option_text).before">{{ getSplitDotsText(opt.option_text).before }}</span>
                                <input
                                    :type="block.question.type === 'radio_input' ? 'number' : 'text'"
                                    :value="getRadioInput(block.question.id)"
                                    @input="setRadioInput(block.question.id, $event.target.value, opt.option_text)"
                                    @click.stop="handleRadioSelect(block.question.id, opt.option_text)"
                                    @focus="handleRadioSelect(block.question.id, opt.option_text)"
                                    @keydown="block.question.type === 'radio_input' ? filterNumberInput($event) : null"
                                    min="0"
                                    placeholder="..."
                                    class="w-16 sm:w-20 py-0.5 px-1.5 text-center font-black rounded-md text-xs font-mono shadow-2xs focus:outline-none border border-gray-300 focus:ring-1 focus:ring-[#005B3C] bg-white text-gray-900"
                                >
                                <span v-if="getSplitDotsText(opt.option_text).after">{{ getSplitDotsText(opt.option_text).after }}</span>
                            </div>
                            <span v-else>{{ opt.option_text }}</span>

                            <!-- Input tambahan untuk radio_text pada opsi Lainnya / Other -->
                            <div
                                v-if="block.question.type === 'radio_text' && !getSplitDotsText(opt.option_text).hasDots && getRadioSelected(block.question.id) === opt.option_text && isCustomOption(opt)"
                                class="w-full mt-2"
                                @click.stop
                            >
                                <input
                                    type="text"
                                    :value="getRadioInput(block.question.id)"
                                    @input="setRadioInput(block.question.id, $event.target.value, opt.option_text)"
                                    placeholder="Tuliskan jawaban spesifik di sini..."
                                    class="w-full rounded-lg bg-white text-gray-900 placeholder-gray-400 p-2 text-xs sm:text-sm font-semibold border border-gray-300 focus:ring-1 focus:ring-[#005B3C] focus:outline-none"
                                >
                            </div>

                            <!-- Input tambahan angka untuk radio_input -->
                            <div
                                v-if="block.question.type === 'radio_input' && !getSplitDotsText(opt.option_text).hasDots && getRadioSelected(block.question.id) === opt.option_text && !opt.option_text.toLowerCase().includes('tidak')"
                                class="w-full mt-2 flex items-center gap-2"
                                @click.stop
                            >
                                <input
                                    type="number"
                                    min="0"
                                    :value="getRadioInput(block.question.id)"
                                    @input="setRadioInput(block.question.id, $event.target.value, opt.option_text)"
                                    @keydown="filterNumberInput"
                                    placeholder="0"
                                    class="w-24 rounded-lg bg-white text-gray-900 placeholder-gray-400 p-2 text-center text-xs sm:text-sm font-bold border border-gray-300 focus:ring-1 focus:ring-[#005B3C] focus:outline-none"
                                >
                                <span class="text-xs font-bold text-[#005B3C]">Bulan</span>
                            </div>
                        </div>
                    </div>
                </label>
            </div>
        </div>

        <!-- TIPE INPUT: Dropdown Select & Searchable Select -->
        <div v-else-if="block.question.type === 'dropdown' || block.question.type === 'searchable_select'">
            <select
                v-model="form.answers[block.question.id]"
                :required="block.question.is_required"
                class="w-full rounded-xl sm:rounded-2xl bg-gray-50/90 p-3.5 sm:p-5 text-gray-900 font-bold focus:bg-white focus:ring-2 focus:ring-[#005B3C] transition-all text-sm sm:text-base border-0 shadow-xs cursor-pointer"
            >
                <option value="" disabled>-- Pilih salah satu opsi --</option>
                <option v-for="opt in block.question.options" :key="opt.id" :value="opt.option_text">
                    {{ opt.option_text }}
                </option>
            </select>
        </div>

        <!-- TIPE INPUT: Isian Teks Bebas Singkat -->
        <div v-else-if="block.question.type === 'text'">
            <input
                type="text"
                v-model="form.answers[block.question.id]"
                :required="block.question.is_required"
                class="w-full rounded-xl sm:rounded-2xl bg-gray-50/90 p-3.5 sm:p-5 text-gray-900 font-bold focus:bg-white focus:ring-2 focus:ring-[#005B3C] transition-all text-sm sm:text-base border-0 shadow-xs"
                placeholder="Ketik jawabanmu di sini..."
            >
        </div>

        <!-- TIPE INPUT: Paragraf (Textarea) -->
        <div v-else-if="block.question.type === 'textarea'">
            <textarea
                v-model="form.answers[block.question.id]"
                rows="4"
                :required="block.question.is_required"
                class="w-full rounded-xl sm:rounded-2xl bg-gray-50/90 p-3.5 sm:p-5 text-gray-900 font-medium focus:bg-white focus:ring-2 focus:ring-[#005B3C] transition-all text-sm sm:text-base border-0 shadow-xs resize-y"
                placeholder="Tuliskan uraian jawabanmu di sini..."
            ></textarea>
        </div>

        <!-- TIPE INPUT: Isian Angka (Dengan Tombol Stepper - / + Identik Univ) -->
        <div v-else-if="block.question.type === 'number'">
            <div class="flex items-center gap-2.5 sm:gap-3">
                <div class="inline-flex items-center bg-gray-50 border border-gray-200/90 rounded-xl p-1 shadow-2xs">
                    <button
                        type="button"
                        @click="stepNumberInput(block.question.id, -1)"
                        class="w-8 h-8 sm:w-9 sm:h-9 rounded-lg bg-white hover:bg-gray-100 text-gray-700 font-black text-base flex items-center justify-center transition-all shadow-2xs active:scale-95 border border-gray-200/80 cursor-pointer"
                        title="Kurang 1"
                    >
                        &minus;
                    </button>
                    <input
                        type="number"
                        v-model="form.answers[block.question.id]"
                        :required="block.question.is_required"
                        @keydown="filterNumberInput"
                        min="0"
                        class="w-16 sm:w-20 bg-transparent py-1 px-2 text-gray-900 font-black font-mono text-center text-base sm:text-lg border-0 focus:ring-0 placeholder-gray-400 outline-none"
                        placeholder="0"
                    >
                    <button
                        type="button"
                        @click="stepNumberInput(block.question.id, 1)"
                        class="w-8 h-8 sm:w-9 sm:h-9 rounded-lg bg-white hover:bg-gray-100 text-gray-700 font-black text-base flex items-center justify-center transition-all shadow-2xs active:scale-95 border border-gray-200/80 cursor-pointer"
                        title="Tambah 1"
                    >
                        +
                    </button>
                </div>
            </div>
        </div>

        <!-- TIPE INPUT: Tanggal / Date -->
        <div v-else-if="block.question.type === 'date'" class="max-w-xs">
            <input
                type="date"
                v-model="form.answers[block.question.id]"
                :required="block.question.is_required"
                class="w-full rounded-xl sm:rounded-2xl bg-gray-50/90 p-3.5 sm:p-4 text-gray-900 font-bold focus:bg-white focus:ring-2 focus:ring-[#005B3C] transition-all text-sm sm:text-base border-0 shadow-xs"
            >
        </div>

        <!-- TIPE INPUT: Waktu / Time -->
        <div v-else-if="block.question.type === 'time'" class="max-w-xs">
            <input
                type="time"
                v-model="form.answers[block.question.id]"
                :required="block.question.is_required"
                class="w-full rounded-xl sm:rounded-2xl bg-gray-50/90 p-3.5 sm:p-4 text-gray-900 font-bold focus:bg-white focus:ring-2 focus:ring-[#005B3C] transition-all text-sm sm:text-base border-0 shadow-xs"
            >
        </div>
    </div>
</template>
