<?php

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
            'recent_registrations' => auth()->user()->imeiRegistrations()->latest()->take(5)->get()
        ]);
    })->name('dashboard');

    // 2. Modul Alur Registrasi IMEI (Direct Store & QRIS DOKU Tanpa Verifikasi Awal)
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
        Route::get('/registration/{id}/download-pdf', [ImeiRegistrationController::class, 'downloadPdf'])->name('download-pdf');
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

        Route::get('/ceir-history', function () {
            return Inertia::render('Services/CeirHistory', [
                'fee' => (int) \App\Models\Setting::get('fee_check_ceir_history', 7500),
                'wallet_balance' => auth()->user()->wallet?->balance ?? 0
            ]);
        })->name('ceir-history');

        Route::get('/history', function () {
            return Inertia::render('Services/History', [
                'history_transactions' => auth()->user()->transactions()
                    ->where(function($query) {
                        $query->where('invoice_number', 'like', 'ROAM1M%')
                              ->orWhere('invoice_number', 'like', 'ROAM3M%')
                              ->orWhere('invoice_number', 'like', 'FEESL%')
                              ->orWhere('invoice_number', 'like', 'FEEHST%');
                    })
                    ->latest()
                    ->get()
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

        // BACKWARD COMPATIBILITY: Mempertahankan fungsional rute roamer lama utuh agar dashboard lama tidak pecah
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

/*
|--------------------------------------------------------------------------
| ADMINISTRATIVE HQ AREA (Dengan Proteksi Kontrol Role Administrator)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    
    Route::group([
        'middleware' => function ($request, $next) {
            if (!auth()->user()->isAdmin()) {
                abort(403, 'Akses Terbatas: Area ini khusus untuk Administrator ROMEI.');
            }
            return $next($request);
        }
    ], function () {
        
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::get('/imei-registrations', function () {
            return Inertia::render('IMEI/AdminIndex', [
                'registrations' => \App\Models\ImeiRegistration::with('user')->latest()->get()
            ]);
        })->name('imei.index');

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

        Route::get('/tickets', [TicketController::class, 'adminIndex'])->name('tickets.index');
        Route::post('/tickets/{id}/admin-reply', [TicketController::class, 'adminReply'])->name('tickets.admin-reply');
        Route::post('/tickets/{id}/reply', [TicketController::class, 'adminReply'])->name('tickets.reply');

        Route::get('/monitoring', [AdminApiMonitorController::class, 'index'])->name('monitoring');
        Route::post('/monitoring/test-payment', [AdminApiMonitorController::class, 'testPayment'])->name('monitoring.test-payment');
        Route::get('/monitoring/check-status/{invoiceId}', [AdminApiMonitorController::class, 'checkStatus'])->name('monitoring.check-status');

        Route::get('/settings', [AdminSettingController::class, 'index'])->name('settings');
        Route::post('/settings/update', [AdminSettingController::class, 'update'])->name('settings.update');

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
        
    }); 
});

require __DIR__.'/auth.php';