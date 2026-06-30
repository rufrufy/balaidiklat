<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE retribusi_billings MODIFY COLUMN status ENUM('draft', 'sent', 'failed', 'deleted') NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        DB::statement("UPDATE retribusi_billings SET status = 'failed' WHERE status = 'deleted'");
        DB::statement("ALTER TABLE retribusi_billings MODIFY COLUMN status ENUM('draft', 'sent', 'failed') NOT NULL DEFAULT 'draft'");
    }
};
