<script setup>
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
    import { Head } from '@inertiajs/vue3';
    import { 
        PlusIcon, ArrowDownTrayIcon, EllipsisHorizontalIcon,
        PlayIcon, TruckIcon, QrCodeIcon, DocumentTextIcon, WrenchScrewdriverIcon,
        ArchiveBoxIcon // Added for Racks
    } from '@heroicons/vue/24/outline';
    import { ExclamationTriangleIcon, CheckBadgeIcon } from '@heroicons/vue/24/solid';
    
    // Mock Data with REAL EXCEL CONTEXT
    const recentMovements = [
        { 
            time: '10:45 AM', 
            title: 'DESB-K-CR-1129 Moved to Offshore', 
            operator: 'Operator: Ahmad Z.', 
            badge: 'Deployed', 
            color: 'bg-blue-500/10 text-blue-400 border-blue-500/20' 
        },
        { 
            time: '09:30 AM', 
            title: 'DESB-HPU-005 Check-Out', 
            operator: 'Crew: Logistics Team', 
            badge: 'Out', 
            color: 'bg-orange-500/10 text-orange-400 border-orange-500/20' 
        },
        { 
            time: '08:15 AM', 
            title: 'DESB-K-CR-1130 Marked Damaged', 
            operator: 'Inspector: Mike R.', 
            badge: 'Critical', 
            color: 'bg-red-500/10 text-red-400 border-red-500/20' 
        },
        { 
            time: '07:50 AM', 
            title: 'Morning Gate Log', 
            operator: 'System Log', 
            badge: 'System', 
            color: 'bg-slate-700 text-slate-300 border-slate-600' 
        },
    ];
    
    const incomingTools = [
        { 
            name: 'Cylinder Rack 4x4x5', 
            id: 'DESB-K-CR-1336', 
            source: 'Supplier HQ', 
            status: 'In Transit', 
            statusColor: 'text-orange-400 bg-orange-500/10 border-orange-500/20', 
            eta: '14:00 Today' 
        },
        { 
            name: 'Offshore Container 10ft', 
            id: 'CNT-10-992', 
            source: 'Rig Platform A', 
            status: 'Arrived', 
            statusColor: 'text-emerald-400 bg-emerald-500/10 border-emerald-500/20', 
            eta: '09:00 Today' 
        },
        { 
            name: 'Hydraulic Power Unit', 
            id: 'DESB-HPU-002', 
            source: 'Maintenance', 
            status: 'Delayed', 
            statusColor: 'text-red-400 bg-red-500/10 border-red-500/20', 
            eta: 'Pending' 
        },
    ];
    </script>
    
    <template>
        <Head title="Dashboard" />
    
        <AuthenticatedLayout>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                <div>
                    <p class="text-slate-400 text-sm font-medium mb-1">Overview</p>
                    <h2 class="text-3xl font-bold text-white">Welcome back, <span class="text-orange-500">Aniq Aiman</span></h2>
                </div>
                <div class="flex items-center gap-3">
                    <button class="btn bg-orange-600 hover:bg-orange-700 text-white border-none gap-2 px-6 normal-case font-bold rounded-lg shadow-lg shadow-orange-900/20 transition-all hover:scale-105">
                        <PlusIcon class="w-5 h-5" /> New Transaction
                    </button>
                    <button class="btn bg-[#1e293b] border-slate-600 text-slate-300 hover:bg-slate-800 hover:text-white gap-2 px-6 normal-case font-medium rounded-lg">
                        <ArrowDownTrayIcon class="w-5 h-5" /> Export Report
                    </button>
                </div>
            </div>
    
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
                <div class="bg-[#1e293b] p-6 rounded-2xl border border-slate-700/50 relative overflow-hidden group hover:border-red-500/30 transition-all shadow-lg">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <h4 class="text-slate-400 text-sm font-medium">Damaged / Critical</h4>
                            <div class="flex items-baseline gap-2 mt-2">
                                <span class="text-4xl font-bold text-white">12</span>
                                <span class="bg-red-500/10 text-red-400 text-[10px] font-bold px-2 py-0.5 rounded border border-red-500/20">Action Req</span>
                            </div>
                        </div>
                        <ExclamationTriangleIcon class="w-14 h-14 text-red-500/10 absolute -right-2 -top-2" />
                    </div>
                    <div class="w-full bg-slate-700 h-1.5 rounded-full mt-4 mb-2 overflow-hidden">
                        <div class="bg-red-500 h-1.5 rounded-full" style="width: 35%"></div>
                    </div>
                    <p class="text-[11px] text-slate-400">3 items marked "Damaged" today</p>
                </div>
    
                <div class="bg-[#1e293b] p-6 rounded-2xl border border-slate-700/50 hover:border-orange-500/30 transition-all shadow-lg">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <h4 class="text-slate-400 text-sm font-medium">Total Asset Value</h4>
                            <div class="flex items-center gap-3 mt-2">
                                <span class="text-4xl font-bold text-white">RM 4.2M</span>
                                <span class="bg-emerald-500/10 text-emerald-400 text-[10px] font-bold px-2 py-0.5 rounded border border-emerald-500/20 leading-tight">+5%</span>
                            </div>
                        </div>
                    </div>
                    <div class="w-full bg-slate-700 h-1.5 rounded-full mt-4 mb-2 overflow-hidden">
                        <div class="bg-gradient-to-r from-orange-600 to-orange-400 h-1.5 rounded-full" style="width: 70%"></div>
                    </div>
                    <p class="text-[11px] text-slate-400">1,240 Total Assets Tracked</p>
                </div>
    
                <div class="bg-[#1e293b] p-6 rounded-2xl border border-slate-700/50 relative overflow-hidden hover:border-amber-500/30 transition-all shadow-lg">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <h4 class="text-slate-400 text-sm font-medium">Pending Certificates</h4>
                            <div class="flex items-baseline gap-2 mt-2">
                                <span class="text-4xl font-bold text-white">5</span>
                                <span class="bg-amber-500/10 text-amber-400 text-[10px] font-bold px-2 py-0.5 rounded border border-amber-500/20">Renew</span>
                            </div>
                        </div>
                        <CheckBadgeIcon class="w-14 h-14 text-amber-500/10 absolute -right-2 -top-2" />
                    </div>
                    <div class="w-full bg-slate-700 h-1.5 rounded-full mt-4 mb-2 overflow-hidden">
                        <div class="bg-amber-500 h-1.5 rounded-full" style="width: 45%"></div>
                    </div>
                    <p class="text-[11px] text-slate-400">2 Expiring in &lt; 7 days</p>
                </div>
    
                <div class="bg-[#1e293b] p-6 rounded-2xl border border-slate-700/50 relative overflow-hidden hover:border-orange-500/30 transition-all shadow-lg">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <h4 class="text-slate-400 text-sm font-medium">Incoming Shipment</h4>
                            <div class="flex items-baseline gap-2 mt-2">
                                <span class="text-4xl font-bold text-white">28</span>
                                <span class="bg-orange-500/10 text-orange-400 text-[10px] font-bold px-2 py-0.5 rounded border border-orange-500/20">Items</span>
                            </div>
                        </div>
                        <TruckIcon class="w-14 h-14 text-orange-500/10 absolute -right-2 -top-2" />
                    </div>
                    <div class="w-full bg-slate-700 h-1.5 rounded-full mt-4 mb-2 overflow-hidden">
                        <div class="bg-white h-1.5 rounded-full" style="width: 60%"></div>
                    </div>
                    <p class="text-[11px] text-slate-400">Next arrival: 14:00 PM</p>
                </div>
            </div>
    
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-8">
                
                <div class="xl:col-span-2 bg-[#1e293b] border border-slate-700/50 rounded-2xl p-6 shadow-xl">
                    <div class="flex justify-between items-center mb-4">
                        <div class="flex items-center gap-3">
                            <span class="relative flex h-3 w-3">
                              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-orange-400 opacity-75"></span>
                              <span class="relative inline-flex rounded-full h-3 w-3 bg-orange-500"></span>
                            </span>
                            <h3 class="font-bold text-white">CCTV Live Feed - TKY Yard</h3>
                        </div>
                        <div class="join bg-[#0f172a] border border-slate-700/50 rounded-lg p-1">
                            <button class="join-item btn btn-xs bg-orange-600 text-white border-none hover:bg-orange-700">Cam 1</button>
                            <button class="join-item btn btn-xs btn-ghost text-slate-400 hover:text-white">Cam 2</button>
                            <button class="join-item btn btn-xs btn-ghost text-slate-400 hover:text-white">Cam 3</button>
                        </div>
                    </div>
    
                    <div class="relative w-full aspect-video bg-black rounded-xl overflow-hidden group border border-slate-700/50 shadow-2xl">
                        <img src="https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?q=80&w=2670&auto=format&fit=crop" class="w-full h-full object-cover opacity-60" alt="Warehouse">
                        
                        <div class="absolute top-4 left-4 bg-black/60 border border-white/10 px-3 py-1 rounded text-xs font-mono text-white flex items-center gap-2 backdrop-blur-sm">
                            <span class="text-orange-500 font-bold">REC</span> 
                            <span class="w-1.5 h-1.5 bg-red-500 rounded-full animate-pulse"></span> 
                            <span>10:42:15 AM</span>
                        </div>
    
                        <div class="absolute inset-0 flex items-center justify-center">
                            <button class="w-16 h-16 rounded-full bg-orange-500/20 backdrop-blur-sm flex items-center justify-center border border-orange-500/50 hover:scale-110 hover:bg-orange-500 hover:border-orange-500 transition-all duration-300 group/play">
                                <PlayIcon class="w-8 h-8 text-white ml-1 group-hover/play:text-black" />
                            </button>
                        </div>
    
                        <div class="absolute bottom-0 left-0 right-0 h-1 bg-white/10">
                            <div class="h-full bg-orange-500 w-2/3 shadow-[0_0_10px_rgba(249,115,22,0.5)]"></div>
                        </div>
                    </div>
                </div>
    
                <div class="xl:col-span-1 bg-[#1e293b] border border-slate-700/50 rounded-2xl p-6 flex flex-col shadow-xl">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-bold text-white">Recent Movements</h3>
                        <button class="text-slate-500 hover:text-white"><EllipsisHorizontalIcon class="w-6 h-6"/></button>
                    </div>
    
                    <div class="relative pl-2 flex-1 overflow-y-auto pr-2 custom-scrollbar">
                        <div class="absolute left-2 top-2 bottom-0 w-px bg-slate-700"></div>
    
                        <div v-for="(item, index) in recentMovements" :key="index" class="relative pl-8 mb-8 last:mb-0 group">
                            <div class="absolute left-0 top-1.5 w-4 h-4 rounded-full border-2 border-[#1e293b] z-10" :class="index === 0 ? 'bg-orange-500 shadow-[0_0_8px_rgba(249,115,22,0.6)]' : 'bg-slate-600'"></div>
                            
                            <span class="text-xs font-mono text-slate-500 mb-1 block">{{ item.time }}</span>
                            <h4 class="text-sm font-semibold text-white group-hover:text-orange-400 transition-colors">{{ item.title }}</h4>
                            <p class="text-xs text-slate-400 mt-1">{{ item.operator }}</p>
                            <span :class="`inline-block mt-2 text-[10px] px-2 py-0.5 rounded border ${item.color}`">
                                {{ item.badge }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
    
            <div class="grid grid-cols-1 xl:grid-cols-4 gap-6">
                
                <div class="xl:col-span-3 bg-[#1e293b] border border-slate-700/50 rounded-2xl p-6 shadow-xl">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-bold text-white">Incoming Assets</h3>
                        <button class="text-sm text-orange-500 hover:text-orange-400 font-medium">View All</button>
                    </div>
    
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="text-slate-500 border-b border-slate-700/50 text-xs uppercase tracking-wider">
                                    <th class="pb-3 font-medium pl-2">Asset Name</th>
                                    <th class="pb-3 font-medium">Tag No</th>
                                    <th class="pb-3 font-medium">Source</th>
                                    <th class="pb-3 font-medium">Status</th>
                                    <th class="pb-3 font-medium text-right pr-2">ETA</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/30">
                                <tr v-for="tool in incomingTools" :key="tool.id" class="group hover:bg-slate-800 transition-colors">
                                    <td class="py-4 pl-2">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded bg-slate-800 flex items-center justify-center group-hover:bg-orange-500/20 group-hover:text-orange-500 transition-colors">
                                                <ArchiveBoxIcon class="w-4 h-4 text-slate-400 group-hover:text-orange-500" />
                                            </div>
                                            <span class="font-medium text-white">{{ tool.name }}</span>
                                        </div>
                                    </td>
                                    <td class="py-4 text-slate-400 font-mono text-xs">{{ tool.id }}</td>
                                    <td class="py-4 text-slate-300">{{ tool.source }}</td>
                                    <td class="py-4">
                                        <span :class="`px-2.5 py-1 rounded-full text-xs font-bold border ${tool.statusColor}`">
                                            {{ tool.status }}
                                        </span>
                                    </td>
                                    <td class="py-4 text-right text-white font-medium pr-2">{{ tool.eta }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
    
                <div class="xl:col-span-1 grid grid-cols-2 gap-4 h-fit">
                    <button class="bg-[#1e293b] hover:bg-[#253045] border border-slate-700/50 aspect-square rounded-2xl flex flex-col items-center justify-center gap-3 group transition-all hover:border-orange-500/30 shadow-lg">
                        <div class="w-12 h-12 rounded-full bg-orange-500/10 text-orange-500 flex items-center justify-center group-hover:bg-orange-500 group-hover:text-white transition-all shadow-lg shadow-orange-900/10">
                            <QrCodeIcon class="w-6 h-6" />
                        </div>
                        <span class="text-sm font-medium text-slate-300 group-hover:text-white">Scan Asset</span>
                    </button>
    
                    <button class="bg-[#1e293b] hover:bg-[#253045] border border-slate-700/50 aspect-square rounded-2xl flex flex-col items-center justify-center gap-3 group transition-all hover:border-blue-500/30 shadow-lg">
                        <div class="w-12 h-12 rounded-full bg-blue-500/10 text-blue-500 flex items-center justify-center group-hover:bg-blue-500 group-hover:text-white transition-all">
                            <DocumentTextIcon class="w-6 h-6" />
                        </div>
                        <span class="text-sm font-medium text-slate-300 group-hover:text-white">Permit Log</span>
                    </button>
                </div>
    
            </div>
        </AuthenticatedLayout>
    </template>
    
    <style scoped>
    /* Stealth Scrollbar for the timeline */
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