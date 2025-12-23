<script setup>
    import { Head, Link, useForm } from '@inertiajs/vue3';
    import { 
        EnvelopeIcon, 
        LockClosedIcon, 
        ArrowRightIcon 
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
        email: '',
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
    
        <div class="min-h-screen flex font-sans text-slate-300">
            
            <div class="hidden lg:flex w-1/2 relative bg-[#0f172a] overflow-hidden">
                <img 
                    src="https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?q=80&w=2070&auto=format&fit=crop" 
                    class="absolute inset-0 w-full h-full object-cover opacity-40 mix-blend-overlay"
                    alt="Offshore Rig"
                />
                
                <div class="absolute inset-0 bg-gradient-to-br from-orange-600/20 to-[#0f172a]/90"></div>
    
                <div class="relative z-10 w-full flex flex-col justify-between p-16">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-gradient-to-br from-orange-500 to-orange-700 rounded-xl flex items-center justify-center text-white shadow-lg shadow-orange-900/30 ring-1 ring-white/10">
                            <span class="font-bold text-xl">D</span>
                        </div>
                        <span class="text-white font-bold text-xl tracking-wide">Dayang Offshore</span>
                    </div>
    
                    <div>
                        <h1 class="text-5xl font-bold text-white mb-6 leading-tight">
                            Master Your <br/>
                            <span class="text-orange-500">Inventory</span> Control.
                        </h1>
                        <p class="text-lg text-slate-400 max-w-md">
                            Securely manage assets, track yard movements, and monitor compliance in real-time.
                        </p>
                    </div>
    
                    <div class="text-sm text-slate-500">
                        &copy; 2025 Dayang Offshore. All rights reserved.
                    </div>
                </div>
            </div>
    
            <div class="w-full lg:w-1/2 bg-[#0f172a] flex items-center justify-center p-8 relative">
                
                <div class="absolute inset-0 lg:hidden bg-[url('https://grainy-gradients.vercel.app/noise.svg')] opacity-5"></div>
    
                <div class="w-full max-w-md relative z-10">
                    
                    <div class="lg:hidden flex justify-center mb-8">
                        <div class="w-12 h-12 bg-gradient-to-br from-orange-500 to-orange-700 rounded-xl flex items-center justify-center text-white shadow-lg">
                            <span class="font-bold text-2xl">D</span>
                        </div>
                    </div>
    
                    <div class="mb-8">
                        <h2 class="text-3xl font-bold text-white mb-2">Welcome Back</h2>
                        <p class="text-slate-500">Please enter your details to sign in.</p>
                    </div>
    
                    <div v-if="status" class="mb-4 text-sm font-medium text-emerald-400 bg-emerald-500/10 p-3 rounded-lg border border-emerald-500/20">
                        {{ status }}
                    </div>
    
                    <form @submit.prevent="submit" class="space-y-5">
                        
                        <div>
                            <label for="email" class="block text-sm font-medium text-slate-400 mb-1.5">Email Address</label>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <EnvelopeIcon class="h-5 w-5 text-slate-500 group-focus-within:text-orange-500 transition-colors" />
                                </div>
                                <input 
                                    id="email" 
                                    type="email" 
                                    v-model="form.email"
                                    required 
                                    autofocus 
                                    autocomplete="username"
                                    class="block w-full pl-10 pr-3 py-2.5 bg-[#1e293b] border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all shadow-sm"
                                    placeholder="name@company.com"
                                />
                            </div>
                            <p v-if="form.errors.email" class="mt-2 text-sm text-red-400">{{ form.errors.email }}</p>
                        </div>
    
                        <div>
                            <label for="password" class="block text-sm font-medium text-slate-400 mb-1.5">Password</label>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <LockClosedIcon class="h-5 w-5 text-slate-500 group-focus-within:text-orange-500 transition-colors" />
                                </div>
                                <input 
                                    id="password" 
                                    type="password" 
                                    v-model="form.password"
                                    required 
                                    autocomplete="current-password"
                                    class="block w-full pl-10 pr-3 py-2.5 bg-[#1e293b] border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all shadow-sm"
                                    placeholder="••••••••"
                                />
                            </div>
                            <p v-if="form.errors.password" class="mt-2 text-sm text-red-400">{{ form.errors.password }}</p>
                        </div>
    
                        <div class="flex items-center justify-between">
                            <label class="flex items-center cursor-pointer">
                                <Checkbox name="remember" v-model:checked="form.remember" class="text-orange-600 bg-[#1e293b] border-slate-700 focus:ring-orange-500" />
                                <span class="ms-2 text-sm text-slate-400 hover:text-slate-300">Remember me</span>
                            </label>
    
                        
                        </div>
    
                        <button 
                            type="submit" 
                            class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-bold text-white bg-gradient-to-r from-orange-600 to-orange-500 hover:from-orange-500 hover:to-orange-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 focus:ring-offset-[#0f172a] transition-all disabled:opacity-50 disabled:cursor-not-allowed items-center gap-2"
                            :disabled="form.processing"
                        >
                            <span v-if="form.processing">Signing in...</span>
                            <span v-else>Sign in to Dashboard</span>
                            <ArrowRightIcon v-if="!form.processing" class="w-4 h-4" />
                        </button>
                    </form>
    
                    <div class="mt-8 text-center">
                        <p class="text-sm text-slate-500">
                            Don't have an account? 
                            <a href="#" class="font-medium text-slate-400 hover:text-white transition-colors">Contact Admin</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </template>