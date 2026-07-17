<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CustomSelect from '@/Components/CustomSelect.vue';
import InputError from '@/Components/InputError.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps({
    items: Array,
    locations: Array,
    transactionTypes: Array,
});

const form = useForm({
    transaction_date: new Date().toISOString().slice(0, 10),
    item_id: props.items[0]?.value ?? '',
    location_id: '',
    source_location_id: '',
    destination_location_id: '',
    transaction_type: props.transactionTypes[0]?.value ?? '',
    quantity: '',
    unit_cost: props.items[0]?.unit_cost ?? '',
    po_no: '',
    do_no: '',
    cog_issued_out: '',
    cog_received: '',
    remarks: '',
});

watch(() => form.item_id, (value) => {
    const selected = props.items.find((item) => item.value === value);
    if (selected && !form.unit_cost) {
        form.unit_cost = selected.unit_cost;
    }
});

const submit = () => {
    form.post(route('inventory.transactions.store'));
};
</script>

<template>
    <Head title="New Stock Transaction" />

    <AuthenticatedLayout>
        <PageHeader title="New Stock Transaction" description="Record receives, issues, transfers, returns, and adjustments against an inventory item." />

        <form class="rounded-2xl border border-slate-700/60 bg-[#1e293b] p-6 space-y-4" @submit.prevent="submit">
            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-300">Transaction Date</label>
                    <TextInput v-model="form.transaction_date" type="date" class="w-full" />
                    <InputError class="mt-2" :message="form.errors.transaction_date" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-medium text-slate-300">Item</label>
                    <CustomSelect v-model="form.item_id" class="select w-full border-slate-600 bg-slate-900 text-slate-200">
                        <option v-for="item in items" :key="item.value" :value="item.value">{{ item.label }}</option>
                    </CustomSelect>
                    <InputError class="mt-2" :message="form.errors.item_id" />
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-4">
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-300">Type</label>
                    <CustomSelect v-model="form.transaction_type" class="select w-full border-slate-600 bg-slate-900 text-slate-200">
                        <option v-for="type in transactionTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                    </CustomSelect>
                </div>
                <TextInput v-model="form.quantity" type="number" step="0.01" class="w-full" placeholder="Quantity" />
                <TextInput v-model="form.unit_cost" type="number" step="0.01" class="w-full" placeholder="Unit cost" />
                <CustomSelect v-model="form.location_id" class="select w-full border-slate-600 bg-slate-900 text-slate-200">
                    <option value="">Primary location</option>
                    <option v-for="location in locations" :key="location.value" :value="location.value">{{ location.label }}</option>
                </CustomSelect>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <CustomSelect v-model="form.source_location_id" class="select w-full border-slate-600 bg-slate-900 text-slate-200">
                    <option value="">Source location</option>
                    <option v-for="location in locations" :key="location.value" :value="location.value">{{ location.label }}</option>
                </CustomSelect>
                <CustomSelect v-model="form.destination_location_id" class="select w-full border-slate-600 bg-slate-900 text-slate-200">
                    <option value="">Destination location</option>
                    <option v-for="location in locations" :key="location.value" :value="location.value">{{ location.label }}</option>
                </CustomSelect>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <TextInput v-model="form.po_no" class="w-full" placeholder="PO no" />
                <TextInput v-model="form.do_no" class="w-full" placeholder="DO no" />
                <TextInput v-model="form.cog_issued_out" class="w-full" placeholder="COG issued out" />
                <TextInput v-model="form.cog_received" class="w-full" placeholder="COG received" />
            </div>

            <textarea v-model="form.remarks" class="textarea w-full border-slate-600 bg-slate-900 text-slate-200" rows="4" placeholder="Remarks"></textarea>

            <PrimaryButton :disabled="form.processing">Record Transaction</PrimaryButton>
        </form>
    </AuthenticatedLayout>
</template>
