<script setup>
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import UserLayout from '@/Layouts/UserLayout.vue';

const props = defineProps({
    history_transactions: {
        type: Array,
        default: () => []
    }
});

const formatRupiah = (val) => 'Rp ' + Number(val || 0).toLocaleString('id-ID');

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric'
    }) + ', ' + d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
};

// Fungsi taktis parsing array log history CEIR secara aman
const parseCeirLogs = (metadata) => {
    if (!metadata) return null;
    let result = metadata.ceirku_result;
    if (typeof result === 'string') {
        try {
            result = JSON.parse(result);
        } catch (e) {
            return null;
        }
    }
    return Array.isArray(result) ? result : null;
};

// Helper mengekstrak pesan/alasan penolakan dari kolom metadata secara aman
const getRefundReason = (metadata) => {
    if (!metadata) return null;
    const parsed = typeof metadata === 'string' ? JSON.parse(metadata) : metadata;
    return parsed.ceirku_result || null;
};
</script>

<template>
    <Head title="Riwayat Diagnosis & Layanan" />
    <UserLayout>
        <div class="max-w-7xl mx-auto py-8 px-4 space-y-6">
            <div>
                <h1 class="text-2xl font-black text-gray-900 tracking-tight">Riwayat Transaksi</h1>
                <p class="text-xs text-gray-500 mt-1">Daftar transaksi dan status yang telah anda lakukan.</p>
            </div>

            <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
                <div v-if="!props.history_transactions || props.history_transactions.length === 0" class="p-12 text-center text-sm text-gray-400 italic">
                    Belum ada riwayat transaksi pengujian layanan pada akun Anda.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-gray-500">
                        <thead class="bg-gray-50 text-[10px] uppercase text-gray-400 font-bold border-b border-gray-100">
                            <tr>
                                <th class="px-6 py-4">Nomor Transaksi / Tanggal</th>
                                <th class="px-6 py-4">Jenis Layanan & Deskripsi</th>
                                <th class="px-6 py-4">Biaya </th>
                                <th class="px-6 py-4">Status Jaringan </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 font-sans">
                            <tr v-for="tx in (props.history_transactions ?? [])" :key="tx.id" class="hover:bg-slate-50/40 transition-colors vertical-align-top">
                                
                                <td class="px-6 py-5 whitespace-nowrap space-y-0.5">
                                    <span class="font-mono font-bold text-gray-900 block">{{ tx.invoice_number || tx.transaction_number }}</span>
                                    <span class="text-[10px] text-gray-400 block">{{ formatDate(tx.created_at) }}</span>
                                </td>
                                
                                <td class="px-6 py-5 font-medium text-gray-700 max-w-xs truncate-2-lines" :title="tx.description">
                                    {{ tx.description }}
                                </td>
                                
                                <td class="px-6 py-5 font-mono font-bold text-slate-900 whitespace-nowrap">
                                    {{ formatRupiah(tx.amount) }}
                                </td>
                                
                                <td class="px-6 py-5 min-w-[380px]">
                                    <div v-if="(tx.invoice_number || tx.transaction_number || '').startsWith('FEEHST') && parseCeirLogs(tx.metadata)" class="space-y-2">
                                        <div class="overflow-hidden border border-gray-200 rounded-xl shadow-inner bg-slate-50/50">
                                            <table class="w-full text-left text-[11px]">
                                                <thead class="bg-gray-100 text-gray-500 font-bold border-b border-gray-200">
                                                    <tr>
                                                        <th class="px-3 py-2 text-center w-8">No</th>
                                                        <th class="px-3 py-2">Tanggal</th>
                                                        <th class="px-3 py-2">Aksi</th>
                                                        <th class="px-3 py-2">Keterangan</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-200 bg-white text-gray-600">
                                                    <tr v-for="log in parseCeirLogs(tx.metadata)" :key="log.no" class="hover:bg-slate-50">
                                                        <td class="px-3 py-1.5 text-center font-bold font-mono text-gray-400 border-r border-gray-100 w-8">{{ log.no }}</td>
                                                        <td class="px-3 py-1.5 font-mono text-gray-500 whitespace-nowrap">{{ log.date ? log.date.split(' ')[0] : '-' }}</td>
                                                        <td class="px-3 py-1.5">
                                                            <span :class="[
                                                                log.action === 'add_roamer' ? 'text-blue-600 bg-blue-50 border border-blue-100' :
                                                                log.action === 'remove_roamer' ? 'text-rose-600 bg-rose-50 border border-rose-100' : 'text-gray-500 bg-gray-50 border border-gray-100',
                                                                'px-1.5 py-0.5 rounded text-[9px] font-mono font-bold uppercase border'
                                                            ]">{{ log.action }}</span>
                                                        </td>
                                                        <td class="px-3 py-1.5 font-medium text-gray-500 truncate max-w-[150px]" :title="log.note">{{ log.note || '-' }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    
                                    <div v-else-if="tx.metadata?.ceirku_result && typeof tx.metadata.ceirku_result === 'string' && (tx.invoice_number || '').startsWith('FEESL')">
                                        <span :class="[
                                            tx.metadata.ceirku_result === 'REGISTERED' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' :
                                            tx.metadata.ceirku_result === 'ROAMER' ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-slate-50 text-slate-700 border-slate-200',
                                            'px-3 py-1.5 rounded-lg text-xs font-black tracking-wider border border-solid font-mono inline-block'
                                        ]">{{ tx.metadata.ceirku_result }}</span>
                                    </div>
                                    
                                    <div v-else-if="(tx.invoice_number || '').startsWith('ROAM1M') || (tx.invoice_number || '').startsWith('ROAM3M')">
                                        <div class="space-y-1.5">
                                            <span :class="[
                                                (tx.metadata?.ceirku_status || '').toLowerCase() === 'success' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' :
                                                (tx.metadata?.ceirku_status || '').toLowerCase() === 'failed' ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-amber-50 text-amber-700 border-amber-200',
                                                'px-3 py-2 rounded-xl text-[11px] font-bold tracking-tight border border-solid inline-block leading-normal'
                                            ]">
                                                {{ tx.custom_status_text }}
                                            </span>

                                            <div v-if="(tx.metadata?.ceirku_status || '').toLowerCase() === 'failed' && getRefundReason(tx.metadata)" class="text-[10px] font-medium text-rose-600 bg-rose-50/50 border border-rose-100 rounded-lg p-2 max-w-sm mt-1">
                                                <span class="font-bold">Alasan Penolakan:</span> {{ getRefundReason(tx.metadata) }}
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div v-else>
                                        <div class="space-y-1.5">
                                            <span :class="[
                                                (tx.status === 'SUCCESS' || (tx.metadata?.ceirku_status || '').toUpperCase() === 'SUCCESS') ? 'bg-emerald-50 text-emerald-600 border border-emerald-100' :
                                                (tx.status === 'FAILED' || (tx.metadata?.ceirku_status || '').toUpperCase() === 'FAILED') ? 'bg-rose-50 text-rose-600 border border-rose-100' : 'bg-amber-50 text-amber-600 border border-amber-100',
                                                'px-2.5 py-1 rounded-full text-[10px] font-bold uppercase inline-block border'
                                            ]">
                                                {{ tx.metadata?.ceirku_status || tx.status }}
                                            </span>

                                            <div v-if="(tx.status === 'FAILED' || (tx.metadata?.ceirku_status || '').toUpperCase() === 'FAILED') && getRefundReason(tx.metadata)" class="text-[10px] font-medium text-rose-600 bg-rose-50/50 border border-rose-100 rounded-lg p-2 max-w-sm mt-1">
                                                <span class="font-bold">Keterangan:</span> {{ getRefundReason(tx.metadata) }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </UserLayout>
</template>

<style scoped>
.truncate-2-lines {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    white-space: normal;
}
.vertical-align-top td {
    vertical-align: top;
}
</style>