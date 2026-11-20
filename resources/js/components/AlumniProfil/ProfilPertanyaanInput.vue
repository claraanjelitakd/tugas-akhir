<script setup>
import { computed } from 'vue';
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

// Konversi format profil → format KartuPertanyaan
// question.tipe → subpertanyaan.type
// question.opsi[].label → subpertanyaan.detils[].option_text
const subpertanyaan = computed(() => ({
    id: props.question.id,
    type: props.question.tipe,
    kode_pertanyaan: props.question.kode_pertanyaan ?? '',
    teks_pertanyaan: props.question.teks_pertanyaan,
    wajib: !!(props.question.pivot_wajib || props.question.wajib),
    keterangan: props.question.keterangan ?? '',
    detils: (props.question.opsi ?? []).map((opt) => ({
        id: opt.id,
        option_text: opt.label ?? opt.option_text ?? '',
        kode_opsi: opt.kode_opsi ?? opt.id,
        is_other: opt.is_other ?? false,
    })),
}));

// KartuPertanyaan mutates form.answers[id] secara langsung.
// Pakai profileAnswers sebagai answers agar state tersimpan ke form.profile_answers.
const fakeForm = computed(() => ({ answers: props.profileAnswers }));
</script>

<template>
    <KartuPertanyaan
        :subpertanyaan="subpertanyaan"
        :form="fakeForm"
        :is-visible="true"
    />
</template>
