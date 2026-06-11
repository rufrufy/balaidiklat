<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetribusiBilling extends Model
{
    protected $attributes = [
        'noskpd' => '1111',
        'periode' => '2026',
        'sts_ssrd' => '4 1 2',
        'namapenyetor' => 'BKPP',
        't_nama' => 'BKPP',
        'npwrd' => '123',
        'rekening' => '76|4.1.02.02.01.0005|Retribusi Pemakaian Ruangan Balai Diklat',
        'keterangan' => 'Sewa Diklat',
        'status' => 'draft',
    ];

    protected $fillable = [
        'kamar_reservasi_id',
        'noskpd',
        'periode',
        'sts_ssrd',
        'namapenyetor',
        't_nama',
        'npwrd',
        'rekening',
        'tanggal',
        'keterangan',
        'kredit',
        'status',
        'response_payload',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'kredit' => 'integer',
            'response_payload' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function reservasi(): BelongsTo
    {
        return $this->belongsTo(KamarReservasi::class, 'kamar_reservasi_id');
    }

    /**
     * Build the exact payload expected by the external eRetribusi app.
     */
    public function toRetribusiPayload(): array
    {
        return [
            'noskpd' => $this->noskpd,
            'tanggal' => optional($this->tanggal)->format('d-m-Y'),
            'periode' => $this->periode,
            'sts_ssrd' => $this->sts_ssrd,
            'namapenyetor' => $this->namapenyetor,
            't_nama' => $this->t_nama,
            'npwrd' => $this->npwrd,
            'rekening' => $this->rekening,
            'keterangan' => $this->keterangan,
            'kredit' => (string) $this->kredit,
        ];
    }
}
