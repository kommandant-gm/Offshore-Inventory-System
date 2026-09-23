<script setup>
import CompanyField from '@/Components/CompanyField.vue';
import CustomSelect from '@/Components/CustomSelect.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
const props = defineProps({ record: Object, fields: Array, attachmentSlots: Object, classificationOptions: { type: Array, default: () => [] } });
const visibleFields = computed(() => props.fields.filter(field => !['unit_price', 'closing_value'].includes(field.key)));
const groups = computed(() => [...new Set(visibleFields.value.map(field => field.group))]);
const classificationKeys = ['category', 'section_1', 'section_2'];
const customEntry = reactive({ category: false, section_1: false, section_2: false });
const form = useForm({
    company: props.record?.company ?? '',
    ...Object.fromEntries(visibleFields.value.map(field => [field.key, props.record?.[field.key] ?? ''])),
    grouping_reviewed: props.record?.grouping_reviewed || false,
    review_note: props.record?.review_note || '',
    uploads: Object.fromEntries(Object.keys(props.attachmentSlots).map(key => [key, null])),
    remove_attachments: [],
});
const previousClassification = Object.fromEntries(classificationKeys.map(key => [key, form[key]]));
function optionsFor(key) {
    const rows = props.classificationOptions.filter(row =>
        (key === 'category' || !form.category || row.category === form.category)
        && (key !== 'section_2' || !form.section_1 || row.section_1 === form.section_1));
    return [...new Set([...rows.map(row => row[key]), form[key]].filter(value => value != null && String(value).trim() !== ''))].sort((a, b) => a.localeCompare(b));
}
function classificationChanged(key) {
    if (previousClassification[key] === form[key]) return;
    previousClassification[key] = form[key];
    for (const child of classificationKeys.slice(classificationKeys.indexOf(key) + 1)) {
        form[child] = '';
        previousClassification[child] = '';
        customEntry[child] = false;
    }
}
const slotsFor = group => group === 'Certificate' ? ['certificate'] : group === 'Certification' ? ['inspection', 'conformity'] : [];
function selectFile(slot, event) {
    form.uploads[slot] = event.target.files[0] || null;
    form.remove_attachments = form.remove_attachments.filter(value => value !== slot);
}
function submit() {
    form.transform(data => ({ ...data, ...(props.record ? { _method: 'patch' } : {}) }))
        .post(props.record ? route('construction.update', props.record.id) : route('construction.store'), { forceFormData: true });
}
</script>

