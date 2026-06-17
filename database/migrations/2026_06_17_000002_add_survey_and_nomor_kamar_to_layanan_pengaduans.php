<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('layanan_pengaduans', function (Blueprint $table): void {
            $table->unsignedTinyInteger('rating')->nullable()->after('isi');
            $table->string('nomor_kamar')->nullable()->after('isi');
        });

        // For SQLite: jenis is stored as text, so adding 'survey' as a valid value
        // works without altering the enum. For MySQL, we would need to modify the enum.
        // The model validation will handle the allowed values.
    }

    public function down(): void
    {
        Schema::table('layanan_pengaduans', function (Blueprint $table): void {
            $table->dropColumn(['rating', 'nomor_kamar']);
        });
    }
};
