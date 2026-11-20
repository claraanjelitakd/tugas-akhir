<script setup>
import { computed, reactive, watch } from 'vue';
import KartuPertanyaan from '@/Pages/Alumni/Components/Kuesioner/KartuPertanyaan.vue';

const props = defineProps({
    question: {
        type: Object,
        required: true,
    },
    profileAnswers: {
        type: Object,
        required: true,
    },
});

const qId = props.question.id;
const needsObject = ['radio_text', 'radio_input'].includes(props.question.tipe);

// Inisialisasi nilai awal berdasarkan tipe pertanyaan.
// radio_text/radio_input membutuhkan struktur objek { selected, input, inputs }
// karena KartuPertanyaan akan akses .selected secara langsung di template.
const existing = props.profileAnswers[qId];
const initialValue = needsObject
    ? (existing && typeof existing === 'object'
        ? existing
        : { selected: typeof existing === 'string' ? existing : '', input: '', inputs: {} })
    : (existing ?? '');

// Setiap komponen punya reactive answers sendiri agar tidak terjadi cross-contamination
// antar pertanyaan yang berbeda ketika salah satu dijawab.
const localAnswers = reactive({ [qId]: initialValue });

// Sync perubahan lokal ke form.profile_answers parent (untuk pengiriman form)
watch(
    () => localAnswers[qId],
    (newVal) => {
        props.profileAnswers[qId] = newVal;
    },
    { deep: true }
);

// Sync perubahan dari parent (misal setelah syncProfileResponses) ke lokal
watch(
    () => props.profileAnswers[qId],
    (newVal) => {
        if (newVal !== undefined && JSON.stringify(newVal) !== JSON.stringify(localAnswers[qId])) {
            localAnswers[qId] = newVal;
        }
    },
    { deep: true }
);

// Konversi format profil → format KartuPertanyaan.
// KartuPertanyaan membaca teks pertanyaan dari field 'subpertanyaan', bukan 'teks_pertanyaan'.
const subpertanyaan = computed(() => ({
    id: qId,
    type: props.question.tipe,
    kode_pertanyaan: props.question.kode_pertanyaan ?? '',
    subpertanyaan: props.question.teks_pertanyaan,
    wajib: !!(props.question.pivot_wajib || props.question.wajib),
    keterangan: props.question.keterangan ?? '',
    detils: (props.question.opsi ?? []).map((opt) => ({
        id: opt.id,
        option_text: opt.label ?? opt.option_text ?? '',
        kode_opsi: opt.kode_opsi ?? opt.id,
        is_other: opt.is_other ?? false,
    })),
}));

const fakeForm = { answers: localAnswers };
</script>

<template>
    <KartuPertanyaan
        :subpertanyaan="subpertanyaan"
        :form="fakeForm"
        :is-visible="true"
    />
</template>
