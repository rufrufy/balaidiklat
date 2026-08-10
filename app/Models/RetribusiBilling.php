<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class RetribusiBilling extends Model
{
    protected $attributes = [
        'noskpd' => '1111',
        'periode' => '2026',
        'sts_ssrd' => '4 1 2',
        'namapenyetor' => 'BKPP',
        't_nama' => 'BKPP',
        'npwrd' => '-',
        'rekening' => '76|4.1.02.02.01.0005|Retribusi Pemakaian Ruangan Balai Diklat',
        'keterangan' => 'Sewa Diklat',
        'status' => 'draft',
        'kode_opd' => '3.1.03.01',
        'kode_rekening_bapenda' => '4.1.02.02.01.0005',
        'rekening_id_bapenda' => '76',
        'npwrd' => '-',
        'nama_wr' => 'BKPP',
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
        // Bapenda API fields
        'kode_opd',
        'no_ketetapan',
        'kode_rekening_bapenda',
        'rekening_id_bapenda',
        'nominal',
        'tahun',
        'tgl_expired',
        'nama_wr',
        'keterangan_bapenda',
        'id_billing',
        'kodebayar',
        'link_ssrd',
        'link_qris',
        'link_qris_image',
        'qris_response_payload',
        'payment_callback_status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'tgl_expired' => 'date',
            'kredit' => 'integer',
            'nominal' => 'integer',
            'response_payload' => 'array',
            'qris_response_payload' => 'array',
            'sent_at' => 'datetime',
            'paid_at' => 'datetime',
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

    /**
     * Build query-string payload for Bapenda /api/v2/prod/retribusi/store.
     */
    public function toBapendaStorePayload(): array
    {
        return [
            'kode_opd' => $this->kode_opd ?? config('services.bapenda.kode_opd'),
            'no_ketetapan' => $this->no_ketetapan,
            'keterangan' => $this->keterangan_bapenda ?? '',
            'kode_rekening' => $this->kode_rekening_bapenda ?? config('services.bapenda.kode_rekening'),
            'nominal' => (string) ($this->nominal ?? $this->kredit ?? 0),
            'tahun' => $this->tahun ?? (string) now()->year,
            'rekening_id' => $this->rekening_id_bapenda ?? config('services.bapenda.rekening_id'),
            'tgl_expired' => optional($this->tgl_expired)->format('Y-m-d') ?? now()->addDays(7)->format('Y-m-d'),
            'npwrd' => $this->npwrd ?? '-',
            'nama_wr' => $this->nama_wr ?? 'BKPP',
        ];
    }

    public function isPaid(): bool
    {
        return $this->payment_callback_status === 'paid' || $this->paid_at !== null;
    }

    public function markPaid(): void
    {
        $this->update([
            'payment_callback_status' => 'paid',
            'paid_at' => now(),
        ]);
    }
}
