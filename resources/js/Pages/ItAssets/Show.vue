<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AssetAssignmentModal from '@/Components/AssetAssignmentModal.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ asset:Object, userOptions: { type: Array, default: () => [] } });
const page = usePage();
const canEdit = computed(() => page.props.auth?.user?.can?.it_assets_edit);
const assignmentOpen = ref(false);
const checkIn = () => {
  if (!window.confirm(`Check in ${props.asset.asset_tag_no} from ${props.asset.assigned_to}?`)) return;
  router.patch(route('it-assets.check-in', props.asset.id), {}, { preserveScroll: true });
};
</script>
<template><Head :title="asset.asset_tag_no"/><AuthenticatedLayout><section class="space-y-6">
  <header class="flex flex-wrap items-end justify-between gap-4 rounded-[2rem] border border-[#d8e7d4] bg-white p-7"><div><p class="text-xs font-bold uppercase tracking-[.25em] text-[#4f9f4a]">IT Asset</p><h1 class="mt-2 text-3xl font-bold">{{ asset.asset_tag_no }}</h1><p>{{ asset.model || asset.description }}</p></div><div v-if="canEdit" class="flex flex-wrap gap-2"><Link class="btn border-[#cfe6c8] bg-white" :href="route('it-assets.edit', asset.id)">Edit asset</Link><button v-if="asset.is_assigned" type="button" class="btn border-[#d9a74d] bg-[#fff8e8] text-[#805d17]" @click="checkIn">Check in</button><button v-else-if="asset.status === 'available'" type="button" class="btn bg-[#4f9f4a] text-white" @click="assignmentOpen = true">Checkout</button></div></header>
  <div class="grid gap-5 md:grid-cols-3"><div v-for="x in [['Serial',asset.serial_no],['Category',asset.category],['Status',asset.status],['Condition',asset.condition],['Location',asset.location],['Operating system',asset.operating_system],['Purchase year',asset.purchase_year],['Asset age',asset.age === null ? null : asset.age+' years'],['Checked out to',asset.assigned_to],['Department',asset.department]]" :key="x[0]" class="rounded-2xl border border-[#d8e7d4] bg-white p-5"><p class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ x[0] }}</p><p class="mt-2 font-semibold">{{ x[1] || '—' }}</p></div></div>
  <div class="rounded-[2rem] border border-[#d8e7d4] bg-white p-7"><h2 class="text-xl font-bold">Assignment history</h2><table class="table mt-4"><thead><tr><th>Employee</th><th>Department</th><th>Assigned</th><th>Returned</th></tr></thead><tbody><tr v-for="x in asset.assignments" :key="x.assigned_at+x.assigned_to_name"><td>{{x.assigned_to_name}}</td><td>{{x.department||'—'}}</td><td>{{x.assigned_at}}</td><td>{{x.returned_at||'Current'}}</td></tr></tbody></table></div>
  <Link class="btn" :href="route('it-assets.index')">Back to register</Link>
  <AssetAssignmentModal v-if="assignmentOpen" :asset="asset" :user-options="userOptions" @close="assignmentOpen = false" />
</section></AuthenticatedLayout></template>
