<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException; 
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Validator; // DITAMBAHKAN untuk debugging manual

class RegisteredUserController extends Controller
{
    /**
     * Menampilkan halaman registrasi (Frontend Vue via Inertia).
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Memproses data registrasi user baru di platform ROMEI.
     * * PERBAIKAN TIMING LOGGING: Mengubah type-hint dari RegisterRequest ke Request dasar 
     * agar validasi bisa kita bongkar manual di dalam method demi memunculkan log kegagalannya.
     */
    public function store(\Illuminate\Http\Request $request): RedirectResponse
    {
        // 1. INTRA-INTERCEPTOR LOGGING: Rekam payload mentah yang dikirim oleh Vue ke log server
        Log::info("ROMEI DEBUG - Payload Masuk dari Form Register:", $request->except(['password', 'password_confirmation']));

        // 2. JALANKAN VALIDASI MANUAL (Bongkar isi RegisterRequest ke sini agar error-nya tercatat di log)
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'whatsapp_number' => ['required', 'string', 'max:25'], 
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            // JIKA GAGAL VALIDASI: Tulis detail inputan mana yang memicu kegagalan ke dalam laravel.log
            Log::warning("ROMEI DEBUG - Form Register Gagal Validasi Input:", [
                'errors' => $validator->errors()->toArray(),
                'input_data' => $request->except(['password', 'password_confirmation'])
            ]);

            return back()->withErrors($validator->errors())->withInput();
        }

        try {
            $user = DB::transaction(function () use ($request) {
                
                // 1. Buat User Pelanggan Biasa Baru
                $user = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'whatsapp_number' => $request->whatsapp_number,
                    'password' => Hash::make($request->password),
                    'role' => 'user', 
                ]);

                // 2. Inisialisasi Wallet Otomatis untuk User Tersebut
                $user->wallet()->create([
                    'balance' => 0.00
                ]);

                // 3. Catat Aktivitas ke Global Audit Log
                AuditLog::create([
                    'user_id' => $user->id,
                    'activity' => 'USER_REGISTERED',
                    'description' => "Pelanggan baru berhasil mendaftar dengan nama: {$user->name} dan nomor WhatsApp: {$user->whatsapp_number}.",
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]);

                return $user;
            });

            // Trigger event registrasi bawaan Laravel
            event(new Registered($user));

            // Otomatis login setelah pendaftaran sukses
            Auth::login($user);

            // Redirect ke halaman dashboard user
            return redirect()->route('dashboard');

        } catch (QueryException $ex) {
            Log::error("ROMEI DATABASE ERROR - Gagal Registrasi: " . $ex->getMessage(), [
                'sql' => $ex->getSql(),
                'bindings' => $ex->getBindings()
            ]);

            return back()->withErrors([
                'error' => 'Database mendeteksi struktur kolom tidak cocok: ' . ($ex->errorInfo[2] ?? $ex->getMessage())
            ])->withInput($request->except(['password', 'password_confirmation']));

        } catch (\Exception $e) {
            Log::critical("Gagal memproses registrasi user: " . $e->getMessage(), [
                'payload' => $request->except(['password', 'password_confirmation']),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->withErrors([
                'error' => 'Gagal mendaftar: ' . $e->getMessage()
            ])->withInput($request->except(['password', 'password_confirmation']));
        }
    }
}