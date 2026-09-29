<script setup>
import { useForm, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue'; 
import Swal from 'sweetalert2';
import {
    CpuChipIcon,
    CurrencyDollarIcon,
    CreditCardIcon,
    PhotoIcon,
    WrenchScrewdriverIcon,
    CheckCircleIcon
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

    doku_client_id: props.settings.doku_client_id || '',
    doku_secret_key: props.settings.doku_secret_key || '',
    maintenance_mode: props.settings.maintenance_mode || false,
    beranda_image: null, 
});

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
                        <div>
                            <label class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 block mb-1">
                                URL API Gateway CEIRKU (Dinamis)
                            </label>
                            <input 
                                type="text" 
                                v-model="form.ceirku_api_url" 
                                placeholder="https://ceirku.net/api/v1" 
                                class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm font-mono focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition"
                            />
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                                Base URL gateway CEIRKU (misal: <span class="font-mono text-blue-600 dark:text-blue-400">https://ceirku.net/api/v1</span>). Jika vendor mengganti domain atau endpoint, ubah di sini tanpa menyentuh kode program.
                            </p>
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

                <!-- SEKSI 3: GERBANG TRANSAKSI DOKU TOP UP -->
                <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-5 transition-colors">
                    <div class="border-b border-slate-200 dark:border-slate-800 pb-3 flex items-center gap-2">
                        <CreditCardIcon class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                        <div>
                            <h3 class="font-bold text-slate-900 dark:text-white text-base">3. Payment Gateway DOKU (Top Up Saldo)</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Digunakan untuk generasi QRIS dan notifikasi pembayaran otomatis konsumen.</p>
                        </div>
                    </div>

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