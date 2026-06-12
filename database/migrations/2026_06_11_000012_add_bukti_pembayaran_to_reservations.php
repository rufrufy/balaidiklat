<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kamar_reservasis', function (Blueprint $table): void {
            if (! Schema::hasColumn('kamar_reservasis', 'bukti_pembayaran')) {
                $table->string('bukti_pembayaran')->nullable()->after('payment_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kamar_reservasis', function (Blueprint $table): void {
            if (Schema::hasColumn('kamar_reservasis', 'bukti_pembayaran')) {
                $table->dropColumn('bukti_pembayaran');
            }
        });
    }
};
