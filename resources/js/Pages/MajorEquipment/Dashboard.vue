<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, Link } from '@inertiajs/vue3';
defineProps({ summary: Object, categories: Array, recent: Array });
</script>
<template>
    <Head title="Miri Inventory Dashboard" />
    <AuthenticatedLayout>
        <PageHeader title="Miri Inventory" description="Overview of Miri inventory records across all categories.">
            <Link class="btn bg-[#4f9f4a] text-white" :href="route('major-equipment.index')">Open Register</Link>
        </PageHeader>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div v-for="card in [{label:'Total Records',value:summary.total},{label:'In Use',value:summary.in_use},{label:'Standby',value:summary.standby},{label:'Under Repair',value:summary.under_repair},{label:'Damaged',value:summary.damaged}]" :key="card.label" class="rounded-2xl border border-[#d8e7d4] bg-white p-5 shadow-sm"><p class="text-xs uppercase tracking-[0.18em] text-[#7f9a7a]">{{ card.label }}</p><p class="mt-2 text-3xl font-bold text-[#234222]">{{ card.value }}</p></div>
        </div>
        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-6 shadow-sm"><h2 class="text-lg font-semibold text-[#234222]">Categories</h2><div class="mt-4 space-y-3"><div v-for="category in categories" :key="category.category" class="flex items-center justify-between rounded-xl bg-[#f7fbf5] px-4 py-3"><span>{{ category.category || 'Uncategorised' }}</span><strong>{{ category.total }}</strong></div><p v-if="categories.length === 0" class="text-sm text-slate-500">No records imported yet.</p></div></section>
            <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-6 shadow-sm"><h2 class="text-lg font-semibold text-[#234222]">Recently Updated</h2><div class="mt-4 space-y-3"><Link v-for="item in recent" :key="item.id" :href="route('major-equipment.show', item.id)" class="block rounded-xl bg-[#f7fbf5] px-4 py-3 hover:bg-[#eef8ea]"><p class="font-semibold">{{ item.tag_no || 'Untagged' }} · {{ item.description || '-' }}</p><p class="mt-1 text-xs text-slate-500">{{ item.section_1 }} / {{ item.section_2 }} · {{ item.status || '-' }} · {{ item.current_location || '-' }}</p></Link><p v-if="recent.length === 0" class="text-sm text-slate-500">No records imported yet.</p></div></section>
        </div>
    </AuthenticatedLayout>
</template>
