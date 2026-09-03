<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
const form = useForm({ file: null });
const submit = () => form.post(route('miri-rental.import.store'));
</script>
<template><Head title="Import Rental" /><AuthenticatedLayout><PageHeader title="Import Miri Rental" description="Import the Rental CSV with separate issue-out, received-backload, and return-to-supplier records." /><form class="max-w-2xl rounded-[2rem] border border-[#d8e7d4] bg-white p-6 shadow-sm" @submit.prevent="submit"><input type="file" accept=".csv,.txt" class="file-input file-input-bordered w-full" @change="form.file = $event.target.files[0]" /><p v-if="form.errors.file" class="mt-2 text-sm text-red-600">{{ form.errors.file }}</p><div class="mt-6 flex gap-3"><PrimaryButton :disabled="form.processing">Import Rental CSV</PrimaryButton><Link class="btn" :href="route('miri-rental.index')">Cancel</Link></div></form></AuthenticatedLayout></template>
