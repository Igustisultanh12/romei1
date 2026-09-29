<script setup>
import { ref } from 'vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Swal from 'sweetalert2';
import { 
    ChatBubbleBottomCenterTextIcon, 
    CheckCircleIcon, 
    XCircleIcon, 
    TrashIcon, 
    ArrowPathIcon,
    ChatBubbleLeftEllipsisIcon,
    StarIcon as StarIconSolid
} from '@heroicons/vue/24/solid';
import { 
    StarIcon as StarIconOutline,
    ExclamationTriangleIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    feedbacks: Object
});

const selectedFeedback = ref(null);
const showReplyModal = ref(false);

const replyForm = useForm({
    admin_reply: ''
});

const openReplyModal = (item) => {
    selectedFeedback.value = item;
    replyForm.admin_reply = item.admin_reply || '';
    showReplyModal.value = true;
};

const closeReplyModal = () => {
    showReplyModal.value = false;
    selectedFeedback.value = null;
    replyForm.reset();
};

const submitReply = () => {
    if (!selectedFeedback.value) return;

    replyForm.post(route('admin.feedbacks.reply', selectedFeedback.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeReplyModal();
            Swal.fire({
                title: 'Berhasil',
                text: 'Balasan resmi admin berhasil disimpan.',
                icon: 'success',
                confirmButtonColor: '#2563eb'
            });
        }
    });
};

const updateStatus = (item, newStatus) => {
    const actionLabel = newStatus === 'approved' ? 'menyetujui & menampilkan' : 'menolak';

    Swal.fire({
        title: 'Konfirmasi Status',
        text: `Apakah Anda yakin ingin ${actionLabel} ulasan dari "${item.name}"?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: newStatus === 'approved' ? '#16a34a' : '#dc2626',
        cancelButtonColor: '#64748b',
        confirmButtonText: newStatus === 'approved' ? 'Ya, Setujui' : 'Ya, Tolak',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('admin.feedbacks.update-status', item.id), {
                status: newStatus
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({
                        title: 'Berhasil',
                        text: `Status ulasan berhasil diubah menjadi ${newStatus}.`,
                        icon: 'success',
                        confirmButtonColor: '#2563eb'
                    });
                }
            });
        }
    });
};

const deleteFeedback = (item) => {
    Swal.fire({
        title: 'Hapus Ulasan',
        text: `Apakah Anda yakin ingin menghapus permanen ulasan dari "${item.name}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('admin.feedbacks.destroy', item.id), {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({
                        title: 'Terhapus',
                        text: 'Ulasan telah berhasil dihapus.',
                        icon: 'success',
                        confirmButtonColor: '#2563eb'
                    });
                }
            });
        }
    });
};
</script>

