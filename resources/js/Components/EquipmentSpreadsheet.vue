<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { cellValue, columnLetter, pasteCells, sheetChanges, sheetRows } from '@/Support/equipmentSheet';

const props = defineProps({ records: Array, columns: Array, canEdit: Boolean, inventoryType: String, firstRow: Number });
const rows = ref([]), originals = ref([]), errors = ref({}), notice = ref(''), saving = ref(false), table = ref(null);
const editing = ref(false);
const editable = computed(() => props.canEdit && editing.value && !saving.value);
let internalReload = false;
const changes = computed(() => sheetChanges(rows.value, originals.value, props.columns));
const dirty = computed(() => changes.value.length > 0);
function reset() {
    editing.value = false;
    originals.value = sheetRows(props.records, props.columns);
    rows.value = originals.value.map(row => ({ ...row }));
    errors.value = {};
}
watch(() => [props.records, props.columns], reset, { immediate: true });
const changed = (index, key) => rows.value[index][key] !== originals.value[index][key];
function edit(index, column, value) {
    if (!editable.value) return;
    rows.value[index][column.key] = cellValue(value, column.type);
    delete errors.value[`${rows.value[index].id}.${column.key}`];
    notice.value = '';
}
function paste(event, row, column) {
    if (!editable.value) { event.preventDefault(); return; }
    const text = event.clipboardData.getData('text/plain');
    if (!/[\t\r\n]/.test(text)) return;
    event.preventDefault();
    try { rows.value = pasteCells(rows.value, props.columns, row, column, text); errors.value = {}; notice.value = ''; }
    catch (error) { notice.value = error.message; }
}
function move(event, row, column) {
    let targetRow = row, targetColumn = column;
    if (event.key === 'Enter') targetRow += event.shiftKey ? -1 : 1;
    else if (event.key === 'ArrowDown') targetRow++;
    else if (event.key === 'ArrowUp') targetRow--;
    else if (event.key === 'ArrowLeft' && event.target.selectionStart === 0 && event.target.selectionEnd === 0) targetColumn--;
    else if (event.key === 'ArrowRight' && event.target.selectionStart === event.target.value.length) targetColumn++;
    else return;
    event.preventDefault();
    const target = table.value?.querySelector(`[data-cell="${targetRow}-${targetColumn}"]`);
    target?.focus(); target?.select();
}
function discard() {
    if (saving.value || (dirty.value && !window.confirm('Discard your unsaved spreadsheet changes?'))) return false;
    reset(); notice.value = '';
    return true;
}
defineExpose({ requestClose: discard });
async function save() {
    if (!editable.value || !dirty.value) return;
    const payload = changes.value;
    saving.value = true; errors.value = {}; notice.value = '';
    try {
        const response = await axios.patch(route('major-equipment.spreadsheet.update'), { inventory_type: props.inventoryType, rows: payload });
        originals.value = rows.value.map(row => ({ ...row }));
        editing.value = false;
        notice.value = response.data.message;
        internalReload = true;
        router.reload({ onFinish: () => { saving.value = false; internalReload = false; } });
    } catch (error) {
        for (const [key, messages] of Object.entries(error.response?.data?.errors ?? {})) {
            const match = key.match(/^rows\.(\d+)\.changes\.(\w+)$/);
            errors.value[match ? `${payload[Number(match[1])].id}.${match[2]}` : key] = Array.isArray(messages) ? messages[0] : messages;
        }
        notice.value = error.response?.status === 422 ? 'Nothing was saved. Correct the highlighted cells and save again.'
            : error.response?.status === 403 ? 'You no longer have permission to edit these records.'
            : error.response?.status === 404 ? 'A record is no longer available. Discard changes and reload the register.'
            : 'Unable to save. Your changes are still here; please try again.';
        saving.value = false;
    }
}
const removeNavigationGuard = router.on('before', event => {
    if (internalReload) return;
    if (saving.value || (dirty.value && !window.confirm('Leave this page and discard your unsaved spreadsheet changes?'))) event.preventDefault();
});
const beforeUnload = event => { if (dirty.value || saving.value) { event.preventDefault(); event.returnValue = ''; } };
onMounted(() => window.addEventListener('beforeunload', beforeUnload));
onBeforeUnmount(() => { removeNavigationGuard(); window.removeEventListener('beforeunload', beforeUnload); });
</script>

