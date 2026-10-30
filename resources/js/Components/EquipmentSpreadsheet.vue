<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { cellValue, pasteCells, sheetChanges, sheetRows, sheetLayout, sheetOptions } from '@/Support/equipmentSheet';

const props = defineProps({ records: Array, columns: Array, canEdit: Boolean, inventoryType: String, firstRow: Number,
    updateUrl: String, showRoute: { type: String, default: 'major-equipment.show' },
    label: { type: String, default: 'Equipment' },
    help: { type: String, default: 'Showing this page of records. Open a record to manage its certificates.' },
});
const rows = ref([]), originals = ref([]), errors = ref({}), notice = ref(''), saving = ref(false), table = ref(null);
const editing = ref(false);
const hiddenColumns = ref([]), freezeThrough = ref('__record');
const preferenceKey = computed(() => `register-sheet-layout:${props.showRoute}:${props.label}:${props.inventoryType || ''}`);
const allColumns = computed(() => sheetLayout(props.columns));
const layout = computed(() => sheetLayout(props.columns, hiddenColumns.value, freezeThrough.value));
const visibleColumns = computed(() => layout.value.filter(column => column.key !== '__record'));
const recordColumn = computed(() => layout.value.find(column => column.key === '__record'));
const sheetWidth = computed(() => layout.value.reduce((width, column) => width + column.width, 0));
const columnStyle = column => column.frozen ? { position: 'sticky', left: `${column.left}px` } : {};
watch(preferenceKey, key => {
    hiddenColumns.value = []; freezeThrough.value = '__record';
    try {
        const saved = JSON.parse(window.localStorage.getItem(key));
        if (Array.isArray(saved?.hidden)) hiddenColumns.value = saved.hidden.filter(key => allColumns.value.some(column => column.key === key));
        if (hiddenColumns.value.length === allColumns.value.length) hiddenColumns.value = [];
        if (saved?.freeze === '' || allColumns.value.some(column => column.key === saved?.freeze)) freezeThrough.value = saved.freeze;
    } catch { /* Layout controls also work when browser storage is unavailable. */ }
}, { immediate: true });
function rememberLayout() {
    try { window.localStorage.setItem(preferenceKey.value, JSON.stringify({ hidden: hiddenColumns.value, freeze: freezeThrough.value })); } catch { /* Keep the layout for this view. */ }
}
function toggleColumn(key) {
    hiddenColumns.value = hiddenColumns.value.includes(key) ? hiddenColumns.value.filter(value => value !== key) : [...hiddenColumns.value, key];
    rememberLayout();
}
function showAllColumns() { hiddenColumns.value = []; rememberLayout(); }
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
    if (!editable.value || column.readonly) return;
    rows.value[index][column.key] = cellValue(value, column.type);
    for (const dependent of props.columns.filter(field => field.dependsOn === column.key)) {
        if (!sheetOptions(dependent, rows.value[index]).includes(rows.value[index][dependent.key])) rows.value[index][dependent.key] = '';
    }
    delete errors.value[`${rows.value[index].id}.${column.key}`];
    notice.value = '';
}
function paste(event, row, column) {
    if (!editable.value) { event.preventDefault(); return; }
    const text = event.clipboardData.getData('text/plain');
    if (!/[\t\r\n]/.test(text)) return;
    event.preventDefault();
    try { rows.value = pasteCells(rows.value, visibleColumns.value, row, column, text); errors.value = {}; notice.value = ''; }
    catch (error) { notice.value = error.message; }
}
function move(event, row, column) {
    if (event.target.tagName === 'SELECT' && event.key !== 'Enter') return;
    let targetRow = row, targetColumn = column;
    if (event.key === 'Enter') targetRow += event.shiftKey ? -1 : 1;
    else if (event.key === 'ArrowDown') targetRow++;
    else if (event.key === 'ArrowUp') targetRow--;
    else if (event.key === 'ArrowLeft' && event.target.selectionStart === 0 && event.target.selectionEnd === 0) targetColumn--;
    else if (event.key === 'ArrowRight' && event.target.selectionStart === event.target.value.length) targetColumn++;
    else return;
    event.preventDefault();
    const target = table.value?.querySelector(`[data-cell="${targetRow}-${targetColumn}"]`);
    target?.focus(); target?.select?.();
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
        const response = await axios.patch(props.updateUrl || route('major-equipment.spreadsheet.update'), { inventory_type: props.inventoryType, rows: payload });
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
    <section :aria-label="`${label} Excel view`">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#d8e7d4] bg-[#f5f9f3] px-5 py-4">
            <div>
                <p class="font-semibold text-[#234222]">Excel view <span v-if="dirty" class="ml-2 text-sm text-amber-800">{{ changes.length }} changed records</span></p>
                <p class="mt-1 text-xs text-slate-600">{{ canEdit && editing ? 'Edit cells, use Tab or Enter to move, or paste cells from Excel. Save before changing pages or filters.' : canEdit ? 'Read-only spreadsheet view. Click Edit to make changes.' : 'Read-only spreadsheet view.' }} Dates: YYYY-MM-DD. Blank cells mean no recorded value.</p>
                <p class="mt-1 text-xs text-slate-500">{{ help }}</p>
            </div>
            <div v-if="canEdit" class="flex gap-2">
                <button v-if="!editing" type="button" class="btn btn-sm bg-[#4f9f4a] text-white" :disabled="saving" @click="editing = true; notice = ''">Edit</button>
                <template v-else>
                    <button type="button" class="btn btn-sm" :disabled="saving" @click="discard">{{ dirty ? 'Discard changes' : 'Cancel' }}</button>
                    <button type="button" class="btn btn-sm bg-[#4f9f4a] text-white" :disabled="!dirty || saving" @click="save">{{ saving ? 'Saving…' : 'Save changes' }}</button>
                </template>
            </div>
        </div>
        <div class="flex flex-wrap items-start gap-4 border-b border-[#d8e7d4] bg-[#f5f9f3] px-5 py-3 text-sm">
            <label class="flex items-center gap-2 font-medium text-[#234222]">
                Freeze through
                <select v-model="freezeThrough" class="max-w-64 rounded border-[#cfe0cb] text-sm" @change="rememberLayout">
                    <option value="">None</option>
                    <option v-for="column in allColumns" :key="column.key" :value="column.key">{{ column.letter ? `${column.letter} · ` : '' }}{{ column.label }}</option>
                </select>
            </label>
            <details class="rounded border border-[#cfe0cb] bg-white px-3 py-2">
                <summary class="cursor-pointer font-medium text-[#234222]">Hide / unhide columns ({{ hiddenColumns.length }} hidden)</summary>
                <button type="button" class="my-2 text-green-800 underline" @click="showAllColumns">Show all columns</button>
                <div class="grid max-h-64 gap-2 overflow-auto pr-3">
                    <label v-for="column in allColumns" :key="column.key" class="flex items-center gap-2">
                        <input type="checkbox" :checked="!hiddenColumns.includes(column.key)" :disabled="layout.length === 1 && !hiddenColumns.includes(column.key)" class="rounded border-[#cfe0cb] text-green-700" @change="toggleColumn(column.key)" />
                        {{ column.letter ? `${column.letter} · ` : '' }}{{ column.label }}
                    </label>
                </div>
                <p class="mt-2 text-xs text-slate-500">Checked columns are visible. Keep at least one column visible.</p>
            </details>
            <p class="basis-full text-xs text-slate-500">Freeze keeps the selected column and preceding visible columns in place when scrolling sideways. Settings are remembered in this browser.</p>
        </div>
        <div v-if="notice || Object.keys(errors).length" class="border-b px-5 py-3 text-sm" role="status" aria-live="polite">
            <p>{{ notice }}</p>
            <ul v-if="Object.keys(errors).length" class="mt-2 list-disc pl-5 text-red-700"><li v-for="(error, key) in errors" :key="key">{{ /^\d+\./.test(key) ? `Record #${key.split('.')[0]}: ` : '' }}{{ error }}</li></ul>
        </div>
        <div ref="table" class="max-h-[70vh] overflow-auto">
            <table class="sheet-table table-fixed border-separate border-spacing-0 text-sm" :style="{ width: `${sheetWidth}px` }" :aria-label="`${editable ? 'Editable' : 'Read-only'} ${label} spreadsheet`">
                <colgroup><col v-for="column in layout" :key="column.key" :style="{ width: `${column.width}px` }" /></colgroup>
                <thead class="sticky top-0 z-20">
                    <tr>
                        <th v-if="recordColumn" :style="columnStyle(recordColumn)" :class="{ 'z-30': recordColumn.frozen }" class="border-b border-r border-[#cfe0cb] bg-[#eaf2e7] px-3 py-2">Record</th>
                        <th v-for="column in visibleColumns" :key="column.key" :style="columnStyle(column)" :class="{ 'z-30': column.frozen }" class="break-words border-b border-r border-[#cfe0cb] bg-[#eaf2e7] px-3 py-2 text-left">
                            <span class="block text-[10px] font-normal text-slate-500">{{ column.letter }}</span>{{ column.label }}<span v-if="column.readonly" class="block text-xs font-normal text-slate-500">Read-only</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, rowIndex) in rows" :key="row.id">
                        <th v-if="recordColumn" :style="columnStyle(recordColumn)" :class="{ 'z-10': recordColumn.frozen }" class="break-words border-b border-r border-[#d8e7d4] bg-[#f5f9f3] px-3 text-left text-xs font-normal">
                            <span class="mr-2 text-slate-400">{{ (firstRow || 1) + rowIndex }}</span><a :href="route(showRoute, row.id)" target="_blank" rel="noopener" class="font-semibold text-green-800 underline" :aria-label="`Open record ${row.id} in a new tab`">#{{ row.id }}</a>
                        </th>
                        <td v-for="(column, columnIndex) in visibleColumns" :key="column.key" :style="columnStyle(column)" class="border-b border-r border-[#d8e7d4] p-0" :class="[column.frozen ? 'z-10' : '', errors[`${row.id}.${column.key}`] ? 'bg-red-50' : changed(rowIndex, column.key) ? 'bg-amber-50' : 'bg-white']">
                            <select v-if="editable && !column.readonly && column.options" :value="row[column.key]" :data-cell="`${rowIndex}-${columnIndex}`" :aria-label="`Record ${row.id}, ${column.label}`" :aria-invalid="!!errors[`${row.id}.${column.key}`]" class="block h-10 w-full border-0 bg-transparent pl-3 pr-7 text-sm focus:ring-inset focus:ring-green-600" @change="edit(rowIndex, column, $event.target.value)" @keydown="move($event, rowIndex, columnIndex)" @paste="paste($event, rowIndex, columnIndex)">
                                <option value="">Select</option>
                                <option v-for="value in sheetOptions(column, row, originals[rowIndex])" :key="value" :value="value">{{ value }}</option>
                                <option v-if="row[column.key] && !sheetOptions(column, row, originals[rowIndex]).includes(row[column.key])" :value="row[column.key]" disabled>{{ row[column.key] }} (choose an option)</option>
                            </select>
                            <input v-else :value="row[column.key]" type="text" :data-cell="`${rowIndex}-${columnIndex}`" :readonly="!editable || column.readonly" :aria-label="`Record ${row.id}, ${column.label}`" :aria-invalid="!!errors[`${row.id}.${column.key}`]" :title="errors[`${row.id}.${column.key}`] || row[column.key]" :list="editable && column.key === 'company' ? 'equipment-sheet-companies' : undefined" class="block h-10 w-full min-w-48 border-0 bg-transparent px-3 text-sm focus:relative focus:z-10 focus:ring-2 focus:ring-inset focus:ring-green-600" @input="edit(rowIndex, column, $event.target.value)" @keydown="move($event, rowIndex, columnIndex)" @paste="paste($event, rowIndex, columnIndex)" />
                        </td>
                    </tr>
                    <tr v-if="!rows.length"><td :colspan="layout.length" class="p-8 text-center text-slate-500">No records match these filters.</td></tr>
                </tbody>
            </table>
        </div>
        <datalist id="equipment-sheet-companies"><option value="DESB" /><option value="FTSB" /></datalist>
    </section>
</template>
