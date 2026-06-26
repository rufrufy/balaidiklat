<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addKamarColumns();
        $this->recreateKamarFotos();
        $this->addReservasiItemColumns();
    }

    private function addKamarColumns(): void
    {
        Schema::table('kamars', function (Blueprint $table): void {
            if (! Schema::hasColumn('kamars', 'fasilitas')) {
                $table->text('fasilitas')->nullable()->after('kuota_total');
            }
            if (! Schema::hasColumn('kamars', 'harga_per_malam')) {
                $table->unsignedBigInteger('harga_per_malam')->default(0)->after('fasilitas');
            }
        });
    }

    private function recreateKamarFotos(): void
    {
        Schema::dropIfExists('kamar_fotos');

        Schema::create('kamar_fotos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kamar_id')->constrained('kamars')->cascadeOnDelete();
            $table->string('foto_path');
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();

            $table->index(['kamar_id', 'urutan']);
        });
    }

    private function addReservasiItemColumns(): void
    {
        Schema::table('kamar_reservasi_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('kamar_reservasi_items', 'harga_per_malam')) {
                $table->unsignedBigInteger('harga_per_malam')->default(0)->after('durasi_hari');
            }
            if (! Schema::hasColumn('kamar_reservasi_items', 'subtotal')) {
                $table->unsignedBigInteger('subtotal')->default(0)->after('harga_per_malam');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kamar_reservasi_items', function (Blueprint $table): void {
            foreach (['harga_per_malam', 'subtotal'] as $column) {
                if (Schema::hasColumn('kamar_reservasi_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('kamar_fotos');

        Schema::table('kamars', function (Blueprint $table): void {
            foreach (['fasilitas', 'harga_per_malam'] as $column) {
                if (Schema::hasColumn('kamars', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
