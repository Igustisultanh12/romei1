<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { GiftIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    vouchers: {
        type: Array,
        default: () => []
    }
});

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
        <Head title="Manajemen Voucher Diskon" />

        <div class="space-y-6 transition-colors duration-300">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
                        <GiftIcon class="w-7 h-7 text-blue-500" />
                        Manajemen Voucher
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Kelola kupon potongan harga dan promo aktif untuk registrasi IMEI pelanggan.</p>
                </div>
                <button class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold transition-all shadow-md">
                    <PlusIcon class="w-4 h-4" /> Tambah Voucher
                </button>
            </div>

            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm overflow-hidden transition-colors duration-300">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-800 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                                <th class="px-6 py-4">Kode Kupon</th>
                                <th class="px-6 py-4">Tipe Potongan</th>
                                <th class="px-6 py-4">Nilai Diskon</th>
                                <th class="px-6 py-4">Kuota Pemakaian</th>
                                <th class="px-6 py-4">Masa Berlaku</th>
                                <th class="px-6 py-4 text-center">Status</th>
                                <th class="px-6 py-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-800 text-xs font-medium text-slate-700 dark:text-slate-300">
                            <tr v-if="props.vouchers.length === 0">
                                <td colspan="7" class="px-6 py-10 text-center text-slate-400 dark:text-slate-500 font-bold">
                                    Belum ada kupon voucher promo yang dibuat.
                                </td>
                            </tr>
                            <tr v-for="voucher in props.vouchers" :key="voucher.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                                <td class="px-6 py-4 font-mono font-bold text-blue-600 dark:text-blue-400 uppercase tracking-wider">
                                    {{ voucher.code }}
                                </td>
                                <td class="px-6 py-4 capitalize text-slate-500 dark:text-slate-400">
                                    {{ voucher.discount_type === 'fixed' ? 'Potongan Tetap' : 'Persentase (%)' }}
                                </td>
                                <td class="px-6 py-4 font-bold text-slate-900 dark:text-white">
                                    {{ voucher.discount_type === 'fixed' ? formatRupiah(voucher.discount_value) : voucher.discount_value + '%' }}
                                </td>
                                <td class="px-6 py-4">
                                    <span class="font-bold text-slate-900 dark:text-white">{{ voucher.uses_count }}</span>
                                    <span class="text-slate-400"> / {{ voucher.max_uses ?? '∞' }} kali</span>
                                </td>
                                <td class="px-6 py-4 text-slate-400">
                                    {{ voucher.expires_at ? new Date(voucher.expires_at).toLocaleDateString('id-ID', {day: '2-digit', month: 'short', year: 'numeric'}) : 'Tanpa Expired' }}
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span :class="voucher.is_active ? 'bg-emerald-500/10 text-emerald-500 ring-emerald-500/20' : 'bg-red-500/10 text-red-500 ring-red-500/20'" class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-bold ring-1 ring-inset">
                                        {{ voucher.is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button class="p-1.5 text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30 rounded-md transition-all">
                                        <TrashIcon class="w-4 h-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>