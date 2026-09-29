<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { 
    UsersIcon, 
    DevicePhoneMobileIcon, 
    BanknotesIcon, 
    ArrowPathIcon,
    ClockIcon,
    CheckCircleIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    stats: Object,
    recent_activities: Array
});

// Helper format mata uang Rupiah
const formatRupiah = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(value);
};
</script>

<template>
    <AdminLayout>
        <Head title="Admin Control Dashboard" />

        <div class="py-6 px-6 max-w-7xl mx-auto space-y-8 transition-colors duration-300">
            <div>
                <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">Overview Dashboard</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Pantau analitik registrasi IMEI dan arus keuangan sistem ROMEI secara berkala.</p>
            </div>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                
                <div class="bg-white dark:bg-slate-900 p-6 border border-slate-100 dark:border-slate-800/60 rounded-xl shadow-sm flex items-center justify-between transition-colors duration-300">
                    <div class="space-y-1">
                        <span class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Total Pendapatan</span>
                        <h3 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">
                            {{ formatRupiah(props.stats?.total_revenue ?? 0) }}
                        </h3>
                    </div>
                    <div class="p-3 bg-green-50 dark:bg-green-500/10 rounded-lg text-green-600 dark:text-green-400">
                        <BanknotesIcon class="w-6 h-6" />
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-900 p-6 border border-slate-100 dark:border-slate-800/60 rounded-xl shadow-sm flex items-center justify-between transition-colors duration-300">
                    <div class="space-y-1">
                        <span class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Registrasi (Bulan Ini)</span>
                        <h3 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">
                            {{ props.stats?.registrations_this_month ?? 0 }} 
                            <span class="text-xs text-slate-400 dark:text-slate-500 font-normal">/ {{ props.stats?.total_registrations ?? 0 }} total</span>
                        </h3>
                    </div>
                    <div class="p-3 bg-blue-50 dark:bg-blue-500/10 rounded-lg text-blue-600 dark:text-blue-400">
                        <DevicePhoneMobileIcon class="w-6 h-6" />
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-900 p-6 border border-slate-100 dark:border-slate-800/60 rounded-xl shadow-sm flex items-center justify-between transition-colors duration-300">
                    <div class="space-y-1">
                        <span class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Total Pengguna</span>
                        <h3 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">
                            {{ props.stats?.total_users ?? 0 }} 
                            <span class="text-xs text-slate-400 dark:text-slate-500 font-normal">Akun</span>
                        </h3>
                    </div>
                    <div class="p-3 bg-purple-50 dark:bg-purple-500/10 rounded-lg text-purple-600 dark:text-purple-400">
                        <UsersIcon class="w-6 h-6" />
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-900 p-6 border border-slate-100 dark:border-slate-800/60 rounded-xl shadow-sm flex items-center justify-between transition-colors duration-300">
                    <div class="space-y-1">
                        <span class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Antrean Withdrawal</span>
                        <h3 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">
                            {{ props.stats?.pending_withdrawals ?? 0 }} 
                            <span class="text-xs text-slate-400 dark:text-slate-500 font-normal">Perlu Approval</span>
                        </h3>
                    </div>
                    <div class="p-3 bg-amber-50 dark:bg-amber-500/10 rounded-lg text-amber-600 dark:text-amber-400">
                        <ClockIcon class="w-6 h-6" />
                    </div>
                </div>

            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <div class="lg:col-span-2 bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800/60 rounded-xl p-6 shadow-sm space-y-4 transition-colors duration-300">
                    <h3 class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Ringkasan Gerbang Pembayaran</h3>
                    <div class="grid grid-cols-3 gap-4 text-center">
                        <div class="p-4 bg-slate-50 dark:bg-slate-950 rounded-lg border border-slate-100/50 dark:border-slate-800/40">
                            <span class="text-xs text-slate-400 dark:text-slate-500 block mb-1">Transaksi Sukses</span>
                            <span class="text-xl font-black text-slate-900 dark:text-white">{{ props.stats?.success_payments ?? 0 }}</span>
                        </div>
                        <div class="p-4 bg-slate-50 dark:bg-slate-950 rounded-lg border border-slate-100/50 dark:border-slate-800/40">
                            <span class="text-xs text-slate-400 dark:text-slate-500 block mb-1">Menunggu Bayar</span>
                            <span class="text-xl font-black text-slate-900 dark:text-white">{{ props.stats?.pending_payments ?? 0 }}</span>
                        </div>
                        <div class="p-4 bg-slate-50 dark:bg-slate-950 rounded-lg border border-slate-100/50 dark:border-slate-800/40">
                            <span class="text-xs text-slate-400 dark:text-slate-500 block mb-1">Dana Refunded</span>
                            <span class="text-xl font-black text-red-600 dark:text-red-400">
                                {{ formatRupiah(props.stats?.total_refund ?? 0) }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800/60 rounded-xl p-6 shadow-sm space-y-4 transition-colors duration-300">
                    <h3 class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Aktivitas Registrasi Terkini</h3>
                    <div class="flow-root">
                        <ul role="list" class="-mb-8">
                            <li v-for="(activity, actIdx) in props.recent_activities" :key="activity.id">
                                <div class="relative pb-6">
                                    <span v-if="actIdx !== props.recent_activities.length - 1" class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-slate-100 dark:bg-slate-800" aria-hidden="true"></span>
                                    <div class="relative flex space-x-3">
                                        <div>
                                            <span class="h-8 w-8 rounded-full bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center ring-8 ring-white dark:ring-slate-900">
                                                <CheckCircleIcon class="w-5 h-5 text-emerald-500" v-if="activity.status === 'approved' || activity.status === 'paid'" />
                                                <ArrowPathIcon class="w-5 h-5 text-amber-500 animate-spin-slow" v-else />
                                            </span>
                                        </div>
                                        <div class="flex-1 min-w-0 pt-1.5 justify-between flex space-x-4">
                                            <div>
                                                <p class="text-xs text-slate-600 dark:text-slate-400">
                                                    <span class="font-bold text-slate-900 dark:text-white">{{ activity.user_name }}</span> mengajukan <span class="font-mono text-blue-500 dark:text-blue-400 font-bold">{{ activity.reg_number }}</span>
                                                </p>
                                            </div>
                                            <div class="text-right text-xs whitespace-nowrap text-slate-400 dark:text-slate-500">
                                                <time>{{ activity.time }}</time>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </li>
                            <li v-if="!props.recent_activities || props.recent_activities.length === 0" class="text-center py-4 text-xs text-slate-400 dark:text-slate-500 font-medium">
                                Belum ada aktivitas pendaftaran hari ini.
                            </li>
                        </ul>
                    </div>
                </div>

            </div>
        </div>
    </AdminLayout>
</template>