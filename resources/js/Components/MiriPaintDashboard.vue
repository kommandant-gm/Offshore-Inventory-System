<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import PaintStockCards from '@/Components/PaintStockCards.vue';
const props = defineProps({ dashboard: Object, canEdit: Boolean });
const pages = reactive({ types: 1, locations: 1 });
const charts = [{ key: 'types', title: 'Records by paint type', filter: 'section_2' }, { key: 'locations', title: 'Records by current location', filter: 'location' }];
const cards = [['total','Paint records',''], ['expired','Past best before','expired'], ['due_30_days','Best before in 30 days','due_30_days'], ['unconfirmed','Unconfirmed dates','unconfirmed'], ['duplicates','Possible repeat rows','duplicates'], ['review','Needs review','review']];
const dateGroups = computed(() => [
    { key: 'expired', label: 'Past best before', color: '#ef4444', filter: 'expired' },
    { key: 'due_30_days', label: 'Today to 30 days', color: '#f59e0b', filter: 'due_30_days' },
    { key: 'beyond_30_days', label: 'Best before beyond 30 days', color: '#10b981' },
    { key: 'unconfirmed', label: 'Unconfirmed date meaning', color: '#8b5cf6', filter: 'unconfirmed' },
    { key: 'no_best_before', label: 'No confirmed best-before date', color: '#94a3b8' },
].map(row => ({ ...row, value: props.dashboard.summary[row.key] })));
const percent = count => props.dashboard.summary.total ? Math.round(count / props.dashboard.summary.total * 100) : 0;
const number = value => Number(value).toLocaleString('en-GB');
const visible = key => props.dashboard[key].slice((pages[key] - 1) * 6, pages[key] * 6);
const pageCount = key => Math.max(1, Math.ceil(props.dashboard[key].length / 6));
const remaining = days => days < 0 ? Math.abs(days) + ' days past best before' : days === 0 ? 'Best before today' : days + ' days left';
</script>
<template>
    <section class="space-y-6">
        <header class="rounded-[28px] bg-gradient-to-r from-[#075442] to-[#218487] p-7 text-white shadow-lg sm:p-9">
            <p class="text-xs font-bold tracking-[.22em] text-teal-200">MIRI INVENTORY</p><h1 class="mt-4 text-3xl font-bold sm:text-4xl">Paint Dashboard</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-teal-100">Recorded paint stock, prices, locations and confirmed best-before dates in one overview.</p>
            <div class="mt-6 flex flex-wrap gap-3"><Link :href="route('paint.index')" class="rounded-xl bg-white px-5 py-3 text-sm font-bold text-[#075442]">View Paint Register</Link><Link v-if="canEdit" :href="route('paint.create')" class="rounded-xl border border-white/30 px-5 py-3 text-sm font-bold hover:bg-white/10">Register paint</Link><Link v-if="canEdit" :href="route('paint.import')" class="rounded-xl border border-white/30 px-5 py-3 text-sm font-bold hover:bg-white/10">Import CSV</Link></div>
        </header>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6"><Link v-for="[key,label,quality] in cards" :key="key" :href="route('paint.index', quality ? { quality } : {})" class="rounded-2xl border border-[#d8e7d4] bg-white p-5"><p class="text-xs text-slate-500">{{ label }}</p><p class="mt-3 text-3xl font-bold text-[#234222]">{{ number(dashboard.summary[key]) }}</p></Link></div>
        <p class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900">Unconfirmed single dates never trigger best-before alerts. Counts represent source records, not unique products. Quantities remain snapshots, with cans and litres kept separate.</p>
        <div v-if="!dashboard.summary.total" class="rounded-2xl border border-dashed bg-white p-8 text-center text-slate-500">No Paint records yet. Queued imports appear here only after processing completes.</div>
        <PaintStockCards :stock-summary="dashboard.stock" />
        <div class="grid gap-5 lg:grid-cols-2">
            <section class="rounded-3xl border border-[#d8e7d4] bg-white p-6">
                <p class="text-xs font-bold tracking-widest text-indigo-500">DATE COVERAGE</p><h2 class="mt-2 text-lg font-bold text-[#234222]">Best-before overview</h2>
                <div class="mt-5 flex h-4 overflow-hidden rounded-full bg-slate-100" aria-hidden="true"><div v-for="row in dateGroups" :key="row.key" :style="{ width: (dashboard.summary.total ? row.value / dashboard.summary.total * 100 : 0) + '%', backgroundColor: row.color }"></div></div>
                <div class="mt-5 space-y-4"><div v-for="row in dateGroups" :key="row.key" class="flex items-center justify-between gap-3 text-sm"><div class="flex items-center gap-3"><span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: row.color }"></span><Link v-if="row.filter" :href="route('paint.index',{ quality:row.filter })" class="text-[#486746] hover:underline">{{ row.label }}</Link><span v-else class="text-[#486746]">{{ row.label }}</span></div><span class="shrink-0 font-semibold">{{ number(row.value) }} <small class="font-normal text-slate-500">({{ percent(row.value) }}%)</small></span></div></div>
            </section>
            <section class="rounded-3xl border border-[#d8e7d4] bg-white p-6">
                <p class="text-xs font-bold tracking-widest text-amber-600">REVIEW PRIORITY</p><h2 class="mt-2 text-lg font-bold text-[#234222]">Past or approaching best before</h2>
                <p class="mt-2 text-xs text-slate-500">Up to six records, earliest best-before date first. Confirmed dates only.</p>
                <div class="mt-4 space-y-3"><Link v-for="item in dashboard.attention" :key="item.id" :href="route('paint.show',item.id)" class="block rounded-xl bg-amber-50 p-3"><p class="text-sm font-semibold text-[#234222]">{{ item.description || 'Description not recorded' }}</p><p class="mt-1 text-xs text-slate-500">{{ item.batch_no || 'No batch' }} · {{ item.current_location || 'Location not recorded' }}</p><p class="mt-1 text-xs font-semibold text-amber-800">{{ item.best_before_date }} · {{ remaining(item.days_remaining) }}</p></Link><p v-if="!dashboard.attention.length" class="py-8 text-sm text-slate-500">No confirmed dates past or within 30 days of best before.</p></div>
            </section>
            <section v-for="chart in charts" :key="chart.key" class="flex flex-col rounded-3xl border border-[#d8e7d4] bg-white p-6">
                <h2 class="text-lg font-bold text-[#234222]">{{ chart.title }}</h2>
                <div class="my-5 flex-1 space-y-5"><div v-for="row in visible(chart.key)" :key="row.label"><div class="mb-2 flex justify-between gap-3 text-sm"><Link v-if="row.label !== 'Not recorded'" :href="route('paint.index',{ [chart.filter]:row.label })" class="font-semibold text-[#486746] hover:underline">{{ row.label }}</Link><span v-else class="font-semibold text-[#486746]">{{ row.label }}</span><span class="shrink-0">{{ number(row.total) }} <small class="text-slate-500">({{ percent(row.total) }}%)</small></span></div><div class="h-2.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-gradient-to-r from-blue-600 to-violet-500" :style="{ width: percent(row.total) + '%' }"></div></div></div><p v-if="!dashboard[chart.key].length" class="text-sm text-slate-500">No records available.</p></div>
                <nav v-if="pageCount(chart.key) > 1" class="flex items-center justify-between border-t pt-4 text-sm" :aria-label="chart.title + ' pages'"><button class="rounded-lg border px-3 py-2 disabled:opacity-40" :disabled="pages[chart.key] === 1" @click="pages[chart.key]--">Previous</button><span>{{ pages[chart.key] }} / {{ pageCount(chart.key) }}</span><button class="rounded-lg border px-3 py-2 disabled:opacity-40" :disabled="pages[chart.key] >= pageCount(chart.key)" @click="pages[chart.key]++">Next</button></nav>
            </section>
        </div>
        <section class="rounded-3xl border border-[#d8e7d4] bg-white p-6"><h2 class="text-lg font-bold text-[#234222]">Recently updated paint records</h2><div class="mt-4 grid gap-3 md:grid-cols-2"><Link v-for="item in dashboard.recent" :key="item.id" :href="route('paint.show',item.id)" class="rounded-xl bg-[#f5f9f3] p-4 hover:bg-green-50"><p class="font-semibold text-[#234222]">{{ item.description || 'Description not recorded' }}</p><p class="mt-1 text-xs text-slate-500">{{ item.section_2 || 'Paint type not recorded' }} · {{ item.batch_no || 'No batch' }} · {{ item.current_location || 'Location not recorded' }}</p></Link></div><p v-if="!dashboard.recent.length" class="mt-4 text-sm text-slate-500">No records available.</p></section>
    </section>
</template>
