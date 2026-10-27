<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ScaffoldingReportSheet from '@/Components/ScaffoldingReportSheet.vue';
import CompanyField from '@/Components/CompanyField.vue';
const props = defineProps({ filters: Object, report: Object, layout: Object });
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
                <ScaffoldingReportSheet :layout="layout" />
                <p class="mt-3 text-xs text-slate-500">Layout matches the supplied Scaffold Inventory Report sheet. Excel includes a separate data and notes sheet. Unknown quantities remain unknown in totals.</p>
                <div v-if="layout.unmapped.length" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm"><strong>Materials outside the template columns</strong><p class="mt-1">These items cannot be safely matched to a fixed column and are excluded from the form totals. Their quantities remain in the Excel detail sheet.</p><p v-for="column in layout.unmapped" :key="column.key" class="mt-2">{{ column.group }} / {{ column.label }} ({{ column.unit }})</p></div>
            </section>
            <section v-if="report.unallocatedReturns.length" class="rounded-2xl border bg-white p-5"><h2 class="font-bold">Returns requiring source-project review</h2><p class="mt-1 text-sm text-slate-500">These quantities are already included in yard stock. Their source projects cannot be determined from the saved records.</p><p v-for="(entry, index) in report.unallocatedReturns" :key="index" class="mt-2 text-sm">{{ entry.label }}: <strong>{{ number(entry.quantity) }} {{ entry.unit }}</strong></p></section>
        </div>
    </AuthenticatedLayout>
</template>
