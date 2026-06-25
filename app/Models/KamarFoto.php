<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KamarFoto extends Model
{
    protected $fillable = [
        'kamar_id',
        'foto_path',
        'urutan',
    ];

    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
        ];
    }

    public function kamar(): BelongsTo
    {
        return $this->belongsTo(Kamar::class);
    }
}
