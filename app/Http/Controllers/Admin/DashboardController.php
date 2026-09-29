<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ImeiRegistration;
use App\Models\Transaction;
use App\Models\Withdrawal;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Menampilkan metrik data statistik utama pada Dashboard Admin ROMEI.
     */
    public function index(): Response
    {
        $now = Carbon::now();

        // 1. Hitung Statistik Pengguna
        $totalUsers = User::count();

        // 2. Hitung Statistik Registrasi IMEI beserta Ruang Waktunya
        $imeiStats = ImeiRegistration::selectRaw("
            COUNT(*) as total,
            COUNT(CASE WHEN DATE(created_at) = ? THEN 1 END) as today,
            COUNT(CASE WHEN MONTH(created_at) = ? AND YEAR(created_at) = ? THEN 1 END) as this_month
        ", [$now->toDateString(), $now->month, $now->year])->first();

        // 3. Hitung Agregasi Keuangan dari Tabel Transaksi Pembayaran
        $financialStats = Transaction::selectRaw("
            SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as total_revenue,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_payments,
            COUNT(CASE WHEN status = 'paid' THEN 1 END) as success_payments,
            SUM(CASE WHEN status = 'refunded' THEN amount ELSE 0 END) as total_refund
        ")->first();

        // 4. Hitung Antrean Penarikan Dana (Withdrawal Pending)
        $pendingWithdrawals = Withdrawal::where('status', 'pending')->count();

        // 5. Ambil data aktivitas terbaru (Registrasi & Transaksi Terkini) untuk Feed Linimasa
        $recentRegistrations = ImeiRegistration::with('user')
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'user_name' => $item->user->name,
                    'reg_number' => $item->registration_number,
                    'imei' => $item->imei1,
                    'status' => $item->status,
                    'time' => $item->created_at->diffForHumans()
                ];
            });

        return Inertia::render('Dashboard/Admin', [
            'stats' => [
                'total_users' => $totalUsers,
                'total_registrations' => $imeiStats->total,
                'registrations_today' => $imeiStats->today,
                'registrations_this_month' => $imeiStats->this_month,
                'total_revenue' => (float) $financialStats->total_revenue,
                'pending_payments' => $financialStats->pending_payments,
                'success_payments' => $financialStats->success_payments,
                'total_refund' => (float) $financialStats->total_refund,
                'pending_withdrawals' => $pendingWithdrawals,
            ],
            'recent_activities' => $recentRegistrations
        ]);
    }
}