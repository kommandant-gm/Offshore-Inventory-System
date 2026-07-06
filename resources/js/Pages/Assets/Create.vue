<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    selectedCategoryId: Number,
    categoryOptions: Array,
    locationOptions: Array,
});

const form = useForm({
    item_code: '',
    description: '',
    category_id: props.selectedCategoryId ?? '',
    uom: 'PCS',
    default_location_id: '',
    opening_stock: '',
    standard_cost: '',
    minimum_stock: '',
    rack_no: '',
    active: true,
    remarks: '',
});

const selectedCategory = computed(() => props.categoryOptions.find((category) => category.value === form.category_id) ?? null);
const backLink = computed(() => route('assets.index', form.category_id ? { category: form.category_id } : {}));

const submit = () => {
    form.post(route('assets.store'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Create Stock Item" />

    <AuthenticatedLayout>
        <PageHeader title="Create Stock Item" description="Use a dedicated full-width workspace to register a new stock item record with complete details.">
            <div class="flex gap-3">
                <Link class="btn border-[#d8e7d4] bg-white text-[#2f6f2d] hover:bg-[#eef8ea]" :href="backLink">
                    Back To Stock Items
                </Link>
                <Link class="btn border-[#d8e7d4] bg-white text-[#2f6f2d] hover:bg-[#eef8ea]" :href="route('asset-ledger.index', form.category_id ? { category: form.category_id } : {})">
                    Open Ledger
                </Link>
            </div>
        </PageHeader>

        <section class="relative overflow-hidden rounded-[2.25rem] border border-[#d8e7d4] bg-white p-6 shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(111,187,104,0.14),transparent_26%),radial-gradient(circle_at_bottom_right,rgba(79,159,74,0.08),transparent_24%)]" />
            <div class="pointer-events-none absolute inset-0 bg-[linear-gradient(140deg,rgba(255,255,255,0.55),transparent_32%,transparent_68%,rgba(111,187,104,0.08))]" />

            <form class="relative space-y-6" @submit.prevent="submit">
                <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                    <div class="space-y-3">
                        <div class="inline-flex items-center gap-2 rounded-full border border-[#cfe6c8] bg-white/80 px-4 py-1.5 text-[11px] font-semibold uppercase tracking-[0.3em] text-[#4f6b4b]">
                            <span class="h-2 w-2 rounded-full bg-[#4f9f4a] shadow-[0_0_10px_rgba(79,159,74,0.45)]" />
                            Stock Item Creation
                        </div>
                        <div>
                            <h2 class="text-3xl font-semibold tracking-tight text-[#234222]">
                                {{ selectedCategory?.label ?? 'New stock item' }}
                            </h2>
                            <p class="mt-2 max-w-2xl text-sm leading-6 text-[#5f7b5e]">
                                Fill the full asset record here. Category, cost, opening stock, rack allocation, and default location all stay in one focused panel.
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3 xl:min-w-[420px]">
                        <div class="rounded-[1.35rem] border border-[#d8e7d4] bg-[#fbfefa] px-4 py-4 backdrop-blur-sm">
                            <p class="text-[11px] uppercase tracking-[0.24em] text-[#7f9a7a]">Status</p>
                            <p class="mt-2 text-sm font-semibold text-[#234222]">{{ form.active ? 'Active Item' : 'Inactive Item' }}</p>
                        </div>
                        <div class="rounded-[1.35rem] border border-[#d8e7d4] bg-[#fbfefa] px-4 py-4 backdrop-blur-sm">
                            <p class="text-[11px] uppercase tracking-[0.24em] text-[#7f9a7a]">Opening</p>
                            <p class="mt-2 text-sm font-semibold text-[#234222]">{{ form.opening_stock || '0' }} <span class="text-[#6f8a6b]">{{ form.uom || 'PCS' }}</span></p>
                        </div>
                        <div class="rounded-[1.35rem] border border-[#d8e7d4] bg-[#fbfefa] px-4 py-4 backdrop-blur-sm">
                            <p class="text-[11px] uppercase tracking-[0.24em] text-[#7f9a7a]">Rack</p>
                            <p class="mt-2 text-sm font-semibold text-[#234222]">{{ form.rack_no || 'Pending allocation' }}</p>
                        </div>
                    </div>
                </div>

                <div class="grid gap-6 xl:grid-cols-[1.5fr,1fr]">
                    <div class="space-y-6">
                        <section class="rounded-[1.9rem] border border-[#d8e7d4] bg-[#fbfefa] p-5">
                            <div class="mb-5">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-[#7f9a7a]">Identity</p>
                                <h3 class="mt-2 text-lg font-semibold text-[#234222]">Item profile</h3>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="group rounded-[1.5rem] border border-[#d8e7d4] bg-white p-4 transition hover:border-[#a8d09e]">
                                    <label class="mb-3 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Item Code</label>
                                    <TextInput v-model="form.item_code" class="w-full border-[#cfe6c8] bg-white text-[#234222] shadow-inner transition group-hover:shadow-[0_0_0_1px_rgba(79,159,74,0.16)]" />
                                    <InputError class="mt-2" :message="form.errors.item_code" />
                                </div>

                                <div class="group rounded-[1.5rem] border border-[#d8e7d4] bg-white p-4 transition hover:border-[#a8d09e]">
                                    <label class="mb-3 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Description</label>
                                    <TextInput v-model="form.description" class="w-full border-[#cfe6c8] bg-white text-[#234222] shadow-inner transition group-hover:shadow-[0_0_0_1px_rgba(79,159,74,0.16)]" :placeholder="selectedCategory?.label ?? 'Description'" />
                                    <InputError class="mt-2" :message="form.errors.description" />
                                </div>

                                <div class="group rounded-[1.5rem] border border-[#d8e7d4] bg-white p-4 transition hover:border-[#a8d09e]">
                                    <label class="mb-3 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Category</label>
                                    <select v-model="form.category_id" class="select w-full border-[#cfe6c8] bg-white text-[#234222] transition group-hover:border-[#a8d09e]">
                                        <option value="">Select category</option>
                                        <option v-for="option in categoryOptions" :key="option.value" :value="option.value">
                                            {{ option.code }} - {{ option.label }}
                                        </option>
                                    </select>
                                    <InputError class="mt-2" :message="form.errors.category_id" />
                                </div>

                                <div class="group rounded-[1.5rem] border border-[#d8e7d4] bg-white p-4 transition hover:border-[#a8d09e]">
                                    <label class="mb-3 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Default Location</label>
                                    <select v-model="form.default_location_id" class="select w-full border-[#cfe6c8] bg-white text-[#234222] transition group-hover:border-[#a8d09e]">
                                        <option value="">Default location</option>
                                        <option v-for="option in locationOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                    </select>
                                    <InputError class="mt-2" :message="form.errors.default_location_id" />
                                </div>
                            </div>
                        </section>

                        <section class="rounded-[1.9rem] border border-[#d8e7d4] bg-[#fbfefa] p-5">
                            <div class="mb-5">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-[#7f9a7a]">Stock Setup</p>
                                <h3 class="mt-2 text-lg font-semibold text-[#234222]">Opening and valuation</h3>
                            </div>

                            <div class="grid gap-4 md:grid-cols-4">
                                <div class="group rounded-[1.35rem] border border-[#d8e7d4] bg-white p-4 transition hover:border-[#a8d09e]">
                                    <label class="mb-3 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Unit of Measure</label>
                                    <TextInput v-model="form.uom" class="w-full border-[#cfe6c8] bg-white text-[#234222] shadow-inner transition group-hover:shadow-[0_0_0_1px_rgba(79,159,74,0.16)]" placeholder="PCS" />
                                    <InputError class="mt-2" :message="form.errors.uom" />
                                </div>

                                <div class="group rounded-[1.35rem] border border-[#d8e7d4] bg-white p-4 transition hover:border-[#a8d09e]">
                                    <label class="mb-3 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Opening Stock</label>
                                    <TextInput v-model="form.opening_stock" class="w-full border-[#cfe6c8] bg-white text-[#234222] shadow-inner transition group-hover:shadow-[0_0_0_1px_rgba(79,159,74,0.16)]" type="number" step="0.01" placeholder="0" />
                                    <InputError class="mt-2" :message="form.errors.opening_stock" />
                                </div>

                                <div class="group rounded-[1.35rem] border border-[#d8e7d4] bg-white p-4 transition hover:border-[#a8d09e]">
                                    <label class="mb-3 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Unit Cost</label>
                                    <TextInput v-model="form.standard_cost" class="w-full border-[#cfe6c8] bg-white text-[#234222] shadow-inner transition group-hover:shadow-[0_0_0_1px_rgba(79,159,74,0.16)]" type="number" step="0.01" placeholder="0.00" />
                                    <InputError class="mt-2" :message="form.errors.standard_cost" />
                                </div>

                                <div class="group rounded-[1.35rem] border border-[#d8e7d4] bg-white p-4 transition hover:border-[#a8d09e]">
                                    <label class="mb-3 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Minimum Stock</label>
                                    <TextInput v-model="form.minimum_stock" class="w-full border-[#cfe6c8] bg-white text-[#234222] shadow-inner transition group-hover:shadow-[0_0_0_1px_rgba(79,159,74,0.16)]" type="number" step="0.01" placeholder="0" />
                                    <InputError class="mt-2" :message="form.errors.minimum_stock" />
                                </div>
                            </div>
                        </section>
                    </div>

                    <div class="space-y-6">
                        <section class="rounded-[1.9rem] border border-[#d8e7d4] bg-[#fbfefa] p-5">
                            <div class="mb-5">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-[#7f9a7a]">Storage</p>
                                <h3 class="mt-2 text-lg font-semibold text-[#234222]">Rack and activity</h3>
                            </div>

                            <div class="space-y-4">
                                <div class="group rounded-[1.35rem] border border-[#d8e7d4] bg-white p-4 transition hover:border-[#a8d09e]">
                                    <label class="mb-3 block text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Rack No.</label>
                                    <TextInput v-model="form.rack_no" class="w-full border-[#cfe6c8] bg-white text-[#234222] shadow-inner transition group-hover:shadow-[0_0_0_1px_rgba(79,159,74,0.16)]" placeholder="Rack no." />
                                    <InputError class="mt-2" :message="form.errors.rack_no" />
                                </div>

                                <label class="flex items-center justify-between rounded-[1.35rem] border border-[#d8e7d4] bg-white px-4 py-4 text-[#234222] transition hover:border-[#a8d09e]">
                                    <div>
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7f9a7a]">Item Status</p>
                                        <p class="mt-1 text-sm text-[#234222]">Keep this item available for active stock movement and reporting.</p>
                                    </div>
                                    <input v-model="form.active" type="checkbox" class="checkbox border-[#b8d7b1]" />
                                </label>
                                <InputError :message="form.errors.active" />
                            </div>
                        </section>

                        <section class="rounded-[1.9rem] border border-[#d8e7d4] bg-[#fbfefa] p-5">
                            <div class="mb-5">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-[#7f9a7a]">Notes</p>
                                <h3 class="mt-2 text-lg font-semibold text-[#234222]">Remarks and handling context</h3>
                            </div>

                            <textarea
                                v-model="form.remarks"
                                class="textarea h-48 w-full border-[#cfe6c8] bg-white text-[#234222] placeholder:text-[#7f9a7a]"
                                rows="6"
                                placeholder="Write storage context, handling notes, purchase comments, or any operational detail relevant to this stock item..."
                            />
                            <InputError class="mt-2" :message="form.errors.remarks" />
                        </section>

                        <section class="rounded-[1.9rem] border border-[#cfe6c8] bg-[#eef8ea] p-5">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-[#3c8a39]">Submit</p>
                            <p class="mt-2 text-sm leading-6 text-[#4f6b4b]">
                                Saving this form creates a new stock item under the selected category and returns you to the category list.
                            </p>

                            <div class="mt-5 flex flex-wrap gap-3">
                                <PrimaryButton
                                    :disabled="form.processing"
                                    class="min-w-[220px] justify-center rounded-full border-none bg-[linear-gradient(135deg,#6fbb68_0%,#4f9f4a_100%)] px-6 py-3 text-sm font-bold uppercase tracking-[0.24em] text-white shadow-[0_18px_40px_rgba(79,159,74,0.24)] transition hover:scale-[1.02] hover:opacity-95"
                                >
                                    Register Stock Item
                                </PrimaryButton>
                                <Link class="btn border-[#d8e7d4] bg-white text-[#2f6f2d] hover:bg-[#eef8ea]" :href="backLink">
                                    Cancel
                                </Link>
                            </div>
                        </section>
                    </div>
                </div>
            </form>
        </section>
    </AuthenticatedLayout>
</template>
