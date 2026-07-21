<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CustomSelect from '@/Components/CustomSelect.vue';
import AssetAssignmentModal from '@/Components/AssetAssignmentModal.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
  title: String,
  description: String,
  stats: { type: Array, default: () => [] },
  rows: { type: [Array, Object], default: () => [] },
  charts: { type: Object, default: null },
  filters: { type: Object, default: null },
  filterOptions: { type: Object, default: () => ({}) },
  availableAssets: { type: Array, default: () => [] },
  userOptions: { type: Array, default: () => [] },
});

const page = usePage();
const canEdit = computed(() => page.props.auth?.user?.can?.it_assets_edit);
const selectedAsset = ref(null);
const selectedAvailableId = ref('');
const filterForm = reactive({ ...(props.filters ?? {}) });
const rowItems = computed(() => Array.isArray(props.rows) ? props.rows : (props.rows?.data ?? []));
const activeFilters = computed(() => Object.values(filterForm).filter((value) => value !== '' && value !== null).length);
const applyFilters = () => router.get(route('it-assets.assignments'), filterForm, { preserveState: true, preserveScroll: true, replace: true });
const clearFilters = () => {
  Object.keys(filterForm).forEach((key) => { filterForm[key] = ''; });
  applyFilters();
};
const openAvailableAsset = () => {
  selectedAsset.value = props.availableAssets.find((asset) => String(asset.id) === String(selectedAvailableId.value)) || null;
};
const checkIn = (row) => {
  if (!window.confirm(`Check in ${row.asset_tag} from ${row.detail}?`)) return;
  router.patch(route('it-assets.check-in', row.asset_id), {}, { preserveScroll: true });
};

