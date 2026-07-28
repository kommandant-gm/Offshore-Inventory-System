<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
  dashboard: { type: Object, required: true },
});

const statusColours = ['#10b981', '#f59e0b', '#ef4444', '#64748b'];
const hoveredStatus = ref(null);
const statusTotal = computed(() => (props.dashboard.status ?? []).reduce((sum, item) => sum + Number(item.value), 0));
const activeStatus = computed(() => hoveredStatus.value === null ? null : props.dashboard.status?.[hoveredStatus.value]);
const statusPercent = (value) => statusTotal.value ? Math.round((Number(value) / statusTotal.value) * 100) : 0;
const statusPie = computed(() => {
  let angle = 0;
  if (!statusTotal.value) return 'conic-gradient(#e5e7eb 0 360deg)';
  return `conic-gradient(${props.dashboard.status.map((item, index) => {
    const start = angle;
    angle += (Number(item.value) / statusTotal.value) * 360;
    return `${statusColours[index % statusColours.length]} ${start}deg ${angle}deg`;
  }).join(',')})`;
});

const timelineMaximum = computed(() => Math.max(...(props.dashboard.expiry_timeline ?? []).map((item) => Number(item.value)), 1));
const costMaximum = computed(() => Math.max(...(props.dashboard.renewal_cost_by_vendor ?? []).map((item) => Number(item.value)), 1));
const money = (value, decimals = 0) => new Intl.NumberFormat('en-MY', {
  style: 'currency', currency: 'MYR', minimumFractionDigits: decimals, maximumFractionDigits: decimals,
}).format(Number(value ?? 0));
const prettyDate = (value) => value ? new Intl.DateTimeFormat('en-MY', {
  day: 'numeric', month: 'short', year: 'numeric',
}).format(new Date(`${value}T00:00:00`)) : 'No expiry';
const renewalLabel = (item) => {
  if (item.days_until_expiry === 0) return 'Expires today';
  if (item.days_until_expiry === 1) return 'Expires tomorrow';
  return `${item.days_until_expiry} days left`;
};
const urgencyClass = (item) => item.days_until_expiry <= 30
  ? 'bg-rose-50 text-rose-700 ring-rose-200'
  : (item.days_until_expiry <= 90 ? 'bg-amber-50 text-amber-700 ring-amber-200' : 'bg-emerald-50 text-emerald-700 ring-emerald-200');

const cards = computed(() => [
  { label: 'Total licences', value: props.dashboard.summary.total_licenses, note: `${props.dashboard.summary.total_seats} purchased seats`, accent: 'bg-[#234222]', icon: 'L', iconClass: 'bg-[#e7f3e3] text-[#234222]' },
  { label: 'Assigned seats', value: props.dashboard.summary.assigned_seats, note: 'Currently in use', accent: 'bg-blue-500', icon: 'A', iconClass: 'bg-blue-50 text-blue-700' },
  { label: 'Available seats', value: props.dashboard.summary.available_seats, note: 'Ready to allocate', accent: 'bg-emerald-500', icon: 'S', iconClass: 'bg-emerald-50 text-emerald-700' },
  { label: 'Expiring soon', value: props.dashboard.summary.expiring_soon, note: 'Within the next 30 days', accent: 'bg-amber-500', icon: 'E', iconClass: 'bg-amber-50 text-amber-700' },
  { label: 'Expired', value: props.dashboard.summary.expired, note: 'Needs review', accent: 'bg-rose-500', icon: '!', iconClass: 'bg-rose-50 text-rose-700' },
]);
</script>

