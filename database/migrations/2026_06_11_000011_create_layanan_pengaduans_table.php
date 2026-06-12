<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('layanan_pengaduans', function (Blueprint $table): void {
            $table->id();
            $table->enum('jenis', ['gangguan', 'saran']);
            $table->string('nama')->nullable();
            $table->string('phone_number')->nullable();
            $table->text('isi');
            $table->enum('status', ['baru', 'diproses', 'selesai'])->default('baru');
            $table->timestamps();

            $table->index(['jenis', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('layanan_pengaduans');
    }
};
