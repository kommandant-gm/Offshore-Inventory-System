<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({ license: Object });
const page = usePage();
const canEdit = computed(() => page.props.auth?.user?.can?.it_assets_edit);
const label = (value) => value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
const statusStyles = { active:'border-emerald-200 bg-emerald-50 text-emerald-700', expiring_soon:'border-amber-200 bg-amber-50 text-amber-700', expired:'border-red-200 bg-red-50 text-red-700', inactive:'border-slate-200 bg-slate-100 text-slate-600' };
const formatDate = (date) => date ? new Intl.DateTimeFormat('en-MY', { day:'2-digit', month:'short', year:'numeric' }).format(new Date(`${date}T00:00:00`)) : '—';
const money = (value) => value === null || value === '' ? '—' : new Intl.NumberFormat('en-MY', { style:'currency', currency:'MYR' }).format(Number(value));
const details = computed(() => [
  ['Vendor', props.license.vendor], ['Licence type', label(props.license.license_type)], ['Licence key', props.license.license_key_masked],
  ['Assigned to / owner', props.license.assigned_to], ['Department', props.license.department], ['Supplier', props.license.supplier],
  ['Purchase reference', props.license.purchase_reference], ['Purchase date', formatDate(props.license.purchase_date)],
  ['Expiry date', props.license.expiry_date ? formatDate(props.license.expiry_date) : 'No expiry'], ['Auto renewal', props.license.auto_renew ? 'Yes' : 'No'],
  ['Renewal cost', money(props.license.renewal_cost)], ['Record state', props.license.active ? 'Active' : 'Inactive'],
]);
</script>

<template>
  <Head :title="license.license_code" />
  <AuthenticatedLayout>
    <section class="space-y-6">
      <header class="flex flex-wrap items-end justify-between gap-4 rounded-[2rem] border border-[#d8e7d4] bg-white p-7 shadow-sm"><div><p class="text-xs font-bold uppercase tracking-[.25em] text-[#4f9f4a]">IT Licence</p><h1 class="mt-2 text-3xl font-bold text-[#234222]">{{ license.software_name }}</h1><p class="mt-2 font-mono text-sm text-[#60745d]">{{ license.license_code }}</p></div><div class="flex gap-2"><Link class="btn" :href="route('it-licenses.index')">Back to register</Link><Link v-if="canEdit" class="btn bg-[#4f9f4a] text-white" :href="route('it-licenses.edit', license.id)">Edit licence</Link></div></header>
      <div class="grid gap-5 md:grid-cols-3">
        <article class="rounded-2xl border border-[#d8e7d4] bg-white p-5"><p class="text-xs font-bold uppercase tracking-wider text-slate-500">Status</p><span class="mt-3 inline-flex rounded-full border px-3 py-1.5 text-xs font-bold" :class="statusStyles[license.status]">{{ label(license.status) }}</span></article>
        <article class="rounded-2xl border border-[#d8e7d4] bg-white p-5"><p class="text-xs font-bold uppercase tracking-wider text-slate-500">Seat usage</p><p class="mt-2 text-2xl font-black text-[#234222]">{{ license.seats_assigned }} / {{ license.seats_total }}</p><p class="text-xs text-[#60745d]">{{ license.seats_available }} seats available</p></article>
        <article class="rounded-2xl border border-[#d8e7d4] bg-white p-5"><p class="text-xs font-bold uppercase tracking-wider text-slate-500">Expiry</p><p class="mt-2 text-lg font-bold text-[#234222]">{{ license.expiry_date ? formatDate(license.expiry_date) : 'No expiry' }}</p><p class="text-xs text-[#60745d]">{{ license.auto_renew ? 'Automatic renewal enabled' : 'Manual renewal' }}</p></article>
      </div>
      <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3"><article v-for="detail in details" :key="detail[0]" class="rounded-2xl border border-[#d8e7d4] bg-white p-5"><p class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ detail[0] }}</p><p class="mt-2 break-words font-semibold text-[#234222]">{{ detail[1] || '—' }}</p></article></div>
      <article class="rounded-[2rem] border border-[#d8e7d4] bg-white p-7"><p class="text-xs font-bold uppercase tracking-wider text-slate-500">Remarks</p><p class="mt-3 whitespace-pre-wrap text-sm leading-6 text-[#60745d]">{{ license.remarks || 'No remarks recorded.' }}</p></article>
    </section>
  </AuthenticatedLayout>
</template>
