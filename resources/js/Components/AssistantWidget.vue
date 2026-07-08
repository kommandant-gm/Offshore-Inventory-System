<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { ChatBubbleLeftRightIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { nextTick, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

const open = ref(false);
const input = ref('');
const loading = ref(false);
const showAllPrompts = ref(false);
const page = usePage();
const messages = ref([
    {
        role: 'assistant',
        text: 'Ask about item location, current stock, last movement, or stock anomalies.',
    },
]);
const bodyRef = ref(null);

const prompts = computed(() => page.props.ui?.assistant_prompts ?? []);
const visiblePrompts = computed(() => showAllPrompts.value ? prompts.value : prompts.value.slice(0, 4));

const scrollToBottom = async () => {
    await nextTick();

    if (bodyRef.value) {
        bodyRef.value.scrollTop = bodyRef.value.scrollHeight;
    }
};

const toggle = async () => {
    open.value = !open.value;

    if (open.value) {
        showAllPrompts.value = false;
        await scrollToBottom();
    }
};

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
    await scrollToBottom();

    try {
        const { data } = await window.axios.post(route('assistant.query'), { message });

        messages.value.push({
            role: 'assistant',
            text: data.answer,
            item: data.item ?? null,
            items: data.items ?? [],
        });
    } catch (error) {
        messages.value.push({
            role: 'assistant',
            text: error?.response?.status === 403
                ? 'You do not have permission to use the assistant.'
                : 'The assistant could not process that question right now.',
        });
    } finally {
        loading.value = false;
        await scrollToBottom();
    }
};
</script>

<template>
    <div class="pointer-events-none fixed bottom-5 right-5 z-50">
        <transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-4 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-4 opacity-0"
        >
            <section
                v-if="open"
                class="pointer-events-auto mb-4 flex max-h-[calc(100vh-6rem)] w-[min(24rem,calc(100vw-2.5rem))] flex-col overflow-hidden rounded-[1.75rem] border border-[#d8e7d4] bg-white shadow-[0_24px_60px_rgba(79,159,74,0.18)]"
            >
                <div class="flex items-center justify-between border-b border-[#edf3eb] bg-[linear-gradient(180deg,#ffffff_0%,#f4fbf1_100%)] px-4 py-3">
                    <div>
                        <p class="text-xs uppercase tracking-[0.22em] text-[#7f9a7a]">Inventory Assistant</p>
                        <p class="text-sm font-semibold text-[#234222]">Live inventory answers</p>
                    </div>
                    <button
                        type="button"
                        class="btn btn-sm btn-circle border border-[#d8e7d4] bg-white text-[#2f6f2d] hover:bg-[#eef8ea]"
                        @click="toggle"
                    >
                        <XMarkIcon class="h-4 w-4" />
                    </button>
                </div>

                <div ref="bodyRef" class="min-h-0 flex-1 space-y-3 overflow-y-auto bg-[#fbfefa] p-4">
                    <article
                        v-for="(message, index) in messages"
                        :key="index"
                        :class="message.role === 'user' ? 'ml-auto bg-[linear-gradient(135deg,#6fbb68_0%,#4f9f4a_100%)] text-white' : 'mr-auto border border-[#d8e7d4] bg-white text-[#234222]'"
                        class="max-w-[90%] rounded-[1.25rem] px-3 py-2 shadow-sm"
                    >
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" :class="message.role === 'user' ? 'text-white/80' : 'text-[#7f9a7a]'">
                            {{ message.role === 'user' ? 'You' : 'Assistant' }}
                        </p>
                        <p class="mt-1 text-sm leading-6">{{ message.text }}</p>
                        <div v-if="message.item" class="mt-2 rounded-lg border border-[#e1efdc] bg-[#fbfefa] px-2 py-1.5 text-xs text-[#4f6b4b]">
                            <a :href="message.item.href" class="font-semibold text-[#3c8a39] hover:text-[#2f6f2d]">
                                {{ message.item.item_code }}
                            </a>
                            <span> - {{ message.item.description }}</span>
                        </div>
                        <div v-if="message.items?.length" class="mt-2 space-y-2">
                            <div v-for="entry in message.items" :key="entry.id" class="rounded-lg border border-[#e1efdc] bg-[#fbfefa] px-2 py-1.5 text-xs text-[#4f6b4b]">
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

                    <article v-if="loading" class="mr-auto max-w-[90%] rounded-[1.25rem] border border-[#d8e7d4] bg-white px-3 py-2 shadow-sm">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#7f9a7a]">Assistant</p>
                        <p class="mt-1 text-sm text-[#4f6b4b]">Checking live inventory records...</p>
                    </article>
                </div>

                <div class="border-t border-[#edf3eb] bg-white p-4">
                    <div class="mb-3 flex flex-wrap gap-2">
                        <button
                            v-for="prompt in visiblePrompts"
                            :key="prompt"
                            type="button"
                            class="rounded-full border border-[#d8e7d4] bg-[#fbfefa] px-3 py-1.5 text-xs text-[#355733] transition hover:bg-[#eef8ea]"
                            @click="sendMessage(prompt)"
                        >
                            {{ prompt }}
                        </button>
                        <button
                            v-if="prompts.length > 4"
                            type="button"
                            class="rounded-full border border-[#cfe6c8] bg-white px-3 py-1.5 text-xs font-semibold text-[#3c8a39] transition hover:bg-[#eef8ea]"
                            @click="showAllPrompts = !showAllPrompts"
                        >
                            {{ showAllPrompts ? 'Less suggestions' : `More suggestions (${prompts.length - 4})` }}
                        </button>
                    </div>

                    <form class="space-y-3" @submit.prevent="sendMessage()">
                        <input
                            v-model="input"
                            type="text"
                            class="input w-full border-[#cfe6c8] bg-white text-[#234222] placeholder:text-[#7f9a7a]"
                            placeholder="Ask about an item..."
                            :disabled="loading"
                        >

                        <div class="flex items-center justify-between gap-3">
                            <Link class="text-sm font-medium text-[#3c8a39] hover:text-[#2f6f2d]" :href="route('assistant.index')">
                                Open Full Assistant
                            </Link>

                            <button
                                type="submit"
                                class="btn border-none bg-[linear-gradient(135deg,#6fbb68_0%,#4f9f4a_100%)] text-white shadow-[0_16px_36px_rgba(79,159,74,0.24)] hover:opacity-95"
                                :disabled="loading"
                            >
                                {{ loading ? 'Checking...' : 'Ask' }}
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </transition>

        <button
            type="button"
            class="pointer-events-auto flex h-14 w-14 items-center justify-center rounded-full border border-[#b8e0ae] bg-[linear-gradient(135deg,#6fbb68_0%,#4f9f4a_100%)] text-white shadow-[0_18px_40px_rgba(79,159,74,0.28)] transition hover:scale-[1.03] hover:opacity-95"
            @click="toggle"
        >
            <ChatBubbleLeftRightIcon class="h-6 w-6" />
        </button>
    </div>
</template>
