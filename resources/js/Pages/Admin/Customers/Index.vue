<template>
    <AdminLayout>
        <div class="p-6 min-h-screen bg-transparent text-slate-900 dark:text-slate-150">
            <div class="max-w-7xl mx-auto">
                
                <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6 gap-4">
                    <div>
                        <h1 class="text-2xl font-black text-slate-800 dark:text-white flex items-center gap-2">
                            <UserGroupIcon class="w-7 h-7 text-blue-600 dark:text-blue-400 shrink-0" />
                            <span>Manajemen Akun Pelanggan</span>
                        </h1>
                        <p class="text-xs font-semibold text-slate-400 dark:text-slate-400 mt-1">
                            Kelola data profil, status suspensi, dan pantau saldo wallet pengguna ROMEI.
                        </p>
                    </div>
                    
                    <div class="w-full md:w-80">
                        <input 
                            v-model="searchQuery" 
                            @input="handleSearch"
                            type="text" 
                            placeholder="Cari nama, email, atau nomor WA..." 
                            class="w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg px-4 py-2 text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:border-blue-500 placeholder-slate-400 dark:placeholder-slate-500 shadow-sm"
                        />
                    </div>
                </div>

                <div v-if="$page.props.flash.success" class="mb-4 p-4 bg-emerald-500/10 border border-emerald-500/30 rounded-lg text-emerald-600 dark:text-emerald-400 text-sm font-medium flex items-center gap-2">
                    <CheckCircleIcon class="w-4 h-4 text-emerald-500 shrink-0" />
                    <span>{{ $page.props.flash.success }}</span>
                </div>

                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm dark:shadow-xl">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                            <thead class="bg-slate-50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-200 uppercase text-xs font-bold tracking-wider border-b border-slate-200 dark:border-slate-800">
                                <tr>
                                    <th class="px-6 py-4">Nama Pelanggan</th>
                                    <th class="px-6 py-4">Kontak / Kredensial</th>
                                    <th class="px-6 py-4">Saldo Wallet</th>
                                    <th class="px-6 py-4">Status Akun</th>
                                    <th class="px-6 py-4 text-center">Aksi Operasional</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50">
                                <tr v-for="customer in customers.data" :key="customer.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition-colors">
                                    <td class="px-6 py-4 font-bold text-slate-800 dark:text-white">
                                        {{ customer.name }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col">
                                            <span class="text-slate-700 dark:text-slate-300 font-medium">{{ customer.email }}</span>
                                            <span class="text-xs text-slate-400 dark:text-slate-500 mt-0.5 font-semibold">WA: +{{ customer.whatsapp_number }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-blue-600 dark:text-cyan-400 font-black">
                                        Rp {{ formatCurrency(customer.wallet ? customer.wallet.balance : 0) }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <span 
                                            :class="customer.is_suspended 
                                                ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20' 
                                                : 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20'" 
                                            class="px-2.5 py-1 rounded-full text-xs font-bold border"
                                        >
                                            {{ customer.is_suspended ? 'Suspended' : 'Aktif' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <div class="flex items-center justify-center gap-3">
                                            <Link 
                                                :href="route('admin.customers.show', customer.id)" 
                                                class="bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-white px-3 py-1.5 rounded-md text-xs font-bold transition-colors border border-slate-200 dark:border-slate-600 shadow-sm"
                                            >
                                                Detail & Edit
                                            </Link>

                                            <button 
                                                @click="toggleSuspend(customer)" 
                                                :class="customer.is_suspended 
                                                    ? 'bg-emerald-600 hover:bg-emerald-500' 
                                                    : 'bg-rose-600 hover:bg-rose-500'" 
                                                class="text-white px-3 py-1.5 rounded-md text-xs font-bold tracking-wide transition-colors shadow-sm"
                                            >
                                                {{ customer.is_suspended ? 'Aktifkan' : 'Suspend' }}
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="customers.data.length === 0">
                                    <td colspan="5" class="px-6 py-10 text-center text-slate-400 dark:text-slate-500 font-medium">
                                        Tidak ada data akun pelanggan yang ditemukan.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="customers.links.length > 3" class="px-6 py-4 bg-slate-50 dark:bg-slate-900/50 border-t border-slate-200 dark:border-slate-800 flex justify-center gap-1">
                        <component 
                            :is="link.url ? 'Link' : 'span'" 
                            v-for="(link, k) in customers.links" 
                            :key="k"
                            :href="link.url"
                            v-html="link.label"
                            :class="[
                                'px-3 py-1.5 rounded-md text-xs font-bold border transition-all',
                                link.active 
                                    ? 'bg-blue-600 border-blue-500 text-white shadow-sm' 
                                    : link.url 
                                        ? 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-600' 
                                        : 'bg-slate-100 border-slate-200 text-slate-400 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-600 cursor-not-allowed'
                            ]"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { UserGroupIcon, CheckCircleIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    customers: Object,
    filters: Object
});

const searchQuery = ref(props.filters.search || '');

// FUNGSI DEBOUNCE NATIVE: Pengganti murni lodash agar kebal dari error kompilasi Vite/Rolldown
const customDebounce = (func, delay) => {
    let timeoutId;
    return (...args) => {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => {
            func.apply(this, args);
        }, delay);
    };
};

// Eksekusi search dengan delay 400ms menggunakan fungsi native
const handleSearch = customDebounce(() => {
    router.get(route('admin.customers.index'), { search: searchQuery.value }, {
        preserveState: true,
        replace: true
    });
}, 400);

// Eksekusi Pemicu Suspensi Akun Pelanggan
const toggleSuspend = (customer) => {
    const confirmText = customer.is_suspended 
        ? `Apakah Anda yakin ingin memulihkan hak akses masuk aplikasi untuk ${customer.name}?`
        : `Apakah Anda yakin ingin melakukan SUSPEND mutlak pada akun ${customer.name}? Sesi login miliknya akan langsung hangus otomatis.`;

    if (confirm(confirmText)) {
        router.post(route('admin.customers.toggle_suspend', customer.id), {}, {
            preserveScroll: true
        });
    }
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID').format(value);
};
</script>