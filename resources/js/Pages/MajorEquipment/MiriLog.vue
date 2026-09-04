<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ summary: Object, logs: Object });
const search = ref('');
const event = ref('All');
const filteredLogs = computed(() => (props.logs?.data ?? []).filter((log) => {
    const text = `${log.user} ${log.module} ${log.event} ${log.summary}`.toLowerCase();
    return (!search.value || text.includes(search.value.toLowerCase())) && (event.value === 'All' || log.event === event.value);
}));
const eventLabel = (value) => ({ created: 'Added', updated: 'Changed', deleted: 'Deleted', imported: 'Imported', signed: 'Signed', login: 'Signed in', logout: 'Signed out' }[value] || value);
const eventTone = (value) => ({ created: 'bg-emerald-100 text-emerald-700', updated: 'bg-blue-100 text-blue-700', deleted: 'bg-rose-100 text-rose-700', imported: 'bg-violet-100 text-violet-700', login: 'bg-cyan-100 text-cyan-700', logout: 'bg-slate-100 text-slate-600' }[value] || 'bg-slate-100 text-slate-600');
</script>

<template>
    <Head title="Miri Log" />
    <AuthenticatedLayout>
        <section class="miri-log-shell space-y-6">
            <header class="relative isolate overflow-hidden rounded-[1.75rem] bg-[linear-gradient(120deg,#1e3a2a_0%,#236b55_58%,#155e75_100%)] px-5 py-7 text-white shadow-[0_24px_70px_rgba(6,78,59,.2)] sm:px-8 sm:py-9 lg:px-10"><div class="pointer-events-none absolute -right-20 -top-28 h-80 w-80 rounded-full bg-cyan-300/20 blur-3xl"/><div class="relative flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-xs font-semibold uppercase tracking-[.24em] text-cyan-200">Miri Inventory Control</p><h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">Miri Log</h1><p class="mt-3 max-w-2xl text-sm leading-6 text-white/70 sm:text-base">A read-only record of staff activity in the Miri inventory system.</p></div><Link :href="route('major-equipment.dashboard')" class="inline-flex w-fit rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-emerald-900 shadow-sm hover:bg-emerald-50">Back to dashboard</Link></div></header>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:gap-4"><div v-for="card in [{label:'Total events',value:summary.total,color:'bg-slate-800'},{label:'Today',value:summary.today,color:'bg-cyan-500'},{label:'Added / imported',value:summary.created,color:'bg-emerald-500'},{label:'Changed / deleted',value:summary.changed,color:'bg-amber-500'}]" :key="card.label" class="relative overflow-hidden rounded-2xl border border-[#d8e7d4] bg-white p-4 shadow-[0_8px_28px_rgba(39,89,45,.06)]"><div class="absolute inset-x-0 top-0 h-1" :class="card.color"/><p class="text-[10px] font-extrabold uppercase tracking-[.12em] text-slate-500">{{ card.label }}</p><p class="mt-2 text-2xl font-black text-slate-800">{{ card.value }}</p></div></div>

            <article class="overflow-hidden rounded-[1.5rem] border border-[#d9e8d5] bg-white shadow-[0_12px_35px_rgba(39,89,45,.07)]"><div class="border-b border-slate-100 px-5 py-5 sm:px-6"><div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-[10px] font-extrabold uppercase tracking-[.22em] text-emerald-600">Activity history</p><h2 class="mt-1 text-xl font-black tracking-tight text-slate-800">Staff actions</h2><p class="mt-1 text-xs text-slate-500">This page only displays the log. No records can be changed here.</p></div><div class="flex gap-3"><input v-model.trim="search" type="search" placeholder="Search staff or action" class="w-full rounded-xl border-slate-200 text-sm sm:w-64"/><select v-model="event" class="rounded-xl border-slate-200 text-sm"><option>All</option><option value="login">Signed in</option><option value="logout">Signed out</option><option value="created">Added</option><option value="updated">Changed</option><option value="deleted">Deleted</option><option value="imported">Imported</option></select></div></div></div><div class="divide-y divide-slate-100"><div v-for="log in filteredLogs" :key="log.id" class="flex gap-4 px-5 py-4 sm:px-6"><div class="mt-1 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#eef8ea] text-sm font-black text-[#2f7d32]">{{ log.user?.slice(0, 1)?.toUpperCase() || 'S' }}</div><div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-2"><strong class="text-sm text-slate-800">{{ log.user }}</strong><span class="rounded-full px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-wide" :class="eventTone(log.event)">{{ eventLabel(log.event) }}</span><span class="text-xs text-slate-400">{{ log.module }}</span></div><p class="mt-1 text-sm text-slate-600">{{ log.summary }}</p><p class="mt-1 text-xs text-slate-400">{{ log.created_at }}<span v-if="log.ip_address"> · {{ log.ip_address }}</span></p></div></div><div v-if="filteredLogs.length === 0" class="px-6 py-16 text-center text-sm text-slate-500">No activity logs match your search.</div></div><div v-if="logs?.last_page > 1" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-4 sm:px-6"><span class="text-xs text-slate-500">Showing {{ logs.from }}–{{ logs.to }} of {{ logs.total }} events</span><div class="flex gap-2"><Link v-if="logs.prev_page_url" :href="logs.prev_page_url" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600">Previous</Link><Link v-if="logs.next_page_url" :href="logs.next_page_url" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600">Next</Link></div></div></article>
        </section>
    </AuthenticatedLayout>
</template>

<style scoped>
.miri-log-shell { font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif; font-feature-settings: "kern" 1, "tnum" 1; }
</style>
