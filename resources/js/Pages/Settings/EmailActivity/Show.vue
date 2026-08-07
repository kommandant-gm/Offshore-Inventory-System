<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ log: Object });
</script>

<template>
  <Head title="Email Details" />
  <AuthenticatedLayout>
    <section class="space-y-6">
      <header class="rounded-[2rem] border border-[#d8e7d4] bg-white p-7 shadow-sm">
        <Link :href="route('settings.email-activity.index')" class="text-sm font-bold text-[#2f7d32]">&larr; Back to Full Email Log</Link>
        <h1 class="mt-4 text-3xl font-bold text-[#234222]">Email Content</h1>
        <p class="mt-2 text-sm text-[#60745d]">Exact recorded notification content and delivery information.</p>
      </header>
      <section class="rounded-[1.7rem] border border-[#d8e7d4] bg-white p-6 shadow-sm">
        <dl class="grid gap-5 md:grid-cols-2"><div><dt class="text-xs font-bold uppercase tracking-wider text-[#7f9a7a]">Recipient</dt><dd class="mt-1 font-semibold text-[#234222]">{{ log.recipient }}</dd></div><div><dt class="text-xs font-bold uppercase tracking-wider text-[#7f9a7a]">Status</dt><dd class="mt-1 font-semibold capitalize" :class="log.status === 'failed' ? 'text-[#a70f29]' : 'text-[#047857]'">{{ log.status }}</dd></div><div><dt class="text-xs font-bold uppercase tracking-wider text-[#7f9a7a]">Subject</dt><dd class="mt-1 font-semibold text-[#234222]">{{ log.subject }}</dd></div><div><dt class="text-xs font-bold uppercase tracking-wider text-[#7f9a7a]">Notification type</dt><dd class="mt-1 text-[#60745d]">{{ log.type }}</dd></div><div><dt class="text-xs font-bold uppercase tracking-wider text-[#7f9a7a]">Created</dt><dd class="mt-1 text-[#60745d]">{{ log.created_at }}</dd></div><div><dt class="text-xs font-bold uppercase tracking-wider text-[#7f9a7a]">Sent</dt><dd class="mt-1 text-[#60745d]">{{ log.sent_at || 'Not sent' }}</dd></div></dl>
      </section>
      <section class="rounded-[1.7rem] border border-[#d8e7d4] bg-white p-6 shadow-sm"><h2 class="text-xl font-bold text-[#234222]">Message content</h2><pre class="mt-4 whitespace-pre-wrap rounded-xl bg-[#f7fbf5] p-5 text-sm leading-7 text-[#31415b]">{{ log.body || 'Content was not captured for this older activity record.' }}</pre></section>
      <section v-if="Object.keys(log.details || {}).length || log.action_url || log.attachment_name" class="rounded-[1.7rem] border border-[#d8e7d4] bg-white p-6 shadow-sm"><h2 class="text-xl font-bold text-[#234222]">Message components</h2><dl class="mt-4 space-y-3"><div v-for="(value, label) in log.details" :key="label" class="flex gap-4 border-b border-[#edf3eb] pb-3"><dt class="w-40 font-semibold text-[#60745d]">{{ label }}</dt><dd class="text-[#234222]">{{ value }}</dd></div><div v-if="log.action_url" class="flex gap-4"><dt class="w-40 font-semibold text-[#60745d]">Action link</dt><dd><a class="break-all text-[#2f7d32] underline" :href="log.action_url">{{ log.action_label || log.action_url }}</a></dd></div><div v-if="log.attachment_name" class="flex gap-4"><dt class="w-40 font-semibold text-[#60745d]">Attachment</dt><dd class="text-[#234222]">{{ log.attachment_name }}</dd></div></dl></section>
      <section v-if="log.error" class="rounded-[1.7rem] border border-[#ffc6cc] bg-[#fff8f8] p-6"><h2 class="font-bold text-[#a70f29]">Delivery error</h2><p class="mt-2 whitespace-pre-wrap text-sm text-[#a70f29]">{{ log.error }}</p></section>
    </section>
  </AuthenticatedLayout>
</template>
