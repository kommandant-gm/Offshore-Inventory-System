<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    categories: Array,
});

const editingId = ref(null);
const isPanelOpen = ref(false);

const form = useForm({
    name: '',
    active: true,
});

const submitLabel = computed(() => editingId.value ? 'Update Category' : 'Create Category');
const pageModeLabel = computed(() => editingId.value ? 'Edit Category' : 'New Category');
const stats = computed(() => ({
    total: props.categories.length,
    active: props.categories.filter((category) => category.active).length,
    inactive: props.categories.filter((category) => !category.active).length,
}));

watch(editingId, (id) => {
    if (!id) {
        form.reset();
        form.active = true;
    }
});

const editCategory = (category) => {
    editingId.value = category.id;
    isPanelOpen.value = true;
    form.name = category.name;
    form.active = category.active;
};

const openPanel = () => {
    editingId.value = null;
    isPanelOpen.value = true;
};

const closePanel = () => {
    editingId.value = null;
    isPanelOpen.value = false;
};

const submit = () => {
    if (editingId.value) {
        form.put(route('categories.update', editingId.value), {
            preserveScroll: true,
            onSuccess: () => closePanel(),
        });
        return;
    }

    form.post(route('categories.store'), {
        preserveScroll: true,
        onSuccess: () => closePanel(),
    });
};
</script>

