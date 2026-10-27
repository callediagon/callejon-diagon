<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();          // a quién afecta
            $table->foreignId('created_by')->nullable()->constrained('users'); // quién la registró (banquero)
            $table->enum('type', ['credit', 'debit', 'reversal']); // tipo
            $table->integer('amount');                            // galeones (puede ser negativo en reversal)
            $table->string('reason');                             // motivo (predefinido u "Otro")
            $table->text('note')->nullable();                     // nota libre si reason="Otro"
            $table->foreignId('related_transaction_id')->nullable()->constrained('transactions'); // para reversals
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};