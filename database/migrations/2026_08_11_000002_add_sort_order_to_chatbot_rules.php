<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chatbot_rules', function (Blueprint $table) {
            $table->integer('sort_order')->default(0)->after('priority');
            $table->index(['state', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('chatbot_rules', function (Blueprint $table) {
            $table->dropIndex(['state', 'sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};
