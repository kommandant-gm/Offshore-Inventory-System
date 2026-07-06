<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    stats: {
        type: Object,
        required: true,
    },
    featuredMovement: {
        type: Object,
        default: null,
    },
    recentMovements: {
        type: Array,
        default: () => [],
    },
    movementMix: {
        type: Array,
        default: () => [],
    },
    weeklyActivity: {
        type: Array,
        default: () => [],
    },
    attentionItems: {
        type: Array,
        default: () => [],
    },
    cogSummary: {
        type: Object,
        default: () => ({ issuedCount: 0, receivedCount: 0 }),
    },
    cogEntries: {
        type: Array,
        default: () => [],
    },
    systemHealth: {
        type: Object,
        required: true,
    },
});

const previewFeaturedMovement = {
    item_code: 'CAT-RACK-012',
    description: 'Cylinder Rack 4x4x5',
    transaction_type: 'interloc_transfer',
    transaction_date: '2026-06-04',
    quantity: 4,
    total_value: 3200,
    source_location: 'Yard Zone A',
    destination_location: 'Project DESB',
    created_by: 'Ops Control',
};

const previewRecentMovements = [
    previewFeaturedMovement,
    {
        id: 'preview-2',
        item_code: 'CAT-ROPE-001',
        description: 'Polyamide Rope 1/2" x 200 MTR',
        transaction_type: 'issue',
        transaction_date: '2026-06-03',
        quantity: 20,
        total_value: 2800,
        source_location: 'Main Store',
        destination_location: 'Offshore Base',
        created_by: 'Inventory Team',
    },
    {
        id: 'preview-3',
        item_code: 'CAT-HOSE-009',
        description: 'Air Hose 2"',
        transaction_type: 'receive',
        transaction_date: '2026-06-02',
        quantity: 12,
        total_value: 1440,
        source_location: 'Vendor',
        destination_location: 'Receiving Bay',
        created_by: 'Store Clerk',
    },
    {
        id: 'preview-4',
        item_code: 'CAT-PAINT-005',
        description: 'Dog Leg Paint Brush 2"',
        transaction_type: 'material_return',
        transaction_date: '2026-06-01',
        quantity: 6,
        total_value: 63,
        source_location: 'Maintenance Team',
        destination_location: 'General Store',
        created_by: 'Maintenance Lead',
    },
];

const previewMovementMix = [
    { type: 'interloc_transfer', label: 'Interloc Transfer', total: 14 },
    { type: 'issue', label: 'Issue', total: 11 },
    { type: 'receive', label: 'Receive', total: 8 },
    { type: 'material_return', label: 'Material Return', total: 5 },
    { type: 'physical_adjustment', label: 'Physical Adjustment', total: 3 },
];

const previewWeeklyActivity = [
    { label: 'Mon', count: 3 },
    { label: 'Tue', count: 6 },
    { label: 'Wed', count: 4 },
    { label: 'Thu', count: 8 },
    { label: 'Fri', count: 5 },
    { label: 'Sat', count: 7 },
    { label: 'Sun', count: 4 },
];

const previewAttentionItems = [
    {
        id: 'preview-item-1',
        item_code: 'CAT-ROPE-001',
        description: 'Polyamide Rope 1/2" x 200 MTR',
        category: 'General Store',
        location: 'Main Store',
        opening_stock: 24,
        current_stock: 20,
        minimum_stock: 10,
    },
    {
        id: 'preview-item-2',
        item_code: 'CAT-RACK-012',
        description: 'Cylinder Rack 4x4x5',
        category: 'Equipment',
        location: 'Yard Zone A',
        opening_stock: 12,
        current_stock: 8,
        minimum_stock: 4,
    },
    {
        id: 'preview-item-3',
        item_code: 'CAT-HOSE-009',
        description: 'Air Hose 2"',
        category: 'General Store',
        location: 'Receiving Bay',
        opening_stock: 18,
        current_stock: 12,
        minimum_stock: 6,
    },
];

