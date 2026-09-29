<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ImeiRegistrationController;
use App\Http\Controllers\RoamerRegistrationController; // Diimpor untuk modul pemisahan penanganan Add Roamer Baru
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\WelcomeController;
use App\Http\Controllers\Webhook\CeirkuWebhookController;
use App\Http\Controllers\Webhook\DokuWebhookController; 
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\WithdrawalController as AdminWithdrawalController;
use App\Http\Controllers\Admin\ApiMonitorController as AdminApiMonitorController;
use App\Http\Controllers\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Admin\FeedbackManagementController as AdminFeedbackController;
use App\Http\Controllers\Admin\CustomerManagementController; 
use App\Http\Controllers\Admin\WhatsappConfigController; // Diimpor untuk modul penanganan kontrol WhatsApp Gateway Port 7777
use App\Http\Controllers\Admin\MailGatewayController;
use App\Http\Controllers\Admin\AdminTwoFactorController;
use App\Http\Middleware\EnsureAdmin2FaVerified;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes - ROMEI Platform
|--------------------------------------------------------------------------
*/

// Halaman Landing Page Utama (Suplay Data Statistik & Feedback Approved)
Route::get('/', [WelcomeController::class, 'index'])->name('welcome');

// Endpoint Handler Kiriman Feedback Baru dari Pengunjung Layanan
Route::post('/feedbacks/store', [WelcomeController::class, 'storeFeedback'])->name('feedbacks.store');

// =========================================================================
// ROUTE WEBHOOK PUBLIK (Menerima Tembakan Asinkron Otomatis dari Pihak Luar)
// =========================================================================
// 1. Webhook Sinkronisasi Roamer dari CEIRKU Pusat
Route::post('/webhook/roamer-status', [CeirkuWebhookController::class, 'handle'])
    ->name('webhook.ceirku');

// =========================================================================
// ROUTE PUBLIC GUEST (Mengamankan Alur Form Login & Pendaftaran Akun ROMEI)
// =========================================================================
Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);
    
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

