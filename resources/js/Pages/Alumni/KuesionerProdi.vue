<!--
  Halaman: Kuesioner Khusus Program Studi (Frontend Alumni)
  File: resources/js/Pages/Alumni/KuesionerProdi.vue
  Fungsi: Menampilkan kuesioner program studi dengan antarmuka yang setara dengan Kuesioner Universitas:
          - Layout lebar desktop (max-w-6xl / max-w-7xl)
          - Stepper tahapan seksi horizontal di atas
          - Banner judul dan deskripsi seksi
          - Matriks tabel untuk butir skala 1-5 (identik dengan F2 Universitas)
          - Kartu interaktif untuk pilihan tunggal, ganda, teks, angka, dan tanggal
          - Navigasi Kembali dan Lanjut / Selesai
-->
<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted } from 'vue';
import Swal from 'sweetalert2';
import Navbar from './Components/Navbar.vue';
import Stepper from './Components/KuesionerProdi/Stepper.vue';
import Banner from './Components/KuesionerProdi/Banner.vue';
import Navigasi from './Components/KuesionerProdi/Navigasi.vue';
import TabelMatriksProdi from './Components/KuesionerProdi/TabelMatriksProdi.vue';
import KartuPertanyaanProdi from './Components/KuesionerProdi/KartuPertanyaanProdi.vue';

const props = defineProps({
    alumni: Object,
    prodi: Object,
    sections: {
        type: Array,
        default: () => [],
    },
    questions: {
        type: Array,
        default: () => [],
    },
    initialAnswers: {
        type: Object,
        default: () => ({}),
    },
    message: String,
});

// Membentuk hash singkat untuk memisahkan cache antar alumni dan versi instrumen.
const hashStorageValue = (value) => {
    let hash = 0;

    for (let index = 0; index < value.length; index += 1) {
        hash = ((hash << 5) - hash) + value.charCodeAt(index);
        hash |= 0;
    }

    return Math.abs(hash).toString(36);
};

// Menghasilkan versi cache dari struktur pertanyaan dan opsi yang aktif.
const getQuestionnaireVersion = () => {
    const signature = (props.questions || [])
        .map((question) => {
            const options = (question.options || [])
                .map((option) => `${option.id}:${option.option_text}:${option.jump_to || ''}`)
                .join(',');

            return `${question.id}:${question.code}:${question.type}:${options}`;
        })
        .sort()
        .join('|');

    return hashStorageValue(signature || 'empty-questionnaire-prodi');
};

const storageScope = `alumni_${props.alumni?.id || props.alumni?.nim || 'unknown'}_prodi_${props.prodi?.id || 'unknown'}_${getQuestionnaireVersion()}`;
const STORAGE_ANSWERS_KEY = `tracerstudy_alumni_kuesioner_prodi_answers_${storageScope}`;
const STORAGE_SECTION_KEY = `tracerstudy_alumni_kuesioner_prodi_sec_${storageScope}`;

// Menentukan apakah nilai server atau draft lokal masih kosong.
const isAnswerValEmpty = (value) => {
    if (value === undefined || value === null || value === '') return true;
    if (Array.isArray(value)) return value.length === 0;
    if (typeof value === 'object') {
        const keys = Object.keys(value);
        return keys.length === 0 || keys.every((key) => isAnswerValEmpty(value[key]));
    }
    return false;
};

// Menggabungkan jawaban server dengan draft lokal; nilai server selalu menang.
const getInitialAnswers = () => {
    const serverAnswers = JSON.parse(JSON.stringify(props.initialAnswers || {}));
    let cachedAnswers = {};
    if (typeof window !== 'undefined') {
        try {
            const cached = localStorage.getItem(STORAGE_ANSWERS_KEY);
            if (cached) {
                cachedAnswers = JSON.parse(cached) || {};
            }
        } catch (e) {
            console.error('Gagal membaca cache jawaban prodi:', e);
        }
    }

    const base = {};
    const allKeys = new Set([...Object.keys(cachedAnswers), ...Object.keys(serverAnswers)]);
    allKeys.forEach((key) => {
        const serverValue = serverAnswers[key];
        const cachedValue = cachedAnswers[key];
        base[key] = !isAnswerValEmpty(serverValue)
            ? serverValue
            : (!isAnswerValEmpty(cachedValue) ? cachedValue : (serverValue ?? cachedValue));
    });

    return base;
};

