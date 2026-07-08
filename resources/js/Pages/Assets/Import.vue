<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    defaultMovementDate: String,
});

const form = useForm({
    file: null,
    movement_date: props.defaultMovementDate,
});

const submit = () => {
    form.post(route('assets.import.store'));
};
</script>

<template>
    <Head title="Import Stock Items" />

    <AuthenticatedLayout>
        <PageHeader title="Import Stock Items" description="Upload the prepared CSV mapping and post supported monthly movement totals into the live inventory ledger." />

        <div class="grid gap-6 xl:grid-cols-[1.3fr,0.7fr]">
            <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-6 shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
                <form class="space-y-6" @submit.prevent="submit">
                    <div class="rounded-[1.5rem] border border-[#e1efdc] bg-[#fbfefa] p-5">
                        <label class="text-sm font-semibold text-[#234222]" for="file">Prepared CSV file</label>
                        <p class="mt-1 text-sm text-[#6f8a6b]">
                            Use the mapped CSV with columns like <span class="font-mono">proposed_item_code</span>, <span class="font-mono">category_name</span>, and movement totals.
                        </p>
                        <input
                            id="file"
                            type="file"
                            accept=".csv,text/csv"
                            class="mt-4 block w-full rounded-xl border border-[#d8e7d4] bg-white px-4 py-3 text-sm text-[#234222] file:mr-4 file:rounded-lg file:border-0 file:bg-[#eef8ea] file:px-4 file:py-2 file:font-semibold file:text-[#2f6f2d]"
                            @input="form.file = $event.target.files[0]"
                        />
                        <InputError class="mt-2" :message="form.errors.file" />
                    </div>

                    <div class="rounded-[1.5rem] border border-[#e1efdc] bg-[#fbfefa] p-5">
                        <label class="text-sm font-semibold text-[#234222]" for="movement_date">Movement date</label>
                        <p class="mt-1 text-sm text-[#6f8a6b]">
                            The importer posts monthly totals using this date. For the April 2026 file, use <span class="font-mono">2026-04-30</span>.
                        </p>
                        <input
                            id="movement_date"
                            v-model="form.movement_date"
                            type="date"
                            class="mt-4 block w-full rounded-xl border border-[#d8e7d4] bg-white px-4 py-3 text-sm text-[#234222]"
                        />
                        <InputError class="mt-2" :message="form.errors.movement_date" />
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row">
                        <PrimaryButton :disabled="form.processing">Import Into System</PrimaryButton>
                        <Link class="btn border-[#b8d7b1] bg-white text-[#2f6f2d] hover:bg-[#eef8ea]" :href="route('assets.index')">
                            Back To Stock Items
                        </Link>
                    </div>
                </form>
            </section>

            <aside class="rounded-[2rem] border border-[#d8e7d4] bg-white p-6 shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
                <h2 class="text-lg font-semibold text-[#234222]">Import Rules</h2>
                <div class="mt-4 space-y-3 text-sm text-[#5f7b5e]">
                    <p>Existing item codes are skipped so the import is safe to re-run.</p>
                    <p>Category and location masters are auto-created when missing.</p>
                    <p>Supported movement totals are posted for received, issued, material return, physical adjustment, and other misc.</p>
                    <p>Inter-location transfer and price adjustment figures are retained in item remarks because the source sheet does not provide enough detail for exact automated posting.</p>
                </div>
            </aside>
        </div>
    </AuthenticatedLayout>
</template>
