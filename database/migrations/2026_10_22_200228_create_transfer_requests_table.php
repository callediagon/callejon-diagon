<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_user_id')->constrained('users');
            $table->foreignId('to_user_id')->constrained('users');
            $table->integer('amount');
            $table->string('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled', 'expired'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users'); // banquero que aprobó/rechazó
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('expires_at');                      // 48h desde creación
            $table->timestamps();

            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_requests');
    }
};