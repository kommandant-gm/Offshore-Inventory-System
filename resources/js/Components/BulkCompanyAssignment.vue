<script setup>
import { useForm } from '@inertiajs/vue3';
const props = defineProps({ ids: Array, register: String });
const emit = defineEmits(['assigned']);
const form = useForm({ register: '', ids: [], company: '' });
function assign() {
    form.register = props.register; form.ids = [...props.ids];
    form.patch(route('miri-company.assign'), { preserveScroll: true, onSuccess: () => { emit('assigned'); form.reset(); } });
}
</script>
<template>
    <div class="rounded-2xl border border-[#d8e7d4] bg-white p-4">
        <p class="text-sm font-semibold text-[#234222]">Bulk company assignment: {{ ids.length }} selected</p>
        <p class="mt-1 text-xs text-slate-500">Select items using the table checkboxes. Selection applies to this page and resets when you change pages or filters.</p>
        <div class="mt-3 flex flex-wrap items-center gap-3">
            <label class="text-sm">Assign to <select v-model="form.company" class="ml-2 rounded-xl border-slate-200"><option value="" disabled>Select company</option><option>DESB</option><option>FTSB</option><option value="unassigned">Not assigned</option></select></label>
            <button type="button" class="btn btn-sm bg-[#4f9f4a] text-white" :disabled="!ids.length || !form.company || form.processing" @click="assign">{{ form.processing ? 'Assigning...' : 'Assign selected items' }}</button>
        </div>
        <p v-for="(error, key) in form.errors" :key="key" class="mt-2 text-sm text-red-600">{{ error }}</p>
    </div>
</template>