<template>
  <div class="space-y-5">
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-5 lg:gap-4">
      <article v-for="card in cards" :key="card.label" class="relative overflow-hidden rounded-2xl border border-[#d8e7d4] bg-white p-4 shadow-[0_8px_28px_rgba(39,89,45,.06)] sm:p-5">
        <div class="absolute inset-x-0 top-0 h-1" :class="card.accent"></div>
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0"><p class="truncate text-[10px] font-bold uppercase tracking-[.16em] text-[#70836e] sm:text-xs">{{ card.label }}</p><p class="mt-2 text-2xl font-bold tracking-tight text-[#173a21] sm:text-3xl">{{ card.value }}</p><p class="mt-1 truncate text-[11px] font-semibold text-[#8a9a88]">{{ card.note }}</p></div>
          <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-sm font-bold sm:h-10 sm:w-10" :class="card.iconClass">{{ card.icon }}</span>
        </div>
      </article>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:gap-5">
      <article class="rounded-[1.5rem] border border-[#d9e8d5] bg-white p-5 shadow-[0_12px_35px_rgba(39,89,45,.07)] sm:p-6 lg:col-span-5">
        <div class="flex items-start justify-between gap-4"><div><p class="text-[10px] font-bold uppercase tracking-[.22em] text-emerald-600">Licence health</p><h2 class="mt-1 text-xl font-bold tracking-tight text-slate-800">Licence status</h2></div><span class="text-right text-xs text-slate-400"><strong class="block text-lg text-slate-700">{{ statusTotal }}</strong>records</span></div>
        <div class="mt-7 grid items-center gap-7 sm:grid-cols-[12rem,minmax(0,1fr)]">
          <div class="relative mx-auto h-44 w-44 rounded-full p-3 shadow-inner sm:h-48 sm:w-48" :style="{ background: statusPie }">
            <div class="flex h-full w-full flex-col items-center justify-center rounded-full bg-white shadow-[inset_0_0_0_1px_#edf4ea]">
              <span class="text-3xl font-bold tracking-tight text-[#173a21]">{{ activeStatus?.value ?? statusTotal }}</span>
              <span class="mt-1 max-w-[7rem] truncate text-xs font-semibold text-[#7b8f78]">{{ activeStatus?.label ?? 'Total licences' }}</span>
            </div>
          </div>
          <div class="space-y-2">
            <div v-for="(item, index) in dashboard.status" :key="item.label" class="flex items-center gap-3 rounded-xl px-3 py-2.5" @mouseenter="hoveredStatus=index" @mouseleave="hoveredStatus=null">
              <span class="h-2.5 w-2.5 shrink-0 rounded-full ring-4 ring-[#edf5ea]" :style="{ backgroundColor: statusColours[index] }"></span><span class="min-w-0 flex-1 truncate text-sm font-semibold text-[#5c715a]">{{ item.label }}</span><span class="text-right"><strong class="block text-sm font-bold text-[#173a21]">{{ item.value }}</strong><small class="text-[10px] font-bold text-[#93a391]">{{ statusPercent(item.value) }}%</small></span>
            </div>
          </div>
        </div>
      </article>

      <article class="rounded-[1.5rem] border border-[#d9e8d5] bg-white p-5 shadow-[0_12px_35px_rgba(39,89,45,.07)] sm:p-6 lg:col-span-7">
        <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-[10px] font-bold uppercase tracking-[.22em] text-violet-500">Capacity planning</p><h2 class="mt-1 text-xl font-bold tracking-tight text-slate-800">Seat utilisation by product</h2><p class="mt-1 text-xs text-slate-500">Assigned seats compared with purchased capacity.</p></div><div class="rounded-xl bg-violet-50 px-3 py-2 text-right"><strong class="block text-lg text-violet-700">{{ dashboard.summary.total_seats ? Math.round((dashboard.summary.assigned_seats / dashboard.summary.total_seats) * 100) : 0 }}%</strong><span class="text-[10px] font-semibold uppercase tracking-wider text-violet-500">Overall use</span></div></div>
        <div v-if="dashboard.seat_utilisation.length" class="mt-6 space-y-5">
          <div v-for="item in dashboard.seat_utilisation" :key="item.label">
            <div class="mb-2 flex items-end justify-between gap-4"><span class="truncate text-sm font-bold text-[#50694e]">{{ item.label }}</span><span class="shrink-0 text-xs text-slate-500"><strong class="text-sm text-[#173a21]">{{ item.assigned }}</strong> / {{ item.total }} seats</span></div>
            <div class="flex h-3 overflow-hidden rounded-full bg-slate-100"><span class="h-full bg-[linear-gradient(90deg,#2563eb,#8b5cf6)] transition-all duration-500" :style="{ width: `${Math.min(item.percent, 100)}%` }"></span></div>
            <div class="mt-1.5 flex justify-between text-[10px] font-semibold text-slate-400"><span>{{ item.percent }}% utilised</span><span>{{ item.available }} available</span></div>
          </div>
        </div>
        <div v-else class="mt-6 rounded-xl border border-dashed border-slate-300 p-10 text-center text-sm text-slate-500">No active licence seats have been recorded yet.</div>
      </article>

      <article class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-[0_12px_35px_rgba(15,23,42,.06)] sm:p-6 lg:col-span-8">
        <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-[10px] font-bold uppercase tracking-[.22em] text-amber-500">Renewal forecast</p><h2 class="mt-1 text-xl font-bold tracking-tight text-slate-800">Licence expiries — next 12 months</h2><p class="mt-1 text-xs text-slate-500">Number of licence records due for renewal each month.</p></div><div class="rounded-xl bg-amber-50 px-3 py-2 text-right"><strong class="block text-lg text-amber-700">{{ money(dashboard.summary.renewal_cost) }}</strong><span class="text-[10px] font-semibold uppercase tracking-wider text-amber-600">Recorded renewal value</span></div></div>
        <div class="mt-7 overflow-x-auto pb-2"><div class="flex h-56 min-w-[42rem] items-end gap-2 border-b border-slate-200 px-2">
          <div v-for="item in dashboard.expiry_timeline" :key="item.full_label" class="group flex h-full min-w-[3rem] flex-1 flex-col justify-end text-center" :title="`${item.full_label}: ${item.value} expiries, ${money(item.cost)}`">
            <span class="mb-2 text-xs font-bold text-slate-700">{{ item.value || '' }}</span><span class="mx-auto block w-3/5 rounded-t-lg bg-[linear-gradient(180deg,#fbbf24,#f97316)] transition-all duration-300 group-hover:w-4/5" :style="{ height: `${item.value ? Math.max((item.value / timelineMaximum) * 78, 8) : 2}%`, opacity: item.value ? 1 : .18 }"></span><span class="mt-2 text-[10px] font-semibold text-slate-500">{{ item.label }}</span>
          </div>
        </div></div>
      </article>

      <article class="rounded-[1.5rem] border border-[#d9e8d5] bg-white p-5 shadow-[0_12px_35px_rgba(39,89,45,.07)] sm:p-6 lg:col-span-4">
        <div><p class="text-[10px] font-bold uppercase tracking-[.22em] text-sky-500">Budget exposure</p><h2 class="mt-1 text-xl font-bold tracking-tight text-slate-800">Renewal cost by vendor</h2><p class="mt-1 text-xs text-slate-500">Based on recorded active licence costs.</p></div>
        <div v-if="dashboard.renewal_cost_by_vendor.length" class="mt-6 space-y-4">
          <div v-for="item in dashboard.renewal_cost_by_vendor" :key="item.label"><div class="mb-1.5 flex items-center justify-between gap-3 text-xs"><strong class="truncate text-slate-700">{{ item.label }}</strong><span class="shrink-0 font-bold text-slate-800">{{ money(item.value) }}</span></div><div class="h-2.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-[linear-gradient(90deg,#06b6d4,#2563eb)]" :style="{ width: `${(item.value / costMaximum) * 100}%` }"></div></div></div>
        </div>
        <div v-else class="mt-6 rounded-xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">Add renewal costs to see vendor exposure.</div>
      </article>

      <article class="overflow-hidden rounded-[1.5rem] border border-[#d9e8d5] bg-white shadow-[0_12px_35px_rgba(39,89,45,.07)] lg:col-span-12">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#e2eee0] px-5 py-5 sm:px-6"><div><p class="text-[10px] font-bold uppercase tracking-[.22em] text-rose-500">Action queue</p><h2 class="mt-1 text-xl font-bold tracking-tight text-slate-800">Upcoming renewals</h2><p class="mt-1 text-xs text-slate-500">The next active licences due to expire.</p></div><Link :href="route('it-licenses.index')" class="text-sm font-bold text-emerald-700 hover:underline">Open licence register →</Link></div>
        <div v-if="dashboard.upcoming_renewals.length" class="overflow-x-auto"><table class="table"><thead><tr><th>Licence</th><th>Vendor</th><th>Expiry</th><th>Renewal</th><th>Cost</th></tr></thead><tbody><tr v-for="item in dashboard.upcoming_renewals" :key="item.id"><td><Link :href="route('it-licenses.show', item.id)" class="font-bold text-[#2f7d32] hover:underline">{{ item.software }}</Link><span class="mt-0.5 block text-xs text-slate-500">{{ item.code }}</span></td><td>{{ item.vendor }}</td><td><span class="block font-semibold text-slate-700">{{ prettyDate(item.expiry_date) }}</span><span class="mt-1 inline-flex rounded-full px-2 py-1 text-[10px] font-bold ring-1" :class="urgencyClass(item)">{{ renewalLabel(item) }}</span></td><td><span v-if="item.auto_renew" class="rounded-full bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700">Auto-renew</span><span v-else class="text-xs font-semibold text-slate-500">Manual</span></td><td class="font-semibold text-slate-700">{{ item.renewal_cost ? money(item.renewal_cost, 2) : '—' }}</td></tr></tbody></table></div>
        <div v-else class="p-10 text-center text-sm text-slate-500">No upcoming licence renewals are recorded.</div>
      </article>
    </div>
  </div>
</template>
