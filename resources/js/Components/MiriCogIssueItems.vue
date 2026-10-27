<script setup>
import { computed, reactive, ref } from 'vue';
import CustomSelect from '@/Components/CustomSelect.vue';
import MiriCogItemPicker from '@/Components/MiriCogItemPicker.vue';

const props = defineProps({ modelValue: Array, registerTypes: Array, movementType: String });
const emit = defineEmits(['update:modelValue']);
let nextKey = 0;
const empty = (type = 'Major equipment') => ({ item_type: type, item_id: '', construction_destination_id: '', quantity: 1, unit: '', description: '', size_model: '', identifier: '', serial_no: '', mr_reference: '', remarks: '', batch_no: '', location: '' });
const draft = reactive(empty());
const error = ref('');
const excludedIds = computed(() => ['Major equipment', 'Rental'].includes(draft.item_type)
    ? props.modelValue.filter(row => row.item_type === draft.item_type).map(row => row.item_id) : []);
const label = type => type === 'Construction' ? 'TEC, Garnet & PPE' : type === 'Major equipment' ? 'Equipment (Machinery / Cargo / Scaffolding)' : type;
const sourceUnit = ref('');
function select(item) {
    draft.item_id = item?.id || '';
    draft.construction_destination_id = '';
    for (const key of ['description', 'size_model', 'identifier', 'serial_no', 'mr_reference', 'unit', 'batch_no', 'location']) draft[key] = item?.[key] || '';
    sourceUnit.value = item?.unit || '';
    error.value = '';
}
function add() {
    if (!draft.item_id) { error.value = 'Select an inventory item first.'; return; }
    if (!Number.isFinite(Number(draft.quantity)) || Number(draft.quantity) <= 0 || Number(draft.quantity) > 999999999.999 || !/^\d+(\.\d{1,3})?$/.test(String(draft.quantity))) { error.value = 'Enter a positive quantity with at most three decimal places.'; return; }
    if (!draft.unit.trim()) { error.value = 'Enter the unit missing from the register, or choose a paint unit.'; return; }
    if (draft.item_type === 'Construction' && props.movementType === 'Transfer' && !draft.construction_destination_id) { error.value = 'Select a destination stock record for this transfer.'; return; }
    if (excludedIds.value.includes(draft.item_id)) { error.value = 'This equipment is already in the list.'; return; }
    if (props.modelValue.length >= 100) { error.value = 'A note can contain up to 100 items.'; return; }
    emit('update:modelValue', [...props.modelValue, { ...draft, row_key: ++nextKey }]);
    Object.assign(draft, empty(draft.item_type));
    sourceUnit.value = '';
    error.value = '';
}
const remove = index => emit('update:modelValue', props.modelValue.filter((_, i) => i !== index));
</script>

