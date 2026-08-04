<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CustomSelect from '@/Components/CustomSelect.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({ assets: Array, categories: Array, locations: Array, conditions: Array });
const form = useForm({ category_id: '', current_location_id: '', current_condition: '', operating_system: '', purchase_year: '', ownership: '', active: '', remarks: '' });
const enabled = reactive({ category_id: false, current_location_id: false, current_condition: false, operating_system: false, purchase_year: false, ownership: false, active: false, remarks: false });
const submit = () => {
  if (!Object.values(enabled).some(Boolean)) return;
  const payload = { asset_ids: props.assets.map((asset) => asset.id) };
  Object.keys(enabled).forEach((key) => { if (enabled[key]) payload[key] = form[key] === '' ? null : form[key]; });
  if (!window.confirm(`Update ${props.assets.length} selected ${props.assets.length === 1 ? 'asset' : 'assets'}?`)) return;
  form.transform(() => payload).patch(route('it-assets.bulk-update'));
};
</script>

<template>
  <Head title="Bulk edit IT assets" />
  <AuthenticatedLayout>
    <section class="space-y-6">
      <header class="flex flex-wrap items-end justify-between gap-4 rounded-[2rem] border border-[#d8e7d4] bg-white p-7">
        <div><p class="text-xs font-bold uppercase tracking-[.25em] text-[#4f9f4a]">IT Asset Register</p><h1 class="mt-2 text-3xl font-bold text-[#234222]">Bulk edit assets</h1><p class="mt-2 text-sm text-[#60745d]">Update shared details for {{ assets.length }} selected {{ assets.length === 1 ? 'asset' : 'assets' }}.</p></div>
        <Link class="btn" :href="route('it-assets.index')">Cancel</Link>
      </header>
      <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-7">
        <div class="mb-5"><h2 class="font-bold text-[#234222]">Selected assets</h2><div class="mt-3 flex flex-wrap gap-2"><span v-for="asset in assets" :key="asset.id" class="rounded-full bg-[#f1f8ef] px-3 py-1.5 text-xs font-semibold text-[#395337]">{{ asset.asset_tag_no }}<span class="ml-1 text-[#7f9a7a]">{{ asset.label }}</span></span></div></div>
        <div class="border-t border-[#edf3eb] pt-5"><h2 class="font-bold text-[#234222]">Fields to update</h2><p class="mt-1 text-xs text-[#60745d]">Tick a field to apply it. Unticked fields will not change.</p></div>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <label class="rounded-xl border border-[#d8e7d4] p-3"><span class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-[#60745d]"><input v-model="enabled.category_id" type="checkbox" class="checkbox checkbox-xs" />Category</span><CustomSelect v-model="form.category_id" class="select select-sm mt-2 w-full" :disabled="!enabled.category_id"><option value="">Choose category</option><option v-for="category in categories" :key="category.id" :value="String(category.id)">{{ category.name }}</option></CustomSelect></label>
          <label class="rounded-xl border border-[#d8e7d4] p-3"><span class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-[#60745d]"><input v-model="enabled.current_location_id" type="checkbox" class="checkbox checkbox-xs" />Location</span><CustomSelect v-model="form.current_location_id" class="select select-sm mt-2 w-full" :disabled="!enabled.current_location_id"><option value="">Clear location</option><option v-for="location in locations" :key="location.id" :value="String(location.id)">{{ location.code }} - {{ location.name }}</option></CustomSelect></label>
          <label class="rounded-xl border border-[#d8e7d4] p-3"><span class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-[#60745d]"><input v-model="enabled.current_condition" type="checkbox" class="checkbox checkbox-xs" />Condition</span><CustomSelect v-model="form.current_condition" class="select select-sm mt-2 w-full" :disabled="!enabled.current_condition"><option value="">Clear condition</option><option v-for="condition in conditions" :key="condition.value" :value="condition.value">{{ condition.label }}</option></CustomSelect></label>
          <label v-for="field in [{ key: 'operating_system', label: 'Operating system' }, { key: 'purchase_year', label: 'Purchase year' }, { key: 'ownership', label: 'Ownership' }]" :key="field.key" class="rounded-xl border border-[#d8e7d4] p-3"><span class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-[#60745d]"><input v-model="enabled[field.key]" type="checkbox" class="checkbox checkbox-xs" />{{ field.label }}</span><input v-model="form[field.key]" :type="field.key === 'purchase_year' ? 'number' : 'text'" class="input input-sm mt-2 w-full" :disabled="!enabled[field.key]" :placeholder="field.key === 'purchase_year' ? 'e.g. 2026' : 'Set value'" /></label>
          <label class="rounded-xl border border-[#d8e7d4] p-3"><span class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-[#60745d]"><input v-model="enabled.active" type="checkbox" class="checkbox checkbox-xs" />Active</span><CustomSelect v-model="form.active" class="select select-sm mt-2 w-full" :disabled="!enabled.active"><option value="">Choose</option><option :value="true">Active</option><option :value="false">Inactive</option></CustomSelect></label>
          <label class="rounded-xl border border-[#d8e7d4] p-3 sm:col-span-2 lg:col-span-4"><span class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-[#60745d]"><input v-model="enabled.remarks" type="checkbox" class="checkbox checkbox-xs" />Remarks</span><textarea v-model="form.remarks" class="textarea textarea-sm mt-2 w-full" :disabled="!enabled.remarks" placeholder="Set remarks (leave blank to clear)"></textarea></label>
        </div>
        <div class="mt-5 flex justify-end"><button type="button" class="btn bg-[#4f9f4a] text-white" :disabled="form.processing || !Object.values(enabled).some(Boolean)" @click="submit">{{ form.processing ? 'Updating...' : 'Apply bulk changes' }}</button></div>
      </section>
    </section>
  </AuthenticatedLayout>
</template>
