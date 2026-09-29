<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import KasperskySummary from '@/Components/KasperskySummary.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ overview: Object, history: Array });
const page = usePage();
const canEdit = computed(() => page.props.auth?.user?.can?.it_assets_edit);
const upload = useForm({ file: null });
const fileInput = ref(null);
const search = ref('');
const status = ref('');
const rows = computed(() => props.overview.rows.filter(row => {
  const matches = `${row.no} ${row.licence} ${row.device} ${row.group}`.toLowerCase().includes(search.value.toLowerCase());
  return matches && (!status.value || (status.value === 'assigned' ? !!row.device : !row.device));
}));
const submit = () => upload.post(route('kaspersky-licenses.import'), {
  preserveScroll: true,
  onSuccess: () => { upload.reset(); if (fileInput.value) fileInput.value.value = ''; },
});
</script>

<template>
  <Head title="Kaspersky Licences" />
  <AuthenticatedLayout>
    <section class="space-y-6">
      <header class="flex flex-wrap items-center justify-between gap-4 rounded-[2rem] border border-[#d8e7d4] bg-white p-7 shadow-sm">
        <div><p class="text-xs font-bold uppercase tracking-[.25em] text-[#4f9f4a]">KL IT Inventory</p><h1 class="mt-2 text-3xl font-bold text-[#234222]">Kaspersky Licences</h1><p class="mt-2 text-sm text-[#60745d]">A licence is assigned when a device is listed. Empty device slots are available.</p></div>
        <Link :href="route('it-licenses.index')" class="rounded-full border border-[#4f9f4a] px-5 py-3 text-sm font-bold text-[#2f7d32]">Back to IT licences</Link>
      </header>
      <KasperskySummary :overview="overview" />
      <form v-if="canEdit" @submit.prevent="submit" class="space-y-4 rounded-[1.7rem] border border-[#d8e7d4] bg-white p-6">
        <div><h2 class="text-lg font-bold text-[#234222]">Monthly licence update</h2><p class="mt-1 text-sm text-[#60745d]">Upload the complete CSV or Excel (.xlsx) file with No, Licence and Device columns. The latest upload replaces the current view; previous snapshots are retained. Dates in the file are ignored.</p></div>
        <label class="block text-sm font-semibold text-[#234222]">Licence spreadsheet<input ref="fileInput" type="file" accept=".csv,.xlsx" class="mt-2 block w-full rounded-lg border border-[#d8e7d4] p-2" :disabled="upload.processing" @change="upload.file = $event.target.files[0] ?? null" /></label>
        <p v-if="upload.errors.file" role="alert" class="text-sm text-red-700">{{ upload.errors.file }}</p>
        <p v-if="upload.progress" class="text-sm text-[#60745d]">Uploading {{ upload.progress.percentage }}%</p>
        <button :disabled="!upload.file || upload.processing" class="rounded-full bg-[#4f9f4a] px-5 py-3 text-sm font-bold text-white disabled:opacity-50">{{ upload.processing ? 'Updating…' : 'Upload monthly update' }}</button>
      </form>
      <section class="overflow-hidden rounded-[1.7rem] border border-[#d8e7d4] bg-white">
        <div class="flex flex-wrap items-end gap-4 border-b border-[#edf3eb] p-5">
          <label class="min-w-48 flex-1 text-sm font-semibold text-[#234222]">Search<input v-model="search" type="search" placeholder="Device, licence or group" class="mt-1 block w-full rounded-xl border-[#d8e7d4] text-sm" /></label>
          <label class="text-sm font-semibold text-[#234222]">Assignment<select v-model="status" class="mt-1 block rounded-xl border-[#d8e7d4] text-sm"><option value="">All licences</option><option value="assigned">Assigned</option><option value="available">Available</option></select></label>
          <span class="py-2 text-sm text-[#60745d]">{{ rows.length }} licences</span>
        </div>
        <div class="max-h-[640px] overflow-auto"><table class="table"><thead class="sticky top-0 bg-white"><tr><th>No</th><th>Licence</th><th>Device</th><th>Group</th><th>Assignment</th></tr></thead><tbody>
          <tr v-for="row in rows" :key="row.no"><td>{{ row.no }}</td><td>{{ row.licence }}</td><td class="font-semibold">{{ row.device || '—' }}</td><td>{{ row.group || '—' }}</td><td><span class="rounded-full px-3 py-1 text-xs font-bold" :class="row.device ? 'bg-blue-50 text-blue-700' : 'bg-emerald-50 text-emerald-700'">{{ row.device ? 'Assigned' : 'Available' }}</span></td></tr>
          <tr v-if="!rows.length"><td colspan="5" class="py-10 text-center text-[#60745d]">{{ overview.total ? 'No licences match these filters.' : 'No Kaspersky licences uploaded yet.' }}</td></tr>
        </tbody></table></div>
      </section>
      <details v-if="history.length" class="rounded-[1.7rem] border border-[#d8e7d4] bg-white p-6"><summary class="cursor-pointer font-bold text-[#234222]">Recent uploads</summary><ul class="mt-3 space-y-2 text-sm text-[#60745d]"><li v-for="item in history" :key="item.id">{{ new Date(item.created_at).toLocaleString('en-MY') }} · {{ item.filename }}</li></ul></details>
    </section>
  </AuthenticatedLayout>
</template>