const getInitialSectionIndex = () => {
    if (typeof window !== 'undefined') {
        try {
            const cached = localStorage.getItem(STORAGE_SECTION_KEY);
            if (cached !== null) {
                const num = parseInt(cached, 10);
                if (!isNaN(num) && num >= 0) return num;
            }
        } catch (e) {
            console.error('Gagal membaca cache section prodi:', e);
        }
    }
    return 0;
};

const form = useForm({
    answers: getInitialAnswers(),
});

// Mendeteksi apakah terdapat perubahan jawaban dari data awal yang tersimpan
const hasAnswerChanges = computed(() => {
    const current = form.answers || {};
    const initial = props.initialAnswers || {};

    const allKeys = new Set([...Object.keys(current), ...Object.keys(initial)]);
    for (const key of allKeys) {
        const cVal = current[key];
        const iVal = initial[key];
        const cEmpty = cVal === undefined || cVal === null || cVal === '' || (Array.isArray(cVal) && cVal.length === 0);
        const iEmpty = iVal === undefined || iVal === null || iVal === '' || (Array.isArray(iVal) && iVal.length === 0);

        if (cEmpty && iEmpty) continue;
        if (cEmpty !== iEmpty) return true;
        if (JSON.stringify(cVal) !== JSON.stringify(iVal)) return true;
    }
    return false;
});

// Mengembalikan jawaban kuesioner prodi ke data awal yang tersimpan di server
const resetAnswersToInitial = () => {
    Swal.fire({
        title: 'Batalkan Semua Perubahan Jawaban?',
        text: 'Seluruh jawaban kuesioner prodi akan dikembalikan persis ke data yang telah tersimpan sebelumnya di server.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#0D542B',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Kembalikan',
        cancelButtonText: 'Lanjut Mengisi',
    }).then((result) => {
        if (result.isConfirmed) {
            form.answers = JSON.parse(JSON.stringify(props.initialAnswers || {}));
            if (typeof window !== 'undefined') {
                try {
                    localStorage.removeItem(STORAGE_ANSWERS_KEY);
                } catch (e) {
                    console.error('Gagal menghapus cache jawaban prodi:', e);
                }
            }
            Swal.fire({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2500,
                icon: 'info',
                title: 'Jawaban dikembalikan ke data tersimpan',
            });
        }
    });
};

const activeSectionIndex = ref(getInitialSectionIndex());
const completedSectionIndices = ref(new Set());

// Mengelompokkan pertanyaan berdasarkan Section
const groupedSections = computed(() => {
    if (props.sections && props.sections.length > 0) {
        return props.sections.map((sec, sIdx) => {
            const secQuestions = (sec.questions && sec.questions.length > 0)
                ? sec.questions
                : props.questions.filter(q => q.section_id === sec.id);
            
            // Urutkan pertanyaan berdasarkan order
            const sorted = [...secQuestions].sort((a, b) => (a.order || 0) - (b.order || 0));

            return {
                ...sec,
                displayOrder: sIdx + 1,
                questions: sorted,
            };
        });
    }

    // Fallback jika belum ada section terpisah
    return [{
        id: 'default',
        order: 1,
        displayOrder: 1,
        title: 'Kuesioner Program Studi',
        description: 'Pertanyaan evaluasi dan pengembangan program studi.',
        questions: props.questions,
    }];
});

const currentSection = computed(() => {
    if (!visibleSections.value || visibleSections.value.length === 0) return null;
    return visibleSections.value[activeSectionIndex.value] || visibleSections.value[0];
});

// Menyusun semua pertanyaan prodi dalam urutan section untuk evaluasi jump_to.
const orderedQuestionsList = computed(() => groupedSections.value.flatMap((section) => section.questions || []));

