<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    transactions: Object,
});
</script>

<template>
    <Head title="Stock Transactions" />

    <AuthenticatedLayout>
        <PageHeader title="Stock Transactions" description="Receives, issues, returns, transfers, and adjustments for inventory items.">
            <Link class="btn bg-orange-600 text-white hover:bg-orange-700" :href="route('inventory.transactions.create')">New Transaction</Link>
        </PageHeader>

        <div class="overflow-hidden rounded-2xl border border-slate-700/60 bg-[#1e293b]">
            <table class="table">
                <thead>
                    <tr class="text-slate-400">
                        <th>Date</th>
                        <th>Item</th>
                        <th>Type</th>
                        <th>Qty</th>
                        <th>Cost</th>
                        <th>Value</th>
                        <th>Location</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="transaction in transactions.data" :key="transaction.id" class="hover:bg-slate-800/40">
                        <td class="font-mono text-slate-300">{{ transaction.transaction_date }}</td>
                        <td>
                            <p class="font-mono text-orange-300">{{ transaction.item_code }}</p>
                            <p class="text-xs text-slate-400">{{ transaction.description }}</p>
                        </td>
                        <td><StatusBadge :value="transaction.transaction_type" /></td>
                        <td class="text-slate-300">{{ transaction.quantity }}</td>
                        <td class="text-slate-300">{{ transaction.unit_cost }}</td>
                        <td class="text-slate-300">{{ transaction.total_value }}</td>
                        <td class="text-slate-300">{{ transaction.location ?? transaction.destination_location ?? transaction.source_location ?? '-' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
