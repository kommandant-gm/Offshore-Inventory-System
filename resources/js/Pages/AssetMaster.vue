<script setup>
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
    import { Head } from '@inertiajs/vue3';
    import { ref, computed } from 'vue';
    import { 
        MagnifyingGlassIcon, 
        CubeIcon, 
        MapPinIcon, 
        ClockIcon,
        ArrowDownTrayIcon,
        PrinterIcon,
        DocumentCheckIcon,
        ArchiveBoxIcon,
        WrenchScrewdriverIcon
    } from '@heroicons/vue/24/outline';
    import { CheckBadgeIcon, ExclamationTriangleIcon, CheckCircleIcon } from '@heroicons/vue/24/solid';
    
    // Mock Data
    const assets = ref([
        { 
            id: 1, 
            description: 'Cylinder Rack 4x4x5 (FT)', 
            tag_no: 'DESB-K-CR-1123', 
            brand: 'TechSteel',
            model: 'CR-Standard', 
            serial: '2011-BATCH-A',
            year: '2011',
            location: 'TKY Yard',
            status: 'Available',
            condition: 'Good',
            category: 'Racking',
            image: 'https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?auto=format&fit=crop&q=80&w=200',
            specs: { weight: '450kg', dims: '4ft x 4ft x 5ft', load: '2000kg' },
            cert: { number: 'CERT-2024-001', expiry: '12-May-2025', type: 'MPI Inspection' },
            history: [
                { date: '12-May-24', event: 'Returned from Offshore', user: 'Ahmad Z.' },
                { date: '10-Jan-24', event: 'Sent to Platform A', user: 'Logistics' }
            ]
        },
        { 
            id: 2, 
            description: 'Hydraulic Power Unit (HPU)', 
            tag_no: 'DESB-HPU-005', 
            brand: 'Caterpillar',
            model: 'C-500', 
            serial: 'CAT-99283',
            year: '2015',
            location: 'Offshore - Rig A',
            status: 'Out',
            condition: 'Operational',
            category: 'Machinery',
            image: 'https://images.unsplash.com/photo-1504917595217-d4dc5ebe6122?auto=format&fit=crop&q=80&w=200',
            specs: { weight: '1200kg', dims: '2m x 1.5m x 1.5m', load: 'N/A' },
            cert: { number: 'EXP-2023-99', expiry: '18-Dec-2024', type: 'Load Test' },
            history: [
                { date: '16-Aug-24', event: 'Deployed to Rig A', user: 'Sarah L.' }
            ]
        },
        { 
            id: 3, 
            description: 'Offshore Container 10ft', 
            tag_no: 'CNT-10-992', 
            brand: 'Swire',
            model: 'Mini-10', 
            serial: 'SW-221',
            year: '2013',
            location: 'TKY Yard',
            status: 'Damaged',
            condition: 'Critical',
            category: 'Container',
            image: 'https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?auto=format&fit=crop&q=80&w=200',
            specs: { weight: '2200kg', dims: '10ft x 8ft x 8ft', load: '10000kg' },
            cert: { number: 'FAIL-2024', expiry: 'Expired', type: 'Visual Inspection' },
            history: [
                { date: 'Today', event: 'Marked as Damaged', user: 'Inspector Mike' }
            ]
        },
    ]);
    
    const searchQuery = ref('');
    const selectedId = ref(assets.value[0].id);
    
    // Computed Properties for Filtering
    const filteredAssets = computed(() => {
        return assets.value.filter(a => 
            a.tag_no.toLowerCase().includes(searchQuery.value.toLowerCase()) || 
            a.description.toLowerCase().includes(searchQuery.value.toLowerCase())
        );
    });
    
    const selectedAsset = computed(() => {
        return assets.value.find(a => a.id === selectedId.value) || assets.value[0];
    });
    
    // Computed Properties for Stats Cards
    const totalAssets = computed(() => assets.value.length);
    const totalAvailable = computed(() => assets.value.filter(a => a.status === 'Available').length);
    const totalOut = computed(() => assets.value.filter(a => a.status === 'Out').length);
    const totalDamaged = computed(() => assets.value.filter(a => a.status === 'Damaged').length);
    
    // Helper functions
    const getStatusColor = (status) => {
        if (status === 'Available') return 'text-emerald-400 bg-emerald-500/10 border-emerald-500/20';
        if (status === 'Out') return 'text-blue-400 bg-blue-500/10 border-blue-500/20';
        if (status === 'Damaged') return 'text-red-400 bg-red-500/10 border-red-500/20';
        return 'text-slate-400 bg-slate-700';
    };
    </script>
    
    <template>
        <Head title="Asset Master" />
    
        <AuthenticatedLayout>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-white">Asset <span class="text-orange-500">Explorer</span></h2>
                    <p class="text-slate-400 text-sm">Manage inventory, certificates, and specifications.</p>
                </div>
                <button class="btn bg-orange-600 hover:bg-orange-700 text-white border-none gap-2 px-6 normal-case font-bold rounded-lg shadow-lg shadow-orange-900/20">
                    <CubeIcon class="w-5 h-5" /> New Asset
                </button>
            </div>
    
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                
                <div class="bg-[#1e293b] border border-slate-700/50 p-4 rounded-xl shadow-lg flex items-center gap-3">
                    <div class="p-3 bg-slate-800 rounded-lg text-white border border-slate-700">
                        <ArchiveBoxIcon class="w-6 h-6" />
                    </div>
                    <div>
                        <p class="text-slate-400 text-xs font-bold uppercase tracking-wider">Total Items</p>
                        <h3 class="text-2xl font-black text-white">{{ totalAssets }}</h3>
                    </div>
                </div>
    
                <div class="bg-[#1e293b] border border-slate-700/50 p-4 rounded-xl shadow-lg flex items-center gap-3">
                    <div class="p-3 bg-emerald-500/10 rounded-lg text-emerald-500 border border-emerald-500/20">
                        <CheckCircleIcon class="w-6 h-6" />
                    </div>
                    <div>
                        <p class="text-slate-400 text-xs font-bold uppercase tracking-wider">Available</p>
                        <h3 class="text-2xl font-black text-white">{{ totalAvailable }}</h3>
                    </div>
                </div>
    
                 <div class="bg-[#1e293b] border border-slate-700/50 p-4 rounded-xl shadow-lg flex items-center gap-3">
                    <div class="p-3 bg-blue-500/10 rounded-lg text-blue-500 border border-blue-500/20">
                        <MapPinIcon class="w-6 h-6" />
                    </div>
                    <div>
                        <p class="text-slate-400 text-xs font-bold uppercase tracking-wider">Deployed</p>
                        <h3 class="text-2xl font-black text-white">{{ totalOut }}</h3>
                    </div>
                </div>
    
                 <div class="bg-[#1e293b] border border-slate-700/50 p-4 rounded-xl shadow-lg flex items-center gap-3">
                    <div class="p-3 bg-red-500/10 rounded-lg text-red-500 border border-red-500/20">
                        <WrenchScrewdriverIcon class="w-6 h-6" />
                    </div>
                    <div>
                        <p class="text-slate-400 text-xs font-bold uppercase tracking-wider">Damaged</p>
                        <h3 class="text-2xl font-black text-white">{{ totalDamaged }}</h3>
                    </div>
                </div>
    
            </div>
    
            <div class="flex flex-col lg:flex-row h-[calc(100vh-280px)] gap-6">
                
                <div class="lg:w-1/3 bg-[#1e293b] border border-slate-700/50 rounded-2xl flex flex-col shadow-xl overflow-hidden">
                    
                    <div class="p-4 border-b border-slate-700/50 bg-[#1e293b]">
                        <div class="relative">
                            <MagnifyingGlassIcon class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                            <input 
                                v-model="searchQuery"
                                type="text" 
                                placeholder="Search tag or description..." 
                                class="input input-sm w-full bg-[#0f172a] border-slate-600 pl-10 text-white focus:border-orange-500 focus:outline-none rounded-lg placeholder:text-slate-500"
                            />
                        </div>
                    </div>
    
                    <div class="flex-1 overflow-y-auto custom-scrollbar p-2 space-y-2">
                        <div 
                            v-for="asset in filteredAssets" 
                            :key="asset.id"
                            @click="selectedId = asset.id"
                            class="p-3 rounded-xl cursor-pointer border transition-all duration-200 group relative"
                            :class="selectedId === asset.id 
                                ? 'bg-orange-500/10 border-orange-500/50 shadow-inner' 
                                : 'bg-transparent border-transparent hover:bg-[#253045] hover:border-slate-700'"
                        >
                            <div class="flex justify-between items-start mb-1">
                                <span class="font-bold text-sm text-white group-hover:text-orange-400 transition-colors line-clamp-1">{{ asset.description }}</span>
                                <span :class="`text-[10px] px-1.5 py-0.5 rounded font-bold uppercase border ${getStatusColor(asset.status)}`">
                                    {{ asset.status }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center text-xs text-slate-400">
                                <span class="font-mono bg-[#0f172a] px-1.5 rounded text-slate-500">{{ asset.tag_no }}</span>
                                <span class="flex items-center gap-1"><MapPinIcon class="w-3 h-3"/> {{ asset.location }}</span>
                            </div>
                            
                            <div v-if="selectedId === asset.id" class="absolute left-0 top-3 bottom-3 w-1 bg-orange-500 rounded-r-full"></div>
                        </div>
                    </div>
                </div>
    
                <div class="lg:w-2/3 bg-[#1e293b] border border-slate-700/50 rounded-2xl shadow-xl flex flex-col overflow-hidden relative">
                    
                    <div class="absolute top-0 right-0 p-8 opacity-5 pointer-events-none">
                        <CubeIcon class="w-64 h-64 text-white" />
                    </div>
    
                    <div class="p-6 border-b border-slate-700/50 bg-[#1e293b] relative z-10">
                        <div class="flex flex-col md:flex-row gap-5">
                            <div class="w-24 h-24 rounded-xl overflow-hidden border-2 border-slate-600 bg-slate-800 shadow-lg flex-shrink-0">
                                <img :src="selectedAsset.image" v-if="selectedAsset.image" class="w-full h-full object-cover" />
                                <div v-else class="w-full h-full flex items-center justify-center text-slate-500"><CubeIcon class="w-10 h-10"/></div>
                            </div>
    
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-1">
                                    <h2 class="text-2xl font-bold text-white leading-tight">{{ selectedAsset.description }}</h2>
                                    <CheckBadgeIcon v-if="selectedAsset.condition === 'Good'" class="w-6 h-6 text-emerald-500 flex-shrink-0" title="Good Condition"/>
                                    <ExclamationTriangleIcon v-else class="w-6 h-6 text-red-500 flex-shrink-0" title="Attention Needed"/>
                                </div>
                                <div class="flex items-center gap-2 mb-4 flex-wrap">
                                    <span class="text-sm font-mono text-orange-400 bg-orange-500/10 px-2 py-0.5 rounded border border-orange-500/20">{{ selectedAsset.tag_no }}</span>
                                    <span class="text-slate-500 text-sm">|</span>
                                    <span class="text-slate-400 text-sm">{{ selectedAsset.category }}</span>
                                </div>
                                <div class="flex gap-2">
                                    <button class="btn btn-xs btn-outline border-slate-600 text-slate-300 hover:text-white hover:bg-slate-700">
                                        <PrinterIcon class="w-3 h-3" /> Print Tag
                                    </button>
                                    <button class="btn btn-xs btn-outline border-slate-600 text-slate-300 hover:text-white hover:bg-slate-700">
                                        <ArrowDownTrayIcon class="w-3 h-3" /> Export History
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
    
                    <div class="flex-1 overflow-y-auto custom-scrollbar p-6 bg-[#0f172a]/30">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            
                            <div class="bg-[#1e293b] p-5 rounded-xl border border-slate-700/50">
                                <h3 class="text-white font-bold mb-4 flex items-center gap-2">
                                    <CubeIcon class="w-4 h-4 text-blue-400" /> Specifications
                                </h3>
                                <div class="space-y-3 text-sm">
                                    <div class="flex justify-between py-2 border-b border-slate-700/50">
                                        <span class="text-slate-500">Brand / Model</span>
                                        <span class="text-slate-200">{{ selectedAsset.brand }} / {{ selectedAsset.model }}</span>
                                    </div>
                                    <div class="flex justify-between py-2 border-b border-slate-700/50">
                                        <span class="text-slate-500">Serial No.</span>
                                        <span class="text-slate-200 font-mono">{{ selectedAsset.serial }}</span>
                                    </div>
                                    <div class="flex justify-between py-2 border-b border-slate-700/50">
                                        <span class="text-slate-500">Year of Purchase</span>
                                        <span class="text-slate-200">{{ selectedAsset.year }}</span>
                                    </div>
                                    <div class="flex justify-between py-2 border-b border-slate-700/50">
                                        <span class="text-slate-500">Dimensions</span>
                                        <span class="text-slate-200">{{ selectedAsset.specs.dims }}</span>
                                    </div>
                                    <div class="flex justify-between py-2">
                                        <span class="text-slate-500">Weight</span>
                                        <span class="text-slate-200">{{ selectedAsset.specs.weight }}</span>
                                    </div>
                                </div>
                            </div>
    
                            <div class="bg-[#1e293b] p-5 rounded-xl border border-slate-700/50">
                                <h3 class="text-white font-bold mb-4 flex items-center gap-2">
                                    <DocumentCheckIcon class="w-4 h-4 text-emerald-400" /> Compliance
                                </h3>
                                
                                <div class="bg-[#0f172a] p-4 rounded-lg border border-slate-700 mb-4">
                                    <span class="text-xs text-slate-500 uppercase block mb-1">Current Certificate</span>
                                    <div class="flex justify-between items-center">
                                        <span class="text-white font-bold">{{ selectedAsset.cert.number }}</span>
                                        <span class="text-emerald-400 text-xs bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-500/20">Active</span>
                                    </div>
                                    <div class="mt-2 text-xs flex justify-between">
                                        <span class="text-slate-400">{{ selectedAsset.cert.type }}</span>
                                        <span class="text-slate-400">Exp: {{ selectedAsset.cert.expiry }}</span>
                                    </div>
                                </div>
    
                                <button class="w-full py-2 border border-dashed border-slate-600 rounded-lg text-slate-500 text-xs hover:border-orange-500 hover:text-orange-500 transition-colors">
                                    + Upload New Document
                                </button>
                            </div>
    
                            <div class="md:col-span-2 bg-[#1e293b] p-5 rounded-xl border border-slate-700/50">
                                <h3 class="text-white font-bold mb-4 flex items-center gap-2">
                                    <ClockIcon class="w-4 h-4 text-orange-400" /> Recent History
                                </h3>
                                
                                <div class="relative pl-4 space-y-6 before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-px before:bg-slate-700">
                                    <div v-for="(event, i) in selectedAsset.history" :key="i" class="relative">
                                        <div class="absolute -left-4 w-3 h-3 rounded-full bg-slate-600 border-2 border-[#1e293b]" :class="i===0 ? 'bg-orange-500' : ''"></div>
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <p class="text-sm font-bold text-slate-200">{{ event.event }}</p>
                                                <p class="text-xs text-slate-500">Action by: {{ event.user }}</p>
                                            </div>
                                            <span class="text-xs text-slate-400 font-mono">{{ event.date }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
    
                        </div>
                    </div>
    
                </div>
            </div>
        </AuthenticatedLayout>
    </template>
    
    <style scoped>
    /* Stealth Scrollbar */
    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: transparent; 
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #334155; 
        border-radius: 3px;
    }
    </style>