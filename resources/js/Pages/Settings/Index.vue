<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    stats: Object,
    latestMovementDate: String,
    canEditSettings: Boolean,
    roleOptions: Array,
    permissionLevels: Array,
    permissionModules: Array,
    rolePresets: Object,
    users: Array,
    branchOptions: Array,
});

const adminGroups = [
    {
        title: 'Master Data',
        description: 'Maintain the shared records that drive stock item registration and reporting.',
        items: [
            { name: 'Categories', href: route('categories.index'), key: 'categories', summary: 'Item grouping and ledger segmentation.' },
            { name: 'Locations', href: route('locations.index'), key: 'locations', summary: 'Inventory locations, racks, zones, and destinations.' },
            { name: 'Stock Items', href: route('assets.index'), key: 'items', summary: 'Inventory catalogue and opening values.' },
        ],
    },
    {
        title: 'Operations',
        description: 'Administrative entry points for transaction control and monthly reporting.',
        items: [
            { name: 'Movements', href: route('asset-movements.index'), key: 'movements', summary: 'Record and review stock movement activity.' },
            { name: 'Stocktakes', href: route('stocktakes.index'), key: 'stocktakes', summary: 'Run physical counts and post variance adjustments by location.' },
            { name: 'Monthly Ledger', href: route('asset-ledger.index'), key: 'movements', summary: 'Review period totals and valuation rollups.' },
            { name: 'COG Control', href: route('cogs.index'), key: 'movements', summary: 'Track consignment note preparation and approvals.' },
            { name: 'Stock Anomalies', href: route('anomalies.index'), key: 'movements', summary: 'Review rule-based stock exceptions that need operator attention.' },
        ],
    },
    {
        title: 'Access',
        description: 'Administrative access references and operator account controls.',
        items: [
            { name: 'Profile', href: route('profile.edit'), key: 'users', summary: 'Update your login profile and account details.' },
            { name: 'Dashboard', href: route('dashboard'), key: 'users', summary: 'Return to the operations summary and activity feed.' },
            { name: 'Assistant', href: route('assistant.index'), key: 'users', summary: 'Ask live inventory questions from stock and movement records.' },
            { name: 'Audit Trail', href: route('audit-trail.index'), key: 'audits', summary: 'Review write history across stock, approvals, and permissions.' },
        ],
    },
];

const userForms = reactive(
    Object.fromEntries(
        props.users.map((user) => [
            user.id,
            {
                role: user.role,
                permissions: { ...user.permissions },
                branch_access: Object.fromEntries(props.branchOptions.map((branch) => [branch.id, user.branch_access?.[branch.id] ?? 'none'])),
                default_branch_id: user.default_branch_id,
                saving: false,
            },
        ]),
    ),
);

const selectedUserId = ref(props.users[0]?.id ?? null);
const permissionSearch = ref('');

const accessSummary = (userId) => {
    const levels = Object.values(userForms[userId].permissions);

    return {
        edit: levels.filter((level) => level === 'edit').length,
        read: levels.filter((level) => level === 'read').length,
        none: levels.filter((level) => level === 'none').length,
    };
};

const selectedUser = computed(() => props.users.find((user) => user.id === selectedUserId.value) ?? null);

const filteredPermissionModules = computed(() => {
    const query = permissionSearch.value.trim().toLowerCase();

    if (!query) {
        return props.permissionModules;
    }

    return props.permissionModules.filter((module) => module.label.toLowerCase().includes(query));
});

const applyRolePreset = (userId) => {
    const form = userForms[userId];
    form.permissions = { ...props.rolePresets[form.role] };
};

const saveAccess = (userId) => {
    const form = userForms[userId];
    form.saving = true;

    router.patch(
        route('settings.users.update', userId),
        {
            role: form.role,
            permissions: form.permissions,
            branch_access: form.branch_access,
            default_branch_id: form.default_branch_id,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                form.saving = false;
            },
        },
    );
};
</script>

