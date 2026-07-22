<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CustomSelect from '@/Components/CustomSelect.vue';
import AssetAssignmentModal from '@/Components/AssetAssignmentModal.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
  assets: Object,
  categories: Array,
  selectedCategoryId: Number,
  locationOptions: Array,
  statusOptions: Array,
  departmentOptions: Array,
  assignedAssetsByDepartment: { type: Array, default: () => [] },
  osOptions: Array,
  filters: Object,
  userOptions: { type: Array, default: () => [] },
});

const page = usePage();
const canEdit = computed(() => page.props.auth?.user?.can?.it_assets_edit);
const selectedAsset = ref(null);
const form = reactive({ ...props.filters });
const activeFilters = computed(() => Object.values(form).filter((value) => value !== '' && value !== null).length);
const applyFilters = () => router.get(route('it-assets.index'), form, { preserveState: true, preserveScroll: true, replace: true });
const clearFilters = () => {
  Object.keys(form).forEach((key) => { form[key] = ''; });
  applyFilters();
};
const checkIn = (asset) => {
  if (!window.confirm(`Check in ${asset.asset_tag_no} from ${asset.assigned_to}?`)) return;
  router.patch(route('it-assets.check-in', asset.id), {}, { preserveScroll: true });
};

const statusStyles = {
  available: {
    badge: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    dot: 'bg-emerald-500 ring-emerald-100',
  },
  deployed: {
    badge: 'border-blue-200 bg-blue-50 text-blue-700',
    dot: 'bg-blue-500 ring-blue-100',
  },
  in_transit: {
    badge: 'border-violet-200 bg-violet-50 text-violet-700',
    dot: 'bg-violet-500 ring-violet-100',
  },
  under_repair: {
    badge: 'border-amber-200 bg-amber-50 text-amber-700',
    dot: 'bg-amber-500 ring-amber-100',
  },
  inspection_hold: {
    badge: 'border-orange-200 bg-orange-50 text-orange-700',
    dot: 'bg-orange-500 ring-orange-100',
  },
  damaged: {
    badge: 'border-red-200 bg-red-50 text-red-700',
    dot: 'bg-red-500 ring-red-100',
  },
  disposed: {
    badge: 'border-slate-200 bg-slate-100 text-slate-600',
    dot: 'bg-slate-400 ring-slate-200',
  },
};

const defaultStatusStyle = {
  badge: 'border-slate-200 bg-slate-50 text-slate-600',
  dot: 'bg-slate-400 ring-slate-100',
};
const statusStyle = (status) => statusStyles[status] ?? defaultStatusStyle;
const statusLabel = (status) => status.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
const chartStatuses = computed(() => {
  const preferredOrder = ['deployed', 'in_transit', 'under_repair', 'inspection_hold', 'damaged', 'available', 'disposed'];
  const present = new Set(props.assignedAssetsByDepartment.flatMap((row) => Object.keys(row.statuses)));
  return preferredOrder.filter((status) => present.has(status)).concat([...present].filter((status) => !preferredOrder.includes(status)));
});
const assignedAssetTotal = computed(() => props.assignedAssetsByDepartment.reduce((total, row) => total + row.total, 0));
const largestDepartmentTotal = computed(() => Math.max(1, ...props.assignedAssetsByDepartment.map((row) => row.total)));
const chartBarWidth = (count, total) => total > 0 ? `${(count / total) * 100}%` : '0%';
const statusBarClass = {
  available: 'bg-emerald-500',
  deployed: 'bg-blue-500',
  in_transit: 'bg-violet-500',
  under_repair: 'bg-amber-500',
  inspection_hold: 'bg-orange-500',
  damaged: 'bg-red-500',
  disposed: 'bg-slate-400',
};
const barClass = (status) => statusBarClass[status] ?? 'bg-slate-500';
</script>

