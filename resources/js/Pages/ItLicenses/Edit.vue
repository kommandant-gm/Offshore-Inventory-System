<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ItLicenseForm from '@/Components/ItLicenseForm.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({ license: Object, types: Array });
const form = useForm({ ...props.license });
const submit = () => form.patch(route('it-licenses.update', props.license.id));
</script>

<template>
  <Head :title="`Edit ${license.license_code}`" />
  <AuthenticatedLayout>
    <form class="space-y-6" @submit.prevent="submit">
      <header class="flex flex-wrap items-end justify-between gap-4 rounded-[2rem] border border-[#d8e7d4] bg-white p-7"><div><p class="text-xs font-bold uppercase tracking-[.25em] text-[#4f9f4a]">IT Licence Register</p><h1 class="mt-2 text-3xl font-bold text-[#234222]">Edit licence</h1><p class="mt-2 text-sm text-[#60745d]">{{ license.license_code }}</p></div><Link class="btn" :href="route('it-licenses.show', license.id)">View licence</Link></header>
      <ItLicenseForm :form="form" :types="types" />
      <div class="flex gap-3"><button class="btn bg-[#4f9f4a] text-white" :disabled="form.processing">{{ form.processing ? 'Saving...' : 'Save changes' }}</button><Link class="btn" :href="route('it-licenses.show', license.id)">Cancel</Link></div>
    </form>
  </AuthenticatedLayout>
</template>
