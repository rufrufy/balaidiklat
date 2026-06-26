<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chatbot_rules', function (Blueprint $table) {
            $table->string('menu_label')->nullable()->after('action');
            $table->string('menu_description')->nullable()->after('menu_label');
            $table->unsignedInteger('menu_order')->default(0)->after('menu_description');
        });
    }

    public function down(): void
    {
        Schema::table('chatbot_rules', function (Blueprint $table) {
            $table->dropColumn(['menu_label', 'menu_description', 'menu_order']);
        });
    }
};
