<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class CustomerManagementController extends Controller
{
    /**
     * Menampilkan daftar seluruh pelanggan beserta saldo wallet mereka
     */
    public function index(Request $request)
    {
        $search = $request->input('search');

        $customers = User::where('role', 'user')
            ->with('wallet')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('whatsapp_number', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/Customers/Index', [
            'customers' => $customers,
            'filters' => $request->only(['search'])
        ]);
    }

    /**
     * Memuat Profil Lengkap Pelanggan, Saldo, dan Riwayat Transaksi Finansial
     */
    public function show($id)
    {
        $customer = User::with(['wallet', 'imeiRegistrations'])->findOrFail($id);

        if ($customer->isAdmin()) {
            abort(403, 'Akses Ditolak: Akun administrator tidak dapat dikelola di area ini.');
        }

        // Mengambil riwayat invoice finansial komersial & diagnosis utilitas milik pelanggan
        $transactions = Transaction::where('user_id', $customer->id)
            ->latest()
            ->paginate(10);

        return Inertia::render('Admin/Customers/Show', [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'whatsapp_number' => $customer->whatsapp_number,
                'role' => $customer->role,
                'is_suspended' => (bool) $customer->is_suspended,
                'created_at' => $customer->created_at->format('d F Y H:i'),
                'balance' => $customer->wallet ? (float) $customer->wallet->balance : 0.0,
                'total_imei' => $customer->imeiRegistrations->count(),
            ],
            'transactions' => $transactions
        ]);
    }

    /**
     * Ubah Profil Pelanggan (Email & Nomor WhatsApp)
     */
    public function updateProfile(Request $request, $id)
    {
        $customer = User::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $customer->id,
            'whatsapp_number' => 'required|string|max:20|unique:users,whatsapp_number,' . $customer->id,
        ]);

        $customer->update([
            'name' => $request->name,
            'email' => $request->email,
            'whatsapp_number' => preg_replace('/[^0-9]/', '', $request->whatsapp_number),
        ]);

        Log::info("ROMEI ADMIN - Profil pelanggan ID {$customer->id} berhasil diubah oleh Admin.", ['admin_id' => auth()->id()]);

        return back()->with('success', 'Profil komponen data pelanggan berhasil diperbarui.');
    }

    /**
     * Utilitas Pengaman Kredensial: Reset Password Instan Pelanggan
     */
    public function resetPassword(Request $request, $id)
    {
        $customer = User::findOrFail($id);

        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $customer->update([
            'password' => Hash::make($request->password),
        ]);

        Log::warning("ROMEI ADMIN - Password pelanggan ID {$customer->id} dipaksa reset oleh Admin.", ['admin_id' => auth()->id()]);

        return back()->with('success', 'Kredensial password baru pelanggan berhasil disimpan.');
    }

    /**
     * Proteksi Keamanan Jaringan: Suspend / Aktifkan Kembali Akun Pelanggan
     */
    public function toggleSuspend($id)
    {
        $customer = User::findOrFail($id);

        if ($customer->isAdmin()) {
            return back()->withErrors(['message' => 'Gagal: Akun dengan tingkatan role Admin tidak dapat disuspend.']);
        }

        // Membalikkan status boolean suspensi gawai pelanggan
        $customer->is_suspended = !$customer->is_suspended;
        $customer->save();

        $statusText = $customer->is_suspended ? 'DIBLOKIR (SUSPEND)' : 'DIAKTIFKAN KEMBALI';
        Log::notice("ROMEI ADMIN - Status akun pelanggan ID {$customer->id} diubah menjadi: {$statusText}", ['admin_id' => auth()->id()]);

        return back()->with('success', "Akun pelanggan berhasil {$statusText}.");
    }
}