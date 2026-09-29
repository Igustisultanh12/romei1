<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            
            // MAP DATA AUTH SECARA STRICT DAN AMAN UNTUK MEMBENTENGIN LAYOUT VUE
            'auth' => [
                'user' => $request->user() ? [
                    'id'               => $request->user()->id,
                    'name'             => $request->user()->name,
                    'email'            => $request->user()->email,
                    'whatsapp_number'  => $request->user()->whatsapp_number,
                    'role'             => $request->user()->role ?? 'user', 
                    // LENGKAPAN PENTING: Deteksi verifikasi email agar frontend tidak memantul tanpa alasan
                    'email_verified'   => $request->user()->hasVerifiedEmail(), 
                ] : null,
            ],

            // PERBAIKAN STABILITAS: Menggunakan eksekusi langsung get() agar tidak menyumbat reaktivitas objek JSON Inertia
            'flash' => [
                'success'              => $request->session()->get('success'), // Untuk notifikasi sukses tiket/registrasi
                'error'                => $request->session()->get('error'),   // Untuk notifikasi kegagalan/validasi
                'message'              => $request->session()->get('flash.message'),
                'sim_lock_details'     => $request->session()->get('flash.sim_lock_details'),
                'ceir_history_details' => $request->session()->get('flash.ceir_history_details'),
                'roamer_details'       => $request->session()->get('flash.roamer_details'),
            ],
        ];
    }
}