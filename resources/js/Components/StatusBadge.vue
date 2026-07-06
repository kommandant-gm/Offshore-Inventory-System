<script setup>
import { computed } from 'vue';

const props = defineProps({
    value: {
        type: String,
        required: true,
    },
});

const classes = computed(() => {
    const value = props.value;

    if (['available', 'good', 'active'].includes(value)) {
        return 'border-emerald-500/25 bg-emerald-50 text-emerald-700';
    }

    if (['deployed', 'under_repair', 'fair'].includes(value)) {
        return 'border-amber-500/25 bg-amber-50 text-amber-700';
    }

    if (['damaged', 'disposed', 'critical', 'inactive'].includes(value)) {
        return 'border-red-500/25 bg-red-50 text-red-700';
    }

    return 'border-slate-200 bg-slate-50 text-slate-700';
});

const label = computed(() => props.value.replaceAll('_', ' '));
</script>

<template>
    <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold capitalize" :class="classes">
        {{ label }}
    </span>
</template>
