<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    transactions: Object,
});

const page = usePage();
const activeTab = ref('latest');
const search = ref('');
const canEditMovements = computed(() => page.props.auth?.user?.can?.movements_edit);
const allTransactions = computed(() => props.transactions.data ?? []);

const filteredTransactions = computed(() => {
    const term = search.value.trim().toLowerCase();

    if (!term) {
        return allTransactions.value;
    }

    return allTransactions.value.filter((transaction) => [
        transaction.item_code,
        transaction.description,
        transaction.transaction_type,
        transaction.location,
        transaction.source_location,
        transaction.destination_location,
        transaction.created_by,
        transaction.remarks,
    ].some((value) => String(value ?? '').toLowerCase().includes(term)));
});

const latestTransactions = computed(() => filteredTransactions.value.slice(0, 5));
const olderTransactions = computed(() => filteredTransactions.value.slice(5));
const visibleTransactions = computed(() => activeTab.value === 'latest' ? latestTransactions.value : olderTransactions.value);
const heroTransaction = computed(() => filteredTransactions.value[0] ?? null);
const hasTransactions = computed(() => allTransactions.value.length > 0);

const typeMeta = (type) => {
    switch (type) {
        case 'receive':
            return { label: 'Receive', hint: 'Stock added into a location.', badge: 'active', tone: 'text-emerald-700' };
        case 'issue':
            return { label: 'Issue', hint: 'Stock issued out from a source location.', badge: 'fair', tone: 'text-amber-700' };
        case 'interloc_transfer':
            return { label: 'Transfer', hint: 'Stock moved between locations with no net quantity change.', badge: 'neutral', tone: 'text-sky-700' };
        case 'material_return':
            return { label: 'Material Return', hint: 'Stock returned back into a location.', badge: 'active', tone: 'text-emerald-700' };
        case 'physical_adjustment':
            return { label: 'Physical Adjustment', hint: 'Manual quantity correction after count or review.', badge: 'fair', tone: 'text-orange-700' };
        case 'price_adjustment':
            return { label: 'Price Adjustment', hint: 'Value changed without changing quantity.', badge: 'inactive', tone: 'text-fuchsia-700' };
        case 'other_misc':
            return { label: 'Other Misc', hint: 'Exceptional manual stock posting.', badge: 'inactive', tone: 'text-slate-700' };
        default:
            return { label: String(type ?? 'movement').replaceAll('_', ' '), hint: 'Recorded stock transaction.', badge: 'inactive', tone: 'text-slate-700' };
    }
};

const formatNumber = (value) => Number(value ?? 0).toFixed(2).replace('.00', '');
const formatMoney = (value) => Number(value ?? 0).toFixed(2);
const primaryLocation = (transaction) => transaction.location ?? transaction.destination_location ?? transaction.source_location ?? 'No location recorded';
const fromLocation = (transaction) => transaction.source_location ?? (transaction.transaction_type === 'issue' ? transaction.location : null) ?? 'N/A';
const toLocation = (transaction) => transaction.destination_location ?? (['receive', 'material_return', 'physical_adjustment', 'other_misc'].includes(transaction.transaction_type) ? transaction.location : null) ?? 'N/A';

const summary = computed(() => filteredTransactions.value.reduce((accumulator, transaction) => {
    const quantity = Number(transaction.quantity ?? 0);

    accumulator.total += 1;

    if (transaction.transaction_type === 'receive') {
        accumulator.received += quantity;
    }

    if (transaction.transaction_type === 'issue') {
        accumulator.issued += quantity;
    }

    if (transaction.transaction_type === 'interloc_transfer') {
        accumulator.transfers += quantity;
    }

    if (['physical_adjustment', 'price_adjustment', 'other_misc'].includes(transaction.transaction_type)) {
        accumulator.adjustments += 1;
    }

    return accumulator;
}, {
    total: 0,
    received: 0,
    issued: 0,
    transfers: 0,
    adjustments: 0,
}));
</script>

