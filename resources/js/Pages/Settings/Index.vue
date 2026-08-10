<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CustomSelect from '@/Components/CustomSelect.vue';
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
    issueSummary: Object,
    recentIssues: Array,
    supervisorEmails: Array,
    emailActivity: Object,
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

const directoryDepartment = 'IT & DIGITAL';
const selectedUserId = ref(props.users.find((user) => (user.department || '').trim().toUpperCase() === directoryDepartment)?.id ?? null);
const permissionSearch = ref('');
const userSearch = ref('');
const departmentFilter = ref('');
const roleFilter = ref('');
const directoryView = ref('cards');
const sendingTestEmail = ref(false);
const checkoutTestEmail = ref('');
const sendingCheckoutTest = ref(false);
const checkinTestEmail = ref('');
const sendingCheckinTest = ref(false);
const importingLdapUsers = ref(false);
const issueLogOpen = ref(false);

const accessSummary = (userId) => {
    const levels = Object.values(userForms[userId].permissions);

    return {
        edit: levels.filter((level) => level === 'edit').length,
        read: levels.filter((level) => level === 'read').length,
        none: levels.filter((level) => level === 'none').length,
    };
};

const selectedUser = computed(() => props.users.find((user) => user.id === selectedUserId.value) ?? null);
const departments = computed(() => [...new Set(props.users.map((user) => user.department || 'N/A'))].sort((a, b) => a.localeCompare(b)));
const departmentCounts = computed(() => props.users.reduce((counts, user) => {
    const department = user.department || 'N/A';
    counts[department] = (counts[department] || 0) + 1;
    return counts;
}, {}));
const roleOptionsForFilter = computed(() => props.roleOptions.filter((role) => role.value !== 'viewer'));
const roleCounts = computed(() => props.users.reduce((counts, user) => {
    const role = ['viewer', null, ''].includes(user.role) ? 'none' : user.role;
    counts[role] = (counts[role] || 0) + 1;
    return counts;
}, {}));
const accessLabel = (user) => ({
    admin: 'ADMIN',
    it: 'IT & DIGITAL',
    miri: 'MIRI',
    supervisor: 'SUPERVISOR',
    technician: 'TECHNICIAN',
    none: 'NO ACCESS',
    viewer: 'NO ACCESS',
}[userForms[user.id]?.role] || 'NO ACCESS');
const visibleUsers = computed(() => {
    const query = userSearch.value.trim().toLowerCase();
    return props.users.filter((user) => {
        const matchesDepartment = !departmentFilter.value || (user.department || 'N/A') === departmentFilter.value;
        const normalizedRole = ['viewer', null, ''].includes(user.role) ? 'none' : user.role;
        const matchesRole = !roleFilter.value || normalizedRole === roleFilter.value;
        const matchesSearch = !query || [user.name, user.username, user.email, user.department, user.job_title].filter(Boolean).some((value) => value.toLowerCase().includes(query));
        return matchesDepartment && matchesRole && matchesSearch;
    });
});
const initials = (name) => (name || '?').split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase();

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
        },
        {
            preserveScroll: true,
            onFinish: () => {
                form.saving = false;
            },
        },
    );
};
const roleDescription = (role) => ({
    miri: 'Miri inventory access only.',
    it: 'Full access across all active branches and modules.',
    supervisor: 'Full access and receives supervisor workflow notifications.',
    technician: 'IT asset access and receives check-in acknowledgment requests.',
    admin: 'Full system administration access.',
    none: 'No system access.',
    viewer: 'No system access.',
}[role] || 'Role-based access.');

const sendSupervisorTestEmail = () => {
    sendingTestEmail.value = true;
    router.post(route('settings.test-supervisor-email'), {}, { preserveScroll: true, onFinish: () => { sendingTestEmail.value = false; } });
};
const sendCheckoutTestEmail = () => {
    sendingCheckoutTest.value = true;
    router.post(route('settings.test-asset-checkout-email'), { email: checkoutTestEmail.value }, { preserveScroll: true, onFinish: () => { sendingCheckoutTest.value = false; } });
};
const sendCheckinTestEmail = () => {
    sendingCheckinTest.value = true;
    router.post(route('settings.test-asset-checkin-email'), { email: checkinTestEmail.value }, { preserveScroll: true, onFinish: () => { sendingCheckinTest.value = false; } });
};
const importLdapUsers = () => {
    importingLdapUsers.value = true;
    router.post(route('settings.import-ldap-users'), {}, { preserveScroll: true, onFinish: () => { importingLdapUsers.value = false; } });
};
</script>

