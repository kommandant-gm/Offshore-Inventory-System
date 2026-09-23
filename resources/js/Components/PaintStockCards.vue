<script setup>
import { reactive } from 'vue';
import CustomSelect from '@/Components/CustomSelect.vue';
const props = defineProps({ stockSummary: Object, scopeLabel: { type: String, default: 'All Miri Paint records' } });
const selected = reactive({ opening: '', closing: '' });
const qty = value => value == null ? 'Not recorded' : Number(value).toLocaleString('en-GB', { maximumFractionDigits: 3 });
const stockCards = [
    { title: 'Opening Stock', key: 'opening', litres: 'opening_litres', label: 'Opening quantity (LTR)', tone: 'border-t-blue-500' },
    { title: 'Closing Stock', key: 'closing', litres: 'balance_litres', label: 'Available to issue (LTR)', tone: 'border-t-emerald-500' },
];
const summary = card => selected[card.key] ? props.stockSummary.paint_types[selected[card.key]] : props.stockSummary;
</script>
<template>
    <section aria-label="Opening and closing stock summaries" class="space-y-3">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="text-lg font-bold text-[#234222]">Paint stock (LTR)</h2>
            <p class="text-xs text-slate-500">{{ stockSummary.records }} records · {{ scopeLabel }}</p>
        </div>
        <div class="grid gap-4 xl:grid-cols-2">
            <article v-for="card in stockCards" :key="card.key" class="rounded-2xl border border-[#d8e7d4] border-t-4 bg-white p-5" :class="card.tone">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-lg font-bold text-[#234222]">{{ card.title }}</h3>
                    <label class="w-full sm:w-52">
                        <span class="mb-1 block text-xs text-slate-500">Paint type</span>
                        <CustomSelect v-model="selected[card.key]" :aria-label="card.title + ' paint type'">
                            <option value="">All paint types</option>
                            <option value="ip">IP Paint</option>
                            <option value="hempel">Hempel Paint</option>
                            <option value="other">Other / Not recorded</option>
                        </CustomSelect>
                    </label>
                </div>
                <dl class="mt-5">
                    <dt class="text-sm text-slate-500">{{ card.label }}</dt>
                    <dd class="mt-1 text-3xl font-bold text-[#234222]">{{ qty(summary(card)[card.litres]) }}</dd>
                </dl>
                <p class="mt-2 text-xs text-slate-500">{{ summary(card)[card.litres + '_count'] }} / {{ summary(card).records }} records have LTR quantities</p>
                <p v-if="!Number(summary(card).records)" class="mt-2 text-sm text-slate-500">No matching paint records.</p>
            </article>
        </div>
        <p class="text-xs leading-5 text-slate-500">New Paint COGs issued in LTR deduct from available litres. Each month's closing balance becomes the next month's opening stock. CAN quantities are tracked separately and are not converted to litres; missing LTR balances stay unknown.</p>
    </section>
</template>
