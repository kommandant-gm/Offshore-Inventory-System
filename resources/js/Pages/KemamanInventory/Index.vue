<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CustomSelect from '@/Components/CustomSelect.vue';
import InputError from '@/Components/InputError.vue';
import PageHeader from '@/Components/PageHeader.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    items: Object,
    summary: Object,
    filters: Object,
    categories: Array,
    statuses: Array,
    canEdit: Boolean,
});

const filters = reactive({ ...props.filters });
const showForm = ref(false);
const editingId = ref(null);
const defaults = () => ({
    category: '', item_description: '', size_swl: '', unit: 'EA', tag_no: '',
    total_quantity: 0, quantity_in: 0, quantity_out: 0, available_quantity: 0,
    location_quantity: 0, damaged_quantity: 0, beyond_repair_quantity: 0,
    not_traceable_quantity: 0, date_issued: '', location: '', document_reference: '',
    backload_date: '', transfer_reference: '', certificate_no: '', test_expiry_date: '',
    equipment_status: 'available', remarks: '',
});
const form = useForm(defaults());

const title = computed(() => editingId.value ? 'Edit equipment record' : 'Add equipment record');
const statusLabel = (value) => props.statuses.find((status) => status.value === value)?.label ?? value;
const statusClass = (value) => ({
    available: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    in_use: 'bg-blue-50 text-blue-700 border-blue-200',
    under_inspection: 'bg-amber-50 text-amber-700 border-amber-200',
    damaged: 'bg-orange-50 text-orange-700 border-orange-200',
    beyond_repair: 'bg-rose-50 text-rose-700 border-rose-200',
    not_traceable: 'bg-yellow-50 text-yellow-800 border-yellow-300',
}[value] ?? 'bg-slate-50 text-slate-700 border-slate-200');

const applyFilters = () => router.get(route('kemaman-inventory.index'), filters, { preserveState: true, replace: true });
const clearFilters = () => {
    filters.search = '';
    filters.category = '';
    filters.status = '';
    applyFilters();
};
const openCreate = () => {
    editingId.value = null;
    form.defaults(defaults());
    form.reset();
    form.clearErrors();
    showForm.value = true;
};
const openEdit = (item) => {
    editingId.value = item.id;
    Object.keys(defaults()).forEach((key) => { form[key] = item[key] ?? defaults()[key]; });
    form.clearErrors();
    showForm.value = true;
    window.scrollTo({ top: 0, behavior: 'smooth' });
};
const closeForm = () => {
    showForm.value = false;
    editingId.value = null;
    form.clearErrors();
};
const submit = () => {
    const options = { preserveScroll: true, onSuccess: closeForm };
    if (editingId.value) form.patch(route('kemaman-inventory.update', editingId.value), options);
    else form.post(route('kemaman-inventory.store'), options);
};
const remove = (item) => {
    if (window.confirm(`Delete ${item.item_description}${item.tag_no ? ` (${item.tag_no})` : ''}?`)) {
        router.delete(route('kemaman-inventory.destroy', item.id), { preserveScroll: true });
    }
};

const metricCards = computed(() => [
    ['Records', props.summary.records, 'Register lines'],
    ['Total Quantity', props.summary.total_quantity, 'Quantity in system'],
    ['Available', props.summary.available_quantity, 'Ready for use'],
    ['Damaged', props.summary.damaged_quantity, 'Requires attention'],
    ['Beyond Repair', props.summary.beyond_repair_quantity, 'Unserviceable'],
    ['Not Traceable', props.summary.not_traceable_quantity, 'Reconciliation variance'],
]);
</script>

