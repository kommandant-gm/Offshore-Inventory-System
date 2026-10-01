<script setup>
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
  item: { type: Object, required: true },
  register: { type: String, required: true, validator: (value) => ['it-assets', 'it-licenses'].includes(value) },
});
const emit = defineEmits(['deleted']);
const form = useForm({ confirmed: true });

function remove() {
  if (form.processing) return;
  form.clearErrors();
  const label = props.item.asset_tag_no || props.item.license_code;
  const detail = props.register === 'it-assets'
    ? 'This permanently removes the asset and its assignment, movement and repair history, including linked movement documents.'
    : 'This permanently removes the licence record.';
  if (!window.confirm(`Delete ${label}?\n\n${detail} This cannot be undone.`)) return;
  form.delete(route(`${props.register}.destroy`, props.item.id), {
    preserveScroll: true,
    onSuccess: () => emit('deleted', props.item.id),
  });
}
</script>

<template>
  <div>
    <button type="button" class="btn btn-xs border-red-200 bg-white text-red-700 hover:border-red-300 hover:bg-red-50" :disabled="form.processing" :aria-label="`Delete ${item.asset_tag_no || item.license_code}`" @click="remove">{{ form.processing ? 'Deleting...' : 'Delete' }}</button>
    <p v-for="(error, key) in form.errors" :key="key" role="alert" class="mt-2 max-w-xs whitespace-normal text-xs text-red-700">{{ error }}</p>
  </div>
</template>
