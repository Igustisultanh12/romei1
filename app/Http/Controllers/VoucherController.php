<?php

namespace App\Http\Controllers;

use App\Http\Requests\Request;
use App\Actions\ApplyVoucherAction;
use App\Models\Package;

class VoucherController extends Controller {
    
    public function check(Illuminate\Http\Request $request) {
        $request->validate([
            'code' => 'required|string',
            'package_id' => 'required|exists:packages,id'
        ]);

        $package = Package::findOrFail($request->package_id);

        try {
            $action = app(ApplyVoucherAction::class)->execute($request->code, $package->price);
            
            return response()->json([
                'success' => true,
                'discount_amount' => $action['discount_amount'],
                'final_price' => $action['final_price'],
                'message' => 'Voucher berhasil diterapkan!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }
}