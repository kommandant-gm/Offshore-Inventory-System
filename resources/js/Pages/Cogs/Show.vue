<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    cog: Object,
});

const money = (value) => Number(value ?? 0).toFixed(2);
</script>

<template>
    <Head :title="`COG ${cog.cog_no}`" />

    <AuthenticatedLayout>
        <PageHeader :title="`COG ${cog.cog_no}`" description="Document-style consignment note with receiver acknowledgement status.">
            <div class="flex gap-3">
                <Link class="btn border-slate-700 bg-slate-900/70 text-slate-200 hover:bg-slate-800" :href="route('cogs.index')">Back To COG</Link>
            </div>
        </PageHeader>

        <section class="rounded-[2rem] border border-slate-700/60 bg-white p-6 text-slate-900 shadow-2xl shadow-black/10">
            <div class="flex items-start justify-between gap-6 border-b border-slate-300 pb-6">
                <div>
                    <p class="text-3xl font-bold text-slate-900">DAYANG ENTERPRISE SDN. BHD.</p>
                    <p class="mt-1 text-sm text-slate-600">Consignment Note</p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-slate-500">COG No.</p>
                    <p class="text-3xl font-bold text-orange-600">{{ cog.cog_no }}</p>
                    <p class="mt-1 text-sm text-slate-600">{{ cog.document_date }}</p>
                    <span class="mt-3 inline-flex rounded-full border border-orange-200 bg-orange-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-orange-700">
                        {{ cog.status.replaceAll('_', ' ') }}
                    </span>
                </div>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4 text-sm">
                <div><p class="text-slate-500">Consignee</p><p class="font-semibold">{{ cog.consignee || '-' }}</p></div>
                <div><p class="text-slate-500">Shipper</p><p class="font-semibold">{{ cog.shipper || '-' }}</p></div>
                <div><p class="text-slate-500">Destination</p><p class="font-semibold">{{ cog.destination || '-' }}</p></div>
                <div><p class="text-slate-500">Vessel</p><p class="font-semibold">{{ cog.vessel || '-' }}</p></div>
                <div><p class="text-slate-500">Department</p><p class="font-semibold">{{ cog.department || '-' }}</p></div>
                <div><p class="text-slate-500">Receiver Name</p><p class="font-semibold">{{ cog.receiver_name || '-' }}</p></div>
                <div><p class="text-slate-500">Receiver Email</p><p class="font-semibold">{{ cog.receiver_email || '-' }}</p></div>
                <div><p class="text-slate-500">CC Emails</p><p class="font-semibold">{{ (cog.cc_emails?.length ? cog.cc_emails.join(', ') : '-') }}</p></div>
                <div><p class="text-slate-500">Receiver Designation</p><p class="font-semibold">{{ cog.receiver_designation || '-' }}</p></div>
            </div>

            <div class="mt-8 overflow-x-auto rounded-2xl border border-slate-300">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-100 text-slate-700">
                        <tr>
                            <th class="px-4 py-3 text-left">Qty</th>
                            <th class="px-4 py-3 text-left">Unit</th>
                            <th class="px-4 py-3 text-left">Part No.</th>
                            <th class="px-4 py-3 text-left">Full Description</th>
                            <th class="px-4 py-3 text-left">Measurement (Cu. Metre)</th>
                            <th class="px-4 py-3 text-left">Gross Wt. (Kg)</th>
                            <th class="px-4 py-3 text-left">PO No.</th>
                            <th class="px-4 py-3 text-left">Ex Location</th>
                            <th class="px-4 py-3 text-left">S/N</th>
                            <th class="px-4 py-3 text-left">Unit Price</th>
                            <th class="px-4 py-3 text-left">Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in cog.items" :key="item.id" class="border-t border-slate-200">
                            <td class="px-4 py-3">{{ item.qty }}</td>
                            <td class="px-4 py-3">{{ item.unit }}</td>
                            <td class="px-4 py-3">{{ item.part_no }}</td>
                            <td class="px-4 py-3">{{ item.full_description }}</td>
                            <td class="px-4 py-3">{{ item.measurement_cu_metre ?? '-' }}</td>
                            <td class="px-4 py-3">{{ item.gross_weight_kg ?? '-' }}</td>
                            <td class="px-4 py-3">{{ item.po_no || '-' }}</td>
                            <td class="px-4 py-3">{{ item.ex_location || '-' }}</td>
                            <td class="px-4 py-3">{{ item.serial_no || '-' }}</td>
                            <td class="px-4 py-3">{{ money(item.unit_price) }}</td>
                            <td class="px-4 py-3">{{ money(item.total_amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-8 grid gap-6 md:grid-cols-2 xl:grid-cols-4 text-sm">
                <div>
                    <p class="text-slate-500">Issued By</p>
                    <p class="font-semibold">{{ cog.issued_by_name || '-' }}</p>
                    <p class="text-slate-600">{{ cog.issued_by_designation || '-' }}</p>
                </div>
                <div>
                    <p class="text-slate-500">Checked By</p>
                    <p class="font-semibold">{{ cog.checked_by_name || '-' }}</p>
                    <p class="text-slate-600">{{ cog.checked_by_designation || '-' }}</p>
                </div>
                <div>
                    <p class="text-slate-500">Approval Sent</p>
                    <p class="font-semibold">{{ cog.approval_sent_at || '-' }}</p>
                </div>
                <div>
                    <p class="text-slate-500">Approved / Rejected</p>
                    <p class="font-semibold">{{ cog.approved_at || cog.rejected_at || '-' }}</p>
                </div>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
