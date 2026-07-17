<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
  title: String,
  description: String,
  stats: { type: Array, default: () => [] },
  rows: { type: Array, default: () => [] },
  charts: { type: Object, default: null },
});

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

  <div v-if="charts" class="grid gap-6 xl:grid-cols-2">
    <article class="rounded-[1.7rem] border border-[#d8e7d4] bg-white p-6 shadow-[0_16px_38px_rgba(79,159,74,.08)]">
      <p class="text-xs font-bold uppercase tracking-[.2em] text-[#7f9a7a]">Asset allocation</p><h2 class="mt-1 text-xl font-bold text-[#234222]">Lifecycle status</h2>
      <div class="mt-6 grid items-center gap-6 sm:grid-cols-[13rem,1fr]">
        <div class="relative mx-auto h-48 w-48 rounded-full" :style="{background:pie}"><div class="absolute inset-8 flex flex-col items-center justify-center rounded-full bg-white"><span class="text-3xl font-bold text-[#234222]">{{total(charts.status)}}</span><span class="text-xs text-[#7f9a7a]">assets</span></div></div>
        <div class="space-y-3"><div v-for="(item,index) in charts.status" :key="item.label" class="flex items-center gap-3"><span class="h-3 w-3 rounded-full" :style="{backgroundColor:palette[index%palette.length]}"/><span class="flex-1 text-sm text-[#60745d]">{{item.label}}</span><strong class="text-sm text-[#234222]">{{item.value}} · {{percent(item.value,charts.status)}}%</strong></div></div>
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
  <div v-if="rows.length" class="overflow-hidden rounded-[1.5rem] border border-[#d8e7d4] bg-white"><table class="table"><thead><tr><th>Asset tag</th><th>Details</th><th>Status / Department</th></tr></thead><tbody><tr v-for="row in rows" :key="row.asset_tag"><td class="font-bold">{{row.asset_tag}}</td><td>{{row.detail||'—'}}</td><td>{{row.meta||'—'}}</td></tr></tbody></table></div>
  <div v-if="!stats.length && !rows.length" class="rounded-[1.5rem] border border-dashed border-[#cfe6c8] bg-white p-12 text-center text-[#60745d]">No records are available for this section yet.</div>
  <Link class="btn border-[#cfe6c8] bg-white" :href="route('it-assets.index')">Open IT Asset Register</Link>
</section></AuthenticatedLayout></template>
