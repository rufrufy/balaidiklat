<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KamarReservasiItem extends Model
{
    protected $fillable = [
        'kamar_reservasi_id',
        'kamar_id',
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
        return $this->belongsTo(Kamar::class);
    }
}
