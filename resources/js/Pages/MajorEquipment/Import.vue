<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
const form = useForm({ file: null });
const submit = () => form.post(route('major-equipment.import.store'));
</script>
<template>
    <Head title="Import Miri Inventory" />
    <AuthenticatedLayout>
        <PageHeader title="Import Miri Inventory" description="Import a prepared Miri Inventory CSV. This file is currently for the Major Equipment category." />
        <section class="max-w-3xl rounded-[2rem] border border-[#d8e7d4] bg-white p-6 shadow-sm">
            <form @submit.prevent="submit">
                <p class="text-sm text-slate-600">The grouped header row is skipped. Blank and untagged rows are accepted; issue-out and received-backload COG numbers and dates are stored separately.</p>
                <input class="mt-5 block w-full rounded-xl border border-[#d8e7d4] p-3" type="file" accept=".csv,text/csv" @input="form.file = $event.target.files[0]" />
                <InputError class="mt-2" :message="form.errors.file" />
                <div class="mt-6 flex gap-3"><PrimaryButton type="submit" :disabled="form.processing">Import Into Miri Inventory</PrimaryButton><Link class="btn" :href="route('major-equipment.index')">Cancel</Link></div>
            </form>
        </section>
    </AuthenticatedLayout>
</template>
