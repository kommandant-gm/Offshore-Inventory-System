<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    transactions: Object,
});

const page = usePage();
const activeTab = ref('live');
const search = ref('');
const canEditMovements = computed(() => page.props.auth?.user?.can?.movements_edit);

const previewTransactions = [
    {
        id: 'preview-1',
        transaction_date: '2026-06-04',
        item_code: 'CAT-ROPE-001',
        description: 'Polyamide Rope 1/2" x 200 MTR',
        transaction_type: 'interloc_transfer',
        quantity: '20',
        total_value: '2800.00',
        source_location: 'Main Store',
        destination_location: 'Offshore Base',
        created_by: 'Inventory Team',
    },
    {
        id: 'preview-2',
        transaction_date: '2026-06-03',
        item_code: 'CAT-RACK-012',
        description: 'Cylinder Rack 4x4x5',
        transaction_type: 'issue',
        quantity: '4',
        total_value: '3200.00',
        source_location: 'Yard Zone A',
        destination_location: 'Project DESB',
        created_by: 'Ops Control',
    },
    {
        id: 'preview-3',
        transaction_date: '2026-06-02',
        item_code: 'CAT-HOSE-009',
        description: 'Air Hose 2"',
        transaction_type: 'receive',
        quantity: '12',
        total_value: '1440.00',
        source_location: 'Vendor',
        destination_location: 'Receiving Bay',
        created_by: 'Store Clerk',
    },
    {
        id: 'preview-4',
        transaction_date: '2026-06-01',
        item_code: 'CAT-PAINT-005',
        description: 'Dog Leg Paint Brush 2"',
        transaction_type: 'material_return',
        quantity: '6',
        total_value: '63.00',
        source_location: 'Maintenance Team',
        destination_location: 'General Store',
        created_by: 'Maintenance Lead',
    },
];

const allTransactions = computed(() => props.transactions.data.length ? props.transactions.data : previewTransactions);

const filteredTransactions = computed(() => {
    const term = search.value.trim().toLowerCase();

    if (!term) {
        return allTransactions.value;
    }

    return allTransactions.value.filter((transaction) => [
        transaction.item_code,
        transaction.description,
        transaction.transaction_type,
        transaction.source_location,
        transaction.destination_location,
        transaction.created_by,
    ].some((value) => String(value ?? '').toLowerCase().includes(term)));
});

const liveTransactions = computed(() => filteredTransactions.value.slice(0, 4));
const historyTransactions = computed(() => filteredTransactions.value.slice(4));
const visibleTransactions = computed(() => activeTab.value === 'live' ? liveTransactions.value : historyTransactions.value);
const heroTransaction = computed(() => liveTransactions.value[0] ?? filteredTransactions.value[0] ?? null);
const usesPreview = computed(() => props.transactions.data.length === 0);

const trackingState = (type) => {
    switch (type) {
        case 'receive':
            return { label: 'received', tone: 'text-emerald-700', dot: 'bg-emerald-500', progress: 100 };
        case 'issue':
            return { label: 'issued out', tone: 'text-amber-700', dot: 'bg-amber-500', progress: 72 };
        case 'interloc_transfer':
            return { label: 'moving', tone: 'text-sky-700', dot: 'bg-sky-500', progress: 58 };
        case 'material_return':
            return { label: 'returned', tone: 'text-emerald-700', dot: 'bg-emerald-500', progress: 88 };
        case 'physical_adjustment':
            return { label: 'adjusted', tone: 'text-orange-700', dot: 'bg-orange-500', progress: 74 };
        case 'price_adjustment':
            return { label: 'repriced', tone: 'text-fuchsia-700', dot: 'bg-fuchsia-500', progress: 66 };
        case 'other_misc':
            return { label: 'misc', tone: 'text-slate-700', dot: 'bg-slate-500', progress: 46 };
        default:
            return { label: String(type ?? 'tracked').replaceAll('_', ' '), tone: 'text-slate-700', dot: 'bg-slate-500', progress: 40 };
    }
};

const progressWidth = (type) => `${trackingState(type).progress}%`;
</script>

