<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CustomSelect from '@/Components/CustomSelect.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
    rentals: Object, summary: Object, filters: Object, categoryOptions: Array, section1Options: Array,
    section2Options: Array, locationOptions: Array, supplierOptions: Array, statusOptions: Array, canEdit: Boolean,
});
const form = reactive({ ...props.filters });
const activeFilters = computed(() => Object.values(form).filter((value) => value !== '' && value !== null).length);
const applyFilters = () => router.get(route('miri-rental.index'), form, { preserveState: true, preserveScroll: true, replace: true });
const clearFilters = () => { Object.keys(form).forEach((key) => { form[key] = ''; }); applyFilters(); };
const toggleMissing = () => { form.missing_details = form.missing_details === 'missing' ? '' : 'missing'; applyFilters(); };
</script>
<template>
    <Head title="Miri Rental Register" />
    <AuthenticatedLayout>
        <section class="space-y-6">
            <header class="flex flex-wrap items-end justify-between gap-4 rounded-[2rem] border border-[#d8e7d4] bg-white p-7 shadow-sm">
                <div><p class="text-xs font-bold uppercase tracking-[.25em] text-[#4f9f4a]">Miri Inventory</p><h1 class="mt-2 text-3xl font-bold text-[#234222]">Rental Register</h1><p class="mt-2 text-sm text-[#60745d]">Rental equipment, COG movements, certificates, suppliers and due dates.</p></div>
                <div v-if="canEdit" class="flex gap-2"><Link :href="route('miri-rental.import')" class="rounded-full border border-[#4f9f4a] px-5 py-3 text-sm font-bold text-[#2f7d32]">Import Rental CSV</Link><Link :href="route('miri-rental.create')" class="rounded-full bg-[#4f9f4a] px-5 py-3 text-sm font-bold text-white">Register rental</Link></div>
            </header>
            <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div v-for="card in [{label:'Total rentals',value:summary.total},{label:'On hire',value:summary.on_hire},{label:'Issued',value:summary.issued},{label:'Backloaded',value:summary.backload},{label:'Overdue',value:summary.overdue}]" :key="card.label" class="rounded-2xl border border-[#d8e7d4] border-t-4 border-t-[#4f9f4a] bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wider text-[#60745d]">{{ card.label }}</p><p class="mt-2 text-3xl font-black text-[#234222]">{{ card.value }}</p></div>
            </section>
            <form class="rounded-[1.7rem] border border-[#d8e7d4] bg-white p-5 shadow-sm" @submit.prevent="applyFilters">
                <div class="flex flex-wrap items-center justify-between gap-3"><div><h2 class="font-bold text-[#234222]">Filter rentals</h2><p class="mt-1 text-xs text-[#7f9a7a]">Search and narrow the Rental register.</p></div><div class="flex gap-2"><button v-if="activeFilters" type="button" class="rounded-xl border border-[#d8e7d4] px-4 py-2 text-sm font-semibold text-[#60745d]" @click="clearFilters">Clear all</button><button type="submit" class="rounded-xl bg-[#4f9f4a] px-5 py-2 text-sm font-bold text-white">Apply filters</button></div></div>
                <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
                    <label class="sm:col-span-2"><span class="filter-label">Search</span><input v-model.trim="form.search" type="search" class="filter-input" placeholder="Serial, description, supplier, COG or project" /></label>
                    <label><span class="filter-label">Category</span><CustomSelect v-model="form.category" class="filter-input"><option value="">All categories</option><option v-for="option in categoryOptions" :key="option" :value="option">{{ option }}</option></CustomSelect></label>
                    <label><span class="filter-label">Subcategory 1</span><CustomSelect v-model="form.section_1" class="filter-input"><option value="">All subcategory 1</option><option v-for="option in section1Options" :key="option" :value="option">{{ option }}</option></CustomSelect></label>
                    <label><span class="filter-label">Subcategory 2</span><CustomSelect v-model="form.section_2" class="filter-input"><option value="">All subcategory 2</option><option v-for="option in section2Options" :key="option" :value="option">{{ option }}</option></CustomSelect></label>
                    <label><span class="filter-label">Location</span><CustomSelect v-model="form.location" class="filter-input"><option value="">All locations</option><option v-for="option in locationOptions" :key="option" :value="option">{{ option }}</option></CustomSelect></label>
                    <label><span class="filter-label">Supplier</span><CustomSelect v-model="form.supplier" class="filter-input"><option value="">All suppliers</option><option v-for="option in supplierOptions" :key="option" :value="option">{{ option }}</option></CustomSelect></label>
                    <label><span class="filter-label">Status</span><CustomSelect v-model="form.status" class="filter-input"><option value="">All statuses</option><option v-for="option in statusOptions" :key="option" :value="option">{{ option }}</option></CustomSelect></label>
                    <div><span class="filter-label">Data quality</span><button type="button" class="flex w-full items-center justify-center gap-2 rounded-xl border px-3 py-2 text-sm font-bold" :class="form.missing_details === 'missing' ? 'border-amber-400 bg-amber-50 text-amber-800' : 'border-[#d8e7d4] bg-white text-[#60745d]'" @click="toggleMissing">{{ form.missing_details === 'missing' ? 'Missing details ×' : 'Show missing details' }}</button></div>
                </div>
            </form>
            <div class="overflow-hidden rounded-[1.7rem] border border-[#d8e7d4] bg-white"><div class="border-b border-[#edf3eb] px-5 py-3 text-sm text-[#60745d]"><strong class="text-[#234222]">{{ rentals.total }}</strong> rentals found <span v-if="rentals.total">· Showing {{ rentals.from }}–{{ rentals.to }}</span></div><div class="overflow-x-auto"><table class="table"><thead><tr><th>Serial / Tag</th><th>Description</th><th>Supplier / Project</th><th>Location</th><th>Due date</th><th>Status</th><th>Actions</th></tr></thead><tbody><tr v-for="item in rentals.data" :key="item.id"><td><Link class="font-bold text-[#2f7d32]" :href="route('miri-rental.show', item.id)">{{ item.serial_tag_equipment_no || '-' }}</Link></td><td>{{ item.description || '-' }}<div class="text-xs text-slate-500">{{ item.category || '-' }}</div></td><td>{{ item.supplier || '-' }}<div class="text-xs text-slate-500">{{ item.project_contract || '-' }}</div></td><td>{{ item.current_location || '-' }}</td><td>{{ item.rental_due_date || '-' }}</td><td>{{ item.status || '-' }}</td><td><Link class="btn btn-xs" :href="route('miri-rental.show', item.id)">View</Link><Link v-if="canEdit" class="btn btn-xs border-[#cfe6c8] bg-white" :href="route('miri-rental.edit', item.id)">Edit</Link></td></tr><tr v-if="!rentals.data.length"><td colspan="7" class="py-12 text-center text-slate-500">No Rental records match the selected filters.</td></tr></tbody></table></div></div>
            <div class="flex flex-wrap gap-2"><Link v-for="link in rentals.links" :key="link.label" v-html="link.label" :href="link.url || '#'" class="btn btn-sm" :class="{ 'btn-disabled': !link.url, 'btn-success text-white': link.active }" /></div>
        </section>
    </AuthenticatedLayout>
</template>
<style scoped>
.filter-label { @apply mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#60745d]; }
.filter-input { @apply w-full rounded-xl border-[#d8e7d4] text-sm focus:border-[#4f9f4a] focus:ring-[#4f9f4a]; }
</style>
