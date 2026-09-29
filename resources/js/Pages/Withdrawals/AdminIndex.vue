<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Swal from 'sweetalert2';
import { 
    BanknotesIcon, 
    CheckCircleIcon, 
    XCircleIcon, 
    ClockIcon, 
    MagnifyingGlassIcon,
    ArrowPathIcon,
    UserIcon,
    BuildingLibraryIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    withdrawals: {
        type: Array,
        default: () => []
    }
});

const searchQuery = ref('');
const statusFilter = ref('all');

const formatRupiah = (val) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(Number(val) || 0);
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short'
    }).format(date);
};

const totalPending = computed(() => {
    return props.withdrawals.filter(w => w.status === 'pending').length;
});

const totalPaid = computed(() => {
    return props.withdrawals.filter(w => w.status === 'paid').length;
});

const totalRejected = computed(() => {
    return props.withdrawals.filter(w => w.status === 'rejected').length;
});

const filteredWithdrawals = computed(() => {
    return props.withdrawals.filter(item => {
        const matchesStatus = statusFilter.value === 'all' || item.status === statusFilter.value;
        const q = searchQuery.value.toLowerCase().trim();
        const userName = item.user?.name?.toLowerCase() || '';
        const userEmail = item.user?.email?.toLowerCase() || '';
        const bank = item.bank_name?.toLowerCase() || '';
        const accNumber = item.account_number?.toLowerCase() || '';
        const accName = item.account_name?.toLowerCase() || '';

        const matchesQuery = !q || 
            userName.includes(q) || 
            userEmail.includes(q) || 
            bank.includes(q) || 
            accNumber.includes(q) || 
            accName.includes(q);

        return matchesStatus && matchesQuery;
    });
});

