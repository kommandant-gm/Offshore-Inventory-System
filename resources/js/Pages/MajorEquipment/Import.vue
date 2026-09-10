<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';
const props = defineProps({ inventoryType: String, recentImports: Array });
const form = useForm({ file: null, inventory_type: props.inventoryType || 'machinery' });
const report = ref(null);
const previewing = ref(false);
const resetPreview = () => { report.value = null; form.clearErrors(); };
const preview = async () => {
    resetPreview(); previewing.value = true;
    try {
        const body = new FormData();
        body.append('inventory_type', form.inventory_type);
        if (form.file) body.append('file', form.file);
        const result = await axios.post(route('major-equipment.import.preview'), body);
        report.value = result.data;
    } catch (error) {
        const errors = error.response?.data?.errors;
        form.setError('file', errors ? Object.values(errors).flat().join(' ') : 'Unable to preview this file. Check the file and try again.');
    } finally { previewing.value = false; }
};
const submit = () => form.post(route('major-equipment.import.store'));
</script>
<template>
    <Head title="Import Miri Inventory" />
    <AuthenticatedLayout>
        <PageHeader title="Import Major Equipment" description="Preview the selected CSV before importing Machinery or Cargo into Miri." />
        <section v-if="recentImports?.length" class="mb-5 rounded-2xl border border-[#d8e7d4] bg-white p-5">
            <h2 class="font-bold">Your recent imports</h2>
            <Link v-for="task in recentImports" :key="task.id" :href="route('major-equipment.import.status', task.id)" class="mt-2 block text-sm text-green-800 underline">{{ task.filename }} — {{ task.status }}</Link>
        </section>
        <form class="space-y-5 rounded-3xl border border-[#d8e7d4] bg-white p-6" @submit.prevent="preview">
            <label class="block max-w-sm"><span class="text-sm font-bold">CSV format</span><select v-model="form.inventory_type" class="select select-bordered mt-2 w-full" :disabled="previewing || form.processing" @change="resetPreview"><option value="machinery">Machinery — existing format</option><option value="cargo">Cargo — dimensions, quantity and inspections</option></select></label>
            <p class="text-sm text-slate-600">Confirmed imports run in the background; progress is shown on a separate page. Duplicate tags will be imported and highlighted for review. Blank quantities stay unspecified. Invalid dates are retained as import warnings. An identical file cannot be imported twice.</p>
            <input class="block w-full rounded-xl border p-3" type="file" accept=".csv,text/csv" :disabled="previewing || form.processing" @change="form.file = $event.target.files[0]; resetPreview()" />
            <InputError :message="form.errors.file || form.errors.inventory_type" />
            <button class="btn bg-[#234222] text-white" :disabled="previewing || form.processing || !form.file">{{ previewing ? 'Checking CSV…' : 'Preview CSV' }}</button>
            <section v-if="report" class="space-y-4 rounded-2xl border border-[#d8e7d4] p-5">
                <h2 class="font-bold">Import preview</h2>
                <div class="flex flex-wrap gap-5 text-sm"><span><strong>{{ report.records }}</strong> records</span><span><strong>{{ report.certificates }}</strong> certificates</span><span><strong>{{ report.duplicate_records }}</strong> records with duplicate tags</span><span><strong>{{ report.missing_details }}</strong> missing details</span><span><strong>{{ report.warning_records }}</strong> records with warnings</span></div>
                <p v-if="report.already_imported" role="alert" class="rounded-xl bg-amber-50 p-3 text-amber-800">This exact file has already been imported. Review the existing records in the register.</p>
                <p class="text-xs text-slate-500">First five records. All records are checked. Review warnings on individual records after import.</p>
                <div class="overflow-x-auto"><table class="table"><thead><tr><th>Tag</th><th>Description</th><th>Section / Subcategory</th><th>Location</th><th>{{ form.inventory_type === 'cargo' ? 'Dimensions / Ton / Length / Qty' : 'Model / Serial' }}</th><th>Certificates</th><th>Warnings</th></tr></thead><tbody><tr v-for="item in report.samples" :key="item.source_values.csv_record"><td>{{ item.tag_no || '-' }}</td><td>{{ item.description }}</td><td>{{ item.section_1 }} / {{ item.section_2 }}</td><td>{{ item.current_location }}</td><td v-if="form.inventory_type === 'cargo'">{{ item.size_model || '-' }} / {{ item.size_ton || '-' }} / {{ item.size_length || '-' }} / {{ item.quantity ?? 'Unspecified' }}</td><td v-else>{{ item.model_brand }} / {{ item.serial_no }}</td><td><p v-for="cert in item.certificates" :key="cert.certificate_type" class="text-xs">{{ cert.certificate_type }}: {{ cert.certificate_no || '-' }} · {{ cert.expiry_date || 'No expiry' }}</p></td><td>{{ item.import_warnings?.join('; ') || '-' }}</td></tr></tbody></table></div>
                <button type="button" class="btn bg-[#4f9f4a] text-white" :disabled="report.already_imported || form.processing || previewing" @click="submit">{{ form.processing ? 'Importing…' : 'Confirm import' }}</button>
            </section>
            <Link class="btn" :href="route('major-equipment.index', { inventory_type: form.inventory_type })">Back to register</Link>
        </form>
    </AuthenticatedLayout>
</template>
