<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Kamar extends Model
{
    // DB produksi memakai jenis_kelas + kuota_total + stok_total (tanpa
    // kode/nama/tipe/status/foto_path). Atribut legacy diisi via accessor.
    protected $fillable = [
        'kode',
        'nama',
        'tipe',
        'harga_per_malam',
    ];

    protected function casts(): array
    {
        return [
            'kuota_total' => 'integer',
            'harga_per_malam' => 'integer',
            'kuota_total' => 'integer',
            'stok_total' => 'integer',
        ];
    }

    public function reservasiItems(): HasMany
    {
        return $this->hasMany(KamarReservasiItem::class, 'jenis_kelas', 'jenis_kelas');
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(KamarFoto::class, 'kamar_id', 'id')->orderBy('urutan');
    }

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
