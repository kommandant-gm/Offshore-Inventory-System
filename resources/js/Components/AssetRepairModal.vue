<script setup>
import CustomSelect from '@/Components/CustomSelect.vue';
import InputError from '@/Components/InputError.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
  assets: { type: Array, default: () => [] },
  assetId: { type: [Number, String], default: null },
});
const emit = defineEmits(['close']);
const today = new Date().toLocaleDateString('en-CA');
const form = useForm({
  asset_id: props.assetId ?? '',
  movement_date: today,
  handled_by: '',
  reference_no: '',
  remarks: '',
});
const submit = () => form.post(route('it-assets.repairs.store'), {
  preserveScroll: true,
  onSuccess: () => emit('close'),
});
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/45 p-4" @click.self="$emit('close')">
    <form class="w-full max-w-xl rounded-[1.75rem] bg-white p-6 shadow-2xl sm:p-7" @submit.prevent="submit">
      <div class="flex items-start justify-between gap-4">
        <div><p class="text-xs font-bold uppercase tracking-[.2em] text-amber-600">Asset lifecycle</p><h2 class="mt-1 text-2xl font-bold text-slate-800">Send asset for repair</h2><p class="mt-1 text-sm text-slate-500">Record the fault so staff can track the asset until it returns.</p></div>
        <button type="button" class="btn btn-sm" aria-label="Close" @click="$emit('close')">✕</button>
      </div>
      <div class="mt-6 grid gap-4 sm:grid-cols-2">
        <label class="form-control sm:col-span-2"><span class="mb-2 text-xs font-bold uppercase tracking-wider">Asset *</span><CustomSelect v-model="form.asset_id" :disabled="assetId !== null" class="select select-bordered"><option value="">Select an asset</option><option v-for="asset in assets" :key="asset.id" :value="asset.id">{{asset.label}}</option></CustomSelect><InputError :message="form.errors.asset_id" /></label>
        <label class="form-control"><span class="mb-2 text-xs font-bold uppercase tracking-wider">Sent date *</span><input v-model="form.movement_date" :max="today" type="date" class="input input-bordered"/><InputError :message="form.errors.movement_date" /></label>
        <label class="form-control"><span class="mb-2 text-xs font-bold uppercase tracking-wider">Vendor / technician</span><input v-model="form.handled_by" class="input input-bordered" placeholder="e.g. HP Service Centre"/><InputError :message="form.errors.handled_by" /></label>
        <label class="form-control sm:col-span-2"><span class="mb-2 text-xs font-bold uppercase tracking-wider">Job / reference number</span><input v-model="form.reference_no" class="input input-bordered" placeholder="Optional repair reference"/><InputError :message="form.errors.reference_no" /></label>
        <label class="form-control sm:col-span-2"><span class="mb-2 text-xs font-bold uppercase tracking-wider">Fault / repair details *</span><textarea v-model="form.remarks" class="textarea textarea-bordered min-h-28" placeholder="Describe the problem and work required"></textarea><InputError :message="form.errors.remarks" /></label>
      </div>
      <div class="mt-6 flex justify-end gap-3"><button type="button" class="btn" @click="$emit('close')">Cancel</button><button class="btn border-amber-600 bg-amber-500 text-white hover:bg-amber-600" :disabled="form.processing">{{form.processing ? 'Saving…' : 'Send for repair'}}</button></div>
    </form>
  </div>
</template>
