<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappSession extends Model
{
    protected $fillable = [
        'phone_number',
        'state',
        'mode',
        'context',
        'last_message_at',
        'human_taken_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'last_message_at' => 'datetime',
            'human_taken_at' => 'datetime',
        ];
    }

    /**
     * Resolve nama customer dari context atau reservasi terbaru.
     */
    public function getCustomerNameAttribute(): ?string
    {
        $ctx = $this->context ?? [];

        if (! empty($ctx['nama'])) {
            return $ctx['nama'];
        }

        // Fallback: cek dari reservasi terbaru dengan phone_number ini
        $reservasi = \App\Models\KamarReservasi::where('phone_number', $this->phone_number)
            ->whereNotNull('nama_pemesan')
            ->latest()
            ->first();

        return $reservasi?->nama_pemesan;
    }
}
