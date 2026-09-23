<script setup>
import { computed } from 'vue';
const props = defineProps({ quantities: { type: Object, required: true } });
const qty = value => value == null ? 'Not recorded' : Number(value).toLocaleString('en-GB', { maximumFractionDigits: 3 });
const types = computed(() => props.quantities.types.filter(type => type.label !== 'Other / Not recorded' || type.locations.some(row => row.records)));
const typeMax = computed(() => Math.max(1, ...types.value.flatMap(type => type.locations.flatMap(row => [row.stock ?? 0, row.issued]))));
const locationMax = computed(() => Math.max(1, ...props.quantities.locations.map(row => row.stock ?? 0)));
const width = (value, max) => `${Math.max(0, Number(value ?? 0)) / max * 100}%`;
</script>
<template>
    <section class="rounded-3xl border border-[#d8e7d4] bg-white p-6">
        <h2 class="text-lg font-bold text-[#234222]">Issued and available stock by paint type</h2>
        <p class="mt-2 text-xs text-slate-500">LTR · BTU and LBN · Issued in {{ quantities.period.slice(0, 7) }}</p>
        <div class="mt-4 flex flex-wrap gap-4 text-xs text-slate-600">
            <span><span class="mr-1 inline-block h-2 w-2 rounded-full bg-blue-600"></span>Issued this month</span>
            <span><span class="mr-1 inline-block h-2 w-2 rounded-full bg-emerald-500"></span>Available stock</span>
        </div>
        <div class="mt-5 space-y-6">
            <article v-for="type in types" :key="type.label">
                <h3 class="font-semibold text-[#486746]">{{ type.label }}</h3>
                <div v-for="row in type.locations" :key="row.label" class="mt-3 rounded-xl bg-slate-50 p-3">
                    <div class="flex flex-wrap items-baseline justify-between gap-2 text-sm">
                        <strong>{{ row.label }}</strong>
                        <span>Issued: {{ qty(row.issued) }} LTR</span>
                        <span>Stock: {{ qty(row.stock) }}<template v-if="row.stock != null"> LTR</template></span>
                    </div>
                    <div class="mt-2 space-y-1" aria-hidden="true">
                        <div class="h-1.5 rounded-full bg-slate-200"><div class="h-full rounded-full bg-blue-600" :style="{ width: width(row.issued, typeMax) }"></div></div>
                        <div class="h-1.5 rounded-full bg-slate-200"><div class="h-full rounded-full bg-emerald-500" :style="{ width: width(row.stock, typeMax) }"></div></div>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">{{ row.recorded }} / {{ row.records }} records have stock in LTR</p>
                </div>
            </article>
        </div>
        <p class="mt-5 text-xs leading-5 text-slate-500">Issued quantities include this month's COG Issue out entries, excluding cancelled issues. Grouped by the paint record's current location. Historical imported issues, transfers and supplier returns are excluded.</p>
    </section>
    <section class="rounded-3xl border border-[#d8e7d4] bg-white p-6">
        <h2 class="text-lg font-bold text-[#234222]">Available stock by location</h2>
        <p class="mt-2 text-xs text-slate-500">Total closing stock in LTR across all paint types.</p>
        <div class="mt-6 space-y-6">
            <div v-for="row in quantities.locations" :key="row.label">
                <div class="mb-2 flex justify-between gap-3 text-sm">
                    <strong class="text-[#486746]">{{ row.label }}</strong>
                    <span>{{ qty(row.stock) }}<template v-if="row.stock != null"> LTR</template></span>
                </div>
                <div class="h-2.5 overflow-hidden rounded-full bg-slate-100" aria-hidden="true"><div class="h-full rounded-full bg-gradient-to-r from-blue-600 to-violet-500" :style="{ width: width(row.stock, locationMax) }"></div></div>
                <p class="mt-2 text-xs text-slate-500">{{ row.recorded }} / {{ row.records }} records have stock in LTR</p>
            </div>
        </div>
        <p class="mt-6 text-xs leading-5 text-slate-500">BTU includes Bintulu stores and racks; LBN includes Labuan stores and racks. SKA and SBA use their named locations. Cans are not converted to litres.</p>
        <p v-if="quantities.excluded_records" class="mt-3 text-xs text-amber-700">{{ quantities.excluded_records }} records have other or unrecorded locations and are excluded from these charts.</p>
    </section>
</template>
