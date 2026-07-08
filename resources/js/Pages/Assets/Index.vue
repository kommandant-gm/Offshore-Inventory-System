<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    categories: Array,
    items: Object,
    selectedCategoryId: Number,
});

const selectedCategory = computed(() => props.categories.find((category) => category.id === props.selectedCategoryId) ?? null);
const totals = computed(() => props.items.data.reduce((accumulator, item) => {
    accumulator.opening += Number(item.opening_stock ?? 0);
    accumulator.current += Number(item.current_stock ?? 0);
    accumulator.min += Number(item.minimum_stock ?? 0);

    return accumulator;
}, { opening: 0, current: 0, min: 0 }));

const selectCategory = (categoryId) => {
    router.get(route('assets.index'), { category: categoryId }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const fmt = (value) => Number(value ?? 0).toFixed(2).replace('.00', '');
</script>

<template>
    <Head title="Stock Items" />

    <AuthenticatedLayout>
        <PageHeader title="Stock Item Categories" description="Category-first stock registration with quick summaries, opening balances, and ledger drilldown." />

        <div class="grid gap-6 2xl:grid-cols-[1.8fr,1fr]">
            <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-5 shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
                <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-sm text-[#6f8a6b]">Category Overview</p>
                        <h2 class="text-lg font-semibold text-[#234222] sm:text-xl">Stock Item Groups</h2>
                    </div>
                    <span class="inline-flex w-fit rounded-full border border-[#b8d7b1] bg-[#eef8ea] px-4 py-1 text-xs font-semibold text-[#3c8a39]">
                        {{ categories.length }} categories
                    </span>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
                    <button
                        v-for="category in categories"
                        :key="category.id"
                        type="button"
                        class="rounded-[1.5rem] border p-4 text-left transition duration-200 sm:p-5"
                        :class="selectedCategoryId === category.id
                            ? 'border-[#86c87b] bg-gradient-to-br from-[#f8fff5] via-[#f1faed] to-[#e7f6e1] shadow-[0_18px_35px_rgba(79,159,74,0.12)]'
                            : 'border-[#d8e7d4] bg-[#fbfefa] hover:border-[#a8d09e] hover:bg-[#f4fbf1]'"
                        @click="selectCategory(category.id)"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-mono text-xs text-[#3c8a39]">{{ category.code }}</p>
                                <h3 class="mt-1 break-words text-base font-semibold text-[#234222] sm:text-lg">{{ category.name }}</h3>
                            </div>
                            <StatusBadge :value="category.active ? 'active' : 'inactive'" />
                        </div>

                        <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <p class="text-xs uppercase tracking-[0.2em] text-[#7f9a7a]">Items</p>
                                <p class="mt-1 text-2xl font-bold text-[#234222] sm:text-3xl">{{ category.item_count }}</p>
                            </div>
                            <div class="flex h-11 items-end gap-1">
                                <span
                                    v-for="bar in 6"
                                    :key="bar"
                                    class="w-2 rounded-full bg-[#6fbb68]"
                                    :style="{ height: `${18 + ((category.item_count + bar) % 5) * 7}px` }"
                                />
                            </div>
                        </div>
                    </button>
                </div>
            </section>

            <aside class="rounded-[2rem] border border-[#d8e7d4] bg-white p-5 shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
                <p class="text-sm text-[#6f8a6b]">Selected Category</p>
                <h2 class="mt-1 text-xl font-semibold text-[#234222] sm:text-2xl">{{ selectedCategory?.name ?? 'Choose a category' }}</h2>
                <p class="mt-2 font-mono text-xs text-[#3c8a39]">{{ selectedCategory?.code ?? 'No category selected' }}</p>

                <div class="mt-5 space-y-3">
                    <div class="rounded-[1.25rem] border border-[#e1efdc] bg-[#fbfefa] p-4">
                        <p class="text-xs uppercase tracking-[0.2em] text-[#7f9a7a]">Opening Units</p>
                        <p class="mt-2 text-2xl font-bold text-[#234222]">{{ totals.opening.toFixed(2).replace('.00', '') }}</p>
                    </div>
                    <div class="rounded-[1.25rem] border border-[#e1efdc] bg-[#fbfefa] p-4">
                        <p class="text-xs uppercase tracking-[0.2em] text-[#7f9a7a]">Current Units</p>
                        <p class="mt-2 text-2xl font-bold text-[#3c8a39]">{{ totals.current.toFixed(2).replace('.00', '') }}</p>
                    </div>
                    <div class="rounded-[1.25rem] border border-[#e1efdc] bg-[#fbfefa] p-4">
                        <p class="text-xs uppercase tracking-[0.2em] text-[#7f9a7a]">Min Threshold</p>
                        <p class="mt-2 text-2xl font-bold text-[#4f9f4a]">{{ totals.min.toFixed(2).replace('.00', '') }}</p>
                    </div>
                </div>
            </aside>
        </div>

        <div class="grid gap-6 2xl:grid-cols-[420px,1fr]">
            <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-5 shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
                <div class="relative overflow-hidden rounded-[1.75rem] border border-[#d8e7d4] bg-[radial-gradient(circle_at_top_left,_rgba(111,187,104,0.16),_transparent_32%),linear-gradient(180deg,_#ffffff_0%,_#f7fcf5_52%,_#eef8ea_100%)] p-5 shadow-[0_20px_60px_rgba(79,159,74,0.12)]">
                    <div class="pointer-events-none absolute inset-0 bg-[linear-gradient(135deg,rgba(255,255,255,0.55),transparent_32%,transparent_70%,rgba(111,187,104,0.08))]" />

                    <div class="relative">
                        <div class="mb-3 inline-flex items-center gap-2 rounded-full border border-[#cfe6c8] bg-white/80 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-[#4f6b4b]">
                            <span class="h-2 w-2 rounded-full bg-[#4f9f4a] shadow-[0_0_10px_rgba(79,159,74,0.45)]" />
                            Stock Item Creation
                        </div>
                        <h2 class="text-xl font-semibold tracking-tight text-[#234222] sm:text-2xl">Open full asset form</h2>
                        <p class="mt-2 text-sm text-[#5f7b5e]">
                            Launch a dedicated full-width creation page so the asset record can be filled in with proper space, context, and validation feedback.
                        </p>

                        <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                            <Link
                                class="btn w-full border-none bg-gradient-to-r from-[#6fbb68] to-[#4f9f4a] px-5 text-sm font-bold uppercase tracking-[0.2em] text-white shadow-[0_16px_36px_rgba(79,159,74,0.28)] transition hover:scale-[1.02] hover:from-[#7bc373] hover:to-[#5aa855] sm:w-auto"
                                :class="{ 'pointer-events-none opacity-50': !selectedCategoryId }"
                                :href="selectedCategoryId ? route('assets.create', { category: selectedCategoryId }) : '#'"
                            >
                                Create Stock Item
                            </Link>
                            <Link
                                class="btn w-full border-[#b8d7b1] bg-white text-[#2f6f2d] hover:bg-[#eef8ea] sm:w-auto"
                                :href="route('asset-ledger.index', { category: selectedCategoryId })"
                            >
                                Open Ledger
                            </Link>
                            <Link
                                class="btn w-full border-[#b8d7b1] bg-white text-[#2f6f2d] hover:bg-[#eef8ea] sm:w-auto"
                                :href="route('assets.import.create')"
                            >
                                Import CSV
                            </Link>
                        </div>

                        <p v-if="!selectedCategoryId" class="mt-3 text-xs font-medium text-[#4f9f4a]">
                            Select a category first to enable asset creation.
                        </p>
                    </div>
                </div>
            </section>

            <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-5 shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
                <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <h2 class="text-lg font-semibold text-[#234222]">Stock Items In Category</h2>
                        <p class="text-sm text-[#5f7b5e]">{{ selectedCategory?.name ?? 'Select a category to view items.' }}</p>
                    </div>
                    <Link class="btn w-full border-[#b8d7b1] bg-white text-[#2f6f2d] hover:bg-[#eef8ea] sm:w-auto" :href="route('asset-ledger.index', { category: selectedCategoryId })">
                        Open Ledger
                    </Link>
                </div>

                <div class="space-y-4">
                    <article
                        v-for="(item, index) in items.data"
                        :key="item.id"
                        class="rounded-[1.5rem] border border-[#d8e7d4] bg-[#fbfefa] p-5 transition hover:border-[#86c87b] hover:shadow-[0_14px_28px_rgba(79,159,74,0.10)]"
                    >
                        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                            <div class="flex min-w-0 items-start gap-4">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-[#eef8ea] text-sm font-bold text-[#3c8a39] ring-1 ring-[#cfe6c8] sm:h-12 sm:w-12">
                                    {{ index + 1 }}
                                </div>
                                <div class="min-w-0">
                                    <p class="font-mono text-xs text-[#3c8a39]">{{ item.item_code }}</p>
                                    <h3 class="mt-1 break-words text-base font-semibold text-[#234222] sm:text-lg">{{ item.description }}</h3>
                                    <p class="mt-1 break-words text-sm text-[#6f8a6b]">{{ item.location ?? 'No default location' }}</p>
                                </div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2 2xl:min-w-[480px] 2xl:grid-cols-4">
                                <div class="rounded-2xl border border-[#e1efdc] bg-white px-4 py-3">
                                    <p class="text-xs uppercase tracking-[0.18em] text-[#7f9a7a]">Opening</p>
                                    <p class="mt-1 text-base font-semibold text-[#234222] sm:text-lg">{{ fmt(item.opening_stock) }}</p>
                                </div>
                                <div class="rounded-2xl border border-[#e1efdc] bg-white px-4 py-3">
                                    <p class="text-xs uppercase tracking-[0.18em] text-[#7f9a7a]">Current</p>
                                    <p class="mt-1 text-base font-semibold text-[#3c8a39] sm:text-lg">{{ fmt(item.current_stock) }}</p>
                                </div>
                                <div class="rounded-2xl border border-[#e1efdc] bg-white px-4 py-3">
                                    <p class="text-xs uppercase tracking-[0.18em] text-[#7f9a7a]">Rack / UOM</p>
                                    <p class="mt-1 text-sm font-semibold text-[#234222]">{{ item.rack_no ?? '-' }} / {{ item.uom }}</p>
                                </div>
                                <div class="flex items-center justify-start sm:justify-end">
                                    <Link class="btn w-full border-[#b8d7b1] bg-white text-[#2f6f2d] hover:bg-[#eef8ea] sm:w-auto" :href="route('assets.show', item.id)">
                                        Ledger
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </article>

                    <div v-if="items.data.length === 0" class="rounded-[1.5rem] border border-dashed border-[#cfe6c8] bg-[#fbfefa] px-6 py-12 text-center text-sm text-[#6f8a6b]">
                        No stock items registered under this category yet.
                    </div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
