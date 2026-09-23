<script setup>
import CustomSelect from '@/Components/CustomSelect.vue';
import CompanyField from '@/Components/CompanyField.vue';
import BulkCompanyAssignment from '@/Components/BulkCompanyAssignment.vue';
import { useCompanySelection } from '@/Composables/useCompanySelection';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';
const props = defineProps({ records: Object, summary: Object, filters: Object, options: Object, qualityOptions: Array, canEdit: Boolean });
const filters = reactive(Object.fromEntries(['company', 'search', 'category', 'section_1', 'section_2', 'location', 'quality'].map(key => [key, props.filters[key] || ''])));
watch(() => props.filters, value => Object.keys(filters).forEach(key => filters[key] = value[key] || ''));
const apply = () => router.get(route('construction.index'), filters, { preserveState: true, preserveScroll: true });
const clear = () => { Object.keys(filters).forEach(key => filters[key] = ''); apply(); };
const { selectedIds, allSelected } = useCompanySelection(() => props.records.data);
</script>

<template>
    <Head title="Construction TEC, Garnet & PPE Register" />
    <AuthenticatedLayout>
        <section class="space-y-6">
            <header class="rounded-3xl border border-[#d8e7d4] bg-white p-6">
                <p class="text-xs font-bold uppercase tracking-widest text-green-700">Miri Inventory</p>
                <h1 class="mt-2 text-2xl font-bold text-[#234222]">Construction TEC, Garnet &amp; PPE Register</h1>
                <p class="mt-3 text-sm text-slate-600">Recorded stock snapshots, tagged tools and original movement information. Imported rows remain separate; historical quantities are not applied again.</p>
                <div v-if="canEdit" class="mt-5 flex flex-wrap gap-3">
                    <Link :href="route('construction.create')" class="btn bg-[#234222] text-white">Register item</Link>
                    <Link :href="route('construction.import')" class="btn">Import CSV</Link>
                </div>
            </header>
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div v-for="[label, value] in [['Source records', summary.total], ['Needs review', summary.review], ['Duplicate-tag records', summary.duplicates], ['Certificate dates recorded', summary.dated_certificates]]" :key="label" class="rounded-2xl border border-[#d8e7d4] bg-white p-5">
                    <p class="text-xs text-slate-500">{{ label }}</p><p class="mt-2 text-2xl font-bold text-[#234222]">{{ value }}</p>
                </div>
            </div>
            <form class="grid gap-3 rounded-3xl border border-[#d8e7d4] bg-white p-5 md:grid-cols-3" @submit.prevent="apply">
                <p class="text-xs text-slate-500 md:col-span-3">Apply filters to update results and available options. Options follow the search and other selected filters.</p>
                <CompanyField v-model="filters.company" filter /><label class="md:col-span-2"><span class="text-xs font-semibold">Search</span><input v-model="filters.search" class="input input-bordered mt-1 w-full" placeholder="Description, tag, model, location or rack" /></label>
                <label><span class="text-xs font-semibold">Data quality</span><CustomSelect v-model="filters.quality" class="select select-bordered mt-1 w-full"><option value="">All records</option><option v-if="qualityOptions.includes('review') || filters.quality === 'review'" :disabled="!qualityOptions.includes('review')" value="review">Needs review</option><option v-if="qualityOptions.includes('duplicates') || filters.quality === 'duplicates'" :disabled="!qualityOptions.includes('duplicates')" value="duplicates">Duplicate tags</option></CustomSelect></label>
                <label v-for="[key,label] in [['category','Category'],['section_1','Section 1'],['section_2','Section 2'],['location','Current location']]" :key="key">
                    <span class="text-xs font-semibold">{{ label }}</span><CustomSelect v-model="filters[key]" class="select select-bordered mt-1 w-full"><option value="">All</option><option v-if="filters[key] && !options[key].includes(filters[key])" :value="filters[key]" disabled>{{ filters[key] }} (no matching records)</option><option v-for="value in options[key]" :key="value">{{ value }}</option></CustomSelect>
                </label>
                <div class="flex items-end gap-2"><button class="btn bg-[#4f9f4a] text-white">Apply filters</button><button type="button" class="btn" @click="clear">Clear</button></div>
            </form>
            <BulkCompanyAssignment v-if="canEdit" :ids="selectedIds" register="construction" @assigned="selectedIds = []" />
            <div class="overflow-hidden rounded-3xl border border-[#d8e7d4] bg-white">
                <p class="border-b px-5 py-4 text-sm">Showing {{ records.from || 0 }}–{{ records.to || 0 }} of {{ records.total }} records. Quantities use their own units; no mixed-unit total.</p>
                <div class="overflow-x-auto"><table class="table">
                    <thead><tr><th v-if="canEdit"><input v-model="allSelected" type="checkbox" aria-label="Select all items on this page" /></th><th>Company</th><th>Item / Tag</th><th>Classification</th><th>Recorded balance</th><th>Location / Rack</th><th>Certificate due date</th><th>Review</th><th>Actions</th></tr></thead>
                    <tbody><tr v-for="item in records.data" :key="item.id"><td v-if="canEdit"><input v-model="selectedIds" type="checkbox" :value="item.id" :aria-label="'Select record #' + item.id" /></td><td>{{ item.company || 'Not assigned' }}</td>
                        <td><Link :href="route('construction.show', item.id)" class="font-bold text-green-800">{{ item.description || 'Description not recorded' }}</Link><p class="text-xs text-slate-500">#{{ item.id }} · {{ item.tag_no || 'No tag recorded' }}</p></td>
                        <td>{{ item.category }}<p class="text-xs text-slate-500">{{ [item.section_1, item.section_2].filter(Boolean).join(' / ') }}</p></td>
                        <td>{{ item.stock_balance ?? 'Not recorded' }}<p class="text-xs text-slate-500">{{ item.unit || 'Unit not recorded' }}</p></td>
                        <td>{{ item.current_location || 'Not recorded' }}<p class="text-xs text-slate-500">{{ item.storage_rack || 'Rack not recorded' }}</p></td>
                        <td>{{ item.certificate_due_date || 'Not recorded' }}</td>
                        <td><p v-if="item.duplicate_count > 1" class="text-xs font-semibold text-amber-700">Duplicate tag ({{ item.duplicate_count }})</p><p v-if="item.needs_review" class="text-xs text-amber-700">Details need review</p><span v-if="!item.needs_review && item.duplicate_count <= 1">—</span></td>
                        <td><div class="flex gap-2"><Link :href="route('construction.show', item.id)" class="btn btn-xs">View</Link><Link v-if="canEdit" :href="route('construction.edit', item.id)" class="btn btn-xs">Edit</Link></div></td>
                    </tr><tr v-if="!records.data.length"><td :colspan="8 + (canEdit ? 1 : 0)" class="py-10 text-center text-slate-500">No matching records.</td></tr></tbody>
                </table></div>
                <nav class="flex items-center justify-between gap-3 border-t p-4" aria-label="Construction register pages">
                    <Link v-if="records.prev_page_url" :href="records.prev_page_url" class="btn btn-sm" preserve-scroll>Previous</Link><span v-else class="btn btn-sm btn-disabled">Previous</span>
                    <span class="text-sm">Page {{ records.current_page }} of {{ records.last_page }}</span>
                    <Link v-if="records.next_page_url" :href="records.next_page_url" class="btn btn-sm" preserve-scroll>Next</Link><span v-else class="btn btn-sm btn-disabled">Next</span>
                </nav>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
