<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('retribusi_billings', function (Blueprint $table): void {
            // Bapenda e-Retribusi API fields (from /api/v2/prod/retribusi/store)
            $table->string('kode_opd')->nullable()->after('npwrd');
            $table->string('no_ketetapan')->nullable()->after('kode_opd');
            $table->string('kode_rekening_bapenda')->nullable()->after('no_ketetapan');
            $table->string('rekening_id_bapenda')->nullable()->after('kode_rekening_bapenda');
            $table->unsignedBigInteger('nominal')->default(0)->after('rekening_id_bapenda');
            $table->string('tahun')->nullable()->after('nominal');
            $table->date('tgl_expired')->nullable()->after('tahun');
            $table->string('nama_wr')->nullable()->after('tgl_expired');
            $table->string('keterangan_bapenda')->nullable()->after('nama_wr');

            // Response fields from Bapenda store API
            $table->unsignedBigInteger('id_billing')->nullable()->after('keterangan_bapenda');
            $table->string('kodebayar')->nullable()->after('id_billing');
            $table->string('link_ssrd')->nullable()->after('kodebayar');

            // QRIS link (from getLinkQris API)
            $table->string('link_qris')->nullable()->after('link_ssrd');
            $table->json('qris_response_payload')->nullable()->after('link_qris');

            // Payment callback status from Bapenda
            $table->string('payment_callback_status')->nullable()->after('qris_response_payload');
            $table->timestamp('paid_at')->nullable()->after('payment_callback_status');
        });
    }

    public function down(): void
    {
        Schema::table('retribusi_billings', function (Blueprint $table): void {
            $table->dropColumn([
                'kode_opd',
                'no_ketetapan',
                'kode_rekening_bapenda',
                'rekening_id_bapenda',
                'nominal',
                'tahun',
                'tgl_expired',
                'nama_wr',
                'keterangan_bapenda',
                'id_billing',
                'kodebayar',
                'link_ssrd',
                'link_qris',
                'qris_response_payload',
                'payment_callback_status',
                'paid_at',
            ]);
        });
    }
};