/*
|--------------------------------------------------------------------------
| PROTECTED AREA (Mesti Login - Bebas Akses Tanpa Hambatan Verifikasi Email)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {
    
    // 1. Beranda Depan Dashboard User Biasa
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard/User', [
            'stats' => [
                'wallet_balance' => auth()->user()->wallet?->balance ?? 0,
                'active_count' => auth()->user()->imeiRegistrations()->where('status', 'approved')->count(),
                'total_spent' => (float) auth()->user()->transactions()->where('status', 'paid')->sum('amount'),
            ],
            'recent_registrations' => auth()->user()->imeiRegistrations()->latest()->take(5)->get(),
            // Ditambahkan share data riwayat mutasi finansial taktis langsung ke dasbor depan pengguna
            'transactions' => auth()->user()->wallet?->transactions()->latest()->take(5)->get() ?? []
        ]);
    })->name('dashboard');

    // 2. Modul Alur Registrasi IMEI Lama (Direct Store & QRIS DOKU)
    Route::prefix('imei')->name('imei.')->group(function () {
        
        // MENYUPLAI DATA SETTINGS SECARA UTUH - BEBAS DARI ERROR RENDERING VUE (_s)
        Route::get('/create', function () {
            $packages = \App\Models\Package::where('is_active', true)->get();
            
            $maintenanceMode = \App\Models\Setting::get('maintenance_mode', 'false') === 'true';
            $berandaImageUrl = \App\Models\Setting::get('beranda_image_url', 'gambar/carton.png');
            $dokuClientId    = \App\Models\Setting::get('doku_client_id', '');

            return Inertia::render('IMEI/Create', [
                'packages' => $packages,
                'settings' => [
                    'maintenance_mode'  => $maintenanceMode,
                    'beranda_image_url' => asset('storage/' . $berandaImageUrl),
                    'doku_client_id'    => $dokuClientId,
                    'fee_add_roamer_1m' => \App\Models\Setting::get('fee_add_roamer_1m', 135000),
                    'fee_add_roamer_3m' => \App\Models\Setting::get('fee_add_roamer_3m', 180000),
                ]
            ]);
        })->name('create');
        
        Route::post('/store', [ImeiRegistrationController::class, 'store'])->name('store');
        Route::get('/payment/{id}', [ImeiRegistrationController::class, 'paymentPage'])->name('payment');
        Route::get('/registration/{id}/cetak-sertifikat', [ImeiRegistrationController::class, 'downloadPdf'])->name('download-pdf');
    });

    // =========================================================================
    // MODUL TERPISAH BARU: Pendaftaran Komersial Add Roamer Modular Terintegrasi
    // URL Akses Baru User: /roamer/create
    // =========================================================================
    Route::prefix('roamer')->name('roamer.')->group(function () {
        Route::get('/create', function () {
            return Inertia::render('Roamer/Create', [
                'settings' => [
                    'fee_add_roamer_1m' => \App\Models\Setting::get('fee_add_roamer_1m', 135000),
                    'fee_add_roamer_3m' => \App\Models\Setting::get('fee_add_roamer_3m', 180000),
                ]
            ]);
        })->name('create');

        // Endpoint penanganan mutasi finansial & handshake API roamer modular 24/36 dinamis
        Route::post('/register', [RoamerRegistrationController::class, 'register'])->name('register');
    });

    // 3. Modul Finansial Internal: Dompet Digital Wallet & Top Up
    Route::prefix('wallet')->name('wallet.')->group(function () {
        Route::get('/', function () {
            return Inertia::render('Wallet/Index', [
                'wallet' => auth()->user()->wallet,
                'transactions' => auth()->user()->wallet?->transactions()->latest()->get() ?? []
            ]);
        })->name('index');
        
        Route::post('/deposit', [ImeiRegistrationController::class, 'storeDeposit'])->name('deposit');
        Route::get('/check-status/{invoiceId}', [ImeiRegistrationController::class, 'checkStatus'])->name('check-status');
    });

    // 4. Modul Tambahan Finansial: Diagnosis Mandiri & Layanan Jaringan Berbayar
    Route::prefix('services')->name('services.')->group(function () {
        Route::get('/sim-lock', function () {
            return Inertia::render('Services/SimLock', [
                'fee' => (int) \App\Models\Setting::get('fee_check_sim_lock', 5000),
                'wallet_balance' => auth()->user()->wallet?->balance ?? 0
            ]);
        })->name('sim-lock');

        // KOREKSI ABSOLUT: Hilangkan dot prefix 'services.' agar sinkron dengan Ziggy frontend
        Route::get('/ceir-history', function () {
            return Inertia::render('Services/CeirHistory', [
                'fee' => (int) \App\Models\Setting::get('fee_check_ceir_history', 7500),
                'wallet_balance' => auth()->user()->wallet?->balance ?? 0
            ]);
        })->name('ceir-history');

        // RESTRUKTURISASI MODUL: Sinkronisasi Visual Mengabaikan String Fallback JSON Metadata Statis
        Route::get('/history', function () {
            $transactions = auth()->user()->transactions()
                ->where(function($query) {
                    $query->where('invoice_number', 'like', 'ROAM1M%')
                          ->orWhere('invoice_number', 'like', 'ROAM3M%')
                          ->orWhere('invoice_number', 'like', 'FEESL%')
                          ->orWhere('invoice_number', 'like', 'FEEHST%');
                })
                ->latest()
                ->get()
                ->map(function ($tx) {
                    // Parsing data metadata secara aman
                    $metadata = is_string($tx->metadata) ? json_decode($tx->metadata, true) : $tx->metadata;
                    
                    // Ambil status spesifik ceirku di dalam metadata secara aman (Case-Insensitive)
                    $ceirkuStatus = isset($metadata['ceirku_status']) ? strtoupper($metadata['ceirku_status']) : 'PROCESSING';

                    // Penentuan text dinamis murni bypass string ceirku_result database lama
                    if ($ceirkuStatus === 'SUCCESS') {
                        $tx->custom_status_text = 'Jaringan Aktif / Selesai';
                    } elseif ($ceirkuStatus === 'FAILED') {
                        $tx->custom_status_text = 'Gagal: Permohonan ditolak oleh admin HQ.';
                    } elseif ($ceirkuStatus === 'PROCESSING') {
                        $tx->custom_status_text = 'Sedang Diproses oleh Admin ROMEI...';
                    } else {
                        $tx->custom_status_text = 'Menunggu proses aktivasi operator pusat.';
                    }
                    return $tx;
                });

            return Inertia::render('Services/History', [
                'history_transactions' => $transactions
            ]);
        })->name('history');

        Route::prefix('tickets')->name('tickets.')->group(function () {
            Route::get('/', [TicketController::class, 'index'])->name('index');
            Route::post('/store', [TicketController::class, 'store'])->name('store');
            Route::get('/{id}', [TicketController::class, 'show'])->name('show');
            Route::post('/{id}/reply', [TicketController::class, 'reply'])->name('reply');
            Route::post('/{id}/close', [TicketController::class, 'close'])->name('close');
        });

        Route::post('/sim-lock/check', [ImeiRegistrationController::class, 'checkSimLockStatus'])->name('sim-lock.check');
        Route::post('/ceir-history/check', [ImeiRegistrationController::class, 'checkCeirHistory'])->name('ceir-history.check');

        // BACKWARD COMPATIBILITY: Mempertahankan fungsional rute roamer lama utuh agar dashboard lama tidak peca
        Route::post('/roamer-3m/register', [ImeiRegistrationController::class, 'registerRoamer3Months'])->name('roamer-3m.register');
    });

    // 5. Pengaturan Akun Terintegrasi (Profil, Keamanan, Notifikasi WA)
    Route::prefix('account')->name('account.')->group(function () {
        Route::get('/settings', [ProfileController::class, 'settingsPage'])->name('settings');
        Route::post('/update-password', [ProfileController::class, 'updatePassword'])->name('update-password');
        Route::post('/update-notification', [ProfileController::class, 'updateNotification'])->name('update-notification');
    });

    Route::post('/vouchers/check', [VoucherController::class, 'check'])->name('vouchers.check');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

// =========================================================================
// ROUTE OTENTIKASI DUA FAKTOR (2FA) ADMIN ROMEI HQ
// =========================================================================
Route::middleware(['auth'])->prefix('admin/2fa')->name('admin.2fa.')->group(function () {
    Route::get('/', [AdminTwoFactorController::class, 'show'])->name('index');
    Route::post('/verify', [AdminTwoFactorController::class, 'verify'])->name('verify')->middleware('throttle:5,1');
    Route::post('/resend', [AdminTwoFactorController::class, 'resend'])->name('resend')->middleware('throttle:3,1');
    Route::post('/cancel', [AdminTwoFactorController::class, 'cancel'])->name('cancel');
});

/*
|--------------------------------------------------------------------------
| ADMINISTRATIVE HQ AREA (Dengan Proteksi Kontrol Role Administrator & 2FA)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', EnsureAdmin2FaVerified::class])->prefix('admin')->name('admin.')->group(function () {
    
    Route::group([
        'middleware' => function ($request, $next) {
            if (!auth()->user()->isAdmin()) {
                abort(403, 'Akses Terbatas: Area ini khusus untuk Administrator ROMEI.');
            }
            return $next($request);
        }
    ], function () {
        
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        // INTEGRASI SINKRONISASI DATA GABUNGAN AMAN DARI ERROR getKey() & MEMBAL BALIK
        Route::get('/imei-registrations', function () {
            $regularRegistrations = \App\Models\ImeiRegistration::with('user')
                ->latest()
                ->get()
                ->map(function ($reg) {
                    return [
                        'id' => $reg->id,
                        'registration_number' => $reg->registration_number ?? 'REG-OLD',
                        'user' => $reg->user,
                        'sim_type' => $reg->sim_type ?? 'single',
                        'imei1' => $reg->imei1,
                        'imei2' => $reg->imei2,
                        'created_at' => $reg->created_at ? $reg->created_at->toIso8601String() : null,
                        'status' => $reg->status ?? 'pending',
                        'is_roamer' => false // Flag penanda properti untuk data reguler
                    ];
                })
                ->toArray();

            $roamerTransactions = \App\Models\Transaction::with('user')
                ->where('invoice_number', 'like', 'ROAM3M%')
                ->latest()
                ->get()
                ->map(function ($tx) {
                    preg_match('/IMEI:\s*([0-9]+)/i', $tx->description, $matches);
                    $extractedImei = $matches[1] ?? '000000000000000';

                    // Parsing JSON metadata secara aman
                    $metadata = is_string($tx->metadata) ? json_decode($tx->metadata, true) : $tx->metadata;
                    
                    // Ambil status spesifik ceirku di dalam metadata secara Case-Insensitive
                    $ceirkuStatus = isset($metadata['ceirku_status']) ? strtoupper($metadata['ceirku_status']) : null;

                    // FIX SINKRONISASI: Mengurai status secara dinamis mengikuti re-mapping kontrol admin terbaru
                    $mappedStatus = 'pending';
                    if ($ceirkuStatus === 'SUCCESS') {
                        $mappedStatus = 'selesai';
                    } elseif ($ceirkuStatus === 'FAILED') {
                        $mappedStatus = 'ditolak';
                    } elseif ($ceirkuStatus === 'PROCESSING' || strtoupper($tx->status) === 'SUCCESS') {
                        $mappedStatus = 'proses';
                    }

                    return [
                        'id' => $tx->id, 
                        'registration_number' => $tx->invoice_number,
                        'user' => $tx->user,
                        'sim_type' => 'single',
                        'imei1' => $extractedImei,
                        'imei2' => null,
                        'created_at' => $tx->created_at ? $tx->created_at->toIso8601String() : null,
                        'status' => $mappedStatus, // Sinkron mengikuti realisasi data perubahan admin hq
                        'is_roamer' => true // Flag penanda properti untuk transaksi lama
                    ];
                })
                ->toArray();

            $allRegistrations = collect(array_merge($regularRegistrations, $roamerTransactions))
                ->sortByDesc('created_at')
                ->values()
                ->all();

            return Inertia::render('IMEI/AdminIndex', [
                'registrations' => $allRegistrations
            ]);
        })->name('imei.index');

        // ROUTE UPDATE STATUS ANTRIAN IMEI INSTAN UNTUK ADMIN
        Route::put('/imei-registrations/{id}/status', [ImeiRegistrationController::class, 'updateStatus'])->name('imei.update-status');

        Route::prefix('withdrawals')->name('withdrawals.')->group(function () {
            Route::get('/', function () {
                return Inertia::render('Withdrawals/AdminIndex', [
                    'withdrawals' => \App\Models\Withdrawal::with('user')->latest()->get()
                ]);
            })->name('index');
            Route::post('/{id}/approve', [AdminWithdrawalController::class, 'approve'])->name('approve');
            Route::post('/{id}/reject', [AdminWithdrawalController::class, 'reject'])->name('reject');
        });

        Route::get('/vouchers', function () {
            return Inertia::render('Vouchers/AdminIndex', [
                'vouchers' => \App\Models\Voucher::latest()->get()
                ]);
        })->name('vouchers.index');

        // Halaman Manajemen Bantuan Tiket HQ
        Route::get('/tickets', [TicketController::class, 'adminIndex'])->name('tickets.index');
        Route::post('/tickets/{id}/admin-reply', [TicketController::class, 'adminReply'])->name('tickets.admin-reply');
        Route::post('/tickets/{id}/reply', [TicketController::class, 'adminReply'])->name('tickets.reply');

        // Monitoring API ROMEI HQ
        Route::get('/monitoring', [AdminApiMonitorController::class, 'index'])->name('monitoring');
        Route::post('/monitoring/test-payment', [AdminApiMonitorController::class, 'testPayment'])->name('monitoring.test-payment');
        Route::get('/monitoring/check-status/{invoiceId}', [AdminApiMonitorController::class, 'checkStatus'])->name('monitoring.check-status');

        Route::get('/settings', [AdminSettingController::class, 'index'])->name('settings');
        Route::post('/settings/update', [AdminSettingController::class, 'update'])->name('settings.update');
        Route::post('/settings/test-ceirku', [AdminSettingController::class, 'testCeirku'])->name('settings.test-ceirku');
        Route::post('/settings/test-qrqu', [AdminSettingController::class, 'testQrqu'])->name('settings.test-qrqu');
        Route::post('/settings/detect-ip', [AdminSettingController::class, 'detectIp'])->name('settings.detect-ip');

        Route::prefix('feedbacks')->name('feedbacks.')->group(function () {
            Route::get('/', [AdminFeedbackController::class, 'index'])->name('index');
            Route::post('/{id}/update-status', [AdminFeedbackController::class, 'updateStatus'])->name('update-status');
            Route::post('/{id}/reply', [AdminFeedbackController::class, 'reply'])->name('reply');
            Route::delete('/{id}', [AdminFeedbackController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('customers')->name('customers.')->group(function () {
            Route::get('/', [CustomerManagementController::class, 'index'])->name('index');
            Route::get('/{id}', [CustomerManagementController::class, 'show'])->name('show');
            Route::put('/{id}/update-profile', [CustomerManagementController::class, 'updateProfile'])->name('update_profile');
            Route::put('/{id}/reset-password', [CustomerManagementController::class, 'reset_password'])->name('reset_password');
            Route::post('/{id}/toggle-suspend', [CustomerManagementController::class, 'toggleSuspend'])->name('toggle_suspend');
        });

        // =========================================================================
        // MODUL ADD-ON BARU: Panel Manajemen & Sinkronisasi WhatsApp Gateway 2
        // =========================================================================
        Route::prefix('whatsapp-config')->name('whatsapp.')->group(function () {
            Route::get('/', [WhatsappConfigController::class, 'index'])->name('config');
            Route::put('/', [WhatsappConfigController::class, 'update'])->name('update');
        });

        // =========================================================================
        // MODUL MAIL GATEWAY SMTP & KEAMANAN 2FA
        // =========================================================================
        Route::prefix('mail-gateway')->name('mail.')->group(function () {
            Route::get('/', [MailGatewayController::class, 'index'])->name('index');
            Route::post('/update', [MailGatewayController::class, 'update'])->name('update')->middleware('throttle:20,1');
            Route::post('/test', [MailGatewayController::class, 'testSend'])->name('test')->middleware('throttle:5,1');
        });
        
    }); 
});

Route::get('/tes-wa-romei', function() {
    // Memanggil service dinamis yang mengarah ke port 3100
    \App\Services\WhatsappService2::sendMessage('62816500104', 'Halo Gusti, ini tes kirim pesan otomatis lewat link browser ROMEI v2.0!');
    
    return response()->json([
        'status' => 'Proses Eksekusi Selesai',
        'catatan' => 'Silakan periksa handphone target atau cek storage/logs/laravel.log jika pesan tidak masuk.'
    ]);
});

require __DIR__.'/auth.php';