<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LayananPengaduan extends Model
{
    protected $fillable = [
        'jenis',
        'nama',
        'phone_number',
        'isi',
        'status',
    ];

    public function jenisLabel(): string
    {
        return $this->jenis === 'saran' ? 'Saran' : 'Laporan Gangguan';
    }
}
