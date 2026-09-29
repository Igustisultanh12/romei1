<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['topup', 'payment', 'refund', 'withdrawal', 'referral_commission']);
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->string('description')->nullable();
            $table->string('reference_id')->nullable(); // Menyimpan ID registrasi atau ID transaksi eksternal
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('wallet_transactions');
    }
};