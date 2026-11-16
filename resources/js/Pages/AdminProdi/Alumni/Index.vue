<!--
  Project: SERU (Sistem Ekosistem Rekam Jejak Alumni) - Tracer Study Universitas Kristen Duta Wacana (UKDW)
  Folder: resources/js/Pages/AdminProdi/Alumni/
  File: Index.vue
  Deskripsi: Halaman direktori mahasiswa & tracer study untuk peran Admin Program Studi.
-->
<script setup>
import AlumniDirectoryShared from '@/Pages/Alumni/Components/AlumniDirectoryShared.vue';

defineProps({
    user: Object,
    prodi: Object,
    alumnis: Array,
    daftarTahun: Array,
    daftarTarget: Array,
    filters: Object,
    stats: Object,
    pagination: Object,
    filterOptions: Object,
});
</script>

<template>
    <AlumniDirectoryShared
        v-bind="$props"
        role="admin_prodi"
        role-title="Admin Program Studi"
        prefix="prodi"
        :can-export-zip="false"
        :can-blast-email="false"
        :show-prodi-filter="false"
    />
</template>
