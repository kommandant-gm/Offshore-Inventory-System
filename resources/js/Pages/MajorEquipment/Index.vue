<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CustomSelect from '@/Components/CustomSelect.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({ equipment: Object, summary: Object, filters: Object, categoryOptions: Array, section1Options: Array, section2Options: Array, locationOptions: Array, issueOutLocationOptions: Array, statusOptions: Array, canEdit: Boolean });
const form = reactive({ ...props.filters });
const activeFilters = computed(() => Object.values(form).filter((value) => value !== '' && value !== null).length);
const applyFilters = () => router.get(route('major-equipment.index'), form, { preserveState: true, preserveScroll: true, replace: true });
const clearFilters = () => { Object.keys(form).forEach((key) => { form[key] = ''; }); applyFilters(); };
const toggleMissing = () => { form.missing_details = form.missing_details === 'missing' ? '' : 'missing'; applyFilters(); };
</script>
<template>
    <Head title="Miri Inventory Register" />
    <AuthenticatedLayout>
        <section class="space-y-6">
            <header class="flex flex-wrap items-end justify-between gap-4 rounded-[2rem] border border-[#d8e7d4] bg-white p-7 shadow-sm"><div><p class="text-xs font-bold uppercase tracking-[.25em] text-[#4f9f4a]">Miri Inventory</p><h1 class="mt-2 text-3xl font-bold text-[#234222]">Miri Inventory Register</h1><p class="mt-2 text-sm text-[#60745d]">Major equipment, certificates, locations and COG tracking.</p></div><div v-if="canEdit" class="flex gap-2"><Link :href="route('major-equipment.import')" class="rounded-full border border-[#4f9f4a] px-5 py-3 text-sm font-bold text-[#2f7d32]">Import CSV</Link><Link :href="route('major-equipment.create')" class="rounded-full bg-[#4f9f4a] px-5 py-3 text-sm font-bold text-white">Register equipment</Link></div></header>
            <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5"><div v-for="card in [{ label: 'Total equipment', value: summary.total, color: 'border-[#4f9f4a]' }, { label: 'In use', value: summary.in_use, color: 'border-blue-400' }, { label: 'Standby', value: summary.standby, color: 'border-emerald-400' }, { label: 'Under repair', value: summary.under_repair, color: 'border-amber-400' }, { label: 'Missing details', value: summary.missing_details, color: 'border-orange-400' }]" :key="card.label" class="rounded-2xl border border-[#d8e7d4] border-t-4 bg-white p-5 shadow-sm" :class="card.color"><p class="text-xs font-bold uppercase tracking-wider text-[#60745d]">{{ card.label }}</p><p class="mt-2 text-3xl font-black text-[#234222]">{{ card.value }}</p></div></section>
            <form class="rounded-[1.7rem] border border-[#d8e7d4] bg-white p-5 shadow-sm sm:p-6" @submit.prevent="applyFilters">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="font-bold text-[#234222]">Filter equipment</h2>
                    <span v-if="activeFilters" class="rounded-full bg-[#e8f5e4] px-2.5 py-1 text-xs font-bold text-[#2f7d32]">{{ activeFilters }} active</span>
                </div>
                <p class="mt-1 text-sm text-[#60745d]">Search equipment or use the filters below to narrow the register.</p>

                <div class="mt-5">
                    <label for="equipment-search" class="filter-label">Search equipment</label>
                    <input id="equipment-search" v-model.trim="form.search" type="search" class="filter-input" placeholder="Tag, serial, model, COG or description" />
                </div>
                <div class="mt-5 grid grid-cols-1 gap-x-5 gap-y-5 sm:grid-cols-2 xl:grid-cols-3">
                    <div class="min-w-0">
                        <label for="equipment-category" class="filter-label">Category</label>
                        <CustomSelect id="equipment-category" v-model="form.category" class="filter-input">
                            <option value="">All categories</option>
                            <option v-for="option in categoryOptions" :key="option" :value="option">{{ option }}</option>
                        </CustomSelect>
                    </div>
                    <div class="min-w-0">
                        <label for="equipment-section_1" class="filter-label">Subcategory 1</label>
                        <CustomSelect id="equipment-section_1" v-model="form.section_1" class="filter-input">
                            <option value="">All subcategory 1</option>
                            <option v-for="option in section1Options" :key="option" :value="option">{{ option }}</option>
                        </CustomSelect>
                    </div>
                    <div class="min-w-0">
                        <label for="equipment-section_2" class="filter-label">Subcategory 2</label>
                        <CustomSelect id="equipment-section_2" v-model="form.section_2" class="filter-input">
                            <option value="">All subcategory 2</option>
                            <option v-for="option in section2Options" :key="option" :value="option">{{ option }}</option>
                        </CustomSelect>
                    </div>
                    <div class="min-w-0">
                        <label for="equipment-location" class="filter-label">Current location</label>
                        <CustomSelect id="equipment-location" v-model="form.location" class="filter-input">
                            <option value="">All locations</option>
                            <option v-for="option in locationOptions" :key="option" :value="option">{{ option }}</option>
                        </CustomSelect>
                    </div>
                    <div class="min-w-0">
                        <label for="equipment-issue_out_location" class="filter-label">Issue-out location</label>
                        <CustomSelect id="equipment-issue_out_location" v-model="form.issue_out_location" class="filter-input">
                            <option value="">All issue-out locations</option>
                            <option v-for="option in issueOutLocationOptions" :key="option" :value="option">{{ option }}</option>
                        </CustomSelect>
                    </div>
                    <div class="min-w-0">
                        <label for="equipment-status" class="filter-label">Status</label>
                        <CustomSelect id="equipment-status" v-model="form.status" class="filter-input">
                            <option value="">All statuses</option>
                            <option v-for="option in statusOptions" :key="option" :value="option">{{ option }}</option>
                        </CustomSelect>
                    </div>
                </div>

                <div class="mt-6 flex flex-col gap-5 border-t border-[#edf3eb] pt-5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <span class="filter-label">Data quality</span>
                        <button type="button" :aria-pressed="form.missing_details === 'missing'" class="flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border px-4 py-2 text-sm font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#4f9f4a] sm:w-auto" :class="form.missing_details === 'missing' ? 'border-amber-400 bg-amber-50 text-amber-800' : 'border-[#d8e7d4] bg-white text-[#60745d] hover:bg-[#f4f9f2]'" @click="toggleMissing">
                            <span class="h-2 w-2 shrink-0 rounded-full" :class="form.missing_details === 'missing' ? 'bg-amber-500' : 'bg-slate-300'"></span>
                            {{ form.missing_details === 'missing' ? 'Missing details only ?' : 'Show missing details' }}
                        </button>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row sm:self-end">
                        <button v-if="activeFilters" type="button" class="min-h-11 rounded-xl border border-[#d8e7d4] px-5 py-2 text-sm font-semibold text-[#60745d] transition hover:bg-[#f4f9f2] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#4f9f4a]" @click="clearFilters">Clear all</button>
                        <button type="submit" class="min-h-11 rounded-xl bg-[#4f9f4a] px-6 py-2 text-sm font-bold text-white transition hover:bg-[#40863c] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#4f9f4a]">Apply filters</button>
                    </div>
                </div>
            </form>
            <div class="overflow-hidden rounded-[1.7rem] border border-[#d8e7d4] bg-white"><div class="border-b border-[#edf3eb] px-5 py-3 text-sm text-[#60745d]"><strong class="text-[#234222]">{{ equipment.total }}</strong> equipment found <span v-if="equipment.total">· Showing {{ equipment.from }}–{{ equipment.to }}</span></div><div class="overflow-x-auto"><table class="table"><thead><tr><th>Tag No.</th><th>Description</th><th>Category / Subcategory</th><th>Current location</th><th>Issue-out location</th><th>Status</th><th>Actions</th></tr></thead><tbody><tr v-for="item in equipment.data" :key="item.id"><td><Link class="font-bold text-[#2f7d32]" :href="route('major-equipment.show', item.id)">{{ item.tag_no || '-' }}</Link><div class="text-xs text-slate-500">{{ item.serial_no || '-' }}</div></td><td>{{ item.description || '-' }}<div class="text-xs text-slate-500">{{ item.model_brand || '-' }}</div></td><td>{{ item.category || '-' }}<div class="text-xs text-slate-500">{{ item.section_1 || '-' }} / {{ item.section_2 || '-' }}</div></td><td>{{ item.current_location || '-' }}</td><td>{{ item.issue_out_location || '-' }}</td><td>{{ item.status || '-' }}</td><td><div class="flex gap-2"><Link class="btn btn-xs" :href="route('major-equipment.show', item.id)">View</Link><Link v-if="canEdit" class="btn btn-xs border-[#cfe6c8] bg-white" :href="route('major-equipment.edit', item.id)">Edit</Link></div></td></tr><tr v-if="!equipment.data.length"><td colspan="7" class="py-12 text-center text-slate-500">No Miri equipment matches the selected filters.</td></tr></tbody></table></div></div>
            <div class="flex flex-wrap gap-2"><Link v-for="link in equipment.links" :key="link.label" v-html="link.label" :href="link.url || '#'" class="btn btn-sm" :class="{ 'btn-disabled': !link.url, 'btn-success text-white': link.active }" /></div>
        </section>
    </AuthenticatedLayout>
</template>
<style scoped>
.filter-label { @apply mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#60745d]; }
:deep(.filter-input) { @apply h-11 min-w-0 w-full rounded-xl border border-[#d8e7d4] bg-white px-3 py-2 text-sm text-[#234222] focus:border-[#4f9f4a] focus:ring-[#4f9f4a]; }
</style>
