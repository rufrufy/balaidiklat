<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatbotState extends Model
{
    protected $fillable = [
        'state_key',
        'label',
        'description',
        'color',
        'sort_order',
        'is_entry_point',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_entry_point' => 'boolean',
        ];
    }
}
