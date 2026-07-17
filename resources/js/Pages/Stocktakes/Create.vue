<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CustomSelect from '@/Components/CustomSelect.vue';
import InputError from '@/Components/InputError.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    locations: Array,
    items: Array,
    draftReference: String,
});

const form = useForm({
    stocktake_date: new Date().toISOString().slice(0, 10),
    location_id: props.locations[0]?.value ?? '',
    remarks: '',
    items: props.items.map((item) => ({
        inventory_item_id: item.id,
        counted_quantity: item.system_quantity,
        remarks: '',
    })),
});

const locationQuantities = computed(() => {
    const selectedLocation = Number(form.location_id);

    return Object.fromEntries(props.items.map((item) => [
        item.id,
        Number(item.location_quantities?.[selectedLocation] ?? 0),
    ]));
});

const submit = () => {
    form.post(route('stocktakes.store'));
};
</script>

<template>
    <Head title="New Stocktake" />

    <AuthenticatedLayout>
        <PageHeader title="New Stocktake" description="Count one location and post variance adjustments in a single controlled workflow." />

        <form class="space-y-5" @submit.prevent="submit">
            <section class="rounded-[1.8rem] border border-[#d8e7d4] bg-white p-5 shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-[#234222]">Reference</label>
                        <TextInput :model-value="draftReference" class="w-full border-[#cfe6c8] bg-white text-[#234222]" disabled />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-[#234222]">Stocktake Date</label>
                        <TextInput v-model="form.stocktake_date" type="date" class="w-full border-[#cfe6c8] bg-white text-[#234222]" />
                        <InputError class="mt-2" :message="form.errors.stocktake_date" />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-[#234222]">Location</label>
                        <CustomSelect v-model="form.location_id" class="select w-full border-[#cfe6c8] bg-white text-[#234222]">
                            <option v-for="location in locations" :key="location.value" :value="location.value">{{ location.label }}</option>
                        </CustomSelect>
                        <InputError class="mt-2" :message="form.errors.location_id" />
                    </div>
                </div>

                <div class="mt-4">
                    <label class="mb-2 block text-sm font-medium text-[#234222]">Remarks</label>
                    <textarea v-model="form.remarks" class="textarea w-full border-[#cfe6c8] bg-white text-[#234222]" rows="3" />
                </div>
            </section>

            <section class="rounded-[1.8rem] border border-[#d8e7d4] bg-white p-5 shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
                <div class="mb-4">
                    <h2 class="text-lg font-semibold text-[#234222]">Count Sheet</h2>
                    <p class="text-sm text-[#6f8a6b]">System quantity updates with the selected location.</p>
                </div>

                <div class="space-y-4">
                    <article v-for="(item, index) in items" :key="item.id" class="rounded-[1.25rem] border border-[#d8e7d4] bg-[#fbfefa] p-4">
                        <div class="grid gap-4 xl:grid-cols-[1.4fr,0.8fr,0.8fr,1fr]">
                            <div>
                                <p class="font-mono text-sm text-[#3c8a39]">{{ item.item_code }}</p>
                                <p class="mt-1 text-sm font-semibold text-[#234222]">{{ item.description }}</p>
                                <p class="mt-1 text-xs text-[#6f8a6b]">{{ item.current_location ?? 'No mapped location' }}</p>
                            </div>
                            <div>
                                <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-[#7f9a7a]">System Qty</label>
                                <TextInput :model-value="locationQuantities[item.id]" class="w-full border-[#cfe6c8] bg-white text-[#234222]" disabled />
                            </div>
                            <div>
                                <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-[#7f9a7a]">Counted Qty</label>
                                <TextInput v-model="form.items[index].counted_quantity" type="number" step="0.01" class="w-full border-[#cfe6c8] bg-white text-[#234222]" />
                            </div>
                            <div>
                                <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-[#7f9a7a]">Remarks</label>
                                <TextInput v-model="form.items[index].remarks" class="w-full border-[#cfe6c8] bg-white text-[#234222]" />
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <PrimaryButton :disabled="form.processing">Complete Stocktake</PrimaryButton>
        </form>
    </AuthenticatedLayout>
</template>
