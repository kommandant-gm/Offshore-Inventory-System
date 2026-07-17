<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, Link, router } from '@inertiajs/vue3';
defineProps({ logs:Object, level:String });
const filter=(level)=>router.get(route('settings.issue-logs.index'),level?{level}:{},{preserveState:true,replace:true});
</script>
<template><Head title="Issue Activity Log"/><AuthenticatedLayout>
  <PageHeader title="Issue Activity Log" description="Codex-only application diagnostics with persisted stack traces." />
  <div class="flex flex-wrap gap-2"><button v-for="option in [{v:'',l:'All'},{v:'error',l:'Errors'},{v:'warning',l:'Warnings'}]" :key="option.v" class="rounded-xl border px-4 py-2 text-sm font-bold" :class="level===option.v?'border-[#4f9f4a] bg-[#eef8ea] text-[#2f7d32]':'border-[#d8e7d4] bg-white text-[#60745d]'" @click="filter(option.v)">{{option.l}}</button></div>
  <div class="space-y-4"><article v-for="issue in logs.data" :key="issue.id" class="rounded-[1.5rem] border border-[#d8e7d4] bg-white p-5"><div class="flex flex-wrap items-center gap-3"><span class="rounded-full px-3 py-1 text-[11px] font-bold uppercase" :class="issue.level==='error'?'bg-[#ffe4e8] text-[#c41635]':'bg-[#fff1bd] text-[#a65300]'">{{issue.level}}</span><strong class="text-sm text-[#234222]">{{issue.created_at}}</strong><span class="text-xs text-[#7f9a7a]">{{issue.exception_class}}</span></div><p class="mt-4 break-words text-sm text-[#31415b]">{{issue.message}}</p><p v-if="issue.location" class="mt-2 break-all text-xs text-[#718096]">{{issue.location}}</p><p v-if="issue.url" class="mt-2 break-all text-xs text-[#718096]">{{issue.method}} {{issue.url}}</p><details v-if="issue.stack_trace" class="mt-4"><summary class="cursor-pointer text-sm font-bold text-[#2f7d32]">View stack trace</summary><pre class="mt-3 max-h-96 overflow-auto whitespace-pre-wrap rounded-xl bg-[#111827] p-4 text-xs text-[#d1fae5]">{{issue.stack_trace}}</pre></details></article><div v-if="!logs.data.length" class="rounded-2xl border border-dashed border-[#d8e7d4] bg-white p-12 text-center text-[#60745d]">No issues found.</div></div>
  <div class="flex flex-wrap gap-2"><Link v-for="link in logs.links" :key="link.label" v-html="link.label" :href="link.url||'#'" class="btn btn-sm" :class="{'btn-disabled':!link.url,'btn-success text-white':link.active}"/></div>
</AuthenticatedLayout></template>
