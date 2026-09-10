<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MiriRentalDashboard from '@/Components/MiriRentalDashboard.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({ summary: Object, categories: Array, locations: Array, recent: Array, expiry: Object, expiring: Array, rentalDashboard: Object, activeDashboard: String, inventoryType: String, typeCounts: Object, statusBreakdown: Array, quality: Object, quantityRecorded: Number });
const activeDashboard = ref(props.activeDashboard ?? 'major');
watch(() => props.activeDashboard, value => activeDashboard.value = value ?? 'major');
const selectionLabel = computed(() => props.inventoryType === 'cargo' ? 'Cargo' : props.inventoryType === 'machinery' ? 'Machinery' : 'Major Equipment');
const colors = ['#2563eb', '#10b981', '#f59e0b', '#8b5cf6', '#06b6d4', '#ec4899', '#64748b', '#84cc16'];
const total = computed(() => Number(props.summary?.total ?? 0));
const statuses = computed(() => (props.statusBreakdown ?? []).map((item, i) => ({
    label: item.label, value: Number(item.value),
    color: ({'In Use':'#2563eb','Standby':'#10b981','Under Repair':'#f59e0b','PENDING REPAIR':'#f59e0b','Damaged':'#ef4444','Not recorded':'#94a3b8'})[item.label] || colors[i % colors.length],
})));
const noCertificate = computed(() => statuses.value.filter(item => item.label.toUpperCase() === 'NO CERT').reduce((sum, item) => sum + item.value, 0));
const noStatus = computed(() => statuses.value.find(item => item.label === 'Not recorded')?.value || 0);
const percent = (value, base = total.value) => base ? Math.round((Number(value) / base) * 100) : 0;
const maximum = (items) => Math.max(...items.map((item) => Number(item.total)), 1);
const expiryTotal = computed(() => Object.values(props.expiry ?? {}).reduce((sum, value) => sum + Number(value || 0), 0));
const expiryStatuses = computed(() => [
    { label: 'Expired', value: Number(props.expiry?.expired ?? 0), color: '#ef4444', tone: 'text-red-600' },
    { label: 'Due within 30 days', value: Number(props.expiry?.due_30_days ?? 0), color: '#f59e0b', tone: 'text-amber-600' },
    { label: 'Due within 90 days', value: Number(props.expiry?.due_90_days ?? 0), color: '#f97316', tone: 'text-orange-600' },
    { label: 'Valid beyond 90 days', value: Number(props.expiry?.valid ?? 0), color: '#10b981', tone: 'text-emerald-600' },
    { label: 'Not recorded', value: Number(props.expiry?.not_recorded ?? 0), color: '#94a3b8', tone: 'text-slate-500' },
]);
const expiryDate = (value) => value ? new Date(`${value}T00:00:00`).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '-';
const expiryLabel = (days) => days < 0 ? `${Math.abs(days)} days overdue` : days === 0 ? 'Expires today' : `${days} days left`;
const pie = computed(() => {
    let angle = 0;
    if (!total.value) return 'conic-gradient(#e5efe2 0 360deg)';
    return `conic-gradient(${statuses.value.map((item) => { const start = angle; angle += (item.value / total.value) * 360; return `${item.color} ${start}deg ${angle}deg`; }).join(',')})`;
});
</script>

