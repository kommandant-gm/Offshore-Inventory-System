<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({ summary: Object, locations: Array, records: Array });
const search = ref('');
const location = ref('');
const type = ref('All');
const status = ref('All');
const currentPage = ref(1);
const pageSize = ref(10);
const date = (value) => value ? new Date(`${value}T00:00:00`).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '-';
const daysUntil = (value) => value ? Math.ceil((new Date(`${value}T00:00:00`) - new Date(new Date().toLocaleDateString('en-CA'))) / 86400000) : null;
const dueLabel = (value) => { const days = daysUntil(value); return days === null ? 'No due date' : days < 0 ? `${Math.abs(days)} days overdue` : days === 0 ? 'Due today' : `${days} days left`; };
const dueTone = (value) => { const days = daysUntil(value); return days !== null && days < 0 ? 'text-rose-600' : days !== null && days <= 30 ? 'text-amber-600' : 'text-emerald-600'; };
const filteredRecords = computed(() => props.records.filter((item) => {
    const haystack = [item.name, item.identifier, item.category, item.current_location, item.to_location, item.backload_from].filter(Boolean).join(' ').toLowerCase();
    return (!search.value || haystack.includes(search.value.toLowerCase())) && (!location.value || item.current_location === location.value) && (type.value === 'All' || item.type === type.value) && (status.value === 'All' || item.status === status.value);
}));
const totalPages = computed(() => Math.max(1, Math.ceil(filteredRecords.value.length / pageSize.value)));
const pageStart = computed(() => (currentPage.value - 1) * pageSize.value);
const paginatedRecords = computed(() => filteredRecords.value.slice(pageStart.value, pageStart.value + pageSize.value));
watch([search, location, type, status, pageSize], () => { currentPage.value = 1; });
watch(totalPages, (pages) => { currentPage.value = Math.min(currentPage.value, pages); });
const maximum = computed(() => Math.max(...props.locations.map((item) => Number(item.total)), 1));
const locationOptions = computed(() => props.locations.map((item) => item.label));
const statusOptions = computed(() => [...new Set(props.records.map((item) => item.status).filter(Boolean))]);
</script>

