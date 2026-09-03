<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({ equipment: Object, categories: Array, certificateTypes: Array });
const item = props.equipment;
const date = (value) => value ? String(value).slice(0, 10) : '';
const form = useForm({
    category: item?.category ?? props.categories?.[0] ?? 'MAJOR EQUIPMENT', section_1: item?.section_1 ?? '', section_2: item?.section_2 ?? '',
    description: item?.description ?? '', unit: item?.unit ?? '', model_brand: item?.model_brand ?? '', serial_no: item?.serial_no ?? '', tag_no: item?.tag_no ?? '',
    current_location: item?.current_location ?? '', status: item?.status ?? 'Standby', issue_out_location: item?.issue_out_location ?? '',
    issue_out_cog_no: item?.issue_out_cog_no ?? '', issue_out_cog_date: date(item?.issue_out_cog_date), received_backload_cog_no: item?.received_backload_cog_no ?? '', received_backload_cog_date: date(item?.received_backload_cog_date),
    mr_request: item?.mr_request ?? '', purchase_order: item?.purchase_order ?? '', delivery_order: item?.delivery_order ?? '', supplier: item?.supplier ?? '',
    unfit_report: item?.unfit_report ?? '', write_off_reference: item?.write_off_reference ?? '', remarks: item?.remarks ?? '', active: item?.active ?? true,
    certificates: (item?.certificates ?? []).map((certificate) => ({ certificate_type: certificate.certificate_type, certificate_no: certificate.certificate_no ?? '', issue_date: date(certificate.issue_date), expiry_date: date(certificate.expiry_date), raw_value: certificate.raw_value ?? '' })),
});
const fields = [
    ['section_1', 'Subcategory 1'], ['section_2', 'Subcategory 2'], ['description', 'Description'], ['unit', 'Unit'], ['model_brand', 'Model / Brand'], ['serial_no', 'Serial No.'], ['tag_no', 'Tag No.'], ['current_location', 'Current Location'], ['issue_out_location', 'Issue-out Location'], ['mr_request', 'MR Request'], ['purchase_order', 'Purchase Order'], ['delivery_order', 'Delivery Order'], ['supplier', 'Supplier'],
];
const submit = () => form[item ? 'patch' : 'post'](item ? route('major-equipment.update', item.id) : route('major-equipment.store'), { preserveScroll: true });
const addCertificate = () => form.certificates.push({ certificate_type: props.certificateTypes?.[0] ?? '', certificate_no: '', issue_date: '', expiry_date: '', raw_value: '' });
const removeCertificate = (index) => form.certificates.splice(index, 1);
</script>
<template>
    <Head :title="item ? 'Edit Miri Equipment' : 'Register Miri Equipment'" />
    <AuthenticatedLayout>
        <PageHeader :title="item ? 'Edit Miri Equipment' : 'Register Miri Equipment'" description="Create or update a Miri inventory record using the Major Equipment register fields." />
        <form class="space-y-6" @submit.prevent="submit">
            <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold">Equipment classification</h2>
                <div class="mt-5 grid gap-4 md:grid-cols-3">
                    <div><label class="label-text">Category</label><select v-model="form.category" class="select select-bordered mt-2 w-full"><option v-for="category in categories" :key="category" :value="category">{{ category }}</option></select><InputError :message="form.errors.category" /></div>
                    <div v-for="[key, label] in fields.slice(0, 2)" :key="key"><label class="label-text">{{ label }}</label><TextInput v-model="form[key]" class="mt-2 w-full" /><InputError :message="form.errors[key]" /></div>
                </div>
            </section>
            <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold">Equipment details</h2>
                <div class="mt-5 grid gap-4 md:grid-cols-3"><div v-for="[key, label] in fields.slice(2)" :key="key"><label class="label-text">{{ label }}</label><TextInput v-model="form[key]" class="mt-2 w-full" /><InputError :message="form.errors[key]" /></div><div><label class="label-text">Status</label><select v-model="form.status" class="select select-bordered mt-2 w-full"><option>In Use</option><option>Standby</option><option>Under Repair</option><option>Damaged</option></select></div></div>
            </section>
            <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold">COG information</h2>
                <div class="mt-5 grid gap-4 md:grid-cols-2"><div><label class="label-text">Issue-out COG No.</label><TextInput v-model="form.issue_out_cog_no" class="mt-2 w-full" /></div><div><label class="label-text">Issue-out COG Date</label><input v-model="form.issue_out_cog_date" type="date" class="input input-bordered mt-2 w-full" /></div><div><label class="label-text">Received-backload COG No.</label><TextInput v-model="form.received_backload_cog_no" class="mt-2 w-full" /></div><div><label class="label-text">Received-backload COG Date</label><input v-model="form.received_backload_cog_date" type="date" class="input input-bordered mt-2 w-full" /></div></div>
            </section>
            <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-6 shadow-sm"><div class="flex items-center justify-between"><div><h2 class="text-lg font-semibold">Certificates</h2><p class="text-sm text-slate-500">Add certificate number and expiry details from the CSV columns.</p></div><button type="button" class="btn bg-[#4f9f4a] text-white" @click="addCertificate">Add certificate</button></div><div v-for="(certificate, index) in form.certificates" :key="index" class="mt-4 grid gap-3 rounded-xl border border-[#e1efdc] p-4 md:grid-cols-6"><select v-model="certificate.certificate_type" class="select select-bordered"><option v-for="type in certificateTypes" :key="type">{{ type }}</option></select><TextInput v-model="certificate.certificate_no" placeholder="Certificate No." /><input v-model="certificate.issue_date" type="date" class="input input-bordered" /><input v-model="certificate.expiry_date" type="date" class="input input-bordered" /><TextInput v-model="certificate.raw_value" placeholder="Original CSV value" /><button type="button" class="btn border-red-200 text-red-700" @click="removeCertificate(index)">Remove</button></div></section>
            <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-6 shadow-sm"><h2 class="text-lg font-semibold">References and notes</h2><div class="mt-5 grid gap-4 md:grid-cols-2"><div><label class="label-text">Unfit Report</label><textarea v-model="form.unfit_report" class="textarea textarea-bordered mt-2 w-full" rows="3" /></div><div><label class="label-text">Write-off Reference</label><textarea v-model="form.write_off_reference" class="textarea textarea-bordered mt-2 w-full" rows="3" /></div><div class="md:col-span-2"><label class="label-text">Remarks</label><textarea v-model="form.remarks" class="textarea textarea-bordered mt-2 w-full" rows="3" /></div></div></section>
            <div class="flex gap-3"><PrimaryButton :disabled="form.processing">{{ item ? 'Save changes' : 'Register equipment' }}</PrimaryButton><Link class="btn" :href="route('major-equipment.index')">Cancel</Link></div>
        </form>
    </AuthenticatedLayout>
</template>
