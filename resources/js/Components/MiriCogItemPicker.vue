<script setup>
import axios from 'axios';
import { ref, watch, onBeforeUnmount } from 'vue';

const props = defineProps({ type: String, movementType: String, modelValue: [String, Number] });
const emit = defineEmits(['update:modelValue', 'selected']);
const search = ref('');
const results = ref([]);
const chosen = ref(null);
const loading = ref(false);
const error = ref('');
const hasMore = ref(false);
let timer;
let controller;
let sequence = 0;
async function load() {
    const request = ++sequence;
    controller?.abort();
    controller = new AbortController();
    loading.value = true;
    error.value = '';
    try {
        const { data } = await axios.get(route('miri-cogs.items'), { params: { type: props.type, search: search.value }, signal: controller.signal });
        if (request !== sequence) return;
        results.value = data.items;
        hasMore.value = data.has_more;
    } catch (e) {
        if (request === sequence && !axios.isCancel(e)) error.value = 'Unable to load items. Try searching again.';
    } finally {
        if (request === sequence) loading.value = false;
    }
}
watch(search, () => {
    ++sequence;
    controller?.abort();
    clearTimeout(timer);
    timer = setTimeout(load, 300);
});
watch(() => [props.type, props.movementType], () => {
    clearTimeout(timer);
    chosen.value = null;
    results.value = [];
    emit('update:modelValue', '');
    emit('selected', null);
    load();
});
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
        <input v-model="search" type="search" aria-label="Search inventory items" placeholder="Search tag / batch, description, location or record ID" class="input input-bordered mt-2 w-full" @focus="!results.length && !loading && load()" />
        <select :value="modelValue" class="input input-bordered mt-2 w-full" aria-label="Inventory item" @change="select" @focus="!results.length && !loading && load()">
            <option value="">Select an item</option>
            <option v-if="chosen && !results.some(item => item.id === chosen.id)" :value="chosen.id">#{{ chosen.id }} · {{ chosen.identifier || chosen.batch_no || 'Unidentified' }} · {{ chosen.description }}</option>
            <option v-for="item in results" :key="item.key" :value="item.id" :disabled="blocked(item)">#{{ item.id }} · {{ item.identifier || item.batch_no || 'Unidentified' }} · {{ item.description || 'Unnamed' }} · {{ item.location || 'No location' }}{{ item.allocated_to?.length ? ' - Allocated to ' + item.allocated_to.join(', ') + ' - Outstanding: ' + item.outstanding_quantity : '' }}</option>
        </select>
        <p class="mt-1 text-xs text-slate-500" role="status">{{ error || (loading ? 'Searching…' : hasMore ? 'Showing first 25 matches. Refine your search for more.' : search && !results.length ? 'No matching items.' : 'Search to find an inventory item.') }}</p>
    </div>
</template>