<template>
    <Head title="Miri Inventory Movement" />
    <AuthenticatedLayout>
        <section class="movement-shell space-y-6">
            <header class="relative isolate overflow-hidden rounded-[1.75rem] bg-[linear-gradient(120deg,#123524_0%,#176b5b_58%,#155e75_100%)] px-5 py-7 text-white shadow-[0_24px_70px_rgba(6,78,59,.2)] sm:px-8 sm:py-9 lg:px-10">
                <div class="pointer-events-none absolute -right-16 -top-28 h-80 w-80 rounded-full bg-cyan-300/20 blur-3xl" />
                <div class="relative flex flex-col justify-between gap-6 lg:flex-row lg:items-end"><div><p class="text-xs font-semibold uppercase tracking-[.24em] text-cyan-200">Miri Inventory Control</p><h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">Inventory movement</h1><p class="mt-3 max-w-2xl text-sm leading-6 text-white/70 sm:text-base">See where equipment is now, where it was issued, and when it is expected back.</p></div><Link :href="route('major-equipment.index')" class="inline-flex w-fit rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-emerald-900 shadow-sm hover:bg-emerald-50">Open inventory register</Link></div>
            </header>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6 lg:gap-4">
                <div v-for="card in [{label:'Tracked items',value:summary.total,color:'bg-slate-800'},{label:'Location known',value:summary.located,color:'bg-emerald-500'},{label:'Issued records',value:summary.issued,color:'bg-blue-500'},{label:'Backloaded',value:summary.backloaded,color:'bg-violet-500'},{label:'Due in 30 days',value:summary.due_soon,color:'bg-amber-500'},{label:'Overdue',value:summary.overdue,color:'bg-rose-500'}]" :key="card.label" class="relative overflow-hidden rounded-2xl border border-[#d8e7d4] bg-white p-4 shadow-[0_8px_28px_rgba(39,89,45,.06)]"><div class="absolute inset-x-0 top-0 h-1" :class="card.color"/><p class="text-[10px] font-extrabold uppercase tracking-[.12em] text-slate-500">{{ card.label }}</p><p class="mt-2 text-2xl font-black text-slate-800">{{ card.value }}</p></div>
            </div>

            <div class="grid gap-5 lg:grid-cols-12">
                <article class="rounded-[1.5rem] border border-[#d9e8d5] bg-white p-5 shadow-[0_12px_35px_rgba(39,89,45,.07)] sm:p-6 lg:col-span-4"><div><p class="text-[10px] font-extrabold uppercase tracking-[.22em] text-violet-500">Where inventory is</p><h2 class="mt-1 text-xl font-black tracking-tight text-slate-800">Current locations</h2><p class="mt-1 text-xs text-slate-500">Combined major equipment and rental records.</p></div><div class="mt-6 space-y-4"><button v-for="item in locations" :key="item.label" type="button" class="w-full text-left" @click="location = location === item.label ? '' : item.label"><div class="mb-1 flex items-center justify-between gap-3 text-sm"><strong class="truncate text-slate-700">{{ item.label }}</strong><span class="font-black text-slate-800">{{ item.total }}</span></div><div class="h-2 overflow-hidden rounded-full bg-slate-100"><span class="block h-full rounded-full bg-[linear-gradient(90deg,#10b981,#3b82f6)]" :style="{ width: `${(item.total / maximum) * 100}%` }"/></div></button><p v-if="locations.length === 0" class="text-sm text-slate-500">No current locations recorded.</p></div></article>
                <article class="overflow-hidden rounded-[1.5rem] border border-[#d9e8d5] bg-white shadow-[0_12px_35px_rgba(39,89,45,.07)] lg:col-span-8"><div class="border-b border-slate-100 px-5 py-5 sm:px-6"><p class="text-[10px] font-extrabold uppercase tracking-[.22em] text-blue-500">Movement register</p><h2 class="mt-1 text-xl font-black tracking-tight text-slate-800">Current whereabouts</h2><div class="mt-5 grid gap-3 md:grid-cols-[minmax(0,1.5fr),repeat(3,minmax(0,1fr))]"><input v-model.trim="search" type="search" placeholder="Search tag, description, or location" class="rounded-xl border-slate-200 text-sm"/><select v-model="location" class="rounded-xl border-slate-200 text-sm"><option value="">All locations</option><option v-for="item in locationOptions" :key="item" :value="item">{{ item }}</option></select><select v-model="type" class="rounded-xl border-slate-200 text-sm"><option>All</option><option>Major equipment</option><option>Rental</option></select><select v-model="status" class="rounded-xl border-slate-200 text-sm"><option>All</option><option v-for="item in statusOptions" :key="item">{{ item }}</option></select></div></div><div class="overflow-x-auto"><table class="table"><thead><tr><th>Inventory</th><th>Current location</th><th>Movement</th><th>Return / received</th><th>Status</th></tr></thead><tbody><tr v-for="item in paginatedRecords" :key="`${item.type}-${item.id}`"><td><Link :href="item.type === 'Rental' ? route('miri-rental.show', item.source_id) : route('major-equipment.show', item.source_id)" class="font-bold text-emerald-700 hover:underline">{{ item.identifier || 'Unidentified' }}</Link><div class="max-w-[14rem] truncate text-xs text-slate-500">{{ item.name }} · {{ item.type }}</div></td><td><strong class="text-slate-700">{{ item.current_location || 'Location not recorded' }}</strong><div v-if="item.to_location && item.to_location !== item.current_location" class="text-xs text-blue-600">Issued to {{ item.to_location }}</div></td><td><div v-if="item.issue_date" class="text-xs text-slate-600">{{ date(item.issue_date) }}<span v-if="item.issue_reference"> · {{ item.issue_reference }}</span></div><div v-else class="text-xs text-slate-400">No issue-out record</div><div v-if="item.from_location" class="text-xs text-slate-400">{{ item.from_location }} → {{ item.to_location || item.current_location || 'destination' }}</div></td><td><div v-if="item.due_date" :class="dueTone(item.due_date)" class="text-xs font-bold">{{ dueLabel(item.due_date) }}</div><div v-if="item.due_date" class="text-xs text-slate-400">Due {{ date(item.due_date) }}</div><div v-if="item.received_date" class="mt-1 text-xs text-emerald-700">Received {{ date(item.received_date) }}</div><div v-if="item.backload_from" class="text-xs text-slate-400">From {{ item.backload_from }}</div><div v-if="!item.due_date && !item.received_date" class="text-xs text-slate-400">Not recorded</div></td><td><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">{{ item.status }}</span></td></tr><tr v-if="filteredRecords.length === 0"><td colspan="5" class="py-14 text-center text-sm text-slate-500">No movement records match these filters.</td></tr></tbody></table></div><div class="space-y-3 border-t border-slate-100 px-5 py-4 text-xs text-slate-500">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p role="status">Showing {{ filteredRecords.length ? pageStart + 1 : 0 }}?{{ pageStart + paginatedRecords.length }} of {{ filteredRecords.length }} matching records<span v-if="filteredRecords.length !== records.length"> ({{ records.length }} total)</span>.</p>
                        <label class="flex items-center gap-2">Rows per page<select v-model.number="pageSize" class="rounded-lg border-slate-200 py-1 text-xs"><option :value="10">10</option><option :value="25">25</option><option :value="50">50</option></select></label>
                    </div>
                    <nav aria-label="Movement register pagination" class="flex flex-wrap items-center justify-end gap-3">
                        <button type="button" :disabled="currentPage === 1" class="rounded-lg border border-slate-200 px-3 py-2 font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40" @click="currentPage--">Previous</button>
                        <span>Page {{ currentPage }} of {{ totalPages }}</span>
                        <button type="button" :disabled="currentPage === totalPages" class="rounded-lg border border-slate-200 px-3 py-2 font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40" @click="currentPage++">Next</button>
                    </nav>
                    <p>Dates and locations reflect the latest imported information.</p>
                </div></article>
            </div>
        </section>
    </AuthenticatedLayout>
</template>

<style scoped>
.movement-shell { font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif; font-feature-settings: "kern" 1, "tnum" 1; }
</style>
