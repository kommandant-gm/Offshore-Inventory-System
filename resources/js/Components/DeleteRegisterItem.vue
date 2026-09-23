<script setup>
import { useForm } from '@inertiajs/vue3';
const props = defineProps({ register: { type: String, required: true }, item: { type: Object, required: true } });
const emit = defineEmits(['deleted']);
const form = useForm({ confirmed: true });
function remove() {
    if (form.processing) return;
    form.clearErrors();
    const label = [props.item.description, props.item.tag_no || props.item.serial_tag_equipment_no || props.item.batch_no].filter(Boolean).join(' · ');
    if (!window.confirm(`Delete ${label || 'this item'} (record #${props.item.id})?\n\nThis permanently removes the record and its attachments. This cannot be undone.\n\nChoose OK to delete or Cancel to keep it.`)) return;
    form.delete(route('miri-register.destroy', { register: props.register, item: props.item.id }), {
        preserveScroll: true, onSuccess: () => emit('deleted', props.item.id),
    });
}
</script>
<template>
    <div>
        <button type="button" class="btn btn-xs border-red-200 bg-white text-red-700 hover:border-red-300 hover:bg-red-50" :disabled="form.processing" :aria-label="'Delete record #' + item.id" @click="remove">{{ form.processing ? 'Deleting…' : 'Delete' }}</button>
        <p v-for="(error, key) in form.errors" :key="key" role="alert" class="mt-2 max-w-xs whitespace-normal text-xs text-red-700">{{ error }}</p>
    </div>
</template>
