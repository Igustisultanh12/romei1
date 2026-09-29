<script setup>
import { ref, onMounted, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { 
    Squares2X2Icon, 
    DevicePhoneMobileIcon, 
    GiftIcon, 
    TicketIcon, 
    CpuChipIcon, 
    Cog6ToothIcon, 
    ArrowLeftOnRectangleIcon,
    SunIcon, 
    MoonIcon,
    UserGroupIcon,
    ChatBubbleLeftRightIcon // Diimpor secara aman untuk visualisasi ikon menu WhatsApp Config
} from '@heroicons/vue/24/outline';

const isDark = ref(true);
const page = usePage();

// BENTENG PERTAHANAN: Mengamankan properti user agar kebal dari error "props.auth is undefined"
const adminUser = computed(() => {
    return page.props.auth?.user || { name: 'Admin ROMEI', email: 'admin@romei1.my.id' };
});

// Ambil inisial huruf pertama nama admin untuk avatar lingkaran secara aman
const avatarInitial = computed(() => {
    const name = adminUser.value?.name || 'A';
    return name.charAt(0).toUpperCase();
});

// Sinkronisasi setelan tema saat halaman dimuat pertama kali
onMounted(() => {
    const savedTheme = localStorage.getItem('theme');
    // Default ROMEI_HQ menggunakan tema gelap jika belum ada preferensi tersimpan
    if (savedTheme === 'light') {
        isDark.value = false;
        document.documentElement.classList.remove('dark');
    } else {
        isDark.value = true;
        document.documentElement.classList.add('dark');
    }
});

// Fungsi sakelar pengubah tema gelap/terang secara realtime
const toggleTheme = () => {
    isDark.value = !isDark.value;
    if (isDark.value) {
        document.documentElement.classList.add('dark');
        localStorage.setItem('theme', 'dark');
    } else {
        document.documentElement.classList.remove('dark');
        localStorage.setItem('theme', 'light');
    }
};

const logout = () => {
    router.post(route('logout'));
};
</script>

<template>
    <div class="min-h-screen flex bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100 transition-colors duration-300 font-sans">
        
        <aside class="w-64 bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 flex flex-col justify-between fixed h-full z-40 transition-colors duration-300">
            <div class="p-6">
                <div class="text-xl font-black tracking-wider text-blue-600 dark:text-blue-500 mb-8 flex items-center gap-2">
                    <CpuChipIcon class="w-6 h-6 text-blue-600 dark:text-blue-500" />
                    ROMEI_HQ
                </div>
                
                <nav class="space-y-1">
                    <Link :href="route('admin.dashboard')" :class="route().current('admin.dashboard') ? 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/50'" class="flex items-center gap-3 px-4 py-3 text-xs font-bold rounded-lg transition-all">
                        <Squares2X2Icon class="w-5 h-5" /> Overview Dashboard
                    </Link>
                    
                    <Link :href="route().has('admin.customers.index') ? route('admin.customers.index') : '#'" :class="route().current('admin.customers.*') ? 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/50'" class="flex items-center gap-3 px-4 py-3 text-xs font-bold rounded-lg transition-all">
                        <UserGroupIcon class="w-5 h-5" /> Kelola Pelanggan
                    </Link>

                    <Link :href="route('admin.imei.index')" :class="route().current('admin.imei.index') ? 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/50'" class="flex items-center gap-3 px-4 py-3 text-xs font-bold rounded-lg transition-all">
                        <DevicePhoneMobileIcon class="w-5 h-5" /> Antrean IMEI
                    </Link>
                    
                    <Link :href="route().has('admin.vouchers.index') ? route('admin.vouchers.index') : '#'" :class="route().current('admin.vouchers.index') ? 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/50'" class="flex items-center gap-3 px-4 py-3 text-xs font-bold rounded-lg transition-all">
                        <GiftIcon class="w-5 h-5" /> Manajemen Voucher
                    </Link>
                    
                    <Link :href="route().has('admin.tickets.index') ? route('admin.tickets.index') : '#'" :class="route().current('admin.tickets.index') ? 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/50'" class="flex items-center gap-3 px-4 py-3 text-xs font-bold rounded-lg transition-all">
                        <TicketIcon class="w-5 h-5" /> Ticket Bantuan
                    </Link>
                    
                    <Link :href="route().has('admin.monitoring') ? route('admin.monitoring') : '#'" :class="route().current('admin.monitoring') ? 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/50'" class="flex items-center gap-3 px-4 py-3 text-xs font-bold rounded-lg transition-all">
                        <CpuChipIcon class="w-5 h-5" /> Monitoring API
                    </Link>

                    <Link :href="route().has('admin.whatsapp.config') ? route('admin.whatsapp.config') : '/admin/whatsapp-config'" :class="$page.url.startsWith('/admin/whatsapp-config') ? 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/50'" class="flex items-center gap-3 px-4 py-3 text-xs font-bold rounded-lg transition-all">
                        <ChatBubbleLeftRightIcon class="w-5 h-5" /> Konfigurasi WhatsApp
                    </Link>
                    
                    <Link :href="route('admin.settings')" :class="route().current('admin.settings') ? 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/50'" class="flex items-center gap-3 px-4 py-3 text-xs font-bold rounded-lg transition-all">
                        <Cog6ToothIcon class="w-5 h-5" /> Konfigurasi Sistem
                    </Link>
                </nav>
            </div>

            <div class="p-4 border-t border-slate-200 dark:border-slate-800 space-y-3 bg-slate-50/50 dark:bg-slate-900/50">
                <div class="flex items-center gap-3 px-2">
                    <div class="w-9 h-9 rounded-full bg-blue-600 text-white font-black text-xs flex items-center justify-center shadow-sm">
                        {{ avatarInitial }}
                    </div>
                    <div class="truncate">
                        <div class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate max-w-[140px]">{{ adminUser.name }}</div>
                        <div class="text-[10px] font-semibold text-slate-400">Administrator Panel</div>
                    </div>
                </div>
                <button @click="logout" class="w-full text-left flex items-center gap-2 px-3 py-2 text-xs font-bold text-red-500 hover:bg-red-50 dark:hover:bg-red-950/20 rounded-lg transition-all">
                    <ArrowLeftOnRectangleIcon class="w-4 h-4" /> Keluar Sistem
                </button>
            </div>
        </aside>

        <div class="flex-1 pl-64 flex flex-col min-h-screen">
            <header class="h-16 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 px-8 flex items-center justify-between sticky top-0 z-30 transition-colors duration-300">
                <div class="text-[11px] font-bold tracking-wider text-slate-400 dark:text-slate-500 uppercase">
                    Sistem Operasi ROMEI v2.0 • Realtime Gate Sync
                </div>
                
                <button @click="toggleTheme" class="p-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg transition-all shadow-sm flex items-center justify-center border border-slate-200/40 dark:border-slate-700/40" title="Ubah Tampilan">
                    <SunIcon v-if="isDark" class="w-5 h-5 text-amber-400" />
                    <MoonIcon v-else class="w-5 h-5 text-indigo-600" />
                </button>
            </header>

            <header v-if="$page.props.errors && Object.keys($page.props.errors).length > 0" class="bg-red-500 text-white text-xs px-8 py-2 font-semibold">
                ⚠️ Terdeteksi Kendala Operasional: {{ Object.values($page.props.errors)[0] }}
            </header>

            <main class="p-8 flex-1 bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
                <slot />
            </main>
        </div>
    </div>
</template>

<style>
.swal2-popup.font-sans {
    font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
}
.swal2-styled.swal2-confirm {
    font-weight: 700 !important;
    border-radius: 9999px !important;
}
.swal2-styled.swal2-cancel {
    font-weight: 700 !important;
    border-radius: 9999px !important;
}
</style>