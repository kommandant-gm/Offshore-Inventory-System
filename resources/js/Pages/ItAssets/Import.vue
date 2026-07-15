<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'; import InputError from '@/Components/InputError.vue'; import { Head, Link, useForm } from '@inertiajs/vue3';
defineProps({ report:Object }); const preview=useForm({file:null}); const confirm=useForm({});
</script>
<template><Head title="Import IT Assets"/><AuthenticatedLayout><section class="space-y-6">
  <header class="rounded-[2rem] border border-[#d8e7d4] bg-white p-7"><p class="text-xs font-bold uppercase tracking-[.25em] text-[#4f9f4a]">Safe migration</p><h1 class="mt-2 text-3xl font-bold">Import legacy KL IT assets</h1><p class="mt-2 text-sm text-slate-600">Upload CSV or XLSX. Preview validation runs before any asset is created.</p></header>
  <form class="rounded-[2rem] border border-[#d8e7d4] bg-white p-7" @submit.prevent="preview.post(route('it-assets.import.preview'))"><input class="file-input file-input-bordered w-full" type="file" accept=".csv,.xlsx" @change="preview.file=$event.target.files[0]"/><InputError :message="preview.errors.file"/><button class="btn mt-4 bg-[#4f9f4a] text-white" :disabled="preview.processing">Run dry-run preview</button></form>
  <section v-if="report" class="rounded-[2rem] border border-[#d8e7d4] bg-white p-7"><div class="grid gap-4 md:grid-cols-4"><div v-for="x in [['Rows',report.total],['Ready',report.ready],['Warnings',report.warning_count],['Rejected',report.rejected_count]]" :key="x[0]" class="rounded-xl bg-slate-50 p-4"><p class="text-xs uppercase text-slate-500">{{x[0]}}</p><p class="text-2xl font-bold">{{x[1]}}</p></div></div>
    <div v-if="report.rejected?.length" class="mt-5"><h2 class="font-bold text-red-700">Rejected rows</h2><ul class="mt-2 list-disc pl-5 text-sm"><li v-for="x in report.rejected" :key="x">{{x}}</li></ul></div>
    <div v-if="report.warnings?.length" class="mt-5"><h2 class="font-bold text-amber-700">Warnings</h2><ul class="mt-2 list-disc pl-5 text-sm"><li v-for="x in report.warnings" :key="x">{{x}}</li></ul></div>
    <form class="mt-6" @submit.prevent="confirm.post(route('it-assets.import.store'))"><button class="btn bg-[#4f9f4a] text-white" :disabled="confirm.processing || !report.ready">Import {{report.ready}} validated assets</button></form>
  </section><Link class="btn" :href="route('it-assets.index')">Cancel</Link>
</section></AuthenticatedLayout></template>
