<script setup>
import { ref, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import UserLayout from '@/Layouts/UserLayout.vue';
import Swal from 'sweetalert2';
import { 
    DevicePhoneMobileIcon, 
    WalletIcon, 
    CheckCircleIcon, 
    XCircleIcon,
    ArrowPathIcon,
    PlusIcon
} from '@heroicons/vue/24/outline';

// Menerima kiriman ringkasan data dari DashboardController sisi user
const props = defineProps({
    stats: Object,
    recent_registrations: Array
});

const formatRupiah = (value) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
};

/**
 * PROSES VALIDASI & EDUKASI SWEETALERT SEBELUM MASUK WIZARD REGISTRASI IMEI
 */
const triggerImeiWizardWithAlert = (e) => {
    // Mencegah navigasi bawaan tag <a> agar SweetAlert muncul terlebih dahulu
    e.preventDefault();

    Swal.fire({
        title: '<span class="text-[#1d1d1f] font-black text-lg tracking-tight block pt-2">Konfirmasi Pra-Pendaftaran</span>',
        html: `
            <div class="text-left text-xs sm:text-sm space-y-3.5 text-[#434344] leading-relaxed font-medium font-sans px-1">
                <p class="border-b border-gray-100 pb-2 text-gray-400 font-semibold">Mohon pastikan 3 poin kelayakan perangkat berikut terpenuhi:</p>
                <div class="space-y-3">
                    <div class="flex items-start gap-2.5">
                        <span class="w-5 h-5 rounded-full bg-blue-50 text-[#0071e3] flex items-center justify-center font-mono text-xs font-bold shrink-0 mt-0.5">1</span>
                        <p>Pastikan data <strong>IMEI</strong> gawai internasional yang akan Anda masukkan sudah benar dan sesuai fisik perangkat.</p>
                    </div>
                    <div class="flex items-start gap-2.5">
                        <span class="w-5 h-5 rounded-full bg-blue-50 text-[#0071e3] flex items-center justify-center font-mono text-xs font-bold shrink-0 mt-0.5">2</span>
                        <p>Pastikan status perangkat Anda <strong>bukan SIM LOCK</strong> (Terkunci operator luar negeri). Jika Anda belum yakin, Anda dapat melakukan <a href="/dashboard" class="text-[#0071e3] font-bold hover:underline">Cek Parameter IMEI Disini</a>.</p>
                    </div>
                    <div class="flex items-start gap-2.5">
                        <span class="w-5 h-5 rounded-full bg-blue-50 text-[#0071e3] flex items-center justify-center font-mono text-xs font-bold shrink-0 mt-0.5">3</span>
                        <p>Jika sebelumnya Anda pernah mendaftarkan paket <strong>Roamer Sementara 3 Bulan</strong>, pastikan masa aktifnya telah berakhir. Anda dapat memastikan statusnya dengan melakukan <a href="/dashboard" class="text-[#0071e3] font-bold hover:underline">Cek Riwayat History CEIR Disini</a>.</p>
                    </div>
                </div>
            </div>
        `,
        icon: 'warning',
        iconColor: '#0071e3',
        background: '#ffffff',
        showCancelButton: true,
        confirmButtonColor: '#0071e3', 
        cancelButtonColor: '#86868b',  
        confirmButtonText: 'Saya Paham, Lanjutkan',
        cancelButtonText: 'Kembali',
        buttonsStyling: true,
        customClass: {
            popup: 'rounded-3xl border border-[#d2d2d7]/60 shadow-2xl p-6 font-sans',
            confirmButton: 'px-5 py-2.5 rounded-full text-xs font-bold font-sans',
            cancelButton: 'px-5 py-2.5 rounded-full text-xs font-bold font-sans'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // SOLUSI UTAMA: Pakai direct string path murni, hilangkan route()
            router.visit('/imei/create');
        }
    });
};
</script>

