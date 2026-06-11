<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kamar_reservasis', function (Blueprint $table): void {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama_pemesan');
            $table->string('instansi')->nullable();
            $table->string('kegiatan');
            $table->string('phone_number')->nullable();
            $table->foreignId('kamar_id')->nullable()->constrained('kamars')->nullOnDelete();
            $table->date('tanggal_masuk')->nullable();
            $table->date('tanggal_keluar')->nullable();
            $table->unsignedInteger('jumlah_peserta')->default(1);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kamar_reservasis');
    }
};
