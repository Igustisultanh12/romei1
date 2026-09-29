<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { CloudIcon, ArrowPathIcon, CogIcon, CheckCircleIcon } from '@heroicons/vue/24/outline';
import Swal from 'sweetalert2';

const props = defineProps({
    config: {
        type: Object,
        default: () => ({ gateway_url: 'http://localhost:3100', status: 'OFFLINE', qr: null })
    }
});

const isUpdating = ref(false);
let reloadInterval = null;

const form = useForm({
    gateway_url: props.config.gateway_url
});

// Fungsi memicu reload data parsial dari controller Laravel
const refreshStatus = () => {
    router.reload({ 
        only: ['config'],
        preserveScroll: true
    });
};

onMounted(() => {
    // Jalankan polling internal aman via Inertia reload setiap 4 detik
    reloadInterval = setInterval(refreshStatus, 4000);
});

onBeforeUnmount(() => {
    if (reloadInterval) clearInterval(reloadInterval);
});

const saveConfiguration = () => {
    isUpdating.value = true;
    form.put('/admin/whatsapp-config', {
        preserveScroll: true,
        onSuccess: () => {
            isUpdating.value = false;
            Swal.fire('Berhasil!', 'Endpoint internal berhasil dikunci.', 'success');
        },
        onError: () => {
            isUpdating.value = false;
        }
    });
};
</script>

<template>
    <AdminLayout>
        <Head title="Konfigurasi WhatsApp Gateway" />

        <div class="space-y-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">Konfigurasi WhatsApp</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Pemantauan jalur internal proxy Node.js Gateway via Laravel Secure Tunnel.</p>
                </div>
                <button @click="refreshStatus" class="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800 rounded-lg transition-all shadow-sm">
                    <ArrowPathIcon class="w-4 h-4" /> Periksa Koneksi
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <div class="space-y-5 flex flex-col justify-between h-full">
                    
                    <div class="bg-white dark:bg-slate-900 p-6 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm flex items-center justify-between">
                        <div class="space-y-1">
                            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Status Koneksi API</span>
                            <div class="flex items-center gap-2">
                                <span v-if="props.config.status === 'ONLINE'" class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-500 uppercase tracking-wide">ONLINE</span>
                                <span v-else-if="props.config.status === 'WAITING_SCAN'" class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/10 text-amber-500 uppercase tracking-wide animate-pulse">WAITING SCAN</span>
                                <span v-else class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/10 text-rose-500 uppercase tracking-wide">OFFLINE</span>
                            </div>
                        </div>
                        <div :class="props.config.status === 'ONLINE' ? 'text-emerald-500 bg-emerald-500/10' : 'text-rose-500 bg-rose-500/10'" class="p-3 rounded-lg">
                            <CloudIcon class="w-6 h-6" />
                        </div>
                    </div>

                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-6 flex-1 mt-5 md:mt-0 flex flex-col justify-between">
                        <div>
                            <div class="border-b border-slate-100 dark:border-slate-800 pb-4 mb-4 flex items-center gap-2">
                                <CogIcon class="w-5 h-5 text-blue-500" />
                                <h3 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">Internal Proxy</h3>
                            </div>
                            
                            <form @submit.prevent="saveConfiguration" class="space-y-4">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wide mb-1.5">URL Base Gateway (Localhost Preferred)</label>
                                    <input type="text" v-model="form.gateway_url" required class="w-full text-xs font-mono font-bold bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 rounded-lg border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:ring-0 p-2.5" />
                                </div>
                                <button type="submit" :disabled="isUpdating || form.processing" class="w-full py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold transition-all shadow-md disabled:opacity-50">
                                    Simpan Perubahan
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-6 col-span-1 md:col-span-2 flex flex-col items-center justify-center text-center min-h-[300px]">
                    
                    <div v-if="props.config.status === 'ONLINE'" class="space-y-3 p-6">
                        <CheckCircleIcon class="w-14 h-14 text-emerald-500 mx-auto" />
                        <h3 class="text-md font-bold text-emerald-500 uppercase tracking-wide">Gateway Terhubung Ter-Otentikasi</h3>
                        <p class="text-xs text-slate-400 max-w-sm mx-auto">Koneksi internal localhost port 3100 aman. Seluruh fungsi kirim notifikasi SMS/WA ROMEI platform siap dieksekusi.</p>
                    </div>

                    <div v-else-if="props.config.status === 'WAITING_SCAN' && props.config.qr" class="space-y-4 p-2">
                        <h3 class="text-xs font-black text-slate-400 dark:text-slate-300 uppercase tracking-wider">Silakan Pindai QR Code Melalui Aplikasi WhatsApp Anda</h3>
                        <div class="bg-white p-4 rounded-2xl inline-block border border-slate-200 shadow-md">
                            <img :src="props.config.qr" class="w-48 h-48 mx-auto" alt="ROMEI WA QR" />
                        </div>
                        <p class="text-[11px] text-amber-500 animate-pulse font-bold">Sinkronisasi data sesi asinkron sedang berlangsung...</p>
                    </div>

                    <div v-else class="space-y-3 p-6 text-slate-400">
                        <ArrowPathIcon class="w-12 h-12 text-slate-400 dark:text-slate-500 animate-spin mx-auto" />
                        <h3 class="text-sm font-bold uppercase tracking-wider">Mencari Sesi Jaringan...</h3>
                        <p class="text-xs max-w-xs mx-auto">Laravel sedang mendengarkan respon port lokal host. Pastikan file server Baileys Anda sudah di-running lewat PM2.</p>
                    </div>

                </div>
            </div>
        </div>
    </AdminLayout>
</template>