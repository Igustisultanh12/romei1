<script setup>
import { Head, Link } from '@inertiajs/vue3';
import UserLayout from '@/Layouts/UserLayout.vue';
import { 
    CreditCardIcon, 
    ArrowLeftIcon, 
    CheckCircleIcon,
    InformationCircleIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    registration: Object,
    transaction: Object
});

// Format mata uang Rupiah Helper
const formatRupiah = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(value);
};
</script>

<template>
    <UserLayout>
        <Head title="Pembayaran Invoice QRIS" />

        <div class="max-w-3xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <!-- BACK BUTTON & HEADER -->
            <div class="mb-6 flex items-center justify-between">
                <Link :href="route('dashboard')" class="inline-flex items-center text-xs font-bold uppercase tracking-wider text-gray-400 hover:text-gray-600 transition-colors">
                    <ArrowLeftIcon class="w-3.5 h-3.5 mr-1" />
                    Kembali ke Dashboard
                </Link>
                <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-800 ring-1 ring-inset ring-amber-600/20 animate-pulse">
                    Menunggu Pembayaran
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-start">
                
                <!-- KARTU KIRI: DETAIL TAGIHAN & SPESIFIKASI PERANGKAT -->
                <div class="md:col-span-2 space-y-6">
                    <div class="bg-white border border-gray-100 rounded-xl shadow-sm p-6 space-y-4">
                        <div>
                            <h2 class="text-lg font-black text-gray-900 tracking-tight">Rincian Pendaftaran</h2>
                            <p class="text-xs text-gray-400 font-mono mt-0.5">{{ props.registration.registration_number }}</p>
                        </div>

                        <div class="border-t border-gray-100 pt-4 space-y-3 text-sm text-gray-600">
                            <div class="flex justify-between">
                                <span>Paket Jaringan</span>
                                <span class="font-bold text-gray-900">{{ props.registration.package_name }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Jenis Slot SIM</span>
                                <span class="font-medium text-gray-900 uppercase text-xs bg-gray-100 px-2 py-0.5 rounded">
                                    {{ props.registration.sim_type }} SIM
                                </span>
                            </div>
                            <div class="border-t border-dashed border-gray-100 pt-3 space-y-1">
                                <div class="flex justify-between">
                                    <span class="text-xs font-medium text-gray-400">IMEI Slot 1</span>
                                    <span class="font-mono text-xs font-bold text-gray-900">{{ props.registration.imei1 }}</span>
                                </div>
                                <div v-if="props.registration.sim_type === 'dual'" class="flex justify-between">
                                    <span class="text-xs font-medium text-gray-400">IMEI Slot 2</span>
                                    <span class="font-mono text-xs font-bold text-gray-900">{{ props.registration.imei2 }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- INSTRUKSI CARA BAYAR -->
                    <div class="bg-gray-50 border border-gray-100 rounded-xl p-5 space-y-3">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 inline-flex items-center">
                            <InformationCircleIcon class="w-4 h-4 mr-1 text-gray-400" />
                            Petunjuk Pembayaran QRIS:
                        </h4>
                        <ol class="list-decimal list-inside text-xs text-gray-500 space-y-1.5 pl-1">
                            <li>Pindai/Scan kode QR resmi DOKU di sebelah kanan menggunakan aplikasi m-Banking (BCA, Mandiri, BRI, dll) atau E-Wallet (Gopay, OVO, Dana, LinkAja).</li>
                            <li>Pastikan nominal yang tertera di aplikasi Anda sama persis dengan total tagihan.</li>
                            <li>Setelah transaksi sukses di aplikasi Anda, sistem ROMEI akan otomatis memvalidasi pembayaran dalam waktu maksimal 1-3 menit.</li>
                            <li>Status jaringan IMEI perangkat Anda akan otomatis aktif di jaringan CEIR setelah pembayaran sukses.</li>
                        </ol>
                    </div>
                </div>

                <!-- KARTU KANAN: GENERATOR KODE QRIS DOKU -->
                <div class="md:col-span-1 bg-white border border-gray-100 rounded-xl shadow-sm p-6 text-center space-y-4">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-gray-400 block">Total Pembayaran</span>
                        <h3 class="text-xl font-black text-navy-600 mt-1">
                            {{ formatRupiah(props.transaction.total_amount) }}
                        </h3>
                        <span class="text-[10px] text-gray-400 font-mono block mt-1">{{ props.transaction.invoice_number }}</span>
                    </div>

                    <!-- TEMPAT RENDER QR CODE / WIDGET QRIS -->
                    <div class="bg-gray-50 border border-dashed border-gray-200 rounded-lg p-4 flex flex-col items-center justify-center aspect-square shadow-inner group">
                        <!-- 
                          Bagian ini dapat diintegrasikan dengan DOKU JS SDK, 
                          atau memuat string QRIS mentah dari backend ke dalam komponen generator QR kustom.
                          Sementara kita siapkan placeholder UI QRIS resmi yang responsif.
                        -->
                        <div class="w-full h-full border-2 border-white rounded bg-white flex flex-col items-center justify-center relative p-2">
                            <div class="absolute inset-0 bg-gray-900/5 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center rounded">
                                <span class="bg-white text-[10px] font-bold px-2 py-1 rounded shadow text-gray-700">Scan QRIS Resmi</span>
                            </div>
                            
                            <!-- LOGO QRIS / TEMPLATE SIMULASI -->
                            <div class="w-12 h-4 bg-gray-200 rounded mb-2 flex items-center justify-center text-[8px] font-black tracking-widest text-gray-400 uppercase">QRIS</div>
                            
                            <!-- Dummy QR Matrix Representation -->
                            <div class="w-32 h-32 bg-gray-900 rounded flex items-center justify-center">
                                <CreditCardIcon class="w-8 h-8 text-white/20 animate-pulse" />
                            </div>
                            
                            <div class="w-full text-center text-[9px] font-semibold text-gray-400 mt-2">DOKU Payment Network</div>
                        </div>
                    </div>

                    <div class="text-[11px] text-gray-400 flex items-center justify-center space-x-1">
                        <CheckCircleIcon class="w-3.5 h-3.5 text-emerald-500" />
                        <span>Sistem Keamanan Enkripsi DOKU</span>
                    </div>
                </div>

            </div>
        </div>
    </UserLayout>
</template>