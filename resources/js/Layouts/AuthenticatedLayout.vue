<script setup>
    import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
    import AssistantWidget from '@/Components/AssistantWidget.vue';
    import { Link, router, usePage } from '@inertiajs/vue3';
    import { 
        Squares2X2Icon, ArchiveBoxIcon, MapIcon, TruckIcon,
        ClipboardDocumentCheckIcon, ChartBarIcon, ChatBubbleLeftRightIcon,
        Cog6ToothIcon, BellIcon, MagnifyingGlassIcon,
        ExclamationTriangleIcon,
        Bars3Icon, ArrowRightStartOnRectangleIcon, TagIcon, BuildingStorefrontIcon, ClipboardDocumentListIcon, ChevronDownIcon, KeyIcon
    } from '@heroicons/vue/24/outline';

    const isSidebarOpen = ref(false);
    const page = usePage();
    const quickSearch = ref('');
    const quickSearchResults = ref([]);
    const quickSearchOpen = ref(false);
    const quickSearchLoading = ref(false);
    const notificationsOpen = ref(false);
    let quickSearchTimer = null;

    const currentUser = page.props.auth.user;
    const storedSection = (key, fallback) => typeof window === 'undefined' || localStorage.getItem(key) === null
        ? fallback
        : localStorage.getItem(key) === 'true';
    const miriExpanded = ref(storedSection('sidebar.miri.expanded', currentUser?.active_branch?.code === 'MIRI'));
    const klExpanded = ref(storedSection('sidebar.kl.expanded', currentUser?.active_branch?.code === 'KL-IT'));
    watch(miriExpanded, (value) => localStorage.setItem('sidebar.miri.expanded', String(value)));
    watch(klExpanded, (value) => localStorage.setItem('sidebar.kl.expanded', String(value)));
    const hasBranch = (code) => currentUser?.branches?.some((branch) => branch.code === code);
    const openBranchRoute = (branchCode, routeName) => {
        const branch = currentUser?.branches?.find((entry) => entry.code === branchCode);
        if (!branch) return;
        if (currentUser?.active_branch?.id === branch.id) {
            router.visit(route(routeName));
            return;
        }
        router.patch(route('branches.activate'), { branch_id: branch.id }, {
            preserveScroll: true,
            onSuccess: () => router.visit(route(routeName)),
        });
    };
    const miriItems = [
        { name: 'Dashboard', icon: Squares2X2Icon, route: 'dashboard' },
        { name: 'Inventory Assistant', icon: ChatBubbleLeftRightIcon, route: 'assistant.index', can: 'assistant_read' },
        { name: 'Stock Items', icon: ArchiveBoxIcon, route: 'assets.index' },
        { name: 'Stock Movements', icon: TruckIcon, route: 'asset-movements.index', can: 'movements_read' },
        { name: 'Receive / Issue', icon: BuildingStorefrontIcon, route: 'asset-movements.create', can: 'movements_edit' },
        { name: 'Stocktakes', icon: ClipboardDocumentListIcon, route: 'stocktakes.index', can: 'movements_read' },
        { name: 'Stock Ledger', icon: ChartBarIcon, route: 'asset-ledger.index' },
        { name: 'COG Control', icon: ClipboardDocumentCheckIcon, route: 'cogs.index' },
        { name: 'Stock Anomalies', icon: ExclamationTriangleIcon, route: 'anomalies.index', can: 'anomalies_read' },
    ];
    const klItems = [
        { name: 'IT Dashboard', icon: Squares2X2Icon, route: 'it-assets.dashboard', can: 'it_assets_read' },
        { name: 'IT Asset Register', icon: ClipboardDocumentCheckIcon, route: 'it-assets.index', can: 'it_assets_read' },
        { name: 'IT Licence Register', icon: KeyIcon, route: 'it-licenses.index', can: 'it_assets_read' },
        { name: 'Import Assets', icon: ArchiveBoxIcon, route: 'it-assets.import.create', can: 'it_assets_edit' },
        { name: 'Assignments / Returns', icon: TruckIcon, route: 'it-assets.assignments', can: 'it_assets_read' },
        { name: 'Repairs', icon: ExclamationTriangleIcon, route: 'it-assets.repairs', can: 'it_assets_read' },
        { name: 'IT Asset Reports', icon: ChartBarIcon, route: 'it-assets.reports', can: 'it_assets_read' },
    ];
    const administrationItems = [
        { name: 'Categories', icon: TagIcon, route: 'categories.index' },
        { name: 'Locations', icon: MapIcon, route: 'locations.index' },
        { name: 'System Settings', icon: Cog6ToothIcon, route: 'settings.index', can: 'superadmin' },
        { name: 'Audit Trail', icon: ClipboardDocumentListIcon, route: 'audit-trail.index', can: 'superadmin' },
    ];
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
    const isSettingsRoute = computed(() => route().current('settings.index') || route().current('settings.*') || route().current('audit-trail.index'));

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
                            <div v-if="notificationsOpen" class="absolute right-0 top-full z-20 mt-2 w-[min(20rem,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-[#d8e7d4] bg-white shadow-[0_18px_45px_rgba(79,159,74,0.10)]">
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
    
                    <div class="flex-1 space-y-5 overflow-y-auto px-4 py-4">
                        <section v-if="hasBranch('MIRI')">
                            <button type="button" class="mb-2 flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-[10px] font-bold uppercase tracking-[0.24em] text-[#60745d] hover:bg-[#f2f8ef]" :aria-expanded="miriExpanded" @click="miriExpanded = !miriExpanded">
                                <span>Miri Inventory</span><ChevronDownIcon class="h-4 w-4 transition-transform" :class="miriExpanded ? 'rotate-180' : ''" />
                            </button>
                            <div v-show="miriExpanded" class="space-y-0.5">
                                <button v-for="item in miriItems.filter((entry) => !entry.can || currentUser?.can?.[entry.can])" :key="`miri-${item.name}`" type="button" @click="openBranchRoute('MIRI', item.route)" :class="currentUser?.active_branch?.code === 'MIRI' && route().current(item.route) ? 'bg-[linear-gradient(135deg,#6fbb68_0%,#4f9f4a_100%)] text-white shadow-md' : 'text-[#5f7b5e] hover:bg-[#eef8ea] hover:text-[#234222]'" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium transition">
                                    <component :is="item.icon" class="h-5 w-5 shrink-0 opacity-75" /><span class="min-w-0 truncate">{{ item.name }}</span>
                                </button>
                            </div>
                        </section>

                        <section v-if="hasBranch('KL-IT')">
                            <button type="button" class="mb-2 flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-[10px] font-bold uppercase tracking-[0.24em] text-[#60745d] hover:bg-[#f2f8ef]" :aria-expanded="klExpanded" @click="klExpanded = !klExpanded">
                                <span>KL IT Inventory</span><ChevronDownIcon class="h-4 w-4 transition-transform" :class="klExpanded ? 'rotate-180' : ''" />
                            </button>
                            <div v-show="klExpanded" class="space-y-0.5">
                                <button v-for="item in klItems.filter((entry) => !entry.can || currentUser?.can?.[entry.can])" :key="`kl-${item.name}`" type="button" @click="openBranchRoute('KL-IT', item.route)" :class="currentUser?.active_branch?.code === 'KL-IT' && route().current(item.route) ? 'bg-[linear-gradient(135deg,#6fbb68_0%,#4f9f4a_100%)] text-white shadow-md' : 'text-[#5f7b5e] hover:bg-[#eef8ea] hover:text-[#234222]'" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium transition">
                                    <component :is="item.icon" class="h-5 w-5 shrink-0 opacity-75" /><span class="min-w-0 truncate">{{ item.name }}</span>
                                </button>
                            </div>
                        </section>

                        <section>
                            <p class="mb-2 px-3 text-[10px] font-bold uppercase tracking-[0.24em] text-[#7f9a7a]">Administration</p>
                            <Link v-for="item in administrationItems.filter((entry) => !entry.can || currentUser?.can?.[entry.can])" :key="`admin-${item.name}`" :href="route(item.route)" :class="route().current(item.route) ? 'bg-[linear-gradient(135deg,#6fbb68_0%,#4f9f4a_100%)] text-white shadow-md' : 'text-[#5f7b5e] hover:bg-[#eef8ea] hover:text-[#234222]'" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition">
                                <component :is="item.icon" class="h-5 w-5 shrink-0 opacity-75" /><span class="min-w-0 truncate">{{ item.name }}</span>
                            </Link>
                        </section>
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
