<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_states', function (Blueprint $table) {
            $table->id();
            $table->string('state_key')->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('color')->default('#072C2C');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_entry_point')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_states');
    }
};
