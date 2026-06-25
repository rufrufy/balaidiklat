<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Restrukturisasi manajemen kamar: hanya butuh jenis kelas + ketersediaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->restructureKamarsTable();
        $this->dropKamarFotosTable();
        $this->restructureKamarReservasisTable();
        $this->restructureKamarReservasiItemsTable();
    }

    private function restructureKamarsTable(): void
    {
        Schema::table('kamars', function (Blueprint $table): void {
            if (! Schema::hasColumn('kamars', 'jenis_kelas')) {
                $table->string('jenis_kelas')->nullable()->after('id');
            }
            if (! Schema::hasColumn('kamars', 'kuota_total')) {
                $table->unsignedInteger('kuota_total')->default(0)->after('jenis_kelas');
            }
        });

        // Migrasi data: pakai kolom lama sebagai seed jenis_kelas sebelum unique index.
        if (Schema::hasColumn('kamars', 'nama')) {
            DB::statement('UPDATE kamars SET jenis_kelas = COALESCE(NULLIF(jenis_kelas, ""), nama) WHERE jenis_kelas IS NULL OR jenis_kelas = ""');
        } elseif (Schema::hasColumn('kamars', 'kode')) {
            DB::statement('UPDATE kamars SET jenis_kelas = COALESCE(NULLIF(jenis_kelas, ""), kode) WHERE jenis_kelas IS NULL OR jenis_kelas = ""');
        }

        // Pastikan tidak ada duplikat: suffix _2, _3 untuk yang sama.
        $this->deduplicateJenisKelas();

        // Tambah unique index setelah data unik.
        $existing = collect(DB::select("SHOW INDEXES FROM kamars WHERE Key_name = 'kamars_jenis_kelas_unique'"));
        if ($existing->isEmpty()) {
            DB::statement('ALTER TABLE kamars ADD UNIQUE KEY kamars_jenis_kelas_unique (jenis_kelas)');
        }

        $this->dropKamarsLegacyColumns();
    }

    /**
     * Suffix duplikat jenis_kelas agar unique sebelum index dibuat.
     */
    private function deduplicateJenisKelas(): void
    {
        $rows = DB::table('kamars')
            ->select('id', 'jenis_kelas')
            ->get();

        $seen = [];
        foreach ($rows as $row) {
            $base = $row->jenis_kelas ?: 'Jenis Kelas';
            $candidate = $base;
            $suffix = 2;
            while (in_array($candidate, $seen, true)) {
                $candidate = $base.' '.$suffix;
                $suffix++;
            }
            $seen[] = $candidate;

            if ($candidate !== $row->jenis_kelas) {
                DB::table('kamars')->where('id', $row->id)->update(['jenis_kelas' => $candidate]);
            }
        }
    }

    private function dropKamarsLegacyColumns(): void
    {
        $legacy = ['kode', 'nama', 'gedung', 'jenis', 'kapasitas', 'tersedia', 'tipe', 'harga_per_malam', 'fasilitas', 'status', 'foto_path'];
        $existing = array_filter($legacy, fn ($c) => Schema::hasColumn('kamars', $c));

        if ($existing === []) {
            return;
        }

        // Drop unique index pada kode dulu (MySQL) sebelum drop kolom.
        $indexes = collect(DB::select("SHOW INDEXES FROM kamars WHERE Key_name = 'kamars_kode_unique'"));
        if ($indexes->isNotEmpty()) {
            DB::statement('ALTER TABLE kamars DROP INDEX kamars_kode_unique');
        }

        Schema::table('kamars', function (Blueprint $table) use ($existing): void {
            $table->dropColumn($existing);
        });
    }

    private function dropKamarFotosTable(): void
    {
        Schema::dropIfExists('kamar_fotos');
    }

    private function restructureKamarReservasisTable(): void
    {
        $this->dropForeignKeyOn('kamar_reservasis', 'kamar_id');

        Schema::table('kamar_reservasis', function (Blueprint $table): void {
            if (! Schema::hasColumn('kamar_reservasis', 'jenis_kelas')) {
                $table->string('jenis_kelas')->nullable()->after('phone_number');
            }
            if (! Schema::hasColumn('kamar_reservasis', 'jumlah')) {
                $table->unsignedInteger('jumlah')->default(1)->after('jenis_kelas');
            }
            if (Schema::hasColumn('kamar_reservasis', 'kamar_id')) {
                $table->dropColumn('kamar_id');
            }
        });

        DB::statement("ALTER TABLE kamar_reservasis MODIFY COLUMN total_harga BIGINT UNSIGNED NOT NULL DEFAULT 0");
    }

    private function restructureKamarReservasiItemsTable(): void
    {
        $this->dropForeignKeyOn('kamar_reservasi_items', 'kamar_id');

        $idx = collect(DB::select("SHOW INDEXES FROM kamar_reservasi_items WHERE Key_name = 'kri_room_date_idx'"));
        if ($idx->isNotEmpty()) {
            DB::statement('ALTER TABLE kamar_reservasi_items DROP INDEX kri_room_date_idx');
        }

        Schema::table('kamar_reservasi_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('kamar_reservasi_items', 'jenis_kelas')) {
                $table->string('jenis_kelas')->nullable()->after('kamar_reservasi_id');
            }
            if (! Schema::hasColumn('kamar_reservasi_items', 'jumlah')) {
                $table->unsignedInteger('jumlah')->default(1)->after('jenis_kelas');
            }

            foreach (['kamar_id', 'harga_per_malam', 'subtotal'] as $column) {
                if (Schema::hasColumn('kamar_reservasi_items', $column)) {
                    $table->dropColumn($column);
                }
            }

            $table->index(['jenis_kelas', 'tanggal_masuk', 'tanggal_keluar'], 'kri_jenis_date_idx');
        });
    }

    private function dropForeignKeyOn(string $table, string $column): void
    {
        $constraints = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
              AND REFERENCED_TABLE_NAME IS NOT NULL
        ", [$table, $column]);

        foreach ($constraints as $constraint) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint->CONSTRAINT_NAME}`");
        }
    }

    public function down(): void
    {
        Schema::table('kamars', function (Blueprint $table): void {
            if (! Schema::hasColumn('kamars', 'kode')) {
                $table->string('kode')->nullable()->unique()->after('id');
            }
            if (! Schema::hasColumn('kamars', 'nama')) {
                $table->string('nama')->nullable()->after('kode');
            }
            if (! Schema::hasColumn('kamars', 'status')) {
                $table->enum('status', ['available', 'limited', 'full', 'maintenance'])->default('available');
            }
        });

        Schema::table('kamars', function (Blueprint $table): void {
            if (Schema::hasColumn('kamars', 'jenis_kelas')) {
                $table->dropUnique('kamars_jenis_kelas_unique');
                $table->dropColumn('jenis_kelas');
            }
            if (Schema::hasColumn('kamars', 'kuota_total')) {
                $table->dropColumn('kuota_total');
            }
        });
    }
};