<template>
    <Head title="Stock Movements" />

    <AuthenticatedLayout>
        <PageHeader title="Stock Movements" description="Transaction register for stock received, issued, transferred, returned, and adjusted across locations.">
            <Link v-if="canEditMovements" class="btn w-full border-none bg-[linear-gradient(135deg,#6fbb68_0%,#4f9f4a_100%)] text-white shadow-[0_16px_36px_rgba(79,159,74,0.24)] hover:opacity-95 sm:w-auto" :href="route('asset-movements.create')">
                New Movement
            </Link>
        </PageHeader>

        <div v-if="!hasTransactions" class="rounded-[1.5rem] border border-[#cfe6c8] bg-[linear-gradient(180deg,#fbfefa_0%,#eef8ea_100%)] px-5 py-4 text-sm text-[#5f7b5e] shadow-[0_18px_40px_rgba(79,159,74,0.10)]">
            No stock movements have been recorded yet. Create the first transaction to populate this register.
        </div>

        <div class="grid gap-6 xl:grid-cols-[0.85fr,1.15fr]">
            <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-5 shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
                <div class="rounded-[1.6rem] border border-[#d8e7d4] bg-[linear-gradient(180deg,#ffffff_0%,#f7fcf5_100%)] p-4 text-[#234222] shadow-[inset_0_1px_0_rgba(255,255,255,0.5)]">
                    <div class="flex items-center gap-3 rounded-full border border-[#d8e7d4] bg-[#fbfefa] px-4 py-3">
                        <span class="text-[#7f9a7a]">Search</span>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search item, type, location, or user"
                            class="w-full bg-transparent text-sm text-[#234222] outline-none placeholder:text-[#7f9a7a]"
                        >
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-white text-[10px] font-semibold uppercase tracking-[0.18em] text-[#4f9f4a] ring-1 ring-[#cfe6c8]">Go</span>
                    </div>

                    <div class="mt-5">
                        <p class="text-lg font-semibold text-[#234222]">Latest Transaction</p>

                        <article
                            v-if="heroTransaction"
                            class="mt-4 overflow-hidden rounded-[1.8rem] border border-[#d8e7d4] bg-[radial-gradient(circle_at_top_left,rgba(111,187,104,0.14),transparent_24%),linear-gradient(180deg,#ffffff_0%,#f6fbf3_100%)] p-5 text-[#234222] shadow-[0_24px_60px_rgba(79,159,74,0.12)]"
                        >
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold tracking-[0.08em] text-[#2f6f2d]">ITEM : {{ heroTransaction.item_code }}</p>
                                    <p class="mt-1 text-xs text-[#6f8a6b]">{{ heroTransaction.description }}</p>
                                </div>
                                <StatusBadge :value="typeMeta(heroTransaction.transaction_type).badge" />
                            </div>

                            <div class="mt-6 grid gap-3 sm:grid-cols-2">
                                <div class="rounded-2xl border border-[#e1efdc] bg-white px-4 py-3">
                                    <p class="text-[11px] uppercase tracking-[0.2em] text-[#7f9a7a]">Transaction Type</p>
                                    <p class="mt-2 text-base font-semibold text-[#234222]">{{ typeMeta(heroTransaction.transaction_type).label }}</p>
                                    <p class="mt-1 text-xs text-[#6f8a6b]">{{ typeMeta(heroTransaction.transaction_type).hint }}</p>
                                </div>
                                <div class="rounded-2xl border border-[#e1efdc] bg-white px-4 py-3">
                                    <p class="text-[11px] uppercase tracking-[0.2em] text-[#7f9a7a]">Transaction Date</p>
                                    <p class="mt-2 text-base font-semibold text-[#234222]">{{ heroTransaction.transaction_date }}</p>
                                    <p class="mt-1 text-xs text-[#6f8a6b]">Posted by {{ heroTransaction.created_by ?? 'System' }}</p>
                                </div>
                            </div>

                            <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                <div class="rounded-2xl border border-[#e1efdc] bg-white px-4 py-3">
                                    <p class="text-[11px] uppercase tracking-[0.2em] text-[#7f9a7a]">Primary Location</p>
                                    <p class="mt-2 text-sm font-semibold text-[#234222]">{{ primaryLocation(heroTransaction) }}</p>
                                </div>
                                <div class="rounded-2xl border border-[#e1efdc] bg-white px-4 py-3">
                                    <p class="text-[11px] uppercase tracking-[0.2em] text-[#7f9a7a]">From Location</p>
                                    <p class="mt-2 text-sm font-semibold text-[#234222]">{{ fromLocation(heroTransaction) }}</p>
                                </div>
                                <div class="rounded-2xl border border-[#e1efdc] bg-white px-4 py-3">
                                    <p class="text-[11px] uppercase tracking-[0.2em] text-[#7f9a7a]">To Location</p>
                                    <p class="mt-2 text-sm font-semibold text-[#234222]">{{ toLocation(heroTransaction) }}</p>
                                </div>
                                <div class="rounded-2xl border border-[#e1efdc] bg-white px-4 py-3">
                                    <p class="text-[11px] uppercase tracking-[0.2em] text-[#7f9a7a]">Quantity / Value</p>
                                    <p class="mt-2 text-lg font-semibold text-[#234222]">{{ formatNumber(heroTransaction.quantity) }} {{ heroTransaction.uom }}</p>
                                    <p class="mt-1 text-xs text-[#6f8a6b]">RM {{ formatMoney(heroTransaction.total_value) }}</p>
                                </div>
                            </div>

                            <div v-if="heroTransaction.remarks" class="mt-5 rounded-[1.3rem] border border-[#e1efdc] bg-white px-4 py-4">
                                <p class="text-[11px] uppercase tracking-[0.2em] text-[#7f9a7a]">Remarks</p>
                                <p class="mt-2 text-sm text-[#4f6b4b]">{{ heroTransaction.remarks }}</p>
                            </div>
                        </article>
                    </div>

                    <div class="mt-6 rounded-[1.4rem] border border-[#d8e7d4] bg-[#fbfefa] px-4 py-4">
                        <div class="mb-4 flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-[#234222]">Transaction Summary</p>
                                <p class="mt-1 text-xs text-[#6f8a6b]">Based on the currently filtered movement records.</p>
                            </div>
                            <div class="inline-flex w-fit rounded-full bg-white px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.22em] text-[#3c8a39] ring-1 ring-[#cfe6c8]">
                                {{ summary.total }} records
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="rounded-2xl border border-[#e1efdc] bg-white px-4 py-3">
                                <p class="text-[11px] uppercase tracking-[0.2em] text-[#7f9a7a]">Received Qty</p>
                                <p class="mt-2 text-lg font-semibold text-[#234222]">{{ formatNumber(summary.received) }}</p>
                            </div>
                            <div class="rounded-2xl border border-[#e1efdc] bg-white px-4 py-3">
                                <p class="text-[11px] uppercase tracking-[0.2em] text-[#7f9a7a]">Issued Qty</p>
                                <p class="mt-2 text-lg font-semibold text-[#234222]">{{ formatNumber(summary.issued) }}</p>
                            </div>
                            <div class="rounded-2xl border border-[#e1efdc] bg-white px-4 py-3">
                                <p class="text-[11px] uppercase tracking-[0.2em] text-[#7f9a7a]">Transfer Qty</p>
                                <p class="mt-2 text-lg font-semibold text-[#234222]">{{ formatNumber(summary.transfers) }}</p>
                            </div>
                            <div class="rounded-2xl border border-[#e1efdc] bg-white px-4 py-3">
                                <p class="text-[11px] uppercase tracking-[0.2em] text-[#7f9a7a]">Adjustment Records</p>
                                <p class="mt-2 text-lg font-semibold text-[#234222]">{{ summary.adjustments }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-5 shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
                <div class="rounded-[1.6rem] border border-[#d8e7d4] bg-[linear-gradient(180deg,#ffffff_0%,#f7fcf5_100%)] p-4 text-[#234222] shadow-[inset_0_1px_0_rgba(255,255,255,0.5)]">
                    <div class="flex items-center gap-3 rounded-full border border-[#d8e7d4] bg-[#fbfefa] px-4 py-3">
                        <span class="text-[#7f9a7a]">Search</span>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search movement records"
                            class="w-full bg-transparent text-sm text-[#234222] outline-none placeholder:text-[#7f9a7a]"
                        >
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-white text-[10px] font-semibold uppercase tracking-[0.18em] text-[#4f9f4a] ring-1 ring-[#cfe6c8]">Go</span>
                    </div>

                    <div class="mt-5">
                        <p class="text-lg font-semibold text-[#234222]">Movement History</p>

                        <div class="mt-4 flex flex-col gap-2 rounded-[1.2rem] border border-[#d8e7d4] bg-[#f4fbf1] p-2 sm:inline-flex sm:flex-row sm:rounded-full sm:p-1">
                            <button
                                type="button"
                                class="w-full rounded-full px-6 py-2 text-sm font-semibold transition sm:min-w-[140px] sm:w-auto"
                                :class="activeTab === 'latest' ? 'bg-white text-[#3c8a39] shadow-sm ring-1 ring-[#cfe6c8]' : 'text-[#6f8a6b]'"
                                @click="activeTab = 'latest'"
                            >
                                Latest
                            </button>
                            <button
                                type="button"
                                class="w-full rounded-full px-6 py-2 text-sm font-semibold transition sm:min-w-[140px] sm:w-auto"
                                :class="activeTab === 'older' ? 'bg-white text-[#3c8a39] shadow-sm ring-1 ring-[#cfe6c8]' : 'text-[#6f8a6b]'"
                                @click="activeTab = 'older'"
                            >
                                Older Records
                            </button>
                        </div>
                    </div>

                    <div class="mt-5 space-y-4">
                        <article
                            v-for="movement in visibleTransactions"
                            :key="movement.id"
                            class="overflow-hidden rounded-[1.65rem] border border-[#d8e7d4] bg-[linear-gradient(180deg,#ffffff_0%,#f7fcf5_100%)] p-5 text-[#234222] shadow-[0_20px_40px_rgba(79,159,74,0.10)]"
                        >
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold tracking-[0.08em] text-[#2f6f2d]">{{ movement.item_code }}</p>
                                    <p class="mt-1 text-xs text-[#6f8a6b]">{{ movement.description }}</p>
                                </div>
                                <StatusBadge :value="typeMeta(movement.transaction_type).badge" />
                            </div>

                            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                <div>
                                    <p class="text-[11px] uppercase tracking-[0.18em] text-[#7f9a7a]">Type / Date</p>
                                    <p class="mt-1 text-base font-semibold text-[#234222]">{{ typeMeta(movement.transaction_type).label }}</p>
                                    <p class="mt-1 text-xs text-[#6f8a6b]">{{ movement.transaction_date }}</p>
                                </div>
                                <div>
                                    <p class="text-[11px] uppercase tracking-[0.18em] text-[#7f9a7a]">Primary / From</p>
                                    <p class="mt-1 text-sm font-semibold text-[#234222]">{{ primaryLocation(movement) }}</p>
                                    <p class="mt-1 text-xs text-[#6f8a6b]">From: {{ fromLocation(movement) }}</p>
                                </div>
                                <div>
                                    <p class="text-[11px] uppercase tracking-[0.18em] text-[#7f9a7a]">To / Posted By</p>
                                    <p class="mt-1 text-sm font-semibold text-[#234222]">{{ toLocation(movement) }}</p>
                                    <p class="mt-1 text-xs text-[#6f8a6b]">{{ movement.created_by ?? 'System' }}</p>
                                </div>
                                <div>
                                    <p class="text-[11px] uppercase tracking-[0.18em] text-[#7f9a7a]">Quantity / Value</p>
                                    <p class="mt-1 text-base font-semibold text-[#234222]">{{ formatNumber(movement.quantity) }} {{ movement.uom }}</p>
                                    <p class="mt-1 text-xs text-[#6f8a6b]">RM {{ formatMoney(movement.total_value) }}</p>
                                </div>
                            </div>

                            <div class="mt-5 rounded-[1.2rem] border border-[#e1efdc] bg-white px-4 py-3">
                                <p class="text-[11px] uppercase tracking-[0.18em] text-[#7f9a7a]">Meaning</p>
                                <p class="mt-1 text-sm" :class="typeMeta(movement.transaction_type).tone">{{ typeMeta(movement.transaction_type).hint }}</p>
                            </div>

                            <div v-if="movement.remarks || movement.cog_no" class="mt-4 flex flex-col gap-2 text-sm text-[#4f6b4b]">
                                <p v-if="movement.remarks"><span class="font-semibold text-[#234222]">Remarks:</span> {{ movement.remarks }}</p>
                                <p v-if="movement.cog_no"><span class="font-semibold text-[#234222]">COG:</span> {{ movement.cog_no }}</p>
                            </div>
                        </article>

                        <div v-if="visibleTransactions.length === 0" class="rounded-[1.6rem] border border-dashed border-[#cfe6c8] bg-[#fbfefa] px-6 py-12 text-center text-sm text-[#6f8a6b]">
                            No movement records found for this view.
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
