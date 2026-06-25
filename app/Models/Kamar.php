<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Kamar extends Model
{
    protected $fillable = [
        'jenis_kelas',
        'kuota_total',
        'fasilitas',
        'harga_per_malam',
    ];

    protected function casts(): array
    {
        return [
            'kuota_total' => 'integer',
            'harga_per_malam' => 'integer',
        ];
    }

    public function reservasiItems(): HasMany
    {
        return $this->hasMany(KamarReservasiItem::class, 'jenis_kelas', 'jenis_kelas');
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(KamarFoto::class)->orderBy('urutan');
    }

    public function allFotoPaths(): Collection
    {
        return $this->fotos->isNotEmpty()
            ? $this->fotos->pluck('foto_path')
            : collect();
    }
}
