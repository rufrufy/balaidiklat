<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kamars', function (Blueprint $table): void {
            if (! Schema::hasColumn('kamars', 'tipe')) {
                $table->enum('tipe', ['kamar', 'ruang_kelas'])->default('kamar')->after('nama');
            }
            if (! Schema::hasColumn('kamars', 'fasilitas')) {
                $table->text('fasilitas')->nullable()->after('harga_per_malam');
            }
        });

        foreach (['gedung', 'jenis', 'kapasitas', 'tersedia'] as $column) {
            if (Schema::hasColumn('kamars', $column)) {
                Schema::table('kamars', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }

    public function down(): void
    {
        Schema::table('kamars', function (Blueprint $table): void {
            if (! Schema::hasColumn('kamars', 'gedung')) {
                $table->string('gedung')->default('-');
            }
            if (! Schema::hasColumn('kamars', 'jenis')) {
                $table->string('jenis')->default('-');
            }
            if (! Schema::hasColumn('kamars', 'kapasitas')) {
                $table->unsignedInteger('kapasitas')->default(0);
            }
            if (! Schema::hasColumn('kamars', 'tersedia')) {
                $table->unsignedInteger('tersedia')->default(0);
            }
        });

        Schema::table('kamars', function (Blueprint $table): void {
            foreach (['tipe', 'fasilitas'] as $column) {
                if (Schema::hasColumn('kamars', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