<template>
    <section class="rounded-3xl border border-[#d8e7d4] bg-white p-6">
        <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="font-bold text-[#234222]">Issue items</h2><span class="text-sm text-slate-500">{{ modelValue.length }} / 100 items</span></div>
        <label class="my-4 block max-w-md text-sm">Register<CustomSelect v-model="draft.item_type" class="mt-1 w-full rounded-xl border-slate-200"><option v-for="type in registerTypes" :key="type" :value="type">{{ label(type) }}</option></CustomSelect></label>
        <MiriCogItemPicker v-model="draft.item_id" :type="draft.item_type" :movement-type="movementType" :excluded-ids="excludedIds" @selected="select">
            <label class="w-24 text-sm">QTY<input v-model="draft.quantity" type="number" min="0.001" max="999999999.999" step="0.001" class="mt-1 w-full rounded-xl border-slate-200" /></label>
            <label class="w-28 text-sm">Unit
                <CustomSelect v-if="draft.item_type === 'Paint'" v-model="draft.unit" class="mt-1 w-full rounded-xl border-slate-200"><option value="">Choose</option><option>CAN</option><option>LTR</option></CustomSelect>
                <input v-else v-model="draft.unit" :readonly="!!sourceUnit" maxlength="20" class="mt-1 w-full rounded-xl border-slate-200 read-only:bg-slate-50" :placeholder="draft.item_id ? 'Enter unit' : 'From register'" />
            </label>
            <label class="min-w-0 flex-[1_1_120px] text-sm">MR #<input v-model="draft.mr_reference" maxlength="1000" class="mt-1 w-full rounded-xl border-slate-200" /></label>
            <label class="min-w-0 flex-[1_1_180px] text-sm">Remarks<input v-model="draft.remarks" maxlength="1000" class="mt-1 w-full rounded-xl border-slate-200" /></label>
            <button type="button" class="btn bg-[#4f9f4a] text-white" :disabled="!draft.item_id || modelValue.length >= 100" @click="add">Add item</button>
        </MiriCogItemPicker>
        <p class="mt-2 text-xs text-slate-500">Item details and unit come from the selected register. If its unit is missing, enter the correct unit before adding it.</p>
        <p v-if="draft.item_id" class="mt-2 text-xs text-slate-500">Source location: {{ draft.location || 'Not recorded' }}<span v-if="draft.batch_no"> · Paint batch: {{ draft.batch_no }}</span></p>
        <div v-if="draft.item_type === 'Construction' && movementType === 'Transfer'" class="mt-4 rounded-xl border p-4">
            <p class="mb-3 text-sm font-semibold">Destination stock record</p><p class="mb-3 text-xs text-slate-500">Choose the matching item and unit at the destination store. The document's To location must match this record.</p>
            <MiriCogItemPicker v-model="draft.construction_destination_id" type="Construction" :movement-type="movementType" />
        </div>
        <p v-if="error" role="alert" class="mt-3 text-sm text-red-700">{{ error }}</p>
        <div class="mt-5 overflow-x-auto rounded-xl border border-[#d8e7d4]">
            <table class="w-full min-w-[1050px] border-collapse text-sm">
                <thead class="bg-[#f2f8ef] text-xs text-[#234222]"><tr><th class="p-3">ITEM NO.</th><th class="p-3">QTY</th><th class="p-3">UNIT</th><th class="p-3 text-left">FULL DESCRIPTION</th><th class="p-3 text-left">SIZE / MODEL</th><th class="p-3 text-left">TAGGING NO.</th><th class="p-3 text-left">SERIAL NO.</th><th class="p-3 text-left">MR #</th><th class="p-3 text-left">REMARKS</th><th class="p-3">ACTION</th></tr></thead>
                <tbody>
                    <tr v-for="(row, index) in modelValue" :key="row.row_key" class="border-t border-[#d8e7d4]">
                        <td class="p-2 text-center">{{ index + 1 }}</td><td class="p-2"><input v-model="row.quantity" :aria-label="`Quantity for item ${index + 1}`" type="number" min="0.001" max="999999999.999" step="0.001" class="w-24 rounded-lg border-slate-200 text-sm" /></td><td class="p-2 text-center">{{ row.unit }}</td>
                        <td class="p-2">{{ row.description || 'Not recorded' }}<p v-if="row.batch_no" class="text-xs text-slate-500">Batch: {{ row.batch_no }}</p><p v-if="row.construction_destination_id" class="text-xs text-slate-500">Destination record #{{ row.construction_destination_id }}</p></td>
                        <td class="p-2">{{ row.size_model || '-' }}</td><td class="p-2">{{ row.identifier || '-' }}</td><td class="p-2">{{ row.serial_no || '-' }}</td>
                        <td class="p-2"><input v-model="row.mr_reference" :aria-label="`MR number for item ${index + 1}`" maxlength="1000" class="w-28 rounded-lg border-slate-200 text-sm" /></td><td class="p-2"><input v-model="row.remarks" :aria-label="`Remarks for item ${index + 1}`" maxlength="1000" class="w-36 rounded-lg border-slate-200 text-sm" /></td>
                        <td class="p-2"><button type="button" class="text-sm font-semibold text-red-700" :aria-label="`Delete item ${index + 1}`" @click="remove(index)">Delete</button></td>
                    </tr>
                    <tr v-if="!modelValue.length"><td colspan="10" class="p-8 text-center text-slate-500">Find an item above and click Add item to include it in this note.</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
