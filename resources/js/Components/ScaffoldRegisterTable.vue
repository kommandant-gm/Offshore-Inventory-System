<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import DeleteRegisterItem from '@/Components/DeleteRegisterItem.vue';
const props = defineProps({ records: Array, columns: Array, canEdit: Boolean });
const selectedIds = defineModel('selectedIds', { default: () => [] });
const columns = computed(() => props.columns.filter(column => !/^issue_\d+_/.test(column.key)));
const allSelected = computed({
    get: () => props.records.length > 0 && props.records.every(row => selectedIds.value.includes(row.id)),
    set: value => { selectedIds.value = value ? props.records.map(row => row.id) : []; },
});
const value = (raw, type) => raw === null || raw === undefined || raw === '' ? 'Not recorded' : type === 'date' ? String(raw).slice(0, 10) : raw;
</script>

<template>
    <div class="overflow-x-auto">
        <p class="px-5 py-3 text-xs text-slate-500">Stock is shown per yard. Opening stock and total issued are historical totals; current balances also include receipts, returns, transfers and corrections. Unverified means no opening stock has been confirmed.</p>
        <table class="table">
            <thead><tr>
                <th v-if="canEdit"><input v-model="allSelected" type="checkbox" aria-label="Select all scaffold records on this page" /></th>
                <th>Record</th><th v-for="column in columns" :key="column.key" class="min-w-36">{{ column.label }}</th><th>Issue-out / COG details</th><th>Actions</th>
            </tr></thead>
            <tbody>
                <tr v-for="item in records" :key="item.id">
                    <td v-if="canEdit"><input v-model="selectedIds" type="checkbox" :value="item.id" :aria-label="`Select record #${item.id}`" /></td>
                    <td><Link :href="route('major-equipment.show', item.id)" class="font-bold text-green-800">#{{ item.id }}</Link><p v-if="item.tag_no" class="mt-1 text-xs">{{ item.tag_no }}</p></td>
                    <td v-for="column in columns" :key="column.key" class="whitespace-pre-wrap">{{ value(item[column.key], column.type) }}</td>
                    <td class="min-w-64 align-top">
                        <details v-if="item.scaffold_issues.length">
                            <summary class="cursor-pointer font-semibold text-green-800">{{ item.scaffold_issue_count > 10 ? 'Latest 10 of ' + item.scaffold_issue_count : item.scaffold_issue_count }} confirmed issues</summary>
                            <table class="mt-3 text-xs"><thead><tr><th>No.</th><th>Yard</th><th>Location / project</th><th>Contract</th><th>MR request</th><th>Qty issued</th><th>Balance after issue</th><th>COG No.</th><th>Issue-out date</th></tr></thead>
                                <tbody><tr v-for="(issue, index) in item.scaffold_issues" :key="index"><td>{{ index + 1 }}</td><td>{{ issue.yard }}</td><td>{{ value(issue.destination) }}</td><td>{{ value(issue.contract) }}</td><td>{{ value(issue.mr_reference) }}</td><td>{{ issue.quantity }} {{ item.unit }}</td><td>{{ issue.balance_after }}</td><td>{{ value(issue.reference) }}</td><td>{{ issue.document_date }}</td></tr></tbody>
                            </table>
                        </details>
                        <p v-else class="text-xs text-slate-500">No confirmed issues</p>
                        <details v-if="item.issue_out_location || item.issue_out_cog_no || item.mr_request" class="mt-3 text-xs"><summary class="cursor-pointer">Original register references</summary><p>Location: {{ value(item.issue_out_location) }}</p><p>MR: {{ value(item.mr_request) }}</p><p>COG: {{ value(item.issue_out_cog_no) }}</p><p>Date: {{ item.issue_out_cog_date?.slice(0, 10) || 'Not recorded' }}</p></details>
                    </td>
                    <td><div class="flex gap-2"><Link class="btn btn-xs" :href="route('major-equipment.show', item.id)">View / full history</Link><Link v-if="canEdit" class="btn btn-xs" :href="route('major-equipment.edit', item.id)">Edit / stock</Link><DeleteRegisterItem v-if="canEdit" register="major" :item="item" @deleted="id => selectedIds = selectedIds.filter(value => value !== id)" /></div></td>
                </tr>
                <tr v-if="!records.length"><td :colspan="columns.length + 3 + (canEdit ? 1 : 0)" class="p-8 text-center text-slate-500">No scaffolding records match these filters.</td></tr>
            </tbody>
        </table>
    </div>
</template>
