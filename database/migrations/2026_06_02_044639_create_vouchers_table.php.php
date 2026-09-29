<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // Contoh: MERDEKA90
            $table->enum('type', ['fixed', 'percentage']); // Potongan harga tetap atau persen
            $table->decimal('reward_value', 15, 2); // Nilai potongan (Rp / %)
            $table->decimal('max_discount_limit', 15, 2)->nullable(); // Batas maksimum jika tipe persentase
            $table->integer('usage_limit')->default(100); // Maksimal kupon bisa diklaim secara global
            $table->integer('used_count')->default(0); // Total kupon yang sudah terpakai
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('vouchers');
    }
};