<template>
    <Head title="Settings" />

    <AuthenticatedLayout>
        <PageHeader
            title="Settings"
            description="Administrative shortcuts for master data, operational controls, account access, and role permission management."
        />

        <div class="grid gap-6 xl:grid-cols-[1.15fr,0.85fr]">
            <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-5 shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
                <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-[#6f8a6b]">Administration Overview</p>
                        <h2 class="text-xl font-semibold text-[#234222]">System Control Center</h2>
                    </div>
                    <span class="w-fit rounded-full border border-[#b8d7b1] bg-[#eef8ea] px-4 py-1 text-xs font-semibold text-[#3c8a39]">
                        Admin use
                    </span>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
                    <article class="rounded-[1.5rem] border border-[#e1efdc] bg-[#fbfefa] p-5">
                        <p class="text-xs uppercase tracking-[0.2em] text-[#7f9a7a]">Users</p>
                        <p class="mt-2 text-3xl font-bold text-[#234222]">{{ stats.users }}</p>
                    </article>
                    <article class="rounded-[1.5rem] border border-[#e1efdc] bg-[#fbfefa] p-5">
                        <p class="text-xs uppercase tracking-[0.2em] text-[#7f9a7a]">Categories</p>
                        <p class="mt-2 text-3xl font-bold text-[#3c8a39]">{{ stats.categories }}</p>
                    </article>
                    <article class="rounded-[1.5rem] border border-[#e1efdc] bg-[#fbfefa] p-5">
                        <p class="text-xs uppercase tracking-[0.2em] text-[#7f9a7a]">Locations</p>
                        <p class="mt-2 text-3xl font-bold text-[#234222]">{{ stats.locations }}</p>
                    </article>
                    <article class="rounded-[1.5rem] border border-[#e1efdc] bg-[#fbfefa] p-5">
                        <p class="text-xs uppercase tracking-[0.2em] text-[#7f9a7a]">Stock Items</p>
                        <p class="mt-2 text-3xl font-bold text-[#234222]">{{ stats.items }}</p>
                    </article>
                    <article class="rounded-[1.5rem] border border-[#e1efdc] bg-[#fbfefa] p-5">
                        <p class="text-xs uppercase tracking-[0.2em] text-[#7f9a7a]">Movements</p>
                        <p class="mt-2 text-3xl font-bold text-[#4f9f4a]">{{ stats.movements }}</p>
                    </article>
                    <article class="rounded-[1.5rem] border border-[#e1efdc] bg-[#fbfefa] p-5">
                        <p class="text-xs uppercase tracking-[0.2em] text-[#7f9a7a]">Stocktakes</p>
                        <p class="mt-2 text-3xl font-bold text-[#234222]">{{ stats.stocktakes }}</p>
                    </article>
                </div>
            </section>

            <aside class="rounded-[2rem] border border-[#d8e7d4] bg-white p-5 shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
                <div class="relative overflow-hidden rounded-[1.75rem] border border-[#d8e7d4] bg-[radial-gradient(circle_at_top_left,_rgba(111,187,104,0.16),_transparent_30%),linear-gradient(180deg,_#ffffff_0%,_#f7fcf5_52%,_#eef8ea_100%)] p-5 shadow-[0_20px_60px_rgba(79,159,74,0.12)]">
                    <div class="pointer-events-none absolute inset-0 bg-[linear-gradient(135deg,rgba(255,255,255,0.55),transparent_32%,transparent_70%,rgba(111,187,104,0.08))]" />

                    <div class="relative">
                        <div class="mb-3 inline-flex items-center gap-2 rounded-full border border-[#cfe6c8] bg-white/80 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-[#4f6b4b]">
                            <span class="h-2 w-2 rounded-full bg-[#4f9f4a] shadow-[0_0_10px_rgba(79,159,74,0.45)]" />
                            Administrative Purpose
                        </div>
                        <h2 class="text-2xl font-semibold tracking-tight text-[#234222]">Settings Panel</h2>
                        <p class="mt-2 text-sm text-[#5f7b5e]">
                            Use this page as the central entry point for maintenance tasks, audit navigation, system setup references, and access control policy.
                        </p>

                        <div class="mt-5 rounded-[1.35rem] border border-[#d8e7d4] bg-white/85 p-4">
                            <p class="text-[11px] uppercase tracking-[0.22em] text-[#7f9a7a]">Latest Movement</p>
                            <p class="mt-2 text-lg font-semibold text-[#234222]">{{ latestMovementDate ?? 'No movement recorded yet' }}</p>
                            <p class="mt-1 text-sm text-[#6f8a6b]">Quick signal for recent operational activity.</p>
                        </div>

                        <div class="mt-4 rounded-[1.35rem] border border-[#d8e7d4] bg-white/85 p-4">
                            <p class="text-[11px] uppercase tracking-[0.22em] text-[#7f9a7a]">Permission Logic</p>
                            <p class="mt-2 text-sm text-[#4f6b4b]">
                                Read Only can open and review a module. Edit can create, update, or submit records in that module.
                            </p>
                        </div>
                    </div>
                </div>
            </aside>
        </div>

        <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-5 shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
            <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-[#6f8a6b]">Role Permissions</p>
                    <h2 class="text-xl font-semibold text-[#234222]">Permission workspace</h2>
                    <p class="mt-1 text-sm text-[#6f8a6b]">Pick one user, then adjust module access in a single compact matrix.</p>
                </div>
                <span class="rounded-full border border-[#b8d7b1] bg-[#eef8ea] px-4 py-1 text-xs font-semibold text-[#3c8a39]">
                    {{ users.length }} users
                </span>
            </div>

            <div class="grid gap-4 xl:grid-cols-[280px,minmax(0,1fr)]">
                <aside class="rounded-[1.6rem] border border-[#d8e7d4] bg-[#fbfefa] p-3">
                    <div class="mb-3 rounded-[1.2rem] border border-[#d8e7d4] bg-white px-4 py-3">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-[#7f9a7a]">Account List</p>
                        <p class="mt-1 text-sm text-[#6f8a6b]">Select a user to edit role permissions.</p>
                    </div>

                    <div class="space-y-2">
                        <button
                            v-for="user in users"
                            :key="`nav-${user.id}`"
                            type="button"
                            class="w-full rounded-[1.15rem] border px-4 py-3 text-left transition"
                            :class="selectedUserId === user.id
                                ? 'border-[#86c87b] bg-[linear-gradient(135deg,#eef8ea_0%,#ffffff_100%)] shadow-[0_14px_28px_rgba(79,159,74,0.10)]'
                                : 'border-[#d8e7d4] bg-white hover:border-[#b8e0ae] hover:bg-[#f7fcf5]'"
                            @click="selectedUserId = user.id"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-[#234222]">{{ user.name }}</p>
                                    <p class="mt-1 truncate text-xs text-[#6f8a6b]">{{ user.username }}</p>
                                </div>
                                <span class="rounded-full border border-[#d8e7d4] bg-white px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-[#5f7b5e]">
                                    {{ userForms[user.id].role }}
                                </span>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-1.5 text-[10px] font-semibold">
                                <span class="rounded-full border border-[#b8e0ae] bg-[#eef8ea] px-2 py-1 text-[#2f6f2d]">{{ accessSummary(user.id).edit }} edit</span>
                                <span class="rounded-full border border-[#d8e7d4] bg-[#f8fbf7] px-2 py-1 text-[#5f7b5e]">{{ accessSummary(user.id).read }} read</span>
                                <span class="rounded-full border border-[#e8ede6] bg-white px-2 py-1 text-[#7f9a7a]">{{ accessSummary(user.id).none }} none</span>
                            </div>
                        </button>
                    </div>
                </aside>

                <div v-if="selectedUser" class="rounded-[1.6rem] border border-[#d8e7d4] bg-[#fbfefa]">
                    <div class="border-b border-[#d8e7d4] bg-white px-4 py-4 sm:px-5">
                        <div class="flex flex-col gap-4 2xl:flex-row 2xl:items-start 2xl:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-lg font-semibold text-[#234222]">{{ selectedUser.name }}</h3>
                                    <span class="rounded-full border border-[#d8e7d4] bg-[#f8fbf7] px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-[#6f8a6b]">
                                        {{ selectedUser.username }}
                                    </span>
                                </div>
                                <p class="mt-1 truncate text-sm text-[#6f8a6b]">{{ selectedUser.email }}</p>
                            </div>

                            <div class="flex flex-wrap items-center gap-3">
                                <div class="w-full md:w-[220px]">
                                    <label class="mb-2 block text-[10px] font-semibold uppercase tracking-[0.22em] text-[#7f9a7a]">Role</label>
                                    <select
                                        v-model="userForms[selectedUser.id].role"
                                        class="select select-sm w-full border-[#cfe6c8] bg-white text-[#234222]"
                                        :disabled="!canEditSettings || userForms[selectedUser.id].saving"
                                        @change="applyRolePreset(selectedUser.id)"
                                    >
                                        <option v-for="role in roleOptions" :key="role.value" :value="role.value">{{ role.label }}</option>
                                    </select>
                                </div>

                                <button
                                    type="button"
                                    class="btn btn-sm w-full md:mt-6 md:w-auto border-none bg-[linear-gradient(135deg,#6fbb68_0%,#4f9f4a_100%)] text-white shadow-[0_16px_36px_rgba(79,159,74,0.24)] hover:opacity-95"
                                    :disabled="!canEditSettings || userForms[selectedUser.id].saving"
                                    @click="saveAccess(selectedUser.id)"
                                >
                                    {{ userForms[selectedUser.id].saving ? 'Saving...' : 'Save Access' }}
                                </button>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-3 xl:grid-cols-[1fr,auto] xl:items-end">
                            <div class="xl:col-span-2 rounded-xl border border-[#d8e7d4] bg-[#f7fbf5] p-4">
                                <p class="mb-3 text-[10px] font-semibold uppercase tracking-[0.22em] text-[#7f9a7a]">Branch access</p>
                                <div class="grid gap-3 md:grid-cols-2">
                                    <div v-for="branch in branchOptions" :key="branch.id" class="flex items-center gap-3 rounded-lg bg-white p-3">
                                        <div class="min-w-0 flex-1"><p class="font-semibold text-[#234222]">{{ branch.code }}</p><p class="text-xs text-[#7f9a7a]">{{ branch.name }}</p></div>
                                        <select v-model="userForms[selectedUser.id].branch_access[branch.id]" class="select select-sm select-bordered"><option value="none">None</option><option value="read">Read</option><option value="edit">Edit</option><option value="manage">Manage</option></select>
                                        <label class="text-xs"><input v-model="userForms[selectedUser.id].default_branch_id" type="radio" :value="branch.id" :disabled="userForms[selectedUser.id].branch_access[branch.id] === 'none'" /> Default</label>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="mb-2 block text-[10px] font-semibold uppercase tracking-[0.22em] text-[#7f9a7a]">Search Permissions</label>
                                <input
                                    v-model="permissionSearch"
                                    type="text"
                                    placeholder="Search modules..."
                                    class="input input-sm w-full border-[#cfe6c8] bg-white text-[#234222] placeholder:text-[#7f9a7a]"
                                />
                            </div>

                            <div class="flex flex-wrap gap-2 text-xs font-semibold">
                                <span class="rounded-full border border-[#b8e0ae] bg-[#eef8ea] px-3 py-1 text-[#2f6f2d]">{{ accessSummary(selectedUser.id).edit }} edit</span>
                                <span class="rounded-full border border-[#d8e7d4] bg-[#f8fbf7] px-3 py-1 text-[#5f7b5e]">{{ accessSummary(selectedUser.id).read }} read</span>
                                <span class="rounded-full border border-[#e8ede6] bg-white px-3 py-1 text-[#7f9a7a]">{{ accessSummary(selectedUser.id).none }} none</span>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-[#f7fbf5] text-[#5f7b5e]">
                                <tr class="border-b border-[#d8e7d4]">
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.22em] sm:px-5">Module</th>
                                    <th class="px-3 py-3 text-center text-[11px] font-semibold uppercase tracking-[0.22em]">No Access</th>
                                    <th class="px-3 py-3 text-center text-[11px] font-semibold uppercase tracking-[0.22em]">Read</th>
                                    <th class="px-3 py-3 text-center text-[11px] font-semibold uppercase tracking-[0.22em]">Edit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="module in filteredPermissionModules"
                                    :key="`${selectedUser.id}-${module.value}`"
                                    class="border-b border-[#edf3eb] last:border-b-0"
                                >
                                    <td class="px-4 py-3 sm:px-5">
                                        <p class="font-semibold text-[#234222]">{{ module.label }}</p>
                                        <p class="mt-1 text-xs text-[#6f8a6b]">{{ module.value }}</p>
                                    </td>
                                    <td
                                        v-for="level in permissionLevels"
                                        :key="`${selectedUser.id}-${module.value}-${level.value}`"
                                        class="px-3 py-3 text-center"
                                    >
                                        <input
                                            :id="`${selectedUser.id}-${module.value}-${level.value}`"
                                            v-model="userForms[selectedUser.id].permissions[module.value]"
                                            type="radio"
                                            :name="`${selectedUser.id}-${module.value}`"
                                            :value="level.value"
                                            class="radio radio-sm border-[#b8d7b1] text-[#4f9f4a]"
                                            :disabled="!canEditSettings || userForms[selectedUser.id].saving"
                                        />
                                    </td>
                                </tr>
                                <tr v-if="filteredPermissionModules.length === 0">
                                    <td colspan="4" class="px-4 py-8 text-center text-sm text-[#6f8a6b] sm:px-5">
                                        No permission modules match this search.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="border-t border-[#d8e7d4] bg-white px-4 py-3 text-xs text-[#6f8a6b] sm:px-5">
                        Settings read allows viewing this screen. Settings edit allows updating roles and permissions.
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-6 2xl:grid-cols-3">
            <section
                v-for="group in adminGroups"
                :key="group.title"
                class="rounded-[2rem] border border-[#d8e7d4] bg-white p-5 shadow-[0_18px_45px_rgba(79,159,74,0.10)]"
            >
                <div class="mb-5">
                    <p class="text-sm text-[#6f8a6b]">{{ group.title }}</p>
                    <h2 class="text-xl font-semibold text-[#234222]">{{ group.description }}</h2>
                </div>

                <div class="space-y-4">
                    <article
                        v-for="item in group.items"
                        :key="item.name"
                        class="rounded-[1.5rem] border border-[#d8e7d4] bg-[#fbfefa] p-4 transition hover:border-[#86c87b] hover:shadow-[0_14px_28px_rgba(79,159,74,0.10)]"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-base font-semibold text-[#234222]">{{ item.name }}</p>
                                <p class="mt-1 text-sm text-[#6f8a6b]">{{ item.summary }}</p>
                            </div>
                            <span class="rounded-full border border-[#cfe6c8] bg-[#eef8ea] px-3 py-1 text-xs font-semibold text-[#3c8a39]">
                                {{ stats[item.key] }}
                            </span>
                        </div>

                        <div class="mt-4">
                            <Link
                                :href="item.href"
                                class="btn border-[#d8e7d4] bg-white text-[#2f6f2d] hover:bg-[#eef8ea]"
                            >
                                Open
                            </Link>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