const previewCogEntries = [
    {
        id: 'preview-cog-1',
        item_code: 'CAT-RACK-012',
        description: 'Cylinder Rack 4x4x5',
        transaction_date: '2026-06-04',
        transaction_type: 'interloc_transfer',
        cog_issued_out: 'COG-OPS-1042',
        cog_received: '',
        total_value: 3200,
    },
    {
        id: 'preview-cog-2',
        item_code: 'CAT-ROPE-001',
        description: 'Polyamide Rope 1/2" x 200 MTR',
        transaction_date: '2026-06-03',
        transaction_type: 'issue',
        cog_issued_out: 'COG-MAR-881',
        cog_received: '',
        total_value: 2800,
    },
    {
        id: 'preview-cog-3',
        item_code: 'CAT-HOSE-009',
        description: 'Air Hose 2"',
        transaction_date: '2026-06-02',
        transaction_type: 'material_return',
        cog_issued_out: '',
        cog_received: 'COG-RET-223',
        total_value: 1440,
    },
];

const overviewCards = computed(() => [
    { label: 'Stock Items', value: props.stats.assetItems, tone: 'text-[#2f6f2d]', href: route('assets.index') },
    { label: 'Movements Logged', value: props.stats.assetTransactions, tone: 'text-[#4f9f4a]', href: route('asset-movements.index') },
    { label: 'Categories', value: props.stats.categories, tone: 'text-[#5b9c56]', href: route('categories.index') },
    { label: 'Locations', value: props.stats.locations, tone: 'text-[#3c8a39]', href: route('locations.index') },
]);

const hasLiveMovements = computed(() => props.recentMovements.length > 0);
const hasLiveCharts = computed(() => props.weeklyActivity.some((entry) => Number(entry.count ?? 0) > 0));
const hasLiveAttention = computed(() => props.attentionItems.length > 0);
const hasLiveCog = computed(() => props.cogEntries.length > 0);

const dashboardFeaturedMovement = computed(() => props.featuredMovement ?? previewFeaturedMovement);
const dashboardRecentMovements = computed(() => hasLiveMovements.value ? props.recentMovements : previewRecentMovements);
const dashboardMovementMix = computed(() => props.movementMix.length > 0 ? props.movementMix : previewMovementMix);
const dashboardWeeklyActivity = computed(() => hasLiveCharts.value ? props.weeklyActivity : previewWeeklyActivity);
const dashboardAttentionItems = computed(() => hasLiveAttention.value ? props.attentionItems : previewAttentionItems);
const dashboardCogEntries = computed(() => hasLiveCog.value ? props.cogEntries : previewCogEntries);
const dashboardCogSummary = computed(() => hasLiveCog.value ? props.cogSummary : { issuedCount: 2, receivedCount: 1 });
const showPreviewBanner = computed(() => !hasLiveMovements.value || !hasLiveCharts.value || !hasLiveAttention.value || !hasLiveCog.value);

const weeklyPeak = computed(() => Math.max(...dashboardWeeklyActivity.value.map((entry) => Number(entry.count ?? 0)), 1));
const chartPalette = ['#4f9f4a', '#6fbb68', '#86c87b', '#b8e0ae', '#d8e7d4'];
const donutCircumference = 2 * Math.PI * 54;

const inventoryDistribution = computed(() => {
    const segments = [
        { label: 'Stock Items', value: Number(props.stats.assetItems ?? 0), color: '#4f9f4a' },
        { label: 'Movements', value: Number(props.stats.assetTransactions ?? 0), color: '#6fbb68' },
        { label: 'Categories', value: Number(props.stats.categories ?? 0), color: '#86c87b' },
        { label: 'Locations', value: Number(props.stats.locations ?? 0), color: '#b8e0ae' },
    ].filter((segment) => segment.value > 0);

    const total = segments.reduce((sum, segment) => sum + segment.value, 0);

    if (!total) {
        return {
            total: 0,
            background: 'conic-gradient(#d8e7d4 0deg 360deg)',
            segments: [],
        };
    }

    let angle = 0;

    return {
        total,
        background: `conic-gradient(${segments.map((segment) => {
            const start = angle;
            angle += (segment.value / total) * 360;
            return `${segment.color} ${start}deg ${angle}deg`;
        }).join(', ')})`,
        segments: segments.map((segment) => ({
            ...segment,
            share: Math.round((segment.value / total) * 100),
        })),
    };
});

