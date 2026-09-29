<?
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Packages
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 15, 2);
            $table->integer('duration_days')->default(90);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Wallets
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->decimal('balance', 15, 2)->default(0);
            $table->timestamps();
        });

        // IMEI Registrations
        Schema::create('imei_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('package_id')->constrained();
            $table->string('imei1', 16);
            $table->string('imei2', 16)->nullable();
            $table->string('status')->default('pending'); // pending, processing, approved, rejected, expired
            $table->string('registration_number')->unique();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
        });
        
        // Transactions
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->morphs('payable'); // Bisa untuk Registrasi atau Deposit
            $table->decimal('amount', 15, 2);
            $table->string('status')->default('pending'); // pending, paid, failed
            $table->string('payment_gateway_ref')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('imei_registrations');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('packages');
    }
};