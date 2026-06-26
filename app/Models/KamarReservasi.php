<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KamarReservasi extends Model
{
    protected $fillable = [
        'kode',
        'nama_pemesan',
        'tipe_penyewa',
        'instansi',
        'kegiatan',
        'phone_number',
        'jenis_kelas',
        'jumlah',
        'kamar_id',
        'multiple_kamar',
        'tanggal_masuk',
        'tanggal_keluar',
        'durasi_hari',
        'jumlah_peserta',
        'total_harga',
        'status',
        'payment_status',
        'bukti_pembayaran',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_masuk' => 'date',
            'tanggal_keluar' => 'date',
            'multiple_kamar' => 'boolean',
            'durasi_hari' => 'integer',
            'jumlah' => 'integer',
            'jumlah_peserta' => 'integer',
            'total_harga' => 'integer',
        ];
    }

    public function kamar(): BelongsTo
    {
        return $this->belongsTo(Kamar::class, 'kamar_id');
    }

    // DB produksi tidak punya kamar_id; fallback via jenis_kelas.
    public function getKamarAttribute(): ?Kamar
    {
        if (isset($this->attributes['kamar_id']) && $this->attributes['kamar_id']) {
            return Kamar::find($this->attributes['kamar_id']);
        }

        $jenis = $this->attributes['jenis_kelas'] ?? null;
        if (! $jenis) {
            return null;
        }

        return Kamar::where('jenis_kelas', $jenis)->first();
    }

    public function items(): HasMany
    {
        return $this->hasMany(KamarReservasiItem::class);
    }

    public function retribusiBillings(): HasMany
    {
        return $this->hasMany(RetribusiBilling::class);
    }
}
