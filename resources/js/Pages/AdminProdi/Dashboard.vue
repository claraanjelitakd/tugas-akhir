<script setup>
// Halaman wrapper dashboard Admin Prodi; UI bersama berada di RoleDashboard.
import RoleDashboard from '@/Components/RoleDashboard.vue';

// Props Inertia diteruskan tanpa mengubah kontrak data yang sudah dipakai test lama.
defineProps({
    user: { type: Object, default: () => ({}) },
    prodi: { type: Object, default: () => ({}) },
    availableYears: { type: Array, default: () => [] },
    selectedYear: { type: [String, Number], default: () => new Date().getFullYear() },
    dashboardStats: { type: Object, default: () => ({}) },
    completionMetrics: { type: Object, default: () => ({}) },
    prodiSummaries: { type: Array, default: () => [] },
    recentAlumni: { type: Array, default: () => [] },
    sectionsList: { type: Array, default: () => [] },
});
</script>

<template>
    <!-- Scope role dikunci pada admin_prodi agar sidebar dan route tidak tertukar. -->
    <RoleDashboard role="admin_prodi" v-bind="$props" />
</template>
