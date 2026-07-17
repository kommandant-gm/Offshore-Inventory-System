<script setup>
import { ChevronDownIcon, CheckIcon } from '@heroicons/vue/24/outline';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useAttrs, useSlots, watch } from 'vue';

defineOptions({ inheritAttrs: false });
const props = defineProps({ modelValue: { default: '' }, disabled: Boolean, placeholder: { type: String, default: 'Select an option' } });
const emit = defineEmits(['update:modelValue', 'change']);
const attrs = useAttrs();
const slots = useSlots();
const open = ref(false);
const activeIndex = ref(-1);
const root = ref(null);

const flatten = (nodes, result = []) => {
  for (const node of nodes ?? []) {
    if (node?.type === 'option') {
      const children = typeof node.children === 'string' ? node.children : Array.isArray(node.children) ? node.children.join('') : '';
      result.push({ value: node.props?.value ?? '', label: children, disabled: Boolean(node.props?.disabled) });
    } else if (Array.isArray(node?.children)) flatten(node.children, result);
  }
  return result;
};
const options = computed(() => flatten(slots.default?.() ?? []));
const selected = computed(() => options.value.find((option) => String(option.value) === String(props.modelValue)));
const passthroughAttrs = computed(() => Object.fromEntries(Object.entries(attrs).filter(([key]) => key !== 'class')));
const buttonClass = computed(() => String(attrs.class ?? '').split(/\s+/).filter((name) => !['select', 'select-bordered', 'select-sm', 'select-lg'].includes(name)).join(' '));
const choose = (option) => {
  if (option.disabled) return;
  emit('update:modelValue', option.value);
  emit('change', { target: { value: option.value } });
  open.value = false;
};
const toggle = async () => {
  if (props.disabled) return;
  open.value = !open.value;
  if (open.value) {
    activeIndex.value = Math.max(options.value.findIndex((option) => String(option.value) === String(props.modelValue)), 0);
    await nextTick();
  }
};
const keydown = (event) => {
  if (!open.value && ['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) { event.preventDefault(); toggle(); return; }
  if (!open.value) return;
  if (!options.value.length) return;
  if (event.key === 'Escape' || event.key === 'Tab') { open.value = false; return; }
  if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
    event.preventDefault(); const direction = event.key === 'ArrowDown' ? 1 : -1;
    activeIndex.value = (activeIndex.value + direction + options.value.length) % options.value.length;
  }
  if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); choose(options.value[activeIndex.value]); }
};
const closeOutside = (event) => { if (!root.value?.contains(event.target)) open.value = false; };
watch(() => props.disabled, (disabled) => { if (disabled) open.value = false; });
onMounted(() => document.addEventListener('click', closeOutside));
onBeforeUnmount(() => document.removeEventListener('click', closeOutside));
</script>

<template>
  <div ref="root" class="relative" @keydown="keydown">
    <button type="button" role="combobox" :aria-expanded="open" aria-haspopup="listbox" :disabled="disabled" v-bind="passthroughAttrs" :class="[buttonClass,'min-h-10 rounded-lg border border-[#cfe6c8] bg-white px-3 py-2 text-[#234222] flex items-center justify-between gap-3 text-left disabled:cursor-not-allowed disabled:opacity-60']" @click="toggle">
      <span class="min-w-0 flex-1 truncate">{{selected?.label || placeholder}}</span><ChevronDownIcon class="h-4 w-4 shrink-0 transition" :class="open?'rotate-180':''"/>
    </button>
    <div v-if="open" role="listbox" class="absolute z-50 mt-2 max-h-64 min-w-full overflow-auto rounded-xl border border-[#d8e7d4] bg-white p-1.5 shadow-[0_18px_45px_rgba(35,66,34,.18)]">
      <button v-for="(option,index) in options" :key="`${String(option.value)}-${index}`" type="button" role="option" :aria-selected="String(option.value)===String(modelValue)" :disabled="option.disabled" class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-left text-sm text-[#355733] hover:bg-[#eef8ea] disabled:opacity-40" :class="{'bg-[#eef8ea]':index===activeIndex}" @mouseenter="activeIndex=index" @click="choose(option)">
        <span>{{option.label}}</span><CheckIcon v-if="String(option.value)===String(modelValue)" class="h-4 w-4 text-[#3c8a39]"/>
      </button>
    </div>
  </div>
</template>
