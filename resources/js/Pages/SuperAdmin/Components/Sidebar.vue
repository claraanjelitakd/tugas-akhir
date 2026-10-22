<script setup>
import AdminSidebar from '../../../components/AdminSidebar.vue';

defineProps({
    user: { type: Object, default: () => ({}) },
    activeMenu: { type: String, default: '' },
});
</script>

<template>
    <AdminSidebar role="superadmin" :user="user" :active-menu="activeMenu" />
</template>
