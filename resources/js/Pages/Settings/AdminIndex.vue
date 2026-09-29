<script setup>
import { useForm, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue'; 
import Swal from 'sweetalert2';

const props = defineProps({
    settings: Object
});

// State Form helper bawaan Inertia terintegrasi dinamis dengan parameter controller baru
const form = useForm({
    ceirku_mode: props.settings.ceirku_mode || 'sandbox',
    ceirku_api_key: props.settings.ceirku_api_key,
    
    // Skema input tarif penanganan keuntungan komersial internal ROMEI
    fee_check_sim_lock: props.settings.fee_check_sim_lock ?? 5000,
    fee_check_ceir_history: props.settings.fee_check_ceir_history ?? 7500,
    fee_add_roamer_3m: props.settings.fee_add_roamer_3m ?? 125000,

    doku_client_id: props.settings.doku_client_id,
    doku_secret_key: props.settings.doku_secret_key,
    maintenance_mode: props.settings.maintenance_mode,
    beranda_image: null, 
});

// State untuk menampilkan gambar preview secara instan sebelum disimpan
const imagePreview = ref(props.settings.beranda_image_url || null);

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
            form.beranda_image = null; // Reset input file setelah berhasil
            Swal.fire({
                title: 'Berhasil!',
                text: 'Konfigurasi Mode API, Skema Tarif Finansial, dan Aset Gambar Beranda sukses diperbarui.',
                icon: 'success',
                confirmButtonColor: '#1e293b'
            });
        },
        onError: () => {
            Swal.fire({
                title: 'Gagal',
                text: 'Periksa kembali kelayakan data input atau format file gambar Anda.',
                icon: 'error',
                confirmButtonColor: '#ef4444'
            });
        }
    });
};
</script>

