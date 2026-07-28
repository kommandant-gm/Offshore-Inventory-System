<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
  dashboard: { type: Object, required: true },
});

const statusColours = ['#10b981', '#f59e0b', '#ef4444', '#64748b'];
const hoveredStatus = ref(null);
const seatView = ref('software');
const filters = reactive({ status: null, software: null, licenseKey: null, expiryMonth: null, vendor: null, capacity: null });
const filterKeys = Object.keys(filters);
const hasFilters = computed(() => filterKeys.some((key) => filters[key] !== null));
const filterLabel = (key) => ({ licenseKey: 'Licence key', expiryMonth: 'Expiry month', capacity: 'Seat capacity' }[key] ?? key);
const filterValue = (key) => key === 'licenseKey'
  ? (props.dashboard.licenses.find((row) => row.license_key_group === filters.licenseKey)?.license_key_reference ?? 'Selected key')
  : filters[key];
const rowMatches = (row, except = null) => filterKeys.every((key) => {
  if (key === except || filters[key] === null) return true;
  if (key === 'capacity') return filters.capacity === 'Assigned seats' ? row.seats_assigned > 0 : row.seats_available > 0;
  if (key === 'licenseKey') return row.license_key_group === filters.licenseKey;
  if (key === 'expiryMonth') return row.expiry_month === filters.expiryMonth;
  return row[key] === filters[key];
});
const recordsFor = (except = null) => props.dashboard.licenses.filter((row) => rowMatches(row, except));
const filteredLicenses = computed(() => recordsFor());
const toggleFilter = (key, value) => { filters[key] = filters[key] === value ? null : value; };
const clearFilters = () => filterKeys.forEach((key) => { filters[key] = null; });

const statusItems = computed(() => props.dashboard.status.map((item) => ({
  ...item,
  value: recordsFor('status').filter((row) => row.status === item.label).length,
})));
const statusTotal = computed(() => statusItems.value.reduce((sum, item) => sum + Number(item.value), 0));
const activeStatus = computed(() => hoveredStatus.value === null ? null : statusItems.value[hoveredStatus.value]);
const statusPercent = (value) => statusTotal.value ? Math.round((Number(value) / statusTotal.value) * 100) : 0;
const statusPie = computed(() => {
  let angle = 0;
  if (!statusTotal.value) return 'conic-gradient(#e5e7eb 0 360deg)';
  return `conic-gradient(${statusItems.value.map((item, index) => {
    const start = angle;
    angle += (Number(item.value) / statusTotal.value) * 360;
    return `${statusColours[index % statusColours.length]} ${start}deg ${angle}deg`;
  }).join(',')})`;
});

const grouped = (rows, key) => Array.from(rows.reduce((groups, row) => {
  const label = row[key];
  if (!groups.has(label)) groups.set(label, []);
  groups.get(label).push(row);
  return groups;
}, new Map()));
const seatDimension = computed(() => seatView.value === 'software' ? 'software' : 'licenseKey');
const seatProperty = computed(() => seatView.value === 'software' ? 'software' : 'license_key_group');
const setSeatView = (view) => {
  seatView.value = view;
  filters.software = null;
  filters.licenseKey = null;
};
const seatItems = computed(() => grouped(recordsFor(seatDimension.value).filter((row) => row.active), seatProperty.value)
  .map(([id, rows]) => {
    const purchased = rows.reduce((sum, row) => sum + Number(row.seats_total), 0);
    const assigned = rows.reduce((sum, row) => sum + Number(row.seats_assigned), 0);
    return { id, label: seatView.value === 'software' ? id : rows[0].license_key_reference, assigned, available: Math.max(0, purchased - assigned), total: purchased, percent: purchased ? Math.round((assigned / purchased) * 100) : 0 };
  })
  .sort((a, b) => (a.id === filters[seatDimension.value] ? -1 : b.id === filters[seatDimension.value] ? 1 : b.assigned - a.assigned))
  .slice(0, 8));
const timelineItems = computed(() => props.dashboard.expiry_timeline.map((month) => {
  const rows = recordsFor('expiryMonth').filter((row) => row.active && row.expiry_month === month.full_label);
  return { ...month, value: rows.length, cost: rows.reduce((sum, row) => sum + Number(row.renewal_cost), 0) };
}));
const vendorItems = computed(() => grouped(recordsFor('vendor').filter((row) => row.active && Number(row.renewal_cost) > 0), 'vendor')
  .map(([label, rows]) => ({ label, value: rows.reduce((sum, row) => sum + Number(row.renewal_cost), 0) }))
  .sort((a, b) => (a.label === filters.vendor ? -1 : b.label === filters.vendor ? 1 : b.value - a.value))
  .slice(0, 6));

