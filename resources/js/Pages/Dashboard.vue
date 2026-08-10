<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { ArrowRightIcon, PlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    stats: Object,
    inventorySummary: Object,
    stockStatus: { type: Array, default: () => [] },
    categoryDistribution: { type: Array, default: () => [] },
    locationDistribution: { type: Array, default: () => [] },
    recentMovements: { type: Array, default: () => [] },
    movementMix: { type: Array, default: () => [] },
    weeklyActivity: { type: Array, default: () => [] },
    attentionItems: { type: Array, default: () => [] },
    cogSummary: { type: Object, default: () => ({ issuedCount: 0, receivedCount: 0 }) },
    cogEntries: { type: Array, default: () => [] },
    systemHealth: Object,
});

const page = usePage();
const isMuhdIsa = page.props.auth?.user?.username?.toLowerCase() === 'muhd.isa';
const arsenalMode = ref(isMuhdIsa && typeof window !== 'undefined' && localStorage.getItem('arsenal.mode') === 'true');
const updateArsenalMode = (event) => { arsenalMode.value = Boolean(event.detail); };
onMounted(() => window.addEventListener('arsenal-mode-change', updateArsenalMode));
onBeforeUnmount(() => window.removeEventListener('arsenal-mode-change', updateArsenalMode));
const canReadMovements = computed(() => page.props.auth?.user?.can?.movements_read);
const canEditMovements = computed(() => page.props.auth?.user?.can?.movements_edit);
const palette = ['#10b981', '#f59e0b', '#ef4444', '#3b82f6', '#8b5cf6', '#06b6d4', '#f97316'];
const number = (value) => new Intl.NumberFormat('en-MY', { maximumFractionDigits: 2 }).format(Number(value ?? 0));
const money = (value) => new Intl.NumberFormat('en-MY', { style: 'currency', currency: 'MYR', maximumFractionDigits: 0 }).format(Number(value ?? 0));
const sum = (items, key = 'value') => items.reduce((total, item) => total + Number(item[key] ?? 0), 0);
const maximum = (items, key = 'value') => Math.max(...items.map((item) => Number(item[key] ?? 0)), 1);
const percentage = (value, total) => total ? Math.round((Number(value) / Number(total)) * 100) : 0;
const healthyPercent = computed(() => percentage(props.inventorySummary.healthy, props.inventorySummary.active_items));
const readyPercent = computed(() => percentage(props.inventorySummary.in_stock, props.inventorySummary.active_items));
const weeklyPeak = computed(() => maximum(props.weeklyActivity, 'count'));
const movementTotal = computed(() => sum(props.movementMix, 'total'));
const statusTotal = computed(() => sum(props.stockStatus));
const statusPie = computed(() => {
    if (!statusTotal.value) return 'conic-gradient(#e2e8f0 0 360deg)';
    let angle = 0;
    return `conic-gradient(${props.stockStatus.map((item, index) => {
        const start = angle;
        angle += (Number(item.value) / statusTotal.value) * 360;
        return `${palette[index]} ${start}deg ${angle}deg`;
    }).join(',')})`;
});
const movementLabel = (type) => ({
    receive: 'Received', issue: 'Issued Out', interloc_transfer: 'Transfer', material_return: 'Returned',
    physical_adjustment: 'Physical Adjustment', price_adjustment: 'Price Adjustment',
}[type] ?? String(type ?? 'Movement').replaceAll('_', ' '));
const movementTone = (type) => ({
    receive: 'bg-emerald-50 text-emerald-700', issue: 'bg-blue-50 text-blue-700', interloc_transfer: 'bg-violet-50 text-violet-700',
    material_return: 'bg-teal-50 text-teal-700', physical_adjustment: 'bg-amber-50 text-amber-700', price_adjustment: 'bg-orange-50 text-orange-700',
}[type] ?? 'bg-slate-100 text-slate-700');
const cards = computed(() => [
    { label: 'Active Stock Items', value: props.inventorySummary.active_items, note: `${props.stats.categories} categories`, color: 'bg-[#234222]', icon: 'T' },
    { label: 'In Stock', value: props.inventorySummary.in_stock, note: `${readyPercent.value}% of active items`, color: 'bg-blue-500', icon: 'I' },
    { label: 'Healthy Stock', value: props.inventorySummary.healthy, note: `${healthyPercent.value}% above minimum`, color: 'bg-emerald-500', icon: 'H' },
    { label: 'Low / Out of Stock', value: props.inventorySummary.low_stock + props.inventorySummary.out_of_stock, note: `${props.inventorySummary.out_of_stock} fully depleted`, color: 'bg-amber-500', icon: 'L' },
]);
</script>

