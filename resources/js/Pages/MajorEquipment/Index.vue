<script setup>
import { useLiveFilterOptions } from '@/Composables/useLiveFilterOptions';
import LiveFilterNotice from '@/Components/LiveFilterNotice.vue';
import DeleteRegisterItem from '@/Components/DeleteRegisterItem.vue';
import CompanyField from '@/Components/CompanyField.vue';
import BulkCompanyAssignment from '@/Components/BulkCompanyAssignment.vue';
import { useCompanySelection } from '@/Composables/useCompanySelection';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CustomSelect from '@/Components/CustomSelect.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';

const props = defineProps({
    equipment: Object, summary: Object, tabCounts: Object, filters: Object,
    descriptionOptions: Array, categoryOptions: Array, section1Options: Array, section2Options: Array,
    locationOptions: Array, issueOutLocationOptions: Array, statusOptions: Array, canEdit: Boolean,
});
const form = reactive({ ...props.filters });
watch(() => props.filters, value => Object.assign(form, value));
const cargo = computed(() => props.filters.inventory_type === 'cargo');
const typeLabel = computed(() => cargo.value ? 'Cargo' : 'Machinery');
const applyFilters = () => router.get(route('major-equipment.index'), form, { preserveState: true, preserveScroll: true, replace: true });
const clearFilters = () => { Object.keys(form).filter(k => k !== 'inventory_type').forEach(k => form[k] = ''); };
const qualityFilter = (value) => { form.quality = form.quality === value ? '' : value; };
const missing = (item) => ['tag_no', 'description', 'current_location'].filter(key => !String(item[key] ?? '').trim());
const { options: liveOptions, loading: optionsLoading, error: optionsError, retry: retryOptions } = useLiveFilterOptions(
    () => route('major-equipment.index'), () => ({ ...form }),
    () => ({ descriptionOptions: props.descriptionOptions, categoryOptions: props.categoryOptions, section1Options: props.section1Options, section2Options: props.section2Options, locationOptions: props.locationOptions, issueOutLocationOptions: props.issueOutLocationOptions, statusOptions: props.statusOptions, qualityCounts: props.summary }),
);
const selectors = computed(() => [
    ...(cargo.value ? [['description', 'Description', liveOptions.value.descriptionOptions]] : []),
    ['category', 'Category', liveOptions.value.categoryOptions], ['section_1', 'Section', liveOptions.value.section1Options],
    ['section_2', 'Subcategory', liveOptions.value.section2Options], ['location', 'Current location', liveOptions.value.locationOptions],
    ['issue_out_location', 'Issue-out location', liveOptions.value.issueOutLocationOptions], ['status', 'Status', liveOptions.value.statusOptions],
]);
const { selectedIds, allSelected } = useCompanySelection(() => props.equipment.data);
</script>

