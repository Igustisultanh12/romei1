<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { CpuChipIcon } from '@heroicons/vue/24/outline';
import Swal from 'sweetalert2'; // Mengimport SweetAlert2 Core
import { watch } from 'vue';

// Form state dibersihkan dari field referral_code
const form = useForm({
    name: '',
    email: '',
    whatsapp_number: '', // Tetap dipertahankan untuk integrasi kirim notifikasi WA via Si Sinden
    password: '',
    password_confirmation: '',
});

// Watcher cerdas untuk menangkap error validasi dari Laravel dan menampilkannya via SweetAlert Toast
watch(() => form.errors, (newErrors) => {
    if (Object.keys(newErrors).length > 0) {
        const firstErrorKey = Object.keys(newErrors)[0];
        const errorMessage = newErrors[firstErrorKey];

        Swal.fire({
            icon: 'error',
            title: 'Pendaftaran Gagal',
            text: errorMessage,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 4000,
            timerProgressBar: true,
            background: '#ffffff',
            color: '#0f172a',
            iconColor: '#ef4444',
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });
    }
}, { deep: true });

const submit = () => {
    // Tampilkan animasi loading sweetalert sesaat sebelum mengirim data registrasi
    Swal.fire({
        title: 'Membuat Akun Baru',
        text: 'Sedang mengamankan infrastruktur kredensial Anda...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
        onSuccess: () => {
            Swal.fire({
                icon: 'success',
                title: 'Pendaftaran Berhasil!',
                text: 'Akun ROMEI Anda telah berhasil dikonfigurasi.',
                confirmButtonColor: '#2563eb',
                timer: 2000,
                timerProgressBar: true
            });
        }
    });
};
</script>

