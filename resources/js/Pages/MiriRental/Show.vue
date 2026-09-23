<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { rentalDetailGroups, rentalDetailValue } from '@/Support/rentalDetails';
const props = defineProps({ rental: Object });
const page = usePage();
const canEdit = computed(() => page.props.auth?.user?.can?.assets_edit);
const groups = computed(() => rentalDetailGroups(props.rental));
const overview = [['company', 'Company'], ['status', 'Rental Status'], ['project_contract', 'Project / Contract'], ['current_location', 'Current Location']];
</script>
<template>
    <Head title="Rental Details" />
    <AuthenticatedLayout>
        <PageHeader title="Rental Details" description="Issue-out, received-backload, and return-to-supplier record." />
        <div class="space-y-5">
            <section aria-label="Rental summary" class="overflow-hidden rounded-2xl border border-[#d8e7d4] bg-[#f5f9f3]">
                <dl class="grid gap-px bg-[#d8e7d4] sm:grid-cols-2 xl:grid-cols-4">
                    <div v-for="[key, label] in overview" :key="key" class="min-w-0 bg-[#f5f9f3] p-5">
                        <dt class="text-xs font-bold uppercase tracking-wide text-[#60745d]">{{ label }}</dt>
                        <dd class="mt-2 whitespace-pre-wrap break-words text-base font-semibold text-[#234222]">{{ key === 'company' && !rental.company ? 'Not assigned' : rentalDetailValue(key, rental[key]) }}</dd>
                    </div>
                </dl>
            </section>
            <div class="grid items-start gap-5 xl:grid-cols-2">
                <section v-for="group in groups" :key="group.title" class="min-w-0 overflow-hidden rounded-2xl border border-[#d8e7d4] bg-white shadow-sm">
                    <h2 class="border-b border-[#d8e7d4] bg-[#f5f9f3] px-5 py-4 text-base font-bold text-[#234222]">{{ group.title }}</h2>
                    <dl class="divide-y divide-slate-100 px-5">
                        <div v-for="[key, label] in group.fields" :key="key" class="grid gap-1 py-3.5 sm:grid-cols-[minmax(0,2fr)_minmax(0,3fr)] sm:gap-4">
                            <dt class="text-sm font-medium text-[#60745d]">{{ label }}</dt>
                            <dd class="min-w-0 whitespace-pre-wrap break-words text-sm text-slate-800">{{ rentalDetailValue(key, rental[key]) }}</dd>
                        </div>
                    </dl>
                </section>
            </div>
            <div class="flex flex-wrap gap-3 rounded-2xl border border-[#d8e7d4] bg-white p-5">
                <a class="btn h-auto min-h-12 bg-[#234222] py-3 text-white" :href="route('miri-rental.pdf', rental.id)">Download registration form</a>
                <Link v-if="canEdit" class="btn bg-[#4f9f4a] text-white" :href="route('miri-rental.edit', rental.id)">Edit rental</Link>
                <Link class="btn" :href="route('miri-rental.index')">Back to Rental Register</Link>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
