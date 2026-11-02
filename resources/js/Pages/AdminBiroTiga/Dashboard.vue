<script setup>
// Halaman wrapper dashboard Biro 3; UI bersama berada di RoleDashboard.
import RoleDashboard from '@/Components/RoleDashboard.vue';

// Props Inertia diteruskan ke dashboard universitas tanpa menambahkan route yang tidak tersedia.
defineProps({
    user: { type: Object, default: () => ({}) },
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
    <!-- Scope role dikunci pada admin_biro3 agar data universitas tetap terbaca konsisten. -->
    <RoleDashboard role="admin_biro3" v-bind="$props" />
</template>
