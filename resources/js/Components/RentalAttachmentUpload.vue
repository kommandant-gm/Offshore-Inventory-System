<script setup>
import InputError from '@/Components/InputError.vue';
defineProps({ modelValue: { default: null }, slotKey: String, label: String, rental: Object, disabled: Boolean, error: String });
defineEmits(['update:modelValue']);
</script>
<template>
    <div class="min-w-0 rounded-xl border border-[#d8e7d4] bg-[#f5f9f3] p-4">
        <label :for="`upload-${slotKey}`" class="block text-sm font-semibold">Upload {{ label }} (PDF)</label>
        <div v-if="rental?.attachments?.[slotKey]" class="mt-2 flex flex-wrap gap-3 text-sm">
            <a :href="route('miri-rental.attachment', { rental: rental.id, slot: slotKey })" target="_blank" rel="noopener" class="break-all text-green-800 underline">Open {{ rental.attachments[slotKey].name }}</a>
            <a :href="route('miri-rental.attachment', { rental: rental.id, slot: slotKey, download: 1 })" class="text-green-800 underline">Download</a>
        </div>
        <input :id="`upload-${slotKey}`" type="file" accept="application/pdf,.pdf" class="mt-3 block w-full text-sm" :disabled="disabled" @change="$emit('update:modelValue', $event.target.files[0] || null)" />
        <p class="mt-2 text-xs text-slate-500">One PDF, up to 5 MB. Selecting a new file replaces the saved attachment when you save.</p>
        <InputError :message="error" />
    </div>
</template>
