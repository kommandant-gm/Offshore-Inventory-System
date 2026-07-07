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

    <div class="min-h-screen bg-[linear-gradient(180deg,#ffffff_0%,#fcfefb_52%,#f7fcf5_100%)] font-sans text-[#234222]">
        <div class="mx-auto flex min-h-screen max-w-[1680px] flex-col lg:grid lg:grid-cols-[1.06fr,0.94fr]">
            <div class="relative overflow-hidden px-6 pb-10 pt-8 sm:px-8 lg:px-12 lg:pb-14 lg:pt-10">
                <div class="absolute inset-x-0 top-0 h-[360px] bg-[radial-gradient(circle_at_top_left,rgba(111,187,104,0.12),transparent_56%),radial-gradient(circle_at_top_right,rgba(184,224,174,0.18),transparent_38%)]" />
                <div class="absolute left-[-8%] top-[18%] h-48 w-48 rounded-full bg-[#f4fbf1] blur-3xl" />
                <div class="absolute bottom-[12%] right-[8%] h-56 w-56 rounded-full bg-[#edf8e9] blur-3xl" />

                <div class="relative z-10 flex h-full flex-col justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-[#6fbb68] to-[#3c8a39] text-white shadow-[0_14px_30px_rgba(79,159,74,0.22)] ring-1 ring-[#d8e7d4]">
                            <span class="text-xl font-bold">D</span>
                        </div>
                        <div>
                            <p class="font-bold tracking-wide text-[#234222] sm:text-lg">Dayang Inventory Management System</p>
                            <p class="text-[11px] uppercase tracking-[0.22em] text-[#7f9a7a]">Operational Access Portal</p>
                        </div>
                    </div>

                    <div class="mt-10 lg:mt-0">
                        <div class="inline-flex items-center gap-2 rounded-full border border-[#cfe6ca] bg-white/90 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-[#4f6b4b] shadow-sm">
                            <span class="h-2 w-2 rounded-full bg-[#4f9f4a]" />
                            Inventory Control
                        </div>

                        <h1 class="mt-6 max-w-2xl text-4xl font-semibold leading-tight text-[#234222] sm:text-5xl">
                            Stock operations with a cleaner,
                            <span class="text-[#4f9f4a]">faster daily workflow.</span>
                        </h1>
                        <p class="mt-5 max-w-xl text-base leading-8 text-[#5f7b5e] sm:text-lg">
                            Track stock items, movement approvals, ledger activity, and location control from one operating surface built for the Dayang process.
                        </p>

                        <div class="mt-8 grid gap-4 sm:grid-cols-3">
                            <div class="rounded-[1.5rem] border border-[#deebda] bg-white p-4 shadow-[0_14px_30px_rgba(79,159,74,0.05)]">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-[#7f9a7a]">Movement Logs</p>
                                <p class="mt-2 text-2xl font-semibold text-[#234222]">Live</p>
                                <p class="mt-1 text-sm text-[#5f7b5e]">Post and review stock movement activity in one place.</p>
                            </div>
                            <div class="rounded-[1.5rem] border border-[#deebda] bg-white p-4 shadow-[0_14px_30px_rgba(79,159,74,0.05)]">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-[#7f9a7a]">Ledger Control</p>
                                <p class="mt-2 text-2xl font-semibold text-[#234222]">Monthly</p>
                                <p class="mt-1 text-sm text-[#5f7b5e]">Review quantities, values, and supporting references.</p>
                            </div>
                            <div class="rounded-[1.5rem] border border-[#deebda] bg-white p-4 shadow-[0_14px_30px_rgba(79,159,74,0.05)]">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-[#7f9a7a]">Access Policy</p>
                                <p class="mt-2 text-2xl font-semibold text-[#234222]">Managed</p>
                                <p class="mt-1 text-sm text-[#5f7b5e]">Use local users and LDAP sign-in under the same system.</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-10 rounded-[1.75rem] border border-[#deebda] bg-white/95 p-5 shadow-[0_18px_45px_rgba(79,159,74,0.06)] backdrop-blur">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-[#7f9a7a]">Daily Focus</p>
                        <div class="mt-3 flex flex-col gap-3 text-sm text-[#4f6b4b] sm:flex-row sm:items-center sm:justify-between">
                            <span>Keep stock movements, COG actions, and ledger reporting aligned before month-end close.</span>
                            <span class="rounded-full border border-[#cfe6ca] bg-[#eef8ea] px-3 py-1 font-semibold text-[#2f6f2d]">System ready</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-center px-6 pb-10 pt-2 sm:px-8 lg:px-12 lg:pb-14 lg:pt-10">
                <div class="w-full max-w-xl rounded-[2rem] border border-[#deebda] bg-white p-6 shadow-[0_24px_60px_rgba(79,159,74,0.08)] sm:p-8">
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
                            <a href="#" class="font-medium text-[#3c8a39] transition-colors hover:text-[#2f6f2d]">Contact Admin</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
