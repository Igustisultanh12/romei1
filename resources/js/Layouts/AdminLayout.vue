<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
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
    ChatBubbleLeftRightIcon,
    ChatBubbleBottomCenterTextIcon,
    EnvelopeIcon,
    BanknotesIcon,
    ExclamationTriangleIcon,
    Bars3Icon,
    Bars3CenterLeftIcon,
    ChevronDoubleLeftIcon,
    ChevronDoubleRightIcon,
    ChevronDownIcon,
    UserCircleIcon,
    XMarkIcon
} from '@heroicons/vue/24/outline';

const isDark = ref(true);
const isCollapsed = ref(false);
const mobileNavOpen = ref(false);
const profileDropdownOpen = ref(false);
const page = usePage();

// Mengamankan properti user agar kebal dari error "props.auth is undefined"
const adminUser = computed(() => {
    return page.props.auth?.user || { name: 'Admin ROMEI', email: 'admin@romei1.my.id' };
});

// Ambil inisial huruf pertama nama admin untuk avatar lingkaran secara aman
const avatarInitial = computed(() => {
    const name = adminUser.value?.name || 'A';
    return name.charAt(0).toUpperCase();
});

// Route URL profil terintegrasi ke Manajemen Pusat Akun (Account Settings)
const profileUrl = computed(() => {
    try {
        if (route().has('account.settings')) {
            return route('account.settings');
        }
    } catch (e) {}
    return '/account/settings';
});

const accountSettingsUrl = profileUrl;

// Daftar menu navigasi utama
const navItems = computed(() => [
    {
        name: 'Overview Dashboard',
        href: route().has('admin.dashboard') ? route('admin.dashboard') : '/admin/dashboard',
        active: route().current('admin.dashboard'),
        icon: Squares2X2Icon,
    },
    {
        name: 'Kelola Pelanggan',
        href: route().has('admin.customers.index') ? route('admin.customers.index') : '/admin/customers',
        active: route().current('admin.customers.*'),
        icon: UserGroupIcon,
    },
    {
        name: 'Antrean IMEI',
        href: route().has('admin.imei.index') ? route('admin.imei.index') : '/admin/imei',
        active: route().current('admin.imei.*'),
        icon: DevicePhoneMobileIcon,
    },
    {
        name: 'Manajemen Voucher',
        href: route().has('admin.vouchers.index') ? route('admin.vouchers.index') : '/admin/vouchers',
        active: route().current('admin.vouchers.*'),
        icon: GiftIcon,
    },
    {
        name: 'Penarikan Saldo',
        href: route().has('admin.withdrawals.index') ? route('admin.withdrawals.index') : '/admin/withdrawals',
        active: page.url.startsWith('/admin/withdrawals'),
        icon: BanknotesIcon,
    },
    {
        name: 'Ticket Bantuan',
        href: route().has('admin.tickets.index') ? route('admin.tickets.index') : '/admin/tickets',
        active: route().current('admin.tickets.*'),
        icon: TicketIcon,
    },
    {
        name: 'Ulasan Pelanggan',
        href: route().has('admin.feedbacks.index') ? route('admin.feedbacks.index') : '/admin/feedbacks',
        active: page.url.startsWith('/admin/feedbacks'),
        icon: ChatBubbleBottomCenterTextIcon,
    },
    {
        name: 'Monitoring API',
        href: route().has('admin.monitoring') ? route('admin.monitoring') : '/admin/monitoring',
        active: route().current('admin.monitoring'),
        icon: CpuChipIcon,
    },
    {
        name: 'Konfigurasi WhatsApp',
        href: route().has('admin.whatsapp.config') ? route('admin.whatsapp.config') : '/admin/whatsapp-config',
        active: page.url.startsWith('/admin/whatsapp-config'),
        icon: ChatBubbleLeftRightIcon,
    },
    {
        name: 'Mail Gateway SMTP',
        href: route().has('admin.mail.index') ? route('admin.mail.index') : '/admin/mail-gateway',
        active: page.url.startsWith('/admin/mail-gateway'),
        icon: EnvelopeIcon,
    },
    {
        name: 'Konfigurasi Sistem',
        href: route().has('admin.settings') ? route('admin.settings') : '/admin/settings',
        active: route().current('admin.settings'),
        icon: Cog6ToothIcon,
        badge: 'CEIRKU',
    },
]);

