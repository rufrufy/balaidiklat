<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE layanan_pengaduans MODIFY COLUMN jenis ENUM('gangguan', 'saran', 'survey') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE layanan_pengaduans MODIFY COLUMN jenis ENUM('gangguan', 'saran') NOT NULL");
    }
};