<template>
    <Head title="Miri Inventory Register" />
    <AuthenticatedLayout>
        <section class="space-y-6">
            <header class="flex flex-wrap items-end justify-between gap-4 rounded-[2rem] border border-[#d8e7d4] bg-white p-7 shadow-sm">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.25em] text-[#4f9f4a]">Miri Inventory · Major Equipment</p>
                    <h1 class="mt-2 text-3xl font-bold text-[#234222]">Miri Inventory Register</h1>
                    <p class="mt-2 text-sm text-[#60745d]">Equipment, certificates, locations and COG tracking.</p>
                </div>
                <div v-if="canEdit" class="flex flex-wrap gap-2">
                    <Link :href="route('major-equipment.import', { inventory_type: filters.inventory_type })" class="btn border-[#4f9f4a] text-[#2f7d32]">Import {{ typeLabel }} CSV</Link>
                    <Link :href="route('major-equipment.create', { inventory_type: filters.inventory_type })" class="btn bg-[#4f9f4a] text-white">Register {{ typeLabel }}</Link>
                </div>
            </header>
            <nav aria-label="Equipment type" class="flex w-fit gap-2 rounded-2xl border border-[#d8e7d4] bg-white p-2">
                <Link v-for="tab in ['machinery', 'cargo']" :key="tab" :href="route('major-equipment.index', { inventory_type: tab })"
                    :aria-current="filters.inventory_type === tab ? 'page' : undefined"
                    class="rounded-xl px-6 py-3 font-bold capitalize"
                    :class="filters.inventory_type === tab ? 'bg-[#234222] text-white' : 'text-[#60745d] hover:bg-green-50'">
                    {{ tab }} <span class="ml-2 rounded-full bg-black/5 px-2 py-0.5 text-xs">{{ tabCounts[tab] || 0 }}</span>
                </Link>
            </nav>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="card in [{label:'Records',value:summary.total},{label:'In use',value:summary.in_use},{label:'Standby',value:summary.standby},{label:'Under repair',value:summary.under_repair}]" :key="card.label" class="rounded-2xl border border-[#d8e7d4] bg-white p-5">
                    <p class="text-xs font-bold uppercase text-[#60745d]">{{ card.label }}</p><p class="mt-2 text-3xl font-bold text-[#234222]">{{ card.value }}</p>
                </div>
            </div>
            <p v-if="cargo" class="text-sm text-slate-500">Counts represent records, not units. Source statuses are preserved.</p>
            <form class="rounded-[1.7rem] border border-[#d8e7d4] bg-white p-5" @submit.prevent="applyFilters">
                <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="font-bold text-[#234222]">Filter {{ typeLabel }}</h2>
                    <div class="flex gap-2"><button type="button" class="btn btn-sm" @click="clearFilters">Clear filters</button><button class="btn btn-sm bg-[#4f9f4a] text-white">Apply filters</button></div>
                </div>
                <LiveFilterNotice :loading="optionsLoading" :error="optionsError" @retry="retryOptions" class="mt-3" />
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <CompanyField v-model="form.company" filter /><label class="sm:col-span-2 lg:col-span-3"><span class="filter-label">Search</span><input v-model.trim="form.search" class="filter-input" type="search" placeholder="Tag, description, model, dimensions, location or COG" /></label>
                    <label v-for="[key,label,options] in selectors" :key="key"><span class="filter-label">{{ label }}</span><CustomSelect v-model="form[key]" class="filter-input"><option value="">All</option><option v-if="form[key] && !options.includes(form[key])" :value="form[key]" disabled>{{ form[key] }} (no matching records)</option><option v-for="option in options" :key="option" :value="option">{{ option }}</option></CustomSelect></label>
                </div>
                <div class="mt-5 border-t border-[#edf3eb] pt-4">
                    <h3 class="filter-label">Data quality</h3>
                    <p class="mb-3 text-xs text-slate-500">Issue counts follow the selections in this filter card. Duplicate tags are checked across Machinery and Cargo in Miri.</p>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="[key,label,count] in [['duplicates','Duplicate tags',liveOptions.qualityCounts.duplicates],['missing','Missing details',liveOptions.qualityCounts.missing_details],['warnings','Import warnings',liveOptions.qualityCounts.warnings]]"
                            :key="key" type="button" class="rounded-xl border px-4 py-2 text-sm font-semibold"
                            :aria-pressed="form.quality === key" :class="form.quality === key ? 'border-amber-500 bg-amber-100 text-amber-900' : 'border-[#d8e7d4] text-[#60745d]'"
                            @click="qualityFilter(key)">{{ label }} <span class="ml-2">{{ count }}</span></button>
                    </div>
                </div>
            </form>
            <BulkCompanyAssignment v-if="canEdit" :ids="selectedIds" :register="filters.inventory_type" @assigned="selectedIds = []" />
            <div class="overflow-hidden rounded-[1.7rem] border border-[#d8e7d4] bg-white">
                <div class="border-b border-[#edf3eb] px-5 py-3 text-sm text-[#60745d]"><strong>{{ equipment.total }}</strong> {{ typeLabel }} records found <span v-if="equipment.total">· Showing {{ equipment.from }}–{{ equipment.to }}</span></div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th v-if="canEdit"><input v-model="allSelected" type="checkbox" aria-label="Select all items on this page" /></th><th>Company</th><th>Tag No.</th><th>Description</th><th>Category / Subcategory</th>
                            <template v-if="cargo"><th>Dimensions / Model</th><th>Tonnage</th></template>
                            <th>Current location</th><th>Issue-out location</th><th>Status</th><th>Actions</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="item in equipment.data" :key="item.id" :class="Number(item.duplicate_count) > 1 ? 'bg-amber-50/70' : ''"><td v-if="canEdit"><input v-model="selectedIds" type="checkbox" :value="item.id" :aria-label="'Select record #' + item.id" /></td><td>{{ item.company || 'Not assigned' }}</td>
                                <td>
                                    <Link class="font-bold text-[#2f7d32]" :href="route('major-equipment.show', item.id)">{{ item.tag_no || 'No tag' }}</Link>
                                    <p class="text-xs text-slate-400">Record #{{ item.id }}</p>
                                    <p v-if="!cargo" class="text-xs text-slate-500">{{ item.serial_no || '' }}</p>
                                    <Link v-if="Number(item.duplicate_count) > 1" class="mt-2 block w-fit rounded-full bg-amber-100 px-2 py-1 text-xs font-bold text-amber-800"
                                        :href="route('major-equipment.show', item.id)">Duplicate tag · {{ item.duplicate_count }} records</Link>
                                    <span v-if="missing(item).length" class="mt-1 block text-xs text-orange-700" :title="missing(item).join(', ')">Missing details</span>
                                    <Link v-if="item.import_warnings?.length" :href="route('major-equipment.show', item.id)" class="mt-1 block text-xs text-red-700">Import warnings</Link>
                                </td>
                                <td>{{ item.description || 'Not recorded' }}<p v-if="!cargo" class="text-xs text-slate-500">{{ item.model_brand || '' }}</p></td>
                                <td>{{ item.category }}<p class="text-xs text-slate-500">{{ item.section_1 || '-' }} / {{ item.section_2 || '-' }}</p></td>
                                <template v-if="cargo"><td>{{ item.size_model || '-' }}</td><td>{{ item.size_ton || '-' }}</td></template>
                                <td>{{ item.current_location || '-' }}</td><td>{{ item.issue_out_location || '-' }}</td><td>{{ item.status || 'Not recorded' }}</td>
                                <td><div class="flex gap-2"><Link class="btn btn-xs" :href="route('major-equipment.show', item.id)">View</Link><Link v-if="canEdit" class="btn btn-xs" :href="route('major-equipment.edit', item.id)">Edit</Link><DeleteRegisterItem v-if="canEdit" register="major" :item="item" @deleted="id => selectedIds = selectedIds.filter(selected => selected !== id)" /></div></td>
                            </tr>
                            <tr v-if="!equipment.data.length"><td :colspan="(cargo ? 10 : 8) + (canEdit ? 1 : 0)" class="py-12 text-center text-slate-500">No {{ typeLabel }} records match these filters.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="flex flex-wrap gap-2"><Link v-for="link in equipment.links" :key="link.label" :href="link.url || '#'" class="btn btn-sm" :class="{ 'btn-disabled': !link.url, 'bg-[#234222] text-white': link.active }" v-html="link.label" /></div>
        </section>
    </AuthenticatedLayout>
</template>
<style scoped>
.filter-label { @apply mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#60745d]; }
.filter-input { @apply w-full rounded-xl border-[#d8e7d4] text-sm focus:border-[#4f9f4a] focus:ring-[#4f9f4a]; }
</style>
