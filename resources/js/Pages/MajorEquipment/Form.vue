<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({ equipment: Object, categories: Array, certificateTypes: Array, inventoryType: String });
const item = props.equipment;
const date = (value) => value ? String(value).slice(0, 10) : '';
const form = useForm({
    inventory_type: item?.inventory_type ?? props.inventoryType ?? 'machinery',
    size_model: item?.size_model ?? '', size_ton: item?.size_ton ?? '', size_length: item?.size_length ?? '', quantity: item?.quantity ?? '',
    category: item?.category ?? props.categories?.[0] ?? 'MAJOR EQUIPMENT', section_1: item?.section_1 ?? (props.inventoryType === 'cargo' ? 'CARGO SET' : 'Machinery'), section_2: item?.section_2 ?? '',
    description: item?.description ?? '', unit: item?.unit ?? '', model_brand: item?.model_brand ?? '', serial_no: item?.serial_no ?? '', tag_no: item?.tag_no ?? '',
    current_location: item?.current_location ?? '', status: item?.status ?? (props.inventoryType === 'cargo' ? '' : 'Standby'), issue_out_location: item?.issue_out_location ?? '',
    issue_out_cog_no: item?.issue_out_cog_no ?? '', issue_out_cog_date: date(item?.issue_out_cog_date), received_backload_cog_no: item?.received_backload_cog_no ?? '', received_backload_cog_date: date(item?.received_backload_cog_date),
    mr_request: item?.mr_request ?? '', purchase_order: item?.purchase_order ?? '', delivery_order: item?.delivery_order ?? '', supplier: item?.supplier ?? '',
    unfit_report: item?.unfit_report ?? '', write_off_reference: item?.write_off_reference ?? '', remarks: item?.remarks ?? '', active: item?.active ?? true,
    certificates: (item?.certificates ?? []).map((certificate) => ({ certificate_type: certificate.certificate_type, certificate_no: certificate.certificate_no ?? '', issue_date: date(certificate.issue_date), expiry_date: date(certificate.expiry_date), raw_value: certificate.raw_value ?? '' })),
});
const fields = [
    ['section_1', 'Subcategory 1'], ['section_2', 'Subcategory 2'], ['description', 'Description'], ['unit', 'Unit'], ['model_brand', 'Model / Brand'], ['serial_no', 'Serial No.'], ['tag_no', 'Tag No.'], ['current_location', 'Current Location'], ['issue_out_location', 'Issue-out Location'], ['mr_request', 'MR Request'], ['purchase_order', 'Purchase Order'], ['delivery_order', 'Delivery Order'], ['supplier', 'Supplier'],
];
const cargo = computed(() => form.inventory_type === 'cargo');
const detailFields = computed(() => fields.slice(2).filter(([key]) => !cargo.value || !['model_brand', 'serial_no'].includes(key)));
const cargoCertificateTypes = ['PADEYE MPI', 'VISUAL INSPECTION', 'WIRE SLING VALIDITY', 'CERTIFICATE OF CONFORMITY'];
const visibleCertificateTypes = computed(() => [...new Set([
    ...props.certificateTypes.filter(type => cargo.value ? cargoCertificateTypes.includes(type) : !cargoCertificateTypes.includes(type)),
    ...form.certificates.map(cert => cert.certificate_type),
])]);
const submit = () => form[item ? 'patch' : 'post'](item ? route('major-equipment.update', item.id) : route('major-equipment.store'), { preserveScroll: true });
const addCertificate = () => form.certificates.push({ certificate_type: visibleCertificateTypes.value?.[0] ?? '', certificate_no: '', issue_date: '', expiry_date: '', raw_value: '' });
const removeCertificate = (index) => form.certificates.splice(index, 1);
</script>
<template>
    <Head :title="item ? 'Edit Miri Equipment' : 'Register Miri Equipment'" />
    <AuthenticatedLayout>
        <PageHeader :title="item ? 'Edit Miri Equipment' : 'Register Miri Equipment'" :description="cargo ? 'Cargo dimensions, quantities and inspection certificates.' : 'Machinery details and equipment certificates.'" />
        <form class="space-y-6" @submit.prevent="submit">
            <div v-if="Object.keys(form.errors).length" role="alert" class="rounded-xl bg-red-50 p-4 text-sm text-red-700"><p v-for="(error,key) in form.errors" :key="key">{{ error }}</p></div>
            <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold">{{ cargo ? 'Cargo' : 'Machinery' }} classification</h2>
                <p class="mt-2 text-sm text-slate-500">Major Equipment / {{ cargo ? 'Cargo Set' : 'Machinery' }}</p>
                <div class="mt-5 grid gap-4 md:grid-cols-3">
                    <div><label class="label-text">Category</label><select v-model="form.category" class="select select-bordered mt-2 w-full"><option v-for="category in categories" :key="category" :value="category">{{ category }}</option></select><InputError :message="form.errors.category" /></div>
                    <div v-for="[key, label] in fields.slice(0, 2)" :key="key"><label class="label-text">{{ label }}</label><TextInput v-model="form[key]" class="mt-2 w-full" /><InputError :message="form.errors[key]" /></div>
                </div>
            </section>
            <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold">Equipment details</h2>
                <div class="mt-5 grid gap-4 md:grid-cols-3"><div v-for="[key, label] in detailFields" :key="key"><label class="label-text">{{ label }}</label><TextInput v-model="form[key]" class="mt-2 w-full" /><InputError :message="form.errors[key]" /></div><div><label class="label-text">Status</label><TextInput v-if="cargo" v-model="form.status" class="mt-2 w-full" placeholder="Status as recorded" /><select v-else v-model="form.status" class="select select-bordered mt-2 w-full"><option value="">Not recorded</option><option v-if="form.status && !['In Use', 'Standby', 'Under Repair', 'Damaged'].includes(form.status)" :value="form.status">{{ form.status }}</option><option>In Use</option><option>Standby</option><option>Under Repair</option><option>Damaged</option></select></div></div>
            </section>
            <section v-if="cargo" class="rounded-[2rem] border border-[#d8e7d4] bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold">Cargo specifications</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-4">
                    <label v-for="[key,label] in [['size_model','Dimensions / Model'],['size_ton','Tonnage'],['size_length','Length']]" :key="key"><span class="label-text">{{ label }}</span><TextInput v-model="form[key]" class="mt-2 w-full" /><InputError :message="form.errors[key]" /></label>
                    <label><span class="label-text">Quantity (leave blank if unknown)</span><input v-model="form.quantity" type="number" min="0" step="0.01" class="input input-bordered mt-2 w-full" /><InputError :message="form.errors.quantity" /></label>
                </div>
            </section>
            <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold">COG information</h2>
                <div class="mt-5 grid gap-4 md:grid-cols-2"><div><label class="label-text">Issue-out COG No.</label><TextInput v-model="form.issue_out_cog_no" class="mt-2 w-full" /></div><div><label class="label-text">Issue-out COG Date</label><input v-model="form.issue_out_cog_date" type="date" class="input input-bordered mt-2 w-full" /></div><div><label class="label-text">Received-backload COG No.</label><TextInput v-model="form.received_backload_cog_no" class="mt-2 w-full" /></div><div><label class="label-text">Received-backload COG Date</label><input v-model="form.received_backload_cog_date" type="date" class="input input-bordered mt-2 w-full" /></div></div>
            </section>
            <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-6 shadow-sm"><div class="flex items-center justify-between"><div><h2 class="text-lg font-semibold">Certificates</h2><p class="text-sm text-slate-500">Add certificate number and expiry details from the CSV columns.</p></div><button type="button" class="btn bg-[#4f9f4a] text-white" @click="addCertificate">Add certificate</button></div><div v-for="(certificate, index) in form.certificates" :key="index" class="mt-4 grid gap-3 rounded-xl border border-[#e1efdc] p-4 md:grid-cols-6"><select v-model="certificate.certificate_type" class="select select-bordered"><option v-for="type in visibleCertificateTypes" :key="type">{{ type }}</option></select><TextInput v-model="certificate.certificate_no" placeholder="Certificate No." /><input v-model="certificate.issue_date" type="date" class="input input-bordered" /><input v-model="certificate.expiry_date" type="date" class="input input-bordered" /><TextInput v-model="certificate.raw_value" placeholder="Original CSV value" /><button type="button" class="btn border-red-200 text-red-700" @click="removeCertificate(index)">Remove</button></div></section>
            <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-6 shadow-sm"><h2 class="text-lg font-semibold">References and notes</h2><div class="mt-5 grid gap-4 md:grid-cols-2"><div><label class="label-text">Unfit Report</label><textarea v-model="form.unfit_report" class="textarea textarea-bordered mt-2 w-full" rows="3" /></div><div><label class="label-text">Write-off Reference</label><textarea v-model="form.write_off_reference" class="textarea textarea-bordered mt-2 w-full" rows="3" /></div><div class="md:col-span-2"><label class="label-text">Remarks</label><textarea v-model="form.remarks" class="textarea textarea-bordered mt-2 w-full" rows="3" /></div></div></section>
            <div class="flex gap-3"><PrimaryButton :disabled="form.processing">{{ item ? 'Save changes' : 'Register equipment' }}</PrimaryButton><Link class="btn" :href="route('major-equipment.index', { inventory_type: form.inventory_type })">Cancel</Link></div>
        </form>
    </AuthenticatedLayout>
</template>