// Menentukan pertanyaan prodi yang dilewati berdasarkan opsi pilihan dan target jump_to.
const hiddenQuestionIds = computed(() => {
    const hidden = new Set();
    const questions = orderedQuestionsList.value;
    const codeToIndex = {};

    questions.forEach((question, index) => {
        const code = String(question.code || question.kode_pertanyaan || '').trim().toUpperCase();
        if (code) codeToIndex[code] = index;
    });

    questions.forEach((question, index) => {
        const answer = form.answers[question.id];
        if (answer === undefined || answer === null || answer === '') return;

        const selectedValue = typeof answer === 'object' && !Array.isArray(answer)
            ? answer.selected
            : answer;
        if (selectedValue === undefined || selectedValue === null || selectedValue === '') return;

        const selectedText = String(selectedValue).trim().toLowerCase();
        const selectedOption = (question.options || []).find((option) => {
            const optionText = String(option.option_text || option.text || '').trim().toLowerCase();
            const optionCode = String(option.kode_opsi || option.code || '').trim().toLowerCase();

            return optionText === selectedText || optionCode === selectedText;
        });

        const targetCode = String(selectedOption?.jump_to || '').trim().toUpperCase();
        const targetIndex = codeToIndex[targetCode];
        if (targetIndex === undefined || targetIndex <= index) return;

        for (let skippedIndex = index + 1; skippedIndex < targetIndex; skippedIndex += 1) {
            hidden.add(questions[skippedIndex].id);
        }
    });

    return hidden;
});

// Memeriksa apakah pertanyaan masih terlihat setelah aturan jump_to diterapkan.
const isQuestionVisible = (questionId) => !hiddenQuestionIds.value.has(questionId);

// Menghapus section yang seluruh pertanyaannya dilewati agar navigasi tidak berhenti di halaman kosong.
const visibleSections = computed(() => groupedSections.value.filter((section) => (
    section.questions || []
).some((question) => isQuestionVisible(question.id))));

// Mengelompokkan butir pertanyaan di seksi aktif:
// Butir-butir bertipe rating_5 yang berurutan akan digabung menjadi 1 blok matriks tabel (seperti F2)
const currentSectionBlocks = computed(() => {
    const list = (currentSection.value?.questions || []).filter((question) => isQuestionVisible(question.id));
    const blocks = [];
    let currentRatingGroup = null;

    for (let i = 0; i < list.length; i++) {
        const q = list[i];
        if (q.type === 'rating_5') {
            if (!currentRatingGroup) {
                currentRatingGroup = {
                    type: 'rating_matrix',
                    questions: [],
                };
                blocks.push(currentRatingGroup);
            }
            currentRatingGroup.questions.push(q);
        } else {
            currentRatingGroup = null;
            blocks.push({
                type: 'standard_question',
                question: q,
            });
        }
    }

    return blocks;
});

// Helper label skala 1-5 untuk matriks tabel
const getMatrixScaleOptions = (questions) => {
    if (!questions || questions.length === 0) {
        return [
            { score: 1, label: 'Sangat Tidak Setuju' },
            { score: 2, label: 'Tidak Setuju' },
            { score: 3, label: 'Netral' },
            { score: 4, label: 'Setuju' },
            { score: 5, label: 'Sangat Setuju' },
        ];
    }

    const firstWithOpts = questions.find(q => q.options && q.options.length >= 5);
    if (firstWithOpts) {
        return firstWithOpts.options.slice(0, 5).map((opt, idx) => ({
            score: idx + 1,
            label: (opt.option_text || '').replace(/^\d+\s*[-=:]\s*/, '').trim(),
        }));
    }

    return [
        { score: 1, label: 'Sangat Rendah' },
        { score: 2, label: 'Rendah' },
        { score: 3, label: 'Cukup' },
        { score: 4, label: 'Tinggi' },
        { score: 5, label: 'Sangat Tinggi' },
    ];
};

const filterNumberInput = (event) => {
    if (['e', 'E', '+', '-', '.'].includes(event.key)) {
        event.preventDefault();
    }
};


// Helper deteksi apakah nilai jawaban termasuk opsi kustom (Lainnya / Other / Tuliskan / ...)
const isCustomAnswerText = (text) => {
    if (!text) return false;
    const lower = String(text).trim().toLowerCase();
    return lower === 'other' || lower.includes('lainnya') || lower.includes('tuliskan') || lower.includes('...') || lower.includes('…');
};

