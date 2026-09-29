<script setup>
import { useForm, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue'; 
import Swal from 'sweetalert2';
import axios from 'axios';
import {
    CpuChipIcon,
    CurrencyDollarIcon,
    CreditCardIcon,
    PhotoIcon,
    WrenchScrewdriverIcon,
    CheckCircleIcon,
    ArrowPathIcon,
    ExclamationTriangleIcon,
    GlobeAltIcon,
    ClipboardDocumentIcon,
    ClipboardDocumentCheckIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    settings: Object
});

const form = useForm({
    ceirku_mode: props.settings.ceirku_mode || 'sandbox',
    ceirku_api_url: props.settings.ceirku_api_url || 'https://ceirku.net/api/v1',
    ceirku_api_key: props.settings.ceirku_api_key || '',
    
    fee_check_sim_lock: props.settings.fee_check_sim_lock ?? 5000,
    fee_check_ceir_history: props.settings.fee_check_ceir_history ?? 7500,
    fee_add_roamer_1m: props.settings.fee_add_roamer_1m ?? 135000,
    fee_add_roamer_3m: props.settings.fee_add_roamer_3m ?? 180000,

    payment_gateway_provider: props.settings.payment_gateway_provider || 'doku',
    doku_client_id: props.settings.doku_client_id || '',
    doku_secret_key: props.settings.doku_secret_key || '',

    qrqu_api_url: props.settings.qrqu_api_url || 'http://localhost:8000',
    qrqu_api_key: props.settings.qrqu_api_key || '',
    qrqu_api_secret: props.settings.qrqu_api_secret || '',
    qrqu_webhook_secret: props.settings.qrqu_webhook_secret || '',

    maintenance_mode: props.settings.maintenance_mode || false,
    beranda_image: null, 
});

const imagePreview = ref(props.settings.beranda_image_url || null);
const testingCeirku = ref(false);
const ceirkuTestResult = ref(null);

const testingQrqu = ref(false);
const qrquTestResult = ref(null);
const copiedQrquWebhook = ref(false);
const qrquWebhookUrl = ref(props.settings.qrqu_webhook_url || (typeof window !== 'undefined' ? (window.location.origin + '/api/webhook/qrqu') : 'https://romei.my.id/api/webhook/qrqu'));

const currentServerIp = ref(props.settings.server_ip || '127.0.0.1');
const copiedIp = ref(false);
const isDetectingIp = ref(false);

const copyServerIp = async () => {
    if (!currentServerIp.value) return;
    try {
        await navigator.clipboard.writeText(currentServerIp.value);
        copiedIp.value = true;
        setTimeout(() => {
            copiedIp.value = false;
        }, 2500);
    } catch (e) {
        const textarea = document.createElement('textarea');
        textarea.value = currentServerIp.value;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        copiedIp.value = true;
        setTimeout(() => {
            copiedIp.value = false;
        }, 2500);
    }
};

