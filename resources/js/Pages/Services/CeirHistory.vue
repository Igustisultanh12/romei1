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

// Mengambil data flash nested array riwayat history log dari backend controller
const ceirHistoryDetails = computed(() => page.props.flash?.ceir_history_details || null);

const form = useForm({
    imei: '',
});

const formatRupiah = (val) => 'Rp ' + Number(val || 0).toLocaleString('id-ID');

const executeCheck = () => {
    if (props.wallet_balance < props.fee) {
        Swal.fire({
            icon: 'error',
            title: 'Saldo Kurang',
            text: `Biaya pelacakan riwayat adalah ${formatRupiah(props.fee)}, sedangkan saldo Wallet ROMEI Anda saat ini adalah ${formatRupiah(props.wallet_balance)}.`,
            confirmButtonColor: '#4f46e5'
        });
        return;
    }

    Swal.fire({
        title: 'Menarik Log Histori',
        text: 'Sedang membongkar berkas manifes database CEIR pusat...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    form.post(route('services.ceir-history.check'), {
        preserveScroll: true,
        onSuccess: (pageContext) => {
            // Tutup spinner loader muatan asinkron secara instan
            Swal.close();
            
            // Membaca data context state page terbaru yang dikembalikan oleh server secara real-time
            const flashData = pageContext.props.flash;
            const details = flashData?.ceir_history_details;

            if (details) {
                Swal.fire({
                    icon: 'success',
                    title: 'Histori Ditemukan',
                    html: `Seluruh rangkuman perputaran data log roamer berhasil ditarik sempurna.<br><span class="text-xs text-gray-500">Anda bisa melihat histori transaksi terperinci Anda di sini.</span>`,
                    showCancelButton: true,
                    confirmButtonText: '📜 Cek Histori Transaksi',
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
                // Fallback penanganan data log jika terlewat session
                Swal.fire({
                    icon: 'success',
                    title: 'Histori Berhasil Ditarik',
                    html: `Data berhasil dikirim.<br><span class="text-xs text-gray-500">Anda bisa melihat histori transaksi Anda di sini.</span>`,
                    showCancelButton: true,
                    confirmButtonText: ' Cek Histori Transaksi ',
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
                title: 'Gagal Tracing',
                text: errors.message || 'Gagal merumuskan interkoneksi data log.',
                confirmButtonColor: '#ef4444'
            });
        }
    });
};
</script>

<template>
    <Head title="Tracing History Sinkronisasi CEIR" />
    <UserLayout>
        <div class="max-w-4xl mx-auto py-8 px-4 space-y-6">
            <div>
                <h1 class="text-2xl font-black text-gray-900 tracking-tight">Tracing Riwayat Sinkronisasi CEIR</h1>
                <p class="text-xs text-gray-500 mt-1">Bongkar riwayat log aktivitas penambahan roamer (`add_roamer`), penghapusan otomatis (`remove_roamer`), serta detail tanggal dari provider live pusat.</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <div class="lg:col-span-4 bg-white p-6 rounded-2xl border border-gray-100 shadow-sm h-fit space-y-4">
                    <div class="bg-indigo-50/70 border border-indigo-100 p-3 rounded-xl text-xs text-indigo-900 space-y-1">
                        <div class="flex justify-between"><span>Tarif Layanan:</span><span class="font-bold font-mono">{{ formatRupiah(props.fee) }}</span></div>
                        <div class="flex justify-between"><span>Saldo Anda:</span><span class="font-bold font-mono text-emerald-600">{{ formatRupiah(props.wallet_balance) }}</span></div>
                    </div>

                    <form @submit.prevent="executeCheck" class="space-y-4">
                        <div class="space-y-1">
                            <label class="text-[10px] font-black uppercase text-gray-400 tracking-wider">Nomor IMEI (15 Digit)</label>
                            <input type="text" v-model="form.imei" maxlength="15" required class="w-full rounded-xl border-gray-200 text-sm font-mono tracking-widest focus:ring-indigo-500 focus:border-indigo-500" placeholder="3565XXXXXXXXXXX" />
                        </div>
                        <button type="submit" :disabled="form.processing || form.imei.length !== 15" class="w-full py-2.5 bg-gray-900 hover:bg-gray-800 text-white text-xs font-bold rounded-xl transition-all shadow-sm disabled:opacity-40">
                            Lacak Log Riwayat
                        </button>
                    </form>
                </div>

                <div class="lg:col-span-8 bg-white p-6 rounded-2xl border border-gray-100 shadow-sm min-h-[300px] flex flex-col justify-between overflow-hidden">
                    <div class="w-full space-y-4" v-if="ceirHistoryDetails">
                        <div class="border-b border-gray-100 pb-2 flex justify-between items-center">
                            <div>
                                <h3 class="font-bold text-gray-900 text-sm">Manifes Log Riwayat IMEI</h3>
                                <p class="text-[10px] text-gray-400">Target Pencarian: <span class="font-mono font-bold text-gray-600">{{ ceirHistoryDetails?.imei }}</span></p>
                            </div>
                            <span class="text-[10px] font-mono font-bold px-2 py-1 bg-indigo-50 text-indigo-600 rounded">Order ID: {{ ceirHistoryDetails?.order_id }}</span>
                        </div>

                        <div class="overflow-x-auto border border-gray-50 rounded-xl">
                            <table class="w-full text-left text-xs text-gray-500">
                                <thead class="bg-gray-50 text-[10px] uppercase text-gray-400 font-bold border-b border-gray-100">
                                    <tr>
                                        <th class="px-4 py-3 text-center">No</th>
                                        <th class="px-4 py-3">Tanggal Log</th>
                                        <th class="px-4 py-3">Aksi Sistem</th>
                                        <th class="px-4 py-3">Keterangan / Provider</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50 font-sans">
                                    <tr v-for="log in ceirHistoryDetails?.logs" :key="log.no" class="hover:bg-slate-50/50 transition-colors">
                                        <td class="px-4 py-3 text-center font-bold font-mono text-gray-400">{{ log.no }}</td>
                                        <td class="px-4 py-3 font-mono text-gray-600 whitespace-nowrap">{{ log.date }}</td>
                                        <td class="px-4 py-3">
                                            <span :class="[
                                                log.action === 'add_roamer' ? 'bg-blue-50 text-blue-600 border border-blue-100' :
                                                log.action === 'remove_roamer' ? 'bg-rose-50 text-rose-600 border border-rose-100' : 'bg-gray-50 text-gray-400',
                                                'px-2 py-0.5 rounded-md font-mono text-[10px] font-bold uppercase'
                                            ]">{{ log.action }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-gray-600 font-medium max-w-[200px] truncate" :title="log.note">{{ log.note }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div v-else class="my-auto text-center space-y-2 py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p class="text-xs text-gray-400 font-medium italic">Silahkan input IMEI anda untuk mulai pengecekan.</p>
                    </div>

                    <div class="text-[10px] text-gray-400 mt-4 pt-2 border-t border-gray-50 bg-slate-50 p-2 rounded-lg">
                        * Data riwayat CIER diperoleh dari database.
                    </div>
                </div>
            </div>
        </div>
    </UserLayout>
</template>