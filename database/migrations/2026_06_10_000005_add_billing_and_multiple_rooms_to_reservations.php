<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kamars', function (Blueprint $table): void {
            if (! Schema::hasColumn('kamars', 'harga_per_malam')) {
                $table->unsignedBigInteger('harga_per_malam')->default(0)->after('tersedia');
            }
        });

        Schema::table('kamar_reservasis', function (Blueprint $table): void {
            if (! Schema::hasColumn('kamar_reservasis', 'multiple_kamar')) {
                $table->boolean('multiple_kamar')->default(false)->after('kamar_id');
            }
            if (! Schema::hasColumn('kamar_reservasis', 'durasi_hari')) {
                $table->unsignedInteger('durasi_hari')->default(1)->after('tanggal_keluar');
            }
            if (! Schema::hasColumn('kamar_reservasis', 'total_harga')) {
                $table->unsignedBigInteger('total_harga')->default(0)->after('jumlah_peserta');
            }
            if (! Schema::hasColumn('kamar_reservasis', 'payment_status')) {
                $table->enum('payment_status', ['unpaid', 'partial', 'paid'])->default('unpaid')->after('status');
            }
        });

        if (! Schema::hasTable('kamar_reservasi_items')) {
            Schema::create('kamar_reservasi_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('kamar_reservasi_id')->constrained('kamar_reservasis')->cascadeOnDelete();
                $table->foreignId('kamar_id')->constrained('kamars')->cascadeOnDelete();
                $table->date('tanggal_masuk');
                $table->date('tanggal_keluar');
                $table->unsignedInteger('durasi_hari')->default(1);
                $table->unsignedBigInteger('harga_per_malam')->default(0);
                $table->unsignedBigInteger('subtotal')->default(0);
                $table->timestamps();

                $table->index(['kamar_id', 'tanggal_masuk', 'tanggal_keluar'], 'kri_room_date_idx');
            });

            return;
        }

        Schema::table('kamar_reservasi_items', function (Blueprint $table): void {
            $table->index(['kamar_id', 'tanggal_masuk', 'tanggal_keluar'], 'kri_room_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kamar_reservasi_items');

        Schema::table('kamar_reservasis', function (Blueprint $table): void {
            foreach (['multiple_kamar', 'durasi_hari', 'total_harga', 'payment_status'] as $column) {
                if (Schema::hasColumn('kamar_reservasis', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('kamars', function (Blueprint $table): void {
            if (Schema::hasColumn('kamars', 'harga_per_malam')) {
                $table->dropColumn('harga_per_malam');
            }
        });
    }
};
