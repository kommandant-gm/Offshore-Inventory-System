<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    logs: Object,
});
</script>

<template>
    <Head title="Audit Trail" />

    <AuthenticatedLayout>
        <PageHeader title="Audit Trail" description="Recent system changes across stock items, movements, approvals, and access control." />

        <div class="space-y-4">
            <article
                v-for="log in logs.data"
                :key="log.id"
                class="rounded-[1.5rem] border border-[#d8e7d4] bg-white p-5 shadow-[0_14px_28px_rgba(79,159,74,0.08)]"
            >
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#7f9a7a]">{{ log.module }} / {{ log.event }}</p>
                        <p class="mt-1 text-base font-semibold text-[#234222]">{{ log.summary }}</p>
                        <p class="mt-1 text-sm text-[#6f8a6b]">{{ log.user ?? 'System' }}</p>
                    </div>
                    <p class="text-sm text-[#5f7b5e]">{{ log.created_at }}</p>
                </div>
                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    <pre class="overflow-x-auto rounded-xl border border-[#e1efdc] bg-[#fbfefa] p-4 text-xs text-[#355733]">{{ JSON.stringify(log.before ?? {}, null, 2) }}</pre>
                    <pre class="overflow-x-auto rounded-xl border border-[#e1efdc] bg-[#fbfefa] p-4 text-xs text-[#355733]">{{ JSON.stringify(log.after ?? {}, null, 2) }}</pre>
                </div>
            </article>
        </div>
    </AuthenticatedLayout>
</template>
