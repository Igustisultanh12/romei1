<script setup>
import { ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { 
    EnvelopeIcon, 
    ServerIcon, 
    ShieldCheckIcon, 
    PaperAirplaneIcon, 
    BookOpenIcon, 
    CheckCircleIcon, 
    ExclamationCircleIcon,
    EyeIcon,
    EyeSlashIcon,
    ArrowPathIcon,
    LockClosedIcon
} from '@heroicons/vue/24/outline';
import Swal from 'sweetalert2';

const props = defineProps({
    mailConfig: Object
});

const page = usePage();
const activeTab = ref('config'); // 'config' | 'test' | 'guide'
const showPassword = ref(false);

const form = useForm({
    mail_mailer: props.mailConfig?.mail_mailer || 'smtp',
    mail_host: props.mailConfig?.mail_host || 'smtp.gmail.com',
    mail_port: props.mailConfig?.mail_port || '587',
    mail_username: props.mailConfig?.mail_username || '',
    mail_password: props.mailConfig?.mail_password || '',
    mail_encryption: props.mailConfig?.mail_encryption || 'tls',
    mail_from_address: props.mailConfig?.mail_from_address || 'no-reply@romei.my.id',
    mail_from_name: props.mailConfig?.mail_from_name || 'ROMEI Platform',
    enable_email_otp: props.mailConfig?.enable_email_otp || '1',
    admin_2fa_enabled: props.mailConfig?.admin_2fa_enabled || '1',
});

const testForm = useForm({
    recipient_email: ''
});

const presetGmail = () => {
    form.mail_mailer = 'smtp';
    form.mail_host = 'smtp.gmail.com';
    form.mail_port = '587';
    form.mail_encryption = 'tls';
    form.mail_from_name = 'ROMEI Platform';
    Swal.fire({
        title: 'Preset Gmail Diterapkan',
        text: 'Silakan isi Username (email Gmail) dan Password (App Password 16-karakter dari Akun Google).',
        icon: 'info',
        confirmButtonColor: '#2563eb'
    });
};

const presetMailtrap = () => {
    form.mail_mailer = 'smtp';
    form.mail_host = 'sandbox.smtp.mailtrap.io';
    form.mail_port = '2525';
    form.mail_encryption = 'tls';
    form.mail_from_name = 'ROMEI Sandbox';
    Swal.fire({
        title: 'Preset Mailtrap Diterapkan',
        text: 'Silakan isi kredensial Username dan Password dari dashboard Mailtrap Anda.',
        icon: 'info',
        confirmButtonColor: '#2563eb'
    });
};

const saveConfig = () => {
    Swal.fire({
        title: 'Menyimpan Pengaturan',
        text: 'Sedang memvalidasi dan memperbarui konfigurasi SMTP...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    form.post(route('admin.mail.update'), {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({
                title: 'Berhasil Disimpan',
                text: 'Konfigurasi Mail Gateway dan Keamanan 2FA telah berhasil diperbarui.',
                icon: 'success',
                confirmButtonColor: '#2563eb'
            });
        },
        onError: () => {
            Swal.fire({
                title: 'Gagal Menyimpan',
                text: 'Harap periksa kembali isian formulir konfigurasi.',
                icon: 'error',
                confirmButtonColor: '#ef4444'
            });
        }
    });
};

