<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import WorkbookSheetPreview from '@/Components/WorkbookSheetPreview.vue';
const props = defineProps({ filters: Object, report: Object, layout: Object });
const form = useForm({ month: props.filters.month, location: 'LBN', preview: 1 });
const preview = () => form.get(route('miri-reports.labuan-ppe'), { preserveState: false });
</script>
<template>
    <Head title="Labuan Warehouse PPE Report" />
    <AuthenticatedLayout>
        <div class="space-y-5">
            <header class="flex flex-wrap justify-between gap-4"><div><h1 class="text-2xl font-bold text-[#234222]">Labuan Warehouse PPE Report</h1><p class="mt-2 text-sm text-slate-500">Monthly Labuan PPE stock using the supplied PPE STORE worksheet layout.</p></div><Link :href="route('miri-reports.index')" class="btn">All reports</Link></header>
            <form class="flex flex-wrap items-end gap-4 rounded-2xl border bg-white p-5" @submit.prevent="preview"><label class="text-sm">Month<input v-model="form.month" type="month" required class="mt-1 block rounded-xl border-slate-200" /></label><button class="btn bg-[#234222] text-white" :disabled="form.processing">Preview report</button><a v-if="report && !report.unavailable" :href="route('miri-reports.labuan-ppe.export', { month: filters.month, location: 'LBN' })" class="btn text-green-800">Download Excel</a><p v-for="error in form.errors" :key="error" class="text-sm text-red-700">{{ error }}</p></form>
            <p class="text-sm text-slate-600">Includes Labuan/LBN items in the PPE category only. Draft issue notes do not change stock. Blank quantities mean unavailable; zero means recorded zero.</p>
            <template v-if="report"><p v-if="report.unavailable" class="rounded-xl border border-amber-200 bg-amber-50 p-4">{{ report.unavailable }}</p><template v-else><p class="text-sm text-slate-500">{{ report.rows.length }} PPE records · Excel includes all rows.</p><WorkbookSheetPreview v-if="layout" :layout="layout" /><p v-if="!report.rows.length" class="text-sm text-slate-500">No Labuan PPE records match this month. The blank form remains available.</p></template><details class="rounded-2xl border bg-white p-5"><summary class="cursor-pointer font-semibold">Report coverage and data notes</summary><ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-slate-600"><li v-for="(note, index) in report.notes" :key="index">{{ note }}</li></ul></details></template>
        </div>
    </AuthenticatedLayout>
</template>
