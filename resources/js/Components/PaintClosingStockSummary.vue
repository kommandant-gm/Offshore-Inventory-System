<script setup>
defineProps({ summary: { type: Object, required: true } });
const qty = value => value == null ? '-' : Number(value).toLocaleString('en-GB', { maximumFractionDigits: 3 });
</script>
<template>
    <section aria-label="Closing paint stock by location and paint type" class="space-y-3">
        <div>
            <h2 class="text-lg font-bold text-[#234222]">Balance stock (closing stock)</h2>
            <p class="mt-1 text-xs text-slate-500">Available stock in LTR. Follows applied filters across all pages.</p>
        </div>
        <div class="grid gap-4 lg:grid-cols-2">
            <article v-for="location in summary.locations" :key="location.label" class="overflow-hidden rounded-2xl border border-[#d8e7d4] bg-white">
                <h3 class="border-b border-[#d8e7d4] bg-[#f5f9f3] px-5 py-4 font-bold text-[#234222]">{{ location.label }} — {{ location.label === 'BTU' ? 'Bintulu' : 'Labuan' }}</h3>
                <dl class="grid grid-cols-2 divide-x divide-[#d8e7d4]">
                    <div v-for="paint in location.paints" :key="paint.label" class="min-w-0 p-5">
                        <dt class="text-sm font-semibold text-[#60745d]">{{ paint.label }}</dt>
                        <dd class="mt-2 text-2xl font-bold text-[#234222]">{{ qty(paint.litres) }} <span v-if="paint.litres != null" class="text-sm font-medium">LTR</span></dd>
                        <p class="mt-2 text-xs text-slate-500">{{ paint.recorded }} / {{ paint.records }} records have LTR balances</p>
                    </div>
                </dl>
            </article>
        </div>
        <p class="text-xs text-slate-500">Stores and racks are grouped under their location. “-” means no recorded LTR balance; zero means a recorded zero balance. Cans are not converted to litres.</p>
        <p v-if="summary.excluded_records" class="text-xs text-amber-700">{{ summary.excluded_records }} matching records have other or unrecorded locations/paint types and are excluded from this summary.</p>
    </section>
</template>
