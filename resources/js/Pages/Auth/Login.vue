<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import {
    UserIcon,
    LockClosedIcon,
    ArrowRightIcon,
} from '@heroicons/vue/24/outline';
import Checkbox from '@/Components/Checkbox.vue';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    supportEmail: {
        type: String,
        default: '',
    },
    authStatus: {
        type: Object,
        default: () => ({
            label: 'Authentication ready',
            tone: 'ready',
        }),
    },
});

const form = useForm({
    username: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Log in" />

    <div class="min-h-screen bg-[radial-gradient(circle_at_top_left,#eef7ef_0%,#f6faf6_35%,#ffffff_72%),linear-gradient(180deg,#fbfdfb_0%,#f5faf6_100%)] font-sans text-[#234222]">
        <div class="mx-auto flex min-h-screen max-w-[1600px] items-center px-4 py-6 sm:px-6 lg:px-10">
            <div class="grid w-full overflow-hidden rounded-[2.25rem] border border-[#d9e7dc] bg-white/78 shadow-[0_28px_90px_rgba(34,68,44,0.12)] backdrop-blur-xl lg:grid-cols-[1.08fr,0.92fr]">
                <div class="relative overflow-hidden bg-[linear-gradient(145deg,#214f41_0%,#2c6450_34%,#3f7d60_100%)] px-7 pb-8 pt-7 sm:px-10 lg:px-12 lg:pb-10 lg:pt-9">
                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(255,255,255,0.18),transparent_32%),radial-gradient(circle_at_82%_78%,rgba(155,221,171,0.24),transparent_24%),linear-gradient(180deg,rgba(255,255,255,0.02),rgba(255,255,255,0))]" />
                    <div class="absolute -left-12 top-16 h-44 w-44 rounded-full bg-white/10 blur-3xl" />
                    <div class="absolute bottom-10 right-10 h-56 w-56 rounded-full bg-[#9fd6ad]/16 blur-3xl" />

                    <div class="relative z-10 flex h-full flex-col">
                        <div class="flex items-center gap-4">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl border border-white/20 bg-white/10 text-white shadow-[0_18px_38px_rgba(12,34,27,0.28)] backdrop-blur">
                                <span class="text-xl font-bold">D</span>
                            </div>
                            <div>
                                <p class="font-bold tracking-wide text-white sm:text-[1.15rem]">Dayang Inventory Management System</p>
                                <p class="text-[11px] uppercase tracking-[0.28em] text-[#d7efe0]">Operational Access Portal</p>
                            </div>
                        </div>

                        <div class="mt-14 max-w-2xl">
                            <div class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/8 px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.24em] text-white/90 backdrop-blur">
                                <span class="h-2 w-2 rounded-full bg-[#c9f0d4]" />
                                Inventory Control
                            </div>

                            <h1 class="mt-7 max-w-xl text-4xl font-semibold leading-tight text-white sm:text-[3.25rem]">
                                Secure stock operations
                                <span class="block text-[#d8f5df]">with a cleaner, faster workflow.</span>
                            </h1>
                            <p class="mt-5 max-w-lg text-base leading-8 text-[#e7f5eb] sm:text-lg">
                                Track stock items, movement approvals, ledger activity, and location control from one operating surface built for the Dayang process.
                            </p>

                            <div class="mt-10 grid gap-4 sm:grid-cols-3">
                                <div class="rounded-[1.6rem] border border-white/14 bg-white/8 p-5 shadow-[0_16px_40px_rgba(12,34,27,0.16)] backdrop-blur">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-[#d7efe0]">Movement Logs</p>
                                    <p class="mt-3 text-2xl font-semibold text-white">Live</p>
                                    <p class="mt-2 text-sm leading-6 text-[#edf8f0]">Post and review stock movement activity in one place.</p>
                                </div>
                                <div class="rounded-[1.6rem] border border-white/14 bg-white/8 p-5 shadow-[0_16px_40px_rgba(12,34,27,0.16)] backdrop-blur">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-[#d7efe0]">Ledger Control</p>
                                    <p class="mt-3 text-2xl font-semibold text-white">Monthly</p>
                                    <p class="mt-2 text-sm leading-6 text-[#edf8f0]">Review quantities, values, and supporting references.</p>
                                </div>
                                <div class="rounded-[1.6rem] border border-white/14 bg-white/8 p-5 shadow-[0_16px_40px_rgba(12,34,27,0.16)] backdrop-blur">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-[#d7efe0]">Access Policy</p>
                                    <p class="mt-3 text-2xl font-semibold text-white">Managed</p>
                                    <p class="mt-2 text-sm leading-6 text-[#edf8f0]">Use local users and LDAP sign-in under the same system.</p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-10 rounded-[1.9rem] border border-white/16 bg-white/10 p-5 shadow-[0_18px_48px_rgba(12,34,27,0.16)] backdrop-blur">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-[#d7efe0]">Daily Focus</p>
                            <div class="mt-3 flex flex-col gap-3 text-sm text-[#edf8f0] sm:flex-row sm:items-center sm:justify-between">
                                <span>Keep stock movements, COG actions, and ledger reporting aligned before month-end close.</span>
                                <span
                                    class="rounded-full border px-3 py-1 font-semibold"
                                    :class="authStatus.tone === 'warning'
                                        ? 'border-[#f7dfbb] bg-[#fff6e7] text-[#9a5d10]'
                                        : 'border-white/18 bg-white text-[#2f6f2d]'"
                                >
                                    {{ authStatus.label }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="relative flex items-center justify-center bg-[linear-gradient(180deg,#ffffff_0%,#fbfdfb_100%)] px-6 py-8 sm:px-8 lg:px-12">
                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(127,180,144,0.10),transparent_28%),radial-gradient(circle_at_bottom_left,rgba(207,230,202,0.22),transparent_26%)]" />
                    <div class="relative w-full max-w-xl rounded-[2rem] border border-[#e2ece3] bg-white/92 p-6 shadow-[0_22px_60px_rgba(26,51,38,0.08)] backdrop-blur sm:p-8">
                        <div class="mb-8 flex items-start justify-between gap-4">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-[#7f9a7a]">Secure Sign In</p>
                                <h2 class="mt-2 text-3xl font-semibold text-[#234222]">Welcome back</h2>
                                <p class="mt-2 text-sm text-[#5f7b5e]">Use your system username or your LDAP account if directory login is enabled.</p>
                            </div>
                            <div class="hidden h-12 w-12 items-center justify-center rounded-2xl border border-[#deebda] bg-[#fbfefa] text-[#4f9f4a] sm:flex">
                                <ArrowRightIcon class="h-5 w-5" />
                            </div>
                        </div>

                        <div v-if="status" class="mb-4 rounded-xl border border-[#b8e0ae] bg-[#f3fbef] p-3 text-sm font-medium text-[#2f6f2d]">
                            {{ status }}
                        </div>

                        <form @submit.prevent="submit" class="space-y-5">
                            <div>
                                <label for="username" class="mb-1.5 block text-sm font-medium text-[#4f6b4b]">Username</label>
                                <div class="group relative">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                        <UserIcon class="h-5 w-5 text-[#7f9a7a] transition-colors group-focus-within:text-[#4f9f4a]" />
                                    </div>
                                    <input
                                        id="username"
                                        type="text"
                                        v-model="form.username"
                                        required
                                        autofocus
                                        autocomplete="username"
                                        class="block w-full rounded-xl border border-[#d8e7d4] bg-white py-3 pl-10 pr-3 text-[#234222] shadow-sm transition-all placeholder:text-[#7f9a7a] focus:border-[#86c87b] focus:outline-none focus:ring-2 focus:ring-[#cfe6ca]"
                                        placeholder="marie.sim"
                                    />
                                </div>
                                <p v-if="form.errors.username" class="mt-2 text-sm text-red-500">{{ form.errors.username }}</p>
                            </div>

                            <div>
                                <label for="password" class="mb-1.5 block text-sm font-medium text-[#4f6b4b]">Password</label>
                                <div class="group relative">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                        <LockClosedIcon class="h-5 w-5 text-[#7f9a7a] transition-colors group-focus-within:text-[#4f9f4a]" />
                                    </div>
                                    <input
                                        id="password"
                                        type="password"
                                        v-model="form.password"
                                        required
                                        autocomplete="current-password"
                                        class="block w-full rounded-xl border border-[#d8e7d4] bg-white py-3 pl-10 pr-3 text-[#234222] shadow-sm transition-all placeholder:text-[#7f9a7a] focus:border-[#86c87b] focus:outline-none focus:ring-2 focus:ring-[#cfe6ca]"
                                        placeholder="........"
                                    />
                                </div>
                                <p v-if="form.errors.password" class="mt-2 text-sm text-red-500">{{ form.errors.password }}</p>
                            </div>

                            <div class="flex items-center justify-between">
                                <label class="flex cursor-pointer items-center">
                                    <Checkbox name="remember" v-model:checked="form.remember" class="border-[#cfe6ca] bg-white text-[#4f9f4a] focus:ring-[#86c87b]" />
                                    <span class="ms-2 text-sm text-[#5f7b5e]">Remember me</span>
                                </label>
                            </div>

                            <button
                                type="submit"
                                class="flex w-full items-center justify-center gap-2 rounded-xl border-none bg-[linear-gradient(135deg,#6fbb68_0%,#4f9f4a_100%)] px-4 py-3 text-sm font-bold text-white shadow-[0_16px_36px_rgba(79,159,74,0.24)] transition-all hover:opacity-95 focus:outline-none focus:ring-2 focus:ring-[#86c87b] focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="form.processing"
                            >
                                <span v-if="form.processing">Signing in...</span>
                                <span v-else>Sign in to Dashboard</span>
                                <ArrowRightIcon v-if="!form.processing" class="h-4 w-4" />
                            </button>
                        </form>

                        <div class="mt-8 border-t border-[#edf3eb] pt-5 text-center">
                            <p class="text-sm text-[#6f8a6b]">
                                Need access or first-time credentials?
                                <a :href="supportEmail ? `mailto:${supportEmail}` : 'mailto:support@example.com'" class="font-medium text-[#3c8a39] transition-colors hover:text-[#2f6f2d]">Contact Admin</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
