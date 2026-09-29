<script setup>
import { ref, onBeforeUnmount } from 'vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import UserLayout from '@/Layouts/UserLayout.vue';
import { BanknotesIcon, ArrowUpRightIcon, ArrowDownLeftIcon, WalletIcon } from '@heroicons/vue/24/outline';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
    wallet: Object,
    transactions: Array
});

const page = usePage();

// State Kendali Tampilan & Integrasi Pembayaran
const isDepositModalOpen = ref(false);
const showQrisDisplay = ref(false);
const qrisPaymentUrl = ref('');
const activeTransactionNumber = ref('');
const isGenerating = ref(false);
let pollingInterval = null;

const depositForm = useForm({
    amount: ''
});

const openDepositModal = () => { 
    isDepositModalOpen.value = true; 
    showQrisDisplay.value = false;
    qrisPaymentUrl.value = '';
    activeTransactionNumber.value = '';
};

const closeDepositModal = () => { 
    isDepositModalOpen.value = false; 
    showQrisDisplay.value = false;
    depositForm.reset(); 
    stopPaymentPolling();
};

/**
 * STRATEGI UTAMA: GENERATE QRIS VIA AXIOS (MENGGUNAKAN JALUR URL YANG BENAR)
 */
const submitDeposit = () => {
    if (depositForm.amount < 1) {
        Swal.fire({
            title: 'Nominal Kurang',
            text: 'Minimal pengisian saldo deposit adalah Rp 1',
            icon: 'warning',
            confirmButtonColor: '#4f46e5'
        });
        return;
    }

    isGenerating.value = true;

    // Menembak ke endpoint wallet murni /wallet/deposit
    axios.post('/wallet/deposit', {
        amount: depositForm.amount
    })
    .then((response) => {
        // Menangkap data invoice_number dan payment_url resmi dari return JSON
        const txNumber = response.data.invoice_number || response.data.transaction_number;
        const linkQris = response.data.payment_url;
        
        if (txNumber && linkQris) {
            activeTransactionNumber.value = txNumber;
            qrisPaymentUrl.value = linkQris; 
            showQrisDisplay.value = true;

            // Jalankan mesin pemantau status pembayaran realtime
            startPaymentPolling(txNumber);
        } else {
            Swal.fire('Gagal', 'Sistem tidak berhasil merumuskan kode invoice atau link bayar dari DOKU.', 'error');
        }
    })
    .catch((error) => {
        Swal.fire('Error 500', error.response?.data?.message || 'Gagal terhubung ke modul jurnaling finansial.', 'error');
    })
    .finally(() => {
        isGenerating.value = false;
    });
};

/**
 * SISTEM POLLING BERKALA: MENEMBAK ROUTE FINANSIAL
 */
const startPaymentPolling = (txNumber) => {
    stopPaymentPolling();

    // Hit berkala ke server setiap 3.5 detik untuk mengecek status transaksi di cache dan memicu jurnal saldo
    pollingInterval = setInterval(() => {
        axios.get(`/wallet/check-status/${txNumber}`)
            .then((response) => {
                // Mendeteksi perubahan flag sukses yang dilempar oleh DOKU Webhook atau Simulasi
                if (response.data.payment_status === 'SUCCESS' || response.data.status === 'success') {
                    stopPaymentPolling();
                    showQrisDisplay.value = false;
                    isDepositModalOpen.value = false;

                    Swal.fire({
                        title: 'Top Up Berhasil!',
                        text: `Selamat, saldo sebesar Rp ${Number(depositForm.amount).toLocaleString('id-ID')} telah ditambahkan ke wallet Anda.`,
                        icon: 'success',
                        confirmButtonColor: '#4f46e5'
                    }).then(() => {
                        // Tarik data parsial dompet terbaru secara dinamis tanpa hard reload browser
                        router.reload({ only: ['wallet', 'transactions'] });
                    });
                }
            })
            .catch((err) => {
                console.error('Mesin polling mendeteksi gangguan api check-status wallet:', err);
            });
    }, 3500);
};

const stopPaymentPolling = () => {
    if (pollingInterval) {
        clearInterval(pollingInterval);
        pollingInterval = null;
    }
};

onBeforeUnmount(() => {
    stopPaymentPolling();
});

const formatRupiah = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val);
};

// FIX UTAMA FRONTEND: Fungsi taktis mendeteksi riwayat transaksi pengembalian saldo (refund) secara berlapis
const isRefund = (tx) => {
    const invoice = tx.invoice_number || '';
    const type = tx.type || '';
    return invoice.startsWith('REFUND-') || type.toLowerCase() === 'refund';
};
</script>

