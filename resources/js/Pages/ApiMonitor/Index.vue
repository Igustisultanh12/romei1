<script setup>
import { ref, onBeforeUnmount } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { 
    CpuChipIcon, 
    CheckCircleIcon, 
    ExclamationTriangleIcon, 
    ArrowPathIcon,
    WalletIcon,
    CreditCardIcon,
    QrCodeIcon,
    XMarkIcon,
    ClockIcon,
    XCircleIcon
} from '@heroicons/vue/24/outline';
import axios from 'axios';

const props = defineProps({
    apis: {
        type: Array,
        default: () => []
    },
    ceir_balance: {
        type: Number,
        default: 0
    },
    active_gateway: {
        type: String,
        default: 'doku'
    }
});

// State reaktif murni
const txAmount = ref(10000);
const txPaymentMethod = ref('qris');
const isSimulating = ref(false);
const isRefreshing = ref(false); // State loader untuk tombol tes ulang koneksi

// State untuk memunculkan portal Iframe DOKU langsung di dalam aplikasi
const showDokuModal = ref(false);
const activePaymentUrl = ref('');
const currentInvoiceId = ref('');

// State untuk Notifikasi Alert Toast di Pojok Layar
const toastNotification = ref(null);
let checkStatusInterval = null;

const showToast = (status, title, message) => {
    toastNotification.value = { status, title, message };
    setTimeout(() => {
        toastNotification.value = null;
    }, 6000);
};

// Fungsi pembersih loop interval agar memory tidak bocor
const clearStatusChecker = () => {
    if (checkStatusInterval) {
        clearInterval(checkStatusInterval);
        checkStatusInterval = null;
    }
};

// Loop Checker Realtime untuk mendeteksi status transaksi di database/gateway
const startCheckingPaymentStatus = (invoiceId) => {
    clearStatusChecker();

    checkStatusInterval = setInterval(() => {
        // Endpoint pengecekan status internal ROMEI
        axios.get(`/api/admin/monitoring/check-status/${invoiceId}`)
            .then((res) => {
                // Begitu status transaksi terdeteksi sukses dibayar oleh pelanggan/kasir
                if (res.data.status === 'success' || res.data.payment_status === 'SUCCESS' || res.data.is_paid) {
                    clearStatusChecker();
                    
                    // AUTOMATIC CLOSE: Portal DOKU langsung menutup diri otomatis
                    showDokuModal.value = false;
                    activePaymentUrl.value = '';
                    
                    // LANGSUNG ATUR NOTIFIKASI BERHASIL
                    showToast(
                        'success', 
                        'Pembayaran Berhasil', 
                        `Transaksi ${invoiceId} telah sukses dibayar! Sistem otomatis memperbarui data invoice.`
                    );

                    refreshMonitoring();
                }
            })
            .catch((err) => {
                // Fallback aman jika terdapat gangguan pembacaan status
            });
    }, 3000); // Mengecek setiap 3 detik sekali secara realtime
};

const runPaymentSimulation = () => {
    isSimulating.value = true;
    toastNotification.value = null;

    axios.post('/api/admin/monitoring/test-payment', {
        amount: parseInt(txAmount.value),
        payment_method: txPaymentMethod.value
    })
    .then((response) => {
        if (response.data.status === 'success' && response.data.payment_url) {
            currentInvoiceId.value = response.data.invoice_id;
            activePaymentUrl.value = response.data.payment_url;
            
            // LANGSUNG MUNCULKAN secara POPUP OVERLAY IFRAME DI DALAM HALAMAN
            showDokuModal.value = true;

            showToast('info', 'Portal Terbuka', 'Silakan lakukan scan / penyelesaian pembayaran pada popup internal.');

            // Mulai jalankan mesin deteksi pembayaran otomatis
            startCheckingPaymentStatus(response.data.invoice_id);
        } else {
            showToast('error', 'Gateway Error', 'Gagal memuat URL halaman pembayaran dari respon DOKU.');
        }
    })
    .catch((error) => {
        const errorMsg = error.response?.data?.message || 'DOKU Live Gateway menolak payload mas. Periksa parameter signature.';
        showToast('error', 'Transaksi Ditolak', errorMsg);
    })
    .finally(() => {
        isSimulating.value = false;
    });
};

