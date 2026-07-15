<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'; import { Head, Link } from '@inertiajs/vue3';
defineProps({ title:String, description:String, stats:{type:Array,default:()=>[]}, rows:{type:Array,default:()=>[]} });
</script>
<template><Head :title="title"/><AuthenticatedLayout><section class="space-y-6">
  <header class="rounded-[2rem] border border-[#d8e7d4] bg-white p-7"><p class="text-xs font-bold uppercase tracking-[.25em] text-[#4f9f4a]">KL IT Inventory</p><h1 class="mt-2 text-3xl font-bold text-[#234222]">{{title}}</h1><p class="mt-2 text-sm text-[#60745d]">{{description}}</p></header>
  <div v-if="stats.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><div v-for="stat in stats" :key="stat.label" class="rounded-[1.5rem] border border-[#d8e7d4] bg-white p-6"><p class="text-xs font-bold uppercase tracking-wider text-[#7f9a7a]">{{stat.label}}</p><p class="mt-3 text-3xl font-bold text-[#2f7d32]">{{stat.value}}</p></div></div>
  <div v-if="rows.length" class="overflow-hidden rounded-[1.5rem] border border-[#d8e7d4] bg-white"><table class="table"><thead><tr><th>Asset tag</th><th>Details</th><th>Status / Department</th></tr></thead><tbody><tr v-for="row in rows" :key="row.asset_tag"><td class="font-bold">{{row.asset_tag}}</td><td>{{row.detail||'—'}}</td><td>{{row.meta||'—'}}</td></tr></tbody></table></div>
  <div v-if="!stats.length && !rows.length" class="rounded-[1.5rem] border border-dashed border-[#cfe6c8] bg-white p-12 text-center text-[#60745d]">No records are available for this section yet.</div>
  <Link class="btn border-[#cfe6c8] bg-white" :href="route('it-assets.index')">Open IT Asset Register</Link>
</section></AuthenticatedLayout></template>
