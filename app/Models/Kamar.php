<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kamar extends Model
{
    protected $fillable = [
        'kode',
        'nama',
        'gedung',
        'jenis',
        'kapasitas',
        'tersedia',
        'harga_per_malam',
        'status',
        'foto_path',
    ];

    protected function casts(): array
    {
        return [
            'kapasitas' => 'integer',
            'tersedia' => 'integer',
            'harga_per_malam' => 'integer',
        ];
    }

    public function reservasiItems(): HasMany
    {
        return $this->hasMany(KamarReservasiItem::class);
    }
}
