<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kamar_fotos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kamar_id')->constrained('kamars')->cascadeOnDelete();
            $table->string('foto_path');
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();

            $table->index(['kamar_id', 'urutan']);
        });

        // Migrate existing foto_path from kamars to kamar_fotos
        $kamars = \Illuminate\Support\Facades\DB::table('kamars')
            ->whereNotNull('foto_path')
            ->where('foto_path', '!=', '')
            ->get(['id', 'foto_path']);

        foreach ($kamars as $kamar) {
            \Illuminate\Support\Facades\DB::table('kamar_fotos')->insert([
                'kamar_id' => $kamar->id,
                'foto_path' => $kamar->foto_path,
                'urutan' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kamar_fotos');
    }
};
