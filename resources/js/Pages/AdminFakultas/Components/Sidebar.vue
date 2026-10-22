<script setup>
import AdminSidebar from '../../../components/AdminSidebar.vue';

defineProps({
    user: { type: Object, default: () => ({}) },
    fakultas: { type: Object, default: () => ({}) },
    activeMenu: { type: String, default: '' },
});
</script>

<template>
    <AdminSidebar role="admin_fakultas" :user="user" :fakultas="fakultas" :active-menu="activeMenu" />
</template>
