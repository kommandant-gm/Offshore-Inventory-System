<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AssetAssignmentModal from '@/Components/AssetAssignmentModal.vue';
import AssetRepairModal from '@/Components/AssetRepairModal.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ asset:Object, userOptions: { type: Array, default: () => [] } });
const page = usePage();
const canEdit = computed(() => page.props.auth?.user?.can?.it_assets_edit);
const statusLabel = (status) => status === 'end_of_life' ? 'End of Life' : status.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
const bagLabel = (bag) => ({ asus_bag: 'Asus Bag (Set)', dell_bag: 'Dell Bag (Set)', others: 'Others' })[bag] || '—';
const assignmentOpen = ref(false);
const repairOpen = ref(false);
const checkIn = () => {
  if (!window.confirm(`Check in ${props.asset.asset_tag_no} from ${props.asset.assigned_to}?`)) return;
  router.patch(route('it-assets.check-in', props.asset.id), {}, { preserveScroll: true });
};
const resendCheckout = () => {
  if (!window.confirm(`Send a new checkout signing link to ${props.asset.assigned_email || 'the assigned staff member'}?`)) return;
  router.post(route('it-assets.checkout.resend', props.asset.id), {}, { preserveScroll: true });
};
const returnFromRepair = () => {
  if (!window.confirm(`Mark ${props.asset.asset_tag_no} as returned from repair and available?`)) return;
  router.patch(route('it-assets.repairs.return', props.asset.id), { movement_date: new Date().toLocaleDateString('en-CA') }, { preserveScroll: true });
};
</script>
<template><Head :title="asset.asset_tag_no"/><AuthenticatedLayout><section class="space-y-6">
  <header class="flex flex-wrap items-end justify-between gap-4 rounded-[2rem] border border-[#d8e7d4] bg-white p-7"><div><p class="text-xs font-bold uppercase tracking-[.25em] text-[#4f9f4a]">IT Asset</p><h1 class="mt-2 text-3xl font-bold">{{ asset.asset_tag_no }}</h1><p>{{ asset.model || asset.description }}</p></div><div v-if="canEdit" class="flex flex-wrap gap-2"><Link class="btn border-[#cfe6c8] bg-white" :href="route('it-assets.edit', asset.id)">Edit asset</Link><Link class="btn border-[#b8cde0] bg-[#f3f8fc] text-[#194568]" :href="route('it-assets.qr-code.show', asset.id)">{{asset.has_qr_code ? 'View QR code' : 'Generate QR code'}}</Link><button v-if="asset.checkout_pending" type="button" class="btn border-[#d9a74d] bg-[#fff8e8] text-[#805d17]" @click="resendCheckout">Resend checkout link</button><button v-else-if="asset.is_assigned" type="button" class="btn border-[#d9a74d] bg-[#fff8e8] text-[#805d17]" @click="checkIn">Check in</button><button v-else-if="asset.status === 'under_repair'" type="button" class="btn border-emerald-300 bg-emerald-50 text-emerald-800" @click="returnFromRepair">Return to service</button><template v-else-if="['available','damaged','inspection_hold'].includes(asset.status)"><button v-if="asset.status === 'available'" type="button" class="btn bg-[#4f9f4a] text-white" @click="assignmentOpen = true">Checkout</button><button type="button" class="btn border-amber-500 bg-amber-50 text-amber-800" @click="repairOpen = true">Send for repair</button></template></div></header>
  <div class="grid gap-5 md:grid-cols-3"><div v-for="x in [['Serial',asset.serial_no],['Category',asset.category],['Status',statusLabel(asset.status)],['Condition',asset.condition],['Location',asset.location],['Operating system',asset.operating_system],['Purchase year',asset.purchase_year],['Asset age',asset.age === null ? null : asset.age+' years'],['Checked out to',asset.assigned_to],['Department',asset.department]]" :key="x[0]" class="rounded-2xl border border-[#d8e7d4] bg-white p-5"><p class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ x[0] }}</p><p class="mt-2 font-semibold">{{ x[1] || '—' }}</p></div></div>
  <div v-if="asset.category?.trim().toLowerCase() === 'laptop'" class="rounded-2xl border border-[#d8e7d4] bg-white p-5"><p class="text-xs font-bold uppercase tracking-wider text-slate-500">Laptop Bag</p><p class="mt-2 font-semibold">{{ bagLabel(asset.bag) }}</p></div>
  <div class="rounded-[2rem] border border-[#d8e7d4] bg-white p-7"><h2 class="text-xl font-bold">Assignment history</h2><table class="table mt-4"><thead><tr><th>Employee</th><th>Department</th><th>Assigned</th><th>Returned</th></tr></thead><tbody><tr v-for="x in asset.assignments" :key="x.assigned_at+x.assigned_to_name"><td>{{x.assigned_to_name}}</td><td>{{x.department||'—'}}</td><td>{{x.assigned_at}}</td><td>{{x.returned_at||'Current'}}</td></tr></tbody></table></div>
  <Link class="btn" :href="route('it-assets.index')">Back to register</Link>
  <AssetAssignmentModal v-if="assignmentOpen" :asset="asset" :user-options="userOptions" @close="assignmentOpen = false" />
  <AssetRepairModal v-if="repairOpen" :asset-id="asset.id" :assets="[{id:asset.id,label:`${asset.asset_tag_no} - ${asset.model || asset.description}`} ]" @close="repairOpen = false" />
</section></AuthenticatedLayout></template>