const movementDonutSegments = computed(() => {
    const entries = dashboardMovementMix.value
        .map((entry, index) => ({
            label: entry.label,
            value: Number(entry.total ?? 0),
            color: chartPalette[index % chartPalette.length],
        }))
        .filter((entry) => entry.value > 0);

    const total = entries.reduce((sum, entry) => sum + entry.value, 0);
    let offset = 0;

    return {
        total,
        segments: entries.map((entry) => {
            const length = total ? (entry.value / total) * donutCircumference : 0;
            const segment = {
                ...entry,
                share: total ? Math.round((entry.value / total) * 100) : 0,
                dasharray: `${length} ${Math.max(donutCircumference - length, 0)}`,
                dashoffset: -offset,
            };
            offset += length;
            return segment;
        }),
    };
});

const movementState = (type) => {
    switch (type) {
        case 'receive':
            return { label: 'Received', tone: 'text-[#3c8a39]', bg: 'bg-[#6fbb68]', progress: 100 };
        case 'issue':
            return { label: 'Issued Out', tone: 'text-[#2f6f2d]', bg: 'bg-[#4f9f4a]', progress: 76 };
        case 'interloc_transfer':
            return { label: 'In Transfer', tone: 'text-[#4f9f4a]', bg: 'bg-[#86c87b]', progress: 58 };
        case 'material_return':
            return { label: 'Returned', tone: 'text-[#3c8a39]', bg: 'bg-[#6fbb68]', progress: 88 };
        case 'physical_adjustment':
            return { label: 'Adjusted', tone: 'text-[#5b9c56]', bg: 'bg-[#b8e0ae]', progress: 68 };
        case 'price_adjustment':
            return { label: 'Repriced', tone: 'text-[#4f9f4a]', bg: 'bg-[#86c87b]', progress: 64 };
        default:
            return { label: String(type ?? 'Movement').replaceAll('_', ' '), tone: 'text-[#5f7b5e]', bg: 'bg-[#d8e7d4]', progress: 44 };
    }
};

