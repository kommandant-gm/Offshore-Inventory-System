<script setup>
import PaintStockCards from '@/Components/PaintStockCards.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
const props = defineProps({ records: Object, filters: Object, summary: Object, stockSummary: Object, options: Object, canEdit: Boolean });
const form = useForm({ search: props.filters.search || '', section_2: props.filters.section_2 || '', location: props.filters.location || '', quality: props.filters.quality || '' });
const apply = () => form.get(route('paint.index'), { preserveState: true, preserveScroll: true });
const qty = value => value === null ? 'Not recorded' : Number(value).toLocaleString('en-GB', { maximumFractionDigits: 3 });
const cards = [['total','Records',''], ['unconfirmed_dates','Unconfirmed dates','unconfirmed'], ['expired','Past best before','expired'], ['due_30_days','Best before in 30 days','due_30_days'], ['duplicates','Possible repeat rows','duplicates'], ['review','Needs review','review']];
</script>
<template>
    <Head title="Miri Paint Register" />
    <AuthenticatedLayout>
        <section class="space-y-5">
            <header class="rounded-3xl bg-gradient-to-r from-[#075442] to-[#218487] p-7 text-white">
                <p class="text-xs font-bold tracking-widest text-teal-200">MIRI INVENTORY</p>
                <h1 class="mt-3 text-3xl font-bold">Paint Register</h1>
                <p class="mt-3 text-sm text-teal-100">Paint batches, stock snapshots and confirmed shelf-life dates.</p>
                <div v-if="canEdit" class="mt-5 flex flex-wrap gap-3"><Link :href="route('paint.create')" class="btn bg-white text-[#234222]">Register paint</Link><Link :href="route('paint.import')" class="btn border-white/30 bg-transparent text-white">Import CSV</Link></div>
            </header>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6"><Link v-for="[key,label,quality] in cards" :key="key" :href="route('paint.index', quality ? { quality } : {})" class="rounded-2xl border border-[#d8e7d4] bg-white p-4"><p class="text-xs text-slate-500">{{ label }}</p><p class="mt-2 text-2xl font-bold text-[#234222]">{{ summary[key] }}</p></Link></div>
            <p class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900">Single dates remain unconfirmed and are excluded from best-before alerts. Cans and litres stay separate. Blank balances are unknown, not zero. Repeated batches are review candidates, not automatically merged.</p>
            <form class="grid gap-3 rounded-2xl border bg-white p-5 sm:grid-cols-2 lg:grid-cols-5" @submit.prevent="apply">
                <label class="text-xs text-slate-600">Search<input v-model="form.search" class="mt-2 w-full rounded-xl border-slate-200" placeholder="Description, batch, location…" /></label>
                <label class="text-xs text-slate-600">Paint type<select v-model="form.section_2" class="mt-2 w-full rounded-xl border-slate-200"><option value="">All paint types</option><option v-for="value in options.section_2" :key="value">{{ value }}</option></select></label>
                <label class="text-xs text-slate-600">Current location<select v-model="form.location" class="mt-2 w-full rounded-xl border-slate-200"><option value="">All locations</option><option v-for="value in options.location" :key="value">{{ value }}</option></select></label>
                <label class="text-xs text-slate-600">Review / dates<select v-model="form.quality" class="mt-2 w-full rounded-xl border-slate-200"><option value="">All records</option><option value="review">Needs review</option><option value="duplicates">Possible repeat rows</option><option value="unconfirmed">Unconfirmed dates</option><option value="expired">Past best before</option><option value="due_30_days">Best before in 30 days</option></select></label>
                <div class="flex items-end gap-2"><button class="btn bg-[#4f9f4a] text-white" :disabled="form.processing">Apply</button><Link :href="route('paint.index')" class="btn">Clear</Link></div>
            </form>
            <PaintStockCards :stock-summary="stockSummary" scope-label="Follows applied filters, not just this page" />
            <div class="overflow-hidden rounded-3xl border border-[#d8e7d4] bg-white">
                <p class="p-5 text-sm text-slate-600">{{ records.total }} records · Showing {{ records.from || 0 }}–{{ records.to || 0 }}</p>
                <div class="overflow-x-auto"><table class="table"><thead><tr><th>Description / Type</th><th>Batch</th><th>Location</th><th>Balance CAN</th><th>Balance LTR</th><th>Best before</th><th>Review</th><th>Actions</th></tr></thead><tbody>
                    <tr v-for="item in records.data" :key="item.id"><td><Link :href="route('paint.show',item.id)" class="font-semibold text-green-800">{{ item.description || 'Not recorded' }}</Link><p class="text-xs text-slate-500">{{ item.section_2 }}</p></td><td>{{ item.batch_no || 'Not recorded' }}</td><td>{{ item.current_location || 'Not recorded' }}</td><td>{{ qty(item.balance_cans) }}</td><td>{{ qty(item.balance_litres) }}</td><td>{{ item.date_status === 'unconfirmed' ? 'Needs confirmation' : item.best_before_date || 'Not recorded' }}</td><td><p v-if="item.duplicate_count > 1" class="text-xs text-amber-700">Possible repeat</p><p v-if="item.needs_review" class="text-xs text-amber-700">Review details</p><span v-if="!item.needs_review && item.duplicate_count <= 1">—</span></td><td><div class="flex gap-3"><Link :href="route('paint.show',item.id)">View</Link><Link v-if="canEdit" :href="route('paint.edit',item.id)" class="text-green-700">Edit</Link></div></td></tr>
                    <tr v-if="!records.data.length"><td colspan="8" class="p-8 text-center text-slate-500">No matching paint records. Queued imports appear after processing completes.</td></tr>
                </tbody></table></div>
                <nav class="flex items-center justify-between border-t p-4" aria-label="Paint register pages"><Link v-if="records.prev_page_url" :href="records.prev_page_url" class="btn">Previous</Link><span v-else></span><span class="text-sm">{{ records.current_page }} / {{ records.last_page }}</span><Link v-if="records.next_page_url" :href="records.next_page_url" class="btn">Next</Link><span v-else></span></nav>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
