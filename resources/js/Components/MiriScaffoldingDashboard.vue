<script setup>
import { Link } from '@inertiajs/vue3';
defineProps({ dashboard: Object, company: String });
const number = value => Number(value).toLocaleString(undefined, { maximumFractionDigits: 3 });
const date = value => String(value || '').slice(0, 10).split('-').reverse().join('/');
</script>

<template>
    <header class="rounded-3xl bg-gradient-to-br from-emerald-900 to-teal-700 p-8 text-white shadow-lg">
        <p class="text-xs font-semibold uppercase tracking-[.24em] text-emerald-200">Miri Inventory</p>
        <h1 class="mt-3 text-3xl font-bold sm:text-4xl">Scaffolding Dashboard</h1>
        <p class="mt-3 max-w-2xl text-sm text-emerald-100">Monitor verified yard stock, opening-stock verification and confirmed movements across contracts.</p>
        <Link :href="route('scaffolding.index', { company })" class="mt-6 inline-flex rounded-xl bg-white px-4 py-3 text-sm font-semibold text-emerald-900">View Scaffolding Register</Link>
        <Link :href="route('miri-reports.scaffolding', { company })" class="ml-3 mt-6 inline-flex rounded-xl border border-white/40 px-4 py-3 text-sm font-semibold text-white">Materials report</Link>
    </header>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <article v-for="[key, label, detail] in [['records', 'Scaffolding items', 'Register records'], ['verified', 'Verified yard balances', 'Item and yard combinations'], ['unverified', 'Awaiting verification', 'Opening stock not yet checked'], ['zero_balance', 'Zero stock balances', 'Verified balances only']]" :key="key" class="rounded-2xl border border-[#d8e7d4] bg-white p-5">
            <h2 class="text-sm font-semibold text-slate-600">{{ label }}</h2><p class="mt-2 text-3xl font-bold text-[#234222]">{{ dashboard.summary[key] }}</p><p class="mt-2 text-xs text-slate-500">{{ detail }}</p>
        </article>
    </div>
    <div class="grid gap-5 lg:grid-cols-2">
        <section v-for="yard in dashboard.yards" :key="yard.yard" class="rounded-3xl border border-[#d8e7d4] bg-white p-6">
            <h2 class="text-xl font-bold text-[#234222]">{{ yard.yard }} yard</h2>
            <p class="mt-2 text-sm text-slate-500">{{ yard.verified }} verified · {{ yard.unverified }} awaiting verification</p>
            <div class="mt-3 h-2 overflow-hidden rounded bg-slate-100" role="progressbar" :aria-label="yard.yard + ' stock verification'" :aria-valuenow="yard.verified" :aria-valuemax="dashboard.summary.records || 1" :aria-valuemin="0"><div class="h-full bg-[#4f9f4a]" :style="{ width: `${dashboard.summary.records ? yard.verified / dashboard.summary.records * 100 : 0}%` }" /></div>
            <table class="mt-5 w-full text-left text-sm"><thead class="border-b text-slate-500"><tr><th class="pb-2">Unit</th><th>Available stock</th><th>Verified items</th></tr></thead><tbody><tr v-for="balance in yard.balances" :key="balance.unit" class="border-b"><td class="py-3">{{ balance.unit }}</td><td class="font-bold text-green-800">{{ number(balance.quantity) }}</td><td>{{ balance.records }}</td></tr><tr v-if="!yard.balances.length"><td colspan="3" class="py-6 text-slate-500">No verified stock yet.</td></tr></tbody></table>
        </section>
    </div>
    <p class="text-sm text-slate-500">Quantities are grouped by unit; different units are never added together. Unverified stock is excluded. Draft issue notes do not deduct stock. Contracts share each yard's stock.</p>
    <div class="grid gap-5 lg:grid-cols-2">
        <section class="rounded-3xl border border-[#d8e7d4] bg-white p-6"><h2 class="text-lg font-bold text-[#234222]">Items by subcategory</h2><div class="mt-4 max-h-80 overflow-y-auto"><div v-for="category in dashboard.subcategories" :key="category.label" class="flex justify-between border-b py-3 text-sm"><span>{{ category.label }}</span><strong>{{ category.total }} records</strong></div><p v-if="!dashboard.subcategories.length" class="text-sm text-slate-500">No scaffolding items match this company.</p></div></section>
        <section class="rounded-3xl border border-[#d8e7d4] bg-white p-6"><h2 class="text-lg font-bold text-[#234222]">Opening stock to verify</h2><p class="mt-1 text-xs text-slate-500">Up to 10 items awaiting verification in one or both yards.</p><Link v-for="item in dashboard.needsVerification" :key="item.id" :href="route('major-equipment.show', item.id)" class="mt-3 block rounded-xl border p-3 text-sm hover:bg-green-50"><strong>{{ item.description || 'Unnamed item' }}</strong><span class="block text-xs text-slate-500">#{{ item.id }} · {{ item.section_2 || 'No subcategory' }} · {{ item.unit || 'Unit missing' }}</span></Link><p v-if="!dashboard.needsVerification.length" class="mt-4 text-sm text-slate-500">{{ dashboard.summary.records ? 'All yard balances are verified.' : 'No items to verify.' }}</p></section>
    </div>
    <section class="rounded-3xl border border-[#d8e7d4] bg-white p-6"><h2 class="text-lg font-bold text-[#234222]">Recent confirmed movements</h2><p class="mt-1 text-xs text-slate-500">Last 10 postings, including opening stock and corrections.</p><div class="mt-4 overflow-x-auto"><table class="w-full min-w-[1000px] text-left text-sm"><thead class="border-b text-slate-500"><tr><th class="pb-3">Document date</th><th>Item</th><th>Yard</th><th>Movement</th><th>Quantity change</th><th>Balance after</th><th>Contract / destination</th><th>COG / reference</th></tr></thead><tbody><tr v-for="movement in dashboard.recent" :key="movement.id" class="border-b"><td class="py-3">{{ date(movement.document_date) }}</td><td><Link :href="route('major-equipment.show', movement.equipment_id)" class="text-green-800 underline">{{ movement.description || `#${movement.equipment_id}` }}</Link></td><td>{{ movement.yard }}</td><td>{{ movement.kind.replaceAll('_', ' ') }}</td><td>{{ number(movement.quantity) }} {{ movement.unit }}</td><td>{{ number(movement.balance_after) }} {{ movement.unit }}</td><td>{{ movement.contract || '-' }} / {{ movement.destination || '-' }}</td><td>{{ movement.reference || '-' }}</td></tr><tr v-if="!dashboard.recent.length"><td colspan="8" class="py-8 text-center text-slate-500">No confirmed stock movements.</td></tr></tbody></table></div></section>
</template>
