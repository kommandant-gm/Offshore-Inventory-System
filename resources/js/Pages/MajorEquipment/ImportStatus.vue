<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { onMounted, onBeforeUnmount } from 'vue';
const props = defineProps({ task: Object });
let timer;
onMounted(() => { timer = setInterval(() => {
    if (['queued', 'processing'].includes(props.task.status)) router.reload({ only: ['task'] });
}, 5000); });
onBeforeUnmount(() => clearInterval(timer));
</script>
<template>
    <Head title="Import progress" />
    <AuthenticatedLayout>
        <section class="space-y-5 rounded-3xl border border-[#d8e7d4] bg-white p-7">
            <h1 class="text-2xl font-bold text-[#234222]">Major Equipment import</h1>
            <p>{{ task.filename }}</p>
            <p role="status" class="font-semibold capitalize">{{ task.status }}</p>
            <p v-if="task.status === 'queued'">Waiting for the import worker. You can leave this page and return to this URL to check progress.</p>
            <p v-if="task.status === 'processing'">Importing records and certificates. This page updates automatically.</p>
            <p v-if="task.error" role="alert" class="text-red-700">{{ task.error }}</p>
            <p v-if="task.result">{{ task.result.created }} records and {{ task.result.certificates_created }} certificates imported. {{ task.result.duplicate_records }} duplicate-tag records and {{ task.result.warning_records }} records with warnings — review Data quality in the register.</p>
            <Link class="btn" :href="route('major-equipment.index', { inventory_type: task.inventory_type })">Open register</Link>
        </section>
    </AuthenticatedLayout>
</template>
