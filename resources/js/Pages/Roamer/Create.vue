<script setup>
import { computed, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import UserLayout from '@/Layouts/UserLayout.vue';
import Swal from 'sweetalert2';
import axios from 'axios';

// Menarik manifes global inertia props untuk membaca data settings
const { props: globalProps } = usePage();

const form = ref({
    sim_type: 'single', // Default: single
    imei1: '',
    imei2: '',
    duration: '3' // Default mengunci durasi paket 3 Bulan 
});

const isAgreed = ref(false); // State checkbox persetujuan syarat & ketentuan 
const isSubmitting = ref(false);

// Menghitung referensi harga dari DB settings secara dinamis sesuai durasi & jenis SIM 
const currentPrice = computed(() => {
    const basePrice = form.value.duration === '1'
        ? (globalProps.settings?.fee_add_roamer_1m ? Number(globalProps.settings.fee_add_roamer_1m) : 135000)
        : (globalProps.settings?.fee_add_roamer_3m ? Number(globalProps.settings.fee_add_roamer_3m) : 180000);

    // Terapkan skema harga promo jika user memilih opsi Dual SIM
    if (form.value.sim_type === 'dual') {
        return (basePrice * 2) - 50000;
    }
    return basePrice;
});

const formatRupiah = (price) => 'Rp ' + Number(price || 0).toLocaleString('id-ID');

const submitRoamer = async () => {
    // Validasi IMEI Utama
    if (!form.value.imei1 || form.value.imei1.length !== 15 || !/^\d+$/.test(form.value.imei1)) {
        Swal.fire({ 
            icon: 'warning', 
            title: 'Periksa IMEI 1', 
            text: 'Nomor IMEI utama harus berisi tepat 15 digit angka.' 
        });
        return;
    }

    // Validasi IMEI Kedua jika memilih skema Dual SIM
    if (form.value.sim_type === 'dual') {
        if (!form.value.imei2 || form.value.imei2.length !== 15 || !/^\d+$/.test(form.value.imei2)) {
            Swal.fire({ 
                icon: 'warning', 
                title: 'Periksa IMEI 2', 
                text: 'Nomor IMEI sekunder wajib diisi tepat 15 digit angka untuk Dual SIM.' 
            });
            return;
        }
        if (form.value.imei1 === form.value.imei2) {
            Swal.fire({ 
                icon: 'warning', 
                title: 'IMEI Duplikat', 
                text: 'Nomor IMEI 1 dan IMEI 2 tidak boleh sama.' 
            });
            return;
        }
    }

    isSubmitting.value = true;
    
    // SPINNER LOADING PERTAMA: Memproses IMEI 1 Utama
    Swal.fire({
        title: 'Memproses Registrasi',
        html: `
            <div class="flex flex-col items-center justify-center p-2 font-sans">
                <div class="text-sm font-bold text-slate-800 text-center">
                    Mendaftarkan IMEI Slot 1 ke Server Pusat...
                </div>
                <div class="text-[11px] text-slate-400 text-center mt-2">
                    Mohon tunggu, jangan menutup halaman ini atau merefresh browser.
                </div>
            </div>
        `,
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    try {
        // --- STEP 1: Mengirimkan data IMEI 1 Terlebih Dahulu ---
        await axios.post('/roamer/register', {
            imei: form.value.imei1,
            duration: form.value.duration
        });

        // --- STEP 2: Jika merupakan Dual SIM, lanjutkan pendaftaran IMEI 2 ---
        if (form.value.sim_type === 'dual') {
            // Perbarui animasi loading SweetAlert secara realtime
            Swal.update({
                html: `
                    <div class="flex flex-col items-center justify-center p-2 font-sans">
                        <div class="text-sm font-bold text-emerald-600 text-center">
                            IMEI 1 Sukses Terverifikasi!
                        </div>
                        <div class="text-sm font-bold text-slate-800 text-center mt-3">
                            Melanjutkan Pendaftaran IMEI Slot 2 ke Server Pusat...
                        </div>
                        <div class="text-[11px] text-slate-400 text-center mt-1">
                            Sedang menginjeksi manifes jaringan slot sekunder...
                        </div>
                    </div>
                `
            });
            Swal.showLoading();

            // Eksekusi pengiriman IMEI 2
            await axios.post('/roamer/register', {
                imei: form.value.imei2,
                duration: form.value.duration
            });
        }
        
        // POPUP NOTIFIKASI SUKSES FINAL
        Swal.fire({
            icon: 'success',
            title: '<span class="text-slate-900 font-black">Aktivasi Sukses Berantai!</span>',
            text: 'Potongan saldo wallet tervalidasi dan seluruh slot IMEI gawai Anda resmi terdaftar di database pusat ROMEI.',
            confirmButtonColor: '#10b981',
            customClass: { popup: 'rounded-3xl' }
        }).then(() => {
            // Mengalihkan langsung ke halaman Riwayat Transaksi Services
            window.location.href = '/services/history';
        });

    } catch (error) {
        const serverError = error.response?.data?.message || 'Gagal memproses pendaftaran roamer atau saldo tidak mencukupi.';
        Swal.fire({ 
            icon: 'error', 
            title: 'Transaksi Ditolak', 
            text: serverError, 
            confirmButtonColor: '#ef4444' 
        });
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <UserLayout>
        <Head title="Aktivasi Add Roamer" />
        <div class="max-w-3xl mx-auto py-10 px-4 font-sans">
            <div class="bg-white border border-slate-100 rounded-3xl shadow-sm p-8 space-y-6">
                
                <div>
                    <h2 class="text-xl font-bold text-slate-950 tracking-tight">Aktivasi Paket Add Roamer Resmi</h2>
                    <p class="text-xs text-slate-400 mt-1">Layanan suntik paket roamer operator luar negeri via server pusat CEIRKU .</p>
                </div>

                <div class="space-y-5">
                    <!-- SEKTOR 1: PILIHAN JENIS SIM CARD -->
                    <div class="space-y-2">
                        <label class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Pilih Jenis Slot Kartu SIM</label>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <label :class="[form.sim_type === 'single' ? 'border-blue-600 ring-2 ring-blue-600 bg-slate-50/50' : 'border-slate-200', 'relative flex cursor-pointer rounded-2xl border bg-white p-4 shadow-sm transition-all']">
                                <input type="radio" v-model="form.sim_type" value="single" class="sr-only" />
                                <span class="block text-sm font-black text-slate-900">Single SIM Card (1 IMEI)</span>
                            </label>
                            <label :class="[form.sim_type === 'dual' ? 'border-blue-600 ring-2 ring-blue-600 bg-slate-50/50' : 'border-slate-200', 'relative flex cursor-pointer rounded-2xl border bg-white p-4 shadow-sm transition-all']">
                                <input type="radio" v-model="form.sim_type" value="dual" class="sr-only" />
                                <span class="block text-sm font-black text-slate-900">Dual SIM Card (2 IMEI Berantai)</span>
                            </label>
                        </div>
                    </div>

                    <!-- SEKTOR 2: INPUT IMEI HARDWARE -->
                    <div class="grid grid-cols-1 gap-4" :class="[form.sim_type === 'dual' ? 'sm:grid-cols-2' : '']">
                        <div class="space-y-1">
                            <label class="text-[10px] font-black uppercase text-slate-400 tracking-wider">IMEI Slot Utama (1)</label>
                            <input type="text" v-model="form.imei1" maxlength="15" class="w-full rounded-xl border-slate-200 px-4 py-3 font-mono text-sm tracking-widest text-slate-900 focus:ring-blue-500 focus:border-blue-500" placeholder="Contoh: 358932107xxxxxx" />
                        </div>
                        <div v-if="form.sim_type === 'dual'" class="space-y-1">
                            <label class="text-[10px] font-black uppercase text-slate-400 tracking-wider">IMEI Slot Sekunder (2)</label>
                            <input type="text" v-model="form.imei2" maxlength="15" class="w-full rounded-xl border-slate-200 px-4 py-3 font-mono text-sm tracking-widest text-slate-900 focus:ring-blue-500 focus:border-blue-500" placeholder="Contoh: 358932107xxxxxx" />
                        </div>
                    </div>

                    <!-- SEKTOR 3: PILIHAN DURASI PAKET KOMERSIAL -->
                    <div class="space-y-2">
                        <label class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Pilih Durasi Paket</label>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <!-- Paket 1 Bulan diset murni Coming Soon & disabled total -->
                            <div class="relative flex flex-col rounded-2xl border border-slate-100 bg-slate-50/70 p-4 opacity-60 cursor-not-allowed select-none">
                                <span class="absolute top-3 right-3 bg-blue-100 text-blue-700 text-[8px] font-extrabold px-1.5 py-0.5 rounded-full uppercase tracking-wider">Coming Soon</span>
                                <span class="block text-sm font-black text-slate-400">Paket Roamer 1 Bulan</span>
                                <span class="text-xs text-slate-400 mt-0.5">Proses 1-24 Jam </span>
                            </div>
                            <!-- Paket 3 Bulan Terbuka Aktif -->
                            <label :class="[form.duration === '3' ? 'border-blue-600 ring-2 ring-blue-600 bg-slate-50/50' : 'border-slate-200', 'relative flex flex-col cursor-pointer rounded-2xl border bg-white p-4 shadow-sm transition-all']">
                                <input type="radio" v-model="form.duration" value="3" class="sr-only" />
                                <span class="block text-sm font-black text-slate-900">Paket Roamer 3 Bulan</span>
                                <span class="text-xs text-slate-400 mt-0.5">Proses Maksimal 3 Hari </span>
                            </label>
                        </div>
                    </div>

                    <!-- SEKTOR 4: RINGKASAN FINANSIAL REALTIME -->
                    <div class="border border-slate-100 rounded-2xl p-4 bg-slate-50/50 flex justify-between items-center">
                        <div class="flex flex-col">
                            <span class="text-xs font-medium text-slate-600">Tarif Aktivasi (Potong Wallet):</span>
                            <span v-if="form.sim_type === 'dual'" class="text-[10px] text-emerald-600 font-bold mt-0.5">* Diskon Bundling Dual SIM Terbuka (-Rp 50.000)</span>
                        </div>
                        <span class="text-base font-black text-blue-600 font-mono">{{ formatRupiah(currentPrice) }}</span>
                    </div>

                    <hr class="border-slate-100 my-2" />

                    <!-- SEKTOR 5: SYARAT & KETENTUAN INTERNAL BERDASARKAN SYRAT KETENTUAN.PDF  -->
                    <div class="border border-slate-200 rounded-2xl p-5 bg-slate-50/40 space-y-4 text-xs text-slate-600 leading-relaxed">
                        <div class="text-center border-b border-slate-200/60 pb-3">
                            <h3 class="font-black text-slate-950 text-sm tracking-tight">SYARAT & KETENTUAN PEMESANAN IMEI ROAMER</h3>
                            <p class="text-[10px] text-slate-400 mt-0.5">Harap baca dan pahami semua aturan sebelum melanjutkan pemesanan.</p>
                        </div>

                        <!-- HUB DIAGNOSIS UTILITAS INTERKONEKSI -->
                        <div class="p-3 bg-amber-50/60 border border-amber-100 rounded-xl space-y-2">
                            <p class="font-bold text-amber-900">ROMEI: Wajib Cek Status Mandiri Sebelum Input Paket!</p>
                            <p class="text-amber-800 text-[11px]">Jika status IMEI Anda sudah terdaftar di <strong>Kemenperin / Bea Cukai (Status Registered)</strong>, sinyal tidak akan naik dan dana *TIDAK DAPAT DI-REFUND*. Lakukan pengecekan di bawah ini:</p>
                            <div class="flex flex-wrap gap-2.5 pt-1">
                                <Link :href="route('services.sim-lock')" class="bg-amber-500 hover:bg-amber-600 text-white font-bold px-3 py-1.5 rounded-lg text-[10px] transition-all">
                                    Cek Status IMEI
                                </Link>
                                <Link :href="route('services.ceir-history')" class="bg-amber-500 hover:bg-amber-600 text-white font-bold px-3 py-1.5 rounded-lg text-[10px] transition-all">
                                    Tracing History CEIR
                                </Link>
                            </div>
                        </div>

                        <!-- ATURAN MANIFESTASI PDF -->
                        <div class="space-y-2 text-[11px]">
                            <p class="font-bold text-slate-900">ATURAN UTAMA KONTRAK REGULASI:</p>
                            <ul class="list-disc pl-4 space-y-1.5 text-slate-600">
                                <li><strong>IMEI wajib diketik/input dengan benar</strong>. Tidak ada sistem refund saldo jika terjadi kesalahan ketik/input dari user  .</li>
                                <li>Jika status IMEI Anda <strong>berstatus roamer / aktif</strong>, Mohon tidak untuk mendaftar, tunggu hingga masa berlaku habis  .</li>
                                <li>Kerusakan hardware perangkat, perangkat berstatus <strong>Ex-Bypass / WiFi Only</strong>, serta perangkat terkunci operator asal luar negeri <strong>(SIM LOCK)</strong>, <strong>TIDAK ADA REFUND</strong>  .</li>
                                <li>Pesanan yang telah sukses dikirim ke dalam sistem <strong>tidak dapat dibatalkan atau ditarik kembali</strong> dengan alasan apapun  .</li>
                                <li>Kesalahan yang menyebabkan terpotongnya saldo atas kelalaian sendiri <strong>tidak bisa mengajukan refund</strong>  .</li>
                            </ul>
                        </div>

                        <!-- CHECKBOX PERNYATAAN SUMPAH INTEGRITAS -->
                        <div class="relative flex items-start bg-blue-50/50 border border-blue-100 rounded-xl p-3.5 mt-2">
                            <div class="flex h-5 items-center">
                                <input id="agreement-checkbox" type="checkbox" v-model="isAgreed" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer" />
                            </div>
                            <div class="ml-3 text-[11px]">
                                <label for="agreement-checkbox" class="font-bold text-slate-950 cursor-pointer select-none">
                                    Dengan ini saya telah membaca syarat dan ketentuan yang sudah ditentukan, dan bertanggung jawab penuh atas transaksi yang saya lakukan. Dengan ini saya setuju dengan syarat and ketentuan tersebut.
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- ACTION EXECUTE BUTTON -->
                    <button type="button" :disabled="isSubmitting || !isAgreed" @click="submitRoamer" class="w-full py-3.5 rounded-xl bg-blue-600 text-xs font-bold text-white hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed transition-all shadow-md shadow-blue-500/10">
                        Selesaikan Registrasi & Ambil Saldo Wallet
                    </button>
                </div>
            </div>
        </div>
    </UserLayout>
</template>