<template>
    <Head title="Pengaturan Kontrol API & Tarif" />
    <AdminLayout>
        <div class="max-w-4xl mx-auto py-6 px-4 space-y-6">
            <div>
                <h1 class="text-2xl font-black text-gray-900 tracking-tight">Pusat Konfigurasi API, Tarif & Aset</h1>
                <p class="text-sm text-gray-500">Kelola operasional Live/Sandbox CEIRKU, margin profit diagnosis e-wallet, kredensial DOKU, serta visual platform.</p>
            </div>

            <form @submit.prevent="saveSettings" class="space-y-6">
                
                <!-- SEKSI 0: UPLOAD ASET VISUAL -->
                <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm space-y-4">
                    <div class="border-b border-gray-100 pb-3">
                        <h3 class="font-bold text-gray-900">0. Gambar Ilustrasi Beranda Utama (Carton)</h3>
                        <p class="text-xs text-gray-400">Unggah berkas gambar pengganti animasi terminal beranda (`carton.png`) secara dinamis.</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-6 items-center">
                        <div class="sm:col-span-4 flex flex-col items-center justify-center p-4 bg-gray-50 rounded-xl border border-dashed border-gray-200">
                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2 block">Pratinjau Aset</span>
                            <img v-if="imagePreview" :src="imagePreview" alt="Preview Carton" class="max-h-32 object-contain rounded drop-shadow" />
                            <div v-else class="text-xs text-gray-400 py-8 text-center italic">Belum ada gambar yang diunggah</div>
                        </div>
                        <div class="sm:col-span-8 space-y-2">
                            <label class="text-xs font-bold uppercase tracking-wider text-gray-400 block">Pilih File Gambar (.png, .jpg, .svg)</label>
                            <input type="file" accept="image/*" @change="handleImageUpload" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-bold file:bg-gray-900 file:text-white hover:file:bg-gray-800 transition-all cursor-pointer" />
                            <p class="text-[11px] text-gray-400 font-medium">Rekomendasi ukuran rasio persegi seimbang dengan latar belakang transparan.</p>
                        </div>
                    </div>
                </div>

                <!-- SEKSI 1: KENDALI INTEGRASI CEIRKU PUSAT -->
                <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm space-y-4">
                    <div class="border-b border-gray-100 pb-3">
                        <h3 class="font-bold text-gray-900">1. Integrasi Jaringan API CEIRKU</h3>
                        <p class="text-xs text-gray-400">Pengaturan pengalihan mode uji coba tanpa biaya dan peletakan token tunggal keamanan admin.</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="text-xs font-bold uppercase tracking-wider text-gray-400">Environment Mode</label>
                            <select v-model="form.ceirku_mode" class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-navy-500 focus:border-navy-500 font-bold">
                                <option value="sandbox">SANDBOX (Uji Coba Gratis - ID 20-23)</option>
                                <option value="live">LIVE PRODUCTION (Komersial Asli - ID 30-31)</option>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="text-xs font-bold uppercase tracking-wider text-gray-400">CEIRKU X-Api-Key Reseller</label>
                            <input type="password" v-model="form.ceirku_api_key" placeholder="Masukkan X-Api-Key Anda dari dashboard profile pusat..." class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-navy-500 focus:border-navy-500 font-mono" />
                        </div>
                    </div>
                </div>

                <!-- SEKSI 2: MARGIN KEUNTUNGAN TARIF WALLET KONSUMEN (MARUP AREA) -->
                <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm space-y-4">
                    <div class="border-b border-gray-100 pb-3">
                        <h3 class="font-bold text-gray-900">2. Skema Tarif Diagnosis & Layanan Berbayar ROMEI</h3>
                        <p class="text-xs text-gray-400">Besaran nominal saldo e-wallet konsumen ROMEI yang dipotong otomatis per eksekusi sukses.</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="text-xs font-bold uppercase tracking-wider text-gray-400">Tarif Cek SIM Lock (Rp)</label>
                            <input type="number" v-model="form.fee_check_sim_lock" min="0" class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-navy-500 focus:border-navy-500 font-bold font-mono text-blue-600" />
                            <p class="text-[10px] text-gray-400 mt-1">Biaya modal pusat: Rp 0 (Sandbox) / Bersyarat (Live).</p>
                        </div>
                        <div>
                            <label class="text-xs font-bold uppercase tracking-wider text-gray-400">Tarif Cek History CEIR (Rp)</label>
                            <input type="number" v-model="form.fee_check_ceir_history" min="0" class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-navy-500 focus:border-navy-500 font-bold font-mono text-blue-600" />
                            <p class="text-[10px] text-gray-400 mt-1">Biaya modal pusat: Rp 0 (Sandbox) / Bersyarat (Live).</p>
                        </div>
                        <div>
                            <label class="text-xs font-bold uppercase tracking-wider text-gray-400">Tarif Add Roamer 3 Bulan (Rp)</label>
                            <input type="number" v-model="form.fee_add_roamer_3m" min="0" class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-navy-500 focus:border-navy-500 font-bold font-mono text-emerald-600" />
                            <p class="text-[10px] text-gray-400 mt-1">Biaya modal asli akun reseller pusat: Rp 75.000.</p>
                        </div>
                    </div>
                </div>

                <!-- SEKSI 3: GERBANG TRANSAKSI DOKU TOP UP -->
                <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm space-y-4">
                    <div class="border-b border-gray-100 pb-3">
                        <h3 class="font-bold text-gray-900">3. Kredensial Payment Gateway DOKU (Top Up)</h3>
                        <p class="text-xs text-gray-400">Digunakan untuk menjenerasikan kode QRIS otomatis pengisian saldo dompet user digital.</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs font-bold uppercase tracking-wider text-gray-400">DOKU Client ID (Mall ID)</label>
                            <input type="text" v-model="form.doku_client_id" class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-navy-500 focus:border-navy-500" />
                        </div>
                        <div>
                            <label class="text-xs font-bold uppercase tracking-wider text-gray-400">DOKU Shared Secret Key</label>
                            <input type="password" v-model="form.doku_secret_key" placeholder="••••••••••••••••" class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-navy-500 focus:border-navy-500" />
                        </div>
                    </div>
                </div>

                <!-- FOOTER BANNER ACTIONS CONTROL -->
                <div class="flex items-center justify-between bg-gray-50 p-4 rounded-xl border border-gray-100">
                    <div class="flex items-center space-x-2">
                        <input type="checkbox" id="maintenance" v-model="form.maintenance_mode" class="rounded text-gray-900 focus:ring-gray-800" />
                        <label for="maintenance" class="text-sm font-semibold text-gray-700 cursor-pointer select-none">Aktifkan Maintenance Mode Aplikasi</label>
                    </div>
                    <button type="submit" :disabled="form.processing" class="bg-gray-900 hover:bg-gray-800 text-white font-bold px-6 py-2 rounded-lg text-sm transition-all shadow-sm disabled:opacity-50">
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Seluruh Perubahan' }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>