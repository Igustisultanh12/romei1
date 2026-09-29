<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { CpuChipIcon } from '@heroicons/vue/24/outline';
import Swal from 'sweetalert2'; 
import { watch } from 'vue';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

// Watcher cerdas untuk menangkap error validasi dari Laravel dan menampilkannya via SweetAlert Toast
watch(() => form.errors, (newErrors) => {
    if (Object.keys(newErrors).length > 0) {
        // PERBAIKAN KUNCI: Tutup modal loading terlebih dahulu agar toast tidak tertutup/terhalang overlay
        Swal.close();

        // Mengambil pesan error pertama yang dikembalikan sistem
        const firstErrorKey = Object.keys(newErrors)[0];
        const errorMessage = newErrors[firstErrorKey];

        Swal.fire({
            icon: 'error',
            title: 'Otentikasi Gagal',
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
    // Tampilkan animasi loading sweetalert sesaat sebelum mengirim data
    Swal.fire({
        title: 'Memproses Masuk',
        text: 'Mohon tunggu sejenak...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    form.post(route('login'), {
        onFinish: () => {
            form.reset('password');
        },
        onSuccess: () => {
            Swal.close(); // Tutup loading alert jika sukses masuk sistem
        },
        onError: () => {
            // Jika gagal, penutupan loading dihandle otomatis oleh watcher di atas
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
            <div class="flex flex-col items-center justify-center mb-8">
                <div class="flex items-center gap-2">
                    <CpuChipIcon class="w-7 h-7 text-blue-600 animate-pulse-slow" />
                    <span class="text-2xl font-black tracking-tight text-slate-950">ROMEI<span class="text-blue-600">.</span></span>
                </div>
                <span class="text-[9px] text-slate-400 font-bold uppercase tracking-wider mt-1">Premium IMEI Registration Platform</span>
            </div>

            <div class="bg-slate-50/70 backdrop-blur-md border border-slate-200/80 p-8 rounded-3xl shadow-sm hover:shadow-md transition-all duration-300">
                <Head title="Masuk ke Akun ROMEI" />

                <div class="mb-6 text-center">
                    <h2 class="text-2xl font-black tracking-tight text-slate-950">Selamat Datang</h2>
                    <p class="text-xs text-slate-400 font-medium mt-1">Silakan masuk untuk mengelola gerbang & memulai transaksi.</p>
                </div>

                <div v-if="status" class="mb-4 text-xs font-bold text-emerald-600 p-3 bg-emerald-500/10 border border-emerald-500/20 rounded-xl">
                    {{ status }}
                </div>

                <form @submit.prevent="submit" class="space-y-5">
                    <div class="space-y-1">
                        <InputLabel for="email" value="Alamat Email" class="text-xs font-bold uppercase tracking-wider text-slate-400" />

                        <TextInput
                            id="email"
                            type="email"
                            class="mt-1 block w-full rounded-xl border-slate-200 bg-white text-xs text-slate-800 font-medium placeholder-slate-300 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 shadow-sm px-4 py-2.5"
                            v-model="form.email"
                            required
                            autofocus
                            autocomplete="username"
                            placeholder="nama@email.com"
                        />

                        <InputError class="mt-1 text-[11px] font-bold text-rose-500" :message="form.errors.email" />
                    </div>

                    <div class="space-y-1">
                        <div class="flex justify-between items-center">
                            <InputLabel for="password" value="Kata Sandi" class="text-xs font-bold uppercase tracking-wider text-slate-400" />
                            
                            <Link
                                v-if="canResetPassword"
                                :href="route('password.request')"
                                class="text-xs font-bold text-blue-600 hover:text-blue-700 transition-colors hover:underline"
                            >
                                Lupa sandi?
                            </Link>
                        </div>

                        <TextInput
                            id="password"
                            type="password"
                            class="mt-1 block w-full rounded-xl border-slate-200 bg-white text-xs text-slate-800 font-medium placeholder-slate-300 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 shadow-sm px-4 py-2.5"
                            v-model="form.password"
                            required
                            autocomplete="current-password"
                            placeholder="••••••••"
                        />

                        <InputError class="mt-1 text-[11px] font-bold text-rose-500" :message="form.errors.password" />
                    </div>

                    <div class="flex items-center justify-between pt-1">
                        <label class="flex items-center cursor-pointer group">
                            <Checkbox name="remember" v-model:checked="form.remember" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500/50 w-4 h-4 transition-all" />
                            <span class="ms-2.5 text-xs text-slate-500 font-semibold select-none group-hover:text-slate-700 transition-colors">Ingat saya di perangkat ini</span>
                        </label>
                    </div>

                    <div class="pt-2 space-y-4">
                        <PrimaryButton
                            class="w-full justify-center bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl text-xs shadow-md shadow-blue-600/10 hover:shadow-blue-600/20 hover:scale-[1.01] active:scale-[0.99] transition-all text-center"
                            :class="{ 'opacity-50 pointer-events-none': form.processing }"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Memproses Otentikasi...' : 'Masuk Sekarang' }}
                        </PrimaryButton>

                        <div class="text-center pt-1 border-t border-slate-200/60">
                            <p class="text-xs text-slate-400 font-medium mt-3">
                                Belum memiliki hak akses akun? 
                                <Link :href="route('register')" class="font-black text-blue-600 hover:text-blue-700 hover:underline ms-1">
                                    Daftar Sekarang
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