const approveWithdrawal = (item) => {
    Swal.fire({
        title: 'Konfirmasi Persetujuan',
        text: `Apakah Anda yakin ingin menyetujui penarikan ${formatRupiah(item.amount)} untuk ${item.account_name} (${item.bank_name})?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#16a34a',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Ya, Setujui & Cairkan',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Memproses Penarikan',
                text: 'Sedang menghubungi gateway pencairan...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            router.post(route('admin.withdrawals.approve', item.id), {}, {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({
                        title: 'Berhasil',
                        text: 'Penarikan dana telah disetujui dan diproses.',
                        icon: 'success',
                        confirmButtonColor: '#2563eb'
                    });
                },
                onError: (errors) => {
                    Swal.fire({
                        title: 'Gagal Memproses',
                        text: errors.message || 'Terjadi kesalahan sistem saat memproses pencairan.',
                        icon: 'error',
                        confirmButtonColor: '#ef4444'
                    });
                }
            });
        }
    });
};

const rejectWithdrawal = (item) => {
    Swal.fire({
        title: 'Penolakan Penarikan',
        text: `Masukkan alasan penolakan penarikan ${formatRupiah(item.amount)}:`,
        input: 'text',
        inputPlaceholder: 'Contoh: Rekening tujuan tidak valid atau nomor salah',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Tolak & Kembalikan Saldo',
        cancelButtonText: 'Batal',
        inputValidator: (value) => {
            if (!value || value.trim().length === 0) {
                return 'Alasan penolakan wajib diisi.';
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Memproses Penolakan',
                text: 'Sedang mengembalikan dana ke saldo pengguna...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            router.post(route('admin.withdrawals.reject', item.id), {
                rejection_reason: result.value
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({
                        title: 'Penarikan Ditolak',
                        text: 'Pengajuan telah ditolak dan saldo telah dikembalikan ke dompet pengguna.',
                        icon: 'info',
                        confirmButtonColor: '#2563eb'
                    });
                },
                onError: (errors) => {
                    Swal.fire({
                        title: 'Gagal',
                        text: errors.message || 'Gagal memproses penolakan.',
                        icon: 'error',
                        confirmButtonColor: '#ef4444'
                    });
                }
            });
        }
    });
};
</script>

<template>
    <Head title="Manajemen Penarikan Saldo" />
    <AdminLayout>
        <div class="max-w-7xl mx-auto space-y-6">
            
            <!-- Header Section -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">Manajemen Penarikan Saldo</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Daftar pengajuan penarikan dana dompet pelanggan, verifikasi rekening, dan pencairan otomatis.</p>
                </div>
            </div>

            <!-- Metric Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm transition-colors">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Total Pengajuan</span>
                            <div class="text-2xl font-black text-slate-900 dark:text-white mt-1">{{ withdrawals.length }}</div>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                            <BanknotesIcon class="w-5 h-5" />
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm transition-colors">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Menunggu</span>
                            <div class="text-2xl font-black text-amber-600 dark:text-amber-400 mt-1">{{ totalPending }}</div>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                            <ClockIcon class="w-5 h-5" />
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm transition-colors">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Berhasil Cair</span>
                            <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1">{{ totalPaid }}</div>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                            <CheckCircleIcon class="w-5 h-5" />
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm transition-colors">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Ditolak / Refund</span>
                            <div class="text-2xl font-black text-rose-600 dark:text-rose-400 mt-1">{{ totalRejected }}</div>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-rose-50 dark:bg-rose-900/30 text-rose-600 dark:text-rose-400 flex items-center justify-center">
                            <XCircleIcon class="w-5 h-5" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters & Search -->
            <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-3 sm:space-y-0 sm:flex sm:items-center sm:justify-between gap-4 transition-colors">
                <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-800/80 p-1 rounded-xl">
                    <button 
                        @click="statusFilter = 'all'" 
                        :class="statusFilter === 'all' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                        class="px-3.5 py-1.5 text-xs font-bold rounded-lg transition"
                    >
                        Semua
                    </button>
                    <button 
                        @click="statusFilter = 'pending'" 
                        :class="statusFilter === 'pending' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                        class="px-3.5 py-1.5 text-xs font-bold rounded-lg transition"
                    >
                        Menunggu ({{ totalPending }})
                    </button>
                    <button 
                        @click="statusFilter = 'paid'" 
                        :class="statusFilter === 'paid' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                        class="px-3.5 py-1.5 text-xs font-bold rounded-lg transition"
                    >
                        Berhasil ({{ totalPaid }})
                    </button>
                    <button 
                        @click="statusFilter = 'rejected'" 
                        :class="statusFilter === 'rejected' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                        class="px-3.5 py-1.5 text-xs font-bold rounded-lg transition"
                    >
                        Ditolak ({{ totalRejected }})
                    </button>
                </div>

                <div class="relative w-full sm:w-72">
                    <MagnifyingGlassIcon class="w-4 h-4 text-slate-400 dark:text-slate-500 absolute left-3 top-3 pointer-events-none" />
                    <input 
                        type="text" 
                        v-model="searchQuery" 
                        placeholder="Cari user, rekening, bank..." 
                        class="w-full pl-9 pr-4 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs placeholder-slate-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition"
                    />
                </div>
            </div>

            <!-- Table of Withdrawals -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden transition-colors">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/75 dark:bg-slate-800/40 text-slate-500 dark:text-slate-400 uppercase font-bold tracking-wider text-[11px]">
                                <th class="py-3.5 px-4">Pengguna</th>
                                <th class="py-3.5 px-4">Nominal</th>
                                <th class="py-3.5 px-4">Rekening Tujuan</th>
                                <th class="py-3.5 px-4">Tanggal Pengajuan</th>
                                <th class="py-3.5 px-4">Status</th>
                                <th class="py-3.5 px-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                            <tr v-for="item in filteredWithdrawals" :key="item.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-slate-900 dark:text-white">{{ item.user?.name || 'Pelanggan #' + item.user_id }}</div>
                                    <div class="text-[11px] text-slate-400 dark:text-slate-500 font-mono">{{ item.user?.email || '-' }}</div>
                                </td>
                                <td class="py-3.5 px-4 font-mono font-bold text-slate-900 dark:text-white text-sm">
                                    {{ formatRupiah(item.amount) }}
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                                        <BuildingLibraryIcon class="w-4 h-4 text-slate-400" />
                                        <span>{{ item.bank_name || 'Bank' }}</span>
                                    </div>
                                    <div class="font-mono text-slate-600 dark:text-slate-400 text-[11px]">
                                        {{ item.account_number }}
                                    </div>
                                    <div class="text-slate-400 dark:text-slate-500 text-[11px]">
                                        a.n. {{ item.account_name || '-' }}
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 text-slate-500 dark:text-slate-400 text-[11px]">
                                    {{ formatDate(item.created_at) }}
                                </td>
                                <td class="py-3.5 px-4">
                                    <span v-if="item.status === 'pending'" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
                                        <ClockIcon class="w-3.5 h-3.5" />
                                        <span>Menunggu</span>
                                    </span>
                                    <span v-else-if="item.status === 'paid'" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                                        <CheckCircleIcon class="w-3.5 h-3.5" />
                                        <span>Berhasil Cair</span>
                                    </span>
                                    <span v-else class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800">
                                        <XCircleIcon class="w-3.5 h-3.5" />
                                        <span>Ditolak</span>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    <div v-if="item.status === 'pending'" class="flex items-center justify-end gap-2">
                                        <button 
                                            @click="approveWithdrawal(item)" 
                                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition shadow-sm"
                                        >
                                            <CheckCircleIcon class="w-3.5 h-3.5" />
                                            <span>Setujui</span>
                                        </button>
                                        <button 
                                            @click="rejectWithdrawal(item)" 
                                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs transition shadow-sm"
                                        >
                                            <XCircleIcon class="w-3.5 h-3.5" />
                                            <span>Tolak</span>
                                        </button>
                                    </div>
                                    <div v-else class="text-[11px] text-slate-400 dark:text-slate-500 italic">
                                        Selesai
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="filteredWithdrawals.length === 0">
                                <td colspan="6" class="py-12 text-center text-slate-400 dark:text-slate-500">
                                    <BanknotesIcon class="w-10 h-10 mx-auto text-slate-300 dark:text-slate-700 mb-2" />
                                    <p class="font-medium text-xs">Tidak ada data penarikan yang cocok dengan kriteria filter.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </AdminLayout>
</template>