<template>
    <Head title="Miri Inventory Dashboard" />
    <AuthenticatedLayout>
        <section :class="['dashboard-shell space-y-6', { 'arsenal-dashboard': arsenalMode }]">
            <header :class="arsenalMode ? 'bg-[linear-gradient(110deg,#071d49_0%,#123b78_58%,#db0007_100%)] shadow-[0_24px_70px_rgba(219,0,7,.28)]' : 'bg-[linear-gradient(110deg,#064e3b_0%,#0f766e_58%,#155e75_100%)] shadow-[0_24px_70px_rgba(6,78,59,.22)]'" class="relative isolate overflow-hidden rounded-[1.8rem] px-6 py-7 text-white sm:px-9 sm:py-9">
                <div class="pointer-events-none absolute -right-20 -top-28 h-80 w-80 rounded-full bg-cyan-300/20 blur-3xl"></div>
                <div class="pointer-events-none absolute -bottom-32 left-1/3 h-72 w-72 rounded-full bg-emerald-300/15 blur-3xl"></div>
                <div class="relative grid items-end gap-8 lg:grid-cols-[minmax(0,1fr),22rem]">
                    <div>
                        <p class="eyebrow text-emerald-200">Miri Inventory</p>
                        <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl lg:text-[2.75rem]">Inventory Dashboard</h1>
                        <p class="mt-3 max-w-2xl text-sm leading-6 text-white/70 sm:text-base">Live stock overview, inventory health, movement activity, location distribution, and attention points in one responsive workspace.</p>
                        <div class="mt-6 flex flex-wrap gap-3">
                            <Link :href="route('assets.index')" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-emerald-900 shadow-sm transition hover:-translate-y-0.5 hover:bg-emerald-50">View stock register <ArrowRightIcon class="h-4 w-4" /></Link>
                            <Link v-if="canEditMovements" :href="route('asset-movements.create')" class="inline-flex items-center gap-2 rounded-xl border border-white/20 bg-white/10 px-4 py-2.5 text-sm font-bold text-white backdrop-blur transition hover:bg-white/15"><PlusIcon class="h-4 w-4" /> Record movement</Link>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3 rounded-2xl border border-white/10 bg-white/[.07] p-4 backdrop-blur-sm">
                        <div class="rounded-xl bg-black/10 p-4"><p class="eyebrow text-white/50">Stock health</p><p class="mt-2 text-3xl font-black text-cyan-200">{{ healthyPercent }}%</p><p class="mt-1 text-xs text-white/55">{{ inventorySummary.healthy }} healthy items</p></div>
                        <div class="rounded-xl bg-black/10 p-4"><p class="eyebrow text-white/50">Available now</p><p class="mt-2 text-3xl font-black text-emerald-300">{{ readyPercent }}%</p><p class="mt-1 text-xs text-white/55">{{ inventorySummary.in_stock }} in stock</p></div>
                    </div>
                </div>
            </header>

            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <Link v-for="card in cards" :key="card.label" :href="route('assets.index')" class="group relative overflow-hidden rounded-2xl border border-[#d8e7d4] bg-white p-4 text-left shadow-[0_8px_28px_rgba(39,89,45,.06)] transition hover:-translate-y-1 hover:shadow-lg sm:p-5">
                    <span class="absolute inset-x-0 top-0 h-1" :class="card.color"></span><div class="flex items-start justify-between gap-3"><div><p class="eyebrow text-[#70836e]">{{ card.label }}</p><p class="mt-2 text-2xl font-black text-[#173a21] sm:text-3xl">{{ card.value }}</p><p class="mt-1 text-[11px] font-semibold text-[#8a9a88]">{{ card.note }}</p></div><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#edf5ea] text-sm font-black text-[#234222]">{{ card.icon }}</span></div>
                </Link>
            </div>

            <div class="grid gap-6 lg:grid-cols-12">
                <article class="panel lg:col-span-5">
                    <div class="flex items-start justify-between gap-3"><div><p class="eyebrow text-blue-600">Inventory health</p><h2 class="panel-title">Stock status</h2><p class="panel-note">Active catalogue items measured against available stock and minimum levels.</p></div><div class="text-right"><strong class="text-2xl text-slate-800">{{ inventorySummary.active_items }}</strong><p class="eyebrow text-slate-400">Items</p></div></div>
                    <div class="mt-6 grid items-center gap-6 sm:grid-cols-[12rem,1fr]">
                        <div class="relative mx-auto h-44 w-44 rounded-full" :style="{ background: statusPie }"><div class="absolute inset-7 flex flex-col items-center justify-center rounded-full bg-white shadow-inner"><strong class="text-3xl text-slate-800">{{ inventorySummary.active_items }}</strong><span class="eyebrow mt-1 text-slate-400">Active</span></div></div>
                        <div class="space-y-4"><div v-for="(item, index) in stockStatus" :key="item.key" class="grid grid-cols-[.75rem,1fr,auto] items-center gap-3"><span class="h-3 w-3 rounded-full" :style="{ backgroundColor: palette[index] }"></span><span><strong class="block text-sm text-slate-700">{{ item.label }}</strong><small class="text-slate-400">{{ percentage(item.value, statusTotal) }}% of items</small></span><strong class="text-sm text-slate-800">{{ item.value }}</strong></div></div>
                    </div>
                    <div class="mt-6 grid grid-cols-2 gap-3 border-t border-slate-100 pt-5"><div class="rounded-xl bg-emerald-50 p-3"><p class="eyebrow text-emerald-600">Stock quantity</p><p class="mt-2 text-xl font-black text-emerald-900">{{ number(inventorySummary.total_quantity) }}</p></div><div class="rounded-xl bg-blue-50 p-3"><p class="eyebrow text-blue-600">Inventory value</p><p class="mt-2 text-xl font-black text-blue-900">{{ money(inventorySummary.total_value) }}</p></div></div>
                </article>

                <article class="panel lg:col-span-7">
                    <div class="flex items-start justify-between gap-3"><div><p class="eyebrow text-violet-600">Inventory mix</p><h2 class="panel-title">Stock items by category</h2><p class="panel-note">Catalogue size and current quantity across the largest categories.</p></div><span class="rounded-xl bg-violet-50 px-3 py-2 text-xs font-bold text-violet-700">{{ stats.categories }} categories</span></div>
                    <div class="mt-6 space-y-3"><Link v-for="(item, index) in categoryDistribution" :key="item.label" :href="route('assets.index', item.key ? { category: item.key } : {})" class="grid grid-cols-[minmax(7rem,10rem),minmax(0,1fr),4.5rem] items-center gap-3 rounded-xl p-1.5 transition hover:bg-violet-50/50"><span class="truncate text-xs font-bold text-slate-600" :title="item.label">{{ item.label }}</span><span class="h-8 overflow-hidden rounded-lg bg-slate-100 p-1"><span class="block h-full min-w-[.4rem] rounded-md bg-gradient-to-r transition-all duration-500" :class="index % 2 ? 'from-blue-500 to-violet-600' : 'from-emerald-500 to-teal-600'" :style="{ width: `${(item.value / maximum(categoryDistribution)) * 100}%` }"></span></span><span class="text-right"><strong class="block text-sm text-slate-800">{{ item.value }}</strong><small class="text-[10px] text-slate-400">{{ number(item.quantity) }} qty</small></span></Link><p v-if="!categoryDistribution.length" class="empty-state">No stock categories recorded.</p></div>
                </article>

                <article class="panel lg:col-span-7">
                    <div class="flex items-start justify-between gap-3"><div><p class="eyebrow text-emerald-600">Movement activity</p><h2 class="panel-title">Last seven days</h2><p class="panel-note">Daily inventory transactions posted this week.</p></div><div class="rounded-xl bg-emerald-50 px-3 py-2 text-right"><strong class="block text-lg text-emerald-700">{{ sum(weeklyActivity, 'count') }}</strong><span class="eyebrow text-emerald-500">Movements</span></div></div>
                    <div class="mt-7 flex h-52 items-end gap-3 border-b border-slate-200 px-2"><div v-for="entry in weeklyActivity" :key="entry.label" class="flex h-full flex-1 flex-col justify-end text-center"><span class="mb-2 text-xs font-black text-slate-600">{{ entry.count }}</span><span class="mx-auto block w-3/5 rounded-t-lg bg-[linear-gradient(180deg,#34d399,#0f766e)] transition-all duration-500" :style="{ height: `${Math.max((entry.count / weeklyPeak) * 75, entry.count ? 8 : 2)}%` }"></span><span class="mt-2 text-[10px] font-bold text-slate-400">{{ entry.label }}</span></div></div>
                </article>

                <article class="panel lg:col-span-5">
                    <div><p class="eyebrow text-sky-600">Movement allocation</p><h2 class="panel-title">Transaction mix</h2><p class="panel-note">Distribution of all recorded movement types.</p></div>
                    <div class="mt-6 space-y-4"><div v-for="(item, index) in movementMix" :key="item.type" class="grid grid-cols-[8rem,1fr,2.5rem] items-center gap-3"><span class="truncate text-xs font-bold text-slate-600">{{ item.label }}</span><span class="h-7 overflow-hidden rounded-lg bg-slate-100"><span class="flex h-full min-w-[.35rem] items-center justify-end rounded-lg pr-2 text-[10px] font-bold text-white" :style="{ width: `${percentage(item.total, movementTotal)}%`, backgroundColor: palette[(index + 3) % palette.length] }"></span></span><strong class="text-right text-sm text-slate-800">{{ item.total }}</strong></div><p v-if="!movementMix.length" class="empty-state">No movements recorded.</p></div>
                </article>

                <article class="panel lg:col-span-12">
                    <div class="flex items-start justify-between gap-3"><div><p class="eyebrow text-violet-600">Storage footprint</p><h2 class="panel-title">Inventory by location</h2><p class="panel-note">Current stock quantity and item coverage across active storage locations.</p></div><span class="rounded-xl bg-violet-50 px-3 py-2 text-xs font-bold text-violet-700">{{ stats.locations }} locations</span></div>
                    <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><article v-for="item in locationDistribution" :key="item.label" class="rounded-2xl border border-slate-200 p-4 transition hover:border-violet-300 hover:shadow-md"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="truncate text-sm font-bold text-slate-800" :title="item.label">{{ item.label }}</p><p class="mt-1 text-xs text-slate-400">{{ item.value }} stock items</p></div><strong class="text-xl text-violet-700">{{ number(item.quantity) }}</strong></div><span class="mt-4 block h-2 overflow-hidden rounded-full bg-violet-50"><span class="block h-full rounded-full bg-gradient-to-r from-violet-500 to-blue-500" :style="{ width: `${percentage(item.quantity, sum(locationDistribution, 'quantity'))}%` }"></span></span></article><p v-if="!locationDistribution.length" class="empty-state sm:col-span-2 lg:col-span-4">No location balances recorded.</p></div>
                </article>

                <article class="overflow-hidden rounded-[1.5rem] border border-amber-200 bg-white shadow-[0_12px_35px_rgba(15,23,42,.05)] lg:col-span-6">
                    <div class="flex items-start justify-between gap-3 border-b border-amber-100 bg-amber-50/50 px-5 py-5 sm:px-6"><div><p class="eyebrow text-amber-600">Action required</p><h2 class="panel-title">Low-stock attention</h2><p class="panel-note">Items currently below their configured minimum level.</p></div><Link :href="route('assets.index')" class="text-xs font-bold text-amber-700 hover:underline">Open register</Link></div>
                    <div v-if="attentionItems.length" class="divide-y divide-slate-100"><Link v-for="item in attentionItems" :key="item.id" :href="route('assets.show', item.id)" class="block px-5 py-4 transition hover:bg-amber-50/30 sm:px-6"><div class="flex items-start justify-between gap-4"><div class="min-w-0"><p class="truncate text-sm font-bold text-slate-800">{{ item.item_code }} · {{ item.description }}</p><p class="mt-1 text-xs text-slate-400">{{ item.category || 'Uncategorised' }} · {{ item.location || 'Unassigned' }}</p></div><span class="shrink-0 rounded-full bg-rose-50 px-2 py-1 text-xs font-bold text-rose-700">{{ number(item.current_stock) }} / {{ number(item.minimum_stock) }}</span></div></Link></div><p v-else class="empty-state m-5">No active stock items are below minimum levels.</p>
                </article>

                <article class="overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white shadow-[0_12px_35px_rgba(15,23,42,.05)] lg:col-span-6">
                    <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-5 sm:px-6"><div><p class="eyebrow text-teal-600">Latest activity</p><h2 class="panel-title">Recent movements</h2><p class="panel-note">The latest stock transactions posted in Miri.</p></div><Link v-if="canReadMovements" :href="route('asset-movements.index')" class="text-xs font-bold text-teal-700 hover:underline">View all</Link></div>
                    <div v-if="recentMovements.length" class="divide-y divide-slate-100"><div v-for="item in recentMovements" :key="item.id" class="px-5 py-4 sm:px-6"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="truncate text-sm font-bold text-slate-800">{{ item.item_code }} · {{ item.description }}</p><p class="mt-1 text-xs text-slate-400">{{ item.source_location || 'Source pending' }} → {{ item.destination_location || 'Destination pending' }}</p></div><span class="shrink-0 rounded-full px-2 py-1 text-[10px] font-bold uppercase" :class="movementTone(item.transaction_type)">{{ movementLabel(item.transaction_type) }}</span></div><p class="mt-2 text-[10px] font-semibold text-slate-400">{{ item.transaction_date }} · Qty {{ number(item.quantity) }}</p></div></div><p v-else class="empty-state m-5">No stock movements recorded.</p>
                </article>

                <article class="panel lg:col-span-12">
                    <div class="flex flex-wrap items-start justify-between gap-4"><div><p class="eyebrow text-indigo-600">COG control</p><h2 class="panel-title">Issued and received references</h2><p class="panel-note">Recent movement records carrying COG references.</p></div><div class="flex gap-2"><span class="rounded-full bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-700">{{ cogSummary.issuedCount }} issued</span><span class="rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700">{{ cogSummary.receivedCount }} received</span></div></div>
                    <div v-if="cogEntries.length" class="mt-5 overflow-x-auto rounded-xl border border-slate-200"><table class="table table-sm"><thead><tr><th>Item</th><th>Date</th><th>Movement</th><th>COG issued</th><th>COG received</th><th>Value</th></tr></thead><tbody><tr v-for="item in cogEntries" :key="item.id"><td><strong class="block text-slate-800">{{ item.item_code }}</strong><small class="text-slate-400">{{ item.description }}</small></td><td>{{ item.transaction_date }}</td><td><span class="rounded-full px-2 py-1 text-[10px] font-bold uppercase" :class="movementTone(item.transaction_type)">{{ movementLabel(item.transaction_type) }}</span></td><td>{{ item.cog_issued_out || '—' }}</td><td>{{ item.cog_received || '—' }}</td><td>{{ money(item.total_value) }}</td></tr></tbody></table></div><p v-else class="empty-state mt-5">No COG-linked movements recorded.</p>
                </article>
            </div>
        </section>
    </AuthenticatedLayout>