const formatNumber = (value) => Number(value ?? 0).toFixed(2).replace('.00', '');
const formatMoney = (value) => Number(value ?? 0).toFixed(2);
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <PageHeader
            title="Operations Dashboard"
            description="High-level stock item control with movement tracking, activity charts, and inventory attention points."
        >
            <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row">
                <Link class="btn w-full border border-[#d8e7d4] bg-white text-[#234222] shadow-sm hover:bg-[#eef8ea] sm:w-auto" :href="route('asset-movements.index')">Open Tracking</Link>
                <Link class="btn w-full border-none bg-[linear-gradient(135deg,#6fbb68_0%,#4f9f4a_100%)] text-white shadow-[0_16px_36px_rgba(79,159,74,0.24)] hover:opacity-95 sm:w-auto" :href="route('asset-movements.create')">New Movement</Link>
            </div>
        </PageHeader>

        <div v-if="showPreviewBanner" class="rounded-[1.5rem] border border-[#cfe6ca] bg-[linear-gradient(180deg,#ffffff_0%,#eef8ea_100%)] px-5 py-4 text-sm text-[#355733] shadow-[0_18px_40px_rgba(79,159,74,0.10)]">
            Showing dashboard preview data for sections that do not have enough live records yet. Real movements and stock activity will replace these previews automatically.
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <Link
                v-for="card in overviewCards"
                :key="card.label"
                :href="card.href"
                class="rounded-[1.7rem] border border-[#d8e7d4] bg-[linear-gradient(180deg,#ffffff_0%,#f3fbef_100%)] p-5 shadow-[0_18px_38px_rgba(79,159,74,0.10)] transition hover:border-[#b8e0ae]"
            >
                <p class="text-xs uppercase tracking-[0.24em] text-[#7f9a7a]">{{ card.label }}</p>
                <div class="mt-3 flex items-end justify-between">
                    <p class="text-3xl font-semibold" :class="card.tone">{{ card.value }}</p>
                    <div class="flex gap-1.5">
                        <span
                            v-for="bar in 5"
                            :key="bar"
                            class="w-2 rounded-full bg-[#6fbb68]"
                            :style="{ height: `${18 + (bar * 5)}px` }"
                        />
                    </div>
                </div>
            </Link>
        </div>

        <div class="grid gap-6 2xl:grid-cols-[1.12fr,0.88fr]">
            <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-5 shadow-[0_18px_40px_rgba(79,159,74,0.10)]">
                <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-sm text-[#7f9a7a]">Movement Highlight</p>
                        <h2 class="text-[1.7rem] font-semibold leading-tight text-[#234222] sm:text-2xl">Live Tracking Focus</h2>
                    </div>
                    <span class="inline-flex w-fit rounded-full border border-[#b8e0ae] bg-[#eef8ea] px-4 py-1.5 text-[11px] font-semibold uppercase tracking-[0.24em] text-[#3c8a39]">
                        {{ systemHealth.movementCountToday }} today
                    </span>
                </div>

                <div class="grid gap-5 2xl:grid-cols-[1.15fr,0.85fr]">
                    <article class="overflow-hidden rounded-[1.8rem] border border-[#d8e7d4] bg-[linear-gradient(180deg,#ffffff_0%,#f4fbf1_100%)] p-5 text-[#234222] shadow-[0_22px_50px_rgba(79,159,74,0.12)]">
                        <template v-if="dashboardFeaturedMovement">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <p class="break-words text-sm font-semibold tracking-[0.08em] text-[#355733]">{{ dashboardFeaturedMovement.item_code }}</p>
                                    <p class="mt-1 text-sm leading-6 text-[#6c8c69]">{{ dashboardFeaturedMovement.description }}</p>
                                </div>
                                <div class="inline-flex w-fit rounded-full border border-[#b8e0ae] bg-[#eef8ea] px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em]" :class="movementState(dashboardFeaturedMovement.transaction_type).tone">
                                    {{ movementState(dashboardFeaturedMovement.transaction_type).label }}
                                </div>
                            </div>

                            <div class="mt-6 rounded-[1.3rem] border border-[#d8e7d4] bg-[#fbfefa] px-4 py-4">
                                <div class="grid gap-2 text-sm text-[#5f7b5e] sm:grid-cols-2 sm:items-center">
                                    <span class="break-words">{{ dashboardFeaturedMovement.source_location ?? 'Source pending' }}</span>
                                    <span class="break-words sm:text-right">{{ dashboardFeaturedMovement.destination_location ?? 'Destination pending' }}</span>
                                </div>

                                <div class="relative mt-4 h-10">
                                    <div class="absolute left-0 right-0 top-1/2 h-[2px] -translate-y-1/2 bg-[#d8e7d4]" />
                                    <div class="absolute left-0 top-1/2 h-[2px] -translate-y-1/2 bg-[#6fbb68]" :style="{ width: `${movementState(dashboardFeaturedMovement.transaction_type).progress}%` }" />
                                    <div class="absolute left-0 top-1/2 h-3 w-3 -translate-y-1/2 rounded-full border-2 border-[#6fbb68] bg-white" />
                                    <div class="absolute top-1/2 h-3 w-3 -translate-y-1/2 rounded-full border-2 border-[#6fbb68] bg-white" :style="{ left: `calc(${movementState(dashboardFeaturedMovement.transaction_type).progress}% - 0.375rem)` }" />
                                    <div class="absolute right-0 top-1/2 h-3 w-3 -translate-y-1/2 rounded-full border-2 border-[#d8e7d4] bg-white" />
                                </div>

                                <div class="mt-3 flex items-center justify-between text-[11px] uppercase tracking-[0.16em] text-[#7f9a7a]">
                                    <span>Source</span>
                                    <span>{{ dashboardFeaturedMovement.transaction_date }}</span>
                                    <span>{{ movementState(dashboardFeaturedMovement.transaction_type).label }}</span>
                                </div>
                            </div>

                            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                <div class="rounded-2xl border border-[#d8e7d4] bg-[#fbfefa] px-4 py-3">
                                    <p class="text-[11px] uppercase tracking-[0.2em] text-[#7f9a7a]">Quantity</p>
                                    <p class="mt-2 text-base font-semibold text-[#234222] sm:text-lg">{{ formatNumber(dashboardFeaturedMovement.quantity) }}</p>
                                </div>
                                <div class="rounded-2xl border border-[#d8e7d4] bg-[#fbfefa] px-4 py-3">
                                    <p class="text-[11px] uppercase tracking-[0.2em] text-[#7f9a7a]">Value</p>
                                    <p class="mt-2 text-base font-semibold text-[#3c8a39] sm:text-lg">RM {{ formatMoney(dashboardFeaturedMovement.total_value) }}</p>
                                </div>
                                <div class="rounded-2xl border border-[#d8e7d4] bg-[#fbfefa] px-4 py-3">
                                    <p class="text-[11px] uppercase tracking-[0.2em] text-[#7f9a7a]">Handled By</p>
                                    <p class="mt-2 break-words text-sm font-semibold text-[#234222]">{{ dashboardFeaturedMovement.created_by ?? 'System' }}</p>
                                </div>
                            </div>
                        </template>
                    </article>

                    <div class="space-y-5">
                        <section class="rounded-[1.7rem] border border-[#d8e7d4] bg-[linear-gradient(180deg,#ffffff_0%,#f4fbf1_100%)] p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm text-[#7f9a7a]">Movement Mix</p>
                                    <h3 class="text-base font-semibold text-[#234222] sm:text-lg">Activity Balance</h3>
                                </div>
                                <span class="inline-flex w-fit rounded-full border border-[#d8e7d4] bg-white px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-[#3c8a39]">
                                    Top Types
                                </span>
                            </div>

                            <div class="mt-5 space-y-4">
                                <div v-for="entry in dashboardMovementMix" :key="entry.type" class="space-y-2">
                                    <div class="flex items-start justify-between gap-4 text-sm">
                                        <span class="min-w-0 break-words text-[#5f7b5e]">{{ entry.label }}</span>
                                        <span class="text-[#3c8a39]">{{ entry.total }}</span>
                                    </div>
                                    <div class="h-2 overflow-hidden rounded-full bg-[#dfe9db]">
                                        <div class="h-full rounded-full bg-[linear-gradient(90deg,#86c87b_0%,#4f9f4a_100%)]" :style="{ width: `${(entry.total / Math.max(...dashboardMovementMix.map(item => item.total), 1)) * 100}%` }" />
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="rounded-[1.7rem] border border-[#d8e7d4] bg-[linear-gradient(180deg,#ffffff_0%,#f4fbf1_100%)] p-5">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-[#7f9a7a]">System Pulse</p>
                                    <h3 class="text-lg font-semibold text-[#234222]">Current Health</h3>
                                </div>
                            </div>

                            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                <div class="rounded-2xl border border-[#d8e7d4] bg-[#fbfefa] px-4 py-4">
                                    <p class="text-[11px] uppercase tracking-[0.2em] text-[#7f9a7a]">Active Items</p>
                                    <p class="mt-2 text-xl font-semibold text-[#234222] sm:text-2xl">{{ systemHealth.activeItems }}</p>
                                </div>
                                <div class="rounded-2xl border border-[#d8e7d4] bg-[#fbfefa] px-4 py-4">
                                    <p class="text-[11px] uppercase tracking-[0.2em] text-[#7f9a7a]">Movements Today</p>
                                    <p class="mt-2 text-xl font-semibold text-[#3c8a39] sm:text-2xl">{{ systemHealth.movementCountToday }}</p>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </section>

            <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-5 shadow-[0_18px_40px_rgba(79,159,74,0.10)]">
                <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-sm text-[#7f9a7a]">Weekly Activity</p>
                        <h2 class="text-2xl font-semibold text-[#234222]">Movement Trend</h2>
                    </div>
                    <span class="inline-flex w-fit rounded-full border border-[#d8e7d4] bg-[#fbfefa] px-4 py-1.5 text-[11px] font-semibold uppercase tracking-[0.24em] text-[#5f7b5e]">
                        Last 7 days
                    </span>
                </div>

                <div class="rounded-[1.7rem] border border-[#d8e7d4] bg-[linear-gradient(180deg,#ffffff_0%,#f4fbf1_100%)] p-5">
                        <div class="flex h-52 items-end gap-2 sm:gap-4">
                        <div v-for="entry in dashboardWeeklyActivity" :key="entry.label" class="flex flex-1 flex-col items-center gap-3">
                            <div class="flex w-full items-end justify-center rounded-t-[1rem] bg-[#edf4ea] px-2 pt-2" style="height: 170px;">
                                <div
                                    class="w-full rounded-[0.9rem] bg-[linear-gradient(180deg,#86c87b_0%,#4f9f4a_100%)] shadow-[0_10px_28px_rgba(79,159,74,0.22)]"
                                    :style="{ height: `${Math.max((entry.count / weeklyPeak) * 140, entry.count ? 24 : 10)}px` }"
                                />
                            </div>
                            <div class="text-center">
                                <p class="text-sm font-semibold text-[#234222]">{{ entry.count }}</p>
                                <p class="text-xs uppercase tracking-[0.16em] text-[#7f9a7a]">{{ entry.label }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <section class="mt-6 rounded-[1.7rem] border border-[#d8e7d4] bg-[linear-gradient(180deg,#ffffff_0%,#f4fbf1_100%)] p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-[#7f9a7a]">Chart Panel</p>
                            <h3 class="text-lg font-semibold text-[#234222]">Inventory Distribution</h3>
                        </div>
                        <span class="inline-flex w-fit rounded-full border border-[#d8e7d4] bg-white px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-[#3c8a39]">
                            Inventory
                        </span>
                    </div>

                    <div class="mt-5 rounded-[1.5rem] border border-[#d8e7d4] bg-white p-5">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-[11px] uppercase tracking-[0.2em] text-[#7f9a7a]">Inventory Pie</p>
                                <p class="mt-1 text-sm font-semibold text-[#234222]">Record Distribution</p>
                            </div>
                            <p class="text-sm font-semibold text-[#3c8a39]">{{ inventoryDistribution.total }}</p>
                        </div>

                        <div class="mt-5 space-y-5">
                            <div class="mx-auto w-fit">
                                <div class="relative h-44 w-44 rounded-full border border-[#d8e7d4] shadow-[0_12px_30px_rgba(79,159,74,0.12)]" :style="{ background: inventoryDistribution.background }">
                                    <div class="absolute inset-[22%] rounded-full border border-[#e2efdd] bg-white" />
                                </div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                <div v-for="segment in inventoryDistribution.segments" :key="segment.label" class="flex min-w-0 items-center justify-between gap-3 rounded-xl border border-[#edf3eb] bg-[#fbfefa] px-4 py-3">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <span class="h-3 w-3 rounded-full" :style="{ backgroundColor: segment.color }" />
                                        <span class="truncate text-sm text-[#355733]">{{ segment.label }}</span>
                                    </div>
                                    <span class="text-sm font-semibold text-[#234222]">{{ segment.share }}%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="mt-6 rounded-[1.7rem] border border-[#d8e7d4] bg-[linear-gradient(180deg,#ffffff_0%,#f4fbf1_100%)] p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-[#7f9a7a]">Chart Panel</p>
                            <h3 class="text-lg font-semibold text-[#234222]">Movement Type Allocation</h3>
                        </div>
                        <span class="inline-flex w-fit rounded-full border border-[#d8e7d4] bg-white px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-[#3c8a39]">
                            Movement
                        </span>
                    </div>

                    <div class="mt-5 rounded-[1.5rem] border border-[#d8e7d4] bg-white p-5">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-[11px] uppercase tracking-[0.2em] text-[#7f9a7a]">Movement Donut</p>
                                <p class="mt-1 text-sm font-semibold text-[#234222]">Type Allocation</p>
                            </div>
                            <p class="text-sm font-semibold text-[#3c8a39]">{{ movementDonutSegments.total }}</p>
                        </div>

                        <div class="mt-5 space-y-5">
                            <div class="relative mx-auto flex h-44 w-44 items-center justify-center">
                                <svg class="h-44 w-44 -rotate-90" viewBox="0 0 120 120" aria-hidden="true">
                                    <circle cx="60" cy="60" r="54" fill="none" stroke="#e4efe0" stroke-width="12" />
                                    <circle
                                        v-for="segment in movementDonutSegments.segments"
                                        :key="segment.label"
                                        cx="60"
                                        cy="60"
                                        r="54"
                                        fill="none"
                                        :stroke="segment.color"
                                        stroke-width="12"
                                        stroke-linecap="round"
                                        :stroke-dasharray="segment.dasharray"
                                        :stroke-dashoffset="segment.dashoffset"
                                    />
                                </svg>
                                <div class="absolute text-center">
                                    <p class="text-[11px] uppercase tracking-[0.18em] text-[#7f9a7a]">Mix</p>
                                    <p class="mt-1 text-3xl font-semibold text-[#234222]">{{ movementDonutSegments.total }}</p>
                                </div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                <div v-for="segment in movementDonutSegments.segments" :key="segment.label" class="flex min-w-0 items-center justify-between gap-3 rounded-xl border border-[#edf3eb] bg-[#fbfefa] px-4 py-3">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <span class="h-3 w-3 rounded-full" :style="{ backgroundColor: segment.color }" />
                                        <span class="truncate text-sm text-[#355733]">{{ segment.label }}</span>
                                    </div>
                                    <span class="text-sm font-semibold text-[#234222]">{{ segment.value }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="mt-6 grid gap-6 2xl:grid-cols-[1fr,1fr]">
                    <section class="rounded-[1.7rem] border border-[#d8e7d4] bg-[linear-gradient(180deg,#ffffff_0%,#f4fbf1_100%)] p-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-[#7f9a7a]">Recent Movement Feed</p>
                                <h3 class="text-lg font-semibold text-[#234222]">Latest Logs</h3>
                            </div>
                            <Link class="text-sm font-medium text-[#3c8a39] hover:text-[#2f6f2d]" :href="route('asset-movements.index')">View all</Link>
                        </div>

                        <div class="mt-5 space-y-3">
                            <article
                                v-for="movement in dashboardRecentMovements"
                                :key="movement.id"
                                class="rounded-2xl border border-[#d8e7d4] bg-[#fbfefa] px-4 py-4"
                            >
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-[#234222]">{{ movement.item_code }}</p>
                                        <p class="mt-1 text-xs leading-5 text-[#6c8c69]">{{ movement.description }}</p>
                                    </div>
                                    <span class="inline-flex w-fit rounded-full border border-[#b8e0ae] bg-[#eef8ea] px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em]" :class="movementState(movement.transaction_type).tone">
                                        {{ movementState(movement.transaction_type).label }}
                                    </span>
                                </div>
                                <div class="mt-3 flex flex-col gap-1 text-xs text-[#6c8c69] sm:flex-row sm:items-center sm:justify-between">
                                    <span class="break-words">{{ movement.source_location ?? 'Source pending' }} to {{ movement.destination_location ?? 'Destination pending' }}</span>
                                    <span>{{ movement.transaction_date }}</span>
                                </div>
                            </article>
                        </div>
                    </section>

                    <section class="rounded-[1.7rem] border border-[#d8e7d4] bg-[linear-gradient(180deg,#ffffff_0%,#f4fbf1_100%)] p-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-[#7f9a7a]">Attention Items</p>
                                <h3 class="text-lg font-semibold text-[#234222]">Stock Focus</h3>
                            </div>
                            <Link class="text-sm font-medium text-[#3c8a39] hover:text-[#2f6f2d]" :href="route('assets.index')">Open assets</Link>
                        </div>

                        <div class="mt-5 space-y-3">
                            <article
                                v-for="item in dashboardAttentionItems"
                                :key="item.id"
                                class="rounded-2xl border border-[#d8e7d4] bg-[#fbfefa] px-4 py-4"
                            >
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-[#234222]">{{ item.item_code }}</p>
                                        <p class="mt-1 text-xs leading-5 text-[#6c8c69]">{{ item.description }}</p>
                                    </div>
                                    <span class="inline-flex w-fit rounded-full border border-[#d8e7d4] bg-white px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-[#4f9f4a]">
                                        {{ item.category }}
                                    </span>
                                </div>
                                <div class="mt-4 grid gap-3 text-xs sm:grid-cols-3">
                                    <div class="rounded-xl border border-[#edf3eb] bg-white px-3 py-3">
                                        <p class="text-[#7f9a7a]">Opening</p>
                                        <p class="mt-1 text-[#234222]">{{ formatNumber(item.opening_stock) }}</p>
                                    </div>
                                    <div class="rounded-xl border border-[#edf3eb] bg-white px-3 py-3">
                                        <p class="text-[#7f9a7a]">Current</p>
                                        <p class="mt-1 text-[#3c8a39]">{{ formatNumber(item.current_stock) }}</p>
                                    </div>
                                    <div class="rounded-xl border border-[#edf3eb] bg-white px-3 py-3">
                                        <p class="text-[#7f9a7a]">Minimum</p>
                                        <p class="mt-1 text-[#234222]">{{ formatNumber(item.minimum_stock) }}</p>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </section>
                </div>

                <section class="mt-6 rounded-[1.7rem] border border-[#d8e7d4] bg-[linear-gradient(180deg,#ffffff_0%,#f4fbf1_100%)] p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-sm text-[#7f9a7a]">COG Control</p>
                            <h3 class="text-lg font-semibold text-[#234222]">Issued And Received References</h3>
                        </div>
                        <span class="inline-flex w-fit rounded-full border border-[#d8e7d4] bg-white px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-[#3c8a39]">
                            COG
                        </span>
                    </div>

                    <div class="mt-5 grid gap-4 2xl:grid-cols-[0.78fr,1.22fr]">
                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                            <div class="rounded-2xl border border-[#d8e7d4] bg-[#fbfefa] px-4 py-4">
                                <p class="text-[11px] uppercase tracking-[0.2em] text-[#7f9a7a]">COG Issued Out</p>
                                <p class="mt-2 text-xl font-semibold text-[#3c8a39] sm:text-2xl">{{ dashboardCogSummary.issuedCount }}</p>
                            </div>
                            <div class="rounded-2xl border border-[#d8e7d4] bg-[#fbfefa] px-4 py-4">
                                <p class="text-[11px] uppercase tracking-[0.2em] text-[#7f9a7a]">COG Received</p>
                                <p class="mt-2 text-xl font-semibold text-[#2f6f2d] sm:text-2xl">{{ dashboardCogSummary.receivedCount }}</p>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <article
                                v-for="entry in dashboardCogEntries"
                                :key="entry.id"
                                class="rounded-2xl border border-[#d8e7d4] bg-[#fbfefa] px-4 py-4"
                            >
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-[#234222]">{{ entry.item_code }}</p>
                                        <p class="mt-1 text-xs leading-5 text-[#6c8c69]">{{ entry.description }}</p>
                                    </div>
                                    <span class="inline-flex w-fit rounded-full border border-[#b8e0ae] bg-[#eef8ea] px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em]" :class="movementState(entry.transaction_type).tone">
                                        {{ movementState(entry.transaction_type).label }}
                                    </span>
                                </div>

                                <div class="mt-4 grid gap-3 text-xs md:grid-cols-3">
                                    <div class="rounded-xl border border-[#edf3eb] bg-white px-3 py-3">
                                        <p class="text-[#7f9a7a]">Date</p>
                                        <p class="mt-1 text-[#234222]">{{ entry.transaction_date }}</p>
                                    </div>
                                    <div class="rounded-xl border border-[#edf3eb] bg-white px-3 py-3">
                                        <p class="text-[#7f9a7a]">COG Issued</p>
                                        <p class="mt-1 text-[#3c8a39]">{{ entry.cog_issued_out || '-' }}</p>
                                    </div>
                                    <div class="rounded-xl border border-[#edf3eb] bg-white px-3 py-3">
                                        <p class="text-[#7f9a7a]">COG Received</p>
                                        <p class="mt-1 text-[#2f6f2d]">{{ entry.cog_received || '-' }}</p>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </div>
                </section>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