const summary = computed(() => {
  const active = filteredLicenses.value.filter((row) => row.active);
  const totalSeats = active.reduce((sum, row) => sum + Number(row.seats_total), 0);
  const assignedSeats = active.reduce((sum, row) => sum + Number(row.seats_assigned), 0);
  return {
    total_licenses: filteredLicenses.value.length,
    total_seats: totalSeats,
    assigned_seats: assignedSeats,
    available_seats: Math.max(0, totalSeats - assignedSeats),
    expiring_soon: filteredLicenses.value.filter((row) => row.status === 'Expiring soon').length,
    expired: filteredLicenses.value.filter((row) => row.status === 'Expired').length,
    renewal_cost: active.reduce((sum, row) => sum + Number(row.renewal_cost), 0),
  };
});
const cards = computed(() => [
  { label: 'Total licences', value: summary.value.total_licenses, note: `${summary.value.total_seats} purchased seats`, accent: 'bg-[#234222]', icon: 'L', iconClass: 'bg-[#e7f3e3] text-[#234222]', filterKey: null },
  { label: 'Assigned seats', value: summary.value.assigned_seats, note: 'Currently in use', accent: 'bg-blue-500', icon: 'A', iconClass: 'bg-blue-50 text-blue-700', filterKey: 'capacity' },
  { label: 'Available seats', value: summary.value.available_seats, note: 'Ready to allocate', accent: 'bg-emerald-500', icon: 'S', iconClass: 'bg-emerald-50 text-emerald-700', filterKey: 'capacity' },
  { label: 'Expiring soon', value: summary.value.expiring_soon, note: 'Within the next 30 days', accent: 'bg-amber-500', icon: 'E', iconClass: 'bg-amber-50 text-amber-700', filterKey: 'status' },
  { label: 'Expired', value: summary.value.expired, note: 'Needs review', accent: 'bg-rose-500', icon: '!', iconClass: 'bg-rose-50 text-rose-700', filterKey: 'status' },
]);
const selectCard = (card) => card.filterKey ? toggleFilter(card.filterKey, card.label) : clearFilters();
const cardSelected = (card) => card.filterKey && filters[card.filterKey] === card.label;

const timelineMaximum = computed(() => Math.max(...timelineItems.value.map((item) => Number(item.value)), 1));
const costMaximum = computed(() => Math.max(...vendorItems.value.map((item) => Number(item.value)), 1));
const money = (value, decimals = 0) => new Intl.NumberFormat('en-MY', {
  style: 'currency', currency: 'MYR', minimumFractionDigits: decimals, maximumFractionDigits: decimals,
}).format(Number(value ?? 0));
const prettyDate = (value) => value ? new Intl.DateTimeFormat('en-MY', {
  day: 'numeric', month: 'short', year: 'numeric',
}).format(new Date(`${value}T00:00:00`)) : 'No expiry';
const renewalLabel = (item) => {
  if (item.days_until_expiry === null) return 'No expiry date';
  if (item.days_until_expiry < 0) return `Expired ${Math.abs(item.days_until_expiry)} days ago`;
  if (item.days_until_expiry === 0) return 'Expires today';
  if (item.days_until_expiry === 1) return 'Expires tomorrow';
  return `${item.days_until_expiry} days left`;
};
const urgencyClass = (item) => item.days_until_expiry === null
  ? 'bg-slate-50 text-slate-600 ring-slate-200'
  : (item.days_until_expiry < 0
    ? 'bg-rose-50 text-rose-700 ring-rose-200'
    : (item.days_until_expiry <= 30 ? 'bg-amber-50 text-amber-700 ring-amber-200' : 'bg-emerald-50 text-emerald-700 ring-emerald-200'));
const detailRows = computed(() => {
  const rows = hasFilters.value
    ? filteredLicenses.value
    : props.dashboard.licenses.filter((row) => row.active && row.days_until_expiry !== null && row.days_until_expiry >= 0);
  return rows.slice().sort((a, b) => {
    if (a.expiry_date === null) return 1;
    if (b.expiry_date === null) return -1;
    return a.expiry_date.localeCompare(b.expiry_date);
  }).slice(0, 10);
});
</script>

