<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';

const props = defineProps({
    filters: Object,
    categories: Array,
    rows: Array,
});

const form = reactive({
    year: props.filters.year,
    month: props.filters.month,
    category: props.filters.category,
});

const summary = computed(() => props.rows.reduce((accumulator, row) => {
    accumulator.opening += Number(row.opening_stock ?? 0);
    accumulator.closing += Number(row.closing_stock ?? 0);
    accumulator.receivedValue += Number(row.total_received_value ?? 0);
    accumulator.issuedValue += Number(row.total_issued_value ?? 0);
    accumulator.closingValue += Number(row.closing_stock_value ?? 0);
    return accumulator;
}, {
    opening: 0,
    closing: 0,
    receivedValue: 0,
    issuedValue: 0,
    closingValue: 0,
}));

const selectedCategoryLabel = computed(() => {
    const selected = props.categories.find((category) => category.value === form.category);
    return selected?.label ?? 'All categories';
});

const reportingLabel = computed(() => {
    const year = Number(form.year);
    const month = Number(form.month);

    if (!Number.isFinite(year) || !Number.isFinite(month) || month < 1 || month > 12) {
        return 'Current period';
    }

    return new Intl.DateTimeFormat('en-MY', {
        month: 'long',
        year: 'numeric',
    }).format(new Date(year, month - 1, 1));
});

