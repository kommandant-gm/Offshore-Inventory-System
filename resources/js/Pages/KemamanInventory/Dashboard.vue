<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { ArrowRightIcon, PlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    summary: Object,
    statusDistribution: Array,
    categories: Array,
    locations: Array,
    condition: Array,
    expiry: Object,
    expiringItems: Array,
    attentionItems: Array,
    recentItems: Array,
    canEdit: Boolean,
});

const palette = ['#10b981', '#3b82f6', '#f59e0b', '#f97316', '#ef4444', '#eab308', '#8b5cf6'];
const sum = (items) => items.reduce((total, item) => total + Number(item.value || 0), 0);
const maximum = (items) => Math.max(...items.map((item) => Number(item.value || 0)), 1);
const percentage = (value, total) => total ? Math.round((Number(value) / total) * 100) : 0;
const serviceability = computed(() => percentage(props.summary.available_quantity, props.summary.total_quantity));
const attentionTotal = computed(() => Number(props.summary.damaged_quantity) + Number(props.summary.beyond_repair_quantity) + Math.abs(Number(props.summary.not_traceable_quantity)));
const statusTotal = computed(() => sum(props.statusDistribution));
const expiryTotal = computed(() => Object.values(props.expiry).reduce((total, value) => total + Number(value), 0));
const statusPie = computed(() => {
    if (!statusTotal.value) return 'conic-gradient(#dfeade 0 360deg)';
    let angle = 0;
    return `conic-gradient(${props.statusDistribution.map((item, index) => {
        const start = angle;
        angle += (Number(item.value) / statusTotal.value) * 360;
        return `${palette[index % palette.length]} ${start}deg ${angle}deg`;
    }).join(',')})`;
});
const statusTone = (status) => ({
    Available: 'bg-emerald-50 text-emerald-700',
    'In Use': 'bg-blue-50 text-blue-700',
    'Under Inspection': 'bg-amber-50 text-amber-700',
    Damaged: 'bg-orange-50 text-orange-700',
    'Beyond Repair': 'bg-rose-50 text-rose-700',
    'Not Traceable': 'bg-yellow-50 text-yellow-800',
}[status] ?? 'bg-slate-100 text-slate-700');
const expiryTone = (days) => days < 0 ? 'text-rose-700 bg-rose-50 border-rose-200' : days <= 30 ? 'text-amber-700 bg-amber-50 border-amber-200' : 'text-blue-700 bg-blue-50 border-blue-200';
const expiryLabel = (days) => days < 0 ? `${Math.abs(Math.round(days))}d overdue` : `${Math.round(days)}d remaining`;
const cards = computed(() => [
    { label: 'Equipment Records', value: props.summary.records, note: 'Register lines', tone: 'from-emerald-500 to-teal-600' },
    { label: 'Quantity in System', value: props.summary.total_quantity, note: 'Across every category', tone: 'from-blue-500 to-indigo-600' },
    { label: 'Available', value: props.summary.available_quantity, note: `${serviceability.value}% serviceability`, tone: 'from-teal-500 to-emerald-600' },
    { label: 'Issued / Out', value: props.summary.quantity_out, note: 'Recorded outside store', tone: 'from-violet-500 to-purple-600' },
    { label: 'Needs Attention', value: attentionTotal.value, note: 'Damage, repair, or variance', tone: 'from-orange-500 to-rose-600' },
]);
</script>

