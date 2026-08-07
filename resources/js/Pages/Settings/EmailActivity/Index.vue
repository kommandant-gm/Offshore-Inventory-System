<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ logs: Object });
</script>

<template>
  <Head title="Email Activity Log" />
  <AuthenticatedLayout>
    <section class="space-y-6">
      <header class="rounded-[2rem] border border-[#d8e7d4] bg-white p-7 shadow-sm">
        <Link :href="route('settings.index')" class="text-sm font-bold text-[#2f7d32]">&larr; Back to Settings</Link>
        <h1 class="mt-4 text-3xl font-bold text-[#234222]">Full Email Activity Log</h1>
        <p class="mt-2 text-sm text-[#60745d]">Review every recorded notification and open a message to inspect its exact contents.</p>
      </header>
      <section class="overflow-hidden rounded-[1.7rem] border border-[#d8e7d4] bg-white shadow-sm">
        <div class="overflow-x-auto"><table class="table"><thead><tr><th>Time</th><th>Recipient</th><th>Subject</th><th>Type</th><th>Status</th><th></th></tr></thead><tbody>
          <tr v-for="log in logs.data" :key="log.id" class="hover:bg-[#f7fbf5]"><td class="whitespace-nowrap text-xs">{{ log.time }}</td><td>{{ log.recipient }}</td><td>{{ log.subject }}</td><td class="text-xs text-[#65748b]">{{ log.type }}</td><td><span class="rounded-full px-3 py-1 text-xs font-bold capitalize" :class="log.status === 'sent' ? 'bg-[#d1fae5] text-[#047857]' : log.status === 'failed' ? 'bg-[#ffe4e8] text-[#a70f29]' : 'bg-[#fff1bd] text-[#914400]'">{{ log.status }}</span></td><td class="text-right"><Link class="btn btn-sm border-[#cfe6c8] bg-white text-[#2f7d32]" :href="route('settings.email-activity.show', log.id)">View content</Link></td></tr>
          <tr v-if="!logs.data.length"><td colspan="6" class="py-12 text-center text-[#7f9a7a]">No email activity has been recorded.</td></tr>
        </tbody></table></div>
      </section>
      <div class="flex flex-wrap gap-2"><Link v-for="link in logs.links" :key="link.label" :href="link.url || '#'" class="rounded-lg border px-3 py-2 text-xs" :class="link.active ? 'border-[#4f9f4a] bg-[#4f9f4a] text-white' : 'border-[#d8e7d4] bg-white text-[#60745d]'" v-html="link.label" /></div>
    </section>
  </AuthenticatedLayout>
</template>
