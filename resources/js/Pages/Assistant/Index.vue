<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    prompts: {
        type: Array,
        default: () => [],
    },
});

const input = ref('');
const loading = ref(false);
const showAllPrompts = ref(false);
const messages = ref([
    {
        role: 'assistant',
        text: 'Ask about item location, current stock, last movement, or stock anomalies. I answer from live inventory records.',
    },
]);
const visiblePrompts = computed(() => showAllPrompts.value ? props.prompts : props.prompts.slice(0, 6));

const sendMessage = async (preset = null) => {
    const message = (preset ?? input.value).trim();

    if (!message || loading.value) {
        return;
    }

    messages.value.push({
        role: 'user',
        text: message,
    });

    input.value = '';
    loading.value = true;

    try {
        const { data } = await window.axios.post(route('assistant.query'), { message });

        messages.value.push({
            role: 'assistant',
            text: data.answer,
            item: data.item ?? null,
            items: data.items ?? [],
        });
    } catch (error) {
        const text = error?.response?.status === 403
            ? 'You do not have permission to use the assistant.'
            : 'The assistant could not process that question right now.';

        messages.value.push({
            role: 'assistant',
            text,
        });
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <Head title="Inventory Assistant" />

    <AuthenticatedLayout>
        <PageHeader
            title="Inventory Assistant"
            description="Ask simple inventory questions using live stock item and movement records."
        />

        <div class="grid gap-6 2xl:grid-cols-[0.7fr,1.3fr]">
            <aside class="rounded-[2rem] border border-[#d8e7d4] bg-white p-5 shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
                <div class="rounded-[1.75rem] border border-[#d8e7d4] bg-[radial-gradient(circle_at_top_left,_rgba(111,187,104,0.16),_transparent_30%),linear-gradient(180deg,_#ffffff_0%,_#f7fcf5_52%,_#eef8ea_100%)] p-5">
                    <p class="text-sm text-[#6f8a6b]">Assistant Scope</p>
                    <h2 class="mt-1 text-2xl font-semibold text-[#234222]">Live inventory answers</h2>
                        <p class="mt-3 text-sm leading-6 text-[#4f6b4b]">
                        This assistant answers from your database. It does not guess. Use exact item codes when possible for the best match, especially when checking why an item is flagged.
                        </p>
                </div>

                <div class="mt-5 space-y-3">
                    <p class="text-sm font-semibold text-[#234222]">Try these questions</p>
                    <button
                        v-for="prompt in visiblePrompts"
                        :key="prompt"
                        type="button"
                        class="w-full rounded-[1.25rem] border border-[#d8e7d4] bg-[#fbfefa] px-4 py-3 text-left text-sm text-[#355733] transition hover:border-[#b8e0ae] hover:bg-[#eef8ea]"
                        @click="sendMessage(prompt)"
                    >
                        {{ prompt }}
                    </button>
                    <button
                        v-if="prompts.length > 6"
                        type="button"
                        class="w-full rounded-[1.25rem] border border-[#cfe6c8] bg-white px-4 py-3 text-left text-sm font-semibold text-[#3c8a39] transition hover:bg-[#eef8ea]"
                        @click="showAllPrompts = !showAllPrompts"
                    >
                        {{ showAllPrompts ? 'Show fewer suggestions' : `Show more suggestions (${prompts.length - 6})` }}
                    </button>
                </div>
            </aside>

            <section class="rounded-[2rem] border border-[#d8e7d4] bg-white p-5 shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-[#6f8a6b]">Chat Panel</p>
                        <h2 class="text-2xl font-semibold text-[#234222]">Inventory chat</h2>
                    </div>
                    <span class="w-fit rounded-full border border-[#b8d7b1] bg-[#eef8ea] px-4 py-1 text-xs font-semibold text-[#3c8a39]">
                        V1
                    </span>
                </div>

                <div class="mt-5 h-[26rem] overflow-y-auto rounded-[1.75rem] border border-[#d8e7d4] bg-[linear-gradient(180deg,#fbfefa_0%,#ffffff_100%)] p-4 sm:h-[32rem]">
                    <div class="space-y-4">
                        <article
                            v-for="(message, index) in messages"
                            :key="index"
                            :class="message.role === 'user' ? 'ml-auto bg-[linear-gradient(135deg,#6fbb68_0%,#4f9f4a_100%)] text-white' : 'mr-auto border border-[#d8e7d4] bg-white text-[#234222]'"
                            class="max-w-full sm:max-w-3xl rounded-[1.5rem] px-4 py-3 shadow-sm"
                        >
                            <p class="text-xs font-semibold uppercase tracking-[0.2em]" :class="message.role === 'user' ? 'text-white/80' : 'text-[#7f9a7a]'">
                                {{ message.role === 'user' ? 'You' : 'Assistant' }}
                            </p>
                            <p class="mt-2 text-sm leading-6">{{ message.text }}</p>
                            <div v-if="message.item" class="mt-3 rounded-xl border border-[#e1efdc] bg-[#fbfefa] px-3 py-2 text-xs text-[#4f6b4b]">
                                <a :href="message.item.href" class="font-semibold text-[#3c8a39] hover:text-[#2f6f2d]">
                                    {{ message.item.item_code }}
                                </a>
                                <span> - {{ message.item.description }}</span>
                            </div>
                            <div v-if="message.items?.length" class="mt-3 space-y-2">
                                <div v-for="entry in message.items" :key="entry.id" class="rounded-xl border border-[#e1efdc] bg-[#fbfefa] px-3 py-2 text-xs text-[#4f6b4b]">
                                    <a :href="entry.href" class="font-semibold text-[#3c8a39] hover:text-[#2f6f2d]">
                                        {{ entry.item_code }}
                                    </a>
                                    <span> - {{ entry.description }}</span>
                                    <div class="mt-1 text-[#6f8a6b]">
                                        {{ entry.current_stock }} {{ entry.uom }} at {{ entry.current_location }}
                                    </div>
                                </div>
                            </div>
                        </article>

                        <article v-if="loading" class="mr-auto max-w-full sm:max-w-3xl rounded-[1.5rem] border border-[#d8e7d4] bg-white px-4 py-3 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7f9a7a]">Assistant</p>
                            <p class="mt-2 text-sm text-[#4f6b4b]">Checking live inventory records...</p>
                        </article>
                    </div>
                </div>

                <form class="mt-5 rounded-[1.75rem] border border-[#d8e7d4] bg-[#fbfefa] p-4" @submit.prevent="sendMessage()">
                    <label class="mb-3 block text-[11px] font-semibold uppercase tracking-[0.22em] text-[#7f9a7a]">Ask inventory assistant</label>
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <input
                            v-model="input"
                            type="text"
                            class="input w-full border-[#cfe6c8] bg-white text-[#234222] placeholder:text-[#7f9a7a]"
                            placeholder="Where is CON-Y1-0001?"
                            :disabled="loading"
                        >
                        <button
                            type="submit"
                            class="btn border-none bg-[linear-gradient(135deg,#6fbb68_0%,#4f9f4a_100%)] text-white shadow-[0_16px_36px_rgba(79,159,74,0.24)] hover:opacity-95"
                            :disabled="loading"
                        >
                            {{ loading ? 'Checking...' : 'Ask' }}
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
