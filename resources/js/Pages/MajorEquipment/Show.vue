<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, Link } from '@inertiajs/vue3';
defineProps({ equipment: Object });

const formatDate = (value) => {
    if (!value) return '-';

    // Preserve the database calendar date without timezone conversion.
    const dateOnly = String(value).slice(0, 10);
    const match = dateOnly.match(/^(\d{4})-(\d{2})-(\d{2})$/);

    return match ? `${match[3]}/${match[2]}/${match[1]}` : value;
};
</script>
<template>
    <Head :title="equipment.tag_no || equipment.description || 'Major Equipment'" />
    <AuthenticatedLayout>
        <PageHeader :title="equipment.tag_no || 'Major Equipment'" :description="equipment.description || '-'" />
        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-6 shadow-sm"><h2 class="text-lg font-semibold">Equipment Details</h2><dl class="mt-4 space-y-3 text-sm"><div><dt class="text-slate-500">Category</dt><dd>{{ equipment.category }}</dd></div><div><dt class="text-slate-500">Section</dt><dd>{{ equipment.section_1 }} / {{ equipment.section_2 }}</dd></div><div><dt class="text-slate-500">Model / Brand</dt><dd>{{ equipment.model_brand || '-' }}</dd></div><div><dt class="text-slate-500">Serial No.</dt><dd>{{ equipment.serial_no || '-' }}</dd></div><div><dt class="text-slate-500">Current Location</dt><dd>{{ equipment.current_location || '-' }}</dd></div><div><dt class="text-slate-500">Issue-out Location</dt><dd>{{ equipment.issue_out_location || '-' }}</dd></div><div><dt class="text-slate-500">Status</dt><dd>{{ equipment.status || '-' }}</dd></div></dl></section>
            <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-6 shadow-sm"><h2 class="text-lg font-semibold">COG and Certificates</h2><dl class="mt-4 space-y-3 text-sm"><div><dt class="text-slate-500">Issue-out COG</dt><dd>{{ equipment.issue_out_cog_no || '-' }} / {{ formatDate(equipment.issue_out_cog_date) }}</dd></div><div><dt class="text-slate-500">Received-backload COG</dt><dd>{{ equipment.received_backload_cog_no || '-' }} / {{ formatDate(equipment.received_backload_cog_date) }}</dd></div></dl><div class="mt-5 space-y-3"><div v-for="certificate in equipment.certificates" :key="certificate.id" class="rounded-xl border p-3"><p class="font-semibold">{{ certificate.certificate_type }}</p><p class="text-sm text-slate-600">{{ certificate.certificate_no || '-' }} · Expiry: {{ formatDate(certificate.expiry_date) }}</p></div><p v-if="equipment.certificates.length === 0" class="text-sm text-slate-500">No certificates recorded.</p></div></section>
        </div>
        <Link class="btn mt-5" :href="route('major-equipment.index')">Back to Miri Inventory</Link>
    </AuthenticatedLayout>
</template>
