<script setup>
import CompanyField from '@/Components/CompanyField.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
const props = defineProps({ rental: Object, categories: Array });
const item = props.rental;
const date = (value) => value ? String(value).slice(0, 10) : '';
const form = useForm({
    company: item?.company ?? '',
    category: item?.category ?? '', section_1: item?.section_1 ?? '', section_2: item?.section_2 ?? '', description: item?.description ?? '',
    serial_tag_equipment_no: item?.serial_tag_equipment_no ?? '', unit: item?.unit ?? '', supplier: item?.supplier ?? '', project_contract: item?.project_contract ?? '',
    current_location: item?.current_location ?? '', rental_due_date: date(item?.rental_due_date), issue_out_cog_no: item?.issue_out_cog_no ?? '', issue_out_cog_date: date(item?.issue_out_cog_date),
    received_backload_from_location: item?.received_backload_from_location ?? '', received_backload_cog_no: item?.received_backload_cog_no ?? '', received_backload_cog_date: date(item?.received_backload_cog_date),
    offhire_certificate_no: item?.offhire_certificate_no ?? '', offhire_certificate_date: date(item?.offhire_certificate_date), return_cog_no: item?.return_cog_no ?? '', return_cog_date: date(item?.return_cog_date),
    mr_no: item?.mr_no ?? '', mr_date: date(item?.mr_date), po_or_sr_no: item?.po_or_sr_no ?? '', po_or_sr_date: date(item?.po_or_sr_date), do_no: item?.do_no ?? '', do_date: date(item?.do_date),
    onhire_certificate_no: item?.onhire_certificate_no ?? '', onhire_certificate_date: date(item?.onhire_certificate_date), status: item?.status ?? 'On Hire', remarks: item?.remarks ?? '', active: item?.active ?? true,
});
const statuses = ['On Hire', 'Issued', 'Received Backload', 'Off Hire', 'Returned to Supplier', 'Overdue'];
const groups = [
    { title: 'Classification and rental item', fields: [['category','Category'],['section_1','Subcategory 1'],['section_2','Subcategory 2'],['description','Description'],['serial_tag_equipment_no','Serial / Tag / Equipment No.'],['unit','Unit'],['supplier','Supplier'],['project_contract','Project / Contract'],['current_location','Current Location'],['rental_due_date','Rental Due Date']] },
    { title: 'Issue out to location', fields: [['issue_out_cog_no','Issue-out COG No.'],['issue_out_cog_date','Issue-out COG Date']] },
    { title: 'Received backload', fields: [['received_backload_from_location','From Location'],['received_backload_cog_no','Received-backload COG No.'],['received_backload_cog_date','Received-backload COG Date']] },
    { title: 'Return to supplier', fields: [['offhire_certificate_no','Offhire Certificate No.'],['offhire_certificate_date','Offhire Certificate Date'],['return_cog_no','Return COG No.'],['return_cog_date','Return COG Date']] },
    { title: 'Documents', fields: [['mr_no','MR No.'],['mr_date','MR Date'],['po_or_sr_no','PO / SR No.'],['po_or_sr_date','PO / SR Date'],['do_no','DO No.'],['do_date','DO Date'],['onhire_certificate_no','Onhire Certificate No.'],['onhire_certificate_date','Onhire Certificate Date']] },
];
const submit = () => form[item ? 'patch' : 'post'](item ? route('miri-rental.update', item.id) : route('miri-rental.store'));
</script>
<template>
    <Head :title="item ? 'Edit Rental' : 'Register Rental'" />
    <AuthenticatedLayout>
        <PageHeader :title="item ? 'Edit Rental' : 'Register Rental'" description="Maintain the Rental register with separate movement and supplier-return information." />
        <form class="space-y-6" @submit.prevent="submit">
            <div class="rounded-2xl border bg-white p-5"><CompanyField v-model="form.company" :error="form.errors.company" /></div>
            <section v-for="group in groups" :key="group.title" class="rounded-[2rem] border border-[#d8e7d4] bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold">{{ group.title }}</h2>
                <div class="mt-5 grid gap-4 md:grid-cols-3">
                    <div v-for="[key, label] in group.fields" :key="key">
                        <label class="label-text">{{ label }}</label>
                        <select v-if="key === 'category'" v-model="form[key]" class="select select-bordered mt-2 w-full"><option value="">Select category</option><option v-for="category in categories" :key="category" :value="category">{{ category }}</option></select>
                        <select v-else-if="key === 'status'" v-model="form[key]" class="select select-bordered mt-2 w-full"><option v-for="status in statuses" :key="status">{{ status }}</option></select>
                        <input v-else-if="key.endsWith('_date') || key === 'rental_due_date'" v-model="form[key]" type="date" class="input input-bordered mt-2 w-full" />
                        <TextInput v-else v-model="form[key]" class="mt-2 w-full" />
                        <InputError :message="form.errors[key]" />
                    </div>
                </div>
            </section>
            <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-6 shadow-sm"><label class="label-text">Status</label><select v-model="form.status" class="select select-bordered mt-2 w-full md:w-1/3"><option v-for="status in statuses" :key="status">{{ status }}</option></select><label class="label-text mt-5 block">Remarks</label><textarea v-model="form.remarks" class="textarea textarea-bordered mt-2 w-full" rows="4" /></section>
            <div class="flex gap-3"><PrimaryButton :disabled="form.processing">{{ item ? 'Save changes' : 'Register rental' }}</PrimaryButton><Link class="btn" :href="route('miri-rental.index')">Cancel</Link></div>
        </form>
    </AuthenticatedLayout>
</template>