<template>
    <Head title="Settings" />

    <AuthenticatedLayout>
        <PageHeader
            title="Settings"
            description="Administrative shortcuts for master data, operational controls, account access, and role permission management."
        />

        <section class="overflow-hidden rounded-[2rem] border border-[#d8e7d4] bg-white shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
            <div class="flex flex-col gap-3 border-b border-[#edf3eb] bg-[#f8fafc] px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                <button type="button" class="text-left" @click="issueLogOpen = !issueLogOpen"><p class="font-semibold text-[#172033]">Issue Activity Log <span class="ml-2 text-xs text-[#718096]">{{ issueLogOpen ? '▲' : '▼' }}</span></p><p class="mt-1 text-sm text-[#65748b]">Persistent Laravel errors, warnings, and stack traces stored in the database.</p></button>
                <Link :href="route('settings.issue-logs.index')" class="w-fit rounded-xl bg-[#111a2e] px-5 py-3 text-sm font-bold text-white">View Full Log</Link>
            </div>
            <div v-if="issueLogOpen" class="p-6">
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="rounded-2xl border border-[#dce3ed] p-5"><p class="text-xs font-bold uppercase tracking-[.2em] text-[#8290a8]">Total</p><p class="mt-3 text-2xl font-bold text-[#111a2e]">{{issueSummary.total}}</p></div>
                    <Link :href="route('settings.issue-logs.index', { level: 'error' })" class="rounded-2xl border border-[#ffc6cc] bg-[#fff8f8] p-5 transition hover:border-[#d61f3c] hover:shadow-md"><p class="text-xs font-bold uppercase tracking-[.2em] text-[#d61f3c]">Errors</p><p class="mt-3 text-2xl font-bold text-[#a70f29]">{{issueSummary.errors}}</p><p class="mt-1 text-xs font-semibold text-[#a70f29]">Open errors →</p></Link>
                    <div class="rounded-2xl border border-[#f7d56b] bg-[#fffdf5] p-5"><p class="text-xs font-bold uppercase tracking-[.2em] text-[#b45b00]">Warnings</p><p class="mt-3 text-2xl font-bold text-[#914400]">{{issueSummary.warnings}}</p></div>
                </div>
                <div v-if="recentIssues.length" class="mt-5 space-y-3"><article v-for="issue in recentIssues" :key="issue.id" class="rounded-2xl border border-[#dce3ed] p-4"><div class="flex flex-wrap items-center gap-3"><span class="rounded-full px-3 py-1 text-[11px] font-bold uppercase" :class="issue.level==='error'?'bg-[#ffe4e8] text-[#c41635]':'bg-[#fff1bd] text-[#a65300]'">{{issue.level}}</span><strong class="text-xs text-[#172033]">{{issue.created_at}}</strong></div><p class="mt-3 break-words text-sm text-[#31415b]">{{issue.message}}</p><p v-if="issue.location" class="mt-2 break-all text-xs text-[#718096]">{{issue.location}}</p></article></div>
                <div v-else class="mt-5 rounded-2xl border border-dashed border-[#dce3ed] px-5 py-10 text-center text-sm text-[#718096]">No application issues have been recorded.</div>
            </div>
        </section>

        <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-6 shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div><p class="text-sm font-semibold text-[#234222]">LDAP / Active Directory personnel</p><p class="mt-1 text-sm text-[#65748b]">Import all directory users, including their name, username, email, and department, into the Personnel directory.</p></div>
                <button type="button" class="btn shrink-0 bg-[#194568] text-white" :disabled="importingLdapUsers" @click="importLdapUsers">{{ importingLdapUsers ? 'Importing...' : 'Import users from LDAP' }}</button>
            </div>
            <div v-if="$page.props.flash.ldap_import_success" class="mt-4 rounded-xl border border-[#b8e0ae] bg-[#eef8ea] px-4 py-3 text-sm font-semibold text-[#2f6f2d]">✓ {{ $page.props.flash.ldap_import_success }}</div>
            <div v-if="$page.props.flash.ldap_import_error" class="mt-4 rounded-xl border border-[#ffc6cc] bg-[#fff8f8] px-4 py-3 text-sm font-semibold text-[#a70f29]">✕ {{ $page.props.flash.ldap_import_error }}</div>
        </section>

        <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-6 shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div><p class="text-sm font-semibold text-[#234222]">Supervisor email notifications</p><p class="mt-1 text-sm text-[#65748b]">Send a test email using the configured mail server and supervisor recipients.</p><p class="mt-2 text-xs text-[#7f9a7a]">{{ supervisorEmails.join(', ') }}</p></div>
                <button type="button" class="btn shrink-0 bg-[#4f9f4a] text-white" :disabled="sendingTestEmail" @click="sendSupervisorTestEmail">{{ sendingTestEmail ? 'Sending...' : 'Send test email' }}</button>
            </div>
            <div v-if="$page.props.flash.success" class="mt-4 rounded-xl border border-[#b8e0ae] bg-[#eef8ea] px-4 py-3 text-sm font-semibold text-[#2f6f2d]">✓ {{ $page.props.flash.success }}</div>
            <div v-if="$page.props.flash.error" class="mt-4 rounded-xl border border-[#ffc6cc] bg-[#fff8f8] px-4 py-3 text-sm font-semibold text-[#a70f29]">✕ {{ $page.props.flash.error }}</div>
        </section>

        <section class="space-y-4 rounded-[2rem] border border-[#d8e7d4] bg-transparent shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
            <div class="rounded-[2rem] border border-[#d8e7d4] bg-white px-6 py-5">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div><p class="text-sm font-semibold text-[#234222]">Digital asset checkout test</p><p class="mt-1 text-sm text-[#65748b]">Send a sample checkout form using the first IT asset. This does not change any asset records.</p></div>
                    <div class="flex w-full flex-wrap gap-2 sm:w-auto"><input v-model.trim="checkoutTestEmail" type="email" placeholder="your@email.com" class="input input-bordered w-full bg-[#f8fbf7] sm:w-60" /><button type="button" class="btn shrink-0 bg-[#4f9f4a] text-white" :disabled="sendingCheckoutTest || !checkoutTestEmail" @click="sendCheckoutTestEmail">{{ sendingCheckoutTest ? 'Sending...' : 'Send checkout test' }}</button><a :href="route('settings.preview-asset-checkout-pdf')" target="_blank" rel="noopener" class="btn shrink-0 border border-[#cfe6c8] bg-white text-[#2f7d32]">Preview checkout PDF</a></div>
                </div>
                <div v-if="$page.props.flash.checkout_success" class="mt-4 rounded-xl border border-[#b8e0ae] bg-[#eef8ea] px-4 py-3 text-sm font-semibold text-[#2f6f2d]">✓ {{ $page.props.flash.checkout_success }}</div>
                <div v-if="$page.props.flash.checkout_error" class="mt-4 rounded-xl border border-[#ffc6cc] bg-[#fff8f8] px-4 py-3 text-sm font-semibold text-[#a70f29]">✕ {{ $page.props.flash.checkout_error }}</div>
            </div>

            <div class="rounded-[2rem] border border-[#d8e7d4] bg-white px-6 py-5">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div><p class="text-sm font-semibold text-[#234222]">Digital asset check-in test</p><p class="mt-1 text-sm text-[#65748b]">Send a sample IT Team receipt acknowledgment form. This does not change any asset records.</p></div>
                    <div class="flex w-full flex-wrap gap-2 sm:w-auto"><input v-model.trim="checkinTestEmail" type="email" placeholder="technician@email.com" class="input input-bordered w-full bg-[#f8fbf7] sm:w-60" /><button type="button" class="btn shrink-0 bg-[#4f9f4a] text-white" :disabled="sendingCheckinTest || !checkinTestEmail" @click="sendCheckinTestEmail">{{ sendingCheckinTest ? 'Sending...' : 'Send check-in test' }}</button><a :href="route('settings.preview-asset-checkin-pdf')" target="_blank" rel="noopener" class="btn shrink-0 border border-[#cfe6c8] bg-white text-[#2f7d32]">Preview check-in PDF</a></div>
                </div>
                <div v-if="$page.props.flash.checkin_success" class="mt-4 rounded-xl border border-[#b8e0ae] bg-[#eef8ea] px-4 py-3 text-sm font-semibold text-[#2f6f2d]">✓ {{ $page.props.flash.checkin_success }}</div>
                <div v-if="$page.props.flash.checkin_error" class="mt-4 rounded-xl border border-[#ffc6cc] bg-[#fff8f8] px-4 py-3 text-sm font-semibold text-[#a70f29]">✕ {{ $page.props.flash.checkin_error }}</div>
            </div>
        </section>

        <section class="overflow-hidden rounded-[2rem] border border-[#d8e7d4] bg-white shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
            <div class="flex flex-col gap-3 border-b border-[#edf3eb] bg-[#f8fafc] px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                <div><p class="font-semibold text-[#172033]">Email Activity Log</p><p class="mt-1 text-sm text-[#65748b]">System email history for supervisor notifications and test emails.</p></div><Link :href="emailActivity.full_url" class="w-fit rounded-xl bg-[#111a2e] px-5 py-3 text-sm font-bold text-white">View Full Log</Link>
            </div>
            <div class="p-6">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-2xl border border-[#dce3ed] p-5"><p class="text-xs font-bold uppercase tracking-[.2em] text-[#8290a8]">Total</p><p class="mt-3 text-2xl font-bold text-[#111a2e]">{{ emailActivity.total }}</p></div>
                    <div class="rounded-2xl border border-[#b8e0ae] bg-[#f2fff8] p-5"><p class="text-xs font-bold uppercase tracking-[.2em] text-[#087f5b]">Sent</p><p class="mt-3 text-2xl font-bold text-[#087f5b]">{{ emailActivity.sent }}</p></div>
                    <div class="rounded-2xl border border-[#f7d56b] bg-[#fffdf5] p-5"><p class="text-xs font-bold uppercase tracking-[.2em] text-[#b45b00]">Pending</p><p class="mt-3 text-2xl font-bold text-[#914400]">{{ emailActivity.pending }}</p></div>
                    <div class="rounded-2xl border border-[#ffc6cc] bg-[#fff8f8] p-5"><p class="text-xs font-bold uppercase tracking-[.2em] text-[#d61f3c]">Failed</p><p class="mt-3 text-2xl font-bold text-[#a70f29]">{{ emailActivity.failed }}</p></div>
                </div>
                <div v-if="emailActivity.recent.length" class="mt-6 overflow-x-auto"><table class="table"><thead><tr><th>Time</th><th>Recipient</th><th>Subject</th><th>Type</th><th>Status</th><th>Issue</th></tr></thead><tbody><tr v-for="entry in emailActivity.recent" :key="entry.id" class="cursor-pointer hover:bg-[#f7fbf5]" @click="router.visit(entry.url)"><td class="whitespace-nowrap text-xs">{{ entry.time }}</td><td>{{ entry.recipient }}</td><td>{{ entry.subject }}</td><td class="text-xs text-[#65748b]">{{ entry.type }}</td><td><span class="rounded-full px-3 py-1 text-xs font-bold capitalize" :class="entry.status === 'sent' ? 'bg-[#d1fae5] text-[#047857]' : entry.status === 'failed' ? 'bg-[#ffe4e8] text-[#a70f29]' : 'bg-[#fff1bd] text-[#914400]'">{{ entry.status }}</span></td><td class="max-w-md whitespace-normal text-xs" :class="entry.status === 'failed' ? 'text-[#a70f29]' : 'text-[#718096]'">{{ entry.error || (entry.status === 'failed' ? 'No error details recorded.' : '—') }}</td></tr></tbody></table></div>
                <div v-else class="mt-6 rounded-2xl border border-dashed border-[#dce3ed] px-5 py-10 text-center text-sm text-[#718096]">No email activity has been recorded yet.</div>
            </div>
        </section>

        <div v-if="false" class="grid gap-6 xl:grid-cols-[1.15fr,0.85fr]">
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
            <div class="mb-5 rounded-[1.5rem] border border-[#edf3eb] bg-white p-1">
                <div class="flex flex-col gap-4 px-3 py-3 lg:flex-row lg:items-center lg:justify-between">
                    <div><p class="text-xl font-bold text-[#172033]">Employee Directory</p><p class="mt-1 text-sm text-[#65748b]">{{ visibleUsers.length }} of {{ users.length }} active employee(s)</p></div>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center"><div class="flex rounded-xl border border-[#dce3ed] bg-[#f8fafc] p-1"><button type="button" class="rounded-lg px-3 py-2 text-xs font-bold" :class="directoryView === 'cards' ? 'bg-white text-[#9b0000] shadow-sm' : 'text-[#718096]'" @click="directoryView = 'cards'">▦ CARDS</button><button type="button" class="rounded-lg px-3 py-2 text-xs font-bold" :class="directoryView === 'list' ? 'bg-white text-[#9b0000] shadow-sm' : 'text-[#718096]'" @click="directoryView = 'list'">☷ LIST</button></div><button type="button" class="rounded-xl bg-[#9b0000] px-4 py-3 text-xs font-bold text-white" :disabled="importingLdapUsers" @click="importLdapUsers">↻ {{ importingLdapUsers ? 'REFRESHING...' : 'REFRESH FROM AD' }}</button><input v-model.trim="userSearch" type="search" placeholder="Search employees..." class="input input-bordered w-full sm:w-56" /></div>
                </div>
                <div class="rounded-[1.25rem] border border-[#dce3ed] bg-[#fbfcfd] p-3 space-y-3"><div><p class="mb-3 text-xs font-bold uppercase tracking-wider text-[#9b0000]">▥ &nbsp; FILTER BY DEPARTMENT</p><div class="flex flex-wrap gap-2"><button type="button" class="rounded-xl border px-4 py-2 text-xs font-bold" :class="!departmentFilter ? 'border-[#9b0000] bg-[#9b0000] text-white shadow-sm' : 'border-[#dce3ed] bg-white text-[#31415b]'" @click="departmentFilter = ''">All Departments <span class="ml-1 rounded-full bg-white/20 px-2 py-0.5">{{ users.length }}</span></button><button v-for="department in departments" :key="department" type="button" class="rounded-xl border border-[#dce3ed] bg-white px-4 py-2 text-xs font-bold text-[#31415b]" :class="departmentFilter === department ? 'border-[#9b0000] bg-[#fff4f4] text-[#9b0000]' : ''" @click="departmentFilter = department">{{ department }} <span class="ml-1 rounded-full bg-[#f1f3f5] px-2 py-0.5">{{ departmentCounts[department] }}</span></button></div></div><div><p class="mb-3 text-xs font-bold uppercase tracking-wider text-[#9b0000]">▥ &nbsp; FILTER BY ROLE</p><div class="flex flex-wrap gap-2"><button type="button" class="rounded-xl border px-4 py-2 text-xs font-bold" :class="!roleFilter ? 'border-[#9b0000] bg-[#9b0000] text-white shadow-sm' : 'border-[#dce3ed] bg-white text-[#31415b]'" @click="roleFilter = ''">All Roles <span class="ml-1 rounded-full bg-white/20 px-2 py-0.5">{{ users.length }}</span></button><button v-for="role in roleOptionsForFilter" :key="role.value" type="button" class="rounded-xl border border-[#dce3ed] bg-white px-4 py-2 text-xs font-bold text-[#31415b]" :class="roleFilter === role.value ? 'border-[#9b0000] bg-[#fff4f4] text-[#9b0000]' : ''" @click="roleFilter = role.value">{{ role.label }} <span class="ml-1 rounded-full bg-[#f1f3f5] px-2 py-0.5">{{ roleCounts[role.value] || 0 }}</span></button></div></div></div>
            </div>

            <div class="space-y-5">
                <aside class="rounded-[1.6rem] border border-[#d8e7d4] bg-[#fbfefa] p-4">
                    <div class="mb-4"><p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-[#7f9a7a]">Role Permissions</p><p class="mt-1 text-sm text-[#6f8a6b]">Select an employee to edit role, branch, and module access.</p></div>

                    <div class="grid gap-3" :class="directoryView === 'cards' ? 'md:grid-cols-2 xl:grid-cols-3' : 'md:grid-cols-2'">
                        <button
                            v-for="user in visibleUsers"
                            :key="`nav-${user.id}`"
                            type="button"
                            class="w-full rounded-[1.15rem] border bg-white p-4 text-left transition"
                            :class="selectedUserId === user.id
                                ? 'border-[#86c87b] bg-[linear-gradient(135deg,#eef8ea_0%,#ffffff_100%)] shadow-[0_14px_28px_rgba(79,159,74,0.10)]'
                                : 'border-[#d8e7d4] bg-white hover:border-[#b8e0ae] hover:bg-[#f7fcf5]'"
                            @click="selectedUserId = user.id"
                        >
                            <div class="flex items-start gap-3">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#9b0000] text-sm font-black text-white">{{ initials(user.name) }}</span>
                                <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-[#234222]">{{ user.name }}</p><p class="mt-1 truncate text-xs text-[#6f8a6b]">{{ user.username }}</p><p class="mt-2 truncate text-xs text-[#6f8a6b]">{{ user.job_title || 'No job title recorded' }}</p><p class="truncate text-xs text-[#6f8a6b]">{{ user.department || 'Department not specified' }}</p></div>
                                <span class="rounded-full border border-[#d8e7d4] bg-white px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-[#5f7b5e]">{{ accessLabel(user) }}</span>
                            </div>
                            <p class="mt-3 text-right text-xs font-bold text-[#9b0000]">Edit role →</p>
                        </button>
                        <div v-if="visibleUsers.length === 0" class="rounded-[1.15rem] border border-dashed border-[#d8e7d4] px-5 py-10 text-center text-sm text-[#6f8a6b] md:col-span-2 xl:col-span-3">No users match the selected filters or search.</div>
                    </div>
                </aside>

                <div v-if="selectedUser" class="rounded-[1.6rem] border border-[#d8e7d4] bg-white p-5">
                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div><p class="text-lg font-bold text-[#234222]">{{ selectedUser.name }}</p><p class="mt-1 text-sm text-[#6f8a6b]">{{ selectedUser.email }} · {{ selectedUser.department || 'Department not specified' }}</p><p class="mt-2 text-sm text-[#65748b]">{{ roleDescription(userForms[selectedUser.id].role) }}</p></div>
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-end"><label><span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-[#7f9a7a]">Role</span><CustomSelect v-model="userForms[selectedUser.id].role" class="select select-bordered w-full border-[#cfe6c8] bg-white sm:w-56" :disabled="!canEditSettings || userForms[selectedUser.id].saving"><option v-for="role in roleOptions" :key="role.value" :value="role.value">{{ role.label }}</option></CustomSelect></label><button type="button" class="btn bg-[#4f9f4a] text-white" :disabled="!canEditSettings || userForms[selectedUser.id].saving" @click="saveAccess(selectedUser.id)">{{ userForms[selectedUser.id].saving ? 'Saving...' : 'Save Role' }}</button></div>
                    </div>
                </div>

                <div v-if="false && selectedUser" class="rounded-[1.6rem] border border-[#d8e7d4] bg-[#fbfefa]">
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
                                    <CustomSelect
                                        v-model="userForms[selectedUser.id].role"
                                        class="select select-sm w-full border-[#cfe6c8] bg-white text-[#234222]"
                                        :disabled="!canEditSettings || userForms[selectedUser.id].saving"
                                        
                                    >
                                        <option v-for="role in roleOptions" :key="role.value" :value="role.value">{{ role.label }}</option>
                                    </CustomSelect>
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
                                        <CustomSelect v-model="userForms[selectedUser.id].branch_access[branch.id]" class="select select-sm select-bordered"><option value="none">None</option><option value="read">Read</option><option value="edit">Edit</option><option value="manage">Manage</option></CustomSelect>
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

        <div v-if="false" class="grid gap-6 2xl:grid-cols-3">
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
