<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retribusi_billings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kamar_reservasi_id')->nullable()->constrained('kamar_reservasis')->nullOnDelete();

            // Static fields (defaults from eRetribusi integration spec).
            $table->string('noskpd')->default('1111');
            $table->string('periode')->default('2026');
            $table->string('sts_ssrd')->default('4 1 2');
            $table->string('namapenyetor')->default('BKPP');
            $table->string('t_nama')->default('BKPP');
            $table->string('npwrd')->default('123');
            $table->string('rekening')->default('76|4.1.02.02.01.0005|Retribusi Pemakaian Ruangan Balai Diklat');

            // Dynamic fields.
            $table->date('tanggal');
            $table->string('keterangan')->default('Sewa Diklat');
            $table->unsignedBigInteger('kredit')->default(0);

            // Delivery tracking to external eRetribusi app.
            $table->enum('status', ['draft', 'sent', 'failed'])->default('draft');
            $table->json('response_payload')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retribusi_billings');
    }
};