const palette = ['#2f7d32', '#55a651', '#7abd70', '#a6d49c', '#d3e9ce', '#f0b65a', '#df7f67'];
const maximum = (items) => Math.max(...(items ?? []).map((item) => Number(item.value)), 1);
const total = (items) => (items ?? []).reduce((sum, item) => sum + Number(item.value), 0);
const percent = (value, items) => total(items) ? Math.round((Number(value) / total(items)) * 100) : 0;
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
<template><Head :title="title"/><AuthenticatedLayout><section class="space-y-6">
  <header class="rounded-[2rem] border border-[#d8e7d4] bg-white p-7"><p class="text-xs font-bold uppercase tracking-[.25em] text-[#4f9f4a]">KL IT Inventory</p><h1 class="mt-2 text-3xl font-bold text-[#234222]">{{title}}</h1><p class="mt-2 text-sm text-[#60745d]">{{description}}</p></header>
  <div v-if="stats.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><div v-for="stat in stats" :key="stat.label" class="rounded-[1.5rem] border border-[#d8e7d4] bg-white p-6"><p class="text-xs font-bold uppercase tracking-wider text-[#7f9a7a]">{{stat.label}}</p><p class="mt-3 text-3xl font-bold text-[#2f7d32]">{{stat.value}}</p></div></div>

  <form v-if="filters" class="rounded-[1.7rem] border border-[#d8e7d4] bg-white p-5 shadow-sm" @submit.prevent="applyFilters">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"><div><div class="flex items-center gap-2"><h2 class="font-bold text-[#234222]">Filter assignments</h2><span v-if="activeFilters" class="rounded-full bg-[#e8f5e4] px-2.5 py-1 text-xs font-bold text-[#2f7d32]">{{activeFilters}} active</span></div><p class="mt-1 text-xs text-[#7f9a7a]">Search current device custody records.</p></div><div class="flex gap-2"><button v-if="activeFilters" type="button" class="rounded-xl border border-[#d8e7d4] px-4 py-2 text-sm font-semibold text-[#60745d]" @click="clearFilters">Clear all</button><button type="submit" class="rounded-xl bg-[#4f9f4a] px-5 py-2 text-sm font-bold text-white">Apply filters</button></div></div>
    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
      <label class="sm:col-span-2"><span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#60745d]">Search</span><input v-model.trim="filterForm.search" type="search" class="w-full rounded-xl border-[#d8e7d4] text-sm focus:border-[#4f9f4a] focus:ring-[#4f9f4a]" placeholder="Asset tag, serial, device or person" /></label>
      <label><span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#60745d]">Department</span><CustomSelect v-model="filterForm.department" class="w-full rounded-xl border-[#d8e7d4] text-sm"><option value="">All departments</option><option v-for="option in filterOptions.departments" :key="option" :value="option">{{option}}</option></CustomSelect></label>
      <label><span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#60745d]">Category</span><CustomSelect v-model="filterForm.category" class="w-full rounded-xl border-[#d8e7d4] text-sm"><option value="">All categories</option><option v-for="option in filterOptions.categories" :key="option.id" :value="String(option.id)">{{option.name}}</option></CustomSelect></label>
      <label><span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#60745d]">Location</span><CustomSelect v-model="filterForm.location" class="w-full rounded-xl border-[#d8e7d4] text-sm"><option value="">All locations</option><option v-for="option in filterOptions.locations" :key="option.id" :value="String(option.id)">{{option.code}} - {{option.name}}</option></CustomSelect></label>
      <label><span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#60745d]">Operating system</span><CustomSelect v-model="filterForm.os" class="w-full rounded-xl border-[#d8e7d4] text-sm"><option value="">All operating systems</option><option v-for="option in filterOptions.operatingSystems" :key="option" :value="option">{{option}}</option></CustomSelect></label>
    </div>
  </form>

  <div v-if="filters && canEdit" class="rounded-[1.7rem] border border-[#d8e7d4] bg-white p-5 shadow-sm">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
      <label class="flex-1"><span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#60745d]">Checkout an available asset</span><CustomSelect v-model="selectedAvailableId" class="w-full rounded-xl border-[#d8e7d4] text-sm"><option value="">Select an available asset</option><option v-for="asset in availableAssets" :key="asset.id" :value="asset.id">{{asset.asset_tag_no}} - {{asset.description}}</option></CustomSelect></label>
      <button type="button" class="rounded-xl bg-[#4f9f4a] px-5 py-3 text-sm font-bold text-white disabled:opacity-50" :disabled="!selectedAvailableId" @click="openAvailableAsset">Checkout to user</button>
    </div>
    <p v-if="!availableAssets.length" class="mt-2 text-xs text-[#7f9a7a]">No assets are currently available for checkout.</p>
  </div>

  <div v-if="charts" class="grid gap-6 xl:grid-cols-2">
    <article class="rounded-[1.7rem] border border-[#d8e7d4] bg-white p-6 shadow-[0_16px_38px_rgba(79,159,74,.08)]">
      <p class="text-xs font-bold uppercase tracking-[.2em] text-[#7f9a7a]">Asset allocation</p><h2 class="mt-1 text-xl font-bold text-[#234222]">Lifecycle status</h2>
      <div class="mt-6 grid items-center gap-6 sm:grid-cols-[13rem,1fr]">
        <div class="relative mx-auto h-48 w-48 rounded-full" :style="{background:pie}"><div class="absolute inset-8 flex flex-col items-center justify-center rounded-full bg-white"><span class="text-3xl font-bold text-[#234222]">{{total(charts.status)}}</span><span class="text-xs text-[#7f9a7a]">assets</span></div></div>
        <div class="space-y-3"><div v-for="(item,index) in charts.status" :key="item.label" class="flex items-center gap-3"><span class="h-3 w-3 rounded-full" :style="{backgroundColor:palette[index%palette.length]}"/><span class="flex-1 text-sm text-[#60745d]">{{item.label}}</span><strong class="text-sm text-[#234222]">{{item.value}} Â· {{percent(item.value,charts.status)}}%</strong></div></div>
      </div>
    </article>

    <article class="rounded-[1.7rem] border border-[#d8e7d4] bg-white p-6 shadow-[0_16px_38px_rgba(79,159,74,.08)]">
      <p class="text-xs font-bold uppercase tracking-[.2em] text-[#7f9a7a]">Inventory mix</p><h2 class="mt-1 text-xl font-bold text-[#234222]">Assets by category</h2>
      <div class="mt-6 space-y-4"><div v-for="item in charts.categories" :key="item.label"><div class="mb-1.5 flex justify-between text-sm"><span class="truncate pr-4 text-[#60745d]">{{item.label}}</span><strong class="text-[#234222]">{{item.value}}</strong></div><div class="h-3 overflow-hidden rounded-full bg-[#edf5ea]"><div class="h-full rounded-full bg-[linear-gradient(90deg,#2f7d32,#7abd70)]" :style="{width:`${(item.value/maximum(charts.categories))*100}%`}"/></div></div></div>
    </article>

    <article class="rounded-[1.7rem] border border-[#d8e7d4] bg-white p-6 shadow-[0_16px_38px_rgba(79,159,74,.08)]">
      <p class="text-xs font-bold uppercase tracking-[.2em] text-[#7f9a7a]">Deployment footprint</p><h2 class="mt-1 text-xl font-bold text-[#234222]">Assets by location</h2>
      <div class="mt-6 flex h-64 items-end gap-3 overflow-x-auto border-b border-[#d8e7d4] px-2 pb-0"><div v-for="(item,index) in charts.locations" :key="item.label" class="flex h-full min-w-[4.5rem] flex-1 flex-col justify-end text-center"><strong class="mb-2 text-sm text-[#234222]">{{item.value}}</strong><div class="mx-auto w-3/5 min-h-[.5rem] rounded-t-lg" :style="{height:`${(item.value/maximum(charts.locations))*75}%`,backgroundColor:palette[index%5]}"/><span class="mt-2 line-clamp-2 min-h-[2.5rem] text-[11px] leading-4 text-[#60745d]">{{item.label}}</span></div></div>
    </article>

    <article class="rounded-[1.7rem] border border-[#d8e7d4] bg-white p-6 shadow-[0_16px_38px_rgba(79,159,74,.08)]">
      <p class="text-xs font-bold uppercase tracking-[.2em] text-[#7f9a7a]">Lifecycle planning</p><h2 class="mt-1 text-xl font-bold text-[#234222]">Asset age profile</h2>
      <div class="mt-6 grid grid-cols-5 gap-3"><div v-for="(item,index) in charts.age" :key="item.label" class="text-center"><div class="flex h-44 items-end justify-center rounded-xl bg-[#f5faf3] p-2"><div class="w-full rounded-lg" :style="{height:`${Math.max((item.value/maximum(charts.age))*100,4)}%`,backgroundColor:palette[index%palette.length]}"/></div><strong class="mt-2 block text-lg text-[#234222]">{{item.value}}</strong><span class="text-[11px] leading-4 text-[#60745d]">{{item.label}}</span></div></div>
    </article>

    <article class="rounded-[1.7rem] border border-[#d8e7d4] bg-white p-6 shadow-[0_16px_38px_rgba(79,159,74,.08)]">
      <p class="text-xs font-bold uppercase tracking-[.2em] text-[#7f9a7a]">Health snapshot</p><h2 class="mt-1 text-xl font-bold text-[#234222]">Condition distribution</h2>
      <div class="mt-6 space-y-4"><div v-for="(item,index) in charts.conditions" :key="item.label" class="grid grid-cols-[7rem,1fr,3rem] items-center gap-3"><span class="truncate text-sm text-[#60745d]">{{item.label}}</span><div class="h-7 overflow-hidden rounded-lg bg-[#edf5ea]"><div class="flex h-full items-center justify-end rounded-lg pr-2 text-xs font-bold text-white" :style="{width:`${Math.max(percent(item.value,charts.conditions),8)}%`,backgroundColor:palette[index%palette.length]}">{{percent(item.value,charts.conditions)}}%</div></div><strong class="text-right text-[#234222]">{{item.value}}</strong></div></div>
    </article>

    <article class="rounded-[1.7rem] border border-[#d8e7d4] bg-white p-6 shadow-[0_16px_38px_rgba(79,159,74,.08)]">
      <p class="text-xs font-bold uppercase tracking-[.2em] text-[#7f9a7a]">Acquisition trend</p><h2 class="mt-1 text-xl font-bold text-[#234222]">Purchases by year</h2>
      <div v-if="charts.purchaseYears.length" class="mt-8 flex h-52 items-end gap-2 border-b border-l border-[#d8e7d4] px-3"><div v-for="item in charts.purchaseYears" :key="item.label" class="flex h-full min-w-[2.75rem] flex-1 flex-col justify-end text-center"><span class="mb-2 text-xs font-bold text-[#234222]">{{item.value}}</span><div class="mx-auto w-2/3 rounded-t-md bg-[#55a651]" :style="{height:`${Math.max((item.value/maximum(charts.purchaseYears))*85,5)}%`}"/><span class="mt-2 text-[11px] text-[#60745d]">{{item.label}}</span></div></div><div v-else class="mt-6 rounded-xl border border-dashed border-[#d8e7d4] p-12 text-center text-sm text-[#60745d]">Add purchase years to assets to populate this trend.</div>
    </article>
  </div>
  <div v-if="rowItems.length" class="overflow-hidden rounded-[1.5rem] border border-[#d8e7d4] bg-white">
    <div v-if="rows.total !== undefined" class="flex justify-between border-b border-[#edf3eb] px-5 py-3 text-sm text-[#60745d]"><span><strong class="text-[#234222]">{{rows.total}}</strong> assignments found</span><span>Showing {{rows.from}}â€“{{rows.to}}</span></div>
    <div class="overflow-x-auto"><table class="table"><thead><tr><th>Asset tag</th><th>Assigned to</th><th>Department</th><th v-if="filters">Category</th><th v-if="filters">Location</th><th v-if="filters">OS</th><th v-if="filters && canEdit">Actions</th></tr></thead><tbody><tr v-for="row in rowItems" :key="row.asset_tag"><td class="font-bold"><Link v-if="row.asset_id" class="text-[#2f7d32]" :href="route('it-assets.show',row.asset_id)">{{row.asset_tag}}</Link><template v-else>{{row.asset_tag}}</template></td><td>{{row.detail||'â€”'}}</td><td>{{row.meta||'â€”'}}</td><td v-if="filters">{{row.category||'â€”'}}</td><td v-if="filters">{{row.location||'â€”'}}</td><td v-if="filters">{{row.os||'â€”'}}</td><td v-if="filters && canEdit"><div class="flex gap-2"><button type="button" class="btn btn-xs border-[#cfe6c8] bg-white" @click="selectedAsset = row">Reassign</button><button type="button" class="btn btn-xs border-[#d9a74d] bg-[#fff8e8] text-[#805d17]" @click="checkIn(row)">Check in</button></div></td></tr></tbody></table></div>
  </div>
  <div v-if="!stats.length && !rowItems.length" class="rounded-[1.5rem] border border-dashed border-[#cfe6c8] bg-white p-12 text-center text-[#60745d]">{{filters ? 'No assignments match the selected filters.' : 'No records are available for this section yet.'}}</div>
  <div v-if="rows.links?.length" class="flex flex-wrap gap-2"><Link v-for="link in rows.links" :key="link.label" v-html="link.label" :href="link.url||'#'" class="btn btn-sm" :class="{'btn-disabled':!link.url,'btn-success text-white':link.active}" preserve-scroll /></div>
  <Link class="btn border-[#cfe6c8] bg-white" :href="route('it-assets.index')">Open IT Asset Register</Link>
  <AssetAssignmentModal v-if="selectedAsset" :asset="selectedAsset" :user-options="userOptions" @close="selectedAsset = null; selectedAvailableId = ''" />
</section></AuthenticatedLayout></template>
