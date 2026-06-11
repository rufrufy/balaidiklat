<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KamarReservasi extends Model
{
    protected $fillable = [
        'kode',
        'nama_pemesan',
        'instansi',
        'kegiatan',
        'phone_number',
        'kamar_id',
        'multiple_kamar',
        'tanggal_masuk',
        'tanggal_keluar',
        'durasi_hari',
        'jumlah_peserta',
        'total_harga',
        'status',
        'payment_status',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_masuk' => 'date',
            'tanggal_keluar' => 'date',
            'multiple_kamar' => 'boolean',
            'durasi_hari' => 'integer',
            'jumlah_peserta' => 'integer',
            'total_harga' => 'integer',
        ];
    }

    public function kamar(): BelongsTo
    {
        return $this->belongsTo(Kamar::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(KamarReservasiItem::class);
    }
}
