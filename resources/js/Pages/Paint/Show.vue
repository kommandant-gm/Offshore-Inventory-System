<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
const props = defineProps({ record: Object, fields: Array, duplicates: Array, stockMonths: Array, stockMovements: Object, canEdit: Boolean });
const groups = [...new Set(props.fields.map(f => f.group))];
</script>
<template>
    <Head title="Paint record" />
    <AuthenticatedLayout>
        <section class="space-y-5">
            <header class="rounded-3xl border border-[#d8e7d4] bg-white p-6"><h1 class="text-2xl font-bold text-[#234222]">{{ record.description || 'Paint record #' + record.id }}</h1><p class="mt-2 text-sm text-slate-500">Batch {{ record.batch_no || 'not recorded' }} · Miri Paint Register</p><div class="mt-4 flex gap-3"><Link :href="route('paint.index')" class="btn">Back to register</Link><Link v-if="canEdit" :href="route('paint.edit',record.id)" class="btn bg-[#4f9f4a] text-white">Edit / review dates</Link></div></header>
            <p class="rounded-xl border bg-white p-4 text-sm">Company: <strong>{{ record.company || 'Not assigned' }}</strong></p>
            <div v-if="record.review_flags.length || duplicates.length" class="rounded-xl bg-amber-50 p-5 text-sm text-amber-900"><p v-for="flag in record.review_flags" :key="flag">{{ flag }}</p><div v-if="duplicates.length" class="mt-3"><p>Possible repeated description + batch + location. No rows have been merged.</p><Link v-for="item in duplicates" :key="item.id" :href="route('paint.show',item.id)" class="mr-3 underline">Record #{{ item.id }}</Link></div></div>
            <section class="rounded-3xl border border-[#d8e7d4] bg-white p-6"><h2 class="font-bold text-[#234222]">Date interpretation</h2><dl class="mt-4 grid gap-4 sm:grid-cols-3"><div><dt class="text-sm text-slate-500">Date meaning</dt><dd>{{ record.date_status }}</dd></div><div><dt class="text-sm text-slate-500">Manufacture date</dt><dd>{{ record.manufacture_date || 'Not recorded' }}</dd></div><div><dt class="text-sm text-slate-500">Best-before date</dt><dd>{{ record.date_status === 'unconfirmed' ? 'Awaiting confirmation' : record.best_before_date || 'Not recorded' }}</dd></div></dl><p v-if="record.review_note" class="mt-4 whitespace-pre-wrap text-sm">Review note: {{ record.review_note }}</p></section>
            <section v-for="group in groups" :key="group" class="rounded-3xl border border-[#d8e7d4] bg-white p-6"><h2 class="font-bold text-[#234222]">{{ group }}</h2><dl class="mt-4 grid gap-5 md:grid-cols-2 xl:grid-cols-3"><div v-for="field in fields.filter(f=>f.group===group)" :key="field.key"><dt class="text-xs text-slate-500">{{ field.label }}</dt><dd class="mt-1 whitespace-pre-wrap break-words text-sm">{{ record[field.key] ?? 'Not recorded' }}</dd></div></dl></section>
            <section class="rounded-3xl border bg-white p-6">
                <h2 class="font-bold text-[#234222]">Monthly stock history</h2>
                <p class="mt-2 text-sm text-slate-500">Current period: {{ record.stock_period }}. Closed months are preserved; movements use their posting month.</p>
                <div class="mt-4 overflow-x-auto"><table class="table"><thead><tr><th>Month</th><th>Opening CAN</th><th>Closing CAN</th><th>Opening LTR</th><th>Closing LTR</th></tr></thead><tbody><tr v-for="month in stockMonths" :key="month.id"><td>{{ month.period.slice(0, 7) }}</td><td>{{ month.opening_cans ?? 'Unknown' }}</td><td>{{ month.closing_cans ?? 'Unknown' }}</td><td>{{ month.opening_litres ?? 'Unknown' }}</td><td>{{ month.closing_litres ?? 'Unknown' }}</td></tr><tr v-if="!stockMonths.length"><td colspan="5">No closed months yet.</td></tr></tbody></table></div>
            </section>
            <section class="rounded-3xl border bg-white p-6">
                <h2 class="font-bold text-[#234222]">Stock movements</h2>
                <div class="mt-4 overflow-x-auto"><table class="table"><thead><tr><th>Posted</th><th>Type / Reference</th><th>Unit</th><th>Change</th><th>Before</th><th>After</th></tr></thead><tbody><tr v-for="entry in stockMovements.data" :key="entry.id"><td>{{ entry.created_at }}</td><td>{{ entry.kind }}<p class="text-xs">{{ entry.note }}</p></td><td>{{ entry.unit }}</td><td>{{ entry.quantity ?? 'Unknown' }}</td><td>{{ entry.balance_before ?? 'Unknown' }}</td><td>{{ entry.balance_after ?? 'Unknown' }}</td></tr><tr v-if="!stockMovements.data.length"><td colspan="6">No stock movements posted yet.</td></tr></tbody></table></div>
                <nav class="mt-4 flex justify-between" aria-label="Stock movement pages"><Link v-if="stockMovements.prev_page_url" :href="stockMovements.prev_page_url" class="btn btn-sm">Previous</Link><span>{{ stockMovements.current_page }} / {{ stockMovements.last_page }}</span><Link v-if="stockMovements.next_page_url" :href="stockMovements.next_page_url" class="btn btn-sm">Next</Link></nav>
            </section>
            <details v-if="record.original_values" class="rounded-2xl border bg-white p-5"><summary class="cursor-pointer font-semibold">Original CSV record {{ record.source_row }} · {{ record.source_filename }}</summary><dl class="mt-4 grid gap-3 md:grid-cols-2"><div v-for="(field,index) in fields" :key="field.key"><dt class="text-xs text-slate-500">{{ field.group }} / {{ field.label }}</dt><dd class="whitespace-pre-wrap text-sm">{{ record.original_values[index] || 'Blank' }}</dd></div></dl></details>
        </section>
    </AuthenticatedLayout>
</template>
