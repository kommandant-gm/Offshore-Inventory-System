<script setup>
import CompanyField from '@/Components/CompanyField.vue';
import axios from 'axios';
import { computed, reactive, ref, watch, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({ type: String, movementType: String, modelValue: [String, Number] });
const emit = defineEmits(['update:modelValue', 'selected']);
const search = ref('');
const company = ref('');
const inventoryType = ref('');
const major = computed(() => props.type === 'Major equipment');
const refinements = reactive({ description: '', section_2: '', location: '' });
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
        const { data } = await axios.get(route('miri-cogs.items'), { params: { type: props.type, company: company.value, search: search.value, ...(major.value ? { inventory_type: inventoryType.value, ...refinements } : {}), after_id: append ? nextAfterId.value : undefined }, signal: controller.signal });
        if (request !== sequence) return;
        results.value = append ? [...results.value, ...data.items.filter(item => !results.value.some(existing => existing.key === item.key))] : data.items;
        if (major.value) options.value = data.options;
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
watch([search, company, inventoryType, () => refinements.description, () => refinements.section_2, () => refinements.location], (values, previous) => {
    resetResults();
    if (values.slice(1).some((value, index) => value !== previous[index + 1])) clearSelection();
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
const blocked = item => props.movementType !== 'Received backload' && item.allocated_to?.length > 0;
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
        <CompanyField v-model="company" filter />
        <div v-if="major" class="mt-2 space-y-3">
            <label class="block text-sm">Equipment type
                <select v-model="inventoryType" class="mt-1 w-full rounded-xl border-slate-200" @change="resetRefinements">
                    <option value="">All Machinery and Cargo</option>
                    <option value="machinery">Machinery</option>
                    <option value="cargo">Cargo</option>
                </select>
            </label>
            <div class="grid gap-3 sm:grid-cols-2">
                <label v-for="[key, label] in [['description', 'Description'], ['section_2', 'Subcategory'], ['location', 'Current location']]" :key="key" class="block text-sm">{{ label }}
                    <select v-model="refinements[key]" class="mt-1 w-full rounded-xl border-slate-200">
                        <option value="">All</option>
                        <option v-if="refinements[key] && !options[key].includes(refinements[key])" :value="refinements[key]" disabled>{{ refinements[key] }} (no matching records)</option>
                        <option v-for="value in options[key]" :key="value" :value="value">{{ value }}</option>
                    </select>
                </label>
            </div>
            <p class="text-xs text-slate-500">Choose Machinery or Cargo, then narrow by description, subcategory or location. Results update automatically.</p>
        </div>
        <input v-model="search" type="search" aria-label="Search inventory items" placeholder="Search tag / batch, description, location or record ID" class="input input-bordered mt-2 w-full" @focus="!results.length && !loading && load()" />
        <select :value="modelValue" class="input input-bordered mt-2 w-full" aria-label="Inventory item" @change="select" @focus="!results.length && !loading && load()">
            <option value="">Select an item</option>
            <option v-if="chosen && !results.some(item => item.id === chosen.id)" :value="chosen.id">#{{ chosen.id }} · {{ chosen.identifier || chosen.batch_no || 'Unidentified' }} · {{ chosen.description }}</option>
            <option v-for="item in results" :key="item.key" :value="item.id" :disabled="blocked(item)">#{{ item.id }} · {{ item.identifier || item.batch_no || 'Unidentified' }} · {{ item.description || 'Unnamed' }} · {{ item.location || 'No location' }}{{ item.allocated_to?.length ? ' - Allocated to ' + item.allocated_to.join(', ') + ' - Outstanding: ' + item.outstanding_quantity : '' }}</option>
        </select>
        <p class="mt-1 text-xs text-slate-500" role="status">{{ error || (loading ? 'Loading items...' : results.length ? `Showing ${results.length} matches${hasMore ? '. Load more to see the rest.' : '. All matches loaded.'}` : 'No matching items.') }}</p>
        <button v-if="hasMore" type="button" class="btn btn-sm mt-2" :disabled="loading" @click="load(true)">{{ loading ? 'Loading...' : 'Load more' }}</button>
    </div>
</template>