<template>
  <div class="space-y-5">
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-5 lg:gap-4">
      <button v-for="card in cards" :key="card.label" type="button" class="relative overflow-hidden rounded-2xl border border-[#d8e7d4] bg-white p-4 text-left shadow-[0_8px_28px_rgba(39,89,45,.06)] transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-emerald-300 sm:p-5" :class="cardSelected(card) ? 'ring-2 ring-emerald-400' : (hasFilters ? 'opacity-85' : '')" :aria-pressed="Boolean(cardSelected(card))" @click="selectCard(card)">
        <div class="absolute inset-x-0 top-0 h-1" :class="card.accent"></div>
        <div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="truncate text-[10px] font-bold uppercase tracking-[.16em] text-[#70836e] sm:text-xs">{{ card.label }}</p><p class="mt-2 text-2xl font-bold tracking-tight text-[#173a21] sm:text-3xl">{{ card.value }}</p><p class="mt-1 truncate text-[11px] font-semibold text-[#8a9a88]">{{ card.note }}</p></div><span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-sm font-bold sm:h-10 sm:w-10" :class="card.iconClass">{{ card.icon }}</span></div>
      </button>
    </div>

    <div v-if="hasFilters" class="flex flex-wrap items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50/70 px-4 py-3" aria-live="polite">
      <span class="mr-1 text-xs font-bold uppercase tracking-wider text-emerald-800">{{ filteredLicenses.length }} matching</span>
      <button v-for="key in filterKeys.filter((key) => filters[key] !== null)" :key="key" type="button" class="rounded-full border border-emerald-200 bg-white px-3 py-1.5 text-xs font-semibold capitalize text-emerald-900 shadow-sm hover:border-emerald-400" @click="filters[key]=null">{{ filterLabel(key) }}: {{ filterValue(key) }} <span aria-hidden="true">×</span></button>
      <button type="button" class="ml-auto text-xs font-bold text-emerald-800 underline decoration-emerald-300 underline-offset-2" @click="clearFilters">Clear all</button>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:gap-5">
      <article class="rounded-[1.5rem] border border-[#d9e8d5] bg-white p-5 shadow-[0_12px_35px_rgba(39,89,45,.07)] sm:p-6 lg:col-span-5">
        <div class="flex items-start justify-between gap-4"><div><p class="text-[10px] font-bold uppercase tracking-[.22em] text-emerald-600">Licence health</p><h2 class="mt-1 text-xl font-bold tracking-tight text-slate-800">Licence status</h2><p class="mt-1 text-xs text-slate-500">Select a status to filter the dashboard.</p></div><span class="text-right text-xs text-slate-400"><strong class="block text-lg text-slate-700">{{ statusTotal }}</strong>records</span></div>
        <div class="mt-7 grid items-center gap-7 sm:grid-cols-[12rem,minmax(0,1fr)]">
          <div class="relative mx-auto h-44 w-44 rounded-full p-3 shadow-inner sm:h-48 sm:w-48" :style="{ background: statusPie }"><div class="flex h-full w-full flex-col items-center justify-center rounded-full bg-white shadow-[inset_0_0_0_1px_#edf4ea]"><span class="text-3xl font-bold tracking-tight text-[#173a21]">{{ activeStatus?.value ?? statusTotal }}</span><span class="mt-1 max-w-[7rem] truncate text-xs font-semibold text-[#7b8f78]">{{ activeStatus?.label ?? (hasFilters ? 'Matching licences' : 'Total licences') }}</span></div></div>
          <div class="space-y-2">
            <button v-for="(item, index) in statusItems" :key="item.label" type="button" class="flex w-full items-center gap-3 rounded-xl border border-transparent px-3 py-2.5 text-left transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-200" :class="filters.status === item.label ? 'border-emerald-300 bg-emerald-50 ring-1 ring-emerald-200' : ''" :aria-pressed="filters.status === item.label" @click="toggleFilter('status', item.label)" @mouseenter="hoveredStatus=index" @mouseleave="hoveredStatus=null"><span class="h-2.5 w-2.5 shrink-0 rounded-full ring-4 ring-[#edf5ea]" :style="{ backgroundColor: statusColours[index] }"></span><span class="min-w-0 flex-1 truncate text-sm font-semibold text-[#5c715a]">{{ item.label }}</span><span class="text-right"><strong class="block text-sm font-bold text-[#173a21]">{{ item.value }}</strong><small class="text-[10px] font-bold text-[#93a391]">{{ statusPercent(item.value) }}%</small></span></button>
          </div>
        </div>
      </article>

      <article class="rounded-[1.5rem] border border-[#d9e8d5] bg-white p-5 shadow-[0_12px_35px_rgba(39,89,45,.07)] sm:p-6 lg:col-span-7">
        <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-[10px] font-bold uppercase tracking-[.22em] text-violet-500">Capacity planning</p><h2 class="mt-1 text-xl font-bold tracking-tight text-slate-800">Seat utilisation by {{ seatView === 'software' ? 'product' : 'licence key' }}</h2><p class="mt-1 text-xs text-slate-500">Select a {{ seatView === 'software' ? 'product' : 'masked key reference' }} to cross-filter every visual.</p></div><div class="rounded-xl bg-violet-50 px-3 py-2 text-right"><strong class="block text-lg text-violet-700">{{ summary.total_seats ? Math.round((summary.assigned_seats / summary.total_seats) * 100) : 0 }}%</strong><span class="text-[10px] font-semibold uppercase tracking-wider text-violet-500">Overall use</span></div></div>
        <div class="mt-4 inline-flex rounded-xl bg-slate-100 p-1" aria-label="Seat utilisation grouping">
          <button type="button" class="rounded-lg px-3 py-1.5 text-xs font-bold transition" :class="seatView === 'software' ? 'bg-white text-violet-700 shadow-sm' : 'text-slate-500 hover:text-slate-800'" :aria-pressed="seatView === 'software'" @click="setSeatView('software')">By product</button>
          <button type="button" class="rounded-lg px-3 py-1.5 text-xs font-bold transition" :class="seatView === 'licenseKey' ? 'bg-white text-violet-700 shadow-sm' : 'text-slate-500 hover:text-slate-800'" :aria-pressed="seatView === 'licenseKey'" @click="setSeatView('licenseKey')">By licence key</button>
        </div>
        <div v-if="seatItems.length" class="mt-6 space-y-3">
          <button v-for="item in seatItems" :key="item.id" type="button" class="block w-full rounded-xl p-2 text-left transition hover:bg-violet-50 focus:outline-none focus:ring-2 focus:ring-violet-200" :class="filters[seatDimension] === item.id ? 'bg-violet-50 ring-1 ring-violet-300' : ''" :aria-pressed="filters[seatDimension] === item.id" @click="toggleFilter(seatDimension, item.id)"><div class="mb-2 flex items-end justify-between gap-4"><span class="truncate text-sm font-bold text-[#50694e]" :title="item.label">{{ item.label }}</span><span class="shrink-0 text-xs text-slate-500"><strong class="text-sm text-[#173a21]">{{ item.assigned }}</strong> / {{ item.total }} seats</span></div><div class="flex h-3 overflow-hidden rounded-full bg-slate-100"><span class="h-full bg-[linear-gradient(90deg,#2563eb,#8b5cf6)] transition-all duration-500" :style="{ width: `${Math.min(item.percent, 100)}%` }"></span></div><div class="mt-1.5 flex justify-between text-[10px] font-semibold text-slate-400"><span>{{ item.percent }}% utilised</span><span :class="item.available ? 'font-bold text-emerald-600' : ''">{{ item.available }} available</span></div></button>
        </div><div v-else class="mt-6 rounded-xl border border-dashed border-slate-300 p-10 text-center text-sm text-slate-500">No active licence seats match this selection.</div>
      </article>

      <article class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-[0_12px_35px_rgba(15,23,42,.06)] sm:p-6 lg:col-span-8">
        <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-[10px] font-bold uppercase tracking-[.22em] text-amber-500">Renewal forecast</p><h2 class="mt-1 text-xl font-bold tracking-tight text-slate-800">Licence expiries — next 12 months</h2><p class="mt-1 text-xs text-slate-500">Select a month to filter the other visuals.</p></div><div class="rounded-xl bg-amber-50 px-3 py-2 text-right"><strong class="block text-lg text-amber-700">{{ money(summary.renewal_cost) }}</strong><span class="text-[10px] font-semibold uppercase tracking-wider text-amber-600">Selected renewal value</span></div></div>
        <div class="mt-7 overflow-x-auto pb-2"><div class="flex h-56 min-w-[42rem] items-end gap-2 border-b border-slate-200 px-2"><button v-for="item in timelineItems" :key="item.full_label" type="button" class="group flex h-full min-w-[3rem] flex-1 flex-col justify-end rounded-t-lg text-center transition focus:outline-none focus:ring-2 focus:ring-amber-200" :class="filters.expiryMonth === item.full_label ? 'bg-amber-50 ring-1 ring-amber-300' : ''" :aria-pressed="filters.expiryMonth === item.full_label" :title="`${item.full_label}: ${item.value} expiries, ${money(item.cost)}`" @click="toggleFilter('expiryMonth', item.full_label)"><span class="mb-2 text-xs font-bold text-slate-700">{{ item.value || '' }}</span><span class="mx-auto block w-3/5 rounded-t-lg bg-[linear-gradient(180deg,#fbbf24,#f97316)] transition-all duration-300 group-hover:w-4/5" :style="{ height: `${item.value ? Math.max((item.value / timelineMaximum) * 78, 8) : 2}%`, opacity: item.value ? 1 : .18 }"></span><span class="mt-2 text-[10px] font-semibold text-slate-500">{{ item.label }}</span></button></div></div>
      </article>

      <article class="rounded-[1.5rem] border border-[#d9e8d5] bg-white p-5 shadow-[0_12px_35px_rgba(39,89,45,.07)] sm:p-6 lg:col-span-4">
        <div><p class="text-[10px] font-bold uppercase tracking-[.22em] text-sky-500">Budget exposure</p><h2 class="mt-1 text-xl font-bold tracking-tight text-slate-800">Renewal cost by vendor</h2><p class="mt-1 text-xs text-slate-500">Select a vendor to cross-filter the dashboard.</p></div>
        <div v-if="vendorItems.length" class="mt-6 space-y-2"><button v-for="item in vendorItems" :key="item.label" type="button" class="block w-full rounded-xl p-2 text-left transition hover:bg-sky-50 focus:outline-none focus:ring-2 focus:ring-sky-200" :class="filters.vendor === item.label ? 'bg-sky-50 ring-1 ring-sky-300' : ''" :aria-pressed="filters.vendor === item.label" @click="toggleFilter('vendor', item.label)"><div class="mb-1.5 flex items-center justify-between gap-3 text-xs"><strong class="truncate text-slate-700">{{ item.label }}</strong><span class="shrink-0 font-bold text-slate-800">{{ money(item.value) }}</span></div><div class="h-2.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-[linear-gradient(90deg,#06b6d4,#2563eb)]" :style="{ width: `${(item.value / costMaximum) * 100}%` }"></div></div></button></div>
        <div v-else class="mt-6 rounded-xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">No renewal costs match this selection.</div>
      </article>

      <article class="overflow-hidden rounded-[1.5rem] border border-[#d9e8d5] bg-white shadow-[0_12px_35px_rgba(39,89,45,.07)] lg:col-span-12">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#e2eee0] px-5 py-5 sm:px-6"><div><p class="text-[10px] font-bold uppercase tracking-[.22em] text-rose-500">{{ hasFilters ? 'Filtered details' : 'Action queue' }}</p><h2 class="mt-1 text-xl font-bold tracking-tight text-slate-800">{{ hasFilters ? 'Selected licence details' : 'Upcoming renewals' }}</h2><p class="mt-1 text-xs text-slate-500">{{ hasFilters ? 'Records matching every active dashboard selection.' : 'The next active licences due to expire.' }}</p></div><Link :href="route('it-licenses.index')" class="text-sm font-bold text-emerald-700 hover:underline">Open licence register →</Link></div>
        <div v-if="detailRows.length" class="overflow-x-auto"><table class="table"><thead><tr><th>Licence</th><th>Licence key</th><th>Seat</th><th>Vendor</th><th>Expiry</th><th>Renewal</th><th>Cost</th></tr></thead><tbody><tr v-for="item in detailRows" :key="item.id"><td><Link :href="route('it-licenses.show', item.id)" class="font-bold text-[#2f7d32] hover:underline">{{ item.software }}</Link><span class="mt-0.5 block text-xs text-slate-500">{{ item.code }}</span></td><td class="text-xs font-semibold text-slate-600">{{ item.license_key_reference }}</td><td><span v-if="item.seats_available" class="rounded-full bg-emerald-50 px-2 py-1 text-xs font-bold text-emerald-700">Available</span><span v-else class="text-xs font-semibold text-slate-600">{{ item.assigned_to || 'Assigned' }}</span></td><td>{{ item.vendor }}</td><td><span class="block font-semibold text-slate-700">{{ prettyDate(item.expiry_date) }}</span><span class="mt-1 inline-flex rounded-full px-2 py-1 text-[10px] font-bold ring-1" :class="urgencyClass(item)">{{ renewalLabel(item) }}</span></td><td><span v-if="item.auto_renew" class="rounded-full bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700">Auto-renew</span><span v-else class="text-xs font-semibold text-slate-500">Manual</span></td><td class="font-semibold text-slate-700">{{ item.renewal_cost ? money(item.renewal_cost, 2) : '—' }}</td></tr></tbody></table></div>
        <div v-else class="p-10 text-center text-sm text-slate-500">No licences match the current selection.</div>
      </article>
    </div>
  </div>
</template>
