<script setup>
const props = defineProps({ stockSummary: Object, scopeLabel: { type: String, default: 'All Miri Paint records' } });
const qty = value => value === null ? 'Not recorded' : Number(value).toLocaleString('en-GB', { maximumFractionDigits: 3 });
const money = value => value === null ? 'Not recorded' : 'RM ' + Number(value).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const priceRange = key => {
    const min = props.stockSummary[key + '_min'], max = props.stockSummary[key + '_max'];
    return min === null ? 'Not recorded' : Number(min) === Number(max) ? money(min) : money(min) + ' – ' + money(max);
};
const stockCards = [
    { title: 'Opening Stock', prefix: 'opening', cans: 'opening_cans', litres: 'opening_litres', tone: 'border-t-blue-500' },
    { title: 'Closing Stock', prefix: 'closing', cans: 'balance_cans', litres: 'balance_litres', tone: 'border-t-emerald-500' },
];
</script>
<template>
            <section aria-label="Opening and closing stock summaries" class="space-y-3">
                <div class="flex flex-wrap items-baseline justify-between gap-2"><h2 class="text-lg font-bold text-[#234222]">Stock &amp; recorded prices</h2><p class="text-xs text-slate-500">{{ stockSummary.records }} records · {{ scopeLabel }}</p></div>
                <div class="grid gap-4 xl:grid-cols-2">
                    <article v-for="card in stockCards" :key="card.prefix" class="rounded-2xl border border-[#d8e7d4] border-t-4 bg-white p-5" :class="card.tone">
                        <h3 class="text-lg font-bold text-[#234222]">{{ card.title }}</h3>
                        <dl class="mt-4 grid gap-5 sm:grid-cols-2">
                            <div v-for="[key,label] in [[card.cans,'Recorded quantity (CAN)'],[card.litres,'Recorded quantity (LTR)']]" :key="key"><dt class="text-xs text-slate-500">{{ label }}</dt><dd class="mt-1 text-2xl font-bold text-[#234222]">{{ qty(stockSummary[key]) }}</dd><p class="mt-1 text-xs text-slate-500">{{ stockSummary[key + '_count'] }} / {{ stockSummary.records }} records filled</p></div>
                            <div><dt class="text-xs text-slate-500">Recorded unit price range</dt><dd class="mt-1 text-lg font-bold text-[#234222]">{{ priceRange(card.prefix + '_unit_price') }}</dd><p class="mt-1 text-xs text-slate-500">{{ stockSummary[card.prefix + '_unit_price_count'] }} / {{ stockSummary.records }} records filled · not summed or averaged</p></div>
                            <div><dt class="text-xs text-slate-500">Sum of recorded total prices</dt><dd class="mt-1 text-2xl font-bold text-[#234222]">{{ money(stockSummary[card.prefix + '_total_price']) }}</dd><p class="mt-1 text-xs text-slate-500">{{ stockSummary[card.prefix + '_total_price_count'] }} / {{ stockSummary.records }} records filled</p></div>
                        </dl>
                    </article>
                </div>
                <p class="text-xs leading-5 text-slate-500">New Paint COGs update closing stock. Each month carries closing quantities into opening stock. CAN and LTR are independent; missing balances stay unknown. Recorded prices are not recalculated. Imported historical issues are not replayed.</p>
            </section>
</template>
