<script setup>
import { computed, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import CustomSelect from '@/Components/CustomSelect.vue';
const props = defineProps({ record: Object, movements: Object, canEdit: Boolean });
const form = useForm({ kind: props.record.stock_initialized_at ? 'receipt' : 'opening', quantity: '', unit: props.record.unit || '', location: props.record.current_location || '', reference: '', note: '', confirmed: false, stock_token: props.record.stock_token, request_key: crypto.randomUUID() });
const qty = value => value == null ? 'Not recorded' : Number(value).toLocaleString('en-GB', { maximumFractionDigits: 3 });
const result = computed(() => form.quantity === '' ? null : ['opening', 'correction'].includes(form.kind) ? Number(form.quantity) : Number(props.record.stock_balance) + Number(form.quantity) * (form.kind === 'writeoff' ? -1 : 1));
watch(() => [form.kind, form.quantity, form.unit, form.location, form.note, form.reference], () => form.confirmed = false);
watch(() => props.record.stock_token, token => { form.stock_token = token; form.confirmed = false; });
const submit = () => form.post(route('construction.stock', props.record.id), { preserveScroll: true, onSuccess: () => {
    form.kind = 'receipt'; form.quantity = ''; form.note = ''; form.reference = ''; form.confirmed = false;
    form.stock_token = props.record.stock_token; form.request_key = crypto.randomUUID();
} });
const names = { opening: 'Verified opening balance', receipt: 'Stock receipt', issue: 'COG issue', backload: 'COG backload', supplier_return: 'Supplier return', transfer_out: 'Transfer out', transfer_in: 'Transfer in', writeoff: 'Write-off', correction: 'Reviewed correction' };
</script>
<template>
    <section class="rounded-3xl border border-[#d8e7d4] bg-white p-6">
        <h2 class="text-lg font-bold text-[#234222]">Stock balance and movements</h2>
        <p class="mt-3 text-3xl font-bold text-[#234222]">{{ qty(record.stock_balance) }} <span class="text-base">{{ record.unit }}</span></p>
        <p class="mt-2 text-sm text-slate-600">{{ record.current_location || 'Location not recorded' }}</p>
        <p v-if="!record.stock_initialized_at" class="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">Verify the stock remaining today before recording new movements. Historical receipts and issues are not added again. A recorded balance above is unverified until you confirm the opening balance.</p>
        <p v-else class="mt-3 text-sm text-slate-600">Balance updates when stock movements are confirmed. Use Internal Issue Note for issues, backloads, supplier returns and transfers. Draft COGs do not change this balance.</p>
        <form v-if="canEdit" class="mt-5 border-t pt-5" @submit.prevent="submit">
            <div class="grid gap-4 md:grid-cols-2">
                <label class="text-sm">Movement<CustomSelect v-model="form.kind" class="mt-2 w-full">
                    <option v-if="!record.stock_initialized_at" value="opening">Verify opening balance</option>
                    <template v-else><option value="receipt">Receive new stock</option><option value="writeoff">Write off stock</option><option value="correction">Correct balance after review</option></template>
                </CustomSelect></label>
                <label class="text-sm">{{ ['opening', 'correction'].includes(form.kind) ? 'Verified remaining quantity' : 'Movement quantity' }}<input v-model="form.quantity" required type="number" :min="['opening', 'correction'].includes(form.kind) ? 0 : 0.001" max="999999999.999" step="0.001" class="input input-bordered mt-2 w-full" /></label>
                <template v-if="!record.stock_initialized_at">
                    <label class="text-sm">Unit<input v-model="form.unit" required maxlength="20" class="input input-bordered mt-2 w-full" placeholder="TON / PC / UNIT" /></label>
                    <label class="text-sm">Storage location<input v-model="form.location" required maxlength="255" class="input input-bordered mt-2 w-full" /></label>
                </template>
                <label class="text-sm">Receipt / document reference<input v-model="form.reference" maxlength="255" class="input input-bordered mt-2 w-full" /></label>
                <label class="text-sm">{{ form.kind === 'opening' ? 'How the opening balance was verified' : 'Reason / movement details' }}<textarea v-model="form.note" required maxlength="1000" class="textarea textarea-bordered mt-2 w-full" /></label>
            </div>
            <p v-if="result !== null" class="mt-4 font-semibold">Balance after confirmation: {{ qty(result) }} {{ record.stock_initialized_at ? record.unit : form.unit }}</p>
            <label class="mt-4 flex items-start gap-2 text-sm"><input v-model="form.confirmed" required type="checkbox" class="mt-1" />I have checked this quantity and confirm the stock movement.</label>
            <div v-if="Object.keys(form.errors).length" role="alert" class="mt-3 text-sm text-red-700"><p v-for="(error, key) in form.errors" :key="key">{{ error }}</p></div>
            <button class="btn mt-4 bg-[#234222] text-white" :disabled="form.processing || !form.confirmed || result === null || result < 0">Confirm stock movement</button>
        </form>
        <h3 class="mt-6 border-t pt-5 font-bold">Movement history</h3>
        <div class="mt-3 overflow-x-auto"><table class="table text-sm"><thead><tr><th>Date</th><th>Movement</th><th>Quantity</th><th>Balance after</th><th>Reference / reason</th></tr></thead><tbody>
            <tr v-for="movement in movements.data" :key="movement.id"><td>{{ movement.created_at }}</td><td>{{ names[movement.kind] || movement.kind }}</td><td>{{ Number(movement.quantity) > 0 ? '+' : '' }}{{ qty(movement.quantity) }} {{ movement.unit }}</td><td>{{ qty(movement.balance_after) }} {{ movement.unit }}</td><td class="whitespace-pre-wrap">{{ movement.reference }}<p>{{ movement.note }}</p><p class="text-xs text-slate-500">{{ movement.location }} · User #{{ movement.user_id }}</p></td></tr>
            <tr v-if="!movements.data.length"><td colspan="5">No confirmed stock movements yet.</td></tr>
        </tbody></table></div>
        <nav v-if="movements.last_page > 1" class="mt-3 flex items-center justify-between text-sm"><Link v-if="movements.prev_page_url" :href="movements.prev_page_url" preserve-scroll>Previous</Link><span>{{ movements.current_page }} / {{ movements.last_page }}</span><Link v-if="movements.next_page_url" :href="movements.next_page_url" preserve-scroll>Next</Link></nav>
    </section>
</template>
