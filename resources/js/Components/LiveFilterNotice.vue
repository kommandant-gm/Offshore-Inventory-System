<script setup>
defineProps({ loading: Boolean, error: String });
defineEmits(['retry']);
</script>
<template>
    <div class="text-xs text-slate-500" aria-live="polite">
        <p>Choices narrow automatically. Click Apply filters to update the record list.</p>
        <p v-if="loading" class="mt-1">Updating filter choices…</p>
        <p v-if="error" class="mt-1 text-red-700">{{ error }} <button type="button" class="underline" @click="$emit('retry')">Retry</button></p>
    </div>
</template>
