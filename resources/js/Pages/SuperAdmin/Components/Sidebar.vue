<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AdminSidebar from '../../../components/AdminSidebar.vue';

const props = defineProps({
    user: { type: Object, default: () => ({}) },
    activeMenu: { type: String, default: '' },
    role: { type: String, default: '' },
});

const page = usePage();

const currentRole = computed(() => {
    if (props.role) return props.role;
    const path = page.url || '';
    if (path.startsWith('/prodi/') || path.startsWith('/prodi')) return 'admin_prodi';
    if (path.startsWith('/fakultas/') || path.startsWith('/fakultas')) return 'admin_fakultas';
    if (path.startsWith('/biro3/') || path.startsWith('/biro3')) return 'admin_biro3';
    return page.props.auth?.user?.role || props.user?.role || 'superadmin';
});
</script>

<template>
    <AdminSidebar :role="currentRole" :user="user" :active-menu="activeMenu" />
</template>
