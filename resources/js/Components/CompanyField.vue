<script setup>
import CustomSelect from '@/Components/CustomSelect.vue';
defineProps({ modelValue: { default: '' }, filter: Boolean, error: String });
defineEmits(['update:modelValue']);
</script>
<template>
    <label class="block text-sm font-semibold text-[#50694e]">Company
        <CustomSelect :model-value="modelValue ?? ''" class="mt-2 w-full rounded-xl border-[#d8e7d4] text-sm" @change="$emit('update:modelValue', $event.target.value)">
            <option value="">{{ filter ? 'All companies' : 'Not assigned' }}</option>
            <option v-if="filter" value="unassigned">Not assigned</option>
            <option value="DESB">DESB</option><option value="FTSB">FTSB</option>
        </CustomSelect>
        <span v-if="error" class="mt-1 block text-xs text-red-600">{{ error }}</span>
    </label>
</template>
