<script setup>
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
    import { Head } from '@inertiajs/vue3';
    import { ref, computed } from 'vue';
    import { 
        TruckIcon, 
        CalendarIcon, 
        CheckCircleIcon,
        ArrowRightIcon,
        MapPinIcon,
        ChartBarIcon
    } from '@heroicons/vue/24/outline';
    
    // SECTION 1: IN TRANSIT (Real Data from Excel context)
    const activeMovements = ref([
        { 
            id: 'MOV-8821', 
            tag: 'DESB-K-CR-1129', // Added Tag No
            asset: 'Cylinder Rack 4x4x5', 
            from: 'Zone A', 
            to: 'Offshore Rig', 
            operator: 'Mike R.', 
            started: '10:45 AM',
            progress: 65,
            eta: '15 mins'
        },
        { 
            id: 'MOV-8822', 
            tag: 'DESB-HPU-005',
            asset: 'Hydraulic Power Unit', 
            from: 'Workshop', 
            to: 'Zone A', 
            operator: 'Ahmad Z.', 
            started: '11:10 AM',
            progress: 20,
            eta: '45 mins'
        }
    ]);
    
    // SECTION 2: PENDING (Scheduled moves for specific racks)
    const pendingMovements = ref([
        { 
            id: 'SCH-9001', 
            tag: 'DESB-K-CR-1335',
            asset: 'Cylinder Rack 4x4x5', 
            from: 'Zone B', 
            to: 'Inspection Bay', 
            scheduled: 'Today, 2:00 PM',
            priority: 'High'
        },
        { 
            id: 'SCH-9002', 
            tag: 'DESB-K-CR-1130',
            asset: 'Cylinder Rack (Damaged)', 
            from: 'Zone C', 
            to: 'Repair Shop', 
            scheduled: 'Tomorrow, 9:00 AM',
            priority: 'Normal'
        },
        { 
            id: 'SCH-9003', 
            tag: 'CNT-10-992',
            asset: 'Offshore Container 10ft', 
            from: 'Zone A', 
            to: 'Zone F (Surplus)', 
            scheduled: 'Tomorrow, 11:30 AM',
            priority: 'Normal'
        }
    ]);
    
    // SECTION 3: COMPLETED (History of received racks)
    const completedMovements = ref([
        { id: 'LOG-5501', tag: 'DESB-K-CR-1123', asset: 'Cylinder Rack 4x4x5', from: 'Offshore', to: 'Zone A', time: '10:30 AM', operator: 'Sarah L.' },
        { id: 'LOG-5502', tag: 'DESB-K-CR-1124', asset: 'Cylinder Rack 4x4x5', from: 'Supplier', to: 'Zone A', time: '09:15 AM', operator: 'System' },
        { id: 'LOG-5503', tag: 'DESB-K-CR-1127', asset: 'Cylinder Rack 4x4x5', from: 'Zone A', to: 'Zone B', time: 'Yesterday', operator: 'Mike R.' },
        { id: 'LOG-5504', tag: 'DESB-K-CR-1332', asset: 'Cylinder Rack 4x4x5', from: 'Zone B', to: 'Zone C', time: 'Yesterday', operator: 'Ahmad Z.' },
    ]);
    
    // Computed Totals for the Cards
    const totalActive = computed(() => activeMovements.value.length);
    const totalPending = computed(() => pendingMovements.value.length);
    const totalCompleted = computed(() => completedMovements.value.length);
    const totalToday = computed(() => totalActive.value + totalPending.value + 5); 
    </script>
    
    <template>
        <Head title="Movement Control" />
    
        <AuthenticatedLayout>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                <div>
                    <h2 class="text-3xl font-bold text-white">Movement <span class="text-orange-500">Control</span></h2>
                    <p class="text-slate-400 text-sm">Real-time tracking and scheduling.</p>
                </div>
                <button class="btn bg-orange-600 hover:bg-orange-700 text-white border-none normal-case gap-2 shadow-lg shadow-orange-900/20">
                    <CalendarIcon class="w-5 h-5" /> Schedule Move
                </button>
            </div>
    
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                
                <div class="bg-[#1e293b] border border-slate-700/50 p-4 rounded-2xl shadow-lg flex items-center gap-4 group hover:border-slate-500 transition-all">
                    <div class="w-12 h-12 rounded-xl bg-slate-800 flex items-center justify-center text-white group-hover:bg-white group-hover:text-slate-900 transition-colors">
                        <ChartBarIcon class="w-6 h-6" />
                    </div>
                    <div>
                        <p class="text-slate-400 text-xs font-medium uppercase tracking-wider">Total Operations</p>
                        <h3 class="text-2xl font-black text-white">{{ totalToday }}</h3>
                    </div>
                </div>
    
                <div class="bg-[#1e293b] border border-slate-700/50 p-4 rounded-2xl shadow-lg flex items-center gap-4 group hover:border-orange-500/50 transition-all relative overflow-hidden">
                    <div class="w-12 h-12 rounded-xl bg-orange-500/10 flex items-center justify-center text-orange-500 group-hover:bg-orange-500 group-hover:text-white transition-colors relative z-10">
                        <TruckIcon class="w-6 h-6" />
                    </div>
                    <div class="relative z-10">
                        <p class="text-slate-400 text-xs font-medium uppercase tracking-wider">In Transit</p>
                        <h3 class="text-2xl font-black text-white">{{ totalActive }}</h3>
                    </div>
                    <div class="absolute right-0 top-0 w-16 h-full bg-gradient-to-l from-orange-500/5 to-transparent"></div>
                </div>
    
                <div class="bg-[#1e293b] border border-slate-700/50 p-4 rounded-2xl shadow-lg flex items-center gap-4 group hover:border-blue-500/50 transition-all">
                    <div class="w-12 h-12 rounded-xl bg-blue-500/10 flex items-center justify-center text-blue-500 group-hover:bg-blue-500 group-hover:text-white transition-colors">
                        <CalendarIcon class="w-6 h-6" />
                    </div>
                    <div>
                        <p class="text-slate-400 text-xs font-medium uppercase tracking-wider">Scheduled</p>
                        <h3 class="text-2xl font-black text-white">{{ totalPending }}</h3>
                    </div>
                </div>
    
                <div class="bg-[#1e293b] border border-slate-700/50 p-4 rounded-2xl shadow-lg flex items-center gap-4 group hover:border-emerald-500/50 transition-all">
                    <div class="w-12 h-12 rounded-xl bg-emerald-500/10 flex items-center justify-center text-emerald-500 group-hover:bg-emerald-500 group-hover:text-white transition-colors">
                        <CheckCircleIcon class="w-6 h-6" />
                    </div>
                    <div>
                        <p class="text-slate-400 text-xs font-medium uppercase tracking-wider">Completed</p>
                        <h3 class="text-2xl font-black text-white">{{ totalCompleted }}</h3>
                    </div>
                </div>
    
            </div>
    
            <div class="mb-10">
                <h3 class="text-white font-bold mb-4 flex items-center gap-2">
                    <span class="relative flex h-3 w-3">
                      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-orange-400 opacity-75"></span>
                      <span class="relative inline-flex rounded-full h-3 w-3 bg-orange-500"></span>
                    </span>
                    Currently In Transit
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div v-for="item in activeMovements" :key="item.id" class="bg-[#1e293b] border border-orange-500/30 rounded-2xl p-6 relative overflow-hidden shadow-lg shadow-orange-900/10 group">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-orange-500/5 rounded-full blur-3xl group-hover:bg-orange-500/10 transition-all"></div>
    
                        <div class="flex justify-between items-start mb-4 relative z-10">
                            <div>
                                <span class="font-mono text-orange-400 font-bold text-lg block">{{ item.id }}</span>
                                <h4 class="text-white font-bold text-xl mt-1">{{ item.asset }}</h4>
                                <p class="text-xs text-slate-400 font-mono mt-0.5">{{ item.tag }}</p>
                            </div>
                            <div class="text-right">
                                <span class="text-xs text-slate-400 uppercase tracking-wide">ETA</span>
                                <p class="text-white font-mono font-bold">{{ item.eta }}</p>
                            </div>
                        </div>
    
                        <div class="relative z-10 mb-6">
                            <div class="flex justify-between text-xs text-slate-400 mb-2 font-medium">
                                <span>{{ item.from }}</span>
                                <TruckIcon class="w-5 h-5 text-orange-500 animate-bounce-x" />
                                <span>{{ item.to }}</span>
                            </div>
                            <div class="w-full bg-slate-700 h-2 rounded-full overflow-hidden">
                                <div class="bg-orange-500 h-full rounded-full relative" :style="`width: ${item.progress}%`">
                                    <div class="absolute right-0 top-0 bottom-0 w-2 bg-white/50 animate-pulse"></div>
                                </div>
                            </div>
                        </div>
    
                        <div class="flex items-center gap-3 relative z-10 border-t border-slate-700/50 pt-4">
                            <div class="w-8 h-8 rounded-full bg-slate-700 flex items-center justify-center text-xs text-white font-bold">
                                {{ item.operator.charAt(0) }}
                            </div>
                            <div class="text-sm">
                                <p class="text-slate-300">Operator: <span class="text-white">{{ item.operator }}</span></p>
                                <p class="text-xs text-slate-500">Started at {{ item.started }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
                
                <div class="xl:col-span-2">
                    <h3 class="text-white font-bold mb-4 flex items-center gap-2">
                        <CalendarIcon class="w-5 h-5 text-blue-400" />
                        Scheduled / Pending
                    </h3>
    
                    <div class="space-y-3">
                        <div v-for="item in pendingMovements" :key="item.id" class="bg-[#1e293b] border border-slate-700/50 rounded-xl p-4 flex items-center gap-4 hover:border-blue-500/30 transition-colors group">
                            
                            <div class="flex-shrink-0 w-24 bg-[#0f172a] rounded-lg py-2 text-center border border-slate-700 group-hover:border-blue-500/30">
                                <span class="block text-xs text-slate-500 font-mono">ID</span>
                                <span class="block text-sm font-bold text-blue-400 font-mono">{{ item.id }}</span>
                            </div>
    
                            <div class="flex-1">
                                <h4 class="text-white font-medium">{{ item.asset }}</h4>
                                 <p class="text-xs text-slate-500 font-mono">{{ item.tag }}</p>
                                
                                <div class="flex items-center gap-2 text-xs text-slate-400 mt-2">
                                    <span>{{ item.from }}</span>
                                    <ArrowRightIcon class="w-3 h-3" />
                                    <span>{{ item.to }}</span>
                                </div>
                            </div>
    
                            <div class="text-right">
                                <div class="text-sm text-white font-medium mb-1">{{ item.scheduled }}</div>
                                <span v-if="item.priority === 'High'" class="text-[10px] bg-red-500/10 text-red-400 px-2 py-0.5 rounded border border-red-500/20 font-bold uppercase">High Priority</span>
                                <span v-else class="text-[10px] bg-slate-700 text-slate-300 px-2 py-0.5 rounded">Standard</span>
                            </div>
                        </div>
                    </div>
                </div>
    
                <div class="xl:col-span-1">
                    <h3 class="text-white font-bold mb-4 flex items-center gap-2">
                        <CheckCircleIcon class="w-5 h-5 text-emerald-400" />
                        Recently Completed
                    </h3>
    
                    <div class="bg-[#1e293b] border border-slate-700/50 rounded-2xl overflow-hidden shadow-lg">
                        <table class="w-full text-left text-sm">
                            <tbody class="divide-y divide-slate-700/50">
                                <tr v-for="item in completedMovements" :key="item.id" class="hover:bg-slate-800/50 transition-colors">
                                    <td class="p-4">
                                        <div class="flex justify-between items-start mb-1">
                                            <span class="text-xs font-mono text-emerald-500">{{ item.id }}</span>
                                            <span class="text-[10px] text-slate-500">{{ item.time }}</span>
                                        </div>
                                        <p class="text-slate-300 font-medium text-xs mb-0.5">{{ item.asset }}</p>
                                        <p class="text-[10px] text-slate-500 font-mono mb-1">{{ item.tag }}</p>
                                        
                                        <div class="flex items-center gap-1 text-[10px] text-slate-500">
                                            <MapPinIcon class="w-3 h-3" />
                                            <span>To: {{ item.to }}</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="p-3 text-center border-t border-slate-700/50 bg-[#0f172a]/50">
                            <button class="text-xs text-slate-400 hover:text-white transition-colors">View Full History</button>
                        </div>
                    </div>
                </div>
    
            </div>
    
        </AuthenticatedLayout>
    </template>
    
    <style scoped>
    @keyframes bounce-x {
      0%, 100% { transform: translateX(-25%); animation-timing-function: cubic-bezier(0.8, 0, 1, 1); }
      50% { transform: translateX(0); animation-timing-function: cubic-bezier(0, 0, 0.2, 1); }
    }
    .animate-bounce-x {
      animation: bounce-x 1s infinite;
    }
    </style>