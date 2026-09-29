<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * MODUL BARU ROMEI: Menampilkan halaman pengaturan akun dengan 3 tab dinamis
     */
    public function settingsPage(): Response
    {
        $user = auth()->user();
        
        return Inertia::render('Account/Settings', [
            'user_data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'whatsapp_number' => $user->whatsapp_number ?? 'Belum Diisi',
                'created_at' => $user->created_at ? $user->created_at->format('d M Y (H:i)') : now()->format('d M Y (H:i)'),
                'wa_notification' => (bool) ($user->wa_notification ?? true), // Status preferensi notifikasi WA
            ]
        ]);
    }

    /**
     * MODUL BARU ROMEI: Memproses modifikasi perubahan kata sandi (Tab Keamanan)
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'current_password.current_password' => 'Kata sandi lama yang Anda masukkan tidak cocok dengan catatan kami.',
            'password.confirmed' => 'Konfirmasi kata sandi baru tidak cocok.',
            'password.min' => 'Kata sandi baru minimal harus terdiri dari 8 karakter.',
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->back()->with('success', 'Kata sandi akun ROMEI Anda berhasil diperbarui.');
    }

    /**
     * MODUL BARU ROMEI: Memproses opsi toggle notifikasi WhatsApp (Tab Notifikasi)
     */
    public function updateNotification(Request $request): RedirectResponse
    {
        $request->validate([
            'wa_notification' => 'required|boolean'
        ]);

        $request->user()->update([
            'wa_notification' => $request->wa_notification
        ]);

        return redirect()->back()->with('success', 'Preferensi notifikasi WhatsApp berhasil disimpan.');
    }

    /**
     * Display the user's profile form (Breeze Default).
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information (Breeze Default).
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account (Breeze Default).
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}