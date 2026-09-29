<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { 
    CpuChipIcon, CheckCircleIcon, ShieldCheckIcon, ClockIcon, ArrowPathIcon, 
    ChatBubbleLeftRightIcon, LockClosedIcon, DocumentTextIcon, ComputerDesktopIcon, 
    DevicePhoneMobileIcon, ChevronDownIcon, Bars3Icon, XMarkIcon, GlobeAltIcon
} from '@heroicons/vue/24/outline';
import { StarIcon as StarSolid } from '@heroicons/vue/24/solid';

const props = defineProps({
    approved_feedbacks: Array,
    stats: Object,
    settings: Object // Berfungsi menangkap konfigurasi dinamis termasuk beranda_image_url dari backend
});

const isScrolled = ref(false);
const isMobileMenuOpen = ref(false);
const activeFaq = ref(null);
const currentSlide = ref(0);
let slideInterval = null;

// =========================================================
// LOGIKA SLIDER OTOMATIS + MANUAL UNTUK SECTION LAYANAN KAMI
// =========================================================
const currentServiceSlide = ref(0);
let serviceInterval = null;

const services = [
    {
        title: 'CEK IMEI',
        description: 'Mengetahui status IMEI perangkat secara cepat dan akurat langsung dari kluster data.',
        icon: DevicePhoneMobileIcon,
        features: [
            'Status Terdaftar (Registered)',
            'Status Roamer / Sementara',
            'Pengecekan Status Blacklist',
            'Informasi Manufaktur Device'
        ],
        btnText: 'Cek Sekarang'
    },
    {
        title: 'CEK HISTORY CEIR',
        description: 'Menampilkan riwayat manifes perangkat genggam Anda pada sistem database CEIR.',
        icon: CpuChipIcon,
        features: [
            'Riwayat Log Registrasi',
            'Transparansi Perubahan Status',
            'Aktivitas Frekuensi Device',
            'Informasi Validitas CEIR'
        ],
        btnText: 'Cek Sekarang'
    },
    {
        title: 'DAFTAR ROAMER 3 BULAN',
        description: 'Registrasi IMEI sementara untuk perangkat internasional yang digunakan di Indonesia.',
        icon: GlobeAltIcon,
        features: [
            'Pengajuan Registrasi Online Full',
            'Verifikasi Dokumen & Device Kilat',
            'Monitoring Status Realtime',
            'Tracking Proses End-to-End'
        ],
        btnText: 'Daftar Sekarang'
    }
];

const nextService = () => {
    currentServiceSlide.value = (currentServiceSlide.value + 1) % services.length;
};

const prevService = () => {
    currentServiceSlide.value = (currentServiceSlide.value - 1 + services.length) % services.length;
};

const startServiceSlide = () => { serviceInterval = setInterval(nextService, 4000); }; // Otomatis bergeser tiap 4 detik
const stopServiceSlide = () => { if (serviceInterval) clearInterval(serviceInterval); };
// =========================================================

// Dummy data internal untuk keunggulan dan FAQ agar loops pembungkus ter-render sempurna
const highlights = [
    { title: 'Enkripsi Data Aman', icon: ShieldCheckIcon },
    { title: 'Pemrosesan Instant', icon: ArrowPathIcon },
    { title: 'Validitas Terjamin', icon: CheckCircleIcon },
    { title: 'Dukungan 24/7', icon: ChatBubbleLeftRightIcon }
];

const faqs = [
    { q: 'Berapa lama proses verifikasi IMEI di ROMEI?', a: 'Proses pengecekan status berjalan secara realtime , sedangkan untuk pendaftaran paket Roemer akan berjalan  1x24 Jam tergantung dengan banyaknya antrian' },
    { q: 'Apakah semua smartphone inter bisa didaftarkan ?', a: 'Ya, Selama device tersebut bukan sim-lock maka bisa didaftarkan. maka dari itu sebelum melakukan daftar roamer disarankan untuk cek IMEI terlebih dahulu' }
];

// Form submit reaktif menggunakan Inertia Form Helper
const feedbackForm = useForm({
    name: '',
    email: '',
    service_type: 'Cek IMEI',
    rating: 5,
    message: ''
});

