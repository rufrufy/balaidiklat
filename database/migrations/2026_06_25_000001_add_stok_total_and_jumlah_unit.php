<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kamars', function (Blueprint $table): void {
            if (! Schema::hasColumn('kamars', 'stok_total')) {
                $table->unsignedInteger('stok_total')->default(1)->after('harga_per_malam');
            }
        });

        Schema::table('kamar_reservasi_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('kamar_reservasi_items', 'jumlah_unit') && ! Schema::hasColumn('kamar_reservasi_items', 'jumlah')) {
                $table->unsignedInteger('jumlah_unit')->default(1)->after('kamar_reservasi_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kamar_reservasi_items', function (Blueprint $table): void {
            if (Schema::hasColumn('kamar_reservasi_items', 'jumlah_unit')) {
                $table->dropColumn('jumlah_unit');
            }
        });

        Schema::table('kamars', function (Blueprint $table): void {
            if (Schema::hasColumn('kamars', 'stok_total')) {
                $table->dropColumn('stok_total');
            }
        });
    }
};
