<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
const props = defineProps({ dashboard: Object, canEdit: Boolean });
const pages = reactive({ categories: 1, locations: 1, sections: 1, statuses: 1 });
const charts = [
    { key: 'categories', title: 'Records by category', subtitle: 'REGISTER MIX', filter: 'category' },
    { key: 'locations', title: 'Records by current location', subtitle: 'LOCATION OVERVIEW', filter: 'location' },
    { key: 'sections', title: 'Records by section', subtitle: 'SECTION 1', filter: 'section_1' },
    { key: 'statuses', title: 'Recorded statuses', subtitle: 'SOURCE DATA', filter: null },
];
const cards = computed(() => [
    { label: 'Total records', value: props.dashboard.summary.total, note: 'Source rows, not total units' },
    { label: 'Balance recorded', value: props.dashboard.summary.balance_recorded, note: 'Includes recorded zero balances' },
    { label: 'Zero balance', value: props.dashboard.summary.zero_balance, note: 'Recorded snapshots, not live stock' },
    { label: 'Certificate dates', value: props.dashboard.summary.certificate_dates, note: 'Rows with a recorded due date' },
    { label: 'Needs review', value: props.dashboard.summary.review, note: 'Includes duplicate-tag records', filter: { quality: 'review' } },
]);
const visible = key => props.dashboard[key].slice((pages[key] - 1) * 6, pages[key] * 6);
const pageCount = key => Math.max(1, Math.ceil(props.dashboard[key].length / 6));
const percent = value => props.dashboard.summary.total ? Math.round(Number(value) / props.dashboard.summary.total * 100) : 0;
const number = value => Number(value).toLocaleString('en-GB', { maximumFractionDigits: 3 });
</script>

<template>
    <div class="space-y-6">
        <header class="rounded-[28px] bg-gradient-to-r from-[#075442] to-[#218487] p-7 text-white shadow-lg sm:p-9">
            <p class="text-xs font-bold tracking-[.22em] text-teal-200">MIRI INVENTORY</p>
            <h1 class="mt-4 text-3xl font-bold sm:text-4xl">Construction TEC, Garnet &amp; PPE</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-teal-100">Overview of recorded stock balances, categories, locations and data requiring staff review.</p>
            <div class="mt-6 flex flex-wrap gap-3">
                <Link :href="route('construction.index')" class="rounded-xl bg-white px-5 py-3 text-sm font-bold text-[#075442]">View register</Link>
                <Link v-if="canEdit" :href="route('construction.create')" class="rounded-xl border border-white/30 px-5 py-3 text-sm font-bold hover:bg-white/10">Register item</Link>
                <Link v-if="canEdit" :href="route('construction.import')" class="rounded-xl border border-white/30 px-5 py-3 text-sm font-bold hover:bg-white/10">Import CSV</Link>
            </div>
        </header>
        <div class="rounded-2xl border border-[#d8e7d4] bg-white p-4 text-sm leading-6 text-[#60745d]">
            Counts represent records, not units. Imported balances are snapshots; historical receipts/issues are not recalculated and different units are not added together.
            <Link :href="route('construction.index', { quality: 'duplicates' })" class="mt-2 block font-semibold text-amber-800 underline">Duplicate-tag records: {{ number(dashboard.summary.duplicates) }} — review in register</Link>
        </div>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <component :is="card.filter ? Link : 'div'" v-for="card in cards" :key="card.label" :href="card.filter ? route('construction.index', card.filter) : undefined" class="rounded-2xl border border-[#d8e7d4] border-t-4 border-t-[#4f9f4a] bg-white p-5">
                <p class="text-xs font-semibold uppercase tracking-wider text-[#60745d]">{{ card.label }}</p>
                <p class="mt-3 text-3xl font-bold text-[#234222]">{{ number(card.value) }}</p>
                <p class="mt-2 text-xs text-slate-500">{{ card.note }}</p>
            </component>
        </div>
        <div v-if="!dashboard.summary.total" class="rounded-2xl border border-dashed border-[#d8e7d4] bg-white p-8 text-center text-slate-600">No Construction records yet. Queued imports appear here only after processing completes.</div>
        <div class="grid gap-5 lg:grid-cols-2">
            <section v-for="chart in charts" :key="chart.key" class="flex flex-col rounded-3xl border border-[#d8e7d4] bg-white p-6">
                <p class="text-xs font-bold tracking-widest text-indigo-500">{{ chart.subtitle }}</p>
                <h2 class="mt-2 text-lg font-bold text-[#234222]">{{ chart.title }}</h2>
                <p v-if="chart.key === 'statuses'" class="mt-2 text-xs text-slate-500">Missing statuses remain “Not recorded”; no operational status is assumed.</p>
                <div class="my-5 flex-1 space-y-5">
                    <div v-for="row in visible(chart.key)" :key="row.label">
                        <div class="mb-2 flex items-start justify-between gap-4 text-sm">
                            <Link v-if="chart.filter && row.label !== 'Not recorded'" :href="route('construction.index', { [chart.filter]: row.label })" class="break-words font-semibold text-[#486746] hover:underline">{{ row.label }}</Link>
                            <span v-else class="break-words font-semibold text-[#486746]">{{ row.label }}</span>
                            <span class="shrink-0 font-semibold text-[#234222]">{{ number(row.total) }} <small class="font-normal text-slate-500">({{ percent(row.total) }}%)</small></span>
                        </div>
                        <div class="h-2.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-gradient-to-r from-blue-600 to-violet-500" :style="{ width: percent(row.total) + '%' }"></div></div>
                    </div>
                    <p v-if="!dashboard[chart.key].length" class="text-sm text-slate-500">No records available.</p>
                </div>
                <nav v-if="pageCount(chart.key) > 1" class="flex items-center justify-between border-t pt-4 text-sm" :aria-label="chart.title + ' pages'">
                    <button type="button" class="rounded-lg border px-3 py-2 disabled:opacity-40" :disabled="pages[chart.key] === 1" @click="pages[chart.key]--">Previous</button>
                    <span>{{ pages[chart.key] }} / {{ pageCount(chart.key) }}</span>
                    <button type="button" class="rounded-lg border px-3 py-2 disabled:opacity-40" :disabled="pages[chart.key] >= pageCount(chart.key)" @click="pages[chart.key]++">Next</button>
                </nav>
            </section>
        </div>
        <section class="rounded-3xl border border-[#d8e7d4] bg-white p-6">
            <h2 class="text-lg font-bold text-[#234222]">Recently updated records</h2>
            <p v-if="!dashboard.recent.length" class="mt-4 text-sm text-slate-500">No records available.</p>
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                <Link v-for="item in dashboard.recent" :key="item.id" :href="route('construction.show', item.id)" class="rounded-xl bg-[#f5f9f3] p-4 hover:bg-green-50">
                    <p class="font-semibold text-[#234222]">{{ item.description || 'Description not recorded' }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ item.category }} · {{ item.tag_no || 'No tag' }} · {{ item.current_location || 'Location not recorded' }}</p>
                    <p class="mt-2 text-sm text-[#486746]">Recorded balance: {{ item.stock_balance === null ? 'Not recorded' : number(item.stock_balance) }} {{ item.unit || '' }}</p>
                </Link>
            </div>
        </section>
    </div>
</template>