<template>
    <section aria-label="Equipment Excel view">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#d8e7d4] bg-[#f5f9f3] px-5 py-4">
            <div>
                <p class="font-semibold text-[#234222]">Excel view <span v-if="dirty" class="ml-2 text-sm text-amber-800">{{ changes.length }} changed records</span></p>
                <p class="mt-1 text-xs text-slate-600">{{ canEdit && editing ? 'Edit cells, use Tab or Enter to move, or paste cells from Excel. Save before changing pages or filters.' : canEdit ? 'Read-only spreadsheet view. Click Edit to make changes.' : 'Read-only spreadsheet view.' }} Dates: YYYY-MM-DD. Blank cells mean no recorded value.</p>
                <p class="mt-1 text-xs text-slate-500">Showing this page of records. Open a record to manage its certificates.</p>
            </div>
            <div v-if="canEdit" class="flex gap-2">
                <button v-if="!editing" type="button" class="btn btn-sm bg-[#4f9f4a] text-white" :disabled="saving" @click="editing = true; notice = ''">Edit</button>
                <template v-else>
                    <button type="button" class="btn btn-sm" :disabled="saving" @click="discard">{{ dirty ? 'Discard changes' : 'Cancel' }}</button>
                    <button type="button" class="btn btn-sm bg-[#4f9f4a] text-white" :disabled="!dirty || saving" @click="save">{{ saving ? 'Saving…' : 'Save changes' }}</button>
                </template>
            </div>
        </div>
        <div v-if="notice || Object.keys(errors).length" class="border-b px-5 py-3 text-sm" role="status" aria-live="polite">
            <p>{{ notice }}</p>
            <ul v-if="Object.keys(errors).length" class="mt-2 list-disc pl-5 text-red-700"><li v-for="(error, key) in errors" :key="key">{{ /^\d+\./.test(key) ? `Record #${key.split('.')[0]}: ` : '' }}{{ error }}</li></ul>
        </div>
        <div ref="table" class="max-h-[70vh] overflow-auto">
            <table class="sheet-table w-max min-w-full border-separate border-spacing-0 text-sm" :aria-label="editable ? 'Editable equipment spreadsheet' : 'Read-only equipment spreadsheet'">
                <thead class="sticky top-0 z-20">
                    <tr>
                        <th class="sticky left-0 z-30 min-w-28 border-b border-r border-[#cfe0cb] bg-[#eaf2e7] px-3 py-2">Record</th>
                        <th v-for="(column, index) in columns" :key="column.key" class="min-w-48 border-b border-r border-[#cfe0cb] bg-[#eaf2e7] px-3 py-2 text-left">
                            <span class="block text-[10px] font-normal text-slate-500">{{ columnLetter(index) }}</span>{{ column.label }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, rowIndex) in rows" :key="row.id">
                        <th class="sticky left-0 z-10 border-b border-r border-[#d8e7d4] bg-[#f5f9f3] px-3 text-left text-xs font-normal">
                            <span class="mr-2 text-slate-400">{{ (firstRow || 1) + rowIndex }}</span><a :href="route('major-equipment.show', row.id)" target="_blank" rel="noopener" class="font-semibold text-green-800 underline" :aria-label="`Open record ${row.id} in a new tab`">#{{ row.id }}</a>
                        </th>
                        <td v-for="(column, columnIndex) in columns" :key="column.key" class="border-b border-r border-[#d8e7d4] p-0" :class="errors[`${row.id}.${column.key}`] ? 'bg-red-50' : changed(rowIndex, column.key) ? 'bg-amber-50' : 'bg-white'">
                            <input :value="row[column.key]" type="text" :data-cell="`${rowIndex}-${columnIndex}`" :readonly="!editable" :aria-label="`Record ${row.id}, ${column.label}`" :aria-invalid="!!errors[`${row.id}.${column.key}`]" :title="errors[`${row.id}.${column.key}`] || row[column.key]" :list="editable && column.key === 'company' ? 'equipment-sheet-companies' : undefined" class="block h-10 w-full min-w-48 border-0 bg-transparent px-3 text-sm focus:relative focus:z-10 focus:ring-2 focus:ring-inset focus:ring-green-600" @input="edit(rowIndex, column, $event.target.value)" @keydown="move($event, rowIndex, columnIndex)" @paste="paste($event, rowIndex, columnIndex)" />
                        </td>
                    </tr>
                    <tr v-if="!rows.length"><td :colspan="columns.length + 1" class="p-8 text-center text-slate-500">No records match these filters.</td></tr>
                </tbody>
            </table>
        </div>
        <datalist id="equipment-sheet-companies"><option value="DESB" /><option value="FTSB" /></datalist>
    </section>
</template>
