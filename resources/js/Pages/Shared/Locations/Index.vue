<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    locations: Array,
    locationOptions: Array,
    typeOptions: Array,
});

const editingId = ref(null);
const form = useForm({
    code: '',
    name: '',
    type: props.typeOptions[0]?.value ?? 'yard',
    parent_id: '',
    active: true,
});

const submitLabel = computed(() => editingId.value ? 'Update Location' : 'Create Location');

watch(editingId, (id) => {
    if (!id) {
        form.reset();
        form.type = props.typeOptions[0]?.value ?? 'yard';
        form.active = true;
    }
});

const editLocation = (location) => {
    editingId.value = location.id;
    form.code = location.code;
    form.name = location.name;
    form.type = location.type;
    form.parent_id = location.parent_id ?? '';
    form.active = location.active;
};

const submit = () => {
    if (editingId.value) {
        form.put(route('locations.update', editingId.value), {
            preserveScroll: true,
            onSuccess: () => editingId.value = null,
        });
        return;
    }

    form.post(route('locations.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <Head title="Locations" />

    <AuthenticatedLayout>
        <PageHeader title="Locations" description="Track yard zones, racks, bins, offshore destinations, repair shops, and scrap areas." />

        <div class="grid gap-6 xl:grid-cols-[360px,1fr]">
            <form class="rounded-2xl border border-[#d8e7d4] bg-white p-6 space-y-4 shadow-[0_18px_45px_rgba(79,159,74,0.10)]" @submit.prevent="submit">
                <div>
                    <label class="mb-2 block text-sm font-medium text-[#5f7b5e]">Code</label>
                    <TextInput v-model="form.code" class="w-full border-[#cfe6c8] bg-white text-[#234222]" />
                    <InputError class="mt-2" :message="form.errors.code" />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-[#5f7b5e]">Name</label>
                    <TextInput v-model="form.name" class="w-full border-[#cfe6c8] bg-white text-[#234222]" />
                    <InputError class="mt-2" :message="form.errors.name" />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-[#5f7b5e]">Type</label>
                    <select v-model="form.type" class="select w-full border-[#cfe6c8] bg-white text-[#234222]">
                        <option v-for="option in typeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.type" />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-[#5f7b5e]">Parent Location</label>
                    <select v-model="form.parent_id" class="select w-full border-[#cfe6c8] bg-white text-[#234222]">
                        <option value="">None</option>
                        <option v-for="option in locationOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.parent_id" />
                </div>

                <label class="flex items-center gap-3 text-sm text-[#234222]">
                    <input v-model="form.active" type="checkbox" class="checkbox checkbox-sm border-[#b8d7b1]" />
                    Active
                </label>

                <div class="flex gap-3">
                    <PrimaryButton :disabled="form.processing" class="border-none bg-[linear-gradient(135deg,#6fbb68_0%,#4f9f4a_100%)] text-white shadow-[0_16px_36px_rgba(79,159,74,0.24)]">{{ submitLabel }}</PrimaryButton>
                    <button v-if="editingId" type="button" class="btn border-[#d8e7d4] bg-white text-[#2f6f2d] hover:bg-[#eef8ea]" @click="editingId = null">Cancel</button>
                </div>
            </form>

            <div class="overflow-hidden rounded-2xl border border-[#d8e7d4] bg-white shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
                <table class="table">
                    <thead>
                        <tr class="text-[#6f8a6b]">
                            <th>Code</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Parent</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="location in locations" :key="location.id" class="hover:bg-[#f4fbf1]">
                            <td class="font-mono text-[#3c8a39]">{{ location.code }}</td>
                            <td class="text-[#234222]">{{ location.name }}</td>
                            <td><StatusBadge :value="location.type" /></td>
                            <td class="text-[#5f7b5e]">{{ location.parent_name ?? '-' }}</td>
                            <td><StatusBadge :value="location.active ? 'active' : 'inactive'" /></td>
                            <td class="text-right">
                                <button class="btn btn-sm border-[#d8e7d4] bg-white text-[#2f6f2d] hover:bg-[#eef8ea]" @click="editLocation(location)">Edit</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
