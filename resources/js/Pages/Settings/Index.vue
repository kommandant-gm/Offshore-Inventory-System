<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
    stats: Object,
    latestMovementDate: String,
    canEditSettings: Boolean,
    roleOptions: Array,
    permissionLevels: Array,
    permissionModules: Array,
    rolePresets: Object,
    users: Array,
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
                saving: false,
            },
        ]),
    ),
);

const accessSummary = (userId) => {
    const levels = Object.values(userForms[userId].permissions);

    return {
        edit: levels.filter((level) => level === 'edit').length,
        read: levels.filter((level) => level === 'read').length,
        none: levels.filter((level) => level === 'none').length,
    };
};

const permissionModuleChunks = computed(() => {
    const chunkSize = 5;
    const chunks = [];

    for (let index = 0; index < props.permissionModules.length; index += chunkSize) {
        chunks.push(props.permissionModules.slice(index, index + chunkSize));
    }

    return chunks;
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
                <div class="mb-5 flex items-center justify-between">
                    <div>
                        <p class="text-sm text-[#6f8a6b]">Administration Overview</p>
                        <h2 class="text-xl font-semibold text-[#234222]">System Control Center</h2>
                    </div>
                    <span class="rounded-full border border-[#b8d7b1] bg-[#eef8ea] px-4 py-1 text-xs font-semibold text-[#3c8a39]">
                        Admin use
                    </span>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
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
                    <h2 class="text-xl font-semibold text-[#234222]">Administrative access matrix</h2>
                    <p class="mt-1 text-sm text-[#6f8a6b]">Compact role controls with direct module-level overrides.</p>
                </div>
                <span class="rounded-full border border-[#b8d7b1] bg-[#eef8ea] px-4 py-1 text-xs font-semibold text-[#3c8a39]">
                    {{ users.length }} users
                </span>
            </div>

            <div class="space-y-4">
                <article
                    v-for="user in users"
                    :key="user.id"
                    class="rounded-[1.6rem] border border-[#d8e7d4] bg-[#fbfefa] p-4"
                >
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-lg font-semibold text-[#234222]">{{ user.name }}</p>
                                <span class="rounded-full border border-[#d8e7d4] bg-white px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-[#6f8a6b]">
                                    {{ user.username }}
                                </span>
                            </div>
                            <p class="mt-1 truncate text-sm text-[#6f8a6b]">{{ user.email }}</p>
                        </div>

                        <div class="grid gap-3 md:grid-cols-[minmax(0,220px)_1fr_auto] xl:min-w-[760px]">
                            <div class="rounded-[1.15rem] border border-[#d8e7d4] bg-white p-3">
                                <label class="mb-2 block text-[10px] font-semibold uppercase tracking-[0.22em] text-[#7f9a7a]">Role</label>
                                <select
                                    v-model="userForms[user.id].role"
                                    class="select select-sm w-full border-[#cfe6c8] bg-white text-[#234222]"
                                    :disabled="!canEditSettings || userForms[user.id].saving"
                                    @change="applyRolePreset(user.id)"
                                >
                                    <option v-for="role in roleOptions" :key="role.value" :value="role.value">{{ role.label }}</option>
                                </select>
                            </div>

                            <div class="rounded-[1.15rem] border border-[#d8e7d4] bg-white p-3">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.22em] text-[#7f9a7a]">Access Summary</p>
                                <div class="mt-2 flex flex-wrap gap-2 text-xs font-semibold">
                                    <span class="rounded-full border border-[#b8e0ae] bg-[#eef8ea] px-3 py-1 text-[#2f6f2d]">{{ accessSummary(user.id).edit }} edit</span>
                                    <span class="rounded-full border border-[#d8e7d4] bg-[#f8fbf7] px-3 py-1 text-[#5f7b5e]">{{ accessSummary(user.id).read }} read</span>
                                    <span class="rounded-full border border-[#e8ede6] bg-white px-3 py-1 text-[#7f9a7a]">{{ accessSummary(user.id).none }} none</span>
                                </div>
                                <p class="mt-2 text-xs text-[#6f8a6b]">Choose a preset, then fine-tune individual modules below.</p>
                            </div>

                            <button
                                type="button"
                                class="btn btn-sm self-start border-none bg-[linear-gradient(135deg,#6fbb68_0%,#4f9f4a_100%)] text-white shadow-[0_16px_36px_rgba(79,159,74,0.24)] hover:opacity-95"
                                :disabled="!canEditSettings || userForms[user.id].saving"
                                @click="saveAccess(user.id)"
                            >
                                {{ userForms[user.id].saving ? 'Saving...' : 'Save' }}
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 overflow-hidden rounded-[1.25rem] border border-[#d8e7d4] bg-white">
                        <div class="hidden bg-[#f7fbf5] px-4 py-2 text-[10px] font-semibold uppercase tracking-[0.22em] text-[#7f9a7a] xl:grid xl:grid-cols-[160px_repeat(5,minmax(0,1fr))] xl:gap-3">
                            <span>Module Row</span>
                            <span v-for="module in permissionModuleChunks[0]" :key="`header-${module.value}`">{{ module.label }}</span>
                        </div>

                        <div
                            v-for="(moduleRow, rowIndex) in permissionModuleChunks"
                            :key="`row-${rowIndex}`"
                            class="border-t border-[#edf3eb] first:border-t-0"
                        >
                            <div class="hidden items-center gap-3 px-4 py-3 xl:grid xl:grid-cols-[160px_repeat(5,minmax(0,1fr))]">
                                <span class="text-xs font-semibold uppercase tracking-[0.18em] text-[#7f9a7a]">Row {{ rowIndex + 1 }}</span>
                                <div
                                    v-for="module in moduleRow"
                                    :key="`${user.id}-${module.value}`"
                                    class="min-w-0"
                                >
                                    <label class="mb-1 block truncate text-[10px] font-semibold uppercase tracking-[0.18em] text-[#7f9a7a]">{{ module.label }}</label>
                                    <select
                                        v-model="userForms[user.id].permissions[module.value]"
                                        class="select select-sm w-full border-[#cfe6c8] bg-white text-[#234222]"
                                        :disabled="!canEditSettings || userForms[user.id].saving"
                                    >
                                        <option
                                            v-for="level in permissionLevels"
                                            :key="`${module.value}-${level.value}`"
                                            :value="level.value"
                                        >
                                            {{ level.label }}
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid gap-3 p-3 sm:grid-cols-2 lg:grid-cols-3 xl:hidden">
                                <div
                                    v-for="module in moduleRow"
                                    :key="`${user.id}-${module.value}-mobile`"
                                    class="rounded-xl border border-[#edf3eb] bg-[#fbfefa] p-3"
                                >
                                    <label class="mb-2 block text-[10px] font-semibold uppercase tracking-[0.18em] text-[#7f9a7a]">{{ module.label }}</label>
                                    <select
                                        v-model="userForms[user.id].permissions[module.value]"
                                        class="select select-sm w-full border-[#cfe6c8] bg-white text-[#234222]"
                                        :disabled="!canEditSettings || userForms[user.id].saving"
                                    >
                                        <option
                                            v-for="level in permissionLevels"
                                            :key="`${module.value}-${level.value}-mobile`"
                                            :value="level.value"
                                        >
                                            {{ level.label }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                        <p class="text-xs text-[#6f8a6b]">
                            Settings read allows viewing this screen. Settings edit allows updating roles and permissions.
                        </p>
                    </div>
                </article>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-3">
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