const closeDokuModalManual = () => {
    clearStatusChecker();
    showDokuModal.value = false;
    activePaymentUrl.value = '';
    showToast('info', 'Popup Ditutup', 'Pengujian portal pembayaran dihentikan oleh admin.');
};

const refreshMonitoring = () => {
    isRefreshing.value = true;
    // Menggunakan router reload Inertia untuk menarik ulang data 'apis' dan 'ceir_balance' dari controller backend
    router.reload({ 
        only: ['apis', 'ceir_balance'],
        onFinish: () => {
            isRefreshing.value = false;
            showToast('success', 'Sinkronisasi Berhasil', 'Status latensi dan kuota saldo API telah diperbarui secara aktual.');
        }
    });
};

const formatRupiah = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(value);
};

const getLatencyClass = (latency) => {
    if (latency === '--') return 'text-slate-400';
    const ms = parseInt(latency);
    if (ms > 300) return 'text-red-500 font-bold';
    if (ms > 150) return 'text-amber-500';
    return 'text-emerald-500';
};

// Pastikan interval mati jika user pindah menu
onBeforeUnmount(() => {
    clearStatusChecker();
});
</script>

<template>
    <AdminLayout>
        <Head title="Realtime API Monitoring Hub" />

        <div class="space-y-6 transition-colors duration-300 relative">
            
            <div v-if="toastNotification" class="fixed top-6 right-6 z-50 max-w-sm w-full border rounded-xl shadow-2xl p-4 animate-slide-in backdrop-blur-md"
                :class="[
                    toastNotification.status === 'success' ? 'bg-emerald-500 border-emerald-600 text-white' : '',
                    toastNotification.status === 'error' ? 'bg-red-500 border-red-600 text-white' : '',
                    toastNotification.status === 'info' ? 'bg-blue-600 border-blue-700 text-white' : ''
                ]">
                <div class="flex items-start gap-3">
                    <CheckCircleIcon v-if="toastNotification.status === 'success'" class="w-5 h-5 shrink-0 mt-0.5" />
                    <ExclamationTriangleIcon v-else class="w-5 h-5 shrink-0 mt-0.5" />
                    <div class="space-y-0.5">
                        <h4 class="text-xs font-black uppercase tracking-wider">{{ toastNotification.title }}</h4>
                        <p class="text-[11px] font-medium opacity-95 leading-relaxed font-mono text-left">{{ toastNotification.message }}</p>
                    </div>
                </div>
            </div>

            <div v-if="showDokuModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm transition-opacity" @click="closeDokuModalManual"></div>

                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl w-full max-w-lg h-[88vh] sm:h-[80vh] flex flex-col shadow-2xl transform transition-all relative z-10 overflow-hidden">
                    <div class="px-3.5 sm:px-4 py-3 bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2 truncate">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse shrink-0"></span>
                            <span class="text-[11px] sm:text-xs font-black text-slate-700 dark:text-slate-300 uppercase tracking-wider font-mono truncate">{{ currentInvoiceId }}</span>
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0">
                            <a
                                :href="activePaymentUrl"
                                target="_blank"
                                class="inline-flex items-center gap-1 px-2.5 py-1 text-[10px] sm:text-[11px] font-bold bg-blue-50 hover:bg-blue-100 dark:bg-blue-500/20 text-blue-600 dark:text-blue-300 rounded-lg border border-blue-200 dark:border-blue-500/30 transition shadow-sm"
                                title="Buka di tab penuh (disarankan untuk pembayaran via e-wallet di HP)"
                            >
                                <span>Tab Penuh ↗</span>
                            </a>
                            <button @click="closeDokuModalManual" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800">
                                <XMarkIcon class="w-5 h-5" />
                            </button>
                        </div>
                    </div>
                    <div class="flex-1 bg-white">
                        <iframe :src="activePaymentUrl" class="w-full h-full border-0" allow="geolocation; microphone; camera font-mono"></iframe>
                    </div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
                        <CpuChipIcon class="w-7 h-7 text-blue-500" />
                        Monitoring Integrasi API
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        Pantau status latensi jembatan data eksternal penyedia verifikasi IMEI dan payment network secara aktual (Live Production).
                    </p>
                </div>
                <button @click="refreshMonitoring" :disabled="isRefreshing" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 rounded-lg text-xs font-bold transition-all shadow-sm disabled:opacity-60">
                    <ArrowPathIcon class="w-4 h-4" :class="{'animate-spin': isRefreshing}" /> 
                    {{ isRefreshing ? 'Mengecek...' : 'Tes Ulang Koneksi' }}
                </button>
            </div>

            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-xl p-6 shadow-sm flex items-center justify-between transition-all">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 rounded-xl">
                        <WalletIcon class="w-7 h-7" />
                    </div>
                    <div>
                        <span class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider block">Sisa Saldo Kuota CEIRKU.ID</span>
                        <h3 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight mt-0.5">
                            {{ formatRupiah(props.ceir_balance) }}
                        </h3>
                    </div>
                </div>
                <span class="text-[11px] text-slate-400 max-w-[220px] text-right hidden sm:block">
                    Information saldo kuota aktual dipetakan riil dari rute internal `/api/v1/balance`.
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div v-for="(api, index) in props.apis" :key="index" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 shadow-sm flex flex-col justify-between transition-colors duration-300 space-y-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-1.5 flex-1 min-w-0">
                            <h4 class="text-sm font-bold text-slate-900 dark:text-white tracking-tight truncate">{{ api.name }}</h4>
                            <span class="font-mono text-[10px] bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-1.5 py-0.5 rounded inline-block truncate max-w-full">
                                {{ api.endpoint }}
                            </span>
                        </div>
                        <div class="shrink-0 text-right">
                            <span v-if="api.status === 'online'" class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs font-bold text-emerald-600 dark:text-emerald-400 ring-1 ring-inset ring-emerald-500/20">
                                <CheckCircleIcon class="w-3.5 h-3.5" /> Operasional
                            </span>
                            <span v-else-if="api.status === 'warning'" class="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2.5 py-0.5 text-xs font-bold text-amber-600 dark:text-amber-400 ring-1 ring-inset ring-amber-500/20">
                                <ExclamationTriangleIcon class="w-3.5 h-3.5" /> Terhubung
                            </span>
                            <span v-else-if="api.status === 'standby'" class="inline-flex items-center gap-1 rounded-full bg-blue-500/10 px-2.5 py-0.5 text-xs font-bold text-blue-600 dark:text-blue-400 ring-1 ring-inset ring-blue-500/20">
                                <ClockIcon class="w-3.5 h-3.5" /> Siaga (Standby)
                            </span>
                            <span v-else-if="api.status === 'maintenance'" class="inline-flex items-center gap-1 rounded-full bg-orange-500/10 px-2.5 py-0.5 text-xs font-bold text-orange-600 dark:text-orange-400 ring-1 ring-inset ring-orange-500/20">
                                <ExclamationTriangleIcon class="w-3.5 h-3.5" /> Pemeliharaan
                            </span>
                            <span v-else class="inline-flex items-center gap-1 rounded-full bg-red-500/10 px-2.5 py-0.5 text-xs font-bold text-red-600 dark:text-red-400 ring-1 ring-inset ring-red-500/20">
                                <XCircleIcon class="w-3.5 h-3.5" /> Terputus
                            </span>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-100 dark:border-slate-800/80 space-y-1.5">
                        <div class="text-xs text-slate-400 flex items-center justify-between">
                            <span>Latensi Respon Jaringan</span>
                            <strong :class="getLatencyClass(api.latency)" class="font-mono">{{ api.latency }}</strong>
                        </div>
                        <p v-if="api.detail" class="text-[11px] leading-relaxed font-medium"
                            :class="{
                                'text-slate-500 dark:text-slate-400': api.status === 'online',
                                'text-amber-600 dark:text-amber-400': api.status === 'warning',
                                'text-blue-600 dark:text-blue-400': api.status === 'standby',
                                'text-orange-600 dark:text-orange-400': api.status === 'maintenance',
                                'text-rose-600 dark:text-rose-400': api.status === 'offline'
                            }"
                        >
                            {{ api.detail }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 shadow-sm space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pb-2 border-b border-slate-100 dark:border-slate-800">
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white tracking-tight">
                            Uji Coba Gate Transaksi {{ props.active_gateway === 'qrqu' ? 'QRqu Gateway' : 'DOKU Gateway' }}
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Tembak langsung request pembuatan invoice live ke engine {{ props.active_gateway === 'qrqu' ? 'QRqu' : 'DOKU' }} untuk memastikan keabsahan integrasi dan respon QRIS.
                        </p>
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold self-start sm:self-auto"
                        :class="props.active_gateway === 'qrqu' ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800' : 'bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800'"
                    >
                        <span class="w-2 h-2 rounded-full animate-pulse" :class="props.active_gateway === 'qrqu' ? 'bg-indigo-500' : 'bg-blue-500'"></span>
                        Gateway: {{ props.active_gateway === 'qrqu' ? 'QRqu' : 'DOKU' }}
                    </span>
                </div>
                <div class="max-w-md space-y-4">
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-slate-400">Nominal Transaksi Asli (IDR)</label>
                            <span class="text-[11px] text-slate-400 font-mono">Min: Rp 1</span>
                        </div>
                        <input v-model="txAmount" type="number" min="1" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-xs font-bold px-3 py-2 text-slate-900 dark:text-white focus:ring-1 focus:ring-blue-500 font-mono" />
                        <div class="flex flex-wrap gap-1.5 pt-1">
                            <button v-for="amt in [1, 1000, 10000, 50000]" :key="amt" type="button" @click="txAmount = amt" class="px-2 py-0.5 rounded text-[10px] font-bold border transition" :class="txAmount === amt ? 'bg-blue-600 text-white border-blue-600' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-200 dark:hover:bg-slate-700'">
                                Rp {{ amt.toLocaleString('id-ID') }}
                            </button>
                        </div>
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-400">Metode Transaksi</label>
                        <div class="grid grid-cols-2 gap-3">
                            <button type="button" @click="txPaymentMethod = 'qris'" :class="txPaymentMethod === 'qris' ? 'border-blue-500 bg-blue-500/10 text-blue-500' : 'border-slate-200 dark:border-slate-800 text-slate-400'" class="border rounded-lg p-3 text-center flex flex-col items-center gap-1.5 transition-all">
                                <QrCodeIcon class="w-5 h-5" />
                                <span class="text-[10px] font-bold uppercase">QRIS Live</span>
                            </button>
                            <button type="button" @click="txPaymentMethod = 'shopeepay'" :class="txPaymentMethod === 'shopeepay' ? 'border-orange-500 bg-orange-500/10 text-orange-500' : 'border-slate-200 dark:border-slate-800 text-slate-400'" class="border rounded-lg p-3 text-center flex flex-col items-center gap-1.5 transition-all">
                                <CreditCardIcon class="w-5 h-5" />
                                <span class="text-[10px] font-bold uppercase">ShopeePay</span>
                            </button>
                        </div>
                    </div>
                    <button @click="runPaymentSimulation" :disabled="isSimulating" class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 disabled:bg-slate-400 text-white rounded-lg text-xs font-bold transition-all shadow-md flex items-center justify-center gap-2">
                        <ArrowPathIcon v-if="isSimulating" class="w-4 h-4 animate-spin" />
                        {{ isSimulating ? ('Membuka Jendela ' + (props.active_gateway === 'qrqu' ? 'QRqu' : 'DOKU') + ' Live...') : ('Tembak Transaksi ' + (props.active_gateway === 'qrqu' ? 'QRqu' : 'DOKU')) }}
                    </button>
                </div>
            </div>

        </div>
    </AdminLayout>
</template>

<style scoped>
@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
.animate-slide-in {
    animation: slideIn 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
</style>