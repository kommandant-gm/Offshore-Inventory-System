<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
  documents: { type: Array, default: () => [] },
  summary: { type: Object, default: () => ({ total: 0, checkouts: 0, checkins: 0 }) },
});
</script>

<template>
  <Head title="Movement Record" />
  <AuthenticatedLayout>
    <section class="space-y-6">
      <header class="rounded-[2rem] border border-[#d8e7d4] bg-white p-7 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[.25em] text-[#4f9f4a]">KL IT Inventory</p>
        <h1 class="mt-2 text-3xl font-bold text-[#234222]">Movement Record</h1>
        <p class="mt-2 text-sm text-[#60745d]">Completed checkout and check-in PDF acknowledgments for IT assets.</p>
      </header>

      <div class="grid gap-4 sm:grid-cols-3">
        <article v-for="card in [{ label: 'All records', value: summary.total }, { label: 'Checkouts', value: summary.checkouts }, { label: 'Check-ins', value: summary.checkins }]" :key="card.label" class="rounded-[1.5rem] border border-[#d8e7d4] bg-white p-5 shadow-sm">
          <p class="text-xs font-bold uppercase tracking-wider text-[#7f9a7a]">{{ card.label }}</p>
          <p class="mt-2 text-3xl font-black text-[#234222]">{{ card.value }}</p>
        </article>
      </div>

      <section class="overflow-hidden rounded-[1.7rem] border border-[#d8e7d4] bg-white shadow-sm">
        <div class="border-b border-[#edf3eb] px-5 py-4">
          <h2 class="font-bold text-[#234222]">Generated PDF records</h2>
          <p class="mt-1 text-xs text-[#7f9a7a]">Signed documents are retained here for future reference.</p>
        </div>
        <div class="overflow-x-auto">
          <table class="table">
            <thead><tr><th>Type</th><th>Asset</th><th>Staff</th><th>Generated</th><th>Document</th><th></th></tr></thead>
            <tbody>
              <tr v-for="document in documents" :key="document.id" class="hover:bg-[#f7fbf5]">
                <td><span class="rounded-full px-3 py-1 text-xs font-bold capitalize" :class="document.type === 'checkout' ? 'bg-blue-50 text-blue-700' : 'bg-emerald-50 text-emerald-700'">{{ document.type }}</span></td>
                <td><strong class="text-[#234222]">{{ document.asset_tag || '—' }}</strong><span class="block text-xs text-[#7f9a7a]">{{ document.description || 'No description' }}</span></td>
                <td>{{ document.staff || '—' }}</td>
                <td class="whitespace-nowrap text-[#60745d]">{{ document.generated_at }}</td>
                <td class="max-w-xs truncate text-xs text-[#7f9a7a]">{{ document.filename }}</td>
                <td class="text-right"><a :href="document.url" class="btn btn-sm border-[#cfe6c8] bg-white text-[#2f7d32]">Download PDF</a></td>
              </tr>
              <tr v-if="!documents.length"><td colspan="6" class="py-12 text-center text-[#7f9a7a]">No signed movement PDFs have been generated yet.</td></tr>
            </tbody>
          </table>
        </div>
      </section>
    </section>
  </AuthenticatedLayout>
</template>
