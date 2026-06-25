<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kamar extends Model
{
    protected $fillable = [
        'jenis_kelas',
        'kuota_total',
    ];

    protected function casts(): array
    {
        return [
            'kuota_total' => 'integer',
        ];
    }

    public function reservasiItems(): HasMany
    {
        return $this->hasMany(KamarReservasiItem::class, 'jenis_kelas', 'jenis_kelas');
    }
}
