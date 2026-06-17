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
        'nomor_kamar',
        'rating',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }

    public function jenisLabel(): string
    {
        return match ($this->jenis) {
            'saran' => 'Saran',
            'survey' => 'Survey Kepuasan',
            default => 'Laporan Gangguan',
        };
    }
}
