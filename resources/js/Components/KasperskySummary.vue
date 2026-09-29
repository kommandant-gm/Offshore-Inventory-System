<script setup>
import { Link } from '@inertiajs/vue3';
defineProps({ overview: Object });
</script>

<template>
  <section class="rounded-[1.7rem] border border-[#d8e7d4] bg-white p-6 shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div><p class="text-xs font-bold uppercase tracking-widest text-[#4f9f4a]">Endpoint protection</p><h2 class="mt-1 text-xl font-bold text-[#234222]">Kaspersky licences</h2></div>
      <Link :href="route('kaspersky-licenses.index')" class="text-sm font-bold text-[#2f7d32] hover:underline">Open Kaspersky register →</Link>
    </div>
    <div class="mt-5 grid grid-cols-3 gap-4">
      <div v-for="item in [{label:'Total licences',value:overview.total},{label:'Assigned',value:overview.assigned},{label:'Available',value:overview.available}]" :key="item.label" class="rounded-xl bg-[#f4f9f2] p-4">
        <p class="text-xs text-[#60745d]">{{ item.label }}</p><p class="mt-1 text-2xl font-bold text-[#234222]">{{ item.value }}</p>
      </div>
    </div>
    <p class="mt-4 text-xs text-[#60745d]">{{ overview.updated_at ? `Last uploaded ${new Date(overview.updated_at).toLocaleString('en-MY')} · ${overview.filename}` : 'Upload a Kaspersky spreadsheet to see licence availability.' }}</p>
  </section>
</template>
