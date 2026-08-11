<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatbotTemplate extends Model
{
    protected $fillable = [
        'key',
        'label',
        'category',
        'content',
        'description',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'updated_at' => 'datetime',
        ];
    }
}
