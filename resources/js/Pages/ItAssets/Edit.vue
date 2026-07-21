<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CustomSelect from '@/Components/CustomSelect.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({ asset: Object, categories: Array, locations: Array, statuses: Array, conditions: Array });
const form = useForm({ ...props.asset });
const submit = () => form.patch(route('it-assets.update', props.asset.id));
</script>

<template>
  <Head :title="`Edit ${asset.asset_tag_no}`" />
  <AuthenticatedLayout>
    <form class="space-y-6" @submit.prevent="submit">
      <header class="flex flex-wrap items-end justify-between gap-4 rounded-[2rem] border border-[#d8e7d4] bg-white p-7">
        <div><p class="text-xs font-bold uppercase tracking-[.25em] text-[#4f9f4a]">IT Asset Register</p><h1 class="mt-2 text-3xl font-bold text-[#234222]">Edit asset</h1><p class="mt-2 text-sm text-[#60745d]">{{ asset.asset_tag_no }}</p></div>
        <Link class="btn" :href="route('it-assets.show', asset.id)">View asset</Link>
      </header>
      <section class="grid gap-5 rounded-[2rem] border border-[#d8e7d4] bg-white p-7 md:grid-cols-2 lg:grid-cols-3">
        <label v-for="field in [{k:'asset_tag_no',l:'Asset tag *'},{k:'serial_no',l:'Serial number'},{k:'description',l:'Description *'},{k:'brand',l:'Brand'},{k:'model',l:'Model'},{k:'operating_system',l:'Operating system'},{k:'purchase_year',l:'Purchase year',t:'number'},{k:'storage_position',l:'Storage position'},{k:'acquisition_date',l:'Acquisition date',t:'date'},{k:'acquisition_cost',l:'Acquisition cost',t:'number'},{k:'ownership',l:'Ownership'}]" :key="field.k" class="form-control">
          <span class="mb-2 text-xs font-bold uppercase tracking-wider text-[#60745d]">{{ field.l }}</span><input v-model="form[field.k]" :type="field.t || 'text'" class="input input-bordered" /><InputError :message="form.errors[field.k]" />
        </label>
        <label class="form-control"><span class="mb-2 text-xs font-bold uppercase tracking-wider">Category *</span><CustomSelect v-model="form.category_id" class="select select-bordered"><option v-for="x in categories" :key="x.id" :value="x.id">{{ x.name }}</option></CustomSelect><InputError :message="form.errors.category_id" /></label>
        <label class="form-control"><span class="mb-2 text-xs font-bold uppercase tracking-wider">Location</span><CustomSelect v-model="form.current_location_id" class="select select-bordered"><option value="">None</option><option v-for="x in locations" :key="x.id" :value="x.id">{{ x.code }} - {{ x.name }}</option></CustomSelect><InputError :message="form.errors.current_location_id" /></label>
        <label class="form-control"><span class="mb-2 text-xs font-bold uppercase tracking-wider">Status</span><CustomSelect v-model="form.current_status" disabled class="select select-bordered"><option v-for="x in statuses" :key="x.value" :value="x.value">{{ x.label }}</option></CustomSelect><span class="mt-1 text-xs text-[#7f9a7a]">Status changes automatically through checkout and check-in.</span><InputError :message="form.errors.current_status" /></label>
        <label class="form-control"><span class="mb-2 text-xs font-bold uppercase tracking-wider">Condition</span><CustomSelect v-model="form.current_condition" class="select select-bordered"><option value="">None</option><option v-for="x in conditions" :key="x.value" :value="x.value">{{ x.label }}</option></CustomSelect><InputError :message="form.errors.current_condition" /></label>
        <label class="form-control"><span class="mb-2 text-xs font-bold uppercase tracking-wider">Active</span><CustomSelect v-model="form.active" class="select select-bordered"><option :value="true">Active</option><option :value="false">Inactive</option></CustomSelect><InputError :message="form.errors.active" /></label>
        <label class="form-control md:col-span-2 lg:col-span-3"><span class="mb-2 text-xs font-bold uppercase tracking-wider">Remarks</span><textarea v-model="form.remarks" class="textarea textarea-bordered"></textarea><InputError :message="form.errors.remarks" /></label>
      </section>
      <div class="flex gap-3"><button class="btn bg-[#4f9f4a] text-white" :disabled="form.processing">Save changes</button><Link class="btn" :href="route('it-assets.show', asset.id)">Cancel</Link></div>
    </form>
  </AuthenticatedLayout>
</template>