const sendTestEmail = () => {
    if (!testForm.recipient_email) {
        Swal.fire({
            title: 'Email Kosong',
            text: 'Masukkan alamat email tujuan uji coba terlebih dahulu.',
            icon: 'warning',
            confirmButtonColor: '#2563eb'
        });
        return;
    }

    Swal.fire({
        title: 'Mengirim Email Uji Coba',
        text: `Menghubungkan ke ${form.mail_host}:${form.mail_port}...`,
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    testForm.post(route('admin.mail.test'), {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({
                title: 'Pengiriman Berhasil',
                text: `Email uji coba berhasil dikirimkan ke ${testForm.recipient_email}. Silakan cek kotak masuk atau folder spam.`,
                icon: 'success',
                confirmButtonColor: '#2563eb'
            });
        },
        onError: () => {
            Swal.fire({
                title: 'Pengiriman Gagal',
                text: 'Koneksi ke server SMTP gagal. Periksa host, port, kredensial, atau enkripsi.',
                icon: 'error',
                confirmButtonColor: '#ef4444'
            });
        }
    });
};
</script>

<template>
    <AdminLayout>
        <Head title="Mail Gateway & 2FA SMTP - ROMEI HQ" />

        <div class="space-y-6 max-w-7xl mx-auto py-2">
            
            <!-- Page Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 dark:border-slate-800 pb-5">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="px-2.5 py-1 rounded-md text-[11px] font-bold bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20 uppercase tracking-wider">
                            Gateway SMTP
                        </span>
                        <span class="text-slate-300 dark:text-slate-700 text-xs">•</span>
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Pusat Layanan Email & M2FA</span>
                    </div>
                    <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight mt-1">
                        Mail Gateway Management
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        Kelola server SMTP pengiriman kode OTP 2FA Admin, notifikasi operasional, dan pengujian koneksi jaringan.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button 
                        @click="presetGmail" 
                        type="button" 
                        class="px-3.5 py-2 text-xs font-bold rounded-lg bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 transition shadow-sm flex items-center gap-2"
                    >
                        <ServerIcon class="w-4 h-4 text-rose-500" />
                        Preset Gmail
                    </button>
                    <button 
                        @click="presetMailtrap" 
                        type="button" 
                        class="px-3.5 py-2 text-xs font-bold rounded-lg bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 transition shadow-sm flex items-center gap-2"
                    >
                        <ServerIcon class="w-4 h-4 text-emerald-500" />
                        Preset Mailtrap
                    </button>
                </div>
            </div>

            <!-- Flash Notification -->
            <div v-if="$page.props.flash?.success" class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 dark:text-emerald-400 text-xs font-bold flex items-center gap-3">
                <CheckCircleIcon class="w-5 h-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                <span>{{ $page.props.flash.success }}</span>
            </div>

            <div v-if="$page.props.flash?.error" class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-700 dark:text-rose-400 text-xs font-bold flex items-center gap-3">
                <ExclamationCircleIcon class="w-5 h-5 shrink-0 text-rose-600 dark:text-rose-400" />
                <span>{{ $page.props.flash.error }}</span>
            </div>

            <!-- Status Metrics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                <div class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
                    <div class="space-y-1">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Driver Mailer</span>
                        <h3 class="text-xl font-black text-slate-900 dark:text-white uppercase">{{ form.mail_mailer }}</h3>
                        <p class="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            Mode Driver Aktif
                        </p>
                    </div>
                    <div class="w-11 h-11 rounded-lg bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                        <EnvelopeIcon class="w-6 h-6" />
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
                    <div class="space-y-1">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">SMTP Server Host</span>
                        <h3 class="text-base font-black text-slate-900 dark:text-white truncate max-w-[180px] font-mono">{{ form.mail_host }}</h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">
                            Port {{ form.mail_port }} ({{ form.mail_encryption ? form.mail_encryption.toUpperCase() : 'NO ENCRYPT' }})
                        </p>
                    </div>
                    <div class="w-11 h-11 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 flex items-center justify-center">
                        <ServerIcon class="w-6 h-6" />
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
                    <div class="space-y-1">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Status Admin 2FA</span>
                        <h3 class="text-xl font-black" :class="form.admin_2fa_enabled === '1' ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'">
                            {{ form.admin_2fa_enabled === '1' ? 'Aktif (Proteksi Penuh)' : 'Nonaktif' }}
                        </h3>
                        <p class="text-[11px] font-semibold" :class="form.admin_2fa_enabled === '1' ? 'text-blue-600 dark:text-blue-400' : 'text-slate-400'">
                            {{ form.admin_2fa_enabled === '1' ? 'Wajib Verifikasi OTP Email' : 'Login Standar' }}
                        </p>
                    </div>
                    <div class="w-11 h-11 rounded-lg flex items-center justify-center" :class="form.admin_2fa_enabled === '1' ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-slate-100 dark:bg-slate-800 text-slate-400'">
                        <ShieldCheckIcon class="w-6 h-6" />
                    </div>
                </div>
            </div>

            <!-- Navigation Tabs -->
            <div class="flex items-center gap-2 border-b border-slate-200 dark:border-slate-800">
                <button 
                    @click="activeTab = 'config'" 
                    type="button" 
                    class="px-5 py-3 text-xs font-bold border-b-2 transition flex items-center gap-2"
                    :class="activeTab === 'config' ? 'border-blue-600 text-blue-600 dark:text-blue-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'"
                >
                    <ServerIcon class="w-4 h-4" />
                    Konfigurasi SMTP
                </button>
                <button 
                    @click="activeTab = 'test'" 
                    type="button" 
                    class="px-5 py-3 text-xs font-bold border-b-2 transition flex items-center gap-2"
                    :class="activeTab === 'test' ? 'border-blue-600 text-blue-600 dark:text-blue-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'"
                >
                    <PaperAirplaneIcon class="w-4 h-4" />
                    Uji Coba Pengiriman Email
                </button>
                <button 
                    @click="activeTab = 'guide'" 
                    type="button" 
                    class="px-5 py-3 text-xs font-bold border-b-2 transition flex items-center gap-2"
                    :class="activeTab === 'guide' ? 'border-blue-600 text-blue-600 dark:text-blue-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'"
                >
                    <BookOpenIcon class="w-4 h-4" />
                    Panduan Setup SMTP
                </button>
            </div>

            <!-- TAB 1: FORM KONFIGURASI -->
            <div v-show="activeTab === 'config'" class="space-y-6">
                <form @submit.prevent="saveConfig" class="space-y-6">
                    
                    <!-- KARTU 1: DRIVER & KONEKSI SERVER -->
                    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-5">
                        <div class="border-b border-slate-100 dark:border-slate-800/80 pb-3">
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <ServerIcon class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                                1. Parameter Server Koneksi SMTP
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Tentukan alamat host, port koneksi, dan mekanisme enkripsi data pengiriman email.</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-5">
                            <div class="sm:col-span-4">
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">Driver Mailer</label>
                                <select v-model="form.mail_mailer" class="w-full text-xs font-bold rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:border-blue-500 focus:ring-0 p-2.5">
                                    <option value="smtp">SMTP (Production / Direct Server)</option>
                                    <option value="log">LOG (Development / Simulasi storage/logs)</option>
                                </select>
                            </div>

                            <div class="sm:col-span-5">
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">SMTP Host Server</label>
                                <input type="text" v-model="form.mail_host" required placeholder="Contoh: smtp.gmail.com" class="w-full text-xs font-mono font-bold rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:border-blue-500 focus:ring-0 p-2.5" />
                            </div>

                            <div class="sm:col-span-3">
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">Port Koneksi</label>
                                <input type="number" v-model="form.mail_port" required placeholder="587" class="w-full text-xs font-mono font-bold rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:border-blue-500 focus:ring-0 p-2.5" />
                            </div>

                            <div class="sm:col-span-4">
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">Enkripsi Jalur</label>
                                <select v-model="form.mail_encryption" class="w-full text-xs font-bold rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:border-blue-500 focus:ring-0 p-2.5">
                                    <option value="tls">TLS (Direkomendasikan untuk Port 587)</option>
                                    <option value="ssl">SSL (Port 465)</option>
                                    <option value="none">Tanpa Enkripsi (Port 25 / Local)</option>
                                </select>
                            </div>

                            <div class="sm:col-span-4">
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">Email Pengirim (From Address)</label>
                                <input type="email" v-model="form.mail_from_address" required placeholder="no-reply@romei.my.id" class="w-full text-xs font-bold rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:border-blue-500 focus:ring-0 p-2.5" />
                            </div>

                            <div class="sm:col-span-4">
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">Nama Pengirim (From Name)</label>
                                <input type="text" v-model="form.mail_from_name" required placeholder="ROMEI Platform" class="w-full text-xs font-bold rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:border-blue-500 focus:ring-0 p-2.5" />
                            </div>
                        </div>
                    </div>

                    <!-- KARTU 2: KREDENSIAL AUTENTIKASI -->
                    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-5">
                        <div class="border-b border-slate-100 dark:border-slate-800/80 pb-3">
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <LockClosedIcon class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                                2. Kredensial Autentikasi SMTP
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Username dan sandi yang digunakan oleh sistem untuk mengotentikasi koneksi ke server email.</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">Username / Email Akun</label>
                                <input type="text" v-model="form.mail_username" placeholder="Contoh: admin@romei.my.id atau akun@gmail.com" class="w-full text-xs font-mono font-bold rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:border-blue-500 focus:ring-0 p-2.5" />
                            </div>

                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Password / Sandi Aplikasi</label>
                                    <button @click="showPassword = !showPassword" type="button" class="text-xs text-blue-600 dark:text-blue-400 font-bold flex items-center gap-1 hover:underline">
                                        <EyeIcon v-if="!showPassword" class="w-3.5 h-3.5" />
                                        <EyeSlashIcon v-else class="w-3.5 h-3.5" />
                                        <span>{{ showPassword ? 'Sembunyikan' : 'Perlihatkan' }}</span>
                                    </button>
                                </div>
                                <input :type="showPassword ? 'text' : 'password'" v-model="form.mail_password" placeholder="Masukkan password SMTP atau App Password..." class="w-full text-xs font-mono font-bold rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:border-blue-500 focus:ring-0 p-2.5" />
                            </div>
                        </div>
                    </div>

                    <!-- KARTU 3: KEBIJAKAN KEAMANAN & 2FA -->
                    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-5">
                        <div class="border-b border-slate-100 dark:border-slate-800/80 pb-3">
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <ShieldCheckIcon class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                                3. Kebijakan Keamanan Otentikasi Dua Faktor (M2FA)
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Aktifkan pengamanan berlapis bagi administrator saat masuk ke panel ROMEI HQ.</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950/50 space-y-3">
                                <div class="flex items-center justify-between">
                                    <div class="space-y-0.5">
                                        <h4 class="text-xs font-bold text-slate-900 dark:text-white">Admin M2FA (Multi-Factor Auth)</h4>
                                        <p class="text-[11px] text-slate-500 dark:text-slate-400">Wajibkan verifikasi OTP email saat akun admin melakukan login.</p>
                                    </div>
                                    <select v-model="form.admin_2fa_enabled" class="text-xs font-bold rounded-lg border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white p-2">
                                        <option value="1">Aktif (Wajib OTP)</option>
                                        <option value="0">Nonaktif</option>
                                    </select>
                                </div>
                            </div>

                            <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950/50 space-y-3">
                                <div class="flex items-center justify-between">
                                    <div class="space-y-0.5">
                                        <h4 class="text-xs font-bold text-slate-900 dark:text-white">Pengiriman OTP via Email</h4>
                                        <p class="text-[11px] text-slate-500 dark:text-slate-400">Aktifkan pengiriman kode OTP melalui gateway SMTP ini.</p>
                                    </div>
                                    <select v-model="form.enable_email_otp" class="text-xs font-bold rounded-lg border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white p-2">
                                        <option value="1">Aktif</option>
                                        <option value="0">Nonaktif</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tombol Simpan -->
                    <div class="flex justify-end pt-2">
                        <button 
                            type="submit" 
                            :disabled="form.processing"
                            class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold transition shadow-md flex items-center gap-2 disabled:opacity-50"
                        >
                            <ArrowPathIcon v-if="form.processing" class="w-4 h-4 animate-spin" />
                            <CheckCircleIcon v-else class="w-4 h-4" />
                            Simpan Perubahan Konfigurasi
                        </button>
                    </div>
                </form>
            </div>

            <!-- TAB 2: UJI COBA PENGIRIMAN -->
            <div v-show="activeTab === 'test'" class="space-y-6">
                <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-6">
                    <div class="border-b border-slate-100 dark:border-slate-800 pb-3">
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <PaperAirplaneIcon class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                            Pengujian Pengiriman Email Langsung
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            Kirimkan email uji coba berisi format template kode OTP resmi ROMEI untuk memastikan server SMTP berfungsi sempurna.
                        </p>
                    </div>

                    <form @submit.prevent="sendTestEmail" class="max-w-lg space-y-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">
                                Alamat Email Penerima Uji Coba
                            </label>
                            <input 
                                type="email" 
                                v-model="testForm.recipient_email" 
                                required 
                                placeholder="Masukkan email Anda (misal: admin@gmail.com)..." 
                                class="w-full text-xs font-bold rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:border-blue-500 focus:ring-0 p-2.5" 
                            />
                            <p class="text-[11px] text-slate-400 mt-1">Sistem akan mengirimkan template email OTP resmi ROMEI HQ ke alamat di atas.</p>
                        </div>

                        <button 
                            type="submit" 
                            :disabled="testForm.processing"
                            class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold transition shadow-md flex items-center gap-2 disabled:opacity-50"
                        >
                            <ArrowPathIcon v-if="testForm.processing" class="w-4 h-4 animate-spin" />
                            <PaperAirplaneIcon v-else class="w-4 h-4" />
                            Kirim Email Uji Coba Sekarang
                        </button>
                    </form>
                </div>
            </div>

            <!-- TAB 3: PANDUAN SETUP -->
            <div v-show="activeTab === 'guide'" class="space-y-6">
                <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-5">
                    <div class="border-b border-slate-100 dark:border-slate-800 pb-3">
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <BookOpenIcon class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                            Panduan Konfigurasi Server SMTP
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Langkah-langkah pengaturan SMTP untuk penyedia layanan umum.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs text-slate-600 dark:text-slate-300">
                        <div class="p-5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950/50 space-y-3">
                            <h4 class="font-bold text-slate-900 dark:text-white text-sm">Pengaturan Gmail SMTP</h4>
                            <ol class="list-decimal pl-4 space-y-2 leading-relaxed">
                                <li>Aktifkan <strong>2-Step Verification</strong> pada Akun Google Anda.</li>
                                <li>Buka menu <strong>Keamanan &gt; Sandi Aplikasi (App Passwords)</strong>.</li>
                                <li>Buat sandi baru untuk aplikasi ROMEI, Google akan menghasilkan 16 karakter unik.</li>
                                <li>Gunakan <strong>smtp.gmail.com</strong> sebagai Host, Port <strong>587</strong>, dan Enkripsi <strong>TLS</strong>.</li>
                                <li>Masukkan alamat Gmail Anda sebagai Username dan 16 karakter Sandi Aplikasi sebagai Password.</li>
                            </ol>
                        </div>

                        <div class="p-5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950/50 space-y-3">
                            <h4 class="font-bold text-slate-900 dark:text-white text-sm">Pengaturan Domain Pribadi / cPanel / aaPanel</h4>
                            <ol class="list-decimal pl-4 space-y-2 leading-relaxed">
                                <li>Gunakan mail host domain Anda, contoh: <strong>mail.domainanda.com</strong>.</li>
                                <li>Port standar SSL adalah <strong>465</strong>, atau Port TLS <strong>587</strong>.</li>
                                <li>Pastikan DNS domain memiliki record <strong>SPF</strong> dan <strong>DKIM</strong> agar email tidak masuk ke folder spam.</li>
                                <li>Gunakan alamat email lengkap sebagai Username, contoh: <strong>admin@domainanda.com</strong>.</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AdminLayout>
</template>
