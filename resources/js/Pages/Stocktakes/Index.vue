<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    stocktakes: Object,
});
</script>

<template>
    <Head title="Stocktakes" />

    <AuthenticatedLayout>
        <PageHeader title="Stocktakes" description="Physical counts with automatic adjustment posting and variance tracking.">
            <Link class="btn border-none bg-[linear-gradient(135deg,#6fbb68_0%,#4f9f4a_100%)] text-white" :href="route('stocktakes.create')">
                New Stocktake
            </Link>
        </PageHeader>

        <div class="space-y-4">
            <article
                v-for="stocktake in stocktakes.data"
                :key="stocktake.id"
                class="rounded-[1.5rem] border border-[#d8e7d4] bg-white p-5 shadow-[0_14px_28px_rgba(79,159,74,0.08)]"
            >
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="font-mono text-sm text-[#3c8a39]">{{ stocktake.reference_no }}</p>
                        <h2 class="mt-1 text-lg font-semibold text-[#234222]">{{ stocktake.location ?? 'Unassigned location' }}</h2>
                        <p class="mt-1 text-sm text-[#6f8a6b]">
                            {{ stocktake.items_count }} item(s) counted on {{ stocktake.stocktake_date }}
                        </p>
                    </div>
                    <div class="flex flex-col gap-2 sm:items-end">
                        <span class="rounded-full border border-[#b8d7b1] bg-[#eef8ea] px-3 py-1 text-xs font-semibold text-[#3c8a39]">
                            {{ stocktake.status }}
                        </span>
                        <Link class="btn border-[#d8e7d4] bg-white text-[#2f6f2d] hover:bg-[#eef8ea]" :href="route('stocktakes.show', stocktake.id)">
                            View
                        </Link>
                    </div>
                </div>
            </article>

            <div v-if="stocktakes.data.length === 0" class="rounded-[1.5rem] border border-dashed border-[#cfe6c8] bg-[#fbfefa] px-6 py-12 text-center text-sm text-[#6f8a6b]">
                No stocktakes recorded yet.
            </div>
        </div>
    </AuthenticatedLayout>
</template>
