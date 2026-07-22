<script setup>
import CustomSelect from '@/Components/CustomSelect.vue';
import InputError from '@/Components/InputError.vue';

defineProps({ form: Object, types: Array });
</script>

<template>
  <section class="space-y-7 rounded-[2rem] border border-[#d8e7d4] bg-white p-6 shadow-sm sm:p-7">
    <div>
      <p class="text-xs font-bold uppercase tracking-[.18em] text-[#4f9f4a]">Licence identity</p>
      <div class="mt-4 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
        <label class="form-control"><span class="mb-2 text-xs font-bold uppercase tracking-wider text-[#60745d]">Licence ID *</span><input v-model="form.license_code" class="input input-bordered" placeholder="LIC-M365-001" /><InputError :message="form.errors.license_code" /></label>
        <label class="form-control"><span class="mb-2 text-xs font-bold uppercase tracking-wider text-[#60745d]">Software / product *</span><input v-model="form.software_name" class="input input-bordered" placeholder="Microsoft 365 Business" /><InputError :message="form.errors.software_name" /></label>
        <label class="form-control"><span class="mb-2 text-xs font-bold uppercase tracking-wider text-[#60745d]">Vendor</span><input v-model="form.vendor" class="input input-bordered" placeholder="Microsoft" /><InputError :message="form.errors.vendor" /></label>
        <label class="form-control"><span class="mb-2 text-xs font-bold uppercase tracking-wider text-[#60745d]">Licence type *</span><CustomSelect v-model="form.license_type" class="select select-bordered"><option v-for="type in types" :key="type.value" :value="type.value">{{ type.label }}</option></CustomSelect><InputError :message="form.errors.license_type" /></label>
        <label class="form-control md:col-span-2"><span class="mb-2 text-xs font-bold uppercase tracking-wider text-[#60745d]">Licence key</span><input v-model="form.license_key" type="password" autocomplete="off" class="input input-bordered font-mono" placeholder="Stored securely and hidden from the register" /><span class="mt-1 text-xs text-[#7f9a7a]">Encrypted in the database and only available on the edit screen.</span><InputError :message="form.errors.license_key" /></label>
      </div>
    </div>

    <div class="border-t border-[#edf3eb] pt-7">
      <p class="text-xs font-bold uppercase tracking-[.18em] text-[#4f9f4a]">Allocation</p>
      <div class="mt-4 grid gap-5 md:grid-cols-2 lg:grid-cols-4">
        <label class="form-control"><span class="mb-2 text-xs font-bold uppercase tracking-wider text-[#60745d]">Purchased seats *</span><input v-model="form.seats_total" type="number" min="1" class="input input-bordered" /><InputError :message="form.errors.seats_total" /></label>
        <label class="form-control"><span class="mb-2 text-xs font-bold uppercase tracking-wider text-[#60745d]">Assigned seats *</span><input v-model="form.seats_assigned" type="number" min="0" :max="form.seats_total" class="input input-bordered" /><InputError :message="form.errors.seats_assigned" /></label>
        <label class="form-control"><span class="mb-2 text-xs font-bold uppercase tracking-wider text-[#60745d]">Assigned to / owner</span><input v-model="form.assigned_to" class="input input-bordered" placeholder="User or licence administrator" /><InputError :message="form.errors.assigned_to" /></label>
        <label class="form-control"><span class="mb-2 text-xs font-bold uppercase tracking-wider text-[#60745d]">Department</span><input v-model="form.department" class="input input-bordered" /><InputError :message="form.errors.department" /></label>
      </div>
    </div>

    <div class="border-t border-[#edf3eb] pt-7">
      <p class="text-xs font-bold uppercase tracking-[.18em] text-[#4f9f4a]">Purchase and renewal</p>
      <div class="mt-4 grid gap-5 md:grid-cols-2 lg:grid-cols-4">
        <label class="form-control"><span class="mb-2 text-xs font-bold uppercase tracking-wider text-[#60745d]">Purchase date</span><input v-model="form.purchase_date" type="date" class="input input-bordered" /><InputError :message="form.errors.purchase_date" /></label>
        <label class="form-control"><span class="mb-2 text-xs font-bold uppercase tracking-wider text-[#60745d]">Expiry date</span><input v-model="form.expiry_date" type="date" class="input input-bordered" /><InputError :message="form.errors.expiry_date" /></label>
        <label class="form-control"><span class="mb-2 text-xs font-bold uppercase tracking-wider text-[#60745d]">Renewal cost (RM)</span><input v-model="form.renewal_cost" type="number" min="0" step="0.01" class="input input-bordered" /><InputError :message="form.errors.renewal_cost" /></label>
        <label class="form-control"><span class="mb-2 text-xs font-bold uppercase tracking-wider text-[#60745d]">Auto renewal</span><CustomSelect v-model="form.auto_renew" class="select select-bordered"><option :value="false">No</option><option :value="true">Yes</option></CustomSelect><InputError :message="form.errors.auto_renew" /></label>
        <label class="form-control"><span class="mb-2 text-xs font-bold uppercase tracking-wider text-[#60745d]">Supplier</span><input v-model="form.supplier" class="input input-bordered" /><InputError :message="form.errors.supplier" /></label>
        <label class="form-control"><span class="mb-2 text-xs font-bold uppercase tracking-wider text-[#60745d]">PO / purchase reference</span><input v-model="form.purchase_reference" class="input input-bordered" /><InputError :message="form.errors.purchase_reference" /></label>
        <label class="form-control"><span class="mb-2 text-xs font-bold uppercase tracking-wider text-[#60745d]">Record state</span><CustomSelect v-model="form.active" class="select select-bordered"><option :value="true">Active</option><option :value="false">Inactive</option></CustomSelect><InputError :message="form.errors.active" /></label>
      </div>
    </div>

    <label class="form-control border-t border-[#edf3eb] pt-7"><span class="mb-2 text-xs font-bold uppercase tracking-wider text-[#60745d]">Remarks</span><textarea v-model="form.remarks" rows="4" class="textarea textarea-bordered"></textarea><InputError :message="form.errors.remarks" /></label>
  </section>
</template>