<template>
    <Head title="Kemaman Inventory" />
    <AuthenticatedLayout>
        <PageHeader title="Kemaman Inventory" description="Equipment, lifting gear, tools, traceability, certification, and condition register for Kemaman.">
            <button v-if="canEdit" type="button" class="btn border-none bg-[linear-gradient(135deg,#6fbb68_0%,#4f9f4a_100%)] text-white" @click="showForm ? closeForm() : openCreate()">
                <XMarkIcon v-if="showForm" class="h-5 w-5" />
                <PlusIcon v-else class="h-5 w-5" />
                {{ showForm ? 'Close form' : 'Add record' }}
            </button>
        </PageHeader>

        <section v-if="showForm" class="rounded-[1.7rem] border border-[#cfe6c8] bg-white p-5 shadow-[0_18px_45px_rgba(79,159,74,0.10)] sm:p-6">
            <div class="mb-5">
                <p class="text-[11px] font-bold uppercase tracking-[0.24em] text-[#4f9f4a]">Kemaman register</p>
                <h2 class="mt-1 text-xl font-bold text-[#234222]">{{ title }}</h2>
                <p class="mt-1 text-sm text-[#6f8a6b]">Use the fields that apply to the equipment. Optional certification and transfer fields may be left blank.</p>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                    <div class="xl:col-span-2"><label class="field-label">Item description</label><TextInput v-model="form.item_description" class="field-input" /><InputError class="mt-1" :message="form.errors.item_description" /></div>
                    <div><label class="field-label">Category</label><TextInput v-model="form.category" class="field-input" list="kemaman-categories" placeholder="e.g. Pneumatic" /><datalist id="kemaman-categories"><option v-for="category in categories" :key="category" :value="category" /></datalist><InputError class="mt-1" :message="form.errors.category" /></div>
                    <div><label class="field-label">Size / SWL</label><TextInput v-model="form.size_swl" class="field-input" placeholder="e.g. 13.0MT" /><InputError class="mt-1" :message="form.errors.size_swl" /></div>
                    <div><label class="field-label">Unit</label><TextInput v-model="form.unit" class="field-input" placeholder="EA" /><InputError class="mt-1" :message="form.errors.unit" /></div>
                    <div class="xl:col-span-2"><label class="field-label">Tag no.</label><TextInput v-model="form.tag_no" class="field-input" /><InputError class="mt-1" :message="form.errors.tag_no" /></div>
                    <div><label class="field-label">Location</label><TextInput v-model="form.location" class="field-input" placeholder="TKY / KSB" /><InputError class="mt-1" :message="form.errors.location" /></div>
                    <div class="xl:col-span-2"><label class="field-label">Equipment status</label><CustomSelect v-model="form.equipment_status" class="select field-input"><option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option></CustomSelect><InputError class="mt-1" :message="form.errors.equipment_status" /></div>
                </div>

                <div>
                    <h3 class="mb-3 text-sm font-bold text-[#315c30]">Quantity and condition</h3>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8">
                        <div v-for="field in [
                            ['total_quantity','Total qty'], ['quantity_in','In'], ['quantity_out','Out'], ['available_quantity','Available'],
                            ['location_quantity','At location'], ['damaged_quantity','Damage'], ['beyond_repair_quantity','Beyond repair'], ['not_traceable_quantity','Not traceable']
                        ]" :key="field[0]"><label class="field-label">{{ field[1] }}</label><TextInput v-model="form[field[0]]" type="number" class="field-input" /><InputError class="mt-1" :message="form.errors[field[0]]" /></div>
                    </div>
                </div>

                <div>
                    <h3 class="mb-3 text-sm font-bold text-[#315c30]">Movement and certification</h3>
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div><label class="field-label">Date issued</label><TextInput v-model="form.date_issued" type="date" class="field-input" /><InputError class="mt-1" :message="form.errors.date_issued" /></div>
                        <div><label class="field-label">Document / COG / MTF no.</label><TextInput v-model="form.document_reference" class="field-input" /><InputError class="mt-1" :message="form.errors.document_reference" /></div>
                        <div><label class="field-label">Backload date</label><TextInput v-model="form.backload_date" type="date" class="field-input" /><InputError class="mt-1" :message="form.errors.backload_date" /></div>
                        <div><label class="field-label">MRV / MTF transfer no.</label><TextInput v-model="form.transfer_reference" class="field-input" /><InputError class="mt-1" :message="form.errors.transfer_reference" /></div>
                        <div class="xl:col-span-2"><label class="field-label">Certificate LT no.</label><TextInput v-model="form.certificate_no" class="field-input" /><InputError class="mt-1" :message="form.errors.certificate_no" /></div>
                        <div><label class="field-label">Test expiry</label><TextInput v-model="form.test_expiry_date" type="date" class="field-input" /><InputError class="mt-1" :message="form.errors.test_expiry_date" /></div>
                        <div><label class="field-label">Remarks</label><TextInput v-model="form.remarks" class="field-input" /><InputError class="mt-1" :message="form.errors.remarks" /></div>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3 border-t border-[#edf3eb] pt-5">
                    <button class="btn border-none bg-[#4f9f4a] px-6 text-white hover:bg-[#3f8d3d]" :disabled="form.processing">{{ editingId ? 'Update record' : 'Create record' }}</button>
                    <button type="button" class="btn border-[#d8e7d4] bg-white text-[#2f6f2d]" @click="closeForm">Cancel</button>
                </div>
            </form>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            <article v-for="card in metricCards" :key="card[0]" class="rounded-2xl border border-[#d8e7d4] bg-white p-4 shadow-sm">
                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#7f9a7a]">{{ card[0] }}</p>
                <p class="mt-2 text-2xl font-black text-[#234222]">{{ card[1] }}</p>
                <p class="mt-1 text-xs text-[#7f9a7a]">{{ card[2] }}</p>
            </article>
        </section>

        <form class="rounded-2xl border border-[#d8e7d4] bg-white p-4 shadow-sm" @submit.prevent="applyFilters">
            <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr),240px,220px,auto]">
                <TextInput v-model="filters.search" class="field-input" placeholder="Search description, tag, certificate, document, or location..." />
                <CustomSelect v-model="filters.category" class="select field-input"><option value="">All categories</option><option v-for="category in categories" :key="category" :value="category">{{ category }}</option></CustomSelect>
                <CustomSelect v-model="filters.status" class="select field-input"><option value="">All statuses</option><option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option></CustomSelect>
                <div class="flex gap-2"><button class="btn flex-1 border-none bg-[#4f9f4a] text-white">Filter</button><button type="button" class="btn border-[#d8e7d4] bg-white text-[#60745d]" @click="clearFilters">Clear</button></div>
            </div>
        </form>

        <section class="overflow-hidden rounded-2xl border border-[#d8e7d4] bg-white shadow-[0_18px_45px_rgba(79,159,74,0.08)]">
            <div class="overflow-x-auto">
                <table class="table table-sm min-w-[2200px]">
                    <thead class="bg-[#e8f4e5] text-[10px] uppercase tracking-wide text-[#315c30]">
                        <tr><th>#</th><th>Item description</th><th>Category</th><th>Size / SWL</th><th>Unit</th><th>Tag no.</th><th>Total</th><th>In</th><th>Out</th><th>Available</th><th>At location</th><th>Damage</th><th>Beyond repair</th><th>Not traceable</th><th>Issued</th><th>Location</th><th>Document / COG / MTF</th><th>Backload</th><th>Transfer no.</th><th>Certificate LT no.</th><th>Test expiry</th><th>Status</th><th>Remarks</th><th v-if="canEdit"></th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, index) in items.data" :key="item.id" class="border-[#edf3eb] align-top hover:bg-[#f7fcf5]">
                            <td class="text-[#7f9a7a]">{{ items.from + index }}</td>
                            <td class="max-w-xs whitespace-normal font-semibold text-[#234222]">{{ item.item_description }}</td>
                            <td><span class="rounded-md bg-[#e8f4e5] px-2 py-1 text-xs font-semibold text-[#315c30]">{{ item.category }}</span></td>
                            <td>{{ item.size_swl || '-' }}</td><td>{{ item.unit }}</td><td class="font-mono text-[#315c30]">{{ item.tag_no || '-' }}</td>
                            <td>{{ item.total_quantity }}</td><td>{{ item.quantity_in }}</td><td>{{ item.quantity_out }}</td><td class="font-bold text-emerald-700">{{ item.available_quantity }}</td><td>{{ item.location_quantity }}</td><td class="text-orange-700">{{ item.damaged_quantity }}</td><td class="text-rose-700">{{ item.beyond_repair_quantity }}</td><td :class="item.not_traceable_quantity !== 0 ? 'bg-yellow-100 font-bold text-yellow-900' : ''">{{ item.not_traceable_quantity }}</td>
                            <td>{{ item.date_issued || '-' }}</td><td>{{ item.location || '-' }}</td><td>{{ item.document_reference || '-' }}</td><td>{{ item.backload_date || '-' }}</td><td>{{ item.transfer_reference || '-' }}</td><td>{{ item.certificate_no || '-' }}</td><td>{{ item.test_expiry_date || '-' }}</td>
                            <td><span class="whitespace-nowrap rounded-full border px-2 py-1 text-[10px] font-bold uppercase" :class="statusClass(item.equipment_status)">{{ statusLabel(item.equipment_status) }}</span></td>
                            <td class="max-w-xs whitespace-normal">{{ item.remarks || '-' }}</td>
                            <td v-if="canEdit"><div class="flex gap-2"><button type="button" class="btn btn-xs border-[#d8e7d4] bg-white text-[#2f6f2d]" @click="openEdit(item)">Edit</button><button type="button" class="btn btn-xs border-rose-200 bg-white text-rose-600" @click="remove(item)">Delete</button></div></td>
                        </tr>
                        <tr v-if="!items.data.length"><td :colspan="canEdit ? 24 : 23" class="py-14 text-center text-[#7f9a7a]">No Kemaman inventory records match the selected filters.</td></tr>
                    </tbody>
                </table>
            </div>
            <div v-if="items.links.length > 3" class="flex flex-wrap gap-2 border-t border-[#edf3eb] p-4">
                <Link v-for="link in items.links" :key="link.label" :href="link.url || '#'" preserve-scroll class="rounded-lg border px-3 py-2 text-xs" :class="link.active ? 'border-[#4f9f4a] bg-[#4f9f4a] text-white' : link.url ? 'border-[#d8e7d4] text-[#60745d] hover:bg-[#f2f8ef]' : 'pointer-events-none border-[#edf3eb] text-[#b3c1b1]'" v-html="link.label" />
            </div>
        </section>
    </AuthenticatedLayout>
</template>

<style scoped>
.field-label { display: block; margin-bottom: 0.45rem; font-size: 0.68rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: #6f8a6b; }
.field-input { width: 100%; border-color: #cfe6c8; background: white; color: #234222; }
</style>