// Sinkronisasi tema dan status sidebar saat dimuat
const handleResize = () => {
    if (window.innerWidth < 1024) {
        mobileNavOpen.value = false;
    }
};

const handleKeydown = (e) => {
    if (e.key === 'Escape') {
        profileDropdownOpen.value = false;
        mobileNavOpen.value = false;
    }
};

onMounted(() => {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'light') {
        isDark.value = false;
        document.documentElement.classList.remove('dark');
    } else {
        isDark.value = true;
        document.documentElement.classList.add('dark');
    }

    // Auto-minimize sidebar secara cerdas:
    // Jika user sudah pernah memilih, gunakan preferensi tersimpan.
    // Jika belum pernah, otomatis minimize pada layar laptop / resolusi < 1280px agar menu tidak sesak.
    const savedCollapsed = localStorage.getItem('admin_sidebar_collapsed');
    if (savedCollapsed !== null) {
        isCollapsed.value = savedCollapsed === 'true';
    } else {
        isCollapsed.value = window.innerWidth < 1280;
    }

    window.addEventListener('resize', handleResize);
    window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    window.removeEventListener('resize', handleResize);
    window.removeEventListener('keydown', handleKeydown);
});

// Fungsi toggle minimize / expand sidebar
const toggleSidebar = () => {
    isCollapsed.value = !isCollapsed.value;
    localStorage.setItem('admin_sidebar_collapsed', isCollapsed.value ? 'true' : 'false');
};

