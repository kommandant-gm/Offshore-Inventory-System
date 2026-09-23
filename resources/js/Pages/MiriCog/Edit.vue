<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
const props = defineProps({ cog: Object, editToken: String, groups: Array, lineFields: Array });
const form = useForm({
    ...Object.fromEntries(props.groups.flatMap(group => group.fields.map(([key]) => [key, props.cog[key] ?? '']))),
    remarks: props.cog.remarks ?? '', edit_token: props.editToken,
    items: props.cog.items.map(item => ({ id: item.id, ...Object.fromEntries(props.lineFields.map(([key]) => [key, item[key] ?? ''])) })),
});
const submit = () => form.patch(route('miri-cogs.update', props.cog.id));
</script>
<template>
    <Head title="Edit Internal Issue Note" />
    <AuthenticatedLayout>
        <form class="space-y-5" @submit.prevent="submit">
            <header class="rounded-3xl border border-[#d8e7d4] bg-white p-6">
                <h1 class="text-2xl font-bold text-[#234222]">Edit Internal Issue Note</h1>
                <p class="mt-2 text-sm text-slate-600">{{ cog.display_cog_no }} · {{ cog.movement_type }} · Draft</p>
                <p class="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">You can correct document details and item descriptions. Items, quantities, units and movement type are locked because they may already affect reservations or stock. To change them, cancel an eligible unfulfilled draft and create a new note. Signed and stock-confirmed notes cannot be edited.</p>
            </header>
            <div v-if="Object.keys(form.errors).length" class="rounded-xl bg-red-50 p-4 text-sm text-red-700" role="alert"><p v-for="(error, key) in form.errors" :key="key">{{ error }}</p></div>
            <section v-for="group in groups" :key="group.title" class="rounded-2xl border border-[#d8e7d4] bg-white p-5">
                <h2 class="font-bold text-[#234222]">{{ group.title }}</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <label v-for="[key, label, type] in group.fields" :key="key" class="text-sm">{{ label }}
                        <input v-model="form[key]" :type="type" :required="['document_date', 'issued_by_name'].includes(key)" maxlength="255" class="input input-bordered mt-2 w-full" />
                    </label>
                </div>
            </section>
            <section class="rounded-2xl border border-[#d8e7d4] bg-white p-5">
                <h2 class="font-bold text-[#234222]">Item details</h2>
                <article v-for="(item, index) in form.items" :key="item.id" class="mt-4 rounded-xl border p-4">
                    <h3 class="text-sm font-bold">Item {{ index + 1 }} · {{ cog.items[index].item_type }} #{{ cog.items[index].item_id }}</h3>
                    <p class="mt-2 text-sm text-slate-600">Quantity: {{ cog.items[index].quantity }} {{ cog.items[index].unit }} · Source: {{ cog.items[index].current_location || 'Not recorded' }}</p>
                    <p v-if="cog.items[index].batch_no" class="mt-1 text-sm text-slate-600">Batch: {{ cog.items[index].batch_no }}</p>
                    <div class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <label v-for="[key, label] in lineFields" :key="key" class="text-sm">{{ label }}
                            <textarea v-if="['mr_reference', 'remarks'].includes(key)" v-model="item[key]" maxlength="1000" rows="2" class="textarea textarea-bordered mt-2 w-full" />
                            <input v-else v-model="item[key]" :disabled="key === 'identifier' && cog.items[index].item_type === 'Paint'" maxlength="255" class="input input-bordered mt-2 w-full disabled:bg-slate-100" />
                        </label>
                    </div>
                </article>
            </section>
            <label class="block rounded-2xl border border-[#d8e7d4] bg-white p-5 text-sm font-bold">Document remarks<textarea v-model="form.remarks" rows="3" maxlength="1000" class="textarea textarea-bordered mt-2 w-full font-normal" /></label>
            <div class="flex gap-3"><button class="btn bg-[#234222] text-white" :disabled="form.processing">{{ form.processing ? 'Saving…' : 'Save changes' }}</button><Link :href="route('miri-cogs.show', cog.id)" class="btn">Cancel</Link></div>
        </form>
    </AuthenticatedLayout>
</template>
