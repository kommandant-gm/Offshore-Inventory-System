<script setup>
    import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
    import AssistantWidget from '@/Components/AssistantWidget.vue';
    import { Link, router, usePage } from '@inertiajs/vue3';
    import { 
        Squares2X2Icon, ArchiveBoxIcon, MapIcon, TruckIcon,
        ClipboardDocumentCheckIcon, ChartBarIcon, ChatBubbleLeftRightIcon,
        Cog6ToothIcon, BellIcon, MagnifyingGlassIcon,
        ExclamationTriangleIcon,
        Bars3Icon, ArrowRightStartOnRectangleIcon, TagIcon, BuildingStorefrontIcon
    } from '@heroicons/vue/24/outline';

    const isSidebarOpen = ref(false);
    const page = usePage();
    const quickSearch = ref('');
    const quickSearchResults = ref([]);
    const quickSearchOpen = ref(false);
    const quickSearchLoading = ref(false);
    const notificationsOpen = ref(false);
    let quickSearchTimer = null;

    const navItems = [
        { name: 'Dashboard', icon: Squares2X2Icon, route: 'dashboard' },
        { name: 'Assistant', icon: ChatBubbleLeftRightIcon, route: 'assistant.index', can: 'assistant_read' },
        { name: 'Stock Anomalies', icon: ExclamationTriangleIcon, route: 'anomalies.index', can: 'anomalies_read' },
        { name: 'Stock Items', icon: ArchiveBoxIcon, route: 'assets.index' },
        { name: 'Stock Movements', icon: TruckIcon, route: 'asset-movements.index', can: 'movements_read' },
        { name: 'Stock Ledger', icon: ChartBarIcon, route: 'asset-ledger.index' },
        { name: 'COG Control', icon: ClipboardDocumentCheckIcon, route: 'cogs.index' },
        { name: 'Categories', icon: TagIcon, route: 'categories.index' },
        { name: 'Locations', icon: MapIcon, route: 'locations.index' },
        { name: 'Receive / Issue', icon: BuildingStorefrontIcon, route: 'asset-movements.create', can: 'movements_edit' },
    ];

    const currentUser = page.props.auth.user;
    const notifications = computed(() => page.props.ui?.notifications?.items ?? []);
    const notificationCount = computed(() => page.props.ui?.notifications?.unread_count ?? 0);
    const currentUserInitials = computed(() => {
        const name = currentUser?.name ?? 'User';

        return name
            .split(' ')
            .filter(Boolean)
            .slice(0, 2)
            .map((value) => value[0]?.toUpperCase() ?? '')
            .join('');
    });
    const isSettingsRoute = computed(() => route().current('settings.index') || route().current('settings.*'));

    const runQuickSearch = async () => {
        const term = quickSearch.value.trim();

        if (term.length < 2) {
            quickSearchResults.value = [];
            quickSearchOpen.value = false;
            quickSearchLoading.value = false;
            return;
        }

        quickSearchLoading.value = true;

        try {
            const response = await fetch(`${route('quick-search')}?q=${encodeURIComponent(term)}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Quick search request failed.');
            }

            const payload = await response.json();
            quickSearchResults.value = payload.items ?? [];
            quickSearchOpen.value = true;
        } catch (error) {
            quickSearchResults.value = [];
            quickSearchOpen.value = true;
        } finally {
            quickSearchLoading.value = false;
        }
    };

    const submitQuickSearch = () => {
        if (quickSearchResults.value[0]?.href) {
            router.visit(quickSearchResults.value[0].href);
            quickSearchOpen.value = false;
            return;
        }

        if (quickSearch.value.trim()) {
            router.get(route('assets.index'));
        }
    };

    const openSearchResult = (href) => {
        quickSearchOpen.value = false;
        router.visit(href);
    };

    const toggleNotifications = () => {
        notificationsOpen.value = !notificationsOpen.value;
    };

    const closeOverlays = (event) => {
        if (!event.target.closest('[data-topbar-search]')) {
            quickSearchOpen.value = false;
        }

        if (!event.target.closest('[data-notification-menu]')) {
            notificationsOpen.value = false;
        }
    };

    watch(quickSearch, () => {
        clearTimeout(quickSearchTimer);
        quickSearchTimer = setTimeout(runQuickSearch, 180);
    });

    onMounted(() => {
        document.addEventListener('click', closeOverlays);
    });

    onBeforeUnmount(() => {
        clearTimeout(quickSearchTimer);
        document.removeEventListener('click', closeOverlays);
    });
    </script>
    
    <template>
        <div class="drawer lg:drawer-open min-h-screen font-sans antialiased bg-[linear-gradient(180deg,#ffffff_0%,#f7fcf5_100%)] text-[#234222]">
            <input id="my-drawer-2" type="checkbox" class="drawer-toggle" v-model="isSidebarOpen" />
            
            <div class="drawer-content relative flex min-h-screen flex-col overflow-hidden">
                
                <div class="navbar sticky top-0 z-30 h-auto min-h-20 w-full border-b border-[#d8e7d4] bg-white px-4 py-3 sm:px-6 lg:px-8">
                    <div class="flex-none lg:hidden mr-4">
                        <label for="my-drawer-2" class="btn btn-square border border-[#d8e7d4] bg-white text-[#2f6f2d] shadow-sm hover:bg-[#eef8ea]">
                            <Bars3Icon class="w-6 h-6" />
                        </label>
                    </div>
                    
                    <div class="min-w-0 flex-1">
                        <h2 class="truncate text-lg font-bold tracking-wide text-[#234222] sm:text-xl">Dayang Inventory Management System</h2>
                    </div>
    
                    <div class="ml-3 flex items-center gap-2 sm:gap-4">
                        <div class="relative hidden group md:flex" data-topbar-search>
                            <MagnifyingGlassIcon class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-[#7f9a7a] group-focus-within:text-[#4f9f4a] transition-colors" />
                            <input
                                v-model="quickSearch"
                                type="text"
                                placeholder="Search item, ID, or zone..."
                                class="input input-sm h-10 w-56 rounded-lg border border-[#d8e7d4] bg-white pl-10 text-sm text-[#234222] placeholder:text-[#7f9a7a] shadow-sm transition-all focus:border-[#4f9f4a] focus:outline-none lg:w-64"
                                @focus="quickSearchOpen = quickSearchResults.length > 0"
                                @keydown.enter.prevent="submitQuickSearch"
                            />
                            <div v-if="quickSearchOpen" class="absolute top-full z-20 mt-2 w-full overflow-hidden rounded-2xl border border-[#d8e7d4] bg-white shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
                                <div v-if="quickSearchLoading" class="px-4 py-3 text-sm text-[#6f8a6b]">Searching...</div>
                                <div v-else-if="quickSearchResults.length === 0" class="px-4 py-3 text-sm text-[#6f8a6b]">No matching items found.</div>
                                <button
                                    v-for="result in quickSearchResults"
                                    :key="result.id"
                                    type="button"
                                    class="flex w-full items-start justify-between gap-3 border-t border-[#edf3eb] px-4 py-3 text-left first:border-t-0 hover:bg-[#f7fcf5]"
                                    @click="openSearchResult(result.href)"
                                >
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-[#234222]">{{ result.title }}</p>
                                        <p class="mt-1 truncate text-xs text-[#6f8a6b]">{{ result.subtitle }}</p>
                                    </div>
                                    <span class="rounded-full border border-[#d8e7d4] bg-[#fbfefa] px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-[#5f7b5e]">
                                        {{ result.type }}
                                    </span>
                                </button>
                            </div>
                        </div>
    
                        <div class="relative" data-notification-menu>
                            <button class="btn btn-circle btn-sm relative border border-[#d8e7d4] bg-white text-[#2f6f2d] shadow-sm transition-all hover:border-[#86c87b] hover:bg-[#eef8ea]" @click.stop="toggleNotifications">
                                <BellIcon class="w-5 h-5" />
                                <span v-if="notificationCount > 0" class="absolute top-0 right-0 min-w-[1rem] rounded-full border-2 border-white bg-[#4f9f4a] px-1 text-[9px] font-bold leading-4 text-white">
                                    {{ notificationCount }}
                                </span>
                            </button>
                            <div v-if="notificationsOpen" class="absolute right-0 top-full z-20 mt-2 w-80 overflow-hidden rounded-2xl border border-[#d8e7d4] bg-white shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
                                <div class="border-b border-[#edf3eb] px-4 py-3">
                                    <p class="text-sm font-semibold text-[#234222]">Notifications</p>
                                    <p class="mt-1 text-xs text-[#6f8a6b]">Live operational items that may need attention.</p>
                                </div>
                                <div v-if="notifications.length === 0" class="px-4 py-4 text-sm text-[#6f8a6b]">
                                    No notifications right now.
                                </div>
                                <Link
                                    v-for="notification in notifications"
                                    :key="notification.id"
                                    :href="notification.href"
                                    class="block border-t border-[#edf3eb] px-4 py-3 first:border-t-0 hover:bg-[#f7fcf5]"
                                    @click="notificationsOpen = false"
                                >
                                    <p class="text-sm font-semibold text-[#234222]">{{ notification.title }}</p>
                                    <p class="mt-1 text-xs text-[#6f8a6b]">{{ notification.description }}</p>
                                </Link>
                            </div>
                        </div>
    
                    </div>
                </div>

                <main class="flex-1 overflow-y-auto space-y-5 p-4 scrollbar-thin scrollbar-thumb-[#b8d7b1] scrollbar-track-transparent sm:p-5 lg:space-y-6 lg:p-8">
                    <div
                        v-if="$page.props.flash.success"
                        class="rounded-xl border border-[#b8e0ae] bg-[#eef8ea] px-4 py-3 text-sm text-[#2f6f2d]"
                    >
                        {{ $page.props.flash.success }}
                    </div>
                    <slot />
                </main>

                <AssistantWidget v-if="currentUser?.can?.assistant_read" />
            </div> 
            
            <div class="drawer-side z-40">
                <label for="my-drawer-2" class="drawer-overlay bg-[#4f9f4a]/10"></label> 
                <aside class="flex h-full w-72 max-w-[85vw] flex-col border-r border-[#d8e7d4] bg-white shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
                    
                    <div class="h-24 flex items-center gap-3 border-b border-[#edf3eb] px-6 bg-white">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-[#6fbb68] to-[#3c8a39] flex items-center justify-center text-white shadow-[0_14px_30px_rgba(79,159,74,0.22)] ring-1 ring-[#d8e7d4]">
                            <span class="font-bold text-xl">D</span>
                        </div>
                        <div>
                            <h1 class="font-bold text-base text-[#234222] tracking-wide leading-tight">Dayang <span class="text-[#4f9f4a]">Inventory</span></h1>
                            <p class="text-[10px] text-[#7f9a7a] font-medium tracking-widest uppercase">Management System</p>
                        </div>
                    </div>
    
                    <div class="flex-1 overflow-y-auto py-4 px-4 space-y-1">
                        <Link v-for="item in navItems.filter((entry) => !entry.can || currentUser?.can?.[entry.can])" :key="item.name" 
                            :href="item.route ? route(item.route) : '#'" 
                            :class="[
                                item.route && route().current(item.route)
                                ? 'bg-[linear-gradient(135deg,#6fbb68_0%,#4f9f4a_100%)] text-white shadow-[0_14px_30px_rgba(79,159,74,0.18)] ring-1 ring-[#b8e0ae]' 
                                : 'text-[#5f7b5e] hover:bg-[#eef8ea] hover:text-[#234222]'
                            ]" 
                            class="flex items-center gap-3 px-4 py-3.5 rounded-xl text-sm font-medium transition-all duration-200 group"
                        >
                            <component :is="item.icon" class="w-5 h-5" :class="item.route && route().current(item.route) ? 'opacity-100' : 'opacity-70 group-hover:opacity-100'" />
                            {{ item.name }}
                        </Link>
                    </div>
    
                    <div v-if="currentUser?.can?.settings_read" class="px-4 pb-4 border-t border-[#edf3eb] pt-4 bg-white">
                         <Link :href="route('settings.index')" :class="isSettingsRoute ? 'bg-[linear-gradient(135deg,#6fbb68_0%,#4f9f4a_100%)] text-white shadow-[0_14px_30px_rgba(79,159,74,0.18)] ring-1 ring-[#b8e0ae]' : 'text-[#5f7b5e] hover:bg-[#eef8ea] hover:text-[#234222]'" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all">
                            <Cog6ToothIcon class="w-5 h-5" :class="isSettingsRoute ? 'opacity-100' : 'opacity-70'" />
                            Settings
                        </Link>
                    </div>
    
                    <div class="p-4 bg-white">
                        <div class="flex items-center gap-3 rounded-xl border border-[#d8e7d4] bg-[#fbfefa] p-3 shadow-sm transition-all cursor-pointer group hover:border-[#b8e0ae] hover:bg-[#f4fbf1]">
                            <div class="relative shrink-0">
                                <div class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-full bg-gradient-to-br from-[#6fbb68] to-[#3c8a39] text-sm font-bold text-white ring ring-[#d8e7d4] transition-all group-hover:ring-[#86c87b]">
                                    {{ currentUserInitials }}
                                </div>
                                <span class="absolute bottom-0 right-0 w-3 h-3 rounded-full border-2 border-white bg-[#4f9f4a]"></span>
                            </div>
                            <div class="flex-1 overflow-hidden">
                                <p class="text-sm font-bold text-[#234222] truncate group-hover:text-[#3c8a39] transition-colors">{{ currentUser?.name }}</p>
                                <p class="text-[11px] text-[#7f9a7a] truncate">{{ currentUser?.email }}</p>
                            </div>
                            
                            <Link :href="route('logout')" method="post" as="button" class="group/logout">
                                <ArrowRightStartOnRectangleIcon class="w-5 h-5 text-[#7f9a7a] group-hover/logout:text-[#3c8a39] transition-colors" />
                            </Link>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </template>