watch(form, () => {
    router.get(route('asset-ledger.index'), form, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
});

const fmt = (value) => {
    if (value === null || value === undefined || value === '') return '';
    return Number(value).toFixed(2).replace('.00', '');
};

const money = (value) => {
    if (value === null || value === undefined || value === '') return '';
    return Number(value).toFixed(2);
};
</script>

<template>
    <Head title="Stock Item Ledger" />

    <AuthenticatedLayout>
        <PageHeader title="Monthly Stock Item Ledger" description="A softer reporting layout with summary cards and a detailed ledger table in the white and green reporting theme." />

        <div class="grid gap-6 xl:grid-cols-[1.65fr,1fr]">
            <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-5 shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
                <div class="mb-5 flex items-center justify-between">
                    <div>
                        <p class="text-sm text-[#6f8a6b]">Reporting</p>
                        <h2 class="text-xl font-semibold text-[#234222]">Stock Item Ledger Summary</h2>
                    </div>
                    <span class="rounded-full border border-[#b8d7b1] bg-[#eef8ea] px-4 py-1 text-xs font-semibold text-[#3c8a39]">
                        {{ rows.length }} items
                    </span>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                    <div class="rounded-[1.5rem] border border-[#e1efdc] bg-[#fbfefa] p-4">
                        <p class="text-xs uppercase tracking-[0.2em] text-[#7f9a7a]">Opening Units</p>
                        <p class="mt-2 text-2xl font-bold text-[#234222]">{{ fmt(summary.opening) }}</p>
                    </div>
                    <div class="rounded-[1.5rem] border border-[#e1efdc] bg-[#fbfefa] p-4">
                        <p class="text-xs uppercase tracking-[0.2em] text-[#7f9a7a]">Closing Units</p>
                        <p class="mt-2 text-2xl font-bold text-[#3c8a39]">{{ fmt(summary.closing) }}</p>
                    </div>
                    <div class="rounded-[1.5rem] border border-[#e1efdc] bg-[#fbfefa] p-4">
                        <p class="text-xs uppercase tracking-[0.2em] text-[#7f9a7a]">Received Value</p>
                        <p class="mt-2 text-2xl font-bold text-sky-700">RM {{ money(summary.receivedValue) }}</p>
                    </div>
                    <div class="rounded-[1.5rem] border border-[#e1efdc] bg-[#fbfefa] p-4">
                        <p class="text-xs uppercase tracking-[0.2em] text-[#7f9a7a]">Issued Value</p>
                        <p class="mt-2 text-2xl font-bold text-amber-700">RM {{ money(summary.issuedValue) }}</p>
                    </div>
                    <div class="rounded-[1.5rem] border border-[#e1efdc] bg-[#fbfefa] p-4">
                        <p class="text-xs uppercase tracking-[0.2em] text-[#7f9a7a]">Closing Value</p>
                        <p class="mt-2 text-2xl font-bold text-[#4f9f4a]">RM {{ money(summary.closingValue) }}</p>
                    </div>
                </div>
            </section>

            <aside class="rounded-[2rem] border border-[#d8e7d4] bg-white p-5 shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
                <div class="mb-5">
                    <p class="text-sm text-[#6f8a6b]">Filters</p>
                    <h2 class="text-xl font-semibold text-[#234222]">Ledger Controls</h2>
                </div>

                <div class="space-y-4">
                    <div class="rounded-[1.25rem] border border-[#e1efdc] bg-[#fbfefa] p-4">
                        <label class="mb-2 block text-xs uppercase tracking-[0.2em] text-[#7f9a7a]">Category</label>
                        <select v-model="form.category" class="select w-full border-[#cfe6c8] bg-white text-[#234222]">
                            <option v-for="category in categories" :key="category.value" :value="category.value">{{ category.label }}</option>
                        </select>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-1">
                        <div class="rounded-[1.25rem] border border-[#e1efdc] bg-[#fbfefa] p-4">
                            <label class="mb-2 block text-xs uppercase tracking-[0.2em] text-[#7f9a7a]">Month</label>
                            <input v-model="form.month" type="number" min="1" max="12" class="input w-full border-[#cfe6c8] bg-white text-[#234222]" />
                        </div>
                        <div class="rounded-[1.25rem] border border-[#e1efdc] bg-[#fbfefa] p-4">
                            <label class="mb-2 block text-xs uppercase tracking-[0.2em] text-[#7f9a7a]">Year</label>
                            <input v-model="form.year" type="number" min="2000" max="2100" class="input w-full border-[#cfe6c8] bg-white text-[#234222]" />
                        </div>
                    </div>

                    <div class="rounded-[1.25rem] border border-[#cfe6c8] bg-[#eef8ea] p-4 text-sm text-[#4f6b4b]">
                        Values in the report are calculated from opening stock, transaction buckets, and unit price. Users only enter the operational movements and references.
                    </div>
                </div>
            </aside>
        </div>

        <section class="relative overflow-hidden rounded-[2rem] border border-[#d8e7d4] bg-white p-5 shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(111,187,104,0.14),transparent_34%),radial-gradient(circle_at_bottom_right,rgba(79,159,74,0.08),transparent_28%)]" />

            <div class="relative space-y-5">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div class="space-y-3">
                        <div class="inline-flex items-center gap-2 rounded-full border border-[#cfe6c8] bg-[#eef8ea] px-4 py-1.5 text-[11px] font-semibold uppercase tracking-[0.35em] text-[#3c8a39]">
                            <span class="h-2 w-2 rounded-full bg-[#4f9f4a] shadow-[0_0_14px_rgba(79,159,74,0.35)]" />
                            Detailed Ledger
                        </div>
                        <div>
                            <h2 class="text-2xl font-semibold text-[#234222]">Monthly spreadsheet rollup</h2>
                            <p class="max-w-2xl text-sm leading-6 text-[#5f7b5e]">
                                Full operational ledger with quantity buckets, valuation columns, and reference fields presented in a cleaner reporting shell.
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <div class="rounded-full border border-[#d8e7d4] bg-[#fbfefa] px-4 py-2 text-xs font-semibold uppercase tracking-[0.28em] text-[#6f8a6b]">
                            {{ reportingLabel }}
                        </div>
                        <div class="rounded-full border border-[#b8d7b1] bg-[#eef8ea] px-4 py-2 text-xs font-semibold uppercase tracking-[0.28em] text-[#3c8a39]">
                            Monthly View
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 xl:grid-cols-[1.1fr,1.1fr,0.9fr,0.9fr]">
                    <div class="rounded-[1.5rem] border border-[#d8e7d4] bg-[#fbfefa] p-4">
                        <p class="text-[11px] uppercase tracking-[0.28em] text-[#7f9a7a]">Scope</p>
                        <p class="mt-3 text-lg font-semibold text-[#234222]">{{ selectedCategoryLabel }}</p>
                        <p class="mt-1 text-sm text-[#6f8a6b]">Filtered asset category for the active reporting month.</p>
                    </div>
                    <div class="rounded-[1.5rem] border border-[#d8e7d4] bg-[#fbfefa] p-4">
                        <p class="text-[11px] uppercase tracking-[0.28em] text-[#7f9a7a]">Row Coverage</p>
                        <div class="mt-3 flex items-end justify-between gap-4">
                            <p class="text-3xl font-semibold text-[#234222]">{{ rows.length }}</p>
                            <div class="flex gap-1.5">
                                <span
                                    v-for="bar in Math.min(rows.length || 1, 10)"
                                    :key="bar"
                                    class="w-2 rounded-full bg-gradient-to-t from-[#4f9f4a]/45 to-[#8fd18a]"
                                    :style="{ height: `${18 + (bar % 5) * 7}px` }"
                                />
                            </div>
                        </div>
                        <p class="mt-2 text-sm text-[#6f8a6b]">Registered items included in the current ledger run.</p>
                    </div>
                    <div class="rounded-[1.5rem] border border-[#d8e7d4] bg-[#fbfefa] p-4">
                        <p class="text-[11px] uppercase tracking-[0.28em] text-[#7f9a7a]">Closing Units</p>
                        <p class="mt-3 text-2xl font-semibold text-[#3c8a39]">{{ fmt(summary.closing) }}</p>
                        <p class="mt-2 text-sm text-[#6f8a6b]">Net quantity remaining after all movement buckets.</p>
                    </div>
                    <div class="rounded-[1.5rem] border border-[#d8e7d4] bg-[#fbfefa] p-4">
                        <p class="text-[11px] uppercase tracking-[0.28em] text-[#7f9a7a]">Closing Value</p>
                        <p class="mt-3 text-2xl font-semibold text-[#4f9f4a]">RM {{ money(summary.closingValue) }}</p>
                        <p class="mt-2 text-sm text-[#6f8a6b]">Calculated from closing stock and the effective unit price.</p>
                    </div>
                </div>

                <div class="rounded-[1.75rem] border border-[#d8e7d4] bg-white shadow-[inset_0_1px_0_rgba(255,255,255,0.5)]">
                    <div class="flex flex-col gap-4 border-b border-[#e1efdc] px-5 py-4 xl:flex-row xl:items-center xl:justify-between">
                        <div>
                            <p class="text-sm font-medium text-[#234222]">Spreadsheet view</p>
                            <p class="mt-1 text-sm text-[#6f8a6b]">Sticky identifiers, horizontal scroll, and full value rollups for each stock item row.</p>
                        </div>

                        <div class="flex flex-wrap gap-2 text-[11px] font-semibold uppercase tracking-[0.24em]">
                            <span class="rounded-full border border-[#d8e7d4] bg-[#fbfefa] px-3 py-1.5 text-[#6f8a6b]">Qty</span>
                            <span class="rounded-full border border-[#d8e7d4] bg-[#fbfefa] px-3 py-1.5 text-[#6f8a6b]">Value</span>
                            <span class="rounded-full border border-[#d8e7d4] bg-[#fbfefa] px-3 py-1.5 text-[#6f8a6b]">References</span>
                        </div>
                    </div>

                    <div class="border-b border-[#e1efdc] px-5 py-3">
                        <div class="flex flex-wrap items-center gap-3 text-xs text-[#6f8a6b]">
                            <span class="rounded-full border border-[#d8e7d4] bg-[#fbfefa] px-3 py-1.5">Scroll horizontally for the full ledger</span>
                            <span class="rounded-full border border-[#cfe6c8] bg-[#eef8ea] px-3 py-1.5 text-[#3c8a39]">First two columns stay pinned</span>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-[2300px] border-separate border-spacing-0 text-sm">
                            <thead>
                                <tr class="text-left text-[11px] uppercase tracking-[0.22em] text-[#7f9a7a]">
                                    <th class="sticky left-0 z-30 w-[72px] min-w-[72px] border-b border-[#e1efdc] bg-[#f4fbf1] px-4 py-4">No</th>
                                    <th class="sticky left-[72px] z-30 w-[260px] min-w-[260px] border-b border-[#e1efdc] bg-[#f4fbf1] px-4 py-4 shadow-[8px_0_18px_rgba(79,159,74,0.06)]">Description</th>
                                    <th class="min-w-[120px] border-b border-[#e1efdc] bg-[#f4fbf1] px-4 py-4">Opening Stock</th>
                                    <th class="border-b border-[#e1efdc] bg-[#f4fbf1] px-4 py-4">Total Received</th>
                                    <th class="border-b border-[#e1efdc] bg-[#f4fbf1] px-4 py-4">Total Issued</th>
                                    <th class="border-b border-[#e1efdc] bg-[#f4fbf1] px-4 py-4">Interloc Transfer</th>
                                    <th class="border-b border-[#e1efdc] bg-[#f4fbf1] px-4 py-4">Matl. Ret.</th>
                                    <th class="border-b border-[#e1efdc] bg-[#f4fbf1] px-4 py-4">Phy. Adj.</th>
                                    <th class="border-b border-[#e1efdc] bg-[#f4fbf1] px-4 py-4">Price Adj.</th>
                                    <th class="border-b border-[#e1efdc] bg-[#f4fbf1] px-4 py-4">Other Misc.</th>
                                    <th class="border-b border-[#e1efdc] bg-[#f4fbf1] px-4 py-4">Closing Stock</th>
                                    <th class="border-b border-[#e1efdc] bg-[#f4fbf1] px-4 py-4">Unit Measure</th>
                                    <th class="border-b border-[#e1efdc] bg-[#f4fbf1] px-4 py-4">Unit Price</th>
                                    <th class="border-b border-[#e1efdc] bg-[#f4fbf1] px-4 py-4">Opening Stock Value (RM)</th>
                                    <th class="border-b border-[#e1efdc] bg-[#f4fbf1] px-4 py-4">Total Received Value (RM)</th>
                                    <th class="border-b border-[#e1efdc] bg-[#f4fbf1] px-4 py-4">Total Issued Value (RM)</th>
                                    <th class="border-b border-[#e1efdc] bg-[#f4fbf1] px-4 py-4">Interloc Trans. Value</th>
                                    <th class="border-b border-[#e1efdc] bg-[#f4fbf1] px-4 py-4">Matl. Ret. Value</th>
                                    <th class="border-b border-[#e1efdc] bg-[#f4fbf1] px-4 py-4">Phy. Adj. Value (RM)</th>
                                    <th class="border-b border-[#e1efdc] bg-[#f4fbf1] px-4 py-4">Price Adj. Value (RM)</th>
                                    <th class="border-b border-[#e1efdc] bg-[#f4fbf1] px-4 py-4">Other Misc Value (RM)</th>
                                    <th class="border-b border-[#e1efdc] bg-[#f4fbf1] px-4 py-4">Closing Stock Value (RM)</th>
                                    <th class="border-b border-[#e1efdc] bg-[#f4fbf1] px-4 py-4">Rack No.</th>
                                    <th class="border-b border-[#e1efdc] bg-[#f4fbf1] px-4 py-4">COG Issued Out</th>
                                    <th class="border-b border-[#e1efdc] bg-[#f4fbf1] px-4 py-4">COG Received</th>
                                    <th class="border-b border-[#e1efdc] bg-[#f4fbf1] px-4 py-4">Purchase Order No.</th>
                                    <th class="border-b border-[#e1efdc] bg-[#f4fbf1] px-4 py-4">Delivery Order No.</th>
                                    <th class="border-b border-[#e1efdc] bg-[#f4fbf1] px-4 py-4">Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in rows" :key="row.no" class="group">
                                    <td class="sticky left-0 z-20 w-[72px] min-w-[72px] border-b border-[#edf3eb] bg-white px-4 py-4 text-[#7f9a7a] transition group-hover:bg-[#f8fcf6]">
                                        {{ row.no }}
                                    </td>
                                    <td class="sticky left-[72px] z-20 w-[260px] min-w-[260px] border-b border-[#edf3eb] bg-white px-4 py-4 text-[#234222] shadow-[8px_0_18px_rgba(79,159,74,0.06)] transition group-hover:bg-[#f8fcf6]">
                                        <div class="min-w-[220px]">
                                            <p class="font-medium text-[#234222]">{{ row.description }}</p>
                                            <p class="mt-1 text-xs text-[#7f9a7a]">{{ row.rack_no || 'Rack pending' }}</p>
                                        </div>
                                    </td>
                                    <td class="min-w-[120px] border-b border-[#edf3eb] px-4 py-4 text-[#4f6b4b]">{{ fmt(row.opening_stock) }}</td>
                                    <td class="border-b border-[#edf3eb] px-4 py-4 text-sky-700">{{ fmt(row.total_received) }}</td>
                                    <td class="border-b border-[#edf3eb] px-4 py-4 text-rose-700">{{ fmt(row.total_issued) }}</td>
                                    <td class="border-b border-[#edf3eb] px-4 py-4 text-[#4f6b4b]">{{ fmt(row.interloc_transfer) }}</td>
                                    <td class="border-b border-[#edf3eb] px-4 py-4 text-emerald-700">{{ fmt(row.material_return) }}</td>
                                    <td class="border-b border-[#edf3eb] px-4 py-4 text-amber-700">{{ fmt(row.physical_adjustment) }}</td>
                                    <td class="border-b border-[#edf3eb] px-4 py-4 text-orange-700">{{ money(row.price_adjustment) }}</td>
                                    <td class="border-b border-[#edf3eb] px-4 py-4 text-[#4f6b4b]">{{ fmt(row.other_misc) }}</td>
                                    <td class="border-b border-[#edf3eb] px-4 py-4 font-semibold text-[#234222]">{{ fmt(row.closing_stock) }}</td>
                                    <td class="border-b border-[#edf3eb] px-4 py-4 text-[#4f6b4b]">{{ row.unit_measure }}</td>
                                    <td class="border-b border-[#edf3eb] px-4 py-4 text-[#4f6b4b]">RM {{ money(row.unit_price) }}</td>
                                    <td class="border-b border-[#edf3eb] px-4 py-4 text-[#4f6b4b]">RM {{ money(row.opening_stock_value) }}</td>
                                    <td class="border-b border-[#edf3eb] px-4 py-4 text-sky-700">RM {{ money(row.total_received_value) }}</td>
                                    <td class="border-b border-[#edf3eb] px-4 py-4 text-rose-700">RM {{ money(row.total_issued_value) }}</td>
                                    <td class="border-b border-[#edf3eb] px-4 py-4 text-[#4f6b4b]">RM {{ money(row.interloc_transfer_value) }}</td>
                                    <td class="border-b border-[#edf3eb] px-4 py-4 text-emerald-700">RM {{ money(row.material_return_value) }}</td>
                                    <td class="border-b border-[#edf3eb] px-4 py-4 text-amber-700">RM {{ money(row.physical_adjustment_value) }}</td>
                                    <td class="border-b border-[#edf3eb] px-4 py-4 text-orange-700">RM {{ money(row.price_adjustment_value) }}</td>
                                    <td class="border-b border-[#edf3eb] px-4 py-4 text-[#4f6b4b]">RM {{ money(row.other_misc_value) }}</td>
                                    <td class="border-b border-[#edf3eb] px-4 py-4 font-semibold text-[#3c8a39]">RM {{ money(row.closing_stock_value) }}</td>
                                    <td class="border-b border-[#edf3eb] px-4 py-4 text-[#4f6b4b]">{{ row.rack_no ?? '' }}</td>
                                    <td class="border-b border-[#edf3eb] px-4 py-4 text-[#4f6b4b]">{{ row.cog_issued_out ?? '' }}</td>
                                    <td class="border-b border-[#edf3eb] px-4 py-4 text-[#4f6b4b]">{{ row.cog_received ?? '' }}</td>
                                    <td class="border-b border-[#edf3eb] px-4 py-4 text-[#4f6b4b]">{{ row.purchase_order_no ?? '' }}</td>
                                    <td class="border-b border-[#edf3eb] px-4 py-4 text-[#4f6b4b]">{{ row.delivery_order_no ?? '' }}</td>
                                    <td class="border-b border-[#edf3eb] px-4 py-4 text-[#4f6b4b]">{{ row.remarks ?? '' }}</td>
                                </tr>
                                <tr v-if="rows.length === 0">
                                    <td colspan="28" class="px-6 py-16">
                                        <div class="mx-auto max-w-lg rounded-[1.5rem] border border-dashed border-[#cfe6c8] bg-[#fbfefa] px-6 py-10 text-center">
                                            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl border border-[#cfe6c8] bg-[#eef8ea] text-[#3c8a39]">
                                                <span class="text-xl">+</span>
                                            </div>
                                            <h3 class="mt-4 text-lg font-semibold text-[#234222]">No ledger rows for this period</h3>
                                            <p class="mt-2 text-sm leading-6 text-[#6f8a6b]">
                                                Adjust the month or category filters, or register stock item movements so this monthly ledger can populate the spreadsheet columns.
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
