<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ItLicenseForm from '@/Components/ItLicenseForm.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({ types: Array });
const form = useForm({
  license_code: '', software_name: '', vendor: '', license_type: props.types[0]?.value ?? 'subscription',
  license_key: '', seats_total: 1, seats_assigned: 0, assigned_to: '', department: '', purchase_date: '',
  expiry_date: '', auto_renew: false, renewal_cost: '', supplier: '', purchase_reference: '', active: true, remarks: '',
});
const submit = () => form.post(route('it-licenses.store'));
</script>

<template>
  <Head title="Register IT Licence" />
  <AuthenticatedLayout>
    <form class="space-y-6" @submit.prevent="submit">
      <header class="rounded-[2rem] border border-[#d8e7d4] bg-white p-7"><p class="text-xs font-bold uppercase tracking-[.25em] text-[#4f9f4a]">IT Licence Register</p><h1 class="mt-2 text-3xl font-bold text-[#234222]">Register IT licence</h1><p class="mt-2 text-sm text-[#60745d]">Track software ownership, seats, renewals, and expiry.</p></header>
      <ItLicenseForm :form="form" :types="types" />
      <div class="flex gap-3"><button class="btn bg-[#4f9f4a] text-white" :disabled="form.processing">{{ form.processing ? 'Registering...' : 'Register licence' }}</button><Link class="btn" :href="route('it-licenses.index')">Cancel</Link></div>
    </form>
  </AuthenticatedLayout>
</template>
