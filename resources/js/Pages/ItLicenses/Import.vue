<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({ report: Object });

const preview = useForm({ file: null });
const confirm = useForm({});
</script>

<template>
  <Head title="Import IT Licences" />
  <AuthenticatedLayout>
    <section class="space-y-6">
      <header class="rounded-[2rem] border border-[#d8e7d4] bg-white p-7">
        <p class="text-xs font-bold uppercase tracking-[.25em] text-[#4f9f4a]">Safe migration</p>
        <h1 class="mt-2 text-3xl font-bold text-[#234222]">Import IT licences</h1>
        <p class="mt-2 text-sm text-slate-600">Upload the software licence CSV or XLSX file. Validation runs before any licence is created.</p>
      </header>

      <form class="rounded-[2rem] border border-[#d8e7d4] bg-white p-7" @submit.prevent="preview.post(route('it-licenses.import.preview'))">
        <label class="block">
          <span class="mb-2 block text-xs font-bold uppercase tracking-wider text-[#60745d]">Licence spreadsheet</span>
          <input class="file-input file-input-bordered w-full" type="file" accept=".csv,.xlsx" @change="preview.file = $event.target.files[0]" />
        </label>
        <InputError :message="preview.errors.file" />
        <button class="btn mt-4 bg-[#4f9f4a] text-white" :disabled="preview.processing || !preview.file">
          {{ preview.processing ? 'Checking file...' : 'Run dry-run preview' }}
        </button>
      </form>

      <section v-if="report" class="rounded-[2rem] border border-[#d8e7d4] bg-white p-7">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
          <div v-for="item in [['Rows', report.total], ['Ready', report.ready], ['Assigned', report.assigned], ['Available', report.available], ['Warnings', report.warning_count], ['Rejected', report.rejected_count]]" :key="item[0]" class="rounded-xl bg-slate-50 p-4">
            <p class="text-xs uppercase text-slate-500">{{ item[0] }}</p>
            <p class="text-2xl font-bold text-[#234222]">{{ item[1] }}</p>
          </div>
        </div>

        <div v-if="report.rejected?.length" class="mt-5">
          <h2 class="font-bold text-red-700">Rejected rows</h2>
          <ul class="mt-2 list-disc pl-5 text-sm"><li v-for="message in report.rejected" :key="message">{{ message }}</li></ul>
        </div>

        <div v-if="report.warnings?.length" class="mt-5">
          <h2 class="font-bold text-amber-700">Warnings</h2>
          <ul class="mt-2 list-disc pl-5 text-sm"><li v-for="message in report.warnings" :key="message">{{ message }}</li></ul>
        </div>

        <p class="mt-5 rounded-xl bg-[#f3f8fc] p-4 text-sm text-[#194568]">
          Each spreadsheet row will become one licence seat. Licence IDs are generated automatically, and licence keys are encrypted in the database.
        </p>

        <form class="mt-6" @submit.prevent="confirm.post(route('it-licenses.import.store'))">
          <button class="btn bg-[#4f9f4a] text-white" :disabled="confirm.processing || !report.ready">
            {{ confirm.processing ? 'Importing...' : `Import ${report.ready} validated licences` }}
          </button>
        </form>
      </section>

      <Link class="btn" :href="route('it-licenses.index')">Cancel</Link>
    </section>
  </AuthenticatedLayout>
</template>
