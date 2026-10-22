<script setup>
import AdminSidebar from '../../../components/AdminSidebar.vue';

defineProps({
    user: { type: Object, default: () => ({}) },
    prodi: { type: Object, default: () => ({}) },
    activeMenu: { type: String, default: '' },
});
</script>

<template>
    <AdminSidebar role="admin_prodi" :user="user" :prodi="prodi" :active-menu="activeMenu" />
</template>