// Deteksi efek gulir layar
const handleScroll = () => { isScrolled.value = window.scrollY > 20; };

// Manajemen State Carousel Feedback Otomatis
const nextSlide = () => {
    if (props.approved_feedbacks && props.approved_feedbacks.length > 0) {
        currentSlide.value = (currentSlide.value + 1) % props.approved_feedbacks.length;
    }
};

const prevSlide = () => {
    if (props.approved_feedbacks && props.approved_feedbacks.length > 0) {
        currentSlide.value = (currentSlide.value - 1 + props.approved_feedbacks.length) % props.approved_feedbacks.length;
    }
};

const startSlide = () => { slideInterval = setInterval(nextSlide, 5000); };
const stopSlide = () => { if (slideInterval) clearInterval(serviceInterval); };

onMounted(() => {
    window.addEventListener('scroll', handleScroll);
    startSlide();
    startServiceSlide(); // Jalankan auto-play carousel layanan saat halaman dimuat
});

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
    stopSlide();
    stopServiceSlide(); // Bersihkan interval agar memori tidak bocor
});

const submitFeedback = () => {
    feedbackForm.post(route('feedbacks.store'), {
        preserveScroll: true,
        onSuccess: () => {
            feedbackForm.reset();
            alert('Sukses mengirimkan ulasan! Feedback Anda akan muncul setelah disetujui admin.');
        }
    });
};
</script>