<template>
    <div class="min-h-screen bg-white text-slate-900 font-sans antialiased relative flex items-center justify-center overflow-hidden py-12 px-4 sm:px-6 lg:px-8">
        
        <div class="absolute inset-0 pointer-events-none opacity-20">
            <div class="absolute top-[-10%] left-[-10%] w-[50vw] h-[50vw] rounded-full bg-blue-400 blur-[120px]"></div>
            <div class="absolute bottom-[-10%] right-[-10%] w-[45vw] h-[45vw] rounded-full bg-indigo-300 blur-[110px]"></div>
        </div>

        <div class="w-full max-w-md relative z-10">
            <div class="flex flex-col items-center justify-center mb-6">
                <div class="flex items-center gap-2">
                    <CpuChipIcon class="w-7 h-7 text-blue-600 animate-pulse-slow" />
                    <span class="text-2xl font-black tracking-tight text-slate-950">ROMEI<span class="text-blue-600">.</span></span>
                </div>
                <span class="text-[9px] text-slate-400 font-bold uppercase tracking-wider mt-1">Premium IMEI Registration Platform</span>
            </div>

            <div class="bg-slate-50/70 backdrop-blur-md border border-slate-200/80 p-8 rounded-3xl shadow-sm hover:shadow-md transition-all duration-300">
                <Head title="Daftar Akun Pengguna" />

                <div class="mb-6 text-center">
                    <h2 class="text-2xl font-black tracking-tight text-slate-950">Daftar Akun</h2>
                    <p class="text-xs text-slate-400 font-medium mt-1">Silakan isi formulir di bawah ini sebelum melanjutkan.</p>
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    
                    <div class="space-y-1">
                        <InputLabel for="name" value="Nama Lengkap" class="text-xs font-bold uppercase tracking-wider text-slate-400" />

                        <TextInput
                            id="name"
                            type="text"
                            class="mt-1 block w-full rounded-xl border-slate-200 bg-white text-xs text-slate-800 font-medium placeholder-slate-300 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 shadow-sm px-4 py-2.5"
                            v-model="form.name"
                            required
                            autofocus
                            autocomplete="name"
                            placeholder="Nama lengkap sesuai paspor / identitas"
                        />

                        <InputError class="mt-1 text-[11px] font-bold text-rose-500" :message="form.errors.name" />
                    </div>

                    <div class="space-y-1">
                        <InputLabel for="email" value="Alamat Email" class="text-xs font-bold uppercase tracking-wider text-slate-400" />

                        <TextInput
                            id="email"
                            type="email"
                            class="mt-1 block w-full rounded-xl border-slate-200 bg-white text-xs text-slate-800 font-medium placeholder-slate-300 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 shadow-sm px-4 py-2.5"
                            v-model="form.email"
                            required
                            autocomplete="username"
                            placeholder="nama@email.com"
                        />

                        <InputError class="mt-1 text-[11px] font-bold text-rose-500" :message="form.errors.email" />
                    </div>

                    <div class="space-y-1">
                        <InputLabel for="whatsapp_number" value="Nomor WhatsApp" class="text-xs font-bold uppercase tracking-wider text-slate-400" />

                        <TextInput
                            id="whatsapp_number"
                            type="tel"
                            class="mt-1 block w-full rounded-xl border-slate-200 bg-white text-xs text-slate-800 font-bold placeholder-slate-300 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 shadow-sm px-4 py-2.5 font-mono"
                            v-model="form.whatsapp_number"
                            required
                            placeholder="Contoh: 081234567890"
                            autocomplete="tel"
                        />

                        <InputError class="mt-1 text-[11px] font-bold text-rose-500" :message="form.errors.whatsapp_number" />
                    </div>

                    <div class="space-y-1">
                        <InputLabel for="password" value="Kata Sandi Baru" class="text-xs font-bold uppercase tracking-wider text-slate-400" />

                        <TextInput
                            id="password"
                            type="password"
                            class="mt-1 block w-full rounded-xl border-slate-200 bg-white text-xs text-slate-800 font-medium placeholder-slate-300 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 shadow-sm px-4 py-2.5"
                            v-model="form.password"
                            required
                            autocomplete="new-password"
                            placeholder="Minimal 8 karakter"
                        />

                        <InputError class="mt-1 text-[11px] font-bold text-rose-500" :message="form.errors.password" />
                    </div>

                    <div class="space-y-1">
                        <InputLabel for="password_confirmation" value="Konfirmasi Kata Sandi" class="text-xs font-bold uppercase tracking-wider text-slate-400" />

                        <TextInput
                            id="password_confirmation"
                            type="password"
                            class="mt-1 block w-full rounded-xl border-slate-200 bg-white text-xs text-slate-800 font-medium placeholder-slate-300 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 shadow-sm px-4 py-2.5"
                            v-model="form.password_confirmation"
                            required
                            autocomplete="new-password"
                            placeholder="Ketik ulang kata sandi"
                        />

                        <InputError class="mt-1 text-[11px] font-bold text-rose-500" :message="form.errors.password_confirmation" />
                    </div>

                    <div class="pt-3 space-y-4">
                        <PrimaryButton
                            class="w-full justify-center bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl text-xs shadow-md shadow-blue-600/10 hover:shadow-blue-600/20 hover:scale-[1.01] active:scale-[0.99] transition-all text-center"
                            :class="{ 'opacity-50 pointer-events-none': form.processing }"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Mendaftarkan Kredensial...' : 'Daftar Akun Sekarang' }}
                        </PrimaryButton>

                        <div class="text-center pt-1 border-t border-slate-200/60">
                            <p class="text-xs text-slate-400 font-medium mt-3">
                                Sudah memiliki akun terdaftar? 
                                <Link :href="route('login')" class="font-black text-blue-600 hover:text-blue-700 hover:underline ms-1">
                                    Masuk Aplikasi
                                </Link>
                            </p>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="text-center mt-6 text-[10px] text-slate-400 font-mono font-medium opacity-70">
                <span>Copyright © 2026 ROMEI Hub. Infrastructure Secure Connection.</span>
            </div>
        </div>
    </div>
</template>

<style>
/* Kostumisasi gaya font global SweetAlert2 agar menyatu dengan UI ROMEI */
.swal2-popup {
    font-family: ui-sans-serif, system-ui, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji" !important;
    border-radius: 1.5rem !important;
    border: 1px solid rgba(226, 232, 240, 0.8) !important;
}
.swal2-toast {
    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.05) !important;
}
</style>

<style scoped>
html {
    scroll-behavior: smooth;
}
@keyframes pulseAnimation {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}
.animate-pulse-slow {
    animation: pulseAnimation 3s ease-in-out infinite;
}
</style>