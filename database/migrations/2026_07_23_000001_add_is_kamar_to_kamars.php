<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kamars', function (Blueprint $table): void {
            if (! Schema::hasColumn('kamars', 'is_kamar')) {
                $table->boolean('is_kamar')->default(true)->after('stok_total');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kamars', function (Blueprint $table): void {
            if (Schema::hasColumn('kamars', 'is_kamar')) {
                $table->dropColumn('is_kamar');
            }
        });
    }
};
