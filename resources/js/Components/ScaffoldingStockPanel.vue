<script setup>
import { computed, ref, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
const props = defineProps({ equipment: Object, stock: Object, canEdit: Boolean });
const yard = ref('BINTULU');
const selected = computed(() => props.stock.yards.find(row => row.yard === yard.value));
const today = () => new Date().toLocaleDateString('en-CA');
const row = () => ({ quantity: '', destination: '', contract: props.equipment.scaffolding_contract || '', mr_reference: '', reference: '', document_date: today(), note: '' });
const form = useForm({ kind: 'opening', yard: yard.value, stock_token: '', request_key: crypto.randomUUID(), confirmed: false, ...row(), rows: [row()] });
function reset() {
    form.reset(); form.clearErrors(); form.yard = yard.value; form.kind = selected.value.account ? 'issue' : 'opening';
    form.stock_token = selected.value.token; form.request_key = crypto.randomUUID(); form.rows = [row()]; form.confirmed = false;
}
watch(yard, reset, { immediate: true });
watch(() => form.kind, () => { form.confirmed = false; form.clearErrors(); });
const balances = computed(() => {
    let balance = Math.round(Number(selected.value.account?.balance || 0) * 1000);
    return form.rows.map(row => { balance -= Math.round(Number(row.quantity || 0) * 1000); return balance / 1000; });
});
const submit = () => form.post(route('scaffolding.stock.store', props.equipment.id), { preserveScroll: true, onSuccess: reset });
const number = value => Number(value).toLocaleString(undefined, { maximumFractionDigits: 3 });
</script>
<template>
    <section class="mb-6 space-y-6 rounded-3xl border border-[#d8e7d4] bg-white p-6">
        <h2 class="text-lg font-bold text-[#234222]">Scaffold classification</h2>
        <dl class="grid gap-4 sm:grid-cols-4">
            <div><dt class="text-xs text-slate-500">Category</dt><dd>SCAFFOLD SET</dd></div>
            <div><dt class="text-xs text-slate-500">Subcategory</dt><dd>{{ equipment.section_2 || 'Not recorded' }}</dd></div>
            <div><dt class="text-xs text-slate-500">Default contract</dt><dd>{{ equipment.scaffolding_contract || 'Not recorded' }}</dd></div>
            <label class="text-sm">Stock location<select v-model="yard" class="mt-1 w-full rounded-xl border-slate-200"><option v-for="entry in stock.yards" :key="entry.yard">{{ entry.yard }}</option></select></label>
        </dl>
        <div class="border-t pt-5">
            <h2 class="text-lg font-bold text-[#234222]">Scaffold detail</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-4">
                <div><dt class="text-xs text-slate-500">Description</dt><dd>{{ equipment.description }}</dd></div>
                <div><dt class="text-xs text-slate-500">Unit</dt><dd>{{ equipment.unit || 'Save a unit in Edit details' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Initial stock quantity</dt><dd>{{ selected.account ? number(selected.account.opening_quantity) : 'Awaiting verification' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Available stock</dt><dd class="text-xl font-bold text-green-800">{{ selected.account ? number(selected.account.balance) : 'Unverified' }}</dd></div>
            </dl>
            <p class="mt-3 text-xs text-slate-500">Stock is shared across contracts within each yard. Opening quantities must reflect verified stock on hand. Existing records and historical notes are not deducted again.</p>
        </div>
        <form v-if="canEdit" class="space-y-4 border-t pt-5" @submit.prevent="submit">
            <label class="block max-w-sm text-sm">Movement<select v-model="form.kind" class="mt-1 w-full rounded-xl border-slate-200">
                <option v-if="!selected.account" value="opening">Verify opening stock</option>
                <template v-else><option value="issue">Issue stock (external COG)</option><option value="receipt">Receive new stock</option><option value="backload">Receive backload</option><option value="writeoff">Write off stock</option><option value="correction">Correct balance to verified count</option></template>
            </select></label>
            <p v-if="form.kind === 'issue'" class="text-sm text-slate-600">Enter each issue with its COG below. For an Internal Issue Note created in this system, confirm stock from that note instead.</p>
            <div v-if="form.kind === 'issue'" class="overflow-x-auto">
                <table class="w-full min-w-[1250px] text-sm">
                    <thead class="bg-green-50 text-left"><tr><th class="p-2">Issued-out location / project</th><th>Contract</th><th>MR request</th><th>Qty issued</th><th>Balance (preview)</th><th>COG number</th><th>Issue-out date</th><th>Notes</th><th></th></tr></thead>
                    <tbody><tr v-for="(entry, index) in form.rows" :key="index" class="border-b">
                        <td v-for="key in ['destination', 'contract', 'mr_reference']" :key="key" class="p-1"><input v-model="entry[key]" :aria-label="key + ' row ' + (index + 1)" :required="key === 'destination'" maxlength="255" :list="key === 'contract' ? 'scaffold-contracts' : undefined" class="w-40 rounded-lg border-slate-200" /></td>
                        <td class="p-1"><input v-model="entry.quantity" :aria-label="'Quantity row ' + (index + 1)" required type="number" min="0.001" max="999999999.999" step="0.001" class="w-28 rounded-lg border-slate-200" /></td>
                        <td class="p-2 font-semibold" :class="balances[index] < 0 ? 'text-red-700' : 'text-green-800'">{{ number(balances[index]) }}</td>
                        <td class="p-1"><input v-model="entry.reference" :aria-label="'COG row ' + (index + 1)" required maxlength="255" class="w-36 rounded-lg border-slate-200" /></td>
                        <td class="p-1"><input v-model="entry.document_date" :aria-label="'Date row ' + (index + 1)" required type="date" class="rounded-lg border-slate-200" /></td>
                        <td class="p-1"><input v-model="entry.note" :aria-label="'Notes row ' + (index + 1)" required maxlength="1000" class="w-40 rounded-lg border-slate-200" /></td>
                        <td><button type="button" :disabled="form.rows.length === 1 || form.processing" class="text-red-700" @click="form.rows.splice(index, 1)">Remove</button></td>
                    </tr></tbody>
                </table>
                <button type="button" class="btn mt-3" :disabled="form.rows.length >= 100 || form.processing" @click="form.rows.push(row())">Add issue row</button>
            </div>
            <div v-else class="grid gap-4 sm:grid-cols-3">
                <label class="text-sm">{{ form.kind === 'correction' ? 'Correct balance to' : 'Quantity' }}<input v-model="form.quantity" required type="number" :min="['opening', 'correction'].includes(form.kind) ? 0 : 0.001" max="999999999.999" step="0.001" class="mt-1 w-full rounded-xl border-slate-200" /></label>
                <label class="text-sm">Document date<input v-model="form.document_date" required type="date" class="mt-1 w-full rounded-xl border-slate-200" /></label>
                <label class="text-sm">Reference / COG number<input v-model="form.reference" :required="form.kind === 'backload'" maxlength="255" class="mt-1 w-full rounded-xl border-slate-200" /></label>
                <label class="text-sm">Contract<input v-model="form.contract" list="scaffold-contracts" maxlength="255" class="mt-1 w-full rounded-xl border-slate-200" /></label>
                <label class="text-sm sm:col-span-2">Verification / movement notes<input v-model="form.note" required maxlength="1000" class="mt-1 w-full rounded-xl border-slate-200" /></label>
            </div>
            <datalist id="scaffold-contracts"><option v-for="contract in stock.options.contract" :key="contract" :value="contract" /></datalist>
            <label class="flex items-start gap-2 text-sm"><input v-model="form.confirmed" required type="checkbox" class="mt-1" />I have checked the quantities and yard and confirm this stock movement.</label>
            <p v-for="(error, key) in form.errors" :key="key" role="alert" class="text-sm text-red-700">{{ error }}</p>
            <button class="btn bg-[#4f9f4a] text-white" :disabled="form.processing || !form.confirmed || (form.kind === 'issue' && balances.some(value => value < 0))">{{ form.kind === 'opening' ? 'Verify opening stock' : 'Confirm stock movement' }}</button>
        </form>
        <div class="border-t pt-5">
            <h2 class="font-bold text-[#234222]">COG information and stock history</h2>
            <p class="mt-1 text-xs text-slate-500">Newest confirmations first. Balance is the balance immediately after that movement; backdated document dates do not reorder stock.</p>
            <div class="mt-3 overflow-x-auto"><table class="w-full min-w-[1000px] text-left text-sm"><thead class="bg-green-50"><tr><th class="p-2">Yard</th><th>Date</th><th>Movement</th><th>Location / project</th><th>Contract</th><th>MR</th><th>Qty change</th><th>Balance</th><th>COG / reference</th><th>Notes</th></tr></thead>
                <tbody><tr v-for="movement in stock.movements.data" :key="movement.id" class="border-b"><td class="p-2">{{ movement.yard }}</td><td>{{ movement.document_date }}</td><td>{{ movement.kind.replaceAll('_', ' ') }}</td><td>{{ movement.destination || '-' }}</td><td>{{ movement.contract || '-' }}</td><td>{{ movement.mr_reference || '-' }}</td><td>{{ number(movement.quantity) }} {{ movement.unit }}</td><td>{{ number(movement.balance_after) }}</td><td><Link v-if="movement.miri_cog_id" :href="route('miri-cogs.show', movement.miri_cog_id)" class="text-green-800 underline">{{ movement.reference }}</Link><span v-else>{{ movement.reference || '-' }}</span></td><td>{{ movement.note }}</td></tr><tr v-if="!stock.movements.data.length"><td colspan="10" class="p-4 text-slate-500">No confirmed movements yet.</td></tr></tbody>
            </table></div>
            <div class="mt-3 flex gap-3"><Link v-if="stock.movements.prev_page_url" :href="stock.movements.prev_page_url" preserve-scroll>Previous</Link><span class="text-sm">Page {{ stock.movements.current_page }} of {{ stock.movements.last_page }}</span><Link v-if="stock.movements.next_page_url" :href="stock.movements.next_page_url" preserve-scroll>Next</Link></div>
        </div>
    </section>
</template>
