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
    removed_certificate_ids: [],
    inventory_type: item?.inventory_type ?? props.inventoryType ?? 'machinery',
    size_model: item?.size_model ?? '', size_ton: item?.size_ton ?? '', size_length: item?.size_length ?? '', quantity: item?.quantity ?? '',
    category: item?.category ?? props.categories?.[0] ?? 'MAJOR EQUIPMENT', section_1: item?.section_1 ?? (props.inventoryType === 'cargo' ? 'CARGO SET' : 'Machinery'), section_2: item?.section_2 ?? '',
    description: item?.description ?? '', unit: item?.unit ?? '', model_brand: item?.model_brand ?? '', serial_no: item?.serial_no ?? '', tag_no: item?.tag_no ?? '',
    current_location: item?.current_location ?? '', status: item?.status ?? (props.inventoryType === 'cargo' ? '' : 'Standby'), issue_out_location: item?.issue_out_location ?? '',
    issue_out_cog_no: item?.issue_out_cog_no ?? '', issue_out_cog_date: date(item?.issue_out_cog_date), received_backload_cog_no: item?.received_backload_cog_no ?? '', received_backload_cog_date: date(item?.received_backload_cog_date),
    mr_request: item?.mr_request ?? '', purchase_order: item?.purchase_order ?? '', delivery_order: item?.delivery_order ?? '', supplier: item?.supplier ?? '',
    unfit_report: item?.unfit_report ?? '', write_off_reference: item?.write_off_reference ?? '', remarks: item?.remarks ?? '', active: item?.active ?? true,
    certificates: (item?.certificates ?? []).map((certificate) => ({ row_key: 'saved-' + certificate.id, id: certificate.id, image: null, remove_image: false, has_image: certificate.has_image, image_name: certificate.image_name, image_mime: certificate.image_mime, certificate_type: certificate.certificate_type, certificate_no: certificate.certificate_no ?? '', issue_date: date(certificate.issue_date), expiry_date: date(certificate.expiry_date), raw_value: certificate.raw_value ?? '' })),
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
const submit = () => form.transform(data => ({ ...data, ...(item ? { _method: 'patch' } : {}) })).post(item ? route('major-equipment.update', item.id) : route('major-equipment.store'), { preserveScroll: true, forceFormData: true });
let nextCertificateKey = 0;
const addCertificate = () => form.certificates.push({ row_key: 'new-' + nextCertificateKey++, id: null, image: null, remove_image: false, has_image: false, image_name: null, certificate_type: visibleCertificateTypes.value?.[0] ?? '', certificate_no: '', issue_date: '', expiry_date: '', raw_value: '' });
const removeCertificate = (index) => {
    const cert = form.certificates[index];
    if (!window.confirm('Remove this certificate and its saved attachment when you save the record?')) return;
    if (cert.id) form.removed_certificate_ids.push(cert.id);
    form.certificates.splice(index, 1);
};
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
            <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3"><div><h2 class="text-lg font-semibold">Certificates</h2><p class="text-sm text-slate-500">Attach one PDF or image per certificate (PDF, JPG, PNG or WebP), up to 5 MB. Images: maximum 4096 × 4096 pixels. Upload up to 10 files / 20 MB per save.</p></div><button type="button" class="btn bg-[#4f9f4a] text-white" @click="addCertificate">Add certificate</button></div>
                <div v-for="(certificate, index) in form.certificates" :key="certificate.row_key" class="mt-4 rounded-xl border border-[#e1efdc] p-4">
                    <div class="grid gap-3 md:grid-cols-3">
                        <label><span class="label-text">Certificate type</span><select v-model="certificate.certificate_type" class="select select-bordered mt-1 w-full"><option v-for="type in visibleCertificateTypes" :key="type">{{ type }}</option></select></label>
                        <label><span class="label-text">Certificate number</span><TextInput v-model="certificate.certificate_no" class="mt-1 w-full" /></label>
                        <label><span class="label-text">Issue date</span><input v-model="certificate.issue_date" type="date" class="input input-bordered mt-1 w-full" /></label>
                        <label><span class="label-text">Expiry date</span><input v-model="certificate.expiry_date" type="date" class="input input-bordered mt-1 w-full" /></label>
                        <label><span class="label-text">Original CSV value</span><TextInput v-model="certificate.raw_value" class="mt-1 w-full" /></label>
                        <button type="button" class="btn self-end border-red-200 text-red-700" @click="removeCertificate(index)">Remove certificate</button>
                    </div>
                    <div class="mt-4 rounded-xl bg-[#f7fbf5] p-4">
                        <div v-if="certificate.has_image && !certificate.remove_image" class="mb-3 flex flex-wrap items-center gap-4">
                            <a :href="route('major-equipment.certificates.image', { equipment: item.id, certificate: certificate.id })" target="_blank" rel="noopener"><span v-if="certificate.image_mime === 'application/pdf'" class="inline-flex h-24 w-36 items-center justify-center rounded-lg border bg-white font-bold text-green-800">Open PDF ↗</span><img v-else loading="lazy" decoding="async" :src="route('major-equipment.certificates.image', { equipment: item.id, certificate: certificate.id, preview: 1 })" alt="Saved certificate" class="h-24 w-36 rounded-lg border object-contain bg-white" /></a>
                            <div><p class="text-sm">{{ certificate.image_name }}</p><a class="text-sm font-bold text-green-800 underline" :href="route('major-equipment.certificates.image', { equipment: item.id, certificate: certificate.id, download: 1 })">Download attachment</a></div>
                        </div>
                        <label class="block"><span class="label-text">{{ certificate.has_image ? 'Replace attachment' : 'Attach PDF or image' }}</span><input :key="(certificate.id || index) + '-' + certificate.remove_image" type="file" accept="image/jpeg,image/png,image/webp,application/pdf,.pdf" class="mt-2 block w-full text-sm" :disabled="certificate.remove_image || form.processing" @change="certificate.image = $event.target.files[0] || null" /></label>
                        <InputError class="mt-2" :message="form.errors['certificates.' + index + '.image']" />
                        <label v-if="certificate.has_image" class="mt-3 flex items-center gap-2 text-sm text-red-700"><input v-model="certificate.remove_image" type="checkbox" class="checkbox checkbox-sm" @change="certificate.image = null" /> Remove saved attachment on save</label>
                        <p v-if="certificate.image" class="mt-2 text-xs text-slate-600">Selected: {{ certificate.image.name }}. The upload is saved with the record.</p>
                    </div>
                </div>
                <p v-if="form.progress" class="mt-3 text-sm">Uploading: {{ form.progress.percentage }}%</p>
            </section>
            <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-6 shadow-sm"><h2 class="text-lg font-semibold">References and notes</h2><div class="mt-5 grid gap-4 md:grid-cols-2"><div><label class="label-text">Unfit Report</label><textarea v-model="form.unfit_report" class="textarea textarea-bordered mt-2 w-full" rows="3" /></div><div><label class="label-text">Write-off Reference</label><textarea v-model="form.write_off_reference" class="textarea textarea-bordered mt-2 w-full" rows="3" /></div><div class="md:col-span-2"><label class="label-text">Remarks</label><textarea v-model="form.remarks" class="textarea textarea-bordered mt-2 w-full" rows="3" /></div></div></section>
            <div class="flex gap-3"><PrimaryButton :disabled="form.processing">{{ item ? 'Save changes' : 'Register equipment' }}</PrimaryButton><Link class="btn" :href="route('major-equipment.index', { inventory_type: form.inventory_type })">Cancel</Link></div>
        </form>
    </AuthenticatedLayout>
</template>