// Sakelar tema gelap/terang
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
        
        <!-- ================= DESKTOP SIDEBAR ================= -->
        <aside 
            :class="[
                'bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 flex-col fixed h-full z-40 transition-all duration-300 ease-in-out hidden lg:flex',
                isCollapsed ? 'w-20' : 'w-64'
            ]"
        >
            <!-- 1. Header Sidebar: Logo & Toggle Minimize -->
            <div 
                :class="[
                    'h-16 px-4 border-b border-slate-200/80 dark:border-slate-800/80 flex items-center shrink-0 transition-all duration-300',
                    isCollapsed ? 'justify-center' : 'justify-between'
                ]"
            >
                <div class="flex items-center gap-2.5 overflow-hidden">
                    <div class="w-9 h-9 rounded-xl bg-blue-600/10 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0 shadow-sm">
                        <CpuChipIcon class="w-5 h-5" />
                    </div>
                    <span v-if="!isCollapsed" class="text-base font-black tracking-wider text-blue-600 dark:text-blue-500 whitespace-nowrap animate-fade-in">
                        ROMEI_HQ
                    </span>
                </div>

                <!-- Tombol Collapse / Expand -->
                <button
                    @click="toggleSidebar"
                    type="button"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                    :title="isCollapsed ? 'Perluas Sidebar' : 'Perkecil Sidebar'"
                >
                    <ChevronDoubleRightIcon v-if="isCollapsed" class="w-4 h-4 text-blue-500" />
                    <ChevronDoubleLeftIcon v-else class="w-4 h-4" />
                </button>
            </div>

            <!-- 2. Menu Navigasi: Scrollable (flex-1 overflow-y-auto) agar TIDAK menenggelamkan footer -->
            <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1.5 scrollbar-thin">
                <Link
                    v-for="item in navItems"
                    :key="item.name"
                    :href="item.href"
                    :class="[
                        'flex items-center rounded-xl text-xs font-bold transition-all group relative',
                        isCollapsed ? 'justify-center px-2 py-3' : 'gap-3 px-3.5 py-2.5',
                        item.active
                            ? 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400 shadow-sm border border-blue-200/60 dark:border-blue-500/20'
                            : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'
                    ]"
                    :title="isCollapsed ? item.name : undefined"
                >
                    <component 
                        :is="item.icon" 
                        class="w-5 h-5 shrink-0 transition-transform group-hover:scale-110" 
                        :class="item.active ? 'text-blue-600 dark:text-blue-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300'" 
                    />
                    <span v-if="!isCollapsed" class="truncate">{{ item.name }}</span>
                    <span 
                        v-if="!isCollapsed && item.badge" 
                        class="ml-auto text-[9px] font-black uppercase px-1.5 py-0.5 rounded bg-blue-100 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800"
                    >
                        {{ item.badge }}
                    </span>

                    <!-- Floating Tooltip saat Sidebar Dikecilkan -->
                    <div
                        v-if="isCollapsed"
                        class="absolute left-full ml-3 px-2.5 py-1 bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900 text-[11px] font-bold rounded-lg opacity-0 pointer-events-none group-hover:opacity-100 transition-opacity whitespace-nowrap shadow-xl z-50 border border-slate-700 dark:border-slate-300"
                    >
                        {{ item.name }}
                    </div>
                </Link>
            </nav>

            <!-- 3. Profil & Logout Pinned Footer (shrink-0) - DIJAMIN SELALU TERLIHAT & DAPAT DIKLIK -->
            <div class="shrink-0 p-3 border-t border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-950/50 space-y-2">
                <!-- Info Profil Dapat Diklik (Mode Normal) -->
                <Link 
                    v-if="!isCollapsed"
                    :href="profileUrl"
                    class="flex items-center gap-3 px-2 py-1.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800/80 transition-all group w-full text-left cursor-pointer border border-transparent hover:border-slate-200 dark:hover:border-slate-700/60"
                    title="Buka Pengaturan Profil Saya"
                >
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-600 text-white font-black text-xs flex items-center justify-center shadow-sm shrink-0 group-hover:ring-2 group-hover:ring-blue-500/40 transition">
                        {{ avatarInitial }}
                    </div>
                    <div class="truncate flex-1">
                        <div class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate group-hover:text-blue-600 dark:group-hover:text-blue-400 transition">{{ adminUser.name }}</div>
                        <div class="text-[10px] font-semibold text-slate-400">Administrator • Profil</div>
                    </div>
                </Link>

                <!-- Info Profil Dapat Diklik (Mode Minimized) -->
                <Link 
                    v-else
                    :href="profileUrl"
                    class="flex justify-center py-1 group relative cursor-pointer"
                    :title="'Profil: ' + adminUser.name + ' (Klik untuk edit)'"
                >
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-600 text-white font-black text-xs flex items-center justify-center shadow-sm group-hover:ring-2 group-hover:ring-blue-500/40 transition">
                        {{ avatarInitial }}
                    </div>
                    <div class="absolute left-full ml-3 px-2.5 py-1 bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900 text-[11px] font-bold rounded-lg opacity-0 pointer-events-none group-hover:opacity-100 transition-opacity whitespace-nowrap shadow-xl z-50 border border-slate-700 dark:border-slate-300">
                        {{ adminUser.name }} (Klik untuk edit profil)
                    </div>
                </Link>

                <!-- Tombol Logout -->
                <button 
                    @click="logout" 
                    type="button"
                    :class="[
                        'w-full flex items-center rounded-xl font-bold text-xs text-red-500 hover:bg-red-50 dark:hover:bg-red-950/20 transition-all border border-transparent hover:border-red-200 dark:hover:border-red-900/30 group relative',
                        isCollapsed ? 'justify-center p-2.5' : 'gap-2 px-3 py-2 text-left'
                    ]"
                    :title="isCollapsed ? 'Keluar Sistem' : undefined"
                >
                    <ArrowLeftOnRectangleIcon class="w-4 h-4 shrink-0 transition-transform group-hover:-translate-x-0.5" />
                    <span v-if="!isCollapsed">Keluar Sistem</span>

                    <div
                        v-if="isCollapsed"
                        class="absolute left-full ml-3 px-2.5 py-1 bg-red-600 text-white text-[11px] font-bold rounded-lg opacity-0 pointer-events-none group-hover:opacity-100 transition-opacity whitespace-nowrap shadow-xl z-50"
                    >
                        Keluar Sistem
                    </div>
                </button>
            </div>
        </aside>

        <!-- ================= MOBILE DRAWER (< lg) ================= -->
        <div v-if="mobileNavOpen" class="fixed inset-0 z-50 lg:hidden flex">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm transition-opacity" @click="mobileNavOpen = false"></div>

            <!-- Drawer Container -->
            <aside class="relative w-4/5 max-w-xs bg-white dark:bg-slate-900 h-full flex flex-col justify-between shadow-2xl z-10 border-r border-slate-200 dark:border-slate-800">
                <!-- Drawer Header -->
                <div class="h-16 px-5 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-blue-600/10 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                            <CpuChipIcon class="w-5 h-5" />
                        </div>
                        <span class="text-base font-black tracking-wider text-blue-600 dark:text-blue-500">ROMEI_HQ</span>
                    </div>
                    <button @click="mobileNavOpen = false" class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                        <XMarkIcon class="w-5 h-5" />
                    </button>
                </div>

                <!-- Drawer Navigation -->
                <nav class="flex-1 overflow-y-auto p-4 space-y-1 scrollbar-thin">
                    <Link
                        v-for="item in navItems"
                        :key="item.name"
                        :href="item.href"
                        @click="mobileNavOpen = false"
                        :class="[
                            'flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all',
                            item.active
                                ? 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400 border border-blue-200/60 dark:border-blue-500/20'
                                : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60'
                        ]"
                    >
                        <component :is="item.icon" class="w-5 h-5 shrink-0" :class="item.active ? 'text-blue-600 dark:text-blue-400' : 'text-slate-400'" />
                        <span class="truncate">{{ item.name }}</span>
                        <span v-if="item.badge" class="ml-auto text-[9px] font-black uppercase px-1.5 py-0.5 rounded bg-blue-100 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300">
                            {{ item.badge }}
                        </span>
                    </Link>
                </nav>

                <!-- Drawer Pinned Profile & Logout (Dapat Diklik) -->
                <div class="p-4 border-t border-slate-200 dark:border-slate-800 space-y-2 bg-slate-50/70 dark:bg-slate-950/50">
                    <Link 
                        :href="profileUrl"
                        @click="mobileNavOpen = false"
                        class="flex items-center gap-3 px-2 py-1.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition-all group w-full text-left cursor-pointer border border-transparent hover:border-slate-200 dark:hover:border-slate-700/60"
                        title="Buka Pengaturan Profil"
                    >
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-600 text-white font-black text-xs flex items-center justify-center shadow-sm">
                            {{ avatarInitial }}
                        </div>
                        <div class="truncate flex-1">
                            <div class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate group-hover:text-blue-600 dark:group-hover:text-blue-400 transition">{{ adminUser.name }}</div>
                            <div class="text-[10px] font-semibold text-slate-400">Administrator • Profil</div>
                        </div>
                    </Link>
                    <button @click="logout" type="button" class="w-full flex items-center gap-2 px-3 py-2 text-xs font-bold text-red-500 hover:bg-red-50 dark:hover:bg-red-950/20 rounded-xl transition-all">
                        <ArrowLeftOnRectangleIcon class="w-4 h-4" /> Keluar Sistem
                    </button>
                </div>
            </aside>
        </div>

        <!-- ================= MAIN CONTENT AREA ================= -->
        <div 
            :class="[
                'flex-1 flex flex-col min-h-screen transition-all duration-300 ease-in-out',
                isCollapsed ? 'lg:pl-20' : 'lg:pl-64',
                'pl-0'
            ]"
        >
            <!-- Top Navbar Header -->
            <header class="h-16 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 px-4 sm:px-8 flex items-center justify-between sticky top-0 z-30 transition-colors duration-300 shadow-sm/5">
                <div class="flex items-center gap-3">
                    <!-- Hamburger Button (< lg) -->
                    <button 
                        @click="mobileNavOpen = true" 
                        type="button" 
                        class="lg:hidden p-2 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                        title="Buka Menu Navigasi"
                    >
                        <Bars3Icon class="w-5 h-5" />
                    </button>

                    <!-- Toggle Button Desktop (Minimize/Expand Sidebar) -->
                    <button
                        @click="toggleSidebar"
                        type="button"
                        class="hidden lg:flex p-2 rounded-xl text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition items-center gap-1.5"
                        :title="isCollapsed ? 'Perluas Sidebar' : 'Kecilkan Sidebar'"
                    >
                        <Bars3CenterLeftIcon class="w-5 h-5" />
                    </button>

                    <div class="text-[11px] font-bold tracking-wider text-slate-400 dark:text-slate-500 uppercase truncate">
                        Sistem Operasi ROMEI v2.0 • Realtime Gate Sync
                    </div>
                </div>
                
                <div class="flex items-center gap-3">
                    <!-- Sakelar Dark Mode -->
                    <button 
                        @click="toggleTheme" 
                        type="button"
                        class="p-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl transition-all shadow-sm flex items-center justify-center border border-slate-200/40 dark:border-slate-700/40" 
                        title="Ubah Mode Tampilan"
                    >
                        <SunIcon v-if="isDark" class="w-5 h-5 text-amber-400" />
                        <MoonIcon v-else class="w-5 h-5 text-indigo-600" />
                    </button>

                    <!-- Header Profile Dropdown: DAPAT DIKLIK & INTERAKTIF -->
                    <div class="relative">
                        <button
                            @click="profileDropdownOpen = !profileDropdownOpen"
                            type="button"
                            class="flex items-center gap-2 pl-2 pr-2.5 py-1 rounded-xl border border-slate-200/80 dark:border-slate-700/80 hover:bg-slate-100 dark:hover:bg-slate-800/80 transition-all focus:outline-none shadow-sm/5 group"
                            title="Menu Pengguna Administrator"
                        >
                            <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-blue-600 to-indigo-600 text-white font-black text-xs flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                                {{ avatarInitial }}
                            </div>
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300 hidden sm:inline max-w-[120px] truncate">
                                {{ adminUser.name }}
                            </span>
                            <ChevronDownIcon class="w-3.5 h-3.5 text-slate-400 group-hover:text-slate-600 dark:group-hover:text-slate-200 transition-transform" :class="{'rotate-180': profileDropdownOpen}" />
                        </button>

                        <!-- Backdrop Click Outside -->
                        <div v-if="profileDropdownOpen" class="fixed inset-0 z-40" @click="profileDropdownOpen = false"></div>

                        <!-- Dropdown Popup Card -->
                        <div
                            v-if="profileDropdownOpen"
                            class="absolute right-0 mt-2 w-60 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl py-2 z-50 text-xs font-semibold animate-fade-in divide-y divide-slate-100 dark:divide-slate-800"
                        >
                            <!-- Header Info -->
                            <div class="px-4 py-3">
                                <div class="font-bold text-slate-900 dark:text-white truncate">{{ adminUser.name }}</div>
                                <div class="text-[10px] text-slate-400 font-mono truncate mt-0.5">{{ adminUser.email || 'admin@romei1.my.id' }}</div>
                                <span class="inline-block mt-2 text-[9px] font-black uppercase px-2 py-0.5 rounded-full bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800">
                                    Administrator ROMEI
                                </span>
                            </div>

                            <!-- Menu Links -->
                            <div class="py-1.5">
                                <Link
                                    :href="profileUrl"
                                    @click="profileDropdownOpen = false"
                                    class="flex items-center gap-2.5 px-4 py-2 text-slate-700 dark:text-slate-200 hover:bg-blue-50 dark:hover:bg-blue-500/10 hover:text-blue-600 dark:hover:text-blue-400 transition"
                                >
                                    <UserCircleIcon class="w-4 h-4 text-blue-500" />
                                    <span>Edit Profil Saya</span>
                                </Link>

                                <Link
                                    :href="accountSettingsUrl"
                                    @click="profileDropdownOpen = false"
                                    class="flex items-center gap-2.5 px-4 py-2 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800/80 transition"
                                >
                                    <Cog6ToothIcon class="w-4 h-4 text-slate-400" />
                                    <span>Pengaturan Akun & Keamanan</span>
                                </Link>
                            </div>

                            <!-- Logout -->
                            <div class="pt-1">
                                <button
                                    @click="logout"
                                    type="button"
                                    class="w-full flex items-center gap-2.5 px-4 py-2 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/20 transition text-left font-bold"
                                >
                                    <ArrowLeftOnRectangleIcon class="w-4 h-4" />
                                    <span>Keluar Sistem (Logout)</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <header v-if="$page.props.errors && Object.keys($page.props.errors).length > 0" class="bg-red-600 text-white text-xs px-8 py-2.5 font-semibold flex items-center gap-2">
                <ExclamationTriangleIcon class="w-4 h-4 shrink-0" />
                <span>Kendala Operasional: {{ Object.values($page.props.errors)[0] }}</span>
            </header>

            <main class="p-4 sm:p-8 flex-1 bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
                <slot />
            </main>
        </div>
    </div>
</template>

<style>
/* Custom Sleek Scrollbar untuk Navigasi Sidebar */
.scrollbar-thin::-webkit-scrollbar {
    width: 4px;
}
.scrollbar-thin::-webkit-scrollbar-track {
    background: transparent;
}
.scrollbar-thin::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.2);
    border-radius: 9999px;
}
.scrollbar-thin::-webkit-scrollbar-thumb:hover {
    background: rgba(148, 163, 184, 0.4);
}

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