<script setup>
// Halaman wrapper dashboard Admin Fakultas; UI bersama berada di RoleDashboard.
import RoleDashboard from '@/Components/RoleDashboard.vue';

// Props Inertia diteruskan ke komponen dashboard bersama dengan scope fakultas.
defineProps({
    user: { type: Object, default: () => ({}) },
    fakultas: { type: Object, default: () => ({}) },
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
    <!-- Scope role dikunci pada admin_fakultas agar sidebar dan route tidak tertukar. -->
    <RoleDashboard role="admin_fakultas" v-bind="$props" />
</template>