// Validasi kelengkapan per butir pertanyaan
const isQuestionAnswered = (q) => {
    if (!q) return false;
    if (q.type === 'header') return true;
    const ans = form.answers[q.id];
    if (ans === undefined || ans === null || ans === '') return false;
    if (Array.isArray(ans)) {
        if (ans.length === 0) return false;
        if (ans.some(v => isCustomAnswerText(v))) {
            const customAns = form.answers[q.id + '_custom'];
            return customAns !== undefined && customAns !== null && String(customAns).trim() !== '';
        }
        return true;
    }
    if (typeof ans === 'object') {
        if (!ans.selected || String(ans.selected).trim() === '') return false;
        if (isCustomAnswerText(ans.selected)) {
            return ans.input !== undefined && ans.input !== null && String(ans.input).trim() !== '';
        }
        return true;
    }
    const str = String(ans).trim();
    if (str === '') return false;
    if ((q.type === 'single_choice' || q.type === 'radio') && isCustomAnswerText(str)) {
        const customAns = form.answers[q.id + '_custom'];
        return customAns !== undefined && customAns !== null && String(customAns).trim() !== '';
    }
    return true;
};

// Validasi kelengkapan satu seksi
const isSectionAnswered = (section) => {
    if (!section || !section.questions || section.questions.length === 0) return false;
    const validQuestions = section.questions.filter(q => q.type !== 'header' && isQuestionVisible(q.id));
    if (validQuestions.length === 0) return true;
    const requiredQuestions = validQuestions.filter(q => q.is_required);
    return requiredQuestions.every(q => isQuestionAnswered(q));
};

// Status kelengkapan seksi untuk stepper (hanya ceklist jika seluruh soal wajib di seksi tersebut benar-benar terjawab)
const isSectionCompleted = (index) => {
    const sec = visibleSections.value[index];
    if (!sec) return false;
    return isSectionAnswered(sec);
};

// Menentukan apakah section boleh dibuka setelah semua section sebelumnya selesai.
const canSelectSection = (index) => {
    if (!visibleSections.value[index]) {
        return false;
    }

    return visibleSections.value
        .slice(0, index)
        .every((section, sectionIndex) => isSectionCompleted(sectionIndex));
};

// Menampilkan alasan ketika alumni mencoba melewati section yang belum lengkap.
const notifyIncompleteSection = (section) => {
    Swal.fire({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        icon: 'warning',
        title: `Lengkapi bagian ${section?.title || 'ini'} terlebih dahulu`,
    });
};

const isLineCompleted = (index) => {
    const currentCompleted = isSectionCompleted(index);
    const nextCompleted = isSectionCompleted(index + 1);
    const currentActive = (activeSectionIndex.value === index);
    const nextActive = (activeSectionIndex.value === index + 1);

    return (currentCompleted && (nextCompleted || nextActive)) || 
           (index < activeSectionIndex.value && currentCompleted);
};

// Sinkronisasi status kelengkapan
const syncSectionCompletion = () => {
    const newCompleted = new Set();
    visibleSections.value.forEach((sec, idx) => {
        if (isSectionAnswered(sec)) {
            newCompleted.add(idx);
        }
    });
    completedSectionIndices.value = newCompleted;
};

// Hitung total progress
const progressStats = computed(() => {
    const validQuestions = orderedQuestionsList.value.filter(q => q.type !== 'header' && isQuestionVisible(q.id));
    const total = validQuestions.length;
    if (total === 0) return { answered: 0, total: 0, percentage: 0 };

    let answered = 0;
    for (const q of validQuestions) {
        if (isQuestionAnswered(q)) {
            answered++;
        }
    }

    return {
        answered,
        total,
        percentage: Math.round((answered / total) * 100),
    };
});

// Navigasi Section
const setSection = (index) => {
    if (!canSelectSection(index)) {
        const firstIncompleteSection = visibleSections.value.find((section, sectionIndex) => !isSectionCompleted(sectionIndex));
        notifyIncompleteSection(firstIncompleteSection);

        return;
    }

    syncSectionCompletion();
    activeSectionIndex.value = index;
    window.scrollTo({ top: 0, behavior: 'smooth' });

    router.post('/alumni/kuesioner-prodi', {
        answers: form.answers,
        stay_on_page: true,
    }, {
        preserveScroll: true,
        preserveState: true,
        showProgress: false,
        only: ['initialAnswers'],
    });
};

const prevSection = () => {
    if (activeSectionIndex.value > 0) {
        activeSectionIndex.value--;
        window.scrollTo({ top: 0, behavior: 'smooth' });

        router.post('/alumni/kuesioner-prodi', {
            answers: form.answers,
            stay_on_page: true,
        }, {
            preserveScroll: true,
            preserveState: true,
            showProgress: false,
            only: ['initialAnswers'],
        });
    }
};

