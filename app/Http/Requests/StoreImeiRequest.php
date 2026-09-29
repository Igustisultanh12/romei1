<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreImeiRequest extends FormRequest
{
    /**
     * Tentukan apakah pengguna diizinkan untuk membuat request ini.
     */
    public function authorize(): bool
    {
        return auth()->check(); // Pastikan pengguna sudah login
    }

    /**
     * Dapatkan aturan validasi yang berlaku untuk request ini.
     */
    public function rules(): array
    {
        return [
            'sim_type'   => 'required|in:single,dual',
            'imei1'      => 'required|digits:15',
            'imei2'      => 'nullable|required_if:sim_type,dual|digits:15',
            'package_id' => 'required|exists:packages,id',
        ];
    }

    /**
     * Kustomisasi pesan error validasi dalam Bahasa Indonesia.
     */
    public function messages(): array
    {
        return [
            'sim_type.required'     => 'Jenis slot SIM wajib dipilih.',
            'sim_type.in'           => 'Jenis slot SIM tidak valid.',
            'imei1.required'        => 'Nomor IMEI slot utama wajib diisi.',
            'imei1.digits'          => 'Nomor IMEI 1 harus tepat berisikan 15 digit angka.',
            'imei2.required_if'     => 'Nomor IMEI slot sekunder wajib diisi jika Anda memilih Dual SIM.',
            'imei2.digits'          => 'Nomor IMEI 2 harus tepat berisikan 15 digit angka.',
            'package_id.required'   => 'Paket durasi aktif jaringan wajib dipilih.',
            'package_id.exists'     => 'Paket durasi yang Anda pilih tidak terdaftar di sistem.',
        ];
    }
}