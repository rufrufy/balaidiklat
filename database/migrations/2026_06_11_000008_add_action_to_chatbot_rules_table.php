<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chatbot_rules', function (Blueprint $table): void {
            if (! Schema::hasColumn('chatbot_rules', 'action')) {
                $table->string('action')->nullable()->after('reply_text');
            }
        });
    }

    public function down(): void
    {
        Schema::table('chatbot_rules', function (Blueprint $table): void {
            if (Schema::hasColumn('chatbot_rules', 'action')) {
                $table->dropColumn('action');
            }
        });
    }
};
