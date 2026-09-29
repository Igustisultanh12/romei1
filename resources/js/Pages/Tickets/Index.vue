<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';
import UserLayout from '@/Layouts/UserLayout.vue'; 
import Swal from 'sweetalert2'; // Mengimport SweetAlert2 Core

const props = defineProps({
    tickets: Array
});

const categories = [
    'Permohonan Tarik Saldo',
    'Jaringan tidak muncul setelah berhasil daftar roamer',
    'Jaringan hilang belum sampai 3 bulan',
    'Keluhan lainnya'
];

const form = useForm({
    category: '',
    withdraw_amount: '',
    description: ''
});

// =========================================================================
// WATCHER CERDAS: Tangkap Error Validasi Backend & Tampilkan via SweetAlert
// =========================================================================
watch(() => form.errors, (newErrors) => {
    if (Object.keys(newErrors).length > 0) {
        // Otomatis tutup loading state jika backend memuntahkan error
        Swal.close();

        // Mengambil pesan error pertama yang dikembalikan sistem
        const firstErrorKey = Object.keys(newErrors)[0];
        let errorMessage = newErrors[firstErrorKey];

        // Menerjemahkan pesan error bawaan Laravel agar ramah dibaca user
        if (errorMessage.includes('at least 10 characters')) {
            errorMessage = 'Isi rincian detail keluhan Anda terlalu pendek! Harap tulis minimal 10 karakter.';
        } else if (errorMessage.includes('at least 10000')) {
            errorMessage = 'Nominal batas minimum permohonan penarikan saldo adalah Rp 10.000.';
        } else if (errorMessage.includes('required')) {
            errorMessage = 'Kolom parameter ini wajib diisi, tidak boleh dikosongkan.';
        }

        Swal.fire({
            icon: 'warning',
            title: 'Validasi Form Gagal',
            text: errorMessage,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 4500,
            timerProgressBar: true,
            background: '#ffffff',
            color: '#0f172a',
            iconColor: '#f59e0b',
        });
    }
}, { deep: true });

const isWithdraw = computed(() => form.category === 'Permohonan Tarik Saldo');

const submitTicket = () => {
    // Jalankan animasi loading sweetalert sesaat sebelum request terkirim
    Swal.fire({
        title: 'Mengirim Laporan',
        text: 'Sedang mendaftarkan tiket Anda ke antrean ROMEI HQ...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    form.post(route('services.tickets.store'), {
        onSuccess: () => {
            form.reset();
            // Tampilkan alert box sukses saat data berhasil menembus database
            Swal.fire({
                icon: 'success',
                title: 'Tiket Resmi Diterbitkan',
                text: 'Laporan aduan kendala Anda telah berhasil disimpan dan masuk antrean Customer Service.',
                confirmButtonColor: '#2563eb',
                background: '#ffffff',
                color: '#0f172a',
            });
        },
        onError: () => {
            // Ditutup otomatis secara reaktif oleh watcher form.errors di atas
        }
    });
};
</script>