<template>
    <UserLayout>
        <Head title="Dashboard Saya" />

        <div class="py-8 px-6 max-w-5xl mx-auto space-y-8">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Selamat Datang</h1>
                    <p class="text-sm text-gray-500 mt-1">Kelola dan pantau status aktif registrasi IMEI perangkat seluler Anda.</p>
                </div>
                
                <!-- SOLUSI UTAMA: Ubah :href="route('...')" menjadi href murni -->
                <!--<a href="/imei/create" @click="triggerImeiWizardWithAlert" class="inline-flex items-center space-x-2 bg-[#1d1d1f] hover:bg-gray-800 text-white px-5 py-2.5 rounded-full text-xs font-bold shadow-sm transition-all select-none">-->
                <!--    <PlusIcon class="w-4 h-4" />-->
                <!--    <span>Daftar IMEI Baru</span>-->
                <!--</a>-->
            </div>

            <!-- Kartu Info Statistik -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <div class="bg-white p-6 border border-gray-100 rounded-xl shadow-sm flex items-center justify-between">
                    <div class="space-y-1">
                        <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Saldo Wallet</span>
                        <h3 class="text-2xl font-black text-gray-900">{{ formatRupiah(props.stats.wallet_balance) }}</h3>
                    </div>
                    <div class="p-3 bg-blue-50 rounded-lg text-blue-600">
                        <WalletIcon class="w-6 h-6" />
                    </div>
                </div>

                <div class="bg-white p-6 border border-gray-100 rounded-xl shadow-sm flex items-center justify-between">
                    <div class="space-y-1">
                        <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">IMEI Aktif (3 Bulan)</span>
                        <h3 class="text-2xl font-black text-gray-900">{{ props.stats.active_count }} <span class="text-sm font-normal text-gray-400">Perangkat</span></h3>
                    </div>
                    <div class="p-3 bg-green-50 rounded-lg text-green-600">
                        <CheckCircleIcon class="w-6 h-6" />
                    </div>
                </div>

                <div class="bg-white p-6 border border-gray-100 rounded-xl shadow-sm flex items-center justify-between">
                    <div class="space-y-1">
                        <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Total Pengeluaran</span>
                        <h3 class="text-2xl font-black text-gray-900">{{ formatRupiah(props.stats.total_spent) }}</h3>
                    </div>
                    <div class="p-3 bg-gray-50 rounded-lg text-gray-600">
                        <DevicePhoneMobileIcon class="w-6 h-6" />
                    </div>
                </div>
            </div>

            <!-- Tabel Data Pengajuan Riwayat -->
            <div class="bg-white border border-gray-100 rounded-xl shadow-sm overflow-hidden">
                <div class="p-5 border-b border-gray-50">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider">Riwayat Pengajuan Terkini</h3>
                </div>

                <div v-if="props.recent_registrations.length === 0" class="p-8 text-center text-sm text-gray-400">
                    Belum ada pengajuan pendaftaran IMEI harian.
                </div>
                
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-500">
                        <thead class="bg-gray-50/70 text-xs text-gray-400 uppercase font-semibold border-b border-gray-50">
                            <tr>
                                <th class="px-6 py-3">No. Registrasi</th>
                                <th class="px-6 py-3">IMEI 1</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3">Tanggal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="item in props.recent_registrations" :key="item.id" class="hover:bg-gray-50/30 transition-colors">
                                <td class="px-6 py-4 font-semibold text-gray-900">{{ item.registration_number }}</td>
                                <td class="px-6 py-4 font-mono">{{ item.imei1 }}</td>
                                <td class="px-6 py-4">
                                    <span :class="[
                                        item.status === 'approved' ? 'bg-green-50 text-green-700' : '',
                                        item.status === 'processing' ? 'bg-amber-50 text-amber-700' : '',
                                        item.status === 'rejected' ? 'bg-red-50 text-red-700' : '',
                                        item.status === 'pending' ? 'bg-gray-100 text-gray-600' : '',
                                        'px-2.5 py-1 rounded-full text-xs font-semibold inline-flex items-center space-x-1'
                                    ]">
                                        <CheckCircleIcon class="w-3.5 h-3.5 mr-1" v-if="item.status === 'approved'" />
                                        <ArrowPathIcon class="w-3.5 h-3.5 mr-1 animate-spin" v-else-if="item.status === 'processing'" />
                                        <span>{{ item.status.toUpperCase() }}</span>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-400">{{ new Date(item.created_at).toLocaleDateString('id-ID') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </UserLayout>
</template>

<style>
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