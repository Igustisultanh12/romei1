<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { 
    ShieldCheckIcon, 
    KeyIcon, 
    ArrowPathIcon, 
    ArrowLeftOnRectangleIcon,
    EnvelopeIcon,
    ExclamationCircleIcon,
    CheckCircleIcon,
    LockClosedIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    maskedEmail: String,
    expiresIn: {
        type: Number,
        default: 600
    },
    cooldown: {
        type: Number,
        default: 0
    }
});

const form = useForm({
    code: ''
});

const resendForm = useForm({});
const remainingTime = ref(props.expiresIn);
const resendCooldown = ref(props.cooldown);
let countdownTimer = null;

onMounted(() => {
    countdownTimer = setInterval(() => {
        if (remainingTime.value > 0) {
            remainingTime.value--;
        }
        if (resendCooldown.value > 0) {
            resendCooldown.value--;
        }
    }, 1000);
});

onBeforeUnmount(() => {
    if (countdownTimer) clearInterval(countdownTimer);
});

const formatTime = (seconds) => {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
};

const handleCodeInput = (e) => {
    // Hanya izinkan angka dan batasi 6 karakter
    const val = e.target.value.replace(/\D/g, '').slice(0, 6);
    form.code = val;

    // Otomatis submit jika 6 digit sudah terisi
    if (val.length === 6) {
        submitVerification();
    }
};

const submitVerification = () => {
    if (form.code.length !== 6) return;
    form.post(route('admin.2fa.verify'), {
        preserveScroll: true,
        onError: () => {
            form.code = '';
        }
    });
};

const handleResend = () => {
    if (resendCooldown.value > 0 || resendForm.processing) return;
    resendForm.post(route('admin.2fa.resend'), {
        preserveScroll: true,
        onSuccess: () => {
            resendCooldown.value = 60;
            remainingTime.value = 600;
        }
    });
};

const handleCancel = () => {
    router.post(route('admin.2fa.cancel'));
};
</script>

