<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_user_id')->constrained('users');   // a quién se le asignan G
            $table->foreignId('requested_by')->constrained('users');     // jefe_casa o moderador que propone
            $table->integer('amount');
            $table->string('reason');
            $table->text('note')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users'); // banquero que autoriza
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_requests');
    }
};