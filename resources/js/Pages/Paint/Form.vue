<script setup>
import CompanyField from '@/Components/CompanyField.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
const props = defineProps({ record: Object, fields: Array });
const groups = [...new Set(props.fields.filter(f => f.key !== 'original_date').map(f => f.group))];
const form = useForm({
    company: props.record?.company ?? '',
    stock_token: props.record?.stock_token || null,
    ...Object.fromEntries(props.fields.filter(f => f.key !== 'original_date').map(f => [f.key, props.record?.[f.key] ?? (f.key === 'category' ? 'PAINT & GARNET' : f.key === 'section_1' ? 'PAINT' : '')])),
    manufacture_date: props.record?.manufacture_date || '', best_before_date: props.record?.best_before_date || '',
    date_status: props.record?.date_status || 'not_recorded', review_note: '',
});
const submit = () => props.record ? form.patch(route('paint.update',props.record.id)) : form.post(route('paint.store'));
function assignDate(field) {
    form.manufacture_date = '';
    form.best_before_date = '';
    form[field] = props.record.unconfirmed_date;
    form.date_status = 'confirmed';
}
</script>
<template>
    <Head :title="record ? 'Edit Paint record' : 'Register paint'" />
    <AuthenticatedLayout>
        <form class="space-y-5" @submit.prevent="submit">
            <div class="rounded-2xl border bg-white p-5"><CompanyField v-model="form.company" :error="form.errors.company" /></div>
            <header class="rounded-3xl border border-[#d8e7d4] bg-white p-6"><img src="/images/dayang-logo.png" alt="Dayang" class="mb-4 h-14 w-auto" /><h1 class="text-2xl font-bold text-[#234222]">{{ record ? 'Edit Paint record #' + record.id : 'Register paint' }}</h1><p class="mt-2 text-sm text-slate-500">Miri Paint Register · New Paint COGs update closing quantities. Manual balance corrections require a review note. Prices remain recorded values.</p></header>
            <div v-if="Object.keys(form.errors).length" role="alert" class="rounded-xl bg-red-50 p-4 text-red-700"><p v-for="(message,key) in form.errors" :key="key">{{ message }}</p></div>
            <section class="rounded-3xl border border-amber-200 bg-amber-50 p-6">
                <h2 class="font-bold text-[#234222]">Manufacture &amp; best-before date review</h2>
                <p class="mt-2 whitespace-pre-wrap text-sm">Original CSV date: {{ record?.original_date || 'Not recorded' }}</p>
                <p class="mt-2 text-sm text-amber-900">Single dates must be identified by staff. Confirmed date pairs follow the source heading: manufacture → best before. Unconfirmed dates are excluded from expiry alerts.</p>
                <div v-if="record?.unconfirmed_date" class="mt-3 flex flex-wrap gap-2"><button type="button" class="btn btn-sm" @click="assignDate('manufacture_date')">Use single date as manufacture</button><button type="button" class="btn btn-sm" @click="assignDate('best_before_date')">Use single date as best before</button></div>
                <div class="mt-4 grid gap-4 md:grid-cols-3">
                    <label class="text-sm">Date meaning<select v-model="form.date_status" class="mt-2 w-full rounded-xl border-slate-200"><option value="not_recorded" :disabled="!!record?.original_date">Not recorded</option><option value="unconfirmed">Unconfirmed — needs review</option><option value="confirmed">Confirmed meaning</option></select></label>
                    <label class="text-sm">Manufacture date<input v-model="form.manufacture_date" type="date" class="mt-2 w-full rounded-xl border-slate-200" /></label>
                    <label class="text-sm">Best-before date<input v-model="form.best_before_date" type="date" class="mt-2 w-full rounded-xl border-slate-200" /></label>
                </div>
                <label class="mt-4 block text-sm">Review note (required for stock adjustments or date changes)<textarea v-model="form.review_note" class="mt-2 w-full rounded-xl border-slate-200" rows="2" placeholder="How was the date meaning verified?" /></label>
                <p v-if="record?.review_note" class="mt-2 text-xs text-slate-600">Previous note: {{ record.review_note }}</p>
            </section>
            <section v-for="group in groups" :key="group" class="rounded-3xl border border-[#d8e7d4] bg-white p-6">
                <h2 class="mb-4 text-lg font-bold text-[#234222]">{{ group }}</h2>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"><label v-for="field in fields.filter(f => f.group === group && f.key !== 'original_date')" :key="field.key" class="text-sm text-slate-600">{{ field.label }}
                    <textarea v-if="field.type === 'textarea'" v-model="form[field.key]" class="mt-2 w-full rounded-xl border-slate-200" rows="2" />
                    <input v-else v-model="form[field.key]" :type="['number','money'].includes(field.type) ? 'number' : 'text'" :step="field.type === 'money' ? '0.01' : '0.001'" :min="['number','money'].includes(field.type) ? 0 : undefined" class="mt-2 w-full rounded-xl border-slate-200" />
                </label></div>
            </section>
            <div class="flex gap-3"><button class="btn bg-[#4f9f4a] text-white" :disabled="form.processing">{{ form.processing ? 'Saving…' : 'Save record' }}</button><Link :href="record ? route('paint.show',record.id) : route('paint.index')" class="btn">Cancel</Link></div>
        </form>
    </AuthenticatedLayout>
</template>
