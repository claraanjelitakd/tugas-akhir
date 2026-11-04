<!--
  Project: SERU (Sistem Ekosistem Rekam Jejak Alumni) - Tracer Study Universitas Kristen Duta Wacana (UKDW)
  Folder: resources/js/Pages/AdminProdi/Alumni/
  File: Show.vue
  Deskripsi: Halaman detail audit kuesioner tracer study dan profil alumni untuk peran Admin Program Studi.
-->
<script setup>
import AlumniDetailShared from '@/Pages/Alumni/Components/AlumniDetailShared.vue';

defineProps({
    user: Object,
    prodi: Object,
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
        role="admin_prodi"
        role-title="Admin Program Studi"
        prefix="prodi"
    />
</template>