<template>
    <UserLayout>
        <Head title="Dompet & Saldo Wallet" />

        <div class="py-8 px-4 max-w-5xl mx-auto space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-black text-gray-900 tracking-tight">Wallet internal Anda</h1>
                    <p class="text-xs text-gray-500 mt-1">Gunakan saldo dompet digital ROMEI untuk mempermudah pemrosesan refund instan.</p>
                </div>
                <button @click="openDepositModal" class="bg-gray-900 hover:bg-gray-800 text-white font-bold text-xs px-4 py-2.5 rounded-xl shadow-sm transition-all flex items-center space-x-2">
                    <BanknotesIcon class="w-4 h-4" />
                    <span>Isi Saldo (Top Up)</span>
                </button>
            </div>

            <div class="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm flex items-center justify-between">
                <div class="space-y-1">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400 block">Saldo Aktif Saat Ini</span>
                    <h2 class="text-3xl font-black tracking-tight text-gray-950 font-mono">{{ formatRupiah(props.wallet?.balance ?? 0) }}</h2>
                </div>
                <div class="bg-slate-50 p-3 rounded-xl border border-gray-100">
                    <WalletIcon class="w-6 h-6 text-gray-800" />
                </div>
            </div>

            <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-50 bg-gray-50/50">
                    <h3 class="text-xs font-black text-gray-900 uppercase tracking-wider">Mutasi Top Up Saldo</h3>
                </div>

                <div v-if="props.transactions.length === 0" class="p-12 text-center text-sm text-gray-400 italic">
                    Belum ada riwayat perputaran dana pada akun Anda.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-gray-500">
                        <thead class="bg-gray-50 text-[10px] uppercase text-gray-400 font-bold border-b border-gray-100">
                            <tr>
                                <th class="px-6 py-4">Jenis Transaksi</th>
                                <th class="px-6 py-4">Keterangan</th>
                                <th class="px-6 py-4 text-right">Nominal</th>
                                <th class="px-6 py-4 text-right">Sisa Saldo</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 font-sans">
                            <tr v-for="tx in props.transactions" :key="tx.id" class="hover:bg-slate-50/40 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span v-if="isRefund(tx)" class="px-2.5 py-1 rounded-full text-xs font-bold bg-rose-50 border border-rose-100 text-rose-600 inline-flex items-center uppercase">
                                        <ArrowDownLeftIcon class="w-3 h-3 mr-1" />
                                        <span>Refund</span>
                                    </span>
                                    <span v-else :class="[
                                        ['topup', 'DEPOSIT', 'referral_commission'].includes(tx.type) ? 'text-green-600 bg-green-50 border-green-100' : 'text-red-600 bg-red-50 border-red-100',
                                        'px-2.5 py-1 rounded-full text-xs font-bold inline-flex items-center uppercase border'
                                    ]">
                                        <ArrowDownLeftIcon class="w-3 h-3 mr-1" v-if="['topup', 'DEPOSIT', 'referral_commission'].includes(tx.type)" />
                                        <ArrowUpRightIcon class="w-3 h-3 mr-1" v-else />
                                        <span>{{ tx.type ? tx.type.replace('_', ' ') : 'PENGELUARAN' }}</span>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-700 font-medium">
                                    <div class="font-bold text-gray-900">{{ tx.description }}</div>
                                    <div class="text-[10px] text-gray-400 font-mono mt-0.5">#{{ tx.invoice_number }}</div>
                                </td>
                                <td :class="[isRefund(tx) ? 'text-rose-600' : (['topup', 'DEPOSIT', 'referral_commission'].includes(tx.type) ? 'text-green-600' : 'text-red-600'), 'px-6 py-4 text-right font-bold font-mono whitespace-nowrap']">
                                    {{ isRefund(tx) || ['topup', 'DEPOSIT', 'referral_commission'].includes(tx.type) ? '+' : '-' }} {{ formatRupiah(tx.amount) }}
                                </td>
                                <td class="px-6 py-4 text-right font-bold text-gray-900 font-mono whitespace-nowrap">{{ formatRupiah(tx.balance_after ?? tx.amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div v-if="isDepositModalOpen" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-2xl max-w-md w-full p-6 space-y-4 transition-all duration-300 max-h-[95vh] overflow-y-auto">
                
                <div v-if="!showQrisDisplay" class="space-y-4">
                    <h3 class="text-lg font-bold text-gray-900">Isi Ulang Saldo Wallet</h3>
                    <form @submit.prevent="submitDeposit" class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Nominal Top Up (Rp)</label>
                            <input type="number" v-model="depositForm.amount" required min="1" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 font-bold" placeholder="Masukkan nominal top up..." />
                        </div>
                        <div class="flex justify-end space-x-2 pt-2">
                            <button type="button" @click="closeDepositModal" class="px-4 py-2 border border-gray-200 text-gray-500 rounded-lg text-sm hover:bg-gray-50 font-medium">Batal</button>
                            <button type="submit" :disabled="isGenerating" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg text-sm disabled:opacity-50">
                                <span v-if="isGenerating">Generating...</span>
                                <span v-else>Generate QRIS</span>
                            </button>
                        </div>
                    </form>
                </div>

                <div v-else class="text-center space-y-4 py-2">
                    <h3 class="text-md font-bold text-gray-900">Pindai QRIS Resmi ROMEI</h3>
                    <p class="text-xs text-gray-400 font-medium px-2">Silakan lakukan pemindaian menggunakan aplikasi perbankan atau e-wallet pilihan Anda.</p>
                    
                    <div class="w-full h-[500px] mx-auto bg-gray-50 border border-gray-100 rounded-xl flex items-center justify-center overflow-hidden p-1 shadow-inner relative">
                        <iframe 
                            :src="qrisPaymentUrl" 
                            class="absolute border-0 rounded-lg origin-top" 
                            style="width: 700px; height: 800px; transform: scale(0.6); transform-origin: top center; top: 0;"
                            scrolling="no"
                        ></iframe>
                    </div>

                    <div class="flex items-center justify-center space-x-2 text-xs text-indigo-600 font-bold bg-indigo-50 py-2.5 rounded-xl animate-pulse">
                        <svg class="animate-spin h-3.5 w-3.5 text-indigo-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>Menanti Sinkronisasi Pembayaran...</span>
                    </div>

                    <button type="button" @click="closeDepositModal" class="w-full py-2 border border-gray-200 text-gray-400 font-bold text-xs rounded-xl hover:bg-gray-50 transition-all">
                        Tutup & Batalkan Pengajuan
                    </button>
                </div>

            </div>
        </div>
    </UserLayout>
</template>