<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
const props = defineProps({ rental: Object });
const canEdit = computed(() => usePage().props.auth?.user?.can?.assets_edit);
</script>
<template><Head title="Rental Details" /><AuthenticatedLayout><PageHeader title="Rental Details" description="Issue-out, received-backload, and return-to-supplier record." />
        <p class="my-4 text-sm">Company: <strong>{{ rental.company || 'Not assigned' }}</strong></p><section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-6 shadow-sm"><div class="grid gap-5 md:grid-cols-3"><div v-for="(value,key) in rental" :key="key" v-if="!['id','branch_id','created_at','updated_at','active'].includes(key)"><p class="text-xs font-bold uppercase text-[#60745d]">{{ key.replaceAll('_',' ') }}</p><p class="mt-1">{{ value || '-' }}</p></div></div><div class="mt-6 flex flex-wrap gap-3"><a class="btn bg-[#234222] text-white" :href="route('miri-rental.pdf', rental.id)">Download registration form</a><Link v-if="canEdit" class="btn bg-[#4f9f4a] text-white" :href="route('miri-rental.edit', rental.id)">Edit rental</Link><Link class="btn" :href="route('miri-rental.index')">Back to Rental Register</Link></div></section></AuthenticatedLayout></template>
