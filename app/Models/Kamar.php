<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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

    public function fotos(): HasMany
    {
        return $this->hasMany(KamarFoto::class)->orderBy('urutan');
    }

    /**
     * Get all photo paths from the kamar_fotos table.
     * Falls back to legacy foto_path if no fotos relation records exist.
     */
    public function allFotoPaths(): Collection
    {
        if ($this->fotos->isNotEmpty()) {
            return $this->fotos->pluck('foto_path');
        }

        if ($this->foto_path) {
            return collect([$this->foto_path]);
        }

        return collect();
    }

    public function tipeLabel(): string
    {
        return $this->tipe === 'ruang_kelas' ? 'Ruang Kelas' : 'Kamar';
    }
}
