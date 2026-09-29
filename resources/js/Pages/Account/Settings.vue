<script setup>
import { ref, watch } from 'vue';
import { useForm, Head, usePage } from '@inertiajs/vue3';
import UserLayout from '@/Layouts/UserLayout.vue';
import Swal from 'sweetalert2';
import { UserIcon, ShieldCheckIcon, BellIcon, KeyIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    user_data: Object
});

const activeTab = ref('profil'); // Pilihan tab: 'profil', 'keamanan', 'notifikasi'

// Form Keamanan (Ubah Password)
const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: ''
});

// Form Notifikasi WhatsApp
const notifyForm = useForm({
    wa_notification: props.user_data.wa_notification
});

// Watcher untuk mendeteksi eror validasi ubah password dari backend
watch(() => passwordForm.errors, (newErrors) => {
    if (Object.keys(newErrors).length > 0) {
        const firstKey = Object.keys(newErrors)[0];
        Swal.fire({
            icon: 'error',
            title: 'Gagal Memperbarui',
            text: newErrors[firstKey],
            confirmButtonColor: '#2563eb'
        });
    }
}, { deep: true });

const submitPassword = () => {
    passwordForm.post(route('account.update-password'), {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset();
            Swal.fire({
                icon: 'success',
                title: 'Password Diperbarui',
                text: 'Kata sandi keamanan akun ROMEI Anda berhasil diganti.',
                confirmButtonColor: '#2563eb'
            });
        }
    });
};

