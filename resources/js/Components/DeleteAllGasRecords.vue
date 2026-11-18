<script setup>
import { useForm } from '@inertiajs/vue3';

const emit = defineEmits(['deleted']);
const form = useForm({ confirmed: true, confirmation: '' });
function remove() {
    if (form.processing) return;
    form.clearErrors();
    const confirmation = window.prompt('Permanently delete ALL Miri Gas records (BIG and LGM) and their attachments across every page and company, regardless of filters? This cannot be undone.\n\nIf any record is linked to COG documents, nothing will be deleted.\n\nType DELETE ALL GAS to confirm.');
    if (confirmation === null) return;
    if (confirmation !== 'DELETE ALL GAS') {
        form.setError('confirmation', 'Confirmation must match DELETE ALL GAS. No records were deleted.');
        return;
    }
    form.confirmation = confirmation;
    form.delete(route('miri-gas.destroy-all'), {
        preserveScroll: true,
        onSuccess: () => { form.reset(); emit('deleted'); },
    });
}
</script>

<template>
    <div class="rounded-2xl border border-red-200 bg-white p-4">
        <button type="button" class="btn border-red-300 bg-white text-red-700 hover:bg-red-50" :disabled="form.processing" @click="remove">
            {{ form.processing ? 'Deleting Gas records...' : 'Delete all Gas records' }}
        </button>
        <p class="mt-2 text-sm text-slate-600">Applies to all Miri Gas records across every page and company, regardless of filters. Records linked to COG documents prevent deletion.</p>
        <p v-for="(error, key) in form.errors" :key="key" role="alert" class="mt-2 text-sm text-red-700">{{ error }}</p>
    </div>
</template>
