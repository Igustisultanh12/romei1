<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * Tentukan apakah pengguna diizinkan untuk membuat request ini.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sanitasi data sebelum masuk ke proses validasi utama.
     * Mengamankan format nomor WhatsApp dari karakter non-angka bawaan input user.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('whatsapp_number')) {
            // Menghapus spasi, strip (-), tanda tambah (+), dan karakter non-digit lainnya otomatis
            $cleanedNumber = preg_replace('/[^0-9]/', '', $this->whatsapp_number);

            $this->merge([
                'whatsapp_number' => $cleanedNumber,
            ]);
        }
    }

    /**
     * Dapatkan aturan validasi yang berlaku untuk request ini.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            
            // Menggunakan regex untuk menghitung panjang karakter digit string secara akurat
            'whatsapp_number' => [
                'required', 
                'string', 
                'regex:/^[0-9]+$/', 
                'min:10', 
                'max:15', 
                'unique:users,whatsapp_number'
            ],
            
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * Dapatkan pesan kesalahan kustom untuk aturan validasi yang ditentukan.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format alamat email tidak valid.',
            'email.unique' => 'Alamat email sudah terdaftar di sistem.',
            
            // Pesan error khusus untuk nomor WhatsApp ROMEI
            'whatsapp_number.required' => 'Nomor WhatsApp wajib diisi.',
            'whatsapp_number.regex' => 'Nomor WhatsApp harus berupa angka seluruhnya (0-9).',
            'whatsapp_number.unique' => 'Nomor WhatsApp sudah terdaftar di sistem.',
            'whatsapp_number.min' => 'Nomor WhatsApp minimal terdiri dari 10 digit.',
            'whatsapp_number.max' => 'Nomor WhatsApp maksimal terdiri dari 15 digit.',
            
            'password.required' => 'Kata sandi wajib diisi.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ];
    }
}