const refreshServerIp = async () => {
    isDetectingIp.value = true;
    try {
        const response = await axios.post(route('admin.settings.detect-ip'));
        if (response.data.success && response.data.ip) {
            currentServerIp.value = response.data.ip;
            Swal.fire({
                title: 'IP Terdeteksi',
                text: `IP Publik Server ROMEI: ${response.data.ip}`,
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
        }
    } catch (err) {
        Swal.fire({
            title: 'Deteksi Gagal',
            text: 'Gagal mendeteksi IP publik server secara realtime.',
            icon: 'error',
            confirmButtonColor: '#ef4444'
        });
    } finally {
        isDetectingIp.value = false;
    }
};

const testCeirkuConnection = async () => {
    if (!form.ceirku_api_url) {
        Swal.fire({
            title: 'URL Kosong',
            text: 'Harap masukkan URL Gateway CEIRKU terlebih dahulu.',
            icon: 'warning',
            confirmButtonColor: '#2563eb'
        });
        return;
    }

    testingCeirku.value = true;
    ceirkuTestResult.value = null;

    try {
        const response = await axios.post(route('admin.settings.test-ceirku'), {
            ceirku_api_url: form.ceirku_api_url,
            ceirku_api_key: form.ceirku_api_key
        });

        ceirkuTestResult.value = {
            success: true,
            latency: response.data.latency,
            message: response.data.message,
            statusCode: response.data.status_code
        };

        Swal.fire({
            title: 'Koneksi Berhasil Terhubung',
            text: response.data.message,
            icon: 'success',
            confirmButtonColor: '#2563eb'
        });
    } catch (err) {
        const errorMsg = err.response?.data?.message || 'Gagal menghubungi endpoint CEIRKU.';
        ceirkuTestResult.value = {
            success: false,
            latency: err.response?.data?.latency || '--',
            message: errorMsg,
            statusCode: err.response?.data?.status_code || 500
        };

        Swal.fire({
            title: 'Koneksi Gagal / Periksa Kredensial',
            text: errorMsg,
            icon: 'warning',
            confirmButtonColor: '#ef4444'
        });
    } finally {
        testingCeirku.value = false;
    }
};

const copyQrquWebhook = async () => {
    try {
        await navigator.clipboard.writeText(qrquWebhookUrl.value);
        copiedQrquWebhook.value = true;
        setTimeout(() => { copiedQrquWebhook.value = false; }, 2500);
    } catch (e) {
        const textarea = document.createElement('textarea');
        textarea.value = qrquWebhookUrl.value;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        copiedQrquWebhook.value = true;
        setTimeout(() => { copiedQrquWebhook.value = false; }, 2500);
    }
};

const testQrquConnection = async () => {
    if (!form.qrqu_api_url) {
        Swal.fire({
            title: 'URL Kosong',
            text: 'Harap masukkan URL Gateway QRqu terlebih dahulu.',
            icon: 'warning',
            confirmButtonColor: '#2563eb'
        });
        return;
    }

    // Auto-normalisasi Base URL di frontend jika user menempelkan /api atau /api/v1 atau trailing slash
    let cleanUrl = form.qrqu_api_url.trim().replace(/\/+$/, '');
    cleanUrl = cleanUrl.replace(/\/(api\/v1|api|v1)(\/invoices|\/account)?\/?$/i, '');
    form.qrqu_api_url = cleanUrl;

    testingQrqu.value = true;
    qrquTestResult.value = null;

    try {
        const response = await axios.post(route('admin.settings.test-qrqu'), {
            qrqu_api_url: form.qrqu_api_url,
            qrqu_api_key: form.qrqu_api_key,
            qrqu_api_secret: form.qrqu_api_secret,
        });

        qrquTestResult.value = {
            success: true,
            latency: response.data.latency,
            message: response.data.message,
            statusCode: response.data.status_code,
            isAuthOk: response.data.is_auth_ok,
        };

        Swal.fire({
            title: 'Koneksi Berhasil Terhubung',
            text: response.data.message,
            icon: 'success',
            confirmButtonColor: '#2563eb'
        });
    } catch (err) {
        const errorMsg = err.response?.data?.message || 'Gagal menghubungi endpoint QRqu.';
        const statusCode = err.response?.data?.status_code || 500;
        qrquTestResult.value = {
            success: false,
            latency: err.response?.data?.latency || '--',
            message: errorMsg,
            statusCode: statusCode,
            isAuthOk: false,
        };

        Swal.fire({
            title: statusCode === 404 ? 'Endpoint Tidak Ditemukan' : 'Koneksi Gagal / Periksa Kredensial',
            text: errorMsg,
            icon: 'warning',
            confirmButtonColor: '#ef4444'
        });
    } finally {
        testingQrqu.value = false;
    }
};

const handleImageUpload = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.beranda_image = file;
        imagePreview.value = URL.createObjectURL(file);
    }
};

const saveSettings = () => {
    Swal.fire({
        title: 'Menyimpan Perubahan',
        text: 'Sedang memperbarui seluruh parameter basis data...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    form.post(route('admin.settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.beranda_image = null;
            Swal.fire({
                title: 'Berhasil Disimpan',
                text: 'Konfigurasi Gateway CEIRKU, Skema Tarif Finansial, dan Aset Visual sukses diperbarui.',
                icon: 'success',
                confirmButtonColor: '#2563eb'
            });
        },
        onError: () => {
            Swal.fire({
                title: 'Gagal Menyimpan',
                text: 'Periksa kembali kelayakan data input atau format file gambar Anda.',
                icon: 'error',
                confirmButtonColor: '#ef4444'
            });
        }
    });
};
</script>

