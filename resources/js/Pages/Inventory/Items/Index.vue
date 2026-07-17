<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CustomSelect from '@/Components/CustomSelect.vue';
import InputError from '@/Components/InputError.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    items: Object,
    categoryOptions: Array,
    locationOptions: Array,
});

const form = useForm({
    item_code: '',
    description: '',
    category_id: props.categoryOptions[0]?.value ?? '',
    uom: 'PCS',
    default_location_id: '',
    standard_cost: '',
    minimum_stock: '',
    active: true,
    remarks: '',
});

const submit = () => {
    form.post(route('inventory.items.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <Head title="Inventory Items" />

    <AuthenticatedLayout>
        <PageHeader title="Inventory Item Master" description="Quantity-based stock items grouped by category with default rack/location and costing." />

        <div class="grid gap-6 xl:grid-cols-[400px,1fr]">
            <form class="rounded-2xl border border-slate-700/60 bg-[#1e293b] p-6 space-y-4" @submit.prevent="submit">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-1">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-300">Item Code</label>
                        <TextInput v-model="form.item_code" class="w-full" />
                        <InputError class="mt-2" :message="form.errors.item_code" />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-300">Description</label>
                        <TextInput v-model="form.description" class="w-full" />
                        <InputError class="mt-2" :message="form.errors.description" />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-300">Category</label>
                        <CustomSelect v-model="form.category_id" class="select w-full border-slate-600 bg-slate-900 text-slate-200">
                            <option v-for="option in categoryOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </CustomSelect>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-300">Default Location</label>
                        <CustomSelect v-model="form.default_location_id" class="select w-full border-slate-600 bg-slate-900 text-slate-200">
                            <option value="">None</option>
                            <option v-for="option in locationOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </CustomSelect>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <TextInput v-model="form.uom" class="w-full" placeholder="UOM" />
                    <TextInput v-model="form.standard_cost" type="number" step="0.01" class="w-full" placeholder="Standard cost" />
                    <TextInput v-model="form.minimum_stock" type="number" step="0.01" class="w-full" placeholder="Minimum stock" />
                </div>

                <textarea v-model="form.remarks" class="textarea w-full border-slate-600 bg-slate-900 text-slate-200" rows="3" placeholder="Remarks"></textarea>

                <label class="flex items-center gap-3 text-sm text-slate-300">
                    <input v-model="form.active" type="checkbox" class="checkbox checkbox-sm border-slate-600" />
                    Active
                </label>

                <PrimaryButton :disabled="form.processing">Create Item</PrimaryButton>
            </form>

            <div class="overflow-hidden rounded-2xl border border-slate-700/60 bg-[#1e293b]">
                <table class="table">
                    <thead>
                        <tr class="text-slate-400">
                            <th>Code</th>
                            <th>Description</th>
                            <th>Category</th>
                            <th>UOM</th>
                            <th>Location</th>
                            <th>Std Cost</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in items.data" :key="item.id" class="hover:bg-slate-800/40">
                            <td class="font-mono text-orange-300">{{ item.item_code }}</td>
                            <td class="text-white">{{ item.description }}</td>
                            <td class="text-slate-300">{{ item.category }}</td>
                            <td class="text-slate-300">{{ item.uom }}</td>
                            <td class="text-slate-300">{{ item.location ?? '-' }}</td>
                            <td class="text-slate-300">{{ item.standard_cost }}</td>
                            <td><StatusBadge :value="item.active ? 'active' : 'inactive'" /></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
