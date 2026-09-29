<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'group'];

    /**
     * Helper untuk mengambil nilai konfigurasi secara instan (Plain Text)
     */
    public static function get($key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        
        if ($setting && !is_null($setting->value)) {
            // Mengembalikan nilai teks asli murni dari database harian
            return $setting->value;
        }
        
        return env(strtoupper($key), $default);
    }

    /**
     * Helper untuk mempermudah penyimpanan data secara instan jika dibutuhkan
     */
    public static function set($key, $value, $group = 'general')
    {
        return self::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'group' => $group
            ]
        );
    }
}