const isLastSection = computed(() => {
    return activeSectionIndex.value >= visibleSections.value.length - 1;
});

const handleNextOrSubmit = () => {
    if (!isSectionCompleted(activeSectionIndex.value)) {
        notifyIncompleteSection(currentSection.value);

        return;
    }

    if (isLastSection.value) {
        const firstIncompleteSection = visibleSections.value.find((section, sectionIndex) => !isSectionCompleted(sectionIndex));

        if (firstIncompleteSection) {
            activeSectionIndex.value = visibleSections.value.indexOf(firstIncompleteSection);
            notifyIncompleteSection(firstIncompleteSection);

            return;
        }

        // Validasi seluruh pertanyaan wajib
        const requiredQuestions = orderedQuestionsList.value.filter(q => q.is_required && isQuestionVisible(q.id));
        const missing = requiredQuestions.filter(q => !isQuestionAnswered(q));

        if (missing.length > 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Jawaban Belum Lengkap',
                text: `Masih ada ${missing.length} pertanyaan wajib yang belum diisi. Mohon lengkapi sebelum mengirim.`,
                confirmButtonColor: '#005B3C',
            });
            return;
        }

        Swal.fire({
            title: 'Kirim Jawaban Kuesioner Prodi?',
            text: 'Pastikan seluruh jawaban Anda sudah sesuai. Jawaban Anda sangat bermanfaat bagi evaluasi kurikulum program studi.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#005B3C',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Ya, Simpan Jawaban',
            cancelButtonText: 'Periksa Kembali',
        }).then((result) => {
            if (result.isConfirmed) {
                form.post('/alumni/kuesioner-prodi', {
                    preserveScroll: true,
                    onSuccess: () => {
                        completedSectionIndices.value.add(activeSectionIndex.value);
                        if (typeof window !== 'undefined') {
                            localStorage.removeItem(STORAGE_ANSWERS_KEY);
                            localStorage.removeItem(STORAGE_SECTION_KEY);
                        }
                        Swal.fire({
                            icon: 'success',
                            title: 'Tersimpan!',
                            text: 'Jawaban kuesioner program studi berhasil disimpan. Terima kasih atas partisipasi Anda!',
                            confirmButtonColor: '#005B3C',
                        });
                    },
                });
            }
        });
    } else {
        // Lanjut ke section berikutnya & sinkronkan status kelengkapan hanya berdasarkan jawaban nyata
        syncSectionCompletion();
        activeSectionIndex.value++;
        window.scrollTo({ top: 0, behavior: 'smooth' });

        router.post('/alumni/kuesioner-prodi', {
            answers: form.answers,
            stay_on_page: true,
        }, {
            preserveScroll: true,
            preserveState: true,
            showProgress: false,
            only: ['initialAnswers'],
            onSuccess: () => {
                syncSectionCompletion();
            }
        });
    }
};

// Lifecycle & Watchers
onMounted(() => {
    if (activeSectionIndex.value >= visibleSections.value.length) {
        activeSectionIndex.value = 0;
    }
    syncSectionCompletion();

    const firstIncompleteSection = visibleSections.value.find((section, sectionIndex) => !isSectionCompleted(sectionIndex));
    if (!canSelectSection(activeSectionIndex.value) && firstIncompleteSection) {
        activeSectionIndex.value = visibleSections.value.indexOf(firstIncompleteSection);
    }
});

watch(activeSectionIndex, (newIdx) => {
    if (typeof window !== 'undefined') {
        localStorage.setItem(STORAGE_SECTION_KEY, newIdx.toString());
    }
});

// Menyesuaikan section aktif ketika jawaban baru menyembunyikan section melalui jump_to.
watch(visibleSections, (sections) => {
    if (activeSectionIndex.value >= sections.length) {
        activeSectionIndex.value = Math.max(0, sections.length - 1);
    }
}, { immediate: true });

let draftTimer = null;
watch(() => form.answers, (newAnswers) => {
    if (typeof window !== 'undefined') {
        clearTimeout(draftTimer);
        draftTimer = setTimeout(() => {
            try {
                localStorage.setItem(STORAGE_ANSWERS_KEY, JSON.stringify(newAnswers));
            } catch (e) {
                console.error('Gagal menyimpan cache jawaban prodi:', e);
            }
        }, 300);
    }
    syncSectionCompletion();
}, { deep: true });
</script>

