<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kamar_reservasis', function (Blueprint $table): void {
            if (! Schema::hasColumn('kamar_reservasis', 'tipe_penyewa')) {
                $table->enum('tipe_penyewa', ['perorangan', 'instansi'])->default('perorangan')->after('nama_pemesan');
            }
        });

        // Make instansi/kegiatan/jumlah_peserta optional (perorangan tidak mengisi).
        Schema::table('kamar_reservasis', function (Blueprint $table): void {
            $table->string('instansi')->nullable()->change();
            $table->string('kegiatan')->nullable()->change();
            $table->unsignedInteger('jumlah_peserta')->nullable()->default(1)->change();
        });
    }

    public function down(): void
    {
        Schema::table('kamar_reservasis', function (Blueprint $table): void {
            if (Schema::hasColumn('kamar_reservasis', 'tipe_penyewa')) {
                $table->dropColumn('tipe_penyewa');
            }
        });
    }
};
