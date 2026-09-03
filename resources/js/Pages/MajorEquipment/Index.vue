<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ equipment: Object, search: String, canEdit: Boolean });
const search = ref(props.search ?? '');
const submit = () => router.get(route('major-equipment.index'), { search: search.value }, { preserveState: true, replace: true });
</script>

<template>
    <Head title="Miri Inventory" />
    <AuthenticatedLayout>
        <PageHeader title="Miri Inventory" description="Miri inventory register. The current import is under the Major Equipment category.">
            <div class="flex flex-wrap gap-2"><Link v-if="canEdit" class="btn bg-[#4f9f4a] text-white" :href="route('major-equipment.create')">Register equipment</Link><Link v-if="canEdit" class="btn border-[#b8d7b1] bg-white text-[#2f6f2d]" :href="route('major-equipment.import')">Import CSV</Link></div>
        </PageHeader>
        <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-5 shadow-sm">
            <form class="mb-5 flex flex-col gap-3 sm:flex-row" @submit.prevent="submit">
                <input v-model="search" class="input input-bordered w-full" placeholder="Search tag, serial, description, or location..." />
                <button class="btn border-[#b8d7b1] bg-white text-[#2f6f2d]">Search</button>
            </form>
            <div class="overflow-x-auto">
                <table class="table w-full">
                    <thead><tr><th>Tag No.</th><th>Description</th><th>Section</th><th>Current Location</th><th>Status</th><th></th></tr></thead>
                    <tbody><tr v-for="item in equipment.data" :key="item.id">
                        <td class="font-mono text-xs">{{ item.tag_no || '-' }}</td><td>{{ item.description || '-' }}</td>
                        <td>{{ item.section_1 || '-' }} / {{ item.section_2 || '-' }}</td><td>{{ item.current_location || '-' }}</td><td>{{ item.status || '-' }}</td>
                        <td><div class="flex gap-2"><Link class="btn btn-sm" :href="route('major-equipment.show', item.id)">View</Link><Link v-if="canEdit" class="btn btn-sm" :href="route('major-equipment.edit', item.id)">Edit</Link></div></td>
                    </tr></tbody>
                </table>
            </div>
            <p v-if="equipment.data.length === 0" class="py-10 text-center text-sm text-slate-500">No Major Equipment records yet.</p>
        </section>
    </AuthenticatedLayout>
</template>
