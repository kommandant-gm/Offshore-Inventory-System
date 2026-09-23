<script setup>
import CustomSelect from '@/Components/CustomSelect.vue';
import { Link } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';
const props = defineProps({ dashboard: Object, canEdit: Boolean });
const pages = reactive({ categories: 1, locations: 1 });
const cards = computed(() => [
    { label: 'Total records', value: props.dashboard.summary.total, note: 'Source rows, not total units' },
    { label: 'Balance recorded', value: props.dashboard.summary.balance_recorded, note: 'Includes recorded zero balances' },
    { label: 'Zero balance', value: props.dashboard.summary.zero_balance, note: 'Recorded snapshots, not live stock' },
    { label: 'Certificate dates', value: props.dashboard.summary.certificate_dates, note: 'Rows with a recorded due date' },
    { label: 'Needs review', value: props.dashboard.summary.review, note: 'Includes duplicate-tag records', filter: { quality: 'review' } },
]);
const number = value => Number(value).toLocaleString('en-GB', { maximumFractionDigits: 3 });
const stockFilters = reactive({ category: '', location: '' });
const stockCharts = [{ key: 'categories', title: 'Stock by category' }, { key: 'locations', title: 'Stock by current location' }];
const stockRows = computed(() => {
    const categories = new Map();
    const locations = [];
    for (const row of props.dashboard.stockGroups) {
        const key = JSON.stringify([row.category, row.unit]);
        const total = categories.get(key) ?? { category: row.category, unit: row.unit, records: 0, recorded: 0, stock: null, key };
        total.records += row.records;
        total.recorded += row.recorded;
        if (row.stock !== null) total.stock = (total.stock ?? 0) + Number(row.stock);
        categories.set(key, total);
        if ((!stockFilters.category || row.category === stockFilters.category) && (!stockFilters.location || row.location === stockFilters.location)) {
            locations.push({ ...row, key: JSON.stringify([row.category, row.location, row.unit]) });
        }
    }
    return { categories: [...categories.values()], locations };
});
const stockVisible = key => stockRows.value[key].slice((pages[key] - 1) * 6, pages[key] * 6);
const stockPageCount = key => Math.max(1, Math.ceil(stockRows.value[key].length / 6));
watch(stockFilters, () => pages.locations = 1);
watch(() => props.dashboard, () => {
    Object.keys(pages).forEach(key => pages[key] = 1);
    stockFilters.category = ''; stockFilters.location = '';
});
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
            <section v-for="chart in stockCharts" :key="chart.key" class="flex flex-col rounded-3xl border border-[#d8e7d4] bg-white p-6">
                <h2 class="text-lg font-bold text-[#234222]">{{ chart.title }}</h2>
                <p class="mt-2 text-xs text-slate-500">Available to issue equals recorded stock balance. COG reservations are not deducted. Quantities are grouped by unit.</p>
                <div v-if="chart.key === 'locations'" class="mt-4 grid gap-3 sm:grid-cols-2">
                    <label class="text-sm text-slate-600">Category<CustomSelect v-model="stockFilters.category" class="mt-1 w-full rounded-xl border-slate-200"><option value="">All categories</option><option v-for="row in dashboard.categories" :key="row.label" :value="row.label">{{ row.label }}</option></CustomSelect></label>
                    <label class="text-sm text-slate-600">Location<CustomSelect v-model="stockFilters.location" class="mt-1 w-full rounded-xl border-slate-200"><option value="">All locations</option><option v-for="row in dashboard.locations" :key="row.label" :value="row.label">{{ row.label }}</option></CustomSelect></label>
                </div>
                <div class="my-5 flex-1 space-y-4">
                    <div v-for="row in stockVisible(chart.key)" :key="row.key" class="rounded-xl border border-slate-100 p-3">
                        <p class="break-words font-semibold text-[#486746]">{{ chart.key === 'locations' ? row.location : row.category }}</p>
                        <p v-if="chart.key === 'locations'" class="mt-1 text-xs text-slate-500">{{ row.category }}</p>
                        <p class="mt-1 text-xs text-slate-500">Unit: {{ row.unit || 'Not recorded' }}</p>
                        <dl class="mt-3 grid grid-cols-2 gap-3">
                            <div><dt class="text-xs text-slate-500">Stock Qty</dt><dd class="mt-1 font-bold text-blue-700">{{ row.stock === null ? 'Not recorded' : number(row.stock) }}</dd></div>
                            <div><dt class="text-xs text-slate-500">Available to issue</dt><dd class="mt-1 font-bold text-green-700">{{ row.stock === null ? 'Not recorded' : number(row.stock) }}</dd></div>
                        </dl>
                        <p v-if="!row.unit" class="mt-2 text-xs text-amber-800">Quantity total unavailable until the unit is recorded.</p>
                        <p v-else-if="row.recorded < row.records" class="mt-2 text-xs text-amber-800">{{ row.records - row.recorded }} of {{ row.records }} records have no balance. Figures include recorded balances only.</p>
                    </div>
                    <p v-if="!stockRows[chart.key].length" class="text-sm text-slate-500">No records match this selection.</p>
                </div>
                <nav v-if="stockPageCount(chart.key) > 1" class="flex items-center justify-between gap-3 border-t pt-4 text-sm" :aria-label="chart.title + ' pages'"><button type="button" class="btn btn-sm" :disabled="pages[chart.key] === 1" @click="pages[chart.key]--">Previous</button><span>{{ pages[chart.key] }} / {{ stockPageCount(chart.key) }}</span><button type="button" class="btn btn-sm" :disabled="pages[chart.key] >= stockPageCount(chart.key)" @click="pages[chart.key]++">Next</button></nav>
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
