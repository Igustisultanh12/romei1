<script setup>
import { ref } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    tickets: Array
});

const selectedTicket = ref(null);

const replyForm = useForm({
    status: '',
    admin_reply: ''
});

const openReplyModal = (ticket) => {
    selectedTicket.value = ticket;
    replyForm.status = ticket.status;
    replyForm.admin_reply = ticket.admin_reply || '';
};

const submitReply = () => {
    replyForm.post(route('admin.tickets.reply', selectedTicket.value.id), {
        onSuccess: () => {
            selectedTicket.value = null;
            replyForm.reset();
        }
    });
};
</script>

<template>
    <AdminLayout>
        <Head title="Admin Management Tiket" />
        <div class="max-w-7xl mx-auto p-6 space-y-6">
            
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 shadow-sm">
                <h2 class="text-xl font-black text-slate-900 dark:text-white tracking-tight">Pusat Resolusi Tiket ROMEI HQ</h2>
                <p class="text-xs text-slate-400 mt-0.5">Pantau, proses, dan eksekusi pengajuan penarikan dana serta masalah jaringan pengguna.</p>

                <div class="overflow-x-auto mt-6">
                    <table class="w-full text-left border-collapse font-mono text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-400 uppercase tracking-wider">
                                <th class="py-3 px-2">Pelanggan</th>
                                <th class="py-3 px-2">No. Tiket</th>
                                <th class="py-3 px-2">Kategori & Detail Keluhan</th>
                                <th class="py-3 px-2">Status</th>
                                <th class="py-3 px-2 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="ticket in tickets" :key="ticket.id" class="border-b border-slate-100 dark:border-slate-800/50 hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                                <td class="py-4 px-2 font-sans">
                                    <div class="font-bold text-slate-900 dark:text-white">{{ ticket.user?.name }}</div>
                                    <div class="text-[10px] text-slate-400 font-mono">{{ ticket.user?.email }}</div>
                                </td>
                                <td class="py-4 px-2 font-bold text-slate-700 dark:text-slate-400">{{ ticket.ticket_number }}</td>
                                <td class="py-4 px-2 font-sans max-w-sm">
                                    <span class="font-bold text-slate-800 dark:text-slate-200 block">{{ ticket.category }}</span>
                                    <span v-if="ticket.withdraw_amount" class="inline-block bg-emerald-500/10 text-emerald-500 font-black rounded text-[11px] font-mono px-1.5 py-0.5 mt-1">Tarik Dana: Rp {{ Number(ticket.withdraw_amount).toLocaleString('id-ID') }}</span>
                                    <p class="text-xs text-slate-400 mt-1 italic">"{{ ticket.description }}"</p>
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
                                <td class="py-4 px-2 text-right">
                                    <button @click="openReplyModal(ticket)" class="px-2.5 py-1 bg-slate-100 hover:bg-blue-600 dark:bg-slate-800 dark:hover:bg-blue-600 text-slate-700 dark:text-slate-200 hover:text-white rounded font-sans text-xs font-bold transition-all">
                                        Proses / Balas
                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="selectedTicket" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm" @click="selectedTicket = null"></div>
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl w-full max-w-lg p-6 relative z-10 space-y-4">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Kelola Tiket: {{ selectedTicket.ticket_number }}</h3>
                    
                    <form @submit.prevent="submitReply" class="space-y-4">
                        <div class="flex flex-col space-y-1">
                            <label class="text-xs font-bold text-slate-400">Update Status Operasional</label>
                            <select v-model="replyForm.status" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-xs px-3 py-2 text-slate-900 dark:text-white">
                                <option value="OPEN">OPEN (Menunggu Antrean)</option>
                                <option value="IN_PROGRESS">IN_PROGRESS (Sedang Diteliti)</option>
                                <option value="RESOLVED">RESOLVED (Berhasil Diselesaikan)</option>
                                <option value="CLOSED">CLOSED (Tiket Ditutup Permanen)</option>
                            </select>
                        </div>

                        <div class="flex flex-col space-y-1">
                            <label class="text-xs font-bold text-slate-400">Pesan Konfirmasi Admin Resmi</label>
                            <textarea v-model="replyForm.admin_reply" rows="4" placeholder="Tuliskan respon validasi atau info transfer dana di sini..." class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-xs px-3 py-2 text-slate-900 dark:text-white"></textarea>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2">
                            <button type="button" @click="selectedTicket = null" class="px-3 py-1.5 text-slate-400 text-xs font-bold">Batal</button>
                            <button type="submit" :disabled="replyForm.processing" class="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold transition-all shadow-md">
                                Eksekusi Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </AdminLayout>
</template>