const saveNotificationSettings = () => {
    notifyForm.post(route('account.update-notification'), {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({
                icon: 'success',
                title: 'Preferensi Disimpan',
                text: 'Pengaturan pengiriman notifikasi WhatsApp berhasil diperbarui.',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        }
    });
};
</script>

<template>
    <UserLayout>
        <Head title="Pengaturan Akun - ROMEI" />
        
        <div class="max-w-4xl mx-auto p-6 space-y-6">
            <div class="flex flex-col">
                <h1 class="text-2xl font-black text-slate-950 tracking-tight">Manajemen Pusat Akun</h1>
                <p class="text-xs text-slate-400 mt-0.5">Kelola data informasi personal, enkripsi keamanan sandi, dan utilitas notifikasi gateway Anda.</p>
            </div>

            <div class="flex border-b border-slate-200 font-bold text-xs space-x-2">
                <button @click="activeTab = 'profil'" :class="[activeTab === 'profil' ? 'border-blue-600 text-blue-600 border-b-2' : 'text-slate-400 hover:text-slate-600', 'flex items-center space-x-2 pb-3 px-4 transition-all focus:outline-none']">
                    <UserIcon class="w-4 h-4" />
                    <span>Profil Pengguna</span>
                </button>
                <button @click="activeTab = 'keamanan'" :class="[activeTab === 'keamanan' ? 'border-blue-600 text-blue-600 border-b-2' : 'text-slate-400 hover:text-slate-600', 'flex items-center space-x-2 pb-3 px-4 transition-all focus:outline-none']">
                    <ShieldCheckIcon class="w-4 h-4" />
                    <span>Keamanan Sandi</span>
                </button>
                <button @click="activeTab = 'notifikasi'" :class="[activeTab === 'notifikasi' ? 'border-blue-600 text-blue-600 border-b-2' : 'text-slate-400 hover:text-slate-600', 'flex items-center space-x-2 pb-3 px-4 transition-all focus:outline-none']">
                    <BellIcon class="w-4 h-4" />
                    <span>Notifikasi Gateway</span>
                </button>
            </div>

            <div v-if="activeTab === 'profil'" class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm max-w-2xl transition-all">
                <div class="flex items-center space-x-4 pb-6 border-b border-slate-100">
                    <div class="w-16 h-16 rounded-full bg-slate-950 text-white flex items-center justify-center font-black text-xl uppercase shadow-md border-2 border-white ring-4 ring-slate-100">
                        {{ user_data.name.charAt(0) }}
                    </div>
                    <div>
                        <h3 class="text-base font-black text-slate-950">{{ user_data.name }}</h3>
                        <p class="text-xs font-mono text-blue-600 font-bold">ID Pengguna: #000{{ user_data.id }}</p>
                    </div>
                </div>

                <div class="mt-6 space-y-4 text-xs font-medium text-slate-700">
                    <div class="grid grid-cols-3 py-2 border-b border-slate-50">
                        <span class="text-slate-400 font-bold">Nama Lengkap</span>
                        <span class="col-span-2 text-slate-950 font-black">{{ user_data.name }}</span>
                    </div>
                    <div class="grid grid-cols-3 py-2 border-b border-slate-50">
                        <span class="text-slate-400 font-bold">Alamat Email</span>
                        <span class="col-span-2 text-slate-950 font-mono font-bold">{{ user_data.email }}</span>
                    </div>
                    <div class="grid grid-cols-3 py-2 border-b border-slate-50">
                        <span class="text-slate-400 font-bold">No. WhatsApp</span>
                        <span class="col-span-2 text-slate-950 font-bold">{{ user_data.whatsapp_number }}</span>
                    </div>
                    <div class="grid grid-cols-3 py-2">
                        <span class="text-slate-400 font-bold">Tanggal Terdaftar</span>
                        <span class="col-span-2 text-slate-500 font-mono font-bold">{{ user_data.created_at }} WIB</span>
                    </div>
                </div>
            </div>

            <div v-if="activeTab === 'keamanan'" class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm max-w-xl transition-all">
                <div class="mb-4">
                    <h3 class="text-sm font-black text-slate-950">Perbarui Kata Sandi Akun</h3>
                    <p class="text-[11px] text-slate-400 mt-0.5">Demi proteksi saldo wallet kuota, lakukan penggantian berkala dengan kombinasi aman.</p>
                </div>

                <form @submit.prevent="submitPassword" class="space-y-4 mt-6">
                    <div class="flex flex-col space-y-1">
                        <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Kata Sandi Saat Ini</label>
                        <input v-model="passwordForm.current_password" type="password" placeholder="••••••••" class="w-full bg-slate-50 border border-slate-200 rounded-xl text-xs px-3 py-2.5 text-slate-900 focus:outline-none focus:ring-1 focus:ring-blue-500" required />
                    </div>

                    <div class="flex flex-col space-y-1">
                        <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Kata Sandi Baru</label>
                        <input v-model="passwordForm.password" type="password" placeholder="Minimal 8 Karakter" class="w-full bg-slate-50 border border-slate-200 rounded-xl text-xs px-3 py-2.5 text-slate-900 focus:outline-none focus:ring-1 focus:ring-blue-500" required />
                    </div>

                    <div class="flex flex-col space-y-1">
                        <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Ulangi Konfirmasi Kata Sandi Baru</label>
                        <input v-model="passwordForm.password_confirmation" type="password" placeholder="Masukkan Sekali Lagi" class="w-full bg-slate-50 border border-slate-200 rounded-xl text-xs px-3 py-2.5 text-slate-900 focus:outline-none focus:ring-1 focus:ring-blue-500" required />
                    </div>

                    <button type="submit" :disabled="passwordForm.processing" class="flex items-center space-x-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-slate-300 text-white rounded-xl text-xs font-bold transition-all shadow-md">
                        <KeyIcon class="w-4 h-4" />
                        <span>{{ passwordForm.processing ? 'Memproses Enkripsi...' : 'Simpan Kredensial Baru' }}</span>
                    </button>
                </form>
            </div>

            <div v-if="activeTab === 'notifikasi'" class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm max-w-xl transition-all">
                <div class="mb-4">
                    <h3 class="text-sm font-black text-slate-950">Notifikasi Gateway WhatsApp</h3>
                    <p class="text-[11px] text-slate-400 mt-0.5">Tentukan bagaimana sistem ROMEI mengirimkan info struk invoice, status CEIR, dan pencairan dana saldo.</p>
                </div>

                <div class="mt-6 p-4 bg-slate-50 rounded-xl border border-slate-100 flex items-center justify-between">
                    <div class="max-w-sm">
                        <span class="text-xs font-black text-slate-950 block">Kirim Struk & Status Jaringan Lewat WA</span>
                        <p class="text-[11px] text-slate-400 mt-0.5">Sistem otomatis menembak pesan WA bot secara asinkron sesaat setelah transaksi terverifikasi paid.</p>
                    </div>
                    
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" v-model="notifyForm.wa_notification" @change="saveNotificationSettings" class="sr-only peer" />
                        <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>
            </div>

        </div>
    </UserLayout>
</template>