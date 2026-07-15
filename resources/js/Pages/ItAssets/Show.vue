<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'; import { Head, Link } from '@inertiajs/vue3'; defineProps({ asset:Object });
</script>
<template><Head :title="asset.asset_tag_no"/><AuthenticatedLayout><section class="space-y-6">
  <header class="rounded-[2rem] border border-[#d8e7d4] bg-white p-7"><p class="text-xs font-bold uppercase tracking-[.25em] text-[#4f9f4a]">IT Asset</p><h1 class="mt-2 text-3xl font-bold">{{ asset.asset_tag_no }}</h1><p>{{ asset.model || asset.description }}</p></header>
  <div class="grid gap-5 md:grid-cols-3"><div v-for="x in [['Serial',asset.serial_no],['Category',asset.category],['Status',asset.status],['Condition',asset.condition],['Location',asset.location],['Operating system',asset.operating_system],['Purchase year',asset.purchase_year],['Asset age',asset.age === null ? null : asset.age+' years'],['Checked out to',asset.assigned_to],['Department',asset.department]]" :key="x[0]" class="rounded-2xl border border-[#d8e7d4] bg-white p-5"><p class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ x[0] }}</p><p class="mt-2 font-semibold">{{ x[1] || '—' }}</p></div></div>
  <div class="rounded-[2rem] border border-[#d8e7d4] bg-white p-7"><h2 class="text-xl font-bold">Assignment history</h2><table class="table mt-4"><thead><tr><th>Employee</th><th>Department</th><th>Assigned</th><th>Returned</th></tr></thead><tbody><tr v-for="x in asset.assignments" :key="x.assigned_at+x.assigned_to_name"><td>{{x.assigned_to_name}}</td><td>{{x.department||'—'}}</td><td>{{x.assigned_at}}</td><td>{{x.returned_at||'Current'}}</td></tr></tbody></table></div>
  <Link class="btn" :href="route('it-assets.index')">Back to register</Link>
</section></AuthenticatedLayout></template>
