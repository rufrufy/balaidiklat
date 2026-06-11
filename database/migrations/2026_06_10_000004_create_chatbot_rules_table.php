<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('nama');
            $table->string('keyword');
            $table->string('match_type')->default('contains');
            $table->string('state')->nullable();
            $table->text('reply_text');
            $table->string('next_state')->nullable();
            $table->unsignedInteger('priority')->default(10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_rules');
    }
};