</template>

<style scoped>
.dashboard-shell { font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif; font-feature-settings: "kern" 1, "tnum" 1; }
.eyebrow { font-size: .625rem; font-weight: 800; letter-spacing: .18em; text-transform: uppercase; }
.panel { border: 1px solid #d9e8d5; border-radius: 1.5rem; background: white; padding: 1.25rem; box-shadow: 0 12px 35px rgba(39,89,45,.07); }
.panel-title { margin-top: .25rem; font-size: 1.25rem; line-height: 1.75rem; font-weight: 800; color: #1e293b; }
.panel-note { margin-top: .25rem; font-size: .75rem; line-height: 1.25rem; color: #64748b; }
.empty-state { border: 1px dashed #cbd5e1; border-radius: .75rem; padding: 2rem; text-align: center; font-size: .875rem; color: #64748b; }
.arsenal-dashboard :deep(.panel) { border-color: #efb4b7; box-shadow: 0 12px 35px rgba(219,0,7,.09); }
.arsenal-dashboard :deep(.panel-title) { color: #071d49; }
.arsenal-dashboard :deep(.panel-note), .arsenal-dashboard :deep(.text-slate-400), .arsenal-dashboard :deep(.text-slate-500), .arsenal-dashboard :deep(.text-slate-600) { color: #52617a; }
.arsenal-dashboard :deep(.text-slate-700), .arsenal-dashboard :deep(.text-slate-800), .arsenal-dashboard :deep(.text-slate-900) { color: #071d49; }
.arsenal-dashboard :deep(.border-slate-100), .arsenal-dashboard :deep(.border-slate-200) { border-color: #f0c9cb; }
.arsenal-dashboard :deep(.bg-slate-100), .arsenal-dashboard :deep(.bg-slate-50) { background-color: #fff1f2; }
.arsenal-dashboard :deep(.bg-emerald-50), .arsenal-dashboard :deep(.bg-violet-50), .arsenal-dashboard :deep(.bg-blue-50), .arsenal-dashboard :deep(.bg-teal-50) { background-color: #fff1f2; }
.arsenal-dashboard :deep(.text-emerald-600), .arsenal-dashboard :deep(.text-emerald-700), .arsenal-dashboard :deep(.text-violet-600), .arsenal-dashboard :deep(.text-violet-700), .arsenal-dashboard :deep(.text-blue-600), .arsenal-dashboard :deep(.text-blue-700) { color: #db0007; }
@media (min-width: 640px) { .panel { padding: 1.5rem; } }
</style>