<template>
    <Head title="ROMEI Hub - Premium IMEI Registration Platform" />

    <!-- WARNA LOCK PUTIH BERSIH (Aman dari kendala Mode Gelap paksa OS/Browser) -->
    <div class="min-h-screen bg-white text-slate-900 font-sans antialiased selection:bg-blue-600 selection:text-white transition-colors duration-300">
        
        <!-- Navigasi dengan Efek Gulir Dinamis (Blur Transparan & Border muncul saat di-scroll) -->
        <nav :class="[isScrolled ? 'bg-white/80 backdrop-blur-md border-b border-slate-200/60 py-3 shadow-sm' : 'bg-transparent py-6']" class="fixed top-0 left-0 w-full z-50 transition-all duration-300">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between">
                <div class="flex flex-col">
                    <div class="flex items-center gap-2">
                        <CpuChipIcon class="w-6 h-6 text-blue-600" />
                        <span class="text-xl font-black tracking-tight text-slate-900">ROMEI<span class="text-blue-600">.</span></span>
                    </div>
                    <span class="hidden lg:block text-[9px] text-slate-400 font-bold uppercase tracking-wider mt-0.5">Trusted IMEI Registration & Verification Platform</span>
                </div>

                <div class="hidden md:flex items-center gap-8 font-semibold text-xs text-slate-600">
                    <a href="#beranda" class="hover:text-blue-600 transition-colors">Beranda</a>
                    <a href="#layanan" class="hover:text-blue-600 transition-colors">Layanan Kami</a>
                    <a href="#keunggulan" class="hover:text-blue-600 transition-colors">Keunggulan</a>
                    <a href="#alur" class="hover:text-blue-600 transition-colors">Alur Penggunaan</a>
                    <a href="#feedback" class="hover:text-blue-600 transition-colors">Testimonial</a>
                </div>

                <div class="hidden md:flex items-center gap-3">
                    <a :href="route('login')" class="text-xs font-bold text-slate-700 px-4 py-2 hover:bg-slate-100 rounded-xl transition-all">Masuk</a>
                    <a :href="route('register')" class="text-xs font-bold bg-slate-950 text-white px-5 py-2.5 rounded-xl shadow-sm hover:opacity-90 transition-all">Daftar Sekarang</a>
                </div>

                <button @click="isMobileMenuOpen = !isMobileMenuOpen" class="md:hidden text-slate-600">
                    <Bars3Icon v-if="!isMobileMenuOpen" class="w-6 h-6" />
                    <XMarkIcon v-else class="w-6 h-6" />
                </button>
            </div>
        </nav>

        <section id="beranda" class="relative pt-36 pb-24 overflow-hidden lg:pt-48 lg:pb-36">
            <div class="absolute inset-0 pointer-events-none opacity-20">
                <div class="absolute top-[-10%] left-[-15%] w-[60vw] h-[60vw] rounded-full bg-blue-400 blur-[130px]"></div>
                <div class="absolute bottom-[5%] right-[-10%] w-[50vw] h-[50vw] rounded-full bg-indigo-300 blur-[120px]"></div>
            </div>

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-16 items-center">
                    <div class="lg:col-span-6 space-y-6 text-center lg:text-left">
                        <h1 class="text-5xl sm:text-6xl font-black text-slate-950 tracking-tight leading-[1.05]">ROMEI<span class="text-blue-600">.</span></h1>
                        <h2 class="text-lg font-extrabold text-slate-800 tracking-tight leading-snug">Platform Registrasi IMEI dan Verifikasi Perangkat Internasional yang Aman, Cepat, dan Terpercaya.</h2>
                        <p class="text-xs sm:text-sm text-slate-400 leading-relaxed max-w-xl mx-auto lg:mx-0 font-medium">ROMEI membantu pengguna melakukan pengecekan status IMEI, pengecekan riwayat Kemenperin/CEIR, serta pendaftaran paket Roamer IMEI 3 Bulan secara online dengan proses yang mudah, transparan, dan terproteksi enkripsi perbankan.</p>
                        
                        <div class="flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-4 pt-2">
                            <a :href="route('register')" class="w-full sm:w-auto px-8 py-3.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md transition-all text-center">Mulai Sekarang →</a>
                            <a href="#layanan" class="w-full sm:w-auto px-8 py-3.5 bg-slate-50 border border-slate-200 hover:bg-slate-100 text-slate-700 rounded-xl text-xs font-bold shadow-sm transition-all text-center">Pelajari Lebih Lanjut</a>
                        </div>
                    </div>

                    <div class="lg:col-span-6 flex justify-center relative">
                        <!-- MENANGKAP GAMBAR DENGAN SEPURNA DARI SYMLINK STORAGE -->
                        <div class="w-full max-w-[420px] aspect-square animate-float flex items-center justify-center">
                            <img :src="props.settings?.beranda_image_url || '/storage/gambar/carton.png'" 
                                 alt="Visual Beranda ROMEI" 
                                 class="w-full h-full object-contain drop-shadow-xl" />
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="border-y border-slate-200/60 bg-slate-50/50 py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-2 lg:grid-cols-4 gap-8 text-center">
                <div class="space-y-1">
                    <span class="text-3xl font-black text-slate-950 font-mono tracking-tight block">{{ props.stats?.total_imei_checks?.toLocaleString('id-ID') || '142,500' }}+</span>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block">Total Pengecekan IMEI</span>
                </div>
                <div class="space-y-1">
                    <span class="text-3xl font-black text-slate-950 font-mono tracking-tight block">{{ props.stats?.total_ceir_checks?.toLocaleString('id-ID') || '98,340' }}+</span>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block">Total Cek History CEIR</span>
                </div>
                <div class="space-y-1">
                    <span class="text-3xl font-black text-slate-950 font-mono tracking-tight block">{{ props.stats?.total_roamer_registrations?.toLocaleString('id-ID') || '34,210' }}+</span>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block">Total Registrasi Roamer</span>
                </div>
                <div class="space-y-1">
                    <span class="text-3xl font-black text-slate-950 font-mono tracking-tight block">{{ props.stats?.total_customers?.toLocaleString('id-ID') || '12,880' }}+</span>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block">Total Pelanggan Aktif</span>
                </div>
            </div>
        </section>

        <!-- =================================================================================== -->
        <!-- SECTION LAYANAN UTAMA (KINI MENJADI CAROUSEL GERAK BERGANTIAN + TOMBOL GULIR MANUAL) -->
        <!-- =================================================================================== -->
        <section id="layanan" class="py-24 overflow-hidden">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">
                
                <!-- Baris Judul Atas (Terpusat Sempurna di Tengah Halaman) -->
                <div class="flex flex-col items-center justify-center text-center gap-4 border-b border-slate-100 pb-6">
                    <div class="space-y-1">
                        <h2 class="text-xs font-bold text-blue-600 uppercase tracking-widest">Layanan Kami</h2>
                        <h3 class="text-3xl font-black text-slate-950 tracking-tight">Solusi untuk IMEI anda</h3>
                    </div>
                    
                    <!-- Tombol Navigasi Manual Slider -->
                    <div class="flex items-center justify-center gap-2 mt-2">
                        <button @click="prevService" @mouseenter="stopServiceSlide" @mouseleave="startServiceSlide" class="p-2.5 border border-slate-200 hover:bg-slate-50 rounded-xl transition-all font-bold shadow-sm text-xs text-slate-700 bg-white">
                            ← Kiri
                        </button>
                        <button @click="nextService" @mouseenter="stopServiceSlide" @mouseleave="startServiceSlide" class="p-2.5 bg-blue-600 text-white hover:bg-blue-700 rounded-xl transition-all font-bold shadow-md text-xs">
                            Kanan →
                        </button>
                    </div>
                </div>

                <!-- Window Viewport Pembungkus Translasi Slider -->
                <div class="relative w-full" @mouseenter="stopServiceSlide" @mouseleave="startServiceSlide">
                    <div class="flex transition-transform duration-500 ease-out" :style="{ transform: `translateX(-${currentServiceSlide * 100}%)` }">
                        
                        <div v-for="(srv, sIdx) in services" :key="sIdx" class="w-full shrink-0 px-2 flex justify-center">
                            <!-- Card Layanan Interaktif -->
                            <div class="max-w-xl w-full border border-slate-200 rounded-3xl p-8 bg-slate-50/70 flex flex-col justify-between shadow-sm hover:shadow-md transition-all duration-300">
                                <div class="space-y-5">
                                    <div class="w-12 h-12 bg-blue-600 text-white rounded-2xl flex items-center justify-center shadow-md">
                                        <component :is="srv.icon" class="w-6 h-6" />
                                    </div>
                                    <h4 class="text-lg font-black tracking-tight text-slate-950">{{ srv.title }}</h4>
                                    <p class="text-xs text-slate-400 font-medium leading-relaxed">{{ srv.description }}</p>
                                    <div class="h-px bg-slate-200 my-2"></div>
                                    <ul class="text-xs font-bold text-slate-500 space-y-2.5">
                                        <li v-for="(feat, fIdx) in srv.features" :key="fIdx" class="flex items-center gap-2.5">
                                            <CheckCircleIcon class="w-4 h-4 text-blue-600 shrink-0" /> {{ feat }}
                                        </li>
                                    </ul>
                                </div>
                                <a :href="route('register')" class="w-full text-center block mt-8 py-3 bg-white border border-slate-200 text-xs font-bold rounded-2xl hover:bg-blue-600 hover:text-white hover:border-blue-600 transition-all shadow-sm">
                                    {{ srv.btnText }}
                                </a>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Titik Penunjuk Posisi (Dots Indicator) -->
                <div class="flex justify-center gap-2 pt-2">
                    <button v-for="(_, index) in services" :key="index" @click="currentServiceSlide = index" :class="[currentServiceSlide === index ? 'w-6 bg-blue-600' : 'w-2 bg-slate-200']" class="h-2 rounded-full transition-all duration-300"></button>
                </div>

            </div>
        </section>
        <!-- =================================================================================== -->

        <section id="keunggulan" class="py-24 bg-slate-50/60 border-y border-slate-200/60">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-16">
                <div class="text-center space-y-3">
                    <h2 class="text-xs font-bold text-blue-600 uppercase tracking-widest">Keunggulan Platform</h2>
                    <h3 class="text-3xl font-black text-slate-950 tracking-tight">Standar Layanan Profesional & Terbuka</h3>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div v-for="(hl, hIdx) in highlights" :key="hIdx" class="bg-white border border-slate-200/60 rounded-xl p-5 flex items-center gap-4 shadow-sm">
                        <div class="p-2.5 bg-blue-50 text-blue-600 rounded-xl shrink-0"><component :is="hl.icon" class="w-4 h-4" /></div>
                        <span class="text-xs font-extrabold text-slate-800 tracking-tight leading-snug">{{ hl.title }}</span>
                    </div>
                </div>
            </div>
        </section>

        <section id="alur" class="py-24 border-b border-slate-200/60">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-16">
                <div class="text-center space-y-3">
                    <h2 class="text-xs font-bold text-blue-600 uppercase tracking-widest">Alur Penggunaan</h2>
                    <h3 class="text-3xl font-black text-slate-950 tracking-tight">Proses Cepat Kurang Dari 5 Menit</h3>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-5 gap-8 relative text-center">
                    <div class="space-y-3">
                        <div class="w-9 h-9 bg-blue-600 text-white font-mono text-xs font-black rounded-full flex items-center justify-center mx-auto shadow">1</div>
                        <h4 class="text-xs font-black tracking-tight uppercase">Pilih Layanan</h4>
                        <p class="text-[11px] text-slate-400 max-w-[170px] mx-auto font-medium">Tentukan layanan apa yang anda butuhkan.</p>
                    </div>
                    <div class="space-y-3">
                        <div class="w-9 h-9 bg-slate-50 text-slate-700 font-mono text-xs font-black rounded-full flex items-center justify-center mx-auto border border-slate-200 shadow-sm">2</div>
                        <h4 class="text-xs font-black tracking-tight uppercase">Inputkan IMEI</h4>
                        <p class="text-[11px] text-slate-400 max-w-[170px] mx-auto font-medium">Inputkan IMEI anda ke sistem.</p>
                    </div>
                    <div class="space-y-3">
                        <div class="w-9 h-9 bg-slate-50 text-slate-700 font-mono text-xs font-black rounded-full flex items-center justify-center mx-auto border border-slate-200 shadow-sm">3</div>
                        <h4 class="text-xs font-black tracking-tight uppercase">Bayar Transaksi</h4>
                        <p class="text-[11px] text-slate-400 max-w-[170px] mx-auto font-medium">Selesaikan nominal tagihan melalui platform QRIS / ShopeePay .</p>
                    </div>
                    <div class="space-y-3">
                        <div class="w-9 h-9 bg-slate-50 text-slate-700 font-mono text-xs font-black rounded-full flex items-center justify-center mx-auto border border-slate-200 shadow-sm">4</div>
                        <h4 class="text-xs font-black tracking-tight uppercase">Pemrosesan</h4>
                        <p class="text-[11px] text-slate-400 max-w-[170px] mx-auto font-medium">Sistem akan proses pesanan.</p>
                    </div>
                    <div class="space-y-3">
                        <div class="w-9 h-9 bg-slate-50 text-slate-700 font-mono text-xs font-black rounded-full flex items-center justify-center mx-auto border border-slate-200 shadow-sm">5</div>
                        <h4 class="text-xs font-black tracking-tight uppercase">Monitor Dashboard</h4>
                        <p class="text-[11px] text-slate-400 max-w-[170px] mx-auto font-medium">Hasil akan ditampilkan di dashboard dan whatsapp.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="py-24 bg-slate-50/40 border-b border-slate-200/60">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-8">
                <div class="space-y-3">
                    <h3 class="text-2xl font-black text-slate-950 tracking-tight">Metode Pembayaran Digital</h3>
                    <p class="text-xs text-slate-400 font-medium">Pembayaran dengan Verifikasi otomatis tanpa perlu repot uploud bukti transfer.</p>
                </div>
                <div class="flex flex-col sm:flex-row items-center justify-center gap-6">
                    
                    <!-- LOGO QRIS RESMI -->
                    <div class="bg-white border border-slate-200 px-6 py-4 rounded-2xl flex items-center gap-3 shadow-sm w-full sm:w-auto justify-center">
                        <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSRNBlq07PittdXvjPaA5CY72MTVgiX7ceVTQ&s" 
                             alt="Logo QRIS" 
                             class="h-6 w-auto object-contain" />
                        <span class="font-mono text-xs font-black tracking-tight text-slate-700">QRIS</span>
                    </div>
                    
                    <!-- LOGO SHOPEEPAY RESMI -->
                    <div class="bg-white border border-slate-200 px-6 py-4 rounded-2xl flex items-center gap-3 shadow-sm w-full sm:w-auto justify-center">
                        <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSvHQwk6fSvmFM-OB2y4-6V92KKvAM9QtGb-A&s" 
                             alt="Logo ShopeePay" 
                             class="h-5 w-auto object-contain" />
                        <span class="font-mono text-xs font-black tracking-tight text-slate-700">ShopeePay</span>
                    </div>
                </div>
                
                <div class="flex items-center justify-center gap-2 text-[10px] font-black uppercase tracking-wider">
                    <span class="bg-emerald-500/10 text-emerald-500 px-3 py-1 border border-emerald-500/20 rounded-md">AMAN</span>
                    <span class="bg-blue-500/10 text-blue-500 px-3 py-1 border border-blue-500/20 rounded-md">OTOMATIS</span>
                    <span class="bg-indigo-500/10 text-indigo-500 px-3 py-1 border border-indigo-500/20 rounded-md">CEPAT</span>
                </div>
            </div>
        </section>

        <section id="feedback" class="py-24">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-16">
                <div class="text-center space-y-3">
                    <h2 class="text-xs font-bold text-blue-600 uppercase tracking-widest">Feedback Pelanggan</h2>
                    <h3 class="text-3xl font-black text-slate-950 tracking-tight">Apa Kata Pengguna ROMEI?</h3>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-start">
                    <div class="lg:col-span-5 bg-slate-50/80 border border-slate-200 p-6 rounded-2xl space-y-5 shadow-sm">
                        <div class="text-center lg:text-left space-y-1">
                            <h4 class="text-3xl font-black font-mono tracking-tight text-slate-950">4.9 / 5.0</h4>
                            <p class="text-xs text-slate-400 font-medium">Berdasarkan dari 5.000+ Ulasan Pelanggan Terverifikasi</p>
                        </div>
                        <div class="space-y-3 font-medium text-xs">
                            <div class="space-y-1">
                                <div class="flex justify-between text-[11px]"><span>Kualitas Pelayanan</span><span class="font-bold">98%</span></div>
                                <div class="w-full h-1.5 bg-white rounded-full overflow-hidden"><div class="w-[98%] h-full bg-blue-600"></div></div>
                            </div>
                            <div class="space-y-1">
                                <div class="flex justify-between text-[11px]"><span>Kecepatan Sistem Gateway</span><span class="font-bold">97%</span></div>
                                <div class="w-full h-1.5 bg-white rounded-full overflow-hidden"><div class="w-[97%] h-full bg-blue-600"></div></div>
                            </div>
                            <div class="space-y-1">
                                <div class="flex justify-between text-[11px]"><span>Kemudahan Antarmuka Wizard</span><span class="font-bold">99%</span></div>
                                <div class="w-full h-1.5 bg-white rounded-full overflow-hidden"><div class="w-[99%] h-full bg-blue-600"></div></div>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-7 bg-slate-50/80 border border-slate-200 p-8 rounded-2xl min-h-[250px] flex flex-col justify-between relative group shadow-sm" @mouseenter="stopSlide" @mouseleave="startSlide">
                        <div v-if="props.approved_feedbacks && props.approved_feedbacks.length > 0" class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 bg-blue-600 text-white font-black text-xs rounded-full flex items-center justify-center uppercase shadow-inner">{{ props.approved_feedbacks[currentSlide].name.charAt(0) }}</div>
                                    <div>
                                        <h5 class="text-xs font-black text-slate-900 leading-none">{{ props.approved_feedbacks[currentSlide].name }}</h5>
                                        <span class="text-[10px] text-slate-400 font-mono mt-1 inline-block bg-white px-2 py-0.5 rounded">{{ props.approved_feedbacks[currentSlide].service_type }}</span>
                                    </div>
                                </div>
                                <span v-if="props.approved_feedbacks[currentSlide].is_verified_customer" class="text-[9px] font-black bg-emerald-500/10 text-emerald-500 px-2 py-0.5 border border-emerald-500/20 rounded-md">Pelanggan Terverifikasi</span>
                            </div>
                            
                            <div class="flex items-center text-amber-500">
                                <StarSolid v-for="i in props.approved_feedbacks[currentSlide].rating" :key="i" class="w-3.5 h-3.5" />
                            </div>

                            <p class="text-xs text-slate-500 leading-relaxed font-medium italic">"{{ props.approved_feedbacks[currentSlide].message }}"</p>

                            <div v-if="props.approved_feedbacks[currentSlide].admin_reply" class="bg-white p-4 rounded-xl border border-slate-200/60 text-left space-y-1">
                                <h6 class="text-[10px] font-black uppercase text-blue-600 tracking-wider">Balasan Tim ROMEI:</h6>
                                <p class="text-[11px] text-slate-400 font-medium leading-relaxed font-sans">"{{ props.approved_feedbacks[currentSlide].admin_reply }}"</p>
                            </div>
                        </div>
                        <div v-else class="text-center py-12 text-xs text-slate-400 font-medium">Belum ada ulasan terverifikasi yang disetujui admin harian.</div>

                        <div class="flex justify-end gap-2 pt-4">
                            <button @click="prevSlide" class="p-1.5 border border-slate-200 rounded-lg hover:bg-white transition-all text-xs font-bold">←</button>
                            <button @click="nextSlide" class="p-1.5 border border-slate-200 rounded-lg hover:bg-white transition-all text-xs font-bold">→</button>
                        </div>
                    </div>
                </div>

                <div class="max-w-xl mx-auto border border-slate-200 p-6 rounded-2xl bg-slate-50/40 shadow-sm space-y-4">
                    <div class="text-center">
                        <h4 class="text-xs font-black uppercase text-blue-600 tracking-wider">Formulir Kepuasan</h4>
                        <h5 class="text-base font-black text-slate-900 mt-1">Kirimkan Feedback & Penilaian Anda</h5>
                    </div>
                    <form @submit.prevent="submitFeedback" class="space-y-3.5 text-xs">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <label class="font-bold text-slate-400">Nama Lengkap *</label>
                                <input v-model="feedbackForm.name" type="text" required class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 focus:ring-1 focus:ring-blue-600" />
                            </div>
                            <div class="space-y-1">
                                <label class="font-bold text-slate-400">Email Akun (Opsional)</label>
                                <input v-model="feedbackForm.email" type="email" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 focus:ring-1 focus:ring-blue-600" />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <label class="font-bold text-slate-400">Jenis Layanan Terkait *</label>
                                <select v-model="feedbackForm.service_type" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 focus:ring-1 focus:ring-blue-600 font-bold">
                                    <option>Cek IMEI</option>
                                    <option>Cek History CEIR</option>
                                    <option>Daftar Roamer 3 Bulan</option>
                                </select>
                            </div>
                            <div class="space-y-1">
                                <label class="font-bold text-slate-400">Rating Penilaian Bintang *</label>
                                <select v-model="feedbackForm.rating" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 focus:ring-1 focus:ring-blue-600 font-bold text-slate-700">
                                    <option :value="5">5 Bintang (Sangat Puas)</option>
                                    <option :value="4">4 Bintang (Puas)</option>
                                    <option :value="3">3 Bintang (Cukup)</option>
                                    <option :value="2">2 Bintang (Kurang Puas)</option>
                                    <option :value="1">1 Bintang (Kecewa)</option>
                                </select>
                            </div>
                        </div>
                        <div class="space-y-1">
                            <label class="font-bold text-slate-400">Isi Feedback / Pesan Masukan *</label>
                            <textarea v-model="feedbackForm.message" rows="3" required minlength="10" placeholder="Tulis masukan objektif mas Sultan..." class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 focus:ring-1 focus:ring-blue-600"></textarea>
                        </div>
                        <button type="submit" :disabled="feedbackForm.processing" class="w-full py-2.5 bg-slate-950 text-white font-bold rounded-xl hover:opacity-90 transition-all disabled:opacity-50">Kirim Feedback Ke Admin</button>
                    </form>
                </div>

            </div>
        </section>

        <section id="faq" class="py-24 bg-slate-50/40 border-t border-slate-200/60">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
                <div class="text-center space-y-3">
                    <h2 class="text-xs font-bold text-blue-600 uppercase tracking-widest">Pertanyaan Umum</h2>
                    <h3 class="text-3xl font-black text-slate-950 tracking-tight">Pusat Bantuan FAQ</h3>
                </div>
                <div class="space-y-4">
                    <div v-for="(faq, fIdx) in faqs" :key="fIdx" class="bg-white border border-slate-200/60 rounded-xl overflow-hidden transition-all shadow-sm">
                        <button @click="activeFaq = activeFaq === fIdx ? null : fIdx" class="w-full px-5 py-4 flex items-center justify-between text-left gap-4">
                            <span class="text-xs font-black text-slate-900 tracking-tight">{{ faq.q }}</span>
                            <ChevronDownIcon :class="activeFaq === fIdx ? 'transform rotate-180' : ''" class="w-4 h-4 text-slate-400 transition-transform duration-200 shrink-0" />
                        </button>
                        <div v-if="activeFaq === fIdx" class="px-5 pb-5 border-t border-slate-100 pt-3">
                            <p class="text-xs text-slate-400 leading-relaxed font-medium font-sans">{{ faq.a }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="kontak" class="py-24">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-10">
                <div class="space-y-3">
                    <h2 class="text-xs font-bold text-blue-600 uppercase tracking-widest">Hubungi Kami</h2>
                    <h3 class="text-3xl font-black text-slate-950 tracking-tight">Layanan Helpdesk Pusat</h3>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-left font-mono text-[11px] text-slate-400 bg-slate-50/80 p-6 rounded-2xl border border-slate-200/60 shadow-sm">
                    <div class="space-y-1"><span class="font-black text-slate-700 block">WHATSAPP:</span><span>+62 881-024-441-441</span></div>
                    <div class="space-y-1"><span class="font-black text-slate-700 block">TELEGRAM:</span><span>@RomeiSupport</span></div>
                    <div class="space-y-1"><span class="font-black text-slate-700 block">EMAIL SUPPORT:</span><span>romei1@gmail.com</span></div>
                    <div class="space-y-1"><span class="font-black text-slate-700 block">JAM OPERASIONAL:</span><span>07:00 - 22:00 WIB</span></div>
                </div>
                <a href="https://wa.me/62881024441441" target="_blank" class="inline-flex items-center gap-2 px-10 py-3.5 bg-slate-950 text-white text-xs font-bold rounded-xl shadow transition-all hover:opacity-90">
                    <ChatBubbleLeftRightIcon class="w-4 h-4" /> Hubungi Customer Support Kami Now
                </a>
            </div>
        </section>

        <footer class="bg-slate-50 border-t border-slate-200 py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <CpuChipIcon class="w-5 h-5 text-blue-600" />
                            <span class="text-base font-black tracking-tight text-slate-950">ROMEI<span class="text-blue-600">.</span></span>
                        </div>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Trusted IMEI Registration & Verification Platform</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-8 gap-y-2 text-[11px] font-bold text-slate-400">
                        <a href="#beranda" class="hover:text-blue-600 transition-colors">Beranda</a>
                        <a href="#layanan" class="hover:text-blue-600 transition-colors">Layanan</a>
                        <a href="#keunggulan" class="hover:text-blue-600 transition-colors">Fitur</a>
                        <a href="#faq" class="hover:text-blue-600 transition-colors">Panduan</a>
                    </div>
                </div>
                <div class="h-px bg-slate-200"></div>
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 text-[10px] text-slate-400 font-medium font-mono">
                    <span>Copyright © 2026 ROMEI Platform. All Rights Reserved.</span>
                    <span class="opacity-60">Powered by SultanWeb Infrastructure v11.0</span>
                </div>
            </div>
        </footer>

    </div>
</template>

<style scoped>
@keyframes floatAnimation {
    0% { transform: translateY(0px); }
    50% { transform: translateY(-8px); }
    100% { transform: translateY(0px); }
}
.animate-float { animation: floatAnimation 4.5s ease-in-out infinite; }
html { scroll-behavior: smooth; }
</style>