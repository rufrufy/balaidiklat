<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KamarReservasiItem extends Model
{
    protected $fillable = [
        'kamar_reservasi_id',
        'jenis_kelas',
        'jumlah',
        'tanggal_masuk',
        'tanggal_keluar',
        'durasi_hari',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_masuk' => 'date',
            'tanggal_keluar' => 'date',
            'durasi_hari' => 'integer',
            'jumlah' => 'integer',
        ];
    }

    public function reservasi(): BelongsTo
    {
        return $this->belongsTo(KamarReservasi::class, 'kamar_reservasi_id');
    }
}
