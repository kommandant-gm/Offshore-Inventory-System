<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
  person: Object,
  summary: Object,
  currentAssets: { type: Array, default: () => [] },
  licences: { type: Array, default: () => [] },
  history: { type: Array, default: () => [] },
});

const initials = (name) => name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
const label = (value) => value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
const formatDate = (date) => date ? new Intl.DateTimeFormat('en-MY', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(`${date}T00:00:00`)) : '—';
const statusStyles = {
  active: 'border-emerald-200 bg-emerald-50 text-emerald-700',
  expiring_soon: 'border-amber-200 bg-amber-50 text-amber-700',
  expired: 'border-red-200 bg-red-50 text-red-700',
  inactive: 'border-slate-200 bg-slate-100 text-slate-600',
};
</script>

<template>
  <Head :title="person.name" />
  <AuthenticatedLayout>
    <section class="space-y-6">
      <header class="rounded-[2rem] border border-[#d8e7d4] bg-white p-7 shadow-sm">
        <Link :href="route('it-people.index')" class="text-sm font-bold text-[#2f7d32]">&larr; Back to People</Link>
        <div class="mt-5 flex flex-col gap-5 sm:flex-row sm:items-center">
          <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-[1.6rem] bg-[linear-gradient(135deg,#6fbb68,#3f8d3d)] text-2xl font-black text-white shadow-lg">{{ initials(person.name) }}</div>
          <div>
            <p class="text-xs font-bold uppercase tracking-[.25em] text-[#4f9f4a]">Person profile</p>
            <h1 class="mt-1 text-3xl font-bold text-[#234222]">{{ person.name }}</h1>
            <p class="mt-2 text-sm text-[#60745d]">{{ person.department || 'Department not specified' }}</p>
          </div>
        </div>
      </header>

      <div class="grid gap-4 lg:grid-cols-[1.25fr_2fr]">
        <article class="rounded-[1.7rem] border border-[#d8e7d4] bg-white p-6 shadow-sm">
          <p class="text-xs font-bold uppercase tracking-[.2em] text-[#4f9f4a]">Details</p>
          <dl class="mt-5 space-y-4">
            <div><dt class="text-xs font-bold uppercase tracking-wider text-[#7f9a7a]">Employee ID</dt><dd class="mt-1 font-semibold text-[#234222]">{{ person.employee_id || 'Not recorded' }}</dd></div>
            <div><dt class="text-xs font-bold uppercase tracking-wider text-[#7f9a7a]">Email</dt><dd class="mt-1 font-semibold text-[#234222]">{{ person.email || 'Not recorded' }}</dd></div>
            <div><dt class="text-xs font-bold uppercase tracking-wider text-[#7f9a7a]">Department</dt><dd class="mt-1 font-semibold text-[#234222]">{{ person.department || 'Not specified' }}</dd></div>
          </dl>
        </article>

        <div class="grid gap-4 sm:grid-cols-3">
          <article v-for="card in [
            { label: 'Current assets', value: summary.current_assets, colour: 'text-blue-700' },
            { label: 'Licences', value: summary.licences, colour: 'text-violet-700' },
            { label: 'History events', value: summary.history_events, colour: 'text-[#2f7d32]' },
          ]" :key="card.label" class="rounded-[1.7rem] border border-[#d8e7d4] bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-[#7f9a7a]">{{ card.label }}</p>
            <p class="mt-3 text-4xl font-black" :class="card.colour">{{ card.value }}</p>
          </article>
        </div>
      </div>

      <section class="overflow-hidden rounded-[1.7rem] border border-[#d8e7d4] bg-white shadow-sm">
        <div class="border-b border-[#edf3eb] px-6 py-5"><h2 class="text-xl font-bold text-[#234222]">Current assets</h2><p class="mt-1 text-xs text-[#7f9a7a]">Equipment currently checked out to this person.</p></div>
        <div class="overflow-x-auto"><table class="table">
          <thead><tr><th>Asset</th><th>Category</th><th>Serial number</th><th>Location</th><th>Assigned</th></tr></thead>
          <tbody>
            <tr v-for="asset in currentAssets" :key="asset.id">
              <td><Link class="font-bold text-[#2f7d32]" :href="route('it-assets.show', asset.id)">{{ asset.asset_tag_no }}</Link><p class="text-xs text-[#60745d]">{{ asset.brand_model || asset.description }}</p></td>
              <td>{{ asset.category || '—' }}</td><td>{{ asset.serial_no || '—' }}</td><td>{{ asset.location || '—' }}</td><td>{{ formatDate(asset.assigned_at) }}</td>
            </tr>
            <tr v-if="!currentAssets.length"><td colspan="5" class="py-10 text-center text-[#7f9a7a]">No assets are currently assigned.</td></tr>
          </tbody>
        </table></div>
      </section>

      <section class="overflow-hidden rounded-[1.7rem] border border-[#d8e7d4] bg-white shadow-sm">
        <div class="border-b border-[#edf3eb] px-6 py-5"><h2 class="text-xl font-bold text-[#234222]">Software licences</h2><p class="mt-1 text-xs text-[#7f9a7a]">Licence records currently associated with this person.</p></div>
        <div class="overflow-x-auto"><table class="table">
          <thead><tr><th>Licence ID</th><th>Software</th><th>Type</th><th>Expiry</th><th>Status</th></tr></thead>
          <tbody>
            <tr v-for="licence in licences" :key="licence.id">
              <td><Link class="font-bold text-[#2f7d32]" :href="route('it-licenses.show', licence.id)">{{ licence.license_code }}</Link></td>
              <td><p class="font-semibold text-[#234222]">{{ licence.software_name }}</p><p class="text-xs text-[#60745d]">{{ licence.vendor || 'Vendor not specified' }}</p></td>
              <td>{{ label(licence.license_type) }}</td><td>{{ formatDate(licence.expiry_date) }}</td>
              <td><span class="inline-flex rounded-full border px-3 py-1 text-xs font-bold" :class="statusStyles[licence.status]">{{ label(licence.status) }}</span></td>
            </tr>
            <tr v-if="!licences.length"><td colspan="5" class="py-10 text-center text-[#7f9a7a]">No software licences are associated with this person.</td></tr>
          </tbody>
        </table></div>
      </section>

      <section class="rounded-[1.7rem] border border-[#d8e7d4] bg-white p-6 shadow-sm">
        <h2 class="text-xl font-bold text-[#234222]">History log</h2>
        <p class="mt-1 text-xs text-[#7f9a7a]">Asset checkout and return activity, newest first.</p>
        <div v-if="history.length" class="mt-6 space-y-0">
          <article v-for="(entry, index) in history" :key="`${entry.date}-${entry.event}-${entry.asset_id}-${index}`" class="relative grid gap-3 pb-7 pl-9 sm:grid-cols-[9rem_1fr]">
            <span v-if="index < history.length - 1" class="absolute left-[.45rem] top-3 h-full w-px bg-[#d8e7d4]"></span>
            <span class="absolute left-0 top-1.5 h-4 w-4 rounded-full border-4 border-white shadow-sm" :class="entry.event === 'Asset returned' ? 'bg-slate-400' : 'bg-[#4f9f4a]'"></span>
            <time class="text-sm font-semibold text-[#60745d]">{{ formatDate(entry.date) }}</time>
            <div>
              <p class="font-bold text-[#234222]">{{ entry.event }}</p>
              <p class="mt-1 text-sm text-[#60745d]"><Link class="font-bold text-[#2f7d32]" :href="route('it-assets.show', entry.asset_id)">{{ entry.asset_tag_no }}</Link> — {{ entry.description }}</p>
              <p v-if="entry.remarks" class="mt-1 text-xs text-[#7f9a7a]">{{ entry.remarks }}</p>
            </div>
          </article>
        </div>
        <div v-else class="mt-5 rounded-xl bg-[#f5faf3] px-4 py-10 text-center text-sm text-[#7f9a7a]">No asset history is recorded for this person.</div>
      </section>
    </section>
  </AuthenticatedLayout>
</template>
