<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('phone_number');
            $table->enum('direction', ['inbound', 'outbound']);
            $table->string('message_type')->nullable();
            $table->text('message_text')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['phone_number', 'created_at']);
            $table->index('direction');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
