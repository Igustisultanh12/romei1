<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { 
    ShieldCheckIcon, 
    KeyIcon, 
    ArrowPathIcon, 
    ArrowLeftOnRectangleIcon,
    EnvelopeIcon,
    ChatBubbleLeftRightIcon,
    ExclamationCircleIcon,
    CheckCircleIcon,
    LockClosedIcon,
    SunIcon,
    MoonIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    channel: {
        type: String,
        default: 'whatsapp' // 'whatsapp' | 'email'
    },
    maskedEmail: String,
    maskedPhone: String,
    hasWhatsapp: {
        type: Boolean,
        default: true
    },
    hasEmail: {
        type: Boolean,
        default: true
    },
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

const resendForm = useForm({
    channel: props.channel
});

const isDark = ref(true);
const remainingTime = ref(props.expiresIn);
const resendCooldown = ref(props.cooldown);
let countdownTimer = null;

onMounted(() => {
    // Sinkronisasi status tema gelap/terang
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'light') {
        isDark.value = false;
        document.documentElement.classList.remove('dark');
    } else {
        isDark.value = true;
        document.documentElement.classList.add('dark');
    }

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

const toggleTheme = () => {
    isDark.value = !isDark.value;
    if (isDark.value) {
        document.documentElement.classList.add('dark');
        localStorage.setItem('theme', 'dark');
    } else {
        document.documentElement.classList.remove('dark');
        localStorage.setItem('theme', 'light');
    }
};

const formatTime = (seconds) => {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
};

const handleCodeInput = (e) => {
    const val = e.target.value.replace(/\D/g, '').slice(0, 6);
    form.code = val;

    if (val.length === 6) {
        submitVerification();
    }
};

const submitVerification = () => {
    if (form.code.length !== 6 || form.processing) return;
    form.post(route('admin.2fa.verify'), {
        preserveScroll: true,
        onError: () => {
            form.code = '';
        }
    });
};

const switchOrResendChannel = (targetChannel) => {
    if (resendCooldown.value > 0 || resendForm.processing) return;

    resendForm.channel = targetChannel;
    resendForm.post(route('admin.2fa.resend'), {
        preserveScroll: true,
        onSuccess: () => {
            resendCooldown.value = 60;
            remainingTime.value = 600;
            form.code = '';
        }
    });
};

const handleCancel = () => {
    router.post(route('admin.2fa.cancel'));
};
</script>

<template>
    <Head title="Verifikasi Keamanan 2FA - ROMEI HQ" />

    <div class="min-h-screen bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 flex flex-col justify-center py-12 sm:px-6 lg:px-8 transition-colors duration-300 font-sans relative">
        
        <!-- Theme Toggle on top right corner -->
        <div class="absolute top-6 right-6">
            <button 
                @click="toggleTheme" 
                type="button"
                class="p-2.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 shadow-sm transition"
                title="Ganti Tema Tampilan"
            >
                <SunIcon v-if="isDark" class="w-5 h-5 text-amber-400" />
                <MoonIcon v-else class="w-5 h-5 text-indigo-600" />
            </button>
        </div>

        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <!-- Brand Logo & Header -->
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
                Peningkatan keamanan berlapis untuk gerbang administratif tingkat tinggi.
            </p>
        </div>

        <div class="mt-6 sm:mx-auto sm:w-full sm:max-w-md px-4">
            <div class="bg-white dark:bg-slate-900 py-8 px-6 sm:px-10 shadow-xl rounded-2xl border border-slate-200 dark:border-slate-800 space-y-6 transition-colors duration-300">
                
                <!-- Channel Selector Tabs -->
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">
                        Pilih Kanal Pengiriman OTP:
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <!-- WhatsApp Option -->
                        <button 
                            type="button" 
                            @click="switchOrResendChannel('whatsapp')"
                            :disabled="!props.hasWhatsapp || resendForm.processing"
                            :class="[
                                props.channel === 'whatsapp' 
                                    ? 'border-blue-600 bg-blue-50/80 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 ring-2 ring-blue-500/20' 
                                    : 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 hover:border-slate-300 dark:hover:border-slate-700',
                                !props.hasWhatsapp ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer'
                            ]"
                            class="p-3 rounded-xl border text-left flex flex-col justify-between transition-all relative overflow-hidden"
                        >
                            <div class="flex items-center justify-between mb-1.5">
                                <ChatBubbleLeftRightIcon class="w-5 h-5 text-emerald-500 shrink-0" />
                                <span v-if="props.channel === 'whatsapp'" class="px-1.5 py-0.5 rounded text-[9px] font-extrabold bg-blue-600 text-white uppercase tracking-wider">
                                    Aktif
                                </span>
                            </div>
                            <div>
                                <span class="block text-xs font-black">WhatsApp</span>
                                <span class="block text-[10px] font-mono text-slate-500 dark:text-slate-400 truncate">
                                    {{ props.hasWhatsapp ? props.maskedPhone : 'Belum diatur' }}
                                </span>
                            </div>
                        </button>

                        <!-- Email Option -->
                        <button 
                            type="button" 
                            @click="switchOrResendChannel('email')"
                            :disabled="!props.hasEmail || resendForm.processing"
                            :class="[
                                props.channel === 'email' 
                                    ? 'border-blue-600 bg-blue-50/80 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 ring-2 ring-blue-500/20' 
                                    : 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 hover:border-slate-300 dark:hover:border-slate-700',
                                !props.hasEmail ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer'
                            ]"
                            class="p-3 rounded-xl border text-left flex flex-col justify-between transition-all relative overflow-hidden"
                        >
                            <div class="flex items-center justify-between mb-1.5">
                                <EnvelopeIcon class="w-5 h-5 text-blue-500 shrink-0" />
                                <span v-if="props.channel === 'email'" class="px-1.5 py-0.5 rounded text-[9px] font-extrabold bg-blue-600 text-white uppercase tracking-wider">
                                    Aktif
                                </span>
                            </div>
                            <div>
                                <span class="block text-xs font-black">Email SMTP</span>
                                <span class="block text-[10px] font-mono text-slate-500 dark:text-slate-400 truncate">
                                    {{ props.hasEmail ? props.maskedEmail : 'Belum diatur' }}
                                </span>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Destination Status Notice Box -->
                <div v-if="props.channel === 'whatsapp'" class="p-4 rounded-xl bg-emerald-50/80 dark:bg-emerald-950/30 border border-emerald-200/80 dark:border-emerald-900/40 text-xs space-y-1.5">
                    <div class="flex items-center gap-2 text-emerald-800 dark:text-emerald-300 font-bold">
                        <ChatBubbleLeftRightIcon class="w-4 h-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                        <span>Kode OTP Terkirim ke WhatsApp:</span>
                    </div>
                    <p class="font-mono text-sm font-black text-slate-900 dark:text-white pl-6">
                        {{ props.maskedPhone }}
                    </p>
                    <p class="text-[11px] text-slate-600 dark:text-slate-400 pl-6 leading-relaxed">
                        Buka aplikasi WhatsApp Anda untuk melihat pesan kode keamanan 6 digit.
                    </p>
                </div>

                <div v-else class="p-4 rounded-xl bg-blue-50/80 dark:bg-blue-950/30 border border-blue-200/80 dark:border-blue-900/40 text-xs space-y-1.5">
                    <div class="flex items-center gap-2 text-blue-800 dark:text-blue-300 font-bold">
                        <EnvelopeIcon class="w-4 h-4 shrink-0 text-blue-600 dark:text-blue-400" />
                        <span>Kode OTP Terkirim ke Email:</span>
                    </div>
                    <p class="font-mono text-sm font-black text-slate-900 dark:text-white pl-6">
                        {{ props.maskedEmail }}
                    </p>
                    <p class="text-[11px] text-slate-600 dark:text-slate-400 pl-6 leading-relaxed">
                        Periksa folder Kotak Masuk atau Spambox email Anda.
                    </p>
                </div>

                <!-- Error Alert -->
                <div v-if="form.errors.code" class="p-3.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/40 text-xs font-bold text-rose-700 dark:text-rose-400 flex items-center gap-2.5">
                    <ExclamationCircleIcon class="w-5 h-5 shrink-0" />
                    <span>{{ form.errors.code }}</span>
                </div>

                <!-- Success Alert -->
                <div v-if="$page.props.flash?.success" class="p-3.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900/40 text-xs font-bold text-emerald-700 dark:text-emerald-400 flex items-center gap-2.5">
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
                            <span class="text-xs font-mono font-bold" :class="remainingTime < 60 ? 'text-rose-600 dark:text-rose-400 animate-pulse' : 'text-slate-500 dark:text-slate-400'">
                                Masa Berlaku: {{ formatTime(remainingTime) }}
                            </span>
                        </div>

                        <!-- Monospace Clean Input -->
                        <div class="relative">
                            <input
                                id="otp-input"
                                type="text"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                autocomplete="one-time-code"
                                autofocus
                                maxlength="6"
                                :value="form.code"
                                @input="handleCodeInput"
                                placeholder="000000"
                                class="w-full text-center tracking-[0.75em] text-2xl font-black font-mono py-3.5 px-4 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-950 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-600 focus:border-transparent outline-none transition shadow-inner"
                            />
                        </div>

                        <p class="mt-2 text-[11px] text-slate-500 dark:text-slate-400 text-center">
                            Formulir otomatis memverifikasi begitu 6 digit selesai diketik.
                        </p>
                    </div>

                    <!-- Submit Button -->
                    <button
                        type="submit"
                        :disabled="form.code.length !== 6 || form.processing"
                        class="w-full py-3.5 px-4 rounded-xl font-bold text-sm text-white bg-blue-600 hover:bg-blue-700 active:bg-blue-800 disabled:opacity-50 disabled:cursor-not-allowed shadow-lg shadow-blue-500/20 transition-all flex items-center justify-center gap-2"
                    >
                        <ArrowPathIcon v-if="form.processing" class="w-4 h-4 animate-spin" />
                        <LockClosedIcon v-else class="w-4 h-4" />
                        <span>Verifikasi & Masuk Dashboard</span>
                    </button>
                </form>

                <!-- Resend / Alternative Channel Action -->
                <div class="pt-4 border-t border-slate-200 dark:border-slate-800 space-y-3 text-center">
                    
                    <div class="flex items-center justify-center gap-2 text-xs">
                        <span class="text-slate-500 dark:text-slate-400">Tidak menerima kode?</span>
                        <button
                            type="button"
                            @click="switchOrResendChannel(props.channel)"
                            :disabled="resendCooldown > 0 || resendForm.processing"
                            class="font-bold text-blue-600 dark:text-blue-400 hover:underline disabled:opacity-50 disabled:no-underline disabled:cursor-not-allowed"
                        >
                            <span v-if="resendCooldown > 0">Kirim Ulang ({{ resendCooldown }}d)</span>
                            <span v-else>Kirim Ulang Kode</span>
                        </button>
                    </div>

                    <!-- Quick Switch helper button -->
                    <div v-if="props.channel === 'whatsapp' && props.hasEmail">
                        <button
                            type="button"
                            @click="switchOrResendChannel('email')"
                            :disabled="resendCooldown > 0 || resendForm.processing"
                            class="text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-blue-600 dark:hover:text-blue-400 underline decoration-slate-300 dark:decoration-slate-700 transition"
                        >
                            Pilih kirim kode verifikasi melalui Email
                        </button>
                    </div>
                    <div v-else-if="props.channel === 'email' && props.hasWhatsapp">
                        <button
                            type="button"
                            @click="switchOrResendChannel('whatsapp')"
                            :disabled="resendCooldown > 0 || resendForm.processing"
                            class="text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-emerald-600 dark:hover:text-emerald-400 underline decoration-slate-300 dark:decoration-slate-700 transition"
                        >
                            Pilih kirim kode verifikasi melalui WhatsApp
                        </button>
                    </div>

                    <div class="pt-2">
                        <button
                            type="button"
                            @click="handleCancel"
                            class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-400 hover:text-rose-500 dark:hover:text-rose-400 transition"
                        >
                            <ArrowLeftOnRectangleIcon class="w-4 h-4" />
                            <span>Batalkan & Keluar ke Halaman Login</span>
                        </button>
                    </div>
                </div>

            </div>

            <!-- Security Badge Footer -->
            <div class="mt-6 flex items-center justify-center gap-2 text-[11px] font-semibold text-slate-400 dark:text-slate-500">
                <ShieldCheckIcon class="w-4 h-4 text-emerald-500" />
                <span>Protected by ROMEI Hardware & Mail Security Shield</span>
            </div>
        </div>

    </div>
</template>
