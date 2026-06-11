<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kamar extends Model
{
    protected $fillable = [
        'kode',
        'nama',
        'tipe',
        'harga_per_malam',
        'fasilitas',
        'status',
        'foto_path',
    ];

    protected function casts(): array
    {
        return [
            'harga_per_malam' => 'integer',
        ];
    }

    public function reservasiItems(): HasMany
    {
        return $this->hasMany(KamarReservasiItem::class);
    }

    public function tipeLabel(): string
    {
        return $this->tipe === 'ruang_kelas' ? 'Ruang Kelas' : 'Kamar';
    }
}
