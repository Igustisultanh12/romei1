<script setup>
import { computed, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { 
    HomeIcon, 
    DevicePhoneMobileIcon, 
    WalletIcon, 
    QuestionMarkCircleIcon, 
    ArrowLeftStartOnRectangleIcon,
    Bars3Icon,
    XMarkIcon,
    CpuChipIcon,
    ClockIcon,
    AdjustmentsHorizontalIcon,
    ChevronDownIcon 
} from '@heroicons/vue/24/outline';

const page = usePage();
const isMobileMenuOpen = ref(false);

// STRIKTOR OBYEK: Menarik paksa payload autentikasi session database
const authUser = computed(() => page.props.auth?.user || null);

// Ambil path URL yang sedang aktif saat ini di browser pembeli
const currentUrlPath = computed(() => page.url || '');

// Cek apakah user sedang membuka salah satu dari sub-menu Layanan Lainnya (Termasuk Tickets)
const isLayananActive = computed(() => {
    return currentUrlPath.value.startsWith('/services/sim-lock') || 
           currentUrlPath.value.startsWith('/services/ceir-history') ||
           currentUrlPath.value.startsWith('/services/tickets');
});

// Jika sub-menu aktif, biarkan dropdown tetap terbuka secara default
const isLayananDropdownOpen = ref(isLayananActive.value);

// Otomatis sinkronisasi status dropdown jika user melakukan navigasi halaman
watch(currentUrlPath, (newPath) => {
    if (isLayananActive.value) {
        isLayananDropdownOpen.value = true;
    }
});

// Helper pembentukan inisial avatar profil user yang kebal dari error nilai null database
const formatInitial = (name) => {
    if (!name) return 'RM';
    const cleanName = name.trim().replace(/\s+/g, ' ');
    const words = cleanName.split(' ');
    if (words.length === 1) return words[0].substring(0, 2).toUpperCase();
    return (words[0][0] + words[1][0]).toUpperCase();
};
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-900 font-sans antialiased selection:bg-blue-600 selection:text-white flex flex-col md:flex-row">
        
        <!-- MOBILE SCREEN HEADER BOTTOM LINE -->
        <div class="md:hidden flex items-center justify-between bg-white border-b border-slate-200/60 px-4 py-3 shadow-sm w-full relative z-50">
            <span class="text-xl font-black tracking-tight text-slate-950">ROMEI<span class="text-blue-600">.</span></span>
            <button @click="isMobileMenuOpen = !isMobileMenuOpen" class="text-slate-500 p-1 focus:outline-none">
                <Bars3Icon v-if="!isMobileMenuOpen" class="w-6 h-6" />
                <XMarkIcon v-else class="w-6 h-6" />
            </button>
        </div>

        <!-- MAIN TERMINAL CONTROL SIDEBAR DESKTOP & MOBILE TRANSITION -->
        <aside :class="[isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0', 'fixed md:sticky top-0 left-0 z-40 w-64 h-screen bg-white border-r border-slate-200/60 flex flex-col justify-between p-5 transition-transform duration-300 ease-in-out md:transform-none']">
            <div class="space-y-7 overflow-y-auto max-h-[calc(100vh-140px)] hide-scrollbar">
                <div class="flex flex-col px-2 pt-2">
                    <span class="text-2xl font-black tracking-tight text-slate-950">ROMEI<span class="text-blue-600">.</span></span>
                    <span class="text-[8px] text-slate-400 font-black uppercase tracking-widest mt-0.5">User Control Terminal</span>
                </div>

                <nav class="space-y-1">
                    <!-- LINK: DASHBOARD USER -->
                    <Link href="/dashboard" @click="isMobileMenuOpen = false"
                          :class="[currentUrlPath === '/dashboard' || currentUrlPath.startsWith('/dashboard?') ? 'bg-slate-50 text-blue-600 font-black border border-slate-200/50 shadow-sm' : 'text-slate-500 hover:bg-slate-50/50 hover:text-slate-950 font-bold', 'flex items-center space-x-3 px-3 py-2.5 rounded-xl text-xs transition-all duration-200 group']">
                        <HomeIcon :class="[currentUrlPath === '/dashboard' || currentUrlPath.startsWith('/dashboard?') ? 'text-blue-600' : 'text-slate-400 group-hover:text-slate-600', 'w-5 h-5 flex-shrink-0']" />
                        <span>Dashboard</span>
                    </Link>

                    <!-- KOREKSI SINKRONISASI: Diarahkan murni ke modul baru /roamer/create dengan highlighter rute aktif -->
                    <Link href="/roamer/create" @click="isMobileMenuOpen = false"
                          :class="[currentUrlPath.startsWith('/roamer') ? 'bg-slate-50 text-blue-600 font-black border border-slate-200/50 shadow-sm' : 'text-slate-500 hover:bg-slate-50/50 hover:text-slate-950 font-bold', 'flex items-center space-x-3 px-3 py-2.5 rounded-xl text-xs transition-all duration-200 group']">
                        <DevicePhoneMobileIcon :class="[currentUrlPath.startsWith('/roamer') ? 'text-blue-600' : 'text-slate-400 group-hover:text-slate-600', 'w-5 h-5 flex-shrink-0']" />
                        <span>Registrasi IMEI</span>
                    </Link>

                    <!-- LINK: WALLET FINANSIAL INTERNAL -->
                    <Link href="/wallet" @click="isMobileMenuOpen = false"
                          :class="[currentUrlPath.startsWith('/wallet') ? 'bg-slate-50 text-blue-600 font-black border border-slate-200/50 shadow-sm' : 'text-slate-500 hover:bg-slate-50/50 hover:text-slate-950 font-bold', 'flex items-center space-x-3 px-3 py-2.5 rounded-xl text-xs transition-all duration-200 group']">
                        <WalletIcon :class="[currentUrlPath.startsWith('/wallet') ? 'text-blue-600' : 'text-slate-400 group-hover:text-slate-600', 'w-5 h-5 flex-shrink-0']" />
                        <span>Wallet & Top Up</span>
                    </Link>

                    <!-- DROPDOWN GRUP: LAYANAN LAINNYA -->
                    <div class="space-y-1">
                        <button @click="isLayananDropdownOpen = !isLayananDropdownOpen"
                                :class="[isLayananActive ? 'text-blue-600 font-black' : 'text-slate-500 hover:text-slate-950 font-bold', 'w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-xs transition-all duration-200 group focus:outline-none']">
                            <div class="flex items-center space-x-3">
                                <AdjustmentsHorizontalIcon :class="[isLayananActive ? 'text-blue-600' : 'text-slate-400 group-hover:text-slate-600', 'w-5 h-5 flex-shrink-0']" />
                                <span>Layanan Lainnya</span>
                            </div>
                            <ChevronDownIcon :class="[isLayananDropdownOpen ? 'rotate-180' : '', 'w-3.5 h-3.5 transition-transform duration-200 text-slate-400']" />
                        </button>

                        <div v-show="isLayananDropdownOpen" class="pl-4 space-y-1 border-l border-slate-100 ml-5 transition-all">
                            <Link href="/services/sim-lock" @click="isMobileMenuOpen = false"
                                  :class="[currentUrlPath.startsWith('/services/sim-lock') ? 'text-blue-600 font-black' : 'text-slate-400 hover:text-slate-700 font-medium', 'flex items-center space-x-2.5 py-2 text-[11px] transition-colors']">
                                <CpuChipIcon class="w-4 h-4 opacity-80" />
                                <span>Cek IMEI & SIM Lock</span>
                            </Link>

                            <Link href="/services/ceir-history" @click="isMobileMenuOpen = false"
                                  :class="[currentUrlPath.startsWith('/services/ceir-history') ? 'text-blue-600 font-black' : 'text-slate-400 hover:text-slate-700 font-medium', 'flex items-center space-x-2.5 py-2 text-[11px] transition-colors']">
                                <ClockIcon class="w-4 h-4 opacity-80" />
                                <span>Tracing History CEIR</span>
                            </Link>

                            <Link href="/services/tickets" @click="isMobileMenuOpen = false"
                                  :class="[currentUrlPath.startsWith('/services/tickets') ? 'text-blue-600 font-black' : 'text-slate-400 hover:text-slate-700 font-medium', 'flex items-center space-x-2.5 py-2 text-[11px] transition-colors']">
                                <QuestionMarkCircleIcon class="w-4 h-4 opacity-80" />
                                <span>Ticket Support</span>
                            </Link>
                        </div>
                    </div>

                    <!-- LINK: RIWAYAT FINANSIAL UTALITAS GABUNGAN -->
                    <Link href="/services/history" @click="isMobileMenuOpen = false"
                          :class="[currentUrlPath === '/services/history' ? 'bg-slate-50 text-blue-600 font-black border border-slate-200/50 shadow-sm' : 'text-slate-500 hover:bg-slate-50/50 hover:text-slate-950 font-bold', 'flex items-center space-x-3 px-3 py-2.5 rounded-xl text-xs transition-all duration-200 group']">
                        <ClockIcon :class="[currentUrlPath === '/services/history' ? 'text-blue-600' : 'text-slate-400 group-hover:text-slate-600', 'w-5 h-5 flex-shrink-0']" />
                        <span>Riwayat Transaksi</span>
                    </Link>
                </nav>
            </div>

            <!-- FOOTER SIDEBAR: DATA SESSI PROFILE & TOMBOL KELUAR -->
            <div class="space-y-4">
                <div class="p-1 border-t border-slate-200/60 pt-4">
                    <Link href="/account/settings" @click="isMobileMenuOpen = false" class="flex items-center space-x-3 px-2 py-1.5 rounded-xl hover:bg-slate-50 border border-transparent hover:border-slate-200/50 transition-all duration-200 group w-full text-left focus:outline-none">
                        <div class="w-9 h-9 rounded-full bg-slate-950 text-white flex items-center justify-center font-black text-xs uppercase shadow-inner border border-slate-800 group-hover:border-blue-600 transition-colors">
                            {{ formatInitial(authUser?.name) }}
                        </div>
                        
                        <div class="truncate max-w-[140px]">
                            <p class="text-xs font-black text-slate-950 truncate group-hover:text-blue-600 transition-colors">
                                {{ authUser?.name || 'ROMEI User' }}
                            </p>
                            
                            <p class="text-[10px] text-slate-400 font-bold truncate mt-0.5 font-mono">
                                {{ authUser?.email || 'session@romei1.my.id' }}
                            </p>
                        </div>
                    </Link>
                </div>
                
                <Link href="/logout" method="post" as="button" class="w-full flex items-center space-x-3 px-3 py-2.5 rounded-xl text-xs font-black text-rose-600 hover:bg-rose-50 transition-all text-left focus:outline-none">
                    <ArrowLeftStartOnRectangleIcon class="w-5 h-5 shrink-0 text-rose-500" />
                    <span>Keluar Aplikasi</span>
                </Link>
            </div>
        </aside>

        <!-- MAIN LAYOUT DISPLAY VIEWPORT -->
        <main class="flex-1 min-w-0 overflow-y-auto bg-slate-50/50 md:bg-slate-50">
            <slot />
        </main>
    </div>
</template>

<style scoped>
html { scroll-behavior: smooth; }
.hide-scrollbar::-webkit-scrollbar { display: none; }
.hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>