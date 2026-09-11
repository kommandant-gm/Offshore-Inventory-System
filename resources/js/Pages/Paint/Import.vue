<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';
defineProps({ recentImports: Array });
const form = useForm({ file: null });
const report = ref(null);
const previewing = ref(false);
function choose(event) { form.file = event.target.files[0] || null; report.value = null; form.clearErrors(); }
async function preview() {
    previewing.value = true; report.value = null; form.clearErrors();
    try {
        const body = new FormData(); if (form.file) body.append('file', form.file);
        report.value = (await axios.post(route('paint.import.preview'), body)).data;
    } catch (error) {
        form.setError('file', Object.values(error.response?.data?.errors || {}).flat().join(' ') || 'Unable to preview CSV. Check the format and try again.');
    } finally { previewing.value = false; }
}
const submit = () => form.post(route('paint.import.store'), { forceFormData: true });
</script>
<template>
    <Head title="Import Paint register" />
    <AuthenticatedLayout>
        <section class="space-y-5">
            <header class="rounded-3xl border border-[#d8e7d4] bg-white p-6"><h1 class="text-2xl font-bold text-[#234222]">Import Paint</h1><p class="mt-3 text-sm text-slate-600">Use the original three-row grouped headers and all 32 meaningful columns. Empty trailing columns are ignored. Maximum CSV size: 20 MB.</p></header>
            <section v-if="recentImports.length" class="rounded-2xl border bg-white p-5"><h2 class="font-bold">Your recent imports</h2><Link v-for="task in recentImports" :key="task.id" :href="route('paint.import.status', task.id)" class="mt-2 block text-sm text-green-800 underline">{{ task.filename }} — {{ task.status }}</Link></section>
            <form class="space-y-5 rounded-3xl border border-[#d8e7d4] bg-white p-6" @submit.prevent="preview">
                <p class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900">All source rows are kept separately. Possible repeated batches are flagged, not merged. Single dates remain unconfirmed and do not trigger expiry alerts. Blanks stay unknown. Imported balances and closing values are preserved; historical receipts/issues do not change them again.</p>
                <input type="file" accept=".csv,text/csv" class="block max-w-full" :disabled="previewing || form.processing" @change="choose" />
                <p v-if="form.errors.file" role="alert" class="text-sm text-red-700">{{ form.errors.file }}</p>
                <button class="btn bg-[#234222] text-white" :disabled="!form.file || previewing || form.processing">{{ previewing ? 'Checking all rows…' : 'Preview CSV' }}</button>
                <section v-if="report" class="space-y-4 border-t pt-5">
                    <h2 class="font-bold">Import preview</h2>
                    <p class="text-sm">{{ report.paired_dates }} valid date pairs mapped as manufacture / best before; {{ report.missing_dates }} rows have no dates.</p>
                    <div v-if="report.conversions.length" class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900"><p class="font-bold">Numeric warnings / proposed conversions (first 10)</p><p v-for="(warning,i) in report.conversions" :key="i">Row {{ warning.row }}: {{ warning.message }}</p></div>
                    <details v-if="report.date_reviews.length" class="text-sm"><summary>Unconfirmed source dates (first 10)</summary><p v-for="row in report.date_reviews" :key="row.row">Row {{ row.row }}: {{ row.value }}</p></details>
                    <p class="text-sm text-slate-600">The imports worker must be running on the server. Confirming queues this file; it does not import immediately.</p>
                    <div class="flex flex-wrap gap-4 text-sm"><span>{{ report.records }} source rows</span><span>{{ report.needs_review }} rows need details reviewed</span><span>{{ report.duplicate_records }} possible repeated rows</span><span>{{ report.unconfirmed_dates }} unconfirmed dates</span></div>
                    <div class="flex flex-wrap gap-2"><span v-for="(count, category) in report.categories" :key="category" class="rounded-full bg-green-50 px-3 py-1 text-xs">{{ category }}: {{ count }}</span></div>
                    <p v-if="report.already_imported" role="alert" class="text-amber-800">This file is already queued or imported. Open its progress page above.</p>
                    <p class="text-xs text-slate-500">First five rows shown; the entire file has been checked. Review and duplicate counts can overlap.</p>
                    <div class="overflow-x-auto"><table class="table"><thead><tr><th>CSV record</th><th>Description / Batch</th><th>Original date / meaning</th><th>Review</th></tr></thead><tbody><tr v-for="row in report.samples" :key="row.source_row"><td>{{ row.source_row }}</td><td>{{ row.description || 'Not recorded' }}<p class="text-xs">{{ row.batch_no || 'No batch' }}</p></td><td>{{ row.original_date || 'Not recorded' }} · {{ row.date_status }}</td><td><p v-for="flag in row.flags" :key="flag" class="text-xs text-amber-800">{{ flag }}</p></td></tr></tbody></table></div>
                    <button type="button" class="btn bg-[#4f9f4a] text-white" :disabled="report.already_imported || form.processing || previewing" @click="submit">{{ form.processing ? 'Queuing…' : 'Confirm background import' }}</button>
                </section>
            </form>
            <Link :href="route('paint.index')" class="btn">Back to register</Link>
        </section>
    </AuthenticatedLayout>
</template>
