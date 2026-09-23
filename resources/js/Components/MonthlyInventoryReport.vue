<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CustomSelect from '@/Components/CustomSelect.vue';

const props = defineProps({ filters: Object, report: Object, columns: Object, options: Object, title: String, description: String, scope: String, previewRoute: String, exportRoute: String });
const form = useForm({ ...props.filters });
const page = ref(1);
const dirty = computed(() => Object.keys(props.filters).some(key => form[key] !== props.filters[key]));
const rows = computed(() => props.report?.rows.slice((page.value - 1) * 25, page.value * 25) ?? []);
const pages = computed(() => Math.max(1, Math.ceil((props.report?.rows.length ?? 0) / 25)));
watch(() => props.report, () => { page.value = 1; });
const preview = () => form.transform(data => ({ ...data, preview: 1 })).get(route(props.previewRoute), { preserveState: true, preserveScroll: true });
const display = value => value === null || value === '' ? '—' : typeof value === 'number' ? value.toLocaleString(undefined, { maximumFractionDigits: 3 }) : value;
</script>

<template>
    <Head :title="title" />
    <AuthenticatedLayout>
        <div class="space-y-6">
            <div>
                <Link :href="route('miri-reports.index')" class="text-sm text-green-700 hover:underline">Back to Reports</Link>
                <h1 class="mt-2 text-2xl font-bold text-[#234222]">{{ title }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ description }}</p>
            </div>
            <form class="rounded-2xl border border-[#d8e7d4] bg-white p-5" @submit.prevent="preview">
                <div class="grid gap-4 md:grid-cols-3">
                    <label class="text-sm">Month
                        <input v-model="form.month" type="month" required class="input input-bordered mt-2 w-full" />
                        <span v-if="form.errors.month" class="text-red-700">{{ form.errors.month }}</span>
                    </label>
                    <template v-if="options">
                        <label class="text-sm">Project / Contract
                            <CustomSelect v-model="form.project" class="select select-bordered mt-2 w-full"><option value="">All projects</option><option v-for="value in options.projects" :key="value" :value="value">{{ value }}</option></CustomSelect>
                            <span v-if="form.errors.project" class="text-red-700">{{ form.errors.project }}</span>
                        </label>
                        <label class="text-sm">Warehouse / Location
                            <CustomSelect v-model="form.location" class="select select-bordered mt-2 w-full"><option value="">All locations</option><option v-for="value in options.locations" :key="value" :value="value">{{ value }}</option></CustomSelect>
                            <span v-if="form.errors.location" class="text-red-700">{{ form.errors.location }}</span>
                        </label>
                    </template>
                    <p class="self-center text-sm text-slate-500" :class="options ? 'md:col-span-3' : 'md:col-span-2'">{{ scope }}</p>
                </div>
                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <button class="btn bg-[#4f9f4a] text-white" :disabled="form.processing">{{ form.processing ? 'Preparing…' : 'Preview report' }}</button>
                    <a v-if="report && !report.unavailable && !dirty && !form.processing" :href="route(exportRoute, filters)" class="btn btn-outline">Export Excel</a>
                    <span v-if="dirty && report" class="text-sm text-amber-800">Filters changed. Preview again before exporting.</span>
                </div>
            </form>
            <template v-if="report">
                <p v-if="report.unavailable" role="alert" class="rounded-xl bg-amber-50 p-4 text-amber-900">{{ report.unavailable }}</p>
                <template v-else>
                    <section class="overflow-hidden rounded-2xl border border-[#d8e7d4] bg-white">
                        <div class="border-b p-4">
                            <h2 class="font-bold">{{ title }} - {{ filters.month }}</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ report.rows.length }} rows · Quantities remain in their recorded units. — means unavailable.</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-green-50"><tr><th v-for="(label, key) in columns" :key="key" class="whitespace-nowrap p-3">{{ label }}</th></tr></thead>
                                <tbody>
                                    <tr v-for="row in rows" :key="`${row.id}-${row.unit}`" class="border-t align-top">
                                        <td v-for="(label, key) in columns" :key="key" class="p-3" :class="['description', 'remarks', 'location'].includes(key) ? 'min-w-52' : 'whitespace-nowrap'">{{ display(row[key]) }}</td>
                                    </tr>
                                    <tr v-if="!rows.length"><td :colspan="Object.keys(columns).length" class="p-6 text-center text-slate-500">No records match this report and month.</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="flex items-center justify-between border-t p-4 text-sm">
                            <button class="btn btn-sm" :disabled="page === 1" @click="page--">Previous</button>
                            <span>Page {{ page }} of {{ pages }} · Excel includes all rows</span>
                            <button class="btn btn-sm" :disabled="page >= pages" @click="page++">Next</button>
                        </div>
                    </section>
                    <details class="rounded-2xl border border-[#d8e7d4] bg-white p-5" open>
                        <summary class="cursor-pointer font-semibold">Report coverage</summary>
                        <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-slate-600"><li v-for="note in report.notes" :key="note">{{ note }}</li></ul>
                    </details>
                </template>
            </template>
        </div>
    </AuthenticatedLayout>
</template>
