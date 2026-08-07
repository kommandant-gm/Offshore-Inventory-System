<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
  documents: { type: Array, default: () => [] },
  pending: { type: Array, default: () => [] },
  staffMovements: { type: Array, default: () => [] },
  summary: { type: Object, default: () => ({ total: 0, checkouts: 0, checkins: 0, pending: 0 }) },
});
const activeTab = ref('pending');
const deploymentRecords = computed(() => props.documents.filter((document) => document.type === 'checkout'));
const checkinRecords = computed(() => props.documents.filter((document) => document.type === 'checkin'));
const visibleRecords = computed(() => activeTab.value === 'deployment' ? deploymentRecords.value : checkinRecords.value);
const resend = (pending) => {
  if (!window.confirm(`Send a new checkout link to ${pending.email}?`)) return;
  router.post(pending.resend_url, {}, { preserveScroll: true });
};
</script>

<template>
  <Head title="Movement Record" />
  <AuthenticatedLayout>
    <section class="space-y-6">
      <header class="rounded-[2rem] border border-[#d8e7d4] bg-white p-7 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[.25em] text-[#4f9f4a]">KL IT Inventory</p>
        <h1 class="mt-2 text-3xl font-bold text-[#234222]">Movement Record</h1>
        <p class="mt-2 text-sm text-[#60745d]">Track pending signatures, asset deployments, and check-in PDF acknowledgments.</p>
      </header>

      <div class="grid gap-4 sm:grid-cols-3">
        <article v-for="card in [{ label: 'Pending checkout', value: summary.pending }, { label: 'Asset deployments', value: summary.checkouts }, { label: 'Asset check-ins', value: summary.checkins }]" :key="card.label" class="rounded-[1.5rem] border border-[#d8e7d4] bg-white p-5 shadow-sm">
          <p class="text-xs font-bold uppercase tracking-wider text-[#7f9a7a]">{{ card.label }}</p>
          <p class="mt-2 text-3xl font-black text-[#234222]">{{ card.value }}</p>
        </article>
      </div>

      <section class="overflow-hidden rounded-[1.7rem] border border-[#d8e7d4] bg-white shadow-sm">
        <div class="flex flex-wrap gap-2 border-b border-[#edf3eb] px-5 py-4">
          <button v-for="tab in [{ key: 'pending', label: 'Pending checkout', count: summary.pending }, { key: 'deployment', label: 'Asset deployment', count: summary.checkouts }, { key: 'checkin', label: 'Asset check-in', count: summary.checkins }, { key: 'staff', label: 'Staff asset movement', count: summary.staff }]" :key="tab.key" type="button" class="rounded-xl border px-4 py-2 text-xs font-bold" :class="activeTab === tab.key ? 'border-[#4f9f4a] bg-[#4f9f4a] text-white' : 'border-[#d8e7d4] bg-white text-[#60745d]'" @click="activeTab = tab.key">{{ tab.label }} <span class="ml-1 rounded-full px-2 py-0.5" :class="activeTab === tab.key ? 'bg-white/20' : 'bg-[#f1f3f5]'">{{ tab.count }}</span></button>
        </div>
        <div v-if="activeTab === 'pending'" class="overflow-x-auto">
          <table class="table"><thead><tr><th>Asset</th><th>Staff</th><th>Email</th><th>Sent</th><th></th></tr></thead><tbody>
            <tr v-for="item in pending" :key="item.asset_id" class="hover:bg-[#f7fbf5]"><td><strong>{{ item.asset_tag || '—' }}</strong><span class="block text-xs text-[#7f9a7a]">{{ item.description || 'No description' }}</span></td><td>{{ item.staff }}</td><td>{{ item.email }}</td><td>{{ item.sent_at }}</td><td class="text-right"><button type="button" class="btn btn-sm border-[#d9a74d] bg-[#fff8e8] text-[#805d17]" @click="resend(item)">Resend link</button></td></tr>
            <tr v-if="!pending.length"><td colspan="5" class="py-12 text-center text-[#7f9a7a]">No pending checkout forms.</td></tr>
          </tbody></table>
        </div>
        <div v-if="activeTab === 'staff'" class="bg-[#fbfefa] p-6">
          <div v-if="staffMovements.length" class="grid gap-8 xl:grid-cols-2">
            <article v-for="staff in staffMovements" :key="`${staff.name}-${staff.employee_id}`" class="relative rounded-[1.7rem] border border-[#d8e7d4] bg-white p-5 shadow-sm">
              <div class="flex items-center gap-4 rounded-2xl border border-[#cfe6c8] bg-[#f3f9f1] p-4">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-[#4f9f4a] text-lg font-black text-white">{{ staff.name?.split(/\s+/).filter(Boolean).map((part) => part[0]).slice(0, 2).join('').toUpperCase() }}</span>
                <div class="min-w-0"><h3 class="truncate font-bold text-[#234222]">{{ staff.name }}</h3><p class="text-xs text-[#60745d]">{{ staff.employee_id || 'No employee ID' }} · {{ staff.department || 'Department not specified' }}</p><p class="truncate text-xs text-[#7f9a7a]">{{ staff.email || 'No email recorded' }}</p></div>
              </div>
              <div class="ml-6 mt-4 border-l-2 border-[#cfe6c8] pl-6">
                <div v-for="asset in staff.assets" :key="`${asset.asset_tag}-${asset.assigned_at}`" class="relative mb-3 rounded-xl border border-[#edf3eb] bg-white p-3 shadow-sm last:mb-0"><span class="absolute -left-[2rem] top-5 h-2.5 w-2.5 rounded-full bg-[#4f9f4a]"></span><div class="flex flex-wrap items-center justify-between gap-2"><div><strong class="text-sm text-[#234222]">{{ asset.asset_tag || 'Unknown asset' }}</strong><p class="text-xs text-[#60745d]">{{ asset.description || 'No description' }}</p></div><span class="rounded-full px-2.5 py-1 text-[10px] font-bold" :class="asset.status === 'Deployed' ? 'bg-blue-50 text-blue-700' : asset.status === 'Returned' ? 'bg-slate-100 text-slate-600' : 'bg-amber-50 text-amber-700'">{{ asset.status }}</span></div><p class="mt-2 text-[11px] text-[#7f9a7a]">Assigned {{ asset.assigned_at || '—' }}<span v-if="asset.returned_at"> · Returned {{ asset.returned_at }}</span></p></div>
              </div>
            </article>
          </div>
          <div v-else class="py-12 text-center text-[#7f9a7a]">No staff asset movement has been recorded yet.</div>
        </div>
        <div v-if="['deployment', 'checkin'].includes(activeTab)" class="border-b border-[#edf3eb] px-5 py-4">
          <h2 class="font-bold text-[#234222]">{{ activeTab === 'deployment' ? 'Asset deployment records' : 'Asset check-in records' }}</h2>
          <p class="mt-1 text-xs text-[#7f9a7a]">Signed PDF documents are retained here for future reference.</p>
        </div>
        <div v-if="['deployment', 'checkin'].includes(activeTab)" class="overflow-x-auto">
          <table class="table">
            <thead><tr><th>Type</th><th>Asset</th><th>Staff</th><th>Generated</th><th>Document</th><th></th></tr></thead>
            <tbody>
              <tr v-for="document in visibleRecords" :key="document.id" class="hover:bg-[#f7fbf5]">
                <td><span class="rounded-full px-3 py-1 text-xs font-bold capitalize" :class="document.type === 'checkout' ? 'bg-blue-50 text-blue-700' : 'bg-emerald-50 text-emerald-700'">{{ document.type }}</span></td>
                <td><strong class="text-[#234222]">{{ document.asset_tag || '—' }}</strong><span class="block text-xs text-[#7f9a7a]">{{ document.description || 'No description' }}</span></td>
                <td>{{ document.staff || '—' }}</td>
                <td class="whitespace-nowrap text-[#60745d]">{{ document.generated_at }}</td>
                <td class="max-w-xs truncate text-xs text-[#7f9a7a]">{{ document.filename }}</td>
                <td class="text-right"><a :href="document.url" class="btn btn-sm border-[#cfe6c8] bg-white text-[#2f7d32]">Download PDF</a></td>
              </tr>
              <tr v-if="!visibleRecords.length"><td colspan="6" class="py-12 text-center text-[#7f9a7a]">No signed PDF records have been generated yet.</td></tr>
            </tbody>
          </table>
        </div>
      </section>
    </section>
  </AuthenticatedLayout>
</template>
