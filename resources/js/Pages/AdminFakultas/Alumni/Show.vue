<!--
  Project: SERU (Sistem Ekosistem Rekam Jejak Alumni) - Tracer Study Universitas Kristen Duta Wacana (UKDW)
  Folder: resources/js/Pages/AdminFakultas/Alumni/
  File: Show.vue
  Deskripsi: Halaman detail audit kuesioner tracer study dan profil alumni untuk peran Admin Fakultas.
-->
<script setup>
import AlumniDetailShared from '@/Pages/Alumni/Components/AlumniDetailShared.vue';

defineProps({
    user: Object,
    fakultas: Object,
    alumni: Object,
    evaluasi: Object,
    sections: Array,
    prodiSections: Array,
    prodiEvaluasi: Object,
    formData: Object,
    provinces: Array,
    kabupatens: Array,
    companies: Array,
    negaras: Array,
    refOptions: Object,
    linkedinSyncResult: Object,
    linkedinTraceMapping: Array,
    linkedinHistory: Array,
    evaluasiAtasan: Object,
    pertanyaanEvaluasiAtasan: Array,
});
</script>

<template>
    <AlumniDetailShared
        v-bind="$props"
        role="admin_fakultas"
        role-title="Admin Fakultas"
        prefix="fakultas"
    />
</template>
