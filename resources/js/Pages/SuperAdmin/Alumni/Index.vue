<!--
  Project: SERU (Sistem Ekosistem Rekam Jejak Alumni) - Tracer Study Universitas Kristen Duta Wacana (UKDW)
  Folder: resources/js/Pages/SuperAdmin/Alumni/
  File: Index.vue
  Deskripsi: Halaman direktori mahasiswa & tracer study untuk peran Super Administrator.
-->
<script setup>
import AlumniDirectoryShared from '@/Pages/Alumni/Components/AlumniDirectoryShared.vue';

defineProps({
    alumnis: Array,
    daftarTahun: Array,
    daftarTarget: Array,
    prodis: Array,
    filters: Object,
    stats: Object,
    pagination: Object,
});
</script>

<template>
    <AlumniDirectoryShared
        v-bind="$props"
        role="superadmin"
        role-title="Super Administrator"
        prefix="superadmin"
        :can-export-zip="true"
        :can-blast-email="true"
        :show-prodi-filter="true"
    />
</template>
