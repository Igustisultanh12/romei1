<template>
    <AdminLayout>
        <div class="p-6 min-h-screen bg-transparent text-slate-800 dark:text-slate-150">
            <div class="max-w-7xl mx-auto">
                
                <div class="mb-6">
                    <Link :href="route('admin.customers.index')" class="text-sm font-bold text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400 flex items-center gap-1.5 transition-colors">
                        <ArrowLeftIcon class="w-4 h-4 shrink-0" />
                        <span>Kembali ke Daftar Pelanggan</span>
                    </Link>
                </div>

                <div v-if="$page.props.flash.success" class="mb-6 p-4 bg-emerald-500/10 border border-emerald-500/30 rounded-lg text-emerald-600 dark:text-emerald-400 text-sm font-medium flex items-center gap-2">
                    <CheckCircleIcon class="w-4 h-4 text-emerald-500 shrink-0" />
                    <span>{{ $page.props.flash.success }}</span>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <div class="space-y-6 lg:col-span-1">
                        
                        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 shadow-sm dark:shadow-xl relative overflow-hidden">
                            <div class="absolute top-0 right-0 p-3">
                                <span :class="customer.is_suspended ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20' : 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20'" class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border tracking-wide">
                                    {{ customer.is_suspended ? 'SUSPENDED' : 'ACTIVE' }}
                                </span>
                            </div>
                            <div class="flex items-center gap-4 mt-2">
                                <div class="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center text-xl font-black text-blue-600 dark:text-cyan-400 shadow-inner">
                                    {{ customer.name.charAt(0).toUpperCase() }}
                                </div>
                                <div class="truncate max-w-[160px]">
                                    <h2 class="text-base font-black text-slate-800 dark:text-white truncate" :title="customer.name">{{ customer.name }}</h2>
                                    <p class="text-[11px] font-semibold text-slate-400 mt-0.5">Gabung: {{ customer.created_at }}</p>
                                </div>
                            </div>
                            
                            <div class="mt-6 pt-6 border-t border-slate-100 dark:border-slate-800/60 space-y-3 text-xs font-semibold">
                                <div class="flex justify-between"><span class="text-slate-400">Total Perangkat:</span><span class="text-slate-800 dark:text-white">{{ customer.total_imei }} Perangkat</span></div>
                                <div class="flex justify-between"><span class="text-slate-400">ID Pengguna:</span><span class="text-slate-500 dark:text-slate-400 font-mono">#{{ customer.id }}</span></div>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 shadow-sm dark:shadow-xl">
                            <span class="text-[10px] text-slate-400 dark:text-slate-500 uppercase font-bold tracking-wider block">Saldo Internal Dompet</span>
                            <h3 class="text-3xl font-black text-blue-600 dark:text-cyan-400 mt-1">Rp {{ formatCurrency(customer.balance) }}</h3>
                            <p class="text-[11px] font-semibold text-slate-400 dark:text-slate-500 mt-2 leading-relaxed">
                                Saldo ini akan terpotong otomatis saat pengguna mengeksekusi layanan berbayar (Cek SIM LOCK / Histori CEIR / Paket Roamer) di platform ROMEI.
                            </p>
                        </div>

                        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 shadow-sm dark:shadow-xl">
                            <h4 class="text-xs font-bold text-slate-800 dark:text-white mb-4 flex items-center gap-1.5 uppercase tracking-wider">
                                <KeyIcon class="w-4 h-4 text-blue-500 shrink-0" />
                                <span>Paksa Reset Password</span>
                            </h4>
                            <form @submit.prevent="handleResetPassword" class="space-y-3">
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-400 mb-1">Password Baru</label>
                                    <input v-model="passwordForm.password" type="password" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg px-3 py-1.5 text-xs text-slate-800 dark:text-slate-200 focus:outline-none focus:border-blue-500 dark:focus:border-cyan-500 font-medium" required />
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-400 mb-1">Konfirmasi Password</label>
                                    <input v-model="passwordForm.password_confirmation" type="password" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg px-3 py-1.5 text-xs text-slate-800 dark:text-slate-200 focus:outline-none focus:border-blue-500 dark:focus:border-cyan-500 font-medium" required />
                                </div>
                                <button type="submit" :disabled="passwordForm.processing" class="w-full bg-blue-600 hover:bg-blue-500 dark:bg-cyan-600 dark:hover:bg-cyan-500 text-white py-2 rounded-lg text-xs font-bold tracking-wide transition-colors disabled:opacity-50 shadow-sm">
                                    Eksekusi Kredensial Baru
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="space-y-6 lg:col-span-2">
                        
                        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 shadow-sm dark:shadow-xl">
                            <h4 class="text-xs font-bold text-slate-800 dark:text-white mb-4 flex items-center gap-1.5 uppercase tracking-wider">
                                <PencilSquareIcon class="w-4 h-4 text-blue-500 shrink-0" />
                                <span>Edit Profil Pelanggan</span>
                            </h4>
                            <form @submit.prevent="handleUpdateProfile" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="md:col-span-2">
                                    <label class="block text-[11px] font-bold text-slate-400 mb-1">Nama Lengkap</label>
                                    <input v-model="profileForm.name" type="text" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:border-blue-500 dark:focus:border-cyan-500 font-bold" required />
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-400 mb-1">Alamat Email Aktif</label>
                                    <input v-model="profileForm.email" type="email" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:border-blue-500 dark:focus:border-cyan-500 font-bold" required />
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-400 mb-1">Nomor WhatsApp Notifikasi</label>
                                    <input v-model="profileForm.whatsapp_number" type="text" placeholder="Contoh: 628xxx" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:border-blue-500 dark:focus:border-cyan-500 font-bold" required />
                                </div>
                                <div class="md:col-span-2 flex justify-end pt-2">
                                    <button type="submit" :disabled="profileForm.processing" class="bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2 rounded-lg text-xs font-bold tracking-wide transition-colors disabled:opacity-50 shadow-sm">
                                        Simpan Perubahan 
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 shadow-sm dark:shadow-xl">
                            <h4 class="text-xs font-bold text-slate-800 dark:text-white mb-4 flex items-center gap-1.5 uppercase tracking-wider">
                                <CreditCardIcon class="w-4 h-4 text-blue-500 shrink-0" />
                                <span>Log Transaksi & Diagnosis Layanan</span>
                            </h4>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                                    <thead class="bg-slate-50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-200 uppercase text-[10px] font-bold tracking-wider border-b border-slate-200 dark:border-slate-800">
                                        <tr>
                                            <th class="px-4 py-3">No. Invoice</th>
                                            <th class="px-4 py-3">Deskripsi Aktivitas</th>
                                            <th class="px-4 py-3">Jumlah Potongan</th>
                                            <th class="px-4 py-3">Status</th>
                                            <th class="px-4 py-3">Tanggal Waktu</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/40 font-medium">
                                        <tr v-for="tx in transactions.data" :key="tx.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition-colors">
                                            <td class="px-4 py-3 font-mono text-slate-400 dark:text-slate-500 font-bold">{{ tx.invoice_number }}</td>
                                            <td class="px-4 py-3 text-slate-800 dark:text-white max-w-xs truncate font-bold" :title="tx.description">
                                                {{ tx.description }}
                                            </td>
                                            <td class="px-4 py-3 text-blue-600 dark:text-cyan-400 font-black">
                                                Rp {{ formatCurrency(tx.amount) }}
                                            </td>
                                            <td class="px-4 py-3">
                                                <span :class="tx.status === 'SUCCESS' ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-500 dark:text-amber-400'" class="font-black">
                                                    {{ tx.status }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-slate-400 dark:text-slate-500 font-semibold">
                                                {{ formatDate(tx.created_at) }}
                                            </td>
                                        </tr>
                                        <tr v-if="transactions.data.length === 0">
                                            <td colspan="5" class="px-4 py-8 text-center text-slate-400 dark:text-slate-500 font-semibold">
                                                Belum ada log riwayat transaksi finansial untuk user ini.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>
                
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { 
    ArrowLeftIcon, 
    CheckCircleIcon, 
    KeyIcon, 
    PencilSquareIcon, 
    CreditCardIcon 
} from '@heroicons/vue/24/outline';

const props = defineProps({
    customer: Object,
    transactions: Object
});

// Setup Form Edit Profil
const profileForm = useForm({
    name: props.customer.name,
    email: props.customer.email,
    whatsapp_number: props.customer.whatsapp_number,
});

// Setup Form Reset Kredensial Password
const passwordForm = useForm({
    password: '',
    password_confirmation: ''
});

const handleUpdateProfile = () => {
    profileForm.put(route('admin.customers.update_profile', props.customer.id), {
        preserveScroll: true
    });
};

const handleResetPassword = () => {
    passwordForm.put(route('admin.customers.reset_password', props.customer.id), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset()
    });
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID').format(value);
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};
</script>