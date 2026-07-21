<script setup>
import CustomSelect from '@/Components/CustomSelect.vue';
import InputError from '@/Components/InputError.vue';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps({
  asset: { type: Object, required: true },
  userOptions: { type: Array, default: () => [] },
});
const emit = defineEmits(['close']);

const now = new Date();
const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
const form = useForm({
  user_id: '',
  assigned_to_name: '',
  employee_id: '',
  department: '',
  assigned_at: today,
  remarks: '',
});

watch(() => form.user_id, (id) => {
  const user = props.userOptions.find((option) => String(option.id) === String(id));
  if (!user) return;
  form.assigned_to_name = user.name;
  form.employee_id = user.employee_id || '';
});

const submit = () => form.post(route('it-assets.checkout', props.asset.id), {
  preserveScroll: true,
  onSuccess: () => emit('close'),
});
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-[#173516]/45 p-4" @click.self="emit('close')">
    <form class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-[1.7rem] bg-white p-6 shadow-2xl" @submit.prevent="submit">
      <div class="flex items-start justify-between gap-4">
        <div>
          <p class="text-xs font-bold uppercase tracking-[.2em] text-[#4f9f4a]">{{ asset.is_assigned ? 'Reassign asset' : 'Checkout asset' }}</p>
          <h2 class="mt-1 text-2xl font-bold text-[#234222]">{{ asset.asset_tag_no || asset.asset_tag }}</h2>
          <p class="mt-1 text-sm text-[#60745d]">{{ asset.description }}</p>
        </div>
        <button type="button" class="rounded-full px-3 py-1 text-2xl text-[#60745d] hover:bg-[#eef8ea]" aria-label="Close" @click="emit('close')">&times;</button>
      </div>

      <InputError class="mt-4" :message="form.errors.asset" />
      <div class="mt-6 grid gap-4 sm:grid-cols-2">
        <label class="form-control sm:col-span-2">
          <span class="mb-2 text-xs font-bold uppercase tracking-wider text-[#60745d]">Choose system user (optional)</span>
          <CustomSelect v-model="form.user_id" class="select select-bordered">
            <option value="">Enter another person manually</option>
            <option v-for="user in userOptions" :key="user.id" :value="user.id">{{ user.name }}{{ user.email ? ` - ${user.email}` : '' }}</option>
          </CustomSelect>
        </label>
        <label class="form-control">
          <span class="mb-2 text-xs font-bold uppercase tracking-wider text-[#60745d]">Assigned to *</span>
          <input v-model.trim="form.assigned_to_name" class="input input-bordered" :readonly="Boolean(form.user_id)" />
          <InputError :message="form.errors.assigned_to_name" />
        </label>
        <label class="form-control">
          <span class="mb-2 text-xs font-bold uppercase tracking-wider text-[#60745d]">Employee ID</span>
          <input v-model.trim="form.employee_id" class="input input-bordered" :readonly="Boolean(form.user_id)" />
          <InputError :message="form.errors.employee_id" />
        </label>
        <label class="form-control">
          <span class="mb-2 text-xs font-bold uppercase tracking-wider text-[#60745d]">Department</span>
          <input v-model.trim="form.department" class="input input-bordered" />
          <InputError :message="form.errors.department" />
        </label>
        <label class="form-control">
          <span class="mb-2 text-xs font-bold uppercase tracking-wider text-[#60745d]">Checkout date *</span>
          <input v-model="form.assigned_at" type="date" class="input input-bordered" />
          <InputError :message="form.errors.assigned_at" />
        </label>
        <label class="form-control sm:col-span-2">
          <span class="mb-2 text-xs font-bold uppercase tracking-wider text-[#60745d]">Remarks</span>
          <textarea v-model.trim="form.remarks" class="textarea textarea-bordered"></textarea>
          <InputError :message="form.errors.remarks" />
        </label>
      </div>
      <div class="mt-6 flex justify-end gap-3">
        <button type="button" class="btn" @click="emit('close')">Cancel</button>
        <button class="btn bg-[#4f9f4a] text-white hover:bg-[#3f8d3d]" :disabled="form.processing">{{ asset.is_assigned ? 'Reassign asset' : 'Checkout asset' }}</button>
      </div>
    </form>
  </div>
</template>
