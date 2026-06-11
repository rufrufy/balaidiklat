<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chatbot_rules', function (Blueprint $table): void {
            $table->text('reply_text')->nullable()->change();
            $table->string('keyword')->default('')->change();
        });
    }

    public function down(): void
    {
        Schema::table('chatbot_rules', function (Blueprint $table): void {
            $table->text('reply_text')->nullable(false)->change();
            $table->string('keyword')->default(null)->change();
        });
    }
};
