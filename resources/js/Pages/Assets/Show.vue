<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

defineProps({
    item: Object,
});

const page = usePage();
</script>

<template>
    <Head :title="item.item_code" />

    <AuthenticatedLayout>
        <PageHeader :title="item.item_code" :description="item.description">
            <Link v-if="page.props.auth?.user?.can?.movements_edit" class="btn bg-orange-600 text-white hover:bg-orange-700" :href="route('asset-movements.create')">Log Movement</Link>
        </PageHeader>

        <div class="grid gap-6 xl:grid-cols-[380px,1fr]">
            <div class="rounded-2xl border border-slate-700/60 bg-[#1e293b] p-6">
                <h2 class="text-lg font-semibold text-white">Stock Item Summary</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Category</dt><dd class="text-white">{{ item.category }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Unit</dt><dd class="text-white">{{ item.uom }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Current Location</dt><dd class="text-white">{{ item.location ?? '-' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Opening Stock</dt><dd class="text-white">{{ item.opening_stock }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Current Stock</dt><dd class="text-white">{{ item.current_stock }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Unit Cost</dt><dd class="text-white">{{ item.standard_cost }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Minimum Stock</dt><dd class="text-white">{{ item.minimum_stock ?? '-' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Rack No.</dt><dd class="text-white">{{ item.rack_no ?? '-' }}</dd></div>
                </dl>

                <div class="mt-5 rounded-xl border border-slate-700/60 bg-slate-900/50 p-4 text-sm text-slate-300">
                    {{ item.remarks || 'No remarks recorded for this stock item.' }}
                </div>

                <div class="mt-5 rounded-xl border border-slate-700/60 bg-slate-900/50 p-4 text-sm text-slate-300">
                    <p class="font-semibold text-white">Location Balances</p>
                    <div v-if="item.location_balances?.length" class="mt-3 space-y-2">
                        <div v-for="balance in item.location_balances" :key="`${balance.location}-${balance.quantity}`" class="flex justify-between gap-4">
                            <span class="text-slate-400">{{ balance.location }}</span>
                            <span class="text-white">{{ balance.quantity }}</span>
                        </div>
                    </div>
                    <p v-else class="mt-3 text-slate-400">No location balance available.</p>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-700/60 bg-[#1e293b]">
                <div class="border-b border-slate-700/60 px-6 py-4">
                    <h2 class="text-lg font-semibold text-white">Stock Movement Ledger</h2>
                </div>

                <div class="divide-y divide-slate-700/60">
                    <div v-for="movement in item.transactions" :key="movement.id" class="px-6 py-4">
                        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <StatusBadge :value="movement.transaction_type" />
                                </div>
                                <p class="mt-2 text-sm text-slate-300">
                                    Qty: {{ movement.quantity }} | Unit cost: {{ movement.unit_cost }} | Value: {{ movement.total_value }}
                                </p>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ movement.source_location ?? movement.location ?? 'N/A' }} -> {{ movement.destination_location ?? movement.location ?? 'N/A' }}
                                </p>
                                <p class="mt-1 text-sm text-slate-400">{{ movement.remarks || 'No remarks.' }}</p>
                            </div>

                            <div class="text-sm text-slate-400">
                                <p>{{ movement.transaction_date }}</p>
                                <p>{{ movement.created_by || 'System' }}</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="item.transactions.length === 0" class="px-6 py-8 text-sm text-slate-400">No movement records yet.</div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
