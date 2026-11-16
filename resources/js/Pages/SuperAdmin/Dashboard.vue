<script setup>
/**
 * Halaman Dashboard Super Administrator (Diselaraskan dengan RoleDashboard).
 * File: resources/js/Pages/SuperAdmin/Dashboard.vue
 *
 * Menggunakan arsitektur layout dan komponen bersama RoleDashboard,
 * dengan hak akses penuh superadmin (Akses Cepat master perusahaan, verifikasi, dsb).
 */
import RoleDashboard from '@/Components/RoleDashboard.vue';

defineProps({
    user: { type: Object, default: () => ({}) },
    availableYears: { type: Array, default: () => [] },
    selectedYear: { type: [String, Number], default: () => new Date().getFullYear() },
    dashboardStats: { type: Object, default: () => ({}) },
    completionMetrics: { type: Object, default: () => ({}) },
    companySummary: { type: Object, default: () => ({}) },
    prodiSummaries: { type: Array, default: () => [] },
    recentAlumni: { type: Array, default: () => [] },
    sectionsList: { type: Array, default: () => [] },
});
</script>

<template>
    <!-- Scope role superadmin dengan tata letak dan fungsi yang seragam -->
    <RoleDashboard role="superadmin" v-bind="$props" />
</template>
