<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
  people: { type: Array, default: () => [] },
  summary: { type: Object, default: () => ({}) },
  filters: { type: Object, default: () => ({}) },
});

const form = reactive({ ...props.filters });
const hasSearch = computed(() => Boolean(form.search));
const applyFilters = () => router.get(route('it-people.index'), form, { preserveState: true, preserveScroll: true, replace: true });
const clearSearch = () => {
  form.search = '';
  applyFilters();
};
const initials = (name) => name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
</script>

<template>
  <Head title="People" />
  <AuthenticatedLayout>
    <section class="space-y-6">
      <header class="rounded-[2rem] border border-[#d8e7d4] bg-white p-7 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[.25em] text-[#4f9f4a]">KL IT Inventory</p>
        <h1 class="mt-2 text-3xl font-bold text-[#234222]">People</h1>
        <p class="mt-2 text-sm text-[#60745d]">See who holds company assets and software licences, with their complete custody history.</p>
      </header>

      <div class="grid gap-4 sm:grid-cols-3">
        <article v-for="card in [
          { label: 'People', value: summary.total },
          { label: 'Asset holders', value: summary.asset_holders },
          { label: 'Licence holders', value: summary.licence_holders },
        ]" :key="card.label" class="rounded-[1.5rem] border border-[#d8e7d4] bg-white p-5 shadow-sm">
          <p class="text-xs font-bold uppercase tracking-wider text-[#7f9a7a]">{{ card.label }}</p>
          <p class="mt-2 text-3xl font-black text-[#234222]">{{ card.value }}</p>
        </article>
      </div>

      <form class="rounded-[1.7rem] border border-[#d8e7d4] bg-white p-5 shadow-sm" @submit.prevent="applyFilters">
        <div class="flex flex-col gap-3 md:flex-row md:items-end">
          <label class="flex-1">
            <span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#60745d]">Search people</span>
            <input v-model.trim="form.search" type="search" class="w-full rounded-xl border-[#d8e7d4] text-sm focus:border-[#4f9f4a] focus:ring-[#4f9f4a]" placeholder="Name, employee ID, email or department" />
          </label>
          <div class="flex gap-2">
            <button v-if="hasSearch" type="button" class="rounded-xl border border-[#d8e7d4] px-4 py-3 text-sm font-semibold text-[#60745d] hover:bg-[#f5faf3]" @click="clearSearch">Clear</button>
            <button type="submit" class="rounded-xl bg-[#4f9f4a] px-5 py-3 text-sm font-bold text-white hover:bg-[#3f8d3d]">Search</button>
          </div>
        </div>
      </form>

      <section class="overflow-hidden rounded-[1.7rem] border border-[#d8e7d4] bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-[#edf3eb] px-5 py-4">
          <div>
            <h2 class="font-bold text-[#234222]">People directory</h2>
            <p class="mt-1 text-xs text-[#7f9a7a]">{{ people.length }} {{ people.length === 1 ? 'person' : 'people' }} shown</p>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="table">
            <thead>
              <tr><th>Person</th><th>Department</th><th>Current assets</th><th>Licences</th><th>Assignment history</th><th></th></tr>
            </thead>
            <tbody>
              <tr v-for="person in people" :key="person.token" class="hover:bg-[#f7fbf5]">
                <td>
                  <Link class="flex items-center gap-3" :href="route('it-people.show', person.token)">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#e8f5e4] text-sm font-black text-[#2f7d32]">{{ initials(person.name) }}</span>
                    <span>
                      <span class="block font-bold text-[#234222]">{{ person.name }}</span>
                      <span class="block text-xs text-[#7f9a7a]">{{ person.employee_id || person.email || 'No employee ID recorded' }}</span>
                    </span>
                  </Link>
                </td>
                <td class="text-[#60745d]">{{ person.department || 'Not specified' }}</td>
                <td><span class="inline-flex min-w-9 justify-center rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">{{ person.current_assets }}</span></td>
                <td><span class="inline-flex min-w-9 justify-center rounded-full bg-violet-50 px-3 py-1 text-xs font-bold text-violet-700">{{ person.licences }}</span></td>
                <td class="font-semibold text-[#405d3e]">{{ person.assignment_history }}</td>
                <td class="text-right"><Link class="btn btn-sm border-[#cfe6c8] bg-white text-[#2f7d32] hover:bg-[#eef8ea]" :href="route('it-people.show', person.token)">View profile</Link></td>
              </tr>
              <tr v-if="!people.length"><td colspan="6" class="py-12 text-center text-[#7f9a7a]">No people match your search.</td></tr>
            </tbody>
          </table>
        </div>
      </section>
    </section>
  </AuthenticatedLayout>
</template>