<template>
    <Head :title="record ? 'Edit Construction record' : 'Register Construction item'" />
    <AuthenticatedLayout>
        <form class="space-y-5" @submit.prevent="submit">
            <div class="rounded-2xl border bg-white p-5"><p v-if="record?.stock_initialized_at" class="text-sm">Company: <strong>{{ record.company || 'Not assigned' }}</strong></p><CompanyField v-else v-model="form.company" :error="form.errors.company" /></div>
            <header class="rounded-3xl border border-[#d8e7d4] bg-white p-6">
                <img src="/images/dayang-logo.png" alt="Dayang" class="mb-4 h-14 w-auto object-contain" />
                <h1 class="text-2xl font-bold text-[#234222]">{{ record ? 'Edit record #' + record.id : 'Register item' }}</h1>
                <p class="mt-2 text-sm text-slate-600">Construction TEC, Garnet &amp; PPE Register · Miri</p>
                <p class="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">These are recorded balances and historical references—not new stock transactions. For new receipts and write-offs, use Confirm stock movement on the item details page. Use Internal Issue Note for issues, backloads and transfers.</p>
            </header>
            <div v-if="Object.keys(form.errors).length" role="alert" class="rounded-xl bg-red-50 p-4 text-sm text-red-700"><p v-for="(message, key) in form.errors" :key="key">{{ message }}</p></div>
            <section v-for="group in groups" :key="group" class="rounded-3xl border border-[#d8e7d4] bg-white p-6">
                <h2 class="text-lg font-bold text-[#234222]">{{ group }}</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <label v-for="field in visibleFields.filter(field => field.group === group)" :key="field.key">
                        <span class="text-sm font-semibold">{{ field.label }}{{ field.key === 'category' ? ' *' : '' }}</span>
                        <template v-if="classificationKeys.includes(field.key)">
                            <CustomSelect v-if="!customEntry[field.key]" v-model="form[field.key]" class="mt-2 w-full" :aria-label="field.label" :aria-required="field.key === 'category'" @change="classificationChanged(field.key)">
                                <option value="">{{ field.key === 'category' ? 'Select category' : 'Not recorded' }}</option>
                                <option v-for="option in optionsFor(field.key)" :key="option" :value="option">{{ option }}</option>
                            </CustomSelect>
                            <input v-else v-model="form[field.key]" :readonly="!!record?.stock_initialized_at && ['stock_balance', 'unit', 'current_location'].includes(field.key)" class="input input-bordered mt-2 w-full" maxlength="255" :required="field.key === 'category'" :aria-label="field.label" @input="classificationChanged(field.key)" />
                            <button type="button" class="mt-2 text-xs font-semibold text-green-800 underline" @click.prevent="customEntry[field.key] = !customEntry[field.key]">{{ customEntry[field.key] ? 'Choose existing value' : 'Enter a new value' }}</button>
                        </template>
                        <textarea v-else-if="['textarea','private'].includes(field.type)" v-model="form[field.key]" rows="3" :maxlength="10000" class="textarea textarea-bordered mt-2 w-full" />
                        <input v-else v-model="form[field.key]" :readonly="!!record?.stock_initialized_at && ['stock_balance', 'unit', 'current_location'].includes(field.key)" :type="field.type === 'number' ? 'number' : field.type === 'date' ? 'date' : 'text'" :step="field.type === 'number' ? (['unit_price','closing_value'].includes(field.key) ? '0.01' : '0.001') : undefined" :min="field.type === 'number' ? 0 : undefined" :maxlength="field.type === 'text' ? 255 : undefined" class="input input-bordered mt-2 w-full" :required="field.key === 'category'" />
                        <p v-if="field.type === 'private'" class="mt-1 text-xs text-slate-500">Restricted to Miri inventory editors. Encrypted at rest; excluded from general lists and audit values.</p>
                        <p v-if="field.key === 'certificate_due_date'" class="mt-1 text-xs text-slate-500">Imported as the certificate due date. Confirm its meaning with the source owner before relying on expiry alerts.</p>
                    </label>
                </div>
                <div v-for="slot in slotsFor(group)" :key="slot" class="mt-5 rounded-xl border border-[#e1efdc] p-4">
                    <p class="text-sm font-bold">{{ attachmentSlots[slot] }} attachment</p>
                    <div v-if="record?.attachments?.[slot]" class="mt-2 flex flex-wrap items-center gap-3 text-sm">
                        <a :href="route('construction.attachment', { construction: record.id, slot })" target="_blank" rel="noopener" class="text-green-800 underline">Open {{ record.attachments[slot].name }}</a>
                        <label class="flex items-center gap-2"><input v-model="form.remove_attachments" type="checkbox" :value="slot" :disabled="!!form.uploads[slot]" />Remove saved attachment</label>
                    </div>
                    <input type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" class="mt-3 block max-w-full text-sm" @change="selectFile(slot, $event)" />
                    <p class="mt-1 text-xs text-slate-500">One image or PDF per certificate. Maximum 5 MB; images up to 4096 × 4096. Selecting a file replaces the existing attachment.</p>
                </div>
            </section>
            <section class="rounded-3xl border border-[#d8e7d4] bg-white p-6">
                <h2 class="text-lg font-bold">Data review</h2>
                <ul v-if="record?.review_flags?.length" class="mt-3 list-inside list-disc text-sm text-amber-800"><li v-for="flag in record.review_flags" :key="flag">{{ flag }}</li></ul>
                <label v-if="record?.grouping_review_required" class="mt-4 flex gap-2 text-sm"><input v-model="form.grouping_reviewed" type="checkbox" />I reviewed this possible stock-history row. Keep it separate; no merge or stock adjustment is authorized.</label>
                <label class="mt-4 block text-sm">Review note<textarea v-model="form.review_note" maxlength="5000" class="textarea textarea-bordered mt-2 w-full" placeholder="Record your review/corrections; required to acknowledge a grouping flag." /></label>
            </section>
            <div class="flex gap-3"><button :disabled="form.processing" class="btn bg-[#234222] text-white">{{ form.processing ? 'Saving…' : 'Save record' }}</button><Link :href="record ? route('construction.show', record.id) : route('construction.index')" class="btn">Cancel</Link></div>
        </form>
    </AuthenticatedLayout>
</template>
