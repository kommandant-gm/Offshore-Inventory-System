<script setup>
import { computed, ref, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({ filters: Object, report: Object, columns: Object });
const form = useForm({ month: props.filters.month });
const page = ref(1);
const dirty = computed(() => form.month !== props.filters.month);
const rows = computed(() => props.report?.rows.slice((page.value - 1) * 25, page.value * 25) ?? []);
const pages = computed(() => Math.max(1, Math.ceil((props.report?.rows.length ?? 0) / 25)));
watch(() => props.report, () => { page.value = 1; });
const preview = () => form.transform(data => ({ ...data, preview: 1 })).get(route('miri-reports.index'), { preserveState: true, preserveScroll: true });
const display = value => value === null || value === '' ? '—' : typeof value === 'number' ? value.toLocaleString(undefined, { maximumFractionDigits: 3 }) : value;
</script>

<template>
    <Head title="Reports" />
    <AuthenticatedLayout>
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-[#234222]">Reports</h1>
                <p class="mt-1 text-sm text-slate-500">Monthly paint inventory · Preview recorded stock and export to Excel.</p>
            </div>
            <form class="rounded-2xl border border-[#d8e7d4] bg-white p-5" @submit.prevent="preview">
                <div class="grid gap-4 md:grid-cols-3">
                    <label class="text-sm">Month
                        <input v-model="form.month" type="month" required class="input input-bordered mt-2 w-full" />
                        <span v-if="form.errors.month" class="text-red-700">{{ form.errors.month }}</span>
                    </label>
                    <p class="self-center text-sm text-slate-500 md:col-span-2">Combined report for Bintulu and Labuan, including Hempel and IP paint.</p>
                </div>
                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <button class="btn bg-[#4f9f4a] text-white" :disabled="form.processing">{{ form.processing ? 'Preparing…' : 'Preview report' }}</button>
                    <a v-if="report && !report.unavailable && !dirty && !form.processing" :href="route('miri-reports.paint.export', filters)" class="btn btn-outline">Export Excel</a>
                    <span v-if="dirty && report" class="text-sm text-amber-800">Filters changed. Preview again before exporting.</span>
                </div>
            </form>
            <template v-if="report">
                <p v-if="report.unavailable" role="alert" class="rounded-xl bg-amber-50 p-4 text-amber-900">{{ report.unavailable }}</p>
                <template v-else>
                    <section class="overflow-hidden rounded-2xl border border-[#d8e7d4] bg-white">
                        <div class="border-b p-4">
                            <h2 class="font-bold">Paint inventory · {{ filters.month }} · {{ filters.location }} · {{ filters.brand === 'all' ? 'All paint types' : filters.brand }}</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ report.rows.length }} rows · Quantities remain in their recorded units. — means unavailable.</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-green-50"><tr><th v-for="(label, key) in columns" :key="key" class="whitespace-nowrap p-3">{{ label }}</th></tr></thead>
                                <tbody>
                                    <tr v-for="row in rows" :key="`${row.id}-${row.unit}`" class="border-t align-top">
                                        <td v-for="(label, key) in columns" :key="key" class="p-3" :class="['description', 'remarks', 'location'].includes(key) ? 'min-w-52' : 'whitespace-nowrap'">{{ display(row[key]) }}</td>
                                    </tr>
                                    <tr v-if="!rows.length"><td :colspan="Object.keys(columns).length" class="p-6 text-center text-slate-500">No paint records match this location, type and month.</td></tr>
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