<template>
    <Head title="Pengaturan Kontrol API & Sistem" />
    <AdminLayout>
        <div class="max-w-5xl mx-auto space-y-6">
            <div>
                <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">Pusat Konfigurasi Sistem ROMEI</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Kelola endpoint & kredensial API CEIRKU, margin profit diagnosis e-wallet, kredensial DOKU, serta aset platform.</p>
            </div>

            <form @submit.prevent="saveSettings" class="space-y-6">
                
                <!-- SEKSI 1: KENDALI INTEGRASI CEIRKU PUSAT -->
                <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-5 transition-colors">
                    <div class="border-b border-slate-200 dark:border-slate-800 pb-3 flex items-center gap-2">
                        <CpuChipIcon class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                        <div>
                            <h3 class="font-bold text-slate-900 dark:text-white text-base">1. Integrasi Gateway API CEIRKU</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Pengaturan domain gateway pusat, mode operasional, dan token otentikasi admin.</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <!-- INFORMASI IP PUBLIK SERVER ROMEI (WHITELIST GATEWAY VENDOR CEIRKU) -->
                        <div class="p-4 rounded-xl border border-indigo-200 dark:border-indigo-900/60 bg-gradient-to-br from-indigo-50/70 via-white to-blue-50/40 dark:from-slate-800/80 dark:via-slate-800/50 dark:to-indigo-950/20">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5 mb-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-lg bg-indigo-100 dark:bg-indigo-900/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                                        <GlobeAltIcon class="w-5 h-5" />
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                                IP Publik Server ROMEI
                                            </span>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 dark:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                                Whitelist Vendor
                                            </span>
                                        </div>
                                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                            Berikan alamat IP publik ini ke vendor/admin CEIRKU untuk didaftarkan pada IP Whitelist agar permohonan IMEI & Roamer tidak diblokir.
                                        </p>
                                    </div>
                                </div>

                                <button 
                                    type="button" 
                                    @click="refreshServerIp" 
                                    :disabled="isDetectingIp"
                                    class="self-start sm:self-auto px-2.5 py-1.5 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-semibold transition flex items-center gap-1.5 shrink-0 disabled:opacity-50"
                                    title="Deteksi ulang IP publik server saat ini"
                                >
                                    <ArrowPathIcon class="w-3.5 h-3.5" :class="{ 'animate-spin': isDetectingIp }" />
                                    <span>{{ isDetectingIp ? 'Mendeteksi...' : 'Deteksi Ulang' }}</span>
                                </button>
                            </div>

                            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                                <div class="flex-1 flex items-center justify-between px-3.5 py-2.5 rounded-xl bg-white dark:bg-slate-900 border border-indigo-100 dark:border-slate-700/80 shadow-inner">
                                    <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Alamat IP:</span>
                                    <span class="font-mono text-sm sm:text-base font-extrabold text-indigo-600 dark:text-indigo-400 tracking-wider select-all">
                                        {{ currentServerIp || 'Tidak terdeteksi' }}
                                    </span>
                                </div>
                                <button 
                                    type="button" 
                                    @click="copyServerIp"
                                    class="px-4 py-2.5 rounded-xl font-bold text-xs flex items-center justify-center gap-2 transition shadow-sm"
                                    :class="copiedIp ? 'bg-emerald-600 text-white' : 'bg-indigo-600 hover:bg-indigo-700 text-white'"
                                >
                                    <ClipboardDocumentCheckIcon v-if="copiedIp" class="w-4 h-4" />
                                    <ClipboardDocumentIcon v-else class="w-4 h-4" />
                                    <span>{{ copiedIp ? 'IP Berhasil Disalin!' : 'Salin IP Server' }}</span>
                                </button>
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400">
                                    URL API Gateway CEIRKU (Dinamis)
                                </label>
                                <span class="text-[10px] font-semibold text-blue-600 dark:text-blue-400">
                                    Dapat disesuaikan kapan saja
                                </span>
                            </div>
                            <div class="flex flex-col sm:flex-row gap-2">
                                <input 
                                    type="text" 
                                    v-model="form.ceirku_api_url" 
                                    placeholder="https://ceirku.net/api/v1" 
                                    class="flex-1 px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm font-mono focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition"
                                />
                                <button 
                                    type="button" 
                                    @click="testCeirkuConnection" 
                                    :disabled="testingCeirku"
                                    class="px-4 py-2.5 bg-blue-50 hover:bg-blue-100 dark:bg-blue-950/40 dark:hover:bg-blue-900/60 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800/80 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 shrink-0 disabled:opacity-50"
                                >
                                    <ArrowPathIcon v-if="testingCeirku" class="w-4 h-4 animate-spin" />
                                    <span>{{ testingCeirku ? 'Menguji Gateway...' : 'Uji Koneksi Gateway' }}</span>
                                </button>
                            </div>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                                Base URL gateway CEIRKU (contoh: <span class="font-mono text-blue-600 dark:text-blue-400">https://ceirku.net/api/v1</span>). Jika pihak vendor mengubah domain atau link API gateway, ubah langsung di sini dan lakukan pengujian koneksi tanpa perlu mengubah kode program.
                            </p>

                            <!-- Live Test Result Banner -->
                            <div v-if="ceirkuTestResult" :class="ceirkuTestResult.success ? 'bg-emerald-50 dark:bg-emerald-950/30 border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300' : 'bg-rose-50 dark:bg-rose-950/30 border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300'" class="mt-2 p-3 rounded-xl border text-xs font-semibold flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <CheckCircleIcon v-if="ceirkuTestResult.success" class="w-4 h-4 text-emerald-500 shrink-0" />
                                    <ExclamationTriangleIcon v-else class="w-4 h-4 text-rose-500 shrink-0" />
                                    <span>{{ ceirkuTestResult.message }}</span>
                                </div>
                                <span class="font-mono text-[10px] px-2 py-0.5 rounded bg-white/50 dark:bg-black/20">
                                    Latensi: {{ ceirkuTestResult.latency }}
                                </span>
                            </div>

                            <div v-if="form.errors.ceirku_api_url" class="text-xs text-red-500 font-semibold mt-1">
                                {{ form.errors.ceirku_api_url }}
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 block mb-1">
                                    Environment Mode
                                </label>
                                <select 
                                    v-model="form.ceirku_mode" 
                                    class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm font-bold focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition"
                                >
                                    <option value="sandbox">SANDBOX (Uji Coba Gratis)</option>
                                    <option value="live">LIVE PRODUCTION (Komersial Asli)</option>
                                </select>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 block mb-1">
                                    CEIRKU X-Api-Key Reseller
                                </label>
                                <input 
                                    type="password" 
                                    v-model="form.ceirku_api_key" 
                                    placeholder="Masukkan token otentikasi X-Api-Key CEIRKU..." 
                                    class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm font-mono focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SEKSI 2: MARGIN KEUNTUNGAN TARIF WALLET KONSUMEN -->
                <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-5 transition-colors">
                    <div class="border-b border-slate-200 dark:border-slate-800 pb-3 flex items-center gap-2">
                        <CurrencyDollarIcon class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                        <div>
                            <h3 class="font-bold text-slate-900 dark:text-white text-base">2. Skema Tarif Layanan & Margin ROMEI</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Besaran nominal saldo e-wallet konsumen yang dipotong otomatis per transaksi sukses.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 block mb-1">
                                Tarif Cek SIM Lock (Rp)
                            </label>
                            <input 
                                type="number" 
                                v-model="form.fee_check_sim_lock" 
                                min="0" 
                                class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm font-bold font-mono focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition text-blue-600 dark:text-blue-400"
                            />
                            <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">Biaya modal: Rp 0 (Sandbox).</p>
                        </div>
                        <div>
                            <label class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 block mb-1">
                                Tarif Cek History CEIR (Rp)
                            </label>
                            <input 
                                type="number" 
                                v-model="form.fee_check_ceir_history" 
                                min="0" 
                                class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm font-bold font-mono focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition text-blue-600 dark:text-blue-400"
                            />
                            <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">Biaya modal: Rp 0 (Sandbox).</p>
                        </div>
                        <div>
                            <label class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 block mb-1">
                                Add Roamer 1 Bulan (Rp)
                            </label>
                            <input 
                                type="number" 
                                v-model="form.fee_add_roamer_1m" 
                                min="0" 
                                class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm font-bold font-mono focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition text-emerald-600 dark:text-emerald-400"
                            />
                            <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">Paket Roamer 30 hari.</p>
                        </div>
                        <div>
                            <label class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 block mb-1">
                                Add Roamer 3 Bulan (Rp)
                            </label>
                            <input 
                                type="number" 
                                v-model="form.fee_add_roamer_3m" 
                                min="0" 
                                class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm font-bold font-mono focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition text-emerald-600 dark:text-emerald-400"
                            />
                            <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">Paket Roamer 90 hari.</p>
                        </div>
                    </div>
                </div>

                <!-- SEKSI 3: GERBANG PEMBAYARAN GATEWAY (DOKU VS QRQU) -->
                <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-6 transition-colors">
                    <div class="border-b border-slate-200 dark:border-slate-800 pb-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <CreditCardIcon class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                            <div>
                                <h3 class="font-bold text-slate-900 dark:text-white text-base">3. Gerbang Pembayaran Gateway (Top Up & Transaksi)</h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Pilih penyedia gerbang pembayaran aktif antara DOKU atau QRqu untuk transaksi & top up saldo konsumen.</p>
                            </div>
                        </div>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider self-start sm:self-auto"
                            :class="form.payment_gateway_provider === 'qrqu' ? 'bg-indigo-100 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800' : 'bg-blue-100 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800'"
                        >
                            <span class="w-2 h-2 rounded-full animate-pulse" :class="form.payment_gateway_provider === 'qrqu' ? 'bg-indigo-500' : 'bg-blue-500'"></span>
                            Aktif: {{ form.payment_gateway_provider === 'qrqu' ? 'QRqu Gateway' : 'DOKU Gateway' }}
                        </span>
                    </div>

                    <!-- PILIHAN KARTU PROVIDER: DOKU ATAU QRQU -->
                    <div>
                        <label class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 block mb-2">
                            Pilih Penyedia Gateway Pembayaran
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- KARTU 1: DOKU -->
                            <div 
                                @click="form.payment_gateway_provider = 'doku'"
                                :class="form.payment_gateway_provider === 'doku' ? 'border-blue-500 bg-blue-50/40 dark:bg-blue-950/20 ring-2 ring-blue-500/20 shadow-sm' : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 bg-white dark:bg-slate-900/40'"
                                class="p-4 rounded-xl border cursor-pointer transition relative flex flex-col justify-between"
                            >
                                <div class="flex items-start justify-between gap-2 mb-2">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/60 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold text-xs">
                                            DK
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-bold text-slate-900 dark:text-white">DOKU Payment Gateway</h4>
                                            <p class="text-[11px] text-slate-500 dark:text-slate-400">Direct Checkout Resmi DOKU</p>
                                        </div>
                                    </div>
                                    <input 
                                        type="radio" 
                                        value="doku" 
                                        v-model="form.payment_gateway_provider" 
                                        class="w-4 h-4 text-blue-600 border-slate-300 focus:ring-blue-500" 
                                    />
                                </div>
                                <p class="text-xs text-slate-600 dark:text-slate-400 mt-1 leading-relaxed">
                                    Menggunakan protokol direct DOKU Checkout v1 dengan SHA256 Digest & HMAC-SHA256 signature.
                                </p>
                            </div>

                            <!-- KARTU 2: QRQU -->
                            <div 
                                @click="form.payment_gateway_provider = 'qrqu'"
                                :class="form.payment_gateway_provider === 'qrqu' ? 'border-indigo-500 bg-indigo-50/40 dark:bg-indigo-950/20 ring-2 ring-indigo-500/20 shadow-sm' : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 bg-white dark:bg-slate-900/40'"
                                class="p-4 rounded-xl border cursor-pointer transition relative flex flex-col justify-between"
                            >
                                <div class="flex items-start justify-between gap-2 mb-2">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-lg bg-indigo-100 dark:bg-indigo-900/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold text-xs">
                                            QQ
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-bold text-slate-900 dark:text-white">QRqu Payment Gateway</h4>
                                            <p class="text-[11px] text-slate-500 dark:text-slate-400">Platform Middleware QRqu</p>
                                        </div>
                                    </div>
                                    <input 
                                        type="radio" 
                                        value="qrqu" 
                                        v-model="form.payment_gateway_provider" 
                                        class="w-4 h-4 text-indigo-600 border-slate-300 focus:ring-indigo-500" 
                                    />
                                </div>
                                <p class="text-xs text-slate-600 dark:text-slate-400 mt-1 leading-relaxed">
                                    Mengintegrasikan platform QRqu sebagai payment aggregator dengan dukungan QRIS dinamis & webhook HMAC.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- FORM KREDENSIAL DOKU (MUNCUL SAAT DOKU AKTIF) -->
                    <div v-if="form.payment_gateway_provider === 'doku'" class="space-y-4 pt-2 border-t border-slate-100 dark:border-slate-800">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 block mb-1">
                                    DOKU Client ID (Mall ID)
                                </label>
                                <input 
                                    type="text" 
                                    v-model="form.doku_client_id" 
                                    placeholder="Contoh: MALL-ID-12345" 
                                    class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm font-mono focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition"
                                />
                            </div>
                            <div>
                                <label class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 block mb-1">
                                    DOKU Shared Secret Key
                                </label>
                                <input 
                                    type="password" 
                                    v-model="form.doku_secret_key" 
                                    placeholder="••••••••••••••••" 
                                    class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm font-mono focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- FORM KREDENSIAL QRQU (MUNCUL SAAT QRQU AKTIF) -->
                    <div v-else class="space-y-4 pt-2 border-t border-slate-100 dark:border-slate-800">
                        <!-- URL GATEWAY QRQU & UJI HANDSHAKE -->
                        <div class="p-4 rounded-xl border border-indigo-200 dark:border-indigo-900/60 bg-gradient-to-br from-indigo-50/60 via-white to-purple-50/30 dark:from-slate-800/80 dark:via-slate-800/50 dark:to-indigo-950/20">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5 mb-2">
                                <label class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                    Endpoint Base URL API QRqu
                                </label>
                                <button 
                                    type="button" 
                                    @click="testQrquConnection" 
                                    :disabled="testingQrqu"
                                    class="self-start sm:self-auto px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold transition flex items-center gap-1.5 shadow-sm disabled:opacity-50"
                                >
                                    <ArrowPathIcon class="w-3.5 h-3.5" :class="{ 'animate-spin': testingQrqu }" />
                                    <span>{{ testingQrqu ? 'Menguji Koneksi...' : 'Uji Jembatan API QRqu' }}</span>
                                </button>
                            </div>
                            <input 
                                type="url" 
                                v-model="form.qrqu_api_url" 
                                placeholder="http://localhost:8000 atau https://qrqu.id" 
                                class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm font-mono focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none transition"
                            />
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                                Masukkan root URL portal QRqu tanpa akhiran sub-path (misal: <code class="font-mono text-indigo-600 dark:text-indigo-400">https://qrqu.id</code> atau <code class="font-mono text-indigo-600 dark:text-indigo-400">http://localhost:8000</code>).
                            </p>

                            <!-- HASIL PENGUJIAN QRQU -->
                            <div v-if="qrquTestResult" class="mt-2.5 p-3 rounded-lg border text-xs flex items-start gap-2"
                                :class="qrquTestResult.success ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200' : 'bg-rose-50 dark:bg-rose-950/40 border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200'"
                            >
                                <CheckCircleIcon v-if="qrquTestResult.success" class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" />
                                <ExclamationTriangleIcon v-else class="w-4 h-4 text-rose-600 shrink-0 mt-0.5" />
                                <div class="flex-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-bold">
                                            {{ qrquTestResult.success ? 'Koneksi Gateway QRqu Berhasil' : 'Koneksi Gateway QRqu Gagal' }}
                                            <span class="font-mono text-[10px] ml-1 opacity-80">({{ qrquTestResult.latency }})</span>
                                        </span>
                                        <span v-if="qrquTestResult.statusCode" class="font-mono text-[10px] px-1.5 py-0.5 rounded bg-black/5 dark:bg-white/10 uppercase">
                                            HTTP {{ qrquTestResult.statusCode }}
                                        </span>
                                    </div>
                                    <p class="text-[11px] mt-0.5">{{ qrquTestResult.message }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- KREDENSIAL MERCHANT QRQU -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 block mb-1">
                                    QRqu Merchant API Key (X-QRQU-KEY)
                                </label>
                                <input 
                                    type="text" 
                                    v-model="form.qrqu_api_key" 
                                    placeholder="Contoh: qrqu_live_... atau qrqu_sand_..." 
                                    class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm font-mono focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none transition"
                                />
                            </div>
                            <div>
                                <label class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 block mb-1">
                                    QRqu Merchant API Secret
                                </label>
                                <input 
                                    type="password" 
                                    v-model="form.qrqu_api_secret" 
                                    placeholder="••••••••••••••••" 
                                    class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm font-mono focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none transition"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 block mb-1">
                                    QRqu Webhook Secret Key
                                </label>
                                <input 
                                    type="password" 
                                    v-model="form.qrqu_webhook_secret" 
                                    placeholder="Opsional, jika kosong menggunakan API Secret" 
                                    class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm font-mono focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none transition"
                                />
                                <p class="text-[11px] text-slate-400 mt-1">Digunakan untuk memvalidasi tanda tangan webhook masuk dari QRqu.</p>
                            </div>

                            <!-- WEBHOOK CALLBACK URL BOX -->
                            <div>
                                <label class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 block mb-1">
                                    Webhook URL ROMEI (Daftarkan di Portal QRqu)
                                </label>
                                <div class="flex items-center gap-1.5">
                                    <input 
                                        type="text" 
                                        readonly 
                                        :value="qrquWebhookUrl" 
                                        class="w-full px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/80 text-slate-600 dark:text-slate-300 text-xs font-mono select-all outline-none"
                                    />
                                    <button 
                                        type="button" 
                                        @click="copyQrquWebhook" 
                                        class="px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs font-semibold shrink-0 transition"
                                        title="Salin Webhook URL"
                                    >
                                        <ClipboardDocumentCheckIcon v-if="copiedQrquWebhook" class="w-4 h-4 text-emerald-600" />
                                        <ClipboardDocumentIcon v-else class="w-4 h-4" />
                                    </button>
                                </div>
                                <p class="text-[11px] text-slate-400 mt-1">Masukkan URL ini pada pengaturan Webhook URL di dashboard merchant QRqu.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SEKSI 4: UPLOAD ASET VISUAL -->
                <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-5 transition-colors">
                    <div class="border-b border-slate-200 dark:border-slate-800 pb-3 flex items-center gap-2">
                        <PhotoIcon class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                        <div>
                            <h3 class="font-bold text-slate-900 dark:text-white text-base">4. Gambar Ilustrasi Beranda Utama (Carton)</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Unggah berkas gambar ilustrasi pada beranda aplikasi secara dinamis.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-6 items-center">
                        <div class="sm:col-span-4 flex flex-col items-center justify-center p-4 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-dashed border-slate-300 dark:border-slate-700 min-h-[140px]">
                            <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-2 block">Pratinjau Aset</span>
                            <img v-if="imagePreview" :src="imagePreview" alt="Preview Carton" class="max-h-28 object-contain rounded drop-shadow" />
                            <div v-else class="text-xs text-slate-400 dark:text-slate-500 py-6 text-center italic">Belum ada gambar yang diunggah</div>
                        </div>
                        <div class="sm:col-span-8 space-y-2">
                            <label class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 block">Pilih File Gambar (.png, .jpg, .svg)</label>
                            <input 
                                type="file" 
                                accept="image/*" 
                                @change="handleImageUpload" 
                                class="block w-full text-sm text-slate-500 dark:text-slate-400 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-blue-600 file:text-white hover:file:bg-blue-700 transition cursor-pointer" 
                            />
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">Rekomendasi ukuran rasio persegi seimbang dengan latar belakang transparan.</p>
                        </div>
                    </div>
                </div>

                <!-- FOOTER BANNER ACTIONS CONTROL -->
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm transition-colors">
                    <div class="flex items-center gap-3">
                        <input 
                            type="checkbox" 
                            id="maintenance" 
                            v-model="form.maintenance_mode" 
                            class="w-4 h-4 rounded text-blue-600 border-slate-300 dark:border-slate-700 dark:bg-slate-800 focus:ring-blue-500" 
                        />
                        <label for="maintenance" class="text-sm font-semibold text-slate-700 dark:text-slate-300 cursor-pointer select-none">
                            Aktifkan Status Maintenance Aplikasi
                        </label>
                    </div>
                    <button 
                        type="submit" 
                        :disabled="form.processing" 
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl text-sm transition-all shadow-sm disabled:opacity-50"
                    >
                        <CheckCircleIcon class="w-4 h-4" />
                        <span>{{ form.processing ? 'Menyimpan Konfigurasi...' : 'Simpan Seluruh Pengaturan' }}</span>
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>