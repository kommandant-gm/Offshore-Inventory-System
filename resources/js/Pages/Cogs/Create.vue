<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    items: Array,
    draftCogNo: String,
});

const emptyLine = () => ({
    inventory_item_id: '',
    qty: '',
    unit: '',
    part_no: '',
    full_description: '',
    measurement_cu_metre: '',
    gross_weight_kg: '',
    po_no: '',
    ex_location: '',
    serial_no: '',
    unit_price: '',
    total_amount: '',
    remarks: '',
});

const form = useForm({
    document_date: new Date().toISOString().slice(0, 10),
    consignee: '',
    shipper: 'Dayang Enterprise Sdn. Bhd.',
    destination: '',
    vessel: '',
    department: '',
    receiver_name: '',
    receiver_email: '',
    cc_emails: '',
    receiver_designation: '',
    issued_by_name: '',
    issued_by_designation: '',
    checked_by_name: '',
    checked_by_designation: '',
    remarks: '',
    items: [emptyLine()],
});

const syncItem = (index) => {
    const selected = props.items.find((item) => item.value === form.items[index].inventory_item_id);
    if (!selected) return;

    form.items[index].part_no = selected.item_code;
    form.items[index].full_description = selected.description;
    form.items[index].unit = selected.uom;
    form.items[index].unit_price = selected.unit_price;
    const qty = Number(form.items[index].qty || 0);
    form.items[index].total_amount = qty ? (qty * Number(selected.unit_price || 0)).toFixed(2) : '';
};

const recalcLine = (index) => {
    const qty = Number(form.items[index].qty || 0);
    const unitPrice = Number(form.items[index].unit_price || 0);
    form.items[index].total_amount = qty || unitPrice ? (qty * unitPrice).toFixed(2) : '';
};

const addLine = () => form.items.push(emptyLine());
const removeLine = (index) => {
    if (form.items.length === 1) return;
    form.items.splice(index, 1);
};

const submit = () => {
    form.post(route('cogs.store'));
};
</script>