<template>
    <Head title="Categories" />

    <AuthenticatedLayout>
        <PageHeader title="Categories" description="Shared categorisation for stock items, ledger grouping, and future module expansion." />

        <div class="grid gap-6 xl:grid-cols-[1.8fr,1fr]">
            <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-5 shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
                <div class="mb-5 flex items-center justify-between">
                    <div>
                        <p class="text-sm text-[#6f8a6b]">Category Overview</p>
                        <h2 class="text-xl font-semibold text-[#234222]">Category Directory</h2>
                    </div>
                    <span class="rounded-full border border-[#b8d7b1] bg-[#eef8ea] px-4 py-1 text-xs font-semibold text-[#3c8a39]">
                        {{ stats.total }} categories
                    </span>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <article class="rounded-[1.5rem] border border-[#e1efdc] bg-[#fbfefa] p-5">
                        <p class="text-xs uppercase tracking-[0.2em] text-[#7f9a7a]">Total</p>
                        <p class="mt-2 text-3xl font-bold text-[#234222]">{{ stats.total }}</p>
                    </article>
                    <article class="rounded-[1.5rem] border border-[#e1efdc] bg-[#fbfefa] p-5">
                        <p class="text-xs uppercase tracking-[0.2em] text-[#7f9a7a]">Active</p>
                        <p class="mt-2 text-3xl font-bold text-[#3c8a39]">{{ stats.active }}</p>
                    </article>
                    <article class="rounded-[1.5rem] border border-[#e1efdc] bg-[#fbfefa] p-5">
                        <p class="text-xs uppercase tracking-[0.2em] text-[#7f9a7a]">Inactive</p>
                        <p class="mt-2 text-3xl font-bold text-amber-700">{{ stats.inactive }}</p>
                    </article>
                </div>
            </section>

            <aside class="rounded-[2rem] border border-[#d8e7d4] bg-white p-5 shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
                <div class="relative overflow-hidden rounded-[1.75rem] border border-[#d8e7d4] bg-[radial-gradient(circle_at_top_left,_rgba(111,187,104,0.16),_transparent_30%),linear-gradient(180deg,_#ffffff_0%,_#f7fcf5_52%,_#eef8ea_100%)] p-5 shadow-[0_20px_60px_rgba(79,159,74,0.12)]">
                    <div class="pointer-events-none absolute inset-0 bg-[linear-gradient(135deg,rgba(255,255,255,0.55),transparent_32%,transparent_70%,rgba(111,187,104,0.08))]" />

                    <div class="relative">
                        <div class="mb-3 inline-flex items-center gap-2 rounded-full border border-[#cfe6c8] bg-white/80 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-[#4f6b4b]">
                            <span class="h-2 w-2 rounded-full bg-[#4f9f4a] shadow-[0_0_10px_rgba(79,159,74,0.45)]" />
                            Category Control
                        </div>
                        <h2 class="text-2xl font-semibold tracking-tight text-[#234222]">Create Category Panel</h2>
                        <p class="mt-2 text-sm text-[#5f7b5e]">
                            Keep the master list focused, then open the panel only when you need to add or revise a category.
                        </p>

                        <div class="mt-5 flex flex-wrap items-center gap-3">
                            <button
                                type="button"
                                class="btn border-none bg-[linear-gradient(135deg,#6fbb68_0%,#4f9f4a_100%)] px-5 text-sm font-bold uppercase tracking-[0.2em] text-white shadow-[0_16px_36px_rgba(79,159,74,0.24)] transition hover:scale-[1.02] hover:opacity-95"
                                @click="openPanel"
                            >
                                Create Category
                            </button>
                        </div>
                    </div>
                </div>

                <transition
                    enter-active-class="transition duration-300 ease-out"
                    enter-from-class="translate-y-3 opacity-0"
                    enter-to-class="translate-y-0 opacity-100"
                    leave-active-class="transition duration-200 ease-in"
                    leave-from-class="translate-y-0 opacity-100"
                    leave-to-class="translate-y-2 opacity-0"
                >
                    <form
                        v-if="isPanelOpen"
                        class="relative mt-5 overflow-hidden rounded-[1.75rem] border border-[#d8e7d4] bg-[radial-gradient(circle_at_top_left,_rgba(111,187,104,0.16),_transparent_28%),linear-gradient(180deg,_#ffffff_0%,_#f7fcf5_52%,_#eef8ea_100%)] p-6 shadow-[0_30px_80px_rgba(79,159,74,0.16)]"
                        @submit.prevent="submit"
                    >
                        <div class="pointer-events-none absolute inset-0 bg-[linear-gradient(135deg,rgba(255,255,255,0.55),transparent_28%,transparent_72%,rgba(111,187,104,0.08))]" />

                        <div class="relative mb-6 flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm text-[#6f8a6b]">{{ pageModeLabel }}</p>
                                <h2 class="mt-1 text-2xl font-semibold tracking-tight text-[#234222]">{{ editingId ? 'Update existing category' : 'Define a new category' }}</h2>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="rounded-full border border-[#cfe6c8] bg-[#eef8ea] px-4 py-1.5 text-xs font-semibold uppercase tracking-[0.18em] text-[#3c8a39]">
                                    Stock Item
                                </div>
                                <button
                                    type="button"
                                    class="btn btn-circle btn-sm border-[#d8e7d4] bg-white text-[#6f8a6b] hover:bg-[#eef8ea] hover:text-[#234222]"
                                    @click="closePanel"
                                >
                                    ✕
                                </button>
                            </div>
                        </div>

                        <div class="relative mb-6">
                            <div class="rounded-[1.35rem] border border-[#d8e7d4] bg-white/90 px-4 py-3 backdrop-blur-sm">
                                <p class="text-[11px] uppercase tracking-[0.22em] text-[#7f9a7a]">Status</p>
                                <p class="mt-1 text-sm font-semibold text-[#234222]">{{ form.active ? 'Active Category' : 'Inactive Category' }}</p>
                            </div>
                        </div>

                        <div class="relative space-y-5">
                            <div class="rounded-[1.5rem] border border-dashed border-[#cfe6c8] bg-[#fbfefa] p-4">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Code</p>
                                <p class="mt-2 text-sm text-[#4f6b4b]">
                                    {{ editingId ? 'Existing code will be kept automatically.' : 'Code will be generated automatically when the category is created.' }}
                                </p>
                            </div>

                            <div class="group rounded-[1.5rem] border border-[#d8e7d4] bg-[#fbfefa] p-4 transition hover:border-[#a8d09e] hover:bg-white">
                                <label class="mb-3 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Name</label>
                                <TextInput v-model="form.name" class="w-full border-[#cfe6c8] bg-white text-[#234222] shadow-inner transition group-hover:shadow-[0_0_0_1px_rgba(79,159,74,0.16)]" />
                                <InputError class="mt-2" :message="form.errors.name" />
                            </div>

                            <div class="flex flex-col gap-4 pt-2 sm:flex-row sm:items-center sm:justify-between">
                                <label class="inline-flex items-center gap-3 rounded-full border border-[#d8e7d4] bg-white px-4 py-2 text-sm text-[#234222]">
                                    <input v-model="form.active" type="checkbox" class="checkbox checkbox-sm border-[#b8d7b1]" />
                                    Active
                                </label>

                                <div class="flex gap-3">
                                    <button
                                        v-if="editingId"
                                        type="button"
                                        class="btn border-[#d8e7d4] bg-white text-[#2f6f2d] hover:bg-[#eef8ea]"
                                        @click="closePanel"
                                    >
                                        Cancel
                                    </button>
                                    <PrimaryButton
                                        :disabled="form.processing"
                                        class="min-w-[220px] justify-center rounded-full border-none bg-[linear-gradient(135deg,#6fbb68_0%,#4f9f4a_100%)] px-6 py-3 text-sm font-bold uppercase tracking-[0.24em] text-white shadow-[0_18px_40px_rgba(79,159,74,0.24)] transition hover:scale-[1.02] hover:opacity-95"
                                    >
                                        {{ submitLabel }}
                                    </PrimaryButton>
                                </div>
                            </div>
                        </div>
                    </form>
                </transition>
            </aside>
        </div>

        <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-5 shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
            <div class="mb-5 flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-[#234222]">Category Directory</h2>
                    <p class="text-sm text-[#6f8a6b]">Browse and edit the category structure driving the stock item workspace.</p>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="category in categories"
                    :key="category.id"
                    class="rounded-[1.5rem] border border-[#d8e7d4] bg-[#fbfefa] p-5 transition hover:border-[#86c87b] hover:shadow-[0_14px_28px_rgba(79,159,74,0.10)]"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-mono text-xs text-[#3c8a39]">{{ category.code }}</p>
                            <h3 class="mt-1 text-lg font-semibold text-[#234222]">{{ category.name }}</h3>
                        </div>
                        <StatusBadge :value="category.active ? 'active' : 'inactive'" />
                    </div>

                    <div class="mt-5 flex items-center justify-between">
                        <span class="rounded-full border border-[#cfe6c8] bg-[#eef8ea] px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-[#3c8a39]">
                            Stock Item
                        </span>
                        <button
                            class="btn border-[#d8e7d4] bg-white text-[#2f6f2d] hover:bg-[#eef8ea]"
                            @click="editCategory(category)"
                        >
                            Edit
                        </button>
                    </div>
                </article>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
