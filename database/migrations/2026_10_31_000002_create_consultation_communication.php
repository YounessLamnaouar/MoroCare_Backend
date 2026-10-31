<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('consultation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teleconsultation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->restrictOnDelete();
            $table->uuid('client_id');
            $table->text('body');
            $table->timestamps();
            $table->unique(['sender_id', 'client_id']);
            $table->index(['teleconsultation_id', 'id']);
        });
        Schema::create('consultation_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teleconsultation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 20);
            $table->text('payload');
            $table->timestamp('created_at');
            $table->index(['teleconsultation_id', 'created_at']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('consultation_signals');
        Schema::dropIfExists('consultation_messages');
    }
};
