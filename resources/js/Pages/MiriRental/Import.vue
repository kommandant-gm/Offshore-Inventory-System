<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
const form = useForm({ file: null });
const props = defineProps({ isGas: Boolean });
const submit = () => form.post(route(props.isGas ? 'miri-gas.import.store' : 'miri-rental.import.store'));
</script>
<template><Head :title="isGas ? 'Import Gas' : 'Import Rental'" /><AuthenticatedLayout><PageHeader :title="isGas ? 'Import Miri Gas' : 'Import Miri Rental'" description="Import the existing rental CSV format. BIG and LGM items appear in Gas Register; other suppliers appear in Rental Register." /><form class="max-w-2xl rounded-[2rem] border border-[#d8e7d4] bg-white p-6 shadow-sm" @submit.prevent="submit"><input type="file" accept=".csv,.txt" class="file-input file-input-bordered w-full" @change="form.file = $event.target.files[0]" /><p v-if="form.errors.file" class="mt-2 text-sm text-red-600">{{ form.errors.file }}</p><div class="mt-6 flex gap-3"><PrimaryButton :disabled="form.processing">Import {{ isGas ? 'Gas' : 'Rental' }} CSV</PrimaryButton><Link class="btn" :href="route(isGas ? 'miri-gas.index' : 'miri-rental.index')">Cancel</Link></div></form></AuthenticatedLayout></template>
