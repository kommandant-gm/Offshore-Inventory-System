<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { onMounted, onBeforeUnmount } from 'vue';
const props = defineProps({ task: Object });
let timer;
onMounted(() => { timer = setInterval(() => {
    if (!document.hidden && ['queued','processing'].includes(props.task.status)) router.reload({ only: ['task'] });
}, 5000); });
onBeforeUnmount(() => clearInterval(timer));
</script>
<template>
    <Head title="Paint import progress" />
    <AuthenticatedLayout>
        <section class="space-y-5 rounded-3xl border border-[#d8e7d4] bg-white p-7">
            <h1 class="text-2xl font-bold text-[#234222]">Paint import progress</h1><p>{{ task.filename }}</p>
            <p role="status" class="font-semibold capitalize">{{ task.status }}</p>
            <p v-if="task.status === 'queued'" class="text-sm">Waiting for the import worker. You can leave this page and reopen it from Your recent imports.</p>
            <p v-if="task.status === 'processing'" class="text-sm">Processing source rows in the background. This page updates automatically.</p>
            <p v-if="task.error" role="alert" class="text-red-700">{{ task.error }}</p>
            <p v-if="task.result" class="text-sm">{{ task.result.created }} source rows imported; {{ task.result.needs_review }} flagged for review; {{ task.result.unconfirmed_dates }} dates remain unconfirmed. Possible repeated batches are highlighted in the register. No rows were merged and no historical stock movements were replayed.</p>
            <div class="flex gap-3"><Link :href="route('paint.index')" class="btn">Open register</Link><Link :href="route('paint.import')" class="btn">Import page</Link></div>
        </section>
    </AuthenticatedLayout>
</template>
