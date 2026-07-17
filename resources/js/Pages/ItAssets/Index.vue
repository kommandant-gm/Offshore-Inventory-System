<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
  assets: Object,
  categories: Array,
  selectedCategoryId: Number,
  locationOptions: Array,
  statusOptions: Array,
  departmentOptions: Array,
  osOptions: Array,
  filters: Object,
});

const form = reactive({ ...props.filters });
const activeFilters = computed(() => Object.values(form).filter((value) => value !== '' && value !== null).length);
const applyFilters = () => router.get(route('it-assets.index'), form, { preserveState: true, preserveScroll: true, replace: true });
const clearFilters = () => {
  Object.keys(form).forEach((key) => { form[key] = ''; });
  applyFilters();
};
</script>

<template>
  <Head title="IT Asset Register" />
  <AuthenticatedLayout>
    <section class="space-y-6">
      <header class="flex flex-wrap items-end justify-between gap-4 rounded-[2rem] border border-[#d8e7d4] bg-white p-7 shadow-sm">
        <div><p class="text-xs font-bold uppercase tracking-[.25em] text-[#4f9f4a]">KL IT Inventory</p><h1 class="mt-2 text-3xl font-bold text-[#234222]">IT Asset Register</h1><p class="mt-2 text-sm text-[#60745d]">Individually tagged equipment, assignments and lifecycle status.</p></div>
        <div class="flex gap-2"><Link :href="route('it-assets.import.create')" class="rounded-full border border-[#4f9f4a] px-5 py-3 text-sm font-bold text-[#2f7d32]">Import legacy file</Link><Link :href="route('it-assets.create')" class="rounded-full bg-[#4f9f4a] px-5 py-3 text-sm font-bold text-white">Register asset</Link></div>
      </header>
      <form class="rounded-[1.7rem] border border-[#d8e7d4] bg-white p-5 shadow-sm" @submit.prevent="applyFilters">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div><div class="flex items-center gap-2"><h2 class="font-bold text-[#234222]">Filter assets</h2><span v-if="activeFilters" class="rounded-full bg-[#e8f5e4] px-2.5 py-1 text-xs font-bold text-[#2f7d32]">{{ activeFilters }} active</span></div><p class="mt-1 text-xs text-[#7f9a7a]">Search and narrow the full asset register.</p></div>
          <div class="flex gap-2"><button v-if="activeFilters" type="button" class="rounded-xl border border-[#d8e7d4] px-4 py-2 text-sm font-semibold text-[#60745d] hover:bg-[#f5faf3]" @click="clearFilters">Clear all</button><button type="submit" class="rounded-xl bg-[#4f9f4a] px-5 py-2 text-sm font-bold text-white hover:bg-[#3f8d3d]">Apply filters</button></div>
        </div>
        <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
          <label class="sm:col-span-2 xl:col-span-2"><span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#60745d]">Search</span><input v-model.trim="form.search" type="search" class="w-full rounded-xl border-[#d8e7d4] text-sm focus:border-[#4f9f4a] focus:ring-[#4f9f4a]" placeholder="Tag, serial, device or person" /></label>
          <label><span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#60745d]">Category</span><select v-model="form.category" class="w-full rounded-xl border-[#d8e7d4] text-sm focus:border-[#4f9f4a] focus:ring-[#4f9f4a]"><option value="">All categories</option><option v-for="category in categories" :key="category.id" :value="String(category.id)">{{ category.name }}</option></select></label>
          <label><span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#60745d]">Location</span><select v-model="form.location" class="w-full rounded-xl border-[#d8e7d4] text-sm focus:border-[#4f9f4a] focus:ring-[#4f9f4a]"><option value="">All locations</option><option v-for="option in locationOptions" :key="option.value" :value="String(option.value)">{{ option.label }}</option></select></label>
          <label><span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#60745d]">Status</span><select v-model="form.status" class="w-full rounded-xl border-[#d8e7d4] text-sm focus:border-[#4f9f4a] focus:ring-[#4f9f4a]"><option value="">All statuses</option><option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select></label>
          <label><span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#60745d]">Assignment</span><select v-model="form.assignment" class="w-full rounded-xl border-[#d8e7d4] text-sm focus:border-[#4f9f4a] focus:ring-[#4f9f4a]"><option value="">All assets</option><option value="assigned">Assigned</option><option value="unassigned">Unassigned</option></select></label>
          <label><span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#60745d]">Department</span><select v-model="form.department" class="w-full rounded-xl border-[#d8e7d4] text-sm focus:border-[#4f9f4a] focus:ring-[#4f9f4a]"><option value="">All departments</option><option v-for="option in departmentOptions" :key="option" :value="option">{{ option }}</option></select></label>
          <label><span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#60745d]">Operating system</span><select v-model="form.os" class="w-full rounded-xl border-[#d8e7d4] text-sm focus:border-[#4f9f4a] focus:ring-[#4f9f4a]"><option value="">All operating systems</option><option v-for="option in osOptions" :key="option" :value="option">{{ option }}</option></select></label>
        </div>
      </form>
      <div class="overflow-hidden rounded-[1.7rem] border border-[#d8e7d4] bg-white">
        <div class="flex items-center justify-between border-b border-[#edf3eb] px-5 py-3 text-sm text-[#60745d]"><span><strong class="text-[#234222]">{{ assets.total }}</strong> {{ assets.total === 1 ? 'asset' : 'assets' }} found</span><span v-if="assets.total">Showing {{ assets.from }}–{{ assets.to }}</span></div>
        <div class="overflow-x-auto"><table class="table">
          <thead><tr><th>Asset tag</th><th>Device</th><th>Serial</th><th>Assigned to</th><th>Department</th><th>OS</th><th>Status</th></tr></thead>
          <tbody><tr v-for="asset in assets.data" :key="asset.id">
            <td><Link class="font-bold text-[#2f7d32]" :href="route('it-assets.show', asset.id)">{{ asset.asset_tag_no }}</Link></td>
            <td>{{ asset.model || asset.description }}<div class="text-xs text-slate-500">{{ asset.category }}</div></td>
            <td>{{ asset.serial_no || '—' }}</td><td>{{ asset.assigned_to || 'Unassigned' }}</td><td>{{ asset.department || '—' }}</td><td>{{ asset.operating_system || '—' }}</td>
            <td><span class="badge badge-outline">{{ asset.status.replaceAll('_', ' ') }}</span></td>
          </tr><tr v-if="!assets.data.length"><td colspan="7" class="py-12 text-center text-slate-500">No IT assets match the selected filters.</td></tr></tbody>
        </table></div>
      </div>
      <div class="flex flex-wrap gap-2"><Link v-for="link in assets.links" :key="link.label" v-html="link.label" :href="link.url || '#'" class="btn btn-sm" :class="{ 'btn-disabled': !link.url, 'btn-success text-white': link.active }" /></div>
    </section>
  </AuthenticatedLayout>
</template>
