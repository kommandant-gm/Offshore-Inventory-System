<script setup>
    import { ref } from 'vue';
    import { Link } from '@inertiajs/vue3'; // <--- 1. Import Link
    import { 
        Squares2X2Icon, ArchiveBoxIcon, MapIcon, TruckIcon, 
        ShieldCheckIcon, ClipboardDocumentCheckIcon, ChartBarIcon, 
        Cog6ToothIcon, BellIcon, MagnifyingGlassIcon, 
        Bars3Icon, QuestionMarkCircleIcon, ArrowRightStartOnRectangleIcon
    } from '@heroicons/vue/24/outline';
    
    const isSidebarOpen = ref(false);
    
    // 2. Updated navItems with 'route' keys matching your web.php
    const navItems = [
        { name: 'Dashboard', icon: Squares2X2Icon, route: 'dashboard' },
        { name: 'Asset Master', icon: ArchiveBoxIcon, route: 'asset.master' }, // Placeholder
        { name: 'Yard Locations', icon: MapIcon, route: 'yard.locations' }, // <--- Your new page
        { name: 'Movements', icon: TruckIcon, route: 'movements' },
        { name: 'Compliance', icon: ShieldCheckIcon, route: null },
        { name: 'Stock Audit', icon: ClipboardDocumentCheckIcon, route: null },
        { name: 'Reports', icon: ChartBarIcon, route: null },
    ];
    </script>
    
    <template>
        <div class="drawer lg:drawer-open font-sans antialiased bg-[#0f172a] text-slate-300">
            <input id="my-drawer-2" type="checkbox" class="drawer-toggle" v-model="isSidebarOpen" />
            
            <div class="drawer-content flex flex-col h-screen overflow-hidden relative">
                
                <div class="w-full navbar bg-[#0f172a]/90 backdrop-blur-md sticky top-0 z-30 border-b border-slate-700/50 h-20 px-4 sm:px-8">
                    <div class="flex-none lg:hidden mr-4">
                        <label for="my-drawer-2" class="btn btn-square btn-ghost text-white hover:bg-slate-800">
                            <Bars3Icon class="w-6 h-6" />
                        </label>
                    </div>
                    
                    <div class="flex-1">
                        <h2 class="text-xl font-bold text-white tracking-wide">Dayang Inventory Dashboard</h2>
                    </div>
    
                    <div class="flex items-center gap-4">
                        <div class="hidden md:flex relative group">
                            <MagnifyingGlassIcon class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-orange-500 transition-colors" />
                            <input type="text" placeholder="Search item, ID, or zone..." class="input input-sm h-10 bg-[#1e293b] border border-slate-700/50 focus:border-orange-500 focus:outline-none rounded-lg pl-10 w-64 text-sm text-white placeholder:text-slate-500 transition-all shadow-inner" />
                        </div>
    
                        <button class="btn btn-circle btn-sm bg-[#1e293b] border-slate-700/50 hover:bg-slate-700 hover:border-orange-500/50 text-white relative transition-all shadow-lg">
                            <BellIcon class="w-5 h-5" />
                            <span class="absolute top-0 right-0 w-2.5 h-2.5 bg-orange-500 rounded-full border-2 border-[#0f172a] animate-pulse"></span>
                        </button>
    
                        <button class="btn btn-circle btn-sm bg-[#1e293b] border-slate-700/50 hover:bg-slate-700 text-white shadow-lg">
                            <QuestionMarkCircleIcon class="w-5 h-5" />
                        </button>
                    </div>
                </div>
    
                <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 space-y-6 scrollbar-thin scrollbar-thumb-slate-700 scrollbar-track-transparent">
                    <slot />
                </main>
            </div> 
            
            <div class="drawer-side z-40">
                <label for="my-drawer-2" class="drawer-overlay bg-[#0f172a]/80"></label> 
                <aside class="w-72 bg-[#1e293b] h-full flex flex-col border-r border-slate-700/50 shadow-2xl">
                    
                    <div class="h-24 flex items-center gap-3 px-6 bg-[#1e293b]">
                        <div class="w-10 h-10 bg-gradient-to-br from-orange-500 to-orange-700 rounded-xl flex items-center justify-center text-white shadow-lg shadow-orange-900/30 ring-1 ring-white/10">
                            <span class="font-bold text-xl">D</span>
                        </div>
                        <div>
                            <h1 class="font-bold text-base text-white tracking-wide leading-tight">Dayang <span class="text-orange-500">Offshore</span></h1>
                            <p class="text-[10px] text-slate-400 font-medium tracking-widest uppercase">Inventory v2.0</p>
                        </div>
                    </div>
    
                    <div class="flex-1 overflow-y-auto py-4 px-4 space-y-1">
                        <Link v-for="item in navItems" :key="item.name" 
                            :href="item.route ? route(item.route) : '#'" 
                            :class="[
                                item.route && route().current(item.route)
                                ? 'bg-gradient-to-r from-orange-600 to-orange-500 text-white shadow-lg shadow-orange-500/20 ring-1 ring-white/10' 
                                : 'text-slate-400 hover:bg-slate-800 hover:text-white'
                            ]" 
                            class="flex items-center gap-3 px-4 py-3.5 rounded-xl text-sm font-medium transition-all duration-200 group"
                        >
                            <component :is="item.icon" class="w-5 h-5" :class="item.route && route().current(item.route) ? 'opacity-100' : 'opacity-70 group-hover:opacity-100'" />
                            {{ item.name }}
                        </Link>
                    </div>
    
                    <div class="px-4 pb-4 border-t border-slate-700/50 pt-4 bg-[#1e293b]">
                         <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                            <Cog6ToothIcon class="w-5 h-5 opacity-70" />
                            Settings
                        </a>
                    </div>
    
                    <div class="p-4 bg-[#1e293b]">
                        <div class="flex items-center gap-3 p-3 rounded-xl bg-slate-900/50 border border-slate-700/50 hover:border-orange-500/30 hover:bg-slate-900 transition-all cursor-pointer group">
                            <div class="avatar">
                                <div class="w-10 rounded-full ring ring-slate-700 group-hover:ring-orange-500/50 transition-all">
                                    <img src="https://ui-avatars.com/api/?name=Aniq+Aiman&background=fb923c&color=fff&bold=true" alt="Aniq" />
                                </div>
                                <span class="absolute bottom-0 right-0 w-3 h-3 bg-emerald-500 border-2 border-slate-900 rounded-full"></span>
                            </div>
                            <div class="flex-1 overflow-hidden">
                                <p class="text-sm font-bold text-white truncate group-hover:text-orange-500 transition-colors">Aniq Aiman</p>
                                <p class="text-[11px] text-slate-400 truncate">Yard Manager</p>
                            </div>
                            
                            <Link :href="route('logout')" method="post" as="button" class="group/logout">
                                <ArrowRightStartOnRectangleIcon class="w-5 h-5 text-slate-500 group-hover/logout:text-red-400 transition-colors" />
                            </Link>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </template>