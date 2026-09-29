<script setup>
import { useForm, Head, usePage, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import UserLayout from '@/Layouts/UserLayout.vue';
import Swal from 'sweetalert2';

const props = defineProps({
    fee: Number,
    wallet_balance: Number
});

const page = usePage();

// Mengambil data flash response penanganan sukses dari backend controller
const simLockDetails = computed(() => page.props.flash?.sim_lock_details || null);

const form = useForm({
    imei: '',
});

const formatRupiah = (val) => 'Rp ' + Number(val || 0).toLocaleString('id-ID');

const executeCheck = () => {
    if (props.wallet_balance < props.fee) {
        Swal.fire({
            icon: 'error',
            title: 'Saldo Kurang',
            text: `Biaya pengecekan adalah ${formatRupiah(props.fee)}, sedangkan saldo Wallet ROMEI Anda saat ini adalah ${formatRupiah(props.wallet_balance)}.`,
            confirmButtonColor: '#4f46e5'
        });
        return;
    }

    Swal.fire({
        title: 'Memproses Diagnosis',
        text: 'Menghubungkan data IMEI Anda ke server CEIR pusat...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    form.post(route('services.sim-lock.check'), {
        preserveScroll: true,
        onSuccess: (pageContext) => {
            // Tutup spinner loader muatan asinkron secara instan
            Swal.close();
            
            // Membaca data context state page terbaru yang dikembalikan oleh server secara real-time
            const flashData = pageContext.props.flash;
            const details = flashData?.sim_lock_details;

            if (details) {
                Swal.fire({
                    icon: 'success',
                    title: 'Pengecekan Sukses',
                    html: `Status Jaringan IMEI Anda adalah <strong>"${details.status}"</strong>.<br><span class="text-xs text-gray-500">Anda bisa melihat histori transaksi terperinci Anda di sini.</span>`,
                    showCancelButton: true,
                    confirmButtonText: 'Cek Histori Transaksi',
                    cancelButtonText: 'Tutup',
                    confirmButtonColor: '#1e293b',
                    cancelButtonColor: '#94a3b8',
                    allowOutsideClick: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        router.get(route('services.history'));
                    }
                });
            } else {
                // Fallback jika data flash terlewat akibat siklus rendering session
                Swal.fire({
                    icon: 'success',
                    title: 'Pengecekan Sukses',
                    html: `Sistem ROMEI berhasil memproses status IMEI anda.<br><span class="text-xs text-gray-500">Anda bisa melihat hasil di riwayat transaksi di sini.</span>`,
                    showCancelButton: true,
                    confirmButtonText: ' Riwayat Transaksi',
                    cancelButtonText: 'Tutup',
                    confirmButtonColor: '#1e293b',
                    cancelButtonColor: '#94a3b8',
                    allowOutsideClick: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        router.get(route('services.history'));
                    }
                });
            }
        },
        onError: (errors) => {
            Swal.close();
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: errors.message || 'Terjadi gangguan internal pada sistem gateway.',
                confirmButtonColor: '#ef4444'
            });
        }
    });
};
</script>

<template>
    <Head title="Diagnosis Jaringan SIM Lock" />
    <UserLayout>
        <div class="max-w-3xl mx-auto py-8 px-4 space-y-6">
            <div>
                <h1 class="text-2xl font-black text-gray-900 tracking-tight">Cek Status Jaringan & SIM Lock</h1>
                <p class="text-xs text-gray-500 mt-1">Diagnosis instan untuk mengetahui apakah status IMEI Anda terdaftar sebagai ROAMER, REGISTERED, atau UNKNOWN.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                <div class="md:col-span-5 bg-white p-6 rounded-2xl border border-gray-100 shadow-sm h-fit space-y-4">
                    <div class="bg-indigo-50/70 border border-indigo-100 p-3 rounded-xl text-xs text-indigo-900 space-y-1">
                        <div class="flex justify-between"><span>Tarif Layanan:</span><span class="font-bold font-mono">{{ formatRupiah(props.fee) }}</span></div>
                        <div class="flex justify-between"><span>Saldo Anda:</span><span class="font-bold font-mono text-emerald-600">{{ formatRupiah(props.wallet_balance) }}</span></div>
                    </div>

                    <form @submit.prevent="executeCheck" class="space-y-4">
                        <div class="space-y-1">
                            <label class="text-[10px] font-black uppercase text-gray-400 tracking-wider">Nomor IMEI (15 Digit)</label>
                            <input type="text" v-model="form.imei" maxlength="15" required class="w-full rounded-xl border-gray-200 text-sm font-mono tracking-widest focus:ring-indigo-500 focus:border-indigo-500" placeholder="3589XXXXXXXXXXX" />
                        </div>
                        <button type="submit" :disabled="form.processing || form.imei.length !== 15" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition-all shadow-sm disabled:opacity-40">
                            {{ form.processing ? 'Sedang Memeriksa...' : 'Mulai Cek Jaringan' }}
                        </button>
                    </form>
                </div>

                <div class="md:col-span-7 bg-white p-6 rounded-2xl border border-gray-100 shadow-sm min-h-[250px] flex flex-col justify-between">
                    <div v-if="simLockDetails" class="space-y-4">
                        <div class="border-b border-gray-100 pb-2 flex justify-between items-center">
                            <h3 class="font-bold text-gray-900 text-sm">Hasil Diagnosis Instan</h3>
                            <span class="text-[10px] font-mono font-bold px-2 py-0.5 bg-gray-100 text-gray-500 rounded">ID: {{ simLockDetails.order_id }}</span>
                        </div>

                        <div class="p-4 rounded-xl text-center space-y-1 relative overflow-hidden" 
                             :class="[
                                 simLockDetails.status === 'REGISTERED' ? 'bg-emerald-50 border border-emerald-100 text-emerald-900' :
                                 simLockDetails.status === 'ROAMER' ? 'bg-blue-50 border border-blue-100 text-blue-900' : 'bg-rose-50 border border-rose-100 text-rose-900'
                             ]">
                            <span class="text-[10px] uppercase tracking-wider block opacity-70">Status Deteksi API</span>
                            <span class="text-2xl font-black tracking-wider block font-sans">{{ simLockDetails.status }}</span>
                        </div>

                        <div class="border border-gray-100 rounded-xl p-4 space-y-2 text-xs text-gray-600">
                            <div class="flex justify-between"><span>Nomor IMEI</span><span class="font-mono font-bold text-gray-900">{{ simLockDetails.imei }}</span></div>
                            <div class="flex justify-between"><span>Catatan Jaringan</span><span class="font-medium text-gray-900">{{ simLockDetails.message }}</span></div>
                        </div>
                    </div>

                    <div v-else class="my-auto text-center space-y-2 py-8">
                        <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <p class="text-xs text-gray-400 font-medium italic">Silakan masukkan 15 digit IMEI di samping untuk memicu data keluaran.</p>
                    </div>

                    <div class="text-[10px] text-gray-400 mt-4 pt-2 border-t border-gray-50 bg-slate-50 p-2 rounded-lg">
                        * Pengecekan ini langsung memotong saldo ROMEI wallet Anda secara sah setelah server pusat sukses merespon. Dana tidak dapat di-refund jika salah memasukkan nomor IMEI.
                    </div>
                </div>
            </div>
        </div>
    </UserLayout>
</template>