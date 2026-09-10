<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, Link } from '@inertiajs/vue3';
defineProps({ equipment: Object, duplicates: { type: Array, default: () => [] } });

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
        <section v-if="duplicates.length" class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 p-5">
            <h2 class="font-bold text-amber-900">Duplicate tag — review matching records</h2>
            <p class="mt-1 text-sm">This is record #{{ equipment.id }}. These other Miri records share the same tag. No records have been merged.</p>
            <Link v-for="match in duplicates" :key="match.id" class="mt-2 block text-sm font-semibold underline" :href="route('major-equipment.show', match.id)">#{{ match.id }} · {{ match.inventory_type }} · {{ match.description || '-' }} · {{ match.current_location || 'No location' }}</Link>
        </section>
        <section v-if="equipment.import_warnings?.length" class="mb-5 rounded-2xl border border-orange-200 bg-orange-50 p-5"><h2 class="font-bold">Import warnings</h2><p class="mt-1 text-xs">Warnings describe the original import. Compare with current values when reviewing corrections.</p><p v-for="warning in equipment.import_warnings" :key="warning" class="mt-2 text-sm">{{ warning }}</p></section>
        <section v-if="equipment.inventory_type === 'cargo'" class="mb-5 rounded-2xl border border-[#d8e7d4] bg-white p-5">
            <h2 class="font-bold">Cargo specifications</h2>
            <dl class="mt-4 grid gap-4 md:grid-cols-4"><div v-for="[key,label] in [['size_model','Dimensions / Model'],['size_ton','Tonnage'],['size_length','Length'],['quantity','Quantity']]" :key="key"><dt class="text-xs text-slate-500">{{ label }}</dt><dd>{{ equipment[key] ?? 'Not recorded' }}</dd></div></dl>
        </section>
        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-6 shadow-sm"><h2 class="text-lg font-semibold">Equipment Details</h2><dl class="mt-4 space-y-3 text-sm"><div><dt class="text-slate-500">Category</dt><dd>{{ equipment.category }}</dd></div><div><dt class="text-slate-500">Section</dt><dd>{{ equipment.section_1 }} / {{ equipment.section_2 }}</dd></div><div v-if="equipment.inventory_type !== 'cargo'"><dt class="text-slate-500">Model / Brand</dt><dd>{{ equipment.model_brand || '-' }}</dd></div><div v-if="equipment.inventory_type !== 'cargo'"><dt class="text-slate-500">Serial No.</dt><dd>{{ equipment.serial_no || '-' }}</dd></div><div><dt class="text-slate-500">Current Location</dt><dd>{{ equipment.current_location || '-' }}</dd></div><div><dt class="text-slate-500">Issue-out Location</dt><dd>{{ equipment.issue_out_location || '-' }}</dd></div><div><dt class="text-slate-500">Status</dt><dd>{{ equipment.status || '-' }}</dd></div></dl></section>
            <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-6 shadow-sm"><h2 class="text-lg font-semibold">COG and Certificates</h2><dl class="mt-4 space-y-3 text-sm"><div><dt class="text-slate-500">Issue-out COG</dt><dd>{{ equipment.issue_out_cog_no || '-' }} / {{ formatDate(equipment.issue_out_cog_date) }}</dd></div><div><dt class="text-slate-500">Received-backload COG</dt><dd>{{ equipment.received_backload_cog_no || '-' }} / {{ formatDate(equipment.received_backload_cog_date) }}</dd></div></dl><div class="mt-5 space-y-3"><div v-for="certificate in equipment.certificates" :key="certificate.id" class="rounded-xl border p-3"><p class="font-semibold">{{ certificate.certificate_type }}</p>
                <div v-if="certificate.has_image" class="my-3 flex flex-wrap items-center gap-3">
                    <a :href="route('major-equipment.certificates.image', { equipment: equipment.id, certificate: certificate.id })" target="_blank" rel="noopener"><span v-if="certificate.image_mime === 'application/pdf'" class="inline-flex h-28 w-40 items-center justify-center rounded-lg border bg-white font-bold text-green-800">Open PDF ↗</span><img v-else loading="lazy" :src="route('major-equipment.certificates.image', { equipment: equipment.id, certificate: certificate.id })" alt="Certificate attachment" class="h-28 w-40 rounded-lg border bg-white object-contain" /></a>
                    <div><p class="text-sm">{{ certificate.image_name }}</p><a class="text-sm font-bold text-green-800 underline" :href="route('major-equipment.certificates.image', { equipment: equipment.id, certificate: certificate.id, download: 1 })">Download attachment</a></div>
                </div><p class="text-sm text-slate-600">{{ certificate.certificate_no || '-' }} · Expiry: {{ formatDate(certificate.expiry_date) }}</p></div><p v-if="equipment.certificates.length === 0" class="text-sm text-slate-500">No certificates recorded.</p></div></section>
        </div>
        <section class="mt-5 rounded-2xl border border-[#d8e7d4] bg-white p-5">
            <h2 class="font-bold">References and notes</h2>
            <dl class="mt-4 grid gap-4 md:grid-cols-2"><div v-for="[key,label] in [['unit','Unit'],['supplier','Supplier'],['mr_request','MR Request'],['purchase_order','Purchase Order'],['delivery_order','Delivery Order'],['unfit_report','Unfit / Damage Report'],['write_off_reference','Write-off Reference'],['remarks','Remarks']]" :key="key"><dt class="text-xs text-slate-500">{{ label }}</dt><dd class="whitespace-pre-wrap">{{ equipment[key] || '-' }}</dd></div></dl>
        </section>
        <details v-if="equipment.source_values?.columns" class="mt-5 rounded-xl border p-4"><summary class="cursor-pointer text-sm font-bold">Original import values (CSV record {{ equipment.source_values.csv_record }})</summary><p class="mt-2 text-xs text-slate-500">Source values are kept for comparison; edits do not change this copy.</p><dl class="mt-3 grid gap-2 sm:grid-cols-2"><div v-for="(value,index) in equipment.source_values.columns" :key="index" class="text-xs"><dt class="text-slate-500">Column {{ index + 1 }}</dt><dd class="whitespace-pre-wrap">{{ value || '-' }}</dd></div></dl></details>
        <div class="mt-5 flex flex-wrap gap-3"><a class="btn bg-[#234222] text-white" :href="route('major-equipment.pdf', equipment.id)">Download registration form</a><Link v-if="$page.props.auth?.user?.can?.assets_edit" class="btn bg-[#4f9f4a] text-white" :href="route('major-equipment.edit', equipment.id)">Edit equipment</Link><Link class="btn" :href="route('major-equipment.index', { inventory_type: equipment.inventory_type })">Back to Miri Inventory</Link></div>
    </AuthenticatedLayout>
</template>
