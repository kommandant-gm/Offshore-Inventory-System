<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
  title: String,
  description: String,
  stats: { type: Array, default: () => [] },
  rows: { type: [Array, Object], default: () => [] },
  charts: { type: Object, default: null },
});

const page = usePage();
const canEdit = computed(() => page.props.auth?.user?.can?.it_assets_edit);
const rowItems = computed(() => Array.isArray(props.rows) ? props.rows : (props.rows?.data ?? []));

const palette = ['#2563eb', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444', '#06b6d4', '#64748b'];
const maximum = (items) => Math.max(...(items ?? []).map((item) => Number(item.value)), 1);
const total = (items) => (items ?? []).reduce((sum, item) => sum + Number(item.value), 0);
const percent = (value, items) => total(items) ? Math.round((Number(value) / total(items)) * 100) : 0;
const hoveredStatus = ref(null);
const hoveredCategory = ref(null);
const hoveredLocation = ref(null);
const dashboardTotal = computed(() => total(props.charts?.status));
const statValue = (label) => Number(props.stats.find((stat) => stat.label === label)?.value ?? 0);
const statPercent = (value) => dashboardTotal.value ? Math.round((Number(value) / dashboardTotal.value) * 100) : 0;
const statTheme = (label) => ({
  'Total assets': { accent: 'bg-[#234222]', icon: 'bg-[#e7f3e3] text-[#234222]', glow: 'from-[#2f7d32]/10' },
  Assigned: { accent: 'bg-blue-500', icon: 'bg-blue-50 text-blue-700', glow: 'from-blue-500/10' },
  Available: { accent: 'bg-emerald-500', icon: 'bg-emerald-50 text-emerald-700', glow: 'from-emerald-500/10' },
  'Under repair': { accent: 'bg-amber-500', icon: 'bg-amber-50 text-amber-700', glow: 'from-amber-500/10' },
}[label] ?? { accent: 'bg-slate-400', icon: 'bg-slate-50 text-slate-600', glow: 'from-slate-500/10' });
const activeStatus = computed(() => hoveredStatus.value === null ? null : props.charts?.status?.[hoveredStatus.value]);
const pie = computed(() => {
  let angle = 0;
  const entries = props.charts?.status ?? [];
  const sum = total(entries);
  if (!sum) return 'conic-gradient(#e5efe2 0 360deg)';
  return `conic-gradient(${entries.map((item, index) => {
    const start = angle;
    angle += (Number(item.value) / sum) * 360;
    return `${palette[index % palette.length]} ${start}deg ${angle}deg`;
  }).join(',')})`;
});
</script>
<template><Head :title="title"/><AuthenticatedLayout><section class="space-y-6" :class="{'dashboard-shell':charts}">
  <header v-if="charts" class="relative isolate overflow-hidden rounded-[1.75rem] bg-[linear-gradient(120deg,#064e3b_0%,#0f766e_58%,#115e59_100%)] px-5 py-6 text-white shadow-[0_24px_70px_rgba(6,78,59,.22)] sm:px-8 sm:py-8 lg:px-10">
    <div class="pointer-events-none absolute -right-16 -top-24 h-72 w-72 rounded-full bg-cyan-300/20 blur-2xl"></div>
    <div class="pointer-events-none absolute -bottom-28 left-1/3 h-64 w-64 rounded-full bg-emerald-300/15 blur-3xl"></div>
    <div class="relative grid items-end gap-8 lg:grid-cols-[minmax(0,1fr),22rem]">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[.24em] text-emerald-200">KL IT Inventory</p>
        <h1 class="mt-3 text-3xl font-bold tracking-[-.02em] sm:text-4xl lg:text-[2.75rem]">{{title}}</h1>
        <p class="mt-3 max-w-2xl text-sm leading-6 text-white/65 sm:text-base">{{description}} Monitor allocation, health, location and asset age from one responsive workspace.</p>
        <div class="mt-6 flex flex-wrap gap-3">
          <Link :href="route('it-assets.index')" class="inline-flex items-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-emerald-900 shadow-sm transition hover:-translate-y-0.5 hover:bg-emerald-50">View asset register</Link>
          <Link v-if="canEdit" :href="route('it-assets.create')" class="inline-flex items-center rounded-xl border border-white/20 bg-white/10 px-4 py-2.5 text-sm font-bold text-white backdrop-blur transition hover:-translate-y-0.5 hover:bg-white/15">+ Register asset</Link>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3 rounded-2xl border border-white/10 bg-white/[.07] p-4 backdrop-blur-sm">
        <div class="rounded-xl bg-black/10 p-4"><p class="text-[10px] font-semibold uppercase tracking-[.16em] text-white/50">Utilisation</p><p class="mt-2 text-2xl font-bold text-cyan-200 sm:text-3xl">{{statPercent(statValue('Assigned'))}}%</p><p class="mt-1 text-xs text-white/55">{{statValue('Assigned')}} assigned</p></div>
        <div class="rounded-xl bg-black/10 p-4"><p class="text-[10px] font-semibold uppercase tracking-[.16em] text-white/50">Ready now</p><p class="mt-2 text-2xl font-bold text-emerald-300 sm:text-3xl">{{statPercent(statValue('Available'))}}%</p><p class="mt-1 text-xs text-white/55">{{statValue('Available')}} available</p></div>
      </div>
    </div>
  </header>
  <header v-else class="rounded-[2rem] border border-[#d8e7d4] bg-white p-7"><p class="text-xs font-bold uppercase tracking-[.25em] text-[#4f9f4a]">KL IT Inventory</p><h1 class="mt-2 text-3xl font-bold text-[#234222]">{{title}}</h1><p class="mt-2 text-sm text-[#60745d]">{{description}}</p></header>

  <div v-if="stats.length" class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
    <article v-for="stat in stats" :key="stat.label" class="group relative overflow-hidden rounded-2xl border border-[#d8e7d4] bg-white p-4 shadow-[0_8px_28px_rgba(39,89,45,.06)] transition duration-300 hover:-translate-y-1 hover:shadow-[0_16px_36px_rgba(39,89,45,.12)] sm:p-5">
      <div class="absolute inset-x-0 top-0 h-1" :class="statTheme(stat.label).accent"></div>
      <div class="absolute inset-0 bg-gradient-to-br to-transparent opacity-0 transition group-hover:opacity-100" :class="statTheme(stat.label).glow"></div>
      <div class="relative flex items-start justify-between gap-3">
        <div><p class="text-[10px] font-extrabold uppercase tracking-[.16em] text-[#70836e] sm:text-xs">{{stat.label}}</p><p class="mt-2 text-2xl font-black tracking-tight text-[#173a21] sm:text-3xl">{{stat.value}}</p><p v-if="stat.label !== 'Total assets'" class="mt-1 text-[11px] font-semibold text-[#8a9a88]">{{statPercent(stat.value)}}% of all assets</p><p v-else class="mt-1 text-[11px] font-semibold text-[#8a9a88]">Tracked equipment</p></div>
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-sm font-black sm:h-10 sm:w-10" :class="statTheme(stat.label).icon">{{stat.label.charAt(0)}}</span>
      </div>
    </article>
  </div>

  <div v-if="charts" class="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:gap-5">
    <article class="rounded-[1.5rem] border border-[#d9e8d5] bg-white p-5 shadow-[0_12px_35px_rgba(39,89,45,.07)] sm:p-6 lg:col-span-5">
      <div class="flex items-start justify-between gap-4"><div><p class="text-[10px] font-extrabold uppercase tracking-[.22em] text-blue-500">Asset allocation</p><h2 class="mt-1 text-xl font-black tracking-tight text-slate-800">Lifecycle status</h2></div><span class="text-right text-xs text-slate-400"><strong class="block text-lg text-slate-700">{{dashboardTotal}}</strong>assets</span></div>
      <div class="mt-7 grid items-center gap-7 sm:grid-cols-[12rem,minmax(0,1fr)]">
        <div class="relative mx-auto h-44 w-44 rounded-full p-3 shadow-inner transition-transform duration-300 hover:scale-[1.03] sm:h-48 sm:w-48" :style="{background:pie}">
          <div class="flex h-full w-full flex-col items-center justify-center rounded-full bg-white shadow-[inset_0_0_0_1px_#edf4ea]">
            <span class="text-3xl font-black tracking-tight text-[#173a21]">{{activeStatus?.value ?? dashboardTotal}}</span>
            <span class="mt-1 max-w-[7rem] truncate text-xs font-semibold text-[#7b8f78]">{{activeStatus?.label ?? 'Total assets'}}</span>
          </div>
        </div>
        <div class="space-y-2">
          <button v-for="(item,index) in charts.status" :key="item.label" type="button" class="group flex w-full items-center gap-3 rounded-xl border border-transparent px-3 py-2.5 text-left transition hover:border-[#dbead7] hover:bg-[#f7fbf5] focus:border-[#b9dbb1] focus:outline-none" @mouseenter="hoveredStatus=index" @mouseleave="hoveredStatus=null" @focus="hoveredStatus=index" @blur="hoveredStatus=null">
            <span class="h-2.5 w-2.5 shrink-0 rounded-full ring-4 ring-[#edf5ea]" :style="{backgroundColor:palette[index%palette.length]}"/>
            <span class="min-w-0 flex-1 truncate text-sm font-semibold text-[#5c715a]">{{item.label}}</span>
            <span class="text-right"><strong class="block text-sm font-black text-[#173a21]">{{item.value}}</strong><small class="text-[10px] font-bold text-[#93a391]">{{percent(item.value,charts.status)}}%</small></span>
          </button>
        </div>
      </div>
    </article>

    <article class="rounded-[1.5rem] border border-[#d9e8d5] bg-white p-5 shadow-[0_12px_35px_rgba(39,89,45,.07)] sm:p-6 lg:col-span-7">
      <div class="flex items-start justify-between gap-4"><div><p class="text-[10px] font-extrabold uppercase tracking-[.22em] text-indigo-500">Inventory mix</p><h2 class="mt-1 text-xl font-black tracking-tight text-slate-800">Assets by category</h2></div><p class="text-right text-xs text-slate-400"><strong class="block text-lg text-slate-700">{{charts.categories.length}}</strong>categories</p></div>
      <div class="mt-7 space-y-5">
        <button v-for="(item,index) in charts.categories" :key="item.label" type="button" class="block w-full text-left focus:outline-none" @mouseenter="hoveredCategory=index" @mouseleave="hoveredCategory=null" @focus="hoveredCategory=index" @blur="hoveredCategory=null">
          <div class="mb-2 flex items-end justify-between gap-4"><span class="truncate text-sm font-bold text-[#50694e]">{{item.label}}</span><span class="shrink-0 text-sm"><strong class="text-[#173a21]">{{item.value}}</strong><small class="ml-1 text-[#91a08f]">({{percent(item.value,charts.categories)}}%)</small></span></div>
          <div class="h-3 overflow-hidden rounded-full bg-slate-100"><div class="h-full origin-left rounded-full bg-[linear-gradient(90deg,#2563eb,#8b5cf6)] transition-all duration-500" :class="hoveredCategory===index ? 'brightness-110' : ''" :style="{width:`${(item.value/maximum(charts.categories))*100}%`,transform:hoveredCategory===index?'scaleY(1.35)':'scaleY(1)'}"/></div>
        </button>
      </div>
    </article>

    <article class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-[0_12px_35px_rgba(15,23,42,.06)] sm:p-6 lg:col-span-7">
      <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-[10px] font-extrabold uppercase tracking-[.22em] text-violet-500">Deployment footprint</p><h2 class="mt-1 text-xl font-black tracking-tight text-slate-800">Assets by location</h2><p class="mt-1 text-xs text-slate-500">Current physical distribution across active sites.</p></div><div class="rounded-xl bg-violet-50 px-3 py-2 text-right"><strong class="block text-lg text-violet-700">{{charts.locations.length}}</strong><span class="text-[10px] font-semibold uppercase tracking-wider text-violet-500">Active sites</span></div></div>
      <div class="mt-6 space-y-3">
        <button v-for="(item,index) in charts.locations" :key="item.label" type="button" class="group w-full rounded-2xl border border-slate-200 bg-white p-4 text-left transition hover:border-violet-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-violet-200" :class="hoveredLocation===index?'bg-violet-50/40':''" @mouseenter="hoveredLocation=index" @mouseleave="hoveredLocation=null" @focus="hoveredLocation=index" @blur="hoveredLocation=null">
          <div class="flex items-center gap-3 sm:gap-4"><span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-violet-100 text-violet-600"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-5 w-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 21s6-5.2 6-11a6 6 0 1 0-12 0c0 5.8 6 11 6 11Z"/><circle cx="12" cy="10" r="2" stroke-width="1.8"/></svg></span><span class="min-w-0 flex-1"><span class="flex items-center justify-between gap-3"><strong class="truncate text-sm text-slate-800">{{item.label}}</strong><strong class="text-lg text-slate-800">{{item.value}}</strong></span><span class="mt-2 block h-2 overflow-hidden rounded-full bg-slate-100"><span class="block h-full rounded-full bg-[linear-gradient(90deg,#8b5cf6,#3b82f6)] transition-all duration-500" :style="{width:`${percent(item.value,charts.locations)}%`}"></span></span><small class="mt-1.5 block text-slate-500">{{percent(item.value,charts.locations)}}% of the asset fleet</small></span></div>
        </button>
        <div v-if="!charts.locations.length" class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">No asset locations recorded yet.</div>
      </div>
    </article>

    <article class="rounded-[1.5rem] border border-[#d9e8d5] bg-white p-5 shadow-[0_12px_35px_rgba(39,89,45,.07)] sm:p-6 lg:col-span-5">
      <div><p class="text-[10px] font-extrabold uppercase tracking-[.22em] text-amber-500">Lifecycle planning</p><h2 class="mt-1 text-xl font-black tracking-tight text-slate-800">Asset age profile</h2></div>
      <div class="mt-6 space-y-4">
        <div v-for="(item,index) in charts.age" :key="item.label" class="grid grid-cols-[6.5rem,minmax(0,1fr),2rem] items-center gap-3 sm:grid-cols-[7.5rem,minmax(0,1fr),2rem]">
          <span class="truncate text-xs font-semibold text-[#637761]">{{item.label}}</span><div class="h-8 overflow-hidden rounded-lg bg-[#f0f5ee] p-1"><div class="h-full min-w-[.35rem] rounded-md transition-all duration-500 hover:brightness-110" :style="{width:`${(item.value/maximum(charts.age))*100}%`,backgroundColor:palette[index%palette.length]}"/></div><strong class="text-right text-sm text-[#173a21]">{{item.value}}</strong>
        </div>
      </div>
    </article>

    <article class="rounded-[1.5rem] border border-[#d9e8d5] bg-white p-5 shadow-[0_12px_35px_rgba(39,89,45,.07)] sm:p-6 lg:col-span-6">
      <div><p class="text-[10px] font-extrabold uppercase tracking-[.22em] text-rose-500">Health snapshot</p><h2 class="mt-1 text-xl font-black tracking-tight text-slate-800">Condition distribution</h2></div>
      <div class="mt-6 space-y-4"><div v-for="(item,index) in charts.conditions" :key="item.label" class="grid grid-cols-[5.5rem,minmax(0,1fr),2rem] items-center gap-3 sm:grid-cols-[7rem,minmax(0,1fr),3rem]"><span class="truncate text-xs font-semibold text-[#637761] sm:text-sm">{{item.label}}</span><div class="h-8 overflow-hidden rounded-lg bg-[#edf5ea]"><div class="flex h-full min-w-[2.5rem] items-center justify-end rounded-lg pr-2 text-[10px] font-black text-white transition-all duration-500" :style="{width:`${Math.max(percent(item.value,charts.conditions),8)}%`,backgroundColor:palette[index%palette.length]}">{{percent(item.value,charts.conditions)}}%</div></div><strong class="text-right text-sm text-[#173a21]">{{item.value}}</strong></div></div>
    </article>

    <article class="rounded-[1.5rem] border border-[#d9e8d5] bg-white p-5 shadow-[0_12px_35px_rgba(39,89,45,.07)] sm:p-6 lg:col-span-6">
      <div><p class="text-[10px] font-extrabold uppercase tracking-[.22em] text-sky-500">Acquisition trend</p><h2 class="mt-1 text-xl font-black tracking-tight text-slate-800">Purchases by year</h2></div>
      <div v-if="charts.purchaseYears.length" class="mt-7 overflow-x-auto pb-1"><div class="flex h-48 min-w-[24rem] items-end gap-2 border-b border-slate-200 px-2"><div v-for="item in charts.purchaseYears" :key="item.label" class="group flex h-full min-w-[2.75rem] flex-1 flex-col justify-end text-center"><span class="mb-2 text-xs font-black text-slate-700 opacity-70 transition group-hover:opacity-100">{{item.value}}</span><div class="mx-auto w-3/5 rounded-t-lg bg-[linear-gradient(180deg,#38bdf8,#2563eb)] transition-all duration-300 group-hover:w-3/4" :style="{height:`${Math.max((item.value/maximum(charts.purchaseYears))*78,5)}%`}"/><span class="mt-2 text-[10px] font-semibold text-slate-500">{{item.label}}</span></div></div></div><div v-else class="mt-6 rounded-xl border border-dashed border-slate-300 p-10 text-center text-sm text-slate-500">Add purchase years to assets to populate this trend.</div>
    </article>
  </div>
  <div v-if="rowItems.length" class="overflow-hidden rounded-[1.5rem] border border-[#d8e7d4] bg-white">
    <div class="overflow-x-auto"><table class="table"><thead><tr><th>Asset tag</th><th>Details</th><th>Status</th></tr></thead><tbody><tr v-for="row in rowItems" :key="row.asset_tag"><td class="font-bold"><Link v-if="row.asset_id" class="text-[#2f7d32]" :href="route('it-assets.show',row.asset_id)">{{row.asset_tag}}</Link><template v-else>{{row.asset_tag}}</template></td><td>{{row.detail||'\u2014'}}</td><td>{{row.meta||'\u2014'}}</td></tr></tbody></table></div>
  </div>
  <div v-if="!stats.length && !rowItems.length" class="rounded-[1.5rem] border border-dashed border-[#cfe6c8] bg-white p-12 text-center text-[#60745d]">No records are available for this section yet.</div>
  <Link v-if="!charts" class="btn border-[#cfe6c8] bg-white" :href="route('it-assets.index')">Open IT Asset Register</Link>
</section></AuthenticatedLayout></template>

<style scoped>
.dashboard-shell {
  font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
  font-feature-settings: "kern" 1, "tnum" 1;
}

.dashboard-shell .font-black,
.dashboard-shell .font-extrabold {
  font-weight: 700;
}
</style>
