<script setup>
import { ChevronDownIcon, CheckIcon } from '@heroicons/vue/24/outline';
import { nextTick, normalizeClass, onBeforeUnmount, onUpdated, ref, useAttrs, useId, useSlots, watch } from 'vue';

defineOptions({ inheritAttrs: false });
const props = defineProps({ modelValue: { default: '' }, modelModifiers: { default: () => ({}) }, disabled: Boolean, placeholder: { type: String, default: 'Select an option' } });
const emit = defineEmits(['update:modelValue', 'change']);
const attrs = useAttrs();
const slots = useSlots();
const listId = `select-${useId()}`;
const root = ref(null);
const trigger = ref(null);
const popup = ref(null);
const open = ref(false);
const activeIndex = ref(-1);
const position = ref({});
let renderedOptions = [];
let typeahead = '';
let typeTimer;
const enabled = value => value === '' || value === true || (value != null && value !== false);
const textContent = nodes => typeof nodes === 'string' || typeof nodes === 'number' ? String(nodes) : Array.isArray(nodes) ? nodes.map(node => textContent(node?.children ?? '')).join('') : '';
function flatten(nodes, inheritedDisabled = false, result = []) {
    for (const node of nodes ?? []) {
        if (node?.type === 'option') {
            const label = textContent(node.children).replace(/\s+/g, ' ').trim();
            result.push({ value: Object.hasOwn(node.props ?? {}, 'value') ? node.props.value : label, label, disabled: inheritedDisabled || enabled(node.props?.disabled) });
        } else if (Array.isArray(node?.children)) flatten(node.children, inheritedDisabled || (node.type === 'optgroup' && enabled(node.props?.disabled)), result);
    }
    return result;
}
// Read the slot during every render: parent option lists can change independently of the selected value.
function selectionLabel() {
    renderedOptions = flatten(slots.default?.() ?? []);
    return renderedOptions.find(option => same(option.value, props.modelValue))?.label || props.placeholder;
}
const same = (a, b) => String(a ?? '') === String(b ?? '');
const optionId = index => `${listId}-${index}`;
const classes = () => normalizeClass(attrs.class).split(/\s+/).filter(Boolean);
const layoutClass = name => /^(?:[\w-]+:)*(?:m[trblxy]?|w|min-w|max-w|col-span|row-span|self|order)-/.test(name);
const wrapperClasses = () => classes().filter(layoutClass);
const triggerClasses = () => classes().filter(name => !layoutClass(name) && !['select', 'select-bordered', 'select-sm', 'select-lg', 'input', 'input-bordered'].includes(name));
const passthrough = () => Object.fromEntries(Object.entries(attrs).filter(([key]) => !['class', 'name', 'required', 'value'].includes(key)));
function positionPopup() {
    if (!open.value || !trigger.value) return;
    const rect = trigger.value.getBoundingClientRect();
    const below = window.innerHeight - rect.bottom - 12;
    const above = rect.top - 12;
    const upward = below < 180 && above > below;
    const height = Math.max(40, Math.min(256, upward ? above : below));
    position.value = { position: 'fixed', width: `${Math.min(rect.width, window.innerWidth - 16)}px`, left: `${Math.max(8, Math.min(rect.left, window.innerWidth - rect.width - 8))}px`, maxHeight: `${height}px`, ...(upward ? { bottom: `${window.innerHeight - rect.top + 6}px` } : { top: `${rect.bottom + 6}px` }) };
}
function scrollActive() {
    nextTick(() => popup.value?.querySelector(`[id="${optionId(activeIndex.value)}"]`)?.scrollIntoView({ block: 'nearest' }));
}
function close() { open.value = false; typeahead = ''; clearTimeout(typeTimer); }
async function show(last = false) {
    if (props.disabled) return;
    open.value = true;
    const selected = renderedOptions.findIndex(option => same(option.value, props.modelValue) && !option.disabled);
    activeIndex.value = selected >= 0 ? selected : (last ? renderedOptions.findLastIndex(option => !option.disabled) : renderedOptions.findIndex(option => !option.disabled));
    await nextTick(); positionPopup(); scrollActive();
}
function choose(option) {
    if (!option || option.disabled || props.disabled) return;
    let value = option.value;
    if (props.modelModifiers.number && value !== '' && !Number.isNaN(Number(value))) value = Number(value);
    emit('update:modelValue', value);
    emit('change', { target: { value: String(option.value ?? ''), name: attrs.name ?? '' } });
    close(); trigger.value?.focus();
}
function move(direction) {
    const size = renderedOptions.length;
    for (let offset = 1; offset <= size; offset++) {
        const index = (activeIndex.value + direction * offset + size) % size;
        if (!renderedOptions[index].disabled) { activeIndex.value = index; scrollActive(); return; }
    }
}
function keydown(event) {
    if (props.disabled) return;
    if (event.key === 'Tab') { close(); return; }
    if (event.key === 'Escape') { if (open.value) { event.preventDefault(); event.stopPropagation(); close(); } return; }
    if (['ArrowDown', 'ArrowUp', 'Home', 'End', 'Enter', ' '].includes(event.key)) {
        event.preventDefault();
        if (!open.value) { show(event.key === 'ArrowUp' || event.key === 'End'); return; }
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') move(event.key === 'ArrowDown' ? 1 : -1);
        else if (event.key === 'Home' || event.key === 'End') { activeIndex.value = event.key === 'Home' ? renderedOptions.findIndex(option => !option.disabled) : renderedOptions.findLastIndex(option => !option.disabled); scrollActive(); }
        else choose(renderedOptions[activeIndex.value]);
        return;
    }
    if (event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey) {
        event.preventDefault();
        if (!open.value) show();
        typeahead += event.key.toLocaleLowerCase();
        clearTimeout(typeTimer); typeTimer = setTimeout(() => typeahead = '', 700);
        const repeated = [...typeahead].every(char => char === typeahead[0]);
        const prefix = repeated ? typeahead[0] : typeahead;
        const start = repeated ? activeIndex.value + 1 : 0;
        for (let offset = 0; offset < renderedOptions.length; offset++) {
            const index = (start + offset) % renderedOptions.length;
            if (!renderedOptions[index].disabled && renderedOptions[index].label.toLocaleLowerCase().startsWith(prefix)) { activeIndex.value = index; scrollActive(); break; }
        }
    }
}
const outside = event => { if (!root.value?.contains(event.target) && !popup.value?.contains(event.target)) close(); };
const scrolled = event => { if (!popup.value?.contains(event.target)) positionPopup(); };
watch(open, value => {
    const action = value ? 'addEventListener' : 'removeEventListener';
    document[action]('pointerdown', outside);
    window[action]('resize', positionPopup);
    window[action]('scroll', scrolled, true);
});
watch(() => props.disabled, disabled => { if (disabled) close(); });
onUpdated(() => {
    if (open.value && (!renderedOptions[activeIndex.value] || renderedOptions[activeIndex.value].disabled)) activeIndex.value = renderedOptions.findIndex(option => !option.disabled);
});
onBeforeUnmount(() => { clearTimeout(typeTimer); document.removeEventListener('pointerdown', outside); window.removeEventListener('resize', positionPopup); window.removeEventListener('scroll', scrolled, true); });
defineExpose({ focus: () => trigger.value?.focus() });
</script>

