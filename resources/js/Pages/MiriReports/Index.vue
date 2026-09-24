<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
</script>

<template>
    <Head title="Reports" />
    <AuthenticatedLayout>
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-[#234222]">Reports</h1>
                <p class="mt-1 text-sm text-slate-500">Select a report to preview and export to Excel.</p>
            </div>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <Link :href="route('miri-reports.rental')" class="block rounded-2xl border border-[#d8e7d4] bg-white p-6 transition hover:border-green-600 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-green-600">
                    <h2 class="text-lg font-semibold text-[#234222]">Equipment Rental List Summary</h2>
                    <p class="mt-2 text-sm text-slate-500">Monthly equipment rentals by project and location, using form DE-F-187A.</p>
                    <span class="mt-4 inline-block text-sm font-semibold text-green-700">Open report &rarr;</span>
                </Link>
                <Link :href="route('miri-reports.bintulu-paint')" class="block rounded-2xl border border-[#d8e7d4] bg-white p-6 transition hover:border-green-600 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-green-600">
                    <h2 class="text-lg font-semibold text-[#234222]">Bintulu Yard Paint Inventory Report</h2>
                    <p class="mt-2 text-sm text-slate-500">Monthly paint stock, movements and recorded values for Bintulu Yard.</p>
                    <span class="mt-4 inline-block text-sm font-semibold text-green-700">Open report &rarr;</span>
                </Link>
                <Link :href="route('miri-reports.bintulu-cidb')" class="block rounded-2xl border border-[#d8e7d4] bg-white p-6 transition hover:border-green-600 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-green-600">
                    <h2 class="text-lg font-semibold text-[#234222]">Bintulu Yard CIDB Training Item Inventory Report</h2>
                    <p class="mt-2 text-sm text-slate-500">Monthly CIDB training stock, movements and recorded values for Bintulu Yard.</p>
                    <span class="mt-4 inline-block text-sm font-semibold text-green-700">Open report &rarr;</span>
                </Link>
                <Link :href="route('miri-reports.bintulu-consumable')" class="block rounded-2xl border border-[#d8e7d4] bg-white p-6 transition hover:border-green-600 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-green-600">
                    <h2 class="text-lg font-semibold text-[#234222]">Bintulu Yard Consumable Inventory Report</h2>
                    <p class="mt-2 text-sm text-slate-500">Monthly consumable stock, movements and recorded values for Bintulu Yard.</p>
                    <span class="mt-4 inline-block text-sm font-semibold text-green-700">Open report &rarr;</span>
                </Link>
                <Link :href="route('miri-reports.bintulu-ppe')" class="block rounded-2xl border border-[#d8e7d4] bg-white p-6 transition hover:border-green-600 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-green-600">
                    <h2 class="text-lg font-semibold text-[#234222]">Bintulu Yard PPE Inventory Report</h2>
                    <p class="mt-2 text-sm text-slate-500">Monthly PPE stock, movements and recorded values for Bintulu Yard.</p>
                    <span class="mt-4 inline-block text-sm font-semibold text-green-700">Open report &rarr;</span>
                </Link>
                <Link :href="route('miri-reports.labuan-consumable')" class="block rounded-2xl border border-[#d8e7d4] bg-white p-6 transition hover:border-green-600 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-green-600">
                    <h2 class="text-lg font-semibold text-[#234222]">Labuan Warehouse General Store Consumable Inventory Report</h2>
                    <p class="mt-2 text-sm text-slate-500">Monthly Consumable and PPE stock with IN/OUT movement details for Labuan.</p>
                    <span class="mt-4 inline-block text-sm font-semibold text-green-700">Open report &rarr;</span>
                </Link>
                <Link :href="route('miri-reports.labuan-paint')" class="block rounded-2xl border border-[#d8e7d4] bg-white p-6 transition hover:border-green-600 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-green-600">
                    <h2 class="text-lg font-semibold text-[#234222]">Labuan Warehouse Paint Inventory Report</h2>
                    <p class="mt-2 text-sm text-slate-500">Paint receipt, batch, load-out and backload details in the Labuan Excel format.</p>
                    <span class="mt-4 inline-block text-sm font-semibold text-green-700">Open report &rarr;</span>
                </Link>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