<template>
    <Head title="Miri Inventory Dashboard" />
    <AuthenticatedLayout>
        <section class="dashboard-shell space-y-6">
            <nav class="inline-flex rounded-2xl border border-[#d8e7d4] bg-white p-1.5 shadow-[0_8px_28px_rgba(39,89,45,.06)]" aria-label="Miri dashboard view">
                <button type="button" class="rounded-xl px-5 py-2.5 text-sm font-bold transition" :class="activeDashboard === 'major' ? 'bg-[#234222] text-white shadow-sm' : 'text-[#60745d] hover:bg-[#f1f7ef] hover:text-[#234222]'" :aria-pressed="activeDashboard === 'major'" @click="activeDashboard = 'major'">Major Equipment</button>
                <button type="button" class="rounded-xl px-5 py-2.5 text-sm font-bold transition" :class="activeDashboard === 'rentals' ? 'bg-[#234222] text-white shadow-sm' : 'text-[#60745d] hover:bg-[#f1f7ef] hover:text-[#234222]'" :aria-pressed="activeDashboard === 'rentals'" @click="activeDashboard = 'rentals'">Rentals</button>
            </nav>
            <MiriRentalDashboard v-if="activeDashboard === 'rentals'" :dashboard="rentalDashboard" />
            <template v-else>
            <nav class="flex flex-wrap gap-2" aria-label="Major Equipment dashboard filter">
                <Link v-for="[key,label] in [['all','All Major Equipment'],['machinery','Machinery'],['cargo','Cargo']]" :key="key"
                    :href="route('major-equipment.dashboard', { inventory_type: key, view: 'major' })"
                    :aria-current="inventoryType === key ? 'page' : undefined"
                    class="rounded-xl border px-5 py-3 text-sm font-bold"
                    :class="inventoryType === key ? 'border-[#234222] bg-[#234222] text-white' : 'border-[#d8e7d4] bg-white text-[#60745d]'">
                    {{ label }} <span class="ml-2">{{ key === 'all' ? Number(typeCounts.machinery || 0) + Number(typeCounts.cargo || 0) : typeCounts[key] || 0 }}</span>
                </Link>
            </nav>
            <header class="relative isolate overflow-hidden rounded-[1.75rem] bg-[linear-gradient(120deg,#064e3b_0%,#0f766e_58%,#115e59_100%)] px-5 py-6 text-white shadow-[0_24px_70px_rgba(6,78,59,.22)] sm:px-8 sm:py-8 lg:px-10">
                <div class="pointer-events-none absolute -right-16 -top-24 h-72 w-72 rounded-full bg-cyan-300/20 blur-2xl" />
                <div class="pointer-events-none absolute -bottom-28 left-1/3 h-64 w-64 rounded-full bg-emerald-300/15 blur-3xl" />
                <div class="relative grid items-end gap-8 lg:grid-cols-[minmax(0,1fr),22rem]">
                    <div><p class="text-xs font-semibold uppercase tracking-[.24em] text-emerald-200">Miri Inventory</p><h1 class="mt-3 text-3xl font-bold tracking-[-.02em] sm:text-4xl lg:text-[2.75rem]">{{ selectionLabel }} Dashboard</h1><p class="mt-3 max-w-2xl text-sm leading-6 text-white/65 sm:text-base">Monitor equipment, categories, locations, and operational status from one responsive workspace.</p><div class="mt-6 flex flex-wrap gap-3"><Link :href="route('major-equipment.index', { inventory_type: inventoryType === 'all' ? 'machinery' : inventoryType })" class="inline-flex items-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-emerald-900 shadow-sm transition hover:-translate-y-0.5 hover:bg-emerald-50">View {{ inventoryType === 'all' ? 'Machinery' : selectionLabel }} register</Link><Link v-if="inventoryType === 'all'" :href="route('major-equipment.index', { inventory_type: 'cargo' })" class="inline-flex items-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-emerald-900">View Cargo register</Link><Link :href="route('major-equipment.import', { inventory_type: inventoryType === 'cargo' ? 'cargo' : 'machinery' })" class="inline-flex items-center rounded-xl border border-white/20 bg-white/10 px-4 py-2.5 text-sm font-bold text-white backdrop-blur transition hover:-translate-y-0.5 hover:bg-white/15">+ Import {{ inventoryType === 'cargo' ? 'Cargo' : 'Machinery' }} CSV</Link></div></div>
                    <div class="grid grid-cols-2 gap-3 rounded-2xl border border-white/10 bg-white/[.07] p-4 backdrop-blur-sm"><div class="rounded-xl bg-black/10 p-4"><p class="text-[10px] font-semibold uppercase tracking-[.16em] text-white/50">{{ inventoryType === 'cargo' ? 'No Cert (status)' : 'In use' }}</p><p class="mt-2 text-2xl font-bold text-cyan-200 sm:text-3xl">{{ percent(inventoryType === 'cargo' ? noCertificate : summary.in_use) }}%</p><p class="mt-1 text-xs text-white/55">{{ inventoryType === 'cargo' ? noCertificate : summary.in_use }} records</p></div><div class="rounded-xl bg-black/10 p-4"><p class="text-[10px] font-semibold uppercase tracking-[.16em] text-white/50">{{ inventoryType === 'cargo' ? 'Status not recorded' : 'Standby' }}</p><p class="mt-2 text-2xl font-bold text-emerald-300 sm:text-3xl">{{ percent(inventoryType === 'cargo' ? noStatus : summary.standby) }}%</p><p class="mt-1 text-xs text-white/55">{{ inventoryType === 'cargo' ? noStatus : summary.standby }} records</p></div></div>
                </div>
            </header>

            <div class="rounded-2xl border border-[#d8e7d4] bg-white p-4 text-sm text-[#60745d]">
                <p>All totals count records, not units. Status charts include the original recorded statuses and records with no status.</p>
                <p v-if="inventoryType === 'cargo'" class="mt-1">Quantity is recorded for {{ quantityRecorded }} of {{ total }} Cargo records.</p>
                <div class="mt-3 flex flex-wrap gap-4"><span>Duplicate-tag records: <strong>{{ quality.duplicates }}</strong></span><span>Missing details: <strong>{{ quality.missing }}</strong></span><span>Import warnings: <strong>{{ quality.warnings }}</strong></span></div>
            </div>
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-5 lg:gap-4">
                <div v-for="card in [{label:'Total Records',value:summary.total,accent:'bg-[#234222]',icon:'T'},{label:inventoryType === 'cargo' ? 'No Cert (status)' : 'In Use',value:inventoryType === 'cargo' ? noCertificate : summary.in_use,accent:'bg-blue-500',icon:'I'},{label:inventoryType === 'cargo' ? 'Status Not Recorded' : 'Standby',value:inventoryType === 'cargo' ? noStatus : summary.standby,accent:'bg-emerald-500',icon:'S'},{label:'Under Repair',value:summary.under_repair,accent:'bg-amber-500',icon:'U'},{label:'Due in 30 Days',value:expiry?.due_30_days ?? 0,accent:'bg-rose-500',icon:'!'}]" :key="card.label" class="relative overflow-hidden rounded-2xl border border-[#d8e7d4] bg-white p-4 shadow-[0_8px_28px_rgba(39,89,45,.06)] sm:p-5"><div class="absolute inset-x-0 top-0 h-1" :class="card.accent"/><div class="flex items-start justify-between gap-3"><div><p class="text-[10px] font-extrabold uppercase tracking-[.16em] text-[#70836e] sm:text-xs">{{ card.label }}</p><p class="mt-2 text-2xl font-black text-[#173a21] sm:text-3xl">{{ card.value }}</p><p class="mt-1 text-[11px] font-semibold text-[#8a9a88]">{{ card.label === 'Due in 30 Days' ? 'certificates' : `${percent(card.value)}% of records` }}</p></div><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#e7f3e3] text-sm font-black text-[#234222]">{{ card.icon }}</span></div></div>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:gap-5">
                <article class="rounded-[1.5rem] border border-[#d9e8d5] bg-white p-5 shadow-[0_12px_35px_rgba(39,89,45,.07)] sm:p-6 lg:col-span-5"><div class="flex items-start justify-between gap-4"><div><p class="text-[10px] font-extrabold uppercase tracking-[.22em] text-blue-500">Equipment allocation</p><h2 class="mt-1 text-xl font-black tracking-tight text-slate-800">Status overview</h2></div><span class="text-right text-xs text-slate-400"><strong class="block text-lg text-slate-700">{{ total }}</strong>records</span></div><div class="mt-7 grid items-center gap-7 sm:grid-cols-[12rem,minmax(0,1fr)]"><div class="relative mx-auto h-44 w-44 rounded-full p-3 shadow-inner sm:h-48 sm:w-48" :style="{ background: pie }"><div class="flex h-full w-full flex-col items-center justify-center rounded-full bg-white shadow-[inset_0_0_0_1px_#edf4ea]"><span class="text-3xl font-black text-[#173a21]">{{ total }}</span><span class="mt-1 text-xs font-semibold text-[#7b8f78]">Total records</span></div></div><div class="max-h-80 space-y-2 overflow-y-auto"><div v-for="item in statuses" :key="item.label" class="flex items-center gap-3 rounded-xl px-3 py-2.5"><span class="h-2.5 w-2.5 shrink-0 rounded-full ring-4 ring-[#edf5ea]" :style="{ backgroundColor: item.color }"/><span class="min-w-0 flex-1 truncate text-sm font-semibold text-[#5c715a]">{{ item.label }}</span><span class="text-right"><strong class="block text-sm font-black text-[#173a21]">{{ item.value }}</strong><small class="text-[10px] font-bold text-[#93a391]">{{ percent(item.value) }}%</small></span></div></div></div></article>
                <article class="rounded-[1.5rem] border border-[#d9e8d5] bg-white p-5 shadow-[0_12px_35px_rgba(39,89,45,.07)] sm:p-6 lg:col-span-7"><div class="flex items-start justify-between gap-4"><div><p class="text-[10px] font-extrabold uppercase tracking-[.22em] text-indigo-500">Inventory mix</p><h2 class="mt-1 text-xl font-black tracking-tight text-slate-800">{{ inventoryType === 'all' ? 'Machinery and Cargo' : 'Records by subcategory' }}</h2></div><p class="text-right text-xs text-slate-400"><strong class="block text-lg text-slate-700">{{ categories.length }}</strong>{{ inventoryType === 'all' ? 'types' : 'subcategories' }}</p></div><div class="mt-7 space-y-5"><div v-for="item in categories" :key="item.category" class="rounded-xl p-2"><div class="mb-2 flex items-end justify-between gap-4"><span class="truncate text-sm font-bold text-[#50694e]">{{ item.category || 'Uncategorised' }}</span><span class="shrink-0 text-sm"><strong class="text-[#173a21]">{{ item.total }}</strong><small class="ml-1 text-[#91a08f]">({{ percent(item.total) }}%)</small></span></div><div class="h-3 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-[linear-gradient(90deg,#2563eb,#8b5cf6)]" :style="{ width: `${(item.total / maximum(categories)) * 100}%` }"/></div></div><p v-if="categories.length === 0" class="text-sm text-slate-500">No records imported yet.</p></div></article>
                <article class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-[0_12px_35px_rgba(15,23,42,.06)] sm:p-6 lg:col-span-7"><div><p class="text-[10px] font-extrabold uppercase tracking-[.22em] text-violet-500">Deployment footprint</p><h2 class="mt-1 text-xl font-black tracking-tight text-slate-800">Records by current location</h2></div><div class="mt-6 space-y-3"><div v-for="item in locations" :key="item.label" class="rounded-2xl border border-slate-200 bg-white p-4"><div class="flex items-center justify-between gap-3"><strong class="truncate text-sm text-slate-800">{{ item.label }}</strong><strong class="text-lg text-slate-800">{{ item.total }}</strong></div><div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100"><span class="block h-full rounded-full bg-[linear-gradient(90deg,#8b5cf6,#3b82f6)]" :style="{ width: `${percent(item.total)}%` }"/></div></div><p v-if="locations.length === 0" class="text-sm text-slate-500">No locations recorded yet.</p></div></article>
                <article class="rounded-[1.5rem] border border-[#f2dfd7] bg-white p-5 shadow-[0_12px_35px_rgba(39,89,45,.07)] sm:p-6 lg:col-span-7"><div class="flex items-start justify-between gap-4"><div><p class="text-[10px] font-extrabold uppercase tracking-[.22em] text-rose-500">Certificate health</p><h2 class="mt-1 text-xl font-black tracking-tight text-slate-800">Expiry status</h2><p class="mt-1 text-xs text-slate-500">How long until each certificate expires.</p></div><span class="text-right text-xs text-slate-400"><strong class="block text-lg text-slate-700">{{ expiryTotal }}</strong>certificates</span></div><div class="mt-6 h-3 overflow-hidden rounded-full bg-slate-100"><span v-for="item in expiryStatuses" :key="item.label" class="inline-block h-full" :style="{ width: `${expiryTotal ? (item.value / expiryTotal) * 100 : 0}%`, backgroundColor: item.color }"/></div><div class="mt-5 grid gap-2 sm:grid-cols-2"><div v-for="item in expiryStatuses" :key="item.label" class="flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2"><span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: item.color }"/><span class="min-w-0 flex-1 truncate text-xs font-semibold text-slate-600">{{ item.label }}</span><strong class="text-sm" :class="item.tone">{{ item.value }}</strong></div></div><div class="mt-6 border-t border-slate-100 pt-4"><p class="text-[10px] font-extrabold uppercase tracking-[.18em] text-slate-400">Nearest deadlines</p><div class="mt-3 space-y-2"><Link v-for="item in expiring" :key="item.id" :href="route('major-equipment.show', item.equipment?.id)" class="flex items-center gap-3 rounded-xl px-3 py-2 hover:bg-rose-50"><span class="min-w-0 flex-1"><strong class="block truncate text-xs text-slate-700">{{ item.equipment?.tag_no || 'Untagged' }} · {{ item.certificate_type }}</strong><small class="text-[11px] text-slate-400">{{ expiryDate(item.expiry_date) }}</small></span><strong class="shrink-0 text-xs" :class="item.days_remaining < 0 ? 'text-red-600' : item.days_remaining <= 30 ? 'text-amber-600' : 'text-emerald-600'">{{ expiryLabel(item.days_remaining) }}</strong></Link><p v-if="expiring.length === 0" class="text-sm text-slate-500">No certificate expiry dates recorded yet.</p></div></div></article>
                <article class="rounded-[1.5rem] border border-[#d9e8d5] bg-white p-5 shadow-[0_12px_35px_rgba(39,89,45,.07)] sm:p-6 lg:col-span-5"><div><p class="text-[10px] font-extrabold uppercase tracking-[.22em] text-emerald-600">Latest activity</p><h2 class="mt-1 text-xl font-black tracking-tight text-slate-800">Recently updated</h2></div><div class="mt-5 space-y-3"><Link v-for="item in recent" :key="item.id" :href="route('major-equipment.show', item.id)" class="block rounded-xl bg-[#f7fbf5] px-4 py-3 hover:bg-[#eef8ea]"><p class="font-semibold text-[#234222]">{{ item.tag_no || 'Untagged' }} · {{ item.description || '-' }}</p><p class="mt-1 truncate text-xs text-slate-500">{{ item.section_1 }} / {{ item.section_2 }} · {{ item.status || '-' }}</p></Link><p v-if="recent.length === 0" class="text-sm text-slate-500">No records imported yet.</p></div></article>
            </div>
            </template>
        </section>
    </AuthenticatedLayout>
</template>

<style scoped>
.dashboard-shell { font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif; font-feature-settings: "kern" 1, "tnum" 1; }
.dashboard-shell .font-black, .dashboard-shell .font-extrabold { font-weight: 700; }
</style>
