<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    cogs: Object,
});
</script>

<template>
    <Head title="COG" />

    <AuthenticatedLayout>
        <PageHeader title="COG Control" description="Consignment notes with receiver approval tracking and document status control.">
            <Link class="btn border-none bg-[linear-gradient(135deg,#6fbb68_0%,#4f9f4a_100%)] text-white shadow-[0_16px_36px_rgba(79,159,74,0.28)] hover:opacity-95" :href="route('cogs.create')">
                New COG
            </Link>
        </PageHeader>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="cog in cogs.data"
                :key="cog.id"
                class="rounded-[1.7rem] border border-[#d8e7d4] bg-white p-5 shadow-[0_18px_40px_rgba(79,159,74,0.12)]"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="font-mono text-xs text-[#4f9f4a]">{{ cog.cog_no }}</p>
                        <h2 class="mt-1 text-lg font-semibold text-[#234222]">{{ cog.destination || 'Destination pending' }}</h2>
                        <p class="mt-1 text-sm text-[#6f8a6b]">{{ cog.consignee || 'Consignee pending' }}</p>
                    </div>
                    <span class="rounded-full border border-[#cfe6c8] bg-[#eef8ea] px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-[#3c8a39]">
                        {{ cog.status.replaceAll('_', ' ') }}
                    </span>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3 text-xs">
                    <div class="rounded-xl border border-[#d8e7d4] bg-[#fbfefa] px-3 py-3">
                        <p class="text-[#7f9a7a]">Date</p>
                        <p class="mt-1 text-[#234222]">{{ cog.document_date }}</p>
                    </div>
                    <div class="rounded-xl border border-[#d8e7d4] bg-[#fbfefa] px-3 py-3">
                        <p class="text-[#7f9a7a]">Items</p>
                        <p class="mt-1 text-[#234222]">{{ cog.items_count }}</p>
                    </div>
                    <div class="col-span-2 rounded-xl border border-[#d8e7d4] bg-[#fbfefa] px-3 py-3">
                        <p class="text-[#7f9a7a]">Receiver</p>
                        <p class="mt-1 text-[#234222]">{{ cog.receiver_name || cog.receiver_email || 'Not assigned' }}</p>
                    </div>
                </div>

                <div class="mt-5">
                    <Link class="btn w-full border-[#d8e7d4] bg-white text-[#2f6f2d] hover:bg-[#eef8ea]" :href="route('cogs.show', cog.id)">
                        Open COG
                    </Link>
                </div>
            </article>
        </div>
    </AuthenticatedLayout>
</template>
