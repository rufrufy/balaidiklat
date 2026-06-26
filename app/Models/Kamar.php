<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Kamar extends Model
{
    // DB produksi: jenis_kelas + kuota_total + stok_total + fasilitas + harga_per_malam.
    protected $fillable = [
        'jenis_kelas',
        'kuota_total',
        'stok_total',
        'fasilitas',
        'harga_per_malam',
    ];

    protected function casts(): array
    {
        return [
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
        return str_contains(strtolower((string) $this->jenis_kelas), 'kelas') ? 'Ruang Kelas' : 'Kamar';
    }

    // Kompatibilitas code lama yang akses kode/nama/tipe/status.
    public function getKodeAttribute(): string
    {
        return (string) ($this->attributes['kode'] ?? $this->attributes['jenis_kelas'] ?? $this->id);
    }

    public function getNamaAttribute(): string
    {
        return (string) ($this->attributes['nama'] ?? $this->attributes['jenis_kelas'] ?? 'Kamar');
    }

    public function getTipeAttribute(): string
    {
        if (isset($this->attributes['tipe'])) {
            return $this->attributes['tipe'];
        }

        return str_contains(strtolower((string) $this->jenis_kelas), 'kelas') ? 'ruang_kelas' : 'kamar';
    }

    public function getStatusAttribute(): string
    {
        return $this->attributes['status'] ?? 'available';
    }

    public function getFotoPathAttribute(): ?string
    {
        return $this->attributes['foto_path'] ?? null;
    }
}
