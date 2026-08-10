<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table): void {
            $table->string('mode')->default('bot')->after('state');
            $table->timestamp('human_taken_at')->nullable()->after('last_message_at');
            $table->index('mode');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table): void {
            $table->dropIndex(['mode']);
            $table->dropColumn(['mode', 'human_taken_at']);
        });
    }
};
