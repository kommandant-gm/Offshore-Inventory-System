<script setup>
import { computed } from 'vue';
const props = defineProps({ form: Object });
const addRow = () => {
    if (props.form.scaffold_issues.length < 10) props.form.scaffold_issues.push({ destination: '', mr_reference: '', quantity: '', reference: '', document_date: '' });
};
const balances = computed(() => {
    if (props.form.scaffold_total === '' || props.form.scaffold_total == null) return [];
    let remaining = Math.round(Number(props.form.scaffold_total) * 1000);
    return props.form.scaffold_issues.map(row => {
        remaining -= Math.round(Number(row.quantity || 0) * 1000);
        return remaining / 1000;
    });
});
</script>

<template>
    <div class="mt-5 space-y-5 border-t pt-5">
        <label class="block max-w-sm text-sm">Total stock qty (A)
            <input v-model="form.scaffold_total" type="number" min="0" max="999999999.999" step="0.001" :required="form.scaffold_issues.length > 0" class="mt-1 w-full rounded-xl border-slate-200" />
        </label>
        <p class="text-sm text-slate-500">Enter the verified total before the issues listed below. Saving records that opening stock and deducts these issues once. Leave the total blank to verify stock later.</p>
        <div class="overflow-x-auto">
            <h3 class="mb-3 font-semibold">Issued-out details</h3>
            <table class="w-full min-w-[800px] text-left text-sm">
                <thead class="bg-green-50"><tr><th class="p-2">No.</th><th>Issued-out location / project</th><th>MR request</th><th>Qty issued (B)</th><th>Balance stock qty (C)</th><th></th></tr></thead>
                <tbody><tr v-for="(row, index) in form.scaffold_issues" :key="index" class="border-b">
                    <td class="p-2">{{ index + 1 }}</td>
                    <td class="p-1"><input v-model="row.destination" required maxlength="255" :aria-label="`Location / project row ${index + 1}`" class="w-full rounded-lg border-slate-200" /></td>
                    <td class="p-1"><input v-model="row.mr_reference" maxlength="255" :aria-label="`MR request row ${index + 1}`" class="w-full rounded-lg border-slate-200" /></td>
                    <td class="p-1"><input v-model="row.quantity" required type="number" min="0.001" max="999999999.999" step="0.001" :aria-label="`Quantity row ${index + 1}`" class="w-28 rounded-lg border-slate-200" /></td>
                    <td class="p-2 font-semibold" :class="balances[index] < 0 ? 'text-red-700' : 'text-green-800'">{{ balances[index] ?? 'Enter total stock' }}</td>
                    <td><button type="button" class="text-red-700" @click="form.scaffold_issues.splice(index, 1)">Remove row {{ index + 1 }}</button></td>
                </tr></tbody>
            </table>
        </div>
        <button type="button" class="btn" :disabled="form.scaffold_issues.length >= 10 || form.processing" @click="addRow">Add issue / COG row ({{ form.scaffold_issues.length }}/10)</button>
        <p class="text-xs text-slate-500">Balance = total stock minus all quantities issued so far. The same row number links each issue to its COG entry.</p>
        <div v-if="form.scaffold_issues.length" class="overflow-x-auto">
            <h3 class="mb-3 font-semibold">COG information</h3>
            <table class="w-full min-w-[500px] text-left text-sm">
                <thead class="bg-green-50"><tr><th class="p-2">No.</th><th>Issue-out COG No.</th><th>Issue-out date</th></tr></thead>
                <tbody><tr v-for="(row, index) in form.scaffold_issues" :key="index" class="border-b">
                    <td class="p-2">{{ index + 1 }}</td>
                    <td class="p-1"><input v-model="row.reference" required maxlength="255" :aria-label="`COG row ${index + 1}`" class="w-full rounded-lg border-slate-200" /></td>
                    <td class="p-1"><input v-model="row.document_date" required type="date" :aria-label="`Issue-out date row ${index + 1}`" class="w-full rounded-lg border-slate-200" /></td>
                </tr></tbody>
            </table>
        </div>
    </div>
</template>
