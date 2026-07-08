<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    stocktake: Object,
});
</script>

<template>
    <Head :title="stocktake.reference_no" />

    <AuthenticatedLayout>
        <PageHeader :title="stocktake.reference_no" :description="`Location: ${stocktake.location ?? 'N/A'}`" />

        <section class="rounded-[1.8rem] border border-[#d8e7d4] bg-white p-5 shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
            <div class="grid gap-4 md:grid-cols-4">
                <div><p class="text-xs uppercase tracking-[0.18em] text-[#7f9a7a]">Date</p><p class="mt-1 text-[#234222]">{{ stocktake.stocktake_date }}</p></div>
                <div><p class="text-xs uppercase tracking-[0.18em] text-[#7f9a7a]">Status</p><p class="mt-1 text-[#234222]">{{ stocktake.status }}</p></div>
                <div><p class="text-xs uppercase tracking-[0.18em] text-[#7f9a7a]">Created By</p><p class="mt-1 text-[#234222]">{{ stocktake.created_by }}</p></div>
                <div><p class="text-xs uppercase tracking-[0.18em] text-[#7f9a7a]">Completed At</p><p class="mt-1 text-[#234222]">{{ stocktake.completed_at }}</p></div>
            </div>
        </section>

        <section class="mt-6 space-y-4">
            <article v-for="item in stocktake.items" :key="item.id" class="rounded-[1.4rem] border border-[#d8e7d4] bg-white p-5 shadow-[0_14px_28px_rgba(79,159,74,0.08)]">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="font-mono text-sm text-[#3c8a39]">{{ item.item_code }}</p>
                        <p class="mt-1 text-base font-semibold text-[#234222]">{{ item.description }}</p>
                    </div>
                    <div class="text-sm text-[#5f7b5e]">
                        Adjustment Tx: {{ item.adjustment_transaction_id ?? 'None' }}
                    </div>
                </div>
                <div class="mt-4 grid gap-3 md:grid-cols-4">
                    <div><p class="text-xs uppercase tracking-[0.18em] text-[#7f9a7a]">System</p><p class="mt-1 text-[#234222]">{{ item.system_quantity }} {{ item.uom }}</p></div>
                    <div><p class="text-xs uppercase tracking-[0.18em] text-[#7f9a7a]">Counted</p><p class="mt-1 text-[#234222]">{{ item.counted_quantity }} {{ item.uom }}</p></div>
                    <div><p class="text-xs uppercase tracking-[0.18em] text-[#7f9a7a]">Variance</p><p class="mt-1 text-[#234222]">{{ item.variance_quantity }} {{ item.uom }}</p></div>
                    <div><p class="text-xs uppercase tracking-[0.18em] text-[#7f9a7a]">Remarks</p><p class="mt-1 text-[#234222]">{{ item.remarks ?? '-' }}</p></div>
                </div>
            </article>
        </section>
    </AuthenticatedLayout>
</template>
