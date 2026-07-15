<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
defineProps({ assets: Object, categories: Array, selectedCategoryId: Number });
</script>

<template>
  <Head title="IT Asset Register" />
  <AuthenticatedLayout>
    <section class="space-y-6">
      <header class="flex flex-wrap items-end justify-between gap-4 rounded-[2rem] border border-[#d8e7d4] bg-white p-7 shadow-sm">
        <div><p class="text-xs font-bold uppercase tracking-[.25em] text-[#4f9f4a]">KL IT Inventory</p><h1 class="mt-2 text-3xl font-bold text-[#234222]">IT Asset Register</h1><p class="mt-2 text-sm text-[#60745d]">Individually tagged equipment, assignments and lifecycle status.</p></div>
        <div class="flex gap-2"><Link :href="route('it-assets.import.create')" class="rounded-full border border-[#4f9f4a] px-5 py-3 text-sm font-bold text-[#2f7d32]">Import legacy file</Link><Link :href="route('it-assets.create')" class="rounded-full bg-[#4f9f4a] px-5 py-3 text-sm font-bold text-white">Register asset</Link></div>
      </header>
      <div class="overflow-hidden rounded-[1.7rem] border border-[#d8e7d4] bg-white">
        <div class="overflow-x-auto"><table class="table">
          <thead><tr><th>Asset tag</th><th>Device</th><th>Serial</th><th>Assigned to</th><th>Department</th><th>OS</th><th>Status</th></tr></thead>
          <tbody><tr v-for="asset in assets.data" :key="asset.id">
            <td><Link class="font-bold text-[#2f7d32]" :href="route('it-assets.show', asset.id)">{{ asset.asset_tag_no }}</Link></td>
            <td>{{ asset.model || asset.description }}<div class="text-xs text-slate-500">{{ asset.category }}</div></td>
            <td>{{ asset.serial_no || '—' }}</td><td>{{ asset.assigned_to || 'Unassigned' }}</td><td>{{ asset.department || '—' }}</td><td>{{ asset.operating_system || '—' }}</td>
            <td><span class="badge badge-outline">{{ asset.status.replaceAll('_', ' ') }}</span></td>
          </tr><tr v-if="!assets.data.length"><td colspan="7" class="py-12 text-center text-slate-500">No IT assets in this branch.</td></tr></tbody>
        </table></div>
      </div>
      <div class="flex flex-wrap gap-2"><Link v-for="link in assets.links" :key="link.label" v-html="link.label" :href="link.url || '#'" class="btn btn-sm" :class="{ 'btn-disabled': !link.url, 'btn-success text-white': link.active }" /></div>
    </section>
  </AuthenticatedLayout>
</template>
