<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CompanyField from '@/Components/CompanyField.vue';
const props = defineProps({ filters: Object, report: Object });
const form = useForm({ as_of: props.filters.as_of, company: props.filters.company });
const submit = () => form.get(route('miri-reports.scaffolding'), { preserveState: false });
const number = value => value === null ? '—' : Number(value).toLocaleString(undefined, { maximumFractionDigits: 3 });
</script>
<template>
    <Head title="Scaffolding Materials Report" />
    <AuthenticatedLayout>
        <div class="space-y-5">
            <header class="flex flex-wrap items-center justify-between gap-4"><div><h1 class="text-2xl font-bold text-[#234222]">Scaffolding Materials Report</h1><p class="mt-1 text-sm text-slate-500">Materials at projects, locations and yards, based on recorded stock movements.</p></div><Link :href="route('scaffolding.index')" class="btn">Scaffolding Register</Link></header>
            <form class="flex flex-wrap items-end gap-4 rounded-2xl border bg-white p-5" @submit.prevent="submit"><label class="text-sm">As of date<input v-model="form.as_of" required type="date" class="mt-1 block rounded-xl border-slate-200" /></label><div class="w-64"><CompanyField v-model="form.company" filter /></div><button class="btn bg-[#234222] text-white" :disabled="form.processing">Generate report</button><p v-for="error in form.errors" :key="error" class="text-sm text-red-700">{{ error }}</p></form>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm"><p class="font-bold">Report basis and data gaps</p><p v-for="warning in report.warnings" :key="warning" class="mt-2 font-semibold">{{ warning }}</p><p v-for="note in report.notes" :key="note" class="mt-2">{{ note }}</p></div>
            <section class="rounded-2xl border bg-white p-5">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3"><div><h2 class="font-bold">{{ report.title }} — AS OF {{ report.as_of }}</h2><p class="mt-1 text-xs text-slate-500">{{ report.company }} · Generated {{ report.generated_at }}</p></div><div class="flex gap-2"><a :href="route('miri-reports.scaffolding', { as_of: filters.as_of, company: filters.company, format: 'xlsx' })" class="btn text-green-800">Download Excel</a><a :href="route('miri-reports.scaffolding', { as_of: filters.as_of, company: filters.company, format: 'pdf' })" class="btn text-green-800">Download PDF</a></div></div>
                <p v-if="!report.columns.length" class="py-8 text-center text-slate-500">No scaffolding materials match this company.</p>
                <div v-else class="max-h-[70vh] overflow-auto border"><table class="w-full border-separate border-spacing-0 text-xs"><thead class="sticky top-0 z-20"><tr><th rowspan="2" class="sticky left-0 z-30 min-w-72 border-b border-r bg-green-100 p-3 text-left">PROJECTS &amp; LOCATIONS<br><span class="font-normal">Contract / MR references</span></th><th v-for="group in report.groups" :key="group.label" :colspan="group.span" class="border-b border-r bg-green-100 p-3">{{ group.label }}</th></tr><tr><th v-for="column in report.columns" :key="column.key" class="min-w-28 border-b border-r bg-rose-100 p-3">{{ column.label }}<span class="mt-1 block font-normal">{{ column.unit }}</span></th></tr></thead><tbody><tr v-for="(row, index) in report.rows" :key="index"><th class="sticky left-0 z-10 border-b border-r p-3 text-left" :class="row.kind === 'yard' ? 'bg-green-50' : 'bg-white'"><span class="block">{{ row.label }}</span><span v-if="row.contract" class="mt-1 block">{{ row.contract }}</span><span v-if="row.mr.length" class="mt-1 block font-normal">MR: {{ row.mr.join(', ') }}</span></th><td v-for="column in report.columns" :key="column.key" class="border-b border-r p-3 text-center" :class="row.kind === 'yard' ? 'bg-green-50 font-semibold' : ''">{{ number(row.values[column.key]) }}</td></tr></tbody></table></div>
                <p class="mt-3 text-xs text-slate-500">PDF uses landscape A3 panels with repeated location columns to keep wide reports readable. Excel retains the full matrix with frozen headings.</p>
            </section>
            <section v-if="report.unallocatedReturns.length" class="rounded-2xl border bg-white p-5"><h2 class="font-bold">Returns requiring source-project review</h2><p class="mt-1 text-sm text-slate-500">These quantities are already included in yard stock. Their source projects cannot be determined from the saved records.</p><p v-for="(entry, index) in report.unallocatedReturns" :key="index" class="mt-2 text-sm">{{ entry.label }}: <strong>{{ number(entry.quantity) }} {{ entry.unit }}</strong></p></section>
        </div>
    </AuthenticatedLayout>
</template>
