<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatbotRule extends Model
{
    protected $fillable = [
        'nama',
        'keyword',
        'match_type',
        'state',
        'reply_text',
        'action',
        'next_state',
        'priority',
        'is_active',
        'menu_label',
        'menu_description',
        'menu_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'priority' => 'integer',
            'menu_order' => 'integer',
        ];
    }

    public function matches(string $input, ?string $state): bool
    {
        if ($this->state && $this->state !== $state) {
            return false;
        }

        $keyword = mb_strtolower(trim($this->keyword));
        $message = mb_strtolower(trim($input));

        return match ($this->match_type) {
            'any' => true,
            'exact' => $message === $keyword,
            'starts_with' => str_starts_with($message, $keyword),
            default => str_contains($message, $keyword),
        };
    }
}
