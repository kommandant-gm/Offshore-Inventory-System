<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    report: {
        type: Object,
        required: true,
    },
});

const severityClasses = {
    critical: {
        badge: 'border-[#f7c6c6] bg-[#fff1f1] text-[#a33a3a]',
        card: 'border-[#f1d0d0] bg-[linear-gradient(180deg,#ffffff_0%,#fff8f8_100%)]',
    },
    warning: {
        badge: 'border-[#e5ddb0] bg-[#fffbe8] text-[#8b6b13]',
        card: 'border-[#e9e0b7] bg-[linear-gradient(180deg,#ffffff_0%,#fffef6_100%)]',
    },
};
</script>

<template>
    <Head title="Stock Anomaly Agent" />

    <AuthenticatedLayout>
        <PageHeader
            title="Stock Anomaly Agent"
            description="Rule-based monitoring for negative balances, low stock, stale inventory, and location mismatches using your live inventory records."
        >
            <div class="rounded-full border border-[#d8e7d4] bg-white px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-[#4f6b4b]">
                Scanned {{ report.generated_at }}
            </div>
        </PageHeader>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-[1.5rem] border border-[#e1efdc] bg-white p-5 shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
                <p class="text-xs uppercase tracking-[0.2em] text-[#7f9a7a]">Total anomalies</p>
                <p class="mt-2 text-3xl font-bold text-[#234222]">{{ report.summary.total }}</p>
            </article>
            <article class="rounded-[1.5rem] border border-[#f1d0d0] bg-[#fff8f8] p-5 shadow-[0_18px_45px_rgba(163,58,58,0.08)]">
                <p class="text-xs uppercase tracking-[0.2em] text-[#b16565]">Critical</p>
                <p class="mt-2 text-3xl font-bold text-[#a33a3a]">{{ report.summary.critical }}</p>
            </article>
            <article class="rounded-[1.5rem] border border-[#e9e0b7] bg-[#fffef6] p-5 shadow-[0_18px_45px_rgba(139,107,19,0.08)]">
                <p class="text-xs uppercase tracking-[0.2em] text-[#9a8125]">Warnings</p>
                <p class="mt-2 text-3xl font-bold text-[#8b6b13]">{{ report.summary.warning }}</p>
            </article>
            <article class="rounded-[1.5rem] border border-[#e1efdc] bg-white p-5 shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
                <p class="text-xs uppercase tracking-[0.2em] text-[#7f9a7a]">Items affected</p>
                <p class="mt-2 text-3xl font-bold text-[#234222]">{{ report.summary.items_affected }}</p>
            </article>
        </div>

        <div class="grid gap-6 xl:grid-cols-[0.8fr,1.2fr]">
            <aside class="rounded-[2rem] border border-[#d8e7d4] bg-white p-5 shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
                <div class="rounded-[1.75rem] border border-[#d8e7d4] bg-[radial-gradient(circle_at_top_left,_rgba(111,187,104,0.16),_transparent_30%),linear-gradient(180deg,_#ffffff_0%,_#f7fcf5_52%,_#eef8ea_100%)] p-5">
                    <p class="text-sm text-[#6f8a6b]">Agent scope</p>
                    <h2 class="mt-1 text-2xl font-semibold text-[#234222]">Operational exceptions</h2>
                    <p class="mt-3 text-sm leading-6 text-[#4f6b4b]">
                        This agent is deterministic. It scans current balances and movement history, then highlights records that need inventory review.
                    </p>
                </div>

                <div class="mt-5 space-y-3">
                    <p class="text-sm font-semibold text-[#234222]">Detected rule groups</p>
                    <article
                        v-for="group in report.groups"
                        :key="group.type"
                        class="rounded-[1.25rem] border border-[#e1efdc] bg-[#fbfefa] px-4 py-3"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-[#234222]">{{ group.label }}</p>
                                <p class="mt-1 text-xs text-[#6f8a6b]">{{ group.count }} flagged records</p>
                            </div>
                            <span class="rounded-full border border-[#d8e7d4] bg-white px-3 py-1 text-xs font-semibold text-[#3c8a39]">
                                {{ group.critical > 0 ? `${group.critical} critical` : 'Monitor' }}
                            </span>
                        </div>
                    </article>
                    <p v-if="report.groups.length === 0" class="rounded-[1.25rem] border border-[#d8e7d4] bg-[#fbfefa] px-4 py-3 text-sm text-[#4f6b4b]">
                        No anomaly groups were detected in the current scan.
                    </p>
                </div>
            </aside>

            <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-5 shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-sm text-[#6f8a6b]">Live queue</p>
                        <h2 class="text-2xl font-semibold text-[#234222]">Items requiring attention</h2>
                    </div>
                    <span class="rounded-full border border-[#b8d7b1] bg-[#eef8ea] px-4 py-1 text-xs font-semibold text-[#3c8a39]">
                        Rule based
                    </span>
                </div>

                <div v-if="report.entries.length === 0" class="mt-5 rounded-[1.75rem] border border-[#d8e7d4] bg-[linear-gradient(180deg,#fbfefa_0%,#ffffff_100%)] p-6 text-sm text-[#4f6b4b]">
                    No stock anomalies are currently detected.
                </div>

                <div v-else class="mt-5 space-y-4">
                    <article
                        v-for="entry in report.entries"
                        :key="entry.id"
                        :class="severityClasses[entry.severity].card"
                        class="rounded-[1.75rem] border p-5 shadow-sm"
                    >
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        :class="severityClasses[entry.severity].badge"
                                        class="rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em]"
                                    >
                                        {{ entry.severity }}
                                    </span>
                                    <span class="rounded-full border border-[#d8e7d4] bg-white px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-[#6f8a6b]">
                                        {{ entry.title }}
                                    </span>
                                </div>
                                <div class="mt-3">
                                    <Link :href="entry.item.href" class="text-lg font-semibold text-[#234222] hover:text-[#3c8a39]">
                                        {{ entry.item.item_code }}
                                    </Link>
                                    <p class="mt-1 text-sm text-[#4f6b4b]">{{ entry.item.description }}</p>
                                </div>
                            </div>
                            <div class="rounded-[1.25rem] border border-[#e1efdc] bg-white px-4 py-3 text-sm text-[#234222]">
                                <p>Current stock: <span class="font-semibold">{{ entry.current_stock }} {{ entry.uom }}</span></p>
                                <p v-if="entry.minimum_stock !== null" class="mt-1">Minimum stock: <span class="font-semibold">{{ entry.minimum_stock }} {{ entry.uom }}</span></p>
                            </div>
                        </div>

                        <p class="mt-4 text-sm leading-6 text-[#4f6b4b]">{{ entry.detail }}</p>

                        <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                            <div class="rounded-[1.15rem] border border-[#e1efdc] bg-white px-4 py-3">
                                <p class="text-[11px] uppercase tracking-[0.2em] text-[#7f9a7a]">Category</p>
                                <p class="mt-2 text-sm font-semibold text-[#234222]">{{ entry.item.category ?? 'Unassigned' }}</p>
                            </div>
                            <div class="rounded-[1.15rem] border border-[#e1efdc] bg-white px-4 py-3">
                                <p class="text-[11px] uppercase tracking-[0.2em] text-[#7f9a7a]">Current location</p>
                                <p class="mt-2 text-sm font-semibold text-[#234222]">{{ entry.current_location ?? 'Not mapped' }}</p>
                            </div>
                            <div class="rounded-[1.15rem] border border-[#e1efdc] bg-white px-4 py-3">
                                <p class="text-[11px] uppercase tracking-[0.2em] text-[#7f9a7a]">Assigned location</p>
                                <p class="mt-2 text-sm font-semibold text-[#234222]">{{ entry.assigned_location ?? 'Not assigned' }}</p>
                            </div>
                            <div class="rounded-[1.15rem] border border-[#e1efdc] bg-white px-4 py-3">
                                <p class="text-[11px] uppercase tracking-[0.2em] text-[#7f9a7a]">Last movement</p>
                                <p class="mt-2 text-sm font-semibold text-[#234222]">
                                    {{ entry.last_movement ? `${entry.last_movement.date} • ${entry.last_movement.type}` : 'No movement recorded' }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 rounded-[1.25rem] border border-[#d8e7d4] bg-white px-4 py-3">
                            <p class="text-[11px] uppercase tracking-[0.2em] text-[#7f9a7a]">Recommended action</p>
                            <p class="mt-2 text-sm leading-6 text-[#4f6b4b]">{{ entry.recommendation }}</p>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
