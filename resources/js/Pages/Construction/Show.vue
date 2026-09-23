<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConstructionStockPanel from '@/Components/ConstructionStockPanel.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
const props = defineProps({ record: Object, fields: Array, attachmentSlots: Object, canEdit: Boolean, duplicates: Array, stockMovements: Object });
const groups = computed(() => [...new Set(props.fields.map(field => field.group))]);
const slotsFor = group => group === 'Certificate' ? ['certificate'] : group === 'Certification' ? ['inspection', 'conformity'] : [];
</script>
<template>
    <Head title="Construction record" />
    <AuthenticatedLayout>
        <section class="space-y-5">
            <header class="rounded-3xl border border-[#d8e7d4] bg-white p-6">
                <p class="text-xs font-bold uppercase text-green-700">Miri · Construction TEC, Garnet &amp; PPE Register</p>
                <h1 class="mt-2 text-2xl font-bold text-[#234222]">{{ record.description || 'Description not recorded' }}</h1>
                <p class="mt-2 text-sm text-slate-600">Record #{{ record.id }} · {{ record.tag_no || 'No tag' }} · {{ record.stock_initialized_at ? 'Stock tracking active' : 'Opening balance needs verification' }}</p>
                <div class="mt-4 flex gap-3"><Link :href="route('construction.index')" class="btn btn-sm">Back to register</Link><Link v-if="canEdit" :href="route('construction.edit', record.id)" class="btn btn-sm bg-[#234222] text-white">Edit record</Link></div>
            </header>
            <p class="rounded-xl border bg-white p-4 text-sm">Company: <strong>{{ record.company || 'Not assigned' }}</strong></p>
            <ConstructionStockPanel :record="record" :movements="stockMovements" :can-edit="canEdit" />
            <section v-if="record.review_flags.length || duplicates.length" class="rounded-2xl bg-amber-50 p-5">
                <h2 class="font-bold text-amber-900">Needs review</h2>
                <ul class="mt-2 list-inside list-disc text-sm text-amber-900"><li v-for="flag in record.review_flags" :key="flag">{{ flag }}</li></ul>
                <p v-if="duplicates.length" class="mt-3 text-sm font-semibold">Other records in this register with the same tag:</p>
                <Link v-for="other in duplicates" :key="other.id" :href="route('construction.show', other.id)" class="mt-1 block text-sm text-green-800 underline">#{{ other.id }} · {{ other.description || 'Unnamed' }} · {{ other.current_location || 'No location' }}</Link>
            </section>
            <section v-for="group in groups" :key="group" class="rounded-3xl border border-[#d8e7d4] bg-white p-6">
                <h2 class="text-lg font-bold text-[#234222]">{{ group }}</h2>
                <dl class="mt-4 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                    <div v-for="field in fields.filter(field => field.group === group)" :key="field.key"><dt class="text-xs font-semibold text-slate-500">{{ field.label }}</dt><dd class="mt-1 whitespace-pre-wrap break-words text-sm">{{ field.type === 'private' && !canEdit ? 'Restricted to inventory editors' : (record[field.key] ?? 'Not recorded') }}</dd></div>
                </dl>
                <div v-for="slot in slotsFor(group)" :key="slot" class="mt-4 border-t pt-3 text-sm">
                    <p class="font-semibold">{{ attachmentSlots[slot] }} attachment</p>
                    <div v-if="record.attachments[slot]" class="mt-2 flex flex-wrap gap-4">
                        <a :href="route('construction.attachment', { construction: record.id, slot })" target="_blank" rel="noopener" class="text-green-800 underline">Open {{ record.attachments[slot].name }}</a>
                        <a :href="route('construction.attachment', { construction: record.id, slot, download: 1 })" class="text-green-800 underline">Download</a>
                    </div><p v-else class="mt-1 text-slate-500">No attachment.</p>
                </div>
            </section>
            <section class="rounded-2xl border bg-white p-5"><h2 class="font-bold">Review note</h2><p class="mt-2 whitespace-pre-wrap text-sm">{{ record.review_note || 'No review note recorded.' }}</p></section>
            <details v-if="canEdit && record.original_values" class="rounded-2xl border bg-white p-5">
                <summary class="cursor-pointer font-bold">Original CSV values · {{ record.source_filename }} · CSV record {{ record.source_row }}</summary>
                <p class="mt-2 text-xs text-slate-500">Read-only source snapshot; corrections above do not alter the original file values.</p>
                <dl class="mt-4 grid gap-4 md:grid-cols-2"><div v-for="(field,index) in fields" :key="field.key"><dt class="text-xs text-slate-500">{{ field.group }} / {{ field.label }}</dt><dd class="whitespace-pre-wrap break-words text-sm">{{ record.original_values[index] || 'Blank in CSV' }}</dd></div></dl>
            </details>
        </section>
    </AuthenticatedLayout>
</template>
