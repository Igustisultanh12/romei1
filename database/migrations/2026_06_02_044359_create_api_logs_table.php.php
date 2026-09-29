<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('api_logs', function (Blueprint $table) {
            $table->id();
            $table->string('service_name'); // 'CEIRKU' atau 'DOKU'
            $table->string('endpoint');
            $table->string('method', 10);
            $table->integer('http_status'); // 200, 400, 500, etc.
            $table->float('latency_ms'); // Durasi response dalam milidetik
            $table->boolean('is_success');
            $table->text('error_message')->nullable();
            $table->timestamps();
            
            // Indexing untuk mempercepat kueri analitik dashboard harian
            $table->index(['service_name', 'created_at']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('api_logs');
    }
};