<template>
    <Head title="Kemaman Equipment Dashboard" />
    <AuthenticatedLayout>
        <section class="dashboard-shell space-y-6">
            <header class="relative isolate overflow-hidden rounded-[1.8rem] bg-[linear-gradient(120deg,#064e3b_0%,#0f766e_55%,#155e75_100%)] px-6 py-7 text-white shadow-[0_24px_70px_rgba(6,78,59,.22)] sm:px-9 sm:py-9">
                <div class="pointer-events-none absolute -right-20 -top-28 h-80 w-80 rounded-full bg-cyan-300/20 blur-3xl"></div>
                <div class="pointer-events-none absolute -bottom-32 left-1/3 h-72 w-72 rounded-full bg-emerald-300/15 blur-3xl"></div>
                <div class="relative grid items-end gap-8 lg:grid-cols-[minmax(0,1fr),22rem]">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.24em] text-emerald-200">Kemaman Inventory</p>
                        <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">Equipment Dashboard</h1>
                        <p class="mt-3 max-w-2xl text-sm leading-6 text-white/70 sm:text-base">Live visibility of equipment availability, condition, locations, traceability, and certification exposure.</p>
                        <div class="mt-6 flex flex-wrap gap-3">
                            <Link :href="route('kemaman-inventory.index')" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-emerald-900 shadow-sm transition hover:-translate-y-0.5 hover:bg-emerald-50">Open equipment register <ArrowRightIcon class="h-4 w-4" /></Link>
                            <Link v-if="canEdit" :href="route('kemaman-inventory.index')" class="inline-flex items-center gap-2 rounded-xl border border-white/20 bg-white/10 px-4 py-2.5 text-sm font-bold text-white backdrop-blur transition hover:bg-white/15"><PlusIcon class="h-4 w-4" /> Manage equipment</Link>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3 rounded-2xl border border-white/10 bg-white/[.07] p-4 backdrop-blur-sm">
                        <div class="rounded-xl bg-black/10 p-4"><p class="eyebrow text-white/50">Serviceability</p><p class="mt-2 text-3xl font-black text-emerald-300">{{ serviceability }}%</p><p class="mt-1 text-xs text-white/55">{{ summary.available_quantity }} ready</p></div>
                        <div class="rounded-xl bg-black/10 p-4"><p class="eyebrow text-white/50">Certificate Risk</p><p class="mt-2 text-3xl font-black text-amber-300">{{ expiry.expired + expiry.due_30_days }}</p><p class="mt-1 text-xs text-white/55">expired / due soon</p></div>
                    </div>
                </div>
            </header>

            <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                <article v-for="card in cards" :key="card.label" class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-4 shadow-[0_8px_28px_rgba(15,23,42,.05)] transition hover:-translate-y-1 hover:shadow-lg sm:p-5">
                    <span class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r" :class="card.tone"></span>
                    <p class="eyebrow text-slate-500">{{ card.label }}</p><p class="mt-2 text-2xl font-black text-slate-800 sm:text-3xl">{{ card.value }}</p><p class="mt-1 text-[11px] font-semibold text-slate-400">{{ card.note }}</p>
                </article>
            </div>

            <div v-if="summary.records === 0" class="rounded-[1.6rem] border border-dashed border-emerald-300 bg-emerald-50/50 p-12 text-center">
                <h2 class="text-xl font-black text-emerald-950">The dashboard is ready for equipment data</h2><p class="mt-2 text-sm text-emerald-800/70">Add the first Kemaman record to populate all charts and operational alerts.</p><Link :href="route('kemaman-inventory.index')" class="btn mt-5 border-none bg-emerald-700 text-white">Open register</Link>
            </div>

            <div v-else class="grid gap-6 lg:grid-cols-12">
                <article class="panel lg:col-span-5">
                    <div><p class="eyebrow text-emerald-600">Fleet state</p><h2 class="panel-title">Equipment status</h2><p class="panel-note">Quantity distribution by current operational status.</p></div>
                    <div class="mt-6 grid items-center gap-6 sm:grid-cols-[12rem,1fr]">
                        <div class="relative mx-auto h-44 w-44 rounded-full" :style="{ background: statusPie }"><div class="absolute inset-7 flex flex-col items-center justify-center rounded-full bg-white shadow-inner"><strong class="text-3xl text-slate-800">{{ statusTotal }}</strong><span class="eyebrow mt-1 text-slate-400">Total qty</span></div></div>
                        <div class="space-y-3"><Link v-for="(item, index) in statusDistribution" :key="item.key" :href="route('kemaman-inventory.index', { status: item.key })" class="grid grid-cols-[.75rem,1fr,auto] items-center gap-3 rounded-lg p-1 transition hover:bg-slate-50"><span class="h-3 w-3 rounded-full" :style="{ backgroundColor: palette[index % palette.length] }"></span><span><strong class="block text-sm text-slate-700">{{ item.label }}</strong><small class="text-slate-400">{{ item.records }} records</small></span><strong class="text-sm text-slate-800">{{ item.value }}</strong></Link></div>
                    </div>
                </article>

                <article class="panel lg:col-span-7">
                    <div class="flex items-start justify-between gap-3"><div><p class="eyebrow text-blue-600">Inventory mix</p><h2 class="panel-title">Top equipment categories</h2><p class="panel-note">Quantity held across the largest categories.</p></div><span class="rounded-xl bg-blue-50 px-3 py-2 text-xs font-bold text-blue-700">Top {{ categories.length }}</span></div>
                    <div class="mt-6 space-y-3"><Link v-for="(item, index) in categories" :key="item.label" :href="route('kemaman-inventory.index', { category: item.label })" class="grid grid-cols-[minmax(7rem,10rem),minmax(0,1fr),3rem] items-center gap-3 rounded-xl p-1.5 transition hover:bg-blue-50/50"><span class="truncate text-xs font-bold text-slate-600" :title="item.label">{{ item.label }}</span><span class="h-8 overflow-hidden rounded-lg bg-slate-100 p-1"><span class="block h-full min-w-[.4rem] rounded-md bg-gradient-to-r transition-all duration-500" :class="index % 2 ? 'from-cyan-500 to-blue-600' : 'from-emerald-500 to-teal-600'" :style="{ width: `${(item.value / maximum(categories)) * 100}%` }"></span></span><strong class="text-right text-sm text-slate-800">{{ item.value }}</strong></Link><p v-if="!categories.length" class="empty-state">No categories recorded.</p></div>
                </article>

                <article class="panel lg:col-span-7">
                    <div><p class="eyebrow text-violet-600">Deployment footprint</p><h2 class="panel-title">Equipment by location</h2><p class="panel-note">Where registered equipment is currently held.</p></div>
                    <div class="mt-6 grid gap-3 sm:grid-cols-2"><article v-for="(item, index) in locations" :key="item.label" class="rounded-2xl border border-slate-200 p-4"><div class="flex items-start justify-between gap-3"><div><p class="text-sm font-bold text-slate-800">{{ item.label }}</p><p class="mt-1 text-xs text-slate-400">{{ item.records }} records</p></div><strong class="text-2xl text-violet-700">{{ item.value }}</strong></div><span class="mt-4 block h-2 overflow-hidden rounded-full bg-violet-50"><span class="block h-full rounded-full bg-gradient-to-r from-violet-500 to-blue-500" :style="{ width: `${percentage(item.value, sum(locations))}%` }"></span></span></article><p v-if="!locations.length" class="empty-state sm:col-span-2">No locations recorded.</p></div>
                </article>

                <article class="panel lg:col-span-5">
                    <div><p class="eyebrow text-orange-600">Physical condition</p><h2 class="panel-title">Quantity reconciliation</h2><p class="panel-note">Condition counts captured in the equipment register.</p></div>
                    <div class="mt-6 space-y-4"><div v-for="(item, index) in condition" :key="item.label" class="grid grid-cols-[8rem,1fr,2.5rem] items-center gap-3"><span class="truncate text-xs font-bold text-slate-600">{{ item.label }}</span><span class="h-7 overflow-hidden rounded-lg bg-slate-100"><span class="flex h-full min-w-[.35rem] items-center justify-end rounded-lg pr-2 text-[10px] font-bold text-white" :style="{ width: `${(item.value / maximum(condition)) * 100}%`, backgroundColor: palette[index % palette.length] }"></span></span><strong class="text-right text-sm text-slate-800">{{ item.value }}</strong></div></div>
                </article>

                <article class="panel lg:col-span-12">
                    <div class="flex flex-wrap items-start justify-between gap-4"><div><p class="eyebrow text-amber-600">Compliance watch</p><h2 class="panel-title">Test and certification expiry</h2><p class="panel-note">Upcoming inspection exposure based on recorded test-expiry dates.</p></div><div class="text-right"><strong class="text-2xl text-slate-800">{{ expiryTotal }}</strong><p class="eyebrow text-slate-400">Tracked records</p></div></div>
                    <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-5"><div v-for="item in [{label:'Expired',value:expiry.expired,tone:'rose'},{label:'Due ≤ 30 days',value:expiry.due_30_days,tone:'amber'},{label:'Due 31–90 days',value:expiry.due_90_days,tone:'blue'},{label:'Valid > 90 days',value:expiry.valid,tone:'emerald'},{label:'Not recorded',value:expiry.not_recorded,tone:'slate'}]" :key="item.label" class="rounded-xl border border-slate-200 bg-slate-50/60 p-3"><p class="eyebrow text-slate-500">{{ item.label }}</p><p class="mt-2 text-2xl font-black text-slate-800">{{ item.value }}</p></div></div>
                    <div v-if="expiringItems.length" class="mt-5 overflow-x-auto rounded-xl border border-slate-200"><table class="table table-sm"><thead><tr><th>Equipment</th><th>Tag no.</th><th>Certificate</th><th>Test expiry</th><th>Timing</th></tr></thead><tbody><tr v-for="item in expiringItems" :key="item.id"><td class="font-semibold text-slate-800">{{ item.description }}</td><td class="font-mono text-xs">{{ item.tag_no || '—' }}</td><td>{{ item.certificate_no || '—' }}</td><td>{{ item.test_expiry_date }}</td><td><span class="rounded-full border px-2 py-1 text-xs font-bold" :class="expiryTone(item.days_remaining)">{{ expiryLabel(item.days_remaining) }}</span></td></tr></tbody></table></div>
                </article>

                <article class="overflow-hidden rounded-[1.5rem] border border-rose-200 bg-white shadow-[0_12px_35px_rgba(15,23,42,.05)] lg:col-span-7">
                    <div class="border-b border-rose-100 bg-rose-50/50 px-5 py-5 sm:px-6"><p class="eyebrow text-rose-600">Action required</p><h2 class="panel-title">Equipment needing attention</h2><p class="panel-note">Damage, beyond-repair, traceability, and inspection exceptions.</p></div>
                    <div v-if="attentionItems.length" class="divide-y divide-slate-100"><div v-for="item in attentionItems" :key="item.id" class="flex items-start justify-between gap-4 px-5 py-4 sm:px-6"><div class="min-w-0"><p class="truncate text-sm font-bold text-slate-800">{{ item.description }}</p><p class="mt-1 text-xs text-slate-400">{{ item.category }} · {{ item.tag_no || 'No tag' }}</p><div class="mt-2 flex flex-wrap gap-2 text-[10px] font-bold"><span v-if="item.damaged" class="rounded-full bg-orange-50 px-2 py-1 text-orange-700">{{ item.damaged }} damaged</span><span v-if="item.beyond_repair" class="rounded-full bg-rose-50 px-2 py-1 text-rose-700">{{ item.beyond_repair }} beyond repair</span><span v-if="item.not_traceable" class="rounded-full bg-yellow-50 px-2 py-1 text-yellow-800">Variance {{ item.not_traceable }}</span></div></div><span class="shrink-0 rounded-full px-2 py-1 text-[10px] font-bold uppercase" :class="statusTone(item.status)">{{ item.status }}</span></div></div><p v-else class="empty-state m-5">No equipment exceptions require attention.</p>
                </article>

                <article class="overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white shadow-[0_12px_35px_rgba(15,23,42,.05)] lg:col-span-5">
                    <div class="border-b border-slate-100 px-5 py-5 sm:px-6"><p class="eyebrow text-teal-600">Latest activity</p><h2 class="panel-title">Recently updated equipment</h2></div>
                    <div class="divide-y divide-slate-100"><div v-for="item in recentItems" :key="item.id" class="px-5 py-4 sm:px-6"><div class="flex items-start justify-between gap-3"><p class="min-w-0 truncate text-sm font-bold text-slate-800">{{ item.description }}</p><span class="shrink-0 rounded-full px-2 py-1 text-[10px] font-bold uppercase" :class="statusTone(item.status)">{{ item.status }}</span></div><p class="mt-1 text-xs text-slate-400">{{ item.tag_no || item.category }} · {{ item.location || 'Unassigned' }}</p><p class="mt-1 text-[10px] font-semibold text-slate-400">Updated {{ item.updated_at }}</p></div></div>
                </article>
            </div>
        </section>
    </AuthenticatedLayout>
</template>

<style scoped>
.dashboard-shell { font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif; font-feature-settings: "kern" 1, "tnum" 1; }
.eyebrow { font-size: .625rem; font-weight: 800; letter-spacing: .18em; text-transform: uppercase; }
.panel { border: 1px solid #e2e8f0; border-radius: 1.5rem; background: white; padding: 1.25rem; box-shadow: 0 12px 35px rgba(15,23,42,.05); }
.panel-title { margin-top: .25rem; font-size: 1.25rem; line-height: 1.75rem; font-weight: 800; color: #1e293b; }
.panel-note { margin-top: .25rem; font-size: .75rem; line-height: 1.25rem; color: #64748b; }
.empty-state { border: 1px dashed #cbd5e1; border-radius: .75rem; padding: 2rem; text-align: center; font-size: .875rem; color: #64748b; }
@media (min-width: 640px) { .panel { padding: 1.5rem; } }
</style>
