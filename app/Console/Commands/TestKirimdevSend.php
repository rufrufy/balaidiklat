<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestKirimdevSend extends Command
{
    protected $signature = 'kirimdev:test {to? : Nomor tujuan E.164 misal 6287738350324}';

    protected $description = 'Test kirim pesan ke kirimdev API + print response/error persis.';

    public function handle(): int
    {
        $to = (string) $this->argument('to') ?: '6287738350324';

        $baseUrl = (string) config('services.kirimchat.base_url');
        $apiKey = (string) config('services.kirimchat.api_key');
        $phoneId = (string) config('services.kirimchat.phone_number_id');

        $this->info('Config:');
        $this->line("  base_url        : {$baseUrl}");
        $this->line("  phone_number_id : {$phoneId}");
        $this->line('  api_key empty   : '.(empty($apiKey) ? 'YES (MASALAH!)' : 'no'));
        $this->line('  api_key prefix  : '.(str_starts_with($apiKey, 'kdv_live_') ? 'kdv_live_ (benar)' : (str_starts_with($apiKey, 'kc_live_') ? 'kc_live_ (SALAH - itu kirim.chat!)' : 'unknown/empty')));
        $this->line("  to              : {$to}");
        $this->newLine();

        if (empty($apiKey)) {
            $this->error('KIRIMCHAT_API_KEY kosong. Isi .env dengan kdv_live_ dari dashboard kirimdev.');
            return self::FAILURE;
        }

        if (empty($phoneId)) {
            $this->error('KIRIMCHAT_PHONE_NUMBER_ID kosong. Isi .env dengan phone_number_id dari dashboard kirimdev.');
            return self::FAILURE;
        }

        $url = rtrim($baseUrl, '/').'/'.$phoneId.'/messages';
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => 'Test dari BalaiDiklat: '.now()->format('H:i:s'), 'preview_url' => true],
        ];

        $this->info('Request:');
        $this->line("  URL     : {$url}");
        $this->line('  payload : '.json_encode($payload));
        $this->newLine();

        try {
            $response = Http::timeout(30)
                ->withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->post($url, $payload);
        } catch (\Throwable $e) {
            $this->error('Connection error: '.$e->getMessage());
            return self::FAILURE;
        }

        $this->info('Response:');
        $this->line("  status : {$response->status()}");
        $this->line('  body   : '.$response->body());

        if ($response->successful()) {
            $this->newLine();
            $this->info('SUCCESS - pesan terkirim/queued. Cek WA tujuan.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->error('FAILED - lihat error code di body atas.');
        $this->line('Kode error umum kirimdev:');
        $this->line('  401 = api_key invalid/salah provider (kc_live vs kdv_live)');
        $this->line('  404 = phone_number_id salah / endpoint salah');
        $this->line('  422 marketing_opted_out = user opt-out marketing');
        $this->line('  422 consent_required = user belum opt-in');
        $this->line('  422 account_restricted = akun WA di-restrict Meta');
        $this->line('  422 outside_24h_window = harus pakai template (di luar 24h)');
        $this->line('  422 upstream_error = akun WA disconnect');

        return self::FAILURE;
    }
}
