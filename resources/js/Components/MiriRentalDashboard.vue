<script setup>
import { computed, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({ dashboard: { type: Object, required: true } });
const total = computed(() => Number(props.dashboard.summary?.total ?? 0));
const project = ref('');
const selectedProject = computed(() => props.dashboard.projects.find(item => item.project === project.value));
const overviewTotal = computed(() => selectedProject.value?.total ?? total.value);
const overviewStatuses = computed(() => selectedProject.value?.status ?? props.dashboard.status);
const percent = value => overviewTotal.value ? Math.round(Number(value) / overviewTotal.value * 100) : 0;
const projectPage = ref(1);
const pageCount = computed(() => Math.max(1, Math.ceil(props.dashboard.projects.length / 6)));
const visibleProjects = computed(() => props.dashboard.projects.slice((projectPage.value - 1) * 6, projectPage.value * 6));
const series = [{ key: 'on_hire', label: 'On Hire', color: '#10b981' }, { key: 'off_hire', label: 'Off Hire', color: '#3b82f6' }, { key: 'overdue', label: 'Overdue', color: '#f43f5e' }];
const chartMax = computed(() => Math.max(1, ...props.dashboard.projects.flatMap(item => series.map(entry => item[entry.key]))));
const statusColor = label => ({ 'On Hire': '#10b981', 'Off Hire': '#3b82f6', 'Returned to Supplier': '#64748b', 'Overdue': '#f43f5e', 'Issued': '#06b6d4', 'Received Backload': '#8b5cf6' })[label] || '#94a3b8';
watch(() => props.dashboard, () => { project.value = ''; projectPage.value = 1; });
const locationProject = ref('');
const locationPage = ref(1);
const locations = computed(() => {
    const grouped = new Map();
    for (const item of props.dashboard.locations) {
        if (locationProject.value && item.project !== locationProject.value) continue;
        const row = grouped.get(item.label) ?? { label: item.label, value: 0, on_hire: 0, off_hire: 0, overdue: 0 };
        for (const key of ['value', 'on_hire', 'off_hire', 'overdue']) row[key] += Number(item[key]);
        grouped.set(item.label, row);
    }
    return [...grouped.values()].sort((a, b) => b.value - a.value || a.label.localeCompare(b.label));
});
const locationTotal = computed(() => locations.value.reduce((sum, item) => sum + item.value, 0));
const locationMax = computed(() => Math.max(1, ...locations.value.map(item => item.value)));
const locationPageCount = computed(() => Math.max(1, Math.ceil(locations.value.length / 8)));
const visibleLocations = computed(() => locations.value.slice((locationPage.value - 1) * 8, locationPage.value * 8));
watch(locationProject, () => locationPage.value = 1);
watch(() => props.dashboard, () => { locationProject.value = ''; locationPage.value = 1; });
const formatDate = (value) => value ? new Date(`${value}T00:00:00`).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '-';
const dueLabel = (days) => days === 0 ? 'Due today' : `${days} days left`;
</script>

<template>
    <section class="rental-dashboard space-y-6">
        <header class="relative isolate overflow-hidden rounded-[1.75rem] bg-[linear-gradient(120deg,#064e3b_0%,#0f766e_58%,#155e75_100%)] px-5 py-6 text-white shadow-[0_24px_70px_rgba(6,78,59,.22)] sm:px-8 sm:py-8 lg:px-10"><div class="pointer-events-none absolute -right-16 -top-24 h-72 w-72 rounded-full bg-cyan-300/20 blur-2xl"/><div class="relative flex flex-col justify-between gap-6 lg:flex-row lg:items-end"><div><p class="text-xs font-semibold uppercase tracking-[.24em] text-emerald-200">Miri Inventory</p><h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">Rental dashboard</h1><p class="mt-3 max-w-2xl text-sm leading-6 text-white/70 sm:text-base">Track hired equipment, return dates, locations, suppliers, and rental movement at a glance.</p></div><Link :href="route('miri-rental.index')" class="inline-flex w-fit rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-emerald-900 shadow-sm hover:bg-emerald-50">Open rental register</Link></div></header>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6 lg:gap-4"><div v-for="card in [{label:'Total rentals',value:dashboard.summary.total,color:'bg-slate-800'},{label:'On hire',value:dashboard.summary.on_hire,color:'bg-emerald-500'},{label:'Issued',value:dashboard.summary.issued,color:'bg-blue-500'},{label:'Backloaded',value:dashboard.summary.backload,color:'bg-violet-500'},{label:'Due in 30 days',value:dashboard.summary.due_30_days,color:'bg-amber-500'},{label:'Overdue',value:dashboard.summary.overdue,color:'bg-rose-500'}]" :key="card.label" class="relative overflow-hidden rounded-2xl border border-[#d8e7d4] bg-white p-4 shadow-[0_8px_28px_rgba(39,89,45,.06)]"><div class="absolute inset-x-0 top-0 h-1" :class="card.color"/><p class="text-[10px] font-extrabold uppercase tracking-[.12em] text-slate-500">{{ card.label }}</p><p class="mt-2 text-2xl font-black text-slate-800">{{ card.value }}</p></div></div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:gap-5"><article class="rounded-[1.5rem] border border-[#d9e8d5] bg-white p-5 shadow-sm sm:p-6 lg:col-span-5">
                <div class="flex items-start justify-between gap-4"><div><p class="text-xs font-semibold uppercase text-blue-500">Rental health</p><h2 class="mt-1 text-xl font-bold text-slate-800">Status overview</h2></div><span class="text-right text-xs text-slate-400"><strong class="block text-lg text-slate-700">{{ overviewTotal }}</strong>rental records</span></div>
                <label class="mt-4 block text-sm font-semibold text-slate-600">Project / Contract
                    <select v-model="project" class="mt-2 w-full rounded-xl border-slate-200"><option value="">All projects</option><option v-for="item in dashboard.projects" :key="item.project" :value="item.project">{{ item.project }}</option></select>
                </label>
                <div class="mt-5 space-y-3"><div v-for="item in overviewStatuses" :key="item.label" class="flex items-center gap-3"><span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: statusColor(item.label) }"/><span class="min-w-0 flex-1 text-sm font-semibold text-slate-600">{{ item.label }}</span><div class="hidden h-2 w-20 overflow-hidden rounded-full bg-slate-100 sm:block"><span class="block h-full" :style="{ width: `${percent(item.value)}%`, backgroundColor: statusColor(item.label) }"/></div><strong class="text-right text-sm text-slate-800">{{ item.value }} <small class="font-normal text-slate-500">({{ percent(item.value) }}%)</small></strong></div></div>
                <p class="mt-4 text-xs text-slate-500">Counts represent rental records. Overdue is an alert that can overlap On Hire or Issued. Off Hire, Received Backload and Returned to Supplier are excluded from overdue.</p>
            </article>
            <article class="rounded-[1.5rem] border border-[#d9e8d5] bg-white p-5 shadow-sm sm:p-6 lg:col-span-7">
                <p class="text-xs font-semibold uppercase text-amber-600">Project comparison</p><h2 class="mt-1 text-xl font-bold text-slate-800">Rentals by project / contract</h2>
                <p class="mt-2 text-xs text-slate-500">On Hire, Off Hire and Overdue record counts for each project. Overdue may overlap active statuses; these bars should not be added together.</p>
                <div class="mt-4 flex flex-wrap gap-4 text-xs"><span v-for="entry in series" :key="entry.key" class="inline-flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: entry.color }"/>{{ entry.label }}</span></div>
                <div class="mt-5 space-y-5"><div v-for="item in visibleProjects" :key="item.project"><div class="mb-2 flex justify-between gap-3 text-sm"><strong class="break-words text-slate-700">{{ item.project }}</strong><span class="shrink-0 text-slate-500">{{ item.total }} records</span></div><div v-for="entry in series" :key="entry.key" class="mt-2 flex items-center gap-3"><span class="w-16 shrink-0 text-xs text-slate-600">{{ entry.label }}</span><div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100"><span class="block h-full rounded-full" :style="{ width: `${item[entry.key] / chartMax * 100}%`, backgroundColor: entry.color }"/></div><strong class="w-10 text-right text-xs text-slate-700">{{ item[entry.key] }}</strong></div></div><p v-if="!dashboard.projects.length" class="text-sm text-slate-500">No rental records available.</p></div>
                <nav v-if="pageCount > 1" class="mt-5 flex items-center justify-between gap-3 border-t pt-4" aria-label="Rental project pages"><span class="text-xs text-slate-500">Page {{ projectPage }} of {{ pageCount }}</span><div class="flex gap-2"><button type="button" class="btn btn-sm" :disabled="projectPage === 1" @click="projectPage--">Previous</button><button type="button" class="btn btn-sm" :disabled="projectPage === pageCount" @click="projectPage++">Next</button></div></nav>
            </article><article class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6 lg:col-span-5">
                <div class="flex items-start justify-between gap-3"><div><p class="text-xs font-semibold uppercase text-violet-500">Deployment footprint</p><h2 class="mt-1 text-xl font-bold text-slate-800">Rental locations</h2></div><span class="text-right text-xs text-slate-500"><strong class="block text-lg text-slate-800">{{ locationTotal }}</strong>rental records</span></div>
                <label class="mt-4 block text-sm font-semibold text-slate-600">Project / Contract
                    <select v-model="locationProject" class="mt-2 w-full rounded-xl border-slate-200"><option value="">All projects</option><option v-for="item in dashboard.projects" :key="item.project" :value="item.project">{{ item.project }}</option></select>
                </label>
                <p class="mt-3 text-xs text-slate-500">Totals include all statuses. Overdue can overlap On Hire or Issued; completed rentals are excluded from overdue.</p>
                <div class="mt-5 space-y-5">
                    <div v-for="item in visibleLocations" :key="item.label">
                        <div class="mb-2 flex justify-between gap-3 text-sm"><strong class="break-words text-slate-600">{{ item.label }}</strong><strong class="shrink-0 text-slate-800">{{ item.value }} records</strong></div>
                        <div class="h-2 overflow-hidden rounded-full bg-slate-100"><span class="block h-full rounded-full bg-gradient-to-r from-violet-500 to-blue-500" :style="{ width: `${item.value / locationMax * 100}%` }"/></div>
                        <div class="mt-3 flex flex-wrap gap-x-4 gap-y-2 text-xs"><span v-for="entry in series" :key="entry.key" class="inline-flex items-center gap-2"><span class="h-2 w-2 rounded-full" :style="{ backgroundColor: entry.color }"/><span class="text-slate-600">{{ entry.label }}: <strong class="text-slate-800">{{ item[entry.key] }}</strong></span></span></div>
                    </div>
                    <p v-if="!locations.length" class="text-sm text-slate-500">No rental locations recorded for this selection.</p>
                </div>
                <nav v-if="locationPageCount > 1" class="mt-5 flex items-center justify-between gap-3 border-t pt-4" aria-label="Rental location pages"><span class="text-xs text-slate-500">Page {{ locationPage }} of {{ locationPageCount }} / {{ locations.length }} locations</span><div class="flex gap-2"><button type="button" class="btn btn-sm" :disabled="locationPage === 1" @click="locationPage--">Previous</button><button type="button" class="btn btn-sm" :disabled="locationPage === locationPageCount" @click="locationPage++">Next</button></div></nav>
            </article><article class="rounded-[1.5rem] border border-[#d9e8d5] bg-white p-5 shadow-[0_12px_35px_rgba(39,89,45,.07)] sm:p-6 lg:col-span-7"><div><p class="text-[10px] font-extrabold uppercase tracking-[.22em] text-emerald-600">Action queue</p><h2 class="mt-1 text-xl font-black tracking-tight text-slate-800">Upcoming rental returns</h2></div><div class="mt-5 space-y-2"><Link v-for="item in dashboard.upcoming" :key="item.id" :href="route('miri-rental.show', item.id)" class="flex items-center gap-3 rounded-xl bg-[#f7fbf5] px-4 py-3 hover:bg-[#eef8ea]"><span class="min-w-0 flex-1"><strong class="block truncate text-sm text-[#234222]">{{ item.identifier || 'Unidentified' }} · {{ item.description || 'Unnamed rental' }}</strong><small class="block truncate text-xs text-slate-500">{{ item.location || 'Location not recorded' }} · {{ item.supplier || 'Supplier not recorded' }}</small></span><span class="shrink-0 text-right"><strong class="block text-xs" :class="item.days_remaining <= 30 ? 'text-amber-600' : 'text-emerald-600'">{{ dueLabel(item.days_remaining) }}</strong><small class="text-[11px] text-slate-400">{{ formatDate(item.due_date) }}</small></span></Link><p v-if="!dashboard.upcoming.length" class="text-sm text-slate-500">No upcoming rental due dates recorded.</p></div></article></div>

        <article class="rounded-[1.5rem] border border-[#d9e8d5] bg-white p-5 shadow-[0_12px_35px_rgba(39,89,45,.07)] sm:p-6"><div class="flex items-start justify-between gap-4"><div><p class="text-[10px] font-extrabold uppercase tracking-[.22em] text-cyan-600">Latest activity</p><h2 class="mt-1 text-xl font-black tracking-tight text-slate-800">Recently updated rentals</h2></div><Link :href="route('miri-rental.index')" class="text-sm font-bold text-emerald-700 hover:underline">View register →</Link></div><div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-4"><Link v-for="item in dashboard.recent" :key="item.id" :href="route('miri-rental.show', item.id)" class="rounded-xl bg-[#f7fbf5] px-4 py-3 hover:bg-[#eef8ea]"><p class="truncate font-semibold text-[#234222]">{{ item.identifier || 'Unidentified' }}</p><p class="mt-1 truncate text-xs text-slate-500">{{ item.description || '-' }}</p><p class="mt-2 text-[11px] text-slate-400">{{ item.status || 'Not stated' }} · {{ item.updated_at }}</p></Link><p v-if="!dashboard.recent.length" class="text-sm text-slate-500">No rental records imported yet.</p></div></article>
    </section>
</template>

<style scoped>
.rental-dashboard { font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif; font-feature-settings: "kern" 1, "tnum" 1; }
</style>
