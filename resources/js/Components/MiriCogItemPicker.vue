<script setup>
import CustomSelect from '@/Components/CustomSelect.vue';
import CompanyField from '@/Components/CompanyField.vue';
import axios from 'axios';
import { computed, reactive, ref, watch, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({ type: String, movementType: String, modelValue: [String, Number], excludedIds: { type: Array, default: () => [] } });
const emit = defineEmits(['update:modelValue', 'selected']);
const search = ref('');
const company = ref('');
const inventoryType = ref('');
const major = computed(() => props.type === 'Major equipment');
const refinements = reactive({ description: '', section_2: '', location: '', tag_no: '', serial_no: '' });
const options = ref({ description: [], section_2: [], location: [] });
const results = ref([]);
const chosen = ref(null);
const loading = ref(false);
const error = ref('');
const hasMore = ref(false);
const nextAfterId = ref(null);
let timer;
let controller;
let sequence = 0;
async function load(append = false) {
    clearTimeout(timer);
    const request = ++sequence;
    controller?.abort();
    controller = new AbortController();
    loading.value = true;
    error.value = '';
    try {
        const { data } = await axios.get(route('miri-cogs.items'), { params: { type: props.type, company: company.value, search: search.value, ...refinements, ...(major.value ? { inventory_type: inventoryType.value } : {}), after_id: append ? nextAfterId.value : undefined }, signal: controller.signal });
        if (request !== sequence) return;
        results.value = append ? [...results.value, ...data.items.filter(item => !results.value.some(existing => existing.key === item.key))] : data.items;
        options.value = data.options;
        hasMore.value = data.has_more;
        nextAfterId.value = data.next_after_id;
    } catch (e) {
        if (request === sequence && !axios.isCancel(e)) error.value = append ? 'Unable to load more items. Please try again.' : 'Unable to load items. Try searching again.';
    } finally {
        if (request === sequence) loading.value = false;
    }
}
function resetResults() {
    ++sequence;
    controller?.abort();
    clearTimeout(timer);
    results.value = [];
    hasMore.value = false;
    nextAfterId.value = null;
    loading.value = false;
    error.value = '';
}
function clearSelection() {
    chosen.value = null;
    emit('update:modelValue', '');
    emit('selected', null);
}
function resetRefinements() {
    Object.keys(refinements).forEach(key => refinements[key] = '');
    options.value = { description: [], section_2: [], location: [] };
}
watch([search, company, inventoryType, () => refinements.description, () => refinements.section_2, () => refinements.location, () => refinements.tag_no, () => refinements.serial_no], () => {
    resetResults();
    clearSelection();
    loading.value = true;
    timer = setTimeout(() => load(), 300);
});
watch(() => [props.type, props.movementType], () => {
    resetResults();
    clearSelection();
    search.value = '';
    inventoryType.value = '';
    resetRefinements();
    load();
});
onMounted(() => load());
watch(() => props.modelValue, value => { if (!value) chosen.value = null; });
const blocked = item => props.excludedIds.includes(item.id) || (props.movementType !== 'Received backload' && item.allocated_to?.length > 0);
function select(event) {
    const item = results.value.find(item => String(item.id) === event.target.value) || (String(chosen.value?.id) === event.target.value ? chosen.value : null);
    if (item && blocked(item)) return;
    chosen.value = item;
    emit('update:modelValue', item?.id || '');
    emit('selected', item);
}
onBeforeUnmount(() => { ++sequence; clearTimeout(timer); controller?.abort(); });
</script>

<template>
    <div>
        <p class="mb-3 text-sm font-semibold text-[#2f7d32]">Search items by</p>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <CompanyField v-model="company" filter />
            <label v-if="major" class="block text-sm">Equipment type
                <CustomSelect v-model="inventoryType" class="mt-1 w-full rounded-xl border-slate-200" @change="resetRefinements">
                    <option value="">All equipment</option><option value="machinery">Machinery</option><option value="cargo">Cargo</option><option value="scaffolding">Scaffolding</option>
                </CustomSelect>
            </label>
            <label v-for="[key, label] in [['section_2', 'Subcategory'], ['location', 'Current location']]" :key="key" class="block text-sm">{{ label }}
                <CustomSelect v-model="refinements[key]" class="mt-1 w-full rounded-xl border-slate-200">
                    <option value="">All</option><option v-if="refinements[key] && !options[key].includes(refinements[key])" :value="refinements[key]" disabled>{{ refinements[key] }} (no matching records)</option><option v-for="value in options[key]" :key="value" :value="value">{{ value }}</option>
                </CustomSelect>
            </label>
            <label class="block text-sm">{{ type === 'Paint' ? 'Batch No.' : 'Tagging No.' }}<input v-model="refinements.tag_no" type="search" maxlength="255" class="mt-1 w-full rounded-xl border-slate-200" /></label>
            <label v-if="type !== 'Paint'" class="block text-sm">Serial No.<input v-model="refinements.serial_no" type="search" maxlength="255" class="mt-1 w-full rounded-xl border-slate-200" /></label>
            <label class="block text-sm">Description
                <CustomSelect v-model="refinements.description" class="mt-1 w-full rounded-xl border-slate-200">
                    <option value="">All</option><option v-if="refinements.description && !options.description.includes(refinements.description)" :value="refinements.description" disabled>{{ refinements.description }} (no matching records)</option><option v-for="value in options.description" :key="value" :value="value">{{ value }}</option>
                </CustomSelect>
            </label>
            <label class="block text-sm">Search<input v-model="search" type="search" maxlength="255" aria-label="Search inventory items" placeholder="Description, tag, location or record ID" class="mt-1 w-full rounded-xl border-slate-200" /></label>
        </div>
        <div class="mt-5 flex flex-wrap items-end gap-3 border-t border-dashed border-[#d8e7d4] pt-4">
            <label class="min-w-0 flex-[2_1_280px] text-sm">Select an item
                <CustomSelect :model-value="modelValue" class="mt-1 w-full rounded-xl border-slate-200" aria-label="Inventory item" @change="select">
                    <option value="">Select an item</option>
                    <option v-for="item in results" :key="item.key" :value="item.id" :disabled="blocked(item)">#{{ item.id }} · {{ item.identifier || item.batch_no || 'Unidentified' }} · {{ item.description || 'Unnamed' }} · {{ item.serial_no || 'No serial' }} · {{ item.location || 'No location' }}{{ excludedIds.includes(item.id) ? ' — Already added' : item.allocated_to?.length ? ' — Allocated to ' + item.allocated_to.join(', ') + ' — Outstanding: ' + item.outstanding_quantity : '' }}</option>
                </CustomSelect>
            </label>
            <slot />
        </div>
        <p class="mt-2 text-xs text-slate-500" role="status">{{ error || (loading ? 'Loading items...' : results.length ? `Showing ${results.length} matches${hasMore ? '. Load more to see the rest.' : '. All matches loaded.'}` : 'No matching items.') }}</p>
        <button v-if="error" type="button" class="btn btn-sm mt-2" @click="load()">Retry</button>
        <button v-if="hasMore" type="button" class="btn btn-sm mt-2" :disabled="loading" @click="load(true)">{{ loading ? 'Loading...' : 'Load more' }}</button>
    </div>
</template>