<template>
  <Head title="IT Asset Register" />
  <AuthenticatedLayout>
    <section class="space-y-6">
      <header class="flex flex-wrap items-end justify-between gap-4 rounded-[2rem] border border-[#d8e7d4] bg-white p-7 shadow-sm">
        <div><p class="text-xs font-bold uppercase tracking-[.25em] text-[#4f9f4a]">KL IT Inventory</p><h1 class="mt-2 text-3xl font-bold text-[#234222]">IT Asset Register</h1><p class="mt-2 text-sm text-[#60745d]">Individually tagged equipment, assignments and lifecycle status.</p></div>
        <div v-if="canEdit" class="flex gap-2"><Link :href="route('it-assets.import.create')" class="rounded-full border border-[#4f9f4a] px-5 py-3 text-sm font-bold text-[#2f7d32]">Import legacy file</Link><Link :href="route('it-assets.create')" class="rounded-full bg-[#4f9f4a] px-5 py-3 text-sm font-bold text-white">Register asset</Link></div>
      </header>
      <section class="rounded-[1.7rem] border border-[#d8e7d4] bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div><p class="text-xs font-bold uppercase tracking-[.18em] text-[#4f9f4a]">Assignment overview</p><h2 class="mt-1 text-lg font-bold text-[#234222]">Assigned assets by department status</h2><p class="mt-1 text-xs text-[#7f9a7a]">Current assignments across the full IT asset register.</p></div>
          <div class="rounded-2xl bg-[#f2f8ef] px-4 py-3 text-right"><p class="text-2xl font-bold text-[#234222]">{{ assignedAssetTotal }}</p><p class="text-xs font-semibold text-[#60745d]">Assigned assets</p></div>
        </div>
        <div v-if="assignedAssetsByDepartment.length" class="mt-5 grid gap-6 xl:grid-cols-[minmax(0,1fr)_auto]">
          <div class="space-y-4">
            <div v-for="row in assignedAssetsByDepartment" :key="row.department" class="grid grid-cols-[9rem_minmax(0,1fr)_2.5rem] items-center gap-3">
              <div class="truncate text-sm font-semibold text-[#395337]" :title="row.department">{{ row.department }}</div>
              <div class="flex h-7 overflow-hidden rounded-lg bg-[#edf3eb]" :aria-label="`${row.department}: ${row.total} assigned assets`">
                <div v-for="status in chartStatuses" :key="status" :class="barClass(status)" :style="{ width: chartBarWidth(row.statuses[status] || 0, largestDepartmentTotal) }" :title="`${statusLabel(status)}: ${row.statuses[status] || 0}`"></div>
              </div>
              <div class="text-right text-sm font-bold text-[#234222]">{{ row.total }}</div>
            </div>
          </div>
          <div class="flex flex-wrap content-start gap-x-4 gap-y-2 xl:max-w-52 xl:flex-col">
            <div v-for="status in chartStatuses" :key="status" class="flex items-center gap-2 text-xs font-semibold text-[#60745d]"><span class="h-2.5 w-2.5 rounded-sm" :class="barClass(status)"></span>{{ statusLabel(status) }}</div>
          </div>
        </div>
        <div v-else class="mt-5 rounded-xl bg-[#f5faf3] px-4 py-8 text-center text-sm text-[#7f9a7a]">No assigned assets to chart yet.</div>
      </section>
      <form class="rounded-[1.7rem] border border-[#d8e7d4] bg-white p-5 shadow-sm" @submit.prevent="applyFilters">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div><div class="flex items-center gap-2"><h2 class="font-bold text-[#234222]">Filter assets</h2><span v-if="activeFilters" class="rounded-full bg-[#e8f5e4] px-2.5 py-1 text-xs font-bold text-[#2f7d32]">{{ activeFilters }} active</span></div><p class="mt-1 text-xs text-[#7f9a7a]">Search and narrow the full asset register.</p></div>
          <div class="flex gap-2"><button v-if="activeFilters" type="button" class="rounded-xl border border-[#d8e7d4] px-4 py-2 text-sm font-semibold text-[#60745d] hover:bg-[#f5faf3]" @click="clearFilters">Clear all</button><button type="submit" class="rounded-xl bg-[#4f9f4a] px-5 py-2 text-sm font-bold text-white hover:bg-[#3f8d3d]">Apply filters</button></div>
        </div>
        <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
          <label class="sm:col-span-2 xl:col-span-2"><span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#60745d]">Search</span><input v-model.trim="form.search" type="search" class="w-full rounded-xl border-[#d8e7d4] text-sm focus:border-[#4f9f4a] focus:ring-[#4f9f4a]" placeholder="Tag, serial, device or person" /></label>
          <label><span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#60745d]">Category</span><CustomSelect v-model="form.category" class="w-full rounded-xl border-[#d8e7d4] text-sm focus:border-[#4f9f4a] focus:ring-[#4f9f4a]"><option value="">All categories</option><option v-for="category in categories" :key="category.id" :value="String(category.id)">{{ category.name }}</option></CustomSelect></label>
          <label><span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#60745d]">Location</span><CustomSelect v-model="form.location" class="w-full rounded-xl border-[#d8e7d4] text-sm focus:border-[#4f9f4a] focus:ring-[#4f9f4a]"><option value="">All locations</option><option v-for="option in locationOptions" :key="option.value" :value="String(option.value)">{{ option.label }}</option></CustomSelect></label>
          <label><span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#60745d]">Status</span><CustomSelect v-model="form.status" class="w-full rounded-xl border-[#d8e7d4] text-sm focus:border-[#4f9f4a] focus:ring-[#4f9f4a]"><option value="">All statuses</option><option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option></CustomSelect></label>
          <label><span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#60745d]">Assignment</span><CustomSelect v-model="form.assignment" class="w-full rounded-xl border-[#d8e7d4] text-sm focus:border-[#4f9f4a] focus:ring-[#4f9f4a]"><option value="">All assets</option><option value="assigned">Assigned</option><option value="unassigned">Unassigned</option></CustomSelect></label>
          <label><span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#60745d]">Department</span><CustomSelect v-model="form.department" class="w-full rounded-xl border-[#d8e7d4] text-sm focus:border-[#4f9f4a] focus:ring-[#4f9f4a]"><option value="">All departments</option><option v-for="option in departmentOptions" :key="option" :value="option">{{ option }}</option></CustomSelect></label>
          <label><span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#60745d]">Operating system</span><CustomSelect v-model="form.os" class="w-full rounded-xl border-[#d8e7d4] text-sm focus:border-[#4f9f4a] focus:ring-[#4f9f4a]"><option value="">All operating systems</option><option v-for="option in osOptions" :key="option" :value="option">{{ option }}</option></CustomSelect></label>
        </div>
      </form>
      <div class="overflow-hidden rounded-[1.7rem] border border-[#d8e7d4] bg-white">
        <div class="flex items-center justify-between border-b border-[#edf3eb] px-5 py-3 text-sm text-[#60745d]"><span><strong class="text-[#234222]">{{ assets.total }}</strong> {{ assets.total === 1 ? 'asset' : 'assets' }} found</span><span v-if="assets.total">Showing {{ assets.from }}&ndash;{{ assets.to }}</span></div>
        <div class="overflow-x-auto"><table class="table">
          <thead><tr><th>Asset tag</th><th>Device</th><th>Serial</th><th>Assigned to</th><th>Department</th><th>OS</th><th>Status</th><th v-if="canEdit">Actions</th></tr></thead>
          <tbody><tr v-for="asset in assets.data" :key="asset.id">
            <td><Link class="font-bold text-[#2f7d32]" :href="route('it-assets.show', asset.id)">{{ asset.asset_tag_no }}</Link></td>
            <td>{{ asset.model || asset.description }}<div class="text-xs text-slate-500">{{ asset.category }}</div></td>
            <td>{{ asset.serial_no || '\u2014' }}</td><td>{{ asset.assigned_to || 'Unassigned' }}</td><td>{{ asset.department || '\u2014' }}</td><td>{{ asset.operating_system || '\u2014' }}</td>
            <td>
              <span
                class="inline-flex items-center gap-2 whitespace-nowrap rounded-full border px-3 py-1.5 text-xs font-bold shadow-sm"
                :class="statusStyle(asset.status).badge"
              >
                <span class="h-2 w-2 rounded-full ring-4" :class="statusStyle(asset.status).dot"></span>
                {{ statusLabel(asset.status) }}
              </span>
            </td>
            <td v-if="canEdit"><div class="flex flex-wrap gap-2">
              <Link class="btn btn-xs border-[#cfe6c8] bg-white" :href="route('it-assets.edit', asset.id)">Edit</Link>
              <Link class="btn btn-xs border-[#b8cde0] bg-[#f3f8fc] text-[#194568]" :href="route('it-assets.qr-code.show', asset.id)">{{ asset.has_qr_code ? 'QR code' : 'Generate QR' }}</Link>
              <button v-if="asset.is_assigned" type="button" class="btn btn-xs border-[#d9a74d] bg-[#fff8e8] text-[#805d17]" @click="checkIn(asset)">Check in</button>
              <button v-else-if="asset.status === 'available'" type="button" class="btn btn-xs bg-[#4f9f4a] text-white" @click="selectedAsset = asset">Checkout</button>
            </div></td>
          </tr><tr v-if="!assets.data.length"><td :colspan="canEdit ? 8 : 7" class="py-12 text-center text-slate-500">No IT assets match the selected filters.</td></tr></tbody>
        </table></div>
      </div>
      <div class="flex flex-wrap gap-2"><Link v-for="link in assets.links" :key="link.label" v-html="link.label" :href="link.url || '#'" class="btn btn-sm" :class="{ 'btn-disabled': !link.url, 'btn-success text-white': link.active }" /></div>
    </section>
    <AssetAssignmentModal v-if="selectedAsset" :asset="selectedAsset" :user-options="userOptions" @close="selectedAsset = null" />
  </AuthenticatedLayout>
</template>