<template>
    <Head :title="'Kuesioner Program Studi ' + (prodi?.nama_prodi || '')" />

    <div class="relative flex min-h-screen w-full min-w-0 max-w-full flex-col overflow-x-hidden bg-slate-50 pb-20 font-sans text-gray-900 antialiased md:pb-6">
        <!-- Header Atas: Navbar & Stepper Tahapan -->
        <header class="relative z-50 w-full bg-white shadow-md">
            <!-- 1. Navbar Atas -->
            <Navbar />

            <!-- 2. Stepper Tahapan (Bulatan Angka 1 s/d N & Judul Bagian) -->
            <Stepper 
                v-if="visibleSections && visibleSections.length > 0"
                :sections="visibleSections"
                :active-index="activeSectionIndex"
                :completed-indices="completedSectionIndices"
                :is-section-completed="isSectionCompleted"
                :is-line-completed="isLineCompleted"
                :can-select-section="canSelectSection"
                @select-section="setSection"
            />
        </header>

        <!-- Area Konten Formulir Utama (Ukuran Sama Persis Kuesioner Universitas) -->
        <main class="box-border min-w-0 w-full max-w-6xl flex-1 overflow-x-hidden px-3 py-4 mx-auto mt-3 sm:px-4 sm:mt-6 md:px-6 md:mt-8 lg:px-8 xl:max-w-7xl">
            
            <!-- Pesan jika prodi belum memiliki pertanyaan -->
            <div v-if="questions.length === 0" class="bg-white rounded-3xl p-8 sm:p-12 text-center shadow-xs border border-gray-100">
                <div class="w-16 h-16 rounded-2xl bg-emerald-50 text-[#005B3C] flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"></path></svg>
                </div>
                <h3 class="text-xl font-bold text-gray-800 mb-2">Belum Ada Kuesioner Khusus Prodi</h3>
                <p class="text-gray-500 text-sm max-w-md mx-auto mb-6">
                    Program Studi <strong>{{ prodi?.nama_prodi || 'Anda' }}</strong> saat ini belum menambahkan butir kuesioner khusus. Anda dapat kembali ke Dashboard atau menyelesaikan kuesioner universitas lainnya.
                </p>
                <Link 
                    href="/alumni/dashboard" 
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#005B3C] hover:bg-[#00482f] text-white font-bold rounded-xl shadow-xs transition-all text-sm"
                >
                    ← Kembali ke Dashboard
                </Link>
            </div>

            <!-- Form Kuesioner Aktif Berdasarkan Seksi -->
            <div v-else-if="currentSection">
                <!-- 3. Banner Judul Bagian -->
                <Banner 
                    :current-index="activeSectionIndex"
                    :total-sections="visibleSections.length"
                    :title="currentSection.title"
                    :description="currentSection.description"
                    :badge-text="'Kuesioner Program Studi ' + (prodi?.nama_prodi || '')"
                    :percentage="progressStats.percentage"
                    :progress-label="progressStats.answered + ' / ' + progressStats.total + ' Soal'"
                    :has-changes="hasAnswerChanges"
                    @reset="resetAnswersToInitial"
                />

                <!-- Konten Formulir Seksi Aktif -->
                <form @submit.prevent="handleNextOrSubmit" class="space-y-4 sm:space-y-6">
                    <template v-for="(block, bIdx) in currentSectionBlocks" :key="'block_' + bIdx">
                        <!-- 1. Matriks Tabel Skala 1-5 -->
                        <TabelMatriksProdi 
                            v-if="block.type === 'rating_matrix'"
                            :block="block"
                            :form="form"
                            :is-question-answered="isQuestionAnswered"
                            :get-matrix-scale-options="getMatrixScaleOptions"
                        />

                        <!-- 2. Kartu Pertanyaan Standar & Header -->
                        <KartuPertanyaanProdi 
                            v-else
                            :block="block"
                            :form="form"
                        />
                    </template>

                    <!-- Tombol Navigasi Desktop & Mobile (< Kembali & > Lanjut/Selesai) -->
                    <Navigasi 
                        :active-section-index="activeSectionIndex"
                        :total-sections="visibleSections.length"
                        :is-last-visible-section="isLastSection"
                        :is-processing="form.processing"
                        @prev="prevSection"
                    />
                </form>
            </div>
        </main>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    display: none;
}
.custom-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
