<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KamarReservasiItem extends Model
{
    // DB produksi: jenis_kelas + jumlah (tanpa kamar_id/jumlah_unit).
    protected $fillable = [
        'kamar_reservasi_id',
        'kamar_id',
        'jenis_kelas',
        'jumlah',
        'jumlah_unit',
        'tanggal_masuk',
        'tanggal_keluar',
        'durasi_hari',
        'harga_per_malam',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_masuk' => 'date',
            'tanggal_keluar' => 'date',
            'jumlah' => 'integer',
            'jumlah_unit' => 'integer',
            'durasi_hari' => 'integer',
            'harga_per_malam' => 'integer',
            'subtotal' => 'integer',
        ];
    }

    public function reservasi(): BelongsTo
    {
        return $this->belongsTo(KamarReservasi::class, 'kamar_reservasi_id');
    }

    public function kamar(): BelongsTo
    {
        return $this->belongsTo(Kamar::class, 'kamar_id');
    }

    // Skema produksi memakai `jumlah`; skema migration memakai `jumlah_unit`.
    public function getJumlahUnitAttribute(): int
    {
        if (array_key_exists('jumlah_unit', $this->attributes) && $this->attributes['jumlah_unit'] !== null) {
            return (int) $this->attributes['jumlah_unit'];
        }

        return (int) ($this->attributes['jumlah'] ?? 1);
    }
}
