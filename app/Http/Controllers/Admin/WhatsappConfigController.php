<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class WhatsappConfigController extends Controller
{
    /**
     * TAMPILAN UTAMA: Mengikuti metode check internal proxy localhost
     */
    public function index()
    {
        $gatewayUrl = Setting::get('whatsapp_gateway_url', 'http://localhost:3100');
        
        try {
            // Mengikuti metode panggilan internal port 3100 rute /status
            $response = Http::timeout(5)->get($gatewayUrl . '/status');
            
            if ($response->successful()) {
                $nodeData = $response->json();
                $gatewayStatus = $nodeData['status'] ?? 'OFFLINE';
                $qrCode = $nodeData['qr'] ?? null;
            } else {
                $gatewayStatus = 'OFFLINE';
                $qrCode = null;
            }
        } catch (\Exception $e) {
            $gatewayStatus = 'OFFLINE';
            $qrCode = null;
        }

        // Lempar data komplit ke komponen Vue via Inertia share
        return Inertia::render('Admin/WhatsappConfig/Index', [
            'config' => [
                'gateway_url' => $gatewayUrl,
                'status'      => $gatewayStatus,
                'qr'          => $qrCode
            ]
        ]);
    }

    /**
     * AKSI: Menyimpan update link URL / Port Gateway Baru
     */
    public function update(Request $request)
    {
        $request->validate([
            'gateway_url' => 'required|string'
        ]);

        try {
            Setting::set('whatsapp_gateway_url', $request->gateway_url);
            return redirect()->back()->with('success', 'Endpoint internal WhatsApp Gateway berhasil diperbarui!');
        } catch (\Exception $e) {
            Log::error('ROMEI WHATSAPP CONFIG UPDATE ERROR: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal menyimpan: ' . $e->getMessage());
        }
    }
}