<template>
    <Head title="New COG" />

    <AuthenticatedLayout>
        <PageHeader title="Create COG" description="Create a consignment note with multiple stock item lines and receiver email approval.">
            <div class="flex gap-3">
                <Link class="btn border-[#b8d7b1] bg-white text-[#2f6f2d] hover:bg-[#eef8ea]" :href="route('cogs.index')">Back To COG</Link>
                <span class="rounded-full border border-[#cfe6c8] bg-[#eef8ea] px-4 py-2 text-sm font-semibold text-[#4f9f4a]">{{ draftCogNo }}</span>
            </div>
        </PageHeader>

        <form class="space-y-6" @submit.prevent="submit">
            <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-5 shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-[1.35rem] border border-[#d8e7d4] bg-[#fbfefa] p-4">
                        <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Document Date</label>
                        <TextInput v-model="form.document_date" type="date" class="w-full border-[#cfe6c8] bg-white text-[#234222]" />
                    </div>
                    <div class="rounded-[1.35rem] border border-[#d8e7d4] bg-[#fbfefa] p-4">
                        <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Consignee</label>
                        <TextInput v-model="form.consignee" class="w-full border-[#cfe6c8] bg-white text-[#234222]" />
                    </div>
                    <div class="rounded-[1.35rem] border border-[#d8e7d4] bg-[#fbfefa] p-4">
                        <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Destination</label>
                        <TextInput v-model="form.destination" class="w-full border-[#cfe6c8] bg-white text-[#234222]" />
                    </div>
                    <div class="rounded-[1.35rem] border border-[#d8e7d4] bg-[#fbfefa] p-4">
                        <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Vessel</label>
                        <TextInput v-model="form.vessel" class="w-full border-[#cfe6c8] bg-white text-[#234222]" />
                    </div>
                </div>

                <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-[1.35rem] border border-[#d8e7d4] bg-[#fbfefa] p-4">
                        <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Department</label>
                        <TextInput v-model="form.department" class="w-full border-[#cfe6c8] bg-white text-[#234222]" />
                    </div>
                    <div class="rounded-[1.35rem] border border-[#d8e7d4] bg-[#fbfefa] p-4">
                        <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Receiver Name</label>
                        <TextInput v-model="form.receiver_name" class="w-full border-[#cfe6c8] bg-white text-[#234222]" />
                    </div>
                    <div class="rounded-[1.35rem] border border-[#d8e7d4] bg-[#fbfefa] p-4">
                        <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Receiver Email</label>
                        <TextInput v-model="form.receiver_email" type="email" class="w-full border-[#cfe6c8] bg-white text-[#234222]" />
                    </div>
                    <div class="rounded-[1.35rem] border border-[#d8e7d4] bg-[#fbfefa] p-4 md:col-span-2 xl:col-span-1">
                        <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">CC Acknowledgement Emails</label>
                        <TextInput v-model="form.cc_emails" class="w-full border-[#cfe6c8] bg-white text-[#234222]" placeholder="manager@company.com, ops@company.com" />
                        <p class="mt-2 text-xs text-[#7f9a7a]">Separate multiple emails with commas.</p>
                    </div>
                    <div class="rounded-[1.35rem] border border-[#d8e7d4] bg-[#fbfefa] p-4">
                        <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Receiver Designation</label>
                        <TextInput v-model="form.receiver_designation" class="w-full border-[#cfe6c8] bg-white text-[#234222]" />
                    </div>
                </div>

                <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-[1.35rem] border border-[#d8e7d4] bg-[#fbfefa] p-4">
                        <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Issued By Name</label>
                        <TextInput v-model="form.issued_by_name" class="w-full border-[#cfe6c8] bg-white text-[#234222]" />
                    </div>
                    <div class="rounded-[1.35rem] border border-[#d8e7d4] bg-[#fbfefa] p-4">
                        <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Issued By Designation</label>
                        <TextInput v-model="form.issued_by_designation" class="w-full border-[#cfe6c8] bg-white text-[#234222]" />
                    </div>
                    <div class="rounded-[1.35rem] border border-[#d8e7d4] bg-[#fbfefa] p-4">
                        <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Checked By Name</label>
                        <TextInput v-model="form.checked_by_name" class="w-full border-[#cfe6c8] bg-white text-[#234222]" />
                    </div>
                    <div class="rounded-[1.35rem] border border-[#d8e7d4] bg-[#fbfefa] p-4">
                        <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Checked By Designation</label>
                        <TextInput v-model="form.checked_by_designation" class="w-full border-[#cfe6c8] bg-white text-[#234222]" />
                    </div>
                </div>
            </section>

            <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-5 shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-[#234222]">COG Line Items</h2>
                        <p class="text-sm text-[#6f8a6b]">Add the materials or stock items to be consigned.</p>
                    </div>
                    <button type="button" class="btn border-none bg-[linear-gradient(135deg,#6fbb68_0%,#4f9f4a_100%)] text-white shadow-[0_16px_36px_rgba(79,159,74,0.24)] hover:opacity-95" @click="addLine">
                        Add Line
                    </button>
                </div>

                <div class="space-y-4">
                    <article v-for="(item, index) in form.items" :key="index" class="rounded-[1.5rem] border border-[#d8e7d4] bg-[#fbfefa] p-4">
                        <div class="grid gap-4 xl:grid-cols-6">
                            <div class="xl:col-span-2">
                                <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Stock Item</label>
                                <select v-model="item.inventory_item_id" class="select w-full border-[#cfe6c8] bg-white text-[#234222]" @change="syncItem(index)">
                                    <option value="">Manual line</option>
                                    <option v-for="option in items" :key="option.value" :value="option.value">{{ option.item_code }} - {{ option.description }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Qty</label>
                                <TextInput v-model="item.qty" type="number" step="0.01" class="w-full border-[#cfe6c8] bg-white text-[#234222]" @input="recalcLine(index)" />
                            </div>
                            <div>
                                <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Unit</label>
                                <TextInput v-model="item.unit" class="w-full border-[#cfe6c8] bg-white text-[#234222]" />
                            </div>
                            <div>
                                <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Part No.</label>
                                <TextInput v-model="item.part_no" class="w-full border-[#cfe6c8] bg-white text-[#234222]" />
                            </div>
                            <div class="flex items-end justify-end">
                                <button type="button" class="btn border-[#d8e7d4] bg-white text-[#2f6f2d] hover:bg-[#eef8ea]" @click="removeLine(index)">
                                    Remove
                                </button>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-4 xl:grid-cols-5">
                            <div class="xl:col-span-2">
                                <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Full Description</label>
                                <TextInput v-model="item.full_description" class="w-full border-[#cfe6c8] bg-white text-[#234222]" />
                            </div>
                            <div>
                                <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Measurement (Cu. Metre)</label>
                                <TextInput v-model="item.measurement_cu_metre" type="number" step="0.001" class="w-full border-[#cfe6c8] bg-white text-[#234222]" />
                            </div>
                            <div>
                                <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Gross Wt. (Kg)</label>
                                <TextInput v-model="item.gross_weight_kg" type="number" step="0.001" class="w-full border-[#cfe6c8] bg-white text-[#234222]" />
                            </div>
                            <div>
                                <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">S/N</label>
                                <TextInput v-model="item.serial_no" class="w-full border-[#cfe6c8] bg-white text-[#234222]" />
                            </div>
                        </div>

                        <div class="mt-4 grid gap-4 xl:grid-cols-5">
                            <div>
                                <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">PO No.</label>
                                <TextInput v-model="item.po_no" class="w-full border-[#cfe6c8] bg-white text-[#234222]" />
                            </div>
                            <div>
                                <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Ex Location</label>
                                <TextInput v-model="item.ex_location" class="w-full border-[#cfe6c8] bg-white text-[#234222]" />
                            </div>
                            <div>
                                <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Unit Price</label>
                                <TextInput v-model="item.unit_price" type="number" step="0.01" class="w-full border-[#cfe6c8] bg-white text-[#234222]" @input="recalcLine(index)" />
                            </div>
                            <div>
                                <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Total Amount</label>
                                <TextInput v-model="item.total_amount" type="number" step="0.01" class="w-full border-[#cfe6c8] bg-white text-[#234222]" />
                            </div>
                            <div>
                                <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Line Remarks</label>
                                <TextInput v-model="item.remarks" class="w-full border-[#cfe6c8] bg-white text-[#234222]" />
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-5 shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
                <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Remarks</label>
                <textarea v-model="form.remarks" class="textarea h-28 w-full border-[#cfe6c8] bg-white text-[#234222]" />

                <div class="mt-5 flex justify-end">
                    <PrimaryButton :disabled="form.processing" class="rounded-full border-none bg-[linear-gradient(135deg,#6fbb68_0%,#4f9f4a_100%)] px-6 py-3 text-sm font-bold uppercase tracking-[0.24em] text-white shadow-[0_18px_40px_rgba(79,159,74,0.24)]">
                        Create COG
                    </PrimaryButton>
                </div>
            </section>
        </form>
    </AuthenticatedLayout>
</template>
