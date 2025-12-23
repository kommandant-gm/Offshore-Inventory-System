<script setup>
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
    import { Head } from '@inertiajs/vue3';
    import { ref, computed } from 'vue';
    import { 
        MapIcon, 
        CubeIcon, 
        MagnifyingGlassIcon, 
        FunnelIcon,
        ChevronRightIcon,
        CpuChipIcon,
        BoltIcon,
        WrenchScrewdriverIcon,
        ExclamationCircleIcon,
        ArchiveBoxIcon
    } from '@heroicons/vue/24/outline';
    import { CheckCircleIcon, MapPinIcon } from '@heroicons/vue/24/solid';
    
    // Mock Data integrated with EXCEL DATA
    const zones = ref([
        { 
            id: 'Z-01', 
            name: 'Zone A - TKY Main Yard', 
            capacity: 85, 
            items: 12, 
            total_slots: 50,
            status: 'Operational',
            manager: 'Ahmad Z.',
            type: 'Storage Area',
            inventory: [
                { 
                    name: 'CYLINDER RACK 4 X 4 X 5 (FT)', 
                    id: 'DESB-K-CR-1123', 
                    serial: '2011', 
                    type: 'Rack', 
                    status: 'Available' 
                },
                { 
                    name: 'CYLINDER RACK 4 X 4 X 5 (FT)', 
                    id: 'DESB-K-CR-1124', 
                    serial: '2011', 
                    type: 'Rack', 
                    status: 'Available' 
                },
                { 
                    name: 'CYLINDER RACK 4 X 4 X 5 (FT)', 
                    id: 'DESB-K-CR-1127', 
                    serial: '2011', 
                    type: 'Rack', 
                    status: 'Available' 
                },
                { 
                    name: 'CYLINDER RACK 4 X 4 X 5 (FT)', 
                    id: 'DESB-K-CR-1332', 
                    serial: '2013', 
                    type: 'Rack', 
                    status: 'Available' 
                },
                 { 
                    name: 'CYLINDER RACK 4 X 4 X 5 (FT)', 
                    id: 'DESB-K-CR-1336', 
                    serial: '2013', 
                    type: 'Rack', 
                    status: 'Available' 
                },
            ]
        },
        { 
            id: 'Z-02', 
            name: 'Zone B - Out / Deployed', 
            capacity: 45, 
            items: 3, 
            total_slots: 100,
            status: 'Operational',
            manager: 'Logistics',
            type: 'Offshore',
            inventory: [
                { 
                    name: 'CYLINDER RACK 4 X 4 X 5 (FT)', 
                    id: 'DESB-K-CR-1129', 
                    serial: '2011', 
                    type: 'Rack', 
                    status: 'Out' // Based on Excel "OUT" column
                },
                { 
                    name: 'CYLINDER RACK 4 X 4 X 5 (FT)', 
                    id: 'DESB-K-CR-1333', 
                    serial: '2013', 
                    type: 'Rack', 
                    status: 'Out' 
                },
                { 
                    name: 'CYLINDER RACK 4 X 4 X 5 (FT)', 
                    id: 'DESB-K-CR-1335', 
                    serial: '2013', 
                    type: 'Rack', 
                    status: 'Out' 
                },
            ]
        },
        { 
            id: 'Z-03', 
            name: 'Zone C - Repair Bay', 
            capacity: 92, 
            items: 1, 
            total_slots: 20,
            status: 'Critical',
            manager: 'Mike R.',
            type: 'Maintenance',
            inventory: [
                { 
                    name: 'CYLINDER RACK 4 X 4 X 5 (FT)', 
                    id: 'DESB-K-CR-1130', 
                    serial: '2011', 
                    type: 'Rack', 
                    status: 'Damaged' // Based on Excel "DAMAGED" column
                },
            ]
        },
        { 
            id: 'Z-04', 
            name: 'Zone D - Transit Bay', 
            capacity: 15, 
            items: 0, 
            total_slots: 20,
            status: 'Operational',
            manager: 'System',
            type: 'Loading Bay',
            inventory: []
        },
        { 
            id: 'Z-05', 
            name: 'Zone E - Disposed', 
            capacity: 60, 
            items: 0, 
            total_slots: 100,
            status: 'Maintenance',
            manager: 'Farid K.',
            type: 'Scrap Yard',
            inventory: []
        },
        { 
            id: 'Z-06', 
            name: 'Zone F - Surplus', 
            capacity: 5, 
            items: 0, 
            total_slots: 200,
            status: 'Operational',
            manager: 'Ahmad Z.',
            type: 'Open Yard',
            inventory: []
        },
    ]);
    
    // State for interaction
    const selectedZone = ref(zones.value[0]);
    const searchQuery = ref('');
    
    // Helper to calculate progress bar color
    const getProgressColor = (percent) => {
        if (percent >= 90) return 'bg-red-500';
        if (percent >= 70) return 'bg-orange-500';
        return 'bg-emerald-500';
    };
    
    // Filtered Zones based on search
    const filteredZones = computed(() => {
        return zones.value.filter(z => 
            z.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            z.id.toLowerCase().includes(searchQuery.value.toLowerCase())
        );
    });
    
    const selectZone = (zone) => {
        selectedZone.value = zone;
    };
    </script>
    
    <template>
        <Head title="Yard Locations" />
    
        <AuthenticatedLayout>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                <div>
                    <p class="text-slate-400 text-sm font-medium mb-1">Asset Master</p>
                    <h2 class="text-3xl font-bold text-white">Yard Locations & <span class="text-orange-500">Mapping</span></h2>
                </div>
                
                <div class="flex items-center gap-3 w-full md:w-auto">
                    <div class="relative w-full md:w-64">
                        <MagnifyingGlassIcon class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                        <input 
                            v-model="searchQuery"
                            type="text" 
                            placeholder="Search zone ID or name..." 
                            class="input input-bordered w-full bg-[#1e293b] border-slate-600 pl-10 text-white placeholder-slate-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 rounded-lg"
                        />
                    </div>
                    <button class="btn btn-square bg-[#1e293b] border-slate-600 text-slate-300 hover:text-white hover:bg-slate-800">
                        <FunnelIcon class="w-5 h-5" />
                    </button>
                    <button class="btn bg-orange-600 hover:bg-orange-700 text-white border-none gap-2 px-6 normal-case font-bold rounded-lg shadow-lg shadow-orange-900/20">
                        <MapIcon class="w-5 h-5" /> Map View
                    </button>
                </div>
            </div>
    
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 h-[calc(100vh-220px)]">
                
                <div class="lg:col-span-2 flex flex-col gap-6">
                    <div class="grid grid-cols-3 gap-4">
                        <div class="bg-[#1e293b] p-4 rounded-xl border border-slate-700/50 flex items-center gap-4">
                            <div class="p-3 rounded-lg bg-blue-500/10 text-blue-500"><MapPinIcon class="w-6 h-6"/></div>
                            <div>
                                <p class="text-slate-400 text-xs">Total Zones</p>
                                <p class="text-xl font-bold text-white">6 Zones</p>
                            </div>
                        </div>
                        <div class="bg-[#1e293b] p-4 rounded-xl border border-slate-700/50 flex items-center gap-4">
                            <div class="p-3 rounded-lg bg-emerald-500/10 text-emerald-500"><CubeIcon class="w-6 h-6"/></div>
                            <div>
                                <p class="text-slate-400 text-xs">Total Capacity</p>
                                <p class="text-xl font-bold text-white">68% <span class="text-xs font-normal text-slate-500">Utilized</span></p>
                            </div>
                        </div>
                        <div class="bg-[#1e293b] p-4 rounded-xl border border-slate-700/50 flex items-center gap-4">
                            <div class="p-3 rounded-lg bg-orange-500/10 text-orange-500"><WrenchScrewdriverIcon class="w-6 h-6"/></div>
                            <div>
                                <p class="text-slate-400 text-xs">Maintenance</p>
                                <p class="text-xl font-bold text-white">1 Zone</p>
                            </div>
                        </div>
                    </div>
    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 overflow-y-auto pr-2 custom-scrollbar pb-4">
                        <div 
                            v-for="zone in filteredZones" 
                            :key="zone.id"
                            @click="selectZone(zone)"
                            class="bg-[#1e293b] border rounded-xl p-5 cursor-pointer transition-all duration-300 group relative overflow-hidden"
                            :class="selectedZone.id === zone.id 
                                ? 'border-orange-500 ring-1 ring-orange-500/50 shadow-lg shadow-orange-900/10' 
                                : 'border-slate-700/50 hover:border-slate-500 hover:bg-[#253045]'"
                        >
                            <div class="flex justify-between items-start mb-4 relative z-10">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-slate-800 flex items-center justify-center text-slate-300 font-bold border border-slate-700 group-hover:text-white group-hover:border-slate-500 transition-colors">
                                        {{ zone.id.split('-')[1] }}
                                    </div>
                                    <div>
                                        <h3 class="text-white font-bold text-sm group-hover:text-orange-400 transition-colors">{{ zone.name }}</h3>
                                        <p class="text-slate-400 text-xs">{{ zone.type }}</p>
                                    </div>
                                </div>
                                <span 
                                    class="px-2 py-1 rounded text-[10px] font-bold border uppercase"
                                    :class="zone.status === 'Operational' 
                                        ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' 
                                        : zone.status === 'Critical' 
                                        ? 'bg-red-500/10 text-red-400 border-red-500/20'
                                        : 'bg-amber-500/10 text-amber-400 border-amber-500/20'"
                                >
                                    {{ zone.status }}
                                </span>
                            </div>
    
                            <div class="relative z-10">
                                <div class="flex justify-between text-xs mb-1.5">
                                    <span class="text-slate-400">Capacity Usage</span>
                                    <span class="text-white font-mono">{{ zone.items }} / {{ zone.total_slots }}</span>
                                </div>
                                <div class="w-full bg-slate-700 h-2 rounded-full overflow-hidden">
                                    <div 
                                        class="h-full rounded-full transition-all duration-500"
                                        :class="getProgressColor(zone.capacity)"
                                        :style="`width: ${zone.capacity}%`"
                                    ></div>
                                </div>
                            </div>
    
                            <MapIcon class="absolute -bottom-4 -right-4 w-32 h-32 text-slate-800/50 group-hover:text-slate-700/50 transition-colors -rotate-12" />
                        </div>
                    </div>
                </div>
    
                <div class="lg:col-span-1 bg-[#1e293b] border border-slate-700/50 rounded-2xl flex flex-col shadow-xl overflow-hidden relative">
                    
                    <div class="p-6 border-b border-slate-700/50 bg-[#1e293b] relative z-20">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-orange-500 font-mono text-xs font-bold bg-orange-500/10 px-2 py-0.5 rounded border border-orange-500/20">{{ selectedZone.id }}</span>
                            <span class="w-1 h-1 bg-slate-500 rounded-full"></span>
                            <span class="text-slate-400 text-xs">Managed by {{ selectedZone.manager }}</span>
                        </div>
                        <h2 class="text-2xl font-bold text-white mb-4">{{ selectedZone.name }}</h2>
                        
                        <div class="grid grid-cols-2 gap-3">
                            <button class="btn btn-sm bg-orange-600 hover:bg-orange-700 text-white border-none w-full">
                                <CubeIcon class="w-4 h-4"/> Add Item
                            </button>
                            <button class="btn btn-sm btn-outline border-slate-600 text-slate-300 hover:text-white hover:bg-slate-700 w-full">
                                Edit Zone
                            </button>
                        </div>
                    </div>
    
                    <div class="flex-1 overflow-y-auto custom-scrollbar p-6 bg-[#0f172a]/30">
                        
                        <div class="flex gap-4 mb-6">
                            <div class="flex-1 bg-slate-800/50 rounded-lg p-3 border border-slate-700/50 text-center">
                                <span class="block text-2xl font-bold text-white">{{ selectedZone.items }}</span>
                                <span class="text-[10px] text-slate-400 uppercase tracking-wider">Items Stored</span>
                            </div>
                            <div class="flex-1 bg-slate-800/50 rounded-lg p-3 border border-slate-700/50 text-center">
                                <span class="block text-2xl font-bold text-white">{{ selectedZone.total_slots - selectedZone.items }}</span>
                                <span class="text-[10px] text-slate-400 uppercase tracking-wider">Free Slots</span>
                            </div>
                        </div>
    
                        <h3 class="text-sm font-bold text-white mb-4 flex items-center gap-2">
                            Current Inventory
                            <span class="bg-slate-700 text-slate-300 text-[10px] px-1.5 py-0.5 rounded-full">{{ selectedZone.inventory.length }}</span>
                        </h3>
    
                        <div class="space-y-3">
                            <div v-if="selectedZone.inventory.length === 0" class="text-center py-8 text-slate-500">
                                <CubeIcon class="w-12 h-12 mx-auto mb-2 opacity-20"/>
                                <p class="text-sm">No items in this zone.</p>
                            </div>
    
                            <div 
                                v-for="item in selectedZone.inventory" 
                                :key="item.id"
                                class="bg-[#1e293b] p-3 rounded-lg border border-slate-700/50 hover:border-orange-500/30 group transition-all"
                            >
                                <div class="flex justify-between items-start mb-2">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded bg-slate-800 flex items-center justify-center text-slate-400">
                                            <ArchiveBoxIcon v-if="item.type === 'Rack'" class="w-4 h-4"/>
                                            <BoltIcon v-else-if="item.type === 'Equipment'" class="w-4 h-4"/>
                                            <CubeIcon v-else class="w-4 h-4"/>
                                        </div>
                                        <div class="overflow-hidden">
                                            <h4 class="text-sm font-medium text-white group-hover:text-orange-400 transition-colors truncate w-40" :title="item.name">
                                                {{ item.name }}
                                            </h4>
                                            <p class="text-[10px] text-slate-500 font-mono">{{ item.id }}</p>
                                            <p class="text-[10px] text-slate-600">SN: {{ item.serial }}</p>
                                        </div>
                                    </div>
                                    <ChevronRightIcon class="w-4 h-4 text-slate-600 group-hover:text-white" />
                                </div>
                                <div class="flex items-center gap-2 mt-2">
                                    <span class="text-[10px] px-2 py-0.5 rounded bg-slate-800 text-slate-400 border border-slate-700">{{ item.type }}</span>
                                    <span 
                                        class="text-[10px] flex items-center gap-1" 
                                        :class="{
                                            'text-emerald-400': item.status === 'Available',
                                            'text-blue-400': item.status === 'Out',
                                            'text-red-400': item.status === 'Damaged'
                                        }"
                                    >
                                        <span 
                                            class="w-1.5 h-1.5 rounded-full" 
                                            :class="{
                                                'bg-emerald-500': item.status === 'Available',
                                                'bg-blue-500': item.status === 'Out',
                                                'bg-red-500': item.status === 'Damaged'
                                            }"
                                        ></span>
                                        {{ item.status }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
    
                    <div v-if="selectedZone.capacity > 90" class="p-4 bg-red-500/10 border-t border-red-500/20">
                        <div class="flex items-start gap-3">
                            <ExclamationCircleIcon class="w-5 h-5 text-red-400 shrink-0 mt-0.5"/>
                            <div>
                                <p class="text-sm font-bold text-red-400">Zone Critical!</p>
                                <p class="text-xs text-red-300/70 mt-1">This zone is near maximum capacity. Move items to Zone F.</p>
                            </div>
                        </div>
                    </div>
    
                </div>
            </div>
        </AuthenticatedLayout>
    </template>
    
    <style scoped>
    /* Stealth Scrollbar matching dashboard */
    .custom-scrollbar::-webkit-scrollbar {
        width: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: transparent;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background-color: #334155;
        border-radius: 20px;
    }
    </style>