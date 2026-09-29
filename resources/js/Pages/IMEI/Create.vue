<script setup>
import { computed, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import UserLayout from '@/Layouts/UserLayout.vue';
import Swal from 'sweetalert2';
import axios from 'axios';

// Mengikat global inertia props untuk membaca data settings dinamis
const { props: globalProps } = usePage();

// State lokal wizard pendaftaran IMEI murni mandiri
const currentStep = ref(1);
const isAgreed = ref(false); 
const form = ref({
    sim_type: '',
    imei1: '',
    imei2: '',
    package_id: 1, // Default fallback ID paket untuk local database
    voucher_code: ''
});

const selectedPackage = ref(null);

// SINKRONISASI SETTINGS UTAMA: Menyusun paket dinamis murni membaca tabel settings database admin
const displayPackages = computed(() => {
    // 1. Opsi 1 bulan statis coming soon
    const comingSoonPackage = {
        id: 'coming_soon_1m',
        name: 'Paket Jaringan 1 Bulan',
        price: 0,
        duration_days: 30,
        is_coming_soon: true
    };

    // 2. Mengambil nominal dinamis fee_add_roamer_3m dari database settings panel admin Mas Sultan
    const adminRoamerPrice = globalProps.settings?.fee_add_roamer_3m 
        ? Number(globalProps.settings.fee_add_roamer_3m) 
        : 200000;

    const officialPackage = {
        id: 1, 
        name: 'Paket Jaringan 3 Bulan (Resmi)',
        price: adminRoamerPrice, // Mengikat data dinamis settings murni dari panel admin
        duration_days: 90,
        is_coming_soon: false
    };

    return [comingSoonPackage, officialPackage];
});

// Navigasi tahapan wizard lokal
const nextStep = () => { if (currentStep.value < 4) currentStep.value++; };
const prevStep = () => { if (currentStep.value > 1) currentStep.value--; };

// Computed data helper untuk manipulasi teks template
const imei1Value = computed(() => form.value?.imei1 || '-');
const imei2Value = computed(() => form.value?.imei2 || '-');
const isDualSim = computed(() => form.value?.sim_type === 'dual');
const chosenPackageName = computed(() => selectedPackage.value?.name || 'Belum memilih paket');

// Validasi dinamis tombol Lanjutkan di setiap tahapan
const isStepValid = computed(() => {
    if (currentStep.value === 1) return !!form.value.sim_type;
    if (currentStep.value === 2) {
        if (form.value.sim_type === 'single') return form.value.imei1 && form.value.imei1.length === 15 && /^\d+$/.test(form.value.imei1);
        return form.value.imei1 && form.value.imei1.length === 15 && form.value.imei2 && form.value.imei2.length === 15 && form.value.imei1 !== form.value.imei2 && /^\d+$/.test(form.value.imei1) && /^\d+$/.test(form.value.imei2);
    }
    if (currentStep.value === 3) return !!form.value.package_id;
    return true;
});

const formatRupiah = (price) => 'Rp ' + Number(price || 0).toLocaleString('id-ID');

// SINKRONISASI HARGA TAGIHAN FINAL: Mengikuti rumus (fee_add_roamer_3m * 2) - 50.000 untuk Dual SIM
const formattedFinalPrice = computed(() => {
    const adminRoamerPrice = globalProps.settings?.fee_add_roamer_3m 
        ? Number(globalProps.settings.fee_add_roamer_3m) 
        : 200000;
        
    const finalCalculatedPrice = isDualSim.value ? ((adminRoamerPrice * 2) - 50000) : adminRoamerPrice;
    return 'Rp ' + finalCalculatedPrice.toLocaleString('id-ID');
});

// Halaman Popup Ketentuan Layanan
const handleImeiSubmission = () => {
    Swal.fire({
        title: '<span class="text-slate-950 font-black text-lg tracking-tight">Pernyataan & Ketentuan Layanan</span>',
        html: `
            <div class="text-left text-xs text-slate-600 space-y-3 leading-relaxed font-sans">
                <p class="font-bold text-slate-900">Before proceeding to the payment process, please note the following official regulations:</p>
                <ol class="list-decimal pl-4 space-y-2">
                    <li>Make sure the IMEI number data you entered is <strong>100% correct and valid</strong> according to your device.</li>
                    <li>Make sure your device is <strong>not SIM LOCKED</strong> (locked by the carrier from its country of origin). If you are not sure yet, please perform an independent check in the diagnostics menu.</li>
                    <li>If this device has previously been registered for a <strong>3-Month (90 Days) Roamer</strong> package, ensure the previous roamer package active period has <strong>completely expired</strong> to avoid network conflicts.</li>
                </ol>
                <p class="text-[11px] text-rose-500 font-medium mt-2">* Data input errors resulting from ignoring the points above are beyond the responsibility of the system and funds cannot be refunded.</p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'I Understand, Proceed',
        cancelButtonText: 'Review Again',
        confirmButtonColor: '#2563eb',
        cancelButtonColor: '#94a3b8',
        customClass: { popup: 'rounded-3xl border border-slate-100' }
    }).then((result) => {
        if (result.isConfirmed) {
            nextStep(); 
        }
    });
};

const selectPackage = (pkg) => {
    if (!pkg || pkg.is_coming_soon) return;
    form.value.package_id = pkg.id;
    selectedPackage.value = pkg;
    nextStep(); 
};

// Pipeline Eksekusi Rantai Loading Sekuensial Berputar SweetAlert2 via Axios
const processWalletPayment = async () => {
    Swal.fire({
        title: 'Processing Registration',
        html: `
            <div class="flex flex-col items-center justify-center p-2 font-sans">
                <div class="text-sm font-bold text-slate-800 text-center">
                    Registering IMEI 1 to the ROMEI Central Server...
                </div>
                <div class="text-[11px] text-slate-400 text-center mt-2">
                    Please wait, do not close this page or refresh your browser.
                </div>
            </div>
        `,
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    try {
        if (isDualSim.value) {
            setTimeout(() => {
                Swal.update({
                    html: `
                        <div class="flex flex-col items-center justify-center p-2 font-sans">
                            <div class="text-sm font-bold text-emerald-600 text-center">
                                IMEI 1 Successfully Verified!
                            </div>
                            <div class="text-sm font-bold text-slate-800 text-center mt-3">
                                Proceeding with IMEI 2 Registration to Central Server...
                            </div>
                            <div class="text-[11px] text-slate-400 text-center mt-1">
                                Injecting network manifests for the secondary slot...
                            </div>
                        </div>
                    `
                });
                Swal.showLoading();
            }, 3000); 
        }

        await axios.post('/imei/store', { ...form.value });

        Swal.fire({
            icon: 'success',
            title: '<span class="text-slate-900 font-black">Sequential Activation Success!</span>',
            text: 'Wallet balance deduction has been validated and all IMEI slots of your device are officially registered in the ROMEI central database.',
            confirmButtonColor: '#10b981',
            customClass: { popup: 'rounded-3xl' }
        }).then(() => {
            window.location.href = '/dashboard';
        });

    } catch (error) {
        const serverError = error.response?.data?.errors?.message || error.response?.data?.message || 'There was an issue processing your internal transaction data or your wallet balance is insufficient.';
        Swal.fire({ 
            icon: 'error', 
            title: 'Package Processing Failed', 
            text: serverError, 
            confirmButtonColor: '#ef4444',
            customClass: { popup: 'rounded-3xl' }
        });
    }
};
</script>

<template>
    <UserLayout>
        <Head title="Registrasi IMEI Baru" />
        <div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            
            <nav class="mb-12">
                <ol class="space-y-4 md:flex md:space-y-0 md:space-x-8">
                    <li v-for="step in 4" :key="step" class="md:flex-1">
                        <div :class="[currentStep >= step ? 'border-blue-600 text-blue-600 font-bold' : 'border-gray-200 text-gray-400', 'flex flex-col border-l-4 py-2 pl-4 md:border-l-0 md:border-t-4 md:pl-0 md:pt-4 transition-all duration-200']">
                            <span class="text-[10px] uppercase tracking-wider">Langkah {{ step }}</span>
                            <span class="text-xs mt-0.5 font-sans">
                                <span v-if="step === 1">Jenis Slot SIM</span>
                                <span v-if="step === 2">Input IMEI Gawai</span>
                                <span v-if="step === 3">Pilih Paket Durasi</span>
                                <span v-if="step === 4">Metode Pembayaran</span>
                            </span>
                        </div>
                    </li>
                </ol>
            </nav>

            <div class="bg-white border border-slate-100 rounded-3xl shadow-sm p-8 transition-all duration-300">
                
                <div v-if="currentStep === 1" class="space-y-6">
                    <div>
                        <h2 class="text-xl font-bold text-slate-950 tracking-tight">Pilih Jenis Slot Kartu SIM</h2>
                        <p class="text-xs text-slate-400 mt-1">Sesuaikan dengan ketersediaan slot hardware pada ponsel luar negeri Anda.</p>
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label :class="[form.sim_type === 'single' ? 'border-blue-600 ring-2 ring-blue-600 bg-slate-50/50' : 'border-slate-200', 'relative flex cursor-pointer rounded-2xl border bg-white p-5 shadow-sm transition-all']">
                            <input type="radio" v-model="form.sim_type" value="single" class="sr-only" />
                            <span class="block text-sm font-black text-slate-900">Single SIM Card (1 IMEI)</span>
                        </label>
                        <label :class="[form.sim_type === 'dual' ? 'border-blue-600 ring-2 ring-blue-600 bg-slate-50/50' : 'border-slate-200', 'relative flex cursor-pointer rounded-2xl border bg-white p-5 shadow-sm transition-all']">
                            <input type="radio" v-model="form.sim_type" value="dual" class="sr-only" />
                            <span class="block text-sm font-black text-slate-900">Dual SIM Card (2 IMEI Berantai)</span>
                        </label>
                    </div>
                </div>

                <div v-if="currentStep === 2" class="space-y-6">
                    <div>
                        <h2 class="text-xl font-bold text-slate-950 tracking-tight">Masukkan Nomor IMEI Resmi</h2>
                        <p class="text-xs text-slate-400 mt-1">Tekan dial ke ponsel Anda <span class="font-mono bg-slate-100 px-1 py-0.5 rounded">*#06#</span> untuk memunculkan kode asli hardware.</p>
                    </div>

                    <div class="p-4 bg-amber-50/60 border border-amber-100 rounded-2xl text-xs text-amber-800 space-y-2">
                        <p class="font-bold">Ragu dengan Status Ponsel Luar Negeri Anda?</p>
                        <p class="text-amber-700">Untuk menghindari kegagalan aktivasi, Anda dapat melakukan pengecekan berbayar via saldo ROMEI e-wallet pada menu:</p>
                        <div class="flex flex-wrap gap-3 pt-1">
                            <Link :href="route('services.sim-lock')" class="bg-amber-500 hover:bg-amber-600 text-white font-bold px-3 py-1.5 rounded-lg transition-all">
                                Cek Status SIM Lock
                            </Link>
                            <Link :href="route('services.ceir-history')" class="bg-amber-500 hover:bg-amber-600 text-white font-bold px-3 py-1.5 rounded-lg transition-all">
                                Cek History Sinkronisasi CEIR
                            </Link>
                        </div>
                    </div>

                    <div class="space-y-4 max-w-md">
                        <div class="space-y-1">
                            <label class="text-[10px] font-black uppercase text-slate-400 tracking-wider">IMEI Slot Utama (1)</label>
                            <input type="text" v-model="form.package_id" class="sr-only" /><input type="text" v-model="form.imei1" maxlength="15" class="w-full rounded-xl border-slate-200 px-4 py-3 font-mono text-sm tracking-widest text-slate-900 focus:ring-blue-500 focus:border-blue-500" placeholder="Contoh: 358932107xxxxxx" />
                        </div>
                        <div v-if="isDualSim" class="space-y-1">
                            <label class="text-[10px] font-black uppercase text-slate-400 tracking-wider">IMEI Slot Sekunder (2)</label>
                            <input type="text" v-model="form.imei2" maxlength="15" class="w-full rounded-xl border-slate-200 px-4 py-3 font-mono text-sm tracking-widest text-slate-900 focus:ring-blue-500 focus:border-blue-500" placeholder="Contoh: 358932107xxxxxx" />
                        </div>
                    </div>
                </div>

                <div v-if="currentStep === 3" class="space-y-6">
                    <div>
                        <h2 class="text-xl font-bold text-slate-950 tracking-tight">Pilih Paket Durasi Aktif Jaringan</h2>
                        <p class="text-xs text-slate-400 mt-1">Sesuai kebijakan komersial Bea Cukai, hak akses jaringan seluler turis berlaku kelipatan 90 Hari.</p>
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div 
                            v-for="pkg in displayPackages" 
                            :key="pkg.id" 
                            @click="selectPackage(pkg)" 
                            :class="[
                                form.package_id === pkg.id ? 'border-blue-600 ring-2 ring-blue-600 bg-slate-50/40' : 'border-slate-200', 
                                pkg.is_coming_soon ? 'opacity-60 cursor-not-allowed bg-slate-50' : 'cursor-pointer hover:shadow-md bg-white',
                                'p-6 border rounded-2xl transition-all relative'
                            ]"
                        >
                            <div v-if="pkg.is_coming_soon" class="absolute top-3 right-3 bg-blue-100 text-blue-700 text-[9px] font-extrabold px-2 py-0.5 rounded-full uppercase tracking-wider">
                                Coming Soon
                            </div>

                            <h3 v-text="pkg.name" class="text-sm font-black text-slate-950"></h3>
                            <p v-if="!pkg.is_coming_soon" v-text="formatRupiah(pkg.price)" class="text-xl font-black text-blue-600 font-mono mt-1"></p>
                            <p class="text-sm font-bold text-slate-400 font-sans mt-1" v-else>Estimasi Segera</p>
                            <p class="text-[10px] text-slate-400 font-medium mt-1">Masa aktif melekat di CEIR sepanjang {{ pkg.duration_days }} Hari.</p>
                        </div>
                    </div>
                </div>

                <div v-if="currentStep === 4" class="space-y-6">
                    <div>
                        <h2 class="text-xl font-bold text-slate-950 tracking-tight">Konfirmasi Tagihan & Sumpah Regulasi</h2>
                        <p class="text-xs text-slate-400 mt-1">Tinjau ulang muatan manifestasi data Anda sebelum gerbang invoice pembayaran dikunci.</p>
                    </div>
                    <div class="border border-slate-200/60 rounded-2xl p-5 bg-slate-50/50 space-y-3 text-xs text-slate-600">
                        <div class="flex justify-between border-b border-slate-100 pb-2"><span>Nomor IMEI 1</span><span v-text="imei1Value" class="font-mono font-bold text-slate-900 tracking-wider"></span></div>
                        <div v-if="isDualSim" class="flex justify-between border-b border-slate-100 pb-2"><span>Nomor IMEI 2</span><span v-text="imei2Value" class="font-mono font-bold text-slate-900 tracking-wider"></span></div>
                        <div class="flex justify-between border-b border-slate-100 pb-2"><span>Pilihan Durasi Layanan</span><span v-text="chosenPackageName" class="font-bold text-slate-900"></span></div>
                        <div class="flex justify-between pt-2 text-sm font-black text-slate-950">
                            <span>Total Kewajiban Bayar (Potong Wallet)</span>
                            <span v-text="formattedFinalPrice" class="text-blue-600 font-mono text-base"></span>
                        </div>
                    </div>

                    <div class="relative flex items-start bg-blue-50/40 border border-blue-100 rounded-2xl p-4 mt-4">
                        <div class="flex h-5 items-center">
                            <input id="agreement-checkbox" type="checkbox" v-model="isAgreed" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                        </div>
                        <div class="ml-3 text-xs">
                            <label for="agreement-checkbox" class="font-bold text-slate-950 cursor-pointer select-none">
                                Saya menyatakan sudah membaca, menyetujui, dan menjamin sepenuhnya bahwa IMEI gawai yang dimasukkan benar, bukan berstatus Sim-Lock, serta bersedia mematuhi segala konsekuensi hukum aturan Bea Cukai.
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex justify-between border-t border-slate-100 pt-6">
                    <button type="button" v-if="currentStep > 1" @click="prevStep" class="px-4 py-2 border border-slate-200 rounded-xl text-xs font-bold text-slate-600 bg-white hover:bg-slate-50 transition-all">
                        Kembali
                    </button>
                    <div v-else></div>

                    <button type="button" v-if="currentStep === 2" :disabled="!isStepValid" @click="handleImeiSubmission" class="px-5 py-2.5 rounded-xl bg-blue-600 text-xs font-bold text-white hover:bg-blue-700 disabled:opacity-40 transition-all">
                        Lanjutkan
                    </button>
                    <button type="button" v-else-if="currentStep < 4" :disabled="!isStepValid" @click="nextStep" class="px-5 py-2.5 rounded-xl bg-blue-600 text-xs font-bold text-white hover:bg-blue-700 disabled:opacity-40 transition-all">
                        Lanjutkan
                    </button>
                    <button type="button" v-else :disabled="!isAgreed" @click="processWalletPayment" class="px-6 py-2.5 rounded-xl bg-emerald-500 text-xs font-bold text-white hover:bg-emerald-600 disabled:opacity-40 transition-all shadow-md shadow-emerald-500/10">
                        Selesaikan Registrasi via Saldo Wallet
                    </button>
                </div>
            </div>
        </div>
    </UserLayout>
</template>