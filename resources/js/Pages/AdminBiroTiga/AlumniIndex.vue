<!--
  Project: SERU (Sistem Ekosistem Rekam Jejak Alumni) - Tracer Study Universitas Kristen Duta Wacana (UKDW)
  Folder: resources/js/Pages/AdminBiroTiga/
  File: AlumniIndex.vue
  Deskripsi: Halaman direktori mahasiswa & tracer study untuk peran Biro 3 (Kemahasiswaan & Alumni).
-->
<script setup>
import AlumniDirectoryShared from '@/Pages/Alumni/Components/AlumniDirectoryShared.vue';

defineProps({
    user: Object,
    alumnis: Array,
    daftarTahun: Array,
    daftarTarget: Array,
    prodis: Array,
    filters: Object,
    stats: Object,
    pagination: Object,
    filterOptions: Object,
});
</script>

<template>
    <AlumniDirectoryShared
        v-bind="$props"
        role="admin_biro3"
        role-title="Biro 3 Kemahasiswaan & Alumni"
        prefix="biro3"
        :can-export-zip="true"
        :can-blast-email="true"
        :show-prodi-filter="true"
    />
</template>
