<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CustomSelect from '@/Components/CustomSelect.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({ licenses: Object, summary: Object, filters: Object, types: Array, departments: Array });
const page = usePage();
const canEdit = computed(() => page.props.auth?.user?.can?.it_assets_edit);
const form = reactive({ ...props.filters });
const activeFilters = computed(() => Object.values(form).filter((value) => value !== '' && value !== null).length);
const applyFilters = () => router.get(route('it-licenses.index'), form, { preserveState: true, preserveScroll: true, replace: true });
const clearFilters = () => {
  Object.keys(form).forEach((key) => { form[key] = ''; });
  applyFilters();
};
const label = (value) => value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
const statusStyles = {
  active: 'border-emerald-200 bg-emerald-50 text-emerald-700',
  expiring_soon: 'border-amber-200 bg-amber-50 text-amber-700',
  expired: 'border-red-200 bg-red-50 text-red-700',
  inactive: 'border-slate-200 bg-slate-100 text-slate-600',
};
const formatDate = (date) => date ? new Intl.DateTimeFormat('en-MY', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(`${date}T00:00:00`)) : 'No expiry';
</script>

<template>
  <Head title="IT Licence Register" />
  <AuthenticatedLayout>
    <section class="space-y-6">
      <header class="flex flex-wrap items-end justify-between gap-4 rounded-[2rem] border border-[#d8e7d4] bg-white p-7 shadow-sm">
        <div><p class="text-xs font-bold uppercase tracking-[.25em] text-[#4f9f4a]">KL IT Inventory</p><h1 class="mt-2 text-3xl font-bold text-[#234222]">IT Licence Register</h1><p class="mt-2 text-sm text-[#60745d]">Software licences, seat availability, ownership, and renewal dates.</p></div>
        <div v-if="canEdit" class="flex flex-wrap gap-2">
          <Link :href="route('it-licenses.import.create')" class="rounded-full border border-[#4f9f4a] px-5 py-3 text-sm font-bold text-[#2f7d32]">Import licences</Link>
          <Link :href="route('it-licenses.create')" class="rounded-full bg-[#4f9f4a] px-5 py-3 text-sm font-bold text-white">Register licence</Link>
        </div>
      </header>

      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <article v-for="card in [{label:'Total licences',value:summary.total},{label:'Active',value:summary.active},{label:'Expiring in 30 days',value:summary.expiring_soon},{label:'Expired',value:summary.expired},{label:'Users assigned',value:summary.users_assigned}]" :key="card.label" class="rounded-[1.5rem] border border-[#d8e7d4] bg-white p-5 shadow-sm">
          <p class="text-xs font-bold uppercase tracking-wider text-[#7f9a7a]">{{ card.label }}</p><p class="mt-2 text-3xl font-black text-[#234222]">{{ card.value }}</p>
        </article>
      </div>

      <form class="rounded-[1.7rem] border border-[#d8e7d4] bg-white p-5 shadow-sm" @submit.prevent="applyFilters">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"><div><div class="flex items-center gap-2"><h2 class="font-bold text-[#234222]">Filter licences</h2><span v-if="activeFilters" class="rounded-full bg-[#e8f5e4] px-2.5 py-1 text-xs font-bold text-[#2f7d32]">{{ activeFilters }} active</span></div><p class="mt-1 text-xs text-[#7f9a7a]">Search products, vendors, owners, and licence IDs.</p></div><div class="flex gap-2"><button v-if="activeFilters" type="button" class="rounded-xl border border-[#d8e7d4] px-4 py-2 text-sm font-semibold text-[#60745d]" @click="clearFilters">Clear all</button><button class="rounded-xl bg-[#4f9f4a] px-5 py-2 text-sm font-bold text-white">Apply filters</button></div></div>
        <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <label><span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#60745d]">Search</span><input v-model.trim="form.search" type="search" class="w-full rounded-xl border-[#d8e7d4] text-sm focus:border-[#4f9f4a] focus:ring-[#4f9f4a]" placeholder="Licence ID, product, vendor or owner" /></label>
          <label><span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#60745d]">Type</span><CustomSelect v-model="form.type" class="w-full rounded-xl border-[#d8e7d4] text-sm"><option value="">All types</option><option v-for="type in types" :key="type.value" :value="type.value">{{ type.label }}</option></CustomSelect></label>
          <label><span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#60745d]">Status</span><CustomSelect v-model="form.status" class="w-full rounded-xl border-[#d8e7d4] text-sm"><option value="">All statuses</option><option value="active">Active</option><option value="expiring_soon">Expiring soon</option><option value="expired">Expired</option><option value="inactive">Inactive</option></CustomSelect></label>
          <label><span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#60745d]">Department</span><CustomSelect v-model="form.department" class="w-full rounded-xl border-[#d8e7d4] text-sm"><option value="">All departments</option><option v-for="department in departments" :key="department" :value="department">{{ department }}</option></CustomSelect></label>
        </div>
      </form>

      <div class="overflow-hidden rounded-[1.7rem] border border-[#d8e7d4] bg-white">
        <div class="flex items-center justify-between border-b border-[#edf3eb] px-5 py-3 text-sm text-[#60745d]"><span><strong class="text-[#234222]">{{ licenses.total }}</strong> {{ licenses.total === 1 ? 'licence' : 'licences' }} found</span><span v-if="licenses.total">Showing {{ licenses.from }}&ndash;{{ licenses.to }}</span></div>
        <div class="overflow-x-auto"><table class="table">
          <thead><tr><th>Licence ID</th><th>Software</th><th>Type</th><th>Checked Out To</th><th>Expiry</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <tr v-for="license in licenses.data" :key="license.id">
              <td><Link class="font-bold text-[#2f7d32]" :href="route('it-licenses.show', license.id)">{{ license.license_code }}</Link></td>
              <td><p class="font-semibold text-[#234222]">{{ license.software_name }}</p><p class="text-xs text-slate-500">{{ license.vendor || 'Vendor not specified' }}</p></td>
              <td>{{ label(license.license_type) }}</td>
              <td><p v-if="license.assigned_to" class="max-w-64 font-semibold text-[#234222]">{{ license.assigned_to }}</p><span v-else class="text-sm text-slate-500">{{ license.seats_assigned ? 'Assignee not recorded' : 'Available / Unassigned' }}</span></td>
              <td>{{ formatDate(license.expiry_date) }}</td>
              <td><span class="inline-flex whitespace-nowrap rounded-full border px-3 py-1.5 text-xs font-bold" :class="statusStyles[license.status]">{{ label(license.status) }}</span></td>
              <td><div class="flex gap-2"><Link class="btn btn-xs border-[#b8cde0] bg-[#f3f8fc] text-[#194568]" :href="route('it-licenses.show', license.id)">View</Link><Link v-if="canEdit" class="btn btn-xs border-[#cfe6c8] bg-white" :href="route('it-licenses.edit', license.id)">Edit</Link></div></td>
            </tr>
            <tr v-if="!licenses.data.length"><td colspan="7" class="py-12 text-center text-slate-500">No IT licences match the selected filters.</td></tr>
          </tbody>
        </table></div>
      </div>
      <div class="flex flex-wrap gap-2"><Link v-for="link in licenses.links" :key="link.label" v-html="link.label" :href="link.url || '#'" class="btn btn-sm" :class="{ 'btn-disabled': !link.url, 'btn-success text-white': link.active }" /></div>
    </section>
  </AuthenticatedLayout>
</template>