<template>
    <div ref="root" class="relative min-w-0" :class="wrapperClasses()">
        <button ref="trigger" v-bind="passthrough()" type="button" role="combobox" aria-haspopup="listbox" :aria-expanded="open" :aria-controls="listId" :aria-activedescendant="open && activeIndex >= 0 ? optionId(activeIndex) : undefined" :aria-required="attrs.required != null && attrs.required !== false ? true : undefined" :disabled="disabled" class="flex min-h-10 w-full items-center justify-between gap-3 rounded-lg border border-[#cfe6c8] bg-white px-3 py-2 text-left text-[#234222] focus:outline-none focus:ring-2 focus:ring-[#4f9f4a] disabled:cursor-not-allowed disabled:opacity-60" :class="triggerClasses()" @click="open ? close() : show()" @keydown="keydown">
            <span class="min-w-0 flex-1 truncate">{{ selectionLabel() }}</span><ChevronDownIcon class="h-4 w-4 shrink-0 transition" :class="{ 'rotate-180': open }" />
        </button>
        <input v-if="attrs.name" type="hidden" :name="attrs.name" :value="modelValue ?? ''" :disabled="disabled" />
        <Teleport to="body">
            <div v-if="open" :id="listId" ref="popup" role="listbox" :aria-label="attrs['aria-label'] || 'Options'" class="z-[10000] overflow-y-auto overscroll-contain rounded-xl border border-[#d8e7d4] bg-white p-1.5 shadow-xl" :style="position" @mousedown.prevent>
                <div v-for="(option, index) in renderedOptions" :id="optionId(index)" :key="`${String(option.value)}-${index}`" role="option" :aria-selected="same(option.value, modelValue)" :aria-disabled="option.disabled" class="flex cursor-pointer items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-left text-sm text-[#355733]" :class="{ 'bg-[#eef8ea]': index === activeIndex, 'cursor-not-allowed opacity-40': option.disabled }" @mouseenter="!option.disabled && (activeIndex = index)" @click="choose(option)">
                    <span class="min-w-0 break-words">{{ option.label }}</span><CheckIcon v-if="same(option.value, modelValue)" class="h-4 w-4 shrink-0 text-[#3c8a39]" />
                </div>
                <p v-if="!renderedOptions.length" class="px-3 py-2 text-sm text-slate-500">No options available.</p>
            </div>
        </Teleport>
    </div>
</template>