<template>
    <Head title="Verifikasi Keamanan 2FA - ROMEI HQ" />

    <div class="min-h-screen bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 flex flex-col justify-center py-12 sm:px-6 lg:px-8 transition-colors duration-300 font-sans">
        
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <!-- Logo & Title -->
            <div class="flex justify-center items-center gap-2.5 mb-3">
                <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center shadow-lg shadow-blue-500/20">
                    <ShieldCheckIcon class="w-6 h-6" />
                </div>
                <span class="text-2xl font-black tracking-wider text-slate-900 dark:text-white">ROMEI<span class="text-blue-600">.</span>HQ</span>
            </div>
            
            <h2 class="text-center text-xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                Otentikasi Dua Faktor (2FA)
            </h2>
            <p class="mt-1 text-center text-xs text-slate-500 dark:text-slate-400">
                Peningkatan keamanan berlapis untuk akses administratif tingkat tinggi.
            </p>
        </div>

        <div class="mt-6 sm:mx-auto sm:w-full sm:max-w-md px-4">
            <div class="bg-white dark:bg-slate-900 py-8 px-6 sm:px-10 shadow-xl rounded-2xl border border-slate-200 dark:border-slate-800 space-y-6 transition-colors duration-300">
                
                <!-- Destination Info Box -->
                <div class="p-4 rounded-xl bg-blue-50/70 dark:bg-blue-950/30 border border-blue-200/60 dark:border-blue-900/40 text-xs space-y-1.5">
                    <div class="flex items-center gap-2 text-blue-700 dark:text-blue-300 font-bold">
                        <EnvelopeIcon class="w-4 h-4 shrink-0" />
                        <span>Kode OTP Terkirim ke Email:</span>
                    </div>
                    <p class="font-mono text-sm font-black text-slate-900 dark:text-white pl-6">
                        {{ props.maskedEmail }}
                    </p>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 pl-6">
                        Periksa folder Kotak Masuk atau Spambox email Anda.
                    </p>
                </div>

                <!-- Error Alert -->
                <div v-if="form.errors.code" class="p-3.5 rounded-xl bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900/40 text-xs font-bold text-rose-700 dark:text-rose-400 flex items-center gap-2.5">
                    <ExclamationCircleIcon class="w-5 h-5 shrink-0" />
                    <span>{{ form.errors.code }}</span>
                </div>

                <!-- Success Alert -->
                <div v-if="$page.props.flash?.success" class="p-3.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-900/40 text-xs font-bold text-emerald-700 dark:text-emerald-400 flex items-center gap-2.5">
                    <CheckCircleIcon class="w-5 h-5 shrink-0" />
                    <span>{{ $page.props.flash.success }}</span>
                </div>

                <!-- Verification Form -->
                <form @submit.prevent="submitVerification" class="space-y-6">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400">
                                Masukkan 6 Digit Kode OTP
                            </label>
                            <span class="text-[11px] font-mono font-bold" :class="remainingTime > 60 ? 'text-slate-400' : 'text-rose-500 animate-pulse'">
                                Kedaluwarsa: {{ formatTime(remainingTime) }}
                            </span>
                        </div>

                        <div class="relative">
                            <input 
                                type="text"
                                :value="form.code"
                                @input="handleCodeInput"
                                autofocus
                                maxlength="6"
                                placeholder="000000"
                                class="w-full text-center text-3xl font-mono font-black tracking-[0.5em] py-3.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 transition-all placeholder:text-slate-300 dark:placeholder:text-slate-600 placeholder:tracking-[0.5em]"
                            />
                        </div>
                        <p class="text-[11px] text-slate-400 dark:text-slate-500 text-center mt-2">
                            Kode akan diverifikasi otomatis saat seluruh 6 digit terisi.
                        </p>
                    </div>

                    <button 
                        type="submit" 
                        :disabled="form.processing || form.code.length !== 6"
                        class="w-full flex items-center justify-center gap-2 py-3 px-4 border border-transparent rounded-xl shadow-md text-xs font-black uppercase tracking-wider text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
                    >
                        <ArrowPathIcon v-if="form.processing" class="w-4 h-4 animate-spin" />
                        <LockClosedIcon v-else class="w-4 h-4" />
                        Verifikasi &amp; Masuk Dashboard
                    </button>
                </form>

                <!-- Footer Actions: Resend & Cancel -->
                <div class="pt-4 border-t border-slate-100 dark:border-slate-800/80 flex flex-col gap-3">
                    <button 
                        @click="handleResend"
                        type="button"
                        :disabled="resendCooldown > 0 || resendForm.processing"
                        class="w-full text-center text-xs font-bold text-blue-600 dark:text-blue-400 hover:underline disabled:text-slate-400 dark:disabled:text-slate-600 disabled:no-underline cursor-pointer flex items-center justify-center gap-1.5"
                    >
                        <ArrowPathIcon class="w-3.5 h-3.5" :class="{'animate-spin': resendForm.processing}" />
                        <span v-if="resendCooldown > 0">
                            Kirim Ulang Kode (Tunggu {{ resendCooldown }} detik)
                        </span>
                        <span v-else>
                            Tidak Menerima Kode? Kirim Ulang OTP
                        </span>
                    </button>

                    <button 
                        @click="handleCancel"
                        type="button"
                        class="w-full text-center text-xs font-semibold text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors flex items-center justify-center gap-1.5 py-1"
                    >
                        <ArrowLeftOnRectangleIcon class="w-3.5 h-3.5" />
                        Batalkan &amp; Kembali ke Halaman Login
                    </button>
                </div>

            </div>

            <!-- Security Footer Note -->
            <p class="mt-6 text-center text-[11px] text-slate-400 dark:text-slate-600 font-medium">
                Sistem Proteksi Akses Berlapis ROMEI v2.0 • Enkripsi End-to-End
            </p>
        </div>
    </div>
</template>