<template>
    <Head title="Stock Item Movements" />

    <AuthenticatedLayout>
        <PageHeader title="Stock Item Movement Tracking" description="Movement cards and live tracking panels styled like an operations monitor, mapped to your stock item workflow.">
            <Link v-if="canEditMovements" class="btn w-full border-none bg-[linear-gradient(135deg,#6fbb68_0%,#4f9f4a_100%)] text-white shadow-[0_16px_36px_rgba(79,159,74,0.24)] hover:opacity-95 sm:w-auto" :href="route('asset-movements.create')">
                New Movement
            </Link>
        </PageHeader>

        <div v-if="usesPreview" class="rounded-[1.5rem] border border-[#cfe6c8] bg-[linear-gradient(180deg,#fbfefa_0%,#eef8ea_100%)] px-5 py-4 text-sm text-[#5f7b5e] shadow-[0_18px_40px_rgba(79,159,74,0.10)]">
            Showing preview movement cards because there are no recorded stock item movements yet. The layout will use your real movement data automatically once transactions exist.
        </div>

        <div class="grid gap-6 xl:grid-cols-[0.92fr,1.08fr]">
            <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-5 shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
                <div class="rounded-[1.6rem] border border-[#d8e7d4] bg-[linear-gradient(180deg,#ffffff_0%,#f7fcf5_100%)] p-4 text-[#234222] shadow-[inset_0_1px_0_rgba(255,255,255,0.5)]">
                    <div class="flex items-center gap-3 rounded-full border border-[#d8e7d4] bg-[#fbfefa] px-4 py-3">
                        <span class="text-[#7f9a7a]">Search</span>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search item code, description, location"
                            class="w-full bg-transparent text-sm text-[#234222] outline-none placeholder:text-[#7f9a7a]"
                        >
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-white text-[10px] font-semibold uppercase tracking-[0.18em] text-[#4f9f4a] ring-1 ring-[#cfe6c8]">Go</span>
                    </div>

                    <div class="mt-5">
                        <p class="text-lg font-semibold text-[#234222]">Current Movement</p>

                        <article
                            v-if="heroTransaction"
                            class="mt-4 overflow-hidden rounded-[1.8rem] border border-[#d8e7d4] bg-[radial-gradient(circle_at_top_left,rgba(111,187,104,0.14),transparent_24%),linear-gradient(180deg,#ffffff_0%,#f6fbf3_100%)] p-5 text-[#234222] shadow-[0_24px_60px_rgba(79,159,74,0.12)]"
                        >
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold tracking-[0.08em] text-[#2f6f2d]">ITEM : {{ heroTransaction.item_code }}</p>
                                    <p class="mt-1 text-xs text-[#6f8a6b]">{{ heroTransaction.description }}</p>
                                </div>
                                <div class="inline-flex w-fit rounded-full border border-[#cfe6c8] bg-[#eef8ea] px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-[#3c8a39]">
                                    {{ trackingState(heroTransaction.transaction_type).label }}
                                </div>
                            </div>

                            <div class="mt-6 rounded-[1.3rem] border border-[#e1efdc] bg-white px-4 py-4">
                                <div class="flex flex-col gap-2 text-sm text-[#4f6b4b] sm:flex-row sm:items-center sm:justify-between">
                                    <span>{{ heroTransaction.source_location ?? 'Source pending' }}</span>
                                    <span>{{ heroTransaction.destination_location ?? 'Destination pending' }}</span>
                                </div>

                                <div class="relative mt-4 h-10">
                                    <div class="absolute left-0 right-0 top-1/2 h-[2px] -translate-y-1/2 bg-[#d8e7d4]" />
                                    <div class="absolute left-0 top-1/2 h-[2px] -translate-y-1/2 bg-[#4f9f4a]" :style="{ width: progressWidth(heroTransaction.transaction_type) }" />
                                    <div class="absolute left-0 top-1/2 h-3 w-3 -translate-y-1/2 rounded-full border-2 border-[#4f9f4a] bg-white" />
                                    <div class="absolute top-1/2 h-3 w-3 -translate-y-1/2 rounded-full border-2 border-[#4f9f4a] bg-white" :style="{ left: `calc(${progressWidth(heroTransaction.transaction_type)} - 0.375rem)` }" />
                                    <div class="absolute right-0 top-1/2 h-3 w-3 -translate-y-1/2 rounded-full border-2 border-[#d8e7d4] bg-white" />
                                </div>

                                <div class="mt-3 flex items-center justify-between text-[11px] uppercase tracking-[0.14em] text-[#7f9a7a]">
                                    <span>Source</span>
                                    <span>Processing</span>
                                    <span>{{ trackingState(heroTransaction.transaction_type).label }}</span>
                                </div>
                            </div>

                            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                <div class="rounded-2xl border border-[#e1efdc] bg-white px-4 py-3">
                                    <p class="text-[11px] uppercase tracking-[0.2em] text-[#7f9a7a]">Quantity</p>
                                    <p class="mt-2 text-lg font-semibold text-[#234222]">{{ heroTransaction.quantity }}</p>
                                </div>
                                <div class="rounded-2xl border border-[#e1efdc] bg-white px-4 py-3">
                                    <p class="text-[11px] uppercase tracking-[0.2em] text-[#7f9a7a]">Movement Value</p>
                                    <p class="mt-2 text-lg font-semibold text-[#234222]">RM {{ heroTransaction.total_value }}</p>
                                </div>
                            </div>
                        </article>
                    </div>

                    <div class="mt-6 rounded-[1.4rem] border border-[#d8e7d4] bg-[#fbfefa] px-4 py-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-[#234222]">Movement Summary</p>
                                <p class="mt-1 text-xs text-[#6f8a6b]">Live preview of the selected or latest stock item movement route.</p>
                            </div>
                            <div class="inline-flex w-fit rounded-full bg-white px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.22em] text-[#3c8a39] ring-1 ring-[#cfe6c8]">
                                {{ filteredTransactions.length }} tracked
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
                        <p class="text-lg font-semibold text-[#234222]">Stock Flow</p>

                        <div class="mt-4 flex flex-col gap-2 rounded-[1.2rem] border border-[#d8e7d4] bg-[#f4fbf1] p-2 sm:inline-flex sm:flex-row sm:rounded-full sm:p-1">
                            <button
                                type="button"
                                class="w-full rounded-full px-6 py-2 text-sm font-semibold transition sm:min-w-[140px] sm:w-auto"
                                :class="activeTab === 'live' ? 'bg-white text-[#3c8a39] shadow-sm ring-1 ring-[#cfe6c8]' : 'text-[#6f8a6b]'"
                                @click="activeTab = 'live'"
                            >
                                Live
                            </button>
                            <button
                                type="button"
                                class="w-full rounded-full px-6 py-2 text-sm font-semibold transition sm:min-w-[140px] sm:w-auto"
                                :class="activeTab === 'history' ? 'bg-white text-[#3c8a39] shadow-sm ring-1 ring-[#cfe6c8]' : 'text-[#6f8a6b]'"
                                @click="activeTab = 'history'"
                            >
                                History
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
                                <button type="button" class="w-fit rounded-full border border-[#d8e7d4] p-2 text-[#6f8a6b] transition hover:bg-[#eef8ea]">
                                    ...
                                </button>
                            </div>

                            <div class="mt-5 grid gap-4 md:grid-cols-[1fr_auto_1fr] md:items-center">
                                <div>
                                    <p class="text-base font-semibold text-[#234222]">{{ movement.source_location ?? 'Source pending' }}</p>
                                    <p class="mt-1 text-xs text-[#6f8a6b]">{{ movement.transaction_date }}</p>
                                </div>

                                <div class="flex items-center justify-center gap-2 text-[#4f9f4a]">
                                    <span>&gt;&gt;</span>
                                </div>

                                <div class="md:text-right">
                                    <p class="text-base font-semibold text-[#234222]">{{ movement.destination_location ?? 'Destination pending' }}</p>
                                    <p class="mt-1 text-xs text-[#6f8a6b]">{{ movement.created_by ?? 'System' }}</p>
                                </div>
                            </div>

                            <div class="mt-5">
                                <div class="h-[3px] overflow-hidden rounded-full bg-[#d8e7d4]">
                                    <div class="h-full rounded-full bg-[#4f9f4a]" :style="{ width: progressWidth(movement.transaction_type) }" />
                                </div>
                                <div class="mt-2 flex items-center justify-between text-[11px] uppercase tracking-[0.16em] text-[#7f9a7a]">
                                    <span>Movement</span>
                                    <span>Tracking</span>
                                    <span :class="trackingState(movement.transaction_type).tone">{{ trackingState(movement.transaction_type).label }}</span>
                                </div>
                            </div>

                            <div class="mt-5 flex flex-wrap items-center gap-3 text-xs">
                                <span class="rounded-full border border-[#d8e7d4] bg-white px-3 py-1.5 text-[#4f6b4b]">Qty {{ movement.quantity }}</span>
                                <span class="rounded-full border border-[#d8e7d4] bg-white px-3 py-1.5 text-[#4f6b4b]">RM {{ movement.total_value }}</span>
                                <span class="inline-flex items-center gap-2 rounded-full border border-[#d8e7d4] bg-white px-3 py-1.5" :class="trackingState(movement.transaction_type).tone">
                                    <span class="h-2 w-2 rounded-full" :class="trackingState(movement.transaction_type).dot" />
                                    {{ movement.transaction_type.replaceAll('_', ' ') }}
                                </span>
                            </div>
                        </article>

                        <div v-if="visibleTransactions.length === 0" class="rounded-[1.6rem] border border-dashed border-[#cfe6c8] bg-[#fbfefa] px-6 py-12 text-center text-sm text-[#6f8a6b]">
                            No movement cards found for this view.
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