<template>
    <UserLayout>
        <Head title="Ticket Bantuan ROMEI" />
        <div class="max-w-6xl mx-auto p-6 space-y-6">
            
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 shadow-sm">
                <h2 class="text-xl font-black text-slate-950 dark:text-white tracking-tight">Buat Tiket Laporan Baru</h2>
                <p class="text-xs text-slate-400 mt-0.5">Sampaikan kendala jaringan atau ajukan penarikan dana kuota secara riil.</p>
                
                <form @submit.prevent="submitTicket" class="mt-6 space-y-4 max-w-2xl">
                    <div class="flex flex-col space-y-1">
                        <label class="text-xs font-bold text-slate-400">Pilih Kategori Kendala / Permohonan</label>
                        <select v-model="form.category" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-xs px-3 py-2.5 text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-blue-500">
                            <option value="" disabled>-- Pilih Opsi Keluhan --</option>
                            <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                        </select>
                        <span v-if="form.errors.category" class="text-[11px] text-red-500 font-bold font-mono mt-0.5">{{ form.errors.category }}</span>
                    </div>

                    <div v-if="isWithdraw" class="flex flex-col space-y-1 transition-all duration-300">
                        <label class="text-xs font-bold text-slate-400">Jumlah Dana Nominal yang Ditarik (IDR)</label>
                        <input v-model="form.withdraw_amount" type="number" placeholder="Contoh: 50000" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-xs font-bold px-3 py-2.5 text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-blue-500" />
                        <span v-if="form.errors.withdraw_amount" class="text-[11px] text-red-500 font-bold font-mono mt-0.5">{{ form.errors.withdraw_amount }}</span>
                    </div>

                    <div class="flex flex-col space-y-1">
                        <label class="text-xs font-bold text-slate-400">
                            {{ isWithdraw ? 'Keterangan Alasan / Detail Rekening Tujuan & Nama Bank' : 'Keterangan Kronologi Keluhan Jaringan' }}
                        </label>
                        <textarea v-model="form.description" rows="4" placeholder="Tulis rincian informasi di sini secara detail..." class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-xs px-3 py-2.5 text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-blue-500"></textarea>
                        <span v-if="form.errors.description" class="text-[11px] text-red-500 font-bold font-mono mt-0.5">{{ form.errors.description }}</span>
                    </div>

                    <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-slate-300 disabled:text-slate-500 text-white rounded-lg text-xs font-bold transition-all shadow-md">
                        {{ form.processing ? 'Mengirim Aduan...' : 'Submit Laporan Resmi' }}
                    </button>
                </form>
            </div>

            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 shadow-sm">
                <h3 class="text-base font-bold text-slate-950 dark:text-white tracking-tight mb-4">Riwayat Tiket Bantuan Anda</h3>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse font-mono text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-400 uppercase tracking-wider">
                                <th class="py-3 px-2">No. Tiket</th>
                                <th class="py-3 px-2">Kategori Masalah</th>
                                <th class="py-3 px-2">Status</th>
                                <th class="py-3 px-2">Balasan Tanggapan Admin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="ticket in tickets" :key="ticket.id" class="border-b border-slate-100 dark:border-slate-800/50 hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                                <td class="py-4 px-2 font-bold text-blue-600">{{ ticket.ticket_number }}</td>
                                
                                <td class="py-4 px-2 font-sans text-slate-700 dark:text-slate-300">
                                    <span class="font-bold block text-slate-900 dark:text-slate-100">{{ ticket.subject }}</span>
                                    <p class="text-[11px] text-slate-400 mt-1 truncate max-w-xs italic whitespace-pre-line">"{{ ticket.description }}"</p>
                                </td>
                                
                                <td class="py-4 px-2">
                                    <span :class="{
                                        'bg-blue-500/10 text-blue-500 ring-blue-500/20': ticket.status === 'OPEN',
                                        'bg-amber-500/10 text-amber-500 ring-amber-500/20': ticket.status === 'IN_PROGRESS',
                                        'bg-emerald-500/10 text-emerald-500 ring-emerald-500/20': ticket.status === 'RESOLVED',
                                        'bg-slate-500/10 text-slate-500 ring-slate-500/20': ticket.status === 'CLOSED',
                                    }" class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold ring-1 ring-inset">
                                        {{ ticket.status }}
                                    </span>
                                </td>
                                
                                <td class="py-4 px-2 font-sans text-slate-600 dark:text-slate-400 max-w-xs">
                                    <div v-if="ticket.admin_reply" class="text-xs">
                                        <p class="font-semibold text-slate-900 dark:text-white mb-0.5">Tanggapan Operator:</p>
                                        <p class="italic text-slate-500">"{{ ticket.admin_reply }}"</p>
                                    </div>
                                    <span v-else class="text-slate-400 italic">Menunggu validasi CS...</span>
                                </td>
                            </tr>
                            
                            <tr v-if="tickets.length === 0">
                                <td colspan="4" class="text-center py-8 text-slate-400 font-sans italic">Belum ada riwayat tiket aduan yang Anda buat.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </UserLayout>
</template>

<style>
/* Kostumisasi gaya visual SweetAlert2 agar menyatu dengan rancangan ROMEI */
.swal2-popup {
    font-family: ui-sans-serif, system-ui, sans-serif !important;
    border-radius: 1.25rem !important;
}
</style>