<template>
    <AdminLayout>
        <Head title="Manajemen Ulasan Pelanggan - ROMEI HQ" />

        <div class="space-y-6 max-w-7xl mx-auto py-2">
            
            <!-- Page Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 dark:border-slate-800 pb-5">
                <div>
                    <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2.5">
                        <ChatBubbleBottomCenterTextIcon class="w-7 h-7 text-blue-600 dark:text-blue-500" />
                        Manajemen Ulasan & Kepuasan Pelanggan
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        Tinjau, moderasi, setujui penayangan ulasan di halaman beranda, dan berikan balasan resmi admin.
                    </p>
                </div>

                <button 
                    @click="() => router.reload({ preserveScroll: true })" 
                    type="button" 
                    class="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 rounded-lg transition shadow-sm"
                >
                    <ArrowPathIcon class="w-4 h-4" />
                    <span>Segarkan Data</span>
                </button>
            </div>

            <!-- Table Card -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden transition-colors">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-800 text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                <th class="px-6 py-4">Pelanggan</th>
                                <th class="px-6 py-4">Rating & Layanan</th>
                                <th class="px-6 py-4">Isi Ulasan & Respon Admin</th>
                                <th class="px-6 py-4 text-center">Status</th>
                                <th class="px-6 py-4 text-right">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 text-xs text-slate-700 dark:text-slate-300">
                            <tr v-if="props.feedbacks.data.length === 0">
                                <td colspan="5" class="px-6 py-12 text-center text-slate-400 dark:text-slate-500 font-semibold">
                                    Belum ada ulasan yang dikirimkan oleh pengunjung atau pelanggan.
                                </td>
                            </tr>

                            <tr v-for="item in props.feedbacks.data" :key="item.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                                <!-- Pelanggan -->
                                <td class="px-6 py-4 align-top">
                                    <div class="font-bold text-slate-900 dark:text-white">{{ item.name }}</div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400 font-mono">{{ item.email }}</div>
                                    <span v-if="item.is_verified_customer" class="inline-block mt-1 px-2 py-0.5 rounded text-[9px] font-bold bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-900">
                                        Pelanggan Terverifikasi
                                    </span>
                                </td>

                                <!-- Rating & Layanan -->
                                <td class="px-6 py-4 align-top space-y-1">
                                    <div class="flex items-center gap-1 text-amber-400">
                                        <template v-for="star in 5" :key="star">
                                            <StarIconSolid v-if="star <= item.rating" class="w-4 h-4" />
                                            <StarIconOutline v-else class="w-4 h-4 text-slate-300 dark:text-slate-700" />
                                        </template>
                                        <span class="text-xs font-bold text-slate-700 dark:text-slate-300 ml-1">({{ item.rating }}/5)</span>
                                    </div>
                                    <div class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                                        {{ item.service_type || 'Registrasi IMEI' }}
                                    </div>
                                </td>

                                <!-- Isi Ulasan & Respon Admin -->
                                <td class="px-6 py-4 align-top max-w-md space-y-2">
                                    <p class="text-xs leading-relaxed text-slate-800 dark:text-slate-200">
                                        "{{ item.message }}"
                                    </p>
                                    
                                    <!-- Admin Reply if exists -->
                                    <div v-if="item.admin_reply" class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-[11px] space-y-0.5">
                                        <div class="font-bold text-blue-600 dark:text-blue-400 flex items-center gap-1">
                                            <ChatBubbleLeftEllipsisIcon class="w-3.5 h-3.5" />
                                            <span>Respon Resmi ROMEI HQ:</span>
                                        </div>
                                        <p class="text-slate-600 dark:text-slate-400 italic">
                                            {{ item.admin_reply }}
                                        </p>
                                    </div>
                                </td>

                                <!-- Status Badge -->
                                <td class="px-6 py-4 align-top text-center">
                                    <span v-if="item.status === 'approved'" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                                        <CheckCircleIcon class="w-3.5 h-3.5" />
                                        Ditampilkan
                                    </span>
                                    <span v-else-if="item.status === 'rejected'" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800">
                                        <XCircleIcon class="w-3.5 h-3.5" />
                                        Ditolak
                                    </span>
                                    <span v-else class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
                                        Menunggu Tinjauan
                                    </span>
                                </td>

                                <!-- Tindakan -->
                                <td class="px-6 py-4 align-top text-right space-x-1.5 whitespace-nowrap">
                                    <!-- Approve -->
                                    <button 
                                        v-if="item.status !== 'approved'"
                                        @click="updateStatus(item, 'approved')"
                                        type="button"
                                        class="p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 border border-emerald-200 dark:border-emerald-800 transition shadow-sm"
                                        title="Setujui & Tampilkan di Beranda"
                                    >
                                        <CheckCircleIcon class="w-4 h-4" />
                                    </button>

                                    <!-- Reject -->
                                    <button 
                                        v-if="item.status !== 'rejected'"
                                        @click="updateStatus(item, 'rejected')"
                                        type="button"
                                        class="p-1.5 rounded-lg bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 hover:bg-amber-100 dark:hover:bg-amber-900/60 border border-amber-200 dark:border-amber-800 transition shadow-sm"
                                        title="Tolak Ulasan"
                                    >
                                        <XCircleIcon class="w-4 h-4" />
                                    </button>

                                    <!-- Reply -->
                                    <button 
                                        @click="openReplyModal(item)"
                                        type="button"
                                        class="p-1.5 rounded-lg bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 hover:bg-blue-100 dark:hover:bg-blue-900/60 border border-blue-200 dark:border-blue-800 transition shadow-sm"
                                        title="Tulis / Edit Balasan Admin"
                                    >
                                        <ChatBubbleLeftEllipsisIcon class="w-4 h-4" />
                                    </button>

                                    <!-- Delete -->
                                    <button 
                                        @click="deleteFeedback(item)"
                                        type="button"
                                        class="p-1.5 rounded-lg bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 hover:bg-rose-100 dark:hover:bg-rose-900/60 border border-rose-200 dark:border-rose-800 transition shadow-sm"
                                        title="Hapus Ulasan Permanen"
                                    >
                                        <TrashIcon class="w-4 h-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="props.feedbacks.links && props.feedbacks.links.length > 3" class="px-6 py-4 bg-slate-50 dark:bg-slate-900/50 border-t border-slate-200 dark:border-slate-800 flex justify-center gap-1">
                    <component 
                        :is="link.url ? 'Link' : 'span'" 
                        v-for="(link, k) in props.feedbacks.links" 
                        :key="k"
                        :href="link.url"
                        v-html="link.label"
                        :class="[
                            'px-3 py-1.5 rounded-lg text-xs font-bold border transition-all',
                            link.active 
                                ? 'bg-blue-600 border-blue-500 text-white shadow-sm' 
                                : link.url 
                                    ? 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-700' 
                                    : 'bg-slate-100 border-slate-200 text-slate-400 dark:bg-slate-800/40 dark:border-slate-800 dark:text-slate-600 cursor-not-allowed'
                        ]"
                    />
                </div>
            </div>

            <!-- Modal Balasan Admin -->
            <div v-if="showReplyModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm transition-opacity" @click="closeReplyModal"></div>

                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl w-full max-w-lg p-6 relative z-10 space-y-4 shadow-2xl">
                    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                        <div class="flex items-center gap-2">
                            <ChatBubbleLeftEllipsisIcon class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                            <h3 class="text-base font-bold text-slate-900 dark:text-white">
                                Balas Ulasan: {{ selectedFeedback?.name }}
                            </h3>
                        </div>
                    </div>

                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs space-y-1">
                        <span class="font-bold text-slate-500 uppercase tracking-wider text-[10px]">Pesan Pelanggan:</span>
                        <p class="text-slate-800 dark:text-slate-200 italic">
                            "{{ selectedFeedback?.message }}"
                        </p>
                    </div>

                    <form @submit.prevent="submitReply" class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">
                                Balasan Resmi Tim Administrator
                            </label>
                            <textarea 
                                v-model="replyForm.admin_reply" 
                                rows="4" 
                                required
                                placeholder="Tuliskan respon resmi yang ramah dan solutif..." 
                                class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                            ></textarea>
                            <div v-if="replyForm.errors.admin_reply" class="text-xs text-rose-500 font-semibold mt-1">
                                {{ replyForm.errors.admin_reply }}
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-200 dark:border-slate-800">
                            <button 
                                type="button" 
                                @click="closeReplyModal" 
                                class="px-4 py-2 text-xs font-bold text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition"
                            >
                                Batal
                            </button>
                            <button 
                                type="submit" 
                                :disabled="replyForm.processing"
                                class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 active:bg-blue-800 transition shadow-md disabled:opacity-50"
                            >
                                Simpan Balasan
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </AdminLayout>
</template>
